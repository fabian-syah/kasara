<script setup>
import { ref, computed, onMounted, watch, unref } from 'vue';
import { useRouter } from 'vue-router';
import {
    History, Search, Calendar, Building2, User, Filter,
    CheckCircle2, AlertTriangle, Eye, ChevronDown, ChevronRight, ChevronUp,
    RefreshCw, Plus, ArrowLeft, Camera, X, Smartphone, Package, Check, Download,
    Copy, CheckCheck, ListFilter, Layers, Sparkles, ImageOff,
    ShieldCheck, UserCheck, Store, Crown
} from 'lucide-vue-next';
import axios from '../../api/axios';
import { useToast } from '../../composables/useToast';
import { useAuthStore } from '../../store/auth';

const router = useRouter();
const toast = useToast();
const authStore = useAuthStore();

// State
const loading = ref(false);
const records = ref([]);
const branchStockTotal = ref(0);
const collapsedDays = ref({});
const pagination = ref({
    current_page: 1,
    last_page: 1,
    total: 0,
    per_page: 30
});

// Tab Navigation: '' (Semua), 'by_imei' (Stok by IMEI), 'by_unit' (Stok by Unit)
const activeTab = ref('');
// Inside 'by_imei' tab: view mode 'all_imei' (All IMEI unit list) or 'by_session' (Grouped by SO session)
const imeiViewMode = ref('all_imei');
const expandedSessions = ref({});

