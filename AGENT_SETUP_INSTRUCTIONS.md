# Agent Creation Setup Instructions

## Overview
The agent creation functionality has been updated to properly mark operators as agents when they are created through the agent creation form.

## Required Database Update

Before using the agent creation feature, you need to add the `is_agent` column to the operators table.

### Option 1: Run the PHP Script (Recommended)
```bash
cd /opt/lampp/htdocs/daloradius
php add_is_agent_column.php
```

### Option 2: Run the SQL Script
```bash
mysql -u root -p < add_is_agent_column.sql
```

### Option 3: Manual SQL Command
```sql
USE radius;
ALTER TABLE operators ADD COLUMN is_agent TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 if operator is an agent, 0 otherwise';
```

## What the Agent Creation Does

When you create an agent through `/app/operators/mng-agent-new.php`, the system will:

1. **Create Agent Record** - Adds entry to `agents` table with contact information
2. **Create Operator Account** - Adds entry to `operators` table with login credentials
3. **Mark as Agent** - Sets `is_agent = 1` in the operators table
4. **Grant Permissions** - Assigns all available permissions to the new operator
5. **Success Confirmation** - Shows confirmation that operator is marked as agent

## Form Fields

**Agent Information:**
- Name (required)
- Company
- Email
- Phone

**Operator Login Credentials:**
- Operator Username (required)
- Operator Password (required)
- First Name
- Last Name

**Contact Information:**
- Address
- City
- Country

## Verification

After creating an agent, you can verify it worked by:
1. Checking the operators list at `/app/operators/config-operators-list.php`
2. Looking for the "Agent" column which should show "Yes" for agent operators
3. The agent should be able to login using their operator credentials

## Files Modified

- `app/operators/mng-agent-new.php` - Updated to set `is_agent = 1`
- `add_is_agent_column.php` - Script to add missing database column
- `add_is_agent_column.sql` - SQL script to add missing database column