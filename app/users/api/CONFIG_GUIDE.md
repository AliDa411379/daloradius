# API Configuration Guide

## Setup Instructions

### 1. Configure API Keys

Edit `config.php` and set your API keys:

```php
define('API_KEYS', [
    'agent_api_production' => 'YOUR_SECURE_API_KEY_HERE',
    'admin_api_production' => 'YOUR_ADMIN_API_KEY_HERE',
    'erp_api_production' => 'YOUR_ERP_API_KEY_HERE',
]);
```

**Generate secure keys**:
```bash
openssl rand -hex 32
```

### 2. Enable Authentication

In `config.php`:
```php
define('API_AUTH_ENABLED', true);  // MUST be true in production
```

### 3. Configure Security Settings

**IP Whitelist** (optional):
```php
define('API_ALLOWED_IPS', [
    '192.168.1.100',    // Specific IP
    '10.0.0.0/8',       // CIDR range
]);
```

**CORS Settings**:
```php
define('API_CORS_ALLOWED_ORIGINS', [
    'https://your-frontend-domain.com',
    'https://agent-portal.com',
]);
```

**Rate Limiting**:
```php
define('API_RATE_LIMIT_ENABLED', true);
define('API_RATE_LIMIT_PER_MINUTE', 60);  // Requests per minute per IP
```

### 4. Update API Files

Each protected API file should include:

```php
<?php
// At the very top, before any other code
require_once('auth.php');

// Use helper functions instead of custom ones:
// Replace sendError() with apiSendError()
// Replace sendSuccess() with apiSendSuccess()
```

### 5. Create Required Directories

```bash
mkdir -p var/logs
mkdir -p var/cache
chmod 755 var/logs
chmod 755 var/cache
```

## Using the APIs

### With API Key (Header - Recommended):
```bash
curl -X POST https://your-server/app/users/api/agent_topup_balance.php \
  -H "Content-Type: application/json" \
  -H "X-API-Key: your_api_key_here" \
  -d '{"agent_id":1,"username":"testuser","amount":100}'
```

### With API Key (Query Parameter):
```bash
curl "https://your-server/app/users/api/user_balance.php?username=testuser&api_key=your_api_key_here"
```

### With API Key (POST Body):
```json
{
  "api_key": "your_api_key_here",
  "agent_id": 1,
  "username": "testuser",
  "amount": 100
}
```

## Security Best Practices

### Production Checklist:
- [x] Set `API_AUTH_ENABLED = true`
- [x] Generate strong API keys (32+ characters)
- [x] Use HTTPS only
- [x] Configure IP whitelist if possible
- [x] Enable rate limiting
- [x] Set `API_DEBUG_MODE = false`
- [x] Set `API_JSON_PRETTY_PRINT = false`
- [x] Monitor logs regularly
- [x] Rotate API keys periodically
- [x] Keep config.php outside web root (or protect with .htaccess)

### .htaccess Protection for config.php:
```apache
<Files "config.php">
    Order Allow,Deny
    Deny from all
</Files>
```

## Error Responses

All errors follow this format:
```json
{
  "success": false,
  "error": "Error message here",
  "error_code": "ERROR_CODE_HERE"
}
```

### Common Error Codes:
- `API_KEY_MISSING` - No API key provided
- `INVALID_API_KEY` - API key is invalid
- `IP_NOT_ALLOWED` - IP address not whitelisted
- `RATE_LIMIT_EXCEEDED` - Too many requests
- `INVALID_JSON_INPUT` - Malformed JSON
- `MISSING_REQUIRED_FIELD` - Required parameter missing

## Logging

### Access Logs:
Location: `var/logs/api_access.log`

Format:
```
[2025-11-24 12:45:00] POST agent_topup_balance.php from 192.168.1.100 (Key: agent_ap...)
```

### Error Logs:
Location: `var/logs/api_errors.log`

Format:
```
[2025-11-24 12:45:30] ERROR in agent_topup_balance.php: Invalid amount (Code: 400)
```

## Rate Limiting

Cache files stored in: `var/cache/rate_limit_*.txt`

These files are automatically cleaned up after 1 minute.

## Testing

### Disable Authentication for Testing:
```php
define('API_AUTH_ENABLED', false);  // Testing only!
```

### Test API Keys:
```php
'agent_api_test' => 'test_key_12345',  // Use for local testing
```

**IMPORTANT**: Re-enable authentication and use production keys before deploying!

## Troubleshooting

### "API key required" error:
- Check if `X-API-Key` header is sent
- Verify API key is correct
- Check if `API_AUTH_ENABLED` is true

### "IP not whitelisted" error:
- Add your IP to `API_ALLOWED_IPS`
- Or set `API_ALLOWED_IPS = []` to allow all IPs

### "Rate limit exceeded" error:
- Wait 60 seconds
- Increase `API_RATE_LIMIT_PER_MINUTE`
- Or disable: `API_RATE_LIMIT_ENABLED = false`

### CORS errors in browser:
- Add your frontend domain to `API_CORS_ALLOWED_ORIGINS`
- Or use `'*'` to allow all origins (not recommended for production)
