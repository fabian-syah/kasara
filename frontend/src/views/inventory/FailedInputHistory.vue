<script setup>
import { ref, onMounted, watch, computed } from 'vue';
import { useRouter } from 'vue-router';
import {
    Search, ArrowLeft, RefreshCw, AlertTriangle, Box, Calendar, User, XCircle, Copy
} from 'lucide-vue-next';
import { inventory } from '../../api/axios';
import { useToast } from '../../composables/useToast';
import { formatDate, getLogicalDate, getTodayLocal } from '../../utils/formatters';
import { useAuthStore } from '../../store/auth';

const authStore = useAuthStore();
const router = useRouter();
const toast = useToast();

const loading = ref(false);
const items = ref([]);  
const activeTab = ref('hp');
const searchQuery = ref('');
const pagination = ref({
    current_page: 1,
    last_page: 1,
    total: 0
});

// Date Filter
const filterMode = ref('month');
const isRestricted = computed(() => {
    const role = (authStore.userRole || '').toLowerCase();
    const privilegedRoles = ['super_admin', 'audit', 'owner', 'leader', 'analist', 'admin_produk'];
    return !privilegedRoles.some(r => role.includes(r));
});

const getMinDate = computed(() => {
    if (!isRestricted.value) return null;
    const d = getLogicalDate();
    d.setDate(d.getDate() - 7);
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
});

const currentDate = getLogicalDate();
const currentMonth = currentDate.getMonth() + 1;
const currentYear = currentDate.getFullYear();
const prevDate = new Date(currentDate);
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
const selectedDate = ref(getTodayLocal());

const filterPresets = [
    { label: 'Hari Ini', value: 'today' },
    { label: 'Kemarin', value: 'yesterday' },
    { label: 'Pilih Tanggal', value: 'date' },
    { label: 'Per Bulan', value: 'month' },
];

const getDateParam = () => {
    if (filterMode.value === 'today') {
        const d = getLogicalDate();
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    } else if (filterMode.value === 'yesterday') {
        const d = getLogicalDate();
        d.setDate(d.getDate() - 1);
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    } else if (filterMode.value === 'date') {
        return selectedDate.value;
    }
    return null;
};

const fetchData = async (page = 1) => {
    loading.value = true;
    try {
        const params = {
            page,
            type: activeTab.value,
            search: searchQuery.value,
        };

        const dateParam = getDateParam();
        if (dateParam) {
            params.date = dateParam;
        } else {
            params.month = selectedMonth.value.month;
            params.year = selectedMonth.value.year;
        }

        const response = await inventory.failedInputs(params);
        items.value = response.data.data;
        pagination.value = {
            current_page: response.data.current_page,
            last_page: response.data.last_page,
            total: response.data.total
        };
    } catch (error) {
        console.error(error);
        toast.error('Gagal memuat riwayat gagal input.');
    } finally {
        loading.value = false;
    }
};

const getErrorTypeBadge = (type) => {
    switch (type) {
        case 'duplicate':
            return { label: 'Duplikat', class: 'bg-amber-500/20 text-amber-400 border-amber-500/30' };
        case 'validation':
            return { label: 'Validasi', class: 'bg-blue-500/20 text-blue-400 border-blue-500/30' };
        case 'exception':
        default:
            return { label: 'Error', class: 'bg-red-500/20 text-red-400 border-red-500/30' };
    }
};

