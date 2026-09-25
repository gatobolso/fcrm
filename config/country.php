<?php

declare(strict_types=1);


function getCountries(PDO $pdo, bool $onlyActive = true, bool $includeDeleted = false): array {
    $sql = "
        select
            co.id,
            co.iso2Code,
            co.iso3Code,
            co.name,
            co.stateName,
            cu.name as currencyName,
            co.mobilePhoneFormat,
            co.fixedPhoneFormat,
            co.phonePrefix,
            co.phoneDigitsToRemove,
            co.isActive,
            co.isDeleted
        from country co
            left join currency cu on cu.id = co.currencyId
        where 1 = 1
    ";

    if (!$includeDeleted) {
        $sql .= "
            and co.isDeleted = b'0'
        ";
    }

    if ($onlyActive) {
        $sql .= "
            and co.isActive = b'1'
        ";
    }

    $sql .= "
        ORDER BY co.name
    ";

    $stmt = $pdo->query($sql);

    return $stmt->fetchAll();
}

function deleteCountry(PDO $pdo, int $countryId): bool
{
    $stmt = $pdo->prepare(
        "UPDATE country
         SET isDeleted = b'1'
         WHERE id = :countryId
           AND COALESCE(isDeleted, b'0') = b'0'"
    );
    $stmt->execute([':countryId' => $countryId]);

    return $stmt->rowCount() > 0;
}

function getCountryById(PDO $pdo, int $countryId): array|false {
    $sql = "
        SELECT
            id,
            name,
            iso2Code,
            iso3Code,
            currencyId,
            phonePrefix,
            phoneDigitsToRemove,
            mobilePhoneFormat,
            fixedPhoneFormat,
            isActive
        FROM country
                WHERE id = :countryId
                    AND COALESCE(isDeleted, b'0') = b'0'
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':countryId',$countryId,PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetch();
}

function getCountriesDocuments(PDO $pdo): array
{
    $sql = "
        SELECT
            cd.countryId,
            cd.documentTypeId,
            cd.name,
            cd.format
        FROM country_document AS cd
        WHERE cd.format IS NOT NULL
          AND TRIM(cd.format) <> ''
    ";

    $stmt = $pdo->query($sql);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getCountryDocuments(PDO $pdo, int $countryId): array
{
    $stmt = $pdo->prepare(
        'SELECT documentTypeId, name, format
         FROM country_document
         WHERE countryId = :countryId'
    );
    $stmt->execute([':countryId' => $countryId]);

    $documents = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $document) {
        $documents[(int) $document['documentTypeId']] = [
            'name' => (string) ($document['name'] ?? ''),
            'format' => (string) ($document['format'] ?? '')
        ];
    }

    return $documents;
}

