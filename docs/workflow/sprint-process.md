# Sprint Process

## Purpose
This document defines the standard sprint execution process for Ysabelle Retail Shop. The goal is to keep six weeks of delivery organized, modular, reviewable, and safe for parallel contributors.

## Sprint Model
- total duration: 6 weeks
- sprint cadence: 1 sprint per week
- sprint objective: one clear business outcome per sprint
- expected result: each sprint ends with a reviewable, documented, and quality-checked increment

## Sprint Sequence
1. Sprint 1: foundation, standards, and secure access baseline
2. Sprint 2: catalog, storefront, and product discovery
3. Sprint 3: cart, checkout, and payment initiation readiness
4. Sprint 4: orders, notifications, and realtime tracking
5. Sprint 5: admin operations, support, chatbot, and recommendation flows
6. Sprint 6: hardening, regression coverage, security review, and release readiness

## Sprint Inputs
Each sprint must begin with:
- approved sprint goal
- defined business outcome
- scoped task list
- ownership assignment
- risk list
- related standards and artifacts identified

## Sprint Ceremony Flow
### 1. Sprint Planning
Held at sprint start.

Required outputs:
- sprint name
- sprint objective
- in-scope work
- out-of-scope work
- task ownership
- shared-file warnings
- acceptance criteria
- quality target

### 2. Mid-Sprint Checkpoint
Held around the middle of the sprint.

Required checks:
- is scope still controlled
- are shared-file collisions emerging
- are blockers documented
- do artifacts need updates
- is the sprint still achievable at quality level

### 3. Sprint Review
Held at sprint end.

Required review points:
- what shipped
- what did not ship
- what risks remain
- whether acceptance criteria were met
- whether the quality target was achieved honestly

### 4. Sprint Retrospective
Held after review.

Required reflection:
- what created momentum
- what created friction
- what caused avoidable rework
- what must change in the next sprint workflow

## Standard Weekly Shape
### Day 1
- planning
- standards alignment
- task assignment
- artifact check

### Day 2
- focused execution on domain-local work
- early review of risky assumptions

### Day 3
- implementation continuation
- blocker escalation
- partial QA on completed slices

### Day 4
- integration
- regression review
- documentation updates

### Day 5
- finish scoped work
- run acceptance checks
- sprint review and retrospective preparation

## Scope Control Rules
- one sprint must not absorb the next sprint’s work without explicit approval
- shared files must be changed late and minimally
- cross-domain tasks must be broken into smaller owned pieces
- unfinished work must be closed or carried forward explicitly, never silently

## Quality Gates Per Sprint
Each sprint must satisfy:
- scope discipline
- documentation alignment
- ownership clarity
- review completeness
- no known P1 security or financial correctness regressions inside delivered scope

## Escalation Conditions
Escalate immediately when:
- a task requires changes outside its ownership zone
- a shared file becomes contested
- payment, auth, or order-state logic becomes ambiguous
- a sprint cannot finish without quality compromise

## Sprint Completion Rule
A sprint is complete only when:
- committed work matches the approved scope
- documentation is current
- review comments are resolved or explicitly deferred
- acceptance criteria are met
- residual risks are stated openly
