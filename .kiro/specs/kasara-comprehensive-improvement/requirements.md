# Requirements Document

## Introduction

This document defines the comprehensive improvement requirements for the KASARA retail inventory management system. KASARA is a live production system (5 months of real data) managing IMEI-tracked phones and non-IMEI accessories across multiple branches, with 12+ user roles, POS/sales, online shop integration, audit, and reporting capabilities.

All improvements must be backward-compatible, additive, and preserve existing data integrity. The system must remain operational during incremental deployment. No data loss is acceptable.

The improvements span six areas: security hardening, code quality refactoring, missing feature additions, architecture improvements, testing infrastructure, and database optimization.

## Glossary

- **KASARA_System**: The complete retail inventory management application comprising a Laravel 12 backend API and Vue 3 frontend SPA
- **Backend_API**: The Laravel 12 application serving RESTful endpoints at api.stokps.com/api, using Sanctum for authentication and Spatie for role/permission management
- **Frontend_SPA**: The Vue 3 single-page application using Pinia for state management, Tailwind CSS 4 for styling, and Vite for bundling
- **Auth_Module**: The authentication subsystem handling login, token management, PIN operations, and session control via Laravel Sanctum
- **Permission_Engine**: The authorization subsystem using Spatie Laravel Permission on the backend to manage roles and permissions for 12+ user roles
- **Inventory_Module**: The core module managing stock-in, stock-out, transfers, IMEI tracking, and inventory state across branches, warehouses, online shops, and distributors
- **Audit_Module**: The subsystem providing sales auditing, profit analysis, stock-in/out verification, and checklist workflows
- **Report_Module**: The subsystem generating sales reports, brand/type reports, ranking reports, and stock history exports
- **Service_Layer**: A dedicated layer of PHP classes encapsulating business logic, separated from HTTP controllers and database models
- **Form_Request**: Laravel validation classes that encapsulate request validation rules, authorization checks, and error messages
- **Rate_Limiter**: A mechanism that restricts the number of requests a client can make to an endpoint within a time window
- **CSP**: Content Security Policy — an HTTP header that controls which resources the browser is allowed to load
- **Queue_Worker**: A Laravel background process that executes time-consuming jobs asynchronously (exports, notifications, reports)
- **Activity_Log**: A system-wide audit trail recording user actions, data changes, and system events beyond inventory-specific logs
- **Low_Stock_Alert**: An automated notification triggered when a product's available quantity falls below its configured min_stock threshold
- **API_Version**: A URL prefix (e.g., /api/v1/) that allows backward-compatible evolution of the API contract
- **Excluded_Keywords**: The list of test/trial terms ['trial', 'anu', 'testing', 'huft', 'test'] used to filter out non-production data from queries

## Requirements

### Requirement 1: Remove Unprotected Public Routes

**User Story:** As a system administrator, I want all data-modifying and diagnostic endpoints protected by authentication, so that unauthorized users cannot access or alter production data.

#### Acceptance Criteria

1. WHEN a request is made to /inventory/fix-data without a valid Sanctum token, THE Backend_API SHALL return HTTP 401 Unauthorized
2. WHEN a request is made to /inventory/fix-logs without a valid Sanctum token, THE Backend_API SHALL return HTTP 401 Unauthorized
3. WHEN a request is made to /debug-pending without a valid Sanctum token, THE Backend_API SHALL return HTTP 401 Unauthorized
4. WHEN the application is deployed to production, THE Backend_API SHALL not expose any route prefixed with "debug" or "fix" outside the auth:sanctum middleware group
5. IF a previously public fix/debug route is moved behind authentication, THEN THE Backend_API SHALL continue to function identically for authenticated super_admin users

---

### Requirement 2: Enforce Login Rate Limiting

**User Story:** As a security engineer, I want login attempts rate-limited to prevent brute-force attacks, so that user accounts remain secure.

#### Acceptance Criteria

1. THE Auth_Module SHALL limit login attempts to a maximum of 5 attempts per minute per IP address
2. WHEN a client exceeds 5 login attempts within one minute, THE Auth_Module SHALL return HTTP 429 Too Many Requests with a Retry-After header
3. WHEN the rate limit window expires, THE Auth_Module SHALL allow the client to attempt login again
4. THE Auth_Module SHALL identify clients by IP address for rate limiting purposes
5. IF a legitimate user is rate-limited, THEN THE Auth_Module SHALL include the number of seconds until the next allowed attempt in the response

