## Mikrotik Attribute Conversion Reference

### Quick Reference Card

---

## Traffic Conversion (MB → Mikrotik Attributes)

### Formula
```php
$total_bytes = $traffic_mb * 1048576;  // 1 MB = 1,048,576 bytes
$gigawords = floor($total_bytes / 4294967296);  // 1 Gigaword = 4,294,967,296 bytes
$bytes = $total_bytes % 4294967296;
```

### RADIUS Attributes Set
- **Mikrotik-Total-Limit-Gigawords** = $gigawords (integer)
- **Mikrotik-Total-Limit** = $bytes (integer)

### Conversion Table

| Bundle Traffic | Total Bytes     | Gigawords | Bytes Remainder   | Notes                    |
|----------------|-----------------|-----------|-------------------|--------------------------|
| 10 MB          | 10,485,760      | 0         | 10,485,760        | Small bundle             |
| 100 MB         | 104,857,600     | 0         | 104,857,600       | Basic bundle             |
| 500 MB         | 524,288,000     | 0         | 524,288,000       | Standard bundle          |
| 1 GB (1024 MB) | 1,073,741,824   | 0         | 1,073,741,824     | Popular bundle           |
| 2 GB (2048 MB) | 2,147,483,648   | 0         | 2,147,483,648     | Medium bundle            |
| 3 GB (3072 MB) | 3,221,225,472   | 0         | 3,221,225,472     | Large bundle             |
| 4 GB (4096 MB) | 4,294,967,296   | 1         | 0                 | **Exactly 1 Gigaword!**  |
| 5 GB (5120 MB) | 5,368,709,120   | 1         | 1,073,741,824     | Ultra bundle             |
| 10 GB (10240 MB)| 10,737,418,240  | 2         | 2,147,483,648     | Premium bundle           |
| 20 GB (20480 MB)| 21,474,836,480  | 5         | 0                 | Enterprise bundle        |
| 50 GB (51200 MB)| 53,687,091,200  | 12        | 2,147,483,648     | Unlimited-like           |
| 100 GB (102400 MB)| 107,374,182,400 | 25        | 0                 | Very large bundle        |

### Understanding Gigawords
- **1 Gigaword** = 4,294,967,296 bytes = **4 GB**
- Used because 32-bit integers max at ~4.3 billion
- Mikrotik uses TWO attributes to represent large traffic values

### Example Calculation (5 GB Bundle)
```
Traffic: 5120 MB
Total Bytes: 5120 × 1,048,576 = 5,368,709,120 bytes

Gigawords: floor(5,368,709,120 / 4,294,967,296) = floor(1.25) = 1
Bytes: 5,368,709,120 % 4,294,967,296 = 1,073,741,824

Result:
  Mikrotik-Total-Limit-Gigawords = 1
  Mikrotik-Total-Limit = 1,073,741,824
```

---

## Time Conversion (Minutes → Session-Timeout)

### Formula
```php
$seconds = $minutes * 60;
```

### RADIUS Attribute Set
- **Session-Timeout** = $seconds (integer)

### Conversion Table

| Bundle Duration        | Minutes | Session-Timeout | Use Case              |
|------------------------|---------|-----------------|------------------------|
| 30 minutes             | 30      | 1,800           | Quick session          |
| 1 hour                 | 60      | 3,600           | Standard bundle        |
| 2 hours                | 120     | 7,200           | Extended session       |
| 6 hours                | 360     | 21,600          | Day pass               |
| 12 hours               | 720     | 43,200          | Half-day bundle        |
| 24 hours (1 day)       | 1,440   | 86,400          | **Daily bundle**       |
| 48 hours (2 days)      | 2,880   | 172,800         | Weekend pass           |
| 72 hours (3 days)      | 4,320   | 259,200         | 3-day bundle           |
| 7 days (1 week)        | 10,080  | 604,800         | Weekly bundle          |
| 30 days (1 month)      | 43,200  | 2,592,000       | **Monthly bundle**     |
| 90 days (3 months)     | 129,600 | 7,776,000       | Quarterly              |
| 365 days (1 year)      | 525,600 | 31,536,000      | Annual bundle          |

### Example Calculation (7 Days Bundle)
```
Time: 7 days = 7 × 24 × 60 = 10,080 minutes
Session-Timeout: 10,080 × 60 = 604,800 seconds

Result:
  Session-Timeout = 604,800
```

---

## Combined Example: Typical Bundle

### Bundle Definition
- **Name:** "5GB Monthly Bundle"
- **Traffic:** 5120 MB (5 GB)
- **Validity:** 30 days
- **Price:** $15.00

### Attributes Generated

**From Traffic (5120 MB):**
```
Mikrotik-Total-Limit-Gigawords = 1
Mikrotik-Total-Limit = 1,073,741,824
```

**From Time (30 days = 43,200 minutes):**
```
Session-Timeout = 2,592,000
```

### SQL Check After Purchase
```sql
SELECT username, attribute, value 
FROM radreply 
WHERE username = 'johndoe' 
AND attribute IN ('Mikrotik-Total-Limit-Gigawords', 'Mikrotik-Total-Limit', 'Session-Timeout');
```

**Expected Result:**
```
johndoe | Mikrotik-Total-Limit-Gigawords | 1
johndoe | Mikrotik-Total-Limit           | 1073741824
johndoe | Session-Timeout                | 2592000
```

---

## Code Location

### Conversion Functions
**File:** `contrib/scripts/mikrotik_integration_functions.php`

```php
// Lines 37-55: mikrotik_convert_traffic()
// Lines 62-69: mikrotik_convert_time()
```

### Integration Point
**File:** `app/common/library/RadiusAccessManager.php`

```php
// Lines 364-372: Traffic attribute setting
// Lines 375-380: Time attribute setting
```

---

## Constants Defined

```php
define('BYTES_PER_GIGAWORD', 4294967296);  // 4 GB
define('BYTES_PER_MB', 1048576);           // 1 MB
define('SECONDS_PER_MINUTE', 60);          // 60 seconds
```

---

## Important Notes

⚠️ **Precision:**
- All conversions use integers
- Minimum value is 1 (except unlimited = 0)
- No rounding errors

⚠️ **Consistency:**
- Same formulas used everywhere
- API and Frontend produce identical results
- Logged for audit trail

⚠️ **Mikrotik Behavior:**
- Total usage = (Gigawords × 4GB) + Bytes
- When limit reached, user is blocked
- Session-Timeout disconnects user after time expires

---

## Testing Quick Commands

### Calculate manually for verification:
```bash
# Traffic conversion (example: 1024 MB)
echo "scale=0; 1024 * 1048576" | bc
# Result: 1073741824

# Gigawords (example: 5120 MB)
echo "scale=0; (5120 * 1048576) / 4294967296" | bc
# Result: 1

# Bytes remainder
echo "scale=0; (5120 * 1048576) % 4294967296" | bc
# Result: 1073741824

# Time conversion (example: 1440 minutes = 1 day)
echo "scale=0; 1440 * 60" | bc
# Result: 86400
```

---

*Last Updated: 2025-11-25*
