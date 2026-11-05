<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import {
    Smartphone, Package, Check, X, CheckCircle2, XCircle, ClipboardCheck,
    Camera, Upload, Plus, Trash2, AlertTriangle, Building2,
    User, Lock, ShieldCheck, History, ArrowLeft, ChevronDown,
    RefreshCw, Search, Eye
} from 'lucide-vue-next';
import axios from '../../api/axios';
import { useToast } from '../../composables/useToast';
import { useAuthStore } from '../../store/auth';

const router = useRouter();
const toast = useToast();
const authStore = useAuthStore();

// Loading states
const loadingBranches = ref(false);
const loadingCs = ref(false);
const loadingItems = ref(false);
const submitting = ref(false);

// Step 1: Branch & CS Selection
const branches = ref([]);
const selectedBranchId = ref(null);
const csUsers = ref([]);
const selectedCsUserId = ref(null);

// Password verification for store accounts
const showPasswordModal = ref(false);
const csPassword = ref('');
const passwordVerified = ref(false);
const verifyingPassword = ref(false);

// Step 2: Configuration
const categoryType = ref('hp'); // 'hp' | 'non_hp'
const method = ref('by_unit'); // 'by_unit' | 'by_imei'
const selectedBrand = ref('');
const selectedProductName = ref('');

// Step 3: Items Data
const availableBrands = ref([]);
const availableTypes = ref([]);
const pendingItems = ref([]);
const totalAvailableUnits = ref(0);

// Step 4: Barang Lebih (Excess Items)
const excessItems = ref([]);
const showExcessModal = ref(false);
const newExcess = ref({
    brand: '',
    product_name: '',
    storage: '',
    imei: '',
    quantity: 1,
    notes: ''
});

// Step 5: Foto SO
const photoFiles = ref([]);
const photoPreviews = ref([]);

// Step 6: Catatan Umum
const generalNotes = ref('');

// User Role Helpers
const userRole = computed(() => (authStore.userRole || '').toLowerCase());
const isPrivilegedRole = computed(() => {
    const privileged = ['super_admin', 'leader', 'audit', 'owner', 'analist'];
    return privileged.some(r => userRole.value.includes(r));
});
const isStoreAccount = computed(() => {
    return userRole.value === 'toko_offline' || (!isPrivilegedRole.value && authStore.user?.id !== selectedCsUserId.value);
});

// Fetch branches
const fetchBranches = async () => {
    loadingBranches.value = true;
    try {
        const res = await axios.get('/inventory/opname/branches');
        branches.value = res.data.data || [];

        if (branches.value.length > 0) {
            // Default select first or user's branch
            const userBranchId = authStore.user?.branch_id;
            const match = branches.value.find(b => Number(b.id) === Number(userBranchId));
            selectedBranchId.value = match ? match.id : branches.value[0].id;
            await fetchCsUsers();
        }
    } catch (err) {
        console.error('Error fetching branches for opname:', err);
        toast.error('Gagal memuat daftar cabang.');
    } finally {
        loadingBranches.value = false;
    }
};

// Fetch CS accounts for selected branch
const fetchCsUsers = async () => {
    if (!selectedBranchId.value) return;
    loadingCs.value = true;
    try {
        const res = await axios.get(`/inventory/opname/branch-cs/${selectedBranchId.value}`);
        csUsers.value = res.data.data || [];

        if (csUsers.value.length > 0) {
            // If logged-in user is one of the CS, auto select
            const myId = authStore.user?.id;
            const myCs = csUsers.value.find(u => Number(u.id) === Number(myId));
            if (myCs) {
                selectedCsUserId.value = myCs.id;
                passwordVerified.value = true;
            } else {
                selectedCsUserId.value = csUsers.value[0].id;
                passwordVerified.value = false;
            }
        } else {
            selectedCsUserId.value = null;
        }

        await fetchPendingItems();
    } catch (err) {
        console.error('Error fetching CS users:', err);
    } finally {
        loadingCs.value = false;
    }
};

// Fetch items to be audited
const fetchPendingItems = async () => {
    if (!selectedBranchId.value) return;
    loadingItems.value = true;

    try {
        const params = {
            branch_id: selectedBranchId.value,
            category_type: categoryType.value,
            method: method.value,
        };

        if (selectedBrand.value) {
            params.brand = selectedBrand.value;
        }
        if (selectedProductName.value && method.value === 'by_imei') {
            params.product_name = selectedProductName.value;
        }

        const res = await axios.get('/inventory/opname/pending-items', { params });
        availableBrands.value = res.data.available_brands || [];
        totalAvailableUnits.value = res.data.total_available_units || 0;

        if (method.value === 'by_imei') {
            availableTypes.value = res.data.available_types || [];
            pendingItems.value = (res.data.items || []).map(item => ({
                ...item,
                is_present: item.is_present !== undefined ? item.is_present : true,
                notes: item.notes || ''
            }));
        } else {
            pendingItems.value = (res.data.items || []).map(item => ({
                ...item,
                physical_qty: Number(item.physical_qty) || Number(item.system_qty) || 0,
                difference: (Number(item.physical_qty) || Number(item.system_qty) || 0) - (Number(item.system_qty) || 0),
                notes: item.notes || ''
            }));
        }
    } catch (err) {
        console.error('Error fetching pending items for opname:', err);
        toast.error('Gagal mengambil data stok sistem.');
    } finally {
        loadingItems.value = false;
    }
};

// Watchers
watch(selectedBranchId, () => {
    selectedBrand.value = '';
    selectedProductName.value = '';
    fetchCsUsers();
});

watch(categoryType, (newVal) => {
    selectedBrand.value = '';
    selectedProductName.value = '';
    if (newVal === 'non_hp') {
        method.value = 'by_unit';
    }
    fetchPendingItems();
});

watch(method, () => {
    selectedProductName.value = '';
    fetchPendingItems();
});

watch(selectedBrand, () => {
    selectedProductName.value = '';
    fetchPendingItems();
});

