# Handoff Report: Investigation of Audio Analysis Support

## Summary
This investigation analyzes the database schema, models, backend jobs, test suites, and frontend components related to the **Audio Analysis** feature.
- **Key Finding**: The backend implements server-side audio capture of the TikTok live stream using FFmpeg (via the Python service) and performs multimodal analysis using `RunwareAiService` (Gemini model). However, the frontend `AudioAnalysisCard` is a complete client-side mockup showing local microphone device selection and volume controls, which is a conceptual mismatch with the server-side livestream capture.
- **Suggested Action**: Instructions are provided for the worker agent to align the frontend UX with the backend service-side capture behavior and improve diagnostic coverage.

---

## 1. Observation

Direct code references and observed data:

### A. Database Schema
- **Migration files**:
  - `backend/database/migrations/2026_05_21_000002_create_live_sessions_table.php` defines the table structure of `live_sessions` (lines 11-26).
  - `backend/database/migrations/2026_05_21_202200_add_ai_context_summary_to_live_sessions.php` adds `ai_context_summary` (lines 11-13).
  - `backend/database/migrations/2026_05_22_095753_add_ai_insights_and_alerts_to_live_sessions_table.php` adds `ai_insights` and `ai_alerts` (lines 14-17).
- **Result**: No audio-specific columns exist inside `live_sessions`. The audio analysis feature is entirely controlled via subscription package capability flags (stored in users/subscriptions metadata).

### B. LiveSession Model
- `backend/app/Models/LiveSession.php` (lines 13-29) defines the `$fillable` array. It includes:
  ```php
  'user_id', 'name', 'platform', 'tiktok_username', 'status',
  'tiktok_session_id', 'room_id', 'thumbnail', 'duration_seconds',
  'started_at', 'ended_at', 'error_message', 'ai_context_summary',
  'ai_insights', 'ai_alerts'
  ```
- No audio-specific attributes are defined or cast within the model.

### C. AnalyzeCommentsJob (Backend Comment & Audio Analysis Pipeline)
- Located at `backend/app/Jobs/AnalyzeCommentsJob.php`.
- **Comment batching**: Comments are processed in batches of 50 (lines 95-100):
  ```php
  $unprocessed = LiveEvent::where('live_session_id', $this->liveSessionId)
      ->where('event_type', 'comment')
      ->where('ai_processed', false)
      ->orderBy('event_at')
      ->limit(50)
      ->get();
  ```
- **Audio Capture**: Check for subscription capability and TikTok session ID (lines 151-170):
  ```php
  // === Audio Capture: Lấy audio 3s từ livestream qua Python FFmpeg ===
  $audioB64 = null;
  $audioAnalysisEnabled = $features['audio_analysis'] ?? false;
  if ($audioAnalysisEnabled && $session->tiktok_session_id) {
      try {
          $snapshot = $tiktokService->getSnapshot($session->tiktok_session_id);
          $audioB64 = $snapshot ? ($snapshot['audio_b64'] ?? null) : null;
          if ($audioB64) {
              Log::info('Audio captured for AI analysis', [ ... ]);
          }
      } catch (\Throwable $snapEx) {
          Log::warning('Audio capture failed, falling back to text-only', [ ... ]);
      }
  }
  ```
- **Multimodal AI request**: Prompts are sent with text inputs and optional base64 audio payload (lines 183-203):
  ```php
  // === Build multimodal parts: text + audio (nếu có) ===
  $parts = [RunwareAiService::text($userMessage)];
  if ($audioB64) {
      $parts[] = RunwareAiService::audioBase64($audioB64, 'mp3');
  }

  $response = $runware->chatMultimodal(
      systemPrompt: $systemPrompt,
      parts: $parts,
      temperature: 0,
      maxTokens: 4096,
  );
  ```
- **Gemini System Prompt (buildSystemPrompt)**: Lines 601-613 add the `AUDIO LIVESTREAM` instruction block if `$hasAudio` is true:
  ```php
  === AUDIO LIVESTREAM ===
  You are also provided with a 3-second audio clip from the livestream. Listen to it to identify:
  - Which product the host/streamer is currently describing or holding (use this to match product_tag accurately).
  - Whether a minigame, number guessing game, or giveaway is running (comments containing digits or short text during this time may just be game participation, not purchase orders).
  - The tone, vocal context, and words spoken by the host to better interpret viewer comments.
  If the audio is noisy or unclear, ignore it and analyze based on raw text.
  ```

### D. Audio Analysis Testing
- **Test files**:
  - `backend/tests/Feature/AnalyzeCommentsJobTest.php` contains tests like `test_audio_fallback_to_text_only` (line 171) and `test_audio_present_adds_audio_section_and_part` (line 354).
  - These verify that when audio capture fails (e.g. VPS service returns null or throws exception), prompt fallback works correctly without the audio instructions block and with a single text-only part.

