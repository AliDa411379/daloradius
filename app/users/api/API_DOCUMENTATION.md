# DaloRADIUS API Documentation
## ERP Integration - Agent & User APIs

**Total APIs: 13**

All APIs are located in: `app/users/api/`

---

## Authentication

**All APIs require an API key for authentication.**

### Header Format:
```
X-API-Key: your-api-key-here
```

### Configuration:
API keys are defined in `app/users/api/config.php`:
- `agent_api_production` - For agent mobile app/portal
- `agent_api_test` - For development and testing
- `admin_api_production` - For administrative tasks
- `erp_api_production` - For ERP system integration

### Security Features:
- ✅ API key authentication (configurable)
- ✅ Rate limiting (60 requests/minute)
- ✅ IP whitelisting (optional)
- ✅ CORS configuration
- ✅ Request/error logging

### Example:
```bash
curl -H "X-API-Key: b1b266069ed2850f4024cd1efa4273a42262482456a8ffe26894d654d4795188" \
     https://wifi.samanet.sy/api/user_balance.php?username=testuser
```

---

## Error Responses
All APIs return errors in this format:
```json
{
  "success": false,
  "error": "Error message here",
  "error_code": "ERROR_CODE_HERE"
}
```

---

## 1. Agent Balance Topup

**File**: `agent_topup_balance.php`  
**Methods**: POST  
**Description**: Add balance to user account via agent

**Request**:
```json
{
  "agent_id": 1,
  "username": "testuser",
  "amount": 100.50,
  "payment_method": "cash",
  "notes": "Balance topup via agent shop"
}
```

**Response**:
```json
{
  "success": true,
  "payment_id": 123,
  "username": "testuser",
  "amount": 100.50,
  "balance_before": 50.00,
  "new_balance": 150.50,
  "agent_name": "Agent Shop A",
  "payment_date": "2025-11-24 12:30:00",
  "message": "Balance topup successful"
}
```

---

## 2. Agent Purchase Bundle

**File**: `agent_purchase_bundle.php`  
**Methods**: POST  
**Description**: Purchase and auto-activate bundle for user

**Request**:
```json
{
  "agent_id": 1,
  "username": "testuser",
  "plan_id": 5,
  "payment_method": "cash"
}
```

**Response**:
```json
{
  "success": true,
  "bundle_id": 456,
  "plan_name": "50GB Monthly Bundle",
  "amount_charged": 30.00,
  "new_balance": 120.50,
  "expiry_date": "2025-12-24 12:30:00",
  "agent_name": "Agent Shop A",
  "agent_payment_id": 124,
  "radius_access_granted": true,
  "message": "Bundle purchased and activated successfully"
}
```

---

## 3. Get Active Users by Agent

**File**: `agent_get_active_users.php`  
**Methods**: GET, POST  
**Description**: Get list of users who made payments via specific agent

**Request** (GET):
```
GET /app/users/api/agent_get_active_users.php?agent_id=1&status=active&limit=50&offset=0
```

**Request** (POST):
```json
{
  "agent_id": 1,
  "status": "active",
  "subscription_type": "all",
  "limit": 50,
  "offset": 0
}
```

**Response**:
```json
{
  "success": true,
  "total_count": 125,
  "returned_count": 50,
  "limit": 50,
  "offset": 0,
  "users": [
    {
      "username": "user123",
      "full_name": "John Doe",
      "subscription_type": "monthly",
      "plan_name": "Basic 10GB",
      "balance": 25.50,
      "status": "active",
      "creation_date": "2025-10-15",
      "last_payment": "2025-11-20",
      "last_payment_amount": 30.00
    },
    {
      "username": "user456",
      "full_name": "Jane Smith",
      "subscription_type": "prepaid",
      "plan_name": null,
      "balance": 150.00,
      "active_bundle": "50GB Bundle",
      "bundle_expiry": "2025-12-10",
      "status": "active",
      "creation_date": "2025-11-01"
    }
  ]
}
```

---

## 4. Agent Payment History

**File**: `agent_payment_history.php`  
**Methods**: GET, POST  
**Description**: Get payment history for specific agent with statistics

