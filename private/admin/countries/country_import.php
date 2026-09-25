<?php

declare(strict_types=1);

$title = 'Importar geografía';

require_once __DIR__ . '/../../../config/app.php';
require_once BASE_PATH . '/includes/auth.php';
requirePermission('COUNTRY_EDIT');
require_once BASE_PATH . '/config/database.php';

function curlOptions(): array {
    $options = [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_TIMEOUT => 90, CURLOPT_HTTPHEADER => ['Accept: application/json', 'User-Agent: FCRM/1.0']];
    if (defined('CURLSSLOPT_NATIVE_CA')) $options[CURLOPT_SSL_OPTIONS] = CURLSSLOPT_NATIVE_CA;
    return $options;
}

function decodeApiResponse(string|false $response, string $error, int $httpCode, string $url): array {
    if ($response === false || $error !== '') throw new RuntimeException('No se pudo conectar con la API: ' . $error);
    if ($httpCode < 200 || $httpCode >= 300) {
        $preview = mb_substr(trim(strip_tags((string) $response)), 0, 300);
        throw new RuntimeException('La API respondió con HTTP ' . $httpCode . '. URL: ' . $url . ($preview !== '' ? '. Respuesta: ' . $preview : ''));
    }

    $data = json_decode($response, true);
    if (!is_array($data)) throw new RuntimeException('La API devolvió una respuesta JSON no válida.');
    if (!empty($data['error'])) throw new RuntimeException((string) ($data['msg'] ?? $data['message'] ?? 'La API devolvió un error.'));
    return $data;
}

function apiGet(string $url): array {
    $curl = curl_init($url);
    curl_setopt_array($curl, curlOptions() + [CURLOPT_HTTPGET => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5]);
    $response = curl_exec($curl);
    $error = curl_error($curl);
    $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $effectiveUrl = (string) curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
    curl_close($curl);
    return decodeApiResponse($response, $error, $httpCode, $effectiveUrl);
}

function getCountry(PDO $pdo, int $countryId): array {
    $stmt = $pdo->prepare("SELECT id, name, iso2Code, iso3Code, stateName FROM country WHERE id = :countryId AND COALESCE(isDeleted, b'0') = b'0' LIMIT 1");
    $stmt->execute([':countryId' => $countryId]);
    $country = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$country) throw new RuntimeException('El país no existe o está eliminado.');
    return $country;
}

function localizeStateName(string $sourceName, string $iso2): string {
    $translations = [
        'AR' => [
            'Autonomous City of Buenos Aires' => 'Ciudad Autónoma de Buenos Aires',
            'Buenos Aires Province' => 'Buenos Aires',
            'Tierra del Fuego Province' => 'Tierra del Fuego, Antártida e Islas del Atlántico Sur'
        ]
    ];
    $iso2 = strtoupper(trim($iso2));
    if (isset($translations[$iso2][$sourceName])) return $translations[$iso2][$sourceName];
    if ($iso2 === 'AR') return trim((string) preg_replace('/\s+(Province|State|Department|Region)$/iu', '', $sourceName));
    return $sourceName;
}

function localizeCityName(string $sourceName, string $iso2): string {
    $translations = ['AR' => ['Buenos Aires' => 'Buenos Aires']];
    return $translations[strtoupper(trim($iso2))][$sourceName] ?? $sourceName;
}

