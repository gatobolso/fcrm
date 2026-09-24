<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$theme = $_SESSION['theme'] ?? 'light';
$username = $_SESSION['userName'] ?? 'Usuario';
$allowedThemes = ['light', 'dark'];
if (!in_array($theme, $allowedThemes, true)) {
    $theme = 'light';
}
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="<?= htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <script>
        const initialTheme = localStorage.getItem('theme');
        if (initialTheme === 'light' || initialTheme === 'dark') {
            document.documentElement.setAttribute('data-bs-theme', initialTheme);
        }
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/global.css">
    <link rel="icon" type="image/ico" href="<?= BASE_URL ?>/assets/favicon.ico">
</head>

<body class="d-flex">
    <script>
        if (localStorage.getItem('sidebarCollapsed') === '1') {
            document.body.classList.add('sidebar-collapsed');
        }
    </script>

    <header class="top-header px-3">
        <div class="container-fluid px-0 d-flex align-items-center justify-content-between">
            <a class="navbar-brand" href="<?= BASE_URL ?>/private/dashboard.php">
                <img src="<?= BASE_URL ?>/assets/logo1.webp" class="header-logo" alt="Logo">
            </a>

            <div class="dropdown ms-auto">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-person-circle me-1"></i>
                    <?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>
                </button>

                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>/private/profile.php"><i class="bi bi-person me-2"></i> Mi perfil</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><button type="button" class="dropdown-item" onclick="changeTheme('light')"><i class="bi bi-sun me-2"></i> Tema claro</button></li>
                    <li><button type="button" class="dropdown-item" onclick="changeTheme('dark')"><i class="bi bi-moon me-2"></i> Tema oscuro</button></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Cerrar sesión</a></li>
                </ul>
            </div>
        </div>
    </header>
