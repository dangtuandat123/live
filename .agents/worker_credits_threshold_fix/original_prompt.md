## 2026-05-22T14:46:57Z

Objective: Fix the minor credits progress bar warning threshold discrepancy in `backend/resources/js/Components/app-sidebar.tsx` and run verification.

Tasks:
1. In `backend/resources/js/Components/app-sidebar.tsx`, locate the `SidebarCredits` component.
2. In the `Progress` bar component logic (around line 127):
   Change the threshold check:
   From:
   percentage >= 75 ? 'bg-amber-500'
   To:
   percentage >= 80 ? 'bg-amber-500'
   This strictly aligns with the user's requirement (color the sidebar progress bar amber under 20% remaining, which is between >=80% and <90% used).
3. Verify the changes:
   - Run typecheck: `npx tsc --noEmit` under the `backend/` directory to ensure type safety.
   - Run build: `npm run build` under the `backend/` directory to ensure production bundler succeeds.
   - Run tests: `php artisan test` under the `backend/` directory to confirm 100% of unit/feature tests pass.
4. Output your results and verification details in your handoff file under your directory.
