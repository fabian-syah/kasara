<script setup>
import { ref, onMounted, watch, computed } from 'vue';
import { useRouter } from 'vue-router';
import {
    Search, ArrowLeft, RefreshCw, Smartphone, Box, Calendar, User, FileText, Database
} from 'lucide-vue-next';
import { inventory } from '../../api/axios';
import { useToast } from '../../composables/useToast';
import { formatDate } from '../../utils/formatters';

const router = useRouter();
const toast = useToast();

const loading = ref(false);
const items = ref([]); // Data list
const activeTab = ref('hp'); // 'hp' or 'non-hp'
const searchQuery = ref('');
const pagination = ref({
    current_page: 1,
    last_page: 1,
    total: 0
});

// Month Filter
const currentDate = new Date();
const currentMonth = currentDate.getMonth() + 1;
const currentYear = currentDate.getFullYear();
const prevDate = new Date();
prevDate.setMonth(prevDate.getMonth() - 1);
const prevMonth = prevDate.getMonth() + 1;
const prevYear = prevDate.getFullYear();

const monthOptions = [
    {
        label: currentDate.toLocaleString('id-ID', { month: 'long', year: 'numeric' }),
        value: { month: currentMonth, year: currentYear }
    },
    {
        label: prevDate.toLocaleString('id-ID', { month: 'long', year: 'numeric' }),
        value: { month: prevMonth, year: prevYear }
    }
];
const selectedMonth = ref(monthOptions[0].value);

const fetchData = async (page = 1) => {
    loading.value = true;
    try {
        const params = {
            page,
            type: activeTab.value,
            page,
            type: activeTab.value,
            search: searchQuery.value,
            month: selectedMonth.value.month,
            year: selectedMonth.value.year
        };

        const response = await inventory.historyIn(params);
        items.value = response.data.data;
        pagination.value = {
            current_page: response.data.current_page,
            last_page: response.data.last_page,
            total: response.data.total
        };
    } catch (error) {
        console.error(error);
        toast.error('Gagal memuat history stok masuk.');
    } finally {
        loading.value = false;
    }
};

watch([activeTab, searchQuery, selectedMonth], () => {
    fetchData(1);
});

