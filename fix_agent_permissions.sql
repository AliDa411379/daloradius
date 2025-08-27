-- Fix agent permissions by adding missing ACL entries
-- This script adds the necessary ACL file entries and permissions for agent management

-- First, let's add the agent management files to operators_acl_files table
INSERT IGNORE INTO operators_acl_files (file, category, section) VALUES
('mng_agent_new', 'Management', 'Agents'),
('mng_agent_edit', 'Management', 'Agents'),
('mng_agent_del', 'Management', 'Agents'),
('mng_agent_list', 'Management', 'Agents'),
('mng_agents', 'Management', 'Agents'),
('mng_agents_edit', 'Management', 'Agents'),
('mng_agents_del', 'Management', 'Agents'),
('mng_agents_list', 'Management', 'Agents');

-- Now grant permissions to all existing operators for agent management
-- This will give all current operators access to agent management functions

-- Get the file IDs for agent management files
SET @mng_agent_new_id = (SELECT id FROM operators_acl_files WHERE file = 'mng_agent_new');
SET @mng_agent_edit_id = (SELECT id FROM operators_acl_files WHERE file = 'mng_agent_edit');
SET @mng_agent_del_id = (SELECT id FROM operators_acl_files WHERE file = 'mng_agent_del');
SET @mng_agent_list_id = (SELECT id FROM operators_acl_files WHERE file = 'mng_agent_list');
SET @mng_agents_id = (SELECT id FROM operators_acl_files WHERE file = 'mng_agents');
SET @mng_agents_edit_id = (SELECT id FROM operators_acl_files WHERE file = 'mng_agents_edit');
SET @mng_agents_del_id = (SELECT id FROM operators_acl_files WHERE file = 'mng_agents_del');
SET @mng_agents_list_id = (SELECT id FROM operators_acl_files WHERE file = 'mng_agents_list');

-- Grant permissions to all operators
INSERT IGNORE INTO operators_acl (operator_id, file, access)
SELECT o.id, 'mng_agent_new', 1 FROM operators o;

INSERT IGNORE INTO operators_acl (operator_id, file, access)
SELECT o.id, 'mng_agent_edit', 1 FROM operators o;

INSERT IGNORE INTO operators_acl (operator_id, file, access)
SELECT o.id, 'mng_agent_del', 1 FROM operators o;

INSERT IGNORE INTO operators_acl (operator_id, file, access)
SELECT o.id, 'mng_agent_list', 1 FROM operators o;

INSERT IGNORE INTO operators_acl (operator_id, file, access)
SELECT o.id, 'mng_agents', 1 FROM operators o;

INSERT IGNORE INTO operators_acl (operator_id, file, access)
SELECT o.id, 'mng_agents_edit', 1 FROM operators o;

INSERT IGNORE INTO operators_acl (operator_id, file, access)
SELECT o.id, 'mng_agents_del', 1 FROM operators o;

INSERT IGNORE INTO operators_acl (operator_id, file, access)
SELECT o.id, 'mng_agents_list', 1 FROM operators o;

-- Display results
SELECT 'Agent ACL files added:' as message;
SELECT * FROM operators_acl_files WHERE category = 'Management' AND section = 'Agents';

SELECT 'Total permissions granted:' as message;
SELECT COUNT(*) as total_permissions FROM operators_acl WHERE file LIKE 'mng_agent%';