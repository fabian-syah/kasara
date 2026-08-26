<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { balancing } from '../../api/axios';
import {
    ArrowLeft, Calendar, User, FileText, Camera, CreditCard,
    Plus, Trash2, Check, Loader2, AlertTriangle, X, Lock,
    Building2, Scale, ChevronDown, Search, Eye, EyeOff
} from 'lucide-vue-next';

const route = useRoute();
const router = useRouter();

// Branch info from query params
const branchId = computed(() => route.query.branch_id);
const branchName = computed(() => route.query.branch_name || 'Cabang');

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
const form = ref({
    reporting_date: getBalancingDate(),
    customer_name: '',
    balancing_description: '',
    balancing_cs_user_id: null,
    notes: '',
    proof_image: null,
});

// Payment methods (split payments supporting negative values)
const paymentEntries = ref([
    { payment_method_id: null, amount: '' }
]);

// Data from API
const csUsers = ref([]);
const customers = ref([]);
const paymentMethods = ref([]);
const loadingData = ref(true);
const submitting = ref(false);
const submitSuccess = ref(false);
const submitResult = ref(null);

// Customer search
const customerSearch = ref('');
const showCustomerDropdown = ref(false);
const selectedCustomerData = ref(null); // Track selected customer details
const filteredCustomers = computed(() => {
    if (!customerSearch.value) return customers.value.slice(0, 20);
    const q = customerSearch.value.toLowerCase();
    return customers.value.filter(c => c.name && c.name.toLowerCase().includes(q)).slice(0, 20);
});

function selectCustomer(customerObj) {
    customerSearch.value = customerObj.name;
    form.value.customer_name = customerObj.name;
    selectedCustomerData.value = customerObj;
    showCustomerDropdown.value = false;
}

// CS search
const csSearch = ref('');
const showCsDropdown = ref(false);
const filteredCsUsers = computed(() => {
    if (!csSearch.value) return csUsers.value;
    const q = csSearch.value.toLowerCase();
    return csUsers.value.filter(u =>
        u.name.toLowerCase().includes(q) ||
        (u.username && u.username.toLowerCase().includes(q))
    );
});

// Image preview
const imagePreview = ref(null);
const fileInputRef = ref(null);

// Password verification modal
const showPasswordModal = ref(false);
const passwordInput = ref('');
const passwordError = ref('');
const showPassword = ref(false);

// Validation
const errors = ref({});

// Computed: total selling price (sum of all payment entries)
const totalAmount = computed(() => {
    return paymentEntries.value.reduce((sum, entry) => {
        const val = parseFloat(String(entry.amount).replace(/[^0-9.\-]/g, '')) || 0;
        return sum + val;
    }, 0);
});

// Format currency
function formatCurrency(value) {
    if (!value && value !== 0) return '';
    const num = typeof value === 'string' ? parseFloat(value.replace(/[^0-9.\-]/g, '')) : value;
    if (isNaN(num)) return '';
    const isNegative = num < 0;
    const formatted = Math.abs(num).toLocaleString('id-ID');
    return isNegative ? `-Rp ${formatted}` : `Rp ${formatted}`;
}

// Add payment method entry
function addPaymentEntry() {
    paymentEntries.value.push({ payment_method_id: null, amount: '' });
}

// Remove payment method entry
function removePaymentEntry(index) {
    if (paymentEntries.value.length > 1) {
        paymentEntries.value.splice(index, 1);
    }
}

// Get payment method name by id
function getPaymentMethodName(id) {
    const method = paymentMethods.value.find(m => m.id === id);
    return method ? method.name : 'Pilih...';
}

// Handle image upload
function triggerFileInput() {
    fileInputRef.value?.click();
}

function handleImageChange(event) {
    const file = event.target.files[0];
    if (!file) return;

    form.value.proof_image = file;

    // Preview
    const reader = new FileReader();
    reader.onload = (e) => {
        imagePreview.value = e.target.result;
    };
    reader.readAsDataURL(file);
}

