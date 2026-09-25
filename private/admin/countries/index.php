<?php

declare(strict_types=1);

$title = 'Países';

require_once __DIR__ . '/../../../config/app.php';
require_once BASE_PATH . '/includes/auth.php';
requirePermission('COUNTRY_VIEW');

require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/config/country.php';

$isAdmin = isAdministrator($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken();
    $message = '';

    if (isset($_POST['borrar'])) {
        requirePermission('COUNTRY_DELETE');
        deleteCountry($pdo, (int) $_POST['borrar']);
        $message = 'El país fue eliminado correctamente.';
    } else {
        requirePermission('COUNTRY_EDIT');
        $countryId = (int) ($_POST['bloquear'] ?? $_POST['desbloquear'] ?? 0);

        if ($countryId > 0) {
            $isActive = isset($_POST['bloquear']) ? 0 : 1;
            $stmt = $pdo->prepare("UPDATE country SET isActive = :isActive WHERE id = :countryId AND COALESCE(isDeleted, b'0') = b'0'");
            $stmt->execute([':isActive' => $isActive, ':countryId' => $countryId]);
            $message = $isActive === 1 ? 'El país fue activado correctamente.' : 'El país fue desactivado correctamente.';
        }
    }

    if ($message !== '') {
        $_SESSION['countryUpdateOK'] = 1;
        $_SESSION['countryUpdateMessage'] = $message;
    }

    header('Location: index.php');
    exit;
}

$paises = $isAdmin ? getCountries($pdo, false, true) : getCountries($pdo, true, false);

// state y city son entidades dependientes del país y no utilizan isActive ni isDeleted.
$stateCountStmt = $pdo->query("SELECT countryId, COUNT(*) AS stateCount FROM state GROUP BY countryId");
$stateCounts = [];

while ($row = $stateCountStmt->fetch(PDO::FETCH_ASSOC)) {
    $stateCounts[(int) $row['countryId']] = (int) $row['stateCount'];
}

$updateOK = (int) ($_SESSION['countryUpdateOK'] ?? 0);
$updateMessage = (string) ($_SESSION['countryUpdateMessage'] ?? 'La operación se completó correctamente.');
unset($_SESSION['countryUpdateOK'], $_SESSION['countryUpdateMessage']);

include BASE_PATH . '/layouts/header.php';
include BASE_PATH . '/layouts/sidebar.php';
?>

<?php if ($updateOK === 1): ?>
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1090; margin-top: 65px;">
        <div id="successToast" class="toast text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body"><i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($updateMessage, ENT_QUOTES, 'UTF-8') ?></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Cerrar"></button>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="page-title mb-0">Países</h1>

    <?php if (hasPermission('COUNTRY_CREATE')): ?>
        <a href="<?= BASE_URL ?>/private/admin/country/new.php" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Nuevo país</a>
    <?php endif; ?>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Código ISO2</th>
                        <th>Código ISO3</th>
                        <th>Prefijo</th>
                        <th>Moneda</th>
                        <th>División administrativa</th>
                        <th class="text-center">Cantidad</th>

                        <?php if ($isAdmin): ?>
                            <th>Estado</th>
                        <?php endif; ?>

                        <th class="text-end" style="width: 180px;">Acciones</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if ($paises === []): ?>
                        <tr>
                            <td colspan="<?= $isAdmin ? 9 : 8 ?>" class="text-center text-body-secondary py-4">No se encontraron países.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($paises as $pais): ?>
                        <?php
                        $countryId = (int) $pais['id'];
                        $stateCount = $stateCounts[$countryId] ?? 0;
                        ?>

                        <tr>
                            <td><?= htmlspecialchars((string) ($pais['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($pais['iso2Code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($pais['iso3Code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($pais['phonePrefix'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($pais['currencyName'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($pais['stateName'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-center"><span class="badge text-bg-primary"><?= $stateCount ?></span></td>

                            <?php if ($isAdmin): ?>
                                <td>
                                    <?php if (!empty($pais['isDeleted'])): ?>
                                        <span class="badge text-bg-danger">Borrado</span>
                                    <?php elseif (empty($pais['isActive'])): ?>
                                        <span class="badge text-bg-secondary">Desactivado</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-success">Activo</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>

                            <td class="text-end text-nowrap">
                                <?php if (empty($pais['isDeleted']) && hasPermission('STATE_VIEW')): ?>
                                    <a href="<?= BASE_URL ?>/private/admin/states/index.php?countryId=<?= $countryId ?>" class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="Ver registros de <?= htmlspecialchars(mb_strtolower($pais['stateName'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                        <i class="bi bi-map me-1"></i>
                                        <?= $stateCount ?>
                                    </a>
                                <?php endif; ?>

                                <?php if (empty($pais['isDeleted']) && hasPermission('COUNTRY_EDIT')): ?>
                                    <a href="<?= BASE_URL ?>/private/admin/country/edit.php?id=<?= $countryId ?>" class="btn btn-sm btn-outline-warning" data-bs-toggle="tooltip" title="Editar país">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>

                                    <?php if (!empty($pais['isActive'])): ?>
                                        <form method="POST" class="d-inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="bloquear" value="<?= $countryId ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" data-bs-toggle="tooltip" title="Desactivar país"><i class="bi bi-lock"></i></button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" class="d-inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="desbloquear" value="<?= $countryId ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-success" data-bs-toggle="tooltip" title="Activar país">
                                                <i class="bi bi-unlock"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php if (empty($pais['isDeleted']) && hasPermission('COUNTRY_DELETE')): ?>
                                    <form method="POST" class="d-inline">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="borrar" value="<?= $countryId ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" data-bs-toggle="tooltip" title="Borrar país" onclick="return confirm('¿Borrar este país?');">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if (empty($pais['isDeleted']) && !empty($pais['isActive']) && hasPermission('COUNTRY_EDIT')): ?>
                                    <form method="POST" action="<?= BASE_URL ?>/private/admin/countries/country_import.php?countryId=<?= $countryId ?>" class="d-inline">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="countryId" value="<?= (int) $countryId ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-info" data-bs-toggle="tooltip" title="Importar divisiones administrativas y ciudades">
                                            <i class="bi bi-cloud-download"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($updateOK === 1): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const toastElement = document.getElementById('successToast');
        if (toastElement) new bootstrap.Toast(toastElement, { autohide: true, delay: 4000 }).show();
    });
    </script>
<?php endif; ?>

<?php include BASE_PATH . '/layouts/footer.php'; ?>
