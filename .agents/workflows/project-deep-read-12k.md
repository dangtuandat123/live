---
description: Deeply read, map, and understand a project with evidence-first, coverage-based analysis before auditing, fixing, refactoring, or architecture decisions.
---

# Project Deep Read Workflow - 12k Safe

Use when the user asks to read, understand, audit, map, refactor, debug, secure, or make architecture decisions about a project.

Goal: build the deepest practical understanding with evidence. Do not pretend absolute understanding.

Always state:

> Understanding is coverage-based, not absolute.

## Non-negotiables

- Do not claim complete understanding.
- Do not report guesses as confirmed bugs.
- Do not treat listed files as read files.
- Do not treat searches as traced behavior.
- Do not confuse frontend prevention with backend enforcement.
- Do not confuse test existence with meaningful coverage.
- Prefer narrow verified conclusions over broad unsupported claims.
- Always expose unknowns.
- Always produce a Project Coverage Report before final conclusions.

## Step 1 - Scope and correctness

Record:

- user request;
- project root/workspace;
- scope: full repo, app, package, module, feature, bug, PR, or files;
- goal: understand, audit, refactor, debug, security, product QA, architecture, performance, or tests;
- source of truth: README/docs, product behavior, tests, API schema, DB schema, ticket/spec, existing behavior, or user instruction.

If no source of truth exists, state: correctness is inferred from code, tests, and observed conventions.

Do not produce final conclusions yet.

## Step 2 - Select depth mode and budget

Declare one mode:

### Survey Mode
Quick orientation only. Output inventory, likely stack/architecture, candidate risks, and next reading plan. No confirmed bugs or safety claims.

### Core Mode
Use before normal fixes/reviews. Prioritize source-of-truth files, entrypoints, routes/controllers/pages, core services, schemas/models/migrations, auth/permissions, shared contracts/types, cache/state, critical tests, config/env/CI/deploy.

### Critical Path Mode
Use for a feature, bug, API, user journey, or business process. Trace at least one path: UI -> action -> API -> service -> DB -> response -> UI; route -> controller -> service -> repository -> model; producer -> event/job/webhook -> consumer; schema/type -> implementation -> test.

### PR/Diff Mode
Use for changed files/PRs/patches. Read changed files fully, identify callers/consumers/tests/contracts/configs, trace impacted paths, and do not generalize to whole-project safety.

### Full Audit Mode
Use only when explicitly requested or repo is small enough. Requires broad inventory, deep reads of core files, reference tracing, product behavior inventory, invariant extraction, attack scenarios, safe validation commands, evidence ledger, risk register, coverage report, and confidence rating.

Define budget: max scope, priority areas, excluded areas, commands allowed, commands forbidden, and whether external-state-changing commands are skipped.

If the repo is too large, split into passes and report partial coverage.

## Step 3 - Repository inventory

Inspect structure. Record:

- package manager and lockfiles;
- languages/frameworks;
- apps/packages/workspaces and monorepo tooling;
- source dirs;
- entrypoints;
- route/page/controller dirs;
- config/env/CI/deploy/Docker files;
- DB schema/migrations;
- tests;
- docs;
- generated/vendor/build/binary folders.

Exclude by default unless needed: `.git`, `node_modules`, `dist`, `build`, `.next`, `.turbo`, `coverage`, `vendor`, generated artifacts, binary assets, and lockfile internals unless dependency audit is requested.

For monorepos, map workspace root, package graph, app boundaries, shared packages, cross-package imports, package scripts/tests/deploy paths.

## Step 4 - Read source-of-truth first

Before source code, read relevant:

- README/docs/architecture notes;
- package/build/test config;
- env examples;
- routing config;
- API schema/OpenAPI/GraphQL schema;
- DB schema/migrations;
- test setup;
- CI/deploy/Docker config;
- feature flag config;
- auth/permission config.

Extract purpose, domains, runtime commands, architecture assumptions, external services, security-sensitive areas, constraints, deployment model, and test strategy.

Record every file fully read.

## Step 5 - Architecture and domain map

Map layers/domains:

- frontend pages/routes/screens;
- components/forms/actions;
- state/cache management;
- API routes/controllers;
- services/use cases;
- repositories/data access;
- DB models/migrations;
- background jobs;
- webhooks/events/queues;
- auth/permissions;
- shared types/contracts;
- tests.

For each area, identify core files and logic ownership.

## Step 6 - Read core files fully

Read full contents of in-scope core files, especially files that define:

- app entrypoints;
- routing/pages/controllers;
- business logic;
- data models/schema;
- auth/permissions;
- API contracts;
- shared types;
- state/cache;
- background jobs/events/webhooks;
- critical tests.

Record all full-file reads. If only scanned/listed, mark as scanned/listed, not read.

## Step 7 - Trace references

Repo-wide search and record terms for important:

- exported functions/classes/components;
- route paths/API endpoints;
- action names;
- permission keys;
- table/model names;
- event/job/webhook names;
- env keys;
- feature flags;
- user-visible labels/text;
- error codes;
- config keys.

Trace relevant caller -> implementation -> consumer paths, UI -> API -> service -> DB paths, producer -> consumer paths, and schema/type -> implementation -> test paths.

