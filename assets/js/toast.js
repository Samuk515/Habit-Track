// Reads success query parameters from the URL and shows a
// SweetAlert2 toast notification for each one. After firing, the
// success param is removed from the URL so a refresh doesn't repeat it.
(function () {
    var params = new URLSearchParams(window.location.search);
    var success = params.get('success');
    if (!success) {
        return;
    }

    var messages = {
        add: 'Added successfully.',
        update: 'Updated successfully.',
        delete: 'Deleted successfully.',
        log: 'Log saved.',
        clear_log: 'Log cleared.',
        toggle_active: 'Reminder toggled.'
    };

    var title = messages[success];
    if (!title) {
        return;
    }

    var toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: function (toastEl) {
            toastEl.addEventListener('mouseenter', Swal.stopTimer);
            toastEl.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });

    toast.fire({
        icon: 'success',
        title: title
    });

    params.delete('success');
    var newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
    window.history.replaceState({}, '', newUrl);
})();
