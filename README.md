# Neighborhood Management App

Sistema de administracion para barrios privados/consorcios, desarrollado con Laravel 12 + Inertia + Vue 3.

Permite gestionar propietarios, lotes, expensas, pagos, metodos de cobro, egresos y reportes contables, incluyendo estado individual por propietario con filtro por periodo o por rango de fechas e impresion.

## Stack

- Backend: PHP 8.2+, Laravel 12
- Frontend: Vue 3, Inertia.js, Vite, Tailwind CSS
- DB: MySQL (configurable)
- Auth: Laravel Breeze
- Iconos: lucide-vue-next

## Funcionalidades principales

- Seleccion de barrio activo por sesion.
- Gestion de lotes (UF), propietarios y residentes.
- Generacion masiva de expensas:
  - CC1: monto fijo por unidad.
  - CC2: reparto proporcional por coeficiente/superficie.
- Registro de pagos de expensas e imputacion por periodo.
- Carga de extraordinarias y multas manuales.
- Aplicacion automatica de interes por mora (comando programado).
- Gestion de egresos y movimientos bancarios.
- Rendicion mensual imprimible.
- Nuevo: estado individual por propietario:
  - filtro por periodo (`Y-m`) o rango de fechas (`Y-m-d`),
  - resumen de cargos/pagos/saldo,
  - impresion en formato reporte.

## Estructura funcional (resumen)

- `app/Http/Controllers/Dashboard`
  - `ExpensesController.php`
  - `PaymentsController.php`
  - `OwnerStatementController.php` (nuevo)
- `app/Services/ExpenseGeneratorService.php`
- `app/Console/Commands/ApplyLateInterestCommand.php`
- `resources/js/Pages`
  - `Expenses/Index.vue`
  - `Payments/Index.vue`
  - `Reports/OwnerStatements.vue` (nuevo)
- `resources/views/reports`
  - `monthly-reconciliation.blade.php`
  - `owner-statement.blade.php` (nuevo)
- `routes/web.php`
- `routes/console.php`

## Requisitos

- PHP 8.2+
- Composer
- Node.js 18+
- npm
- MySQL 8+ (o compatible)

## Instalacion local

1. Clonar el proyecto.
2. Instalar dependencias backend:
   - `composer install`
3. Crear entorno:
   - `copy .env.example .env` (Windows) o `cp .env.example .env`
4. Configurar `.env` (DB, APP_URL, etc.).
5. Generar key:
   - `php artisan key:generate`
6. Ejecutar migraciones:
   - `php artisan migrate`
7. (Opcional) Seed inicial:
   - `php artisan db:seed`
8. Instalar frontend:
   - `npm install`
9. Desarrollo:
   - `composer run dev`
   - o en terminales separadas:
   - `php artisan serve`
   - `npm run dev`

## Configuracion relevante

- Zona horaria app:
  - `.env` -> `APP_TIMEZONE=America/Argentina/Buenos_Aires`
- Tasa base de mora CC2:
  - `.env` -> `CC2_CONSTRUCTION_INDEX_RATE=0.03`
  - `config/fines.php`

## Tareas programadas (mora)

Definidas en `routes/console.php`:

- Dia 11, 00:00: aplica mora dia 10 (CC1)
- Dia 16, 00:00: aplica mora dia 15 (CC2)
- Dia 21, 00:00: aplica mora dia 20 (CC1)

Para que corra en produccion, configurar scheduler:

- `php artisan schedule:run` cada minuto (cron del servidor/plataforma).

## Estado individual por propietario (nuevo)

Menu: `Estado por Propietario`

Ruta:

- `GET /owner-statements`
- `GET /owner-statements/print`

Permite:

- Seleccionar propietario.
- Filtrar:
  - Por periodo (`period_from` a `period_to`).
  - Por rango de fechas (`date_from` a `date_to`).
- Ver:
  - cargos por periodo (mensual, extraordinaria, multas),
  - pagos imputados,
  - saldo pendiente.
- Imprimir reporte en nueva pestaña.

## Comandos utiles

- Ejecutar tests:
  - `php artisan test`
- Limpiar caches:
  - `php artisan optimize:clear`
- Ver rutas:
  - `php artisan route:list`
- Aplicar mora manual (ejemplo):
  - `php artisan expenses:apply-late-interest --day=15 --period=2026-02`

## Build para produccion

- `npm run build`
- `php artisan config:cache`
- `php artisan route:cache`
- `php artisan view:cache`

## Storage y archivos (vouchers/comprobantes)

El sistema utiliza el disco `public` para archivos como comprobantes:

- subida: `storage/app/public/...`
- URL publica: `/storage/...`

### Entorno local

Crear enlace simbolico:

- `php artisan storage:link`

Esto crea `public/storage` apuntando a `storage/app/public`.

### Laravel Cloud

En Laravel Cloud no conviene depender de `storage:link` en disco local efimero para archivos persistentes.

Recomendado:

1. Usar almacenamiento externo (S3 compatible) para persistencia real.
2. Configurar en `.env`:
   - `FILESYSTEM_DISK=s3`
   - `AWS_ACCESS_KEY_ID=...`
   - `AWS_SECRET_ACCESS_KEY=...`
   - `AWS_DEFAULT_REGION=...`
   - `AWS_BUCKET=...`
3. Ejecutar deploy.

Si temporalmente usas disco local dentro del contenedor, debes asegurar:

- que `php artisan storage:link` se ejecute en build/release hook,
- y entender que al recrear instancias podrias perder archivos locales.

## Seguridad y buenas practicas

- No commitear `.env` ni credenciales.
- Validar tamano/tipo de archivos de comprobantes.
- Restringir acceso por `neighborhood_id` (ya aplicado en controladores clave).
- Auditar periodicamente permisos y datos sensibles.

## Licencia

Proyecto interno. Ajustar licencia segun politica del equipo/cliente.
