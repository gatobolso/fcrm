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

        if (isset($_POST['borrar'])) {
            requirePermission('COUNTRY_DELETE');
            deleteCountry($pdo, (int) $_POST['borrar']);
        } else {
            requirePermission('COUNTRY_EDIT');

            $id = (int) ($_POST['bloquear'] ?? $_POST['desbloquear'] ?? 0);

            if ($id > 0) {
                $activo = isset($_POST['bloquear']) ? 0 : 1;

                $stmt = $pdo->prepare(
                    "UPDATE country
                     SET isActive = :isActive
                     WHERE id = :countryId
                       AND COALESCE(isDeleted, b'0') = b'0'"
                );

                $stmt->execute([
                    ':isActive' => $activo,
                    ':countryId' => $id
                ]);
            }
        }

        header('Location: index.php');
        exit;
    }

    $paises = $isAdmin
        ? getCountries($pdo, false, true)
        : getCountries($pdo, true, false);

    include BASE_PATH . '/layouts/header.php';
    include BASE_PATH . '/layouts/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="page-title mb-0">Países</h1>
    <a href="<?= BASE_URL ?>/private/admin/country/new.php" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i>
        Nuevo país
    </a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <table class="table table-striped table-hover align-middle">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Código ISO2</th>
                    <th>Código ISO3</th>
                    <th>Prefijo</th>
                    <th>Moneda</th>
                    <?php if ($isAdmin): ?>
                        <th>Estado</th>
                    <?php endif; ?>
                    <th style="width:150px;">Acciones</th>
                </tr>
            </thead>

            <tbody>

            <?php foreach ($paises as $pais): ?>
                <tr>
                    <td>
                        <?= htmlspecialchars($pais['name'], ENT_QUOTES, 'UTF-8') ?>
                    </td>
                    <td>
                        <?= htmlspecialchars($pais['iso2Code'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                    </td>
                    <td>
                        <?= htmlspecialchars($pais['iso3Code'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                    </td>
                    <td>
                        <?= htmlspecialchars($pais['phonePrefix'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                    </td>
                    <td>
                        <?= htmlspecialchars((string) ($pais['currencyName'] ?? ''),ENT_QUOTES,'UTF-8') ?>
                    </td>
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
                    <td>
                        <?php if (empty($pais['isDeleted'])): ?>
                            <a class="btn btn-sm btn-outline-warning" href="<?= BASE_URL ?>/private/admin/country/edit.php?id=<?= (int) $pais['id'] ?>" data-bs-toggle="tooltip" title="Editar país">
                                <i class="bi bi-pencil-square"></i>
                            </a>
                        <?php endif; ?>
                        <?php if (empty($pais['isDeleted']) && $pais['isActive']) : ?>
                            <form method="POST" class="d-inline">
                                <?= csrfField() ?>
                                <input  type="hidden" name="bloquear" value="<?= $pais['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" data-bs-toggle="tooltip" title="Desactivar país">
                                    <i class="bi bi-lock"></i>
                                </button>
                            </form>
                        <?php elseif (empty($pais['isDeleted'])) : ?>
                            <form method="POST" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="desbloquear" value="<?= $pais['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-success" data-bs-toggle="tooltip" title="Activar país">
                                    <i class="bi bi-unlock"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                        <?php if (empty($pais['isDeleted'])): ?>
                            <form method="POST" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="borrar" value="<?= (int) $pais['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" data-bs-toggle="tooltip" title="Borrar país" onclick="return confirm('¿Borrar este país?');">
                                    <i class="bi bi-trash"></i>
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

<?php include '../../../layouts/footer.php'; ?>