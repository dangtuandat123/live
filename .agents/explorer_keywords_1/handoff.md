# Handoff Report

## 1. Observation
We observed the following exact patterns in the codebase to answer R1, R2, and R3:

### R1: Manual Keyword Configuration
*   **File Path**: `backend/resources/js/Pages/Lives/Setup.tsx`
    *   Line 69: Initial form state:
        ```typescript
        keywords: ['mua', 'chốt', 'ship', 'giá', 'size'] as string[],
        ```
    *   Line 72: Input input text state:
        ```typescript
        const [keywordInput, setKeywordInput] = React.useState('');
        ```
    *   Lines 84-97: Helper functions for mutating local state array:
        ```typescript
        function addKeyword() { ... }
        function removeKeyword(kw: string) { ... }
        ```
    *   Lines 312-363: Form UI Card mapping standard inputs to `keywords`:
        ```tsx
        {/* Keywords */}
        <Card>
            <CardHeader>
                <CardTitle>Từ khóa theo dõi</CardTitle>
                ...
        </Card>
        ```
*   **File Path**: `backend/app/Http/Controllers/LiveSessionController.php`
    *   Lines 125-126: Validation rules for keywords payload:
        ```php
        'keywords' => ['nullable', 'array'],
        'keywords.*' => ['string', 'max:100'],
        ```
    *   Lines 163-168: Loop creating keywords relations:
        ```php
        if (! empty($validated['keywords'])) {
            foreach ($validated['keywords'] as $keyword) {
                $session->keywords()->create(['keyword' => $keyword]);
            }
        }
        ```

### R2: AnalyzeCommentsJob prompt & keyword storage
*   **File Path**: `backend/app/Jobs/AnalyzeCommentsJob.php`
    *   Lines 441-524: The prompt building logic (`buildSystemPrompt`) and instructions to return intent/question/product tags and a `session_note`:
        ```php
        return <<<PROMPT
        ...
        Trả về JSON duy nhất: {"results": [{"id": int, "sentiment": "positive"|"neutral"|"negative", "intent_tag": "Chốt đơn"|"Hỏi thông tin"|"Phản hồi SP"|"Yêu cầu hỗ trợ"|null, "question_tag": string|null, "product_tag": string|null, "has_phone": bool}], "session_note": "string (max 300 ký tự)"}
        ...
        PROMPT;
        ```
    *   Lines 316-324: Currently fetches manual session keywords to insert in the system prompt:
        ```php
        $keywords = $session->keywords->pluck('keyword')->toArray();
        ```
    *   Line 441: Current system prompt context binding:
        ```php
        - Từ khóa theo dõi: {$keywordList}
        ```
    *   No keyword extraction or persisting mechanism currently exists in the `handle` method.

### R3: Top Keywords Query & Dashboard Rendering
*   **File Path**: `backend/app/Http/Controllers/LiveSessionController.php`
    *   Lines 1157-1185: SQL `LIKE` queries execution inside `getTopKeywords($session)`:
        ```php
        $count = $session->events()
            ->where('event_type', 'comment')
            ->where('data->comment', 'like', "%{$kw}%")
            ->count();
        ```
*   **File Path**: `backend/resources/js/Pages/Lives/Show.tsx`
    *   Lines 2696-2697: State hook for `topKeywords`:
        ```typescript
        const [topKeywords, setTopKeywords] = React.useState<TopKeyword[]>(
            initialTopKeywords ?? [],
        );
        ```
    *   Line 2739: Event fetching callback:
        ```typescript
        if (data.topKeywords) setTopKeywords(data.topKeywords);
        ```
    *   Lines 3207-3237: Card rendering:
        ```tsx
        {/* Top Keywords Card */}
        <Card className="flex-1 overflow-hidden">
            ...
            {topKeywords && topKeywords.length > 0 ? (
                topKeywords.map((item) => (
                    <div key={`k-${item.keyword}`}>
                        <span>{item.keyword}</span>
                        <span>{item.count}</span>
                    </div>
                ))
            ) : ( ... )}
        </Card>
        ```

---

## 2. Logic Chain
1. **R1 (Removal of Manual Keyword Setup)**: Removing manual setup requires removing the input form card from the setup UI and removing the manual keywords parameter from both frontend form state (in `Setup.tsx`) and backend validation/saving logic (in `LiveSessionController::store`). Since the migration path moves towards auto-discovery, manual keywords are no longer needed on new session creations.
2. **R2 (AI Prompt Modification & Persistence)**: Since the AI analysis runs periodically inside `AnalyzeCommentsJob`, we can modify the system prompt to return a top-level `extracted_keywords` array (max 5 items, lowercase, short 1-3 words, targeting products/price/quality/queries). We can then parse `extracted_keywords` from the LLM JSON response and persist them into the `live_session_keywords` table using a robust helper method. This helper method filters, downcases, removes duplicates, and ensures that the total number of keywords in the session table never exceeds 30.
3. **R3 (SQL Query Count and Dashboard Rendering)**: In the live dashboard (`Show.tsx`), `topKeywords` is received from the backend, polled every 5 seconds, and rendered inside a flex-wrap container card. The backend currently computes this counts dynamically in `getTopKeywords` by running a `LIKE` SQL query in a loop for each keyword against the JSON field `data->comment` in the `live_events` table.

---

## 3. Caveats
*   We did not run the PHP test suite or rebuild the frontend because this is a read-only investigation.
*   LLM behavior may vary; if the LLM fails to output valid JSON arrays for `extracted_keywords`, the fallback "poison pill" recovery in `AnalyzeCommentsJob` marks the comments as processed, but transient keywords for that batch might be lost.

---

## 4. Conclusion
We have identified all the target lines, structures, and risks. The manual configuration can be cleanly removed by modifying `Setup.tsx` and `LiveSessionController::store`. AI auto-discovery can be supported by updating the LLM system prompt in `AnalyzeCommentsJob` and implementing the proposed `persistExtractedKeywords` method to enforce the 30-item limit. The dashboard is fully ready to display the discovered top keywords counts, though the loop of `LIKE` database queries poses a performance risk that could be addressed by scaling cache TTL or incremental counts.

---

## 5. Verification Method
1.  **Frontend Inspection**: Inspect `Setup.tsx` using `view_file` to confirm that all JSX and typescript state referring to `keywords` are removed.
2.  **Controller Inspection**: Inspect `LiveSessionController::store` using `view_file` to confirm that the `keywords` validation rules and the loop saving keywords are removed.
3.  **Job Execution and Prompt Verification**: Check the logs or run `php artisan test` (specifically `LiveSessionUIIntegrationTest` and `AnalyzeCommentsJobTest`) to confirm they continue to run successfully. Write a new unit test matching `AnalyzeCommentsJobTest` to assert that:
    *   The LLM output returns `extracted_keywords`.
    *   Extracted keywords are normalized to lowercase.
    *   Duplicate keywords are ignored.
    *   The keyword limit of 30 per session is strictly enforced.