---

### Requirement 3: Backend-Authoritative Permission System

**User Story:** As a system administrator, I want the backend to be the single source of truth for permissions, so that frontend permission overrides cannot grant unauthorized access.

#### Acceptance Criteria

1. THE Permission_Engine SHALL return the complete list of user permissions from the backend /user endpoint on every authentication and session refresh
2. THE Frontend_SPA SHALL use permissions received from the Backend_API without overriding or replacing them with locally-defined permission sets
3. WHEN a user's role or permissions change in the backend, THE Frontend_SPA SHALL reflect the updated permissions on the next /user fetch without requiring a full page reload
4. THE Frontend_SPA SHALL retain local permission definitions only as a UI display fallback when the backend is unreachable, and SHALL NOT use them for access control decisions on API calls
5. THE Backend_API SHALL enforce permission checks on every protected endpoint using Spatie middleware or policy gates, independent of what the frontend sends
6. IF the backend returns an empty permissions array for a valid authenticated user, THEN THE Permission_Engine SHALL deny access to all permission-gated features until permissions are resolved

---

### Requirement 4: Secure Transaction PIN Handling

**User Story:** As a user with transaction PIN enabled, I want my PIN never exposed in the frontend UI or API responses, so that my financial operations remain secure.

#### Acceptance Criteria

1. THE Backend_API SHALL never include the transaction_pin field value in any API response payload
2. THE Backend_API SHALL only expose a boolean transaction_pin_exists field indicating whether a PIN is set
3. THE Frontend_SPA SHALL not display, log, or store the actual PIN value in any component state, template, or browser storage
4. WHEN a PIN verification is required, THE Frontend_SPA SHALL send the PIN directly to the /pin/verify endpoint and discard it from memory after the response
5. IF a developer inspects network responses, THEN THE Backend_API SHALL have the transaction_pin field in the User model's $hidden array

---

### Requirement 5: Strengthen Content Security Policy

**User Story:** As a security engineer, I want the CSP header to disallow unsafe-inline and unsafe-eval, so that XSS attack vectors are minimized.

#### Acceptance Criteria

1. THE Backend_API SHALL serve a Content-Security-Policy header that does not include 'unsafe-eval' in the script-src directive
2. THE Backend_API SHALL serve a Content-Security-Policy header that uses nonce-based or hash-based allowlisting instead of 'unsafe-inline' for script-src
3. THE Frontend_SPA SHALL function correctly with the stricter CSP by eliminating inline scripts and eval usage
4. WHILE the CSP is enforced, THE KASARA_System SHALL not break any existing functionality including chart rendering, QR code scanning, and PDF generation
5. IF a third-party library requires eval (e.g., chart.js), THEN THE Backend_API SHALL use a specific nonce or hash for that library rather than blanket unsafe-eval

---

### Requirement 6: Extract Service Layer from Controllers

**User Story:** As a developer, I want business logic separated into service classes, so that controllers remain thin and logic is reusable and testable.

#### Acceptance Criteria

1. THE KASARA_System SHALL have a dedicated App\Services namespace containing service classes for each business domain (Inventory, StockOut, Transfer, Audit, Report)
2. WHEN the InventoryController is refactored, THE Backend_API SHALL split it into at minimum: InventoryController, StockInController (new), InventoryAccountController (new), and InventoryExportController (new), each under 500 lines
3. THE Service_Layer SHALL encapsulate all business rules including stock validation, transfer logic, pricing calculations, and audit workflows
4. WHEN a service method is called, THE Service_Layer SHALL perform the same business logic that the original controller method performed, producing identical API responses
5. THE Backend_API SHALL maintain all existing API endpoint URLs and response formats after the refactoring
6. IF any controller method exceeds 50 lines after refactoring, THEN THE Backend_API SHALL delegate the excess logic to a service class

---

### Requirement 7: Implement Form Request Validation

**User Story:** As a developer, I want validation rules defined in dedicated Form Request classes, so that validation is consistent, reusable, and separated from business logic.

#### Acceptance Criteria

1. THE Backend_API SHALL have Form_Request classes for all endpoints that accept user input (stock-in, stock-out, transfers, user creation, product management)
2. WHEN a request fails validation, THE Backend_API SHALL return HTTP 422 with a standardized error response containing field-specific error messages
3. THE Form_Request classes SHALL include authorization checks using the authorize() method tied to Spatie permissions
4. THE Backend_API SHALL produce identical validation error messages and behavior as the current inline validation after migration to Form Request classes
5. WHEN a new endpoint is added, THE Backend_API SHALL require a corresponding Form_Request class rather than inline validation

