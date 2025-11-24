ALTER TABLE userbillinfo 
ADD COLUMN external_invoice_id VARCHAR(255) UNIQUE COMMENT 'External system (ERP) invoice ID for tracking' AFTER id,
ADD INDEX idx_external_invoice_id (external_invoice_id);
