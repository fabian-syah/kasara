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
    X
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
const displayBundleTotalPrice = ref("0");

// Computeds
const filteredProducts = computed(() => {
    let prods = inventoryStore.products || [];
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        prods = prods.filter(p =>
            (p.product?.name || p.name || "").toLowerCase().includes(q) ||
            (p.imei || "").toLowerCase().includes(q) ||
            (p.product?.brand || "").toLowerCase().includes(q)
        );
    }
    return prods;
});

const cartItems = computed(() => cartStore.items);
const cartItemCount = computed(() => cartStore.itemCount);
const cartTotal = computed(() => cartStore.total);

const displayDiscount = ref("0");

// Sync discount display
watch(() => cartStore.discount, (newVal) => {
    if (cartStore.discountType === 'fixed') {
        displayDiscount.value = formatNumber(newVal);
    } else {
        displayDiscount.value = newVal?.toString() || "0";
    }
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
        // Check if this specific IMEI is in cart or in any bundle in cart
        const inCart = cartStore.items.some(i => i.imei === product.imei);
        const inBundles = cartStore.items.some(i =>
            i.is_bundle && i.bundle_items?.some(bi => bi.imei === product.imei)
        );
        return (inCart || inBundles) ? 0 : 1;
    }

    const inCart = cartStore.items
        .filter(i => i.id === product.id && !i.is_bundle)
        .reduce((sum, i) => sum + i.quantity, 0);

    // Also consider items within bundles in cart
    const inBundles = cartStore.items
        .filter(i => i.is_bundle && i.bundle_items)
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
    bundleItems.value = [];
    bundleTotalPrice.value = 0;
    displayBundleTotalPrice.value = "0";
    showBundlingModal.value = true;
}

