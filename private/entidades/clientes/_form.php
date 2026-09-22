<?php

    $isEdit = ($formMode ?? '') === 'edit';
    $pageHeading = $isEdit ? 'Editar cliente' : 'Nuevo cliente';
    $submitText = $isEdit ? 'Guardar cambios' : 'Crear cliente';
    $contactStartIndex = count($entityContacts);

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="page-title"><?= htmlspecialchars($pageHeading, ENT_QUOTES, 'UTF-8') ?></h1>
        <div class="text-body-secondary">
            <?= $isEdit
                ? htmlspecialchars($entity['name'] ?? '', ENT_QUOTES, 'UTF-8')
                : 'Complete los datos del nuevo cliente.' ?>
        </div>
    </div>

    <a href="index.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>
        Volver
    </a>
</div>

<?php if ($error !== ''): ?>
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1090; margin-top: 65px;">
        <div id="errorToast" class="toast text-bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="false">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>

                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Cerrar"></button>
            </div>
        </div>
    </div>
<?php endif; ?>

<form method="POST" autocomplete="off">
    <?= csrfField() ?>

    <?php if ($isEdit): ?>
        <input type="hidden" name="entityId" value="<?= (int) $entity['id'] ?>">
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <strong>Datos generales</strong>
        </div>

        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <label for="name" class="form-label">Nombre</label>
                    <input type="text" id="name" name="name" class="form-control" maxlength="150" value="<?= htmlspecialchars($entity['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required autofocus>
                </div>

                <div class="col-md-4">
                    <label for="entityTypeId" class="form-label">Tipo</label>
                    <select id="entityTypeId" name="entityTypeId" class="form-select">
                        <option value="" disabled hidden <?= empty($entity['entityTypeId']) ? 'selected' : '' ?>>Seleccione</option>

                        <?php foreach ($entityTypes as $entityType): ?>
                            <option value="<?= (int) $entityType['id'] ?>" <?= (int) ($entity['entityTypeId'] ?? 0) === (int) $entityType['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($entityType['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="countryId" class="form-label">País</label>
                    <select id="countryId" name="countryId" class="form-select">
                        <option value="" disabled hidden <?= empty($entity['countryId']) ? 'selected' : '' ?>>Seleccione</option>

                        <?php foreach ($countries as $country): ?>
                            <option value="<?= (int) $country['id'] ?>" <?= (int) ($entity['countryId'] ?? 0) === (int) $country['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($country['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="documentTypeId" class="form-label">Tipo de documento</label>
                    <select id="documentTypeId" name="documentTypeId" class="form-select">
                        <option value="" disabled hidden <?= empty($entity['documentTypeId']) ? 'selected' : '' ?>>Seleccione</option>

                        <?php foreach ($documentTypes as $documentType): ?>
                            <option value="<?= (int) $documentType['id'] ?>" <?= (int) ($entity['documentTypeId'] ?? 0) === (int) $documentType['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($documentType['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="documentNumber" class="form-label">Documento</label>
                    <input type="text" id="documentNumber" name="documentNumber" class="form-control" maxlength="20" value="<?= htmlspecialchars($entity['documentNumber'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="col-12">
                    <label for="address" class="form-label">Dirección</label>
                    <input type="text" id="address" name="address" class="form-control" maxlength="200" value="<?= htmlspecialchars($entity['address'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="col-12">
                    <label for="comments" class="form-label">Comentarios</label>
                    <textarea id="comments" name="comments" class="form-control" maxlength="1000" rows="4"><?= htmlspecialchars(trim((string) ($entity['comments'] ?? '')), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <div class="col-md-4">
                    <div class="form-check">
                        <input type="checkbox" id="hasImported" name="hasImported" class="form-check-input" value="1" <?= !empty($entity['hasImported']) ? 'checked' : '' ?>>
                        <label for="hasImported" class="form-check-label">Ha importado</label>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-check">
                        <input type="checkbox" id="isContractSigned" name="isContractSigned" class="form-check-input" value="1" <?= !empty($entity['isContractSigned']) ? 'checked' : '' ?>>
                        <label for="isContractSigned" class="form-check-label">Contrato firmado</label>
                    </div>
                </div>

                <?php if ($isEdit): ?>
                    <div class="col-md-4">
                        <div class="form-check">
                            <input type="checkbox" id="isDeleted" name="isDeleted" class="form-check-input" value="1" <?= !empty($entity['isDeleted']) ? 'checked' : '' ?>>
                            <label for="isDeleted" class="form-check-label">Entidad eliminada</label>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <strong>Clasificación</strong>
        </div>

        <div class="card-body">
            <div class="row g-3">
                <?php foreach ($entityTypes as $entityType): ?>
                    <?php $typeId = (int) $entityType['id']; ?>

                    <div class="col-md-4">
                        <div class="form-check">
                            <input type="checkbox" id="entityType<?= $typeId ?>" name="entityTypes[]" class="form-check-input" value="<?= $typeId ?>" <?= in_array($typeId, $selectedEntityTypes, true) ? 'checked' : '' ?>>
                            <label for="entityType<?= $typeId ?>" class="form-check-label">
                                <?= htmlspecialchars($entityType['name'], ENT_QUOTES, 'UTF-8') ?>
                            </label>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

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
                    <div class="contact-row border rounded p-3">
                        <input type="hidden" name="contacts[<?= $index ?>][id]" value="<?= (int) ($contact['id'] ?? 0) ?>">

                        <div class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label">Tipo</label>
                                <select name="contacts[<?= $index ?>][contactTypeId]" class="form-select contact-type" required>
                                    <option value="" disabled hidden>Seleccione</option>

                                    <?php foreach ($contactTypes as $contactType): ?>
                                        <option value="<?= (int) $contactType['id'] ?>" <?= (int) ($contact['contactTypeId'] ?? 0) === (int) $contactType['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($contactType['name'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Contacto</label>
                                <input
                                    type="text"
                                    name="contacts[<?= $index ?>][contact]"
                                    class="form-control"
                                    maxlength="100"
                                    value="<?= htmlspecialchars($contact['contact'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    required>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Comentario</label>
                                <input
                                    type="text"
                                    name="contacts[<?= $index ?>][comment]"
                                    class="form-control"
                                    maxlength="100"
                                    value="<?= htmlspecialchars($contact['comment'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                >
                            </div>

                            <div class="col-md-2">
                                <div class="d-flex flex-column gap-2">
                                    <div class="form-check">
                                        <input
                                            type="checkbox"
                                            name="contacts[<?= $index ?>][isPrimary]"
                                            class="form-check-input primary-contact"
                                            value="1"
                                            <?= !empty($contact['isPrimary']) ? 'checked' : '' ?>
                                        >
                                        <label class="form-check-label">Principal</label>
                                    </div>

                                    <div class="form-check">
                                        <input
                                            type="checkbox"
                                            name="contacts[<?= $index ?>][isActive]"
                                            class="form-check-input"
                                            value="1"
                                            <?= !isset($contact['isActive']) || !empty($contact['isActive']) ? 'checked' : '' ?>
                                        >
                                        <label class="form-check-label">Activo</label>
                                    </div>

                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-danger remove-contact"
                                        title="Quitar contacto"
                                    >
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div
                id="noContactsMessage"
                class="text-center text-body-secondary py-3 <?= $entityContacts ? 'd-none' : '' ?>"
            >
                No hay contactos registrados.
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mb-4">
        <a href="index.php" class="btn btn-outline-secondary">
            Cancelar
        </a>

        <button type="submit" class="btn btn-primary">
            <i class="bi bi-floppy me-1"></i>
            <?= htmlspecialchars($submitText, ENT_QUOTES, 'UTF-8') ?>
        </button>
    </div>
</form>

<template id="contactTemplate">
    <div class="contact-row border rounded p-3">
        <input type="hidden" name="contacts[__INDEX__][id]" value="0">

        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Tipo</label>
                <select
                    name="contacts[__INDEX__][contactTypeId]"
                    class="form-select contact-type"
                    required
                >
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
                <input
                    type="text"
                    name="contacts[__INDEX__][contact]"
                    class="form-control"
                    maxlength="100"
                    required
                >
            </div>

            <div class="col-md-3">
                <label class="form-label">Comentario</label>
                <input
                    type="text"
                    name="contacts[__INDEX__][comment]"
                    class="form-control"
                    maxlength="100"
                >
            </div>

            <div class="col-md-2">
                <div class="d-flex flex-column gap-2">
                    <div class="form-check">
                        <input
                            type="checkbox"
                            name="contacts[__INDEX__][isPrimary]"
                            class="form-check-input primary-contact"
                            value="1"
                        >
                        <label class="form-check-label">Principal</label>
                    </div>

                    <div class="form-check">
                        <input
                            type="checkbox"
                            name="contacts[__INDEX__][isActive]"
                            class="form-check-input"
                            value="1"
                            checked
                        >
                        <label class="form-check-label">Activo</label>
                    </div>

                    <button
                        type="button"
                        class="btn btn-sm btn-outline-danger remove-contact"
                        title="Quitar contacto"
                    >
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const contactsContainer = document.getElementById('contactsContainer');
    const addContactButton = document.getElementById('addContactButton');
    const contactTemplate = document.getElementById('contactTemplate');
    const noContactsMessage = document.getElementById('noContactsMessage');

    let contactIndex = <?= $contactStartIndex ?>;

    function updateNoContactsMessage() {
        const rowCount = contactsContainer.querySelectorAll('.contact-row').length;
        noContactsMessage.classList.toggle('d-none', rowCount > 0);
    }

    function enforceOnePrimaryPerType(changedCheckbox) {
        if (!changedCheckbox.checked) {
            return;
        }

        const currentRow = changedCheckbox.closest('.contact-row');
        const currentType = currentRow.querySelector('.contact-type').value;

        if (currentType === '') {
            changedCheckbox.checked = false;
            return;
        }

        contactsContainer.querySelectorAll('.contact-row').forEach(function (row) {
            if (row === currentRow) {
                return;
            }

            const typeSelect = row.querySelector('.contact-type');
            const primaryCheckbox = row.querySelector('.primary-contact');

            if (
                typeSelect
                && primaryCheckbox
                && typeSelect.value === currentType
            ) {
                primaryCheckbox.checked = false;
            }
        });
    }

    addContactButton.addEventListener('click', function () {
        const html = contactTemplate.innerHTML.replaceAll(
            '__INDEX__',
            contactIndex
        );

        contactsContainer.insertAdjacentHTML('beforeend', html);
        contactIndex++;
        updateNoContactsMessage();

        const lastRow = contactsContainer.lastElementChild;
        const typeSelect = lastRow.querySelector('.contact-type');

        if (typeSelect) {
            typeSelect.focus();
        }
    });

    contactsContainer.addEventListener('click', function (event) {
        const removeButton = event.target.closest('.remove-contact');

        if (!removeButton) {
            return;
        }

        event.preventDefault();

        const row = removeButton.closest('.contact-row');

        if (row) {
            row.remove();
            updateNoContactsMessage();
        }
    });

    contactsContainer.addEventListener('change', function (event) {
        if (event.target.classList.contains('primary-contact')) {
            enforceOnePrimaryPerType(event.target);
        }

        if (event.target.classList.contains('contact-type')) {
            const row = event.target.closest('.contact-row');
            const primaryCheckbox = row.querySelector('.primary-contact');

            if (primaryCheckbox && primaryCheckbox.checked) {
                enforceOnePrimaryPerType(primaryCheckbox);
            }
        }
    });

    updateNoContactsMessage();

    <?php if ($error !== ''): ?>
        const toastElement = document.getElementById('errorToast');

        if (toastElement) {
            const errorToast = new bootstrap.Toast(toastElement, {
                autohide: false
            });

            errorToast.show();
        }
    <?php endif; ?>
});
</script>
