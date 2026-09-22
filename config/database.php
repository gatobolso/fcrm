<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Configuración de MariaDB
|--------------------------------------------------------------------------
*/

$host = "127.0.0.1";
$dbname = "fcrm";
$user = "fcrm_user";
$password = "6oiK-JsO2@9!!]uD";

/*
|--------------------------------------------------------------------------
| Conexión PDO
|--------------------------------------------------------------------------
*/

try {
    $pdo = new PDO(
        "mysql:host=$host;port=3307;dbname=$dbname;charset=utf8mb4",
        $user,
        $password
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

function loadUserPermissions(PDO $pdo, int $entityUserId): array {
    $sql = "
        SELECT DISTINCT p.code
        FROM entity_user__role AS ur
        INNER JOIN role__permission AS rp ON rp.roleId = ur.roleId
        INNER JOIN permission AS p ON p.id = rp.permissionId
        WHERE ur.userId = :userId
            AND p.isActive = b'1'
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->bindValue(':userId', $entityUserId, PDO::PARAM_INT);

    $stmt->execute();

    $permissions = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $permissions[$row['code']] = true;
    }

    return $permissions;
}