<?php
    declare(strict_types=1);

    $title = 'Clientes';

    require_once '../../../includes/init.php'; // Conecta DB, checa sesión y bloqueo
    requirePermission('CLIENT_VIEW');

    require_once BASE_PATH . '/config/entity.php';

    $isAdmin = isAdministrator($pdo);

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
        requireValidCsrfToken();
        $message = '';

        if (isset($_POST['bloquear'])) {
            requirePermission('CLIENT_BLOCK');
            setClientBlocked($pdo, (int) $_POST['bloquear'], true);
            $message = 'El cliente fue bloqueado correctamente.';
        } elseif (isset($_POST['desbloquear'])) {
            requirePermission('CLIENT_BLOCK');
            setClientBlocked($pdo, (int) $_POST['desbloquear'], false);
            $message = 'El cliente fue desbloqueado correctamente.';
        } elseif (isset($_POST['borrar'])) {
            requirePermission('ENTITY_DELETE');
            deleteClient($pdo, (int) $_POST['borrar']);
            $message = 'El cliente fue eliminado correctamente.';
        } elseif (isset($_POST['blockUser'])) {
            requirePermission('USER_UNLOCK');
            setUserBlocked($pdo, (int) $_POST['blockUser'], true);
            $message = 'El usuario fue bloqueado correctamente.';
        } elseif (isset($_POST['unblockUser'])) {
            requirePermission('USER_UNLOCK');
            setUserBlocked($pdo, (int) $_POST['unblockUser'], false);
            $message = 'El usuario fue desbloqueado correctamente.';
        } elseif (isset($_POST['activateUser'])) {
            requirePermission('USER_ACTIVATE');
            setUserActivated($pdo, (int) $_POST['activateUser'], true);
            $message = 'El usuario fue activado correctamente.';
        } elseif (isset($_POST['deactivateUser'])) {
            requirePermission('USER_ACTIVATE');
            setUserActivated($pdo, (int) $_POST['deactivateUser'], false);
            $message = 'El usuario fue desactivado correctamente.';
        } elseif (isset($_POST['deleteUser'])) {
            requirePermission('USER_DELETE');
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
    | Filtros y Carga de Datos
    |--------------------------------------------------------------------------
    */

    $filters = [
        'name' => trim($_GET['name'] ?? ''),
        'country' => trim($_GET['country'] ?? ''),
        'documento' => trim($_GET['documento'] ?? ''),
        'email' => trim($_GET['email'] ?? ''),
        'phone' => trim($_GET['phone'] ?? ''),
        'address' => trim($_GET['address'] ?? '')
    ];

    $clientes = getEntitiesByTypeId($pdo, ET_CLIENT, $isAdmin);
    $usersByEntity = getUsersByEntities($pdo, $isAdmin);
    $addressesByEntity = getAddressesByEntities($pdo, $isAdmin);

    // --- INTEGRACIÓN DE CONTACTOS DINÁMICOS ---
    // 1. Obtenemos los tipos de contacto para las columnas de la tabla
    $contactTypes = $pdo->query("SELECT id, name FROM fcrm.contact_type ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

    // 2. Filtrado de clientes
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
                    && $matches($cliente['address'] ?? '', $filters['address']);
            }
        )
    );

    // 3. Carga masiva de contactos para evitar queries en bucle (N+1)
    $contactsByEntity = [];
    $primaryContacts = [];
    if (!empty($clientes)) {
        $entityIds = array_column($clientes, 'id');
        $inQuery = implode(',', array_map('intval', $entityIds));
        
        // Traemos todos los contactos y el nombre del tipo
        $contactStmt = $pdo->query("
            SELECT ec.*, ct.name as type_name 
            FROM fcrm.entity_contact ec 
            JOIN fcrm.contact_type ct ON ec.contactTypeId = ct.id 
            WHERE ec.entityId IN ($inQuery) 
            ORDER BY ec.isPrimary DESC
        ");

        while ($row = $contactStmt->fetch(PDO::FETCH_ASSOC)) {
            $entityId = (int)$row['entityId'];
            $typeId = (int)$row['contactTypeId'];
            
            // Guardamos todos para el collapse
            $contactsByEntity[$entityId][] = $row;
            
            // Guardamos solo el primario para la columna de la tabla
            if ($row['isPrimary']) {
                $primaryContacts[$entityId][$typeId] = $row['contact'];
            }
        }
    }

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
        <form id="clientFilters" method="GET" action=""></form>
            <div class="table-responsive">
                <table id="clientCollapses" class="table table-striped table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>País</th>
                            <th>Documento</th>
                            
                            <!-- COLUMNAS DINÁMICAS DE CONTACTO -->
                            <?php foreach ($contactTypes as $ct): ?>
                                <th><?= htmlspecialchars($ct['name']) ?></th>
                            <?php endforeach; ?>
                            
                            <th>Dirección</th>
                            <th>Info</th>
                            <th class="text-center"> Acciones</th>
                        </tr>

                        <tr>
                            <th>
                                <input form="clientFilters" type="text" name="name" class="form-control form-control-sm" placeholder="Filtrar..." value="<?= htmlspecialchars($filters['name'], ENT_QUOTES, 'UTF-8') ?>">
                            </th>
                            <th>
                                <input form="clientFilters" type="text" name="country" class="form-control form-control-sm" placeholder="Filtrar..." value="<?= htmlspecialchars($filters['country'], ENT_QUOTES, 'UTF-8') ?>">
                            </th>
                            <th>
                                <input form="clientFilters" type="text" name="documento" class="form-control form-control-sm" placeholder="Filtrar..." value="<?= htmlspecialchars($filters['documento'], ENT_QUOTES, 'UTF-8') ?>">
                            </th>
                            
                            <!-- Filtros vacíos para mantener alineación de columnas de contacto -->
                            <?php foreach ($contactTypes as $ct): ?>
                                <th></th>
                            <?php endforeach; ?>

                            <th>
                                <input form="clientFilters" type="text" name="address" class="form-control form-control-sm" placeholder="Filtrar..." value="<?= htmlspecialchars($filters['address'], ENT_QUOTES, 'UTF-8') ?>">
                            </th>
                            <th>
                            </th>

                            <th class="text-end text-nowrap">
                                <button form="clientFilters" type="submit" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="Aplicar filtros">
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
                                <td colspan="20" class="text-center text-body-secondary py-4">
                                    No se encontraron clientes.
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($clientes as $cliente): ?>
                            <?php
                                $entityId = (int) $cliente['id'];
                                $relatedUsers = $usersByEntity[$entityId] ?? [];
                                $addresses = $addressesByEntity[$entityId] ?? [];
                                $contacts = $contactsByEntity[$entityId] ?? [];
                                $primaryAddress = $addresses[0] ?? null;
                                $otherAddresses = array_slice($addresses, 1);
                                $hasComments = trim((string) ($cliente['comments'] ?? '')) !== '';
                                $collapseId = 'clientUsers' . $entityId;
                                $addressCollapseId = 'clientAddresses' . $entityId;
                                $commentsCollapseId = 'clientComments' . $entityId;
                                $contactsCollapseId = 'clientContacts' . $entityId;

                                $tooltipAddress = ($otherAddresses === []) ? 'No hay direcciones' : 'Ver otras direcciones';
                            ?>

                            <tr>
                                <!-- Nombre -->
                                <td>
                                    <?= htmlspecialchars((string) ($cliente['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                </td>

                                <!-- País -->
                                <td>
                                    <?= htmlspecialchars((string) ($cliente['countryName'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                </td>

                                <!-- Documento -->
                                <td>
                                    <?= htmlspecialchars(aplicarFormatoDocumento($cliente['documentNumber'] ?? null,
                                     $cliente['documentFormat'] ?? null), ENT_QUOTES, 'UTF-8') ?>
                                </td>

                                <!-- VALORES DINÁMICOS DE CONTACTOS PRIMARIOS -->
                                <?php foreach ($contactTypes as $ct): ?>
                                    <td>
                                        <?= htmlspecialchars($primaryContacts[$entityId][$ct['id']] ?? '---', ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                <?php endforeach; ?>

                                <!-- Dirección -->
                                <td>
                                    <?php if ($primaryAddress): ?>
                                        <?= htmlspecialchars((string) $primaryAddress['addressLine1'], ENT_QUOTES, 'UTF-8') ?>
                                        <?php if (!empty($primaryAddress['cityName'])): ?>
                                            <div class="small text-body-secondary">
                                                <?= htmlspecialchars((string) $primaryAddress['cityName'], ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?= htmlspecialchars((string) ($cliente['address'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                    <?php endif; ?>
                                </td>

                                <!-- Info -->
                                <td>
                                    <?php if (!empty($cliente['isDeleted'])): ?>
                                        <span class="badge text-bg-danger">Borrado</span>
                                    <?php else: ?>
                                        <span class="badge <?= !empty($cliente['hasImported']) ? 'bg-success' : 'bg-danger' ?>" data-bs-toggle="tooltip" title="<?= !empty($cliente['hasImported']) ? 'Importó con Chinalat' : 'No importó con Chinalat' ?>">
                                            <i class="bi bi-box-seam"></i>
                                        </span>

                                        <span class="badge <?= !empty($cliente['isContractSigned']) ? 'bg-success' : 'bg-danger' ?>" data-bs-toggle="tooltip" title="<?= !empty($cliente['isContractSigned']) ? 'Contrato firmado' : 'Contrato no firmado' ?>">
                                            <i class="bi bi-file-earmark-check"></i>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Acciones -->
                                <td class="text-end">
                                    <?php if (!empty($cliente['isDeleted'])): ?>
                                        <span class="text-body-secondary small">Sin acciones</span>
                                    <?php else: ?>
                                        <div class="client-actions">
                                            <div class="client-action-row">
                                                <span data-tooltip="<?= $tooltipAddress ?>">
                                                    <button type="button" class="btn btn-xs btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#<?= $addressCollapseId ?>" <?= $otherAddresses === [] ? 'disabled' : '' ?>>
                                                        <i class="bi bi-geo-alt"></i>
                                                    </button>
                                                </span>
                                                <span>
                                                    <button type="button" class="btn btn-xs btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#<?= $contactsCollapseId ?>" aria-expanded="false" aria-controls="<?= $contactsCollapseId ?>" data-tooltip="Ver todos los contactos" <?= empty($contacts) ? 'disabled' : '' ?>>
                                                        <i class="bi bi-telephone"></i>
                                                    </button>
                                                </span>
                                                <button type="button" class="btn btn-xs btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#<?= $addressCollapseId ?>" aria-expanded="false" aria-controls="<?= $addressCollapseId ?>" data-tooltip="Ver otras direcciones" <?= $otherAddresses === [] ? 'disabled' : '' ?>>
                                                    <i class="bi bi-geo-alt"></i>
                                                </button>
                                                <button type="button" class="btn btn-xs btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#<?= $commentsCollapseId ?>" aria-expanded="false" aria-controls="<?= $commentsCollapseId ?>" data-tooltip="Ver comentarios" <?= !$hasComments ? 'disabled' : '' ?>>
                                                    <i class="bi bi-chat-left-text"></i>
                                                </button>
                                                <?php if (hasPermission('IMPORT_VIEW')): ?>
                                                    <a class="btn btn-xs btn-outline-secondary" href="<?= BASE_URL ?>/private/importaciones/index.php?entityId=<?= $entityId ?>" data-bs-toggle="tooltip" title="Ver importaciones">
                                                        <i class="bi bi-box-seam"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>

                                            <div class="client-action-row">

                                                <?php if (hasPermission('CLIENT_EDIT')): ?>
                                                    <a class="btn btn-xs btn-outline-warning" href="edit.php?id=<?= $entityId ?>" data-bs-toggle="tooltip" title="Editar cliente">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </a>
                                                <?php endif; ?>


                                                <?php if (hasPermission('USER_VIEW')): ?>
                                                    <button type="button" class="btn btn-xs btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>" aria-expanded="false" aria-controls="<?= $collapseId ?>" data-tooltip="Ver usuarios relacionados">
                                                        <i class="bi bi-person-gear"></i>
                                                    </button>
                                                <?php endif; ?>


                                                <?php if (hasPermission('ENTITY_DELETE')): ?>
                                                    <form method="POST" class="d-inline">
                                                        <?= csrfField() ?>
                                                        <button type="submit" name="borrar" value="<?= $entityId ?>" class="btn btn-xs btn-outline-danger" data-bs-toggle="tooltip" title="Borrar cliente" onclick="return confirm('¿Borrar este cliente?');">
                                                            <i class="bi bi-person-dash"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <!-- Detalle de Comentarios -->
                            <?php if ($hasComments): ?>
                                <tr class="comments-detail-row">
                                    <td colspan="20" class="p-0 border-0">
                                        <div id="<?= $commentsCollapseId ?>" class="collapse" data-bs-parent="#clientCollapses">
                                            <div class="p-3 border-bottom bg-body-tertiary text-start">
                                                <strong class="d-block mb-2">Comentarios</strong>
                                                <div><?= nl2br(htmlspecialchars((string) $cliente['comments'], ENT_QUOTES, 'UTF-8')) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            
                            <!-- Detalle de Otras Direcciones -->
                            <?php if ($otherAddresses !== []): ?>
                                <tr class="addresses-detail-row">
                                    <td colspan="20" class="p-0 border-0">
                                        <div id="<?= $addressCollapseId ?>" class="collapse" data-bs-parent="#clientCollapses">
                                            <div class="p-3 border-bottom bg-body-tertiary">
                                                <strong class="d-block mb-2">Otras direcciones</strong>
                                                <div class="row g-2">
                                                    <?php foreach ($otherAddresses as $address): ?>
                                                        <div class="col-md-6">
                                                            <div class="border rounded p-2 h-100">
                                                                <div class="fw-semibold">
                                                                    <?= htmlspecialchars((string) ($address['addressType'] ?? 'Otra'), ENT_QUOTES, 'UTF-8') ?>
                                                                </div>
                                                                <div><?= htmlspecialchars((string) $address['addressLine1'], ENT_QUOTES, 'UTF-8') ?></div>
                                                                <?php if (!empty($address['addressLine2'])): ?>
                                                                    <div><?= htmlspecialchars((string) $address['addressLine2'], ENT_QUOTES, 'UTF-8') ?></div>
                                                                <?php endif; ?>
                                                                <div class="small text-body-secondary">
                                                                    <?= htmlspecialchars(trim(implode(', ', array_filter([
                                                                        $address['postalCode'] ?? '',
                                                                        $address['cityName'] ?? '',
                                                                        $address['stateName'] ?? '',
                                                                        $address['countryName'] ?? ''
                                                                    ]))), ENT_QUOTES, 'UTF-8') ?>
                                                                </div>
                                                                <?php if ($isAdmin && (!empty($address['isDeleted']) || empty($address['isActive']))): ?>
                                                                    <span class="badge text-bg-secondary mt-2">
                                                                        <?= !empty($address['isDeleted']) ? 'Borrada' : 'Inactiva' ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>

                            <!-- Detalle de Contactos (Nuevo comportamiento la misma línea que usuarios/direcciones) -->
                            <?php if (!empty($contacts)): ?>
                                <tr class="contacts-detail-row">
                                    <td colspan="20" class="p-0 border-0">
                                        <div id="<?= $contactsCollapseId ?>" class="collapse" data-bs-parent="#clientCollapses">
                                            <div class="p-3 border-bottom bg-body-tertiary">
                                                <strong class="d-block mb-2">Información de Contacto</strong>
                                                <div class="row g-2">
                                                    <?php foreach ($contacts as $contact): ?>
                                                        <div class="col-md-4">
                                                            <div class="border rounded p-2 h-100 bg-white">
                                                                <div class="fw-semibold small text-muted">
                                                                    <?= htmlspecialchars($contact['type_name'], ENT_QUOTES, 'UTF-8') ?>
                                                                    <?= $contact['isPrimary'] ? '<span class="badge bg-success ms-1">Principal</span>' : '' ?>
                                                                </div>
                                                                <div class="text-dark"><?= htmlspecialchars($contact['contact'], ENT_QUOTES, 'UTF-8') ?></div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>

                            <!-- Detalle de Usuarios Relacionados -->
                            <?php if (empty($cliente['isDeleted']) && hasPermission('USER_VIEW')): ?>
                            <tr class="users-detail-row">
                                <td colspan="20" class="p-0 border-0">
                                    <div id="<?= $collapseId ?>" class="collapse" data-bs-parent="#clientCollapses">
                                        <div class="p-3 border-bottom bg-body-tertiary">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <div><strong>Usuarios relacionados</strong></div>
                                                <?php if (hasPermission('USER_CREATE')): ?>
                                                    <a href="<?= BASE_URL ?>/private/entidades/usuarios/new.php?entityId=<?= $entityId ?>&modal=1" class="btn btn-xs btn-primary user-modal-trigger" data-bs-toggle="modal" data-bs-target="#userFormModal">
                                                        <i class="bi bi-person-plus me-1"></i> Nuevo usuario
                                                    </a>
                                                <?php endif; ?>
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
                                                            <tr><th>Usuario</th><th>Fecha de creación</th><th>Info</th><th class="text-end">Acciones</th></tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($relatedUsers as $user): ?>
                                                                <?php $userId = (int) $user['id']; ?>
                                                                <tr>
                                                                    <td><i class="bi bi-person-circle me-1"></i> <?= htmlspecialchars((string) ($user['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                                                    <td><?= htmlspecialchars((string) ($user['createDate'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                                                    <td>
                                                                        <?php if (empty($user['isDeleted'])): ?>
                                                                            <span class="badge <?= !empty($user['isConfirmed']) ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= !empty($user['isConfirmed']) ? 'Confirmado' : 'No confirmado' ?></span>
                                                                            <span class="badge <?= !empty($user['isBlocked']) ? 'text-bg-danger' : 'text-bg-success' ?>"><?= !empty($user['isBlocked']) ? 'Bloqueado' : 'No bloqueado' ?></span>
                                                                        <?php endif; ?>
                                                                        <?php if ($isAdmin): ?>
                                                                            <?php if (!empty($user['isDeleted'])): ?>
                                                                                <span class="badge text-bg-danger">Borrado</span>
                                                                            <?php elseif (!empty($user['isBlocked'])): ?>
                                                                                <span class="badge text-bg-warning">Bloqueado</span>
                                                                            <?php elseif (empty($user['isConfirmed'])): ?>
                                                                                <span class="badge text-bg-secondary">Pendiente</span>
                                                                            <?php else: ?>
                                                                                <span class="badge text-bg-success">Vigente</span>
                                                                            <?php endif; ?>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td class="text-end text-nowrap">
                                                                        <?php if (!empty($user['isDeleted'])): ?>
                                                                            <span class="text-body-secondary small">Sin acciones</span>
                                                                        <?php else: ?>
                                                                            <?php if (hasPermission('USER_EDIT')): ?>
                                                                                <a href="<?= BASE_URL ?>/private/entidades/usuarios/edit.php?id=<?= $userId ?>&modal=1" class="btn btn-xs btn-outline-warning user-modal-trigger" data-bs-toggle="modal" data-bs-target="#userFormModal" title="Editar usuario"><i class="bi bi-pencil-square"></i></a>
                                                                            <?php endif; ?>
                                                                            <?php if (hasPermission('USER_UNLOCK')): ?>
                                                                                <?php if (!empty($user['isBlocked'])): ?>
                                                                                    <form method="POST" class="d-inline"><?= csrfField() ?><button type="submit" name="unblockUser" value="<?= $userId ?>" class="btn btn-xs btn-outline-success" data-bs-toggle="tooltip" title="Desbloquear usuario"><i class="bi bi-unlock"></i></button></form>
                                                                                <?php else: ?>
                                                                                    <form method="POST" class="d-inline"><?= csrfField() ?><button type="submit" name="blockUser" value="<?= $userId ?>" class="btn btn-xs btn-outline-danger" data-bs-toggle="tooltip" title="Bloquear usuario"><i class="bi bi-lock"></i></button></form>
                                                                                <?php endif; ?>
                                                                            <?php endif; ?>
                                                                            <?php if (hasPermission('USER_DELETE')): ?>
                                                                                <form method="POST" class="d-inline"><?= csrfField() ?><button type="submit" name="deleteUser" value="<?= $userId ?>" class="btn btn-xs btn-outline-danger" data-bs-toggle="tooltip" title="Eliminar usuario" onclick="return confirm('¿Eliminar este usuario?');"><i class="bi bi-trash"></i></button></form>
                                                                            <?php endif; ?>
                                                                        <?php endif; ?>
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
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="userFormModal" tabindex="-1" aria-labelledby="userFormModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userFormModalLabel">Usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-0">
                <iframe id="userFormFrame" title="Formulario de usuario" class="w-100 border-0" style="height: 560px;"></iframe>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('userFormModal');
    const frame = document.getElementById('userFormFrame');

    if (!modal || !frame) {
        return;
    }

    modal.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        frame.src = trigger ? trigger.getAttribute('href') : '';
    });

    modal.addEventListener('hidden.bs.modal', function () {
        frame.src = '';
    });

    window.addEventListener('message', function (event) {
        if (event.data && event.data.type === 'closeUserModal') {
            bootstrap.Modal.getOrCreateInstance(modal).hide();
        }
    });

    document.querySelectorAll('[data-tooltip]').forEach(function (element) {
        new bootstrap.Tooltip(element, { title: element.dataset.tooltip, trigger: 'hover' });
    });
});
</script>

<style>
.client-actions {
    display: inline-flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.2rem;
}
.client-action-row {
    display: flex;
    gap: 0.2rem;
    min-height: 24px;
}
.btn-xs {
    --bs-btn-padding-y: 0.15rem;
    --bs-btn-padding-x: 0.35rem;
    --bs-btn-font-size: 0.72rem;
    --bs-btn-border-radius: 0.25rem;
}
.users-detail-row > td, .contacts-detail-row > td {
    background-color: var(--bs-tertiary-bg);
}
</style>

<?php if ($updateOK === 1): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const toastElement = document.getElementById('successToast');
        if (!toastElement) return;
        const successToast = new bootstrap.Toast(toastElement, { autohide: true, delay: 4000 });
        successToast.show();
    });
    </script>
<?php endif; ?>

<?php include BASE_PATH . '/layouts/footer.php'; ?>
