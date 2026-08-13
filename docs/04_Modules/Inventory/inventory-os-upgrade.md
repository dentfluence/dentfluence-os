# Inventory OS Upgrade — Reference Document
**Project:** Dentfluence OS — Inventory Module  
**Goal:** Upgrade (not rebuild) the existing Inventory module into a dental-first Inventory Operating System  
**Status as of:** 2026-07-02  

---

## Core Rules (Never Break These)

- **No database redesign.** All schema changes are additive only.
- **No breaking existing APIs.** `/api/v1/inventory` mobile endpoints stay intact.
- **Never touch:** Finance, Expenses, Accounts Payable, Daily Huddle, Tasks, Notifications, Audit Logs, GRN architecture, Purchase History, Inventory History, Location-wise stock.
- **Backward compatible** with all existing routes and form actions.
- UI terminology may differ from backend terminology (see mapping below).

---

## UI Terminology → Backend Mapping

| User Sees (UI)       | Backend Term           |
|----------------------|------------------------|
| Inventory            | Products / Catalogue   |
| Orders               | Purchase Orders        |
| Receive Delivery     | GRN (Goods Receipt Note) |
| Add Stock            | Stock In               |
| Use Item             | Stock Out              |
| Alerts               | (new hub page)         |
| Assets               | Reusable Assets        |
| Activity             | Stock Movements        |

---

## What's Already Built (Do Not Rebuild)

### Database (all migrations run, all tables exist)
- `inventory_categories`, `inventory_locations`, `inventory_vendors`
- `inventory_items`, `inventory_stocks`, `stock_movements`
- `purchase_orders`, `purchase_order_items`
- `goods_receipt_notes`, `grn_items`
- `vendor_invoices`, `vendor_invoice_items`
- `inventory_variants`, `inventory_sub_types`, `product_dealers`
- `stock_count_sessions`, `stock_count_lines`
- `reusable_assets`, `implant_catalog`, `implant_placements`
- `finance_vendors`, `finance_vendor_payments`

### Models (all in `app/Models/Inventory/` and `app/Models/Procurement/`)
InventoryItem, InventoryCategory, InventoryLocation, InventoryStock, StockMovement, PurchaseOrder, PurchaseOrderItem, InventoryVendor, InventoryVariant, InventorySubType, ReusableAsset, ImplantCatalog, ImplantPlacement, StockCountSession, StockCountLine, GoodsReceiptNote, GrnItem, VendorInvoice, VendorInvoiceItem

### Services
- `app/Services/Inventory/InventoryService.php` (709 lines) — all core logic:
  - `meta()`, `itemsStockQuery()`, `productsQuery()`, `findItem()`
  - `createStockIn()`, `createStockOut()`, `adjustStock()`
  - `createPurchaseOrder()`, `markOrdered()`, `receivePurchaseOrder()`
  - `implantCatalogQuery()`, `createCatalogItem()`, `createPlacement()`
  - `purchaseOrderWhatsappMessage()`

### Controller
- `app/Http/Controllers/InventoryController.php` (1787+ lines)
- All methods: `dashboard()`, `items()`, `products()`, `stockIn()`, `stockOut()`, `purchase()`, `vendors()`, `reusableAssets()`, `expiry()`, `reports()`, `settings()`, `receivePO()`, `reverseLastGrn()`, `stockCheck()`, `implants()`, and all CRUD methods for categories/locations/variants/vendors.
- **Phase 1 added:** `alerts()` method

### Routes (all under `inventory.` prefix, `module:inventory` middleware)
All existing routes intact. Phase 1 added:
```
GET /inventory/alerts  →  inventory.alerts
```

### Views (`resources/views/inventory/`)
- `dashboard.blade.php` — KPI dashboard
- `products.blade.php` — Catalogue/stock listing
- `items.blade.php` — Stock with quick adjust
- `purchase.blade.php` — Purchase Orders / GRN
- `stock-in.blade.php`, `stock-out.blade.php`
- `vendors.blade.php`, `expiry.blade.php`
- `implants.blade.php`, `reusable-assets.blade.php`
- `reports.blade.php`, `settings.blade.php`
- `stock-count-index.blade.php`, `stock-count.blade.php`
- **Phase 1 added:** `alerts.blade.php`
- `partials/subnav.blade.php` — **Phase 1 rewritten (see below)**

