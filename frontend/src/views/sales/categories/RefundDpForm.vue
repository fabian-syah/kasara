<script setup>
import { ref, computed, onMounted, watch } from "vue";
import api from "../../../api/axios";
import { formatCurrency } from "../../../utils/formatters";
import {
    ArrowLeft,
    ArrowRight,
    Search,
    Loader2,
    Save,
    X,
    Plus,
    AlertTriangle,
    Undo2
} from "lucide-vue-next";
import { useAuthStore } from "../../../store/auth";

const props = defineProps({
    availablePaymentMethods: Array,
    salesAccount: String,
    selectedAccountObject: Object,
    brands: Array,
    productTypes: Array,
    productPrices: Array,
    distributors: Array
});

const emit = defineEmits(["back", "transaction-complete", "verify-pin"]);
const authStore = useAuthStore();

// State
const activeDps = ref([]);
const isLoadingDps = ref(false);
const searchQuery = ref("");
const selectedDp = ref(null);
const currentStep = ref(1); // 1 = Select DP, 2 = Form Refund
const isSubmitting = ref(false);

const refundForm = ref({
    reason: "",
    notes: "",
    photo: null
});

function handlePhotoUpload(event) {
    const file = event.target.files[0];
    if (file) {
        if (file.size > 10 * 1024 * 1024) {
            alert('Ukuran foto tidak boleh lebih dari 10MB.');
            return;
        }
        refundForm.value.photo = file;
    }
}

function removePhoto() {
    refundForm.value.photo = null;
    const input = document.getElementById('refundPhotoUpload');
    if (input) input.value = '';
}

const splitPayments = ref([]);

