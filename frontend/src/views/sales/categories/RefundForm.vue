<script setup>
import { ref, computed, watch } from "vue";
import api from "../../../api/axios";
import { useAuthStore } from "../../../store/auth";
import { formatCurrency } from "../../../utils/formatters";
import {
    Plus,
    Loader2,
    Save,
    ArrowLeft
} from "lucide-vue-next";

const props = defineProps({
    brands: Array,
    productTypes: Array,
    productPrices: Array,
    availablePaymentMethods: Array,
    salesAccount: String,
});

const emit = defineEmits(["back", "transaction-complete"]);

const authStore = useAuthStore();
const isSubmitting = ref(false);
const showPinModal = ref(false);
const pinModalMode = ref("verify");
const pinModalTitle = ref("Verifikasi PIN");

const refundForm = ref({
    customer_name: "",
    customer_phone: "",
    brand_id: null,
    product_type_id: null,
    storage: "",
    condition: "",
    imei: "",
    refund_price: 0,
    payment_method_id: null,
    reason: "",
    notes: "",
});

const refundPhotos = ref({
    unit: null,
    unitPreview: null,
    customer: null,
    customerPreview: null
});

// Computeds
const filteredRefundTypes = computed(() => {
    if (!refundForm.value.brand_id) return [];
    return props.productTypes.filter(t => t.brand_id === refundForm.value.brand_id);
});

const selectedRefundType = computed(() => {
    if (!refundForm.value.product_type_id) return null;
    return props.productTypes.find(t => t.id === refundForm.value.product_type_id);
});

const isImeiRefund = computed(() => {
    if (!selectedRefundType.value) return true;
    const cat = selectedRefundType.value.category?.toLowerCase();
    return cat === 'imei' || cat === 'hp / gadget';
});

const filteredRefundStorages = computed(() => {
    if (!refundForm.value.product_type_id) return [];
    const set = new Set();
    const type = selectedRefundType.value;
    if (type?.storage) {
        type.storage.split(/[,]/).forEach(s => {
            const clean = s.trim();
            if (clean) set.add(clean);
        });
    }
    return Array.from(set).sort();
});

// Watchers
watch(() => refundForm.value.brand_id, () => {
    refundForm.value.product_type_id = null;
    refundForm.value.storage = "";
    refundForm.value.condition = "";
});

