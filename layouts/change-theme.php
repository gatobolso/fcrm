<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /fcrm/private/dashboard.php');
    exit;
}

$theme = $_POST['theme'] ?? 'light';
$allowedThemes = ['light', 'dark'];

if (!in_array($theme, $allowedThemes, true)) {
    $theme = 'light';
}

$_SESSION['theme'] = $theme;

$redirect = $_SERVER['HTTP_REFERER'] ?? '/fcrm/private/dashboard.php';
header('Location: ' . $redirect);
exit;
