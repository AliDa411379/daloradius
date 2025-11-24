# DaloRADIUS User Creation and Agent Management APIs

## Overview
These APIs provide endpoints for creating agents and RADIUS users with ERP invoice ID tracking.

## Endpoints

### 1. Create Agent API
```
POST /api/create_agent.php
```

### 2. Create User with ERP Integration
```
POST /api/create_user_erp.php
```

## Authentication

Both APIs support two authentication methods:

1. **API Key** (header or parameter):
   ```
   X-API-Key: your-secret-api-key-here
   ```
   Or as query/POST parameter:
   ```
   ?api_key=your-secret-api-key-here
   ```

2. **Session** - If called from authenticated web interface

---

## API 1: Create Agent

Creates a new agent record and automatically creates a corresponding operator record with `is_agent=1` flag. Returns both agent ID and operator ID, along with a generated password for the operator.

### Request Format

#### JSON Content-Type (Recommended)
```bash
curl -X POST http://your-domain/app/api/create_agent.php \
  -H "Content-Type: application/json" \
  -H "X-API-Key: your-secret-api-key-here" \
  -d '{
    "username": "agent_username"
  }'
```

#### Form Data
```bash
curl -X POST http://your-domain/app/api/create_agent.php \
  -d "username=agent_username" \
  -d "api_key=your-secret-api-key-here"
```

### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `username` | string | Yes | Unique agent username (max 255 chars). Creates both agent and operator records |
| `api_key` | string | Optional | API key (if not using header) |

### Response Format

#### Success Response (201)
```json
{
  "success": true,
  "message": "Agent and operator created successfully",
  "data": {
    "agent_id": 42,
    "operator_id": 15,
    "username": "agent_username",
    "password": "aBcD3eFgH9Km",
    "created_at": "2025-11-17 14:04:13"
  },
  "timestamp": "2025-11-17 14:04:13"
}
```

#### Error Responses

**400 - Bad Request**
```json
{
  "success": false,
  "message": "username is required",
  "data": null,
  "timestamp": "2025-11-17 14:04:13"
}
```

**400 - Duplicate Agent or Operator**
```json
{
  "success": false,
  "message": "Agent with this username already exists",
  "data": null,
  "timestamp": "2025-11-17 14:04:13"
}
```
Or:
```json
{
  "success": false,
  "message": "Operator with this username already exists",
  "data": null,
  "timestamp": "2025-11-17 14:04:13"
}
```

**401 - Unauthorized**
```json
{
  "success": false,
  "message": "Authentication required",
  "data": null,
  "timestamp": "2025-11-17 14:04:13"
}
```

**500 - Internal Server Error**
```json
{
  "success": false,
  "message": "Failed to create agent: <error details>",
  "data": null,
  "timestamp": "2025-11-17 14:04:13"
}
```

### Example Usage - Python
```python
import requests
import json

url = "http://your-domain/app/api/create_agent.php"
headers = {
    "Content-Type": "application/json",
    "X-API-Key": "your-secret-api-key-here"
}
data = {
    "username": "my_agent"
}

response = requests.post(url, headers=headers, json=data)
result = response.json()

if result['success']:
    agent_id = result['data']['agent_id']
    operator_id = result['data']['operator_id']
    password = result['data']['password']
    print(f"Agent created with ID: {agent_id}")
    print(f"Operator created with ID: {operator_id}")
    print(f"Operator password: {password}")
else:
    print(f"Error: {result['message']}")
```

---

## API 2: Create User with ERP Integration

Creates RADIUS users with ERP invoice ID tracking and returns QR code for easy credential sharing. Optionally assigns the user to an agent.

### Request Format

#### JSON Content-Type (Recommended)
```bash
curl -X POST http://your-domain/app/api/create_user_erp.php \
  -H "Content-Type: application/json" \
  -H "X-API-Key: your-secret-api-key-here" \
  -d '{
    "external_invoice_id": "INV-2025-001234",
    "plan_name": "Premium Plan",
    "agent_id": 42
  }'
```

#### Form Data
```bash
curl -X POST http://your-domain/app/api/create_user_erp.php \
  -d "external_invoice_id=INV-2025-001234" \
  -d "plan_name=Premium Plan" \
  -d "agent_id=42" \
  -d "api_key=your-secret-api-key-here"
```

Note: `agent_id` comes from the Create Agent API response

### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `external_invoice_id` | string | Yes | Unique external invoice identifier (max 255 chars) |
| `plan_name` | string | Yes | Name of billing plan to assign to user |
| `agent_id` | integer | No | Agent ID to assign user to (defaults to 1 if not provided) |
| `api_key` | string | Optional | API key (if not using header) |

