# Laravel Best Practices Skill

Loaded from: d:\Workspace\livestream\.agents\skills\laravel-best-practices\SKILL.md

Consistency First: Check siblings and existing patterns first.
Database Performance: Eager load relations, cursor for iteration, index queries.
Security: Fillable/guarded, no raw SQL with input, CSRF, throttles.
Eloquent Patterns: Local scopes, type hints, casts method, whereBelongsTo.
Validation: Form requests, validated() only.
Testing: LazilyRefreshDatabase, assertModelExists, recycle.
Queue & Jobs: ShouldBeUnique, retry_after.
Routing & Controllers: Scoped bindings, apiResource, under 10 lines.
HTTP Client: timeout/connectTimeout, retry with backoff.
Events & Scheduling: withoutOverlapping, onOneServer.
Architecture: Single-purpose Action classes, mb_*.
Migrations: constrained(), down() reversible.
Collections & Views: higher-order messages, Blade components.
Conventions: Laravel naming conventions, Str/Arr helpers.
