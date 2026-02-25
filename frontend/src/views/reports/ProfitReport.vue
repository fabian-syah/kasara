<template>
    <div class="space-y-8">
        <!-- Section: Audit Profit -->
        <div
            class="bg-surface-50 dark:bg-surface-900/50 p-6 rounded-2xl border border-surface-200 dark:border-surface-700">
            <!-- Header & Filters -->
            <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 mb-6">
                <div>
                    <h2 class="text-xl font-bold text-text-primary">Audit Profit</h2>
                    <p class="text-sm text-gray-500 mt-1">Analisis profit per transaksi penjualan</p>
                </div>

                <div class="flex flex-wrap items-center gap-3 w-full lg:w-auto">
                    <!-- Period Filter -->
                    <div class="relative min-w-[140px]">
                        <select v-model="selectedPeriod" @change="handlePeriodChange"
                            class="w-full appearance-none bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl px-4 py-2.5 pr-10 text-sm font-medium focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all cursor-pointer">
                            <option value="daily">Harian</option>
                            <option value="monthly">Bulanan</option>
                        </select>
                        <ChevronDown :size="16"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
                    </div>

                    <!-- Daily: Date Picker -->
                    <div v-if="selectedPeriod === 'daily'" class="relative group">
                        <div
                            class="flex items-center gap-2 px-4 py-2.5 bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl hover:border-primary-500 hover:ring-2 hover:ring-primary-500/10 transition-all cursor-pointer">
                            <Calendar :size="18"
                                class="text-gray-500 dark:text-gray-400 group-hover:text-primary-500" />
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-200 min-w-[100px]">
                                {{ formattedDateDisplay }}
                            </span>
                        </div>
                        <input type="date" v-model="filters.start_date" @change="handleDateChange"
                            @click="$event.target.showPicker()"
                            class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-20" />
                    </div>

                    <!-- Monthly: Month & Year Selectors -->
                    <div v-if="selectedPeriod === 'monthly'" class="flex items-center gap-2">
                        <div class="relative min-w-[140px]">
                            <select v-model="selectedMonth" @change="handleMonthChange"
                                class="w-full appearance-none bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl px-4 py-2.5 pr-10 text-sm font-medium focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all cursor-pointer">
                                <option v-for="(m, i) in months" :key="i" :value="i + 1">{{ m }}</option>
                            </select>
                            <ChevronDown :size="16"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
                        </div>
                        <div class="relative min-w-[100px]">
                            <select v-model="selectedYear" @change="handleMonthChange"
                                class="w-full appearance-none bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl px-4 py-2.5 pr-10 text-sm font-medium focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all cursor-pointer">
                                <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                            </select>
                            <ChevronDown :size="16"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
                        </div>
                    </div>

                    <!-- Branch Filter -->
                    <div v-if="canFilterBranch" class="relative min-w-[200px]">
                        <select v-model="selectedLocationKey" @change="fetchData"
                            class="w-full appearance-none bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl px-4 py-2.5 pr-10 text-sm font-medium focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all cursor-pointer">
                            <option value="all">Semua Cabang/Toko</option>
                            <option v-for="loc in locations" :key="`${loc.type}:${loc.id}`"
                                :value="`${loc.type === 'branch' ? 'B' : 'S'}:${loc.id}`">
                                {{ loc.name }}
                            </option>
                        </select>
                        <ChevronDown :size="16"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
                    </div>

                    <!-- Export Button -->
                    <button @click="exportExcel" :disabled="exporting"
                        class="flex items-center gap-2 px-5 py-2.5 bg-gray-900 dark:bg-white text-white dark:text-gray-900 rounded-xl text-sm font-bold shadow-lg shadow-gray-200 dark:shadow-none hover:transform hover:-translate-y-0.5 transition-all disabled:opacity-50">
                        <Download :size="18" :class="{ 'animate-bounce': exporting }" />
                        <span>{{ exporting ? 'Exporting...' : 'Export' }}</span>
                    </button>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6" v-if="profitRecords.daily_sales.length > 0">
                <div
                    class="bg-white dark:!bg-surface-800 rounded-xl border border-gray-100 dark:border-surface-700 p-4">
                    <p class="text-xs font-semibold text-text-secondary uppercase mb-1">Total Harga Jual</p>
                    <p class="text-lg font-bold text-text-primary">{{ formatCurrency(totalHargaJual) }}</p>
                </div>
                <div
                    class="bg-white dark:!bg-surface-800 rounded-xl border border-gray-100 dark:border-surface-700 p-4">
                    <p class="text-xs font-semibold text-text-secondary uppercase mb-1">Total Harga Modal</p>
                    <p class="text-lg font-bold text-text-primary">{{ formatCurrency(totalHargaModal) }}</p>
                </div>
                <div
                    class="bg-white dark:!bg-surface-800 rounded-xl border border-gray-100 dark:border-surface-700 p-4">
                    <p class="text-xs font-semibold text-text-secondary uppercase mb-1">Total Profit</p>
                    <p class="text-lg font-bold"
                        :class="totalProfit >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                        {{ formatCurrency(totalProfit) }}
                    </p>
                </div>
            </div>

            <!-- Table -->
            <div
                class="bg-white dark:!bg-surface-800 rounded-2xl shadow-sm border border-gray-100 dark:border-surface-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead
                            class="text-xs font-semibold text-text-secondary uppercase bg-gray-50/50 dark:!bg-surface-700/50 border-b border-gray-100 dark:border-surface-700">
                            <tr>
                                <th class="px-4 py-4">No</th>
                                <th class="px-4 py-4">Waktu</th>
                                <th class="px-4 py-4">No Pesanan</th>
                                <th class="px-4 py-4">Nama</th>
                                <th class="px-4 py-4">Kategori</th>
                                <th colspan="4"
                                    class="p-0 border-b border-gray-200 dark:border-surface-700 bg-gray-50/50 dark:!bg-surface-700/50">
                                    <div class="grid grid-cols-[80px_100px_1fr_100px_150px_100px] w-full min-w-[700px]">
                                        <div class="px-4 py-4 text-left font-semibold text-text-secondary uppercase">
                                            Tipe</div>
                                        <div class="px-4 py-4 text-left font-semibold text-text-secondary uppercase">
                                            Brand</div>
                                        <div class="px-4 py-4 text-left font-semibold text-text-secondary uppercase">
                                            Rincian Barang</div>
                                        <div class="px-4 py-4 text-right font-semibold text-text-secondary uppercase">
                                            Harga Jual</div>
                                        <div class="px-4 py-4 text-left font-semibold text-text-secondary uppercase">
                                            Harga Modal</div>
                                        <div class="px-4 py-4 text-right font-semibold text-text-secondary uppercase">
                                            Profit</div>
                                    </div>
                                </th>
                                <th class="px-4 py-4 text-center">#</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-surface-700">
                            <tr v-if="loading">
                                <td colspan="12" class="px-6 py-12">
                                    <div class="flex flex-col items-center justify-center text-text-secondary">
                                        <Loader2 class="w-8 h-8 animate-spin text-primary-500 mb-2" />
                                        <span class="text-sm font-medium">Memuat data profit...</span>
                                    </div>
                                </td>
                            </tr>
                            <tr v-else-if="profitRecords.daily_sales.length === 0">
                                <td colspan="12" class="px-6 py-12 text-center text-text-secondary">
                                    <div class="flex flex-col items-center justify-center">
                                        <div
                                            class="w-12 h-12 bg-gray-100 dark:!bg-surface-700 rounded-full flex items-center justify-center mb-3">
                                            <TrendingUp class="w-6 h-6 text-gray-400" />
                                        </div>
                                        <span class="font-medium text-text-primary">Tidak ada data
                                            profit</span>
                                        <span class="text-xs mt-1">Belum ada transaksi pada periode ini</span>
                                    </div>
                                </td>
                            </tr>
                            <tr v-else v-for="(item, index) in profitRecords.daily_sales" :key="index"
                                class="hover:bg-gray-50 dark:hover:bg-surface-700/30 transition-colors group text-text-primary">
                                <td class="px-4 py-4 text-text-secondary font-medium">{{ index + 1 }}</td>
                                <td class="px-4 py-4 font-medium text-text-primary text-xs whitespace-nowrap">
                                    {{ formatDate(item.date) }}</td>
                                <td class="px-4 py-4 text-text-primary font-medium text-xs">{{ item.order_no
                                    }}</td>
                                <td class="px-4 py-4 font-medium text-xs">{{ item.customer_name }}
                                </td>
                                <td class="px-4 py-4">
                                    <span
                                        class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400 border border-blue-100 dark:border-blue-500/20">
                                        {{ item.category }}
                                    </span>
                                </td>
                                <td colspan="4" class="p-0 align-top">
                                    <div class="flex flex-col w-full h-full min-w-[700px]">
                                        <template v-if="item.items && item.items.length > 0">
                                            <div v-for="(detail, idx) in item.items" :key="idx"
                                                class="grid grid-cols-[80px_100px_1fr_100px_150px_100px] border-b border-gray-100 dark:!border-surface-700 last:border-0 hover:bg-black/5 dark:hover:bg-white/5 transition-colors">
                                                <div
                                                    class="px-4 py-4 font-medium text-xs text-text-primary border-r border-gray-100 dark:!border-surface-700 flex items-start break-words">
                                                    {{ detail.type || item.type }}</div>
                                                <div
                                                    class="px-4 py-4 text-xs font-semibold text-text-secondary border-r border-gray-100 dark:!border-surface-700 flex items-start break-words whitespace-pre-wrap">
                                                    {{ detail.brand || item.brand_names }}</div>
                                                <div
                                                    class="px-4 py-4 text-xs font-medium text-text-secondary flex flex-col justify-center border-r border-gray-100 dark:!border-surface-700">
                                                    <div class="flex justify-between items-start gap-3 w-full">
                                                        <div class="whitespace-normal flex-1 leading-relaxed">{{
                                                            detail.name }}</div>
                                                        <div
                                                            class="bg-gray-100 dark:!bg-surface-700 px-2 py-0.5 rounded text-xs font-bold text-text-primary whitespace-nowrap mt-0.5">
                                                            {{ detail.qty }}</div>
                                                    </div>
                                                </div>
                                                <!-- Harga Jual -->
                                                <div
                                                    class="px-4 py-4 text-text-primary font-mono text-xs font-semibold whitespace-nowrap text-right flex items-center justify-end border-r border-gray-100 dark:!border-surface-700">
                                                    {{ formatCurrency(detail.harga_jual || 0) }}
                                                </div>
                                                <!-- Harga Modal -->
                                                <div
                                                    class="px-4 py-4 flex items-center justify-end border-r border-gray-100 dark:!border-surface-700">
                                                    <div class="font-mono text-xs font-semibold text-right whitespace-nowrap"
                                                        :class="detail.has_saved_modal ? 'text-emerald-600 dark:text-emerald-400' : 'text-text-primary'">
                                                        {{ formatCurrency(detail.harga_modal ??
                                                            detail.default_harga_modal) }}
                                                    </div>
                                                </div>
                                                <!-- Profit -->
                                                <div class="px-4 py-4 font-mono text-xs font-bold whitespace-nowrap text-right flex items-center justify-end"
                                                    :class="detail.profit >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                                                    {{ formatCurrency(detail.profit) }}
                                                </div>
                                            </div>

                                            <div v-if="item.items && item.items.length > 1"
                                                class="px-4 py-3 border-t border-gray-100 dark:border-surface-700 text-xs text-text-secondary flex justify-between bg-gray-50/50 dark:!bg-surface-800/50">
                                                <div>
                                                    <span>Total Pesanan: <span
                                                            class="font-bold text-text-primary ml-1">{{ item.qty
                                                            }}</span></span>
                                                </div>
                                                <div class="flex items-center gap-4">
                                                    <span class="font-mono text-[10px] text-gray-500">Jual: {{
                                                        formatCurrency(item.harga_jual) }}</span>
                                                    <span class="font-mono text-[10px] text-gray-500">Modal: {{
                                                        formatCurrency(item.harga_modal ?? item.default_harga_modal)
                                                        }}</span>
                                                    <span class="font-bold font-mono text-[11px]"
                                                        :class="item.profit >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                                                        Profit: {{ formatCurrency(item.profit) }}</span>
                                                </div>
                                            </div>
                                        </template>
                                        <template v-else>
                                            <div class="p-4 text-center text-sm text-gray-500">
                                                Data Rincian Barang Tidak Valid
                                            </div>
                                        </template>
                                    </div>
                                </td>
                                <!-- Actions -->
                                <td class="px-4 py-4">
                                    <div class="flex items-center justify-center gap-2 transition-opacity">
                                        <button @click="openReceipt(item)"
                                            class="p-2 hover:bg-white dark:hover:bg-surface-600 rounded-lg text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 hover:shadow-sm border border-gray-200/50 dark:border-surface-600/50 transition-all shadow-sm"
                                            title="Lihat Nota">
                                            <Eye :size="16" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Receipt Modal (no edit icon for profit - separate checklist button) -->
    <ReceiptModal :isOpen="showReceiptModal" :transaction="selectedTransaction" @close="showReceiptModal = false" />

