# Dentfluence — Inventory Module: Project Summary
**As of:** May 2026  
**Stack:** PHP · MySQL · Server-rendered modular architecture  
**Project:** Dentfluence Web App — Inventory + Purchase + Vendor Communication Module

---

## Architecture

```
modules/
    inventory/        ← Inventory Master, Stock In, Stock Out, Audit, Stock Movements
    purchase/         ← Purchase List, Purchase History
    communication/    ← Vendor Communication, WhatsApp flow
    vendors/          ← Vendor/Dealer management
    masters/          ← Categories, Sub Types, Locations, Brands, Packaging, Treatment Tags
config/
    db.php            ← MySQL connection
layout/
    sidebar.php
    header.php
ajax/                 ← AJAX handlers for all modules
assets/
    images/
    css/
    js/
```

---

## Database Tables (Planned / Designed)

### Master Tables
| Table | Purpose |
|---|---|
| `categories` | Product categories (Restorative, Anesthesia, etc.) |
| `sub_types` | Sub-categories under each category |
| `packaging_types` | Syringe, Bottle, Box, Cartridge, etc. |
| `locations` | Store Room A/B/C, Drawer B, Operatories |
| `companies` | Manufacturer companies (3M, Dentsply, Septodont, etc.) |
| `brands` | Brand names linked to companies (Filtek Z250 XT → 3M) |
| `vendors` | Dealer/Supplier records (DentalKart, Unicorn DenMart, etc.) |
| `treatment_tags` | RCT, Restorative, Aesthetic, Impression, etc. |
| `users` | Admin, Inventory Staff, Doctor roles |

### Core Inventory Tables
| Table | Key Fields |
|---|---|
| `products` | `id`, `generic_name`, `category_id`, `sub_type_id`, `packaging_type_id`, `qty_in_packaging`, `unit`, `usage_type` (single/multiple), `purchase_price`, `mrp`, `location_id`, `min_stock`, `reorder_level`, `company_id`, `brand_id`, `preferred_dealer_id`, `description`, `image_path`, `status`, `created_at` |
| `product_brands` | Many-to-many: product ↔ alternative brands |
| `product_dealers` | Many-to-many: product ↔ alternate dealers |
| `product_treatment_tags` | Many-to-many: product ↔ treatment tags |

### Stock Movement Tables
| Table | Key Fields |
|---|---|
| `stock_in` | `id`, `product_id`, `batch_no`, `mfg_date`, `expiry_date`, `dealer_id`, `invoice_no`, `purchase_price`, `discount`, `tax`, `quantity`, `total_amount`, `location_id`, `notes`, `created_by`, `created_at` |
| `stock_out` | `id`, `product_id`, `batch_no`, `expiry_date`, `qty_issued`, `issue_date`, `issue_time`, `issued_to_user_id`, `location_operatory`, `reason`, `notes`, `created_by`, `created_at` |
| `stock_movements` | Unified log of all IN/OUT events for audit trail |

### Purchase Tables
| Table | Key Fields |
|---|---|
| `purchase_orders` | `id`, `product_id`, `dealer_id`, `brand_id`, `qty`, `purchase_price`, `batch_no`, `expiry_date`, `invoice_no`, `notes`, `status`, `created_by`, `created_at` |

### Communication Table
| Table | Key Fields |
|---|---|
| `vendor_communications` | `id`, `purchase_order_id`, `dealer_id`, `contact_person`, `whatsapp_no`, `order_summary`, `instructions`, `status` (Pending/Ordered/Dispatched/Received), `created_at`, `updated_at` |

### Audit Table
| Table | Key Fields |
|---|---|
| `audits` | `id`, `audit_date`, `created_by`, `notes`, `status` |
| `audit_items` | `id`, `audit_id`, `product_id`, `system_qty`, `physical_qty`, `mismatch`, `adjustment_notes` |

---

## Key Design Decisions

### Product ≠ Brand (Critical)
The system separates:
- **Generic Product** — e.g. `Composite Resin`
- **Company** — e.g. `3M`
- **Brand** — e.g. `Filtek Z250 XT`

This prevents duplicate records and enables last-purchase intelligence per brand.

