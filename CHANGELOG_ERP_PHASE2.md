# ERP Integration - Phase 2 Changelog

**Date:** February 2026
**Scope:** Navigation/ACL, Outdoor Service Type, Bundle Change, Price Protection, Action History, API Security, MySQL Event-based Billing

---

## Overview

This release replaces 3 PHP cron scripts (billing.php, suspend.php, reactivate.php) with native MySQL events and triggers, adds the outdoor/fiber service type, implements bundle change with prorate refund, adds system-wide audit logging, and makes all bundle/report pages visible in the UI.

### Architecture Change: PHP Cron -> MySQL Events

| Before | After |
|--------|-------|
| `var/scripts/billing.php` (cron every hour) | `evt_outdoor_monthly_billing` (MySQL event, daily 01:00) |
| `var/scripts/suspend.php` (cron every hour) | `evt_expire_bundles` (MySQL event, every 15 min) |
| `var/scripts/reactivate.php` (cron every hour) | `trg_balance_topup_reactivate` (MySQL trigger, instant) + `evt_reactivate_outdoor` (fallback, every 30 min) |
| 3 cron entries | 1 cron entry (CoA disconnect only) |

**Why:** MySQL events run inside the database with zero network overhead. The trigger provides instant reactivation when balance increases instead of waiting for the next cron poll.

**The only remaining PHP cron** is `check_bundle_expiry.php` which reads the `pending_disconnects` queue table and sends CoA Disconnect-Request packets via `radclient`. MySQL cannot execute shell commands, so this bridge is required.

---

## SQL Migrations

Run these in order. All are idempotent (use `IF NOT EXISTS`, `INSERT IGNORE`, `DROP ... IF EXISTS`).

```bash
# Prerequisites
mysql -u root -p radius -e "SET GLOBAL event_scheduler = ON;"
# Or add to /etc/mysql/my.cnf under [mysqld]: event_scheduler=ON

# Run migrations in order
mysql -u root -p radius < contrib/db/erp_integration/04_acl_nav_updates.sql
mysql -u root -p radius < contrib/db/erp_integration/05_outdoor_service_type.sql
mysql -u root -p radius < contrib/db/erp_integration/06_plan_price_history_and_action_log.sql
mysql -u root -p radius < contrib/db/erp_integration/07_auto_reactivate_and_free_bundle.sql
mysql -u root -p radius < contrib/db/erp_integration/08_subscription_lifecycle_events.sql
```

### Migration Details

| File | What It Does | Tables Affected |
|------|-------------|-----------------|
| `04_acl_nav_updates.sql` | Registers 3 new pages in ACL, grants admin access | `operators_acl_files`, `operators_acl` |
| `05_outdoor_service_type.sql` | Adds outdoor subscription type (id=3) | `subscription_types` (INSERT only) |
| `06_plan_price_history_and_action_log.sql` | Creates audit trail tables | Creates `plan_price_history`, `system_action_log` |
| `07_auto_reactivate_and_free_bundle.sql` | Adds auto-reactivate flag per user | `userbillinfo` (ADD COLUMN `auto_reactivate`) |
| `08_subscription_lifecycle_events.sql` | Creates billing engine | Creates `pending_disconnects`, 3 stored procedures, 4 events, 1 trigger |

### Verification

```sql
-- Check events are running
SELECT EVENT_NAME, STATUS, LAST_EXECUTED
FROM INFORMATION_SCHEMA.EVENTS
WHERE EVENT_SCHEMA = 'radius';

-- Check trigger exists
SELECT TRIGGER_NAME, EVENT_OBJECT_TABLE
FROM INFORMATION_SCHEMA.TRIGGERS
WHERE TRIGGER_SCHEMA = 'radius' AND TRIGGER_NAME = 'trg_balance_topup_reactivate';

-- Check new tables
SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = 'radius'
  AND TABLE_NAME IN ('pending_disconnects', 'plan_price_history', 'system_action_log');
```

---

## Cron Configuration

Replace old crontab entries with:

