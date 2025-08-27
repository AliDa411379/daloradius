# Agent Auto-Assignment Implementation

## Overview
This implementation automatically assigns users and payments to the logged-in agent operator, preventing them from selecting different agents.

## Features Implemented

### ✅ User Creation (mng-new.php)
- **Agent operators**: Agent selection is hidden and current agent is auto-assigned
- **Non-agent operators**: Full agent selection functionality remains
- **UI**: Shows read-only field with clear messaging for agent operators

### ✅ Payment Creation (bill-payments-new.php)  
- **Agent operators**: Agent field is read-only, shows current agent
- **User filtering**: Only shows users belonging to the current agent
- **Non-agent operators**: Full functionality preserved

### ✅ Database Integration
- **Primary method**: Uses `operator_id` foreign key in agents table
- **Fallback method**: Matches by company name, email, or name if foreign key doesn't exist
- **Backward compatible**: Works with existing database structures

## Files Modified/Created

### Core Files
- `app/operators/library/agent_functions.php` - Helper functions for agent operations
- `app/operators/mng-new.php` - User creation with auto-assignment
- `app/operators/bill-payments-new.php` - Payment creation with auto-assignment  
- `app/operators/include/management/userbillinfo.php` - Agent selection UI
- `app/operators/mng-agent-new.php` - Agent creation with operator linking

### Setup/Test Files
- `update_agent_schema.sql` - Database schema updates
- `verify_agent_setup.php` - Setup verification script
- `test_agent_auto_assignment.php` - Functionality testing script

## Database Requirements

### Required Columns
```sql
-- Essential: Mark operators as agents
ALTER TABLE operators ADD COLUMN IF NOT EXISTS is_agent TINYINT(1) NOT NULL DEFAULT 0;

-- Recommended: Direct operator-agent relationship
ALTER TABLE agents ADD COLUMN IF NOT EXISTS operator_id INT(11) NULL;
```

### Sample Setup
```sql
-- 1. Mark an operator as agent
UPDATE operators SET is_agent = 1 WHERE username = 'agent_username';

-- 2. Create or update agent record
INSERT INTO agents (name, company, email, operator_id) 
VALUES ('Agent Name', 'Company', 'email@example.com', 
        (SELECT id FROM operators WHERE username = 'agent_username'));
```

## How It Works

### For Agent Operators (is_agent = 1):
1. **Login**: System identifies operator as agent
2. **User Creation**: 
   - Agent selection field is hidden
   - Current agent is automatically assigned
   - Shows read-only confirmation
3. **Payment Creation**:
   - Agent field shows current agent (read-only)
   - User dropdown filtered to show only users from current agent

### For Regular Operators (is_agent = 0):
- Full functionality preserved
- Can select any agent
- Can create payments for any user

## Agent Identification Logic

### Primary Method (Recommended)
```php
// Direct foreign key relationship
SELECT id FROM agents WHERE operator_id = ? AND is_deleted = 0
```

### Fallback Method
```php
// Match by operator info
SELECT id FROM agents WHERE is_deleted = 0 AND (
    company = operator.company OR 
    email = operator.email1 OR 
    name = operator.firstname OR 
    name = operator.lastname OR
    name = CONCAT(operator.firstname, ' ', operator.lastname)
)
```

## Testing

### Quick Test
1. Run `verify_agent_setup.php` to check setup
2. Run `test_agent_auto_assignment.php` for detailed testing
3. Login with agent operator and test user/payment creation

### Manual Test Steps
1. **Setup agent operator**:
   ```sql
   UPDATE operators SET is_agent = 1 WHERE username = 'test_agent';
   ```

2. **Create matching agent**:
   - Use agent creation form or direct SQL
   - Ensure company/email matches operator

3. **Test user creation**:
   - Login as agent operator
   - Go to user creation page
   - Verify agent field is read-only and shows correct agent

4. **Test payment creation**:
   - Go to payment creation page  
   - Verify agent is auto-assigned
   - Verify user list is filtered

## Troubleshooting

### Agent Not Auto-Assigned
- Check `is_agent = 1` for operator
- Verify agent record exists with matching company/email
- Check database connection and table names in config

### Users Not Filtered in Payments
- Ensure users are properly linked to agents via `user_agent` table
- Check agent ID is correctly identified

### Fallback Matching Issues
- Ensure operator has company or email filled
- Check agent record has matching company/email/name

## Configuration

### Database Tables Used
- `operators` - Operator accounts (requires `is_agent` column)
- `agents` - Agent records (optional `operator_id` column)
- `userinfo` - User information
- `user_agent` - User-agent relationships
- `payments` - Payment records
- `invoices` - Invoice records

### Session Variables Required
- `$_SESSION['operator_user']` - Operator username
- `$_SESSION['operator_id']` - Operator ID

## Security Notes
- Agent operators can only access their own users/payments
- No way to bypass agent assignment through form manipulation
- Maintains audit trail with proper operator identification
- Backward compatible with existing permissions system

## Support
- Check `verify_agent_setup.php` for configuration issues
- Review database logs for SQL errors
- Test with `test_agent_auto_assignment.php` for functionality verification