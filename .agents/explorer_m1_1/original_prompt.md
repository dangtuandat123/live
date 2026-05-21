## 2026-05-21T14:52:54Z
**Context**: We are at Milestone 1: DB Schema & Models.
**Task**: Explore the existing Laravel database schema and migrations.
Specifically:
1. Locate existing migrations and see how they are structured.
2. Locate `User` model (`backend/app/Models/User.php`) and inspect its structure, columns, and traits.
3. Plan the migration fields and schema design for the new tables:
   - `subscription_packages` (fields: `id`, `name`, `price`, `duration_days`, `features` JSON)
   - `user_subscriptions` (fields: `id`, `user_id`, `subscription_package_id`, `starts_at`, `expires_at`, `status`)
   - `payment_configs` (fields: `id`, `name`, `prefix`, `suffix`, `webhook_url`, `method`, `params_template` JSON/TEXT, `headers_template` JSON/TEXT)
   - `transactions` (fields: `id`, `user_id`, `amount`, `payment_config_id`, `status` [pending, success, failed], `transaction_id`)
   Ensure proper database types, nullability, foreign key constraints (with cascade on delete or appropriate actions), and database indexes (e.g., on foreign keys, transaction_id, status).
4. Write your findings to d:\Workspace\livestream\.agents\explorer_m1_1\handoff.md.
**Completion criteria**: A detailed handoff.md with proposed schema and migrations design.
