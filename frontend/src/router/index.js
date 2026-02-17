import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../store/auth'

// Layouts
import MainLayout from '../layouts/MainLayout.vue'

// Auth Views
import Login from '../views/auth/Login.vue'

// Lazy-loaded Views
const Dashboard = () => import('../views/admin/Dashboard.vue')
const POS = () => import('../views/cashier/POS.vue')
const Inventory = () => import('../views/inventory/Inventory.vue')
const Products = () => import('../views/products/Products.vue')
const Users = () => import('../views/users/Users.vue')
const Transactions = () => import('../views/transactions/Transactions.vue')
const Audit = () => import('../views/audit/Audit.vue')
const Reports = () => import('../views/reports/Reports.vue')

const routes = [
    // Auth Routes (Public)
    {
        path: '/login',
        name: 'Login',
        component: Login,
        meta: { requiresGuest: true }
    },

    // Main App Routes (Protected)
    {
        path: '/',
        component: MainLayout,
        meta: { requiresAuth: true },
        children: [
            {
                path: '',
                name: 'Dashboard',
                component: Dashboard,
                meta: { title: 'Dashboard', menu: 'dashboard' }
            },
            {
                path: 'pos',
                name: 'POS',
                component: POS,
                meta: {
                    title: 'Kasir (POS)',
                    menu: 'pos',
                    permissions: ['pos.access']
                }
            },
            {
                path: 'reports/brand',
                name: 'BrandReport',
                component: () => import('../views/reports/BrandReport.vue'),
                meta: {
                    title: 'Laporan Brand',
                    menu: 'reports',
                    permissions: ['inventory.view'] // Allow all who can view products for now
                }
            },
            {
                path: 'reports/type',
                name: 'TypeReport',
                component: () => import('../views/reports/TypeReport.vue'),
                meta: {
                    title: 'Laporan Tipe',
                    menu: 'reports',
                    permissions: ['inventory.view']
                }
            },
            {
                path: 'reports/sales',
                name: 'SalesReport',
                component: () => import('../views/reports/SalesReport.vue'),
                meta: {
                    title: 'Laporan Penjualan',
                    menu: 'reports',
                    permissions: ['inventory.view', 'reports.view']
                }
            },
            {
                path: 'inventory',
                name: 'Inventory',
                component: Inventory,
                meta: {
                    title: 'Inventory',
                    menu: 'inventory',
                    permissions: ['inventory.view', 'audit.view']
                }
            },
            {
                path: 'inventory/history/in',
                name: 'StockInHistory',
                component: () => import('../views/inventory/StockInHistory.vue'),
                meta: {
                    title: 'Daftar Stok Masuk',
                    menu: 'reports',
                    permissions: ['inventory.view', 'audit.view']
                }
            },
            {
                path: 'inventory/history/out',
                name: 'StockOutHistory',
                component: () => import('../views/inventory/StockOutHistory.vue'),
                meta: {
                    title: 'Daftar Stok Keluar',
                    menu: 'reports',
                    permissions: ['inventory.view', 'audit.view']
                }
            },
            {
                path: 'inventory/stock-opname',
                name: 'StockOpname',
                component: () => import('../views/inventory/StockOpname.vue'),
                meta: {
                    title: 'Stock Opname',
                    menu: 'inventory',
                    permissions: ['inventory.view']
                }
            },
            {
                path: 'inventory/stock-in',
                name: 'StockIn',
                component: () => import('../views/inventory/StockIn.vue'),
                meta: {
                    title: 'Input Barang Masuk',
                    menu: 'inventory', // Highlight Inventory menu
                    permissions: ['inventory.view', 'inventory.manage', 'inventory.stock_in']
                }
            },
            {
                path: 'inventory/stock-out',
                name: 'StockOut',
                component: () => import('../views/inventory/StockOut.vue'),
                meta: {
                    title: 'Pengeluaran Stok',
                    menu: 'inventory',
                    permissions: ['inventory.manage', 'inventory.stock_in', 'inventory.stock_out']
                }
            },
            {
                path: 'track',
                name: 'TrackStock',
                component: () => import('../views/inventory/TrackStock.vue'),
                meta: {
                    title: 'Lacak Barang',
                    menu: 'track',
                    permissions: ['inventory.view', 'audit.view']
                }
            },
            {
                path: 'inventory/incoming-transfers',
                name: 'IncomingTransfers',
                component: () => import('../views/inventory/IncomingTransfers.vue'),
                meta: {
                    title: 'Barang Masuk Transfer',
                    menu: 'inventory',
                    permissions: ['inventory.manage', 'inventory.stock_in']
                }
            },
            {
                path: 'inventory/incoming-history',
                name: 'incoming_transfer_history',
                component: () => import('../views/inventory/IncomingTransferHistory.vue'),
                meta: {
                    title: 'Riwayat Transfer Masuk',
                    menu: 'inventory',
                    permissions: ['inventory.view']
                }
            },
            {
                path: 'retur-items',
                name: 'ReturItems',
                component: () => import('../views/inventory/ReturItems.vue'),
                meta: {
                    title: 'Retur Masuk',
                    menu: 'retur_items',
                    permissions: ['inventory.view', 'inventory.manage']
                }
            },
            {
                path: 'products',
                name: 'Products',
                component: Products,
                meta: {
                    title: 'Produk',
                    menu: 'products',
                    permissions: ['products.view']
                }
            },
            {
                path: 'users',
                name: 'Users',
                component: Users,
                meta: {
                    title: 'Staff & Role',
                    menu: 'users',
                    permissions: ['users.view']
                }
            },
            {
                path: 'transactions',
                name: 'Transactions',
                component: Transactions,
                meta: {
                    title: 'Transaksi',
                    menu: 'transactions',
                    permissions: ['transactions.view']
                }
            },
            {
                path: 'audit',
                redirect: '/audit/sales', // Redirect to sales by default
                meta: { title: 'Audit', menu: 'audit', permissions: ['audit.view'] }
            },
            {
                path: 'audit/sales',
                name: 'AuditSales',
                component: () => import('../views/audit/Sales.vue'),
                meta: { title: 'Audit Penjualan', menu: 'audit_sales', permissions: ['audit.view'] }
            },
            {
                path: 'audit/inventory',
                name: 'AuditInventory',
                component: () => import('../views/audit/Inventory.vue'),
                meta: { title: 'Audit Inventory', menu: 'audit_inventory', permissions: ['audit.view'] }
            },
            {
                path: 'audit/analysis',
                name: 'AuditAnalysis',
                component: () => import('../views/audit/BranchAnalysis.vue'),
                meta: { title: 'Analisa Cabang', menu: 'audit_analysis', permissions: ['audit.view'] }
            },
            {
                path: 'reports',
                name: 'Reports',
                component: Reports,
                meta: {
                    title: 'Laporan',
                    menu: 'reports',
                    permissions: ['reports.view']
                }
            },
            {
                path: 'settings',
                name: 'Settings',
                component: () => import('../views/settings/Settings.vue'),
                meta: {
                    title: 'Pengaturan Profil',
                    menu: 'settings'
                }
            },

            // Master Data Routes (Super Admin)
            {
                path: 'warehouses',
                name: 'Warehouses',
                component: () => import('../views/warehouses/Index.vue'),
                meta: {
                    title: 'Data Gudang',
                    menu: 'warehouses',
                    permissions: ['master.view']
                }
            },
            {
                path: 'locations', // Keeping purely for backward compat if sidebar uses it, or redirect?
                // Actually let's assume locations might be something else later (Zoning?), but for now user asked for Gudang.
                // Let's just Add Warehouses and update sidebar. 
                redirect: 'warehouses'
            },
            {
                path: 'distributors',
                name: 'Distributors',
                component: () => import('../views/master/Distributors.vue'),
                meta: {
                    title: 'Distributor',
                    menu: 'distributors',
                    permissions: ['master.view']
                }
            },
            {
                path: 'online-shops',
                name: 'OnlineShops',
                component: () => import('../views/online-shop/Index.vue'),
                meta: {
                    title: 'Toko Online',
                    menu: 'online_shops',
                    permissions: ['master.view']
                }
            },
            {
                path: 'brands',
                name: 'Brands',
                component: () => import('../views/master/brands/Index.vue'),
                meta: {
                    title: 'Data Merek',
                    menu: 'brands',
                    permissions: ['master.view']
                }
            },
            {
                path: 'types',
                name: 'ProductTypes',
                component: () => import('../views/master/types/Index.vue'),
                meta: {
                    title: 'Tipe Produk',
                    menu: 'types',
                    permissions: ['master.view']
                }
            },
            {
                path: 'prices',
                name: 'ProductPrices',
                component: () => import('../views/master/prices/Index.vue'),
                meta: {
                    title: 'Data Harga',
                    menu: 'prices',
                    permissions: ['master.view']
                }
            },
            {
                path: 'categories',
                name: 'Categories',
                component: () => import('../views/master/Categories.vue'),
                meta: {
                    title: 'Kategori & Jenis',
                    menu: 'categories',
                    permissions: ['master.view']
                }
            },
            {
                path: 'branches',
                name: 'Branches',
                component: () => import('../views/branches/Index.vue'),
                meta: {
                    title: 'Cabang Fisik',
                    menu: 'branches',
                    permissions: ['branches.view']
                }
            },

            // Online Shop Routes
            {
                path: 'online-shop/scan',
                name: 'OnlineScan',
                component: () => import('../views/online-shop/OrderScan.vue'),
                meta: {
                    title: 'Scan Pesanan',
                    menu: 'online_scan',
                    permissions: ['online.scan']
                }
            },
            {
                path: 'online-shop/inventory',
                // Reuse existing inventory path? User said "inventory gudangnya... inventory distributornya".
                // But specifically for 'toko_online', they might use this view.
                // Actually, the user might want the STANDARD inventory view but filtered?
                // "inventory tuh cuman bisa lihat history bulan ini dan bulan lalu doang" -> This specific view checks that.
                // Let's protect this route or map '/inventory' to this component IF role is 'toko_online'?
                // For simplicity, let's use a explicit route.
                name: 'OnlineInventory',
                component: () => import('../views/online-shop/InventoryHistory.vue'),
                meta: {
                    title: 'History Inventory',
                    menu: 'inventory',
                    permissions: ['inventory.view']
                }
            },
            {
                path: 'online-shop/analysis',
                name: 'OnlineAnalysis',
                component: () => import('../views/online-shop/Analytics.vue'),
                meta: {
                    title: 'Analisa Shopee',
                    menu: 'online_analysis',
                    permissions: ['online.analysis']
                }
            },
            {
                path: 'online-shop/sales',
                name: 'OnlineSales',
                component: () => import('../views/online-shop/OnlineSales.vue'),
                meta: {
                    title: 'Penjualan Online',
                    menu: 'online_sales',
                    permissions: ['online.orders']
                }
            },
            {
                path: 'online-shop/history',
                name: 'ShopeeHistory',
                component: () => import('../views/online-shop/ShopeeHistory.vue'),
                meta: {
                    title: 'History Shopee',
                    menu: 'shopee_history',
                    permissions: ['online.scan', 'online.analysis']
                }
            },
            {
                path: 'system-status',
                name: 'SystemStatus',
                component: () => import('../views/admin/SystemStatus.vue'),
                meta: {
                    title: 'VPS Status',
                    menu: 'system_status',
                }
            }
        ]
    },

    // 404 Catch-all
    {
        path: '/:pathMatch(.*)*',
        redirect: '/'
    }
]

