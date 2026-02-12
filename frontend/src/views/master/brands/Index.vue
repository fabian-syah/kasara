<script setup>
import { ref, onMounted, computed } from 'vue';
import { Plus, Search, Edit, Trash2, Tag, RefreshCw, Box } from 'lucide-vue-next';
import { brands as api } from '../../../api/axios';
import { useToast } from '../../../composables/useToast';
import BrandModal from './BrandModal.vue';

const toast = useToast();
const brands = ref([]);
const loading = ref(false);
const searchQuery = ref('');
const activeCategory = ref('all');
const showModal = ref(false);
const editingBrand = ref(null);

const fetchData = async () => {
    loading.value = true;
    try {
        const res = await api.list();
        brands.value = res.data.data;
    } catch (error) {
        console.error(error);
        toast.error('Gagal memuat data merek');
    } finally {
        loading.value = false;
    }
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric'
    });
};

const formatTime = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit'
    });
};

const filteredBrands = computed(() => {
    let result = brands.value;

    // Filter by Category
    if (activeCategory.value !== 'all') {
        result = result.filter(b => b.category === activeCategory.value);
    }

    // Filter by Search
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(b =>
            b.name.toLowerCase().includes(query) ||
            (b.description && b.description.toLowerCase().includes(query))
        );
    }

    return result;
});

const openCreateModal = () => {
    editingBrand.value = null;
    showModal.value = true;
};

const openEditModal = (brand) => {
    editingBrand.value = brand;
    showModal.value = true;
};

const handleDelete = async (id) => {
    if (!confirm('Apakah Anda yakin ingin menghapus merek ini?')) return;
    try {
        await api.delete(id);
        toast.success('Merek berhasil dihapus');
        fetchData();
    } catch (error) {
        toast.error('Gagal menghapus merek');
    }
};

const handleSaved = () => {
    showModal.value = false;
    fetchData();
};

onMounted(fetchData);
</script>

<template>
    <div class="space-y-6 animate-in">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h1 class="text-2xl font-bold text-text-primary tracking-tight">Data Merek</h1>
                <p class="text-text-secondary mt-1">Kelola daftar merek produk</p>
            </div>
            <button @click="openCreateModal"
                class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2.5 rounded-xl font-medium flex items-center gap-2 transition-all shadow-lg shadow-primary-500/20 active:scale-95">
                <Plus :size="20" />
                <span>Tambah Merek</span>
            </button>
        </div>

        <!-- Filter -->
        <div class="space-y-4">
            <!-- Category Tabs -->
            <div class="flex gap-2 p-1 bg-surface-800 rounded-xl border border-surface-700 w-fit">
                <button @click="activeCategory = 'all'" class="px-4 py-2 rounded-lg text-sm font-medium transition-all"
                    :class="activeCategory === 'all' ? 'bg-surface-700 text-white shadow-sm' : 'text-text-secondary hover:text-white hover:bg-surface-700/50'">
                    Semua
                </button>
                <button @click="activeCategory = 'imei'"
                    class="px-4 py-2 rounded-lg text-sm font-medium transition-all flex items-center gap-2"
                    :class="activeCategory === 'imei' ? 'bg-blue-500/20 text-blue-400 shadow-sm' : 'text-text-secondary hover:text-white hover:bg-surface-700/50'">
                    HP / Unit
                </button>
                <button @click="activeCategory = 'non-imei'"
                    class="px-4 py-2 rounded-lg text-sm font-medium transition-all flex items-center gap-2"
                    :class="activeCategory === 'non-imei' ? 'bg-purple-500/20 text-purple-400 shadow-sm' : 'text-text-secondary hover:text-white hover:bg-surface-700/50'">
                    Non-HP
                </button>
            </div>

            <!-- Search -->
            <div class="bg-surface-800 rounded-2xl border border-surface-700 p-4">
                <div class="relative max-w-md">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary" :size="18" />
                    <input v-model="searchQuery" type="text" placeholder="Cari nama merek..."
                        class="w-full bg-surface-900 border border-surface-700 rounded-xl py-2.5 pl-10 pr-4 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition-all placeholder:text-text-secondary" />
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="bg-surface-800 rounded-2xl border border-surface-700 overflow-hidden">
            <div v-if="loading" class="p-12 flex justify-center items-center">
                <RefreshCw class="animate-spin text-primary-500" :size="32" />
                <span class="ml-3 text-text-secondary">Memuat data...</span>
            </div>

            <div v-else-if="filteredBrands.length === 0" class="p-12 text-center text-text-secondary">
                <Box :size="48" class="mx-auto mb-3 opacity-50" />
                <p>Belum ada data merek</p>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm text-left text-text-primary">
                    <thead class="bg-surface-900/50 text-text-secondary uppercase text-xs font-semibold">
                        <tr>
                            <th class="px-6 py-4">Nama Merek</th>
                            <th class="px-6 py-4">Kategori</th>
                            <th class="px-6 py-4 text-center">Total Produk</th>
                            <th class="px-6 py-4">Tanggal Dibuat</th>
                            <th class="px-6 py-4">Terakhir Update</th>
                            <th class="px-6 py-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-700/50">
                        <tr v-for="brand in filteredBrands" :key="brand.id"
                            class="hover:bg-surface-700/30 transition-colors">
                            <td class="px-6 py-4 font-medium flex items-center gap-3">
                                <div
                                    class="w-8 h-8 rounded-lg bg-surface-700 flex items-center justify-center text-text-secondary">
                                    <Tag :size="16" />
                                </div>
                                <div>
                                    <div class="font-medium text-white">{{ brand.name }}</div>
                                    <div class="text-xs text-text-secondary font-mono">{{ brand.slug }}</div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 rounded-md text-xs font-medium"
                                    :class="brand.category === 'imei' ? 'bg-blue-500/20 text-blue-400' : 'bg-purple-500/20 text-purple-400'">
                                    {{ brand.category === 'imei' ? 'HP / Unit' : 'Non-HP' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="px-2 py-1 rounded-md bg-surface-700 text-text-primary text-xs font-bold">
                                    {{ brand.product_types_count || 0 }} Type
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-col">
                                    <span class="text-text-primary">{{ formatDate(brand.created_at) }}</span>
                                    <span class="text-xs text-text-secondary">{{ formatTime(brand.created_at) }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-text-secondary">
                                {{ formatDate(brand.updated_at) }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <button @click="openEditModal(brand)"
                                        class="p-2 text-blue-400 hover:bg-blue-500/10 rounded-lg transition-colors">
                                        <Edit :size="16" />
                                    </button>
                                    <button @click="handleDelete(brand.id)"
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

        <BrandModal :show="showModal" :brand="editingBrand" @close="showModal = false" @saved="handleSaved" />
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
