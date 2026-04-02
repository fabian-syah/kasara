<script setup>
import { ref, computed, watch } from "vue";
import api from "../../../api/axios";
import { useAuthStore } from "../../../store/auth";
import { formatCurrency } from "../../../utils/formatters";
import {
    Plus,
    Loader2,
    Save,
    Receipt,
    ArrowLeft
} from "lucide-vue-next";

const props = defineProps({
    brands: Array,
    productTypes: Array,
    productPrices: Array,
    availablePaymentMethods: Array,
    salesAccount: String,
    selectedAccountObject: Object
});

const emit = defineEmits(["back", "transaction-complete", "reset"]);

const authStore = useAuthStore();
const isSubmitting = ref(false);


const tradeInForm = ref({
    customer_name: "",
    customer_phone: "",
    source: "luar_pstore",
    brand_id: null,
    product_type_id: null,
    storage: "",
    condition: "",
    imei: "",
    imeis_raw: "",
    quantity: 1,
    buy_price: 0,
    payment_method_id: null,
    reason: "",
    notes: "",
});

const tradeInPhotos = ref({
    unit: null,
    unitPreview: null,
    customer: null,
    customerPreview: null
});

// Computeds
const filteredTradeInTypes = computed(() => {
    if (!tradeInForm.value.brand_id) return [];
    return props.productTypes.filter(t => t.brand_id === tradeInForm.value.brand_id);
});

const selectedTradeInType = computed(() => {
    if (!tradeInForm.value.product_type_id) return null;
    return props.productTypes.find(t => t.id === tradeInForm.value.product_type_id);
});

const isImeiTradeIn = computed(() => {
    if (!selectedTradeInType.value) return true;
    const cat = selectedTradeInType.value.category?.toLowerCase();
    return cat === 'imei' || cat === 'hp / gadget';
});

const filteredTradeInCapacities = computed(() => {
    if (!tradeInForm.value.product_type_id) return [];
    const set = new Set();
    props.productPrices
        .filter(p => p.product_type_id === tradeInForm.value.product_type_id)
        .forEach(p => { if (p.storage) set.add(p.storage); });
    if (selectedTradeInType.value?.storage) {
        selectedTradeInType.value.storage.split(/[,]/).forEach(s => {
            const clean = s.trim();
            if (clean) set.add(clean);
        });
    }
    return Array.from(set).sort();
});

const filteredTradeInConditions = computed(() => {
    const defaults = ['new', 'second', 'ex_ibox'];
    const set = new Set(defaults);
    if (tradeInForm.value.product_type_id) {
        props.productPrices
            .filter(p => p.product_type_id === tradeInForm.value.product_type_id && p.storage === tradeInForm.value.storage)
            .forEach(p => { if (p.condition) set.add(p.condition); });
    }
    return Array.from(set);
});

const totalTradeInUnits = computed(() => {
    return tradeInForm.value.quantity || 0;
});

// Watchers
watch(() => tradeInForm.value.brand_id, () => {
    tradeInForm.value.product_type_id = null;
    tradeInForm.value.storage = "";
    tradeInForm.value.condition = "";
});

watch(() => tradeInForm.value.product_type_id, () => {
    tradeInForm.value.storage = "";
    tradeInForm.value.condition = "";
});

