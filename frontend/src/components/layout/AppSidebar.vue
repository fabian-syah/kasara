<script setup>
import { computed, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAuthStore } from "../../store/auth";
import { useThemeStore } from "../../store/theme";
import { getMenuForRole, getRoleLabel } from "../../utils/permissions";
import {
    LayoutDashboard,
    ShoppingCart,
    Package,
    PackageSearch,
    Box,
    Users,
    Receipt,
    ClipboardCheck,
    ClipboardList,
    BarChart3,
    Settings,
    X,
    Building2,
    MapPin,
    Truck,
    Globe,
    Tags,
    ScanBarcode,
    LineChart,
    ChevronRight,
    ChevronDown,
    LogOut,
    Warehouse,
    Database,
    Search,
    ArrowDownRight,
    DollarSign,
    HelpCircle,
    MoreHorizontal
} from "lucide-vue-next";

const props = defineProps({
    isMobileMenuOpen: Boolean,
    isExpanded: {
        type: Boolean,
        default: true
    }
});

const emit = defineEmits(['close-mobile-menu', 'expand-sidebar']);

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const themeStore = useThemeStore();

// Sidebar state
const isMiniSidebar = ref(false);

// Navigation state
const expandedMenus = ref({});

const toggleMenu = (id) => {
    expandedMenus.value = {
        ...expandedMenus.value,
        [id]: !expandedMenus.value[id]
    };
};

// Menu configuration
const menuItems = [
    { id: "dashboard", path: "/", label: "Dashboard", icon: LayoutDashboard },

    // Online Shop Modules
    {
        id: "online_sales_group",
        label: "Penjualan",
        icon: ClipboardList,
        items: [
            { id: "online_sales", path: "/online-shop/sales", label: "Penjualan Online" },
            { id: "shopee_history", path: "/online-shop/history", label: "History Orderan Online" },
        ]
    },
    { id: "online_scan", path: "/online-shop/scan", label: "Scan Pesanan", icon: ScanBarcode },
    { id: "online_analysis", path: "/online-shop/analysis", label: "Analisa Shopee", icon: LineChart },
    {
        id: "reports",
        label: "Pusat Laporan",
        icon: BarChart3,
        items: [
            { id: "report_sales", path: "/reports/sales", label: "Laporan Penjualan (Laku)" },
            { id: "report_brand", path: "/reports/brand", label: "Laporan Brand (Stok)" },
            { id: "report_type", path: "/reports/type", label: "Laporan Tipe (Stok)" },
            { id: "stock_in_history", path: "/inventory/history/in", label: "Riwayat Stok Masuk" },
            { id: "stock_out_history", path: "/inventory/history/out", label: "Riwayat Stok Keluar" },
        ]
    },

    // Modul Cabang Fisik
    { id: "branches", path: "/branches", label: "Cabang Fisik", icon: Building2 },

    // Special
    { id: "distributor_monitoring", path: "/distributor/monitoring", label: "Monitoring Distributor", icon: PackageSearch },
    { id: "online_monitoring", path: "/monitoring/online", label: "Monitoring Stok Online", icon: Globe },
    { id: "warehouse_monitoring", path: "/monitoring/warehouse", label: "Monitoring Stok Gudang", icon: Warehouse },

    { id: "inventory", path: "/inventory", label: "Inventory", icon: Box },
    { id: "products", path: "/products", label: "Produk", icon: Package },
    { id: "users", path: "/users", label: "Staff & Role", icon: Users },
    {
        id: "transactions",
        path: "/transactions",
        label: "Transaksi",
        icon: Receipt,
    },
    {
        id: "audit",
        label: "Audit",
        icon: ClipboardCheck,
        items: [
            { id: "audit_sales_report", path: "/audit/report", label: "Audit Penjualan" },
            { id: "audit_profit_uc", path: "/audit/uc/profit", label: "Audit Profit" },
            { id: "audit_stock_in_uc", path: "/audit/uc/stock-in", label: "Audit Barang Masuk" },
            { id: "audit_stock_out_uc", path: "/audit/uc/stock-out", label: "Audit Barang Keluar" },
        ]
    },

    // Audit Specific Menus (Restored)
    {
        id: "audit_cabang",
        label: "Cabang",
        icon: Building2,
        items: [
            { id: "audit_sales_sub", path: "/audit/sales", label: "Penjualan" },
            { id: "audit_inventory_sub", path: "/audit/inventory", label: "Inventory" },
            { id: "audit_analysis_sub", path: "/audit/analysis", label: "Analisa Cabang" },
        ]
    },
    { id: "audit_sales", path: "/audit/sales", label: "Penjualan", icon: Receipt },
    { id: "audit_inventory", path: "/audit/inventory", label: "Inventory", icon: Package },
    { id: "audit_analysis", path: "/audit/analysis", label: "Analisa Cabang", icon: BarChart3 },

    // Incoming Transfer (Barang Masuk)
    {
        id: "incoming_group",
        label: "OTW",
        icon: ArrowDownRight,
        items: [
            { id: "incoming_transfers", path: "/inventory/incoming-transfers", label: "Konfirmasi Masuk" },
            { id: "incoming_transfer_history", path: "/inventory/incoming-history", label: "Riwayat Masuk" },
            { id: "outgoing_transfer_history", path: "/inventory/outgoing-history", label: "Riwayat Keluar" },
        ]
    },

    // Master Data
    { id: "warehouses", path: "/warehouses", label: "Cabang & Gudang", icon: Warehouse },
    { id: "online_shops", path: "/online-shops", label: "Toko Online", icon: Globe },
    { id: "brands", path: "/brands", label: "Data Merek", icon: Database },
    { id: "types", path: "/types", label: "Tipe Produk", icon: Tags },
    { id: "prices", path: "/prices", label: "Data Harga", icon: DollarSign },
    { id: "categories", path: "/categories", label: "Kategori", icon: Box },
    { id: "distributors", path: "/distributors", label: "Distributor", icon: Truck },

    // Lacak Barang
    { id: "questions", path: "/questions", label: "Pertanyaan", icon: HelpCircle },
    { id: "track", path: "/track", label: "Lacak Barang", icon: Search },

    // Retur Masuk (Gudang)
    { id: "retur_items", path: "/retur-items", label: "Retur Masuk", icon: ArrowDownRight },

    { id: "settings", path: "/settings", label: "Pengaturan", icon: Settings },
    { id: "payment_methods", path: "/settings/payments", label: "Pembayaran", icon: DollarSign },
];

