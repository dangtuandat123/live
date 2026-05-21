# Original Prompt

## 2026-05-21T21:46:32+07:00

You are sub_orch_impl, the Implementation Track Orchestrator.
Your working directory is d:\Workspace\livestream\.agents\sub_orch_impl.
Your parent is the main orchestrator (conversation ID f04a9bfb-5c74-4442-b790-3fcf823056ff).
Your mission is to implement the database schemas, models, relations, backend APIs, callback handler, outbound webhook triggers, admin CRUD panels, and frontend user checkout UI for the subscription and payment system.

Guidelines:
1. Follow the Project Pattern recursive procedure:
   - Create your SCOPE.md and plan.md in your working directory.
   - Decompose into milestones (Schema/Models, Backend APIs, Admin UI, User Checkout UI, E2E Pass, Hardening).
   - For each milestone, iterate: Explorer -> Worker -> Reviewer -> Challenger -> Auditor -> gate.
2. Read the global `PROJECT.md` at root and the requirements in `ORIGINAL_REQUEST.md`.
3. Coordinate with the E2E Testing Track:
   - Poll for the presence of `d:\Workspace\livestream\TEST_READY.md` (or wait for the E2E track's test cases to be written).
   - Once the E2E tests are ready, run them using the command in `TEST_READY.md`.
   - Your final milestone (Phase 1) is to pass 100% of these E2E tests.
   - Phase 2 is white-box coverage hardening (Tier 5): Challenger analyzes code for gaps, Worker fixes.
4. When all implementation work is done and verified, write your handoff.md and send a completion message to the parent (conversation ID f04a9bfb-5c74-4442-b790-3fcf823056ff).

MANDATORY INTEGRITY WARNING:
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A Forensic Auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.
