# Review Report — AI Auto-Discovery Keywords

This report presents an independent review of the implementation of the AI Auto-Discovery Keywords feature.

---

## 1. Observation

Direct observations made in the workspace:

### Inspected Scope
1. **Frontend Setup Component**: `backend/resources/js/Pages/Lives/Setup.tsx`
2. **Session Controller**: `backend/app/Http/Controllers/LiveSessionController.php`
3. **AI Comments Analysis Job**: `backend/app/Jobs/AnalyzeCommentsJob.php`
4. **Integration & Feature Tests**: `backend/tests/Feature/AnalyzeCommentsJobTest.php`

### Code Observations

#### 1. Removal of Manual Keyword Configuration (R1)
- **Frontend**: Checked `backend/resources/js/Pages/Lives/Setup.tsx`. There is no input field, tag list, or state variable related to user keywords. The submission payload is:
  ```typescript
  const form = useForm({
      name: '',
      tiktok_username: '',
      product_ids: [] as number[],
  });
  ```
- **Backend Controller**: Checked `LiveSessionController::store`. The validation rule does not include keywords validation:
  ```php
  $validated = $request->validate([
      'name' => ['required', 'string', 'max:255'],
      'tiktok_username' => ['required', 'string', 'max:100'],
      'product_ids' => ['nullable', 'array'],
      'product_ids.*' => ['integer', 'exists:products,id'],
  ]);
  ```

#### 2. AI Auto-Discovery Integration in Job Prompt and Handle Function (R2)
- **System Prompt**: Checked `AnalyzeCommentsJob::buildSystemPrompt`. The prompt includes clear instructions for returning extracted keywords:
  - Instructions on format:
    ```
    Trả về JSON duy nhất: {"results": [...], "session_note": "string (max 300 ký tự)", "extracted_keywords": ["keyword1", "keyword2"]}
    ```
  - Instruction on extracted keywords criteria:
    ```
    **extracted_keywords** — Thêm trường "extracted_keywords" chứa danh sách tối đa 5 từ khóa được trích xuất từ batch bình luận này. Các từ khóa phải viết thường (lowercase), ngắn từ 1-3 từ, liên quan đến sản phẩm, giá cả, chất lượng hoặc các câu hỏi chung của người xem.
    ```
- **Deduplication, Normalization, Trimming & Limit Logic**: Checked `AnalyzeCommentsJob::handle`. The extracted keywords are handled in lines 345-382:
  ```php
  $extractedKeywords = $response['extracted_keywords'] ?? [];
  if (is_array($extractedKeywords) && !empty($extractedKeywords)) {
      $currentCount = $session->keywords()->count();
      if ($currentCount < 30) {
          $normalizedKeywords = [];
          foreach ($extractedKeywords as $kw) {
              if (!is_string($kw)) {
                  continue;
              }
              $normalized = mb_strtolower(trim($kw));
              if ($normalized !== '' && !in_array($normalized, $normalizedKeywords)) {
                  $normalizedKeywords[] = $normalized;
              }
          }

          if (!empty($normalizedKeywords)) {
              $existingKeywords = $session->keywords()
                  ->pluck('keyword')
                  ->map(fn($k) => mb_strtolower(trim($k)))
                  ->toArray();

              $newKeywords = [];
              foreach ($normalizedKeywords as $kw) {
                  if (!in_array($kw, $existingKeywords)) {
                      $newKeywords[] = $kw;
                  }
              }

              if (!empty($newKeywords)) {
                  $availableSlots = 30 - $currentCount;
                  $toAdd = array_slice($newKeywords, 0, $availableSlots);
                  foreach ($toAdd as $kw) {
                      $session->keywords()->create(['keyword' => $kw]);
                  }
              }
          }
      }
  }
  ```

#### 3. Counting Dynamic Keywords (R3)
- Checked `LiveSessionController::getTopKeywords`. It counts events using `where('data->comment', 'like', "%{$kw}%")` dynamically:
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
      // ... sort and return ...
  }
  ```

#### 4. Verification Commands Run
- **Artisan Tests Command**: `php artisan test`
  - Output: `Tests: 96 passed (666 assertions)`
  - Command completed successfully in 5.20s.
- **Frontend Build Command**: `npm run build`
  - Output: Compiled successfully. Built asset size and chunks output. Built in 8.36s.

---

## 2. Logic Chain

1. **R1 Verification**: Because `Setup.tsx` has no input UI for keywords, and the payload does not contain keywords, manual configuration is confirmed to be completely removed from the frontend. Because `LiveSessionController::store` does not validate or process `keywords`, backend manual keyword configuration is also confirmed to be completely removed.
2. **R2 Verification**: Because `AnalyzeCommentsJob::buildSystemPrompt` requests `extracted_keywords` and validates/normalizes them to lowercase/trimmed text inside the transaction block, while checking the database state size up to exactly 30 (`30 - $currentCount`), the requirements for normalization, trimming, deduplication, and a limit of 30 keywords per session are fully met.
3. **R3 Verification**: Because `getTopKeywords` dynamically loops over `$session->keywords()` (populated by the AI auto-discovery job) and runs an SQL `LIKE` query (`where('data->comment', 'like', "%{$kw}%")`) against comments in the `live_events` table, keyword count is dynamic and has no static/hardcoded values.
4. **Testing Verification**: Because the test suite `AnalyzeCommentsJobTest` includes tests like `test_it_extracts_and_persists_keywords_from_scratch` and `test_it_extracts_and_persists_keywords_with_30_limit` which pass successfully, and running `npm run build` succeeds, testing and frontend compilation constraints are met.

---

## 3. Caveats

- **No runtime audio connection**: Audio analysis was mocked in tests. Live audio collection via FFmpeg from TikTok was not verified in a real live setting due to lack of a real livestream, but the fallback mechanism is covered by unit tests.
- **AI responses mapping**: AI output accuracy is dependent on the LLM's adherence to the system prompt's format constraint (returning `extracted_keywords`). If the model doesn't return that attribute, the job falls back gracefully without breaking.

---

## 4. Conclusion

### Verdict: APPROVE (Safe within audited scope)

### Quality Review Summary
- **Correctness**: The implementation correctly fulfills all specified requirements (R1, R2, R3).
- **Logical Completeness**: Complete coverage of key files. The flow starts from Setup (input removed) -> Controller -> AI Job Prompt/Extraction -> DB Persistence -> Controller top keywords computation via SQL query.
- **Quality**: Conforms to Laravel and React/Typescript best practices.

### Challenge / Adversarial Review Summary
- **Overall risk assessment**: **LOW**
- **Stress test of the 30-limit logic**:
  - The job counts keywords already present. If the limit is already reached ($currentCount >= 30), it skips processing.
  - If a batch returns 5 new keywords and there are 28 existing, `availableSlots` is calculated as `30 - 28 = 2`.
  - `array_slice($newKeywords, 0, 2)` selects the first 2 new keywords, bringing the total count to exactly 30.
  - Case insensitive comparison `mb_strtolower` ensures we do not add duplicates like "áo thun" and "Áo Thun".

---

## 5. Verification Method

To independently verify:
1. Run backend tests:
   ```bash
   php artisan test --filter=AnalyzeCommentsJobTest
   ```
2. Build frontend:
   ```bash
   npm run build
   ```
3. Inspect `backend/app/Jobs/AnalyzeCommentsJob.php` lines 345-382 to verify normalization and limiting.
4. Inspect `backend/app/Http/Controllers/LiveSessionController.php` line 1149 to verify dynamic SQL count.
