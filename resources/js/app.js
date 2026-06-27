import './bootstrap';
import ApexCharts from 'apexcharts';
import Swal from 'sweetalert2';

window.ApexCharts = ApexCharts;
window.Swal = Swal;

// ── SweetAlert2 toast — listens for swal:toast events from Livewire ──────────
const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3500,
    timerProgressBar: true,
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer);
        toast.addEventListener('mouseleave', Swal.resumeTimer);
    },
});

document.addEventListener('swal:toast', (e) => {
    Toast.fire({
        icon: e.detail.type,   // 'success' | 'error' | 'warning'
        title: e.detail.message,
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
