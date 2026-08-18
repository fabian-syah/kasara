<script setup>
import { ref, computed, watch } from "vue";
import { useCartStore } from "../../../store/cart";
import { useInventoryStore } from "../../../store/inventory";
import { formatCurrency } from "../../../utils/formatters";
import {
    Search,
    Plus,
    ShoppingCart,
    Trash2,
    ArrowLeft,
    ArrowRight,
    ShoppingBag,
    CheckCircle,
    X,
    Edit2
} from "lucide-vue-next";

const props = defineProps({
    transactionCategory: String,
    availablePaymentMethods: Array
});

const emit = defineEmits(["prev", "next", "select-outgoing-unit"]);

const cartStore = useCartStore();
const inventoryStore = useInventoryStore();

const searchQuery = ref("");
const showBundlingModal = ref(false);

// Bundling State
const bundleItems = ref([]);
const bundleTotalPrice = ref(0);
const bundlingHelper = ref({ totalPrice: 0 }); // Helper for v-money compatibility
const displayBundleTotalPrice = ref("0");
const showMobileCart = ref(false);
const editingBundleId = ref(null);

// Computeds
const displayLimit = ref(50);

const filteredProducts = computed(() => {
    let prods = inventoryStore.products || [];
    const q = searchQuery.value?.toLowerCase();
    
    if (q) {
        prods = prods.filter(p =>
            (p.product?.name || p.name || "").toLowerCase().includes(q) ||
            (p.imei || "").toLowerCase().includes(q) ||
            (p.product?.brand || "").toLowerCase().includes(q)
        );
    }
    return prods.slice(0, displayLimit.value);
});

const totalFilteredCount = computed(() => {
    let prods = inventoryStore.products || [];
    const q = searchQuery.value?.toLowerCase();
    
    if (q) {
        return prods.filter(p =>
            (p.product?.name || p.name || "").toLowerCase().includes(q) ||
            (p.imei || "").toLowerCase().includes(q) ||
            (p.product?.brand || "").toLowerCase().includes(q)
        ).length;
    }
    return prods.length;
});

watch(searchQuery, () => {
    displayLimit.value = 50;
});

const loadMore = () => {
    displayLimit.value += 50;
};

const cartItems = computed(() => cartStore.items);
const cartItemCount = computed(() => cartStore.itemCount);
const cartTotal = computed(() => cartStore.total);

const displayDiscount = ref("0");

// Sync discount display
watch(() => cartStore.discount, (newVal) => {
    // With v-money, we usually work with the raw numeric value directly.
    // However, the input expects a formatted string or the v-money directive handles it.
}, { immediate: true });

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

function getRemainingStock(product) {
    if (product.imei) {
        const inCart = cartStore.items.some(i => i.imei === product.imei);
        const inBundles = cartStore.items.some(i =>
            i.is_bundle && 
            i.id !== editingBundleId.value && 
            i.bundle_items?.some(bi => bi.imei === product.imei)
        );
        return (inCart || inBundles) ? 0 : 1;
    }

    const inCart = cartStore.items
        .filter(i => i.id === product.id && !i.is_bundle)
        .reduce((sum, i) => sum + i.quantity, 0);

    const inBundles = cartStore.items
        .filter(i => i.is_bundle && i.id !== editingBundleId.value && i.bundle_items)
        .reduce((sum, bundle) => {
            const count = bundle.bundle_items
                .filter(bi => bi.id === product.id)
                .reduce((s, bi) => s + (bi.quantity || 1), 0);
            return sum + count;
        }, 0);

    return (product.stock || product.quantity || 0) - inCart - inBundles;
}

function isItemFullyOccupied(product) {
    return getRemainingStock(product) <= 0;
}

function getCartStatus(productId) {
    const item = cartStore.items.find(i => i.id === productId);
    if (!item) {
        // Check in bundles
        const inBundle = cartStore.items.find(i => i.is_bundle && i.bundle_items?.some(bi => bi.id === productId));
        return inBundle ? "Dalam Bundle" : null;
    }
    return "Di Keranjang";
}

function addToCart(product) {
    if (isItemFullyOccupied(product)) {
        const status = getCartStatus(product.id);
        alert(`Produk ini sudah tidak tersedia (Sudah ada ${status?.toLowerCase() || 'di keranjang'}).`);
        return;
    }

    if (!product.imei) {
        const existingInCart = cartStore.items.find(i => i.id === product.id && !i.is_bundle);
        if (existingInCart) {
            cartStore.incrementQuantity(product.id);
            return;
        }
    }

    cartStore.addItem(product);
}

function removeFromCart(productId) {
    cartStore.removeItem(productId);
}

function incrementQty(productId) {
    const item = cartStore.items.find(i => i.id === productId);
    if (item && isItemFullyOccupied(item)) {
        alert("Stok tidak mencukupi.");
        return;
    }
    cartStore.incrementQuantity(productId);
}

function decrementQty(productId) {
    cartStore.decrementQuantity(productId);
}

function handleItemPriceInput(item, e) {
    const val = e.target.value;
    const num = parseNumber(val);
    item.price = num;
    e.target.value = formatNumber(num);
}

function handleItemDiscountInput(item, e) {
    const val = e.target.value;
    const num = parseNumber(val);
    cartStore.updateItemDiscount(item.id, num);
    e.target.value = formatNumber(num);
}

