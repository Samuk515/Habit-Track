// Shared across every page in the app — a delete button opts into a
// SweetAlert2 confirmation just by having a data-confirm-message
// attribute and type="button" instead of type="submit". No per-page
// duplication needed; one listener here covers Categories, Habits,
// Subtasks, and Reminders alike.
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-confirm-message]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var form = btn.closest('form');
            var message = btn.getAttribute('data-confirm-message');

            Swal.fire({
                title: 'Are you sure?',
                text: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete it',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#FF6B6B',
                cancelButtonColor: '#8A92A6'
            }).then(function (result) {
                if (result.isConfirmed && form) {
                    form.submit();
                }
            });
        });
    });
});