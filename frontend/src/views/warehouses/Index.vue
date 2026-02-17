<script setup>
import { ref, onMounted, computed } from 'vue';
import {
    Warehouse,
    Search,
    Plus,
    MapPin,
    Clock,
    Edit,
    Trash2,
    CheckCircle,
    XCircle,
    RotateCcw
} from 'lucide-vue-next';
import { warehouses as api } from '../../api/axios';
import WarehouseModal from './WarehouseModal.vue';
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
        const response = await api.list(); // No filter needed anymore
        branchesList.value = response.data.data;
    } catch (error) {
        toast.error("Gagal memuat data gudang");
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
    if (!confirm('Hapus gudang ini?')) return;
    try {
        await api.delete(id);
        toast.success('Gudang berhasil dihapus');
        branchesList.value = branchesList.value.filter(b => b.id !== id);
    } catch (error) {
        toast.error('Gagal menghapus gudang');
    }
};

const handleSaved = () => {
    showModal.value = false;
    fetchBranches();
};

const togglingReturn = ref({});
const handleToggleReturn = async (branch) => {
    if (togglingReturn.value[branch.id]) return;
    togglingReturn.value[branch.id] = true;
    try {
        await api.toggleReturn(branch.id);
        toast.success(`Status retur ${branch.name} berhasil diubah`);
        fetchBranches();
    } catch (error) {
        toast.error('Gagal mengubah status retur');
    } finally {
        togglingReturn.value[branch.id] = false;
    }
};
</script>

<template>
    <!-- Template remains mostly same, just ensure modal prop is :warehouse instead of :branch -->
    <div class="space-y-6 animate-in fade-in duration-500">
        <!-- ... header ... -->
        <!-- (Using same template structure, just updating script imports and API calls first. 
              Actually I should rewrite template to fix prop passing to Modal) -->

        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-white tracking-tight flex items-center gap-3">
                    <Warehouse class="text-primary-500" :size="28" />
                    Data Gudang
                </h1>
                <p class="text-text-secondary mt-1">Kelola data gudang penyimpanan fisik</p>
            </div>

            <button @click="openCreateModal" class="btn btn-primary shadow-lg shadow-primary-500/20">
                <Plus :size="20" />
                <span>Tambah Gudang</span>
            </button>
        </div>

        <div class="bg-surface-800 rounded-2xl border border-surface-700 p-4 sticky top-4 z-10 shadow-xl">
            <div class="relative">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary" :size="20" />
                <input v-model="searchQuery" type="text" placeholder="Cari gudang..."
                    class="w-full bg-surface-900 border border-surface-700 rounded-xl px-4 py-2 pl-10 text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition-all placeholder:text-surface-600" />
            </div>
        </div>

        <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div v-for="i in 3" :key="i"
                class="h-48 bg-surface-800 rounded-2xl animate-pulse border border-surface-700"></div>
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div v-for="branch in handleSearch" :key="branch.id"
                class="group bg-surface-800 rounded-2xl border border-surface-700 p-5 hover:border-primary-500/50 transition-all duration-300 relative overflow-hidden">
                <!-- Render content -->
                <div class="flex justify-between items-start mb-4">
                    <div class="flex items-center gap-3">
                        <div
                            class="w-12 h-12 rounded-xl bg-surface-900 border border-surface-700 flex items-center justify-center text-primary-500 font-bold text-lg shadow-inner">
                            <Warehouse :size="24" />
                        </div>
                        <div>
                            <h3 class="font-bold text-white text-lg leading-tight">{{ branch.name }}</h3>
                            <p
                                class="text-xs font-mono text-text-secondary bg-surface-900 px-1.5 py-0.5 rounded inline-block mt-1">
                                {{ branch.code }}
                            </p>
                        </div>
                    </div>
                    <div class="flex gap-1">
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

                <div class="space-y-3 mb-4">
                    <div class="flex items-start gap-2.5 text-sm text-text-secondary">
                        <MapPin :size="16" class="mt-0.5 shrink-0" />
                        <span class="line-clamp-2">{{ branch.address || 'Alamat belum diatur' }}</span>
                    </div>
                </div>

                <div class="pt-4 border-t border-surface-700 flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium border"
                            :class="branch.is_active ? 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20' : 'bg-red-500/10 text-red-500 border-red-500/20'">
                            <component :is="branch.is_active ? CheckCircle : XCircle" :size="12" />
                            {{ branch.is_active ? 'Aktif' : 'Nonaktif' }}
                        </span>

                        <span v-if="branch.can_accept_returns"
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium border bg-blue-500/10 text-blue-400 border-blue-500/20">
                            <RotateCcw :size="12" />
                            Retur ON
                        </span>
                    </div>

                    <button @click="handleToggleReturn(branch)" :disabled="togglingReturn[branch.id]"
                        class="text-xs font-medium transition-colors"
                        :class="branch.can_accept_returns ? 'text-emerald-500 hover:text-emerald-400' : 'text-surface-400 hover:text-text-primary'">
                        {{
                            togglingReturn[branch.id]
                                ? '...'
                                : (branch.can_accept_returns ? 'Matikan Retur' : 'Aktifkan Retur')
                        }}
                    </button>
                </div>
            </div>
        </div>

        <WarehouseModal :show="showModal" :warehouse="selectedBranch" @close="showModal = false" @saved="handleSaved" />
    </div>
</template>

<style scoped>
/* Tambahkan satu ../ lagi agar jalurnya pas ke folder assets */
@reference "../../style.css";

.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: theme('colors.surface.700');
    border-radius: 9999px;
}
</style>