<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import api, { users as usersApi } from '../../api/axios';
import { formatCurrency, formatNumber } from '../../utils/formatters';
import {
    BarChart3,
    Smartphone,
    Package,
    Users,
    ArrowUpDown,
    ArrowUp,
    Building2,
    Filter,
    Loader2,
    Camera,
    User
} from 'lucide-vue-next';
import { useAuthStore } from '../../store/auth';

const authStore = useAuthStore();
const storageBaseUrl = computed(() => authStore.storageBaseUrl);
const loading = ref(true);
const stats = ref({
    brands: [],
    products: [],
    cs: []
});

const filters = ref({
    start_date: '',
    end_date: '',
    branch_id: '',
    online_shop_id: ''
});

const usersList = ref([]);
const usersMap = computed(() => {
    const map = {};
    const list = Array.isArray(usersList.value) ? usersList.value : (usersList.value?.data || []);
    list.forEach(u => {
        if (u.name) map[u.name.toLowerCase()] = u;
        if (u.full_name) map[u.full_name.toLowerCase()] = u;
    });
    return map;
});

const fetchUsers = async () => {
    try {
        const response = await usersApi.list();
        // Handle both raw array and nested data object
        usersList.value = response.data?.data || response.data || [];
    } catch (error) {
        console.error('Failed to fetch users for photo mapping', error);
    }
};

const filterOptions = ref({
    branches: [],
    online_shops: []
});

const formatDateStr = (date) => {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
};

const setRange = (type) => {
    const today = new Date();

    if (type === 'today') {
        filters.value.start_date = formatDateStr(today);
        filters.value.end_date = formatDateStr(today);
    } else if (type === 'yesterday') {
        const yesterday = new Date(today);
        yesterday.setDate(today.getDate() - 1);
        filters.value.start_date = formatDateStr(yesterday);
        filters.value.end_date = formatDateStr(yesterday);
    } else if (type === 'month') {
        const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
        filters.value.start_date = formatDateStr(startOfMonth);
        filters.value.end_date = formatDateStr(today);
    } else if (type === 'all') {
        filters.value.start_date = '';
        filters.value.end_date = '';
    }
    fetchReport();
};

const activeRange = computed(() => {
    const today = new Date();
    const todayStr = formatDateStr(today);
    
    const yesterday = new Date(today);
    yesterday.setDate(today.getDate() - 1);
    const yesterdayStr = formatDateStr(yesterday);

    const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
    const startOfMonthStr = formatDateStr(startOfMonth);

    if (!filters.value.start_date && !filters.value.end_date) return 'all';
    
    if (filters.value.start_date === filters.value.end_date) {
        if (filters.value.start_date === todayStr) return 'today';
        if (filters.value.start_date === yesterdayStr) return 'yesterday';
    }
    
    if (filters.value.start_date === startOfMonthStr && filters.value.end_date === todayStr) return 'month';
    
    return 'custom';
});

const fetchFilters = async () => {
    try {
        const response = await api.get('/reports/filters');
        filterOptions.value = response.data;
    } catch (error) {
        console.error('Error fetching filters:', error);
    }
};

const activeTab = ref('brand'); // brand, product, cs

const fetchReport = async () => {
    loading.value = true;
    try {
        const response = await api.get('/reports/sales', { params: filters.value });
        stats.value = response.data;
    } catch (error) {
        console.error('Error fetching sales report:', error);
    } finally {
        loading.value = false;
    }
};

onMounted(async () => {
    const today = new Date();
    filters.value.start_date = formatDateStr(today);
    filters.value.end_date = formatDateStr(today);
    
    // Auto-set filters for specific roles
    const isOnlineShopRole = authStore.hasRole(['online_shop', 'toko_online']) || authStore.user?.online_shop_id;
    if (authStore.user?.online_shop_id) {
        filters.value.online_shop_id = authStore.user.online_shop_id;
    }
    
    if (isOnlineShopRole) {
        // Explicitly set branch_id to empty to avoid backend branch-locking for shop roles
        filters.value.branch_id = '';
    } else if (authStore.user?.branch_id) {
        // Only auto-set branch_id if NOT an online shop
        filters.value.branch_id = authStore.user.branch_id;
    }

    await Promise.all([
        fetchFilters(),
        fetchUsers()
    ]);

    // Fallback: If online shop role but no ID set explicitly, use the first available from options
    if (isOnlineShopRole && !filters.value.online_shop_id && filterOptions.value.online_shops?.length > 0) {
        filters.value.online_shop_id = filterOptions.value.online_shops[0].id;
    }

    fetchReport();
});

