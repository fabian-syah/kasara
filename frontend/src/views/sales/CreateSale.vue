<script setup>
import { ref, onMounted, watch } from "vue";
import api from "../../api/axios";
import { useEscapeKey } from "../../composables/useEscapeKey";
import { useCartStore } from "../../store/cart";
import { useInventoryStore } from "../../store/inventory";
import { useAuthStore } from "../../store/auth";

// UI Components
import {
    Plus,
    CheckCircle2,
    ArrowLeft,
    ArrowRight,
    Shield,
    DollarSign
} from "lucide-vue-next";
import PinModal from "../../components/modals/PinModal.vue";
import ReceiptModal from "../../components/modals/ReceiptModal.vue";

// Category Components
import AngkatBarangForm from "./categories/AngkatBarangForm.vue";
import RefundForm from "./categories/RefundForm.vue";
import TukarUnitForm from "./categories/TukarUnitForm.vue";
import TukarTambahForm from "./categories/TukarTambahForm.vue";
import DowngradeForm from "./categories/DowngradeForm.vue";
import PenjualanStep3 from "./categories/PenjualanStep3.vue";
import PaymentStep from "./categories/PaymentStep.vue";

const cartStore = useCartStore();
const inventoryStore = useInventoryStore();
const authStore = useAuthStore();

// Wizard Steps
const currentStep = ref(1); // 1: Account, 2: Category, 3: Items/Form, 4: Payment
const salesAccount = ref("");
const salesAccounts = ref([]);
const transactionCategory = ref("penjualan");

const categoriesPenjualan = [
    { id: "penjualan", label: "Penjualan" },
    { id: "angkat_barang", label: "Angkat Barang" },
    { id: "refund", label: "Refund" },
    { id: "tukar_unit", label: "Tukar Unit" },
    { id: "tukar_tambah", label: "Tukar Tambah" },
    { id: "downgrade", label: "Downgrade" },
];

const brands = ref([]);
const productTypes = ref([]);
const productPrices = ref([]);
const availablePaymentMethods = ref([]);

// Modals State
const showSuccessModal = ref(false);
const showReceiptModal = ref(false);
const lastTransaction = ref(null);
const showInitialPinSetup = ref(false);
const showPinModal = ref(false);
const pinModalMode = ref("verify");
const pinModalTitle = ref("Verifikasi PIN");
const pendingPinCallback = ref(null);

onMounted(async () => {
    try {
        const [hpRes, nonHpRes, accountsRes, userRes, paymentsRes, brandsRes, typesRes, pricesRes] = await Promise.all([
            api.get('/inventory', { params: { type: 'hp', status: 'available', per_page: 1000 } }),
            api.get('/inventory', { params: { type: 'non-hp', per_page: 1000 } }),
            api.get('/inventory/my-accounts'),
            api.get('/user'),
            api.get('/payment-methods'),
            api.get('/brands'),
            api.get('/product-types', { params: { per_page: 1000 } }),
            api.get('/product-prices', { params: { per_page: 1000 } })
        ]);

        // Process inventory
        const hpData = hpRes.data?.data || hpRes.data || [];
        const nonHpData = (nonHpRes.data?.data || nonHpRes.data || []).map(item => ({
            ...item,
            is_non_hp: true,
            selling_price: item.product?.selling_price || item.product?.price || 0,
            condition: 'new',
            distributor: { name: item.latest_distributor || item.latest_supplier || null }
        }));
        inventoryStore.products = [...hpData, ...nonHpData];

        // Process accounts
        const rawAccounts = accountsRes.data.data || accountsRes.data;
        salesAccounts.value = rawAccounts.filter(acc =>
            acc.roles && acc.roles.some(r => r.name === 'sales')
        );

        // Process Payment Methods
        availablePaymentMethods.value = (paymentsRes.data.data || paymentsRes.data).filter(p => p.is_active);

        // Process Brands & Types
        brands.value = brandsRes.data.data || brandsRes.data || [];
        productTypes.value = typesRes.data.data || typesRes.data || [];
        productPrices.value = pricesRes.data.data || pricesRes.data || [];

        // Auto-select user account
        const userData = userRes.data.data || userRes.data;
        if (userData) {
            if (userData.roles?.some(r => r.name === 'sales') && !userData.transaction_pin) {
                showInitialPinSetup.value = true;
            }
            const match = salesAccounts.value.find(acc => acc.name === userData.name || acc.id === userData.id);
            if (match) salesAccount.value = match.name;
        }
    } catch (e) {
        console.error("Gagal memuat data awal", e);
    }
});

function nextStep() {
    if (currentStep.value === 1 && !salesAccount.value) {
        alert("Silakan pilih Akun Sales terlebih dahulu.");
        return;
    }
    if (currentStep.value < 4) currentStep.value++;
}

function prevStep() {
    if (currentStep.value > 1) currentStep.value--;
}

function handleTransactionComplete(transaction) {
    lastTransaction.value = transaction;
    showSuccessModal.value = true;
    cartStore.clearCart();
    currentStep.value = 1;
}

