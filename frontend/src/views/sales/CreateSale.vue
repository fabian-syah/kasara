<script setup>
import { ref, computed, onMounted, watch } from "vue";
import api from "../../api/axios";
import { useEscapeKey } from "../../composables/useEscapeKey";
import { useCartStore } from "../../store/cart";
import { useInventoryStore } from "../../store/inventory";
import { useAuthStore } from "../../store/auth";
import { formatCurrency } from "../../utils/formatters";
import {
    Search,
    Plus,
    ShoppingCart,
    Minus,
    Trash2,
    X,
    CreditCard,
    Banknote,
    QrCode,
    Receipt,
    CheckCircle,
    AlertCircle,
    User,
    ArrowLeft,
    ArrowRight,
    ShoppingBag,
    Shield,
    Loader2,
    Hash,
} from "lucide-vue-next";
import PinModal from "../../components/modals/PinModal.vue";

const cartStore = useCartStore();
const inventoryStore = useInventoryStore();

// Wizard Steps
const currentStep = ref(1); // 1: Account, 2: Category, 3: Items, 4: Form/Payment
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

const searchQuery = ref("");
const selectedCategory = ref(null);

// Form Fields (Step 4)
const customerForm = ref({
    customer_name: "",
    customer_phone: "",
    notes: "",
});

// Payment state (Step 4)
const paymentAmount = ref(0);
const selectedPaymentMethod = ref(null);
const availablePaymentMethods = ref([]);
const splitPayments = ref([]);
const proofImage = ref(null);
const proofImagePreview = ref(null);
const isSubmitting = ref(false);

// Success modal
const showSuccessModal = ref(false);
const lastTransaction = ref(null);

const authStore = useAuthStore();
const currentUser = ref(null);
const showInitialPinSetup = ref(false);

onMounted(async () => {
    try {
        const [hpRes, nonHpRes, accountsRes, userRes, paymentsRes] = await Promise.all([
            api.get('/inventory', { params: { type: 'hp', status: 'available', per_page: 1000 } }),
            api.get('/inventory', { params: { type: 'non-hp', per_page: 1000 } }),
            api.get('/inventory/my-accounts'),
            api.get('/user'),
            api.get('/payment-methods')
        ]);

        // Process HP items
        const hpData = hpRes.data?.data || hpRes.data || [];

        // Process Non-HP items
        const rawNonHpData = nonHpRes.data?.data || nonHpRes.data || [];
        const nonHpData = rawNonHpData.map(item => ({
            ...item,
            is_non_hp: true,
            selling_price: item.product?.selling_price || item.product?.price || 0,
            condition: 'new',
            ram: null,
            storage: null,
            imei: null,
            distributor: { name: item.latest_distributor || item.latest_supplier || null }
        }));

        // Combine and set in store
        inventoryStore.products = [...hpData, ...nonHpData];

        const rawAccounts = accountsRes.data.data || accountsRes.data;
        // Filter ONLY for sales role as requested by user (hide inventory accounts like bian trial)
        salesAccounts.value = rawAccounts.filter(acc =>
            acc.roles && acc.roles.some(r => r.name === 'sales')
        );

        const currentUserData = userRes.data.data || userRes.data;
        currentUser.value = currentUserData;

        // Payment Methods
        const payments = (paymentsRes.data.data || paymentsRes.data).filter(p => p.is_active);
        availablePaymentMethods.value = payments;
        if (payments.length > 0) {
            // Default to cash or first one
            const cashMethod = payments.find(p => p.category?.toLowerCase() === 'cash' || p.name?.toLowerCase() === 'tunai');
            selectedPaymentMethod.value = cashMethod ? cashMethod.id : payments[0].id;
        }

        // Auto-select logged-in user if they are in the list
        if (currentUserData) {
            // Check if PIN setup is needed (only for sales role)
            const isSales = currentUserData.roles && currentUserData.roles.some(r => r.name === 'sales');
            if (isSales && !currentUserData.transaction_pin) {
                showInitialPinSetup.value = true;
            }

            const match = salesAccounts.value.find(acc =>
                acc.name === currentUserData.name ||
                acc.username === currentUserData.username ||
                acc.id === currentUserData.id
            );
            if (match) {
                salesAccount.value = match.name;
            }
        }
    } catch (e) {
        console.error("Gagal memuat data awal", e);
    }
});

const isBundling = ref(false);
function toggleBundling() {
    isBundling.value = !isBundling.value;
    if (transactionCategory.value === 'penjualan' || transactionCategory.value === 'bundling') {
        transactionCategory.value = isBundling.value ? 'bundling' : 'penjualan';
    }
}

// Step Navigation
function nextStep() {
    if (currentStep.value === 1 && !salesAccount.value) {
        alert("Silakan pilih Akun Sales terlebih dahulu.");
        return;
    }
    if (currentStep.value === 3 && cartItems.value.length === 0) {
        alert("Pilih minimal 1 produk terlebih dahulu.");
        return;
    }

    if (currentStep.value < 4) {
        currentStep.value++;
        if (currentStep.value === 4) {
            paymentAmount.value = cartTotal.value;
            displayPaymentAmount.value = formatNumber(cartTotal.value);
            // Initialize split payments with a single entry covering the total
            splitPayments.value = [{
                method_id: selectedPaymentMethod.value,
                amount: cartTotal.value,
                display_amount: formatNumber(cartTotal.value)
            }];
        }
    }
}

function prevStep() {
    if (currentStep.value > 1) {
        currentStep.value--;
    }
}

const filteredProducts = computed(() => {
    let result = inventoryStore.products;
    if (searchQuery.ref || searchQuery.value) {
        const query = (searchQuery.value || "").toLowerCase();
        result = result.filter(
            (p) =>
                (p.product?.name || p.name || "").toLowerCase().includes(query) ||
                (p.product?.brand || "").toLowerCase().includes(query) ||
                (p.imei || "").toLowerCase().includes(query)
        );
    }
    if (selectedCategory.value) {
        result = result.filter((p) => p.category === selectedCategory.value);
    }
    return result;
});

