<?php
declare(strict_types=1);

require_once '../../../config/app.php';
require_once BASE_PATH . '/includes/auth.php';
requirePermission('USER_EDIT');

require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/config/entity.php';

$isModal = ($_GET['modal'] ?? '') === '1';
$userId = (int) ($_GET['id'] ?? $_POST['userId'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT eu.id, eu.entityId, eu.username, e.name AS clientName
     FROM entity_user AS eu
     INNER JOIN entity AS e ON e.id = eu.entityId
     WHERE eu.id = :userId
       AND e.entityTypeId = 2
       AND COALESCE(e.isDeleted, b\'0\') = b\'0\'
       AND COALESCE(eu.isDeleted, b\'0\') = b\'0\'
     LIMIT 1'
);
$stmt->execute([':userId' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code($userId > 0 ? 404 : 400);
    exit($userId > 0 ? 'Usuario no encontrado.' : 'Identificador de usuario inválido.');
}

$title = 'Editar usuario';
$error = '';
$sessionTheme = $_SESSION['theme'] ?? null;
$theme = is_string($sessionTheme) && in_array($sessionTheme, ['light', 'dark'], true)
    ? $sessionTheme
    : 'light';
$form = [
    'username' => (string) $user['username'],
    'password' => '',
    'passwordConfirmation' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken();

    $form = [
        'username' => trim((string) ($_POST['username'] ?? '')),
        'password' => (string) ($_POST['password'] ?? ''),
        'passwordConfirmation' => (string) ($_POST['passwordConfirmation'] ?? '')
    ];

    if (!preg_match('/^[A-Za-z0-9._-]{3,100}$/', $form['username'])) {
        $error = 'El usuario debe tener entre 3 y 100 caracteres y solo puede contener letras, números, punto, guion y guion bajo.';
    } elseif ($form['password'] !== '' && strlen($form['password']) < 8) {
        $error = 'La nueva contraseña debe tener al menos 8 caracteres.';
    } elseif ($form['password'] !== $form['passwordConfirmation']) {
        $error = 'Las contraseñas no coinciden.';
    }

    if ($error === '') {
        $result = updateClientUser(
            $pdo,
            $userId,
            $form['username'],
            $form['password'] !== '' ? $form['password'] : null
        );

        if ($result === true) {
            if ($isModal) {
                echo '<script>window.parent.location.reload();</script>';
                exit;
            }

            $_SESSION['entityUpdateOK'] = 1;
            $_SESSION['entityUpdateMessage'] = 'El usuario fue actualizado correctamente.';
            header('Location: ' . BASE_URL . '/private/entidades/clientes/index.php');
            exit;
        }

        $error = (string) $result;
    }
}

if ($isModal):
?>
    <!doctype html>
    <html lang="es" data-bs-theme="<?= htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
        <style>body { padding: 1rem; background: var(--bs-body-bg); color: var(--bs-body-color); }</style>
    </head>
    <body>
<?php else:
    include BASE_PATH . '/layouts/header.php';
    include BASE_PATH . '/layouts/sidebar.php';
endif;
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="page-title mb-0">Editar usuario</h1>
        <div class="text-body-secondary">
            Cliente: <?= htmlspecialchars((string) $user['clientName'], ENT_QUOTES, 'UTF-8') ?>
        </div>
    </div>

    <a href="<?= $isModal ? '#' : BASE_URL . '/private/entidades/clientes/index.php' ?>" class="btn btn-outline-secondary <?= $isModal ? 'd-none' : '' ?>"<?= $isModal ? ' onclick="window.parent.postMessage({type: \'closeUserModal\'}, \'*\'); return false;"' : '' ?>>
        <i class="bi bi-arrow-left me-1"></i>
        Volver
    </a>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger" role="alert">
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<form method="POST" autocomplete="off">
    <?= csrfField() ?>
    <input type="hidden" name="userId" value="<?= $userId ?>">

    <div class="card shadow-sm mb-4">
        <div class="card-header"><strong>Datos de acceso</strong></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="username" class="form-label">Usuario</label>
                    <input type="text" id="username" name="username" class="form-control" maxlength="100" value="<?= htmlspecialchars($form['username'], ENT_QUOTES, 'UTF-8') ?>" required autofocus>
                </div>
                <div class="col-md-6">
                    <label for="password" class="form-label">Nueva contraseña</label>
                    <input type="password" id="password" name="password" class="form-control" minlength="8">
                    <div class="form-text">Déjala vacía para conservar la contraseña actual.</div>
                </div>
                <div class="col-md-6">
                    <label for="passwordConfirmation" class="form-label">Repetir contraseña</label>
                    <input type="password" id="passwordConfirmation" name="passwordConfirmation" class="form-control" minlength="8">
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mb-4">
        <a href="<?= $isModal ? '#' : BASE_URL . '/private/entidades/clientes/index.php' ?>" class="btn btn-outline-secondary <?= $isModal ? 'd-none' : '' ?>"<?= $isModal ? ' onclick="window.parent.postMessage({type: \'closeUserModal\'}, \'*\'); return false;"' : '' ?>>Cancelar</a>
        <button type="submit" class="btn btn-primary"><i class="bi bi-floppy me-1"></i>Guardar cambios</button>
    </div>
</form>

<?php if (!$isModal): ?>
    <?php include BASE_PATH . '/layouts/footer.php'; ?>
<?php else: ?>
    </body>
    </html>
<?php endif; ?>
