# Security Artifacts

## Domain
Threat identification, abuse prevention, authorization controls, secure operations, and production hardening

## Artifact Inventory

### 1. Threat Model
- Purpose:
  identify the highest-risk threats across auth, checkout, payment, admin, support, uploads, and realtime
- Owner:
  security reviewer
- Format:
  threat model document
- Related sprint:
  Sprint 6
- Related modules:
  all domains
- Completion criteria:
  top threats, impacts, mitigations, and ownership are documented

### 2. Sensitive Action Register
- Purpose:
  catalog actions that require elevated review, auditability, or strict authorization
- Owner:
  security reviewer
- Format:
  action register
- Related sprint:
  Sprint 1 and Sprint 6
- Related modules:
  Accounts, Payments, Orders, Admin, Support
- Completion criteria:
  sensitive actions are visible and have defined protection expectations

### 3. Abuse Case Inventory
- Purpose:
  define abuse patterns such as brute force, promo abuse, checkout tampering, support spam, and upload abuse
- Owner:
  security reviewer
- Format:
  abuse inventory
- Related sprint:
  Sprint 6
- Related modules:
  Accounts, Cart, Checkout, Support, Recommendation
- Completion criteria:
  abuse cases have prevention or containment expectations

### 4. Release Hardening Checklist
- Purpose:
  define the security checks required before go-live
- Owner:
  security reviewer with platform architecture owner
- Format:
  checklist
- Related sprint:
  Sprint 6
- Related modules:
  all domains
- Completion criteria:
  production-readiness gates are measurable and reviewable

## Acceptance Notes
Security artifacts are complete only when:
- important threats are not buried in implementation details
- ownership for mitigation is visible
- release hardening has concrete checks
