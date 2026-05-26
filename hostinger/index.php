<?php
require_once __DIR__ . '/config/bootstrap.php';
Auth::startSession();
if (Auth::check()) {
    header('Location: dashboard.php');
    exit;
}
header('Location: login.php');
exit;
