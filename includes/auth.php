<?php
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    #if (!isset($_SESSION["usuario"])) {
    #    header("Location: login.php");
    #    exit;
    #}

    function hasPermission(string $permission): bool
    {
        return !empty(
            $_SESSION['permissions'][$permission]
        );
    }

    function requirePermission(string $permission): void
    {
        if (!hasPermission($permission))
        {
            http_response_code(403);
            die('Acceso denegado');
        }
    }