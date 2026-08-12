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
    selectedAccountObject: Object,
    transactionCategory: String
});

const emit = defineEmits(["prev", "next", "transaction-complete", "verify-pin"]);

const authStore = useAuthStore();
const isSubmitting = ref(false);
const isRestoring = ref(true);

const isCompressing = ref(false);
const dpPhotos = ref({
    unit: null,
    unitPreview: null
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


const dpForm = ref({
    customer_name: "",
    customer_phone: "",
    brand_id: null,
    product_type_id: null,
    storage: "",
    condition: "",
    selling_price: 0,
    payment_method_id: null,
    notes: "",
});


function getFilteredTypesForItem(item) {
    if (!item.brand_id) return [];
    return props.productTypes.filter(t => t.brand_id === item.brand_id);
}

// Persistence
const storageKey = computed(() => {
    const userId = authStore.user?.id || 'guest';
    const acc = props.salesAccount ? `_acc_${props.salesAccount.replace(/\s+/g, '_')}` : '';
    return `temp_dp_form_${userId}${acc}`;
});

watch([dpForm, dpPhotos], ([newForm, newPhotos]) => {
    if (isRestoring.value) return;
    
    const persistentPhotos = {
        unitPreview: newPhotos.unitPreview
    };

    localStorage.setItem(storageKey.value, JSON.stringify({
        form: newForm,
        photos: persistentPhotos
    }));
}, { deep: true });

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

async function restoreDraft() {
    const saved = localStorage.getItem(storageKey.value);
    if (saved) {
        try {
            isRestoring.value = true;
            const data = JSON.parse(saved);
            Object.assign(dpForm.value, data.form || data);
            
            if (data.photos) {
                dpPhotos.value.unitPreview = data.photos.unitPreview;
                
                if (data.photos.unitPreview && data.photos.unitPreview.startsWith('data:')) {
                    try {
                        dpPhotos.value.unit = dataURLtoFile(data.photos.unitPreview, 'unit_restored.jpg');
                    } catch (e) {}
                }
            }

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

watch(() => authStore.user?.id, (newId) => {
    if (newId) {
        restoreDraft();
    }
}, { immediate: true });

onMounted(() => {
    setTimeout(() => {
        if (isRestoring.value && !authStore.user?.id) {
            isRestoring.value = false;
        }
    }, 2000);
});

// Computeds
const filteredBrands = computed(() => {
    return props.brands;
});

const filteredTradeInTypes = computed(() => {
    if (!dpForm.value.brand_id) return [];
    return props.productTypes.filter(t => t.brand_id === dpForm.value.brand_id);
});

const selectedTradeInType = computed(() => {
    if (!dpForm.value.product_type_id) return null;
    return props.productTypes.find(t => t.id === dpForm.value.product_type_id);
});

const isImeiTradeIn = computed(() => {
    if (!selectedTradeInType.value) return true;
    const cat = selectedTradeInType.value.category?.toLowerCase();
    return cat === 'imei' || cat === 'hp / gadget';
});


const isSplitInvalid = computed(() => {
    const totalSplit = splitPayments.value.reduce((sum, p) => sum + (p.amount || 0), 0);
    // DP must be at least 1 and not more than selling price
    return totalSplit <= 0 || totalSplit > (dpForm.value.selling_price || 0);
});

const filteredTradeInCapacities = computed(() => {
    if (!dpForm.value.product_type_id) return [];
    const set = new Set();
    
    const prices = props.productPrices.filter(p => {
        const matchesType = p.product_type_id === dpForm.value.product_type_id;
        return matchesType;
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
    const set = new Set(defaults);
    
    if (dpForm.value.product_type_id) {
        props.productPrices
            .filter(p => {
                const matchesType = p.product_type_id === dpForm.value.product_type_id;
                const matchesStorage = p.storage === dpForm.value.storage;
                return matchesType && matchesStorage;
            })
            .forEach(p => { if (p.condition) set.add(p.condition); });
    }
    
    return Array.from(set);
});

// Watchers
watch(() => dpForm.value.brand_id, (newVal, oldVal) => {
    if (isRestoring.value) return;
    if (newVal !== oldVal) {
        dpForm.value.product_type_id = null;
        dpForm.value.storage = "";
        dpForm.value.condition = "";
    }
});

watch(() => dpForm.value.product_type_id, (newVal, oldVal) => {
    if (isRestoring.value) return;
    if (newVal !== oldVal) {
        dpForm.value.storage = "";
        dpForm.value.condition = "";
    }
});

watch(() => dpForm.value.storage, (newVal, oldVal) => {
    if (isRestoring.value) return;
    if (newVal !== oldVal) {
        dpForm.value.condition = "";
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
            dpPhotos.value[type] = compressedFile;

            // 3. Set Preview
            const reader = new FileReader();
            reader.onload = (e) => {
                dpPhotos.value[`${type}Preview`] = e.target.result;
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
    if (methods?.length > 0 && !dpForm.value.payment_method_id) {
        const cashMethod = methods.find(p => p.category?.toLowerCase() === 'cash' || p.name?.toLowerCase() === 'tunai');
        dpForm.value.payment_method_id = cashMethod ? cashMethod.id : methods[0].id;
    }
}, { immediate: true });


async function submitTradeIn(pin = null) {
    const isImei = isImeiTradeIn.value;
    const hasRequiredFields = dpForm.value.customer_name && 
                             dpForm.value.customer_phone && 
                             dpForm.value.brand_id && 
                             dpForm.value.product_type_id && 
                             dpForm.value.selling_price;

    const hasSpecificFields = !isImei || (dpForm.value.storage && dpForm.value.condition);

    if (!hasRequiredFields || !hasSpecificFields) {
         alert("Mohon lengkapi semua data wajib.");
         return;
     }

    const totalSplit = splitPayments.value.reduce((sum, p) => sum + (p.amount || 0), 0);
    if (totalSplit <= 0 || totalSplit > dpForm.value.selling_price) {
        alert(`Total DP (${formatCurrency(totalSplit)}) tidak valid. Harus lebih dari 0 dan tidak boleh melebihi Harga Total (${formatCurrency(dpForm.value.selling_price)}).`);
        return;
    }

    if (!dpPhotos.value.unit) {
        alert("Foto unit wajib diupload.");
        return;
    }

    if (!pin && props.selectedAccountObject) {
        emit('verify-pin', (verifiedPin) => submitTradeIn(verifiedPin));
        return;
    }

    isSubmitting.value = true;
    const formData = new FormData();
    formData.append('category', 'dp');
    formData.append('sales_account', props.salesAccount);

    const totalPaid = splitPayments.value.reduce((sum, p) => sum + p.amount, 0);
    formData.append('paid_amount', totalPaid);
    formData.append('dp_amount', totalPaid);
    formData.append('selling_price', dpForm.value.selling_price);

    formData.append('customer_name', dpForm.value.customer_name);
    formData.append('customer_wa', dpForm.value.customer_phone);
    formData.append('customer_phone', dpForm.value.customer_phone);
    if (props.selectedAccountObject?.id) {
        formData.append('inventory_user_id', props.selectedAccountObject.id);
    }
    // if (pin) formData.append('password', pin);
      if (pin) formData.append('transaction_pin', pin);
    formData.append('notes', dpForm.value.notes);

    // Send manual product details 
    formData.append('brand_id', dpForm.value.brand_id);
    formData.append('product_type_id', dpForm.value.product_type_id);
    formData.append('storage', dpForm.value.storage);
    formData.append('condition', dpForm.value.condition);

    formData.append('global_discount_value', 0);
    formData.append('global_discount_type', 'fixed');
    formData.append('total_discount', 0);

    formData.append('split_payments', JSON.stringify(splitPayments.value.map(p => ({
        payment_method_id: p.method_id,
        amount: p.amount
    }))));

    // Map photos to stock-out expected fields
    if (dpPhotos.value.unit) formData.append('proof_image', dpPhotos.value.unit);

    try {
        const response = await api.post('/stock-outs', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });

        const data = response.data.data || response.data;
        const selectedType = props.productTypes.find(t => t.id === dpForm.value.product_type_id);
        const selectedBrand = props.brands?.find(b => b.id === dpForm.value.brand_id);
        let itemName = [selectedBrand?.name, selectedType?.name, dpForm.value.storage, dpForm.value.condition].filter(Boolean).join(' ');
        // Guaranteed removal of PSTORE UNIT
        itemName = itemName.replace(/PSTORE UNIT/gi, '');
        itemName = itemName.replace(/^\s*-\s*/, '');
        itemName = itemName.trim();

            const transaction = {
                id: data.id,
                order_no: data.receipt_id || "TRX-" + Date.now(),
                items: [{
                    product: selectedType,
                    name: itemName || 'Manual Item DP',
                imei: '-',
                selling_price: dpForm.value.selling_price,
                condition: dpForm.value.condition,
                storage: dpForm.value.storage,
                price: dpForm.value.selling_price,
                qty: 1,
            }],
            original_price: dpForm.value.selling_price,
            grand_total: dpForm.value.selling_price,
            total: dpForm.value.selling_price,
            paid: totalPaid,
            cash: splitPayments.value.filter(p => props.availablePaymentMethods.find(m => m.id === p.method_id)?.category?.toLowerCase() === 'cash').reduce((sum, p) => sum + p.amount, 0),
            transfer: splitPayments.value.filter(p => props.availablePaymentMethods.find(m => m.id === p.method_id)?.category?.toLowerCase() === 'transfer').reduce((sum, p) => sum + p.amount, 0),
            payment_method_name: props.availablePaymentMethods.find(m => m.id === splitPayments.value[0]?.method_id)?.name,
            split_payments_data: splitPayments.value.map(p => ({
                method_name: props.availablePaymentMethods.find(m => m.id === p.method_id)?.name || 'Unknown',
                amount: p.amount
            })),
            category: 'dp',
            customer_name: dpForm.value.customer_name,
            customer_phone: dpForm.value.customer_phone,
            customer_wa: dpForm.value.customer_phone,
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
            proof_images: [
                data.proof_image ? `${authStore.storageBaseUrl}/storage/${data.proof_image}` : null
            ].filter(Boolean)
        };

        emit("transaction-complete", transaction);

        // Reset form
        dpForm.value = {
            customer_name: "",
            customer_phone: "",
            brand_id: null,
            product_type_id: null,
            storage: "",
            condition: "",
            selling_price: 0,
            payment_method_id: props.availablePaymentMethods?.[0]?.id || null,
            notes: "",
        };
        splitPayments.value = [
            {
                method_id: props.availablePaymentMethods[0]?.id || null,
                amount: 0
            }
        ];
        dpPhotos.value = { unit: null, unitPreview: null };

    } catch (error) {
        console.error("DP failed", error);
        let msg = "Gagal memproses DP";
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
    <div class="flex-1 overflow-y-auto custom-scrollbar bg-white dark:bg-surface-800 rounded-[1.5rem] sm:rounded-[2rem] border border-surface-200 dark:border-surface-700 p-4 sm:p-8 shadow-xl">
        <div class="max-w-4xl mx-auto">
            <div class="flex items-center justify-between mb-8 gap-4">
                <div class="flex items-center gap-3">
                    <button @click="emit('prev')" class="p-2 -ml-2 hover:bg-surface-100 dark:hover:bg-surface-700 rounded-full text-primary-600 transition-colors">
                        <ArrowLeft :size="28" stroke-width="3" />
                    </button>
                    <div class="flex flex-col">
                        <h3 class="text-lg sm:text-2xl font-black text-text-primary uppercase tracking-tight leading-none">Formulir DP</h3>
                        <p class="text-[10px] font-bold text-text-secondary uppercase tracking-widest mt-1">Pembayaran di Awal / Pre-order</p>
                    </div>
                </div>
                <div class="hidden xs:block px-4 py-1.5 bg-primary-100 dark:bg-primary-900/30 text-primary-600 rounded-full text-[10px] font-black uppercase tracking-widest">
                    DP
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Customer Info -->
                <div class="space-y-6">
                    <h4 class="text-sm font-black text-primary-600 uppercase tracking-widest border-b border-primary-100 dark:border-primary-900/30 pb-2">
                        Data Customer</h4>
                    <div>
                        <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Nama Customer <span class="text-red-500">*</span></label>
                        <input v-model="dpForm.customer_name" type="text" placeholder="Nama lengkap..." class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none" />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">No WhatsApp <span class="text-red-500">*</span></label>
                        <input v-model="dpForm.customer_phone" type="text" placeholder="08xxx..." class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none" />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Keterangan / Notes</label>
                        <textarea v-model="dpForm.notes" rows="2" class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none text-sm" placeholder="Catatan tambahan..."></textarea>
                    </div>
                </div>

                <!-- Unit Specs -->
                <div class="space-y-6">
                    <h4 class="text-sm font-black text-primary-600 uppercase tracking-widest border-b border-primary-100 dark:border-primary-900/30 pb-2">
                        Data Unit DP</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Brand <span class="text-red-500">*</span></label>
                            <select v-model="dpForm.brand_id" class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none">
                                <option :value="null" disabled>Pilih Brand</option>
                                <option v-for="b in filteredBrands" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Tipe <span class="text-red-500">*</span></label>
                            <select v-model="dpForm.product_type_id" class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none" :disabled="!dpForm.brand_id">
                                <option :value="null" disabled>Pilih Tipe</option>
                                <option v-for="p in filteredTradeInTypes" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select>
                        </div>
                    </div>
                    <div v-if="isImeiTradeIn" class="grid grid-cols-1 xs:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Kapasitas (Internal) <span class="text-red-500">*</span></label>
                            <select v-model="dpForm.storage" class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none" :disabled="!dpForm.product_type_id">
                                <option value="" disabled>Pilih Kapasitas</option>
                                <option v-for="storage in filteredTradeInCapacities" :key="storage" :value="storage">{{ storage }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Kondisi <span class="text-red-500">*</span></label>
                            <select v-model="dpForm.condition" class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none" :disabled="!dpForm.storage">
                                <option value="" disabled>Pilih Kondisi</option>
                                <option v-for="cond in filteredTradeInConditions" :key="cond" :value="cond">{{ cond === 'new' ? 'New' : (cond === 'ex_ibox' ? 'Ex iBox' : 'Second / SCD') }}</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Nominal Bayar DP <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm font-bold text-text-secondary">Rp</span>
                            <input v-money:selling_price="dpForm" type="text" class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl pl-10 pr-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none font-black text-lg text-primary-600" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Financial & Media -->
            <div class="space-y-6 mt-8">
                <h4 class="text-sm font-black text-primary-600 uppercase tracking-widest border-b border-primary-100 dark:border-primary-900/30 pb-2">
                    Pembayaran DP & Bukti</h4>
                
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest">Metode Pembayaran DP <span class="text-red-500">*</span></label>
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
                                    <label class="block text-[10px] font-black text-text-secondary uppercase tracking-widest mb-1">Nominal Bayar DP</label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary text-[11px] font-black">Rp</span>
                                        <input v-money:amount="payment" type="text" class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-lg px-3 py-2 bg-surface-50 dark:bg-surface-900 text-xs font-black text-text-primary focus:outline-none focus:border-primary-500 transition-all pl-8" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2 text-center">Foto Bukti DP / Unit <span class="text-red-500">*</span></label>
                        <div @click="$refs.unitAngkatInput.click()" class="relative border-2 border-dashed border-surface-300 dark:border-surface-600 rounded-2xl aspect-video flex flex-col items-center justify-center cursor-pointer hover:bg-surface-50 dark:hover:bg-surface-800 transition-all overflow-hidden group">
                            <template v-if="isCompressing">
                                <Loader2 class="w-8 h-8 text-primary-600 animate-spin" />
                                <span class="text-[10px] font-black text-text-secondary uppercase mt-2">Memproses...</span>
                            </template>
                            <template v-else-if="dpPhotos.unitPreview">
                                <img :src="dpPhotos.unitPreview" class="w-full h-full object-contain bg-surface-100 dark:bg-surface-900" />
                                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity">
                                    <Camera class="text-white w-6 h-6" />
                                </div>
                            </template>
                            <template v-else>
                                <Plus :size="32" class="text-text-secondary mb-2" />
                                <span class="text-[10px] font-black text-text-secondary uppercase tracking-widest">Upload Bukti DP</span>
                            </template>
                        </div>
                        <input type="file" ref="unitAngkatInput" @change="e => handlePhotoChange('unit', e)" accept="image/*" class="hidden" capture="environment" />
                    </div>
                </div>
            </div>

        </div>

        <!-- Submit Section -->
        <div class="mt-12 pt-8 border-t border-surface-100 dark:border-surface-700 flex flex-col sm:flex-row gap-4 max-w-4xl mx-auto">
            <button @click="emit('prev')"
                class="flex-1 py-4 bg-surface-100 dark:bg-surface-700 text-text-primary rounded-2xl font-black uppercase tracking-widest hover:bg-surface-200 transition-all">
                Kembali
            </button>
            <button @click="submitTradeIn()" :disabled="isSubmitting || isSplitInvalid"
                class="flex-[2] py-4 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 disabled:bg-surface-300 dark:disabled:bg-surface-600 disabled:cursor-not-allowed text-white rounded-2xl font-black uppercase tracking-widest shadow-xl shadow-emerald-500/20 transition-all flex items-center justify-center gap-3">
                <Loader2 v-if="isSubmitting" class="animate-spin" :size="24" />
                <template v-else>
                    <Save :size="24" /> Selesaikan & Simpan DP
                </template>
            </button>
        </div>
    </div>
</template>
