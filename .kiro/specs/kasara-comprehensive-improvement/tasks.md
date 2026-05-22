# Implementation Plan: KASARA Comprehensive Improvement

## Overview

Phased implementation of security hardening, code quality improvements, architecture enhancements, new features, and testing infrastructure for the KASARA retail inventory management system. All changes are additive, backward-compatible, and preserve existing production data.

Implementation language: PHP (Laravel 12) for backend, JavaScript/Vue 3 for frontend.

## Tasks

- [x] 1. Security Hardening — Route Protection & Rate Limiting
  - [x] 1.1 Move unprotected fix/debug routes behind auth:sanctum middleware
    - Remove `/inventory/fix-data`, `/inventory/fix-logs`, and `/debug-pending` from public route group in `routes/api.php`
    - Move fix-data and fix-logs inside `auth:sanctum` + `role:super_admin` middleware group under `/admin` prefix
    - Remove or gate the `/debug-pending` route entirely
    - Verify authenticated super_admin users can still access the moved routes
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5_

  - [x] 1.2 Implement login rate limiting (5 attempts/minute/IP)
    - Define `login` rate limiter in `AppServiceProvider::boot()` using `RateLimiter::for('login', ...)`
    - Set limit to 5 per minute, keyed by IP address
    - Return HTTP 429 with `Retry-After` header and JSON error message
    - Replace `throttle:1000,1` on login route with `throttle:login`
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5_

  - [x]* 1.3 Write feature tests for route protection and rate limiting
    - Test unauthenticated access to fix-data/fix-logs returns 401
    - Test authenticated non-admin access returns 403
    - Test rate limiting triggers after 5 rapid login attempts
    - _Requirements: 1.1, 1.2, 2.1, 2.2_

- [x] 2. Security Hardening — Permissions & PIN Security
  - [x] 2.1 Make backend the authoritative source for permissions
    - Update `AuthController::me()` to return full permissions array via `$user->getAllPermissions()->pluck('name')`
    - Include roles array in the user response payload
    - _Requirements: 3.1, 3.5_

  - [x] 2.2 Remove frontend permission override logic
    - Modify `enrichUserPermissions()` in `store/auth.js` to use backend permissions directly without local override
    - Keep `utils/permissions.js` as UI display fallback only (sidebar hints when offline)
    - Ensure empty permissions array from backend denies access to all gated features
    - _Requirements: 3.2, 3.3, 3.4, 3.6_

  - [x] 2.3 Secure transaction PIN handling
    - Verify `transaction_pin` is in User model's `$hidden` array
    - Remove `transaction_pin` from `$fillable` if present (prevent mass assignment)
    - Create `app/Http/Resources/UserResource.php` that explicitly excludes PIN field
    - Update controllers returning user data to use UserResource
    - Ensure frontend PIN input components clear PIN from reactive state after verification
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5_

  - [x]* 2.4 Write feature tests for permission enforcement and PIN security
    - Test that /user endpoint returns permissions array
    - Test that transaction_pin is never in any API response
    - Test that unauthorized users get 403 on protected endpoints
    - _Requirements: 3.1, 3.5, 4.1, 4.5_

- [x] 3. Security Hardening — Content Security Policy
  - [x] 3.1 Implement nonce-based CSP in SecurityHeaders middleware
    - Generate random nonce in `SecurityHeaders.php` middleware
    - Build CSP header without `unsafe-inline` and `unsafe-eval`
    - Use nonce for script-src and style-src directives
    - Configure allowed domains for fonts, images, connect-src (including WebSocket)
    - Deploy initially as `Content-Security-Policy-Report-Only` for monitoring
    - _Requirements: 5.1, 5.2, 5.5_

  - [x] 3.2 Update frontend Vite config for CSP compatibility
    - Configure Vite to support nonce injection on script/style tags
    - Eliminate any inline scripts or eval usage in frontend code
    - Handle Chart.js compatibility (pre-compile configs or use strict-dynamic)
    - Verify QR code scanning and PDF generation still work
    - _Requirements: 5.3, 5.4_

- [ ] 4. Checkpoint — Security Phase Complete
  - Ensure all security changes are deployed and working. Verify no regressions in authentication, permissions, or frontend functionality. Ask the user if questions arise.

