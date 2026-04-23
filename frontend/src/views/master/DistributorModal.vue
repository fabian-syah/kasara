<script setup>
import { ref, watch, computed } from 'vue';
import { distributors as api, brands as brandsApi } from '../../api/axios';
import { useToast } from '../../composables/useToast';
import { useEscapeKey } from '../../composables/useEscapeKey';
import { Tag, Check } from 'lucide-vue-next';

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
    is_active: true,
    allowed_brands: []
});

const brands = ref([]);
const fetchBrands = async () => {
    try {
        const response = await brandsApi.list();
        // The API returns { success: true, data: [...] }
        brands.value = Array.isArray(response.data.data) ? response.data.data : (Array.isArray(response.data) ? response.data : []);
    } catch (error) {
        console.error("Failed to fetch brands:", error);
        brands.value = [];
    }
};

watch(() => props.show, (newVal) => {
    if (newVal) fetchBrands();
});

watch(() => props.item, (newVal) => {
    const defaultBrandIds = Array.isArray(brands.value) ? brands.value.map(b => b.id) : [];
    if (newVal) {
        form.value = { 
            ...newVal, 
            is_active: !!newVal.is_active,
            allowed_brands: (newVal.allowed_brands && Array.isArray(newVal.allowed_brands)) ? newVal.allowed_brands : defaultBrandIds
        };
    } else {
        form.value = {
            name: '',
            code: '',
            contact_person: '',
            phone: '',
            email: '',
            address: '',
            is_active: true,
            allowed_brands: defaultBrandIds
        };
    }
}, { immediate: true });

// Auto-fill brands for NEW distributors once brands data arrives
watch(brands, (newBrands) => {
    if (!props.item && form.value.allowed_brands.length === 0 && newBrands.length > 0) {
        form.value.allowed_brands = newBrands.map(b => b.id);
    }
});

const isAllBrandsSelected = computed(() => {
    return brands.value.length > 0 && form.value.allowed_brands.length === brands.value.length;
});

const toggleAllBrands = () => {
    if (isAllBrandsSelected.value) {
        form.value.allowed_brands = [];
    } else {
        form.value.allowed_brands = brands.value.map(b => b.id);
    }
};

const toggleBrand = (id) => {
    const index = form.value.allowed_brands.indexOf(id);
    if (index > -1) {
        form.value.allowed_brands.splice(index, 1);
    } else {
        form.value.allowed_brands.push(id);
    }
};

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
                    <textarea v-model="form.address" class="input w-full" rows="2"
                        placeholder="Alamat lengkap..."></textarea>
                </div>

                <!-- Brand Restrictions -->
                <div class="space-y-3 pt-2">
                    <div class="flex items-center justify-between">
                        <label class="label !mb-0 flex items-center gap-2">
                            <Tag :size="16" class="text-primary-400" />
                            Merek yang Tersedia
                        </label>
                        <button @click="toggleAllBrands" type="button" class="text-[10px] uppercase font-bold tracking-wider px-2 py-1 rounded bg-surface-700 hover:bg-surface-600 transition-colors text-text-secondary">
                            {{ isAllBrandsSelected ? 'Unselect All' : 'Select All' }}
                        </button>
                    </div>
                    
                    <div class="bg-surface-900/50 border border-surface-700/50 rounded-xl p-3 max-h-40 overflow-y-auto custom-scrollbar">
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                            <div v-for="brand in brands" :key="brand.id" 
                                @click="toggleBrand(brand.id)"
                                class="flex items-center gap-2 p-2 rounded-lg cursor-pointer transition-all border group"
                                :class="form.allowed_brands.includes(brand.id) 
                                    ? 'bg-primary-500/10 border-primary-500/30 text-white' 
                                    : 'bg-surface-800 border-surface-700 text-text-secondary hover:border-surface-600'">
                                <div class="w-4 h-4 rounded-md border flex items-center justify-center transition-colors shrink-0"
                                    :class="form.allowed_brands.includes(brand.id)
                                        ? 'bg-primary-500 border-primary-500'
                                        : 'border-surface-600 group-hover:border-surface-500'">
                                    <Check v-if="form.allowed_brands.includes(brand.id)" :size="12" class="text-white" />
                                </div>
                                <span class="text-xs font-medium truncate">{{ brand.name }}</span>
                            </div>
                        </div>
                        <div v-if="brands.length === 0" class="text-center py-4 text-xs text-surface-500 italic">
                            Belum ada data merek...
                        </div>
                    </div>
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
