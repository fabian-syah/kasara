<script setup>
import { ref, computed, watch, onMounted, nextTick } from "vue";
import api from "../../../api/axios";
import { useAuthStore } from "../../../store/auth";
import { formatCurrency } from "../../../utils/formatters";
import {
    Plus,
    Loader2,
    Save,
    ArrowLeft,
    Camera,
    X
} from "lucide-vue-next";
import { compressImage } from "../../../utils/imageCompressor";

const props = defineProps({
    brands: Array,
    productTypes: Array,
    productPrices: Array,
    distributors: Array,
    availablePaymentMethods: Array,
    salesAccount: String,
    selectedAccountObject: Object
});

const emit = defineEmits(["back", "transaction-complete", "verify-pin"]);


const authStore = useAuthStore();
const isSubmitting = ref(false);
const isRestoring = ref(true);

const isCompressing = ref(false);
const refundPhotos = ref({
    unit: null,
    unitPreview: null,
    customer: null,
    customerPreview: null
});

const isSplitInvalid = computed(() => {
    const totalSplit = splitPayments.value.reduce((sum, p) => sum + (p.amount || 0), 0);
    return totalSplit !== (refundForm.value.refund_price || 0);
});


const refundForm = ref({
    customer_name: "",
    customer_phone: "",
    distributor_id: null,
    brand_id: null,
    product_type_id: null,
    storage: "",
    condition: "",
    imei: "",
    quantity: 1,
    refund_price: 0,
    payment_method_id: null,
    reason: "",
    notes: "",
});

// Multi-item support: additional items beyond the first one
const additionalItems = ref([]);

function addItem() {
    additionalItems.value.push({
        brand_id: null,
        product_type_id: null,
        storage: "",
        condition: "",
        imeis_raw: "",
        quantity: 1,
        buy_price: 0,
        distributor_id: refundForm.value.distributor_id,
    });
}

function removeItem(index) {
    additionalItems.value.splice(index, 1);
}

function getFilteredTypesForItem(item) {
    if (!item.brand_id) return [];
    return props.productTypes.filter(t => t.brand_id === item.brand_id);
}

function getCapacitiesForItem(item) {
    if (!item.product_type_id) return [];
    const set = new Set();
    const prices = props.productPrices.filter(p => p.product_type_id === item.product_type_id);
    prices.forEach(p => { if (p.storage) set.add(p.storage); });
    if (set.size === 0) {
        const pt = props.productTypes.find(t => t.id === item.product_type_id);
        if (pt?.storage) pt.storage.split(/[,]/).forEach(s => { const c = s.trim(); if (c) set.add(c); });
    }
    return Array.from(set).sort();
}

function isItemImei(item) {
    const pt = props.productTypes.find(t => t.id === item.product_type_id);
    if (!pt) return false;
    const cat = pt.category?.toLowerCase();
    return cat === 'imei' || cat === 'hp / gadget';
}

// Persistence Logic
// Persistence Logic
const storageKey = computed(() => {
    const userId = authStore.user?.id || 'guest';
    const acc = props.salesAccount ? `_acc_${props.salesAccount.replace(/\s+/g, '_')}` : '';
    return `temp_refund_form_${userId}${acc}`;
});

watch([refundForm, refundPhotos], ([newForm, newPhotos]) => {
    if (isRestoring.value) return;
    
    const persistentPhotos = {
        unitPreview: newPhotos.unitPreview,
        customerPreview: newPhotos.customerPreview
    };

    localStorage.setItem(storageKey.value, JSON.stringify({
        form: newForm,
        photos: persistentPhotos
    }));
}, { deep: true });