### Integrations (all working, do not break)
- **Finance:** GRN → creates FinanceExpense (unpaid) → Accounts Payable chain
- **Daily Huddle:** Reads low stock, expiry data
- **Tasks:** Manual task creation linked to inventory
- **Notifications:** Basic alerts
- **Mobile API:** `/api/v1/inventory` via `app/Http/Controllers/Api/V1/InventoryController.php`

---

## Role System (User Model)

Roles are on `users.role` (string field, legacy). Role constants:

```php
User::ROLE_ADMIN               = 'admin'
User::ROLE_DOCTOR              = 'doctor'
User::ROLE_RESIDENT_DENTIST    = 'resident_dentist'
User::ROLE_ASSOCIATE_DENTIST   = 'associate_dentist'
User::ROLE_VISITING_CONSULTANT = 'visiting_consultant'
User::ROLE_FRONT_DESK          = 'front_desk'
User::ROLE_ASSISTANT           = 'assistant'
User::ROLE_ACCOUNTS            = 'accounts'

User::DOCTOR_ROLES = ['doctor', 'resident_dentist', 'associate_dentist', 'visiting_consultant']
```

Helper methods: `$user->isAdmin()`, `$user->isAssistant()`

### Inventory Nav Tier Mapping
| Role(s)                          | Tier              | Sees                                             |
|----------------------------------|-------------------|--------------------------------------------------|
| doctor, resident_dentist, etc.   | Dentist / Owner   | Dashboard only                                   |
| assistant, front_desk            | Assistant         | Dashboard, Inventory, Orders, Alerts             |
| manager, accounts                | Inventory Manager | Dashboard, Inventory, Orders, Alerts, Implants, Assets, Reports |
| admin                            | Admin             | All 8 tabs + Settings                            |

---

## Phase 1 — Navigation + Terminology ✅ DONE (2026-07-02)

### What changed
**`resources/views/inventory/partials/subnav.blade.php`** — fully rewritten:
- New 8-tab structure replacing old 6-tab (staff) + 4-tab (admin) structure
- Tabs: Dashboard · Inventory · Orders · Alerts · Implants · Assets · Reports · Settings
- 4-tier role-based visibility (see table above)
- Settings tab pinned to far right with divider, admin-only
- Inventory tab active for stock-in/stock-out/stock-count sub-pages
- Orders tab active for vendors page too

**`routes/web.php`** — added:
```php
Route::get('/alerts', [InventoryController::class, 'alerts'])->name('alerts');
```

**`app/Http/Controllers/InventoryController.php`** — added `alerts()` method:
- Queries: critical stock (qty=0), low stock (qty ≤ minimum_qty), expiring soon (≤90 days), expired items, dead stock (no movement 90+ days), pending/delayed deliveries
- Variables passed to view: `$criticalStock`, `$lowStock`, `$expiringSoon`, `$expiredItems`, `$deadStock`, `$pendingDeliveries`, `$summary`

**`resources/views/inventory/alerts.blade.php`** — new functional stub page:
- Summary KPI bar (6 counts)
- 6 alert sections with tables + color-coded status
- Phase 4 teaser note at bottom

### Terminal commands needed after this phase
```bash
php artisan route:clear
php artisan view:clear
```

---

## Phase 2 — Dashboard + Health Score (NOT STARTED)

### What to build
1. **Inventory Health Score** — composite KPI calculated from:
   - Stock availability rate (items in stock / total active items)
   - Low stock penalty (items at/below min_qty)
   - Out of stock penalty
   - Expiry penalty (items expiring ≤30 days)
   - Dead stock penalty (items with no movement 90+ days)
   - Output: `Excellent` (90–100) / `Good` (70–89) / `Needs Attention` (50–69) / `Critical` (<50)

2. **New KPI cards on dashboard:**
   - Inventory Health Score (with visual badge)
   - Days Until Stock-out (avg across critical items)
   - Dead Stock Value (₹ blocked in dead items)
   - Monthly Wastage (expired/damaged items this month)
   - Today's Deliveries (POs with expected_delivery_date = today)
   - Implant Stock Health

