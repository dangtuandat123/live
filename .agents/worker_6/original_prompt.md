## 2026-05-21T14:24:52Z
**Context**: You are the Codebase Implementer working under the Project Orchestrator in directory d:\Workspace\livestream\.agents\worker_6.
**Objective**: Fix the concurrency unique lock, case-insensitivity, and transaction boundary defects in `backend/app/Jobs/AnalyzeCommentsJob.php` and update the adversarial tests in `backend/tests/Feature/AnalyzeCommentsJobAdversarialTest.php` to assert correct behavior.
**Target Files to modify**:
- backend/app/Jobs/AnalyzeCommentsJob.php
- backend/tests/Feature/AnalyzeCommentsJobAdversarialTest.php

**Instructions**:
1. Load and apply the instructions from the Laravel Best Practices skill at d:\Workspace\livestream\.agents\skills\laravel-best-practices\SKILL.md when editing the Laravel PHP code.
2. Modify `backend/app/Jobs/AnalyzeCommentsJob.php`:
   - **Manual Unique Locking**:
     - Remove `implements ShouldBeUnique` from the class declaration (so it only implements `ShouldQueue`).
     - Remove the `$uniqueFor` property.
     - At the start of `handle()`, acquire a manual lock using cache:
       ```php
       $lockKey = 'analyze-comments-lock-' . $this->liveSessionId;
       $lock = cache()->lock($lockKey, 120);
       if (!$lock->get()) {
           return;
       }
       ```
     - Initialize a boolean flag `$dispatchedNext = false;` at the top of `handle()`.
     - In the main try block and catch block, when dispatching the next job:
       - Release the manual lock: `$lock->release();`
       - Set `$dispatchedNext = true;`
       - Dispatch: `self::dispatch($this->liveSessionId)->delay(now()->addSeconds(2));`
       - Remove any old cache clearing or container-resolved unique lock release calls.
     - Wrap the entire body of `handle()` in a `try...finally` block (or update the outer try-catch-finally block) such that in the `finally` block, we release the lock if it was not already released:
       ```php
       finally {
           if (isset($lock) && !$dispatchedNext) {
               $lock->release();
           }
       }
       ```
   - **Case-Insensitive Exception Check**:
     - In the catch block of `handle()`, convert `$e->getMessage()` to lowercase before checking for retryable exception keywords:
       ```php
       $errMsg = strtolower($e->getMessage());
       $isUnrecoverable = !str_contains($errMsg, 'rate limit') && 
                          !str_contains($errMsg, 'timeout') && 
                          !str_contains($errMsg, 'connection');
       ```
   - **Atomic Transaction & Stats Aggregation**:
     - In the main execution loop, move the `$this->updateAggregateStats(...)` call INSIDE the `DB::transaction(...)` callback block, placing it at the very end of the block.
     - Inside `DB::transaction()`, calculate the new leads count *before* executing the bulk comments update query (by checking existing leads on the unmodified database comments), store it in `$newLeadsCount`, and build the `$batchStats` array.
     - Simplify `updateAggregateStats` to accept `$session` and `$batchStats`. Perform the atomic increment updates directly via `live_stats` table update. No need to query leads here anymore since they are pre-calculated.
3. Modify `backend/tests/Feature/AnalyzeCommentsJobAdversarialTest.php`:
   - Update `test_case_sensitivity_bug_causes_rate_limit_to_be_treated_as_unrecoverable` to `test_rate_limit_is_treated_as_retryable_regardless_of_case`. Throw `"Rate limit exceeded."` (capitalized) and assert that the comment remains UNPROCESSED (i.e. retryable behavior matches lowercase).
   - Update `test_stats_out_of_sync_on_stats_update_failure` to `test_transaction_rolls_back_on_stats_update_failure`. Assert that if stats update fails, the comment is NOT marked processed (reverting the comment update back to unprocessed via database rollback).
4. Run the test suite to make sure all 42 tests pass cleanly:
   `php artisan test`

**MANDATORY INTEGRITY WARNING**:
> DO NOT CHEAT. All implementations must be genuine. DO NOT
> hardcode test results, create dummy/facade implementations, or
> circumvent the intended task. A Forensic Auditor will independently
> verify your work. Integrity violations WILL be detected and your
> work WILL be rejected.

**Output Requirements**:
Write a detailed report named handoff.md in your working directory `d:\Workspace\livestream\.agents\worker_6` detailing:
1. Exact changes made to `AnalyzeCommentsJob.php` and `AnalyzeCommentsJobAdversarialTest.php`.
2. Commands run to execute the tests and their outputs.
3. Verification results confirming all 42 tests pass.
**Report Back**: Send a message to the Project Orchestrator (conversation ID: a88491d0-5eb1-46f2-88b4-738be87777f3) once your changes are complete and tests pass.
