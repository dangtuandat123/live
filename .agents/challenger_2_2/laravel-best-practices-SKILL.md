# Laravel Best Practices (Local Copy)

Best practices for Laravel, prioritized by impact. Each rule teaches what to do and why.

## Consistency First
Before applying any rule, check what the application already does. Inconsistency is worse than a suboptimal pattern.

## Quick Reference
1. Database Performance (prevent N+1, chunk, index, cursor)
2. Advanced Query Patterns
3. Security (fillable, sanitize, csrf, throttle, no raw SQL with user input)
4. Caching (remember, flexible, memo, locks)
5. Eloquent Patterns
6. Validation & Forms (Form Requests)
7. Configuration (env() only in config files)
8. Testing Patterns (LazilyRefreshDatabase, assertModelExists, fakes after factory)
9. Queue & Job Patterns (retry_after > timeout, unique jobs, failed(), retryUntil)
10. Routing & Controllers
11. HTTP Client
12. Events, Notifications & Mail (afterCommit)
13. Error Handling
14. Task Scheduling (withoutOverlapping, onOneServer)
15. Architecture
16. Migrations
17. Collections
18. Blade & Views
19. Conventions & Style
