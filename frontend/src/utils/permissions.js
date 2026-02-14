// Role definitions and permissions for APEX POS
// Based on the 12 roles identified from the reference image

export const ROLES = {
    SUPER_ADMIN: 'super_admin',
    ANALIST: 'analist',
    ADMIN_PRODUK: 'admin_produk',
    AUDIT: 'audit',
    SECURITY: 'security',
    LEADER: 'leader',
    DISTRIBUTION: 'distribution',
    SALES: 'sales',
    INVENTORY: 'inventory',
    GUDANG: 'gudang',
    INVENTORY_KASIR: 'inventory_kasir',
    TOKO_ONLINE: 'toko_online',
    LEADER_SHOPEE: 'leader_shopee'
}

export const ROLE_LABELS = {
    [ROLES.SUPER_ADMIN]: 'Super Admin',
    [ROLES.ANALIST]: 'Analist',
    [ROLES.ADMIN_PRODUK]: 'Admin Produk',
    [ROLES.AUDIT]: 'Audit',
    [ROLES.SECURITY]: 'Security',
    [ROLES.LEADER]: 'Leader',
    [ROLES.DISTRIBUTION]: 'Distribution',
    [ROLES.SALES]: 'Sales',
    [ROLES.INVENTORY]: 'Inventory',
    [ROLES.GUDANG]: 'Gudang',
    [ROLES.INVENTORY_KASIR]: 'Inventory & Kasir',
    [ROLES.TOKO_ONLINE]: 'Toko Online',
    [ROLES.LEADER_SHOPEE]: 'Leader Shopee'
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
    ONLINE_ANALYSIS: 'online.analysis'
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
        'master.view' // Added for Brands, Types, Categories
    ],

    [ROLES.AUDIT]: [
        PERMISSIONS.AUDIT_VIEW,
        PERMISSIONS.AUDIT_APPROVE,
        PERMISSIONS.TRANSACTIONS_VIEW,
        PERMISSIONS.BRANCHES_VIEW,
        PERMISSIONS.USERS_VIEW
    ],

    [ROLES.SECURITY]: [
        PERMISSIONS.SECURITY_BARCODE,
        PERMISSIONS.SECURITY_HISTORY,
        PERMISSIONS.TRANSACTIONS_VIEW
    ],

    [ROLES.LEADER]: [
        PERMISSIONS.TRANSACTIONS_VIEW,
        PERMISSIONS.REPORTS_VIEW,
        PERMISSIONS.REPORTS_SALES,
        PERMISSIONS.BRANCHES_VIEW
    ],

    [ROLES.DISTRIBUTION]: [
        PERMISSIONS.DISTRIBUTION_VIEW,
        PERMISSIONS.DISTRIBUTION_SIMULATE,
        PERMISSIONS.INVENTORY_VIEW,
        PERMISSIONS.INVENTORY_STOCK_IN,
        PERMISSIONS.BRANCHES_VIEW
    ],

    [ROLES.SALES]: [
        PERMISSIONS.POS_ACCESS,
        PERMISSIONS.TRANSACTIONS_CREATE,
        PERMISSIONS.TRANSACTIONS_VIEW,
        PERMISSIONS.INVENTORY_VIEW,
        PERMISSIONS.INVENTORY_STOCK_IN, // Allow access to stock in/out pages
        PERMISSIONS.INVENTORY_MANAGE // Allow confirming transfers
    ],

    [ROLES.INVENTORY]: [
        PERMISSIONS.INVENTORY_VIEW,
        PERMISSIONS.INVENTORY_STOCK_IN,
        PERMISSIONS.REPORTS_VIEW
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
        PERMISSIONS.USERS_VIEW // Needed to select target account for stock in
    ],

    [ROLES.LEADER_SHOPEE]: [
        PERMISSIONS.ONLINE_ANALYSIS,
        PERMISSIONS.INVENTORY_VIEW // Read only access to inventory
    ]
}

// Sidebar menu configuration per role
export const ROLE_MENUS = {
    [ROLES.SUPER_ADMIN]: ['dashboard', 'online_scan', 'online_analysis', 'shopee_history', 'pos', 'inventory', 'incoming_transfers', 'retur_items', 'users', 'transactions', 'audit', 'reports', 'settings', 'warehouses', 'distributors', 'channels', 'online_shops', 'brands', 'types', 'prices', 'branches', 'track'],
    [ROLES.ANALIST]: ['dashboard', 'reports', 'track'],
    [ROLES.ADMIN_PRODUK]: ['dashboard', 'brands', 'types', 'prices', 'track'],
    [ROLES.AUDIT]: ['dashboard', 'audit', 'transactions', 'track'],
    [ROLES.SECURITY]: ['dashboard', 'transactions', 'track'],
    [ROLES.LEADER]: ['dashboard', 'transactions', 'reports', 'track'],
    [ROLES.DISTRIBUTION]: ['dashboard', 'inventory', 'incoming_transfers', 'reports', 'track'],
    [ROLES.SALES]: ['dashboard', 'pos', 'transactions', 'inventory', 'track'],
    [ROLES.INVENTORY]: ['dashboard', 'inventory', 'incoming_transfers', 'retur_items', 'track'],
    [ROLES.GUDANG]: ['dashboard', 'inventory', 'incoming_transfers', 'retur_items', 'track'],
    [ROLES.INVENTORY_KASIR]: ['dashboard', 'pos', 'transactions', 'track'],
    [ROLES.TOKO_ONLINE]: ['dashboard', 'online_scan', 'shopee_history', 'reports', 'inventory', 'incoming_transfers', 'track'],
    [ROLES.LEADER_SHOPEE]: ['dashboard', 'online_analysis', 'shopee_history', 'track']
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
