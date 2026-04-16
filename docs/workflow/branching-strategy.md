# Branching Strategy

## Purpose
This document defines the branch naming and branch lifecycle strategy for Ysabelle Retail Shop.

## Branching Model
The project uses a controlled sprint-oriented branching model:
- `main` for production-ready code
- `develop` for active sprint integration
- short-lived task branches for execution
- short-lived hotfix branches for emergency production corrections

## Branch Types
### Feature Branch
Used for planned sprint work.

Format:
- `feature/s{number}-short-description`

Examples:
- `feature/s1-auth-foundation`
- `feature/s3-checkout-review-flow`
- `feature/s5-support-chat-escalation`

### Fix Branch
Used for scoped bug fixes inside active sprint work.

Format:
- `fix/s{number}-short-description`

Examples:
- `fix/s3-cart-merge-conflict`
- `fix/s4-order-status-edge-case`

### Docs Branch
Used for documentation-only changes.

Format:
- `docs/short-description`

Examples:
- `docs/workflow-package`
- `docs/payment-artifacts-update`

### Hotfix Branch
Used for urgent production fixes.

Format:
- `hotfix/short-description`

Examples:
- `hotfix/payment-webhook-validation`
- `hotfix/admin-access-regression`

## Branch Creation Rule
- create from `develop` for sprint work
- create from `main` only for production hotfixes
- keep the branch purpose narrow

## Branch Lifetime Rule
Branches must be short-lived.

Expected behavior:
- open quickly
- merge quickly
- close quickly

Long-lived branches are discouraged because they:
- increase merge conflict risk
- hide drift from standards
- make review harder

## Branch Scope Rule
One branch should normally represent:
- one feature slice
- one fix
- one documentation package

If a branch starts requiring multiple unrelated explanations, split it.

## Protected Branch Expectations
Protected branches should enforce:
- review before merge
- no destructive history rewrite
- successful checks where configured

## Naming Rule
Use:
- lowercase only
- hyphen-separated words
- stable wording that reflects task intent

Do not use:
- personal names
- vague labels
- dates unless required by team process

## Closing Rule
A branch may be closed only after:
- merge is complete
- review comments are resolved
- follow-up tasks are captured if anything was deferred
