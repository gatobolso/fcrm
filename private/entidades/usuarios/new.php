<?php
declare(strict_types=1);

require_once '../../../config/app.php';
require_once BASE_PATH . '/includes/auth.php';
requirePermission('USER_CREATE');

require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/config/entity.php';

$isModal = ($_GET['modal'] ?? '') === '1';
$entityId = (int) ($_GET['entityId'] ?? $_POST['entityId'] ?? 0);
$client = $entityId > 0 ? getClientById($pdo, $entityId) : false;

if (!$client) {
    http_response_code($entityId > 0 ? 404 : 400);
    exit($entityId > 0 ? 'Cliente no encontrado.' : 'Identificador de cliente inválido.');
}

$title = 'Nuevo usuario';
$error = '';
$sessionTheme = $_SESSION['theme'] ?? null;
$theme = is_string($sessionTheme) && in_array($sessionTheme, ['light', 'dark'], true)
    ? $sessionTheme
    : 'light';
$form = [
    'username' => '',
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
    } elseif (strlen($form['password']) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres.';
    } elseif ($form['password'] !== $form['passwordConfirmation']) {
        $error = 'Las contraseñas no coinciden.';
    }

    if ($error === '') {
        $result = insertClientUser(
            $pdo,
            $entityId,
            $form['username'],
            $form['password']
        );

        if (is_int($result) && $result > 0) {
            if ($isModal) {
                echo '<script>window.parent.location.reload();</script>';
                exit;
            }

            $_SESSION['entityUpdateOK'] = 1;
            $_SESSION['entityUpdateMessage'] = 'El usuario fue creado correctamente.';
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
        <script>
            const savedTheme = localStorage.getItem('theme');

            if (savedTheme === 'light' || savedTheme === 'dark') {
                document.documentElement.setAttribute('data-bs-theme', savedTheme);
            }
        </script>
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
        <h1 class="page-title mb-0">Nuevo usuario</h1>
        <div class="text-body-secondary">
            Cliente: <?= htmlspecialchars((string) $client['name'], ENT_QUOTES, 'UTF-8') ?>
        </div>
    </div>

    <a href="<?= $isModal ? '#' : BASE_URL . '/private/entidades/clientes/index.php' ?>" class="btn btn-outline-secondary <?= $isModal ? 'd-none' : '' ?>"<?= $isModal ? ' onclick="window.parent.postMessage({type: \'closeUserModal\'}, \'*\'); return false;"' : '' ?>>
        <i class="bi bi-arrow-left me-1"></i>
        Volver
    </a>
</div>

<?php if ($error !== ''): ?>
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1090; margin-top: 65px;">
        <div class="toast show text-bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Cerrar"></button>
            </div>
        </div>
    </div>
<?php endif; ?>

<form method="POST" autocomplete="off">
    <?= csrfField() ?>
    <input type="hidden" name="entityId" value="<?= $entityId ?>">

    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <strong>Datos de acceso</strong>
        </div>

        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="username" class="form-label">Usuario</label>
                    <input type="text" id="username" name="username" class="form-control" maxlength="100" value="<?= htmlspecialchars($form['username'], ENT_QUOTES, 'UTF-8') ?>" required autofocus>
                </div>

                <div class="col-md-6">
                    <label for="password" class="form-label">Contraseña</label>
                    <input type="password" id="password" name="password" class="form-control" minlength="8" required>
                </div>

                <div class="col-md-6">
                    <label for="passwordConfirmation" class="form-label">Repetir contraseña</label>
                    <input type="password" id="passwordConfirmation" name="passwordConfirmation" class="form-control" minlength="8" required>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mb-4">
        <a href="<?= $isModal ? '#' : BASE_URL . '/private/entidades/clientes/index.php' ?>" class="btn btn-outline-secondary <?= $isModal ? '' : '' ?>"<?= $isModal ? ' onclick="window.parent.postMessage({type: \'closeUserModal\'}, \'*\'); return false;"' : '' ?>>Cancelar</a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-person-plus me-1"></i>
            Crear usuario
        </button>
    </div>
</form>

<?php if (!$isModal): ?>
    <?php include BASE_PATH . '/layouts/footer.php'; ?>
<?php else: ?>
    </body>
    </html>
<?php endif; ?>
