# Laravel Best Practices (Copied Skill)

This is a copy of the laravel-best-practices skill instructions.

Consistency first:
- Check sibling files, related controllers, models, or tests for established patterns.
- Follow them first.

Database:
- Eager load with `with()` to prevent N+1 queries.
- Select only needed columns.
- Index columns used in WHERE, ORDER BY, JOIN.

Security:
- Define `$fillable` or `$guarded` on every model.
- No raw SQL with user input.
- Validate input.

Eloquent:
- Correct relationship types with return type hints.
- Attribute casts in `casts()` method.
