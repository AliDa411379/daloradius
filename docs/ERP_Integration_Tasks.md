# DaloRADIUS - ERP Integration API: Full Task Report

**Date:** 2026-03-09
**Team Size:** 3 developers
**Estimated Duration:** 3 Sprints (3 weeks)

---

## Team Roles

| Person | Role | Scope |
|--------|------|-------|
| **Dev 1** | ERP Developer | ERP Dashboard frontend, ERP-side integration |
| **Dev 2** | DaloRADIUS API - Core | User data, online/blocked status, usage endpoints |
| **Dev 3** | DaloRADIUS API - Actions | Block/unblock, renewals, SMS, reports endpoints |

---

## Existing API Endpoints (Ready to Use)

| Endpoint | Method | Description |
|----------|--------|-------------|
| `user_comprehensive_info.php` | GET | Full user profile (personal info, balance, payment history, bundle history, usage) |
| `user_balance.php` | GET | Balance + subscription status |
| `agent_get_active_users.php` | GET | Online users with usage stats |
| `plan_lookups.php` | GET | Available plans/bundles |

---

## New API Endpoints to Build

### Group A: User Status Endpoints (Dev 2)

| # | Endpoint | Method | Description | Priority |
|---|----------|--------|-------------|----------|
| A1 | `erp_online_users.php` | GET | All currently online users (active RADIUS sessions) | HIGH |
| A2 | `erp_disconnected_users.php` | GET | Recently disconnected users (had sessions, now offline) | MEDIUM |
| A3 | `erp_blocked_users.php` | GET | Users in block_user or disabled group | HIGH |
| A4 | `erp_upcoming_blocks.php` | GET | Users whose bundle expires within N days (default 3) | HIGH |
| A5 | `erp_user_detail.php` | GET | Full user info: profile, plan, balance, bundle, online status, speed | HIGH |
| A6 | `erp_user_usage.php` | GET | Usage stats per day or per month (traffic + time from radacct) | HIGH |
| A7 | `erp_user_plan_profile.php` | GET | User's plan details + RADIUS profile (speed/bandwidth limits) | MEDIUM |

### Group B: Action Endpoints (Dev 3)

| # | Endpoint | Method | Description | Priority |
|---|----------|--------|-------------|----------|
| B1 | `erp_block_user.php` | POST | Block a user (add to block_user RADIUS group) | HIGH |
| B2 | `erp_unblock_user.php` | POST | Unblock a user (remove from block_user, restore plan access) | HIGH |
| B3 | `erp_renew_bundle.php` | POST | Renew user's current bundle for full period | HIGH |
| B4 | `erp_renew_temporary.php` | POST | Grant temporary access for N days (default 2) | HIGH |
| B5 | `erp_sms_history.php` | GET | Get SMS notifications sent to a user | MEDIUM |
| B6 | `erp_send_sms.php` | POST | Send SMS notification to user | MEDIUM |
| B7 | `erp_dashboard_summary.php` | GET | Aggregated dashboard stats (online/blocked/expiring counts) | HIGH |

### Group C: ERP Dashboard (Dev 1)

| # | Task | Description | Priority |
|---|------|-------------|----------|
| C1 | Dashboard page | Cards showing: online count, disconnected, blocked, upcoming blocks | HIGH |
| C2 | Online users table | Real-time table with search (user, NAS, duration, traffic) | HIGH |
| C3 | Blocked users table | Table with unblock action button | HIGH |
| C4 | Upcoming blocks table | Users expiring soon, with renew action buttons | HIGH |
| C5 | User detail page | Full profile: info, plan, speed, balance, bundle, usage chart | HIGH |
| C6 | Usage charts | Daily/monthly traffic charts per user | MEDIUM |
| C7 | SMS log page | Table of sent SMS per user | LOW |
| C8 | Action buttons | Block, unblock, renew, renew-2-days, send SMS integration | HIGH |

---

## Sprint Plan

### Sprint 1 - Core Status APIs (Week 1)

