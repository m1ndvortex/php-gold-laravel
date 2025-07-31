# Customer Relationship Management (CRM) System

## Overview

The CRM system provides comprehensive customer management capabilities for the jewelry SaaS platform, including customer profile management, ledger tracking, grouping/segmentation, and automated notification system for birthdays and occasions.

## Features Implemented

### 1. Customer Profile Management

#### Complete Contact Information
- **Personal Details**: Name, phone, email, address, city, postal code
- **Business Information**: Tax ID, National ID, customer type (individual/business)
- **Financial Settings**: Credit limit, current balance, gold balance
- **Preferences**: Contact preferences (email, SMS, WhatsApp), tags, notes
- **Tracking**: Birth date, last transaction date, activity status

#### Customer Model Features
```php
// Key attributes
$fillable = [
    'name', 'phone', 'email', 'address', 'tax_id',
    'customer_group_id', 'credit_limit', 'current_balance', 'gold_balance',
    'birth_date', 'notes', 'tags', 'city', 'postal_code',
    'national_id', 'customer_type', 'is_active', 'last_transaction_at',
    'contact_preferences'
];

// Helper methods
hasExceededCreditLimit(): bool
getAvailableCreditAttribute(): float
isBirthdayToday(): bool
isBirthdayWithinDays(int $days): bool
updateBalance(float $amount, string $type): void
```

### 2. Customer Ledger System

#### Gold & Currency Balance Tracking
- **Dual Currency Support**: Track both IRR currency and gold amounts
- **Transaction Types**: Credit and debit entries
- **Reference Tracking**: Link to invoices, payments, or other transactions
- **Balance History**: Maintain running balance after each transaction
- **Audit Trail**: Track who created each entry and when

#### CustomerLedger Model Features
```php
// Key attributes
$fillable = [
    'customer_id', 'transaction_type', 'amount', 'gold_amount',
    'currency', 'description', 'reference_type', 'reference_id',
    'balance_after', 'gold_balance_after', 'transaction_date', 'created_by'
];

// Helper methods
isDebit(): bool
isCredit(): bool
reference() // Polymorphic relationship to referenced model
```

### 3. Customer Grouping and Segmentation

#### Customer Groups
- **Multilingual Support**: Persian and English names/descriptions
- **Financial Settings**: Discount percentage, credit limit multiplier
- **Group Settings**: Configurable JSON settings for group-specific rules
- **Status Management**: Active/inactive groups

#### CustomerGroup Model Features
```php
// Key attributes
$fillable = [
    'name', 'name_en', 'description', 'description_en',
    'discount_percentage', 'credit_limit_multiplier',
    'is_active', 'settings'
];

// Helper methods
getLocalizedNameAttribute(): string
getLocalizedDescriptionAttribute(): string
```

#### Pre-configured Groups
1. **مشتریان عادی (Regular Customers)**: 0% discount, 1x credit multiplier
2. **مشتریان VIP (VIP Customers)**: 5% discount, 2x credit multiplier
3. **عمده فروشان (Wholesalers)**: 10% discount, 3x credit multiplier
4. **طلافروشان (Jewelers)**: 7.5% discount, 2.5x credit multiplier
5. **مشتریان غیرفعال (Inactive Customers)**: 0% discount, 0x credit multiplier

### 4. Birthday/Occasion Reminder System

#### Notification Types
- **Birthday Notifications**: Automatic creation for upcoming birthdays
- **Custom Occasions**: Manual creation for anniversaries, special events
- **Overdue Payment Reminders**: Automatic alerts for overdue invoices
- **Credit Limit Exceeded**: Alerts when customers exceed credit limits

#### CustomerNotification Model Features
```php
// Key attributes
$fillable = [
    'customer_id', 'type', 'title', 'title_en', 'message', 'message_en',
    'scheduled_at', 'sent_at', 'channels', 'metadata', 'status', 'error_message'
];

// Notification types
'birthday', 'occasion', 'overdue_payment', 'credit_limit_exceeded'

// Statuses
'pending', 'sent', 'failed', 'cancelled'

// Channels
'email', 'sms', 'whatsapp', 'system'
```

#### Notification Triggers
- **Birthday Notifications**: Created 7 days before birthday (configurable)
- **Overdue Payments**: Created when invoice status becomes 'overdue'
- **Credit Limit**: Created when customer balance exceeds credit limit
- **Custom Occasions**: Manually created by users

## API Endpoints

### Customer Management
```
GET    /api/customers                    # List customers with filtering
POST   /api/customers                    # Create new customer
GET    /api/customers/{id}               # Get customer details
PUT    /api/customers/{id}               # Update customer
DELETE /api/customers/{id}               # Delete/deactivate customer

GET    /api/customers/statistics         # Get customer statistics
GET    /api/customers/birthdays/upcoming # Get upcoming birthdays
GET    /api/customers/birthdays/today    # Get today's birthdays
POST   /api/customers/import             # Import customers from CSV
GET    /api/customers/export             # Export customers

GET    /api/customers/{id}/ledger        # Get customer ledger
POST   /api/customers/{id}/ledger        # Create ledger entry
```

### Customer Groups
```
GET    /api/customer-groups              # List customer groups
POST   /api/customer-groups              # Create new group
GET    /api/customer-groups/{id}         # Get group details
PUT    /api/customer-groups/{id}         # Update group
DELETE /api/customer-groups/{id}         # Delete group

GET    /api/customer-groups/{id}/customers    # Get customers in group
POST   /api/customer-groups/{id}/move-customers # Move customers to group
```

