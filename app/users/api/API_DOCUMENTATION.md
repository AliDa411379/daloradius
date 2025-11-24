# DaloRADIUS API Documentation
## ERP Integration - Agent & User APIs

All APIs are located in: `app/users/api/`

---

## Authentication
Currently, these APIs do not require authentication. **Implement API key authentication before production use.**

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

## Testing Examples

### cURL Examples:

**1. Balance Topup**:
```bash
curl -X POST http://your-server/app/users/api/agent_topup_balance.php \
  -H "Content-Type: application/json" \
  -d '{
    "agent_id": 1,
    "username": "testuser",
    "amount": 100
  }'
```

**2. Purchase Bundle**:
```bash
curl -X POST http://your-server/app/users/api/agent_purchase_bundle.php \
  -H "Content-Type: application/json" \
  -d '{
    "agent_id": 1,
    "username": "testuser",
    "plan_id": 5
  }'
```

**3. Get User Balance**:
```bash
curl http://your-server/app/users/api/user_balance.php?username=testuser
```

**4. Get Active Users**:
```bash
curl http://your-server/app/users/api/agent_get_active_users.php?agent_id=1
```

**5. Get All Bundles**:
```bash
curl http://your-server/app/users/api/plan_lookups.php?bundle_only=true
```

---

## Security Recommendations

### Before Production:
1. **Implement API Key Authentication**
2. **Add Rate Limiting**
3. **Use HTTPS Only**
4. **Validate IP Addresses (optional)**
5. **Add Request Logging**
6. **Implement CORS properly**

### Example API Key Check:
```php
$apiKey = isset($_SERVER['HTTP_X_API_KEY']) ? $_SERVER['HTTP_X_API_KEY'] : '';
if ($apiKey !== 'your-secret-api-key') {
    sendError('Unauthorized', 401);
}
```
