<?php
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    function requireAuth(): void
    {
        if (empty($_SESSION['userId'])) {
            header('Location: ' . BASE_URL . '/login.php');
            exit;
        }
    }

    function hasPermission(string $permission): bool
    {
        return !empty(
            $_SESSION['permissions'][$permission]
        );
    }

    function isAdministrator(PDO $pdo): bool
    {
        if (empty($_SESSION['userId'])) {
            return false;
        }

        $stmt = $pdo->prepare(
            'SELECT EXISTS (
                SELECT 1
                FROM entity_user__role AS eur
                INNER JOIN role AS r ON r.id = eur.roleId
                WHERE eur.userId = :userId
                  AND r.code = :roleCode
            )'
        );
        $stmt->execute([
            ':userId' => (int) $_SESSION['userId'],
            ':roleCode' => 'ADMIN'
        ]);

        return (bool) $stmt->fetchColumn();
    }

    function requirePermission(string $permission): void
    {
        requireAuth();

        if (!hasPermission($permission)) {
            http_response_code(403);
            exit('Acceso denegado');
        }
    }

    function csrfToken(): string
    {
        if (empty($_SESSION['csrfToken'])) {
            $_SESSION['csrfToken'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrfToken'];
    }

    function csrfField(): string
    {
        return '<input type="hidden" name="csrfToken" value="'
            . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8')
            . '">';
    }

    function requireValidCsrfToken(): void
    {
        $token = $_POST['csrfToken'] ?? '';

        if (!is_string($token) || !hash_equals(csrfToken(), $token)) {
            http_response_code(419);
            exit('Solicitud no válida.');
        }
    }

    /**
     * Verifica que el usuario siga teniendo acceso al sistema
     * Debe llamarse en cada página protegida
     */
    function verifyUserStatus(PDO $pdo): void {
        // Si no hay sesión, no hay nada que verificar (el sistema ya debería redirigir al login)
        if (!isset($_SESSION['userId'])) {
            return;
        }

        $userId = $_SESSION['userId'];

        // Consulta rápida solo para verificar el estado
        $stmt = $pdo->prepare("SELECT isBlocked, isDeleted FROM fcrm.entity_user WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Si el usuario ya no existe, está bloqueado o eliminado
        if (!$user || (int)$user['isBlocked'] === 1 || (int)$user['isDeleted'] === 1) {
            
            // 1. Limpiamos la sesión completamente
            $_SESSION = []; 
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            session_destroy();

            // 2. Redireccionamos al login con un mensaje de error
            // Usamos un parámetro en la URL para mostrar el mensaje en login.php
            header('Location: ' . BASE_URL . '/login.php?error=blocked');
            exit;
        }
    }
