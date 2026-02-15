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
    <aside class="fixed inset-y-0 left-0 z-50 w-72 flex flex-col transition-transform duration-300 lg:translate-x-0"
        :class="isMobileMenuOpen ? 'translate-x-0' : '-translate-x-full'">

        <!-- Glass Background with Gradient Blob -->
        <div class="absolute inset-0 bg-surface-900/90 backdrop-blur-xl border-r border-white/5 overflow-hidden">
            <div
                class="absolute top-0 left-0 w-full h-96 bg-primary-500/10 blur-[100px] rounded-full -translate-y-1/2 -translate-x-1/2 pointer-events-none">
            </div>
            <div
                class="absolute bottom-0 right-0 w-full h-96 bg-purple-500/10 blur-[100px] rounded-full translate-y-1/2 translate-x-1/2 pointer-events-none">
            </div>
        </div>

        <div class="relative flex flex-col h-full z-10">
            <!-- Logo Section -->
            <div class="p-8 pb-4 flex items-center justify-between">
                <div class="flex items-center gap-4 group cursor-pointer">
                    <div class="relative w-12 h-12 flex items-center justify-center">
                        <div
                            class="absolute inset-0 bg-gradient-to-tr from-primary-500 to-purple-600 rounded-2xl rotate-6 opacity-50 group-hover:rotate-12 transition-transform duration-500 blur-sm">
                        </div>
                        <div
                            class="relative w-full h-full bg-surface-800 rounded-2xl border border-white/10 flex items-center justify-center shadow-2xl group-hover:scale-105 transition-transform duration-300 overflow-hidden">
                            <div
                                class="absolute inset-0 bg-gradient-to-br from-white/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                            </div>
                            <span
                                class="text-2xl font-black bg-gradient-to-br from-white to-white/50 bg-clip-text text-transparent">A</span>
                        </div>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-2xl font-bold tracking-tight text-white leading-none">
                            APEX<span
                                class="text-transparent bg-clip-text bg-gradient-to-r from-primary-400 to-purple-400">POS</span>
                        </span>
                        <span
                            class="text-[10px] font-medium text-surface-400 tracking-[0.2em] uppercase mt-1">Management</span>
                    </div>
                </div>
                <!-- Close Button (Mobile Only) -->
                <button @click="emit('close-mobile-menu')"
                    class="lg:hidden p-2 text-surface-400 hover:text-white transition-colors">
                    <X :size="20" />
                </button>
            </div>

            <!-- Branch Indicator Card -->
            <div class="px-6 mb-6">
                <div
                    class="relative p-0.5 rounded-2xl bg-gradient-to-r from-white/10 to-transparent overflow-hidden group">
                    <div
                        class="relative bg-surface-800/50 backdrop-blur-sm p-4 rounded-2xl border border-white/5 hover:border-white/10 transition-colors">
                        <div class="flex items-center gap-3 mb-2">
                            <div class="p-2 rounded-lg bg-emerald-500/10 text-emerald-400">
                                <Building2 :size="16" />
                            </div>
                            <span class="text-xs font-semibold text-surface-400 uppercase tracking-wider">Cabang
                                Aktif</span>
                        </div>
                        <p class="text-sm font-medium text-white truncate pl-1">
                            {{ userBranch }}
                        </p>
                        <!-- Glow effect -->
                        <div
                            class="absolute -right-4 -top-4 w-12 h-12 bg-emerald-500/20 blur-xl rounded-full group-hover:bg-emerald-500/30 transition-all duration-500">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 px-4 space-y-1 overflow-y-auto custom-scrollbar pb-6">
                <p
                    class="px-4 py-2 text-[10px] font-bold text-surface-500 uppercase tracking-widest mb-2 flex items-center gap-2">
                    <span class="w-8 h-[1px] bg-surface-700"></span>
                    Menu Utama
                </p>

                <div v-for="item in visibleMenuItems" :key="item.id">
                    <!-- Dropdown Menu -->
                    <div v-if="item.items" class="space-y-1 mb-1">
                        <button @click.prevent="toggleMenu(item.id)" type="button"
                            class="w-full flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all duration-300 group border border-transparent relative overflow-hidden"
                            :class="[
                                isGroupActive(item.items)
                                    ? 'text-white'
                                    : 'text-surface-400 hover:text-white hover:bg-white/5'
                            ]">
                            <!-- Active Background -->
                            <div v-if="isGroupActive(item.items)"
                                class="absolute inset-0 bg-gradient-to-r from-primary-500/20 to-transparent border-l-2 border-primary-500">
                            </div>

                            <component :is="item.icon" :size="20"
                                class="relative z-10 transition-transform duration-300 group-hover:scale-110"
                                :class="isGroupActive(item.items) ? 'text-primary-400' : 'text-surface-500 group-hover:text-white'" />
                            <span class="relative z-10">{{ item.label }}</span>
                            <div
                                class="relative z-10 ml-auto flex items-center justify-center w-5 h-5 rounded-full bg-surface-800/50 group-hover:bg-surface-800 transition-colors">
                                <ChevronDown :size="12" class="transition-transform duration-300"
                                    :class="{ 'rotate-180': expandedMenus[item.id] || isGroupActive(item.items) }" />
                            </div>
                        </button>

                        <!-- Submenu Items -->
                        <div v-show="expandedMenus[item.id] || isGroupActive(item.items)"
                            class="pl-4 space-y-1 border-l border-white/5 ml-6 overflow-hidden transition-all duration-300">
                            <router-link v-for="subitem in item.items" :key="subitem.id" :to="subitem.path"
                                class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm transition-all duration-200 relative group/sub"
                                :class="isActiveRoute(subitem.path)
                                    ? 'text-white font-medium'
                                    : 'text-surface-400 hover:text-white'
                                    ">
                                <div class="absolute left-0 w-1 h-1 rounded-full transition-all duration-300"
                                    :class="isActiveRoute(subitem.path) ? 'bg-primary-500 w-1.5 h-1.5 shadow-[0_0_10px_rgba(59,130,246,0.5)]' : 'bg-surface-700 group-hover/sub:bg-surface-500'">
                                </div>
                                <span
                                    class="pl-2 relative z-10 transition-transform duration-300 group-hover/sub:translate-x-1">{{
                                    subitem.label }}</span>
                            </router-link>
                        </div>
                    </div>

                    <!-- Regular Link -->
                    <router-link v-else :to="item.path"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all duration-300 group border border-transparent relative overflow-hidden mb-1"
                        :class="isActiveRoute(item.path)
                            ? 'text-white'
                            : 'text-surface-400 hover:text-white hover:bg-white/5'
                            ">
                        <!-- Active Gradient Background -->
                        <div v-if="isActiveRoute(item.path)"
                            class="absolute inset-0 bg-gradient-to-r from-primary-500/20 to-purple-500/5 border-l-2 border-primary-500">
                        </div>

                        <component :is="item.icon" :size="20"
                            class="relative z-10 transition-transform duration-300 group-hover:scale-110" :class="isActiveRoute(item.path)
                                ? 'text-primary-400 drop-shadow-[0_0_8px_rgba(96,165,250,0.5)]'
                                : 'text-surface-500 group-hover:text-white'
                                " />
                        <span class="relative z-10">{{ item.label }}</span>

                        <!-- Hover Arrow -->
                        <ChevronRight :size="14"
                            class="relative z-10 ml-auto opacity-0 -translate-x-2 group-hover:opacity-100 group-hover:translate-x-0 transition-all duration-300 text-surface-400" />
                    </router-link>
                </div>
            </nav>

            <!-- User Section -->
            <div class="p-4 border-t border-white/5 bg-black/20 backdrop-blur-sm">
                <div
                    class="p-3 rounded-2xl bg-surface-800/50 border border-white/5 hover:border-white/10 transition-all duration-300 group cursor-pointer">
                    <div class="flex items-center gap-3">
                        <div class="relative">
                            <img :src="authStore.user?.photo
                                ? (authStore.user.photo.startsWith('http') ? authStore.user.photo : `${authStore.storageBaseUrl}/storage/${authStore.user.photo}`)
                                : `https://ui-avatars.com/api/?name=${encodeURIComponent(userName)}&background=${themeStore.isDark ? '3b82f6' : '0f172a'}&color=fff&size=128`"
                                class="w-10 h-10 rounded-xl object-cover ring-2 ring-white/5 group-hover:ring-primary-500/50 transition-all duration-300"
                                :alt="userName"
                                @error="(e) => e.target.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(userName)}&background=${themeStore.isDark ? '3b82f6' : '0f172a'}&color=fff&size=128`" />
                            <div
                                class="absolute -bottom-1 -right-1 w-3.5 h-3.5 bg-emerald-500 rounded-full border-2 border-surface-900">
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p
                                class="text-sm font-bold text-white truncate group-hover:text-primary-400 transition-colors">
                                {{ userName }}
                            </p>
                            <p class="text-[10px] text-surface-400 font-medium uppercase tracking-wide truncate">
                                {{ userRole }}
                            </p>
                        </div>
                        <button @click="handleLogout"
                            class="p-2 rounded-lg hover:bg-red-500/20 text-surface-400 hover:text-red-400 transition-colors"
                            title="Keluar">
                            <LogOut :size="16" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </aside>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: rgba(255, 255, 255, 0.1);
    border-radius: 9999px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background-color: rgba(255, 255, 255, 0.2);
}
</style>
