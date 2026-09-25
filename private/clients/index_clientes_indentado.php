<?php

declare(strict_types=1);

$title = "Clientes";

require_once "../../../includes/init.php";
requirePermission("CLIENT_VIEW");
require_once BASE_PATH . "/config/entity.php";

$isAdmin = isAdministrator($pdo);

function aplicarFormatoTelefono(?string $valor, ?string $formato, ?string $prefijo, int $digitos = 0): string {
    if ($valor === null || $valor === "") {
        return "";
    }
    $valor = preg_replace("/\D/", "", $valor) ?? "";
    $valor = substr($valor, max(0, $digitos));
    if ($formato === null || $formato === "") {
        return trim(($prefijo ?? "") . " " . $valor);
    }
    $resultado = trim((string) $prefijo);
    if ($resultado !== "") {
        $resultado .= " ";
    }
    $indice = 0;
    foreach (str_split($formato) as $caracter) {
        if ($caracter === "#") {
            if (!isset($valor[$indice])) {
                break;
            }
            $resultado .= $valor[$indice++];
        } else {
            $resultado .= $caracter;
        }
    }
    return trim($resultado);
}

function aplicarFormatoDocumento(?string $valor, ?string $formato): string {
    if ($valor === null || $valor === "") {
        return "";
    }
    $valor = preg_replace("/\D/", "", $valor) ?? "";
    if ($formato === null || $formato === "") {
        return $valor;
    }
    $resultado = "";
    $indice = 0;
    foreach (str_split($formato) as $caracter) {
        if ($caracter === "#") {
            if (!isset($valor[$indice])) {
                break;
            }
            $resultado .= $valor[$indice++];
        } else {
            $resultado .= $caracter;
        }
    }
    return $resultado;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    requireValidCsrfToken();
    $message = "";

    if (isset($_POST["bloquear"])) {
        requirePermission("CLIENT_BLOCK");
        setClientBlocked($pdo, (int) $_POST["bloquear"], true);
        $message = "El cliente fue bloqueado correctamente.";
    } elseif (isset($_POST["desbloquear"])) {
        requirePermission("CLIENT_BLOCK");
        setClientBlocked($pdo, (int) $_POST["desbloquear"], false);
        $message = "El cliente fue desbloqueado correctamente.";
    } elseif (isset($_POST["borrar"])) {
        requirePermission("ENTITY_DELETE");
        deleteClient($pdo, (int) $_POST["borrar"]);
        $message = "El cliente fue eliminado correctamente.";
    } elseif (isset($_POST["deleteAddress"])) {
        requirePermission("CLIENT_EDIT");
        deleteEntityAddress($pdo, (int) $_POST["deleteAddress"]);
        $message = "La dirección fue eliminada correctamente.";
    } elseif (isset($_POST["deleteContact"])) {
        requirePermission("CLIENT_EDIT");
        deleteEntityContact($pdo, (int) $_POST["deleteContact"]);
        $message = "El contacto fue eliminado correctamente.";
    } elseif (isset($_POST["blockUser"])) {
        requirePermission("USER_UNLOCK");
        setUserBlocked($pdo, (int) $_POST["blockUser"], true);
        $message = "El usuario fue bloqueado correctamente.";
    } elseif (isset($_POST["unblockUser"])) {
        requirePermission("USER_UNLOCK");
        setUserBlocked($pdo, (int) $_POST["unblockUser"], false);
        $message = "El usuario fue desbloqueado correctamente.";
    } elseif (isset($_POST["activateUser"])) {
        requirePermission("USER_ACTIVATE");
        setUserActivated($pdo, (int) $_POST["activateUser"], true);
        $message = "El usuario fue activado correctamente.";
    } elseif (isset($_POST["deactivateUser"])) {
        requirePermission("USER_ACTIVATE");
        setUserActivated($pdo, (int) $_POST["deactivateUser"], false);
        $message = "El usuario fue desactivado correctamente.";
    } elseif (isset($_POST["deleteUser"])) {
        requirePermission("USER_DELETE");
        deleteUser($pdo, (int) $_POST["deleteUser"]);
        $message = "El usuario fue eliminado correctamente.";
    }

    if ($message !== "") {
        $_SESSION["entityUpdateOK"] = 1;
        $_SESSION["entityUpdateMessage"] = $message;
    }

    header("Location: " . BASE_URL . "/private/entidades/clientes/index.php");
    exit();
}

