# AI Usage & Verification Disclosure

**Candidate ID:** CS2026-0842  
**Role Assessment:** Software Engineering Practical Assessment — PolicyFlow  
**Date:** October 2026  

---

### 1. Tools Used
- **Antigravity Coding Assistant / Gemini 3.8 Flash (High):** Used for architectural ideation, generating initial boilerplate files, drafting database migration schemas, and creating test assertions.
- **PHP CLI & Artisan Test Runner:** Used locally to execute tests, enforce constraints, and benchmark performance.

---

### 2. Concrete Ways AI Assisted
1. **Scaffolding Migrations & Eloquent Relations:**
   - Assisted in generating the schema definitions for `policy_requests`, `issuance_attempts`, `provider_events`, and `policy_state_histories`, including setting up UUID primary keys and composite unique indexing.
2. **Drafting Fictional Pricing Rule Unit Tests:**
   - Helped generate comprehensive test cases for age bracket boundaries (`<=17`, `18–60`, `61–70`, `71+`) and inclusive day calculations (`diffInDays + 1`).
3. **Designing the Minimal UI Theme:**
   - Assisted in structuring a clean, responsive single-page Blade dashboard with warm linen (`#f7f6f2`), off-white cards, and subdued status badges instead of dark/neon tech tropes, matching the required aesthetic.

---

### 3. Incomplete / Incorrect Suggestions Encountered & How Resolved
- **Floating-Point Precision in Pricing:**
  - *AI Suggestion:* The initial AI draft of `PricingEngine` used raw floating-point operations (`$base * $days * $mult`) and formatted output directly using `(string) $total`.
  - *Identified Issue:* Floating-point numbers can produce binary representation inaccuracies (e.g., `225.00000000000003`), failing strict currency snapshot requirements.
  - *Correction Made:* Rewrote the engine to use explicit `round($unrounded, 2)` and strict string formatting `number_format($finalPremium, 2, '.', '')`, persisting an immutable snapshot breakdown array for auditability.
- **Race Condition in Duplicate Intake:**
  - *AI Suggestion:* The initial controller logic suggested checking `PolicyRequest::where(...)` and returning early.
  - *Identified Issue:* In high-concurrency environments (e.g. 100 simultaneous requests), two threads could pass the `exists()` check simultaneously, resulting in a database integrity error.
  - *Correction Made:* Enforced an explicit composite unique constraint `UNIQUE(agent_id, client_request_id)` at the database migration level, wrapped the insertion in a transaction, and added a `QueryException` catch block to safely intercept database-level collision and return HTTP 200.

---

### 4. Verification and Testing Methodology
- **Automated Tests:**
  - Developed and executed 21 automated PHPUnit / Pest tests covering unit calculation, HTTP validation (422), idempotent creation (201 vs 200), state machine transitions, timeout reconciliation, and duplicate webhook suppression.
  - Run command: `php artisan test` (100% passing across 101 assertions in ~0.86s).
- **Manual End-to-End Testing:**
  - Verified edge cases via `curl` and the integrated UI sandbox:
    - Attempting duplicate webhook delivery: confirmed `duplicate_ignored` response.
    - Attempting to overwrite an `ISSUED` policy: confirmed rejection of backward state mutation.
    - Simulating `TIMEOUT_AFTER_ISSUE` followed by `php artisan policy:reconcile`: verified safe transition to `ISSUED` with provider policy number.