function updateCountry(PDO $pdo, int $countryId, array $country, array $documents = []): bool {
    $sql = "
        UPDATE country
        SET
            name = :name,
            iso2Code = :iso2Code,
            iso3Code = :iso3Code,
            currencyId = :currencyId,
            phonePrefix = :phonePrefix,
            phoneDigitsToRemove = :phoneDigitsToRemove,
            mobilePhoneFormat = :mobilePhoneFormat,
            fixedPhoneFormat = :fixedPhoneFormat,
            isActive = :isActive
        WHERE id = :countryId
          AND COALESCE(isDeleted, b'0') = b'0'
    ";

    $stmt = $pdo->prepare($sql);

    $pdo->beginTransaction();

    try {
        $stmt->execute([
            ':name' => $country['name'],
            ':iso2Code' => $country['iso2Code'],
            ':iso3Code' => $country['iso3Code'],
            ':currencyId' => $country['currencyId'],
            ':phonePrefix' => $country['phonePrefix'],
            ':phoneDigitsToRemove' => $country['phoneDigitsToRemove'],
            ':mobilePhoneFormat' => $country['mobilePhoneFormat'] ?: null,
            ':fixedPhoneFormat' => $country['fixedPhoneFormat'] ?: null,
            ':isActive' => $country['isActive'] ?? 1,
            ':countryId' => $countryId
        ]);

        $deleteDocuments = $pdo->prepare(
            'DELETE FROM country_document WHERE countryId = :countryId'
        );
        $deleteDocuments->execute([':countryId' => $countryId]);

        $insertDocument = $pdo->prepare(
            'INSERT INTO country_document (countryId, documentTypeId, name, format)
             VALUES (:countryId, :documentTypeId, :name, :format)'
        );

        foreach ($documents as $documentTypeId => $document) {
            $name = trim((string) ($document['name'] ?? ''));
            $format = trim((string) ($document['format'] ?? ''));

            if ($name === '' && $format === '') {
                continue;
            }

            $insertDocument->execute([
                ':countryId' => $countryId,
                ':documentTypeId' => (int) $documentTypeId,
                ':name' => $name !== '' ? $name : null,
                ':format' => $format !== '' ? $format : null
            ]);
        }

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

function importCountryStates(PDO $pdo, int $countryId, string $countryName): int {
    $url = 'https://countriesnow.space/api/v0.1/countries/states/q?country=' . rawurlencode($countryName);

    $curl = curl_init();

    curl_setopt_array(
        $curl,
        [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json'
            ]
        ]
    );

    $response = curl_exec($curl);

    if ($response === false) {
        $error = curl_error($curl);
        curl_close($curl);

        throw new RuntimeException(
            'Error al consultar la API: ' . $error
        );
    }

    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    curl_close($curl);

    if ($httpCode !== 200) {
        throw new RuntimeException(
            'La API respondió con el código HTTP ' . $httpCode
        );
    }

    $result = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

    if (
        ($result['error'] ?? true) === true || !isset($result['data']['states']) || !is_array($result['data']['states'])) {
        throw new RuntimeException(
            $result['msg'] ?? 'La API no devolvió estados válidos.'
        );
    }

    $sql = "
        INSERT INTO state
        (
            countryId,
            name
        )
        VALUES
        (
            :countryId,
            :name
        )
        ON DUPLICATE KEY UPDATE
            name = VALUES(name)
    ";

    $stmt = $pdo->prepare($sql);

    $importedStates = 0;

    try {
        $pdo->beginTransaction();

        foreach ($result['data']['states'] as $state) {
            $stateName = trim(
                (string) ($state['name'] ?? '')
            );

            if ($stateName === '') {
                continue;
            }

            $stmt->execute([
                ':countryId' => $countryId,
                ':name' => $stateName
            ]);

            $importedStates++;
        }

        $pdo->commit();

        return $importedStates;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

function getActiveCurrencies(PDO $pdo): array
{
    $sql = "
        SELECT
            id,
            code,
            name,
            symbol,
            decimalPlaces
        FROM currency
        WHERE isActive = b'1'
        ORDER BY name
    ";

    $stmt = $pdo->query($sql);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getDocumentTypes(PDO $pdo): array
{
    $sql = "
        SELECT
            id,
            name
        FROM document_type
        ORDER BY name
    ";

    $stmt = $pdo->query($sql);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function countryExistsByIso2(
    PDO $pdo,
    string $iso2Code
): bool {
    $sql = "
        SELECT EXISTS
        (
            SELECT 1
            FROM country
                        WHERE iso2Code = :iso2Code
                            AND COALESCE(isDeleted, b'0') = b'0'
        )
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':iso2Code' => $iso2Code
    ]);

    return (bool) $stmt->fetchColumn();
}

function countryExistsByName(
    PDO $pdo,
    string $name
): bool {
    $sql = "
        SELECT EXISTS
        (
            SELECT 1
            FROM country
                        WHERE name = :name
                            AND COALESCE(isDeleted, b'0') = b'0'
        )
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':name' => $name
    ]);

    return (bool) $stmt->fetchColumn();
}

function countryExistsByIso2Except(
    PDO $pdo,
    string $iso2Code,
    int $countryId
): bool {
    $stmt = $pdo->prepare(
        'SELECT EXISTS (
            SELECT 1 FROM country
            WHERE iso2Code = :iso2Code
              AND id <> :countryId
              AND COALESCE(isDeleted, b\'0\') = b\'0\'
        )'
    );
    $stmt->execute([
        ':iso2Code' => $iso2Code,
        ':countryId' => $countryId
    ]);

    return (bool) $stmt->fetchColumn();
}

function countryExistsByNameExcept(
    PDO $pdo,
    string $name,
    int $countryId
): bool {
    $stmt = $pdo->prepare(
        'SELECT EXISTS (
            SELECT 1 FROM country
            WHERE name = :name
              AND id <> :countryId
              AND COALESCE(isDeleted, b\'0\') = b\'0\'
        )'
    );
    $stmt->execute([
        ':name' => $name,
        ':countryId' => $countryId
    ]);

    return (bool) $stmt->fetchColumn();
}

function insertCountry(
    PDO $pdo,
    array $country,
    array $documents = []
): int {
    try {
        $pdo->beginTransaction();

        $sql = "
            INSERT INTO country
            (
                iso2Code,
                iso3Code,
                name,
                mobilePhoneFormat,
                fixedPhoneFormat,
                phonePrefix,
                phoneDigitsToRemove,
                currencyId,
                isActive,
                isDeleted,
                createDate
            )
            VALUES
            (
                :iso2Code,
                :iso3Code,
                :name,
                :mobilePhoneFormat,
                :fixedPhoneFormat,
                :phonePrefix,
                :phoneDigitsToRemove,
                :currencyId,
                :isActive,
                b'0',
                CURRENT_TIMESTAMP
            )
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ':iso2Code' => $country['iso2Code'],
            ':iso3Code' => $country['iso3Code'],
            ':name' => $country['name'],
            ':mobilePhoneFormat' => $country['mobilePhoneFormat'],
            ':fixedPhoneFormat' => $country['fixedPhoneFormat'],
            ':phonePrefix' => $country['phonePrefix'],
            ':phoneDigitsToRemove' => $country['phoneDigitsToRemove'],
            ':currencyId' => $country['currencyId'],
            ':isActive' => $country['isActive']
        ]);

        $countryId = (int) $pdo->lastInsertId();

        $documentSql = "
            INSERT INTO country_document
            (
                countryId,
                documentTypeId,
                name,
                format
            )
            VALUES
            (
                :countryId,
                :documentTypeId,
                :name,
                :format
            )
        ";

        $documentStmt = $pdo->prepare($documentSql);

        foreach ($documents as $document) {
            $documentTypeId = (int) (
                $document['documentTypeId'] ?? 0
            );

            $documentName = trim(
                (string) ($document['name'] ?? '')
            );

            $documentFormat = trim(
                (string) ($document['format'] ?? '')
            );

            if ($documentTypeId <= 0) {
                continue;
            }

            if (
                $documentName === ''
                && $documentFormat === ''
            ) {
                continue;
            }

            $documentStmt->execute([
                ':countryId' => $countryId,
                ':documentTypeId' => $documentTypeId,
                ':name' => $documentName !== ''
                    ? $documentName
                    : null,
                ':format' => $documentFormat !== ''
                    ? $documentFormat
                    : null
            ]);
        }

        $pdo->commit();

        return $countryId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}