$filters = [
    "name" => trim($_GET["name"] ?? ""),
    "country" => trim($_GET["country"] ?? ""),
    "documento" => trim($_GET["documento"] ?? ""),
    "email" => trim($_GET["email"] ?? ""),
    "phone" => trim($_GET["phone"] ?? ""),
    "address" => trim($_GET["address"] ?? ""),
];

$clientes = getEntitiesByTypeId($pdo, ET_CLIENT, $isAdmin);
$usersByEntity = getUsersByEntities($pdo, $isAdmin);
$addressesByEntity = getAddressesByEntities($pdo, $isAdmin);
$contactTypes = $pdo->query("SELECT id, name FROM fcrm.contact_type ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

$clientes = array_values(
    array_filter($clientes, static function (array $cliente) use ($filters, $addressesByEntity): bool {
        $matches = static function (?string $value, string $filter): bool {
            return $filter === "" || mb_stripos((string) $value, $filter) !== false;
        };
        $entityId = (int) $cliente["id"];
        $addressValues = [(string) ($cliente["address"] ?? "")];

        foreach ($addressesByEntity[$entityId] ?? [] as $address) {
            $addressValues[] = implode(" ", array_filter([$address["addressLine1"] ?? "", $address["addressLine2"] ?? "", $address["postalCode"] ?? "", $address["cityName"] ?? "", $address["stateName"] ?? "", $address["countryName"] ?? "", $address["addressType"] ?? ""]));
        }

        return $matches($cliente["name"] ?? "", $filters["name"]) && $matches($cliente["countryName"] ?? "", $filters["country"]) && $matches($cliente["documentNumber"] ?? "", $filters["documento"]) && $matches($cliente["email"] ?? "", $filters["email"]) && $matches($cliente["phone"] ?? "", $filters["phone"]) && $matches(implode(" ", $addressValues), $filters["address"]);
    }),
);

$contactsByEntity = [];
$primaryContacts = [];
if ($clientes !== []) {
    $entityIds = array_map("intval", array_column($clientes, "id"));
    $placeholders = implode(",", array_fill(0, count($entityIds), "?"));
    $contactStmt = $pdo->prepare("SELECT ec.*, ct.name AS type_name FROM fcrm.entity_contact ec INNER JOIN fcrm.contact_type ct ON ct.id = ec.contactTypeId WHERE ec.entityId IN ($placeholders) ORDER BY ec.entityId, ec.isPrimary DESC, ec.id");
    $contactStmt->execute($entityIds);
    while ($row = $contactStmt->fetch(PDO::FETCH_ASSOC)) {
        $entityId = (int) $row["entityId"];
        $typeId = (int) $row["contactTypeId"];
        $contactsByEntity[$entityId][] = $row;
        if (!empty($row["isPrimary"]) && !isset($primaryContacts[$entityId][$typeId])) {
            $primaryContacts[$entityId][$typeId] = $row["contact"];
        }
    }
}

$updateOK = (int) ($_SESSION["entityUpdateOK"] ?? 0);
$updateMessage = (string) ($_SESSION["entityUpdateMessage"] ?? "Los datos se actualizaron correctamente.");
unset($_SESSION["entityUpdateOK"], $_SESSION["entityUpdateMessage"]);

include BASE_PATH . "/layouts/header.php";
include BASE_PATH . "/layouts/sidebar.php";
?>

<?php if ($updateOK === 1): ?>
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1090; margin-top: 65px;"><div id="successToast" class="toast text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true"><div class="d-flex"><div class="toast-body"><i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($updateMessage, ENT_QUOTES, "UTF-8") ?></div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Cerrar"></button></div></div></div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4"><h1 class="page-title mb-0">Clientes</h1><a href="<?= BASE_URL ?>/private/entidades/clientes/new.php" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Nuevo cliente</a></div>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="GET" action="" class="border rounded p-3 mb-4 bg-body-tertiary">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label for="filterName" class="form-label">Nombre</label>
                    <input type="text" id="filterName" name="name" class="form-control form-control-sm" placeholder="Filtrar por nombre" value="<?= htmlspecialchars($filters["name"], ENT_QUOTES, "UTF-8") ?>">
                </div>
                <div class="col-md-2">
                    <label for="filterCountry" class="form-label">País</label>
                    <input type="text" id="filterCountry" name="country" class="form-control form-control-sm" placeholder="Filtrar por país" value="<?= htmlspecialchars($filters["country"], ENT_QUOTES, "UTF-8") ?>">
                </div>
                <div class="col-md-2">
                    <label for="filterDocument" class="form-label">Documento</label>
                    <input type="text" id="filterDocument" name="documento" class="form-control form-control-sm" placeholder="Filtrar por documento" value="<?= htmlspecialchars($filters["documento"], ENT_QUOTES, "UTF-8") ?>">
                </div>
                <div class="col-md-2">
                    <label for="filterEmail" class="form-label">Correo</label>
                    <input type="text" id="filterEmail" name="email" class="form-control form-control-sm" placeholder="Filtrar por correo" value="<?= htmlspecialchars($filters["email"], ENT_QUOTES, "UTF-8") ?>">
                </div>
                <div class="col-md-2">
                    <label for="filterPhone" class="form-label">Teléfono</label>
                    <input type="text" id="filterPhone" name="phone" class="form-control form-control-sm" placeholder="Filtrar por teléfono" value="<?= htmlspecialchars($filters["phone"], ENT_QUOTES, "UTF-8") ?>">
                </div>
                <div class="col-md-2">
                    <label for="filterAddress" class="form-label">Dirección</label>
                    <input type="text" id="filterAddress" name="address" class="form-control form-control-sm" placeholder="Calle, ciudad, CP..." value="<?= htmlspecialchars($filters["address"], ENT_QUOTES, "UTF-8") ?>">
                </div>
                <div class="col-12 d-flex justify-content-end gap-2">
                    <a href="index.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg me-1"></i>Limpiar</a>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search me-1"></i>Buscar</button>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table id="clientCollapses" class="table table-striped table-hover align-middle">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>País</th>
                        <th>Documento</th>
                        <?php foreach ($contactTypes as $ct): ?>
                            <th><?= htmlspecialchars((string) $ct["name"], ENT_QUOTES, "UTF-8") ?></th>
                        <?php endforeach; ?>
                        <th>Dirección</th>
                        <th>Info</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
<tbody>
<?php if ($clientes === []): ?><tr><td colspan="<?= count($contactTypes) + 6 ?>" class="text-center text-body-secondary py-4">No se encontraron clientes.</td></tr><?php endif; ?>

<?php foreach ($clientes as $cliente): ?>
<?php
$entityId = (int) $cliente["id"];
$relatedUsers = $usersByEntity[$entityId] ?? [];
$addresses = $addressesByEntity[$entityId] ?? [];
$contacts = $contactsByEntity[$entityId] ?? [];
$primaryAddress = null;
foreach ($addresses as $addressItem) {
    if (!empty($addressItem["isPrimary"]) && empty($addressItem["isDeleted"])) {
        $primaryAddress = $addressItem;
        break;
    }
}
if ($primaryAddress === null) {
    foreach ($addresses as $addressItem) {
        if (empty($addressItem["isDeleted"])) {
            $primaryAddress = $addressItem;
            break;
        }
    }
}
$hasComments = trim((string) ($cliente["comments"] ?? "")) !== "";
$usersCollapseId = "clientUsers" . $entityId;
$addressesCollapseId = "clientAddresses" . $entityId;
$commentsCollapseId = "clientComments" . $entityId;
$contactsCollapseId = "clientContacts" . $entityId;
?>
<tr>
<td><?= htmlspecialchars((string) ($cliente["name"] ?? ""), ENT_QUOTES, "UTF-8") ?></td>
<td><?= htmlspecialchars((string) ($cliente["countryName"] ?? ""), ENT_QUOTES, "UTF-8") ?></td>
<td><?= htmlspecialchars(aplicarFormatoDocumento($cliente["documentNumber"] ?? null, $cliente["documentFormat"] ?? null), ENT_QUOTES, "UTF-8") ?></td>
<?php foreach ($contactTypes as $ct): ?><td><?= htmlspecialchars((string) ($primaryContacts[$entityId][(int) $ct["id"]] ?? "---"), ENT_QUOTES, "UTF-8") ?></td><?php endforeach; ?>
<td><?php if ($primaryAddress):
    htmlspecialchars((string) ($primaryAddress["addressLine1"] ?? ""), ENT_QUOTES, "UTF-8")
    if (!empty($primaryAddress["cityName"])): ?><div class="small text-body-secondary"><?= htmlspecialchars((string) $primaryAddress["cityName"], ENT_QUOTES, "UTF-8") ?></div><?php endif;
else:
    htmlspecialchars((string) ($cliente["address"] ?? ""), ENT_QUOTES, "UTF-8")
endif; ?></td>
<td><?php if (!empty($cliente["isDeleted"])): ?><span class="badge text-bg-danger">Borrado</span><?php else: ?><span class="badge <?= !empty($cliente["hasImported"]) ? "bg-success" : "bg-danger" ?>" data-bs-toggle="tooltip" title="<?= !empty($cliente["hasImported"]) ? "Importó con Chinalat" : "No importó con Chinalat" ?>"><i class="bi bi-box-seam"></i></span> <span class="badge <?= !empty($cliente["isContractSigned"]) ? "bg-success" : "bg-danger" ?>" data-bs-toggle="tooltip" title="<?= !empty($cliente["isContractSigned"]) ? "Contrato firmado" : "Contrato no firmado" ?>"><i class="bi bi-file-earmark-check"></i></span><?php endif; ?></td>
<td class="text-end">
<?php if (!empty($cliente["isDeleted"])): ?><span class="text-body-secondary small">Sin acciones</span><?php else: ?>
<div class="client-actions">
<div class="client-action-row">
<button type="button" class="btn btn-xs btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#<?= $contactsCollapseId ?>" data-tooltip="Administrar contactos" aria-expanded="false" aria-controls="<?= $contactsCollapseId ?>"><i class="bi bi-telephone"></i></button>
<button type="button" class="btn btn-xs btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#<?= $addressesCollapseId ?>" data-tooltip="Administrar direcciones" aria-expanded="false" aria-controls="<?= $addressesCollapseId ?>"><i class="bi bi-geo-alt"></i></button>
<button type="button" class="btn btn-xs btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#<?= $commentsCollapseId ?>" data-tooltip="Ver comentarios" aria-expanded="false" aria-controls="<?= $commentsCollapseId ?>" <?= !$hasComments ? "disabled" : "" ?>><i class="bi bi-chat-left-text"></i></button>
<?php if (hasPermission("IMPORT_VIEW")): ?><a class="btn btn-xs btn-outline-secondary" href="<?= BASE_URL ?>/private/importaciones/index.php?entityId=<?= $entityId ?>" data-bs-toggle="tooltip" title="Ver importaciones"><i class="bi bi-box-seam"></i></a><?php endif; ?>
</div>
<div class="client-action-row">
<?php if (hasPermission("CLIENT_EDIT")): ?><a class="btn btn-xs btn-outline-warning" href="edit.php?id=<?= $entityId ?>" data-bs-toggle="tooltip" title="Editar cliente"><i class="bi bi-pencil-square"></i></a><?php endif; ?>
<?php if (hasPermission("USER_VIEW")): ?><button type="button" class="btn btn-xs btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#<?= $usersCollapseId ?>" data-tooltip="Administrar usuarios" aria-expanded="false" aria-controls="<?= $usersCollapseId ?>"><i class="bi bi-person-gear"></i></button><?php endif; ?>
<?php if (hasPermission("ENTITY_DELETE")): ?><form method="POST" class="d-inline"><?= csrfField() ?><button type="submit" name="borrar" value="<?= $entityId ?>" class="btn btn-xs btn-outline-danger" data-bs-toggle="tooltip" title="Borrar cliente" onclick="return confirm('¿Borrar este cliente?');"><i class="bi bi-person-dash"></i></button></form><?php endif; ?>
</div>
</div>
<?php endif; ?>
</td>
</tr>

<tr class="contacts-detail-row"><td colspan="<?= count($contactTypes) + 6 ?>" class="p-0 border-0"><div id="<?= $contactsCollapseId ?>" class="collapse" data-bs-parent="#clientCollapses"><div class="p-3 border-bottom bg-body-tertiary">
<div class="d-flex justify-content-between align-items-center mb-3"><strong>Contactos</strong><?php if (hasPermission("CLIENT_EDIT")): ?><a href="<?= BASE_URL ?>/private/entidades/contactos/new.php?entityId=<?= $entityId ?>&modal=1" class="btn btn-xs btn-primary entity-modal-trigger" data-bs-toggle="modal" data-bs-target="#entityFormModal" data-modal-title="Nuevo contacto"><i class="bi bi-plus-circle me-1"></i>Nuevo contacto</a><?php endif; ?></div>
<?php if ($contacts === []): ?><div class="alert alert-secondary mb-0"><i class="bi bi-info-circle me-1"></i>Este cliente no tiene contactos registrados.</div><?php else: ?>
<div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0"><thead><tr><th>Tipo</th><th>Contacto</th><th>Comentario</th><th>Info</th><th class="text-end">Acciones</th></tr></thead><tbody>
<?php foreach ($contacts as $contact):
    $contactId = (int) $contact["id"]; ?><tr><td><?= htmlspecialchars((string) ($contact["type_name"] ?? ""), ENT_QUOTES, "UTF-8") ?></td><td><?= htmlspecialchars((string) ($contact["contact"] ?? ""), ENT_QUOTES, "UTF-8") ?></td><td><?= htmlspecialchars((string) ($contact["comment"] ?? ""), ENT_QUOTES, "UTF-8") ?></td><td><?php
if (!empty($contact["isPrimary"])): ?><span class="badge text-bg-success">Principal</span><?php endif;
if (empty($contact["isActive"])): ?> <span class="badge text-bg-secondary">Inactivo</span><?php endif;
if (!empty($contact["isDeleted"])): ?> <span class="badge text-bg-danger">Borrado</span><?php endif;
?></td><td class="text-end text-nowrap"><?php if (empty($contact["isDeleted"]) && hasPermission("CLIENT_EDIT")): ?><a href="<?= BASE_URL ?>/private/entidades/contactos/edit.php?id=<?= $contactId ?>&modal=1" class="btn btn-xs btn-outline-warning entity-modal-trigger" data-bs-toggle="modal" data-bs-target="#entityFormModal" data-modal-title="Editar contacto" title="Editar contacto"><i class="bi bi-pencil-square"></i></a> <form method="POST" class="d-inline"><?= csrfField() ?><button type="submit" name="deleteContact" value="<?= $contactId ?>" class="btn btn-xs btn-outline-danger" data-bs-toggle="tooltip" title="Eliminar contacto" onclick="return confirm('¿Eliminar este contacto?');"><i class="bi bi-trash"></i></button></form><?php else: ?><span class="text-body-secondary small">Sin acciones</span><?php endif; ?></td></tr><?php
endforeach; ?>
</tbody></table></div><?php endif; ?>
</div></div></td></tr>

<tr class="addresses-detail-row"><td colspan="<?= count($contactTypes) + 6 ?>" class="p-0 border-0"><div id="<?= $addressesCollapseId ?>" class="collapse" data-bs-parent="#clientCollapses"><div class="p-3 border-bottom bg-body-tertiary">
<div class="d-flex justify-content-between align-items-center mb-3"><strong>Direcciones</strong><?php if (hasPermission("CLIENT_EDIT")): ?><a href="<?= BASE_URL ?>/private/entidades/direcciones/new.php?entityId=<?= $entityId ?>&modal=1" class="btn btn-xs btn-primary entity-modal-trigger" data-bs-toggle="modal" data-bs-target="#entityFormModal" data-modal-title="Nueva dirección"><i class="bi bi-plus-circle me-1"></i>Nueva dirección</a><?php endif; ?></div>
<?php if ($addresses === []): ?><div class="alert alert-secondary mb-0"><i class="bi bi-info-circle me-1"></i>Este cliente no tiene direcciones registradas.</div><?php else: ?>
<div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0"><thead><tr><th>Tipo</th><th>Dirección</th><th>Localidad</th><th>Info</th><th class="text-end">Acciones</th></tr></thead><tbody>
<?php foreach ($addresses as $address):
    $addressId = (int) $address["id"]; ?><tr><td><?= htmlspecialchars((string) ($address["addressType"] ?? ""), ENT_QUOTES, "UTF-8") ?></td><td><div><?= htmlspecialchars((string) ($address["addressLine1"] ?? ""), ENT_QUOTES, "UTF-8") ?></div><?php if (!empty($address["addressLine2"])): ?><div class="small text-body-secondary"><?= htmlspecialchars((string) $address["addressLine2"], ENT_QUOTES, "UTF-8") ?></div><?php endif; ?></td><td><?= htmlspecialchars(trim(implode(", ", array_filter([$address["postalCode"] ?? "", $address["cityName"] ?? "", $address["stateName"] ?? "", $address["countryName"] ?? ""]))), ENT_QUOTES, "UTF-8") ?></td><td><?php
if (!empty($address["isPrimary"])): ?><span class="badge text-bg-success">Principal</span><?php endif;
if (empty($address["isActive"])): ?> <span class="badge text-bg-secondary">Inactiva</span><?php endif;
if (!empty($address["isDeleted"])): ?> <span class="badge text-bg-danger">Borrada</span><?php endif;
?></td><td class="text-end text-nowrap"><?php if (empty($address["isDeleted"]) && hasPermission("CLIENT_EDIT")): ?><a href="<?= BASE_URL ?>/private/entidades/direcciones/edit.php?id=<?= $addressId ?>&modal=1" class="btn btn-xs btn-outline-warning entity-modal-trigger" data-bs-toggle="modal" data-bs-target="#entityFormModal" data-modal-title="Editar dirección" title="Editar dirección"><i class="bi bi-pencil-square"></i></a> <form method="POST" class="d-inline"><?= csrfField() ?><button type="submit" name="deleteAddress" value="<?= $addressId ?>" class="btn btn-xs btn-outline-danger" data-bs-toggle="tooltip" title="Eliminar dirección" onclick="return confirm('¿Eliminar esta dirección?');"><i class="bi bi-trash"></i></button></form><?php else: ?><span class="text-body-secondary small">Sin acciones</span><?php endif; ?></td></tr><?php
endforeach; ?>
</tbody></table></div><?php endif; ?>
</div></div></td></tr>

<?php if ($hasComments): ?><tr class="comments-detail-row"><td colspan="<?= count($contactTypes) + 6 ?>" class="p-0 border-0"><div id="<?= $commentsCollapseId ?>" class="collapse" data-bs-parent="#clientCollapses"><div class="p-3 border-bottom bg-body-tertiary"><div class="d-flex justify-content-between align-items-center mb-2"><strong>Comentarios</strong><?php if (hasPermission("CLIENT_EDIT")): ?><a href="edit.php?id=<?= $entityId ?>#comments" class="btn btn-xs btn-outline-warning"><i class="bi bi-pencil-square me-1"></i>Editar</a><?php endif; ?></div><div><?= nl2br(htmlspecialchars((string) $cliente["comments"], ENT_QUOTES, "UTF-8")) ?></div></div></div></td></tr><?php endif; ?>

<?php if (empty($cliente["isDeleted"]) && hasPermission("USER_VIEW")): ?>
<tr class="users-detail-row"><td colspan="<?= count($contactTypes) + 6 ?>" class="p-0 border-0"><div id="<?= $usersCollapseId ?>" class="collapse" data-bs-parent="#clientCollapses"><div class="p-3 border-bottom bg-body-tertiary">
<div class="d-flex justify-content-between align-items-center mb-3"><strong>Usuarios relacionados</strong><?php if (hasPermission("USER_CREATE")): ?><a href="<?= BASE_URL ?>/private/entidades/usuarios/new.php?entityId=<?= $entityId ?>&modal=1" class="btn btn-xs btn-primary user-modal-trigger" data-bs-toggle="modal" data-bs-target="#userFormModal"><i class="bi bi-person-plus me-1"></i>Nuevo usuario</a><?php endif; ?></div>
<?php if ($relatedUsers === []): ?><div class="alert alert-secondary mb-0"><i class="bi bi-info-circle me-1"></i>Este cliente no tiene usuarios relacionados.</div><?php else: ?>
<div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0"><thead><tr><th>Usuario</th><th>Fecha de creación</th><th>Info</th><th class="text-end">Acciones</th></tr></thead><tbody>
<?php foreach ($relatedUsers as $user):
    $userId = (int) $user["id"]; ?><tr><td><i class="bi bi-person-circle me-1"></i><?= htmlspecialchars((string) ($user["username"] ?? ""), ENT_QUOTES, "UTF-8") ?></td><td><?= htmlspecialchars((string) ($user["createDate"] ?? ""), ENT_QUOTES, "UTF-8") ?></td><td><?php if (!empty($user["isDeleted"])): ?><span class="badge text-bg-danger">Borrado</span><?php elseif (!empty($user["isBlocked"])): ?><span class="badge text-bg-warning">Bloqueado</span><?php elseif (empty($user["isConfirmed"])): ?><span class="badge text-bg-secondary">Pendiente</span><?php else: ?><span class="badge text-bg-success">Vigente</span><?php endif; ?></td><td class="text-end text-nowrap"><?php if (!empty($user["isDeleted"])): ?><span class="text-body-secondary small">Sin acciones</span><?php else:if (
        hasPermission("USER_EDIT")
    ): ?><a href="<?= BASE_URL ?>/private/entidades/usuarios/edit.php?id=<?= $userId ?>&modal=1" class="btn btn-xs btn-outline-warning user-modal-trigger" data-bs-toggle="modal" data-bs-target="#userFormModal" title="Editar usuario"><i class="bi bi-pencil-square"></i></a><?php endif; ?> <?php if (hasPermission("USER_UNLOCK")):
     if (!empty($user["isBlocked"])): ?><form method="POST" class="d-inline"><?= csrfField() ?><button type="submit" name="unblockUser" value="<?= $userId ?>" class="btn btn-xs btn-outline-success" data-bs-toggle="tooltip" title="Desbloquear usuario"><i class="bi bi-unlock"></i></button></form><?php else: ?><form method="POST" class="d-inline"><?= csrfField() ?><button type="submit" name="blockUser" value="<?= $userId ?>" class="btn btn-xs btn-outline-danger" data-bs-toggle="tooltip" title="Bloquear usuario"><i class="bi bi-lock"></i></button></form><?php endif;
 endif; ?> <?php if (hasPermission("USER_DELETE")): ?><form method="POST" class="d-inline"><?= csrfField() ?><button type="submit" name="deleteUser" value="<?= $userId ?>" class="btn btn-xs btn-outline-danger" data-bs-toggle="tooltip" title="Eliminar usuario" onclick="return confirm('¿Eliminar este usuario?');"><i class="bi bi-trash"></i></button></form><?php endif;endif; ?></td></tr><?php
endforeach; ?>
</tbody></table></div><?php endif; ?>
</div></div></td></tr>
<?php endif; ?>
<?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="userFormModal" tabindex="-1" aria-labelledby="userFormModalLabel" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="userFormModalLabel">Usuario</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><div class="modal-body p-0"><iframe id="userFormFrame" title="Formulario de usuario" class="w-100 border-0" style="height: 560px;"></iframe></div></div></div></div>
<div class="modal fade" id="entityFormModal" tabindex="-1" aria-labelledby="entityFormModalLabel" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="entityFormModalLabel">Formulario</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><div class="modal-body p-0"><iframe id="entityFormFrame" title="Formulario" class="w-100 border-0" style="height: 560px;"></iframe></div></div></div></div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const userModal = document.getElementById('userFormModal');
    const userFrame = document.getElementById('userFormFrame');
    const entityModal = document.getElementById('entityFormModal');
    const entityFrame = document.getElementById('entityFormFrame');
    const entityModalTitle = document.getElementById('entityFormModalLabel');

    if (userModal && userFrame) {
        userModal.addEventListener('show.bs.modal', function (event) { const trigger = event.relatedTarget; userFrame.src = trigger ? trigger.getAttribute('href') : ''; });
        userModal.addEventListener('hidden.bs.modal', function () { userFrame.src = ''; });
    }

    if (entityModal && entityFrame) {
        entityModal.addEventListener('show.bs.modal', function (event) { const trigger = event.relatedTarget; entityFrame.src = trigger ? trigger.getAttribute('href') : ''; entityModalTitle.textContent = trigger?.dataset.modalTitle || 'Formulario'; });
        entityModal.addEventListener('hidden.bs.modal', function () { entityFrame.src = ''; });
    }

    window.addEventListener('message', function (event) {
        if (!event.data) return;
        if (event.data.type === 'closeUserModal' && userModal) { bootstrap.Modal.getOrCreateInstance(userModal).hide(); window.location.reload(); }
        if (event.data.type === 'closeEntityModal' && entityModal) { bootstrap.Modal.getOrCreateInstance(entityModal).hide(); window.location.reload(); }
    });

    document.querySelectorAll('[data-tooltip]').forEach(function (element) {
        new bootstrap.Tooltip(element, { title: element.dataset.tooltip, placement: 'top', trigger: 'hover', container: 'body', popperConfig: function (config) { config.modifiers = config.modifiers.map(function (modifier) { return modifier.name === 'flip' ? { ...modifier, enabled: false } : modifier; }); return config; } });
    });

    <?php if ($updateOK === 1): ?>
    const toastElement = document.getElementById('successToast');
    if (toastElement) new bootstrap.Toast(toastElement, { autohide: true, delay: 4000 }).show();
    <?php endif; ?>
});
</script>

<style>
.client-actions { display: inline-flex; flex-direction: column; align-items: flex-end; gap: 0.2rem; }
.client-action-row { display: flex; gap: 0.2rem; min-height: 24px; }
.btn-xs { --bs-btn-padding-y: 0.15rem; --bs-btn-padding-x: 0.35rem; --bs-btn-font-size: 0.72rem; --bs-btn-border-radius: 0.25rem; }
.users-detail-row > td, .contacts-detail-row > td, .addresses-detail-row > td, .comments-detail-row > td { background-color: var(--bs-tertiary-bg); }
.tooltip-inner { max-width: 220px; white-space: nowrap; }
</style>

<?php include BASE_PATH . "/layouts/footer.php"; ?>