onMounted(() => {
    fetchData();
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
                    <h1 class="text-2xl font-bold text-text-primary tracking-tight">Daftar Stok Masuk</h1>
                    <p class="text-text-secondary mt-1">Riwayat barang masuk ke inventory</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button @click="fetchData(pagination.current_page)"
                    class="p-2.5 text-text-secondary hover:text-primary-500 hover:bg-primary-500/10 rounded-xl transition-all">
                    <RefreshCw :size="20" :class="{ 'animate-spin': loading }" />
                </button>
            </div>
        </div>

        <!-- Controls -->
        <div class="bg-surface-800 rounded-2xl border border-surface-700 p-4">
            <div class="flex flex-col sm:flex-row gap-4 justify-between">
                <!-- Tab Switcher -->
                <div class="flex space-x-1 rounded-xl bg-surface-900 p-1 w-fit">
                    <button v-for="tab in ['hp', 'non-hp']" :key="tab" @click="activeTab = tab"
                        class="px-4 py-2 rounded-lg text-sm font-medium leading-5 transition-all duration-200" :class="activeTab === tab
                            ? 'bg-surface-700 text-white shadow'
                            : 'text-text-secondary hover:text-white'">
                        {{ tab === 'hp' ? 'Unit / HP' : 'NON HP / NON IMEI' }}
                    </button>
                </div>

                <div class="flex items-center gap-2">
                    <select v-model="selectedMonth"
                        class="bg-surface-900 border border-surface-700 rounded-xl px-3 py-2 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/50">
                        <option v-for="(option, index) in monthOptions" :key="index" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                </div>

                <!-- Search -->
                <div class="relative w-full sm:w-72">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary" :size="18" />
                    <input v-model="searchQuery" type="text" placeholder="Cari SKU, Produk, atau..."
                        class="w-full bg-surface-900 border border-surface-700 rounded-xl py-2 pl-10 pr-4 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition-all placeholder:text-text-secondary" />
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-surface-800 rounded-2xl border border-surface-700 overflow-hidden">
            <div v-if="loading" class="p-12 flex justify-center items-center">
                <RefreshCw class="animate-spin text-primary-500" :size="32" />
                <span class="ml-3 text-text-secondary">Memuat data...</span>
            </div>

            <div v-else-if="items.length === 0" class="p-12 text-center text-text-secondary">
                <Box :size="48" class="mx-auto mb-3 opacity-50" />
                <p>Belum ada riwayat stok masuk</p>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm text-left text-text-primary">
                    <thead class="bg-surface-900/50 text-text-secondary uppercase text-xs font-semibold">
                        <tr>
                            <th class="px-6 py-4">Tanggal</th>
                            <th class="px-6 py-4">Produk</th>
                            <th class="px-6 py-4" v-if="activeTab === 'hp'">IMEI / Detail</th>
                            <th class="px-6 py-4" v-else>Quantity / Info</th>
                            <th class="px-6 py-4">Sumber / Distributor</th>
                            <th class="px-6 py-4">Diinput Oleh</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-700/50">
                        <tr v-for="item in items" :key="item.id" class="hover:bg-surface-700/30 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap text-text-secondary">
                                <div class="flex items-center gap-2">
                                    <Calendar :size="14" />
                                    {{ formatDate(item.created_at) }}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div>
                                    <div class="font-medium text-white">{{ item.product ? item.product.name : 'Unknown'
                                        }}</div>
                                    <div class="text-xs text-text-secondary">{{ item.product ? item.product.sku : '-' }}
                                    </div>
                                </div>
                            </td>
                            <!-- HP Specific -->
                            <td class="px-6 py-4" v-if="activeTab === 'hp'">
                                <div class="font-mono text-xs bg-surface-900 px-2 py-1 rounded inline-block mb-1">
                                    {{ item.imei }}
                                </div>
                                <div class="text-xs text-text-secondary flex gap-2">
                                    <span v-if="item.ram">{{ item.ram }}</span>
                                    <span v-if="item.storage">{{ item.storage }}</span>
                                    <span class="capitalize"
                                        :class="item.condition === 'new' ? 'text-green-400' : 'text-amber-400'">
                                        {{ item.condition }}
                                    </span>
                                </div>
                            </td>
                            <!-- Non-HP Specific -->
                            <td class="px-6 py-4" v-else>
                                <div class="flex items-center gap-2">
                                    <span class="text-lg font-bold text-green-400">+{{ item.quantity }}</span>
                                    <span class="text-xs text-text-secondary">Unit</span>
                                </div>
                                <div class="text-xs text-text-secondary mt-1 max-w-xs truncate">
                                    {{ item.description || '-' }}
                                </div>
                            </td>

                            <td class="px-6 py-4">
                                <div v-if="activeTab === 'hp'">
                                    {{ item.distributor ? item.distributor.name : (item.supplier_name || '-') }}
                                    <div class="text-xs text-text-secondary">
                                        {{ item.placement_name || '-' }}
                                    </div>
                                </div>
                                <div v-else>
                                    {{ item.distributor ? item.distributor.name : (item.supplier_name || '-') }}
                                    <div class="text-xs text-text-secondary"
                                        v-if="!item.distributor && item.description">
                                        {{ item.description }}
                                    </div>
                                </div>
                            </td>

                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <User :size="14" class="text-text-secondary" />
                                    <span>{{ item.user ? item.user.name : '-' }}</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="pagination.last_page > 1" class="border-t border-surface-700/50 p-4 flex justify-center gap-2">
                <button @click="fetchData(pagination.current_page - 1)" :disabled="pagination.current_page === 1"
                    class="px-4 py-2 rounded-lg bg-surface-700 hover:bg-surface-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors text-sm">
                    Previous
                </button>
                <span class="px-4 py-2 text-sm text-text-secondary">
                    Page {{ pagination.current_page }} of {{ pagination.last_page }}
                </span>
                <button @click="fetchData(pagination.current_page + 1)"
                    :disabled="pagination.current_page === pagination.last_page"
                    class="px-4 py-2 rounded-lg bg-surface-700 hover:bg-surface-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors text-sm">
                    Next
                </button>
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
