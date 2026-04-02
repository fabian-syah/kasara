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
    User
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


const unitExchangeForm = ref({
    customer_name: "",
    customer_phone: "",
    incoming_source: "luar_pstore",
    incoming_brand_id: null,
    incoming_product_type_id: null,
    incoming_storage: "",
    incoming_condition: "",
    incoming_imei: "",
    incoming_cost_price: 0,
    outgoing_product_detail_id: null,
    reason: "",
    notes: "",
});

const unitExchangePhotos = ref({
    unit: null,
    unitPreview: null,
    customer: null,
    customerPreview: null
});

// Computeds
const filteredExchangeTypes = computed(() => {
    if (!unitExchangeForm.value.incoming_brand_id) return [];
    return props.productTypes.filter(t => t.brand_id === unitExchangeForm.value.incoming_brand_id);
});

const selectedExchangeType = computed(() => {
    if (!unitExchangeForm.value.incoming_product_type_id) return null;
    return props.productTypes.find(t => t.id === unitExchangeForm.value.incoming_product_type_id);
});

const isImeiExchange = computed(() => {
    if (!selectedExchangeType.value) return true;
    const cat = selectedExchangeType.value.category?.toLowerCase();
    return cat === 'imei' || cat === 'hp / gadget';
});

const filteredExchangeStorages = computed(() => {
    if (!unitExchangeForm.value.incoming_product_type_id) return [];
    const set = new Set();
    const type = selectedExchangeType.value;
    if (type?.storage) {
        type.storage.split(/[,]/).forEach(s => {
            const clean = s.trim();
            if (clean) set.add(clean);
        });
    }
    return Array.from(set).sort();
});

const selectedOutgoingItem = computed(() => {
    if (!unitExchangeForm.value.outgoing_product_detail_id) return null;
    return inventoryStore.products.find(p => p.id === unitExchangeForm.value.outgoing_product_detail_id);
});

// Watchers
watch(() => unitExchangeForm.value.incoming_brand_id, () => {
    unitExchangeForm.value.incoming_product_type_id = null;
    unitExchangeForm.value.incoming_storage = "";
    unitExchangeForm.value.incoming_condition = "";
});

watch(() => unitExchangeForm.value.incoming_product_type_id, () => {
    unitExchangeForm.value.incoming_storage = "";
    unitExchangeForm.value.incoming_condition = "";
    if (!isImeiExchange.value && unitExchangeForm.value.incoming_product_type_id) {
        unitExchangeForm.value.incoming_storage = "Non-HP";
    }
});

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

async function handleExchangePhotoUpload(type, event) {
    const file = event.target.files[0];
    if (!file) return;
    unitExchangePhotos.value[type] = file;
    unitExchangePhotos.value[type + 'Preview'] = URL.createObjectURL(file);
}

