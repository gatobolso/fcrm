<?php

    require_once '../../../includes/init.php'; 

    $entityId = $_GET['id'] ?? 0;

    $stmt = $pdo->prepare("
        SELECT ec.contact, ct.name as type_name, ec.isPrimary 
        FROM fcrm.entity_contact ec 
        JOIN fcrm.contact_type ct ON ec.contactTypeId = ct.id 
        WHERE ec.entityId = ? 
        ORDER BY ec.isPrimary DESC
    ");
    $stmt->execute([$entityId]);
    $contacts = $stmt->fetchAll();

    if (!$contacts) {
        echo "<p class='text-center'>No hay contactos registrados.</p>";
        exit;
    }

    echo '<ul class="list-group">';
    foreach ($contacts as $c) {
        $badge = $c['isPrimary'] ? '<span class="badge bg-success">Principal</span>' : '';
        echo "<li class='list-group-item d-flex justify-content-between align-items-center'>
                <div><strong>{$c['type_name']}:</strong> {$c['contact']}</div>
                $badge
            </li>";
    }
    echo '</ul>';
