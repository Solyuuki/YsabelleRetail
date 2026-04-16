# Folder Map

## Purpose
This document defines approved repository zones, guarded areas, and ownership-sensitive locations. It is intended to prevent random file placement, accidental framework disruption, and cross-team collisions.

## Top-Level Repository Map
- `.github/`
  CI, workflow, template, and repository automation.
- `app/`
  Application runtime code. High-sensitivity area. Do not touch without sprint scope.
- `bootstrap/`
  Application bootstrap and runtime startup configuration. Shared-risk area.
- `config/`
  Framework and application configuration. Shared-risk area.
- `database/`
  Migrations, factories, seeders. High-impact area.
- `public/`
  Public entrypoint and compiled public assets.
- `resources/`
  Views, CSS, JavaScript. Shared UI area.
- `routes/`
  Web, console, and broadcast route declarations. Shared-risk area.
- `storage/`
  Runtime files. Not a source-of-truth directory for implementation.
- `tests/`
  Automated tests.
- `vendor/`
  Dependency code. Never edit directly.
- `node_modules/`
  Dependency code. Never edit directly.
- `standards/`
  Governance and implementation standards.
- `artifacts/`
  Traceability, decision records, feature documents, and flow assets.

## Approved Documentation Zones
During documentation-only work, only these top-level directories may be created or changed:
- `standards/`
- `artifacts/`

## Guarded Runtime Zones
The following folders are high-risk because they affect the actual application runtime:
- `app/`
- `bootstrap/`
- `config/`
- `database/`
- `public/`
- `resources/`
- `routes/`
- `tests/`

Changes in these areas must always be:
- sprint-scoped
- ownership-approved
- reviewed for security and regression risk

## Prohibited Direct-Edit Zones
These directories are never edited manually:
- `vendor/`
- `node_modules/`
- generated cache files under `storage/`

## Shared Collision Files
These are the most common merge-conflict files and must be treated as shared-risk surfaces:
- `.env.example`
- `bootstrap/app.php`
- `routes/web.php`
- `routes/channels.php`
- `app/Models/User.php`
- `resources/css/app.css`
- `resources/js/app.js`
- `config/services.php`

## Documentation Substructure Standard
Approved documentation structure:
- `standards/`
  numbered governance documents
- `artifacts/auth/`
- `artifacts/product/`
- `artifacts/cart/`
- `artifacts/checkout/`
- `artifacts/payment/`
- `artifacts/orders/`
- `artifacts/websocket/`
- `artifacts/chatbot/`
- `artifacts/recommendation/`
- `artifacts/security/`
- `artifacts/support/`
- `artifacts/flowcharts/`

## Placement Rules
- Standards files must stay inside `standards/`.
- Feature documentation must stay inside the correct `artifacts/<domain>/` directory.
- Flowcharts belong in `artifacts/flowcharts/`.
- A document must not mix multiple unrelated domains without a clear cross-domain purpose.

## Folder Naming Rules
- Use lowercase folder names.
- Use hyphenated Markdown filenames.
- Use fixed numbering for standards files.
- Avoid temporary folders like `misc`, `random`, `notes`, or `draft-final-final`.

## Documentation Classification
Every documentation file belongs to one of these types:
- governance
- feature artifact
- decision record
- flowchart
- risk register
- acceptance document

If a file does not fit any of these types, it should not be created.

## Repository Safety Rule
Folder placement is not cosmetic. Incorrect placement creates ownership confusion, review gaps, and deployment risk. A contributor must always choose the narrowest correct directory rather than a convenient one.
