document.addEventListener('DOMContentLoaded', () => {
    const grid = document.getElementById('cal-grid');
    const monthLabel = document.getElementById('cal-month-label');
    const dayDetail = document.getElementById('cal-day-detail');
    const prevBtn = document.getElementById('cal-prev');
    const nextBtn = document.getElementById('cal-next');
    if (!grid) return;

    const events = window.CALENDAR_EVENTS || [];
    const eventsByDate = {};
    events.forEach((event) => {
        if (!eventsByDate[event.date]) {
            eventsByDate[event.date] = [];
        }
        eventsByDate[event.date].push(event);
    });

    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'];
    const weekdayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    const today = new Date();
    let viewYear = today.getFullYear();
    let viewMonth = today.getMonth();

    function dateKey(year, month, day) {
        return year + '-' + String(month + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
    }

    function escapeHtml(value) {
        const element = document.createElement('div');
        element.textContent = value;
        return element.innerHTML;
    }

    function showDay(key, dayEvents) {
        if (dayEvents.length === 0) {
            dayDetail.innerHTML = '<p class="cal-detail-empty">No activity on ' + key + '.</p>';
            return;
        }

        let html = '<h3>' + key + '</h3><ul class="cal-detail-list">';
        dayEvents.forEach((event) => {
            html += '<li><strong>' + escapeHtml(event.habit) + '</strong> — '
                + escapeHtml(event.label) + '</li>';
        });
        dayDetail.innerHTML = html + '</ul>';
    }

    function render() {
        monthLabel.textContent = monthNames[viewMonth] + ' ' + viewYear;
        grid.innerHTML = '';

        weekdayNames.forEach((name) => {
            const cell = document.createElement('div');
            cell.className = 'cal-weekday';
            cell.textContent = name;
            grid.appendChild(cell);
        });

        const firstWeekday = new Date(viewYear, viewMonth, 1).getDay();
        const daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();
        const todayKey = dateKey(today.getFullYear(), today.getMonth(), today.getDate());

        for (let index = 0; index < firstWeekday; index++) {
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
            if (key === todayKey) cell.classList.add('cal-day-today');
            if (dayEvents.length > 0) cell.classList.add('cal-day-has-events');
            cell.innerHTML = '<span class="cal-day-number">' + day + '</span>'
                + (dayEvents.length > 0 ? '<span class="cal-day-dot"></span>' : '');
            cell.addEventListener('click', () => showDay(key, dayEvents));
            grid.appendChild(cell);
        }
    }

    prevBtn.addEventListener('click', () => {
        viewMonth--;
        if (viewMonth < 0) {
            viewMonth = 11;
            viewYear--;
        }
        render();
    });

    nextBtn.addEventListener('click', () => {
        viewMonth++;
        if (viewMonth > 11) {
            viewMonth = 0;
            viewYear++;
        }
        render();
    });

    render();
});