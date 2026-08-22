document.addEventListener('DOMContentLoaded', () => {
    // --- Extra-reminder label field toggle ---
    // This block was missing from the live file — it's what makes the
    // label input appear when "Extra" is chosen in the subtask dropdown.
    const select = document.getElementById('reminder-subtask-select');
    const labelWrapper = document.getElementById('reminder-label-wrapper');

    function toggleLabelField() {
        if (!select || !labelWrapper) return;
        labelWrapper.style.display = select.value === 'extra' ? 'block' : 'none';
    }

    if (select && labelWrapper) {
        select.addEventListener('change', toggleLabelField);
        toggleLabelField();
    }

    // --- Notifications ---
    const btn = document.getElementById('enable-notifications-btn');
    const status = document.getElementById('notification-status');
    if (!btn || !status) return;

    const reminders = window.ACTIVE_REMINDERS || [];
    const notified = new Set();

    const updateStatus = (msg) => {
        status.textContent = msg;
    };

    const requestPermission = async () => {
        if (!('Notification' in window)) {
            updateStatus('Notifications not supported in this browser.');
            return;
        }
        if (Notification.permission === 'granted') {
            updateStatus('Notifications enabled.');
            btn.style.display = 'none';
            return;
        }
        const perm = await Notification.requestPermission();
        if (perm === 'granted') {
            updateStatus('Notifications enabled.');
            btn.style.display = 'none';
        } else {
            updateStatus('Notification permission denied.');
        }
    };

    btn.addEventListener('click', requestPermission);

    if (Notification.permission === 'granted') {
        btn.style.display = 'none';
        updateStatus('Notifications enabled.');
    }

    const checkReminders = () => {
        const now = new Date();
        const currentTime = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');

        reminders.forEach((r) => {
            if (r.time !== currentTime) return;

            const key = r.id + '-' + currentTime;
            if (notified.has(key)) return;
            notified.add(key);

            if (Notification.permission === 'granted') {
                new Notification(r.label || 'Reminder', {
                    body: r.time + ' · ' + r.type,
                    icon: '/assets/images/icon.png',
                });
            }
        });

        setTimeout(() => {
            notified.forEach((key) => {
                const [id, time] = key.split('-');
                if (time !== currentTime) {
                    notified.delete(key);
                }
            });
        }, 60000);
    };

    checkReminders();
    setInterval(checkReminders, 30000);
});