# SPEC-0001: Estructura de Arquitectura Base e Infraestructura de Proyecto

- **Fecha:** 2026-08-21
- **Estado:** En Revisión (Esperando Aprobación del Usuario)
- **Módulo:** Infraestructura Base y Arquitectura por Capas

---

## 1. Objetivo

Establecer la estructura física de directorios, configuración de Autoloading PSR-4 con Composer, gestión centralizada de variables de entorno, plantilla `.env.example`, conjunto de archivos de configuración modular en `config/` (`app.php`, `database.php`, `broadcasting.php`, `storage.php`, `sunat.php`, `services.php`), Front Controller `public/index.php`, enrutador inicial `routes/web.php`, suite de comandos `Makefile`, archivo `phpunit.xml`, `phpstan.neon` y la inclusión de archivos `.gitkeep` en todos los directorios del proyecto para garantizar una arquitectura limpia y versionada.

---

## 2. Alcance / Estructura de Directorios

El proyecto se estructurará de forma estricta según el siguiente árbol de archivos:

```text
cs-hotel/
├── public/
│   └── index.php              # Único punto de entrada HTTP (Front Controller)
├── src/
│   ├── Controllers/           # Controladores HTTP
│   ├── Models/                # Entidades DTO / Modelos puros
│   ├── Services/              # Lógica de negocio y servicios
│   ├── Repositories/          # Capa de datos SQL con PDO
│   ├── Database/              # Conexión PDO Singleton
│   └── Support/               # Configuración, Router, Helpers
├── config/
│   ├── app.php                # Configuración general (Env, Debug, Timezone, URL)
│   ├── database.php           # Configuración BD (PDO MySQL estricto)
│   ├── broadcasting.php       # Configuración Tiempo Real (Pusher / Polling Fallback)
│   ├── storage.php            # Configuración Almacenamiento (Cloudflare R2 / Local Fallback)
│   ├── sunat.php              # Configuración SUNAT (Modo Simulado vs Real)
│   └── services.php           # Configuración APIs Externas (API JSONPE DNI/RUC)
├── database/
│   ├── migrations/            # Migraciones SQL/PHP versionadas
│   └── seeds/                 # Datos semilla no-producción
├── routes/
│   └── web.php                # Definición de rutas de la aplicación
├── specs/                     # Especificaciones por módulo
├── views/                     # Plantillas HTML
├── storage/
│   ├── app/
│   │   └── comprobantes/      # Almacenamiento local de comprobantes
│   ├── logs/                  # Archivos de log
│   └── cache/                 # Caché de aplicación
├── tests/
│   ├── Unit/                  # Pruebas unitarias
│   ├── Integration/           # Pruebas de integración BD
│   └── Feature/               # Pruebas E2E de endpoints
├── .env.example               # Plantilla completa de variables de entorno
├── .gitignore                 # Archivos excluidos de git
├── composer.json              # Autoload PSR-4 y dependencias
├── phpunit.xml                # Configuración de pruebas PHPUnit 12
├── phpstan.neon               # Análisis estático nivel 9
├── Makefile                   # Automatización de tareas (make verify)
└── AGENTS.md                  # Constitución del proyecto
```

---

## 3. Reglas de la Arquitectura Base

1. **`declare(strict_types=1);` Mandatory:**
   - La primera línea de cada archivo PHP (después de `<?php`) debe ser `declare(strict_types=1);`.

