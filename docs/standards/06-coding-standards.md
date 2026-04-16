# Coding Standards

## Purpose
This document defines how production-grade implementation must be written once application coding begins. It exists to ensure quality, safety, and maintainability across a parallel team.

## Core Philosophy
The platform must be built with:
- thin orchestration at the edges
- explicit business rules in the middle
- low coupling between domains
- traceable state changes
- predictable validation and authorization

## Controller Standard
Controllers must:
- accept requests
- delegate to requests, actions, services, or queries
- return responses or views
- remain short and readable

Controllers must not:
- contain pricing engines
- contain payment verification logic
- contain raw authorization hacks
- build large data transformations that belong in dedicated classes

## Validation Standard
All write operations must validate input explicitly.

Validation rules:
- use dedicated request classes for external input
- validate files, enums, booleans, numeric ranges, and relationships
- reject unsafe or ambiguous state transitions
- treat client-submitted totals, statuses, or stock assumptions as untrusted

## Authorization Standard
All protected actions must be checked through:
- policies
- role or permission checks
- ownership rules

Never rely only on:
- hidden UI buttons
- JavaScript restrictions
- route obscurity

## Truth Table Standard
Truth tables are mandatory for complex branching decisions, especially in:
- checkout eligibility
- payment status handling
- order transition rules
- support escalation rules
- recommendation confidence gating

A truth-table-driven rule should be:
- documented in `artifacts/`
- represented clearly in domain logic
- tested for expected and denied states

## Pricing And Checkout Standard
Pricing must be server-authoritative.

Rules:
- never trust client totals
- recompute all totals server-side at checkout
- validate stock at the time of purchase
- persist snapshots for financial consistency
- separate payment initiation from payment verification

## Payment Standard
Payment handling must:
- use a gateway abstraction
- verify provider callbacks server-side
- remain idempotent
- log state changes clearly
- avoid storing sensitive payment credentials locally

## Realtime Standard
Realtime updates are supplementary, not authoritative.

Rules:
- live UI should reflect server events
- order truth must still exist in persistent storage
- broadcasts must be authorized and scoped
- a broadcast failure must not break a purchase flow

## UI Standard
The customer experience must feel premium and deliberate.

Rules:
- design with reusable components
- avoid inconsistent spacing and typography
- ensure desktop and mobile support
- communicate state clearly for stock, payment, and order progress
- favor calm clarity over noisy dashboards

## Blade Standard
Views should:
- remain presentational
- consume prepared data structures
- use reusable components where repetition starts
- avoid embedding business decisions that belong in services or policies

## JavaScript Standard
Client scripts should:
- enhance UX
- subscribe to realtime events
- support dynamic interactions such as filters, previews, and support chat

Client scripts must not:
- authoritatively decide pricing
- bypass server validation
- carry secrets

## Logging Standard
Log events that matter operationally:
- auth lockouts
- payment verification results
- order state transitions
- support escalation triggers
- admin overrides

Do not:
- log secrets
- log raw payment credentials
- log unnecessary personal data

## Testing Standard
Every critical flow requires tests.

Minimum critical test domains:
- auth
- cart and checkout
- payment verification
- order transitions
- support access control
- recommendation scoring sanity

Bug-fix rule:
- every resolved bug must add or update the test that would have caught it

## File Size And Modularity
Prefer roughly 100 to 300 lines per file when practical.

If a file grows beyond that range, ask:
- can orchestration be split from business logic
- can formatting or transformation be extracted
- can a reusable concern be isolated

## Secure Output Standard
All user-facing output must be:
- safely escaped by default
- sanitized if rich text is allowed
- reviewed for injection risk

## Review Standard
Code is not production-ready unless reviewers can answer:
- where is the business rule located
- where is validation enforced
- where is authorization enforced
- what would fail safely
- how would this be tested
