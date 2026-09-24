<?php
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/auth.php';
require_once 'config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';

    // 1. MODIFICACIÓN: JOIN con la tabla system
    // Traemos u.systemId para la sesión y s.baseUrl para la redirección
    $stmt = $pdo->prepare("
        SELECT 
            u.id, u.username, u.pwdHash, u.isConfirmed, u.isBlocked, u.isDeleted, u.systemId,
            e.name as entityName,
            s.name as systemName, s.baseUrl as systemBaseUrl
        FROM fcrm.entity_user u
        JOIN fcrm.entity e ON e.id = u.entityId
        JOIN fcrm.system s ON s.id = u.systemId
        WHERE u.username = ?
        LIMIT 1
    ");

    $stmt->execute([$usuario]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verificamos existencia y contraseña
    if ($user && password_verify($password, (string) $user['pwdHash'])) {
        
        // Validaciones de estado
        if ((int)$user['isDeleted'] === 1) {
            $error = 'Usuario o contraseña incorrectos';
        } 
        elseif ((int)$user['isBlocked'] === 1) {
            $error = 'Tu cuenta se encuentra bloqueada. Por favor, comunícate con el administrador del sistema.';
        } 
        elseif ((int)$user['isConfirmed'] === 0) {
            $error = 'Tu cuenta aún no ha sido confirmada. Revisa tu correo electrónico.';
        } 
        else {
            // TODO CORRECTO: Iniciamos sesión
            session_regenerate_id(true);

            $_SESSION['userId'] = (int) $user['id'];
            $_SESSION['userName'] = (string) $user['username'];
            $_SESSION['entityName'] = (string) $user['entityName'];
            $_SESSION['systemId'] = (int) $user['systemId'];
            $_SESSION['systemName'] = (string) $user['systemName']; // Guardamos el nombre del sistema (ej: "CRM")
            $_SESSION['permissions'] = loadUserPermissions($pdo, (int) $user['id']);
            csrfToken();

            // 2. REDIRECCIÓN DINÁMICA
            // Verificamos si la baseUrl ya incluye el dominio o es una ruta relativa
            $targetUrl = $user['systemBaseUrl'];

            if (strpos($targetUrl, 'http') !== 0) {
                // Si no empieza por http, le concatenamos la BASE_URL definida en tu config
                $targetUrl = BASE_URL . $targetUrl;
            }

            header('Location: ' . $targetUrl);
            exit;
        }

    } else {
        $error = 'Usuario o contraseña incorrectos';
    }
}
?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container">
    <div class="row vh-100 justify-content-center align-items-center">
        <div class="col-11 col-sm-8 col-md-6 col-lg-4">
            <div class="card shadow-lg">
                <div class="card-body p-4">
                    <h2 class="text-center mb-4">Iniciar sesión</h2>

                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="loginForm">
                        <!-- CAMPO CSRF: Fundamental para la seguridad -->
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">

                        <div class="mb-3">
                            <label class="form-label">Usuario</label>
                            <input type="text" class="form-control" name="usuario" required autofocus>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Contraseña</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>

                        <button type="submit" class="btn btn-primary w-100" id="btnSubmit">
                            Entrar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Pequeño script para mejorar la UX del botón
    document.getElementById('loginForm').onsubmit = function() {
        const btn = document.getElementById('btnSubmit');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Verificando...';
    };
</script>

</body>
</html>