const categories = computed(() => inventoryStore.categories);
const cartItems = computed(() => cartStore.items);
const cartTotal = computed(() => cartStore.total);
const cartSubtotal = computed(() => cartStore.subtotal);
const cartItemCount = computed(() => cartStore.itemCount);

const paymentMethods = []; // Deprecated, using availablePaymentMethods from DB

const selectedPaymentMethodObj = computed(() =>
    availablePaymentMethods.value.find(m => m.id === selectedPaymentMethod.value)
);

const isCashPayment = computed(() => {
    const cat = selectedPaymentMethodObj.value?.category?.toLowerCase();
    const name = selectedPaymentMethodObj.value?.name?.toLowerCase();
    return cat === 'cash' || cat === 'tunai' || name?.includes('cash') || name?.includes('tunai');
});

const changeAmount = computed(() => {
    const totalPaid = splitPayments.value.reduce((sum, p) => sum + p.amount, 0);
    return totalPaid - cartTotal.value;
});

const isFormValid = computed(() => {
    const hasName = !!customerForm.value.customer_name;
    const hasPhone = !!customerForm.value.customer_phone;
    const hasNotes = !!customerForm.value.notes;
    const hasPhoto = !!proofImage.value;
    const paymentMatches = changeAmount.value >= 0;

    // For sale categories, these are mandatory
    const salesCategoriesList = ['penjualan', 'bundling', 'tukar_unit', 'tukar_tambah', 'downgrade'];
    if (salesCategoriesList.includes(transactionCategory.value)) {
        return hasName && hasPhone && hasNotes && hasPhoto && paymentMatches;
    }
    return paymentMatches;
});

function addToCart(product) {
    if (!isBundling.value && cartItems.value.length >= 1) {
        alert("Mode Penjualan Normal hanya memperbolehkan 1 jenis barang. Aktifkan Mode Bundling jika ingin menambah lebih banyak.");
        return;
    }
    const availableStock = product.stock !== undefined ? product.stock : (product.quantity !== undefined ? product.quantity : 1);
    if (availableStock > 0) {
        cartStore.addItem(product);
    }
}

function removeFromCart(productId) {
    cartStore.removeItem(productId);
}

function incrementQty(productId) {
    cartStore.incrementQuantity(productId);
}

function decrementQty(productId) {
    cartStore.decrementQuantity(productId);
}

function addSplitPayment() {
    splitPayments.value.push({
        method_id: availablePaymentMethods.value[0]?.id || null,
        amount: 0,
        display_amount: "0"
    });
}

function removeSplitPayment(index) {
    if (splitPayments.value.length > 1) {
        splitPayments.value.splice(index, 1);
    }
}

function handleSplitAmountInput(index, e) {
    const val = e.target.value;
    const num = parseNumber(val);
    splitPayments.value[index].amount = num;
    splitPayments.value[index].display_amount = formatNumber(num);
}

function handleFileChange(e) {
    const file = e.target.files[0];
    if (file) {
        if (file.size > 10 * 1024 * 1024) {
            alert("Ukuran file maksimal 10MB");
            e.target.value = "";
            return;
        }
        proofImage.value = file;
        const reader = new FileReader();
        reader.onload = (e) => {
            proofImagePreview.value = e.target.result;
        };
        reader.readAsDataURL(file);
    }
}

async function handleSubmitOrder() {
    // Only Sales role with PIN enabled requires PIN
    if (authStore.userRole === 'sales' && authStore.user?.pin_enabled) {
        showPinModal.value = true;
        pinModalMode.value = "verify";
        pinModalTitle.value = "Verifikasi PIN Transaksi";
    } else {
        await processPayment();
    }
}

async function handlePinSuccess() {
    showPinModal.value = false;
    await processPayment();
}

async function processPayment() {
    if (isSubmitting.value) return;
    try {
        isSubmitting.value = true;
        const formData = new FormData();
        formData.append('category', transactionCategory.value);
        formData.append('sales_account', salesAccount.value);
        if (selectedPaymentMethod.value) {
            formData.append('payment_method_id', selectedPaymentMethod.value);
        }
        formData.append('paid_amount', paymentAmount.value);
        formData.append('selling_price', cartStore.total);

        // Form details
        formData.append('customer_name', customerForm.value.customer_name);
        formData.append('customer_phone', customerForm.value.customer_phone);

        let finalNotes = customerForm.value.notes;
        if (cartStore.discount > 0) {
            const discText = cartStore.discountType === 'percentage' ? `${cartStore.discount}%` : formatCurrency(cartStore.discount);
            finalNotes = (finalNotes ? finalNotes + "\n" : "") + `[Diskon ${discText}: -${formatCurrency(cartStore.discountAmount)}]`;
        }
        formData.append('notes', finalNotes);

        let nonHpIndex = 0;
        cartItems.value.forEach(item => {
            if (item.imei) {
                formData.append('product_detail_ids[]', item.id);
            } else {
                formData.append(`non_hp_items[${nonHpIndex}][product_id]`, item.product_id || item.id);
                formData.append(`non_hp_items[${nonHpIndex}][quantity]`, item.quantity);
                formData.append(`non_hp_items[${nonHpIndex}][selling_price]`, item.price || 0);
                nonHpIndex++;
            }
        });

        // Split Payments
        formData.append('split_payments', JSON.stringify(splitPayments.value.map(p => ({
            payment_method_id: p.method_id,
            amount: p.amount
        }))));

        // Proof Image
        if (proofImage.value) {
            formData.append('proof_image', proofImage.value);
        }

        const response = await api.post('/stock-outs', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });

        lastTransaction.value = {
            id: response.data?.data?.receipt_id || "TRX-" + Date.now(),
            items: [...cartItems.value],
            total: cartTotal.value,
            paid: paymentAmount.value,
            change: changeAmount.value,
            method: selectedPaymentMethod.value,
            category: transactionCategory.value,
            sales_account: salesAccount.value,
            time: new Date().toLocaleString("id-ID"),
        };

        showSuccessModal.value = true;
        cartStore.clearCart();
        paymentAmount.value = 0;
        splitPayments.value = [];
        proofImage.value = null;
        proofImagePreview.value = null;
        customerForm.value = {
            customer_name: "",
            customer_phone: "",
            notes: "",
        };
        currentStep.value = 1;
        salesAccount.value = "";
    } catch (error) {
        console.error("Payment failed", error);
        alert(error.response?.data?.message || "Gagal memproses transaksi");
    } finally {
        isSubmitting.value = false;
    }
}