- [ ] 5. Code Quality — Service Layer Extraction
  - [ ] 5.1 Create service layer directory structure and interfaces
    - Create `app/Services/Inventory/` directory with `InventoryServiceInterface.php` and `StockInServiceInterface.php`
    - Create `app/Services/StockOut/StockOutService.php` stub
    - Create `app/Services/Transfer/TransferService.php` stub
    - Create `app/Services/Audit/AuditService.php` stub
    - Create `app/Services/Report/ReportService.php` stub
    - Register service bindings in a ServiceProvider
    - _Requirements: 6.1, 6.3_

  - [ ] 5.2 Extract InventoryService from InventoryController
    - Create `InventoryService.php` with methods: `getFilteredInventory()`, `calculateTotalValue()`, `getFilterOptions()`, `getMetaLocations()`
    - Create `InventoryFilterService.php` encapsulating all filter logic (also satisfies Req 11)
    - Move business logic from controller to service, keeping controller as thin HTTP layer
    - Verify API responses remain identical
    - _Requirements: 6.2, 6.4, 6.5, 6.6, 11.1, 11.2, 11.3, 11.4, 11.5_

  - [ ] 5.3 Extract StockInService and split InventoryController
    - Create `StockInService.php` with `processStockIn()` and `voidStockIn()` methods
    - Create `app/Http/Controllers/Inventory/StockInController.php` (<200 lines)
    - Create `app/Http/Controllers/Inventory/InventoryAccountController.php` (<200 lines)
    - Create `app/Http/Controllers/Inventory/InventoryExportController.php` (<200 lines)
    - Update routes to point to new controllers (same URLs)
    - _Requirements: 6.2, 6.4, 6.5_

  - [ ] 5.4 Extract StockOutService and TransferService
    - Move stock-out business logic from `StockOutController` to `StockOutService`
    - Move transfer logic from `TransferController` to `TransferService`
    - Keep controllers as thin HTTP wrappers
    - Verify all API responses remain identical
    - _Requirements: 6.3, 6.4, 6.5, 6.6_

- [ ] 6. Code Quality — Form Requests & Query Optimization
  - [ ] 6.1 Create Form Request classes for stock operations
    - Create `StockInRequest.php` with validation rules for stock-in (IMEI and non-IMEI)
    - Create `StockOutRequest.php` with validation rules for stock-out
    - Create `TransferRequest.php` with validation rules for transfers
    - Include `authorize()` method tied to Spatie permissions in each
    - Return HTTP 422 with standardized field-specific error messages
    - _Requirements: 7.1, 7.2, 7.3, 7.4_

  - [ ] 6.2 Create Form Request classes for user and product management
    - Create `CreateUserRequest.php`, `UpdateUserRequest.php`
    - Create `CreateProductRequest.php`, `UpdateProductRequest.php`
    - Replace inline validation in controllers with Form Request classes
    - Verify error messages remain identical
    - _Requirements: 7.1, 7.2, 7.4, 7.5_

  - [ ] 6.3 Fix N+1 query issues in inventory endpoints
    - Add eager loading for `distributor`, `product`, `branch`, `inventoryLogs` in inventory index query
    - Remove `Distributor::find()` and `InventoryLog::where()` calls inside loops/transforms
    - Use pre-loaded relationships in data transformation
    - Verify response data remains identical and queries complete within 200ms
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5_

  - [ ]* 6.4 Write unit tests for service layer methods
    - Test InventoryService filter logic produces correct results
    - Test StockInService processes stock-in correctly
    - Test Form Request validation rules reject invalid input
    - _Requirements: 6.4, 7.4, 8.4_

- [x] 7. Code Quality — Cleanup & Configuration
  - [x] 7.1 Remove debug and temporary files from repository
    - Delete `AuditController_TEMP.php` and any files matching `debug_*.php`, `tmp_check_*.php`, `fix_*.php`, `simulate_*.php`
    - Verify no production logic depends on these files before removal
    - Migrate any needed logic to properly named service classes
    - _Requirements: 9.1, 9.2, 9.3, 9.5_

  - [x] 7.2 Add .gitignore rules for debug/temp file patterns
    - Add patterns: `debug_*`, `tmp_*`, `fix_*`, `simulate_*`, `*_TEMP*`
    - _Requirements: 9.4_

  - [x] 7.3 Centralize excluded keywords configuration
    - Create `config/kasara.php` with `excluded_keywords` array: `['trial', 'anu', 'testing', 'huft', 'test']`
    - Replace all hardcoded keyword arrays across controllers/services with `config('kasara.excluded_keywords')`
    - Support environment-based override via `.env`
    - Verify query results remain identical
    - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5_

