<template>
    <div class="p-6 h-full flex flex-col">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-text-primary">Pengaturan Pembayaran</h1>
            <button @click="openModal()" class="btn btn-primary flex items-center gap-2">
                <component :is="plusIcon" class="w-5 h-5" />
                Tambah Metode
            </button>
        </div>

        <!-- Table -->
        <div class="card overflow-hidden border border-surface-700 p-0">
            <table class="table min-w-full">
                <thead>
                    <tr>
                        <th class="px-6 py-3">Nama Bank / Metode</th>
                        <th class="px-6 py-3">Nomor Rekening</th>
                        <th class="px-6 py-3">Atas Nama</th>
                        <th class="px-6 py-3">Kategori</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-surface-700">
                    <tr v-for="pm in paymentMethods" :key="pm.id" class="hover:bg-surface-800/50 transition-colors">
                        <td class="px-6 py-4 font-medium text-text-primary">{{ pm.name }}</td>
                        <td class="px-6 py-4 text-text-secondary">{{ pm.account_number || '-' }}</td>
                        <td class="px-6 py-4 text-text-secondary">{{ pm.account_name || '-' }}</td>
                        <td class="px-6 py-4">
                            <span v-if="pm.category === 'cash'" class="badge badge-success">Tunai</span>
                            <span v-else-if="pm.category === 'edc'" class="badge badge-warning">EDC</span>
                            <span v-else class="badge badge-info text-blue-400">Transfer</span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="badge cursor-pointer hover:opacity-80 transition-opacity"
                                :class="pm.is_active ? 'badge-success' : 'badge-danger'" @click="toggleStatus(pm)">
                                {{ pm.is_active ? 'Aktif' : 'Non-Aktif' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right space-x-2">
                            <button @click="openModal(pm)"
                                class="text-primary-500 hover:text-primary-600 font-medium transition-colors">Edit</button>
                            <button @click="deletePm(pm.id)"
                                class="text-red-500 hover:text-red-600 font-medium transition-colors">Hapus</button>
                        </td>
                    </tr>
                    <tr v-if="paymentMethods.length === 0">
                        <td colspan="6" class="px-6 py-8 text-center text-text-secondary">Belum ada metode pembayaran.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Modal -->
        <Teleport to="body">
            <div v-if="isModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" aria-hidden="true" @click="closeModal"></div>

                <!-- Modal Panel -->
                <div
                    class="relative bg-surface-800 rounded-2xl border border-surface-700 w-full max-w-lg shadow-2xl overflow-hidden animate-in zoom-in duration-200">
                    <div class="bg-surface-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4 border-b border-surface-700">
                        <h3 class="text-lg leading-6 font-bold text-text-primary" id="modal-title">
                            {{ isEditing ? 'Edit Metode Pembayaran' : 'Tambah Metode Pembayaran' }}
                        </h3>
                    </div>

                    <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
                        <!-- Nama Bank / Metode -->
                        <div>
                            <label class="block text-sm font-medium text-text-secondary mb-1">Nama Bank /
                                Metode</label>
                            <input v-model="form.name" type="text" class="input"
                                placeholder="Contoh: BCA, Mandiri, Cash">
                        </div>

                        <!-- Kategori Select -->
                        <div>
                            <label class="block text-sm font-medium text-text-secondary mb-1">Kategori</label>
                            <select v-model="form.category" class="input">
                                <option value="cash">Tunai (Cash)</option>
                                <option value="edc">EDC (Edisi)</option>
                                <option value="transfer">Transfer</option>
                            </select>
                        </div>

                        <!-- Nomor Rekening (if not cash) -->
                        <div v-if="form.category !== 'cash'"
                            class="animate-in fade-in slide-in-from-top-2 duration-200">
                            <label class="block text-sm font-medium text-text-secondary mb-1">Nomor Rekening</label>
                            <input v-model="form.account_number" type="text" class="input"
                                placeholder="Contoh: 1234567890">
                        </div>

                        <!-- Atas Nama (if not cash) -->
                        <div v-if="form.category !== 'cash'"
                            class="animate-in fade-in slide-in-from-top-2 duration-200">
                            <label class="block text-sm font-medium text-text-secondary mb-1">Atas Nama</label>
                            <input v-model="form.account_name" type="text" class="input" placeholder="Contoh: John Doe">
                        </div>

                        <!-- Checkbox Active -->
                        <div class="flex items-center">
                            <input id="is_active" v-model="form.is_active" type="checkbox"
                                class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-surface-600 rounded bg-surface-900">
                            <label for="is_active"
                                class="ml-2 block text-sm text-text-primary select-none cursor-pointer">
                                Status Aktif
                            </label>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div
                        class="bg-surface-800/50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse border-t border-surface-700">
                        <button @click="save" type="button" class="btn btn-primary w-full sm:w-auto sm:ml-3">
                            {{ isEditing ? 'Simpan Perubahan' : 'Simpan' }}
                        </button>
                        <button @click="closeModal" type="button"
                            class="btn btn-secondary mt-3 w-full sm:mt-0 sm:w-auto">
                            Batal
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { PlusIcon } from 'lucide-vue-next';
import axios from '../../api/axios';
import { useEscapeKey } from '../../composables/useEscapeKey';

const plusIcon = PlusIcon;
const paymentMethods = ref([]);
const isModalOpen = ref(false);
const isEditing = ref(false);
const form = ref({
    id: null,
    name: '',
    account_number: '',
    account_name: '',
    category: 'transfer',
    is_active: true
});

const loadData = async () => {
    try {
        const res = await axios.get('/payment-methods');
        paymentMethods.value = res.data;
    } catch (e) {
        console.error("Failed to load payment methods", e);
    }
};

const openModal = (pm = null) => {
    isModalOpen.value = true;
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
};

const closeModal = () => {
    isModalOpen.value = false;
    setTimeout(() => {
        form.value = { id: null, name: '', account_number: '', account_name: '', category: 'transfer', is_active: true };
    }, 200);
};

useEscapeKey(() => {
    if (isModalOpen.value) closeModal();
});

const save = async () => {
    try {
        if (!form.value.name) {
            alert('Nama metode harus diisi');
            return;
        }

        if (isEditing.value) {
            await axios.put(`/payment-methods/${form.value.id}`, form.value);
        } else {
            await axios.post('/payment-methods', form.value);
        }
        closeModal();
        loadData();
    } catch (e) {
        console.error(e);
        alert('Gagal menyimpan: ' + (e.response?.data?.message || e.message));
    }
};

const deletePm = async (id) => {
    if (!confirm('Apakah anda yakin ingin menghapus metode ini?')) return;
    try {
        await axios.delete(`/payment-methods/${id}`);
        loadData();
    } catch (e) {
        console.error(e);
        alert('Gagal menghapus: ' + (e.response?.data?.message || e.message));
    }
};

const toggleStatus = async (pm) => {
    try {
        const newVal = !pm.is_active;
        pm.is_active = newVal;
        await axios.put(`/payment-methods/${pm.id}`, { ...pm, is_active: newVal });
    } catch (e) {
        console.error(e);
        pm.is_active = !pm.is_active;
        alert('Gagal mengubah status');
    }
}

onMounted(() => {
    loadData();
});
</script>
