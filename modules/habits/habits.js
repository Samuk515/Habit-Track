document.addEventListener('DOMContentLoaded', () => {
    // Types with no meaningful numeric target — field hides entirely.
    const NO_TARGET_TYPES = ['boolean', 'partial'];
    // time_of_day swaps the input itself to type="time" instead of "number".
    const TIME_TYPES = ['time_of_day'];

    const PLACEHOLDERS = {
        count: 'Target count',
        duration: 'Target duration (minutes)',
        weight: 'Target weight (kg)',
        distance: 'Target distance (km)',
        rating: 'Target rating (1–5)',
        percentage: 'Target percentage (0–100)',
        steps: 'Target steps',
        custom: 'Target value',
        money: 'Target amount (Rs.)',
        score: 'Target score',
        volume: 'Target volume (ml)',
    };

    // querySelectorAll matters here: the Add form and an open Edit
    // form (if any) each have their own .measurement-select, and this
    // needs to control both independently.
    document.querySelectorAll('.measurement-select').forEach((select) => {
        const form = select.closest('form');
        const wrapper = form ? form.querySelector('.target-value-field') : null;
        const input = wrapper ? wrapper.querySelector('input[name="target_value"]') : null;
        if (!wrapper || !input) return;

        const update = () => {
            const type = select.value;

            if (NO_TARGET_TYPES.includes(type)) {
                wrapper.style.display = 'none';
                return;
            }
            wrapper.style.display = 'block';

            if (TIME_TYPES.includes(type)) {
                input.type = 'time';
                input.placeholder = '';
            } else {
                input.type = 'number';
                input.placeholder = PLACEHOLDERS[type] || 'Target value';
            }
        };

        select.addEventListener('change', update);
        update(); // correct initial state on page load — matters for
                  // the edit form, which loads with a type already selected
    });
});