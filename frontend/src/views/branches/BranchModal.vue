<script setup>
import { ref, watch, computed } from 'vue';
import { X, Save, Building2, MapPin, Clock } from 'lucide-vue-next';
import { branches as api } from '../../api/axios';
import { useToast } from '../../composables/useToast';
import { useEscapeKey } from '../../composables/useEscapeKey';

const props = defineProps({
    show: Boolean,
    branch: Object,
    type: String
});

const emit = defineEmits(['close', 'saved']);
const toast = useToast();

// 1. DEKLARASI STATE (Wajib di atas agar bisa diakses Watcher)
const isLoading = ref(false);
const form = ref({
    code: '',
    name: '',
    address: '',
    timezone: 'WIB',
    is_active: true,
    type: props.type || 'physical'
});

const timezones = [
    { value: 'WIB', label: 'WIB (GMT+7)' },
    { value: 'WITA', label: 'WITA (GMT+8)' },
    { value: 'WIT', label: 'WIT (GMT+9)' },
];

const isEditing = computed(() => !!props.branch);

// 2. WATCHER (Berjalan setelah form diinisialisasi)
watch(() => props.branch, (newVal) => {
    if (newVal) {
        form.value = {
            code: newVal.code,
            name: newVal.name,
            address: newVal.address,
            timezone: newVal.timezone || 'WIB',
            is_active: !!newVal.is_active, // Pastikan boolean
            type: newVal.type || props.type || 'physical'
        };
    } else {
        resetForm();
    }
}, { immediate: true });

// 3. METHODS
function resetForm() {
    form.value = {
        code: '',
        name: '',
        address: '',
        timezone: 'WIB',
        is_active: true,
        type: props.type || 'physical'
    };
}

const save = async () => {
    if (!form.value.code || !form.value.name) {
        toast.error('Kode dan Nama cabang wajib diisi');
        return;
    }

    isLoading.value = true;
    try {
        const payload = {
            ...form.value
        };

        if (isEditing.value) {
            await api.update(props.branch.id, payload);
            toast.success('Cabang berhasil diperbarui');
        } else {
            await api.create(payload);
            toast.success('Cabang berhasil ditambahkan');
        }
        emit('saved');
    } catch (error) {
        console.error(error);
        const msg = error.response?.data?.message || 'Gagal menyimpan cabang';
        toast.error(msg);
    } finally {
        isLoading.value = false;
    }
};

useEscapeKey(() => {
    if (props.show) emit('close');
});
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="emit('close')"></div>

        <div
            class="relative bg-surface-800 rounded-2xl border border-surface-700 w-full max-w-lg shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-200">
            <div class="px-6 py-4 border-b border-surface-700 flex justify-between items-center bg-surface-900/50">
                <h3 class="text-lg font-bold text-white flex items-center gap-2">
                    <Building2 class="text-primary-500" :size="20" />
                    {{ isEditing ? 'Edit' : 'Tambah' }} {{ type === 'warehouse' ? 'Gudang' : 'Cabang' }}
                </h3>
                <button @click="emit('close')"
                    class="p-1 rounded-lg text-text-secondary hover:text-white hover:bg-surface-700 transition-colors">
                    <X :size="20" />
                </button>
            </div>

            <div class="p-6 space-y-4 overflow-y-auto max-h-[70vh] custom-scrollbar">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-1.5">Kode Cabang</label>
                        <input v-model="form.code" type="text"
                            class="w-full bg-surface-900 border border-surface-700 rounded-xl px-4 py-2 text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition-all font-mono"
                            placeholder="CTH: JKT01" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-1.5">Zona Waktu</label>
                        <div class="relative">
                            <Clock class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary" :size="16" />
                            <select v-model="form.timezone"
                                class="w-full bg-surface-900 border border-surface-700 rounded-xl pl-10 pr-4 py-2 text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 appearance-none">
                                <option v-for="tz in timezones" :key="tz.value" :value="tz.value">{{ tz.label }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-text-secondary mb-1.5">Nama Cabang</label>
                    <input v-model="form.name" type="text"
                        class="w-full bg-surface-900 border border-surface-700 rounded-xl px-4 py-2 text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition-all"
                        placeholder="Contoh: PStore Jakarta Pusat" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-text-secondary mb-1.5">Alamat Lengkap</label>
                    <div class="relative">
                        <MapPin class="absolute left-3 top-3 text-text-secondary" :size="16" />
                        <textarea v-model="form.address" rows="3"
                            class="w-full bg-surface-900 border border-surface-700 rounded-xl pl-10 pr-4 py-2 text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition-all resize-none"
                            placeholder="Alamat lengkap cabang..."></textarea>
                    </div>
                </div>

                <div class="flex items-center gap-3 p-4 bg-surface-900/50 rounded-xl border border-surface-700">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-white">Status Cabang</p>
                        <p class="text-xs text-text-secondary">Nonaktifkan jika cabang tutup sementara/permanen</p>
                    </div>
                    <button @click="form.is_active = !form.is_active"
                        class="relative w-12 h-6 rounded-full transition-colors duration-200 ease-in-out focus:outline-none"
                        :class="form.is_active ? 'bg-emerald-500' : 'bg-surface-600'">
                        <span
                            class="inline-block w-4 h-4 transform rounded-full bg-white shadow transition duration-200 ease-in-out mt-1 ml-1"
                            :class="form.is_active ? 'translate-x-6' : 'translate-x-0'"></span>
                    </button>
                </div>
            </div>

            <div class="px-6 py-4 border-t border-surface-700 flex justify-end gap-3 bg-surface-900/50">
                <button type="button" @click="emit('close')"
                    class="px-4 py-2 text-sm font-medium text-text-secondary hover:text-white transition-colors">
                    Batal
                </button>
                <button type="button" @click="save" :disabled="isLoading"
                    class="px-4 py-2 text-sm font-medium bg-primary-600 hover:bg-primary-500 text-white rounded-xl transition-all shadow-lg shadow-primary-500/20 flex items-center gap-2 disabled:opacity-50">
                    <Save :size="16" />
                    {{ isLoading ? 'Menyimpan...' : 'Simpan Cabang' }}
                </button>
            </div>
        </div>
    </div>
</template>