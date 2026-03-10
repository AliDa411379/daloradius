# ADSL / Outdoor (Fiber) Users Guide

## Overview

Outdoor/ADSL users represent fixed-line subscribers who connect via fiber-to-MikroTik or DSL equipment. These are **monthly plan** subscribers with:

- **Monthly recurring plans** with a set cost deducted from balance each month
- **Balance system** - users top up their balance, monthly cost is deducted automatically
- **Bandwidth-only** control (no traffic or time limits)
- **Operator-created only** (no self-signup)
- Managed through RADIUS group profiles for bandwidth

## Subscription Types

The system supports three subscription types:

| ID | Type | Display Name | Billing Model |
|----|------|-------------|---------------|
| 1 | monthly | Monthly Subscription | Monthly invoice |
| 2 | prepaid | Prepaid (Bundles) | Pay-as-you-go bundles with traffic/time limits |
| 3 | outdoor | Outdoor/Fiber Service | Monthly plan, bandwidth-only, no traffic/time limits |

### Outdoor vs Other Types

| Aspect | Monthly (Type 1) | Prepaid/Bundle (Type 2) | Outdoor/ADSL (Type 3) |
|--------|-----------------|------------------------|----------------------|
| Traffic limits | Can have traffic caps | Yes (from bundle) | **No** traffic caps |
| Time limits | Can have session timeouts | Yes (bundle expiry) | **No** time limits |
| Bandwidth | Via RADIUS group | Via RADIUS group | Via RADIUS group |
| Balance | Optional | Yes (prepaid) | **Yes** (pay ahead for monthly) |
| Billing | Monthly deduction | Per bundle purchase | Monthly deduction from balance |
| Connection | Hotspot/WiFi typically | Hotspot/WiFi | Fiber/DSL/PPPoE |
| Equipment | Shared access points | Shared APs | Dedicated MikroTik per subscriber |

## Database Setup

Run the outdoor subscription type SQL if not already applied:

```sql
-- File: contrib/db/erp_integration/05_outdoor_service_type.sql
INSERT INTO subscription_types (type_name, display_name, description) VALUES
('outdoor', 'Outdoor/Fiber Service', 'Fiber-to-MikroTik connection broadcasting WiFi for single user');
```

No schema changes (ALTER TABLE) are needed. The system reuses existing columns.

---

## Step 1: Create an Outdoor Monthly Plan

Navigate to **Billing > Plans > New Plan**.

### Required Fields

| Field | Value | Notes |
|-------|-------|-------|
| Plan Name | e.g. `ADSL-10M` | Unique identifier |
| Plan Type | `Outdoor` | Select from dropdown |
| Subscription Type | `Outdoor/Fiber Service` | ID = 3 |
| Plan Cost | e.g. `50000` | **Monthly cost** deducted from user balance each cycle |
| Currency | `SYP` (or your currency) | |
| Bandwidth Up | e.g. `2048` | Upload in kbps |
| Bandwidth Down | e.g. `10240` | Download in kbps |
| Plan Active | `yes` | |
| Plan Recurring | `yes` | Monthly recurring plan |
| Recurring Period | `Monthly` | Billed every month |
| Is Bundle | `0` (No) | Outdoor plans are NOT bundles |

### Fields to Leave Empty/Zero

| Field | Value | Reason |
|-------|-------|--------|
| Traffic Total | `0` | No traffic cap - unlimited data |
| Traffic Up/Down | `0` | No traffic cap |
| Time Bank | `0` | No time limit - always connected |
| Bundle Validity Days | `0` | Not a bundle |
| Bundle Validity Hours | `0` | Not a bundle |

### Example Monthly Plans

| Plan Name | Cost (SYP) | Download | Upload | Use Case |
|-----------|-----------|----------|--------|----------|
| ADSL-2M | 25,000 | 2048 kbps | 512 kbps | Basic home |
| ADSL-5M | 40,000 | 5120 kbps | 1024 kbps | Standard home |
| ADSL-10M | 60,000 | 10240 kbps | 2048 kbps | Premium home |
| ADSL-20M | 90,000 | 20480 kbps | 4096 kbps | Business |
| ADSL-50M | 150,000 | 51200 kbps | 10240 kbps | Enterprise |

