# Merge Collisions

## Purpose
This document defines how to avoid, reduce, and resolve merge conflicts in a parallel sprint environment.

The project explicitly forbids bundle fixes and uncontrolled cross-sprint edits. Collision management is therefore a production discipline, not an afterthought.

## Collision Principles
- one task should prefer one ownership zone
- shared files must be treated as scarce resources
- contributors should announce intent before editing shared-risk files
- scope should be narrowed before code is touched

## Highest-Risk Collision Files
- `.env.example`
- `bootstrap/app.php`
- `routes/web.php`
- `routes/channels.php`
- `app/Models/User.php`
- `resources/css/app.css`
- `resources/js/app.js`
- `config/services.php`

## Collision Avoidance Rules
Before editing a shared file:
1. confirm it is required for the assigned sprint
2. confirm no narrower file can solve the problem
3. note the exact section intended for change
4. avoid opportunistic cleanup in the same edit

## One-Change Rule For Shared Files
When modifying a shared file:
- make one scoped change set
- avoid mixing unrelated features
- include comments in the task log describing the reason and surface area

## Documentation-First Collision Protection
For risky or ambiguous changes, create or update the relevant artifact first:
- route map
- state matrix
- env contract note
- ownership clarification

This forces the team to align before a shared file becomes contested.

## Parallel Work Strategy
Prefer this sequence:
- domain-local files first
- shared file integration last

Examples:
- create feature views, requests, services, and artifacts before touching a shared route file
- finish domain policy and state docs before modifying `User.php` or central config

## Conflict Resolution Order
If a collision occurs, resolve in this order:
1. protect production correctness
2. protect security posture
3. protect sprint boundaries
4. preserve modular structure
5. preserve formatting consistency

## What Not To Do
Do not:
- reformat a whole shared file during a small scoped change
- rename unrelated sections while merging
- bundle deferred cleanup into a contested file
- overwrite someone else’s approved decision without escalation

## Required Questions During Conflict Resolution
- Which change is in scope for the active sprint
- Which change is closer to the standards
- Which change is safer for production
- Which change introduces less coupling
- Does this conflict require a standards or artifact clarification

## Merge Readiness Checklist
- scope is still narrow
- shared-file edits are necessary
- unrelated hunks are removed
- ownership has been respected
- artifact alignment exists where needed

## Final Rule
Winning a merge conflict by force is not success. Success is resolving the conflict without losing correctness, safety, or team clarity.
