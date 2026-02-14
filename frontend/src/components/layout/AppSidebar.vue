<script setup>
import { computed, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAuthStore } from "../../store/auth";
import { useThemeStore } from "../../store/theme";
import { getMenuForRole, getRoleLabel } from "../../utils/permissions";
import {
    LayoutDashboard,
    ShoppingCart,
    Package,
    Box,
    Users,
    Receipt,
    ClipboardCheck,
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
    DollarSign
} from "lucide-vue-next";

const props = defineProps({
    isMobileMenuOpen: Boolean,
});

const emit = defineEmits(['close-mobile-menu']);

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const themeStore = useThemeStore();

// Navigation state
const expandedMenus = ref({
    reports: false,
});

const toggleMenu = (id) => {
    // Gunakan spread operator atau pastikan key sudah ada agar reaktif
    expandedMenus.value = {
        ...expandedMenus.value,
        [id]: !expandedMenus.value[id]
    };
    console.log('New state for', id, ':', expandedMenus.value[id]);
};

// Menu configuration
const menuItems = [
    { id: "dashboard", path: "/", label: "Dashboard", icon: LayoutDashboard },

    // Online Shop Modules
    { id: "online_scan", path: "/online-shop/scan", label: "Scan Pesanan", icon: ScanBarcode },
    { id: "online_analysis", path: "/online-shop/analysis", label: "Analisa Shopee", icon: LineChart },
    { id: "shopee_history", path: "/online-shop/history", label: "History Orderan Online", icon: Receipt },
    {
        id: "reports",
        label: "Laporan Stok",
        icon: BarChart3,
        items: [
            { id: "report_brand", path: "/reports/brand", label: "Laporan Brand" },
            { id: "report_type", path: "/reports/type", label: "Laporan Tipe" },
        ]
    },

    // Modul Cabang Fisik (TAMBAHKAN INI)
    { id: "branches", path: "/branches", label: "Cabang Fisik", icon: Building2 },

    //   { id: "pos", path: "/pos", label: "Kasir (POS)", icon: ShoppingCart },
    { id: "inventory", path: "/inventory", label: "Inventory", icon: Box },
    { id: "products", path: "/products", label: "Produk", icon: Package },
    { id: "users", path: "/users", label: "Staff & Role", icon: Users },
    {
        id: "transactions",
        path: "/transactions",
        label: "Transaksi",
        icon: Receipt,
    },
    { id: "audit", path: "/audit", label: "Audit", icon: ClipboardCheck },

    // Incoming Transfer (Barang Masuk)
    {
        id: "incoming_group",
        label: "Barang Masuk",
        icon: ArrowDownRight,
        items: [
            { id: "incoming_transfers", path: "/inventory/incoming-transfers", label: "Konfirmasi Masuk" },
            { id: "incoming_transfer_history", path: "/inventory/incoming-history", label: "Riwayat Masuk" },
        ]
    },

    // Admin Produk Reports (Moved to Online Shop section conceptually or renamed)
    // The user wants it for "toko_online" role.

    // Master Data
    { id: "warehouses", path: "/warehouses", label: "Cabang & Gudang", icon: Warehouse },
    { id: "online_shops", path: "/online-shops", label: "Toko Online", icon: Globe },
    { id: "brands", path: "/brands", label: "Data Merek", icon: Database },
    { id: "types", path: "/types", label: "Tipe Produk", icon: Tags },
    { id: "prices", path: "/prices", label: "Data Harga", icon: DollarSign },
    { id: "categories", path: "/categories", label: "Kategori", icon: Box },
    { id: "distributors", path: "/distributors", label: "Distributor", icon: Truck },

    // Lacak Barang
    { id: "track", path: "/track", label: "Lacak Barang", icon: Search },

    // Retur Masuk (Gudang)
    { id: "retur_items", path: "/retur-items", label: "Retur Masuk", icon: ArrowDownRight },

    { id: "settings", path: "/settings", label: "Pengaturan", icon: Settings },
];

// User info
const userName = computed(() => authStore.user?.name || "Guest");
const userRole = computed(() => getRoleLabel(authStore.userRole));
const userBranch = computed(() => authStore.user?.branch?.name || "-");

