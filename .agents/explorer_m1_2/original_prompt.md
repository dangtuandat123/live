## 2026-05-21T14:52:54Z
**Context**: We are at Milestone 1: DB Schema & Models.
**Task**: Explore the model and model relations design.
Specifically:
1. Locate and inspect the `User` model (`backend/app/Models/User.php`).
2. Formulate the exact Eloquent relationships that need to be added to the `User` model for `subscriptions` and `transactions`.
3. Design the Eloquent models `SubscriptionPackage`, `UserSubscription`, `PaymentConfig`, and `Transaction` with their `$fillable` attributes, casts (e.g., features JSON cast, starts_at/expires_at datetime casts), and relationship methods.
4. Check if there are any base models, traits, or global scopes used in existing models.
5. Write your findings to d:\Workspace\livestream\.agents\explorer_m1_2\handoff.md.
**Completion criteria**: A detailed handoff.md with proposed model definitions and relationships.