watch(selectedProductName, () => {
    if (method.value === 'by_imei') {
        fetchPendingItems();
    }
});

// By Unit calculation
const onPhysicalQtyChange = (item) => {
    const sys = Number(item.system_qty) || 0;
    const phys = Number(item.physical_qty) || 0;
    item.difference = phys - sys;
};

const fillAllMatchingSystem = () => {
    if (method.value !== 'by_unit') return;
    pendingItems.value.forEach(item => {
        item.physical_qty = item.system_qty;
        item.difference = 0;
    });
    toast.success('Jumlah fisik disesuaikan dengan stok sistem.');
};

// By IMEI Quick Toggles
const markAllPresent = (status) => {
    if (method.value !== 'by_imei') return;
    pendingItems.value.forEach(item => {
        item.is_present = status;
    });
    toast.info(status ? 'Semua unit ditandai Ada (Cocok)' : 'Semua unit ditandai Tidak Ada');
};

// Summary Counters
const summaryStats = computed(() => {
    let sys = 0;
    let phys = 0;
    let missing = 0;

    if (method.value === 'by_imei') {
        sys = pendingItems.value.length;
        pendingItems.value.forEach(it => {
            if (it.is_present) phys++;
            else missing++;
        });
    } else {
        pendingItems.value.forEach(it => {
            const s = Number(it.system_qty) || 0;
            const p = Number(it.physical_qty) || 0;
            sys += s;
            phys += p;
            if (p < s) missing += (s - p);
        });
    }

    const excess = excessItems.value.reduce((sum, e) => sum + (Number(e.quantity) || 1), 0);

    return {
        totalSystem: sys,
        totalPhysical: phys,
        totalMissing: missing,
        totalExcess: excess,
        hasDifference: (missing > 0 || excess > 0 || (phys !== sys && method.value === 'by_unit'))
    };
});

// Excess Items Management
const addExcessItem = () => {
    if (!newExcess.value.product_name) {
        toast.warning('Nama / Tipe barang lebih wajib diisi.');
        return;
    }

    excessItems.value.push({
        ...newExcess.value,
        id: Date.now()
    });

    newExcess.value = {
        brand: selectedBrand.value || '',
        product_name: '',
        storage: '',
        imei: '',
        quantity: 1,
        notes: ''
    };
    showExcessModal.value = false;
    toast.success('Barang lebih berhasil dicatat.');
};

const removeExcessItem = (index) => {
    excessItems.value.splice(index, 1);
};

// Photo Upload Handling
const handlePhotoSelect = (e) => {
    const files = Array.from(e.target.files || []);
    if (!files.length) return;

    files.forEach(file => {
        if (!file.type.startsWith('image/')) {
            toast.warning('Hanya file gambar yang diperbolehkan.');
            return;
        }

        const reader = new FileReader();
        reader.onload = (event) => {
            photoPreviews.value.push(event.target.result);
        };
        reader.readAsDataURL(file);
    });
};

const removePhoto = (index) => {
    photoPreviews.value.splice(index, 1);
};

// Verification & Submission
const handleVerifyAndSubmit = async () => {
    if (!selectedBranchId.value) {
        toast.warning('Pilih cabang terlebih dahulu.');
        return;
    }
    if (!selectedCsUserId.value) {
        toast.warning('Pilih akun CS penanggung jawab SO.');
        return;
    }
    if (pendingItems.value.length === 0 && excessItems.value.length === 0) {
        toast.warning('Tidak ada barang yang diperiksa.');
        return;
    }

    // Check if store account needs password verification
    if (isStoreAccount.value && !passwordVerified.value) {
        showPasswordModal.value = true;
        return;
    }

    await submitOpname();
};

const submitWithPassword = async () => {
    if (!csPassword.value) {
        toast.warning('Masukkan password CS untuk verifikasi.');
        return;
    }

    verifyingPassword.value = true;
    try {
        const verifyRes = await axios.post('/verify-password', {
            password: csPassword.value,
            inventory_user_id: selectedCsUserId.value
        });

        if (verifyRes.data.success) {
            passwordVerified.value = true;
            showPasswordModal.value = false;
            toast.success('Verifikasi CS berhasil.');
            await submitOpname();
        } else {
            toast.error(verifyRes.data.message || 'Password CS salah.');
        }
    } catch (err) {
        toast.error(err.response?.data?.message || 'Password verifikasi CS salah.');
    } finally {
        verifyingPassword.value = false;
    }
};

const submitOpname = async () => {
    submitting.value = true;
    try {
        const payload = {
            branch_id: selectedBranchId.value,
            cs_user_id: selectedCsUserId.value,
            category_type: categoryType.value,
            method: method.value,
            brand: selectedBrand.value || null,
            product_type: selectedProductName.value || null,
            items: pendingItems.value,
            excess_items: excessItems.value,
            photos: photoPreviews.value,
            notes: generalNotes.value,
            password: csPassword.value || undefined,
        };

        const res = await axios.post('/inventory/opname/submit', payload);

        if (res.data.success) {
            toast.success('Stok Opname berhasil disimpan!');
            router.push('/inventory/opname/history');
        } else {
            toast.error(res.data.message || 'Gagal menyimpan stok opname.');
        }
    } catch (err) {
        console.error('Error submitting stock opname:', err);
        toast.error(err.response?.data?.message || 'Gagal menyimpan stok opname.');
    } finally {
        submitting.value = false;
    }
};

onMounted(() => {
    fetchBranches();
});
</script>