function handleVerifyPin(callback) {
    pendingPinCallback.value = callback;
    showPinModal.value = true;
    pinModalMode.value = "verify";
    pinModalTitle.value = "Verifikasi PIN Transaksi";
}

function handlePinSuccess(pin) {
    showPinModal.value = false;
    if (pendingPinCallback.value) {
        pendingPinCallback.value(pin);
        pendingPinCallback.value = null;
    }
}

function closeSuccessModal() {
    showSuccessModal.value = false;
    lastTransaction.value = null;
}

useEscapeKey(() => {
    if (showSuccessModal.value) closeSuccessModal();
});

// Watch for category changes to potentially skip steps or reset
watch(transactionCategory, () => {
    if (currentStep.value > 2) currentStep.value = 3;
    cartStore.clearCart();
});
</script>

<template>
    <div class="min-h-screen bg-surface-50 dark:bg-surface-950 p-4 sm:p-6 lg:p-8 font-jakarta">
        <div class="max-w-[1600px] mx-auto h-[calc(100vh-4rem)] flex flex-col">

            <!-- HEADER & LOGO -->
            <div class="flex items-center justify-between mb-8 shrink-0">
                <div class="flex items-center gap-4">
                    <div
                        class="w-12 h-12 bg-primary-600 rounded-2xl flex items-center justify-center shadow-lg shadow-primary-500/20">
                        <ShoppingBag class="text-white" :size="28" stroke-width="2.5" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-black text-text-primary tracking-tight">Buat Penjualan</h1>
                        <p class="text-sm text-text-secondary font-bold uppercase tracking-widest">Sistem Kasir v2.0</p>
                    </div>
                </div>
            </div>

            <!-- STEP WIZARD NAV -->
            <div
                class="bg-white dark:bg-surface-800 rounded-2xl p-2 mb-8 border border-surface-200 dark:border-surface-700 shadow-sm shrink-0 overflow-x-auto custom-scrollbar">
                <div class="flex items-center justify-between relative px-4 min-w-[300px]">
                    <div v-for="step in 4" :key="step" class="z-10 flex flex-col items-center gap-2 py-2">
                        <div @click="step < currentStep ? currentStep = step : null"
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-full flex items-center justify-center font-black transition-all cursor-pointer text-xs sm:text-sm"
                            :class="currentStep === step ? 'bg-primary-600 text-white shadow-lg ring-4 ring-primary-500/20' : step < currentStep ? 'bg-emerald-500 text-white' : 'bg-surface-100 dark:bg-surface-900 text-text-secondary opacity-50'">
                            <CheckCircle2 v-if="step < currentStep" :size="16" class="sm:w-5 sm:h-5" />
                            <span v-else>{{ step }}</span>
                        </div>
                        <span class="text-[9px] sm:text-[10px] font-black uppercase tracking-widest hidden xs:block"
                            :class="currentStep === step ? 'text-primary-600' : 'text-text-secondary opacity-50'">
                            {{ ['Akun', 'Kategori', 'Item', 'Bayar'][step - 1] }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- STEP 1: PILIH AKUN -->
            <div v-if="currentStep === 1" class="flex-1 flex items-center justify-center animate-fade-in">
                <div
                    class="w-full max-w-2xl bg-white dark:bg-surface-800 rounded-[2.5rem] border border-surface-200 dark:border-surface-700 p-8 sm:p-12 shadow-2xl relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-64 h-64 bg-primary-500/5 rounded-full -mr-32 -mt-32 blur-3xl">
                    </div>
                    <div class="relative z-10">
                        <div
                            class="w-20 h-20 bg-primary-50 dark:bg-primary-900/20 rounded-3xl flex items-center justify-center mb-8 mx-auto ring-8 ring-primary-500/5">
                            <Shield class="text-primary-600" :size="40" stroke-width="2.5" />
                        </div>
                        <h2 class="text-3xl font-black text-text-primary text-center mb-4">Pilih Akun Kasir</h2>
                        <p class="text-text-secondary text-center mb-10 font-medium">Silakan pilih akun sales yang
                            bertugas untuk transaksi ini.</p>

                        <div class="space-y-4">
                            <label
                                class="block text-xs font-black text-text-secondary uppercase tracking-widest px-1">Nama
                                Sales / Admin</label>
                            <div class="grid grid-cols-1 gap-3 max-h-[300px] overflow-y-auto pr-2 custom-scrollbar">
                                <button v-for="acc in salesAccounts" :key="acc.id" @click="salesAccount = acc.name"
                                    class="w-full p-5 rounded-2xl border-2 transition-all flex items-center justify-between group"
                                    :class="salesAccount === acc.name ? 'border-primary-600 bg-primary-50 dark:bg-primary-900/20' : 'border-surface-100 dark:border-surface-700 bg-surface-50 dark:bg-surface-900 hover:border-surface-300'">
                                    <span class="font-black text-lg transition-colors"
                                        :class="salesAccount === acc.name ? 'text-primary-600' : 'text-text-primary'">{{
                                            acc.name }}</span>
                                    <div class="w-6 h-6 rounded-full border-2 flex items-center justify-center transition-all"
                                        :class="salesAccount === acc.name ? 'border-primary-600 bg-primary-600' : 'border-surface-300 bg-white dark:bg-surface-800'">
                                        <div v-if="salesAccount === acc.name" class="w-2 h-2 rounded-full bg-white">
                                        </div>
                                    </div>
                                </button>
                            </div>
                        </div>

                        <button @click="nextStep" :disabled="!salesAccount"
                            class="w-full mt-10 h-16 bg-primary-600 hover:bg-primary-500 disabled:opacity-50 text-white rounded-2xl font-black text-lg shadow-xl shadow-primary-500/30 transition-all flex items-center justify-center gap-3">
                            Lanjutkan
                            <ArrowRight :size="24" stroke-width="3" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- STEP 2: CATEGORY SELECTION -->
            <div v-if="currentStep === 2" class="flex-1 flex items-center justify-center animate-fade-in py-8">
                <div class="w-full max-w-5xl grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
                    <button v-for="cat in categoriesPenjualan" :key="cat.id"
                        @click="transactionCategory = cat.id; nextStep()"
                        class="p-6 sm:p-8 bg-white dark:bg-surface-800 rounded-[1.5rem] sm:rounded-[2rem] border-2 border-surface-100 dark:border-surface-700 hover:border-primary-500 hover:shadow-2xl hover:shadow-primary-500/10 transition-all group relative overflow-hidden text-left flex flex-col gap-3 sm:gap-4 active:scale-95">
                        <div
                            class="w-10 h-10 sm:w-14 sm:h-14 bg-surface-50 dark:bg-surface-900 rounded-xl sm:rounded-2xl flex items-center justify-center group-hover:bg-primary-600 group-hover:text-white transition-all">
                            <Plus v-if="cat.id === 'penjualan'" :size="20" class="sm:w-8 sm:h-8" />
                            <DollarSign v-else :size="20" class="sm:w-8 sm:h-8" />
                        </div>
                        <h3
                            class="text-sm sm:text-xl font-black text-text-primary group-hover:text-primary-600 transition-colors uppercase tracking-tight">
                            {{ cat.label }}</h3>
                    </button>
                </div>
            </div>

            <!-- STEP 3: TRANSACTION COMPONENTS -->
            <div v-if="currentStep === 3" class="flex-1 flex flex-col min-h-0">
                <PenjualanStep3 v-if="transactionCategory === 'penjualan'" :transactionCategory="transactionCategory"
                    :availablePaymentMethods="availablePaymentMethods" @prev="prevStep" @next="currentStep = 4" />

                <AngkatBarangForm v-else-if="transactionCategory === 'angkat_barang'"
                    :availablePaymentMethods="availablePaymentMethods" :brands="brands" :productTypes="productTypes"
                    :productPrices="productPrices" @back="prevStep" @transaction-complete="handleTransactionComplete"
                    @verify-pin="handleVerifyPin" />

                <RefundForm v-else-if="transactionCategory === 'refund'"
                    :availablePaymentMethods="availablePaymentMethods" :brands="brands" :productTypes="productTypes"
                    @back="prevStep" @transaction-complete="handleTransactionComplete" @verify-pin="handleVerifyPin" />

                <TukarUnitForm v-else-if="transactionCategory === 'tukar_unit'"
                    :availablePaymentMethods="availablePaymentMethods" :brands="brands" :productTypes="productTypes"
                    @back="prevStep" @transaction-complete="handleTransactionComplete" @verify-pin="handleVerifyPin" />

                <TukarTambahForm v-else-if="transactionCategory === 'tukar_tambah'"
                    :availablePaymentMethods="availablePaymentMethods" :brands="brands" :productTypes="productTypes"
                    @back="prevStep" @transaction-complete="handleTransactionComplete" @verify-pin="handleVerifyPin" />

                <DowngradeForm v-else-if="transactionCategory === 'downgrade'"
                    :availablePaymentMethods="availablePaymentMethods" :brands="brands" :productTypes="productTypes"
                    @back="prevStep" @transaction-complete="handleTransactionComplete" @verify-pin="handleVerifyPin" />
            </div>

            <!-- STEP 4: PAYMENT -->
            <PaymentStep v-if="currentStep === 4" :availablePaymentMethods="availablePaymentMethods"
                :transactionCategory="transactionCategory" :salesAccount="salesAccount" @prev="prevStep"
                @transaction-complete="handleTransactionComplete" @verify-pin="handleVerifyPin" />

        </div>

        <!-- SHARED MODALS -->
        <PinModal :show="showPinModal" :title="pinModalTitle" :mode="pinModalMode" @close="showPinModal = false"
            @success="handlePinSuccess" />
        <ReceiptModal v-if="showSuccessModal" :show="showSuccessModal" :transaction="lastTransaction"
            @close="closeSuccessModal" />

    </div>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@200;300;400;500;600;700;800&display=swap');

.font-jakarta {
    font-family: 'Plus Jakarta Sans', sans-serif;
}

.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #e2e8f0;
    border-radius: 10px;
}

.dark .custom-scrollbar::-webkit-scrollbar-thumb {
    background: #334155;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #cbd5e1;
}

.animate-fade-in {
    animation: fadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px) scale(0.98);
    }

    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}
</style>
