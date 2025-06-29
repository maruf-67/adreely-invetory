# Inventory Management System - Database Design

## 1. Introduction

This document outlines the proposed database schema for the Inventory Management System. The design is based on the provided requirements and aims to be scalable, maintainable, and efficient.

A key requirement is that "each admin will have individual project". This suggests a **multi-tenant architecture**. We will implement this by adding a `business_id` to most tables. This ensures that data for each business (run by an admin) is isolated.

## 2. Core Concepts & Relationships

*   **Business:** The top-level entity representing an admin's entire workspace/project.
*   **Users:** A central table for all actors: admins, staff, and various party types (Suppliers, Retailers, etc.). Users are scoped to a business.
*   **Products:** The items that are managed in the inventory. Products are also scoped to a business.
*   **Orders (Purchase & Sales):** These documents track the flow of products into and out of the inventory.
*   **Inventory Tracking:** A dedicated history table will log every stock movement for complete traceability.
*   **Financials:** Separate tables will manage payments, expenses, salaries, and investments to keep financial records clean.

## 3. Table Definitions

Here is the detailed breakdown of each table, its columns, and relationships.

---

### `businesses`

Stores information about each admin's business/company.

| Column Name | Data Type | Constraints | Description |
| :--- | :--- | :--- | :--- |
| `id` | `bigint` | Primary Key, Unsigned, Auto-Increment | Unique identifier for the business. |
| `name` | `string` | Not Null | The name of the business. |
| `owner_id` | `bigint` | Foreign Key (`users.id`) | The admin user who owns this business. |
| `created_at` | `timestamp` | | |
| `updated_at` | `timestamp` | | |

---

### `users`

A central table for all people interacting with the system, including admins, staff, and business contacts (parties).

| Column Name | Data Type | Constraints | Description |
| :--- | :--- | :--- | :--- |
| `id` | `bigint` | Primary Key, Unsigned, Auto-Increment | Unique identifier for the user. |
| `business_id` | `bigint` | Foreign Key (`businesses.id`), Nullable | The business this user belongs to. Null for the system owner/super-admin if any. |
| `name` | `string` | Not Null | Full name of the user/contact. |
| `email` | `string` | Unique, Nullable | Email address. Can be null for contacts not needing login. |
| `phone` | `string` | Not Null, Unique | Phone number. Marked as required in the spec. |
| `password` | `string` | Nullable | Hashed password for users who can log in (admin, staff). |
| `image` | `string` | Nullable | URL or path to profile photo. |
| `address` | `text` | Nullable | Physical address. |
| `user_type` | `enum` | Not Null, Default: 'guest' | Type of user: `admin`, `staff`, `supplier`, `retailer`, `dealer`, `wholesaler`, `guest`. |
| `party_type` | `enum` | Nullable | For contacts: `Regular`, `Priority`. |
| `previous_due` | `decimal(15,2)` | Nullable | Opening balance due from this party. |
| `previous_credit` | `decimal(15,2)` | Nullable | Opening balance credit for this party. |
| `current_balance`| `decimal(15,2)` | Not Null, Default: 0 | Calculated current balance. Positive means business owes them, negative means they owe the business. |
| `created_by` | `bigint` | Foreign Key (`users.id`), Nullable | The user (admin/staff) who created this contact. |
| `remember_token`| `string` | Nullable | |
| `email_verified_at`| `timestamp` | Nullable | |
| `created_at` | `timestamp` | | |
| `updated_at` | `timestamp` | | |

---

### `products`

Stores all products for a business.

| Column Name | Data Type | Constraints | Description |
| :--- | :--- | :--- | :--- |
| `id` | `bigint` | Primary Key, Unsigned, Auto-Increment | Unique identifier for the product. |
| `business_id` | `bigint` | Foreign Key (`businesses.id`) | The business this product belongs to. |
| `name` | `string` | Not Null | Product name. |
| `sku` | `string` | Unique per business, Nullable | Stock Keeping Unit. |
| `image` | `string` | Nullable | URL or path to product image. |
| `category_id` | `bigint` | Foreign Key (`categories.id`), Nullable | Link to the product category. |
| `brand_id` | `bigint` | Foreign Key (`brands.id`), Nullable | Link to the product brand. |
| `unit_id` | `bigint` | Foreign Key (`units.id`) | Link to the product unit (e.g., kg, pcs). |
| `description` | `text` | Nullable | Detailed product description. |
| `purchase_price`| `decimal(15,2)`| Not Null | The price at which the product is bought. |
| `selling_price` | `decimal(15,2)`| Not Null | The price at which the product is sold. |
| `quantity` | `integer` | Not Null, Default: 0 | Current stock level. |
| `low_stock_threshold` | `integer` | Not Null, Default: 0 | Threshold for low stock alerts. |
| `created_at` | `timestamp` | | |
| `updated_at` | `timestamp` | | |

