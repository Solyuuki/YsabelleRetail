# Naming Rules

## Purpose
Good naming reduces defects, review friction, and architectural drift. Every file and identifier should communicate scope, responsibility, and domain intent without guesswork.

## General Naming Principles
- be explicit
- be stable
- be domain-oriented
- avoid abbreviations unless universally understood
- avoid names that describe implementation trivia instead of responsibility

## File Naming Rules
### Standards Files
- use fixed numeric prefixes
- use lowercase
- use hyphen-separated filenames
- example: `06-coding-standards.md`

### Artifact Files
- use lowercase
- use hyphen-separated filenames
- include domain relevance in the filename
- example: `payment-artifacts.md`

## Future Runtime Naming Standards
This section governs implementation-phase naming once coding begins.

### Controllers
- name by user-facing responsibility
- examples:
  - `StorefrontController`
  - `CartController`
  - `CheckoutController`
  - `AdminOrderController`

Avoid:
- `MainController`
- `ProcessController`
- `HelperController`

### Form Requests
- name by action and domain
- examples:
  - `AddCartItemRequest`
  - `StartCheckoutRequest`
  - `UpdateProductRequest`

### Services
- name by business responsibility
- examples:
  - `CheckoutService`
  - `OrderTrackingService`
  - `ProductRecommendationService`

### Actions
- use a verb-led name
- examples:
  - `CreateOrderAction`
  - `ReserveInventoryAction`
  - `SendSupportReplyAction`

### Queries
- name by read model or retrieval intent
- examples:
  - `ProductCatalogQuery`
  - `AdminOrderOverviewQuery`

### Events
- use past-tense domain events
- examples:
  - `OrderStatusUpdated`
  - `PaymentVerified`
  - `SupportMessageCreated`

### Notifications
- use destination-oriented event outcome names
- examples:
  - `OrderPlacedNotification`
  - `PasswordResetRequestedNotification`

### Policies
- use model or domain scope
- examples:
  - `OrderPolicy`
  - `SupportTicketPolicy`

### Enums
- use singular noun plus intent
- examples:
  - `OrderStatus`
  - `PaymentStatus`
  - `SupportTicketPriority`

## Route Naming
Use dot notation and reflect the user journey or admin context.

Examples:
- `home`
- `store.index`
- `store.products.show`
- `cart.index`
- `checkout.review`
- `orders.show`
- `account.orders.index`
- `admin.dashboard`
- `admin.products.index`
- `support.tickets.show`

Rules:
- do not encode HTTP verbs in route names
- keep route names predictable
- use `admin.` prefix for admin routes
- use `account.` prefix for customer account routes

## View Naming
- group by area and action
- examples:
  - `store/index.blade.php`
  - `store/product/show.blade.php`
  - `checkout/review.blade.php`
  - `admin/dashboard.blade.php`

### Blade Components
- use reusable noun or noun-phrase names
- examples:
  - `product-card`
  - `order-timeline`
  - `support-chat-widget`

## Database Naming
### Tables
- use plural snake case
- examples:
  - `products`
  - `product_variants`
  - `support_tickets`

### Pivot Tables
- use alphabetical singular names joined with underscore where practical

### Columns
- use singular, clear names
- status fields should reference enum meaning
- foreign keys should use `_id`
- UUID references should use `_uuid` when not primary

### Indexes
- use explicit names when generated names would be unclear

## Test Naming
- test class or file should describe the feature behavior
- examples:
  - `CheckoutFlowTest`
  - `OrderStatusTransitionMatrixTest`
  - `ProductRecommendationServiceTest`

## Forbidden Naming Patterns
Do not use:
- `temp`
- `new`
- `old`
- `helper`
- `misc`
- `stuff`
- `final2`
- vague acronyms not defined anywhere

## Naming Review Rule
If a reviewer cannot guess the file responsibility from its name, the name is not good enough.
