# Scope: Redesign Audio Analysis to Multi-modal Pipeline

## Architecture
- **Backend (Laravel)**:
  - `AnalyzeCommentsJob.php`: AI-based processing of comments and audio cues from live streams. Communicates with Gemini multimodal model.
  - `LiveSession.php`: Model representing a live stream session, storing session data, stats, and metadata like `last_audio_cues`.
  - `LiveSessionController.php`: Exposes API endpoints for retrieving live session data (via polling).
- **Frontend (React + Inertia)**:
  - `Show.tsx`: Livestream dashboard page displaying session state, charts, insights, and the `AudioAnalysisCard`.

## Milestones
| # | Name | Scope | Dependencies | Status |
|---|------|-------|-------------|--------|
| 1 | Exploration & Alignment | Analyze models, jobs, tests, and component layout for audio analysis | none | DONE |
| 2 | Database Migration & Model | Create database migration for `last_audio_cues` column and update fillable in LiveSession model | M1 | IN_PROGRESS (Conv: 76f4a419-98fc-4e81-91f6-df405aa8ef3d) |
| 3 | Backend AI Integration | Update AnalyzeCommentsJob prompt to request and process `audio_cues` from Gemini | M2 | IN_PROGRESS (Conv: 76f4a419-98fc-4e81-91f6-df405aa8ef3d) |
| 4 | Frontend UI Redesign | Redesign AudioAnalysisCard in Show.tsx to show real audio cues and clean up mockup items | M3 | IN_PROGRESS (Conv: 76f4a419-98fc-4e81-91f6-df405aa8ef3d) |
| 5 | Verification & Audit | Run all backend tests, frontend builds, and Forensic Auditor verification | M4 | PLANNED |

## Interface Contracts
### AnalyzeCommentsJob ↔ Gemini AI
- **Request Prompt**: Include system prompt rules to output `"audio_cues"` (string or null, max 200 characters in Vietnamese) summarizing what the speaker said/room state based on the 3s audio snippet.
- **Response Format**: JSON containing `"audio_cues": string | null`.

### LiveSessionController ↔ Show.tsx
- **Session Prop**: Page props and event fetch payload contain the `last_audio_cues` field on the `session` object.
