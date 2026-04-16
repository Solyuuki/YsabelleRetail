# Team Ownership

## Purpose
This document establishes ownership boundaries so contributors can move quickly without damaging parallel work.

Ownership does not mean one person can change anything inside a zone without review. Ownership means that a team or role is the default accountable reviewer and decision-maker for that area.

## Ownership Model
Use three levels of ownership:
- primary owner
- required reviewer
- consulted reviewer

## Domain Ownership Map
### Accounts
- Primary owner: authentication and identity team
- Required reviewer: security reviewer
- Consulted reviewer: UX reviewer for customer-facing auth flows

### Catalog
- Primary owner: catalog and merchandising team
- Required reviewer: admin experience owner
- Consulted reviewer: recommendation owner when style metadata changes

### Cart And Checkout
- Primary owner: commerce flow team
- Required reviewer: security reviewer for high-risk changes
- Consulted reviewer: payments owner

### Payments
- Primary owner: payments owner
- Required reviewer: security reviewer
- Consulted reviewer: finance operations reviewer

### Orders
- Primary owner: order operations team
- Required reviewer: support owner when customer-facing status changes
- Consulted reviewer: payments owner if transitions depend on payment events

### Notifications And Realtime
- Primary owner: platform integration owner
- Required reviewer: security reviewer for private channel changes
- Consulted reviewer: support owner for support-room broadcasting

### Support
- Primary owner: support systems owner
- Required reviewer: security reviewer when customer data exposure changes
- Consulted reviewer: orders owner

### Recommendation
- Primary owner: recommendation and merchandising owner
- Required reviewer: product owner
- Consulted reviewer: catalog owner

### Admin Experience
- Primary owner: internal operations UX owner
- Required reviewer: security reviewer for privileged actions
- Consulted reviewer: relevant domain owner

## Shared File Ownership
These files are shared-risk and require extra care:
- `.env.example`
  Primary owner: platform architecture owner
- `bootstrap/app.php`
  Primary owner: platform architecture owner
- `routes/web.php`
  Primary owner: platform architecture owner
- `app/Models/User.php`
  Primary owner: accounts owner
- `resources/css/app.css`
  Primary owner: design system owner
- `config/services.php`
  Primary owner: platform integration owner

## Documentation Ownership
### Standards
- Primary owner: architecture owner
- Required reviewer: security reviewer

### Artifacts
- Primary owner: feature domain owner
- Required reviewer: architecture owner for structure
- Consulted reviewer: product owner when business acceptance changes

## Review Triggers
The following changes always require review from more than one role:
- authentication changes
- payment changes
- order state machine changes
- admin permissions changes
- support visibility changes
- realtime channel authorization changes
- environment contract changes

## Escalation Rules
Escalate before changing scope when:
- a change touches multiple domains
- a shared file must change
- the change affects financial correctness
- the change introduces a new secret or external integration
- the change can lock out users or expose customer data

## Accountability Rule
If ownership is unclear, work is not ready to start. Clarify ownership before implementation begins.
