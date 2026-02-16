<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import { formatCurrency, formatNumber } from '../../utils/formatters';
import {
    BarChart3,
    Smartphone,
    Package,
    Users,
    ArrowUpDown,
    ArrowUp,
    ArrowDown,
    Filter,
    Search,
    Download
} from 'lucide-vue-next';

const loading = ref(true);
const stats = ref({
    brands: [],
    products: [],
    cs: []
});

const filters = ref({
    start_date: '',
    end_date: ''
});

const activeTab = ref('brand'); // brand, product, cs

const fetchReport = async () => {
    loading.value = true;
    try {
        const response = await axios.get('/reports/sales', { params: filters.value });
        stats.value = response.data;
    } catch (error) {
        console.error('Error fetching sales report:', error);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchReport();
});

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
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-text-primary tracking-tight">Laporan Penjualan</h1>
                <p class="text-text-secondary text-sm">Rekapitulasi penjualan barang laku (All Time)</p>
            </div>

            <div class="flex items-center gap-3">
                <div class="flex items-center gap-2 bg-surface-800 p-1 rounded-lg border border-surface-700">
                    <input type="date" v-model="filters.start_date"
                        class="bg-transparent text-xs text-text-primary outline-none px-2" />
                    <span class="text-text-secondary">to</span>
                    <input type="date" v-model="filters.end_date"
                        class="bg-transparent text-xs text-text-primary outline-none px-2" />
                </div>
                <button @click="fetchReport" class="btn btn-primary btn-sm">
                    <Filter :size="14" />
                    Filter
                </button>
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
                            <td class="px-6 py-4 border-b border-surface-800 text-blue-400 font-medium">{{
                                formatNumber(b.non_hp) }}</td>
                            <td class="px-6 py-4 border-b border-surface-800 font-black text-text-primary">
                                {{ formatNumber(b.hp_new + b.hp_second + b.non_hp) }}
                            </td>
                        </tr>
                        <tr v-if="stats.brands.length === 0">
                            <td colspan="5" class="px-6 py-10 text-center text-text-secondary italic">Tidak ada data
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
                                Customer Service</th>
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
                                    <div
                                        class="w-8 h-8 rounded-full bg-surface-700 flex items-center justify-center text-xs font-bold text-primary-400 border border-surface-600">
                                        {{ c.name.charAt(0).toUpperCase() }}
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
