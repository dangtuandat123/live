# Scope: E2E Testing for Subscription, Payment, and Admin Config

## Architecture
- **Features Under Test**:
  1. Subscription Packages Listing (GET `/api/subscription/packages`)
  2. Subscription Status (GET `/api/subscription/status`)
  3. Subscription Checkout (POST `/api/subscription/checkout`)
  4. Payment Callback (POST `/api/payments/callback`)
  5. Outbound Webhook Triggering
  6. Admin Payment Config CRUD (GET/POST/PUT/DELETE `/api/admin/payments` or similar routes)
  7. Admin Subscription Package CRUD (GET/POST/PUT/DELETE `/api/admin/packages` or similar routes)

## Milestones
| # | Name | Scope | Dependencies | Status |
|---|------|-------|-------------|--------|
| 1 | Test Plan & Feature Inventory | Define features, design 4-tier test case matrix, create SCOPE.md and plan.md | none | IN_PROGRESS |
| 2 | Test Infrastructure Setup | Create test file structure, mock outbound webhook HTTP clients, configure database transactions | M1 | PLANNED |
| 3 | Tier 1-4 Test Implementation | Implement feature coverage (Tier 1), boundaries (Tier 2), cross-features (Tier 3), and workloads (Tier 4) | M2 | PLANNED |
| 4 | Final Verification & Publishing | Verify 100% test pass on mock implementation or coordinates, publish TEST_READY.md and TEST_INFRA.md | M3 | PLANNED |

## Interface Contracts
- See PROJECT.md for full public API contracts and outbound webhook placeholder resolution details.
