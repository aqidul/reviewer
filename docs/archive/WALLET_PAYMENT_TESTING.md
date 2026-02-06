# Wallet Payment Feature - Testing Guide

## Version 2.0.2 - Wallet Payment for Review Requests

This document describes how to test the new wallet payment feature for review requests.

## Prerequisites

1. **Database Migration**
   ```bash
   mysql -u reviewflow_user -p reviewflow < migrations/add_wallet_payment_gateway.sql
   ```

2. **Seller must have wallet balance**
   - Login as admin
   - Go to "Manage Seller Wallet"
   - Add balance to a test seller's wallet OR
   - Have seller complete a wallet recharge request through bank transfer

## Test Scenarios

### Test 1: Payment Page with Sufficient Balance

**Steps:**
1. Login as a seller with wallet balance ≥ ₹295
2. Create a new review request (e.g., ₹200 product, which becomes ₹295 with commission & GST)
3. Click "Proceed to Payment"
4. Observe the payment page

**Expected Results:**
- ✅ Wallet balance displayed in blue info box
- ✅ "Pay with Wallet" card shows in green border
- ✅ Wallet payment button is enabled
- ✅ Shows "Instant - No additional fees" message
- ✅ "Pay with Razorpay" card also visible
- ✅ Both payment amounts match the total (₹295)

### Test 2: Payment Page with Insufficient Balance

**Steps:**
1. Login as a seller with wallet balance < ₹295
2. Create a new review request requiring ₹295
3. Click "Proceed to Payment"
4. Observe the payment page

**Expected Results:**
- ✅ Wallet balance displayed correctly
- ✅ "Pay with Wallet" card shows with grey border
- ✅ Red warning message: "Insufficient balance"
- ✅ Wallet payment button replaced with "Add Money" link
- ✅ "Add Money" link redirects to wallet.php
- ✅ Razorpay payment option still available

### Test 3: Successful Wallet Payment

**Steps:**
1. Login as seller with sufficient wallet balance (e.g., ₹3,127)
2. Create review request for ₹295
3. Go to payment page
4. Click "Pay ₹295.00" button under "Pay with Wallet"
5. Confirm the payment in the confirmation dialog
6. Wait for redirect

**Expected Results:**
- ✅ Confirmation dialog appears
- ✅ After confirmation, redirects to orders.php
- ✅ Success message: "Payment completed successfully using wallet!"
- ✅ Order status shows as "Paid" with payment method "wallet"
- ✅ Wallet balance reduced by ₹295 (verify on wallet page)
- ✅ total_spent increased by ₹295
- ✅ Transaction appears in payment_transactions table
- ✅ Invoice generated in tax_invoices table

### Test 4: Database Verification

**Check after successful wallet payment:**

```sql
-- Verify wallet balance deducted
SELECT balance, total_spent FROM seller_wallet WHERE seller_id = ?;

-- Verify review request marked as paid
SELECT payment_status, payment_method, payment_id 
FROM review_requests WHERE id = ?;

-- Verify payment transaction logged
SELECT * FROM payment_transactions 
WHERE review_request_id = ? 
ORDER BY created_at DESC LIMIT 1;

-- Verify invoice generated
SELECT * FROM tax_invoices 
WHERE review_request_id = ? 
ORDER BY created_at DESC LIMIT 1;
```

**Expected:**
- ✅ Wallet balance decreased correctly
- ✅ total_spent increased correctly
- ✅ payment_status = 'paid'
- ✅ payment_method = 'wallet'
- ✅ payment_id starts with 'WALLET_'
- ✅ Transaction with gateway = 'wallet', status = 'success'
- ✅ Invoice created with proper amounts

### Test 5: Concurrent Payment Prevention

**Steps:**
1. Login as seller with ₹300 balance
2. Create review request for ₹295
3. Open payment page in TWO browser tabs
4. Click "Pay with Wallet" in BOTH tabs simultaneously

**Expected Results:**
- ✅ Only ONE payment succeeds
- ✅ Second payment fails with "already paid" error
- ✅ Wallet only deducted once
- ✅ Database transaction prevents double payment

### Test 6: Razorpay Payment Still Works

**Steps:**
1. Login as seller
2. Create review request
3. Go to payment page
4. Click "Pay with Razorpay" button
5. Complete Razorpay flow (use test mode)

**Expected Results:**
- ✅ Razorpay checkout opens correctly
- ✅ Payment processes normally
- ✅ Review request marked as paid with payment_method = 'razorpay'
- ✅ No interference with wallet payment feature

### Test 7: Cancel/Back Navigation

**Steps:**
1. Go to payment page
2. Use browser back button
3. Refresh page
4. Try accessing payment page with invalid session

**Expected Results:**
- ✅ Session validation works
- ✅ Redirects appropriately
- ✅ No errors or crashes

### Test 8: Edge Cases

**Test 8a: Zero Balance Wallet**
- Balance = ₹0.00
- Shows insufficient balance message
- Add Money link available

**Test 8b: Exact Balance Match**
- Balance = ₹295.00
- Payment amount = ₹295.00
- Payment succeeds
- Balance becomes ₹0.00

**Test 8c: Very Large Balance**
- Balance = ₹100,000
- Should work normally

## Security Checks

### CSRF Protection
- ✅ POST request required for wallet-payment.php
- ✅ Session validation checks seller_id
- ✅ Request ownership verified (seller_id match)

### Race Condition Prevention
- ✅ Database transactions used
- ✅ FOR UPDATE locks prevent concurrent access
- ✅ payment_status checked before processing

### Input Validation
- ✅ request_id validated as positive integer
- ✅ Seller authentication required
- ✅ Balance verification before deduction

## Performance Checks

- ✅ Single database transaction for atomicity
- ✅ Minimal queries (3 reads + 5 writes)
- ✅ No external API calls (instant payment)
- ✅ Proper error handling and rollback

## User Experience Checks

- ✅ Clear visual distinction between payment methods
- ✅ Wallet balance prominently displayed
- ✅ Insufficient balance clearly indicated
- ✅ Payment confirmation dialog for wallet payments
- ✅ Success/error messages informative
- ✅ Consistent with existing UI/UX patterns

## Rollback Plan

If issues found:
1. Revert database enum change if needed
2. Remove wallet payment option from UI
3. Keep Razorpay as sole payment method
4. Investigate and fix issues
5. Re-deploy after testing

## Sign-off Checklist

- [ ] All test scenarios passed
- [ ] Database migration applied
- [ ] No syntax errors
- [ ] Security checks passed
- [ ] Performance acceptable
- [ ] Documentation updated
- [ ] CHANGELOG updated
- [ ] Code reviewed
- [ ] Stakeholder approval obtained

---

**Test Date:** _____________  
**Tester Name:** _____________  
**Environment:** _____________  
**Result:** ☐ PASS  ☐ FAIL  
**Comments:** _____________
