# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.1] - 2026-02-01

### Bug Fixes
- Fixed wallet balance not updating after admin approval - Enhanced error logging and validation
- Fixed seller dropdown search in Manage Seller Wallet page - Now opens on first character and focus
- Fixed missing sidebar links for brand-wise tasks and export features

### New Features
- Added brand-wise organization to pending tasks with collapsible sections
- Created dedicated brand view page for pending tasks (task-pending-brandwise.php)
- Added "Brand View" link to pending tasks page

### Improvements
- Completely reorganized admin sidebar with professional structure
- Added submenu support for brand-wise task views under main task links
- Enhanced sidebar organization with clear sections: Users, Tasks, Finance, Sellers, Reports & Export, Settings, Chatbot
- Added Export Review Data link to sidebar under Reports & Export section
- Added Manage Seller Wallet link to sidebar under Finance section
- Removed duplicate and unnecessary sidebar items
- Improved badge counts for pending items in sidebar
- Enhanced error logging in wallet approval process for better debugging
- Updated migration script to support bank_transfer and admin_adjustment payment types

### Technical Improvements
- Enhanced database migration for payment_transactions table ENUM values
- Improved error handling in wallet-requests.php with detailed logging
- Better JavaScript handling for seller dropdown with immediate search
- Added focus event handler for better UX in seller selection

## [2.0.0] - 2026-02-01

### Bug Fixes
- Fixed wallet balance not updating after admin approval - Wallet transactions now properly credit seller accounts upon approval
- Fixed review request data not showing to admin - All review request fields now visible in admin panel
- Fixed approved requests visibility - Approved requests now properly displayed in admin dashboard with filtering options

### New Features
- **AI Chatbot Integration** - Self-learning chatbot widget available on Admin, Seller, and User dashboards with FAQ integration
- **Admin Login as Seller** - Administrators can now impersonate seller accounts for support purposes with clear session indicators
- **Brand-wise Task Organization** - Tasks grouped by brand name with collapsible sections and date-wise sorting (recent first)
- **Admin Data Export** - Export review data to Excel format with brand selection and date range filtering
- **Enhanced Task Assignment** - Task assignment now includes both Seller and Brand selection with filtered dropdowns
- **Seller Brand Data Filtering** - Sellers can now view only their own brands' completed task data with export functionality
- **Light/Dark Theme Toggle** - Theme switcher available across all user types with localStorage persistence
- **Version Display** - Application version (v2.0.0) now displayed on all dashboard footers with changelog link

### UI/UX Improvements
- Modern, clean design language with consistent color schemes
- Glassmorphism effects on cards and modal dialogs
- Smooth animations and transitions throughout the interface
- Professional typography with improved readability
- Responsive layouts optimized for mobile and desktop
- Enhanced visual hierarchy with better spacing and contrast

### Technical Improvements
- Centralized theme management with CSS variables
- Improved session management for role impersonation
- Enhanced security with proper session cleanup
- Database optimization for brand-wise queries
- Improved error handling and logging
- Better code organization with reusable components

### Documentation
- Created comprehensive CHANGELOG.md for version tracking
- Added inline code documentation for new features
- Updated configuration with version constants

## [1.0.0] - 2025-01-01

### Initial Release
- User registration and authentication system
- Task assignment and completion workflow
- Wallet management for users and sellers
- Admin dashboard for system management
- Review request submission system
- Payment gateway integration (Razorpay, PayUMoney)
- GST invoice generation
- Referral and reward system
- Multi-tier user levels (Bronze, Silver, Gold, Elite)
- Fraud detection and prevention mechanisms

---

**Note**: For detailed upgrade instructions, please refer to the [Upgrade Guide](UPGRADE_GUIDE.md).
