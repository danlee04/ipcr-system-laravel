import Alpine from 'alpinejs';

/**
 * The app shell. It holds just two things:
 *
 *   drawerOpen - is the off-canvas sidebar open on a small screen
 *   collapsed  - is the sidebar reduced to icons only on desktop
 *
 * `collapsed` is remembered in localStorage so the user does not have to
 * collapse it again on every page load. Wrapped in try/catch because
 * localStorage throws when site data is blocked.
 */
Alpine.data('appShell', () => ({
    drawerOpen: false,
    collapsed: false,

    init() {
        try {
            this.collapsed = localStorage.getItem('sidebar-collapsed') === '1';
        } catch (e) {
            // Storage is blocked - fall back to the expanded default.
        }
    },

    toggleCollapsed() {
        this.collapsed = !this.collapsed;

        try {
            localStorage.setItem('sidebar-collapsed', this.collapsed ? '1' : '0');
        } catch (e) {
            // Not remembered this session - hardly fatal.
        }
    },

    closeDrawer() {
        this.drawerOpen = false;
    },
}));

window.Alpine = Alpine;

Alpine.start();

/**
 * Dashboard charts.
 *
 * Chart.js is bundled rather than pulled from a CDN, and only the pieces
 * actually used are registered - the whole library is roughly three times the
 * size of everything else in this file.
 *
 * A canvas opts in with data-chart="doughnut|bar" and carries its numbers in
 * data-chart-config, so the server stays the single source of the figures.
 */
import {
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    DoughnutController,
    Legend,
    LinearScale,
    Tooltip,
} from 'chart.js';

Chart.register(
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    DoughnutController,
    Legend,
    LinearScale,
    Tooltip,
);

const axisColor = 'rgba(0, 0, 0, 0.35)';
const gridColor = 'rgba(0, 0, 0, 0.05)';

function renderChart(canvas) {
    let config;

    try {
        config = JSON.parse(canvas.dataset.chartConfig ?? '{}');
    } catch (e) {
        return; // Malformed payload: leave the canvas blank rather than throw.
    }

    if (canvas.dataset.chart === 'doughnut') {
        const total = config.data.reduce((sum, n) => sum + n, 0);

        new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: config.labels,
                datasets: [{
                    data: config.data,
                    backgroundColor: config.colors,
                    borderWidth: 0,
                    hoverOffset: 6,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (item) => total > 0
                                ? ` ${item.label}: ${item.parsed} (${Math.round(item.parsed / total * 100)}%)`
                                : ` ${item.label}: ${item.parsed}`,
                        },
                    },
                },
            },
        });

        return;
    }

    if (canvas.dataset.chart === 'bar') {
        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: config.labels,
                datasets: [
                    { label: 'Total', data: config.total, backgroundColor: '#D6E8F7', borderRadius: 5, order: 2 },
                    { label: 'Approved', data: config.approved, backgroundColor: '#639922', borderRadius: 5, order: 1 },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { color: axisColor, boxWidth: 10, padding: 12, font: { size: 11 } },
                    },
                },
                scales: {
                    x: { grid: { color: gridColor }, ticks: { color: axisColor, font: { size: 11 } } },
                    y: {
                        beginAtZero: true,
                        grid: { color: gridColor },
                        ticks: { color: axisColor, font: { size: 11 }, precision: 0 },
                    },
                },
            },
        });
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('canvas[data-chart]').forEach(renderChart);
});
