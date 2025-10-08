-- ============================================================
-- CAMYNIA FINANCE MODULE - DUMMY DATA
-- Sample finance data for purchase orders, vendors, inventory, etc.
-- Run this AFTER installing the database schema
-- ============================================================

SET FOREIGN_KEY_CHECKS=0;

-- ============================================================
-- FINANCE MODULE TABLE SCHEMAS
-- ============================================================

-- Vendor Categories
CREATE TABLE IF NOT EXISTS `vendor_categories` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_company_name` (`company_id`, `name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vendors
CREATE TABLE IF NOT EXISTS `vendors` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `vendor_code` VARCHAR(50) NOT NULL,
  `vendor_name` VARCHAR(255) NOT NULL,
  `legal_name` VARCHAR(255),
  `category_id` INT UNSIGNED NULL,
  
  -- Contact Information
  `primary_contact_name` VARCHAR(255),
  `primary_contact_email` VARCHAR(255),
  `primary_contact_phone` VARCHAR(50),
  
  -- Address
  `website` VARCHAR(255),
  `address_line1` VARCHAR(255),
  `address_line2` VARCHAR(255),
  `city` VARCHAR(100),
  `state_province` VARCHAR(100),
  `postal_code` VARCHAR(20),
  `country` VARCHAR(2) DEFAULT 'US',
  
  -- Tax Information
  `tax_id_type` ENUM('EIN', 'SSN', 'VAT', 'OTHER'),
  `tax_id` VARCHAR(50),
  `tax_exempt` BOOLEAN DEFAULT 0,
  `is_1099_vendor` BOOLEAN DEFAULT 0,
  
  -- Payment Terms
  `payment_terms` VARCHAR(50) DEFAULT 'NET30',
  `payment_method` ENUM('CHECK', 'ACH', 'WIRE', 'CREDIT_CARD') DEFAULT 'CHECK',
  `credit_limit` DECIMAL(15,2) DEFAULT 0,
  
  -- Banking (encrypted in production)
  `bank_name` VARCHAR(255),
  `bank_account_number` VARCHAR(100),
  `bank_routing_number` VARCHAR(50),
  
  -- Risk Management
  `risk_level` ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
  `risk_score` INT DEFAULT 50,
  `risk_notes` TEXT,
  
  -- Status
  `status` ENUM('pending', 'active', 'on_hold', 'inactive') DEFAULT 'active',
  `onboarding_status` ENUM('new', 'in_progress', 'approved', 'rejected') DEFAULT 'new',
  `notes` TEXT,
  
  -- Audit
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`category_id`) REFERENCES `vendor_categories`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  UNIQUE KEY `unique_company_code` (`company_id`, `vendor_code`),
  INDEX `idx_status` (`status`),
  INDEX `idx_vendor_name` (`vendor_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catalog Items (Products/Services)
CREATE TABLE IF NOT EXISTS `catalog_items` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `item_code` VARCHAR(50) NOT NULL,
  `item_name` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `item_type` ENUM('goods', 'service') DEFAULT 'goods',
  `uom` VARCHAR(20) DEFAULT 'EA',
  
  -- Pricing
  `standard_cost` DECIMAL(15,2) DEFAULT 0,
  `list_price` DECIMAL(15,2) DEFAULT 0,
  `currency_code` VARCHAR(3) DEFAULT 'USD',
  
  -- Accounting
  `gl_account_code` VARCHAR(50),
  
  -- Tax
  `is_taxable` BOOLEAN DEFAULT 1,
  `tax_code` VARCHAR(50),
  
  -- Inventory Management
  `is_inventory_item` BOOLEAN DEFAULT 0,
  `reorder_point` INT DEFAULT 0,
  `reorder_qty` INT DEFAULT 0,
  `lead_time_days` INT DEFAULT 0,
  
  -- Tracking
  `track_serial_numbers` BOOLEAN DEFAULT 0,
  `track_lot_numbers` BOOLEAN DEFAULT 0,
  
  -- Vendor
  `preferred_vendor_id` INT UNSIGNED NULL,
  
  -- Status
  `status` ENUM('active', 'inactive', 'discontinued') DEFAULT 'active',
  
  -- Audit
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`preferred_vendor_id`) REFERENCES `vendors`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  UNIQUE KEY `unique_company_code` (`company_id`, `item_code`),
  INDEX `idx_item_type` (`item_type`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Warehouses
CREATE TABLE IF NOT EXISTS `warehouses` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `warehouse_code` VARCHAR(50) NOT NULL,
  `warehouse_name` VARCHAR(255) NOT NULL,
  `address_line1` VARCHAR(255),
  `address_line2` VARCHAR(255),
  `city` VARCHAR(100),
  `state_province` VARCHAR(100),
  `postal_code` VARCHAR(20),
  `country` VARCHAR(2) DEFAULT 'US',
  `manager_user_id` INT UNSIGNED NULL,
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`manager_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  UNIQUE KEY `unique_company_code` (`company_id`, `warehouse_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Purchase Orders
CREATE TABLE IF NOT EXISTS `purchase_orders` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `vendor_id` INT UNSIGNED NOT NULL,
  `po_number` VARCHAR(50),
  `po_date` DATE NOT NULL,
  `expected_delivery_date` DATE,
  `payment_terms` VARCHAR(50) DEFAULT 'NET30',
  `shipping_method` VARCHAR(100) DEFAULT 'STANDARD',
  `shipping_cost` DECIMAL(15,2) DEFAULT 0,
  `buyer_user_id` INT UNSIGNED NULL,
  `notes` TEXT,
  
  -- Amounts
  `subtotal_amount` DECIMAL(15,2) NOT NULL,
  `tax_amount` DECIMAL(15,2) DEFAULT 0,
  `total_amount` DECIMAL(15,2) NOT NULL,
  
  -- Status
  `status` ENUM('draft', 'approved', 'sent', 'acknowledged', 'receiving', 'closed', 'cancelled') DEFAULT 'draft',
  `approved_date` TIMESTAMP NULL,
  `approved_by_user_id` INT UNSIGNED NULL,
  
  -- Audit
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`vendor_id`) REFERENCES `vendors`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`buyer_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`approved_by_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  UNIQUE KEY `unique_po_number` (`company_id`, `po_number`),
  INDEX `idx_status` (`status`),
  INDEX `idx_vendor` (`vendor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Purchase Order Lines
CREATE TABLE IF NOT EXISTS `purchase_order_lines` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `po_id` INT UNSIGNED NOT NULL,
  `line_number` INT NOT NULL,
  `catalog_item_id` INT UNSIGNED NULL,
  `requisition_line_id` INT UNSIGNED NULL,
  
  `item_description` VARCHAR(500) NOT NULL,
  `quantity` DECIMAL(15,3) NOT NULL,
  `uom` VARCHAR(20) DEFAULT 'EA',
  `unit_price` DECIMAL(15,2) NOT NULL,
  
  `tax_rate` DECIMAL(5,2) DEFAULT 0,
  `tax_amount` DECIMAL(15,2) DEFAULT 0,
  `line_total` DECIMAL(15,2) NOT NULL,
  
  `expected_delivery_date` DATE,
  `received_qty` DECIMAL(15,3) DEFAULT 0,
  
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`po_id`) REFERENCES `purchase_orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`catalog_item_id`) REFERENCES `catalog_items`(`id`) ON DELETE SET NULL,
  INDEX `idx_po` (`po_id`),
  INDEX `idx_item` (`catalog_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Goods Receipts
CREATE TABLE IF NOT EXISTS `goods_receipts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `grn_number` VARCHAR(50),
  `po_id` INT UNSIGNED NULL,
  `receipt_date` DATE NOT NULL,
  `received_by_user_id` INT UNSIGNED NOT NULL,
  `warehouse_id` INT UNSIGNED NULL,
  `packing_slip_number` VARCHAR(100),
  `notes` TEXT,
  `status` ENUM('draft', 'completed', 'cancelled') DEFAULT 'completed',
  
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`po_id`) REFERENCES `purchase_orders`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`received_by_user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`) ON DELETE SET NULL,
  UNIQUE KEY `unique_grn` (`company_id`, `grn_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Goods Receipt Lines
CREATE TABLE IF NOT EXISTS `goods_receipt_lines` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `gr_id` INT UNSIGNED NOT NULL,
  `po_line_id` INT UNSIGNED NULL,
  `catalog_item_id` INT UNSIGNED NULL,
  `item_description` VARCHAR(500) NOT NULL,
  `quantity_received` DECIMAL(15,3) NOT NULL,
  `uom` VARCHAR(20) DEFAULT 'EA',
  `unit_cost` DECIMAL(15,2) DEFAULT 0,
  `notes` TEXT,
  
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`gr_id`) REFERENCES `goods_receipts`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`po_line_id`) REFERENCES `purchase_order_lines`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`catalog_item_id`) REFERENCES `catalog_items`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AP Invoices
CREATE TABLE IF NOT EXISTS `ap_invoices` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `vendor_id` INT UNSIGNED NOT NULL,
  `po_id` INT UNSIGNED NULL,
  `vendor_invoice_number` VARCHAR(100) NOT NULL,
  `invoice_date` DATE NOT NULL,
  `due_date` DATE,
  `payment_terms` VARCHAR(50),
  
  -- Amounts
  `subtotal_amount` DECIMAL(15,2) NOT NULL,
  `tax_amount` DECIMAL(15,2) DEFAULT 0,
  `total_amount` DECIMAL(15,2) NOT NULL,
  
  -- 3-Way Match
  `match_status` ENUM('pending', 'matched', 'variance', 'exception', 'manual_override') DEFAULT 'pending',
  `match_variance_amount` DECIMAL(15,2) DEFAULT 0,
  `match_notes` TEXT,
  
  -- Status
  `status` ENUM('draft', 'pending_match', 'matched', 'variance', 'approved', 'paid', 'cancelled') DEFAULT 'draft',
  `approved_date` TIMESTAMP NULL,
  `approved_by_user_id` INT UNSIGNED NULL,
  `paid_date` DATE NULL,
  
  -- Audit
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`vendor_id`) REFERENCES `vendors`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`po_id`) REFERENCES `purchase_orders`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`approved_by_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  INDEX `idx_vendor` (`vendor_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AP Invoice Lines
CREATE TABLE IF NOT EXISTS `ap_invoice_lines` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `invoice_id` INT UNSIGNED NOT NULL,
  `po_line_id` INT UNSIGNED NULL,
  `line_number` INT NOT NULL,
  `item_description` VARCHAR(500) NOT NULL,
  `quantity` DECIMAL(15,3) NOT NULL,
  `unit_price` DECIMAL(15,2) NOT NULL,
  `tax_rate` DECIMAL(5,2) DEFAULT 0,
  `tax_amount` DECIMAL(15,2) DEFAULT 0,
  `line_total` DECIMAL(15,2) NOT NULL,
  
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`invoice_id`) REFERENCES `ap_invoices`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`po_line_id`) REFERENCES `purchase_order_lines`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Expense Reports
CREATE TABLE IF NOT EXISTS `expense_reports` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `report_number` VARCHAR(50),
  `report_name` VARCHAR(255) NOT NULL,
  `employee_user_id` INT UNSIGNED NOT NULL,
  `business_purpose` TEXT,
  `total_amount` DECIMAL(15,2) NOT NULL,
  `status` ENUM('draft', 'submitted', 'approved', 'rejected', 'paid') DEFAULT 'draft',
  `submitted_date` TIMESTAMP NULL,
  `approved_date` TIMESTAMP NULL,
  `approved_by_user_id` INT UNSIGNED NULL,
  
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`employee_user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`approved_by_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Expense Line Items
CREATE TABLE IF NOT EXISTS `expense_line_items` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `expense_report_id` INT UNSIGNED NOT NULL,
  `expense_date` DATE NOT NULL,
  `expense_category` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `amount` DECIMAL(15,2) NOT NULL,
  `currency_code` VARCHAR(3) DEFAULT 'USD',
  `merchant_name` VARCHAR(255),
  `receipt_url` VARCHAR(500),
  
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`expense_report_id`) REFERENCES `expense_reports`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Asset Categories
CREATE TABLE IF NOT EXISTS `asset_categories` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `category_name` VARCHAR(100) NOT NULL,
  `depreciation_method` ENUM('straight_line', 'declining_balance', 'none') DEFAULT 'straight_line',
  `useful_life_years` INT DEFAULT 5,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_company_name` (`company_id`, `category_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fixed Assets
