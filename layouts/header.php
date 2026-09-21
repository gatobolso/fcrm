<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$theme = $_SESSION['theme'] ?? 'light';
$username = $_SESSION['username'] ?? $_SESSION['usuario'] ?? 'Usuario';

$allowedThemes = ['light', 'dark'];

if (!in_array($theme, $allowedThemes, true)) {
    $theme = 'light';
}
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="<?= htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <script>
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-bs-theme', savedTheme);
    </script>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="icon" type="image/ico" href="<?= BASE_URL ?>/assets/favicon.ico">

    <style>
        html,
        body {
            min-height: 100%;
        }

        body {
            margin: 0;
            background-color: var(--bs-body-bg);
            color: var(--bs-body-color);
        }

        .header-logo {
            display: block;
            width: auto;
            height: 42px;
            object-fit: contain;
        }
        .topbar {
            min-height: 56px;
            border-bottom: 1px solid var(--bs-border-color);
            background-color: var(--bs-body-bg);
        }

        .main-container {
            display: flex;
            min-height: calc(100vh - 57px);
        }

        .sidebar {
            width: 190px;
            flex: 0 0 190px;
            border-right: 1px solid var(--bs-border-color);
            background-color: var(--bs-tertiary-bg);
        }

        .sidebar .nav-link {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            padding: 0.55rem 0.75rem;
            color: var(--bs-body-color);
            border-radius: 0.375rem;
            font-size: 0.9rem;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: var(--bs-primary);
            background-color: var(--bs-secondary-bg);
        }

        .content {
            min-width: 0;
            flex: 1 1 auto;
            padding: 1.25rem;
            background-color: var(--bs-body-bg);
        }

        .page-title {
            margin: 0;
            font-size: 1.65rem;
            font-weight: 600;
        }

        @media (max-width: 767.98px) {
            .main-container {
                display: block;
            }

            .sidebar {
                width: 100%;
                min-height: auto;
                border-right: 0;
                border-bottom: 1px solid var(--bs-border-color);
            }

            .content {
                padding: 1rem;
            }
        }

        .sidebar .nav-link {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            width: 100%;
            padding: 0.55rem 0.75rem;
            color: var(--bs-body-color);
            border-radius: 0.375rem;
            font-size: 0.9rem;
            text-decoration: none;
        }

        .sidebar .nav-link:hover {
            color: var(--bs-primary);
            background-color: var(--bs-secondary-bg);
        }

        .sidebar .nav-link.active {
            color: var(--bs-primary);
            background-color: var(--bs-secondary-bg);
            font-weight: 600;
        }

        .sidebar .nav-link-submenu {
            padding-top: 0.45rem;
            padding-bottom: 0.45rem;
            padding-left: 0.75rem;
            font-size: 0.85rem;
        }

        .sidebar .submenu-arrow {
            margin-left: auto;
            font-size: 0.75rem;
            transition: transform 0.2s ease;
        }

        .sidebar button[aria-expanded="true"] .submenu-arrow {
            transform: rotate(180deg);
        }

        .sidebar .nav-link.disabled {
            opacity: 0.65;
            pointer-events: none;
        }

        .users-detail-row > td {
            background-color: var(--bs-tertiary-bg);
        }

        .users-toggle .toggle-arrow {
            display: inline-block;
            transition: transform 0.2s ease;
        }

        .users-toggle[aria-expanded="true"] .toggle-arrow {
            transform: rotate(180deg);
        }
    </style>
</head>

<body>
    <nav class="navbar topbar px-3">
        <div class="container-fluid px-0">
            <a class="navbar-brand">
                <img src="/fcrm/assets/logo1.webp" class="header-logo"></img>
            </a>

            <div class="dropdown ms-auto">
                <button
                    class="btn btn-outline-secondary dropdown-toggle"
                    type="button"
                    id="userDropdown"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                >
                    <i class="bi bi-person-circle me-1"></i>
                    <?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>
                </button>

                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                    <li>
                        <a class="dropdown-item" href="/fcrm/private/profile.php">
                            <i class="bi bi-person me-2"></i>
                            Mi perfil
                        </a>
                    </li>

                    <li><hr class="dropdown-divider"></li>

                    <li id="optionThemeLight">
                        <button type="button" class="dropdown-item" onclick="changeTheme('light')">
                            <i class="bi bi-sun me-2"></i>
                            Tema claro
                        </button>
                    </li>

                    <li id="optionThemeDark">
                        <button type="submit" class="dropdown-item" onclick="changeTheme('dark')">
                            <i class="bi bi-moon me-2"></i>
                            Tema oscuro
                        </button>
                    </li>

                    <li><hr class="dropdown-divider"></li>

                    <li>
                        <a class="dropdown-item text-danger" href="/fcrm/logout.php">
                            <i class="bi bi-box-arrow-right me-2"></i>
                            Cerrar sesión
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="main-container">