### Stock Out = Issue Entry (Not Consumption)
Stock Out is logged at the point of issuing from the store, NOT at point of patient use. No procedure-level tracking. This is the core operational philosophy.

### No Negative Stock
System blocks stock out if quantity exceeds available stock.

### 15-Day Audit Cycle
Audits are not primary stock management — they are verification checkpoints. Physical count is entered, compared against system count, mismatches are flagged and adjusted.

### Purchase Order → Communication Auto-Creation
When a purchase order is saved, a vendor communication entry is automatically created. WhatsApp message is pre-generated and offered to the user immediately after save.

### Last Purchase Intelligence
When brand is selected in the purchase drawer, the system auto-fills last purchase price, last purchase date, and last dealer used for that product+brand combination.

---

## UI/UX Decisions
- **Theme:** White background + Purple accent (`#6C47FF`) · Dark sidebar (`#0F0E1A`)
- **Layout:** Desktop-first · Sidebar navigation · Fixed header
- **Interactions:** Modals for Add/Edit · Right-side slide-over drawers for Stock In, Stock Out, Purchase Order
- **Tables:** Reusable with search, filter, column toggle, pagination
- **No separate pages** for operational actions — everything modal or drawer

---

## Modules Status

### ✅ Session 1 — BUILT (HTML Prototype)
**Inventory Dashboard**
- Stat cards: Total Products, Total Stock Value, Low Stock, Critical Items, Expiring Soon
- Stock Status donut chart (In Stock / Low / Out of Stock / Not Tracked)
- Top Consumed Items (Last 15 Days) bar chart
- Recent Stock Movements feed
- Low Stock Alert table
- Critical Items (Out of Stock) table
- Expiring Soon table

**Inventory Master Page**
- Full product data table: Generic Name, Brand Name, Category, Packaging, Location, Current Stock (color-coded), Min Stock, Status badge, Unit Cost, Actions
- Filter strip: All / Active / Inactive / Low Stock / Out of Stock / Expiring Soon
- Filters: All Categories, All Sub Types, All Locations, All Status
- Search: generic name, brand, company — real-time
- Pagination with page size selector
- Column toggle
- Import Products button
- Add New Product button

**Add New Product Modal (Full)**
- Section 1 — Basic Information: Generic Product Name, Category, Sub Type, Usage (Single/Multiple), Description
- Section 2 — Packaging Details: Packaging Type, Qty in Packaging (with unit), Pack Size, Shelf Life
- Section 3 — Pricing & Cost: Purchase Price (₹), MRP (optional), Cost per Unit (auto-calculated)
- Section 4 — Location & Stock: Primary Location, Minimum Stock, Reorder Level, Preferred Brand
- Section 5 — Company & Brand: Company Name (with Add Company inline), Brand Name (with Add Brand inline), Alternative Brands (multi-chip)
- Section 6 — Dealer / Supplier: Dealer (with Add Dealer inline), Alternate Dealers (multi-chip), Last Purchase Date
- Section 7 — Product Image: Drag & drop upload
- Section 8 — Treatment Tags: Checkbox grid with Add Custom Tag

**Stock In Drawer (Right slide-over)**
- Product select, Batch No., Mfg Date, Expiry Date, Company (auto), Brand (auto), Packaging (auto), Qty in Packaging (auto), No. of Packs, Total Quantity (calculated), Purchase Price, Discount, Tax, Total Amount (calculated), Dealer/Supplier, Invoice No., Purchase Date, Location, Notes

**Stock Out Drawer (Right slide-over)**
- Product select, Batch No., Expiry Date, Available Stock (auto), Qty to Issue, Issue Date, Time, Issued To (user select), Location/Operatory, Reason/Purpose, Notes

---

### 🔲 Session 2 — PENDING
**Purchase List Page**
- Filtered view: Low Stock + Critical + Out of Stock items only
- Columns: Product, Category, Current Stock (color-coded + badge), Min Stock, Suggested Qty, Preferred Brand, Last Purchase (price + date), Action
- Stat cards: Low Stock / Critical / Out of Stock counts
- Filters: Category, Location