function removeImage() {
    form.value.proof_image = null;
    imagePreview.value = null;
    if (fileInputRef.value) fileInputRef.value.value = '';
}

// Select CS user
function selectCsUser(user) {
    form.value.balancing_cs_user_id = user.id;
    csSearch.value = user.name;
    showCsDropdown.value = false;
}

// Validate form
function validateForm() {
    const errs = {};

    if (!form.value.reporting_date) errs.reporting_date = 'Tanggal wajib diisi';
    if (!form.value.notes) errs.notes = 'Keterangan / Notes wajib diisi';
    if (!form.value.proof_image) errs.proof_image = 'Foto bukti wajib diupload';

    // Validate payment entries
    const validPayments = paymentEntries.value.filter(p => p.payment_method_id && p.amount);
    if (validPayments.length === 0) {
        errs.payments = 'Minimal satu metode pembayaran harus diisi';
    }

    errors.value = errs;
    return Object.keys(errs).length === 0;
}

// Open password modal
const isClickLocked = ref(false);
function handleSubmit() {
    if (isClickLocked.value) return;
    isClickLocked.value = true;
    setTimeout(() => { isClickLocked.value = false; }, 1000);

    if (!validateForm()) return;
    showPasswordModal.value = true;
    passwordInput.value = '';
    passwordError.value = '';
}

// Final submit with password
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
        formData.append('date', form.value.reporting_date);
        formData.append('customer_name', form.value.customer_name || '');
        formData.append('balancing_description', form.value.balancing_description || '');
        if (form.value.balancing_cs_user_id) {
            formData.append('balancing_cs_user_id', form.value.balancing_cs_user_id);
        }
        formData.append('notes', form.value.notes);
        formData.append('selling_price', totalAmount.value);
        formData.append('password', passwordInput.value);

        // Build split_payments
        const validPayments = paymentEntries.value
            .filter(p => p.payment_method_id && p.amount)
            .map(p => ({
                payment_method_id: p.payment_method_id,
                amount: parseFloat(String(p.amount).replace(/[^0-9.\-]/g, '')) || 0,
            }));
        formData.append('split_payments', JSON.stringify(validPayments));

        // Proof image
        if (form.value.proof_image) {
            formData.append('proof_image', form.value.proof_image);
        }

        const { data } = await balancing.storePaymentMethod(formData);

        submitSuccess.value = true;
        submitResult.value = data.data;
        showPasswordModal.value = false;

    } catch (e) {
        const resp = e.response?.data;
        if (resp?.errors?.password) {
            passwordError.value = resp.errors.password[0];
        } else {
            passwordError.value = resp?.message || 'Terjadi kesalahan saat menyimpan.';
        }
    } finally {
        submitting.value = false;
    }
}

// Fetch initial data
async function fetchData() {
    loadingData.value = true;
    try {
        const [usersRes, customersRes, methodsRes] = await Promise.all([
            balancing.branchUsers(branchId.value),
            balancing.customers(branchId.value, form.value.reporting_date),
            balancing.paymentMethods(branchId.value),
        ]);

        csUsers.value = usersRes.data.data || [];
        customers.value = customersRes.data.data || [];
        paymentMethods.value = methodsRes.data.data || [];
    } catch (e) {
        console.error('Failed to fetch form data:', e);
    } finally {
        loadingData.value = false;
    }
}

watch(() => form.value.reporting_date, async (newDate) => {
    if (newDate) {
        try {
            const res = await balancing.customers(branchId.value, newDate);
            customers.value = res.data.data || [];
            form.value.customer_name = ''; // Reset customer when date changes
            customerSearch.value = '';
            selectedCustomerData.value = null;
        } catch (e) {
            console.error('Failed to fetch customers for date:', e);
        }
    } else {
        customers.value = [];
        form.value.customer_name = '';
        customerSearch.value = '';
        selectedCustomerData.value = null;
    }
});

function goBack() {
    router.push('/balancing/payment-method');
}

function goHome() {
    router.push('/balancing');
}

