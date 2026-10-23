#!/usr/bin/env bash
# PolicyFlow Sample cURL Scenarios

BASE_URL="http://localhost:8000"

echo "=== 1. Create Policy Request (Intake) ==="
INTAKE_RES=$(curl -s -X POST "${BASE_URL}/api/policy-requests" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "client_request_id": "AGT-2026-0001",
    "agent_id": "AGENT-17",
    "customer_name": "Sample Customer",
    "date_of_birth": "1998-04-10",
    "destination": "INTERNATIONAL",
    "trip_start": "2026-11-10",
    "trip_end": "2026-11-14",
    "plan_code": "PLUS",
    "sum_insured": 300000
  }')
echo "$INTAKE_RES"

echo -e "\n=== 2. Repeated Intake (Idempotent 200 OK) ==="
curl -s -X POST "${BASE_URL}/api/policy-requests" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "client_request_id": "AGT-2026-0001",
    "agent_id": "AGENT-17",
    "customer_name": "Sample Customer",
    "date_of_birth": "1998-04-10",
    "destination": "INTERNATIONAL",
    "trip_start": "2026-11-10",
    "trip_end": "2026-11-14",
    "plan_code": "PLUS",
    "sum_insured": 300000
  }'

echo -e "\n=== 3. List Policies ==="
curl -s "${BASE_URL}/api/policy-requests" -H "Accept: application/json"
