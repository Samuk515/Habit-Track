document.addEventListener('DOMContentLoaded', () => {

    // Password show/hide toggles
    document.querySelectorAll('.toggle-password').forEach(btn => {
        btn.addEventListener('click', () => {
            const target = document.getElementById(btn.dataset.target);
            if (!target) return;
            const isHidden = target.type === 'password';
            target.type = isHidden ? 'text' : 'password';
            btn.textContent = isHidden ? '🙈' : '👁';
        });
    });

    // Live password-match feedback (register page only — these
    // elements don't exist on login.php, so the checks below no-op there)
    const pw = document.getElementById('password');
    const confirmPw = document.getElementById('confirm_password');
    const feedback = document.getElementById('match-feedback');

    if (pw && confirmPw && feedback) {
        const checkMatch = () => {
            if (confirmPw.value === '') {
                feedback.textContent = '';
                feedback.className = 'match-feedback';
                return;
            }
            if (pw.value === confirmPw.value) {
                feedback.textContent = '✓ Passwords match';
                feedback.className = 'match-feedback match';
            } else {
                feedback.textContent = '✕ Passwords do not match';
                feedback.className = 'match-feedback no-match';
            }
        };
        pw.addEventListener('input', checkMatch);
        confirmPw.addEventListener('input', checkMatch);
    }

    // Submit-button locking — this is UX polish to stop double-submits
    // on slow connections. It is NOT what stops duplicate DB rows —
    // the UNIQUE(email) constraint / uniqueness check server-side does that.
    const form = document.querySelector('form');
    const submitBtn = document.getElementById('submit-btn');
    if (form && submitBtn) {
        form.addEventListener('submit', () => {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Please wait...';
        });
    }
});