function getOrCreateState(PDO $pdo, int $countryId, string $code, string $name): array {
    if ($code !== '') {
        $stmt = $pdo->prepare('SELECT id FROM state WHERE countryId = :countryId AND code = :code LIMIT 1');
        $stmt->execute([':countryId' => $countryId, ':code' => $code]);
    } else {
        $stmt = $pdo->prepare('SELECT id FROM state WHERE countryId = :countryId AND name = :name LIMIT 1');
        $stmt->execute([':countryId' => $countryId, ':name' => $name]);
    }

    $stateId = (int) ($stmt->fetchColumn() ?: 0);

    if ($stateId > 0) {
        $stmt = $pdo->prepare('UPDATE state SET code = :code, name = :name WHERE id = :stateId');
        $stmt->execute([':code' => $code !== '' ? $code : null, ':name' => $name, ':stateId' => $stateId]);
        return ['id' => $stateId, 'created' => false];
    }

    $stmt = $pdo->prepare('INSERT INTO state (countryId, code, name) VALUES (:countryId, :code, :name)');
    $stmt->execute([':countryId' => $countryId, ':code' => $code !== '' ? $code : null, ':name' => $name]);
    return ['id' => (int) $pdo->lastInsertId(), 'created' => true];
}

function generateCityCode(string $name): string {
    $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
    $normalized = strtoupper((string) $normalized);
    $normalized = preg_replace('/[^A-Z0-9]+/', '-', $normalized) ?? '';
    $normalized = trim($normalized, '-');
    $normalized = $normalized !== '' ? $normalized : 'CITY';
    return substr($normalized, 0, 100) . '-' . strtoupper(substr(sha1($name), 0, 6));
}

function getOrCreateCity(PDO $pdo, int $stateId, string $name): bool {
    $code = generateCityCode($name);
    $stmt = $pdo->prepare('SELECT id FROM city WHERE stateId = :stateId AND (code = :code OR name = :name) LIMIT 1');
    $stmt->execute([':stateId' => $stateId, ':code' => $code, ':name' => $name]);
    $cityId = (int) ($stmt->fetchColumn() ?: 0);

    if ($cityId > 0) {
        $stmt = $pdo->prepare('UPDATE city SET code = :code, name = :name WHERE id = :cityId');
        $stmt->execute([':code' => $code, ':name' => $name, ':cityId' => $cityId]);
        return false;
    }

    $stmt = $pdo->prepare('INSERT INTO city (stateId, code, name) VALUES (:stateId, :code, :name)');
    $stmt->execute([':stateId' => $stateId, ':code' => $code, ':name' => $name]);
    return true;
}

