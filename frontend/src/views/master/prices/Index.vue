<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { Plus, Search, Edit2, Trash2, Tag, Smartphone, Filter } from 'lucide-vue-next';
import { productPrices as api, brands as brandsApi, productTypes as typesApi } from '../../../api/axios';
import { useToast } from '../../../composables/useToast';
import PriceModal from './PriceModal.vue'; // We will create this next

const toast = useToast();
const loading = ref(false);
const prices = ref([]);
const brands = ref([]);
const types = ref([]);

const showModal = ref(false);
const selectedPrice = ref(null);

const filters = ref({
    search: '',
    product_type_id: '',
    condition: ''
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
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(val);
};

const confirmDelete = async (id) => {
    if (!confirm('Yakin ingin menghapus harga ini?')) return;
    try {
        await api.delete(id);
        toast.success('Harga berhasil dihapus');
        fetchData();
    } catch (e) {
        toast.error('Gagal menghapus');
    }
};
</script>

<template>
    <div class="space-y-6 animate-in fade-in">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-text-primary">Data Harga</h1>
                <p class="text-text-secondary">Kelola harga dasar berdasarkan Tipe dan Kondisi</p>
            </div>
            <button @click="openModal()" class="btn btn-primary flex items-center gap-2 px-4 py-2 rounded-xl">
                <Plus :size="18" /> Tambah Harga
            </button>
        </div>

        <!-- Filters -->
        <div class="bg-surface-800 p-4 rounded-xl border border-surface-700 flex gap-4 flex-wrap">
            <div class="relative flex-1 min-w-[200px]">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary" :size="18" />
                <input v-model="filters.search" class="input pl-10 bg-surface-900" placeholder="Cari Tipe / Merek..." />
            </div>
            <select v-model="filters.condition" class="input bg-surface-900 w-[150px]">
                <option value="">Semua Kondisi</option>
                <option value="new">Baru (New)</option>
                <option value="second">Bekas (Second)</option>
            </select>
        </div>

        <!-- Table -->
        <div class="bg-surface-800 rounded-xl border border-surface-700 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-surface-700 bg-surface-900/50 text-text-secondary text-sm">
                            <th class="p-4 font-medium">Merek & Tipe</th>
                            <th class="p-4 font-medium">Kondisi</th>
                            <th class="p-4 font-medium">Harga Modal</th>
                            <th class="p-4 font-medium">Harga Jual</th>
                            <th class="p-4 font-medium text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-700">
                        <tr v-if="loading" class="animate-pulse">
                            <td colspan="5" class="p-8 text-center text-text-secondary">Memuat data...</td>
                        </tr>
                        <tr v-else-if="prices.length === 0">
                            <td colspan="5" class="p-8 text-center text-text-secondary">Belum ada data harga.</td>
                        </tr>
                        <tr v-else v-for="item in prices" :key="item.id"
                            class="hover:bg-surface-700/30 transition-colors">
                            <td class="p-4">
                                <div class="font-bold text-text-primary">{{ item.product_type?.name }}</div>
                                <div class="text-xs text-text-secondary">{{ item.product_type?.brand?.name }}</div>
                            </td>
                            <td class="p-4">
                                <span class="px-2 py-1 rounded-lg text-xs font-bold uppercase tracking-wide"
                                    :class="item.condition === 'new' ? 'bg-emerald-500/10 text-emerald-500' : 'bg-amber-500/10 text-amber-500'">
                                    {{ item.condition === 'new' ? 'BARU' : 'BEKAS' }}
                                </span>
                            </td>
                            <td class="p-4 text-text-primary">{{ formatRupiah(item.cost_price) }}</td>
                            <td class="p-4 text-text-primary font-bold">{{ formatRupiah(item.price) }}</td>
                            <td class="p-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button @click="openModal(item)"
                                        class="p-2 hover:bg-surface-600 rounded-lg text-blue-400 transition-colors">
                                        <Edit2 :size="16" />
                                    </button>
                                    <button @click="confirmDelete(item.id)"
                                        class="p-2 hover:bg-surface-600 rounded-lg text-red-400 transition-colors">
                                        <Trash2 :size="16" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <PriceModal :show="showModal" :price="selectedPrice" @close="showModal = false"
            @saved="fetchData(); showModal = false" />
    </div>
</template>

<style scoped>
@reference "../../../style.css";

.input {
    @apply w-full border border-surface-600 rounded-xl px-4 py-2 text-text-primary focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500 transition-all placeholder:text-surface-500;
}

.btn-primary {
    @apply bg-primary-600 hover:bg-primary-500 text-white font-medium transition-all shadow-lg shadow-primary-500/20 active:scale-95;
}
</style>
