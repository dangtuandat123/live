# BRIEFING — 2026-05-22T13:25:35Z

## Mission
Analyze and optimize system prompts for CommentAnalyzer and LiveSessionAnalyzer, designing them in English with XML tags and CoT/few-shot examples to output Vietnamese matching existing PHP structures.

## 🔒 My Identity
- Archetype: explorer
- Roles: Teamwork explorer, read-only investigator
- Working directory: d:\Workspace\livestream\.agents\explorer_prompt_opt_1
- Original parent: cd8336cf-71af-49c3-aef0-45b06c8ab166
- Milestone: Prompt Optimization

## 🔒 Key Constraints
- Read-only investigation — do NOT implement code changes.
- Analyze requirements for R1 (CommentAnalyzer) and R2 (LiveSessionAnalyzer).
- Design new system prompts in English using XML tags and Chain-of-Thought (CoT) or few-shot examples.
- Ensure output is in Vietnamese and matches the existing JSON schema and PHP class structures exactly for backward compatibility.
- Write analysis.md and handoff.md in working directory.
- Communicate results back to main agent using send_message.

## Current Parent
- Conversation ID: cd8336cf-71af-49c3-aef0-45b06c8ab166
- Updated: not yet

## Investigation State
- **Explored paths**:
  - `backend/app/Ai/Agents/CommentAnalyzer.php`
  - `backend/app/Ai/Agents/LiveSessionAnalyzer.php`
  - `backend/app/Jobs/AnalyzeCommentsJob.php`
  - `backend/app/Services/RunwareAiService.php`
  - `backend/tests/Feature/AnalyzeCommentsJobTest.php`
  - `backend/tests/Feature/LiveSessionAiInsightsTest.php`
  - `ORIGINAL_REQUEST.md`
- **Key findings**:
  - The existing test suite in `AnalyzeCommentsJobTest.php` checks if the `CommentAnalyzer` instructions contain the exact Vietnamese substrings `'Chốt đơn'` and `'cú pháp đặt hàng'`. Thus, the optimized English prompt must retain these terms as references to pass tests.
  - `AnalyzeCommentsJob.php` maintains its own prompt construction in `buildSystemPrompt()` which mirrors `CommentAnalyzer.php` but includes audio and session memory variables, as well as `session_note` and `extracted_keywords` output properties. Both prompts must be updated in sync.
  - Designed fully optimized system prompts in English utilizing XML tagging, detailed context limits, classification rules (specifically for Vietnamese nuances), Chain-of-Thought steps, and realistic few-shot examples.
- **Unexplored areas**: None.

## Key Decisions Made
- Chose to design proposed complete files for both `CommentAnalyzer.php` and `LiveSessionAnalyzer.php` as well as the job prompt helper in `AnalyzeCommentsJob.php` for seamless integration.

## Artifact Index
- `d:\Workspace\livestream\.agents\explorer_prompt_opt_1\original_prompt.md` — Holds the original prompt with UTC timestamp.
- `d:\Workspace\livestream\.agents\explorer_prompt_opt_1\proposed_CommentAnalyzer.php` — Proposed class with optimized system prompt.
- `d:\Workspace\livestream\.agents\explorer_prompt_opt_1\proposed_LiveSessionAnalyzer.php` — Proposed class with optimized system prompt.
- `d:\Workspace\livestream\.agents\explorer_prompt_opt_1\proposed_AnalyzeCommentsJob_buildSystemPrompt.php` — Proposed sync function with optimized system prompt.
