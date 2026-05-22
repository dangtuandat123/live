# Handoff Report — AI Auto-Discovery Keywords

This report supports the transition to automated keyword discovery by outlining observed code paths, reasoning, conclusions, and specific next steps for the implementation agent.

## 1. Observation
Below are the exact code snippets and locations observed during the investigation:

### Setup.tsx (`backend/resources/js/Pages/Lives/Setup.tsx`)
- **Initial form state (Line 69)**:
  ```typescript
  keywords: ['mua', 'chốt', 'ship', 'giá', 'size'] as string[],
  ```
- **Temporary State (Line 72)**:
  ```typescript
  const [keywordInput, setKeywordInput] = React.useState('');
  ```
- **Helpers (Lines 84–97)**:
  ```typescript
  function addKeyword() {
      const trimmed = keywordInput.trim();
      if (trimmed && !form.data.keywords.includes(trimmed)) {
          form.setData('keywords', [...form.data.keywords, trimmed]);
          setKeywordInput('');
      }
  }

  function removeKeyword(kw: string) {
      form.setData(
          'keywords',
          form.data.keywords.filter((k) => k !== kw),
      );
  }
  ```
- **Keywords Form Card UI (Lines 312–363)**:
  ```typescript
  {/* Keywords */}
  <Card>
      <CardHeader>
          <CardTitle>Từ khóa theo dõi</CardTitle>
          <CardDescription>
              Các từ khóa bán hàng mà AI sẽ ưu tiên phát hiện trong bình luận
          </CardDescription>
      </CardHeader>
      <CardContent className="grid gap-3">
          ...
      </CardContent>
  </Card>
  ```

### LiveSessionController.php (`backend/app/Http/Controllers/LiveSessionController.php`)
- **Validation in `store()` (Lines 125–126)**:
  ```php
  'keywords' => ['nullable', 'array'],
  'keywords.*' => ['string', 'max:100'],
  ```
- **Storing manually entered keywords in `store()` (Lines 163–168)**:
  ```php
  // Save keywords
  if (! empty($validated['keywords'])) {
      foreach ($validated['keywords'] as $keyword) {
          $session->keywords()->create(['keyword' => $keyword]);
      }
  }
  ```
- **Keywords counting in `getTopKeywords()` (Lines 1157–1185)**:
  ```php
  private function getTopKeywords(LiveSession $session): array
  {
      $setupKeywords = $session->keywords()->pluck('keyword')->toArray();
      $keywordCounts = [];
      
      foreach ($setupKeywords as $kw) {
          $kw = trim($kw);
          if ($kw === '') {
              continue;
          }
          $count = $session->events()
              ->where('event_type', 'comment')
              ->where('data->comment', 'like', "%{$kw}%")
              ->count();
          if ($count > 0) {
              $keywordCounts[] = [
                  'keyword' => $kw,
                  'count' => $count,
              ];
          }
      }

      // Sort descending by count
      usort($keywordCounts, function ($a, $b) {
          return $b['count'] <=> $a['count'];
      });

      return $keywordCounts;
  }
  ```

### AnalyzeCommentsJob.php (`backend/app/Jobs/AnalyzeCommentsJob.php`)
- **System Prompt JSON format instruction (Line 493)**:
  ```php
  Trả về JSON duy nhất: {"results": [{"id": int, "sentiment": "positive"|"neutral"|"negative", "intent_tag": "Chốt đơn"|"Hỏi thông tin"|"Phản hồi SP"|"Yêu cầu hỗ trợ"|null, "question_tag": string|null, "product_tag": string|null, "has_phone": bool}], "session_note": "string (max 300 ký tự)"}
  ```
- **Job executes AI call (Lines 193–198)**:
  ```php
  $response = $runware->chatMultimodal(
      systemPrompt: $systemPrompt,
      parts: $parts,
      temperature: 0,
      maxTokens: 4096,
  );
  ```
- **Job does not read or save keywords**: No references exist to handle keyword extraction or database operations on `live_session_keywords` during comment processing.

### Live Session Keywords Migration (`backend/database/migrations/2026_05_21_000005_create_live_session_keywords_table.php`)
- **Table Structure (Lines 11–15)**:
  ```php
  Schema::create('live_session_keywords', function (Blueprint $table) {
      $table->id();
      $table->foreignId('live_session_id')->constrained()->cascadeOnDelete();
      $table->string('keyword');
  });
  ```

### Show.tsx (`backend/resources/js/Pages/Lives/Show.tsx`)
- **State Initialization (Line 2696)**:
  ```typescript
  const [topKeywords, setTopKeywords] = React.useState<TopKeyword[]>(
      initialTopKeywords ?? [],
  );
  ```
