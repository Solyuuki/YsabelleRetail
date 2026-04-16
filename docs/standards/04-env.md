# Environment Standards

## Purpose
This document defines how environment variables are named, grouped, secured, reviewed, and deployed for Ysabelle Retail Shop.

Environment configuration is part of the production contract. Poor environment discipline causes security leaks, deployment drift, and runtime confusion.

## Core Principles
- secrets must never be committed
- environment names must be stable and descriptive
- local, staging, and production must share the same key contract whenever possible
- environment values must configure behavior, not replace application logic
- unsafe defaults must not be shipped to production

## Environment Categories
All environment variables should belong to one of these groups:
- application
- database
- session and cache
- queue and broadcasting
- mail
- storage
- payment
- support and chatbot
- recommendation and media processing
- observability and security

## Required Grouping Order
When environment files are authored or revised, values should be grouped in this order:
1. App identity
2. Locale and maintenance
3. Logging
4. Database
5. Session and cache
6. Queue and broadcasting
7. Mail
8. Storage
9. Payment
10. WebSocket and realtime
11. Support and AI integrations
12. Security and observability

## Naming Rules
- use uppercase snake case only
- prefix domain-specific variables clearly
- use stable names that can survive implementation refactors
- avoid vague names like `KEY`, `TOKEN`, `HOST2`

Examples of acceptable naming:
- `PAYMENT_PROVIDER`
- `PAYMONGO_SECRET_KEY`
- `PAYMONGO_WEBHOOK_SECRET`
- `REVERB_APP_ID`
- `SUPPORT_CHAT_PROVIDER`
- `CHATBOT_ENABLED`
- `RECOMMENDATION_MAX_UPLOAD_MB`

## Secrets Policy
The following values are considered secrets and must never appear in committed source:
- app secret keys
- database passwords
- queue and cache passwords
- provider secret keys
- webhook verification secrets
- API bearer tokens
- SMTP passwords

## Local Environment Rules
Local development may use:
- development-safe hostnames
- sandbox payment credentials
- local mail capture tools
- non-production logging levels

Local development must not use:
- live payment keys
- production webhook endpoints
- shared production credentials

## Staging Environment Rules
Staging should resemble production in structure but not in financial impact.

Staging should use:
- staging domains
- sandbox payment modes
- realistic queue and websocket behavior
- review-friendly log levels

## Production Environment Rules
Production must enforce:
- strong secrets
- secure cookies
- production logging channels
- queue workers and broadcast servers aligned with scaling needs
- live provider keys only through managed secret stores

## Documentation Requirements
Every new environment key must be documented with:
- purpose
- acceptable values or shape
- whether it is required
- whether it is secret
- default behavior when absent

## Example Key Registry Categories
### Payment
- provider identifier
- public key or publishable key if needed
- secret key
- webhook secret
- callback URLs if environment-sensitive

### Realtime
- broadcast driver
- websocket app identifiers
- host and port settings
- TLS expectations

### Support And AI
- support provider identifier
- chatbot enable flag
- AI model identifier if future integration exists
- knowledge-base source toggle

### Security And Observability
- trusted proxy settings
- session hardening flags
- audit log switch
- error reporting DSN

## Review Checklist For Environment Changes
- Is the key necessary
- Is the name clear and future-safe
- Is it documented
- Is the value secret
- Does it affect only one domain
- Does it introduce deployment coupling
- Does it require infrastructure coordination

## Anti-Patterns
Do not:
- commit secrets
- overload one variable for multiple purposes
- create environment flags to bypass core validation or authorization
- add undocumented keys
- store business rules directly in environment values when the logic belongs in code
