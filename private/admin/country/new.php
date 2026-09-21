<?php

declare(strict_types=1);

require_once '../../../config/app.php';
require_once '../../../includes/auth.php';

#requirePermission('COUNTRY_CREATE');

require_once '../../../config/database.php';

$title = 'Nuevo país';
$error = '';

$currencies = getActiveCurrencies($pdo);
$documentTypes = getDocumentTypes($pdo);

$form = [
    'name' => '',
    'iso2Code' => '',
    'iso3Code' => '',
    'currencyId' => '',
    'phonePrefix' => '',
    'mobilePhoneFormat' => '',
    'fixedPhoneFormat' => '',
    'phoneDigitsToRemove' => '0',
    'isActive' => 1
];

$documentValues = [];

foreach ($documentTypes as $documentType) {
    $documentTypeId = (int) $documentType['id'];

    $documentValues[$documentTypeId] = [
        'name' => '',
        'format' => ''
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = [
        'name' => trim($_POST['name'] ?? ''),
        'iso2Code' => strtoupper(
            trim($_POST['iso2Code'] ?? '')
        ),
        'iso3Code' => strtoupper(
            trim($_POST['iso3Code'] ?? '')
        ),
        'currencyId' => $_POST['currencyId'] ?? '',
        'phonePrefix' => trim(
            $_POST['phonePrefix'] ?? ''
        ),
        'mobilePhoneFormat' => trim(
            $_POST['mobilePhoneFormat'] ?? ''
        ),
        'fixedPhoneFormat' => trim(
            $_POST['fixedPhoneFormat'] ?? ''
        ),
        'phoneDigitsToRemove' => trim(
            $_POST['phoneDigitsToRemove'] ?? '0'
        ),
        'isActive' => isset($_POST['isActive']) ? 1 : 0
    ];

    $postedDocuments = $_POST['documents'] ?? [];

    foreach ($documentTypes as $documentType) {
        $documentTypeId = (int) $documentType['id'];

        $documentValues[$documentTypeId] = [
            'name' => trim(
                $postedDocuments[$documentTypeId]['name'] ?? ''
            ),
            'format' => trim(
                $postedDocuments[$documentTypeId]['format'] ?? ''
            )
        ];
    }

    if ($form['name'] === '') {
        $error = 'El nombre del país es obligatorio.';
    } elseif (
        strlen($form['iso2Code']) !== 2
    ) {
        $error = 'El código ISO2 debe tener exactamente 2 caracteres.';
    } elseif (
        $form['iso3Code'] !== ''
        && strlen($form['iso3Code']) !== 3
    ) {
        $error = 'El código ISO3 debe tener exactamente 3 caracteres.';
    } elseif (
        !ctype_digit($form['phoneDigitsToRemove'])
    ) {
        $error = 'Los dígitos a eliminar deben ser un número entero.';
    } elseif (
        countryExistsByIso2(
            $pdo,
            $form['iso2Code']
        )
    ) {
        $error = 'Ya existe un país con ese código ISO2.';
    } elseif (
        countryExistsByName(
            $pdo,
            $form['name']
        )
    ) {
        $error = 'Ya existe un país con ese nombre.';
    }

    if ($error === '') {
        try {
            $documents = [];

            foreach (
                $documentValues as $documentTypeId => $document
            ) {
                $documents[] = [
                    'documentTypeId' => $documentTypeId,
                    'name' => $document['name'],
                    'format' => $document['format']
                ];
            }

            $countryId = insertCountry(
                $pdo,
                [
                    'name' => $form['name'],
                    'iso2Code' => $form['iso2Code'],
                    'iso3Code' => $form['iso3Code'] !== ''
                        ? $form['iso3Code']
                        : null,
                    'currencyId' => $form['currencyId'] !== ''
                        ? (int) $form['currencyId']
                        : null,
                    'phonePrefix' => $form['phonePrefix'] !== ''
                        ? $form['phonePrefix']
                        : null,
                    'mobilePhoneFormat' =>
                        $form['mobilePhoneFormat'] !== ''
                            ? $form['mobilePhoneFormat']
                            : null,
                    'fixedPhoneFormat' =>
                        $form['fixedPhoneFormat'] !== ''
                            ? $form['fixedPhoneFormat']
                            : null,
                    'phoneDigitsToRemove' =>
                        $form['phoneDigitsToRemove'] !== ''
                            ? (int) $form['phoneDigitsToRemove']
                            : null,
                    'isActive' => $form['isActive']
                ],
                $documents
            );

            header(
                'Location: '
                . BASE_URL
                . '/private/administracion/paises/index.php'
                . '?created=1&id='
                . $countryId
            );

            exit;
        } catch (PDOException $e) {
            error_log($e->getMessage());

            $error = 'No se pudo guardar el país.';
        }
    }
}

include BASE_PATH . '/layouts/header.php';
include BASE_PATH . '/layouts/sidebar.php';

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="page-title">
            Nuevo país
        </h1>

        <div class="text-body-secondary">
            Datos generales, moneda, formatos telefónicos y documentos.
        </div>
    </div>

    <?= BASE_URL ?>/private/administracion/paises/index.php
        <i class="bi bi-arrow-left me-1"></i>
        Volver
    </a>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger" role="alert">
        <?= htmlspecialchars(
            $error,
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </div>
<?php endif; ?>

<form method="POST" autocomplete="off">

    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <strong>Datos generales</strong>
        </div>

        <div class="card-body">
            <div class="row g-3">

                <div class="col-md-6">
                    <label
                        for="name"
                        class="form-label"
                    >
                        Nombre
                    </label>

                    <input
                        type="text"
                        id="name"
                        name="name"
                        class="form-control"
                        maxlength="50"
                        value="<?= htmlspecialchars(
                            $form['name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        required
                        autofocus
                    >
                </div>

                <div class="col-md-3">
                    <label
                        for="iso2Code"
                        class="form-label"
                    >
                        Código ISO2
                    </label>

                    <input
                        type="text"
                        id="iso2Code"
                        name="iso2Code"
                        class="form-control text-uppercase"
                        minlength="2"
                        maxlength="2"
                        placeholder="UY"
                        value="<?= htmlspecialchars(
                            $form['iso2Code'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        required
                    >
                </div>

                <div class="col-md-3">
                    <label
                        for="iso3Code"
                        class="form-label"
                    >
                        Código ISO3
                    </label>

                    <input
                        type="text"
                        id="iso3Code"
                        name="iso3Code"
                        class="form-control text-uppercase"
                        minlength="3"
                        maxlength="3"
                        placeholder="URY"
                        value="<?= htmlspecialchars(
                            $form['iso3Code'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >
                </div>

                <div class="col-md-6">
                    <label
                        for="currencyId"
                        class="form-label"
                    >
                        Moneda
                    </label>

                    <select
                        id="currencyId"
                        name="currencyId"
                        class="form-select"
                    >
                        <option value="" selected disabled hidden>
                            Seleccione una moneda
                        </option>

                        <?php foreach ($currencies as $currency): ?>
                            <option
                                value="<?= (int) $currency['id'] ?>"
                                <?= (
                                    (string) $form['currencyId']
                                    ===
                                    (string) $currency['id']
                                ) ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars(
                                    $currency['code']
                                    . ' - '
                                    . $currency['name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input
                            type="checkbox"
                            id="isActive"
                            name="isActive"
                            class="form-check-input"
                            value="1"
                            <?= $form['isActive'] ? 'checked' : '' ?>
                        >

                        <label
                            for="isActive"
                            class="form-check-label"
                        >
                            País activo
                        </label>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <strong>Configuración telefónica</strong>
        </div>

        <div class="card-body">
            <div class="row g-3">

                <div class="col-md-3">
                    <label
                        for="phonePrefix"
                        class="form-label"
                    >
                        Prefijo internacional
                    </label>

                    <input
                        type="text"
                        id="phonePrefix"
                        name="phonePrefix"
                        class="form-control"
                        maxlength="10"
                        placeholder="+598"
                        value="<?= htmlspecialchars(
                            $form['phonePrefix'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >
                </div>

                <div class="col-md-3">
                    <label
                        for="phoneDigitsToRemove"
                        class="form-label"
                    >
                        Dígitos iniciales a eliminar
                    </label>

                    <input
                        type="number"
                        id="phoneDigitsToRemove"
                        name="phoneDigitsToRemove"
                        class="form-control"
                        min="0"
                        max="99"
                        value="<?= htmlspecialchars(
                            $form['phoneDigitsToRemove'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >
                </div>

                <div class="col-md-3">
                    <label
                        for="mobilePhoneFormat"
                        class="form-label"
                    >
                        Formato móvil
                    </label>

                    <input
                        type="text"
                        id="mobilePhoneFormat"
                        name="mobilePhoneFormat"
                        class="form-control"
                        maxlength="20"
                        placeholder="+598 ## ### ###"
                        value="<?= htmlspecialchars(
                            $form['mobilePhoneFormat'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >
                </div>

                <div class="col-md-3">
                    <label
                        for="fixedPhoneFormat"
                        class="form-label"
                    >
                        Formato fijo
                    </label>

                    <input
                        type="text"
                        id="fixedPhoneFormat"
                        name="fixedPhoneFormat"
                        class="form-control"
                        maxlength="20"
                        placeholder="+598 # ### ####"
                        value="<?= htmlspecialchars(
                            $form['fixedPhoneFormat'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >
                </div>

            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <strong>Documentos del país</strong>
        </div>

        <div class="card-body">

            <?php if (!$documentTypes): ?>
                <div class="alert alert-warning mb-0">
                    No existen tipos de documento configurados.
                </div>
            <?php else: ?>

                <div class="row g-3">

                    <?php foreach ($documentTypes as $documentType): ?>
                        <?php
                        $documentTypeId = (int) $documentType['id'];
                        $documentValue =
                            $documentValues[$documentTypeId];
                        ?>

                        <div class="col-12">
                            <div class="border rounded p-3">

                                <div class="fw-semibold mb-3">
                                    <?= htmlspecialchars(
                                        $documentType['name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </div>

                                <div class="row g-3">

                                    <div class="col-md-6">
                                        <label
                                            for="documentName<?= $documentTypeId ?>"
                                            class="form-label"
                                        >
                                            Nombre en el país
                                        </label>

                                        <input
                                            type="text"
                                            id="documentName<?= $documentTypeId ?>"
                                            name="documents[<?= $documentTypeId ?>][name]"
                                            class="form-control"
                                            maxlength="50"
                                            placeholder="Ejemplo: Cédula de identidad"
                                            value="<?= htmlspecialchars(
                                                $documentValue['name'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                        >
                                    </div>

                                    <div class="col-md-6">
                                        <label
                                            for="documentFormat<?= $documentTypeId ?>"
                                            class="form-label"
                                        >
                                            Formato
                                        </label>

                                        <input
                                            type="text"
                                            id="documentFormat<?= $documentTypeId ?>"
                                            name="documents[<?= $documentTypeId ?>][format]"
                                            class="form-control"
                                            maxlength="20"
                                            placeholder="#.###.###-#"
                                            value="<?= htmlspecialchars(
                                                $documentValue['format'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                        >
                                    </div>

                                </div>
                            </div>
                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mb-4">

        private/administracion/paises/index.php"
            class="btn btn-outline-secondary"
        >
            Cancelar
        </a>

        <button
            type="submit"
            class="btn btn-primary"
        >
            <i class="bi bi-floppy me-1"></i>
            Guardar país
        </button>

    </div>

</form>

<?php

include BASE_PATH . '/layouts/footer.php';

?>