-- Sistema inicial de permisos para FCRM.
-- Ejecutar una vez sobre la base de datos fcrm.

INSERT INTO permission (code, name, isActive)
SELECT 'DASHBOARD_VIEW', 'Ver el panel principal', b'1'
WHERE NOT EXISTS (SELECT 1 FROM permission WHERE code = 'DASHBOARD_VIEW');

INSERT INTO permission (code, name, isActive)
SELECT 'CLIENT_VIEW', 'Ver clientes', b'1'
WHERE NOT EXISTS (SELECT 1 FROM permission WHERE code = 'CLIENT_VIEW');

INSERT INTO permission (code, name, isActive)
SELECT 'CLIENT_CREATE', 'Crear clientes', b'1'
WHERE NOT EXISTS (SELECT 1 FROM permission WHERE code = 'CLIENT_CREATE');

INSERT INTO permission (code, name, isActive)
SELECT 'CLIENT_EDIT', 'Editar clientes', b'1'
WHERE NOT EXISTS (SELECT 1 FROM permission WHERE code = 'CLIENT_EDIT');

INSERT INTO permission (code, name, isActive)
SELECT 'CLIENT_BLOCK', 'Bloquear clientes', b'1'
WHERE NOT EXISTS (SELECT 1 FROM permission WHERE code = 'CLIENT_BLOCK');

INSERT INTO permission (code, name, isActive)
SELECT 'ENTITY_DELETE', 'Eliminar entidades', b'1'
WHERE NOT EXISTS (SELECT 1 FROM permission WHERE code = 'ENTITY_DELETE');

INSERT INTO permission (code, name, isActive)
SELECT 'EVENT_VIEW', 'Ver eventos', b'1'
WHERE NOT EXISTS (SELECT 1 FROM permission WHERE code = 'EVENT_VIEW');

INSERT INTO permission (code, name, isActive)
SELECT 'EXPENSE_VIEW', 'Ver gastos', b'1'
WHERE NOT EXISTS (SELECT 1 FROM permission WHERE code = 'EXPENSE_VIEW');

INSERT INTO permission (code, name, isActive)
SELECT 'IMPORT_VIEW', 'Ver importaciones', b'1'
WHERE NOT EXISTS (SELECT 1 FROM permission WHERE code = 'IMPORT_VIEW');

INSERT INTO permission (code, name, isActive)
SELECT 'USER_VIEW', 'Ver usuarios', b'1'
WHERE NOT EXISTS (SELECT 1 FROM permission WHERE code = 'USER_VIEW');

INSERT INTO permission (code, name, isActive)
SELECT 'USER_CREATE', 'Crear usuarios', b'1'
WHERE NOT EXISTS (SELECT 1 FROM permission WHERE code = 'USER_CREATE');

INSERT INTO permission (code, name, isActive)
SELECT 'USER_EDIT', 'Editar usuarios', b'1'
WHERE NOT EXISTS (SELECT 1 FROM permission WHERE code = 'USER_EDIT');

INSERT INTO permission (code, name, isActive)
SELECT 'USER_UNLOCK', 'Bloquear y desbloquear usuarios', b'1'
WHERE NOT EXISTS (SELECT 1 FROM permission WHERE code = 'USER_UNLOCK');

INSERT INTO permission (code, name, isActive)
SELECT 'USER_ACTIVATE', 'Activar y desactivar usuarios', b'1'
WHERE NOT EXISTS (SELECT 1 FROM permission WHERE code = 'USER_ACTIVATE');

INSERT INTO permission (code, name, isActive)
SELECT 'USER_DELETE', 'Eliminar usuarios', b'1'
WHERE NOT EXISTS (SELECT 1 FROM permission WHERE code = 'USER_DELETE');

INSERT INTO permission (code, name, isActive)
SELECT 'COUNTRY_VIEW', 'Ver países', b'1'
WHERE NOT EXISTS (SELECT 1 FROM permission WHERE code = 'COUNTRY_VIEW');

INSERT INTO permission (code, name, isActive)
SELECT 'COUNTRY_CREATE', 'Crear países', b'1'
WHERE NOT EXISTS (SELECT 1 FROM permission WHERE code = 'COUNTRY_CREATE');

INSERT INTO permission (code, name, isActive)
SELECT 'COUNTRY_EDIT', 'Editar y activar países', b'1'
WHERE NOT EXISTS (SELECT 1 FROM permission WHERE code = 'COUNTRY_EDIT');

