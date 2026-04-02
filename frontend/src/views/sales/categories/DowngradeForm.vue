<script setup>
import { ref, computed, watch } from "vue";
import api from "../../../api/axios";
import { useAuthStore } from "../../../store/auth";
import { useInventoryStore } from "../../../store/inventory";
import { formatCurrency } from "../../../utils/formatters";
import {
    Plus,
    Loader2,
    Save,
    ArrowLeft,
    User,
    AlertCircle
} from "lucide-vue-next";

const props = defineProps({
    brands: Array,
    productTypes: Array,
    productPrices: Array,
    availablePaymentMethods: Array,
    salesAccount: String,
    selectedAccountObject: Object
});

const emit = defineEmits(["back", "transaction-complete", "verify-pin"]);

const authStore = useAuthStore();
const inventoryStore = useInventoryStore();
const isSubmitting = ref(false);

const downgradeForm = ref({
    customer_name: "",
    customer_phone: "",
    incoming_source: "luar_pstore",
    incoming_brand_id: null,
    incoming_product_type_id: null,
    incoming_storage: "",
    incoming_condition: "second",
    incoming_imei: "",
    incoming_cost_price: 0,
    outgoing_product_detail_id: null,
    outgoing_price: 0,
    payment_method_id: null,
    reason: "",
    notes: "",
});

const downgradePhotos = ref({
    unit: null,
    unitPreview: null,
    customer: null,
    customerPreview: null
});

// Computeds
const filteredDowngradeTypes = computed(() => {
    if (!downgradeForm.value.incoming_brand_id) return [];
    return props.productTypes.filter(t => t.brand_id === downgradeForm.value.incoming_brand_id);
});

const selectedDowngradeType = computed(() => {
    if (!downgradeForm.value.incoming_product_type_id) return null;
    return props.productTypes.find(t => t.id === downgradeForm.value.incoming_product_type_id);
});

const isImeiDowngrade = computed(() => {
    if (!selectedDowngradeType.value) return true;
    const cat = selectedDowngradeType.value.category?.toLowerCase();
    return cat === 'imei' || cat === 'hp / gadget' || cat === 'hp/gadget';
});

const filteredDowngradeStorages = computed(() => {
    if (!downgradeForm.value.incoming_product_type_id) return [];
    const set = new Set();
    const type = selectedDowngradeType.value;
    if (type?.storage) {
        type.storage.split(/[,]/).forEach(s => {
            const clean = s.trim();
            if (clean) set.add(clean);
        });
    }
    return Array.from(set).sort();
});

const selectedOutgoingDowngrade = computed(() => {
    if (!downgradeForm.value.outgoing_product_detail_id) return null;
    return inventoryStore.products.find(p => p.id === downgradeForm.value.outgoing_product_detail_id);
});

const downgradePriceDiff = computed(() => {
    return (downgradeForm.value.outgoing_price || 0) - (downgradeForm.value.incoming_cost_price || 0);
});

// Watchers
watch(() => downgradeForm.value.incoming_brand_id, () => {
    downgradeForm.value.incoming_product_type_id = null;
    downgradeForm.value.incoming_storage = "";
});

watch(() => downgradeForm.value.incoming_product_type_id, () => {
    downgradeForm.value.incoming_storage = "";
    if (!isImeiDowngrade.value && downgradeForm.value.incoming_product_type_id) {
        downgradeForm.value.incoming_storage = "Non-HP";
    }
});

watch(() => downgradeForm.value.outgoing_product_detail_id, (newId) => {
    if (newId) {
        const item = inventoryStore.products.find(p => p.id === newId);
        if (item) {
            const selling = parseFloat(item.selling_price || item.price || 0);
            const cost = parseFloat(item.cost_price || 0);
            downgradeForm.value.outgoing_price = selling > 0 ? selling : (cost > 0 ? cost : 0);
        }
    } else {
        downgradeForm.value.outgoing_price = 0;
    }
});

