<script setup>
import { ref, computed, onMounted } from "vue";
import api from "../../api/axios";
import { useEscapeKey } from "../../composables/useEscapeKey";
import { useCartStore } from "../../store/cart";
import { useInventoryStore } from "../../store/inventory";
import { useAuthStore } from "../../store/auth";
import { formatCurrency } from "../../utils/formatters";
import {
    Search,
    ShoppingCart,
    Plus,
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
    { id: "bundling", label: "Bundling" },
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
const selectedPaymentMethod = ref("cash");
const isSubmitting = ref(false);

// Success modal
const showSuccessModal = ref(false);
const lastTransaction = ref(null);

const authStore = useAuthStore();
const showInitialPinSetup = ref(false);

onMounted(async () => {
    inventoryStore.fetchProducts();
    try {
        const [accountsRes, userRes] = await Promise.all([
            api.get('/inventory/my-accounts'),
            api.get('/user')
        ]);

        const rawAccounts = accountsRes.data.data || accountsRes.data;
        // Filter ONLY for sales role as requested by user (hide inventory accounts like bian trial)
        salesAccounts.value = rawAccounts.filter(acc =>
            acc.roles && acc.roles.some(r => r.name === 'sales')
        );

        const currentUserData = userRes.data.data || userRes.data;
        currentUser.value = currentUserData;

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
            } else if (salesAccounts.value.length > 0) {
                // If it's a sales account, it should be in the list now
                // but if not, we can still default to the first one available
            }
        }
    } catch (e) {
        console.error("Gagal memuat data awal", e);
    }
});

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

const paymentMethods = [
    { id: "cash", label: "Tunai", icon: Banknote },
    { id: "transfer", label: "Transfer", icon: CreditCard },
    { id: "qris", label: "QRIS", icon: QrCode },
];

function addToCart(product) {
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
        formData.append('payment_method', selectedPaymentMethod.value);
        formData.append('paid_amount', paymentAmount.value);
        formData.append('selling_price', cartTotal.value);

        // Form details
        formData.append('customer_name', customerForm.value.customer_name);
        formData.append('customer_phone', customerForm.value.customer_phone);

        let finalNotes = customerForm.value.notes;
        if (cartStore.discount > 0) {
            const discText = cartStore.discountType === 'percentage' ? `${cartStore.discount}%` : formatCurrency(cartStore.discount);
            finalNotes = (finalNotes ? finalNotes + "\n" : "") + `[Diskon ${discText}: -${formatCurrency(cartStore.discountAmount)}]`;
        }
        formData.append('notes', finalNotes);

        cartItems.value.forEach(item => {
            formData.append('product_detail_ids[]', item.id);
        });

        const response = await api.post('/stock-outs', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });

        const change = paymentAmount.value - cartTotal.value;
        lastTransaction.value = {
            id: response.data?.data?.receipt_id || "TRX-" + Date.now(),
            items: [...cartItems.value],
            total: cartTotal.value,
            paid: paymentAmount.value,
            change: change,
            method: selectedPaymentMethod.value,
            category: transactionCategory.value,
            sales_account: salesAccount.value,
            time: new Date().toLocaleString("id-ID"),
        };

        showSuccessModal.value = true;
        cartStore.clearCart();
        paymentAmount.value = 0;
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

function setQuickAmount(amount) {
    paymentAmount.value = amount;
}

const changeAmount = computed(() => paymentAmount.value - cartTotal.value);

</script>

