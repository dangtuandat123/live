# Progress tracker

Last visited: 2026-05-21T15:01:40Z

- [x] Step 1: Check and modify migration files (update foreign key constraints to restrictOnDelete, remove redundant index on transaction_id)
- [x] Step 2: Update Eloquent models with `$attributes` properties mirroring DB schema
- [x] Step 3: Fix future subscription start check bypass in `UserSubscription::isActive()` and `User::activeSubscription()`
- [x] Step 4: Add new test cases to `SubscriptionDatabaseTest.php` for future starts_at check and cascade delete restrictions
- [x] Step 5: Run `php artisan migrate:fresh --seed` in `backend` to ensure clean execution
- [x] Step 6: Run `php artisan test` in `backend` to verify all tests pass cleanly
- [ ] Step 7: Write a detailed handoff report and finalize task