---

### `categories`, `brands`, `units`

Lookup tables for product attributes.

**`categories`**
*   `id` (PK)
*   `business_id` (FK)
*   `name` (string)

**`brands`**
*   `id` (PK)
*   `business_id` (FK)
*   `name` (string)

**`units`**
*   `id` (PK)
*   `business_id` (FK)
*   `name` (string, e.g., 'pcs', 'kg', 'liter')
*   `short_name` (string, e.g., 'pc', 'kg', 'l')

---

### `purchase_orders`

Tracks orders placed with suppliers.

| Column Name | Data Type | Constraints | Description |
| :--- | :--- | :--- | :--- |
| `id` | `bigint` | Primary Key, Unsigned, Auto-Increment | Unique identifier for the purchase order. |
| `business_id` | `bigint` | Foreign Key (`businesses.id`) | |
| `supplier_id` | `bigint` | Foreign Key (`users.id`) | The supplier for this order. |
| `order_date` | `date` | Not Null | |
| `expected_delivery_date` | `date` | Nullable | |
| `status` | `enum` | Not Null | `Pending`, `Partial`, `Completed`, `Cancelled`. |
| `sub_total` | `decimal(15,2)` | Not Null | Total price before discounts/taxes. |
| `discount` | `decimal(15,2)` | Default: 0 | Discount on the order. |
| `total_amount` | `decimal(15,2)` | Not Null | Final amount of the order. |
| `paid_amount` | `decimal(15,2)` | Default: 0 | Amount paid so far for this order. |
| `due_amount` | `decimal(15,2)` | | Calculated `total_amount - paid_amount`. |
| `created_by` | `bigint` | Foreign Key (`users.id`) | User who created the order. |
| `created_at` | `timestamp` | | |
| `updated_at` | `timestamp` | | |

---

### `purchase_order_items`

Line items for each purchase order.

| Column Name | Data Type | Constraints | Description |
| :--- | :--- | :--- | :--- |
| `id` | `bigint` | Primary Key, Unsigned, Auto-Increment | |
| `purchase_order_id` | `bigint` | Foreign Key (`purchase_orders.id`) | |
| `product_id` | `bigint` | Foreign Key (`products.id`) | |
| `quantity_ordered`| `integer` | Not Null | Quantity of the product ordered. |
| `quantity_received`| `integer` | Default: 0 | Quantity received so far. |
| `unit_price` | `decimal(15,2)` | Not Null | Price per unit for this line item. |
| `total_price` | `decimal(15,2)` | Not Null | `quantity_ordered * unit_price`. |
| `created_at` | `timestamp` | | |
| `updated_at` | `timestamp` | | |

---

### `sales_orders`

Tracks sales made to customers.

| Column Name | Data Type | Constraints | Description |
| :--- | :--- | :--- | :--- |
| `id` | `bigint` | Primary Key, Unsigned, Auto-Increment | |
| `business_id` | `bigint` | Foreign Key (`businesses.id`) | |
| `customer_id` | `bigint` | Foreign Key (`users.id`) | The customer for this order. |
| `invoice_number`| `string` | Unique per business | Auto-generated invoice number. |
| `order_date` | `date` | Not Null | |
| `status` | `enum` | Not Null | `Pending`, `Processing`, `Shipped`, `Delivered`, `Cancelled`. |
| `sub_total` | `decimal(15,2)` | Not Null | |
| `vat_gst_percent`| `decimal(5,2)` | Default: 0 | VAT/GST percentage applied. |
| `discount` | `decimal(15,2)` | Default: 0 | |
| `total_amount` | `decimal(15,2)` | Not Null | Final amount of the order. |
| `paid_amount` | `decimal(15,2)` | Default: 0 | |
| `due_amount` | `decimal(15,2)` | | Calculated `total_amount - paid_amount`. |
| `created_by` | `bigint` | Foreign Key (`users.id`) | User who created the order (admin/staff). |
| `created_at` | `timestamp` | | |
| `updated_at` | `timestamp` | | |

---

### `sales_order_items`

Line items for each sales order.

