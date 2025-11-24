# DaloRadius Screens & API Guide for ERP Integration

This guide shows exactly which DaloRadius screens need to be modified and what APIs need to be created to support the dual subscription model ERP integration.

---

## 📋 Table of Contents

1. [Screens to Modify](#screens-to-modify)
2. [New Screens to Create](#new-screens-to-create)
3. [APIs to Create](#apis-to-create)
4. [Agent Portal Enhancement](#agent-portal-enhancement)
5. [Implementation Steps](#implementation-steps)

---

## 🖥️ Screens to Modify

### 1. **Billing Plans** - `app/operators/bill-plans-new.php` & `bill-plans-edit.php`

**Purpose**: Add subscription type selection and bundle-specific fields

**Fields to Add**:
```php
// After line 80 in bill-plans-new.php, add these new POST variables:

$subscriptionType = (array_key_exists('subscriptionType', $_POST) && !empty(trim($_POST['subscriptionType'])) &&
                     in_array(trim($_POST['subscriptionType']), array('fixed_monthly', 'prepaid_bundle')))
                  ? trim($_POST['subscriptionType']) : 'fixed_monthly';

$bundleValidityDays = (array_key_exists('bundleValidityDays', $_POST) && !empty(trim($_POST['bundleValidityDays'])))
                      ? intval(trim($_POST['bundleValidityDays'])) : NULL;

$allowAgentSale = (array_key_exists('allowAgentSale', $_POST) && isset($_POST['allowAgentSale']))
                  ? 1 : 0;

$requiresBalance = (array_key_exists('requiresBalance', $_POST) && isset($_POST['requiresBalance']))
                   ? 1 : 0;

$autoRenew = (array_key_exists('autoRenew', $_POST) && isset($_POST['autoRenew']))
             ? 1 : 0;
```

**SQL to Modify** (around line 112):
```sql
INSERT INTO billing_plans (
    id, planName, planId, planType, planTimeBank, planTimeType,
    planTimeRefillCost, planBandwidthUp, planBandwidthDown, planTrafficTotal,
    planTrafficUp, planTrafficDown, planTrafficRefillCost, planRecurring,
    planRecurringPeriod, planRecurringBillingSchedule, planCost,
    planSetupCost, planTax, planCurrency, planGroup, planActive,
    subscription_type, bundle_validity_days, allow_agent_sale, requires_balance, auto_renew,
    creationdate, creationby, updatedate, updateby
) VALUES (
    0, '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
    '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
    '%s', %s, %d, %d, %d,
    '%s', '%s', NULL, NULL
)
```

**UI Fields to Add** (around line 275):
```php
// Add after planCurrency field

$input_descriptors0[] = array(
    'type' => 'select',
    'options' => array('fixed_monthly', 'prepaid_bundle'),
    'caption' => 'Subscription Type',
    'name' => 'subscriptionType',
    'selected_value' => $subscriptionType,
    'tooltipText' => 'Choose fixed monthly or prepaid bundle',
);

$input_descriptors0[] = array(
    'name' => 'bundleValidityDays',
    'type' => 'number',
    'caption' => 'Bundle Validity (Days)',
    'value' => $bundleValidityDays,
    'tooltipText' => 'How many days the bundle is valid after activation (for prepaid bundles)',
);

$input_descriptors0[] = array(
    'type' => 'checkbox',
    'name' => 'allowAgentSale',
    'caption' => 'Allow Agent Sale',
    'checked' => $allowAgentSale,
    'tooltipText' => 'Allow agents to sell this plan',
);

$input_descriptors0[] = array(
    'type' => 'checkbox',
    'name' => 'requiresBalance',
    'caption' => 'Requires Balance',
    'checked' => $requiresBalance,
    'tooltipText' => 'Plan requires user balance for activation',
);

$input_descriptors0[] = array(
    'type' => 'checkbox',
    'name' => 'autoRenew',
    'caption' => 'Auto Renew',
    'checked' => $autoRenew,
    'tooltipText' => 'Automatically renew this plan (for monthly subscriptions)',
);
```

---

### 2. **User Management** - `app/operators/mng-new.php` & `mng-edit.php`

**Purpose**: Add subscription type and agent assignment

**Files to Modify**:
- `app/operators/mng-new.php` - When creating new users
- `app/operators/mng-edit.php` - When editing existing users

**Fields to Add**:
```php
// Add these fields in the user billing info section

$subscriptionType = (array_key_exists('subscriptionType', $_POST)) 
                    ? $_POST['subscriptionType'] : 'fixed_monthly';

$agentId = (array_key_exists('agentId', $_POST) && !empty($_POST['agentId'])) 
           ? intval($_POST['agentId']) : NULL;

$autoInvoiceDay = (array_key_exists('autoInvoiceDay', $_POST) && !empty($_POST['autoInvoiceDay'])) 
                  ? intval($_POST['autoInvoiceDay']) : 4;
```

**UI Component**:
```php
// Add to user billing info form

$input_descriptors[] = array(
    'type' => 'select',
    'options' => array('fixed_monthly', 'prepaid_bundle'),
    'caption' => 'Subscription Type',
    'name' => 'subscriptionType',
    'selected_value' => $subscriptionType,
);

// Get agents list
include_once('include/management/populate_selectbox.php');
$agents_options = get_agents_list(); // You'll need to create this function

$input_descriptors[] = array(
    'type' => 'select',
    'options' => $agents_options,
    'caption' => 'Agent',
    'name' => 'agentId',
    'selected_value' => $agentId,
    'tooltipText' => 'Assign user to an agent (for prepaid bundles)',
);

$input_descriptors[] = array(
    'type' => 'number',
    'name' => 'autoInvoiceDay',
    'caption' => 'Auto Invoice Day',
    'value' => $autoInvoiceDay,
    'min' => 1,
    'max' => 28,
    'tooltipText' => 'Day of month to auto-generate invoice (1-28)',
);
```

---

### 3. **Agent Balance Management** - `app/agent/add-balance.php`

**Current Status**: ✅ Already exists!

**Enhancement Needed**: Connect it to the stored procedure

**Modify around the balance addition code**:
```php
// Replace manual balance updates with stored procedure call

$sql = "CALL add_user_balance(?, ?, ?, ?, ?, ?, ?, @success, @message)";
$stmt = $dbSocket->prepare($sql);
$stmt->execute([
    $user_id,
    $agent_id,
    $amount,
    $payment_method,
    $receipt_number,
    $notes,
    $operator
]);

// Get output parameters
$result = $dbSocket->query("SELECT @success AS success, @message AS message");
$output = $result->fetchRow();

if ($output['success']) {
    $successMsg = $output['message'];
} else {
    $failureMsg = $output['message'];
}
```

---

## 🆕 New Screens to Create

### 1. **Bundle Activation Screen** - `app/operators/bundles-activate.php`

**Location**: `app/operators/bundles-activate.php`

**Purpose**: Allow operators/agents to activate bundles for users

**Key Features**:
- Search/select user
- Display user's current balance
- Show available bundles (plans with `subscription_type = 'prepaid_bundle'`)
- Activate bundle and deduct from balance

**Example Structure**:
```php
<?php
include("library/checklogin.php");
$operator = $_SESSION['operator_user'];

include('library/check_operator_perm.php');
include_once('../common/includes/config_read.php');

$userId = (isset($_POST['userId'])) ? intval($_POST['userId']) : 0;
$planId = (isset($_POST['planId'])) ? intval($_POST['planId']) : 0;
$agentId = $_SESSION['agent_id'] ?? NULL; // If agent is logged in

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userId > 0 && $planId > 0) {
    include('../common/includes/db_open.php');
    
    // Call stored procedure
    $sql = "CALL activate_bundle(?, ?, ?, 'balance', ?, @success, @message)";
    $stmt = $dbSocket->prepare($sql);
    $stmt->execute([$userId, $planId, $agentId, $operator]);
    
    // Get results
    $result = $dbSocket->query("SELECT @success AS success, @message AS message");
    $output = $result->fetchRow();
    
    if ($output['success']) {
        $successMsg = $output['message'];
    } else {
        $failureMsg = $output['message'];
    }
    
    include('../common/includes/db_close.php');
}

// Display form with user search, balance display, and bundle selection
// ... UI code here ...
?>
```

---

### 2. **Bundle Management Screen** - `app/operators/bundles-list.php`

**Location**: `app/operators/bundles-list.php`

**Purpose**: View all bundle activations

**Features**:
- List all bundle activations
- Filter by status (active, expired, cancelled)
- Show expiry dates
- Search by username/agent

---

### 3. **Monthly Invoice Schedule** - `app/operators/bill-invoice-schedule.php`

**Location**: `app/operators/bill-invoice-schedule.php`

**Purpose**: Manage automatic monthly invoice generation

**Features**:
- List all subscribers with monthly schedules
- Edit invoice day
- Enable/disable auto-payment
- Pause/resume schedule

---

### 4. **Agent Portal Bundle Activation** - `app/agent/bundles-activate.php`

**Location**: `app/agent/bundles-activate.php`

**Purpose**: Allow agents to activate bundles for their customers

**Similar to** operators version but:
- Filtered to show only agent's customers
- Automatic agent_id assignment
- Commission tracking

---

## 🔌 APIs to Create

### 1. **Bundle Activation API** - `app/api/activate_bundle.php`

**Location**: `app/api/activate_bundle.php`

**Purpose**: ERP systems can activate bundles via API

**Request**:
```http
POST /app/api/activate_bundle.php
Content-Type: application/json
Authorization: Bearer YOUR_API_TOKEN

{
    "user_id": 123,
    "plan_id": 10,
    "agent_id": 5,
    "payment_method": "balance",
    "created_by": "erp_system"
}
```

**Response**:
```json
{
    "success": true,
    "message": "Bundle activated successfully. Expires: 2025-12-20 12:00:00",
    "data": {
        "activation_id": 456,
        "user_id": 123,
        "plan_id": 10,
        "expiry_date": "2025-12-20 12:00:00",
        "cost": 25.00,
        "new_balance": 75.00
    }
}
```

**Code**:
```php
<?php
header('Content-Type: application/json');
include_once('../common/includes/config_read.php');
include_once('../common/includes/db_open.php');

// Verify API token
$headers = getallheaders();
$token = $headers['Authorization'] ?? '';
// ... token verification logic ...

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

$userId = $data['user_id'] ?? 0;
$planId = $data['plan_id'] ?? 0;
$agentId = $data['agent_id'] ?? NULL;
$paymentMethod = $data['payment_method'] ?? 'balance';
$createdBy = $data['created_by'] ?? 'api';

// Call stored procedure
$sql = "CALL activate_bundle(?, ?, ?, ?, ?, @success, @message)";
$stmt = $dbSocket->prepare($sql);
$stmt->execute([$userId, $planId, $agentId, $paymentMethod, $createdBy]);

// Get results
$result = $dbSocket->query("SELECT @success AS success, @message AS message");
$output = $result->fetchRow();

if ($output['success']) {
    // Get activation details
    $sql = "SELECT * FROM bundle_activations WHERE user_id = ? ORDER BY id DESC LIMIT 1";
    $res = $dbSocket->query($sql, [$userId]);
    $activation = $res->fetchRow(DB_FETCHMODE_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => $output['message'],
        'data' => $activation
    ]);
} else {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $output['message']
    ]);
}

include_once('../common/includes/db_close.php');
?>
```

---

### 2. **Add Balance API** - `app/api/add_balance.php`

**Location**: `app/api/add_balance.php`

**Purpose**: ERP systems can add balance to user accounts

**Request**:
```http
POST /app/api/add_balance.php
Content-Type: application/json

{
    "user_id": 123,
    "agent_id": 5,
    "amount": 100.00,
    "payment_method": "cash",
    "receipt_number": "RCP-20251120-001",
    "notes": "Cash payment at agent shop"
}
```

**Response**:
```json
{
    "success": true,
    "message": "Balance added successfully. New balance: 100.00",
    "data": {
        "transaction_id": 789,
        "previous_balance": 0.00,
        "amount_added": 100.00,
        "new_balance": 100.00,
        "transaction_fee": 5.00
    }
}
```

---

### 3. **Get User Balance API** - `app/api/get_balance.php`

**Location**: `app/api/get_balance.php`

**Purpose**: Check user's current balance

**Request**:
```http
GET /app/api/get_balance.php?user_id=123
or
GET /app/api/get_balance.php?username=user001
```

**Response**:
```json
{
    "success": true,
    "data": {
        "user_id": 123,
        "username": "user001",
        "money_balance": 75.50,
        "subscription_type": "prepaid_bundle",
        "bundle_expiry_date": "2025-12-20 12:00:00",
        "days_until_expiry": 30
    }
}
```

---

### 4. **Bundle Status API** - `app/api/bundle_status.php`

**Location**: `app/api/bundle_status.php`

**Purpose**: Get bundle activation status for a user

**Request**:
```http
GET /app/api/bundle_status.php?user_id=123
```

**Response**:
```json
{
    "success": true,
    "data": {
        "user_id": 123,
        "username": "user001",
        "current_bundle": {
            "plan_name": "Bundle-10GB-30Days",
            "activation_date": "2025-11-20 12:00:00",
            "expiry_date": "2025-12-20 12:00:00",
            "days_remaining": 30,
            "status": "active",
            "cost": 25.00
        },
        "bundle_history": [
            {
                "plan_name": "Bundle-5GB-15Days",
                "activation_date": "2025-11-05 10:00:00",
                "expiry_date": "2025-11-20 10:00:00",
                "status": "expired",
                "cost": 15.00
            }
        ]
    }
}
```

---

### 5. **Generate Invoice API** - `app/api/generate_invoice.php`

**Location**: `app/api/generate_invoice.php`

**Purpose**: Manually trigger invoice generation for monthly subscribers

**Request**:
```http
POST /app/api/generate_invoice.php
Content-Type: application/json

{
    "user_id": 123
}
```

**Response**:
```json
{
    "success": true,
    "message": "Invoice generated successfully",
    "data": {
        "invoice_id": 456,
        "user_id": 123,
        "amount": 50.00,
        "status": "paid",
        "paid_from_balance": true,
        "new_balance": 25.00
    }
}
```

---

### 6. **Agent Performance API** - `app/api/agent_performance.php`

**Location**: `app/api/agent_performance.php`

**Purpose**: Get agent sales and performance metrics

**Request**:
```http
GET /app/api/agent_performance.php?agent_id=5&from_date=2025-11-01&to_date=2025-11-30
```

**Response**:
```json
{
    "success": true,
    "data": {
        "agent_id": 5,
        "agent_name": "Ali Mohammed",
        "period": {
            "from": "2025-11-01",
            "to": "2025-11-30"
        },
        "metrics": {
            "total_topups": 5000.00,
            "total_transactions": 45,
            "bundles_sold": 30,
            "commission_earned": 250.00,
            "average_transaction": 111.11
        }
    }
}
```

---

## 👤 Agent Portal Enhancement

### Screens in `app/agent/` to Enhance

#### 1. **Dashboard** - `app/agent/index.php`
**Add**:
- Total balance added today/this month
- Bundles sold today/this month
- Commission earned
- Quick links to add balance and activate bundles

#### 2. **Add Balance** - `app/agent/add-balance.php`
**Current**: ✅ Already exists
**Enhance**: Use stored procedure instead of manual SQL

#### 3. **Activate Bundle** - `app/agent/bundles-activate.php` (NEW)
**Create new screen** for agents to activate bundles for their customers

#### 4. **My Customers** - `app/agent/users/list.php` (ENHANCE)
**Show**:
- Customer subscription types
- Bundle expiry dates
- Current balance
- Quick action buttons

---

## 📝 Implementation Steps

### Phase 1: Database (Already Done ✅)
1. Run `erp_integration_migration.sql`
2. Verify all tables and procedures created

### Phase 2: Backend Modifications (Week 1)
1. Modify `bill-plans-new.php` and `bill-plans-edit.php`
2. Modify user management screens
3. Update `app/agent/add-balance.php` to use stored procedure

### Phase 3: New Screens (Week 2)
1. Create `bundles-activate.php` for operators
2. Create `bundles-activate.php` for agents
3. Create `bundles-list.php`
4. Create `bill-invoice-schedule.php`

### Phase 4: APIs (Week 3)
1. Create `activate_bundle.php` API
2. Create `add_balance.php` API
3. Create `get_balance.php` API
4. Create `bundle_status.php` API
5. Create `generate_invoice.php` API
6. Create `agent_performance.php` API

### Phase 5: Testing (Week 4)
1. Test bundle activation flow
2. Test agent balance addition
3. Test monthly invoice generation
4. Test all APIs with Postman/curl
5. Integration testing with ERP

---

## 🔐 API Security

All APIs should include:

```php
// API Authentication
function verify_api_token($token) {
    global $dbSocket;
    
    // Remove 'Bearer ' prefix
    $token = str_replace('Bearer ', '', $token);
    
    // Verify token in database
    $sql = "SELECT * FROM api_tokens WHERE token = ? AND is_active = 1 AND expires_at > NOW()";
    $res = $dbSocket->query($sql, [$token]);
    
    if ($res->numRows() === 0) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid or expired API token']);
        exit;
    }
    
    return $res->fetchRow(DB_FETCHMODE_ASSOC);
}

// Rate Limiting
function check_rate_limit($api_key, $max_requests = 100, $period_minutes = 60) {
    global $dbSocket;
    
    $sql = "SELECT COUNT(*) FROM api_requests 
            WHERE api_key = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)";
    $res = $dbSocket->query($sql, [$api_key, $period_minutes]);
    $count = intval($res->fetchRow()[0]);
    
    if ($count >= $max_requests) {
        http_response_code(429);
        echo json_encode(['error' => 'Rate limit exceeded']);
        exit;
    }
    
    // Log request
    $sql = "INSERT INTO api_requests (api_key, endpoint, created_at) VALUES (?, ?, NOW())";
    $dbSocket->query($sql, [$api_key, $_SERVER['REQUEST_URI']]);
}
```

---

## 🎯 Priority Order

1. **MUST HAVE** (Do first):
   - ✅ Database migration (Already done)
   - Modify billing plans screen
   - Enhance agent add-balance screen
   - Create bundle activation API

2. **SHOULD HAVE** (Do second):
   - Create bundle activation screen
   - Create bundle list screen
   - Get balance API
   - Bundle status API

3. **NICE TO HAVE** (Do later):
   - Invoice schedule screen
   - Agent performance API
   - Advanced reporting

---

## 📞 ERP Integration Endpoints Summary

Your ERP system should integrate with these endpoints:

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/add_balance.php` | POST | Add money to user balance |
| `/api/activate_bundle.php` | POST | Activate a bundle for user |
| `/api/get_balance.php` | GET | Check user balance |
| `/api/bundle_status.php` | GET | Get bundle activation status |
| `/api/generate_invoice.php` | POST | Generate monthly invoice |
| `/api/agent_performance.php` | GET | Get agent metrics |

---

**Need help implementing any of these screens or APIs? Let me know which one to start with!**
