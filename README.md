# FCRM

Aplicación web de gestión de clientes, entidades, usuarios, contactos y datos maestros, desarrollada en PHP con MariaDB y Bootstrap 5.

## Descripción

FCRM es una aplicación tipo CRM orientada a centralizar la información de clientes y otras entidades relacionadas con el negocio.

El sistema permite gestionar:

- Entidades y clientes.
- Múltiples tipos de entidad.
- Correos y teléfonos por entidad.
- Contactos principales y secundarios.
- Países, estados, ciudades y monedas.
- Tipos y formatos de documentos por país.
- Usuarios del sistema.
- Roles y permisos.
- Preferencias de usuario.
- Tema claro y oscuro.
- Auditoría de cambios.

## Tecnologías

- PHP 8
- MariaDB
- PDO
- HTML5
- CSS3
- JavaScript
- Bootstrap 5.3
- Bootstrap Icons
- WampServer para el entorno local

## Requisitos

- PHP 8.1 o superior.
- MariaDB 10.6 o superior.
- Servidor web Apache.
- Extensión PDO MySQL habilitada.
- Extensión cURL habilitada para las importaciones desde API.
- Navegador web moderno.

En Windows se puede utilizar WampServer, XAMPP o Laragon.

## Estructura del proyecto

```text
fcrm/
├── config/
│   ├── app.php
│   ├── database.php
│   ├── country.php
│   └── entity.php
├── includes/
│   └── auth.php
├── layouts/
│   ├── header.php
│   ├── sidebar.php
│   └── footer.php
├── private/
│   ├── dashboard.php
│   ├── clientes/
│   │   ├── index.php
│   │   ├── new.php
│   │   └── edit.php
│   └── administracion/
│       ├── paises/
│       │   ├── index.php
│       │   ├── new.php
│       │   └── edit.php
│       ├── estados/
│       ├── ciudades/
│       └── monedas/
├── assets/
│   ├── css/
│   └── js/
├── login.php
├── logout.php
└── README.md
```

## Configuración

### 1. Clonar el repositorio

```bash
git clone URL_DEL_REPOSITORIO
cd fcrm
```

### 2. Configurar la aplicación

Crear o revisar el archivo `config/app.php`:

```php
<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', '/fcrm');
```

`BASE_PATH` representa la ruta física del proyecto y se utiliza para cargar archivos PHP.

`BASE_URL` representa la ruta web base y se utiliza en enlaces, formularios y redirecciones.

### 3. Configurar la conexión

Configurar las credenciales de MariaDB en `config/database.php`:

```php
<?php

$dbHost = '127.0.0.1';
$dbPort = 3306;
$dbName = 'fcrm';
$dbUser = 'root';
$dbPassword = '';

$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
    $dbHost,
    $dbPort,
    $dbName
);

$pdo = new PDO(
    $dsn,
    $dbUser,
    $dbPassword,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]
);
```

No se recomienda guardar credenciales reales en el repositorio. Para entornos compartidos o productivos, utilizar variables de entorno o un gestor de secretos.

### 4. Preparar la base de datos

Crear una base de datos con codificación UTF-8:

```sql
CREATE DATABASE fcrm
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;
```

Después, ejecutar los scripts SQL del proyecto para crear tablas, índices, claves foráneas, vistas y triggers.

## Modelo general

La entidad principal del sistema es `entity`.

```text
entity
├── entity__entity_type
│   └── entity_type
├── entity_contact
│   └── contact_type
├── entity_user
│   ├── entity_user__role
│   │   └── role
│   │       └── role__permission
│   │           └── permission
│   └── entity_user__preferences
├── country
│   ├── country_document
│   │   └── document_type
│   └── state
│       └── city
└── data_changes
```

## Entidades

La tabla `entity` almacena los datos generales:

- UUID.
- Nombre.
- País.
- Tipo y número de documento.
- Dirección.
- Indicadores comerciales.
- Comentarios.
- Contrato.
- Estado de eliminación lógica.
- Fecha de creación.

Una entidad puede tener varios tipos mediante `entity__entity_type`.

Ejemplos de tipos:

- Cliente.
- Empleado.
- Proveedor.
- Contacto.

## Contactos

Los contactos se almacenan en `entity_contact`.

Cada entidad puede tener varios contactos de cada tipo:

- Correo.
- Teléfono fijo.
- Teléfono móvil.
- Otros tipos configurables.

Cada contacto puede tener:

- Tipo.
- Valor.
- Comentario.
- Indicador de contacto principal.
- Indicador de contacto activo.

La pantalla de edición permite agregar y retirar filas dinámicamente. Al guardar, la aplicación sincroniza la lista recibida con la información almacenada en MariaDB.

## Países y documentos

La tabla `country` almacena:

- Código ISO2.
- Código ISO3.
- Nombre.
- Moneda.
- Prefijo telefónico.
- Formatos de teléfono móvil y fijo.
- Cantidad de dígitos iniciales que deben eliminarse.
- Estado activo y eliminación lógica.

Los documentos se configuran mediante:

```text
country
└── country_document
    └── document_type
```

Esto permite definir formatos diferentes según el país y el tipo de documento.

