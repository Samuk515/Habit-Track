$(document).ready(function () {
    $('#habits-table').DataTable({
        pageLength: 5,
        pagingType: 'simple_numbers', // Previous / 1 2 3 / Next — matches Google's numbered style
        searching: false,
        lengthChange: false,
        info: false,
        ordering: false // no sort arrows — plain pagination only, as originally asked for
    });

    $('#edit-habit-dialog').dialog({
        autoOpen: false,
        modal: true,
        width: 480,
        buttons: {}
    });

    // If the last save attempt failed validation, reopen the dialog
    // with exactly what was typed, instead of losing it.
    if (window.FAILED_EDIT) {
        populateEditDialog(window.FAILED_EDIT);
        $('#edit-habit-dialog').dialog('open');
    }
});

function formatMinutesAsTime(minutes) {
    var hh = Math.floor(minutes / 60);
    var mm = minutes % 60;
    return (hh < 10 ? '0' : '') + hh + ':' + (mm < 10 ? '0' : '') + mm;
}

function populateEditDialog(habit) {
    $('#edit-habit-id').val(habit.id);
    $('#edit-habit-name').val(habit.name);
    $('#edit-habit-category').val(habit.category_id);
    $('#edit-habit-nature').val(habit.nature);

    // Trigger the measurement-type change FIRST — habits.js swaps the
    // target-value input between type="number" and type="time" based
    // on this selection. Setting a time-formatted string into a
    // number input before that swap happens gets silently rejected
    // by the browser, so ordering here matters.
    $('#edit-habit-measurement').val(habit.measurement_type).trigger('change');

    var targetDisplay = '';
    if (habit.target_value !== null) {
        targetDisplay = habit.measurement_type === 'time_of_day'
            ? formatMinutesAsTime(habit.target_value)
            : habit.target_value;
    }
    $('#edit-habit-target-value').val(targetDisplay);

    $('#edit-habit-target-type').val(habit.target_type);
    $('#edit-habit-description').val(habit.description || '');
}

function openEditHabitDialog(habitId) {
    var habits = window.HABITS_DATA || [];
    var habit = habits.find(function (h) { return h.id === habitId; });
    if (!habit) return;

    populateEditDialog(habit);
    $('#edit-habit-dialog').dialog('open');
}