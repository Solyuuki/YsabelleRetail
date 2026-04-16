# Definition Of Done

## Purpose
This document defines the minimum standard required before any task, sprint item, or documentation deliverable can be considered complete.

## Core Principle
Done means complete, reviewable, safe, and aligned. It does not mean “mostly working” or “ready later.”

## Global Definition Of Done
Work is done only when:
- scope matches the assigned task
- standards were followed
- related artifacts are current where applicable
- review has happened at the correct level
- known critical issues inside scope are not left unresolved
- residual risk is stated honestly

## Documentation Definition Of Done
Documentation is done when:
- file placement is correct
- content is complete enough to guide work
- ownership is clear
- wording is precise, not vague
- no contradictory instructions remain

## Implementation Definition Of Done
Implementation work is done when:
- behavior matches acceptance criteria
- validation is present
- authorization is present where needed
- affected edge cases were considered
- tests or verification steps exist at the right level
- no unrelated files were changed without reason

## Review Definition Of Done
Review is done when:
- required reviewers approved or signed off
- review comments are resolved or explicitly deferred
- final scope is still narrow and intentional

## Sprint Item Definition Of Done
A sprint item is done when:
- business outcome is satisfied at the promised level
- deliverables are present
- documentation is not lagging behind
- blockers are closed or formally carried over

## Release-Facing Definition Of Done
High-risk work affecting auth, payment, orders, support visibility, or realtime is done only when:
- security expectations are met
- failure paths were considered
- rollback or recovery path is understood
- the change does not create hidden operational ambiguity

## Not Done Conditions
Work is not done if:
- it still needs a “small follow-up” to be safe
- it relies on undocumented assumptions
- it expands into another sprint without approval
- it bypasses standards for speed
- it is waiting on known fixes not captured anywhere

## Final Check Questions
Before closing work, confirm:
- did this stay inside scope
- is it aligned with the sprint goal
- can another contributor understand it without guessing
- has the right person reviewed it
- would we be comfortable defending this as production-grade

## Completion Rule
If the answer to any final check question is no, the work is not done yet.