async function restoreDraft() {
    const saved = localStorage.getItem(storageKey.value);
    if (saved) {
        try {
            isRestoring.value = true;
            const data = JSON.parse(saved);
            Object.assign(refundForm.value, data.form || data);
            
            if (data.photos) {
                refundPhotos.value.unitPreview = data.photos.unitPreview;
                refundPhotos.value.customerPreview = data.photos.customerPreview;
                
                if (data.photos.unitPreview && data.photos.unitPreview.startsWith('data:')) {
                    try {
                        refundPhotos.value.unit = dataURLtoFile(data.photos.unitPreview, 'unit_restored.jpg');
                    } catch (e) {}
                }
                if (data.photos.customerPreview && data.photos.customerPreview.startsWith('data:')) {
                    try {
                        refundPhotos.value.customer = dataURLtoFile(data.photos.customerPreview, 'customer_restored.jpg');
                    } catch (e) {}
                }
            }

            await nextTick();
            await nextTick();
            setTimeout(() => {
                isRestoring.value = false;
            }, 500);
        } catch (e) {
            isRestoring.value = false;
        }
    } else {
        isRestoring.value = false;
    }
}

// React to user ID or initial mount
watch(() => authStore.user?.id, (newId) => {
    if (newId) {
        restoreDraft();
    }
}, { immediate: true });

onMounted(() => {
    // If user already loaded, restoreDraft will be called by watch immediate
    // But we ensure it's not stuck in isRestoring if user never loads or no id
    setTimeout(() => {
        if (isRestoring.value && !authStore.user?.id) {
            isRestoring.value = false;
        }
    }, 2000);
});

// End of state definitions


// Computeds
const filteredBrands = computed(() => {
    if (!refundForm.value.distributor_id) return props.brands;
    const dist = props.distributors.find(d => d.id === refundForm.value.distributor_id);
    if (!dist || !dist.allowed_brands) return props.brands;
    try {
        const allowedIds = typeof dist.allowed_brands === 'string' ? JSON.parse(dist.allowed_brands) : dist.allowed_brands;
        if (!Array.isArray(allowedIds)) return props.brands;
        const numericIds = allowedIds.map(id => Number(id));
        return props.brands.filter(b => numericIds.includes(Number(b.id)));
    } catch {
        return props.brands;
    }
});
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
watch(() => refundForm.value.distributor_id, (newVal, oldVal) => {
    if (isRestoring.value) return;
    if (newVal !== oldVal) {
        refundForm.value.brand_id = null;
        refundForm.value.product_type_id = null;
    }
});

watch(() => refundForm.value.brand_id, (newVal, oldVal) => {
    if (isRestoring.value) return;
    if (newVal !== oldVal) {
        refundForm.value.product_type_id = null;
        refundForm.value.storage = "";
        refundForm.value.condition = "";
    }
});

watch(() => refundForm.value.product_type_id, () => {
    if (isRestoring.value) return;
    refundForm.value.storage = "";
    refundForm.value.condition = "";
});