## Step 8 - Product behavior inventory

When relevant, inventory:

- pages/routes/screens;
- forms/buttons/actions;
- modals/toasts;
- loading/empty/error/success states;
- permission-gated UI;
- duplicate/similar flows;
- user-visible text.

Look for wrong text, placeholder text, inconsistent naming, duplicate buttons/features, dead UI, unreachable pages, UI allowed but backend denied, or UI hidden but API allowed.

## Step 9 - Core invariants

List invariants that must not break, such as:

- users access only their tenant/org data;
- paid or write actions are idempotent;
- status transitions cannot skip required states;
- deleted records are hidden;
- API response shape matches consumers;
- backend validates rules even if frontend validates too;
- mutations invalidate affected cache;
- migrations preserve data;
- retries cannot double-create records;
- permissions are server-enforced.

Use these invariants to judge findings.

## Step 10 - Attack scenarios

Try to break in-scope flows with:

- null/empty/invalid input;
- duplicate input;
- invalid enum;
- large dataset;
- concurrent requests;
- stale cache;
- out-of-order events;
- webhook replay;
- partial failure/retry;
- network timeout;
- missing/deleted related records;
- old data shape;
- old client calling new API;
- timezone/date boundary;
- feature flag on/off;
- tenant/org mismatch;
- permission mismatch.

Do not report weak guesses as confirmed bugs.

## Step 11 - Security checks

When in scope, check:

- secrets committed or logged;
- auth bypass;
- tenant/org isolation;
- server-side permission enforcement;
- IDOR/object ownership;
- unsafe deserialization;
- path traversal/file upload/SSRF risks;
- webhook signature verification;
- replay protection;
- rate limits/abuse paths;
- dependency/config exposure.

## Step 12 - Tests and safe validation

Inspect tests and determine:

- what is covered;
- uncovered invariants;
- whether tests assert real behavior;
- whether mocks are too optimistic;
- whether snapshots hide behavior issues;
- whether integration/e2e tests are needed.

If safe and appropriate, run targeted tests, typecheck, lint, build, or integration/e2e.

Command safety:

- prefer read-only commands;
- do not run deploy, migrate, seed, reset, clean, rm, production, or external-state-changing commands without explicit approval;
- if commands are skipped, state why and list recommended commands.

## Step 13 - Coverage classification

For each area, mark one:

- Read deeply: full files read and references traced;
- Partially understood: structure scanned and some files read;
- Indexed only: files listed/searched, not read;
- Excluded: generated/vendor/build/binary/out of scope;
- Unknown: inaccessible or insufficient context.

Do not claim safety outside deeply read or clearly traced areas.

## Step 14 - Evidence quality gate

Before findings/conclusions, verify:

- each confirmed bug has code-path evidence;
- each risk is labeled unconfirmed;
- major claims tie to files/searches/commands/tests;
- unknowns are listed;
- failed/unrun commands are disclosed;
- monorepo/package overrides were considered;
- generated/build/vendor exclusions do not hide source-of-truth behavior.

If evidence is weak, downgrade Confirmed Bug to Risk. If scope is incomplete, downgrade confidence.

## Step 15 - Output maps and reports

Output a Project Understanding Map:

1. Purpose.
2. Tech stack.
3. Entrypoints.
4. Structure/modules.
5. Data model.
6. API/routes.
7. Auth/permissions.
8. Important flows.
9. Product behavior.
10. External integrations.
11. Tests.
12. Build/deploy/config.
13. Core invariants.
14. High-risk areas.
15. Duplicate/similar flows.
16. Unknowns.

Output a Project Coverage Report:

- active mode and scope;
- full files read;
- directories scanned;
- searches performed;
- entrypoints/routes/components/actions checked;
- text/labels checked;
- API/contracts checked;
- DB/schema/migrations checked;
- auth/permissions checked;
- jobs/events/webhooks checked;
- tests checked;
- config/env/CI/deploy checked;
- commands run/not run;
- exclusions;
- not checked;
- confidence: High within audited scope, Medium with known gaps, or Low insufficient coverage.

Include an Evidence Ledger:

| Area | Claim | Evidence | Full files read | Searches | Commands | Confidence | Unknowns |
|---|---|---|---|---|---|---|---|

## Step 16 - Findings, if requested

Order findings by severity. Each finding must include:

- Type: Confirmed Bug, Risk, Improvement, Test Gap, Product QA Issue, Security Concern, Architecture Concern, or Unknown;
- Severity: Critical, High, Medium, Low, Info;
- Location;
- Evidence;
- Cross-check;
- Why it matters;
- Impact;
- Reproduction or scenario;
- Suggested fix;
- Validation;
- Confidence.

A Confirmed Bug requires code-path evidence plus at least one cross-check: caller/consumer, contract/schema/type, test, docs/spec, DB model/migration, or reproducible scenario. Otherwise classify as Risk.

## Step 17 - Final conclusion

End with:

- what is well understood;
- what is not fully understood;
- highest-risk areas;
- whether more targeted audit is needed;
- next recommended step.

Never say "100% understood" or "nothing is missed". Say "coverage-based understanding" and show evidence.
