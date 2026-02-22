<script setup>
import { ref, computed, onMounted } from "vue";
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
  User,
  Percent,
  Receipt,
  CheckCircle,
  AlertCircle,
} from "lucide-vue-next";

const cartStore = useCartStore();
const inventoryStore = useInventoryStore();

// Search & filters
const searchQuery = ref("");
const selectedCategory = ref(null);

// Payment modal
const showPaymentModal = ref(false);
const paymentAmount = ref(0);
const selectedPaymentMethod = ref("cash");

// Success modal
const showSuccessModal = ref(false);
const lastTransaction = ref(null);

// Fetch products on mount
onMounted(() => {
  inventoryStore.fetchProducts();
});

// Filtered products
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

// Categories
const categories = computed(() => inventoryStore.categories);

// Cart items from store
const cartItems = computed(() => cartStore.items);
const cartTotal = computed(() => cartStore.total);
const cartSubtotal = computed(() => cartStore.subtotal);
const cartItemCount = computed(() => cartStore.itemCount);

// Payment methods
const paymentMethods = [
  { id: "cash", label: "Tunai", icon: Banknote },
  { id: "transfer", label: "Transfer", icon: CreditCard },
  { id: "qris", label: "QRIS", icon: QrCode },
];

// Actions
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

function processPayment() {
  // Simulate payment processing
  const change = paymentAmount.value - cartTotal.value;

  lastTransaction.value = {
    id: "TRX-" + Date.now(),
    items: [...cartItems.value],
    total: cartTotal.value,
    paid: paymentAmount.value,
    change: change,
    method: selectedPaymentMethod.value,
    time: new Date().toLocaleString("id-ID"),
  };

  showPaymentModal.value = false;
  showSuccessModal.value = true;

  // Clear cart after success
  cartStore.clearCart();
  paymentAmount.value = 0;
}

function closeSuccessModal() {
  showSuccessModal.value = false;
  lastTransaction.value = null;
}

useEscapeKey(() => {
  if (showPaymentModal.value) showPaymentModal.value = false;
  else if (showSuccessModal.value) closeSuccessModal();
});

// Quick amounts for cash payment
function setQuickAmount(amount) {
  paymentAmount.value = amount;
}

const changeAmount = computed(() => paymentAmount.value - cartTotal.value);

function scrollToCart() {
  const cartElement = document.getElementById('cart-section');
  if (cartElement) {
    cartElement.scrollIntoView({ behavior: 'smooth' });
  }
}
</script>

