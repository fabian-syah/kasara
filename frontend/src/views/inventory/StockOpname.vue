<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import {
    ArrowLeft, RefreshCw, Search, Smartphone, Package, BarChart3, Box, Filter
} from 'lucide-vue-next';
import { inventory as inventoryApi } from '../../api/axios';
import { useToast } from '../../composables/useToast';

const router = useRouter();
const toast = useToast();

const loading = ref(false);
const rawItems = ref([]);
const searchQuery = ref('');
const viewMode = ref('all'); // 'all', 'hp', 'non-hp'

const fetchAllInventory = async () => {
    loading.value = true;
    try {
        // Fetch all pages to get complete inventory
        let page = 1;
        let allItems = [];
        let hasMore = true;

        while (hasMore) {
            const response = await inventoryApi.list({ page, per_page: 100 });
            const data = response.data;
            if (data.data) {
                allItems = allItems.concat(data.data);
                hasMore = data.current_page < data.last_page;
                page++;
            } else {
                hasMore = false;
            }
        }
        rawItems.value = allItems;
    } catch (error) {
        console.error(error);
        toast.error('Gagal memuat data inventory.');
    } finally {
        loading.value = false;
    }
};

// Aggregate items by product
const aggregatedProducts = computed(() => {
    const map = new Map();

    rawItems.value.forEach(item => {
        const productId = item.product_id || item.product?.id || 'unknown';
        const productName = item.product?.name || item.name || 'Unknown';
        const productSku = item.product?.sku || item.sku || '-';
        const isHp = !!item.imei;

        const key = `${productId}-${isHp ? 'hp' : 'non-hp'}`;

        if (!map.has(key)) {
            map.set(key, {
                productId,
                productName,
                productSku,
                brand: item.product?.brand || item.brand || '-',
                type: isHp ? 'HP' : 'Non-HP',
                isHp,
                totalQty: 0,
                availableQty: 0,
                soldQty: 0,
                items: []
            });
        }

        const entry = map.get(key);
        if (isHp) {
            entry.totalQty++;
            entry.items.push(item);
            if (item.status === 'available' || item.status === 'tersedia') {
                entry.availableQty++;
            } else {
                entry.soldQty++;
            }
        } else {
            entry.totalQty += (item.quantity || item.balance || 1);
            entry.availableQty += (item.quantity || item.balance || 1);
        }
    });

    return Array.from(map.values());
});

const filteredProducts = computed(() => {
    let results = aggregatedProducts.value;

    if (viewMode.value === 'hp') {
        results = results.filter(p => p.isHp);
    } else if (viewMode.value === 'non-hp') {
        results = results.filter(p => !p.isHp);
    }

    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        results = results.filter(p =>
            p.productName.toLowerCase().includes(q) ||
            p.productSku.toLowerCase().includes(q) ||
            p.brand.toLowerCase().includes(q)
        );
    }

    return results.sort((a, b) => b.availableQty - a.availableQty);
});

// Summary stats
const summaryStats = computed(() => {
    const hpItems = aggregatedProducts.value.filter(p => p.isHp);
    const nonHpItems = aggregatedProducts.value.filter(p => !p.isHp);

    return {
        totalHpAvailable: hpItems.reduce((sum, p) => sum + p.availableQty, 0),
        totalHpSold: hpItems.reduce((sum, p) => sum + p.soldQty, 0),
        totalNonHpAvailable: nonHpItems.reduce((sum, p) => sum + p.availableQty, 0),
        totalProducts: aggregatedProducts.value.length,
        totalAll: hpItems.reduce((sum, p) => sum + p.availableQty, 0) + nonHpItems.reduce((sum, p) => sum + p.availableQty, 0)
    };
});

onMounted(() => {
    fetchAllInventory();
});
</script>