function handleDiscountInput(e) {
    const val = e.target.value;
    const num = parseNumber(val);
    cartStore.setDiscount(num, 'fixed');
    displayDiscount.value = formatNumber(num);
}

// Bundling Logic
function openBundlingModal() {
    editingBundleId.value = null;
    bundleItems.value = [];
    bundleTotalPrice.value = 0;
    bundlingHelper.value.totalPrice = 0;
    displayBundleTotalPrice.value = "0";
    showBundlingModal.value = true;
}

function closeBundlingModal() {
    showBundlingModal.value = false;
    editingBundleId.value = null;
}

function editBundle(bundle) {
    editingBundleId.value = bundle.id;
    bundleItems.value = bundle.bundle_items.map(item => ({
        ...item,
        bundle_price: item.price,
        display_bundle_price: formatNumber(item.price)
    }));
    calculateBundleTotal();
    showBundlingModal.value = true;
}

function addToBundle(product) {
    if (isItemFullyOccupied(product)) return;

    // Check if already in this specific bundle draft
    const existing = bundleItems.value.find(i => i.id === product.id);
    if (existing && !product.imei) {
        const remaining = getRemainingStock(product);
        if (existing.quantity < remaining) {
            existing.quantity++;
        }
        return;
    }

    if (existing && product.imei) return;

    bundleItems.value.push({
        ...product,
        quantity: 1,
        bundle_price: product.selling_price || product.price || 0,
        display_bundle_price: formatNumber(product.selling_price || product.price || 0)
    });
    calculateBundleTotal();
}

function removeFromBundle(idx) {
    bundleItems.value.splice(idx, 1);
    calculateBundleTotal();
}

function incrementBundleItemQty(idx) {
    const item = bundleItems.value[idx];
    if (isItemFullyOccupied(item)) return;
    item.quantity++;
    calculateBundleTotal();
}

function decrementBundleItemQty(idx) {
    if (bundleItems.value[idx].quantity > 1) {
        bundleItems.value[idx].quantity--;
        calculateBundleTotal();
    }
}

function handleBundleItemPriceInput(idx, e) {
    const val = e.target.value;
    const num = parseNumber(val);
    bundleItems.value[idx].bundle_price = num;
    bundleItems.value[idx].display_bundle_price = formatNumber(num);
    calculateBundleTotal();
}

function handleBundlePriceInput() {
    bundleTotalPrice.value = bundlingHelper.value.totalPrice;
    displayBundleTotalPrice.value = formatNumber(bundleTotalPrice.value);
}

let bundleCalcTimeout = null;
function calculateBundleTotal() {
    if (bundleCalcTimeout) clearTimeout(bundleCalcTimeout);
    bundleCalcTimeout = setTimeout(() => {
        const total = bundleItems.value.reduce((sum, item) => sum + (Number(item.bundle_price || 0) * Number(item.quantity || 1)), 0);
        bundleTotalPrice.value = total;
        bundlingHelper.value.totalPrice = total;
        displayBundleTotalPrice.value = formatNumber(total);
    }, 150);
}

function finishBundling() {
    if (bundleItems.value.length < 2) {
        alert("Pilih minimal 2 item untuk bundling.");
        return;
    }

    const bundleName = "Paket Bundling: " + bundleItems.value.map(i => i.product?.name || i.name).join(", ");
    
    if (editingBundleId.value) {
        cartStore.updateBundle(editingBundleId.value, bundleItems.value, bundleTotalPrice.value, bundleName);
    } else {
        cartStore.addBundle(bundleItems.value, bundleTotalPrice.value, bundleName);
    }
    
    closeBundlingModal();
}

const selectOutgoingUnit = (item) => {
    emit('select-outgoing-unit', item);
};
</script>

