$(document).ready(function () {
    $("form").validate({
        rules: {
            email: {
                required: true,
                email: true
            },
            password: {
                required: true
            }
        },
        messages: {
            email: {
                required: "Please enter your email address.",
                email: "Please enter a valid email address."
            },
            password: "Please enter your password."
        },
        errorElement: "span",
        errorClass: "field-error"
    });
});