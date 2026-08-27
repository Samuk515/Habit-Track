$(document).ready(function () {
    $('#subtasks-table').DataTable({
        pageLength: 5,
        pagingType: 'simple_numbers',
        searching: false,
        lengthChange: false,
        info: false,
        ordering: false
    });

    $('#edit-subtask-dialog').dialog({
        autoOpen: false,
        modal: true,
        width: 420,
        buttons: {}
    });

    $('#log-subtask-dialog').dialog({
        autoOpen: false,
        modal: true,
        width: 420,
        buttons: {}
    });

    // If the last save attempt failed validation, reopen the right
    // dialog with exactly what was typed, instead of losing it.
    if (window.FAILED_EDIT) {
        populateEditDialog(window.FAILED_EDIT);
        $('#edit-subtask-dialog').dialog('open');
    }
    if (window.FAILED_LOG) {
        $('#log-subtask-id').val(window.FAILED_LOG.id);
        $('#log-subtask-value').val(window.FAILED_LOG.value);
        $('#log-subtask-unit').val(window.FAILED_LOG.unit);
        $('#clear-log-subtask-id').val(window.FAILED_LOG.id);
        $('#clear-log-form').hide();
        $('#log-subtask-dialog').dialog('open');
    }
});

function populateEditDialog(subtask) {
    $('#edit-subtask-id').val(subtask.id);
    $('#edit-subtask-name').val(subtask.name);
    $('#edit-subtask-description').val(subtask.description || '');
    $('#edit-subtask-optional').prop('checked', !!subtask.is_optional);
}

function openEditSubtaskDialog(subtaskId) {
    var subtasks = window.SUBTASKS_DATA || [];
    var subtask = subtasks.find(function (s) { return s.id === subtaskId; });
    if (!subtask) return;

    populateEditDialog(subtask);
    $('#edit-subtask-dialog').dialog('open');
}

function openLogSubtaskDialog(subtaskId) {
    var subtasks = window.SUBTASKS_DATA || [];
    var subtask = subtasks.find(function (s) { return s.id === subtaskId; });
    if (!subtask) return;

    $('#log-subtask-id').val(subtask.id);
    $('#log-subtask-value').val(subtask.today_value !== null ? subtask.today_value : '');
    $('#log-subtask-unit').val(subtask.today_unit || '');
    $('#clear-log-subtask-id').val(subtask.id);

    if (subtask.logged_today) {
        $('#clear-log-form').show();
    } else {
        $('#clear-log-form').hide();
    }

    $('#log-subtask-dialog').dialog('open');
}