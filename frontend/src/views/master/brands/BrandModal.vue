<script setup>
import { ref, computed, watch } from 'vue';
import { X, Save, Tag, FileText } from 'lucide-vue-next';
import { brands as api } from '../../../api/axios';
import { useToast } from '../../../composables/useToast';
import { useEscapeKey } from '../../../composables/useEscapeKey';

const props = defineProps({
    show: Boolean,
    brand: Object
});

const emit = defineEmits(['close', 'saved']);
const toast = useToast();
const loading = ref(false);

const form = ref({
    name: '',
    description: ''
});

const isEditing = computed(() => !!props.brand);
const title = computed(() => isEditing.value ? 'Edit Merek' : 'Tambah Merek Baru');

watch(() => props.brand, (newVal) => {
    if (newVal) {
        form.value = { ...newVal };
    } else {
        form.value = {
            name: '',
            category: 'non-imei',
            description: ''
        };
    }
}, { immediate: true });

const save = async () => {
    if (!form.value.name) {
        toast.error('Nama merek wajib diisi');
        return;
    }
    if (!form.value.category) {
        toast.error('Kategori wajib dipilih');
        return;
    }

    loading.value = true;
    try {
        if (isEditing.value) {
            await api.update(props.brand.id, form.value);
            toast.success('Merek berhasil diperbarui');
        } else {
            await api.create(form.value);
            toast.success('Merek berhasil ditambahkan');
        }
        emit('saved');
    } catch (error) {
        console.error(error);
        toast.error(error.response?.data?.message || 'Gagal menyimpan data');
    } finally {
        loading.value = false;
    }
};

useEscapeKey(() => {
    if (props.show) emit('close');
});
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="$emit('close')"></div>

        <!-- Modal -->
        <div
            class="bg-surface-800 rounded-2xl w-full max-w-lg shadow-2xl border border-surface-700 relative z-10 flex flex-col max-h-[90vh]">
            <!-- Header -->
            <div class="p-6 border-b border-surface-700 flex justify-between items-center">
                <h2 class="text-xl font-bold text-text-primary">{{ title }}</h2>
                <button @click="$emit('close')" class="text-text-secondary hover:text-text-primary transition-colors">
                    <X :size="24" />
                </button>
            </div>

            <!-- Body -->
            <div class="p-6 overflow-y-auto space-y-5">
                <div>
                    <label class="block text-sm font-medium text-text-secondary mb-1">Nama Merek</label>
                    <div class="relative">
                        <Tag class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary" :size="16" />
                        <input v-model="form.name" type="text"
                            class="w-full bg-surface-900 border border-surface-700 rounded-xl pl-10 pr-4 py-2.5 text-text-primary focus:border-primary-500 focus:ring-1 focus:ring-primary-500 outline-none placeholder:text-text-secondary"
                            placeholder="Contoh: Samsung, Apple">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-text-secondary mb-1">Kategori</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="cursor-pointer border rounded-xl p-3 flex items-center gap-3 transition-all"
                            :class="form.category === 'imei' ? 'border-primary-500 bg-primary-500/10 text-primary-400' : 'border-surface-700 hover:border-surface-600 bg-surface-900 text-text-secondary'">
                            <input type="radio" v-model="form.category" value="imei" class="hidden">
                            <div class="flex flex-col">
                                <span class="font-medium text-sm">Unit / HP</span>
                                <span class="text-[10px] opacity-70">Wajib memiliki IMEI</span>
                            </div>
                        </label>
                        <label class="cursor-pointer border rounded-xl p-3 flex items-center gap-3 transition-all"
                            :class="form.category === 'non-imei' ? 'border-primary-500 bg-primary-500/10 text-primary-400' : 'border-surface-700 hover:border-surface-600 bg-surface-900 text-text-secondary'">
                            <input type="radio" v-model="form.category" value="non-imei" class="hidden">
                            <div class="flex flex-col">
                                <span class="font-medium text-sm">Non-HP / Aksesoris</span>
                                <span class="text-[10px] opacity-70">Tidak perlu IMEI</span>
                            </div>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-text-secondary mb-1">Deskripsi (Opsional)</label>
                    <div class="relative">
                        <FileText class="absolute left-3 top-3 text-text-secondary" :size="16" />
                        <textarea v-model="form.description" rows="3"
                            class="w-full bg-surface-900 border border-surface-700 rounded-xl pl-10 pr-4 py-2.5 text-text-primary focus:border-primary-500 focus:ring-1 focus:ring-primary-500 outline-none placeholder:text-text-secondary"
                            placeholder="Deskripsi singkat tentang merek ini..."></textarea>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="p-6 border-t border-surface-700 flex justify-end gap-3">
                <button @click="$emit('close')"
                    class="px-4 py-2 text-text-secondary hover:text-text-primary font-medium">
                    Batal
                </button>
                <button @click="save" :disabled="loading"
                    class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-2 rounded-xl font-medium shadow-lg shadow-primary-500/20 active:scale-95 transition-all flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    <div v-if="loading"
                        class="w-4 h-4 border-2 border-white/20 border-t-white rounded-full animate-spin"></div>
                    <span v-else>Simpan</span>
                </button>
            </div>
        </div>
    </div>
</template>