CREATE TABLE IF NOT EXISTS `fixed_assets` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `asset_tag` VARCHAR(50) NOT NULL,
  `category_id` INT UNSIGNED NULL,
  `description` VARCHAR(500) NOT NULL,
  `acquisition_date` DATE NOT NULL,
  `acquisition_cost` DECIMAL(15,2) NOT NULL,
  `salvage_value` DECIMAL(15,2) DEFAULT 0,
  `useful_life_years` INT DEFAULT 5,
  `depreciation_method` ENUM('straight_line', 'declining_balance', 'none') DEFAULT 'straight_line',
  `accumulated_depreciation` DECIMAL(15,2) DEFAULT 0,
  `location` VARCHAR(255),
  `assigned_to_user_id` INT UNSIGNED NULL,
  `status` ENUM('active', 'disposed', 'stolen', 'lost') DEFAULT 'active',
  `disposal_date` DATE NULL,
  `disposal_value` DECIMAL(15,2) NULL,
  `notes` TEXT,
  
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`category_id`) REFERENCES `asset_categories`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`assigned_to_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  UNIQUE KEY `unique_company_tag` (`company_id`, `asset_tag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inventory Stock
CREATE TABLE IF NOT EXISTS `inventory_stock` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `catalog_item_id` INT UNSIGNED NOT NULL,
  `warehouse_id` INT UNSIGNED NOT NULL,
  `quantity_on_hand` DECIMAL(15,3) DEFAULT 0,
  `quantity_allocated` DECIMAL(15,3) DEFAULT 0,
  `quantity_available` DECIMAL(15,3) GENERATED ALWAYS AS (quantity_on_hand - quantity_allocated) STORED,
  `total_value` DECIMAL(15,2) DEFAULT 0,
  `last_counted_date` DATE NULL,
  
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`catalog_item_id`) REFERENCES `catalog_items`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_stock` (`company_id`, `catalog_item_id`, `warehouse_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inventory Transactions
CREATE TABLE IF NOT EXISTS `inventory_transactions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `catalog_item_id` INT UNSIGNED NOT NULL,
  `warehouse_id` INT UNSIGNED NOT NULL,
  `transaction_type` ENUM('receipt', 'issue', 'adjustment', 'transfer', 'cycle_count') NOT NULL,
  `quantity` DECIMAL(15,3) NOT NULL,
  `unit_cost` DECIMAL(15,2) DEFAULT 0,
  `reference_type` VARCHAR(50) COMMENT 'purchase_order, sales_order, etc',
  `reference_id` INT UNSIGNED COMMENT 'ID of the referenced document',
  `reason` TEXT,
  
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`catalog_item_id`) REFERENCES `catalog_items`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cost Centers
CREATE TABLE IF NOT EXISTS `cost_centers` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `code` VARCHAR(50) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `manager_user_id` INT UNSIGNED NULL,
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`manager_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  UNIQUE KEY `unique_company_code` (`company_id`, `code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Budget Periods
CREATE TABLE IF NOT EXISTS `budget_periods` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `period_name` VARCHAR(100) NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `status` ENUM('draft', 'active', 'closed') DEFAULT 'draft',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Budgets
CREATE TABLE IF NOT EXISTS `budgets` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `budget_period_id` INT UNSIGNED NOT NULL,
  `cost_center_id` INT UNSIGNED NULL,
  `category` VARCHAR(100) NOT NULL,
  `budgeted_amount` DECIMAL(15,2) NOT NULL,
  `actual_amount` DECIMAL(15,2) DEFAULT 0,
  `variance` DECIMAL(15,2) GENERATED ALWAYS AS (budgeted_amount - actual_amount) STORED,
  `notes` TEXT,
  
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`budget_period_id`) REFERENCES `budget_periods`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`cost_center_id`) REFERENCES `cost_centers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Approval Rules
CREATE TABLE IF NOT EXISTS `approval_rules` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `rule_name` VARCHAR(255) NOT NULL,
  `document_type` ENUM('purchase_order', 'expense_report', 'ap_invoice') NOT NULL,
  `min_amount` DECIMAL(15,2) DEFAULT 0,
  `max_amount` DECIMAL(15,2) NULL,
  `approver_user_id` INT UNSIGNED NULL,
  `approver_role_id` INT UNSIGNED NULL,
  `approval_level` INT DEFAULT 1,
  `is_active` BOOLEAN DEFAULT 1,
  
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`approver_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`approver_role_id`) REFERENCES `roles`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Requisitions (Purchase Requests)
CREATE TABLE IF NOT EXISTS `requisitions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `requisition_number` VARCHAR(50),
  `requested_by_user_id` INT UNSIGNED NOT NULL,
  `request_date` DATE NOT NULL,
  `required_by_date` DATE,
  `business_justification` TEXT,
  `total_amount` DECIMAL(15,2) NOT NULL,
  `status` ENUM('draft', 'submitted', 'approved', 'rejected', 'converted', 'cancelled') DEFAULT 'draft',
  `approved_date` TIMESTAMP NULL,
  `approved_by_user_id` INT UNSIGNED NULL,
  
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`requested_by_user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`approved_by_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Requisition Lines
CREATE TABLE IF NOT EXISTS `requisition_lines` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `requisition_id` INT UNSIGNED NOT NULL,
  `line_number` INT NOT NULL,
  `catalog_item_id` INT UNSIGNED NULL,
  `item_description` VARCHAR(500) NOT NULL,
  `quantity` DECIMAL(15,3) NOT NULL,
  `uom` VARCHAR(20) DEFAULT 'EA',
  `estimated_unit_price` DECIMAL(15,2) DEFAULT 0,
  `estimated_total` DECIMAL(15,2) NOT NULL,
  `preferred_vendor_id` INT UNSIGNED NULL,
  
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`requisition_id`) REFERENCES `requisitions`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`catalog_item_id`) REFERENCES `catalog_items`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`preferred_vendor_id`) REFERENCES `vendors`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Finance Audit Trail
CREATE TABLE IF NOT EXISTS `finance_audit_trail` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `entity_type` VARCHAR(50) NOT NULL,
  `entity_id` INT UNSIGNED NOT NULL,
  `entity_number` VARCHAR(100),
  `action` VARCHAR(50) NOT NULL,
  `description` TEXT,
  `field_name` VARCHAR(100),
  `old_value` TEXT,
  `new_value` TEXT,
  `amount_delta` DECIMAL(15,2),
  `metadata` JSON,
  `signature` TEXT,
  `approval_level` INT,
  `ip_address` VARCHAR(45),
  `user_agent` VARCHAR(500),
  `request_method` VARCHAR(10),
  `request_url` VARCHAR(500),
  `request_payload` JSON,
  
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  INDEX `idx_entity` (`entity_type`, `entity_id`),
  INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data Access Log (for GDPR compliance)
