# Scope: AI Prompt Optimization

## Architecture
- **CommentAnalyzer (`backend/app/Ai/Agents/CommentAnalyzer.php`)**: Agent containing the system prompt for analyzing TikTok livestream comments.
- **LiveSessionAnalyzer (`backend/app/Ai/Agents/LiveSessionAnalyzer.php`)**: Agent containing the system prompt for high-level live session summary and alerts.
- **AnalyzeCommentsJob (`backend/app/Jobs/AnalyzeCommentsJob.php`)**: Background job that builds a duplicated system prompt inside `buildSystemPrompt()`.

## Milestones
| # | Name | Scope | Dependencies | Status |
|---|------|-------|-------------|--------|
| 1 | Exploration & Design | Analyze existing prompts and design optimized English versions with CoT and XML tags. | none | DONE |
| 2 | Implementation | Replace prompts in CommentAnalyzer, LiveSessionAnalyzer, and AnalyzeCommentsJob. | M1 | DONE |
| 3 | Verification | Run syntax checks, PHPUnit test suites, npm run build, and Forensic Auditor. | M2 | DONE |

## Interface Contracts
- Output schemas of `CommentAnalyzer` and `LiveSessionAnalyzer` must remain completely unchanged to ensure backward compatibility.
- Language of system instructions must be in English.
- Output text must remain in Vietnamese.
- Required substring assertions in `AnalyzeCommentsJobTest` ('Chốt đơn' and 'cú pháp đặt hàng') must be preserved.
