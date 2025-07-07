# Payment Status Management Documentation

## Overview
The payment status management system handles different payment outcomes, especially for payment methods like cheques that can bounce or be cancelled. The system properly manages balances and order statuses based on payment state changes.

## Payment Statuses

### Available Statuses
- **`pending`**: Payment submitted but not yet cleared (default for cheques, bank transfers)
- **`clear`**: Payment successfully processed and cleared
- **`bounced`**: Payment failed (e.g., insufficient funds, invalid cheque)
- **`cancelled`**: Payment was cancelled before processing

### Auto-Status Assignment
The system automatically assigns default statuses based on payment method:

```php
// Cheques and bank transfers start as pending
if (payment_method_type == 'cheque' || payment_method_type == 'bank_transfer') {
    default_status = 'pending'
} else {
    // Cash, mobile banking, etc. are immediately clear
    default_status = 'clear'
}
```

---

## How Payment Status Affects Balances

### Key Principle
**Only `clear` payments count towards order balances and generate overpayments.**

### Status Change Effects

#### 1. Pending → Clear
- Payment amount is added to order's paid total
- If this creates overpayment, supplier balance is credited
- Balance history record is created

#### 2. Clear → Bounced/Cancelled  
- Payment amount is removed from order's paid total
- If overpayment existed, supplier balance is debited (reversed)
- Balance history record shows reversal

#### 3. Pending → Bounced/Cancelled
- No balance impact (was never counted)
- Order totals remain unchanged

---

## API Endpoints

### Payment Management
```
GET /api/payments                              # List all payments with filters
GET /api/payments/summary                      # Payment summary by status
GET /api/payments/pending                      # Get pending payments needing attention
PUT /api/payments/{id}/status                  # Update single payment status
PUT /api/payments/bulk-status                  # Bulk update payment statuses
```

### Purchase Order Payments
```
POST /api/purchase-orders/{id}/payments        # Add payment (auto-status based on method)
PUT /api/purchase-orders/{id}/payments/{paymentId}/status  # Update payment status
```

---

## Use Cases

### Use Case 1: Cheque Payment Workflow

#### Step 1: Receive Cheque
```json
POST /api/purchase-orders/1/payments
{
    "payment_method_id": 3,  // Cheque payment method
    "amount": 1000.00,
    "transaction_date": "2025-07-04",
    "reference_number": "CHQ-12345",
    "details": "Payment by cheque"
}
```

**Result:**
- Payment status: `pending`
- Order paid_amount: No change (still 0)
- Supplier balance: No change
- Message: "Payment added as pending. Order balance will be updated when payment is cleared."

#### Step 2: Cheque Clears
```json
PUT /api/payments/1/status
{
    "status": "clear",
    "reason": "Cheque cleared successfully"
}
```

**Result:**
- Payment status: `clear`
- Order paid_amount: +$1000
- If overpayment: Supplier balance updated
- Balance history: Created if overpayment

#### Step 3: Cheque Bounces (Alternative)
```json
PUT /api/payments/1/status
{
    "status": "bounced",
    "reason": "Insufficient funds"
}
```

**Result:**
- Payment status: `bounced`
- Order paid_amount: No change (was never counted)
- Supplier balance: No change
- Message: "Payment status updated from pending to bounced"

### Use Case 2: Cheque Overpayment Scenario

#### Order Total: $1000, Cheque Payment: $1200

**Step 1: Submit Cheque**
- Status: `pending`
- Order paid: $0
- Due amount: $1000
- Supplier balance: $0

**Step 2: Cheque Clears**
- Status: `clear`
- Order paid: $1200
- Due amount: $0
- Overpayment: $200
- Supplier balance: +$200

**Step 3: Cheque Bounces Later**
- Status: `bounced`
- Order paid: $0 (reverted)
- Due amount: $1000 (back to original)
- Overpayment: $0 (reversed)
- Supplier balance: $0 (debited $200)

### Use Case 3: Mixed Payment Methods

#### Order Total: $1500

**Payment 1: Cash $800**
```json
{
    "payment_method_id": 1,  // Cash
    "amount": 800.00
}
```
- Status: `clear` (immediate)
- Order paid: $800
- Due: $700

**Payment 2: Cheque $900**
```json
{
    "payment_method_id": 3,  // Cheque
    "amount": 900.00
}
```
- Status: `pending`
- Order paid: $800 (unchanged)
- Due: $700 (unchanged)

**Cheque Clears:**
- Order paid: $1700 ($800 + $900)
- Due: $0
- Overpayment: $200 to supplier balance

### Use Case 4: Bulk Status Updates

#### Update Multiple Pending Cheques
```json
PUT /api/payments/bulk-status
{
    "payment_ids": [1, 2, 3, 4],
    "status": "clear",
    "reason": "Bank confirmed all cheques cleared"
}
```

**Result:**
- All 4 payments marked as cleared
- Order balances updated for each
- Overpayments calculated and applied
- Balance history records created

---

## Payment Summary Dashboard

### API Response Example
```json
GET /api/payments/summary

{
    "success": true,
    "data": {
        "status_summary": {
            "clear": {
                "count": 45,
                "total_amount": "125000.00"
            },
            "pending": {
                "count": 12,
                "total_amount": "35000.00"
            },
            "bounced": {
                "count": 3,
                "total_amount": "8000.00"
            }
        },
        "method_breakdown": {
            "Cash": {
                "clear": { "count": 30, "total_amount": "75000.00" }
            },
            "Cheque": {
                "pending": { "count": 10, "total_amount": "30000.00" },
                "clear": { "count": 15, "total_amount": "50000.00" },
                "bounced": { "count": 3, "total_amount": "8000.00" }
            }
        },
        "totals": {
            "cleared": "125000.00",
            "pending": "35000.00",
            "bounced": "8000.00"
        }
    }
}
```

---

## Pending Payments Management

### Get Pending Payments Needing Attention
```json
GET /api/payments/pending?method_type=cheque&days_old=7

{
    "success": true,
    "data": {
        "payments": [...],
        "summary": {
            "total_pending_amount": "35000.00",
            "total_pending_count": 12,
            "oldest_payment": "2025-06-20"
        }
    }
}
```

### Filters Available:
- `method_type`: Filter by payment method (cheque, bank_transfer, etc.)
- `days_old`: Show payments older than X days
- `start_date` / `end_date`: Date range filter

---

## Database Changes

### Enhanced Payment Model
```php
// Status constants
const STATUS_PENDING = 'pending';
const STATUS_CLEAR = 'clear';
const STATUS_BOUNCED = 'bounced';
const STATUS_CANCELLED = 'cancelled';

// Status checking methods
isCleared(), isPending(), isBounced(), isCancelled()

// Status update with balance handling
updateStatus($newStatus, $reason)
```

### Purchase Order Updates
- Only counts `clear` payments in `paid_amount`
- `updatePaidAmount()` method filters by status
- Balance calculations consider payment status

---

## Benefits

1. **Accurate Financial Reporting**: Only cleared payments count in balances
2. **Cheque Management**: Proper handling of cheque bounces and delays
3. **Audit Trail**: Complete history of payment status changes
4. **Flexible Workflow**: Support for various payment method lifecycles
5. **Automatic Reversals**: Balance adjustments when payments fail
6. **Bulk Operations**: Efficient management of multiple payments

This system ensures financial accuracy by properly tracking payment statuses and only counting cleared payments in order balances and supplier credits.
