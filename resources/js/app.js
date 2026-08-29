import './bootstrap';
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
        timer: 3000,
        timerProgressBar: true,
        customClass: { popup: 'swal-toast-popup' },
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
        script.onload = () => resolve(window.mapboxgl);
        script.onerror = () => reject(new Error('mapbox-gl failed to load'));
        document.head.appendChild(script);
    });

    return mapboxLoading;
};
