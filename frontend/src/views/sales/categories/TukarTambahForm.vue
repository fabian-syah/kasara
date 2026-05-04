<script setup>
import { ref, computed, watch, onMounted, nextTick } from "vue";
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
    AlertCircle,
    Camera
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
const inventoryStore = useInventoryStore();
const isSubmitting = ref(false);
const isRestoring = ref(true);
const suggestedOutgoingPrice = ref(0);
const stockSearchQuery = ref("");
const showStockDropdown = ref(false);

const isCompressing = ref(false);
const tukarTambahPhotos = ref({
    unit: null,
    unitPreview: null,
    customer: null,
    customerPreview: null
});


const tukarTambahForm = ref({
    customer_name: "",
    customer_phone: "",
    incoming_source: "luar_pstore",
    distributor_id: null,
    incoming_brand_id: null,
    incoming_product_type_id: null,
    incoming_storage: "",
    incoming_condition: "second",
    incoming_imei: "",
    incoming_quantity: 1,
    incoming_cost_price: 0,
    outgoing_product_detail_id: null,
    outgoing_quantity: 1,
    outgoing_price: 0,
    payment_method_id: null,
    reason: "",
    notes: "",
});

// Persistence Logic
const storageKey = computed(() => {
    const userId = authStore.user?.id || 'guest';
    const acc = props.salesAccount ? `_acc_${props.salesAccount.replace(/\s+/g, '_')}` : '';
    return `temp_tukar_tambah_form_${userId}${acc}`;
});

watch([tukarTambahForm, stockSearchQuery, tukarTambahPhotos], ([newForm, newQuery, newPhotos]) => {
    if (isRestoring.value) return;
    
    const persistentPhotos = {
        unitPreview: newPhotos.unitPreview,
        customerPreview: newPhotos.customerPreview
    };

    localStorage.setItem(storageKey.value, JSON.stringify({
        form: newForm,
        query: newQuery,
        photos: persistentPhotos
    }));
}, { deep: true });

