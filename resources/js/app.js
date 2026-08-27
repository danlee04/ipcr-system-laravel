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

/**
 * A filtered list that answers as you type.
 *
 * The form underneath is an ordinary GET form and still works on its own: this
 * only intercepts it, asks the same URL for the rows alone, and swaps them in.
 * The server keeps deciding what matches, so searching, filtering and paging
 * mean exactly what they meant before - there is no second implementation of
 * the rules living in the browser.
 *
 * Anything that goes wrong falls back to loading the URL properly, which is
 * the behaviour we started from.
 */
Alpine.data('liveList', (action) => ({
    busy: false,
    timer: null,
    request: null,

    init() {
        const form = this.form();

        if (!form) {
            return;
        }

        // Listeners live on the form, never on the results: the rows contain
        // forms and selects of their own, and typing in a row's edit modal
        // must not set off a search.
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            this.load();
        });

        form.addEventListener('input', (event) => {
            if (event.target.matches('input[type="search"], input[type="text"]')) {
                this.schedule();
            }
        });

        form.addEventListener('change', (event) => {
            if (!event.target.matches('select, input[type="date"], input[type="checkbox"], input[type="radio"]')) {
                return;
            }

            // After Alpine has settled, not during the event. Choosing a
            // division clears the section beside it, and Alpine writes that
            // back to the DOM on its own flush - read the form any sooner and
            // the request carries the section that was just cleared.
            this.$nextTick(() => this.load());
        });

        // Paging, and anything else in the rows pointing back at this list.
        this.$root.addEventListener('click', (event) => {
            const link = event.target.closest('[data-live-results] a[href]');

            if (!link || !link.href.startsWith(action)) {
                return;
            }

            event.preventDefault();
            this.load(link.href);
        });
    },

    form() {
        return this.$root.querySelector('[data-live-form]');
    },

    results() {
        return this.$root.querySelector('[data-live-results]');
    },

    /** Typing waits for a pause. One request per word, not one per letter. */
    schedule() {
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.load(), 300);
    },

    /** Everything filled in, as a query string. Blanks are left out. */
    query() {
        const params = new URLSearchParams();

        new FormData(this.form()).forEach((value, key) => {
            if (String(value).trim() !== '') {
                params.append(key, value);
            }
        });

        return params.toString();
    },

    async load(url = null) {
        // A changed filter starts at page one. Only a paging link says
        // otherwise, and it carries its own page in the URL.
        const query = this.query();
        const target = url ?? (query === '' ? action : `${action}?${query}`);

        this.request?.abort();
        this.request = new AbortController();
        this.busy = true;

        try {
            const response = await fetch(target, {
                headers: { 'X-Live-List': '1', 'X-Requested-With': 'XMLHttpRequest' },
                signal: this.request.signal,
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`Responded ${response.status}`);
            }

            this.results().innerHTML = await response.text();
            this.syncExports(query);

            // Replaced rather than pushed: a history entry per keystroke would
            // bury whatever the user was looking at before they arrived.
            window.history.replaceState({}, '', target);
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            window.location.href = target;
        } finally {
            this.busy = false;
        }
    },

    /**
     * A download link outside the rows would otherwise still carry the filters
     * the page was loaded with, and quietly hand over the wrong sheet.
     */
    syncExports(query) {
        this.$root.querySelectorAll('[data-live-export]').forEach((link) => {
            const base = link.dataset.liveExport;

            link.href = query === '' ? base : `${base}?${query}`;
        });
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
