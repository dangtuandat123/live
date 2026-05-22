## 2026-05-22T07:40:00Z
Your working directory is d:\Workspace\livestream\.agents\teamwork_preview_explorer_ui_sync. Your mission is to analyze the codebase for the 5 requirements in Follow-up — 2026-05-22T14:38:26Z in ORIGINAL_REQUEST.md.

Target Files to Inspect:
- backend/app/Http/Controllers/LiveSessionController.php
- backend/app/Jobs/AnalyzeCommentsJob.php
- backend/app/Models/LiveSession.php
- backend/resources/js/Pages/Lives/Show.tsx

Requirements to Analyze:
1. R1: Conversion Funnel Distortion: Inspect where potentialCustomers are retrieved in LiveSessionController (show and fetchEvents) and see how they are limited. Check how the frontend calculates the phễu (funnel) stages and see how to introduce a separate potential_customers_count (or leverage stats.leads_count or calculate it correctly) to avoid funnel distortion (Stage 3 "Có SĐT/ĐC" vs Stage 4 "Chốt đơn").
2. R2: Labeling Alignment: Look at the labels "KH tiềm năng", "Có SĐT/ĐC", "Chốt đơn" in the frontend (Lives/Show.tsx) and the corresponding fields in backend stats. Identify semantic inconsistencies (e.g. stats.leads_count is shown as "KH tiềm năng" in quick stats, but Stage 4 in funnel is "Chốt đơn", and the potentialCustomers list contains both chốt đơn and phone-only customers). Propose how to align the terms cleanly.
3. R3: Cache Invalidation Bug: Check how show and fetchEvents use cache. Look at updateEvent and the end of AnalyzeCommentsJob handles. Propose the cache keys to invalidate and the exact lines to add invalidation code.
4. R4: Redundancy & Clean Code: Identify where sentiment charts are duplicated (left column vs statistics tab). Check the redundant keywords prop/relation and how it can be removed. Check the "Từ khóa được nhắc nhiều" (Top Keywords) card to see why it merges topQuestions and topProducts instead of actual keywords.
5. R5: Phone Extraction Regex vs AI Synchronization: Check how fetchAndStoreEvents uses Regex 0\d{9,10} to extract has_phone, and how AnalyzeCommentsJob processes the comments and overwrites has_phone. Propose a synchronization logic so that if Regex already set has_phone = true, AI does not overwrite it to false.

Output requirements:
Write your findings to d:\Workspace\livestream\.agents\teamwork_preview_explorer_ui_sync\analysis.md and then send a handoff report path back to the parent orchestrator via send_message.
