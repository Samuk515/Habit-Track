document.addEventListener('DOMContentLoaded', () => {
    const grid = document.getElementById('cal-grid');
    const monthLabel = document.getElementById('cal-month-label');
    const dayDetail = document.getElementById('cal-day-detail');
    const prevBtn = document.getElementById('cal-prev');
    const nextBtn = document.getElementById('cal-next');
    if (!grid) return;

    // window.CALENDAR_EVENTS is embedded by calendar.php as a plain
    // JSON array before this file loads: [{date, label, habit}, ...]
    const events = window.CALENDAR_EVENTS || [];

    // Group events by "YYYY-MM-DD" so each day cell can look itself
    // up directly instead of scanning the whole list every render.
    const eventsByDate = {};
    events.forEach((e) => {
        if (!eventsByDate[e.date]) {
            eventsByDate[e.date] = [];
        }
        eventsByDate[e.date].push(e);
    });

    const MONTH_NAMES = ['January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'];
    const WEEKDAY_NAMES = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    const today = new Date();
    let viewYear = today.getFullYear();
    let viewMonth = today.getMonth(); // 0 = January

    function pad(n) {
        return n < 10 ? '0' + n : String(n);
    }

    function dateKey(year, month, day) {
        return year + '-' + pad(month + 1) + '-' + pad(day);
    }

    // Escapes text before it goes into innerHTML — event labels and
    // habit names come from user input (subtask names), so this
    // matters even though the page itself is read-only.
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function render() {
        monthLabel.textContent = MONTH_NAMES[viewMonth] + ' ' + viewYear;
        grid.innerHTML = '';

        WEEKDAY_NAMES.forEach((name) => {
            const cell = document.createElement('div');
            cell.className = 'cal-weekday';
            cell.textContent = name;
            grid.appendChild(cell);
        });

        const firstWeekday = new Date(viewYear, viewMonth, 1).getDay();
        const daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();
        const todayKey = dateKey(today.getFullYear(), today.getMonth(), today.getDate());

        for (let i = 0; i < firstWeekday; i++) {
            const blank = document.createElement('div');
            blank.className = 'cal-day cal-day-empty';
            grid.appendChild(blank);
        }

        for (let day = 1; day <= daysInMonth; day++) {
            const key = dateKey(viewYear, viewMonth, day);
            const dayEvents = eventsByDate[key] || [];

            const cell = document.createElement('button');
            cell.type = 'button';
            cell.className = 'cal-day';
            if (key === todayKey) {
                cell.className += ' cal-day-today';
            }
            if (dayEvents.length > 0) {
                cell.className += ' cal-day-has-events';
            }

            cell.innerHTML = '<span class="cal-day-number">' + day + '</span>'
                + (dayEvents.length > 0 ? '<span class="cal-day-dot"></span>' : '');

            cell.addEventListener('click', () => showDay(key, dayEvents));
            grid.appendChild(cell);
        }
    }

    function showDay(key, dayEvents) {
        if (dayEvents.length === 0) {
            dayDetail.innerHTML = '<p class="cal-detail-empty">No activity on ' + key + '.</p>';
            return;
        }

        let html = '<h3>' + key + '</h3><ul class="cal-detail-list">';
        dayEvents.forEach((e) => {
            html += '<li><strong>' + escapeHtml(e.habit) + '</strong> — ' + escapeHtml(e.label) + '</li>';
        });
        html += '</ul>';
        dayDetail.innerHTML = html;
    }

    prevBtn.addEventListener('click', () => {
        viewMonth -= 1;
        if (viewMonth < 0) {
            viewMonth = 11;
            viewYear -= 1;
        }
        render();
    });

    nextBtn.addEventListener('click', () => {
        viewMonth += 1;
        if (viewMonth > 11) {
            viewMonth = 0;
            viewYear += 1;
        }
        render();
    });

    render();
});