INSERT INTO permission (code, name, isActive)
SELECT 'COUNTRY_DELETE', 'Borrar países', b'1'
WHERE NOT EXISTS (SELECT 1 FROM permission WHERE code = 'COUNTRY_DELETE');

INSERT INTO permission (code, name, isActive)
SELECT 'PROVIDER_VIEW', 'Ver proveedores', b'1'
WHERE NOT EXISTS (SELECT 1 FROM permission WHERE code = 'PROVIDER_VIEW');

INSERT INTO permission (code, name, isActive)
SELECT 'STATE_VIEW', 'Ver estados', b'1'
WHERE NOT EXISTS (SELECT 1 FROM permission WHERE code = 'STATE_VIEW');

INSERT INTO permission (code, name, isActive)
SELECT 'CITY_VIEW', 'Ver ciudades', b'1'
WHERE NOT EXISTS (SELECT 1 FROM permission WHERE code = 'CITY_VIEW');

INSERT INTO permission (code, name, isActive)
SELECT 'CURRENCY_VIEW', 'Ver monedas', b'1'
WHERE NOT EXISTS (SELECT 1 FROM permission WHERE code = 'CURRENCY_VIEW');

INSERT INTO permission (code, name, isActive)
SELECT 'DOCUMENT_TYPE_VIEW', 'Ver tipos de documento', b'1'
WHERE NOT EXISTS (SELECT 1 FROM permission WHERE code = 'DOCUMENT_TYPE_VIEW');

INSERT INTO permission (code, name, isActive)
SELECT 'CONTACT_TYPE_VIEW', 'Ver tipos de contacto', b'1'
WHERE NOT EXISTS (SELECT 1 FROM permission WHERE code = 'CONTACT_TYPE_VIEW');

INSERT INTO permission (code, name, isActive)
SELECT 'ROLE_VIEW', 'Ver roles', b'1'
WHERE NOT EXISTS (SELECT 1 FROM permission WHERE code = 'ROLE_VIEW');

INSERT INTO permission (code, name, isActive)
SELECT 'PERMISSION_VIEW', 'Ver permisos', b'1'
WHERE NOT EXISTS (SELECT 1 FROM permission WHERE code = 'PERMISSION_VIEW');

INSERT INTO role (code, name)
SELECT 'ADMIN', 'Administrador'
WHERE NOT EXISTS (SELECT 1 FROM role WHERE code = 'ADMIN');

INSERT INTO role__permission (roleId, permissionId)
SELECT r.id, p.id
FROM role AS r
CROSS JOIN permission AS p
WHERE r.code = 'ADMIN'
  AND p.code IN (
      'DASHBOARD_VIEW',
      'CLIENT_VIEW', 'CLIENT_CREATE', 'CLIENT_EDIT', 'CLIENT_BLOCK', 'ENTITY_DELETE',
      'EVENT_VIEW', 'EXPENSE_VIEW', 'IMPORT_VIEW',
      'USER_VIEW', 'USER_CREATE', 'USER_EDIT', 'USER_UNLOCK', 'USER_ACTIVATE', 'USER_DELETE',
      'COUNTRY_VIEW', 'COUNTRY_CREATE', 'COUNTRY_EDIT', 'COUNTRY_DELETE', 'PROVIDER_VIEW', 'STATE_VIEW', 'CITY_VIEW', 'CURRENCY_VIEW',
      'DOCUMENT_TYPE_VIEW', 'CONTACT_TYPE_VIEW', 'ROLE_VIEW', 'PERMISSION_VIEW'
  )
  AND NOT EXISTS (
      SELECT 1
      FROM role__permission AS existing
      WHERE existing.roleId = r.id
        AND existing.permissionId = p.id
  );

-- Otorga el rol inicial a los usuarios administrativos existentes.
INSERT INTO entity_user__role (userId, roleId)
SELECT eu.id, r.id
FROM entity_user AS eu
CROSS JOIN role AS r
WHERE eu.userTypeId = 1
  AND eu.isConfirmed = 1
  AND eu.isBlocked = 0
  AND eu.isDeleted = 0
  AND r.code = 'ADMIN'
  AND NOT EXISTS (
      SELECT 1
      FROM entity_user__role AS existing
      WHERE existing.userId = eu.id
        AND existing.roleId = r.id
  );
