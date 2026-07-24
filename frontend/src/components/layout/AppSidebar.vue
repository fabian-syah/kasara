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
    MoreHorizontal,
    Trophy,
    History,
    PieChart,
    UserCircle
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
    { id: "security_scan", path: "/security-scan/start", label: "Scan QR Security", icon: ScanBarcode },
    { id: "security_history", path: "/security-scan/history", label: "History Security", icon: History },

    // Online Shop Modules
    {
        id: "online_sales_group",
        label: "Modul Online Shop",
        icon: Globe,
        items: [
            { id: "online_sales", path: "/online-shop/sales", label: "Penjualan Online" },
            { id: "shopee_history", path: "/online-shop/history", label: "History Orderan Online" },
            { id: "online_scan", path: "/online-shop/scan", label: "Scan Pesanan" },
            { id: "online_analysis", path: "/online-shop/analysis", label: "Analisa Shopee" },
        ]
    },
    {
        id: "reports",
        label: "Pusat Laporan",
        icon: BarChart3,
        items: [
            { id: "report_sales", path: "/reports/sales", label: "Laporan Penjualan (Laku)" },
            { id: "report_ranking", path: "/reports/ranking", label: "Ranking Cabang (Omset)" },
            { id: "report_brand", path: "/reports/brand", label: "Laporan Brand (Stok)" },
            { id: "report_type", path: "/reports/type", label: "Laporan Tipe (Stok)" },
            { id: "stock_in_history", path: "/inventory/history/in", label: "Riwayat Stok Masuk" },
            { id: "stock_out_history", path: "/inventory/history/out", label: "Riwayat Stok Keluar" },
        ]
    },

    // Modul Cabang Fisik
    { id: "branches", path: "/branches", label: "Cabang Fisik", icon: Building2 },

    // Special
    {
        id: "monitoring_group",
        label: "Pusat Monitoring",
        icon: BarChart3,
        items: [
            { id: "stock_summary", path: "/monitoring/summary", label: "Ringkasan Stok" },
            { id: "distributor_monitoring", path: "/distributor/monitoring", label: "Monitoring Distributor" },
            { id: "online_monitoring", path: "/monitoring/online", label: "Monitoring Online" },
            { id: "warehouse_monitoring", path: "/monitoring/warehouse", label: "Monitoring Gudang" },
        ]
    },

    {
        id: "inventory",
        label: "Inventory",
        icon: Box,
        items: [
            { id: "inventory_main", path: "/inventory", label: "Data Inventory" },
            { id: "inventory_opname", path: "/inventory/stock-opname", label: "Stok Opname" },
            { id: "download_center", path: "/inventory/download-center", label: "Download Center" },
            { id: "inventory_monitoring_hub", path: "/inventory/monitoring-otw", label: "Monitoring OTW" },
            { id: "audit_input_transfer", path: "/audit/input-transfer", label: "Kirim Cabang" },
            { id: "retur_items", path: "/retur-items", label: "Retur Masuk (Gudang)" },
        ]
    },

    {
        id: "analysis_group",
        label: "Analisis",
        icon: PieChart,
        items: [
            { id: "stock_analysis", path: "/inventory/stock-analysis", label: "Analisa Stok" },
            { id: "sold_analysis", path: "/inventory/sold-analysis", label: "Analisa Produk Terjual" },
        ]
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
            { id: "audit_pin_resets", path: "/audit/pin-resets", label: "Permintaan PIN" },
            { id: "audit_photo_approvals", path: "/audit/photo-approvals", label: "Persetujuan Foto" },
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

    // Master Data
    {
        id: "master_data_group",
        label: "Modul Master Data",
        icon: Database,
        items: [
            { id: "users", path: "/users", label: "Staff & Role" },
            { id: "warehouses", path: "/warehouses", label: "Cabang & Gudang" },
            { id: "online_shops", path: "/online-shops", label: "Toko Online" },
            { id: "brands", path: "/brands", label: "Data Merek" },
            { id: "types", path: "/types", label: "Tipe Produk" },
            { id: "prices", path: "/prices", label: "Data Harga" },
            { id: "categories", path: "/categories", label: "Kategori" },
            { id: "distributors", path: "/distributors", label: "Distributor" },
            { id: "payment_methods", path: "/settings/payments", label: "Metode Pembayaran" },
        ]
    },

    // Liga Cabang (Super Admin)
    { id: "branch_league", path: "/admin/leagues", label: "Liga Cabang", icon: Trophy },

    // Sales Menus
    { id: "sales_create", path: "/sales/create", label: "Buat Penjualan", icon: ShoppingCart },
    {
        id: "sales_check",
        label: "Cek Penjualan",
        icon: ClipboardList,
        items: [
            { id: "sales_check_main", path: "/sales/check", label: "Riwayat Penjualan" },
            { id: "sales_ranking", path: "/sales/ranking", label: "Peringkat Penjualan" },
            { id: "custom_nota", path: "/sales/custom-nota", label: "Custom Nota" },
        ]
    },

    // Lacak Barang
    {
        id: "support_group",
        label: "Lacak & Bantuan",
        icon: Search,
        items: [
            { id: "track", path: "/track", label: "Lacak & History IMEI" },
            { id: "questions", path: "/questions", label: "Pertanyaan CS/User" },
        ]
    },

    { id: "settings", path: "/settings", label: "Pengaturan", icon: Settings },
    { id: "profile_inventory", path: "/settings/profile-inventory", label: "Pengaturan Pribadi", icon: UserCircle },
];

