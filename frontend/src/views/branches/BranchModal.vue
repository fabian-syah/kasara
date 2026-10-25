<script setup>
import { ref, watch, computed, onMounted } from 'vue';
import { X, Save, Building2, MapPin, Clock, Store, ShoppingBag, Check } from 'lucide-vue-next';
import api, { branches } from '../../api/axios';
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
    enable_online_sales: false,
    phone: '',
    warranty_terms: '',
    type: props.type || 'physical',
    payment_method_ids: []
});

const allPaymentMethods = ref([]);

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
            enable_online_sales: !!newVal.enable_online_sales,
            phone: newVal.phone || '',
            warranty_terms: newVal.warranty_terms || '',
            type: newVal.type || props.type || 'physical',
            // Default to all selected IF it's a new config (empty list) AND we have the methods loaded
            payment_method_ids: (newVal.payment_methods && newVal.payment_methods.length > 0) 
                ? newVal.payment_methods.map(p => p.id) 
                : (allPaymentMethods.value.length > 0 ? allPaymentMethods.value.map(p => p.id) : [])
        };
    } else {
        resetForm();
    }
}, { immediate: true });

onMounted(async () => {
    try {
        const res = await api.get('/payment-methods');
        allPaymentMethods.value = res.data.filter(p => p.is_active);
        
        // Re-apply defaults now that allPaymentMethods is populated
        if (!isEditing.value) {
            form.value.payment_method_ids = allPaymentMethods.value.map(p => p.id);
        } else if (!props.branch.payment_methods || props.branch.payment_methods.length === 0) {
            // ONLY check all if the branch has ZERO payment methods (first time config)
            form.value.payment_method_ids = allPaymentMethods.value.map(p => p.id);
        }
    } catch (e) {
        console.error("Gagal ambil metode pembayaran", e);
    }
});

