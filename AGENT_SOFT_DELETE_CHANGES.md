# Agent Soft Delete Implementation

## Overview
Implemented soft delete functionality for agents and their associated operator accounts. Instead of permanently deleting records, they are marked as deleted using an `is_deleted` flag.

## Database Changes

### 1. Added is_deleted columns
- **File**: `add_is_deleted_columns.sql`
- **Changes**: Added `is_deleted TINYINT(1) NOT NULL DEFAULT 0` to both `agents` and `operators` tables

## Code Changes

### 1. Agent Deletion (Single)
- **File**: `app/operators/mng-agents-del.php`
- **Changes**: 
  - Changed DELETE to UPDATE with `is_deleted = 1`
  - Added logic to also mark associated operator as deleted
  - Updated queries to exclude deleted agents (`WHERE is_deleted = 0`)

### 2. Agent Deletion (Bulk)
- **File**: `app/operators/mng-agents-list.php`
- **Changes**:
  - Changed DELETE to UPDATE with `is_deleted = 1`
  - Added logic to mark associated operators as deleted
  - Updated queries to exclude deleted agents

### 3. Agent Listing
- **File**: `app/operators/mng-agents-list.php`
- **Changes**:
  - Updated count query: `SELECT COUNT(id) FROM agents WHERE is_deleted = 0`
  - Updated main query: `SELECT ... FROM agents WHERE is_deleted = 0`

### 4. Agent Editing
- **File**: `app/operators/mng-agents-edit.php`
- **Changes**:
  - Updated query to exclude deleted agents: `WHERE id = ? AND is_deleted = 0`

### 5. Agent Functions
- **File**: `app/operators/include/management/functions.php`
- **Changes**:
  - Updated `count_agents()` function to exclude deleted agents
  - Updated `get_user_agents()` function to exclude deleted agents

### 6. Agent Selection in Forms
- **File**: `app/operators/include/management/userbillinfo.php`
- **Changes**: Updated query to exclude deleted agents

- **File**: `app/operators/bill-payments-new.php`
- **Changes**: Updated query to exclude deleted agents

### 7. AJAX Agent Users
- **File**: `app/operators/library/ajax/get_agent_users.php`
- **Changes**: Updated queries to exclude deleted agents

### 8. Operators List
- **File**: `app/operators/config-operators-list.php`
- **Changes**: Updated queries to exclude deleted operators

## How It Works

1. **Agent Deletion**: When an agent is "deleted", the system:
   - Marks the agent record with `is_deleted = 1`
   - Finds the associated operator account (matched by company/email)
   - Marks the operator account with `is_deleted = 1`

2. **Data Filtering**: All queries that retrieve agents/operators now include `WHERE is_deleted = 0` to exclude deleted records

3. **User Experience**: 
   - Deleted agents no longer appear in lists
   - Deleted agents cannot be edited
   - Associated operator accounts are also hidden
   - Users assigned to deleted agents remain but lose agent association

## Benefits

1. **Data Preservation**: No data is permanently lost
2. **Referential Integrity**: Foreign key relationships remain intact
3. **Audit Trail**: Deleted records can be reviewed if needed
4. **Reversible**: Deletion can be undone by setting `is_deleted = 0`

## Testing

- Created `test_agent_deletion.php` to verify functionality
- All agent-related queries now properly exclude deleted records
- Both single and bulk deletion work with soft delete

## Files Modified

1. `add_is_deleted_columns.sql` (new)
2. `app/operators/mng-agents-del.php`
3. `app/operators/mng-agents-list.php`
4. `app/operators/mng-agents-edit.php`
5. `app/operators/include/management/functions.php`
6. `app/operators/include/management/userbillinfo.php`
7. `app/operators/bill-payments-new.php`
8. `app/operators/library/ajax/get_agent_users.php`
9. `app/operators/config-operators-list.php`
10. `test_agent_deletion.php` (new)
11. `AGENT_SOFT_DELETE_CHANGES.md` (new)