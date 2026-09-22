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