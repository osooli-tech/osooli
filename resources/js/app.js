import './bootstrap';
import './date-picker';
import ApexCharts from 'apexcharts';
import Swal from 'sweetalert2';

window.ApexCharts = ApexCharts;
window.Swal = Swal;

// ── Toast notifications (dispatched from Livewire via $this->dispatch('toast')) ─
window.addEventListener('toast', (e) => {
    const { type, message } = e.detail;

    const iconMap = { success: 'success', error: 'error', warning: 'warning', info: 'info' };

    Swal.fire({
        icon: iconMap[type] ?? 'info',
        title: message,
        showConfirmButton: false,
        timer: type === 'warning' ? 9000 : 3000,
        timerProgressBar: true,
        customClass: { popup: 'swal-toast-popup' },
    });
});

// ── Report language (any link marked data-report-language) ────────────────────
// Printing asks first whether the report should be in Arabic or English; the
// choice travels as ?lang= and applies to that report only. Delegated from
// the document so links Livewire renders later are covered too.
document.addEventListener('click', (event) => {
    const link = event.target.closest('a[data-report-language]');
    if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.button !== 0) {
        return;
    }

    event.preventDefault();
    const text = window.reportLanguageText ?? {};

    Swal.fire({
        title: text.title ?? 'Report language',
        icon: 'question',
        showDenyButton: true,
        showCancelButton: true,
        confirmButtonText: text.arabic ?? 'العربية',
        denyButtonText: text.english ?? 'English',
        cancelButtonText: text.cancel ?? 'Cancel',
        confirmButtonColor: '#006c4e',
        denyButtonColor: '#002444',
    }).then((result) => {
        if (result.isDismissed) {
            return;
        }

        const url = new URL(link.href, window.location.origin);
        url.searchParams.set('lang', result.isConfirmed ? 'ar' : 'en');
        window.location.href = url.toString();
    });
});

// ── Notification sound (dispatched from NotificationBell when unread count rises) ─
window.addEventListener('play-notification-sound', () => {
    new Audio('/sounds/notification.wav').play().catch(() => {
        // Autoplay can be blocked before the visitor's first interaction with
        // the page — missing the chime once is harmless, so this is silent.
    });
});

// ── SweetAlert2 delete confirmation — called from Blade via onclick ───────────
window.confirmDeleteRole = function (roleId, roleName, confirmText, cancelText, titleText) {
    Swal.fire({
        title: titleText,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: confirmText,
        cancelButtonText: cancelText,
        reverseButtons: true,
    }).then((result) => {
        if (result.isConfirmed) {
            window.Livewire.dispatch('deleteRole', { roleId });
        }
    });
};

// ── Mapbox loader ──────────────────────────────────────────────────────────
// mapbox-gl comes from the CDN, not the Vite bundle, because it cannot be
// bundled with the app's WebWorker setup. Loaded via JS rather than a <script>
// tag: a static tag — even deferred — blocks DOMContentLoaded until it
// resolves, and on a network that cannot reach api.mapbox.com that is a
// multi-second stall on every single page before Alpine or Livewire can start.
// This way the rest of the page runs immediately; only the map itself waits.
let mapboxLoading = null;

window.loadMapbox = function loadMapbox() {
    if (window.mapboxgl) {
        return Promise.resolve(window.mapboxgl);
    }
    if (mapboxLoading) {
        return mapboxLoading;
    }

    const css = document.createElement('link');
    css.rel = 'stylesheet';
    css.href = 'https://api.mapbox.com/mapbox-gl-js/v3.3.0/mapbox-gl.css';
    document.head.appendChild(css);

    mapboxLoading = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = 'https://api.mapbox.com/mapbox-gl-js/v3.3.0/mapbox-gl.js';
        script.onload = () => {
            // Without this, Mapbox GL draws Arabic as isolated letters in
            // reverse order. Set here, once, so every map on the site —
            // the dashboard, the owners page, the polygon and boundary
            // editors — shapes Arabic labels properly.
            if (window.mapboxgl.getRTLTextPluginStatus() === 'unavailable') {
                window.mapboxgl.setRTLTextPlugin(
                    'https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-rtl-text/v0.3.0/mapbox-gl-rtl-text.js',
                    null,
                    true
                );
            }
            resolve(window.mapboxgl);
        };
        script.onerror = () => reject(new Error('mapbox-gl failed to load'));
        document.head.appendChild(script);
    });

    return mapboxLoading;
};