</template>

<script setup>
import { ref, onMounted, computed, watch, reactive } from 'vue'
import { useEscapeKey } from '../../composables/useEscapeKey'
import { Loader2, Eye, FileText, ChevronDown, Calendar, TrendingUp, Save, ClipboardCheck, Pencil } from 'lucide-vue-next'
import axios from '../../api/axios'
import { useAuthStore } from '../../store/auth'
import ReceiptModal from '../../components/modals/ReceiptModal.vue'

const authStore = useAuthStore()
const isLeader = computed(() => (authStore.userRole || '').toLowerCase() === 'leader')

const loading = ref(false)
const exporting = ref(false)
const selectedPeriod = ref('daily')

// Receipt Modal State
const showReceiptModal = ref(false)
const selectedTransaction = ref(null)

const openReceipt = (item) => {
    selectedTransaction.value = item;
    showReceiptModal.value = true;
}

// Audit Checklist Modal State
const showChecklistModal = ref(false)
const checklistLoading = ref(false)
const checklistSaving = ref(false)
const checklistData = ref(null)
const checklistStockOutId = ref(null)
const checklistEditMode = ref(false)

const closeChecklist = () => {
    showChecklistModal.value = false
    checklistEditMode.value = false
}

useEscapeKey(() => {
    if (showChecklistModal.value) closeChecklist();
});

