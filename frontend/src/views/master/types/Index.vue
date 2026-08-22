<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { Plus, Search, Edit, Trash2, Smartphone, Disc, Wrench, RefreshCw, Box } from 'lucide-vue-next';
import { productTypes as api, brands as brandsApi, auth as apiAuth } from '../../../api/axios';
import { useToast } from '../../../composables/useToast';
import TypeModal from './TypeModal.vue';

const toast = useToast();
const types = ref([]);
const brands = ref([]);
const loading = ref(false);
const searchQuery = ref('');

const selectedBrand = ref('');
const selectedCategory = ref('hp');
const showModal = ref(false);
const editingType = ref(null);

// --- Delete with Password Confirmation ---
const showDeleteModal = ref(false);
const deletePin = ref('');
const typeToDelete = ref(null);
const verifyingPin = ref(false);

// Debounce search for performance
let searchTimeout = null;
const debouncedSearch = ref('');

watch(searchQuery, (val) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        debouncedSearch.value = val;
    }, 300);
});

const fetchBrands = async () => {
    try {
        const res = await brandsApi.list();
        brands.value = res.data.data;
    } catch (e) {
        console.error(e);
    }
};

const fetchTypes = async () => {
    loading.value = true;
    try {
        const params = {};
        if (selectedBrand.value) params.brand_id = selectedBrand.value;
        if (selectedCategory.value) params.category = selectedCategory.value;
        if (debouncedSearch.value) params.search = debouncedSearch.value;

        const res = await api.list(params);
        types.value = res.data.data;
    } catch (error) {
        console.error(error);
        toast.error('Gagal memuat data tipe');
    } finally {
        loading.value = false;
    }
};

// Re-fetch when filters change (server-side filtering = fast)
watch([selectedBrand, selectedCategory, debouncedSearch], () => {
    fetchTypes();
});

const openCreateModal = () => {
    editingType.value = null;
    showModal.value = true;
};

const openEditModal = (type) => {
    editingType.value = type;
    showModal.value = true;
};

const openDeleteModal = (id) => {
    typeToDelete.value = id;
    deletePin.value = '';
    showDeleteModal.value = true;
};

const confirmDelete = async () => {
    if (!deletePin.value) return;
    verifyingPin.value = true;
    try {
        await apiAuth.verifyPassword(deletePin.value);
        await api.delete(typeToDelete.value);
        toast.success('Tipe berhasil dihapus');
        fetchTypes();
        showDeleteModal.value = false;
    } catch (error) {
        console.error(error);
        if (error.response && error.response.status === 422) {
            toast.error('Password salah!');
        } else {
            toast.error('Gagal menghapus tipe');
        }
    } finally {
        verifyingPin.value = false;
    }
};

const handleSaved = () => {
    showModal.value = false;
    fetchTypes();
};

const getCategoryLabel = (cat) => {
    if (cat === 'imei') return 'HP / Gadget';
    if (cat === 'non_imei') return 'Non-HP';
    return 'Jasa Service';
};

const getCategoryColor = (cat) => {
    if (cat === 'imei') return 'text-blue-500 bg-blue-500/10 border-blue-500/20';
    if (cat === 'non_imei') return 'text-purple-500 bg-purple-500/10 border-purple-500/20';
    return 'text-amber-500 bg-amber-500/10 border-amber-500/20';
};