| Task | Assignee | Priority | Dependencies | Status |
|------|----------|----------|--------------|--------|
| SQL migration (11_erp_api_tables.sql) | Dev 3 | HIGH | None | [ ] |
| A1: Online users endpoint | Dev 2 | HIGH | None | [ ] |
| A3: Blocked users endpoint | Dev 2 | HIGH | None | [ ] |
| A4: Upcoming blocks endpoint | Dev 2 | HIGH | None | [ ] |
| B7: Dashboard summary endpoint | Dev 3 | HIGH | None | [ ] |
| C1: Dashboard page (mockup + API integration) | Dev 1 | HIGH | A1, A3, A4, B7 | [ ] |

### Sprint 2 - User Detail & Actions (Week 2)

| Task | Assignee | Priority | Dependencies | Status |
|------|----------|----------|--------------|--------|
| A2: Disconnected users endpoint | Dev 2 | MEDIUM | None | [ ] |
| A5: User detail endpoint | Dev 2 | HIGH | None | [ ] |
| A6: User usage (daily/monthly) | Dev 2 | HIGH | None | [ ] |
| A7: User plan + profile (speed) | Dev 2 | MEDIUM | None | [ ] |
| B1: Block user endpoint | Dev 3 | HIGH | None | [ ] |
| B2: Unblock user endpoint | Dev 3 | HIGH | None | [ ] |
| B3: Renew bundle endpoint | Dev 3 | HIGH | None | [ ] |
| B4: Temporary renew (2 days) | Dev 3 | HIGH | SQL migration | [ ] |
| C2: Online users table | Dev 1 | HIGH | A1 | [ ] |
| C3: Blocked users table | Dev 1 | HIGH | A3 | [ ] |
| C4: Upcoming blocks table | Dev 1 | HIGH | A4 | [ ] |

### Sprint 3 - SMS, Reports & Polish (Week 3)

| Task | Assignee | Priority | Dependencies | Status |
|------|----------|----------|--------------|--------|
| A6: Usage charts data refinement | Dev 2 | MEDIUM | A6 | [ ] |
| B5: SMS history endpoint | Dev 3 | MEDIUM | SQL migration | [ ] |
| B6: Send SMS endpoint | Dev 3 | MEDIUM | SQL migration | [ ] |
| C5: User detail page | Dev 1 | HIGH | A5, A6, A7 | [ ] |
| C6: Usage charts | Dev 1 | MEDIUM | A6 | [ ] |
| C7: SMS log page | Dev 1 | LOW | B5 | [ ] |
| C8: Action buttons integration | Dev 1 | HIGH | B1-B4, B6 | [ ] |

---

## API Response Schemas

### A1: GET /erp_online_users.php

```json
{
  "success": true,
  "count": 42,
  "data": [
    {
      "username": "user1",
      "fullname": "John Doe",
      "nas_ip": "10.0.0.1",
      "nas_name": "Router-1",
      "session_start": "2026-03-09 08:00:00",
      "session_duration_minutes": 120,
      "download_mb": 512.5,
      "upload_mb": 45.2,
      "framed_ip": "192.168.1.100",
      "plan_name": "Basic 10Mbps",
      "phone": "+963912345678"
    }
  ]
}
```

### A2: GET /erp_disconnected_users.php?hours=24

```json
{
  "success": true,
  "count": 150,
  "data": [
    {
      "username": "user5",
      "fullname": "Sara Ahmad",
      "last_session_end": "2026-03-09 06:30:00",
      "last_nas": "Router-2",
      "total_sessions_today": 3,
      "plan_name": "Standard 15Mbps",
      "phone": "+963944567890"
    }
  ]
}
```

### A3: GET /erp_blocked_users.php

```json
{
  "success": true,
  "count": 5,
  "data": [
    {
      "username": "user2",
      "fullname": "Jane Doe",
      "phone": "+963998765432",
      "block_reason": "block_user",
      "plan_name": "Premium 20Mbps",
      "bundle_expiry": "2026-03-05",
      "last_session": "2026-03-05 14:30:00",
      "balance": 0.00
    }
  ]
}
```

