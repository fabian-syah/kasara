// Role definitions and permissions for KASARA
// Based on the 12 roles identified from the reference image

export const ROLES = {
    SUPER_ADMIN: 'super_admin',
    ANALIST: 'analist',
    ADMIN_PRODUK: 'admin_produk',
    AUDIT: 'audit',
    SECURITY: 'security',
    LEADER: 'leader',
    DISTRIBUTOR: 'distributor',
    DISTRIBUTION: 'distribution',
    SALES: 'toko_offline',
    INVENTORY: 'inventory',
    GUDANG: 'gudang',
    INVENTORY_KASIR: 'inventory_kasir',
    TOKO_ONLINE: 'toko_online'
}

export const ROLE_LABELS = {
    [ROLES.SUPER_ADMIN]: 'Super Admin',
    [ROLES.ANALIST]: 'Analist',
    [ROLES.ADMIN_PRODUK]: 'Admin Produk',
    [ROLES.AUDIT]: 'Audit',
    [ROLES.SECURITY]: 'Security',
    [ROLES.LEADER]: 'Leader',
    [ROLES.DISTRIBUTOR]: 'Distributor',
    [ROLES.DISTRIBUTION]: 'Distribution',
    [ROLES.SALES]: 'Toko Offline',
    [ROLES.GUDANG]: 'Gudang',
    [ROLES.INVENTORY_KASIR]: 'Inventory & Kasir',
    [ROLES.TOKO_ONLINE]: 'Toko Online'
}

// Permission constants
export const PERMISSIONS = {
    // User management
    USERS_VIEW: 'users.view',
    USERS_CREATE: 'users.create',
    USERS_EDIT: 'users.edit',
    USERS_DELETE: 'users.delete',

    // Branch management
    BRANCHES_VIEW: 'branches.view',
    BRANCHES_MANAGE: 'branches.manage',

    // Products
    PRODUCTS_VIEW: 'products.view',
    PRODUCTS_CREATE: 'products.create',
    PRODUCTS_EDIT: 'products.edit',
    PRODUCTS_DELETE: 'products.delete',
    PRODUCTS_SET_PRICE: 'products.set_price',

    // Inventory
    INVENTORY_VIEW: 'inventory.view',
    INVENTORY_MANAGE: 'inventory.manage',
    INVENTORY_TRANSFER: 'inventory.transfer',
    INVENTORY_STOCK_IN: 'inventory.stock_in',

    // POS / Transactions
    POS_ACCESS: 'pos.access',
    TRANSACTIONS_VIEW: 'transactions.view',
    TRANSACTIONS_CREATE: 'transactions.create',
    TRANSACTIONS_VOID: 'transactions.void',

    // Reports & Analytics
    REPORTS_VIEW: 'reports.view',
    REPORTS_SALES: 'reports.sales',
    REPORTS_PROFIT: 'reports.profit',
    ANALYTICS_VIEW: 'analytics.view',

    // Audit
    AUDIT_VIEW: 'audit.view',
    AUDIT_APPROVE: 'audit.approve',

    // Security
    SECURITY_BARCODE: 'security.barcode',
    SECURITY_HISTORY: 'security.history',

    // Distribution
    DISTRIBUTION_VIEW: 'distribution.view',
    DISTRIBUTION_SIMULATE: 'distribution.simulate',

    // Online store
    ONLINE_ORDERS: 'online.orders',
    ONLINE_SCAN: 'online.scan',
    ONLINE_ANALYSIS: 'online.analysis',

    // Distributor Monitoring
    DISTRIBUTOR_MONITORING: 'distributor.monitoring',

    // Online Shop Monitoring
    ONLINE_MONITORING: 'online.monitoring',

    // Warehouse Monitoring
    WAREHOUSE_MONITORING: 'warehouse.monitoring'
}

