# Ysabelle Store Platform

Laravel 12 backend foundation for Ysabelle Store, a premium retail shoe and operations platform.

## Current State

This repository is now a structured foundation rather than a stock Laravel starter:

- separated route boundaries for `storefront`, `auth`, `admin`, and `api`
- domain-oriented models for access, catalog, inventory, cart, orders, payments, shipping, discounts, and audit logs
- dedicated controllers, requests, resources, services, and policies
- baseline retail schema and seeders for future feature implementation
- Blade placeholder screens that reflect actual system boundaries instead of the default Laravel welcome page

Business features are not fully implemented yet. The codebase is prepared for them structurally.

## Quick Start

1. Install dependencies:
   - `composer install`
   - `npm install`
2. Create environment:
   - copy `.env.example` to `.env`
   - configure MariaDB credentials
3. Generate app key:
   - `php artisan key:generate`
4. Run schema and seeders:
   - `php artisan migrate`
   - `php artisan db:seed`
5. Link storage:
   - `php artisan storage:link`
6. Start local development:
   - `composer dev`

## Developer Entry Points

- `routes/storefront.php`: public storefront route surface
- `routes/auth.php`: auth-facing route surface
- `routes/admin.php`: admin-only route surface
- `routes/api.php`: API v1 route surface
- `app/Http/Controllers`: access-area controllers
- `app/Models`: domain models
- `app/Services`: business-oriented query and dashboard services
- `app/Policies`: authorization boundaries
- `database/migrations`: retail schema foundation
- `database/seeders`: baseline operational seeders

## Testing

Run the automated test suite with:

- `php artisan test`

The test environment uses SQLite in memory and does not require the local MariaDB connection.