// 3. METHODS
function resetForm() {
    form.value = {
        code: '',
        name: '',
        address: '',
        timezone: 'WIB',
        is_active: true,
        enable_online_sales: false,
        phone: '',
        warranty_terms: '',
        type: props.type || 'physical',
        payment_method_ids: allPaymentMethods.value.map(p => p.id)
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
            await branches.update(props.branch.id, payload);
            toast.success('Cabang berhasil diperbarui');
        } else {
            await branches.create(payload);
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
            <div class="px-4 sm:px-6 py-4 border-b border-surface-700 flex justify-between items-center bg-surface-900/50">
                <h3 class="text-lg font-bold text-white flex items-center gap-2">
                    <Building2 class="text-primary-500" :size="20" />
                    {{ isEditing ? 'Edit' : 'Tambah' }} {{ type === 'warehouse' ? 'Gudang' : 'Cabang' }}
                </h3>
                <button @click="emit('close')"
                    class="p-1.5 rounded-lg text-text-secondary hover:text-white hover:bg-surface-700 transition-colors">
                    <X :size="20" />
                </button>
            </div>

            <div class="p-4 sm:p-6 space-y-4 overflow-y-auto max-h-[75vh] custom-scrollbar">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-1.5">Kode Cabang</label>
                        <input v-model="form.code" type="text"
                            class="w-full bg-surface-900 border border-surface-700 rounded-xl px-4 py-2 text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition-all font-mono text-sm"
                            placeholder="CTH: JKT01" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-1.5">Zona Waktu</label>
                        <div class="relative">
                            <Clock class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary" :size="16" />
                            <select v-model="form.timezone"
                                class="w-full bg-surface-900 border border-surface-700 rounded-xl pl-10 pr-4 py-2 text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 appearance-none text-sm">
                                <option v-for="tz in timezones" :key="tz.value" :value="tz.value">{{ tz.label }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-text-secondary mb-1.5">Nama Cabang</label>
                    <input v-model="form.name" type="text"
                        class="w-full bg-surface-900 border border-surface-700 rounded-xl px-4 py-2 text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition-all text-sm"
                        placeholder="Contoh: Kasara Jakarta Pusat" />
                </div>

                <!-- Status Cabang Switch Card -->
                <div 
                    @click="form.is_active = !form.is_active"
                    role="button"
                    tabindex="0"
                    @keydown.space.prevent="form.is_active = !form.is_active"
                    class="flex items-center justify-between gap-3 sm:gap-4 p-3.5 sm:p-4 rounded-xl border transition-all duration-200 cursor-pointer select-none group"
                    :class="form.is_active 
                        ? 'bg-emerald-500/5 border-emerald-500/30 hover:border-emerald-500/50 hover:bg-emerald-500/10' 
                        : 'bg-surface-900/50 border-surface-700/80 hover:border-surface-600 hover:bg-surface-900/80'">
                    
                    <div class="flex items-center gap-3 sm:gap-3.5 min-w-0 flex-1">
                        <!-- Icon badge -->
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 transition-colors duration-200"
                            :class="form.is_active 
                                ? 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/25 shadow-sm shadow-emerald-500/10' 
                                : 'bg-surface-800 text-surface-400 border border-surface-700'">
                            <Store :size="20" />
                        </div>
                        
                        <!-- Content -->
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="text-sm font-semibold text-white">Status Cabang</p>
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-medium tracking-wide transition-colors"
                                    :class="form.is_active 
                                        ? 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30' 
                                        : 'bg-surface-800 text-text-secondary border border-surface-700'">
                                    <span class="w-1.5 h-1.5 rounded-full" :class="form.is_active ? 'bg-emerald-400' : 'bg-surface-500'"></span>
                                    {{ form.is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </div>
                            <p class="text-xs text-text-secondary mt-0.5 leading-relaxed">
                                {{ form.is_active ? 'Cabang beroperasi aktif dan melayani transaksi' : 'Cabang ditutup sementara atau permanen' }}
                            </p>
                        </div>
                    </div>

                    <!-- Pixel-Perfect Symmetrical Switch -->
                    <button 
                        type="button" 
                        role="switch" 
                        :aria-checked="form.is_active" 
                        @click.stop="form.is_active = !form.is_active"
                        class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:ring-offset-2 focus:ring-offset-surface-900"
                        :class="form.is_active ? 'bg-emerald-500' : 'bg-surface-700'">
                        <span class="sr-only">Toggle Status Cabang</span>
                        <span 
                            class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-md ring-0 transition duration-200 ease-in-out"
                            :class="form.is_active ? 'translate-x-[22px]' : 'translate-x-[2px]'"></span>
                    </button>
                </div>

                <!-- Penjualan Online Toko Feature Toggle Card -->
                <div 
                    @click="form.enable_online_sales = !form.enable_online_sales"
                    role="button"
                    tabindex="0"
                    @keydown.space.prevent="form.enable_online_sales = !form.enable_online_sales"
                    class="flex items-center justify-between gap-3 sm:gap-4 p-3.5 sm:p-4 rounded-xl border transition-all duration-200 cursor-pointer select-none group"
                    :class="form.enable_online_sales 
                        ? 'bg-primary-500/5 border-primary-500/30 hover:border-primary-500/50 hover:bg-primary-500/10' 
                        : 'bg-surface-900/50 border-surface-700/80 hover:border-surface-600 hover:bg-surface-900/80'">
                    
                    <div class="flex items-center gap-3 sm:gap-3.5 min-w-0 flex-1">
                        <!-- Icon badge -->
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 transition-colors duration-200"
                            :class="form.enable_online_sales 
                                ? 'bg-primary-500/15 text-primary-400 border border-primary-500/25 shadow-sm shadow-primary-500/10' 
                                : 'bg-surface-800 text-surface-400 border border-surface-700'">
                            <ShoppingBag :size="20" />
                        </div>
                        
                        <!-- Content -->
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="text-sm font-semibold text-white">Penjualan Online Toko</p>
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-medium tracking-wide transition-colors"
                                    :class="form.enable_online_sales 
                                        ? 'bg-primary-500/15 text-primary-400 border border-primary-500/30' 
                                        : 'bg-surface-800 text-text-secondary border border-surface-700'">
                                    <span class="w-1.5 h-1.5 rounded-full" :class="form.enable_online_sales ? 'bg-primary-400' : 'bg-surface-500'"></span>
                                    {{ form.enable_online_sales ? 'Fitur Aktif' : 'Nonaktif' }}
                                </span>
                            </div>
                            <p class="text-xs text-text-secondary mt-0.5 leading-relaxed">
                                Tampilkan kategori Penjualan Online Toko di menu Buat Penjualan cabang ini
                            </p>
                        </div>
                    </div>

                    <!-- Pixel-Perfect Symmetrical Switch -->
                    <button 
                        type="button" 
                        role="switch" 
                        :aria-checked="form.enable_online_sales" 
                        @click.stop="form.enable_online_sales = !form.enable_online_sales"
                        class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary-500/50 focus:ring-offset-2 focus:ring-offset-surface-900"
                        :class="form.enable_online_sales ? 'bg-primary-500' : 'bg-surface-700'">
                        <span class="sr-only">Toggle Penjualan Online Toko</span>
                        <span 
                            class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-md ring-0 transition duration-200 ease-in-out"
                            :class="form.enable_online_sales ? 'translate-x-[22px]' : 'translate-x-[2px]'"></span>
                    </button>
                </div>

                <!-- Payment Methods Selection -->
                <div v-if="allPaymentMethods.length > 0" class="space-y-3 pt-1">
                    <label class="block text-sm font-medium text-text-secondary">Metode Pembayaran Tersedia</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 sm:gap-3">
                        <label v-for="pm in allPaymentMethods" :key="pm.id" 
                            class="flex items-center gap-3 p-3 rounded-xl border transition-all cursor-pointer group select-none"
                            :class="form.payment_method_ids.includes(pm.id) 
                                ? 'bg-primary-500/10 border-primary-500/60 text-white shadow-sm shadow-primary-500/10' 
                                : 'bg-surface-900/50 border-surface-700/80 text-text-secondary hover:border-surface-600 hover:text-white'">
                            <input type="checkbox" :value="pm.id" v-model="form.payment_method_ids" class="hidden" />
                            <div class="w-5 h-5 rounded-md border flex items-center justify-center transition-all shrink-0"
                                :class="form.payment_method_ids.includes(pm.id) 
                                    ? 'bg-primary-500 border-primary-500 text-white' 
                                    : 'border-surface-600 bg-surface-800 group-hover:border-surface-500'">
                                <Check v-if="form.payment_method_ids.includes(pm.id)" :size="12" class="stroke-[3]" />
                            </div>
                            <span class="text-xs font-semibold uppercase tracking-wide truncate">{{ pm.name }}</span>
                        </label>
                    </div>
                </div>

            </div>

            <div class="px-4 sm:px-6 py-3.5 sm:py-4 border-t border-surface-700 flex justify-end gap-3 bg-surface-900/50">
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