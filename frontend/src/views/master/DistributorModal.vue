<script setup>
import { ref, watch, computed } from 'vue';
import { X, Save, Truck, User, Phone, Mail, MapPin } from 'lucide-vue-next';
import { distributors as api } from '../../api/axios';
import { useToast } from '../../composables/useToast';
import { useEscapeKey } from '../../composables/useEscapeKey';

const props = defineProps({
    show: Boolean,
    item: Object
});

const emit = defineEmits(['close', 'saved']);
const toast = useToast();
const isLoading = ref(false);

const form = ref({
    name: '',
    code: '',
    contact_person: '',
    phone: '',
    email: '',
    address: '',
    is_active: true
});

watch(() => props.item, (newVal) => {
    if (newVal) {
        form.value = { ...newVal, is_active: !!newVal.is_active };
    } else {
        form.value = {
            name: '',
            code: '',
            contact_person: '',
            phone: '',
            email: '',
            address: '',
            is_active: true
        };
    }
}, { immediate: true });

const save = async () => {
    if (!form.value.name) return toast.error("Nama wajib diisi");
    isLoading.value = true;
    try {
        if (props.item) {
            await api.update(props.item.id, form.value);
            toast.success("Berhasil diperbarui");
        } else {
            await api.create(form.value);
            toast.success("Berhasil ditambahkan");
        }
        emit('saved');
    } catch (error) {
        toast.error("Gagal menyimpan data");
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
                    <Truck class="text-primary-500" :size="20" />
                    {{ item ? 'Edit Distributor' : 'Tambah Distributor' }}
                </h3>
                <button @click="emit('close')" class="p-1 rounded-lg hover:bg-surface-700 text-text-secondary">
                    <X :size="20" />
                </button>
            </div>

            <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto custom-scrollbar">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="label">Nama Distributor</label>
                        <input v-model="form.name" class="input w-full" placeholder="PT. ABC Jaya" />
                    </div>
                    <div>
                        <label class="label">Kode (Opsional)</label>
                        <input v-model="form.code" class="input w-full font-mono" placeholder="DST001" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="label">Contact Person</label>
                        <div class="relative">
                            <User class="absolute left-3 top-2.5 text-text-secondary" :size="16" />
                            <input v-model="form.contact_person" class="input w-full pl-10" placeholder="Bpk. Budi" />
                        </div>
                    </div>
                    <div>
                        <label class="label">No. Telepon</label>
                        <div class="relative">
                            <Phone class="absolute left-3 top-2.5 text-text-secondary" :size="16" />
                            <input v-model="form.phone" class="input w-full pl-10" placeholder="0812..." />
                        </div>
                    </div>
                </div>

                <div>
                    <label class="label">Email</label>
                    <div class="relative">
                        <Mail class="absolute left-3 top-2.5 text-text-secondary" :size="16" />
                        <input v-model="form.email" type="email" class="input w-full pl-10"
                            placeholder="email@distributor.com" />
                    </div>
                </div>

                <div>
                    <label class="label">Alamat</label>
                    <textarea v-model="form.address" class="input w-full" rows="3"
                        placeholder="Alamat lengkap..."></textarea>
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
