<?php
    declare(strict_types=1);

    $title = 'Clientes';

    require_once __DIR__ . '/../../../config/app.php';
    require_once BASE_PATH . '/includes/auth.php';
    // requirePermission('CLIENT_VIEW');

    require_once BASE_PATH . '/config/database.php';
    require_once BASE_PATH . '/config/entity.php';

    /*
    |--------------------------------------------------------------------------
    | Funciones auxiliares de presentación
    |--------------------------------------------------------------------------
    */

    function aplicarFormatoTelefono(?string $valor, ?string $formato, ?string $prefijo, int $digitos = 0): string {
        if ($valor === null || $valor === '') {
            return '';
        }

        $valor = preg_replace('/\D/', '', $valor) ?? '';
        $valor = substr($valor, max(0, $digitos));

        if ($formato === null || $formato === '') {
            return trim(($prefijo ?? '') . ' ' . $valor);
        }

        $resultado = trim((string) $prefijo);

        if ($resultado !== '') {
            $resultado .= ' ';
        }

        $indice = 0;

        foreach (str_split($formato) as $caracter) {
            if ($caracter === '#') {
                if (!isset($valor[$indice])) {
                    break;
                }

                $resultado .= $valor[$indice];
                $indice++;
            } else {
                $resultado .= $caracter;
            }
        }

        return trim($resultado);
    }

    function aplicarFormatoDocumento(?string $valor, ?string $formato): string {
        if ($valor === null || $valor === '') {
            return '';
        }

        $valor = preg_replace('/\D/', '', $valor) ?? '';

        if ($formato === null || $formato === '') {
            return $valor;
        }

        $resultado = '';
        $indice = 0;

        foreach (str_split($formato) as $caracter) {
            if ($caracter === '#') {
                if (!isset($valor[$indice])) {
                    break;
                }

                $resultado .= $valor[$indice];
                $indice++;
            } else {
                $resultado .= $caracter;
            }
        }

        return $resultado;
    }

    /*
    |--------------------------------------------------------------------------
    | Procesar acciones POST antes de cargar el grid
    |--------------------------------------------------------------------------
    */

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $message = '';

        if (isset($_POST['bloquear'])) {
            setClientBlocked($pdo, (int) $_POST['bloquear'], true);
            $message = 'El cliente fue bloqueado correctamente.';
        } elseif (isset($_POST['desbloquear'])) {
            setClientBlocked($pdo, (int) $_POST['desbloquear'], false);
            $message = 'El cliente fue desbloqueado correctamente.';
        } elseif (isset($_POST['borrar'])) {
            deleteClient($pdo, (int) $_POST['borrar']);
            $message = 'El cliente fue eliminado correctamente.';
        } elseif (isset($_POST['blockUser'])) {
            setUserBlocked($pdo, (int) $_POST['blockUser'], true);
            $message = 'El usuario fue bloqueado correctamente.';
        } elseif (isset($_POST['unblockUser'])) {
            setUserBlocked($pdo, (int) $_POST['unblockUser'], false);
            $message = 'El usuario fue desbloqueado correctamente.';
        } elseif (isset($_POST['activateUser'])) {
            setUserActivated($pdo, (int) $_POST['activateUser'], true);
            $message = 'El usuario fue activado correctamente.';
        } elseif (isset($_POST['deactivateUser'])) {
            setUserActivated($pdo, (int) $_POST['deactivateUser'], false);
            $message = 'El usuario fue desactivado correctamente.';
        } elseif (isset($_POST['deleteUser'])) {
            deleteUser($pdo, (int) $_POST['deleteUser']);
            $message = 'El usuario fue eliminado correctamente.';
        }

        if ($message !== '') {
            $_SESSION['entityUpdateOK'] = 1;
            $_SESSION['entityUpdateMessage'] = $message;
        }

        header(
            'Location: '
            . BASE_URL
            . '/private/entidades/clientes/index.php'
        );
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Filtros
    |--------------------------------------------------------------------------
    */

    $filters = [
        'name' => trim($_GET['name'] ?? ''),
        'country' => trim($_GET['country'] ?? ''),
        'documento' => trim($_GET['documento'] ?? ''),
        'email' => trim($_GET['email'] ?? ''),
        'phone' => trim($_GET['phone'] ?? ''),
        'address' => trim($_GET['address'] ?? ''),
        'comments' => trim($_GET['comments'] ?? '')
    ];

    /*
    |--------------------------------------------------------------------------
    | Cargar datos después de procesar acciones
    |--------------------------------------------------------------------------
    */

    $clientes = getEntitiesByTypeId($pdo, 2);
    $usersByEntity = getUsersByEntities($pdo);

    $clientes = array_values(
        array_filter(
            $clientes,
            static function (array $cliente) use ($filters): bool {
                $matches = static function (?string $value, string $filter): bool {
                    if ($filter === '') {
                        return true;
                    }

                    return mb_stripos((string) $value, $filter) !== false;
                };

                return $matches($cliente['name'] ?? '', $filters['name'])
                    && $matches($cliente['countryName'] ?? '', $filters['country'])
                    && $matches($cliente['documentNumber'] ?? '', $filters['documento'])
                    && $matches($cliente['email'] ?? '', $filters['email'])
                    && $matches($cliente['phone'] ?? '', $filters['phone'])
                    && $matches($cliente['address'] ?? '', $filters['address'])
                    && $matches($cliente['comments'] ?? '', $filters['comments']);
            }
        )
    );

    $updateOK = (int) ($_SESSION['entityUpdateOK'] ?? 0);
    $updateMessage = (string) (
        $_SESSION['entityUpdateMessage']
        ?? 'Los datos se actualizaron correctamente.'
    );

    unset(
        $_SESSION['entityUpdateOK'],
        $_SESSION['entityUpdateMessage']
    );

    include BASE_PATH . '/layouts/header.php';
    include BASE_PATH . '/layouts/sidebar.php';