- [ ] 8. Checkpoint — Code Quality Phase Complete
  - Ensure all refactoring is complete, API responses are identical to before, and no regressions exist. Ask the user if questions arise.

- [ ] 9. Architecture — API Versioning & Rate Limiting
  - [ ] 9.1 Implement API versioning with /api/v1/ prefix
    - Add `/api/v1/` route prefix group wrapping all existing routes
    - Maintain `/api/` as alias to `/api/v1/` for backward compatibility
    - Add `API-Version` response header to all responses
    - Update frontend `VITE_API_URL` to target `/api/v1/`
    - _Requirements: 14.1, 14.2, 14.4, 14.5_

  - [ ] 9.2 Implement rate limiting on sensitive endpoints
    - Define rate limiters: `stock-operations` (30/min/user), `exports` (60/min/user)
    - Apply to stock-in, stock-out, transfer, and export endpoints
    - Key by authenticated user ID (not just IP)
    - Exempt super_admin from rate limits on admin endpoints
    - Return HTTP 429 with Retry-After header on limit exceeded
    - _Requirements: 15.1, 15.2, 15.3, 15.4, 15.5_

  - [ ] 9.3 Remove hardcoded production URLs
    - Search and replace all hardcoded `https://api.stokps.com` references in backend with `config('app.url')`
    - Search and replace hardcoded URLs in frontend with `import.meta.env.VITE_API_URL`
    - Use Laravel's `url()` helper for generating absolute URLs
    - _Requirements: 17.1, 17.2, 17.3, 17.4, 17.5_

- [ ] 10. Architecture — Queue Processing & Error Boundary
  - [ ] 10.1 Implement queue-based export processing
    - Create `ExportJob` base class with retry logic (3 attempts)
    - Create `SalesExportJob`, `StockMutationExportJob`, `InventoryExportJob`
    - Update export endpoints to dispatch jobs and return HTTP 202 with job ID
    - Create endpoint to check export job status (pending/processing/completed/failed)
    - Create endpoint to download completed export files
    - Maintain synchronous fallback for small datasets (<1000 records)
    - _Requirements: 16.1, 16.2, 16.3, 16.4, 16.5, 16.6_

  - [ ] 10.2 Implement Vue error boundary component
    - Create `ErrorBoundary.vue` component using Vue's `onErrorCaptured` hook
    - Display user-friendly error message with retry/reload action
    - Log errors to console with component stack trace
    - Keep navigation sidebar and header functional during error state
    - Handle chunk load errors with full-page error state and reload option
    - _Requirements: 18.1, 18.2, 18.3, 18.4, 18.5, 18.6_

  - [ ] 10.3 Update frontend to handle async exports
    - Add export job status polling/notification in frontend
    - Display job status (pending, processing, completed, failed) in UI
    - Provide download link when export completes
    - _Requirements: 16.4_

- [ ] 11. Architecture — Caching Strategy
  - [ ] 11.1 Implement caching for reference data
    - Cache product listings, brand lists, category lists, distributor lists (TTL: 5 min)
    - Implement cache invalidation on create/update/delete of cached entities
    - Use Laravel cache abstraction with configurable driver (Redis preferred, file fallback)
    - Ensure no user-specific data is cached without proper key isolation
    - Add graceful fallback to DB queries on cache driver failure
    - _Requirements: 22.1, 22.2, 22.3, 22.4, 22.5_

  - [ ] 11.2 Add cache metrics to system status endpoint
    - Expose cache hit/miss counts via the existing system status endpoint
    - _Requirements: 22.6_

- [ ] 12. Checkpoint — Architecture Phase Complete
  - Ensure API versioning, rate limiting, queue processing, error boundary, and caching are all working. Verify backward compatibility. Ask the user if questions arise.

