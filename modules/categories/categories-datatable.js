$(document).ready(function () {
    $('#categories-table').DataTable({
        pageLength: 5,
        pagingType: 'simple_numbers', // Previous / 1 2 3 / Next — matches Habits' pagination
        searching: false,
        lengthChange: false,
        info: false,
        columnDefs: [
            { orderable: false, targets: -1 } // Actions column shouldn't be sortable
        ]
    });

    $('#edit-category-dialog').dialog({
        autoOpen: false,
        modal: true,
        width: 420,
        buttons: {}
    });

    // If the last save attempt failed validation, reopen the dialog
    // with exactly what was typed, instead of losing it.
    if (window.FAILED_EDIT) {
        populateEditDialog(window.FAILED_EDIT);
        $('#edit-category-dialog').dialog('open');
    }
});

function populateEditDialog(category) {
    $('#edit-category-id').val(category.id);
    $('#edit-category-name').val(category.name);
    $('#edit-category-description').val(category.description || '');
}

function openEditCategoryDialog(categoryId) {
    var categories = window.CATEGORIES_DATA || [];
    var category = categories.find(function (c) { return c.id === categoryId; });
    if (!category) return;

    populateEditDialog(category);
    $('#edit-category-dialog').dialog('open');
}