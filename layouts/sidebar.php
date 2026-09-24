<?php
declare(strict_types=1);

// Importamos la configuración básica
require_once __DIR__ . '/../config/app.php';

// Obtenemos la ruta actual para marcar el menú activo
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$currentPath = $currentPath ?? '';

// Función para determinar si un enlace está activo
if (!function_exists('isActiveMenu')) {
    function isActiveMenu(string $path, string $currentPath): string {
        return str_contains($currentPath, $path) ? 'active' : '';
    }
}

// Verificación de permisos para grupos de menú
$canViewMasters = hasPermission('PROVIDER_VIEW')
    || hasPermission('COUNTRY_VIEW')
    || hasPermission('STATE_VIEW')
    || hasPermission('CITY_VIEW')
    || hasPermission('CURRENCY_VIEW')
    || hasPermission('DOCUMENT_TYPE_VIEW')
    || hasPermission('CONTACT_TYPE_VIEW');

$canViewSecurity = hasPermission('USER_VIEW')
    || hasPermission('ROLE_VIEW')
    || hasPermission('PERMISSION_VIEW');
?>

<aside id="mainSidebar" class="sidebar p-2">
    <!-- Botón de Colapso: Pequeño y alineado a la derecha
    <div class="d-flex justify-content-end mb-2">
        <button 
            type="button" 
            id="sidebarToggle" 
            class="btn btn-xs btn-outline-secondary" 
            data-bs-toggle="tooltip" 
            title="Contraer/Expandir menú"
        >
            <span id="toggleArrow"></span>
        </button>
    </div>
 -->
    <nav aria-label="Navegación principal">
        <ul class="nav nav-pills flex-column gap-1">
            
            <!-- Inicio -->
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/private/dashboard.php" class="nav-link <?= isActiveMenu('dashboard.php', $currentPath) ?>">
                    <i class="bi bi-speedometer2"></i>
                    <span class="menu-text">Inicio</span>
                </a>
            </li>

            <!-- Clientes -->
            <?php if (hasPermission('CLIENT_VIEW')): ?>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>/private/entidades/clientes/index.php" class="nav-link <?= isActiveMenu('clientes/index.php', $currentPath) ?>">
                        <i class="bi bi-people"></i>
                        <span class="menu-text">Clientes</span>
                    </a>
                </li>
            <?php endif; ?>

            <!-- Eventos -->
            <?php if (hasPermission('EVENT_VIEW')): ?>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>/private/eventos/index.php" class="nav-link <?= isActiveMenu('eventos/index.php', $currentPath) ?>">
                        <i class="bi bi-calendar-event"></i>
                        <span class="menu-text">Eventos</span>
                    </a>
                </li>
            <?php endif; ?>

            <!-- Gastos -->
            <?php if (hasPermission('EXPENSE_VIEW')): ?>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>/private/gastos/index.php" class="nav-link <?= isActiveMenu('gastos/index.php', $currentPath) ?>">
                        <i class="bi bi-cash-coin"></i>
                        <span class="menu-text">Gastos</span>
                    </a>
                </li>
            <?php endif; ?>

            <!-- Importaciones -->
            <?php if (hasPermission('IMPORT_VIEW')): ?>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>/private/importaciones/index.php" class="nav-link <?= isActiveMenu('importaciones/index.php', $currentPath) ?>">
                        <i class="bi bi-box-seam"></i>
                        <span class="menu-text">Importaciones</span>
                    </a>
                </li>
            <?php endif; ?>

            <!-- Proveedores -->
            <?php if (hasPermission('PROVIDER_VIEW')): ?>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>/private/entidades/proveedores/index.php" class="nav-link <?= isActiveMenu('proveedores/index.php', $currentPath) ?>">
                        <i class="bi bi-building"></i>
                        <span class="menu-text">Proveedores</span>
                    </a>
                </li>
            <?php endif; ?>

            <!-- GRUPO: MAESTROS -->
            <?php if ($canViewMasters): ?>
                <li class="nav-item mt-3">
                    <span class="nav-link disabled text-uppercase small fw-semibold section-header">
                        Maestros
                    </span>
                </li>

                <?php if (hasPermission('COUNTRY_VIEW')): ?>
                    <li class="nav-item">
                        <a href="<?= BASE_URL ?>/private/admin/country/index.php" class="nav-link nav-link-submenu <?= isActiveMenu('country/index.php', $currentPath) ?>">
                            <i class="bi bi-globe-americas"></i>
                            <span class="menu-text">Países</span>
                        </a>
                    </li>
                <?php endif; ?>

                <?php if (hasPermission('STATE_VIEW')): ?>
                    <li class="nav-item">
                        <a href="<?= BASE_URL ?>/private/admin/state/index.php" class="nav-link nav-link-submenu <?= isActiveMenu('state/index.php', $currentPath) ?>">
                            <i class="bi bi-map"></i>
                            <span class="menu-text">Estados</span>
                        </a>
                    </li>
                <?php endif; ?>

                <?php if (hasPermission('CITY_VIEW')): ?>
                    <li class="nav-item">
                        <a href="<?= BASE_URL ?>/private/admin/city/index.php" class="nav-link nav-link-submenu <?= isActiveMenu('city/index.php', $currentPath) ?>">
                            <i class="bi bi-buildings"></i>
                            <span class="menu-text">Ciudades</span>
                        </a>
                    </li>
                <?php endif; ?>

                <?php if (hasPermission('CURRENCY_VIEW')): ?>
                    <li class="nav-item">
                        <a href="<?= BASE_URL ?>/private/admin/currency/index.php" class="nav-link nav-link-submenu <?= isActiveMenu('currency/index.php', $currentPath) ?>">
                            <i class="bi bi-currency-exchange"></i>
                            <span class="menu-text">Monedas</span>
                        </a>
                    </li>
                <?php endif; ?>

                <?php if (hasPermission('DOCUMENT_TYPE_VIEW')): ?>
                    <li class="nav-item">
                        <a href="<?= BASE_URL ?>/private/admin/documentType/index.php" class="nav-link nav-link-submenu <?= isActiveMenu('documentType/index.php', $currentPath) ?>">
                            <i class="bi bi-card-text"></i>
                            <span class="menu-//text">Tipos de documento</span>
                        </a>
                    </li>
                <?php endif; ?>

                <?php if (hasPermission('CONTACT_TYPE_VIEW')): ?>
                    <li class="nav-item">
                        <a href="<?= BASE_URL ?>/private/admin/contactType/index.php" class="nav-link nav-link-submenu <?= isActiveMenu('contactType/index.php', $currentPath) ?>">
                            <i class="bi bi-person-lines-fill"></i>
                            <span class="menu-text">Tipos de contacto</span>
                        </a>
                    </li>
                <?php endif; ?>
            <?php endif; ?>

            <!-- GRUPO: SEGURIDAD -->
            <?php if ($canViewSecurity): ?>
                <li class="nav-item mt-3">
                    <span class="nav-link disabled text-uppercase small fw-semibold section-header">
                        Seguridad
                    </span>
                </li>

                <?php if (hasPermission('USER_VIEW')): ?>
                    <li class="nav-item">
                        <a href="<?= BASE_URL ?>/private/entidades/usuarios/index.php" class="nav-link <?= isActiveMenu('usuarios/index.php', $currentPath) ?>">
                            <i class="bi bi-person-gear"></i>
                            <span class="menu-text">Usuarios</span>
                        </a>
                    </li>
                <?php endif; ?>

                <?php if (hasPermission('ROLE_VIEW')): ?>
                    <li class="nav-item">
                        <a href="<?= BASE_URL ?>/private/admin/roles/index.php" class="nav-link nav-link-submenu <?= isActiveMenu('roles/index.php', $currentPath) ?>">
                            <i class="bi bi-person-badge"></i>
                            <span class="menu-text">Roles</span>
                        </a>
                    </li>
                <?php endif; ?>

                <?php if (hasPermission('PERMISSION_VIEW')): ?>
                    <li class="nav-item">
                        <a href="<?= BASE_URL ?>/private/admin/permisos/index.php" class="nav-link nav-link-submenu <?= isActiveMenu('permisos/index.php', $currentPath) ?>">
                            <i class="bi bi-shield-lock"></i>
                            <span class="menu-text">Permisos</span>
                        </a>
                    </li>
                <?php endif; ?>
            <?php endif; ?>
        </ul>
    </nav>
</aside>

<main class="content">
