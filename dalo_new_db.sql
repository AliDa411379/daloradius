-- --------------------------------------------------------
-- Host:                         172.30.16.200
-- Server version:               10.11.11-MariaDB-0ubuntu0.24.04.2 - Ubuntu 24.04
-- Server OS:                    debian-linux-gnu
-- HeidiSQL Version:             12.8.0.6908
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Dumping database structure for radius
CREATE DATABASE IF NOT EXISTS `radius` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;
USE `radius`;

-- Dumping structure for table radius.agents
CREATE TABLE IF NOT EXISTS `agents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL,
  `company` varchar(200) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` varchar(200) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `creation_date` datetime DEFAULT current_timestamp(),
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 if agent is deleted, 0 otherwise',
  `operator_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_operator_id` (`operator_id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table radius.agent_payments
CREATE TABLE IF NOT EXISTS `agent_payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `agent_id` int(10) unsigned NOT NULL COMMENT 'Reference to agents.id',
  `user_id` int(8) unsigned NOT NULL COMMENT 'Reference to userbillinfo.id',
  `username` varchar(128) NOT NULL,
  `payment_type` enum('balance_topup','bundle_purchase','invoice_payment') NOT NULL,
  `amount` decimal(10,2) NOT NULL COMMENT 'Amount agent paid',
  `payment_date` datetime NOT NULL,
  `payment_method` varchar(50) DEFAULT 'cash' COMMENT 'cash, bank_transfer, card, etc',
  `reference_type` varchar(50) DEFAULT NULL COMMENT 'bundle, invoice, topup',
  `reference_id` bigint(20) DEFAULT NULL COMMENT 'ID of related record (bundle_id, invoice_id, etc)',
  `user_balance_before` decimal(10,2) DEFAULT NULL,
  `user_balance_after` decimal(10,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` varchar(128) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_agent_id` (`agent_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_username` (`username`),
  KEY `idx_payment_date` (`payment_date`),
  KEY `idx_payment_type` (`payment_type`),
  KEY `idx_reference` (`reference_type`,`reference_id`)
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Agent payment transactions for users (no agent balance stored)';

-- Data exporting was unselected.

-- Dumping structure for table radius.batch_history
CREATE TABLE IF NOT EXISTS `batch_history` (
  `id` int(32) NOT NULL AUTO_INCREMENT,
  `batch_name` varchar(64) DEFAULT NULL COMMENT 'an identifier name of the batch instance',
  `batch_description` varchar(256) DEFAULT NULL COMMENT 'general description of the entry',
  `hotspot_id` int(32) DEFAULT 0 COMMENT 'the hotspot business id associated with this batch instance',
  `batch_status` varchar(128) NOT NULL DEFAULT 'Pending' COMMENT 'the batch status',
  `creationdate` datetime DEFAULT '0000-00-00 00:00:00',
  `creationby` varchar(128) DEFAULT NULL,
  `updatedate` datetime DEFAULT '0000-00-00 00:00:00',
  `updateby` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `batch_name` (`batch_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table radius.billing_history
CREATE TABLE IF NOT EXISTS `billing_history` (
  `id` int(8) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(128) DEFAULT NULL,
  `planId` int(32) DEFAULT NULL,
  `billAmount` varchar(200) DEFAULT NULL,
  `billAction` varchar(128) NOT NULL DEFAULT 'Unavailable',
  `billPerformer` varchar(200) DEFAULT NULL,
  `billReason` varchar(200) DEFAULT NULL,
  `paymentmethod` varchar(200) DEFAULT NULL,
  `cash` varchar(200) DEFAULT NULL,
  `creditcardname` varchar(200) DEFAULT NULL,
  `creditcardnumber` varchar(200) DEFAULT NULL,
  `creditcardverification` varchar(200) DEFAULT NULL,
  `creditcardtype` varchar(200) DEFAULT NULL,
  `creditcardexp` varchar(200) DEFAULT NULL,
  `coupon` varchar(200) DEFAULT NULL,
  `discount` varchar(200) DEFAULT NULL,
  `notes` varchar(200) DEFAULT NULL,
  `creationdate` datetime DEFAULT '0000-00-00 00:00:00',
  `creationby` varchar(128) DEFAULT NULL,
  `updatedate` datetime DEFAULT '0000-00-00 00:00:00',
  `updateby` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=205 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table radius.billing_merchant
CREATE TABLE IF NOT EXISTS `billing_merchant` (
  `id` int(8) NOT NULL AUTO_INCREMENT,
  `username` varchar(128) NOT NULL DEFAULT '',
  `password` varchar(128) NOT NULL DEFAULT '',
  `mac` varchar(200) NOT NULL DEFAULT '',
  `pin` varchar(200) NOT NULL DEFAULT '',
  `txnId` varchar(200) NOT NULL DEFAULT '',
  `planName` varchar(128) NOT NULL DEFAULT '',
  `planId` int(32) NOT NULL,
  `quantity` varchar(200) NOT NULL DEFAULT '',
  `business_email` varchar(200) NOT NULL DEFAULT '',
  `business_id` varchar(200) NOT NULL DEFAULT '',
  `txn_type` varchar(200) NOT NULL DEFAULT '',
  `txn_id` varchar(200) NOT NULL DEFAULT '',
  `payment_type` varchar(200) NOT NULL DEFAULT '',
  `payment_tax` varchar(200) NOT NULL DEFAULT '',
  `payment_cost` varchar(200) NOT NULL DEFAULT '',
  `payment_fee` varchar(200) NOT NULL DEFAULT '',
  `payment_total` varchar(200) NOT NULL DEFAULT '',
  `payment_currency` varchar(200) NOT NULL DEFAULT '',
  `first_name` varchar(200) NOT NULL DEFAULT '',
  `last_name` varchar(200) NOT NULL DEFAULT '',
  `payer_email` varchar(200) NOT NULL DEFAULT '',
  `payer_address_name` varchar(200) NOT NULL DEFAULT '',
  `payer_address_street` varchar(200) NOT NULL DEFAULT '',
  `payer_address_country` varchar(200) NOT NULL DEFAULT '',
  `payer_address_country_code` varchar(200) NOT NULL DEFAULT '',
  `payer_address_city` varchar(200) NOT NULL DEFAULT '',
  `payer_address_state` varchar(200) NOT NULL DEFAULT '',
  `payer_address_zip` varchar(200) NOT NULL DEFAULT '',
  `payment_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `payment_status` varchar(200) NOT NULL DEFAULT '',
  `pending_reason` varchar(200) NOT NULL DEFAULT '',
  `reason_code` varchar(200) NOT NULL DEFAULT '',
  `receipt_ID` varchar(200) NOT NULL DEFAULT '',
  `payment_address_status` varchar(200) NOT NULL DEFAULT '',
  `vendor_type` varchar(200) NOT NULL DEFAULT '',
  `payer_status` varchar(200) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table radius.billing_paypal
CREATE TABLE IF NOT EXISTS `billing_paypal` (
  `id` int(8) NOT NULL AUTO_INCREMENT,
  `username` varchar(128) DEFAULT NULL,
  `password` varchar(128) DEFAULT NULL,
  `mac` varchar(200) DEFAULT NULL,
  `pin` varchar(200) DEFAULT NULL,
  `txnId` varchar(200) DEFAULT NULL,
  `planName` varchar(128) DEFAULT NULL,
  `planId` varchar(200) DEFAULT NULL,
  `quantity` varchar(200) DEFAULT NULL,
  `receiver_email` varchar(200) DEFAULT NULL,
  `business` varchar(200) DEFAULT NULL,
  `tax` varchar(200) DEFAULT NULL,
  `mc_gross` varchar(200) DEFAULT NULL,
  `mc_fee` varchar(200) DEFAULT NULL,
  `mc_currency` varchar(200) DEFAULT NULL,
  `first_name` varchar(200) DEFAULT NULL,
  `last_name` varchar(200) DEFAULT NULL,
  `payer_email` varchar(200) DEFAULT NULL,
  `address_name` varchar(200) DEFAULT NULL,
  `address_street` varchar(200) DEFAULT NULL,
  `address_country` varchar(200) DEFAULT NULL,
  `address_country_code` varchar(200) DEFAULT NULL,
  `address_city` varchar(200) DEFAULT NULL,
  `address_state` varchar(200) DEFAULT NULL,
  `address_zip` varchar(200) DEFAULT NULL,
  `payment_date` datetime DEFAULT NULL,
  `payment_status` varchar(200) DEFAULT NULL,
  `payment_address_status` varchar(200) DEFAULT NULL,
  `payer_status` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table radius.billing_plans
CREATE TABLE IF NOT EXISTS `billing_plans` (
  `id` int(8) NOT NULL AUTO_INCREMENT,
  `planName` varchar(128) DEFAULT NULL,
  `planId` varchar(128) DEFAULT NULL,
  `planType` varchar(128) DEFAULT NULL,
  `planTimeBank` varchar(128) DEFAULT NULL,
  `planTimeType` varchar(128) DEFAULT NULL,
  `planTimeRefillCost` varchar(128) DEFAULT NULL,
  `planBandwidthUp` varchar(128) DEFAULT NULL,
  `planBandwidthDown` varchar(128) DEFAULT NULL,
  `planTrafficTotal` varchar(128) DEFAULT NULL,
  `planTrafficUp` varchar(128) DEFAULT NULL,
  `planTrafficDown` varchar(128) DEFAULT NULL,
  `planTrafficRefillCost` varchar(128) DEFAULT NULL,
  `planRecurring` varchar(128) DEFAULT NULL,
  `planRecurringPeriod` varchar(128) DEFAULT NULL,
  `planRecurringBillingSchedule` varchar(128) NOT NULL DEFAULT 'Fixed',
  `planCost` varchar(128) DEFAULT NULL,
  `planSetupCost` varchar(128) DEFAULT NULL,
  `planTax` varchar(128) DEFAULT NULL,
  `planCurrency` varchar(128) DEFAULT NULL,
  `planGroup` varchar(128) DEFAULT NULL,
  `planActive` varchar(32) NOT NULL DEFAULT 'yes',
  `subscription_type_id` int(10) unsigned DEFAULT 1 COMMENT 'Link to subscription_types (1=monthly, 2=prepaid)',
  `is_bundle` tinyint(1) DEFAULT 0 COMMENT '1 for prepaid bundles, 0 for monthly plans',
  `bundle_validity_days` int(11) DEFAULT NULL COMMENT 'Validity period for bundles in days',
  `bundle_validity_hours` int(11) DEFAULT NULL COMMENT 'Additional hours for bundle validity',
  `auto_renew` tinyint(1) DEFAULT 0 COMMENT 'Auto-renew from balance if available (future feature)',
  `creationdate` datetime DEFAULT '0000-00-00 00:00:00',
  `creationby` varchar(128) DEFAULT NULL,
  `updatedate` datetime DEFAULT '0000-00-00 00:00:00',
  `updateby` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `planName` (`planName`),
  KEY `idx_subscription_type` (`subscription_type_id`),
  KEY `idx_is_bundle` (`is_bundle`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table radius.billing_plans_profiles
CREATE TABLE IF NOT EXISTS `billing_plans_profiles` (
  `id` int(32) NOT NULL AUTO_INCREMENT,
  `plan_name` varchar(128) NOT NULL COMMENT 'the name of the plan',
  `profile_name` varchar(256) DEFAULT NULL COMMENT 'the profile/group name',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=133 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table radius.billing_rates
CREATE TABLE IF NOT EXISTS `billing_rates` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `rateName` varchar(128) NOT NULL DEFAULT '',
  `rateType` varchar(128) NOT NULL DEFAULT '',
  `rateCost` int(32) NOT NULL DEFAULT 0,
  `creationdate` datetime DEFAULT '0000-00-00 00:00:00',
  `creationby` varchar(128) DEFAULT NULL,
  `updatedate` datetime DEFAULT '0000-00-00 00:00:00',
  `updateby` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rateName` (`rateName`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table radius.cui
CREATE TABLE IF NOT EXISTS `cui` (
  `clientipaddress` varchar(46) NOT NULL DEFAULT '',
  `callingstationid` varchar(50) NOT NULL DEFAULT '',
  `username` varchar(64) NOT NULL DEFAULT '',
  `cui` varchar(32) NOT NULL DEFAULT '',
  `creationdate` timestamp NOT NULL DEFAULT current_timestamp(),
  `lastaccounting` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`username`,`clientipaddress`,`callingstationid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table radius.dictionary
CREATE TABLE IF NOT EXISTS `dictionary` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `Type` varchar(30) DEFAULT NULL,
  `Attribute` varchar(64) DEFAULT NULL,
  `Value` varchar(64) DEFAULT NULL,
  `Format` varchar(20) DEFAULT NULL,
  `Vendor` varchar(32) DEFAULT NULL,
  `RecommendedOP` varchar(32) DEFAULT NULL,
  `RecommendedTable` varchar(32) DEFAULT NULL,
  `RecommendedHelper` varchar(32) DEFAULT NULL,
  `RecommendedTooltip` varchar(512) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9724 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table radius.hotspots
CREATE TABLE IF NOT EXISTS `hotspots` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `name` varchar(200) DEFAULT NULL,
  `mac` varchar(200) DEFAULT NULL,
  `geocode` varchar(200) DEFAULT NULL,
  `owner` varchar(200) DEFAULT NULL,
  `email_owner` varchar(200) DEFAULT NULL,
  `manager` varchar(200) DEFAULT NULL,
  `email_manager` varchar(200) DEFAULT NULL,
  `address` varchar(200) DEFAULT NULL,
  `company` varchar(200) DEFAULT NULL,
  `phone1` varchar(200) DEFAULT NULL,
  `phone2` varchar(200) DEFAULT NULL,
  `type` varchar(200) DEFAULT NULL,
  `companywebsite` varchar(200) DEFAULT NULL,
  `companyemail` varchar(200) DEFAULT NULL,
  `companycontact` varchar(200) DEFAULT NULL,
  `companyphone` varchar(200) DEFAULT NULL,
  `creationdate` datetime DEFAULT '0000-00-00 00:00:00',
  `creationby` varchar(128) DEFAULT NULL,
  `updatedate` datetime DEFAULT '0000-00-00 00:00:00',
  `updateby` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `name` (`name`),
  KEY `mac` (`mac`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table radius.invoice
CREATE TABLE IF NOT EXISTS `invoice` (
  `id` int(32) NOT NULL AUTO_INCREMENT,
  `user_id` int(32) DEFAULT NULL COMMENT 'user id of the userbillinfo table',
  `batch_id` int(32) DEFAULT NULL COMMENT 'batch id of the batch_history table',
  `date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `status_id` int(10) NOT NULL DEFAULT 1 COMMENT 'the status of the invoice from invoice_status',
  `type_id` int(10) NOT NULL DEFAULT 1 COMMENT 'the type of the invoice from invoice_type',
  `notes` varchar(128) NOT NULL COMMENT 'general notes/description',
  `creationdate` datetime DEFAULT '0000-00-00 00:00:00',
  `creationby` varchar(128) DEFAULT NULL,
  `updatedate` datetime DEFAULT '0000-00-00 00:00:00',
  `updateby` varchar(128) DEFAULT NULL,
  `due_date` date DEFAULT NULL COMMENT 'Invoice due date (4th of month)',
  PRIMARY KEY (`id`),
  KEY `idx_due_date` (`due_date`)
) ENGINE=InnoDB AUTO_INCREMENT=152 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table radius.invoice_items
CREATE TABLE IF NOT EXISTS `invoice_items` (
  `id` int(32) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(32) NOT NULL COMMENT 'invoice id of the invoices table',
  `plan_id` int(32) DEFAULT NULL COMMENT 'the plan_id of the billing_plans table',
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'the amount cost of an item',
  `tax_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'the tax amount for an item',
  `total` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'the total amount',
  `notes` varchar(128) NOT NULL COMMENT 'general notes/description',
  `creationdate` datetime DEFAULT '0000-00-00 00:00:00',
  `creationby` varchar(128) DEFAULT NULL,
  `updatedate` datetime DEFAULT '0000-00-00 00:00:00',
  `updateby` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `chk_invoice_amount_limit` CHECK (`amount` <= 300000.00)
) ENGINE=InnoDB AUTO_INCREMENT=155 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table radius.invoice_status
CREATE TABLE IF NOT EXISTS `invoice_status` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `value` varchar(32) NOT NULL DEFAULT '' COMMENT 'status value',
  `notes` varchar(128) NOT NULL COMMENT 'general notes/description',
  `creationdate` datetime DEFAULT '0000-00-00 00:00:00',
  `creationby` varchar(128) DEFAULT NULL,
  `updatedate` datetime DEFAULT '0000-00-00 00:00:00',
  `updateby` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table radius.invoice_type
CREATE TABLE IF NOT EXISTS `invoice_type` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `value` varchar(32) NOT NULL DEFAULT '' COMMENT 'type value',
  `notes` varchar(128) NOT NULL COMMENT 'general notes/description',
  `creationdate` datetime DEFAULT '0000-00-00 00:00:00',
  `creationby` varchar(128) DEFAULT NULL,
  `updatedate` datetime DEFAULT '0000-00-00 00:00:00',
  `updateby` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table radius.messages
CREATE TABLE IF NOT EXISTS `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` enum('login','support','dashboard') NOT NULL,
  `content` longtext NOT NULL,
  `created_on` datetime DEFAULT NULL,
  `created_by` varchar(32) DEFAULT NULL,
  `modified_on` datetime DEFAULT NULL,
  `modified_by` varchar(32) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table radius.monthly_subscription_config
CREATE TABLE IF NOT EXISTS `monthly_subscription_config` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_generation_day` int(2) NOT NULL DEFAULT 26 COMMENT 'Day of month (1-28)',
  `invoice_due_days` int(3) NOT NULL DEFAULT 8 COMMENT 'Days until invoice is due (26th + 8 = 4th next month)',
  `grace_period_days` int(3) NOT NULL DEFAULT 7 COMMENT 'Additional days before suspension after due date',
  `auto_suspend_on_overdue` tinyint(1) DEFAULT 1 COMMENT 'Auto-suspend after grace period',
  `allow_balance_payment` tinyint(1) DEFAULT 1 COMMENT 'Allow payment from user balance',
  `auto_pay_from_balance` tinyint(1) DEFAULT 0 COMMENT 'Auto-deduct from balance when invoice created',
  `send_invoice_email` tinyint(1) DEFAULT 1,
  `send_reminder_email` tinyint(1) DEFAULT 1,
  `reminder_days_before_due` int(2) DEFAULT 3,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table radius.nas
CREATE TABLE IF NOT EXISTS `nas` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `nasname` varchar(128) NOT NULL,
  `shortname` varchar(32) DEFAULT NULL,
  `type` varchar(30) DEFAULT 'other',
  `ports` int(5) DEFAULT NULL,
  `secret` varchar(60) NOT NULL DEFAULT 'secret',
  `server` varchar(64) DEFAULT NULL,
  `community` varchar(50) DEFAULT NULL,
  `description` varchar(200) DEFAULT 'RADIUS Client',
  PRIMARY KEY (`id`),
  KEY `nasname` (`nasname`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table radius.nat
CREATE TABLE IF NOT EXISTS `nat` (
  `NO` int(11) DEFAULT NULL,
  `Private IP` varchar(15) DEFAULT NULL,
  `Public IP` varchar(15) DEFAULT NULL,
  `start port` int(11) DEFAULT NULL,
  `end port` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table radius.node
CREATE TABLE IF NOT EXISTS `node` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `time` datetime NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT 'Time of last checkin',
  `netid` int(11) NOT NULL DEFAULT 1,
  `name` varchar(100) NOT NULL DEFAULT 'Unknown',
  `description` varchar(100) DEFAULT '',
  `type` varchar(100) DEFAULT NULL COMMENT 'Node type: point to point, sector, nas, etc.',
  `latitude` varchar(20) DEFAULT '',
  `longitude` varchar(20) DEFAULT '',
  `owner_name` varchar(50) DEFAULT '',
  `owner_email` varchar(50) DEFAULT '',
  `owner_phone` varchar(25) DEFAULT '',
  `owner_address` varchar(100) DEFAULT '',
  `approval_status` varchar(1) DEFAULT 'P',
  `ip` varchar(20) NOT NULL COMMENT 'ROBIN',
  `mac` varchar(20) NOT NULL COMMENT 'ROBIN',
  `uptime` varchar(100) NOT NULL COMMENT 'ROBIN',
  `robin` varchar(20) DEFAULT '',
  `batman` varchar(20) DEFAULT '',
  `memfree` varchar(20) DEFAULT '',
  `nbs` mediumtext DEFAULT NULL,
  `gateway` varchar(20) DEFAULT '',
  `gw-qual` varchar(20) DEFAULT '',
  `routes` mediumtext DEFAULT NULL,
  `users` int(11) DEFAULT 0,
  `kbdown` varchar(20) DEFAULT '0',
  `kbup` varchar(20) DEFAULT '0',
  `hops` varchar(3) DEFAULT '0',
  `rank` varchar(3) DEFAULT '',
  `ssid` varchar(20) DEFAULT '',
  `pssid` varchar(20) DEFAULT '',
  `gateway_bit` tinyint(1) DEFAULT 0,
  `memlow` varchar(20) DEFAULT '',
  `usershi` char(3) DEFAULT '0',
  `cpu` float NOT NULL DEFAULT 0,
  `wan_iface` varchar(128) DEFAULT NULL,
  `wan_ip` varchar(128) DEFAULT NULL,
  `wan_mac` varchar(128) DEFAULT NULL,
  `wan_gateway` varchar(128) DEFAULT NULL,
  `wifi_iface` varchar(128) DEFAULT NULL,
  `wifi_ip` varchar(128) DEFAULT NULL,
  `wifi_mac` varchar(128) DEFAULT NULL,
  `wifi_ssid` varchar(128) DEFAULT NULL,
  `wifi_key` varchar(128) DEFAULT NULL,
  `wifi_channel` varchar(128) DEFAULT NULL,
  `lan_iface` varchar(128) DEFAULT NULL,
  `lan_mac` varchar(128) DEFAULT NULL,
  `lan_ip` varchar(128) DEFAULT NULL,
  `wan_bup` varchar(128) DEFAULT NULL,
  `wan_bdown` varchar(128) DEFAULT NULL,
  `firmware` varchar(128) DEFAULT NULL,
  `firmware_revision` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mac` (`mac`)
) ENGINE=InnoDB AUTO_INCREMENT=65591 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='node database';

-- Data exporting was unselected.

-- Dumping structure for table radius.operators
CREATE TABLE IF NOT EXISTS `operators` (
  `id` int(32) NOT NULL AUTO_INCREMENT,
  `username` varchar(32) NOT NULL,
  `password` varchar(95) NOT NULL,
  `firstname` varchar(32) NOT NULL,
  `lastname` varchar(32) NOT NULL,
  `title` varchar(32) NOT NULL,
  `department` varchar(32) NOT NULL,
  `company` varchar(32) NOT NULL,
  `phone1` varchar(32) NOT NULL,
  `phone2` varchar(32) NOT NULL,
  `email1` varchar(32) NOT NULL,
  `email2` varchar(32) NOT NULL,
  `messenger1` varchar(32) NOT NULL,
  `messenger2` varchar(32) NOT NULL,
  `notes` varchar(128) NOT NULL,
  `lastlogin` datetime DEFAULT '0000-00-00 00:00:00',
  `creationdate` datetime DEFAULT '0000-00-00 00:00:00',
  `creationby` varchar(128) DEFAULT NULL,
  `updatedate` datetime DEFAULT '0000-00-00 00:00:00',
  `updateby` varchar(128) DEFAULT NULL,
  `is_agent` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Whether this operator is an agent (1) or admin (0)',
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Soft delete flag',
  PRIMARY KEY (`id`),
  KEY `username` (`username`),
  KEY `idx_is_agent` (`is_agent`),
  KEY `idx_is_deleted` (`is_deleted`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table radius.operators_acl
CREATE TABLE IF NOT EXISTS `operators_acl` (
  `id` int(32) NOT NULL AUTO_INCREMENT,
  `operator_id` int(32) NOT NULL,
  `file` varchar(128) NOT NULL,
  `access` tinyint(8) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=956 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table radius.operators_acl_files
CREATE TABLE IF NOT EXISTS `operators_acl_files` (
  `id` int(32) NOT NULL AUTO_INCREMENT,
  `file` varchar(128) NOT NULL,
  `category` varchar(128) NOT NULL,
  `section` varchar(128) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=228 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table radius.payment
CREATE TABLE IF NOT EXISTS `payment` (
  `id` int(32) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(32) NOT NULL COMMENT 'invoice id of the invoices table',
  `amount` decimal(10,2) NOT NULL COMMENT 'the amount paid',
  `date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `type_id` int(10) NOT NULL DEFAULT 1 COMMENT 'the type of the payment from payment_type',
  `notes` varchar(128) NOT NULL COMMENT 'general notes/description',
  `creationdate` datetime DEFAULT '0000-00-00 00:00:00',
  `creationby` varchar(128) DEFAULT NULL,
  `updatedate` datetime DEFAULT '0000-00-00 00:00:00',
  `updateby` varchar(128) DEFAULT NULL,
  `from_balance` tinyint(1) DEFAULT 0 COMMENT '1 if paid from balance, 0 otherwise',
  PRIMARY KEY (`id`),
  KEY `idx_from_balance` (`from_balance`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table radius.payment_refunds
CREATE TABLE IF NOT EXISTS `payment_refunds` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `original_payment_type` enum('agent_payment','invoice_payment','balance_topup') NOT NULL,
  `original_payment_id` bigint(20) unsigned NOT NULL,
  `user_id` int(8) unsigned NOT NULL,
  `username` varchar(128) NOT NULL,
  `refund_amount` decimal(10,2) NOT NULL,
  `original_amount` decimal(10,2) NOT NULL,
  `is_partial` tinyint(1) DEFAULT 0,
  `refund_reason` text DEFAULT NULL,
  `user_balance_before` decimal(10,2) DEFAULT NULL,
  `user_balance_after` decimal(10,2) DEFAULT NULL,
  `refund_date` datetime NOT NULL,
  `performed_by` varchar(128) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_original_payment` (`original_payment_type`,`original_payment_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_username` (`username`),
  KEY `idx_refund_date` (`refund_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Payment refund tracking for audit trail';

-- Data exporting was unselected.

-- Dumping structure for table radius.payment_type
CREATE TABLE IF NOT EXISTS `payment_type` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `value` varchar(32) NOT NULL DEFAULT '' COMMENT 'type value',
  `notes` varchar(128) NOT NULL COMMENT 'general notes/description',
  `creationdate` datetime DEFAULT '0000-00-00 00:00:00',
  `creationby` varchar(128) DEFAULT NULL,
  `updatedate` datetime DEFAULT '0000-00-00 00:00:00',
  `updateby` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table radius.proxys
CREATE TABLE IF NOT EXISTS `proxys` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `proxyname` varchar(128) DEFAULT NULL,
  `retry_delay` int(8) DEFAULT NULL,
  `retry_count` int(8) DEFAULT NULL,
  `dead_time` int(8) DEFAULT NULL,
  `default_fallback` int(8) DEFAULT NULL,
  `creationdate` datetime DEFAULT NULL,
  `creationby` varchar(128) DEFAULT NULL,
  `updatedate` datetime DEFAULT NULL,
  `updateby` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table radius.radacct
CREATE TABLE IF NOT EXISTS `radacct` (
  `radacctid` bigint(21) NOT NULL AUTO_INCREMENT,
  `acctsessionid` varchar(64) NOT NULL DEFAULT '',
  `acctuniqueid` varchar(32) NOT NULL DEFAULT '',
  `username` varchar(64) NOT NULL DEFAULT '',
  `realm` varchar(64) DEFAULT '',
  `nasipaddress` varchar(15) NOT NULL DEFAULT '',
  `nasportid` varchar(32) DEFAULT NULL,
  `nasporttype` varchar(32) DEFAULT NULL,
  `acctstarttime` datetime DEFAULT NULL,
  `acctupdatetime` datetime DEFAULT NULL,
  `acctstoptime` datetime DEFAULT NULL,
  `acctinterval` int(12) DEFAULT NULL,
  `acctsessiontime` int(12) unsigned DEFAULT NULL,
  `acctauthentic` varchar(32) DEFAULT NULL,
  `connectinfo_start` varchar(50) DEFAULT NULL,
  `connectinfo_stop` varchar(50) DEFAULT NULL,
  `acctinputoctets` bigint(20) DEFAULT NULL,
  `acctoutputoctets` bigint(20) DEFAULT NULL,
  `calledstationid` varchar(50) NOT NULL DEFAULT '',
  `callingstationid` varchar(50) NOT NULL DEFAULT '',
  `acctterminatecause` varchar(32) NOT NULL DEFAULT '',
  `servicetype` varchar(32) DEFAULT NULL,
  `framedprotocol` varchar(32) DEFAULT NULL,
  `framedipaddress` varchar(15) NOT NULL DEFAULT '',
  `public_ip_framed` varchar(15) DEFAULT NULL,
  `framedipv6address` varchar(45) NOT NULL DEFAULT '',
  `framedipv6prefix` varchar(45) NOT NULL DEFAULT '',
  `framedinterfaceid` varchar(44) NOT NULL DEFAULT '',
  `delegatedipv6prefix` varchar(45) NOT NULL DEFAULT '',
  `groupname` varchar(64) NOT NULL DEFAULT '',
  PRIMARY KEY (`radacctid`),
  UNIQUE KEY `acctuniqueid` (`acctuniqueid`),
  KEY `username` (`username`),
  KEY `framedipaddress` (`framedipaddress`),
  KEY `framedipv6address` (`framedipv6address`),
  KEY `framedipv6prefix` (`framedipv6prefix`),
  KEY `framedinterfaceid` (`framedinterfaceid`),
  KEY `delegatedipv6prefix` (`delegatedipv6prefix`),
  KEY `acctsessionid` (`acctsessionid`),
  KEY `acctsessiontime` (`acctsessiontime`),
  KEY `acctstarttime` (`acctstarttime`),
  KEY `acctinterval` (`acctinterval`),
  KEY `acctstoptime` (`acctstoptime`),
  KEY `nasipaddress` (`nasipaddress`)
) ENGINE=InnoDB AUTO_INCREMENT=9119 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table radius.radacct_billing
CREATE TABLE IF NOT EXISTS `radacct_billing` (
  `radacctid` bigint(21) NOT NULL AUTO_INCREMENT,
  `acctsessionid` varchar(64) NOT NULL DEFAULT '',
  `acctuniqueid` varchar(32) NOT NULL DEFAULT '',
  `username` varchar(64) NOT NULL DEFAULT '',
  `groupname` varchar(64) NOT NULL DEFAULT '',
  `realm` varchar(64) DEFAULT '',
  `nasipaddress` varchar(15) NOT NULL DEFAULT '',
  `nasportid` varchar(15) DEFAULT NULL,
  `nasporttype` varchar(32) DEFAULT NULL,
  `acctstarttime` datetime DEFAULT NULL,
  `acctupdatetime` datetime DEFAULT NULL,
  `acctstoptime` datetime DEFAULT NULL,
  `acctinterval` int(12) DEFAULT NULL,
  `acctsessiontime` int(12) unsigned DEFAULT NULL,
  `acctauthentic` varchar(32) DEFAULT NULL,
  `connectinfo_start` varchar(50) DEFAULT NULL,
  `connectinfo_stop` varchar(50) DEFAULT NULL,
  `acctinputoctets` bigint(20) DEFAULT NULL,
  `acctoutputoctets` bigint(20) DEFAULT NULL,
  `calledstationid` varchar(50) NOT NULL DEFAULT '',
  `callingstationid` varchar(50) NOT NULL DEFAULT '',
  `acctterminatecause` varchar(32) NOT NULL DEFAULT '',
  `servicetype` varchar(32) DEFAULT NULL,
  `framedprotocol` varchar(32) DEFAULT NULL,
  `framedipaddress` varchar(15) NOT NULL DEFAULT '',
  `processed_for_billing` tinyint(1) DEFAULT 0,
  `billing_processed_date` datetime DEFAULT NULL,
  `traffic_mb` decimal(15,2) DEFAULT NULL,
  `session_minutes` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`radacctid`),
  UNIQUE KEY `acctuniqueid` (`acctuniqueid`),
  KEY `username` (`username`),
  KEY `framedipaddress` (`framedipaddress`),
  KEY `acctsessionid` (`acctsessionid`),
  KEY `acctstarttime` (`acctstarttime`),
  KEY `acctstoptime` (`acctstoptime`),
  KEY `nasipaddress` (`nasipaddress`),
  KEY `processed_for_billing` (`processed_for_billing`),
  KEY `billing_processed_date` (`billing_processed_date`)
) ENGINE=InnoDB AUTO_INCREMENT=9119 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Data exporting was unselected.

-- Dumping structure for table radius.radcheck
CREATE TABLE IF NOT EXISTS `radcheck` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(64) NOT NULL DEFAULT '',
  `attribute` varchar(64) NOT NULL DEFAULT '',
  `op` char(2) NOT NULL DEFAULT '==',
  `value` varchar(253) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `username` (`username`(32))
) ENGINE=InnoDB AUTO_INCREMENT=407 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table radius.radgroupcheck
CREATE TABLE IF NOT EXISTS `radgroupcheck` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `groupname` varchar(64) NOT NULL DEFAULT '',
  `attribute` varchar(64) NOT NULL DEFAULT '',
  `op` char(2) NOT NULL DEFAULT '==',
  `value` varchar(253) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `groupname` (`groupname`(32))
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table radius.radgroupreply
CREATE TABLE IF NOT EXISTS `radgroupreply` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `groupname` varchar(64) NOT NULL DEFAULT '',
  `attribute` varchar(64) NOT NULL DEFAULT '',
  `op` char(2) NOT NULL DEFAULT '=',
  `value` varchar(253) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `groupname` (`groupname`(32))
) ENGINE=InnoDB AUTO_INCREMENT=91 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table radius.radhuntgroup
CREATE TABLE IF NOT EXISTS `radhuntgroup` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `groupname` varchar(64) NOT NULL DEFAULT '',
  `nasipaddress` varchar(15) NOT NULL DEFAULT '',
  `nasportid` varchar(15) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `nasipaddress` (`nasipaddress`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table radius.radippool
CREATE TABLE IF NOT EXISTS `radippool` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `pool_name` varchar(30) NOT NULL,
  `framedipaddress` varchar(15) NOT NULL DEFAULT '',
  `nasipaddress` varchar(15) NOT NULL DEFAULT '',
  `calledstationid` varchar(30) NOT NULL DEFAULT '',
  `callingstationid` varchar(30) NOT NULL DEFAULT '',
  `expiry_time` datetime NOT NULL DEFAULT current_timestamp(),
  `username` varchar(64) NOT NULL DEFAULT '',
  `pool_key` varchar(30) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `radippool_poolname_expire` (`pool_name`,`expiry_time`),
  KEY `framedipaddress` (`framedipaddress`),
  KEY `radippool_nasip_poolkey_ipaddress` (`nasipaddress`,`pool_key`,`framedipaddress`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table radius.radpostauth
CREATE TABLE IF NOT EXISTS `radpostauth` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(64) NOT NULL DEFAULT '',
  `pass` varchar(64) NOT NULL DEFAULT '',
  `reply` varchar(32) NOT NULL DEFAULT '',
  `authdate` timestamp(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `username` (`username`(32))
) ENGINE=InnoDB AUTO_INCREMENT=8859 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table radius.radreply
CREATE TABLE IF NOT EXISTS `radreply` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(64) NOT NULL DEFAULT '',
  `attribute` varchar(64) NOT NULL DEFAULT '',
  `op` char(2) NOT NULL DEFAULT '=',
  `value` varchar(253) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `username` (`username`(32))
) ENGINE=InnoDB AUTO_INCREMENT=86 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table radius.radusergroup
CREATE TABLE IF NOT EXISTS `radusergroup` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(64) NOT NULL DEFAULT '',
  `groupname` varchar(64) NOT NULL DEFAULT '',
  `priority` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_group` (`username`,`groupname`),
  KEY `username` (`username`(32))
) ENGINE=InnoDB AUTO_INCREMENT=1539 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table radius.realms
CREATE TABLE IF NOT EXISTS `realms` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `realmname` varchar(128) DEFAULT NULL,
  `type` varchar(32) DEFAULT NULL,
  `authhost` varchar(256) DEFAULT NULL,
  `accthost` varchar(256) DEFAULT NULL,
  `secret` varchar(128) DEFAULT NULL,
  `ldflag` varchar(64) DEFAULT NULL,
  `nostrip` int(8) DEFAULT NULL,
  `hints` int(8) DEFAULT NULL,
  `notrealm` int(8) DEFAULT NULL,
  `creationdate` datetime DEFAULT NULL,
  `creationby` varchar(128) DEFAULT NULL,
  `updatedate` datetime DEFAULT NULL,
  `updateby` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table radius.sms_log
CREATE TABLE IF NOT EXISTS `sms_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mobile` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `response` text DEFAULT NULL,
  `http_code` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table radius.sms_verification
CREATE TABLE IF NOT EXISTS `sms_verification` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mobile` varchar(20) NOT NULL,
  `verification_code` varchar(6) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL,
  `verified` tinyint(1) DEFAULT 0,
  `attempts` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_mobile` (`mobile`),
  KEY `idx_code` (`verification_code`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table radius.subscription_types
CREATE TABLE IF NOT EXISTS `subscription_types` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type_name` varchar(50) NOT NULL COMMENT 'monthly or prepaid',
  `display_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `type_name` (`type_name`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table radius.userbillinfo
CREATE TABLE IF NOT EXISTS `userbillinfo` (
  `id` int(8) unsigned NOT NULL AUTO_INCREMENT,
  `external_invoice_id` varchar(255) DEFAULT NULL COMMENT 'External system (ERP) invoice ID for tracking',
  `username` varchar(128) DEFAULT NULL,
  `planName` varchar(128) DEFAULT NULL,
  `subscription_type` varchar(50) DEFAULT NULL,
  `hotspot_id` int(32) DEFAULT NULL,
  `hotspotlocation` varchar(32) DEFAULT NULL,
  `contactperson` varchar(200) DEFAULT NULL,
  `company` varchar(200) DEFAULT NULL,
  `email` varchar(200) DEFAULT NULL,
  `phone` varchar(200) DEFAULT NULL,
  `address` varchar(200) DEFAULT NULL,
  `city` varchar(200) DEFAULT NULL,
  `state` varchar(200) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `zip` varchar(200) DEFAULT NULL,
  `paymentmethod` varchar(200) DEFAULT NULL,
  `cash` varchar(200) DEFAULT NULL,
  `creditcardname` varchar(200) DEFAULT NULL,
  `creditcardnumber` varchar(200) DEFAULT NULL,
  `creditcardverification` varchar(200) DEFAULT NULL,
  `creditcardtype` varchar(200) DEFAULT NULL,
  `creditcardexp` varchar(200) DEFAULT NULL,
  `notes` varchar(200) DEFAULT NULL,
  `changeuserbillinfo` varchar(128) DEFAULT NULL,
  `lead` varchar(200) DEFAULT NULL,
  `coupon` varchar(200) DEFAULT NULL,
  `ordertaker` varchar(200) DEFAULT NULL,
  `billstatus` varchar(200) DEFAULT NULL,
  `lastbill` date NOT NULL DEFAULT '0000-00-00',
  `nextbill` date NOT NULL DEFAULT '0000-00-00',
  `traffic_balance` bigint(20) unsigned DEFAULT 0,
  `nextinvoicedue` int(32) DEFAULT NULL,
  `billdue` int(32) DEFAULT NULL,
  `postalinvoice` varchar(8) DEFAULT NULL,
  `faxinvoice` varchar(8) DEFAULT NULL,
  `emailinvoice` varchar(8) DEFAULT NULL,
  `batch_id` int(32) DEFAULT NULL,
  `creationdate` datetime DEFAULT '0000-00-00 00:00:00',
  `creationby` varchar(128) DEFAULT NULL,
  `updatedate` datetime DEFAULT '0000-00-00 00:00:00',
  `updateby` varchar(128) DEFAULT NULL,
  `subscription_type_id` int(10) unsigned DEFAULT 1 COMMENT 'Current subscription type (1=monthly, 2=prepaid)',
  `current_bundle_id` int(32) DEFAULT NULL COMMENT 'Active bundle ID from user_bundles table',
  `bundle_activation_date` datetime DEFAULT NULL COMMENT 'When current bundle was activated',
  `bundle_expiry_date` datetime DEFAULT NULL COMMENT 'When current bundle expires',
  `bundle_status` enum('active','expired','suspended') DEFAULT NULL COMMENT 'Current bundle status',
  `monthly_billing_day` int(2) DEFAULT 26 COMMENT 'Day of month for invoice generation (1-28, default 26)',
  `timebank_balance` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Current Accumulative Time Bank balance',
  `money_balance` decimal(10,2) DEFAULT 0.00 COMMENT 'User monetary balance in dollars',
  `total_invoices_amount` decimal(10,2) DEFAULT 0.00 COMMENT 'Total amount of unpaid invoices',
  `last_balance_update` datetime DEFAULT NULL COMMENT 'Last time balance was modified',
  PRIMARY KEY (`id`),
  UNIQUE KEY `external_invoice_id` (`external_invoice_id`),
  KEY `username` (`username`),
  KEY `planname` (`planName`),
  KEY `idx_money_balance` (`money_balance`),
  KEY `idx_last_balance_update` (`last_balance_update`),
  KEY `idx_external_invoice_id` (`external_invoice_id`),
  KEY `idx_subscription_type` (`subscription_type_id`),
  KEY `idx_bundle_expiry` (`bundle_expiry_date`),
  KEY `idx_bundle_status` (`bundle_status`),
  KEY `idx_current_bundle` (`current_bundle_id`),
  CONSTRAINT `chk_money_balance_limit` CHECK (`money_balance` >= -300000.00)
) ENGINE=InnoDB AUTO_INCREMENT=389 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table radius.userinfo
CREATE TABLE IF NOT EXISTS `userinfo` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(128) DEFAULT NULL,
  `firstname` varchar(200) DEFAULT NULL,
  `lastname` varchar(200) DEFAULT NULL,
  `email` varchar(200) DEFAULT NULL,
  `department` varchar(200) DEFAULT NULL,
  `company` varchar(200) DEFAULT NULL,
  `workphone` varchar(200) DEFAULT NULL,
  `homephone` varchar(200) DEFAULT NULL,
  `mobilephone` varchar(200) DEFAULT NULL,
  `address` varchar(200) DEFAULT NULL,
  `city` varchar(200) DEFAULT NULL,
  `state` varchar(200) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `zip` varchar(200) DEFAULT NULL,
  `notes` varchar(200) DEFAULT NULL,
  `changeuserinfo` varchar(128) DEFAULT NULL,
  `portalloginpassword` varchar(128) DEFAULT '',
  `enableportallogin` int(32) DEFAULT 0,
  `creationdate` datetime DEFAULT '0000-00-00 00:00:00',
  `creationby` varchar(128) DEFAULT NULL,
  `updatedate` datetime DEFAULT '0000-00-00 00:00:00',
  `updateby` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=391 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table radius.user_agent
CREATE TABLE IF NOT EXISTS `user_agent` (
  `user_id` int(11) NOT NULL,
  `agent_id` int(11) NOT NULL,
  PRIMARY KEY (`user_id`,`agent_id`),
  KEY `idx_user_agent_user` (`user_id`),
  KEY `idx_user_agent_agent` (`agent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table radius.user_balance_history
CREATE TABLE IF NOT EXISTS `user_balance_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL COMMENT 'Reference to userbillinfo.id',
  `username` varchar(128) NOT NULL COMMENT 'Username for quick reference',
  `transaction_type` enum('credit','debit','payment','refund','adjustment','invoice_created','invoice_cancelled') NOT NULL,
  `amount` decimal(10,2) NOT NULL COMMENT 'Transaction amount (positive for credits, negative for debits)',
  `balance_before` decimal(10,2) NOT NULL COMMENT 'Balance before transaction',
  `balance_after` decimal(10,2) NOT NULL COMMENT 'Balance after transaction',
  `reference_type` varchar(50) DEFAULT NULL COMMENT 'Type of reference: invoice, payment, manual, api, etc',
  `reference_id` int(11) DEFAULT NULL COMMENT 'ID of related record (invoice_id, payment_id, etc)',
  `description` text DEFAULT NULL COMMENT 'Human-readable description',
  `created_by` varchar(128) DEFAULT NULL COMMENT 'Who created this transaction',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP address of requester',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_username` (`username`),
  KEY `idx_transaction_type` (`transaction_type`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_reference` (`reference_type`,`reference_id`)
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Complete audit trail for all balance transactions';

-- Data exporting was unselected.

-- Dumping structure for table radius.user_bundles
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
  `created_at` datetime DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_username` (`username`),
  KEY `idx_plan_id` (`plan_id`),
  KEY `idx_status` (`status`),
  KEY `idx_expiry_date` (`expiry_date`),
  KEY `idx_agent_payment` (`agent_payment_id`),
  KEY `idx_purchase_date` (`purchase_date`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Bundle purchase and activation history';

-- Data exporting was unselected.

-- Dumping structure for table radius.wimax
CREATE TABLE IF NOT EXISTS `wimax` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(64) NOT NULL DEFAULT '',
  `authdate` timestamp NOT NULL,
  `spi` varchar(16) NOT NULL DEFAULT '',
  `mipkey` varchar(400) NOT NULL DEFAULT '',
  `lifetime` int(12) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `username` (`username`),
  KEY `spi` (`spi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for trigger radius.radacct_billing_delete
SET @OLDTMP_SQL_MODE=@@SQL_MODE, SQL_MODE='STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION';
DELIMITER //
CREATE TRIGGER radacct_billing_delete
AFTER DELETE ON radacct
FOR EACH ROW
BEGIN
    DELETE FROM radacct_billing WHERE radacctid = OLD.radacctid;
END//
DELIMITER ;
SET SQL_MODE=@OLDTMP_SQL_MODE;

-- Dumping structure for trigger radius.radacct_billing_insert
SET @OLDTMP_SQL_MODE=@@SQL_MODE, SQL_MODE='STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION';
DELIMITER //
CREATE TRIGGER radacct_billing_insert
AFTER INSERT ON radacct
FOR EACH ROW
BEGIN
    DECLARE v_groupname VARCHAR(64);
    SELECT COALESCE(NEW.groupname,
                    (SELECT rug.groupname
                     FROM radusergroup rug
                     WHERE rug.username = NEW.username
                     ORDER BY rug.priority ASC LIMIT 1),
                    '')
      INTO v_groupname;

    INSERT INTO radacct_billing (
        radacctid, acctsessionid, acctuniqueid, username, groupname, realm,
        nasipaddress, nasportid, nasporttype, acctstarttime, acctupdatetime,
        acctstoptime, acctinterval, acctsessiontime, acctauthentic,
        connectinfo_start, connectinfo_stop, acctinputoctets, acctoutputoctets,
        calledstationid, callingstationid, acctterminatecause, servicetype,
        framedprotocol, framedipaddress, traffic_mb, session_minutes
    )
    VALUES (
        NEW.radacctid, NEW.acctsessionid, NEW.acctuniqueid, NEW.username, v_groupname,
        COALESCE(NEW.realm, ''), NEW.nasipaddress, NEW.nasportid, NEW.nasporttype,
        NEW.acctstarttime, NEW.acctupdatetime, NEW.acctstoptime, NEW.acctinterval,
        NEW.acctsessiontime, NEW.acctauthentic, NEW.connectinfo_start,
        NEW.connectinfo_stop, NEW.acctinputoctets, NEW.acctoutputoctets,
        NEW.calledstationid, NEW.callingstationid, NEW.acctterminatecause,
        NEW.servicetype, NEW.framedprotocol, NEW.framedipaddress,
        ROUND(COALESCE((NEW.acctinputoctets + NEW.acctoutputoctets) / 1048576, 0), 2),
        ROUND(COALESCE(NEW.acctsessiontime / 60, 0), 2)
    );
END//
DELIMITER ;
SET SQL_MODE=@OLDTMP_SQL_MODE;

-- Dumping structure for trigger radius.radacct_billing_update
SET @OLDTMP_SQL_MODE=@@SQL_MODE, SQL_MODE='STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION';
DELIMITER //
CREATE TRIGGER radacct_billing_update
AFTER UPDATE ON radacct
FOR EACH ROW
BEGIN
    DECLARE v_groupname VARCHAR(64);
    SELECT COALESCE(NEW.groupname,
                    (SELECT rug.groupname
                     FROM radusergroup rug
                     WHERE rug.username = NEW.username
                     ORDER BY rug.priority ASC LIMIT 1),
                    '')
      INTO v_groupname;

    UPDATE radacct_billing SET
        acctsessionid = NEW.acctsessionid,
        acctuniqueid = NEW.acctuniqueid,
        username = NEW.username,
        groupname = v_groupname,
        realm = COALESCE(NEW.realm, ''),
        nasipaddress = NEW.nasipaddress,
        nasportid = NEW.nasportid,
        nasporttype = NEW.nasporttype,
        acctstarttime = NEW.acctstarttime,
        acctupdatetime = NEW.acctupdatetime,
        acctstoptime = NEW.acctstoptime,
        acctinterval = NEW.acctinterval,
        acctsessiontime = NEW.acctsessiontime,
        acctauthentic = NEW.acctauthentic,
        connectinfo_start = NEW.connectinfo_start,
        connectinfo_stop = NEW.connectinfo_stop,
        acctinputoctets = NEW.acctinputoctets,
        acctoutputoctets = NEW.acctoutputoctets,
        calledstationid = NEW.calledstationid,
        callingstationid = NEW.callingstationid,
        acctterminatecause = NEW.acctterminatecause,
        servicetype = NEW.servicetype,
        framedprotocol = NEW.framedprotocol,
        framedipaddress = NEW.framedipaddress,
        traffic_mb = ROUND(COALESCE((NEW.acctinputoctets + NEW.acctoutputoctets) / 1048576, 0), 2),
        session_minutes = ROUND(COALESCE(NEW.acctsessiontime / 60, 0), 2)
    WHERE radacctid = NEW.radacctid;
END//
DELIMITER ;
SET SQL_MODE=@OLDTMP_SQL_MODE;

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