async function restoreDraft() {
    const saved = localStorage.getItem(storageKey.value);
    if (saved) {
        try {
            isRestoring.value = true;
            const data = JSON.parse(saved);
            Object.assign(tukarTambahForm.value, data.form);
            stockSearchQuery.value = data.query || "";
            
            if (data.photos) {
                tukarTambahPhotos.value.unitPreview = data.photos.unitPreview;
                tukarTambahPhotos.value.customerPreview = data.photos.customerPreview;
                
                if (data.photos.unitPreview && data.photos.unitPreview.startsWith('data:')) {
                    try {
                        tukarTambahPhotos.value.unit = dataURLtoFile(data.photos.unitPreview, 'unit_restored.jpg');
                    } catch (e) {}
                }
                if (data.photos.customerPreview && data.photos.customerPreview.startsWith('data:')) {
                    try {
                        tukarTambahPhotos.value.customer = dataURLtoFile(data.photos.customerPreview, 'customer_restored.jpg');
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
    if (!tukarTambahForm.value.distributor_id) return props.brands;
    const dist = props.distributors.find(d => d.id === tukarTambahForm.value.distributor_id);
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

const filteredTukarTambahTypes = computed(() => {
    if (!tukarTambahForm.value.incoming_brand_id) return [];
    return props.productTypes.filter(t => t.brand_id === tukarTambahForm.value.incoming_brand_id);
});

const selectedTukarTambahType = computed(() => {
    if (!tukarTambahForm.value.incoming_product_type_id) return null;
    return props.productTypes.find(t => t.id === tukarTambahForm.value.incoming_product_type_id);
});

const isImeiTukarTambah = computed(() => {
    if (!selectedTukarTambahType.value) return true;
    const cat = selectedTukarTambahType.value.category?.toLowerCase();
    return cat === 'imei' || cat === 'hp / gadget';
});

const filteredTukarTambahStorages = computed(() => {
    if (!tukarTambahForm.value.incoming_product_type_id) return [];
    const set = new Set();
    const type = selectedTukarTambahType.value;
    if (type?.storage) {
        type.storage.split(/[,]/).forEach(s => {
            const clean = s.trim();
            if (clean) set.add(clean);
        });
    }
    return Array.from(set).sort();
});

const selectedOutgoingTukarTambah = computed(() => {
    if (!tukarTambahForm.value.outgoing_product_detail_id) return null;
    return inventoryStore.products.find(p => p.id === tukarTambahForm.value.outgoing_product_detail_id);
});

const filteredInventoryProducts = computed(() => {
    const q = stockSearchQuery.value.toLowerCase().trim();
    const allProducts = inventoryStore.products.filter(p => (p.imei || p.stock > 0) && p.status !== 'sold');
    if (!q) return allProducts;

    const cleanQ = q.replace(/\./g, '');
    const isNumeric = /^\d+$/.test(cleanQ);

    return allProducts.filter(p => {
        const name = (p.product?.name || p.name || '').toLowerCase();
        const brand = (p.product?.brand || p.brand || '').toLowerCase();
        const imei = (p.imei || '').toLowerCase();

        const matchesText = name.includes(q) || brand.includes(q) || imei.includes(q);
        if (isNumeric && cleanQ.length >= 4) {
            const cost = p.cost_price?.toString() || '';
            const selling = p.selling_price?.toString() || '';
            return matchesText || cost.startsWith(cleanQ) || selling.startsWith(cleanQ);
        }
        return matchesText;
    });
});

const tukarTambahPriceDiff = computed(() => {
    const totalOut = (tukarTambahForm.value.outgoing_price || 0) * (tukarTambahForm.value.outgoing_quantity || 1);
    const totalIn = (tukarTambahForm.value.incoming_cost_price || 0) * (tukarTambahForm.value.incoming_quantity || 1);
    return totalOut - totalIn;
});

// Watchers
watch(() => tukarTambahForm.value.distributor_id, (newVal, oldVal) => {
    if (isRestoring.value) return;
    if (newVal !== oldVal) {
        tukarTambahForm.value.incoming_brand_id = null;
        tukarTambahForm.value.incoming_product_type_id = null;
    }
});

watch(() => tukarTambahForm.value.incoming_brand_id, (newVal, oldVal) => {
    if (isRestoring.value) return;
    if (newVal !== oldVal) {
        tukarTambahForm.value.incoming_product_type_id = null;
        tukarTambahForm.value.incoming_storage = "";
        tukarTambahForm.value.incoming_condition = "second";
    }
});

watch(() => tukarTambahForm.value.incoming_product_type_id, (newVal, oldVal) => {
    if (isRestoring.value) return;
    if (newVal !== oldVal) {
        tukarTambahForm.value.incoming_storage = "";
        tukarTambahForm.value.incoming_condition = "second";
    }
    if (!isImeiTukarTambah.value && tukarTambahForm.value.incoming_product_type_id) {
        tukarTambahForm.value.incoming_storage = "Non-HP";
        tukarTambahForm.value.incoming_condition = "second";
    }
});

watch(() => isImeiTukarTambah.value, (newVal) => {
    if (isRestoring.value) return;
    if (!newVal) {
        tukarTambahForm.value.incoming_storage = "Non-HP";
        tukarTambahForm.value.incoming_condition = "second";
    }
}, { immediate: true });

watch(() => tukarTambahForm.value.outgoing_product_detail_id, (newId) => {
    if (isRestoring.value) return;
    if (newId) {
        const item = inventoryStore.products.find(p => p.id === newId);
        if (item) {
            const selling = parseFloat(item.selling_price || item.price || 0);
            const cost = parseFloat(item.cost_price || 0);
            suggestedOutgoingPrice.value = selling > 0 ? selling : (cost > 0 ? cost : 0);
        }
    } else {
        suggestedOutgoingPrice.value = 0;
        tukarTambahForm.value.outgoing_price = 0;
    }
});

// Init payment method
watch(() => props.availablePaymentMethods, (methods) => {
    if (methods?.length > 0 && !tukarTambahForm.value.payment_method_id) {
        const cashMethod = methods.find(p => p.category?.toLowerCase() === 'cash' || p.name?.toLowerCase() === 'tunai');
        tukarTambahForm.value.payment_method_id = cashMethod ? cashMethod.id : methods[0].id;
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
            tukarTambahPhotos.value[type] = compressedFile;

            // 3. Set Preview
            const reader = new FileReader();
            reader.onload = (e) => {
                tukarTambahPhotos.value[`${type}Preview`] = e.target.result;
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

function selectStockItem(item) {
    tukarTambahForm.value.outgoing_product_detail_id = item.id;

    // Use price from search query if numeric, otherwise fallback to item price
    const cleanQ = stockSearchQuery.value.replace(/\./g, '').trim();
    if (/^\d+$/.test(cleanQ) && cleanQ.length >= 4) {
        tukarTambahForm.value.outgoing_price = parseInt(cleanQ);
    } else {
        const selling = parseFloat(item.selling_price || item.price || 0);
        const cost = parseFloat(item.cost_price || 0);
        tukarTambahForm.value.outgoing_price = selling > 0 ? selling : (cost > 0 ? cost : 0);
    }

    stockSearchQuery.value = `[${item.product?.brand || '-'}] ${item.product?.name || item.name} - ${item.imei || 'Non-IMEI'}`;
    showStockDropdown.value = false;
}

function closeStockDropdown() {
    setTimeout(() => {
        showStockDropdown.value = false;
    }, 200);
}

async function submitTukarTambah(pin = null) {
    if (tukarTambahPriceDiff.value < 0) {
        alert("Harga Unit Keluar lebih kecil dari Harga Unit Masuk. Tukar Tambah seharusnya nilai Unit Keluar lebih besar atau sama dengan Unit Masuk. Silakan gunakan menu 'Downgrade' jika unit toko lebih murah.");
        return;
    }

    if (!tukarTambahForm.value.customer_name || !tukarTambahForm.value.customer_phone || !tukarTambahForm.value.incoming_brand_id || !tukarTambahForm.value.incoming_product_type_id || !tukarTambahForm.value.incoming_storage || !tukarTambahForm.value.incoming_condition || !tukarTambahForm.value.incoming_cost_price || !tukarTambahForm.value.outgoing_product_detail_id || !tukarTambahForm.value.outgoing_price || !tukarTambahForm.value.reason || !tukarTambahForm.value.payment_method_id) {
        alert("Mohon lengkapi semua data wajib (Customer, Barang Masuk, Barang Keluar, Harga Jual, Metode Bayar, & Alasan).");
        return;
    }

    if (!tukarTambahPhotos.value.unit) {
        alert("Foto unit wajib diupload.");
        return;
    }

    if (!pin && props.selectedAccountObject?.pin_enabled) {
        emit('verify-pin', (verifiedPin) => submitTukarTambah(verifiedPin));
        return;
    }


    isSubmitting.value = true;
    const formData = new FormData();
    if (tukarTambahPhotos.value.unit) formData.append('photo_unit', tukarTambahPhotos.value.unit);
    if (tukarTambahPhotos.value.customer) formData.append('photo_customer', tukarTambahPhotos.value.customer);
    if (pin) formData.append('transaction_pin', pin);

    if (props.selectedAccountObject?.id) formData.append('inventory_user_id', props.selectedAccountObject.id);
    if (props.salesAccount) formData.append('sales_account', props.salesAccount);
    formData.append('customer_name', tukarTambahForm.value.customer_name);
    formData.append('customer_phone', tukarTambahForm.value.customer_phone);
    if (tukarTambahForm.value.distributor_id) formData.append('distributor_id', tukarTambahForm.value.distributor_id);
    formData.append('incoming_source', tukarTambahForm.value.incoming_source);
    formData.append('incoming_product_type_id', tukarTambahForm.value.incoming_product_type_id);
    formData.append('incoming_storage', tukarTambahForm.value.incoming_storage);
    formData.append('incoming_condition', tukarTambahForm.value.incoming_condition);
    formData.append('incoming_imei', tukarTambahForm.value.incoming_imei);
    formData.append('incoming_quantity', tukarTambahForm.value.incoming_quantity);
    formData.append('incoming_cost_price', tukarTambahForm.value.incoming_cost_price);

    formData.append('outgoing_product_detail_id', tukarTambahForm.value.outgoing_product_detail_id);
    formData.append('outgoing_quantity', tukarTambahForm.value.outgoing_quantity);
    formData.append('outgoing_price', tukarTambahForm.value.outgoing_price);
    formData.append('price_difference', tukarTambahPriceDiff.value);
    formData.append('payment_method_id', tukarTambahForm.value.payment_method_id);
    formData.append('reason', tukarTambahForm.value.reason);
    formData.append('notes', tukarTambahForm.value.notes);

    try {
        const response = await api.post('/tukar-tambah', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });

        const data = response.data.data;
        const transaction = {
            id: data.id,
            order_no: data.receipt_id,
            items: [
                {
                    name: 'OUT: ' + (selectedOutgoingTukarTambah.value?.product?.name || selectedOutgoingTukarTambah.value?.name || 'Unit Keluar'),
                    imei: selectedOutgoingTukarTambah.value?.imei || '-',
                    price: tukarTambahForm.value.outgoing_price,
                    condition: selectedOutgoingTukarTambah.value?.condition || 'second',
                    storage: selectedOutgoingTukarTambah.value?.storage,
                    qty: tukarTambahForm.value.outgoing_quantity,
                    is_hp: !!selectedOutgoingTukarTambah.value?.imei
                },
                {
                    name: 'IN: ' + (selectedTukarTambahType.value?.name || 'Unit Masuk'),
                    imei: tukarTambahForm.value.incoming_imei || '-',
                    price: -tukarTambahForm.value.incoming_cost_price,
                    condition: tukarTambahForm.value.incoming_condition,
                    storage: tukarTambahForm.value.incoming_storage,
                    qty: tukarTambahForm.value.incoming_quantity,
                    is_hp: isImeiTukarTambah.value
                }
            ],
            original_price: tukarTambahPriceDiff.value,
            grand_total: tukarTambahPriceDiff.value,
            total: tukarTambahPriceDiff.value,
            paid: tukarTambahPriceDiff.value,
            payment_method_name: props.availablePaymentMethods.find(m => m.id === tukarTambahForm.value.payment_method_id)?.name,
            category: 'tukar_tambah',
            customer_name: data.customer_name,
            customer_phone: data.customer_phone,
            time: new Date().toLocaleString("id-ID"),
            inventory_user_name: props.salesAccount || authStore.user?.name,
            distributor_name: data.distributor?.name || 'KOSONG'
        };

        emit("transaction-complete", transaction);

        // Reset form
        tukarTambahForm.value = {
            customer_name: "",
            customer_phone: "",
            distributor_id: null,
            incoming_source: "luar_pstore",
            incoming_brand_id: null,
            incoming_product_type_id: null,
            incoming_storage: "",
            incoming_condition: "second",
            incoming_imei: "",
            incoming_quantity: 1,
            incoming_cost_price: 0,
            outgoing_product_detail_id: null,
            outgoing_quantity: 1,
            outgoing_price: 0,
            payment_method_id: null,
            reason: "",
            notes: "",
        };
        tukarTambahPhotos.value = { unit: null, unitPreview: null, customer: null, customerPreview: null };

    } catch (error) {
        console.error("Tukar tambah failed", error);
        let msg = "Gagal memproses tukar tambah";
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
                        <h3 class="text-lg sm:text-2xl font-black text-text-primary uppercase tracking-tight leading-none">Tukar Tambah</h3>
                        <p class="text-[10px] font-bold text-text-secondary uppercase tracking-widest mt-1">Konsolidasi Unit Masuk & Keluar</p>
                    </div>
                </div>
                <div
                    class="hidden xs:block px-4 py-1.5 bg-amber-100 dark:bg-amber-900/30 text-amber-600 rounded-full text-[10px] font-black uppercase tracking-widest">
                    Trade-In
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
                        <input v-model="tukarTambahForm.customer_name" type="text" placeholder="Nama lengkap..."
                            class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none" />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">NO
                            WHATSAPP <span class="text-red-500">*</span></label>
                        <input v-model="tukarTambahForm.customer_phone" type="text" placeholder="08xxx..."
                            class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none" />
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- 2. BARANG MASUK -->
                <div class="space-y-6">
                    <h4
                        class="text-sm font-black text-emerald-600 uppercase tracking-widest border-b border-emerald-100 dark:border-emerald-900/30 pb-2">
                        [1] BARANG MASUK (DARI USER)
                    </h4>
                    <div>
                        <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">SUMBER
                            BARANG <span class="text-red-500">*</span></label>
                        <select v-model="tukarTambahForm.incoming_source"
                            class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none">
                            <option value="luar_pstore">Luar PStore</option>
                            <option value="ex_pstore">Ex PStore</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">PILIH DISTRIBUTOR <span class="text-red-500">*</span></label>
                        <select v-model="tukarTambahForm.distributor_id"
                            class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none font-bold text-primary-600">
                            <option :value="null">-- PILIH DISTRIBUTOR --</option>
                            <option v-for="d in distributors" :key="d.id" :value="d.id">{{ d.name }}</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label
                                class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">BRAND
                                <span class="text-red-500">*</span></label>
                            <select v-model="tukarTambahForm.incoming_brand_id"
                                class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none">
                                <option :value="null" disabled>Pilih Brand</option>
                                <option v-for="b in filteredBrands" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label
                                class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">TIPE
                                <span class="text-red-500">*</span></label>
                            <select v-model="tukarTambahForm.incoming_product_type_id"
                                class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none"
                                :disabled="!tukarTambahForm.incoming_brand_id">
                                <option :value="null" disabled>Pilih Tipe</option>
                                <option v-for="p in filteredTukarTambahTypes" :key="p.id" :value="p.id">{{
                                    p.name }}</option>
                            </select>
                        </div>
                    </div>
                    <div v-if="isImeiTukarTambah" class="grid grid-cols-2 gap-4">
                        <div>
                            <label
                                class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">STORAGE
                                <span class="text-red-500">*</span></label>
                            <select v-model="tukarTambahForm.incoming_storage"
                                class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none"
                                :disabled="!tukarTambahForm.incoming_product_type_id">
                                <option value="" disabled>Pilih Storage</option>
                                <option v-for="s in filteredTukarTambahStorages" :key="s" :value="s">{{ s }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label
                                class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">KATEGORI
                                <span class="text-red-500">*</span></label>
                            <select v-model="tukarTambahForm.incoming_condition"
                                class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none">
                                <option value="" disabled>Pilih Kategori</option>
                                <option value="new">New</option>
                                <option value="second">Second / SCD</option>
                                <option value="ex_ibox">Ex iBox</option>
                            </select>
                        </div>
                    </div>
                    <div v-if="isImeiTukarTambah">
                        <label
                            class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">MASUKKAN
                            IMEI <span class="text-red-500">*</span></label>
                        <input v-model="tukarTambahForm.incoming_imei" type="text" placeholder="15 digit IMEI..."
                            class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none" />
                    </div>
                    <div v-else>
                        <label
                            class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">JUMLAH UNIT <span class="text-red-500">*</span></label>
                        <div class="flex items-center gap-4">
                            <input v-model.number="tukarTambahForm.incoming_quantity" type="number" min="1"
                                class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none" />
                            <span class="text-xs font-bold text-text-secondary uppercase">Unit</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">HARGA
                            UNIT MASUK (PER UNIT) <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <span
                                class="absolute left-4 top-1/2 -translate-y-1/2 text-sm font-bold text-text-secondary">Rp</span>
                            <input v-money:incoming_cost_price="tukarTambahForm" type="text"
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
                        [2] BARANG KELUAR (PILIH STOK TOKO)
                    </h4>
                    <div>
                        <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">CARI
                            & PILIH UNIT KELUAR <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <input v-model="stockSearchQuery" type="text"
                                @focus="showStockDropdown = true"
                                @blur="closeStockDropdown"
                                placeholder="Ketik Nama, Brand, IMEI, atau Harga..."
                                class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none" />
                            
                            <div v-if="showStockDropdown" 
                                class="absolute z-[100] mt-1 w-full bg-white dark:bg-surface-800 border-2 border-surface-200 dark:border-surface-700 rounded-xl shadow-2xl max-h-[300px] overflow-y-auto custom-scrollbar">
                                <div v-if="filteredInventoryProducts.length === 0" class="p-4 text-center text-xs text-text-secondary">
                                    Tidak ada stok ditemukan...
                                </div>
                                <div v-for="item in filteredInventoryProducts" :key="item.id"
                                    @mousedown.prevent="selectStockItem(item)"
                                    class="p-4 border-b border-surface-100 dark:border-surface-700 hover:bg-primary-50 dark:hover:bg-primary-900/20 cursor-pointer transition-colors">
                                    <div class="flex justify-between items-start mb-1">
                                        <span class="font-black text-sm text-text-primary">[{{ item.product?.brand || '-' }}] {{ item.product?.name || item.name }}</span>
                                        <span class="text-[10px] font-black px-2 py-0.5 bg-surface-200 dark:bg-surface-700 rounded text-text-secondary uppercase">{{ item.condition || 'SCD' }}</span>
                                    </div>
                                    <div class="flex justify-between text-[10px] text-text-secondary font-bold">
                                        <span>{{ item.imei ? 'IMEI: ' + item.imei : 'Stok: ' + (item.stock || item.quantity) }}</span>
                                        <span class="text-primary-600">Jual: {{ formatCurrency(item.selling_price || item.price) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p v-if="selectedOutgoingTukarTambah"
                            class="mt-3 p-4 bg-primary-50 dark:bg-primary-900/10 rounded-xl border border-primary-100 dark:border-primary-800 text-xs font-semibold text-primary-700 dark:text-primary-400">
                            Unit Terpilih: {{ selectedOutgoingTukarTambah.product?.name ||
                                selectedOutgoingTukarTambah.name }} ({{
                                selectedOutgoingTukarTambah.imei || 'Non-IMEI' }})
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">HARGA
                            BARANG KELUAR / JUAL <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <span
                                class="absolute left-4 top-1/2 -translate-y-1/2 text-sm font-bold text-text-secondary">Rp</span>
                            <input v-money:outgoing_price="tukarTambahForm" type="text"
                                :placeholder="'Contoh: ' + formatNumber(suggestedOutgoingPrice)"
                                class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl pl-10 pr-4 py-3 bg-white dark:bg-surface-900 focus:border-primary-500 transition-all outline-none font-black text-lg text-primary-600" />
                        </div>
                    </div>

                    <div v-if="selectedOutgoingTukarTambah && !selectedOutgoingTukarTambah.imei">
                        <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">JUMLAH KELUAR <span class="text-red-500">*</span></label>
                        <div class="flex items-center gap-4">
                            <input v-model.number="tukarTambahForm.outgoing_quantity" type="number" min="1" :max="selectedOutgoingTukarTambah.stock || selectedOutgoingTukarTambah.quantity"
                                class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none" />
                            <span class="text-xs font-bold text-text-secondary uppercase">Unit (Maks: {{ selectedOutgoingTukarTambah.stock || selectedOutgoingTukarTambah.quantity }})</span>
                        </div>
                    </div>

                    <!-- Photo and Additional Media Section -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-6">
                        <div>
                            <label
                                class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2 text-center">FOTO
                                UNIT <span class="text-red-500">*</span></label>
                            <div @click="$refs.unitTTInput.click()"
                                class="relative border-2 border-dashed border-surface-300 dark:border-surface-600 rounded-xl aspect-square flex flex-col items-center justify-center cursor-pointer hover:bg-surface-50 dark:hover:bg-surface-800 transition-all overflow-hidden group">
                                <template v-if="isCompressing">
                                    <Loader2 class="w-8 h-8 text-primary-600 animate-spin" />
                                    <span class="text-[10px] font-black text-text-secondary uppercase mt-2">Memproses...</span>
                                </template>
                                <template v-else-if="tukarTambahPhotos.unitPreview">
                                    <img :src="tukarTambahPhotos.unitPreview" class="w-full h-full object-cover" />
                                    <div
                                        class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity">
                                        <Camera class="text-white w-6 h-6" />
                                    </div>
                                </template>
                                <template v-else>
                                    <Plus :size="24" class="text-text-secondary mb-1" />
                                    <span class="text-[9px] font-black text-text-secondary uppercase">Upload
                                        Unit</span>
                                </template>
                            </div>
                            <input type="file" ref="unitTTInput" @change="e => handlePhotoChange('unit', e)"
                                accept="image/*" class="hidden" capture="environment" />
                        </div>
                        <div>
                            <label
                                class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2 text-center">FOTO
                                CUSTOMER <span class="text-red-500">*</span></label>
                            <div @click="$refs.customerTTInput.click()"
                                class="relative border-2 border-dashed border-surface-300 dark:border-surface-600 rounded-xl aspect-square flex flex-col items-center justify-center cursor-pointer hover:bg-surface-50 dark:hover:bg-surface-800 transition-all overflow-hidden group">
                                <template v-if="isCompressing">
                                    <Loader2 class="w-8 h-8 text-primary-600 animate-spin" />
                                    <span class="text-[10px] font-black text-text-secondary uppercase mt-2">Memproses...</span>
                                </template>
                                <template v-else-if="tukarTambahPhotos.customerPreview">
                                    <img :src="tukarTambahPhotos.customerPreview" class="w-full h-full object-cover" />
                                    <div
                                        class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity">
                                        <Camera class="text-white w-6 h-6" />
                                    </div>
                                </template>
                                <template v-else>
                                    <Plus :size="24" class="text-text-secondary mb-1" />
                                    <span class="text-[9px] font-black text-text-secondary uppercase">Upload
                                        Customer</span>
                                </template>
                            </div>
                            <input type="file" ref="customerTTInput"
                                @change="e => handlePhotoChange('customer', e)" accept="image/*" class="hidden"
                                capture="environment" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. ALASAN, PEMBAYARAN & SUMMARY -->
            <div class="mt-8 space-y-6">
                <h4
                    class="text-sm font-black text-primary-600 uppercase tracking-widest border-b border-primary-100 dark:border-primary-900/30 pb-2">
                    PEMBAYARAN & RINGKASAN
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-start">
                    <div class="space-y-6">
                        <div>
                            <label
                                class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">ALASAN
                                TUKAR TAMBAH <span class="text-red-500">*</span></label>
                            <textarea v-model="tukarTambahForm.reason" rows="3"
                                class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none text-sm"
                                placeholder="Kenapa barang ini ditukar tambah? (Wajib diisi)"></textarea>
                        </div>
                        <div>
                            <label
                                class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">METODE
                                PEMBAYARAN <span class="text-red-500">*</span></label>
                            <select v-model="tukarTambahForm.payment_method_id"
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
                        class="p-8 bg-primary-600 rounded-[2rem] shadow-2xl shadow-primary-500/30 text-center transform transition-all hover:scale-[1.02]">
                        <div class="grid grid-cols-2 gap-4 mb-6">
                            <div class="text-left">
                                <p class="text-lg font-bold text-white">
                                    {{ formatCurrency(tukarTambahForm.outgoing_price * tukarTambahForm.outgoing_quantity) }}
                                </p>
                            </div>
                            <div class="text-right">
                                <span
                                    class="text-[9px] font-black text-primary-200 uppercase tracking-widest block mb-1">TOTAL
                                    UNIT MASUK</span>
                                <p class="text-lg font-bold text-white">
                                    {{ formatCurrency(tukarTambahForm.incoming_cost_price * tukarTambahForm.incoming_quantity) }}
                                </p>
                            </div>
                        </div>

                        <div class="pt-6 border-t border-white/20">
                            <span
                                class="text-[10px] font-black text-primary-100 uppercase tracking-[0.2em] block mb-2">SELISIH
                                HARGA (SISA BAYAR)</span>
                            <p class="text-4xl sm:text-5xl font-black text-white px-2 py-1 leading-none">
                                {{ formatCurrency(tukarTambahPriceDiff) }}
                            </p>
                        </div>
                        <div
                            class="mt-6 px-4 py-2 bg-white/10 rounded-full inline-flex items-center gap-2 text-[10px] text-white font-black uppercase tracking-widest border border-white/20"
                            :class="{ 'bg-red-500/40 border-red-500/60': tukarTambahPriceDiff < 0 }">
                            <AlertCircle :size="14" />
                            <span>
                                {{
                                    tukarTambahPriceDiff >= 0 ? 'USER BAYAR KE TOKO' : 'Gunakan Menu Downgrade (Selisih Minus)'
                                }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit Section -->
            <div class="mt-12 pt-8 border-t border-surface-100 dark:border-surface-700 flex flex-col sm:flex-row gap-4">
                <button @click="emit('back')"
                    class="flex-1 py-4 bg-surface-100 dark:bg-surface-700 text-text-primary rounded-2xl font-black uppercase tracking-widest hover:bg-surface-200 transition-all">
                    Kembali Pilih Kategori
                </button>
                <button @click="submitTukarTambah()" :disabled="isSubmitting || tukarTambahPriceDiff < 0"
                    class="flex-[2] py-4 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white rounded-2xl font-black uppercase tracking-widest shadow-xl shadow-emerald-500/20 transition-all flex items-center justify-center gap-3"
                    :class="{ 'bg-surface-300 dark:bg-surface-600 cursor-not-allowed': tukarTambahPriceDiff < 0 }">
                    <Loader2 v-if="isSubmitting" class="animate-spin" :size="24" />
                    <template v-else>
                        <Save :size="24" /> Selesaikan Tukar Tambah
                    </template>
                </button>
            </div>
        </div>
    </div>
</template>