<template>
  <div class="flex flex-col lg:flex-row h-auto lg:h-[calc(100vh-8rem)] gap-6">
    <!-- Products Section -->
    <div class="flex-1 flex flex-col min-w-0">
      <!-- Search & Filters -->
      <div class="flex items-center gap-3 mb-6">
        <div class="relative flex-1">
          <Search class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-500" :size="18" />
          <input v-model="searchQuery" type="text" placeholder="Cari produk..." class="input pl-12 h-12" autofocus />
        </div>
        <button @click="scrollToCart"
          class="lg:hidden relative p-3 bg-surface-800 border border-surface-700 rounded-xl text-primary-400 group active:scale-95 transition-all">
          <ShoppingCart :size="20" />
          <span v-if="cartItemCount > 0"
            class="absolute -top-1 -right-1 w-5 h-5 bg-primary-600 text-white text-[10px] font-bold flex items-center justify-center rounded-full border-2 border-surface-900">
            {{ cartItemCount }}
          </span>
        </button>
      </div>

      <!-- Categories -->
      <div class="flex gap-2 mb-6 overflow-x-auto pb-2">
        <button @click="selectedCategory = null"
          class="px-4 py-2 rounded-xl text-sm font-medium whitespace-nowrap transition-all" :class="!selectedCategory
            ? 'bg-blue-600 text-white'
            : 'bg-slate-800 text-slate-400 hover:bg-slate-700 hover:text-white'
            ">
          Semua
        </button>
        <button v-for="cat in categories" :key="cat.id" @click="selectedCategory = cat.name"
          class="px-4 py-2 rounded-xl text-sm font-medium whitespace-nowrap transition-all" :class="selectedCategory === cat.name
            ? 'bg-blue-600 text-white'
            : 'bg-slate-800 text-slate-400 hover:bg-slate-700 hover:text-white'
            ">
          {{ cat.name }}
        </button>
      </div>

      <!-- Products Grid -->
      <div class="flex-1 overflow-y-auto custom-scrollbar">
        <div v-if="inventoryStore.isLoading" class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
          <div v-for="i in 8" :key="i" class="card skeleton h-48"></div>
        </div>

        <div v-else-if="filteredProducts.length === 0"
          class="flex flex-col items-center justify-center h-64 text-slate-500">
          <Search :size="48" class="mb-4 opacity-50" />
          <p>Tidak ada produk ditemukan</p>
        </div>

        <div v-else class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
          <div v-for="product in filteredProducts" :key="product.id" @click="addToCart(product)"
            class="card card-hover cursor-pointer group active:scale-[0.98] transition-transform"
            :class="{ 'opacity-50 cursor-not-allowed': product.stock === 0 }">
            <!-- Product Image Placeholder -->
            <div class="h-24 bg-surface-700 rounded-xl mb-3 flex items-center justify-center">
              <span class="text-3xl">📱</span>
            </div>

            <!-- Product Info -->
            <h3 class="font-semibold text-white text-sm truncate group-hover:text-primary-400 transition-colors">
              {{ product.name }}
            </h3>
            <p class="text-primary-400 font-bold mt-1">
              {{ formatCurrency(product.price) }}
            </p>
            <div class="flex items-center justify-between mt-2">
              <span class="text-xs px-2 py-0.5 rounded-full" :class="product.stock > product.minStock
                ? 'bg-emerald-500/20 text-emerald-400'
                : product.stock > 0
                  ? 'bg-amber-500/20 text-amber-400'
                  : 'bg-red-500/20 text-red-400'
                ">
                Stok: {{ product.stock }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Cart Section -->
    <div id="cart-section"
      class="w-full lg:w-96 bg-surface-800 rounded-2xl border border-surface-700 flex flex-col h-[600px] lg:h-auto shadow-2xl lg:shadow-none">
      <!-- Cart Header -->
      <div class="p-4 border-b border-surface-700">
        <div class="flex items-center gap-2">
          <ShoppingCart class="text-primary-400" :size="20" />
          <h2 class="text-lg font-bold text-white">Keranjang</h2>
          <span v-if="cartItemCount > 0"
            class="ml-auto bg-primary-600 text-white text-xs font-bold px-2 py-0.5 rounded-full">
            {{ cartItemCount }}
          </span>
        </div>
      </div>

      <!-- Cart Items -->
      <div class="flex-1 overflow-y-auto p-4 custom-scrollbar">
        <div v-if="cartItems.length === 0" class="flex flex-col items-center justify-center h-full text-slate-500">
          <ShoppingCart :size="48" class="mb-4 opacity-50" />
          <p>Keranjang masih kosong</p>
          <p class="text-sm mt-1">Klik produk untuk menambahkan</p>
        </div>

        <div v-else class="space-y-3">
          <div v-for="item in cartItems" :key="item.id" class="bg-surface-900 rounded-xl p-3 border border-surface-700">
            <div class="flex items-start justify-between gap-2">
              <div class="flex-1 min-w-0">
                <h4 class="text-sm font-medium text-white truncate">
                  {{ item.name }}
                </h4>
                <p class="text-blue-400 text-sm font-semibold">
                  {{ formatCurrency(item.price) }}
                </p>
              </div>
              <button @click.stop="removeFromCart(item.id)"
                class="p-1 text-slate-500 hover:text-red-400 transition-colors">
                <Trash2 :size="16" />
              </button>
            </div>

            <div class="flex items-center justify-between mt-3">
              <div class="flex items-center gap-2">
                <button @click.stop="decrementQty(item.id)"
                  class="w-7 h-7 rounded-lg bg-slate-800 hover:bg-slate-700 flex items-center justify-center transition-colors">
                  <Minus :size="14" />
                </button>
                <span class="w-8 text-center text-white font-semibold">{{
                  item.quantity
                }}</span>
                <button @click.stop="incrementQty(item.id)"
                  class="w-7 h-7 rounded-lg bg-slate-800 hover:bg-slate-700 flex items-center justify-center transition-colors"
                  :disabled="item.quantity >= item.stock" :class="{
                    'opacity-50 cursor-not-allowed':
                      item.quantity >= item.stock,
                  }">
                  <Plus :size="14" />
                </button>
              </div>
              <span class="text-white font-bold">
                {{ formatCurrency(item.price * item.quantity) }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Cart Footer -->
      <div class="p-4 border-t border-slate-700/50 bg-slate-900/50">
        <div class="space-y-2 mb-4">
          <div class="flex justify-between text-sm">
            <span class="text-slate-400">Subtotal</span>
            <span class="text-white">{{ formatCurrency(cartSubtotal) }}</span>
          </div>
          <div class="flex justify-between text-sm">
            <span class="text-slate-400">Diskon</span>
            <span class="text-emerald-400">- {{ formatCurrency(cartStore.discountAmount) }}</span>
          </div>
          <div class="h-px bg-slate-700"></div>
          <div class="flex justify-between">
            <span class="text-white font-semibold">Total</span>
            <span class="text-xl font-bold text-emerald-400">{{
              formatCurrency(cartTotal)
            }}</span>
          </div>
        </div>

        <button @click="openPayment" :disabled="cartItems.length === 0"
          class="btn btn-primary w-full py-4 text-base disabled:opacity-50 disabled:cursor-not-allowed">
          <CreditCard :size="20" />
          BAYAR SEKARANG
        </button>
      </div>
    </div>

    <!-- Payment Modal -->
    <Teleport to="body">
      <div v-if="showPaymentModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showPaymentModal = false"></div>

        <div class="relative bg-slate-900 rounded-2xl border border-slate-700 w-full max-w-md p-6 shadow-2xl">
          <button @click="showPaymentModal = false"
            class="absolute top-4 right-4 p-2 text-slate-400 hover:text-white transition-colors">
            <X :size="20" />
          </button>

          <h3 class="text-xl font-bold text-white mb-6">Pembayaran</h3>

          <!-- Total -->
          <div class="bg-slate-800 rounded-xl p-4 mb-6">
            <p class="text-slate-400 text-sm">Total Pembayaran</p>
            <p class="text-3xl font-bold text-white">
              {{ formatCurrency(cartTotal) }}
            </p>
          </div>

          <!-- Payment Methods -->
          <div class="mb-6">
            <p class="text-sm text-slate-400 mb-3">Metode Pembayaran</p>
            <div class="grid grid-cols-3 gap-2">
              <button v-for="method in paymentMethods" :key="method.id" @click="selectedPaymentMethod = method.id"
                class="p-3 rounded-xl border-2 transition-all flex flex-col items-center gap-2" :class="selectedPaymentMethod === method.id
                  ? 'border-blue-500 bg-blue-500/10'
                  : 'border-slate-700 hover:border-slate-600'
                  ">
                <component :is="method.icon" :size="24" :class="selectedPaymentMethod === method.id
                  ? 'text-blue-400'
                  : 'text-slate-400'
                  " />
                <span class="text-xs font-medium" :class="selectedPaymentMethod === method.id
                  ? 'text-blue-400'
                  : 'text-slate-400'
                  ">
                  {{ method.label }}
                </span>
              </button>
            </div>
          </div>

          <!-- Cash Amount -->
          <div v-if="selectedPaymentMethod === 'cash'" class="mb-6">
            <p class="text-sm text-slate-400 mb-3">Jumlah Uang</p>
            <input v-model.number="paymentAmount" type="number" class="input text-center text-xl font-bold" />

            <!-- Quick Amounts -->
            <div class="grid grid-cols-4 gap-2 mt-3">
              <button v-for="amount in [50000, 100000, 150000, 200000]" :key="amount" @click="setQuickAmount(amount)"
                class="py-2 text-xs font-medium bg-slate-800 hover:bg-slate-700 rounded-lg transition-colors">
                {{ formatCurrency(amount) }}
              </button>
            </div>

            <!-- Exact Amount -->
            <button @click="setQuickAmount(cartTotal)"
              class="w-full mt-2 py-2 text-sm font-medium text-blue-400 bg-blue-500/10 hover:bg-blue-500/20 rounded-lg transition-colors">
              Uang Pas
            </button>

            <!-- Change -->
            <div v-if="changeAmount >= 0" class="mt-4 p-3 bg-emerald-500/10 border border-emerald-500/30 rounded-xl">
              <div class="flex justify-between items-center">
                <span class="text-emerald-400">Kembalian</span>
                <span class="text-xl font-bold text-emerald-400">{{
                  formatCurrency(changeAmount)
                }}</span>
              </div>
            </div>
            <div v-else class="mt-4 p-3 bg-red-500/10 border border-red-500/30 rounded-xl">
              <div class="flex items-center gap-2 text-red-400">
                <AlertCircle :size="16" />
                <span class="text-sm">Uang kurang
                  {{ formatCurrency(Math.abs(changeAmount)) }}</span>
              </div>
            </div>
          </div>

          <!-- Process Button -->
          <button @click="processPayment" :disabled="selectedPaymentMethod === 'cash' && changeAmount < 0"
            class="btn btn-success w-full py-4 text-base disabled:opacity-50 disabled:cursor-not-allowed">
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
          class="relative bg-slate-900 rounded-2xl border border-slate-700 w-full max-w-sm p-6 text-center shadow-2xl">
          <div class="w-20 h-20 mx-auto mb-4 bg-emerald-500/20 rounded-full flex items-center justify-center">
            <CheckCircle class="text-emerald-400" :size="40" />
          </div>

          <h3 class="text-xl font-bold text-white mb-2">
            Pembayaran Berhasil!
          </h3>
          <p class="text-slate-400 text-sm mb-6">
            Transaksi telah selesai diproses
          </p>

          <div v-if="lastTransaction" class="bg-slate-800 rounded-xl p-4 mb-6 text-left">
            <div class="flex justify-between text-sm mb-2">
              <span class="text-slate-400">No. Transaksi</span>
              <span class="text-white font-mono">{{ lastTransaction.id }}</span>
            </div>
            <div class="flex justify-between text-sm mb-2">
              <span class="text-slate-400">Total</span>
              <span class="text-white">{{
                formatCurrency(lastTransaction.total)
              }}</span>
            </div>
            <div v-if="lastTransaction.change > 0" class="flex justify-between text-sm">
              <span class="text-slate-400">Kembalian</span>
              <span class="text-emerald-400 font-bold">{{
                formatCurrency(lastTransaction.change)
              }}</span>
            </div>
          </div>

          <div class="flex gap-3">
            <button @click="closeSuccessModal" class="btn btn-secondary flex-1">
              Tutup
            </button>
            <button class="btn btn-primary flex-1">
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
  background-color: #334155;
  border-radius: 9999px;
}
</style>