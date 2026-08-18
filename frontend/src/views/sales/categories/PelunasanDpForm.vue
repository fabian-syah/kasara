<script setup>
import { ref, computed, onMounted, watch } from "vue";
import api from "../../../api/axios";
import { formatCurrency } from "../../../utils/formatters";
import { ArrowLeft, CheckCircle, Search, Loader2, ArrowRight } from "lucide-vue-next";
import { useAuthStore } from "../../../store/auth";
import { useCartStore } from "../../../store/cart";
import { useInventoryStore } from "../../../store/inventory";
import PaymentStep from "./PaymentStep.vue";
import PenjualanStep3 from "./PenjualanStep3.vue";

const props = defineProps({
    transactionCategory: String,
    availablePaymentMethods: Array,
    salesAccount: String,
    selectedAccountObject: Object
});

const emit = defineEmits(["back", "transaction-complete", "verify-pin"]);
const authStore = useAuthStore();
const cartStore = useCartStore();
const inventoryStore = useInventoryStore();

const activeDps = ref([]);
const isLoadingDps = ref(false);
const searchQuery = ref("");
const selectedDp = ref(null);
const currentStep = ref(1); // 1 = Select DP, 2 = Select Items, 3 = Payment

onMounted(async () => {
    fetchActiveDps();
});

async function fetchActiveDps() {
    try {
        isLoadingDps.value = true;
        const response = await api.get('/stock-outs/active-dps');
        activeDps.value = response.data.data || [];
    } catch (error) {
        console.error("Gagal mengambil data DP", error);
    } finally {
        isLoadingDps.value = false;
    }
}

const filteredDps = computed(() => {
    let dps = activeDps.value;
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        dps = dps.filter(dp => 
            (dp.receipt_id && dp.receipt_id.toLowerCase().includes(q)) ||
            (dp.customer_name && dp.customer_name.toLowerCase().includes(q)) ||
            (dp.customer_phone && dp.customer_phone.toLowerCase().includes(q))
        );
    }
    return dps;
});

function selectDp(dp) {
    selectedDp.value = dp;
}