**Request** (GET):
```
GET /app/users/api/agent_payment_history.php?agent_id=1&start_date=2025-11-01&end_date=2025-11-30&limit=50
```

**Request** (POST):
```json
{
  "agent_id": 1,
  "start_date": "2025-11-01",
  "end_date": "2025-11-30",
  "limit": 50,
  "offset": 0
}
```

**Response**:
```json
{
  "success": true,
  "agent_id": 1,
  "date_range": {
    "start": "2025-11-01",
    "end": "2025-11-30"
  },
  "statistics": {
    "total_transactions": 245,
    "total_amount": 12500.50,
    "topup_amount": 8000.00,
    "bundle_amount": 4500.50
  },
  "returned_count": 50,
  "payments": [
    {
      "payment_id": 123,
      "username": "user123",
      "user_full_name": "John Doe",
      "payment_type": "balance_topup",
      "amount": 100.00,
      "payment_date": "2025-11-24 10:30:00",
      "payment_method": "cash",
      "balance_before": 50.00,
      "balance_after": 150.00,
      "notes": "Balance topup"
    }
  ]
}
```

---

## 5. User Balance Lookup

**File**: `user_balance.php`  
**Methods**: GET, POST  
**Description**: Quick lookup of user balance and active bundle

**Request** (GET):
```
GET /app/users/api/user_balance.php?username=testuser
```

**Request** (POST):
```json
{
  "username": "testuser"
}
```

**Response** (Monthly User):
```json
{
  "success": true,
  "username": "testuser",
  "subscription_type": "monthly",
  "subscription_type_display": "Monthly Subscription",
  "plan_name": "Basic 10GB",
  "status": "Active",
  "balances": {
    "money": 125.50,
    "traffic_mb": 0,
    "time_minutes": 0
  }
}
```

**Response** (Prepaid User):
```json
{
  "success": true,
  "username": "testuser2",
  "subscription_type": "prepaid",
  "subscription_type_display": "Prepaid Bundle",
  "plan_name": "50GB Bundle",
  "status": "Active",
  "balances": {
    "money": 75.00,
    "traffic_mb": 0,
    "time_minutes": 0
  },
  "active_bundle": {
    "bundle_id": 456,
    "plan_name": "50GB Monthly Bundle",
    "purchase_date": "2025-11-24 10:00:00",
    "activation_date": "2025-11-24 10:00:00",
    "expiry_date": "2025-12-24 10:00:00",
    "status": "active",
    "days_remaining": 29
  }
}
```

---

## 6. Comprehensive User Info

**File**: `user_comprehensive_info.php`  
**Methods**: GET, POST  
**Description**: Get ALL user information in single call

**Request** (GET):
```
GET /app/users/api/user_comprehensive_info.php?username=testuser
```

**Response**: See `api_endpoints_supplement.md` for full response format.

Includes:
- Personal info (name, email, phone, address)
- Subscription details
- All balances (money, traffic, time)
- Active bundle
- Payment history (last 20)
- Bundle history (last 20)
- Usage summary (total traffic, time, sessions)

---

## 7. Payment Refund

**File**: `payment_refund.php`  
**Methods**: POST  
**Description**: Refund or reverse an agent payment

**Request** (Full Refund):
```json
{
  "payment_reference_type": "agent_payment",
  "payment_reference_id": 123,
  "refund_amount": 100.00,
  "refund_reason": "Incorrect payment amount",
  "performed_by": "operator_username"
}
```

**Request** (Partial Refund):
```json
{
  "payment_reference_type": "agent_payment",
  "payment_reference_id": 123,
  "refund_amount": 50.00,
  "partial": true,
  "refund_reason": "Partial refund requested",
  "performed_by": "operator_username"
}
```

**Response**:
```json
{
  "success": true,
  "refund_id": 789,
  "original_payment_id": 123,
  "payment_type": "agent_payment",
  "refund_amount": 100.00,
  "original_amount": 100.00,
  "is_partial": false,
  "user_balance_before": 150.50,
  "user_balance_after": 250.50,
  "refund_date": "2025-11-24 12:45:00",
  "message": "Payment refunded successfully"
}
```

