<script setup>
import { ref, onMounted, watch } from 'vue';
import { Plus, Search, Edit2, Trash2, RefreshCw, Box, Download } from 'lucide-vue-next';
import { productPrices as api, brands as brandsApi, productTypes as typesApi, auth as apiAuth } from '../../../api/axios';
import { useToast } from '../../../composables/useToast';
import PriceModal from './PriceModal.vue';
import { getTodayLocal } from '../../../utils/formatters';

const toast = useToast();
const loading = ref(false);
const prices = ref([]);
const brands = ref([]);
const types = ref([]);

const showModal = ref(false);
const selectedPrice = ref(null);

// --- Delete with Password Confirmation ---
const showDeleteModal = ref(false);
const deletePin = ref('');
const priceToDelete = ref(null);
const verifyingPin = ref(false);

// Debounced search
let searchTimeout = null;
const searchInput = ref('');

const filters = ref({
    search: '',
    condition: '',
    category: 'hp'
});

watch(searchInput, (val) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        filters.value.search = val;
    }, 300);
});

// Fetch Data
const fetchData = async () => {
    loading.value = true;
    try {
        const res = await api.list(filters.value);
        prices.value = res.data.data;
    } catch (e) {
        console.error(e);
        toast.error('Gagal memuat data harga');
    } finally {
        loading.value = false;
    }
};

const fetchMasterData = async () => {
    try {
        const [bRes, tRes] = await Promise.all([
            brandsApi.list(),
            typesApi.list()
        ]);
        brands.value = bRes.data.data;
        types.value = tRes.data.data;
    } catch (e) {
        console.error("Failed master data", e);
    }
};

onMounted(() => {
    fetchMasterData();
    fetchData();
});

watch(filters, () => fetchData(), { deep: true });

const openModal = (price = null) => {
    selectedPrice.value = price;
    showModal.value = true;
};

const formatRupiah = (val) => {
    if (!val && val !== 0) return 'Rp 0';
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(val);
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    }).format(new Date(dateString));
};

const openDeleteModal = (id) => {
    priceToDelete.value = id;
    deletePin.value = '';
    showDeleteModal.value = true;
};

const confirmDelete = async () => {
    if (!deletePin.value) return;

    verifyingPin.value = true;
    try {
        // 1. Verify PIN
        await apiAuth.verifyPin(deletePin.value);
        await api.delete(priceToDelete.value);
        toast.success('Harga berhasil dihapus');
        fetchData();
        showDeleteModal.value = false;
    } catch (error) {
        console.error(error);
        if (error.response && error.response.status === 422) {
            toast.error('PIN Keamanan salah!');
        } else {
            toast.error('Gagal menghapus harga');
        }
    } finally {
        verifyingPin.value = false;
    }
};

// Export to Excel
const exporting = ref(false);

const formatRupiahExcel = (val) => {
    if (!val && val !== 0) return 'Rp 0';
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(val);
};

