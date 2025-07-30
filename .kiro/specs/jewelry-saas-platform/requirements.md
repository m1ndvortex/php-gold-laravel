# Requirements Document

## 🐳 DOCKER INFRASTRUCTURE NOTICE

**⚠️ CRITICAL: This project uses Docker for ALL services. Do NOT install MySQL, Redis, or other services locally!**

**Active Docker Containers:**
- `jewelry_mysql` - MySQL Database (port 3306)
- `jewelry_redis` - Redis Cache/Sessions/Queues (port 6379)
- `jewelry_app` - Laravel Application (PHP-FPM)
- `jewelry_vite` - Vite Development Server (port 5173)
- `jewelry_nginx` - Nginx Web Server (ports 80/443)

**Before starting any task, verify containers are running:** `docker ps`

## Introduction

This document outlines the requirements for a secure, scalable, and RTL-compliant SaaS platform designed specifically for Persian-speaking jewelers (طلافروش‌ها). The platform will provide comprehensive business management tools including invoicing, inventory management, accounting, and CRM functionality within a multi-tenant environment that ensures data isolation, customizability, and performance optimization.

## Requirements

### Requirement 1: Multi-Tenant Architecture

**User Story:** As a platform operator, I want a secure multi-tenant system so that multiple jewelry businesses can use the platform while maintaining complete data isolation and security.

#### Acceptance Criteria

1. WHEN a new tenant signs up THEN the system SHALL create an isolated environment with separate database schema or dedicated database
2. WHEN a user accesses the platform THEN the system SHALL resolve tenant context via subdomain or header-based routing
3. WHEN any database operation occurs THEN the system SHALL enforce tenant-level data isolation to prevent cross-tenant data access
4. IF a tenant is deleted THEN the system SHALL completely remove all associated data while preserving other tenants' data integrity

### Requirement 2: Persian Language and RTL Support

**User Story:** As a Persian-speaking jeweler, I want a fully localized interface in Farsi with proper RTL layout so that I can use the platform naturally in my native language.

#### Acceptance Criteria

1. WHEN the platform loads THEN the system SHALL display the interface in Persian (Farsi) with proper RTL text direction by default
2. WHEN a user toggles language settings THEN the system SHALL provide English language option while maintaining RTL/LTR layout consistency
3. WHEN displaying numbers and dates THEN the system SHALL support both Persian and English digit formats based on user preference
4. WHEN generating reports or invoices THEN the system SHALL maintain proper RTL formatting in PDF outputs

### Requirement 3: User Role Management

**User Story:** As a platform administrator, I want comprehensive role-based access control so that different user types can access appropriate functionality while maintaining security.

#### Acceptance Criteria

1. WHEN a Super Admin logs in THEN the system SHALL provide access to platform-wide operations, tenant management, and billing without access to tenant business data
2. WHEN a Tenant Admin logs in THEN the system SHALL provide full access to their workspace, team management, settings, and reports
3. WHEN a Tenant Employee logs in THEN the system SHALL enforce role-based permissions (Cashier, Accountant, etc.) as defined by the Tenant Admin
4. WHEN custom roles are created THEN the system SHALL allow Tenant Admins to define granular permissions for their team members

### Requirement 4: Dashboard and Analytics

**User Story:** As a jeweler, I want a comprehensive dashboard with key business insights so that I can monitor my business performance and make informed decisions.

#### Acceptance Criteria

1. WHEN accessing the dashboard THEN the system SHALL display KPIs including today's sales, profit, new customers, and gold sold (MTD)
2. WHEN alerts are triggered THEN the system SHALL show overdue invoices, cheques due, and low inventory notifications
3. WHEN viewing sales trends THEN the system SHALL provide interactive bar/line charts with real-time data
4. WHEN customizing layout THEN the system SHALL allow drag & drop widget arrangement with per-user saved preferences
5. WHEN data updates occur THEN the system SHALL provide real-time sync via WebSockets and Laravel Echo

### Requirement 5: Invoicing System

**User Story:** As a jeweler, I want a comprehensive invoicing system with gold pricing logic so that I can generate accurate invoices for different transaction types.

#### Acceptance Criteria

