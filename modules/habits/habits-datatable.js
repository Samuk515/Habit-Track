$(document).ready(function () {
    $('#habits-table').DataTable({
        pageLength: 5,
        pagingType: 'simple_numbers',
        searching: false,
        lengthChange: false,
        info: false,
        ordering: false
    });

    $('#edit-habit-dialog').dialog({
        autoOpen: false,
        modal: true,
        width: 480,
        buttons: {}
    });

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
