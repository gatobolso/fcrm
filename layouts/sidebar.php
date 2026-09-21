<?php
    declare(strict_types=1);

    require_once __DIR__ . '/../config/app.php';

    $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $currentPath = $currentPath ?? '';

    function isActiveMenu(string $path, string $currentPath): string {
        return str_contains($currentPath, $path) ? 'active' : '';
    }

    $canViewMasters = 1;
        #hasPermission('COUNTRY_VIEW')
        #|| hasPermission('STATE_VIEW')
        #|| hasPermission('CITY_VIEW')
        #|| hasPermission('CURRENCY_VIEW')
        #|| hasPermission('DOCUMENT_TYPE_VIEW')
        #|| hasPermission('CONTACT_TYPE_VIEW');

    $canViewSecurity = 1;
        #hasPermission('ROLE_VIEW') ||
        #hasPermission('PERMISSION_VIEW');


?>

<aside class="sidebar p-2">
    <nav aria-label="Navegación principal">
        <ul class="nav nav-pills flex-column gap-1">
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/private/dashboard.php" class="nav-link">
                    <i class="bi bi-speedometer2"></i>
                    <span>Inicio</span>
                </a>
            </li>

            <?php #if (hasPermission('CLIENT_VIEW')): ?>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>/private/entidades/clientes/index.php" class="nav-link">
                        <i class="bi bi-people"></i>
                        <span>Clientes</span>
                    </a>
                </li>
            <?php #endif; ?>

            <?php #if (hasPermission('EVENT_VIEW')): ?>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>/private/entidades/usuarios/index.php" class="nav-link">
                        <i class="bi bi-calendar-event"></i>
                        <span>Eventos</span>
                    </a>
                </li>
            <?php #endif; ?>

            <?php #if (hasPermission('EXPENSE_VIEW')): ?>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>/private/entidades/usuarios/index.php" class="nav-link">
                        <i class="bi bi-cash-coin"></i>
                        <span>Gastos</span>
                    </a>
                </li>
            <?php #endif; ?>

            <?php #if (hasPermission('IMPORT_VIEW')): ?>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>/private/entidades/usuarios/index.php" class="nav-link">
                        <i class="bi bi-box-seam"></i>
                        <span>Importaciones</span>
                    </a>
                </li>
            <?php #endif; ?>

            <?php if ($canViewMasters): ?>
                <li class="nav-item mt-2">
                    <span class="nav-link disabled text-uppercase small fw-semibold">
                        Maestros
                    </span>
                </li>

                <?php #if (hasPermission('STATE_VIEW')): ?>
                    <li class="nav-item">
                        <a href="<?= BASE_URL ?>/private/admin/state/index.php" class="nav-link nav-link-submenu">
                            <i class="bi bi-building"></i>
                            <span>Proveedores</span>
                        </a>
                    </li>
                <?php #endif; ?>

                <?php #if (hasPermission('COUNTRY_VIEW')): ?>
                    <li class="nav-item">
                        <a href="<?= BASE_URL ?>/private/admin/country/index.php" class="nav-link nav-link-submenu">
                            <i class="bi bi-globe-americas"></i>
                            <span>Países</span>
                        </a>
                    </li>
                <?php #endif; ?>

                <?php #if (hasPermission('STATE_VIEW')): ?>
                    <li class="nav-item">
                        <a href="<?= BASE_URL ?>/private/admin/state/index.php" class="nav-link nav-link-submenu">
                            <i class="bi bi-map"></i>
                            <span>Estados</span>
                        </a>
                    </li>
                <?php #endif; ?>

                <?php #if (hasPermission('CITY_VIEW')): ?>
                    <li class="nav-item">
                        <a href="<?= BASE_URL ?>/private/admin/city/index.php" class="nav-link nav-link-submenu">
                            <i class="bi bi-buildings"></i>
                            <span>Ciudades</span>
                        </a>
                    </li>
                <?php #endif; ?>

                <?php #if (hasPermission('CURRENCY_VIEW')): ?>
                    <li class="nav-item">
                        <a href="<?= BASE_URL ?>/private/admin/currency/index.php" class="nav-link nav-link-submenu">
                            <i class="bi bi-currency-exchange"></i>
                            <span>Monedas</span>
                        </a>
                    </li>
                <?php #endif; ?>

                <?php #if (hasPermission('DOCUMENT_TYPE_VIEW')): ?>
                    <li class="nav-item">
                        <a href="<?= BASE_URL ?>/private/admin/documentType/index.php" class="nav-link nav-link-submenu">
                            <i class="bi bi-card-text"></i>
                            <span>Tipos de documento</span>
                        </a>
                    </li>
                <?php #endif; ?>

                <?php #if (hasPermission('CONTACT_TYPE_VIEW')): ?>
                    <li class="nav-item">
                        <a href="<?= BASE_URL ?>/private/admin/contactType/index.php" class="nav-link nav-link-submenu">
                            <i class="bi bi-person-lines-fill"></i>
                            <span>Tipos de contacto</span>
                        </a>
                    </li>
                <?php #endif; ?>
            <?php endif; ?>

            <?php if ($canViewSecurity): ?>
                <li class="nav-item mt-2">
                    <span class="nav-link disabled text-uppercase small fw-semibold">
                        Seguridad
                    </span>
                </li>

                <?php #if (hasPermission('USER_VIEW')): ?>
                    <li class="nav-item">
                        <a href="<?= BASE_URL ?>/private/entidades/usuarios/index.php" class="nav-link">
                            <i class="bi bi-person-gear"></i>
                            <span>Usuarios</span>
                        </a>
                    </li>
                <?php #endif; ?>

                <?php #if (hasPermission('ROLE_VIEW')): ?>
                    <li class="nav-item">
                        <a href="<?= BASE_URL ?>/private/admin/roles/index.php" class="nav-link nav-link-submenu">
                            <i class="bi bi-person-badge"></i>
                            <span>Roles</span>
                        </a>
                    </li>
                <?php #endif; ?>

                <?php #if (hasPermission('PERMISSION_VIEW')): ?>
                    <li class="nav-item">
                        <a href="<?= BASE_URL ?>/private/admin/permisos/index.php" class="nav-link nav-link-submenu">
                            <i class="bi bi-shield-lock"></i>
                            <span>Permisos</span>
                        </a>
                    </li>
                <?php #endif; ?>

            <?php endif; ?>
        </ul>
    </nav>
</aside>

<main class="content">