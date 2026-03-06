<script setup>
import { ref, computed, onMounted } from "vue";
import api from "../../api/axios";
import { useEscapeKey } from "../../composables/useEscapeKey";
import { useCartStore } from "../../store/cart";
import { useInventoryStore } from "../../store/inventory";
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
} from "lucide-vue-next";

const cartStore = useCartStore();
const inventoryStore = useInventoryStore();

// Pre-sales setup
const isSetupComplete = ref(false);
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

function completeSetup() {
    if (!salesAccount.value) {
        alert("Silakan isi nama Akun Sales terlebih dahulu.");
        return;
    }
    isSetupComplete.value = true;
}

const searchQuery = ref("");
const selectedCategory = ref(null);

// Payment modal
const showPaymentModal = ref(false);
const paymentAmount = ref(0);
const selectedPaymentMethod = ref("cash");

// Success modal
const showSuccessModal = ref(false);
const lastTransaction = ref(null);

onMounted(async () => {
    inventoryStore.fetchProducts();
    try {
        const response = await api.get('/inventory/my-accounts');
        salesAccounts.value = response.data.data || response.data;
    } catch (e) {
        console.error("Gagal memuat akun sales", e);
    }
});

const filteredProducts = computed(() => {
    let products = inventoryStore.products;
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        products = products.filter(
            (p) =>
                p.name.toLowerCase().includes(query) ||
                p.sku.toLowerCase().includes(query)
        );
    }
    if (selectedCategory.value) {
        products = products.filter((p) => p.category === selectedCategory.value);
    }
    return products;
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
    if (product.stock > 0) {
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

function openPayment() {
    if (cartItems.value.length === 0) return;
    paymentAmount.value = cartTotal.value;
    showPaymentModal.value = true;
}

async function processPayment() {
    if (!salesAccount.value || !transactionCategory.value) {
        alert("Pilih akun sales dan kategori terlebih dahulu!");
        return;
    }

    try {
        const formData = new FormData();
        formData.append('category', transactionCategory.value);
        formData.append('sales_account', salesAccount.value);
        formData.append('payment_method', selectedPaymentMethod.value);
        formData.append('paid_amount', paymentAmount.value);
        formData.append('selling_price', cartTotal.value);

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

        showPaymentModal.value = false;
        showSuccessModal.value = true;
        cartStore.clearCart();
        paymentAmount.value = 0;
    } catch (error) {
        console.error("Payment failed", error);
        alert(error.response?.data?.message || "Gagal memproses pembayaran");
    }
}

function closeSuccessModal() {
    showSuccessModal.value = false;
    lastTransaction.value = null;
}

useEscapeKey(() => {
    if (showPaymentModal.value) showPaymentModal.value = false;
    else if (showSuccessModal.value) closeSuccessModal();
});

function setQuickAmount(amount) {
    paymentAmount.value = amount;
}

const changeAmount = computed(() => paymentAmount.value - cartTotal.value);

function scrollToCart() {
    const cartElement = document.getElementById('sales-cart-section');
    if (cartElement) {
        cartElement.scrollIntoView({ behavior: 'smooth' });
    }
}
</script>

<template>
    <div class="flex flex-col lg:flex-row h-auto lg:h-[calc(100vh-8rem)] gap-6">
        <!-- Products Section or Setup Section -->
        <div class="flex-1 flex flex-col min-w-0">
            <!-- Setup Section (Shows before selecting products) -->
            <div v-if="!isSetupComplete"
                class="bg-white dark:bg-surface-800 rounded-2xl border border-surface-200 dark:border-surface-700 p-8 shadow-sm max-w-2xl mx-auto w-full mt-8">
                <div class="text-center mb-8">
                    <div
                        class="w-16 h-16 bg-primary-500/10 text-primary-500 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <User :size="32" />
                    </div>
                    <h2 class="text-2xl font-bold text-text-primary">Mulai Penjualan Baru</h2>
                    <p class="text-text-secondary mt-2">Pilih akun sales dan kategori transaksi terlebih dahulu</p>
                </div>

                <div class="space-y-6">
                    <div>
                        <label class="block text-sm font-semibold text-text-primary mb-2">Akun Sales Utama</label>
                        <select v-model="salesAccount"
                            class="w-full border border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-white dark:bg-surface-900 text-text-primary focus:outline-none focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 transition-all">
                            <option value="" disabled>-- Pilih Akun Sales --</option>
                            <option v-for="account in salesAccounts" :key="account.id"
                                :value="account.full_name || account.name">
                                {{ account.full_name || account.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-text-primary mb-2">Kategori Transaksi</label>
                        <div class="grid grid-cols-2 gap-3">
                            <button v-for="cat in categoriesPenjualan" :key="cat.id"
                                @click="transactionCategory = cat.id"
                                class="p-3 rounded-xl border-2 transition-all font-medium text-sm text-center"
                                :class="transactionCategory === cat.id
                                    ? 'border-primary-500 bg-primary-500/10 text-primary-600 dark:text-primary-400'
                                    : 'border-surface-200 dark:border-surface-700 text-text-secondary hover:border-surface-300 dark:hover:border-surface-600'">
                                {{ cat.label }}
                            </button>
                        </div>
                    </div>

                    <button @click="completeSetup"
                        class="w-full py-4 bg-primary-600 hover:bg-primary-500 text-white rounded-xl font-bold shadow-lg shadow-primary-500/20 transition-all mt-4">
                        Lanjut Pilih Produk
                    </button>
                </div>
            </div>

            <!-- Products View (Shows after setup complete) -->
            <template v-else>
                <!-- Header -->
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h1 class="text-2xl font-bold text-text-primary">Buat Penjualan</h1>
                        <p class="text-text-secondary text-sm mt-1">Pilih produk untuk transaksi
                            <span class="font-bold text-primary-500">{{categoriesPenjualan.find(c => c.id ===
                                transactionCategory)?.label}}</span>
                            oleh <span class="font-bold text-primary-500">{{ salesAccount }}</span>
                        </p>
                    </div>
                    <button @click="isSetupComplete = false"
                        class="text-sm font-medium text-text-secondary hover:text-primary-500 flex items-center gap-1 transition-colors">
                        <User :size="16" /> Ganti Sales/Kategori
                    </button>
                </div>

                <!-- Search & Filters -->
                <div class="flex items-center gap-3 mb-6">
                    <div class="relative flex-1">
                        <Search class="absolute left-4 top-1/2 -translate-y-1/2 text-text-secondary" :size="18" />
                        <input v-model="searchQuery" type="text" placeholder="Cari produk..."
                            class="w-full border border-surface-700 rounded-xl pl-12 pr-4 py-3 bg-white dark:bg-surface-800 text-text-primary focus:outline-none focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 transition-all placeholder:text-surface-500"
                            autofocus />
                    </div>
                    <button @click="scrollToCart"
                        class="lg:hidden relative p-3 bg-white dark:bg-surface-800 border border-surface-200 dark:border-surface-700 rounded-xl text-primary-500 active:scale-95 transition-all">
                        <ShoppingCart :size="20" />
                        <span v-if="cartItemCount > 0"
                            class="absolute -top-1 -right-1 w-5 h-5 bg-primary-600 text-white text-[10px] font-bold flex items-center justify-center rounded-full border-2 border-white dark:border-surface-900">
                            {{ cartItemCount }}
                        </span>
                    </button>
                </div>

                <!-- Categories -->
                <div class="flex gap-2 mb-6 overflow-x-auto pb-2">
                    <button @click="selectedCategory = null"
                        class="px-4 py-2 rounded-xl text-sm font-medium whitespace-nowrap transition-all" :class="!selectedCategory
                            ? 'bg-primary-600 text-white shadow-lg shadow-primary-500/20'
                            : 'bg-white dark:bg-surface-800 text-text-secondary border border-surface-200 dark:border-surface-700 hover:border-primary-500 hover:text-primary-500'
                            ">
                        Semua
                    </button>
                    <button v-for="cat in categories" :key="cat.id" @click="selectedCategory = cat.name"
                        class="px-4 py-2 rounded-xl text-sm font-medium whitespace-nowrap transition-all" :class="selectedCategory === cat.name
                            ? 'bg-primary-600 text-white shadow-lg shadow-primary-500/20'
                            : 'bg-white dark:bg-surface-800 text-text-secondary border border-surface-200 dark:border-surface-700 hover:border-primary-500 hover:text-primary-500'
                            ">
                        {{ cat.name }}
                    </button>
                </div>

                <!-- Products Grid -->
                <div class="flex-1 overflow-y-auto custom-scrollbar">
                    <div v-if="inventoryStore.isLoading" class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                        <div v-for="i in 8" :key="i"
                            class="bg-white dark:bg-surface-800 rounded-2xl border border-surface-200 dark:border-surface-700 h-48 animate-pulse">
                        </div>
                    </div>

                    <div v-else-if="filteredProducts.length === 0"
                        class="flex flex-col items-center justify-center h-64 text-text-secondary">
                        <Search :size="48" class="mb-4 opacity-50" />
                        <p>Tidak ada produk ditemukan</p>
                    </div>

                    <div v-else class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                        <div v-for="product in filteredProducts" :key="product.id" @click="addToCart(product)"
                            class="bg-white dark:bg-surface-800 rounded-2xl border border-surface-200 dark:border-surface-700 p-4 cursor-pointer group active:scale-[0.98] transition-all hover:border-primary-500/50 hover:shadow-lg hover:shadow-primary-500/5"
                            :class="{ 'opacity-50 cursor-not-allowed': product.stock === 0 }">
                            <div
                                class="h-24 bg-surface-100 dark:bg-surface-700 rounded-xl mb-3 flex items-center justify-center">
                                <span class="text-3xl">📱</span>
                            </div>
                            <h3
                                class="font-semibold text-text-primary text-sm truncate group-hover:text-primary-500 transition-colors">
                                {{ product.name }}
                            </h3>
                            <p class="text-primary-500 font-bold mt-1">
                                {{ formatCurrency(product.price) }}
                            </p>
                            <div class="flex items-center justify-between mt-2">
                                <span class="text-xs px-2 py-0.5 rounded-full" :class="product.stock > product.minStock
                                    ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                    : product.stock > 0
                                        ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400'
                                        : 'bg-red-500/10 text-red-600 dark:text-red-400'
                                    ">
                                    Stok: {{ product.stock }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Cart Section -->
        <div id="sales-cart-section"
            class="w-full lg:w-96 bg-white dark:bg-surface-800 rounded-2xl border border-surface-200 dark:border-surface-700 flex flex-col h-[600px] lg:h-auto shadow-2xl lg:shadow-none">
            <div class="p-4 border-b border-surface-200 dark:border-surface-700">
                <div class="flex items-center gap-2">
                    <ShoppingCart class="text-primary-500" :size="20" />
                    <h2 class="text-lg font-bold text-text-primary">Keranjang</h2>
                    <span v-if="cartItemCount > 0"
                        class="ml-auto bg-primary-600 text-white text-xs font-bold px-2 py-0.5 rounded-full">
                        {{ cartItemCount }}
                    </span>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto p-4 custom-scrollbar">
                <div v-if="cartItems.length === 0"
                    class="flex flex-col items-center justify-center h-full text-text-secondary">
                    <ShoppingCart :size="48" class="mb-4 opacity-50" />
                    <p>Keranjang masih kosong</p>
                    <p class="text-sm mt-1">Klik produk untuk menambahkan</p>
                </div>

                <div v-else class="space-y-3">
                    <div v-for="item in cartItems" :key="item.id"
                        class="bg-surface-50 dark:bg-surface-900 rounded-xl p-3 border border-surface-200 dark:border-surface-700">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex-1 min-w-0">
                                <h4 class="text-sm font-medium text-text-primary truncate">
                                    {{ item.name }}
                                </h4>
                                <p class="text-primary-500 text-sm font-semibold">
                                    {{ formatCurrency(item.price) }}
                                </p>
                            </div>
                            <button @click.stop="removeFromCart(item.id)"
                                class="p-1 text-text-secondary hover:text-red-500 transition-colors">
                                <Trash2 :size="16" />
                            </button>
                        </div>

                        <div class="flex items-center justify-between mt-3">
                            <div class="flex items-center gap-2">
                                <button @click.stop="decrementQty(item.id)"
                                    class="w-7 h-7 rounded-lg bg-surface-200 dark:bg-surface-700 hover:bg-surface-300 dark:hover:bg-surface-600 flex items-center justify-center transition-colors">
                                    <Minus :size="14" />
                                </button>
                                <span class="w-8 text-center text-text-primary font-semibold">{{
                                    item.quantity
                                }}</span>
                                <button @click.stop="incrementQty(item.id)"
                                    class="w-7 h-7 rounded-lg bg-surface-200 dark:bg-surface-700 hover:bg-surface-300 dark:hover:bg-surface-600 flex items-center justify-center transition-colors"
                                    :disabled="item.quantity >= item.stock" :class="{
                                        'opacity-50 cursor-not-allowed': item.quantity >= item.stock,
                                    }">
                                    <Plus :size="14" />
                                </button>
                            </div>
                            <span class="text-text-primary font-bold">
                                {{ formatCurrency(item.price * item.quantity) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cart Footer -->
            <div class="p-4 border-t border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-900/50">
                <div class="space-y-2 mb-4">
                    <div class="flex justify-between text-sm">
                        <span class="text-text-secondary">Subtotal</span>
                        <span class="text-text-primary">{{ formatCurrency(cartSubtotal) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-text-secondary">Diskon</span>
                        <span class="text-emerald-500">- {{ formatCurrency(cartStore.discountAmount) }}</span>
                    </div>
                    <div class="h-px bg-surface-200 dark:bg-surface-700"></div>
                    <div class="flex justify-between">
                        <span class="text-text-primary font-semibold">Total</span>
                        <span class="text-xl font-bold text-primary-500">{{
                            formatCurrency(cartTotal)
                        }}</span>
                    </div>
                </div>

                <button @click="openPayment" :disabled="cartItems.length === 0"
                    class="flex items-center justify-center gap-2 w-full py-4 text-base font-bold rounded-xl bg-primary-600 hover:bg-primary-500 text-white shadow-lg shadow-primary-500/20 disabled:opacity-50 disabled:cursor-not-allowed transition-all">
                    <CreditCard :size="20" />
                    BAYAR SEKARANG
                </button>
            </div>
        </div>

        <!-- Payment Modal -->
        <Teleport to="body">
            <div v-if="showPaymentModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showPaymentModal = false"></div>

                <div
                    class="relative bg-white dark:bg-surface-800 rounded-2xl border border-surface-200 dark:border-surface-700 w-full max-w-md p-6 shadow-2xl">
                    <button @click="showPaymentModal = false"
                        class="absolute top-4 right-4 p-2 text-text-secondary hover:text-text-primary transition-colors">
                        <X :size="20" />
                    </button>

                    <h3 class="text-xl font-bold text-text-primary mb-6">Pembayaran</h3>

                    <div class="bg-surface-50 dark:bg-surface-900 rounded-xl p-4 mb-6">
                        <p class="text-text-secondary text-sm">Total Pembayaran</p>
                        <p class="text-3xl font-bold text-text-primary">
                            {{ formatCurrency(cartTotal) }}
                        </p>
                    </div>

                    <div class="mb-6">
                        <p class="text-sm text-text-secondary mb-3">Metode Pembayaran</p>
                        <div class="grid grid-cols-3 gap-2">
                            <button v-for="method in paymentMethods" :key="method.id"
                                @click="selectedPaymentMethod = method.id"
                                class="p-3 rounded-xl border-2 transition-all flex flex-col items-center gap-2" :class="selectedPaymentMethod === method.id
                                    ? 'border-primary-500 bg-primary-500/10'
                                    : 'border-surface-200 dark:border-surface-700 hover:border-surface-300 dark:hover:border-surface-600'
                                    ">
                                <component :is="method.icon" :size="24"
                                    :class="selectedPaymentMethod === method.id ? 'text-primary-500' : 'text-text-secondary'" />
                                <span class="text-xs font-medium"
                                    :class="selectedPaymentMethod === method.id ? 'text-primary-500' : 'text-text-secondary'">
                                    {{ method.label }}
                                </span>
                            </button>
                        </div>
                    </div>

                    <div v-if="selectedPaymentMethod === 'cash'" class="mb-6">
                        <p class="text-sm text-text-secondary mb-3">Jumlah Uang</p>
                        <input v-model.number="paymentAmount" type="number"
                            class="w-full border border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-white dark:bg-surface-900 text-text-primary text-center text-xl font-bold focus:outline-none focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 transition-all" />

                        <div class="grid grid-cols-4 gap-2 mt-3">
                            <button v-for="amount in [50000, 100000, 150000, 200000]" :key="amount"
                                @click="setQuickAmount(amount)"
                                class="py-2 text-xs font-medium bg-surface-100 dark:bg-surface-700 hover:bg-surface-200 dark:hover:bg-surface-600 rounded-lg transition-colors text-text-primary">
                                {{ formatCurrency(amount) }}
                            </button>
                        </div>

                        <button @click="setQuickAmount(cartTotal)"
                            class="w-full mt-2 py-2 text-sm font-medium text-primary-500 bg-primary-500/10 hover:bg-primary-500/20 rounded-lg transition-colors">
                            Uang Pas
                        </button>

                        <div v-if="changeAmount >= 0"
                            class="mt-4 p-3 bg-emerald-500/10 border border-emerald-500/30 rounded-xl">
                            <div class="flex justify-between items-center">
                                <span class="text-emerald-600 dark:text-emerald-400">Kembalian</span>
                                <span class="text-xl font-bold text-emerald-600 dark:text-emerald-400">{{
                                    formatCurrency(changeAmount)
                                }}</span>
                            </div>
                        </div>
                        <div v-else class="mt-4 p-3 bg-red-500/10 border border-red-500/30 rounded-xl">
                            <div class="flex items-center gap-2 text-red-500">
                                <AlertCircle :size="16" />
                                <span class="text-sm">Uang kurang
                                    {{ formatCurrency(Math.abs(changeAmount)) }}</span>
                            </div>
                        </div>
                    </div>

                    <button @click="processPayment" :disabled="selectedPaymentMethod === 'cash' && changeAmount < 0"
                        class="flex items-center justify-center gap-2 w-full py-4 text-base font-bold rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white shadow-lg shadow-emerald-500/20 disabled:opacity-50 disabled:cursor-not-allowed transition-all">
                        <Receipt :size="20" />
                        Proses Pembayaran
                    </button>
                </div>
            </div>
        </Teleport>

        <!-- Success Modal -->
        <Teleport to="body">
            <div v-if="showSuccessModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>

                <div
                    class="relative bg-white dark:bg-surface-800 rounded-2xl border border-surface-200 dark:border-surface-700 w-full max-w-sm p-6 text-center shadow-2xl">
                    <div class="w-20 h-20 mx-auto mb-4 bg-emerald-500/20 rounded-full flex items-center justify-center">
                        <CheckCircle class="text-emerald-500" :size="40" />
                    </div>

                    <h3 class="text-xl font-bold text-text-primary mb-2">
                        Pembayaran Berhasil!
                    </h3>
                    <p class="text-text-secondary text-sm mb-6">
                        Transaksi telah selesai diproses
                    </p>

                    <div v-if="lastTransaction" class="bg-surface-50 dark:bg-surface-900 rounded-xl p-4 mb-6 text-left">
                        <div class="flex justify-between text-sm mb-2">
                            <span class="text-text-secondary">No. Transaksi</span>
                            <span class="text-text-primary font-mono">{{ lastTransaction.id }}</span>
                        </div>
                        <div class="flex justify-between text-sm mb-2">
                            <span class="text-text-secondary">Total</span>
                            <span class="text-text-primary">{{
                                formatCurrency(lastTransaction.total)
                            }}</span>
                        </div>
                        <div v-if="lastTransaction.change > 0" class="flex justify-between text-sm">
                            <span class="text-text-secondary">Kembalian</span>
                            <span class="text-emerald-500 font-bold">{{
                                formatCurrency(lastTransaction.change)
                            }}</span>
                        </div>
                    </div>

                    <div class="flex gap-3">
                        <button @click="closeSuccessModal"
                            class="flex-1 py-3 rounded-xl font-bold text-text-secondary bg-surface-100 dark:bg-surface-700 hover:bg-surface-200 dark:hover:bg-surface-600 transition-colors">
                            Tutup
                        </button>
                        <button
                            class="flex-1 py-3 rounded-xl font-bold flex items-center justify-center gap-2 bg-primary-600 hover:bg-primary-500 text-white transition-colors">
                            <Receipt :size="16" />
                            Cetak Struk
                        </button>
                    </div>
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
    background-color: rgba(156, 163, 175, 0.3);
    border-radius: 9999px;
}
</style>
