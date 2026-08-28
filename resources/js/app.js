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
