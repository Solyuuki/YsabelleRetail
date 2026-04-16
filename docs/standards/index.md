# Ysabelle Retail Shop Standards Index

## Purpose
This directory is the single source of truth for engineering, architecture, delivery, and change-control standards for Ysabelle Retail Shop. Every contributor, whether human or AI-assisted, must read this documentation before making changes to the repository.

These standards exist to protect four things:
- delivery speed without chaos
- production reliability
- security and compliance posture
- maintainable modular architecture

## Mandatory Reading Order
Read these files in the following order before starting any scoped task:
1. `standards/10-commandments.md`
2. `standards/01-big-picture.md`
3. `standards/02-folder-map.md`
4. `standards/03-folder-guide.md`
5. `standards/07-team-ownership.md`
6. `standards/08-merge-collisions.md`
7. `standards/09-edge-cases.md`
8. `standards/04-env.md`
9. `standards/05-naming-rules.md`
10. `standards/06-coding-standards.md`

## How To Use This Folder
- Use this folder before planning, not after implementation.
- Use this folder to decide ownership, placement, naming, and boundaries.
- Use this folder to reject work that is oversized, cross-sprint, unsafe, or structurally incorrect.
- Use this folder together with `artifacts/` to align implementation with business flows and deliverables.

## Directory Contents
- `01-big-picture.md`
  Business vision, platform scope, user groups, operating model, and system-level architectural intent.
- `02-folder-map.md`
  Repository map with approved locations, sensitive files, and guarded folders.
- `03-folder-guide.md`
  Detailed instructions for what belongs in each directory and what does not.
- `04-env.md`
  Environment variable contract, secret handling, and deployment environment expectations.
- `05-naming-rules.md`
  Naming rules for files, folders, classes, routes, views, migrations, tests, and artifacts.
- `06-coding-standards.md`
  Production implementation standards, architecture rules, validation rules, UI consistency rules, and testing standards.
- `07-team-ownership.md`
  Module ownership, reviewer requirements, and escalation expectations.
- `08-merge-collisions.md`
  Shared-file collision management, change sequencing, and conflict avoidance.
- `09-edge-cases.md`
  High-risk business and technical scenarios that must be explicitly handled.
- `10-commandments.md`
  Non-negotiable rules and enforcement-grade guidance.

## Standards Governance
These standards are governed by the platform architecture owner and the security reviewer. A change to the standards is treated as a controlled change request, not a casual edit.

A standards change must include:
- the exact problem being solved
- the reason the current standard is insufficient
- the proposed revision
- the expected impact on teams, schedule, and release quality
- the effective date and owner

## Definition Of Done For Documentation
Documentation in this repository is considered complete only when it is:
- explicit enough for a new team member to follow without oral clarification
- scoped enough to avoid touching unrelated areas
- consistent with the current project phase
- actionable enough to guide implementation decisions
- reviewed for ambiguity, contradictions, and unsafe assumptions

## Relationship To The Artifacts Folder
The `standards/` folder defines how work must be done.  
The `artifacts/` folder defines what delivery assets, decision records, maps, and supporting materials must exist for each feature area.

In simple terms:
- `standards/` governs behavior
- `artifacts/` governs traceability

## Compliance Rule
If any implementation idea, sprint task, or file placement conflicts with this standards set, the standards set wins unless a documented exception is approved.
