---
description: Evidence-driven static/code-path audit for UX/backend/API, data, security, async state, duplicate flows, PR/diff, and tests. Under 12k.
---

# Evidence Deep Audit v3 - 12k

Use for: deep audit, code/module/feature/PR audit, UX/backend audit, frontend/backend mismatch, pre-merge review, hidden bug/risk hunt.

Default: audit only. Do not edit code unless asked. No browser/visual QA.

## Non-negotiable

Do not produce final findings until all passes are completed or blocked.

Every finding needs: type, severity, location, evidence, cross-check, impact, scenario, fix, validation, confidence.

If evidence is incomplete, classify as Risk, Needs validation, Question, or Test Gap.

## Completion Gate

Audit is incomplete if:
- Expected Behavior Contract is empty;
- Coverage Ledger has relevant unknowns without explanation;
- no repo-wide search terms are reported;
- UI feature has no mapped action;
- backend feature has no mapped endpoint/action;
- detectable stack has no Stack Profile;
- findings lack evidence/cross-check;
- final decision is Safe without clear coverage and relevant validation.

---

## Pass 0 - Scope Lock + Stack Profile

Identify target, audit type, expected user/backend behavior, source of truth, changed files, risks, commands, exclusions.

Detect stack/framework:
- Next.js: server/client boundary, route handlers/actions, cache/revalidate.
- React Query/SWR: query keys, invalidation, optimistic rollback.
- ORM/DB/Auth: tenant scope, transactions, soft delete, migrations, session/role/owner enforcement.

If no clear source exists: "Correctness is inferred from code, naming, tests, and nearby behavior; not proven by explicit spec."

```md
## Scope Lock + Stack Profile
| Item | Value |
|---|---|
| Target | |
| Audit type | |
| Source of truth | |
| Stack/framework | |
| Stack-specific risks | |
| Expected user behavior | |
| Expected backend/data behavior | |
| Risk level | |
| Exclusions | |
| Commands likely needed | |
```

---

## Pass 1 - Expected Behavior Contract

Define expected behavior from request, docs/spec, routes, UI copy, API schema, types, tests, similar flows, DB constraints.

```md
## Expected Behavior Contract
| Behavior/Rule | Source | Confidence | What would be wrong |
|---|---|---|---|
```

If inferred, mark Medium/Low confidence. Findings should reference this contract when relevant.

---

## Pass 2 - Repo/Feature Map

Ignore generated/vendor/build unless relevant: node_modules, .git, dist, build, .next, coverage, vendor.

Map entrypoints, routes/pages, UI, forms/actions, APIs, services/domain, DB/schema/migrations, types, auth, state/cache, tests, config/env, duplicates.

```md
## Repo / Feature Map
| Area | Files/Symbols | Why relevant | Read status |
|---|---|---|---|
```

Do not claim deep audit without mapping UI/action and backend/data side.

---

## Pass 3 - Coverage Ledger

```md
## Coverage Ledger
| Category | Found | Read | Not checked | Notes |
|---|---:|---:|---:|---|
| Screens/components | | | | |
| User actions | | | | |
| API/actions | | | | |
| Services/domain | | | | |
| DB/schema/config | | | | |
| Auth/permissions | | | | |
| State/cache | | | | |
| Tests | | | | |
```

If "Not checked" has relevant unknowns, do not claim full audit.

---

## Pass 4 - Repo-wide Search

Search symbols/text: functions, components, hooks, routes, endpoints, actions, labels, validation/toasts/modals, DB names, permissions, events, env/cache keys, duplicates, old/new names.

```md
## Repo-wide Searches
| Search term | Important matches | Why searched | Follow-up |
|---|---|---|---|
```

Do not mark unused/dead until references were searched.

---

## Pass 5 - Static UX/Product Matrix

Audit UX from code only: titles, actions, forms, labels, validation, buttons, modals, toasts, loading/empty/error/success/disabled states, permissions, duplicate/dead actions, text vs behavior.