// Init payment method
watch(() => props.availablePaymentMethods, (methods) => {
    if (methods?.length > 0 && !downgradeForm.value.payment_method_id) {
        const cashMethod = methods.find(p => p.category?.toLowerCase() === 'cash' || p.name?.toLowerCase() === 'tunai');
        downgradeForm.value.payment_method_id = cashMethod ? cashMethod.id : methods[0].id;
    }
}, { immediate: true });

// Helpers
function formatNumber(n) {
    if (!n) return "0";
    return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

function parseNumber(s) {
    if (!s) return 0;
    if (typeof s === 'number') return s;
    let clean = s.toString().replace(/Rp/g, "").replace(/\s/g, "");
    if (clean.endsWith('.00') || clean.endsWith(',00')) {
        clean = clean.slice(0, -3);
    }
    const finalClean = clean.replace(/[^0-9]/g, "");
    return parseInt(finalClean) || 0;
}

function handleDowngradePhotoUpload(type, event) {
    const file = event.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = (e) => {
        if (type === 'unit') {
            downgradePhotos.value.unit = file;
            downgradePhotos.value.unitPreview = e.target.result;
        } else {
            downgradePhotos.value.customer = file;
            downgradePhotos.value.customerPreview = e.target.result;
        }
    };
    reader.readAsDataURL(file);
}

async function submitDowngrade(pin = null) {
    if (!downgradeForm.value.customer_name || !downgradeForm.value.customer_phone || !downgradeForm.value.incoming_brand_id || !downgradeForm.value.incoming_product_type_id || !downgradeForm.value.incoming_storage || !downgradeForm.value.incoming_condition || !downgradeForm.value.incoming_cost_price || !downgradeForm.value.outgoing_product_detail_id || !downgradeForm.value.outgoing_price || !downgradeForm.value.reason || !downgradeForm.value.payment_method_id) {
        alert("Mohon lengkapi semua data wajib (Customer, Barang Masuk, Barang Keluar, Metode Bayar, & Alasan).");
        return;
    }

    if (!downgradePhotos.value.unit && !downgradePhotos.value.customer) {
        alert("Minimal pilih salah satu foto (Unit atau Customer).");
        return;
    }

    if (!pin && props.selectedAccountObject?.pin_enabled) {
        emit('verify-pin', (verifiedPin) => submitDowngrade(verifiedPin));
        return;
    }

    isSubmitting.value = true;
    const formData = new FormData();
    if (downgradePhotos.value.unit) formData.append('photo_unit', downgradePhotos.value.unit);
    if (downgradePhotos.value.customer) formData.append('photo_customer', downgradePhotos.value.customer);
    if (pin) formData.append('transaction_pin', pin);

    if (props.selectedAccountObject?.id) formData.append('inventory_user_id', props.selectedAccountObject.id);
    formData.append('customer_name', downgradeForm.value.customer_name);
    formData.append('customer_phone', downgradeForm.value.customer_phone);
    formData.append('incoming_source', downgradeForm.value.incoming_source);
    formData.append('incoming_product_type_id', downgradeForm.value.incoming_product_type_id);
    formData.append('incoming_storage', downgradeForm.value.incoming_storage);
    formData.append('incoming_condition', downgradeForm.value.incoming_condition);
    formData.append('incoming_imei', downgradeForm.value.incoming_imei);
    formData.append('incoming_cost_price', downgradeForm.value.incoming_cost_price);

    formData.append('outgoing_product_detail_id', downgradeForm.value.outgoing_product_detail_id);
    formData.append('outgoing_price', downgradeForm.value.outgoing_price);
    formData.append('price_difference', downgradePriceDiff.value);
    formData.append('payment_method_id', downgradeForm.value.payment_method_id);
    formData.append('reason', downgradeForm.value.reason);
    formData.append('notes', downgradeForm.value.notes);
    formData.append('category', 'downgrade');

    try {
        const response = await api.post('/downgrades', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });


        const data = response.data.data;
        const transaction = {
            id: data.id,
            order_no: data.receipt_id,
            items: [
                {
                    name: 'Downgrade OUT: ' + (selectedOutgoingDowngrade.value?.product?.name || selectedOutgoingDowngrade.value?.name || ''),
                    imei: selectedOutgoingDowngrade.value?.imei || '-',
                    price: downgradeForm.value.outgoing_price,
                    qty: 1
                },
                {
                    name: 'Downgrade IN: ' + (selectedDowngradeType.value?.name || ''),
                    imei: downgradeForm.value.incoming_imei || '-',
                    price: -downgradeForm.value.incoming_cost_price,
                    qty: 1
                }
            ],
            original_price: downgradePriceDiff.value,
            grand_total: downgradePriceDiff.value,
            total: downgradePriceDiff.value,
            paid: downgradePriceDiff.value,
            category: 'downgrade',
            customer_name: data.customer_name,
            customer_phone: data.customer_phone,
            time: new Date().toLocaleString("id-ID"),
        };

        emit("transaction-complete", transaction);

        // Reset form
        downgradeForm.value = {
            customer_name: "",
            customer_phone: "",
            incoming_source: "luar_pstore",
            incoming_brand_id: null,
            incoming_product_type_id: null,
            incoming_storage: "",
            incoming_condition: "second",
            incoming_imei: "",
            incoming_cost_price: 0,
            outgoing_product_detail_id: null,
            outgoing_price: 0,
            payment_method_id: null,
            reason: "",
            notes: "",
        };
        downgradePhotos.value = { unit: null, unitPreview: null, customer: null, customerPreview: null };

    } catch (error) {
        console.error("Downgrade failed", error);
        alert(error.response?.data?.message || "Gagal memproses downgrade");
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <div
        class="flex-1 overflow-y-auto custom-scrollbar bg-white dark:bg-surface-800 rounded-[1.5rem] sm:rounded-[2rem] border border-surface-200 dark:border-surface-700 p-4 sm:p-8 shadow-xl">
        <div class="max-w-4xl mx-auto">
            <div class="flex items-center justify-between mb-8">
                <h3 class="text-2xl font-black text-text-primary flex items-center gap-3">
                    <ArrowLeft :size="28" class="text-primary-500 cursor-pointer" @click="emit('back')" />
                    Formulir Downgrade
                </h3>
                <div
                    class="px-4 py-1.5 bg-red-100 dark:bg-red-900/30 text-red-600 rounded-full text-xs font-black uppercase tracking-widest">
                    DOWNGRADE SYSTEM
                </div>
            </div>

            <!-- 1. DATA CUSTOMER -->
            <div
                class="bg-surface-50 dark:bg-surface-900/50 p-6 rounded-2xl mb-8 border border-surface-100 dark:border-surface-700">
                <h4 class="text-sm font-black text-primary-600 uppercase tracking-widest mb-6 flex items-center gap-2">
                    <User :size="18" /> DATA CUSTOMER
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">NAMA
                            CUSTOMER <span class="text-red-500">*</span></label>
                        <input v-model="downgradeForm.customer_name" type="text" placeholder="Nama lengkap..."
                            class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none" />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">NO
                            WHATSAPP <span class="text-red-500">*</span></label>
                        <input v-model="downgradeForm.customer_phone" type="text" placeholder="08xxx..."
                            class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none" />
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- 2. BARANG MASUK -->
                <div class="space-y-6">
                    <h4
                        class="text-sm font-black text-emerald-600 uppercase tracking-widest border-b border-emerald-100 dark:border-emerald-900/30 pb-2">
                        [1] BARANG MASUK
                    </h4>
                    <div>
                        <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">SUMBER
                            HANDPHONE <span class="text-red-500">*</span></label>
                        <select v-model="downgradeForm.incoming_source"
                            class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none">
                            <option value="ex_pstore">Ex PSTORE</option>
                            <option value="luar_pstore">Luar PSTORE</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label
                                class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">PILIH
                                BRAND
                                <span class="text-red-500">*</span></label>
                            <select v-model="downgradeForm.incoming_brand_id"
                                class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none">
                                <option :value="null" disabled>Pilih Brand</option>
                                <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label
                                class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">PILIH
                                TIPE
                                <span class="text-red-500">*</span></label>
                            <select v-model="downgradeForm.incoming_product_type_id"
                                class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none"
                                :disabled="!downgradeForm.incoming_brand_id">
                                <option :value="null" disabled>Pilih Tipe</option>
                                <option v-for="p in filteredDowngradeTypes" :key="p.id" :value="p.id">{{
                                    p.name }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label
                                class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">PILIH
                                STORAGE
                                <span class="text-red-500">*</span></label>
                            <select v-model="downgradeForm.incoming_storage"
                                class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none"
                                :disabled="!downgradeForm.incoming_product_type_id">
                                <option value="" disabled>Pilih Storage</option>
                                <option v-for="s in filteredDowngradeStorages" :key="s" :value="s">{{ s }}
                                </option>
                                <option v-if="!isImeiDowngrade" value="Non-HP">Non-HP</option>
                            </select>
                        </div>
                        <div>
                            <label
                                class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">PILIH
                                KATEGORI
                                <span class="text-red-500">*</span></label>
                            <select v-model="downgradeForm.incoming_condition"
                                class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none">
                                <option value="" disabled>Pilih Kategori</option>
                                <option value="new">New</option>
                                <option value="second">SCD</option>
                                <option value="ex_ibox">Ex iBox</option>
                            </select>
                        </div>
                    </div>
                    <div v-if="isImeiDowngrade">
                        <label
                            class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">MASUKKAN
                            IMEI <span class="text-red-500">*</span></label>
                        <input v-model="downgradeForm.incoming_imei" type="text" placeholder="15 digit IMEI..."
                            class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none font-mono" />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">HARGA
                            DOWNGRADE / BARANG MASUK <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <span
                                class="absolute left-4 top-1/2 -translate-y-1/2 text-sm font-bold text-text-secondary">Rp</span>
                            <input type="text" :value="formatNumber(downgradeForm.incoming_cost_price)"
                                @input="e => { downgradeForm.incoming_cost_price = parseNumber(e.target.value); e.target.value = formatNumber(downgradeForm.incoming_cost_price); }"
                                class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl pl-10 pr-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none font-black text-lg text-primary-600" />
                        </div>
                        <p class="mt-1 text-[10px] text-text-secondary font-medium italic">*Otomatis jadi
                            harga modal</p>
                    </div>
                </div>

                <!-- 3. BARANG KELUAR -->
                <div class="space-y-6">
                    <h4
                        class="text-sm font-black text-amber-600 uppercase tracking-widest border-b border-amber-100 dark:border-amber-900/30 pb-2">
                        [2] BARANG KELUAR
                    </h4>
                    <div>
                        <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">PILIH
                            STOK
                            UNITS <span class="text-red-500">*</span></label>
                        <select v-model="downgradeForm.outgoing_product_detail_id"
                            class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none">
                            <option :value="null" disabled>-- Pilih Stok --</option>
                            <option
                                v-for="item in inventoryStore.products.filter(p => (p.imei || p.stock > 0) && p.status !== 'sold')"
                                :key="item.id" :value="item.id">
                                [{{ item.product?.brand || '-' }}] {{ item.product?.name || item.name }}
                                - {{ item.storage || '-' }} - {{ item.condition || 'SCD' }}
                                - {{ item.imei ? 'IMEI: ' + item.imei : 'Stok: ' + (item.stock ||
                                    item.quantity) }}
                                - (Modal: Rp {{ formatNumber(item.cost_price || 0) }})
                            </option>
                        </select>
                        <div v-if="selectedOutgoingDowngrade"
                            class="mt-3 p-4 bg-primary-50 dark:bg-primary-900/10 rounded-xl border border-primary-100 dark:border-primary-800 space-y-1">
                            <p class="text-[10px] font-black text-primary-600 uppercase tracking-widest">Detail
                                Terpilih:</p>
                            <p class="text-xs font-bold text-primary-700 dark:text-primary-300">
                                {{ selectedOutgoingDowngrade.product?.name || selectedOutgoingDowngrade.name }} ({{
                                    selectedOutgoingDowngrade.storage || '-' }})
                            </p>
                            <p class="text-[10px] font-mono text-primary-600/70">
                                {{ selectedOutgoingDowngrade.imei || 'Non-IMEI' }} | Modal: Rp {{
                                    formatNumber(selectedOutgoingDowngrade.cost_price) }}
                            </p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">ISI
                            HARGA
                            BARANG KELUAR <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <span
                                class="absolute left-4 top-1/2 -translate-y-1/2 text-sm font-bold text-text-secondary">Rp</span>
                            <input type="text" :value="formatNumber(downgradeForm.outgoing_price)"
                                @input="e => { downgradeForm.outgoing_price = parseNumber(e.target.value); e.target.value = formatNumber(downgradeForm.outgoing_price); }"
                                class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl pl-10 pr-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none font-black text-lg text-primary-600" />
                        </div>
                    </div>

                    <!-- Photo Section -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-6">
                        <div>
                            <label
                                class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2 text-center">FOTO
                                UNIT <span class="text-red-500">*</span></label>
                            <div @click="$refs.unitDGInput.click()"
                                class="relative border-2 border-dashed border-surface-300 dark:border-surface-600 rounded-xl aspect-square flex flex-col items-center justify-center cursor-pointer hover:bg-surface-50 dark:hover:bg-surface-800 transition-all overflow-hidden group">
                                <template v-if="downgradePhotos.unitPreview">
                                    <img :src="downgradePhotos.unitPreview" class="w-full h-full object-cover" />
                                    <div
                                        class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity">
                                        <span class="text-white text-[10px] font-black uppercase">Ganti</span>
                                    </div>
                                </template>
                                <template v-else>
                                    <Plus :size="24" class="text-text-secondary mb-1" />
                                    <span class="text-[9px] font-black text-text-secondary uppercase">Upload
                                        Unit</span>
                                </template>
                            </div>
                            <input type="file" ref="unitDGInput" @change="e => handleDowngradePhotoUpload('unit', e)"
                                accept="image/*" class="hidden" capture="environment" />
                        </div>
                        <div>
                            <label
                                class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2 text-center">FOTO
                                CUSTOMER</label>
                            <div @click="$refs.customerDGInput.click()"
                                class="relative border-2 border-dashed border-surface-300 dark:border-surface-600 rounded-xl aspect-square flex flex-col items-center justify-center cursor-pointer hover:bg-surface-50 dark:hover:bg-surface-800 transition-all overflow-hidden group">
                                <template v-if="downgradePhotos.customerPreview">
                                    <img :src="downgradePhotos.customerPreview" class="w-full h-full object-cover" />
                                    <div
                                        class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity">
                                        <span class="text-white text-[10px] font-black uppercase">Ganti</span>
                                    </div>
                                </template>
                                <template v-else>
                                    <Plus :size="24" class="text-text-secondary mb-1" />
                                    <span class="text-[9px] font-black text-text-secondary uppercase">Upload
                                        Customer</span>
                                </template>
                            </div>
                            <input type="file" ref="customerDGInput"
                                @change="e => handleDowngradePhotoUpload('customer', e)" accept="image/*" class="hidden"
                                capture="environment" />
                        </div>
                    </div>
                    <p class="text-[10px] text-text-secondary italic text-center">*Minimal upload salah satu foto</p>
                </div>
            </div>

            <!-- 4. ALASAN, PEMBAYARAN & SUMMARY -->
            <div class="mt-8 space-y-6">
                <h4
                    class="text-sm font-black text-primary-600 uppercase tracking-widest border-b border-primary-100 dark:border-primary-900/30 pb-2">
                    ALASAN & KETERANGAN
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-start">
                    <div class="space-y-6">
                        <div>
                            <label
                                class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">ALASAN
                                DOWNGRADE <span class="text-red-500">*</span></label>
                            <textarea v-model="downgradeForm.reason" rows="2"
                                class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none text-sm"
                                placeholder="Kenapa barang ini di-downgrade? (Wajib diisi)"></textarea>
                        </div>
                        <div>
                            <label
                                class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">KETERANGAN
                                (OPSIONAL)</label>
                            <textarea v-model="downgradeForm.notes" rows="2"
                                class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none text-sm"
                                placeholder="Tambahan catatan jika ada..."></textarea>
                        </div>
                        <div>
                            <label
                                class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">METODE
                                PEMBAYARAN <span class="text-red-500">*</span></label>
                            <select v-model="downgradeForm.payment_method_id"
                                class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none">
                                <option :value="null" disabled>Pilih Metode Bayar...</option>
                                <option v-for="m in availablePaymentMethods" :key="m.id" :value="m.id">{{
                                    m.name
                                    }}</option>
                            </select>
                        </div>
                    </div>

                    <!-- Financial summary card -->
                    <div
                        class="p-8 bg-surface-900 dark:bg-surface-950 rounded-[2rem] shadow-2xl border border-surface-800 text-center transform transition-all hover:scale-[1.02]">
                        <div class="grid grid-cols-2 gap-4 mb-6">
                            <div class="text-left">
                                <span
                                    class="text-[9px] font-black text-text-secondary uppercase tracking-widest block mb-1">HARGA
                                    UNIT KELUAR</span>
                                <p class="text-lg font-bold text-text-primary">
                                    {{ formatCurrency(downgradeForm.outgoing_price) }}
                                </p>
                            </div>
                            <div class="text-right">
                                <span
                                    class="text-[9px] font-black text-text-secondary uppercase tracking-widest block mb-1">HARGA
                                    UNIT MASUK</span>
                                <p class="text-lg font-bold text-text-primary">
                                    {{ formatCurrency(downgradeForm.incoming_cost_price) }}
                                </p>
                            </div>
                        </div>

                        <div class="pt-6 border-t border-surface-800">
                            <span
                                class="text-[10px] font-black text-primary-500 uppercase tracking-[0.2em] block mb-2">SELISIH
                                HARGA (SISA BAYAR)</span>
                            <p class="text-4xl sm:text-5xl font-black text-white px-2 py-1 leading-none">
                                {{ formatCurrency(Math.abs(downgradePriceDiff)) }}
                            </p>
                        </div>
                        <div
                            class="mt-6 px-4 py-2 bg-primary-500/10 rounded-full inline-flex items-center gap-2 text-[10px] text-primary-500 font-black uppercase tracking-widest border border-primary-500/20">
                            <AlertCircle :size="14" />
                            <span>
                                TOKO BAYAR SELISIH KE CUSTOMER</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit Section -->
            <div class="mt-12 pt-8 border-t border-surface-100 dark:border-surface-700 flex flex-col sm:flex-row gap-4">
                <button @click="emit('back')"
                    class="flex-1 py-4 bg-surface-100 dark:bg-surface-700 text-text-primary rounded-2xl font-black uppercase tracking-widest hover:bg-surface-200 transition-all active:scale-95">
                    Kembali
                </button>
                <button @click="submitDowngrade()" :disabled="isSubmitting"
                    class="flex-[2] py-4 bg-primary-600 hover:bg-primary-500 disabled:opacity-50 text-white rounded-2xl font-black uppercase tracking-widest shadow-xl shadow-primary-500/20 transition-all flex items-center justify-center gap-3 active:scale-95">
                    <Loader2 v-if="isSubmitting" class="animate-spin" :size="24" />
                    <template v-else>
                        <Save :size="24" /> Simpan Downgrade (Selesai)
                    </template>
                </button>
            </div>
        </div>
    </div>
</template>
