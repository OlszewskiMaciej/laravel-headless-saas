# Laravel Headless SaaS

A comprehensive, production-ready API for headless SaaS applications built with Laravel 12. This project provides a complete backend solution with authentication, subscription management, role-based permissions, and administrative features.

## ✨ Features

### 🔐 Authentication & Security

-   **API Key Authentication**: Secure, service-specific API key system with environment isolation
-   **User Authentication**: Laravel Sanctum with token-based authentication
-   **Role-Based Access Control**: Comprehensive permissions system using Spatie Laravel Permission
-   **Activity Logging**: Complete audit trail of user actions with Spatie Laravel Activitylog

### 💳 Subscription Management

-   **Stripe Integration**: Full subscription lifecycle management with Laravel Cashier
-   **Multiple Plans**: Support for various subscription tiers
-   **Multi-Currency Support**: Full support for multiple currencies (PLN, USD, EUR) with automatic currency-specific pricing
-   **Trial Periods**: Configurable trial periods for new users
-   **Invoice Management**: Automated invoice generation and retrieval
-   **Webhook Handling**: Secure Stripe webhook processing
-   **Fallback Mechanism**: Automatic fallback to local database when Stripe API is unavailable, ensuring service continuity

### 👨‍💼 Administration

-   **User Management**: Complete CRUD operations for user accounts
-   **API Key Management**: Create, revoke, and monitor API keys
-   **Role & Permission Management**: Dynamic role and permission assignment
-   **Activity Monitoring**: Real-time activity logs and user tracking

### 🏗️ Architecture & Development

-   **Modular Structure**: Clean, organized codebase with separate modules
-   **Repository Pattern**: Abstracted data layer for better testability
-   **UUID Primary Keys**: Enhanced security with UUID identifiers
-   **Comprehensive Testing**: PHPUnit test suite for reliability
-   **API Documentation**: Auto-generated Swagger/OpenAPI documentation

## 🛠️ Technology Stack

### Backend Framework

-   **Laravel 12**: Latest version of the PHP framework
-   **PHP 8.2+**: Modern PHP features and performance improvements

### Key Dependencies

-   **Laravel Sanctum**: API authentication
-   **Laravel Cashier**: Stripe subscription management
-   **Spatie Laravel Permission**: Role-based access control
-   **Spatie Laravel Activitylog**: User activity tracking
-   **Spatie Laravel Data**: Data transfer objects
-   **Spatie Laravel Query Builder**: Advanced query filtering
-   **L5 Swagger**: API documentation generation
-   **Guzzle HTTP**: HTTP client for external APIs

### Development Tools

-   **PHPUnit**: Testing framework
-   **Faker**: Test data generation

## 🗄️ Database Schema

The application uses a comprehensive database schema with the following main tables:

### Core Tables

-   **users**: User accounts with UUID primary keys
-   **personal_access_tokens**: Sanctum authentication tokens
-   **password_reset_tokens**: Password reset functionality

### Subscription Tables

-   **subscriptions**: Stripe subscription data
-   **subscription_items**: Individual subscription line items
-   **customers**: Stripe customer information (via users table)

### Permission System

-   **roles**: User roles (admin, premium, free, etc.)
-   **permissions**: Granular permissions
-   **role_has_permissions**: Role-permission relationships
-   **model_has_roles**: User-role assignments
-   **model_has_permissions**: Direct user permissions

### API Management

-   **api_keys**: Secure API key management with service/environment isolation

### Monitoring

-   **activity_log**: Comprehensive audit trail
-   **jobs**: Background job queue
-   **failed_jobs**: Failed job tracking

### Cache & Sessions

-   **cache**: Application caching
-   **sessions**: User session management

## Getting Started

### Prerequisites

-   PHP 8.2+
-   Composer
-   MySQL or compatible database

### Installation

1. Clone the repository:

    ```
    git clone https://github.com/olszewskimaciej/laravel-headless-saas.git
    cd laravel-headless-saas
    ```