// Role-based permissions mapping
export const ROLE_PERMISSIONS = {
    [ROLES.SUPER_ADMIN]: ['*'], // All permissions

    [ROLES.ANALIST]: [
        PERMISSIONS.REPORTS_VIEW,
        PERMISSIONS.REPORTS_SALES,
        PERMISSIONS.REPORTS_PROFIT,
        PERMISSIONS.ANALYTICS_VIEW,
        PERMISSIONS.BRANCHES_VIEW
    ],

    [ROLES.ADMIN_PRODUK]: [
        PERMISSIONS.PRODUCTS_VIEW,
        PERMISSIONS.PRODUCTS_CREATE,
        PERMISSIONS.PRODUCTS_EDIT,
        PERMISSIONS.PRODUCTS_DELETE,
        PERMISSIONS.PRODUCTS_SET_PRICE,
        PERMISSIONS.INVENTORY_VIEW,
        PERMISSIONS.INVENTORY_MANAGE,
        PERMISSIONS.INVENTORY_STOCK_IN, // Added for Stock In/Out access
        PERMISSIONS.INVENTORY_STOCK_IN, // Added for Stock In/Out access
        'master.view', // Added for Brands, Types, Categories
        'questions.manage' // Permission for Questions
    ],

    [ROLES.AUDIT]: [
        PERMISSIONS.AUDIT_VIEW,
        PERMISSIONS.AUDIT_APPROVE,
        PERMISSIONS.TRANSACTIONS_VIEW,
        PERMISSIONS.BRANCHES_VIEW,
        PERMISSIONS.USERS_VIEW,
        PERMISSIONS.USERS_CREATE,
        PERMISSIONS.USERS_EDIT,
        PERMISSIONS.REPORTS_PROFIT
    ],

    [ROLES.SECURITY]: [
        PERMISSIONS.SECURITY_BARCODE,
        PERMISSIONS.SECURITY_HISTORY,
        PERMISSIONS.TRANSACTIONS_VIEW
    ],

    [ROLES.LEADER]: [
        PERMISSIONS.DISTRIBUTOR_MONITORING,
        PERMISSIONS.ONLINE_MONITORING,
        PERMISSIONS.WAREHOUSE_MONITORING,
        PERMISSIONS.ANALYTICS_VIEW,
        'track.view'
    ],

    [ROLES.DISTRIBUTOR]: [
        PERMISSIONS.DISTRIBUTOR_MONITORING,
        PERMISSIONS.INVENTORY_VIEW,
        PERMISSIONS.INVENTORY_MANAGE,
        'track.view'
    ],

    [ROLES.DISTRIBUTION]: [
        PERMISSIONS.DISTRIBUTOR_MONITORING,
        PERMISSIONS.INVENTORY_VIEW,
        PERMISSIONS.INVENTORY_MANAGE,
        'track.view'
    ],

    [ROLES.SALES]: [
        PERMISSIONS.POS_ACCESS,
        PERMISSIONS.TRANSACTIONS_CREATE,
        PERMISSIONS.TRANSACTIONS_VIEW,
        PERMISSIONS.REPORTS_PROFIT,
        PERMISSIONS.INVENTORY_VIEW,
        PERMISSIONS.INVENTORY_STOCK_IN, // Allow access to stock in/out pages
        PERMISSIONS.INVENTORY_MANAGE // Allow confirming transfers
    ],


    [ROLES.GUDANG]: [
        PERMISSIONS.INVENTORY_VIEW,
        PERMISSIONS.INVENTORY_MANAGE,
        PERMISSIONS.INVENTORY_STOCK_IN
    ],

    [ROLES.INVENTORY_KASIR]: [
        PERMISSIONS.POS_ACCESS,
        PERMISSIONS.TRANSACTIONS_CREATE,
        PERMISSIONS.TRANSACTIONS_VIEW,
        PERMISSIONS.INVENTORY_VIEW,
        PERMISSIONS.INVENTORY_MANAGE,
        PERMISSIONS.INVENTORY_TRANSFER
    ],

    [ROLES.TOKO_ONLINE]: [
        PERMISSIONS.ONLINE_ORDERS,
        PERMISSIONS.ONLINE_SCAN,
        PERMISSIONS.INVENTORY_VIEW,
        PERMISSIONS.INVENTORY_STOCK_IN,
        PERMISSIONS.INVENTORY_MANAGE, // Allow confirming transfers
        PERMISSIONS.USERS_VIEW, // Needed to select target account for stock in
        PERMISSIONS.REPORTS_VIEW
    ]
}

