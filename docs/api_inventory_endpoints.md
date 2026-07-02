# Dentfluence API — Inventory (v1) Endpoint Reference

Backend built 2026-06-26. Use this to wire the Flutter side.

## Conventions

- **Base path:** `/api/v1` (all paths below are relative to it).
- **Auth:** every endpoint except none here is public — send `Authorization: Bearer <token>`.
- **Envelope (all responses):**
  - Success: `{ "success": true, "message": "...", "data": ... }`
  - Error: `{ "success": false, "message": "...", "errors": {...} }`
  - Lists also include `"meta": { current_page, per_page, total, last_page }`.
- **Pagination query params (list endpoints):** `?limit=` (default 20, max 100), `?page=`.
- **Validation errors** return HTTP 422 with `errors` keyed by field. **Domain errors** (insufficient stock, non-draft PO, etc.) return 422 with a human message in `message`.
- **Roles:** reads are open to any logged-in staff. Writes require `admin` or `front_desk`; implant *placement* writes also allow `doctor`.
- **Scope note:** inventory is clinic-wide — responses are NOT filtered by branch (matches the web app).

---

## Lookups

### GET `/inventory/meta`
Dropdown data for every inventory form.
```
data: {
  categories: [{id, name, color}],
  locations:  [{id, name, code, type}],
  vendors:    [{id, vendor_name, contact_person, phone, whatsapp, email}],
  stock_out_movement_types: ["stock_out","treatment_usage","damaged","expired","adjustment"],
  inventory_behaviors: ["consumable","reusable","semi_reusable"],
  implant_component_types: ["fixture","abutment", ...],
  implant_placement_statuses: ["placed","osseointegrating","loaded","failed","explanted"],
  grn_correction_window_hours: 0
}
```

---

## Items & Stock

### GET `/inventory/items`  — stock-by-location list
Query: `category_id`, `location_id`, `search`, `sort` (`product_name|location_name|available_qty`), `dir` (`asc|desc`), `limit`, `page`.
Row shape:
```
{ item_id, product_name, generic_name, consumption_unit, minimum_qty,
  reorder_level, available_qty, location_id, location_name,
  category_id, category_name, sub_type_name }
```

### GET `/inventory/products`  — product master
Query: `search`, `category_id`, `sub_type_id`, `brand`, `location_id`, `stock_level` (`healthy|low|critical|out`), `limit`, `page`.
Row shape:
```
{ id, item_code, product_name, generic_name, brand, company_name,
  category_id, category_name, sub_type_name, variant_name,
  inventory_behavior, purchase_unit, consumption_unit, pieces_per_unit,
  minimum_qty, minimum_order_qty, reorder_level, last_purchase_price,
  mrp, gst_rate, has_expiry, is_reusable, total_qty, is_active }
```

### GET `/inventory/items/{item}`  — one item (detail)
Same shape as a `products` row, plus:
```
stocks: [{ location_id, location_name, available_qty }]
```

### PUT `/inventory/items/{item}`  — update item *(admin, front_desk)*
Body:
```
product_name* (str), generic_name, brand, category_id,
inventory_behavior* (consumable|reusable|semi_reusable),
purchase_unit* (str), consumption_unit* (str), pieces_per_unit* (int>=1),
minimum_qty* (num), minimum_order_qty* (num>=1),
last_purchase_price (num), gst_rate (0-100), has_expiry (bool), is_reusable (bool)
```
Returns the updated item (detail shape).

### POST `/inventory/items/{item}/adjust`  — quick +/- stock *(admin, front_desk)*
Body: `type* (add|remove)`, `qty* (int>=1)`, `location_id* (exists)`, `note`.
Returns the item (detail shape). 422 if removing more than available.

---

## Stock movements

### POST `/inventory/stock-in`  *(admin, front_desk)*
Body:
```
inventory_item_id* (exists), to_location_id* (exists), qty* (num>=0.01),
unit_cost (num), batch_no, expiry_date (date, after today),
manufacturing_date (date), notes
```
Returns `{ movement_id }` (201).

### POST `/inventory/stock-out`  *(admin, front_desk)*
Body:
```
inventory_item_id* (exists), from_location_id* (exists), qty* (num>=0.01),
movement_type* (stock_out|treatment_usage|damaged|expired|adjustment), notes
```
Returns `{ movement_id }` (201). 422 if insufficient stock.

---

## Vendors

### GET `/inventory/vendors`
Query: `search`, `limit`, `page`.
Row shape:
```
{ id, vendor_name, contact_person, phone, whatsapp, email,
  gst_no, address, city, credit_days, is_active }
```
(WhatsApp tap-to-chat: open `https://wa.me/<digits>` using `whatsapp` or `phone`.)

