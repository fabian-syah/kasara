<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { balancing } from '../../api/axios';
import {
    ArrowLeft, ArrowRight, Calendar, User, FileText, Camera, CreditCard,
    Plus, Trash2, Check, Loader2, AlertTriangle, X, Lock, Search,
    Building2, Scale, ChevronDown, Eye, EyeOff, ShoppingCart, ShoppingBag,
    CheckCircle, Package
} from 'lucide-vue-next';

const route = useRoute();
const router = useRouter();

// Branch info from query params
const branchId = computed(() => route.query.branch_id);
const branchName = computed(() => route.query.branch_name || 'Cabang');

// Wizard step
const currentStep = ref(1); // 1: Date, 2: Items, 3: Payment, 4: (submit via password modal)

// Helper for 5 AM reset date
function getBalancingDate() {
    const now = new Date();
    if (now.getHours() < 5) {
        now.setDate(now.getDate() - 1);
    }
    const yyyy = now.getFullYear();
    const mm = String(now.getMonth() + 1).padStart(2, '0');
    const dd = String(now.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
}

// Form state
const reportingDate = ref(getBalancingDate());
const customerName = ref('');
const customerWa = ref('');
const csUserId = ref(null);
const notes = ref('');
const proofImage = ref(null);
const imagePreview = ref(null);
const fileInputRef = ref(null);

const paymentProofImages = ref([]);
const paymentProofImagePreviews = ref([]);
const paymentProofInputRef = ref(null);

// Payment state
const paymentMethodId = ref(null);
const splitPayments = ref([]);
const useSplitPayment = ref(false);

// Data from API
const inventory = ref([]);
const csUsers = ref([]);
const paymentMethods = ref([]);
const loadingData = ref(true);
const submitting = ref(false);
const submitSuccess = ref(false);
const submitResult = ref(null);

// Cart state
const cartItems = ref([]);

// Search
const searchQuery = ref('');
const displayLimit = ref(50);

// CS search
const csSearch = ref('');
const showCsDropdown = ref(false);

// Password modal
const showPasswordModal = ref(false);
const passwordInput = ref('');
const passwordError = ref('');
const showPassword = ref(false);

// Validation
const errors = ref({});

// Click lock
const isClickLocked = ref(false);

// ===== Computed =====
const filteredProducts = computed(() => {
    let prods = inventory.value || [];
    const q = searchQuery.value?.toLowerCase();
    if (q) {
        prods = prods.filter(p =>
            (p.product?.name || p.name || '').toLowerCase().includes(q) ||
            (p.imei || '').toLowerCase().includes(q) ||
            (p.product?.brand || '').toLowerCase().includes(q)
        );
    }
    return prods.slice(0, displayLimit.value);
});

const totalFilteredCount = computed(() => {
    let prods = inventory.value || [];
    const q = searchQuery.value?.toLowerCase();
    if (q) {
        return prods.filter(p =>
            (p.product?.name || p.name || '').toLowerCase().includes(q) ||
            (p.imei || '').toLowerCase().includes(q) ||
            (p.product?.brand || '').toLowerCase().includes(q)
        ).length;
    }
    return prods.length;
});

const cartItemCount = computed(() => cartItems.value.reduce((sum, i) => sum + (i.quantity || 1), 0));

const cartSubtotal = computed(() => {
    return cartItems.value.reduce((sum, item) => {
        const price = Number(item.price || item.selling_price || 0);
        const qty = Number(item.quantity || 1);
        const discount = Number(item.item_discount || 0);
        return sum + (price * qty) - (discount * qty);
    }, 0);
});

const globalDiscount = ref(0);
const cartTotal = computed(() => Math.max(0, cartSubtotal.value - globalDiscount.value));

const filteredCsUsers = computed(() => {
    if (!csSearch.value) return csUsers.value;
    const q = csSearch.value.toLowerCase();
    return csUsers.value.filter(u =>
        u.name.toLowerCase().includes(q) ||
        (u.username && u.username.toLowerCase().includes(q))
    );
});

const paymentTotal = computed(() => {
    if (useSplitPayment.value) {
        return splitPayments.value.reduce((sum, sp) => sum + (parseFloat(sp.amount) || 0), 0);
    }
    return cartTotal.value;
});

// Computed: is photo required
const isPhotoRequired = computed(() => {
    let selectedIds = [];
    if (useSplitPayment.value) {
        selectedIds = splitPayments.value.map(sp => sp.payment_method_id).filter(id => id);
    } else if (paymentMethodId.value) {
        selectedIds = [paymentMethodId.value];
    }
    
    if (selectedIds.length === 0) return true;
    
    return selectedIds.some(id => {
        const method = paymentMethods.value.find(m => m.id === id);
        if (!method) return true;
        const name = method.name.toLowerCase();
        return !name.includes('cash') && !name.includes('tunai');
    });
});

// ===== Format helpers =====
function formatCurrency(value) {
    if (!value && value !== 0) return 'Rp 0';
    const num = typeof value === 'string' ? parseFloat(value.replace(/[^0-9.\-]/g, '')) : value;
    if (isNaN(num)) return 'Rp 0';
    return `Rp ${Math.abs(num).toLocaleString('id-ID')}`;
}

function formatNumber(n) {
    if (!n) return '0';
    return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function parseNumber(s) {
    if (!s) return 0;
    if (typeof s === 'number') return s;
    return parseInt(s.toString().replace(/[^0-9]/g, '')) || 0;
}

// ===== Cart logic =====
function getRemainingStock(product) {
    if (product.imei) {
        return cartItems.value.some(i => i.imei === product.imei) ? 0 : 1;
    }
    const inCart = cartItems.value
        .filter(i => i.id === product.id)
        .reduce((sum, i) => sum + (i.quantity || 1), 0);
    return (product.stock || product.quantity || 0) - inCart;
}

function isItemFullyOccupied(product) {
    return getRemainingStock(product) <= 0;
}

function addToCart(product) {
    if (isItemFullyOccupied(product)) return;

    if (!product.imei) {
        const existing = cartItems.value.find(i => i.id === product.id);
        if (existing) {
            existing.quantity = (existing.quantity || 1) + 1;
            return;
        }
    }

    cartItems.value.push({
        ...product,
        quantity: 1,
        item_discount: 0,
        _displayPrice: formatNumber(product.selling_price || product.price || 0),
        _displayDiscount: '0',
    });
}

function removeFromCart(index) {
    cartItems.value.splice(index, 1);
}

function incrementQty(index) {
    const item = cartItems.value[index];
    if (item.imei) return;
    if (getRemainingStock(item) <= 0) return;
    item.quantity = (item.quantity || 1) + 1;
}

function decrementQty(index) {
    const item = cartItems.value[index];
    if ((item.quantity || 1) > 1) {
        item.quantity--;
    }
}

function handlePriceInput(index, e) {
    const num = parseNumber(e.target.value);
    cartItems.value[index].price = num;
    cartItems.value[index].selling_price = num;
    cartItems.value[index]._displayPrice = formatNumber(num);
    e.target.value = formatNumber(num);
}

function handleItemDiscountInput(index, e) {
    const num = parseNumber(e.target.value);
    cartItems.value[index].item_discount = num;
    cartItems.value[index]._displayDiscount = formatNumber(num);
    e.target.value = formatNumber(num);
}

function handleGlobalDiscountInput(e) {
    globalDiscount.value = parseNumber(e.target.value);
    e.target.value = formatNumber(globalDiscount.value);
}

function loadMore() {
    displayLimit.value += 50;
}

watch(searchQuery, () => {
    displayLimit.value = 50;
});

// ===== Payment logic =====
function addSplitPayment() {
    splitPayments.value.push({ payment_method_id: null, amount: '' });
}

function removeSplitPayment(index) {
    if (splitPayments.value.length > 1) {
        splitPayments.value.splice(index, 1);
    }
}

function toggleSplitPayment() {
    useSplitPayment.value = !useSplitPayment.value;
    if (useSplitPayment.value && splitPayments.value.length === 0) {
        addSplitPayment();
    }
}

// ===== CS selection =====
function selectCsUser(user) {
    csUserId.value = user.id;
    csSearch.value = user.name;
    showCsDropdown.value = false;
}

// ===== Image upload =====
function triggerFileInput() { fileInputRef.value?.click(); }
function handleImageChange(event) {
    const file = event.target.files[0];
    if (!file) return;
    proofImage.value = file;
    const reader = new FileReader();
    reader.onload = (e) => { imagePreview.value = e.target.result; };
    reader.readAsDataURL(file);
}
function removeImage() {
    proofImage.value = null;
    imagePreview.value = null;
    if (fileInputRef.value) fileInputRef.value.value = '';
}

function triggerPaymentProofInput() { paymentProofInputRef.value?.click(); }
function handlePaymentProofChange(event) {
    const files = event.target.files;
    if (!files || files.length === 0) return;
    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        paymentProofImages.value.push(file);
        const reader = new FileReader();
        reader.onload = (e) => { paymentProofImagePreviews.value.push(e.target.result); };
        reader.readAsDataURL(file);
    }
    if (paymentProofInputRef.value) paymentProofInputRef.value.value = '';
}
function removePaymentProofImage(index) {
    paymentProofImages.value.splice(index, 1);
    paymentProofImagePreviews.value.splice(index, 1);
}

// ===== Navigation =====
function nextStep() {
    if (currentStep.value === 1) {
        if (!reportingDate.value) {
            errors.value = { reporting_date: 'Pilih tanggal terlebih dahulu' };
            return;
        }
        errors.value = {};
    }
    if (currentStep.value === 2) {
        if (cartItems.value.length === 0) {
            errors.value = { cart: 'Pilih minimal satu barang' };
            return;
        }
        errors.value = {};
    }
    if (currentStep.value < 3) currentStep.value++;
}

function prevStep() {
    if (currentStep.value > 1) currentStep.value--;
}

// ===== Validation =====
function validatePayment() {
    const errs = {};
    if (useSplitPayment.value) {
        const valid = splitPayments.value.filter(sp => sp.payment_method_id && sp.amount);
        if (valid.length === 0) errs.payment = 'Pilih minimal satu metode pembayaran';
    } else {
        if (!paymentMethodId.value) errs.payment = 'Pilih metode pembayaran';
    }

    if (isPhotoRequired.value && paymentProofImages.value.length === 0) {
        errs.payment_proof_images = 'Foto bukti pembayaran wajib diupload untuk metode selain Cash';
    }

    errors.value = errs;
    return Object.keys(errs).length === 0;
}

// ===== Submit =====
function handleSubmit() {
    if (isClickLocked.value) return;
    isClickLocked.value = true;
    setTimeout(() => { isClickLocked.value = false; }, 1000);

    if (!validatePayment()) return;
    showPasswordModal.value = true;
    passwordInput.value = '';
    passwordError.value = '';
}

async function confirmSubmit() {
    if (!passwordInput.value) {
        passwordError.value = 'Password wajib diisi';
        return;
    }
    submitting.value = true;
    passwordError.value = '';

    try {
        const formData = new FormData();
        formData.append('branch_id', branchId.value);
        formData.append('reporting_date', reportingDate.value);
        formData.append('password', passwordInput.value);
        formData.append('customer_name', customerName.value || '');
        formData.append('customer_wa', customerWa.value || '');
        if (csUserId.value) formData.append('customer_service_id', csUserId.value);
        formData.append('notes', notes.value || '');
        formData.append('selling_price', cartTotal.value);
        formData.append('global_discount_value', globalDiscount.value);
        formData.append('global_discount_type', 'fixed');
        formData.append('total_discount', globalDiscount.value);

        // Payment
        if (useSplitPayment.value) {
            const validSplits = splitPayments.value.filter(sp => sp.payment_method_id && sp.amount);
            formData.append('split_payments', JSON.stringify(validSplits));
            if (validSplits.length > 0) {
                formData.append('payment_method_id', validSplits[0].payment_method_id);
            }
        } else {
            formData.append('payment_method_id', paymentMethodId.value);
        }

        // Photo
        if (proofImage.value) {
            formData.append('photo', proofImage.value);
        }
        paymentProofImages.value.forEach((file, index) => {
            formData.append(`payment_proof_images[${index}]`, file);
        });

        // HP items (product_detail_ids) + meta
        const hpItems = cartItems.value.filter(i => i.imei);
        const nonHpItems = cartItems.value.filter(i => !i.imei);

        const productDetailIds = hpItems.map(i => i.id);
        productDetailIds.forEach((id, idx) => {
            formData.append(`product_detail_ids[${idx}]`, id);
        });

        // HP items meta
        const hpMeta = {};
        hpItems.forEach(item => {
            hpMeta[item.id] = {
                selling_price: item.price || item.selling_price || 0,
                item_discount: item.item_discount || 0,
                distributed_discount: 0,
            };
        });
        formData.append('hp_items_meta', JSON.stringify(hpMeta));

        // Non-HP items
        nonHpItems.forEach((item, idx) => {
            formData.append(`non_hp_items[${idx}][product_id]`, item.product_id || item.id);
            formData.append(`non_hp_items[${idx}][quantity]`, item.quantity || 1);
            formData.append(`non_hp_items[${idx}][selling_price]`, item.price || item.selling_price || 0);
            formData.append(`non_hp_items[${idx}][item_discount]`, item.item_discount || 0);
        });

        const { data } = await balancing.storeMissedSale(formData);

        submitSuccess.value = true;
        submitResult.value = data.data;
        showPasswordModal.value = false;
    } catch (e) {
        const resp = e.response?.data;
        if (resp?.message) {
            passwordError.value = resp.message;
        } else {
            passwordError.value = 'Terjadi kesalahan saat menyimpan.';
        }
    } finally {
        submitting.value = false;
    }
}

// ===== Fetch data =====
async function fetchData() {
    loadingData.value = true;
    try {
        const [invRes, usersRes, methodsRes] = await Promise.all([
            balancing.branchInventory(branchId.value),
            balancing.branchUsers(branchId.value),
            balancing.paymentMethods(branchId.value),
        ]);
        inventory.value = invRes.data.data || [];
        csUsers.value = usersRes.data.data || [];
        paymentMethods.value = methodsRes.data.data || [];
    } catch (e) {
        console.error('Failed to fetch form data:', e);
    } finally {
        loadingData.value = false;
    }
}

function goBack() {
    router.push('/balancing/missed-sale');
}

function goHome() {
    router.push('/balancing');
}

function createAnother() {
    submitSuccess.value = false;
    submitResult.value = null;
    currentStep.value = 1;
    reportingDate.value = getBalancingDate();
    customerName.value = '';
    customerWa.value = '';
    csUserId.value = null;
    csSearch.value = '';
    notes.value = '';
    proofImage.value = null;
    imagePreview.value = null;
    paymentProofImages.value = [];
    paymentProofImagePreviews.value = [];
    cartItems.value = [];
    paymentMethodId.value = null;
    splitPayments.value = [];
    useSplitPayment.value = false;
    globalDiscount.value = 0;
    errors.value = {};
}

function handleOutsideClick(e) {
    if (!e.target.closest('.cs-dropdown-container')) {
        showCsDropdown.value = false;
    }
}

onMounted(() => {
    if (!branchId.value) {
        router.push('/balancing/missed-sale');
        return;
    }
    fetchData();
});
</script>

<template>
    <div class="max-w-6xl mx-auto" @click="handleOutsideClick">

        <!-- Success State -->
        <div v-if="submitSuccess" class="text-center py-12">
            <div class="w-20 h-20 mx-auto rounded-full bg-gradient-to-br from-emerald-400 to-teal-500 flex items-center justify-center mb-6 shadow-xl shadow-emerald-500/20 animate-bounce-once">
                <Check :size="36" class="text-white" />
            </div>
            <h2 class="text-2xl font-bold text-neutral-900 dark:text-white mb-2">Balancing Berhasil!</h2>
            <p class="text-neutral-500 dark:text-neutral-400 mb-2">Data penjualan terlewat telah tersimpan.</p>

            <div v-if="submitResult" class="inline-flex flex-col items-center gap-1 mt-3 mb-8 p-4 rounded-2xl bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700">
                <span class="text-xs text-neutral-500 dark:text-neutral-400">No. Receipt</span>
                <span class="text-lg font-bold text-amber-600 dark:text-amber-400 font-mono">{{ submitResult.receipt_id }}</span>
                <span class="text-xs text-neutral-500 dark:text-neutral-400 mt-1">
                    Cabang: {{ submitResult.branch?.name || branchName }}
                </span>
                <span class="text-xs text-neutral-500 dark:text-neutral-400">
                    Tanggal Masuk Omset: {{ submitResult.reporting_date }}
                </span>
                <span class="text-sm font-semibold mt-1 text-emerald-600 dark:text-emerald-400">
                    {{ formatCurrency(submitResult.selling_price) }}
                </span>
            </div>

            <div class="flex items-center justify-center gap-3">
                <button @click="createAnother"
                    class="px-6 py-3 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-medium text-sm transition-colors">
                    Buat Balancing Lagi
                </button>
                <button @click="goHome"
                    class="px-6 py-3 rounded-xl border border-neutral-200 dark:border-neutral-700 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-800 font-medium text-sm transition-colors">
                    Kembali ke Menu
                </button>
            </div>
        </div>

        <!-- Form State -->
        <div v-else>
            <!-- Header -->
            <div class="mb-6">
                <button @click="goBack"
                    class="flex items-center gap-2 text-sm text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 transition-colors mb-4 group">
                    <ArrowLeft :size="16" class="transition-transform group-hover:-translate-x-1" />
                    <span>Pilih Cabang Lain</span>
                </button>

                <div class="flex items-center gap-3 mb-3">
                    <div class="p-2.5 rounded-2xl bg-gradient-to-br from-amber-500 to-orange-600 text-white shadow-lg shadow-amber-500/20">
                        <ShoppingBag :size="24" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-neutral-900 dark:text-white">Balancing Penjualan Terlewat</h1>
                        <div class="flex items-center gap-2 mt-1">
                            <Building2 :size="14" class="text-amber-500" />
                            <span class="text-sm font-medium text-amber-600 dark:text-amber-400">{{ branchName }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step Indicator -->
            <div class="flex items-center gap-0 mb-8 p-1.5 rounded-2xl bg-neutral-100 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700">
                <button v-for="step in 3" :key="step"
                    @click="step < currentStep ? currentStep = step : null"
                    class="flex-1 flex items-center justify-center gap-2 py-2.5 rounded-xl text-xs font-semibold transition-all"
                    :class="currentStep === step
                        ? 'bg-white dark:bg-neutral-700 text-amber-600 dark:text-amber-400 shadow-sm'
                        : step < currentStep
                            ? 'text-emerald-600 dark:text-emerald-400 cursor-pointer hover:bg-white/50 dark:hover:bg-neutral-700/50'
                            : 'text-neutral-400 cursor-default'">
                    <div class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold"
                        :class="currentStep === step
                            ? 'bg-amber-500 text-white'
                            : step < currentStep
                                ? 'bg-emerald-500 text-white'
                                : 'bg-neutral-300 dark:bg-neutral-600 text-white'">
                        <Check v-if="step < currentStep" :size="10" />
                        <span v-else>{{ step }}</span>
                    </div>
                    <span class="hidden sm:inline">{{ ['Tanggal', 'Pilih Barang', 'Pembayaran'][step - 1] }}</span>
                </button>
            </div>

            <!-- Loading -->
            <div v-if="loadingData" class="flex items-center justify-center py-20">
                <Loader2 :size="32" class="animate-spin text-amber-500" />
            </div>

            <!-- ====== STEP 1: DATE PICKER ====== -->
            <div v-else-if="currentStep === 1" class="max-w-lg mx-auto">
                <div class="bg-white dark:bg-neutral-800/50 rounded-2xl border border-neutral-200 dark:border-neutral-700 p-8 shadow-sm">
                    <div class="flex flex-col items-center text-center mb-8">
                        <div class="w-16 h-16 rounded-2xl bg-amber-50 dark:bg-amber-900/20 flex items-center justify-center mb-4">
                            <Calendar :size="32" class="text-amber-500" />
                        </div>
                        <h2 class="text-xl font-bold text-neutral-900 dark:text-white mb-2">Pilih Tanggal Balancing</h2>
                        <p class="text-sm text-neutral-500 dark:text-neutral-400">Omset akan masuk di tanggal yang Anda pilih, bukan tanggal hari ini.</p>
                    </div>

                    <div>
                        <label class="flex items-center gap-2 text-sm font-semibold text-neutral-700 dark:text-neutral-300 mb-2">
                            <Calendar :size="16" class="text-amber-500" />
                            Tanggal Masuk Omset *
                        </label>
                        <input
                            v-model="reportingDate"
                            type="date"
                            class="w-full px-4 py-3.5 rounded-xl border text-sm transition-all focus:outline-none focus:ring-2 focus:ring-amber-500/40 focus:border-amber-500 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white [color-scheme:light] dark:[color-scheme:dark]"
                            :class="errors.reporting_date ? 'border-red-300 dark:border-red-700' : 'border-neutral-200 dark:border-neutral-700'"
                        />
                        <p v-if="errors.reporting_date" class="mt-1.5 text-xs text-red-500">{{ errors.reporting_date }}</p>
                    </div>

                    <button @click="nextStep" :disabled="!reportingDate"
                        class="w-full mt-8 py-4 rounded-2xl bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-600 hover:to-orange-700 text-white font-semibold text-sm shadow-lg shadow-amber-500/20 transition-all flex items-center justify-center gap-2 disabled:opacity-50">
                        Lanjut Pilih Barang
                        <ArrowRight :size="18" />
                    </button>
                </div>
            </div>

            <!-- ====== STEP 2: ITEM SELECTION ====== -->
            <div v-else-if="currentStep === 2" class="flex flex-col lg:flex-row gap-6 items-start">
                <!-- Left: Product List -->
                <div class="flex-[2] flex flex-col min-w-0 w-full">
                    <!-- Search Bar -->
                    <div class="bg-white dark:bg-neutral-800/50 rounded-2xl border border-neutral-200 dark:border-neutral-700 p-4 mb-4 shadow-sm">
                        <div class="relative">
                            <Search class="absolute left-4 top-1/2 -translate-y-1/2 text-neutral-400" :size="18" />
                            <input v-model="searchQuery" type="text" placeholder="Cari produk, IMEI, atau brand..."
                                class="w-full bg-neutral-50 dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 rounded-xl pl-11 pr-4 py-3 text-sm font-medium text-neutral-900 dark:text-white placeholder-neutral-400 focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 transition-all" />
                        </div>
                        <div class="flex items-center justify-between mt-3">
                            <span class="text-xs text-neutral-500">
                                {{ totalFilteredCount }} produk ditemukan
                            </span>
                            <span class="text-xs font-semibold text-amber-600 bg-amber-50 dark:bg-amber-900/20 px-2 py-1 rounded-lg">
                                Tanggal: {{ reportingDate }}
                            </span>
                        </div>
                    </div>

                    <!-- Product Table -->
                    <div class="bg-white dark:bg-neutral-800/50 rounded-2xl border border-neutral-200 dark:border-neutral-700 shadow-sm overflow-y-auto max-h-[600px]">
                        <!-- Desktop Table -->
                        <table class="w-full text-left border-collapse hidden md:table">
                            <thead class="sticky top-0 bg-neutral-50 dark:bg-neutral-900 z-10">
                                <tr>
                                    <th class="px-4 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wider border-b border-neutral-200 dark:border-neutral-700">Produk</th>
                                    <th class="px-4 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wider border-b border-neutral-200 dark:border-neutral-700">IMEI / Stok</th>
                                    <th class="px-4 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wider border-b border-neutral-200 dark:border-neutral-700 text-right">Harga</th>
                                    <th class="px-4 py-3 border-b border-neutral-200 dark:border-neutral-700 w-16"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-100 dark:divide-neutral-700/50">
                                <tr v-for="item in filteredProducts" :key="item.id + (item.imei || '')"
                                    class="hover:bg-amber-50/50 dark:hover:bg-amber-900/10 transition-colors"
                                    :class="{ 'opacity-40': isItemFullyOccupied(item) }">
                                    <td class="px-4 py-3">
                                        <div class="flex flex-col gap-0.5">
                                            <span class="font-semibold text-neutral-900 dark:text-white text-sm">{{ item.product?.name || item.name }}</span>
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs text-amber-600 font-medium">{{ item.product?.brand || '-' }}</span>
                                                <span v-if="item.ram || item.storage" class="text-xs text-neutral-500 bg-neutral-100 dark:bg-neutral-800 px-1.5 py-0.5 rounded">
                                                    {{ item.ram ? item.ram + '/' : '' }}{{ item.storage || '' }}
                                                </span>
                                                <span class="text-[10px] uppercase px-1.5 py-0.5 rounded font-semibold"
                                                    :class="item.condition === 'new' ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600' : 'bg-amber-50 dark:bg-amber-900/20 text-amber-600'">
                                                    {{ item.condition || 'Second' }}
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <code v-if="item.imei" class="text-xs font-mono text-neutral-700 dark:text-neutral-300 bg-neutral-50 dark:bg-neutral-900 px-2 py-1 rounded border border-neutral-200 dark:border-neutral-700">{{ item.imei }}</code>
                                        <span v-else class="text-xs font-semibold text-amber-600 bg-amber-50 dark:bg-amber-900/20 px-2.5 py-1 rounded-lg">Sisa: {{ getRemainingStock(item) }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <span class="text-sm font-bold text-amber-600">{{ formatCurrency(item.selling_price || item.price) }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <button v-if="!isItemFullyOccupied(item)" @click="addToCart(item)"
                                            class="w-9 h-9 flex items-center justify-center bg-amber-100 dark:bg-amber-900/30 text-amber-600 hover:bg-amber-500 hover:text-white rounded-lg transition-all">
                                            <Plus :size="18" />
                                        </button>
                                        <div v-else class="flex items-center gap-1 text-emerald-600 text-[10px] font-semibold uppercase bg-emerald-50 dark:bg-emerald-900/20 px-2 py-1.5 rounded-lg justify-center">
                                            <CheckCircle :size="12" />
                                            <span>Added</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Mobile Card List -->
                        <div class="md:hidden divide-y divide-neutral-100 dark:divide-neutral-700/50">
                            <div v-for="item in filteredProducts" :key="item.id + (item.imei || '')"
                                class="p-3 flex items-center justify-between gap-3"
                                :class="{ 'opacity-40': isItemFullyOccupied(item) }">
                                <div class="flex-1 min-w-0" @click="!isItemFullyOccupied(item) ? addToCart(item) : null">
                                    <span class="text-sm font-semibold text-neutral-900 dark:text-white truncate block">{{ item.product?.name || item.name }}</span>
                                    <div class="flex items-center gap-1.5 flex-wrap mt-0.5">
                                        <span class="text-[10px] text-amber-600 font-medium">{{ item.product?.brand || '-' }}</span>
                                        <span v-if="item.ram || item.storage" class="text-[10px] text-neutral-500 bg-neutral-100 dark:bg-neutral-800 px-1 py-0.5 rounded">{{ item.ram ? item.ram + '/' : '' }}{{ item.storage }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 mt-1">
                                        <code v-if="item.imei" class="text-[9px] font-mono text-neutral-500 truncate max-w-[100px]">{{ item.imei }}</code>
                                        <span v-else class="text-[10px] font-semibold text-amber-600">Stok: {{ getRemainingStock(item) }}</span>
                                        <span class="text-xs font-bold text-amber-600">{{ formatCurrency(item.selling_price || item.price) }}</span>
                                    </div>
                                </div>
                                <button v-if="!isItemFullyOccupied(item)" @click="addToCart(item)"
                                    class="w-9 h-9 flex items-center justify-center bg-amber-100 dark:bg-amber-900/30 text-amber-600 rounded-lg shrink-0">
                                    <Plus :size="18" />
                                </button>
                                <CheckCircle v-else :size="16" class="text-emerald-500 shrink-0" />
                            </div>
                        </div>

                        <!-- Load More -->
                        <div v-if="totalFilteredCount > displayLimit" class="p-4 flex justify-center border-t border-neutral-100 dark:border-neutral-700/50">
                            <button @click="loadMore" class="px-6 py-2.5 border-2 border-amber-500 text-amber-600 rounded-xl text-sm font-semibold hover:bg-amber-50 dark:hover:bg-amber-900/10 transition-colors flex items-center gap-2">
                                <Plus :size="14" />
                                Tampilkan Lebih Banyak ({{ totalFilteredCount - displayLimit }} sisa)
                            </button>
                        </div>

                        <!-- Empty State -->
                        <div v-if="filteredProducts.length === 0" class="py-16 text-center">
                            <Search :size="40" class="mx-auto text-neutral-300 dark:text-neutral-600 mb-3" />
                            <p class="text-neutral-500 text-sm">Produk tidak ditemukan</p>
                        </div>
                    </div>
                </div>

                <!-- Right: Cart -->
                <div class="w-full lg:w-[380px] shrink-0 lg:sticky lg:top-4">
                    <div class="bg-white dark:bg-neutral-800/50 rounded-2xl border border-neutral-200 dark:border-neutral-700 shadow-lg overflow-hidden flex flex-col max-h-[calc(100vh-120px)]">
                        <!-- Cart Header -->
                        <div class="p-4 border-b border-neutral-100 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-900 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <ShoppingCart :size="20" class="text-amber-500" />
                                <span class="text-sm font-bold text-neutral-900 dark:text-white">Keranjang</span>
                                <span class="px-2 py-0.5 bg-amber-500 text-white text-xs font-bold rounded-full">{{ cartItemCount }}</span>
                            </div>
                        </div>

                        <!-- Cart Items -->
                        <div class="flex-1 overflow-y-auto p-3 space-y-2 min-h-0">
                            <div v-if="cartItems.length === 0" class="py-10 text-center">
                                <ShoppingCart :size="40" class="mx-auto text-neutral-300 dark:text-neutral-600 mb-3" />
                                <p class="text-sm text-neutral-500">Keranjang kosong</p>
                                <p class="text-xs text-neutral-400 mt-1">Pilih produk dari daftar</p>
                            </div>

                            <div v-for="(item, idx) in cartItems" :key="idx"
                                class="p-3 bg-neutral-50 dark:bg-neutral-800 rounded-xl border border-neutral-100 dark:border-neutral-700/50">
                                <div class="flex justify-between items-start mb-2 gap-2">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold text-neutral-900 dark:text-white truncate">{{ item.product?.name || item.name }}</p>
                                        <code v-if="item.imei" class="text-[10px] font-mono text-neutral-500">{{ item.imei }}</code>
                                    </div>
                                    <button @click="removeFromCart(idx)" class="p-1.5 text-neutral-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors shrink-0">
                                        <Trash2 :size="14" />
                                    </button>
                                </div>

                                <div class="flex items-end justify-between gap-2">
                                    <!-- Qty controls -->
                                    <div v-if="!item.imei" class="flex items-center gap-1">
                                        <button @click="decrementQty(idx)" class="w-7 h-7 flex items-center justify-center bg-neutral-200 dark:bg-neutral-700 rounded text-neutral-700 dark:text-neutral-300 text-xs font-bold hover:bg-neutral-300 transition-colors">−</button>
                                        <span class="w-8 text-center text-sm font-bold">{{ item.quantity || 1 }}</span>
                                        <button @click="incrementQty(idx)" class="w-7 h-7 flex items-center justify-center bg-neutral-200 dark:bg-neutral-700 rounded text-neutral-700 dark:text-neutral-300 text-xs font-bold hover:bg-neutral-300 transition-colors">+</button>
                                    </div>
                                    <span v-else class="text-xs text-neutral-500">1x</span>

                                    <!-- Price -->
                                    <div class="flex flex-col items-end gap-1">
                                        <div class="flex items-center gap-1 bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 rounded-lg px-2 py-1.5">
                                            <span class="text-[10px] text-neutral-400">Rp</span>
                                            <input type="text" :value="item._displayPrice || formatNumber(item.price || item.selling_price || 0)"
                                                @input="handlePriceInput(idx, $event)"
                                                class="w-20 text-right text-xs font-bold bg-transparent outline-none text-neutral-900 dark:text-white" />
                                        </div>
                                        <div class="flex items-center gap-1 bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-800/30 rounded-lg px-2 py-1">
                                            <span class="text-[9px] text-amber-500">Diskon</span>
                                            <input type="text" :value="item._displayDiscount || '0'"
                                                @input="handleItemDiscountInput(idx, $event)"
                                                class="w-16 text-right text-[11px] font-bold bg-transparent outline-none text-amber-600" placeholder="0" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Cart Footer -->
                        <div class="p-3 bg-neutral-50 dark:bg-neutral-900 border-t border-neutral-200 dark:border-neutral-700 space-y-2">
                            <div class="flex justify-between text-xs font-medium text-neutral-500">
                                <span>Subtotal</span>
                                <span>{{ formatCurrency(cartSubtotal) }}</span>
                            </div>
                            <!-- Global Discount -->
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-xs font-medium text-neutral-500">Diskon Global</span>
                                <div class="flex items-center gap-1 bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg px-2 py-1.5">
                                    <span class="text-[10px] text-neutral-400">Rp</span>
                                    <input type="text" :value="formatNumber(globalDiscount)"
                                        @input="handleGlobalDiscountInput($event)"
                                        class="w-20 text-right text-xs font-bold bg-transparent outline-none text-amber-600" placeholder="0" />
                                </div>
                            </div>
                            <div class="flex justify-between text-sm font-bold pt-1 border-t border-neutral-200 dark:border-neutral-700">
                                <span class="text-neutral-900 dark:text-white">Total</span>
                                <span class="text-amber-600">{{ formatCurrency(cartTotal) }}</span>
                            </div>
                            <p v-if="errors.cart" class="text-xs text-red-500">{{ errors.cart }}</p>

                            <div class="flex gap-2 pt-1">
                                <button @click="prevStep"
                                    class="w-10 h-10 flex items-center justify-center border border-neutral-200 dark:border-neutral-700 rounded-xl text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors">
                                    <ArrowLeft :size="16" />
                                </button>
                                <button @click="nextStep" :disabled="cartItems.length === 0"
                                    class="flex-1 h-10 bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-600 hover:to-orange-700 disabled:opacity-50 text-white rounded-xl font-semibold text-sm transition-all flex items-center justify-center gap-2">
                                    Pembayaran
                                    <ArrowRight :size="16" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ====== STEP 3: PAYMENT ====== -->
            <form v-else-if="currentStep === 3" @submit.prevent="handleSubmit" class="max-w-3xl mx-auto space-y-6">
                <!-- Back -->
                <button type="button" @click="prevStep"
                    class="flex items-center gap-2 text-sm text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 transition-colors group">
                    <ArrowLeft :size="16" class="transition-transform group-hover:-translate-x-1" />
                    <span>Kembali ke Keranjang</span>
                </button>

                <!-- Cart Summary -->
                <div class="p-4 rounded-2xl bg-amber-50/50 dark:bg-amber-900/10 border border-amber-200/60 dark:border-amber-800/30">
                    <div class="flex items-center gap-2 mb-3">
                        <ShoppingCart :size="16" class="text-amber-500" />
                        <span class="text-sm font-semibold text-amber-700 dark:text-amber-300">Ringkasan Keranjang</span>
                    </div>
                    <div class="space-y-1">
                        <div v-for="(item, idx) in cartItems" :key="idx" class="flex justify-between text-xs text-neutral-600 dark:text-neutral-400">
                            <span class="truncate flex-1 mr-2">{{ item.product?.name || item.name }} {{ item.imei ? `(${item.imei})` : '' }} x{{ item.quantity || 1 }}</span>
                            <span class="font-medium shrink-0">{{ formatCurrency((item.price || item.selling_price || 0) * (item.quantity || 1)) }}</span>
                        </div>
                    </div>
                    <div class="mt-3 pt-2 border-t border-amber-200/50 dark:border-amber-800/30 flex justify-between font-bold text-sm">
                        <span class="text-amber-700 dark:text-amber-300">Total Bayar</span>
                        <span class="text-amber-600">{{ formatCurrency(cartTotal) }}</span>
                    </div>
                </div>

                <!-- Customer Name -->
                <div>
                    <label class="flex items-center gap-2 text-sm font-semibold text-neutral-700 dark:text-neutral-300 mb-2">
                        <User :size="16" class="text-amber-500" />
                        Nama Customer
                    </label>
                    <input v-model="customerName" type="text" placeholder="Opsional..."
                        class="w-full px-4 py-3 rounded-xl border border-neutral-200 dark:border-neutral-700 text-sm bg-white dark:bg-neutral-800/50 text-neutral-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-amber-500/40 focus:border-amber-500 transition-all" />
                </div>

                <!-- Customer WA -->
                <div>
                    <label class="flex items-center gap-2 text-sm font-semibold text-neutral-700 dark:text-neutral-300 mb-2">
                        <User :size="16" class="text-amber-500" />
                        No. WA Customer
                    </label>
                    <input v-model="customerWa" type="text" placeholder="Opsional..."
                        class="w-full px-4 py-3 rounded-xl border border-neutral-200 dark:border-neutral-700 text-sm bg-white dark:bg-neutral-800/50 text-neutral-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-amber-500/40 focus:border-amber-500 transition-all" />
                </div>

                <!-- CS User -->
                <div class="cs-dropdown-container relative">
                    <label class="flex items-center gap-2 text-sm font-semibold text-neutral-700 dark:text-neutral-300 mb-2">
                        <User :size="16" class="text-amber-500" />
                        Customer Service
                    </label>
                    <div class="relative">
                        <input v-model="csSearch" @focus="showCsDropdown = true" type="text" placeholder="Cari CS..."
                            class="w-full px-4 py-3 pr-10 rounded-xl border border-neutral-200 dark:border-neutral-700 text-sm bg-white dark:bg-neutral-800/50 text-neutral-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-amber-500/40 focus:border-amber-500 transition-all" />
                        <button type="button" @click.stop="showCsDropdown = !showCsDropdown" class="absolute right-2 top-1/2 -translate-y-1/2 p-2 text-neutral-400">
                            <ChevronDown :size="16" class="transition-transform" :class="showCsDropdown ? 'rotate-180' : ''" />
                        </button>
                    </div>
                    <div v-if="showCsDropdown && filteredCsUsers.length"
                        class="absolute z-50 mt-1 w-full max-h-60 overflow-y-auto rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 shadow-xl p-1.5">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-1">
                            <button v-for="user in filteredCsUsers" :key="user.id" @click.prevent="selectCsUser(user)"
                                class="w-full text-left px-3 py-2 text-sm rounded-lg text-neutral-700 dark:text-neutral-300 hover:bg-amber-50 dark:hover:bg-amber-900/20 transition-colors flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
                                <span class="font-medium truncate">{{ user.name }}</span>
                                <span v-if="user.username" class="text-neutral-400 text-[10px] truncate">({{ user.username }})</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Payment Method -->
                <div>
                    <label class="flex items-center gap-2 text-sm font-semibold text-neutral-700 dark:text-neutral-300 mb-2">
                        <CreditCard :size="16" class="text-amber-500" />
                        Metode Pembayaran *
                    </label>

                    <!-- Toggle split -->
                    <div class="flex items-center gap-3 mb-3">
                        <button type="button" @click="toggleSplitPayment"
                            class="text-xs font-medium px-3 py-1.5 rounded-lg border transition-all"
                            :class="useSplitPayment
                                ? 'bg-amber-50 dark:bg-amber-900/20 border-amber-300 dark:border-amber-700 text-amber-600'
                                : 'border-neutral-200 dark:border-neutral-700 text-neutral-500 hover:border-amber-300'">
                            {{ useSplitPayment ? '✓ Split Payment Aktif' : 'Gunakan Split Payment' }}
                        </button>
                    </div>

                    <!-- Single Payment -->
                    <div v-if="!useSplitPayment">
                        <select v-model="paymentMethodId"
                            class="w-full px-4 py-3 rounded-xl border border-neutral-200 dark:border-neutral-700 text-sm bg-white dark:bg-neutral-800/50 text-neutral-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-amber-500/40 focus:border-amber-500 transition-all">
                            <option :value="null" disabled>Pilih metode pembayaran...</option>
                            <option v-for="m in paymentMethods" :key="m.id" :value="m.id">{{ m.name }}</option>
                        </select>
                    </div>

                    <!-- Split Payments -->
                    <div v-else class="space-y-3">
                        <div v-for="(sp, idx) in splitPayments" :key="idx"
                            class="flex items-start gap-3 p-3 rounded-xl bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-100 dark:border-neutral-700/50">
                            <div class="flex-1">
                                <label class="text-[10px] text-neutral-500 mb-1 block">Metode</label>
                                <select v-model="sp.payment_method_id"
                                    class="w-full px-3 py-2 rounded-lg border border-neutral-200 dark:border-neutral-600 text-sm bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-amber-500/40 transition-all">
                                    <option :value="null" disabled>Pilih...</option>
                                    <option v-for="m in paymentMethods" :key="m.id" :value="m.id">{{ m.name }}</option>
                                </select>
                            </div>
                            <div class="w-36">
                                <label class="text-[10px] text-neutral-500 mb-1 block">Jumlah (Rp)</label>
                                <input v-model="sp.amount" type="number" placeholder="0"
                                    class="w-full px-3 py-2 rounded-lg border border-neutral-200 dark:border-neutral-600 text-sm bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-amber-500/40 transition-all font-mono" />
                            </div>
                            <button v-if="splitPayments.length > 1" @click="removeSplitPayment(idx)" type="button"
                                class="mt-6 p-1.5 text-red-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">
                                <Trash2 :size="14" />
                            </button>
                        </div>
                        <button type="button" @click="addSplitPayment"
                            class="w-full py-2.5 rounded-xl border border-dashed border-amber-300 dark:border-amber-700 text-amber-600 text-sm font-medium hover:bg-amber-50 dark:hover:bg-amber-900/10 transition-colors flex items-center justify-center gap-2">
                            <Plus :size="14" />
                            Tambah Metode
                        </button>
                    </div>
                    <p v-if="errors.payment" class="mt-1.5 text-xs text-red-500">{{ errors.payment }}</p>
                </div>

                <!-- Notes -->
                <div>
                    <label class="flex items-center gap-2 text-sm font-semibold text-neutral-700 dark:text-neutral-300 mb-2">
                        <FileText :size="16" class="text-amber-500" />
                        Keterangan / Notes
                    </label>
                    <textarea v-model="notes" rows="3" placeholder="Catatan tentang penjualan terlewat..."
                        class="w-full px-4 py-3 rounded-xl border border-neutral-200 dark:border-neutral-700 text-sm bg-white dark:bg-neutral-800/50 text-neutral-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-amber-500/40 focus:border-amber-500 transition-all resize-none"></textarea>
                </div>

                <!-- Photo -->
                <div>
                    <label class="flex items-center gap-2 text-sm font-semibold text-neutral-700 dark:text-neutral-300 mb-2">
                        <Camera :size="16" class="text-amber-500" />
                        Foto Bukti (Fisik) 
                    </label>
                    <input ref="fileInputRef" type="file" accept="image/*" class="hidden" @change="handleImageChange" />

                    <div v-if="!imagePreview" @click="triggerFileInput"
                        class="w-full border-2 border-dashed rounded-2xl p-6 text-center cursor-pointer transition-all hover:border-amber-400 hover:bg-amber-50/50 dark:hover:bg-amber-950/20"
                        :class="errors.photo ? 'border-red-300 dark:border-red-700' : 'border-neutral-200 dark:border-neutral-700'">
                        <Camera :size="28" class="mx-auto text-neutral-300 dark:text-neutral-600 mb-2" />
                        <p class="text-sm text-neutral-500">Klik untuk upload (opsional)</p>
                        <p class="text-xs text-neutral-400 mt-1">PNG, JPG (Maks. 20MB)</p>
                    </div>

                    <div v-else class="relative rounded-2xl overflow-hidden border border-neutral-200 dark:border-neutral-700">
                        <img :src="imagePreview" class="w-full max-h-48 object-contain bg-neutral-50 dark:bg-neutral-800" />
                        <button @click="removeImage" type="button"
                            class="absolute top-2 right-2 p-1.5 rounded-full bg-red-500 hover:bg-red-600 text-white shadow-lg transition-colors">
                            <X :size="12" />
                        </button>
                    </div>
                    <p v-if="errors.photo" class="mt-1.5 text-xs text-red-500">{{ errors.photo }}</p>
                </div>

                <!-- Foto Bukti Pembayaran -->
                <div v-if="isPhotoRequired">
                    <label class="flex items-center gap-2 text-sm font-semibold text-neutral-700 dark:text-neutral-300 mb-2">
                        <CreditCard :size="16" class="text-amber-500" />
                        Foto Bukti Pembayaran / Transfer (Bisa Lebih Dari Satu) *
                    </label>
                    <input ref="paymentProofInputRef" type="file" accept="image/*" multiple class="hidden" @change="handlePaymentProofChange" />

                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-3">
                        <div v-for="(preview, idx) in paymentProofImagePreviews" :key="idx" class="relative rounded-2xl overflow-hidden border border-amber-200 dark:border-amber-700/50 group">
                            <img :src="preview" class="w-full h-32 object-cover bg-neutral-50 dark:bg-neutral-800" />
                            <button @click.prevent="removePaymentProofImage(idx)" type="button"
                                class="absolute top-2 right-2 p-1.5 rounded-full bg-red-500 hover:bg-red-600 text-white shadow-lg transition-colors opacity-0 group-hover:opacity-100">
                                <X :size="12" />
                            </button>
                        </div>
                    </div>

                    <div @click="triggerPaymentProofInput"
                        class="w-full border-2 border-dashed border-amber-300 dark:border-amber-600/50 rounded-2xl p-6 text-center cursor-pointer transition-all hover:border-amber-500 hover:bg-amber-50/50 dark:hover:bg-amber-950/20"
                        :class="errors.payment_proof_images ? 'border-red-300 dark:border-red-700' : 'bg-amber-50/10 dark:bg-amber-900/10'">
                        <Camera :size="28" class="mx-auto text-amber-500 mb-2" />
                        <p class="text-sm font-semibold text-amber-700 dark:text-amber-500">Tambah Bukti Pembayaran</p>
                        <p class="text-xs text-amber-600/70 mt-1">Upload satu per satu atau sekaligus</p>
                    </div>

                    <p v-if="errors.payment_proof_images" class="mt-1.5 text-xs text-red-500">{{ errors.payment_proof_images }}</p>
                </div>

                <!-- Submit -->
                <div class="pt-4 border-t border-neutral-100 dark:border-neutral-800">
                    <button type="submit" :disabled="isClickLocked"
                        class="w-full py-4 rounded-2xl bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-600 hover:to-orange-700 text-white font-semibold text-sm shadow-lg shadow-amber-500/20 transition-all flex items-center justify-center gap-2 disabled:opacity-50">
                        <Loader2 v-if="isClickLocked" class="animate-spin" :size="18" />
                        <Check v-else :size="18" />
                        <span>{{ isClickLocked ? 'Memproses...' : 'Selesaikan Transaksi' }}</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Password Modal -->
        <Teleport to="body">
            <div v-if="showPasswordModal" class="fixed inset-0 z-[99999] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showPasswordModal = false"></div>
                <div class="relative w-full max-w-sm bg-white dark:bg-neutral-800 rounded-3xl shadow-2xl p-6 transform transition-all">
                    <div class="flex justify-center mb-5">
                        <div class="w-16 h-16 rounded-full bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center shadow-lg shadow-amber-500/20">
                            <Lock :size="28" class="text-white" />
                        </div>
                    </div>
                    <h3 class="text-lg font-bold text-center text-neutral-900 dark:text-white mb-1">Verifikasi Password</h3>
                    <p class="text-sm text-center text-neutral-500 dark:text-neutral-400 mb-5">Masukkan password Super Admin</p>

                    <div class="relative">
                        <input v-model="passwordInput" :type="showPassword ? 'text' : 'password'"
                            placeholder="Masukkan password..." @keyup.enter="confirmSubmit"
                            class="w-full px-4 py-3 pr-12 rounded-xl border border-neutral-200 dark:border-neutral-600 text-sm bg-neutral-50 dark:bg-neutral-700 text-neutral-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-amber-500/40 focus:border-amber-500 transition-all"
                            :class="passwordError ? '!border-red-400' : ''" />
                        <button type="button" @click="showPassword = !showPassword"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 transition-colors">
                            <EyeOff v-if="showPassword" :size="20" />
                            <Eye v-else :size="20" />
                        </button>
                    </div>
                    <p v-if="passwordError" class="mt-2 text-xs text-red-500 text-center">{{ passwordError }}</p>

                    <div class="flex gap-3 mt-6">
                        <button @click="showPasswordModal = false" type="button"
                            class="flex-1 py-3 rounded-xl border border-neutral-200 dark:border-neutral-600 text-neutral-700 dark:text-neutral-300 text-sm font-medium hover:bg-neutral-50 dark:hover:bg-neutral-700 transition-colors">
                            Batal
                        </button>
                        <button @click="confirmSubmit" type="button" :disabled="submitting"
                            class="flex-1 py-3 rounded-xl bg-gradient-to-r from-amber-500 to-orange-600 text-white text-sm font-medium transition-all hover:from-amber-600 hover:to-orange-700 disabled:opacity-60 flex items-center justify-center gap-2">
                            <Loader2 v-if="submitting" :size="16" class="animate-spin" />
                            <span>{{ submitting ? 'Menyimpan...' : 'Konfirmasi' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
.animate-bounce-once {
    animation: bounceOnce 0.6s ease-out;
}
@keyframes bounceOnce {
    0% { transform: scale(0.5); opacity: 0; }
    60% { transform: scale(1.1); opacity: 1; }
    100% { transform: scale(1); }
}
</style>
