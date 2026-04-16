# Git Workflow

## Purpose
This document defines how the team should use Git to keep the project stable, traceable, and safe during parallel sprint delivery.

## Core Git Principles
- every branch must map to a real task or fix
- commits must be scoped and reviewable
- shared-file changes must stay narrow
- history should explain intent, not hide it

## Primary Branches
- `main`
  stable production-ready branch
- `develop`
  active integration branch for sprint delivery

## Working Rule
- no direct commits to `main`
- no direct commits to `develop` without review if team policy requires pull requests
- all feature work starts from `develop`

## Standard Flow
1. pull latest `develop`
2. create task branch
3. complete scoped work
4. commit in logical units
5. push branch
6. open pull request into `develop`
7. complete review and fixes
8. merge after approval

## Commit Standard
Commits should be:
- scoped
- readable
- aligned to one meaningful change

Recommended commit style:
- `docs(workflow): add sprint process guide`
- `docs(artifacts): add payment artifact inventory`
- `feat(cart): add cart state handling`
- `fix(checkout): block invalid promo at review step`

## Pull Request Standard
Every pull request should state:
- objective
- scope
- related sprint
- files changed
- risks
- review focus

## Merge Safety Rules
- keep pull requests small when possible
- avoid mixing docs, refactors, and feature changes without a strong reason
- resolve conflicts intentionally, not mechanically
- re-check standards after conflict resolution

## Rebase And Sync Rule
Contributors must keep branches current with `develop`, especially before review. Long-lived branches increase merge risk and should be avoided.

## Hotfix Rule
If a production hotfix is required:
- create a dedicated hotfix branch from `main`
- keep the fix minimal
- merge back to both `main` and `develop`
- document the hotfix reason and follow-up actions

## Git Anti-Patterns
Do not:
- force-push over shared history without approval
- bundle unrelated work into one branch
- commit secrets
- commit generated dependency directories
- use vague commit messages like `update`, `changes`, or `fix stuff`

## Review Readiness Rule
If a branch cannot be explained in a short paragraph, it is probably too broad for safe review.
