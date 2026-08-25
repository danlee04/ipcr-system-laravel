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
