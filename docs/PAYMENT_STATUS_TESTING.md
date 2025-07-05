# Payment Status Testing Guide

## Quick Test Scenarios

### 1. Test Cheque Payment Workflow

#### Create Purchase Order
```bash
POST /api/purchase-orders
{
    "supplier_id": 3,
    "order_date": "2025-07-04",
    "items": [{"product_id": 1, "quantity_ordered": 10, "unit_price": 100.00}]
}
```

#### Submit Cheque Payment
```bash
POST /api/purchase-orders/1/payments
{
    "payment_method_id": 3,  // Cheque method
    "amount": 1200.00,       // Overpayment
    "transaction_date": "2025-07-04",
    "reference_number": "CHQ-001"
}
```

**Expected Result:**
```json
{
    "success": true,
    "message": "Payment added as pending. Order balance will be updated when payment is cleared.",
    "data": {
        "payment_status": "pending",
        "overpayment_amount": 0,     // No overpayment yet
        "cleared_payments_total": 0,  // Still 0
        "pending_payments_total": 1200
    }
}
```

#### Clear the Cheque
```bash
PUT /api/payments/1/status
{
    "status": "clear",
    "reason": "Cheque cleared by bank"
}
```

**Expected Result:**
```json
{
    "success": true,
    "message": "Payment status updated from pending to clear",
    "data": {
        "payment_summary": {
            "cleared_total": 1200,
            "remaining_due": 0
        },
        "supplier_balance": 200  // $200 overpayment added
    }
}
```

#### Test Cheque Bounce
```bash
PUT /api/payments/1/status
{
    "status": "bounced",
    "reason": "Insufficient funds"
}
```

**Expected Result:**
- Supplier balance reduced by $200
- Order balance reverted to $0 paid
- Balance history shows reversal

### 2. Test Mixed Payment Methods

#### Order Total: $1000

#### Payment 1: Cash $400 (immediate clear)
```bash
POST /api/purchase-orders/1/payments
{
    "payment_method_id": 1,  // Cash
    "amount": 400
}
```
- Status: `clear`
- Order paid: $400

#### Payment 2: Cheque $800 (pending)
```bash
POST /api/purchase-orders/1/payments
{
    "payment_method_id": 3,  // Cheque
    "amount": 800
}
```
- Status: `pending`
- Order paid: Still $400 (cheque not counted yet)

#### Clear Cheque (creates overpayment)
```bash
PUT /api/payments/2/status
{
    "status": "clear"
}
```
- Order paid: $1200 ($400 + $800)
- Overpayment: $200 to supplier

### 3. Test Pending Payments Dashboard

#### Get All Pending Payments
```bash
GET /api/payments/pending
```

#### Get Old Cheques (over 7 days)
```bash
GET /api/payments/pending?method_type=cheque&days_old=7
```

#### Get Payment Summary
```bash
GET /api/payments/summary
```

### 4. Test Bulk Status Updates

#### Clear Multiple Cheques
```bash
PUT /api/payments/bulk-status
{
    "payment_ids": [1, 2, 3],
    "status": "clear",
    "reason": "Bank confirmation received"
}
```

#### Mark Multiple as Bounced
```bash
PUT /api/payments/bulk-status
{
    "payment_ids": [4, 5],
    "status": "bounced",
    "reason": "Bank returned cheques"
}
```

## Verification Checklist

### ✅ Payment Status Logic
- [ ] Cheque payments default to `pending`
- [ ] Cash payments default to `clear`
- [ ] Only `clear` payments count in order totals
- [ ] Pending payments don't affect balances

### ✅ Status Change Effects
- [ ] Pending→Clear: Updates order balance, creates overpayment if applicable
- [ ] Clear→Bounced: Reverses balance, removes overpayment
- [ ] Pending→Bounced: No balance impact

### ✅ Overpayment Handling
- [ ] Overpayments only created for `clear` payments
- [ ] Supplier balance updated correctly
- [ ] Balance history records created
- [ ] Overpayments reversed when payments bounce

### ✅ API Responses
- [ ] Correct status messages based on payment method
- [ ] Payment summary shows correct breakdowns
- [ ] Pending payments dashboard works
- [ ] Bulk updates process correctly

### ✅ Edge Cases
- [ ] Multiple status changes on same payment
- [ ] Mixed payment methods on same order
- [ ] Overpayment with subsequent payment failures
- [ ] Bulk operations with partial failures

## Common Test Data

### Payment Methods
```sql
-- Cash (immediate clear)
INSERT INTO payment_methods (business_id, name, type) VALUES (1, 'Cash', 'cash');

-- Cheque (pending by default)
INSERT INTO payment_methods (business_id, name, type) VALUES (1, 'Cheque', 'cheque');

-- Bank Transfer (pending by default)
INSERT INTO payment_methods (business_id, name, type) VALUES (1, 'Bank Transfer', 'bank_transfer');
```

### Test Scenarios Summary
1. **Normal Cheque Flow**: Pending → Clear
2. **Bounced Cheque**: Pending → Bounced
3. **Overpayment Reversal**: Clear → Bounced with balance adjustment
4. **Mixed Methods**: Cash + Cheque on same order
5. **Bulk Operations**: Multiple payments status change

This comprehensive testing ensures the payment status system handles all real-world scenarios correctly.