<template>
    <div class="max-w-7xl mx-auto px-4 py-6 h-[calc(100vh-8rem)]">
        <!-- Progress Bar -->
        <div class="mb-10 max-w-4xl mx-auto">
            <div class="flex items-center justify-between relative">
                <div
                    class="absolute left-0 right-0 top-1/2 -translate-y-1/2 h-1 bg-surface-200 dark:bg-surface-700 -z-10 mx-6 rounded-full">
                    <div class="h-full bg-primary-500 transition-all duration-300 rounded-full"
                        :style="{ width: `${((currentStep - 1) / 3) * 100}%` }"></div>
                </div>

                <div v-for="step in 4" :key="step" class="flex flex-col items-center gap-2">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold transition-all duration-300 shadow-sm"
                        :class="currentStep >= step ? 'bg-primary-600 text-white' : 'bg-surface-100 dark:bg-surface-800 text-text-secondary'">
                        <CheckCircle v-if="currentStep > step" :size="20" />
                        <span v-else>{{ step }}</span>
                    </div>
                    <span class="text-[10px] font-bold uppercase tracking-wider"
                        :class="currentStep >= step ? 'text-primary-600' : 'text-text-secondary'">
                        {{ ['Akun', 'Kategori', 'Barang', 'Formulir'][step - 1] }}
                    </span>
                </div>
            </div>
        </div>

        <!-- content area -->
        <div class="h-full flex flex-col">
            <!-- STEP 1: ACCOUNT SELECTION -->
            <div v-if="currentStep === 1" class="flex-1 flex flex-col justify-center max-w-2xl mx-auto w-full">
                <div
                    class="bg-white dark:bg-surface-800 rounded-3xl border border-surface-200 dark:border-surface-700 p-8 shadow-xl text-center">
                    <div
                        class="w-20 h-20 bg-primary-500/10 text-primary-500 rounded-2xl flex items-center justify-center mx-auto mb-6">
                        <User :size="40" />
                    </div>
                    <h2 class="text-3xl font-bold text-text-primary mb-2">Pilih Akun Sales</h2>
                    <p class="text-text-secondary mb-8">Pilih nama akun utama yang melakukan penjualan</p>

                    <div class="text-left mb-8">
                        <label class="block text-sm font-semibold text-text-primary mb-3">Nama Sales (Untuk
                            Pencatatan)</label>
                        <select v-model="salesAccount"
                            class="w-full border border-surface-200 dark:border-surface-700 rounded-2xl px-5 py-4 bg-surface-50 dark:bg-surface-900 text-text-primary text-lg font-medium focus:outline-none focus:ring-4 focus:ring-primary-500/10 focus:border-primary-500 transition-all">
                            <option value="" disabled>-- Pilih Akun Sales --</option>
                            <option v-for="account in salesAccounts" :key="account.id"
                                :value="account.full_name || account.name">
                                {{ account.full_name || account.name }}
                            </option>
                        </select>
                    </div>

                    <button @click="nextStep" :disabled="!salesAccount"
                        class="w-full py-4 bg-primary-600 hover:bg-primary-500 disabled:opacity-50 text-white rounded-2xl font-bold text-lg shadow-lg shadow-primary-500/20 transition-all flex items-center justify-center gap-2">
                        Lanjut ke Kategori
                        <ArrowRight :size="20" />
                    </button>
                </div>
            </div>

            <!-- STEP 2: CATEGORY SELECTION -->
            <div v-if="currentStep === 2" class="flex-1 flex flex-col justify-center max-w-3xl mx-auto w-full">
                <div
                    class="bg-white dark:bg-surface-800 rounded-3xl border border-surface-200 dark:border-surface-700 p-8 shadow-xl">
                    <div class="text-center mb-8">
                        <h2 class="text-3xl font-bold text-text-primary">Kategori Transaksi</h2>
                        <p class="text-text-secondary mt-2">Pilih jenis transaksi untuk {{ salesAccount }}</p>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-8">
                        <button v-for="cat in categoriesPenjualan" :key="cat.id" @click="transactionCategory = cat.id"
                            class="p-6 rounded-2xl border-2 transition-all flex flex-col items-center gap-3 relative overflow-hidden group"
                            :class="transactionCategory === cat.id
                                ? 'border-primary-500 bg-primary-500/10 text-primary-600'
                                : 'border-surface-100 dark:border-surface-700 hover:border-surface-300'">
                            <div
                                class="w-12 h-12 bg-white dark:bg-surface-800 rounded-xl shadow-sm flex items-center justify-center">
                                <ShoppingBag :size="24"
                                    :class="transactionCategory === cat.id ? 'text-primary-500' : 'text-text-secondary'" />
                            </div>
                            <span class="font-bold text-sm">{{ cat.label }}</span>
                            <div v-if="transactionCategory === cat.id" class="absolute top-2 right-2 text-primary-500">
                                <CheckCircle :size="16" />
                            </div>
                        </button>
                    </div>

                    <div class="flex gap-4">
                        <button @click="prevStep"
                            class="flex-1 py-4 bg-surface-100 dark:bg-surface-700 text-text-primary rounded-2xl font-bold transition-all flex items-center justify-center gap-2">
                            <ArrowLeft :size="20" /> Kembali
                        </button>
                        <button @click="nextStep"
                            class="flex-[2] py-4 bg-primary-600 hover:bg-primary-500 text-white rounded-2xl font-bold text-lg shadow-xl shadow-primary-500/20 transition-all flex items-center justify-center gap-2">
                            Lanjut Pilih Barang
                            <ArrowRight :size="20" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- STEP 3: ITEM SELECTION -->
            <div v-if="currentStep === 3" class="flex-1 flex flex-col lg:flex-row gap-6 min-h-0">
                <!-- Products -->
                <div class="flex-[2] flex flex-col min-w-0">
                    <div
                        class="bg-white dark:bg-surface-800 rounded-2xl border border-surface-200 dark:border-surface-700 p-4 mb-4 flex gap-4">
                        <div class="relative flex-1">
                            <Search class="absolute left-4 top-1/2 -translate-y-1/2 text-text-secondary" :size="20" />
                            <input v-model="searchQuery" type="text" placeholder="Cari IMEI, Brand, atau Nama Produk..."
                                class="w-full bg-surface-50 dark:bg-surface-900 border-none rounded-xl pl-12 pr-4 py-3 text-sm text-text-primary focus:ring-4 focus:ring-primary-500/10 transition-all" />
                        </div>
                    </div>

                    <div
                        class="flex-1 overflow-y-auto custom-scrollbar bg-white dark:bg-surface-800 rounded-2xl border border-surface-200 dark:border-surface-700 mb-4 shadow-sm">
                        <table class="w-full text-left border-collapse">
                            <thead class="sticky top-0 bg-surface-50 dark:bg-surface-900 z-10">
                                <tr>
                                    <th
                                        class="px-4 py-3 text-[10px] font-bold text-text-secondary uppercase tracking-wider border-b border-surface-100 dark:border-surface-700">
                                        Produk & Brand</th>
                                    <th
                                        class="px-4 py-3 text-[10px] font-bold text-text-secondary uppercase tracking-wider border-b border-surface-100 dark:border-surface-700">
                                        Spek & Kondisi</th>
                                    <th
                                        class="px-4 py-3 text-[10px] font-bold text-text-secondary uppercase tracking-wider border-b border-surface-100 dark:border-surface-700">
                                        IMEI</th>
                                    <th
                                        class="px-4 py-3 text-[10px] font-bold text-text-secondary uppercase tracking-wider border-b border-surface-100 dark:border-surface-700">
                                        Distributor</th>
                                    <th
                                        class="px-4 py-3 text-[10px] font-bold text-text-secondary uppercase tracking-wider border-b border-surface-100 dark:border-surface-700 text-right">
                                        Harga</th>
                                    <th
                                        class="px-4 py-3 text-[10px] font-bold text-text-secondary uppercase tracking-wider border-b border-surface-100 dark:border-surface-700 text-right">
                                        Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-surface-100 dark:divide-surface-700">
                                <tr v-for="item in filteredProducts" :key="item.id"
                                    class="hover:bg-surface-50 dark:hover:bg-surface-900/50 transition-colors group">
                                    <td class="px-4 py-4">
                                        <div class="flex flex-col">
                                            <span class="font-bold text-text-primary text-sm">{{ item.product?.name ||
                                                item.name }}</span>
                                            <span class="text-[10px] text-text-secondary uppercase font-semibold">{{
                                                item.product?.brand || '-' }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex flex-col">
                                            <span class="text-xs font-semibold text-text-primary">{{ item.ram || '-'
                                            }}/{{ item.storage || '-' }}</span>
                                            <span
                                                class="text-[10px] uppercase px-2 py-0.5 rounded-full bg-surface-100 dark:bg-surface-700 w-fit mt-1"
                                                :class="item.condition === 'new' ? 'text-emerald-500' : 'text-amber-500'">
                                                {{ item.condition || 'Second' }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <code
                                            class="text-[11px] font-mono bg-surface-50 dark:bg-surface-900 px-2 py-1 rounded border border-surface-100 dark:border-surface-700">
                                            {{ item.imei || '-' }}
                                        </code>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="text-xs text-text-secondary">{{ item.distributor?.name ||
                                            item.supplier_name || '-' }}</span>
                                    </td>
                                    <td class="px-4 py-4 text-right">
                                        <span class="text-sm font-black text-primary-500">{{
                                            formatCurrency(item.selling_price || item.price) }}</span>
                                    </td>
                                    <td class="px-4 py-4 text-right">
                                        <button @click="addToCart(item)"
                                            class="p-2 bg-primary-600 hover:bg-primary-500 text-white rounded-lg transition-all shadow-lg shadow-primary-500/20 active:scale-95">
                                            <Plus :size="16" />
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="filteredProducts.length === 0">
                                    <td colspan="6" class="px-4 py-20 text-center text-text-secondary italic">
                                        Data tidak ditemukan atau stok kosong.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex gap-4">
                        <button @click="prevStep"
                            class="flex-1 py-4 bg-surface-100 dark:bg-surface-700 text-text-primary rounded-xl font-bold transition-all flex items-center justify-center gap-2">
                            <ArrowLeft :size="20" /> Kembali
                        </button>
                        <button @click="nextStep" :disabled="cartItems.length === 0"
                            class="flex-[2] py-4 bg-primary-600 hover:bg-primary-500 disabled:opacity-50 text-white rounded-xl font-bold text-lg shadow-xl shadow-primary-500/20 transition-all flex items-center justify-center gap-3">
                            Lanjut ke Pembayaran
                            <ArrowRight :size="20" />
                        </button>
                    </div>
                </div>

                <!-- Cart Sidebar (Sticky in step 3) -->
                <div
                    class="w-full lg:w-96 flex flex-col bg-white dark:bg-surface-800 rounded-2xl border border-surface-200 dark:border-surface-700 shadow-xl overflow-hidden">
                    <div
                        class="p-4 border-b border-surface-100 dark:border-surface-700 bg-surface-50 dark:bg-surface-900 flex items-center justify-between font-bold">
                        <div class="flex items-center gap-2">
                            <ShoppingCart :size="18" class="text-primary-500" /> Keranjang ({{ cartItemCount }})
                        </div>
                        <span class="text-[10px] text-text-secondary font-bold uppercase">{{ salesAccount }}</span>
                    </div>
                    <div class="flex-1 overflow-y-auto p-4 custom-scrollbar">
                        <div v-if="cartItems.length === 0"
                            class="h-full flex flex-col items-center justify-center text-text-secondary opacity-50">
                            <ShoppingCart :size="48" class="mb-4" />
                            <p class="text-sm">Kosong</p>
                        </div>
                        <div v-else class="space-y-4">
                            <div v-for="item in cartItems" :key="item.id"
                                class="pb-4 border-b border-surface-100 dark:border-surface-700 last:border-0 relative">
                                <div class="flex justify-between items-start mb-2 pr-6">
                                    <div class="min-w-0">
                                        <p class="text-xs font-bold text-text-primary truncate">{{ item.product?.name ||
                                            item.name }}</p>
                                        <p class="text-[10px] font-mono text-text-secondary">{{ item.imei }}</p>
                                    </div>
                                    <button @click="removeFromCart(item.id)"
                                        class="text-red-400 hover:text-red-500 absolute top-0 right-0">
                                        <Trash2 :size="16" />
                                    </button>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span
                                        class="text-xs px-2 py-0.5 bg-surface-50 dark:bg-surface-900 rounded text-text-secondary font-bold">QTY:
                                        1</span>
                                    <p class="text-xs font-black text-primary-500">{{ formatCurrency(item.selling_price
                                        || item.price) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        class="p-4 bg-surface-50 dark:bg-surface-900 mt-auto border-t border-surface-100 dark:border-surface-700">
                        <div class="flex justify-between mb-3 text-lg font-black text-text-primary">
                            <span>Total</span>
                            <span class="text-primary-600">{{ formatCurrency(cartTotal) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- STEP 4: FORM & PAYMENT -->
            <div v-if="currentStep === 4"
                class="flex-1 flex flex-col lg:flex-row gap-6 min-h-0 overflow-y-auto custom-scrollbar">
                <!-- Transaction Summary & Form -->
                <div class="flex-1 space-y-6">
                    <div
                        class="bg-white dark:bg-surface-800 rounded-3xl border border-surface-200 dark:border-surface-700 p-8 shadow-xl">
                        <h3 class="text-xl font-bold text-text-primary mb-6 flex items-center gap-2">
                            <Receipt :size="22" class="text-primary-500" /> Detail Formulir
                        </h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-semibold text-text-primary mb-2">Nama Pelanggan
                                    (Opsional)</label>
                                <input v-model="customerForm.customer_name" type="text" placeholder="Nama..."
                                    class="w-full border border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 text-text-primary focus:outline-none focus:border-primary-500 transition-all" />
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-text-primary mb-2">HP Pelanggan
                                    (Opsional)</label>
                                <input v-model="customerForm.customer_phone" type="text" placeholder="08..."
                                    class="w-full border border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 text-text-primary focus:outline-none focus:border-primary-500 transition-all" />
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-semibold text-text-primary mb-2">Catatan
                                    Tambahan</label>
                                <textarea v-model="customerForm.notes" rows="3"
                                    placeholder="Tambahkan catatan khusus jika ada..."
                                    class="w-full border border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 text-text-primary focus:outline-none focus:border-primary-500 transition-all"></textarea>
                            </div>
                        </div>
                    </div>

                    <div
                        class="bg-white dark:bg-surface-800 rounded-3xl border border-surface-200 dark:border-surface-700 p-8 shadow-xl">
                        <h3 class="text-xl font-bold text-text-primary mb-6 flex items-center gap-2">
                            <ShoppingCart :size="22" class="text-primary-500" /> Ringkasan Barang
                        </h3>
                        <div class="space-y-4">
                            <div v-for="item in cartItems" :key="item.id"
                                class="flex justify-between items-center bg-surface-50 dark:bg-surface-900 p-4 rounded-2xl">
                                <div class="flex items-center gap-4">
                                    <div
                                        class="w-10 h-10 bg-primary-500/10 text-primary-500 rounded-lg flex items-center justify-center font-bold text-xs">
                                        {{ item.quantity }}x</div>
                                    <div>
                                        <p class="font-bold text-sm text-text-primary">{{ item.name }}</p>
                                        <p class="text-[10px] text-text-secondary">{{ formatCurrency(item.price) }} /
                                            unit</p>
                                    </div>
                                </div>
                                <p class="font-bold text-text-primary">{{ formatCurrency(item.price * item.quantity) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Block -->
                <div class="w-full lg:w-[400px]">
                    <div
                        class="bg-white dark:bg-surface-800 rounded-3xl border border-surface-200 dark:border-surface-700 p-8 shadow-xl sticky top-0">
                        <div class="text-center mb-8">
                            <p class="text-text-secondary text-sm font-bold uppercase tracking-widest mb-1">TOTAL
                                TAGIHAN</p>
                            <p class="text-4xl font-extrabold text-primary-600">{{ formatCurrency(cartTotal) }}</p>
                        </div>

                        <div class="space-y-6">
                            <div>
                                <p class="text-sm font-bold text-text-primary mb-4">Pilih Pembayaran</p>
                                <div class="grid grid-cols-3 gap-2">
                                    <button v-for="method in paymentMethods" :key="method.id"
                                        @click="selectedPaymentMethod = method.id"
                                        class="p-4 rounded-2xl border-2 transition-all flex flex-col items-center gap-2"
                                        :class="selectedPaymentMethod === method.id ? 'border-primary-500 bg-primary-500/10 text-primary-600' : 'border-surface-100 dark:border-surface-700 text-text-secondary'">
                                        <component :is="method.icon" :size="24" />
                                        <span class="text-[10px] font-bold">{{ method.label }}</span>
                                    </button>
                                </div>
                            </div>

                            <div v-if="selectedPaymentMethod === 'cash'">
                                <p class="text-sm font-bold text-text-primary mb-2">Jumlah Pembayaran</p>
                                <input v-model.number="paymentAmount" type="number"
                                    class="w-full border border-surface-200 dark:border-surface-700 rounded-2xl px-5 py-4 bg-surface-50 dark:bg-surface-900 text-text-primary text-2xl font-black text-center focus:outline-none focus:border-primary-500 transition-all" />
                                <div class="grid grid-cols-2 gap-2 mt-2">
                                    <button @click="setQuickAmount(cartTotal)"
                                        class="py-2 text-xs font-bold bg-primary-500/10 text-primary-600 rounded-xl">Uang
                                        Pas</button>
                                    <button @click="setQuickAmount(100000)"
                                        class="py-2 text-xs font-bold bg-surface-100 dark:bg-surface-700 rounded-xl">Rp
                                        100.000</button>
                                </div>
                            </div>

                            <!-- Discount Section -->
                            <div class="h-px bg-surface-100 dark:bg-surface-700 my-4"></div>
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <p class="text-sm font-bold text-text-primary">Diskon</p>
                                    <div class="flex gap-1">
                                        <button @click="cartStore.discountType = 'percentage'"
                                            class="px-2 py-1 text-[10px] rounded-lg font-bold transition-all"
                                            :class="cartStore.discountType === 'percentage' ? 'bg-primary-500 text-white' : 'bg-surface-100 dark:bg-surface-700 text-text-secondary'">%</button>
                                        <button @click="cartStore.discountType = 'fixed'"
                                            class="px-2 py-1 text-[10px] rounded-lg font-bold transition-all"
                                            :class="cartStore.discountType === 'fixed' ? 'bg-primary-500 text-white' : 'bg-surface-100 dark:bg-surface-700 text-text-secondary'">Rp</button>
                                    </div>
                                </div>
                                <div class="relative">
                                    <span v-if="cartStore.discountType === 'fixed'"
                                        class="absolute left-4 top-1/2 -translate-y-1/2 text-text-secondary text-sm font-bold">Rp</span>
                                    <input v-model.number="cartStore.discount" type="number"
                                        class="w-full border border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 text-text-primary focus:outline-none focus:border-primary-500 transition-all font-bold"
                                        :class="cartStore.discountType === 'fixed' ? 'pl-10' : ''" placeholder="0" />
                                    <span v-if="cartStore.discountType === 'percentage'"
                                        class="absolute right-4 top-1/2 -translate-y-1/2 text-text-secondary text-sm font-bold">%</span>
                                </div>
                            </div>

                            <div v-if="cartStore.discountAmount > 0"
                                class="p-3 bg-primary-500/10 rounded-xl flex justify-between items-center text-xs font-bold text-primary-600">
                                <span>Potongan Diskon</span>
                                <span>- {{ formatCurrency(cartStore.discountAmount) }}</span>
                            </div>

                            <div class="h-px bg-surface-100 dark:bg-surface-700 my-4"></div>

                            <div v-if="selectedPaymentMethod === 'cash'">
                                <div v-if="changeAmount >= 0"
                                    class="mt-4 p-4 bg-emerald-500/10 border border-emerald-500/20 rounded-2xl flex justify-between items-center">
                                    <span class="text-xs font-bold text-emerald-600">Kembalian</span>
                                    <span class="text-xl font-black text-emerald-600">{{ formatCurrency(changeAmount)
                                        }}</span>
                                </div>
                                <div v-else
                                    class="mt-4 p-4 bg-red-500/10 border border-red-500/20 rounded-2xl text-center text-red-500 text-xs font-bold">
                                    Pembayaran kurang {{ formatCurrency(Math.abs(changeAmount)) }}
                                </div>
                            </div>

                            <div class="flex gap-4 pt-4">
                                <button @click="prevStep"
                                    class="flex-1 py-4 bg-surface-100 dark:bg-surface-700 text-text-primary rounded-2xl font-bold transition-all flex items-center justify-center">
                                    <ArrowLeft :size="20" />
                                </button>
                                <button @click="handleSubmitOrder"
                                    :disabled="(selectedPaymentMethod === 'cash' && changeAmount < 0) || isSubmitting"
                                    class="flex-[3] py-4 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white rounded-2xl font-black text-lg shadow-xl shadow-emerald-500/20 transition-all flex items-center justify-center gap-2">
                                    <Loader2 v-if="isSubmitting" class="animate-spin mr-2" />
                                    <CheckCircle v-else :size="24" />
                                    {{ isSubmitting ? 'MEMPROSES...' : 'SELESAIKAN PROSES' }}
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
                <div class="absolute inset-0 bg-black/80 backdrop-blur-md"></div>
                <div
                    class="relative bg-white dark:bg-surface-800 rounded-[40px] border border-surface-200 dark:border-surface-700 w-full max-w-sm p-10 text-center shadow-2xl">
                    <div
                        class="w-24 h-24 mx-auto mb-6 bg-emerald-500/20 rounded-full flex items-center justify-center animate-bounce">
                        <CheckCircle class="text-emerald-500" :size="48" />
                    </div>
                    <h3 class="text-2xl font-black text-text-primary mb-2">Suksess!</h3>
                    <p class="text-text-secondary mb-8">Transaksi telah tersimpan</p>

                    <div v-if="lastTransaction"
                        class="bg-surface-50 dark:bg-surface-900 rounded-3xl p-6 mb-8 text-left space-y-3">
                        <div class="flex justify-between text-xs">
                            <span class="text-text-secondary">Receipt ID</span>
                            <span class="font-mono font-bold">{{ lastTransaction.id }}</span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span class="text-text-secondary">Sales</span>
                            <span class="font-bold">{{ lastTransaction.sales_account }}</span>
                        </div>
                        <div class="h-px bg-surface-200 dark:bg-surface-700"></div>
                        <div class="flex justify-between text-xl font-black">
                            <span class="text-text-primary">Total</span>
                            <span class="text-emerald-500">{{ formatCurrency(lastTransaction.total) }}</span>
                        </div>
                    </div>

                    <button @click="closeSuccessModal"
                        class="w-full py-4 bg-primary-600 hover:bg-primary-500 text-white rounded-2xl font-bold transition-all shadow-xl shadow-primary-500/30 mb-3">
                        Mulai Transaksi Baru
                    </button>
                    <button class="w-full py-3 text-text-secondary font-bold flex items-center justify-center gap-2">
                        <Receipt :size="18" /> Cetak Struk
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
