<?php
    declare(strict_types=1);

    require_once '../../../includes/auth.php';
    #requirePermission('CLIENT_CREATE');

    require_once '../../../config/database.php';
    require_once '../../../config/entity.php';
    require_once '../../../config/country.php';


	$title = 'Nuevo cliente';
	$formMode = 'create';
	$error = '';

    $countries = getCountries($pdo);
    $documentTypes = getDocumentTypes($pdo);
    $entityTypes = getEntityTypes($pdo);
    $contactTypes = getContactTypes($pdo);

    $entity = [
        'id' => 0,
        'name' => '',
        'entityTypeId' => null,
        'countryId' => null,
        'documentTypeId' => null,
        'documentNumber' => '',
        'address' => '',
        'hasImported' => 0,
        'comments' => '',
        'isContractSigned' => 0,
        'contractFile' => '',
        'isDeleted' => 0
    ];

    $selectedEntityTypes = [];
    $entityContacts = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $entity = [
            'name' => trim($_POST['name'] ?? ''),
            'entityTypeId' => !empty($_POST['entityTypeId']) ? (int) $_POST['entityTypeId'] : null,
            'countryId' => !empty($_POST['countryId']) ? (int) $_POST['countryId'] : null,
            'documentTypeId' => !empty($_POST['documentTypeId']) ? (int) $_POST['documentTypeId'] : null,
            'documentNumber' => trim($_POST['documentNumber'] ?? '') ?: null,
            'address' => trim($_POST['address'] ?? '') ?: null,
            'hasImported' => isset($_POST['hasImported']) ? 1 : 0,
            'comments' => trim($_POST['comments'] ?? '') ?: null,
            'isContractSigned' => isset($_POST['isContractSigned']) ? 1 : 0,
            'contractFile' => trim($_POST['contractFile'] ?? '') ?: null,
            'isDeleted' => 0
        ];

        $selectedEntityTypes = array_map(
            'intval',
            $_POST['entityTypes'] ?? []
        );

        $entityContacts = $_POST['contacts'] ?? [];

        if ($entity['name'] === '') {
            $error = 'El nombre es obligatorio.';
        }

        if ($error === '') {
            $result = insertEntity(
                $pdo,
                $entity,
                $selectedEntityTypes,
                $entityContacts
            );

            if (is_int($result) && $result > 0) {
                $_SESSION['successMessage'] =
                    'El cliente se creó correctamente.';

                header('Location: index.php'
                );

                exit;
            }

            $error = (string) $result;
        }
    }

    include '../../../layouts/header.php';
    include '../../../layouts/sidebar.php';
    include '_form.php';
    include '../../../layouts/footer.php';