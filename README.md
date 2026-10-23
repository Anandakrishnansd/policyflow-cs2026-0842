# PolicyFlow — Fault-Tolerant Insurance Engine

**Campus Placement Practical Assessment | Software Engineering**  
**Candidate ID:** CS2026-0842  
**Tech Stack:** PHP 8.2+, Laravel 12, SQLite / MySQL, REST API, HTML5 / Blade  

---

## 1. Executive Summary

PolicyFlow is a fault-tolerant travel insurance intake, pricing, and policy issuance engine built to operate safely against unreliable third-party insurer APIs. 

The primary engineering objective is **absolute idempotency and zero duplicate issuance** under:
1. Near-simultaneous or repeated client intake requests.
2. Network timeouts occurring after the insurer has internally issued a policy.
3. Repeated or out-of-order webhook callbacks.
4. Transient provider failures requiring safe retries without mutating original quote terms.

---

## 2. Architecture & Design Principles

```
  +-------------------------------------------------------------+
  |                   Client / Agency Intake                   |
  +-------------------------------------------------------------+
                                 │
           POST /api/policy-requests (agent_id, client_request_id)
                                 │
                                 ▼
  +─────────────────────────────────────────────────────────────+
  |              Idempotency & Pricing Layer                    |
  |  • Compound Unique Key: (agent_id, client_request_id)       |
  |  • Deterministic PricingEngine with Decimal-Safe Math       |
  |  • Immutable Rate & Breakdown Snapshot Persisted            |
  +─────────────────────────────────────────────────────────────+
                                 │
                     Policy Status: VALIDATED
                                 │
                                 ▼
  +─────────────────────────────────────────────────────────────+
  |              Issuance Orchestrator & State Machine          |
  |  • Pessimistic Locking (lockForUpdate) during Transitions   |
  |  • Non-Regressive State Guards (ISSUED cannot be mutated)   |
  +──────────────────────────────┬──────────────────────────────+
                                 │
            Dispatches HTTP Call to External Insurer
                                 │
                                 ▼
  +─────────────────────────────────────────────────────────────+
  |                  Mock Insurer Gateway & Ledger              |
  |  Scenarios: SUCCESS, TIMEOUT, TEMP_FAILURE, ASYNC_CALLBACK  |
  +──────────────┬───────────────────────────────┬──────────────+
                 │                               │
        Success (200)                     Timeout (408/504)
                 │                               │
                 ▼                               ▼
         Status: ISSUED           Status: NEEDS_RECONCILIATION
                                                 │
                                                 ▼
                                  Reconciliation Engine / Worker
                                  (Queries provider ledger before
                                   reissuance; safe promotion)
```

### Core Entities:
- **`policy_requests`**: Stores policy intake, immutable rate snapshot (`premium_breakdown` JSON), current state, policy number, and provider reference.
- **`issuance_attempts`**: Audit trail of every external provider communication attempt with payloads, HTTP status, and error messages.
- **`provider_events`**: Stores inbound webhook callbacks with unique `event_id` enforcement for deduplication.
- **`policy_state_histories`**: Append-only log recording every state transition (`from_status`, `to_status`, timestamp, and reason).
- **`mock_insurer_policies`**: Simulates the external insurer's internal ledger for reliable status lookups.

---

## 3. Implemented Reliability Scenarios

We have implemented and verified all four failure modes specified in the brief:

| Reliability Scenario | Root Risk | Mitigation Strategy | Test Coverage |
|---|---|---|---|
| **1. Duplicate Intake** | Double policy creation on network retry | Database compound unique index `UNIQUE(agent_id, client_request_id)`. First submission returns HTTP 201; repeated submissions safely return the existing record with HTTP 200. | `PolicyIntakeTest::test_repeated_intake_returns_200_without_duplicate`, `ReliabilityTest::test_case_1_duplicate_intake_is_idempotent` |
| **2. Timeout After Success** | Client experiences timeout, but insurer already created policy; naive retry causes duplicate coverage | Insurer drops response packet. Policy transitions to `NEEDS_RECONCILIATION`. Re-submission is intercepted until reconciliation queries provider status (`GET /api/mock-insurer/policies/{ref}`) and promotes safely to `ISSUED`. | `ReliabilityTest::test_case_2_timeout_after_success_and_reconciliation` |
| **3. Duplicate Webhook Callback** | Webhook redelivery alters state or duplicates records | Inbound callbacks check `provider_events.event_id`. Duplicates return `duplicate_ignored` and leave policy state unchanged. | `ReliabilityTest::test_case_3_duplicate_callback_deduplication` |
| **4. Retry After Temporary Failure** | Insurer 503 outage loses quote terms or creates conflicting records | First attempt transitions policy to `FAILED`. Safe retry reuses the existing policy record and original accepted premium snapshot without re-pricing. | `ReliabilityTest::test_case_4_retry_after_temporary_failure` |

