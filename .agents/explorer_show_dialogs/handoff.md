# Handoff Report — Explorer Dialogs & Banners

## Summary
This report analyzes and details the implementation plan for the following UI enhancements in the livestream interface (`Lives/Show.tsx`):
1. **Low Time Warning Banner**: Displays an amber warning banner when the active session has consumed >=85% or has <10 minutes remaining of the package's max duration.
2. **Upgrade Duration Dialog Update**: Adds clear messaging indicating that livestream history and analysis are safely stored and accessible/exportable at any time.
3. **Low Credits Alert Banner**: Displays an amber banner warning when >=90% of AI credits are used (when limit != -1).
4. **Audio Analysis Gating UI**: Adds a visual placeholder card for Audio Analysis in the sidebar, displaying a locked/upgrade badge if the package feature is missing, and triggers a unified upgrade dialog when clicked.

---

## Project Coverage Report

- **Active Mode**: Core Mode
- **Declared Scope**: `backend/resources/js/Pages/Lives/Show.tsx`, `backend/resources/js/Pages/Subscription/Index.tsx`
- **Full Files Read**:
  - `d:\Workspace\livestream\backend\resources\js\Pages\Lives\Show.tsx`
- **Files Scanned / Read Partially**:
  - `d:\Workspace\livestream\backend\resources\js\Pages\Subscription\Index.tsx` (lines 104-635)
  - `d:\Workspace\livestream\backend\app\Jobs\AnalyzeCommentsJob.php` (lines 150-200, 560-630)
- **Repo-wide searches performed**:
  - Search for `audio` in files
  - Search for `subscription` in `Show.tsx`
  - Search for `audio` in `Lives/Index.tsx`
- **Entrypoints checked**: Livestream show route (`/lives/{id}`)
- **DB models / migrations checked**: `SubscriptionPackage` model features list (`audio_analysis`, `export_leads`, `max_duration_hours`, `ai_credits`)
- **State / cache invalidation checked**: React Context state propagation (`LiveContext`)
- **Tests checked**: None checked (client-side React components).

---

## Evidence Ledger

| Area | Claim | Evidence | Full files read | Searches | Commands | Confidence | Unknowns |
|---|---|---|---|---|---|---|---|
| Gating | Subscription data contains features | `auth.subscription.features` defined in `Show.tsx` (lines 2958-2978) | Yes | Yes | None | High | None |
| Duration | Duration is updated in real-time | Polling payload contains `duration` string `HH:MM:SS` (lines 3221-3222) | Yes | Yes | None | High | None |
| Dialog | Dialog can be unified via context | `LiveContext` provider wraps `LivesShow` (lines 300-305, 3280-3290) | Yes | Yes | None | High | None |

---

## 5-Component Handoff Report

### 1. Observation
From static analysis of `d:\Workspace\livestream\backend\resources\js\Pages\Lives\Show.tsx`:
*   **Subscription Features**: The type `SubscriptionFeature` includes `max_duration_hours`, `ai_credits`, `export_leads`, and `audio_analysis` (lines 2958-2965):
    ```typescript
    interface SubscriptionFeature {
        limit_streams: number;
        max_duration_hours: number;
        ai_credits: number;
        audio_analysis: boolean;
        export_leads: boolean;
    }
    ```
*   **Dialogs**: Currently, the `Upgrade Duration Dialog` and `Upgrade Credits Dialog` are defined inline inside the main component `LivesShow` (lines 3832-3977). However, the general `Upgrade Dialog` for locked features is placed locally inside `CustomersPanel` (lines 1970-2006).
*   **Existing Banners**: Warnings such as "Offline" and "Session Error" are displayed above the video panel (lines 3341-3386).
*   **Live Context**: Subcomponents like `CustomersPanel` pull data using the `useLiveData()` hook which hooks into `LiveContext` (lines 300-305).
*   **Audio Analysis Job**: The backend checks for the `audio_analysis` feature in `AnalyzeCommentsJob.php` to decide whether to fetch base64 audio and send it to the AI (lines 153-154).

### 2. Logic Chain
*   **Banner Triggering**: Since `session.duration` is a string (e.g. `"01:23:45"`), we must convert it to total elapsed seconds to compare against the package's limit `max_duration_hours` (in seconds).
*   **Warning Conditions**:
    *   Elapsed ratio `>= 0.85` OR remaining time `< 600 seconds` (10 minutes) triggers the Low Time Warning.
    *   Credits used ratio `used_ai_credits / ai_credits >= 0.9` (when `ai_credits !== -1`) triggers the Low Credits Warning.
*   **Dialog Consolidation**: Since we need an Upgrade Dialog for both "Export Leads" and "Audio Analysis" UI elements, lifting the local state from `CustomersPanel` to the parent `LivesShow` and exposing `triggerUpgradeDialog` via `LiveContext` avoids duplicating markup and provides a unified gating interface.
*   **Locked Audio UI**: Placing a placeholder Card in the left column of the live dashboard offers an intuitive visual indicator. If the feature is missing, it displays a lock icon and upgrade badge; if active, it displays a live waveform pulse animation.

### 3. Caveats
*   The logic assumes `session.duration` is formatted as `"HH:MM:SS"` or `"MM:SS"`. If the format changes, the parser helper function needs updating.
*   No other pages require gating indicators for `audio_analysis` since the actual speech processing happens automatically on the backend during a live session.

### 4. Conclusion
We have created a ready-to-apply patch file: `d:\Workspace\livestream\.agents\explorer_show_dialogs\proposed_changes.patch`.
Applying this patch will correctly:
*   Add the `Volume2` icon import.
*   Extend `LiveContext` to provide `triggerUpgradeDialog`.
*   Unify the feature upgrade dialog in `LivesShow` and wire it up to `CustomersPanel` and `AudioAnalysisUI`.
*   Render the two new warning banners (Low Time, Low Credits) at the top of the session view.
*   Add the Audio Analysis status card to the left panel.
*   Render the database storing clarification message inside `UpgradeDurationDialog`.

### 5. Verification Method
1.  **Code Inspection**: Verify that the patch applies clean without syntax/type conflicts.
2.  **Mocking Session States**:
    *   Force `session.status = 'live'` and `session.duration = '00:52:00'` while `subscription.features.max_duration_hours = 1` to assert that the Low Time Warning banner displays (85% consumed).
    *   Set `subscription.used_ai_credits = 950` and `subscription.features.ai_credits = 1000` to assert that the Low Credits Warning banner displays.
    *   Inspect `UpgradeDurationDialog` to verify the presence of the green notification text regarding database storage.
    *   Verify clicking on the Audio Analysis card triggers the Upgrade Dialog with correct copy, and clicking the button takes the user to `/subscription`.
