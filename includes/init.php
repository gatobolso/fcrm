<?php
// init.php - El corazón de tu aplicación
require_once __DIR__ . '/../config/app.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/auth.php';

// 1. Verificar que esté logueado
if (!isset($_SESSION['userId'])) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

// 2. Verificar que no esté bloqueado en tiempo real
verifyUserStatus($pdo); 