```md
## Static UX Matrix
| Screen/Component | State/Action/Text | Evidence | Expected | Actual | Issue |
|---|---|---|---|---|---|
```

Flag missing states, misleading/destructive copy, duplicates, placeholder/untranslated text, UI/backend mismatch. If insufficient evidence, mark Needs runtime validation.

---

## Pass 6 - Action Logic Matrix

For every relevant button, submit, link, menu item, destructive/save/retry/upload/navigation/bulk action, map the path.

```md
## Action Matrix
| Action | Component | Handler | Validation | Disabled/Loading | Success/Error | Backend/API | Risk |
|---|---|---|---|---|---|---|---|
```

Check handler/API, disabled logic, double-submit, reset after success, modal timing, success copy vs backend, errors, confirmation, backend protection, stale state.

---

## Pass 7 - Copy/Text Intent Matrix

Search visible strings: titles, buttons, labels, validation, empty/error/success copy, toasts, modals, confirmation, help, routes, docs/examples.

```md
## Copy / Text Matrix
| Text | Location | User expectation | Actual code behavior | Mismatch |
|---|---|---|---|---|
```

Flag text/action mismatch, early success copy, validation mismatch, duplicate text, inconsistent naming, placeholder/demo/untranslated text.

---

## Pass 8 - Frontend-Backend Contract Matrix

For every relevant user action, map frontend to backend.

```md
## Frontend-Backend Matrix
| UI action | Client | API | Request | Server validation/auth | DB/cache | Response/UI | Mismatch |
|---|---|---|---|---|---|---|---|
```

Check fields, required/optional, response shape, errors, permissions, effect vs UI copy, cache invalidation, optimistic update, pagination/sort/filter/status. Mismatch = Risk; with code+consumer evidence = Confirmed Bug.

---

## Pass 9 - Backend Abuse Matrix

For every endpoint/action/mutation, reason about abuse cases from code.

```md
## Backend Abuse Matrix
| Endpoint/Action | Missing auth | Wrong owner/tenant | Invalid/extra input | Replay/double submit | Server result | Finding |
|---|---|---|---|---|---|---|
```

Check unauth access, unauthorized role, wrong owner/tenant, disabled/deleted record, invalid enum, missing/extra fields, malformed payload, direct API bypass, broad update/delete, rate limit. Frontend protection does not count.

---

## Pass 10 - Data Flow and Invariants

List invariants, then attack them.

```md
## Invariant Matrix
| Invariant | Code locations | Attack case | Evidence | Result | Finding |
|---|---|---|---|---|---|
```

Attack null/empty/zero/negative/max, invalid enum, old shape, missing/deleted relation, duplicate input, concurrency, partial failure, timeout, timezone, large dataset, wrong user/tenant, flag/env mismatch.

---

## Pass 11 - State/Async/Race

```md
## State / Async / Race Matrix
| Flow | State vars | Initial | Loading | Success | Error | Cleanup | Race risk |
|---|---|---|---|---|---|---|---|
```

Check stale closure, missing hook deps, render loop, request race, cleanup, double submit, optimistic rollback, modal/nav during request, cache key, refetch/invalidate, loading reset, swallowed error, early success, retry double-write.

---

## Pass 12 - Security/Privacy Threat Matrix

For sensitive paths: auth, payment, user/tenant/admin data, upload/download, webhooks, external APIs, secrets/logs, destructive/bulk actions.

```md
## Security / Privacy Matrix
| Asset | Attacker | Entry | Weak control | Abuse | Severity |
|---|---|---|---|---|---|
```

Check missing auth, IDOR, privilege escalation, tenant breakout, injection, path traversal, XSS, CSRF, SSRF, open redirect, unsafe files, PII/secret leak, unsafe logging, rate limit, client trust.

---

## Pass 13 - Performance/Reliability/Data Integrity

```md
## Performance / Reliability / Data Integrity
| Area | Risk checked | Evidence | Finding |
|---|---|---|---|
```

