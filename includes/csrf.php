<?php
function generateCsrfToken() { //checks whether a Php session is active or not if not, is starts one 
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    } // cross site request forgery. it protect forms from being submitted by a third-party site

    if (empty($_SESSION['csrf_token'])) { // if there is no csrf token in the session, it generates random bytes and store in crsf token
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($submittedToken) { //checks php session is active or  then if token is missing  then it fails and compares wiht hash_equals
    if (session_status() !== PHP_SESSION_ACTIVE) { // it avoid the timing attacks
        session_start();
    }

    if (empty($_SESSION['csrf_token']) || empty($submittedToken)) {
        return false; // show the error message if the token is missing or invalid
    }

    return hash_equals($_SESSION['csrf_token'], $submittedToken);
}
