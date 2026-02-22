<script setup>
import { ref, watch } from 'vue';
import { X, Save, Tags } from 'lucide-vue-next';
import { categories as api } from '../../api/axios';
import { useToast } from '../../composables/useToast';
import { useEscapeKey } from '../../composables/useEscapeKey';

const props = defineProps({
    show: Boolean,
    item: Object,
    type: String // 'product' or 'transaction'
});

const emit = defineEmits(['close', 'saved']);
const toast = useToast();
const isLoading = ref(false);

const form = ref({
    name: '',
    description: '',
    type: 'product',
    is_active: true
});

watch(() => props.show, (val) => {
    if (val) {
        if (props.item) {
            form.value = { ...props.item, is_active: !!props.item.is_active };
        } else {
            form.value = {
                name: '',
                description: '',
                type: props.type || 'product',
                is_active: true
            };
        }
    }
});

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
            class="relative bg-surface-800 rounded-2xl border border-surface-700 w-full max-w-md shadow-2xl overflow-hidden animate-in zoom-in duration-200">
            <div class="px-6 py-4 border-b border-surface-700 flex justify-between items-center bg-surface-900/50">
                <h3 class="text-lg font-bold text-white flex items-center gap-2">
                    <Tags class="text-primary-500" :size="20" />
                    {{ item ? 'Edit Kategori' : 'Tambah Kategori' }}
                </h3>
                <button @click="emit('close')" class="p-1 rounded-lg hover:bg-surface-700 text-text-secondary">
                    <X :size="20" />
                </button>
            </div>

            <div class="p-6 space-y-4">
                <div>
                    <label class="label">Tipe</label>
                    <input :value="form.type === 'product' ? 'Jenis Barang' : 'Kategori Transaksi'" disabled
                        class="input w-full opacity-60 cursor-not-allowed" />
                </div>

                <div>
                    <label class="label">Nama Kategori</label>
                    <input v-model="form.name" class="input w-full" placeholder="Nama..." />
                </div>

                <div>
                    <label class="label">Deskripsi</label>
                    <textarea v-model="form.description" class="input w-full" rows="3"
                        placeholder="Deskripsi singkat..."></textarea>
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