---

## 8. Plan Lookups

**File**: `plan_lookups.php`  
**Methods**: GET, POST  
**Description**: Get available billing plans and bundles

**Request** (GET):
```
GET /app/users/api/plan_lookups.php?subscription_type=prepaid&bundle_only=true
```

**Request** (POST):
```json
{
  "subscription_type": "prepaid",
  "bundle_only": true,
  "include_details": true
}
```

**Parameters**:
- `subscription_type` (optional): "monthly", "prepaid", or "all" (default: "all")
- `bundle_only` (optional): true/false (default: false)
- `include_details` (optional): Include traffic/time limits (default: true)

**Response**:
```json
{
  "success": true,
  "total_plans": 5,
  "summary": {
    "total_bundles": 5,
    "total_monthly": 0,
    "total_prepaid": 5
  },
  "filters_applied": {
    "subscription_type": "prepaid",
    "bundle_only": true
  },
  "plans": [
    {
      "plan_id": 5,
      "plan_name": "10GB Daily Bundle",
      "plan_type": "Prepaid",
      "cost": 5.00,
      "currency": "USD",
      "is_bundle": true,
      "subscription_type": "prepaid",
      "bundle_details": {
        "validity_days": 1,
        "validity_hours": 0,
        "auto_renew": false,
        "total_validity_hours": 24,
        "validity_display": "1 days"
      },
      "details": {
        "traffic_total_mb": 10240,
        "traffic_upload_mb": 0,
        "traffic_download_mb": 0,
        "time_bank_minutes": 0,
        "recurring": false,
        "recurring_period": "",
        "radius_profiles": ["prepaid_10gb"]
      }
    },
    {
      "plan_id": 6,
      "plan_name": "50GB Monthly Bundle",
      "plan_type": "Prepaid",
      "cost": 30.00,
      "currency": "USD",
      "is_bundle": true,
      "subscription_type": "prepaid",
      "bundle_details": {
        "validity_days": 30,
        "validity_hours": 0,
        "auto_renew": false,
        "total_validity_hours": 720,
        "validity_display": "30 days"
      },
      "details": {
        "traffic_total_mb": 51200,
        "traffic_upload_mb": 0,
        "traffic_download_mb": 0,
        "time_bank_minutes": 0,
        "recurring": false,
        "recurring_period": "",
        "radius_profiles": ["prepaid_50gb"]
      }
    }
  ]
}
```

---

## 9. Bulk Balance Topup

**File**: `agent_bulk_topup_balance.php`  
**Methods**: POST  
**Description**: Add balance to multiple users at once (max 100 users per request)

**Request**:
```json
{
  "agent_id": 1,
  "users": [
    {"username": "user1", "amount": 100.00},
    {"username": "user2", "amount": 50.00},
    {"username": "user3", "amount": 75.50}
  ],
  "notes": "Bulk topup for January"
}
```

**Response**:
```json
{
  "success": true,
  "data": {
    "total_users": 3,
    "successful": 3,
    "failed": 0,
    "total_amount": 225.50,
    "details": [
      {
        "username": "user1",
        "amount": 100.00,
        "success": true,
        "old_balance": 50.00,
        "new_balance": 150.00,
        "message": "Balance added successfully"
      },
      {
        "username": "user2",
        "amount": 50.00,
        "success": true,
        "old_balance": 25.00,
        "new_balance": 75.00,
        "message": "Balance added successfully"
      },
      {
        "username": "user3",
        "amount": 75.50,
        "success": true,
        "old_balance": 100.00,
        "new_balance": 175.50,
        "message": "Balance added successfully"
      }
    ]
  },
  "message": "Bulk topup completed"
}
```