<template>
    <div class="space-y-6 pb-6">
        <!-- Top Navigation & Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white dark:!bg-surface-800 p-4 sm:p-6 rounded-2xl border border-gray-100 dark:border-surface-700 shadow-sm">
            <div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <button @click="router.back()" class="p-2 rounded-xl text-gray-500 hover:text-gray-900 hover:bg-gray-100 dark:hover:bg-surface-700 transition-colors">
                        <ArrowLeft :size="20" />
                    </button>
                    <h1 class="text-xl sm:text-2xl font-bold text-text-primary tracking-tight flex items-center gap-2 flex-wrap">
                        <span>Data Stok</span>
                        <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-500/30">
                            Jangan Dipakai Dulu
                        </span>
                    </h1>
                </div>
                <p class="text-text-secondary text-xs sm:text-sm mt-1 ml-10">
                    Pemeriksaan & pencatatan fisik stok toko harian
                </p>
            </div>

            <div class="flex items-center gap-2 self-stretch sm:self-auto">
                <router-link to="/inventory/opname/history"
                    class="flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl border border-gray-200 dark:border-surface-600 bg-white dark:!bg-surface-700 text-xs sm:text-sm font-semibold text-text-primary hover:border-emerald-500 hover:text-emerald-600 transition-all shadow-sm flex-1 sm:flex-initial">
                    <History :size="16" />
                    <span>History Stok Opname</span>
                </router-link>
            </div>
        </div>

        <!-- Section 1: Lokasi Cabang & Akun CS -->
        <div class="bg-white dark:!bg-surface-800 p-4 sm:p-6 rounded-2xl border border-gray-100 dark:border-surface-700 shadow-sm space-y-4">
            <h2 class="text-sm font-bold text-text-primary flex items-center gap-2">
                <Building2 :size="16" class="text-emerald-500" />
                <span>Cabang & Akun Penanggung Jawab</span>
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Dropdown Cabang -->
                <div>
                    <label class="block text-xs font-semibold text-text-secondary uppercase tracking-wider mb-1.5">
                        Cabang Fisik
                    </label>
                    <div class="relative">
                        <select v-model="selectedBranchId" :disabled="!isPrivilegedRole"
                            class="w-full appearance-none bg-white dark:!bg-surface-700 border border-gray-200 dark:border-surface-600 rounded-xl px-4 py-2.5 pr-10 text-sm font-semibold text-text-primary focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all cursor-pointer disabled:bg-gray-100 dark:disabled:bg-surface-900 disabled:cursor-not-allowed">
                            <option v-for="b in branches" :key="b.id" :value="b.id" class="bg-white text-gray-900 dark:bg-surface-800 dark:text-white">
                                {{ b.name }}
                            </option>
                        </select>
                        <ChevronDown :size="16" class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
                    </div>
                </div>

                <!-- Dropdown Akun CS -->
                <div>
                    <label class="block text-xs font-semibold text-text-secondary uppercase tracking-wider mb-1.5 flex items-center justify-between">
                        <span>Akun CS Pelaksana</span>
                        <span v-if="isStoreAccount && !passwordVerified" class="text-[11px] text-amber-600 dark:text-amber-400 font-medium flex items-center gap-1">
                            <Lock :size="12" /> Wajib Verifikasi
                        </span>
                    </label>
                    <div class="relative">
                        <select v-model="selectedCsUserId"
                            class="w-full appearance-none bg-white dark:!bg-surface-700 border border-gray-200 dark:border-surface-600 rounded-xl px-4 py-2.5 pr-10 text-sm font-semibold text-text-primary focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all cursor-pointer">
                            <option v-if="csUsers.length === 0" :value="null" class="bg-white text-gray-900 dark:bg-surface-800 dark:text-white">Tidak ada akun CS di cabang ini</option>
                            <option v-for="u in csUsers" :key="u.id" :value="u.id" class="bg-white text-gray-900 dark:bg-surface-800 dark:text-white">
                                {{ u.name }} (@{{ u.username || 'cs' }})
                            </option>
                        </select>
                        <ChevronDown :size="16" class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 2: Mode & Kategori Stok Opname -->
        <div class="bg-white dark:!bg-surface-800 p-4 sm:p-6 rounded-2xl border border-gray-100 dark:border-surface-700 shadow-sm space-y-4">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <h2 class="text-sm font-bold text-text-primary flex items-center gap-2">
                    <Smartphone :size="16" class="text-emerald-500" />
                    <span>Kategori & Metode Opname</span>
                </h2>

                <!-- Total Sisa Belum SO -->
                <div v-if="loadingItems" class="text-xs font-medium text-gray-400 flex items-center gap-1.5 bg-gray-50 dark:bg-surface-700/60 px-3 py-1.5 rounded-lg border border-gray-200/60 dark:border-surface-600">
                    <RefreshCw :size="13" class="animate-spin text-emerald-500" />
                    <span>Memeriksa stok...</span>
                </div>
                <div v-else-if="totalAvailableUnits === 0 && !selectedBrand && !selectedProductName" class="text-xs font-bold text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/50 px-3 py-1.5 rounded-lg border border-emerald-200 dark:border-emerald-800 flex items-center gap-1.5">
                    <CheckCircle2 :size="14" class="text-emerald-500" />
                    <span>Semua unit sudah di-SO hari ini ✅</span>
                </div>
                <div v-else-if="totalAvailableUnits === 0" class="text-xs font-bold text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/50 px-3 py-1.5 rounded-lg border border-emerald-200 dark:border-emerald-800 flex items-center gap-1.5">
                    <CheckCircle2 :size="14" class="text-emerald-500" />
                    <span>Filter ini sudah di-SO ✅</span>
                </div>
                <div v-else class="text-xs font-medium text-text-secondary bg-amber-50 dark:bg-amber-950/40 px-3 py-1.5 rounded-lg border border-amber-200 dark:border-amber-800/60">
                    Belum di-SO hari ini: <span class="font-bold text-amber-700 dark:text-amber-300">{{ totalAvailableUnits }} unit</span>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Toggle HP / Non-HP -->
                <div class="flex items-center p-1 bg-gray-100 dark:bg-surface-700 rounded-xl border border-gray-200 dark:border-surface-600">
                    <button type="button" @click="categoryType = 'hp'"
                        class="flex-1 flex items-center justify-center gap-2 py-2 text-xs sm:text-sm font-bold rounded-lg transition-all"
                        :class="categoryType === 'hp' ? 'bg-white dark:bg-surface-800 text-emerald-600 dark:text-emerald-400 shadow-sm' : 'text-text-secondary hover:text-text-primary'">
                        <Smartphone :size="16" />
                        <span>HP (IMEI)</span>
                    </button>
                    <button type="button" @click="categoryType = 'non_hp'"
                        class="flex-1 flex items-center justify-center gap-2 py-2 text-xs sm:text-sm font-bold rounded-lg transition-all"
                        :class="categoryType === 'non_hp' ? 'bg-white dark:bg-surface-800 text-emerald-600 dark:text-emerald-400 shadow-sm' : 'text-text-secondary hover:text-text-primary'">
                        <Package :size="16" />
                        <span>Non-HP / Aksesoris</span>
                    </button>
                </div>

                <!-- Toggle By Unit / By IMEI (Khusus HP) -->
                <div v-if="categoryType === 'hp'" class="flex items-center p-1 bg-gray-100 dark:bg-surface-700 rounded-xl border border-gray-200 dark:border-surface-600">
                    <button type="button" @click="method = 'by_unit'"
                        class="flex-1 flex items-center justify-center gap-1.5 py-2 text-xs sm:text-sm font-bold rounded-lg transition-all"
                        :class="method === 'by_unit' ? 'bg-white dark:bg-surface-800 text-emerald-600 dark:text-emerald-400 shadow-sm' : 'text-text-secondary hover:text-text-primary'">
                        <span>Stok by Unit</span>
                    </button>
                    <button type="button" @click="method = 'by_imei'"
                        class="flex-1 flex items-center justify-center gap-1.5 py-2 text-xs sm:text-sm font-bold rounded-lg transition-all"
                        :class="method === 'by_imei' ? 'bg-white dark:bg-surface-800 text-emerald-600 dark:text-emerald-400 shadow-sm' : 'text-text-secondary hover:text-text-primary'">
                        <span>Stok by IMEI (Checklist)</span>
                    </button>
                </div>
            </div>

            <!-- Brand Filter Selector -->
            <div class="pt-2">
                <label class="block text-xs font-semibold text-text-secondary uppercase tracking-wider mb-2">
                    Pilih Brand (Merek)
                </label>
                <div class="flex flex-wrap gap-2">
                    <button type="button" @click="selectedBrand = ''"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all border"
                        :class="selectedBrand === '' ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm' : 'bg-white dark:bg-surface-700 text-text-secondary border-gray-200 dark:border-surface-600 hover:border-emerald-500'">
                        Semua Brand
                    </button>
                    <button v-for="b in availableBrands" :key="b.brand" type="button" @click="selectedBrand = b.brand"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all border flex items-center gap-1.5"
                        :class="selectedBrand === b.brand ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm' : 'bg-white dark:bg-surface-700 text-text-secondary border-gray-200 dark:border-surface-600 hover:border-emerald-500'">
                        <span>{{ b.brand }}</span>
                        <span class="text-[10px] px-1.5 py-0.2 rounded-full"
                            :class="selectedBrand === b.brand ? 'bg-emerald-700 text-white' : 'bg-gray-100 dark:bg-surface-600 text-text-secondary'">
                            {{ b.count }}
                        </span>
                    </button>
                </div>
            </div>

            <!-- If By IMEI: Tipe Produk Selector -->
            <div v-if="categoryType === 'hp' && method === 'by_imei'" class="pt-2 border-t border-gray-100 dark:border-surface-700">
                <label class="block text-xs font-semibold text-text-secondary uppercase tracking-wider mb-2">
                    Pilih Tipe HP (Diurutkan Stok Terbanyak)
                </label>
                <div class="relative max-w-md">
                    <select v-model="selectedProductName"
                        class="w-full appearance-none bg-white dark:!bg-surface-700 border border-gray-200 dark:border-surface-600 rounded-xl px-4 py-2.5 pr-10 text-sm font-semibold text-text-primary focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all cursor-pointer">
                        <option value="" class="bg-white text-gray-900 dark:bg-surface-800 dark:text-white">-- Pilih Tipe Produk untuk Checklist IMEI --</option>
                        <option v-for="t in availableTypes" :key="t.product_name" :value="t.product_name" class="bg-white text-gray-900 dark:bg-surface-800 dark:text-white">
                            {{ t.product_name }} ({{ t.count }} unit)
                        </option>
                    </select>
                    <ChevronDown :size="16" class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
                </div>
            </div>
        </div>

        <!-- Section 3: Daftar Barang yang Di-SO -->
        <div class="bg-white dark:!bg-surface-800 p-4 sm:p-6 rounded-2xl border border-gray-100 dark:border-surface-700 shadow-sm space-y-4">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h2 class="text-base font-bold text-text-primary">
                        {{ method === 'by_imei' ? 'Checklist IMEI Fisik' : 'Perhitungan Unit Fisik' }}
                    </h2>
                    <p class="text-xs text-text-secondary mt-0.5">
                        {{ method === 'by_imei' ? 'Tandai Ada ✅ jika unit fisik ditemukan, atau ❌ jika tidak ada' : 'Masukkan jumlah fisik unit yang ditemukan di toko' }}
                    </p>
                </div>

                <!-- Quick Action Buttons -->
                <div class="flex items-center gap-2 self-stretch sm:self-auto">
                    <button v-if="method === 'by_unit' && pendingItems.length > 0" type="button" @click="fillAllMatchingSystem"
                        class="px-3 py-1.5 rounded-lg border border-gray-200 dark:border-surface-600 bg-gray-50 dark:bg-surface-700 text-xs font-semibold text-text-secondary hover:text-text-primary hover:border-emerald-500 transition-all">
                        Isi Sesuai Sistem
                    </button>
                    <template v-else-if="method === 'by_imei' && pendingItems.length > 0">
                        <button type="button" @click="markAllPresent(true)"
                            class="px-3 py-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300 text-xs font-bold hover:bg-emerald-100 transition-all flex items-center gap-1">
                            <Check :size="14" />
                            <span>Semua Ada ✅</span>
                        </button>
                        <button type="button" @click="markAllPresent(false)"
                            class="px-3 py-1.5 rounded-lg bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300 text-xs font-bold hover:bg-rose-100 transition-all flex items-center gap-1">
                            <X :size="14" />
                            <span>Semua Tidak Ada ❌</span>
                        </button>
                    </template>
                </div>
            </div>

            <!-- Loading State -->
            <div v-if="loadingItems" class="py-12 flex flex-col items-center justify-center text-gray-400 gap-2">
                <RefreshCw :size="24" class="animate-spin text-emerald-500" />
                <span class="text-xs font-medium">Memuat data stok...</span>
            </div>

            <!-- Empty State -->
            <div v-else-if="pendingItems.length === 0" class="py-12 text-center text-text-secondary bg-gray-50/50 dark:bg-surface-900/30 rounded-xl border border-dashed border-gray-200 dark:border-surface-700 px-4">
                <template v-if="totalAvailableUnits === 0 && !selectedBrand && !selectedProductName">
                    <CheckCircle2 :size="38" class="mx-auto text-emerald-500 mb-2" />
                    <p class="text-base font-bold text-text-primary">
                        Semua Stok Sudah Selesai di-SO Hari Ini ✅
                    </p>
                    <p class="text-xs text-text-secondary mt-1 max-w-md mx-auto">
                        Seluruh unit fisik di cabang ini telah berhasil di-stok opname dan tercatat di sistem.
                    </p>
                    <div class="mt-4">
                        <router-link to="/inventory/opname/history"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-sm transition-all">
                            <History :size="15" />
                            <span>Buka History Stok Opname</span>
                        </router-link>
                    </div>
                </template>
                <template v-else-if="method === 'by_imei' && !selectedProductName">
                    <Package :size="32" class="mx-auto text-gray-300 dark:text-gray-600 mb-2" />
                    <p class="text-sm font-semibold text-text-primary">
                        Silakan pilih Tipe HP di atas untuk memulai checklist
                    </p>
                    <p class="text-xs text-text-secondary mt-1 max-w-md mx-auto">
                        Pilih salah satu tipe produk untuk menampilkan seluruh IMEI yang tersedia di cabang.
                    </p>
                </template>
                <template v-else>
                    <Package :size="32" class="mx-auto text-gray-300 dark:text-gray-600 mb-2" />
                    <p class="text-sm font-semibold text-text-primary">
                        Tidak ada barang yang perlu di-SO untuk filter ini
                    </p>
                    <p class="text-xs text-text-secondary mt-1 max-w-md mx-auto">
                        Semua barang untuk filter ini sudah selesai di-SO hari ini, atau stok di cabang ini kosong.
                    </p>
                </template>
            </div>

            <!-- CASE A: STOK BY UNIT -->
            <div v-else-if="method === 'by_unit'" class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[640px]">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-surface-700 bg-gray-50/80 dark:bg-surface-700/40 text-[11px] font-bold text-text-secondary uppercase">
                            <th class="py-3 px-3">Brand & Tipe Produk</th>
                            <th class="py-3 px-3 text-center">Kapasitas (GB)</th>
                            <th class="py-3 px-3 text-center">Stok Sistem</th>
                            <th class="py-3 px-3 text-center w-36">Fisik Toko</th>
                            <th class="py-3 px-3 text-center">Selisih</th>
                            <th class="py-3 px-3">Catatan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-surface-700/60 text-sm">
                        <tr v-for="it in pendingItems" :key="it.key" class="hover:bg-gray-50/60 dark:hover:bg-surface-700/30 transition-colors">
                            <td class="py-3 px-3">
                                <div class="font-bold text-text-primary">{{ it.product_name }}</div>
                                <div class="text-xs text-text-secondary font-medium">{{ it.brand }}</div>
                            </td>
                            <td class="py-3 px-3 text-center font-mono text-xs font-semibold">
                                {{ it.storage || '-' }}
                            </td>
                            <td class="py-3 px-3 text-center">
                                <span class="px-2.5 py-1 rounded-lg bg-gray-100 dark:bg-surface-700 font-bold font-mono text-xs text-text-primary">
                                    {{ it.system_qty }}
                                </span>
                            </td>
                            <td class="py-3 px-3 text-center">
                                <input type="number" min="0" v-model.number="it.physical_qty" @input="onPhysicalQtyChange(it)"
                                    class="w-24 text-center font-bold font-mono text-sm py-1.5 bg-white dark:!bg-surface-700 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500" />
                            </td>
                            <td class="py-3 px-3 text-center font-mono font-bold text-xs">
                                <span v-if="it.difference === 0" class="text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/40 px-2 py-0.5 rounded">
                                    Cocok (0)
                                </span>
                                <span v-else-if="it.difference < 0" class="text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/40 px-2 py-0.5 rounded">
                                    Kurang ({{ it.difference }})
                                </span>
                                <span v-else class="text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/40 px-2 py-0.5 rounded">
                                    Lebih (+{{ it.difference }})
                                </span>
                            </td>
                            <td class="py-3 px-3">
                                <input type="text" v-model="it.notes" placeholder="Catatan (opsional)..."
                                    class="w-full text-xs py-1.5 px-2.5 bg-white dark:!bg-surface-700 border border-gray-200 dark:border-surface-600 rounded-lg focus:ring-1 focus:ring-emerald-500 text-text-primary placeholder:text-gray-400" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- CASE B: STOK BY IMEI (CHECKLIST) -->
            <div v-else-if="method === 'by_imei'" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div v-for="it in pendingItems" :key="it.product_detail_id"
                    class="p-4 rounded-xl border transition-all flex flex-col justify-between gap-3"
                    :class="it.is_present ? 'bg-white dark:!bg-surface-800 border-gray-200 dark:border-surface-700' : 'bg-rose-50/40 dark:bg-rose-950/20 border-rose-200 dark:border-rose-900/60'">
                    
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="font-mono text-sm sm:text-base font-bold text-text-primary tracking-wide">
                                {{ it.imei }}
                            </div>
                            <div class="text-xs text-text-secondary mt-1 flex flex-wrap items-center gap-2">
                                <span class="font-semibold text-text-primary">{{ it.product_name }}</span>
                                <span v-if="it.storage && it.storage !== '-'" class="px-1.5 py-0.5 rounded bg-gray-100 dark:bg-surface-700 text-[10px] font-bold">
                                    {{ it.storage }}
                                </span>
                                <span class="px-1.5 py-0.5 rounded bg-gray-100 dark:bg-surface-700 text-[10px]">
                                    {{ it.condition === 'new' ? 'Baru' : 'Second' }}
                                </span>
                                <span class="text-gray-400 text-[11px]">{{ it.distributor }}</span>
                            </div>
                        </div>

                        <!-- Status Indicator Pill -->
                        <span class="text-[11px] font-bold px-2 py-0.5 rounded-full uppercase"
                            :class="it.is_present ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300'">
                            {{ it.is_present ? 'Ada ✅' : 'Tidak Ada ❌' }}
                        </span>
                    </div>

                    <!-- Checklist Action Buttons: Ada (✅) / Tidak Ada (❌) -->
                    <div class="grid grid-cols-2 gap-2 pt-1">
                        <button type="button" @click="it.is_present = true"
                            class="py-2.5 px-3 rounded-xl text-xs sm:text-sm font-bold flex items-center justify-center gap-1.5 transition-all touch-manipulation min-h-[44px]"
                            :class="it.is_present ? 'bg-emerald-600 text-white shadow-sm ring-2 ring-emerald-500/30' : 'border border-gray-200 dark:border-surface-600 bg-gray-50/80 dark:bg-surface-700/80 text-text-secondary hover:bg-emerald-50 hover:text-emerald-600 dark:hover:bg-emerald-950/40 dark:hover:text-emerald-300'">
                            <Check :size="16" />
                            <span>Ada ✅</span>
                        </button>
                        <button type="button" @click="it.is_present = false"
                            class="py-2.5 px-3 rounded-xl text-xs sm:text-sm font-bold flex items-center justify-center gap-1.5 transition-all touch-manipulation min-h-[44px]"
                            :class="!it.is_present ? 'bg-rose-600 text-white shadow-sm ring-2 ring-rose-500/30' : 'border border-gray-200 dark:border-surface-600 bg-gray-50/80 dark:bg-surface-700/80 text-text-secondary hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/40 dark:hover:text-rose-300'">
                            <X :size="16" />
                            <span>Tidak Ada ❌</span>
                        </button>
                    </div>

                    <!-- Input Notes if Missing -->
                    <div v-if="!it.is_present" class="pt-1">
                        <input type="text" v-model="it.notes" placeholder="Keterangan unit tidak ada (misal: di servis / display)..."
                            class="w-full text-xs py-1.5 px-3 bg-white dark:!bg-surface-700 border border-rose-300 dark:border-rose-800 rounded-lg focus:ring-1 focus:ring-rose-500 text-text-primary" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 4: Barang Lebih (Surplus) -->
        <div class="bg-white dark:!bg-surface-800 p-4 sm:p-6 rounded-2xl border border-gray-100 dark:border-surface-700 shadow-sm space-y-4">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h2 class="text-sm font-bold text-text-primary flex items-center gap-2">
                        <AlertTriangle :size="16" class="text-amber-500" />
                        <span>Barang Lebih (Fisik Ada tapi Tidak di Sistem)</span>
                    </h2>
                    <p class="text-xs text-text-secondary mt-0.5">
                        Catat jika ada barang fisik di toko yang belum terdaftar di sistem
                    </p>
                </div>

                <button type="button" @click="showExcessModal = true"
                    class="px-3.5 py-2 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-amber-700 dark:text-amber-300 text-xs font-bold hover:bg-amber-100 transition-all flex items-center gap-1.5">
                    <Plus :size="16" />
                    <span>Catat Barang Lebih</span>
                </button>
            </div>

            <!-- List of Excess Items -->
            <div v-if="excessItems.length > 0" class="divide-y divide-gray-100 dark:divide-surface-700 border border-gray-200 dark:border-surface-700 rounded-xl overflow-hidden">
                <div v-for="(ex, idx) in excessItems" :key="ex.id || idx" class="p-3.5 flex items-center justify-between gap-3 bg-amber-50/30 dark:bg-surface-700/20">
                    <div class="space-y-0.5">
                        <div class="text-xs sm:text-sm font-bold text-text-primary flex items-center gap-2">
                            <span>{{ ex.product_name }}</span>
                            <span v-if="ex.brand" class="text-[10px] bg-gray-100 dark:bg-surface-600 px-1.5 py-0.5 rounded font-medium text-text-secondary">{{ ex.brand }}</span>
                            <span v-if="ex.storage" class="text-[10px] bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 px-1.5 py-0.5 rounded font-bold">{{ ex.storage }}</span>
                        </div>
                        <div class="text-xs text-text-secondary flex flex-wrap items-center gap-2 font-mono">
                            <span v-if="ex.imei">IMEI: {{ ex.imei }}</span>
                            <span v-else>Jumlah: {{ ex.quantity }} unit</span>
                            <span v-if="ex.notes" class="text-gray-400 font-sans italic">— "{{ ex.notes }}"</span>
                        </div>
                    </div>
                    <button type="button" @click="removeExcessItem(idx)" class="p-2 text-rose-500 hover:text-rose-700 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-lg transition-colors">
                        <Trash2 :size="16" />
                    </button>
                </div>
            </div>
            <div v-else class="text-xs text-gray-400 italic py-2">
                Belum ada barang lebih yang dicatat.
            </div>
        </div>

        <!-- Section 5: Foto Bukti SO & Catatan Umum -->
        <div class="bg-white dark:!bg-surface-800 p-4 sm:p-6 rounded-2xl border border-gray-100 dark:border-surface-700 shadow-sm space-y-4">
            <h2 class="text-sm font-bold text-text-primary flex items-center gap-2">
                <Camera :size="16" class="text-emerald-500" />
                <span>Foto Bukti SO & Catatan</span>
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Foto Upload -->
                <div>
                    <label class="block text-xs font-semibold text-text-secondary uppercase tracking-wider mb-2">
                        Foto Bukti Fisik Stok
                    </label>

                    <div class="flex items-center gap-3">
                        <label class="flex items-center gap-2 px-4 py-2.5 bg-gray-50 dark:bg-surface-700 border border-dashed border-gray-300 dark:border-surface-600 rounded-xl cursor-pointer hover:border-emerald-500 transition-colors text-xs font-semibold text-text-primary">
                            <Upload :size="16" class="text-emerald-500" />
                            <span>Pilih / Ambil Foto</span>
                            <input type="file" accept="image/*" multiple @change="handlePhotoSelect" class="hidden" />
                        </label>
                        <span class="text-xs text-text-secondary">{{ photoPreviews.length }} foto terpilih</span>
                    </div>

                    <!-- Thumbnails Preview -->
                    <div v-if="photoPreviews.length > 0" class="flex flex-wrap gap-2.5 mt-3">
                        <div v-for="(p, pIdx) in photoPreviews" :key="pIdx" class="relative group w-20 h-20 rounded-xl border border-gray-200 dark:border-surface-600 overflow-hidden bg-gray-100">
                            <img :src="p" alt="Bukti SO" class="w-full h-full object-cover" />
                            <button type="button" @click="removePhoto(pIdx)"
                                class="absolute top-1 right-1 p-1 bg-black/70 text-white rounded-full hover:bg-rose-600 transition-colors">
                                <X :size="12" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Catatan Umum -->
                <div>
                    <label class="block text-xs font-semibold text-text-secondary uppercase tracking-wider mb-2">
                        Catatan Tambahan (Opsional)
                    </label>
                    <textarea v-model="generalNotes" rows="3" placeholder="Tuliskan catatan kondisi stok toko, kendala, atau keterangan khusus..."
                        class="w-full text-sm p-3 bg-white dark:!bg-surface-700 border border-gray-200 dark:border-surface-600 rounded-xl focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 text-text-primary placeholder:text-gray-400"></textarea>
                </div>
            </div>
        </div>

        <!-- Sticky Bottom Summary & Submit Dock -->
        <div class="sticky bottom-4 z-30 bg-white/95 dark:!bg-surface-800/95 backdrop-blur-xl rounded-2xl border border-gray-200/80 dark:border-surface-700 p-4 sm:p-5 shadow-xl shadow-black/5 dark:shadow-black/30 transition-all">
            <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
                <!-- Summary Metrics Group -->
                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3 sm:gap-5 w-full lg:w-auto">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0 border border-emerald-500/20">
                            <ClipboardCheck :size="20" />
                        </div>
                        <div>
                            <div class="text-[10px] uppercase font-black text-text-secondary tracking-wider">Hasil Pemeriksaan Fisik</div>
                            <div class="text-sm font-black text-text-primary">
                                {{ summaryStats.totalPhysical }} <span class="font-normal text-text-secondary text-xs">dari {{ summaryStats.totalSystem }} unit</span>
                            </div>
                        </div>
                    </div>

                    <!-- Metrics Badges -->
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <div class="px-3 py-1.5 rounded-xl bg-gray-100 dark:bg-surface-700/80 border border-gray-200/60 dark:border-surface-600 font-semibold text-text-secondary flex items-center gap-1.5">
                            <span>Sistem:</span>
                            <span class="font-bold font-mono text-text-primary">{{ summaryStats.totalSystem }}</span>
                        </div>
                        <div class="px-3 py-1.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200/60 dark:border-emerald-800/60 font-semibold text-emerald-700 dark:text-emerald-300 flex items-center gap-1.5">
                            <span>Fisik Ada:</span>
                            <span class="font-bold font-mono">{{ summaryStats.totalPhysical }}</span>
                        </div>
                        <div v-if="summaryStats.totalMissing > 0" class="px-3 py-1.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200/60 dark:border-rose-800/60 font-semibold text-rose-700 dark:text-rose-300 flex items-center gap-1.5">
                            <span>Selisih:</span>
                            <span class="font-bold font-mono">-{{ summaryStats.totalMissing }}</span>
                        </div>
                        <div v-if="summaryStats.totalExcess > 0" class="px-3 py-1.5 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200/60 dark:border-amber-800/60 font-semibold text-amber-700 dark:text-amber-300 flex items-center gap-1.5">
                            <span>Lebih:</span>
                            <span class="font-bold font-mono">+{{ summaryStats.totalExcess }}</span>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="w-full lg:w-auto">
                    <button v-if="pendingItems.length === 0 && excessItems.length === 0 && totalAvailableUnits === 0"
                        type="button"
                        disabled
                        class="w-full lg:w-auto px-7 py-3 rounded-xl bg-gray-100 dark:bg-surface-700 border border-gray-200 dark:border-surface-600 text-gray-400 dark:text-gray-500 font-bold text-sm flex items-center justify-center gap-2.5 cursor-not-allowed min-h-[48px]">
                        <CheckCircle2 :size="18" class="text-emerald-500" />
                        <span>Sudah Selesai di-SO Hari Ini</span>
                    </button>
                    <button v-else
                        type="button"
                        @click="handleVerifyAndSubmit"
                        :disabled="submitting || (pendingItems.length === 0 && excessItems.length === 0)"
                        class="w-full lg:w-auto px-7 py-3 rounded-xl bg-gradient-to-r from-emerald-600 to-emerald-500 hover:from-emerald-500 hover:to-emerald-400 active:from-emerald-700 active:to-emerald-600 text-white font-bold text-sm shadow-lg shadow-emerald-500/25 hover:shadow-emerald-500/40 transition-all flex items-center justify-center gap-2.5 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer min-h-[48px]">
                        <RefreshCw v-if="submitting" :size="18" class="animate-spin" />
                        <CheckCircle2 v-else :size="18" />
                        <span>{{ submitting ? 'Menyimpan Hasil SO...' : 'Simpan & Selesaikan SO' }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal Catat Barang Lebih -->
        <div v-if="showExcessModal" class="fixed inset-0 z-[999] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div class="bg-white dark:!bg-surface-800 rounded-2xl max-w-md w-full p-5 sm:p-6 border border-gray-100 dark:border-surface-700 shadow-2xl space-y-4">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-surface-700 pb-3">
                    <h3 class="text-base font-bold text-text-primary flex items-center gap-2">
                        <Plus :size="18" class="text-amber-500" />
                        <span>Tambah Barang Lebih</span>
                    </h3>
                    <button @click="showExcessModal = false" class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-surface-700 transition-colors">
                        <X :size="18" />
                    </button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block font-semibold text-text-secondary uppercase mb-1">Merek / Brand</label>
                        <input type="text" v-model="newExcess.brand" placeholder="Misal: iPhone / Samsung / Ugreen"
                            class="w-full py-2 px-3 bg-white dark:!bg-surface-700 border border-gray-200 dark:border-surface-600 rounded-xl text-sm text-text-primary" />
                    </div>

                    <div>
                        <label class="block font-semibold text-text-secondary uppercase mb-1">Nama / Tipe Barang *</label>
                        <input type="text" v-model="newExcess.product_name" placeholder="Misal: iPhone 13 / Charger 20W"
                            class="w-full py-2 px-3 bg-white dark:!bg-surface-700 border border-gray-200 dark:border-surface-600 rounded-xl text-sm text-text-primary" />
                    </div>

                    <div v-if="categoryType === 'hp'" class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block font-semibold text-text-secondary uppercase mb-1">Storage (GB)</label>
                            <input type="text" v-model="newExcess.storage" placeholder="Misal: 128 GB"
                                class="w-full py-2 px-3 bg-white dark:!bg-surface-700 border border-gray-200 dark:border-surface-600 rounded-xl text-sm text-text-primary" />
                        </div>
                        <div>
                            <label class="block font-semibold text-text-secondary uppercase mb-1">IMEI Unit</label>
                            <input type="text" v-model="newExcess.imei" placeholder="15 digit IMEI"
                                class="w-full py-2 px-3 bg-white dark:!bg-surface-700 border border-gray-200 dark:border-surface-600 rounded-xl text-sm text-text-primary font-mono" />
                        </div>
                    </div>

                    <div v-else>
                        <label class="block font-semibold text-text-secondary uppercase mb-1">Jumlah (Qty)</label>
                        <input type="number" min="1" v-model.number="newExcess.quantity"
                            class="w-full py-2 px-3 bg-white dark:!bg-surface-700 border border-gray-200 dark:border-surface-600 rounded-xl text-sm text-text-primary font-mono" />
                    </div>

                    <div>
                        <label class="block font-semibold text-text-secondary uppercase mb-1">Catatan / Keterangan</label>
                        <textarea v-model="newExcess.notes" rows="2" placeholder="Keterangan asal barang lebih (misal: kiriman dari cabang lain)..."
                            class="w-full py-2 px-3 bg-white dark:!bg-surface-700 border border-gray-200 dark:border-surface-600 rounded-xl text-sm text-text-primary"></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-gray-100 dark:border-surface-700">
                    <button type="button" @click="showExcessModal = false"
                        class="px-4 py-2 rounded-xl text-xs font-semibold text-text-secondary hover:bg-gray-100 dark:hover:bg-surface-700">
                        Batal
                    </button>
                    <button type="button" @click="addExcessItem"
                        class="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-500 text-white text-xs font-bold shadow-sm">
                        Simpan Barang Lebih
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal Verifikasi Password CS -->
        <div v-if="showPasswordModal" class="fixed inset-0 z-[999] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div class="bg-white dark:!bg-surface-800 rounded-2xl max-w-sm w-full p-5 sm:p-6 border border-gray-100 dark:border-surface-700 shadow-2xl space-y-4">
                <div class="text-center space-y-1">
                    <div class="w-12 h-12 rounded-full bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 mx-auto flex items-center justify-center mb-2">
                        <ShieldCheck :size="24" />
                    </div>
                    <h3 class="text-base font-bold text-text-primary">Verifikasi Password CS</h3>
                    <p class="text-xs text-text-secondary">
                        Masukkan password akun CS yang dipilih untuk mengonfirmasi hasil Stok Opname toko.
                    </p>
                </div>

                <div>
                    <input type="password" v-model="csPassword" placeholder="Password CS..." @keydown.enter="submitWithPassword"
                        class="w-full py-2.5 px-3.5 bg-white dark:!bg-surface-700 border border-gray-300 dark:border-surface-600 rounded-xl text-sm text-text-primary focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 text-center tracking-widest font-mono" />
                </div>

                <div class="flex items-center gap-2 pt-1">
                    <button type="button" @click="showPasswordModal = false"
                        class="flex-1 py-2.5 rounded-xl text-xs font-semibold text-text-secondary hover:bg-gray-100 dark:hover:bg-surface-700 border border-gray-200 dark:border-surface-600">
                        Batal
                    </button>
                    <button type="button" @click="submitWithPassword" :disabled="verifyingPassword || !csPassword"
                        class="flex-1 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-sm disabled:opacity-50 flex items-center justify-center gap-1.5">
                        <RefreshCw v-if="verifyingPassword" :size="14" class="animate-spin" />
                        <span>Verifikasi & Simpan</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
