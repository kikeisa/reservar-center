# Reservar Center — Sistema de Reservas

API REST + interfaz web en Laravel para gestión de reservas con lógica de negocio compleja: horarios, festivos colombianos, solapamiento de profesionales y reembolsos según plan del usuario.

## Tecnologías y librerías de interfaz

### SweetAlert2
Librería JavaScript para modales y alertas de alta calidad visual. Se utiliza en toda la interfaz para reemplazar los diálogos nativos del navegador con experiencias ricas e interactivas: confirmaciones de cancelación con preview de reembolso, formularios de creación de reservas con validación en tiempo real, y feedback de éxito o error con mensajes descriptivos. Su API basada en promesas se integra de forma natural con `fetch` para flujos asíncronos sin recargar la página.

> CDN: `https://cdn.jsdelivr.net/npm/sweetalert2@11`

### DataTables
Plugin jQuery para transformar tablas HTML estáticas en grillas interactivas con búsqueda, ordenamiento y paginación del lado del cliente. Se conecta mediante AJAX a los endpoints JSON del servidor, lo que mantiene las vistas limpias y los controladores desacoplados de la presentación. Se usa tanto en el panel del administrador (reservas de todos los usuarios) como en el home del cliente (reservas propias), con columnas personalizadas, badges de estado y botones de acción renderizados dinámicamente.

> CDN: `https://cdn.datatables.net/1.13.7/`

---

## Requisitos

- PHP 8.2+
- Composer
- MySQL 8+

## Instalación

```bash
composer install
cp .env.example .env
php artisan key:generate

# Configurar DB_* en .env, luego:
php artisan migrate
php artisan db:seed
npm install && npm run build
php artisan serve
```

## Credenciales por defecto (seed)

| Email | Contraseña | Rol | Plan |
|---|---|---|---|
| `recarrillomejia89@gmail.com` | `Admin2026#` | super_admin | premium |
| `carlos@example.com` | `password` | cliente | standard |
| `maria@example.com` | `password` | cliente | premium |

---

## Endpoints API

Todos requieren autenticación Sanctum (`Authorization: Bearer {token}`).

| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/api/bookings` | Reservas del usuario (admin: todas) |
| `POST` | `/api/bookings` | Crear reserva |
| `GET` | `/api/bookings/{id}` | Detalle de una reserva |
| `DELETE` | `/api/bookings/{id}` | Cancelar reserva |
| `GET` | `/api/bookings/{id}/refund` | Preview del reembolso |

### Crear reserva — body JSON

```json
{
  "service_id": 1,
  "starts_at": "2026-06-15 09:00:00"
}
```

## Rutas web del cliente

| Ruta | Descripción |
|---|---|
| `GET /client/services` | Lista de servicios disponibles (JSON) |
| `GET /client/bookings` | Reservas del usuario (JSON) |
| `POST /client/bookings` | Crear reserva |
| `GET /client/bookings/{id}/refund` | Preview reembolso |
| `DELETE /client/bookings/{id}` | Cancelar reserva |

## Rutas web del administrador

| Ruta | Descripción |
|---|---|
| `GET /admin/dashboard` | Panel de usuarios |
| `GET /admin/reservations` | Panel de todas las reservas |
| `GET /admin/reservations/list` | JSON para DataTable |
| `DELETE /admin/reservations/{id}` | Cancelar reserva desde admin |

---

## Reglas de negocio

**Horarios válidos:** Lunes–Sábado, 07:00–19:00 hora Bogotá (`America/Bogota`).
Sin domingos, sin festivos Colombia 2026.
Mínimo **2 horas de anticipación** desde el momento de la reserva.
Máximo **3 reservas activas futuras** por usuario.
El profesional no puede tener solapamiento de horarios.

**Matriz de reembolso:**

| Tiempo antes del inicio | Estándar | Premium |
|---|---|---|
| > 24 horas | 100% | 100% |
| 4h – 24h | 50% | 100% |
| 1h – 4h | 0% | 50% |
| < 1 hora | 0% | 0% |
| Servicio `non_refundable` | 0% | 0% |

---

## Tests

```bash
# Suite completa
php artisan test

