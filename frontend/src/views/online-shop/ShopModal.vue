<script setup>
import { ref, watch, computed } from 'vue';
import { X, Save, Key, Globe, Store, Link as LinkIcon } from 'lucide-vue-next';
import api from '../../api/axios';
import { useToast } from '../../composables/useToast';
import { useEscapeKey } from '../../composables/useEscapeKey';

const props = defineProps({
    show: Boolean,
    shop: Object
});

const emit = defineEmits(['close', 'saved']);
const toast = useToast();
const loading = ref(false);

const form = ref({
    code: '',
    name: '',
    platform: 'shopee',
    url: '',
    api_key: '',
    api_secret: '',
    is_active: true,
    timezone: 'WIB' // Default
});

const platforms = [
    { value: 'shopee', label: 'Shopee' },
    { value: 'tiktok', label: 'TikTok Shop' },
    { value: 'tokopedia', label: 'Tokopedia' },
    { value: 'lazada', label: 'Lazada' },
    { value: 'other', label: 'Lainnya' }
];

const isEditing = computed(() => !!props.shop);
const title = computed(() => isEditing.value ? 'Edit Toko Online' : 'Tambah Toko Online');

watch(() => props.shop, (newVal) => {
    if (newVal) {
        form.value = { ...newVal };
    } else {
        form.value = {
            code: '',
            name: '',
            platform: 'shopee',
            url: '',
            api_key: '',
            api_secret: '',
            is_active: true,
            timezone: 'WIB',
            type: 'online'
        };
    }
}, { immediate: true });

const save = async () => {
    if (!form.value.name || !form.value.code) {
        toast.error('Nama dan Kode toko wajib diisi');
        return;
    }

    loading.value = true;
    try {
        const payload = {
            ...form.value,
            type: 'online'
        };

        if (isEditing.value) {
            await api.put(`/online-shops/${props.shop.id}`, payload);
            toast.success('Toko berhasil diperbarui');
        } else {
            await api.post('/online-shops', payload);
            toast.success('Toko berhasil ditambahkan');
        }
        emit('saved');
    } catch (error) {
        if (error.response?.data?.errors) {
            const errors = error.response.data.errors;
            Object.values(errors).forEach(msg => toast.error(msg[0]));
        } else {
            toast.error('Gagal menyimpan data');
        }
        console.error(error);
    } finally {
        loading.value = false;
    }
};

useEscapeKey(() => {
    if (props.show) emit('close');
});
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-[110] flex items-center justify-center p-4">
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
            <div class="p-6 overflow-y-auto custom-scrollbar space-y-5">
                <!-- Basic Info -->
                <div class="space-y-4">
                    <h3 class="text-sm font-bold text-text-secondary uppercase tracking-wider flex items-center gap-2">
                        <Store :size="14" /> Informasi Dasar
                    </h3>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-text-secondary mb-1">Kode Toko (ID)</label>
                            <input v-model="form.code" type="text"
                                class="w-full bg-surface-900 border border-surface-700 rounded-xl px-4 py-2 text-text-primary focus:border-primary-500 focus:ring-1 focus:ring-primary-500 outline-none"
                                placeholder="CTH: SHOPEE-01">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-text-secondary mb-1">Platform</label>
                            <select v-model="form.platform"
                                class="w-full bg-surface-900 border border-surface-700 rounded-xl px-4 py-2 text-text-primary focus:border-primary-500 outline-none">
                                <option v-for="p in platforms" :key="p.value" :value="p.value">{{ p.label }}</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-1">Nama Toko</label>
                        <input v-model="form.name" type="text"
                            class="w-full bg-surface-900 border border-surface-700 rounded-xl px-4 py-2 text-text-primary focus:border-primary-500 focus:ring-1 focus:ring-primary-500 outline-none"
                            placeholder="Contoh: Official Store Jakarta">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-1">URL Toko</label>
                        <div class="relative">
                            <LinkIcon class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary" :size="16" />
                            <input v-model="form.url" type="url"
                                class="w-full bg-surface-900 border border-surface-700 rounded-xl pl-10 pr-4 py-2 text-text-primary focus:border-primary-500 outline-none"
                                placeholder="https://shopee.co.id/toko...">
                        </div>
                    </div>
                </div>

                <hr class="border-surface-700">

                <!-- API Integration -->
                <div class="space-y-4">
                    <h3 class="text-sm font-bold text-text-secondary uppercase tracking-wider flex items-center gap-2">
                        <Key :size="14" /> Integrasi API
                    </h3>

                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-1">API Key</label>
                        <input v-model="form.api_key" type="password"
                            class="w-full bg-surface-900 border border-surface-700 rounded-xl px-4 py-2 text-text-primary focus:border-primary-500 outline-none font-mono text-sm"
                            placeholder="Paste API Key here...">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-1">API Secret</label>
                        <input v-model="form.api_secret" type="password"
                            class="w-full bg-surface-900 border border-surface-700 rounded-xl px-4 py-2 text-text-primary focus:border-primary-500 outline-none font-mono text-sm"
                            placeholder="Paste API Secret here...">
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" id="status" v-model="form.is_active"
                        class="w-4 h-4 rounded border-surface-600 bg-surface-900 text-primary-600 focus:ring-primary-500">
                    <label for="status" class="text-sm text-text-primary">Status Aktif</label>
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
                    <span v-else>Simpan Toko</span>
                </button>
            </div>
        </div>
    </div>
</template>
