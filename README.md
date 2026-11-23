# LuxStay PMS

Sistema de gestión hotelera para tres sucursales. Un solo proyecto Laravel concentra el portal público, las operaciones del personal y la PWA; MySQL es la base de datos compartida.

## Arquitectura y accesos

- `/`: portal público para consultar disponibilidad y enviar pre-reservas. No cobra en línea.
- `/login`: acceso del personal.
- PWA adaptable a escritorio y móvil. La vista operativa se actualiza mediante polling cada 15 segundos; el service worker conserva recursos estáticos y la pantalla offline, no páginas con datos privados.
- La disponibilidad comercial se calcula por sucursal y rango de fechas. El estado físico de una habitación (`limpia`, `sucia`, `en_limpieza`, `mantenimiento`) es independiente.

### Roles

| Rol | Alcance |
| --- | --- |
| Admin | Acceso a todas las sucursales, usuarios, tarifas, tasas de cambio, reportes y resolución de incidencias. |
| Recepción | Reservas, pre-reservas, cobros y estancias de su sucursal; puede consultar housekeeping, pero no modificar tareas de limpieza. |
| Limpieza | Cola y reportes de habitaciones de su sucursal; no puede gestionar reservas, cobros, tarifas ni administración. |

La autorización y el aislamiento por sucursal se validan en servidor, además de ocultar acciones en la interfaz.

## Reglas operativas

- Huéspedes, acompañantes, solicitudes de habitaciones, servicios extra, pagos y estancias están normalizados en relaciones independientes.
- Titular y acompañantes cuentan para la capacidad; el servidor valida solapamientos por documento y calcula precios y totales.
- Los pagos guardan moneda, importe original, tasa aplicada e importe convertido a PEN. La tasa y el total enviados por el cliente no son fuentes confiables.
- Una cancelación con **24 horas o más** genera reembolso pendiente del 50% de lo cobrado; con menos de 24 horas o no-show se registra la penalidad para completar el 100% del total.
- Las reubicaciones conservan las estancias anteriores y ponen la habitación afectada en mantenimiento.
- El check-out se rechaza mientras una habitación activa tenga una alerta o una incidencia de avería pendiente; debe resolverse primero.
- Los gráficos de ingresos se basan en movimientos registrados, netos de reembolsos, consolidados en PEN. El precio cotizado de una reserva no se considera un cobro.

## Desarrollo local en Windows

### Requisitos

- PHP 8.3 o superior y Composer.
- MySQL compatible con la migración del proyecto.
- Node.js y npm.

### Clonar o actualizar

Clonar la rama de trabajo:

```powershell
git clone --branch develop https://github.com/Benyirika/Proyecto_Ingenieria_Web.git luxstay-pms
Set-Location luxstay-pms
```

Para actualizar un clon existente:

```powershell
git switch develop
git pull --ff-only origin develop
```

### Instalar y configurar

```powershell
composer install
npm ci
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
```

Edita `.env` localmente; no lo subas a GitHub. Usa una base de datos local **nueva y exclusiva para desarrollo**, por ejemplo `luxstay_pms_dev`. Configura `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD`. Conserva `APP_DEBUG=false`. Para crear el administrador inicial configura tanto `LUXSTAY_ADMIN_EMAIL` como `LUXSTAY_ADMIN_PASSWORD`; la contraseña debe tener al menos 12 caracteres. No reutilices credenciales de producción.

Genera la clave de la aplicación:

```powershell
php artisan key:generate
```

En una base de desarrollo recién creada, prepara esquema y catálogos:

```powershell
php artisan migrate --seed
```

Este comando aplica todas las migraciones pendientes. **No lo uses con una base legacy o con datos que deban conservarse**: la migración final elimina columnas antiguas. No uses `migrate:fresh`, `migrate:refresh` ni `migrate:rollback` sobre datos compartidos.

### Ejecutar

En terminales separadas:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

```powershell
npm run dev
```

Abre `http://127.0.0.1:8000/` para el portal y `http://127.0.0.1:8000/login` para el personal. Para compilar assets de entrega:

```powershell
npm run build
```

Para ejecutar pruebas:

```powershell
php artisan test
```

La suite automatizada usa SQLite en memoria; no sustituye el ensayo de migraciones sobre una copia de MySQL.

## Migraciones sobre una base legacy

Las migraciones normalizadoras son de avance y la eliminación legacy no tiene rollback seguro. Antes de tocar una base existente:

1. Genera un dump MySQL verificable y restáuralo en una base aislada.
2. Apunta una terminal dedicada exclusivamente a esa copia.
3. Ejecuta **solo** la expansión:

   ```powershell
   php artisan config:clear
   $env:DB_CONNECTION = 'mysql'
   $env:DB_DATABASE = 'luxstay_pms_migration_copy'
   $env:DB_URL = ''
   php artisan migrate --database=mysql --path=database/migrations/2026_11_23_012628_create_normalized_pms_tables.php --force
   ```

4. Verifica los conteos de reservas, huéspedes, habitaciones solicitadas, estancias, pagos y datos legacy; confirma que ningún `total_price` antiguo se haya tratado como cobro.
5. Valida flujos y reportes sobre la copia. Solo con respaldo probado, revisión y autorización explícita se ensaya allí la migración `2026_11_23_012834_drop_legacy_reservation_and_room_columns.php`.
6. La migración destructiva no se aplica a una base real hasta completar la revisión anterior y aprobar una ventana de cambio.

No ejecutes `php artisan migrate` sin limitar el alcance durante esta transición: aplicaría también cualquier migración destructiva pendiente.