watch(() => tradeInForm.value.storage, () => {
    tradeInForm.value.condition = "";
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

const displayBuyPrice = ref("0");
// No longer needed: handleBuyPriceInput replaced by v-money

function handleImeiInput(e) {
    const val = e.target.value;
    const filtered = val.replace(/[^0-9,\n]/g, "");
    tradeInForm.value.imeis_raw = filtered;
    e.target.value = filtered;
}

const handlePhotoUpload = (type, e) => {
    const file = e.target.files[0];
    if (!file) return;
    tradeInPhotos.value[type] = file;
    tradeInPhotos.value[type + 'Preview'] = URL.createObjectURL(file);
}

// Init payment method
watch(() => props.availablePaymentMethods, (methods) => {
    if (methods?.length > 0 && !tradeInForm.value.payment_method_id) {
        const cashMethod = methods.find(p => p.category?.toLowerCase() === 'cash' || p.name?.toLowerCase() === 'tunai');
        tradeInForm.value.payment_method_id = cashMethod ? cashMethod.id : methods[0].id;
    }
}, { immediate: true });

async function submitTradeIn(pin = null) {
    if (!tradeInForm.value.customer_name || !tradeInForm.value.customer_phone || !tradeInForm.value.brand_id || !tradeInForm.value.product_type_id || !tradeInForm.value.storage || !tradeInForm.value.condition || !tradeInForm.value.buy_price) {
        alert("Mohon lengkapi semua data wajib (Nama, WA, Brand, Tipe, Kapasitas, Kondisi, Harga).");
        return;
    }

    if (!tradeInPhotos.value.unit) {
        alert("Foto unit wajib diupload.");
        return;
    }

    if (!pin && props.selectedAccountObject?.pin_enabled) {
        emit('verify-pin', (verifiedPin) => submitTradeIn(verifiedPin));
        return;
    }


    isSubmitting.value = true;
    const formData = new FormData();
    if (tradeInPhotos.value.unit) formData.append('photo_unit', tradeInPhotos.value.unit);
    if (tradeInPhotos.value.customer) formData.append('photo_customer', tradeInPhotos.value.customer);
    if (pin) formData.append('transaction_pin', pin);

    if (props.selectedAccountObject?.id) formData.append('inventory_user_id', props.selectedAccountObject.id);
    formData.append('customer_name', tradeInForm.value.customer_name);
    formData.append('customer_phone', tradeInForm.value.customer_phone);
    formData.append('brand_id', tradeInForm.value.brand_id);
    formData.append('product_type_id', tradeInForm.value.product_type_id);
    formData.append('source', tradeInForm.value.source);
    formData.append('storage', tradeInForm.value.storage);
    formData.append('condition', tradeInForm.value.condition);
    formData.append('buy_price', tradeInForm.value.buy_price);
    formData.append('payment_method_id', tradeInForm.value.payment_method_id);
    formData.append('reason', tradeInForm.value.reason);
    formData.append('notes', tradeInForm.value.notes);

    if (isImeiTradeIn.value) {
        const list = tradeInForm.value.imeis_raw.split(/[\n,]/).map(i => i.trim()).filter(i => i !== "");
        if (list.length === 0) { alert("Masukkan Minimal 1 IMEI"); isSubmitting.value = false; return; }
        if (list.some(i => !/^\d+$/.test(i))) { alert("IMEI Harus Berupa Angka (Numeric 0-9)"); isSubmitting.value = false; return; }
        list.forEach(i => formData.append('imeis[]', i));
    } else {
        formData.append('quantity', tradeInForm.value.quantity);
    }

    try {
        const response = await api.post('/trade-ins', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });

        const data = response.data.data;
        const batchCount = response.data.count || 1;
        const transaction = {
            id: data.id,
            order_no: data.receipt_id,
            items: [{
                product: data.product_type,
                name: data.product_type?.name,
                imei: isImeiTradeIn.value ? (batchCount > 1 ? `${batchCount} Unit (Batch)` : data.imei) : '-',
                selling_price: data.buy_price,
                condition: data.condition,
                storage: data.storage,
                ram: data.ram,
                price: data.buy_price,
                qty: batchCount
            }],
            original_price: data.buy_price * batchCount,
            grand_total: data.buy_price * batchCount,
            total: data.buy_price * batchCount,
            paid: data.buy_price * batchCount,
            cash: data.payment_method?.category?.toLowerCase() === 'cash' ? (data.buy_price * batchCount) : 0,
            transfer: data.payment_method?.category?.toLowerCase() === 'transfer' ? (data.buy_price * batchCount) : 0,
            payment_method_name: data.payment_method?.name,
            category: 'angkat_barang',
            customer_name: data.customer_name,
            customer_phone: data.customer_phone,
            time: new Date().toLocaleString("id-ID"),
        };

        emit("transaction-complete", transaction);

        // Reset form
        tradeInForm.value = {
            customer_name: "",
            customer_phone: "",
            source: "luar_pstore",
            brand_id: null,
            product_type_id: null,
            storage: "",
            condition: "",
            imei: "",
            imeis_raw: "",
            quantity: 1,
            buy_price: 0,
            payment_method_id: props.availablePaymentMethods?.[0]?.id || null,
            reason: "",
            notes: "",
        };
        tradeInPhotos.value = { unit: null, unitPreview: null, customer: null, customerPreview: null };
        displayBuyPrice.value = "0";

    } catch (error) {
        console.error("Trade-in failed", error);
        alert(error.response?.data?.message || "Gagal memproses barang angkat");
    } finally {
        isSubmitting.value = false;
    }
}


</script>

<template>
    <div
        class="flex-1 overflow-y-auto custom-scrollbar bg-white dark:bg-surface-800 rounded-[1.5rem] sm:rounded-[2rem] border border-surface-200 dark:border-surface-700 p-4 sm:p-8 shadow-xl">
        <div class="max-w-4xl mx-auto">
            <h3 class="text-2xl font-black text-text-primary mb-8 flex items-center gap-3">
                <Receipt :size="28" class="text-primary-500" stroke-width="2.5" /> Formulir Angkat Barang
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Customer Info -->
                <div class="space-y-6">
                    <h4
                        class="text-sm font-black text-primary-600 uppercase tracking-widest border-b border-primary-100 dark:border-primary-900/30 pb-2">
                        Data Customer</h4>
                    <div>
                        <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Nama
                            Customer <span class="text-red-500">*</span></label>
                        <input v-model="tradeInForm.customer_name" type="text" placeholder="Nama lengkap..."
                            class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none" />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">No
                            WhatsApp <span class="text-red-500">*</span></label>
                        <input v-model="tradeInForm.customer_phone" type="text" placeholder="08xxx..."
                            class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none" />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Sumber
                            Handphone <span class="text-red-500">*</span></label>
                        <select v-model="tradeInForm.source"
                            class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none">
                            <option value="pstore">ex pstore</option>
                            <option value="luar_pstore">Luar pstore</option>
                        </select>
                    </div>
                </div>

                <!-- HP Specs -->
                <div class="space-y-6">
                    <h4
                        class="text-sm font-black text-primary-600 uppercase tracking-widest border-b border-primary-100 dark:border-primary-900/30 pb-2">
                        Spesifikasi Unit</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label
                                class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Brand
                                <span class="text-red-500">*</span></label>
                            <select v-model="tradeInForm.brand_id"
                                class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none">
                                <option :value="null" disabled>Pilih Brand</option>
                                <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label
                                class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Tipe
                                <span class="text-red-500">*</span></label>
                            <select v-model="tradeInForm.product_type_id"
                                class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none"
                                :disabled="!tradeInForm.brand_id">
                                <option :value="null" disabled>Pilih Tipe</option>
                                <option v-for="p in filteredTradeInTypes" :key="p.id" :value="p.id">{{
                                    p.name }}
                                </option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 xs:grid-cols-2 gap-4">
                        <div>
                            <label
                                class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Kapasitas
                                (Internal) <span class="text-red-500">*</span></label>
                            <select v-model="tradeInForm.storage"
                                class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none"
                                :disabled="!tradeInForm.product_type_id">
                                <option value="" disabled>Pilih Kapasitas</option>
                                <option v-for="storage in filteredTradeInCapacities" :key="storage" :value="storage">{{
                                    storage }}</option>
                            </select>
                        </div>
                        <div>
                            <label
                                class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Kondisi
                                <span class="text-red-500">*</span></label>
                            <select v-model="tradeInForm.condition"
                                class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none"
                                :disabled="!tradeInForm.storage">
                                <option value="" disabled>Pilih Kondisi</option>
                                <option v-for="cond in filteredTradeInConditions" :key="cond" :value="cond">
                                    {{
                                        cond === 'new' ? 'New' : (cond === 'ex_ibox' ? 'Ex iBox' :
                                            'Second / SCD') }}</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div v-if="isImeiTradeIn">
                    <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Daftar
                        IMEI (Pisahkan tiap baris/koma) <span class="text-red-500">*</span></label>
                    <textarea :value="tradeInForm.imeis_raw" @input="handleImeiInput" rows="3"
                        placeholder="Masukkan IMEI...&#10;Contoh:&#10;351234...&#10;355678..."
                        class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none font-mono text-sm"></textarea>
                    <div class="mt-2 flex items-center justify-between text-[10px] font-bold">
                        <span :class="totalTradeInUnits > 0 ? 'text-primary-500' : 'text-text-secondary'">
                            {{ totalTradeInUnits }} Unit terdeteksi
                        </span>
                        <span class="text-text-secondary italic">Hanya angka (0-9)</span>
                    </div>
                </div>
                <div v-else>
                    <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Jumlah
                        Stok <span class="text-red-500">*</span></label>
                    <div class="flex items-center gap-4">
                        <input v-model.number="tradeInForm.quantity" type="number" min="1"
                            class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none" />
                        <span class="text-xs font-bold text-text-secondary uppercase">Unit</span>
                    </div>
                </div>
            </div>

            <!-- Financial & Media -->
            <div class="space-y-6">
                <h4
                    class="text-sm font-black text-primary-600 uppercase tracking-widest border-b border-primary-100 dark:border-primary-900/30 pb-2">
                    Pembayaran & Bukti</h4>
                <div>
                    <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Harga
                        Angkat <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span
                            class="absolute left-4 top-1/2 -translate-y-1/2 text-sm font-bold text-text-secondary">Rp</span>
                        <input v-money="tradeInForm.buy_price" type="text"
                            class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl pl-10 pr-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none font-black text-lg text-primary-600" />
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Metode
                        Pembayaran <span class="text-red-500">*</span></label>
                    <select v-model="tradeInForm.payment_method_id"
                        class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none">
                        <option v-for="m in availablePaymentMethods" :key="m.id" :value="m.id">{{ m.name
                            }}</option>
                    </select>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label
                            class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2 text-center">Foto
                            Unit <span class="text-red-500">*</span></label>
                        <div @click="$refs.unitInput.click()"
                            class="relative border-2 border-dashed border-surface-300 dark:border-surface-600 rounded-xl aspect-square flex flex-col items-center justify-center cursor-pointer hover:bg-surface-50 dark:hover:bg-surface-800 transition-all overflow-hidden group">
                            <template v-if="tradeInPhotos.unitPreview">
                                <img :src="tradeInPhotos.unitPreview" class="w-full h-full object-cover" />
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
                            <input type="file" ref="unitInput" @change="e => handlePhotoUpload('unit', e)"
                                accept="image/*" class="hidden" capture="environment" />
                        </div>
                    </div>
                    <div>
                        <label
                            class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2 text-center">Foto
                            Customer</label>
                        <div @click="$refs.customerInput.click()"
                            class="relative border-2 border-dashed border-surface-300 dark:border-surface-600 rounded-xl aspect-square flex flex-col items-center justify-center cursor-pointer hover:bg-surface-50 dark:hover:bg-surface-800 transition-all overflow-hidden group">
                            <template v-if="tradeInPhotos.customerPreview">
                                <img :src="tradeInPhotos.customerPreview" class="w-full h-full object-cover" />
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
                            <input type="file" ref="customerInput" @change="e => handlePhotoUpload('customer', e)"
                                accept="image/*" class="hidden" capture="environment" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Additional info -->
            <div class="space-y-6">
                <h4
                    class="text-sm font-black text-primary-600 uppercase tracking-widest border-b border-primary-100 dark:border-primary-900/30 pb-2">
                    Informasi Tambahan</h4>
                <div>
                    <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Alasan
                        Angkat
                        (Opsional)</label>
                    <textarea v-model="tradeInForm.reason" rows="2"
                        class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none text-sm"
                        placeholder="Kenapa barang ini diangkat?"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Keterangan
                        Tambahan
                        (Opsional)</label>
                    <textarea v-model="tradeInForm.notes" rows="2"
                        class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none text-sm"
                        placeholder="Catatan tambahan..."></textarea>
                </div>
            </div>
        </div>

        <!-- Submit Section -->
        <div class="mt-12 pt-8 border-t border-surface-100 dark:border-surface-700 flex flex-col sm:flex-row gap-4">
            <button @click="emit('back')"
                class="flex-1 py-4 bg-surface-100 dark:bg-surface-700 text-text-primary rounded-2xl font-black uppercase tracking-widest hover:bg-surface-200 transition-all">
                Kembali ke Kategori
            </button>
            <button @click="submitTradeIn()" :disabled="isSubmitting"
                class="flex-[2] py-4 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white rounded-2xl font-black uppercase tracking-widest shadow-xl shadow-emerald-500/20 transition-all flex items-center justify-center gap-3">
                <Loader2 v-if="isSubmitting" class="animate-spin" :size="24" />
                <template v-else>
                    <Save :size="24" /> Selesaikan & Simpan ke Inventory
                </template>
            </button>
        </div>
    </div>
</template>
