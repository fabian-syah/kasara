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
const unitExchangePhotos = ref({
    unit: null,
    unitPreview: null,
    customer: null,
    customerPreview: null
});

const splitPayments = ref([]);

const isSplitInvalid = computed(() => {
    const totalSplit = splitPayments.value.reduce((sum, p) => sum + (p.amount || 0), 0);
    return totalSplit !== 0;
});

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


const unitExchangeForm = ref({
    customer_name: "",
    customer_phone: "",
    incoming_source: "luar_pstore",
    distributor_id: null,
    incoming_brand_id: null,
    incoming_product_type_id: null,
    incoming_storage: "",
    incoming_condition: "",
    incoming_imei: "",
    incoming_quantity: 1,
    incoming_cost_price: 0,
    outgoing_product_detail_id: null,
    outgoing_quantity: 1,
    outgoing_price: 0,
    reason: "",
    notes: "",
});

// Multi-item support: additional incoming items beyond the first one
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
        distributor_id: unitExchangeForm.value.distributor_id,
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
    return `temp_tukar_unit_form_${userId}${acc}`;
});

watch([unitExchangeForm, stockSearchQuery, unitExchangePhotos], ([newForm, newQuery, newPhotos]) => {
    if (isRestoring.value) return;
    
    // We only persist the previews (Base64), not the File objects
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
            Object.assign(unitExchangeForm.value, data.form);
            stockSearchQuery.value = data.query || "";
            
            if (data.photos) {
                unitExchangePhotos.value.unitPreview = data.photos.unitPreview;
                unitExchangePhotos.value.customerPreview = data.photos.customerPreview;
                
                // Convert back to File if possible for submission
                if (data.photos.unitPreview && data.photos.unitPreview.startsWith('data:')) {
                    try {
                        unitExchangePhotos.value.unit = dataURLtoFile(data.photos.unitPreview, 'unit_restored.jpg');
                    } catch (e) {}
                }
                if (data.photos.customerPreview && data.photos.customerPreview.startsWith('data:')) {
                    try {
                        unitExchangePhotos.value.customer = dataURLtoFile(data.photos.customerPreview, 'customer_restored.jpg');
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
    if (!unitExchangeForm.value.distributor_id) return props.brands;
    const dist = props.distributors.find(d => d.id === unitExchangeForm.value.distributor_id);
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

const filteredInventoryProducts = computed(() => {
    // Priority search query: if user is typing in incoming price, use that, otherwise use stockSearchQuery
    const q = stockSearchQuery.value.toLowerCase().trim();
    const incomingQ = unitExchangeForm.value.incoming_cost_price?.toString() || '';
    
    const allProducts = inventoryStore.products.filter(p => (p.imei || p.stock > 0) && p.status !== 'sold');
    if (!q && (!incomingQ || incomingQ === '0')) return allProducts;

    const cleanQ = q.replace(/\./g, '');
    const cleanIncoming = incomingQ.replace(/\./g, '');
    
    return allProducts.filter(p => {
        const name = (p.product?.name || p.name || '').toLowerCase();
        const brand = (p.product?.brand || p.brand || '').toLowerCase();
        const imei = (p.imei || '').toLowerCase();
        const cost = parseFloat(p.cost_price || 0);
        const selling = parseFloat(p.selling_price || p.price || 0);
        const itemMaxPrice = Math.max(cost, selling);

        const matchesText = name.includes(q) || brand.includes(q) || imei.includes(q);
        
        // Price filtering logic (Tukar Unit fairness)
        let matchesPriceRange = true;
        const incomingVal = parseFloat(cleanIncoming || 0);
        if (incomingVal > 0) {
            // If incoming is 20M, min allowed is 15M (5M diff). 
            // If incoming is 8M, min is 3M.
            const minAllowed = Math.max(0, incomingVal - 5000000);
            matchesPriceRange = itemMaxPrice >= minAllowed;
        }

        const matchesSearchPrice = (cleanQ && cleanQ.length >= 4 && (cost.toString().startsWith(cleanQ) || selling.toString().startsWith(cleanQ)));

        return (matchesText || matchesSearchPrice) && matchesPriceRange;
    });
});

// Watchers
watch(() => unitExchangeForm.value.distributor_id, (newVal, oldVal) => {
    if (isRestoring.value) return;
    if (newVal !== oldVal) {
        unitExchangeForm.value.incoming_brand_id = null;
        unitExchangeForm.value.incoming_product_type_id = null;
    }
});

watch(() => unitExchangeForm.value.incoming_brand_id, (newVal, oldVal) => {
    if (isRestoring.value) return;
    if (newVal !== oldVal) {
        unitExchangeForm.value.incoming_product_type_id = null;
        unitExchangeForm.value.incoming_storage = "";
        unitExchangeForm.value.incoming_condition = "";
    }
});

watch(() => unitExchangeForm.value.incoming_product_type_id, (newVal, oldVal) => {
    if (isRestoring.value) return;
    if (newVal !== oldVal) {
        unitExchangeForm.value.incoming_storage = "";
        unitExchangeForm.value.incoming_condition = "";
    }
});

watch(() => isImeiExchange.value, (newVal) => {
    if (isRestoring.value) return;
    if (!newVal) {
        unitExchangeForm.value.incoming_storage = "Non-HP";
        unitExchangeForm.value.incoming_condition = "second";
    }
}, { immediate: true });

watch(() => unitExchangeForm.value.outgoing_product_detail_id, (newId) => {
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
        unitExchangeForm.value.outgoing_price = 0;
        unitExchangeForm.value.incoming_cost_price = 0;
    }
});

watch(() => unitExchangeForm.value.incoming_cost_price, (newVal) => {
    // No longer syncing with outgoing_price, just allow independent input
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
            unitExchangePhotos.value[type] = compressedFile;

            // 3. Set Preview
            const reader = new FileReader();
            reader.onload = (e) => {
                unitExchangePhotos.value[`${type}Preview`] = e.target.result;
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
    unitExchangeForm.value.outgoing_product_detail_id = item.id;

    // Default to inventory price (selling price if available, else cost price)
    const selling = parseFloat(item.selling_price || item.price || 0);
    const cost = parseFloat(item.cost_price || 0);
    const inventoryPrice = selling > 0 ? selling : (cost > 0 ? cost : 0);
    
    unitExchangeForm.value.outgoing_price = inventoryPrice;
    // No longer forcing incoming price to match outgoing price automatically
    // unitExchangeForm.value.incoming_cost_price = inventoryPrice;

    stockSearchQuery.value = `[${item.product?.brand || '-'}] ${item.product?.name || item.name} - ${item.imei || 'Non-IMEI'}`;
    showStockDropdown.value = false;
}

function closeStockDropdown() {
    setTimeout(() => {
        showStockDropdown.value = false;
    }, 200);
}

async function submitUnitExchange(pin = null) {
    if (
        isImeiExchange.value && 
        selectedOutgoingItem.value?.imei && 
        unitExchangeForm.value.incoming_imei &&
        selectedOutgoingItem.value.imei.toLowerCase().trim() === unitExchangeForm.value.incoming_imei.toLowerCase().trim()
    ) {
        alert("Gagal diproses: IMEI Unit Masuk tidak boleh sama dengan IMEI Unit Keluar.");
        return;
    }

    if (!unitExchangeForm.value.customer_name || !unitExchangeForm.value.customer_phone || !unitExchangeForm.value.incoming_brand_id || !unitExchangeForm.value.incoming_product_type_id || !unitExchangeForm.value.incoming_storage || !unitExchangeForm.value.incoming_condition || !unitExchangeForm.value.incoming_cost_price || !unitExchangeForm.value.reason || !unitExchangeForm.value.outgoing_product_detail_id || !unitExchangeForm.value.outgoing_price || !unitExchangeForm.value.distributor_id) {
        alert("Mohon lengkapi semua data wajib (Customer, Distributor, Unit Masuk, Unit Keluar, Harga Jual, Alasan).");
        return;
    }

    const totalSplit = splitPayments.value.reduce((sum, p) => sum + (p.amount || 0), 0);
    if (totalSplit !== 0) {
        alert(`Total split pembayaran (${formatCurrency(totalSplit)}) harus senilai Rp 0 karena transaksi Tukar Unit seimbang.`);
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
    if (props.salesAccount) formData.append('sales_account', props.salesAccount);
    formData.append('customer_name', unitExchangeForm.value.customer_name);
    formData.append('customer_phone', unitExchangeForm.value.customer_phone);
    if (unitExchangeForm.value.distributor_id) {
        formData.append('distributor_id', unitExchangeForm.value.distributor_id);
        const selectedDist = props.distributors.find(d => d.id === unitExchangeForm.value.distributor_id);
        if (selectedDist) formData.append('distributor_name', selectedDist.name);
    }
    formData.append('incoming_source', unitExchangeForm.value.incoming_source);
    formData.append('incoming_product_type_id', unitExchangeForm.value.incoming_product_type_id);
    formData.append('incoming_storage', unitExchangeForm.value.incoming_storage);
    formData.append('incoming_condition', unitExchangeForm.value.incoming_condition);
    formData.append('incoming_imei', unitExchangeForm.value.incoming_imei);
    formData.append('incoming_quantity', unitExchangeForm.value.incoming_quantity);
    formData.append('incoming_cost_price', unitExchangeForm.value.incoming_cost_price);
    formData.append('outgoing_product_detail_id', unitExchangeForm.value.outgoing_product_detail_id);
    formData.append('outgoing_quantity', unitExchangeForm.value.outgoing_quantity);
    formData.append('outgoing_price', unitExchangeForm.value.outgoing_price);
    formData.append('payment_method_id', splitPayments.value[0]?.method_id || 1);
    formData.append('split_payments', JSON.stringify(splitPayments.value.map(p => ({
        payment_method_id: p.method_id,
        amount: p.amount
    }))));
    formData.append('reason', unitExchangeForm.value.reason);
    formData.append('notes', unitExchangeForm.value.notes);

    // Multi-item support: if there are additional items, send as incoming_items[] array
    if (additionalItems.value.length > 0) {
        const allIncomingItems = [];
        
        // Main incoming item
        allIncomingItems.push({
            brand_id: unitExchangeForm.value.incoming_brand_id,
            product_type_id: unitExchangeForm.value.incoming_product_type_id,
            imeis: isImeiExchange.value ? [unitExchangeForm.value.incoming_imei].filter(i => i) : [],
            quantity: isImeiExchange.value ? 1 : (unitExchangeForm.value.incoming_quantity || 1),
            storage: unitExchangeForm.value.incoming_storage,
            condition: unitExchangeForm.value.incoming_condition,
            buy_price: unitExchangeForm.value.incoming_cost_price,
            distributor_id: unitExchangeForm.value.distributor_id,
        });

        // Additional items
        for (const item of additionalItems.value) {
            const itemIsImei = isItemImei(item);
            const itemImeis = itemIsImei 
                ? (item.imeis_raw || '').split(/[\n,]/).map(i => i.trim()).filter(i => i !== "")
                : [];
            allIncomingItems.push({
                brand_id: item.brand_id,
                product_type_id: item.product_type_id,
                imeis: itemImeis,
                quantity: itemIsImei ? Math.max(1, itemImeis.length) : (item.quantity || 1),
                storage: item.storage,
                condition: item.condition,
                buy_price: item.buy_price,
                distributor_id: item.distributor_id || unitExchangeForm.value.distributor_id,
            });
        }

        formData.append('incoming_items', JSON.stringify(allIncomingItems));
    }

    try {
        const response = await api.post('/unit-exchanges', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });

        const data = response.data.data;
        const transaction = {
            id: data.id,
            order_no: data.receipt_id,
            items: [
                {
                    name: `OUT: ${data.outgoing_product_detail?.product?.name || data.outgoing_product_detail?.name || 'Unit Keluar'}`,
                    imei: data.outgoing_product_detail?.imei || '-',
                    price: data.outgoing_price,
                    qty: data.outgoing_quantity || 1,
                    condition: data.outgoing_product_detail?.condition || 'second',
                    storage: data.outgoing_product_detail?.storage,
                    is_hp: !!data.outgoing_product_detail?.imei
                },
                {
                    name: `IN: ${data.incoming_product_type?.name || 'Unit Masuk'}`,
                    imei: data.incoming_imei || '-',
                    price: -data.outgoing_price, // Negative to balance out
                    qty: data.incoming_quantity || 1,
                    condition: data.incoming_condition,
                    storage: data.incoming_storage,
                    is_hp: true
                }
            ],
            original_price: data.outgoing_price,
            grand_total: 0, // balanced
            total: 0,
            paid: 0,
            cash: 0,
            transfer: 0,
            payment_method_name: props.availablePaymentMethods.find(m => m.id === splitPayments.value[0]?.method_id)?.name,
            split_payments_data: splitPayments.value.map(p => ({
                method_name: props.availablePaymentMethods.find(m => m.id === p.method_id)?.name || 'Unknown',
                amount: p.amount
            })),
            category: 'tukar_unit',
            customer_name: data.customer_name,
            customer_phone: data.customer_phone,
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
        unitExchangeForm.value = {
            customer_name: "",
            customer_phone: "",
            distributor_id: null,
            incoming_source: "luar_pstore",
            incoming_brand_id: null,
            incoming_product_type_id: null,
            incoming_storage: "",
            incoming_condition: "",
            incoming_imei: "",
            incoming_quantity: 1,
            incoming_cost_price: 0,
            outgoing_product_detail_id: null,
            outgoing_quantity: 1,
            outgoing_price: 0,
            reason: "",
            notes: "",
        };
        splitPayments.value = [
            {
                method_id: props.availablePaymentMethods[0]?.id || null,
                amount: 0
            }
        ];
        unitExchangePhotos.value = { unit: null, unitPreview: null, customer: null, customerPreview: null };

    } catch (error) {
        console.error("Unit exchange failed", error);
        let msg = "Gagal memproses tukar unit";
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
                        <h3 class="text-lg sm:text-2xl font-black text-text-primary uppercase tracking-tight leading-none">Tukar Unit</h3>
                        <p class="text-[10px] font-bold text-text-secondary uppercase tracking-widest mt-1">Ganti Unit Bermasalah</p>
                    </div>
                </div>
                <div
                    class="hidden xs:block px-4 py-1.5 bg-amber-100 dark:bg-amber-900/30 text-amber-600 rounded-full text-[10px] font-black uppercase tracking-widest">
                    Exchange
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
                    <div>
                        <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Pilih Distributor <span class="text-red-500">*</span></label>
                        <select v-model="unitExchangeForm.distributor_id"
                            class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none font-bold text-primary-600">
                            <option :value="null">-- Pilih Distributor --</option>
                            <option v-for="d in distributors" :key="d.id" :value="d.id">{{ d.name }}</option>
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
                                <option v-for="b in filteredBrands" :key="b.id" :value="b.id">{{ b.name }}</option>
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
                    <div v-if="isImeiExchange" class="grid grid-cols-2 gap-4">
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
                        <input v-model="unitExchangeForm.incoming_imei" type="text" placeholder="15 digit IMEI..."
                            class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none" />
                    </div>
                    <div v-else>
                        <label
                            class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">JUMLAH UNIT <span class="text-red-500">*</span></label>
                        <div class="flex items-center gap-4">
                            <input v-model.number="unitExchangeForm.incoming_quantity" type="number" min="1"
                                class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none" />
                            <span class="text-xs font-bold text-text-secondary uppercase">Unit</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Harga
                            Tukar Unit / Barang Masuk (Per Unit) <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <span
                                class="absolute left-4 top-1/2 -translate-y-1/2 text-sm font-bold text-text-secondary">Rp</span>
                            <input v-money:incoming_cost_price="unitExchangeForm" type="text"
                                class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl pl-10 pr-4 py-3 bg-white dark:bg-surface-900 focus:border-primary-500 transition-all outline-none font-black text-lg text-primary-600" />
                        </div>
                        <p class="mt-1 text-[10px] text-text-secondary font-medium italic">*Otomatis jadi harga modal</p>
                    </div>
                </div>

                <!-- 3. BARANG KELUAR -->
                <div class="space-y-6">
                    <h4
                        class="text-sm font-black text-amber-600 uppercase tracking-widest border-b border-amber-100 dark:border-amber-900/30 pb-2">
                        [2] Barang Keluar (Pilih Stok Toko)
                    </h4>
                    <div>
                        <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">CARI
                            &
                            PILIH UNIT KELUAR <span class="text-red-500">*</span></label>
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
                        <p v-if="selectedOutgoingItem"
                            class="mt-3 p-4 bg-primary-50 dark:bg-primary-900/10 rounded-xl border border-primary-100 dark:border-primary-800 text-xs font-semibold text-primary-700 dark:text-primary-400">
                            Unit Terpilih: {{ selectedOutgoingItem.product?.name ||
                                selectedOutgoingItem.name }} ({{
                                selectedOutgoingItem.imei || 'Non-IMEI' }})
                            <br/>
                            Harga Jual (Sistem): Rp {{ formatNumber(selectedOutgoingItem.selling_price) }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">ISI
                            HARGA
                            BARANG KELUAR <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <span
                                class="absolute left-4 top-1/2 -translate-y-1/2 text-sm font-bold text-text-secondary">Rp</span>
                            <input v-money:outgoing_price="unitExchangeForm" type="text"
                                :placeholder="'Contoh: ' + formatNumber(suggestedOutgoingPrice)"
                                class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl pl-10 pr-4 py-3 bg-white dark:bg-surface-900 focus:border-primary-500 transition-all outline-none font-black text-lg text-primary-600" />
                        </div>
                    </div>

                    <div v-if="selectedOutgoingItem && !selectedOutgoingItem.imei">
                        <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">JUMLAH KELUAR <span class="text-red-500">*</span></label>
                        <div class="flex items-center gap-4">
                            <input v-model.number="unitExchangeForm.outgoing_quantity" type="number" min="1" :max="selectedOutgoingItem.stock || selectedOutgoingItem.quantity"
                                class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none" />
                            <span class="text-xs font-bold text-text-secondary uppercase">Unit (Maks: {{ selectedOutgoingItem.stock || selectedOutgoingItem.quantity }})</span>
                        </div>
                    </div>

                    <!-- Photo and Additional Media -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label
                                class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2 text-center">Foto
                                Unit <span class="text-red-500">*</span></label>
                            <div @click="$refs.unitExchangeInput.click()"
                                class="relative border-2 border-dashed border-surface-300 dark:border-surface-600 rounded-xl aspect-square flex flex-col items-center justify-center cursor-pointer hover:bg-surface-50 dark:hover:bg-surface-800 transition-all overflow-hidden group">
                                <template v-if="isCompressing">
                                    <Loader2 class="w-8 h-8 text-primary-600 animate-spin" />
                                    <span class="text-[10px] font-black text-text-secondary uppercase mt-2">Memproses...</span>
                                </template>
                                <template v-else-if="unitExchangePhotos.unitPreview">
                                    <img :src="unitExchangePhotos.unitPreview" class="w-full h-full object-cover" />
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
                            <input type="file" ref="unitExchangeInput"
                                @change="e => handlePhotoChange('unit', e)" accept="image/*" class="hidden"
                                capture="environment" />
                        </div>
                        <div>
                            <label
                                class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2 text-center">Foto
                                Customer</label>
                            <div @click="$refs.customerExchangeInput.click()"
                                class="relative border-2 border-dashed border-surface-300 dark:border-surface-600 rounded-xl aspect-square flex flex-col items-center justify-center cursor-pointer hover:bg-surface-50 dark:hover:bg-surface-800 transition-all overflow-hidden group">
                                <template v-if="isCompressing">
                                    <Loader2 class="w-8 h-8 text-primary-600 animate-spin" />
                                    <span class="text-[10px] font-black text-text-secondary uppercase mt-2">Memproses...</span>
                                </template>
                                <template v-else-if="unitExchangePhotos.customerPreview">
                                    <img :src="unitExchangePhotos.customerPreview" class="w-full h-full object-cover" />
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
                            <input type="file" ref="customerExchangeInput"
                                @change="e => handlePhotoChange('customer', e)" accept="image/*" class="hidden"
                                capture="environment" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Additional Items Section (DISABLED - uncomment when ready)
            <div class="mt-8 space-y-4">
                <div v-if="additionalItems.length > 0" class="space-y-4 mb-6">
                    <div v-for="(item, idx) in additionalItems" :key="idx"
                        class="p-4 bg-surface-50 dark:bg-surface-900 rounded-xl border border-surface-200 dark:border-surface-700 relative">
                        <button @click="removeItem(idx)" type="button"
                            class="absolute top-2 right-2 text-gray-400 hover:text-red-500 transition-colors">
                            <X :size="16" />
                        </button>
                        <p class="text-[10px] font-black text-primary-500 uppercase tracking-widest mb-3">Item Tambahan #{{ idx + 1 }}</p>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] font-bold text-text-secondary uppercase mb-1">Brand</label>
                                <select v-model="item.brand_id"
                                    class="w-full border border-surface-200 dark:border-surface-700 rounded-lg px-3 py-2 bg-white dark:bg-surface-800 text-xs focus:border-primary-500 outline-none">
                                    <option :value="null" disabled>Pilih</option>
                                    <option v-for="b in filteredBrands" :key="b.id" :value="b.id">{{ b.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-text-secondary uppercase mb-1">Tipe</label>
                                <select v-model="item.product_type_id" :disabled="!item.brand_id"
                                    class="w-full border border-surface-200 dark:border-surface-700 rounded-lg px-3 py-2 bg-white dark:bg-surface-800 text-xs focus:border-primary-500 outline-none">
                                    <option :value="null" disabled>Pilih</option>
                                    <option v-for="t in getFilteredTypesForItem(item)" :key="t.id" :value="t.id">{{ t.name }}</option>
                                </select>
                            </div>
                            <div v-if="isItemImei(item)">
                                <label class="block text-[10px] font-bold text-text-secondary uppercase mb-1">Kapasitas</label>
                                <select v-model="item.storage" :disabled="!item.product_type_id"
                                    class="w-full border border-surface-200 dark:border-surface-700 rounded-lg px-3 py-2 bg-white dark:bg-surface-800 text-xs focus:border-primary-500 outline-none">
                                    <option value="" disabled>Pilih</option>
                                    <option v-for="s in getCapacitiesForItem(item)" :key="s" :value="s">{{ s }}</option>
                                </select>
                            </div>
                            <div v-if="isItemImei(item)">
                                <label class="block text-[10px] font-bold text-text-secondary uppercase mb-1">Kondisi</label>
                                <select v-model="item.condition"
                                    class="w-full border border-surface-200 dark:border-surface-700 rounded-lg px-3 py-2 bg-white dark:bg-surface-800 text-xs focus:border-primary-500 outline-none">
                                    <option value="" disabled>Pilih</option>
                                    <option value="new">New</option>
                                    <option value="second">Second</option>
                                    <option value="ex_ibox">Ex iBox</option>
                                </select>
                            </div>
                        </div>
                        <div v-if="isItemImei(item)" class="mt-3">
                            <label class="block text-[10px] font-bold text-text-secondary uppercase mb-1">IMEI</label>
                            <textarea v-model="item.imeis_raw" rows="2" placeholder="IMEI..."
                                class="w-full border border-surface-200 dark:border-surface-700 rounded-lg px-3 py-2 bg-white dark:bg-surface-800 text-xs font-mono focus:border-primary-500 outline-none"></textarea>
                        </div>
                        <div v-else class="mt-3">
                            <label class="block text-[10px] font-bold text-text-secondary uppercase mb-1">Quantity</label>
                            <input v-model.number="item.quantity" type="number" min="1"
                                class="w-24 border border-surface-200 dark:border-surface-700 rounded-lg px-3 py-2 bg-white dark:bg-surface-800 text-xs focus:border-primary-500 outline-none" />
                        </div>
                        <div class="mt-3">
                            <label class="block text-[10px] font-bold text-text-secondary uppercase mb-1">Harga Unit Masuk</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-text-secondary">Rp</span>
                                <input v-money:buy_price="item" type="text"
                                    class="w-full border border-surface-200 dark:border-surface-700 rounded-lg pl-8 pr-3 py-2 bg-white dark:bg-surface-800 text-xs font-bold text-primary-600 focus:border-primary-500 outline-none" />
                            </div>
                        </div>
                    </div>
                </div>

                <button @click="addItem" type="button"
                    class="w-full py-3 border-2 border-dashed border-primary-300 dark:border-primary-700 rounded-xl text-primary-600 font-bold text-xs uppercase tracking-widest hover:bg-primary-50 dark:hover:bg-primary-900/20 transition-all flex items-center justify-center gap-2">
                    <Plus :size="16" stroke-width="3" /> Tambah Item Lain
                </button>
            </div>
            -->

            <!-- 4. ALASAN & CATATAN & PEMBAYARAN -->
            <div class="mt-8 space-y-6">
                <h4
                    class="text-sm font-black text-primary-600 uppercase tracking-widest border-b border-primary-100 dark:border-primary-900/30 pb-2">
                    Alasan, Catatan & Pembayaran
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
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
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest">Metode Pembayaran</label>
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
            </div>

            <!-- Submit Section -->
            <div class="mt-12 pt-8 border-t border-surface-100 dark:border-surface-700 flex flex-col sm:flex-row gap-4">
                <button @click="emit('back')"
                    class="flex-1 py-4 bg-surface-100 dark:bg-surface-700 text-text-primary rounded-2xl font-black uppercase tracking-widest hover:bg-surface-200 transition-all">
                    Kembali Pilih Kategori
                </button>
                <button @click="submitUnitExchange()" :disabled="isSubmitting || isSplitInvalid"
                    class="flex-[2] py-4 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 disabled:bg-surface-300 dark:disabled:bg-surface-600 disabled:cursor-not-allowed text-white rounded-2xl font-black uppercase tracking-widest shadow-xl shadow-emerald-500/20 transition-all flex items-center justify-center gap-3">
                    <Loader2 v-if="isSubmitting" class="animate-spin" :size="24" />
                    <template v-else>
                        <Save :size="24" /> Selesaikan Tukar Unit
                    </template>
                </button>
            </div>
        </div>
    </div>
</template>
