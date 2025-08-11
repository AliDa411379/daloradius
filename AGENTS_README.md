# daloRADIUS Agents Management System

## Overview
The Agents Management System is a new feature added to daloRADIUS that allows administrators to manage sales agents or representatives within the system.

## Features
- **Create New Agents**: Add new agents with complete contact information
- **List Agents**: View all agents in a paginated table with sorting capabilities
- **Edit Agents**: Modify existing agent information
- **Delete Agents**: Remove agents from the system
- **Search and Filter**: Built-in search and filtering capabilities

## Database Structure
The agents are stored in the `agents` table with the following fields:
- `id` (Primary Key, Auto-increment)
- `name` (Agent Name - Required)
- `company` (Company Name)
- `phone` (Phone Number)
- `email` (Email Address)
- `address` (Street Address)
- `city` (City)
- `country` (Country)
- `creation_date` (Timestamp - Auto-generated)

## Files Added
- `config-agents.php` - Main agents configuration page
- `config-agents-new.php` - Create new agent form
- `config-agents-list.php` - List all agents with pagination
- `config-agents-edit.php` - Edit existing agent
- `config-agents-del.php` - Delete agent confirmation
- `include/menu/sidebar/config/agents.php` - Sidebar menu configuration

## Navigation
The Agents system is accessible through:
1. **Main Menu**: Config → Agents
2. **Direct URLs**:
   - `/app/operators/config-agents.php` - Main page
   - `/app/operators/config-agents-new.php` - New agent
   - `/app/operators/config-agents-list.php` - List agents

## Language Support
All text strings have been added to the English language file (`lang/en.php`) with proper translations for:
- Button labels
- Field names
- Page titles
- Help text
- Tooltips

## Integration
The agents system follows daloRADIUS conventions:
- Uses the same authentication and permission system
- Follows the same UI/UX patterns
- Integrates with the existing menu system
- Uses the same database connection methods
- Implements CSRF protection
- Includes proper logging

## Usage
1. **Access the system**: Navigate to Config → Agents
2. **Create an agent**: Click "New Agent" and fill in the form
3. **View agents**: Click "List Agents" to see all agents
4. **Edit an agent**: Click the edit icon next to any agent in the list
5. **Delete an agent**: Click the delete icon and confirm

## Security
- CSRF token protection on all forms
- Input validation and sanitization
- SQL injection prevention using prepared statements
- XSS protection with proper HTML escaping

## Future Enhancements
Potential future features could include:
- Agent performance tracking
- Commission management
- Customer assignment to agents
- Agent reporting and analytics
- Bulk agent operations
- Agent photo uploads
- Advanced search filters