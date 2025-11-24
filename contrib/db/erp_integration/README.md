# ERP Integration - Database Migration

## Overview
This directory contains database migrations for the dual subscription model (Monthly + Prepaid Bundles) ERP integration.

## Migration Files

### 01_erp_dual_subscription_schema.sql
Main schema migration that creates:
- `subscription_types` - Monthly and prepaid subscription types
- Extensions to `billing_plans` - Bundle fields
- Extensions to `userbillinfo` - Subscription tracking
- `user_bundles` - Bundle purchase and activation history
- `agent_payments` - Agent payment records (NO agent balance)
- `payment_refunds` - Refund audit trail
- `monthly_subscription_config` - Billing configuration

## Installation

**IMPORTANT: Backup your database first!**

```bash
# Backup
mysqldump -u username -p radius > backup_$(date +%Y%m%d).sql

# Run migration
mysql -u username -p radius < 01_erp_dual_subscription_schema.sql
```

## Verification

After running the migration, verify:

```sql
-- Check tables created
SHOW TABLES LIKE '%subscription%';
SHOW TABLES LIKE '%bundle%';
SHOW TABLES LIKE '%agent_payment%';
SHOW TABLES LIKE '%refund%';

-- Check billing_plans columns
DESC billing_plans;

-- Check userbillinfo columns
DESC userbillinfo;

-- Check subscription types
SELECT * FROM subscription_types;

-- Check monthly config
SELECT * FROM monthly_subscription_config;
```

## Rollback

If you need to rollback, run:

```sql
-- Remove new tables
DROP TABLE IF EXISTS payment_refunds;
DROP TABLE IF EXISTS agent_payments;
DROP TABLE IF EXISTS user_bundles;
DROP TABLE IF EXISTS monthly_subscription_config;
DROP TABLE IF EXISTS subscription_types;

-- Remove columns from billing_plans
ALTER TABLE billing_plans 
  DROP COLUMN auto_renew,
  DROP COLUMN bundle_validity_hours,
  DROP COLUMN bundle_validity_days,
  DROP COLUMN is_bundle,
  DROP COLUMN subscription_type_id;

-- Remove columns from userbillinfo
ALTER TABLE userbillinfo
  DROP COLUMN monthly_billing_day,
  DROP COLUMN bundle_status,
  DROP COLUMN bundle_expiry_date,
  DROP COLUMN bundle_activation_date,
  DROP COLUMN current_bundle_id,
  DROP COLUMN subscription_type_id;
```

## Next Steps

After migrating the database:
1. Deploy core library classes
2. Update operator UI
3. Deploy API endpoints
4. Configure cron jobs
5. Test thoroughly