---

### Requirement 8: Resolve N+1 Query Issues

**User Story:** As a system administrator, I want database queries optimized to eliminate N+1 patterns, so that inventory listing performance is acceptable under load.

#### Acceptance Criteria

1. WHEN the inventory index endpoint is called, THE Backend_API SHALL eager-load all related models (distributor, product, branch, inventoryLogs) in a maximum of 5 database queries regardless of result count
2. THE Backend_API SHALL not execute Distributor::find() or InventoryLog::where() inside a loop or collection transform callback
3. WHEN inventory data is transformed for API response, THE Backend_API SHALL use pre-loaded relationships rather than lazy-loading
4. THE Backend_API SHALL produce identical response data after query optimization as before
5. WHILE the inventory index serves paginated results, THE Backend_API SHALL complete the database queries within 200ms for up to 100 items per page under normal load

---

### Requirement 9: Remove Debug and Temporary Files

**User Story:** As a developer, I want the repository free of debug, temporary, and simulation files, so that the codebase is clean and no accidental execution of fix scripts occurs.

#### Acceptance Criteria

1. THE KASARA_System SHALL not contain any files matching the patterns debug_*.php, tmp_check_*.php, fix_*.php, or simulate_*.php in the repository
2. THE KASARA_System SHALL not contain AuditController_TEMP.php or any file with _TEMP suffix in the repository
3. WHEN debug/temp files are removed, THE Backend_API SHALL continue to function identically for all production features
4. THE KASARA_System SHALL include a .gitignore rule preventing future commits of files matching debug_*, tmp_*, fix_*, and simulate_* patterns
5. IF any logic from temporary files is needed for production, THEN THE Backend_API SHALL migrate that logic into properly named, tested service classes before removal

---

### Requirement 10: Centralize Excluded Keywords Configuration

**User Story:** As a developer, I want the excluded keywords list defined in a single configuration location, so that changes propagate consistently across all query filters.

#### Acceptance Criteria

1. THE Backend_API SHALL define the Excluded_Keywords list in a single configuration file (config/kasara.php or equivalent)
2. WHEN any query needs to filter out test/trial data, THE Backend_API SHALL reference the centralized configuration rather than hardcoding the list
3. THE Backend_API SHALL produce identical query results after centralization as before
4. WHEN a new excluded keyword is added to the configuration, THE Backend_API SHALL apply it to all locations that filter test data without code changes
5. THE Backend_API SHALL allow the Excluded_Keywords list to be modified via environment configuration for different deployment environments

---

### Requirement 11: Eliminate Duplicated Filter Logic

**User Story:** As a developer, I want inventory filtering logic defined once, so that filter behavior is consistent between different code paths and easier to maintain.

#### Acceptance Criteria

1. THE Backend_API SHALL have a single InventoryFilterService or query scope that encapsulates all inventory filtering logic
2. WHEN the inventory index() method and applyInventoryFilters() method apply filters, THE Backend_API SHALL delegate to the same shared filtering implementation
3. THE Backend_API SHALL produce identical filtered results after deduplication as before
4. WHEN a new filter criterion is added, THE Backend_API SHALL require modification in only one location
5. THE Backend_API SHALL support all existing filter parameters (status, placement_type, placement_id, brand, category, search) through the unified filter implementation

---

### Requirement 12: Implement Low-Stock Alert Notifications

**User Story:** As a branch manager, I want to receive notifications when product stock falls below the minimum threshold, so that I can reorder before stockouts occur.

#### Acceptance Criteria

1. WHEN a product's available quantity at any placement falls below its configured min_stock value, THE KASARA_System SHALL generate a Low_Stock_Alert
2. THE KASARA_System SHALL deliver Low_Stock_Alert notifications to users with inventory.manage permission for the affected placement
3. THE KASARA_System SHALL check stock levels after every stock-out, transfer-out, and sale transaction
4. THE KASARA_System SHALL not generate duplicate alerts for the same product-placement combination until the stock level has been restored above min_stock and fallen below again
5. WHILE a Low_Stock_Alert is active, THE Frontend_SPA SHALL display a visual indicator on the dashboard and inventory views
6. THE KASARA_System SHALL allow users to configure notification preferences (in-app only, or in-app plus external channel)

---

