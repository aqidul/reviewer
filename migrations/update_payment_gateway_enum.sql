-- ============================================
-- Update payment_gateway ENUM to include bank_transfer
-- Migration for Offline Wallet Recharge System
-- ============================================

USE reviewflow;

-- Modify payment_gateway ENUM to include 'bank_transfer'
ALTER TABLE payment_transactions 
MODIFY COLUMN payment_gateway ENUM('razorpay', 'payumoney', 'bank_transfer') NOT NULL;

-- Show completion message
SELECT 'Payment gateway ENUM updated successfully to include bank_transfer!' as message;
