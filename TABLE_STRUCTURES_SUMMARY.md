# DaloRADIUS Table Structures Summary

## user_bundles Table

**Location:** 
- `contrib/db/erp_integration/01_erp_dual_subscription_schema.sql` (lines 53-78)
- `dalo_new_db.sql` (lines 1044-1069)

**CREATE TABLE Statement:**
```sql
CREATE TABLE IF NOT EXISTS `user_bundles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(8) unsigned NOT NULL COMMENT 'Reference to userbillinfo.id',
  `username` varchar(128) NOT NULL,
  `plan_id` int(8) NOT NULL COMMENT 'Reference to billing_plans.id',
  `plan_name` varchar(128) NOT NULL,
  `purchase_amount` decimal(10,2) NOT NULL COMMENT 'Amount paid for bundle',
  `purchase_date` datetime NOT NULL,
  `activation_date` datetime DEFAULT NULL COMMENT 'When bundle was activated',
  `expiry_date` datetime DEFAULT NULL COMMENT 'When bundle expires',
  `status` enum('pending','active','expired','cancelled') DEFAULT 'active' COMMENT 'Bundle status - auto-activate on purchase',
  `balance_before` decimal(10,2) NOT NULL COMMENT 'User balance before purchase',
  `balance_after` decimal(10,2) NOT NULL COMMENT 'User balance after purchase',
  `agent_payment_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Link to agent_payments if purchased via agent',
  `created_by` varchar(128) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `notes` text,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_username` (`username`),
  KEY `idx_plan_id` (`plan_id`),
  KEY `idx_status` (`status`),
  KEY `idx_expiry_date` (`expiry_date`),
  KEY `idx_agent_payment` (`agent_payment_id`),
  KEY `idx_purchase_date` (`purchase_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci 
COMMENT='Bundle purchase and activation history';
```

### Column Details:

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| id | bigint(20) unsigned | NO | AUTO_INCREMENT | Primary key |
| user_id | int(8) unsigned | NO | - | Reference to userbillinfo.id |
| username | varchar(128) | NO | - | Username |
| plan_id | int(8) | NO | - | Reference to billing_plans.id |
| plan_name | varchar(128) | NO | - | Plan name |
| purchase_amount | decimal(10,2) | NO | - | Amount paid for bundle |
| purchase_date | datetime | NO | - | Purchase timestamp |
| activation_date | datetime | YES | NULL | When bundle was activated |
| expiry_date | datetime | YES | NULL | When bundle expires |
| status | enum | YES | 'active' | Bundle status (pending, active, expired, cancelled) |
| balance_before | decimal(10,2) | NO | - | User balance before purchase |
| balance_after | decimal(10,2) | NO | - | User balance after purchase |
| agent_payment_id | bigint(20) unsigned | YES | NULL | Link to agent_payments table |
| created_by | varchar(128) | YES | NULL | Who created the record |
| created_at | datetime | YES | CURRENT_TIMESTAMP | Creation timestamp |
| notes | text | YES | NULL | Additional notes |

---

## userbillinfo Table - Bundle-Related Columns

**Location:** `dalo_new_db.sql` (lines 907-972)

### Bundle & Subscription Columns:

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| subscription_type_id | int(10) unsigned | YES | 1 | Current subscription type (1=monthly, 2=prepaid) |
| current_bundle_id | int(32) | YES | NULL | Active bundle ID from user_bundles table |
| bundle_activation_date | datetime | YES | NULL | When current bundle was activated |
| bundle_expiry_date | datetime | YES | NULL | When current bundle expires |
| bundle_status | enum('active','expired','suspended') | YES | NULL | Current bundle status |
| monthly_billing_day | int(2) | YES | 26 | Day of month for invoice generation (1-28) |

### Balance-Related Columns:

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| timebank_balance | decimal(10,2) | NO | 0.00 | Current Accumulative Time Bank balance |
| money_balance | decimal(10,2) | YES | 0.00 | User monetary balance in dollars |
| total_invoices_amount | decimal(10,2) | YES | 0.00 | Total amount of unpaid invoices |
| last_balance_update | datetime | YES | NULL | Last time balance was modified |

**Note:** There is a CHECK constraint: `money_balance >= -300000.00`

---

## billing_plans Table - Auto-Renewal Columns

**Location:** `dalo_new_db.sql` (lines 199-235)

### Bundle & Auto-Renewal Columns:

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| subscription_type_id | int(10) unsigned | YES | 1 | Link to subscription_types (1=monthly, 2=prepaid) |
| is_bundle | tinyint(1) | YES | 0 | 1 for prepaid bundles, 0 for monthly plans |
| bundle_validity_days | int(11) | YES | NULL | Validity period for bundles in days |
| bundle_validity_hours | int(11) | YES | NULL | Additional hours for bundle validity |
| **auto_renew** | **tinyint(1)** | **YES** | **0** | **Auto-renew from balance if available (future feature)** |

---

## Key Findings:

1. **auto_reactivate Column:** Does NOT exist in the database schema
   - No column named `auto_reactivate` in any table

2. **auto_renew Column:** EXISTS in `billing_plans` table
   - Column: `auto_renew` tinyint(1) DEFAULT 0
   - Comment: "Auto-renew from balance if available (future feature)"
   - This is a PLAN-level setting, not a USER-level setting

3. **User Bundle Management:**
   - User bundles are tracked in `user_bundles` table
   - Current active bundle is linked via `userbillinfo.current_bundle_id`
   - Bundle status tracked in both tables (user_bundles.status and userbillinfo.bundle_status)

4. **Status Values:**
   - `user_bundles.status`: 'pending', 'active', 'expired', 'cancelled'
   - `userbillinfo.bundle_status`: 'active', 'expired', 'suspended'

5. **No Direct Auto-Reactivation Flag:**
   - There is no user-level flag for automatic reactivation
   - The `auto_renew` in billing_plans is marked as "future feature"
   - Reactivation logic would need to be implemented programmatically

---

## Recommendations:

If you need an auto-reactivation feature, you would need to either:

1. **Use the existing `auto_renew` column in billing_plans** (plan-level)
2. **Add a new column to userbillinfo:**
   ```sql
   ALTER TABLE userbillinfo 
   ADD COLUMN auto_reactivate tinyint(1) DEFAULT 0 
   COMMENT 'Auto-reactivate user when balance is sufficient';
   ```

The current schema supports manual bundle purchases and tracking, but automatic reactivation would require additional implementation in the billing scripts.
