import Alpine from 'alpinejs';

/**
 * Ang app shell. Dalawang bagay lang ang hawak nito:
 *
 *   drawerOpen - bukas ba ang off-canvas na sidebar sa maliit na screen
 *   collapsed  - naka-icon-only ba ang sidebar sa desktop
 *
 * Naaalala ang `collapsed` sa localStorage para hindi na paulit-ulit
 * i-collapse ng user sa bawat page load. Nakabalot sa try/catch dahil
 * nag-a-throw ang localStorage kapag naka-block ang site data.
 */
Alpine.data('appShell', () => ({
    drawerOpen: false,
    collapsed: false,

    init() {
        try {
            this.collapsed = localStorage.getItem('sidebar-collapsed') === '1';
        } catch (e) {
            // Naka-block ang storage - expanded na lang ang default.
        }
    },

    toggleCollapsed() {
        this.collapsed = !this.collapsed;

        try {
            localStorage.setItem('sidebar-collapsed', this.collapsed ? '1' : '0');
        } catch (e) {
            // Hindi maalala ngayong session - hindi naman ito fatal.
        }
    },

    closeDrawer() {
        this.drawerOpen = false;
    },
}));

window.Alpine = Alpine;

Alpine.start();