**Error Example** (some failed):
```json
{
  "success": true,
  "data": {
    "total_users": 3,
    "successful": 2,
    "failed": 1,
    "total_amount": 150.00,
    "details": [
      {
        "username": "user1",
        "amount": 100.00,
        "success": true,
        "old_balance": 50.00,
        "new_balance": 150.00,
        "message": "Balance added successfully"
      },
      {
        "username": "invaliduser",
        "amount": 50.00,
        "success": false,
        "message": "User not found or not assigned to this agent"
      },
      {
        "username": "user3",
        "amount": 50.00,
        "success": true,
        "old_balance": 75.00,
        "new_balance": 125.00,
        "message": "Balance added successfully"
      }
    ]
  },
  "message": "Bulk topup completed"
}
```

---

## 10. Bundle Purchase Report

**File**: `report_bundle_purchases.php`  
**Methods**: GET  
**Description**: Get bundle purchase statistics and detailed transaction data

**Request**:
```
GET /app/users/api/report_bundle_purchases.php?start_date=2025-01-01&end_date=2025-01-31&agent_id=1&status=active
```

**Parameters**:
- `start_date` (optional): Start date (default: first day of current month)
- `end_date` (optional): End date (default: last day of current month)
- `agent_id` (optional): Filter by agent ID
- `status` (optional): Filter by status (active/expired/used)
- `username` (optional): Filter by specific user

**Response**:
```json
{
  "success": true,
  "data": {
    "period": {
      "start_date": "2025-01-01",
      "end_date": "2025-01-31"
    },
    "summary": {
      "total_purchases": 245,
      "total_revenue": 7350.00,
      "unique_users": 182,
      "active_bundles": 198,
      "expired_bundles": 47
    },
    "by_plan": [
      {
        "plan_name": "50GB Monthly Bundle",
        "count": 120,
        "revenue": 3600.00
      },
      {
        "plan_name": "10GB Daily Bundle",
        "count": 98,
        "revenue": 490.00
      }
    ],
    "purchases": [
      {
        "id": 456,
        "username": "user123",
        "bundle_name": "50GB Monthly Bundle",
        "cost": 30.00,
        "currency": "USD",
        "purchase_date": "2025-01-24 10:30:00",
        "activation_date": "2025-01-24 10:30:00",
        "expiry_date": "2025-02-24 10:30:00",
        "status": "active",
        "validity": {
          "days": 30,
          "hours": 0
        },
        "remaining": {
          "days": 29,
          "hours": 23
        }
      }
    ]
  },
  "message": "Bundle purchase report generated"
}
```

---

## 11. Payment Report

**File**: `report_payments.php`  
**Methods**: GET  
**Description**: Get payment transaction statistics and history

**Request**:
```
GET /app/users/api/ report_payments.php?start_date=2025-01-01&end_date=2025-01-31&agent_id=1&payment_type=balance_topup
```

**Parameters**:
- `start_date` (optional): Start date (default: first day of current month)
- `end_date` (optional): End date (default: last day of current month)
- `agent_id` (optional): Filter by agent ID
- `payment_type` (optional): Filter by type (balance_topup/bundle_purchase)

**Response**:
```json
{
  "success": true,
  "data": {
    "period": {
      "start_date": "2025-01-01",
      "end_date": "2025-01-31"
    },
    "summary": {
      "total_transactions": 523,
      "total_amount": 15750.50,
      "unique_users": 298,
      "balance_topups": {
        "count": 378,
        "amount": 11250.50
      },
      "bundle_purchases": {
        "count": 145,
        "amount": 4500.00
      }
    },
    "by_agent": [
      {
        "agent_id": 1,
        "agent_name": "Agent Shop A",
        "transaction_count": 245,
        "total_amount": 7350.00
      },
      {
        "agent_id": 2,
        "agent_name": "Agent Shop B",
        "transaction_count": 178,
        "total_amount": 5320.50
      }
    ],
    "transactions": [
      {
        "id": 123,
        "agent": {
          "id": 1,
          "name": "Agent Shop A"
        },
        "username": "user123",
        "payment_type": "balance_topup",
        "amount": 100.00,
        "balance_before": 50.00,
        "balance_after": 150.00,
        "bundle": null,
        "payment_method": "cash",
        "payment_date": "2025-01-24 10:30:00",
        "notes": "Balance topup"
      },
      {
        "id": 124,
        "agent": {
          "id": 1,
          "name": "Agent Shop A"
        },
        "username": "user456",
        "payment_type": "bundle_purchase",
        "amount": 30.00,
        "balance_before": 150.00,
        "balance_after": 120.00,
        "bundle": {
          "id": 5,
          "name": "50GB Monthly Bundle"
        },
        "payment_method": "cash",
        "payment_date": "2025-01-24 11:15:00",
        "notes": "Bundle purchase"
      }
    ]
  },
  "message": "Payment report generated"
}
```