async function submitUnitExchange(pin = null) {
    if (!unitExchangeForm.value.customer_name || !unitExchangeForm.value.customer_phone || !unitExchangeForm.value.incoming_brand_id || !unitExchangeForm.value.incoming_product_type_id || !unitExchangeForm.value.incoming_storage || !unitExchangeForm.value.incoming_condition || !unitExchangeForm.value.incoming_cost_price || !unitExchangeForm.value.reason || !unitExchangeForm.value.outgoing_product_detail_id) {
        alert("Mohon lengkapi semua data wajib (Customer, Unit Masuk, Unit Keluar, Alasan).");
        return;
    }

    if (!unitExchangePhotos.value.unit) {
        alert("Foto unit wajib diupload.");
        return;
    }

    if (!pin && props.selectedAccountObject?.pin_enabled) {
        emit('verify-pin', (verifiedPin) => submitUnitExchange(verifiedPin));
        return;
    }


    isSubmitting.value = true;
    const formData = new FormData();
    if (unitExchangePhotos.value.unit) formData.append('photo_unit', unitExchangePhotos.value.unit);
    if (unitExchangePhotos.value.customer) formData.append('photo_customer', unitExchangePhotos.value.customer);
    if (pin) formData.append('transaction_pin', pin);

    if (props.selectedAccountObject?.id) formData.append('inventory_user_id', props.selectedAccountObject.id);
    formData.append('customer_name', unitExchangeForm.value.customer_name);
    formData.append('customer_phone', unitExchangeForm.value.customer_phone);
    formData.append('incoming_source', unitExchangeForm.value.incoming_source);
    formData.append('incoming_product_type_id', unitExchangeForm.value.incoming_product_type_id);
    formData.append('incoming_storage', unitExchangeForm.value.incoming_storage);
    formData.append('incoming_condition', unitExchangeForm.value.incoming_condition);
    formData.append('incoming_imei', unitExchangeForm.value.incoming_imei);
    formData.append('incoming_cost_price', unitExchangeForm.value.incoming_cost_price);
    formData.append('outgoing_product_detail_id', unitExchangeForm.value.outgoing_product_detail_id);
    formData.append('reason', unitExchangeForm.value.reason);
    formData.append('notes', unitExchangeForm.value.notes);

    try {
        const response = await api.post('/unit-exchanges', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });

        const data = response.data.data;
        const transaction = {
            id: data.id,
            order_no: data.receipt_id,
            items: [{
                product: data.incoming_product_type,
                name: data.incoming_product_type?.name,
                imei: data.incoming_imei || '-',
                selling_price: data.incoming_cost_price,
                condition: data.incoming_condition,
                storage: data.incoming_storage,
                price: data.incoming_cost_price,
                qty: 1
            }],
            original_price: data.incoming_cost_price,
            grand_total: data.incoming_cost_price,
            total: data.incoming_cost_price,
            paid: data.incoming_cost_price,
            cash: 0,
            transfer: 0,
            category: 'tukar_unit',
            customer_name: data.customer_name,
            customer_phone: data.customer_phone,
            time: new Date().toLocaleString("id-ID"),
        };

        emit("transaction-complete", transaction);

        // Reset form
        unitExchangeForm.value = {
            customer_name: "",
            customer_phone: "",
            incoming_source: "luar_pstore",
            incoming_brand_id: null,
            incoming_product_type_id: null,
            incoming_storage: "",
            incoming_condition: "",
            incoming_imei: "",
            incoming_cost_price: 0,
            outgoing_product_detail_id: null,
            reason: "",
            notes: "",
        };
        unitExchangePhotos.value = { unit: null, unitPreview: null, customer: null, customerPreview: null };

    } catch (error) {
        console.error("Unit exchange failed", error);
        alert(error.response?.data?.message || "Gagal memproses tukar unit");
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
                    Formulir Tukar Unit
                </h3>
                <div
                    class="px-4 py-1.5 bg-amber-100 dark:bg-amber-900/30 text-amber-600 rounded-full text-xs font-black uppercase tracking-widest">
                    Konsolidasi Transaksi
                </div>
            </div>

            <!-- 1. DATA CUSTOMER -->
            <div
                class="bg-surface-50 dark:bg-surface-900/50 p-6 rounded-2xl mb-8 border border-surface-100 dark:border-surface-700">
                <h4 class="text-sm font-black text-primary-600 uppercase tracking-widest mb-6 flex items-center gap-2">
                    <User :size="18" /> Data Customer
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Nama
                            Customer <span class="text-red-500">*</span></label>
                        <input v-model="unitExchangeForm.customer_name" type="text" placeholder="Nama lengkap..."
                            class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-white dark:bg-surface-900 focus:border-primary-500 transition-all outline-none" />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">No
                            WhatsApp <span class="text-red-500">*</span></label>
                        <input v-model="unitExchangeForm.customer_phone" type="text" placeholder="08xxx..."
                            class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-white dark:bg-surface-900 focus:border-primary-500 transition-all outline-none" />
                    </div>
                </div>
            </div>

            <!-- 2. BARANG MASUK & KELUAR -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="space-y-6">
                    <h4
                        class="text-sm font-black text-primary-600 uppercase tracking-widest border-b border-primary-100 dark:border-primary-900/30 pb-2">
                        [1] Barang Masuk (Dari Customer)
                    </h4>
                    <div>
                        <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Sumber
                            <span class="text-red-500">*</span></label>
                        <select v-model="unitExchangeForm.incoming_source"
                            class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none">
                            <option value="luar_pstore">Luar PStore</option>
                            <option value="ex_pstore">Ex PStore</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label
                                class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Brand
                                <span class="text-red-500">*</span></label>
                            <select v-model="unitExchangeForm.incoming_brand_id"
                                class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none">
                                <option :value="null" disabled>Pilih Brand</option>
                                <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label
                                class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Tipe
                                <span class="text-red-500">*</span></label>
                            <select v-model="unitExchangeForm.incoming_product_type_id"
                                class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none"
                                :disabled="!unitExchangeForm.incoming_brand_id">
                                <option :value="null" disabled>Pilih Tipe</option>
                                <option v-for="p in filteredExchangeTypes" :key="p.id" :value="p.id">{{
                                    p.name }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label
                                class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Storage
                                <span class="text-red-500">*</span></label>
                            <select v-model="unitExchangeForm.incoming_storage"
                                class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none"
                                :disabled="!unitExchangeForm.incoming_product_type_id">
                                <option value="" disabled>Pilih Storage</option>
                                <option v-for="s in filteredExchangeStorages" :key="s" :value="s">{{ s }}
                                </option>
                                <option v-if="!isImeiExchange" value="Non-HP">Non-HP</option>
                            </select>
                        </div>
                        <div>
                            <label
                                class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Kategori
                                <span class="text-red-500">*</span></label>
                            <select v-model="unitExchangeForm.incoming_condition"
                                class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none">
                                <option value="" disabled>Pilih Kategori</option>
                                <option value="new">New</option>
                                <option value="second">Second / SCD</option>
                                <option value="ex_ibox">Ex iBox</option>
                            </select>
                        </div>
                    </div>
                    <div v-if="isImeiExchange">
                        <label
                            class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Masukkan
                            IMEI <span class="text-red-500">*</span></label>
                        <input v-model="unitExchangeForm.incoming_imei" type="text" placeholder="15 digit IMEI..."
                            class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none" />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Harga
                            Tukar Unit / Barang Masuk <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <span
                                class="absolute left-4 top-1/2 -translate-y-1/2 text-sm font-bold text-text-secondary">Rp</span>
                            <input type="text" :value="formatNumber(unitExchangeForm.incoming_cost_price)"
                                @input="e => { unitExchangeForm.incoming_cost_price = parseNumber(e.target.value); e.target.value = formatNumber(unitExchangeForm.incoming_cost_price); }"
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
                        [2] Barang Keluar (Pilih Stok Toko)
                    </h4>
                    <div>
                        <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Cari
                            &
                            Pilih Unit Keluar <span class="text-red-500">*</span></label>
                        <select v-model="unitExchangeForm.outgoing_product_detail_id"
                            class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none">
                            <option :value="null" disabled>-- Pilih Unit dari Inventory --</option>
                            <option
                                v-for="item in inventoryStore.products.filter(p => (p.imei || p.stock > 0) && p.status !== 'sold')"
                                :key="item.id" :value="item.id">
                                [{{ item.product?.brand || '-' }}] {{ item.product?.name || item.name }}
                                - {{ item.storage || '-' }} - {{ item.condition || 'SCD' }}
                                - {{ item.imei ? 'IMEI: ' + item.imei : 'Stok: ' + (item.stock ||
                                    item.quantity) }}
                            </option>
                        </select>
                        <p v-if="selectedOutgoingItem"
                            class="mt-3 p-4 bg-primary-50 dark:bg-primary-900/10 rounded-xl border border-primary-100 dark:border-primary-800 text-xs font-semibold text-primary-700 dark:text-primary-400">
                            Unit Terpilih: {{ selectedOutgoingItem.product?.name ||
                                selectedOutgoingItem.name }} ({{
                                selectedOutgoingItem.imei || 'Non-IMEI' }})
                        </p>
                    </div>

                    <!-- Photo and Additional Media -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label
                                class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2 text-center">Foto
                                Unit <span class="text-red-500">*</span></label>
                            <div @click="$refs.unitExchangeInput.click()"
                                class="relative border-2 border-dashed border-surface-300 dark:border-surface-600 rounded-xl aspect-square flex flex-col items-center justify-center cursor-pointer hover:bg-surface-50 dark:hover:bg-surface-800 transition-all overflow-hidden group">
                                <template v-if="unitExchangePhotos.unitPreview">
                                    <img :src="unitExchangePhotos.unitPreview" class="w-full h-full object-cover" />
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
                            <input type="file" ref="unitExchangeInput"
                                @change="e => handleExchangePhotoUpload('unit', e)" accept="image/*" class="hidden"
                                capture="environment" />
                        </div>
                        <div>
                            <label
                                class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2 text-center">Foto
                                Customer</label>
                            <div @click="$refs.customerExchangeInput.click()"
                                class="relative border-2 border-dashed border-surface-300 dark:border-surface-600 rounded-xl aspect-square flex flex-col items-center justify-center cursor-pointer hover:bg-surface-50 dark:hover:bg-surface-800 transition-all overflow-hidden group">
                                <template v-if="unitExchangePhotos.customerPreview">
                                    <img :src="unitExchangePhotos.customerPreview" class="w-full h-full object-cover" />
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
                            <input type="file" ref="customerExchangeInput"
                                @change="e => handleExchangePhotoUpload('customer', e)" accept="image/*" class="hidden"
                                capture="environment" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. ALASAN & CATATAN -->
            <div class="mt-8 space-y-6">
                <h4
                    class="text-sm font-black text-primary-600 uppercase tracking-widest border-b border-primary-100 dark:border-primary-900/30 pb-2">
                    Alasan & Catatan
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Alasan
                            Tukar Unit <span class="text-red-500">*</span></label>
                        <textarea v-model="unitExchangeForm.reason" rows="3"
                            class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none text-sm"
                            placeholder="Kenapa barang ini ditukar? (Wajib diisi)"></textarea>
                    </div>
                    <div>
                        <label
                            class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Keterangan
                            Tambahan (Opsional)</label>
                        <textarea v-model="unitExchangeForm.notes" rows="3"
                            class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none text-sm"
                            placeholder="Catatan tambahan..."></textarea>
                    </div>
                </div>
            </div>

            <!-- Submit Section -->
            <div class="mt-12 pt-8 border-t border-surface-100 dark:border-surface-700 flex flex-col sm:flex-row gap-4">
                <button @click="emit('back')"
                    class="flex-1 py-4 bg-surface-100 dark:bg-surface-700 text-text-primary rounded-2xl font-black uppercase tracking-widest hover:bg-surface-200 transition-all">
                    Kembali Pilih Kategori
                </button>
                <button @click="submitUnitExchange()" :disabled="isSubmitting"
                    class="flex-[2] py-4 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white rounded-2xl font-black uppercase tracking-widest shadow-xl shadow-emerald-500/20 transition-all flex items-center justify-center gap-3">
                    <Loader2 v-if="isSubmitting" class="animate-spin" :size="24" />
                    <template v-else>
                        <Save :size="24" /> Selesaikan Tukar Unit
                    </template>
                </button>
            </div>
        </div>
    </div>
</template>
