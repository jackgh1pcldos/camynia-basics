# Quick Start - Finance Module Dummy Data

## TL;DR - Just import this file:

```bash
mysql -u root -p your_database_name < installer/finance-DATA-dummy.sql
```

That's it! Your finance module is ready with:
- ✅ All 24 finance tables created
- ✅ ~100 dummy records for testing
- ✅ Inventory error **FIXED**

## What You Get

After importing, you'll have working data for:

1. **Vendors** - 5 sample vendors ready to use
2. **Catalog Items** - 8 products/services (laptops, monitors, office supplies)
3. **Purchase Orders** - 3 complete POs with line items
4. **Inventory** - Stock levels across 2 warehouses (NY & LA)
5. **AP Invoices** - 2 invoices matched to POs
6. **Expense Reports** - 2 employee expense reports
7. **Fixed Assets** - 5 tracked assets (computers, furniture)
8. **Budgets** - Q1 2025 budget data across departments

## Quick Test

After import, test these URLs (adjust domain as needed):

```
/staff/finance/vendors.php      → Should show 5 vendors
/staff/finance/catalog.php      → Should show 8 catalog items
/staff/finance/inventory.php    → Should work WITHOUT errors! ✓
/staff/finance/purchase-orders.php → Should show 3 POs
/staff/finance/invoices.php     → Should show 2 invoices
/staff/finance/expenses.php     → Should show 2 expense reports
/staff/finance/assets.php       → Should show 5 assets
```

## The Error That's Now Fixed

**Before:**
```
Error: SQLSTATE[42S22]: Column not found: 1054 Unknown column 's.catalog_item_id' in 'on clause'
```

**After:**
✅ The `inventory_stock` table now has the `catalog_item_id` column with proper foreign key

## Need More Info?

See `FINANCE-README.md` for:
- Detailed table descriptions
- Alternative import methods (phpMyAdmin, MySQL Workbench)
- Complete dummy data listing
- Troubleshooting guide

## Safe to Re-run

This file uses:
- `CREATE TABLE IF NOT EXISTS` - won't break existing tables
- `INSERT IGNORE` - won't create duplicates

Run it as many times as you want!

---

**Questions?** Check FINANCE-README.md or open an issue.