```cron
# CoA Disconnect Processor - reads pending_disconnects table, sends radclient
*/5 * * * * /usr/bin/php /var/www/daloradius/contrib/scripts/check_bundle_expiry.php >> /var/log/daloradius/coa_disconnect.log 2>&1
```

Remove any old entries for `billing.php`, `suspend.php`, `reactivate.php`.

---

## New Features

### 1. Bundle Navigation (Phase 1)

The "Bundles" section is now visible in the top navigation bar with its own sidebar and sub-navigation.

**Menu structure:**
- Bundles (top nav)
  - Purchase Bundle
  - List Bundles
  - Change Bundle
  - Add Balance

**Reports section** now includes:
- Action History
- Bundle Purchases
- Agent Payments

### 2. Outdoor/Fiber Service Type (Phase 2)

New subscription type for fiber-to-MikroTik users. Operator-created only (no self-signup).

**Characteristics:**
- `subscription_type_id = 3` in `userbillinfo` and `billing_plans`
- `planType = 'Outdoor'` in `billing_plans`
- Monthly recurring billing (deducted from balance by MySQL event)
- No traffic/time limits (bandwidth controlled via RADIUS profile only)
- Auto-suspend on insufficient balance
- Instant reactivation on balance topup (via MySQL trigger)

**How to create an outdoor plan:**
1. Go to Billing > Plans > New Plan
2. Set Plan Type to "Outdoor"
3. Set Subscription Type to "Outdoor/Fiber"
4. Set Recurring = "yes", Period = "monthly"
5. Set Plan Cost (monthly fee)
6. Assign a RADIUS profile (for bandwidth via Mikrotik-Rate-Limit)

### 3. Change Bundle with Prorate Refund (Phase 3)

Operators and agents can now change a user's active bundle to a different plan. The system calculates a prorate refund for remaining days.

**Formula:** `refund = (remaining_days / total_days) * purchase_amount`

**Flow:**
1. Select user with active bundle
2. System shows current bundle info + remaining days
3. Select new plan
4. Preview shows: refund amount, new cost, net charge, resulting balance
5. Confirm to execute (single database transaction)

**Also supports:** Grant Free Bundle for staff/VIP users (no balance deduction).

**Operator page:** `bundle-change.php`
**API endpoint:** `POST /app/users/api/agent_change_bundle.php`

### 4. Plan Price Change Protection (Phase 4)

When a plan's cost or configuration is edited, the system automatically records all changed fields to `plan_price_history`.

**Monitored fields:** planCost, planSetupCost, planTax, planBandwidthUp, planBandwidthDown, planTrafficTotal, planTrafficUp, planTrafficDown, bundle_validity_days, bundle_validity_hours, planActive, planType, is_bundle, planRecurring, planRecurringPeriod

Existing bundles and payments are NOT affected by plan price changes (purchase_amount is stored per-transaction).

### 5. System-Wide Action History (Phase 5)

All significant actions are now logged to `system_action_log` with full audit trail.

**Logged actions:**
| Action Type | When | Source Page |
|------------|------|-------------|
| `user_create` | New user created | `mng-new.php` |
| `user_edit` | User edited (with old/new values) | `mng-edit.php` |
| `user_delete` | User deleted | `mng-del.php` |
| `plan_create` | New plan created | `bill-plans-new.php` |
| `plan_edit` | Plan edited (with field-level diff) | `bill-plans-edit.php` |
| `bundle_purchase` | Bundle purchased | `bundle-purchase.php` |
| `bundle_change` | Bundle changed (prorate refund) | `bundle-change.php` |
| `free_bundle` | Free bundle granted | `bundle-change.php` |
| `balance_topup` | Balance added to user | `bill-balance-add.php` |
| `operator_login` | Operator logged in | `dologin.php` |
| `operator_create` | New operator created | `config-operators-new.php` |
| `operator_edit` | Operator edited | `config-operators-edit.php` |
| `agent_edit` | Agent edited | `mng-agents-edit.php` |
| `outdoor_billing` | Monthly billing run | MySQL event (automatic) |
| `bundle_expiry` | Bundle expiry check | MySQL event (automatic) |
| `outdoor_reactivate` | Outdoor user reactivated | MySQL event (automatic) |