// Sidebar menu configuration per role
export const ROLE_MENUS = {
    [ROLES.SUPER_ADMIN]: ['dashboard', 'online_sales_group', 'online_sales', 'shopee_history', 'online_scan', 'online_analysis', 'pos', 'inventory', 'master_data_group', 'monitoring_group', 'support_group', 'inventory_main', 'inventory_opname', 'inventory_monitoring_hub', 'retur_items', 'users', 'transactions', 'audit_sales', 'audit', 'audit_sales_report', 'audit_profit_uc', 'audit_stock_in_uc', 'audit_stock_out_uc', 'audit_photo_approvals', 'reports', 'report_sales', 'report_ranking', 'report_brand', 'report_type', 'stock_in_history', 'stock_out_history', 'audit_pin_resets', 'settings', 'warehouses', 'distributors', 'distributor_monitoring', 'online_monitoring', 'warehouse_monitoring', 'stock_summary', 'channels', 'online_shops', 'brands', 'types', 'prices', 'branches', 'questions', 'track', 'sales_check', 'sales_check_main', 'sales_ranking'],
    [ROLES.ANALIST]: ['reports', 'report_ranking'],
    [ROLES.ADMIN_PRODUK]: ['dashboard', 'master_data_group', 'brands', 'types', 'prices', 'track'],
    [ROLES.AUDIT]: ['dashboard', 'audit_cabang', 'audit_sales_sub', 'audit_inventory_sub', 'audit_analysis_sub', 'audit', 'audit_sales_report', 'audit_profit_uc', 'audit_stock_in_uc', 'audit_stock_out_uc', 'audit_pin_resets', 'audit_photo_approvals', 'users', 'inventory', 'inventory_main', 'inventory_opname', 'inventory_monitoring_hub', 'track'],
    [ROLES.SECURITY]: ['dashboard', 'transactions', 'track'],
    [ROLES.LEADER]: ['dashboard', 'monitoring_group', 'distributor_monitoring', 'online_monitoring', 'warehouse_monitoring', 'stock_summary', 'track'],
    [ROLES.DISTRIBUTOR]: ['dashboard', 'inventory', 'inventory_main', 'inventory_opname', 'inventory_monitoring_hub', 'track'],
    [ROLES.DISTRIBUTION]: ['dashboard', 'inventory', 'inventory_main', 'inventory_opname', 'inventory_monitoring_hub', 'track'],
    [ROLES.SALES]: ['dashboard', 'sales_create', 'sales_check', 'sales_check_main', 'sales_ranking', 'inventory', 'inventory_main', 'inventory_opname', 'inventory_monitoring_hub', 'track'],
    [ROLES.GUDANG]: ['dashboard', 'inventory', 'inventory_main', 'inventory_opname', 'inventory_monitoring_hub', 'retur_items', 'track'],
    [ROLES.INVENTORY_KASIR]: ['dashboard', 'pos', 'transactions', 'track'],
    [ROLES.TOKO_ONLINE]: ['dashboard', 'online_sales_group', 'online_sales', 'shopee_history', 'inventory', 'inventory_main', 'inventory_opname', 'inventory_monitoring_hub', 'reports', 'report_sales', 'track']
}

// Helper functions
export function hasPermission(userPermissions, requiredPermission) {
    if (!userPermissions) return false
    if (userPermissions.includes('*')) return true
    return userPermissions.includes(requiredPermission)
}

export function hasAnyPermission(userPermissions, requiredPermissions) {
    if (!userPermissions) return false
    if (userPermissions.includes('*')) return true
    return requiredPermissions.some(perm => userPermissions.includes(perm))
}

export function hasAllPermissions(userPermissions, requiredPermissions) {
    if (!userPermissions) return false
    if (userPermissions.includes('*')) return true
    return requiredPermissions.every(perm => userPermissions.includes(perm))
}

export function getMenuForRole(role) {
    return ROLE_MENUS[role] || ['dashboard']
}

export function getPermissionsForRole(role) {
    return ROLE_PERMISSIONS[role] || []
}

export function getRoleLabel(role) {
    return ROLE_LABELS[role] || role
}
