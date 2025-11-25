# Bundle Purchase Mikrotik Attribute Integration - Summary

## Problem Statement
When purchasing a bundle (either via API or frontend), the system was not sending Mikrotik RADIUS attributes with proper conversion. The conversion functions existed in `contrib/scripts/mikrotik_integration_functions.php` but were not being used during bundle purchases.

## Solution Overview
Integrated Mikrotik attribute management into the `RadiusAccessManager` class so that bundle purchases automatically set RADIUS attributes using the **same conversion logic** for both API and frontend purchases.

---

## Changes Made

### File: `app/common/library/RadiusAccessManager.php`

#### 1. **Added Mikrotik Integration Import**
```php
// Include Mikrotik integration functions for attribute conversion
require_once(__DIR__ . '/../../../contrib/scripts/mikrotik_integration_functions.php');
```

#### 2. **Added Table References**
```php
private $table_radreply = 'radreply';
private $table_billing_plans = 'billing_plans';
private $table_userbillinfo = 'userbillinfo';
```

#### 3. **Updated `grantAccess()` Method**
Now automatically sets Mikrotik attributes when granting access:
```php
// Set Mikrotik attributes based on plan
$attributesSet = $this->setMikrotikAttributesForPlan($username, $planName);
```

#### 4. **Added `setMikrotikAttributesForPlan()` Method** (Lines 309-394)
This private method:
- Gets plan details (planType, planTimeBank, planTrafficTotal)
- Gets user's current balances (timebank_balance, traffic_balance)
- Uses **mikrotik_convert_traffic()** to convert MB → bytes + gigawords
- Uses **mikrotik_convert_time()** to convert minutes → seconds
- Sets RADIUS attributes:
  - `Mikrot-Total-Limit-Gigawords`
  - `Mikrotik-Total-Limit`
  - `Session-Timeout`
- Logs all operations for debugging

#### 5. **Added `removeMikrotikAttributes()` Method** (Lines 396-404)
Public method to remove Mikrotik attributes when needed.

---

## How It Works Now

### **Bundle Purchase Flow (Both API and Frontend)**

1. **User selects bundle** → BundleManager processes purchase
2. **Balance deducted** → BalanceManager handles transaction
3. **RadiusAccessManager.grantAccess() is called**:
   - Assigns user to RADIUS groups
   - **NEW:** Calls `setMikrotikAttributesForPlan()`
   - Converts traffic and time using standard functions
   - Sets proper Mikrotik RADIUS attributes

### **Attribute Conversion (Consistent across all sources)**

Both API and frontend now use these **EXACT SAME FUNCTIONS** from `mikrotik_integration_functions.php`:

**Traffic Conversion:**
```php
function mikrotik_convert_traffic($traffic_mb) {
    $total_bytes = $traffic_mb * BYTES_PER_MB;
    $gigawords = floor($total_bytes / BYTES_PER_GIGAWORD);
    $remaining_bytes = $total_bytes % BYTES_PER_GIGAWORD;
    
    return [
        'gigawords' => (int)$gigawords,
        'bytes' => (int)$remaining_bytes
    ];
}
```

**Time Conversion:**
```php
function mikrotik_convert_time($time_minutes) {
    $seconds = $time_minutes * SECONDS_PER_MINUTE;
    return max(1, (int)$seconds);
}
```

---

## Where Bundle Purchase Happens

### 1. **Frontend Purchase** (`app/operators/bundle-purchase.php`)
- Operator selects user and bundle
- Calls `BundleManager->purchaseBundle()`
- Then calls `RadiusAccessManager->grantAccess()`
- **Attributes are now automatically set! ✓**

### 2. **API Purchase** (`app/users/api/agent_purchase_bundle.php`)
- Agent sends JSON request
- Calls `BundleManager->purchaseBundle()`
- Then calls `RadiusAccessManager->grantAccess()`
- **Attributes are now automatically set! ✓**

---

## Testing & Verification

### Check Attributes After Bundle Purchase
```sql
-- View user's RADIUS attributes
SELECT * FROM radreply 
WHERE username = 'testuser' 
AND attribute IN ('Mikrotik-Total-Limit', 'Mikrotik-Total-Limit-Gigawords', 'Session-Timeout');
```

### Expected Results
For a bundle with **1024 MB traffic** and **60 minutes**:

| attribute | op | value |
|-----------|-----|-------|
| Mikrotik-Total-Limit | = | 1073741824 |
| Mikrotik-Total-Limit-Gigawords | = | 0 |
| Session-Timeout | = | 3600 |

### Check Logs
```bash
tail -f /var/www/daloradius/var/logs/mikrotik_integration.log
```

You should see:
```
[2025-11-25 20:52:00] [INFO] [MIKROTIK_LIB] Setting Mikrotik attributes for testuser - Plan: Bundle1GB, Traffic: 1024MB, Time: 60min
[2025-11-25 20:52:00] [INFO] [MIKROTIK_LIB] Updated Mikrotik-Total-Limit-Gigawords=0 for testuser
[2025-11-25 20:52:00] [INFO] [MIKROTIK_LIB] Updated Mikrotik-Total-Limit=1073741824 for testuser
[2025-11-25 20:52:00] [INFO] [MIKROTIK_LIB] Updated Session-Timeout=3600 for testuser
[2025-11-25 20:52:00] [INFO] [MIKROTIK_LIB] Successfully set Mikrotik attributes for testuser
```

---

## Benefits

✅ **Consistency**: Both API and frontend use identical conversion logic  
✅ **Automatic**: No manual intervention needed after bundle purchase  
✅ **Centralized**: All Mikrotik logic in one place (mikrotik_integration_functions.php)  
✅ **Logged**: Full audit trail of all attribute changes  
✅ **Maintainable**: Easy to update conversion logic in one location  

---

## Future Enhancements

Consider adding:
1. Automatic attribute refresh when bundle expires
2. Support for bundle renewals
3. Real-time balance updates via RADIUS CoA (Change of Authorization)
4. Bandwidth throttling based on usage patterns

---

## Related Files

- **`contrib/scripts/mikrotik_integration_functions.php`** - Conversion functions
- **`app/common/library/RadiusAccessManager.php`** - RADIUS access control *(UPDATED)*
- **`app/common/library/BundleManager.php`** - Bundle purchase logic
- **`app/operators/bundle-purchase.php`** - Frontend bundle purchase
- **`app/users/api/agent_purchase_bundle.php`** - API bundle purchase

---

*Last Updated: 2025-11-25*
