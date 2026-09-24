<?php

declare(strict_types=1);


function getEntitiesByTypeId(PDO $pdo, int $entityTypeId, bool $includeDeleted = false): array
{
    $sql = "
        select
            e.id,
            e.name,
            co.name as countryName,
            co.iso2Code as countryIso2Code,
            e.documentNumber,
            cd.name as documentName,
            e.email,
            e.phone,
            e.address,
            cd.format as documentFormat,
            co.mobilePhoneFormat,
            co.phonePrefix,
            co.phoneDigitsToRemove,
            e.hasImported,
            e.comments,
            e.isContractSigned,
            e.contractFile,
            e.createDate,
            e.isDeleted + 0 AS isDeleted
            /*
            GROUP_CONCAT(
                DISTINCT CASE
                    WHEN ec.contactTypeId = 1
                    THEN ec.contact
                END
                ORDER BY ec.id
                SEPARATOR '||'
            ) AS emails,
            GROUP_CONCAT(
                DISTINCT CASE
                    WHEN ec.contactTypeId in (2,3)
                    THEN ec.contact
                END
                ORDER BY ec.id
                SEPARATOR '||'
            ) AS phones
            */
        from fcrm.entity e
            left join fcrm.country co on co.id = e.countryId
            left join fcrm.country_document cd on cd.countryId = co.id and cd.documentTypeId = e.documentTypeId
            #left  join fcrm.entity_contact ec on ec.entityId = e.id
        where e.entityTypeId = :entityTypeId
        /*
        group by
            e.id,
            e.name,
            co.name,
            e.documentNumber,
            cd.name,
            e.emailAddress,
            e.phoneNumber,
            cd.format,
            co.mobilePhoneFormat,
            co.phonePrefix,
            co.phoneDigitsToRemove,
            e.hasImported,
            e.comments,
            e.isContractSigned,
            e.contractFile,
            e.createDate
            */";

    if (!$includeDeleted) {
        $sql = str_replace(
            'where e.entityTypeId = :entityTypeId',
            "where COALESCE(e.isDeleted, b'0') = b'0'
                and e.entityTypeId = :entityTypeId",
            $sql
        );
    }

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':entityTypeId', $entityTypeId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function getEntityContacts(PDO $pdo, int $entityId): array|false {
    $sql = "
        select
            id,
            contactTypeId,
            contact,
            comment,
            isPrimary
        from fcrm.entity_contact
        where isActive = 1
            and entityId = :entityId
        ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':entityId', $entityId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function getClientById(PDO $pdo, int $entityId): array|false {
    $sql = "
        select
            id,
            entityTypeId,
            name,
            countryId,
            documentTypeId,
            documentNumber,
            email,
            phone,
            address,
            hasImported,
            comments,
            isContractSigned,
            contractFile
        from fcrm.entity
        where COALESCE(isDeleted, b'0') = b'0'
            and entityTypeId = 2
            and id = :entityId
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':entityId',$entityId,PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetch();
}

function insertEntity(PDO $pdo, array $entity, array $entityTypes, array $contacts): int|string {
    try {
        $pdo->beginTransaction();

        /*
        |--------------------------------------------------------------------------
        | 1. Insertar entidad
        |--------------------------------------------------------------------------
        */

        $sql = "
            INSERT INTO entity
            (
                entityTypeId,
                name,
                countryId,
                documentTypeId,
                documentNumber,
                address,
                comments
            )
            VALUES
            (
                :entityTypeId,
                :name,
                :countryId,
                :documentTypeId,
                :documentNumber,
                :address,
                :comments
            )
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->bindValue(':entityTypeId', $entity['entityTypeId'], $entity['entityTypeId'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':name', $entity['name'], PDO::PARAM_STR);
        $stmt->bindValue(':countryId', $entity['countryId'], $entity['countryId'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':documentTypeId', $entity['documentTypeId'], $entity['documentTypeId'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':documentNumber', $entity['documentNumber'], $entity['documentNumber'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':address', $entity['address'], $entity['address'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':comments', $entity['comments'], $entity['comments'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

        $stmt->execute();

        $entityId = (int) $pdo->lastInsertId();

        if ($entityId <= 0) {
            throw new RuntimeException(
                'No se pudo obtener el identificador de la entidad.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Insertar tipos de entidad
        |--------------------------------------------------------------------------
        */

        $entityTypes = array_values(
            array_unique(
                array_filter(
                    array_map('intval', $entityTypes),
                    static fn(int $id): bool => $id > 0
                )
            )
        );

        if ($entityTypes !== []) {
            $typeSql = "
                INSERT INTO entity__entity_type (entityId, entityTypeId, createDate, isActive, isDeleted)
                VALUES(:entityId, :entityTypeId, CURRENT_TIMESTAMP, b'1', b'0')
            ";

            $typeStmt = $pdo->prepare($typeSql);

            foreach ($entityTypes as $entityTypeId) {
                $typeStmt->bindValue(':entityId', $entityId, PDO::PARAM_INT);
                $typeStmt->bindValue(':entityTypeId', $entityTypeId, PDO::PARAM_INT);

                $typeStmt->execute();
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 3. Insertar contactos
        |--------------------------------------------------------------------------
        */

        $contactSql = "
            INSERT INTO entity_contact (entityId, contactTypeId, contact, comment, isPrimary, isActive)
            VALUES (:entityId, :contactTypeId, :contact, :comment, :isPrimary, :isActive)
        ";

        $contactStmt = $pdo->prepare($contactSql);

        $principalContactTypes = [];

        foreach ($contacts as $contact) {
            $contactTypeId = (int) ($contact['contactTypeId'] ?? 0);
            $contactValue = trim((string) ($contact['contact'] ?? ''));
            $comment = trim((string) ($contact['comment'] ?? ''));
            $isPrimary = !empty($contact['isPrimary']) ? 1 : 0;
            $isActive = !empty($contact['isActive']) ? 1 : 0;

            if ($contactTypeId <= 0 || $contactValue === '') {
                continue;
            }

            if ($isPrimary === 1) {
                if (isset($principalContactTypes[$contactTypeId])) {
                    throw new InvalidArgumentException(
                        'Solo puede existir un contacto principal por cada tipo.'
                    );
                }

                $principalContactTypes[$contactTypeId] = true;
            }

            $contactStmt->bindValue(':entityId', $entityId, PDO::PARAM_INT);
            $contactStmt->bindValue(':contactTypeId', $contactTypeId, PDO::PARAM_INT);
            $contactStmt->bindValue(':contact', $contactValue, PDO::PARAM_STR);

            if ($comment === '') {
                $contactStmt->bindValue(':comment', null, PDO::PARAM_NULL);
            } else {
                $contactStmt->bindValue(':comment', $comment, PDO::PARAM_STR);
            }

            $contactStmt->bindValue(':isPrimary', $isPrimary, PDO::PARAM_INT );
            $contactStmt->bindValue(':isActive', $isActive, PDO::PARAM_INT);

            $contactStmt->execute();
        }

        /*
        |--------------------------------------------------------------------------
        | 4. Confirmar todos los inserts
        |--------------------------------------------------------------------------
        */

        $pdo->commit();

        return $entityId;
    } catch (Throwable $e) {
        /*
        |--------------------------------------------------------------------------
        | 5. Revertir todos los inserts
        |--------------------------------------------------------------------------
        */

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Error al crear la entidad: ' . $e->getMessage());

        if ($e instanceof InvalidArgumentException) {
            return $e->getMessage();
        }

        if (
            $e instanceof PDOException
            && (string) $e->getCode() === '23000'
        ) {
            return 'No se pudo guardar porque existe un dato duplicado o una relación no válida.';
        }

        return 'No se pudo crear el cliente. No se guardó ningún dato.';
    }
}

function updateEntity(PDO $pdo, array $entity): string {
    $error= '';

    $sql = "
        update entity
        set
            entityTypeId = :entityTypeId,
            name = :name,
            countryId = :countryId,
            documentTypeId = :documentTypeId,
            documentNumber = :documentNumber,
            address = :address,
            comments = :comments
        where id = :entityId
    ";

    try {
        $stmt = $pdo->prepare($sql);

        $stmt->bindValue(':entityTypeId', $entity['entityTypeId'], PDO::PARAM_INT);
        $stmt->bindValue(':name', $entity['name'], PDO::PARAM_STR);
        $stmt->bindValue(':countryId', $entity['countryId'], PDO::PARAM_INT);
        $stmt->bindValue(':documentTypeId', $entity['documentTypeId'], PDO::PARAM_INT);
        $stmt->bindValue(':documentNumber', $entity['documentNumber'], PDO::PARAM_STR);
        $stmt->bindValue(':address', $entity['address'], $entity['address'] === null ? PDO::PARAM_NULL: PDO::PARAM_STR);
        $stmt->bindValue(':comments', $entity['comments'], $entity['comments'] === null ? PDO::PARAM_NULL: PDO::PARAM_STR);
        $stmt->bindValue(':entityId', $entity['entityId'], PDO::PARAM_INT);
    
        $stmt->execute();

    } catch (Throwable $e) {
        error_log($e->getMessage());
        $error = $e->getMessage();
    }

    return $error;
}

function setClientBlocked(PDO $pdo,int $clientId,bool $blocked): bool {
    $sql = "
        UPDATE entity
        SET isBlocked = :isBlocked
        WHERE id = :clientId
            AND entityTypeId = 2
            AND COALESCE(isDeleted, b'0') = b'0'
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':isBlocked', $blocked ? 1 : 0, PDO::PARAM_INT);
    $stmt->bindValue(':clientId', $clientId, PDO::PARAM_INT);

    return $stmt->execute();
}

function deleteClient(PDO $pdo, int $clientId): bool {
    $sql = "
        UPDATE entity
        SET isDeleted = 1
        WHERE COALESCE(isDeleted, b'0') = b'0'
            and entityTypeId = 2
            and id = :clientId
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':clientId',$clientId,PDO::PARAM_INT);

    return $stmt->execute();
}

function setUserBlocked(PDO $pdo, int $userId, bool $blocked): bool
{
    $stmt = $pdo->prepare(
        'UPDATE entity_user
         SET isBlocked = :isBlocked
                 WHERE id = :userId
                     AND entityId IN (SELECT id FROM entity WHERE entityTypeId = 2)
           AND COALESCE(isDeleted, b\'0\') = b\'0\''
    );
    $stmt->bindValue(':isBlocked', $blocked ? 1 : 0, PDO::PARAM_INT);
    $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);

    return $stmt->execute();
}

function setUserActivated(PDO $pdo, int $userId, bool $activated): bool
{
    $stmt = $pdo->prepare(
        'UPDATE entity_user
         SET isConfirmed = :isConfirmed
                 WHERE id = :userId
                     AND entityId IN (SELECT id FROM entity WHERE entityTypeId = 2)
           AND COALESCE(isDeleted, b\'0\') = b\'0\''
    );
    $stmt->bindValue(':isConfirmed', $activated ? 1 : 0, PDO::PARAM_INT);
    $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);

    return $stmt->execute();
}

function deleteUser(PDO $pdo, int $userId): bool
{
    $stmt = $pdo->prepare(
        'UPDATE entity_user
         SET isDeleted = b\'1\'
                 WHERE id = :userId
                     AND entityId IN (SELECT id FROM entity WHERE entityTypeId = 2)
           AND COALESCE(isDeleted, b\'0\') = b\'0\''
    );
    $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);

    return $stmt->execute();
}

function getEntityTypes(PDO $pdo): array {
    $sql = "
        SELECT
            id,
            name
        FROM entity_type
        ORDER BY id
    ";

    $stmt = $pdo->query($sql);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getContactTypes(PDO $pdo): array {
    $sql = "
        SELECT
            id,
            name
        FROM contact_type
        ORDER BY id
        ";

    return $pdo
        ->query($sql)
        ->fetchAll(PDO::FETCH_ASSOC);
}

function getUsersByEntities(PDO $pdo, bool $includeDeleted = false): array
{
    $sql = "
        SELECT
            eu.id,
            eu.entityId,
            eu.username,
            eu.createDate,
            eu.isConfirmed + 0 AS isConfirmed,
            eu.isBlocked + 0 AS isBlocked,
            eu.isDeleted + 0 AS isDeleted
        FROM entity_user AS eu
        ORDER BY
            eu.entityId,
            eu.username
    ";

    if (!$includeDeleted) {
        $sql = str_replace(
            '        ORDER BY',
            "        WHERE COALESCE(eu.isDeleted, b'0') = b'0'\n        ORDER BY",
            $sql
        );
    }

    $stmt = $pdo->query($sql);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $usersByEntity = [];

    foreach ($rows as $row) {
        $entityId = (int) $row['entityId'];

        if (!isset($usersByEntity[$entityId])) {
            $usersByEntity[$entityId] = [];
        }

        $usersByEntity[$entityId][] = $row;
    }

    return $usersByEntity;
}

function getAddressesByEntities(PDO $pdo, bool $includeDeleted = false): array
{
    $sql = "
        SELECT
            ea.id,
            ea.entityId,
            ea.addressLine1,
            ea.addressLine2,
            ea.postalCode,
            ea.addressType,
            ea.isPrimary + 0 AS isPrimary,
            ea.isActive + 0 AS isActive,
            ea.isDeleted + 0 AS isDeleted,
            ci.name AS cityName,
            st.name AS stateName,
            co.name AS countryName
        FROM entity_address AS ea
        LEFT JOIN city AS ci ON ci.id = ea.cityId
        LEFT JOIN state AS st ON st.id = ci.stateId
        LEFT JOIN country AS co ON co.id = st.countryId
    ";

    if (!$includeDeleted) {
        $sql .= "
            WHERE ea.isActive = b'1'
              AND COALESCE(ea.isDeleted, b'0') = b'0'
        ";
    }

    $sql .= " ORDER BY ea.entityId, ea.isPrimary DESC, ea.id";

    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $addressesByEntity = [];

    foreach ($rows as $row) {
        $addressesByEntity[(int) $row['entityId']][] = $row;
    }

    return $addressesByEntity;
}

function insertClientUser(
    PDO $pdo,
    int $entityId,
    string $username,
    string $password,
    int $userTypeId = 2
): int|string {
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO entity_user
                     (entityId, userTypeId, username, pwdHash, isConfirmed, isBlocked, isDeleted)
                 SELECT :entityId, :userTypeId, :username, :pwdHash, b\'0\', b\'0\', b\'0\'
             FROM entity
             WHERE id = :entityIdCheck
               AND entityTypeId = 2
               AND COALESCE(isDeleted, b\'0\') = b\'0\''
        );
        $stmt->execute([
            ':entityId' => $entityId,
            ':entityIdCheck' => $entityId,
            ':userTypeId' => $userTypeId,
            ':username' => $username,
            ':pwdHash' => password_hash($password, PASSWORD_DEFAULT)
        ]);

        if ($stmt->rowCount() !== 1) {
            return 'El cliente no existe o está eliminado.';
        }

        return (int) $pdo->lastInsertId();
    } catch (PDOException $exception) {
        if ((string) $exception->getCode() === '23000') {
            return 'El nombre de usuario ya existe.';
        }

        error_log($exception->getMessage());
        return 'No se pudo crear el usuario.';
    }
}

function updateClientUser(
    PDO $pdo,
    int $userId,
    string $username,
    ?string $password = null
): bool|string {
    try {
        $fields = ['username = :username'];
        $params = [
            ':userId' => $userId,
            ':username' => $username
        ];

        if ($password !== null) {
            $fields[] = 'pwdHash = :pwdHash';
            $params[':pwdHash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        $stmt = $pdo->prepare(
            'UPDATE entity_user AS eu
             INNER JOIN entity AS e ON e.id = eu.entityId
             SET ' . implode(', ', $fields) . '
             WHERE eu.id = :userId
               AND e.entityTypeId = 2
               AND COALESCE(e.isDeleted, b\'0\') = b\'0\'
               AND COALESCE(eu.isDeleted, b\'0\') = b\'0\''
        );
        $stmt->execute($params);

        return true;
    } catch (PDOException $exception) {
        if ((string) $exception->getCode() === '23000') {
            return 'El nombre de usuario ya existe.';
        }

        error_log($exception->getMessage());
        return 'No se pudo actualizar el usuario.';
    }
}