### RADIUS Profile

After creating the plan, assign RADIUS profiles that define bandwidth attributes. The key MikroTik attribute is:

```
Mikrotik-Rate-Limit = "10240k/2048k"
```

This is set through **RADIUS group reply attributes** linked to the plan.

---

## Step 2: Create RADIUS Group for Bandwidth

Navigate to **Management > Groups > Group Reply > New**.

Create a group reply entry:

| Field | Value |
|-------|-------|
| Group Name | e.g. `ADSL-10M-group` |
| Attribute | `Mikrotik-Rate-Limit` |
| Op | `:=` |
| Value | `10240k/2048k` (download/upload) |

You can add additional group reply attributes as needed:

| Attribute | Example Value | Purpose |
|-----------|---------------|---------|
| `Mikrotik-Rate-Limit` | `10240k/2048k` | Bandwidth limit |
| `Framed-Pool` | `outdoor-pool` | IP address pool |
| `Framed-IP-Address` | `10.0.1.100` | Static IP (if applicable) |

---

## Step 3: Link Plan to RADIUS Profile

The plan should be linked to the RADIUS group through the profiles system. When creating/editing the plan:

1. Go to **Billing > Plans > Edit Plan**
2. In the Profiles tab, add the RADIUS group name (e.g. `ADSL-10M-group`)
3. This ensures when a user is assigned this plan, they automatically get the correct RADIUS groups

---

## Step 4: Create an Outdoor User

Navigate to **Management > Users > New User**.

### Tab 1: User Info

| Field | Value | Notes |
|-------|-------|-------|
| Username | e.g. `adsl_customer_001` | Unique identifier (often phone number or account ID) |
| Password | (set password) | For PPPoE/RADIUS authentication |
| Auth Type | `Cleartext-Password` or `User-Password` | Standard RADIUS auth |

### Tab 2: User Billing Info

| Field | Value | Notes |
|-------|-------|-------|
| Plan Name | Select the outdoor plan (e.g. `ADSL-10M`) | **Monthly plan with bandwidth** |
| Subscription Type | `Outdoor/Fiber Service` | |
| Balance | Initial top-up amount | Customer pays upfront for future months |
| Address | Customer's installation address | Important for field service |
| Contact Info | Phone, email | For billing and support |

### Initial Balance Top-Up

When creating a new outdoor user, you should add an initial balance so they can pay for the first month(s):

1. Create the user with the outdoor plan
2. Go to **Bundles > Add Balance** or use the balance top-up page
3. Add the customer's payment as balance (e.g. 100,000 SYP for 2 months of ADSL-10M at 50,000/month)

### What Happens Automatically

When an outdoor user is created and assigned to an outdoor plan:

1. **RadiusAccessManager** detects `planType = 'Outdoor'`
2. Traffic limits are cleared (set to 0) - unlimited data
3. Time/expiration limits are removed - always connected
4. User is assigned to the plan's RADIUS groups
5. Bandwidth is controlled exclusively through the RADIUS group profile
6. Monthly cost is deducted from user's balance by the billing script

---

## Step 5: Monthly Billing Workflow (Balance-Based)

Outdoor users use a **balance-based monthly billing** model:

### How It Works

1. **Customer pays upfront** (cash, bank transfer, etc.) - operator adds balance
2. **Plan cost** is deducted from balance automatically by a MySQL event (daily at 01:00)
3. When **balance runs out** (insufficient for next month), user is **blocked** and a CoA Disconnect is sent
4. When customer **pays again** (balance topped up), user is **instantly reactivated** via a MySQL trigger

### Balance Flow Example

```
Month 1: Customer pays 150,000 SYP
         Balance: 150,000 SYP
         MySQL event deducts 50,000 (ADSL-10M plan)
         Balance after: 100,000 SYP

Month 2: MySQL event deducts 50,000
         Balance after: 50,000 SYP

Month 3: MySQL event deducts 50,000
         Balance after: 0 SYP

Month 4: MySQL event finds balance < plan cost
         User BLOCKED (added to block_user group)
         CoA Disconnect sent to NAS (kicks active session)
         Customer pays 100,000 SYP -> Balance: 100,000 SYP
         MySQL trigger fires INSTANTLY -> User REACTIVATED
```

