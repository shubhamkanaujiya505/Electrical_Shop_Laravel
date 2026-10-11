# Electrical Shop backend

Laravel API backend for the Electrical Shop SaaS platform. Angular runs from the separate D:\xampp\htdocs\Electrical_Shop_Angular folder.

## Local setup

- PHP 8.2 or newer and Composer.
- Start MySQL using XAMPP. Database: electrical_shop_dev.
- On a fresh checkout copy .env.example to .env and set local database credentials.
- Run `composer install`, `php artisan key:generate` (fresh setup only), then `php artisan migrate`.
- Run `php artisan serve --host=127.0.0.1 --port=8010` or `composer dev`.
- Start Angular using `npm start` in the frontend folder. Open http://localhost:4210.

The current local connection uses XAMPP root with an empty password. Use a dedicated account and separate credentials when deploying. Keep .env private. Never overwrite an existing application key during normal restarts.

## Structure

- routes/api.php: routes with the /api prefix.
- app/Http/Controllers: request coordination.
- app/Models: data and relationships.
- config/cors.php: explicit frontend origins.
- database/migrations: versioned database changes.
- tests/Feature: API behavior and critical checks.
- docs/PHASES.md: phase tracking and verification evidence.

Add request validation classes and small business services as features need them. Keep tenant and stock rules consistent.

## Verify

- /up checks application boot.
- /api/health checks the database and returns a safe 503 response when unavailable.
- `php artisan test` uses in memory SQLite without resetting local MySQL data.
- `php vendor/bin/pint --test` checks formatting.

Angular has a development proxy. Production hosting must also route /api to Laravel, use HTTPS and disable debug mode.

## Owner account (Phase 2A/2B)

Open Angular and choose Create shop to register a shop and owner together. Sign in opens the protected dashboard. Owners can edit Shop profile and manage staff; staff can view the profile and edit it only when permission is granted.

- `GET /api/auth/csrf`: establishes session and CSRF cookies.
- `POST /api/auth/register`: name, email, password, password_confirmation, shop_name and optional shop_phone.
- `POST /api/auth/login`: email and password.
- `GET /api/auth/me`: current owner and shop.
- `POST /api/auth/logout`: invalidates the current session.
- `GET /api/shop`, `PATCH /api/shop`: current shop profile; update fields are name, phone and address.

Account routes use Laravel's web middleware for session and CSRF protection and return JSON. The frontend first requests the CSRF endpoint, then sends the `X-XSRF-TOKEN` header on mutations. Serve frontend and `/api` on the same origin in production, as the development proxy does locally. Do not configure cross-origin cookie authentication without revisiting this contract.

Shop identity and role come from the authenticated account. Requests cannot choose another tenant or elevate a role. Registration is transactional; login is rate limited. Tests cover two-shop isolation for the implemented profile endpoints. Future inventory and billing endpoints must enforce the same tenant rules.

## Staff access (Phase 2C)

- `GET /api/staff?page=1`: owner-only, 20 staff accounts per page from the current shop.
- `POST /api/staff`: name, email, password, password_confirmation and permissions (empty array by default; only `shop.update` can be granted).
- `PATCH /api/staff/{id}`: is_active and permissions. Target must be a staff account in the owner's shop.

`/api/auth/me` and successful registration/login responses include a permissions array. Owners receive `shop.update` and `staff.manage`; staff receive only their granted profile-edit permission. Middleware checks both active shop membership and the required permission. Staff can read their shop without an edit grant. Disabling an account takes effect on its next protected request. There is no staff deletion, owner-role change or credential reset endpoint in this phase.

## Framework choices

Laravel 12 was selected for the installed PHP 8.2 runtime. Angular 21 works with the installed Node 24 runtime. Angular 22 was assessed first; Angular 21 was chosen after Windows Application Control blocked the newer native build tools. Recheck framework and runtime support before production deployment.

- [Laravel release support](https://laravel.com/docs/12.x/releases)
- [Angular version compatibility](https://angular.dev/reference/versions)
