<script setup>
import { ref, computed, onMounted } from "vue";
import api from "../../../api/axios";
import { formatCurrency } from "../../../utils/formatters";
import { ArrowLeft, CheckCircle, Search, Loader2, ArrowRight } from "lucide-vue-next";
import { useAuthStore } from "../../../store/auth";
import PaymentStep from "./PaymentStep.vue";

const props = defineProps({
    transactionCategory: String,
    availablePaymentMethods: Array,
    salesAccount: String,
    selectedAccountObject: Object
});

const emit = defineEmits(["back", "transaction-complete", "verify-pin"]);
const authStore = useAuthStore();

const activeDps = ref([]);
const isLoadingDps = ref(false);
const searchQuery = ref("");
const selectedDp = ref(null);
const currentStep = ref(1); // 1 = Select DP, 2 = Payment

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

function continueToPayment() {
    if (!selectedDp.value) return;
    currentStep.value = 2;
}

// Map the selected DP to a "mock" cart for PaymentStep
const paymentCartTotal = computed(() => {
    if (!selectedDp.value) return 0;
    // For Pelunasan DP, we calculate the remaining balance
    const totalSellingPrice = Number(selectedDp.value.selling_price || 0);
    const dpPaid = Number(selectedDp.value.dp_amount || 0);
    return Math.max(0, totalSellingPrice - dpPaid);
});

// Mock Cart Store for PaymentStep
import { useCartStore } from "../../../store/cart";
const cartStore = useCartStore();

function handleProceedToPayment() {
    // Clear current cart and add the DP items
    cartStore.clearCart();
    
    // Add a single "Pelunasan DP" item to the cart so PaymentStep shows it correctly
    // Since the actual HP was already deducted, this is just for the Receipt/UI
    const remainingBalance = paymentCartTotal.value;
    
    let brand = "-";
    let type = "-";
    let gb = "-";
    
    if (selectedDp.value.items && selectedDp.value.items.length > 0) {
        const firstItem = selectedDp.value.items[0];
        brand = firstItem.product?.brand?.name || firstItem.product?.brand || firstItem.brand || "-";
        type = firstItem.product?.name || firstItem.name || "-";
        gb = firstItem.storage || "-";
    } else if (selectedDp.value.nonHpDetails && selectedDp.value.nonHpDetails.length > 0) {
        const firstItem = selectedDp.value.nonHpDetails[0];
        type = firstItem.product?.name || firstItem.name || "-";
    }

    let dpDate = selectedDp.value.created_at;
    if (dpDate) {
        const d = new Date(dpDate);
        dpDate = d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
    } else {
        dpDate = "-";
    }
    
    const mockItem = {
        id: 'pelunasan_' + selectedDp.value.id,
        name: `Pelunasan Nota: ${selectedDp.value.receipt_id}`,
        price: remainingBalance,
        quantity: 1,
        is_hp: false,
        discount: 0,
        dp_info: {
            customer_name: selectedDp.value.customer_name,
            dp_date: dpDate,
            brand: brand,
            type: type,
            gb: gb,
            dp_amount: selectedDp.value.dp_amount
        }
    };
    
    cartStore.addItem(mockItem);
    currentStep.value = 2;
}

function handleTransactionComplete(transactionData) {
    // Inject parent_dp_id before emitting
    const enrichedData = {
        ...transactionData,
        parent_dp_id: selectedDp.value.id
    };
    
    // Override the API call from PaymentStep if needed, but wait!
    // PaymentStep makes the API call itself. We need to pass parent_dp_id to PaymentStep.
    emit('transaction-complete', enrichedData);
}

</script>

<template>
    <div v-if="currentStep === 1" class="w-full flex flex-col gap-4 sm:gap-8 items-start relative min-h-0">
        <!-- Header -->
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
            <div v-else-if="filteredDps.length === 0" class="py-12 text-center text-surface-400">
                Belum ada nota DP yang aktif atau ditemukan.
            </div>
            <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div v-for="dp in filteredDps" :key="dp.id" 
                    @click="selectDp(dp)"
                    class="p-4 rounded-xl border-2 cursor-pointer transition-all flex flex-col gap-2"
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
                <button @click="handleProceedToPayment" :disabled="!selectedDp"
                    class="px-8 py-4 bg-primary-600 hover:bg-primary-500 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-2xl font-black text-lg shadow-xl shadow-primary-500/20 transition-all flex items-center gap-2">
                    Lanjutkan Pelunasan <ArrowRight :size="20" />
                </button>
            </div>
        </div>
    </div>

    <!-- STEP 2: PAYMENT -->
    <div v-else-if="currentStep === 2" class="w-full flex-1 flex flex-col min-h-0 relative">
        <PaymentStep :availablePaymentMethods="availablePaymentMethods"
            transactionCategory="pelunasan_dp" :salesAccount="salesAccount"
            :selectedAccountObject="selectedAccountObject" @prev="currentStep = 1"
            @transaction-complete="handleTransactionComplete" @verify-pin="$emit('verify-pin', $event)" />
    </div>
</template>