3. **Role-specific dashboard views:**
   - Dentist/Owner: Health Score + 4 exec KPIs only, no tables
   - Assistant: Action cards (items to use, things to receive) not analytics
   - Manager/Admin: Full current dashboard + new KPIs

### Files to modify
- `app/Http/Controllers/InventoryController.php` — extend `buildKpis()`, add health score logic
- `resources/views/inventory/dashboard.blade.php` — add health score card, role-conditional sections
- No new migrations needed

---

## Phase 3 — Card-Based Inventory Page (NOT STARTED)

### What to build
1. **New card UI** for `inventory.products` page (replaces default table view)
   - Large search bar at top
   - Sections: Favorites · Frequently Used · Recent Items · All Inventory
   - Item card shows: photo (optional), name, current stock, status color, shelf/location, expiry summary
   - Quick action buttons per card: Use / Receive / Transfer / History / Reorder

2. **Product detail page** — new route `inventory.products.show` (`/inventory/products/{item}`)
   - 360 view: current stock, all locations, batch details, expiry, avg monthly consumption, last purchase, supplier, purchase history, usage history, activity timeline

3. **Favorites** — optional migration:
   ```sql
   CREATE TABLE inventory_item_favorites (
     id bigint PK,
     user_id bigint FK users,
     inventory_item_id bigint FK inventory_items,
     created_at timestamp
   )
   ```

4. **Frequently Used** — derived from `stock_movements` WHERE `movement_type = 'out'` GROUP BY `inventory_item_id` ORDER BY COUNT DESC (no migration needed)

### Files to modify
- `resources/views/inventory/products.blade.php` — rebuild with card layout (keep table as toggle)
- `app/Http/Controllers/InventoryController.php` — add `showProduct()` method + `products()` upgrades
- `routes/web.php` — add `GET /inventory/products/{item}` → `inventory.products.show`
- New view: `resources/views/inventory/product-detail.blade.php`
- Optional migration for favorites

### Note on existing table
Keep the existing table view accessible as a toggle ("Switch to table view") so power users aren't blocked.

---

## Phase 4 — Alerts Hub + Smart Purchasing (NOT STARTED)

### What to build
1. **Smart Purchasing Engine** — adds to `alerts.blade.php` and `InventoryService`:
   - Method: `suggestedPurchases()` — for each item below reorder_qty:
     - avg monthly consumption = stock_movements last 90 days / 3
     - days remaining = current_stock / (avg_monthly / 30)
     - suggested qty = (reorder_qty - current_stock) + (avg_monthly * 1.5) - pending_PO_qty
   - One-click "Create Order from Suggestion" (pre-fills PO form)

2. **Large Purchase Approval flow:**
   - Config: `inventory_settings.approval_threshold` (₹ amount)
   - POs above threshold get status `pending_approval`
   - Owner/admin sees approval card on dashboard

3. **Intelligent notification tiers:**
   - Critical: Out of stock, Delivery delayed >3 days, Expired items in stock
   - Warning: Low stock, Expiring ≤30 days, PO >2 days overdue
   - Info: Delivery expected today, New suggestion available

4. **Alerts page full build** — replace stub with:
   - Smart suggestions section (top priority)
   - Approval queue
   - All current stub sections (polished)
   - Asset service due alerts (from `reusable_assets` where `current_usage_count >= service_threshold`)

### Files to modify
- `app/Services/Inventory/InventoryService.php` — add `suggestedPurchases()`, `pendingApprovals()`
- `app/Http/Controllers/InventoryController.php` — extend `alerts()`, add `approvePO()` / `rejectPO()`
- `resources/views/inventory/alerts.blade.php` — replace stub with full page
- `routes/web.php` — add approval routes
- `app/Models/Inventory/PurchaseOrder.php` — add `pending_approval` status (check existing status enum)

---

## Phase 5 — Automation + Analytics (NOT STARTED)

### What to build

