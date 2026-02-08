# Get Active Users by Agent API

**Endpoint:** `/app/users/api/agent_get_active_users.php`  
**Methods:** GET, POST  
**Authentication:** Required (X-API-Key header)

---

## Request

### GET
```
/app/users/api/agent_get_active_users.php?agent_id=1&status=active&limit=50
```

### POST
```json
{
  "agent_id": 1,
  "status": "active",
  "subscription_type": "all",
  "limit": 50,
  "offset": 0
}
```

### Parameters

| Parameter | Required | Default | Description |
|-----------|----------|---------|-------------|
| `agent_id` | Yes | - | Agent ID |
| `status` | No | `active` | Filter: `active`, `all` |
| `subscription_type` | No | `all` | Filter: `monthly`, `prepaid`, `all` |
| `limit` | No | 50 | Max results (max: 100) |
| `offset` | No | 0 | Pagination offset |

---

## Response

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
      "is_online": true,
      "online_time_str": "02:15:30",
      "online_traffic_mb": 150.5,
      "connected_since": "2025-12-04 10:00:00",
      "last_payment": "2025-11-20",
      "last_payment_amount": 30.00
    }
  ]
}
```

---

## Response Fields

### All Users
- `username` - Username
- `full_name` - Full name
- `subscription_type` - `monthly` or `prepaid`
- `plan_name` - Current plan
- `balance` - Money balance
- `status` - User status
- `creation_date` - Account creation date

### Online Status (NEW)
- `is_online` - Currently connected (boolean)
- `online_time_str` - Session duration (HH:MM:SS)
- `online_traffic_mb` - Session traffic (MB)
- `connected_since` - Session start time (null if offline)

### Monthly Users
- `last_payment` - Last payment date
- `last_payment_amount` - Last payment amount

### Prepaid Users
- `active_bundle` - Active bundle name
- `bundle_expiry` - Bundle expiration date

---

## Examples

### cURL
```bash
curl "http://172.30.18.200:8000/app/users/api/agent_get_active_users.php?agent_id=1" \
  -H "X-API-Key: your-api-key"
```

### JavaScript
```javascript
const response = await fetch('/app/users/api/agent_get_active_users.php', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-API-Key': 'your-api-key'
  },
  body: JSON.stringify({ agent_id: 1, limit: 50 })
});

const data = await response.json();
const onlineUsers = data.users.filter(u => u.is_online);
console.log(`${onlineUsers.length} users online`);
```

---

## Error Responses

```json
{
  "success": false,
  "error": "agent_id is required"
}
```

---

## Notes

- Online status checked via `radacct` table (sessions without stop time)
- Traffic/time from current session only
- Offline users have `is_online: false` and zero usage
- Returns users with any payment via the agent
