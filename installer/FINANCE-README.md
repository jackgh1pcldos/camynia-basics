# Finance Module Dummy Data Import

This guide explains how to import the finance module dummy data into your Camynia database.

## Prerequisites

1. You must have already installed the main database schema (`database-schema.sql`)
2. You must have at least one company set up in your database
3. MySQL/MariaDB must be installed and accessible

## Import Instructions

### Option 1: Using MySQL Command Line

```bash
mysql -u your_username -p your_database_name < installer/finance-DATA-dummy.sql
```

### Option 2: Using phpMyAdmin

1. Log in to phpMyAdmin
2. Select your database
3. Click on the "Import" tab
4. Click "Choose File" and select `finance-DATA-dummy.sql`
5. Click "Go" to execute the import

### Option 3: Using MySQL Workbench

1. Open MySQL Workbench
2. Connect to your database
3. Go to Server > Data Import
4. Select "Import from Self-Contained File"
5. Browse and select `finance-DATA-dummy.sql`
6. Click "Start Import"

## What Gets Imported

This SQL file creates the following finance tables and populates them with dummy data:

### Tables Created:
- **vendor_categories** - Categories for organizing vendors
- **vendors** - Vendor/supplier information
- **catalog_items** - Product and service catalog
- **warehouses** - Warehouse locations
- **purchase_orders** & **purchase_order_lines** - Purchase orders
- **goods_receipts** & **goods_receipt_lines** - Goods receipt notes
- **ap_invoices** & **ap_invoice_lines** - Accounts payable invoices
- **expense_reports** & **expense_line_items** - Employee expense reports
- **asset_categories** - Categories for fixed assets
- **fixed_assets** - Fixed asset tracking
- **inventory_stock** - Current inventory levels
- **inventory_transactions** - Inventory movement history
- **cost_centers** - Cost center definitions
- **budget_periods** - Budget period definitions (FY, quarters)
- **budgets** - Budget allocations
- **approval_rules** - Approval workflow rules
- **requisitions** & **requisition_lines** - Purchase requisitions
- **finance_audit_trail** - Audit log for all finance transactions
- **data_access_log** - GDPR compliance data access logging

### Dummy Data Includes:

- **5 vendor categories** (Office Supplies, IT Services, Equipment, Professional Services, Utilities)
- **5 vendors** with complete contact and payment information
- **2 warehouses** (New York and Los Angeles)
- **8 catalog items** (laptops, monitors, office supplies, services)
- **3 purchase orders** with line items
- **2 goods receipts** documenting received items
- **2 AP invoices** matched to purchase orders
- **2 expense reports** with line items
- **5 fixed assets** (computers, furniture, monitors)
- **6 inventory stock records** with current quantities
- **5 inventory transactions** showing receipts and issues
- **4 cost centers** (Engineering, Sales, Marketing, Administration)
- **3 budget periods** (FY 2025, Q1 2025, Q2 2025)
- **5 budget allocations** across different categories
- **4 approval rules** for different document types and amounts
- **2 requisitions** with line items

All dummy data is associated with **Company ID 1** (Acme Corporation) from the main schema.

## Fixes Provided

This SQL file specifically fixes the inventory error:
```
Error: SQLSTATE[42S22]: Column not found: 1054 Unknown column 's.catalog_item_id' in 'on clause'
```

The `inventory_stock` table is now properly created with the `catalog_item_id` column that the inventory API expects.

## Testing After Import

After importing, you can test the finance modules:

1. **Vendors**: Navigate to `/staff/finance/vendors.php`
2. **Catalog Items**: Navigate to `/staff/finance/catalog.php`
3. **Purchase Orders**: Navigate to `/staff/finance/purchase-orders.php`
4. **Inventory**: Navigate to `/staff/finance/inventory.php`
5. **AP Invoices**: Navigate to `/staff/finance/invoices.php`
6. **Expense Reports**: Navigate to `/staff/finance/expenses.php`
7. **Fixed Assets**: Navigate to `/staff/finance/assets.php`

All these modules should now load without errors and display the dummy data.

## Notes

- The SQL file uses `INSERT IGNORE` to prevent duplicate key errors if you run it multiple times
- All tables use `IF NOT EXISTS` so they won't overwrite existing tables
- Foreign key checks are temporarily disabled during import and re-enabled at the end
- All monetary amounts are in USD
- The dummy data follows a realistic business scenario with interconnected records

## Troubleshooting

If you encounter errors:

1. Ensure you've imported `database-schema.sql` first
2. Check that you have a company with ID 1 in your database
3. Verify you have users with IDs 3, 4, 5, 6, 7, 8 (created by `dummy-data.sql`)
4. Check MySQL error logs for specific issues

## Support

For questions or issues, please open an issue on the repository.
