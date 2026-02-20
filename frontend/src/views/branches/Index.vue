<script setup>
import { ref, onMounted, computed } from 'vue';
import {
    Building2,
    Search,
    Plus,
    MapPin,
    Clock,
    MoreVertical,
    Edit,
    Trash2,
    CheckCircle,
    XCircle,
    RotateCcw
} from 'lucide-vue-next';
import { branches as api } from '../../api/axios';
import BranchModal from './BranchModal.vue';
import { useToast } from '../../composables/useToast';

const toast = useToast();
const branchesList = ref([]);
const loading = ref(false);
const searchQuery = ref('');
const showModal = ref(false);
const selectedBranch = ref(null);

const fetchBranches = async () => {
    loading.value = true;
    try {
        const response = await api.list({ type: 'physical' });
        branchesList.value = response.data.data.filter(b => b.type === 'physical' || !b.type); // Default to physical if null
    } catch (error) {
        console.error("Failed to fetch branches", error);
        toast.error("Gagal memuat data cabang");
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchBranches();
});

const handleSearch = computed(() => {
    if (!searchQuery.value) return branchesList.value;
    const lower = searchQuery.value.toLowerCase();
    return branchesList.value.filter(b =>
        b.name.toLowerCase().includes(lower) ||
        b.code.toLowerCase().includes(lower) ||
        (b.address && b.address.toLowerCase().includes(lower))
    );
});

const openCreateModal = () => {
    selectedBranch.value = null;
    showModal.value = true;
};

const openEditModal = (branch) => {
    selectedBranch.value = branch;
    showModal.value = true;
};

const handleDelete = async (id) => {
    if (!confirm('Apakah Anda yakin ingin menghapus cabang ini?')) return;
    try {
        await api.delete(id);
        toast.success('Cabang berhasil dihapus');
        branchesList.value = branchesList.value.filter(b => b.id !== id);
    } catch (error) {
        console.error(error);
        toast.error('Gagal menghapus cabang');
    }
};

const handleSaved = () => {
    showModal.value = false;
    fetchBranches();
};

const togglingStatus = ref({});

const handleToggleStatus = async (branch) => {
    if (togglingStatus.value[branch.id]) return;
    togglingStatus.value[branch.id] = true;
    try {
        await api.toggleStatus(branch.id);
        toast.success(`Status aktif ${branch.name} berhasil diubah`);
        fetchBranches();
    } catch (error) {
        toast.error('Gagal mengubah status aktif');
    } finally {
        togglingStatus.value[branch.id] = false;
    }
};
</script>