// User info
const userName = computed(() => authStore.userName);
const userRole = computed(() => getRoleLabel(authStore.userRole));
const userBranch = computed(() => authStore.user?.branch?.name || "-");

// Filter menu based on user role
const visibleMenuItems = computed(() => {
    const role = authStore.userRole;
    if (!role) return menuItems.filter((item) => item.id === "dashboard");

    let filtered = [];

    // Khusus untuk role inventory, hanya tampilkan Dashboard, Buat Penjualan, dan Cek Penjualan, serta Pengaturan Pribadi, Stok Opname, Lacak IMEI
    if (role === 'inventory') {
        filtered = menuItems.filter(item => ['dashboard', 'sales_create', 'sales_check', 'inventory', 'support_group', 'profile_inventory'].includes(item.id));
        
        filtered = filtered.map(group => {
            if (group.id === 'sales_check' && group.items) {
                return {
                    ...group,
                    items: group.items.filter(sub => sub.id === 'sales_check_main')
                };
            }
            if (group.id === 'inventory' && group.items) {
                return {
                    ...group,
                    items: group.items.filter(sub => sub.id === 'inventory_opname')
                };
            }
            if (group.id === 'support_group' && group.items) {
                return {
                    ...group,
                    items: group.items.filter(sub => sub.id === 'track')
                };
            }
            return group;
        });

        return filtered;
    }

    if (role.toLowerCase().replace(/\s+/g, '_') === "super_admin") {
        filtered = menuItems.filter(item => !['audit_sales', 'audit_inventory', 'audit_analysis'].includes(item.id));
    } else {
        // Get allowed menus for role
        const allowedMenus = getMenuForRole(role);
        filtered = menuItems.filter((item) => allowedMenus.includes(item.id));
    }

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

    // Hide Custom Nota menu unless the user is superfabian
    const userIdentifier = authStore.user?.username?.toLowerCase() || authStore.user?.name?.toLowerCase();
    filtered = filtered.map(group => {
        if (group.items) {
            return {
                ...group,
                items: group.items.filter(sub => {
                    if (sub.id === 'custom_nota' && userIdentifier !== 'superfabian') {
                        return false;
                    }
                    return true;
                })
            };
        }
        if (group.id === 'custom_nota' && userIdentifier !== 'superfabian') {
            return null;
        }
        return group;
    }).filter(Boolean);

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
        class="fixed inset-y-0 left-0 z-[99999] flex flex-col bg-white/70 dark:bg-[#050505]/70 backdrop-blur-3xl border border-neutral-200/50 dark:border-neutral-800/60 shadow-xl transition-[width,transform,opacity] duration-300 lg:static rounded-2xl md:rounded-[24px] overflow-hidden"
        :class="[
            isExpanded ? 'w-[290px]' : 'w-[90px]',
            isMobileMenuOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
        ]">

        <!-- macOS Traffic Lights (Desktop Only) -->
        <div class="hidden lg:flex items-center gap-2 px-6 pt-5 pb-1">
            <div class="w-3 h-3 rounded-full bg-red-500 hover:bg-red-600 cursor-pointer transition-colors shadow-sm">
            </div>
            <div
                class="w-3 h-3 rounded-full bg-amber-500 hover:bg-amber-600 cursor-pointer transition-colors shadow-sm">
            </div>
            <div class="w-3 h-3 rounded-full bg-emerald-500 hover:bg-emerald-600 cursor-pointer transition-colors shadow-sm"
                @click="emit('expand-sidebar')"></div>
        </div>

        <!-- Logo Section -->
        <div
            class="flex items-center justify-between px-6 py-4 shrink-0 border-b border-surface-200/50 dark:border-surface-800/50">
            <router-link to="/" class="flex items-center gap-3">
                <span v-show="isExpanded"
                    class="text-xl font-bold bg-gradient-to-r from-neutral-900 to-neutral-600 dark:from-white dark:to-neutral-400 bg-clip-text text-transparent whitespace-nowrap">
                    KASARA
                </span>
            </router-link>
            <!-- Close Button (Mobile Only) -->
            <button @click="emit('close-mobile-menu')" aria-label="Tutup Menu"
                class="lg:hidden p-2 bg-neutral-100 dark:bg-neutral-800 text-text-secondary hover:text-text-primary rounded-xl transition-colors">
                <X :size="20" />
            </button>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 overflow-y-auto px-3 py-6 space-y-1.5 sidebar-scrollbar">
            <p v-show="isExpanded"
                class="px-4 text-[11px] font-semibold text-text-secondary uppercase tracking-wider mb-4">
                Menu
            </p>
            <div v-show="!isExpanded" class="flex justify-center mb-4">
                <MoreHorizontal :size="16" class="text-text-secondary" />
            </div>

            <div v-for="item in visibleMenuItems" :key="item.id">
                <!-- Dropdown Menu -->
                <div v-if="item.items" class="space-y-1">
                    <button @click.prevent="toggleMenu(item.id); if (!isExpanded) emit('expand-sidebar');" type="button"
                        class="w-full flex items-center rounded-[14px] font-medium transition-all duration-300 group"
                        :class="[
                            isExpanded ? 'gap-3 px-4 py-3' : 'justify-center p-3',
                            isGroupActive(item.items)
                                ? 'bg-primary-500/10 text-primary-600 dark:text-primary-400'
                                : 'text-text-secondary hover:bg-neutral-100/80 dark:hover:bg-neutral-800/80 hover:text-text-primary'
                        ]">
                        <component :is="item.icon" :size="20" class="shrink-0" />
                        <span v-show="isExpanded" class="text-sm flex-1 text-left">{{ item.label }}</span>
                        <ChevronDown v-show="isExpanded" :size="16"
                            class="shrink-0 transition-transform duration-300 opacity-60 group-hover:opacity-100"
                            :class="{ 'rotate-180': expandedMenus[item.id] || isGroupActive(item.items) }" />
                    </button>

                    <!-- Submenu Items -->
                    <div v-if="isExpanded" v-show="expandedMenus[item.id] || isGroupActive(item.items)"
                        class="ml-8 space-y-1 mt-1.5 border-l-2 border-neutral-100 dark:border-neutral-800/60 pl-2">
                        <router-link
                            v-for="subitem in item.items.filter(si => visibleMenuItems.some(v => v.id === si.id) || getMenuForRole(authStore.userRole).includes(si.id))"
                            :key="subitem.id" :to="subitem.path"
                            class="flex items-center gap-3 px-3 py-2.5 rounded-[12px] text-[13px] font-medium transition-all duration-300"
                            :class="isActiveRoute(subitem.path)
                                ? 'text-primary-600 dark:text-primary-400 bg-primary-500/10 shadow-sm'
                                : 'text-text-secondary hover:text-text-primary hover:bg-neutral-100/50 dark:hover:bg-neutral-800/50'
                                ">
                            <div class="w-1.5 h-1.5 rounded-full shrink-0"
                                :class="isActiveRoute(subitem.path) ? 'bg-primary-500' : 'bg-neutral-300 dark:bg-neutral-600'">
                            </div>
                            <span>{{ subitem.label }}</span>
                        </router-link>
                    </div>
                </div>

                <!-- Regular Link -->
                <router-link v-else :to="item.path"
                    class="flex items-center rounded-[14px] font-medium transition-all duration-300 group" :class="[
                        isExpanded ? 'gap-3 px-4 py-3' : 'justify-center p-3',
                        isActiveRoute(item.path)
                            ? 'bg-primary-500/10 text-primary-600 dark:text-primary-400 shadow-sm'
                            : 'text-text-secondary hover:bg-neutral-100/80 dark:hover:bg-neutral-800/80 hover:text-text-primary'
                    ]" :title="!isExpanded ? item.label : undefined">
                    <component :is="item.icon" :size="20"
                        class="shrink-0 transition-transform duration-300 group-hover:scale-110" />
                    <span v-show="isExpanded" class="text-sm">{{ item.label }}</span>
                </router-link>
            </div>
        </nav>

        <!-- User Section -->
        <div class="border-t border-surface-200/50 dark:border-surface-800/50 p-4">
            <div v-if="isExpanded" class="flex items-center gap-3 mb-4 px-2">
                <img :src="authStore.userPhotoUrl
                    ? authStore.userPhotoUrl
                    : `https://ui-avatars.com/api/?name=${encodeURIComponent(userName)}&background=${themeStore.isDark ? '10b981' : '050505'}&color=fff&size=128`"
                    class="w-10 h-10 rounded-full border-2 border-surface-200 dark:border-surface-700 object-cover shadow-sm transition-transform hover:scale-105"
                    :alt="userName"
                    @error="(e) => e.target.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(userName)}&background=${themeStore.isDark ? '10b981' : '050505'}&color=fff&size=128`" />
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-text-primary truncate">
                        {{ userName }}
                    </p>
                    <p class="text-[11px] text-primary-500 font-medium uppercase tracking-wide">
                        {{ userRole }}
                    </p>
                    <p v-if="authStore.user?.branch?.name" class="text-[10px] text-text-secondary truncate mt-0.5">
                        {{ authStore.user.branch.name }}
                    </p>
                    <div v-if="authStore.user?.league"
                        class="mt-1 inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold" :class="{
                            'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400': authStore.user.league.key === 'liga_1',
                            'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400': authStore.user.league.key === 'liga_2',
                            'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400': authStore.user.league.key === 'zona_merah',
                            'bg-surface-100 dark:bg-surface-700 text-surface-500': authStore.user.league.key === 'non_liga',
                        }">
                        <Trophy :size="10" />
                        {{ authStore.user.league.label }}
                    </div>
                </div>
            </div>
            <button @click="handleLogout"
                class="flex items-center w-full rounded-[14px] transition-all duration-300 text-text-secondary hover:text-red-500 hover:bg-red-500/10"
                :class="isExpanded ? 'gap-3 px-4 py-3' : 'justify-center p-3'">
                <LogOut :size="20" class="transition-transform group-hover:-translate-x-1" />
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