watch(() => isImeiRefund.value, (newVal) => {
    if (isRestoring.value) return;
    if (!newVal) {
        refundForm.value.storage = "Non-HP";
        refundForm.value.condition = "new";
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

function dataURLtoFile(dataurl, filename) {
    try {
        var arr = dataurl.split(','), mime = arr[0].match(/:(.*?);/)[1],
            bstr = atob(arr[1]), n = bstr.length, u8arr = new Uint8Array(n);
        while (n--) {
            u8arr[n] = bstr.charCodeAt(n);
        }
        return new File([u8arr], filename, { type: mime });
    } catch (e) {
        return null;
    }
}

async function handlePhotoChange(type, event) {
    const file = event.target.files[0];
    if (file) {
        try {
            isCompressing.value = true;
            
            // 1. Compress Image
            const compressedFile = await compressImage(file, {
                maxWidth: 1600,
                maxHeight: 1600,
                quality: 0.8
            });

            // 2. Set File Object
            refundPhotos.value[type] = compressedFile;

            // 3. Set Preview
            const reader = new FileReader();
            reader.onload = (e) => {
                refundPhotos.value[`${type}Preview`] = e.target.result;
            };
            reader.readAsDataURL(compressedFile);
        } catch (error) {
            console.error("Compression failed:", error);
            alert("Gagal mengompres gambar. Silakan coba lagi.");
        } finally {
            isCompressing.value = false;
        }
    }
}

// Init payment method
const splitPayments = ref([]);

function addSplitPayment() {
    splitPayments.value.push({
        method_id: props.availablePaymentMethods[0]?.id || null,
        amount: 0
    });
}

function removeSplitPayment(index) {
    if (splitPayments.value.length > 1) {
        splitPayments.value.splice(index, 1);
    }
}

onMounted(() => {
    if (splitPayments.value.length === 0) {
        splitPayments.value.push({
            method_id: refundForm.value.payment_method_id || props.availablePaymentMethods[0]?.id || null,
            amount: refundForm.value.refund_price || 0
        });
    }
});

watch(() => refundForm.value.refund_price, (newPrice) => {
    if (splitPayments.value.length === 1) {
        splitPayments.value[0].amount = newPrice || 0;
    }
});

watch(() => props.availablePaymentMethods, (methods) => {
    if (methods?.length > 0 && !refundForm.value.payment_method_id) {
        const cashMethod = methods.find(p => p.category?.toLowerCase() === 'cash' || p.name?.toLowerCase() === 'tunai');
        refundForm.value.payment_method_id = cashMethod ? cashMethod.id : methods[0].id;
        if (splitPayments.value.length > 0 && !splitPayments.value[0].method_id) {
            splitPayments.value[0].method_id = refundForm.value.payment_method_id;
        }
    }
}, { immediate: true });

async function submitRefund(pin = null) {
    if (!refundForm.value.customer_name || !refundForm.value.customer_phone || !refundForm.value.brand_id || !refundForm.value.product_type_id || !refundForm.value.storage || !refundForm.value.condition || !refundForm.value.refund_price || !refundForm.value.reason || !refundForm.value.distributor_id) {
        alert("Mohon lengkapi semua data wajib (Nama, WA, Distributor, Brand, Tipe, Kapasitas, Kondisi, Harga Refund, Alasan).");
        return;
    }

    const totalSplit = splitPayments.value.reduce((sum, p) => sum + (p.amount || 0), 0);
    if (totalSplit !== refundForm.value.refund_price) {
        alert(`Total split pembayaran (${formatCurrency(totalSplit)}) tidak sesuai dengan nominal refund (${formatCurrency(refundForm.value.refund_price)}).`);
        return;
    }

    if (!refundPhotos.value.unit) {
        alert("Foto unit wajib diupload.");
        return;
    }

    if (!pin && props.selectedAccountObject?.pin_enabled) {
        emit('verify-pin', (verifiedPin) => submitRefund(verifiedPin));
        return;
    }


    isSubmitting.value = true;
    const formData = new FormData();
    if (refundPhotos.value.unit) formData.append('photo_unit', refundPhotos.value.unit);
    if (refundPhotos.value.customer) formData.append('photo_customer', refundPhotos.value.customer);
    if (pin) formData.append('transaction_pin', pin);

    if (props.selectedAccountObject?.id) formData.append('inventory_user_id', props.selectedAccountObject.id);
    if (props.salesAccount) formData.append('sales_account', props.salesAccount);
    formData.append('customer_name', refundForm.value.customer_name);
    formData.append('customer_phone', refundForm.value.customer_phone);
    if (refundForm.value.distributor_id) {
        formData.append('distributor_id', refundForm.value.distributor_id);
        const selectedDist = props.distributors.find(d => d.id === refundForm.value.distributor_id);
        if (selectedDist) formData.append('distributor_name', selectedDist.name);
    }
    formData.append('brand_id', refundForm.value.brand_id);
    formData.append('product_type_id', refundForm.value.product_type_id);
    formData.append('storage', refundForm.value.storage);
    formData.append('condition', refundForm.value.condition);
    formData.append('imei', refundForm.value.imei);
    formData.append('quantity', refundForm.value.quantity);
    formData.append('refund_price', refundForm.value.refund_price);
    formData.append('payment_method_id', splitPayments.value[0]?.method_id || refundForm.value.payment_method_id || 1);
    formData.append('split_payments', JSON.stringify(splitPayments.value.map(p => ({
        payment_method_id: p.method_id,
        amount: p.amount
    }))));
    formData.append('reason', refundForm.value.reason);
    formData.append('notes', refundForm.value.notes);

    // Multi-item support: if there are additional items, send as items[] array
    if (additionalItems.value.length > 0) {
        const allItems = [];
        
        // Main item
        allItems.push({
            brand_id: refundForm.value.brand_id,
            product_type_id: refundForm.value.product_type_id,
            imeis: isImeiRefund.value ? [refundForm.value.imei].filter(i => i) : [],
            quantity: isImeiRefund.value ? 1 : (refundForm.value.quantity || 1),
            storage: refundForm.value.storage,
            condition: refundForm.value.condition,
            buy_price: refundForm.value.refund_price,
            distributor_id: refundForm.value.distributor_id,
        });

        // Additional items
        for (const item of additionalItems.value) {
            const itemIsImei = isItemImei(item);
            const itemImeis = itemIsImei 
                ? (item.imeis_raw || '').split(/[\n,]/).map(i => i.trim()).filter(i => i !== "")
                : [];
            allItems.push({
                brand_id: item.brand_id,
                product_type_id: item.product_type_id,
                imeis: itemImeis,
                quantity: itemIsImei ? Math.max(1, itemImeis.length) : (item.quantity || 1),
                storage: item.storage,
                condition: item.condition,
                buy_price: item.buy_price,
                distributor_id: item.distributor_id || refundForm.value.distributor_id,
            });
        }

        formData.append('items', JSON.stringify(allItems));
    }

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
                imei: refundForm.value.imei || '-',
                selling_price: refundForm.value.refund_price,
                condition: refundForm.value.condition,
                storage: refundForm.value.storage,
                price: refundForm.value.refund_price,
                qty: refundForm.value.quantity,
                is_hp: true
            }],
            original_price: data.refund_price,
            grand_total: data.refund_price,
            total: data.refund_price,
            paid: data.refund_price,
            cash: data.payment_method?.category?.toLowerCase() === 'cash' ? data.refund_price : 0,
            transfer: data.payment_method?.category?.toLowerCase() === 'transfer' ? data.refund_price : 0,
            payment_method_name: props.availablePaymentMethods.find(m => m.id === (splitPayments.value[0]?.method_id || refundForm.value.payment_method_id))?.name,
            split_payments_data: splitPayments.value.map(p => ({
                method_name: props.availablePaymentMethods.find(m => m.id === p.method_id)?.name || 'Unknown',
                amount: p.amount
            })),
            category: 'refund',
            customer_name: data.customer_name,
            customer_phone: data.customer_phone,
            time: new Date().toLocaleString("id-ID"),
            inventory_user_name: props.salesAccount || authStore.user?.name
        };

        emit("transaction-complete", transaction);

        // Reset form
        additionalItems.value = [];
        localStorage.removeItem('temp_refund_form');
        refundForm.value = {
            customer_name: "",
            customer_phone: "",
            distributor_id: null,
            brand_id: null,
            product_type_id: null,
            storage: "",
            condition: "",
            imei: "",
            quantity: 1,
            refund_price: 0,
            payment_method_id: props.availablePaymentMethods?.[0]?.id || null,
            reason: "",
            notes: "",
        };
        refundPhotos.value = { unit: null, unitPreview: null, customer: null, customerPreview: null };

    } catch (error) {
        console.error("Refund failed", error);
        let msg = "Gagal memproses refund";
        if (error.response) {
            if (error.response.status === 413) msg = "File terlalu besar. Silakan coba kurangi resolusi atau gunakan foto lain.";
            else if (error.response.data?.message) msg = error.response.data.message;
            else msg = `Error ${error.response.status}: ${error.response.statusText}`;
        }
        alert(msg);
    } finally {
        isSubmitting.value = false;
    }
}


