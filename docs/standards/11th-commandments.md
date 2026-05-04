# 11th Commandments

## Purpose
This document defines the non-negotiable rules for all contributors working on Ysabelle Retail Shop. These are hard boundaries, not suggestions.

## Hard Boundaries
1. `100% follow all project standards under the standards/ folder.`
2. `Do not break, rewrite, or destabilize the existing codebase.`
3. `Do not touch other Sprint/files if they are not part of the assigned task.`
4. `No bundle coding and no bundle fixes. Apply targeted, modular fixes only.`
5. `Keep files short, modular, and maintainable. Prefer roughly 100–300 lines per file when possible. Avoid giant files.`
6. `Fix known bugs and errors completely, not partially.`
7. `Keep everything modularized and aligned only with the assigned task.`
8. `The result must be production-ready and aim for 10/10 quality.`
9. `Ensure the implementation is fully working and introduces no conflicts with other teams.`
10. `Always provide a detailed output report in the exact structure requested below.`
11. `Add final status feedback and quality rating, for example 10/10 if production-ready.`

## Refined Enforcement Rules
### Rule 1 Enforcement
No contributor may claim compliance while ignoring the standards package. If a task starts without standards alignment, the task starts incorrectly.

### Rule 2 Enforcement
No opportunistic rewrites. No aesthetic refactors inside unrelated work. No “cleanup” that changes behavior without a scoped reason.

### Rule 3 Enforcement
Sprint boundaries are real boundaries. If a task touches another sprint’s files or goals, stop and realign scope first.

### Rule 4 Enforcement
One task means one target area. If multiple bugs appear across unrelated areas, fix only the assigned one unless an approved escalation expands the scope.

### Rule 5 Enforcement
Large files are a design smell. Split orchestration from business logic. Split domain logic from presentation. Split shared helpers before bloat becomes technical debt.

### Rule 6 Enforcement
A bug is not fixed if a failure path remains broken. Complete the root fix, document the edge case, and add the coverage that would catch recurrence.

### Rule 7 Enforcement
Every new file must belong to a domain and a reason. If the new file has no clear home, the design is not ready.

### Rule 8 Enforcement
Production-ready means secure, validated, reviewable, testable, and maintainable. “Works on my machine” is not a quality standard.

### Rule 9 Enforcement
Parallel team safety matters. Shared files, auth logic, payment flow, and status transitions must be handled with coordination and minimal blast radius.

### Rule 10 Enforcement
Every delivery must state exactly what changed, what was avoided, what risks remain, and what quality level was achieved.

### Rule 11 Enforcement
A final rating must reflect reality, not optimism. If risks remain, the rating must be lower and the reasons must be explicit.

## Additional Production Rules
- No secrets in source code, config defaults, views, scripts, or artifacts.
- No payment flow may trust browser state over server verification.
- No private realtime channel may exist without authorization logic.
- No admin action may bypass audit expectations for high-risk state changes.
- No cross-domain coupling should be introduced casually.
- No feature is complete if its critical edge cases remain undocumented or untested.
- No AI or developer may invent architecture that conflicts with the bounded domain direction already documented.

## Review Questions
Before declaring any task complete, answer:
- Did this stay inside scope
- Did this protect the rest of the codebase
- Did this follow the standards folder exactly
- Did this avoid unrelated edits
- Is the result truly production-grade

## Final Commandment
If there is ever a conflict between convenience and system integrity, system integrity wins.