### Adding Balance (Top-Up)

Operators can add balance through:
- **Bundles > Add Balance** page in the operator panel
- **API endpoint** `agent_topup_balance.php` (for agent-based top-ups)
- **Bulk top-up** via `agent_bulk_topup_balance.php` API

Each balance addition is recorded in `user_balance_history` for audit.

When balance is added and the user is an outdoor subscriber with sufficient balance, the MySQL trigger `trg_balance_topup_reactivate` fires immediately - removing the block and restoring the plan group. No waiting for a cron job.

### Architecture: MySQL Events + Trigger

All billing logic runs inside the database via MySQL events and a trigger. This replaces the old PHP cron scripts (`billing.php`, `suspend.php`, `reactivate.php`).

| Component | Type | Schedule | Purpose |
|-----------|------|----------|---------|
| `sp_outdoor_monthly_billing` | Stored Procedure | Daily at 01:00 | Deduct plan cost from balance, block if insufficient |
| `sp_expire_and_block_bundles` | Stored Procedure | Every 15 min | Expire prepaid bundles, block users with no active bundle |
| `sp_reactivate_outdoor_users` | Stored Procedure | Every 30 min | Fallback reactivation for blocked outdoor users with balance |
| `trg_balance_topup_reactivate` | Trigger | Real-time | Instant reactivation when balance increases above plan cost |
| `check_bundle_expiry.php` | PHP Cron | Every 5 min | CoA Disconnect only (reads `pending_disconnects` queue) |

The only PHP cron job still needed is for CoA Disconnect (sending `radclient` commands to the NAS), since MySQL cannot execute shell commands.

### Setup

**1. Enable MySQL Event Scheduler:**

```sql
SET GLOBAL event_scheduler = ON;
```

Or add to `/etc/mysql/my.cnf` under `[mysqld]`:
```
event_scheduler=ON
```

**2. Apply the SQL file:**

```bash
mysql -u root -p radius < contrib/db/erp_integration/08_subscription_lifecycle_events.sql
```

**3. Verify events are running:**

```sql
SELECT EVENT_NAME, STATUS, LAST_EXECUTED
FROM INFORMATION_SCHEMA.EVENTS
WHERE EVENT_SCHEMA = 'radius';
```

**4. Set up the single cron job for CoA Disconnect:**

```cron
# CoA Disconnect processor - reads pending_disconnects table, sends radclient
*/5 * * * * /usr/bin/php /var/www/daloradius/contrib/scripts/check_bundle_expiry.php
```

See `contrib/scripts/erp-crontab` for the full crontab configuration.

### Payment Process

1. Customer pays for upcoming months (cash, bank transfer, etc.)
2. Operator adds balance via **Bundles > Add Balance** or agent API
3. Balance is recorded in `user_balance_history`
4. **If user was blocked:** MySQL trigger fires instantly, removing block and restoring RADIUS access
5. MySQL event deducts plan cost from balance automatically on the billing date
6. If balance becomes insufficient, user is blocked and CoA Disconnect kicks the session

---

## Step 6: Manage Outdoor Users

### Viewing User Info

Navigate to **Management > Users > Edit User** and search for the username.

The **User Billing Info** tab shows:
- Subscription type: Outdoor/Fiber Service
- Plan name, monthly cost, and bandwidth info
- Address and contact information
- Payment/invoice history

### Changing User Plan (Bandwidth Upgrade/Downgrade)

When a customer wants to upgrade or downgrade their monthly plan:

1. Go to **Management > Users > Edit User**
2. Change the **Plan Name** to the new outdoor plan (e.g. ADSL-10M to ADSL-20M)
3. Click Apply
4. RadiusAccessManager will automatically:
   - Remove old RADIUS group assignments
   - Assign new RADIUS groups with updated bandwidth
   - Clear any leftover traffic/time attributes