1. **Task Automation** — auto-create tasks on inventory events:
   | Event | Task Created | Priority | Assigned To |
   |-------|-------------|----------|-------------|
   | PO created | Confirm with vendor | Medium | Manager |
   | Expected delivery tomorrow | Follow up delivery | High | Manager |
   | GRN received | Verify invoice | Medium | Accounts |
   | Item hits low stock | Create purchase recommendation | Medium | Manager |
   | Item hits zero stock | Immediate purchase required | High | Manager |
   | Item expiring ≤30 days | Use before expiry | Medium | Assistant |
   | Asset service due | Schedule maintenance | Medium | Manager |
   | Delivery delayed >3 days | Contact vendor | High | Manager |
   | Large PO pending | Dentist approval required | High | Admin/Owner |

   Implementation: Hook into existing events or add observers on `PurchaseOrder`, `GoodsReceiptNote`, `InventoryStock` models.
   Use existing `TaskController`/Tasks module — do NOT create a new task system.

2. **Daily Huddle expansion** — extend the existing Huddle with inventory block:
   - Inventory Health Score
   - Today's Deliveries
   - Items Below Minimum (count + list)
   - Items Expiring This Week
   - Yesterday's Inventory Movements (summary)
   - Yesterday's Wastage
   - Pending Purchase Approvals
   Find the huddle data-building file (likely `HuddleController` or a service) and add inventory queries.

3. **Reports upgrade** — extend `reports.blade.php` and `reports()` controller method:
   - Fast Moving Items (top 10 by stock_movements count, last 30 days)
   - Slow Moving Items (bottom 10)
   - Inventory Turnover (COGS / avg inventory value)
   - Supplier Performance (avg delivery days per vendor, on-time % )
   - Spending by Category (monthly)
   - Waste Cost (expired + damaged this month)

4. **Spending Analytics** — extend Finance analytics:
   - Monthly Purchase Trend (chart)
   - Vendor-wise spend
   - Category-wise spend
   - Inventory Value Growth (month-over-month)

5. **AI-readiness data** — no AI implementation, just ensure data is queryable:
   - `inventory_items.average_monthly_consumption` (computed, store in item or derive from movements)
   - `inventory_items.days_until_stockout` (computed field or view)
   - Vendor delivery performance table/view

---

## Key File Locations

```
app/
  Http/Controllers/
    InventoryController.php          ← main controller (1800+ lines)
    Api/V1/InventoryController.php   ← mobile API controller
    StockCountController.php         ← stock count sessions
    VendorInvoiceController.php      ← vendor invoices
  Models/
    Inventory/                       ← all inventory models
    Procurement/                     ← GoodsReceiptNote, GrnItem, VendorInvoice
    Finance/FinanceVendor.php        ← linked vendor finance model
  Services/Inventory/
    InventoryService.php             ← 709-line core service

resources/views/inventory/
  dashboard.blade.php
  products.blade.php                 ← "Inventory" tab page
  purchase.blade.php                 ← "Orders" tab page
  alerts.blade.php                   ← "Alerts" tab page (Phase 1 stub)
  implants.blade.php
  reusable-assets.blade.php
  reports.blade.php
  expiry.blade.php                   ← still accessible, not in main nav
  stock-in.blade.php                 ← accessible via buttons, not nav
  stock-out.blade.php                ← accessible via buttons, not nav
  partials/subnav.blade.php         ← Phase 1 rewritten

routes/web.php                       ← inventory routes ~line 510–607

database/migrations/
  2026_05_28_*                       ← core inventory tables
  2026_06_12_*                       ← procurement/vendor extensions
  2026_06_20_*                       ← stock count tables
```

---

## Design Principles (Enforce in Every Phase)

1. **Dental-first language** — no ERP jargon visible to users
2. **Action before analytics** — show what needs doing before charts
3. **Minimum clicks** — every common action ≤ 2 clicks from any inventory page
4. **Search over navigation** — large search bar always visible
5. **Cards for browsing, tables for data entry** — never mix
6. **Role-appropriate complexity** — assistants see simple; owners see KPIs
7. **Never duplicate data entry** — all finance links are automatic

---

## How to Start Any New Phase

1. Read this document first
2. Read the relevant existing files before writing any code
3. State your plan and estimated output size before starting
4. Build in chunks if output > ~150 lines
5. After each chunk: `php artisan route:clear && php artisan view:clear`
6. Never run `migrate:fresh` or `rollback` without asking