### A4: GET /erp_upcoming_blocks.php?days=3

```json
{
  "success": true,
  "count": 8,
  "data": [
    {
      "username": "user3",
      "fullname": "Ahmad Ali",
      "phone": "+963933456789",
      "plan_name": "Standard 15Mbps",
      "bundle_expiry": "2026-03-11",
      "days_remaining": 2,
      "balance": 5000.00,
      "can_auto_renew": true
    }
  ]
}
```

### A5: GET /erp_user_detail.php?username=user1

```json
{
  "success": true,
  "data": {
    "personal": {
      "username": "user1",
      "firstname": "John",
      "lastname": "Doe",
      "email": "john@example.com",
      "phone": "+963912345678",
      "address": "Damascus, Syria",
      "city": "Damascus",
      "country": "SY"
    },
    "billing": {
      "plan_name": "Basic 10Mbps",
      "subscription_type": "prepaid",
      "money_balance": 5000.00,
      "bundle_expiry": "2026-03-25",
      "days_remaining": 16,
      "current_bundle_id": 45
    },
    "speed_profile": {
      "download_limit": "10M",
      "upload_limit": "5M",
      "session_timeout": 86400,
      "radius_groups": ["plan-basic-10"]
    },
    "status": {
      "is_online": true,
      "is_blocked": false,
      "current_session_start": "2026-03-09 08:00:00",
      "current_nas": "Router-1",
      "framed_ip": "192.168.1.100"
    },
    "usage_summary": {
      "today_download_mb": 256.5,
      "today_upload_mb": 32.1,
      "month_download_mb": 15360,
      "month_upload_mb": 2048,
      "total_sessions_month": 45
    }
  }
}
```

### A6: GET /erp_user_usage.php?username=user1&period=daily&month=2026-03

```json
{
  "success": true,
  "username": "user1",
  "period": "daily",
  "data": [
    {
      "date": "2026-03-01",
      "download_mb": 1024.5,
      "upload_mb": 128.3,
      "total_mb": 1152.8,
      "session_count": 3,
      "total_time_minutes": 720
    }
  ],
  "summary": {
    "total_download_mb": 15360,
    "total_upload_mb": 2048,
    "total_sessions": 45,
    "total_time_hours": 480
  }
}
```

### A7: GET /erp_user_plan_profile.php?username=user1

```json
{
  "success": true,
  "data": {
    "username": "user1",
    "plan_name": "Basic 10Mbps",
    "plan_cost": 15000,
    "subscription_type": "prepaid",
    "bundle_validity_days": 30,
    "is_bundle": true,
    "radius_profile": {
      "groups": ["plan-basic-10"],
      "attributes": {
        "Mikrotik-Rate-Limit": "10M/5M",
        "Session-Timeout": 86400,
        "WISPr-Bandwidth-Max-Down": 10485760,
        "WISPr-Bandwidth-Max-Up": 5242880
      }
    }
  }
}
```

### B1: POST /erp_block_user.php

```json
// Request
{
  "username": "user1",
  "reason": "non_payment"
}

// Response
{
  "success": true,
  "message": "User user1 has been blocked",
  "blocked_at": "2026-03-09 10:00:00"
}
```

### B2: POST /erp_unblock_user.php

```json
// Request
{
  "username": "user1"
}

// Response
{
  "success": true,
  "message": "User user1 has been unblocked and access restored",
  "plan_name": "Basic 10Mbps",
  "unblocked_at": "2026-03-09 10:05:00"
}
```

### B3: POST /erp_renew_bundle.php

```json
// Request
{
  "username": "user1",
  "created_by": "erp_system"
}

// Response
{
  "success": true,
  "username": "user1",
  "plan_name": "Basic 10Mbps",
  "new_bundle_id": 46,
  "new_expiry": "2026-04-08",
  "amount_deducted": 15000,
  "balance_after": 35000
}
```