2. **Composer y Autoloading PSR-4:**
   - `App\` mapea a `src/`.
   - `Tests\` mapea a `tests/`.
   - Prohibido utilizar `require_once` manuales para clases dentro del namespace `App\`.

3. **Front Controller Centralizado (`public/index.php`):**
   - Es el único punto de entrada HTTP del servidor web.
   - Carga el autoloader de Composer (`vendor/autoload.php`).
   - Carga la configuración `.env` a través de `Support\Config`.
   - Inicializa el Router y despacha la petición actual.

4. **Variables de Entorno e Inmutabilidad de Secretos:**
   - Todo acceso a `$_ENV` debe encapsularse en `App\Support\Config`.
   - Ninguna clase lee `$_ENV` o `getenv()` directamente salvo `Support\Config`.
   - El archivo `.env.example` debe mantenerse actualizado con todos los bloques de variables (`APP_*`, `DB_*`, `PUSHER_*`, `STORAGE_*`, `SUNAT_*`, `JSONPE_*`).

5. **Módulos de Configuración (`config/`):**
   - `config/app.php`: Nombre, entorno, zona horaria (`America/Lima`), URL base.
   - `config/database.php`: DSN PDO, charset `utf8mb4`, modo estricto de MySQL.
   - `config/broadcasting.php`: Parámetros de Pusher/Soketi y flag de activación.
   - `config/storage.php`: Driver `local` vs `r2`, credenciales y endpoints S3.
   - `config/sunat.php`: Modo `simulated` vs `real`, credenciales SOL y certificado.
   - `config/services.php`: Token y endpoint para la API JSONPE de consulta DNI/RUC.

6. **Directorios Preservados en Git (`.gitkeep`):**
   - Todos los directorios vacíos que forman parte de la arquitectura del proyecto contienen un archivo `.gitkeep` para ser versionados en Git.

---

## 4. Archivos a Crear en esta Especificación

1. `composer.json` (PHP ^8.4, PSR-4 `App\`, `vlucas/phpdotenv`, `phpunit/phpunit`, `phpstan/phpstan`).
2. `.env.example` (Con todas las variables de app, db, pusher, storage, sunat, jsonpe).
3. `config/app.php`
4. `config/database.php`
5. `config/broadcasting.php`
6. `config/storage.php`
7. `config/sunat.php`
8. `config/services.php`
9. `public/index.php`
10. `routes/web.php`
11. `Makefile` (`make install`, `make lint`, `make analyse`, `make test`, `make verify`)
12. `phpunit.xml`
13. `phpstan.neon`
14. Archivos `.gitkeep` en:
    - `src/Controllers/.gitkeep`
    - `src/Models/.gitkeep`
    - `src/Services/.gitkeep`
    - `src/Repositories/.gitkeep`
    - `src/Database/.gitkeep`
    - `src/Support/.gitkeep`
    - `database/migrations/.gitkeep`
    - `database/seeds/.gitkeep`
    - `views/.gitkeep`
    - `tests/Unit/.gitkeep`
    - `tests/Integration/.gitkeep`
    - `tests/Feature/.gitkeep`

---

## 5. Criterios de Aceptación (Given / When / Then)

### Criterio 1: Estructura Completa de Directorios y Archivos de Configuración Modular
- **Given:** El proyecto recién configurado.
- **When:** Se inspecciona el directorio `config/`.
- **Then:** Existen los archivos `app.php`, `database.php`, `broadcasting.php`, `storage.php`, `sunat.php` y `services.php`, retornando arreglos tipados consumiendo `Support\Config`.

### Criterio 2: Autoloading PSR-4 y Front Controller
- **Given:** Una petición HTTP entrante a `public/index.php`.
- **When:** El Front Controller requiere `vendor/autoload.php` e instancia una clase dentro del namespace `App\Support\Config`.
- **Then:** La clase se carga automáticamente sin errores de `Class Not Found`.

### Criterio 3: Verificación Automática en Verde (`make verify`)
- **Given:** Las herramientas de calidad configuradas.
- **When:** Se ejecuta el comando `make verify`.
- **Then:** Se ejecutan de manera exitosa las fases `make lint` (PHP sintaxis), `make analyse` (PHPStan nivel 9) y `make test` (PHPUnit), finalizando con código de salida 0.

---

## 6. Auditoría y Seguridad

- `.env` debe permanecer ignorado en `.gitignore`.
- Ninguna clave API o secreto real se incluirá en `.env.example` ni en los archivos de configuración.