# Solo unitarios con detalle
./vendor/bin/phpunit --testdox
```

**15 tests — 26 assertions** (todos en verde)

| Suite | Tests |
|---|---|
| `Unit/BookingServiceTest` | 8 tests de lógica pura |
| `Feature/AdminBookingTest` | 5 tests de acceso admin vs cliente |
| `Feature/ExampleTest` | 1 smoke test |
| `Unit/ExampleTest` | 1 smoke test |

---

## Estructura de archivos

```
app/
  Models/
    User.php                        # plan: standard|premium, role: cliente|super_admin
    Service.php                     # professional_id, non_refundable
    Reservation.php                 # starts_at, ends_at, status, refund_amount
  Services/
    BookingService.php              # Toda la lógica de negocio
  Http/Controllers/
    BookingController.php           # API REST (5 endpoints)
    AdminReservationController.php  # Panel admin de reservas
    ClientBookingController.php     # Endpoints web del cliente
    UserController.php              # CRUD de usuarios (admin)
database/
  migrations/
    ..._create_users_table.php
    ..._add_role_to_users_table.php
    ..._add_plan_to_users_table.php
    ..._create_services_table.php
    ..._create_reservations_table.php
    ..._create_personal_access_tokens_table.php
  seeders/
    seed.json                       # Datos con inconsistencias intencionales
    DatabaseSeeder.php              # Lee seed.json y normaliza tipos
  factories/
    UserFactory.php
    ServiceFactory.php
    ReservationFactory.php
resources/views/
  layouts/app.blade.php             # Layout base con nav admin/cliente
  home.blade.php                    # Panel del cliente (DataTable + SweetAlert2)
  admin/
    dashboard.blade.php             # Gestión de usuarios
    reservations.blade.php          # Gestión de reservas (admin)
routes/
  web.php                           # Rutas web (auth, admin, client)
  api.php                           # Rutas API Sanctum
tests/
  Unit/BookingServiceTest.php       # 8 tests unitarios
  Feature/AdminBookingTest.php      # 5 tests de feature
```

---

## Historial de cambios

### v0.4 — 2026-05-16
**Panel cliente con agenda interactiva**
- Nueva vista `home.blade.php` con DataTable de reservas personales
- Modal SweetAlert2 para crear reservas con validación en tiempo real (domingo, festivo, horario 7–19, anticipación 2h)
- Preview de reembolso en COP antes de confirmar cancelación
- Contador de cupos activos (0–3) con indicador de color
- Nuevo `ClientBookingController` con 5 endpoints web bajo `/client/*`
- Rutas web `/client/services`, `/client/bookings`, `/client/bookings/{id}/refund`

### v0.3 — 2026-05-16
**Módulo de reservas en panel administrador**
- Nueva vista `admin/reservations.blade.php` con DataTable (fechas en hora Bogotá, montos en COP)
- `AdminReservationController` — lista todas las reservas con usuario y servicio, cancela con cálculo de reembolso
- Rutas `/admin/reservations/*` bajo el middleware `is_admin`
- Link **Reservas** en el navbar del admin con resaltado activo
- `GET /api/bookings` bifurcado: admin recibe todas las reservas + datos de usuario; cliente solo las suyas
- 5 tests de feature en `AdminBookingTest`

### v0.2 — 2026-05-16
**Módulo de reservas completo**
- 3 migraciones: columna `plan` en users, tabla `services`, tabla `reservations`
- Modelos `Service` y `Reservation` con relaciones y casts
- `BookingService` con lógica completa: festivos Colombia 2026, horario Bogotá, solapamiento, límite de 3 activas, matriz de reembolso
- `BookingController` con 5 endpoints REST API
- `routes/api.php` registrado en `bootstrap/app.php`
- Factories `ServiceFactory` y `ReservationFactory`
- `seed.json` con inconsistencias intencionales + `DatabaseSeeder` que normaliza tipos
- `NOTAS.md` con decisiones técnicas
- 8 tests unitarios en `BookingServiceTest` (sin dependencia HTTP)
- Instalación y configuración de Laravel Sanctum

### v0.1 — base inicial
- Proyecto Laravel 12 con autenticación (`laravel/ui`)
- Modelo `User` con roles `super_admin` / `cliente`
- Middleware `is_admin` para rutas protegidas
- Panel admin de usuarios (CRUD) con DataTable y SweetAlert2
- Migraciones base: users, cache, jobs, sessions, role