const resolvePhoto = (user, name) => {
    // 1. Try to find user in our detailed usersMap first (more likely to have photo)
    const mappedUser = name ? usersMap.value[name.toLowerCase()] : null;
    
    // 2. Check multiple potential photo fields for robustness (from mapped user or provided user)
    const source = mappedUser || user;
    const photo = source?.photo || source?.photo_inventory || source?.avatar || source?.profile_photo || source?.image;
    
    if (!photo) return `https://ui-avatars.com/api/?name=${encodeURIComponent(name || 'User')}&background=10b981&color=fff&size=128`;
    if (photo.startsWith('http')) return photo;
    return `${storageBaseUrl.value}/storage/${photo}`;
};

// Sorting logic for CS
const csSort = ref({
    key: 'omset',
    order: 'desc'
});

const sortedCS = computed(() => {
    return [...stats.value.cs].sort((a, b) => {
        let valA = a[csSort.value.key];
        let valB = b[csSort.value.key];

        if (csSort.value.order === 'asc') {
            return valA > valB ? 1 : -1;
        } else {
            return valA < valB ? 1 : -1;
        }
    });
});

const toggleSort = (key) => {
    if (csSort.value.key === key) {
        csSort.value.order = csSort.value.order === 'asc' ? 'desc' : 'asc';
    } else {
        csSort.value.key = key;
        csSort.value.order = 'desc';
    }
};

const productSearch = ref('');
const filteredProducts = computed(() => {
    if (!productSearch.value) return stats.value.products;
    const search = productSearch.value.toLowerCase();
    return stats.value.products.filter(p =>
        p.name.toLowerCase().includes(search) ||
        p.brand.toLowerCase().includes(search)
    );
});

</script>

