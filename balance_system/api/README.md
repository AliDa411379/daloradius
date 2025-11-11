# Balance System API Documentation

## Overview
RESTful API for managing user balances, payments, and invoices.

## Authentication
All API requests require authentication via:
- **API Key**: Set in header `X-API-KEY` or query parameter `api_key`
- **Session**: If called from authenticated web interface

Default API Key: `your-secret-api-key-here` (⚠️ CHANGE THIS!)

## Base URL
```
http://your-server.com/app/balance_system/api/balance_api.php
```

## Endpoints

### 1. Add Balance (Credit User Account)

**Endpoint**: `?action=add_balance`
**Method**: `POST`
**Description**: Add money to a user's balance

**Request Body**:
```json
{
    "username": "testuser",
    "amount": 100.00,
    "description": "Monthly recharge"
}
```

**Response**:
```json
{
    "success": true,
    "message": "Balance added successfully",
    "data": {
        "balance_before": 50.00,
        "balance_after": 150.00,
        "amount": 100.00,
        "history_id": 123
    },
    "timestamp": "2024-01-15 10:30:00"
}
```

**cURL Example**:
```bash
curl -X POST "http://your-server.com/app/balance_system/api/balance_api.php?action=add_balance" \
  -H "X-API-KEY: your-secret-api-key-here" \
  -H "Content-Type: application/json" \
  -d '{
    "username": "testuser",
    "amount": 100.00,
    "description": "Balance recharge"
  }'
```

---

### 2. Get Balance Information

**Endpoint**: `?action=get_balance`
**Method**: `GET`
**Description**: Retrieve user's current balance and unpaid invoices

**Parameters**:
- `username` (required): Username to query

**Response**:
```json
{
    "success": true,
    "message": "Balance retrieved successfully",
    "data": {
        "username": "testuser",
        "money_balance": 150.00,
        "total_invoices_amount": 30.00,
        "last_balance_update": "2024-01-15 10:30:00",
        "plan_name": "Premium Plan",
        "plan_cost": 30.00,
        "unpaid_invoices_count": 1,
        "unpaid_invoices": [
            {
                "id": 456,
                "date": "2024-01-26",
                "due_date": "2024-02-04",
                "total_due": 30.00,
                "total_paid": 0.00,
                "outstanding": 30.00
            }
        ]
    },
    "timestamp": "2024-01-15 10:35:00"
}
```

**cURL Example**:
```bash
curl "http://your-server.com/app/balance_system/api/balance_api.php?action=get_balance&username=testuser" \
  -H "X-API-KEY: your-secret-api-key-here"
```

---

### 3. Get Balance History

**Endpoint**: `?action=get_history`
**Method**: `GET`
**Description**: Get transaction history for a user

**Parameters**:
- `username` (required): Username
- `limit` (optional): Number of records (default: 50)
- `offset` (optional): Offset for pagination (default: 0)

**Response**:
```json
{
    "success": true,
    "message": "History retrieved successfully",
    "data": {
        "username": "testuser",
        "limit": 50,
        "offset": 0,
        "count": 3,
        "history": [
            {
                "id": 123,
                "transaction_type": "credit",
                "amount": 100.00,
                "balance_before": 50.00,
                "balance_after": 150.00,
                "reference_type": "manual",
                "reference_id": null,
                "description": "Balance recharge",
                "created_by": "operator1",
                "created_at": "2024-01-15 10:30:00"
            },
            {
                "id": 122,
                "transaction_type": "payment",
                "amount": -30.00,
                "balance_before": 80.00,
                "balance_after": 50.00,
                "reference_type": "invoice",
                "reference_id": 456,
                "description": "Payment for invoice #456",
                "created_by": "system",
                "created_at": "2024-01-10 15:20:00"
            }
        ]
    },
    "timestamp": "2024-01-15 10:40:00"
}
```

**cURL Example**:
```bash
curl "http://your-server.com/app/balance_system/api/balance_api.php?action=get_history&username=testuser&limit=10" \
  -H "X-API-KEY: your-secret-api-key-here"
```

---

### 4. Pay Invoice

**Endpoint**: `?action=pay_invoice`
**Method**: `POST`
**Description**: Pay a specific invoice from user balance

**Request Body**:
```json
{
    "invoice_id": 456,
    "amount": 30.00,
    "notes": "Payment via API"
}
```

**Response**:
```json
{
    "success": true,
    "message": "Payment processed successfully",
    "data": {
        "payment_id": 789,
        "invoice_id": 456,
        "payment_amount": 30.00,
        "balance_before": 150.00,
        "balance_after": 120.00,
        "invoice_status": "Paid",
        "total_paid": 30.00,
        "total_due": 30.00,
        "outstanding": 0.00
    },
    "timestamp": "2024-01-15 11:00:00"
}
```