function jsonResponse(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$countryId = (int) ($_GET['countryId'] ?? $_POST['countryId'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        requireValidCsrfToken();
        if ($countryId <= 0) throw new InvalidArgumentException('El identificador del país no es válido.');

        $country = getCountry($pdo, $countryId);
        $action = trim((string) ($_POST['action'] ?? ''));

        if ($action === 'loadStates') {
            $url = 'https://countriesnow.space/api/v0.1/countries/states/q?' . http_build_query(['country' => (string) $country['name']], '', '&', PHP_QUERY_RFC3986);
            $response = apiGet($url);
            $apiStates = $response['data']['states'] ?? [];
            if (!is_array($apiStates) || $apiStates === []) throw new RuntimeException('La API no devolvió divisiones administrativas para el país.');

            $states = [];
            $created = 0;
            $existing = 0;
            $pdo->beginTransaction();

            try {
                foreach ($apiStates as $apiState) {
                    $sourceName = trim((string) ($apiState['name'] ?? ''));
                    $stateCode = trim((string) ($apiState['state_code'] ?? ''));
                    if ($sourceName === '') continue;

                    $name = localizeStateName($sourceName, (string) $country['iso2Code']);
                    $state = getOrCreateState($pdo, $countryId, $stateCode, $name);
                    $state['name'] = $name;
                    $state['apiName'] = $sourceName;
                    $state['code'] = $stateCode;
                    $states[] = $state;
                    $state['created'] ? $created++ : $existing++;
                }
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }

            jsonResponse(['ok' => true, 'states' => $states, 'created' => $created, 'existing' => $existing]);
        }

        if ($action === 'loadCities') {
            $stateId = (int) ($_POST['stateId'] ?? 0);
            $sourceStateName = trim((string) ($_POST['stateName'] ?? ''));
            if ($stateId <= 0 || $sourceStateName === '') throw new InvalidArgumentException('La división administrativa no es válida.');

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM state WHERE id = :stateId AND countryId = :countryId');
            $stmt->execute([':stateId' => $stateId, ':countryId' => $countryId]);
            if ((int) $stmt->fetchColumn() !== 1) throw new RuntimeException('La división administrativa no pertenece al país seleccionado.');

            $url = 'https://countriesnow.space/api/v0.1/countries/state/cities/q?' . http_build_query(['country' => (string) $country['name'], 'state' => $sourceStateName], '', '&', PHP_QUERY_RFC3986);
            $response = apiGet($url);
            $apiCities = $response['data'] ?? [];
            if (!is_array($apiCities)) throw new RuntimeException('La API no devolvió una lista válida de ciudades.');

            $created = 0;
            $existing = 0;
            $pdo->beginTransaction();

            try {
                foreach ($apiCities as $apiCity) {
                    $sourceName = trim(is_array($apiCity) ? (string) ($apiCity['name'] ?? '') : (string) $apiCity);
                    if ($sourceName === '') continue;
                    $name = localizeCityName($sourceName, (string) $country['iso2Code']);
                    getOrCreateCity($pdo, $stateId, $name) ? $created++ : $existing++;
                }
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }

            jsonResponse(['ok' => true, 'created' => $created, 'existing' => $existing, 'total' => count($apiCities)]);
        }

        throw new InvalidArgumentException('La acción solicitada no es válida.');
    } catch (Throwable $e) {
        error_log('Error importando geografía: ' . $e->getMessage());
        jsonResponse(['ok' => false, 'message' => $e->getMessage()], 500);
    }
}

if ($countryId <= 0) exit('El identificador del país no es válido.');
$country = getCountry($pdo, $countryId);

include BASE_PATH . '/layouts/header.php';
include BASE_PATH . '/layouts/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h1 class="page-title mb-0">Importar geografía</h1><div class="text-body-secondary"><?= htmlspecialchars((string) $country['name'], ENT_QUOTES, 'UTF-8') ?></div></div>
    <a href="<?= BASE_URL ?>/private/admin/countries/country_index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Volver a países</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="alert alert-info"><i class="bi bi-info-circle me-1"></i>Se importarán las divisiones y ciudades evitando duplicados. Los nombres se mostrarán en español cuando exista una equivalencia configurada.</div>
        <form id="importForm" class="mb-4"><?= csrfField() ?><input type="hidden" name="countryId" value="<?= $countryId ?>"><button type="submit" id="startImportButton" class="btn btn-primary"><i class="bi bi-cloud-download me-1"></i>Iniciar importación</button></form>

        <div id="progressPanel" class="d-none">
            <div class="d-flex justify-content-between align-items-center mb-2"><strong id="progressTitle">Preparando importación...</strong><span id="progressPercent" class="badge text-bg-primary">0%</span></div>
            <div class="progress mb-3"><div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%;">0%</div></div>
            <div class="row g-3 mb-4">
                <div class="col-md-3"><div class="border rounded p-3 text-center"><div class="small text-body-secondary">Divisiones nuevas</div><div id="statesCreated" class="fs-4 fw-semibold">0</div></div></div>
                <div class="col-md-3"><div class="border rounded p-3 text-center"><div class="small text-body-secondary">Divisiones existentes</div><div id="statesExisting" class="fs-4 fw-semibold">0</div></div></div>
                <div class="col-md-3"><div class="border rounded p-3 text-center"><div class="small text-body-secondary">Ciudades nuevas</div><div id="citiesCreated" class="fs-4 fw-semibold">0</div></div></div>
                <div class="col-md-3"><div class="border rounded p-3 text-center"><div class="small text-body-secondary">Ciudades existentes</div><div id="citiesExisting" class="fs-4 fw-semibold">0</div></div></div>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-2"><strong>Registro del proceso</strong><span id="errorCount" class="badge text-bg-danger d-none">0 errores</span></div>
            <div id="importLog" class="border rounded bg-body-tertiary p-3 import-log"></div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('importForm');
    const button = document.getElementById('startImportButton');
    const panel = document.getElementById('progressPanel');
    const bar = document.getElementById('progressBar');
    const title = document.getElementById('progressTitle');
    const percent = document.getElementById('progressPercent');
    const log = document.getElementById('importLog');
    const errorBadge = document.getElementById('errorCount');
    const counters = { statesCreated: 0, statesExisting: 0, citiesCreated: 0, citiesExisting: 0 };
    let errors = 0;

    function updateCounter(name, value) { counters[name] += Number(value || 0); document.getElementById(name).textContent = counters[name]; }
    function escapeHtml(value) { const div = document.createElement('div'); div.textContent = String(value); return div.innerHTML; }
    function addLog(message, type = 'info') {
        const icon = type === 'success' ? 'check-circle-fill text-success' : type === 'error' ? 'exclamation-triangle-fill text-danger' : 'info-circle-fill text-primary';
        log.insertAdjacentHTML('beforeend', '<div class="d-flex gap-2 mb-2"><i class="bi bi-' + icon + '"></i><div>' + escapeHtml(message) + '</div></div>');
        log.scrollTop = log.scrollHeight;
        if (type === 'error') { errors++; errorBadge.textContent = errors + (errors === 1 ? ' error' : ' errores'); errorBadge.classList.remove('d-none'); }
    }
    function setProgress(current, total, message) { const value = total > 0 ? Math.round(current / total * 100) : 0; title.textContent = message; percent.textContent = value + '%'; bar.style.width = value + '%'; bar.textContent = value + '%'; }
    async function request(action, extra = {}) {
        const data = new FormData(form); data.append('action', action); Object.entries(extra).forEach(function ([key, value]) { data.append(key, value); });
        const response = await fetch(window.location.href, { method: 'POST', body: data, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const result = await response.json().catch(function () { return { ok: false, message: 'Respuesta no válida del servidor.' }; });
        if (!response.ok || !result.ok) throw new Error(result.message || 'La operación no se pudo completar.');
        return result;
    }

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        button.disabled = true; panel.classList.remove('d-none'); log.innerHTML = ''; errors = 0; errorBadge.classList.add('d-none');
        Object.keys(counters).forEach(function (key) { counters[key] = 0; document.getElementById(key).textContent = '0'; });
        addLog('Conectando con la API para descargar las divisiones administrativas.');

        try {
            const result = await request('loadStates');
            updateCounter('statesCreated', result.created); updateCounter('statesExisting', result.existing);
            addLog('Divisiones cargadas: ' + result.states.length + '.', 'success');

            for (let i = 0; i < result.states.length; i++) {
                const state = result.states[i];
                setProgress(i, result.states.length, 'Descargando ciudades de ' + state.name + '...');
                try {
                    const cities = await request('loadCities', { stateId: state.id, stateName: state.apiName });
                    updateCounter('citiesCreated', cities.created); updateCounter('citiesExisting', cities.existing);
                    addLog(state.name + ': ' + cities.created + ' ciudades nuevas y ' + cities.existing + ' existentes.', 'success');
                } catch (error) { addLog(state.name + ': ' + error.message, 'error'); }
                setProgress(i + 1, result.states.length, 'Procesadas ' + (i + 1) + ' de ' + result.states.length + ' divisiones.');
            }

            bar.classList.remove('progress-bar-animated'); bar.classList.add(errors > 0 ? 'bg-warning' : 'bg-success');
            title.textContent = errors > 0 ? 'Importación finalizada con incidencias.' : 'Importación completada correctamente.';
        } catch (error) { bar.classList.add('bg-danger'); addLog(error.message, 'error'); title.textContent = 'La importación no pudo completarse.'; }
        finally { button.disabled = false; button.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>Ejecutar nuevamente'; }
    });
});
</script>

<style>.import-log { height: 300px; overflow-y: auto; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: .85rem; }</style>

<?php include BASE_PATH . '/layouts/footer.php'; ?>
