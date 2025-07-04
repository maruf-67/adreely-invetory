# Balance Management System Documentation

## Overview
The Balance Management System automatically tracks and manages user balances for suppliers and customers, including handling overpayments in purchase orders.

## Key Features
- **Automatic Balance Tracking**: User balances are updated automatically when payments are made
- **Overpayment Handling**: Extra payments beyond the order total are added to supplier balance
- **Balance History**: Complete audit trail of all balance changes
- **Manual Adjustments**: Admin can manually adjust balances when needed
- **Comprehensive Reporting**: View balance summaries and payment histories

---

## How It Works

### 1. User Balance Structure
Each user has three balance fields:
- `previous_due`: Outstanding amount from before system implementation
- `previous_credit`: Credit amount from before system implementation  
- `current_balance`: Current running balance (positive = credit, negative = due)

**Total Balance = previous_due - previous_credit + current_balance**

### 2. Overpayment Process
When a payment is made to a purchase order:

1. **Normal Payment**: If payment ≤ due amount, payment is applied normally
2. **Overpayment**: If payment > due amount:
   - Due amount is paid to complete the order
   - Extra amount is added to supplier's `current_balance`
   - `extra_amount` field in purchase order tracks the overpayment
   - Balance history record is created

### 3. Balance History Tracking
Every balance change creates a `UserBalance` record with:
- Previous balance amount
- New balance amount
- Transaction type (credit/debit)
- Description and reference
- Link to source transaction

---

## API Endpoints

### Balance Management
```
GET /api/user-balances/summary                    # Business balance summary
GET /api/user-balances/{userId}                   # User balance details
GET /api/user-balances/{userId}/history           # User balance history
POST /api/user-balances/{userId}/adjustment       # Manual balance adjustment
GET /api/user-balances/{userId}/payments          # User payment history
```

### Purchase Order Payments
```
POST /api/purchase-orders/{id}/payments           # Add payment (handles overpayments)
```

---

## Use Cases

### Use Case 1: Normal Payment
**Scenario**: Purchase order total is $1,000, payment is $800

**Process**:
1. Payment of $800 is recorded
2. Purchase order `paid_amount` = $800
3. Purchase order `due_amount` = $200
4. No balance change for supplier

**Result**:
- Order status: Partial payment
- Supplier balance: No change

### Use Case 2: Overpayment
**Scenario**: Purchase order total is $1,000, payment is $1,200

**Process**:
1. Payment of $1,200 is recorded
2. Purchase order `paid_amount` = $1,200
3. Purchase order `extra_amount` = $200
4. Supplier `current_balance` += $200
5. Balance history record created

**Result**:
- Order status: Fully paid with overpayment
- Supplier balance: +$200 credit
- Message: "Payment added successfully with overpayment of 200.00 added to supplier balance"

### Use Case 3: Multiple Overpayments
**Scenario**: Supplier has multiple orders with overpayments

**Process**:
1. Order 1: $100 overpayment → Supplier balance: $100
2. Order 2: $150 overpayment → Supplier balance: $250
3. Order 3: $50 overpayment → Supplier balance: $300

**Result**:
- Supplier has $300 credit balance
- Can be used for future orders or manual adjustments

### Use Case 4: Manual Balance Adjustment
**Scenario**: Need to adjust supplier balance for returns or corrections

**API Call**:
```json
POST /api/user-balances/{supplierId}/adjustment
{
    "amount": 150.00,
    "balance_type": "debit",
    "description": "Product return adjustment",
    "reference_number": "RET-2025-001"
}
```

**Process**:
1. Supplier balance reduced by $150
2. Balance history record created
3. Audit trail maintained

### Use Case 5: Balance Summary Report
**Scenario**: Admin wants to see all user balances

**API Call**: `GET /api/user-balances/summary`

**Response**:
```json
{
    "success": true,
    "data": {
        "summary": {
            "total_supplier_credit": 1500.00,
            "total_supplier_due": 300.00,
            "total_customer_credit": 800.00,
            "total_customer_due": 2000.00,
            "net_balance": 2000.00
        },
        "suppliers": [
            {
                "user": {
                    "id": 3,
                    "name": "ABC Supplier",
                    "user_type": "supplier"
                },
                "balance": {
                    "total_balance": 250.00,
                    "status": "credit",
                    "absolute_amount": 250.00
                }
            }
        ],
        "customers": [...]
    }
}
```

---

## Database Schema

### purchase_orders table
```sql
extra_amount DECIMAL(15,2) DEFAULT 0  -- Overpayment amount
```

### user_balances table
```sql
user_id BIGINT                        -- User whose balance changed
balanceable_type VARCHAR              -- Source model (Payment, etc.)
balanceable_id BIGINT                 -- Source record ID
amount DECIMAL(15,2)                  -- Transaction amount
balance_type ENUM('credit', 'debit')  -- Transaction type
previous_balance DECIMAL(15,2)        -- Balance before transaction
new_balance DECIMAL(15,2)             -- Balance after transaction
description TEXT                      -- Transaction description
transaction_date DATE                 -- Transaction date
reference_number VARCHAR              -- Reference/invoice number
```

### users table
```sql
previous_due DECIMAL(15,2)            -- Previous outstanding amount
previous_credit DECIMAL(15,2)         -- Previous credit amount
current_balance DECIMAL(15,2)         -- Current running balance
```

---

## Benefits

1. **Automated Tracking**: No manual balance calculations needed
2. **Overpayment Handling**: Excess payments are automatically credited
3. **Complete Audit Trail**: Every balance change is recorded
4. **Flexible Reporting**: Various balance reports available
5. **Manual Override**: Admin can make manual adjustments when needed
6. **Multi-tenant Safe**: All balances are business-specific

---

## Technical Implementation

### Key Models
- `User`: Stores balance fields and balance methods
- `UserBalance`: Tracks balance history
- `PurchaseOrder`: Handles overpayment logic
- `Payment`: Links to purchase orders

### Key Methods
- `User::updateBalance()`: Updates user balance
- `User::getBalanceStatus()`: Gets balance summary
- `PurchaseOrder::handleOverpayment()`: Processes overpayments
- `UserBalance::createRecord()`: Creates balance history

### Controllers
- `UserBalanceController`: Balance management endpoints
- `PurchaseOrderController`: Enhanced payment handling

This system provides comprehensive balance management with automatic overpayment handling and complete audit trails for all transactions.