1. WHEN creating an invoice THEN the system SHALL calculate final price using: Gold Weight × (Daily Gold Price + Manufacturing Fee + Jeweler's Profit + VAT)
2. WHEN selecting invoice type THEN the system SHALL support Sale, Purchase, and Trade invoice types
3. WHEN processing payments THEN the system SHALL support split payments across Cash, Card, Cheque, and Credit methods
4. WHEN generating invoices THEN the system SHALL create PDF invoices with customizable branding and RTL formatting
5. WHEN setting up recurring invoices THEN the system SHALL provide scheduling functionality for regular transactions
6. WHEN gold prices update THEN the system SHALL auto-populate daily gold prices from external API sources

### Requirement 6: Customer Management (CRM)

**User Story:** As a jeweler, I want comprehensive customer management capabilities so that I can maintain detailed customer relationships and track their transaction history.

#### Acceptance Criteria

1. WHEN creating customer profiles THEN the system SHALL store complete contact information, tags, and tax ID details
2. WHEN tracking customer transactions THEN the system SHALL maintain gold & currency ledger with complete balance history
3. WHEN importing customer data THEN the system SHALL support CSV/Excel import and export functionality
4. WHEN managing customer groups THEN the system SHALL allow categorization (Wholesalers, VIPs) with specific handling rules
5. WHEN setting credit limits THEN the system SHALL automatically block invoice creation when limits are exceeded
6. WHEN occasions approach THEN the system SHALL provide birthday/occasion reminder notifications

### Requirement 7: Inventory Management

**User Story:** As a jeweler, I want comprehensive inventory tracking for different product types so that I can manage stock levels and track product movement accurately.

#### Acceptance Criteria

1. WHEN categorizing products THEN the system SHALL support Raw Gold, Finished Jewelry, Coins, and Stones categories
2. WHEN generating labels THEN the system SHALL create barcode/QR labels for product identification
3. WHEN adjusting stock THEN the system SHALL allow manual adjustments for lost, damaged, or found items with audit trails
4. WHEN creating complex products THEN the system SHALL support Bill of Materials (BOM) for multi-component items
5. WHEN stock levels are low THEN the system SHALL trigger minimum quantity alerts
6. WHEN conducting physical counts THEN the system SHALL provide stock reconciliation module with variance reporting

### Requirement 8: Accounting System

**User Story:** As a jeweler, I want a complete double-entry accounting system so that I can maintain accurate financial records and generate required reports.

#### Acceptance Criteria

1. WHEN recording transactions THEN the system SHALL enforce double-entry bookkeeping with standard Chart of Accounts
2. WHEN creating journal entries THEN the system SHALL support both manual and automated recurring entries
3. WHEN managing cheques THEN the system SHALL track complete cheque lifecycle from issuance to clearance
4. WHEN reconciling banks THEN the system SHALL support CSV import and matching functionality
5. WHEN generating reports THEN the system SHALL produce Trial Balance, Profit & Loss, Balance Sheet, and General Ledger reports
6. WHEN managing assets THEN the system SHALL provide Fixed Asset Management with depreciation tracking
7. WHEN analyzing costs THEN the system SHALL support Cost Center Tagging for different business segments
8. WHEN closing periods THEN the system SHALL provide transaction locking by date functionality

### Requirement 9: Security and Session Management

**User Story:** As a business owner, I want comprehensive security features including session management so that I can protect my business data and control access to my system.

#### Acceptance Criteria

1. WHEN users access the platform THEN the system SHALL enforce TLS 1.3 + AES encryption globally
2. WHEN authenticating users THEN the system SHALL support two-factor authentication with multiple methods
3. WHEN tracking sessions THEN the system SHALL provide session device tracker showing active sessions with remote logout capability
4. WHEN detecting anomalies THEN the system SHALL alert on unusual login patterns (IP, geo location)
5. WHEN users are idle THEN the system SHALL automatically logout after configurable timeout periods
6. WHEN auditing activities THEN the system SHALL maintain comprehensive audit logs for all sensitive actions
7. WHEN restricting access THEN the system SHALL support IP whitelisting and geo-fencing controls

### Requirement 10: Platform Configuration and Settings

**User Story:** As a tenant admin, I want comprehensive configuration options so that I can customize the platform to match my business needs and branding.

#### Acceptance Criteria

1. WHEN configuring business identity THEN the system SHALL allow customization of name, logo, headers, and footers
2. WHEN setting financial defaults THEN the system SHALL store default gold price, VAT rates, and profit percentages
3. WHEN managing themes THEN the system SHALL provide full theme customization per tenant including dark/light mode
4. WHEN setting up notifications THEN the system SHALL allow configuration of email & SMS templates and triggers
5. WHEN backing up data THEN the system SHALL provide one-click backup and restore functionality
6. WHEN importing data THEN the system SHALL support bulk import/export for inventory and customer data

### Requirement 11: Docker Infrastructure and Development Environment

**User Story:** As a developer, I want a fully Dockerized development environment so that I can develop, test, and deploy the application consistently across different environments.

#### Acceptance Criteria

1. WHEN setting up development THEN the system SHALL provide Docker containers for Laravel backend with hot reload
2. WHEN running frontend THEN the system SHALL include Vite server container with volume mounting for development
3. WHEN using database THEN the system SHALL provide MySQL container with volume persistence
4. WHEN caching data THEN the system SHALL include Redis container for sessions, queues, and real-time features
5. WHEN serving in production THEN the system SHALL include Nginx reverse proxy with SSL termination
6. WHEN testing with real data THEN the system SHALL provide database seeding and migration capabilities within Docker environment

#### ⚠️ CRITICAL INFRASTRUCTURE NOTE FOR ALL DEVELOPERS:

**THIS PROJECT USES DOCKER FOR ALL SERVICES - DO NOT INSTALL LOCALLY!**

The following services are running in Docker containers and should NEVER be installed locally:
- **MySQL Database**: Container `jewelry_mysql` on port 3306
- **Redis Cache/Sessions/Queues**: Container `jewelry_redis` on port 6379  
- **Laravel Application**: Container `jewelry_app` (PHP-FPM)
- **Vite Development Server**: Container `jewelry_vite` on port 5173
- **Nginx Web Server**: Container `jewelry_nginx` on ports 80/443

**Docker Commands to Remember:**
- Start all services: `docker-compose up -d`
- Laravel commands: `docker exec jewelry_app php artisan [command]`
- Database access: `docker exec jewelry_mysql mysql -u jewelry_user -p`
- Redis access: `docker exec jewelry_redis redis-cli`
- View logs: `docker logs [container_name]`
- Restart service: `docker restart [container_name]`

**Configuration Files:**
- Database connection: Uses `mysql` hostname (not localhost)
- Redis connection: Uses `redis` hostname (not localhost)
- All environment variables are in `.env` file
- Docker configuration in `docker-compose.yml`

**ALWAYS verify Docker containers are running with `docker ps` before starting any task!**