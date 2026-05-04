# WebSocket Artifacts

## Final Approach

Ysabelle Retail currently uses a Laravel-native realtime demo stack built from:

- domain events:
  `OrderPlaced`, `InventoryStockChanged`
- listeners:
  `RecordOrderActivity`, `RecordInventoryActivity`
- persistence:
  `audit_logs` table as the admin activity stream
- delivery:
  admin-side polling fallback through `GET /admin/realtime/feed`

This release does not require Reverb, Pusher, or any paid websocket service. Persistent commerce data remains the source of truth, and live updates are additive.

## Channel Map

### Admin Activity Feed

- Delivery route:
  `/admin/realtime/feed`
- Audience:
  authenticated admins only
- Protection:
  `auth` + `admin` middleware
- Visibility:
  private admin-only operational activity
- Returned data:
  notification toasts and the latest admin activity feed entries

## Event Payload Catalog

### `commerce.online_order.placed`

- Trigger:
  successful online checkout commit
- Payload:
  `order_number`, `customer_name`, `grand_total`, `payment_method`, `payment_status`
- Sensitivity:
  internal admin notification only

### `commerce.walk_in_sale.completed`

- Trigger:
  successful walk-in POS completion
- Payload:
  `order_number`, `customer_name`, `grand_total`, `payment_method`, `payment_status`
- Sensitivity:
  internal admin notification only

### `inventory.stock_changed`

- Trigger:
  any stock movement committed through online sale, walk-in sale, manual stock, product-form adjustment, or batch import
- Payload:
  `movement_type`, `quantity_delta`, `sku`, `product_name`, `variant_name`, `current_quantity`, `reorder_level`, `stock_status`, `reference_number`
- Sensitivity:
  internal admin notification only

## Authorization Boundary

- Guests:
  blocked
- Customers:
  blocked
- Admins:
  allowed
- Public storefront:
  no realtime inventory internals exposed

## Delivery Failure Behavior

- Polling interval:
  10 seconds
- If polling fails:
  the admin UI keeps working normally and retries on the next interval
- Source of truth:
  dashboard, orders, inventory, and reports still read from the database
- User experience:
  the live status badge changes to a retry state instead of breaking the page

## Local Commands

- `composer serve:local`
- `npm run dev`
- `composer check`
