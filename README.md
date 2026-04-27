# Ysabelle Store Platform

Laravel 12 ecommerce demo platform for Ysabelle Retail Shop with a polished storefront, admin back office, shared inventory, walk-in POS, branded reports, and simulated checkout flows.

## Current State

This repository now includes:

- separate route boundaries for `storefront`, `auth`, `admin`, and `api`
- Laravel-native services, requests, policies, middleware, events, and listeners for core commerce flows
- realistic local demo seed data for catalog, customers, online orders, walk-in sales, stock movements, and reports
- unified stock management with manual updates, batch imports, movement history, and low-stock visibility
- branded CSV, PDF, and XLSX report exports
- admin realtime activity alerts using Laravel events plus a safe polling fallback

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

## Demo Accounts

- Admin: `admin@ysabelle.store` / `Password123x`
- Customer: `customer@ysabelle.store` / `Password123x`

These local demo credentials are only seeded in the `local` environment.

## Realtime Demo

The admin dashboard uses Laravel events and listeners to create a live activity feed. The frontend refreshes it through a polling fallback, so no paid websocket service is required.

- App server: `php artisan serve`
- Frontend assets: `npm run dev`
- Full guardrail: `composer check`

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
- `composer check`

The test environment uses SQLite in memory and does not require the local MariaDB connection.
