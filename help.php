<?php
/**
 * Help Page
 */

require_once 'includes/config.php';
require_once 'includes/functions.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help & Support - Reviewer Task Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .help-section {
            padding: 60px 0;
        }
        .help-card {
            border: none;
            border-radius: 10px;
            transition: transform 0.3s;
            height: 100%;
        }
        .help-card:hover {
            transform: translateY(-5px);
        }
        .help-icon {
            font-size: 2.5rem;
            margin-bottom: 20px;
            color: #667eea;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="help-section">
        <div class="container">
            <h1 class="text-center mb-5">Help & Support Center</h1>
            
            <div class="row mb-5">
                <div class="col-md-4 mb-4">
                    <div class="card help-card shadow-sm">
                        <div class="card-body text-center p-4">
                            <div class="help-icon">
                                <i class="bi bi-question-circle-fill"></i>
                            </div>
                            <h4>FAQ</h4>
                            <p>Find answers to frequently asked questions about the review process, payments, and task completion.</p>
                            <a href="#faq" class="btn btn-outline-primary">View FAQ</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="card help-card shadow-sm">
                        <div class="card-body text-center p-4">
                            <div class="help-icon">
                                <i class="bi bi-chat-dots-fill"></i>
                            </div>
                            <h4>Chat Assistant</h4>
                            <p>Get instant help from our AI chatbot. Available 24/7 to answer your questions about the system.</p>
                            <a href="<?php echo BASE_URL; ?>/chatbot/" class="btn btn-outline-primary">Open Chat</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="card help-card shadow-sm">
                        <div class="card-body text-center p-4">
                            <div class="help-icon">
                                <i class="bi bi-envelope-fill"></i>
                            </div>
                            <h4>Contact Support</h4>
                            <p>Can't find what you need? Contact our support team directly via email or phone.</p>
                            <a href="#contact" class="btn btn-outline-primary">Contact Us</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- FAQ Section -->
            <div id="faq" class="mb-5">
                <h2 class="mb-4">Frequently Asked Questions</h2>
                <div class="accordion" id="faqAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                How does the review process work?
                            </button>
                        </h2>
                        <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                The process has 4 steps: 1) Get assigned a product 2) Purchase the product 3) Submit review with screenshots 4) Receive 100% refund after verification.
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                When will I receive my refund?
                            </button>
                        </h2>
                        <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Refunds are processed within 3-5 business days after admin verification. You'll receive notification when refund is initiated.
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                What if I miss the deadline?
                            </button>
                        </h2>
                        <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Contact admin immediately if you can't meet the deadline. Extensions may be granted on a case-by-case basis.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Contact Section -->
            <div id="contact" class="mb-5">
                <h2 class="mb-4">Contact Support</h2>
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Email Support</h5>
                                <p class="card-text">For account issues, payment queries, and technical support:</p>
                                <p><strong>Email:</strong> admin@reviewflow.com</p>
                                <p><strong>Response Time:</strong> Within 24 hours on business days</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Phone Support</h5>
                                <p class="card-text">For urgent matters during business hours:</p>
                                <p><strong>Phone:</strong> +91-XXXXXXXXXX</p>
                                <p><strong>Hours:</strong> 10:00 AM - 6:00 PM (Mon-Fri)</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Back to Home -->
            <div class="text-center">
                <a href="<?php echo BASE_URL; ?>/" class="btn btn-primary">
                    <i class="bi bi-arrow-left"></i> Back to Homepage
                </a>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