**Create Purchase Order — Right Slide-over Drawer**
- Product summary card (image, name, category, current stock, min stock, suggested qty)
- Dealer/Supplier select
- Brand select → triggers Last Purchase Intelligence autofill
- Last Purchase Price / Date / Dealer display block
- Order Details: Quantity, Purchase Price (₹), Batch No., Expiry Date, Invoice No., Notes
- Save Purchase Order → auto-creates vendor communication entry

**Purchase History Page**
- Table of all past purchase orders with status tracking

---

### 🔲 Session 3 — PENDING
**Vendor Communication Module**
- Auto-created entry from purchase order
- Dealer Name, Contact Person, WhatsApp button, Call button
- Order Summary, Instructions
- Status flow: Pending → Ordered → Dispatched → Received

**WhatsApp Flow**
- Popup after purchase order save: Send via WhatsApp / Call Vendor / Save for Later
- Pre-generated WhatsApp message with order details

**Audit Module (15-Day Count)**
- Start audit → load all products with current system qty
- Physical count entry per product
- System vs Physical comparison
- Mismatch detection and highlighting
- Adjustment logging with notes
- Audit completion and history

---

### 🔲 Session 4 — PENDING
**Masters Pages (CRUD)**
- Categories
- Sub Types
- Packaging Types
- Locations
- Vendors / Dealers
- Treatment Tags
- Companies
- Brands

**Reports**
- Stock Report (export)
- Expiry Report
- Consumption Report

**Users & Roles**
- Admin / Inventory Staff / Doctor permission levels
- Restrict: stock adjustments, audit completion, product deletion

**System Settings**

---

## Roles & Permissions (Designed)
| Action | Admin | Inventory Staff | Doctor |
|---|---|---|---|
| View inventory | ✅ | ✅ | ✅ |
| Add product | ✅ | ✅ | ❌ |
| Stock In | ✅ | ✅ | ❌ |
| Stock Out | ✅ | ✅ | ❌ |
| Delete product | ✅ | ❌ | ❌ |
| Complete audit | ✅ | ✅ | ❌ |
| Stock adjustments | ✅ | ❌ | ❌ |
| Masters CRUD | ✅ | ❌ | ❌ |
| View reports | ✅ | ✅ | ✅ |

---

## File Count Estimate (Full Module)
| Type | Count |
|---|---|
| PHP files (pages + AJAX handlers) | ~40 |
| SQL schema file | 1 |
| JS component files (table, modal, drawer, toast, select2) | ~5 |
| CSS files (base, components, utilities) | ~3 |
| **Total** | **~50 files** |

---

## Routes / Pages (PHP)
```
index.php                          → redirect to dashboard
modules/inventory/dashboard.php    → Inventory Dashboard
modules/inventory/master.php       → Inventory Master (product list)
modules/inventory/stock_in.php     → Stock In page (+ AJAX modal)
modules/inventory/stock_out.php    → Stock Out page (+ AJAX modal)
modules/inventory/audit.php        → 15-Day Audit
modules/inventory/stock_movements.php → Full movement log
modules/purchase/purchase_list.php → Purchase List (low/critical/OOS)
modules/purchase/purchase_history.php → Purchase History
modules/communication/index.php    → Vendor Communication
modules/masters/categories.php
modules/masters/sub_types.php
modules/masters/packaging.php
modules/masters/locations.php
modules/masters/vendors.php
modules/masters/brands.php
modules/masters/companies.php
modules/masters/treatment_tags.php
modules/reports/stock_report.php
modules/reports/expiry_report.php
modules/reports/consumption_report.php
settings/users_roles.php
settings/system.php
ajax/products.php                  → Product CRUD
ajax/stock_in.php
ajax/stock_out.php
ajax/purchase_order.php
ajax/communication.php
ajax/audit.php
ajax/masters.php                   → All master CRUD
ajax/dashboard_stats.php
```

---

## What's Next

**Immediate next step:** Start Session 2 — Purchase List page + Purchase Order slide-over drawer + Purchase History.

**Paste into new session:**
1. The full project instructions (STRICT rules block)
2. This summary document
3. Tell Claude: "This is Session 2. Session 1 is complete — Dashboard, Inventory Master, Stock In drawer, Stock Out drawer are built as HTML prototype. Now build Session 2: Purchase List + Purchase Order drawer + Purchase History."

---

*Summary generated: May 2026*
