<?php
declare(strict_types=1);

require_once '../../../config/app.php';
require_once '../../../includes/auth.php';
requirePermission('COUNTRY_EDIT');
require_once '../../../config/database.php';
require_once '../../../config/country.php';

$title = 'Editar país';
$countryId = (int) ($_GET['id'] ?? $_POST['countryId'] ?? 0);
$country = $countryId > 0 ? getCountryById($pdo, $countryId) : false;

if (!$country) {
	http_response_code($countryId > 0 ? 404 : 400);
	exit($countryId > 0 ? 'País no encontrado.' : 'Identificador inválido.');
}

$currencies = getActiveCurrencies($pdo);
$documentTypes = getDocumentTypes($pdo);
$documents = getCountryDocuments($pdo, $countryId);
$error = '';

$form = [
	'name' => (string) $country['name'],
	'iso2Code' => (string) $country['iso2Code'],
	'iso3Code' => (string) ($country['iso3Code'] ?? ''),
	'currencyId' => (string) ($country['currencyId'] ?? ''),
	'phonePrefix' => (string) ($country['phonePrefix'] ?? ''),
	'mobilePhoneFormat' => (string) ($country['mobilePhoneFormat'] ?? ''),
	'fixedPhoneFormat' => (string) ($country['fixedPhoneFormat'] ?? ''),
	'phoneDigitsToRemove' => (string) ($country['phoneDigitsToRemove'] ?? 0),
	'isActive' => (int) ($country['isActive'] ?? 0)
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	requireValidCsrfToken();

	foreach (array_keys($form) as $field) {
		if ($field === 'isActive') {
			continue;
		}

		$form[$field] = trim((string) ($_POST[$field] ?? ''));
	}

	$form['iso2Code'] = strtoupper($form['iso2Code']);
	$form['iso3Code'] = strtoupper($form['iso3Code']);
	$form['isActive'] = isset($_POST['isActive']) ? 1 : 0;

	$postedDocuments = is_array($_POST['documents'] ?? null) ? $_POST['documents'] : [];
	$documents = [];

	foreach ($documentTypes as $documentType) {
		$documentTypeId = (int) $documentType['id'];
		$document = is_array($postedDocuments[$documentTypeId] ?? null) ? $postedDocuments[$documentTypeId] : [];
		$documents[$documentTypeId] = [
			'name' => trim((string) ($document['name'] ?? '')),
			'format' => trim((string) ($document['format'] ?? ''))
		];
	}

	if ($form['name'] === '') {
		$error = 'El nombre del país es obligatorio.';
	} elseif (!preg_match('/^[A-Z]{2}$/', $form['iso2Code'])) {
		$error = 'El código ISO2 debe contener exactamente 2 letras.';
	} elseif ($form['iso3Code'] !== '' && !preg_match('/^[A-Z]{3}$/', $form['iso3Code'])) {
		$error = 'El código ISO3 debe contener exactamente 3 letras.';
	} elseif (!ctype_digit($form['phoneDigitsToRemove'])) {
		$error = 'Los dígitos a eliminar deben ser un número entero.';
	} elseif (countryExistsByIso2Except($pdo, $form['iso2Code'], $countryId)) {
		$error = 'Ya existe otro país con ese código ISO2.';
	} elseif (countryExistsByNameExcept($pdo, $form['name'], $countryId)) {
		$error = 'Ya existe otro país con ese nombre.';
	}

	if ($error === '') {
		try {
			updateCountry($pdo, $countryId, [
				'name' => $form['name'],
				'iso2Code' => $form['iso2Code'],
				'iso3Code' => $form['iso3Code'] !== '' ? $form['iso3Code'] : null,
				'currencyId' => $form['currencyId'] !== '' ? (int) $form['currencyId'] : null,
				'phonePrefix' => $form['phonePrefix'] !== '' ? $form['phonePrefix'] : null,
				'phoneDigitsToRemove' => (int) $form['phoneDigitsToRemove'],
				'mobilePhoneFormat' => $form['mobilePhoneFormat'] !== '' ? $form['mobilePhoneFormat'] : null,
				'fixedPhoneFormat' => $form['fixedPhoneFormat'] !== '' ? $form['fixedPhoneFormat'] : null,
				'isActive' => $form['isActive']
			], $documents);

			header('Location: ' . BASE_URL . '/private/admin/country/index.php');
			exit;
		} catch (Throwable $exception) {
			error_log($exception->getMessage());
			$error = 'No se pudo actualizar el país.';
		}
	}
}

include BASE_PATH . '/layouts/header.php';
include BASE_PATH . '/layouts/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
	<div>
		<h1 class="page-title mb-0">Editar país</h1>
		<div class="text-body-secondary"><?= htmlspecialchars($form['name'], ENT_QUOTES, 'UTF-8') ?></div>
	</div>
	<a href="<?= BASE_URL ?>/private/admin/country/index.php" class="btn btn-outline-secondary">
		<i class="bi bi-arrow-left me-1"></i>Volver
	</a>
</div>