- **Polling Event handler (Line 2739)**:
  ```typescript
  if (data.topKeywords) setTopKeywords(data.topKeywords);
  ```
- **Render UI (Lines 3215–3230)**:
  ```typescript
  {topKeywords &&
  topKeywords.length > 0 ? (
      topKeywords.map((item) => (
          <div
              key={`k-${item.keyword}`}
              className="bg-primary/10 text-primary flex items-center gap-1 rounded-md px-2 py-0.5 text-xs"
          >
              <span>
                  {item.keyword}
              </span>
              <span className="font-bold tabular-nums">
                  {item.count}
              </span>
          </div>
      ))
  ) : (
      <div className="text-muted-foreground text-xs">
          Chưa có dữ liệu
      </div>
  )}
  ```

---

## 2. Logic Chain
1. **Manual Configuration Removal (R1)**: Since setup has hardcoded default keywords (`Setup.tsx`, line 69) and displays them via a custom input Card component (`Setup.tsx`, lines 312-363) which submits to `lives.store` route, these parts must be pruned. On the backend, `LiveSessionController::store` validates (`keywords` validation array) and saves them, which will now become dead/unused code.
2. **AI Discovery Prompt Addition (R2)**: The AI engine parses comments in batches of 50 inside `AnalyzeCommentsJob.php`. The system prompt specifies a fixed JSON schema shape (line 493) which currently omits keywords. Modifying the system prompt schema to request `"extracted_keywords": string[]` forces Gemini/Runware AI to yield discovered keywords.
3. **Database Standardization & Persistence (R2)**: To ensure data integrity, the parsed keywords from the AI must be converted to lowercase, trimmed, and filtered against empty or excessively long inputs. Since the table has no database-level unique constraint on `(live_session_id, keyword)`, checking existence via `$session->keywords()->where('keyword', $kw)->exists()` protects against duplicate records. Placing a cap (break loop when `keywords()->count() >= 30`) restricts table growth.
4. **Keyword Dashboard Display (R3)**: `LiveSessionController::getTopKeywords` queries the session's keywords from the `live_session_keywords` table, executes LIKE queries on comments for occurrences count, caches it, and serves it to Inertia view rendering and AJAX updates. This means that if we populate `live_session_keywords` dynamically with AI discovered keywords instead of manual ones, `getTopKeywords` and `Show.tsx` dashboard cards will dynamically work out-of-the-box without requiring rendering architecture changes.

---

## 3. Caveats
- **Runware AI performance/limits**: Auto-discovery relies completely on the Runware AI service. If the AI returns malformed JSON or times out, the keywords for that batch will not be discovered.
- **SQL LIKE performance**: Using `LIKE "%{$kw}%"` queries on the JSON comments column runs sequentially on every poll request (if not cached). While cache is implemented, a large volume of comments could bottleneck databases with poor indexing.

---

## 4. Conclusion
We have mapped all components and established a clear path to replace the manual configuration with AI auto-discovery. The transition will require clean, localized modifications to Setup UI, endpoint validations, and the comment processing job.

---

## 5. Verification Method
After implementation, verify using:
1. **Feature Tests**:
   Execute the integration suite:
   ```bash
   php artisan test --filter=LiveSessionUIIntegrationTest
   ```
2. **Database Invariant Check**:
   Insert mock entries to `live_session_keywords` via Tinker and inspect the SQL output of:
   ```php
   $session = App\Models\LiveSession::first();
   $controller = app(App\Http\Controllers\LiveSessionController::class);
   // Call the private method via reflection or inspect output:
   dd($session->keywords()->pluck('keyword'));
   ```
3. **AI Job Mock Test**:
   Write a regression test to mock the Runware AI response with `extracted_keywords` and assert:
   - Only unique lowercase, trimmed keywords are saved.
   - The total keywords for the session never exceed 30.

---

## 6. Remaining Work (Implementation Steps)
1. **Update `Setup.tsx`**:
   - Prune the keywords card (lines 312–363).
   - Delete `keywordInput` state, `addKeyword`, and `removeKeyword` helpers.
2. **Update `LiveSessionController::store`**:
   - Delete lines 125–126 and lines 163–168.
3. **Update `AnalyzeCommentsJob.php`**:
   - Add `extracted_keywords` to JSON block in prompt instructions (lines 493 and guidelines).
   - Add keyword processing code to handle standardizing, checking 30-limit, and inserting to DB.
4. **Update `LiveSessionUIIntegrationTest.php`**:
   - Refactor tests to simulate auto-discovered keywords or inject mock data into the database to assert dashboard counts behavior.
