# Edge Cases

## Purpose
This document lists high-risk business and technical scenarios that must be anticipated explicitly. These are not optional nice-to-have checks. They are release-protection items.

## Customer Account Edge Cases
### Re-registration Attempt
A user tries to register with an email that already exists but has not completed verification.

Expected handling:
- do not create duplicate accounts
- offer a safe resend-verification path
- avoid exposing whether the account is active beyond approved UX patterns

### Password Reset Flooding
A user or attacker repeatedly requests password resets.

Expected handling:
- rate-limit requests
- do not leak account existence unnecessarily
- log abnormal behavior

## Cart Edge Cases
### Guest Cart To User Cart Merge
A guest signs in while already having an existing account cart.

Expected handling:
- define merge priority explicitly
- revalidate prices and stock after merge
- avoid silent item loss

### Cart Item Becomes Out Of Stock
An item added earlier is no longer available during checkout.

Expected handling:
- block purchase progression for invalid items
- explain which line item failed
- allow recovery without losing the entire cart unnecessarily

### Price Changed After Add To Cart
Price or promo changed between browse and checkout.

Expected handling:
- checkout must use current server-authoritative pricing
- user must see updated totals before payment initiation

## Checkout Edge Cases
### Address Incomplete Or Unsupported
Customer provides an address that does not meet delivery constraints.

Expected handling:
- prevent invalid shipping calculation
- provide a clear correction path

### Promo No Longer Valid
Promo expires or becomes ineligible after being applied.

Expected handling:
- remove invalid discount safely
- explain why the change happened

## Payment Edge Cases
### Browser Redirect Success Before Webhook
Customer returns from provider redirect before the server receives webhook confirmation.

Expected handling:
- do not finalize order as paid based only on redirect
- show a pending verification state

### Webhook Arrives Before Browser Redirect
Payment provider webhook reaches the server first.

Expected handling:
- order should still reconcile correctly
- customer should see the updated final state on refresh or realtime update

### Duplicate Webhook Delivery
Provider sends the same webhook more than once.

Expected handling:
- idempotent verification and order updates
- duplicate event should not duplicate financial or order state

### Payment Authorized But Internal Update Fails
Provider confirms payment but internal downstream processing fails.

Expected handling:
- preserve traceable pending-reconciliation status
- generate operational alerts

## Order Edge Cases
### Unauthorized Order Access
A user attempts to open another customer’s order.

Expected handling:
- deny access
- do not leak order existence

### Invalid Status Transition
An admin or system process attempts to jump from one status to another disallowed status.

Expected handling:
- reject transition
- log the attempted action

### Customer Sees Stale Tracking State
Realtime event fails or is delayed.

Expected handling:
- persistent order timeline remains accurate
- page reload retrieves truth from storage

## Support Edge Cases
### Support Agent Sees Wrong Customer Context
Agent opens a ticket while filters or route state are stale.

Expected handling:
- ticket view must be server-authoritative
- order and user context must match the ticket scope

### Bot Gives Low-Confidence Answer
Chatbot cannot confidently answer a question about refunds, disputes, or payment failures.

Expected handling:
- stop pretending certainty
- escalate to human support

## Recommendation Edge Cases
### Uploaded Image Is Too Large Or Invalid
Customer uploads an oversized or malformed file for visual matching.

Expected handling:
- reject safely
- explain supported file constraints

### Weak Recommendation Confidence
Style matching confidence is low.

Expected handling:
- communicate that matches are approximate
- prefer broad style suggestions over false precision

### Brand-Like Inputs Cause Misleading Results
A user uploads a logo or branded style input that does not map cleanly to catalog metadata.

Expected handling:
- fall back to color and style traits
- avoid claiming official brand association unless that is truly supported

## Admin Edge Cases
### Admin Overwrites Sensitive State
An admin attempts to manually change a paid order or user access status.

Expected handling:
- require strict authorization
- record audit context
- constrain dangerous operations

### Shared File Hotspot During Parallel Sprint Work
Two teams need the same shared file.

Expected handling:
- coordinate ownership
- narrow the change
- sequence integration instead of racing

## Final Rule
If an implementation touches a domain listed here, the corresponding edge case must be considered before the work is marked complete.
