<script setup>
import { ref, computed, onMounted, watch } from "vue";
import api from "../../../api/axios";
import { formatCurrency } from "../../../utils/formatters";
import { ArrowLeft, CheckCircle, Search, Loader2, ArrowRight, User, ShoppingCart, Trash2, Camera, X, Save, AlertCircle, Plus, ChevronDown } from "lucide-vue-next";
import { useAuthStore } from "../../../store/auth";
import { useInventoryStore } from "../../../store/inventory";
import { compressImage } from "../../../utils/imageCompressor";

const props = defineProps({
    transactionCategory: String,
    availablePaymentMethods: Array,
    salesAccount: String,
    selectedAccountObject: Object
});

const emit = defineEmits(["back", "transaction-complete", "verify-pin"]);
const authStore = useAuthStore();
const inventoryStore = useInventoryStore();

const activeDps = ref([]);
const isLoadingDps = ref(false);
const searchQuery = ref("");
const selectedDp = ref(null);
const currentStep = ref(1); // 1 = Select DP, 2 = Form Pelunasan

const fetchActiveDps = async () => {
    isLoadingDps.value = true;
    try {
        const response = await api.get('/stock-outs/active-dps');
        activeDps.value = response.data.data || response.data || [];
    } catch (error) {
        console.error("Gagal mengambil data DP aktif:", error);
    } finally {
        isLoadingDps.value = false;
    }
};

onMounted(() => {
    fetchActiveDps();
});

const stockSearchQuery = ref("");
const showStockDropdown = ref(false);
const outgoingItem = ref(null);

const isSubmitting = ref(false);
const isCompressing = ref(false);
const isCompressingPayment = ref(false);

const incomingItem = ref({ incoming_source: 'luar_pstore', distributor_id: null, brand_id: null, product_type_id: null, storage: '', condition: 'second', imei: '', quantity: 1, cost_price: 0 });

function getFilteredBrandsForItem(item) { 
    const defaultBrands = Array.isArray(props.brands) ? props.brands : []; 
    if (!item?.distributor_id) return defaultBrands; 
    const dist = (Array.isArray(props.distributors) ? props.distributors : []).find(d => d && d.id == item.distributor_id); 
    if (!dist || !dist.allowed_brands) return defaultBrands; 
    try { 
        const allowedIds = typeof dist.allowed_brands === 'string' ? JSON.parse(dist.allowed_brands) : dist.allowed_brands; 
        if (!Array.isArray(allowedIds)) return defaultBrands; 
        const numericIds = allowedIds.map(id => Number(id)); 
        return defaultBrands.filter(b => b && numericIds.includes(Number(b.id))); 
    } catch { 
        return defaultBrands; 
    } 
}

function getFilteredTypesForItem(item) { 
    if (!item?.brand_id || !Array.isArray(props.productTypes)) return []; 
    return props.productTypes.filter(t => t && t.brand_id == item.brand_id); 
}

function getCapacitiesForItem(item) { 
    if (!item?.product_type_id || !Array.isArray(props.productTypes)) return []; 
    const set = new Set(); 
    const type = props.productTypes.find(t => t && t.id == item.product_type_id); 
    if (type?.storage) { 
        type.storage.split(/[,]/).forEach(s => { 
            const clean = s.trim(); 
            if (clean) set.add(clean); 
        }); 
    } 
    const prices = (Array.isArray(props.productPrices) ? props.productPrices : []).filter(p => p && p.product_type_id == item.product_type_id); 
    prices.forEach(p => { 
        if (p?.storage) set.add(p.storage); 
    }); 
    return Array.from(set).sort(); 
}

function autoFillIncomingItemFromDp(dp) {
    if (!dp) return;
    
    let firstItem = null;
    if (dp.items && Array.isArray(dp.items) && dp.items.length > 0) {
        firstItem = dp.items[0];
    } else if (dp.nonHpDetails && Array.isArray(dp.nonHpDetails) && dp.nonHpDetails.length > 0) {
        firstItem = dp.nonHpDetails[0];
    } else if (dp.non_hp_details && Array.isArray(dp.non_hp_details) && dp.non_hp_details.length > 0) {
        firstItem = dp.non_hp_details[0];
    }
    
    if (!firstItem) return;

    // Cost price: remaining balance or DP amount or item selling price
    const remainingBalance = (Number(dp.total_price || dp.grand_total || 0) - Number(dp.dp_amount || 0));
    incomingItem.value.cost_price = remainingBalance > 0 ? remainingBalance : (firstItem.selling_price || dp.dp_amount || 0);

    // Storage
    const storageStr = (firstItem.storage || firstItem.gb || "").toString().trim();
    if (storageStr) {
        incomingItem.value.storage = storageStr;
    }

    // Determine Brand ID & Product Type ID
    let brandId = null;
    let typeId = null;

    // Direct object relation
    if (firstItem.product) {
        brandId = firstItem.product.brand_id || firstItem.product.brand?.id || null;
        typeId = firstItem.product.id || null;
    }
    
    // Direct product_id match
    if (!typeId && firstItem.product_id && Array.isArray(props.productTypes)) {
        const found = props.productTypes.find(pt => pt && pt.id == firstItem.product_id);
        if (found) {
            typeId = found.id;
            brandId = found.brand_id;
        }
    }

    // Name-based matching for Brand
    const rawBrandName = (firstItem.product?.brand?.name || firstItem.product?.brand || firstItem.brand || firstItem.brand_name || "").toString().trim().toLowerCase();
    
    if (!brandId && rawBrandName && Array.isArray(props.brands)) {
        const cleanRawBrand = rawBrandName.replace(/[™®]/g, '').trim();
        const foundB = props.brands.find(b => {
            if (!b) return false;
            const bName = (b.name || "").toString().trim().toLowerCase().replace(/[™®]/g, '');
            return bName && (bName === cleanRawBrand || cleanRawBrand.includes(bName) || bName.includes(cleanRawBrand));
        });
        if (foundB) {
            brandId = foundB.id;
        }
    }

    // Name-based matching for Product Type
    const rawTypeName = (firstItem.product?.name || firstItem.name || firstItem.type || firstItem.product_name || "").toString().trim().toLowerCase();
    
    if (!typeId && rawTypeName && Array.isArray(props.productTypes)) {
        const cleanRawType = rawTypeName.replace(/[™®]/g, '').trim();
        
        const candidateTypes = brandId 
            ? props.productTypes.filter(pt => pt && pt.brand_id == brandId) 
            : props.productTypes.filter(pt => !!pt);
            
        let foundT = candidateTypes.find(pt => {
            const ptName = (pt.name || "").toString().trim().toLowerCase().replace(/[™®]/g, '');
            return ptName && ptName === cleanRawType;
        });

        if (!foundT) {
            foundT = candidateTypes.find(pt => {
                const ptName = (pt.name || "").toString().trim().toLowerCase().replace(/[™®]/g, '');
                return ptName && (ptName.includes(cleanRawType) || cleanRawType.includes(ptName));
            });
        }

        if (foundT) {
            typeId = foundT.id;
            if (!brandId) brandId = foundT.brand_id;
        }
    }

    if (brandId) incomingItem.value.brand_id = Number(brandId);
    if (typeId) incomingItem.value.product_type_id = Number(typeId);
}

