<?php
    declare(strict_types=1);

    $title = 'Página pricipal';

    require_once __DIR__ . '../../../config/app.php';
    require_once BASE_PATH . '/includes/auth.php';
    requirePermission('DASHBOARD_VIEW');

    #require_once '../config/database.php';

    include BASE_PATH . '/layouts/header.php';
    include BASE_PATH . '/layouts/sidebar.php';
?>

    <p>
        <strong>Bienvenido <?= htmlspecialchars($_SESSION['entityName'] ?? '') ?></strong>
    </p>

<?php include BASE_PATH . '/layouts/footer.php'; ?>