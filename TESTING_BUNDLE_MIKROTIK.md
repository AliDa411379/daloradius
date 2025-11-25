# Bundle Purchase Testing Checklist

## Prerequisites
- [ ] Ensure bundles are created in billing_plans table with `is_bundle = 1`
- [ ] Verify mikrotik_integration.log file is writable: `/var/www/daloradius/var/logs/mikrotik_integration.log`
- [ ] Check that `radreply` table exists and is accessible

---

## Test Scenario 1: Frontend Bundle Purchase

### Setup
1. Login to daloRADIUS operator panel
2. Navigate to: **Operators → Bundle Purchase** (`bundle-purchase.php`)
3. Select test user with sufficient balance
4. Select a bundle (e.g., 1GB / 60 minutes)

### Expected Behavior
- [ ] Bundle purchase form displays
- [ ] User balance is visible
- [ ] Bundle selection works
- [ ] Purchase succeeds without errors

### Verification Steps

#### 1. Check Bundle Record
```sql
SELECT * FROM user_bundles 
WHERE username = 'testuser' 
ORDER BY created_at DESC 
LIMIT 1;
```
**Expected:** Status = 'active', bundle_id matches selected bundle

#### 2. Check RADIUS Groups
```sql
SELECT * FROM radusergroup 
WHERE username = 'testuser';
```
**Expected:** User assigned to plan groups, NOT in 'block_user'

#### 3. Check Mikrotik Attributes ✅ CRITICAL
```sql
SELECT attribute, op, value FROM radreply 
WHERE username = 'testuser' 
AND attribute IN ('Mikrotik-Total-Limit', 'Mikrotik-Total-Limit-Gigawords', 'Session-Timeout')
ORDER BY attribute;
```

**Expected for 1024 MB / 60 min bundle:**
```
Mikrotik-Total-Limit           = 1073741824  (1024 * 1048576)
Mikrotik-Total-Limit-Gigawords = 0
Session-Timeout                = 3600         (60 * 60)
```

#### 4. Check Logs
```bash
tail -20 /var/www/daloradius/var/logs/mikrotik_integration.log
```

**Expected:**
```
[YYYY-MM-DD HH:MM:SS] [INFO] [MIKROTIK_LIB] Setting Mikrotik attributes for testuser - Plan: Bundle1GB, Traffic: 1024MB, Time: 60min
[YYYY-MM-DD HH:MM:SS] [INFO] [MIKROTIK_LIB] Updated Mikrotik-Total-Limit-Gigawords=0 for testuser
[YYYY-MM-DD HH:MM:SS] [INFO] [MIKROTIK_LIB] Updated Mikrotik-Total-Limit=1073741824 for testuser
[YYYY-MM-DD HH:MM:SS] [INFO] [MIKROTIK_LIB] Updated Session-Timeout=3600 for testuser
[YYYY-MM-DD HH:MM:SS] [INFO] [MIKROTIK_LIB] Successfully set Mikrotik attributes for testuser
```

---

## Test Scenario 2: API Bundle Purchase

### Setup
Use curl or Postman to call the API:

```bash
curl -X POST http://your-server/app/users/api/agent_purchase_bundle.php \
  -H "Content-Type: application/json" \
  -d '{
    "agent_id": 1,
    "username": "testuser2",
    "plan_id": 5,
    "payment_method": "cash"
  }'
```

### Expected Response
```json
{
  "success": true,
  "bundle_id": 123,
  "plan_name": "Bundle1GB",
  "amount_charged": 10.00,
  "new_balance": 90.00,
  "expiry_date": "2025-12-25 20:52:00",
  "agent_name": "Test Agent",
  "agent_payment_id": 456,
  "radius_access_granted": true,
  "message": "Bundle purchased and activated successfully"
}
```

### Verification Steps (Same as Scenario 1)

#### 1. Check Bundle Record
```sql
SELECT * FROM user_bundles 
WHERE username = 'testuser2' 
ORDER BY created_at DESC 
LIMIT 1;
```

#### 2. Check RADIUS Groups
```sql
SELECT * FROM radusergroup 
WHERE username = 'testuser2';
```