- [ ] 13. Features — Low-Stock Alerts & Activity Log
  - [ ] 13.1 Implement low-stock alert system
    - Create `low_stock_alerts` migration (product_id, placement_type, placement_id, triggered_at, resolved_at)
    - Create `LowStockAlert` model and `NotificationService`
    - Trigger alert check after stock-out, transfer-out, and sale transactions
    - Prevent duplicate alerts for same product-placement until resolved
    - Deliver notifications to users with `inventory.manage` permission for affected placement
    - _Requirements: 12.1, 12.2, 12.3, 12.4_

  - [ ] 13.2 Add low-stock alert UI indicators
    - Display visual indicator on dashboard for active low-stock alerts
    - Show alert badge on inventory views for affected products
    - Add notification preferences (in-app, external channel toggle)
    - _Requirements: 12.5, 12.6_

  - [ ] 13.3 Implement activity log system
    - Create `activity_logs` migration (user_id, action, model_type, model_id, old_values, new_values, ip_address, created_at)
    - Create `ActivityLog` model (append-only, no update/delete)
    - Create trait `LogsActivity` to attach to models for automatic logging on create/update/delete
    - Log within same database transaction as primary operation
    - If log write fails, complete primary operation and log failure to error log
    - _Requirements: 13.1, 13.2, 13.3, 13.5, 13.6_

  - [ ] 13.4 Create activity log API and UI
    - Create paginated, filterable API endpoint for activity logs (filter by user, model, date range, action)
    - Create admin UI view for browsing activity logs
    - _Requirements: 13.4_

  - [ ]* 13.5 Write tests for low-stock alerts and activity log
    - Test alert triggers correctly on stock below min_stock
    - Test no duplicate alerts for same product-placement
    - Test activity log records all CRUD operations
    - _Requirements: 12.1, 12.4, 13.1, 13.3_

- [ ] 14. Features — Purchase Orders & Customer Database
  - [ ] 14.1 Implement supplier management
    - Create `suppliers` migration (name, contact_person, phone, email, address, payment_terms, notes, timestamps, soft_deletes)
    - Create `Supplier` model with product associations
    - Create `SupplierController` with CRUD endpoints under auth:sanctum
    - Add permission check for `inventory.manage`
    - _Requirements: 26.2, 26.6_

  - [ ] 14.2 Implement purchase order system
    - Create `purchase_orders` migration (supplier_id, status, order_date, expected_date, notes, created_by, timestamps)
    - Create `purchase_order_items` migration (purchase_order_id, product_id, quantity, unit_price, received_quantity)
    - Create `PurchaseOrder` and `PurchaseOrderItem` models
    - Create `PurchaseOrderController` with CRUD + status transitions (draft → submitted → partially_received → received → cancelled)
    - Link received POs to stock-in transactions for traceability
    - _Requirements: 26.1, 26.3, 26.4, 26.5, 26.6_

  - [ ] 14.3 Implement customer database
    - Create `customers` migration (name, phone, email, address, notes, timestamps, soft_deletes)
    - Create `Customer` model with order relationship
    - Add `customer_id` nullable foreign key to orders table (additive migration)
    - Create `CustomerController` with CRUD + search + purchase history summary
    - Allow selecting/creating customer during sale creation
    - Preserve existing `customer_name` string field as fallback
    - _Requirements: 27.1, 27.2, 27.3, 27.4, 27.5, 27.6_

  - [ ] 14.4 Create frontend views for suppliers, POs, and customers
    - Create supplier list/create/edit views
    - Create purchase order list/create/edit/receive views
    - Create customer list/detail views with purchase history
    - Integrate customer selection into POS/sale flow
    - _Requirements: 26.1, 26.5, 27.2, 27.3_

  - [ ]* 14.5 Write tests for purchase orders and customer management
    - Test PO status transitions
    - Test PO-to-stock-in linking
    - Test customer CRUD and soft delete behavior
    - _Requirements: 26.4, 27.6_

- [ ] 15. Checkpoint — Features Phase Complete
  - Ensure all new features work correctly, data integrity is maintained, and no regressions in existing functionality. Ask the user if questions arise.

