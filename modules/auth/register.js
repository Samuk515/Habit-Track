$(document).ready(function () {
    $("form").validate({
        rules: {
            name: {
                required: true
            },
            email: {
                required: true,
                email: true
            },
            password: {
                required: true,
                minlength: 8
            },
            confirm_password: {
                required: true,
                minlength: 8,
                // References the password field by its existing id="password"
                // — jQuery Validate's built-in equalTo rule for confirm-password checks.
                equalTo: "#password"
            }
        },
        messages: {
            name: "Please enter your full name.",
            email: {
                required: "Please enter your email address.",
                email: "Please enter a valid email address."
            },
            password: {
                required: "Please enter a password.",
                minlength: "Password must be at least 8 characters."
            },
            confirm_password: {
                required: "Please confirm your password.",
                minlength: "Password must be at least 8 characters.",
                equalTo: "Passwords do not match."
            }
        },
        errorElement: "span",
        errorClass: "field-error"
    });
});