# Folder Guide

## Purpose
This guide explains what belongs in each approved directory and what must stay out. It exists to keep the codebase modular, short-file oriented, and safe for parallel contributors.

## Standards Directory
`standards/` contains governance-level instructions.

What belongs here:
- project rules
- architecture and delivery guidance
- naming rules
- environment contracts
- merge protection guidance
- edge-case policies

What does not belong here:
- feature implementation notes tied to one module
- release-specific temporary notes
- test logs
- personal reminders

## Artifacts Directory
`artifacts/` contains traceability and delivery support documents for feature areas.

What belongs here:
- artifact inventories
- truth-table references
- state maps
- feature-level acceptance criteria
- threat models
- service flow diagrams
- operational decision records

What does not belong here:
- source code
- package installation commands
- throwaway brainstorming without ownership

## Runtime Application Directories
This section is included for governance completeness. During documentation-only work, these folders are not to be edited.

### `app/`
Contains runtime PHP logic.

Expected categories in implementation phase:
- controllers for orchestration only
- requests for validation
- actions for focused business operations
- services for domain logic
- queries for read-side aggregation
- events, listeners, notifications
- policies and authorization logic
- support helpers that are domain-safe

What must not happen:
- giant controller files
- pricing logic duplicated in multiple modules
- payment verification embedded in views
- hidden cross-domain coupling

### `routes/`
Contains HTTP, console, and broadcast route declarations.

Allowed implementation direction later:
- route definitions should stay shallow
- route files should delegate logic to controllers

What must not happen:
- inline business logic
- route-level hacks that bypass policies or validation

### `database/`
Contains migrations, factories, and seeders.

What belongs there in implementation phase:
- reversible migrations
- domain-scoped factories
- seeded baseline reference data only

What must not happen:
- one migration handling multiple unrelated domains
- irreversible destructive data changes without explicit review

### `resources/`
Contains Blade views, CSS, and JavaScript.

What belongs there later:
- layouts
- components
- page views
- modular client-side behavior
- design tokens and reusable styling primitives

What must not happen:
- application secrets
- server-authoritative business rules
- duplicated page logic across views

### `config/`
Contains application configuration.

What belongs there later:
- runtime-safe configuration maps
- environment-driven settings

What must not happen:
- hardcoded secrets
- business logic masquerading as configuration

## Future Modular Folder Strategy
When implementation starts, the preferred organization is a modular monolith. Each domain should have bounded folders that keep files short and responsibilities obvious.

Recommended domains:
- Accounts
- Catalog
- Cart
- Checkout
- Payments
- Orders
- Notifications
- Support
- Recommendation
- Admin
- Shared

## Short-File Rule
Every folder strategy must support this repository rule:
- prefer roughly 100 to 300 lines per file where practical
- split responsibilities before files become difficult to review

## Decision Placement Rules
Place work according to responsibility:
- governance rule: `standards/`
- feature traceability document: `artifacts/`
- executable application behavior: runtime folders during implementation phase

If uncertain, ask:
- is this enforcing a team rule
- is this documenting a feature area
- is this executable runtime behavior

Then place it accordingly.

## Forbidden Folder Patterns
Do not create:
- `tmp/`
- `misc/`
- `scratch/`
- `old/`
- `backup/`
- `final-final/`

If content is worth keeping, it needs a real home. If it is not worth keeping, it should not be committed.