---

## 4. Local Setup & Execution Guide

### Prerequisites
- PHP 8.2 or newer
- Composer 2.x
- Git

### 1. Installation
```bash
# Clone the repository
git clone <repo-url> policyflow
cd policyflow

# Install PHP dependencies
composer install

# Environment configuration
cp .env.example .env
php artisan key:generate
```

### 2. Database Migrations & Seed Data
```bash
# Priming database and running migrations
php artisan migrate

# Seed database with realistic demonstration policies (including Spec Example 1)
php artisan db:seed
```

### 3. Automated Test Suite
To run all 21 unit and feature tests:
```bash
php artisan test
```

### 4. Running the Application Locally
```bash
php artisan serve
```
Visit **http://localhost:8000** in your browser to access the interactive PolicyFlow Dashboard.

---

## 5. Console Command: Stale Policy Reconciliation

To run the automated reconciliation worker from the command line:

```bash
# Reconcile all policies in NEEDS_RECONCILIATION or PENDING_CONFIRMATION status:
php artisan policy:reconcile

# Reconcile a specific policy by UUID:
php artisan policy:reconcile --id=01a169cb-d420-7272-8c37-742bf1c278d3
```

---

## 6. REST API Contract Summary

### 1. Create Policy Request (Intake)
- **Endpoint:** `POST /api/policy-requests`
- **Status Codes:**
  - `201 Created`: Newly created policy request.
  - `200 OK`: Duplicate submission safely returned.
  - `422 Unprocessable Content`: Validation failure.

**Request Payload:**
```json
{
  "client_request_id": "AGT-2026-0001",
  "agent_id": "AGENT-17",
  "customer_name": "Sample Customer",
  "date_of_birth": "1998-04-10",
  "destination": "INTERNATIONAL",
  "trip_start": "2026-11-10",
  "trip_end": "2026-11-14",
  "plan_code": "PLUS",
  "sum_insured": 300000
}
```

**Premium Calculation Formula:**
$$\text{Premium} = \text{base\_daily\_rate} \times \text{travel\_days} \times \text{age\_multiplier} \times \text{destination\_multiplier} \times \text{coverage\_multiplier}$$
- Travel days: Nov 10 to Nov 14 (inclusive) = 5 days
- Age on trip start: 28 years $\rightarrow 1.00$
- Plan PLUS: ₹20 / day
- International: $\times 1.80$
- ₹300,000 Sum Insured: $\times 1.25$
$$\text{Total} = 20 \times 5 \times 1.00 \times 1.80 \times 1.25 = ₹225.00$$

### 2. Submit Issuance
- **Endpoint:** `POST /api/policy-requests/{id}/submit`
- **Optional body:** `{"scenario": "SUCCESS" | "TIMEOUT_AFTER_ISSUE" | "TEMPORARY_FAILURE" | "DELAYED_CALLBACK"}`

### 3. Webhook Callback
- **Endpoint:** `POST /api/mock-insurer/callback`
- **Payload:**
```json
{
  "event_id": "EVT-2026-9812",
  "provider_reference": "PRV-ASYNC-88912",
  "event_type": "POLICY_ISSUED",
  "policy_number": "POL-2026-443322"
}
```

### 4. Reconcile Policy
- **Endpoint:** `POST /api/policy-requests/{id}/reconcile`

---

## 7. Known Assumptions & Limitations

1. **In-Process Mock Provider:** To ensure zero external dependencies and frictionless local reviewer evaluation, the mock insurer is hosted within the same Laravel application instance under `/api/mock-insurer/*` backed by a `mock_insurer_policies` table.
2. **Synchronous HTTP by Default:** Queue drivers are configured for database/sync execution out of the box so that reviewer testing does not require Redis or worker daemon processes.
3. **Database Portability:** Configured with SQLite by default for instant local execution without MySQL service dependencies, but strictly adheres to ANSI SQL migrations compatible with MySQL 8.0+ and PostgreSQL.