**Report page:** Reports > Action History (`rep-action-history.php`)
- Filter by action type, target type, username, operator, date range
- Expandable rows showing old/new values as JSON
- CSV export

### 6. API Security Fixes (Phase 6)

- All endpoints now require API key authentication (moved from public to protected)
- Removed test API key `test_key_12345`
- Added `agent_change_bundle.php` to protected endpoints
- Added `agent_bulk_topup_balance.php`, `plan_lookups.php` to protected endpoints

---

## Files Changed

### New Files (16)

| File | Purpose |
|------|---------|
| `app/common/library/ActionLogger.php` | Audit trail library class |
| `app/operators/bundle-change.php` | Change bundle / grant free bundle page |
| `app/operators/rep-action-history.php` | Action history report page |
| `app/users/api/agent_change_bundle.php` | API: change bundle endpoint |
| `app/operators/include/menu/sidebar/bundle/default.php` | Bundle sidebar (default) |
| `app/operators/include/menu/sidebar/bundle/purchase.php` | Bundle sidebar (purchase) |
| `app/operators/include/menu/sidebar/bundle/list.php` | Bundle sidebar (list) |
| `app/operators/include/menu/sidebar/bundle/change.php` | Bundle sidebar (change) |
| `contrib/db/erp_integration/04_acl_nav_updates.sql` | ACL registration |
| `contrib/db/erp_integration/05_outdoor_service_type.sql` | Outdoor subscription type |
| `contrib/db/erp_integration/06_plan_price_history_and_action_log.sql` | Audit tables |
| `contrib/db/erp_integration/07_auto_reactivate_and_free_bundle.sql` | Auto-reactivate column |
| `contrib/db/erp_integration/08_subscription_lifecycle_events.sql` | MySQL events + trigger |
| `doc/adsl-outdoor-users.md` | Outdoor user setup guide |
| `TABLE_STRUCTURES_SUMMARY.md` | Table structure reference |
| `CHANGELOG_ERP_PHASE2.md` | This file |

### Modified Files (24)

| File | Change Summary |
|------|---------------|
| `app/common/includes/validation.php` | Added "outdoor" to valid plan types |
| `app/common/library/BundleManager.php` | Added changeBundle(), purchaseFreeBundle(), autoReactivateBundles(), getChangeBundlePreview() |
| `app/common/library/RadiusAccessManager.php` | Added outdoor plan handling (skip traffic/time limits) |
| `app/operators/bill-balance-add.php` | Added ActionLogger for balance_topup |
| `app/operators/bill-plans-edit.php` | Added ActionLogger + plan price change tracking |
| `app/operators/bill-plans-new.php` | Added ActionLogger for plan_create |
| `app/operators/bundle-purchase.php` | Added ActionLogger for bundle_purchase |
| `app/operators/config-operators-edit.php` | Added ActionLogger for operator_edit |
| `app/operators/config-operators-new.php` | Added ActionLogger for operator_create |
| `app/operators/dologin.php` | Added ActionLogger for operator_login |
| `app/operators/mng-agents-edit.php` | Added ActionLogger for agent_edit |
| `app/operators/mng-del.php` | Added ActionLogger for user_delete |
| `app/operators/mng-edit.php` | Added auto_reactivate field + ActionLogger for user_edit |
| `app/operators/mng-new.php` | Added ActionLogger for user_create |
| `app/operators/include/management/functions.php` | Added subscription_type, auto_reactivate to allowed fields |
| `app/operators/include/management/userbillinfo.php` | Added outdoor subscription option + auto_reactivate toggle |
| `app/operators/include/menu/nav.php` | Added "Bundles" to top nav |
| `app/operators/include/menu/subnav.php` | Added bundle + report sub-navigation entries |
| `app/operators/include/menu/sidebar.php` | Added "bundle" category with subcategories |
| `app/operators/include/menu/sidebar/rep/default.php` | Added Action History, Bundle Purchases, Agent Payments links |
| `app/operators/lang/en.php` | Added 4 translation keys for bundle buttons |
| `app/users/api/config.php` | All endpoints protected, removed test key |
| `contrib/scripts/check_bundle_expiry.php` | Rewritten to CoA-only processor (173 lines, was 407) |
| `contrib/scripts/erp-crontab` | Simplified to 1 cron entry |

