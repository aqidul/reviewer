-- ============================================
-- ReviewFlow Chatbot Tables
-- Migration Script for Chatbot Functionality
-- ============================================

-- Create chatbot_unanswered table for logging unanswered questions
CREATE TABLE IF NOT EXISTS chatbot_unanswered (
    id INT PRIMARY KEY AUTO_INCREMENT,
    question TEXT NOT NULL,
    user_type ENUM('guest', 'user', 'seller', 'admin') DEFAULT 'guest',
    user_id INT NULL,
    is_resolved TINYINT(1) DEFAULT 0,
    admin_answer TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_type (user_type),
    INDEX idx_is_resolved (is_resolved),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create faq table for storing FAQ entries
CREATE TABLE IF NOT EXISTS faq (
    id INT PRIMARY KEY AUTO_INCREMENT,
    question TEXT NOT NULL,
    answer TEXT NOT NULL,
    category VARCHAR(50) DEFAULT 'general',
    user_type ENUM('all', 'user', 'seller', 'admin') DEFAULT 'all',
    is_active TINYINT(1) DEFAULT 1,
    view_count INT DEFAULT 0,
    helpful_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_user_type (user_type),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default FAQs for sellers
INSERT INTO faq (question, answer, category, user_type) VALUES
(
    'How do I request reviews?',
    'To request reviews: 1. Click "New Request" in the sidebar, 2. Enter product details (link, name, price), 3. Choose number of reviews needed, 4. Make payment, 5. Wait for admin approval. Once approved, reviewers will be assigned automatically!',
    'reviews',
    'seller'
),
(
    'How do I recharge my wallet?',
    'To recharge wallet: 1. Go to "Wallet" in sidebar, 2. Click "Recharge Wallet", 3. Enter amount, 4. Choose payment method (Razorpay supports UPI, Cards, Net Banking), 5. Complete payment. Your balance updates instantly!',
    'wallet',
    'seller'
),
(
    'How do I view my invoices?',
    'To view invoices: 1. Go to "Invoices" in sidebar, 2. See all your invoices listed, 3. Click "View" for details, 4. Click "Download" to save PDF. Invoices include GST breakdown and are generated automatically after payment.',
    'billing',
    'seller'
),
(
    'What is the cost per review?',
    'Review pricing: Base commission is ₹50 per review, plus 18% GST. Example: 10 reviews = ₹500 + ₹90 GST = ₹590 total. You can pay via Razorpay (UPI/Cards/Net Banking) or wallet balance.',
    'pricing',
    'seller'
),
(
    'How long does admin approval take?',
    'Admin typically reviews and approves requests within 24 hours. You will receive a notification once your request is approved. You can track the status in the "Orders" section.',
    'reviews',
    'seller'
)
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- Verify tables were created
SELECT 
    'chatbot_unanswered' as table_name,
    COUNT(*) as row_count 
FROM chatbot_unanswered
UNION ALL
SELECT 
    'faq' as table_name,
    COUNT(*) as row_count 
FROM faq;