---

## Testing Examples

### cURL Examples:

**1. Balance Topup**:
```bash
curl -X POST https://wifi.samanet.sy/api/agent_topup_balance.php \
  -H "Content-Type: application/json" \
  -H "X-API-Key: your-api-key-here" \
  -d '{
    "agent_id": 1,
    "username": "testuser",
    "amount": 100
  }'
```

**2. Purchase Bundle**:
```bash
curl -X POST https://wifi.samanet.sy/api/agent_purchase_bundle.php \
  -H "Content-Type: application/json" \
  -H "X-API-Key: your-api-key-here" \
  -d '{
    "agent_id": 1,
    "username": "testuser",
    "plan_id": 5
  }'
```

**3. Get User Balance**:
```bash
curl https://wifi.samanet.sy/api/user_balance.php?username=testuser \
  -H "X-API-Key: your-api-key-here"
```

**4. Get Active Users**:
```bash
curl https://wifi.samanet.sy/api/agent_get_active_users.php?agent_id=1 \
  -H "X-API-Key: your-api-key-here"
```

**5. Get All Bundles**:
```bash
curl https://wifi.samanet.sy/api/plan_lookups.php?bundle_only=true \
  -H "X-API-Key: your-api-key-here"
```

**6. Bulk Balance Topup**:
```bash
curl -X POST https://wifi.samanet.sy/api/agent_bulk_topup_balance.php \
  -H "Content-Type: application/json" \
  -H "X-API-Key: your-api-key-here" \
  -d '{
    "agent_id": 1,
    "users": [
      {"username": "user1", "amount": 100},
      {"username": "user2", "amount": 50}
    ],
    "notes": "January bulk topup"
  }'
```

**7. Get Bundle Purchase Report**:
```bash
curl "https://wifi.samanet.sy/api/report_bundle_purchases.php?start_date=2025-01-01&end_date=2025-01-31&agent_id=1" \
  -H "X-API-Key: your-api-key-here"
```

**8. Get Payment Report**:
```bash
curl "https://wifi.samanet.sy/api/report_payments.php?start_date=2025-01-01&end_date=2025-01-31&payment_type=balance_topup" \
  -H "X-API-Key: your-api-key-here"
```

---

## Implementation Notes

### Base URL:
Replace `your-server` with your actual domain:
- Production: `https://wifi.samanet.sy/api/`
- Development: `http://localhost/daloradius/app/users/api/`

**Note:** In examples, use the full production URL like:
```
https://wifi.samanet.sy/api/agent_topup_balance.php
```

### API Key Management:
1. API keys are configured in `app/users/api/config.php`
2. Generate secure keys using: `openssl rand -hex 32`
3. Different keys for different client types (agent, admin, ERP)
4. Keys can be enabled/disabled individually

### Security Configuration:
Located in `app/users/api/config.php`:
- `API_AUTH_ENABLED` - Enable/disable API key auth
- `API_RATE_LIMIT_ENABLED` - Enable rate limiting
- `API_RATE_LIMIT_PER_MINUTE` - Requests per minute (default: 60)
- `API_ALLOWED_IPS` - IP whitelist (empty = allow all)
- `API_CORS_ALLOWED_ORIGINS` - CORS origins

### Production Checklist:
- ✅ Set `API_AUTH_ENABLED` to `true`
- ✅ Use HTTPS only
- ✅ Configure IP whitelist in `API_ALLOWED_IPS`
- ✅ Set specific CORS origins (remove `*`)
- ✅ Generate unique API keys for each client
- ✅ Monitor API logs: `var/logs/api_access.log`
- ✅ Monitor error logs: `var/logs/api_errors.log`
