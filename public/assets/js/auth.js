document.addEventListener('DOMContentLoaded', function () {
  setupPasswordToggles();
  setupRegisterForm();
  setupLoginForm();
});

function setupPasswordToggles() {
  var toggles = document.querySelectorAll('.toggle-password');
  toggles.forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = document.getElementById(btn.dataset.target);
      if (!input) return;
      var showing = input.type === 'text';
      input.type = showing ? 'password' : 'text';
      btn.textContent = showing ? 'Show' : 'Hide';
    });
  });
}

function lockSubmitButton(form) {
  var btn = form.querySelector('button[type="submit"]');
  if (btn) {
    btn.disabled = true;
    btn.textContent = 'Please wait...';
  }
}

function showClientErrors(errors) {
  var box = document.getElementById('client-error-box');
  if (!box) return;
  box.innerHTML = '';
  errors.forEach(function (msg) {
    var line = document.createElement('div');
    line.textContent = msg;
    box.appendChild(line);
  });
  box.style.display = errors.length ? 'block' : 'none';
}

function setupRegisterForm() {
  var form = document.getElementById('register-form');
  if (!form) return;

  var password = document.getElementById('password');
  var confirmPassword = document.getElementById('confirm_password');
  var hint = document.getElementById('password-match-hint');

  function checkMatch() {
    if (!hint) return;
    if (confirmPassword.value === '') {
      hint.textContent = '';
      return;
    }
    if (password.value === confirmPassword.value) {
      hint.textContent = 'Passwords match';
      hint.style.color = '#1DD1A1';
    } else {
      hint.textContent = 'Passwords do not match';
      hint.style.color = '#FF6B6B';
    }
  }

  if (password && confirmPassword) {
    password.addEventListener('input', checkMatch);
    confirmPassword.addEventListener('input', checkMatch);
  }

  form.addEventListener('submit', function (e) {
    var errors = [];

    if (password.value.length < 8) {
      errors.push('Password must be at least 8 characters.');
    }
    if (password.value !== confirmPassword.value) {
      errors.push('Passwords do not match.');
    }

    if (errors.length > 0) {
      e.preventDefault();
      showClientErrors(errors);
      return;
    }

    showClientErrors([]);
    lockSubmitButton(form);
  });
}

function setupLoginForm() {
  var form = document.getElementById('login-form');
  if (!form) return;

  form.addEventListener('submit', function () {
    lockSubmitButton(form);
  });
}