watch([activeTab, searchQuery, filterMode, selectedMonth, selectedDate], () => {
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
                    <h1 class="text-2xl font-bold text-text-primary tracking-tight">Riwayat Gagal Input</h1>
                    <p class="text-text-secondary mt-1">Daftar percobaan input stok yang gagal</p>
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
        <div class="bg-surface-800 rounded-2xl border border-surface-700 p-4 space-y-4">
            <div class="flex flex-col sm:flex-row gap-4 justify-between">
                <!-- Tab Switcher -->
                <div class="flex space-x-1 rounded-xl bg-surface-900 p-1 w-fit">
                    <button v-for="tab in ['hp', 'non-hp']" :key="tab" @click="activeTab = tab"
                        class="px-4 py-2 rounded-lg text-sm font-medium leading-5 transition-all duration-200"
                        :class="activeTab === tab
                            ? 'bg-surface-700 text-white shadow'
                            : 'text-text-secondary hover:text-white'">
                        {{ tab === 'hp' ? 'Unit / HP' : 'NON HP / NON IMEI' }}
                    </button>
                </div>

                <!-- Search -->
                <div class="relative w-full sm:w-72">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary" :size="18" />
                    <input v-model="searchQuery" type="text" placeholder="Cari produk, IMEI, error..."
                        class="w-full bg-surface-900 border border-surface-700 rounded-xl py-2 pl-10 pr-4 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition-all placeholder:text-text-secondary" />
                </div>
            </div>

            <!-- Date Filter -->
            <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center">
                <div class="flex flex-wrap gap-2">
                    <button v-for="preset in filterPresets" :key="preset.value" @click="filterMode = preset.value"
                        class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all border"
                        :class="filterMode === preset.value
                            ? 'bg-primary-500/20 text-primary-400 border-primary-500/30'
                            : 'bg-surface-900 text-text-secondary border-surface-700 hover:text-white'">
                        {{ preset.label }}
                    </button>
                </div>

                <input v-if="filterMode === 'date'" v-model="selectedDate" type="date" :min="getMinDate"
                    :max="getTodayLocal()"
                    class="bg-surface-900 border border-surface-700 rounded-xl px-3 py-1.5 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/50" />

                <select v-if="filterMode === 'month'" v-model="selectedMonth"
                    class="bg-surface-900 border border-surface-700 rounded-xl px-3 py-1.5 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/50">
                    <option v-for="(option, index) in monthOptions" :key="index" :value="option.value">
                        {{ option.label }}
                    </option>
                </select>

                <span class="text-xs text-text-secondary ml-2">
                    Total: {{ pagination.total }} item
                </span>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-surface-800 rounded-2xl border border-surface-700 overflow-hidden">
            <div v-if="loading" class="p-12 flex justify-center items-center">
                <RefreshCw class="animate-spin text-primary-500" :size="32" />
                <span class="ml-3 text-text-secondary">Memuat data...</span>
            </div>

            <div v-else-if="items.length === 0" class="p-12 text-center text-text-secondary">
                <AlertTriangle :size="48" class="mx-auto mb-3 opacity-50" />
                <p>Belum ada riwayat gagal input</p>
                <p class="text-xs mt-1">Data akan muncul ketika ada percobaan input stok yang gagal</p>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm text-left text-text-primary">
                    <thead class="bg-surface-900/50 text-text-secondary uppercase text-xs font-semibold">
                        <tr>
                            <th class="px-6 py-4">Tanggal</th>
                            <th class="px-6 py-4">Produk</th>
                            <th class="px-6 py-4" v-if="activeTab === 'hp'">IMEI</th>
                            <th class="px-6 py-4" v-else>Qty</th>
                            <th class="px-6 py-4">Tipe Error</th>
                            <th class="px-6 py-4">Pesan Error</th>
                            <th class="px-6 py-4 hidden md:table-cell">Lokasi</th>
                            <th class="px-6 py-4 hidden lg:table-cell">Diinput Oleh</th>
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
                                    <div class="font-medium text-white">{{ item.product_name || (item.product ? item.product.name : '-') }}</div>
                                    <div class="text-xs text-text-secondary" v-if="item.distributor_name">
                                        {{ item.distributor_name }}
                                    </div>
                                </div>
                            </td>
                            <!-- HP: IMEI -->
                            <td class="px-6 py-4" v-if="activeTab === 'hp'">
                                <div v-if="item.imei"
                                    class="font-mono text-xs bg-surface-900 px-2 py-1 rounded inline-block">
                                    {{ item.imei }}
                                </div>
                                <span v-else class="text-text-secondary/30">-</span>
                            </td>
                            <!-- Non-HP: Qty -->
                            <td class="px-6 py-4" v-else>
                                <span v-if="item.quantity" class="text-lg font-bold text-red-400">{{ item.quantity }}</span>
                                <span v-else class="text-text-secondary/30">-</span>
                            </td>

                            <td class="px-6 py-4">
                                <span class="px-2 py-1 rounded-lg text-xs font-medium border"
                                    :class="getErrorTypeBadge(item.error_type).class">
                                    {{ getErrorTypeBadge(item.error_type).label }}
                                </span>
                            </td>

                            <td class="px-6 py-4">
                                <span class="text-xs text-red-400 max-w-[250px] block truncate" :title="item.error_message">
                                    {{ item.error_message }}
                                </span>
                            </td>

                            <td class="px-6 py-4 hidden md:table-cell">
                                <span v-if="item.placement_name" class="text-xs text-text-secondary">
                                    {{ item.placement_name }}
                                </span>
                                <span v-else class="text-text-secondary/30">-</span>
                            </td>

                            <td class="px-6 py-4 hidden lg:table-cell">
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
