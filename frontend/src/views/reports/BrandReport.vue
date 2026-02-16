<script setup>
import { ref, onMounted, computed } from 'vue';
import api from '../../api/axios';
import { formatNumber } from '../../utils/formatters';
import { Download } from 'lucide-vue-next';

const reports = ref([]);
const isLoading = ref(false);
const searchQuery = ref('');
const filterType = ref('hp');

const fetchReports = async () => {
    isLoading.value = true;
    try {
        const response = await api.get('/reports/brand', {
            params: { type: filterType.value }
        });
        reports.value = response.data;
    } catch (error) {
        console.error('Failed to fetch brand report:', error);
    } finally {
        isLoading.value = false;
    }
};

const filteredReports = computed(() => {
    if (!searchQuery.value) return reports.value;
    const query = searchQuery.value.toLowerCase();
    return reports.value.filter(report =>
        report.name.toLowerCase().includes(query)
    );
});

const totalStock = computed(() => {
    return filteredReports.value.reduce((acc, curr) => acc + curr.total, 0);
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
                <h1 class="text-2xl font-bold text-text-primary tracking-tight">Laporan Brand</h1>
                <p class="text-text-secondary mt-1">Stok berdasarkan Merek dan Kondisi</p>
            </div>

            <!-- Search & Actions -->
            <div class="flex items-center gap-3">
                <select v-model="filterType" @change="fetchReports"
                    class="bg-surface-800 text-text-primary border border-surface-600 rounded-lg px-3 py-2 focus:outline-none focus:border-primary-500">
                    <option value="all">Semua Tipe</option>
                    <option value="hp">HP (IMEI)</option>
                    <option value="non-hp">Non-HP (Aksesoris)</option>
                </select>
                <input v-model="searchQuery" type="text" placeholder="Cari brand..." class="input max-w-xs" />
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="card bg-surface-800 border-surface-700">
                <p class="text-text-secondary text-sm">Total Brand</p>
                <p class="text-2xl font-bold text-text-primary mt-1">{{ filteredReports.length }}</p>
            </div>
            <div class="card bg-surface-800 border-surface-700">
                <p class="text-text-secondary text-sm">Total Unit (Semua)</p>
                <p class="text-2xl font-bold text-emerald-400 mt-1">{{ formatNumber(totalStock) }}</p>
            </div>
        </div>

        <!-- Table -->
        <div class="card overflow-hidden p-0">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-text-secondary uppercase bg-surface-700/50">
                        <tr>
                            <th class="px-6 py-4 font-medium">Brand</th>
                            <th class="px-6 py-4 font-medium text-center">Baru (New)</th>
                            <th class="px-6 py-4 font-medium text-center">Bekas (Second)</th>
                            <th class="px-6 py-4 font-medium text-right">Total Unit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-700">
                        <tr v-if="isLoading">
                            <td colspan="4" class="px-6 py-8 text-center text-text-secondary">
                                <div class="flex justify-center items-center gap-2">
                                    <div
                                        class="w-4 h-4 border-2 border-primary-500 border-t-transparent rounded-full animate-spin">
                                    </div>
                                    Memuat data...
                                </div>
                            </td>
                        </tr>
                        <tr v-else-if="filteredReports.length === 0">
                            <td colspan="4" class="px-6 py-8 text-center text-text-secondary">
                                Tidak ada data ditemukan
                            </td>
                        </tr>
                        <tr v-else v-for="report in filteredReports" :key="report.id"
                            class="hover:bg-surface-700/30 transition-colors">
                            <td class="px-6 py-4 font-medium text-text-primary">{{ report.name }}</td>
                            <td class="px-6 py-4 text-center">
                                <span class="bg-emerald-500/10 text-emerald-400 px-2 py-1 rounded text-xs font-bold">
                                    {{ formatNumber(report.new) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="bg-amber-500/10 text-amber-400 px-2 py-1 rounded text-xs font-bold">
                                    {{ formatNumber(report.second) }}
                                </span>
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