<template>
    <div class="flex flex-col gap-4 sm:gap-8 items-start w-full">
        <!-- Header for Step 3 -->
        <div class="w-full flex items-center justify-between bg-white dark:bg-surface-800 p-4 rounded-2xl border border-surface-200 dark:border-surface-700 shadow-sm mb-2">
            <div class="flex items-center gap-3">
                <button @click="emit('prev')" class="p-2 -ml-2 hover:bg-surface-100 dark:hover:bg-surface-700 rounded-full text-primary-600 transition-colors">
                    <ArrowLeft :size="28" stroke-width="3" />
                </button>
                <div class="flex flex-col">
                    <h3 class="text-lg sm:text-xl font-black text-text-primary uppercase tracking-tight leading-none">{{ transactionCategory === 'pelunasan_dp' ? 'Pelunasan DP' : 'Penjualan Store' }}</h3>
                    <p class="text-[10px] font-bold text-text-secondary uppercase tracking-widest mt-1">{{ transactionCategory === 'pelunasan_dp' ? 'Step 2 — Pilih Unit / Item dari Stok' : 'Pilih Item & Masukkan ke Keranjang' }}</p>
                </div>
            </div>
            <div class="hidden sm:flex items-center gap-2 px-4 py-1.5 bg-primary-100 dark:bg-primary-900/30 text-primary-600 rounded-full text-xs font-black uppercase tracking-widest">
                {{ transactionCategory === 'pelunasan_dp' ? 'VALIDASI STOK' : 'TRANSAKSI LANGSUNG' }}
            </div>
            <!-- Mobile Cart Toggle -->
            <button @click="showMobileCart = true" class="lg:hidden relative p-3 bg-primary-600 text-white rounded-xl shadow-lg active:scale-95">
                <ShoppingCart :size="20" />
                <span v-if="cartItemCount > 0" class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-[10px] font-black rounded-full flex items-center justify-center border-2 border-white shadow-sm">
                    {{ cartItemCount }}
                </span>
            </button>
        </div>

        <div class="flex flex-col lg:flex-row gap-8 w-full items-start">
            <!-- Left: Product Selection -->
            <div class="flex-[2] flex flex-col min-w-0 w-full">
                <div class="bg-white dark:bg-surface-800 rounded-[1.5rem] border border-surface-200 dark:border-surface-700 p-4 sm:p-6 mb-6 shadow-sm flex flex-col md:flex-row gap-4 items-center">
                    <div class="relative flex-1 w-full">
                    <Search class="absolute left-5 top-1/2 -translate-y-1/2 text-text-secondary" :size="20" />
                    <input v-model="searchQuery" type="text" placeholder="Cari..."
                        class="w-full bg-surface-50 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 rounded-2xl pl-12 pr-4 py-3 sm:py-4 text-base sm:text-lg font-medium text-text-primary focus:outline-none focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 transition-all" />
                </div>

                <!-- Bundling Button (Only for Penjualan flow) -->
                <div v-if="['penjualan', 'bundling', 'penjualan_store'].includes(transactionCategory)"
                    class="flex items-center gap-4">
                    <button @click="openBundlingModal"
                        class="px-6 py-4 bg-primary-600 hover:bg-primary-500 text-white rounded-2xl font-bold flex items-center gap-2 shadow-lg shadow-primary-500/20 transition-all active:scale-95">
                        <Plus :size="20" stroke-width="3" />
                        Buat Bundling
                    </button>
                </div>
            </div>

            <div
                class="flex-1 overflow-y-auto overflow-x-auto custom-scrollbar bg-white dark:bg-surface-800 rounded-[1.5rem] border border-surface-200 dark:border-surface-700 mb-4 shadow-sm">

                <!-- Table for Tablet/Desktop -->
                <table class="w-full text-left border-collapse hidden md:table">
                    <thead class="sticky top-0 bg-surface-50 dark:bg-surface-900 z-10">
                        <tr>
                            <th
                                class="px-6 py-3 text-xs font-black text-text-secondary uppercase tracking-widest border-b border-surface-200 dark:border-surface-700">
                                Produk & Brand</th>
                            <th
                                class="px-6 py-3 text-xs font-black text-text-secondary uppercase tracking-widest border-b border-surface-200 dark:border-surface-700">
                                Spek & Kondisi</th>
                            <th
                                class="px-6 py-3 text-xs font-black text-text-secondary uppercase tracking-widest border-b border-surface-200 dark:border-surface-700">
                                IMEI / Stok</th>
                            <th
                                class="hidden xl:table-cell px-6 py-3 text-xs font-black text-text-secondary uppercase tracking-widest border-b border-surface-200 dark:border-surface-700">
                                Distributor</th>
                            <th
                                class="px-6 py-3 text-xs font-black text-text-secondary uppercase tracking-widest border-b border-surface-200 dark:border-surface-700 text-right">
                                Harga</th>
                            <th class="px-6 py-3 border-b border-surface-200 dark:border-surface-700 w-24">
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-100 dark:divide-surface-700">
                        <tr v-for="item in filteredProducts" :key="item.id"
                            class="hover:bg-primary-50/50 dark:hover:bg-primary-900/10 transition-colors group">
                            <td class="px-6 py-2">
                                <div class="flex flex-col gap-1">
                                    <span class="font-black text-text-primary text-base">{{ item.product?.name
                                        ||
                                        item.name }}</span>
                                    <span class="text-xs text-primary-600 font-bold uppercase tracking-wider">{{
                                        item.product?.brand || '-' }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-2">
                                <div class="flex flex-col items-start gap-2">
                                    <span
                                        class="text-sm font-bold text-text-primary bg-surface-100 dark:bg-surface-800 px-3 py-1 rounded-lg">{{
                                            item.ram ? item.ram + ' / ' : '' }}{{ item.storage || '-' }}</span>
                                    <span class="text-xs uppercase px-3 py-1 rounded-lg font-bold border"
                                        :class="item.condition === 'new' 
                                            ? 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-500/20' 
                                            : 'bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-500/20'">
                                        {{ item.condition || 'Second' }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-2">
                                <code v-if="item.imei"
                                    class="text-sm font-mono font-bold text-text-primary bg-surface-50 dark:bg-surface-900 px-3 py-1.5 rounded-lg border border-surface-200 dark:border-surface-700">
                                    {{ item.imei }}
                                </code>
                                <span v-else
                                    class="text-sm font-black text-primary-600 bg-primary-500/10 px-4 py-1.5 rounded-lg">
                                    Sisa: {{ getRemainingStock(item) }}
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
                                <template v-if="transactionCategory === 'tukar_unit'">
                                    <button @click="selectOutgoingUnit(item)"
                                        class="px-6 py-3 bg-primary-600 text-white rounded-xl font-bold transition-all shadow-sm active:scale-95 ml-auto text-sm uppercase tracking-widest hover:bg-primary-500 flex items-center gap-2">
                                        <ArrowRight :size="18" /> Pilih
                                    </button>
                                </template>
                                <template v-else>
                                    <button v-if="!isItemFullyOccupied(item)" @click="addToCart(item)"
                                        class="w-12 h-12 flex items-center justify-center bg-primary-100 text-primary-600 hover:bg-primary-600 hover:text-white dark:bg-primary-900/50 dark:text-primary-400 dark:hover:bg-primary-600 dark:hover:text-white rounded-xl transition-all shadow-sm active:scale-95 ml-auto">
                                        <Plus :size="24" stroke-width="3" />
                                    </button>
                                    <div v-else class="flex flex-col items-end">
                                        <div
                                            class="flex items-center gap-1 text-emerald-600 font-black text-[10px] uppercase tracking-widest bg-emerald-500/10 px-3 py-2 rounded-lg">
                                            <CheckCircle :size="14" />
                                            {{ getCartStatus(item.id) }}
                                        </div>
                                    </div>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="totalFilteredCount > displayLimit" class="p-6 flex justify-center border-t border-surface-100 dark:border-surface-700 hidden md:flex">
                    <button @click="loadMore" class="px-8 py-3 bg-white dark:bg-surface-900 border-2 border-primary-600 text-primary-600 rounded-2xl font-black hover:bg-primary-50 dark:hover:bg-primary-900/20 transition-all flex items-center gap-2">
                        <Plus :size="18" stroke-width="3" />
                        Tampilkan Lebih Banyak ({{ totalFilteredCount - displayLimit }} Sisa)
                    </button>
                </div>

                <!-- Card list for Mobile -->
                <div class="md:hidden divide-y divide-surface-100 dark:divide-surface-700 pb-32">
                    <div v-for="item in filteredProducts" :key="item.id" 
                        class="p-4 flex items-center justify-between gap-4 active:bg-surface-50 dark:active:bg-surface-900 transition-colors"
                        :class="{'opacity-50': isItemFullyOccupied(item)}">
                        
                        <div class="flex-1 min-w-0" @click="!isItemFullyOccupied(item) ? addToCart(item) : null">
                            <div class="flex flex-col gap-0.5">
                                <span class="font-black text-text-primary text-sm leading-tight truncate">
                                    {{ item.product?.name || item.name }}
                                </span>
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="text-[9px] text-primary-600 font-black uppercase tracking-wider">
                                        {{ item.product?.brand || '-' }}
                                    </span>
                                    <span v-if="item.ram || item.storage" class="text-[9px] font-bold text-text-secondary bg-surface-100 dark:bg-surface-800 px-1.5 py-0.5 rounded">
                                        {{ item.ram ? item.ram + '/' : '' }}{{ item.storage || '-' }}
                                    </span>
                                    <span class="text-[9px] uppercase px-1.5 py-0.5 rounded font-black border"
                                        :class="item.condition === 'new' 
                                            ? 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-500/20' 
                                            : 'bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-500/20'">
                                        {{ item.condition || 'Second' }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-2 mt-1">
                                    <code v-if="item.imei" class="text-[9px] font-mono font-bold text-text-secondary tracking-tighter truncate max-w-[120px]">
                                        {{ item.imei }}
                                    </code>
                                    <span v-else class="text-[9px] font-black text-primary-600">
                                        Stok: {{ getRemainingStock(item) }}
                                    </span>
                                    <span class="text-xs font-black text-primary-600">
                                        {{ formatCurrency(item.selling_price || item.price) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="shrink-0 flex items-center gap-2">
                            <template v-if="transactionCategory === 'tukar_unit'">
                                <button @click="selectOutgoingUnit(item)"
                                    class="w-8 h-8 flex items-center justify-center bg-primary-600 text-white rounded-lg shadow-lg active:scale-95">
                                    <ArrowRight :size="16" />
                                </button>
                            </template>
                            <template v-else>
                                <button v-if="!isItemFullyOccupied(item)" @click="addToCart(item)"
                                    class="w-10 h-10 flex items-center justify-center bg-primary-100 text-primary-600 dark:bg-primary-900/40 rounded-xl active:scale-90 transition-transform">
                                    <Plus :size="20" stroke-width="3" />
                                </button>
                                <div v-else
                                    class="flex items-center justify-center w-8 h-8 text-emerald-600 bg-emerald-500/10 rounded-lg">
                                    <CheckCircle :size="16" />
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <div v-if="totalFilteredCount > displayLimit" class="p-4 flex justify-center border-t border-surface-100 dark:border-surface-700 md:hidden">
                    <button @click="loadMore" class="w-full py-3 bg-white dark:bg-surface-900 border-2 border-primary-600 text-primary-600 rounded-xl font-black transition-all flex items-center justify-center gap-2 text-sm">
                        <Plus :size="16" stroke-width="3" />
                        Lainnya ({{ totalFilteredCount - displayLimit }})
                    </button>
                </div>

                <div v-if="filteredProducts.length === 0" class="px-6 py-20 text-center">
                    <div class="flex flex-col items-center justify-center text-text-secondary">
                        <Search :size="48" class="mb-4 opacity-50" />
                        <span class="text-lg font-medium">Produk tidak ditemukan</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="w-full lg:w-[420px] flex flex-col bg-white dark:bg-surface-800 rounded-[1.5rem] border border-surface-200 dark:border-surface-700 shadow-xl overflow-hidden shrink-0 lg:sticky lg:top-4 lg:max-h-[calc(100vh-40px)]"
            :class="{'fixed inset-0 z-[110] rounded-none': showMobileCart, 'hidden lg:flex': !showMobileCart}">
            
            <div
                class="p-4 sm:p-6 border-b border-surface-100 dark:border-surface-700 bg-surface-50 dark:bg-surface-900 flex items-center justify-between font-bold">
                <div class="flex items-center gap-3">
                    <button v-if="showMobileCart" @click="showMobileCart = false" class="lg:hidden p-2 -ml-2 hover:bg-surface-200 dark:hover:bg-surface-700 rounded-full">
                        <ArrowLeft :size="20" />
                    </button>
                    <ShoppingCart :size="24" class="text-primary-500" stroke-width="2.5" />
                    <span class="text-lg sm:text-xl">Keranjang <span
                            class="text-primary-500 font-black px-2 py-0.5 bg-primary-500/10 rounded-lg ml-1">{{
                                cartItemCount }}</span></span>
                </div>
                <button v-if="showMobileCart" @click="showMobileCart = false" class="lg:hidden text-xs font-black uppercase tracking-widest text-primary-600 bg-primary-500/10 px-3 py-1.5 rounded-lg active:scale-95">
                    Tambah Lagi
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-4 pb-24 sm:p-6 sm:pb-6 custom-scrollbar min-h-0">
                <div v-if="cartItems.length === 0"
                    class="h-full flex flex-col items-center justify-center text-text-secondary opacity-50 py-10">
                    <ShoppingCart :size="64" class="mb-6" stroke-width="1.5" />
                    <p class="text-xl font-medium">Keranjang Kosong</p>
                    <p class="text-sm mt-2">Pilih produk dari daftar di sebelah kiri.</p>
                </div>
                <div v-else class="space-y-4">
                    <div v-for="item in cartItems" :key="item.id"
                        class="p-5 bg-white dark:bg-surface-800 border-2 border-surface-100 dark:border-surface-700 rounded-2xl relative shadow-sm group hover:border-surface-300 dark:hover:border-surface-600 transition-colors">
                        <div class="flex justify-between items-start mb-4 gap-4">
                            <div class="min-w-0 flex-1 flex flex-col gap-1">
                                <p class="text-sm font-black text-text-primary line-clamp-2 leading-tight">
                                    {{
                                        item.product?.name ||
                                        item.name }}</p>
                                <span v-if="item.imei"
                                    class="text-xs font-mono font-bold text-text-secondary bg-surface-50 dark:bg-surface-900 px-2 py-1 rounded w-fit">{{
                                        item.imei }}</span>
                            </div>
                            <div class="flex items-center gap-1 shrink-0">
                                <button v-if="item.is_bundle" @click="editBundle(item)"
                                    class="text-primary-500 hover:text-primary-600 bg-primary-50 dark:bg-primary-900/30 p-2 rounded-full transition-colors">
                                    <Edit2 :size="18" />
                                </button>
                                <button @click="removeFromCart(item.id)"
                                    class="text-surface-400 hover:text-red-500 bg-surface-50 dark:bg-surface-900 p-2 rounded-full transition-colors">
                                    <Trash2 :size="18" />
                                </button>
                            </div>
                        </div>
                        <div
                            class="flex flex-col sm:flex-row justify-between items-start sm:items-end border-t border-surface-100 dark:border-surface-700 pt-4 gap-4 sm:gap-0">
                            <div class="flex items-center gap-3 w-full sm:w-auto">
                                <button v-if="!item.imei" @click="decrementQty(item.id)"
                                    class="w-10 h-10 sm:w-8 sm:h-8 flex items-center justify-center bg-surface-100 dark:bg-surface-700 rounded-lg text-text-primary hover:bg-surface-200 transition-colors font-black text-lg sm:text-base">-</button>
                                <span class="text-base sm:text-sm font-black px-2">
                                    {{ item.quantity }}<span class="text-text-secondary font-medium ml-1">x</span>
                                </span>
                                <button v-if="!item.imei" @click="incrementQty(item.id)"
                                    :disabled="isItemFullyOccupied(item)"
                                    class="w-10 h-10 sm:w-8 sm:h-8 flex items-center justify-center bg-surface-100 dark:bg-surface-700 disabled:opacity-30 disabled:cursor-not-allowed rounded-lg text-text-primary hover:bg-surface-200 transition-colors font-black text-lg sm:text-base">+</button>
                            </div>
                            <div class="flex flex-col items-start sm:items-end gap-3 sm:gap-2 w-full sm:w-auto">
                                <!-- Price/Subtotal -->
                                <div v-if="!item.is_bundle"
                                    class="flex items-center justify-between sm:justify-end gap-2 border-2 border-surface-200 dark:border-surface-700 rounded-xl bg-surface-50 dark:bg-surface-900 px-3 py-2 focus-within:border-primary-500 transition-all w-full sm:w-auto">
                                    <span
                                        class="text-[10px] sm:text-[9px] font-black text-text-secondary uppercase tracking-widest whitespace-nowrap">Harga
                                        Unit</span>
                                    <div class="flex items-center gap-1">
                                        <span class="text-[10px] font-bold text-text-secondary">Rp</span>
                                        <input v-money:price="item" type="text"
                                            class="w-24 sm:w-20 text-right text-sm sm:text-xs font-black bg-transparent outline-none focus:text-primary-600"
                                            :placeholder="formatNumber(item.cost_price)" />
                                    </div>
                                </div>
                                <p v-else class="text-sm font-black text-primary-600">{{
                                    formatCurrency(item.price) }}</p>

                                <!-- Discount per Item -->
                                <div class="flex flex-col gap-1 w-full sm:w-auto">
                                    <div
                                        class="flex items-center justify-between sm:justify-end gap-2 border-2 border-amber-200 dark:border-amber-900/30 rounded-xl bg-amber-50/50 dark:bg-amber-900/10 px-3 py-2 focus-within:border-amber-500 transition-all w-full sm:w-auto">
                                        <span
                                            class="text-[10px] sm:text-[9px] font-black text-amber-600 uppercase tracking-widest whitespace-nowrap">Diskon
                                            Unit</span>
                                        <div class="flex items-center gap-1">
                                            <span class="text-[10px] font-bold text-amber-600">Rp</span>
                                            <input v-money:discount="item" type="text"
                                                class="w-24 sm:w-20 text-right text-sm sm:text-xs font-black bg-transparent outline-none text-amber-600 placeholder:text-amber-300"
                                                placeholder="0" />
                                        </div>
                                    </div>
                                    <div v-if="cartStore.getDistributedGlobalDiscount(item) > 0"
                                        class="px-2 py-1.5 sm:py-1 bg-primary-50 dark:bg-primary-900/10 rounded-lg border border-primary-100 dark:border-primary-900/20">
                                        <p
                                            class="text-[10px] sm:text-[9px] font-black text-primary-600 uppercase tracking-tighter">
                                            Pot. Global ({{ cartStore.globalDiscountPercentage.toFixed(1)
                                            }}%):
                                            -{{ formatCurrency(cartStore.getDistributedGlobalDiscount(item))
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div
                class="p-3 sm:p-4 bg-surface-50 dark:bg-surface-900 mt-auto border-t border-surface-200 dark:border-surface-700 shrink-0 space-y-2 sm:space-y-3">

                <!-- Mini Summary -->
                <div class="space-y-1 sm:space-y-2 border-b border-surface-200 dark:border-surface-700 pb-2 sm:pb-4">
                    <div class="flex justify-between text-xs sm:text-sm font-bold text-text-secondary uppercase tracking-widest">
                        <span>Subtotal</span>
                        <span>{{ formatCurrency(cartStore.subtotal) }}</span>
                    </div>
                    <div v-if="cartStore.itemDiscountTotal > 0"
                        class="flex justify-between text-[10px] sm:text-xs font-bold text-amber-600 uppercase tracking-widest">
                        <span>Diskon Item</span>
                        <span>-{{ formatCurrency(cartStore.itemDiscountTotal) }}</span>
                    </div>
                </div>

                <!-- Global Discount Input -->
                <div class="flex items-center justify-between gap-4">
                    <div class="flex flex-col">
                        <span
                            class="text-[10px] font-black text-text-secondary uppercase tracking-widest leading-none mb-1">Diskon
                            All</span>
                        <div class="flex items-center gap-1">
                            <span class="text-[9px] text-text-secondary/50 font-bold uppercase">(Nota)</span>
                            <span v-if="cartStore.globalDiscountPercentage > 0"
                                class="text-[9px] bg-primary-500 text-white px-1.5 py-0.5 rounded-full font-black">
                                {{ cartStore.globalDiscountPercentage.toFixed(1) }}%
                            </span>
                        </div>
                    </div>
                    <div class="relative flex-1 max-w-[200px]">
                        <span
                            class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-text-secondary">Rp</span>
                        <input v-money:discount="cartStore" type="text"
                            class="w-full bg-white dark:bg-surface-800 border-2 border-surface-200 dark:border-surface-700 rounded-xl pl-9 pr-4 py-3 text-sm font-black text-primary-600 focus:outline-none focus:border-primary-500 transition-all text-right"
                            placeholder="0" />
                    </div>
                </div>

                <div class="flex justify-between items-center text-xl sm:text-2xl font-black pt-1 sm:pt-2">
                    <span class="text-text-primary text-xs sm:text-lg uppercase tracking-widest">Total
                        Bayar</span>
                    <span class="text-primary-600">{{ formatCurrency(cartTotal) }}</span>
                </div>
                <div class="flex gap-2 sm:gap-3 pt-1">
                    <button @click="emit('prev')"
                        class="w-10 h-10 sm:w-14 sm:h-14 flex-none bg-white dark:bg-surface-800 text-text-primary border-2 border-surface-200 dark:border-surface-700 rounded-xl font-bold transition-all flex items-center justify-center hover:bg-surface-50 hover:border-surface-300">
                        <ArrowLeft :size="18" class="sm:w-5 sm:h-5" />
                    </button>
                    <button @click="emit('next')" :disabled="cartItems.length === 0"
                        class="flex-1 h-10 sm:h-14 bg-primary-600 hover:bg-primary-500 disabled:opacity-50 text-white rounded-xl font-bold text-sm sm:text-base shadow-lg shadow-primary-500/20 transition-all flex items-center justify-center gap-2">
                        Pembayaran
                        <ArrowRight :size="18" class="sm:w-5 sm:h-5" />
                    </button>
                </div>
            </div>
        </div>
    </div>

    </div>

    <!-- Bundling Modal Teleport -->
    <Teleport to="body">
        <div v-show="showBundlingModal" class="fixed inset-0 z-[100000] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="closeBundlingModal"></div>
            <div
                class="relative bg-white dark:bg-surface-800 rounded-[2rem] border border-surface-200 dark:border-surface-700 w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden shadow-2xl">
                <div
                    class="p-4 sm:p-6 border-b border-surface-100 dark:border-surface-700 flex justify-between items-center">
                    <h3 class="text-lg sm:text-2xl font-black text-text-primary">
                        {{ editingBundleId ? 'Edit Sistem Bundling' : 'Buat Sistem Bundling' }}
                    </h3>
                    <button @click="closeBundlingModal"
                        class="p-1.5 sm:p-2 hover:bg-surface-100 dark:hover:bg-surface-700 rounded-full transition-colors">
                        <X :size="20" class="sm:w-6 sm:h-6" />
                    </button>
                </div>

                <div class="flex-1 overflow-hidden flex flex-col md:flex-row">
                    <!-- Left: Item Picker -->
                    <div
                        class="flex-1 p-4 sm:p-6 overflow-y-auto custom-scrollbar border-b md:border-b-0 md:border-r border-surface-100 dark:border-surface-700">
                        <div class="mb-4 sm:mb-6 sticky top-0 bg-white dark:bg-surface-800 z-10 pb-4">
                            <div class="relative">
                                <Search
                                    class="absolute left-4 top-1/2 -translate-y-1/2 text-text-secondary sm:w-[18px] sm:h-[18px]"
                                    :size="16" />
                                <input v-model="searchQuery" type="text" placeholder="Cari item untuk bundle..."
                                    class="w-full bg-surface-50 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 rounded-xl pl-10 sm:pl-11 pr-4 py-2.5 sm:py-3 text-xs sm:text-sm font-medium focus:outline-none focus:border-primary-500 transition-all" />
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div v-for="item in filteredProducts" :key="item.id" @click="addToBundle(item)"
                                class="p-4 bg-surface-50 dark:bg-surface-900 rounded-xl border border-surface-200 dark:border-surface-700 hover:border-primary-500 cursor-pointer transition-all flex justify-between items-center group"
                                :class="{ 'opacity-50 pointer-events-none border-emerald-500 bg-emerald-500/5': isItemFullyOccupied(item) }">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <p class="font-bold text-text-primary text-sm">{{ item.product?.name ||
                                            item.name }}</p>
                                        <CheckCircle v-if="getCartStatus(item.id)" :size="14"
                                            class="text-emerald-500" />
                                    </div>
                                    <p v-if="item.imei" class="text-xs font-mono text-text-secondary">{{
                                        item.imei
                                    }}</p>
                                    <p v-else
                                        class="text-[10px] font-black text-primary-600 bg-primary-500/10 px-2 py-0.5 rounded w-fit">
                                        Sisa: {{ getRemainingStock(item) }}
                                    </p>
                                    <p class="text-xs text-primary-600 font-bold mt-1">{{
                                        formatCurrency(item.selling_price || item.price) }}</p>
                                </div>
                                <Plus v-if="!isItemFullyOccupied(item)" :size="18"
                                    class="text-surface-400 group-hover:text-primary-500 transition-colors" />
                                <span v-else
                                    class="text-[10px] font-black text-emerald-600 uppercase tracking-widest">{{
                                        getCartStatus(item.id) || 'Stok Habis' }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Selected Items & Final Price -->
                    <div
                        class="w-full md:w-[350px] bg-surface-50 dark:bg-surface-900 p-3 sm:p-6 flex flex-col h-[350px] sm:h-[400px] md:h-auto md:max-h-full flex-shrink-0 border-t md:border-t-0 border-surface-200 dark:border-surface-700">
                        <h4
                            class="text-[10px] sm:text-sm font-black text-text-secondary uppercase tracking-widest mb-3 sm:mb-4">
                            Item
                            Terpilih</h4>

                        <div class="flex-1 overflow-y-auto custom-scrollbar space-y-2 mb-4">
                            <div v-if="bundleItems.length === 0"
                                class="h-full flex flex-col items-center justify-center text-text-secondary opacity-50 py-2 sm:py-10">
                                <ShoppingBag class="w-6 h-6 sm:w-12 sm:h-12 mb-1 sm:mb-3" />
                                <p class="text-[9px] sm:text-xs font-medium text-center">Belum ada item dipilih</p>
                            </div>
                            <div v-for="(item, idx) in bundleItems" :key="item.id"
                                class="p-3 bg-white dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 animate-fade-in relative group/item">
                                <button @click="removeFromBundle(idx)"
                                    class="absolute top-2 right-2 text-surface-400 hover:text-red-500 p-1 hover:bg-red-50 dark:hover:bg-red-500/10 rounded-lg transition-colors z-10">
                                    <Trash2 :size="16" />
                                </button>

                                <div class="mb-2 pr-6">
                                    <p class="text-xs font-black text-text-primary truncate">{{
                                        item.product?.name
                                        || item.name }}</p>
                                    <p v-if="item.imei" class="text-[10px] font-mono text-text-secondary">{{
                                        item.imei }}</p>
                                </div>

                                <div class="flex items-center justify-between gap-3">
                                    <!-- Quantity Controls for non-IMEI -->
                                    <div v-if="!item.imei"
                                        class="flex items-center bg-surface-100 dark:bg-surface-900 rounded-lg border border-surface-200 dark:border-surface-700 h-9">
                                        <button @click="decrementBundleItemQty(idx)"
                                            class="px-2 h-full flex items-center justify-center text-text-primary hover:bg-surface-200 dark:hover:bg-surface-700 transition-colors rounded-l-lg font-black">-</button>
                                        <span
                                            class="px-3 text-xs font-black text-center border-x border-surface-200 dark:border-surface-700 h-full flex items-center bg-white dark:bg-surface-800">
                                            {{ item.quantity }}<span
                                                class="text-[10px] text-text-secondary ml-0.5">x</span>
                                        </span>
                                        <button @click="incrementBundleItemQty(idx)"
                                            :disabled="isItemFullyOccupied(item)"
                                            class="px-2 h-full flex items-center justify-center text-text-primary hover:bg-surface-200 dark:hover:bg-surface-700 disabled:opacity-30 disabled:cursor-not-allowed transition-colors rounded-r-lg font-black">+</button>
                                    </div>
                                    <div v-else
                                        class="h-9 px-3 flex items-center justify-center bg-surface-100 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 rounded-lg text-[10px] font-black uppercase tracking-widest text-text-secondary">
                                        1 Unit
                                    </div>

                                    <!-- Price Input -->
                                    <div class="relative flex-1">
                                        <span
                                            class="absolute left-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-text-secondary">Rp</span>
                                        <input v-money:bundle_price="item" type="text"
                                            @input="calculateBundleTotal"
                                            class="w-full bg-surface-50 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 rounded-lg pl-8 pr-3 py-2 text-xs font-black text-primary-600 outline-none focus:border-primary-500 transition-all h-9"
                                            :placeholder="formatNumber(item.selling_price || item.price || 0)" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="pt-3 sm:pt-6 border-t border-surface-200 dark:border-surface-700 mt-auto">
                            <label
                                class="block text-[9px] sm:text-xs font-black text-text-secondary uppercase tracking-widest mb-1 sm:mb-3">Harga
                                Total Bundle</label>
                            <div class="relative mb-4 sm:mb-6">
                                <span
                                    class="absolute left-4 top-1/2 -translate-y-1/2 text-text-secondary font-bold text-sm sm:text-base">Rp</span>
                                <input v-money:totalPrice="bundlingHelper" type="text"
                                    @input="handleBundlePriceInput"
                                    class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 sm:px-5 py-3 sm:py-4 bg-white dark:bg-surface-800 text-text-primary text-lg sm:text-xl font-black focus:outline-none focus:border-primary-500 transition-all pl-11 sm:pl-12"
                                    placeholder="0" />
                            </div>

                            <button @click="finishBundling"
                                :disabled="bundleItems.length < 2 || bundleTotalPrice <= 0"
                                class="w-full py-3 sm:py-4 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white rounded-xl font-bold transition-all shadow-lg shadow-emerald-500/20 flex items-center justify-center gap-2 text-sm sm:text-base active:scale-95">
                                <CheckCircle :size="18" class="sm:w-5 sm:h-5" />
                                Selesai
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Mobile Sticky Bottom Summary -->
    <div v-if="cartStore.items.length > 0 && !showMobileCart"
        class="lg:hidden fixed bottom-6 left-6 right-6 z-[100]">
        <div @click="showMobileCart = true" 
            class="bg-primary-600 text-white rounded-2xl p-4 shadow-2xl shadow-primary-500/40 flex items-center justify-between group active:scale-95 transition-all">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center relative">
                    <ShoppingCart :size="24" stroke-width="2.5" />
                    <span class="absolute -top-2 -right-2 bg-red-500 text-white text-[10px] font-black w-5 h-5 flex items-center justify-center rounded-full border-2 border-primary-600">
                        {{ cartItemCount }}
                    </span>
                </div>
                <div class="flex flex-col">
                    <span class="text-[10px] font-black uppercase tracking-widest text-primary-100 leading-none mb-1">Total Sementara</span>
                    <span class="text-lg font-black tracking-tight">{{ formatCurrency(cartTotal) }}</span>
                </div>
            </div>
            <div class="flex items-center gap-2 bg-white/20 px-4 py-3 rounded-xl font-black text-sm uppercase tracking-widest">
                {{ transactionCategory === 'tukar_unit' ? 'Lanjut' : 'Checkout' }}
                <ArrowRight :size="18" stroke-width="3" />
            </div>
        </div>
    </div>
</template>