- [ ] 16. Testing & CI/CD
  - [ ] 16.1 Set up backend test infrastructure
    - Configure PHPUnit/Pest with SQLite in-memory test database
    - Create test factories for User, Inventory, Product, Order, StockOut, Transfer models
    - Create base test case with authentication helpers
    - _Requirements: 19.6_

  - [ ] 16.2 Write feature tests for authentication and financial flows
    - Test login, logout, token refresh
    - Test order creation, void, and refund calculations
    - Test stock-in (IMEI and non-IMEI), stock-out, transfer workflows
    - Target 80% coverage on service layer classes
    - _Requirements: 19.1, 19.2, 19.4, 19.5_

  - [ ]* 16.3 Write feature tests for permission enforcement
    - Test authorized access succeeds for each role
    - Test unauthorized access returns 403
    - Test rate limiting on sensitive endpoints
    - _Requirements: 19.3_

  - [ ] 16.4 Set up CI/CD pipeline
    - Create GitHub Actions workflow for PR checks: PHP linting (Pint), test suite, frontend build (Vite)
    - Configure pipeline to block merge on failure
    - Add staging auto-deploy on main branch merge (with manual production approval)
    - Target <10 minute pipeline completion
    - _Requirements: 25.1, 25.2, 25.3, 25.4, 25.5, 25.6_

- [ ] 17. Database Optimization
  - [ ] 17.1 Add performance indexes via additive migration
    - Add composite index on `inventories` table: `(placement_type, placement_id, status)`
    - Add index on `inventory_logs` table: `(inventory_id, created_at)`
    - Add index on `stock_outs` table: `(branch_id, created_at)`
    - Add index on `orders` table: `(branch_id, created_at)`
    - Verify indexes are additive (no column drops or modifications)
    - _Requirements: 20.1, 20.2, 20.3, 20.4, 20.5_

  - [ ] 17.2 Consolidate migration history
    - Identify duplicate/conflicting migrations (e.g., multiple expedition field additions)
    - Create baseline migration representing current production schema
    - Ensure fresh migration produces identical schema to production
    - Do NOT drop, rename, or alter existing columns
    - _Requirements: 21.1, 21.2, 21.3, 21.4, 21.5, 21.6_

- [ ] 18. Operations — Scheduled Reports & Backup Strategy
  - [ ] 18.1 Implement scheduled report generation
    - Create `ScheduledReportJob` dispatched via Laravel scheduler
    - Configure daily sales summary (default 06:00) and weekly inventory movement (Monday)
    - Store generated reports in application storage
    - Notify subscribed users on completion
    - Create subscribe/unsubscribe API endpoint
    - _Requirements: 23.1, 23.2, 23.3, 23.4, 23.5, 23.6_

  - [ ] 18.2 Implement backup and archival strategy
    - Configure automated daily database backups (separate storage location)
    - Set retention: 30 days daily, 6 months weekly
    - Create archive migration for `inventory_logs` and `activity_logs` older than 12 months
    - Maintain referential integrity and read-only access to archived data
    - Set up backup failure alerting
    - _Requirements: 24.1, 24.2, 24.3, 24.4, 24.5, 24.6_

- [ ] 19. Final Checkpoint — All Phases Complete
  - Ensure all tests pass, all features work, no data loss has occurred, and the system is production-ready. Ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation between phases
- All database migrations are ADDITIVE — no dropping columns or tables
- All API changes maintain backward compatibility
- The system must remain operational during incremental deployment
- Phase 1 (Security) is highest priority and should be deployed first
- Service layer extraction (Phase 2) enables easier testing and feature development in later phases

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.2", "7.1", "7.2"] },
    { "id": 1, "tasks": ["1.3", "2.1", "2.2", "2.3", "7.3"] },
    { "id": 2, "tasks": ["2.4", "3.1"] },
    { "id": 3, "tasks": ["3.2"] },
    { "id": 4, "tasks": ["5.1", "9.3"] },
    { "id": 5, "tasks": ["5.2", "5.3", "5.4", "6.3"] },
    { "id": 6, "tasks": ["6.1", "6.2", "6.4"] },
    { "id": 7, "tasks": ["9.1", "9.2"] },
    { "id": 8, "tasks": ["10.1", "10.2"] },
    { "id": 9, "tasks": ["10.3", "11.1"] },
    { "id": 10, "tasks": ["11.2", "13.1", "13.3"] },
    { "id": 11, "tasks": ["13.2", "13.4", "13.5"] },
    { "id": 12, "tasks": ["14.1", "14.3"] },
    { "id": 13, "tasks": ["14.2", "14.4"] },
    { "id": 14, "tasks": ["14.5", "16.1"] },
    { "id": 15, "tasks": ["16.2", "16.3", "17.1"] },
    { "id": 16, "tasks": ["16.4", "17.2"] },
    { "id": 17, "tasks": ["18.1", "18.2"] }
  ]
}
```