const getCategoryIcon = (cat) => {
    if (cat === 'imei') return Smartphone;
    if (cat === 'non_imei') return Disc;
    return Wrench;
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

onMounted(() => {
    fetchBrands();
    fetchTypes();
});
</script>

<template>
    <div class="space-y-4 sm:space-y-6 animate-in">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
            <div>
                <h1 class="text-xl sm:text-2xl font-bold text-text-primary tracking-tight">Data Tipe Produk</h1>
                <p class="text-text-secondary text-sm mt-1">Kelola tipe dan spesifikasi produk</p>
            </div>
            <button @click="openCreateModal"
                class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2.5 rounded-xl font-medium flex items-center gap-2 transition-all shadow-lg shadow-primary-500/20 active:scale-95 text-sm">
                <Plus :size="18" />
                <span>Tambah Tipe</span>
            </button>
        </div>

        <!-- Filter -->
        <div class="bg-surface-800 rounded-2xl border border-surface-700 p-3 sm:p-4">
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary" :size="18" />
                    <input v-model="searchQuery" type="text" placeholder="Cari tipe atau brand..."
                        class="w-full bg-surface-900 border border-surface-700 rounded-xl py-2.5 pl-10 pr-4 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition-all placeholder:text-text-secondary" />
                </div>
                <!-- Brand Filter -->
                <select v-model="selectedBrand"
                    class="w-full sm:w-48 bg-surface-900 border border-surface-700 rounded-xl px-4 py-2.5 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/50 appearance-none">
                    <option value="">Semua Brand</option>
                    <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option>
                </select>
            </div>
        </div>

        <!-- Category Tabs -->
        <div class="flex gap-2 border-b border-surface-700">
            <button @click="selectedCategory = 'hp'"
                class="px-4 sm:px-6 py-3 font-medium transition-all relative text-sm"
                :class="selectedCategory === 'hp' ? 'text-primary-500' : 'text-text-secondary hover:text-text-primary'">
                HP / IMEI
                <div v-if="selectedCategory === 'hp'" class="absolute bottom-0 left-0 right-0 h-0.5 bg-primary-500">
                </div>
            </button>
            <button @click="selectedCategory = 'non-hp'"
                class="px-4 sm:px-6 py-3 font-medium transition-all relative text-sm"
                :class="selectedCategory === 'non-hp' ? 'text-primary-500' : 'text-text-secondary hover:text-text-primary'">
                NON HP
                <div v-if="selectedCategory === 'non-hp'" class="absolute bottom-0 left-0 right-0 h-0.5 bg-primary-500">
                </div>
            </button>
        </div>

        <!-- Content -->
        <div class="bg-surface-800 rounded-2xl border border-surface-700 overflow-hidden">
            <div v-if="loading" class="p-8 sm:p-12 flex justify-center items-center">
                <RefreshCw class="animate-spin text-primary-500" :size="28" />
                <span class="ml-3 text-text-secondary text-sm">Memuat data...</span>
            </div>

            <div v-else-if="types.length === 0" class="p-8 sm:p-12 text-center text-text-secondary">
                <Box :size="40" class="mx-auto mb-3 opacity-50" />
                <p class="text-sm">Belum ada data tipe produk</p>
            </div>

            <!-- Desktop Table (hidden on mobile) -->
            <div v-else class="hidden md:block overflow-x-auto">
                <table class="w-full text-sm text-left text-text-primary">
                    <thead class="bg-surface-900/50 text-text-secondary uppercase text-xs font-semibold">
                        <tr>
                            <th class="px-5 py-3">Brand</th>
                            <th class="px-5 py-3">Nama Tipe</th>
                            <th class="px-5 py-3">Kategori</th>
                            <th class="px-5 py-3">Spesifikasi</th>
                            <th class="px-5 py-3">Dibuat</th>
                            <th class="px-5 py-3">Update</th>
                            <th class="px-5 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-700/50">
                        <tr v-for="type in types" :key="type.id" class="hover:bg-surface-700/30 transition-colors">
                            <td class="px-5 py-3">
                                <span class="font-semibold text-primary-400">{{ type.brand ? type.brand.name : '-'
                                    }}</span>
                            </td>
                            <td class="px-5 py-3 font-medium">{{ type.name }}</td>
                            <td class="px-5 py-3">
                                <span
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-xs font-medium border"
                                    :class="getCategoryColor(type.category)">
                                    <component :is="getCategoryIcon(type.category)" :size="12" />
                                    {{ getCategoryLabel(type.category) }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-text-secondary">
                                <div v-if="type.category === 'imei'" class="flex gap-2 text-xs">
                                    <span v-if="type.storage" class="bg-surface-900 px-2 py-0.5 rounded">{{ type.storage
                                        }}</span>
                                    <span v-else class="italic">-</span>
                                </div>
                                <div v-else-if="type.non_imei_category" class="flex gap-2 text-xs">
                                    <span
                                        class="bg-surface-900 px-2 py-0.5 rounded text-emerald-400 border border-emerald-500/20 capitalize">{{
                                        type.non_imei_category }}</span>
                                </div>
                                <span v-else class="text-xs italic">-</span>
                            </td>
                            <td class="px-5 py-3 text-text-secondary text-xs">
                                {{ formatDate(type.created_at) }}
                            </td>
                            <td class="px-5 py-3 text-text-secondary text-xs">
                                {{ formatDate(type.updated_at) }}
                            </td>
                            <td class="px-5 py-3 text-right">
                                <div class="flex justify-end gap-1">
                                    <button @click="openEditModal(type)"
                                        class="p-2 text-blue-400 hover:bg-blue-500/10 rounded-lg transition-colors">
                                        <Edit :size="16" />
                                    </button>
                                    <button @click="openDeleteModal(type.id)"
                                        class="p-2 text-red-400 hover:bg-red-500/10 rounded-lg transition-colors">
                                        <Trash2 :size="16" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Card List (shown on mobile only) -->
            <div v-if="!loading && types.length > 0" class="md:hidden divide-y divide-surface-700/50">
                <div v-for="type in types" :key="type.id" class="p-4 hover:bg-surface-700/20 transition-colors">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <p class="text-primary-400 text-xs font-semibold mb-0.5">{{ type.brand ? type.brand.name :
                                '-' }}</p>
                            <p class="font-semibold text-text-primary text-sm truncate">{{ type.name }}</p>
                            <div class="flex flex-wrap items-center gap-2 mt-2">
                                <span
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[10px] font-medium border"
                                    :class="getCategoryColor(type.category)">
                                    <component :is="getCategoryIcon(type.category)" :size="10" />
                                    {{ getCategoryLabel(type.category) }}
                                </span>
                                <span v-if="type.category === 'imei' && type.storage"
                                    class="bg-surface-900 px-2 py-0.5 rounded text-[10px] text-text-secondary">{{
                                    type.storage }}</span>
                                <span v-else-if="type.non_imei_category"
                                    class="bg-surface-900 px-2 py-0.5 rounded text-[10px] text-emerald-400 border border-emerald-500/20 capitalize">{{
                                    type.non_imei_category }}</span>
                            </div>
                        </div>
                        <div class="flex gap-1 shrink-0">
                            <button @click="openEditModal(type)"
                                class="p-2 text-blue-400 hover:bg-blue-500/10 rounded-lg transition-colors">
                                <Edit :size="16" />
                            </button>
                            <button @click="openDeleteModal(type.id)"
                                class="p-2 text-red-400 hover:bg-red-500/10 rounded-lg transition-colors">
                                <Trash2 :size="16" />
                            </button>
                        </div>
                    </div>
                    <p class="text-[10px] text-text-secondary mt-2">Dibuat: {{ formatDate(type.created_at) }}</p>
                </div>
            </div>
        </div>

        <TypeModal :show="showModal" :type="editingType" @close="showModal = false" @saved="handleSaved" />

        <!-- Delete Confirmation Modal -->
        <div v-if="showDeleteModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm animate-in">
            <div class="bg-surface-800 border border-surface-700 rounded-2xl w-full max-w-md p-6 shadow-xl slide-in">
                <h3 class="text-lg font-bold text-text-primary mb-2">Konfirmasi Hapus</h3>
                <p class="text-text-secondary text-sm mb-5">
                    Masukkan Password Anda untuk melanjutkan penghapusan tipe ini.
                </p>
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-text-secondary uppercase mb-1">Password</label>
                        <input v-model="deletePin" type="password" placeholder="Masukkan password anda"
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
