<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { X, Save, Smartphone, Disc, Wrench, HardDrive, Cpu, Tag, DollarSign } from 'lucide-vue-next';
import { productTypes as api, brands as brandsApi } from '../../../api/axios';
import { useToast } from '../../../composables/useToast';

const props = defineProps({
    show: Boolean,
    type: Object
});

const emit = defineEmits(['close', 'saved']);
const toast = useToast();
const loading = ref(false);
const brands = ref([]);

const form = ref({
    brand_id: '',
    name: '',
    category: 'imei', // imei, non_imei, service
    storage: ''
});

const categories = [
    { value: 'imei', label: 'HP / Gadget (IMEI)', icon: Smartphone },
    { value: 'non_imei', label: 'Barang Non IMEI', icon: Disc },
    // { value: 'service', label: 'Jasa Service', icon: Wrench } // Merged into non_imei
];

const capacityOptions = [
    // Storage Only
    "32 GB", "64 GB", "128 GB", "256 GB", "512 GB", "1 TB", "2 TB",
    // RAM/Storage Combos
    "1/8", "1/16", "1/32",
    "2/8", "2/16", "2/32", "2/64",
    "3/32", "3/64", "3/128", "3/256",
    "4/32", "4/64", "4/128", "4/256", "4/512",
    "6/64", "6/128", "6/256", "6/512",
    "8/64", "8/128", "8/256", "8/512", "8/1024",
    "12/128", "12/256", "12/512", "12/1024",
    "16/128", "16/256", "16/512", "16/1024",
    "18/128", "18/256", "18/512", "18/1024",
    "24/256", "24/512", "24/1024", "24/2064"
];

const isEditing = computed(() => !!props.type);
const title = computed(() => isEditing.value ? 'Edit Tipe Produk' : 'Tambah Tipe Produk');

const showSpecs = computed(() => form.value.category === 'imei');

const filteredBrands = computed(() => {
    // Map Type category (non_imei) to Brand category (non-imei)
    const targetCat = form.value.category === 'imei' ? 'imei' : 'non-imei';
    return brands.value.filter(b => b.category === targetCat);
});

// Fetch brands for dropdown
onMounted(async () => {
    try {
        const res = await brandsApi.list();
        brands.value = res.data.data;
    } catch (e) {
        console.error("Failed to load brands", e);
    }
});

const selectedStorage = ref([]);

watch(() => props.type, (newVal) => {
    if (newVal) {
        form.value = { ...newVal };
        // Sync selected storage for multi-select
        if (newVal.storage) {
            selectedStorage.value = newVal.storage.split(',').map(s => s.trim()).filter(Boolean);
        } else {
            selectedStorage.value = [];
        }
    } else {
        form.value = {
            brand_id: '',
            name: '',
            category: 'imei',
            storage: ''
        };
        selectedStorage.value = [];
    }
}, { immediate: true });

const toggleStorage = (cap) => {
    if (selectedStorage.value.includes(cap)) {
        selectedStorage.value = selectedStorage.value.filter(c => c !== cap);
    } else {
        selectedStorage.value.push(cap);
    }
    // Update form model as comma-separated string
    form.value.storage = selectedStorage.value.join(', ');
};

const save = async () => {
    if (!form.value.brand_id || !form.value.name) {
        toast.error('Merek dan Nama Tipe wajib diisi');
        return;
    }

    loading.value = true;
    try {
        // Clean up specs if not IMEI
        const payload = { ...form.value };
        if (!showSpecs.value) {
            payload.storage = null;
        }

        if (isEditing.value) {
            await api.update(props.type.id, payload);
            toast.success('Tipe berhasil diperbarui');
        } else {
            await api.create(payload);
            toast.success('Tipe berhasil ditambahkan');
        }
        emit('saved');
    } catch (error) {
        console.error(error);
        toast.error(error.response?.data?.message || 'Gagal menyimpan data');
    } finally {
        loading.value = false;
    }
};

// Helper for formatting
const formatCurrency = (value) => {
    if (!value) return '';
    return new Intl.NumberFormat('id-ID', {
        style: 'decimal',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(value);
};
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

                <!-- Category Selection -->
                <div>
                    <label class="block text-sm font-medium text-text-secondary mb-2">Kategori Produk</label>
                    <div class="grid grid-cols-3 gap-2">
                        <button v-for="cat in categories" :key="cat.value" @click="form.category = cat.value"
                            class="p-3 rounded-xl border flex flex-col items-center gap-2 transition-all"
                            :class="form.category === cat.value ? 'bg-primary-600 border-primary-500 text-white' : 'bg-surface-900 border-surface-700 text-text-secondary hover:border-primary-500'">
                            <component :is="cat.icon" :size="20" />
                            <span class="text-xs font-medium text-center">{{ cat.label }}</span>
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-text-secondary mb-1">Merek</label>
                    <div class="relative">
                        <Tag class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary" :size="16" />
                        <select v-model="form.brand_id"
                            class="w-full bg-surface-900 border border-surface-700 rounded-xl pl-10 pr-4 py-2.5 text-text-primary focus:border-primary-500 focus:ring-1 focus:ring-primary-500 outline-none appearance-none">
                            <option value="" disabled>Pilih Merek...</option>
                            <option v-for="b in filteredBrands" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-text-secondary mb-1">Nama Tipe</label>
                    <div class="relative">
                        <Smartphone class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary" :size="16" />
                        <input v-model="form.name" type="text"
                            class="w-full bg-surface-900 border border-surface-700 rounded-xl pl-10 pr-4 py-2.5 text-text-primary focus:border-primary-500 focus:ring-1 focus:ring-primary-500 outline-none placeholder:text-text-secondary"
                            placeholder="Contoh: iPhone 15 Pro, Casing..">
                    </div>
                </div>

                <!-- Specs (Only for IMEI) -->
                <div v-if="showSpecs" class="animate-in">
                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-1">Kapasitas (RAM /
                            Storage)</label>
                        <div class="relative">
                            <HardDrive class="absolute left-3 top-3 text-text-secondary" :size="16" />
                            <div class="pl-10">
                                <div class="flex flex-wrap gap-2 max-h-[200px] overflow-y-auto pr-2 custom-scrollbar">
                                    <button v-for="cap in capacityOptions" :key="cap" @click="toggleStorage(cap)"
                                        class="px-3 py-1.5 rounded-lg text-xs font-medium border transition-all duration-200"
                                        :class="selectedStorage.includes(cap)
                                            ? 'bg-primary-600 border-primary-500 text-white shadow-lg shadow-primary-500/20'
                                            : 'bg-surface-900 border-surface-700 text-text-secondary hover:border-surface-500 hover:text-text-primary'">
                                        {{ cap }}
                                    </button>
                                </div>
                                <div v-if="selectedStorage.length > 0" class="mt-2 text-[10px] text-emerald-500">
                                    Terpilih: {{ selectedStorage.join(', ') }}
                                </div>
                            </div>
                        </div>
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
                        class="w-4 h-4 border-2 border-white/20 border-t-white rounded-full animate-spin">
                    </div>
                    <span v-else>Simpan</span>
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.animate-in {
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(5px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