<template>
    <div class="space-y-6 animate-in">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div class="flex items-center gap-3">
                <button @click="router.push({ name: 'Inventory' })"
                    class="p-2 hover:bg-surface-800 rounded-xl transition-colors">
                    <ArrowLeft :size="20" class="text-text-secondary" />
                </button>
                <div>
                    <h1 class="text-2xl font-bold text-text-primary tracking-tight">Stock Opname</h1>
                    <p class="text-text-secondary mt-1">Ringkasan stok tersedia per produk</p>
                </div>
            </div>

            <button @click="fetchAllInventory"
                class="p-2.5 text-text-secondary hover:text-primary-500 hover:bg-primary-500/10 rounded-xl transition-all">
                <RefreshCw :size="20" :class="{ 'animate-spin': loading }" />
            </button>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-surface-800 rounded-2xl border border-surface-700 p-5">
                <div class="flex items-center gap-3 mb-3">
                    <div class="p-2.5 bg-blue-500/10 rounded-xl">
                        <Smartphone :size="20" class="text-blue-400" />
                    </div>
                    <span class="text-sm text-text-secondary">HP Tersedia</span>
                </div>
                <div class="text-3xl font-bold text-blue-400">{{ summaryStats.totalHpAvailable }}</div>
                <div class="text-xs text-text-secondary mt-1">{{ summaryStats.totalHpSold }} unit terjual/keluar</div>
            </div>

            <div class="bg-surface-800 rounded-2xl border border-surface-700 p-5">
                <div class="flex items-center gap-3 mb-3">
                    <div class="p-2.5 bg-purple-500/10 rounded-xl">
                        <Package :size="20" class="text-purple-400" />
                    </div>
                    <span class="text-sm text-text-secondary">Non-HP Tersedia</span>
                </div>
                <div class="text-3xl font-bold text-purple-400">{{ summaryStats.totalNonHpAvailable }}</div>
                <div class="text-xs text-text-secondary mt-1">unit aksesoris & lainnya</div>
            </div>

            <div class="bg-surface-800 rounded-2xl border border-surface-700 p-5">
                <div class="flex items-center gap-3 mb-3">
                    <div class="p-2.5 bg-emerald-500/10 rounded-xl">
                        <BarChart3 :size="20" class="text-emerald-400" />
                    </div>
                    <span class="text-sm text-text-secondary">Total Tersedia</span>
                </div>
                <div class="text-3xl font-bold text-emerald-400">{{ summaryStats.totalAll }}</div>
                <div class="text-xs text-text-secondary mt-1">semua jenis produk</div>
            </div>

            <div class="bg-surface-800 rounded-2xl border border-surface-700 p-5">
                <div class="flex items-center gap-3 mb-3">
                    <div class="p-2.5 bg-amber-500/10 rounded-xl">
                        <Box :size="20" class="text-amber-400" />
                    </div>
                    <span class="text-sm text-text-secondary">Jumlah Produk</span>
                </div>
                <div class="text-3xl font-bold text-amber-400">{{ summaryStats.totalProducts }}</div>
                <div class="text-xs text-text-secondary mt-1">jenis produk tercatat</div>
            </div>
        </div>

        <!-- Controls -->
        <div class="bg-surface-800 rounded-2xl border border-surface-700 p-4">
            <div class="flex flex-col sm:flex-row gap-4 justify-between items-start sm:items-center">
                <!-- View Mode Tabs -->
                <div class="flex space-x-1 rounded-xl bg-surface-900 p-1 w-fit">
                    <button
                        v-for="tab in [{ key: 'all', label: 'Semua' }, { key: 'hp', label: 'HP' }, { key: 'non-hp', label: 'Non-HP' }]"
                        :key="tab.key" @click="viewMode = tab.key"
                        class="px-4 py-2 rounded-lg text-sm font-medium leading-5 transition-all duration-200" :class="viewMode === tab.key
                            ? 'bg-surface-700 text-white shadow'
                            : 'text-text-secondary hover:text-white'">
                        {{ tab.label }}
                    </button>
                </div>

                <!-- Search -->
                <div class="relative w-full sm:w-72">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary" :size="18" />
                    <input v-model="searchQuery" type="text" placeholder="Cari produk, SKU, brand..."
                        class="w-full bg-surface-900 border border-surface-700 rounded-xl py-2 pl-10 pr-4 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition-all placeholder:text-text-secondary" />
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-surface-800 rounded-2xl border border-surface-700 overflow-hidden">
            <div v-if="loading" class="p-12 flex justify-center items-center">
                <RefreshCw class="animate-spin text-primary-500" :size="32" />
                <span class="ml-3 text-text-secondary">Memuat data opname...</span>
            </div>

            <div v-else-if="filteredProducts.length === 0" class="p-12 text-center text-text-secondary">
                <Box :size="48" class="mx-auto mb-3 opacity-50" />
                <p>Tidak ada data produk</p>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm text-left text-text-primary">
                    <thead class="bg-surface-900/50 text-text-secondary uppercase text-xs font-semibold">
                        <tr>
                            <th class="px-6 py-4">#</th>
                            <th class="px-6 py-4">Produk</th>
                            <th class="px-6 py-4">Brand</th>
                            <th class="px-6 py-4">Jenis</th>
                            <th class="px-6 py-4 text-center">Tersedia</th>
                            <th class="px-6 py-4 text-center">Terjual/Keluar</th>
                            <th class="px-6 py-4 text-center">Total Masuk</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-700/50">
                        <tr v-for="(product, index) in filteredProducts" :key="index"
                            class="hover:bg-surface-700/30 transition-colors">
                            <td class="px-6 py-4 text-text-secondary text-xs">{{ index + 1 }}</td>
                            <td class="px-6 py-4">
                                <div>
                                    <div class="font-medium text-white">{{ product.productName }}</div>
                                    <div class="text-xs text-text-secondary font-mono">{{ product.productSku }}</div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-text-secondary">{{ product.brand }}</td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 rounded-lg text-xs font-medium border" :class="product.isHp
                                    ? 'text-blue-400 bg-blue-400/10 border-blue-400/20'
                                    : 'text-purple-400 bg-purple-400/10 border-purple-400/20'">
                                    {{ product.type }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="text-lg font-bold"
                                    :class="product.availableQty > 0 ? 'text-emerald-400' : 'text-red-400'">
                                    {{ product.availableQty }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="text-text-secondary">{{ product.soldQty }}</span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="text-text-secondary">{{ product.totalQty }}</span>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-surface-900/70 border-t border-surface-600">
                        <tr class="font-bold">
                            <td class="px-6 py-4 text-right text-text-secondary" colspan="4">
                                TOTAL
                            </td>
                            <td class="px-6 py-4 text-center text-emerald-400 text-lg">
                                {{filteredProducts.reduce((s, p) => s + p.availableQty, 0)}}
                            </td>
                            <td class="px-6 py-4 text-center text-text-secondary">
                                {{filteredProducts.reduce((s, p) => s + p.soldQty, 0)}}
                            </td>
                            <td class="px-6 py-4 text-center text-text-secondary">
                                {{filteredProducts.reduce((s, p) => s + p.totalQty, 0)}}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</template>

<style scoped>
.animate-in {
    animation: fadeIn 0.3s ease-out;
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