### B4: POST /erp_renew_temporary.php

```json
// Request
{
  "username": "user1",
  "days": 2,
  "created_by": "erp_system"
}

// Response
{
  "success": true,
  "username": "user1",
  "temporary_expiry": "2026-03-11 10:00:00",
  "days_granted": 2,
  "access_id": 12
}
```

### B5: GET /erp_sms_history.php?username=user1

```json
{
  "success": true,
  "count": 3,
  "data": [
    {
      "id": 101,
      "phone": "+963912345678",
      "message": "Your bundle expires in 2 days. Please renew.",
      "sms_type": "expiry_warning",
      "status": "sent",
      "sent_at": "2026-03-07 09:00:00"
    }
  ]
}
```

### B6: POST /erp_send_sms.php

```json
// Request
{
  "username": "user1",
  "message": "Your bundle expires tomorrow. Renew now!",
  "sms_type": "expiry_warning"
}

// Response
{
  "success": true,
  "sms_id": 102,
  "phone": "+963912345678",
  "status": "pending",
  "queued_at": "2026-03-09 10:00:00"
}
```

### B7: GET /erp_dashboard_summary.php

```json
{
  "success": true,
  "timestamp": "2026-03-09T10:00:00Z",
  "online_users": 42,
  "disconnected_users": 150,
  "blocked_users": 5,
  "upcoming_blocks_3days": 8,
  "upcoming_blocks_7days": 15,
  "total_users": 200,
  "active_bundles": 180,
  "expired_bundles": 15,
  "total_traffic_today_gb": 256.5
}
```

---

## SQL Migration File

File: `contrib/db/erp_integration/11_erp_api_tables.sql`

```sql
-- SMS notifications tracking
CREATE TABLE IF NOT EXISTS sms_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(64) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    sms_type ENUM('expiry_warning','payment_reminder','block_notice','custom') DEFAULT 'custom',
    status ENUM('pending','sent','failed') DEFAULT 'pending',
    sent_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Temporary access grants (for 2-day renewals etc.)
CREATE TABLE IF NOT EXISTS temporary_access (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(64) NOT NULL,
    granted_by VARCHAR(64),
    days_granted INT NOT NULL DEFAULT 2,
    start_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    expiry_date DATETIME NOT NULL,
    status ENUM('active','expired','revoked') DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_expiry (expiry_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## Key SQL Queries Reference

### Online Users (A1)

```sql
SELECT ra.username, ui.firstname, ui.lastname, ra.nasipaddress,
       n.shortname AS nas_name, ra.acctstarttime, ra.acctsessiontime,
       ra.acctinputoctets, ra.acctoutputoctets, ra.framedipaddress,
       ub.planName, ui.mobilephone
FROM radacct ra
LEFT JOIN userinfo ui ON ra.username = ui.username
LEFT JOIN nas n ON ra.nasipaddress = n.nasname
LEFT JOIN userbillinfo ub ON ra.username = ub.username
WHERE ra.acctstoptime IS NULL OR ra.acctstoptime = '0000-00-00 00:00:00';
```

### Blocked Users (A3)

```sql
SELECT rug.username, ui.firstname, ui.lastname, ui.mobilephone,
       ub.planName, ub.bundle_expiry_date, ub.money_balance,
       MAX(ra.acctstoptime) AS last_session
FROM radusergroup rug
LEFT JOIN userinfo ui ON rug.username = ui.username
LEFT JOIN userbillinfo ub ON rug.username = ub.username
LEFT JOIN radacct ra ON rug.username = ra.username
WHERE rug.groupname IN ('block_user', 'daloRADIUS-Disabled-Users')
GROUP BY rug.username;
```

### Upcoming Blocks (A4)

```sql
SELECT ub.username, ui.firstname, ui.lastname, ui.mobilephone,
       ub.planName, ub.bundle_expiry_date, ub.money_balance,
       DATEDIFF(ub.bundle_expiry_date, NOW()) AS days_remaining,
       bp.planCost