// User info
const userName = computed(() => authStore.userName);
const userRole = computed(() => getRoleLabel(authStore.userRole));
const userBranch = computed(() => authStore.user?.branch?.name || "-");

// Filter menu based on user role
const visibleMenuItems = computed(() => {
    const role = authStore.userRole;
    if (!role) return menuItems.filter((item) => item.id === "dashboard");

    if (role.toLowerCase().replace(/\s+/g, '_') === "super_admin") {
        return menuItems.filter(item => !['audit_sales', 'audit_inventory', 'audit_analysis'].includes(item.id));
    }

    // Get allowed menus for role
    const allowedMenus = getMenuForRole(role);
    let filtered = menuItems.filter((item) => allowedMenus.includes(item.id));

    // For leader role: dynamically hide monitoring menus based on placements
    if (role === 'leader') {
        const userData = authStore.user;
        const placements = userData?.placements || [];
        const placementTypes = placements.map(p => p.model_type);

        const hasDistributor = !!userData?.distributor_id || placementTypes.includes('distributor');
        const hasOnlineShop = !!userData?.online_shop_id || placementTypes.includes('online_shop');
        const hasWarehouse = !!userData?.warehouse_id || placementTypes.includes('warehouse');

        filtered = filtered.filter(item => {
            if (item.id === 'distributor_monitoring' && !hasDistributor) return false;
            if (item.id === 'online_monitoring' && !hasOnlineShop) return false;
            if (item.id === 'warehouse_monitoring' && !hasWarehouse) return false;
            return true;
        });
    }

    return filtered;
});

// Logout handler
async function handleLogout() {
    await authStore.logout();
    router.push("/login");
}

// Check active route
function isActiveRoute(path) {
    if (path === "/") return route.path === "/";
    return route.path.startsWith(path);
}

function isGroupActive(items) {
    return items.some(item => isActiveRoute(item.path));
}

// Close mobile menu on route change
watch(() => route.path, () => {
    emit('close-mobile-menu');
});
</script>