const openChecklist = async (item) => {
    checklistStockOutId.value = item.id
    checklistEditMode.value = false
    showChecklistModal.value = true
    checklistLoading.value = true
    try {
        const res = await axios.get(`/audit/profit-checklist/${item.id}`)
        checklistData.value = res.data
    } catch (e) {
        console.error('Failed to load profit checklist', e)
        alert('Gagal memuat checklist: ' + (e.response?.data?.message || e.message))
    } finally {
        checklistLoading.value = false
    }
}

const setAnswer = (index, value) => {
    if (checklistData.value?.questions?.[index]) {
        checklistData.value.questions[index].answer = value
    }
}

const saveChecklist = async () => {
    if (!checklistData.value?.questions) return

    const answeredQuestions = checklistData.value.questions.filter(q => q.answer !== null)
    if (answeredQuestions.length === 0) {
        alert('Silakan jawab minimal 1 pertanyaan')
        return
    }

    checklistSaving.value = true
    try {
        const payload = {
            answers: answeredQuestions.map(q => ({
                question_id: q.question_id,
                answer: q.answer,
                notes: q.notes || null,
                content: q.content
            }))
        }
        const res = await axios.post(`/audit/profit-checklist/${checklistStockOutId.value}`, payload)

        // Update the score in the table
        const item = profitRecords.value.daily_sales.find(s => s.id === checklistStockOutId.value)
        if (item) {
            item.audit_score = res.data.score
            item.audit_answered = res.data.answered
            item.audit_total = res.data.total
        }

        // Update modal data
        checklistData.value.score = res.data.score
        checklistData.value.answered = res.data.answered
        checklistData.value.total = res.data.total

        alert('Checklist profit berhasil disimpan!')
    } catch (e) {
        console.error('Failed to save checklist', e)
        alert('Gagal menyimpan: ' + (e.response?.data?.message || e.message))
    } finally {
        checklistSaving.value = false
    }
}



