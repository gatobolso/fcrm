<?php
    declare(strict_types=1);

    $title = 'Editar Cliente';

    require_once __DIR__ . '/../../../config/app.php';
    require_once BASE_PATH . '/includes/auth.php';
    #requirePermission('CLIENT_EDIT');

    require_once BASE_PATH . '/config/database.php';
    require_once BASE_PATH . '/config/entity.php';
    require_once BASE_PATH . '/config/country.php';
    

    $entityId = (int)($_GET['id'] ?? $_POST['entityId'] ?? 0);
    $entity = getClientById($pdo, $entityId);
    $entityContacts = getEntityContacts($pdo, $entityId);
    $countries = getCountries($pdo);
    $countriesDocuments = getCountriesDocuments($pdo);
    $entityTypes = getEntityTypes($pdo);
    $documentTypes = getDocumentTypes($pdo);
    $contactTypes = getContactTypes($pdo);

    if ($entityId <= 0) {
        http_response_code(400);
        exit('Identificador inválido.');
    }

    if (!$entity) {
        die('Cliente no encontrado');
    }


    $error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $documentNumber = preg_replace('/\D/', '', $_POST['documentNumber'] ?? '');
        if ($documentNumber === '') {
            $documentNumber = null;
        }

        $entityData = [
            'entityId' => $entityId,
            'entityTypeId' => (int) $_POST['entityTypeId'],
            'name' => trim($_POST['name'] ?? ''),
            'countryId' => (int) $_POST['countryId'],
            'documentTypeId' => (int) $_POST['documentTypeId'],
            'documentNumber' => $documentNumber ?? '',
            'address' => trim($_POST['address'] ?? '') !== '' ? trim($_POST['address']) : null,
            'comments' => trim($_POST['comments'] ?? '') !== '' ? trim($_POST['comments']) : null
        ];

        #$selectedEntityTypes = array_map('intval', $_POST['entityTypes'] ?? []);
        #$entityContacts = $_POST['contacts'] ?? [];

        if ($entity['name'] === '') {
            $error = 'El nombre es obligatorio.';
        }

        if ($error === '') {
            $result = updateEntity($pdo, $entityData);

            if ($result === '') {
                $_SESSION['entityUpdateOK'] = 1;
                header('Location: index.php');
                exit;
            }

            $error = $result;
        }

        /* Mantener en pantalla los valores introducidos cuando ocurre un error.*/
        $entity = array_merge($entity, $entityData);
    }
?>

<?php if ($error !== ''): ?>
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1090; margin-top: 65px;">
        <div id="errorToast" class="toast text-bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="false">
            <div class="toast-header">
                <i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>
                <strong class="me-auto">Error</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Cerrar"></button>
            </div>
            <div class="toast-body">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
    include '../../../layouts/header.php';
    include '../../../layouts/sidebar.php';
?>

