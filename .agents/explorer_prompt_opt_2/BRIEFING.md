# BRIEFING — 2026-05-22T20:25:00+07:00

## Mission
Optimize system prompts for CommentAnalyzer and LiveSessionAnalyzer with XML tags, CoT, and Vietnamese output matching existing schema.

## 🔒 My Identity
- Archetype: explorer
- Roles: Teamwork explorer
- Working directory: d:\Workspace\livestream\.agents\explorer_prompt_opt_2
- Original parent: cd8336cf-71af-49c3-aef0-45b06c8ab166
- Milestone: Prompt Optimization

## 🔒 Key Constraints
- Read-only investigation — do NOT implement.
- Design optimized prompts in English using XML tags, Chain-of-Thought (CoT) or few-shot examples.
- Ensure output is in Vietnamese.
- Ensure output matches existing JSON schema and PHP structures exactly for backward compatibility.
- Write analysis.md and handoff.md to d:\Workspace\livestream\.agents\explorer_prompt_opt_2\.

## Current Parent
- Conversation ID: cd8336cf-71af-49c3-aef0-45b06c8ab166
- Updated: 2026-05-22T20:25:00+07:00

## Investigation State
- **Explored paths**: `CommentAnalyzer.php`, `LiveSessionAnalyzer.php`, `AnalyzeCommentsJob.php`, `AnalyzeCommentsJobTest.php`, `LiveSessionAiInsightsTest.php`, `LiveSessionController.php`.
- **Key findings**:
  - `CommentAnalyzer.php` instructions must contain the Vietnamese substrings `"Chốt đơn"` and `"cú pháp đặt hàng"` to pass tests.
  - `AnalyzeCommentsJob.php` contains a duplicated/extended version of the comment analysis prompt that includes `session_note` and `extracted_keywords` in its output. Optimization must cover both versions.
  - `LiveSessionAnalyzer.php` expects a structured JSON input of recent comments, statistics, products, keywords, and memory, and must output a summary and alerts in Vietnamese.
- **Unexplored areas**: None.

## Key Decisions Made
- Designed system prompts in English using clean XML tags and Chain-of-Thought few-shot examples.
- Mandated Vietnamese outputs within the prompts to match the existing JSON schemas and database constraints.
- Provided fully rewritten proposed files as drop-in replacements for implementing agents.

## Artifact Index
- d:\Workspace\livestream\.agents\explorer_prompt_opt_2\original_prompt.md — User request
- d:\Workspace\livestream\.agents\explorer_prompt_opt_2\BRIEFING.md — Status and memory
- d:\Workspace\livestream\.agents\explorer_prompt_opt_2\progress.md — Liveness heartbeat
- d:\Workspace\livestream\.agents\explorer_prompt_opt_2\analysis.md — Detailed prompt designs and analysis
- d:\Workspace\livestream\.agents\explorer_prompt_opt_2\handoff.md — Handoff report
- d:\Workspace\livestream\.agents\explorer_prompt_opt_2\proposed_CommentAnalyzer.php — Proposed base agent file
- d:\Workspace\livestream\.agents\explorer_prompt_opt_2\proposed_LiveSessionAnalyzer.php — Proposed live session agent file