<template>
    <div class="space-y-6 animate-in fade-in duration-500 pb-20 lg:pb-0">
        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-white tracking-tight flex items-center gap-3">
                    <Building2 class="text-primary-500" :size="28" />
                    Cabang Fisik
                </h1>
                <p class="text-text-secondary mt-1">Kelola data warehouse dan toko fisik</p>
            </div>

            <button @click="openCreateModal"
                class="btn btn-primary shadow-lg shadow-primary-500/20 w-full sm:w-auto justify-center">
                <Plus :size="20" />
                <span>Tambah Cabang</span>
            </button>
        </div>

        <!-- Filters -->
        <div class="bg-surface-800 rounded-2xl border border-surface-700 p-4 sticky top-4 z-10 shadow-xl">
            <div class="relative">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary" :size="20" />
                <input v-model="searchQuery" type="text" placeholder="Cari nama cabang, kode, atau alamat..."
                    class="w-full bg-surface-900 border border-surface-700 rounded-xl pl-10 pr-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition-all placeholder:text-surface-600" />
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <div v-for="i in 6" :key="i"
                class="h-48 bg-surface-800 rounded-2xl animate-pulse border border-surface-700"></div>
        </div>

        <!-- Empty State -->
        <div v-else-if="branchesList.length === 0" class="text-center py-20 px-4">
            <div class="w-20 h-20 bg-surface-800 rounded-full flex items-center justify-center mx-auto mb-4">
                <Building2 class="text-surface-600" :size="40" />
            </div>
            <h3 class="text-lg font-medium text-white mb-1">Belum ada cabang</h3>
            <p class="text-text-secondary max-w-sm mx-auto">
                Silakan tambahkan cabang fisik baru untuk mulai mengelola inventory dan penjualan offline.
            </p>
            <button @click="openCreateModal" class="mt-6 text-primary-500 hover:text-primary-400 font-medium">
                + Tambah Cabang Sekarang
            </button>
        </div>

        <!-- Branch Grid -->
        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <div v-for="branch in handleSearch" :key="branch.id"
                class="group bg-surface-800 rounded-2xl border border-surface-700 p-5 hover:border-primary-500/50 transition-all duration-300 hover:shadow-xl hover:shadow-primary-500/5 relative overflow-hidden flex flex-col justify-between">
                <div>
                    <div class="flex justify-between items-start mb-4">
                        <div class="flex items-center gap-3">
                            <div
                                class="w-12 h-12 rounded-xl bg-surface-900 border border-surface-700 flex items-center justify-center text-primary-500 font-bold text-lg shadow-inner shrink-0">
                                {{ branch.code.substring(0, 2).toUpperCase() }}
                            </div>
                            <div class="min-w-0">
                                <h3
                                    class="font-bold text-white text-lg leading-tight group-hover:text-primary-500 transition-colors truncate">
                                    {{ branch.name }}
                                </h3>
                                <p
                                    class="text-xs font-mono text-text-secondary bg-surface-900 px-1.5 py-0.5 rounded inline-block mt-1">
                                    {{ branch.code }}
                                </p>
                            </div>
                        </div>
                        <div class="flex gap-1 lg:opacity-0 group-hover:opacity-100 transition-opacity">
                            <button @click="openEditModal(branch)"
                                class="p-2 hover:bg-surface-700 rounded-lg text-blue-400 transition-colors">
                                <Edit :size="18" />
                            </button>
                            <button @click="handleDelete(branch.id)"
                                class="p-2 hover:bg-surface-700 rounded-lg text-red-400 transition-colors">
                                <Trash2 :size="18" />
                            </button>
                        </div>
                    </div>

                    <div class="space-y-3 mb-6">
                        <div class="flex items-start gap-2.5 text-sm text-text-secondary">
                            <MapPin :size="16" class="mt-0.5 shrink-0" />
                            <span class="line-clamp-2">{{ branch.address || 'Alamat belum diatur' }}</span>
                        </div>
                        <div class="flex items-center gap-2.5 text-sm text-text-secondary">
                            <Clock :size="16" />
                            <span>{{ branch.timezone || 'WIB' }}</span>
                        </div>
                    </div>
                </div>

                <div class="pt-4 border-t border-surface-700 flex justify-between items-center gap-2">
                    <span
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium border shrink-0"
                        :class="branch.is_active ? 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20' : 'bg-red-500/10 text-red-500 border-red-500/20'">
                        <component :is="branch.is_active ? CheckCircle : XCircle" :size="12" />
                        {{ branch.is_active ? 'Aktif' : 'Nonaktif' }}
                    </span>

                    <button @click="handleToggleStatus(branch)" :disabled="togglingStatus[branch.id]"
                        class="text-xs font-medium transition-all px-3 py-1.5 rounded-lg border border-transparent hover:border-current"
                        :class="branch.is_active ? 'text-red-400 bg-red-400/5 hover:bg-red-400/10' : 'text-emerald-500 bg-emerald-500/5 hover:bg-emerald-500/10'">
                        {{ togglingStatus[branch.id] ? '...' : (branch.is_active ? 'Set Nonaktif' : 'Set Aktif') }}
                    </button>
                </div>
            </div>
        </div>

        <BranchModal :show="showModal" :branch="selectedBranch" :type="'physical'" @close="showModal = false"
            @saved="handleSaved" />
    </div>
</template>
