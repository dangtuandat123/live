---
trigger: always_on
---

# No False Full Understanding Rule - 12k Safe

## Purpose

When asked to read, understand, audit, map, refactor, debug, secure, or make architecture decisions about a project, the Agent must use evidence-first, coverage-based understanding.

The Agent must never imply total certainty about a codebase unless it has explicit, checkable evidence.

## Core rule

Never claim or imply:

- "I understand 100%";
- "I read everything";
- "nothing is missed";
- "this is safe";
- "there are no bugs";
- "this cannot happen";
- "the whole project works like this";
- "all routes/APIs/tests/configs were checked".

For non-trivial or large repositories, always state:

> Understanding is coverage-based, not absolute.

## Evidence-first claims

Every major claim about architecture, behavior, safety, bugs, data flow, permissions, performance, tests, or product behavior must be backed by at least one of:

- a full file read;
- a traced caller -> implementation -> consumer path;
- a schema, contract, migration, model, or type definition;
- a test that asserts the behavior;
- a config/build/CI/deploy/env file;
- a repo-wide search with search terms recorded;
- safe command output.

If evidence is incomplete, label the statement as:

- Hypothesis;
- Risk;
- Partial understanding;
- Unknown;
- Not checked.

## Do not confuse

The Agent must not confuse:

- listed files with read files;
- snippets with full-file understanding;
- searched references with traced behavior;
- no search results with feature absence;
- frontend validation with backend enforcement;
- hidden UI with denied API access;
- test existence with meaningful coverage;
- passing build with correct behavior;
- type safety with runtime safety;
- docs with implementation;
- route presence with route correctness;
- generated code with source-of-truth logic;
- sample env with deployed config.

## Depth modes

Declare one active mode before conclusions.

### Survey Mode

Use for quick orientation only. Allowed: inventory, likely stack, likely architecture, candidate risks, next reading plan. Not allowed: confirmed bugs, safety claims, whole-project conclusions.

### Core Mode

Use before normal fixes/reviews. Prioritize source-of-truth files, entrypoints, routes/controllers/pages, core services, schemas/models/migrations, auth/permissions, shared contracts/types, cache/state boundaries, critical tests, config/env/CI/deploy.

### Critical Path Mode

Use for one feature, bug, flow, API, or business process. Trace at least one path such as UI -> action -> API -> service -> DB -> response -> UI, or producer -> event/job/webhook -> consumer.

### PR/Diff Mode

Use for changed files, PRs, patches, or recent edits. Read changed files fully, identify callers/consumers/tests/contracts/configs, trace impacted paths, and do not generalize PR-only coverage to the whole project.

### Full Audit Mode

Use only when explicitly requested or when the repo is small enough. Requires coverage report, evidence ledger, risk register, safe validation commands where appropriate, unknowns, and confidence rating.

## Required coverage report

Before final conclusions, output or reference a Project Coverage Report containing:

- active depth mode;
- declared scope;
- full files read;
- files only listed/scanned;
- directories scanned;
- repo-wide searches performed;
- entrypoints checked;
- routes/pages/screens/controllers checked;
- components/actions/forms checked;
- APIs/contracts/schemas checked;
- DB models/migrations checked;
- auth/permissions checked;
- jobs/events/webhooks/queues checked;
- state/cache invalidation checked;
- tests checked;
- docs/source-of-truth checked;
- config/env/CI/deploy checked;
- commands run and outputs observed;
- commands not run and why;
- generated/vendor/build/binary exclusions;
- unknowns or inaccessible areas.

## Evidence ledger

For any non-trivial project conclusion, maintain this ledger:

| Area | Claim | Evidence | Full files read | Searches | Commands | Confidence | Unknowns |
|---|---|---|---|---|---|---|---|

Prefer fewer verified claims over many impressive but weak claims.

## Anti audit-theater

Do not produce impressive-sounding summaries without concrete evidence. Downgrade vague claims like "security looks good", "tests are sufficient", "the architecture is clean", or "this should be fine" unless backed by evidence.

## Command safety

Before running commands:

- prefer read-only commands first;
- do not run deploy, migrate, seed, reset, clean, rm, production, or external-state-changing commands without explicit approval;
- if a command may modify files or external systems, skip it or ask;
- if commands are not run, list the exact commands recommended and why they were skipped.

## Security checks

When relevant, check and report coverage for:

- secret exposure and unsafe logging;
- auth bypass;
- tenant/org isolation;
- server-side permission enforcement;
- IDOR/object ownership;
- input validation;
- file upload/path traversal/SSRF/deserialization risks;
- webhook signatures and replay protection;
- rate limits and abuse paths;
- dependency/config exposure.

## Monorepo checks

For monorepos, identify package graph, app boundaries, shared packages, cross-package imports, package scripts/tests/deploy paths, and avoid assuming one package's conventions apply globally.

## Stop condition

Before final conclusions, verify:

- active mode declared;
- scope covered or gaps listed;
- critical entrypoints checked or marked unknown;
- source-of-truth files checked if available;
- high-risk areas have evidence or are marked not checked;
- commands run safely or listed as not run;
- findings classified by evidence strength;
- unknowns are visible.

If not met, say the conclusion is partial.

## Finding classification

Classify findings as:

- Confirmed Bug;
- Risk;
- Improvement;
- Test Gap;
- Product QA Issue;
- Security Concern;
- Architecture Concern;
- Unknown.

A Confirmed Bug requires code-path evidence plus at least one cross-check: caller/consumer, contract/schema/type, test, docs/spec, DB model/migration, or reproducible scenario. Otherwise classify as Risk.

## Final rule

Never hide limitations. Never invent files, commands, tests, routes, APIs, behavior, architecture, or safety claims. Always distinguish what was read, searched, traced, validated, excluded, and still unknown.
