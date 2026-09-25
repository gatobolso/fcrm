<?php

declare(strict_types=1);

$title = 'Divisiones administrativas';

require_once __DIR__ . '/../../../config/app.php';
require_once BASE_PATH . '/includes/auth.php';
requirePermission('STATE_VIEW');

require_once BASE_PATH . '/config/database.php';

$countryId = (int) ($_GET['countryId'] ?? $_POST['countryId'] ?? 0);
$name = trim($_GET['name'] ?? '');
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken();

    if (isset($_POST['deleteState'])) {
        requirePermission('STATE_DELETE');
        $stateId = (int) $_POST['deleteState'];

        try {
            $stmt = $pdo->prepare('DELETE FROM state WHERE id = :stateId');
            $stmt->execute([':stateId' => $stateId]);

            if ($stmt->rowCount() === 0) {
                throw new RuntimeException('La división administrativa no existe o ya fue eliminada.');
            }

            $_SESSION['stateUpdateOK'] = 1;
            $_SESSION['stateUpdateMessage'] = 'La división administrativa fue eliminada correctamente.';
        } catch (PDOException $e) {
            error_log($e->getMessage());

            if ((string) $e->getCode() === '23000') {
                $_SESSION['stateUpdateError'] = 'No se puede eliminar porque tiene ciudades relacionadas.';
            } else {
                $_SESSION['stateUpdateError'] = 'No se pudo eliminar la división administrativa.';
            }
        } catch (Throwable $e) {
            error_log($e->getMessage());
            $_SESSION['stateUpdateError'] = $e->getMessage();
        }
    }

    $redirect = BASE_URL . '/private/admin/state/index.php';
    if ($countryId > 0) $redirect .= '?countryId=' . $countryId;
    header('Location: ' . $redirect);
    exit;
}

$countriesStmt = $pdo->query("SELECT id, name, stateName, isActive + 0 AS isActive, COALESCE(isDeleted, b'0') + 0 AS isDeleted FROM country ORDER BY name");
$countries = $countriesStmt->fetchAll(PDO::FETCH_ASSOC);
$countriesById = [];

foreach ($countries as $countryRow) {
    $countriesById[(int) $countryRow['id']] = $countryRow;
}

$selectedCountry = $countriesById[$countryId] ?? null;
$stateName = trim((string) ($selectedCountry['stateName'] ?? 'División administrativa')) ?: 'División administrativa';
$pageTitle = $selectedCountry ? $stateName . ' de ' . $selectedCountry['name'] : 'Divisiones administrativas';

$sql = "
    SELECT
        s.id,
        s.countryId,
        s.name,
        s.createDate,
        c.name AS countryName,
        c.stateName,
        c.isActive + 0 AS countryIsActive,
        COALESCE(c.isDeleted, b'0') + 0 AS countryIsDeleted,
        COUNT(ci.id) AS cityCount
    FROM state s
    INNER JOIN country c ON c.id = s.countryId
    LEFT JOIN city ci ON ci.stateId = s.id
    WHERE (:countryId = 0 OR s.countryId = :countryId)
      AND (:name = '' OR s.name LIKE :nameFilter)
    GROUP BY
        s.id,
        s.countryId,
        s.name,
        s.createDate,
        c.name,
        c.stateName,
        c.isActive,
        c.isDeleted
    ORDER BY c.name, s.name
";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':countryId', $countryId, PDO::PARAM_INT);
$stmt->bindValue(':name', $name, PDO::PARAM_STR);
$stmt->bindValue(':nameFilter', '%' . $name . '%', PDO::PARAM_STR);
$stmt->execute();
$states = $stmt->fetchAll(PDO::FETCH_ASSOC);

$updateOK = (int) ($_SESSION['stateUpdateOK'] ?? 0);
$updateMessage = (string) ($_SESSION['stateUpdateMessage'] ?? 'La operación se completó correctamente.');
$errorMessage = (string) ($_SESSION['stateUpdateError'] ?? '');
unset($_SESSION['stateUpdateOK'], $_SESSION['stateUpdateMessage'], $_SESSION['stateUpdateError']);

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

