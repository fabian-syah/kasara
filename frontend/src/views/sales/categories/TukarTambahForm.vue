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
const inventoryStore = useInventoryStore();
const isSubmitting = ref(false);
const isRestoring = ref(true);
const suggestedOutgoingPrice = ref(0);
const stockSearchQuery = ref("");
const showStockDropdown = ref(false);

const isCompressing = ref(false);
const showValidationModal = ref(false);
const validationMessage = ref("");

function showValidationError(msg) {
    validationMessage.value = msg;
    showValidationModal.value = true;
}

const tukarTambahPhotos = ref({
    unit: null,
    unitPreview: null,
    customer: null,
    customerPreview: null,
    paymentProof: null,
    paymentProofPreview: null
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
    payment_method_id: null,
    reason: "",
    notes: "",
});

const outgoingItems = ref([]); // For multiple outgoing items

// Multi-item support: additional incoming items beyond the first one
const additionalItems = ref([]);

function addOutgoingItem() {
    // Usually added via selectStockItem
}

function removeOutgoingItem(index) {
    outgoingItems.value.splice(index, 1);
}

function addItem() {
    additionalItems.value.push({
        incoming_source: tukarTambahForm.value.incoming_source,
        distributor_id: tukarTambahForm.value.distributor_id,
        brand_id: null,
        product_type_id: null,
        storage: "",
        condition: "second",
        imeis_raw: "",
        quantity: 1,
        buy_price: 0,
    });
}

function removeItem(index) {
    additionalItems.value.splice(index, 1);
}

function getFilteredBrandsForItem(item) {
    const defaultBrands = props.brands || [];
    if (!item.distributor_id) return defaultBrands;
    const dist = (props.distributors || []).find(d => d.id === item.distributor_id);
    if (!dist || !dist.allowed_brands) return defaultBrands;
    try {
        const allowedIds = typeof dist.allowed_brands === 'string' ? JSON.parse(dist.allowed_brands) : dist.allowed_brands;
        if (!Array.isArray(allowedIds)) return defaultBrands;
        const numericIds = allowedIds.map(id => Number(id));
        return defaultBrands.filter(b => numericIds.includes(Number(b.id)));
    } catch {
        return defaultBrands;
    }
}

function getFilteredTypesForItem(item) {
    if (!item.brand_id) return [];
    return (props.productTypes || []).filter(t => t.brand_id === item.brand_id);
}

function getCapacitiesForItem(item) {
    if (!item.product_type_id) return [];
    const set = new Set();
    const type = (props.productTypes || []).find(t => t.id === item.product_type_id);
    if (type?.storage) {
        type.storage.split(/[,]/).forEach(s => {
            const clean = s.trim();
            if (clean) set.add(clean);
        });
    }
    const prices = (props.productPrices || []).filter(p => p.product_type_id === item.product_type_id);
    prices.forEach(p => { if (p.storage) set.add(p.storage); });
    return Array.from(set).sort();
}

function isItemImei(item) {
    const pt = props.productTypes.find(t => t.id === item.product_type_id);
    if (!pt) return false;
    const cat = pt.category?.toLowerCase();
    return cat === 'imei' || cat === 'hp / gadget';
}

// Persistence Logic
const storageKey = computed(() => {
    const userId = authStore.user?.id || 'guest';
    const acc = props.salesAccount ? `_acc_${props.salesAccount.replace(/\s+/g, '_')}` : '';
    return `temp_tukar_tambah_form_${userId}${acc}`;
});

