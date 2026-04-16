# Artifacts Directory

## Purpose
The `artifacts/` folder contains feature-level delivery records, flow documents, traceability references, risk materials, and acceptance-oriented documentation for Ysabelle Retail Shop.

This folder is not for code. It is for the documents that make implementation safer, clearer, and more reviewable.

## Artifact Structure
- `artifacts/auth/`
  Authentication, identity, role, and session-related artifacts
- `artifacts/product/`
  Catalog, merchandising, product metadata, and preview-supporting artifacts
- `artifacts/cart/`
  Cart state, merge behavior, and pricing guardrail artifacts
- `artifacts/checkout/`
  Checkout stages, truth tables, and completion artifacts
- `artifacts/payment/`
  Payment flow, provider contract, webhook, and reconciliation artifacts
- `artifacts/orders/`
  Order lifecycle, status transition, and tracking artifacts
- `artifacts/websocket/`
  Realtime channels, event maps, and broadcast artifacts
- `artifacts/chatbot/`
  Chatbot scope, escalation, and knowledge artifacts
- `artifacts/recommendation/`
  Style, visual preview, and recommendation scoring artifacts
- `artifacts/security/`
  Threat, access, abuse, and hardening artifacts
- `artifacts/support/`
  Customer service, ticketing, handoff, and SLA artifacts
- `artifacts/flowcharts/`
  Presentation-ready system flowcharts

## How To Read An Artifact
Each artifact file should state:
- purpose
- owner
- format
- related sprint
- related modules or future files
- completion criteria

## Artifact Usage Rules
- create artifacts before or alongside implementation, not after the fact
- keep artifacts scoped to their domain
- use artifacts to guide acceptance and reduce ambiguity
- update artifacts when the business rule changes materially

## Artifact Categories
The project uses these artifact types:
- inventory registers
- flow maps
- truth tables
- state diagrams
- policy docs
- threat models
- acceptance checklists

## Review Rule
If a feature area is complex enough to fail in multiple ways, it should have an artifact that makes its behavior visible and reviewable.