const exportToExcel = () => {
    exporting.value = true;
    try {
        const categoryLabel = filters.value.category === 'hp' ? 'HP_IMEI' : 'Non_HP';
        const isHp = filters.value.category === 'hp';

        let html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="UTF-8"><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Data Harga</x:Name></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head><body><table border="1">';

        // Header
        html += '<tr style="background:#1E293B;color:#fff;font-weight:bold">';
        html += '<td>No</td><td>Brand</td><td>Tipe</td>';
        if (isHp) html += '<td>Kapasitas</td><td>Kondisi</td>';
        html += '<td>Harga Modal</td><td>Harga Jual</td><td>Tanggal</td><td>Jam</td>';
        html += '</tr>';

        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

        // Data rows
        prices.value.forEach((item, index) => {
            const brand = item.product_type?.brand?.name || '-';
            const tipe = item.product_type?.name || '-';
            const kapasitas = [item.ram, item.storage].filter(Boolean).join(' / ') || '-';
            const kondisi = item.condition === 'new' ? 'Baru' : (item.condition === 'ex_ibox' ? 'Ex iBox' : 'Bekas');
            const modal = formatRupiahExcel(item.cost_price);
            const jual = formatRupiahExcel(item.price);
            const d = item.updated_at ? new Date(item.updated_at) : null;
            const tanggal = d ? `${String(d.getDate()).padStart(2, '0')} ${months[d.getMonth()]} ${d.getFullYear()}` : '-';
            const jam = d ? `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}` : '-';

            html += '<tr>';
            html += `<td>${index + 1}</td><td>${brand}</td><td>${tipe}</td>`;
            if (isHp) html += `<td>${kapasitas}</td><td>${kondisi}</td>`;
            html += `<td>${modal}</td><td>${jual}</td><td>${tanggal}</td><td>${jam}</td>`;
            html += '</tr>';
        });

        html += '</table></body></html>';

        const blob = new Blob(['\uFEFF' + html], { type: 'application/vnd.ms-excel;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `Data_Harga_${categoryLabel}_${getTodayLocal()}.xls`;
        link.click();
        URL.revokeObjectURL(url);
        toast.success('Data berhasil diexport!');
    } catch (e) {
        console.error(e);
        toast.error('Gagal export data');
    } finally {
        exporting.value = false;
    }
};
</script>

<template>
    <div class="space-y-4 sm:space-y-6 animate-in">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
            <div>
                <h1 class="text-xl sm:text-2xl font-bold text-text-primary tracking-tight">Data Harga</h1>
                <p class="text-text-secondary text-sm mt-1">Kelola harga dasar berdasarkan Tipe dan Kondisi</p>
            </div>
            <div class="flex items-center gap-2">
                <button @click="exportToExcel" :disabled="exporting || prices.length === 0"
                    class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 sm:px-4 py-2.5 rounded-xl font-medium flex items-center gap-2 transition-all shadow-lg shadow-emerald-500/20 active:scale-95 text-sm disabled:opacity-50 disabled:cursor-not-allowed">
                    <Download :size="18" />
                    <span class="hidden sm:inline">Export Excel</span>
                </button>
                <button @click="openModal()"
                    class="bg-primary-600 hover:bg-primary-700 text-white px-3 sm:px-4 py-2.5 rounded-xl font-medium flex items-center gap-2 transition-all shadow-lg shadow-primary-500/20 active:scale-95 text-sm">
                    <Plus :size="18" />
                    <span class="hidden sm:inline">Tambah Harga</span>
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-surface-800 p-3 sm:p-4 rounded-2xl border border-surface-700">
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary" :size="18" />
                    <input v-model="searchInput" type="text" placeholder="Cari Brand / Tipe..."
                        class="w-full bg-surface-900 border border-surface-700 rounded-xl py-2.5 pl-10 pr-4 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition-all placeholder:text-text-secondary" />
                </div>
                <select v-if="filters.category === 'hp'" v-model="filters.condition"
                    class="w-full sm:w-44 bg-surface-900 border border-surface-700 rounded-xl px-4 py-2.5 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/50 appearance-none">
                    <option value="">Semua Kondisi</option>
                    <option value="new">Baru (New)</option>
                    <option value="second">Bekas (Second)</option>
                    <option value="ex_ibox">Ex iBox (Khusus iPhone)</option>
                </select>
            </div>
        </div>

        <!-- Category Tabs -->
        <div class="flex gap-2 border-b border-surface-700">
            <button @click="filters.category = 'hp'"
                class="px-4 sm:px-6 py-3 font-medium transition-all relative text-sm"
                :class="filters.category === 'hp' ? 'text-primary-500' : 'text-text-secondary hover:text-text-primary'">
                HP / IMEI
                <div v-if="filters.category === 'hp'" class="absolute bottom-0 left-0 right-0 h-0.5 bg-primary-500">
                </div>
            </button>
            <button @click="filters.category = 'non-hp'"
                class="px-4 sm:px-6 py-3 font-medium transition-all relative text-sm"
                :class="filters.category === 'non-hp' ? 'text-primary-500' : 'text-text-secondary hover:text-text-primary'">
                Non HP / Aksesoris
                <div v-if="filters.category === 'non-hp'" class="absolute bottom-0 left-0 right-0 h-0.5 bg-primary-500">
                </div>
            </button>
        </div>

        <!-- Content -->
        <div class="bg-surface-800 rounded-2xl border border-surface-700 overflow-hidden">
            <!-- Loading -->
            <div v-if="loading" class="p-8 sm:p-12 flex justify-center items-center">
                <RefreshCw class="animate-spin text-primary-500" :size="28" />
                <span class="ml-3 text-text-secondary text-sm">Memuat data...</span>
            </div>

            <!-- Empty -->
            <div v-else-if="prices.length === 0" class="p-8 sm:p-12 text-center text-text-secondary">
                <Box :size="40" class="mx-auto mb-3 opacity-50" />
                <p class="text-sm">Belum ada data harga</p>
            </div>

            <!-- Desktop Table -->
            <div v-else class="hidden md:block overflow-x-auto">
                <table class="w-full text-sm text-left text-text-primary">
                    <thead class="bg-surface-900/50 text-text-secondary uppercase text-xs font-semibold">
                        <tr>
                            <th class="px-5 py-3">Brand & Tipe</th>
                            <th v-if="filters.category === 'hp'" class="px-5 py-3 text-center">Kapasitas</th>
                            <th v-if="filters.category === 'hp'" class="px-5 py-3 text-center">Kondisi</th>
                            <th class="px-5 py-3 text-right">Harga Modal</th>
                            <th class="px-5 py-3 text-right">Harga Jual</th>
                            <th class="px-5 py-3">Diperbarui</th>
                            <th class="px-5 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-700/50">
                        <tr v-for="item in prices" :key="item.id" class="hover:bg-surface-700/30 transition-colors">
                            <!-- Combined Brand & Type -->
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-9 h-9 rounded-xl bg-primary-500/10 flex items-center justify-center shrink-0">
                                        <span class="text-primary-400 text-xs font-bold">{{
                                            (item.product_type?.brand?.name || '?')[0] }}</span>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-semibold text-text-primary truncate">
                                            {{ item.product_type?.brand?.name || '-' }} {{ item.product_type?.name || ''
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <!-- Capacity (HP only) -->
                            <td v-if="filters.category === 'hp'" class="px-5 py-3 text-center">
                                    <span v-if="item.ram || item.storage"
                                        class="inline-flex items-center px-2 py-0.5 rounded-md bg-indigo-500/10 text-indigo-400 text-[11px] font-semibold border border-indigo-500/20">
                                        {{ [item.ram, item.storage].filter(Boolean).join('/') }}
                                    </span>
                                    <span v-else
                                        class="text-text-secondary text-xs italic">-</span>
                            </td>
                            <!-- Condition (HP only) -->
                            <td v-if="filters.category === 'hp'" class="px-5 py-3 text-center">
                                <span class="px-2.5 py-1 rounded-lg text-[11px] font-bold uppercase tracking-wide"
                                    :class="item.condition === 'new' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : (item.condition === 'ex_ibox' ? 'bg-purple-500/10 text-purple-400 border border-purple-500/20' : 'bg-amber-500/10 text-amber-400 border border-amber-500/20')">
                                    {{ item.condition === 'new' ? 'BARU' : (item.condition === 'ex_ibox' ? 'EX IBOX' :
                                    'BEKAS') }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right text-text-secondary text-sm">{{
                                formatRupiah(item.cost_price) }}</td>
                            <td class="px-5 py-3 text-right font-bold text-emerald-400 text-sm">{{
                                formatRupiah(item.price) }}</td>
                            <td class="px-5 py-3 text-text-secondary text-xs">{{ formatDate(item.updated_at) }}</td>
                            <td class="px-5 py-3 text-right">
                                <div class="flex justify-end gap-1">
                                    <button @click="openModal(item)"
                                        class="p-2 text-blue-400 hover:bg-blue-500/10 rounded-lg transition-colors">
                                        <Edit2 :size="16" />
                                    </button>
                                    <button @click="openDeleteModal(item.id)"
                                        class="p-2 text-red-400 hover:bg-red-500/10 rounded-lg transition-colors">
                                        <Trash2 :size="16" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Card List -->
            <div v-if="!loading && prices.length > 0" class="md:hidden divide-y divide-surface-700/50">
                <div v-for="item in prices" :key="item.id" class="p-4 hover:bg-surface-700/20 transition-colors">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3 flex-1 min-w-0">
                            <div class="w-9 h-9 rounded-xl bg-primary-500/10 flex items-center justify-center shrink-0">
                                <span class="text-primary-400 text-xs font-bold">{{ (item.product_type?.brand?.name ||
                                    '?')[0] }}</span>
                            </div>
                            <div class="min-w-0">
                                <p class="font-semibold text-text-primary text-sm truncate">
                                    {{ item.product_type?.brand?.name || '-' }} {{ item.product_type?.name || '' }}
                                </p>
                                <div class="flex flex-wrap items-center gap-1.5 mt-1">
                                    <span v-if="item.ram || item.storage"
                                        class="inline-flex px-1.5 py-0.5 rounded bg-indigo-500/10 text-indigo-400 text-[10px] font-semibold border border-indigo-500/20">
                                        {{ [item.ram, item.storage].filter(Boolean).join('/') }}
                                    </span>
                                    <span v-if="filters.category === 'hp'"
                                        class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase"
                                        :class="item.condition === 'new' ? 'bg-emerald-500/10 text-emerald-400' : (item.condition === 'ex_ibox' ? 'bg-purple-500/10 text-purple-400' : 'bg-amber-500/10 text-amber-400')">
                                        {{ item.condition === 'new' ? 'BARU' : (item.condition === 'ex_ibox' ? 'EX IBOX'
                                        : 'BEKAS') }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="flex gap-1 shrink-0">
                            <button @click="openModal(item)"
                                class="p-2 text-blue-400 hover:bg-blue-500/10 rounded-lg transition-colors">
                                <Edit2 :size="16" />
                            </button>
                            <button @click="openDeleteModal(item.id)"
                                class="p-2 text-red-400 hover:bg-red-500/10 rounded-lg transition-colors">
                                <Trash2 :size="16" />
                            </button>
                        </div>
                    </div>
                    <!-- Price Row -->
                    <div class="flex items-center justify-between mt-2.5 pt-2 border-t border-surface-700/50">
                        <div class="flex items-center gap-4">
                            <div>
                                <p class="text-[10px] text-text-secondary uppercase">Modal</p>
                                <p class="text-sm text-text-secondary">{{ formatRupiah(item.cost_price) }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] text-text-secondary uppercase">Jual</p>
                                <p class="text-sm font-bold text-emerald-400">{{ formatRupiah(item.price) }}</p>
                            </div>
                        </div>
                        <p class="text-[10px] text-text-secondary">{{ formatDate(item.updated_at) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <PriceModal :show="showModal" :price="selectedPrice" :initialCategory="filters.category"
            @close="showModal = false" @saved="fetchData(); showModal = false" />

        <!-- Delete Confirmation Modal -->
        <div v-if="showDeleteModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm animate-in">
            <div class="bg-surface-800 border border-surface-700 rounded-2xl w-full max-w-md p-6 shadow-xl">
                <h3 class="text-lg font-bold text-text-primary mb-2">Konfirmasi Hapus</h3>
                <p class="text-text-secondary text-sm mb-5">
                    Masukkan PIN Keamanan Anda untuk melanjutkan penghapusan harga ini.
                </p>
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-text-secondary uppercase mb-1">PIN Keamanan</label>
                        <input v-model="deletePin" type="password" placeholder="Masukkan PIN keamanan anda" maxlength="4"
                            class="w-full bg-surface-900 border border-surface-700 rounded-xl px-4 py-3 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-red-500/50 focus:border-red-500 transition-all placeholder:text-surface-500"
                            @keyup.enter="confirmDelete" />
                    </div>
                    <div class="flex justify-end gap-3 pt-1">
                        <button @click="showDeleteModal = false"
                            class="px-4 py-2 bg-surface-700 hover:bg-surface-600 text-text-primary rounded-xl font-medium text-sm transition-colors">
                            Batal
                        </button>
                        <button @click="confirmDelete" :disabled="verifyingPin || !deletePin"
                            class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-xl font-medium text-sm transition-all shadow-lg shadow-red-500/20 flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                            <RefreshCw v-if="verifyingPin" class="animate-spin" :size="16" />
                            <Trash2 v-else :size="16" />
                            <span>{{ verifyingPin ? 'Memverifikasi...' : 'Hapus' }}</span>
                        </button>
                    </div>
                </div>
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
