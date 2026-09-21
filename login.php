<?php
echo $_SERVER['REQUEST_METHOD'];

session_start();

require_once 'config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("
        select pwdHash
        from fcrm.user
        where username = ?
            and activated = 1
            and userTypeId = 1
        limit 1
    ");

    //$stmt->execute([$usuario]);

    //$user = $stmt->fetch(PDO::FETCH_ASSOC);

    //if ($user && password_verify($password, $user['pwdHash'])) {

        session_regenerate_id(true);

        $_SESSION['userId'] = '1';//$user['IdUsuario'];
        $_SESSION['usuario'] = $usuario;

        header('Location: private/dashboard.php');
        exit;
    //}

    $error = 'Usuario o contraseña incorrectos';
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">
</head>
<body class="bg-light">

<div class="container">

    <div class="row vh-100 justify-content-center align-items-center">

        <div class="col-11 col-sm-8 col-md-6 col-lg-4">

            <div class="card shadow-lg">

                <div class="card-body p-4">

                    <h2 class="text-center mb-4">
                        Iniciar sesión
                    </h2>

                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">

                        <div class="mb-3">
                            <label class="form-label">Usuario</label>
                            <input type="text" class="form-control" name="usuario" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Contraseña</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            Entrar
                        </button>

                    </form>

                </div>

            </div>

        </div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>