Ejemplo para una cédula uruguaya:

```text
#.###.###-#
```

La interfaz aplica la máscara mientras el usuario escribe, limita la cantidad de números permitidos y guarda únicamente los dígitos en la base de datos.

## Estados y ciudades

La estructura geográfica es:

```text
country
└── state
    └── city
```

El término `state` representa la primera división administrativa de cada país:

- Departamento en Uruguay.
- Provincia en Argentina.
- Estado en Brasil o Estados Unidos.

Los estados pueden importarse desde una API externa y guardarse localmente para evitar depender de Internet durante el uso normal del sistema.

## Usuarios, roles y permisos

La seguridad utiliza un modelo basado en roles:

```text
entity_user
└── entity_user__role
    └── role
        └── role__permission
            └── permission
```

Los permisos utilizan códigos estables, por ejemplo:

```text
CLIENT_VIEW
CLIENT_CREATE
CLIENT_EDIT
CLIENT_DELETE
COUNTRY_VIEW
COUNTRY_CREATE
COUNTRY_EDIT
COUNTRY_DELETE
USER_VIEW
USER_EDIT
```

Los permisos se cargan en la sesión al iniciar sesión.

Ejemplo de validación:

```php
requirePermission('CLIENT_EDIT');
```

Ocultar una opción del menú mejora la experiencia de usuario, pero cada página y cada acción POST deben validar también el permiso correspondiente.

## Autenticación

Las contraseñas deben guardarse mediante `password_hash()`:

```php
$passwordHash = password_hash(
    $password,
    PASSWORD_DEFAULT
);
```

La validación debe realizarse con `password_verify()`:

```php
if (password_verify($password, $user['pwdHash'])) {
    // Inicio de sesión correcto.
}
```

Nunca se deben guardar contraseñas en texto plano.

## Tema claro y oscuro

El proyecto utiliza el soporte de temas de Bootstrap 5 mediante `data-bs-theme`.

La preferencia se guarda en `localStorage`:

```javascript
localStorage.setItem('theme', 'dark');
```

El tema se aplica antes de cargar el contenido para evitar un cambio visual al abrir la página.

## Auditoría

La tabla `data_changes` registra los cambios realizados en las tablas auditadas.

Datos previstos:

- Tabla modificada.
- Campo modificado.
- Identificador del registro.
- Acción realizada.
- Tipo de dato.
- Valor anterior.
- Valor nuevo.
- Fecha.
- Usuario responsable.

La auditoría puede implementarse mediante triggers de MariaDB para registrar cambios realizados tanto por la aplicación como directamente desde otras herramientas.

No deben auditarse valores sensibles como:

- Contraseñas.
- Hashes de contraseña.
- Tokens.
- Identificadores de sesión.

## Convenciones

### Base de datos

- Tablas y columnas en inglés.
- Identificadores numéricos con `BIGINT`.
- Motor `InnoDB`.
- Codificación `utf8mb4`.
- Fechas de creación con `CURRENT_TIMESTAMP`.
- Claves foráneas para preservar integridad referencial.
- Índices en columnas de búsqueda y relación.
- Consultas preparadas mediante PDO.

### PHP

- Código bajo `declare(strict_types=1)` cuando corresponda.
- SQL centralizado en archivos de configuración o repositorios.
- Salida HTML protegida con `htmlspecialchars()`.
- Conversión explícita de identificadores a `int`.
- Transacciones para operaciones que afectan varias tablas.
- Patrón POST/Redirect/GET después de guardar cambios.

### Frontend

- Bootstrap 5.3.
- Bootstrap Icons.
- Formularios responsivos.
- Menú lateral condicionado por permisos.
- Barra superior con usuario logueado y cambio de tema.
- Tooltips inicializados desde el layout común.

## Seguridad

- Utilizar consultas preparadas.
- Validar permisos en el servidor.
- Escapar todos los valores mostrados en HTML.
- Regenerar el identificador de sesión después del login.
- No exponer credenciales en el repositorio.
- No confiar en campos ocultos como mecanismo de autorización.
- Validar identificadores y pertenencia de registros antes de actualizar o eliminar.
- Utilizar POST para operaciones que modifican información.

## Estado del proyecto

Funcionalidades en desarrollo:

- Administración de clientes y entidades.
- Contactos múltiples por entidad.
- Contacto principal por tipo.
- Países y formatos documentales.
- Estados y ciudades.
- Monedas.
- Usuarios, roles y permisos.
- Tema claro y oscuro.
- Auditoría mediante triggers.
- Importación de datos geográficos desde API.

## Próximos pasos

- Completar el alta y edición de entidades.
- Sincronizar contactos al guardar.
- Completar administración de estados y ciudades.
- Incorporar direcciones estructuradas por entidad.
- Crear mantenimiento de usuarios, roles y permisos.
- Implementar protección CSRF en formularios.
- Crear paginación en los listados.
- Agregar filtros y ordenamiento reutilizables.
- Completar triggers de auditoría.
- Añadir pruebas automatizadas.
- Preparar configuración por entorno.

## Licencia

Proyecto de uso privado. Agregar una licencia antes de distribuirlo públicamente.