2. Install dependencies:

    ```
    composer install
    ```

3. Set up environment variables:

    ```
    cp .env.example .env
    php artisan key:generate
    ```

4. Configure your database in the `.env` file.

5. Run migrations and seeders:

    ```
    php artisan migrate:fresh --seed
    ```

6. Configure Stripe settings in your `.env` file:

    ```
    STRIPE_KEY=pk_test_your_stripe_publishable_key_here
    STRIPE_SECRET=sk_test_your_stripe_secret_key_here

    # Monthly plan price IDs for different currencies
    STRIPE_MONTHLY_PLAN_PLN_ID=price_your_monthly_plan_pln_id_here
    STRIPE_MONTHLY_PLAN_USD_ID=price_your_monthly_plan_usd_id_here
    STRIPE_MONTHLY_PLAN_EUR_ID=price_your_monthly_plan_eur_id_here

    # Annual plan price IDs for different currencies
    STRIPE_ANNUAL_PLAN_PLN_ID=price_your_annual_plan_pln_id_here
    STRIPE_ANNUAL_PLAN_USD_ID=price_your_annual_plan_usd_id_here
    STRIPE_ANNUAL_PLAN_EUR_ID=price_your_annual_plan_eur_id_here

    STRIPE_WEBHOOK_SECRET=whsec_your_webhook_secret_here
    ```

    **Note:**

    - Get your Stripe API keys from your [Stripe Dashboard](https://dashboard.stripe.com/apikeys)
    - Create subscription plans in Stripe for each supported currency (PLN, USD, EUR) and use their price IDs
    - Set up webhook endpoints in Stripe and use the webhook secret
    - The application supports multiple currencies - configure price IDs for each currency you want to support
    - Optionally modify configuration files in the `config/` folder (e.g., `config/subscription.php`) to customize subscription behavior

7. Start the development server:
    ```
    php artisan serve
    ```

## 💰 Multi-Currency Support

The application provides comprehensive multi-currency support for subscription management:

### Supported Currencies

-   **PLN (Polish Złoty)** - Default currency with symbol: zł
-   **USD (US Dollar)** - Symbol: $
-   **EUR (Euro)** - Symbol: €

### Currency Configuration

Each subscription plan can be configured with different pricing for each supported currency:

```php
// Example from config/subscription.php
'plans' => [
    'monthly' => [
        'name' => 'Monthly Plan',
        'currencies' => [
            'PLN' => ['price' => 10, 'stripe_id' => 'price_monthly_pln'],
            'USD' => ['price' => 10, 'stripe_id' => 'price_monthly_usd'],
            'EUR' => ['price' => 10, 'stripe_id' => 'price_monthly_eur'],
        ],
    ],
],
```

### How It Works

1. **Stripe Integration**: Each currency requires a separate price ID in Stripe
2. **Dynamic Pricing**: API automatically serves the correct price based on user's currency preference
3. **Flexible Configuration**: Add or remove currencies by modifying the configuration file
4. **Consistent Experience**: All subscription operations (creation, updates, cancellations) work seamlessly across all currencies

### Adding New Currencies

To add support for a new currency:

1. Create the corresponding price in Stripe for each plan
2. Add the currency configuration to `config/subscription.php`
3. Add the Stripe price IDs to your `.env` file
4. The API will automatically support the new currency

## 🔄 Subscription Fallback System

The application includes a robust fallback mechanism for subscription data retrieval:

### How It Works

1. **Primary Source**: Stripe API is always used as the source of truth for subscription data
2. **Automatic Sync**: A cron job regularly synchronizes subscription data from Stripe to the local database
3. **Fallback Protection**: When Stripe API is unavailable, the system automatically falls back to local database

### Fallback Scenarios

The system uses local database fallback in the following situations:

-   **Network Issues**: When the application cannot reach Stripe servers
-   **API Rate Limits**: When Stripe API rate limits are exceeded
-   **Service Outages**: During Stripe service interruptions
-   **Timeout Errors**: When Stripe API responses are too slow

### Sync Command

To manually synchronize subscription data from Stripe:

```bash
# Sync all users' subscriptions
php artisan subscriptions:sync

# Sync specific user's subscription
php artisan subscriptions:sync --user=123

# Dry run to see what would be synced
php artisan subscriptions:sync --dry-run

# Sync only users with changes in last 7 days
php artisan subscriptions:sync --days=7

# Sync subscriptions and automatically update user roles based on subscription status
php artisan subscriptions:sync --sync-roles

# Combine options for comprehensive sync with role updates
php artisan subscriptions:sync --sync-roles --days=7
```

### Role Synchronization

The `--sync-roles` option provides automatic role management based on subscription status:

-   **Premium Role**: Assigned to users with active subscriptions
-   **Trial Role**: Assigned to users currently on trial period
-   **Free Role**: Assigned to users without active subscriptions or trials
-   **Admin Protection**: Admin users are never affected by role synchronization

#### How Role Sync Works

1. **Subscription Analysis**: Checks each user's current subscription status in Stripe
2. **Role Determination**: Determines appropriate role based on subscription state:
    - Active subscription → `premium` role
    - Trial period → `trial` role
    - Canceled but within grace period → `premium` role
    - No active subscription → `free` role
3. **Safe Updates**: Admin users are automatically skipped to prevent accidental role changes
4. **Audit Trail**: All role changes are logged for compliance and monitoring

#### Example Usage Scenarios

```bash
# Daily sync with role updates (recommended for automated tasks)
php artisan subscriptions:sync --sync-roles --days=1

# Emergency sync for specific user with role correction
php artisan subscriptions:sync --user=123 --sync-roles

# Preview what role changes would be made
php artisan subscriptions:sync --dry-run --sync-roles
```

### Benefits

-   **High Availability**: Service continues even during Stripe outages
-   **Better Performance**: Local fallback provides faster response times
-   **Data Consistency**: Regular sync ensures local data stays current
-   **Transparency**: Clear indication of data source in API responses

## API Key Authentication

The API uses secure API key authentication for all endpoints. API keys are:

-   Hashed before storage
-   Service-specific (frontend, mobile, etc.)
-   Environment-specific (dev, test, staging, production)
-   Managed with soft deletes and expiration dates

### Default API Keys (Development Only)

For development purposes, the following default API keys are provided vie seeder:

-   Default Test Key: `test_dev_default_api_key`
-   Web Frontend: `web_frontend_dev_default_api_key`
-   Mobile App: `mobile_app_dev_default_api_key`

**Note:** These default keys are for development only. Use the API key management commands or admin endpoints to create secure keys for production.

### API Key Management

API keys can be managed using the following Artisan commands:

```
php artisan api-key:create {service} {environment} --name="Key Name" --description="Key Description" --expires-days=365
php artisan api-key:list
php artisan api-key:revoke {id}
```

## Testing with Postman

A comprehensive Postman collection is included for testing all API endpoints:

1. Import the collection from `postman/Laravel_Headless_SaaS.postman_collection.json`
2. Import the environment files:
    - Development: `postman/Laravel_Headless_SaaS_Dev.postman_environment.json`
    - Production: `postman/Laravel_Headless_SaaS_Prod.postman_environment.json`

### API Key Authentication in Postman

The Postman collection is configured with:

-   A pre-request script that automatically adds the API key to all requests
-   Environment variables for API keys
-   Comprehensive API key tests

### Important Notes on API Key Usage

Even though the API key header is not explicitly visible in all requests in Auth, Subscription, User, and Admin folders in Postman, it is being added automatically by the pre-request script. The development environment includes default API keys, but you should generate your own keys for production environments.

## License

This project is licensed under the MIT License - see the LICENSE file for details.
