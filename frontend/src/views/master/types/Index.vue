<script setup>
import { ref, onMounted, computed } from 'vue';
import { Plus, Search, Edit, Trash2, Smartphone, Disc, Wrench, RefreshCw, Box } from 'lucide-vue-next';
import { productTypes as api, brands as brandsApi } from '../../../api/axios';
import { useToast } from '../../../composables/useToast';
import TypeModal from './TypeModal.vue';

const toast = useToast();
const types = ref([]);
const brands = ref([]);
const loading = ref(false);
const searchQuery = ref('');

const selectedBrand = ref('');
const selectedCategory = ref('hp'); // 'hp' or 'non-hp'
const showModal = ref(false);
const editingType = ref(null);

const fetchData = async () => {
    loading.value = true;
    try {
        const [typesRes, brandsRes] = await Promise.all([
            api.list(),
            brandsApi.list()
        ]);
        types.value = typesRes.data.data;
        brands.value = brandsRes.data.data;
    } catch (error) {
        console.error(error);
        toast.error('Gagal memuat data tipe');
    } finally {
        loading.value = false;
    }
};

const filteredTypes = computed(() => {
    let result = types.value;

    if (selectedBrand.value) {
        result = result.filter(t => t.brand_id === selectedBrand.value);
    }

    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();

        result = result.filter(t =>
            t.name.toLowerCase().includes(query) ||
            (t.brand && t.brand.name.toLowerCase().includes(query))
        );
    }

    // Filter by Category Tab
    if (selectedCategory.value === 'hp') {
        result = result.filter(t => t.category === 'imei' || t.category === 'HP / Gadget');
    } else {
        result = result.filter(t => t.category !== 'imei' && t.category !== 'HP / Gadget');
    }

    return result;
});

const openCreateModal = () => {
    editingType.value = null;
    showModal.value = true;
};

const openEditModal = (type) => {
    editingType.value = type;
    showModal.value = true;
};

const handleDelete = async (id) => {
    if (!confirm('Apakah Anda yakin ingin menghapus tipe ini?')) return;
    try {
        await api.delete(id);
        toast.success('Tipe berhasil dihapus');
        fetchData(); // Refresh to get latest state
    } catch (error) {
        toast.error('Gagal menghapus tipe');
    }
};

const handleSaved = () => {
    showModal.value = false;
    fetchData();
};

const getCategoryIcon = (cat) => {
    if (cat === 'imei') return Smartphone;
    if (cat === 'non_imei') return Disc;
    return Wrench;
};

const getCategoryLabel = (cat) => {
    if (cat === 'imei') return 'HP / Gadget';
    if (cat === 'non_imei') return 'NON HP / NON IMEI';
    return 'Jasa Service';
};

const getCategoryColor = (cat) => {
    if (cat === 'imei') return 'text-blue-500 bg-blue-500/10 border-blue-500/20';
    if (cat === 'non_imei') return 'text-purple-500 bg-purple-500/10 border-purple-500/20';
    return 'text-amber-500 bg-amber-500/10 border-amber-500/20';
};

onMounted(fetchData);
</script>

<template>
    <div class="space-y-6 animate-in">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h1 class="text-2xl font-bold text-text-primary tracking-tight">Data Tipe Produk</h1>
                <p class="text-text-secondary mt-1">Kelola tipe dan spesifikasi produk</p>
            </div>
            <button @click="openCreateModal"
                class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2.5 rounded-xl font-medium flex items-center gap-2 transition-all shadow-lg shadow-primary-500/20 active:scale-95">
                <Plus :size="20" />
                <span>Tambah Tipe</span>
            </button>
        </div>

        <!-- Filter -->
        <div class="bg-surface-800 rounded-2xl border border-surface-700 p-4">
            <div class="flex flex-col sm:flex-row gap-4">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary" :size="18" />
                    <input v-model="searchQuery" type="text" placeholder="Cari tipe atau merek..."
                        class="w-full bg-surface-900 border border-surface-700 rounded-xl py-2.5 pl-10 pr-4 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition-all placeholder:text-text-secondary" />
                </div>
                <!-- Brand Filter -->
                <select v-model="selectedBrand"
                    class="sm:w-48 bg-surface-900 border border-surface-700 rounded-xl px-4 py-2.5 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/50 appearance-none">
                    <option value="">Semua Merek</option>
                    <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option>
                </select>
            </div>
        </div>


        <!-- Category Tabs -->
        <div class="flex gap-2 border-b border-surface-700">
            <button @click="selectedCategory = 'hp'" class="px-6 py-3 font-medium transition-all relative"
                :class="selectedCategory === 'hp' ? 'text-primary-500' : 'text-text-secondary hover:text-text-primary'">
                HP / IMEI
                <div v-if="selectedCategory === 'hp'" class="absolute bottom-0 left-0 right-0 h-0.5 bg-primary-500">
                </div>
            </button>
            <button @click="selectedCategory = 'non-hp'" class="px-6 py-3 font-medium transition-all relative"
                :class="selectedCategory === 'non-hp' ? 'text-primary-500' : 'text-text-secondary hover:text-text-primary'">
                NON HP / NON IMEI
                <div v-if="selectedCategory === 'non-hp'" class="absolute bottom-0 left-0 right-0 h-0.5 bg-primary-500">
                </div>
            </button>
        </div>

        <!-- Content -->
        <div class="bg-surface-800 rounded-2xl border border-surface-700 overflow-hidden">
            <div v-if="loading" class="p-12 flex justify-center items-center">
                <RefreshCw class="animate-spin text-primary-500" :size="32" />
                <span class="ml-3 text-text-secondary">Memuat data...</span>
            </div>

            <div v-else-if="filteredTypes.length === 0" class="p-12 text-center text-text-secondary">
                <Box :size="48" class="mx-auto mb-3 opacity-50" />
                <p>Belum ada data tipe produk</p>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm text-left text-text-primary">
                    <thead class="bg-surface-900/50 text-text-secondary uppercase text-xs font-semibold">
                        <tr>
                            <th class="px-6 py-4">Nama Tipe</th>
                            <th class="px-6 py-4">Merek</th>
                            <th class="px-6 py-4">Kategori</th>
                            <th class="px-6 py-4">Spesifikasi</th>
                            <th class="px-6 py-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-700/50">
                        <tr v-for="type in filteredTypes" :key="type.id"
                            class="hover:bg-surface-700/30 transition-colors">
                            <td class="px-6 py-4 font-medium">{{ type.name }}</td>
                            <td class="px-6 py-4">{{ type.brand ? type.brand.name : '-' }}</td>
                            <td class="px-6 py-4">
                                <span
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium border"
                                    :class="getCategoryColor(type.category)">
                                    <component :is="getCategoryIcon(type.category)" :size="12" />
                                    {{ getCategoryLabel(type.category) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-text-secondary">
                                <div v-if="type.category === 'imei'" class="flex gap-3 text-xs">
                                    <span v-if="type.storage" class="bg-surface-900 px-2 py-1 rounded">Kapasitas: {{
                                        type.storage }}</span>
                                </div>
                                <span v-else class="text-xs italic">-</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <button @click="openEditModal(type)"
                                        class="p-2 text-blue-400 hover:bg-blue-500/10 rounded-lg transition-colors">
                                        <Edit :size="16" />
                                    </button>
                                    <button @click="handleDelete(type.id)"
                                        class="p-2 text-red-400 hover:bg-red-500/10 rounded-lg transition-colors">
                                        <Trash2 :size="16" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <TypeModal :show="showModal" :type="editingType" @close="showModal = false" @saved="handleSaved" />
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