## Response Format

### Success Response (201)
```json
{
  "success": true,
  "message": "User created successfully",
  "data": {
    "username": "aBcD3eFgH9",
    "password": "xY7zPqR4mN2KwL",
    "plan_name": "Premium Plan",
    "external_invoice_id": "INV-2025-001234",
    "qrcode_url": "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=...",
    "qrcode_text": "Username: aBcD3eFgH9\nPassword: xY7zPqR4mN2KwL\nPlan: Premium Plan",
    "created_at": "2025-11-17 14:04:13",
    "traffic_balance": 102400.0,
    "time_balance": 1440.0
  },
  "timestamp": "2025-11-17 14:04:13"
}
```

### Error Responses

**400 - Bad Request**
```json
{
  "success": false,
  "message": "external_invoice_id is required",
  "data": null,
  "timestamp": "2025-11-17 14:04:13"
}
```

**401 - Unauthorized**
```json
{
  "success": false,
  "message": "Authentication required",
  "data": null,
  "timestamp": "2025-11-17 14:04:13"
}
```

**404 - Not Found**
```json
{
  "success": false,
  "message": "Plan not found",
  "data": null,
  "timestamp": "2025-11-17 14:04:13"
}
```

**500 - Internal Server Error**
```json
{
  "success": false,
  "message": "Failed to create user: <error details>",
  "data": null,
  "timestamp": "2025-11-17 14:04:13"
}
```

## What Gets Created

When a user is successfully created, the following happens:

1. **RADIUS User (radcheck)** - MD5-Password authentication attribute
2. **User Info (userinfo)** - Basic user record with creation metadata
3. **User Billing Info (userbillinfo)** - Billing information with:
   - ERP Invoice ID (for tracking)
   - Initial traffic balance from plan
   - Initial time balance from plan
   - Next billing date (if recurring)
4. **User Groups (radusergroup)** - Associates profiles from plan
5. **Mikrotik Attributes (radreply)** - Sets up:
   - Mikrotik-Total-Limit (traffic in bytes)
   - Mikrotik-Total-Limit-Gigawords
   - Session-Timeout (time in seconds)
6. **Agent Assignment** - Assigned to specified agent_id (defaults to 1 if not provided)
7. **QR Code** - Generated for easy credential sharing

## Database Changes Required

Run the migration to add the erpinvoiceid field:
```bash
mysql -u bassel -p radius < contrib/db/add_erpinvoiceid_field.sql
```

Or manually:
```sql
ALTER TABLE userbillinfo ADD COLUMN erpinvoiceid VARCHAR(255) DEFAULT NULL UNIQUE AFTER id;
```

## Configuration

Both APIs use the same API key:
```php
define('API_KEY', 'your-secret-api-key-here');
```

Edit this in `/app/api/create_agent.php` and `/app/api/create_user_erp.php`.

## Complete Workflow Example - Python

```python
import requests
import json

api_url_base = "http://your-domain/app/api"
headers = {
    "Content-Type": "application/json",
    "X-API-Key": "your-secret-api-key-here"
}

# Step 1: Create an agent (also creates operator)
print("Creating agent...")
agent_data = {
    "username": "sales_agent_01"
}

response = requests.post(f"{api_url_base}/create_agent.php", 
                        headers=headers, json=agent_data)
agent_result = response.json()

if not agent_result['success']:
    print(f"Error creating agent: {agent_result['message']}")
    exit(1)

agent_id = agent_result['data']['agent_id']
operator_id = agent_result['data']['operator_id']
operator_password = agent_result['data']['password']
print(f"Agent created with ID: {agent_id}")
print(f"Operator created - Username: sales_agent_01, Password: {operator_password}")

# Step 2: Create a user and assign to the agent using agent_id
print("\nCreating user and assigning to agent...")
user_data = {
    "external_invoice_id": "INV-2025-001234",
    "plan_name": "Premium Plan",
    "agent_id": agent_id
}

response = requests.post(f"{api_url_base}/create_user_erp.php", 
                        headers=headers, json=user_data)
user_result = response.json()

if user_result['success']:
    print(f"User created: {user_result['data']['username']}")
    print(f"Password: {user_result['data']['password']}")
    print(f"Assigned to agent ID: {agent_id}")
    print(f"QR Code: {user_result['data']['qrcode_url']}")
else:
    print(f"Error creating user: {user_result['message']}")
```

## Example Usage - Python (Create User Only)