CREATE TABLE IF NOT EXISTS `data_access_log` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `accessed_by_user_id` INT UNSIGNED NOT NULL,
  `data_type` VARCHAR(50) NOT NULL,
  `table_name` VARCHAR(100) NOT NULL,
  `record_id` INT UNSIGNED NOT NULL,
  `fields_accessed` JSON,
  `access_reason` TEXT,
  `ip_address` VARCHAR(45),
  `user_agent` VARCHAR(500),
  
  `accessed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`accessed_by_user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  INDEX `idx_accessed` (`accessed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DUMMY DATA FOR FINANCE TABLES
-- ============================================================

-- Vendor Categories for Company 1
INSERT IGNORE INTO vendor_categories (id, company_id, name, description) VALUES
(1, 1, 'Office Supplies', 'Suppliers of office supplies and stationery'),
(2, 1, 'IT Services', 'IT consulting and services providers'),
(3, 1, 'Equipment', 'Equipment and hardware suppliers'),
(4, 1, 'Professional Services', 'Consulting, legal, accounting services'),
(5, 1, 'Utilities', 'Utility and facility services');

-- Vendors for Company 1
INSERT IGNORE INTO vendors (id, company_id, vendor_code, vendor_name, legal_name, category_id, 
    primary_contact_name, primary_contact_email, primary_contact_phone,
    address_line1, city, state_province, postal_code, country,
    payment_terms, payment_method, credit_limit, risk_level, status, created_by) VALUES
(1, 1, 'VEN-001', 'Office Depot Inc', 'Office Depot Corporation', 1,
    'Sarah Johnson', 'sarah.j@officedepot.com', '555-0101',
    '123 Supply Street', 'New York', 'NY', '10001', 'US',
    'NET30', 'ACH', 50000.00, 'low', 'active', 3),
(2, 1, 'VEN-002', 'TechPro Solutions', 'TechPro Solutions LLC', 2,
    'Mike Chen', 'mike@techpro.com', '555-0102',
    '456 Tech Avenue', 'San Jose', 'CA', '95110', 'US',
    'NET45', 'ACH', 100000.00, 'medium', 'active', 3),
(3, 1, 'VEN-003', 'Global Equipment Co', 'Global Equipment Corporation', 3,
    'Jennifer Martinez', 'j.martinez@globalequip.com', '555-0103',
    '789 Industrial Blvd', 'Chicago', 'IL', '60601', 'US',
    'NET30', 'CHECK', 75000.00, 'low', 'active', 3),
(4, 1, 'VEN-004', 'Legal Partners LLP', 'Legal Partners Limited Liability Partnership', 4,
    'Robert Williams', 'rwilliams@legalpartners.com', '555-0104',
    '321 Law Street', 'Boston', 'MA', '02101', 'US',
    'NET15', 'CHECK', 25000.00, 'low', 'active', 3),
(5, 1, 'VEN-005', 'CloudHost Inc', 'CloudHost Incorporated', 2,
    'Amanda Lee', 'amanda@cloudhost.io', '555-0105',
    '654 Cloud Drive', 'Seattle', 'WA', '98101', 'US',
    'NET30', 'CREDIT_CARD', 150000.00, 'medium', 'active', 3);

-- Warehouses for Company 1
INSERT IGNORE INTO warehouses (id, company_id, warehouse_code, warehouse_name, 
    address_line1, city, state_province, postal_code, country, manager_user_id, status) VALUES
(1, 1, 'WH-NYC', 'New York Main Warehouse', 
    '100 Warehouse Ave', 'New York', 'NY', '10002', 'US', 5, 'active'),
(2, 1, 'WH-LA', 'Los Angeles Distribution Center', 
    '200 Distribution Way', 'Los Angeles', 'CA', '90001', 'US', 6, 'active');

-- Catalog Items for Company 1
INSERT IGNORE INTO catalog_items (id, company_id, item_code, item_name, description, item_type, uom,
    standard_cost, list_price, currency_code, is_taxable, is_inventory_item, 
    reorder_point, reorder_qty, preferred_vendor_id, status, created_by) VALUES
(1, 1, 'ITEM-001', 'Laptop Computer Dell XPS 15', 'Dell XPS 15 laptop with 16GB RAM', 'goods', 'EA',
    1200.00, 1500.00, 'USD', 1, 1, 5, 10, 3, 'active', 3),
(2, 1, 'ITEM-002', 'Office Chair Ergonomic', 'Ergonomic office chair with lumbar support', 'goods', 'EA',
    250.00, 350.00, 'USD', 1, 1, 10, 20, 1, 'active', 3),
(3, 1, 'ITEM-003', 'Printer Paper A4 500 Sheets', 'White A4 printer paper, 500 sheets per ream', 'goods', 'REAM',
    4.50, 7.00, 'USD', 1, 1, 50, 100, 1, 'active', 3),
(4, 1, 'ITEM-004', 'IT Consulting Services', 'Professional IT consulting hourly rate', 'service', 'HOUR',
    0.00, 150.00, 'USD', 1, 0, 0, 0, 2, 'active', 3),
(5, 1, 'ITEM-005', 'Monitor 27" 4K', '27 inch 4K LED monitor', 'goods', 'EA',
    300.00, 450.00, 'USD', 1, 1, 8, 15, 3, 'active', 3),
(6, 1, 'ITEM-006', 'Wireless Mouse', 'Ergonomic wireless mouse', 'goods', 'EA',
    15.00, 25.00, 'USD', 1, 1, 30, 50, 1, 'active', 3),
(7, 1, 'ITEM-007', 'USB-C Docking Station', 'Universal USB-C docking station', 'goods', 'EA',
    120.00, 180.00, 'USD', 1, 1, 10, 20, 3, 'active', 3),
(8, 1, 'ITEM-008', 'Cloud Storage Subscription', 'Monthly cloud storage subscription', 'service', 'MONTH',
    0.00, 50.00, 'USD', 1, 0, 0, 0, 5, 'active', 3);

-- Purchase Orders for Company 1
INSERT IGNORE INTO purchase_orders (id, company_id, vendor_id, po_number, po_date, expected_delivery_date,
    payment_terms, shipping_method, shipping_cost, buyer_user_id, notes,
    subtotal_amount, tax_amount, total_amount, status, approved_date, approved_by_user_id, created_by) VALUES
(1, 1, 3, 'PO-2025-00001', '2025-01-15', '2025-02-01',
    'NET30', 'STANDARD', 50.00, 5, 'Initial equipment order for new employees',
    6000.00, 480.00, 6530.00, 'approved', '2025-01-16 10:30:00', 3, 5),
(2, 1, 1, 'PO-2025-00002', '2025-01-20', '2025-01-30',
    'NET30', 'EXPRESS', 25.00, 5, 'Monthly office supplies replenishment',
    350.00, 28.00, 403.00, 'approved', '2025-01-20 14:00:00', 3, 5),
(3, 1, 2, 'PO-2025-00003', '2025-01-25', '2025-02-15',
    'NET45', 'STANDARD', 0.00, 5, 'Q1 IT consulting services',
    12000.00, 0.00, 12000.00, 'approved', '2025-01-25 09:00:00', 3, 5);

-- Purchase Order Lines
INSERT IGNORE INTO purchase_order_lines (id, po_id, line_number, catalog_item_id, item_description,
    quantity, uom, unit_price, tax_rate, tax_amount, line_total, expected_delivery_date) VALUES
-- PO-2025-00001 lines
(1, 1, 1, 1, 'Laptop Computer Dell XPS 15', 4, 'EA', 1200.00, 8.00, 384.00, 5184.00, '2025-02-01'),
(2, 1, 2, 5, 'Monitor 27" 4K', 4, 'EA', 300.00, 8.00, 96.00, 1296.00, '2025-02-01'),
-- PO-2025-00002 lines
(3, 2, 1, 3, 'Printer Paper A4 500 Sheets', 50, 'REAM', 4.50, 8.00, 18.00, 243.00, '2025-01-30'),
(4, 2, 2, 6, 'Wireless Mouse', 10, 'EA', 15.00, 8.00, 12.00, 162.00, '2025-01-30'),
-- PO-2025-00003 lines
(5, 3, 1, 4, 'IT Consulting Services', 80, 'HOUR', 150.00, 0.00, 0.00, 12000.00, '2025-02-15');

-- Goods Receipts
INSERT IGNORE INTO goods_receipts (id, company_id, grn_number, po_id, receipt_date, 
    received_by_user_id, warehouse_id, packing_slip_number, notes, status) VALUES
(1, 1, 'GRN-2025-00001', 1, '2025-02-01', 6, 1, 'PKG-12345', 'All items received in good condition', 'completed'),
(2, 1, 'GRN-2025-00002', 2, '2025-01-29', 6, 1, 'PKG-12346', 'Office supplies received', 'completed');

-- Goods Receipt Lines
INSERT IGNORE INTO goods_receipt_lines (id, gr_id, po_line_id, catalog_item_id, item_description,
    quantity_received, uom, unit_cost, notes) VALUES
-- GRN-2025-00001 lines
(1, 1, 1, 1, 'Laptop Computer Dell XPS 15', 4, 'EA', 1200.00, 'All units tested and working'),
(2, 1, 2, 5, 'Monitor 27" 4K', 4, 'EA', 300.00, 'No damage'),
-- GRN-2025-00002 lines
(3, 2, 3, 3, 'Printer Paper A4 500 Sheets', 50, 'REAM', 4.50, 'Complete order'),
(4, 2, 4, 6, 'Wireless Mouse', 10, 'EA', 15.00, 'Complete order');

-- AP Invoices
INSERT IGNORE INTO ap_invoices (id, company_id, vendor_id, po_id, vendor_invoice_number,
    invoice_date, due_date, payment_terms, subtotal_amount, tax_amount, total_amount,
    match_status, status, created_by) VALUES
(1, 1, 3, 1, 'INV-GE-2025-0123', '2025-02-01', '2025-03-03', 'NET30',
    4800.00, 384.00, 5184.00, 'matched', 'approved', 3),
(2, 1, 1, 2, 'INV-OD-2025-0456', '2025-01-29', '2025-02-28', 'NET30',
    275.00, 22.00, 297.00, 'matched', 'approved', 3);

-- AP Invoice Lines
INSERT IGNORE INTO ap_invoice_lines (id, invoice_id, po_line_id, line_number, item_description,
    quantity, unit_price, tax_rate, tax_amount, line_total) VALUES
-- Invoice 1 lines
(1, 1, 1, 1, 'Laptop Computer Dell XPS 15', 4, 1200.00, 8.00, 384.00, 5184.00),
-- Invoice 2 lines
(2, 2, 3, 1, 'Printer Paper A4 500 Sheets', 50, 4.50, 8.00, 18.00, 243.00),
(3, 2, 4, 2, 'Wireless Mouse', 5, 15.00, 8.00, 6.00, 81.00);

-- Expense Reports
INSERT IGNORE INTO expense_reports (id, company_id, report_number, report_name, employee_user_id,
    business_purpose, total_amount, status, submitted_date, approved_date, approved_by_user_id) VALUES
(1, 1, 'EXP-2025-00001', 'January 2025 Travel Expenses', 5,
    'Client meetings in San Francisco', 1250.50, 'approved', '2025-01-31 16:00:00', '2025-02-01 10:00:00', 3),
(2, 1, 'EXP-2025-00002', 'Office Supplies - Emergency Purchase', 7,
    'Emergency office supplies for new hires', 145.75, 'approved', '2025-01-28 14:00:00', '2025-01-29 09:00:00', 3);

-- Expense Line Items
INSERT IGNORE INTO expense_line_items (id, expense_report_id, expense_date, expense_category,
    description, amount, currency_code, merchant_name) VALUES
(1, 1, '2025-01-15', 'Airfare', 'Round trip flight to SFO', 425.00, 'USD', 'United Airlines'),
(2, 1, '2025-01-15', 'Hotel', 'Hotel accommodation 3 nights', 675.50, 'USD', 'Marriott Hotel'),
(3, 1, '2025-01-16', 'Meals', 'Client dinner', 150.00, 'USD', 'The French Laundry'),
(4, 2, '2025-01-28', 'Office Supplies', 'Pens, notebooks, sticky notes', 145.75, 'USD', 'Staples');

-- Asset Categories
INSERT IGNORE INTO asset_categories (id, company_id, category_name, depreciation_method, useful_life_years) VALUES
(1, 1, 'Computer Equipment', 'straight_line', 3),
(2, 1, 'Office Furniture', 'straight_line', 7),
(3, 1, 'Vehicles', 'declining_balance', 5),
(4, 1, 'Building Improvements', 'straight_line', 15);

-- Fixed Assets
INSERT IGNORE INTO fixed_assets (id, company_id, asset_tag, category_id, description,
    acquisition_date, acquisition_cost, salvage_value, useful_life_years, depreciation_method,
    accumulated_depreciation, location, assigned_to_user_id, status, created_by) VALUES
(1, 1, 'ASSET-001', 1, 'Dell XPS 15 Laptop - Serial: DL12345', 
    '2024-01-15', 1200.00, 100.00, 3, 'straight_line', 366.67, 'NY Office', 4, 'active', 3),
(2, 1, 'ASSET-002', 1, 'Dell XPS 15 Laptop - Serial: DL12346',
    '2024-01-15', 1200.00, 100.00, 3, 'straight_line', 366.67, 'NY Office', 5, 'active', 3),
(3, 1, 'ASSET-003', 2, 'Conference Room Table - Oak',
    '2023-06-01', 2500.00, 250.00, 7, 'straight_line', 535.71, 'Conference Room A', NULL, 'active', 3),
(4, 1, 'ASSET-004', 1, '27" 4K Monitor - Serial: MON98765',
    '2024-03-10', 450.00, 50.00, 3, 'straight_line', 122.22, 'NY Office', 4, 'active', 3),
(5, 1, 'ASSET-005', 2, 'Executive Desk - Mahogany',
    '2023-08-15', 1800.00, 200.00, 7, 'straight_line', 342.86, 'CEO Office', 3, 'active', 3);

-- Inventory Stock
INSERT IGNORE INTO inventory_stock (id, company_id, catalog_item_id, warehouse_id, 
    quantity_on_hand, quantity_allocated, total_value, last_counted_date) VALUES
(1, 1, 1, 1, 4, 0, 4800.00, '2025-02-01'),
(2, 1, 5, 1, 4, 0, 1200.00, '2025-02-01'),
(3, 1, 3, 1, 50, 0, 225.00, '2025-01-29'),
(4, 1, 6, 1, 10, 2, 150.00, '2025-01-29'),
(5, 1, 2, 1, 15, 0, 3750.00, '2025-01-10'),
(6, 1, 7, 1, 8, 1, 960.00, '2025-01-15');

-- Inventory Transactions
INSERT IGNORE INTO inventory_transactions (id, company_id, catalog_item_id, warehouse_id,
    transaction_type, quantity, unit_cost, reference_type, reference_id, reason, created_by) VALUES
(1, 1, 1, 1, 'receipt', 4, 1200.00, 'goods_receipt', 1, 'Received from PO-2025-00001', 6),
(2, 1, 5, 1, 'receipt', 4, 300.00, 'goods_receipt', 1, 'Received from PO-2025-00001', 6),
(3, 1, 3, 1, 'receipt', 50, 4.50, 'goods_receipt', 2, 'Received from PO-2025-00002', 6),
(4, 1, 6, 1, 'receipt', 10, 15.00, 'goods_receipt', 2, 'Received from PO-2025-00002', 6),
(5, 1, 6, 1, 'issue', -2, 15.00, NULL, NULL, 'Issued to engineering team', 5);

-- Cost Centers
INSERT IGNORE INTO cost_centers (id, company_id, code, name, description, manager_user_id, status) VALUES
(1, 1, 'CC-001', 'Engineering', 'Engineering department cost center', 3, 'active'),
(2, 1, 'CC-002', 'Sales', 'Sales department cost center', 7, 'active'),
(3, 1, 'CC-003', 'Marketing', 'Marketing department cost center', 8, 'active'),
(4, 1, 'CC-004', 'Administration', 'Administration and general overhead', 3, 'active');

-- Budget Periods
INSERT IGNORE INTO budget_periods (id, company_id, period_name, start_date, end_date, status) VALUES
(1, 1, 'FY 2025', '2025-01-01', '2025-12-31', 'active'),
(2, 1, 'Q1 2025', '2025-01-01', '2025-03-31', 'active'),
(3, 1, 'Q2 2025', '2025-04-01', '2025-06-30', 'draft');

-- Budgets
INSERT IGNORE INTO budgets (id, company_id, budget_period_id, cost_center_id, category,
    budgeted_amount, actual_amount, notes) VALUES
(1, 1, 2, 1, 'Equipment', 50000.00, 6530.00, 'Q1 equipment purchases'),
(2, 1, 2, 1, 'Office Supplies', 5000.00, 403.00, 'Q1 office supplies'),
(3, 1, 2, 1, 'Consulting Services', 20000.00, 12000.00, 'Q1 IT consulting'),
(4, 1, 2, 2, 'Travel & Entertainment', 15000.00, 1250.50, 'Q1 sales travel'),
(5, 1, 2, 3, 'Marketing Materials', 10000.00, 0.00, 'Q1 marketing expenses');

-- Approval Rules
INSERT IGNORE INTO approval_rules (id, company_id, rule_name, document_type,
    min_amount, max_amount, approver_user_id, approval_level, is_active) VALUES
(1, 1, 'PO Under $1000 - Dept Manager', 'purchase_order', 0.00, 1000.00, 5, 1, 1),
(2, 1, 'PO $1000-$10000 - VP', 'purchase_order', 1000.00, 10000.00, 3, 2, 1),
(3, 1, 'Expense Under $500 - Manager', 'expense_report', 0.00, 500.00, 5, 1, 1),
(4, 1, 'Expense $500+ - VP', 'expense_report', 500.00, NULL, 3, 2, 1);

-- Requisitions
INSERT IGNORE INTO requisitions (id, company_id, requisition_number, requested_by_user_id,
    request_date, required_by_date, business_justification, total_amount, status,
    approved_date, approved_by_user_id) VALUES
(1, 1, 'REQ-2025-00001', 4, '2025-01-10', '2025-01-31',
    'New laptops for Q1 hires', 6000.00, 'converted', '2025-01-12 10:00:00', 3),
(2, 1, 'REQ-2025-00002', 8, '2025-02-01', '2025-02-28',
    'Marketing materials for Q1 campaign', 2500.00, 'approved', '2025-02-02 14:00:00', 3);

-- Requisition Lines
INSERT IGNORE INTO requisition_lines (id, requisition_id, line_number, catalog_item_id,
    item_description, quantity, uom, estimated_unit_price, estimated_total, preferred_vendor_id) VALUES
(1, 1, 1, 1, 'Laptop Computer Dell XPS 15', 4, 'EA', 1200.00, 4800.00, 3),
(2, 1, 2, 5, 'Monitor 27" 4K', 4, 'EA', 300.00, 1200.00, 3),
(3, 2, 1, NULL, 'Promotional Banners', 10, 'EA', 150.00, 1500.00, NULL),
(4, 2, 2, NULL, 'Marketing Brochures', 1000, 'EA', 1.00, 1000.00, NULL);

SET FOREIGN_KEY_CHECKS=1;

-- Success message
SELECT 'Finance module tables created and populated with dummy data successfully!' as Status;