const router = createRouter({
    history: createWebHistory(),
    routes
})

// Navigation Guards
router.beforeEach(async (to, from, next) => {
    const authStore = useAuthStore()

    // Check if route requires authentication
    if (to.meta.requiresAuth) {
        if (!authStore.isAuthenticated) {
            return next({ name: 'Login', query: { redirect: to.fullPath } })
        }

        // If authenticated but user profile is missing (e.g., page refresh)
        // Try to fetch user data
        if (!authStore.user) {
            try {
                await authStore.fetchUser()
                if (!authStore.user) {
                    // If still no user after fetch attempt, force logout/login
                    return next({ name: 'Login' })
                }
            } catch (error) {
                return next({ name: 'Login' })
            }
        }
    }

    // Check if route is for guests only (like login)
    if (to.meta.requiresGuest && authStore.isAuthenticated) {
        return next({ name: 'Dashboard' })
    }

    // Check permissions if defined
    if (to.meta.permissions && to.meta.permissions.length > 0) {
        // Super admin bypass
        if (authStore.userRole === 'super_admin') {
            return next()
        }

        const userPermissions = authStore.user?.permissions || []

        // Super admin has all permissions (legacy check)
        if (!userPermissions.includes('*')) {
            const hasPermission = to.meta.permissions.some(perm =>
                userPermissions.includes(perm)
            )

            if (!hasPermission) {
                return next({ name: 'Dashboard' })
            }
        }
    }

    next()
})

// Update document title
router.afterEach((to) => {
    document.title = to.meta.title
        ? `${to.meta.title} | APEX POS`
        : 'APEX POS'
})

// Handle chunk load errors (e.g., after deployment)
router.onError((error, to) => {
    if (error.message.includes('Failed to fetch dynamically imported module') || error.message.includes('Importing a module script failed')) {
        if (!to?.path) return

        // Prevent infinite reload loop
        const targetPath = to.fullPath
        const reloadKey = `reload-${targetPath}`
        const lastReload = sessionStorage.getItem(reloadKey)
        const now = Date.now()

        if (!lastReload || now - parseInt(lastReload) > 10000) {
            sessionStorage.setItem(reloadKey, now.toString())
            window.location.assign(targetPath)
        }
    }
})

export default router