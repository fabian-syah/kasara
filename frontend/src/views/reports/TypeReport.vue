<script setup>
import { ref, onMounted, computed } from 'vue';
import api from '../../api/axios';
import { formatNumber } from '../../utils/formatters';

const reports = ref([]);
const isLoading = ref(false);
const searchQuery = ref('');
const filterType = ref('hp'); // Default to hp

const fetchReports = async () => {
    isLoading.value = true;
    try {
        const response = await api.get('/reports/type', {
            params: { type: filterType.value }
        });
        reports.value = response.data;
    } catch (error) {
        console.error('Failed to fetch type report:', error);
    } finally {
        isLoading.value = false;
    }
};

const filteredReports = computed(() => {
    if (!searchQuery.value) return reports.value;
    const query = searchQuery.value.toLowerCase();
    return reports.value.filter(report =>
        report.name.toLowerCase().includes(query) ||
        report.brand_name.toLowerCase().includes(query)
    );
});

onMounted(() => {
    fetchReports();
});
</script>

<template>
    <div class="space-y-6 animate-in">
        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between md:items-end gap-4">
            <div>
                <h1 class="text-2xl font-bold text-text-primary tracking-tight">Laporan Tipe</h1>
                <p class="text-text-secondary mt-1">Stok rinci per Tipe Produk</p>
            </div>

            <!-- Search -->
            <div class="flex items-center gap-3">
                <select v-model="filterType" @change="fetchReports"
                    class="bg-surface-800 text-text-primary border border-surface-600 rounded-lg px-3 py-2 focus:outline-none focus:border-primary-500">
                    <option value="all">Semua Tipe</option>
                    <option value="hp">HP (IMEI)</option>
                    <option value="non-hp">Non-HP (Aksesoris)</option>
                </select>
                <input v-model="searchQuery" type="text" placeholder="Cari tipe atau brand..."
                    class="input w-full md:w-80" />
            </div>
        </div>

        <!-- Table -->
        <div class="card overflow-hidden p-0">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-text-secondary uppercase bg-surface-700/50">
                        <tr>
                            <th class="px-6 py-4 font-medium">Brand</th>
                            <th class="px-6 py-4 font-medium">Tipe Produk</th>
                            <th class="px-6 py-4 font-medium text-center">Stok Baru</th>
                            <th class="px-6 py-4 font-medium text-center">Stok Bekas</th>
                            <th class="px-6 py-4 font-medium text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-700">
                        <tr v-if="isLoading">
                            <td colspan="5" class="px-6 py-8 text-center text-text-secondary">
                                <div class="flex justify-center items-center gap-2">
                                    <div
                                        class="w-4 h-4 border-2 border-primary-500 border-t-transparent rounded-full animate-spin">
                                    </div>
                                    Memuat data...
                                </div>
                            </td>
                        </tr>
                        <tr v-else-if="filteredReports.length === 0">
                            <td colspan="5" class="px-6 py-8 text-center text-text-secondary">
                                Tidak ada data ditemukan
                            </td>
                        </tr>
                        <tr v-else v-for="report in filteredReports" :key="report.id"
                            class="hover:bg-surface-700/30 transition-colors">
                            <td class="px-6 py-4 text-text-secondary font-medium">{{ report.brand_name }}</td>
                            <td class="px-6 py-4 text-text-primary">{{ report.name }}</td>
                            <td class="px-6 py-4 text-center">
                                <span v-if="report.new > 0"
                                    class="bg-emerald-500/10 text-emerald-400 px-2 py-1 rounded text-xs font-bold">
                                    {{ formatNumber(report.new) }}
                                </span>
                                <span v-else class="text-text-disabled">-</span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span v-if="report.second > 0"
                                    class="bg-amber-500/10 text-amber-400 px-2 py-1 rounded text-xs font-bold">
                                    {{ formatNumber(report.second) }}
                                </span>
                                <span v-else class="text-text-disabled">-</span>
                            </td>
                            <td class="px-6 py-4 text-right font-bold text-primary-400">
                                {{ formatNumber(report.total) }}
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