?>

<?php if ($updateOK === 1): ?>
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1090; margin-top: 65px;">
        <div id="successToast" class="toast text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?= htmlspecialchars($updateMessage, ENT_QUOTES, 'UTF-8') ?>
                </div>

                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Cerrar"></button>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="page-title mb-0">Clientes</h1>

    <a
        href="<?= BASE_URL ?>/private/entidades/clientes/new.php"
        class="btn btn-primary"
    >
        <i class="bi bi-plus-circle me-1"></i>
        Nuevo cliente
    </a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="GET" action="">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>País</th>
                            <th>Documento</th>
                            <th>Email</th>
                            <th>Teléfono</th>
                            <th>Dirección</th>
                            <th>Comentarios</th>
                            <th>Info</th>
                            <th class="text-center"> Acciones</th>
                        </tr>

                        <tr>
                            <th>
                                <input type="text" name="name" class="form-control form-control-sm" placeholder="Filtrar..." value="<?= htmlspecialchars($filters['name'], ENT_QUOTES, 'UTF-8') ?>">
                            </th>
                            <th>
                                <input type="text" name="country" class="form-control form-control-sm" placeholder="Filtrar..." value="<?= htmlspecialchars($filters['country'], ENT_QUOTES, 'UTF-8') ?>">
                            </th>
                            <th>
                                <input type="text" name="documento" class="form-control form-control-sm" placeholder="Filtrar..." value="<?= htmlspecialchars($filters['documento'], ENT_QUOTES, 'UTF-8') ?>">
                            </th>
                            <th>
                                <input type="text" name="email" class="form-control form-control-sm" placeholder="Filtrar..." value="<?= htmlspecialchars($filters['email'], ENT_QUOTES, 'UTF-8') ?>">
                            </th>
                            <th>
                                <input type="text" name="phone" class="form-control form-control-sm" placeholder="Filtrar..." value="<?= htmlspecialchars($filters['phone'], ENT_QUOTES, 'UTF-8') ?>">
                            </th>
                            <th>
                                <input type="text" name="address" class="form-control form-control-sm" placeholder="Filtrar..." value="<?= htmlspecialchars($filters['address'], ENT_QUOTES, 'UTF-8') ?>">
                            </th>
                            <th>
                                <input type="text" name="comments" class="form-control form-control-sm" placeholder="Filtrar..." value="<?= htmlspecialchars($filters['comments'], ENT_QUOTES, 'UTF-8') ?>">
                            </th>
                            <th>
                            </th>

                            <th class="text-end text-nowrap">
                                <button type="submit" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="Aplicar filtros">
                                    <i class="bi bi-search"></i>
                                </button>

                                <a href="index.php" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="Limpiar filtros">
                                    <i class="bi bi-x-lg"></i>
                                </a>
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if ($clientes === []): ?>
                            <tr>
                                <td colspan="9" class="text-center text-body-secondary py-4">
                                    No se encontraron clientes.
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($clientes as $cliente): ?>
                            <?php $entityId = (int) $cliente['id']; $relatedUsers = $usersByEntity[$entityId] ?? []; $collapseId = 'clientUsers' . $entityId;?>

                            <tr>
                                <td>
                                    <?= htmlspecialchars((string) ($cliente['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars((string) ($cliente['countryName'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(aplicarFormatoDocumento($cliente['documentNumber'] ?? null,
                                     $cliente['documentFormat'] ?? null), ENT_QUOTES, 'UTF-8') ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars((string) ($cliente['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars((string) ($cliente['phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars((string) ($cliente['address'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars((string) ($cliente['comments'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                </td>

                                <td class="text-nowrap">
                                    <span class="badge <?= !empty($cliente['hasImported']) ? 'bg-success' : 'bg-danger' ?>" data-bs-toggle="tooltip" title="<?= !empty($cliente['hasImported']) ? 'Importó con Chinalat' : 'No importó con Chinalat' ?>">
                                        <i class="bi bi-box-seam"></i>
                                    </span>

                                    <span class="badge <?= !empty($cliente['isContractSigned']) ? 'bg-success' : 'bg-danger' ?>" data-bs-toggle="tooltip" title="<?= !empty($cliente['isContractSigned']) ? 'Contrato firmado' : 'Contrato no firmado' ?>">
                                        <i class="bi bi-file-earmark-check"></i>
                                    </span>
                                </td>

                                <td class="text-end text-nowrap">
                                    <?php #if (hasPermission('CONTACT_EDIT')): ?>
                                    <a class="btn btn-sm btn-outline-warning" href="edit.php?id=<?= $entityId ?>" data-bs-toggle="tooltip" title="Editar cliente">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    <?php #endif; ?>

                                    <?php #if (hasPermission('IMPORT_VIEW')): ?>
                                    <a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>/private/importaciones/index.php?entityId=<?= $entityId ?>" data-bs-toggle="tooltip" title="Ver importaciones">
                                        <i class="bi bi-box-seam"></i>
                                    </a>
                                    <?php #endif; ?>

                                    <?php #if (hasPermission('USER_VIEW')): ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>" aria-expanded="false" aria-controls="<?= $collapseId ?>" title="Ver usuarios relacionados">
                                        <i class="bi bi-person-gear"></i>
                                        <!--<span class="user-count"><?= count($relatedUsers) ?></span>-->
                                        <i class="bi bi-chevron-down toggle-arrow"></i>
                                    </button>
                                    <?php #endif; ?>

                                    <?php #if (hasPermission('ENTITY_DELETE')): ?>
                                    <form method="POST" class="d-inline">
                                        <button type="submit" name="borrar" value="<?= $entityId ?>" class="btn btn-sm btn-outline-danger" data-bs-toggle="tooltip" title="Borrar cliente" onclick="return confirm('¿Borrar este cliente?');">
                                            <i class="bi bi-person-dash"></i>
                                        </button>
                                    </form>
                                    <?php #endif; ?>
                                    
                                </td>
                            </tr>

                            <?php #if (hasPermission('USER_VIEW')): ?>
                            <tr class="users-detail-row">
                                <td colspan="9" class="p-0 border-0">
                                    <div id="<?= $collapseId ?>" class="collapse">
                                        <div class="p-3 border-bottom bg-body-tertiary">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <div>
                                                    <strong>Usuarios relacionados</strong>
                                                    <!--<div class="small text-body-secondary">
                                                        <?= htmlspecialchars((string) ($cliente['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                                    </div>-->
                                                </div>

                                                <a href="<?= BASE_URL ?>/private/usuarios/new.php?entityId=<?= $entityId ?>" class="btn btn-sm btn-primary">
                                                    <i class="bi bi-person-plus me-1"></i>
                                                    Nuevo usuario
                                                </a>
                                            </div>

                                            <?php if ($relatedUsers === []): ?>
                                                <div class="alert alert-secondary mb-0">
                                                    <i class="bi bi-info-circle me-1"></i>
                                                    Este cliente no tiene usuarios relacionados.
                                                </div>
                                            <?php else: ?>
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-hover align-middle mb-0">
                                                        <thead>
                                                            <tr>
                                                                <th>Usuario</th>
                                                                <th>Fecha de creación</th>
                                                                <th>Activado</th>
                                                                <th>Bloqueado</th>
                                                                <th class="text-end">Acciones</th>
                                                            </tr>
                                                        </thead>

                                                        <tbody>
                                                            <?php foreach ($relatedUsers as $user): ?>
                                                                <?php $userId = (int) $user['id']; ?>

                                                                <tr>
                                                                    <td>
                                                                        <i class="bi bi-person-circle me-1"></i>
                                                                        <?= htmlspecialchars((string) ($user['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                                                    </td>

                                                                    <td>
                                                                        <?= htmlspecialchars((string) ($user['createDate'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                                                    </td>

                                                                    <td>
                                                                        <span class="badge <?= !empty($user['isActivated']) ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                                                            <?= !empty($user['isActivated']) ? 'Sí' : 'No' ?>
                                                                        </span>
                                                                    </td>

                                                                    <td>
                                                                        <span class="badge <?= !empty($user['isBlocked']) ? 'text-bg-danger' : 'text-bg-success' ?>">
                                                                            <?= !empty($user['isBlocked']) ? 'Sí' : 'No' ?>
                                                                        </span>
                                                                    </td>

                                                                    <td class="text-end text-nowrap">
                                                                        <?php #if (hasPermission('USER_EDIT')): ?>
                                                                        <a href="<?= BASE_URL ?>/private/usuarios/edit.php?id=<?= $userId ?>" class="btn btn-sm btn-outline-warning" data-bs-toggle="tooltip" title="Editar usuario">
                                                                            <i class="bi bi-pencil-square"></i>
                                                                        </a>
                                                                        <?php #endif; ?>

                                                                        <?php #if (hasPermission('USER_UNLOCK')): ?>
                                                                        <?php if (!empty($user['isBlocked'])): ?>
                                                                            <form method="POST" class="d-inline">
                                                                                <button type="submit" name="unblockUser" value="<?= $userId ?>" class="btn btn-sm btn-outline-success" data-bs-toggle="tooltip" title="Desbloquear usuario">
                                                                                    <i class="bi bi-unlock"></i>
                                                                                </button>
                                                                            </form>
                                                                        <?php else: ?>
                                                                            <form method="POST" class="d-inline">
                                                                                <button type="submit" name="blockUser" value="<?= $userId ?>" class="btn btn-sm btn-outline-danger" data-bs-toggle="tooltip" title="Bloquear usuario">
                                                                                    <i class="bi bi-lock"></i>
                                                                                </button>
                                                                            </form>
                                                                        <?php endif; ?>
                                                                        <?php #endif; ?>

                                                                        <?php #if (hasPermission('USER_ACTIVATE')): ?>
                                                                        <?php if (!empty($user['isActivated'])): ?>
                                                                            <form method="POST" class="d-inline">
                                                                                <button type="submit" name="deactivateUser" value="<?= $userId ?>" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="Desactivar usuario" >
                                                                                    <i class="bi bi-person-dash"></i>
                                                                                </button>
                                                                            </form>
                                                                        <?php else: ?>
                                                                            <form method="POST" class="d-inline">
                                                                                <button type="submit" name="activateUser" value="<?= $userId ?>" class="btn btn-sm btn-outline-success" data-bs-toggle="tooltip" title="Activar usuario">
                                                                                    <i class="bi bi-person-check"></i>
                                                                                </button>
                                                                            </form>
                                                                        <?php endif; ?>
                                                                        <?php #endif; ?>

                                                                        <?php #if (hasPermission('USER_DELETE')): ?>
                                                                        <form method="POST" class="d-inline">
                                                                            <button type="submit" name="deleteUser" value="<?= $userId ?>" class="btn btn-sm btn-outline-danger" data-bs-toggle="tooltip" title="Eliminar usuario" onclick="return confirm('¿Eliminar este usuario?');">
                                                                                <i class="bi bi-trash"></i>
                                                                            </button>
                                                                        </form>
                                                                        <?php #endif; ?>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php #endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</div>

<style>
.users-detail-row > td {
    background-color: var(--bs-tertiary-bg);
}

.users-toggle {
    display: inline-flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.35rem;
    width: 82px;
}

.users-toggle .user-count {
    min-width: 18px;
    text-align: center;
    font-size: 0.75rem;
    font-weight: 600;
}

.users-toggle .toggle-arrow {
    display: inline-block;
    font-size: 0.7rem;
    transition: transform 0.2s ease;
}

.users-toggle[aria-expanded="true"] .toggle-arrow {
    transform: rotate(180deg);
}
</style>

<?php if ($updateOK === 1): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const toastElement = document.getElementById('successToast');

        if (!toastElement) {
            return;
        }

        const successToast = new bootstrap.Toast(toastElement, {
            autohide: true,
            delay: 4000
        });

        successToast.show();
    });
    </script>
<?php endif; ?>

<?php include BASE_PATH . '/layouts/footer.php'; ?>