```python
import requests
import json

url = "http://your-domain/app/api/create_user_erp.php"
headers = {
    "Content-Type": "application/json",
    "X-API-Key": "your-secret-api-key-here"
}
data = {
    "external_invoice_id": "INV-2025-001234",
    "plan_name": "Premium Plan",
    "agent_id": 42
}

response = requests.post(url, headers=headers, json=data)
result = response.json()

if result['success']:
    print(f"User created: {result['data']['username']}")
    print(f"Password: {result['data']['password']}")
    print(f"QR Code: {result['data']['qrcode_url']}")
else:
    print(f"Error: {result['message']}")
```

## Complete Workflow Example - PHP

```php
$api_url_base = "http://your-domain/app/api";
$api_key = "your-secret-api-key-here";

// Step 1: Create an agent (also creates operator)
echo "Creating agent...\n";
$agent_data = [
    "username" => "sales_agent_01"
];

$agent_options = [
    "http" => [
        "method" => "POST",
        "header" => [
            "Content-Type: application/json",
            "X-API-Key: $api_key"
        ],
        "content" => json_encode($agent_data)
    ]
];

$agent_context = stream_context_create($agent_options);
$agent_response = file_get_contents("$api_url_base/create_agent.php", false, $agent_context);
$agent_result = json_decode($agent_response, true);

if (!$agent_result['success']) {
    echo "Error creating agent: " . $agent_result['message'];
    exit(1);
}

$agent_id = $agent_result['data']['agent_id'];
$operator_id = $agent_result['data']['operator_id'];
$operator_password = $agent_result['data']['password'];
echo "Agent created with ID: $agent_id\n";
echo "Operator created - Username: sales_agent_01, Password: $operator_password\n";

// Step 2: Create a user and assign to the agent using agent_id
echo "\nCreating user and assigning to agent...\n";
$user_data = [
    "external_invoice_id" => "INV-2025-001234",
    "plan_name" => "Premium Plan",
    "agent_id" => $agent_id
];

$user_options = [
    "http" => [
        "method" => "POST",
        "header" => [
            "Content-Type: application/json",
            "X-API-Key: $api_key"
        ],
        "content" => json_encode($user_data)
    ]
];

$user_context = stream_context_create($user_options);
$user_response = file_get_contents("$api_url_base/create_user_erp.php", false, $user_context);
$user_result = json_decode($user_response, true);

if ($user_result['success']) {
    echo "User: " . $user_result['data']['username'] . "\n";
    echo "Password: " . $user_result['data']['password'] . "\n";
    echo "Assigned to agent ID: $agent_id\n";
} else {
    echo "Error: " . $user_result['message'];
}
```

## Example Usage - PHP (Create User Only)

```php
$url = "http://your-domain/app/api/create_user_erp.php";
$data = [
    "external_invoice_id" => "INV-2025-001234",
    "plan_name" => "Premium Plan",
    "agent_id" => 42
];

$options = [
    "http" => [
        "method" => "POST",
        "header" => [
            "Content-Type: application/json",
            "X-API-Key: your-secret-api-key-here"
        ],
        "content" => json_encode($data)
    ]
];

$context = stream_context_create($options);
$response = file_get_contents($url, false, $context);
$result = json_decode($response, true);

if ($result['success']) {
    echo "User: " . $result['data']['username'];
    echo "Password: " . $result['data']['password'];
} else {
    echo "Error: " . $result['message'];
}
```

## Error Handling

Common errors and solutions:

| Error | Cause | Solution |
|-------|-------|----------|
| Authentication required | Wrong/missing API key | Verify API_KEY setting and header/parameter |
| External Invoice ID already exists | Duplicate invoice ID | Use unique invoice IDs |
| Plan not found | Invalid plan name | Check plan name in billing_plans table |
| Username generation failed | System error | Retry or check system resources |
| Database connection failed | DB connection issue | Verify database credentials in config |

## Security Notes

- Change the default API key in production
- Use HTTPS only in production
- Consider rate limiting this endpoint
- Log all user creation attempts
- Validate erpinvoiceid format before sending to API
- Store passwords securely on client side
- The QR code contains credentials - handle with care

## Troubleshooting

### API returns 500 error
Check logs in:
- `/var/logs/mikrotik_integration.log`
- Database error logs
- PHP error logs

### User created but can't authenticate
- Verify MD5-Password was set correctly
- Check if user exists in radcheck table
- Verify plan has appropriate attributes

### QR Code not generating
- Check internet connectivity (uses external QR API)
- Try generating manually: `generate_qrcode("your_text")`