Check N+1, indexes, broad update/delete, transactions, soft-delete, migration compat, cache key/invalidation, timeout/retry/backoff, partial/external failure, large data, memory/listener cleanup.

---

## Pass 14 - Duplicate/Dead/Consistency

```md
## Duplicate / Dead / Consistency Matrix
| Pattern searched | Matches | Risk | Finding |
|---|---|---|---|
```

Check duplicate components/routes/actions/API/business logic, old+new implementations, unreachable/orphan/unused flows, stale env/docs, inconsistent naming. Do not mark dead without search.

---

## Pass 15 - Tests/Mutation Gaps

Inspect tests and use mutation thinking.

```md
## Test / Mutation Gap Matrix
| Behavior | Existing test | Mutation that should fail | Caught? | Missing test |
|---|---|---|---|---|
```

Check happy path, invalid input, empty/error state, permission, wrong owner/tenant, backend validation, FE/BE contract, duplicate submit, async failure, cache invalidation, regressions. Flag optimistic mocks/snapshots.

---

## Pass 16 - PR/Diff Mode

If auditing PR/diff:
1. list changed files by risk type: UI, API, DB, auth, config, tests, docs;
2. for each changed public symbol, route, schema, table, permission, env key, event, or visible text, search references;
3. compare old vs new behavior;
4. check upstream caller and downstream consumer when available;
5. breaking behavior without migration, compat, docs, or tests blocks merge.

If not PR/diff, mark "not applicable".

---

## Pass 17 - Validation

Run if possible: targeted tests, typecheck, lint, build, integration/e2e, dependency/security/secret scan.

```md
## Validation
| Command | Ran? | Result | Proves | Does not prove |
|---|---|---|---|---|
```

Never claim passed if not run. Typecheck/lint/build do not prove product correctness. Safe requires relevant validation unless user accepts static-only confidence.

---

## Pass 18 - Independent Review + Missed-risk

Run 3 reviewers: Product QA, Backend/Data, Security/Abuse. Challenge UI+backend, caller+consumer, text, states, server validation, auth/tenant, cache, duplicates, tests, validation, weak findings downgraded, no safety beyond scope.

Do one final targeted search for highest-risk missed area.

```md
## Independent Review + Missed-risk
| Reviewer/Area | Focus | Extra check | New finding/result |
|---|---|---|---|
| Product Logic QA | text/action/state/duplicate/UI-backend/form | | |
| Backend/Data | API/validation/auth/tenant/transaction/cache/idempotency | | |
| Security/Abuse | direct API abuse/IDOR/client trust/logs/input/privilege | | |
| Missed-risk | likely hidden bug area | | |
```

---

# Final Report Format

```md
# Evidence Deep Audit Report

## Summary
- Scope:
- Mode: static/code-path audit
- Confidence:
- Critical:
- High:
- Medium:
- Low:
- Decision:

## Scope Lock + Stack Profile
## Expected Behavior Contract
## Coverage Ledger
## Repo-wide Searches
## Static UX Matrix
## Action Matrix
## Copy / Text Matrix
## Frontend-Backend Matrix
## Backend Abuse Matrix
## Invariant Matrix
## State / Async / Race Matrix
## Security / Privacy Matrix
## Duplicate / Dead / Consistency Matrix
## Test / Mutation Gap Matrix
## PR/Diff Mode
## Validation
## Independent Review + Missed-risk

## Findings
### [Severity] Title
- Type:
- Location:
- Evidence:
- Cross-check:
- Why wrong/risky:
- Impact:
- Scenario:
- Minimal fix:
- Validation:
- Confidence:

## Product / UX / Text / Duplicate Issues
## Test Gaps
## Missed-risk / Limitations
## Suggested Fix Order

## Final Notes
Static/code-path audit only. No pixel-perfect or runtime-only correctness is claimed unless proven by tests or runtime evidence.

## Decision
End with exactly one:
- Block merge
- Fix before merge
- Merge with follow-up
- Safe within audited scope
```