| Column Name | Data Type | Constraints | Description |
| :--- | :--- | :--- | :--- |
| `id` | `bigint` | Primary Key, Unsigned, Auto-Increment | |
| `sales_order_id` | `bigint` | Foreign Key (`sales_orders.id`) | |
| `product_id` | `bigint` | Foreign Key (`products.id`) | |
| `quantity` | `integer` | Not Null | |
| `unit_price` | `decimal(15,2)` | Not Null | |
| `total_price` | `decimal(15,2)` | Not Null | `quantity * unit_price`. |
| `created_at` | `timestamp` | | |
| `updated_at` | `timestamp` | | |

---

### `inventory_histories`

Logs every single stock movement for auditing and tracking.

| Column Name | Data Type | Constraints | Description |
| :--- | :--- | :--- | :--- |
| `id` | `bigint` | Primary Key, Unsigned, Auto-Increment | |
| `business_id` | `bigint` | Foreign Key (`businesses.id`) | |
| `product_id` | `bigint` | Foreign Key (`products.id`) | |
| `user_id` | `bigint` | Foreign Key (`users.id`), Nullable | The user responsible for the change. |
| `type` | `enum` | Not Null | `stock-in`, `stock-out`, `adjustment`. |
| `quantity_change`| `integer` | Not Null | Positive for stock-in, negative for stock-out. |
| `reason` | `string` | Nullable | e.g., 'Purchase Order Received', 'Sales Order Fulfilled', 'Stock Correction'. |
| `reference_id` | `bigint` | Nullable | ID of the source document (e.g., purchase_order_id, sales_order_id). |
| `reference_type`| `string` | Nullable | The model of the source document (e.g., 'App\Models\PurchaseOrder'). Polymorphic relation. |
| `created_at` | `timestamp` | | |
| `updated_at` | `timestamp` | | |

---

### `payments`

A polymorphic table to store all payment transactions.

| Column Name | Data Type | Constraints | Description |
| :--- | :--- | :--- | :--- |
| `id` | `bigint` | Primary Key, Unsigned, Auto-Increment | |
| `business_id` | `bigint` | Foreign Key (`businesses.id`) | |
| `paymentable_id`| `bigint` | Not Null | ID of the order (Purchase or Sales). |
| `paymentable_type`|`string` | Not Null | The model of the order ('App\Models\PurchaseOrder' or 'App\Models\SalesOrder'). |
| `payment_method_id`|`bigint` | Foreign Key (`payment_methods.id`) | |
| `amount` | `decimal(15,2)` | Not Null | |
| `transaction_date`| `date` | Not Null | |
| `details` | `text` | Nullable | e.g., Cheque number, transaction ID. |
| `status` | `enum` | Nullable | For Cheques: `Pending`, `Clear`, `Hold`, `Rejected`. |
| `created_by` | `bigint` | Foreign Key (`users.id`) | |
| `created_at` | `timestamp` | | |
| `updated_at` | `timestamp` | | |

---

### `payment_methods`

Stores the dynamic payment methods for a business.

| Column Name | Data Type | Constraints | Description |
| :--- | :--- | :--- | :--- |
| `id` | `bigint` | Primary Key, Unsigned, Auto-Increment | |
| `business_id` | `bigint` | Foreign Key (`businesses.id`) | |
| `gateway_name` | `string` | Not Null | e.g., 'Bank Transfer', 'Cash', 'Cheque'. |
| `account_name` | `string` | Nullable | |
| `account_number`| `string` | Nullable | |
| `branch` | `string` | Nullable | |
| `currency` | `string` | Nullable | |
| `is_active` | `boolean` | Default: true | |
| `created_at` | `timestamp` | | |
| `updated_at` | `timestamp` | | |

---

### `expenses` & `expense_categories`

**`expense_categories`**
*   `id` (PK)
*   `business_id` (FK)
*   `name` (string)

**`expenses`**
*   `id` (PK)
*   `business_id` (FK)
*   `expense_category_id` (FK)
*   `expense_date` (date)
*   `name` (string, e.g., 'Office Rent')
*   `amount` (decimal)
*   `reference_number` (string, nullable)
*   `note` (text, nullable)
*   `created_by` (FK to `users.id`)

---

### Other Tables (Simplified)

*   **`salaries`**: `id`, `business_id`, `employee_id` (FK to `users.id`), `month` (date), `amount` (decimal), `type` (enum: 'Advance', 'Salary'), `created_by` (FK to `users.id`).
*   **`investors`**: `id`, `business_id`, `name`, `contact_info`.
*   **`investments`**: `id`, `business_id`, `investor_id` (FK), `amount` (decimal), `investment_date` (date), `status` (enum: 'Active', 'Closed').
*   **`extra_incomes`**: `id`, `business_id`, `income_type_id` (FK), `amount` (decimal), `date` (date), `description` (text).
*   **`extra_income_types`**: `id`, `business_id`, `name` (string).