</script>

<template>
    <div
        class="flex-1 overflow-y-auto custom-scrollbar bg-white dark:bg-surface-800 rounded-[1.5rem] sm:rounded-[2rem] border border-surface-200 dark:border-surface-700 p-4 sm:p-8 shadow-xl">
        <div class="max-w-4xl mx-auto">
            <div class="flex items-center justify-between mb-8 gap-4">
                <div class="flex items-center gap-3">
                    <button @click="emit('back')" class="p-2 -ml-2 hover:bg-surface-100 dark:hover:bg-surface-700 rounded-full text-primary-600 transition-colors">
                        <ArrowLeft :size="28" stroke-width="3" />
                    </button>
                    <div class="flex flex-col">
                        <h3 class="text-lg sm:text-2xl font-black text-text-primary uppercase tracking-tight leading-none">Formulir Refund</h3>
                        <p class="text-[10px] font-bold text-text-secondary uppercase tracking-widest mt-1">Pengembalian Barang ke Stok</p>
                    </div>
                </div>
                <div
                    class="hidden xs:block px-4 py-1.5 bg-primary-100 dark:bg-primary-900/30 text-primary-600 rounded-full text-[10px] font-black uppercase tracking-widest">
                    In-Stock
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
                    <div>
                        <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Pilih Distributor <span class="text-red-500">*</span></label>
                        <select v-model="refundForm.distributor_id"
                            class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none font-bold text-primary-600">
                            <option :value="null">-- Pilih Distributor --</option>
                            <option v-for="d in distributors" :key="d.id" :value="d.id">{{ d.name }}</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-1 xs:grid-cols-2 gap-4">
                        <div>
                            <label
                                class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Brand
                                <span class="text-red-500">*</span></label>
                            <select v-model="refundForm.brand_id"
                                class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none">
                                <option :value="null" disabled>Pilih Brand</option>
                                <option v-for="b in filteredBrands" :key="b.id" :value="b.id">{{ b.name }}
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
                    <div v-if="isImeiRefund" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
                            </select>
                        </div>
                        <div class="mt-4 sm:mt-0">
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
                    <div v-else>
                        <label
                            class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Jumlah Unit <span class="text-red-500">*</span></label>
                        <div class="flex items-center gap-4">
                            <input v-model.number="refundForm.quantity" type="number" min="1"
                                class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none" />
                            <span class="text-xs font-bold text-text-secondary uppercase">Unit</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Financial & Media -->
            <!-- Additional Items Section (DISABLED - uncomment when ready)
            <div class="space-y-4 mt-8 mb-8">
                <div v-if="additionalItems.length > 0" class="space-y-4 mb-6">
                    ...
                </div>
                <button @click="addItem" type="button" class="...">
                    Tambah Item Lain
                </button>
            </div>
            -->

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
                            <input v-money:refund_price="refundForm" type="text"
                                class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl pl-10 pr-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none font-black text-lg text-primary-600" />
                        </div>
                        <p class="mt-1 text-[10px] text-text-secondary font-medium italic">*Harga ini
                            akan otomatis menjadi
                            harga modal unit</p>
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest">Metode Pembayaran <span class="text-red-500">*</span></label>
                            <button @click="addSplitPayment" type="button" class="text-xs font-bold text-primary-500 hover:text-primary-600 flex items-center gap-1 bg-primary-50 dark:bg-primary-900/20 px-3 py-1.5 rounded-lg transition-all active:scale-95">
                                <Plus :size="12" stroke-width="3" /> Split Bayar
                            </button>
                        </div>
                        <div class="space-y-3">
                            <div v-for="(payment, index) in splitPayments" :key="index" class="p-4 bg-white dark:bg-surface-900 rounded-xl border border-surface-200 dark:border-surface-700 relative flex flex-col gap-2">
                                <button v-if="splitPayments.length > 1" @click="removeSplitPayment(index)" type="button" class="absolute top-2 right-2 text-gray-400 hover:text-red-500 transition-colors">
                                    <X :size="16" />
                                </button>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-[10px] font-black text-text-secondary uppercase tracking-widest mb-1">Metode</label>
                                        <select v-model="payment.method_id" class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-lg px-3 py-2 bg-surface-50 dark:bg-surface-900 text-xs font-bold text-text-primary focus:outline-none focus:border-primary-500 transition-all">
                                            <option v-for="method in availablePaymentMethods" :key="method.id" :value="method.id">{{ method.name }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-black text-text-secondary uppercase tracking-widest mb-1">Nominal</label>
                                        <div class="relative">
                                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary text-[11px] font-black">Rp</span>
                                            <input v-money:amount="payment" type="text" class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-lg px-3 py-2 bg-surface-50 dark:bg-surface-900 text-xs font-black text-text-primary focus:outline-none focus:border-primary-500 transition-all pl-8" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label
                                class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2 text-center">Foto
                                Unit <span class="text-red-500">*</span></label>
                            <div @click="$refs.unitRefundInput.click()"
                                class="relative border-2 border-dashed border-surface-300 dark:border-surface-600 rounded-2xl aspect-square flex flex-col items-center justify-center cursor-pointer hover:bg-surface-50 dark:hover:bg-surface-800 transition-all overflow-hidden group">
                                <template v-if="isCompressing">
                                    <Loader2 class="w-8 h-8 text-primary-600 animate-spin" />
                                    <span class="text-[10px] font-black text-text-secondary uppercase mt-2">Memproses...</span>
                                </template>
                                <template v-else-if="refundPhotos.unitPreview">
                                    <img :src="refundPhotos.unitPreview" class="w-full h-full object-cover" />
                                    <div
                                        class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity">
                                        <Camera class="text-white w-6 h-6" />
                                    </div>
                                </template>
                                <template v-else>
                                    <Plus :size="32" class="text-text-secondary mb-2" />
                                    <span class="text-[10px] font-black text-text-secondary uppercase tracking-widest">Upload
                                        Unit</span>
                                </template>
                            </div>
                            <input type="file" ref="unitRefundInput" @change="e => handlePhotoChange('unit', e)"
                                accept="image/*" class="hidden" capture="environment" />
                        </div>
                        <div>
                            <label
                                class="block text-xs font-black text-text-secondary uppercase tracking-widest mb-4 text-center">Foto
                                Customer</label>
                            <div @click="$refs.customerRefundInput.click()"
                                class="relative border-2 border-dashed border-surface-300 dark:border-surface-600 rounded-2xl aspect-square flex flex-col items-center justify-center cursor-pointer hover:bg-surface-50 dark:hover:bg-surface-800 transition-all overflow-hidden group">
                                <template v-if="isCompressing">
                                    <Loader2 class="w-8 h-8 text-primary-600 animate-spin" />
                                    <span class="text-[10px] font-black text-text-secondary uppercase mt-2">Memproses...</span>
                                </template>
                                <template v-else-if="refundPhotos.customerPreview">
                                    <img :src="refundPhotos.customerPreview" class="w-full h-full object-cover" />
                                    <div
                                        class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity">
                                        <Camera class="text-white w-6 h-6" />
                                    </div>
                                </template>
                                <template v-else>
                                    <Plus :size="32" class="text-text-secondary mb-2" />
                                    <span class="text-[10px] font-black text-text-secondary uppercase tracking-widest">Upload
                                        Customer</span>
                                </template>
                            </div>
                            <input type="file" ref="customerRefundInput"
                                    @change="e => handlePhotoChange('customer', e)" accept="image/*"
                                    class="hidden" capture="environment" />
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
                <button @click="submitRefund()" :disabled="isSubmitting || isSplitInvalid"
                    class="flex-[2] py-4 bg-primary-600 hover:bg-primary-500 disabled:opacity-50 disabled:bg-surface-300 dark:disabled:bg-surface-600 disabled:cursor-not-allowed text-white rounded-2xl font-black uppercase tracking-widest shadow-xl shadow-primary-500/20 transition-all flex items-center justify-center gap-3">
                    <Loader2 v-if="isSubmitting" class="animate-spin" :size="24" />
                    <template v-else>
                        <Save :size="24" /> Proses Refund & Simpan ke Inventory
                    </template>
                </button>
            </div>
        </div>
    </div>
</template>
