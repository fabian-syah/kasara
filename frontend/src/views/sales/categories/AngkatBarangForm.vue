<script setup>
import { ref, computed, watch, onMounted, nextTick } from "vue";
import api from "../../../api/axios";
import { useAuthStore } from "../../../store/auth";
import { formatCurrency } from "../../../utils/formatters";
import {
    Plus,
    Loader2,
    Save,
    Receipt,
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

const emit = defineEmits(["back", "transaction-complete", "reset"]);

const authStore = useAuthStore();
const isSubmitting = ref(false);
const isRestoring = ref(true);

const isCompressing = ref(false);
const tradeInPhotos = ref({
    unit: null,
    unitPreview: null,
    customer: null,
    customerPreview: null
});

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
            method_id: props.availablePaymentMethods[0]?.id || null,
            amount: 0
        });
    }
});


const tradeInForm = ref({
    customer_name: "",
    customer_phone: "",
    source: "luar_pstore",
    distributor_id: null,
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

// Multi-item support: additional items beyond the first one
const additionalItems = ref([]);

function addItem() {
    additionalItems.value.push({
        source: tradeInForm.value.source || 'luar_pstore',
        brand_id: null,
        product_type_id: null,
        storage: "",
        condition: "",
        imeis_raw: "",
        quantity: 1,
        buy_price: 0,
        distributor_id: tradeInForm.value.distributor_id,
    });
}

function removeItem(index) {
    additionalItems.value.splice(index, 1);
}

function getFilteredTypesForItem(item) {
    if (!item.brand_id) return [];
    return props.productTypes.filter(t => t.brand_id === item.brand_id);
}

function getFilteredBrandsForItem(item) {
    const distId = item.distributor_id || tradeInForm.value.distributor_id;
    if (!distId) return props.brands;
    const dist = props.distributors.find(d => d.id === distId);
    if (!dist || !dist.allowed_brands) return props.brands;
    try {
        const allowedIds = typeof dist.allowed_brands === 'string' ? JSON.parse(dist.allowed_brands) : dist.allowed_brands;
        if (!Array.isArray(allowedIds)) return props.brands;
        const numericIds = allowedIds.map(id => Number(id));
        return props.brands.filter(b => numericIds.includes(Number(b.id)));
    } catch { return props.brands; }
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
    if (!pt) return false; // Don't show IMEI field until type is selected
    const cat = (pt.category || '').toLowerCase().trim();
    return cat === 'imei' || cat === 'hp / gadget' || cat === 'hp/gadget';
}


// Persistence
// Persistence
const storageKey = computed(() => {
    const userId = authStore.user?.id || 'guest';
    const acc = props.salesAccount ? `_acc_${props.salesAccount.replace(/\s+/g, '_')}` : '';
    return `temp_angkat_barang_form_${userId}${acc}`;
});

watch([tradeInForm, tradeInPhotos], ([newForm, newPhotos]) => {
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
            Object.assign(tradeInForm.value, data.form || data);
            
            if (data.photos) {
                tradeInPhotos.value.unitPreview = data.photos.unitPreview;
                tradeInPhotos.value.customerPreview = data.photos.customerPreview;
                
                if (data.photos.unitPreview && data.photos.unitPreview.startsWith('data:')) {
                    try {
                        tradeInPhotos.value.unit = dataURLtoFile(data.photos.unitPreview, 'unit_restored.jpg');
                    } catch (e) {}
                }
                if (data.photos.customerPreview && data.photos.customerPreview.startsWith('data:')) {
                    try {
                        tradeInPhotos.value.customer = dataURLtoFile(data.photos.customerPreview, 'customer_restored.jpg');
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
    if (!tradeInForm.value.distributor_id) return props.brands;
    
    const selectedDist = props.distributors.find(d => d.id === tradeInForm.value.distributor_id);
    if (!selectedDist || !selectedDist.allowed_brands) return props.brands;
    
    let allowedIds = [];
    try {
        allowedIds = typeof selectedDist.allowed_brands === 'string' 
            ? JSON.parse(selectedDist.allowed_brands) 
            : selectedDist.allowed_brands;
    } catch (e) {
        return props.brands;
    }
    
    if (!Array.isArray(allowedIds)) return props.brands;
    
    // Map to numbers for consistent comparison
    const numericIds = allowedIds.map(id => Number(id));
    
    return props.brands.filter(b => numericIds.includes(Number(b.id)));
});

const filteredTradeInTypes = computed(() => {
    if (!tradeInForm.value.brand_id) return [];
    
    // Simply show all types for the selected brand
    // This is more flexible as per "Stock In" behavior
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

const totalAllItemsPrice = computed(() => {
    const count = isImeiTradeIn.value 
        ? tradeInForm.value.imeis_raw.split(/[\n,]/).map(i => i.trim()).filter(i => i !== "").length || 1
        : (tradeInForm.value.quantity || 1);
    let total = (tradeInForm.value.buy_price || 0) * count;
    
    for (const item of additionalItems.value) {
        const itemIsImei = isItemImei(item);
        const itemCount = itemIsImei 
            ? (item.imeis_raw || '').split(/[\n,]/).map(i => i.trim()).filter(i => i !== "").length || 1
            : (item.quantity || 1);
        total += (item.buy_price || 0) * itemCount;
    }
    return total;
});

const isSplitInvalid = computed(() => {
    const count = isImeiTradeIn.value 
        ? tradeInForm.value.imeis_raw.split(/[\n,]/).map(i => i.trim()).filter(i => i !== "").length 
        : (tradeInForm.value.quantity || 1);
    let targetAmount = (tradeInForm.value.buy_price || 0) * (count || 1);
    
    // Add additional items prices
    for (const item of additionalItems.value) {
        const itemIsImei = isItemImei(item);
        const itemCount = itemIsImei 
            ? (item.imeis_raw || '').split(/[\n,]/).map(i => i.trim()).filter(i => i !== "").length || 1
            : (item.quantity || 1);
        targetAmount += (item.buy_price || 0) * itemCount;
    }
    
    const totalSplit = splitPayments.value.reduce((sum, p) => sum + (p.amount || 0), 0);
    return totalSplit !== targetAmount;
});

const filteredTradeInCapacities = computed(() => {
    if (!tradeInForm.value.product_type_id) return [];
    const set = new Set();
    
    // Filter prices by type AND distributor
    const prices = props.productPrices.filter(p => {
        const matchesType = p.product_type_id === tradeInForm.value.product_type_id;
        const matchesDist = !tradeInForm.value.distributor_id || p.distributor_id === tradeInForm.value.distributor_id;
        return matchesType && matchesDist;
    });

    prices.forEach(p => { if (p.storage) set.add(p.storage); });
    
    if (set.size === 0 && selectedTradeInType.value?.storage) {
        selectedTradeInType.value.storage.split(/[,]/).forEach(s => {
            const clean = s.trim();
            if (clean) set.add(clean);
        });
    }
    return Array.from(set).sort();
});

const filteredTradeInConditions = computed(() => {
    const defaults = ['new', 'second', 'ex_ibox'];
    const set = new Set();
    
    if (tradeInForm.value.product_type_id) {
        props.productPrices
            .filter(p => {
                const matchesType = p.product_type_id === tradeInForm.value.product_type_id;
                const matchesStorage = p.storage === tradeInForm.value.storage;
                const matchesDist = !tradeInForm.value.distributor_id || p.distributor_id === tradeInForm.value.distributor_id;
                return matchesType && matchesStorage && matchesDist;
            })
            .forEach(p => { if (p.condition) set.add(p.condition); });
    }
    
    // Fallback if no specific prices found
    if (set.size === 0) defaults.forEach(d => set.add(d));
    
    return Array.from(set);
});

const totalTradeInUnits = computed(() => {
    return tradeInForm.value.quantity || 0;
});

// Watchers
watch(() => tradeInForm.value.distributor_id, (newVal, oldVal) => {
    if (isRestoring.value) return;
    if (newVal !== oldVal) {
        tradeInForm.value.brand_id = null;
        tradeInForm.value.product_type_id = null;
        tradeInForm.value.storage = "";
        tradeInForm.value.condition = "";
    }
});

watch(() => tradeInForm.value.brand_id, (newVal, oldVal) => {
    if (isRestoring.value) return;
    if (newVal !== oldVal) {
        tradeInForm.value.product_type_id = null;
        tradeInForm.value.storage = "";
        tradeInForm.value.condition = "";
    }
});

watch(() => tradeInForm.value.product_type_id, (newVal, oldVal) => {
    if (isRestoring.value) return;
    if (newVal !== oldVal) {
        tradeInForm.value.storage = "";
        tradeInForm.value.condition = "";
    }
});

watch(() => tradeInForm.value.storage, (newVal, oldVal) => {
    if (isRestoring.value) return;
    if (newVal !== oldVal) {
        tradeInForm.value.condition = "";
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

const displayBuyPrice = ref("0");
// No longer needed: handleBuyPriceInput replaced by v-money

function handleImeiInput(e) {
    const val = e.target.value;
    const filtered = val.replace(/[^0-9,\n]/g, "");
    tradeInForm.value.imeis_raw = filtered;
    e.target.value = filtered;
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
            tradeInPhotos.value[type] = compressedFile;

            // 3. Set Preview
            const reader = new FileReader();
            reader.onload = (e) => {
                tradeInPhotos.value[`${type}Preview`] = e.target.result;
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
watch(() => props.availablePaymentMethods, (methods) => {
    if (methods?.length > 0 && !tradeInForm.value.payment_method_id) {
        const cashMethod = methods.find(p => p.category?.toLowerCase() === 'cash' || p.name?.toLowerCase() === 'tunai');
        tradeInForm.value.payment_method_id = cashMethod ? cashMethod.id : methods[0].id;
    }
}, { immediate: true });

async function submitTradeIn(pin = null) {
    const isImei = isImeiTradeIn.value;
    const hasRequiredFields = tradeInForm.value.customer_name && 
                             tradeInForm.value.customer_phone && 
                             tradeInForm.value.brand_id && 
                             tradeInForm.value.product_type_id && 
                             tradeInForm.value.buy_price &&
                             tradeInForm.value.distributor_id;

    const hasSpecificFields = !isImei || (tradeInForm.value.storage && tradeInForm.value.condition);

    if (!hasRequiredFields || !hasSpecificFields) {
         alert("Mohon lengkapi semua data wajib.");
         return;
     }

    const count = isImeiTradeIn.value 
        ? tradeInForm.value.imeis_raw.split(/[\n,]/).map(i => i.trim()).filter(i => i !== "").length 
        : (tradeInForm.value.quantity || 1);
    let targetAmount = (tradeInForm.value.buy_price || 0) * count;
    
    // Add additional items prices
    for (const item of additionalItems.value) {
        const itemIsImei = isItemImei(item);
        const itemCount = itemIsImei 
            ? (item.imeis_raw || '').split(/[\n,]/).map(i => i.trim()).filter(i => i !== "").length || 1
            : (item.quantity || 1);
        targetAmount += (item.buy_price || 0) * itemCount;
    }
    
    const totalSplit = splitPayments.value.reduce((sum, p) => sum + (p.amount || 0), 0);
    if (totalSplit !== targetAmount) {
        alert(`Total split pembayaran (${formatCurrency(totalSplit)}) tidak sesuai dengan total harga angkat (${formatCurrency(targetAmount)}).`);
        return;
    }

    if (!tradeInPhotos.value.unit) {
        alert("Foto unit wajib diupload.");
        return;
    }

    if (!pin && props.selectedAccountObject) {
        emit('verify-pin', (verifiedPin) => submitTradeIn(verifiedPin));
        return;
    }


    isSubmitting.value = true;
    const formData = new FormData();
    if (tradeInPhotos.value.unit) formData.append('photo_unit', tradeInPhotos.value.unit);
    if (tradeInPhotos.value.customer) formData.append('photo_customer', tradeInPhotos.value.customer);
    // if (pin) formData.append('password', pin);
      if (pin) formData.append('transaction_pin', pin);

    if (props.selectedAccountObject?.id) formData.append('inventory_user_id', props.selectedAccountObject.id);
    if (props.salesAccount) formData.append('sales_account', props.salesAccount);
    formData.append('customer_name', tradeInForm.value.customer_name);
    formData.append('customer_phone', tradeInForm.value.customer_phone);
    if (tradeInForm.value.distributor_id) {
        formData.append('distributor_id', tradeInForm.value.distributor_id);
        const selectedDist = props.distributors.find(d => d.id === tradeInForm.value.distributor_id);
        if (selectedDist) formData.append('distributor_name', selectedDist.name);
    }
    formData.append('brand_id', tradeInForm.value.brand_id);
    formData.append('product_type_id', tradeInForm.value.product_type_id);
    formData.append('source', tradeInForm.value.source);
    if (isImeiTradeIn.value) {
        formData.append('storage', tradeInForm.value.storage);
        formData.append('condition', tradeInForm.value.condition);
    }
    formData.append('buy_price', tradeInForm.value.buy_price);
    formData.append('payment_method_id', splitPayments.value[0]?.method_id || 1);
    formData.append('split_payments', JSON.stringify(splitPayments.value.map(p => ({
        payment_method_id: p.method_id,
        amount: p.amount
    }))));
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

    // Multi-item support: if there are additional items, send as items[] array
    if (additionalItems.value.length > 0) {
        // Build items array including the main item
        const allItems = [];
        
        // Main item
        const mainImeis = isImeiTradeIn.value 
            ? tradeInForm.value.imeis_raw.split(/[\n,]/).map(i => i.trim()).filter(i => i !== "")
            : [];
        allItems.push({
            brand_id: tradeInForm.value.brand_id,
            product_type_id: tradeInForm.value.product_type_id,
            imeis: mainImeis,
            quantity: isImeiTradeIn.value ? mainImeis.length : (tradeInForm.value.quantity || 1),
            storage: tradeInForm.value.storage,
            condition: tradeInForm.value.condition,
            buy_price: tradeInForm.value.buy_price,
            distributor_id: tradeInForm.value.distributor_id,
            notes: tradeInForm.value.notes,
        });

        // Additional items
        for (const item of additionalItems.value) {
            const itemIsImei = isItemImei(item);
            const itemImeis = itemIsImei 
                ? item.imeis_raw.split(/[\n,]/).map(i => i.trim()).filter(i => i !== "")
                : [];
            allItems.push({
                brand_id: item.brand_id,
                product_type_id: item.product_type_id,
                imeis: itemImeis,
                quantity: itemIsImei ? itemImeis.length : (item.quantity || 1),
                storage: item.storage,
                condition: item.condition,
                buy_price: item.buy_price,
                distributor_id: item.distributor_id || tradeInForm.value.distributor_id,
                notes: item.notes || '',
            });
        }

        // Remove single-item fields and send items[] instead
        formData.delete('brand_id');
        formData.delete('product_type_id');
        formData.delete('storage');
        formData.delete('condition');
        formData.delete('buy_price');
        // Remove imeis[] that were appended above
        formData.delete('imeis[]');
        formData.delete('quantity');

        formData.append('items', JSON.stringify(allItems));
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
                qty: batchCount,
                distributor_name: data.distributor?.name || 'KOSONG'
            }],
            original_price: data.buy_price * batchCount,
            grand_total: data.buy_price * batchCount,
            total: data.buy_price * batchCount,
            paid: data.buy_price * batchCount,
            cash: splitPayments.value.filter(p => props.availablePaymentMethods.find(m => m.id === p.method_id)?.category?.toLowerCase() === 'cash').reduce((sum, p) => sum + p.amount, 0),
            transfer: splitPayments.value.filter(p => props.availablePaymentMethods.find(m => m.id === p.method_id)?.category?.toLowerCase() === 'transfer').reduce((sum, p) => sum + p.amount, 0),
            payment_method_name: props.availablePaymentMethods.find(m => m.id === splitPayments.value[0]?.method_id)?.name,
            split_payments_data: splitPayments.value.map(p => ({
                method_name: props.availablePaymentMethods.find(m => m.id === p.method_id)?.name || 'Unknown',
                amount: p.amount
            })),
            category: 'angkat_barang',
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
                data.photo_customer ? `${authStore.storageBaseUrl}/storage/${data.photo_customer}` : null
            ].filter(Boolean)
        };

        emit("transaction-complete", transaction);

        // Reset form
        additionalItems.value = [];
        tradeInForm.value = {
            customer_name: "",
            customer_phone: "",
            source: "luar_pstore",
            distributor_id: null,
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
        splitPayments.value = [
            {
                method_id: props.availablePaymentMethods[0]?.id || null,
                amount: 0
            }
        ];
        tradeInPhotos.value = { unit: null, unitPreview: null, customer: null, customerPreview: null };
        displayBuyPrice.value = "0";

    } catch (error) {
        console.error("Trade-in failed", error);
        let msg = "Gagal memproses barang angkat";
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
                        <h3 class="text-lg sm:text-2xl font-black text-text-primary uppercase tracking-tight leading-none">Angkat Barang</h3>
                        <p class="text-[10px] font-bold text-text-secondary uppercase tracking-widest mt-1">Beli Barang dari Customer</p>
                    </div>
                </div>
                <div
                    class="hidden xs:block px-4 py-1.5 bg-primary-100 dark:bg-primary-900/30 text-primary-600 rounded-full text-[10px] font-black uppercase tracking-widest">
                    Buy-In
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
                            Barang <span class="text-red-500">*</span></label>
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
                        Data Unit Angkat Barang</h4>
                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <label
                                class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Distributor
                                <span class="text-red-500">*</span></label>
                            <select v-model="tradeInForm.distributor_id"
                                class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none">
                                <option :value="null">Semua Distributor</option>
                                <option v-for="d in distributors" :key="d.id" :value="d.id">{{ d.name }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label
                                class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Brand
                                <span class="text-red-500">*</span></label>
                            <select v-model="tradeInForm.brand_id"
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
                    <div v-if="isImeiTradeIn" class="grid grid-cols-1 xs:grid-cols-2 gap-4">
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

            <!-- Additional Items Section (DISABLED - uncomment when ready)
            <div v-if="additionalItems.length > 0" class="space-y-4 mt-8">
                ...
            </div>
            <div class="mt-6">
                <button @click="addItem" type="button">Tambah Item Lain</button>
            </div>
            -->

            <!-- Financial & Media -->
            <div class="space-y-6 mt-8">
                <h4
                    class="text-sm font-black text-primary-600 uppercase tracking-widest border-b border-primary-100 dark:border-primary-900/30 pb-2">
                    Pembayaran & Bukti</h4>
                <div>
                    <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Harga
                        Angkat (Item Utama) <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span
                            class="absolute left-4 top-1/2 -translate-y-1/2 text-sm font-bold text-text-secondary">Rp</span>
                        <input v-money:buy_price="tradeInForm" type="text"
                            class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl pl-10 pr-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none font-black text-lg text-primary-600" />
                    </div>
                </div>
                <!-- Total All Items -->
                <div v-if="additionalItems.length > 0" class="p-4 bg-primary-50 dark:bg-primary-900/20 rounded-xl border border-primary-100 dark:border-primary-800">
                    <div class="flex justify-between items-center">
                        <span class="text-xs font-bold text-text-secondary uppercase tracking-widest">Total Semua Item</span>
                        <span class="text-xl font-black text-primary-600">{{ formatCurrency(totalAllItemsPrice) }}</span>
                    </div>
                    <p class="text-[10px] text-text-secondary mt-1">Nominal split pembayaran harus sama dengan total ini</p>
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
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label
                            class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2 text-center">Foto
                            Unit <span class="text-red-500">*</span></label>
                        <div @click="$refs.unitAngkatInput.click()"
                            class="relative border-2 border-dashed border-surface-300 dark:border-surface-600 rounded-2xl aspect-square flex flex-col items-center justify-center cursor-pointer hover:bg-surface-50 dark:hover:bg-surface-800 transition-all overflow-hidden group">
                            <template v-if="isCompressing">
                                <Loader2 class="w-8 h-8 text-primary-600 animate-spin" />
                                <span class="text-[10px] font-black text-text-secondary uppercase mt-2">Memproses...</span>
                            </template>
                            <template v-else-if="tradeInPhotos.unitPreview">
                                <img :src="tradeInPhotos.unitPreview" class="w-full h-full object-cover" />
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
                        <input type="file" ref="unitAngkatInput" @change="e => handlePhotoChange('unit', e)"
                            accept="image/*" class="hidden" capture="environment" />
                    </div>
                    <div>
                        <label
                            class="block text-xs font-black text-text-secondary uppercase tracking-widest mb-4 text-center">Foto
                            Customer <span class="text-red-500">*</span></label>
                        <div @click="$refs.customerAngkatInput.click()"
                            class="relative border-2 border-dashed border-surface-300 dark:border-surface-600 rounded-2xl aspect-square flex flex-col items-center justify-center cursor-pointer hover:bg-surface-50 dark:hover:bg-surface-800 transition-all overflow-hidden group">
                            <template v-if="isCompressing">
                                <Loader2 class="w-8 h-8 text-primary-600 animate-spin" />
                                <span class="text-[10px] font-black text-text-secondary uppercase mt-2">Memproses...</span>
                            </template>
                            <template v-else-if="tradeInPhotos.customerPreview">
                                <img :src="tradeInPhotos.customerPreview" class="w-full h-full object-cover" />
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
                        <input type="file" ref="customerAngkatInput"
                            @change="e => handlePhotoChange('customer', e)" accept="image/*" class="hidden"
                            capture="environment" />
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
            <button @click="submitTradeIn()" :disabled="isSubmitting || isSplitInvalid"
                class="flex-[2] py-4 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 disabled:bg-surface-300 dark:disabled:bg-surface-600 disabled:cursor-not-allowed text-white rounded-2xl font-black uppercase tracking-widest shadow-xl shadow-emerald-500/20 transition-all flex items-center justify-center gap-3">
                <Loader2 v-if="isSubmitting" class="animate-spin" :size="24" />
                <template v-else>
                    <Save :size="24" /> Selesaikan & Simpan ke Inventory
                </template>
            </button>
        </div>
    </div>
</template>