**cURL Example**:
```bash
curl -X POST "http://your-server.com/app/balance_system/api/balance_api.php?action=pay_invoice" \
  -H "X-API-KEY: your-secret-api-key-here" \
  -H "Content-Type: application/json" \
  -d '{
    "invoice_id": 456,
    "amount": 30.00,
    "notes": "Automatic payment"
  }'
```

---

### 5. Get Unpaid Invoices

**Endpoint**: `?action=get_unpaid_invoices`
**Method**: `GET`
**Description**: List all unpaid invoices for a user

**Parameters**:
- `username` (required): Username

**Response**:
```json
{
    "success": true,
    "message": "Unpaid invoices retrieved",
    "data": {
        "username": "testuser",
        "count": 2,
        "invoices": [
            {
                "id": 456,
                "date": "2024-01-26",
                "due_date": "2024-02-04",
                "status_id": 4,
                "total_due": 30.00,
                "total_paid": 0.00,
                "outstanding": 30.00
            },
            {
                "id": 457,
                "date": "2024-02-26",
                "due_date": "2024-03-04",
                "status_id": 4,
                "total_due": 30.00,
                "total_paid": 15.00,
                "outstanding": 15.00
            }
        ]
    },
    "timestamp": "2024-01-15 11:10:00"
}
```

**cURL Example**:
```bash
curl "http://your-server.com/app/balance_system/api/balance_api.php?action=get_unpaid_invoices&username=testuser" \
  -H "X-API-KEY: your-secret-api-key-here"
```

---

### 6. Pay All Invoices

**Endpoint**: `?action=pay_all_invoices`
**Method**: `POST`
**Description**: Automatically pay all unpaid invoices for a user

**Request Body**:
```json
{
    "username": "testuser"
}
```

**Response**:
```json
{
    "success": true,
    "message": "Processed 2 of 2 invoices",
    "data": {
        "total_invoices": 2,
        "paid_count": 2,
        "failed_count": 0,
        "details": [
            {
                "invoice_id": 456,
                "amount": 30.00,
                "success": true,
                "message": "Payment processed successfully"
            },
            {
                "invoice_id": 457,
                "amount": 15.00,
                "success": true,
                "message": "Payment processed successfully"
            }
        ]
    },
    "timestamp": "2024-01-15 11:20:00"
}
```

**cURL Example**:
```bash
curl -X POST "http://your-server.com/app/balance_system/api/balance_api.php?action=pay_all_invoices" \
  -H "X-API-KEY: your-secret-api-key-here" \
  -H "Content-Type: application/json" \
  -d '{
    "username": "testuser"
  }'
```

---

## Error Responses

All errors follow this format:
```json
{
    "success": false,
    "message": "Error description",
    "data": null,
    "timestamp": "2024-01-15 12:00:00"
}
```

**Common HTTP Status Codes**:
- `200` - Success
- `400` - Bad Request (invalid parameters)
- `401` - Unauthorized (invalid API key)
- `404` - Not Found (user/invoice not found)
- `405` - Method Not Allowed (wrong HTTP method)
- `500` - Internal Server Error

---

## Security Notes

1. **Change API Key**: Modify the `API_KEY` constant in `balance_api.php`
2. **Use HTTPS**: Always use HTTPS in production
3. **IP Whitelisting**: Consider restricting API access by IP
4. **Rate Limiting**: Implement rate limiting for production use
5. **Logging**: All transactions are logged with IP addresses

---

## Integration Examples

### PHP
```php
<?php
$api_url = 'http://your-server.com/app/balance_system/api/balance_api.php';
$api_key = 'your-secret-api-key-here';

$data = [
    'username' => 'testuser',
    'amount' => 100.00,
    'description' => 'Balance recharge'
];

$ch = curl_init($api_url . '?action=add_balance');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-API-KEY: ' . $api_key,
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
if ($result['success']) {
    echo "Balance added successfully!";
} else {
    echo "Error: " . $result['message'];
}
?>
```

### Python
```python
import requests
import json

api_url = 'http://your-server.com/app/balance_system/api/balance_api.php'
api_key = 'your-secret-api-key-here'

headers = {
    'X-API-KEY': api_key,
    'Content-Type': 'application/json'
}

data = {
    'username': 'testuser',
    'amount': 100.00,
    'description': 'Balance recharge'
}

response = requests.post(
    f'{api_url}?action=add_balance',
    headers=headers,
    data=json.dumps(data)
)

result = response.json()
if result['success']:
    print("Balance added successfully!")
else:
    print(f"Error: {result['message']}")
```

---

## Support

For issues or questions, contact your system administrator.