<?php if ($errorMessage !== ''): ?>
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1090; margin-top: 65px;">
        <div id="errorToast" class="toast text-bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="false">
            <div class="d-flex">
                <div class="toast-body"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Cerrar"></button>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="page-title mb-0"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
        <?php if ($selectedCountry): ?>
            <div class="text-body-secondary"><?= htmlspecialchars((string) $selectedCountry['name'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
    </div>

    <div class="d-flex gap-2">
        <?php if ($selectedCountry): ?>
            <a href="<?= BASE_URL ?>/private/admin/country/index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Volver a países</a>
        <?php endif; ?>

        <?php if (hasPermission('STATE_CREATE')): ?>
            <a href="<?= BASE_URL ?>/private/admin/state/new.php<?= $countryId > 0 ? '?countryId=' . $countryId : '' ?>" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Nueva <?= htmlspecialchars(mb_strtolower($stateName), ENT_QUOTES, 'UTF-8') ?></a>
        <?php endif; ?>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="GET" action="" class="border rounded p-3 mb-4 bg-body-tertiary">
            <div class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label for="countryId" class="form-label">País</label>
                    <select id="countryId" name="countryId" class="form-select form-select-sm">
                        <option value="">Todos los países</option>
                        <?php foreach ($countries as $country): ?>
                            <option value="<?= (int) $country['id'] ?>" <?= $countryId === (int) $country['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $country['name'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-5">
                    <label for="stateNameFilter" class="form-label">Nombre</label>
                    <input type="text" id="stateNameFilter" name="name" class="form-control form-control-sm" placeholder="Filtrar por nombre" value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="col-md-2 d-flex justify-content-end gap-2">
                    <a href="index.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search me-1"></i>Buscar</button>
                </div>
            </div>
        </form>

        <?php if ($selectedCountry && (!empty($selectedCountry['isDeleted']) || empty($selectedCountry['isActive']))): ?>
            <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i>El país está <?= !empty($selectedCountry['isDeleted']) ? 'eliminado' : 'desactivado' ?>. Sus divisiones y ciudades se conservan, pero no estarán disponibles en nuevas operaciones.</div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead>
                    <tr>
                        <?php if (!$selectedCountry): ?>
                            <th>País</th>
                            <th>Tipo</th>
                        <?php endif; ?>

                        <th><?= htmlspecialchars($stateName, ENT_QUOTES, 'UTF-8') ?></th>
                        <th class="text-center">Ciudades</th>
                        <th>Fecha de creación</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if ($states === []): ?>
                        <tr>
                            <td colspan="<?= $selectedCountry ? 4 : 6 ?>" class="text-center text-body-secondary py-4">No se encontraron divisiones administrativas.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($states as $state): ?>
                        <?php
                        $stateId = (int) $state['id'];
                        $cityCount = (int) $state['cityCount'];
                        $rowStateName = trim((string) ($state['stateName'] ?? 'División administrativa')) ?: 'División administrativa';
                        ?>

                        <tr>
                            <?php if (!$selectedCountry): ?>
                                <td><?= htmlspecialchars((string) $state['countryName'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($rowStateName, ENT_QUOTES, 'UTF-8') ?></td>
                            <?php endif; ?>

                            <td><?= htmlspecialchars((string) $state['name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-center"><span class="badge text-bg-primary"><?= $cityCount ?></span></td>
                            <td><?= htmlspecialchars((string) ($state['createDate'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>

                            <td class="text-end text-nowrap">
                                <?php if (hasPermission('CITY_VIEW')): ?>
                                    <a href="<?= BASE_URL ?>/private/admin/city/index.php?stateId=<?= $stateId ?>" class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="Ver ciudades"><i class="bi bi-buildings me-1"></i><?= $cityCount ?></a>
                                <?php endif; ?>

                                <?php if (hasPermission('STATE_EDIT')): ?>
                                    <a href="<?= BASE_URL ?>/private/admin/state/edit.php?id=<?= $stateId ?>" class="btn btn-sm btn-outline-warning" data-bs-toggle="tooltip" title="Editar <?= htmlspecialchars(mb_strtolower($rowStateName), ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-pencil-square"></i></a>
                                <?php endif; ?>

                                <?php if (hasPermission('STATE_DELETE')): ?>
                                    <form method="POST" class="d-inline">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="countryId" value="<?= (int) $state['countryId'] ?>">
                                        <button type="submit" name="deleteState" value="<?= $stateId ?>" class="btn btn-sm btn-outline-danger" data-bs-toggle="tooltip" title="Eliminar <?= htmlspecialchars(mb_strtolower($rowStateName), ENT_QUOTES, 'UTF-8') ?>" onclick="return confirm('¿Eliminar este registro?');"><i class="bi bi-trash"></i></button>
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    const successToast = document.getElementById('successToast');
    const errorToast = document.getElementById('errorToast');
    if (successToast) new bootstrap.Toast(successToast, { autohide: true, delay: 4000 }).show();
    if (errorToast) new bootstrap.Toast(errorToast, { autohide: false }).show();
});
</script>

<?php include BASE_PATH . '/layouts/footer.php'; ?>
