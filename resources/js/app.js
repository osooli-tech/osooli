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
