<template>
    <div class="p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-text-primary">Pengaturan Pembayaran</h1>
            <button @click="openModal()"
                class="bg-primary-600 text-white px-4 py-2 rounded-lg hover:bg-primary-700 flex items-center gap-2">
                <component :is="plusIcon" class="w-5 h-5" />
                Tambah Metode
            </button>
        </div>

        <!-- Table -->
        <div class="bg-surface-800 rounded-xl shadow overflow-hidden border border-surface-700">
            <table class="min-w-full divide-y divide-surface-700">
                <thead class="bg-surface-700">
                    <tr>
                        <th
                            class="px-6 py-3 text-left text-xs font-medium text-text-secondary uppercase tracking-wider">
                            Nama
                            Bank / Metode</th>
                        <th
                            class="px-6 py-3 text-left text-xs font-medium text-text-secondary uppercase tracking-wider">
                            Nomor
                            Rekening</th>
                        <th
                            class="px-6 py-3 text-left text-xs font-medium text-text-secondary uppercase tracking-wider">
                            Atas
                            Nama</th>
                        <th
                            class="px-6 py-3 text-left text-xs font-medium text-text-secondary uppercase tracking-wider">
                            Tipe
                        </th>
                        <th
                            class="px-6 py-3 text-left text-xs font-medium text-text-secondary uppercase tracking-wider">
                            Status</th>
                        <th
                            class="px-6 py-3 text-right text-xs font-medium text-text-secondary uppercase tracking-wider">
                            Aksi
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-surface-800 divide-y divide-surface-700">
                    <tr v-for="pm in paymentMethods" :key="pm.id">
                        <td class="px-6 py-4 whitespace-nowrap font-medium text-text-primary">{{ pm.name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-text-secondary">{{ pm.account_number || '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-text-secondary">{{ pm.account_name || '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                :class="pm.is_cash ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'">
                                {{ pm.is_cash ? 'Tunai' : 'Transfer' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full cursor-pointer"
                                :class="pm.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                                @click="toggleStatus(pm)">
                                {{ pm.is_active ? 'Aktif' : 'Non-Aktif' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <button @click="openModal(pm)"
                                class="text-primary-600 hover:text-primary-900 mr-4">Edit</button>
                            <button @click="deletePm(pm.id)" class="text-red-600 hover:text-red-900">Hapus</button>
                        </td>
                    </tr>
                    <tr v-if="paymentMethods.length === 0">
                        <td colspan="6" class="px-6 py-4 text-center text-text-secondary">Belum ada metode pembayaran.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Modal -->
        <div v-if="isModalOpen" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog"
            aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-surface-900 bg-opacity-75 transition-opacity" aria-hidden="true"
                    @click="closeModal"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div
                    class="inline-block align-bottom bg-surface-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-surface-700">
                    <div class="bg-surface-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg leading-6 font-medium text-text-primary" id="modal-title">
                            {{ isEditing ? 'Edit Metode Pembayaran' : 'Tambah Metode Pembayaran' }}
                        </h3>
                        <div class="mt-4 space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-text-secondary">Nama Bank / Metode</label>
                                <input v-model="form.name" type="text"
                                    class="mt-1 block w-full bg-surface-900 border-surface-600 text-text-primary rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm"
                                    placeholder="Contoh: BCA, Mandiri, Cash">
                            </div>

                            <div class="flex items-center">
                                <input id="is_cash" v-model="form.is_cash" type="checkbox"
                                    class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-surface-600 rounded bg-surface-900">
                                <label for="is_cash" class="ml-2 block text-sm text-text-primary">
                                    Ini adalah metode Tunai (Cash)
                                </label>
                            </div>

                            <div v-if="!form.is_cash">
                                <label class="block text-sm font-medium text-text-secondary">Nomor Rekening</label>
                                <input v-model="form.account_number" type="text"
                                    class="mt-1 block w-full bg-surface-900 border-surface-600 text-text-primary rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm">
                            </div>

                            <div v-if="!form.is_cash">
                                <label class="block text-sm font-medium text-text-secondary">Atas Nama</label>
                                <input v-model="form.account_name" type="text"
                                    class="mt-1 block w-full bg-surface-900 border-surface-600 text-text-primary rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm">
                            </div>

                            <div class="flex items-center">
                                <input id="is_active" v-model="form.is_active" type="checkbox"
                                    class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-surface-600 rounded bg-surface-900">
                                <label for="is_active" class="ml-2 block text-sm text-text-primary">
                                    Aktif
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="bg-surface-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button @click="save" type="button"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary-600 text-base font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:ml-3 sm:w-auto sm:text-sm">
                            {{ isEditing ? 'Simpan Perubahan' : 'Tambah Simpan' }}
                        </button>
                        <button @click="closeModal" type="button"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-surface-600 shadow-sm px-4 py-2 bg-surface-800 text-base font-medium text-text-primary hover:bg-surface-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Batal
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { PlusIcon } from 'lucide-vue-next'; // Assumption: Lucide icons are available
import axios from '../../api/axios'; // FIXED: Relative import path

const plusIcon = PlusIcon;
const paymentMethods = ref([]);
const isModalOpen = ref(false);
const isEditing = ref(false);
const form = ref({
    id: null,
    name: '',
    account_number: '',
    account_name: '',
    is_cash: false,
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
        form.value = { ...pm, is_cash: Boolean(pm.is_cash), is_active: Boolean(pm.is_active) };
    } else {
        isEditing.value = false;
        form.value = {
            id: null,
            name: '',
            account_number: '',
            account_name: '',
            is_cash: false,
            is_active: true
        };
    }
};

const closeModal = () => {
    isModalOpen.value = false;
    form.value = { id: null, name: '', account_number: '', account_name: '', is_cash: false, is_active: true };
};

const save = async () => {
    try {
        if (isEditing.value) {
            await axios.put(`/payment-methods/${form.value.id}`, form.value);
        } else {
            await axios.post('/payment-methods', form.value);
        }
        closeModal();
        loadData();
    } catch (e) {
        alert('Gagal menyimpan: ' + (e.response?.data?.message || e.message));
    }
};

const deletePm = async (id) => {
    if (!confirm('Apakah anda yakin ingin menghapus metode ini?')) return;
    try {
        await axios.delete(`/payment-methods/${id}`);
        loadData();
    } catch (e) {
        alert('Gagal menghapus: ' + (e.response?.data?.message || e.message));
    }
};

const toggleStatus = async (pm) => {
    try {
        const newVal = !pm.is_active;
        await axios.put(`/payment-methods/${pm.id}`, { ...pm, is_active: newVal });
        loadData();
    } catch (e) {
        console.error(e);
    }
}

onMounted(() => {
    loadData();
});
</script>
