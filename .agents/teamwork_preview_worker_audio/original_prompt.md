## 2026-05-22T15:16:03Z
You are teamwork_preview_worker_audio.
Your working directory is d:\Workspace\livestream\.agents\teamwork_preview_worker_audio\.

MANDATORY INTEGRITY WARNING:
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A Forensic Auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.

Your objective is to implement the following changes in the workspace:

1. Database Migration:
   - Create a Laravel database migration to add a `last_audio_cues` column (type: text, nullable) to the `live_sessions` table.
   - Run the migration using php artisan migrate.

2. Model Update:
   - Update `backend/app/Models/LiveSession.php` to include `last_audio_cues` in the `$fillable` array.

3. Backend AI Integration in `AnalyzeCommentsJob.php`:
   - Update the system prompt in `AnalyzeCommentsJob.php` to request a new property `"audio_cues"` (string or null, max 200 characters in Vietnamese) in the JSON response from Gemini.
   - The prompt should instruct Gemini to listen to the 3-second audio clip and briefly summarize what the host/streamer is describing, holding, saying, or the room status.
   - Extract the `"audio_cues"` value from the AI JSON response and save it to the `last_audio_cues` attribute of the `LiveSession` model. Make sure to handle potential JSON parsing issues, missing fields, or empty values safely.

4. Frontend UI Update in `backend/resources/js/Pages/Lives/Show.tsx`:
   - Redesign `AudioAnalysisCard` to reflect the multi-modal audio analysis features.
   - When the user's subscription supports audio analysis (`audio_analysis` is active):
     - Display a bright green/emerald badge: "Đa phương thức (Text + Audio + Memory) hoạt động".
     - Display the actual text heard by the AI: "AI vừa nghe thấy: \"[last_audio_cues]\"" (using the live session's `last_audio_cues` prop).
     - If `last_audio_cues` is null or empty, display a friendly waiting message: "Đang lắng nghe livestream để phân tích ngữ cảnh...".
     - Display the last analysis time (e.g. "Cập nhật [X] giây trước" or based on polling/updates).
     - Keep the active animated green sound wave (waveform).
     - Remove mockup controls that do not have functional backings, specifically: the input device selector dropdown and the volume slider.
   - When subscription does not support audio analysis (`audio_analysis` is locked):
     - Keep the locked overlay and the upgrade Dialog.

5. Test Suite Updates in `backend/tests/Feature/AnalyzeCommentsJobTest.php`:
   - Update/add test cases to verify that:
     - The AI prompt receives/requests the `"audio_cues"` field.
     - The database correctly stores the `"audio_cues"` returned from the AI mock response into the `last_audio_cues` column of the session.
     - The fallback behavior works correctly when audio is not available.

6. Verification:
   - Run Laravel unit/feature tests: `php artisan test`
   - Compile the frontend to check for any TypeScript/Inertia/build issues: `npm run build`

Write your handoff report to d:\Workspace\livestream\.agents\teamwork_preview_worker_audio\handoff.md with all implementation details, commands run, and verification results.

When you are done, send a message to the orchestrator (conversation ID: c2ad0427-e738-4860-bcd8-711923fb38c2) with the path to your handoff.md.