watch([tukarTambahForm, stockSearchQuery, tukarTambahPhotos, additionalItems, outgoingItems], ([newForm, newQuery, newPhotos]) => {
    if (isRestoring.value) return;
    
    


function addItem() {
    additionalItems.value.push({
        incoming_source: tukarTambahForm.value.incoming_source,
        distributor_id: tukarTambahForm.value.distributor_id,
        brand_id: null,
        product_type_id: null,
        storage: "",
        condition: "second",
        imeis_raw: "",
        quantity: 1,
        buy_price: 0,
    });
}

function removeItem(index) {
    additionalItems.value.splice(index, 1);
}

function getFilteredBrandsForItem(item) {
    const defaultBrands = props.brands || [];
    if (!item.distributor_id) return defaultBrands;
    const dist = (props.distributors || []).find(d => d.id === item.distributor_id);
    if (!dist || !dist.allowed_brands) return defaultBrands;
    try {
        const allowedIds = typeof dist.allowed_brands === 'string' ? JSON.parse(dist.allowed_brands) : dist.allowed_brands;
        if (!Array.isArray(allowedIds)) return defaultBrands;
        const numericIds = allowedIds.map(id => Number(id));
        return defaultBrands.filter(b => numericIds.includes(Number(b.id)));
    } catch {
        return defaultBrands;
    }
}

function getFilteredTypesForItem(item) {
    if (!item.brand_id) return [];
    return (props.productTypes || []).filter(t => t.brand_id === item.brand_id);
}

function getCapacitiesForItem(item) {
    if (!item.product_type_id) return [];
    const set = new Set();
    const type = (props.productTypes || []).find(t => t.id === item.product_type_id);
    if (type?.storage) {
        type.storage.split(/[,]/).forEach(s => {
            const clean = s.trim();
            if (clean) set.add(clean);
        });
    }
    const prices = (props.productPrices || []).filter(p => p.product_type_id === item.product_type_id);
    prices.forEach(p => { if (p.storage) set.add(p.storage); });
    return Array.from(set).sort();
}

const persistentPhotos = {
        unitPreview: newPhotos.unitPreview,
        customerPreview: newPhotos.customerPreview,
        paymentProofPreview: newPhotos.paymentProofPreview
    };

    localStorage.setItem(storageKey.value, JSON.stringify({
        form: newForm,
        query: newQuery,
        photos: persistentPhotos,
        outgoing: outgoingItems.value,
        additionalIncoming: additionalItems.value
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
            if (data.outgoing) outgoingItems.value = data.outgoing;
            if (data.additionalIncoming) additionalItems.value = data.additionalIncoming;
            
            if (data.photos) {
                tukarTambahPhotos.value.unitPreview = data.photos.unitPreview;
                tukarTambahPhotos.value.customerPreview = data.photos.customerPreview;
                tukarTambahPhotos.value.paymentProofPreview = data.photos.paymentProofPreview;
                
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
                if (data.photos.paymentProofPreview && data.photos.paymentProofPreview.startsWith('data:')) {
                    try {
                        tukarTambahPhotos.value.paymentProof = dataURLtoFile(data.photos.paymentProofPreview, 'payment_proof_restored.jpg');
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
    return null; // Deprecated, using outgoingItems array
});

const filteredInventoryProducts = computed(() => {
    const q = stockSearchQuery.value.toLowerCase().trim();
    const allProducts = inventoryStore.products.filter(p => (p.imei || p.stock > 0 || p.quantity > 0) && p.status !== 'sold');
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

const totalOutgoingPriceComputed = computed(() => {
    let totalOut = 0;
    for (const item of outgoingItems.value) {
        totalOut += (item.price || 0) * (item.quantity || 1);
    }
    return totalOut;
});

const tukarTambahPriceDiff = computed(() => {
    let totalOut = totalOutgoingPriceComputed.value;
    let totalIn = (tukarTambahForm.value.incoming_cost_price || 0) * (tukarTambahForm.value.incoming_quantity || 1);
    
    // Add additional items
    for (const item of additionalItems.value) {
        totalIn += (item.buy_price || 0) * (item.quantity || 1);
    }
    
    return totalOut - totalIn;
});

const totalIncomingPriceComputed = computed(() => {
    let totalIn = (tukarTambahForm.value.incoming_cost_price || 0) * (tukarTambahForm.value.incoming_quantity || 1);
    for (const item of additionalItems.value) {
        totalIn += (item.buy_price || 0) * (item.quantity || 1);
    }
    return totalIn;
});

const isSplitInvalid = computed(() => {
    const totalSplit = splitPayments.value.reduce((sum, p) => sum + (p.amount || 0), 0);
    return totalSplit !== tukarTambahPriceDiff.value;
});

const isCashOnly = computed(() => {
    return splitPayments.value.every(p => {
        const method = props.availablePaymentMethods.find(m => m.id === p.method_id);
        if (method) {
            const name = method.name.toLowerCase();
            return name.includes('cash') || name.includes('tunai');
        }
        return false;
    });
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
            method_id: tukarTambahForm.value.payment_method_id || props.availablePaymentMethods[0]?.id || null,
            amount: Math.max(0, tukarTambahPriceDiff.value)
        });
    }
});

watch(() => tukarTambahPriceDiff.value, (newDiff) => {
    if (splitPayments.value.length === 1) {
        splitPayments.value[0].amount = Math.max(0, newDiff);
    }
});

watch(() => props.availablePaymentMethods, (methods) => {
    if (methods?.length > 0 && !tukarTambahForm.value.payment_method_id) {
        const cashMethod = methods.find(p => p.category?.toLowerCase() === 'cash' || p.name?.toLowerCase() === 'tunai');
        tukarTambahForm.value.payment_method_id = cashMethod ? cashMethod.id : methods[0].id;
        if (splitPayments.value.length > 0 && !splitPayments.value[0].method_id) {
            splitPayments.value[0].method_id = tukarTambahForm.value.payment_method_id;
        }
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
            showValidationError("Gagal mengompres gambar. Silakan coba lagi.");
        } finally {
            isCompressing.value = false;
        }
    }
}

function selectStockItem(item) {
    let price = 0;
    const cleanQ = stockSearchQuery.value.replace(/\./g, '').trim();
    if (/^\d+$/.test(cleanQ) && cleanQ.length >= 4) {
        price = parseInt(cleanQ);
    } else {
        const selling = parseFloat(item.selling_price || item.price || 0);
        const cost = parseFloat(item.cost_price || 0);
        price = selling > 0 ? selling : (cost > 0 ? cost : 0);
    }

    outgoingItems.value.push({
        product_detail_id: item.id,
        item: item,
        price: price,
        quantity: 1,
        max_quantity: item.stock || item.quantity || 1
    });

    stockSearchQuery.value = "";
    showStockDropdown.value = false;
}

function closeStockDropdown() {
    setTimeout(() => {
        showStockDropdown.value = false;
    }, 200);
}

async function submitTukarTambah(pin = null) {
    if (isImeiTukarTambah.value && tukarTambahForm.value.incoming_imei) {
        const incomingImeiLower = tukarTambahForm.value.incoming_imei.toLowerCase().trim();
        const hasDuplicateImei = outgoingItems.value.some(outItem => outItem.item?.imei && outItem.item.imei.toLowerCase().trim() === incomingImeiLower);
        if (hasDuplicateImei) {
            showValidationError("Gagal diproses: IMEI Unit Masuk tidak boleh sama dengan IMEI Unit Keluar.");
            return;
        }
    }

    if (tukarTambahPriceDiff.value <= 0) {
        showValidationError("Harga Unit Keluar tidak lebih besar dari Harga Unit Masuk. Tukar Tambah seharusnya nilai Unit Keluar lebih besar dari Unit Masuk. Silakan gunakan menu 'Tukar Unit' jika harga sama, atau 'Downgrade' jika unit toko lebih murah.");
        return;
    }

    if (!tukarTambahForm.value.customer_name || !tukarTambahForm.value.customer_phone || !tukarTambahForm.value.incoming_brand_id || !tukarTambahForm.value.incoming_product_type_id || !tukarTambahForm.value.incoming_storage || !tukarTambahForm.value.incoming_condition || !tukarTambahForm.value.incoming_cost_price || outgoingItems.value.length === 0 || !tukarTambahForm.value.reason || !tukarTambahForm.value.distributor_id) {
        showValidationError("Mohon lengkapi semua data wajib (Customer, Distributor, Barang Masuk, Barang Keluar, Harga Jual, Metode Bayar, & Alasan).");
        return;
    }

    const totalSplit = splitPayments.value.reduce((sum, p) => sum + (p.amount || 0), 0);
    if (totalSplit !== tukarTambahPriceDiff.value) {
        showValidationError(`Total split pembayaran (${formatCurrency(totalSplit)}) tidak sesuai dengan sisa bayar (${formatCurrency(tukarTambahPriceDiff.value)}).`);
        return;
    }

    if (!tukarTambahPhotos.value.unit) {
        showValidationError("Foto unit wajib diupload.");
        return;
    }

    if (!isCashOnly.value && tukarTambahPriceDiff.value > 0 && !tukarTambahPhotos.value.paymentProof) {
        showValidationError("Foto bukti pembayaran transfer wajib diupload untuk metode non-tunai.");
        return;
    }

    if (!pin && props.selectedAccountObject) {
        emit('verify-pin', (verifiedPin) => submitTukarTambah(verifiedPin));
        return;
    }


    isSubmitting.value = true;
    const formData = new FormData();
    if (tukarTambahPhotos.value.unit) formData.append('photo_unit', tukarTambahPhotos.value.unit);
    if (tukarTambahPhotos.value.customer) formData.append('photo_customer', tukarTambahPhotos.value.customer);
    if (tukarTambahPhotos.value.paymentProof) formData.append('payment_proof_image', tukarTambahPhotos.value.paymentProof);
    // if (pin) formData.append('password', pin);
      if (pin) formData.append('transaction_pin', pin);

    if (props.selectedAccountObject?.id) formData.append('inventory_user_id', props.selectedAccountObject.id);
    if (props.salesAccount) formData.append('sales_account', props.salesAccount);
    formData.append('customer_name', tukarTambahForm.value.customer_name);
    formData.append('customer_phone', tukarTambahForm.value.customer_phone);
    if (tukarTambahForm.value.distributor_id) {
        formData.append('distributor_id', tukarTambahForm.value.distributor_id);
        const selectedDist = props.distributors.find(d => d.id === tukarTambahForm.value.distributor_id);
        if (selectedDist) formData.append('distributor_name', selectedDist.name);
    }
    formData.append('incoming_source', tukarTambahForm.value.incoming_source);
    formData.append('incoming_product_type_id', tukarTambahForm.value.incoming_product_type_id);
    formData.append('incoming_storage', tukarTambahForm.value.incoming_storage);
    formData.append('incoming_condition', tukarTambahForm.value.incoming_condition);
    formData.append('incoming_imei', tukarTambahForm.value.incoming_imei);
    formData.append('incoming_quantity', tukarTambahForm.value.incoming_quantity);
    formData.append('incoming_cost_price', tukarTambahForm.value.incoming_cost_price);

    // Use the first outgoing item for scalar fallback if needed
    if (outgoingItems.value.length > 0) {
        formData.append('outgoing_product_detail_id', outgoingItems.value[0].product_detail_id);
        formData.append('outgoing_quantity', outgoingItems.value[0].quantity);
        formData.append('outgoing_price', outgoingItems.value[0].price);
    }

    const allOutgoingItems = outgoingItems.value.map(item => ({
        product_detail_id: item.product_detail_id,
        quantity: item.quantity,
        price: item.price
    }));
    formData.append('outgoing_items', JSON.stringify(allOutgoingItems));

    formData.append('price_difference', tukarTambahPriceDiff.value);
    formData.append('payment_method_id', splitPayments.value[0]?.method_id || tukarTambahForm.value.payment_method_id || 1);
    formData.append('split_payments', JSON.stringify(splitPayments.value.map(p => ({
        payment_method_id: p.method_id,
        amount: p.amount
    }))));
    formData.append('reason', tukarTambahForm.value.reason);
    formData.append('notes', tukarTambahForm.value.notes);

    // Multi-item support: if there are additional items, send as items[] array
    if (additionalItems.value.length > 0) {
        const allItems = [];
        
        // Main item
        allItems.push({
            brand_id: tukarTambahForm.value.incoming_brand_id,
            product_type_id: tukarTambahForm.value.incoming_product_type_id,
            imeis: isImeiTukarTambah.value ? [tukarTambahForm.value.incoming_imei].filter(i => i) : [],
            quantity: isImeiTukarTambah.value ? 1 : (tukarTambahForm.value.incoming_quantity || 1),
            storage: tukarTambahForm.value.incoming_storage,
            condition: tukarTambahForm.value.incoming_condition,
            buy_price: tukarTambahForm.value.incoming_cost_price,
            distributor_id: tukarTambahForm.value.distributor_id,
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
                distributor_id: item.distributor_id || tukarTambahForm.value.distributor_id,
            });
        }

        formData.append('items', JSON.stringify(allItems));
    }

    try {
        const response = await api.post('/tukar-tambah', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });

        const data = response.data.data;
        const finalItems = [];

        for (const outItem of outgoingItems.value) {
            finalItems.push({
                name: 'OUT: ' + (outItem.item?.product?.name || outItem.item?.name || 'Unit Keluar'),
                imei: outItem.item?.imei || '-',
                price: outItem.price,
                condition: outItem.item?.condition || 'second',
                storage: outItem.item?.storage,
                qty: outItem.quantity,
                is_hp: !!outItem.item?.imei
            });
        }

        finalItems.push({
            name: 'IN: ' + (selectedTukarTambahType.value?.name || 'Unit Masuk'),
            imei: tukarTambahForm.value.incoming_imei || '-',
            price: -tukarTambahForm.value.incoming_cost_price,
            condition: tukarTambahForm.value.incoming_condition,
            storage: tukarTambahForm.value.incoming_storage,
            qty: tukarTambahForm.value.incoming_quantity,
            is_hp: isImeiTukarTambah.value
        });

        for (const item of additionalItems.value) {
            const itemType = props.productTypes.find(t => t.id === item.product_type_id);
            const itemIsHp = isItemImei(item);
            finalItems.push({
                name: 'IN: ' + (itemType?.name || 'Unit Masuk'),
                imei: itemIsHp ? (item.imeis_raw || '').replace(/\n/g, ', ') || '-' : '-',
                price: -item.buy_price,
                condition: item.condition,
                storage: item.storage,
                qty: itemIsHp ? Math.max(1, (item.imeis_raw || '').split(/[\n,]/).filter(i => i.trim()).length) : (item.quantity || 1),
                is_hp: itemIsHp
            });
        }

        const transaction = {
            id: data.id,
            order_no: data.receipt_id,
            items: finalItems,
            original_price: tukarTambahPriceDiff.value,
            grand_total: tukarTambahPriceDiff.value,
            total: tukarTambahPriceDiff.value,
            paid: tukarTambahPriceDiff.value,
            payment_method_name: props.availablePaymentMethods.find(m => m.id === (splitPayments.value[0]?.method_id || tukarTambahForm.value.payment_method_id))?.name,
            split_payments_data: splitPayments.value.map(p => ({
                method_name: props.availablePaymentMethods.find(m => m.id === p.method_id)?.name || 'Unknown',
                amount: p.amount
            })),
            category: 'tukar_tambah',
            customer_name: data.customer_name,
            customer_phone: data.customer_phone,
            branch_name: props.selectedAccountObject?.branch?.name || authStore.user?.branch?.name || '',
            branch_timezone: authStore.user?.branch?.timezone || 'WIB',
            created_at: new Date().toISOString(),
            date: new Date().toLocaleDateString("id-ID", {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            }),
            time: new Date().toLocaleTimeString("id-ID", { hour: '2-digit', minute: '2-digit' }),
            inventory_user_name: props.salesAccount || authStore.user?.name,
            distributor_name: data.distributor?.name || 'KOSONG',
            proof_images: [
                data.photo_unit ? `${authStore.storageBaseUrl}/storage/${data.photo_unit}` : null,
                data.photo_customer ? `${authStore.storageBaseUrl}/storage/${data.photo_customer}` : null,
                data.payment_proof_image ? `${authStore.storageBaseUrl}/storage/${data.payment_proof_image}` : null
            ].filter(Boolean)
        };

        emit("transaction-complete", transaction);

        additionalItems.value = [];
        outgoingItems.value = [];
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
            payment_method_id: null,
            reason: "",
            notes: "",
        };
        splitPayments.value = [
            {
                method_id: props.availablePaymentMethods[0]?.id || null,
                amount: 0
            }
        ];
        tukarTambahPhotos.value = { unit: null, unitPreview: null, customer: null, customerPreview: null, paymentProof: null, paymentProofPreview: null };

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
        class="flex-1 overflow-y-auto custom-scrollbar bg-white/70 dark:bg-surface-800/70 backdrop-blur-2xl rounded-[2rem] border border-white/50 dark:border-surface-600/30 p-5 pb-24 sm:p-10 sm:pb-10 shadow-2xl relative">
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
                        <div v-if="outgoingItems.length > 0" class="mt-4 space-y-4">
                            <div v-for="(outItem, index) in outgoingItems" :key="index" class="p-4 bg-primary-50 dark:bg-primary-900/10 rounded-xl border border-primary-100 dark:border-primary-800 relative">
                                <button @click="removeOutgoingItem(index)" type="button" class="absolute top-2 right-2 text-primary-400 hover:text-red-500 transition-colors">
                                    <X :size="20" />
                                </button>
                                <p class="text-xs font-black text-primary-700 dark:text-primary-400 mb-4 pr-6">
                                    {{ outItem.item?.product?.name || outItem.item?.name }} ({{ outItem.item?.imei || 'Non-IMEI' }})
                                </p>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-[10px] font-bold text-primary-600 dark:text-primary-400 uppercase tracking-widest mb-2">HARGA JUAL</label>
                                        <div class="relative">
                                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-primary-600/50">Rp</span>
                                            <input v-money:price="outItem" type="text"
                                                class="w-full border-2 border-primary-200 dark:border-primary-800/50 rounded-xl pl-8 pr-3 py-2 bg-white dark:bg-surface-900 focus:border-primary-500 transition-all outline-none font-bold text-sm text-primary-600" />
                                        </div>
                                    </div>
                                    <div v-if="!outItem.item?.imei">
                                        <label class="block text-[10px] font-bold text-primary-600 dark:text-primary-400 uppercase tracking-widest mb-2">JUMLAH KELUAR</label>
                                        <div class="flex items-center gap-2">
                                            <input v-model.number="outItem.quantity" type="number" min="1" :max="outItem.max_quantity"
                                                class="w-full border-2 border-primary-200 dark:border-primary-800/50 rounded-xl px-3 py-2 bg-white dark:bg-surface-900 focus:border-primary-500 transition-all outline-none text-sm font-bold" />
                                            <span class="text-[10px] font-bold text-primary-600/60 uppercase">Maks: {{ outItem.max_quantity }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
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
                        <div v-if="!isCashOnly && tukarTambahPriceDiff > 0" class="col-span-1 sm:col-span-2 md:col-span-1">
                            <label class="block text-xs font-bold text-amber-600 uppercase tracking-widest mb-2 text-center">FOTO BUKTI TRANSFER <span class="text-red-500">*</span></label>
                            <div @click="$refs.paymentProofTTInput.click()" class="relative border-2 border-dashed border-amber-300 dark:border-amber-600 rounded-xl aspect-square sm:aspect-[2/1] md:aspect-square flex flex-col items-center justify-center cursor-pointer hover:bg-amber-50 dark:hover:bg-amber-900/10 transition-all overflow-hidden group">
                                <template v-if="isCompressing">
                                    <Loader2 class="w-8 h-8 text-amber-600 animate-spin" />
                                    <span class="text-[10px] font-black text-amber-600 uppercase mt-2">Memproses...</span>
                                </template>
                                <template v-else-if="tukarTambahPhotos.paymentProofPreview">
                                    <img :src="tukarTambahPhotos.paymentProofPreview" class="w-full h-full object-cover" />
                                    <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity">
                                        <Camera class="text-white w-6 h-6" />
                                    </div>
                                </template>
                                <template v-else>
                                    <Plus :size="24" class="text-amber-500 mb-1" />
                                    <span class="text-[9px] font-black text-amber-600 uppercase">Upload Bukti TF</span>
                                </template>
                            </div>
                            <input type="file" ref="paymentProofTTInput" @change="e => handlePhotoChange('paymentProof', e)" accept="image/*" class="hidden" capture="environment" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Additional Incoming Items Section -->
            <div class="mt-8 space-y-4">
                <div v-for="(item, index) in additionalItems" :key="index" class="p-4 bg-surface-50 dark:bg-surface-900 rounded-xl border border-surface-200 dark:border-surface-700 relative">
                    <button @click="removeItem(index)" type="button" class="absolute top-2 right-2 text-gray-400 hover:text-red-500">
                        <X :size="20" />
                    </button>
                    <h5 class="text-xs font-bold text-emerald-600 dark:text-emerald-400 mb-4">UNIT MASUK #{{ index + 2 }}</h5>
                    
                    <div class="mb-4">
                        <label class="block text-[10px] font-bold text-text-secondary uppercase mb-1">SUMBER BARANG</label>
                        <select v-model="item.incoming_source" class="w-full border-2 border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800 rounded-lg px-3 py-2">
                            <option value="luar_pstore">Luar PStore</option>
                            <option value="ex_pstore">Ex PStore</option>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-[10px] font-bold text-text-secondary uppercase mb-1">PILIH DISTRIBUTOR</label>
                        <select v-model="item.distributor_id" @change="item.brand_id = null; item.product_type_id = null" class="w-full border-2 border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800 rounded-lg px-3 py-2 font-bold text-primary-600">
                            <option :value="null">-- PILIH DISTRIBUTOR --</option>
                            <option v-for="d in distributors" :key="d.id" :value="d.id">{{ d.name }}</option>
                        </select>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-[10px] font-bold text-text-secondary uppercase mb-1">Brand</label>
                            <select v-model="item.brand_id" @change="item.product_type_id = null; item.storage = ''; item.condition = 'second'" class="w-full border-2 border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800 rounded-lg px-3 py-2">
                                <option :value="null">Pilih Brand</option>
                                <option v-for="b in getFilteredBrandsForItem(item)" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-text-secondary uppercase mb-1">Tipe</label>
                            <select v-model="item.product_type_id" @change="item.storage = ''; item.condition = 'second'" class="w-full border-2 border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800 rounded-lg px-3 py-2" :disabled="!item.brand_id">
                                <option :value="null">Pilih Tipe</option>
                                <option v-for="p in getFilteredTypesForItem(item)" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select>
                        </div>
                    </div>
                    
                    <div v-if="item.product_type_id" class="grid grid-cols-2 gap-4 mb-4">
                        <div v-if="isItemImei(item)">
                            <label class="block text-[10px] font-bold text-text-secondary uppercase mb-1">Storage</label>
                            <select v-model="item.storage" class="w-full border-2 border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800 rounded-lg px-3 py-2">
                                <option value="">Pilih Storage</option>
                                <option v-for="s in getCapacitiesForItem(item)" :key="s" :value="s">{{ s }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-text-secondary uppercase mb-1">Kondisi</label>
                            <select v-model="item.condition" class="w-full border-2 border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800 rounded-lg px-3 py-2">
                                <option value="new">New</option>
                                <option value="second">Second / SCD</option>
                                <option value="ex_ibox">Ex iBox</option>
                            </select>
                        </div>
                    </div>
                    
                    <div v-if="item.product_type_id" class="mb-4">
                        <div v-if="isItemImei(item)">
                            <label class="block text-[10px] font-bold text-text-secondary uppercase mb-1">IMEI (Pisahkan dengan baris baru)</label>
                            <textarea v-model="item.imeis_raw" rows="2" class="w-full border-2 border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800 rounded-lg px-3 py-2" placeholder="12345..."></textarea>
                        </div>
                        <div v-else class="flex items-center gap-4">
                            <div class="flex-1">
                                <label class="block text-[10px] font-bold text-text-secondary uppercase mb-1">Quantity</label>
                                <input v-model.number="item.quantity" type="number" min="1" class="w-full border-2 border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800 rounded-lg px-3 py-2" />
                            </div>
                        </div>
                    </div>
                    
                    <div v-if="item.product_type_id">
                        <label class="block text-[10px] font-bold text-text-secondary uppercase mb-1">Harga Modal (Per Unit)</label>
                        <input v-money:buy_price="item" type="text" class="w-full border-2 border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800 rounded-lg px-3 py-2 font-bold text-primary-600 dark:text-primary-400" />
                    </div>
                </div>
                
                <button @click="addItem" type="button" class="w-full py-4 border-2 border-dashed border-emerald-300 dark:border-emerald-700 text-emerald-600 dark:text-emerald-400 rounded-[1.25rem] font-bold tracking-widest flex items-center justify-center gap-2 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-all hover:border-emerald-400 active:scale-[0.98]">
                    <Plus :size="18" /> Tambah Unit Masuk Lain
                </button>
            </div>

                        

            <!-- 4. ALASAN, PEMBAYARAN & SUMMARY -->
            <div class="mt-8 space-y-6">
                <h4
                    class="text-sm font-black text-transparent bg-clip-text bg-gradient-to-r from-primary-600 to-emerald-600 uppercase tracking-[0.15em] border-b border-surface-200 dark:border-surface-700 pb-3 mb-6">
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
                            <div class="flex items-center justify-between mb-2">
                                <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest">METODE PEMBAYARAN <span class="text-red-500">*</span></label>
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
                    </div>

                    <!-- Financial summary card -->
                    <div
                        class="relative overflow-hidden p-8 sm:p-10 bg-gradient-to-br from-emerald-500 via-teal-500 to-primary-600 rounded-[2.5rem] shadow-2xl shadow-emerald-500/30 text-center transform transition-all hover:-translate-y-1 hover:shadow-emerald-500/40"
                    >
                        <div class="absolute inset-0 bg-[url('/noise.png')] opacity-10 mix-blend-overlay pointer-events-none"></div>
                        <div class="absolute -right-20 -top-20 w-64 h-64 bg-white/20 blur-3xl rounded-full pointer-events-none"></div>
                        <div class="absolute -left-20 -bottom-20 w-64 h-64 bg-black/10 blur-3xl rounded-full pointer-events-none"></div>
                        
                        <div class="relative z-10">
                        <div class="grid grid-cols-2 gap-4 mb-8">
                            <div class="text-left bg-white/10 p-4 rounded-2xl border border-white/20 flex flex-col justify-center">
                                <span class="text-[9px] font-black text-primary-200 uppercase tracking-widest block mb-1">TOTAL
                                    UNIT KELUAR</span>
                                <p class="text-lg font-bold text-white truncate">
                                    {{ formatCurrency(totalOutgoingPriceComputed) }}
                                </p>
                            </div>
                            <div class="text-right bg-white/10 p-4 rounded-2xl border border-white/20 flex flex-col justify-center">
                                <span
                                    class="text-[9px] font-black text-primary-200 uppercase tracking-widest block mb-1">TOTAL
                                    UNIT MASUK</span>
                                <p class="text-lg font-bold text-white truncate">
                                    {{ formatCurrency(totalIncomingPriceComputed) }}
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
                            class="mt-8 px-5 py-2.5 bg-white/10 backdrop-blur-md rounded-full inline-flex items-center gap-2 text-[10px] text-white font-black uppercase tracking-widest border border-white/30 shadow-inner"
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
            </div>

            <!-- Submit Section -->
            <div class="mt-12 pt-8 border-t border-surface-100 dark:border-surface-700 flex flex-col sm:flex-row gap-4">
                <button @click="emit('back')"
                    class="flex-1 py-4 sm:py-5 bg-surface-100 dark:bg-surface-800 text-text-primary rounded-2xl font-black uppercase tracking-widest hover:bg-surface-200 dark:hover:bg-surface-700 transition-all active:scale-[0.98] border border-surface-200 dark:border-surface-700">
                    Kembali Pilih Kategori
                </button>
                <button @click="submitTukarTambah()" :disabled="isSubmitting || tukarTambahPriceDiff < 0 || isSplitInvalid"
                    class="flex-[2] py-4 sm:py-5 bg-gradient-to-r from-emerald-500 to-primary-600 hover:from-emerald-400 hover:to-primary-500 disabled:opacity-50 text-white rounded-2xl font-black uppercase tracking-[0.1em] shadow-xl shadow-emerald-500/30 transition-all hover:shadow-emerald-500/50 hover:-translate-y-1 active:scale-[0.98] flex items-center justify-center gap-3"
                    :class="{ 'bg-surface-300 dark:bg-surface-600 cursor-not-allowed opacity-50': tukarTambahPriceDiff < 0 || isSplitInvalid }">
                    <Loader2 v-if="isSubmitting" class="animate-spin" :size="24" />
                    <template v-else>
                        <Save :size="24" /> Selesaikan Tukar Tambah
                    </template>
                </button>
            </div>
        </div>

        <!-- Validation Modal -->
        <div v-if="showValidationModal" class="fixed inset-0 z-[200] flex items-center justify-center p-4 animate-in fade-in duration-200">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showValidationModal = false"></div>
            <div class="bg-surface-900 border border-surface-700 p-8 rounded-[2rem] w-full max-w-md shadow-2xl relative z-10 animate-in zoom-in-95 duration-300">
                <button @click="showValidationModal = false" class="absolute top-5 right-5 w-8 h-8 flex items-center justify-center rounded-full bg-surface-800 hover:bg-surface-700 hover:text-red-500 text-text-secondary transition-colors">
                    <X :size="16" />
                </button>
                <div class="flex items-center gap-3 text-red-500 mb-5 font-black text-xl uppercase tracking-widest">
                    <AlertCircle :size="28" stroke-width="2.5" /> PERHATIAN
                </div>
                <div class="text-text-primary text-sm font-semibold leading-relaxed mb-8 bg-red-500/10 p-5 rounded-2xl border border-red-500/20">
                    {{ validationMessage }}
                </div>
                <button @click="showValidationModal = false" class="w-full py-4 bg-primary-600 hover:bg-primary-500 text-white font-black uppercase tracking-widest rounded-2xl transition-all shadow-xl shadow-primary-500/20 active:scale-95 text-xs">
                    SAYA MENGERTI
                </button>
            </div>
        </div>
    </div>
</template>
