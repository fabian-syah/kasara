<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import {
    Smartphone, Package, Check, X, CheckCircle2, XCircle, ClipboardCheck,
    Camera, Upload, Plus, Trash2, AlertTriangle, Building2,
    User, Lock, ShieldCheck, History, ArrowLeft, ChevronDown,
    RefreshCw, Search, Eye, ArrowRight, Shield, UserCheck, Store,
    Layers, QrCode, Sparkles, Filter, CheckCheck, RotateCcw
} from 'lucide-vue-next';
import axios from '../../api/axios';
import { useToast } from '../../composables/useToast';
import { useAuthStore } from '../../store/auth';
import PasswordModal from '../../components/modals/PasswordModal.vue';

const router = useRouter();
const toast = useToast();
const authStore = useAuthStore();

// Loading states
const loadingBranches = ref(false);
const loadingCs = ref(false);
const loadingItems = ref(false);
const submitting = ref(false);

// Step Navigation:
// Step 1: Pilih Toko
// Step 2: Pilih CS
// Step 3: Pilih HP / Non-HP
// Step 4: Pilih SO by unit / SO by IMEI (Khusus HP)
// Step 5: Pilih Brand & Kondisi (Multi-select) / Pilih Tipe HP
// Step 6: Pemeriksaan Fisik (Tabel Unit / Checklist IMEI)
const currentStep = ref(1);

// Step 1: Branch Selection
const branches = ref([]);
const selectedBranchId = ref(null);
const searchBranch = ref('');

// Step 2: CS Selection
const csUsers = ref([]);
const selectedCsUserId = ref(null);
const searchCs = ref('');

// Step 3 & 4: Configuration
const categoryType = ref('hp'); // 'hp' | 'non_hp'
const method = ref('by_unit'); // 'by_unit' | 'by_imei'

// Step 5: Multi-select Brand and Condition
const selectedBrands = ref([]); // Array of strings (e.g. ['Apple', 'Samsung']). Empty = Semua Brand.
const selectedConditions = ref([]); // Array of strings (e.g. ['new', 'second', 'ex_ibox']). Empty = Belum Dipilih / Semua.
const selectedProductName = ref(''); // For SO by IMEI
const searchType = ref(''); // Search bar for phone model in SO by IMEI

// Server Data
const availableBrands = ref([]);
const availableConditions = ref([]);
const availableTypes = ref([]);
const pendingItems = ref([]);
const totalAvailableUnits = ref(0);

// Password verification for non-CS accounts (akun toko, etc.)
const showPasswordModal = ref(false);
const passwordModalMode = ref('password'); // 'password' or 'alert'
const passwordModalError = ref('');
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

const isCsLogin = computed(() => {
    return (typeof authStore.hasRole === 'function' && authStore.hasRole('inventory')) || 
           (authStore.userRole || '').toLowerCase() === 'inventory' ||
           (authStore.user?.roles?.[0]?.name || '').toLowerCase() === 'inventory';
});

const isStoreAccount = computed(() => {
    if (isCsLogin.value && authStore.user?.id === selectedCsUserId.value) return false;
    if (isLeaderOrAudit.value || isPrivilegedRole.value) return false;
    return true;
});

// Stepper Progress Definitions
const activeSteps = computed(() => {
    if (categoryType.value === 'non_hp') {
        return [
            { step: 1, label: 'Toko', short: 'Toko' },
            { step: 2, label: 'CS Pelaksana', short: 'CS' },
            { step: 3, label: 'Kategori', short: 'Kat' },
            { step: 5, label: 'Pilih Brand', short: 'Brand' },
            { step: 6, label: 'Pemeriksaan Fisik', short: 'Fisik' },
        ];
    }
    return [
        { step: 1, label: 'Toko', short: 'Toko' },
        { step: 2, label: 'CS Pelaksana', short: 'CS' },
        { step: 3, label: 'HP / Non-HP', short: 'Kat' },
        { step: 4, label: 'Unit / IMEI', short: 'Metode' },
        { step: 5, label: method.value === 'by_imei' ? 'Tipe HP' : 'Brand & Kondisi', short: 'Filter' },
        { step: 6, label: 'Pemeriksaan Fisik', short: 'Fisik' },
    ];
});

const currentStepIndex = computed(() => {
    return activeSteps.value.findIndex(s => s.step === currentStep.value);
});

const currentStepMeta = computed(() => {
    return activeSteps.value[currentStepIndex.value] || activeSteps.value[0];
});

const progressPercentage = computed(() => {
    const list = activeSteps.value;
    const curIdx = currentStepIndex.value;
    if (curIdx <= 0) return 0;
    return Math.min(100, Math.round((curIdx / (list.length - 1)) * 100));
});