<template>
    <aside
        class="fixed inset-y-0 left-0 z-[99999] flex flex-col bg-white/80 dark:bg-[#050505]/90 backdrop-blur-2xl border-r border-neutral-200/50 dark:border-neutral-800/60 transition-all duration-300 lg:static"
        :class="[
            isExpanded ? 'w-[290px]' : 'w-[90px]',
            isMobileMenuOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
        ]">
        <!-- Logo Section -->
        <div
            class="flex items-center justify-between h-[72px] px-6 border-b border-surface-200 dark:border-surface-800 shrink-0">
            <router-link to="/" class="flex items-center gap-3">
                <img src="/images/logo-pstore.png" alt="PSTORE POS" class="w-12 h-12 object-contain dark:brightness-0 dark:invert" />
                <span v-show="isExpanded" class="text-xl font-bold text-text-primary whitespace-nowrap">
                    PSTORE <span class="text-primary-500">POS</span>
                </span>
            </router-link>
            <!-- Close Button (Mobile Only) -->
            <button @click="emit('close-mobile-menu')"
                class="lg:hidden text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
                <X :size="20" />
            </button>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 overflow-y-auto px-4 py-6 space-y-1 sidebar-scrollbar">
            <p v-show="isExpanded"
                class="px-3 text-[11px] font-semibold text-text-secondary uppercase tracking-wider mb-3">
                Menu
            </p>
            <div v-show="!isExpanded" class="flex justify-center mb-3">
                <MoreHorizontal :size="16" class="text-text-secondary" />
            </div>

            <div v-for="item in visibleMenuItems" :key="item.id">
                <!-- Dropdown Menu -->
                <div v-if="item.items" class="space-y-0.5">
                    <button @click.prevent="toggleMenu(item.id); if (!isExpanded) emit('expand-sidebar');" type="button"
                        class="w-full flex items-center rounded-lg font-medium transition-all duration-200 group"
                        :class="[
                            isExpanded ? 'gap-3 px-3 py-2.5' : 'justify-center p-2.5',
                            isGroupActive(item.items)
                                ? 'bg-primary-50 dark:bg-primary-500/10 text-primary-600 dark:text-primary-400'
                                : 'text-text-secondary hover:bg-surface-100 dark:hover:bg-surface-800 hover:text-text-primary'
                        ]">
                        <component :is="item.icon" :size="20" class="shrink-0" />
                        <span v-show="isExpanded" class="text-sm flex-1 text-left">{{ item.label }}</span>
                        <ChevronDown v-show="isExpanded" :size="16" class="shrink-0 transition-transform duration-200"
                            :class="{ 'rotate-180': expandedMenus[item.id] || isGroupActive(item.items) }" />
                    </button>

                    <!-- Submenu Items -->
                    <div v-if="isExpanded" v-show="expandedMenus[item.id] || isGroupActive(item.items)"
                        class="ml-9 space-y-0.5 mt-1">
                        <router-link v-for="subitem in item.items" :key="subitem.id" :to="subitem.path"
                            class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-[13px] font-medium transition-all duration-200"
                            :class="isActiveRoute(subitem.path)
                                ? 'text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-500/10'
                                : 'text-text-secondary hover:text-text-primary hover:bg-surface-50 dark:hover:bg-surface-800/50'
                                ">
                            <div class="w-1.5 h-1.5 rounded-full shrink-0"
                                :class="isActiveRoute(subitem.path) ? 'bg-primary-500' : 'bg-gray-300 dark:bg-gray-600'">
                            </div>
                            <span>{{ subitem.label }}</span>
                        </router-link>
                    </div>
                </div>

                <!-- Regular Link -->
                <router-link v-else :to="item.path"
                    class="flex items-center rounded-lg font-medium transition-all duration-200 group" :class="[
                        isExpanded ? 'gap-3 px-3 py-2.5' : 'justify-center p-2.5',
                        isActiveRoute(item.path)
                            ? 'bg-primary-50 dark:bg-primary-500/10 text-primary-600 dark:text-primary-400'
                            : 'text-text-secondary hover:bg-surface-100 dark:hover:bg-surface-800 hover:text-text-primary'
                    ]" :title="!isExpanded ? item.label : undefined">
                    <component :is="item.icon" :size="20" class="shrink-0" />
                    <span v-show="isExpanded" class="text-sm">{{ item.label }}</span>
                </router-link>
            </div>
        </nav>

        <!-- User Section -->
        <div class="border-t border-gray-200 dark:border-gray-800 p-4">
            <div v-if="isExpanded" class="flex items-center gap-3 mb-3 px-1">
                <img :src="authStore.user?.photo
                    ? (authStore.user.photo.startsWith('http') ? authStore.user.photo : `${authStore.storageBaseUrl}/storage/${authStore.user.photo}`)
                    : `https://ui-avatars.com/api/?name=${encodeURIComponent(userName)}&background=${themeStore.isDark ? '3b82f6' : '0f172a'}&color=fff&size=128`"
                    class="w-10 h-10 rounded-full border-2 border-surface-200 dark:border-surface-700 object-cover"
                    :alt="userName"
                    @error="(e) => e.target.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(userName)}&background=${themeStore.isDark ? '3b82f6' : '0f172a'}&color=fff&size=128`" />
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-text-primary truncate">
                        {{ userName }}
                    </p>
                    <p class="text-[11px] text-primary-500 font-medium uppercase tracking-wide">
                        {{ userRole }}
                    </p>
                </div>
            </div>
            <button @click="handleLogout"
                class="flex items-center w-full rounded-lg transition-all duration-200 text-text-secondary hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10"
                :class="isExpanded ? 'gap-3 px-3 py-2.5' : 'justify-center p-2.5'">
                <LogOut :size="20" />
                <span v-show="isExpanded" class="text-sm font-medium">Keluar</span>
            </button>
        </div>
    </aside>
</template>

<style scoped>
.sidebar-scrollbar::-webkit-scrollbar {
    width: 4px;
}

.sidebar-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}

.sidebar-scrollbar::-webkit-scrollbar-thumb {
    background-color: rgba(156, 163, 175, 0.3);
    border-radius: 9999px;
}

.sidebar-scrollbar::-webkit-scrollbar-thumb:hover {
    background-color: rgba(156, 163, 175, 0.5);
}
</style>