function getDpItemInfo(dp) {
    let brand = "-";
    let type = "-";
    let gb = "-";
    
    if (dp.items && dp.items.length > 0) {
        const firstItem = dp.items[0];
        brand = firstItem.product?.brand?.name || firstItem.product?.brand || firstItem.brand || "-";
        type = firstItem.product?.name || firstItem.name || "-";
        gb = firstItem.storage || "-";
    } else if (dp.nonHpDetails && dp.nonHpDetails.length > 0) {
        const firstItem = dp.nonHpDetails[0];
        type = firstItem.product?.name || firstItem.name || "-";
    } else if (dp.non_hp_details && dp.non_hp_details.length > 0) {
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

// DP amount already paid
const dpAmount = computed(() => {
    if (!selectedDp.value) return 0;
    return Number(selectedDp.value.dp_amount || 0);
});

function handleProceedToItemSelection() {
    if (!selectedDp.value) return;
    // Clear cart before item selection
    cartStore.clearCart();
    currentStep.value = 2;
}

function handleItemsNext() {
    // User finished selecting items, proceed to payment
    currentStep.value = 3;
}

function handleItemsPrev() {
    // Go back to DP selection
    currentStep.value = 1;
}

function handleTransactionComplete(transactionData) {
    // Inject parent_dp_id and DP info before emitting
    const enrichedData = {
        ...transactionData,
        parent_dp_id: selectedDp.value.id,
        dp_deduction: dpAmount.value,
        original_dp_receipt: selectedDp.value.receipt_id
    };
    
    emit('transaction-complete', enrichedData);
}

</script>

<template>
    <!-- STEP 1: SELECT DP NOTA -->
    <div v-if="currentStep === 1" class="w-full flex flex-col gap-4 sm:gap-8 items-start relative min-h-0">
        <!-- Header -->
        <div class="w-full flex items-center justify-between bg-white dark:bg-surface-800 p-4 rounded-2xl border border-surface-200 dark:border-surface-700 shadow-sm mb-2">
            <div class="flex items-center gap-3">
                <button @click="emit('back')" class="p-2 -ml-2 hover:bg-surface-100 dark:hover:bg-surface-700 rounded-full text-primary-600 transition-colors">
                    <ArrowLeft :size="28" stroke-width="3" />
                </button>
                <div class="flex flex-col">
                    <h3 class="text-lg sm:text-xl font-black text-text-primary uppercase tracking-tight leading-none">Pelunasan DP</h3>
                    <p class="text-[10px] font-bold text-text-secondary uppercase tracking-widest mt-1">Step 1 — Pilih Nota DP Customer</p>
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
            <div v-else-if="filteredDps.length === 0" class="py-12 text-center text-surface-400">
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

                    <!-- Additional Details -->
                    <div class="flex flex-col mt-2 pt-2 border-t border-surface-200 dark:border-surface-700 text-xs text-text-secondary space-y-1">
                        <div class="flex justify-between"><span class="font-bold">Tanggal DP:</span> <span class="font-bold text-text-primary">{{ getDpItemInfo(dp).dpDate }}</span></div>
                        <div class="flex justify-between"><span class="font-bold">Brand:</span> <span class="font-bold text-text-primary">{{ getDpItemInfo(dp).brand }}</span></div>
                        <div class="flex justify-between"><span class="font-bold">Tipe:</span> <span class="font-bold text-text-primary">{{ getDpItemInfo(dp).type }}</span></div>
                        <div class="flex justify-between"><span class="font-bold">Penyimpanan (GB):</span> <span class="font-bold text-text-primary">{{ getDpItemInfo(dp).gb }}</span></div>
                    </div>

                    <div class="flex flex-col mt-2 pt-2 border-t border-surface-200 dark:border-surface-700">
                        <div class="flex justify-between text-xs mb-1">
                            <span class="text-text-secondary font-bold">Total Harga</span>
                            <span class="font-black">{{ formatCurrency(dp.selling_price) }}</span>
                        </div>
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
                <button @click="handleProceedToItemSelection" :disabled="!selectedDp"
                    class="px-8 py-4 bg-primary-600 hover:bg-primary-500 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-2xl font-black text-lg shadow-xl shadow-primary-500/20 transition-all flex items-center gap-2">
                    Pilih Unit / Item <ArrowRight :size="20" />
                </button>
            </div>
        </div>
    </div>

    <!-- STEP 2: SELECT ITEMS (Like Penjualan Store) -->
    <div v-else-if="currentStep === 2" class="w-full flex flex-col gap-4 min-h-0">
        <!-- DP Info Banner -->
        <div class="w-full bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-500/20 rounded-2xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex flex-col gap-1">
                <p class="text-xs font-black text-amber-700 dark:text-amber-400 uppercase tracking-widest">Pelunasan Nota DP: {{ selectedDp?.receipt_id }}</p>
                <p class="text-sm font-bold text-amber-600 dark:text-amber-500">
                    {{ selectedDp?.customer_name }} — DP Dibayar: <span class="font-black">{{ formatCurrency(dpAmount) }}</span>
                </p>
            </div>
            <div class="flex flex-col items-end">
                <p class="text-[10px] font-bold text-amber-600 uppercase tracking-widest">Sisa Lunas</p>
                <p class="text-xl font-black text-red-500">{{ formatCurrency((selectedDp?.selling_price || 0) - dpAmount) }}</p>
            </div>
        </div>

        <!-- Reuse PenjualanStep3 for item selection -->
        <PenjualanStep3 
            transactionCategory="pelunasan_dp"
            :availablePaymentMethods="availablePaymentMethods" 
            @prev="handleItemsPrev" 
            @next="handleItemsNext" />
    </div>

    <!-- STEP 3: PAYMENT -->
    <div v-else-if="currentStep === 3" class="w-full flex-1 flex flex-col min-h-0 relative">
        <!-- DP Deduction Info -->
        <div class="w-full bg-emerald-50 dark:bg-emerald-900/10 border border-emerald-200 dark:border-emerald-500/20 rounded-2xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
            <div class="flex flex-col gap-1">
                <p class="text-xs font-black text-emerald-700 dark:text-emerald-400 uppercase tracking-widest">Pelunasan Nota: {{ selectedDp?.receipt_id }}</p>
                <p class="text-sm font-bold text-emerald-600">{{ selectedDp?.customer_name }}</p>
            </div>
            <div class="flex flex-col items-end">
                <p class="text-[10px] font-bold text-emerald-600 uppercase tracking-widest">DP Sudah Dibayar</p>
                <p class="text-xl font-black text-emerald-600">- {{ formatCurrency(dpAmount) }}</p>
            </div>
        </div>

        <PaymentStep :availablePaymentMethods="availablePaymentMethods"
            transactionCategory="pelunasan_dp" :salesAccount="salesAccount"
            :selectedAccountObject="selectedAccountObject" 
            :dpDeduction="dpAmount"
            :parentDpId="selectedDp?.id"
            @prev="currentStep = 2"
            @transaction-complete="handleTransactionComplete" @verify-pin="$emit('verify-pin', $event)" />
    </div>
</template>
