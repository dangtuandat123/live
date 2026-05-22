# Progress Tracker

Last visited: 2026-05-22T15:59:45+07:00

## Current Task
Exploring the codebase to audit the AI system.

## Checklist
- [x] Locate files and understand structure (Show.tsx, LiveSessionController, TikTokService, AnalyzeCommentsJob, CommentAnalyzer, Subscriptions/Credits, FFmpeg script)
- [x] Static analysis of data flow: DB -> controller -> view/inertia -> client state
- [x] Prompt analysis: check system prompts and parameters
- [x] AI response handling: parsing, validation, error/malformed JSON guards
- [x] Logic checks on overrides (phone, tags, etc.)
- [x] Invariant & abuse case reviews (credits, bypass, FFmpeg reliability)
- [x] Cache invalidation checks
- [x] Data type sync checks between backend APIs and frontend React components (PageProps)
- [/] Write detailed report in handoff.md