function selectDp(dp) {
    selectedDp.value = dp;
    autoFillIncomingItemFromDp(dp);
}

watch(
    [() => selectedDp.value, () => props.brands, () => props.productTypes],
    ([newDp]) => {
        if (newDp) {
            autoFillIncomingItemFromDp(newDp);
        }
    },
    { immediate: true, deep: true }
);

function getDpItemInfo(dp) {
    let brand = "-";
    let type = "-";
    let gb = "-";
    
    if (dp.items && Array.isArray(dp.items) && dp.items.length > 0) {
        const firstItem = dp.items[0];
        brand = firstItem.product?.brand?.name || firstItem.product?.brand || firstItem.brand || "-";
        type = firstItem.product?.name || firstItem.name || "-";
        gb = firstItem.storage || "-";
    } else if (dp.nonHpDetails && Array.isArray(dp.nonHpDetails) && dp.nonHpDetails.length > 0) {
        const firstItem = dp.nonHpDetails[0];
        type = firstItem.product?.name || firstItem.name || "-";
    } else if (dp.non_hp_details && Array.isArray(dp.non_hp_details) && dp.non_hp_details.length > 0) {
        const firstItem = dp.non_hp_details[0];
        type = firstItem.product?.name || firstItem.name || "-";
    }

    let dpDate = dp.created_at;
    if (dpDate) {
        const d = new Date(dpDate);
        dpDate = d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
    } else {
        dpDate = "-";
    }

    return { brand, type, gb, dpDate };
}

const dpAmount = computed(() => {
    if (!selectedDp.value) return 0;
    return Number(selectedDp.value.dp_amount || 0);
});

function handleProceedToForm() {
    if (!selectedDp.value) return;
    
    // Auto-fill customer data
    customerForm.value.customer_name = selectedDp.value.customer_name || "";
    customerForm.value.customer_phone = selectedDp.value.customer_phone || "";
    customerForm.value.notes = "Pelunasan DP Nota: " + selectedDp.value.receipt_id;
    
    autoFillIncomingItemFromDp(selectedDp.value);
    
    addSplitPayment();
    currentStep.value = 2;
}

// -- FORM PELUNASAN LOGIC --