---

## Purchase Orders + GRN

### GET `/inventory/purchase-orders`
Query: `status` (`draft|ordered|partially_received|completed|cancelled|all`), `limit`, `page`.
Row shape:
```
{ id, order_no, vendor_id, vendor_name, order_date, expected_date,
  status, invoice_status, total_amount, gst_amount, item_count }
```

### GET `/inventory/purchase-orders/{po}`  — detail (for the receive screen)
List shape + `notes` + `items`:
```
items: [{ id, inventory_item_id, product_name, qty_ordered,
          qty_received, unit_price, gst_rate, total_price }]
```

### POST `/inventory/purchase-orders`  — create *(admin, front_desk)*
Body:
```
vendor_id* (exists), order_date* (date), expected_date (>= order_date),
status* (draft|ordered), notes,
items*: [ { item_id* (exists), qty* (int>=1), price* (num), gst (0-100) } ]
```
Returns PO detail (201). When `status=ordered`, vendor follow-up Tasks are auto-created.

### PATCH `/inventory/purchase-orders/{po}/mark-ordered`  *(admin, front_desk)*
No body. Draft → ordered. Returns PO list shape. 422 if not a draft.

### POST `/inventory/purchase-orders/{po}/receive`  — record GRN *(admin, front_desk)*
Body:
```
location_id* (exists), received_date* (date), vendor_invoice_no,
lines*: [ { item_id* (exists), qty* (int>=0), unit_cost (num),
            batch_no, expiry (date) } ]
```
Returns `{ grn_number, po_status }` (201). Side effects: stock updated, PO
status recalculated, unpaid bill posted to Finance, delivery follow-up task
auto-closed. 422 if every line qty is 0.

### GET `/inventory/purchase-orders/{po}/whatsapp-message`  — "Send PO" helper
Returns a ready-to-send message + normalized number:
```
data: { whatsapp_number: "91XXXXXXXXXX" | null, message: "Hello ...\n..." }
```
Flutter opens `https://wa.me/<whatsapp_number>?text=<urlEncoded message>`.

---

## Implants

### GET `/inventory/implants/catalog`
Query: `limit` (default 30), `page`.
Row shape:
```
{ id, brand, system, component_type, product_code, description,
  diameter_mm, length_mm, platform, material, unit_price,
  photo_url, is_active, placements_count }
```

### POST `/inventory/implants/catalog`  — add component *(admin, front_desk)*
**multipart/form-data** (optional `photo`). Fields:
```
brand* (str), system, component_type* (fixture|abutment|healing_abutment|
analogue|scan_body|coping|graft|other), product_code, description,
diameter_mm, length_mm, platform, material, unit_price (num),
photo (image jpg/png/webp <=2MB)
```
Returns the catalog row (201).

### POST `/inventory/implants/catalog/{catalogItem}`  — update *(admin, front_desk)*
Same fields as create + `is_active` (bool). POST (not PUT) so a new `photo`
can be uploaded as multipart.

### GET `/inventory/implants/placements`
Query: `limit` (default 30), `page`.
Row shape:
```
{ id, patient_id, patient_name, implant_catalog_id, catalog_name,
  surgeon_id, surgeon_name, lot_number, serial_number, tooth_position,
  surgery_date, implant_brand_freetext, implant_code_freetext,
  status, label_photo_url, notes }
```

### GET `/inventory/implants/form-options`
```
data: { patients: [{id,name,phone}], catalog: [{id,name}],
        surgeons: [{id,name}], statuses: [...] }
```

### POST `/inventory/implants/placements`  — record placement *(admin, front_desk, doctor)*
**multipart/form-data** (optional `label_photo`). Fields:
```
patient_id* (exists), treatment_visit_id, implant_catalog_id, surgeon_id,
lot_number, serial_number, tooth_position, surgery_date* (date),
implant_brand_freetext, implant_code_freetext,
status* (placed|osseointegrating|loaded|failed|explanted), notes,
label_photo (image <=4MB)
```
Returns the placement row (201).

### POST `/inventory/implants/placements/{placement}`  — update *(admin, front_desk, doctor)*
Fields: `status*`, `lot_number`, `serial_number`, `tooth_position`,
`surgery_date`, `notes`, `label_photo`. POST for multipart photo.

---

*Fields marked `*` are required. No new migration is required — all endpoints
reuse existing tables. Run `php artisan optimize:clear` after deploying so the
new routes register.*
