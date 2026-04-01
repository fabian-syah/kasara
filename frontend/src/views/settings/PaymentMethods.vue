<template>
    <div class="p-6 h-full flex flex-col animate-in fade-in duration-500">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-text-primary tracking-tight">Pengaturan Pembayaran</h1>
                <p class="text-text-secondary text-sm mt-1">Kelola metode pembayaran untuk transaksi</p>
            </div>
            <button @click="openModal()" class="btn btn-primary flex items-center gap-2 px-6 h-12 rounded-2xl font-bold shadow-lg shadow-blue-500/20 active:scale-95 transition-all">
                <PlusIcon :size="20" />
                Tambah Metode
            </button>
        </div>

        <!-- Table -->
        <div class="card overflow-hidden border border-surface-700 p-0 shadow-xl">
            <table class="table min-w-full">
                <thead>
                    <tr class="bg-surface-900/50">
                        <th class="px-6 py-4 text-left text-xs font-black uppercase tracking-widest text-text-secondary">Nama Bank / Metode</th>
                        <th class="px-6 py-4 text-left text-xs font-black uppercase tracking-widest text-text-secondary">Nomor Rekening</th>
                        <th class="px-6 py-4 text-left text-xs font-black uppercase tracking-widest text-text-secondary">Atas Nama</th>
                        <th class="px-6 py-4 text-left text-xs font-black uppercase tracking-widest text-text-secondary">Kategori</th>
                        <th class="px-6 py-4 text-left text-xs font-black uppercase tracking-widest text-text-secondary">Status</th>
                        <th class="px-6 py-4 text-right text-xs font-black uppercase tracking-widest text-text-secondary">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-surface-700/50">
                    <tr v-if="isLoadingData" class="animate-pulse">
                         <td colspan="6" class="px-6 py-12 text-center text-text-secondary">
                             <Loader2 class="animate-spin mx-auto mb-2 text-primary-500" :size="32" />
                             Memuat data...
                         </td>
                    </tr>
                    <tr v-else v-for="pm in paymentMethods" :key="pm.id" class="hover:bg-surface-700/30 transition-colors">
                        <td class="px-6 py-4 font-bold text-text-primary">{{ pm.name }}</td>
                        <td class="px-6 py-4 text-text-secondary font-mono text-xs">{{ pm.account_number || '-' }}</td>
                        <td class="px-6 py-4 text-text-secondary text-sm">{{ pm.account_name || '-' }}</td>
                        <td class="px-6 py-4">
                            <span v-if="pm.category === 'cash'" class="px-2 py-1 rounded-lg text-[10px] font-black uppercase tracking-tighter bg-emerald-500/10 text-emerald-500 border border-emerald-500/20">Tunai</span>
                            <span v-else-if="pm.category === 'edc'" class="px-2 py-1 rounded-lg text-[10px] font-black uppercase tracking-tighter bg-amber-500/10 text-amber-500 border border-amber-500/20">EDC</span>
                            <span v-else class="px-2 py-1 rounded-lg text-[10px] font-black uppercase tracking-tighter bg-blue-500/10 text-blue-400 border border-blue-500/20">Transfer</span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-2">
                                <button @click="toggleStatus(pm)" :disabled="isProcessingId === pm.id"
                                    class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 focus:ring-offset-surface-900 disabled:opacity-50 disabled:cursor-not-allowed"
                                    :class="pm.is_active ? 'bg-emerald-500' : 'bg-surface-600'">
                                    <span class="sr-only">Toggle status</span>
                                    <span class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                        :class="pm.is_active ? 'translate-x-4' : 'translate-x-0'">
                                        <Loader2 v-if="isProcessingId === pm.id" class="animate-spin text-primary-500 w-3 h-3 m-0.5" />
                                    </span>
                                </button>
                                <span class="text-xs font-bold" :class="pm.is_active ? 'text-emerald-400' : 'text-text-secondary'">
                                    {{ pm.is_active ? 'Aktif' : 'Non-Aktif' }}
                                </span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-right">
                             <div class="flex justify-end gap-1">
                                <button @click="openModal(pm)" :disabled="isProcessingId === pm.id"
                                    class="p-2 text-primary-400 hover:bg-primary-500/10 rounded-lg transition-all disabled:opacity-30" title="Edit">
                                    <Edit2 :size="16" />
                                </button>
                                <button @click="deletePm(pm.id)" :disabled="isProcessingId === pm.id"
                                    class="p-2 text-red-500 hover:bg-red-500/10 rounded-lg transition-all disabled:opacity-30" title="Hapus">
                                    <Loader2 v-if="isDeletingId === pm.id" :size="16" class="animate-spin" />
                                    <Trash2 v-else :size="16" />
                                </button>
                             </div>
                        </td>
                    </tr>
                    <tr v-if="!isLoadingData && paymentMethods.length === 0">
                        <td colspan="6" class="px-6 py-12 text-center text-text-secondary">
                             <div class="max-w-[200px] mx-auto opacity-50 space-y-2">
                                 <PlusCircle :size="48" class="mx-auto" />
                                 <p class="text-sm font-medium">Belum ada metode pembayaran yang dikonfigurasi.</p>
                             </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Modal -->
        <Teleport to="body">
            <div v-if="isModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/80 backdrop-blur-md animate-in fade-in duration-300" aria-hidden="true" @click="!isSaving && closeModal()"></div>

                <!-- Modal Panel -->
                <div
                    class="relative bg-surface-900 rounded-[2.5rem] border border-surface-700/50 w-full max-w-lg shadow-2xl overflow-hidden animate-in zoom-in-95 duration-300">
                    <div class="px-8 pt-8 pb-4 flex justify-between items-center">
                        <h3 class="text-2xl font-black text-white tracking-tight">
                            {{ isEditing ? 'Edit Metode' : 'Tambah Metode' }}
                        </h3>
                        <button @click="closeModal" class="p-2 hover:bg-surface-700 rounded-full transition-colors" :disabled="isSaving">
                            <X :size="24" />
                        </button>
                    </div>

                    <form @submit.prevent="save" class="p-8 pt-4 space-y-6">
                        <div class="grid grid-cols-1 gap-6">
                            <!-- Nama Bank / Metode -->
                            <div class="space-y-2">
                                <label class="block text-[10px] font-black uppercase tracking-widest text-text-secondary ml-1">Nama Bank / Metode</label>
                                <input v-model="form.name" type="text" class="input h-14 bg-surface-800 border-surface-700/50 rounded-2xl focus:ring-primary-500/20"
                                    placeholder="Contoh: BCA, Mandiri, Tunai" required>
                            </div>

                            <!-- Kategori Select -->
                            <div class="space-y-2">
                                <label class="block text-[10px] font-black uppercase tracking-widest text-text-secondary ml-1">Kategori</label>
                                <div class="grid grid-cols-3 gap-2">
                                    <button v-for="cat in [
                                        {id: 'cash', label: 'Tunai', icon: Banknote},
                                        {id: 'edc', label: 'EDC', icon: CreditCard},
                                        {id: 'transfer', label: 'Transfer', icon: Landmark}
                                    ]" :key="cat.id" type="button" @click="form.category = cat.id"
                                    class="flex flex-col items-center justify-center gap-2 p-4 rounded-2xl border-2 transition-all"
                                    :class="form.category === cat.id ? 'bg-primary-500/10 border-primary-500 text-white' : 'bg-surface-800 border-surface-700/50 text-text-secondary hover:border-surface-600'">
                                        <component :is="cat.icon" :size="20" />
                                        <span class="text-xs font-bold">{{ cat.label }}</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Nomor Rekening (if not cash) -->
                            <div v-if="form.category !== 'cash'"
                                class="space-y-2 animate-in fade-in slide-in-from-top-2 duration-300">
                                <label class="block text-[10px] font-black uppercase tracking-widest text-text-secondary ml-1">Nomor Rekening</label>
                                <input v-model="form.account_number" type="text" class="input h-14 bg-surface-800 border-surface-700/50 rounded-2xl focus:ring-primary-500/20"
                                    placeholder="Masukkan No. Rekening">
                            </div>

                            <!-- Atas Nama (if not cash) -->
                            <div v-if="form.category !== 'cash'"
                                class="space-y-2 animate-in fade-in slide-in-from-top-2 duration-300">
                                <label class="block text-[10px] font-black uppercase tracking-widest text-text-secondary ml-1">Atas Nama</label>
                                <input v-model="form.account_name" type="text" class="input h-14 bg-surface-800 border-surface-700/50 rounded-2xl focus:ring-primary-500/20" 
                                    placeholder="Nama Pemilik Rekening">
                            </div>

                            <!-- Status Toggle -->
                            <div class="flex items-center justify-between p-4 bg-surface-800 rounded-2xl border border-surface-700/50">
                                <div class="flex items-center gap-3">
                                    <div class="p-2 bg-emerald-500/10 rounded-lg">
                                        <CheckCircle2 :size="18" class="text-emerald-500" />
                                    </div>
                                    <div>
                                        <p class="text-sm font-bold text-white">Status Aktif</p>
                                        <p class="text-[10px] text-text-secondary">Metode muncul di menu penjualan</p>
                                    </div>
                                </div>
                                <button type="button" @click="form.is_active = !form.is_active"
                                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none"
                                    :class="form.is_active ? 'bg-emerald-500' : 'bg-surface-600'">
                                    <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                        :class="form.is_active ? 'translate-x-5' : 'translate-x-0'" />
                                </button>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex gap-3 pt-4">
                            <button @click="closeModal" type="button" :disabled="isSaving"
                                class="flex-1 h-14 rounded-2xl font-bold text-text-secondary hover:bg-surface-800 transition-colors">
                                Batal
                            </button>
                            <button type="submit" :disabled="isSaving"
                                class="flex-[2] h-14 bg-primary-600 hover:bg-primary-500 disabled:opacity-50 text-white rounded-2xl font-black text-lg shadow-lg shadow-primary-500/20 active:scale-95 transition-all flex items-center justify-center gap-2">
                                <Loader2 v-if="isSaving" class="animate-spin" :size="20" />
                                <Save v-else :size="20" />
                                <span>{{ isSaving ? 'Memproses...' : (isEditing ? 'Simpan Perubahan' : 'Tambah Metode') }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { 
    PlusIcon, Loader2, Edit2, Trash2, X, Save, 
    Banknote, CreditCard, Landmark, CheckCircle2, 
    PlusCircle 
} from 'lucide-vue-next';
import axios from '../../api/axios';
import { useEscapeKey } from '../../composables/useEscapeKey';
import { useToast } from '../../composables/useToast';

const toast = useToast();
const paymentMethods = ref([]);
const isModalOpen = ref(false);
const isEditing = ref(false);
const isLoadingData = ref(false);
const isSaving = ref(false);
const isProcessingId = ref(null);
const isDeletingId = ref(null);

const form = ref({
    id: null,
    name: '',
    account_number: '',
    account_name: '',
    category: 'transfer',
    is_active: true
});

const loadData = async (silent = false) => {
    if (!silent) isLoadingData.value = true;
    try {
        const res = await axios.get('/payment-methods');
        paymentMethods.value = res.data;
    } catch (e) {
        console.error("Failed to load payment methods", e);
        toast.error("Gagal memuat data metode pembayaran");
    } finally {
        isLoadingData.value = false;
    }
};

const openModal = (pm = null) => {
    if (pm) {
        isEditing.value = true;
        form.value = {
            ...pm,
            is_active: Boolean(pm.is_active)
        };
    } else {
        isEditing.value = false;
        form.value = {
            id: null,
            name: '',
            account_number: '',
            account_name: '',
            category: 'transfer',
            is_active: true
        };
    }
    isModalOpen.value = true;
};

const closeModal = () => {
    if (isSaving.value) return;
    isModalOpen.value = false;
};

useEscapeKey(() => {
    if (isModalOpen.value) closeModal();
});

const save = async () => {
    if (!form.value.name) {
        toast.warning('Nama metode harus diisi');
        return;
    }

    isSaving.value = true;
    try {
        if (isEditing.value) {
            await axios.put(`/payment-methods/${form.value.id}`, form.value);
            toast.success("Metode pembayaran berhasil diperbarui");
        } else {
            await axios.post('/payment-methods', form.value);
            toast.success("Metode pembayaran baru telah ditambahkan");
        }
        closeModal();
        loadData(true);
    } catch (e) {
        console.error(e);
        toast.error(e.response?.data?.message || "Gagal menyimpan perubahan");
    } finally {
        isSaving.value = false;
    }
};

const deletePm = async (id) => {
    if (!confirm('Apakah anda yakin ingin menghapus metode ini?')) return;
    
    isDeletingId.value = id;
    try {
        await axios.delete(`/payment-methods/${id}`);
        toast.success("Metode pembayaran telah dihapus");
        loadData(true);
    } catch (e) {
        console.error(e);
        toast.error(e.response?.data?.message || "Gagal menghapus metode");
    } finally {
        isDeletingId.value = null;
    }
};

const toggleStatus = async (pm) => {
    if (isProcessingId.value === pm.id) return;

    isProcessingId.value = pm.id;
    try {
        const newVal = !pm.is_active;
        await axios.put(`/payment-methods/${pm.id}`, { 
            name: pm.name,
            category: pm.category,
            account_number: pm.account_number,
            account_name: pm.account_name,
            is_active: newVal 
        });
        pm.is_active = newVal;
        toast.info(`Metode ${pm.name} kini ${newVal ? 'Aktif' : 'Non-Aktif'}`);
    } catch (e) {
        console.error(e);
        toast.error('Gagal mengubah status');
    } finally {
        isProcessingId.value = null;
    }
}

onMounted(() => {
    loadData();
});
</script>

<style scoped>
@reference "../../style.css";

.input {
    @apply w-full bg-surface-900 border-surface-700/50 text-white px-4 py-3 rounded-xl focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all placeholder:text-text-secondary/50;
}
</style>
