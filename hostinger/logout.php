<?php
require_once __DIR__ . '/config/bootstrap.php';
Auth::logout();
header('Location: login.php');
exit;