#### 3. Check Mikrotik Attributes ✅ CRITICAL
```sql
SELECT attribute, op, value FROM radreply 
WHERE username = 'testuser2' 
AND attribute IN ('Mikrotik-Total-Limit', 'Mikrotik-Total-Limit-Gigawords', 'Session-Timeout')
ORDER BY attribute;
```

**Must match the SAME conversion as frontend!**

#### 4. Check Agent Payment Record
```sql
SELECT * FROM agent_payments 
WHERE reference_type = 'bundle' 
AND username = 'testuser2'
ORDER BY created_at DESC 
LIMIT 1;
```

#### 5. Check Logs (Same as Scenario 1)

---

## Test Scenario 3: Expired Bundle

### Setup
1. Manually update bundle expiry to past date:
```sql
UPDATE user_bundles 
SET expiry_date = DATE_SUB(NOW(), INTERVAL 1 DAY) 
WHERE id = [bundle_id];
```

2. Run bundle expiry check (if cron exists):
```bash
php /var/www/daloradius/contrib/scripts/check_expired_bundles.php
```

### Expected Behavior
- [ ] Bundle status changes to 'expired'
- [ ] User added to 'block_user' group
- [ ] Mikrotik attributes can be removed (optional, based on your policy)

---

## Attribute Conversion Reference

### Traffic Conversion Formula
```
Total Bytes = Traffic_MB × 1,048,576
Gigawords = floor(Total Bytes / 4,294,967,296)
Bytes = Total Bytes % 4,294,967,296
```

### Examples:
| Traffic (MB) | Mikrotik-Total-Limit | Mikrotik-Total-Limit-Gigawords |
|--------------|----------------------|--------------------------------|
| 100 MB       | 104,857,600          | 0                              |
| 1024 MB      | 1,073,741,824        | 0                              |
| 5120 MB      | 1,073,741,824        | 1                              |
| 10240 MB     | 2,147,483,648        | 2                              |

### Time Conversion Formula
```
Seconds = Minutes × 60
```

### Examples:
| Time (Minutes) | Session-Timeout |
|----------------|-----------------|
| 30 min         | 1,800           |
| 60 min         | 3,600           |
| 1440 min (24h) | 86,400          |

---

## Troubleshooting

### Issue: Attributes Not Set

**Check 1:** Is mikrotik_integration_functions.php loaded?
```bash
grep -r "mikrotik_integration_functions.php" /var/www/daloradius/app/common/library/RadiusAccessManager.php
```
**Expected:** Should find the require_once statement

**Check 2:** Are there errors in logs?
```bash
tail -50 /var/www/daloradius/var/logs/mikrotik_integration.log | grep ERROR
```

**Check 3:** Does the radreply table exist?
```sql
SHOW TABLES LIKE 'radreply';
```

**Check 4:** Does user exist in userbillinfo?
```sql
SELECT username, planName, traffic_balance, timebank_balance 
FROM userbillinfo 
WHERE username = 'testuser';
```

### Issue: Wrong Attribute Values

**Check:** Verify bundle plan settings
```sql
SELECT planName, planType, planTimeBank, planTrafficTotal 
FROM billing_plans 
WHERE id = [bundle_id];
```

**Check:** User balances
```sql
SELECT username, timebank_balance, traffic_balance 
FROM userbillinfo 
WHERE username = 'testuser';
```

### Issue: API Returns Error

**Check:** PHP error log
```bash
tail -f /var/log/php-fpm/error.log
# or
tail -f /var/log/apache2/error.log
```

**Check:** Request format
```bash
# Should be valid JSON
echo '{
  "agent_id": 1,
  "username": "testuser",
  "plan_id": 5
}' | jq .
```

---

## Success Criteria

✅ **Frontend Purchase:**
- Bundle purchased successfully
- Balance deducted
- Mikrotik attributes set correctly
- User can authenticate via RADIUS

✅ **API Purchase:**
- JSON response successful
- Same attribute values as frontend
- Agent payment recorded
- Mikrotik attributes identical to frontend conversion

✅ **Consistency:**
- Both methods use SAME conversion logic
- Attributes match expected formulas
- Logs show successful operations

---

*Testing Completed: ___________*  
*Tested By: ___________*  
*Result: [ ] PASS / [ ] FAIL*