### E. AudioAnalysisCard (Frontend Layout & Controls)
- Located at `backend/resources/js/Pages/Lives/Show.tsx` (lines 2962-3084).
- **Subscription gate**: Checked via Inertia page props (lines 2965-2967):
  ```typescript
  const subscription = auth?.subscription;
  const isAudioAnalysisEnabled = subscription?.features?.audio_analysis ?? false;
  ```
- **Mockup controls**: Uses React local states with mockup indicators (lines 2969-2971):
  ```typescript
  const [isMuted, setIsMuted] = React.useState(false);
  const [selectedDevice, setSelectedDevice] = React.useState('default');
  const [volume, setVolume] = React.useState(70);
  ```
- **Local microphone / Device selection**: Lines 3010-3023 render a microphone selector:
  ```html
  <label className="text-muted-foreground text-[10px] font-medium">Thiết bị đầu vào</label>
  <select disabled={!isAudioAnalysisEnabled} ...>
      <option value="default">Microphone mặc định</option>
      <option value="comm">Communications Microphone</option>
  </select>
  ```
- **Conceptual Mismatch**:
  * **Actual Backend Behavior**: The system captures audio from the livestream on the server (via python service connecting to the TikTok livestream link).
  * **Frontend UI Behavior**: The mockup displays controls for selecting client-side microphones (`Microphone mặc định`, `Communications Microphone`) and volume/mute controls, which has no technical connection or relevance to the server-side TikTok capture pipeline.

---

## 2. Logic Chain

1. **Schema Check**: We observed migrations for `live_sessions` and noticed that no table columns specify audio configurations or audio status flags (Observation A & B).
2. **Backend Logic**: In `AnalyzeCommentsJob.php`, audio capture runs automatically if the subscriber has the `audio_analysis` feature enabled and the session has `tiktok_session_id` set (Observation C). It fetches audio snapshots from the server-side endpoint (`/sessions/{sessionId}/snapshot`) (Observation C).
3. **Frontend Mismatch**: In `Show.tsx`, the `AudioAnalysisCard` has simulated controls for client-side local microphones and volume sliders (Observation E). Because the backend receives the livestream audio stream directly from TikTok, these client-side controls are conceptually irrelevant and do not change server-side snapshot captures.
4. **Conclusion**: The frontend should be adjusted to present server-side stream status information rather than misleading local hardware device controls.

---

## 3. Caveats

- We did not inspect the Python VPS backend codebase. We assume it correctly captures audio via FFmpeg when `/sessions/{sessionId}/snapshot` is requested and returns it as base64 encoded MP3 in the `audio_b64` property.
- The subscription settings model (`User::getSubscriptionFeatures`) is the only gatekeeper enabling the backend audio capture pipeline.

---

## 4. Conclusion

The backend pipeline for audio analysis is fully functional, using a multimodal prompt with the Gemini model on Runware AI, fallback capability, and integration test coverage. The primary area requiring attention is the **Frontend UI/UX implementation**, where the `AudioAnalysisCard` needs to be refactored to reflect that audio is captured directly from the TikTok livestream rather than the client's local microphone.

---

## 5. Instructions for the Worker Agent

### Target task: Refactor Frontend and Backend Diagnostic Support for Audio Analysis

Please implement the following changes:

#### Step 1: Refactor `AudioAnalysisCard` in `backend/resources/js/Pages/Lives/Show.tsx`
1. **Remove Client-side Microphone Settings**:
   - Delete the local device dropdown selector (`<select>` for "Thiết bị đầu vào") and the volume slider.
2. **Implement Server-side Status Panel**:
   - Replace mockup state variables (`selectedDevice`, `volume`) with server-side indicators.
   - Display a status indicator reflecting whether audio capture is actively running. For example:
     - **Active**: Stream is live, and audio capture is configured.
     - **Fallback (Text-Only)**: The subscription permits it, but the last snapshot failed or the stream is offline.
     - **Locked**: Subscription does not support audio analysis.
   - The visual audio wave animation should animate when status is **Active** and the stream is in `live` status.
3. **Show Last Captured Timestamp (Optional / Nice to have)**:
   - Introduce a small text showing "Lần bắt âm thanh cuối: [Time]" using the latest event or comment processing timestamp.

#### Step 2: Expose Audio Analysis Status via controller
1. In `backend/app/Http/Controllers/LiveSessionController.php`, verify if we can return a flag `audio_capture_status` in the `fetchEvents` JSON response.
2. This status flag should return:
   - `'active'` if `$session->status === 'live'` and the user subscription features have `audio_analysis === true`.
   - `'fallback'` if the last audio snapshot retrieval failed (e.g. logging status) or has issues.
   - `'inactive'` if the stream is offline or features are disabled.

#### Step 3: Run Verification Tests
- Run PHPUnit tests to make sure there are no regression issues:
  ```powershell
  ./vendor/bin/phpunit
  ```
