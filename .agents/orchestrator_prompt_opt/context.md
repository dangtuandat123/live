# Context - AI Prompt Optimization

## Key Files
- `backend/app/Ai/Agents/CommentAnalyzer.php`: Analyzes individual live comments.
- `backend/app/Ai/Agents/LiveSessionAnalyzer.php`: Analyzes the overall live session for insights and alerts.
- `backend/tests/`: Unit/Integration tests directory.

## System Boundaries
- Codebase language: PHP (Laravel)
- LLM response requirements: Vietnamese JSON responses adhering strictly to the predefined JSON Schema of each analyzer class.
- System prompt format requirement: English language system prompt using XML tag structure and Chain-of-Thought (CoT) or few-shot examples.