### Requirement 13: Implement Activity Log / General Audit Trail

**User Story:** As an auditor, I want a comprehensive log of all user actions and data changes, so that I can trace any modification back to its author and timestamp.

#### Acceptance Criteria

1. THE Activity_Log SHALL record the actor (user_id), action type, target model, target ID, changed fields (old and new values), IP address, and timestamp for every create, update, and delete operation
2. THE Activity_Log SHALL cover all models including User, Inventory, Product, Order, StockOut, Transfer, Branch, and configuration changes
3. WHEN a user performs any data-modifying action, THE KASARA_System SHALL create an Activity_Log entry within the same database transaction
4. THE Backend_API SHALL provide a paginated, filterable API endpoint for querying Activity_Log entries by user, model type, date range, and action type
5. THE Activity_Log SHALL be append-only; existing log entries SHALL not be modifiable or deletable through the application
6. IF the Activity_Log write fails, THEN THE KASARA_System SHALL still complete the primary operation and log the failure to the application error log

---

### Requirement 14: Implement API Versioning

**User Story:** As a developer, I want API endpoints versioned, so that breaking changes can be introduced in new versions without disrupting existing clients.

#### Acceptance Criteria

1. THE Backend_API SHALL serve all current endpoints under the /api/v1/ prefix
2. THE Backend_API SHALL maintain the existing /api/ prefix as an alias to /api/v1/ for backward compatibility
3. WHEN a new API version is introduced, THE Backend_API SHALL keep previous versions operational for a documented deprecation period
4. THE Frontend_SPA SHALL target a specific API version in its base URL configuration
5. THE Backend_API SHALL include an API-Version response header indicating the version serving the request
6. IF a client requests a deprecated or removed API version, THEN THE Backend_API SHALL return HTTP 410 Gone with a message indicating the supported versions

---

### Requirement 15: Implement Rate Limiting on Sensitive Endpoints

**User Story:** As a security engineer, I want financial and stock-modifying endpoints rate-limited, so that automated abuse or accidental rapid-fire submissions are prevented.

#### Acceptance Criteria

1. THE Backend_API SHALL apply rate limiting of 30 requests per minute per user to stock-out, stock-in, and transfer endpoints
2. THE Backend_API SHALL apply rate limiting of 60 requests per minute per user to report export endpoints
3. WHEN a user exceeds the rate limit, THE Backend_API SHALL return HTTP 429 with a Retry-After header
4. THE Rate_Limiter SHALL identify users by their authenticated user ID (not just IP) for authenticated endpoints
5. THE Backend_API SHALL exempt super_admin users from rate limiting on administrative endpoints
6. THE Backend_API SHALL log rate limit violations to the Activity_Log for security monitoring

---

### Requirement 16: Implement Queue-Based Processing for Heavy Operations

**User Story:** As a user, I want export and report generation to run in the background, so that my browser does not time out waiting for large data operations.

#### Acceptance Criteria

1. WHEN a user requests a data export (sales, stock mutation, inventory), THE Backend_API SHALL dispatch the export job to a queue and return HTTP 202 Accepted with a job ID
2. THE Queue_Worker SHALL process export jobs asynchronously and store the result file in application storage
3. WHEN an export job completes, THE KASARA_System SHALL notify the requesting user that their export is ready for download
4. THE Frontend_SPA SHALL display export job status (pending, processing, completed, failed) and provide a download link upon completion
5. IF an export job fails, THEN THE Queue_Worker SHALL retry up to 3 times before marking it as failed and notifying the user
6. THE Backend_API SHALL maintain the existing synchronous export endpoints as a fallback for small datasets (under 1000 records)

---

### Requirement 17: Remove Hardcoded Production URLs

**User Story:** As a developer, I want all environment-specific URLs configured via environment variables, so that the application works correctly across development, staging, and production environments.

#### Acceptance Criteria

1. THE Backend_API SHALL not contain any hardcoded reference to https://api.stokps.com in source code files
2. THE Frontend_SPA SHALL not contain any hardcoded reference to https://api.stokps.com in source code files except as a fallback default in environment configuration
3. THE KASARA_System SHALL read the API base URL from environment variables (APP_URL for backend, VITE_API_URL for frontend)
4. WHEN the application is deployed to a new environment, THE KASARA_System SHALL function correctly by only changing environment variables without code modifications
5. THE Backend_API SHALL use Laravel's url() and config('app.url') helpers instead of hardcoded URLs for generating absolute URLs