watch(() => refundForm.value.product_type_id, () => {
    refundForm.value.storage = "";
    refundForm.value.condition = "";
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

const displayRefundPrice = ref("0");
function handleRefundPriceInput(e) {
    const val = e.target.value;
    const num = parseNumber(val);
    refundForm.value.refund_price = num;
    displayRefundPrice.value = formatNumber(num);
    e.target.value = formatNumber(num);
}

const handleRefundPhotoUpload = (type, e) => {
    const file = e.target.files[0];
    if (!file) return;
    refundPhotos.value[type] = file;
    refundPhotos.value[type + 'Preview'] = URL.createObjectURL(file);
}

// Init payment method
watch(() => props.availablePaymentMethods, (methods) => {
    if (methods?.length > 0 && !refundForm.value.payment_method_id) {
        const cashMethod = methods.find(p => p.category?.toLowerCase() === 'cash' || p.name?.toLowerCase() === 'tunai');
        refundForm.value.payment_method_id = cashMethod ? cashMethod.id : methods[0].id;
    }
}, { immediate: true });

async function submitRefund(pin = null) {
    if (!refundForm.value.customer_name || !refundForm.value.customer_phone || !refundForm.value.brand_id || !refundForm.value.product_type_id || !refundForm.value.storage || !refundForm.value.condition || !refundForm.value.refund_price || !refundForm.value.reason) {
        alert("Mohon lengkapi semua data wajib (Nama, WA, Brand, Tipe, Kapasitas, Kondisi, Harga Refund, Alasan).");
        return;
    }

    if (!refundPhotos.value.unit) {
        alert("Foto unit wajib diupload.");
        return;
    }

    if (!pin && authStore.userRole === 'sales' && authStore.user?.pin_enabled) {
        showPinModal.value = true;
        pinModalMode.value = "verify";
        pinModalTitle.value = "Verifikasi PIN Transaksi";
        return;
    }

    isSubmitting.value = true;
    const formData = new FormData();
    if (refundPhotos.value.unit) formData.append('photo_unit', refundPhotos.value.unit);
    if (refundPhotos.value.customer) formData.append('photo_customer', refundPhotos.value.customer);
    if (pin) formData.append('transaction_pin', pin);

    formData.append('customer_name', refundForm.value.customer_name);
    formData.append('customer_phone', refundForm.value.customer_phone);
    formData.append('brand_id', refundForm.value.brand_id);
    formData.append('product_type_id', refundForm.value.product_type_id);
    formData.append('storage', refundForm.value.storage);
    formData.append('condition', refundForm.value.condition);
    formData.append('imei', refundForm.value.imei);
    formData.append('refund_price', refundForm.value.refund_price);
    formData.append('payment_method_id', refundForm.value.payment_method_id);
    formData.append('reason', refundForm.value.reason);
    formData.append('notes', refundForm.value.notes);

    try {
        const response = await api.post('/refunds', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });

        const data = response.data.data;
        const transaction = {
            id: data.id,
            order_no: data.receipt_id,
            items: [{
                product: data.product_type,
                name: data.product_type?.name,
                imei: data.imei || '-',
                selling_price: data.refund_price,
                condition: data.condition,
                storage: data.storage,
                price: data.refund_price,
                qty: 1
            }],
            original_price: data.refund_price,
            grand_total: data.refund_price,
            total: data.refund_price,
            paid: data.refund_price,
            cash: data.payment_method?.category?.toLowerCase() === 'cash' ? data.refund_price : 0,
            transfer: data.payment_method?.category?.toLowerCase() === 'transfer' ? data.refund_price : 0,
            payment_method_name: data.payment_method?.name,
            category: 'refund',
            customer_name: data.customer_name,
            customer_phone: data.customer_phone,
            time: new Date().toLocaleString("id-ID"),
        };

        emit("transaction-complete", transaction);

        // Reset form
        refundForm.value = {
            customer_name: "",
            customer_phone: "",
            brand_id: null,
            product_type_id: null,
            storage: "",
            condition: "",
            imei: "",
            refund_price: 0,
            payment_method_id: props.availablePaymentMethods?.[0]?.id || null,
            reason: "",
            notes: "",
        };
        refundPhotos.value = { unit: null, unitPreview: null, customer: null, customerPreview: null };
        displayRefundPrice.value = "0";

    } catch (error) {
        console.error("Refund failed", error);
        alert(error.response?.data?.message || "Gagal memproses refund");
    } finally {
        isSubmitting.value = false;
    }
}

function handlePinSuccess(pin) {
    showPinModal.value = false;
    submitRefund(pin);
}
</script>

<template>
    <div
        class="flex-1 overflow-y-auto custom-scrollbar bg-white dark:bg-surface-800 rounded-[2rem] border border-surface-200 dark:border-surface-700 p-8 shadow-xl">
        <div class="max-w-4xl mx-auto">
            <div class="flex items-center justify-between mb-8">
                <h3 class="text-2xl font-black text-text-primary flex items-center gap-3">
                    <ArrowLeft :size="28" class="text-primary-500 cursor-pointer" @click="emit('back')" />
                    Formulir Refund
                    Barang
                </h3>
                <div
                    class="px-4 py-1.5 bg-primary-100 dark:bg-primary-900/30 text-primary-600 rounded-full text-xs font-black uppercase tracking-widest">
                    Masuk ke Inventory
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Customer Info -->
                <div class="space-y-6">
                    <h4
                        class="text-sm font-black text-primary-600 uppercase tracking-widest border-b border-primary-100 dark:border-primary-900/30 pb-2">
                        Data Customer</h4>
                    <div>
                        <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Nama
                            Customer <span class="text-red-500">*</span></label>
                        <input v-model="refundForm.customer_name" type="text" placeholder="Nama lengkap..."
                            class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none" />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">No
                            WhatsApp <span class="text-red-500">*</span></label>
                        <input v-model="refundForm.customer_phone" type="text" placeholder="08xxx..."
                            class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none" />
                    </div>
                </div>

                <!-- HP Specs -->
                <div class="space-y-6">
                    <h4
                        class="text-sm font-black text-primary-600 uppercase tracking-widest border-b border-primary-100 dark:border-primary-900/30 pb-2">
                        Spesifikasi Unit</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label
                                class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Brand
                                <span class="text-red-500">*</span></label>
                            <select v-model="refundForm.brand_id"
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
                            <select v-model="refundForm.product_type_id"
                                class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none"
                                :disabled="!refundForm.brand_id">
                                <option :value="null" disabled>Pilih Tipe</option>
                                <option v-for="p in filteredRefundTypes" :key="p.id" :value="p.id">{{
                                    p.name }}
                                </option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-1">
                        <div>
                            <label
                                class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Kapasitas
                                <span class="text-red-500">*</span></label>
                            <select v-model="refundForm.storage"
                                class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none"
                                :disabled="!refundForm.product_type_id">
                                <option value="" disabled>Pilih Kapasitas</option>
                                <option v-for="s in filteredRefundStorages" :key="s" :value="s">{{ s }}
                                </option>
                                <option v-if="!isImeiRefund" value="Non-HP">Non-HP</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-1">
                        <div class="mt-4">
                            <label
                                class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Kategori
                                <span class="text-red-500">*</span></label>
                            <select v-model="refundForm.condition"
                                class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none">
                                <option value="" disabled>Pilih Kategori</option>
                                <option value="new">New</option>
                                <option value="second">Second / SCD</option>
                                <option value="ex_ibox">Ex iBox</option>
                            </select>
                        </div>
                    </div>
                    <div v-if="isImeiRefund">
                        <label
                            class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Masukkan
                            IMEI <span class="text-red-500">*</span></label>
                        <input v-model="refundForm.imei" type="text" placeholder="15 digit IMEI..."
                            class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none" />
                    </div>
                </div>
            </div>

            <!-- Financial & Media -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-8">
                <div class="space-y-6">
                    <h4
                        class="text-sm font-black text-primary-600 uppercase tracking-widest border-b border-primary-100 dark:border-primary-900/30 pb-2">
                        Pembayaran & Bukti</h4>
                    <div>
                        <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Harga
                            Refund <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <span
                                class="absolute left-4 top-1/2 -translate-y-1/2 text-sm font-bold text-text-secondary">Rp</span>
                            <input type="text" :value="displayRefundPrice" @input="handleRefundPriceInput"
                                class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl pl-10 pr-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none font-black text-lg text-primary-600" />
                        </div>
                        <p class="mt-1 text-[10px] text-text-secondary font-medium italic">*Harga ini
                            akan otomatis menjadi
                            harga modal unit</p>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Metode
                            Pembayaran <span class="text-red-500">*</span></label>
                        <select v-model="refundForm.payment_method_id"
                            class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none">
                            <option v-for="m in availablePaymentMethods" :key="m.id" :value="m.id">{{
                                m.name
                            }}</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label
                                class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2 text-center">Foto
                                Unit <span class="text-red-500">*</span></label>
                            <div @click="$refs.unitRefundInput.click()"
                                class="relative border-2 border-dashed border-surface-300 dark:border-surface-600 rounded-xl aspect-square flex flex-col items-center justify-center cursor-pointer hover:bg-surface-50 dark:hover:bg-surface-800 transition-all overflow-hidden group">
                                <template v-if="refundPhotos.unitPreview">
                                    <img :src="refundPhotos.unitPreview" class="w-full h-full object-cover" />
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
                                <input type="file" ref="unitRefundInput"
                                    @change="e => handleRefundPhotoUpload('unit', e)" accept="image/*" class="hidden"
                                    capture="environment" />
                            </div>
                        </div>
                        <div>
                            <label
                                class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2 text-center">Foto
                                Customer</label>
                            <div @click="$refs.customerRefundInput.click()"
                                class="relative border-2 border-dashed border-surface-300 dark:border-surface-600 rounded-xl aspect-square flex flex-col items-center justify-center cursor-pointer hover:bg-surface-50 dark:hover:bg-surface-800 transition-all overflow-hidden group">
                                <template v-if="refundPhotos.customerPreview">
                                    <img :src="refundPhotos.customerPreview" class="w-full h-full object-cover" />
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
                                <input type="file" ref="customerRefundInput"
                                    @change="e => handleRefundPhotoUpload('customer', e)" accept="image/*"
                                    class="hidden" capture="environment" />
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
                            Refund <span class="text-red-500">*</span></label>
                        <textarea v-model="refundForm.reason" rows="3"
                            class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none text-sm"
                            placeholder="Kenapa barang ini direfund? (Wajib diisi)"></textarea>
                    </div>
                    <div>
                        <label
                            class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Keterangan
                            Tambahan (Opsional)</label>
                        <textarea v-model="refundForm.notes" rows="3"
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
                <button @click="submitRefund()" :disabled="isSubmitting"
                    class="flex-[2] py-4 bg-primary-600 hover:bg-primary-500 disabled:opacity-50 text-white rounded-2xl font-black uppercase tracking-widest shadow-xl shadow-primary-500/20 transition-all flex items-center justify-center gap-3">
                    <Loader2 v-if="isSubmitting" class="animate-spin" :size="24" />
                    <template v-else>
                        <Save :size="24" /> Proses Refund & Simpan ke Inventory
                    </template>
                </button>
            </div>
        </div>
    </div>
</template>
