## 2026-05-21T21:48:42+07:00

You are Explorer 3, working in directory d:\Workspace\livestream\.agents\explorer_e2e_3_retry_2.
Your task is to explore the codebase at d:\Workspace\livestream and design the HTTP mocking and outbound webhook verification strategy for tests/Feature/SubscriptionPaymentTest.php.
Specifically:
1. Look at the Outbound Webhook Contract in PROJECT.md:
   - Method: GET/POST/PUT
   - Headers: Key-Value pairs resolved from headers_template
   - Payload/Params: Key-Value pairs resolved from params_template replacing {user_id}, {amount}, {transaction_id}.
2. Plan how to mock external HTTP endpoints using Laravel's Http::fake() and verify that the outgoing requests exactly match the configured templates and method.
3. Design mock callbacks for the public POST /api/payments/callback and checkout generation.
4. Output your design report to d:\Workspace\livestream\.agents\explorer_e2e_3_retry_2\webhook_mock_design.md.
5. Provide a handoff report at d:\Workspace\livestream\.agents\explorer_e2e_3_retry_2\handoff.md when done.
