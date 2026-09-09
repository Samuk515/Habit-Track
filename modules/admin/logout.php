<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

unset($_SESSION['admin_authenticated'], $_SESSION['admin_email']);
header('Location: login.php');
exit;