---

### Requirement 18: Implement Vue Error Boundary

**User Story:** As a user, I want the application to gracefully handle frontend errors without crashing the entire page, so that I can continue working even when a component fails.

#### Acceptance Criteria

1. THE Frontend_SPA SHALL implement a global error boundary component that catches unhandled JavaScript errors and Vue component errors
2. WHEN a Vue component throws an unhandled error, THE Frontend_SPA SHALL display a user-friendly error message in place of the failed component without crashing the entire application
3. THE Frontend_SPA SHALL log caught errors to the browser console with component stack trace information
4. THE Frontend_SPA SHALL provide a "Retry" or "Reload" action within the error boundary UI
5. WHILE an error boundary is active, THE Frontend_SPA SHALL keep the navigation sidebar and header functional so users can navigate away
6. IF a route-level component fails to load (chunk load error), THEN THE Frontend_SPA SHALL display a full-page error state with an option to reload

---

### Requirement 19: Implement Backend Test Suite for Critical Flows

**User Story:** As a developer, I want automated tests covering authentication, financial transactions, and inventory operations, so that regressions are caught before deployment.

#### Acceptance Criteria

1. THE Backend_API SHALL have feature tests covering: login, logout, token refresh, and rate limiting
2. THE Backend_API SHALL have feature tests covering: stock-in (IMEI and non-IMEI), stock-out, and transfer workflows
3. THE Backend_API SHALL have feature tests covering: permission enforcement (authorized access succeeds, unauthorized access returns 403)
4. THE Backend_API SHALL have feature tests covering: order creation, void, and refund financial calculations
5. WHEN the test suite is executed, THE Backend_API SHALL achieve a minimum of 80% code coverage on service layer classes
6. THE Backend_API SHALL include a CI-compatible test command that runs all tests without requiring external services (using SQLite in-memory or test database)

---

### Requirement 20: Add Database Indexes for Common Query Patterns

**User Story:** As a system administrator, I want database indexes optimized for common query patterns, so that inventory listing and filtering performs well as data grows.

#### Acceptance Criteria

1. THE Backend_API SHALL add a composite index on the inventories table for (placement_type, placement_id, status) columns
2. THE Backend_API SHALL add an index on the inventory_logs table for (inventory_id, created_at) columns
3. THE Backend_API SHALL add an index on the stock_outs table for (branch_id, created_at) columns
4. THE Backend_API SHALL add an index on the orders table for (branch_id, created_at) columns
5. WHEN indexes are added via migration, THE Backend_API SHALL use additive migrations that do not drop or modify existing columns or data
6. THE Backend_API SHALL verify that existing queries benefit from the new indexes by confirming query plan improvements

---

### Requirement 21: Consolidate Database Migrations

**User Story:** As a developer, I want a clean migration history without duplicate or conflicting fix patches, so that fresh database setup is reliable and migration state is predictable.

#### Acceptance Criteria

1. THE Backend_API SHALL identify and remove duplicate migrations (e.g., multiple migrations adding the same expedition fields)
2. THE Backend_API SHALL create a single consolidated "baseline" migration that represents the current production schema state
3. WHEN the consolidated migration is applied to a fresh database, THE Backend_API SHALL produce an identical schema to the current production database
4. THE Backend_API SHALL not drop, rename, or alter any existing columns that contain production data during consolidation
5. THE Backend_API SHALL maintain a migration that can be safely run on the existing production database (no-op for already-applied changes)
6. IF a migration conflict is detected, THEN THE Backend_API SHALL resolve it by creating a new additive migration rather than modifying historical migrations

---

### Requirement 22: Implement Caching Strategy

**User Story:** As a system administrator, I want frequently-accessed, rarely-changing data cached, so that database load is reduced and response times improve.

#### Acceptance Criteria

1. THE Backend_API SHALL cache product listings, brand lists, category lists, and distributor lists with a configurable TTL (default 5 minutes)
2. WHEN cached data is modified (product created/updated/deleted), THE Backend_API SHALL invalidate the relevant cache entries immediately
3. THE Backend_API SHALL use Laravel's cache abstraction with a configurable driver (Redis preferred, file as fallback)
4. THE Backend_API SHALL not cache user-specific or permission-sensitive data without proper cache key isolation per user/role
5. WHILE the cache is unavailable (driver failure), THE Backend_API SHALL fall back to direct database queries without errors
6. THE Backend_API SHALL include cache hit/miss metrics accessible via the system status endpoint

