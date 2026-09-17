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
    const notificationsEnabled = window.NOTIFICATIONS_ENABLED !== false;
    const notificationsSupported = 'Notification' in window;

    if (window.NOTIFICATIONS_ENABLED === false) {
        if (status) status.textContent = 'Notifications are disabled in Settings.';
        if (btn) btn.disabled = true;
        return;
    }

    const notified = new Set();

    const updateStatus = (msg) => {
        status.textContent = msg;
    };

    const requestPermission = async () => {
        if (!notificationsEnabled) {
            updateStatus('Notifications are disabled in Settings.');
            return;
        }
        if (!notificationsSupported) {
            updateStatus('Notifications not supported in this browser.');
            return;
        }
        if (Notification.permission === 'granted') {
            updateStatus('Notifications enabled.');
            btn.style.display = 'none';
            return;
        }
        try {
            const perm = await Notification.requestPermission();
            if (perm === 'granted') {
                updateStatus('Notifications enabled.');
                btn.style.display = 'none';
            } else {
                updateStatus('Notification permission denied.');
            }
        } catch (error) {
            updateStatus('Unable to enable notifications in this browser.');
        }
    };

    btn.addEventListener('click', requestPermission);

    if (!notificationsEnabled) {
        btn.style.display = 'none';
        updateStatus('Notifications are disabled in Settings.');
    } else if (!notificationsSupported) {
        btn.style.display = 'none';
        updateStatus('Notifications not supported in this browser.');
    } else if (Notification.permission === 'granted') {
        btn.style.display = 'none';
        updateStatus('Notifications enabled.');
    }

    const checkReminders = () => {
        const now = new Date();
        const currentTime = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
        const today = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0')
            + '-' + String(now.getDate()).padStart(2, '0');

        reminders.forEach((r) => {
            if (r.time !== currentTime) return;

            const key = r.type === 'once' ? 'habit-track-reminder-once-' + r.id : r.id + '-' + today;
            if (r.type === 'once' && localStorage.getItem(key) === '1') return;
            if (notified.has(key)) return;
            notified.add(key);

            if (notificationsEnabled && notificationsSupported && Notification.permission === 'granted') {
                new Notification(r.label || 'Reminder', {
                    body: r.time + ' · ' + r.type,
                    icon: '/assets/images/icon.png',
                });
                if (r.type === 'once') {
                    localStorage.setItem(key, '1');
                }
            }
        });
    };

    checkReminders();
    setInterval(checkReminders, 30000);
});