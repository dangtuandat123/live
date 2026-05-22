# BRIEFING — 2026-05-22T20:26:00+07:00

## Mission
Analyze requirements and design optimized system prompts in English using XML tags and Chain-of-Thought/Few-shot examples for CommentAnalyzer.php and LiveSessionAnalyzer.php, producing output in Vietnamese and ensuring compatibility with existing JSON schemas.

## 🔒 My Identity
- Archetype: explorer
- Roles: analyzer, prompt engineer, reviewer
- Working directory: d:\Workspace\livestream\.agents\explorer_prompt_opt_3
- Original parent: cd8336cf-71af-49c3-aef0-45b06c8ab166
- Milestone: prompt optimization

## 🔒 Key Constraints
- Read-only investigation — do NOT implement
- Outputs must target Vietnamese translation and match existing JSON schema and PHP class structures exactly.
- All reports must be placed in d:\Workspace\livestream\.agents\explorer_prompt_opt_3\ as analysis.md and handoff.md.

## Current Parent
- Conversation ID: cd8336cf-71af-49c3-aef0-45b06c8ab166
- Updated: 2026-05-22T20:26:00+07:00

## Investigation State
- **Explored paths**:
  - `backend/app/Ai/Agents/CommentAnalyzer.php`: Analysis of structure, instructions prompt, and target JSON schema.
  - `backend/app/Ai/Agents/LiveSessionAnalyzer.php`: Analysis of input setters, instructions prompt, and target JSON schema.
  - `backend/app/Jobs/AnalyzeCommentsJob.php`: Detected duplicated prompt under `buildSystemPrompt()`.
  - `backend/resources/js/Pages/Lives/Show.tsx`: Checked frontend rendering structure of `ai_insights` and `ai_alerts`.
  - `backend/tests/Feature/AnalyzeCommentsJobTest.php`: Reviewed test coverage and constraints on required prompt substrings.
- **Key findings**:
  - `CommentAnalyzer.php` outputs a JSON with `results` only, but the main job `AnalyzeCommentsJob.php` builds its own prompt containing `results`, `session_note`, and `extracted_keywords`. Syncing both is essential.
  - Test suites enforce the presence of exact substrings: `"Chốt đơn"`, `"cú pháp đặt hàng"`, and `"session_note"`. The optimized prompt designs carefully integrate these strings.
- **Unexplored areas**: None.

## Key Decisions Made
- Use XML tags, English instruction phrasing, and CoT/Few-shot examples.
- Keep output in Vietnamese matching the existing enums and schemas.
- Sync prompts across both AI agents and the backend job.

## Artifact Index
- d:\Workspace\livestream\.agents\explorer_prompt_opt_3\analysis.md — Detailed prompt analysis and new prompt design
- d:\Workspace\livestream\.agents\explorer_prompt_opt_3\handoff.md — Handoff report with findings, logic chain, and verification steps
