<script setup>
import { ref, watch, onMounted } from 'vue';
import api from '../../api/axios';
import { PackageSearch, Boxes, AlertCircle, ShoppingBag } from 'lucide-vue-next';
import { useAuthStore } from '../../store/auth';

const authStore = useAuthStore();
const isLoading = ref(true);
const combinedStock = ref([]);
const stats = ref({
    totalItems: 0,
    totalQuantity: 0,
});

const pageTitle = "Ringkasan Stok";
const pageSubtitle = "Pantau ketersediaan stok produk secara keseluruhan pada lokasi otoritas Anda.";

const fetchSummary = async () => {
    isLoading.value = true;
    try {
        const response = await api.get('/inventory/stock-summary');
        combinedStock.value = response.data.items || [];
        stats.value = response.data.stats || { totalItems: 0, totalQuantity: 0 };
    } catch (e) {
        console.error("Gagal memuat ringkasan stok", e);
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => {
    fetchSummary();
});
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="p-3 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 rounded-xl">
                    <Boxes class="w-6 h-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 uppercase tracking-tight">{{ pageTitle }}</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ pageSubtitle }}</p>
                </div>
            </div>
            <button @click="fetchSummary" class="flex items-center gap-2 px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 transition shadow-sm text-sm font-medium">
                <PackageSearch class="w-4 h-4" /> Refresh Data
            </button>
        </div>

        <!-- Metric Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="bg-white dark:bg-slate-800 rounded-2xl p-6 border border-slate-200 dark:border-slate-700 shadow-sm relative overflow-hidden group">
                <div class="absolute inset-0 bg-gradient-to-r from-emerald-50 to-transparent dark:from-emerald-900/10 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                <div class="flex items-center justify-between relative">
                    <div>
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Total Varian / Tipe Spesifik</p>
                        <p class="text-3xl font-bold text-slate-800 dark:text-white mt-2">{{ stats.totalItems }}</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white dark:bg-slate-800 rounded-2xl p-6 border border-slate-200 dark:border-slate-700 shadow-sm relative overflow-hidden group">
                <div class="absolute inset-0 bg-gradient-to-r from-blue-50 to-transparent dark:from-blue-900/10 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                <div class="flex items-center justify-between relative">
                    <div>
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Total Keseluruhan Barang</p>
                        <p class="text-3xl font-bold text-slate-800 dark:text-white mt-2">{{ stats.totalQuantity }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="isLoading" class="bg-white dark:bg-slate-800 rounded-xl p-8 border border-slate-200 dark:border-slate-700 shadow-sm text-center">
            <div class="animate-spin w-8 h-8 md:w-10 md:h-10 border-4 border-indigo-100 border-t-indigo-600 rounded-full mx-auto"></div>
            <p class="mt-4 text-slate-500 dark:text-slate-400">Memuat data ringkasan stok...</p>
        </div>

        <!-- Data Grid -->
        <div v-else-if="combinedStock.length > 0" class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
             <div class="overflow-x-auto">
                 <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                     <thead class="bg-slate-50 dark:bg-slate-900/50 text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700 font-medium">
                         <tr>
                             <th class="px-6 py-4">Produk</th>
                             <th class="px-6 py-4">Kapasitas</th>
                             <th class="px-6 py-4">Kondisi</th>
                             <th class="px-6 py-4 text-center">Qty / Tersedia</th>
                         </tr>
                     </thead>
                     <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                         <tr v-for="(item, index) in combinedStock" :key="index" class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition duration-150">
                             <td class="px-6 py-4">
                                <div class="font-medium text-slate-800 dark:text-slate-200">{{ item.name }}</div>
                                <div class="text-xs text-slate-500 mt-0.5">{{ item.brand || 'No Brand' }}</div>
                             </td>
                             <td class="px-6 py-4">
                                <span v-if="item.storage" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-600">
                                    {{ item.storage }}
                                </span>
                                <span v-else class="text-slate-400">-</span>
                             </td>
                             <td class="px-6 py-4">
                                 <span v-if="item.condition" :class="[
                                    'inline-flex px-2.5 py-1 rounded-full text-xs font-medium border',
                                    item.condition.toLowerCase() === 'baru' ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-400 dark:border-emerald-800' : 
                                    item.condition.toLowerCase().includes('second') ? 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-900/30 dark:text-amber-400 dark:border-amber-800' :
                                    item.condition.toLowerCase().includes('ibox') ? 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-900/30 dark:text-blue-400 dark:border-blue-800' :
                                    'bg-slate-50 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700'
                                 ]">
                                    {{ item.condition }}
                                 </span>
                                 <span v-else class="text-slate-400">-</span>
                             </td>
                             <td class="px-6 py-4 text-center">
                                 <span class="inline-flex min-w-[2rem] items-center justify-center font-bold text-base px-2 py-1 rounded-lg bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400">
                                    {{ item.quantity }}
                                 </span>
                             </td>
                         </tr>
                     </tbody>
                 </table>
             </div>
        </div>

        <!-- Empty State -->
        <div v-else class="bg-white dark:bg-slate-800 rounded-xl p-12 py-20 border border-slate-200 dark:border-slate-700 shadow-sm text-center flex flex-col items-center justify-center">
            <div class="p-4 bg-slate-50 dark:bg-slate-800/50 rounded-full mb-4">
                <AlertCircle class="w-8 h-8 text-slate-400" />
            </div>
            <h3 class="text-lg font-medium text-slate-800 dark:text-slate-200 mb-1">Tidak ada stok</h3>
            <p class="text-slate-500 dark:text-slate-400 max-w-sm">Data ringkasan inventori Anda kosong saat ini pada lokasi yang bisa diakses.</p>
        </div>
    </div>
</template>