### Customer Notifications
```
GET    /api/customer-notifications       # List notifications with filtering
POST   /api/customer-notifications       # Create custom notification
GET    /api/customer-notifications/{id}  # Get notification details
PUT    /api/customer-notifications/{id}  # Update pending notification
POST   /api/customer-notifications/{id}/cancel # Cancel notification

GET    /api/customer-notifications/pending     # Get pending notifications
GET    /api/customer-notifications/statistics  # Get notification statistics
POST   /api/customer-notifications/create-birthday    # Create birthday notifications
POST   /api/customer-notifications/create-overdue     # Create overdue notifications
POST   /api/customer-notifications/create-credit-limit # Create credit limit notifications
POST   /api/customer-notifications/process-pending    # Process pending notifications

GET    /api/customer-notifications/customer/{id}/history # Get customer notification history
```

## Console Commands

### Process Customer Notifications
```bash
# Create birthday notifications for next 7 days
php artisan customers:process-notifications --create-birthdays --days-ahead=7

# Create overdue payment notifications
php artisan customers:process-notifications --create-overdue

# Create credit limit exceeded notifications
php artisan customers:process-notifications --create-credit-limit

# Send all pending notifications
php artisan customers:process-notifications --send-pending

# Process all notification types (default behavior)
php artisan customers:process-notifications
```

## Services

### CustomerService
Handles core customer operations:
- Customer CRUD operations
- Ledger entry management
- Birthday calculations
- Statistics generation
- Import/export functionality

### CustomerNotificationService
Manages notification system:
- Automatic notification creation
- Notification processing and sending
- Channel management based on customer preferences
- Duplicate prevention
- Notification history tracking

## Database Schema

### customers
```sql
- id, name, phone, email, address, tax_id
- customer_group_id (FK to customer_groups)
- credit_limit, current_balance, gold_balance
- birth_date, notes, tags (JSON)
- city, postal_code, national_id
- customer_type (individual/business)
- is_active, last_transaction_at
- contact_preferences (JSON)
- timestamps
```

### customer_groups
```sql
- id, name, name_en, description, description_en
- discount_percentage, credit_limit_multiplier
- is_active, settings (JSON)
- timestamps
```

### customer_ledgers
```sql
- id, customer_id (FK)
- transaction_type (credit/debit)
- amount, gold_amount, currency
- description, reference_type, reference_id
- balance_after, gold_balance_after
- transaction_date, created_by (FK to users)
- timestamps
```

### customer_notifications
```sql
- id, customer_id (FK)
- type, title, title_en, message, message_en
- scheduled_at, sent_at
- channels (JSON), metadata (JSON)
- status, error_message
- timestamps
```

## Frontend Components

### CustomerManagement.vue
- Customer listing with advanced filtering
- Statistics dashboard
- Customer CRUD operations
- Import/export functionality
- Responsive design with RTL support

### CustomerNotifications.vue
- Notification management interface
- Statistics overview
- Bulk notification creation
- Notification processing controls
- Status tracking and filtering

## Usage Examples

### Creating a Customer with Opening Balance
```php
$customerData = [
    'name' => 'احمد محمدی',
    'phone' => '09123456789',
    'email' => 'ahmad@example.com',
    'customer_type' => 'individual',
    'customer_group_id' => 1,
    'credit_limit' => 5000,
    'opening_balance' => 1000, // Creates initial ledger entry
    'birth_date' => '1990-01-01',
    'contact_preferences' => ['email', 'sms']
];

$customer = $customerService->createCustomer($customerData);
```

### Creating Custom Occasion Notification
```php
$occasionData = [
    'title' => 'سالگرد ازدواج',
    'title_en' => 'Wedding Anniversary',
    'message' => 'سالگرد ازدواج مشتری فرا رسیده است',
    'message_en' => 'Customer wedding anniversary is approaching',
    'scheduled_at' => now()->addDays(3),
    'channels' => ['email', 'sms'],
    'metadata' => [
        'occasion_type' => 'wedding_anniversary',
        'years' => 5
    ]
];

$notification = $notificationService->createOccasionNotification($customer, $occasionData);
```

### Processing Notifications via Cron
```bash
# Add to crontab for daily processing
0 9 * * * cd /path/to/project && php artisan customers:process-notifications
```

## Security Features

- **Tenant Isolation**: All customer data is isolated per tenant
- **Permission-based Access**: Role-based access control for customer operations
- **Audit Logging**: Track all customer modifications and ledger entries
- **Data Validation**: Comprehensive validation for all customer inputs
- **Soft Deletes**: Customers with invoices are deactivated, not deleted

## Performance Optimizations

- **Database Indexing**: Optimized indexes for common queries
- **Eager Loading**: Efficient relationship loading
- **Pagination**: All listings use pagination
- **Caching**: Statistics and frequently accessed data cached
- **Bulk Operations**: Efficient import/export functionality

## Internationalization

- **RTL Support**: Full right-to-left layout support
- **Persian/English**: Dual language support throughout
- **Localized Dates**: Persian calendar support
- **Number Formatting**: Persian number formatting
- **Notification Messages**: Multilingual notification content

## Testing

Comprehensive test coverage includes:
- **Unit Tests**: Service layer business logic
- **Feature Tests**: API endpoint functionality
- **Integration Tests**: Database operations and relationships
- **Notification Tests**: Automated notification creation and processing

## Future Enhancements

1. **Advanced Segmentation**: Machine learning-based customer segmentation
2. **Communication History**: Track all customer communications
3. **Loyalty Programs**: Points-based loyalty system
4. **Advanced Analytics**: Customer lifetime value, churn prediction
5. **Mobile App Integration**: Push notifications for mobile apps
6. **WhatsApp Integration**: Direct WhatsApp messaging
7. **Email Templates**: Customizable email notification templates
8. **Bulk Operations**: Bulk customer updates and operations