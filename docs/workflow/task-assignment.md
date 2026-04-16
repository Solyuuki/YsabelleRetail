# Task Assignment

## Purpose
This document defines how work is assigned across the team so execution remains orderly, accountable, and safe for parallel contribution.

## Assignment Principles
- every task must have one primary owner
- every task must name required reviewers when risk justifies it
- ownership must be explicit before execution starts
- tasks should align to sprint scope and domain boundaries

## Assignment Inputs
Before assigning a task, confirm:
- sprint number
- business outcome
- domain area
- priority
- dependency chain
- related standards
- related artifacts

## Required Assignment Fields
Each task record should include:
- task title
- sprint
- domain
- objective
- primary owner
- required reviewer
- consulted reviewer if needed
- dependencies
- risk level
- acceptance criteria

## Ownership Rules
### Primary Owner
Responsible for:
- executing the task
- keeping scope controlled
- requesting review
- reporting blockers

### Required Reviewer
Responsible for:
- validating correctness in their domain
- blocking unsafe or non-compliant changes

### Consulted Reviewer
Responsible for:
- advising on affected areas when the task overlaps concerns

## Task Granularity Rule
Tasks must be:
- specific
- independently reviewable
- small enough to complete without cross-sprint spillover

Bad assignment examples:
- “build checkout”
- “fix all admin issues”

Good assignment examples:
- “define checkout review-state truth table”
- “implement customer order timeline status mapping”

## Dependency Rule
If Task B depends on Task A:
- Task A must be identified explicitly
- Task B must not start as if the dependency does not exist
- dependency risk must be visible in sprint planning

## Assignment Priority Order
1. production correctness
2. security-sensitive work
3. sprint-critical path
4. user-visible experience improvements
5. supporting enhancements

## Reassignment Rule
Reassign a task only when:
- the current owner is blocked beyond a reasonable window
- sprint risk demands redistribution
- ownership changes are documented clearly

## Anti-Patterns
Do not:
- assign one task to multiple primary owners
- assign vague outcomes
- hide dependencies
- assign high-risk work without a qualified reviewer

## Completion Readiness Rule
A task is ready for execution only when someone can answer:
- who owns it
- why it matters this sprint
- what done looks like
- what standards govern it