<?php if ($error !== ''): ?>
	<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1090; margin-top: 65px;">
		<div class="toast show text-bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
			<div class="d-flex"><div class="toast-body"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div></div>
		</div>
	</div>
<?php endif; ?>

<form method="POST" autocomplete="off">
	<?= csrfField() ?>
	<input type="hidden" name="countryId" value="<?= $countryId ?>">

	<div class="card shadow-sm mb-4">
		<div class="card-header"><strong>Datos generales</strong></div>
		<div class="card-body"><div class="row g-3">
			<div class="col-md-6"><label for="name" class="form-label">Nombre</label><input id="name" name="name" class="form-control" maxlength="50" required value="<?= htmlspecialchars($form['name'], ENT_QUOTES, 'UTF-8') ?>"></div>
			<div class="col-md-3"><label for="iso2Code" class="form-label">Código ISO2</label><input id="iso2Code" name="iso2Code" class="form-control text-uppercase" maxlength="2" required value="<?= htmlspecialchars($form['iso2Code'], ENT_QUOTES, 'UTF-8') ?>"></div>
			<div class="col-md-3"><label for="iso3Code" class="form-label">Código ISO3</label><input id="iso3Code" name="iso3Code" class="form-control text-uppercase" maxlength="3" value="<?= htmlspecialchars($form['iso3Code'], ENT_QUOTES, 'UTF-8') ?>"></div>
			<div class="col-md-6"><label for="currencyId" class="form-label">Moneda</label><select id="currencyId" name="currencyId" class="form-select"><option value="">Sin moneda</option><?php foreach ($currencies as $currency): ?><option value="<?= (int) $currency['id'] ?>" <?= $form['currencyId'] === (string) $currency['id'] ? 'selected' : '' ?>><?= htmlspecialchars($currency['code'] . ' - ' . $currency['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
			<div class="col-md-6 d-flex align-items-end"><div class="form-check mb-2"><input type="checkbox" id="isActive" name="isActive" class="form-check-input" value="1" <?= $form['isActive'] ? 'checked' : '' ?>><label for="isActive" class="form-check-label">País activo</label></div></div>
		</div></div>
	</div>

	<div class="card shadow-sm mb-4">
		<div class="card-header"><strong>Configuración telefónica</strong></div>
		<div class="card-body"><div class="row g-3">
			<div class="col-md-3"><label for="phonePrefix" class="form-label">Prefijo internacional</label><input id="phonePrefix" name="phonePrefix" class="form-control" maxlength="10" value="<?= htmlspecialchars($form['phonePrefix'], ENT_QUOTES, 'UTF-8') ?>"></div>
			<div class="col-md-3"><label for="phoneDigitsToRemove" class="form-label">Dígitos iniciales a eliminar</label><input type="number" id="phoneDigitsToRemove" name="phoneDigitsToRemove" class="form-control" min="0" max="99" value="<?= htmlspecialchars($form['phoneDigitsToRemove'], ENT_QUOTES, 'UTF-8') ?>"></div>
			<div class="col-md-3"><label for="mobilePhoneFormat" class="form-label">Formato móvil</label><input id="mobilePhoneFormat" name="mobilePhoneFormat" class="form-control" maxlength="20" value="<?= htmlspecialchars($form['mobilePhoneFormat'], ENT_QUOTES, 'UTF-8') ?>"></div>
			<div class="col-md-3"><label for="fixedPhoneFormat" class="form-label">Formato fijo</label><input id="fixedPhoneFormat" name="fixedPhoneFormat" class="form-control" maxlength="20" value="<?= htmlspecialchars($form['fixedPhoneFormat'], ENT_QUOTES, 'UTF-8') ?>"></div>
		</div></div>
	</div>

	<div class="card shadow-sm mb-4">
		<div class="card-header"><strong>Documentos del país</strong></div>
		<div class="card-body">
			<?php foreach ($documentTypes as $documentType): ?>
				<?php $documentTypeId = (int) $documentType['id']; $document = $documents[$documentTypeId] ?? ['name' => '', 'format' => '']; ?>
				<div class="row g-3 mb-3"><div class="col-12"><strong><?= htmlspecialchars($documentType['name'], ENT_QUOTES, 'UTF-8') ?></strong></div><div class="col-md-6"><label class="form-label">Nombre en el país</label><input name="documents[<?= $documentTypeId ?>][name]" class="form-control" maxlength="50" value="<?= htmlspecialchars($document['name'], ENT_QUOTES, 'UTF-8') ?>"></div><div class="col-md-6"><label class="form-label">Formato</label><input name="documents[<?= $documentTypeId ?>][format]" class="form-control" maxlength="20" value="<?= htmlspecialchars($document['format'], ENT_QUOTES, 'UTF-8') ?>"></div></div>
			<?php endforeach; ?>
		</div>
	</div>

	<div class="d-flex justify-content-end gap-2 mb-4"><a href="<?= BASE_URL ?>/private/admin/country/index.php" class="btn btn-outline-secondary">Cancelar</a><button type="submit" class="btn btn-primary"><i class="bi bi-floppy me-1"></i>Guardar cambios</button></div>
</form>

<?php include BASE_PATH . '/layouts/footer.php'; ?>
