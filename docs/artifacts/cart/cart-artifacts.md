# Cart Artifacts

## Domain
Cart behavior, item state, quantity updates, guest persistence, and merge logic

## Artifact Inventory

### 1. Cart State Model
- Purpose:
  define the cart lifecycle from empty cart to ready-for-checkout cart
- Owner:
  commerce flow owner
- Format:
  state specification
- Related sprint:
  Sprint 3
- Related modules:
  Cart, Checkout, Accounts
- Completion criteria:
  valid cart states and transitions are explicit

### 2. Guest-To-User Cart Merge Artifact
- Purpose:
  define what happens when a guest with cart items signs into an account with existing cart items
- Owner:
  commerce flow owner
- Format:
  truth-table style decision document
- Related sprint:
  Sprint 3
- Related modules:
  Cart, Accounts
- Completion criteria:
  merge rules, item conflict rules, and revalidation expectations are unambiguous

### 3. Cart Pricing Guardrail Document
- Purpose:
  define what cart totals are informative versus authoritative
- Owner:
  commerce flow owner with security reviewer
- Format:
  pricing safety document
- Related sprint:
  Sprint 3
- Related modules:
  Cart, Checkout, Payment
- Completion criteria:
  it is clear that server totals win and when recalculation must happen

## Acceptance Notes
Cart artifacts are complete only when:
- cart state does not become hand-wavy at login or checkout transition time
- stock and price revalidation is documented
- recovery behavior is documented for changed items
