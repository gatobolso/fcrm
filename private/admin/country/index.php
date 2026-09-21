<?php
    declare(strict_types=1);

    $title = 'Países';

    require_once __DIR__ . '/../../../config/app.php';
    require_once BASE_PATH . '/includes/auth.php';
    #requirePermission('COUNTRY_VIEW');

    require_once BASE_PATH . '/config/database.php';
    require_once BASE_PATH . '/config/country.php';

    $paises = getCountries($pdo);


    if ($_SERVER['REQUEST_METHOD'] === 'POST')
    {
        $id = (int)($_POST['bloquear'] ?? $_POST['desbloquear'] ?? 0);

        if ($id > 0)
        {
            $activo = isset($_POST['bloquear']) ? 0 : 1;

            $stmt = $pdo->prepare("
                UPDATE country
                SET isActive = ?
                WHERE id = ?
            ");

            $stmt->bindValue(1, $activo, PDO::PARAM_INT);
            $stmt->bindValue(2, $id, PDO::PARAM_INT);
            $stmt->execute();

            header('Location: index.php');
            exit;
        }
    }

    include BASE_PATH . '/layouts/header.php';
    include BASE_PATH . '/layouts/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="page-title mb-0">Países</h1>
    <a href="new.php" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i>
        Nuevo País
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
                    <th style="width:150px;">Acciones</th>
                </tr>
            </thead>

            <tbody>

            <?php foreach ($paises as $pais): ?>
                <tr>
                    <td>
                        <?= htmlspecialchars($pais['name']) ?>
                    </td>
                    <td>
                        <?= htmlspecialchars($pais['iso2Code'] ?? '') ?>
                    </td>
                    <td>
                        <?= htmlspecialchars($pais['iso3Code'] ?? '') ?>
                    </td>
                    <td>
                        <?= htmlspecialchars($pais['phonePrefix'] ?? '') ?>
                    </td>
                    <td>
                        <?= htmlspecialchars((string) ($pais['currencyName'] ?? ''),ENT_QUOTES,'UTF-8') ?>
                    </td>
                    <td>
                        <a class="btn btn-sm" href="edit.php?id=<?= $pais['id'] ?>">
                            <i class="bi bi-pencil-square" data-bs-toggle="tooltip" title="Editar"></i>
                        </a>
                        <?php if ($pais['isActive']) : ?>
                            <form method="POST" style="display:inline;">
                                <input  type="hidden" name="bloquear" value="<?= $pais['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger" data-bs-toggle="tooltip" title="Desactivar">
                                    <i class="bi bi-lock"></i>
                                </button>
                            </form>
                        <?php else : ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="desbloquear" value="<?= $pais['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-success" data-bs-toggle="tooltip" title="Activar">
                                    <i class="bi bi-unlock"></i>
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