<script setup>
import { ref, onMounted, watch, computed } from "vue";
import api from "../../api/axios";
import { useEscapeKey } from "../../composables/useEscapeKey";
import { useToast } from "../../composables/useToast";
import { useCartStore } from "../../store/cart";
import { useInventoryStore } from "../../store/inventory";
import { useAuthStore } from "../../store/auth";

// UI Components
import {
    CheckCircle2,
    ArrowLeft,
    ArrowRight,
    Shield,
    DollarSign,
    ShoppingCart,
    PackageOpen,
    TrendingUp,
    TrendingDown,
    RotateCcw,
    RefreshCw,
    ShoppingBag,
    Plus,
    UserPlus,
    Loader2
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
const toast = useToast();

// Wizard Steps
const currentStep = ref(1); // 1: Account, 2: Category, 3: Items/Form, 4: Payment
const salesAccount = ref("");
const salesAccounts = ref([]);
const transactionCategory = ref("penjualan_store");

const categoriesPenjualan = [
    { id: "penjualan_store", label: "Penjualan Store", icon: 'ShoppingCart' },
    { id: "angkat_barang", label: "Angkat Barang", icon: 'PackageOpen' },
    { id: "refund", label: "Refund", icon: 'RotateCcw' },
    { id: "tukar_unit", label: "Tukar Unit", icon: 'RefreshCw' },
    { id: "tukar_tambah", label: "Tukar Tambah", icon: 'TrendingUp' },
    { id: "downgrade", label: "Downgrade", icon: 'TrendingDown' },
];

// Persistence Logic
const getWizardStorageKey = () => `temp_sale_state_${authStore.user?.id || 'guest'}`;
const getCategoryStorageKey = (base) => {
    const userId = authStore.user?.id || 'guest';
    const acc = salesAccount.value ? `_acc_${salesAccount.value.replace(/\s+/g, '_')}` : '';
    return `${base}_${userId}${acc}`;
};

watch([currentStep, salesAccount, transactionCategory], ([step, acc, cat]) => {
    localStorage.setItem(getWizardStorageKey(), JSON.stringify({
        currentStep: step,
        salesAccount: acc,
        transactionCategory: cat
    }));
}, { deep: true });

const brands = ref([]);
const productTypes = ref([]);
const productPrices = ref([]);
const distributors = ref([]);

const selectedAccountObject = computed(() => {
    return salesAccounts.value.find(acc => acc.name === salesAccount.value) || null;
});
const availablePaymentMethods = ref([]);

function clearAllTempStates() {
    const userId = authStore.user?.id || 'guest';
    const acc = salesAccount.value ? `_acc_${salesAccount.value.replace(/\s+/g, '_')}` : '';
    const keys = [
        'temp_sale_state',
        'temp_cart_state',
        'temp_tukar_unit_form',
        'temp_tukar_tambah_form',
        'temp_downgrade_form',
        'temp_refund_form',
        'temp_angkat_barang_form'
    ];
    keys.forEach(k => {
        localStorage.removeItem(`${k}_${userId}`);
        localStorage.removeItem(`${k}_${userId}${acc}`);
    });
}

// Modals State
const showSuccessModal = ref(false);
const showReceiptModal = ref(false);
const lastTransaction = ref(null);
const showInitialPinSetup = ref(false);
const showPinModal = ref(false);
const pinModalMode = ref("verify");
const pinModalTitle = ref("Verifikasi PIN");
const pendingPinCallback = ref(null);
const showCreateAccount = ref(false);
const newAccountName = ref("");
const newAccountPin = ref("");
const loadingCreate = ref(false);
const loadingStep3Data = ref(false);
const isDataLoaded = ref(false);

async function refreshAccounts() {
    try {
        const [accRes, usersRes] = await Promise.all([
            api.get('/inventory/my-accounts'),
            api.get('/users', { params: { role: 'inventory' } })
        ]);
        
        const rawAccounts = accRes.data.data || accRes.data;
        const allInventoryUsers = usersRes.data.data || usersRes.data || [];
        
        // Trust backend for data and photos
        salesAccounts.value = rawAccounts.map(acc => ({
            ...acc,
            photo: acc.photo || acc.photo_inventory || null
        }));
    } catch (e) {
        console.error("Gagal refresh akun", e);
    }
}

async function handleCreateAccount() {
    if (!newAccountName.value.trim()) {
        toast.error("Nama akun harus diisi.");
        return;
    }

    loadingCreate.value = true;
    try {
        const res = await api.post('/inventory/account', {
            name: newAccountName.value,
            transaction_pin: newAccountPin.value
        });

        if (res.data.success) {
            toast.success("Akun inventory berhasil dibuat!");
            await refreshAccounts();
            salesAccount.value = res.data.data.name;
            showCreateAccount.value = false;
            newAccountName.value = "";
            newAccountPin.value = "";
        }
    } catch (e) {
        toast.error(e.response?.data?.message || "Gagal membuat akun inventory.");
    } finally {
        loadingCreate.value = false;
    }
}
async function fetchHeavyData() {
    if (isDataLoaded.value) return;
    loadingStep3Data.value = true;
    try {
        const [hpRes, nonHpRes, paymentsRes, brandsRes, typesRes, pricesRes, distRes] = await Promise.all([
            api.get('/inventory', { params: { type: 'hp', status: 'available', per_page: -1 } }),
            api.get('/inventory', { params: { type: 'non-hp', per_page: -1 } }),
            api.get('/payment-methods'),
            api.get('/brands'),
            api.get('/product-types', { params: { per_page: 1000 } }),
            api.get('/product-prices', { params: { per_page: 1000 } }),
            api.get('/distributors')
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

        // Process Payment Methods
        availablePaymentMethods.value = (paymentsRes.data.data || paymentsRes.data).filter(p => p.is_active);

        // Process Brands & Types
        brands.value = brandsRes.data.data || brandsRes.data || [];
        productTypes.value = typesRes.data.data || typesRes.data || [];
        productPrices.value = pricesRes.data.data || pricesRes.data || [];
        distributors.value = distRes.data.data || distRes.data || [];
        
        isDataLoaded.value = true;
    } catch (e) {
        console.error("Gagal memuat data transaksi", e);
    } finally {
        loadingStep3Data.value = false;
    }
}

onMounted(async () => {
    // Restore Main Wizard State (Account, Step, Category)
    const savedState = localStorage.getItem(getWizardStorageKey());
    if (savedState) {
        try {
            const data = JSON.parse(savedState);
            currentStep.value = data.currentStep || 1;
            salesAccount.value = data.salesAccount || "";
            transactionCategory.value = data.transactionCategory || "";
        } catch (e) {}
    }

    try {
        // Phase 1: Essential data for Step 1
        const [accountsRes, usersRes, userRes] = await Promise.all([
            api.get('/inventory/my-accounts'),
            api.get('/users', { params: { role: 'inventory' } }),
            api.get('/user')
        ]);

        // Process accounts with merged user data for photos
        const rawAccounts = accountsRes.data.data || accountsRes.data;
        const allInventoryUsers = usersRes.data.data || usersRes.data || [];
        
        // Trust backend for data and photos
        salesAccounts.value = rawAccounts.map(acc => ({
            ...acc,
            photo: acc.photo || acc.photo_inventory || null
        }));

        // Auto-select user account
        const userData = userRes.data.data || userRes.data;
        if (userData) {
            if (userData.roles?.some(r => r.name === 'toko_offline') && !userData.transaction_pin) {
                showInitialPinSetup.value = true;
            }
            const match = salesAccounts.value.find(acc => acc.name === userData.name || acc.id === userData.id);
            if (match && !salesAccount.value) salesAccount.value = match.name;
        }

        // If we restored to step 3 or 4, fetch heavy data
        if (currentStep.value >= 3) {
            fetchHeavyData();
        }

        // Background Phase 2: Start fetching heavy data in background
        fetchHeavyData();
    } catch (e) {
        console.error("Gagal memuat data awal", e);
    }
});

function nextStep() {
    if (currentStep.value === 1 && !salesAccount.value) {
        alert("Silakan pilih Akun Sales terlebih dahulu.");
        return;
    }
    
    // Safety check: ensure heavy data is fetched before moving to Step 3
    if (currentStep.value === 2 && !isDataLoaded.value && !loadingStep3Data.value) {
        fetchHeavyData();
    }
    
    if (currentStep.value < 4) currentStep.value++;
}

function prevStep() {
    if (currentStep.value > 1) currentStep.value--;
}

const categoryLabels = {
    penjualan_store: 'Transaksi Penjualan Store berhasil! 🎉',
    cancel_penjualan: 'Cancel Penjualan berhasil diproses! 🚫',
    angkat_barang: 'Angkat Barang berhasil diproses! 📦',
    refund: 'Refund berhasil diproses! 🔄',
    tukar_unit: 'Tukar Unit berhasil! 🔁',
    tukar_tambah: 'Tukar Tambah berhasil! 📈',
    downgrade: 'Downgrade berhasil diproses! 📉',
};

function handleTransactionComplete(transaction) {
    lastTransaction.value = transaction;
    showSuccessModal.value = true;
    cartStore.clearCart();
    clearAllTempStates();

    const category = transaction?.category || transactionCategory.value;
    toast.success(categoryLabels[category] || 'Transaksi berhasil! ✅', 4000);

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

// Computed & Helpers for Account Photo
const storageBaseUrl = computed(() => authStore.storageBaseUrl);

function getInitials(name) {
    if (!name) return '?';
    const words = name.trim().split(/\s+/);
    if (words.length > 1) return (words[0][0] + words[1][0]).toUpperCase();
    return name.substring(0, 2).toUpperCase();
}

function getPhotoUrl(acc) {
    const p = acc?.photo || acc?.photo_inventory;
    if (!p) return null;
    return p.startsWith('http') ? p : `${storageBaseUrl.value}/storage/${p}`;
}

// Watch for category changes to potentially skip steps or reset
watch(transactionCategory, () => {
    if (currentStep.value > 2) currentStep.value = 3;
    cartStore.clearCart();
});
</script>
<template>
    <div class="font-jakarta bg-transparent">
        <div class="w-full flex flex-col">

            <!-- HEADER & LOGO -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4 sm:mb-8 shrink-0">
                <div class="flex items-center gap-3 sm:gap-4">
                    <div
                        class="w-10 h-10 sm:w-16 sm:h-16 bg-primary-600 rounded-xl sm:rounded-2xl flex items-center justify-center shadow-lg shadow-primary-500/20">
                        <ShoppingBag class="text-white w-6 h-6 sm:w-8 sm:h-8" :size="24" stroke-width="3" />
                    </div>
                    <div>
                        <h1 class="text-xl sm:text-4xl font-black text-text-primary tracking-tight">Buat Penjualan</h1>
                        <p class="text-[10px] sm:text-sm font-bold text-text-secondary uppercase tracking-widest">
                            Sistem Kasir v2.0</p>
                    </div>
                </div>
            </div>

            <!-- STEP WIZARD NAV -->
            <div
                class="bg-white dark:bg-surface-800 rounded-2xl p-2 mb-4 sm:mb-8 border border-surface-200 dark:border-surface-700 shadow-sm shrink-0 overflow-x-auto custom-scrollbar">
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

            <div v-if="currentStep === 1" class="flex items-center justify-center p-2 sm:p-0">
                <div
                    class="w-full max-w-2xl bg-white dark:bg-surface-800 rounded-[2rem] sm:rounded-[2.5rem] border border-surface-200 dark:border-surface-700 p-6 sm:p-12 shadow-2xl relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-64 h-64 bg-primary-500/5 rounded-full -mr-32 -mt-32 blur-3xl">
                    </div>
                    <div class="relative z-10">
                        <div
                            class="w-20 h-20 bg-primary-50 dark:bg-primary-900/20 rounded-3xl flex items-center justify-center mb-8 mx-auto ring-8 ring-primary-500/5">
                            <Shield class="text-primary-600" :size="40" stroke-width="2.5" />
                        </div>
                        <h2 class="text-3xl font-black text-text-primary text-center mb-4">Pilih Akun Inventory</h2>
                        <p class="text-text-secondary text-center mb-10 font-medium">Silakan pilih akun inventory yang
                            bertugas untuk transaksi ini.</p>

                        <div class="space-y-4">
                            <div class="flex items-center justify-between px-1">
                                <label class="block text-xs font-black text-text-secondary uppercase tracking-widest">
                                    Daftar Akun Inventory
                                </label>
                                <button v-if="!showCreateAccount" @click="showCreateAccount = true"
                                    class="flex items-center gap-1.5 text-xs font-black text-primary-600 uppercase hover:text-primary-700 transition-colors">
                                    <UserPlus :size="14" />
                                    Tambah Akun
                                </button>
                            </div>

                            <!-- Create Account Form -->
                            <div v-if="showCreateAccount"
                                class="p-4 rounded-2xl border-2 border-dashed border-primary-500/30 bg-primary-500/5 mb-4">
                                <label class="block text-[10px] font-black text-primary-600 uppercase mb-2 px-1">Nama
                                    Akun Baru</label>
                                <div class="flex flex-col sm:flex-row gap-2">
                                    <input v-model="newAccountName" type="text" placeholder="Nama Akun (Sales A)"
                                        class="flex-1 px-4 py-2.5 rounded-xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-700 font-bold focus:ring-2 focus:ring-primary-500 outline-none transition-all"
                                        @keyup.enter="handleCreateAccount" />
                                    <input v-model="newAccountPin" type="password" maxlength="4" placeholder="PIN (Opsional)"
                                        class="w-full sm:w-32 px-4 py-2.5 rounded-xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-700 font-bold focus:ring-2 focus:ring-primary-500 outline-none transition-all"
                                        @keyup.enter="handleCreateAccount" />
                                    <div class="flex gap-2">
                                        <button @click="handleCreateAccount" :disabled="loadingCreate"
                                            class="flex-1 sm:flex-none px-4 py-2.5 bg-primary-600 text-white rounded-xl font-bold hover:bg-primary-500 transition-all flex items-center justify-center gap-2">
                                            <Loader2 v-if="loadingCreate" :size="16" class="animate-spin" />
                                            Simpan
                                        </button>
                                        <button @click="showCreateAccount = false; newAccountName = ''"
                                            class="flex-1 sm:flex-none px-4 py-2.5 bg-surface-100 dark:bg-surface-700 text-text-secondary rounded-xl font-bold hover:bg-surface-200 transition-all">
                                            Batal
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-[400px] overflow-y-auto pr-2 custom-scrollbar">
                                <button v-for="acc in salesAccounts" :key="acc.id" @click="salesAccount = acc.name"
                                    class="w-full p-3 sm:p-4 rounded-2xl border-2 transition-all flex flex-col justify-center gap-1 relative overflow-hidden group"
                                    :class="salesAccount === acc.name ? 'border-primary-600 bg-primary-50 dark:bg-primary-900/20 shadow-lg shadow-primary-500/10' : 'border-surface-100 dark:border-surface-700 bg-surface-50 dark:bg-surface-900 hover:border-surface-300'">
                                    
                                    <div class="flex items-center gap-3 w-full pr-8">
                                        <!-- Photo or Initials -->
                                        <div class="w-10 h-10 sm:w-12 sm:h-12 shrink-0 rounded-full bg-surface-200 dark:bg-surface-700 flex items-center justify-center overflow-hidden border border-surface-300 dark:border-surface-600">
                                            <img v-if="getPhotoUrl(acc)" :src="getPhotoUrl(acc)" class="w-full h-full object-cover" alt="Profile" 
                                                @error="(e) => e.target.style.display='none'" />
                                            <span class="text-xs sm:text-sm font-black text-text-secondary">{{ getInitials(acc.name) }}</span>
                                        </div>

                                        <!-- Account Info -->
                                        <div class="flex flex-col items-start truncate text-left flex-1 min-w-0 pt-0.5">
                                            <span class="font-black text-sm sm:text-base transition-colors truncate w-full"
                                                :class="salesAccount === acc.name ? 'text-primary-600' : 'text-text-primary'">
                                                {{ acc.name }}
                                            </span>
                                            <span v-if="acc.created_by" class="text-[10px] sm:text-xs font-bold text-text-secondary truncate w-full mt-0.5">
                                                by {{ acc.created_by.full_name || acc.created_by.name }}
                                            </span>
                                        </div>
                                        
                                        <!-- Radio UI -->
                                        <div class="w-5 h-5 shrink-0 rounded-full border-2 flex items-center justify-center transition-all absolute top-1/2 -translate-y-1/2 right-4"
                                            :class="salesAccount === acc.name ? 'border-primary-600 bg-primary-600' : 'border-surface-300 bg-white dark:bg-surface-800'">
                                            <div v-if="salesAccount === acc.name" class="w-2 h-2 rounded-full bg-white"></div>
                                        </div>
                                    </div>
                                </button>
                            </div>
                        </div>

                        <button @click="nextStep" :disabled="!salesAccount"
                            class="w-full mt-8 h-12 sm:h-16 bg-primary-600 hover:bg-primary-500 disabled:opacity-50 text-white rounded-2xl font-black text-lg shadow-xl shadow-primary-500/30 transition-all flex items-center justify-center gap-3">
                            Lanjutkan
                            <ArrowRight :size="24" stroke-width="3" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- STEP 2: CATEGORY SELECTION -->
            <div v-if="currentStep === 2" class="flex-1 flex flex-col items-center justify-center py-8">
                <div class="w-full max-w-5xl grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 mb-12">
                    <button v-for="cat in categoriesPenjualan" :key="cat.id"
                        @click="transactionCategory = cat.id; nextStep()"
                        class="p-6 sm:p-8 bg-white dark:bg-surface-800 rounded-[1.5rem] sm:rounded-[2rem] border-2 border-surface-100 dark:border-surface-700 hover:border-primary-500 hover:shadow-2xl hover:shadow-primary-500/10 transition-all group relative overflow-hidden text-left flex flex-col gap-3 sm:gap-4 active:scale-95">
                        <div
                            class="w-10 h-10 sm:w-14 sm:h-14 bg-surface-50 dark:bg-surface-900 rounded-xl sm:rounded-2xl flex items-center justify-center group-hover:bg-primary-600 group-hover:text-white transition-all">
                            <ShoppingCart v-if="cat.id === 'penjualan_store'" :size="20" class="sm:w-8 sm:h-8" />
                            <PackageOpen v-else-if="cat.id === 'angkat_barang'" :size="20" class="sm:w-8 sm:h-8" />
                            <RotateCcw v-else-if="cat.id === 'refund'" :size="20" class="sm:w-8 sm:h-8" />
                            <RefreshCw v-else-if="cat.id === 'tukar_unit'" :size="20" class="sm:w-8 sm:h-8" />
                            <TrendingUp v-else-if="cat.id === 'tukar_tambah'" :size="20" class="sm:w-8 sm:h-8" />
                            <TrendingDown v-else-if="cat.id === 'downgrade'" :size="20" class="sm:w-8 sm:h-8" />
                        </div>
                        <h3
                            class="text-sm sm:text-xl font-black text-text-primary group-hover:text-primary-600 transition-colors uppercase tracking-tight">
                            {{ cat.label }}</h3>
                    </button>
                </div>

                <!-- Back to Account Selection -->
                <button @click="prevStep" 
                    class="group flex items-center gap-3 px-8 py-4 bg-surface-100 dark:bg-surface-700 hover:bg-surface-200 dark:hover:bg-surface-600 rounded-2xl transition-all active:scale-95">
                    <ArrowLeft class="text-text-secondary group-hover:text-primary-600 transition-colors" :size="20" stroke-width="3" />
                    <span class="text-sm font-black text-text-secondary group-hover:text-primary-600 uppercase tracking-widest transition-colors">
                        Kembali Ganti Akun
                    </span>
                </button>
            </div>

            <!-- LOADING OVERLAY FOR STEP 3 DATA -->
            <div v-if="currentStep === 3 && loadingStep3Data" class="flex-1 flex flex-col items-center justify-center">
                <div class="flex flex-col items-center gap-6 p-12 rounded-[2.5rem] bg-white/40 dark:bg-surface-800/40 backdrop-blur-md">
                    <div class="relative w-20 h-20">
                        <Loader2 class="w-20 h-20 text-primary-600 animate-spin" stroke-width="2" />
                        <ShoppingCart class="absolute inset-0 m-auto w-8 h-8 text-primary-600" />
                    </div>
                    <div class="text-center">
                        <h3 class="text-2xl font-black text-text-primary mb-2">Menyiapkan Inventori...</h3>
                        <p class="text-text-secondary font-medium">Mohon tunggu sebentar, sedang memuat data terbaru.</p>
                    </div>
                </div>
            </div>

            <!-- STEP 3: TRANSACTION COMPONENTS -->
            <div v-if="currentStep === 3 && !loadingStep3Data" class="flex-1 flex flex-col min-h-0">
                <PenjualanStep3 v-if="transactionCategory === 'penjualan_store'" :transactionCategory="transactionCategory"
                    :availablePaymentMethods="availablePaymentMethods" @prev="prevStep" @next="currentStep = 4" />

                <AngkatBarangForm v-else-if="transactionCategory === 'angkat_barang'"
                    :availablePaymentMethods="availablePaymentMethods" :brands="brands" :productTypes="productTypes"
                    :productPrices="productPrices" :distributors="distributors" :selectedAccountObject="selectedAccountObject" 
                    :salesAccount="salesAccount"
                    @back="prevStep" @transaction-complete="handleTransactionComplete"
                    @verify-pin="handleVerifyPin" />

                <RefundForm v-else-if="transactionCategory === 'refund'"
                    :availablePaymentMethods="availablePaymentMethods" :brands="brands" :productTypes="productTypes"
                    :distributors="distributors" :selectedAccountObject="selectedAccountObject"
                    :salesAccount="salesAccount"
                    @back="prevStep" @transaction-complete="handleTransactionComplete" @verify-pin="handleVerifyPin" />

                <TukarUnitForm v-else-if="transactionCategory === 'tukar_unit'"
                    :availablePaymentMethods="availablePaymentMethods" :brands="brands" :productTypes="productTypes"
                    :distributors="distributors" :selectedAccountObject="selectedAccountObject"
                    :salesAccount="salesAccount"
                    @back="prevStep" @transaction-complete="handleTransactionComplete" @verify-pin="handleVerifyPin" />

                <TukarTambahForm v-else-if="transactionCategory === 'tukar_tambah'"
                    :availablePaymentMethods="availablePaymentMethods" :brands="brands" :productTypes="productTypes"
                    :distributors="distributors" :selectedAccountObject="selectedAccountObject"
                    :salesAccount="salesAccount"
                    @back="prevStep" @transaction-complete="handleTransactionComplete" @verify-pin="handleVerifyPin" />

                <DowngradeForm v-else-if="transactionCategory === 'downgrade'"
                    :availablePaymentMethods="availablePaymentMethods" :brands="brands" :productTypes="productTypes"
                    :distributors="distributors" :selectedAccountObject="selectedAccountObject"
                    :salesAccount="salesAccount"
                    @back="prevStep" @transaction-complete="handleTransactionComplete" @verify-pin="handleVerifyPin" />
            </div>

            <!-- STEP 4: PAYMENT -->
            <PaymentStep v-if="currentStep === 4" :availablePaymentMethods="availablePaymentMethods"
                :transactionCategory="transactionCategory" :salesAccount="salesAccount" 
                :selectedAccountObject="selectedAccountObject" @prev="prevStep"
                @transaction-complete="handleTransactionComplete" @verify-pin="handleVerifyPin" />

        </div>

        <!-- SHARED MODALS -->
        <PinModal :show="showPinModal" :title="pinModalTitle" :mode="pinModalMode" @close="showPinModal = false"
            @success="handlePinSuccess" />
        <ReceiptModal v-if="showSuccessModal" :is-open="showSuccessModal" :transaction="lastTransaction"
            :auto-send="lastTransaction?.category === 'penjualan' && (!!lastTransaction?.customer_wa || !!lastTransaction?.customer_phone) && (lastTransaction?.customer_wa !== '-' || lastTransaction?.customer_phone !== '-')"
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
