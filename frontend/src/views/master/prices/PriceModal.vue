<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { X, Save, DollarSign } from 'lucide-vue-next';
import { productPrices as api, brands as brandsApi, productTypes as typesApi } from '../../../api/axios';
import { useToast } from '../../../composables/useToast';

const props = defineProps({
    show: Boolean,
    show: Boolean,
    price: Object, // If exists, editing
    initialCategory: String // 'hp' or 'non-hp'
});

const emit = defineEmits(['close', 'saved']);
const toast = useToast();
const loading = ref(false);

const brands = ref([]);
const types = ref([]);
const selectedBrandId = ref('');

const category = ref('hp'); // 'hp' or 'non-hp'

const form = ref({
    product_type_id: '',
    condition: 'new',
    ram: '',
    storage: '',
    cost_price: 0,
    price: 0
});

const isEditing = computed(() => !!props.price);
const title = computed(() => isEditing.value ? 'Edit Harga' : 'Tambah Harga Baru');

// Helper to format/parse currency
const formatCurrency = (value) => {
    if (!value) return '';
    return new Intl.NumberFormat('id-ID', {
        style: 'decimal',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(value);
};

const costPriceDisplay = computed({
    get: () => form.value.cost_price ? formatCurrency(form.value.cost_price) : '',
    set: (val) => form.value.cost_price = parseInt(val.replace(/\D/g, '') || '0')
});

const priceDisplay = computed({
    get: () => form.value.price ? formatCurrency(form.value.price) : '',
    set: (val) => form.value.price = parseInt(val.replace(/\D/g, '') || '0')
});

// Load brands and types
onMounted(async () => {
    try {
        const [bRes, tRes] = await Promise.all([
            brandsApi.list(),
            typesApi.list()
        ]);
        brands.value = bRes.data.data;
        types.value = tRes.data.data;
    } catch (e) {
        console.error("Failed loading master data", e);
    }
});

// Filter types based on selected brand
const filteredTypes = computed(() => {
    if (!selectedBrandId.value) return [];
    return types.value.filter(t => {
        // Normalize category check
        const typeCategory = (t.category || '').toLowerCase().trim();
        // Check for 'imei' (new standard) or legacy 'hp'/'hp / gadget'
        const isHp = typeCategory === 'imei' || typeCategory === 'hp' || typeCategory.includes('hp');

        const matchesCategory = category.value === 'hp' ? isHp : !isHp;

        // Debugging (optional, remove in prod)
        // console.log(`Type: ${t.name}, Cat: ${t.category}, isHp: ${isHp}, SelectedCat: ${category.value}, Match: ${matchesCategory}`);

        return t.brand_id == selectedBrandId.value && matchesCategory;
    });
});

// Computed options for RAM and Storage based on selected type
const capacityOptions = computed(() => {
    if (!form.value.product_type_id) return { rams: [], storages: [] };
    const type = types.value.find(t => t.id === form.value.product_type_id);
    if (!type) return { rams: [], storages: [] };

    const ramSet = new Set();
    const storageSet = new Set();

    if (type.ram) type.ram.split(/[,/]+/).forEach(s => ramSet.add(s.trim()));
    if (type.storage) type.storage.split(/[,/]+/).forEach(s => storageSet.add(s.trim()));

    return {
        rams: Array.from(ramSet),
        storages: Array.from(storageSet)
    };
});

watch(() => props.price, (newVal) => {
    if (newVal) {
        form.value = { ...newVal };
        // Set selectedBrandId to pre-fill dropdowns
        const type = types.value.find(t => t.id === newVal.product_type_id);
        if (type) {
            selectedBrandId.value = type.brand_id;
            category.value = type.category === 'HP / Gadget' ? 'hp' : 'non-hp';
        }
    } else {
        category.value = props.initialCategory || 'hp';
        form.value = {
            product_type_id: '',
            condition: 'new',
            ram: '',
            storage: '',
            cost_price: 0,
            price: 0
        };
        selectedBrandId.value = '';
    }
}, { immediate: true });

const save = async () => {
    if (!form.value.product_type_id) {
        toast.error('Pilih Tipe Produk');
        return;
    }

    loading.value = true;
    try {
        if (isEditing.value) {
            await api.update(props.price.id, form.value);
            toast.success('Harga berhasil diperbarui');
        } else {
            await api.create(form.value);
            toast.success('Harga berhasil ditambahkan');
        }
        emit('saved');
    } catch (error) {
        console.error(error);
        toast.error(error.response?.data?.message || 'Gagal menyimpan harga');
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="$emit('close')"></div>

        <div
            class="bg-surface-800 rounded-2xl w-full max-w-lg shadow-2xl border border-surface-700 relative z-10 flex flex-col">
            <div class="p-6 border-b border-surface-700 flex justify-between items-center">
                <h2 class="text-xl font-bold text-text-primary">{{ title }}</h2>
                <button @click="$emit('close')" class="text-text-secondary hover:text-text-primary">
                    <X :size="24" />
                </button>
            </div>

            <div class="p-6 space-y-4">
                <!-- Category Switch -->
                <div class="bg-surface-900 p-1 rounded-xl flex mb-4" v-if="!isEditing">
                    <button @click="category = 'hp'; form.product_type_id = ''"
                        class="flex-1 py-2 rounded-lg text-sm font-medium transition-all"
                        :class="category === 'hp' ? 'bg-surface-700 text-white shadow' : 'text-text-secondary hover:text-text-primary'">
                        HP / IMEI
                    </button>
                    <button @click="category = 'non-hp'; form.product_type_id = ''"
                        class="flex-1 py-2 rounded-lg text-sm font-medium transition-all"
                        :class="category === 'non-hp' ? 'bg-surface-700 text-white shadow' : 'text-text-secondary hover:text-text-primary'">
                        Non HP
                    </button>
                </div>

                <!-- Brand & Type Selection -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="label">Merek</label>
                        <select v-model="selectedBrandId" class="input" :disabled="isEditing">
                            <option value="">Pilih Merek</option>
                            <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Tipe Produk</label>
                        <select v-model="form.product_type_id" class="input" :disabled="!selectedBrandId || isEditing">
                            <option value="">Pilih Tipe</option>
                            <option v-for="t in filteredTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                    </div>
                </div>

                <!-- Capacity Selection (HP Only) -->
                <div v-if="category === 'hp' && (capacityOptions.rams.length > 0 || capacityOptions.storages.length > 0)"
                    class="grid grid-cols-2 gap-4 animate-in fade-in">
                    <div v-if="capacityOptions.rams.length > 0">
                        <label class="label">RAM (Opsional)</label>
                        <select v-model="form.ram" class="input" :disabled="isEditing">
                            <option value="">Semua RAM</option>
                            <option v-for="r in capacityOptions.rams" :key="r" :value="r">{{ r }}</option>
                        </select>
                    </div>
                    <div v-if="capacityOptions.storages.length > 0">
                        <label class="label">Storage (Opsional)</label>
                        <select v-model="form.storage" class="input" :disabled="isEditing">
                            <option value="">Semua Storage</option>
                            <option v-for="s in capacityOptions.storages" :key="s" :value="s">{{ s }}</option>
                        </select>
                    </div>
                </div>

                <!-- Condition (HP Only) -->
                <div v-if="category === 'hp'">
                    <label class="label">Kondisi Barang</label>
                    <div class="grid grid-cols-2 gap-3">
                        <button type="button" @click="form.condition = 'new'"
                            class="p-3 rounded-xl border text-center font-bold transition-all"
                            :class="form.condition === 'new' ? 'bg-emerald-500 border-emerald-500 text-white' : 'bg-surface-900 border-surface-700 text-text-secondary'">
                            BARU (NEW)
                        </button>
                        <button type="button" @click="form.condition = 'second'"
                            class="p-3 rounded-xl border text-center font-bold transition-all"
                            :class="form.condition === 'second' ? 'bg-amber-500 border-amber-500 text-white' : 'bg-surface-900 border-surface-700 text-text-secondary'">
                            BEKAS (SECOND)
                        </button>
                    </div>
                </div>

                <!-- Prices -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="label">Harga Modal</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary text-sm">Rp</span>
                            <input v-model="costPriceDisplay" type="text" class="input pl-8" placeholder="0">
                        </div>
                    </div>
                    <div>
                        <label class="label">Harga Jual</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary text-sm">Rp</span>
                            <input v-model="priceDisplay" type="text" class="input pl-8" placeholder="0">
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-6 border-t border-surface-700 flex justify-end gap-3">
                <button @click="$emit('close')"
                    class="px-4 py-2 text-text-secondary hover:text-text-primary font-medium">Batal</button>
                <button @click="save" :disabled="loading"
                    class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-2 rounded-xl font-medium flex items-center gap-2">
                    <span v-if="!loading">Simpan</span>
                    <span v-else>Menyimpan...</span>
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
@reference "../../../style.css";

.label {
    @apply block text-sm font-medium text-text-secondary mb-1;
}

.input {
    @apply w-full bg-surface-900 border border-surface-700 rounded-xl px-4 py-2.5 text-text-primary focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 transition-all;
}
</style>
