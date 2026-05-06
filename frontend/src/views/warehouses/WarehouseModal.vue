<script setup>
import { ref, watch, computed } from 'vue';
import { X, Save, Warehouse } from 'lucide-vue-next';
import { warehouses as api } from '../../api/axios';
import { useToast } from '../../composables/useToast';
import { useEscapeKey } from '../../composables/useEscapeKey';

const props = defineProps({
    show: Boolean,
    warehouse: Object
});

const emit = defineEmits(['close', 'saved']);
const toast = useToast();

const isLoading = ref(false);
const form = ref({
    code: '',
    name: '',
    address: '',
    timezone: 'WIB',
    is_active: true,
    can_accept_returns: false
});

const timezones = [
    { value: 'WIB', label: 'WIB (GMT+7)' },
    { value: 'WITA', label: 'WITA (GMT+8)' },
    { value: 'WIT', label: 'WIT (GMT+9)' },
];

const isEditing = computed(() => !!props.warehouse);

watch(() => props.warehouse, (newVal) => {
    if (newVal) {
        form.value = {
            code: newVal.code,
            name: newVal.name,
            address: newVal.address,
            timezone: newVal.timezone || 'WIB',
            is_active: !!newVal.is_active,
            can_accept_returns: !!newVal.can_accept_returns
        };
    } else {
        form.value = {
            code: '',
            name: '',
            address: '',
            timezone: 'WIB',
            is_active: true,
            can_accept_returns: false
        };
    }
}, { immediate: true });

const save = async () => {
    if (!form.value.code || !form.value.name) return toast.error("Kode dan Nama wajib diisi");
    isLoading.value = true;
    try {
        if (props.warehouse) {
            await api.update(props.warehouse.id, form.value);
            toast.success("Berhasil diperbarui");
        } else {
            await api.create(form.value);
            toast.success("Berhasil ditambahkan");
        }
        emit('saved');
    } catch (error) {
        const msg = error.response?.data?.message || "Gagal menyimpan data";
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
            class="relative bg-surface-800 rounded-2xl border border-surface-700 w-full max-w-lg shadow-2xl overflow-hidden animate-in zoom-in duration-200">
            <div class="px-6 py-4 border-b border-surface-700 flex justify-between items-center bg-surface-900/50">
                <h3 class="text-lg font-bold text-white flex items-center gap-2">
                    <Warehouse class="text-primary-500" :size="20" />
                    {{ isEditing ? 'Edit Gudang' : 'Tambah Gudang Baru' }}
                </h3>
                <button @click="emit('close')" class="p-1 rounded-lg hover:bg-surface-700 text-text-secondary">
                    <X :size="20" />
                </button>
            </div>

            <div class="p-6 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="label">Kode Gudang</label>
                        <input v-model="form.code" class="input w-full font-mono uppercase" placeholder="WH-001"
                            :disabled="isEditing" />
                    </div>
                    <div>
                        <label class="label">Zona Waktu</label>
                        <select v-model="form.timezone" class="input w-full">
                            <option v-for="tz in timezones" :key="tz.value" :value="tz.value">{{ tz.label }}</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="label">Nama Gudang</label>
                    <input v-model="form.name" class="input w-full" placeholder="Gudang Pusat..." />
                </div>

                <div>
                    <label class="label">Alamat</label>
                    <textarea v-model="form.address" class="input w-full" rows="3"
                        placeholder="Alamat lengkap..."></textarea>
                </div>

                <div
                    class="flex items-center justify-between p-4 bg-surface-900/50 rounded-xl border border-surface-700">
                    <div>
                        <p class="text-sm font-bold text-white">Terima Retur</p>
                        <p class="text-xs text-text-secondary">Izinkan gudang ini menerima pengembalian barang</p>
                    </div>
                    <button type="button" @click="form.can_accept_returns = !form.can_accept_returns" :class="[
                        form.can_accept_returns ? 'bg-emerald-500' : 'bg-surface-700',
                        'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none'
                    ]">
                        <span aria-hidden="true" :class="[
                            form.can_accept_returns ? 'translate-x-5' : 'translate-x-0',
                            'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out'
                        ]"></span>
                    </button>
                </div>
            </div>

            <div class="px-6 py-4 border-t border-surface-700 flex justify-end gap-3 bg-surface-900/50">
                <button @click="emit('close')" class="btn hover:text-white text-text-secondary">Batal</button>
                <button @click="save" :disabled="isLoading" class="btn btn-primary flex items-center gap-2">
                    <Save :size="16" /> {{ isLoading ? 'Menyimpan...' : 'Simpan' }}
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
@reference "../../style.css";

.label {
    @apply block text-sm font-medium text-text-secondary mb-1.5;
}

.input {
    @apply bg-surface-900 border border-surface-700 rounded-xl px-4 py-2 text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition-all placeholder:text-surface-600;
}
</style>
