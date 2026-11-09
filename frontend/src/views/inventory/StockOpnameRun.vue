<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import {
    Smartphone, Package, Check, X, CheckCircle2, XCircle, ClipboardCheck,
    Camera, Upload, Plus, Trash2, AlertTriangle, Building2,
    User, Lock, ShieldCheck, History, ArrowLeft, ChevronDown,
    RefreshCw, Search, Eye, ArrowRight, Shield, UserCheck, Store,
    Layers, QrCode, Sparkles
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

// Step Navigation:
// Step 1: Pilih Toko (Cabang)
// Step 2: Pilih CS
// Step 3: Pemeriksaan Stok (HP/Non-HP -> By Unit/IMEI -> Brand/Kondisi / Tipe)
const currentStep = ref(1);

// Step 1: Branch Selection
const branches = ref([]);
const selectedBranchId = ref(null);
const searchBranch = ref('');

// Step 2: CS Selection
const csUsers = ref([]);
const selectedCsUserId = ref(null);
const searchCs = ref('');

// Step 3: Configuration (Kategori & Metode Opname)
const categoryType = ref('hp'); // 'hp' | 'non_hp'
const method = ref('by_unit'); // 'by_unit' | 'by_imei'
const selectedBrand = ref('');
const selectedCondition = ref('all'); // 'all' | 'new' | 'second'
const selectedProductName = ref(''); // For SO by IMEI
const searchType = ref(''); // Search bar for phone model in SO by IMEI

// Items Data from Server
const availableBrands = ref([]);
const availableConditions = ref([]);
const availableTypes = ref([]);
const pendingItems = ref([]);
const totalAvailableUnits = ref(0);

// Password verification for store accounts
const showPasswordModal = ref(false);
const csPassword = ref('');
const passwordVerified = ref(false);
const verifyingPassword = ref(false);

// Barang Lebih (Excess Items)
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

// Foto SO & Catatan Umum
const photoPreviews = ref([]);
const generalNotes = ref('');

// User Role Helpers
const userRole = computed(() => (authStore.userRole || '').toLowerCase().trim());

const isLeader = computed(() => {
    const role = userRole.value;
    if (role === 'leader' || role.includes('leader')) return true;
    if (authStore.user?.role && String(authStore.user.role).toLowerCase().includes('leader')) return true;
    if (Array.isArray(authStore.user?.roles)) {
        return authStore.user.roles.some(r => {
            const name = typeof r === 'string' ? r : (r?.name || '');
            return name.toLowerCase().includes('leader');
        });
    }
    return false;
});

const isAudit = computed(() => {
    const role = userRole.value;
    if (role === 'audit' || role.includes('audit')) return true;
    if (authStore.user?.role && String(authStore.user.role).toLowerCase().includes('audit')) return true;
    if (Array.isArray(authStore.user?.roles)) {
        return authStore.user.roles.some(r => {
            const name = typeof r === 'string' ? r : (r?.name || '');
            return name.toLowerCase().includes('audit');
        });
    }
    return false;
});

const isLeaderOrAudit = computed(() => isLeader.value || isAudit.value);

const isPrivilegedRole = computed(() => {
    const privileged = ['super_admin', 'leader', 'audit', 'owner', 'admin_produk', 'analist', 'analis'];
    return isLeaderOrAudit.value || privileged.some(r => userRole.value.includes(r));
});

const isStoreAccount = computed(() => {
    if (isLeaderOrAudit.value || isPrivilegedRole.value) return false;
    return userRole.value === 'toko_offline' || (authStore.user?.id !== selectedCsUserId.value);
});

// Computed Filters
const filteredBranches = computed(() => {
    if (!searchBranch.value.trim()) return branches.value;
    const q = searchBranch.value.toLowerCase().trim();
    return branches.value.filter(b =>
        (b.name && b.name.toLowerCase().includes(q)) ||
        (b.code && b.code.toLowerCase().includes(q))
    );
});

const filteredCsUsers = computed(() => {
    if (!searchCs.value.trim()) return csUsers.value;
    const q = searchCs.value.toLowerCase().trim();
    return csUsers.value.filter(u =>
        (u.name && u.name.toLowerCase().includes(q)) ||
        (u.username && u.username.toLowerCase().includes(q))
    );
});

const filteredAvailableTypes = computed(() => {
    let list = availableTypes.value || [];
    if (selectedBrand.value) {
        list = list.filter(t => !t.brand || t.brand.toLowerCase() === selectedBrand.value.toLowerCase());
    }
    if (searchType.value.trim()) {
        const q = searchType.value.toLowerCase().trim();
        list = list.filter(t =>
            (t.product_name && t.product_name.toLowerCase().includes(q)) ||
            (t.brand && t.brand.toLowerCase().includes(q))
        );
    }
    return list;
});

const activeBranch = computed(() => {
    return branches.value.find(b => Number(b.id) === Number(selectedBranchId.value)) || null;
});

const activeCsUser = computed(() => {
    if (isLeaderOrAudit.value && selectedCsUserId.value === authStore.user?.id) {
        return authStore.user;
    }
    return csUsers.value.find(u => Number(u.id) === Number(selectedCsUserId.value)) || authStore.user || null;
});

const getInitials = (name) => {
    if (!name) return 'SO';
    return name.split(' ').map(n => n[0]).filter(Boolean).slice(0, 2).join('').toUpperCase();
};

const getCsPhotoUrl = (acc) => {
    if (!acc) return null;
    const rawPhoto = acc.photo || acc.photo_inventory;
    if (!rawPhoto) return null;
    if (rawPhoto.startsWith('http://') || rawPhoto.startsWith('https://') || rawPhoto.startsWith('data:image/')) return rawPhoto;
    let path = rawPhoto;
    if (path.startsWith('/')) path = path.substring(1);
    if (path.startsWith('storage/')) path = path.substring(8);
    const baseUrl = (authStore.storageBaseUrl || 'https://api.stokps.com').replace(/\/+$/, '').replace(/\/api\/?$/, '');
    return `${baseUrl}/storage/${path}`;
};

// API Calls
const fetchBranches = async () => {
    loadingBranches.value = true;
    try {
        const res = await axios.get('/inventory/opname/branches');
        branches.value = res.data.data || [];

        if (branches.value.length > 0) {
            // Default select user's branch if matches, otherwise first branch
            const userBranchId = authStore.user?.branch_id;
            const match = branches.value.find(b => Number(b.id) === Number(userBranchId));
            if (!selectedBranchId.value) {
                selectedBranchId.value = match ? match.id : branches.value[0].id;
            }
        }
    } catch (err) {
        console.error('Error fetching branches for opname:', err);
        toast.error('Gagal memuat daftar cabang.');
    } finally {
        loadingBranches.value = false;
    }
};

const fetchCsUsers = async () => {
    if (!selectedBranchId.value) return;
    loadingCs.value = true;
    try {
        const res = await axios.get(`/inventory/opname/branch-cs/${selectedBranchId.value}`);
        csUsers.value = res.data.data || [];

        if (csUsers.value.length > 0) {
            const myId = authStore.user?.id;
            const myCs = csUsers.value.find(u => Number(u.id) === Number(myId));
            if (myCs) {
                selectedCsUserId.value = myCs.id;
                passwordVerified.value = true;
            } else if (!selectedCsUserId.value) {
                selectedCsUserId.value = csUsers.value[0].id;
                passwordVerified.value = false;
            }
        } else {
            selectedCsUserId.value = authStore.user?.id || null;
            passwordVerified.value = true;
        }
    } catch (err) {
        console.error('Error fetching CS users:', err);
    } finally {
        loadingCs.value = false;
    }
};

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
        if (method.value === 'by_unit' && selectedCondition.value && selectedCondition.value !== 'all') {
            params.condition = selectedCondition.value;
        }
        if (method.value === 'by_imei' && selectedProductName.value) {
            params.product_name = selectedProductName.value;
        }

        const res = await axios.get('/inventory/opname/pending-items', { params });
        availableBrands.value = res.data.available_brands || [];
        availableConditions.value = res.data.available_conditions || [];
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

// Wizard Step Navigation
const goToStep = (step) => {
    if (step === 1) {
        currentStep.value = 1;
    } else if (step === 2) {
        if (!selectedBranchId.value) {
            toast.warning('Silakan pilih toko terlebih dahulu.');
            return;
        }
        currentStep.value = 2;
    } else if (step === 3) {
        if (!selectedBranchId.value) {
            toast.warning('Silakan pilih toko terlebih dahulu.');
            return;
        }
        if (!selectedCsUserId.value) {
            toast.warning('Silakan pilih akun CS pelaksana terlebih dahulu.');
            return;
        }
        currentStep.value = 3;
    }
};

const proceedToStep2 = async () => {
    if (!selectedBranchId.value) {
        toast.warning('Silakan pilih toko terlebih dahulu.');
        return;
    }
    await fetchCsUsers();
    currentStep.value = 2;
};

const proceedToStep3 = async () => {
    if (!selectedCsUserId.value) {
        toast.warning('Silakan pilih akun CS pelaksana.');
        return;
    }
    selectedBrand.value = '';
    selectedCondition.value = 'all';
    selectedProductName.value = '';
    searchType.value = '';
    excessItems.value = [];
    photoPreviews.value = [];
    await fetchPendingItems();
    currentStep.value = 3;
};

// Select Tipe for SO by IMEI
const selectType = (typeName) => {
    selectedProductName.value = typeName;
    fetchPendingItems();
};

const clearSelectedType = () => {
    selectedProductName.value = '';
    pendingItems.value = [];
    fetchPendingItems();
};

// Watchers
watch(selectedBranchId, () => {
    selectedCsUserId.value = null;
    selectedBrand.value = '';
    selectedCondition.value = 'all';
    selectedProductName.value = '';
    fetchCsUsers();
    if (currentStep.value === 3) {
        fetchPendingItems();
    }
});

watch(categoryType, (newVal) => {
    selectedBrand.value = '';
    selectedCondition.value = 'all';
    selectedProductName.value = '';
    searchType.value = '';
    if (newVal === 'non_hp') {
        method.value = 'by_unit';
    }
    if (currentStep.value === 3) {
        fetchPendingItems();
    }
});

watch(method, () => {
    selectedProductName.value = '';
    selectedCondition.value = 'all';
    searchType.value = '';
    if (currentStep.value === 3) {
        fetchPendingItems();
    }
});

watch(selectedBrand, () => {
    selectedProductName.value = '';
    if (currentStep.value === 3) {
        fetchPendingItems();
    }
});

watch(selectedCondition, () => {
    if (method.value === 'by_unit' && currentStep.value === 3) {
        fetchPendingItems();
    }
});

// Calculations & Quick Actions
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

const markAllPresent = (status) => {
    if (method.value !== 'by_imei') return;
    pendingItems.value.forEach(item => {
        item.is_present = status;
    });
    toast.info(status ? 'Semua unit ditandai Ada (Cocok)' : 'Semua unit ditandai Tidak Ada');
};

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

// Submission & Verification
const handleVerifyAndSubmit = async () => {
    if (!selectedBranchId.value) {
        toast.warning('Pilih toko terlebih dahulu.');
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
            branch_id: Number(selectedBranchId.value),
            cs_user_id: Number(selectedCsUserId.value),
            category_type: categoryType.value,
            method: method.value,
            brand: selectedBrand.value ? String(selectedBrand.value) : null,
            product_type: selectedProductName.value ? String(selectedProductName.value) : null,
            items: pendingItems.value.map(it => ({
                ...it,
                product_id: it.product_id ? Number(it.product_id) : null,
                product_detail_id: it.product_detail_id ? Number(it.product_detail_id) : null,
                product_name: it.product_name !== null && it.product_name !== undefined ? String(it.product_name) : null,
                brand: it.brand !== null && it.brand !== undefined ? String(it.brand) : null,
                storage: it.storage !== null && it.storage !== undefined ? String(it.storage) : null,
                condition: it.condition !== null && it.condition !== undefined ? String(it.condition) : null,
                imei: it.imei !== null && it.imei !== undefined ? String(it.imei) : null,
                system_qty: Number(it.system_qty) || 0,
                physical_qty: Number(it.physical_qty) || 0,
                is_present: Boolean(it.is_present)
            })),
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

onMounted(async () => {
    if (!authStore.user && typeof authStore.fetchUser === 'function') {
        try {
            await authStore.fetchUser();
        } catch (e) {}
    }
    await fetchBranches();
    if (selectedBranchId.value) {
        await fetchCsUsers();
    }
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

        <!-- 3-STEP WIZARD NAV -->
        <div class="bg-white dark:bg-surface-800 rounded-2xl p-3 border border-surface-200 dark:border-surface-700 shadow-sm shrink-0 overflow-x-auto custom-scrollbar">
            <div class="flex items-center justify-between relative px-4 sm:px-12 max-w-2xl mx-auto">
                <div v-for="step in 3" :key="step" class="z-10 flex flex-col items-center gap-1.5 py-1">
                    <button type="button" @click="goToStep(step)"
                        class="w-9 h-9 sm:w-10 sm:h-10 rounded-full flex items-center justify-center font-black transition-all cursor-pointer text-xs sm:text-sm"
                        :class="currentStep === step ? 'bg-emerald-600 text-white shadow-lg ring-4 ring-emerald-500/20' : step < currentStep ? 'bg-emerald-500 text-white' : 'bg-surface-100 dark:bg-surface-900 text-text-secondary opacity-50'">
                        <CheckCircle2 v-if="step < currentStep" :size="18" />
                        <span v-else>{{ step }}</span>
                    </button>
                    <span class="text-[10px] sm:text-xs font-black uppercase tracking-wider text-center"
                        :class="currentStep === step ? 'text-emerald-600 dark:text-emerald-400' : 'text-text-secondary opacity-60'">
                        {{ ['1. Pilih Toko', '2. Pilih CS', '3. Pemeriksaan Stok'][step - 1] }}
                    </span>
                </div>
                <!-- Connector line behind steps -->
                <div class="absolute left-12 right-12 top-5 -translate-y-1/2 h-0.5 -z-0 bg-surface-200 dark:bg-surface-700">
                    <div class="h-full bg-emerald-500 transition-all duration-300"
                        :style="{ width: currentStep === 1 ? '0%' : currentStep === 2 ? '50%' : '100%' }"></div>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- STEP 1: PILIH TOKO (CABANG)                -->
        <!-- ========================================== -->
        <div v-if="currentStep === 1" class="flex items-center justify-center p-2 sm:p-0 my-4">
            <div class="w-full max-w-2xl bg-white dark:bg-surface-800 rounded-[2rem] sm:rounded-[2.5rem] border border-surface-200 dark:border-surface-700 p-6 sm:p-10 shadow-2xl relative overflow-hidden">
                <div class="absolute top-0 right-0 w-64 h-64 bg-emerald-500/5 rounded-full -mr-32 -mt-32 blur-3xl pointer-events-none"></div>

                <div class="relative z-10">
                    <div class="w-16 h-16 sm:w-20 sm:h-20 bg-emerald-50 dark:bg-emerald-950/40 rounded-3xl flex items-center justify-center mb-6 mx-auto ring-8 ring-emerald-500/10">
                        <Building2 class="text-emerald-600 dark:text-emerald-400" :size="36" stroke-width="2.5" />
                    </div>

                    <h2 class="text-2xl sm:text-3xl font-black text-text-primary text-center mb-2">
                        1. Pilih Toko / Cabang
                    </h2>
                    <p class="text-text-secondary text-center mb-6 font-medium text-xs sm:text-sm">
                        Silakan pilih toko atau cabang tempat fisik stok akan diperiksa.
                    </p>

                    <div class="space-y-4">
                        <!-- List Header -->
                        <div class="flex items-center justify-between px-1">
                            <label class="block text-xs font-black text-text-secondary uppercase tracking-widest">
                                Daftar Toko / Cabang
                            </label>
                            <span class="text-xs text-text-secondary font-medium">
                                {{ filteredBranches.length }} cabang
                            </span>
                        </div>

                        <!-- Search Input -->
                        <div class="relative">
                            <input v-model="searchBranch" type="text" placeholder="Cari nama toko atau kode cabang..."
                                class="w-full px-4 py-2.5 pl-10 text-xs rounded-xl bg-surface-50 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 text-text-primary placeholder:text-text-secondary focus:border-emerald-500 focus:outline-none transition-all" />
                            <Search :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary" />
                        </div>

                        <!-- Grid of Branches -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-[340px] overflow-y-auto pr-1 custom-scrollbar">
                            <div v-if="loadingBranches" class="col-span-1 sm:col-span-2 py-8 text-center text-text-secondary text-xs flex items-center justify-center gap-2">
                                <RefreshCw :size="14" class="animate-spin text-emerald-500" />
                                <span>Memuat daftar toko...</span>
                            </div>
                            <div v-else-if="filteredBranches.length === 0" class="col-span-1 sm:col-span-2 py-8 text-center text-text-secondary text-xs">
                                Tidak ada toko yang ditemukan.
                            </div>
                            <button v-for="b in filteredBranches" :key="b.id"
                                type="button"
                                @click="selectedBranchId = b.id"
                                @dblclick="proceedToStep2"
                                class="w-full p-3.5 sm:p-4 rounded-2xl border-2 transition-all flex items-center justify-between gap-3 relative overflow-hidden group cursor-pointer text-left"
                                :class="selectedBranchId === b.id ? 'border-emerald-600 bg-emerald-50 dark:bg-emerald-950/30 shadow-lg shadow-emerald-500/10 ring-2 ring-emerald-500/20' : 'border-surface-100 dark:border-surface-700 bg-surface-50 dark:bg-surface-900 hover:border-surface-300 dark:hover:border-surface-600'">
                                <div class="flex items-center gap-3 min-w-0 pr-6">
                                    <div class="w-10 h-10 sm:w-11 sm:h-11 shrink-0 rounded-xl bg-surface-200 dark:bg-surface-700 flex items-center justify-center font-black text-xs text-text-primary border border-surface-300 dark:border-surface-600">
                                        {{ b.code ? b.code.substring(0, 3).toUpperCase() : getInitials(b.name) }}
                                    </div>
                                    <div class="flex flex-col items-start truncate min-w-0">
                                        <span class="font-black text-xs sm:text-sm truncate w-full"
                                            :class="selectedBranchId === b.id ? 'text-emerald-600 dark:text-emerald-400' : 'text-text-primary'">
                                            {{ b.name }}
                                        </span>
                                        <span class="text-[10px] sm:text-xs font-semibold text-text-secondary truncate w-full mt-0.5">
                                            Kode: {{ b.code || '-' }}
                                        </span>
                                    </div>
                                </div>
                                <div class="w-5 h-5 shrink-0 rounded-full border-2 flex items-center justify-center transition-all absolute top-1/2 -translate-y-1/2 right-4"
                                    :class="selectedBranchId === b.id ? 'border-emerald-600 bg-emerald-600' : 'border-surface-300 dark:border-surface-600 bg-white dark:bg-surface-800'">
                                    <div v-if="selectedBranchId === b.id" class="w-2 h-2 rounded-full bg-white"></div>
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- Continue to Step 2 Button -->
                    <button type="button" @click="proceedToStep2"
                        :disabled="!selectedBranchId"
                        class="w-full mt-8 h-12 sm:h-14 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white rounded-2xl font-black text-sm sm:text-base shadow-xl shadow-emerald-500/25 transition-all flex items-center justify-center gap-3 cursor-pointer">
                        <span>Lanjutkan ke Pilih CS</span>
                        <ArrowRight :size="20" stroke-width="2.5" />
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- STEP 2: PILIH CS                           -->
        <!-- ========================================== -->
        <div v-else-if="currentStep === 2" class="flex items-center justify-center p-2 sm:p-0 my-4">
            <div class="w-full max-w-2xl bg-white dark:bg-surface-800 rounded-[2rem] sm:rounded-[2.5rem] border border-surface-200 dark:border-surface-700 p-6 sm:p-10 shadow-2xl relative overflow-hidden">
                <div class="absolute top-0 right-0 w-64 h-64 bg-emerald-500/5 rounded-full -mr-32 -mt-32 blur-3xl pointer-events-none"></div>

                <div class="relative z-10">
                    <!-- Selected Branch Banner with Change Button -->
                    <div class="mb-6 p-3.5 rounded-2xl bg-emerald-50/80 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/60 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <Building2 :size="18" class="text-emerald-600 dark:text-emerald-400 shrink-0" />
                            <div class="truncate text-xs">
                                <span class="text-text-secondary">Toko:</span>
                                <strong class="text-text-primary ml-1 font-bold">{{ activeBranch?.name || 'Cabang Terpilih' }}</strong>
                                <span v-if="activeBranch?.code" class="ml-1.5 px-1.5 py-0.2 rounded text-[10px] bg-emerald-100 dark:bg-emerald-900/60 text-emerald-800 dark:text-emerald-200 font-mono">
                                    {{ activeBranch.code }}
                                </span>
                            </div>
                        </div>
                        <button type="button" @click="currentStep = 1"
                            class="text-xs font-bold text-emerald-700 dark:text-emerald-300 hover:underline shrink-0 flex items-center gap-1">
                            <ArrowLeft :size="13" />
                            <span>Ganti Toko</span>
                        </button>
                    </div>

                    <div class="w-16 h-16 sm:w-20 sm:h-20 bg-emerald-50 dark:bg-emerald-950/40 rounded-3xl flex items-center justify-center mb-6 mx-auto ring-8 ring-emerald-500/10">
                        <Shield class="text-emerald-600 dark:text-emerald-400" :size="36" stroke-width="2.5" />
                    </div>

                    <h2 class="text-2xl sm:text-3xl font-black text-text-primary text-center mb-2">
                        2. Pilih Akun CS Pelaksana
                    </h2>
                    <p class="text-text-secondary text-center mb-6 font-medium text-xs sm:text-sm">
                        Silakan pilih akun Customer Service yang bertugas atau bertanggung jawab untuk transaksi stok opname ini.
                    </p>

                    <!-- Self-Audit Option for Leader / Audit / Admin -->
                    <div v-if="isLeaderOrAudit || isPrivilegedRole" class="mb-4 p-3.5 rounded-2xl border-2 transition-all flex items-center justify-between gap-3 cursor-pointer"
                        :class="selectedCsUserId === authStore.user?.id ? 'border-purple-600 bg-purple-50 dark:bg-purple-950/30 ring-2 ring-purple-500/20' : 'border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-900'"
                        @click="selectedCsUserId = authStore.user?.id; passwordVerified = true">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-purple-100 dark:bg-purple-900/60 text-purple-700 dark:text-purple-300 flex items-center justify-center font-black text-xs">
                                <ShieldCheck :size="20" />
                            </div>
                            <div>
                                <div class="text-xs font-bold text-text-primary flex items-center gap-1.5">
                                    <span>{{ authStore.user?.name || authStore.userName }} (Saya Sendiri)</span>
                                    <span class="text-[10px] px-2 py-0.5 rounded-full bg-purple-100 dark:bg-purple-900 text-purple-700 dark:text-purple-300 font-bold uppercase">
                                        {{ isAudit ? 'Audit' : isLeader ? 'Leader' : 'Admin' }}
                                    </span>
                                </div>
                                <div class="text-[11px] text-text-secondary">Lakukan pemeriksaan stok opname sebagai akun {{ isAudit ? 'Audit' : isLeader ? 'Leader' : 'Admin' }}</div>
                            </div>
                        </div>
                        <div class="w-5 h-5 shrink-0 rounded-full border-2 flex items-center justify-center"
                            :class="selectedCsUserId === authStore.user?.id ? 'border-purple-600 bg-purple-600' : 'border-surface-300 dark:border-surface-600'">
                            <div v-if="selectedCsUserId === authStore.user?.id" class="w-2 h-2 rounded-full bg-white"></div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <!-- List Header -->
                        <div class="flex items-center justify-between px-1">
                            <label class="block text-xs font-black text-text-secondary uppercase tracking-widest">
                                Daftar Akun CS di {{ activeBranch?.name || 'Toko' }}
                            </label>
                            <span class="text-xs text-text-secondary font-medium">
                                {{ filteredCsUsers.length }} akun
                            </span>
                        </div>

                        <!-- Search Input -->
                        <div class="relative">
                            <input v-model="searchCs" type="text" placeholder="Cari nama CS..."
                                class="w-full px-4 py-2.5 pl-10 text-xs rounded-xl bg-surface-50 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 text-text-primary placeholder:text-text-secondary focus:border-emerald-500 focus:outline-none transition-all" />
                            <Search :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary" />
                        </div>

                        <!-- Grid of CS Accounts -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-[340px] overflow-y-auto pr-1 custom-scrollbar">
                            <div v-if="loadingCs" class="col-span-1 sm:col-span-2 py-8 text-center text-text-secondary text-xs flex items-center justify-center gap-2">
                                <RefreshCw :size="14" class="animate-spin text-emerald-500" />
                                <span>Memuat daftar akun CS...</span>
                            </div>
                            <div v-else-if="filteredCsUsers.length === 0" class="col-span-1 sm:col-span-2 py-8 text-center text-text-secondary text-xs">
                                Tidak ada akun CS di cabang ini.
                            </div>
                            <button v-for="u in filteredCsUsers" :key="u.id"
                                type="button"
                                @click="selectedCsUserId = u.id; passwordVerified = false"
                                @dblclick="proceedToStep3"
                                class="w-full p-3.5 sm:p-4 rounded-2xl border-2 transition-all flex items-center justify-between gap-3 relative overflow-hidden group cursor-pointer text-left"
                                :class="selectedCsUserId === u.id ? 'border-emerald-600 bg-emerald-50 dark:bg-emerald-950/30 shadow-lg shadow-emerald-500/10 ring-2 ring-emerald-500/20' : 'border-surface-100 dark:border-surface-700 bg-surface-50 dark:bg-surface-900 hover:border-surface-300 dark:hover:border-surface-600'">
                                <div class="flex items-center gap-3 min-w-0 pr-6">
                                    <div class="w-10 h-10 sm:w-12 sm:h-12 shrink-0 rounded-full bg-surface-200 dark:bg-surface-700 flex items-center justify-center overflow-hidden border border-surface-300 dark:border-surface-600">
                                        <img v-if="getCsPhotoUrl(u)" :src="getCsPhotoUrl(u)" class="w-full h-full object-cover" alt="Profile"
                                            @error="(e) => e.target.style.display='none'" />
                                        <span class="text-xs sm:text-sm font-black text-text-secondary">{{ getInitials(u.name) }}</span>
                                    </div>
                                    <div class="flex flex-col items-start truncate min-w-0 flex-1">
                                        <span class="font-black text-xs sm:text-sm truncate w-full"
                                            :class="selectedCsUserId === u.id ? 'text-emerald-600 dark:text-emerald-400' : 'text-text-primary'">
                                            {{ u.name }}
                                        </span>
                                        <span class="text-[10px] sm:text-xs font-semibold text-text-secondary truncate w-full mt-0.5">
                                            {{ u.created_by ? 'by ' + (u.created_by.full_name || u.created_by.name) : '@' + (u.username || 'cs') }}
                                        </span>
                                    </div>
                                </div>
                                <div class="w-5 h-5 shrink-0 rounded-full border-2 flex items-center justify-center transition-all absolute top-1/2 -translate-y-1/2 right-4"
                                    :class="selectedCsUserId === u.id ? 'border-emerald-600 bg-emerald-600' : 'border-surface-300 dark:border-surface-600 bg-white dark:bg-surface-800'">
                                    <div v-if="selectedCsUserId === u.id" class="w-2 h-2 rounded-full bg-white"></div>
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center gap-3 mt-8">
                        <button type="button" @click="currentStep = 1"
                            class="px-5 h-12 sm:h-14 rounded-2xl border border-gray-200 dark:border-surface-600 font-bold text-xs sm:text-sm text-text-secondary hover:text-text-primary hover:bg-gray-50 dark:hover:bg-surface-700 transition-all flex items-center justify-center gap-2">
                            <ArrowLeft :size="16" />
                            <span>Kembali</span>
                        </button>
                        <button type="button" @click="proceedToStep3"
                            :disabled="!selectedCsUserId"
                            class="flex-1 h-12 sm:h-14 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white rounded-2xl font-black text-sm sm:text-base shadow-xl shadow-emerald-500/25 transition-all flex items-center justify-center gap-3 cursor-pointer">
                            <span>Lanjutkan ke Pemeriksaan Stok</span>
                            <ArrowRight :size="20" stroke-width="2.5" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- STEP 3: PEMERIKSAAN STOK                   -->
        <!-- ========================================== -->
        <div v-else-if="currentStep === 3" class="space-y-6">
            <!-- Active Context Header Banner -->
            <div class="bg-white dark:!bg-surface-800 p-4 sm:p-5 rounded-2xl border border-gray-100 dark:border-surface-700 shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0 border border-emerald-500/20">
                        <Building2 :size="20" />
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="text-sm sm:text-base font-black text-text-primary tracking-tight">
                                {{ activeBranch?.name || 'Cabang Terpilih' }}
                            </h3>
                            <span v-if="activeBranch?.code" class="text-[10px] font-bold px-2 py-0.5 rounded-md bg-surface-100 dark:bg-surface-700 text-text-secondary font-mono">
                                {{ activeBranch.code }}
                            </span>
                            <!-- Role Scope Pill Badge -->
                            <span v-if="isAudit" class="inline-flex items-center gap-1 text-[10px] font-black px-2.5 py-0.5 rounded-full bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800">
                                <ShieldCheck :size="12" />
                                <span>SO AUDIT</span>
                            </span>
                            <span v-else-if="isLeader" class="inline-flex items-center gap-1 text-[10px] font-black px-2.5 py-0.5 rounded-full bg-sky-100 dark:bg-sky-900/40 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800">
                                <UserCheck :size="12" />
                                <span>SO LEADER</span>
                            </span>
                            <span v-else class="inline-flex items-center gap-1 text-[10px] font-black px-2.5 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                <Store :size="12" />
                                <span>SO CS TOKO</span>
                            </span>
                        </div>
                        <p class="text-xs text-text-secondary mt-0.5 flex items-center gap-1.5 flex-wrap">
                            <span>Pelaksana:</span>
                            <strong class="text-text-primary">{{ activeCsUser?.name || 'CS' }}</strong>
                            <span class="text-[10px] font-bold uppercase px-1.5 py-0.5 rounded"
                                :class="isAudit ? 'text-purple-700 dark:text-purple-300 bg-purple-100 dark:bg-purple-900/40' : isLeader ? 'text-sky-700 dark:text-sky-300 bg-sky-100 dark:bg-sky-900/40' : 'text-emerald-700 dark:text-emerald-300 bg-emerald-100 dark:bg-emerald-900/40'">
                                {{ isAudit ? 'Audit' : isLeader ? 'Leader' : 'CS' }}
                            </span>
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 self-stretch sm:self-auto">
                    <button type="button" @click="currentStep = 2"
                        class="flex-1 sm:flex-initial flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl border border-gray-200 dark:border-surface-600 text-xs font-bold text-text-primary hover:bg-gray-100 dark:hover:bg-surface-700 hover:border-emerald-500 transition-all cursor-pointer">
                        <User :size="13" />
                        <span>Ganti CS</span>
                    </button>
                    <button type="button" @click="currentStep = 1"
                        class="flex-1 sm:flex-initial flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl border border-gray-200 dark:border-surface-600 text-xs font-bold text-text-primary hover:bg-gray-100 dark:hover:bg-surface-700 hover:border-emerald-500 transition-all cursor-pointer">
                        <Building2 :size="13" />
                        <span>Ganti Toko</span>
                    </button>
                </div>
            </div>

            <!-- Configuration & Filter Controls (Strict Sequence) -->
            <div class="bg-white dark:!bg-surface-800 p-4 sm:p-6 rounded-2xl border border-gray-100 dark:border-surface-700 shadow-sm space-y-5">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                    <div>
                        <h2 class="text-sm sm:text-base font-bold text-text-primary flex items-center gap-2">
                            <Smartphone :size="18" class="text-emerald-500" />
                            <span>Metode Pemeriksaan Stok</span>
                        </h2>
                        <span class="text-[11px] text-text-secondary flex items-center gap-1 mt-0.5">
                            <span>Siklus harian aktif • Reset otomatis pukul</span>
                            <strong class="text-emerald-600 dark:text-emerald-400">05:00 AM</strong>
                        </span>
                    </div>

                    <!-- Status Sisa Unit Belum SO -->
                    <div v-if="loadingItems" class="text-xs font-medium text-gray-400 flex items-center gap-1.5 bg-gray-50 dark:bg-surface-700/60 px-3 py-1.5 rounded-lg border border-gray-200/60 dark:border-surface-600">
                        <RefreshCw :size="13" class="animate-spin text-emerald-500" />
                        <span>Memeriksa stok sistem...</span>
                    </div>
                    <div v-else-if="totalAvailableUnits === 0 && !selectedBrand && !selectedProductName" class="text-xs font-bold px-3 py-1.5 rounded-lg border flex items-center gap-1.5"
                        :class="isAudit ? 'text-purple-700 dark:text-purple-300 bg-purple-50 dark:bg-purple-950/50 border-purple-200 dark:border-purple-800' : isLeader ? 'text-sky-700 dark:text-sky-300 bg-sky-50 dark:bg-sky-950/50 border-sky-200 dark:border-sky-800' : 'text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/50 border-emerald-200 dark:border-emerald-800'">
                        <CheckCircle2 :size="14" />
                        <span>Semua unit sudah di-{{ isAudit ? 'SO Audit' : isLeader ? 'SO Leader' : 'SO' }} hari ini (Reset 05:00 AM) ✅</span>
                    </div>
                    <div v-else class="text-xs font-medium text-text-secondary bg-amber-50 dark:bg-amber-950/40 px-3 py-1.5 rounded-lg border border-amber-200 dark:border-amber-800/60">
                        Belum di-SO hari ini: <span class="font-bold text-amber-700 dark:text-amber-300">{{ totalAvailableUnits }} unit</span>
                    </div>
                </div>

                <!-- 1. PILIH HP / NON-HP & 2. PILIH SO BY UNIT / SO BY IMEI -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- 1. PILIH HP / NON-HP -->
                    <div class="space-y-1.5">
                        <label class="block text-xs font-black text-text-secondary uppercase tracking-wider">
                            1. Pilih Kategori Barang
                        </label>
                        <div class="flex items-center p-1 bg-gray-100 dark:bg-surface-700 rounded-xl border border-gray-200 dark:border-surface-600">
                            <button type="button" @click="categoryType = 'hp'"
                                class="flex-1 flex items-center justify-center gap-2 py-2.5 text-xs sm:text-sm font-bold rounded-lg transition-all"
                                :class="categoryType === 'hp' ? 'bg-white dark:bg-surface-800 text-emerald-600 dark:text-emerald-400 shadow-sm' : 'text-text-secondary hover:text-text-primary'">
                                <Smartphone :size="16" />
                                <span>HP (IMEI)</span>
                            </button>
                            <button type="button" @click="categoryType = 'non_hp'"
                                class="flex-1 flex items-center justify-center gap-2 py-2.5 text-xs sm:text-sm font-bold rounded-lg transition-all"
                                :class="categoryType === 'non_hp' ? 'bg-white dark:bg-surface-800 text-emerald-600 dark:text-emerald-400 shadow-sm' : 'text-text-secondary hover:text-text-primary'">
                                <Package :size="16" />
                                <span>Non-HP / Aksesoris</span>
                            </button>
                        </div>
                    </div>

                    <!-- 2. PILIH SO BY UNIT / SO BY IMEI -->
                    <div v-if="categoryType === 'hp'" class="space-y-1.5">
                        <label class="block text-xs font-black text-text-secondary uppercase tracking-wider">
                            2. Pilih Metode Stok Opname
                        </label>
                        <div class="flex items-center p-1 bg-gray-100 dark:bg-surface-700 rounded-xl border border-gray-200 dark:border-surface-600">
                            <button type="button" @click="method = 'by_unit'"
                                class="flex-1 flex items-center justify-center gap-2 py-2.5 text-xs sm:text-sm font-bold rounded-lg transition-all"
                                :class="method === 'by_unit' ? 'bg-white dark:bg-surface-800 text-emerald-600 dark:text-emerald-400 shadow-sm' : 'text-text-secondary hover:text-text-primary'">
                                <Layers :size="16" />
                                <span>SO by Unit</span>
                            </button>
                            <button type="button" @click="method = 'by_imei'"
                                class="flex-1 flex items-center justify-center gap-2 py-2.5 text-xs sm:text-sm font-bold rounded-lg transition-all"
                                :class="method === 'by_imei' ? 'bg-white dark:bg-surface-800 text-emerald-600 dark:text-emerald-400 shadow-sm' : 'text-text-secondary hover:text-text-primary'">
                                <QrCode :size="16" />
                                <span>SO by IMEI</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 3A. JIKA SO BY UNIT: PILIH BRAND & PILIH KONDISI -->
                <div v-if="method === 'by_unit'" class="space-y-4 pt-3 border-t border-gray-100 dark:border-surface-700">
                    <!-- PILIH BRAND -->
                    <div>
                        <label class="block text-xs font-black text-text-secondary uppercase tracking-wider mb-2">
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

                    <!-- PILIH KONDISI (BARU / SECOND) -->
                    <div v-if="categoryType === 'hp'">
                        <label class="block text-xs font-black text-text-secondary uppercase tracking-wider mb-2">
                            Pilih Kondisi Unit
                        </label>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" @click="selectedCondition = 'all'"
                                class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all border"
                                :class="selectedCondition === 'all' ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm' : 'bg-white dark:bg-surface-700 text-text-secondary border-gray-200 dark:border-surface-600 hover:border-emerald-500'">
                                Semua Kondisi
                            </button>
                            <button type="button" @click="selectedCondition = 'new'"
                                class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all border flex items-center gap-1.5"
                                :class="selectedCondition === 'new' ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm' : 'bg-white dark:bg-surface-700 text-text-secondary border-gray-200 dark:border-surface-600 hover:border-emerald-500'">
                                <span>Baru (New)</span>
                                <span v-if="availableConditions.find(c => c.condition === 'new')" class="text-[10px] px-1.5 py-0.2 rounded-full"
                                    :class="selectedCondition === 'new' ? 'bg-emerald-700 text-white' : 'bg-gray-100 dark:bg-surface-600 text-text-secondary'">
                                    {{ availableConditions.find(c => c.condition === 'new')?.count || 0 }}
                                </span>
                            </button>
                            <button type="button" @click="selectedCondition = 'second'"
                                class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all border flex items-center gap-1.5"
                                :class="selectedCondition === 'second' ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm' : 'bg-white dark:bg-surface-700 text-text-secondary border-gray-200 dark:border-surface-600 hover:border-emerald-500'">
                                <span>Second (Bekas)</span>
                                <span v-if="availableConditions.find(c => c.condition === 'second')" class="text-[10px] px-1.5 py-0.2 rounded-full"
                                    :class="selectedCondition === 'second' ? 'bg-emerald-700 text-white' : 'bg-gray-100 dark:bg-surface-600 text-text-secondary'">
                                    {{ availableConditions.find(c => c.condition === 'second')?.count || 0 }}
                                </span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 3B. JIKA SO BY IMEI: PILIH TIPE HP (DEDICATED SELECTOR) -->
                <div v-else-if="method === 'by_imei'" class="space-y-4 pt-3 border-t border-gray-100 dark:border-surface-700">
                    <div>
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 mb-2">
                            <div>
                                <label class="block text-xs font-black text-text-secondary uppercase tracking-wider">
                                    Pilih Tipe HP
                                </label>
                                <p class="text-[11px] text-text-secondary">
                                    Pilih salah satu tipe HP untuk melakukan checklist nomor IMEI unit fisik.
                                </p>
                            </div>

                            <!-- Optional Brand Quick Filter for Types -->
                            <div class="flex flex-wrap items-center gap-1.5">
                                <button type="button" @click="selectedBrand = ''"
                                    class="px-2.5 py-1 rounded-lg text-[11px] font-bold transition-all border"
                                    :class="selectedBrand === '' ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-gray-50 dark:bg-surface-700 text-text-secondary border-gray-200 dark:border-surface-600'">
                                    Semua
                                </button>
                                <button v-for="b in availableBrands" :key="b.brand" type="button" @click="selectedBrand = b.brand"
                                    class="px-2.5 py-1 rounded-lg text-[11px] font-bold transition-all border"
                                    :class="selectedBrand === b.brand ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-gray-50 dark:bg-surface-700 text-text-secondary border-gray-200 dark:border-surface-600'">
                                    {{ b.brand }}
                                </button>
                            </div>
                        </div>

                        <!-- Active Selected Type Banner (If selected) -->
                        <div v-if="selectedProductName" class="p-3.5 sm:p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border-2 border-emerald-600 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 shadow-sm mb-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-black">
                                    <Smartphone :size="20" />
                                </div>
                                <div>
                                    <div class="text-xs uppercase font-bold text-emerald-700 dark:text-emerald-300">Tipe Terpilih</div>
                                    <div class="text-base sm:text-lg font-black text-text-primary tracking-tight">
                                        {{ selectedProductName }}
                                    </div>
                                    <div class="text-xs text-text-secondary">
                                        {{ pendingItems.length }} unit IMEI belum di-SO
                                    </div>
                                </div>
                            </div>
                            <button type="button" @click="clearSelectedType"
                                class="px-3.5 py-2 rounded-xl bg-white dark:bg-surface-800 border border-emerald-300 dark:border-emerald-700 text-xs font-bold text-emerald-700 dark:text-emerald-300 hover:bg-emerald-100 transition-all cursor-pointer self-stretch sm:self-auto flex items-center justify-center gap-1.5">
                                <RefreshCw :size="13" />
                                <span>Ganti Tipe HP</span>
                            </button>
                        </div>

                        <!-- Search Type Input -->
                        <div v-if="!selectedProductName" class="space-y-3">
                            <div class="relative max-w-md">
                                <input v-model="searchType" type="text" placeholder="Cari tipe HP (contoh: iPhone 11, Samsung S24)..."
                                    class="w-full px-4 py-2 pl-9 text-xs rounded-xl bg-surface-50 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 text-text-primary placeholder:text-text-secondary focus:border-emerald-500 focus:outline-none transition-all" />
                                <Search :size="15" class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary" />
                            </div>

                            <!-- Phone Types Cards Grid -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2.5 max-h-[300px] overflow-y-auto pr-1 custom-scrollbar">
                                <div v-if="filteredAvailableTypes.length === 0" class="col-span-full py-6 text-center text-text-secondary text-xs">
                                    Tidak ada tipe HP yang perlu di-SO untuk filter ini.
                                </div>
                                <button v-for="t in filteredAvailableTypes" :key="t.product_name"
                                    type="button"
                                    @click="selectType(t.product_name)"
                                    class="p-3 rounded-xl border transition-all text-left flex items-center justify-between gap-2.5 group cursor-pointer"
                                    :class="selectedProductName === t.product_name ? 'border-emerald-600 bg-emerald-50 dark:bg-emerald-950/40' : 'border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-900 hover:border-emerald-500 hover:bg-emerald-50/30'">
                                    <div class="min-w-0 flex-1">
                                        <div class="font-bold text-xs text-text-primary truncate group-hover:text-emerald-600 transition-colors">
                                            {{ t.product_name }}
                                        </div>
                                        <div class="text-[10px] text-text-secondary font-medium">
                                            {{ t.brand || 'HP' }}
                                        </div>
                                    </div>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 dark:bg-emerald-900/60 text-emerald-800 dark:text-emerald-300 shrink-0">
                                        {{ t.count }} unit
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section: Daftar Barang yang Di-SO (Tabel by Unit ATAU Checklist by IMEI) -->
            <div class="bg-white dark:!bg-surface-800 p-4 sm:p-6 rounded-2xl border border-gray-100 dark:border-surface-700 shadow-sm space-y-4">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                    <div>
                        <h2 class="text-base font-bold text-text-primary">
                            {{ method === 'by_imei' ? 'Checklist IMEI Fisik' : 'Perhitungan Unit Fisik' }}
                        </h2>
                        <p class="text-xs text-text-secondary mt-0.5">
                            {{ method === 'by_imei' ? 'Tandai Ada ✅ jika unit fisik ditemukan di toko, atau ❌ jika tidak ada' : 'Masukkan jumlah fisik unit yang ditemukan di toko' }}
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
                    <span class="text-xs font-medium">Memuat data stok sistem...</span>
                </div>

                <!-- Empty State -->
                <div v-else-if="pendingItems.length === 0" class="py-12 text-center text-text-secondary bg-gray-50/50 dark:bg-surface-900/30 rounded-xl border border-dashed border-gray-200 dark:border-surface-700 px-4">
                    <template v-if="totalAvailableUnits === 0 && !selectedBrand && !selectedProductName">
                        <CheckCircle2 :size="38" class="mx-auto text-emerald-500 mb-2" />
                        <p class="text-base font-bold text-text-primary">
                            Semua Stok Sudah Selesai di-{{ isAudit ? 'SO Audit' : isLeader ? 'SO Leader' : 'SO' }} Hari Ini ✅
                        </p>
                        <p class="text-xs text-text-secondary mt-1 max-w-md mx-auto leading-relaxed">
                            Seluruh unit fisik di cabang ini telah berhasil diperiksa oleh tim {{ isAudit ? 'Audit' : isLeader ? 'Leader' : 'Toko' }} dan tercatat di sistem. Sesi SO baru akan aktif otomatis besok pukul <strong>05:00 AM</strong>.
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
                        <Smartphone :size="32" class="mx-auto text-emerald-500/80 mb-2" />
                        <p class="text-sm font-bold text-text-primary">
                            Silakan pilih Tipe HP di atas untuk memulai checklist IMEI
                        </p>
                        <p class="text-xs text-text-secondary mt-1 max-w-md mx-auto">
                            Klik salah satu kartu tipe HP di bagian <strong>Pilih Tipe HP</strong> untuk menampilkan seluruh nomor IMEI yang tersedia.
                        </p>
                    </template>
                    <template v-else>
                        <Package :size="32" class="mx-auto text-gray-300 dark:text-gray-600 mb-2" />
                        <p class="text-sm font-semibold text-text-primary">
                            Tidak ada barang yang perlu di-SO untuk filter ini
                        </p>
                        <p class="text-xs text-text-secondary mt-1 max-w-md mx-auto">
                            Semua barang untuk filter ini sudah selesai di-SO hari ini, atau stok di cabang ini sedang kosong.
                        </p>
                    </template>
                </div>

                <!-- CASE A: STOK BY UNIT (TABEL DENGAN KOLOM KONDISI) -->
                <div v-else-if="method === 'by_unit'" class="overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[700px]">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-surface-700 bg-gray-50/80 dark:bg-surface-700/40 text-[11px] font-bold text-text-secondary uppercase">
                                <th class="py-3 px-3">Brand & Tipe Produk</th>
                                <th class="py-3 px-3 text-center">Kondisi</th>
                                <th class="py-3 px-3 text-center">Kapasitas</th>
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
                                <td class="py-3 px-3 text-center">
                                    <span v-if="it.condition === 'new'" class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                        Baru
                                    </span>
                                    <span v-else class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                        Second
                                    </span>
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

                <!-- CASE B: STOK BY IMEI (CHECKLIST CARDS) -->
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
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold"
                                        :class="it.condition === 'new' ? 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300' : 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300'">
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

            <!-- Section: Barang Lebih (Surplus) -->
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

            <!-- Section: Foto Bukti SO & Catatan Umum -->
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
                            <span>Sudah Selesai di-SO Hari Ini (Reset 05:00 AM)</span>
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
