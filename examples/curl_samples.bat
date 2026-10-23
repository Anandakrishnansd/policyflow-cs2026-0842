@echo off
REM PolicyFlow Windows cURL Test Runner

set BASE_URL=http://localhost:8000

echo [1] Submitting Policy Intake...
curl -s -X POST "%BASE_URL%/api/policy-requests" -H "Content-Type: application/json" -H "Accept: application/json" -d "{\"client_request_id\":\"AGT-2026-0001\",\"agent_id\":\"AGENT-17\",\"customer_name\":\"Sample Customer\",\"date_of_birth\":\"1998-04-10\",\"destination\":\"INTERNATIONAL\",\"trip_start\":\"2026-11-10\",\"trip_end\":\"2026-11-14\",\"plan_code\":\"PLUS\",\"sum_insured\":300000}"

echo.
echo [2] Repeating identical intake (Idempotency check)...
curl -s -X POST "%BASE_URL%/api/policy-requests" -H "Content-Type: application/json" -H "Accept: application/json" -d "{\"client_request_id\":\"AGT-2026-0001\",\"agent_id\":\"AGENT-17\",\"customer_name\":\"Sample Customer\",\"date_of_birth\":\"1998-04-10\",\"destination\":\"INTERNATIONAL\",\"trip_start\":\"2026-11-10\",\"trip_end\":\"2026-11-14\",\"plan_code\":\"PLUS\",\"sum_insured\":300000}"

echo.
echo [3] Listing policies...
curl -s "%BASE_URL%/api/policy-requests" -H "Accept: application/json"