// Fetch active DPs
const fetchActiveDps = async () => {
    isLoadingDps.value = true;
    try {
        const params = {};
        if (props.selectedAccountObject?.branch_id) {
            params.branch_id = props.selectedAccountObject.branch_id;
        } else if (props.selectedAccountObject?.branch?.id) {
            params.branch_id = props.selectedAccountObject.branch.id;
        }

        const response = await api.get('/stock-outs/active-dps', { params });
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

// Filtered DPs based on search
const filteredDps = computed(() => {
    if (!activeDps.value) return [];
    if (!searchQuery.value) return activeDps.value;
    
    const query = searchQuery.value.toLowerCase();
    return activeDps.value.filter(dp => {
        const customerName = (dp.customer_name || '').toLowerCase();
        const customerPhone = (dp.customer_phone || dp.customer_wa || '').toLowerCase();
        const receiptId = (dp.receipt_id || '').toLowerCase();
        return customerName.includes(query) || customerPhone.includes(query) || receiptId.includes(query);
    });
});

// DP Amount
const dpAmount = computed(() => {
    if (!selectedDp.value) return 0;
    const dp = Number(selectedDp.value.dp_amount || 0);
    return dp > 0 ? dp : Number(selectedDp.value.selling_price || 0);
});

// Extract DP item info (reuse pattern from PelunasanDpForm)
function getDpItemInfo(dp) {
    if (!dp) return { brand: "-", type: "-", gb: "-", dpDate: "-" };
    let brand = "-";
    let type = "-";
    let gb = "-";
    
    if (dp.items && Array.isArray(dp.items) && dp.items.length > 0) {
        const firstItem = dp.items[0];
        brand = firstItem.product?.brand?.name || firstItem.product?.brand || firstItem.brand || "-";
        type = firstItem.product?.name || firstItem.name || "-";
        gb = firstItem.storage || "-";
    } else if (dp.non_hp_details && Array.isArray(dp.non_hp_details) && dp.non_hp_details.length > 0) {
        const firstItem = dp.non_hp_details[0];
        type = firstItem.product?.name || firstItem.name || "-";
    } else if (dp.notes) {
        const lines = dp.notes.split('\n');
        let firstLine = lines[0] || "";
        
        const gbMatch = firstLine.match(/(\d+\s*GB)/i);
        if (gbMatch) {
            gb = gbMatch[1];
            firstLine = firstLine.replace(gbMatch[1], '').trim();
        }
        
        firstLine = firstLine.replace(/\b(Second|New|BNO|Baru|SCD)\b/gi, '').trim();
        
        if (props.brands && Array.isArray(props.brands)) {
            const sortedBrands = [...props.brands].sort((a, b) => (b.name?.length || 0) - (a.name?.length || 0));
            for (const b of sortedBrands) {
                if (b.name && firstLine.toLowerCase().startsWith(b.name.toLowerCase())) {
                    brand = b.name;
                    firstLine = firstLine.substring(b.name.length).trim();
                    firstLine = firstLine.replace(/^[™®-]\s*/, '').trim();
                    break;
                }
            }
        }
        
        if (brand === "-") {
            const commonBrands = ['Iphone ™', 'Iphone', 'Samsung', 'Oppo', 'Vivo', 'Xiaomi', 'Realme', 'Infinix', 'Poco', 'Asus', 'Itel', 'Tecno', 'Nokia', 'Huawei', 'Honor'];
            for (const b of commonBrands) {
                if (firstLine.toLowerCase().startsWith(b.toLowerCase())) {
                    brand = b;
                    firstLine = firstLine.substring(b.length).trim();
                    firstLine = firstLine.replace(/^[™®-]\s*/, '').trim();
                    break;
                }
            }
        }
        
        type = firstLine || "-";
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

// Select DP
function selectDp(dp) {
    selectedDp.value = dp;
}

// Proceed to refund form
function handleProceedToForm() {
    if (!selectedDp.value) return;
    
    // Init split payments with DP amount
    splitPayments.value = [{
        method_id: props.availablePaymentMethods?.[0]?.id || null,
        amount: dpAmount.value
    }];
    
    currentStep.value = 2;
}

// Split payment helpers
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

const isSplitInvalid = computed(() => {
    const totalSplit = splitPayments.value.reduce((sum, p) => sum + (p.amount || 0), 0);
    return totalSplit !== dpAmount.value;
});

// Init payment method
watch(() => props.availablePaymentMethods, (methods) => {
    if (methods?.length > 0 && splitPayments.value.length > 0 && !splitPayments.value[0].method_id) {
        const cashMethod = methods.find(p => p.category?.toLowerCase() === 'cash' || p.name?.toLowerCase() === 'tunai');
        splitPayments.value[0].method_id = cashMethod ? cashMethod.id : methods[0].id;
    }
}, { immediate: true });

// Submit
const isClickLocked = ref(false);
async function submitRefundDp(pin = null) {
    if (isClickLocked.value && !pin) return;
    if (!pin) {
        isClickLocked.value = true;
        setTimeout(() => { isClickLocked.value = false; }, 1000);
    }

    if (!refundForm.value.reason) {
        alert("Alasan pembatalan DP wajib diisi.");
        return;
    }

    const totalSplit = splitPayments.value.reduce((sum, p) => sum + (p.amount || 0), 0);
    if (totalSplit !== dpAmount.value) {
        alert(`Total split pembayaran (${formatCurrency(totalSplit)}) tidak sesuai dengan nominal refund (${formatCurrency(dpAmount.value)}).`);
        return;
    }

    if (!pin && props.selectedAccountObject) {
        emit('verify-pin', (verifiedPin) => submitRefundDp(verifiedPin));
        return;
    }

    isSubmitting.value = true;
    try {
        const formData = new FormData();
        formData.append('stock_out_id', selectedDp.value.id);
        formData.append('reason', refundForm.value.reason);
        
        if (refundForm.value.notes) {
            formData.append('notes', refundForm.value.notes);
        }
        
        if (refundForm.value.photo) {
            formData.append('photo', refundForm.value.photo);
        }
        
        formData.append('payment_method_id', splitPayments.value[0]?.method_id);
        formData.append('split_payments', JSON.stringify(splitPayments.value.map(p => ({
            payment_method_id: p.method_id,
            amount: p.amount
        }))));
        
        if (props.salesAccount) {
            formData.append('sales_account', props.salesAccount);
        }

        if (props.selectedAccountObject?.id) {
            formData.append('inventory_user_id', props.selectedAccountObject.id);
        }
        
        if (pin) {
            formData.append('transaction_pin', pin);
        }

        const response = await api.post('/dp-refunds', formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        });
        const data = response.data.data || response.data;

        const dpInfo = getDpItemInfo(selectedDp.value);

        const transaction = {
            id: data.id,
            order_no: data.receipt_id || "RDP-" + Date.now(),
            items: [{
                name: `Refund DP: ${dpInfo.brand} - ${dpInfo.type}`,
                imei: '-',
                price: dpAmount.value,
                condition: '-',
                storage: dpInfo.gb,
                qty: 1,
                is_hp: true
            }],
            original_price: dpAmount.value,
            grand_total: dpAmount.value,
            total: dpAmount.value,
            paid: dpAmount.value,
            cash: splitPayments.value.filter(p => {
                const m = props.availablePaymentMethods.find(m => m.id === p.method_id);
                return m?.category?.toLowerCase() === 'cash';
            }).reduce((sum, p) => sum + p.amount, 0),
            transfer: splitPayments.value.filter(p => {
                const m = props.availablePaymentMethods.find(m => m.id === p.method_id);
                return m?.category?.toLowerCase() === 'transfer';
            }).reduce((sum, p) => sum + p.amount, 0),
            payment_method_name: props.availablePaymentMethods.find(m => m.id === splitPayments.value[0]?.method_id)?.name,
            split_payments_data: splitPayments.value.map(p => ({
                method_name: props.availablePaymentMethods.find(m => m.id === p.method_id)?.name || 'Unknown',
                amount: p.amount
            })),
            category: 'refund_dp',
            customer_name: selectedDp.value.customer_name || selectedDp.value.customer_wa,
            customer_phone: selectedDp.value.customer_wa || selectedDp.value.customer_phone,
            branch_name: props.selectedAccountObject?.branch?.name || authStore.user?.branch?.name || '',
            branch_timezone: authStore.user?.branch?.timezone || 'WIB',
            created_at: new Date().toISOString(),
            date: new Date().toLocaleDateString("id-ID", { day: '2-digit', month: 'short', year: 'numeric' }),
            time: new Date().toLocaleTimeString("id-ID", { hour: '2-digit', minute: '2-digit' }),
            inventory_user_name: props.salesAccount || authStore.user?.name,
            notes: `Refund DP nota ${selectedDp.value.receipt_id}. Alasan: ${refundForm.value.reason}`
        };

        emit("transaction-complete", transaction);

    } catch (error) {
        console.error("Refund DP failed", error);
        let msg = "Gagal memproses refund DP";
        if (error.response) {
            if (error.response.data?.message) msg = error.response.data.message;
            else msg = `Error ${error.response.status}: ${error.response.statusText}`;
        }
        alert(msg);
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <!-- STEP 1: SELECT DP TO REFUND -->
    <div v-if="currentStep === 1" class="w-full flex flex-col gap-4 sm:gap-8 items-start relative min-h-0">
        <div class="w-full flex items-center justify-between bg-white dark:bg-surface-800 p-4 rounded-2xl border border-surface-200 dark:border-surface-700 shadow-sm mb-2">
            <div class="flex items-center gap-3">
                <button @click="emit('back')" class="p-2 -ml-2 hover:bg-surface-100 dark:hover:bg-surface-700 rounded-full text-primary-600 transition-colors">
                    <ArrowLeft :size="28" stroke-width="3" />
                </button>
                <div class="flex flex-col">
                    <h3 class="text-lg sm:text-xl font-black text-text-primary uppercase tracking-tight leading-none">Refund DP</h3>
                    <p class="text-[10px] font-bold text-text-secondary uppercase tracking-widest mt-1">Pembatalan & Pengembalian Uang DP</p>
                </div>
            </div>
            <div class="hidden xs:block px-4 py-1.5 bg-red-100 dark:bg-red-900/30 text-red-600 rounded-full text-[10px] font-black uppercase tracking-widest">
                Refund
            </div>
        </div>

        <div class="w-full bg-white dark:bg-surface-800 rounded-[1.5rem] border border-surface-200 dark:border-surface-700 p-4 sm:p-6 mb-6 shadow-sm flex flex-col gap-4">
            <!-- Search -->
            <div class="relative w-full">
                <Search class="absolute left-5 top-1/2 -translate-y-1/2 text-text-secondary" :size="20" />
                <input v-model="searchQuery" type="text" placeholder="Cari Nama / No Nota / No HP..."
                    class="w-full bg-surface-50 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 rounded-2xl pl-12 pr-4 py-3 sm:py-4 text-base sm:text-lg font-medium text-text-primary focus:outline-none focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 transition-all" />
            </div>

            <!-- Loading -->
            <div v-if="isLoadingDps" class="py-12 flex justify-center items-center">
                <Loader2 class="animate-spin text-primary-500" :size="32" />
            </div>

            <!-- Empty -->
            <div v-else-if="!filteredDps || filteredDps.length === 0" class="py-12 text-center text-surface-400">
                <Undo2 class="mx-auto mb-3 text-surface-300" :size="40" />
                <p class="font-bold">Belum ada nota DP yang aktif atau ditemukan.</p>
                <p class="text-xs mt-1">Semua DP sudah dilunasi atau di-refund.</p>
            </div>

            <!-- DP List -->
            <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div v-for="dp in filteredDps" :key="dp.id" 
                    @click="selectDp(dp)"
                    class="p-4 rounded-xl border-2 cursor-pointer transition-all flex flex-col gap-2 relative overflow-hidden hover:shadow-lg"
                    :class="selectedDp?.id === dp.id ? 'border-red-500 bg-red-50 dark:bg-red-900/10 shadow-lg shadow-red-500/10' : 'border-surface-200 dark:border-surface-700 hover:border-red-300 bg-surface-50 dark:bg-surface-900'">
                    
                    <!-- Selected indicator -->
                    <div v-if="selectedDp?.id === dp.id" class="absolute top-3 right-3 w-6 h-6 bg-red-500 rounded-full flex items-center justify-center">
                        <svg class="w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                    </div>

                    <div class="flex justify-between items-start pr-8">
                        <div>
                            <p class="font-black text-text-primary">{{ dp.customer_name || dp.customer_wa || '-' }}</p>
                            <p class="text-xs text-text-secondary">{{ dp.customer_wa || dp.customer_phone || '-' }}</p>
                        </div>
                        <span class="text-xs font-black bg-amber-500/10 text-amber-600 px-2 py-1 rounded">
                            {{ dp.receipt_id }}
                        </span>
                    </div>

                    <div class="flex flex-col mt-2 pt-2 border-t border-surface-200 dark:border-surface-700 text-xs text-text-secondary space-y-1">
                        <div class="flex justify-between"><span class="font-bold">Tanggal DP:</span> <span class="font-bold text-text-primary">{{ getDpItemInfo(dp).dpDate }}</span></div>
                        <div class="flex justify-between"><span class="font-bold">Brand:</span> <span class="font-bold text-text-primary">{{ getDpItemInfo(dp).brand }}</span></div>
                        <div class="flex justify-between"><span class="font-bold">Tipe:</span> <span class="font-bold text-text-primary">{{ getDpItemInfo(dp).type }}</span></div>
                        <div class="flex justify-between"><span class="font-bold">Kapasitas:</span> <span class="font-bold text-text-primary">{{ getDpItemInfo(dp).gb }}</span></div>
                    </div>

                    <div class="flex flex-col mt-2 pt-2 border-t border-surface-200 dark:border-surface-700">
                        <div class="flex justify-between text-xs mb-1">
                            <span class="text-text-secondary font-bold">DP Dibayar</span>
                            <span class="font-black text-emerald-600">{{ formatCurrency(dp.dp_amount > 0 ? dp.dp_amount : dp.selling_price) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Proceed Button -->
            <div class="mt-4 flex justify-end">
                <button @click="handleProceedToForm" :disabled="!selectedDp"
                    class="px-8 py-4 bg-red-600 hover:bg-red-500 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-2xl font-black text-lg shadow-xl shadow-red-500/20 transition-all flex items-center gap-2 active:scale-95">
                    Proses Refund DP <ArrowRight :size="20" />
                </button>
            </div>
        </div>
    </div>

    <!-- STEP 2: REFUND DP FORM -->
    <div v-else-if="currentStep === 2" class="flex-1 overflow-y-auto custom-scrollbar bg-white dark:bg-surface-800 rounded-[1.5rem] sm:rounded-[2rem] border border-surface-200 dark:border-surface-700 p-4 pb-24 sm:p-8 sm:pb-8 shadow-xl">
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="flex items-center justify-between mb-8 gap-4">
                <div class="flex items-center gap-3">
                    <button @click="currentStep = 1" class="p-2 -ml-2 hover:bg-surface-100 dark:hover:bg-surface-700 rounded-full text-primary-600 transition-colors">
                        <ArrowLeft :size="28" stroke-width="3" />
                    </button>
                    <div class="flex flex-col">
                        <h3 class="text-lg sm:text-2xl font-black text-text-primary uppercase tracking-tight leading-none">Formulir Refund DP</h3>
                        <p class="text-[10px] font-bold text-text-secondary uppercase tracking-widest mt-1">Pengembalian Uang DP ke Customer</p>
                    </div>
                </div>
                <div class="hidden xs:block px-4 py-1.5 bg-red-100 dark:bg-red-900/30 text-red-600 rounded-full text-[10px] font-black uppercase tracking-widest">
                    Refund
                </div>
            </div>

            <!-- Warning Banner -->
            <div class="mb-8 p-4 bg-amber-50 dark:bg-amber-900/10 border-2 border-amber-200 dark:border-amber-800 rounded-2xl flex items-start gap-3">
                <AlertTriangle class="text-amber-500 shrink-0 mt-0.5" :size="20" />
                <div>
                    <p class="text-sm font-black text-amber-700 dark:text-amber-400">Perhatian: Refund DP bersifat permanen</p>
                    <p class="text-xs text-amber-600 dark:text-amber-500 mt-1">Setelah di-refund, transaksi DP ini tidak bisa dipulihkan. Uang DP akan dikembalikan sepenuhnya ke customer.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- DP Info (Read-only) -->
                <div class="space-y-6">
                    <h4 class="text-sm font-black text-red-600 uppercase tracking-widest border-b border-red-100 dark:border-red-900/30 pb-2">
                        Data DP yang Di-refund
                    </h4>
                    
                    <div class="bg-red-50 dark:bg-red-900/10 border-2 border-red-200 dark:border-red-800 rounded-xl p-5 flex flex-col gap-3">
                        <div class="flex justify-between items-center border-b border-red-200 dark:border-red-800/50 pb-2 mb-2">
                            <span class="text-xs font-black text-red-700 dark:text-red-500 uppercase">NOTA: {{ selectedDp?.receipt_id }}</span>
                            <span class="text-[10px] font-bold text-red-600">{{ getDpItemInfo(selectedDp).dpDate }}</span>
                        </div>
                        
                        <div class="flex justify-between text-xs">
                            <span class="text-red-700 dark:text-red-400 font-bold">Customer</span>
                            <span class="font-black text-red-900 dark:text-red-300 text-right">{{ selectedDp?.customer_name || selectedDp?.customer_wa || '-' }}</span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span class="text-red-700 dark:text-red-400 font-bold">No. HP / WA</span>
                            <span class="font-black text-red-900 dark:text-red-300 text-right">{{ selectedDp?.customer_wa || selectedDp?.customer_phone || '-' }}</span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span class="text-red-700 dark:text-red-400 font-bold">Model/Tipe</span>
                            <span class="font-black text-red-900 dark:text-red-300 text-right">{{ getDpItemInfo(selectedDp).brand }} - {{ getDpItemInfo(selectedDp).type }}</span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span class="text-red-700 dark:text-red-400 font-bold">Kapasitas</span>
                            <span class="font-black text-red-900 dark:text-red-300 text-right">{{ getDpItemInfo(selectedDp).gb }}</span>
                        </div>
                        
                        <div class="mt-4 pt-3 border-t border-red-200 dark:border-red-800/50">
                            <div class="flex justify-between text-base">
                                <span class="text-red-800 dark:text-red-400 font-black uppercase tracking-widest">NOMINAL REFUND</span>
                                <span class="font-black text-red-600 text-lg">{{ formatCurrency(dpAmount) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Refund Details -->
                <div class="space-y-6">
                    <h4 class="text-sm font-black text-primary-600 uppercase tracking-widest border-b border-primary-100 dark:border-primary-900/30 pb-2">
                        Detail Refund
                    </h4>
                    
                    <div>
                        <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Alasan Pembatalan DP <span class="text-red-500">*</span></label>
                        <textarea v-model="refundForm.reason" rows="3"
                            class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none text-sm"
                            placeholder="Kenapa DP dibatalkan? (Wajib diisi)"></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Keterangan Tambahan (Opsional)</label>
                        <textarea v-model="refundForm.notes" rows="2"
                            class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none text-sm"
                            placeholder="Catatan tambahan..."></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Foto Bukti Refund / Customer (Opsional)</label>
                        <div class="relative w-full">
                            <input type="file" ref="photoInput" accept="image/*" @change="handlePhotoUpload" class="hidden" id="refundPhotoUpload" />
                            <label for="refundPhotoUpload" 
                                class="w-full flex items-center justify-between border-2 border-dashed border-surface-300 dark:border-surface-600 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 cursor-pointer hover:bg-surface-100 dark:hover:bg-surface-800 transition-all">
                                <span class="text-sm text-surface-500 font-medium truncate pr-4">
                                    {{ refundForm.photo ? refundForm.photo.name : 'Upload Foto...' }}
                                </span>
                                <div class="shrink-0 flex items-center gap-2">
                                    <button v-if="refundForm.photo" @click.prevent="removePhoto" type="button" class="p-1 hover:bg-red-100 text-red-500 rounded-lg transition-colors">
                                        <X :size="16" />
                                    </button>
                                    <div class="p-2 bg-primary-100 text-primary-600 rounded-lg">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Method -->
            <div class="mt-8 space-y-6">
                <h4 class="text-sm font-black text-primary-600 uppercase tracking-widest border-b border-primary-100 dark:border-primary-900/30 pb-2">
                    Metode Pengembalian Dana
                </h4>

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest">Metode Pembayaran Refund <span class="text-red-500">*</span></label>
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

                    <!-- Split validation -->
                    <div v-if="isSplitInvalid && splitPayments.length > 0" class="mt-2 text-xs text-red-500 font-bold flex items-center gap-1">
                        <AlertTriangle :size="14" />
                        Total split ({{ formatCurrency(splitPayments.reduce((sum, p) => sum + (p.amount || 0), 0)) }}) harus sama dengan nominal refund ({{ formatCurrency(dpAmount) }})
                    </div>
                </div>
            </div>

            <!-- Submit Section -->
            <div class="mt-12 pt-8 border-t border-surface-100 dark:border-surface-700 flex flex-col sm:flex-row gap-4">
                <button @click="currentStep = 1"
                    class="flex-1 py-4 bg-surface-100 dark:bg-surface-700 text-text-primary rounded-2xl font-black uppercase tracking-widest hover:bg-surface-200 transition-all">
                    Kembali Pilih DP
                </button>
                <button @click="submitRefundDp()" :disabled="isSubmitting || isSplitInvalid || !refundForm.reason"
                    class="flex-[2] py-4 bg-red-600 hover:bg-red-500 disabled:opacity-50 disabled:bg-surface-300 dark:disabled:bg-surface-600 disabled:cursor-not-allowed text-white rounded-2xl font-black uppercase tracking-widest shadow-xl shadow-red-500/20 transition-all flex items-center justify-center gap-3 active:scale-[0.98]">
                    <Loader2 v-if="isSubmitting" class="animate-spin" :size="24" />
                    <template v-else>
                        <Undo2 :size="24" /> Proses Refund DP — {{ formatCurrency(dpAmount) }}
                    </template>
                </button>
            </div>
        </div>
    </div>
</template>