function createAnother() {
    submitSuccess.value = false;
    submitResult.value = null;
    form.value = {
        reporting_date: '',
        customer_name: '',
        balancing_description: '',
        balancing_cs_user_id: null,
        notes: '',
        proof_image: null,
    };
    paymentEntries.value = [{ payment_method_id: null, amount: '' }];
    imagePreview.value = null;
    customerSearch.value = '';
    csSearch.value = '';
    errors.value = {};
}

onMounted(() => {
    if (!branchId.value) {
        router.push('/balancing/payment-method');
        return;
    }
    fetchData();
});

// Close dropdowns on outside click
function handleOutsideClick(e) {
    if (!e.target.closest('.customer-dropdown-container')) {
        showCustomerDropdown.value = false;
    }
    if (!e.target.closest('.cs-dropdown-container')) {
        showCsDropdown.value = false;
    }
}
</script>

<template>
    <div class="max-w-3xl mx-auto" @click="handleOutsideClick">
        <!-- Success State -->
        <div v-if="submitSuccess" class="text-center py-12">
            <div class="w-20 h-20 mx-auto rounded-full bg-gradient-to-br from-emerald-400 to-teal-500 flex items-center justify-center mb-6 shadow-xl shadow-emerald-500/20 animate-bounce-once">
                <Check :size="36" class="text-white" />
            </div>
            <h2 class="text-2xl font-bold text-neutral-900 dark:text-white mb-2">Balancing Berhasil!</h2>
            <p class="text-neutral-500 dark:text-neutral-400 mb-2">Data balancing metode pembayaran telah tersimpan.</p>

            <div v-if="submitResult" class="inline-flex flex-col items-center gap-1 mt-3 mb-8 p-4 rounded-2xl bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700">
                <span class="text-xs text-neutral-500 dark:text-neutral-400">No. Receipt</span>
                <span class="text-lg font-bold text-violet-600 dark:text-violet-400 font-mono">{{ submitResult.receipt_id }}</span>
                <span class="text-xs text-neutral-500 dark:text-neutral-400 mt-1">
                    Cabang: {{ submitResult.branch?.name || branchName }}
                </span>
                <span class="text-xs text-neutral-500 dark:text-neutral-400">
                    Tanggal Masuk Omset: {{ submitResult.reporting_date }}
                </span>
                <span class="text-sm font-semibold mt-1" :class="submitResult.selling_price < 0 ? 'text-red-500' : 'text-emerald-600 dark:text-emerald-400'">
                    {{ formatCurrency(submitResult.selling_price) }}
                </span>
            </div>

            <div class="flex items-center justify-center gap-3">
                <button @click="createAnother"
                    class="px-6 py-3 rounded-xl bg-violet-600 hover:bg-violet-700 text-white font-medium text-sm transition-colors">
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
            <div class="mb-8">
                <button @click="goBack"
                    class="flex items-center gap-2 text-sm text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 transition-colors mb-4 group">
                    <ArrowLeft :size="16" class="transition-transform group-hover:-translate-x-1" />
                    <span>Pilih Cabang Lain</span>
                </button>

                <div class="flex items-center gap-3 mb-3">
                    <div class="p-2.5 rounded-2xl bg-gradient-to-br from-violet-500 to-indigo-600 text-white shadow-lg shadow-violet-500/20">
                        <Scale :size="24" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-neutral-900 dark:text-white">Balancing Metode Pembayaran</h1>
                        <div class="flex items-center gap-2 mt-1">
                            <Building2 :size="14" class="text-violet-500" />
                            <span class="text-sm font-medium text-violet-600 dark:text-violet-400">{{ branchName }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Loading -->
            <div v-if="loadingData" class="flex items-center justify-center py-20">
                <Loader2 :size="32" class="animate-spin text-violet-500" />
            </div>

            <!-- Form -->
            <form v-else @submit.prevent="handleSubmit" class="space-y-6">

                <!-- Date Picker -->
                <div>
                    <label class="flex items-center gap-2 text-sm font-semibold text-neutral-700 dark:text-neutral-300 mb-2">
                        <Calendar :size="16" class="text-violet-500" />
                        Tanggal Balancing (Masuk Omset) *
                    </label>
                    <input
                        v-model="form.reporting_date"
                        type="date"
                        class="w-full px-4 py-3 rounded-xl border text-sm transition-all focus:outline-none focus:ring-2 focus:ring-violet-500/40 focus:border-violet-500 bg-white dark:bg-neutral-800/50 text-neutral-900 dark:text-white [color-scheme:light] dark:[color-scheme:dark]"
                        :class="errors.reporting_date ? 'border-red-300 dark:border-red-700' : 'border-neutral-200 dark:border-neutral-700'"
                    />
                    <p v-if="errors.reporting_date" class="mt-1.5 text-xs text-red-500">{{ errors.reporting_date }}</p>
                    <p class="mt-1.5 text-xs text-neutral-400">Omset akan masuk di tanggal ini, bukan tanggal hari ini.</p>
                </div>

                <!-- Customer Name -->
                <div class="customer-dropdown-container relative">
                    <label class="flex items-center gap-2 text-sm font-semibold text-neutral-700 dark:text-neutral-300 mb-2">
                        <User :size="16" class="text-violet-500" />
                        Nama Customer
                    </label>
                    <div class="relative">
                        <input
                            v-model="customerSearch"
                            :disabled="!form.reporting_date"
                            @focus="customerSearch.length > 0 && form.reporting_date ? showCustomerDropdown = true : null"
                            @input="form.customer_name = customerSearch; showCustomerDropdown = true; selectedCustomerData = null"
                            type="text"
                            placeholder="Ketik atau pilih nama customer..."
                            class="w-full px-4 py-3 pr-10 rounded-xl border border-neutral-200 dark:border-neutral-700 text-sm bg-white dark:bg-neutral-800/50 text-neutral-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-violet-500/40 focus:border-violet-500 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                        />
                        <button type="button" :disabled="!form.reporting_date" @click.stop="form.reporting_date ? showCustomerDropdown = !showCustomerDropdown : null" class="absolute right-2 top-1/2 -translate-y-1/2 p-2 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed">
                            <ChevronDown :size="16" class="transition-transform" :class="showCustomerDropdown ? 'rotate-180' : ''" />
                        </button>
                    </div>
                    <!-- Dropdown -->
                    <div v-if="showCustomerDropdown && filteredCustomers.length"
                        class="absolute z-50 mt-1 w-full max-h-64 overflow-y-auto rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 shadow-xl">
                        <button
                            v-for="(cust, idx) in filteredCustomers"
                            :key="idx"
                            @click.prevent="selectCustomer(cust)"
                            class="w-full text-left px-4 py-3 border-b last:border-b-0 border-neutral-100 dark:border-neutral-700/50 hover:bg-violet-50 dark:hover:bg-violet-900/20 transition-colors flex flex-col gap-1"
                        >
                            <span class="text-sm font-medium text-neutral-900 dark:text-white">{{ cust.name }}</span>
                            <span v-if="cust.transactions && cust.transactions.length" class="text-xs text-neutral-500 dark:text-neutral-400">
                                {{ cust.transactions.length }} transaksi pada tanggal ini
                            </span>
                        </button>
                    </div>

                    <!-- Selected Customer Preview -->
                    <div v-if="selectedCustomerData && selectedCustomerData.transactions && selectedCustomerData.transactions.length > 0" class="mt-3 p-4 bg-violet-50/50 dark:bg-violet-900/10 border border-violet-100 dark:border-violet-900/30 rounded-xl">
                        <h4 class="text-xs font-semibold text-violet-600 dark:text-violet-400 uppercase tracking-wider mb-3">Detail Transaksi Customer</h4>
                        <div class="space-y-3">
                            <div v-for="(trx, idx) in selectedCustomerData.transactions" :key="idx" class="flex flex-col gap-1.5 p-3 bg-white dark:bg-neutral-800 rounded-lg shadow-sm border border-neutral-100 dark:border-neutral-700/50">
                                <div class="flex justify-between items-start">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-semibold px-2 py-0.5 bg-neutral-100 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-300 rounded uppercase">{{ trx.category }}</span>
                                        <span class="text-xs text-neutral-500">{{ trx.time }}</span>
                                    </div>
                                    <span class="text-sm font-bold text-neutral-900 dark:text-white">{{ formatCurrency(trx.total_amount) }}</span>
                                </div>
                                <div v-if="trx.receipt_id" class="text-xs text-neutral-500 font-mono">{{ trx.receipt_id }}</div>
                                <div v-if="trx.items && trx.items.length" class="flex flex-wrap gap-1 mt-1">
                                    <span v-for="(item, i) in trx.items" :key="i" class="text-xs bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-300 px-2 py-0.5 rounded-full">
                                        {{ item }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Keterangan Balancing -->
                <div>
                    <label class="flex items-center gap-2 text-sm font-semibold text-neutral-700 dark:text-neutral-300 mb-2">
                        <FileText :size="16" class="text-violet-500" />
                        Keterangan Balancing
                    </label>
                    <input
                        v-model="form.balancing_description"
                        type="text"
                        placeholder="Contoh: Koreksi pembayaran tunai ke transfer..."
                        class="w-full px-4 py-3 rounded-xl border border-neutral-200 dark:border-neutral-700 text-sm bg-white dark:bg-neutral-800/50 text-neutral-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-violet-500/40 focus:border-violet-500 transition-all"
                    />
                </div>

                <!-- CS User (Dropdown) -->
                <div class="cs-dropdown-container relative">
                    <label class="flex items-center gap-2 text-sm font-semibold text-neutral-700 dark:text-neutral-300 mb-2">
                        <User :size="16" class="text-violet-500" />
                        Nama Customer Service
                    </label>
                    <div class="relative">
                        <input
                            v-model="csSearch"
                            @focus="showCsDropdown = true"
                            type="text"
                            placeholder="Cari CS yang menangani..."
                            class="w-full px-4 py-3 pr-10 rounded-xl border border-neutral-200 dark:border-neutral-700 text-sm bg-white dark:bg-neutral-800/50 text-neutral-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-violet-500/40 focus:border-violet-500 transition-all"
                        />
                        <button type="button" @click.stop="showCsDropdown = !showCsDropdown" class="absolute right-2 top-1/2 -translate-y-1/2 p-2 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 focus:outline-none">
                            <ChevronDown :size="16" class="transition-transform" :class="showCsDropdown ? 'rotate-180' : ''" />
                        </button>
                    </div>
                    <div v-if="showCsDropdown && filteredCsUsers.length"
                        class="absolute z-50 mt-1 w-full max-h-48 overflow-y-auto rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 shadow-xl">
                        <button
                            v-for="user in filteredCsUsers"
                            :key="user.id"
                            @click.prevent="selectCsUser(user)"
                            class="w-full text-left px-4 py-2.5 text-sm text-neutral-700 dark:text-neutral-300 hover:bg-violet-50 dark:hover:bg-violet-900/20 transition-colors"
                        >
                            <span class="font-medium">{{ user.name }}</span>
                            <span v-if="user.username" class="text-neutral-400 ml-2 text-xs">({{ user.username }})</span>
                        </button>
                    </div>
                    <p v-if="csUsers.length === 0 && !loadingData" class="mt-1.5 text-xs text-neutral-400">Tidak ada CS ditemukan di cabang ini.</p>
                </div>

                <!-- Notes -->
                <div>
                    <label class="flex items-center gap-2 text-sm font-semibold text-neutral-700 dark:text-neutral-300 mb-2">
                        <FileText :size="16" class="text-violet-500" />
                        Keterangan / Notes *
                    </label>
                    <textarea
                        v-model="form.notes"
                        rows="3"
                        placeholder="Masukkan catatan detail tentang balancing ini..."
                        class="w-full px-4 py-3 rounded-xl border text-sm transition-all focus:outline-none focus:ring-2 focus:ring-violet-500/40 focus:border-violet-500 bg-white dark:bg-neutral-800/50 text-neutral-900 dark:text-white resize-none"
                        :class="errors.notes ? 'border-red-300 dark:border-red-700' : 'border-neutral-200 dark:border-neutral-700'"
                    ></textarea>
                    <p v-if="errors.notes" class="mt-1.5 text-xs text-red-500">{{ errors.notes }}</p>
                </div>

                <!-- Foto Bukti -->
                <div>
                    <label class="flex items-center gap-2 text-sm font-semibold text-neutral-700 dark:text-neutral-300 mb-2">
                        <Camera :size="16" class="text-violet-500" />
                        Foto Bukti *
                    </label>
                    <input ref="fileInputRef" type="file" accept="image/*" class="hidden" @change="handleImageChange" />

                    <div v-if="!imagePreview"
                        @click="triggerFileInput"
                        class="w-full border-2 border-dashed rounded-2xl p-8 text-center cursor-pointer transition-all hover:border-violet-400 hover:bg-violet-50/50 dark:hover:bg-violet-950/20"
                        :class="errors.proof_image ? 'border-red-300 dark:border-red-700' : 'border-neutral-200 dark:border-neutral-700'"
                    >
                        <Camera :size="32" class="mx-auto text-neutral-300 dark:text-neutral-600 mb-3" />
                        <p class="text-sm text-neutral-500 dark:text-neutral-400">Klik untuk upload foto bukti</p>
                        <p class="text-xs text-neutral-400 mt-1">PNG, JPG, JPEG (Maks. 20MB)</p>
                    </div>

                    <div v-else class="relative rounded-2xl overflow-hidden border border-neutral-200 dark:border-neutral-700">
                        <img :src="imagePreview" class="w-full max-h-64 object-contain bg-neutral-50 dark:bg-neutral-800" />
                        <button @click="removeImage" type="button"
                            class="absolute top-3 right-3 p-1.5 rounded-full bg-red-500 hover:bg-red-600 text-white shadow-lg transition-colors">
                            <X :size="14" />
                        </button>
                    </div>
                    <p v-if="errors.proof_image" class="mt-1.5 text-xs text-red-500">{{ errors.proof_image }}</p>
                </div>

                <!-- Payment Methods Section -->
                <div>
                    <label class="flex items-center gap-2 text-sm font-semibold text-neutral-700 dark:text-neutral-300 mb-3">
                        <CreditCard :size="16" class="text-violet-500" />
                        Metode Pembayaran *
                    </label>
                    <p class="text-xs text-neutral-400 mb-4 -mt-1">Masukkan nilai minus (-) untuk mengurangi omset dan merevisi metode pembayaran.</p>

                    <div class="space-y-3">
                        <div v-for="(entry, index) in paymentEntries" :key="index"
                            class="flex items-start gap-3 p-4 rounded-xl bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-100 dark:border-neutral-700/50">

                            <!-- Method Select -->
                            <div class="flex-1 min-w-0">
                                <label class="text-xs text-neutral-500 dark:text-neutral-400 mb-1 block">Metode</label>
                                <select
                                    v-model="entry.payment_method_id"
                                    class="w-full px-3 py-2.5 rounded-lg border border-neutral-200 dark:border-neutral-600 text-sm bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-violet-500/40 focus:border-violet-500 transition-all"
                                >
                                    <option :value="null" disabled>Pilih metode...</option>
                                    <option v-for="method in paymentMethods" :key="method.id" :value="method.id">
                                        {{ method.name }}
                                    </option>
                                </select>
                            </div>

                            <!-- Amount Input (supports negative) -->
                            <div class="w-40 sm:w-48">
                                <label class="text-xs text-neutral-500 dark:text-neutral-400 mb-1 block">Jumlah (Rp)</label>
                                <input
                                    v-model="entry.amount"
                                    type="text"
                                    placeholder="-500.000"
                                    class="w-full px-3 py-2.5 rounded-lg border border-neutral-200 dark:border-neutral-600 text-sm bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-violet-500/40 focus:border-violet-500 transition-all font-mono"
                                />
                            </div>

                            <!-- Remove Button -->
                            <button v-if="paymentEntries.length > 1" @click="removePaymentEntry(index)" type="button"
                                class="mt-6 p-2 rounded-lg text-red-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors shrink-0">
                                <Trash2 :size="16" />
                            </button>
                        </div>
                    </div>

                    <!-- Add Payment Method -->
                    <button @click="addPaymentEntry" type="button"
                        class="mt-3 flex items-center gap-2 px-4 py-2.5 rounded-xl border border-dashed border-violet-300 dark:border-violet-700 text-violet-600 dark:text-violet-400 text-sm font-medium hover:bg-violet-50 dark:hover:bg-violet-950/20 transition-colors w-full justify-center">
                        <Plus :size="16" />
                        Tambah Metode Pembayaran
                    </button>

                    <p v-if="errors.payments" class="mt-1.5 text-xs text-red-500">{{ errors.payments }}</p>

                    <!-- Total -->
                    <div class="mt-4 p-4 rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800/30">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-neutral-600 dark:text-neutral-400">Total Nilai Balancing</span>
                            <span class="text-xl font-bold font-mono"
                                :class="totalAmount < 0 ? 'text-red-500' : totalAmount > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-neutral-500'">
                                {{ formatCurrency(totalAmount) }}
                            </span>
                        </div>
                        <p v-if="totalAmount < 0" class="text-xs text-amber-500 mt-2 flex items-center gap-1">
                            <AlertTriangle :size="12" />
                            Nilai minus akan mengurangi omset di tanggal yang dipilih.
                        </p>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="pt-4 border-t border-neutral-100 dark:border-neutral-800">
                    <button type="submit" :disabled="isClickLocked"
                        class="w-full py-4 rounded-2xl bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-700 hover:to-indigo-700 text-white font-semibold text-sm shadow-lg shadow-violet-500/20 hover:shadow-xl hover:shadow-violet-500/30 transition-all duration-300 flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                        <Loader2 v-if="isClickLocked" class="animate-spin" :size="18" />
                        <Check v-else :size="18" />
                        <span>{{ isClickLocked ? 'Memproses...' : 'Selesaikan Transaksi' }}</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Password Verification Modal -->
        <Teleport to="body">
            <div v-if="showPasswordModal" class="fixed inset-0 z-[99999] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showPasswordModal = false"></div>
                <div class="relative w-full max-w-sm bg-white dark:bg-neutral-800 rounded-3xl shadow-2xl p-6 transform transition-all">
                    <!-- Lock Icon -->
                    <div class="flex justify-center mb-5">
                        <div class="w-16 h-16 rounded-full bg-gradient-to-br from-violet-500 to-indigo-600 flex items-center justify-center shadow-lg shadow-violet-500/20">
                            <Lock :size="28" class="text-white" />
                        </div>
                    </div>

                    <h3 class="text-lg font-bold text-center text-neutral-900 dark:text-white mb-1">Verifikasi Password</h3>
                    <p class="text-sm text-center text-neutral-500 dark:text-neutral-400 mb-5">Masukkan password Anda untuk mengkonfirmasi</p>

                    <div class="relative">
                        <input
                            v-model="passwordInput"
                            :type="showPassword ? 'text' : 'password'"
                            placeholder="Masukkan password..."
                            @keyup.enter="confirmSubmit"
                            class="w-full px-4 py-3 pr-12 rounded-xl border border-neutral-200 dark:border-neutral-600 text-sm bg-neutral-50 dark:bg-neutral-700 text-neutral-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-violet-500/40 focus:border-violet-500 transition-all"
                            :class="passwordError ? '!border-red-400' : ''"
                        />
                        <button type="button" @click="showPassword = !showPassword"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 focus:outline-none transition-colors">
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
                            class="flex-1 py-3 rounded-xl bg-gradient-to-r from-violet-600 to-indigo-600 text-white text-sm font-medium transition-all hover:from-violet-700 hover:to-indigo-700 disabled:opacity-60 flex items-center justify-center gap-2">
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