function closeSuccessModal() {
    showSuccessModal.value = false;
    lastTransaction.value = null;
}

useEscapeKey(() => {
    if (showSuccessModal.value) closeSuccessModal();
});

// Auto Rupiah logic
const displayPaymentAmount = ref("0");
const displayDiscount = ref("0");

// Helper to format raw number to Rupiah string
function formatNumber(n) {
    if (!n) return "0";
    return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

// Helper to get number from Rupiah string
function parseNumber(s) {
    if (!s) return 0;
    const clean = s.toString().replace(/[^0-9]/g, "");
    return parseInt(clean) || 0;
}

// Sync displays when underlying values change (e.g. from cartTotal)
watch(() => paymentAmount.value, (newVal) => {
    displayPaymentAmount.value = formatNumber(newVal);
});

watch(() => cartStore.discount, (newVal) => {
    if (cartStore.discountType === 'fixed') {
        displayDiscount.value = formatNumber(newVal);
    } else {
        displayDiscount.value = newVal?.toString() || "0";
    }
});

function handlePaymentInput(e) {
    const val = e.target.value;
    const num = parseNumber(val);
    paymentAmount.value = num;
    displayPaymentAmount.value = formatNumber(num);
}

function handleDiscountInput(e) {
    const val = e.target.value;
    if (cartStore.discountType === 'fixed') {
        const num = parseNumber(val);
        cartStore.discount = num;
        displayDiscount.value = formatNumber(num);
    } else {
        // Percentage (max 100)
        let num = parseInt(val.replace(/[^0-9]/g, "")) || 0;
        if (num > 100) num = 100;
        cartStore.discount = num;
        displayDiscount.value = num.toString();
    }
}

function handleItemPriceInput(item, e) {
    const val = e.target.value;
    const num = parseNumber(val);
    item.price = num;

    // Force reactivity update on the input element's value to maintain format
    // even if the raw number didn't change (e.g., typing letters)
    e.target.value = formatNumber(num);
}

// Sync paymentAmount to cartTotal when Step 4 is entered
watch(() => currentStep.value, (newStep) => {
    if (newStep === 4) {
        paymentAmount.value = cartStore.total;
        displayPaymentAmount.value = formatNumber(cartStore.total);
    }
});
</script>

<template>
    <div class="max-w-[1600px] mx-auto px-4 py-8 h-[calc(100vh-8rem)]">
        <!-- Progress Bar -->
        <div class="mb-12 max-w-5xl mx-auto">
            <div class="flex items-center justify-between relative">
                <div
                    class="absolute left-0 right-0 top-1/2 -translate-y-1/2 h-1 bg-surface-200 dark:bg-surface-700 -z-10 mx-8 rounded-full">
                    <div class="h-full bg-primary-500 transition-all duration-500 ease-out rounded-full"
                        :style="{ width: `${((currentStep - 1) / 3) * 100}%` }"></div>
                </div>

                <div v-for="step in 4" :key="step" class="flex flex-col items-center gap-3">
                    <div class="w-14 h-14 rounded-full flex items-center justify-center font-bold text-lg transition-all duration-300 shadow-sm"
                        :class="currentStep >= step ? 'bg-primary-600 text-white shadow-primary-500/30 scale-110' : 'bg-white dark:bg-surface-800 text-text-secondary border-2 border-surface-200 dark:border-surface-700'">
                        <CheckCircle v-if="currentStep > step" :size="24" />
                        <span v-else>{{ step }}</span>
                    </div>
                    <span class="text-xs font-bold uppercase tracking-widest transition-colors"
                        :class="currentStep >= step ? 'text-primary-600' : 'text-text-secondary'">
                        {{ ['Akun Sales', 'Kategori', 'Pilih Barang', 'Pembayaran'][step - 1] }}
                    </span>
                </div>
            </div>
        </div>

        <!-- content area -->
        <div class="h-full flex flex-col transition-all duration-300">
            <!-- STEP 1: ACCOUNT SELECTION -->
            <div v-if="currentStep === 1"
                class="flex-1 flex flex-col justify-center max-w-3xl mx-auto w-full animate-fade-in">
                <div
                    class="bg-white dark:bg-surface-800 rounded-[2rem] border border-surface-200 dark:border-surface-700 p-10 shadow-xl text-center">
                    <div
                        class="w-24 h-24 bg-primary-500/10 text-primary-500 rounded-[2rem] flex items-center justify-center mx-auto mb-8">
                        <User :size="48" stroke-width="1.5" />
                    </div>
                    <h2 class="text-4xl font-black text-text-primary mb-3">Akun Sales</h2>
                    <p class="text-text-secondary text-lg mb-10">Pilih nama akun utama yang bertanggung jawab pada
                        transaksi ini</p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
                        <button v-for="account in salesAccounts" :key="account.id"
                            @click="salesAccount = (account.full_name || account.name); nextStep()"
                            class="p-6 rounded-2xl border-2 transition-all flex items-center gap-4 text-left group"
                            :class="salesAccount === (account.full_name || account.name)
                                ? 'border-primary-500 bg-primary-500/5 shadow-lg shadow-primary-500/10'
                                : 'border-surface-200 dark:border-surface-700 hover:border-primary-500/50 hover:bg-surface-50 dark:hover:bg-surface-900/50'">
                            <div class="w-12 h-12 rounded-full flex items-center justify-center transition-colors"
                                :class="salesAccount === (account.full_name || account.name) ? 'bg-primary-500 text-white' : 'bg-surface-100 dark:bg-surface-800 text-text-secondary group-hover:bg-primary-100 group-hover:text-primary-600'">
                                <User :size="20" />
                            </div>
                            <span
                                class="font-bold text-lg text-text-primary group-hover:text-primary-600 transition-colors">{{
                                    account.full_name || account.name }}</span>
                        </button>
                    </div>

                    <div class="flex justify-end mt-8 border-t border-surface-100 dark:border-surface-700 pt-8">
                        <button @click="nextStep" :disabled="!salesAccount"
                            class="py-4 px-10 bg-primary-600 hover:bg-primary-500 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-2xl font-bold text-lg shadow-xl shadow-primary-500/20 transition-all flex items-center gap-3">
                            Lanjut
                            <ArrowRight :size="24" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- STEP 2: CATEGORY SELECTION -->
            <div v-if="currentStep === 2"
                class="flex-1 flex flex-col justify-center max-w-4xl mx-auto w-full animate-fade-in">
                <div
                    class="bg-white dark:bg-surface-800 rounded-[2rem] border border-surface-200 dark:border-surface-700 p-10 shadow-xl">
                    <div class="text-center mb-10">
                        <h2 class="text-4xl font-black text-text-primary mb-3">Kategori Transaksi</h2>
                        <p class="text-text-secondary text-lg">Pilih jenis transaksi yang akan dilakukan oleh <strong
                                class="text-primary-600">{{ salesAccount }}</strong></p>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-3 gap-6 mb-12">
                        <button v-for="cat in categoriesPenjualan" :key="cat.id"
                            @click="transactionCategory = cat.id; nextStep()"
                            class="p-8 rounded-[1.5rem] border-2 transition-all duration-300 flex flex-col items-center gap-4 relative overflow-hidden group hover:-translate-y-1 hover:shadow-xl"
                            :class="transactionCategory === cat.id
                                ? 'border-primary-500 bg-primary-500/5 shadow-primary-500/10'
                                : 'border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-900/50 hover:border-primary-300'">
                            <div class="w-16 h-16 bg-white dark:bg-surface-800 rounded-[1.25rem] shadow-sm flex items-center justify-center transition-colors"
                                :class="transactionCategory === cat.id ? 'ring-2 ring-primary-500 ring-offset-2 dark:ring-offset-surface-800' : 'group-hover:text-primary-500'">
                                <ShoppingBag :size="32"
                                    :class="transactionCategory === cat.id ? 'text-primary-500' : 'text-text-secondary group-hover:text-primary-500'"
                                    stroke-width="1.5" />
                            </div>
                            <span
                                class="font-bold text-lg text-text-primary group-hover:text-primary-600 transition-colors">{{
                                    cat.label }}</span>
                            <div v-if="transactionCategory === cat.id"
                                class="absolute top-4 right-4 text-primary-500 bg-white dark:bg-surface-800 rounded-full shadow-sm p-0.5">
                                <CheckCircle :size="20" />
                            </div>
                        </button>
                    </div>

                    <div class="flex justify-between border-t border-surface-100 dark:border-surface-700 pt-8">
                        <button @click="prevStep"
                            class="py-4 px-8 bg-surface-100 dark:bg-surface-700 hover:bg-surface-200 dark:hover:bg-surface-600 text-text-primary rounded-2xl font-bold text-lg transition-all flex items-center gap-3">
                            <ArrowLeft :size="24" /> Kembali
                        </button>
                        <button @click="nextStep"
                            class="py-4 px-10 bg-primary-600 hover:bg-primary-500 text-white rounded-2xl font-bold text-lg shadow-xl shadow-primary-500/20 transition-all flex items-center gap-3">
                            Lanjut
                            <ArrowRight :size="24" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- STEP 3: ITEM SELECTION -->
            <div v-if="currentStep === 3" class="flex-1 flex flex-col lg:flex-row gap-8 min-h-0 animate-fade-in">
                <!-- Products -->
                <div class="flex-[2] flex flex-col min-w-0">
                    <div
                        class="bg-white dark:bg-surface-800 rounded-[1.5rem] border border-surface-200 dark:border-surface-700 p-6 mb-6 shadow-sm flex flex-col md:flex-row gap-4 items-center">
                        <div class="relative flex-1 w-full">
                            <Search class="absolute left-5 top-1/2 -translate-y-1/2 text-text-secondary" :size="24" />
                            <input v-model="searchQuery" type="text" placeholder="Cari IMEI, Brand, atau Nama Produk..."
                                class="w-full bg-surface-50 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 rounded-2xl pl-14 pr-6 py-4 text-lg font-medium text-text-primary focus:outline-none focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 transition-all" />
                        </div>

                        <!-- Bundling Toggle (Only for Penjualan flow) -->
                        <div v-if="transactionCategory === 'penjualan' || transactionCategory === 'bundling'"
                            class="flex items-center gap-4 px-6 py-4 bg-surface-50 dark:bg-surface-900 rounded-2xl border border-surface-200 dark:border-surface-700 whitespace-nowrap">
                            <span class="text-sm font-bold text-text-secondary uppercase tracking-wider">Mode
                                Bundling</span>
                            <button @click="toggleBundling"
                                class="w-14 h-8 rounded-full relative transition-all duration-300"
                                :class="isBundling ? 'bg-primary-500' : 'bg-surface-300 dark:bg-surface-600'">
                                <div class="absolute top-1 w-6 h-6 bg-white rounded-full transition-all duration-300 shadow-sm"
                                    :class="isBundling ? 'left-7' : 'left-1'"></div>
                            </button>
                        </div>
                    </div>

                    <div
                        class="flex-1 overflow-y-auto custom-scrollbar bg-white dark:bg-surface-800 rounded-[1.5rem] border border-surface-200 dark:border-surface-700 mb-4 shadow-sm">
                        <table class="w-full text-left border-collapse">
                            <thead class="sticky top-0 bg-surface-50/95 dark:bg-surface-900/95 backdrop-blur-sm z-10">
                                <tr>
                                    <th
                                        class="px-6 py-5 text-xs font-black text-text-secondary uppercase tracking-widest border-b border-surface-200 dark:border-surface-700">
                                        Produk & Brand</th>
                                    <th
                                        class="px-6 py-5 text-xs font-black text-text-secondary uppercase tracking-widest border-b border-surface-200 dark:border-surface-700">
                                        Spek & Kondisi</th>
                                    <th
                                        class="px-6 py-5 text-xs font-black text-text-secondary uppercase tracking-widest border-b border-surface-200 dark:border-surface-700">
                                        IMEI / Stok</th>
                                    <th
                                        class="hidden xl:table-cell px-6 py-5 text-xs font-black text-text-secondary uppercase tracking-widest border-b border-surface-200 dark:border-surface-700">
                                        Distributor</th>
                                    <th
                                        class="px-6 py-5 text-xs font-black text-text-secondary uppercase tracking-widest border-b border-surface-200 dark:border-surface-700 text-right">
                                        Harga</th>
                                    <th class="px-6 py-5 border-b border-surface-200 dark:border-surface-700 w-24">
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-surface-100 dark:divide-surface-700">
                                <tr v-for="item in filteredProducts" :key="item.id"
                                    class="hover:bg-primary-50/50 dark:hover:bg-primary-900/10 transition-colors group">
                                    <td class="px-6 py-5">
                                        <div class="flex flex-col gap-1">
                                            <span class="font-black text-text-primary text-base">{{ item.product?.name
                                                ||
                                                item.name }}</span>
                                            <span class="text-xs text-primary-600 font-bold uppercase tracking-wider">{{
                                                item.product?.brand || '-' }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-5">
                                        <div class="flex flex-col items-start gap-2">
                                            <span
                                                class="text-sm font-bold text-text-primary bg-surface-100 dark:bg-surface-800 px-3 py-1 rounded-lg">{{
                                                    item.ram || '-'
                                                }} / {{ item.storage || '-' }}</span>
                                            <span class="text-xs uppercase px-3 py-1 rounded-lg font-bold"
                                                :class="item.condition === 'new' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400'">
                                                {{ item.condition || 'Second' }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-5">
                                        <code v-if="item.imei"
                                            class="text-sm font-mono font-bold text-text-primary bg-surface-50 dark:bg-surface-900 px-3 py-1.5 rounded-lg border border-surface-200 dark:border-surface-700">
                                            {{ item.imei }}
                                        </code>
                                        <span v-else
                                            class="text-sm font-black text-primary-600 bg-primary-500/10 px-4 py-1.5 rounded-lg">
                                            Stok: {{ item.quantity || 0 }}
                                        </span>
                                    </td>
                                    <td class="hidden xl:table-cell px-6 py-5">
                                        <span class="text-sm font-semibold text-text-secondary">{{
                                            item.distributor?.name ||
                                            item.supplier_name || '-' }}</span>
                                    </td>
                                    <td class="px-6 py-5 text-right">
                                        <span class="text-lg font-black text-primary-600">{{
                                            formatCurrency(item.selling_price || item.price) }}</span>
                                    </td>
                                    <td class="px-6 py-5 text-right">
                                        <button @click="addToCart(item)"
                                            class="w-12 h-12 flex items-center justify-center bg-primary-100 text-primary-600 hover:bg-primary-600 hover:text-white dark:bg-primary-900/50 dark:text-primary-400 dark:hover:bg-primary-600 dark:hover:text-white rounded-xl transition-all shadow-sm active:scale-95 ml-auto">
                                            <Plus :size="24" stroke-width="3" />
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="filteredProducts.length === 0">
                                    <td colspan="6" class="px-6 py-32 text-center">
                                        <div class="flex flex-col items-center justify-center text-text-secondary">
                                            <Search :size="48" class="mb-4 opacity-50" />
                                            <span class="text-lg font-medium">Produk tidak ditemukan atau stok
                                                kosong.</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Cart Sidebar (Sticky in step 3) -->
                <div
                    class="w-full lg:w-[450px] flex flex-col bg-white dark:bg-surface-800 rounded-[1.5rem] border border-surface-200 dark:border-surface-700 shadow-xl overflow-hidden shrink-0">
                    <div
                        class="p-6 border-b border-surface-100 dark:border-surface-700 bg-surface-50 dark:bg-surface-900 flex items-center justify-between font-bold">
                        <div class="flex items-center gap-3">
                            <ShoppingCart :size="24" class="text-primary-500" stroke-width="2.5" />
                            <span class="text-xl">Keranjang <span
                                    class="text-primary-500 font-black px-2 py-0.5 bg-primary-500/10 rounded-lg ml-1">{{
                                        cartItemCount }}</span></span>
                        </div>
                    </div>

                    <div class="flex-1 overflow-y-auto p-6 custom-scrollbar">
                        <div v-if="cartItems.length === 0"
                            class="h-full flex flex-col items-center justify-center text-text-secondary opacity-50">
                            <ShoppingCart :size="64" class="mb-6" stroke-width="1.5" />
                            <p class="text-xl font-medium">Keranjang Kosong</p>
                            <p class="text-sm mt-2">Pilih produk dari daftar di sebelah kiri.</p>
                        </div>
                        <div v-else class="space-y-4">
                            <div v-for="item in cartItems" :key="item.id"
                                class="p-5 bg-white dark:bg-surface-800 border-2 border-surface-100 dark:border-surface-700 rounded-2xl relative shadow-sm group hover:border-surface-300 dark:hover:border-surface-600 transition-colors">
                                <div class="flex justify-between items-start mb-4 pr-8">
                                    <div class="min-w-0 flex flex-col gap-1">
                                        <p class="text-sm font-black text-text-primary line-clamp-2 leading-tight">{{
                                            item.product?.name ||
                                            item.name }}</p>
                                        <span v-if="item.imei"
                                            class="text-xs font-mono font-bold text-text-secondary bg-surface-50 dark:bg-surface-900 px-2 py-1 rounded w-fit">{{
                                                item.imei }}</span>
                                    </div>
                                    <button @click="removeFromCart(item.id)"
                                        class="text-surface-400 hover:text-red-500 absolute top-4 right-4 bg-surface-50 dark:bg-surface-900 p-2 rounded-full transition-colors">
                                        <Trash2 :size="18" />
                                    </button>
                                </div>
                                <div
                                    class="flex justify-between items-end border-t border-surface-100 dark:border-surface-700 pt-4">
                                    <div class="flex items-center gap-3">
                                        <button v-if="!item.imei" @click="decrementQty(item.id)"
                                            class="w-8 h-8 flex items-center justify-center bg-surface-100 dark:bg-surface-700 rounded-lg text-text-primary hover:bg-surface-200 transition-colors font-black">-</button>
                                        <span class="text-sm font-black px-2">
                                            {{ item.quantity }}<span
                                                class="text-text-secondary font-medium ml-1">x</span>
                                        </span>
                                        <button v-if="!item.imei" @click="incrementQty(item.id)"
                                            class="w-8 h-8 flex items-center justify-center bg-surface-100 dark:bg-surface-700 rounded-lg text-text-primary hover:bg-surface-200 transition-colors font-black">+</button>
                                    </div>
                                    <div v-if="!item.imei" class="flex flex-col items-end">
                                        <div
                                            class="flex items-center gap-2 border-2 border-surface-200 dark:border-surface-700 rounded-xl bg-surface-50 dark:bg-surface-900 px-3 py-2.5 focus-within:border-primary-500 transition-all">
                                            <span class="text-xs text-text-secondary font-bold">Rp</span>
                                            <input type="text" :value="formatNumber(item.price)"
                                                @input="e => handleItemPriceInput(item, e)"
                                                class="w-24 text-right text-sm font-black bg-transparent outline-none focus:text-primary-600" />
                                        </div>
                                    </div>
                                    <p v-else class="text-lg font-black text-primary-600">{{
                                        formatCurrency(item.selling_price || item.price) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        class="p-6 bg-surface-50 dark:bg-surface-900 mt-auto border-t border-surface-200 dark:border-surface-700 shrink-0">
                        <div class="flex justify-between items-center mb-6 text-2xl font-black">
                            <span class="text-text-primary text-lg uppercase tracking-widest">Total</span>
                            <span class="text-primary-600">{{ formatCurrency(cartTotal) }}</span>
                        </div>
                        <div class="flex gap-3">
                            <button @click="prevStep"
                                class="w-16 h-16 flex-none bg-white dark:bg-surface-800 text-text-primary border-2 border-surface-200 dark:border-surface-700 rounded-[1.25rem] font-bold transition-all flex items-center justify-center hover:bg-surface-50 hover:border-surface-300">
                                <ArrowLeft :size="24" />
                            </button>
                            <button @click="nextStep" :disabled="cartItems.length === 0"
                                class="flex-1 h-16 bg-primary-600 hover:bg-primary-500 disabled:opacity-50 text-white rounded-[1.25rem] font-bold text-lg shadow-xl shadow-primary-500/30 transition-all flex items-center justify-center gap-3">
                                Pembayaran
                                <ArrowRight :size="24" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- STEP 4: FORM & PAYMENT -->
            <div v-if="currentStep === 4"
                class="flex-1 flex flex-col lg:flex-row gap-8 min-h-0 overflow-y-auto custom-scrollbar animate-fade-in">
                <!-- Transaction Summary & Form -->
                <div class="flex-[2] space-y-8 min-w-0">
                    <div
                        class="bg-white dark:bg-surface-800 rounded-[2rem] border border-surface-200 dark:border-surface-700 p-8 shadow-xl">
                        <h3 class="text-2xl font-black text-text-primary mb-8 flex items-center gap-3">
                            <User :size="28" class="text-primary-500" stroke-width="2.5" /> Detail Pelanggan
                        </h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label
                                    class="block text-sm font-bold text-text-secondary uppercase tracking-widest mb-3">
                                    Nama Pelanggan <span class="text-red-500">*</span>
                                </label>
                                <input v-model="customerForm.customer_name" type="text" placeholder="Masukkan nama..."
                                    class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-2xl px-5 py-4 bg-surface-50 dark:bg-surface-900 text-lg font-medium text-text-primary focus:outline-none focus:border-primary-500 focus:bg-white transition-all" />
                            </div>
                            <div>
                                <label
                                    class="block text-sm font-bold text-text-secondary uppercase tracking-widest mb-3">
                                    WhatsApp Customer <span class="text-red-500">*</span>
                                </label>
                                <input v-model="customerForm.customer_phone" type="text" placeholder="08xxx..."
                                    class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-2xl px-5 py-4 bg-surface-50 dark:bg-surface-900 text-lg font-medium text-text-primary focus:outline-none focus:border-primary-500 focus:bg-white transition-all" />
                            </div>
                            <div class="md:col-span-2">
                                <label
                                    class="block text-sm font-bold text-text-secondary uppercase tracking-widest mb-3">
                                    Keterangan / Notes <span class="text-red-500">*</span>
                                </label>
                                <textarea v-model="customerForm.notes" rows="2"
                                    placeholder="Catatan khusus untuk nota ini..."
                                    class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-2xl px-5 py-4 bg-surface-50 dark:bg-surface-900 text-lg font-medium text-text-primary focus:outline-none focus:border-primary-500 focus:bg-white transition-all resize-none"></textarea>
                            </div>
                            <div class="md:col-span-2">
                                <label
                                    class="block text-sm font-bold text-text-secondary uppercase tracking-widest mb-3">
                                    Foto Bukti (Max 10MB) <span class="text-red-500">*</span>
                                </label>
                                <div class="flex flex-col gap-4">
                                    <input type="file" @change="handleFileChange" accept="image/*"
                                        class="block w-full text-sm text-surface-500 file:mr-4 file:py-3 file:px-6 file:rounded-xl file:border-0 file:text-sm file:font-bold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100 transition-all cursor-pointer" />
                                    <div v-if="proofImagePreview"
                                        class="relative w-40 h-40 rounded-2xl overflow-hidden border-2 border-surface-200 dark:border-surface-700">
                                        <img :src="proofImagePreview" class="w-full h-full object-cover" />
                                        <button @click="proofImage = null; proofImagePreview = null"
                                            class="absolute top-2 right-2 bg-red-500 text-white p-1 rounded-full shadow-lg">
                                            <X :size="16" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        class="bg-white dark:bg-surface-800 rounded-[2rem] border border-surface-200 dark:border-surface-700 p-8 shadow-xl">
                        <h3 class="text-2xl font-black text-text-primary mb-8 flex items-center gap-3">
                            <ShoppingCart :size="28" class="text-primary-500" stroke-width="2.5" /> Ringkasan Pembelian
                        </h3>
                        <div class="space-y-4">
                            <div v-for="item in cartItems" :key="item.id"
                                class="flex justify-between items-center bg-surface-50 dark:bg-surface-900 p-5 rounded-2xl border border-surface-100 dark:border-surface-700">
                                <div class="flex items-center gap-5">
                                    <div
                                        class="w-14 h-14 bg-white dark:bg-surface-800 border-2 border-surface-200 dark:border-surface-700 rounded-xl flex items-center justify-center font-black text-lg shadow-sm">
                                        {{ item.quantity }}<span class="text-xs text-text-secondary ml-0.5">x</span>
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <p class="font-black text-lg text-text-primary">{{ item.name }}</p>
                                        <p class="text-sm font-bold text-text-secondary">{{ formatCurrency(item.price)
                                        }} / unit</p>
                                    </div>
                                </div>
                                <p class="font-black text-xl text-primary-600">{{ formatCurrency(item.price *
                                    item.quantity) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Block -->
                <div class="flex-[1.5] min-w-[350px]">
                    <div
                        class="bg-white dark:bg-surface-800 rounded-[2rem] border border-surface-200 dark:border-surface-700 p-8 shadow-2xl lg:sticky lg:top-0">
                        <div class="text-center mb-8 pb-8 border-b border-surface-100 dark:border-surface-700">
                            <p class="text-text-secondary text-sm font-black uppercase tracking-widest mb-3">TOTAL
                                TAGIHAN</p>
                            <p class="text-5xl font-black text-primary-600 tracking-tight">{{ formatCurrency(cartTotal)
                            }}</p>
                        </div>

                        <div class="space-y-8">
                            <div>
                                <div class="flex items-center justify-between mb-4">
                                    <p class="text-sm font-black text-text-secondary uppercase tracking-widest">Metode
                                        Pembayaran (Split)</p>
                                    <button @click="addSplitPayment"
                                        class="text-xs font-bold text-primary-500 hover:text-primary-600 flex items-center gap-1 bg-primary-50 dark:bg-primary-900/20 px-3 py-2 rounded-lg transition-all active:scale-95">
                                        <Plus :size="14" stroke-width="3" /> Tambah Metode
                                    </button>
                                </div>

                                <div class="space-y-4">
                                    <div v-for="(payment, index) in splitPayments" :key="index"
                                        class="p-5 bg-surface-50 dark:bg-surface-900 rounded-2xl border-2 border-surface-100 dark:border-surface-700 relative group animate-fade-in">

                                        <button v-if="splitPayments.length > 1" @click="removeSplitPayment(index)"
                                            class="absolute top-4 right-4 text-surface-400 hover:text-red-500 transition-colors">
                                            <Trash2 :size="16" />
                                        </button>

                                        <div class="grid grid-cols-1 gap-4">
                                            <div>
                                                <label
                                                    class="block text-[10px] font-black text-text-secondary uppercase tracking-widest mb-2">Metode</label>
                                                <select v-model="payment.method_id"
                                                    class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-white dark:bg-surface-800 text-sm font-bold text-text-primary focus:outline-none focus:border-primary-500 transition-all">
                                                    <option v-for="method in availablePaymentMethods" :key="method.id"
                                                        :value="method.id">
                                                        {{ method.name }} {{ method.account_number ?
                                                            `(${method.account_number})` : '' }}
                                                    </option>
                                                </select>
                                            </div>
                                            <div>
                                                <label
                                                    class="block text-[10px] font-black text-text-secondary uppercase tracking-widest mb-2">Nominal
                                                    Bayar</label>
                                                <div class="relative">
                                                    <span
                                                        class="absolute left-4 top-1/2 -translate-y-1/2 text-text-secondary text-sm font-black">Rp</span>
                                                    <input :value="payment.display_amount"
                                                        @input="e => handleSplitAmountInput(index, e)" type="text"
                                                        class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-white dark:bg-surface-800 text-text-primary text-xl font-black focus:outline-none focus:border-primary-500 transition-all pl-10"
                                                        placeholder="0" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Discount Section -->
                            <div class="pt-6 border-t border-surface-100 dark:border-surface-700">
                                <div class="flex items-center justify-between mb-4">
                                    <p class="text-sm font-black text-text-secondary uppercase tracking-widest">Diskon
                                        Tambahan</p>
                                    <div class="flex gap-1.5 bg-surface-100 dark:bg-surface-800 p-1 rounded-xl">
                                        <button @click="cartStore.discountType = 'percentage'"
                                            class="px-4 py-2 text-xs rounded-lg font-black transition-all"
                                            :class="cartStore.discountType === 'percentage' ? 'bg-primary-500 text-white shadow-md' : 'text-text-secondary hover:text-text-primary'">%</button>
                                        <button @click="cartStore.discountType = 'fixed'"
                                            class="px-4 py-2 text-xs rounded-lg font-black transition-all"
                                            :class="cartStore.discountType === 'fixed' ? 'bg-primary-500 text-white shadow-md' : 'text-text-secondary hover:text-text-primary'">Rp</button>
                                    </div>
                                </div>
                                <div class="relative">
                                    <span v-if="cartStore.discountType === 'fixed'"
                                        class="absolute left-5 top-1/2 -translate-y-1/2 text-text-secondary text-lg font-bold">Rp</span>
                                    <input :value="displayDiscount" @input="handleDiscountInput" type="text"
                                        class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-2xl px-5 py-4 bg-surface-50 dark:bg-surface-900 text-text-primary text-xl font-black focus:outline-none focus:border-primary-500 focus:bg-white transition-all"
                                        :class="cartStore.discountType === 'fixed' ? 'pl-14' : ''" placeholder="0" />
                                    <span v-if="cartStore.discountType === 'percentage'"
                                        class="absolute right-5 top-1/2 -translate-y-1/2 text-text-secondary text-lg font-bold">%</span>
                                </div>
                            </div>

                            <div v-if="cartStore.discountAmount > 0"
                                class="p-5 bg-primary-500/10 border border-primary-500/20 rounded-2xl flex justify-between items-center">
                                <span class="text-sm font-black text-primary-700">Potongan Diskon</span>
                                <span class="text-xl font-black text-primary-600">- {{
                                    formatCurrency(cartStore.discountAmount) }}</span>
                            </div>

                            <div v-if="isCashPayment" class="pt-6 border-t border-surface-100 dark:border-surface-700">
                                <div v-if="changeAmount >= 0"
                                    class="p-6 bg-emerald-500/10 border-2 border-emerald-500/20 rounded-2xl flex justify-between items-center">
                                    <span
                                        class="text-sm font-black text-emerald-700 uppercase tracking-widest">Kembalian</span>
                                    <span class="text-3xl font-black text-emerald-600">{{ formatCurrency(changeAmount)
                                    }}</span>
                                </div>
                                <div v-else
                                    class="p-6 bg-red-500/10 border-2 border-red-500/20 rounded-2xl flex justify-between items-center">
                                    <span
                                        class="text-sm font-black text-red-700 uppercase tracking-widest">Kurang</span>
                                    <span class="text-3xl font-black text-red-600">{{
                                        formatCurrency(Math.abs(changeAmount)) }}</span>
                                </div>
                            </div>

                            <!-- Change/Balance Status -->
                            <div v-if="changeAmount < 0"
                                class="p-6 bg-red-500/10 border-2 border-red-500/20 rounded-2xl flex justify-between items-center my-6 animate-pulse">
                                <span class="text-sm font-black text-red-700 uppercase tracking-widest">Uang
                                    Kurang</span>
                                <span class="text-3xl font-black text-red-600">{{ formatCurrency(Math.abs(changeAmount))
                                    }}</span>
                            </div>
                            <div v-else-if="changeAmount >= 0"
                                class="p-6 bg-emerald-500/10 border-2 border-emerald-500/20 rounded-2xl flex justify-between items-center my-6">
                                <span
                                    class="text-sm font-black text-emerald-700 uppercase tracking-widest">Kembalian</span>
                                <span class="text-3xl font-black text-emerald-600">{{ formatCurrency(changeAmount)
                                    }}</span>
                            </div>

                            <div v-if="!isFormValid"
                                class="p-4 bg-orange-50 border border-orange-200 rounded-xl flex items-start gap-3 mb-6">
                                <AlertCircle class="text-orange-500 shrink-0" :size="20" />
                                <p class="text-xs text-orange-700 font-medium">Lengkapi: Nama, WA, Catatan, Foto, &
                                    Pembayaran.</p>
                            </div>

                            <div class="flex gap-4 pt-8 border-t border-surface-100 dark:border-surface-700">
                                <button @click="prevStep"
                                    class="w-20 h-20 flex-none bg-surface-100 dark:bg-surface-800 hover:bg-surface-200 dark:hover:bg-surface-700 text-text-primary rounded-[1.25rem] font-bold transition-all flex items-center justify-center">
                                    <ArrowLeft :size="28" />
                                </button>
                                <button @click="handleSubmitOrder" :disabled="!isFormValid || isSubmitting"
                                    class="flex-1 h-20 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-[1.25rem] font-black text-xl shadow-2xl shadow-emerald-500/30 transition-all flex items-center justify-center gap-3">
                                    <Loader2 v-if="isSubmitting" class="animate-spin" :size="28" />
                                    <CheckCircle v-else :size="28" />
                                    {{ isSubmitting ? 'MEMPROSES...' : 'SELESAIKAN TRANSAKSI' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SUCCESS MODAL -->
        <Teleport to="body">
            <div v-if="showSuccessModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/80 backdrop-blur-sm"></div>
                <div
                    class="relative bg-white dark:bg-surface-800 rounded-[2.5rem] border border-surface-200 dark:border-surface-700 w-full max-w-md p-10 text-center shadow-2xl">
                    <div
                        class="w-28 h-28 mx-auto mb-8 bg-emerald-500/10 rounded-full flex items-center justify-center animate-bounce">
                        <CheckCircle class="text-emerald-500" :size="64" stroke-width="1.5" />
                    </div>
                    <h3 class="text-4xl font-black text-text-primary mb-3">Suksess!</h3>
                    <p class="text-text-secondary text-lg mb-10">Transaksi telah berhasil diproses & tersimpan</p>

                    <div v-if="lastTransaction"
                        class="bg-surface-50 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 rounded-[1.5rem] p-6 mb-10 text-left space-y-4">
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-text-secondary font-bold uppercase tracking-widest">Receipt ID</span>
                            <span
                                class="font-mono font-black text-text-primary bg-white dark:bg-surface-800 px-3 py-1 rounded-lg border border-surface-200 dark:border-surface-700">{{
                                    lastTransaction.id }}</span>
                        </div>
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-text-secondary font-bold uppercase tracking-widest">Sales</span>
                            <span class="font-black text-text-primary">{{ lastTransaction.sales_account }}</span>
                        </div>
                        <div class="h-px bg-surface-200 dark:bg-surface-700 my-4"></div>
                        <div class="flex justify-between items-end">
                            <span class="text-text-secondary font-bold uppercase tracking-widest mb-1">Total</span>
                            <span class="text-3xl font-black text-emerald-500">{{ formatCurrency(lastTransaction.total)
                            }}</span>
                        </div>
                    </div>

                    <button @click="closeSuccessModal"
                        class="w-full py-5 bg-primary-600 hover:bg-primary-500 text-white rounded-[1.25rem] font-bold text-lg transition-all shadow-xl shadow-primary-500/30 mb-4">
                        Mulai Transaksi Baru
                    </button>
                    <button
                        class="w-full py-4 text-text-secondary hover:text-text-primary hover:bg-surface-50 dark:hover:bg-surface-900 font-bold text-sm uppercase tracking-widest rounded-[1.25rem] flex items-center justify-center gap-2 transition-colors">
                        <Receipt :size="20" /> Cetak Struk Bukti
                    </button>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: rgba(156, 163, 175, 0.2);
    border-radius: 9999px;
}

input[type="number"]::-webkit-inner-spin-button,
input[type="number"]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
</style>
