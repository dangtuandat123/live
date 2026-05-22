# Handoff Report — AI Auto-Discovery Keywords

## 1. Observation
I directly observed the following components and code snippets in the livestream workspace:
- **Manual setup on frontend:**
  - `backend/resources/js/Pages/Lives/Setup.tsx` line 69 initializes:
    ```typescript
    keywords: ['mua', 'chốt', 'ship', 'giá', 'size'] as string[],
    ```
  - `backend/resources/js/Pages/Lives/Setup.tsx` lines 313-363 renders:
    ```typescript
    {/* Từ khóa theo dõi */}
    <Card>
        <CardHeader>
            <CardTitle>Từ khóa theo dõi</CardTitle>
            ...
        </CardHeader>
        ...
    </Card>
    ```
- **Manual setup validation & storage on backend:**
  - `backend/app/Http/Controllers/LiveSessionController.php` lines 125-126 validates:
    ```php
    'keywords' => ['nullable', 'array'],
    'keywords.*' => ['string', 'max:100'],
    ```
  - `backend/app/Http/Controllers/LiveSessionController.php` lines 163-168 stores:
    ```php
    // Save keywords
    if (! empty($validated['keywords'])) {
        foreach ($validated['keywords'] as $keyword) {
            $session->keywords()->create(['keyword' => $keyword]);
        }
    }
    ```
- **AI prompting & job execution:**
  - `backend/app/Jobs/AnalyzeCommentsJob.php` lines 490-524 specifies LLM expected output structure as:
    ```php
    Trả về JSON duy nhất: {"results": [...], "session_note": "string (max 300 ký tự)"}
    ```
- **Keyword database schema & model:**
  - `backend/database/migrations/2026_05_21_000005_create_live_session_keywords_table.php` defines the table structure for `live_session_keywords` with fields `id`, `live_session_id`, `keyword`.
  - `backend/app/Models/LiveSessionKeyword.php` maps model `LiveSessionKeyword` to `live_session_keywords` table.
- **Top keyword counting & aggregation:**
  - `backend/app/Http/Controllers/LiveSessionController.php` lines 1157-1185 counts keywords:
    ```php
    $count = $session->events()
        ->where('event_type', 'comment')
        ->where('data->comment', 'like', "%{$kw}%")
        ->count();
    ```
- **UI Dashboard display:**
  - `backend/resources/js/Pages/Lives/Show.tsx` lines 3206-3238 renders keywords from `topKeywords` prop as badge list inside the Top Keywords Card.
- **Test execution:**
  - Ran `php artisan test` inside directory `d:\Workspace\livestream\backend` and verified all 94 feature and unit tests passed successfully.

---

## 2. Logic Chain
- **Step 1:** To remove the manual keyword configurations (R1), we must delete the input interface (Card) and form state variables in `Setup.tsx` and stop validating or inserting keywords inside `LiveSessionController::store()`. Since these deletions completely sever the manual data flow from client to server, the `live_session_keywords` table will start empty.
- **Step 2:** To support AI Auto-Discovery Keywords (R2), we must modify `AnalyzeCommentsJob`'s system prompt instructions to include an `extracted_keywords` array in its returned JSON schema.
- **Step 3:** The parsed keywords list from the LLM must be processed in the backend `handle()` method to apply normalization (`mb_strtolower`), filter by length (1 to 3 words), and prevent duplication.
- **Step 4:** To enforce the per-session constraint of ~30 keywords, the code must count how many keywords already exist in the database for the session, deduct this from 30, and slice the new unique keywords array down to the remaining capacity before inserting.
- **Step 5:** Since `LiveSessionController::getTopKeywords` queries `live_session_keywords` and counts occurrences using a series of SQL LIKE counts, it will naturally transition to aggregating these AI-discovered keywords. The dashboard in `Show.tsx` will display them dynamically since it receives and renders `topKeywords` in real-time.

---

## 3. Caveats
- Since this is a read-only investigation, the source code files remain unmodified in the workspace.
- The prompt adjustment will require updating mock assertions inside `AnalyzeCommentsJobTest.php` because LLM mock responses in tests currently return only `results` and `session_note` without the `extracted_keywords` key.

---

## 4. Conclusion
The current workspace setup is fully prepared to remove manual keyword entries and implement AI Auto-Discovery Keywords. Deleting the specified form components, updating the system prompt with the `extracted_keywords` payload, standardizing and capping the database inputs to 30 elements, and utilizing the existing SQL LIKE counter will fulfill the task objectives.

---

## 5. Verification Method
1. **Verification of current state:** Run the PHPUnit tests using the command `php artisan test` in `d:\Workspace\livestream\backend` to ensure the test suite is green before changes are applied.
2. **Reviewing files:** Inspect `d:\Workspace\livestream\.agents\explorer_keywords_3\analysis.md` for a comprehensive breakdown of the code paths, matrices, and suggested fix details.