---

### Requirement 23: Implement Scheduled Reports

**User Story:** As a branch manager, I want daily and weekly summary reports generated automatically, so that I receive performance insights without manually running reports.

#### Acceptance Criteria

1. THE KASARA_System SHALL support scheduling daily sales summary reports to be generated at a configurable time (default 06:00 local time)
2. THE KASARA_System SHALL support scheduling weekly inventory movement reports generated every Monday
3. WHEN a scheduled report is generated, THE KASARA_System SHALL store it in application storage and notify subscribed users
4. THE Backend_API SHALL provide an API endpoint for users to subscribe/unsubscribe from specific report schedules
5. THE KASARA_System SHALL use Laravel's task scheduler (cron) to trigger report generation jobs dispatched to the queue
6. IF a scheduled report generation fails, THEN THE KASARA_System SHALL retry once and log the failure for administrator review

---

### Requirement 24: Implement Data Backup and Archival Strategy

**User Story:** As a system administrator, I want automated database backups and data archival, so that data is protected against loss and old data does not degrade query performance.

#### Acceptance Criteria

1. THE KASARA_System SHALL perform automated daily database backups stored in a separate storage location from the primary database
2. THE KASARA_System SHALL retain daily backups for 30 days and weekly backups for 6 months
3. THE KASARA_System SHALL support archiving inventory_logs and activity_logs older than 12 months to a separate archive table or storage
4. WHEN data is archived, THE KASARA_System SHALL maintain referential integrity and allow read-only access to archived data through a dedicated endpoint
5. THE KASARA_System SHALL verify backup integrity by performing automated restore tests on a monthly schedule
6. IF a backup fails, THEN THE KASARA_System SHALL alert the system administrator immediately via the configured notification channel

---

### Requirement 25: Implement CI/CD Pipeline

**User Story:** As a developer, I want automated build, test, and deployment pipelines, so that code changes are validated before reaching production and deployments are consistent.

#### Acceptance Criteria

1. THE KASARA_System SHALL have a CI pipeline that runs on every pull request: linting (Pint for PHP), type checking, and the full test suite
2. THE KASARA_System SHALL have a CI pipeline that builds the frontend (Vite build) and verifies no build errors
3. WHEN all CI checks pass on a pull request merge to main, THE KASARA_System SHALL trigger an automated deployment to the staging environment
4. THE KASARA_System SHALL require manual approval for production deployments after staging verification
5. THE CI pipeline SHALL complete within 10 minutes for the full test and build cycle
6. IF any CI check fails, THEN THE KASARA_System SHALL block the pull request merge and notify the author

---

### Requirement 26: Implement Purchase Order and Supplier Management

**User Story:** As an inventory manager, I want to create purchase orders and manage supplier relationships, so that procurement is tracked and supplier performance is measurable.

#### Acceptance Criteria

1. THE KASARA_System SHALL allow users with inventory.manage permission to create, edit, and track purchase orders with line items referencing products
2. THE KASARA_System SHALL maintain a supplier database with contact information, payment terms, and product associations
3. WHEN a purchase order is received (goods arrive), THE KASARA_System SHALL allow linking it to a stock-in transaction for traceability
4. THE KASARA_System SHALL track purchase order statuses: draft, submitted, partially_received, received, cancelled
5. THE KASARA_System SHALL provide a report showing supplier lead times, order history, and outstanding orders
6. THE Backend_API SHALL expose CRUD endpoints for suppliers and purchase orders under the auth:sanctum middleware with appropriate permission checks

---

### Requirement 27: Implement Customer Database

**User Story:** As a sales staff member, I want to record and look up customer information, so that repeat customers are recognized and purchase history is accessible.

#### Acceptance Criteria

1. THE KASARA_System SHALL maintain a customers table with fields: name, phone, email (optional), address (optional), and notes
2. WHEN a sale is created, THE Frontend_SPA SHALL allow selecting an existing customer or creating a new one, linking the order to the customer record
3. THE KASARA_System SHALL provide a searchable customer list with purchase history summary (total orders, total spend, last purchase date)
4. THE Backend_API SHALL expose customer CRUD endpoints accessible to users with pos.access or transactions.create permissions
5. THE KASARA_System SHALL migrate existing customer_name string fields in orders to reference the new customers table while preserving the original string as a fallback
6. IF a customer record is deleted, THEN THE KASARA_System SHALL soft-delete it and retain the association with historical orders

