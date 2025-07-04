# Balance Management API Testing Guide

## Quick Test Steps

### 1. Create a Purchase Order
```bash
POST /api/purchase-orders
Content-Type: application/json
Authorization: Bearer {token}

{
    "supplier_id": 3,
    "order_date": "2025-07-04",
    "items": [
        {
            "product_id": 1,
            "quantity_ordered": 10,
            "unit_price": 100.00
        }
    ]
}
```

### 2. Make Normal Payment
```bash
POST /api/purchase-orders/1/payments
Content-Type: application/json
Authorization: Bearer {token}

{
    "payment_method_id": 1,
    "amount": 800.00,
    "transaction_date": "2025-07-04",
    "details": "Partial payment"
}
```

### 3. Make Overpayment
```bash
POST /api/purchase-orders/1/payments
Content-Type: application/json
Authorization: Bearer {token}

{
    "payment_method_id": 1,
    "amount": 1200.00,
    "transaction_date": "2025-07-04",
    "details": "Full payment with extra"
}
```

**Expected Response:**
```json
{
    "success": true,
    "message": "Payment added successfully with overpayment of 200.00 added to supplier balance",
    "data": {
        "payment": {...},
        "purchase_order": {...},
        "overpayment_amount": 200.00,
        "supplier_balance": 200.00
    }
}
```

### 4. Check User Balance
```bash
GET /api/user-balances/3
Authorization: Bearer {token}
```

**Expected Response:**
```json
{
    "success": true,
    "data": {
        "user": {
            "id": 3,
            "name": "Supplier Name",
            "user_type": "supplier"
        },
        "balance_summary": {
            "previous_due": 0.00,
            "previous_credit": 0.00,
            "current_balance": 200.00,
            "total_balance": 200.00,
            "status": "credit",
            "absolute_amount": 200.00
        }
    }
}
```

### 5. Check Balance History
```bash
GET /api/user-balances/3/history
Authorization: Bearer {token}
```

### 6. Check Business Balance Summary
```bash
GET /api/user-balances/summary
Authorization: Bearer {token}
```

### 7. Make Manual Balance Adjustment
```bash
POST /api/user-balances/3/adjustment
Content-Type: application/json
Authorization: Bearer {token}

{
    "amount": 50.00,
    "balance_type": "debit",
    "description": "Manual adjustment for return",
    "reference_number": "ADJ-001"
}
```

## Test Results to Verify

1. **Overpayment is captured** in `purchase_orders.extra_amount`
2. **Supplier balance increases** by overpayment amount
3. **Balance history record** is created automatically
4. **Purchase order shows** correct paid_amount and due_amount
5. **API returns** overpayment confirmation message

## Testing Scenarios

### Scenario 1: Multiple Overpayments
- Create multiple purchase orders for same supplier
- Make overpayments on each
- Verify balance accumulates correctly

### Scenario 2: Mixed Payments
- Make partial payment (under total)
- Make exact payment (equal to remaining)
- Make overpayment (over remaining)
- Verify each payment type works correctly

### Scenario 3: Manual Adjustments
- Make overpayment to build credit
- Use manual adjustment to reduce balance
- Verify balance history shows both transactions

This system automatically handles all balance calculations and provides complete audit trails for financial tracking.
