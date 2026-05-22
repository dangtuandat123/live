## 2026-05-22T07:10:53Z
We need to fix the integration route mismatch bug between the React frontend and Laravel backend.

### Task Requirements:
1. Modify `backend/routes/web.php` line 51 from:
   `Route::put('/live-events/{liveEvent}', [LiveSessionController::class, 'updateEvent'])->name('live-events.update');`
   to:
   `Route::put('/api/live-events/{liveEvent}', [LiveSessionController::class, 'updateEvent'])->name('live-events.update');`
   This prefixes the endpoint with `/api` to align with the frontend calls.

2. Modify `backend/tests/Feature/LiveEventUpdateTest.php` in the test `test_api_live_events_route_prefix_check` (lines 115-142).
   Change the assertion from:
   `$this->assertEquals(404, $response->getStatusCode(), ...)`
   to:
   `$this->assertEquals(200, $response->getStatusCode(), "The frontend fetches '/api/live-events/{id}' and it should resolve correctly!");`

3. Verify:
   - Run `npm run build` in the backend directory to ensure front-end builds successfully with zero errors.
   - Run `php artisan test` to ensure all tests pass (expecting 89 tests to pass).

Please write your progress and findings directly to your working directory: `d:\Workspace\livestream\.agents\worker_ui_sync_route_fix_final`.