5. **Monthly cost changes** take effect on the next billing cycle
6. The change is logged in the Action History for audit

### Suspending an Outdoor User (Insufficient Balance)

Suspension happens automatically when balance < plan cost, or manually:

**Automatic (via MySQL event `evt_outdoor_monthly_billing`):**
- Runs daily at 01:00 via MySQL event scheduler
- Checks if user balance < monthly plan cost
- Adds user to `block_user` RADIUS group
- Queues a CoA Disconnect in `pending_disconnects` table
- PHP cron picks up the queue and sends `radclient` Disconnect-Request to NAS

**Manual:**
1. Go to **Management > Users > Edit User**
2. Add user to `block_user` RADIUS group

### Reactivating a Suspended User (After Payment)

Reactivation happens automatically when balance is topped up, or manually:

**Automatic (instant via MySQL trigger `trg_balance_topup_reactivate`):**
- Fires immediately when `money_balance` increases on `userbillinfo`
- If user is outdoor (subscription_type_id=3) and balance >= plan cost and currently blocked:
  - Removes `block_user` group assignment
  - Removes `Auth-Type := Reject` from radcheck
  - Restores plan RADIUS group with priority 1
- User can reconnect immediately (no waiting for cron)
- A fallback event `evt_reactivate_outdoor` also runs every 30 minutes as a safety net

**Manual:**
1. Add balance via **Bundles > Add Balance** (trigger handles reactivation automatically)
2. If trigger didn't fire, go to **Management > Users > Edit User** and re-assign the plan

---

## How RADIUS Authentication Works for Outdoor Users

```
1. User's MikroTik connects via PPPoE
2. MikroTik sends RADIUS Access-Request to FreeRADIUS
3. FreeRADIUS checks radcheck (username + password)
4. FreeRADIUS looks up radusergroup (group membership)
5. FreeRADIUS applies radgroupreply attributes:
   - Mikrotik-Rate-Limit = "10240k/2048k"
   - (any other profile attributes)
6. Access-Accept sent to MikroTik
7. MikroTik applies bandwidth limit to the PPPoE connection
8. User gets internet at the assigned speed - no data cap, no time limit
```

### Key Differences from Other User Types

| Aspect | Prepaid (Bundle) | Monthly (Type 1) | Outdoor/ADSL (Type 3) |
|--------|-----------------|-------------------|----------------------|
| Billing | Per bundle purchase | Monthly deduction | **Monthly deduction from balance** |
| Balance | Yes (prepaid top-up) | Optional | **Yes** (pay ahead for months) |
| Traffic limit | Yes (from bundle) | Optional | **No** (unlimited data) |
| Time limit | Yes (bundle expiry) | Optional | **No** (always connected) |
| Bandwidth | Via RADIUS group | Via RADIUS group | Via RADIUS group |
| Auto-suspend | Bundle expires | Insufficient balance | **Insufficient balance** |
| Auto-reactivate | New bundle purchase | Balance topped up | **Balance topped up** |
| Connection | Hotspot/WiFi | Various | PPPoE/Fiber |
| Equipment | Shared AP | Various | Dedicated MikroTik |

---

## MikroTik Configuration Notes

### NAS Setup

Ensure the MikroTik device is registered as a NAS in daloRADIUS:

1. Go to **Management > NAS > New NAS**
2. Add the MikroTik's IP, shared secret, and short name
3. Match the shared secret with the MikroTik RADIUS client configuration

### MikroTik RADIUS Client

```routeros
/radius
add address=<freeradius-ip> secret=<shared-secret> service=ppp
```

### PPPoE Server on MikroTik

```routeros
/interface pppoe-server server
add service-name=outdoor-pppoe interface=ether1 \
    default-profile=default-encryption authentication=mschap2,chap
```

### Rate Limit Format

The `Mikrotik-Rate-Limit` attribute format:

```
rx-rate[/tx-rate] [rx-burst-rate/tx-burst-rate] [rx-burst-threshold/tx-burst-threshold] [rx-burst-time/tx-burst-time] [priority] [rx-rate-min/tx-rate-min]
```

