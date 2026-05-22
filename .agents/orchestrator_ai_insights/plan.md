# Plan - AI Insights & Alerts Implementation

## Task Decomposition
1. **Milestone 1: Database Migration**
   - Goal: Add `ai_insights` and `ai_alerts` fields.
   - Status: DONE
   - Verification: Run migration and check table columns.

2. **Milestone 2: App\Ai\Agents\LiveSessionAnalyzer.php**
   - Goal: Define the AI Agent with system prompt, instructions, and structured JSON output.
   - Status: DONE
   - Verification: PHPUnit test asserting class design and interfaces.

3. **Milestone 3: Backend Controller & Job Integration**
   - Goal: Integrate into `AnalyzeCommentsJob` and `LiveSessionController` with 30s throttle cache logic and a manual refresh endpoint.
   - Status: DONE
   - Verification: Call endpoints/jobs and mock RunwareAiService responses.

4. **Milestone 4: Frontend UI (Show.tsx) Updates**
   - Goal: Add panel elements for summary/alerts and "Cập nhật AI Insights" button.
   - Status: DONE
   - Verification: Successful npm build.

5. **Milestone 5: E2E and Unit Verification**
   - Goal: Run all tests and verify all Acceptance Criteria are met.
   - Status: DONE
   - Verification: Clear audit logs, passing test results.

6. **Milestone 6: Fix Quality Review Findings**
   - Goal: Fix High/Medium/Low review findings in Controller, Agent, View, and Tests.
   - Status: DONE
   - Verification: All tests pass, build compiles, and quality reviewer/auditor verify the fixes.