FROM userbillinfo ub
LEFT JOIN userinfo ui ON ub.username = ui.username
LEFT JOIN billing_plans bp ON ub.planName = bp.planName
WHERE ub.subscription_type_id = 2
  AND ub.bundle_expiry_date IS NOT NULL
  AND ub.bundle_expiry_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL ? DAY)
ORDER BY ub.bundle_expiry_date ASC;
```

### User Usage Per Day (A6)

```sql
SELECT DATE(acctstarttime) AS usage_date,
       SUM(acctinputoctets) / 1048576 AS download_mb,
       SUM(acctoutputoctets) / 1048576 AS upload_mb,
       (SUM(acctinputoctets) + SUM(acctoutputoctets)) / 1048576 AS total_mb,
       COUNT(*) AS session_count,
       SUM(acctsessiontime) / 60 AS total_minutes
FROM radacct
WHERE username = ?
  AND acctstarttime >= ? AND acctstarttime < ?
GROUP BY DATE(acctstarttime)
ORDER BY usage_date;
```

### User Usage Per Month (A6)

```sql
SELECT DATE_FORMAT(acctstarttime, '%Y-%m') AS usage_month,
       SUM(acctinputoctets) / 1048576 AS download_mb,
       SUM(acctoutputoctets) / 1048576 AS upload_mb,
       (SUM(acctinputoctets) + SUM(acctoutputoctets)) / 1048576 AS total_mb,
       COUNT(*) AS session_count,
       SUM(acctsessiontime) / 3600 AS total_hours
FROM radacct
WHERE username = ?
GROUP BY DATE_FORMAT(acctstarttime, '%Y-%m')
ORDER BY usage_month DESC
LIMIT 12;
```

### User Speed Profile (A7)

```sql
SELECT rgr.attribute, rgr.value
FROM radusergroup rug
JOIN radgroupreply rgr ON rug.groupname = rgr.groupname
WHERE rug.username = ?
  AND rgr.attribute IN (
    'Mikrotik-Rate-Limit',
    'WISPr-Bandwidth-Max-Down',
    'WISPr-Bandwidth-Max-Up',
    'Session-Timeout'
  );
```

### Dashboard Summary (B7)

```sql
-- Online count
SELECT COUNT(DISTINCT username) FROM radacct
WHERE acctstoptime IS NULL OR acctstoptime = '0000-00-00 00:00:00';

-- Blocked count
SELECT COUNT(DISTINCT username) FROM radusergroup
WHERE groupname IN ('block_user', 'daloRADIUS-Disabled-Users');

-- Upcoming blocks (3 days)
SELECT COUNT(*) FROM userbillinfo
WHERE subscription_type_id = 2
  AND bundle_expiry_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY);

-- Total users
SELECT COUNT(*) FROM radcheck WHERE attribute = 'MD5-Password';

-- Today's traffic
SELECT SUM(acctinputoctets + acctoutputoctets) / 1073741824 AS total_gb
FROM radacct
WHERE DATE(acctstarttime) = CURDATE();
```

---

## Configuration Updates

Add to `app/users/api/config.php` protected endpoints array:

```php
'erp_block_user.php',
'erp_unblock_user.php',
'erp_renew_bundle.php',
'erp_renew_temporary.php',
'erp_send_sms.php',
'erp_dashboard_summary.php',
'erp_online_users.php',
'erp_disconnected_users.php',
'erp_blocked_users.php',
'erp_upcoming_blocks.php',
'erp_user_detail.php',
'erp_user_usage.php',
'erp_user_plan_profile.php',
'erp_sms_history.php'
```

---

## Summary

| Category | Count | Status |
|----------|-------|--------|
| Existing endpoints (reuse) | 4 | Ready |
| New Status APIs (Dev 2) | 7 | To build |
| New Action APIs (Dev 3) | 7 | To build |
| ERP Dashboard pages (Dev 1) | 8 | To build |
| SQL Migration files | 1 | To create |
| **Total new work** | **14 API + 8 pages** | **~3 weeks** |