const filteredInventoryProducts = computed(() => {
    const q = (stockSearchQuery.value || "").toLowerCase().trim();
    const allProducts = (inventoryStore.products || []).filter(p => (p.imei || p.stock > 0 || p.quantity > 0) && p.status !== 'sold');
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

function selectStockItem(item) {
    let price = 0;
    const selling = parseFloat(item.selling_price || item.price || 0);
    const cost = parseFloat(item.cost_price || 0);
    price = selling > 0 ? selling : (cost > 0 ? cost : 0);

    outgoingItem.value = {
        product_detail_id: item.id,
        item: item,
        price: price,
        quantity: 1,
        max_quantity: item.stock || item.quantity || 1
    };

    stockSearchQuery.value = "";
    showStockDropdown.value = false;
    
    recalculateSplitPayments();
}

function removeOutgoingItem() {
    outgoingItem.value = null;
    recalculateSplitPayments();
}

function closeStockDropdown() {
    setTimeout(() => {
        showStockDropdown.value = false;
    }, 200);
}

// Financials
const totalOutgoingPriceComputed = computed(() => {
    if (!outgoingItem.value) return 0;
    return (outgoingItem.value.price || 0) * (outgoingItem.value.quantity || 1);
});

const sisaBayar = computed(() => {
    const totalOut = totalOutgoingPriceComputed.value;
    const diff = totalOut - dpAmount.value - (incomingItem.value.cost_price || 0);
    return Math.max(0, diff); // If DP is more than price, user doesn't pay more. (Should we handle downgrade?)
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

function addSplitPayment() {
    if (splitPayments.value.length === 0) {
        splitPayments.value.push({
            method_id: props.availablePaymentMethods[0]?.id || null,
            amount: sisaBayar.value
        });
    } else {
        splitPayments.value.push({
            method_id: props.availablePaymentMethods[0]?.id || null,
            amount: 0
        });
    }
}

function removeSplitPayment(index) {
    if (splitPayments.value.length > 1) {
        splitPayments.value.splice(index, 1);
    }
}

function recalculateSplitPayments() {
    if (splitPayments.value.length === 1) {
        splitPayments.value[0].amount = sisaBayar.value;
    }
}

watch(sisaBayar, () => {
    recalculateSplitPayments();
});

// Photos
async function handlePhotoChange(type, event) {
    const file = event.target.files[0];
    if (file) {
        try {
            if (type === 'proofImage') isCompressing.value = true;
            if (type === 'customerProofImage') isCompressing.value = true;
            if (type === 'paymentProofImage') isCompressingPayment.value = true;
            
            const compressedFile = await compressImage(file, { maxWidth: 1600, maxHeight: 1600, quality: 0.8 });
            
            const reader = new FileReader();
            reader.onload = (e) => {
                if (type === 'proofImage') {
                    proofImage.value = compressedFile;
                    proofImagePreview.value = e.target.result;
                } else if (type === 'customerProofImage') {
                    customerProofImage.value = compressedFile;
                    customerProofImagePreview.value = e.target.result;
                } else {
                    paymentProofImage.value = compressedFile;
                    paymentProofImagePreview.value = e.target.result;
                }
            };
            reader.readAsDataURL(compressedFile);
        } catch (error) {
            console.error("Compression failed:", error);
            alert("Gagal mengompres gambar. Silakan coba lagi.");
        } finally {
            if (type === 'proofImage' || type === 'customerProofImage') isCompressing.value = false;
            if (type === 'paymentProofImage') isCompressingPayment.value = false;
        }
    }
}

async function handleSubmit(pin = null) {
    if (!outgoingItem.value) {
        alert("Silakan pilih unit/item barang keluar.");
        return;
    }
    
    if (!customerForm.value.customer_name || !customerForm.value.customer_phone) {
        alert("Nama dan No WhatsApp customer wajib diisi.");
        return;
    }

    if (!proofImage.value) {
        alert("Foto unit wajib diupload.");
        return;
    }

    if (!customerProofImage.value) {
        alert("Foto customer wajib diupload.");
        return;
    }

    if (!isCashOnly.value && sisaBayar.value > 0 && !paymentProofImage.value) {
        alert("Foto bukti pembayaran transfer wajib diupload untuk metode non-tunai.");
        return;
    }

    const totalSplit = splitPayments.value.reduce((sum, p) => sum + (p.amount || 0), 0);
    if (totalSplit !== sisaBayar.value) {
        alert(`Total split pembayaran (${formatCurrency(totalSplit)}) tidak sesuai dengan sisa bayar (${formatCurrency(sisaBayar.value)}).`);
        return;
    }

    if (!pin && props.selectedAccountObject) {
        emit('verify-pin', (verifiedPin) => handleSubmit(verifiedPin));
        return;
    }

    isSubmitting.value = true;
    try {
        const formData = new FormData();
        formData.append('category', 'pelunasan_dp');
        formData.append('sales_account', props.salesAccount);
        
        formData.append('parent_dp_id', selectedDp.value.id);
        formData.append('dp_deduction', dpAmount.value);
        
        formData.append('paid_amount', totalSplit);
        formData.append('selling_price', totalOutgoingPriceComputed.value);

        formData.append('customer_name', customerForm.value.customer_name);
        formData.append('customer_wa', customerForm.value.customer_phone);
        formData.append('notes', customerForm.value.notes);

        if (props.selectedAccountObject?.id) {
            formData.append('inventory_user_id', props.selectedAccountObject.id);
        }
        if (pin) formData.append('transaction_pin', pin);

        // Outgoing items
        const item = outgoingItem.value;
        if (item.item?.imei) {
            formData.append('product_detail_ids[]', item.product_detail_id);
            formData.append(`hp_items_meta[${item.product_detail_id}][selling_price]`, Number(item.price || 0));
            formData.append(`hp_items_meta[${item.product_detail_id}][item_discount]`, 0);
            formData.append(`hp_items_meta[${item.product_detail_id}][distributed_discount]`, 0);
        } else {
            formData.append(`non_hp_items[0][product_id]`, item.item?.product_id || item.product_detail_id);
            formData.append(`non_hp_items[0][quantity]`, item.quantity);
            formData.append(`non_hp_items[0][selling_price]`, Number(item.price || 0));
            formData.append(`non_hp_items[0][item_discount]`, 0);
            formData.append(`non_hp_items[0][distributed_discount]`, 0);
        }

        formData.append('global_discount_value', 0);
        formData.append('global_discount_type', 'fixed');
        formData.append('total_discount', 0);

        formData.append('split_payments', JSON.stringify(splitPayments.value.map(p => ({
            payment_method_id: p.method_id,
            amount: p.amount
        }))));

        if (proofImage.value) formData.append('proof_image', proofImage.value);
        if (customerProofImage.value) formData.append('customer_proof_image', customerProofImage.value);
        if (paymentProofImage.value) formData.append('payment_proof_image', paymentProofImage.value);

        const response = await api.post('/stock-outs', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });

        const data = response.data.data || response.data;
        
        let cashAmount = 0;
        let transferAmount = 0;
        splitPayments.value.forEach(p => {
            const method = props.availablePaymentMethods.find(m => m.id === p.method_id);
            if (method) {
                const name = method.name.toLowerCase();
                if (name.includes('cash') || name.includes('tunai')) cashAmount += Number(p.amount || 0);
                else transferAmount += Number(p.amount || 0);
            }
        });

        const transactionData = {
            id: data.id,
            order_no: data.receipt_id || "TRX-" + Date.now(),
            items: [
                {
                    name: item.item?.product?.name || item.item?.name,
                    imei: item.item?.imei || '-',
                    price: item.price,
                    condition: item.item?.condition || 'second',
                    storage: item.item?.storage || '-',
                    qty: item.quantity,
                    is_hp: !!item.item?.imei
                }
            ],
            original_price: totalOutgoingPriceComputed.value,
            total_discount: 0,
            grand_total: totalOutgoingPriceComputed.value,
            total: totalOutgoingPriceComputed.value,
            paid: totalSplit,
            change: totalSplit - sisaBayar.value,
            cash: cashAmount,
            transfer: transferAmount,
            split_payments_data: splitPayments.value.map(p => ({
                method_name: props.availablePaymentMethods.find(m => m.id === p.method_id)?.name || 'Unknown',
                amount: p.amount
            })),
            payment_method_name: props.availablePaymentMethods.find(m => m.id === splitPayments.value[0]?.method_id)?.name || '-',
            category: 'pelunasan_dp',
            customer_name: customerForm.value.customer_name,
            customer_phone: customerForm.value.customer_phone,
            notes: customerForm.value.notes,
            branch_name: props.selectedAccountObject?.branch?.name || authStore.user?.branch?.name || '',
            branch_timezone: props.selectedAccountObject?.branch?.timezone || authStore.user?.branch?.timezone || 'WIB',
            created_at: new Date().toISOString(),
            date: new Date().toLocaleDateString("id-ID", { day: '2-digit', month: 'short', year: 'numeric' }),
            time: new Date().toLocaleTimeString("id-ID", { hour: '2-digit', minute: '2-digit' }),
            sales_name: props.salesAccount,
            inventory_account_name: props.salesAccount,
            proof_images: [
                proofImagePreview.value,
                customerProofImagePreview.value,
                paymentProofImagePreview.value
            ].filter(Boolean),
            parent_dp_id: selectedDp.value.id,
            incoming_source: incomingItem.value.incoming_source,
            distributor_id: incomingItem.value.distributor_id,
            incoming_brand_id: incomingItem.value.brand_id,
            incoming_product_type_id: incomingItem.value.product_type_id,
            incoming_storage: incomingItem.value.storage,
            incoming_condition: incomingItem.value.condition,
            incoming_imei: incomingItem.value.imei,
            incoming_cost_price: incomingItem.value.cost_price,
            incoming_quantity: incomingItem.value.quantity,
            dp_deduction: dpAmount.value,
            original_dp_receipt: selectedDp.value.receipt_id
        };

        emit('transaction-complete', transactionData);

    } catch (error) {
        console.error("Pelunasan failed", error);
        let msg = "Gagal memproses pelunasan DP";
        if (error.response) {
            if (error.response.status === 413) msg = "File terlalu besar.";
            else if (error.response.data?.message) msg = error.response.data.message;
        }
        alert(msg);
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <!-- STEP 1: SELECT DP NOTA -->
    <div v-if="currentStep === 1" class="w-full flex flex-col gap-4 sm:gap-8 items-start relative min-h-0">
        <div class="w-full flex items-center justify-between bg-white dark:bg-surface-800 p-4 rounded-2xl border border-surface-200 dark:border-surface-700 shadow-sm mb-2">
            <div class="flex items-center gap-3">
                <button @click="emit('back')" class="p-2 -ml-2 hover:bg-surface-100 dark:hover:bg-surface-700 rounded-full text-primary-600 transition-colors">
                    <ArrowLeft :size="28" stroke-width="3" />
                </button>
                <div class="flex flex-col">
                    <h3 class="text-lg sm:text-xl font-black text-text-primary uppercase tracking-tight leading-none">Pelunasan DP</h3>
                    <p class="text-[10px] font-bold text-text-secondary uppercase tracking-widest mt-1">Pilih Nota DP Customer</p>
                </div>
            </div>
        </div>

        <div class="w-full bg-white dark:bg-surface-800 rounded-[1.5rem] border border-surface-200 dark:border-surface-700 p-4 sm:p-6 mb-6 shadow-sm flex flex-col gap-4">
            <div class="relative w-full">
                <Search class="absolute left-5 top-1/2 -translate-y-1/2 text-text-secondary" :size="20" />
                <input v-model="searchQuery" type="text" placeholder="Cari Nama / No Nota / No HP..."
                    class="w-full bg-surface-50 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 rounded-2xl pl-12 pr-4 py-3 sm:py-4 text-base sm:text-lg font-medium text-text-primary focus:outline-none focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 transition-all" />
            </div>

            <div v-if="isLoadingDps" class="py-12 flex justify-center items-center">
                <Loader2 class="animate-spin text-primary-500" :size="32" />
            </div>
            <div v-else-if="!filteredDps || filteredDps.length === 0" class="py-12 text-center text-surface-400">
                Belum ada nota DP yang aktif atau ditemukan.
            </div>
            <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div v-for="dp in filteredDps" :key="dp.id" 
                    @click="selectDp(dp)"
                    class="p-4 rounded-xl border-2 cursor-pointer transition-all flex flex-col gap-2 relative overflow-hidden"
                    :class="selectedDp?.id === dp.id ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/10' : 'border-surface-200 dark:border-surface-700 hover:border-primary-300 bg-surface-50 dark:bg-surface-900'">
                    
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="font-black text-text-primary">{{ dp.customer_name }}</p>
                            <p class="text-xs text-text-secondary">{{ dp.customer_phone }}</p>
                        </div>
                        <span class="text-xs font-black bg-amber-500/10 text-amber-600 px-2 py-1 rounded">
                            {{ dp.receipt_id }}
                        </span>
                    </div>

                    <div class="flex flex-col mt-2 pt-2 border-t border-surface-200 dark:border-surface-700 text-xs text-text-secondary space-y-1">
                        <div class="flex justify-between"><span class="font-bold">Tanggal DP:</span> <span class="font-bold text-text-primary">{{ getDpItemInfo(dp).dpDate }}</span></div>
                        <div class="flex justify-between"><span class="font-bold">Brand:</span> <span class="font-bold text-text-primary">{{ getDpItemInfo(dp).brand }}</span></div>
                        <div class="flex justify-between"><span class="font-bold">Tipe:</span> <span class="font-bold text-text-primary">{{ getDpItemInfo(dp).type }}</span></div>
                        <div class="flex justify-between"><span class="font-bold">Penyimpanan (GB):</span> <span class="font-bold text-text-primary">{{ getDpItemInfo(dp).gb }}</span></div>
                    </div>

                    <div class="flex flex-col mt-2 pt-2 border-t border-surface-200 dark:border-surface-700">
                        <div class="flex justify-between text-xs mb-1">
                            <span class="text-text-secondary font-bold">DP Dibayar</span>
                            <span class="font-black text-emerald-600">{{ formatCurrency(dp.dp_amount) }}</span>
                        </div>
                        <div class="flex justify-between text-sm mt-1 pt-1 border-t border-surface-200 dark:border-surface-700">
                            <span class="text-text-secondary font-bold uppercase tracking-widest">Sisa Lunas</span>
                            <span class="font-black text-red-500">{{ formatCurrency(dp.selling_price - dp.dp_amount) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4 flex justify-end">
                <button @click="handleProceedToForm" :disabled="!selectedDp"
                    class="px-8 py-4 bg-primary-600 hover:bg-primary-500 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-2xl font-black text-lg shadow-xl shadow-primary-500/20 transition-all flex items-center gap-2">
                    Lanjut Pelunasan <ArrowRight :size="20" />
                </button>
            </div>
        </div>
    </div>

    <!-- STEP 2: FORM PELUNASAN (Like Tukar Tambah) -->
    <div v-else-if="currentStep === 2" class="flex-1 overflow-y-auto custom-scrollbar bg-white/70 dark:bg-surface-800/70 backdrop-blur-2xl rounded-[2rem] border border-white/50 dark:border-surface-600/30 p-5 pb-24 sm:p-10 sm:pb-10 shadow-2xl relative">
        <div class="max-w-4xl mx-auto">
            <div class="flex items-center justify-between mb-8 gap-4">
                <div class="flex items-center gap-3">
                    <button @click="currentStep = 1" class="p-2 -ml-2 hover:bg-surface-100 dark:hover:bg-surface-700 rounded-full text-primary-600 transition-colors">
                        <ArrowLeft :size="28" stroke-width="3" />
                    </button>
                    <div class="flex flex-col">
                        <h3 class="text-lg sm:text-2xl font-black text-text-primary uppercase tracking-tight leading-none">Form Pelunasan DP</h3>
                        <p class="text-[10px] font-bold text-text-secondary uppercase tracking-widest mt-1">Selesaikan Transaksi Customer</p>
                    </div>
                </div>
                <div class="hidden xs:block px-4 py-1.5 bg-primary-100 dark:bg-primary-900/30 text-primary-600 rounded-full text-[10px] font-black uppercase tracking-widest">
                    PELUNASAN
                </div>
            </div>

            <!-- DATA CUSTOMER -->
            <div class="bg-surface-50 dark:bg-surface-900/50 p-6 rounded-2xl mb-8 border border-surface-100 dark:border-surface-700">
                <h4 class="text-sm font-black text-primary-600 uppercase tracking-widest mb-6 flex items-center gap-2">
                    <User :size="18" /> DATA CUSTOMER
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">NAMA CUSTOMER <span class="text-red-500">*</span></label>
                        <input v-model="customerForm.customer_name" type="text" placeholder="Nama lengkap..." class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none" />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">NO WHATSAPP <span class="text-red-500">*</span></label>
                        <input v-model="customerForm.customer_phone" type="text" placeholder="08xxx..." class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none" />
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <!-- BARANG MASUK (DP DATA) -->
                <div class="space-y-6">
                    <h4 class="text-sm font-black text-emerald-600 uppercase tracking-widest border-b border-emerald-100 dark:border-emerald-900/30 pb-2">
                        [1] BARANG MASUK (DARI USER)
                    </h4>
                    <div class="space-y-4">
                        <!-- SUMBER BARANG -->
                        <div>
                            <label class="block text-[10px] font-black text-text-secondary uppercase tracking-widest mb-1.5">
                                SUMBER BARANG <span class="text-red-500">*</span>
                            </label>
                            <select v-model="incomingItem.incoming_source"
                                class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-3 py-2.5 bg-surface-50 dark:bg-surface-900 text-sm font-bold text-text-primary focus:outline-none focus:border-primary-500 transition-all">
                                <option value="luar_pstore">Luar PStore</option>
                                <option value="ex_pstore">Ex PStore</option>
                            </select>
                        </div>
                        <!-- DISTRIBUTOR -->
                        <div>
                            <label class="block text-[10px] font-black text-text-secondary uppercase tracking-widest mb-1.5">
                                PILIH DISTRIBUTOR <span class="text-red-500">*</span>
                            </label>
                            <select v-model="incomingItem.distributor_id"
                                class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-3 py-2.5 bg-surface-50 dark:bg-surface-900 text-sm font-bold text-text-primary focus:outline-none focus:border-primary-500 transition-all">
                                <option :value="null">-- PILIH DISTRIBUTOR --</option>
                                <option v-for="d in distributors" :key="d.id" :value="d.id">{{ d.name }}</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <!-- BRAND -->
                            <div>
                                <label class="block text-[10px] font-black text-text-secondary uppercase tracking-widest mb-1.5">BRAND <span class="text-red-500">*</span></label>
                                <select v-model="incomingItem.brand_id" @change="incomingItem.product_type_id = null; incomingItem.storage = '';"
                                    class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-3 py-2.5 bg-surface-50 dark:bg-surface-900 text-sm font-bold text-text-primary focus:outline-none focus:border-primary-500 transition-all">
                                    <option :value="null">Pilih Brand</option>
                                    <option v-for="b in getFilteredBrandsForItem(incomingItem)" :key="b.id" :value="b.id">{{ b.name }}</option>
                                </select>
                            </div>
                            <!-- TYPE -->
                            <div>
                                <label class="block text-[10px] font-black text-text-secondary uppercase tracking-widest mb-1.5">TIPE <span class="text-red-500">*</span></label>
                                <select v-model="incomingItem.product_type_id" :disabled="!incomingItem.brand_id" @change="incomingItem.storage = '';"
                                    class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-3 py-2.5 bg-surface-50 dark:bg-surface-900 text-sm font-bold text-text-primary focus:outline-none focus:border-primary-500 transition-all disabled:opacity-50">
                                    <option :value="null">Pilih Tipe</option>
                                    <option v-for="t in getFilteredTypesForItem(incomingItem)" :key="t.id" :value="t.id">{{ t.name }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <!-- STORAGE -->
                            <div>
                                <label class="block text-[10px] font-black text-text-secondary uppercase tracking-widest mb-1.5">STORAGE <span class="text-red-500">*</span></label>
                                <select v-model="incomingItem.storage" :disabled="!incomingItem.product_type_id"
                                    class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-3 py-2.5 bg-surface-50 dark:bg-surface-900 text-sm font-bold text-text-primary focus:outline-none focus:border-primary-500 transition-all disabled:opacity-50">
                                    <option value="">Pilih Storage</option>
                                    <option v-for="cap in getCapacitiesForItem(incomingItem)" :key="cap" :value="cap">{{ cap }}</option>
                                </select>
                            </div>
                            <!-- KONDISI -->
                            <div>
                                <label class="block text-[10px] font-black text-text-secondary uppercase tracking-widest mb-1.5">KATEGORI <span class="text-red-500">*</span></label>
                                <select v-model="incomingItem.condition"
                                    class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-3 py-2.5 bg-surface-50 dark:bg-surface-900 text-sm font-bold text-text-primary focus:outline-none focus:border-primary-500 transition-all">
                                    <option value="second">Second / SCD</option>
                                    <option value="new">Baru / NEW</option>
                                    <option value="bno">BNO</option>
                                </select>
                            </div>
                        </div>
                        <!-- IMEI -->
                        <div>
                            <label class="block text-[10px] font-black text-text-secondary uppercase tracking-widest mb-1.5">
                                MASUKKAN IMEI <span class="text-red-500">*</span>
                            </label>
                            <input v-model="incomingItem.imei" type="text" placeholder="15 digit IMEI..."
                                class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-3 py-2.5 bg-surface-50 dark:bg-surface-900 text-sm font-bold text-text-primary focus:outline-none focus:border-primary-500 transition-all" />
                        </div>
                        <!-- HARGA -->
                        <div>
                            <label class="block text-[10px] font-black text-text-secondary uppercase tracking-widest mb-1.5">
                                HARGA UNIT MASUK (PER UNIT) <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm font-bold text-text-secondary">Rp</span>
                                <input v-money:cost_price="incomingItem" type="text"
                                    class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl pl-8 pr-3 py-2.5 bg-surface-50 dark:bg-surface-900 text-sm font-bold text-text-primary focus:outline-none focus:border-primary-500 transition-all" />
                            </div>
                            <p class="text-[9px] font-bold text-text-secondary italic mt-1">*Otomatis jadi harga modal</p>
                        </div>
                    </div>
                </div>

                <!-- BARANG KELUAR (STOK TOKO) -->
                <div class="space-y-6">
                    <h4 class="text-sm font-black text-amber-600 uppercase tracking-widest border-b border-amber-100 dark:border-amber-900/30 pb-2">
                        [2] BARANG KELUAR (PILIH STOK TOKO)
                    </h4>
                    
                    <div>
                        <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">CARI & PILIH UNIT KELUAR <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <input v-model="stockSearchQuery" type="text"
                                @focus="showStockDropdown = true"
                                @blur="closeStockDropdown"
                                placeholder="Ketik Nama, Brand, IMEI, atau Harga..."
                                class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none" />
                            
                            <div v-if="showStockDropdown" class="absolute z-[100] mt-1 w-full bg-white dark:bg-surface-800 border-2 border-surface-200 dark:border-surface-700 rounded-xl shadow-2xl max-h-[300px] overflow-y-auto custom-scrollbar">
                                <div v-if="!filteredInventoryProducts || filteredInventoryProducts.length === 0" class="p-4 text-center text-xs text-text-secondary">
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

                        <div v-if="outgoingItem" class="mt-4 p-4 bg-primary-50 dark:bg-primary-900/10 rounded-xl border border-primary-100 dark:border-primary-800 relative">
                            <button @click="removeOutgoingItem" type="button" class="absolute top-2 right-2 text-primary-400 hover:text-red-500 transition-colors">
                                <X :size="20" />
                            </button>
                            <p class="text-xs font-black text-primary-700 dark:text-primary-400 mb-4 pr-6">
                                {{ outgoingItem.item?.product?.name || outgoingItem.item?.name }} ({{ outgoingItem.item?.imei || 'Non-IMEI' }})
                            </p>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-bold text-primary-600 dark:text-primary-400 uppercase tracking-widest mb-2">HARGA JUAL</label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-primary-600/50">Rp</span>
                                        <input v-money:price="outgoingItem" type="text"
                                            class="w-full border-2 border-primary-200 dark:border-primary-800/50 rounded-xl pl-8 pr-3 py-2 bg-white dark:bg-surface-900 focus:border-primary-500 transition-all outline-none font-bold text-sm text-primary-600" />
                                    </div>
                                </div>
                                <div v-if="!outgoingItem.item?.imei">
                                    <label class="block text-[10px] font-bold text-primary-600 dark:text-primary-400 uppercase tracking-widest mb-2">JUMLAH KELUAR</label>
                                    <div class="flex items-center gap-2">
                                        <input v-model.number="outgoingItem.quantity" type="number" min="1" :max="outgoingItem.max_quantity"
                                            class="w-full border-2 border-primary-200 dark:border-primary-800/50 rounded-xl px-3 py-2 bg-white dark:bg-surface-900 focus:border-primary-500 transition-all outline-none text-sm font-bold" />
                                        <span class="text-[10px] font-bold text-primary-600/60 uppercase">Maks: {{ outgoingItem.max_quantity }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FOTO UPLOADS -->
            <div class="bg-surface-50 dark:bg-surface-900/50 p-6 rounded-2xl mb-8 border border-surface-100 dark:border-surface-700">
                <h4 class="text-sm font-black text-primary-600 uppercase tracking-widest mb-6 flex items-center gap-2">
                    <Camera :size="18" /> DOKUMENTASI & BUKTI
                </h4>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    <!-- FOTO UNIT -->
                    <div>
                        <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2 text-center">FOTO UNIT KELUAR <span class="text-red-500">*</span></label>
                        <div @click="$refs.unitProofInput.click()" class="relative border-2 border-dashed border-surface-300 dark:border-surface-600 rounded-xl aspect-[4/3] flex flex-col items-center justify-center cursor-pointer hover:bg-surface-50 dark:hover:bg-surface-800 transition-all overflow-hidden group">
                            <template v-if="isCompressing">
                                <Loader2 class="w-8 h-8 text-primary-600 animate-spin" />
                                <span class="text-[10px] font-black text-text-secondary uppercase mt-2">Memproses...</span>
                            </template>
                            <template v-else-if="proofImagePreview">
                                <img :src="proofImagePreview" class="w-full h-full object-cover" />
                                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity">
                                    <Camera class="text-white w-6 h-6" />
                                </div>
                            </template>
                            <template v-else>
                                <Plus :size="24" class="text-text-secondary mb-1" />
                                <span class="text-[9px] font-black text-text-secondary uppercase">Upload Unit</span>
                            </template>
                        </div>
                        <input type="file" ref="unitProofInput" @change="e => handlePhotoChange('proofImage', e)" accept="image/*" class="hidden" capture="environment" />
                    </div>

                    <!-- FOTO CUSTOMER -->
                    <div>
                        <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2 text-center">FOTO CUSTOMER <span class="text-red-500">*</span></label>
                        <div @click="$refs.customerProofInput.click()" class="relative border-2 border-dashed border-surface-300 dark:border-surface-600 rounded-xl aspect-[4/3] flex flex-col items-center justify-center cursor-pointer hover:bg-surface-50 dark:hover:bg-surface-800 transition-all overflow-hidden group">
                            <template v-if="isCompressing">
                                <Loader2 class="w-8 h-8 text-primary-600 animate-spin" />
                                <span class="text-[10px] font-black text-text-secondary uppercase mt-2">Memproses...</span>
                            </template>
                            <template v-else-if="customerProofImagePreview">
                                <img :src="customerProofImagePreview" class="w-full h-full object-cover" />
                                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity">
                                    <Camera class="text-white w-6 h-6" />
                                </div>
                            </template>
                            <template v-else>
                                <Plus :size="24" class="text-text-secondary mb-1" />
                                <span class="text-[9px] font-black text-text-secondary uppercase">Upload Customer</span>
                            </template>
                        </div>
                        <input type="file" ref="customerProofInput" @change="e => handlePhotoChange('customerProofImage', e)" accept="image/*" class="hidden" capture="environment" />
                    </div>
                    
                    <!-- BUKTI TRANSFER -->
                    <div v-if="!isCashOnly && sisaBayar > 0">
                        <label class="block text-xs font-bold text-amber-600 uppercase tracking-widest mb-2 text-center">BUKTI TRANSFER <span class="text-red-500">*</span></label>
                        <div @click="$refs.paymentProofInput.click()" class="relative border-2 border-dashed border-amber-300 dark:border-amber-600 rounded-xl aspect-[4/3] flex flex-col items-center justify-center cursor-pointer hover:bg-amber-50 dark:hover:bg-amber-900/10 transition-all overflow-hidden group">
                            <template v-if="isCompressingPayment">
                                <Loader2 class="w-8 h-8 text-amber-600 animate-spin" />
                                <span class="text-[10px] font-black text-amber-600 uppercase mt-2">Memproses...</span>
                            </template>
                            <template v-else-if="paymentProofImagePreview">
                                <img :src="paymentProofImagePreview" class="w-full h-full object-cover" />
                                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity">
                                    <Camera class="text-white w-6 h-6" />
                                </div>
                            </template>
                            <template v-else>
                                <Plus :size="24" class="text-amber-500 mb-1" />
                                <span class="text-[9px] font-black text-amber-600 uppercase">Upload Bukti TF</span>
                            </template>
                        </div>
                        <input type="file" ref="paymentProofInput" @change="e => handlePhotoChange('paymentProofImage', e)" accept="image/*" class="hidden" capture="environment" />
                    </div>
                </div>
            </div>

            <!-- PEMBAYARAN & RINGKASAN -->
            <div class="mt-8 space-y-6">
                <h4 class="text-sm font-black text-transparent bg-clip-text bg-gradient-to-r from-primary-600 to-emerald-600 uppercase tracking-[0.15em] border-b border-surface-200 dark:border-surface-700 pb-3 mb-6">
                    PEMBAYARAN & RINGKASAN
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-start">
                    <div class="space-y-6">
                        <div>
                            <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">CATATAN TAMBAHAN</label>
                            <textarea v-model="customerForm.notes" rows="3"
                                class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none text-sm"
                                placeholder="Catatan transaksi..."></textarea>
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
                                    <button v-if="splitPayments && splitPayments.length > 1" @click="removeSplitPayment(index)" type="button" class="absolute top-2 right-2 text-gray-400 hover:text-red-500 transition-colors">
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
                    <div class="relative overflow-hidden p-8 sm:p-10 bg-gradient-to-br from-emerald-500 via-teal-500 to-primary-600 rounded-[2.5rem] shadow-2xl shadow-emerald-500/30 text-center transform transition-all hover:-translate-y-1 hover:shadow-emerald-500/40">
                        <div class="absolute inset-0 bg-[url('/noise.png')] opacity-10 mix-blend-overlay pointer-events-none"></div>
                        <div class="absolute -right-20 -top-20 w-64 h-64 bg-white/20 blur-3xl rounded-full pointer-events-none"></div>
                        <div class="absolute -left-20 -bottom-20 w-64 h-64 bg-black/10 blur-3xl rounded-full pointer-events-none"></div>
                        
                        <div class="relative z-10">
                            <div class="grid grid-cols-2 gap-4 mb-8">
                                <div class="text-left bg-white/10 p-4 rounded-2xl border border-white/20 flex flex-col justify-center">
                                    <span class="text-[9px] font-black text-primary-200 uppercase tracking-widest block mb-1">HARGA UNIT KELUAR</span>
                                    <p class="text-lg font-bold text-white truncate">{{ formatCurrency(totalOutgoingPriceComputed) }}</p>
                                </div>
                                <div class="text-right bg-white/10 p-4 rounded-2xl border border-white/20 flex flex-col justify-center">
                                    <span class="text-[9px] font-black text-primary-200 uppercase tracking-widest block mb-1">DP DIBAYAR</span>
                                    <p class="text-lg font-bold text-white truncate">- {{ formatCurrency(dpAmount) }}</p>
                                </div>
                            </div>

                            <div class="pt-6 border-t border-white/20">
                                <span class="text-[10px] font-black text-primary-100 uppercase tracking-[0.2em] block mb-2">SISA PELUNASAN</span>
                                <p class="text-4xl sm:text-5xl font-black text-white px-2 py-1 leading-none">{{ formatCurrency(sisaBayar) }}</p>
                            </div>
                            
                            <div v-if="sisaBayar === 0" class="mt-6 px-4 py-2 bg-white/20 backdrop-blur-md rounded-full inline-flex items-center gap-2 text-xs text-white font-black uppercase tracking-widest border border-white/30">
                                <CheckCircle :size="16" /> LUNAS
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit Section -->
            <div class="mt-12 pt-8 border-t border-surface-100 dark:border-surface-700 flex flex-col sm:flex-row gap-4">
                <button @click="currentStep = 1"
                    class="flex-1 py-4 sm:py-5 bg-surface-100 dark:bg-surface-800 text-text-primary rounded-2xl font-black uppercase tracking-widest hover:bg-surface-200 dark:hover:bg-surface-700 transition-all active:scale-[0.98] border border-surface-200 dark:border-surface-700">
                    Kembali Ganti DP
                </button>
                <button @click="handleSubmit()" :disabled="isSubmitting"
                    class="flex-[2] py-4 sm:py-5 bg-gradient-to-r from-emerald-500 to-primary-600 hover:from-emerald-400 hover:to-primary-500 disabled:opacity-50 text-white rounded-2xl font-black uppercase tracking-[0.1em] shadow-xl shadow-emerald-500/30 transition-all hover:shadow-emerald-500/50 hover:-translate-y-1 active:scale-[0.98] flex items-center justify-center gap-3">
                    <Loader2 v-if="isSubmitting" class="animate-spin" :size="24" />
                    <template v-else>
                        <Save :size="24" /> Selesaikan Pelunasan
                    </template>
                </button>
            </div>
        </div>
    </div>
</template>