// Filter Helpers
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
    if (selectedBrands.value.length > 0) {
        list = list.filter(t => !t.brand || selectedBrands.value.some(b => b.toLowerCase() === t.brand.toLowerCase()));
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

// API Fetching
const fetchBranches = async () => {
    loadingBranches.value = true;
    try {
        const res = await axios.get('/inventory/opname/branches');
        branches.value = res.data.data || [];

        if (branches.value.length > 0) {
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

        if (selectedBrands.value.length > 0) {
            params.brands = selectedBrands.value.join(',');
        }
        if (selectedConditions.value.length > 0) {
            params.conditions = selectedConditions.value.join(',');
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
                is_present: item.is_present !== undefined && item.is_present !== null ? item.is_present : null, // Default: Belum dipilih (null)
                notes: item.notes || ''
            }));
        } else {
            pendingItems.value = (res.data.items || []).map(item => ({
                ...item,
                physical_qty: 0, // Default: NOL!
                difference: 0 - (Number(item.system_qty) || 0),
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

// Wizard Step Navigation Transitions
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
        if (!selectedBranchId.value || !selectedCsUserId.value) {
            toast.warning('Silakan pilih toko & CS terlebih dahulu.');
            return;
        }
        currentStep.value = 3;
    } else if (step === 4) {
        if (categoryType.value !== 'hp') return;
        currentStep.value = 4;
    } else if (step === 5) {
        currentStep.value = 5;
    } else if (step === 6) {
        if (method.value === 'by_imei' && !selectedProductName.value) {
            toast.warning('Silakan pilih tipe HP terlebih dahulu.');
            return;
        }
        currentStep.value = 6;
    }
};

// Step Transitions
const proceedFromStep1 = async () => {
    if (!selectedBranchId.value) {
        toast.warning('Silakan pilih toko / cabang terlebih dahulu.');
        return;
    }
    await fetchCsUsers();
    currentStep.value = 2;
};

const proceedFromStep2 = () => {
    if (!selectedCsUserId.value) {
        toast.warning('Silakan pilih akun CS pelaksana.');
        return;
    }

    // Jika login sebagai akun CS dan memilih akunnya sendiri: tidak perlu verifikasi password
    if (isCsLogin.value && authStore.user?.id === selectedCsUserId.value) {
        passwordVerified.value = true;
        currentStep.value = 3;
        return;
    }

    // Jika menggunakan akun non-CS (akun toko offline, admin toko, dll.): verifikasi password CS seperti di penjualan toko
    if (!passwordVerified.value) {
        passwordModalMode.value = 'password';
        passwordModalError.value = '';
        showPasswordModal.value = true;
        return;
    }

    currentStep.value = 3;
};

const handlePasswordSuccess = async (password) => {
    passwordModalError.value = '';
    verifyingPassword.value = true;
    try {
        const res = await axios.post('/verify-password', {
            password: password,
            inventory_user_id: selectedCsUserId.value
        });

        if (res.data?.success) {
            passwordVerified.value = true;
            csPassword.value = password;
            showPasswordModal.value = false;
            toast.success('Verifikasi CS berhasil! ✅');
            if (currentStep.value === 2) {
                currentStep.value = 3;
            } else if (currentStep.value === 6) {
                await submitOpname();
            }
        } else {
            passwordModalError.value = res.data?.message || 'Password CS salah.';
        }
    } catch (err) {
        passwordModalError.value = err.response?.data?.message || 'Password verifikasi CS salah.';
    } finally {
        verifyingPassword.value = false;
    }
};

const selectCategory = (cat) => {
    categoryType.value = cat;
    selectedBrands.value = [];
    selectedConditions.value = [];
    selectedProductName.value = '';
    searchType.value = '';

    if (cat === 'hp') {
        currentStep.value = 4; // Go to Choose Method
    } else {
        method.value = 'by_unit';
        fetchPendingItems();
        currentStep.value = 5; // Go to Brand selection for non-hp
    }
};

const selectMethod = (m) => {
    method.value = m;
    selectedProductName.value = '';
    selectedBrands.value = [];
    selectedConditions.value = [];
    searchType.value = '';
    fetchPendingItems();
    currentStep.value = 5;
};

// Multi-select Brand Toggles
const isBrandSelected = (bName) => {
    if (!bName) return selectedBrands.value.length === 0;
    return selectedBrands.value.includes(bName);
};

const toggleBrand = (bName) => {
    if (!bName) {
        selectedBrands.value = [];
    } else {
        const idx = selectedBrands.value.indexOf(bName);
        if (idx > -1) {
            selectedBrands.value.splice(idx, 1);
        } else {
            selectedBrands.value.push(bName);
        }
    }
    fetchPendingItems();
};

const selectAllBrands = () => {
    selectedBrands.value = (availableBrands.value || []).map(b => b.brand);
    fetchPendingItems();
};

const resetBrands = () => {
    selectedBrands.value = [];
    fetchPendingItems();
};

// Multi-select Condition Toggles
const isConditionSelected = (cKey) => {
    if (!cKey || cKey === 'all') return selectedConditions.value.length === 0;
    return selectedConditions.value.includes(cKey);
};

const toggleCondition = (cKey) => {
    if (!cKey || cKey === 'all') {
        selectedConditions.value = [];
    } else {
        const idx = selectedConditions.value.indexOf(cKey);
        if (idx > -1) {
            selectedConditions.value.splice(idx, 1);
        } else {
            selectedConditions.value.push(cKey);
        }
    }
    fetchPendingItems();
};

const proceedFromStep5Unit = async () => {
    await fetchPendingItems();
    currentStep.value = 6;
};

const selectTypeAndProceed = async (typeName) => {
    selectedProductName.value = typeName;
    await fetchPendingItems();
    currentStep.value = 6;
};

const changeSelectedType = () => {
    selectedProductName.value = '';
    currentStep.value = 5;
};

// Watchers
watch(selectedBranchId, () => {
    selectedCsUserId.value = null;
    selectedBrands.value = [];
    selectedConditions.value = [];
    selectedProductName.value = '';
    fetchCsUsers();
});

// Physical Calculation
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
    if (status === true) {
        toast.info('Semua unit ditandai Ada ✅');
    } else if (status === false) {
        toast.info('Semua unit ditandai Tidak Ada ❌');
    } else {
        toast.info('Status checklist direset ke Belum Dipilih ⏳');
    }
};

const summaryStats = computed(() => {
    let sys = 0;
    let phys = 0;
    let missing = 0;
    let unselected = 0;

    if (method.value === 'by_imei') {
        sys = pendingItems.value.length;
        pendingItems.value.forEach(it => {
            if (it.is_present === true) phys++;
            else if (it.is_present === false) missing++;
            else unselected++;
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
        totalUnselected: unselected,
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
        brand: selectedBrands.value[0] || '',
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

    if (method.value === 'by_imei' && summaryStats.value.totalUnselected > 0) {
        const confirmMsg = `Terdapat ${summaryStats.value.totalUnselected} unit IMEI yang statusnya Belum Dipilih (belum diperiksa Ada / Tidak Ada).\n\nApakah unit yang belum dipilih dianggap Tidak Ada dan ingin melanjutkan simpan?`;
        if (!confirm(confirmMsg)) {
            return;
        }
    }

    if (isStoreAccount.value && !passwordVerified.value) {
        passwordModalMode.value = 'password';
        passwordModalError.value = '';
        showPasswordModal.value = true;
        return;
    }

    await submitOpname();
};

const submitOpname = async () => {
    submitting.value = true;
    try {
        const payload = {
            branch_id: Number(selectedBranchId.value),
            cs_user_id: Number(selectedCsUserId.value),
            category_type: categoryType.value,
            method: method.value,
            brand: selectedBrands.value.length > 0 ? selectedBrands.value.join(', ') : null,
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
    <div class="space-y-5 sm:space-y-6 pb-12 sm:pb-8 max-w-7xl mx-auto px-2 sm:px-4">
        <!-- Top AI Header Bar -->
        <div class="relative overflow-hidden rounded-2xl sm:rounded-3xl bg-gradient-to-r from-surface-900 via-surface-850 to-surface-900 border border-emerald-500/20 p-4 sm:p-6 shadow-xl shadow-black/20">
            <div class="absolute -right-16 -top-16 w-60 h-60 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -left-16 -bottom-16 w-60 h-60 bg-teal-500/10 rounded-full blur-3xl pointer-events-none"></div>

            <div class="relative z-10 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <div class="flex items-center gap-2 mb-2 flex-wrap">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-black bg-gradient-to-r from-emerald-500/15 to-teal-500/15 border border-emerald-500/30 text-emerald-400 shadow-sm backdrop-blur-md">
                            <Sparkles :size="13" class="text-emerald-400 animate-pulse" />
                            <span>KASARA AI • Smart Stock Opname</span>
                        </span>
                        <span class="text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-amber-500/15 text-amber-300 border border-amber-500/30">
                            Mode Harian (Reset 05:00 AM)
                        </span>
                    </div>

                    <div class="flex items-center gap-2.5">
                        <button @click="router.back()" class="p-2 rounded-xl text-surface-400 hover:text-white hover:bg-surface-800 transition-colors cursor-pointer">
                            <ArrowLeft :size="20" />
                        </button>
                        <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight">
                            Pemeriksaan Fisik Stok Toko
                        </h1>
                    </div>
                    <p class="text-surface-400 text-xs sm:text-sm mt-1 ml-10">
                        Pencatatan real-time audit fisik unit HP & aksesoris di cabang toko.
                    </p>
                </div>

                <div class="flex items-center gap-2 w-full md:w-auto">
                    <router-link to="/inventory/opname/history"
                        class="flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl border border-surface-700 bg-surface-800/80 hover:bg-surface-700 text-xs sm:text-sm font-bold text-white hover:border-emerald-500 transition-all shadow-sm w-full md:w-auto">
                        <History :size="16" class="text-emerald-400" />
                        <span>Riwayat / History SO</span>
                    </router-link>
                </div>
            </div>
        </div>

        <!-- AI PIPELINE STEPPER -->
        <!-- Desktop / Tablet View (Horizontal Node Stepper) -->
        <div class="hidden sm:block bg-surface-900/90 dark:bg-surface-900/90 backdrop-blur-xl rounded-2xl sm:rounded-3xl p-4 border border-surface-750 shadow-lg">
            <div class="flex items-center justify-between relative px-4 max-w-4xl mx-auto">
                <div v-for="(s, sIdx) in activeSteps" :key="s.step" class="z-10 flex flex-col items-center gap-2 flex-1 group">
                    <button type="button" @click="goToStep(s.step)"
                        class="w-10 h-10 rounded-2xl flex items-center justify-center font-black transition-all cursor-pointer text-xs relative"
                        :class="currentStep === s.step
                            ? 'bg-gradient-to-tr from-emerald-600 to-teal-500 text-white shadow-lg shadow-emerald-500/30 ring-4 ring-emerald-500/25 scale-110'
                            : s.step < currentStep
                            ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/40 hover:bg-emerald-500/30'
                            : 'bg-surface-800 text-surface-500 border border-surface-700/60 opacity-60'">
                        <CheckCircle2 v-if="s.step < currentStep" :size="18" class="text-emerald-400" />
                        <span v-else>{{ sIdx + 1 }}</span>
                    </button>
                    <span class="text-[11px] font-black uppercase tracking-wider text-center truncate max-w-[110px]"
                        :class="currentStep === s.step ? 'text-emerald-400 font-extrabold' : 'text-surface-400'">
                        {{ s.label }}
                    </span>
                </div>
                <!-- Connected Glow Line -->
                <div class="absolute left-10 right-10 top-5 -translate-y-1/2 h-1 -z-0 bg-surface-800 rounded-full overflow-hidden">
                    <div class="h-full bg-gradient-to-r from-emerald-500 via-teal-400 to-emerald-400 transition-all duration-500 shadow-sm shadow-emerald-500"
                        :style="{ width: progressPercentage + '%' }"></div>
                </div>
            </div>
        </div>

        <!-- Mobile View (Compact AI Pill Stepper) -->
        <div class="block sm:hidden bg-surface-900/95 backdrop-blur-xl rounded-2xl p-3 border border-surface-800 shadow-lg space-y-2">
            <div class="flex items-center justify-between text-xs">
                <div class="flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center font-black text-[11px] border border-emerald-500/30">
                        {{ currentStepIndex + 1 }}
                    </span>
                    <span class="font-bold text-white text-xs tracking-tight">
                        {{ currentStepMeta.label }}
                    </span>
                </div>
                <span class="text-[10px] font-mono font-bold text-emerald-400">
                    Langkah {{ currentStepIndex + 1 }} / {{ activeSteps.length }}
                </span>
            </div>
            <!-- Progress Line -->
            <div class="w-full bg-surface-800 h-1.5 rounded-full overflow-hidden">
                <div class="h-full bg-gradient-to-r from-emerald-500 to-teal-400 transition-all duration-300"
                    :style="{ width: progressPercentage + '%' }"></div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- STEP 1: PILIH TOKO                         -->
        <!-- ========================================== -->
        <div v-if="currentStep === 1" class="flex items-center justify-center py-2 sm:py-6">
            <div class="w-full max-w-2xl bg-gradient-to-b from-surface-850 to-surface-900 rounded-3xl border border-surface-750 p-5 sm:p-10 shadow-2xl relative overflow-hidden">
                <div class="absolute -top-24 -right-24 w-72 h-72 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>

                <div class="relative z-10">
                    <div class="w-16 h-16 sm:w-20 sm:h-20 bg-gradient-to-br from-emerald-500/20 to-teal-500/10 border border-emerald-500/30 rounded-3xl flex items-center justify-center mb-5 mx-auto ring-8 ring-emerald-500/10 shadow-lg shadow-emerald-500/10">
                        <Building2 class="text-emerald-400" :size="36" stroke-width="2.5" />
                    </div>

                    <h2 class="text-xl sm:text-3xl font-black text-white text-center mb-2">
                        1. Pilih Toko / Cabang
                    </h2>
                    <p class="text-surface-400 text-center mb-6 font-medium text-xs sm:text-sm max-w-md mx-auto">
                        Pilih lokasi cabang toko tempat pemeriksaan stok fisik akan dilakukan.
                    </p>

                    <div class="space-y-4">
                        <!-- List Header -->
                        <div class="flex items-center justify-between px-1">
                            <label class="block text-xs font-black text-surface-400 uppercase tracking-widest">
                                Daftar Cabang Toko
                            </label>
                            <span class="text-xs text-emerald-400 font-bold font-mono">
                                {{ filteredBranches.length }} cabang
                            </span>
                        </div>

                        <!-- Search Input -->
                        <div class="relative">
                            <input v-model="searchBranch" type="text" placeholder="Cari nama toko atau kode cabang..."
                                class="w-full px-4 py-3 pl-10 text-xs sm:text-sm rounded-xl bg-surface-800/80 border border-surface-700 text-white placeholder:text-surface-500 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none transition-all" />
                            <Search :size="16" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-surface-400" />
                        </div>

                        <!-- Grid of Branches -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-[340px] overflow-y-auto pr-1 custom-scrollbar">
                            <div v-if="loadingBranches" class="col-span-full py-10 text-center text-surface-400 text-xs flex items-center justify-center gap-2">
                                <RefreshCw :size="16" class="animate-spin text-emerald-500" />
                                <span>Memuat daftar toko...</span>
                            </div>
                            <div v-else-if="filteredBranches.length === 0" class="col-span-full py-10 text-center text-surface-400 text-xs">
                                Tidak ada toko yang sesuai pencarian.
                            </div>
                            <button v-for="b in filteredBranches" :key="b.id"
                                type="button"
                                @click="selectedBranchId = b.id"
                                @dblclick="proceedFromStep1"
                                class="w-full p-3.5 sm:p-4 rounded-2xl border-2 transition-all flex items-center justify-between gap-3 relative overflow-hidden group cursor-pointer text-left"
                                :class="selectedBranchId === b.id
                                    ? 'border-emerald-500 bg-emerald-500/10 shadow-lg shadow-emerald-500/10 ring-2 ring-emerald-500/30'
                                    : 'border-surface-750 bg-surface-800/70 hover:border-surface-600 hover:bg-surface-800'">
                                <div class="flex items-center gap-3 min-w-0 pr-6">
                                    <div class="w-10 h-10 sm:w-11 sm:h-11 shrink-0 rounded-xl bg-surface-700/80 flex items-center justify-center font-black text-xs text-white border border-surface-600">
                                        {{ b.code ? b.code.substring(0, 3).toUpperCase() : getInitials(b.name) }}
                                    </div>
                                    <div class="flex flex-col items-start truncate min-w-0">
                                        <span class="font-black text-xs sm:text-sm truncate w-full"
                                            :class="selectedBranchId === b.id ? 'text-emerald-400' : 'text-white'">
                                            {{ b.name }}
                                        </span>
                                        <span class="text-[10px] sm:text-xs font-semibold text-surface-400 truncate w-full mt-0.5 font-mono">
                                            Kode: {{ b.code || '-' }}
                                        </span>
                                    </div>
                                </div>
                                <div class="w-5 h-5 shrink-0 rounded-full border-2 flex items-center justify-center transition-all absolute top-1/2 -translate-y-1/2 right-4"
                                    :class="selectedBranchId === b.id ? 'border-emerald-500 bg-emerald-500' : 'border-surface-600 bg-surface-800'">
                                    <div v-if="selectedBranchId === b.id" class="w-2 h-2 rounded-full bg-white"></div>
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- Continue to Step 2 Button -->
                    <button type="button" @click="proceedFromStep1"
                        :disabled="!selectedBranchId"
                        class="w-full mt-7 h-12 sm:h-14 bg-gradient-to-r from-emerald-600 to-teal-500 hover:from-emerald-500 hover:to-teal-400 disabled:opacity-50 text-white rounded-2xl font-black text-sm sm:text-base shadow-xl shadow-emerald-500/25 transition-all flex items-center justify-center gap-3 cursor-pointer">
                        <span>Lanjutkan ke Pilih CS</span>
                        <ArrowRight :size="20" stroke-width="2.5" />
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- STEP 2: PILIH CS                           -->
        <!-- ========================================== -->
        <div v-else-if="currentStep === 2" class="flex items-center justify-center py-2 sm:py-6">
            <div class="w-full max-w-2xl bg-gradient-to-b from-surface-850 to-surface-900 rounded-3xl border border-surface-750 p-5 sm:p-10 shadow-2xl relative overflow-hidden">
                <div class="absolute -top-24 -right-24 w-72 h-72 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>

                <div class="relative z-10">
                    <!-- Selected Branch Banner -->
                    <div class="mb-5 p-3.5 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <Building2 :size="18" class="text-emerald-400 shrink-0" />
                            <div class="truncate text-xs">
                                <span class="text-surface-400">Toko:</span>
                                <strong class="text-white ml-1 font-black">{{ activeBranch?.name || 'Cabang Terpilih' }}</strong>
                                <span v-if="activeBranch?.code" class="ml-1.5 px-1.5 py-0.2 rounded text-[10px] bg-emerald-950/80 text-emerald-300 font-mono border border-emerald-500/30">
                                    {{ activeBranch.code }}
                                </span>
                            </div>
                        </div>
                        <button type="button" @click="currentStep = 1"
                            class="text-xs font-bold text-emerald-400 hover:underline shrink-0 flex items-center gap-1 cursor-pointer">
                            <ArrowLeft :size="13" />
                            <span>Ganti Toko</span>
                        </button>
                    </div>

                    <div class="w-16 h-16 sm:w-20 sm:h-20 bg-gradient-to-br from-emerald-500/20 to-teal-500/10 border border-emerald-500/30 rounded-3xl flex items-center justify-center mb-5 mx-auto ring-8 ring-emerald-500/10 shadow-lg shadow-emerald-500/10">
                        <Shield class="text-emerald-400" :size="36" stroke-width="2.5" />
                    </div>

                    <h2 class="text-xl sm:text-3xl font-black text-white text-center mb-2">
                        2. Pilih Akun CS Pelaksana
                    </h2>
                    <p class="text-surface-400 text-center mb-6 font-medium text-xs sm:text-sm max-w-md mx-auto">
                        Pilih staf Customer Service yang bertugas atau bertanggung jawab pada SO ini.
                    </p>

                    <!-- Self-Audit Option for Leader / Audit / Admin -->
                    <div v-if="isLeaderOrAudit || isPrivilegedRole" class="mb-4 p-3.5 rounded-2xl border-2 transition-all flex items-center justify-between gap-3 cursor-pointer"
                        :class="selectedCsUserId === authStore.user?.id ? 'border-purple-500 bg-purple-500/15 ring-2 ring-purple-500/30' : 'border-surface-750 bg-surface-800/70'"
                        @click="selectedCsUserId = authStore.user?.id; passwordVerified = true">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-purple-900/60 text-purple-300 flex items-center justify-center font-black text-xs border border-purple-500/40">
                                <ShieldCheck :size="20" />
                            </div>
                            <div>
                                <div class="text-xs font-bold text-white flex items-center gap-1.5">
                                    <span>{{ authStore.user?.name || authStore.userName }} (Saya Sendiri)</span>
                                    <span class="text-[10px] px-2 py-0.5 rounded-full bg-purple-900 text-purple-300 font-bold uppercase border border-purple-500/40">
                                        {{ isAudit ? 'Audit' : isLeader ? 'Leader' : 'Admin' }}
                                    </span>
                                </div>
                                <div class="text-[11px] text-surface-400">Audit stok atas nama sendiri tanpa verifikasi password CS</div>
                            </div>
                        </div>
                        <div class="w-5 h-5 shrink-0 rounded-full border-2 flex items-center justify-center"
                            :class="selectedCsUserId === authStore.user?.id ? 'border-purple-500 bg-purple-500' : 'border-surface-600 bg-surface-800'">
                            <div v-if="selectedCsUserId === authStore.user?.id" class="w-2 h-2 rounded-full bg-white"></div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <!-- List Header -->
                        <div class="flex items-center justify-between px-1">
                            <label class="block text-xs font-black text-surface-400 uppercase tracking-widest">
                                Daftar CS di {{ activeBranch?.name || 'Toko' }}
                            </label>
                            <span class="text-xs text-emerald-400 font-bold font-mono">
                                {{ filteredCsUsers.length }} akun
                            </span>
                        </div>

                        <!-- Search Input -->
                        <div class="relative">
                            <input v-model="searchCs" type="text" placeholder="Cari nama CS..."
                                class="w-full px-4 py-3 pl-10 text-xs sm:text-sm rounded-xl bg-surface-800/80 border border-surface-700 text-white placeholder:text-surface-500 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none transition-all" />
                            <Search :size="16" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-surface-400" />
                        </div>

                        <!-- Grid of CS Accounts -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-[340px] overflow-y-auto pr-1 custom-scrollbar">
                            <div v-if="loadingCs" class="col-span-full py-10 text-center text-surface-400 text-xs flex items-center justify-center gap-2">
                                <RefreshCw :size="16" class="animate-spin text-emerald-500" />
                                <span>Memuat daftar akun CS...</span>
                            </div>
                            <div v-else-if="filteredCsUsers.length === 0" class="col-span-full py-10 text-center text-surface-400 text-xs">
                                Tidak ada akun CS di cabang ini.
                            </div>
                            <button v-for="u in filteredCsUsers" :key="u.id"
                                type="button"
                                @click="selectedCsUserId = u.id; passwordVerified = (isCsLogin && authStore.user?.id === u.id); csPassword = ''"
                                @dblclick="proceedFromStep2"
                                class="w-full p-3.5 sm:p-4 rounded-2xl border-2 transition-all flex items-center justify-between gap-3 relative overflow-hidden group cursor-pointer text-left"
                                :class="selectedCsUserId === u.id
                                    ? 'border-emerald-500 bg-emerald-500/10 shadow-lg shadow-emerald-500/10 ring-2 ring-emerald-500/30'
                                    : 'border-surface-750 bg-surface-800/70 hover:border-surface-600 hover:bg-surface-800'">
                                <div class="flex items-center gap-3 min-w-0 pr-6">
                                    <div class="w-10 h-10 sm:w-12 sm:h-12 shrink-0 rounded-full bg-surface-700 flex items-center justify-center overflow-hidden border border-surface-600">
                                        <img v-if="getCsPhotoUrl(u)" :src="getCsPhotoUrl(u)" class="w-full h-full object-cover" alt="Profile"
                                            @error="(e) => e.target.style.display='none'" />
                                        <span class="text-xs sm:text-sm font-black text-surface-300">{{ getInitials(u.name) }}</span>
                                    </div>
                                    <div class="flex flex-col items-start truncate min-w-0 flex-1">
                                        <span class="font-black text-xs sm:text-sm truncate w-full"
                                            :class="selectedCsUserId === u.id ? 'text-emerald-400' : 'text-white'">
                                            {{ u.name }}
                                        </span>
                                        <span class="text-[10px] sm:text-xs font-semibold text-surface-400 truncate w-full mt-0.5">
                                            {{ u.created_by ? 'by ' + (u.created_by.full_name || u.created_by.name) : '@' + (u.username || 'cs') }}
                                        </span>
                                    </div>
                                </div>
                                <div class="w-5 h-5 shrink-0 rounded-full border-2 flex items-center justify-center transition-all absolute top-1/2 -translate-y-1/2 right-4"
                                    :class="selectedCsUserId === u.id ? 'border-emerald-500 bg-emerald-500' : 'border-surface-600 bg-surface-800'">
                                    <div v-if="selectedCsUserId === u.id" class="w-2 h-2 rounded-full bg-white"></div>
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center gap-3 mt-7">
                        <button type="button" @click="currentStep = 1"
                            class="px-5 h-12 sm:h-14 rounded-2xl border border-surface-700 font-bold text-xs sm:text-sm text-surface-400 hover:text-white hover:bg-surface-800 transition-all flex items-center justify-center gap-2 cursor-pointer">
                            <ArrowLeft :size="16" />
                            <span>Kembali</span>
                        </button>
                        <button type="button" @click="proceedFromStep2"
                            :disabled="!selectedCsUserId"
                            class="flex-1 h-12 sm:h-14 bg-gradient-to-r from-emerald-600 to-teal-500 hover:from-emerald-500 hover:to-teal-400 disabled:opacity-50 text-white rounded-2xl font-black text-sm sm:text-base shadow-xl shadow-emerald-500/25 transition-all flex items-center justify-center gap-3 cursor-pointer">
                            <span>Lanjut Pilih Kategori</span>
                            <ArrowRight :size="20" stroke-width="2.5" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- STEP 3: PILIH HP / NON-HP                  -->
        <!-- ========================================== -->
        <div v-else-if="currentStep === 3" class="flex items-center justify-center py-2 sm:py-6">
            <div class="w-full max-w-2xl bg-gradient-to-b from-surface-850 to-surface-900 rounded-3xl border border-surface-750 p-5 sm:p-10 shadow-2xl relative overflow-hidden">
                <div class="absolute -top-24 -right-24 w-72 h-72 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>

                <div class="relative z-10">
                    <!-- Context Pill -->
                    <div class="mb-5 p-3 rounded-2xl bg-surface-800/80 border border-surface-700 flex items-center justify-between text-xs">
                        <div class="truncate">
                            <span class="text-surface-400">Toko:</span>
                            <strong class="text-white ml-1 font-bold">{{ activeBranch?.name }}</strong>
                            <span class="mx-2 text-surface-600">•</span>
                            <span class="text-surface-400">CS:</span>
                            <strong class="text-white ml-1 font-bold">{{ activeCsUser?.name }}</strong>
                        </div>
                        <button type="button" @click="currentStep = 2" class="text-emerald-400 font-bold hover:underline cursor-pointer">
                            Ganti CS
                        </button>
                    </div>

                    <div class="w-16 h-16 sm:w-20 sm:h-20 bg-gradient-to-br from-emerald-500/20 to-teal-500/10 border border-emerald-500/30 rounded-3xl flex items-center justify-center mb-5 mx-auto ring-8 ring-emerald-500/10 shadow-lg shadow-emerald-500/10">
                        <Smartphone class="text-emerald-400" :size="36" stroke-width="2.5" />
                    </div>

                    <h2 class="text-xl sm:text-3xl font-black text-white text-center mb-2">
                        3. Pilih Kategori Barang
                    </h2>
                    <p class="text-surface-400 text-center mb-7 font-medium text-xs sm:text-sm max-w-md mx-auto">
                        Pilih jenis barang yang akan diperiksa stok fisiknya di toko hari ini.
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Card HP -->
                        <button type="button" @click="selectCategory('hp')"
                            class="p-5 sm:p-6 rounded-3xl border-2 transition-all flex flex-col items-center text-center gap-3 group cursor-pointer text-left relative overflow-hidden"
                            :class="categoryType === 'hp'
                                ? 'border-emerald-500 bg-emerald-500/10 shadow-xl shadow-emerald-500/10 ring-2 ring-emerald-500/30'
                                : 'border-surface-750 bg-surface-800/70 hover:border-emerald-500/50 hover:bg-surface-800'">
                            <div class="w-16 h-16 rounded-2xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center group-hover:scale-110 transition-transform border border-emerald-500/30 shadow-md">
                                <Smartphone :size="30" />
                            </div>
                            <div>
                                <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                    Ber-Nomor IMEI
                                </span>
                                <h3 class="text-base sm:text-lg font-black text-white mt-2 group-hover:text-emerald-400 transition-colors">
                                    HP (Smartphone)
                                </h3>
                                <p class="text-xs text-surface-400 mt-1">
                                    iPhone, Samsung, Xiaomi, Vivo, Oppo, Infinix, dll.
                                </p>
                            </div>
                            <div class="mt-2 text-xs font-bold text-emerald-400 flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                                <span>Pilih HP</span>
                                <ArrowRight :size="14" />
                            </div>
                        </button>

                        <!-- Card Non-HP -->
                        <button type="button" @click="selectCategory('non_hp')"
                            class="p-5 sm:p-6 rounded-3xl border-2 transition-all flex flex-col items-center text-center gap-3 group cursor-pointer text-left relative overflow-hidden"
                            :class="categoryType === 'non_hp'
                                ? 'border-emerald-500 bg-emerald-500/10 shadow-xl shadow-emerald-500/10 ring-2 ring-emerald-500/30'
                                : 'border-surface-750 bg-surface-800/70 hover:border-emerald-500/50 hover:bg-surface-800'">
                            <div class="w-16 h-16 rounded-2xl bg-teal-500/20 text-teal-400 flex items-center justify-center group-hover:scale-110 transition-transform border border-teal-500/30 shadow-md">
                                <Package :size="30" />
                            </div>
                            <div>
                                <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-0.5 rounded-full bg-teal-500/20 text-teal-300 border border-teal-500/30">
                                    Tanpa IMEI
                                </span>
                                <h3 class="text-base sm:text-lg font-black text-white mt-2 group-hover:text-teal-400 transition-colors">
                                    Non-HP / Aksesoris
                                </h3>
                                <p class="text-xs text-surface-400 mt-1">
                                    Charger, kabel data, casing, TWS, tempered glass, dll.
                                </p>
                            </div>
                            <div class="mt-2 text-xs font-bold text-teal-400 flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                                <span>Pilih Non-HP</span>
                                <ArrowRight :size="14" />
                            </div>
                        </button>
                    </div>

                    <!-- Back Button -->
                    <div class="flex items-center justify-start mt-7">
                        <button type="button" @click="currentStep = 2"
                            class="px-5 h-12 rounded-2xl border border-surface-700 font-bold text-xs sm:text-sm text-surface-400 hover:text-white hover:bg-surface-800 transition-all flex items-center gap-2 cursor-pointer">
                            <ArrowLeft :size="16" />
                            <span>Kembali ke Pilih CS</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- STEP 4: PILIH SO BY UNIT / SO BY IMEI      -->
        <!-- ========================================== -->
        <div v-else-if="currentStep === 4" class="flex items-center justify-center py-2 sm:py-6">
            <div class="w-full max-w-2xl bg-gradient-to-b from-surface-850 to-surface-900 rounded-3xl border border-surface-750 p-5 sm:p-10 shadow-2xl relative overflow-hidden">
                <div class="absolute -top-24 -right-24 w-72 h-72 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>

                <div class="relative z-10">
                    <!-- Context Pill -->
                    <div class="mb-5 p-3 rounded-2xl bg-surface-800/80 border border-surface-700 flex items-center justify-between text-xs">
                        <div class="truncate">
                            <span class="text-surface-400">Toko:</span>
                            <strong class="text-white ml-1 font-bold">{{ activeBranch?.name }}</strong>
                            <span class="mx-2 text-surface-600">•</span>
                            <span class="text-surface-400">Kategori:</span>
                            <strong class="text-emerald-400 ml-1 font-bold">HP (Smartphone)</strong>
                        </div>
                        <button type="button" @click="currentStep = 3" class="text-emerald-400 font-bold hover:underline cursor-pointer">
                            Ganti Kategori
                        </button>
                    </div>

                    <div class="w-16 h-16 sm:w-20 sm:h-20 bg-gradient-to-br from-emerald-500/20 to-teal-500/10 border border-emerald-500/30 rounded-3xl flex items-center justify-center mb-5 mx-auto ring-8 ring-emerald-500/10 shadow-lg shadow-emerald-500/10">
                        <Layers class="text-emerald-400" :size="36" stroke-width="2.5" />
                    </div>

                    <h2 class="text-xl sm:text-3xl font-black text-white text-center mb-2">
                        4. Pilih Metode Stok Opname
                    </h2>
                    <p class="text-surface-400 text-center mb-7 font-medium text-xs sm:text-sm max-w-md mx-auto">
                        Pilih cara pemeriksaan unit smartphone yang tersedia di toko.
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- SO by Unit -->
                        <button type="button" @click="selectMethod('by_unit')"
                            class="p-5 sm:p-6 rounded-3xl border-2 transition-all flex flex-col items-center text-center gap-3 group cursor-pointer text-left relative overflow-hidden"
                            :class="method === 'by_unit'
                                ? 'border-emerald-500 bg-emerald-500/10 shadow-xl shadow-emerald-500/10 ring-2 ring-emerald-500/30'
                                : 'border-surface-750 bg-surface-800/70 hover:border-emerald-500/50 hover:bg-surface-800'">
                            <div class="w-16 h-16 rounded-2xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center group-hover:scale-110 transition-transform border border-emerald-500/30 shadow-md">
                                <Layers :size="30" />
                            </div>
                            <div>
                                <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                    Hitung Kuantitas
                                </span>
                                <h3 class="text-base sm:text-lg font-black text-white mt-2 group-hover:text-emerald-400 transition-colors">
                                    SO by Unit
                                </h3>
                                <p class="text-xs text-surface-400 mt-1">
                                    Hitung total unit fisik berdasarkan brand, tipe, dan kondisi (Baru / Second).
                                </p>
                            </div>
                            <div class="mt-2 text-xs font-bold text-emerald-400 flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                                <span>Pilih SO by Unit</span>
                                <ArrowRight :size="14" />
                            </div>
                        </button>

                        <!-- SO by IMEI -->
                        <button type="button" @click="selectMethod('by_imei')"
                            class="p-5 sm:p-6 rounded-3xl border-2 transition-all flex flex-col items-center text-center gap-3 group cursor-pointer text-left relative overflow-hidden"
                            :class="method === 'by_imei'
                                ? 'border-emerald-500 bg-emerald-500/10 shadow-xl shadow-emerald-500/10 ring-2 ring-emerald-500/30'
                                : 'border-surface-750 bg-surface-800/70 hover:border-emerald-500/50 hover:bg-surface-800'">
                            <div class="w-16 h-16 rounded-2xl bg-teal-500/20 text-teal-400 flex items-center justify-center group-hover:scale-110 transition-transform border border-teal-500/30 shadow-md">
                                <QrCode :size="30" />
                            </div>
                            <div>
                                <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-0.5 rounded-full bg-teal-500/20 text-teal-300 border border-teal-500/30">
                                    Checklist Seri
                                </span>
                                <h3 class="text-base sm:text-lg font-black text-white mt-2 group-hover:text-teal-400 transition-colors">
                                    SO by IMEI
                                </h3>
                                <p class="text-xs text-surface-400 mt-1">
                                    Pilih tipe HP lalu periksa dan checklist nomor IMEI fisik satu per satu.
                                </p>
                            </div>
                            <div class="mt-2 text-xs font-bold text-teal-400 flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                                <span>Pilih SO by IMEI</span>
                                <ArrowRight :size="14" />
                            </div>
                        </button>
                    </div>

                    <!-- Back Button -->
                    <div class="flex items-center justify-start mt-7">
                        <button type="button" @click="currentStep = 3"
                            class="px-5 h-12 rounded-2xl border border-surface-700 font-bold text-xs sm:text-sm text-surface-400 hover:text-white hover:bg-surface-800 transition-all flex items-center gap-2 cursor-pointer">
                            <ArrowLeft :size="16" />
                            <span>Kembali ke Kategori</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- STEP 5A: PILIH BRAND & KONDISI (BY UNIT)   -->
        <!-- ========================================== -->
        <div v-else-if="currentStep === 5 && method === 'by_unit'" class="flex items-center justify-center py-2 sm:py-6">
            <div class="w-full max-w-3xl bg-gradient-to-b from-surface-850 to-surface-900 rounded-3xl border border-surface-750 p-5 sm:p-10 shadow-2xl relative overflow-hidden">
                <div class="absolute -top-24 -right-24 w-72 h-72 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>

                <div class="relative z-10 space-y-6">
                    <!-- Context Pill -->
                    <div class="p-3.5 rounded-2xl bg-surface-800/80 border border-surface-700 flex flex-wrap items-center justify-between gap-2 text-xs">
                        <div class="truncate">
                            <span class="text-surface-400">Toko:</span>
                            <strong class="text-white ml-1 font-bold">{{ activeBranch?.name }}</strong>
                            <span class="mx-2 text-surface-600">•</span>
                            <span class="text-surface-400">Metode:</span>
                            <strong class="text-emerald-400 ml-1 font-bold">
                                {{ categoryType === 'hp' ? 'HP by Unit' : 'Non-HP / Aksesoris' }}
                            </strong>
                        </div>
                        <button type="button" @click="currentStep = (categoryType === 'hp' ? 4 : 3)" class="text-emerald-400 font-bold hover:underline cursor-pointer">
                            Ubah Metode
                        </button>
                    </div>

                    <div class="text-center max-w-lg mx-auto">
                        <h2 class="text-xl sm:text-3xl font-black text-white mb-2">
                            5. Pilih Brand & Kondisi
                        </h2>
                        <p class="text-surface-400 font-medium text-xs sm:text-sm">
                            Anda dapat memilih <strong class="text-emerald-400 font-bold">lebih dari 1 brand</strong> dan <strong class="text-emerald-400 font-bold">lebih dari 1 kondisi</strong>.
                        </p>
                    </div>

                    <!-- 1. MULTI-SELECT BRAND -->
                    <div class="space-y-3 bg-surface-800/60 p-4 sm:p-5 rounded-2xl border border-surface-750">
                        <div class="flex items-center justify-between flex-wrap gap-2">
                            <div class="flex items-center gap-2">
                                <Filter :size="14" class="text-emerald-400" />
                                <label class="text-xs font-black text-white uppercase tracking-wider">
                                    Pilih Brand (Merek)
                                </label>
                                <span v-if="selectedBrands.length > 0" class="text-[10px] px-2 py-0.2 rounded-full bg-emerald-500/20 text-emerald-300 font-mono font-bold">
                                    {{ selectedBrands.length }} terpilih
                                </span>
                            </div>
                            <div class="flex items-center gap-2 text-xs">
                                <button type="button" @click="selectAllBrands" class="text-[11px] font-bold text-emerald-400 hover:underline cursor-pointer">
                                    Pilih Semua
                                </button>
                                <span class="text-surface-600">•</span>
                                <button type="button" @click="resetBrands" class="text-[11px] font-bold text-surface-400 hover:text-white cursor-pointer">
                                    Semua Brand (Default)
                                </button>
                            </div>
                        </div>

                        <!-- Brand Chips -->
                        <div class="flex flex-wrap gap-2 pt-1">
                            <!-- Button Semua Brand -->
                            <button type="button" @click="toggleBrand('')"
                                class="px-4 py-2 rounded-xl text-xs font-bold transition-all border cursor-pointer flex items-center gap-1.5"
                                :class="isBrandSelected('')
                                    ? 'bg-gradient-to-r from-emerald-600 to-teal-500 text-white border-emerald-500 shadow-lg shadow-emerald-500/20'
                                    : 'bg-surface-800 text-surface-300 border-surface-700 hover:border-surface-600'">
                                <Check v-if="isBrandSelected('')" :size="13" />
                                <span>Semua Brand</span>
                            </button>

                            <!-- Individual Brands -->
                            <button v-for="b in availableBrands" :key="b.brand" type="button" @click="toggleBrand(b.brand)"
                                class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all border flex items-center gap-2 cursor-pointer"
                                :class="isBrandSelected(b.brand)
                                    ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500 shadow-md ring-2 ring-emerald-500/25'
                                    : 'bg-surface-800 text-surface-400 border-surface-700 hover:border-surface-600 hover:text-white'">
                                <Check v-if="isBrandSelected(b.brand)" :size="13" class="text-emerald-400" />
                                <span>{{ b.brand }}</span>
                                <span class="text-[10px] px-1.5 py-0.2 rounded-full font-mono"
                                    :class="isBrandSelected(b.brand) ? 'bg-emerald-500/30 text-emerald-200' : 'bg-surface-700 text-surface-400'">
                                    {{ b.count }}
                                </span>
                            </button>
                        </div>
                    </div>

                    <!-- 2. MULTI-SELECT KONDISI (Khusus HP) -->
                    <div v-if="categoryType === 'hp'" class="space-y-3 bg-surface-800/60 p-4 sm:p-5 rounded-2xl border border-surface-750">
                        <div class="flex items-center justify-between flex-wrap gap-2">
                            <div class="flex items-center gap-2">
                                <Sparkles :size="14" class="text-emerald-400" />
                                <label class="text-xs font-black text-white uppercase tracking-wider">
                                    Pilih Kondisi Unit
                                </label>
                                <span v-if="selectedConditions.length > 0" class="text-[10px] px-2 py-0.2 rounded-full bg-emerald-500/20 text-emerald-300 font-mono font-bold">
                                    {{ selectedConditions.length }} kondisi terpilih
                                </span>
                            </div>
                            <button type="button" @click="toggleCondition('all')" class="text-[11px] font-bold text-surface-400 hover:text-white cursor-pointer">
                                Semua Kondisi (Default)
                            </button>
                        </div>

                        <!-- Condition Chips -->
                        <div class="flex flex-wrap gap-2 pt-1">
                            <!-- Semua Kondisi -->
                            <button type="button" @click="toggleCondition('all')"
                                class="px-4 py-2 rounded-xl text-xs font-bold transition-all border cursor-pointer flex items-center gap-1.5"
                                :class="isConditionSelected('all')
                                    ? 'bg-gradient-to-r from-emerald-600 to-teal-500 text-white border-emerald-500 shadow-lg shadow-emerald-500/20'
                                    : 'bg-surface-800 text-surface-300 border-surface-700 hover:border-surface-600'">
                                <Check v-if="isConditionSelected('all')" :size="13" />
                                <span>Semua Kondisi</span>
                            </button>

                            <!-- Baru (New) -->
                            <button type="button" @click="toggleCondition('new')"
                                class="px-4 py-2 rounded-xl text-xs font-bold transition-all border flex items-center gap-2 cursor-pointer"
                                :class="isConditionSelected('new')
                                    ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500 shadow-md ring-2 ring-emerald-500/25'
                                    : 'bg-surface-800 text-surface-400 border-surface-700 hover:border-surface-600 hover:text-white'">
                                <Check v-if="isConditionSelected('new')" :size="13" class="text-emerald-400" />
                                <span>Baru (New)</span>
                                <span v-if="availableConditions.find(c => c.condition === 'new')" class="text-[10px] px-1.5 py-0.2 rounded-full font-mono"
                                    :class="isConditionSelected('new') ? 'bg-emerald-500/30 text-emerald-200' : 'bg-surface-700 text-surface-400'">
                                    {{ availableConditions.find(c => c.condition === 'new')?.count || 0 }}
                                </span>
                            </button>

                            <!-- Second (Bekas) -->
                            <button type="button" @click="toggleCondition('second')"
                                class="px-4 py-2 rounded-xl text-xs font-bold transition-all border flex items-center gap-2 cursor-pointer"
                                :class="isConditionSelected('second')
                                    ? 'bg-amber-500/20 text-amber-300 border-amber-500 shadow-md ring-2 ring-amber-500/25'
                                    : 'bg-surface-800 text-surface-400 border-surface-700 hover:border-surface-600 hover:text-white'">
                                <Check v-if="isConditionSelected('second')" :size="13" class="text-amber-400" />
                                <span>Second (Bekas)</span>
                                <span v-if="availableConditions.find(c => c.condition === 'second')" class="text-[10px] px-1.5 py-0.2 rounded-full font-mono"
                                    :class="isConditionSelected('second') ? 'bg-amber-500/30 text-amber-200' : 'bg-surface-700 text-surface-400'">
                                    {{ availableConditions.find(c => c.condition === 'second')?.count || 0 }}
                                </span>
                            </button>

                            <!-- Ex iBox -->
                            <button type="button" @click="toggleCondition('ex_ibox')"
                                class="px-4 py-2 rounded-xl text-xs font-bold transition-all border flex items-center gap-2 cursor-pointer"
                                :class="isConditionSelected('ex_ibox')
                                    ? 'bg-sky-500/20 text-sky-300 border-sky-500 shadow-md ring-2 ring-sky-500/25'
                                    : 'bg-surface-800 text-surface-400 border-surface-700 hover:border-surface-600 hover:text-white'">
                                <Check v-if="isConditionSelected('ex_ibox')" :size="13" class="text-sky-400" />
                                <span>Ex iBox</span>
                                <span v-if="availableConditions.find(c => c.condition === 'ex_ibox')" class="text-[10px] px-1.5 py-0.2 rounded-full font-mono"
                                    :class="isConditionSelected('ex_ibox') ? 'bg-sky-500/30 text-sky-200' : 'bg-surface-700 text-surface-400'">
                                    {{ availableConditions.find(c => c.condition === 'ex_ibox')?.count || 0 }}
                                </span>
                            </button>
                        </div>
                    </div>

                    <!-- Live AI Ready Status Card -->
                    <div class="p-4 sm:p-5 rounded-2xl bg-surface-800 border border-emerald-500/30 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 shadow-md">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center font-black">
                                <ClipboardCheck :size="20" />
                            </div>
                            <div>
                                <div class="text-[11px] uppercase font-bold text-surface-400 tracking-wider">
                                    Stok Sistem Siap Diperiksa
                                </div>
                                <div class="text-base sm:text-lg font-black text-white">
                                    {{ pendingItems.length }} varian <span class="text-emerald-400 text-xs sm:text-sm font-bold">({{ totalAvailableUnits }} total unit)</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-1.5 text-xs font-bold text-surface-400 self-stretch sm:self-auto justify-end">
                            <span v-if="selectedBrands.length > 0" class="text-[10px] px-2 py-0.5 rounded bg-surface-700 text-white font-mono">
                                {{ selectedBrands.join(', ') }}
                            </span>
                            <span v-else class="text-[10px] px-2 py-0.5 rounded bg-surface-700 text-white">
                                Semua Brand
                            </span>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center gap-3 pt-2">
                        <button type="button" @click="currentStep = (categoryType === 'hp' ? 4 : 3)"
                            class="px-5 h-12 sm:h-14 rounded-2xl border border-surface-700 font-bold text-xs sm:text-sm text-surface-400 hover:text-white hover:bg-surface-800 transition-all flex items-center gap-2 cursor-pointer">
                            <ArrowLeft :size="16" />
                            <span>Kembali</span>
                        </button>
                        <button type="button" @click="proceedFromStep5Unit"
                            class="flex-1 h-12 sm:h-14 bg-gradient-to-r from-emerald-600 to-teal-500 hover:from-emerald-500 hover:to-teal-400 text-white rounded-2xl font-black text-sm sm:text-base shadow-xl shadow-emerald-500/25 transition-all flex items-center justify-center gap-3 cursor-pointer">
                            <span>Mulai Perhitungan Fisik (Tabel)</span>
                            <ArrowRight :size="20" stroke-width="2.5" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- STEP 5B: PILIH TIPE HP (SO BY IMEI)        -->
        <!-- ========================================== -->
        <div v-else-if="currentStep === 5 && method === 'by_imei'" class="flex items-center justify-center py-2 sm:py-6">
            <div class="w-full max-w-4xl bg-gradient-to-b from-surface-850 to-surface-900 rounded-3xl border border-surface-750 p-5 sm:p-10 shadow-2xl relative overflow-hidden">
                <div class="absolute -top-24 -right-24 w-72 h-72 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>

                <div class="relative z-10 space-y-6">
                    <!-- Context Pill -->
                    <div class="p-3.5 rounded-2xl bg-surface-800/80 border border-surface-700 flex flex-wrap items-center justify-between gap-2 text-xs">
                        <div class="truncate">
                            <span class="text-surface-400">Toko:</span>
                            <strong class="text-white ml-1 font-bold">{{ activeBranch?.name }}</strong>
                            <span class="mx-2 text-surface-600">•</span>
                            <span class="text-surface-400">Metode:</span>
                            <strong class="text-emerald-400 ml-1 font-bold">SO by IMEI (Checklist)</strong>
                        </div>
                        <button type="button" @click="currentStep = 4" class="text-emerald-400 font-bold hover:underline cursor-pointer">
                            Ganti Metode
                        </button>
                    </div>

                    <div class="text-center max-w-lg mx-auto">
                        <h2 class="text-xl sm:text-3xl font-black text-white mb-2">
                            5. Pilih Tipe HP
                        </h2>
                        <p class="text-surface-400 font-medium text-xs sm:text-sm">
                            Pilih model/tipe HP di bawah untuk memeriksa nomor seri IMEI satu per satu.
                        </p>
                    </div>

                    <!-- Multi-Brand Filter Chips for Types -->
                    <div class="space-y-2 bg-surface-800/60 p-3.5 sm:p-4 rounded-2xl border border-surface-750">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-bold text-surface-400 uppercase text-[10px] tracking-wider">
                                Filter Merek HP (Multi-select)
                            </span>
                            <button v-if="selectedBrands.length > 0" @click="resetBrands" class="text-emerald-400 text-[11px] font-bold hover:underline cursor-pointer">
                                Reset ke Semua Brand
                            </button>
                        </div>
                        <div class="flex flex-wrap items-center gap-1.5">
                            <button type="button" @click="toggleBrand('')"
                                class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all border cursor-pointer flex items-center gap-1"
                                :class="isBrandSelected('')
                                    ? 'bg-gradient-to-r from-emerald-600 to-teal-500 text-white border-emerald-500 shadow-sm'
                                    : 'bg-surface-800 text-surface-400 border-surface-700 hover:border-surface-600'">
                                <Check v-if="isBrandSelected('')" :size="12" />
                                <span>Semua Brand</span>
                            </button>
                            <button v-for="b in availableBrands" :key="b.brand" type="button" @click="toggleBrand(b.brand)"
                                class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all border flex items-center gap-1.5 cursor-pointer"
                                :class="isBrandSelected(b.brand)
                                    ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500'
                                    : 'bg-surface-800 text-surface-400 border-surface-700 hover:border-surface-600 hover:text-white'">
                                <Check v-if="isBrandSelected(b.brand)" :size="12" class="text-emerald-400" />
                                <span>{{ b.brand }}</span>
                                <span class="text-[10px] px-1.5 py-0.2 rounded-full font-mono"
                                    :class="isBrandSelected(b.brand) ? 'bg-emerald-500/30 text-emerald-200' : 'bg-surface-700 text-surface-400'">
                                    {{ b.count }}
                                </span>
                            </button>
                        </div>
                    </div>

                    <!-- Opsi Pilih Per Kondisi (New, Second, Ex iBox) -->
                    <div class="space-y-2 bg-surface-800/60 p-3.5 sm:p-4 rounded-2xl border border-surface-750">
                        <div class="flex items-center justify-between text-xs">
                            <div class="flex items-center gap-1.5">
                                <Sparkles :size="13" class="text-emerald-400" />
                                <span class="font-bold text-surface-400 uppercase text-[10px] tracking-wider">
                                    Opsi Kondisi Unit (New / Scd / Ex iBox)
                                </span>
                            </div>
                            <span class="text-[10px] text-surface-400 italic">
                                Default: Belum Dipilih
                            </span>
                        </div>
                        <div class="flex flex-wrap items-center gap-1.5">
                            <!-- Button Belum Dipilih (Default) -->
                            <button type="button" @click="toggleCondition('all')"
                                class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all border cursor-pointer flex items-center gap-1"
                                :class="selectedConditions.length === 0
                                    ? 'bg-gradient-to-r from-emerald-600 to-teal-500 text-white border-emerald-500 shadow-sm'
                                    : 'bg-surface-800 text-surface-400 border-surface-700 hover:border-surface-600 hover:text-white'">
                                <Check v-if="selectedConditions.length === 0" :size="12" />
                                <span>Belum Dipilih (Semua)</span>
                            </button>

                            <!-- Baru (New) -->
                            <button type="button" @click="toggleCondition('new')"
                                class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all border flex items-center gap-1.5 cursor-pointer"
                                :class="isConditionSelected('new')
                                    ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500 shadow-sm ring-1 ring-emerald-500/30'
                                    : 'bg-surface-800 text-surface-400 border-surface-700 hover:border-surface-600 hover:text-white'">
                                <Check v-if="isConditionSelected('new')" :size="12" class="text-emerald-400" />
                                <span>Baru (New)</span>
                                <span v-if="availableConditions.find(c => c.condition === 'new')" class="text-[10px] px-1.5 py-0.2 rounded-full font-mono"
                                    :class="isConditionSelected('new') ? 'bg-emerald-500/30 text-emerald-200' : 'bg-surface-700 text-surface-400'">
                                    {{ availableConditions.find(c => c.condition === 'new')?.count || 0 }}
                                </span>
                            </button>

                            <!-- Second (Bekas) -->
                            <button type="button" @click="toggleCondition('second')"
                                class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all border flex items-center gap-1.5 cursor-pointer"
                                :class="isConditionSelected('second')
                                    ? 'bg-amber-500/20 text-amber-300 border-amber-500 shadow-sm ring-1 ring-amber-500/30'
                                    : 'bg-surface-800 text-surface-400 border-surface-700 hover:border-surface-600 hover:text-white'">
                                <Check v-if="isConditionSelected('second')" :size="12" class="text-amber-400" />
                                <span>Second (Bekas)</span>
                                <span v-if="availableConditions.find(c => c.condition === 'second')" class="text-[10px] px-1.5 py-0.2 rounded-full font-mono"
                                    :class="isConditionSelected('second') ? 'bg-amber-500/30 text-amber-200' : 'bg-surface-700 text-surface-400'">
                                    {{ availableConditions.find(c => c.condition === 'second')?.count || 0 }}
                                </span>
                            </button>

                            <!-- Ex iBox -->
                            <button type="button" @click="toggleCondition('ex_ibox')"
                                class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all border flex items-center gap-1.5 cursor-pointer"
                                :class="isConditionSelected('ex_ibox')
                                    ? 'bg-sky-500/20 text-sky-300 border-sky-500 shadow-sm ring-1 ring-sky-500/30'
                                    : 'bg-surface-800 text-surface-400 border-surface-700 hover:border-surface-600 hover:text-white'">
                                <Check v-if="isConditionSelected('ex_ibox')" :size="12" class="text-sky-400" />
                                <span>Ex iBox</span>
                                <span v-if="availableConditions.find(c => c.condition === 'ex_ibox')" class="text-[10px] px-1.5 py-0.2 rounded-full font-mono"
                                    :class="isConditionSelected('ex_ibox') ? 'bg-sky-500/30 text-sky-200' : 'bg-surface-700 text-surface-400'">
                                    {{ availableConditions.find(c => c.condition === 'ex_ibox')?.count || 0 }}
                                </span>
                            </button>
                        </div>
                    </div>

                    <!-- Search Type Input -->
                    <div class="relative">
                        <input v-model="searchType" type="text" placeholder="Cari nama tipe HP (contoh: iPhone 11, Samsung S24)..."
                            class="w-full px-4 py-3 pl-10 text-xs sm:text-sm rounded-xl bg-surface-800/80 border border-surface-700 text-white placeholder:text-surface-500 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none transition-all" />
                        <Search :size="18" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-surface-400" />
                    </div>

                    <!-- Types Cards Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 max-h-[380px] overflow-y-auto pr-1 custom-scrollbar">
                        <div v-if="filteredAvailableTypes.length === 0" class="col-span-full py-12 text-center text-surface-400 text-xs">
                            Tidak ada tipe HP yang perlu di-SO untuk filter ini.
                        </div>
                        <button v-for="t in filteredAvailableTypes" :key="t.product_name"
                            type="button"
                            @click="selectTypeAndProceed(t.product_name)"
                            class="p-4 rounded-2xl border-2 transition-all text-left flex items-center justify-between gap-3 group cursor-pointer"
                            :class="selectedProductName === t.product_name
                                ? 'border-emerald-500 bg-emerald-500/15 ring-2 ring-emerald-500/30'
                                : 'border-surface-750 bg-surface-800/80 hover:border-emerald-500/50 hover:bg-surface-800'">
                            <div class="min-w-0 flex-1">
                                <span class="text-[10px] font-black uppercase text-emerald-400">
                                    {{ t.brand || 'HP' }}
                                </span>
                                <div class="font-black text-xs sm:text-sm text-white truncate group-hover:text-emerald-400 transition-colors mt-0.5">
                                    {{ t.product_name }}
                                </div>
                            </div>
                            <div class="shrink-0 flex flex-col items-end gap-1">
                                <span class="px-2.5 py-1 rounded-full text-xs font-black bg-emerald-500/20 text-emerald-300 font-mono border border-emerald-500/30">
                                    {{ t.count }} unit
                                </span>
                                <ArrowRight :size="14" class="text-surface-400 group-hover:text-emerald-400 group-hover:translate-x-1 transition-all" />
                            </div>
                        </button>
                    </div>

                    <!-- Back Button -->
                    <div class="flex items-center justify-start pt-2">
                        <button type="button" @click="currentStep = 4"
                            class="px-5 h-12 rounded-2xl border border-surface-700 font-bold text-xs sm:text-sm text-surface-400 hover:text-white hover:bg-surface-800 transition-all flex items-center gap-2 cursor-pointer">
                            <ArrowLeft :size="16" />
                            <span>Kembali ke Pilih Metode</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- STEP 6: PEMERIKSAAN FISIK STOK             -->
        <!-- ========================================== -->
        <div v-else-if="currentStep === 6" class="space-y-6">
            <!-- Active Context Banner -->
            <div class="bg-gradient-to-r from-surface-850 via-surface-900 to-surface-850 p-4 sm:p-5 rounded-3xl border border-surface-750 shadow-lg flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4">
                <div class="flex items-center gap-3.5 min-w-0">
                    <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-emerald-500/20 to-teal-500/10 text-emerald-400 flex items-center justify-center shrink-0 border border-emerald-500/30">
                        <Building2 :size="22" />
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="text-base sm:text-lg font-black text-white tracking-tight">
                                {{ activeBranch?.name || 'Cabang Terpilih' }}
                            </h3>
                            <span v-if="activeBranch?.code" class="text-[10px] font-bold px-2 py-0.5 rounded-md bg-surface-800 text-surface-300 font-mono border border-surface-700">
                                {{ activeBranch.code }}
                            </span>
                            <!-- Role Scope Pill Badge -->
                            <span v-if="isAudit" class="inline-flex items-center gap-1 text-[10px] font-black px-2.5 py-0.5 rounded-full bg-purple-900/60 text-purple-300 border border-purple-500/40">
                                <ShieldCheck :size="12" />
                                <span>SO AUDIT</span>
                            </span>
                            <span v-else-if="isLeader" class="inline-flex items-center gap-1 text-[10px] font-black px-2.5 py-0.5 rounded-full bg-sky-900/60 text-sky-300 border border-sky-500/40">
                                <UserCheck :size="12" />
                                <span>SO LEADER</span>
                            </span>
                            <span v-else class="inline-flex items-center gap-1 text-[10px] font-black px-2.5 py-0.5 rounded-full bg-emerald-900/60 text-emerald-300 border border-emerald-500/40">
                                <Store :size="12" />
                                <span>SO CS TOKO</span>
                            </span>
                        </div>
                        <p class="text-xs text-surface-400 mt-1 flex items-center gap-2 flex-wrap">
                            <span>Pelaksana: <strong class="text-white">{{ activeCsUser?.name || 'CS' }}</strong></span>
                            <span class="text-surface-600">•</span>
                            <span class="font-bold text-emerald-400">
                                <template v-if="categoryType === 'hp' && method === 'by_imei'">
                                    Tipe: {{ selectedProductName }} ({{ pendingItems.length }} unit)
                                </template>
                                <template v-else-if="categoryType === 'hp'">
                                    SO by Unit •
                                    {{ selectedBrands.length > 0 ? selectedBrands.join(', ') : 'Semua Brand' }}
                                    ({{ selectedConditions.length > 0 ? selectedConditions.map(c => c === 'new' ? 'Baru' : 'Second').join(', ') : 'Semua Kondisi' }})
                                </template>
                                <template v-else>
                                    Non-HP • {{ selectedBrands.length > 0 ? selectedBrands.join(', ') : 'Semua Brand' }}
                                </template>
                            </span>
                        </p>
                    </div>
                </div>

                <!-- Fast Navigation Buttons -->
                <div class="flex items-center gap-2 self-stretch lg:self-auto flex-wrap">
                    <button v-if="method === 'by_imei'" type="button" @click="changeSelectedType"
                        class="flex-1 sm:flex-initial flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-emerald-500/20 border border-emerald-500/40 text-xs font-bold text-emerald-300 hover:bg-emerald-500/30 transition-all cursor-pointer">
                        <Smartphone :size="14" />
                        <span>Ganti Tipe HP</span>
                    </button>
                    <button v-else type="button" @click="currentStep = 5"
                        class="flex-1 sm:flex-initial flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-emerald-500/20 border border-emerald-500/40 text-xs font-bold text-emerald-300 hover:bg-emerald-500/30 transition-all cursor-pointer">
                        <Filter :size="14" />
                        <span>Filter Brand / Kondisi</span>
                    </button>
                    <button type="button" @click="currentStep = 2"
                        class="flex-1 sm:flex-initial flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl border border-surface-700 bg-surface-800 text-xs font-bold text-surface-300 hover:text-white hover:border-surface-600 transition-all cursor-pointer">
                        <User :size="14" />
                        <span>Ganti CS</span>
                    </button>
                    <button type="button" @click="currentStep = 1"
                        class="flex-1 sm:flex-initial flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl border border-surface-700 bg-surface-800 text-xs font-bold text-surface-300 hover:text-white hover:border-surface-600 transition-all cursor-pointer">
                        <Building2 :size="14" />
                        <span>Ganti Toko</span>
                    </button>
                </div>
            </div>

            <!-- Examination Content (Tabel by Unit ATAU Checklist by IMEI) -->
            <div class="bg-surface-850 rounded-3xl border border-surface-750 p-4 sm:p-6 shadow-xl space-y-4">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                    <div>
                        <h2 class="text-base sm:text-lg font-black text-white flex items-center gap-2">
                            <ClipboardCheck :size="20" class="text-emerald-400" />
                            <span>{{ method === 'by_imei' ? 'Checklist IMEI: ' + selectedProductName : 'Tabel Perhitungan Unit Fisik' }}</span>
                        </h2>
                        <p class="text-xs text-surface-400 mt-0.5">
                            {{ method === 'by_imei' ? 'Tandai Ada ✅ jika unit fisik ditemukan di toko, atau ❌ jika tidak ada' : 'Masukkan jumlah fisik unit yang ditemukan di toko' }}
                        </p>
                    </div>

                    <!-- Quick Action Buttons -->
                    <div class="flex items-center gap-2 self-stretch sm:self-auto flex-wrap">
                        <button v-if="method === 'by_unit' && pendingItems.length > 0" type="button" @click="fillAllMatchingSystem"
                            class="px-3.5 py-2 rounded-xl border border-surface-700 bg-surface-800 text-xs font-bold text-surface-300 hover:text-white hover:border-emerald-500 transition-all cursor-pointer">
                            Isi Sesuai Sistem
                        </button>
                        <template v-else-if="method === 'by_imei' && pendingItems.length > 0">
                            <button type="button" @click="markAllPresent(true)"
                                class="px-3.5 py-2 rounded-xl bg-emerald-500/20 border border-emerald-500/40 text-emerald-300 text-xs font-bold hover:bg-emerald-500/30 transition-all flex items-center gap-1.5 cursor-pointer">
                                <Check :size="15" />
                                <span>Semua Ada ✅</span>
                            </button>
                            <button type="button" @click="markAllPresent(false)"
                                class="px-3.5 py-2 rounded-xl bg-rose-500/20 border border-rose-500/40 text-rose-300 text-xs font-bold hover:bg-rose-500/30 transition-all flex items-center gap-1.5 cursor-pointer">
                                <X :size="15" />
                                <span>Semua Tidak Ada ❌</span>
                            </button>
                            <button type="button" @click="markAllPresent(null)"
                                class="px-3.5 py-2 rounded-xl bg-surface-800 border border-surface-700 text-surface-400 text-xs font-bold hover:bg-surface-750 hover:text-white transition-all flex items-center gap-1.5 cursor-pointer">
                                <RotateCcw :size="14" />
                                <span>Reset (Belum Dipilih)</span>
                            </button>
                        </template>
                    </div>
                </div>

                <!-- Loading State -->
                <div v-if="loadingItems" class="py-14 flex flex-col items-center justify-center text-surface-400 gap-3">
                    <RefreshCw :size="26" class="animate-spin text-emerald-500" />
                    <span class="text-xs font-medium">Memuat data stok sistem...</span>
                </div>

                <!-- Empty State -->
                <div v-else-if="pendingItems.length === 0" class="py-12 text-center text-surface-400 bg-surface-900/60 rounded-2xl border border-dashed border-surface-700 px-4">
                    <CheckCircle2 :size="40" class="mx-auto text-emerald-400 mb-2" />
                    <p class="text-base font-bold text-white">
                        Semua Unit Untuk Pilihan Ini Sudah Selesai di-SO Hari Ini ✅
                    </p>
                    <p class="text-xs text-surface-400 mt-1 max-w-md mx-auto leading-relaxed">
                        Seluruh unit untuk filter ini telah tercatat di sistem hari ini. Sesi SO baru akan aktif otomatis besok pukul <strong>05:00 AM</strong>.
                    </p>
                    <div class="mt-4 flex items-center justify-center gap-2">
                        <button type="button" @click="currentStep = 5"
                            class="px-4 py-2 rounded-xl border border-surface-700 bg-surface-800 text-xs font-bold text-white hover:border-emerald-500 transition-all cursor-pointer">
                            Pilih Brand / Tipe Lain
                        </button>
                        <router-link to="/inventory/opname/history"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-sm transition-all">
                            <History :size="15" />
                            <span>Buka History Stok Opname</span>
                        </router-link>
                    </div>
                </div>

                <!-- CASE A: STOK BY UNIT (TABEL PERHITUNGAN FISIK) -->
                <div v-else-if="method === 'by_unit'" class="overflow-x-auto custom-scrollbar">
                    <table class="w-full text-left border-collapse min-w-[720px]">
                        <thead>
                            <tr class="border-b border-surface-700 bg-surface-900/80 text-[11px] font-black text-surface-400 uppercase tracking-wider">
                                <th class="py-3 px-3">Brand & Tipe Produk</th>
                                <th class="py-3 px-3 text-center">Kondisi</th>
                                <th class="py-3 px-3 text-center">Kapasitas</th>
                                <th class="py-3 px-3 text-center">Stok Sistem</th>
                                <th class="py-3 px-3 text-center w-36">Fisik Toko</th>
                                <th class="py-3 px-3 text-center">Selisih</th>
                                <th class="py-3 px-3">Catatan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-800 text-sm">
                            <tr v-for="it in pendingItems" :key="it.key" class="hover:bg-surface-800/60 transition-colors">
                                <td class="py-3 px-3">
                                    <div class="font-bold text-white">{{ it.product_name }}</div>
                                    <div class="text-xs text-surface-400 font-medium">{{ it.brand }}</div>
                                </td>
                                <td class="py-3 px-3 text-center">
                                    <span v-if="it.condition === 'new'" class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">
                                        Baru
                                    </span>
                                    <span v-else class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-500/20 text-amber-300 border border-amber-500/40">
                                        Second
                                    </span>
                                </td>
                                <td class="py-3 px-3 text-center font-mono text-xs font-semibold text-surface-300">
                                    {{ it.storage || '-' }}
                                </td>
                                <td class="py-3 px-3 text-center">
                                    <span class="px-2.5 py-1 rounded-lg bg-surface-800 border border-surface-700 font-black font-mono text-xs text-white">
                                        {{ it.system_qty }}
                                    </span>
                                </td>
                                <td class="py-3 px-3 text-center">
                                    <input type="number" min="0" v-model.number="it.physical_qty" @input="onPhysicalQtyChange(it)"
                                        class="w-24 text-center font-bold font-mono text-sm py-1.5 bg-surface-900 border border-surface-700 rounded-xl focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 text-white" />
                                </td>
                                <td class="py-3 px-3 text-center font-mono font-bold text-xs">
                                    <span v-if="it.difference === 0" class="text-emerald-400 bg-emerald-500/15 border border-emerald-500/30 px-2.5 py-0.5 rounded-lg">
                                        Cocok (0)
                                    </span>
                                    <span v-else-if="it.difference < 0" class="text-rose-400 bg-rose-500/15 border border-rose-500/30 px-2.5 py-0.5 rounded-lg">
                                        Kurang ({{ it.difference }})
                                    </span>
                                    <span v-else class="text-amber-400 bg-amber-500/15 border border-amber-500/30 px-2.5 py-0.5 rounded-lg">
                                        Lebih (+{{ it.difference }})
                                    </span>
                                </td>
                                <td class="py-3 px-3">
                                    <input type="text" v-model="it.notes" placeholder="Catatan (opsional)..."
                                        class="w-full text-xs py-1.5 px-3 bg-surface-900 border border-surface-700 rounded-xl focus:ring-1 focus:ring-emerald-500 text-white placeholder:text-surface-500" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- CASE B: STOK BY IMEI (CHECKLIST CARDS) -->
                <div v-else-if="method === 'by_imei'" class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
                    <div v-for="it in pendingItems" :key="it.product_detail_id || it.imei"
                        class="p-4 rounded-2xl border transition-all flex flex-col justify-between gap-3"
                        :class="it.is_present === true
                            ? 'bg-surface-800/80 border-emerald-500/50 ring-1 ring-emerald-500/20'
                            : (it.is_present === false
                                ? 'bg-rose-950/20 border-rose-800/60'
                                : 'bg-surface-800/40 border-surface-750')">

                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <div class="font-mono text-sm sm:text-base font-black text-white tracking-wider">
                                    {{ it.imei }}
                                </div>
                                <div class="text-xs text-surface-400 mt-1 flex flex-wrap items-center gap-2">
                                    <span class="font-bold text-surface-200">{{ it.product_name }}</span>
                                    <span v-if="it.storage && it.storage !== '-'" class="px-1.5 py-0.5 rounded bg-surface-700 text-[10px] font-mono font-bold text-white">
                                        {{ it.storage }}
                                    </span>
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-black"
                                        :class="it.condition === 'new' 
                                            ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40' 
                                            : (it.condition === 'ex_ibox' 
                                                ? 'bg-sky-500/20 text-sky-300 border border-sky-500/40' 
                                                : 'bg-amber-500/20 text-amber-300 border border-amber-500/40')">
                                        {{ it.condition_label || (it.condition === 'new' ? 'Baru' : (it.condition === 'ex_ibox' ? 'Ex iBox' : 'Second')) }}
                                    </span>
                                    <span class="text-surface-500 text-[11px]">{{ it.distributor }}</span>
                                </div>
                            </div>

                            <!-- Status Indicator Pill -->
                            <span class="text-[11px] font-black px-2.5 py-0.5 rounded-full uppercase"
                                :class="it.is_present === true 
                                    ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40' 
                                    : (it.is_present === false 
                                        ? 'bg-rose-500/20 text-rose-300 border border-rose-500/40' 
                                        : 'bg-surface-700/60 text-surface-400 border border-surface-600')">
                                {{ it.is_present === true ? 'Ada ✅' : (it.is_present === false ? 'Tidak Ada ❌' : 'Belum Dipilih ⏳') }}
                            </span>
                        </div>

                        <!-- Checklist Action Buttons: Ada (✅) / Tidak Ada (❌) -->
                        <div class="grid grid-cols-2 gap-2 pt-1">
                            <button type="button" @click="it.is_present = true"
                                class="py-2.5 px-3 rounded-xl text-xs sm:text-sm font-bold flex items-center justify-center gap-1.5 transition-all touch-manipulation min-h-[44px] cursor-pointer"
                                :class="it.is_present === true
                                    ? 'bg-gradient-to-r from-emerald-600 to-teal-500 text-white shadow-lg shadow-emerald-500/25 ring-2 ring-emerald-500/40 font-black'
                                    : 'border border-surface-700 bg-surface-800 text-surface-400 hover:text-white hover:bg-surface-750'">
                                <Check :size="16" />
                                <span>Ada ✅</span>
                            </button>
                            <button type="button" @click="it.is_present = false"
                                class="py-2.5 px-3 rounded-xl text-xs sm:text-sm font-bold flex items-center justify-center gap-1.5 transition-all touch-manipulation min-h-[44px] cursor-pointer"
                                :class="it.is_present === false
                                    ? 'bg-rose-600 text-white shadow-lg shadow-rose-600/30 ring-2 ring-rose-500/40 font-black'
                                    : 'border border-surface-700 bg-surface-800 text-surface-400 hover:text-white hover:bg-surface-750'">
                                <X :size="16" />
                                <span>Tidak Ada ❌</span>
                            </button>
                        </div>

                        <!-- Input Notes if Missing -->
                        <div v-if="it.is_present === false" class="pt-1">
                            <input type="text" v-model="it.notes" placeholder="Alasan tidak ada (misal: servis, display, dll)..."
                                class="w-full text-xs py-2 px-3 bg-surface-900 border border-rose-800/80 rounded-xl focus:ring-1 focus:ring-rose-500 text-white placeholder:text-surface-500" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section: Barang Lebih (Surplus) -->
            <div class="bg-surface-850 rounded-3xl border border-surface-750 p-4 sm:p-6 shadow-xl space-y-4">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                    <div>
                        <h2 class="text-base font-black text-white flex items-center gap-2">
                            <AlertTriangle :size="18" class="text-amber-400" />
                            <span>Barang Lebih (Fisik Ada tapi Tidak di Sistem)</span>
                        </h2>
                        <p class="text-xs text-surface-400 mt-0.5">
                            Catat jika ada barang fisik di toko yang belum terdaftar di sistem.
                        </p>
                    </div>

                    <button type="button" @click="showExcessModal = true"
                        class="px-3.5 py-2 rounded-xl bg-amber-500/20 border border-amber-500/40 text-amber-300 text-xs font-bold hover:bg-amber-500/30 transition-all flex items-center gap-1.5 cursor-pointer">
                        <Plus :size="16" />
                        <span>Catat Barang Lebih</span>
                    </button>
                </div>

                <!-- List of Excess Items -->
                <div v-if="excessItems.length > 0" class="divide-y divide-surface-800 border border-surface-750 rounded-2xl overflow-hidden">
                    <div v-for="(ex, idx) in excessItems" :key="ex.id || idx" class="p-3.5 flex items-center justify-between gap-3 bg-surface-800/60">
                        <div class="space-y-0.5">
                            <div class="text-xs sm:text-sm font-bold text-white flex items-center gap-2">
                                <span>{{ ex.product_name }}</span>
                                <span v-if="ex.brand" class="text-[10px] bg-surface-700 px-1.5 py-0.5 rounded font-medium text-surface-300">{{ ex.brand }}</span>
                                <span v-if="ex.storage" class="text-[10px] bg-emerald-500/20 text-emerald-300 px-1.5 py-0.5 rounded font-bold font-mono">{{ ex.storage }}</span>
                            </div>
                            <div class="text-xs text-surface-400 flex flex-wrap items-center gap-2 font-mono">
                                <span v-if="ex.imei">IMEI: {{ ex.imei }}</span>
                                <span v-else>Jumlah: {{ ex.quantity }} unit</span>
                                <span v-if="ex.notes" class="text-surface-500 font-sans italic">— "{{ ex.notes }}"</span>
                            </div>
                        </div>
                        <button type="button" @click="removeExcessItem(idx)" class="p-2 text-rose-400 hover:text-rose-300 hover:bg-rose-500/20 rounded-xl transition-colors cursor-pointer">
                            <Trash2 :size="16" />
                        </button>
                    </div>
                </div>
                <div v-else class="text-xs text-surface-500 italic py-2">
                    Belum ada barang lebih yang dicatat.
                </div>
            </div>

            <!-- Section: Foto Bukti SO & Catatan Umum -->
            <div class="bg-surface-850 rounded-3xl border border-surface-750 p-4 sm:p-6 shadow-xl space-y-4">
                <h2 class="text-base font-black text-white flex items-center gap-2">
                    <Camera :size="18" class="text-emerald-400" />
                    <span>Foto Bukti SO & Catatan Tambahan</span>
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Foto Upload -->
                    <div>
                        <label class="block text-xs font-black text-surface-400 uppercase tracking-wider mb-2">
                            Foto Bukti Fisik Stok
                        </label>

                        <div class="flex items-center gap-3">
                            <label class="flex items-center gap-2 px-4 py-2.5 bg-surface-800 border border-dashed border-surface-700 rounded-xl cursor-pointer hover:border-emerald-500 transition-colors text-xs font-semibold text-white">
                                <Upload :size="16" class="text-emerald-400" />
                                <span>Pilih / Ambil Foto</span>
                                <input type="file" accept="image/*" multiple @change="handlePhotoSelect" class="hidden" />
                            </label>
                            <span class="text-xs text-surface-400">{{ photoPreviews.length }} foto terpilih</span>
                        </div>

                        <!-- Thumbnails Preview -->
                        <div v-if="photoPreviews.length > 0" class="flex flex-wrap gap-2.5 mt-3">
                            <div v-for="(p, pIdx) in photoPreviews" :key="pIdx" class="relative group w-20 h-20 rounded-xl border border-surface-700 overflow-hidden bg-surface-800">
                                <img :src="p" alt="Bukti SO" class="w-full h-full object-cover" />
                                <button type="button" @click="removePhoto(pIdx)"
                                    class="absolute top-1 right-1 p-1 bg-black/80 text-white rounded-full hover:bg-rose-600 transition-colors cursor-pointer">
                                    <X :size="12" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Catatan Umum -->
                    <div>
                        <label class="block text-xs font-black text-surface-400 uppercase tracking-wider mb-2">
                            Catatan Tambahan (Opsional)
                        </label>
                        <textarea v-model="generalNotes" rows="3" placeholder="Tuliskan catatan kondisi fisik stok, temuan khusus, atau kendala toko..."
                            class="w-full text-xs sm:text-sm p-3 bg-surface-800 border border-surface-700 rounded-xl focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 text-white placeholder:text-surface-500"></textarea>
                    </div>
                </div>
            </div>

            <!-- Sticky Bottom Summary & Submit Dock -->
            <div class="sticky bottom-4 z-30 bg-surface-900/95 backdrop-blur-2xl rounded-2xl sm:rounded-3xl border border-surface-750 p-4 sm:p-5 shadow-2xl shadow-black/50 transition-all">
                <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
                    <!-- Summary Metrics Group -->
                    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3 sm:gap-5 w-full lg:w-auto">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-emerald-500/20 to-teal-500/10 text-emerald-400 flex items-center justify-center shrink-0 border border-emerald-500/30">
                                <ClipboardCheck :size="20" />
                            </div>
                            <div>
                                <div class="text-[10px] uppercase font-black text-surface-400 tracking-wider">Hasil Pemeriksaan</div>
                                <div class="text-sm sm:text-base font-black text-white">
                                    {{ summaryStats.totalPhysical }} <span class="font-normal text-surface-400 text-xs">dari {{ summaryStats.totalSystem }} unit</span>
                                </div>
                            </div>
                        </div>

                        <!-- Metrics Badges -->
                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <div class="px-3 py-1.5 rounded-xl bg-surface-800 border border-surface-700 font-semibold text-surface-300 flex items-center gap-1.5">
                                <span>Sistem:</span>
                                <span class="font-bold font-mono text-white">{{ summaryStats.totalSystem }}</span>
                            </div>
                            <div class="px-3 py-1.5 rounded-xl bg-emerald-500/15 border border-emerald-500/30 font-semibold text-emerald-300 flex items-center gap-1.5">
                                <span>Fisik Ada:</span>
                                <span class="font-bold font-mono">{{ summaryStats.totalPhysical }}</span>
                            </div>
                            <div v-if="method === 'by_imei' && summaryStats.totalUnselected > 0" class="px-3 py-1.5 rounded-xl bg-amber-500/15 border border-amber-500/40 font-semibold text-amber-300 flex items-center gap-1.5 animate-pulse">
                                <span>Belum Dipilih:</span>
                                <span class="font-bold font-mono">{{ summaryStats.totalUnselected }}</span>
                            </div>
                            <div v-if="summaryStats.totalMissing > 0" class="px-3 py-1.5 rounded-xl bg-rose-500/15 border border-rose-500/30 font-semibold text-rose-300 flex items-center gap-1.5">
                                <span>Selisih:</span>
                                <span class="font-bold font-mono">-{{ summaryStats.totalMissing }}</span>
                            </div>
                            <div v-if="summaryStats.totalExcess > 0" class="px-3 py-1.5 rounded-xl bg-amber-500/15 border border-amber-500/30 font-semibold text-amber-300 flex items-center gap-1.5">
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
                            class="w-full lg:w-auto px-7 py-3 rounded-2xl bg-surface-800 border border-surface-700 text-surface-500 font-bold text-xs sm:text-sm flex items-center justify-center gap-2 cursor-not-allowed min-h-[48px]">
                            <CheckCircle2 :size="18" class="text-emerald-500" />
                            <span>Sudah Selesai di-SO Hari Ini (Reset 05:00 AM)</span>
                        </button>
                        <button v-else
                            type="button"
                            @click="handleVerifyAndSubmit"
                            :disabled="submitting || (pendingItems.length === 0 && excessItems.length === 0)"
                            class="w-full lg:w-auto px-7 py-3 rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-500 hover:from-emerald-500 hover:to-teal-400 active:from-emerald-700 active:to-teal-600 text-white font-black text-sm shadow-xl shadow-emerald-500/30 hover:shadow-emerald-500/40 transition-all flex items-center justify-center gap-2.5 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer min-h-[48px]">
                            <RefreshCw v-if="submitting" :size="18" class="animate-spin" />
                            <CheckCircle2 v-else :size="18" />
                            <span>{{ submitting ? 'Menyimpan Hasil SO...' : 'Simpan & Selesaikan SO' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Catat Barang Lebih -->
        <div v-if="showExcessModal" class="fixed inset-0 z-[999] flex items-center justify-center p-4 bg-black/80 backdrop-blur-md">
            <div class="bg-surface-850 rounded-3xl max-w-md w-full p-6 border border-surface-750 shadow-2xl space-y-4">
                <div class="flex items-center justify-between border-b border-surface-750 pb-3">
                    <h3 class="text-base font-black text-white flex items-center gap-2">
                        <Plus :size="18" class="text-amber-400" />
                        <span>Tambah Barang Lebih</span>
                    </h3>
                    <button @click="showExcessModal = false" class="p-1.5 rounded-lg text-surface-400 hover:text-white hover:bg-surface-800 transition-colors cursor-pointer">
                        <X :size="18" />
                    </button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block font-bold text-surface-300 uppercase mb-1">Merek / Brand</label>
                        <input type="text" v-model="newExcess.brand" placeholder="Misal: iPhone / Samsung / Ugreen"
                            class="w-full py-2.5 px-3 bg-surface-900 border border-surface-700 rounded-xl text-sm text-white" />
                    </div>

                    <div>
                        <label class="block font-bold text-surface-300 uppercase mb-1">Nama / Tipe Barang *</label>
                        <input type="text" v-model="newExcess.product_name" placeholder="Misal: iPhone 13 / Charger 20W"
                            class="w-full py-2.5 px-3 bg-surface-900 border border-surface-700 rounded-xl text-sm text-white" />
                    </div>

                    <div v-if="categoryType === 'hp'" class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block font-bold text-surface-300 uppercase mb-1">Storage (GB)</label>
                            <input type="text" v-model="newExcess.storage" placeholder="Misal: 128 GB"
                                class="w-full py-2.5 px-3 bg-surface-900 border border-surface-700 rounded-xl text-sm text-white" />
                        </div>
                        <div>
                            <label class="block font-bold text-surface-300 uppercase mb-1">IMEI Unit</label>
                            <input type="text" v-model="newExcess.imei" placeholder="15 digit IMEI"
                                class="w-full py-2.5 px-3 bg-surface-900 border border-surface-700 rounded-xl text-sm text-white font-mono" />
                        </div>
                    </div>

                    <div v-else>
                        <label class="block font-bold text-surface-300 uppercase mb-1">Jumlah (Qty)</label>
                        <input type="number" min="1" v-model.number="newExcess.quantity"
                            class="w-full py-2.5 px-3 bg-surface-900 border border-surface-700 rounded-xl text-sm text-white font-mono" />
                    </div>

                    <div>
                        <label class="block font-bold text-surface-300 uppercase mb-1">Catatan / Keterangan</label>
                        <textarea v-model="newExcess.notes" rows="2" placeholder="Keterangan asal barang lebih (misal: kiriman dari cabang lain)..."
                            class="w-full py-2 px-3 bg-surface-900 border border-surface-700 rounded-xl text-sm text-white"></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-surface-750">
                    <button type="button" @click="showExcessModal = false"
                        class="px-4 py-2.5 rounded-xl text-xs font-bold text-surface-400 hover:bg-surface-800 hover:text-white cursor-pointer">
                        Batal
                    </button>
                    <button type="button" @click="addExcessItem"
                        class="px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-surface-950 font-black text-xs shadow-md cursor-pointer">
                        Simpan Barang Lebih
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal Verifikasi Password CS (Menggunakan Password Login CS) -->
        <PasswordModal v-if="showPasswordModal"
            :show="showPasswordModal"
            mode="password"
            title="Verifikasi Akun CS"
            :description="'Masukkan Password Login Akun CS (' + (activeCsUser?.name || '') + ') untuk melanjutkan.'"
            :user="activeCsUser"
            :loading="verifyingPassword"
            :error="passwordModalError"
            @close="showPasswordModal = false; passwordModalError = ''"
            @success="handlePasswordSuccess" />
    </div>
</template>
