// it wait for html to load and then it will call the funtions of setupPasswordToggles, setupRegisterForm and setupLoginForm
document.addEventListener('DOMContentLoaded', function () {
  setupPasswordToggles(); // show or hide password
  setupRegisterForm(); // sets up validation for the registration
  setupLoginForm(); // sets up validation for the login
});
// handles the password visibility toggle for password fields
function setupPasswordToggles() {
  var toggles = document.querySelectorAll('.toggle-password');
  toggles.forEach(function (btn) { // loops through each show/hide buttons
    btn.addEventListener('click', function () { // Runs code when the button is clicked
      var input = document.getElementById(btn.dataset.target); // Gets the input field connected to that button.
      if (!input) return;
      var showing = input.type === 'text';
      input.type = showing ? 'password' : 'text'; //Checks whether the password is currently visible.
      btn.textContent = showing ? 'Show' : 'Hide';
    });
  });
}
// disables the submit button to prevent multiple submissions
function lockSubmitButton(form) {
  var btn = form.querySelector('button[type="submit"]');
  if (btn) {
    btn.disabled = true; // Disables the submit button to prevent multiple submissions
    btn.textContent = 'Please wait...';
  }
}
// displays client-side validation errors in the error box
function showClientErrors(errors) {
  var box = document.getElementById('client-error-box');
  if (!box) return;
  box.innerHTML = ''; //clear the old errors messages
  errors.forEach(function (msg) {
    var line = document.createElement('div');
    line.textContent = msg;
    box.appendChild(line);
  });
  box.style.display = errors.length ? 'block' : 'none'; // Show or hide the error box based on whether there are errors
}
// sets up the registration form validation and password match check
function setupRegisterForm() { // Sets up the registration form validation and password match check
  var form = document.getElementById('register-form'); // Get the registration form element
  if (!form) return; // If the form doesn't exist, exit the function

  var password = document.getElementById('password'); // Get the password input field
  var confirmPassword = document.getElementById('confirm_password'); // Get the confirm password input field
  var hint = document.getElementById('password-match-hint'); // Get the element to display password match hints

  function checkMatch() {
    if (!hint) return; // If the hint element doesn't exist, exit the function
    if (confirmPassword.value === '') {
      hint.textContent = ''; // Clear the hint if the confirm password field is empty
      return;
    }
    if (password.value === confirmPassword.value) {
      hint.textContent = 'Passwords match';
      hint.style.color = '#1DD1A1'; // Set the hint color to green if passwords match
    } else {
      hint.textContent = 'Passwords do not match';
      hint.style.color = '#FF6B6B'; // Set the hint color to red if passwords do not match
    }
  }

  if (password && confirmPassword) {
    password.addEventListener('input', checkMatch); // Check for password match on input in the password field
    confirmPassword.addEventListener('input', checkMatch);
  }

  form.addEventListener('submit', function (e) {
    var errors = [];

    // Check if the password is at least 8 characters long
    if (password.value.length < 8) {
      errors.push('Password must be at least 8 characters.');
    }
    if (password.value !== confirmPassword.value) { // Check if the password and confirm password fields match
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
}// Git commit forced by user - Sat Jul 18 21:21:23 +0545 2026