Common examples:
| Plan | Value | Meaning |
|------|-------|---------|
| 2 Mbps | `2048k/2048k` | 2M download / 2M upload |
| 5 Mbps | `5120k/1024k` | 5M download / 1M upload |
| 10 Mbps | `10240k/2048k` | 10M download / 2M upload |
| 20 Mbps | `20480k/4096k` | 20M download / 4M upload |
| 50 Mbps | `51200k/10240k` | 50M download / 10M upload |

With burst:
```
10240k/2048k 15360k/3072k 8192k/1536k 8/8
```
(10M/2M normal, burst to 15M/3M when under threshold, 8 second burst time)

---

## Troubleshooting

### User Cannot Connect

1. **Check RADIUS auth:** Go to **Config > Maintenance > Test User** and test the username
2. **Check NAS config:** Verify MikroTik NAS entry matches the router's IP and secret
3. **Check RADIUS groups:** Verify the user has group assignments in **Management > User Groups**
4. **Check FreeRADIUS logs:** `tail -f /var/log/freeradius/radius.log`

### Bandwidth Not Applied

1. **Verify group reply attributes:** Check `Mikrotik-Rate-Limit` is set correctly in the RADIUS group
2. **Check group membership:** Ensure user is in the correct RADIUS group (radusergroup table)
3. **Test with radtest:**
   ```bash
   radtest username password freeradius-ip 0 shared-secret
   ```
4. **Check MikroTik active connections:** `/ppp active print` should show the rate limit

### User Gets Traffic/Time Limits (Should Not)

This means RadiusAccessManager didn't properly handle the outdoor type. Check:
1. The plan's `planType` contains "outdoor" (case-insensitive)
2. The user's `subscription_type_id = 3` in userbillinfo
3. Run manual cleanup:
   ```sql
   -- Remove traffic limits for outdoor user
   DELETE FROM radcheck WHERE username='<user>' AND attribute='Expiration';
   DELETE FROM radreply WHERE username='<user>' AND attribute='Session-Timeout';
   DELETE FROM radreply WHERE username='<user>' AND attribute LIKE 'Mikrotik-Total-Limit%';
   ```

### User Suspended But Has Paid

1. Check balance was added via **Bundles > Add Balance** (not just payment recorded)
2. Verify `money_balance` in userbillinfo is >= plan cost
3. The MySQL trigger should have auto-reactivated the user. If not:
   - Check `SELECT * FROM radusergroup WHERE username='<user>'` for `block_user` entry
   - Manually run: `CALL sp_reactivate_outdoor_users();`
   - Or manually re-assign the plan in **Management > Users > Edit User**
4. Verify the MySQL event scheduler is running: `SHOW VARIABLES LIKE 'event_scheduler'`

### Balance Not Updating After Top-Up

1. Check the balance addition appears in `user_balance_history` table
2. Verify the API call returned success (if using agent API)
3. Check `money_balance` field directly: `SELECT money_balance FROM userbillinfo WHERE username='<user>'`

---

## Summary: Quick Checklist for New Outdoor User

1. [ ] Create outdoor monthly plan in **Billing > Plans > New Plan** (planType=Outdoor, subscription_type_id=3, recurring=yes)
2. [ ] Create RADIUS group with `Mikrotik-Rate-Limit` attribute in **Management > Groups > Group Reply**
3. [ ] Link plan to RADIUS profile/group
4. [ ] Register MikroTik as NAS in **Management > NAS > New NAS**
5. [ ] Create user in **Management > Users > New User**
6. [ ] Assign outdoor monthly plan to user
7. [ ] **Add initial balance** via Bundles > Add Balance (customer's first payment)
8. [ ] Verify RADIUS authentication with Test User
9. [ ] User connects via PPPoE - bandwidth applied, unlimited data
10. [ ] Apply `contrib/db/erp_integration/08_subscription_lifecycle_events.sql` (MySQL events + trigger)
11. [ ] Enable MySQL event scheduler: `SET GLOBAL event_scheduler = ON`
12. [ ] Set up single cron job for CoA Disconnect (see `contrib/scripts/erp-crontab`)
