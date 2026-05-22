# Laravel Best Practices Skill Reference

## Core Guidelines
- Consistent with existing codebase design.
- Database Performance: Eager load with `with()`, select only needed columns, avoid N+1 queries.
- Security: Define `$fillable` or `$guarded`, authorize, sanitize user inputs.
- Caching: Use `Cache::remember()`, lock for race conditions.
- Eloquent Patterns: Proper relationship types, local scopes, casts.
- Validation: Use Form Request classes where applicable; array notation for validation rules.
- Queue & Jobs: Job timeout & retry configuration, unique jobs.
- Testing: `LazilyRefreshDatabase`, `assertModelExists()`, fakes.
- Migrations: Mirrored column defaults.