<div class="card shadow-sm">

    <div class="card-header">
        <h3 class="mb-0">Editar Cliente</h3>
    </div>

    <div class="card-body">
        <form method="POST">
            
            <div class="mb-3">
                <label class="form-label">Nombre</label>
                <input type="text" name="name" class="form-control" maxlength="150" value="<?= htmlspecialchars($entity['name']) ?>" required>
            </div>
            <div class="mb-3">
                <label for="entityTypeId" class="form-label">Tipo</label>
                <select id="entityTypeId" name="entityTypeId" class="form-select">
                <option value="" selected disabled hidden>Seleccione</option>
                <?php foreach ($entityTypes as $entityType): ?>
                    <option value="<?= (int) $entityType['id'] ?>" <?= ((int) ($entity['entityTypeId'] ?? 0) === (int) $entityType['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($entityType['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="countryId" class="form-label">País</label>
                <select id="countryId" name="countryId" class="form-select">
                <option value="" selected disabled hidden>Seleccione</option>
                <?php foreach ($countries as $country): ?>
                    <option value="<?= (int) $country['id'] ?>"<?= ((int) ($entity['countryId'] ?? 0) === (int) $country['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($country['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="documentTypeId" class="form-label">Tipo de documento</label>
                    <select id="documentTypeId" name="documentTypeId" class="form-select">
                        <option value="" selected disabled hidden>Seleccione</option>    
                        <?php foreach ($documentTypes as $documentType): ?>
                        <option value="<?= (int) $documentType['id'] ?>"<?= ((int) ($entity['documentTypeId'] ?? 0) === (int) $documentType['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($documentType['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="documentNumber" class="form-label">Documento</label>
                    <input type="text" id="documentNumber" name="documentNumber" class="form-control" inputmode="numeric" value="<?= htmlspecialchars($entity['documentNumber'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <div id="documentFormatHelp" class="form-text"></div>
                </div>
            </div>
            <div class="mb-3">
                <label for="address" class="form-label">Dirección</label>
                <input type="text" id="address" name="address" class="form-control" maxlength="200" value="<?= htmlspecialchars($entity['address'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="mb-3">
                <label for="comments" class="form-label">Comentarios</label>
                <textarea id="comments" name="comments" class="form-control" maxlength="1000" rows="4"><?= htmlspecialchars(trim($entity['comments'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

            <!-- Contactos -->
            <div class="card shadow-sm mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Contactos</strong>
                    <button type="button" id="addContactButton" class="btn btn-sm btn-primary">
                        <i class="bi bi-plus-circle me-1"></i>
                        Agregar contacto
                    </button>
                </div>

                <div class="card-body">
                    <div id="contactsContainer" class="d-flex flex-column gap-3">
                        <?php foreach ($entityContacts as $index => $contact): ?>
                        <div class="contact-row border rounded p-3" data-index="<?= $index ?>">
                            <input type="hidden" name="contacts[<?= $index ?>][id]" value="<?= (int) ($contact['id'] ?? 0) ?>">
                           
                            <div class="row g-3 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label">Tipo</label>
                                    <select name="contacts[<?= $index ?>][contactTypeId]" class="form-select contact-type" required>
                                        <option value="" selected disabled hidden>Seleccione</option>
                                        <?php foreach ($contactTypes as $contactType): ?>
                                        <option value="<?= (int) $contactType['id'] ?>"<?= ((int) ($contact['contactTypeId'] ?? 0) === (int) $contactType['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($contactType['name'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Contacto</label>
                                    <input type="text" name="contacts[<?= $index ?>][contact]" class="form-control" maxlength="100" value="<?= htmlspecialchars($contact['contact'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Comentario</label>
                                    <input type="text" name="contacts[<?= $index ?>][comment]" class="form-control" maxlength="100" value="<?= htmlspecialchars($contact['comment'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                </div>

                                <div class="col-md-2">
                                    <div class="d-flex gap-3 align-items-center">
                                        <div class="form-check">
                                            <label class="form-check-label">Principal</label>
                                            <input type="checkbox" name="contacts[<?= $index ?>][isPrimary]" class="form-check-input primary-contact" value="1" <?= !empty($contact['isPrimary']) ? 'checked' : '' ?>>
                                        </div>
                                        <div class="form-check">
                                            <label class="form-check-label">Activo</label>
                                            <input type="checkbox" name="contacts[<?= $index ?>][isActive]" class="form-check-input" value="1" <?= !isset($contact['isActive']) || !empty($contact['isActive']) ? 'checked' : '' ?>>
                                        </div>

                                        <button type="button" class="btn btn-sm btn-outline-danger remove-contact" title="Eliminar contacto">
                                            <i class="bi bi-trash"></i>
                                        </button>

                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div id="noContactsMessage" class="text-body-secondary text-center py-3 <?= $entityContacts ? 'd-none' : '' ?>">No hay contactos registrados.</div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary"> Guardar </button>
            <a href="index.php" class="btn btn-outline-secondary">
    <i class="bi bi-x-circle me-1"></i>
    Cancelar
</a>
        </form>

        <template id="contactTemplate">
            <div class="contact-row border rounded p-3">
                <input type="hidden" name="contacts[__INDEX__][id]" value="0">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Tipo</label>
                        <select name="contacts[__INDEX__][contactTypeId]" class="form-select contact-type" required>
                            <option value="" selected disabled hidden>Seleccione</option>
                            <?php foreach ($contactTypes as $contactType): ?>
                            <option value="<?= (int) $contactType['id'] ?>">
                                <?= htmlspecialchars($contactType['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Contacto</label>
                        <input type="text" name="contacts[__INDEX__][contact]" class="form-control" maxlength="100" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Comentario</label>
                        <input type="text" name="contacts[__INDEX__][comment]" class="form-control" maxlength="100">
                    </div>

                    <div class="col-md-2">
                        <div class="d-flex align-items-center gap-3">
                            <div class="form-check">
                                <label class="form-check-label">Principal</label>
                                <input type="checkbox" name="contacts[__INDEX__][isPrimary]" class="form-check-input primary-contact" value="1">
                            </div>

                            <div class="form-check">
                                <label class="form-check-label">Activo</label>
                                <input type="checkbox" name="contacts[__INDEX__][isActive]" class="form-check-input" value="1" checked>
                            </div>

                            <button type="button" class="btn btn-sm btn-outline-danger remove-contact" title="Eliminar contacto">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</div>

<?php if ($error !== ''): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toastElement = document.getElementById('errorToast');

            if (!toastElement) {
                return;
            }

            const errorToast = new bootstrap.Toast(
                toastElement,
                {
                    autohide: false
                }
            );

            errorToast.show();
        });
    </script>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const contactsContainer = document.getElementById('contactsContainer');
    const addContactButton = document.getElementById('addContactButton');
    const contactTemplate = document.getElementById('contactTemplate');
    const noContactsMessage = document.getElementById('noContactsMessage');
    let contactIndex = contactsContainer ? contactsContainer.querySelectorAll('.contact-row').length : 0;

    function updateNoContactsMessage() {
        if (!contactsContainer || !noContactsMessage) {
            return;
        }

        const contactRows = contactsContainer.querySelectorAll('.contact-row');

        noContactsMessage.classList.toggle(
            'd-none',
            contactRows.length > 0
        );
    }

    if (addContactButton && contactsContainer && contactTemplate) {
        addContactButton.addEventListener('click', function () {
            const html = contactTemplate.innerHTML.replaceAll(
                '__INDEX__',
                contactIndex
            );

            contactsContainer.insertAdjacentHTML(
                'beforeend',
                html
            );

            contactIndex++;

            updateNoContactsMessage();
        });
    }

    if (contactsContainer) {
        contactsContainer.addEventListener('click', function (event) {
            const removeButton = event.target.closest('.remove-contact');

            if (!removeButton) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            const contactRow = removeButton.closest('.contact-row');

            if (!contactRow) {
                return;
            }

            contactRow.remove();

            updateNoContactsMessage();
        });
    }

    updateNoContactsMessage();
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const countrySelect = document.getElementById('countryId');
    const documentTypeSelect = document.getElementById('documentTypeId');
    const documentInput = document.getElementById('documentNumber');

    const documentFormats = <?= json_encode($countriesDocuments, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    if (!countrySelect || !documentTypeSelect || !documentInput) {
        return;
    }

    let currentFormat = '';

    function getFormat() {
        const countryId = Number(countrySelect.value);
        const documentTypeId = Number(documentTypeSelect.value);

        const configuration = documentFormats.find(function (item) {
            return Number(item.countryId) === countryId
                && Number(item.documentTypeId) === documentTypeId;
        });

        return configuration?.format ?? '';
    }

    function applyFormat(value, format) {
        if (!format) {
            return value.replace(/\D/g, '');
        }

        const maximumDigits = (format.match(/#/g) || []).length;
        const digits = value.replace(/\D/g, '').substring(0, maximumDigits);

        let result = '';
        let digitIndex = 0;

        for (let i = 0; i < format.length; i++) {
            const character = format[i];

            if (character === '#') {
                if (digitIndex >= digits.length) {
                    break;
                }

                result += digits[digitIndex];
                digitIndex++;

                continue;
            }

            if (digitIndex > 0) {
                result += character;
            }
        }

        return result;
    }

    function updateDocumentFormat() {
        currentFormat = getFormat();

        if (!currentFormat) {
            documentInput.placeholder = '';
            documentInput.removeAttribute('maxlength');
            return;
        }

        documentInput.placeholder = currentFormat;
        documentInput.maxLength = currentFormat.length;

        documentInput.value = applyFormat(
            documentInput.value,
            currentFormat
        );
    }

    documentInput.addEventListener('input', function () {
        documentInput.value = applyFormat(
            documentInput.value,
            currentFormat
        );
    });

    documentInput.addEventListener('keydown', function (event) {
    if (
        event.key !== 'Backspace'
        && event.key !== 'Delete'
    ) {
        return;
    }

    if (currentFormat === '') {
        return;
    }

    event.preventDefault();

    const start = documentInput.selectionStart;
    const end = documentInput.selectionEnd;
    const currentValue = documentInput.value;

    /*
     * Si hay texto seleccionado, elimina los números
     * contenidos dentro de la selección.
     */
    if (start !== end) {
        const beforeSelection = currentValue.substring(0, start);
        const selectedValue = currentValue.substring(start, end);
        const afterSelection = currentValue.substring(end);

        const selectedDigits = selectedValue.replace(/\D/g, '');

        if (selectedDigits.length > 0) {
            const newValue = beforeSelection + afterSelection;

            documentInput.value = applyFormat(
                newValue,
                currentFormat
            );

            return;
        }
    }

    const digits = currentValue.replace(/\D/g, '');

    /*
     * Calcula cuántos números hay antes del cursor.
     */
    const digitsBeforeCursor = currentValue
        .substring(0, start)
        .replace(/\D/g, '')
        .length;

    let newDigits = digits;

    if (event.key === 'Backspace') {
        if (digitsBeforeCursor <= 0) {
            return;
        }

        newDigits =
            digits.substring(0, digitsBeforeCursor - 1)
            + digits.substring(digitsBeforeCursor);
    }

    if (event.key === 'Delete') {
        if (digitsBeforeCursor >= digits.length) {
            return;
        }

        newDigits =
            digits.substring(0, digitsBeforeCursor)
            + digits.substring(digitsBeforeCursor + 1);
    }

    documentInput.value = applyFormat(
        newDigits,
        currentFormat
    );

    /*
     * Después del borrado, coloca el cursor
     * en una posición coherente.
     */
    let targetDigitPosition = event.key === 'Backspace'
        ? Math.max(0, digitsBeforeCursor - 1)
        : digitsBeforeCursor;

    let cursorPosition = 0;
    let countedDigits = 0;

    while (
        cursorPosition < documentInput.value.length
        && countedDigits < targetDigitPosition
    ) {
        if (
            /\d/.test(
                documentInput.value[cursorPosition]
            )
        ) {
            countedDigits++;
        }

        cursorPosition++;
    }

    documentInput.setSelectionRange(
        cursorPosition,
        cursorPosition
    );
});

    countrySelect.addEventListener(
        'change',
        updateDocumentFormat
    );

    documentTypeSelect.addEventListener(
        'change',
        updateDocumentFormat
    );

    updateDocumentFormat();
});
</script>

<?php include '../../../layouts/footer.php'; ?>