function closeBundlingModal() {
    showBundlingModal.value = false;
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

function handleBundlePriceInput(e) {
    const val = e.target.value;
    const num = parseNumber(val);
    bundleTotalPrice.value = num;
    displayBundleTotalPrice.value = formatNumber(num);
}

function calculateBundleTotal() {
    const total = bundleItems.value.reduce((sum, item) => sum + (item.bundle_price * item.quantity), 0);
    bundleTotalPrice.value = total;
    displayBundleTotalPrice.value = formatNumber(total);
}

function finishBundling() {
    if (bundleItems.value.length < 2) {
        alert("Pilih minimal 2 item untuk bundling.");
        return;
    }

    const bundleName = "Paket Bundling: " + bundleItems.value.map(i => i.product?.name || i.name).join(", ");
    cartStore.addBundle(bundleItems.value, bundleTotalPrice.value, bundleName);
    closeBundlingModal();
}

const selectOutgoingUnit = (item) => {
    emit('select-outgoing-unit', item);
};
</script>

<template>
    <div class="flex flex-col lg:flex-row gap-8 animate-fade-in items-start">
        <!-- Left: Product Selection -->
        <div v-if="transactionCategory !== 'angkat_barang' && transactionCategory !== 'refund' && transactionCategory !== 'tukar_unit' && transactionCategory !== 'tukar_tambah'"
            class="flex-[2] flex flex-col min-w-0 w-full">
            <div
                class="bg-white dark:bg-surface-800 rounded-[1.5rem] border border-surface-200 dark:border-surface-700 p-6 mb-6 shadow-sm flex flex-col md:flex-row gap-4 items-center">
                <div class="relative flex-1 w-full">
                    <Search class="absolute left-5 top-1/2 -translate-y-1/2 text-text-secondary" :size="20" />
                    <input v-model="searchQuery" type="text" placeholder="Cari..."
                        class="w-full bg-surface-50 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 rounded-2xl pl-12 pr-4 py-3 sm:py-4 text-base sm:text-lg font-medium text-text-primary focus:outline-none focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 transition-all" />
                </div>

                <!-- Bundling Button (Only for Penjualan flow) -->
                <div v-if="transactionCategory === 'penjualan' || transactionCategory === 'bundling'"
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

                <!-- Card list for Mobile -->
                <div class="md:hidden divide-y divide-surface-100 dark:divide-surface-700">
                    <div v-for="item in filteredProducts" :key="item.id" class="p-4 flex flex-col gap-3">
                        <div class="flex justify-between items-start">
                            <div class="flex flex-col gap-1">
                                <span class="font-black text-text-primary text-base leading-tight">{{
                                    item.product?.name || item.name }}</span>
                                <span class="text-[10px] text-primary-600 font-bold uppercase tracking-wider">{{
                                    item.product?.brand || '-' }}</span>
                            </div>
                            <template v-if="transactionCategory === 'tukar_unit'">
                                <button @click="selectOutgoingUnit(item)"
                                    class="px-4 py-2 bg-primary-600 text-white rounded-lg shadow-lg active:scale-95 text-xs font-black uppercase tracking-widest flex items-center gap-1">
                                    <ArrowRight :size="14" /> Pilih
                                </button>
                            </template>
                            <template v-else>
                                <button v-if="!isItemFullyOccupied(item)" @click="addToCart(item)"
                                    class="w-10 h-10 flex items-center justify-center bg-primary-600 text-white rounded-xl shadow-lg active:scale-95">
                                    <Plus :size="20" stroke-width="3" />
                                </button>
                                <div v-else
                                    class="flex items-center gap-1 text-emerald-600 font-black text-[10px] uppercase tracking-widest bg-emerald-500/10 px-3 py-1.5 rounded-lg">
                                    <CheckCircle :size="12" />
                                    {{ getCartStatus(item.id) }}
                                </div>
                            </template>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <span
                                class="text-[10px] font-bold text-text-primary bg-surface-100 dark:bg-surface-800 px-2 py-0.5 rounded-md">{{
                                    item.ram || '-' }} / {{ item.storage || '-' }}</span>
                            <span class="text-[10px] uppercase px-2 py-0.5 rounded-md font-bold"
                                :class="item.condition === 'new' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400'">
                                {{ item.condition || 'Second' }}
                            </span>
                            <code v-if="item.imei"
                                class="text-[10px] font-mono font-bold text-text-secondary truncate max-w-[120px]">{{
                                    item.imei }}</code>
                            <span v-else class="text-[10px] font-black text-primary-600">Sisa: {{
                                getRemainingStock(item) }}</span>
                        </div>
                        <div class="flex justify-between items-center mt-1">
                            <span class="text-xs text-text-secondary">{{ item.distributor?.name ||
                                item.supplier_name || '-' }}</span>
                            <span class="text-base font-black text-primary-600">{{
                                formatCurrency(item.selling_price || item.price) }}</span>
                        </div>
                    </div>
                </div>

                <div v-if="filteredProducts.length === 0" class="px-6 py-20 text-center">
                    <div class="flex flex-col items-center justify-center text-text-secondary">
                        <Search :size="48" class="mb-4 opacity-50" />
                        <span class="text-lg font-medium">Produk tidak ditemukan</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Cart Sidebar (Fixed in step 3) -->
        <div v-if="transactionCategory !== 'angkat_barang' && transactionCategory !== 'refund' && transactionCategory !== 'tukar_unit' && transactionCategory !== 'tukar_tambah'"
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

            <div class="flex-1 overflow-y-auto p-6 custom-scrollbar min-h-[300px]">
                <div v-if="cartItems.length === 0"
                    class="h-full flex flex-col items-center justify-center text-text-secondary opacity-50 py-10">
                    <ShoppingCart :size="64" class="mb-6" stroke-width="1.5" />
                    <p class="text-xl font-medium">Keranjang Kosong</p>
                    <p class="text-sm mt-2">Pilih produk dari daftar di sebelah kiri.</p>
                </div>
                <div v-else class="space-y-4">
                    <div v-for="item in cartItems" :key="item.id"
                        class="p-5 bg-white dark:bg-surface-800 border-2 border-surface-100 dark:border-surface-700 rounded-2xl relative shadow-sm group hover:border-surface-300 dark:hover:border-surface-600 transition-colors">
                        <div class="flex justify-between items-start mb-4 pr-8">
                            <div class="min-w-0 flex flex-col gap-1">
                                <p class="text-sm font-black text-text-primary line-clamp-2 leading-tight">
                                    {{
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
                                        <input type="text" :value="formatNumber(item.price)"
                                            @input="e => handleItemPriceInput(item, e)"
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
                                            <input type="text" :value="formatNumber(item.discount || 0)"
                                                @input="e => handleItemDiscountInput(item, e)"
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
                class="p-6 bg-surface-50 dark:bg-surface-900 mt-auto border-t border-surface-200 dark:border-surface-700 shrink-0 space-y-4">

                <!-- Mini Summary -->
                <div class="space-y-2 border-b border-surface-200 dark:border-surface-700 pb-4">
                    <div class="flex justify-between text-sm font-bold text-text-secondary uppercase tracking-widest">
                        <span>Subtotal</span>
                        <span>{{ formatCurrency(cartStore.subtotal) }}</span>
                    </div>
                    <div v-if="cartStore.itemDiscountTotal > 0"
                        class="flex justify-between text-xs font-bold text-amber-600 uppercase tracking-widest">
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
                        <input type="text" :value="displayDiscount" @input="handleDiscountInput"
                            class="w-full bg-white dark:bg-surface-800 border-2 border-surface-200 dark:border-surface-700 rounded-xl pl-9 pr-4 py-3 text-sm font-black text-primary-600 focus:outline-none focus:border-primary-500 transition-all text-right"
                            placeholder="0" />
                    </div>
                </div>

                <div class="flex justify-between items-center text-2xl font-black pt-2">
                    <span class="text-text-primary text-sm sm:text-lg uppercase tracking-widest">Total
                        Bayar</span>
                    <span class="text-primary-600">{{ formatCurrency(cartTotal) }}</span>
                </div>
                <div class="flex gap-3 pt-2">
                    <button @click="emit('prev')"
                        class="w-16 h-16 flex-none bg-white dark:bg-surface-800 text-text-primary border-2 border-surface-200 dark:border-surface-700 rounded-[1.25rem] font-bold transition-all flex items-center justify-center hover:bg-surface-50 hover:border-surface-300">
                        <ArrowLeft :size="24" />
                    </button>
                    <button @click="emit('next')" :disabled="cartItems.length === 0"
                        class="flex-1 h-16 bg-primary-600 hover:bg-primary-500 disabled:opacity-50 text-white rounded-[1.25rem] font-bold text-lg shadow-xl shadow-primary-500/30 transition-all flex items-center justify-center gap-3">
                        Pembayaran
                        <ArrowRight :size="24" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Bundling Modal Teleport -->
        <Teleport to="body">
            <div v-if="showBundlingModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="closeBundlingModal"></div>
                <div
                    class="relative bg-white dark:bg-surface-800 rounded-[2rem] border border-surface-200 dark:border-surface-700 w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden shadow-2xl">
                    <div
                        class="p-6 border-b border-surface-100 dark:border-surface-700 flex justify-between items-center">
                        <h3 class="text-2xl font-black text-text-primary">Buat Sistem Bundling</h3>
                        <button @click="closeBundlingModal"
                            class="p-2 hover:bg-surface-100 dark:hover:bg-surface-700 rounded-full transition-colors">
                            <X :size="24" />
                        </button>
                    </div>

                    <div class="flex-1 overflow-hidden flex flex-col md:flex-row">
                        <!-- Left: Item Picker -->
                        <div
                            class="flex-1 p-6 overflow-y-auto custom-scrollbar border-r border-surface-100 dark:border-surface-700">
                            <div class="mb-6 sticky top-0 bg-white dark:bg-surface-800 z-10 pb-4">
                                <div class="relative">
                                    <Search class="absolute left-4 top-1/2 -translate-y-1/2 text-text-secondary"
                                        :size="18" />
                                    <input v-model="searchQuery" type="text" placeholder="Cari item untuk bundle..."
                                        class="w-full bg-surface-50 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 rounded-xl pl-11 pr-4 py-3 text-sm font-medium focus:outline-none focus:border-primary-500 transition-all" />
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
                        <div class="w-full md:w-[350px] bg-surface-50 dark:bg-surface-900 p-6 flex flex-col">
                            <h4 class="text-sm font-black text-text-secondary uppercase tracking-widest mb-4">Item
                                Terpilih</h4>

                            <div class="flex-1 overflow-y-auto custom-scrollbar space-y-3 mb-6">
                                <div v-if="bundleItems.length === 0"
                                    class="h-full flex flex-col items-center justify-center text-text-secondary opacity-50 py-10">
                                    <ShoppingBag :size="48" class="mb-3" />
                                    <p class="text-xs font-medium text-center">Belum ada item dipilih</p>
                                </div>
                                <div v-for="(item, idx) in bundleItems" :key="item.id"
                                    class="p-4 bg-white dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 animate-fade-in relative group/item">
                                    <button @click="removeFromBundle(idx)"
                                        class="absolute top-2 right-2 text-surface-400 hover:text-red-500 p-1 hover:bg-red-50 dark:hover:bg-red-500/10 rounded-lg transition-colors z-10">
                                        <Trash2 :size="16" />
                                    </button>

                                    <div class="mb-3 pr-6">
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
                                            <input type="text" :value="item.display_bundle_price"
                                                @input="e => handleBundleItemPriceInput(idx, e)"
                                                class="w-full bg-surface-50 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 rounded-lg pl-8 pr-3 py-2 text-xs font-black text-primary-600 outline-none focus:border-primary-500 transition-all h-9"
                                                :placeholder="formatNumber(item.selling_price || item.price || 0)" />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-6 border-t border-surface-200 dark:border-surface-700 mt-auto">
                                <label
                                    class="block text-xs font-black text-text-secondary uppercase tracking-widest mb-3">Harga
                                    Total Bundle</label>
                                <div class="relative mb-6">
                                    <span
                                        class="absolute left-4 top-1/2 -translate-y-1/2 text-text-secondary font-bold">Rp</span>
                                    <input :value="displayBundleTotalPrice" @input="handleBundlePriceInput" type="text"
                                        class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-5 py-4 bg-white dark:bg-surface-800 text-text-primary text-xl font-black focus:outline-none focus:border-primary-500 transition-all pl-12"
                                        placeholder="Tentukan harga..." />
                                </div>

                                <button @click="finishBundling"
                                    :disabled="bundleItems.length < 2 || bundleTotalPrice <= 0"
                                    class="w-full py-4 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white rounded-xl font-bold transition-all shadow-lg shadow-emerald-500/20 flex items-center justify-center gap-2">
                                    <CheckCircle :size="20" />
                                    Selesai
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