// Filters
const branches = ref([]);
const csUsers = ref([]);
const getLocalDateStr = () => {
    const d = new Date();
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

// Logical date helper matching the 05:00 AM daily cycle reset
const getLogicalDate = (rec) => {
    if (rec?.date) return String(rec.date).substring(0, 10);
    if (rec?.created_at) {
        const d = new Date(rec.created_at);
        // If hour < 5, belongs to previous day's logical cycle
        if (d.getHours() < 5) {
            d.setDate(d.getDate() - 1);
        }
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
    return getLocalDateStr();
};

const formatDayTitle = (dateStr) => {
    if (!dateStr || dateStr === 'Lainnya') return 'Tanggal Tidak Diketahui';
    try {
        const parts = dateStr.split('-');
        if (parts.length === 3) {
            const dateObj = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
            return dateObj.toLocaleDateString('id-ID', {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric'
            });
        }
        return dateStr;
    } catch (e) {
        return dateStr;
    }
};

const getDayRelativeBadge = (dateStr) => {
    const today = getLogicalDate({ created_at: new Date() });
    if (dateStr === today) {
        return { label: 'Hari Ini (Siklus Aktif)', isToday: true };
    }
    try {
        const d = new Date(dateStr);
        const now = new Date(today);
        const diffDays = Math.round((now - d) / (1000 * 60 * 60 * 24));
        if (diffDays === 1) return { label: 'Kemarin', isToday: false };
        if (diffDays > 1 && diffDays <= 7) return { label: `${diffDays} Hari Lalu`, isToday: false };
    } catch (e) {}
    return { label: '', isToday: false };
};

const toggleDayCollapse = (dateStr) => {
    collapsedDays.value[dateStr] = !collapsedDays.value[dateStr];
};

const filters = ref({
    branch_id: '',
    cs_user_id: '',
    category_type: '',
    method: '',
    status: '',
    executor_role: '', // 'audit', 'leader', 'store', 'admin', '' (Semua)
    date: '', // Default: Semua Tanggal
    search: ''
});

// Detail Modal
const selectedDetail = ref(null);
const loadingDetail = ref(false);
const showDetailModal = ref(false);
const selectedPhotoModal = ref(null);
const modalItemSearch = ref('');
const modalStatusFilter = ref('all'); // 'all', 'ada', 'tidak_ada'
const copiedImei = ref('');

// Copy IMEI helper
const copyImei = (imei) => {
    if (!imei) return;
    navigator.clipboard.writeText(imei);
    copiedImei.value = imei;
    toast.success(`IMEI ${imei} disalin!`);
    setTimeout(() => {
        if (copiedImei.value === imei) copiedImei.value = '';
    }, 2000);
};

// Robust Photo URL resolver
const getPhotoUrl = (p) => {
    if (!p || typeof p !== 'string') return '';
    if (p.startsWith('data:image/') || p.startsWith('blob:')) return p;
    if (p.startsWith('http://') || p.startsWith('https://')) return p;

    let clean = p;
    if (clean.startsWith('/')) clean = clean.substring(1);
    if (clean.startsWith('storage/')) clean = clean.substring(8);

    const rawBase = unref(authStore.storageBaseUrl) || 'https://api.stokps.com';
    const baseUrl = String(rawBase).replace(/\/+$/, '').replace(/\/api\/?$/, '');
    return `${baseUrl}/storage/${clean}`;
};

const getPhotoProxyUrl = (p) => {
    if (!p || typeof p !== 'string') return '';
    if (p.startsWith('data:image/') || p.startsWith('blob:')) return p;
    if (p.startsWith('http://') || p.startsWith('https://')) return p;

    let clean = p;
    if (clean.startsWith('/')) clean = clean.substring(1);
    if (clean.startsWith('storage/')) clean = clean.substring(8);

    const rawBase = unref(authStore.storageBaseUrl) || 'https://api.stokps.com';
    const baseUrl = String(rawBase).replace(/\/+$/, '').replace(/\/api\/?$/, '');
    return `${baseUrl}/api/storage-proxy/${clean}`;
};

const handleImgError = (event, originalPath) => {
    const img = event.target;
    if (!img.dataset.triedProxy && originalPath) {
        img.dataset.triedProxy = 'true';
        img.src = getPhotoProxyUrl(originalPath);
        return;
    }
    if (!img.dataset.triedRelative && originalPath) {
        img.dataset.triedRelative = 'true';
        let clean = originalPath;
        if (!clean.startsWith('/')) clean = '/' + clean;
        img.src = clean;
        return;
    }
    // All failed -> hide img and reveal fallback placeholder
    img.style.display = 'none';
    const parent = img.parentElement;
    if (parent) {
        const fallback = parent.querySelector('.photo-fallback');
        if (fallback) fallback.classList.remove('hidden');
    }
};

// Toggle session expansion in list
const toggleSessionExpand = (id) => {
    expandedSessions.value[id] = !expandedSessions.value[id];
};

// Switch Tab
const setTab = (tab) => {
    activeTab.value = tab;
    filters.value.method = tab;
    fetchHistory(1);
};

// User role check & tier segregation
const userRole = computed(() => (authStore.userRole || '').toLowerCase().trim());

const isLeader = computed(() => {
    const role = userRole.value;
    if (role.includes('leader')) return true;
    if (authStore.user?.role && String(authStore.user.role).toLowerCase().includes('leader')) return true;
    if (Array.isArray(authStore.user?.roles)) {
        return authStore.user.roles.some(r => (typeof r === 'string' ? r : r?.name || '').toLowerCase().includes('leader'));
    }
    return false;
});

const isAudit = computed(() => {
    const role = userRole.value;
    if (role.includes('audit')) return true;
    if (authStore.user?.role && String(authStore.user.role).toLowerCase().includes('audit')) return true;
    if (Array.isArray(authStore.user?.roles)) {
        return authStore.user.roles.some(r => (typeof r === 'string' ? r : r?.name || '').toLowerCase().includes('audit'));
    }
    return false;
});

const isSuperAdminOrOwner = computed(() => {
    const privileged = ['super_admin', 'owner', 'analist', 'analis', 'admin_produk'];
    return privileged.some(r => userRole.value.includes(r));
});

const isCsUser = computed(() => {
    return (typeof authStore.hasRole === 'function' && authStore.hasRole('inventory')) || 
           (authStore.userRole || '').toLowerCase() === 'inventory' ||
           (authStore.user?.roles?.[0]?.name || '').toLowerCase() === 'inventory';
});

const isPrivilegedRole = computed(() => isLeader.value || isAudit.value || isSuperAdminOrOwner.value);

// Helper for record role
const getRecordRole = (rec) => {
    if (rec?.executor_role) return rec.executor_role;
    const uRole = (rec?.user?.role || '').toLowerCase();
    if (uRole.includes('audit')) return 'audit';
    if (uRole.includes('leader')) return 'leader';
    if (uRole.includes('admin') || uRole.includes('owner') || uRole.includes('analist')) return 'admin';
    return 'store';
};

const getRoleLabel = (role) => {
    switch (role) {
        case 'audit': return 'SO Audit';
        case 'leader': return 'SO Leader';
        case 'admin': return 'SO Admin';
        default: return 'SO CS Toko';
    }
};

const getRoleBadgeClass = (role) => {
    switch (role) {
        case 'audit': return 'bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300 border-purple-200 dark:border-purple-800';
        case 'leader': return 'bg-sky-100 dark:bg-sky-900/40 text-sky-700 dark:text-sky-300 border-sky-200 dark:border-sky-800';
        case 'admin': return 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800';
        default: return 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800';
    }
};

// Fetch branches
const fetchBranches = async () => {
    try {
        const res = await axios.get('/inventory/opname/branches');
        branches.value = res.data.data || [];
        if (branches.value.length === 1) {
            filters.value.branch_id = branches.value[0].id;
        }
    } catch (err) {
        console.error('Error fetching branches:', err);
    }
};

// Fetch CS users
const fetchCsUsers = async () => {
    if (!filters.value.branch_id) {
        csUsers.value = [];
        return;
    }
    try {
        const res = await axios.get(`/inventory/opname/branch-cs/${filters.value.branch_id}`);
        csUsers.value = res.data.data || [];
    } catch (err) {
        console.error('Error fetching CS users:', err);
    }
};

// Fetch History Data
const fetchHistory = async (page = 1) => {
    loading.value = true;
    try {
        const params = {
            page,
            per_page: pagination.value.per_page,
            ...filters.value
        };

        // Clean empty parameters
        Object.keys(params).forEach(k => {
            if (params[k] === '' || params[k] === null || params[k] === undefined || params[k] === 'all') {
                delete params[k];
            }
        });

        const res = await axios.get('/inventory/opname/history', { params });
        records.value = res.data.data || [];
        branchStockTotal.value = res.data.branch_stock_total || 0;
        pagination.value = {
            current_page: res.data.current_page || 1,
            last_page: res.data.last_page || 1,
            total: res.data.total || 0,
            per_page: res.data.per_page || 30
        };
    } catch (err) {
        console.error('Error fetching SO history:', err);
        toast.error('Gagal memuat riwayat stok opname.');
    } finally {
        loading.value = false;
    }
};

// Helper to compute normalized totals for any record
const getRecordTotals = (rec) => {
    if (!rec) {
        return { total_system: 0, total_physical: 0, total_missing: 0, total_excess: 0, has_difference: false };
    }

    const items = rec.items || [];
    const excessList = rec.excessItems || rec.excess_items || [];

    if (items.length > 0 || excessList.length > 0) {
        let sys = 0;
        let phys = 0;
        let missing = 0;
        let rowExcess = 0;

        if (rec.method === 'by_imei') {
            sys = items.length;
            items.forEach(it => {
                if (it.is_present || Number(it.physical_qty) > 0) {
                    phys += 1;
                } else {
                    missing += 1;
                }
            });
        } else {
            // by_unit
            items.forEach(it => {
                const s = Number(it.system_qty) || 0;
                const p = Number(it.physical_qty) || 0;
                sys += s;
                phys += p;
                const diff = p - s;
                if (diff < 0) missing += Math.abs(diff);
                else if (diff > 0) rowExcess += diff;
            });
        }

        let modalExcess = 0;
        excessList.forEach(eit => {
            modalExcess += Math.max(1, Number(eit.quantity) || 1);
        });

        const totalExcess = rowExcess + modalExcess;
        const totalPhysical = phys + modalExcess;
        const hasDiff = (missing > 0 || totalExcess > 0);

        return {
            total_system: sys,
            total_physical: totalPhysical,
            total_missing: missing,
            total_excess: totalExcess,
            has_difference: hasDiff
        };
    }

    const s = Number(rec.total_system) || 0;
    const m = Number(rec.total_missing) || 0;
    const e = Number(rec.total_excess) || 0;
    const p = rec.total_physical !== undefined && rec.total_physical !== null
        ? Number(rec.total_physical)
        : (s - m + e);

    return {
        total_system: s,
        total_physical: p,
        total_missing: m,
        total_excess: e,
        has_difference: (m > 0 || e > 0 || rec.status === 'has_difference')
    };
};

// Helper to get all IMEIs in a session (including excess IMEIs)
const getSessionAllImeis = (rec) => {
    if (!rec) return [];
    const list = [];
    if (rec.items && rec.items.length > 0) {
        rec.items.forEach(it => {
            if (it.imei) {
                list.push({ ...it, is_excess: false });
            }
        });
    }
    const excessList = rec.excessItems || rec.excess_items || [];
    excessList.forEach(eit => {
        if (eit.imei) {
            list.push({
                ...eit,
                system_qty: 0,
                physical_qty: Number(eit.quantity) || 1,
                difference: Number(eit.quantity) || 1,
                is_present: true,
                is_excess: true,
                notes: eit.notes || 'Barang Lebih Fisik'
            });
        }
    });
    return list;
};

// Flattened All IMEI list from current records (including excess IMEIs)
const allImeis = computed(() => {
    const list = [];
    records.value.forEach(rec => {
        if (rec.method === 'by_imei') {
            const sessionImeis = getSessionAllImeis(rec);
            sessionImeis.forEach(it => {
                list.push({
                    ...it,
                    so_id: rec.id,
                    opname_number: rec.opname_number,
                    session_date: rec.date,
                    session_created_at: rec.created_at,
                    branch_name: rec.branch?.name || '-',
                    cs_name: rec.cs_user?.name || '-',
                    photos: it.photos || rec.photos || [],
                    session_record: rec
                });
            });
        }
    });
    return list;
});

// All IMEI Filter by status
const imeiStatusFilter = ref('all'); // 'all', 'ada', 'tidak_ada'
const filteredAllImeis = computed(() => {
    let items = allImeis.value;
    if (imeiStatusFilter.value === 'ada') {
        items = items.filter(it => it.is_present && (it.difference >= 0 || it.is_excess));
    } else if (imeiStatusFilter.value === 'tidak_ada') {
        items = items.filter(it => (!it.is_present || it.difference < 0) && !it.is_excess);
    }
    return items;
});

// Grouped Records By Day (per table per hari with daily progress bar & metrics)
const groupedRecordsByDay = computed(() => {
    const groups = {};
    const todayStr = getLogicalDate({ created_at: new Date() });

    records.value.forEach(rec => {
        const dayKey = getLogicalDate(rec);
        if (!groups[dayKey]) {
            groups[dayKey] = {
                date: dayKey,
                records: [],
                totalSystem: 0,
                totalPhysical: 0,
                totalMissing: 0,
                totalExcess: 0,
            };
        }
        groups[dayKey].records.push(rec);
        const t = getRecordTotals(rec);
        groups[dayKey].totalSystem += t.total_system;
        groups[dayKey].totalPhysical += t.total_physical;
        groups[dayKey].totalMissing += t.total_missing;
        groups[dayKey].totalExcess += t.total_excess;
    });

    return Object.values(groups)
        .sort((a, b) => b.date.localeCompare(a.date))
        .map(g => {
            const isToday = g.date === todayStr;
            const hasDiff = g.totalMissing > 0 || g.totalExcess > 0;

            let effectiveTargetUnits = g.totalSystem;
            if (isToday && branchStockTotal.value > 0 && branchStockTotal.value > g.totalSystem) {
                effectiveTargetUnits = branchStockTotal.value;
            }

            let percent = 0;
            if (effectiveTargetUnits > 0) {
                percent = Math.min(100, Math.round((g.totalPhysical / effectiveTargetUnits) * 100));
            } else if (g.totalPhysical > 0) {
                percent = 100;
            }

            let statusLabel = '100% Sesuai';
            if (percent === 100 && !hasDiff) {
                statusLabel = 'Sempurna & Lengkap';
            } else if (hasDiff) {
                statusLabel = `${g.totalMissing > 0 ? g.totalMissing + ' Kurang' : ''}${g.totalExcess > 0 ? ' +' + g.totalExcess + ' Lebih' : ''}`;
            } else if (percent < 100) {
                statusLabel = `${g.totalPhysical} / ${effectiveTargetUnits} Unit`;
            }

            const rel = getDayRelativeBadge(g.date);

            return {
                ...g,
                percent,
                effectiveTargetUnits,
                isToday,
                hasDiff,
                relativeBadge: rel.label,
                statusLabel,
            };
        });
});

// Grouped All IMEIs By Day (when viewing in all_imei mode)
const groupedAllImeisByDay = computed(() => {
    const groups = {};
    const todayStr = getLogicalDate({ created_at: new Date() });

    filteredAllImeis.value.forEach(it => {
        const dayKey = getLogicalDate({ date: it.session_date, created_at: it.session_created_at });
        if (!groups[dayKey]) {
            groups[dayKey] = {
                date: dayKey,
                items: [],
                totalAda: 0,
                totalTidakAda: 0,
            };
        }
        groups[dayKey].items.push(it);
        if (it.is_present && it.difference >= 0) {
            groups[dayKey].totalAda += 1;
        } else {
            groups[dayKey].totalTidakAda += 1;
        }
    });

    return Object.values(groups)
        .sort((a, b) => b.date.localeCompare(a.date))
        .map(g => {
            const total = g.items.length;
            const percent = total > 0 ? Math.round((g.totalAda / total) * 100) : 100;
            const isToday = g.date === todayStr;
            const rel = getDayRelativeBadge(g.date);

            return {
                ...g,
                total,
                percent,
                isToday,
                relativeBadge: rel.label,
            };
        });
});

// View Detail in Modal
const openDetail = async (item) => {
    loadingDetail.value = true;
    showDetailModal.value = true;
    modalItemSearch.value = '';
    modalStatusFilter.value = 'all';
    copiedImei.value = '';
    try {
        const res = await axios.get(`/inventory/opname/history/${item.id}`);
        selectedDetail.value = res.data.data;
    } catch (err) {
        toast.error('Gagal memuat rincian stok opname.');
        showDetailModal.value = false;
    } finally {
        loadingDetail.value = false;
    }
};

// Modal Items Filtered
const filteredDetailItems = computed(() => {
    if (!selectedDetail.value?.items) return [];
    let items = selectedDetail.value.items;

    if (modalStatusFilter.value === 'ada') {
        items = items.filter(it => it.is_present && it.difference >= 0);
    } else if (modalStatusFilter.value === 'tidak_ada') {
        items = items.filter(it => !it.is_present || it.difference < 0);
    }

    if (!modalItemSearch.value) return items;
    const q = modalItemSearch.value.toLowerCase().trim();
    return items.filter(it => {
        return (it.imei && String(it.imei).toLowerCase().includes(q)) ||
               (it.product_name && String(it.product_name).toLowerCase().includes(q)) ||
               (it.brand && String(it.brand).toLowerCase().includes(q)) ||
               (it.storage && String(it.storage).toLowerCase().includes(q)) ||
               (it.condition && String(it.condition).toLowerCase().includes(q)) ||
               (it.notes && String(it.notes).toLowerCase().includes(q));
    });
});

// Format Date & Time
const formatDateTime = (dateStr) => {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const resetFilters = () => {
    let defaultRole = '';
    if (isAudit.value) defaultRole = 'audit';
    else if (isLeader.value) defaultRole = 'leader';
    else if (!isSuperAdminOrOwner.value) defaultRole = 'store';

    filters.value = {
        branch_id: branches.value.length === 1 ? branches.value[0].id : '',
        cs_user_id: isCsUser.value && authStore.user?.id ? authStore.user.id : '',
        category_type: '',
        method: activeTab.value,
        status: '',
        executor_role: defaultRole,
        date: '',
        search: ''
    };
    fetchHistory(1);
};

onMounted(async () => {
    if (!authStore.user && typeof authStore.fetchUser === 'function') {
        try {
            await authStore.fetchUser();
        } catch (e) {}
    }

    if (isCsUser.value && authStore.user?.id) {
        filters.value.cs_user_id = authStore.user.id;
    }

    // Role-specific initial filter: Audit defaults to Audit history, Leader defaults to Leader, Store defaults to Store
    if (isAudit.value) {
        filters.value.executor_role = 'audit';
    } else if (isLeader.value) {
        filters.value.executor_role = 'leader';
    } else if (!isSuperAdminOrOwner.value) {
        filters.value.executor_role = 'store';
    }

    await fetchBranches();
    await fetchHistory();
});

watch(() => filters.value.branch_id, () => {
    fetchCsUsers();
    fetchHistory(1);
});

watch(() => filters.value.date, () => {
    fetchHistory(1);
});

watch(() => filters.value.status, () => {
    fetchHistory(1);
});

watch(() => filters.value.cs_user_id, () => {
    fetchHistory(1);
});

watch(() => filters.value.executor_role, () => {
    fetchHistory(1);
});
</script>

<template>
    <div class="space-y-5 pb-8">
        <!-- Top Navigation & Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white dark:!bg-surface-800 p-4 sm:p-6 rounded-2xl border border-gray-100 dark:border-surface-700 shadow-sm">
            <div>
                <div class="flex items-center gap-2.5">
                    <button @click="router.back()" class="p-2 rounded-xl text-gray-500 hover:text-gray-900 hover:bg-gray-100 dark:hover:bg-surface-700 transition-colors">
                        <ArrowLeft :size="20" />
                    </button>
                    <h1 class="text-xl sm:text-2xl font-bold text-text-primary tracking-tight">
                        History Stok Opname
                    </h1>
                </div>
                <p class="text-text-secondary text-xs sm:text-sm mt-1 ml-10">
                    Riwayat laporan dan hasil audit fisik stok toko harian (Reset 05:00 AM)
                </p>
            </div>

            <div class="flex items-center gap-2 self-stretch sm:self-auto">
                <button type="button" @click="fetchHistory(pagination.current_page)" :disabled="loading"
                    class="flex items-center justify-center gap-2 px-3.5 py-2.5 rounded-xl border border-gray-200 dark:border-surface-600 bg-white dark:!bg-surface-700 text-xs sm:text-sm font-semibold text-text-primary hover:border-emerald-500 transition-all shadow-sm">
                    <RefreshCw :size="15" :class="{ 'animate-spin': loading }" />
                    <span class="hidden sm:inline">Segarkan</span>
                </button>
                <router-link to="/inventory/opname"
                    class="flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs sm:text-sm font-bold shadow-sm shadow-emerald-600/30 transition-all flex-1 sm:flex-initial">
                    <Plus :size="16" />
                    <span>Mulai Stok Opname</span>
                </router-link>
            </div>
        </div>

        <!-- TAB NAVIGATION: Semua Sesi vs Stok By IMEI vs Stok By Unit -->
        <div class="bg-white dark:!bg-surface-800 p-2 sm:p-2.5 rounded-2xl border border-gray-100 dark:border-surface-700 shadow-sm flex items-center gap-2 overflow-x-auto no-scrollbar">
            <!-- Tab: Semua -->
            <button type="button" @click="setTab('')"
                class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs sm:text-sm font-bold transition-all shrink-0 cursor-pointer"
                :class="activeTab === '' ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-600/30' : 'text-text-secondary hover:text-text-primary hover:bg-gray-100 dark:hover:bg-surface-700'">
                <History :size="16" />
                <span>Semua Sesi</span>
            </button>

            <!-- Tab: Stok by IMEI -->
            <button type="button" @click="setTab('by_imei')"
                class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs sm:text-sm font-bold transition-all shrink-0 cursor-pointer"
                :class="activeTab === 'by_imei' ? 'bg-purple-600 text-white shadow-sm shadow-purple-600/30' : 'text-text-secondary hover:text-text-primary hover:bg-purple-50 dark:hover:bg-surface-700'">
                <Smartphone :size="16" />
                <span>Stok by IMEI (Checklist)</span>
                <span class="text-[10px] font-extrabold uppercase px-1.5 py-0.2 rounded"
                    :class="activeTab === 'by_imei' ? 'bg-purple-700/80 text-white' : 'bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300'">
                    IMEI
                </span>
            </button>

            <!-- Tab: Stok by Unit -->
            <button type="button" @click="setTab('by_unit')"
                class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs sm:text-sm font-bold transition-all shrink-0 cursor-pointer"
                :class="activeTab === 'by_unit' ? 'bg-blue-600 text-white shadow-sm shadow-blue-600/30' : 'text-text-secondary hover:text-text-primary hover:bg-blue-50 dark:hover:bg-surface-700'">
                <Package :size="16" />
                <span>Stok by Unit</span>
                <span class="text-[10px] font-extrabold uppercase px-1.5 py-0.2 rounded"
                    :class="activeTab === 'by_unit' ? 'bg-blue-700/80 text-white' : 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300'">
                    Unit
                </span>
            </button>
        </div>

        <!-- ROLE TIER SEGMENTED SELECTOR: Scoped History by Role Tier (Audit, Leader, Store) -->
        <div v-if="isPrivilegedRole" class="bg-white dark:!bg-surface-800 p-2 sm:p-2.5 rounded-2xl border border-gray-100 dark:border-surface-700 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
            <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar py-0.5">
                <span class="text-[10px] font-black uppercase text-text-secondary tracking-wider pl-2 pr-1 shrink-0">
                    Kategori Laporan:
                </span>

                <!-- Filter: Semua Tipe -->
                <button type="button" @click="filters.executor_role = ''"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold transition-all shrink-0 cursor-pointer"
                    :class="filters.executor_role === '' ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900 shadow-sm' : 'text-text-secondary hover:text-text-primary hover:bg-gray-100 dark:hover:bg-surface-700'">
                    <span>Semua Tipe</span>
                </button>

                <!-- Filter: SO Audit (Audit, Leader, Super Admin) -->
                <button v-if="isAudit || isSuperAdminOrOwner || isLeader" type="button" @click="filters.executor_role = 'audit'"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold transition-all shrink-0 cursor-pointer"
                    :class="filters.executor_role === 'audit' ? 'bg-purple-600 text-white shadow-sm shadow-purple-600/30' : 'text-purple-700 dark:text-purple-300 hover:bg-purple-50 dark:hover:bg-purple-950/40'">
                    <ShieldCheck :size="14" />
                    <span>SO Audit</span>
                    <span v-if="isAudit" class="text-[9px] font-extrabold px-1.5 py-0.2 rounded bg-purple-700/80 text-white uppercase">Saya</span>
                </button>

                <!-- Filter: SO Leader -->
                <button v-if="isLeader || isSuperAdminOrOwner || isAudit" type="button" @click="filters.executor_role = 'leader'"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold transition-all shrink-0 cursor-pointer"
                    :class="filters.executor_role === 'leader' ? 'bg-sky-600 text-white shadow-sm shadow-sky-600/30' : 'text-sky-700 dark:text-sky-300 hover:bg-sky-50 dark:hover:bg-sky-950/40'">
                    <UserCheck :size="14" />
                    <span>SO Leader</span>
                    <span v-if="isLeader" class="text-[9px] font-extrabold px-1.5 py-0.2 rounded bg-sky-700/80 text-white uppercase">Saya</span>
                </button>

                <!-- Filter: SO CS Toko -->
                <button type="button" @click="filters.executor_role = 'store'"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold transition-all shrink-0 cursor-pointer"
                    :class="filters.executor_role === 'store' ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-600/30' : 'text-emerald-700 dark:text-emerald-300 hover:bg-emerald-50 dark:hover:bg-emerald-950/40'">
                    <Store :size="14" />
                    <span>SO CS Toko</span>
                </button>
            </div>

            <!-- Context Info -->
            <div class="text-[11px] text-text-secondary px-2 flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full"
                    :class="filters.executor_role === 'audit' ? 'bg-purple-500' : filters.executor_role === 'leader' ? 'bg-sky-500' : filters.executor_role === 'store' ? 'bg-emerald-500' : 'bg-gray-400'"></span>
                <span v-if="filters.executor_role === 'audit'" class="text-purple-600 dark:text-purple-400 font-semibold">Riwayat pemeriksaan fisik tim Audit</span>
                <span v-else-if="filters.executor_role === 'leader'" class="text-sky-600 dark:text-sky-400 font-semibold">Riwayat supervisi stok Leader</span>
                <span v-else-if="filters.executor_role === 'store'" class="text-emerald-600 dark:text-emerald-400 font-semibold">Riwayat stok opname harian CS Toko</span>
                <span v-else>Riwayat gabungan seluruh peran (Audit, Leader, Toko)</span>
            </div>
        </div>

        <!-- Filter Controls -->
        <div class="bg-white dark:!bg-surface-800 p-4 sm:p-5 rounded-2xl border border-gray-100 dark:border-surface-700 shadow-sm space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                <!-- Tanggal -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-[11px] font-bold text-text-secondary uppercase">Tanggal</label>
                        <button v-if="filters.date" @click="filters.date = ''" type="button" class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold hover:underline cursor-pointer">
                            Semua Tanggal
                        </button>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <input type="date" v-model="filters.date"
                            class="flex-1 min-w-0 py-2 px-3 bg-white dark:!bg-surface-700 border border-gray-200 dark:border-surface-600 rounded-xl text-xs sm:text-sm font-medium text-text-primary focus:ring-1 focus:ring-emerald-500" />
                        <button type="button" @click="filters.date = getLocalDateStr()"
                            :class="filters.date === getLocalDateStr() ? 'bg-emerald-600 text-white' : 'bg-gray-100 dark:bg-surface-700 text-text-secondary hover:text-text-primary'"
                            class="px-2.5 py-2 rounded-xl text-xs font-bold transition-all shrink-0 cursor-pointer"
                            title="Filter hanya hari ini">
                            Hari Ini
                        </button>
                    </div>
                </div>

                <!-- Cabang (Jika Privileged) -->
                <div>
                    <label class="block text-[11px] font-bold text-text-secondary uppercase mb-1">Cabang</label>
                    <div class="relative">
                        <select v-model="filters.branch_id" :disabled="!isPrivilegedRole && branches.length <= 1"
                            class="w-full appearance-none py-2 px-3 pr-8 bg-white dark:!bg-surface-700 border border-gray-200 dark:border-surface-600 rounded-xl text-xs sm:text-sm font-medium text-text-primary focus:ring-1 focus:ring-emerald-500 cursor-pointer">
                            <option value="" class="bg-white text-gray-900 dark:bg-surface-800 dark:text-white">Semua Cabang</option>
                            <option v-for="b in branches" :key="b.id" :value="b.id" class="bg-white text-gray-900 dark:bg-surface-800 dark:text-white">{{ b.name }}</option>
                        </select>
                        <ChevronDown :size="14" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
                    </div>
                </div>

                <!-- CS Pelaksana -->
                <div>
                    <label class="block text-[11px] font-bold text-text-secondary uppercase mb-1">CS Pelaksana</label>
                    <div class="relative">
                        <!-- If CS user, locked to themselves -->
                        <div v-if="isCsUser" class="w-full py-2 px-3 bg-gray-50 dark:!bg-surface-700/60 border border-gray-200 dark:border-surface-600 rounded-xl text-xs sm:text-sm font-semibold text-emerald-600 dark:text-emerald-400 flex items-center justify-between">
                            <span class="truncate">{{ authStore.user?.name || authStore.user?.username || 'Akun Saya' }}</span>
                            <span class="text-[10px] uppercase tracking-wider px-1.5 py-0.5 rounded bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-bold">Akun Saya</span>
                        </div>
                        <template v-else>
                            <select v-model="filters.cs_user_id"
                                class="w-full appearance-none py-2 px-3 pr-8 bg-white dark:!bg-surface-700 border border-gray-200 dark:border-surface-600 rounded-xl text-xs sm:text-sm font-medium text-text-primary focus:ring-1 focus:ring-emerald-500 cursor-pointer">
                                <option value="" class="bg-white text-gray-900 dark:bg-surface-800 dark:text-white">Semua CS</option>
                                <option v-for="cs in csUsers" :key="cs.id" :value="cs.id" class="bg-white text-gray-900 dark:bg-surface-800 dark:text-white">{{ cs.name }}</option>
                            </select>
                            <ChevronDown :size="14" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
                        </template>
                    </div>
                </div>

                <!-- Tipe Pelaksana (Jika Privileged) -->
                <div>
                    <label class="block text-[11px] font-bold text-text-secondary uppercase mb-1">Tipe Pelaksana</label>
                    <div class="relative">
                        <select v-model="filters.executor_role"
                            class="w-full appearance-none py-2 px-3 pr-8 bg-white dark:!bg-surface-700 border border-gray-200 dark:border-surface-600 rounded-xl text-xs sm:text-sm font-medium text-text-primary focus:ring-1 focus:ring-emerald-500 cursor-pointer">
                            <option value="" class="bg-white text-gray-900 dark:bg-surface-800 dark:text-white">Semua Tipe Pelaksana</option>
                            <option value="audit" class="bg-white text-gray-900 dark:bg-surface-800 dark:text-white">SO Audit 🛡️</option>
                            <option value="leader" class="bg-white text-gray-900 dark:bg-surface-800 dark:text-white">SO Leader 👤</option>
                            <option value="store" class="bg-white text-gray-900 dark:bg-surface-800 dark:text-white">SO CS Toko 🏪</option>
                        </select>
                        <ChevronDown :size="14" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
                    </div>
                </div>

                <!-- Status Hasil -->
                <div>
                    <label class="block text-[11px] font-bold text-text-secondary uppercase mb-1">Status Hasil</label>
                    <div class="relative">
                        <select v-model="filters.status"
                            class="w-full appearance-none py-2 px-3 pr-8 bg-white dark:!bg-surface-700 border border-gray-200 dark:border-surface-600 rounded-xl text-xs sm:text-sm font-medium text-text-primary focus:ring-1 focus:ring-emerald-500 cursor-pointer">
                            <option value="" class="bg-white text-gray-900 dark:bg-surface-800 dark:text-white">Semua Status</option>
                            <option value="completed" class="bg-white text-gray-900 dark:bg-surface-800 dark:text-white">Cocok (Lengkap) ✅</option>
                            <option value="has_difference" class="bg-white text-gray-900 dark:bg-surface-800 dark:text-white">Ada Selisih ⚠️</option>
                        </select>
                        <ChevronDown :size="14" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
                    </div>
                </div>
            </div>

            <!-- Search Bar -->
            <div class="pt-1 flex items-center gap-2">
                <div class="relative flex-1">
                    <input type="text" v-model="filters.search" @keydown.enter="fetchHistory(1)"
                        placeholder="Cari No SO / Brand / Tipe / Nomor IMEI / CS..."
                        class="w-full py-2 pl-9 pr-4 bg-gray-50 dark:bg-surface-700/50 border border-gray-200 dark:border-surface-600 rounded-xl text-xs sm:text-sm text-text-primary placeholder:text-gray-400 focus:ring-1 focus:ring-emerald-500" />
                    <Search :size="15" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
                </div>
                <button type="button" @click="fetchHistory(1)"
                    class="px-4 py-2 rounded-xl bg-gray-100 dark:bg-surface-700 hover:bg-gray-200 dark:hover:bg-surface-600 text-text-primary text-xs font-bold transition-all">
                    Cari
                </button>
                <button type="button" @click="resetFilters"
                    class="px-3 py-2 rounded-xl border border-gray-200 dark:border-surface-600 text-text-secondary hover:text-text-primary text-xs font-semibold cursor-pointer">
                    Reset
                </button>
            </div>
        </div>

        <!-- SPECIAL VIEW: Jika di Tab 'by_imei', tampilkan mode switcher (All IMEI vs Per Sesi) -->
        <div v-if="activeTab === 'by_imei'" class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 bg-purple-50/50 dark:bg-purple-950/20 p-3.5 rounded-2xl border border-purple-100 dark:border-purple-900/40">
            <div class="flex items-center gap-2">
                <Smartphone :size="18" class="text-purple-600 dark:text-purple-400 shrink-0" />
                <div>
                    <span class="text-xs font-bold text-text-primary">Tampilan Hasil Stok by IMEI</span>
                    <p class="text-[11px] text-text-secondary">Pilih mode tampilan untuk melihat seluruh unit IMEI atau per sesi</p>
                </div>
            </div>

            <div class="flex items-center gap-1.5 p-1 bg-white dark:bg-surface-800 rounded-xl border border-gray-200 dark:border-surface-700 self-stretch sm:self-auto">
                <button type="button" @click="imeiViewMode = 'all_imei'"
                    class="flex-1 sm:flex-initial flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all"
                    :class="imeiViewMode === 'all_imei' ? 'bg-purple-600 text-white shadow-xs' : 'text-text-secondary hover:text-text-primary'">
                    <ListFilter :size="14" />
                    <span>Rekap Semua Unit IMEI ({{ allImeis.length }})</span>
                </button>
                <button type="button" @click="imeiViewMode = 'by_session'"
                    class="flex-1 sm:flex-initial flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all"
                    :class="imeiViewMode === 'by_session' ? 'bg-purple-600 text-white shadow-xs' : 'text-text-secondary hover:text-text-primary'">
                    <Layers :size="14" />
                    <span>Per Sesi Selesai ({{ records.length }})</span>
                </button>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="bg-white dark:!bg-surface-800 rounded-2xl border border-gray-100 dark:border-surface-700 shadow-sm py-16 flex flex-col items-center justify-center text-gray-400 gap-2">
            <RefreshCw :size="24" class="animate-spin text-emerald-500" />
            <span class="text-xs font-medium">Memuat riwayat stok opname...</span>
        </div>

        <!-- Empty State -->
        <div v-else-if="records.length === 0" class="bg-white dark:!bg-surface-800 rounded-2xl border border-gray-100 dark:border-surface-700 shadow-sm py-16 text-center text-text-secondary">
            <History :size="36" class="mx-auto text-gray-300 dark:text-gray-600 mb-2" />
            <p class="text-sm font-semibold text-text-primary">Belum ada riwayat Stok Opname</p>
            <p class="text-xs text-text-secondary mt-1">Gunakan filter lain atau mulai sesi Stok Opname baru.</p>
        </div>

        <!-- CONTENT SECTION 1: ALL IMEI VIEW (Grouped Per Hari with Progress Bar & Details) -->
        <div v-else-if="activeTab === 'by_imei' && imeiViewMode === 'all_imei'" class="space-y-6">
            <!-- Filter Pill: Semua, Ada, Tidak Ada -->
            <div class="flex flex-wrap items-center justify-between gap-3 bg-white dark:!bg-surface-800 p-3.5 rounded-2xl border border-gray-100 dark:border-surface-700 shadow-sm">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-text-secondary uppercase tracking-wider">Status Fisik:</span>
                    <div class="inline-flex rounded-lg border border-gray-200 dark:border-surface-700 p-0.5 text-xs font-bold bg-gray-50 dark:bg-surface-700">
                        <button type="button" @click="imeiStatusFilter = 'all'"
                            class="px-3 py-1 rounded-md transition-colors cursor-pointer"
                            :class="imeiStatusFilter === 'all' ? 'bg-white dark:bg-surface-800 text-text-primary shadow-xs' : 'text-text-secondary hover:text-text-primary'">
                            Semua ({{ allImeis.length }})
                        </button>
                        <button type="button" @click="imeiStatusFilter = 'ada'"
                            class="px-3 py-1 rounded-md transition-colors flex items-center gap-1 cursor-pointer"
                            :class="imeiStatusFilter === 'ada' ? 'bg-emerald-600 text-white shadow-xs' : 'text-emerald-700 dark:text-emerald-400 hover:text-emerald-800'">
                            <Check :size="12" />
                            <span>Ada ({{ allImeis.filter(i => i.is_present && i.difference >= 0).length }})</span>
                        </button>
                        <button type="button" @click="imeiStatusFilter = 'tidak_ada'"
                            class="px-3 py-1 rounded-md transition-colors flex items-center gap-1 cursor-pointer"
                            :class="imeiStatusFilter === 'tidak_ada' ? 'bg-rose-600 text-white shadow-xs' : 'text-rose-700 dark:text-rose-400 hover:text-rose-800'">
                            <X :size="12" />
                            <span>Tidak Ada ({{ allImeis.filter(i => !i.is_present || i.difference < 0).length }})</span>
                        </button>
                    </div>
                </div>

                <span class="text-xs text-text-secondary">
                    Menampilkan <strong>{{ filteredAllImeis.length }}</strong> unit IMEI
                </span>
            </div>

            <!-- Empty State for All IMEI -->
            <div v-if="filteredAllImeis.length === 0" class="bg-white dark:!bg-surface-800 rounded-2xl border border-gray-100 dark:border-surface-700 p-12 text-center text-text-secondary">
                <History :size="32" class="mx-auto text-gray-300 dark:text-gray-600 mb-2" />
                <p class="text-sm font-semibold text-text-primary">Tidak ada data unit IMEI untuk filter ini</p>
            </div>

            <!-- Per Day Grouped Table for All IMEI -->
            <div v-else v-for="day in groupedAllImeisByDay" :key="day.date"
                class="bg-white dark:!bg-surface-800 rounded-3xl border border-gray-100 dark:border-surface-700 shadow-sm overflow-hidden transition-all duration-300">
                <!-- Day Header & Progress Bar -->
                <div class="p-4 sm:p-5 border-b border-gray-100 dark:border-surface-700/80 bg-gradient-to-r from-purple-50/40 via-white to-gray-50/60 dark:from-surface-800/90 dark:via-surface-800 dark:to-surface-850/80">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="flex items-center gap-2.5">
                            <div class="w-10 h-10 rounded-xl bg-purple-100 dark:bg-purple-900/40 text-purple-600 dark:text-purple-400 flex items-center justify-center shrink-0 border border-purple-200 dark:border-purple-800">
                                <Smartphone :size="18" />
                            </div>
                            <div>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h3 class="text-sm sm:text-base font-black text-text-primary">
                                        {{ formatDayTitle(day.date) }}
                                    </h3>
                                    <span v-if="day.isToday" class="text-[9px] font-black uppercase px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-300/60">
                                        Hari Ini
                                    </span>
                                    <span v-else-if="day.relativeBadge" class="text-[9px] font-bold px-2 py-0.5 rounded-md bg-gray-100 dark:bg-surface-700 text-text-secondary border border-gray-200 dark:border-surface-600">
                                        {{ day.relativeBadge }}
                                    </span>
                                </div>
                                <p class="text-[11px] text-text-secondary mt-0.5">
                                    {{ day.total }} unit diperiksa • {{ day.totalAda }} Ada • {{ day.totalTidakAda }} Tidak Ada
                                </p>
                            </div>
                        </div>

                        <!-- Progress Bar Pill -->
                        <div class="flex items-center gap-2.5 self-end sm:self-center">
                            <div class="text-right">
                                <span class="text-xs font-black font-mono" :class="day.percent === 100 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'">
                                    {{ day.percent }}% Ada
                                </span>
                                <div class="text-[10px] text-text-secondary font-medium">
                                    {{ day.totalAda }}/{{ day.total }} Unit
                                </div>
                            </div>
                            <button type="button" @click="toggleDayCollapse(day.date)"
                                class="p-1.5 rounded-lg border border-gray-200 dark:border-surface-600 hover:bg-gray-100 dark:hover:bg-surface-700 text-text-secondary cursor-pointer">
                                <ChevronUp v-if="!collapsedDays[day.date]" :size="16" />
                                <ChevronDown v-else :size="16" />
                            </button>
                        </div>
                    </div>

                    <!-- Progress Bar Track -->
                    <div class="mt-3 w-full h-2 rounded-full bg-gray-100 dark:bg-surface-700 overflow-hidden border border-gray-200/60 dark:border-surface-600">
                        <div class="h-full rounded-full transition-all duration-500"
                            :class="day.percent === 100 ? 'bg-emerald-500' : 'bg-gradient-to-r from-amber-500 to-rose-500'"
                            :style="{ width: `${day.percent}%` }"></div>
                    </div>
                </div>

                <!-- Day Content (Toggled) -->
                <div v-show="!collapsedDays[day.date]">
                    <!-- Desktop Table (>= md) -->
                    <div class="hidden md:block overflow-x-auto">
                        <table class="w-full text-left border-collapse min-w-[850px] text-xs">
                            <thead>
                                <tr class="border-b border-gray-100 dark:border-surface-700 bg-gray-50/60 dark:bg-surface-700/40 text-[11px] font-bold text-text-secondary uppercase">
                                    <th class="py-3 px-3.5 w-10 text-center">No</th>
                                    <th class="py-3 px-4">Tipe & Brand</th>
                                    <th class="py-3 px-4">Nomor IMEI</th>
                                    <th class="py-3 px-3 text-center">GB</th>
                                    <th class="py-3 px-3 text-center">Kondisi</th>
                                    <th class="py-3 px-3 text-center">Status Fisik</th>
                                    <th class="py-3 px-4">Cabang & CS</th>
                                    <th class="py-3 px-4">No SO & Waktu</th>
                                    <th class="py-3 px-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-surface-700">
                                <tr v-for="(it, idx) in day.items" :key="it.id || idx" class="hover:bg-purple-50/20 dark:hover:bg-surface-700/30 transition-colors">
                                    <td class="py-3 px-3.5 text-center font-mono text-gray-400 text-[11px]">{{ idx + 1 }}</td>
                                    <td class="py-3 px-4 font-semibold text-text-primary">
                                        <div class="font-bold">{{ it.product_name }}</div>
                                        <div v-if="it.brand" class="text-[10px] text-text-secondary">{{ it.brand }}</div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="flex items-center gap-1.5 font-mono font-bold text-text-primary text-xs">
                                            <span>{{ it.imei }}</span>
                                            <button type="button" @click="copyImei(it.imei)" :title="'Salin IMEI: ' + it.imei"
                                                class="p-1 rounded hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-400 hover:text-emerald-600 transition-colors cursor-pointer">
                                                <CheckCheck v-if="copiedImei === it.imei" :size="13" class="text-emerald-500" />
                                                <Copy v-else :size="13" />
                                            </button>
                                        </div>
                                        <div v-if="it.notes" class="text-[10px] text-gray-400 italic">"{{ it.notes }}"</div>
                                    </td>
                                    <td class="py-3 px-3 text-center font-mono font-bold">{{ it.storage || '-' }}</td>
                                    <td class="py-3 px-3 text-center">
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full capitalize"
                                            :class="it.condition === 'new' ? 'bg-cyan-100 dark:bg-cyan-950/50 text-cyan-800 dark:text-cyan-300' : 'bg-gray-100 dark:bg-surface-700 text-text-secondary'">
                                            {{ it.condition || 'second' }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 text-center">
                                        <span v-if="it.is_present && it.difference >= 0"
                                            class="inline-flex items-center gap-1 text-[11px] font-extrabold text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/60 px-2 py-0.5 rounded-full border border-emerald-200/60 dark:border-emerald-800/60">
                                            <Check :size="12" />
                                            <span>Ada</span>
                                        </span>
                                        <span v-else
                                            class="inline-flex items-center gap-1 text-[11px] font-extrabold text-rose-700 dark:text-rose-300 bg-rose-50 dark:bg-rose-950/60 px-2 py-0.5 rounded-full border border-rose-200/60 dark:border-rose-800/60">
                                            <X :size="12" />
                                            <span>Tidak Ada</span>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="font-medium text-text-primary">{{ it.branch_name }}</div>
                                        <div class="text-[10px] text-text-secondary flex items-center gap-1.5 mt-0.5">
                                            <span :class="getRoleBadgeClass(getRecordRole(it.session_record))" class="text-[9px] font-extrabold px-1.5 py-0.2 rounded border">
                                                {{ getRoleLabel(getRecordRole(it.session_record)) }}
                                            </span>
                                            <span>CS: {{ it.cs_name }}</span>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="font-mono text-[11px] font-bold text-text-primary">{{ it.opname_number }}</div>
                                        <div class="text-[10px] text-text-secondary">{{ formatDateTime(it.session_created_at) }}</div>
                                    </td>
                                    <td class="py-3 px-3 text-right">
                                        <button type="button" @click="openDetail(it.session_record)"
                                            class="px-2.5 py-1.5 rounded-lg border border-gray-200 dark:border-surface-600 text-xs font-bold text-text-primary hover:border-purple-500 hover:text-purple-600 transition-colors inline-flex items-center gap-1">
                                            <Eye :size="13" />
                                            <span>Sesi</span>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Cards (< md) -->
                    <div class="md:hidden divide-y divide-gray-100 dark:divide-surface-700">
                        <div v-for="(it, idx) in day.items" :key="it.id || idx" class="p-4 space-y-3">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <div class="font-bold text-sm text-text-primary">{{ it.product_name }}</div>
                                    <div class="text-xs text-text-secondary flex items-center gap-1.5 mt-0.5">
                                        <span v-if="it.brand" class="bg-gray-100 dark:bg-surface-700 px-1.5 py-0.5 rounded text-[10px]">{{ it.brand }}</span>
                                        <span class="font-mono">{{ it.storage || '-' }}</span>
                                        <span>•</span>
                                        <span class="capitalize">{{ it.condition || 'second' }}</span>
                                    </div>
                                </div>

                                <span v-if="it.is_present && it.difference >= 0"
                                    class="inline-flex items-center gap-1 text-[11px] font-extrabold text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/60 px-2.5 py-1 rounded-full border border-emerald-200/60 dark:border-emerald-800/60 shrink-0">
                                    <Check :size="12" />
                                    <span>Ada</span>
                                </span>
                                <span v-else
                                    class="inline-flex items-center gap-1 text-[11px] font-extrabold text-rose-700 dark:text-rose-300 bg-rose-50 dark:bg-rose-950/60 px-2.5 py-1 rounded-full border border-rose-200/60 dark:border-rose-800/60 shrink-0">
                                    <X :size="12" />
                                    <span>Tidak Ada</span>
                                </span>
                            </div>

                            <div class="p-3 bg-gray-50 dark:bg-surface-700/60 rounded-xl border border-gray-200/60 dark:border-surface-600 flex items-center justify-between gap-2">
                                <div>
                                    <div class="text-[10px] uppercase font-bold text-text-secondary">Nomor IMEI Fisik</div>
                                    <div class="font-mono font-bold text-sm text-text-primary tracking-wide select-all">{{ it.imei }}</div>
                                </div>
                                <button type="button" @click="copyImei(it.imei)"
                                    class="px-2.5 py-1.5 rounded-lg bg-white dark:bg-surface-800 border border-gray-200 dark:border-surface-600 text-xs font-bold text-text-primary hover:border-emerald-500 hover:text-emerald-600 transition-all flex items-center gap-1 shrink-0">
                                    <CheckCheck v-if="copiedImei === it.imei" :size="13" class="text-emerald-500" />
                                    <Copy v-else :size="13" />
                                    <span>{{ copiedImei === it.imei ? 'Tersalin' : 'Salin' }}</span>
                                </button>
                            </div>

                            <div class="flex items-center justify-between pt-1 border-t border-gray-100 dark:border-surface-700 text-xs">
                                <div class="space-y-0.5">
                                    <div class="font-medium text-text-primary flex items-center gap-1">
                                        <Building2 :size="12" class="text-emerald-500" />
                                        <span>{{ it.branch_name }}</span>
                                    </div>
                                    <div class="text-[11px] text-text-secondary flex items-center gap-1.5 flex-wrap">
                                        <span :class="getRoleBadgeClass(getRecordRole(it.session_record))" class="text-[9px] font-extrabold px-1.5 py-0.2 rounded border">
                                            {{ getRoleLabel(getRecordRole(it.session_record)) }}
                                        </span>
                                        <span>CS: {{ it.cs_name }} • {{ formatDateTime(it.session_created_at) }}</span>
                                    </div>
                                </div>

                                <button type="button" @click="openDetail(it.session_record)"
                                    class="px-3 py-1.5 rounded-lg bg-gray-100 dark:bg-surface-700 hover:bg-gray-200 dark:hover:bg-surface-600 text-xs font-bold text-text-primary transition-colors flex items-center gap-1">
                                    <Eye :size="13" />
                                    <span>Rincian</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CONTENT SECTION 2: PER SESSION VIEW (Grouped Per Hari with Progress Bar & Rincian) -->
        <div v-else class="space-y-6">
            <div v-for="day in groupedRecordsByDay" :key="day.date"
                class="bg-white dark:!bg-surface-800 rounded-3xl border border-gray-100 dark:border-surface-700 shadow-sm overflow-hidden transition-all duration-300">

                <!-- DAY HEADER & PROGRESS BAR & SUMMARY -->
                <div class="p-4 sm:p-6 border-b border-gray-100 dark:border-surface-700/80 bg-gradient-to-r from-gray-50/80 via-white to-gray-50/60 dark:from-surface-800/90 dark:via-surface-800 dark:to-surface-850/80">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                        <!-- Left: Date Title & Badges -->
                        <div class="flex items-start sm:items-center gap-3">
                            <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-emerald-500/20 via-teal-500/15 to-emerald-600/10 dark:from-emerald-500/30 dark:to-teal-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0 border border-emerald-500/20 shadow-xs">
                                <Calendar :size="20" />
                            </div>
                            <div>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h2 class="text-base sm:text-lg font-black text-text-primary tracking-tight">
                                        {{ formatDayTitle(day.date) }}
                                    </h2>
                                    <!-- Badge Relative Day -->
                                    <span v-if="day.isToday" class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-300/60 dark:border-emerald-700/60 shadow-xs">
                                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                        <span>Hari Ini • Siklus Aktif</span>
                                    </span>
                                    <span v-else-if="day.relativeBadge" class="text-[10px] font-bold px-2 py-0.5 rounded-md bg-gray-100 dark:bg-surface-700 text-text-secondary border border-gray-200 dark:border-surface-600">
                                        {{ day.relativeBadge }}
                                    </span>
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-md bg-purple-100 dark:bg-purple-950/50 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800/60">
                                        {{ day.records.length }} Sesi SO
                                    </span>
                                </div>
                                <p class="text-[11px] sm:text-xs text-text-secondary mt-0.5 flex items-center gap-2">
                                    <span>Siklus harian 05:00 AM</span>
                                    <span class="text-gray-300 dark:text-surface-600">•</span>
                                    <span>Tercatat {{ day.totalPhysical }} unit fisik diperiksa</span>
                                </p>
                            </div>
                        </div>

                        <!-- Right: Daily Percentage Pill & Collapse Button -->
                        <div class="flex items-center gap-3 self-end lg:self-center">
                            <!-- Percentage Badge -->
                            <div class="flex items-center gap-2 px-3.5 py-1.5 rounded-2xl border shadow-xs"
                                :class="day.percent === 100 && !day.hasDiff ? 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800/60 text-emerald-700 dark:text-emerald-300' : day.hasDiff ? 'bg-amber-50 dark:bg-amber-950/40 border-amber-200 dark:border-amber-800/60 text-amber-700 dark:text-amber-300' : 'bg-blue-50 dark:bg-blue-950/40 border-blue-200 dark:border-blue-800/60 text-blue-700 dark:text-blue-300'">
                                <Sparkles v-if="day.percent === 100 && !day.hasDiff" :size="15" class="text-emerald-500 shrink-0" />
                                <AlertTriangle v-else-if="day.hasDiff" :size="15" class="text-amber-500 shrink-0" />
                                <RefreshCw v-else :size="15" class="text-blue-500 shrink-0" />
                                <div class="text-right">
                                    <div class="text-xs sm:text-sm font-black font-mono leading-none">
                                        {{ day.percent }}% SO Selesai
                                    </div>
                                    <div class="text-[10px] font-semibold opacity-85 leading-tight">
                                        {{ day.statusLabel }}
                                    </div>
                                </div>
                            </div>

                            <!-- Toggle Collapse Button -->
                            <button type="button" @click="toggleDayCollapse(day.date)"
                                class="p-2 rounded-xl border border-gray-200 dark:border-surface-600 hover:bg-gray-100 dark:hover:bg-surface-700 text-text-secondary hover:text-text-primary transition-colors cursor-pointer"
                                :title="collapsedDays[day.date] ? 'Tampilkan Tabel' : 'Sembunyikan Tabel'">
                                <ChevronUp v-if="!collapsedDays[day.date]" :size="18" />
                                <ChevronDown v-else :size="18" />
                            </button>
                        </div>
                    </div>

                    <!-- PROGRESS BAR (AI / Sleek Style) -->
                    <div class="mt-4 space-y-1.5">
                        <div class="flex items-center justify-between text-xs font-bold">
                            <span class="text-text-secondary flex items-center gap-1.5">
                                <span>Progress SO Tanggal Ini</span>
                                <span class="text-[10px] font-mono text-emerald-600 dark:text-emerald-400 font-extrabold">({{ day.totalPhysical }} / {{ day.effectiveTargetUnits }} Unit)</span>
                            </span>
                            <span class="font-mono font-black" :class="day.percent === 100 && !day.hasDiff ? 'text-emerald-600 dark:text-emerald-400' : day.hasDiff ? 'text-amber-600 dark:text-amber-400' : 'text-blue-600 dark:text-blue-400'">
                                {{ day.percent }}%
                            </span>
                        </div>
                        <!-- Bar Track -->
                        <div class="w-full h-3 rounded-full bg-gray-200/70 dark:bg-surface-700 p-0.5 overflow-hidden border border-gray-200 dark:border-surface-600 shadow-inner">
                            <div class="h-full rounded-full transition-all duration-700 ease-out relative overflow-hidden"
                                :class="day.percent === 100 && !day.hasDiff ? 'bg-gradient-to-r from-emerald-500 via-teal-400 to-emerald-400 shadow-sm shadow-emerald-500/50' : day.hasDiff ? 'bg-gradient-to-r from-amber-500 via-orange-400 to-rose-500 shadow-sm shadow-rose-500/40' : 'bg-gradient-to-r from-blue-500 via-cyan-400 to-emerald-400 shadow-sm shadow-blue-500/40'"
                                :style="{ width: `${day.percent}%` }">
                                <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/30 to-transparent"></div>
                            </div>
                        </div>
                    </div>

                    <!-- RINCIAN RINGKASAN HARI INI (SUMMARY METRICS CHIPS) -->
                    <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-5 gap-2 text-xs">
                        <!-- Metric 1: Sistem -->
                        <div class="p-2.5 rounded-xl bg-white dark:bg-surface-750/70 border border-gray-200/80 dark:border-surface-700 shadow-2xs">
                            <div class="text-[10px] uppercase font-bold text-text-secondary tracking-wider">Total Sistem</div>
                            <div class="text-sm font-black font-mono text-text-primary mt-0.5">{{ day.totalSystem }} <span class="text-[10px] font-normal text-text-secondary">unit</span></div>
                        </div>

                        <!-- Metric 2: Fisik Ditemukan (Ada) -->
                        <div class="p-2.5 rounded-xl bg-white dark:bg-surface-750/70 border border-emerald-200/60 dark:border-emerald-900/50 shadow-2xs">
                            <div class="text-[10px] uppercase font-bold text-emerald-600 dark:text-emerald-400 tracking-wider">Fisik Ada (Sesuai)</div>
                            <div class="text-sm font-black font-mono text-emerald-600 dark:text-emerald-400 mt-0.5">{{ day.totalPhysical }} <span class="text-[10px] font-normal opacity-80">unit</span></div>
                        </div>

                        <!-- Metric 3: Selisih Kurang -->
                        <div class="p-2.5 rounded-xl bg-white dark:bg-surface-750/70 border shadow-2xs"
                            :class="day.totalMissing > 0 ? 'border-rose-300 dark:border-rose-900/60 bg-rose-50/40 dark:bg-rose-950/20' : 'border-gray-200/80 dark:border-surface-700'">
                            <div class="text-[10px] uppercase font-bold tracking-wider" :class="day.totalMissing > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-text-secondary'">
                                Selisih Kurang
                            </div>
                            <div class="text-sm font-black font-mono mt-0.5" :class="day.totalMissing > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-text-primary'">
                                {{ day.totalMissing > 0 ? '-' + day.totalMissing : '0' }} <span class="text-[10px] font-normal opacity-80">unit</span>
                            </div>
                        </div>

                        <!-- Metric 4: Selisih Lebih -->
                        <div class="p-2.5 rounded-xl bg-white dark:bg-surface-750/70 border shadow-2xs"
                            :class="day.totalExcess > 0 ? 'border-amber-300 dark:border-amber-900/60 bg-amber-50/40 dark:bg-amber-950/20' : 'border-gray-200/80 dark:border-surface-700'">
                            <div class="text-[10px] uppercase font-bold tracking-wider" :class="day.totalExcess > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-text-secondary'">
                                Unit Lebih
                            </div>
                            <div class="text-sm font-black font-mono mt-0.5" :class="day.totalExcess > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-text-primary'">
                                {{ day.totalExcess > 0 ? '+' + day.totalExcess : '0' }} <span class="text-[10px] font-normal opacity-80">unit</span>
                            </div>
                        </div>

                        <!-- Metric 5: Hasil Akhir / Status -->
                        <div class="col-span-2 sm:col-span-4 lg:col-span-1 p-2.5 rounded-xl bg-white dark:bg-surface-750/70 border border-gray-200/80 dark:border-surface-700 shadow-2xs flex flex-col justify-center">
                            <div class="text-[10px] uppercase font-bold text-text-secondary tracking-wider">Hasil Audit</div>
                            <div class="mt-0.5">
                                <span v-if="!day.hasDiff && day.percent === 100" class="inline-flex items-center gap-1 text-[11px] font-black text-emerald-700 dark:text-emerald-300">
                                    <CheckCircle2 :size="13" class="text-emerald-500" />
                                    <span>100% Cocok</span>
                                </span>
                                <span v-else-if="day.hasDiff" class="inline-flex items-center gap-1 text-[11px] font-black text-rose-600 dark:text-rose-400">
                                    <AlertTriangle :size="13" class="text-rose-500" />
                                    <span>Ada Selisih</span>
                                </span>
                                <span v-else class="inline-flex items-center gap-1 text-[11px] font-black text-blue-600 dark:text-blue-400">
                                    <RefreshCw :size="13" class="text-blue-500" />
                                    <span>Sebagian Selesai</span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Day Content (Toggled by !collapsedDays[day.date]) -->
                <div v-show="!collapsedDays[day.date]">
                    <!-- Mobile Cards (< md) for this Day -->
                    <div class="md:hidden divide-y divide-gray-100 dark:divide-surface-700">
                        <div v-for="rec in day.records" :key="rec.id" class="p-4 space-y-3 hover:bg-gray-50/50 dark:hover:bg-surface-700/20 transition-colors">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <div class="flex items-center gap-1.5 mb-1 flex-wrap">
                                        <span :class="getRoleBadgeClass(getRecordRole(rec))" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-extrabold border">
                                            <ShieldCheck v-if="getRecordRole(rec) === 'audit'" :size="11" />
                                            <UserCheck v-else-if="getRecordRole(rec) === 'leader'" :size="11" />
                                            <Crown v-else-if="getRecordRole(rec) === 'admin'" :size="11" />
                                            <Store v-else :size="11" />
                                            <span>{{ getRoleLabel(getRecordRole(rec)) }}</span>
                                        </span>
                                    </div>
                                    <div class="font-mono font-bold text-sm text-text-primary">{{ rec.opname_number }}</div>
                                    <div class="text-[11px] text-text-secondary mt-0.5">{{ formatDateTime(rec.created_at) }}</div>
                                </div>
                                <span v-if="rec.status === 'completed'" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-100 dark:bg-emerald-950/50 text-emerald-800 dark:text-emerald-300 shrink-0">
                                    <CheckCircle2 :size="12" />
                                    <span>Cocok</span>
                                </span>
                                <span v-else class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-100 dark:bg-rose-950/50 text-rose-800 dark:text-rose-300 shrink-0">
                                    <AlertTriangle :size="12" />
                                    <span>Selisih</span>
                                </span>
                            </div>

                            <div class="space-y-1 text-xs">
                                <div class="flex items-center justify-between">
                                    <span class="text-text-secondary">Cabang & Pelaksana:</span>
                                    <span class="font-semibold text-text-primary">
                                        {{ rec.branch?.name || '-' }}
                                        <span v-if="getRecordRole(rec) === 'audit' || getRecordRole(rec) === 'leader'">
                                            ({{ rec.user?.name || '-' }})
                                        </span>
                                        <span v-else>
                                            ({{ rec.cs_user?.name || '-' }})
                                        </span>
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-text-secondary">Metode:</span>
                                    <span class="font-bold uppercase text-[10px] px-2 py-0.5 rounded-full"
                                        :class="rec.method === 'by_imei' ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300' : 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300'">
                                        {{ rec.method === 'by_imei' ? 'By IMEI' : 'By Unit' }}
                                        <span v-if="rec.brand || rec.product_type">• {{ rec.brand }} {{ rec.product_type }}</span>
                                    </span>
                                </div>
                            </div>

                            <!-- Metrics Grid -->
                            <div class="grid grid-cols-4 gap-1.5 p-2.5 bg-gray-50 dark:bg-surface-700/50 rounded-xl text-center text-xs">
                                <div>
                                    <div class="text-[10px] text-text-secondary">Sistem</div>
                                    <div class="font-mono font-bold text-text-primary">{{ getRecordTotals(rec).total_system }}</div>
                                </div>
                                <div>
                                    <div class="text-[10px] text-emerald-600 dark:text-emerald-400 font-semibold">Fisik Ada</div>
                                    <div class="font-mono font-bold text-emerald-600 dark:text-emerald-400">{{ getRecordTotals(rec).total_physical }}</div>
                                </div>
                                <div>
                                    <div class="text-[10px] text-rose-600 dark:text-rose-400 font-semibold">Kurang</div>
                                    <div class="font-mono font-bold text-rose-600 dark:text-rose-400">
                                        <span v-if="getRecordTotals(rec).total_missing > 0">-{{ getRecordTotals(rec).total_missing }}</span>
                                        <span v-else class="text-gray-400">0</span>
                                    </div>
                                </div>
                                <div>
                                    <div class="text-[10px] text-amber-600 dark:text-amber-400 font-semibold">Lebih</div>
                                    <div class="font-mono font-bold text-amber-600 dark:text-amber-400">
                                        <span v-if="getRecordTotals(rec).total_excess > 0">+{{ getRecordTotals(rec).total_excess }}</span>
                                        <span v-else class="text-gray-400">0</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Inline IMEI Accordion on Mobile -->
                            <div v-if="rec.method === 'by_imei' && getSessionAllImeis(rec).length > 0" class="pt-1">
                                <button type="button" @click="toggleSessionExpand(rec.id)"
                                    class="w-full py-1.5 px-3 rounded-lg bg-purple-50 dark:bg-purple-950/40 text-purple-700 dark:text-purple-300 text-xs font-bold flex items-center justify-between border border-purple-200/60 dark:border-purple-800/60">
                                    <span>Lihat {{ getSessionAllImeis(rec).length }} Unit IMEI di Sesi Ini</span>
                                    <ChevronUp v-if="expandedSessions[rec.id]" :size="14" />
                                    <ChevronDown v-else :size="14" />
                                </button>

                                <div v-if="expandedSessions[rec.id]" class="mt-2 space-y-2 p-2.5 rounded-xl bg-purple-50/30 dark:bg-surface-700/40 border border-purple-100 dark:border-purple-900/40">
                                    <div v-for="it in getSessionAllImeis(rec)" :key="it.id" class="p-2 rounded-lg bg-white dark:bg-surface-800 border border-gray-200/60 dark:border-surface-600 text-xs flex items-center justify-between">
                                        <div>
                                            <div class="font-mono font-bold text-text-primary">{{ it.imei }}</div>
                                            <div class="text-[10px] text-text-secondary">{{ it.product_name }} • {{ it.storage || '-' }}</div>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <span v-if="it.is_excess" class="text-[10px] font-bold text-amber-700 bg-amber-50 dark:bg-amber-950/60 px-1.5 py-0.5 rounded">Lebih ⚠️</span>
                                            <span v-else-if="it.is_present && it.difference >= 0" class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded">Ada ✅</span>
                                            <span v-else class="text-[10px] font-bold text-rose-700 bg-rose-50 px-1.5 py-0.5 rounded">Tidak Ada ❌</span>
                                            <button type="button" @click="copyImei(it.imei)" class="p-1 text-gray-400 hover:text-emerald-600">
                                                <Copy :size="12" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer Action -->
                            <div class="flex items-center justify-between pt-1">
                                <span v-if="rec.photos && rec.photos.length > 0" class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-600 dark:text-emerald-400">
                                    <Camera :size="13" />
                                    <span>{{ rec.photos.length }} Foto Bukti</span>
                                </span>
                                <span v-else class="text-xs text-gray-400">Tanpa foto</span>

                                <button type="button" @click="openDetail(rec)"
                                    class="px-4 py-2 rounded-xl bg-gray-100 dark:bg-surface-700 hover:bg-gray-200 dark:hover:bg-surface-600 text-xs font-bold text-text-primary transition-colors flex items-center gap-1.5">
                                    <Eye :size="14" />
                                    <span>Rincian Lengkap</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Desktop Table View (>= md) for this Day -->
                    <div class="hidden md:block overflow-x-auto">
                        <table class="w-full text-left border-collapse min-w-[800px]">
                            <thead>
                                <tr class="border-b border-gray-100 dark:border-surface-700 bg-gray-50/50 dark:bg-surface-700/30 text-[11px] font-bold text-text-secondary uppercase">
                                    <th class="py-3 px-4">No SO & Waktu</th>
                                    <th class="py-3 px-4">Cabang & Pelaksana</th>
                                    <th class="py-3 px-4">Kategori & Metode</th>
                                    <th class="py-3 px-4 text-center">Sistem</th>
                                    <th class="py-3 px-4 text-center">Fisik Ada</th>
                                    <th class="py-3 px-4 text-center">Selisih</th>
                                    <th class="py-3 px-4 text-center">Lebih</th>
                                    <th class="py-3 px-4 text-center">Status</th>
                                    <th class="py-3 px-4 text-center">Foto</th>
                                    <th class="py-3 px-4 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-surface-700 text-xs sm:text-sm">
                                <template v-for="rec in day.records" :key="rec.id">
                                    <tr class="hover:bg-gray-50/60 dark:hover:bg-surface-700/30 transition-colors">
                                        <!-- No SO & Waktu -->
                                        <td class="py-3.5 px-4">
                                            <div class="flex items-center gap-1.5 mb-1">
                                                <span :class="getRoleBadgeClass(getRecordRole(rec))" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-extrabold border">
                                                    <ShieldCheck v-if="getRecordRole(rec) === 'audit'" :size="11" />
                                                    <UserCheck v-else-if="getRecordRole(rec) === 'leader'" :size="11" />
                                                    <Crown v-else-if="getRecordRole(rec) === 'admin'" :size="11" />
                                                    <Store v-else :size="11" />
                                                    <span>{{ getRoleLabel(getRecordRole(rec)) }}</span>
                                                </span>
                                            </div>
                                            <div class="font-mono font-bold text-xs text-text-primary">{{ rec.opname_number }}</div>
                                            <div class="text-[11px] text-text-secondary mt-0.5">{{ formatDateTime(rec.created_at) }}</div>
                                        </td>

                                        <!-- Cabang & Pelaksana -->
                                        <td class="py-3.5 px-4">
                                            <div class="font-semibold text-text-primary flex items-center gap-1.5">
                                                <Building2 :size="13" class="text-emerald-500 shrink-0" />
                                                <span>{{ rec.branch?.name || '-' }}</span>
                                            </div>
                                            <div class="text-[11px] text-text-secondary flex items-center gap-1.5 mt-0.5">
                                                <User :size="12" class="text-gray-400 shrink-0" />
                                                <span v-if="getRecordRole(rec) === 'audit' || getRecordRole(rec) === 'leader'">
                                                    Oleh: <strong>{{ rec.user?.name || '-' }}</strong>
                                                </span>
                                                <span v-else>
                                                    CS: {{ rec.cs_user?.name || '-' }}
                                                </span>
                                            </div>
                                        </td>

                                        <!-- Kategori & Metode -->
                                        <td class="py-3.5 px-4">
                                            <div class="flex items-center gap-1.5">
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase"
                                                    :class="rec.category_type === 'hp' ? 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300' : 'bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300'">
                                                    {{ rec.category_type === 'hp' ? 'HP' : 'Non-HP' }}
                                                </span>
                                                <span class="text-xs font-semibold text-text-primary">
                                                    {{ rec.method === 'by_imei' ? 'By IMEI' : 'By Unit' }}
                                                </span>
                                            </div>
                                            <div v-if="rec.brand || rec.product_type" class="text-[11px] text-text-secondary mt-0.5">
                                                {{ rec.brand ? rec.brand + ' - ' : '' }}{{ rec.product_type || 'Semua Tipe' }}
                                            </div>
                                            <!-- Button to expand IMEIs directly if by_imei -->
                                            <button v-if="rec.method === 'by_imei' && rec.items && rec.items.length > 0"
                                                type="button" @click="toggleSessionExpand(rec.id)"
                                                class="mt-1 text-[11px] text-purple-600 dark:text-purple-400 font-bold hover:underline inline-flex items-center gap-1">
                                                <span>{{ expandedSessions[rec.id] ? 'Tutup IMEI' : 'Lihat ' + getSessionAllImeis(rec).length + ' IMEI' }}</span>
                                                <ChevronUp v-if="expandedSessions[rec.id]" :size="12" />
                                                <ChevronDown v-else :size="12" />
                                            </button>
                                        </td>

                                        <!-- Total Sistem -->
                                        <td class="py-3.5 px-4 text-center font-mono font-bold">
                                            {{ getRecordTotals(rec).total_system }}
                                        </td>

                                        <!-- Total Fisik Ada -->
                                        <td class="py-3.5 px-4 text-center font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                            {{ getRecordTotals(rec).total_physical }}
                                        </td>

                                        <!-- Total Missing (Kurang) -->
                                        <td class="py-3.5 px-4 text-center font-mono font-bold">
                                            <span v-if="getRecordTotals(rec).total_missing > 0" class="text-rose-600 dark:text-rose-400">
                                                -{{ getRecordTotals(rec).total_missing }}
                                            </span>
                                            <span v-else class="text-gray-400">0</span>
                                        </td>

                                        <!-- Total Excess (Lebih) -->
                                        <td class="py-3.5 px-4 text-center font-mono font-bold">
                                            <span v-if="getRecordTotals(rec).total_excess > 0" class="text-amber-600 dark:text-amber-400">
                                                +{{ getRecordTotals(rec).total_excess }}
                                            </span>
                                            <span v-else class="text-gray-400">0</span>
                                        </td>

                                        <!-- Status Badge -->
                                        <td class="py-3.5 px-4 text-center">
                                            <span v-if="!getRecordTotals(rec).has_difference" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 dark:bg-emerald-950/50 text-emerald-800 dark:text-emerald-300">
                                                <CheckCircle2 :size="12" />
                                                <span>Cocok</span>
                                            </span>
                                            <span v-else class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-100 dark:bg-rose-950/50 text-rose-800 dark:text-rose-300">
                                                <AlertTriangle :size="12" />
                                                <span>Selisih</span>
                                            </span>
                                        </td>

                                        <!-- Foto SO -->
                                        <td class="py-3.5 px-4 text-center">
                                            <span v-if="rec.photos && rec.photos.length > 0" class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/40 px-2 py-0.5 rounded">
                                                <Camera :size="12" />
                                                <span>{{ rec.photos.length }}</span>
                                            </span>
                                            <span v-else class="text-gray-400 text-xs">-</span>
                                        </td>

                                        <!-- Action Detail -->
                                        <td class="py-3.5 px-4 text-right">
                                            <button type="button" @click="openDetail(rec)"
                                                class="px-3 py-1.5 rounded-lg border border-gray-200 dark:border-surface-600 text-xs font-bold text-text-primary hover:border-emerald-500 hover:text-emerald-600 transition-colors inline-flex items-center gap-1">
                                                <Eye :size="14" />
                                                <span>Rincian</span>
                                            </button>
                                        </td>
                                    </tr>

                                    <!-- Expanded Drawer: Inline IMEIs for this session -->
                                    <tr v-if="expandedSessions[rec.id] && getSessionAllImeis(rec).length > 0" class="bg-purple-50/25 dark:bg-purple-950/20">
                                        <td colspan="10" class="py-3 px-6">
                                            <div class="space-y-2">
                                                <div class="flex items-center justify-between">
                                                    <span class="text-xs font-bold text-purple-900 dark:text-purple-200 flex items-center gap-1.5">
                                                        <Smartphone :size="13" />
                                                        <span>Daftar IMEI Sesi {{ rec.opname_number }} ({{ getSessionAllImeis(rec).length }} unit)</span>
                                                    </span>
                                                    <button type="button" @click="openDetail(rec)" class="text-xs text-purple-600 dark:text-purple-400 font-bold hover:underline">
                                                        Buka di Modal Rincian →
                                                    </button>
                                                </div>

                                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                                                    <div v-for="it in getSessionAllImeis(rec)" :key="it.id"
                                                        class="p-2.5 rounded-xl bg-white dark:bg-surface-800 border border-purple-100 dark:border-purple-900/60 shadow-xs flex items-center justify-between gap-2">
                                                        <div class="space-y-0.5">
                                                            <div class="font-mono font-bold text-xs text-text-primary">{{ it.imei }}</div>
                                                            <div class="text-[10px] text-text-secondary">{{ it.product_name }} • {{ it.storage || '-' }} ({{ it.condition || 'second' }})</div>
                                                        </div>
                                                        <div class="flex items-center gap-1 shrink-0">
                                                            <span v-if="it.is_excess" class="text-[10px] font-bold text-amber-700 bg-amber-50 dark:bg-amber-950/60 px-1.5 py-0.5 rounded">
                                                                Lebih ⚠️
                                                            </span>
                                                            <span v-else-if="it.is_present && it.difference >= 0" class="text-[10px] font-bold text-emerald-700 bg-emerald-50 dark:bg-emerald-950/60 px-1.5 py-0.5 rounded">
                                                                Ada ✅
                                                            </span>
                                                            <span v-else class="text-[10px] font-bold text-rose-700 bg-rose-50 dark:bg-rose-950/60 px-1.5 py-0.5 rounded">
                                                                Tidak Ada ❌
                                                            </span>
                                                            <button type="button" @click="copyImei(it.imei)" title="Salin IMEI"
                                                                class="p-1 rounded text-gray-400 hover:text-emerald-600 hover:bg-gray-100 dark:hover:bg-surface-700">
                                                                <Copy :size="12" />
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Global Pagination Bar -->
            <div v-if="pagination.total > pagination.per_page" class="bg-white dark:!bg-surface-800 rounded-2xl border border-gray-100 dark:border-surface-700 p-4 shadow-sm flex flex-col sm:flex-row justify-between items-center gap-3">
                <span class="text-xs text-text-secondary">
                    Menampilkan halaman {{ pagination.current_page }} dari {{ pagination.last_page }} (Total {{ pagination.total }} sesi SO)
                </span>
                <div class="flex items-center gap-2">
                    <button type="button" :disabled="pagination.current_page <= 1" @click="fetchHistory(pagination.current_page - 1)"
                        class="px-3 py-1.5 rounded-lg border border-gray-200 dark:border-surface-600 text-xs font-semibold disabled:opacity-40">
                        Sebelumnya
                    </button>
                    <button type="button" :disabled="pagination.current_page >= pagination.last_page" @click="fetchHistory(pagination.current_page + 1)"
                        class="px-3 py-1.5 rounded-lg border border-gray-200 dark:border-surface-600 text-xs font-semibold disabled:opacity-40">
                        Selanjutnya
                    </button>
                </div>
            </div>
        </div>

        <!-- Detail Modal -->
        <div v-if="showDetailModal" class="fixed inset-0 z-[999] flex items-center justify-center p-3 sm:p-4 bg-black/60 backdrop-blur-sm">
            <div class="bg-white dark:!bg-surface-800 rounded-2xl max-w-4xl w-full max-h-[92vh] flex flex-col border border-gray-100 dark:border-surface-700 shadow-2xl overflow-hidden">
                <!-- Modal Header -->
                <div class="p-4 sm:p-5 border-b border-gray-100 dark:border-surface-700 flex items-center justify-between shrink-0">
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <span :class="getRoleBadgeClass(getRecordRole(selectedDetail))" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold border">
                                <ShieldCheck v-if="getRecordRole(selectedDetail) === 'audit'" :size="11" />
                                <UserCheck v-else-if="getRecordRole(selectedDetail) === 'leader'" :size="11" />
                                <Crown v-else-if="getRecordRole(selectedDetail) === 'admin'" :size="11" />
                                <Store v-else :size="11" />
                                <span>{{ getRoleLabel(getRecordRole(selectedDetail)) }}</span>
                            </span>
                            <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400 font-mono">{{ selectedDetail?.opname_number }}</span>
                            <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full"
                                :class="selectedDetail?.method === 'by_imei' ? 'bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800' : 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800'">
                                {{ selectedDetail?.method === 'by_imei' ? 'Stok By IMEI (Checklist)' : 'Stok By Unit' }}
                            </span>
                            <span v-if="selectedDetail?.brand || selectedDetail?.product_type" class="text-xs font-semibold text-text-secondary">
                                • {{ selectedDetail?.brand }} {{ selectedDetail?.product_type }}
                            </span>
                        </div>
                        <h3 class="text-base sm:text-lg font-bold text-text-primary mt-1">Rincian Hasil Stok Opname</h3>
                        <p class="text-xs text-text-secondary mt-0.5 flex items-center gap-2 flex-wrap">
                            <span>{{ selectedDetail?.branch?.name }}</span>
                            <span>•</span>
                            <span v-if="getRecordRole(selectedDetail) === 'audit' || getRecordRole(selectedDetail) === 'leader'">
                                Pelaksana: <strong>{{ selectedDetail?.user?.name || '-' }}</strong>
                            </span>
                            <span v-else>
                                CS: <strong>{{ selectedDetail?.cs_user?.name || '-' }}</strong>
                            </span>
                            <span>•</span>
                            <span>{{ formatDateTime(selectedDetail?.created_at) }}</span>
                        </p>
                    </div>
                    <button @click="showDetailModal = false" class="p-2 rounded-xl text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-surface-700 transition-colors">
                        <X :size="20" />
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="p-4 sm:p-5 overflow-y-auto space-y-5 flex-1">
                    <div v-if="loadingDetail" class="py-12 flex justify-center">
                        <RefreshCw :size="24" class="animate-spin text-emerald-500" />
                    </div>

                    <template v-else-if="selectedDetail">
                        <!-- Metrics Cards -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                            <div class="p-3 rounded-xl bg-gray-50 dark:bg-surface-700/50 border border-gray-100 dark:border-surface-700 text-center">
                                <div class="text-[10px] uppercase font-bold text-text-secondary">
                                    {{ selectedDetail.method === 'by_imei' ? 'Total IMEI Di-SO' : 'Stok Sistem' }}
                                </div>
                                <div class="text-lg font-bold font-mono text-text-primary mt-0.5">{{ getRecordTotals(selectedDetail).total_system }}</div>
                            </div>
                            <div class="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-100 dark:border-emerald-900/40 text-center">
                                <div class="text-[10px] uppercase font-bold text-emerald-700 dark:text-emerald-300">
                                    {{ selectedDetail.method === 'by_imei' ? 'Fisik Ada (Cocok)' : 'Fisik Cocok' }}
                                </div>
                                <div class="text-lg font-bold font-mono text-emerald-600 dark:text-emerald-400 mt-0.5">{{ getRecordTotals(selectedDetail).total_physical }}</div>
                            </div>
                            <div class="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/30 border border-rose-100 dark:border-rose-900/40 text-center">
                                <div class="text-[10px] uppercase font-bold text-rose-700 dark:text-rose-300">
                                    {{ selectedDetail.method === 'by_imei' ? 'Tidak Ada (Selisih)' : 'Selisih (Kurang)' }}
                                </div>
                                <div class="text-lg font-bold font-mono text-rose-600 dark:text-rose-400 mt-0.5">{{ getRecordTotals(selectedDetail).total_missing }}</div>
                            </div>
                            <div class="p-3 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-100 dark:border-amber-900/40 text-center">
                                <div class="text-[10px] uppercase font-bold text-amber-700 dark:text-amber-300">Barang Lebih</div>
                                <div class="text-lg font-bold font-mono text-amber-600 dark:text-amber-400 mt-0.5">{{ getRecordTotals(selectedDetail).total_excess }}</div>
                            </div>
                        </div>

                        <!-- SECTION A: If Method By IMEI (Detailed Unit List with Copyable IMEI) -->
                        <div v-if="selectedDetail.method === 'by_imei'" class="space-y-3">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                                <h4 class="text-xs font-bold text-text-secondary uppercase tracking-wider flex items-center gap-1.5">
                                    <Smartphone :size="14" class="text-purple-500" />
                                    <span>Rincian Unit IMEI ({{ selectedDetail.items?.length || 0 }} unit)</span>
                                </h4>

                                <!-- Filter & Search Controls in Modal -->
                                <div class="flex flex-wrap items-center gap-2">
                                    <!-- Ada / Tidak Ada Pill Filter -->
                                    <div class="inline-flex rounded-lg border border-gray-200 dark:border-surface-600 p-0.5 text-[11px] font-semibold bg-gray-50 dark:bg-surface-700">
                                        <button type="button" @click="modalStatusFilter = 'all'"
                                            class="px-2.5 py-1 rounded-md transition-colors"
                                            :class="modalStatusFilter === 'all' ? 'bg-white dark:bg-surface-800 text-text-primary shadow-xs' : 'text-text-secondary hover:text-text-primary'">
                                            Semua ({{ selectedDetail.items?.length || 0 }})
                                        </button>
                                        <button type="button" @click="modalStatusFilter = 'ada'"
                                            class="px-2.5 py-1 rounded-md transition-colors flex items-center gap-1"
                                            :class="modalStatusFilter === 'ada' ? 'bg-emerald-600 text-white shadow-xs' : 'text-emerald-700 dark:text-emerald-400 hover:text-emerald-800'">
                                            Ada ({{ selectedDetail.items?.filter(it => it.is_present && it.difference >= 0).length || 0 }})
                                        </button>
                                        <button type="button" @click="modalStatusFilter = 'tidak_ada'"
                                            class="px-2.5 py-1 rounded-md transition-colors flex items-center gap-1"
                                            :class="modalStatusFilter === 'tidak_ada' ? 'bg-rose-600 text-white shadow-xs' : 'text-rose-700 dark:text-rose-400 hover:text-rose-800'">
                                            Tidak Ada ({{ selectedDetail.items?.filter(it => !it.is_present || it.difference < 0).length || 0 }})
                                        </button>
                                    </div>

                                    <!-- Search In Modal -->
                                    <div class="relative w-full sm:w-52">
                                        <input type="text" v-model="modalItemSearch" placeholder="Cari IMEI / Tipe / GB..."
                                            class="w-full text-xs py-1.5 pl-7 pr-2.5 bg-gray-50 dark:bg-surface-700 border border-gray-200 dark:border-surface-600 rounded-lg text-text-primary placeholder:text-gray-400 focus:ring-1 focus:ring-emerald-500" />
                                        <Search :size="12" class="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
                                    </div>
                                </div>
                            </div>

                            <!-- Desktop Table for Modal IMEI (>= md) -->
                            <div class="hidden md:block border border-gray-200 dark:border-surface-700 rounded-xl overflow-hidden max-h-80 overflow-y-auto">
                                <table class="w-full text-left text-xs">
                                    <thead class="bg-gray-50 dark:bg-surface-700/60 font-bold text-text-secondary sticky top-0 z-10 shadow-xs">
                                        <tr>
                                            <th class="py-2.5 px-3 w-10 text-center">No</th>
                                            <th class="py-2.5 px-3">Tipe & Brand</th>
                                            <th class="py-2.5 px-3">Nomor IMEI</th>
                                            <th class="py-2.5 px-3 text-center">Kapasitas</th>
                                            <th class="py-2.5 px-3 text-center">Kondisi</th>
                                            <th class="py-2.5 px-3 text-center">Status Fisik</th>
                                            <th class="py-2.5 px-3">Catatan</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-surface-700">
                                        <tr v-if="filteredDetailItems.length === 0">
                                            <td colspan="7" class="py-8 text-center text-text-secondary italic">
                                                Tidak ada unit IMEI yang cocok dengan pencarian / filter.
                                            </td>
                                        </tr>
                                        <tr v-for="(it, idx) in filteredDetailItems" :key="it.id || idx" class="hover:bg-gray-50/50 dark:hover:bg-surface-700/50 transition-colors">
                                            <td class="py-2.5 px-3 text-center font-mono text-gray-400 text-[11px]">{{ idx + 1 }}</td>
                                            <td class="py-2.5 px-3 font-semibold text-text-primary">
                                                <span>{{ it.product_name }}</span>
                                                <span v-if="it.brand" class="text-[10px] text-text-secondary ml-1 font-normal">({{ it.brand }})</span>
                                            </td>
                                            <td class="py-2.5 px-3">
                                                <div class="flex items-center gap-1.5 font-mono font-bold text-text-primary">
                                                    <span>{{ it.imei || '-' }}</span>
                                                    <button v-if="it.imei" type="button" @click="copyImei(it.imei)" :title="'Salin IMEI: ' + it.imei"
                                                        class="p-1 rounded hover:bg-gray-200 dark:hover:bg-surface-600 text-gray-400 hover:text-emerald-600 transition-colors">
                                                        <CheckCheck v-if="copiedImei === it.imei" :size="13" class="text-emerald-500" />
                                                        <Copy v-else :size="13" />
                                                    </button>
                                                </div>
                                            </td>
                                            <td class="py-2.5 px-3 text-center font-mono font-semibold">{{ it.storage || '-' }}</td>
                                            <td class="py-2.5 px-3 text-center">
                                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full capitalize"
                                                    :class="it.condition === 'new' ? 'bg-cyan-100 dark:bg-cyan-950/50 text-cyan-800 dark:text-cyan-300 border border-cyan-200 dark:border-cyan-800' : 'bg-gray-100 dark:bg-surface-700 text-text-secondary border border-gray-200 dark:border-surface-600'">
                                                    {{ it.condition || 'second' }}
                                                </span>
                                            </td>
                                            <td class="py-2.5 px-3 text-center">
                                                <span v-if="it.is_present && it.difference >= 0"
                                                    class="text-[10px] font-extrabold text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/60 px-2 py-0.5 rounded border border-emerald-200/60 dark:border-emerald-800/60 inline-flex items-center gap-1">
                                                    <Check :size="12" />
                                                    <span>Ada</span>
                                                </span>
                                                <span v-else
                                                    class="text-[10px] font-extrabold text-rose-700 dark:text-rose-300 bg-rose-50 dark:bg-rose-950/60 px-2 py-0.5 rounded border border-rose-200/60 dark:border-rose-800/60 inline-flex items-center gap-1">
                                                    <X :size="12" />
                                                    <span>Tidak Ada</span>
                                                </span>
                                            </td>
                                            <td class="py-2.5 px-3 text-text-secondary text-[11px]">
                                                <span v-if="it.notes" class="italic">"{{ it.notes }}"</span>
                                                <span v-else class="text-gray-300 dark:text-gray-600">-</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Mobile Cards for Modal IMEI (< md) -->
                            <div class="md:hidden space-y-2.5 max-h-80 overflow-y-auto pr-1">
                                <div v-if="filteredDetailItems.length === 0" class="py-8 text-center text-text-secondary italic text-xs">
                                    Tidak ada unit IMEI yang cocok.
                                </div>
                                <div v-for="(it, idx) in filteredDetailItems" :key="it.id || idx"
                                    class="p-3 bg-gray-50 dark:bg-surface-700/60 rounded-xl border border-gray-200/60 dark:border-surface-600 space-y-2">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <span class="text-xs font-bold text-text-primary">{{ it.product_name }}</span>
                                            <span v-if="it.brand" class="text-[10px] text-text-secondary ml-1 font-normal">({{ it.brand }})</span>
                                        </div>
                                        <span v-if="it.is_present && it.difference >= 0"
                                            class="text-[10px] font-extrabold text-emerald-700 bg-emerald-100 dark:bg-emerald-950/80 px-2 py-0.5 rounded-full inline-flex items-center gap-1">
                                            <Check :size="11" />
                                            <span>Ada</span>
                                        </span>
                                        <span v-else
                                            class="text-[10px] font-extrabold text-rose-700 bg-rose-100 dark:bg-rose-950/80 px-2 py-0.5 rounded-full inline-flex items-center gap-1">
                                            <X :size="11" />
                                            <span>Tidak Ada</span>
                                        </span>
                                    </div>

                                    <div class="flex items-center justify-between gap-2 bg-white dark:bg-surface-800 p-2 rounded-lg border border-gray-200 dark:border-surface-600">
                                        <span class="font-mono font-bold text-xs text-text-primary">{{ it.imei }}</span>
                                        <button type="button" @click="copyImei(it.imei)" class="text-xs font-semibold text-emerald-600 flex items-center gap-1">
                                            <CheckCheck v-if="copiedImei === it.imei" :size="12" />
                                            <Copy v-else :size="12" />
                                            <span>{{ copiedImei === it.imei ? 'Disalin' : 'Salin' }}</span>
                                        </button>
                                    </div>

                                    <div class="flex items-center justify-between text-[11px] text-text-secondary">
                                        <span>Kapasitas: <strong class="text-text-primary">{{ it.storage || '-' }}</strong></span>
                                        <span>Kondisi: <strong class="text-text-primary capitalize">{{ it.condition || 'second' }}</strong></span>
                                    </div>

                                    <div v-if="it.notes" class="text-[10px] text-rose-600 dark:text-rose-400 italic">
                                        Catatan: "{{ it.notes }}"
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SECTION B: If Method By Unit (Grouped Product Summary Table) -->
                        <div v-else class="space-y-3">
                            <div class="flex items-center justify-between gap-2.5">
                                <h4 class="text-xs font-bold text-text-secondary uppercase tracking-wider flex items-center gap-1.5">
                                    <Package :size="14" class="text-blue-500" />
                                    <span>Daftar Unit Yang Diperiksa ({{ selectedDetail.items?.length || 0 }} item)</span>
                                </h4>
                                <div class="relative w-44 sm:w-56">
                                    <input type="text" v-model="modalItemSearch" placeholder="Cari Brand / Tipe / GB..."
                                        class="w-full text-xs py-1.5 pl-7 pr-2.5 bg-gray-50 dark:bg-surface-700 border border-gray-200 dark:border-surface-600 rounded-lg text-text-primary placeholder:text-gray-400 focus:ring-1 focus:ring-emerald-500" />
                                    <Search :size="12" class="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
                                </div>
                            </div>

                            <div class="border border-gray-200 dark:border-surface-700 rounded-xl overflow-hidden max-h-80 overflow-y-auto">
                                <table class="w-full text-left text-xs">
                                    <thead class="bg-gray-50 dark:bg-surface-700/60 font-bold text-text-secondary sticky top-0 z-10 shadow-xs">
                                        <tr>
                                            <th class="py-2.5 px-3 w-10 text-center">No</th>
                                            <th class="py-2.5 px-3">Brand & Tipe</th>
                                            <th class="py-2.5 px-3 text-center">GB</th>
                                            <th class="py-2.5 px-3 text-center">Sistem</th>
                                            <th class="py-2.5 px-3 text-center">Fisik Ada</th>
                                            <th class="py-2.5 px-3 text-center">Selisih</th>
                                            <th class="py-2.5 px-3 text-center">Status</th>
                                            <th class="py-2.5 px-3">Catatan</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-surface-700">
                                        <tr v-if="filteredDetailItems.length === 0">
                                            <td colspan="8" class="py-8 text-center text-text-secondary italic">
                                                Tidak ada item yang cocok dengan pencarian.
                                            </td>
                                        </tr>
                                        <tr v-for="(it, idx) in filteredDetailItems" :key="it.id || idx" class="hover:bg-gray-50/50 dark:hover:bg-surface-700/50 transition-colors">
                                            <td class="py-2 px-3 text-center font-mono text-gray-400 text-[11px]">{{ idx + 1 }}</td>
                                            <td class="py-2 px-3">
                                                <div class="font-bold text-text-primary">{{ it.product_name }}</div>
                                                <div v-if="it.brand" class="text-[10px] text-text-secondary font-normal">{{ it.brand }}</div>
                                            </td>
                                            <td class="py-2 px-3 text-center font-mono">{{ it.storage || '-' }}</td>
                                            <td class="py-2 px-3 text-center font-mono">{{ it.system_qty }}</td>
                                            <td class="py-2 px-3 text-center font-mono font-bold">{{ it.physical_qty }}</td>
                                            <td class="py-2 px-3 text-center font-mono font-bold">
                                                <span v-if="it.difference < 0" class="text-rose-600 dark:text-rose-400">{{ it.difference }}</span>
                                                <span v-else-if="it.difference > 0" class="text-emerald-600 dark:text-emerald-400">+{{ it.difference }}</span>
                                                <span v-else class="text-gray-400">0</span>
                                            </td>
                                            <td class="py-2 px-3 text-center">
                                                <span v-if="it.difference === 0" class="text-[10px] font-bold text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/50 px-2 py-0.5 rounded border border-emerald-200/50 dark:border-emerald-800/50">
                                                    Cocok ✅
                                                </span>
                                                <span v-else class="text-[10px] font-bold text-rose-700 dark:text-rose-300 bg-rose-50 dark:bg-rose-950/50 px-2 py-0.5 rounded border border-rose-200/50 dark:border-rose-800/50">
                                                    Selisih ❌
                                                </span>
                                            </td>
                                            <td class="py-2 px-3 text-text-secondary text-[11px]">
                                                <span v-if="it.notes" class="italic">"{{ it.notes }}"</span>
                                                <span v-else class="text-gray-300 dark:text-gray-600">-</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Excess Items Section -->
                        <div v-if="(selectedDetail.excess_items?.length > 0) || (selectedDetail.excessItems?.length > 0)" class="space-y-2">
                            <h4 class="text-xs font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wider flex items-center gap-1.5">
                                <AlertTriangle :size="14" />
                                <span>Barang Lebih ({{ (selectedDetail.excess_items || selectedDetail.excessItems).length }} item)</span>
                            </h4>

                            <div class="divide-y divide-gray-100 dark:divide-surface-700 border border-amber-200 dark:border-amber-900/60 rounded-xl overflow-hidden bg-amber-50/20">
                                <div v-for="ex in (selectedDetail.excess_items || selectedDetail.excessItems)" :key="ex.id" class="p-3 text-xs flex justify-between items-center">
                                    <div>
                                        <div class="font-bold text-text-primary">{{ ex.product_name }} ({{ ex.brand }})</div>
                                        <div class="text-[11px] text-text-secondary font-mono flex items-center gap-2">
                                            <span>{{ ex.imei ? 'IMEI: ' + ex.imei : 'Qty: ' + ex.quantity }}</span>
                                            <button v-if="ex.imei" type="button" @click="copyImei(ex.imei)" title="Salin IMEI"
                                                class="p-0.5 rounded text-gray-400 hover:text-emerald-600">
                                                <Copy :size="11" />
                                            </button>
                                            <span v-if="ex.notes" class="font-sans italic text-gray-400 ml-1">"{{ ex.notes }}"</span>
                                        </div>
                                    </div>
                                    <span class="px-2 py-0.5 rounded bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 font-bold text-[10px] border border-amber-200/60 dark:border-amber-800/60">
                                        +{{ ex.quantity || 1 }} Unit
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- FOTO BUKTI SECTION (With Robust URL & Multi-Tier Fallback) -->
                        <div v-if="selectedDetail.photos && selectedDetail.photos.length > 0" class="space-y-2.5">
                            <h4 class="text-xs font-bold text-text-secondary uppercase tracking-wider flex items-center gap-1.5">
                                <Camera :size="14" class="text-emerald-500" />
                                <span>Foto Bukti Fisik SO ({{ selectedDetail.photos.length }} Foto)</span>
                            </h4>

                            <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 gap-3">
                                <div v-for="(p, pIdx) in selectedDetail.photos" :key="pIdx"
                                    class="relative group aspect-square rounded-2xl border border-gray-200 dark:border-surface-600 overflow-hidden cursor-pointer hover:border-emerald-500 transition-all bg-gray-100 dark:bg-surface-700 shadow-xs"
                                    @click="selectedPhotoModal = p">
                                    <img :src="getPhotoUrl(p)"
                                         :alt="'Foto Bukti SO #' + (pIdx + 1)"
                                         @error="handleImgError($event, p)"
                                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" />
                                    
                                    <!-- Fallback UI if photo fails completely -->
                                    <div class="photo-fallback hidden absolute inset-0 flex flex-col items-center justify-center p-2 text-center bg-gray-100 dark:bg-surface-700 text-gray-400">
                                        <ImageOff :size="20" class="mb-1 text-gray-400" />
                                        <span class="text-[10px] font-semibold">Foto SO #{{ pIdx + 1 }}</span>
                                    </div>

                                    <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white text-xs font-bold">
                                        <Eye :size="18" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Catatan Section -->
                        <div v-if="selectedDetail.notes" class="space-y-1 bg-gray-50 dark:bg-surface-700/40 p-3.5 rounded-xl border border-gray-100 dark:border-surface-700 text-xs">
                            <span class="font-bold text-text-secondary uppercase tracking-wider text-[10px]">Catatan:</span>
                            <p class="text-text-primary whitespace-pre-wrap leading-relaxed">{{ selectedDetail.notes }}</p>
                        </div>
                    </template>
                </div>

                <!-- Modal Footer -->
                <div class="p-4 border-t border-gray-100 dark:border-surface-700 flex justify-end shrink-0">
                    <button type="button" @click="showDetailModal = false"
                        class="px-5 py-2 rounded-xl bg-gray-100 dark:bg-surface-700 hover:bg-gray-200 dark:hover:bg-surface-600 text-text-primary text-xs font-bold transition-colors">
                        Tutup
                    </button>
                </div>
            </div>
        </div>

        <!-- Lightbox Photo Preview Modal -->
        <div v-if="selectedPhotoModal" class="fixed inset-0 z-[1000] flex items-center justify-center p-4 bg-black/85 backdrop-blur-md"
            @click.self="selectedPhotoModal = null">
            <div class="relative max-w-4xl max-h-[90vh] flex flex-col items-center">
                <button @click="selectedPhotoModal = null" class="absolute -top-10 right-0 text-white hover:text-gray-300 transition-colors">
                    <X :size="26" />
                </button>
                <img :src="getPhotoUrl(selectedPhotoModal)"
                     @error="handleImgError($event, selectedPhotoModal)"
                     alt="Foto Bukti SO Full"
                     class="max-w-full max-h-[85vh] rounded-2xl object-contain shadow-2xl border border-white/10" />
                <div class="mt-3 flex items-center gap-3 text-xs text-white/80">
                    <a :href="getPhotoUrl(selectedPhotoModal)" target="_blank" download class="hover:underline flex items-center gap-1">
                        <Download :size="13" />
                        <span>Buka / Unduh Resolusi Penuh</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</template>