// Monthly Logic
const months = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];
const currentYear = new Date().getFullYear();
const years = Array.from({ length: 5 }, (_, i) => currentYear - 2 + i);

const selectedMonth = ref(new Date().getMonth() + 1);
const selectedYear = ref(currentYear);

const exportExcel = async () => {
    if (exporting.value) return;
    exporting.value = true;
    try {
        const params = { ...filters.value };
        if (selectedLocationKey.value === 'all') {
            params.branch_id = undefined;
            params.online_shop_id = undefined;
        } else {
            const [type, id] = selectedLocationKey.value.split(':');
            params.branch_id = type === 'B' ? id : undefined;
            params.online_shop_id = type === 'S' ? id : undefined;
        }

        const response = await axios.get('/audit/sales/export', {
            params,
            responseType: 'blob'
        });

        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', `audit-profit-export-${new Date().toISOString().split('T')[0]}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    } catch (e) {
        console.error('Export failed', e);
        alert('Gagal export data: ' + (e.response?.data?.message || e.message));
    } finally {
        exporting.value = false;
    }
}

const profitRecords = ref({
    daily_sales: [],
})

const getTodayLocal = () => {
    const d = new Date();
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

const filters = ref({
    start_date: getTodayLocal(),
    end_date: getTodayLocal(),
    branch_id: null
})

const locations = ref([])
const selectedLocationKey = ref('all')

// Summary computeds
const totalHargaJual = computed(() =>
    profitRecords.value.daily_sales.reduce((sum, item) => sum + (Number(item.harga_jual) || 0), 0)
)
const totalHargaModal = computed(() =>
    profitRecords.value.daily_sales.reduce((sum, item) => {
        const modal = item.harga_modal ?? item.default_harga_modal ?? 0
        return sum + Number(modal)
    }, 0)
)
const totalProfit = computed(() => totalHargaJual.value - totalHargaModal.value)

const formattedDateDisplay = computed(() => {
    if (!filters.value.start_date) return 'Pilih Tanggal';

    if (selectedPeriod.value === 'daily') {
        const date = new Date(filters.value.start_date);
        return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
    } else {
        const monthIndex = selectedMonth.value - 1;
        const year = selectedYear.value;
        return `${months[monthIndex]} ${year}`;
    }
})

const handlePeriodChange = () => {
    if (selectedPeriod.value === 'daily') {
        const today = getTodayLocal();
        filters.value.start_date = today;
        filters.value.end_date = today;
    } else {
        handleMonthChange();
    }
    fetchData();
}

const handleDateChange = () => {
    if (selectedPeriod.value === 'daily') {
        filters.value.end_date = filters.value.start_date;
    }
    fetchData();
}

const handleMonthChange = () => {
    const year = selectedYear.value;
    const month = selectedMonth.value;
    const endDate = new Date(year, month, 0);
    const pad = (n) => n < 10 ? '0' + n : n;
    filters.value.start_date = `${year}-${pad(month)}-01`;
    filters.value.end_date = `${year}-${pad(month)}-${pad(endDate.getDate())}`;
    if (selectedPeriod.value === 'monthly') {
        fetchData();
    }
}


const canFilterBranch = computed(() => {
    // Only Audit, Super Admin, Owner, Leader can filter branches
    const role = (authStore.userRole || '').toLowerCase();
    return ['super_admin', 'audit', 'owner', 'leader'].some(r => role.includes(r));
})

const formatCurrency = (value) => {
    const num = Number(value) || 0
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(num)
}

const formatNumber = (value) => {
    const num = Number(value) || 0
    return new Intl.NumberFormat('id-ID').format(num)
}

const formatDate = (dateString) => {
    if (!dateString) return '-'
    return new Date(dateString).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
}

const fetchBranches = async () => {
    if (loading.value) return;
    try {
        const requests = [
            axios.get('/branches'),
            axios.get('/online-shops')
        ];
        if (!authStore.user) {
            requests.push(axios.get('/user'));
        }
        const results = await Promise.all(requests);
        const branchRes = results[0];
        const shopRes = results[1];
        const userRes = results[2];

        const allBranches = (branchRes.data.data || branchRes.data || []).map(b => ({ ...b, type: 'branch' }));
        const allShops = (shopRes.data.data || shopRes.data || []).map(s => ({ ...s, type: 'online_shop' }));
        const allLocations = [...allBranches, ...allShops];

        const user = userRes ? (userRes.data.user || userRes.data.data || userRes.data) : authStore.user;
        const role = (authStore.userRole || '').toLowerCase();
        const isGlobalRole = ['super_admin', 'owner'].includes(role);

        let allowedBranchIds = [];
        if (user?.branch_id) allowedBranchIds.push(user.branch_id);
        let allowedShopIds = [];
        if (user?.online_shop_id) allowedShopIds.push(user.online_shop_id);

        if (user?.placements && Array.isArray(user.placements)) {
            user.placements.forEach(p => {
                if (p.model_type === 'branch') allowedBranchIds.push(p.model_id);
                if (p.model_type === 'online_shop') allowedShopIds.push(p.model_id);
            });
        }

        allowedBranchIds = [...new Set(allowedBranchIds.map(id => Number(id)))];
        allowedShopIds = [...new Set(allowedShopIds.map(id => Number(id)))];

        const hasAnyRestriction = allowedBranchIds.length > 0 || allowedShopIds.length > 0;

        if (isGlobalRole || (role === 'audit' && !hasAnyRestriction)) {
            locations.value = allLocations;
        } else if (hasAnyRestriction) {
            locations.value = allLocations.filter(loc => {
                if (loc.type === 'branch') return allowedBranchIds.includes(Number(loc.id));
                if (loc.type === 'online_shop') return allowedShopIds.includes(Number(loc.id));
                return false;
            });
            if (locations.value.length === 1 && selectedLocationKey.value === 'all') {
                const loc = locations.value[0];
                selectedLocationKey.value = `${loc.type === 'branch' ? 'B' : 'S'}:${loc.id}`;
            }
        } else {
            locations.value = [];
        }
    } catch (error) {
        console.error('Error fetching locations:', error)
    }
}

const fetchData = async () => {
    loading.value = true
    try {
        const params = { ...filters.value };
        if (selectedLocationKey.value === 'all') {
            params.branch_id = undefined;
            params.online_shop_id = undefined;
        } else {
            const [type, id] = selectedLocationKey.value.split(':');
            params.branch_id = type === 'B' ? id : undefined;
            params.online_shop_id = type === 'S' ? id : undefined;
        }

        const response = await axios.get('/audit/profit', { params })
        profitRecords.value = response.data
        initEditableModal()
    } catch (error) {
        console.error('Error fetching profit data:', error)
    } finally {
        loading.value = false
    }
}

onMounted(async () => {
    const today = getTodayLocal();
    filters.value.start_date = today;
    filters.value.end_date = today;

    if (canFilterBranch.value && locations.value.length === 0) {
        await fetchBranches()
    }

    fetchData()
})

watch(() => authStore.user, async (newUser) => {
    if (newUser && canFilterBranch.value) {
        await fetchBranches();
    }
});
</script>