---

## Database Schema Changes Summary

### New Tables (3)

**plan_price_history**
```
id, plan_id, plan_name, field_changed, old_value, new_value, changed_by, changed_at, ip_address
```

**system_action_log**
```
id, action_type, target_type, target_id, description, old_value, new_value, performed_by, ip_address, created_at
```

**pending_disconnects**
```
id, username, reason, created_at, processed, processed_at
```

### Altered Tables (1)

**userbillinfo** - Added 1 column:
```sql
auto_reactivate TINYINT(1) NOT NULL DEFAULT 0
```

### New Stored Procedures (3)

| Procedure | Called By | Purpose |
|-----------|----------|---------|
| `sp_outdoor_monthly_billing()` | `evt_outdoor_monthly_billing` (daily) | Deducts plan cost from outdoor user balances, blocks on insufficient funds |
| `sp_expire_and_block_bundles()` | `evt_expire_bundles` (every 15 min) | Marks expired bundles, blocks users with no active bundle |
| `sp_reactivate_outdoor_users()` | `evt_reactivate_outdoor` (every 30 min) | Fallback reactivation for blocked outdoor users with sufficient balance |

### New MySQL Events (4)

| Event | Schedule | Purpose |
|-------|----------|---------|
| `evt_outdoor_monthly_billing` | Daily at 01:00 | Outdoor/ADSL monthly billing |
| `evt_expire_bundles` | Every 15 minutes | Bundle expiry + user blocking |
| `evt_reactivate_outdoor` | Every 30 minutes | Fallback reactivation |
| `evt_cleanup_pending_disconnects` | Daily at 03:00 | Remove processed disconnect records older than 7 days |

### New Trigger (1)

| Trigger | Table | Event | Purpose |
|---------|-------|-------|---------|
| `trg_balance_topup_reactivate` | `userbillinfo` | AFTER UPDATE | When `money_balance` increases for a blocked outdoor user with sufficient balance: removes from `block_user` group, restores plan profile, logs to `billing_history` |

---

## CoA (Change of Authorization) Explained

CoA is an RFC 5176 protocol feature that allows the RADIUS server to push a Disconnect-Request to a NAS (MikroTik router) to terminate an active user session immediately.

**How it works in this system:**

```
MySQL Event (blocks user) -> Inserts into pending_disconnects table
    |
PHP Cron (every 5 min)   -> Reads pending_disconnects, finds active sessions in radacct
    |
radclient                -> Sends Disconnect-Request to NAS:3799
    |
MikroTik NAS             -> Terminates user session immediately
```

**Why a PHP cron is still needed:** MySQL events cannot execute shell commands. The `radclient` binary must be invoked from PHP. The `pending_disconnects` table acts as a queue between MySQL (which handles all billing logic) and PHP (which handles CoA only).

---

## Prerequisites

1. **MySQL Event Scheduler** must be enabled:
   ```ini
   # /etc/mysql/my.cnf under [mysqld]
   event_scheduler=ON
   ```

2. **radclient** must be installed (comes with FreeRADIUS):
   ```bash
   which radclient  # Should return /usr/bin/radclient
   ```

3. **NAS secret** must be configured in the `nas` table for each NAS IP

4. **Log directory** must exist:
   ```bash
   mkdir -p /var/log/daloradius
   ```

5. **SQL migrations 01-03** from the ERP Phase 1 must already be applied (creates `subscription_types`, `user_bundles`, `agent_payments` tables and adds columns to `userbillinfo` and `billing_plans`)