<template>
    <div class="p-6 space-y-6">
        <!-- Header -->
        <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-6">
            <div class="shrink-0">
                <h1 class="text-2xl font-bold text-text-primary tracking-tight">Laporan Penjualan</h1>
                <p class="text-text-secondary text-sm">
                    {{
                        filters.start_date && filters.end_date
                            ? `Periode ${filters.start_date} s/d ${filters.end_date}`
                            : 'Rekapitulasi penjualan barang laku (Semua Waktu)'
                    }}
                </p>
            </div>

            <div class="flex flex-col lg:flex-row lg:items-center gap-4 w-full xl:w-auto">
                <!-- Group 1: Quick Filters -->
                <div class="flex flex-wrap bg-surface-800 p-1 rounded-xl border border-surface-700/50 w-full sm:w-auto">
                    <button @click="setRange('today')" :disabled="loading"
                        class="px-3 py-1.5 rounded-lg text-[10px] font-black transition-all flex items-center justify-center gap-2 whitespace-nowrap flex-grow"
                        :class="activeRange === 'today' ? 'bg-primary-500 text-white shadow-lg' : 'text-text-secondary hover:text-text-primary'">
                        HARI INI
                    </button>
                    <button @click="setRange('yesterday')" :disabled="loading"
                        class="px-3 py-1.5 rounded-lg text-[10px] font-black transition-all flex items-center justify-center gap-2 whitespace-nowrap flex-grow"
                        :class="activeRange === 'yesterday' ? 'bg-primary-500 text-white shadow-lg' : 'text-text-secondary hover:text-text-primary'">
                        KEMARIN
                    </button>
                    <button @click="setRange('month')" :disabled="loading"
                        class="px-3 py-1.5 rounded-lg text-[10px] font-black transition-all flex items-center justify-center gap-2 whitespace-nowrap flex-grow"
                        :class="activeRange === 'month' ? 'bg-primary-500 text-white shadow-lg' : 'text-text-secondary hover:text-text-primary'">
                        BULAN INI
                    </button>
                    <button @click="setRange('all')" :disabled="loading"
                        class="px-3 py-1.5 rounded-lg text-[10px] font-black transition-all flex items-center justify-center gap-2 whitespace-nowrap flex-grow"
                        :class="activeRange === 'all' ? 'bg-primary-500 text-white shadow-lg' : 'text-text-secondary hover:text-text-primary'">
                        SEMUA
                    </button>
                </div>

                <!-- Group 2: Selectors & Filters -->
                <div class="flex flex-wrap lg:flex-nowrap items-center gap-2 bg-transparent w-full lg:w-auto">
                    <!-- Date Inputs -->
                    <div class="flex items-center gap-1 bg-surface-800 p-1 rounded-xl border border-surface-700 w-full sm:w-auto">
                        <input type="date" v-model="filters.start_date"
                            class="bg-transparent text-[11px] text-text-primary outline-none px-2 w-full sm:w-28" />
                        <span class="text-surface-600 font-bold">-</span>
                        <input type="date" v-model="filters.end_date"
                            class="bg-transparent text-[11px] text-text-primary outline-none px-2 w-full sm:w-28" />
                    </div>

                    <!-- Selectors Group -->
                    <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                        <!-- Branch Selector -->
                        <div v-if="filterOptions.branches.length > 0"
                            class="flex-1 sm:flex-none flex items-center gap-2 bg-surface-800 p-2 rounded-xl border border-surface-700">
                            <Building2 class="w-4 h-4 text-text-secondary shrink-0" />
                            <select v-model="filters.branch_id" @change="fetchReport"
                                class="bg-transparent text-[11px] text-text-primary outline-none w-full appearance-none cursor-pointer pr-4 uppercase">
                                <option value="">Semua Cabang</option>
                                <option v-for="branch in filterOptions.branches" :key="branch.id" :value="branch.id">
                                    {{ branch.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Online Shop Selector -->
                        <div v-if="filterOptions.online_shops.length > 0"
                            class="flex-1 sm:flex-none flex items-center gap-2 bg-surface-800 p-2 rounded-xl border border-surface-700">
                            <Smartphone class="w-4 h-4 text-text-secondary shrink-0" />
                            <select v-model="filters.online_shop_id" @change="fetchReport"
                                class="bg-transparent text-[11px] text-text-primary outline-none w-full appearance-none cursor-pointer pr-4 uppercase">
                                <option value="">Semua Online</option>
                                <option v-for="shop in filterOptions.online_shops" :key="shop.id" :value="shop.id">
                                    {{ shop.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Apply Button -->
                        <button @click="fetchReport" class="flex-1 sm:flex-none px-4 py-2 bg-primary-500 hover:bg-primary-600 text-white rounded-xl transition-all flex items-center justify-center gap-2 font-black text-[10px] uppercase whitespace-nowrap">
                            <Filter :size="14" />
                            Filter
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex border-b border-surface-700 gap-6">
            <button v-for="tab in [
                { id: 'brand', label: 'Laporan per Brand', icon: BarChart3 },
                { id: 'product', label: 'Total Produk Terjual', icon: Package },
                { id: 'cs', label: 'Performa CS', icon: Users }
            ]" :key="tab.id" @click="activeTab = tab.id"
                class="flex items-center gap-2 py-3 px-1 border-b-2 transition-all font-medium text-sm"
                :class="activeTab === tab.id ? 'border-primary-500 text-primary-500' : 'border-transparent text-text-secondary hover:text-text-primary'">
                <component :is="tab.icon" :size="16" />
                {{ tab.label }}
            </button>
        </div>

        <!-- Loading State -->
        <div v-if="loading"
            class="flex flex-col items-center justify-center py-20 bg-surface-800 rounded-2xl border border-surface-700">
            <div class="w-10 h-10 border-4 border-primary-500/20 border-t-primary-500 rounded-full animate-spin mb-4">
            </div>
            <p class="text-text-secondary text-sm">Memuat data laporan...</p>
        </div>

        <div v-else class="space-y-6 animate-in">
            <!-- Brand Stats -->
            <div v-if="activeTab === 'brand'" class="overflow-x-auto">
                <table class="w-full text-left border-separate border-spacing-0">
                    <thead>
                        <tr class="bg-surface-800">
                            <th
                                class="px-6 py-4 text-xs font-bold text-text-secondary uppercase tracking-wider rounded-tl-xl border-b border-surface-700">
                                Nama Brand</th>
                            <th
                                class="px-6 py-4 text-xs font-bold text-text-secondary uppercase tracking-wider border-b border-surface-700">
                                HP New</th>
                            <th
                                class="px-6 py-4 text-xs font-bold text-text-secondary uppercase tracking-wider border-b border-surface-700">
                                HP Second</th>
                            <th
                                class="px-6 py-4 text-xs font-bold text-text-secondary uppercase tracking-wider border-b border-surface-700">
                                HP Ex iBox</th>
                            <th
                                class="px-6 py-4 text-xs font-bold text-text-secondary uppercase tracking-wider border-b border-surface-700">
                                Non-HP (Aksesoris)</th>
                            <th
                                class="px-6 py-4 text-xs font-bold text-text-secondary uppercase tracking-wider rounded-tr-xl border-b border-surface-700">
                                Total Terjual</th>
                        </tr>
                    </thead>
                    <tbody class="bg-surface-900/50">
                        <tr v-for="b in stats.brands" :key="b.brand"
                            class="hover:bg-surface-800/50 transition-colors group">
                            <td
                                class="px-6 py-4 border-b border-surface-800 font-bold text-text-primary group-hover:text-primary-400 transition-colors">
                                {{ b.brand }}</td>
                            <td class="px-6 py-4 border-b border-surface-800 text-emerald-400 font-medium">{{
                                formatNumber(b.hp_new) }}</td>
                            <td class="px-6 py-4 border-b border-surface-800 text-amber-400 font-medium">{{
                                formatNumber(b.hp_second) }}</td>
                            <td class="px-6 py-4 border-b border-surface-800 text-purple-400 font-medium">{{
                                formatNumber(b.hp_ex_ibox) }}</td>
                            <td class="px-6 py-4 border-b border-surface-800 text-blue-400 font-medium">{{
                                formatNumber(b.non_hp) }}</td>
                            <td class="px-6 py-4 border-b border-surface-800 font-black text-text-primary">
                                {{ formatNumber(b.hp_new + b.hp_second + b.hp_ex_ibox + b.non_hp) }}
                            </td>
                        </tr>
                        <tr v-if="stats.brands.length === 0">
                            <td colspan="6" class="px-6 py-10 text-center text-text-secondary italic">Tidak ada data
                                penjualan</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Product Stats -->
            <div v-if="activeTab === 'product'" class="space-y-4">
                <div class="flex items-center gap-3 bg-surface-800 p-3 rounded-xl border border-surface-700">
                    <Search :size="18" class="text-text-secondary" />
                    <input v-model="productSearch" type="text" placeholder="Cari nama produk atau brand..."
                        class="bg-transparent border-none outline-none text-text-primary text-sm flex-1" />
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-separate border-spacing-0">
                        <thead>
                            <tr class="bg-surface-800">
                                <th
                                    class="px-6 py-4 text-xs font-bold text-text-secondary uppercase tracking-wider rounded-tl-xl border-b border-surface-700">
                                    Produk</th>
                                <th
                                    class="px-6 py-4 text-xs font-bold text-text-secondary uppercase tracking-wider border-b border-surface-700">
                                    Brand</th>
                                <th
                                    class="px-6 py-4 text-xs font-bold text-text-secondary uppercase tracking-wider border-b border-surface-700">
                                    Specs / Kapasitas</th>
                                <th
                                    class="px-6 py-4 text-xs font-bold text-text-secondary uppercase tracking-wider border-b border-surface-700 text-center">
                                    Kondisi</th>
                                <th
                                    class="px-6 py-4 text-xs font-bold text-text-secondary uppercase tracking-wider text-right rounded-tr-xl border-b border-surface-700">
                                    Total Unit</th>
                            </tr>
                        </thead>
                        <tbody class="bg-surface-900/50">
                            <tr v-for="(p, idx) in filteredProducts" :key="idx"
                                class="hover:bg-surface-800/50 transition-colors">
                                <td class="px-6 py-4 border-b border-surface-800">
                                    <div class="flex items-center gap-2">
                                        <component :is="p.is_hp ? Smartphone : Package" :size="14"
                                            :class="p.is_hp ? 'text-blue-400' : 'text-amber-400'" />
                                        <span class="font-bold text-text-primary">{{ p.name }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 border-b border-surface-800 text-text-secondary">{{ p.brand }}</td>
                                <td class="px-6 py-4 border-b border-surface-800 text-center">
                                    <span
                                        class="px-2 py-0.5 bg-surface-700 text-[10px] rounded border border-surface-600 text-text-secondary">{{
                                            p.specs }}</span>
                                </td>
                                <td class="px-6 py-4 border-b border-surface-800 text-center">
                                    <span v-if="p.condition === 'new'"
                                        class="px-2 py-0.5 bg-emerald-500/10 text-emerald-400 text-[10px] rounded border border-emerald-500/20 uppercase font-bold">New</span>
                                    <span v-else-if="p.condition === 'ex_ibox'"
                                        class="px-2 py-0.5 bg-purple-500/10 text-purple-400 text-[10px] rounded border border-purple-500/20 uppercase font-bold">Ex
                                        iBox</span>
                                    <span v-else
                                        class="px-2 py-0.5 bg-amber-500/10 text-amber-400 text-[10px] rounded border border-amber-500/20 uppercase font-bold">Second</span>
                                </td>
                                <td
                                    class="px-6 py-4 border-b border-surface-800 text-right font-black text-primary-400">
                                    {{ formatNumber(p.total) }}
                                </td>
                            </tr>
                            <tr v-if="filteredProducts.length === 0">
                                <td colspan="5" class="px-6 py-10 text-center text-text-secondary italic">Tidak ada
                                    produk ditemukan</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- CS Stats -->
            <div v-if="activeTab === 'cs'" class="overflow-x-auto">
                <table class="w-full text-left border-separate border-spacing-0">
                    <thead>
                        <tr class="bg-surface-800">
                            <th
                                class="px-6 py-4 text-xs font-bold text-text-secondary uppercase tracking-wider rounded-tl-xl border-b border-surface-700">
                                Petugas Stok</th>
                            <th @click="toggleSort('hp_count')"
                                class="px-6 py-4 text-xs font-bold text-text-secondary uppercase tracking-wider border-b border-surface-700 cursor-pointer hover:bg-surface-700 transition-colors group">
                                <div class="flex items-center gap-2">
                                    HP Terjual
                                    <component
                                        :is="csSort.key === 'hp_count' ? (csSort.order === 'asc' ? ArrowUp : ArrowDown) : ArrowUpDown"
                                        :size="12"
                                        :class="csSort.key === 'hp_count' ? 'text-primary-500' : 'opacity-30 group-hover:opacity-100'" />
                                </div>
                            </th>
                            <th @click="toggleSort('acc_count')"
                                class="px-6 py-4 text-xs font-bold text-text-secondary uppercase tracking-wider border-b border-surface-700 cursor-pointer hover:bg-surface-700 transition-colors group">
                                <div class="flex items-center gap-2">
                                    Acc Terjual
                                    <component
                                        :is="csSort.key === 'acc_count' ? (csSort.order === 'asc' ? ArrowUp : ArrowDown) : ArrowUpDown"
                                        :size="12"
                                        :class="csSort.key === 'acc_count' ? 'text-primary-500' : 'opacity-30 group-hover:opacity-100'" />
                                </div>
                            </th>
                            <th @click="toggleSort('omset')"
                                class="px-6 py-4 text-xs font-bold text-text-secondary uppercase tracking-wider rounded-tr-xl border-b border-surface-700 cursor-pointer hover:bg-surface-700 transition-colors group">
                                <div class="flex items-center gap-2">
                                    Total Omset
                                    <component
                                        :is="csSort.key === 'omset' ? (csSort.order === 'asc' ? ArrowUp : ArrowDown) : ArrowUpDown"
                                        :size="12"
                                        :class="csSort.key === 'omset' ? 'text-primary-500' : 'opacity-30 group-hover:opacity-100'" />
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-surface-900/50">
                        <tr v-for="c in sortedCS" :key="c.name" class="hover:bg-surface-800/50 transition-colors">
                            <td class="px-6 py-4 border-b border-surface-800">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-primary-500/10 flex items-center justify-center border border-primary-500/20 overflow-hidden shrink-0">
                                        <img :src="resolvePhoto(c, c.name)"
                                            class="w-full h-full object-cover"
                                            @error="(e) => e.target.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(c.name || 'User')}&background=10b981&color=fff`" />
                                    </div>
                                    <span class="font-bold text-text-primary">{{ c.name }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 border-b border-surface-800">
                                <span class="font-bold text-blue-400">{{ formatNumber(c.hp_count) }}</span> HP
                            </td>
                            <td class="px-6 py-4 border-b border-surface-800">
                                <span class="font-bold text-amber-400">{{ formatNumber(c.acc_count) }}</span> Item
                            </td>
                            <td class="px-6 py-4 border-b border-surface-800 font-black text-emerald-400 text-lg">
                                {{ formatCurrency(c.omset) }}
                            </td>
                        </tr>
                        <tr v-if="stats.cs.length === 0">
                            <td colspan="4" class="px-6 py-10 text-center text-text-secondary italic">Tidak ada data CS
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<style scoped>
.animate-in {
    animation: fadeIn 0.4s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
