import './bootstrap';
import Alpine from 'alpinejs';

Alpine.data('themeManager', () => ({
    isDark: localStorage.getItem('theme') === 'dark' ||
            (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches),
    toggle() {
        this.isDark = !this.isDark;
        localStorage.setItem('theme', this.isDark ? 'dark' : 'light');
    },
}));

window.Alpine = Alpine;
Alpine.start();