// Filter menu based on user role
const visibleMenuItems = computed(() => {
    const userRole = authStore.userRole;
    if (!userRole) return menuItems.filter((item) => item.id === "dashboard");

    if (userRole.toLowerCase().replace(/\s+/g, '_') === "super_admin") return menuItems;

    // Get allowed menus for role
    const allowedMenus = getMenuForRole(userRole);
    return menuItems.filter((item) => allowedMenus.includes(item.id));
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
</script>

<template>
    <aside
        class="fixed inset-y-0 left-0 z-100 w-64 bg-surface-800 border-r border-surface-700 flex flex-col transition-transform duration-300 lg:static lg:translate-x-0"
        :class="isMobileMenuOpen ? 'translate-x-0' : '-translate-x-full'">
        <!-- Logo -->
        <div class="p-6 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div
                    class="w-10 h-10 bg-primary-600 rounded-xl shadow-lg shadow-primary-500/20 flex items-center justify-center text-white font-black text-xl">
                    A
                </div>
                <span class="text-xl font-bold tracking-tight text-text-primary">
                    APEX<span class="text-primary-500">POS</span>
                </span>
            </div>
            <!-- Close Button (Mobile Only) -->
            <button @click="emit('close-mobile-menu')" class="lg:hidden text-text-secondary hover:text-text-primary">
                <X :size="24" />
            </button>
        </div>

        <!-- Branch Indicator -->
        <div class="mx-4 px-4 py-3 bg-surface-900 rounded-xl border border-surface-700 mb-4">
            <div class="flex items-center gap-2 text-xs text-text-secondary mb-1">
                <Building2 :size="12" />
                <span>CABANG AKTIF</span>
            </div>
            <p class="text-sm font-medium text-text-primary truncate">
                {{ userBranch }}
            </p>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 px-4 space-y-1 overflow-y-auto custom-scrollbar">
            <p class="px-4 text-[10px] font-bold text-text-secondary uppercase tracking-widest mb-2">
                Menu Utama
            </p>

            <div v-for="item in visibleMenuItems" :key="item.id">
                <!-- Dropdown Menu -->
                <div v-if="item.items" class="space-y-1">
                    <button @click.prevent="toggleMenu(item.id)" type="button"
                        class="w-full flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all duration-200 group border border-transparent hover:bg-surface-700 hover:text-text-primary"
                        :class="[
                            isGroupActive(item.items) ? 'text-primary-500' : 'text-text-secondary'
                        ]">
                        <component :is="item.icon" :size="18" />
                        <span>{{ item.label }}</span>
                        <ChevronDown :size="16" class="ml-auto transition-transform duration-200"
                            :class="{ 'rotate-180': expandedMenus[item.id] || isGroupActive(item.items) }" />
                    </button>

                    <!-- Submenu Items -->
                    <div v-show="expandedMenus[item.id] || isGroupActive(item.items)" class="pl-4 space-y-1">
                        <router-link v-for="subitem in item.items" :key="subitem.id" :to="subitem.path"
                            class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium transition-all duration-200 border border-transparent"
                            :class="isActiveRoute(subitem.path)
                                ? 'bg-primary-500/10 text-primary-600 border-primary-500/20'
                                : 'text-text-secondary hover:text-text-primary hover:bg-surface-700/50'
                                ">
                            <div class="w-1.5 h-1.5 rounded-full"
                                :class="isActiveRoute(subitem.path) ? 'bg-primary-500' : 'bg-surface-600'"></div>
                            <span>{{ subitem.label }}</span>
                        </router-link>
                    </div>
                </div>

                <!-- Regular Link -->
                <router-link v-else :to="item.path"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all duration-200 group border"
                    :class="isActiveRoute(item.path)
                        ? 'bg-primary-500/10 text-primary-600 dark:text-primary-400 border-primary-500/20'
                        : 'text-text-secondary border-transparent hover:bg-surface-700 hover:text-text-primary'
                        ">
                    <component :is="item.icon" :size="18" class="transition-colors" :class="isActiveRoute(item.path)
                        ? 'text-primary-600 dark:text-primary-400'
                        : 'text-text-secondary group-hover:text-primary-500'
                        " />
                    <span>{{ item.label }}</span>
                    <ChevronRight :size="14" class="ml-auto opacity-0 group-hover:opacity-100 transition-opacity" />
                </router-link>
            </div>
        </nav>

        <!-- User Section -->
        <div class="p-4 bg-surface-900 border-t border-surface-700">
            <div class="flex items-center gap-3 mb-4 px-2">
                <img :src="authStore.user?.photo
                    ? (authStore.user.photo.startsWith('http') ? authStore.user.photo : `${authStore.storageBaseUrl}/storage/${authStore.user.photo}`)
                    : `https://ui-avatars.com/api/?name=${encodeURIComponent(userName)}&background=${themeStore.isDark ? '3b82f6' : '0f172a'}&color=fff&size=128`"
                    class="w-10 h-10 rounded-xl border-2 border-surface-700 object-cover" :alt="userName"
                    @error="(e) => e.target.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(userName)}&background=${themeStore.isDark ? '3b82f6' : '0f172a'}&color=fff&size=128`" />
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-text-primary truncate">
                        {{ userName }}
                    </p>
                    <p class="text-[10px] text-primary-500 font-medium uppercase tracking-wide">
                        {{ userRole }}
                    </p>
                </div>
            </div>
            <button @click="handleLogout"
                class="flex items-center gap-3 w-full p-3 text-text-secondary hover:text-red-500 hover:bg-red-500/10 rounded-xl transition-all duration-300">
                <LogOut :size="18" />
                <span class="font-medium">Keluar</span>
            </button>
        </div>
    </aside>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: #1e293b;
    border-radius: 9999px;
}
</style>
