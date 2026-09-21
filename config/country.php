<?php

declare(strict_types=1);


function getCountries(PDO $pdo, bool $onlyActive = true): array {
    $sql = "
        select
            co.id,
            co.iso2Code,
            co.iso3Code,
            co.name,
            cu.name as currencyName,
            co.mobilePhoneFormat,
            co.fixedPhoneFormat,
            co.phonePrefix,
            co.phoneDigitsToRemove,
            co.isActive
        from country co
            inner join currency cu on cu.id = co.currencyId
        where isDeleted = b'0'
    ";

    if ($onlyActive) {
        $sql .= "
            and co.isActive = b'1'
        ";
    }

    $sql .= "
        ORDER BY id
    ";

    $stmt = $pdo->query($sql);

    return $stmt->fetchAll();
}

function getCountryById(PDO $pdo,int $countryId): array|false {
    $sql = "
        SELECT
            id,
            name,
            iso2,
            iso3,
            mobilePhoneFormat,
            idCardFormat,
            active
        FROM country
        WHERE id = :countryId
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

function updateCountry(PDO $pdo,int $countryId,array $country): bool {
    $sql = "
        UPDATE country
        SET
            name = :name,
            iso2 = :iso2,
            iso3 = :iso3,
            mobilePhoneFormat = :mobilePhoneFormat,
            idCardFormat = :idCardFormat,
            active = :active
        WHERE id = :countryId
    ";

    $stmt = $pdo->prepare($sql);

    return $stmt->execute([
        ':name'              => $country['name'],
        ':iso2'              => $country['iso2'],
        ':iso3'              => $country['iso3'],
        ':mobilePhoneFormat' => $country['mobilePhoneFormat'] ?: null,
        ':idCardFormat'      => $country['idCardFormat'] ?: null,
        ':active'            => $country['active'] ?? 1,
        ':countryId'         => $countryId
    ]);
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
        )
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':name' => $name
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