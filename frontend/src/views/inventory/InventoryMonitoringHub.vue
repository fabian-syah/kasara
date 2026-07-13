<script setup>
import { ref, onMounted, computed, watch } from "vue";
import api from "../../api/axios";
import axios from "axios";
import { useToast } from "../../composables/useToast";
import { useRouter } from "vue-router";
import { useAuthStore } from "../../store/auth";
import PinModal from "../../components/modals/PinModal.vue";
import TransferReceiptModal from "../../components/modals/TransferReceiptModal.vue";
import {
    Package,
    Loader2,
    ArrowDownRight,
    ArrowUpRight,
    Calendar,
    User,
    Smartphone,
    CheckCircle2,
    Clock,
    Building2,
    RefreshCw,
    X,
    AlertTriangle,
    Store,
    ShoppingCart,
    Search,
    ChevronLeft,
    ChevronRight,
    FileText,
    Truck,
    LayoutDashboard,
    Printer,
    MapPin,
    Globe
} from "lucide-vue-next";

const toast = useToast();
const router = useRouter();
const authStore = useAuthStore();

// Tab State
const activeTab = ref("incoming_otw"); // Default to incoming_otw
const lastOtwTab = ref("incoming_otw");

const topCards = [
    { id: "incoming_otw", name: "Konfirmasi Masuk", description: "Verifikasi penerimaan barang", icon: ArrowDownRight, color: "text-blue-500", bg: "bg-blue-500/10", border: "border-blue-500/30", activeBorder: "border-blue-500/80 shadow-blue-500/10" },
    { id: "outgoing_otw", name: "Pantau Kiriman", description: "Lacak status pengiriman", icon: ArrowUpRight, color: "text-purple-500", bg: "bg-purple-500/10", border: "border-purple-500/30", activeBorder: "border-purple-500/80 shadow-purple-500/10" },
    { id: "failed_otw", name: "Gagal Kirim", description: "Tindak lanjuti masalah", icon: AlertTriangle, color: "text-red-500", bg: "bg-red-500/10", border: "border-red-500/30", activeBorder: "border-red-500/80 shadow-red-500/10" },
];

function selectTopCard(id) {
    activeTab.value = id;
    lastOtwTab.value = id;
}

// Global Loading State
const isLoading = ref(false);

const assetInValue = ref(0);
const assetOutValue = ref(0);

function formatCurrency(val) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(val || 0);
}

async function fetchAssetValues() {
    try {
        const response = await api.get('/transfers/asset-values');
        assetInValue.value = response.data.in_value || 0;
        assetOutValue.value = response.data.out_value || 0;
    } catch (e) {
        console.error("Gagal memuat nilai aset:", e);
    }
}

// Common Data States
const transfers = ref([]); // For OTW tabs
const historyData = ref({ data: [], current_page: 1, last_page: 1, total: 0 }); // For History tabs
const searchQuery = ref("");
const currentPage = ref(1);

// Location Filter States
const canChangeLocation = computed(() => {
    const role = (authStore.userRole || '').toLowerCase();
    return ['super_admin', 'analist', 'audit', 'leader', 'owner', 'admin_produk'].some(r => role.includes(r));
});

const locationType = ref('branch');
const filters = ref({
    branch_id: null,
    online_shop_id: null,
    warehouse_id: null,
    distributor_id: null
});

const branches = ref([]);
const onlineShops = ref([]);
const warehouses = ref([]);
const distributors = ref([]);

const filteredBranches = computed(() => {
    const role = (authStore.userRole || '').toLowerCase();
    let result = branches.value || [];
    if (!['super_admin', 'analist', 'owner', 'admin_produk'].some(r => role.includes(r))) {
        const allowed = [authStore.user?.branch_id, ...(authStore.user?.placements?.filter(p => p.model_type === 'branch').map(p => p.model_id) || [])].filter(Boolean).map(Number);
        result = result.filter(b => allowed.includes(Number(b.id)));
    }
    return result;
});

const filteredOnlineShops = computed(() => {
    const role = (authStore.userRole || '').toLowerCase();
    let result = onlineShops.value || [];
    if (!['super_admin', 'analist', 'owner', 'admin_produk'].some(r => role.includes(r))) {
        const allowed = [authStore.user?.online_shop_id, ...(authStore.user?.placements?.filter(p => p.model_type === 'online_shop').map(p => p.model_id) || [])].filter(Boolean).map(Number);
        result = result.filter(o => allowed.includes(Number(o.id)));
    }
    return result;
});


const filteredWarehouses = computed(() => {
    const role = (authStore.userRole || '').toLowerCase();
    let result = warehouses.value || [];
    if (!['super_admin', 'analist', 'owner', 'admin_produk'].some(r => role.includes(r))) {
        const allowed = [authStore.user?.warehouse_id, ...(authStore.user?.placements?.filter(p => p.model_type === 'warehouse').map(p => p.model_id) || [])].filter(Boolean).map(Number);
        result = result.filter(w => allowed.includes(Number(w.id)));
    }
    return result;
});

const filteredDistributors = computed(() => {
    const role = (authStore.userRole || '').toLowerCase();
    let result = distributors.value || [];
    if (!['super_admin', 'analist', 'owner', 'admin_produk'].some(r => role.includes(r))) {
        const allowed = [authStore.user?.distributor_id, ...(authStore.user?.placements?.filter(p => p.model_type === 'distributor').map(p => p.model_id) || [])].filter(Boolean).map(Number);
        result = result.filter(d => allowed.includes(Number(d.id)));
    }
    return result;
});

const fetchLocations = async () => {
    try {
        const response = await api.get('/inventory/meta-locations');
        branches.value = response.data.branches || [];
        onlineShops.value = response.data.online_shops || [];
        warehouses.value = response.data.warehouses || [];
        distributors.value = response.data.distributors || [];
    } catch (err) {
        console.error("Gagal memuat filter lokasi:", err);
    }
};

const handleLocationTypeChange = () => {
    filters.value.branch_id = null;
    filters.value.online_shop_id = null;
    filters.value.warehouse_id = null;
    filters.value.distributor_id = null;
    fetchData(1);
};

// Modal/Form States (Shared)
const showPinModal = ref(false);
const pinCallback = ref(null);
const selectedTransfer = ref(null);
const isSubmitting = ref(false);

// Modal Content States
const showReceiveModal = ref(false); // For incoming_otw
const showReturnModal = ref(false); // For failed_otw (Confirm return to stock)
const showDetailModal = ref(false); // For outgoing_otw, history_in, history_out
const showExpeditionModal = ref(false); // NEW: For adding expedition info
const showPrintModal = ref(false);
const showTrackingModal = ref(false); // NEW: For real-time tracking display
const isTracking = ref(false);
const trackingData = ref(null);
const trackingHtml = ref(""); 
const trackingResult = ref(null); // To store professional JSON results from Binderbyte
const trackingProvider = ref(null); // To know which provider is being used (Binderbyte / BiteShip)

const couriers = ref([
    'JNE', 'J&T', 'Sicepat', 'POS Indonesia', 'Tiki', 'Wahana', 'Anteraja', 'Ninja Xpress', 'Lion Parcel', 'ID Express',
    'SAP Express', 'RPX', 'JET Express', 'Indah Logistic', 'First Logistics', 'NCS', 'REX', 'Shopee Express'
]);

const expeditionForm = ref({
    expedition_name: "",
    expedition_tracking_no: "",
    expedition_date: new Date().toISOString().substr(0, 10),
});


const receiveForm = ref({
    accepted_items: [],
    non_hp_quantities: {},
    items_rejection: {},
    non_hp_rejection_notes: {},
    inventory_user_id: ""
});

// Inventory Accounts
const inventoryAccounts = ref([]);
const selectedInventoryAccount = ref("");

// --- Common Helpers ---
function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('id-ID', {
        day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit'
    });
}

function getBrandName(item) {
    const product = item?.product;
    if (!product) return '';
    const brand = product.brand_relation || product.brandRelation || product.brand;
    if (brand && typeof brand === 'object') return brand.name || '';
    return brand || '';
}

function formatCapacity(ram, storage) {
    if (!ram && !storage) return '';
    const r = ram ? (typeof ram === 'string' ? ram.replace(/GB/gi, '') : ram) : '';
    const s = storage ? (typeof storage === 'string' ? storage.replace(/GB/gi, '') : storage) : '';
    return (r && s) ? `${r}/${s}GB` : `${r || s}GB`;
}

function getTransferItemsSummary(transfer) {
    const parts = [];
    const items = transfer.items || [];
    const nonHp = transfer.non_hp_items || [];
    
    if (items.length > 0) {
        if (items.length <= 2) {
            items.forEach(i => parts.push(`${getBrandName(i)} ${i.product?.name || ''}`));
        } else {
            parts.push(`${items.length} Unit HP`);
        }
    }
    if (nonHp.length > 0) {
        if (nonHp.length <= 2) {
            nonHp.forEach(i => parts.push(`${i.product?.name || i.product_name || ''} (${i.quantity} Qty)`));
        } else {
            const totalQty = nonHp.reduce((sum, i) => sum + (i.quantity || 0), 0);
            parts.push(`${totalQty} Pcs Aksesoris`);
        }
    }
    return parts.join(', ') || 'Tidak ada barang';
}

function getStatusBadgeClass(status, tab) {
    if (tab === 'failed_otw' || tab === 'history_failed' || status === 'failed') {
        return 'bg-red-500/10 text-red-500 border-red-500/20';
    }
    if (status === 'confirmed' || status === 'received') {
        return 'bg-green-500/10 text-green-500 border-green-500/20';
    }
    if (tab === 'incoming_otw') {
        return 'bg-amber-500/10 text-amber-500 border-amber-500/20';
    }
    return 'bg-blue-500/10 text-blue-500 border-blue-500/20';
}

function getStatusLabel(status, tab) {
    if (tab === 'failed_otw' || tab === 'history_failed' || status === 'failed') return 'Gagal';
    if (status === 'confirmed' || status === 'received') return 'Selesai';
    if (tab === 'incoming_otw') return 'Menunggu Konfirmasi';
    return 'OTW';
}

function getSenderDetails(transfer) {
    const parts = [];
    
    // 1. Direct Transfer Location
    let source = transfer.branch || transfer.branch_relation || transfer.branchRelation ||
                 transfer.online_shop || transfer.online_shop_relation || transfer.onlineShopRelation || transfer.onlineShop ||
                 transfer.warehouse || transfer.warehouse_relation || transfer.warehouseRelation;
                 
    // 2. Creator User's Location
    if (!source) {
        const u = transfer.user;
        source = u?.branch || u?.warehouse || u?.online_shop || u?.onlineShop;
    }
    
    // 3. Inventory Sub-Account User's Location
    if (!source) {
        const iu = transfer.inventory_user || transfer.inventoryUser;
        source = iu?.branch || iu?.warehouse || iu?.online_shop || iu?.onlineShop;
    }
    
    // 4. String Fallback
    if (!source) {
        source = transfer.source;
    }
                   
    let sourceName = source && typeof source === 'object' ? source.name : source;
    
    // 5. Fallback to distributor or supplier from items or nonHpItems
    if (!sourceName) {
        sourceName = transfer.items?.[0]?.distributor?.name || 
                     transfer.items?.[0]?.supplier_name ||
                     transfer.non_hp_items?.[0]?.distributor?.name || 
                     transfer.nonHpItems?.[0]?.distributor?.name ||
                     transfer.nonHpItems?.[0]?.supplier_name;
    }
    
    if (sourceName) {
        parts.push(sourceName);
    }
    
    const accountName = transfer.inventory_user?.name || transfer.inventoryUser?.name || 
                        transfer.inventory_user?.username || transfer.inventoryUser?.username ||
                        transfer.user?.name || transfer.user?.username || transfer.user?.email;
                        
    if (accountName) {
        parts.push(accountName);
    }
    
    return parts.join(' - ') || 'Unknown Sender';
}

// --- API Calls ---

async function fetchInventoryAccounts() {
    try {
        const response = await api.get('/inventory/my-accounts');
        inventoryAccounts.value = response.data || [];
        if (inventoryAccounts.value.length > 0) selectedInventoryAccount.value = inventoryAccounts.value[0].id;
    } catch (e) { console.error(e); }
}

async function fetchData(page = 1) {
    isLoading.value = true;
    try {
        fetchAssetValues();
        
        const baseParams = {
            branch_id: filters.value.branch_id,
            online_shop_id: filters.value.online_shop_id,
            warehouse_id: filters.value.warehouse_id,
            distributor_id: filters.value.distributor_id
        };

        if (activeTab.value === "incoming_otw") {
            const response = await api.get('/transfers/pending', { params: baseParams });
            transfers.value = response.data.data || response.data || [];
        } else if (activeTab.value === "outgoing_otw") {
            const response = await api.get('/transfers/outgoing', { params: baseParams });
            transfers.value = response.data.data || response.data || [];
        } else if (activeTab.value === "failed_otw") {
            const response = await api.get('/transfers/failed', { params: baseParams });
            transfers.value = response.data.data || response.data || [];
        } else if (activeTab.value === "history_in") {
            const response = await api.get('/transfers/history', {
                params: { ...baseParams, page, q: searchQuery.value, type: 'incoming' }
            });
            historyData.value = response.data;
            currentPage.value = page;
        } else if (activeTab.value === "history_out") {
            const response = await api.get('/transfers/history', {
                params: { ...baseParams, page, q: searchQuery.value, type: 'outgoing' }
            });
            historyData.value = response.data;
            currentPage.value = page;
        } else if (activeTab.value === "history_failed") {
            const response = await api.get('/transfers/history', {
                params: { ...baseParams, page, q: searchQuery.value, type: 'failed' }
            });
            historyData.value = response.data;
            currentPage.value = page;
        }
    } catch (e) {
        console.error(e);
        toast.error("Gagal memuat data");
    } finally {
        isLoading.value = false;
    }
}

// --- Tab Watcher ---
watch(activeTab, () => {
    transfers.value = [];
    historyData.value = { data: [], current_page: 1, last_page: 1, total: 0 };
    searchQuery.value = "";
    currentPage.value = 1;
    fetchData(1);
});

// --- Search Watcher ---
let searchTimeout;
watch(searchQuery, () => {
    if (activeTab.value.includes("history")) {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => fetchData(1), 500);
    }
});

// --- Modal Logic ---

function openModal(transfer) {
    selectedTransfer.value = transfer;
    if (activeTab.value === "incoming_otw") {
        // Setup Receive Form
        const accepted = (transfer.items || []).map(i => i.id);
        const quantities = {};
        (transfer.non_hp_items || []).forEach(i => quantities[i.id] = i.quantity);
        receiveForm.value = {
            accepted_items: accepted,
            non_hp_quantities: quantities,
            items_rejection: {},
            non_hp_rejection_notes: {},
            inventory_user_id: selectedInventoryAccount.value
        };
        showReceiveModal.value = true;
    } else if (activeTab.value === "failed_otw") {
        showReturnModal.value = true;
    } else {
        showDetailModal.value = true;
    }
}

function openExpeditionModal(transfer) {
    selectedTransfer.value = transfer;
    expeditionForm.value = {
        expedition_name: transfer.expedition_name || "",
        expedition_tracking_no: transfer.expedition_tracking_no || "",
        expedition_date: transfer.expedition_date || new Date().toISOString().substr(0, 10),
    };
    showExpeditionModal.value = true;
}

function openPrintModal(transfer) {
    selectedTransfer.value = transfer;
    showPrintModal.value = true;
}

function closeExpeditionModal() {
    showExpeditionModal.value = false;
    selectedTransfer.value = null;
}

async function trackPackage(courier, trackingNo) {
    if (!trackingNo) {
        toast.error('Nomor resi belum diisi oleh pengirim.');
        return;
    }
    
    showTrackingModal.value = true;
    isTracking.value = true;
    trackingResult.value = null; // Reset
    
    try {
        // Call our new professional backend endpoint
        const response = await api.get('/transfers/track-expedition', {
            params: { 
                courier: courier || 'jne', 
                awb: trackingNo 
            }
        });
        
        if (response.data && response.data.success) {
            trackingResult.value = response.data.data;
            trackingProvider.value = response.data.provider || 'primary';
        } else {
            toast.error(response.data.message || 'Data pelacakan tidak ditemukan.');
        }
    } catch (error) {
        console.error("Tracking Error:", error);
        const errorMsg = error.response?.data?.message || 'Gagal terhubung ke server pelacakan.';
        toast.error(errorMsg);
    } finally {
        isTracking.value = false;
    }
}

function closeTrackingModal() {
    showTrackingModal.value = false;
    trackingData.value = null;
}

function closeModal() {
    showReceiveModal.value = false; showReturnModal.value = false; showDetailModal.value = false;
    selectedTransfer.value = null;
}

// --- PIN Verification ---
function handleVerifyPin(callback) {
    pinCallback.value = callback;
    showPinModal.value = true;
}
function onPinVerified(pin) {
    showPinModal.value = false;
    if (pinCallback.value) { pinCallback.value(pin); pinCallback.value = null; }
}

// --- Submit Actions ---

async function submitReceive(verifiedPin = null) {
    if (!selectedTransfer.value) return;
    const pin = typeof verifiedPin === 'string' ? verifiedPin : null;
    const selectedAccount = inventoryAccounts.value.find(acc => acc.id === selectedInventoryAccount.value);

    if (!pin && selectedAccount?.pin_enabled) {
        handleVerifyPin((vPin) => submitReceive(vPin));
        return;
    }

    isSubmitting.value = true;
    try {
        const payload = {
            items: receiveForm.value.accepted_items,
            items_rejection: receiveForm.value.items_rejection,
            non_hp_items: receiveForm.value.non_hp_quantities,
            non_hp_rejection_notes: receiveForm.value.non_hp_rejection_notes,
            inventory_user_id: selectedInventoryAccount.value,
            transaction_pin: pin
        };
        await api.post(`/transfers/${selectedTransfer.value.id}/confirm`, payload);
        toast.success("Transfer berhasil dikonfirmasi!");
        fetchData(1); closeModal();
    } catch (e) {
        toast.error(e.response?.data?.message || "Gagal mengkonfirmasi");
    } finally { isSubmitting.value = false; }
}

async function submitReturn(verifiedPin = null) {
    if (!selectedTransfer.value) return;
    const pin = typeof verifiedPin === 'string' ? verifiedPin : null;

    isSubmitting.value = true;
    try {
        await api.post(`/transfers/${selectedTransfer.value.id}/confirm-return`, {
            transaction_pin: pin,
            inventory_user_id: selectedInventoryAccount.value
        });
        toast.success('Barang telah diterima kembali ke stok.');
        fetchData(1); closeModal();
    } catch (e) {
        toast.error(e.response?.data?.message || 'Gagal memproses pengembalian.');
    } finally { isSubmitting.value = false; }
}

async function submitExpedition() {
    if (!selectedTransfer.value || isSubmitting.value) return;
    
    isSubmitting.value = true;
    try {
        await api.post(`/transfers/${selectedTransfer.value.id}/expedition`, expeditionForm.value);
        
        // Wait for data reload before closing
        await fetchData(currentPage.value);
        
        closeExpeditionModal();
        toast.success("Informasi ekspedisi berhasil disimpan!");
    } catch (e) {
        console.error("Expedition Error:", e);
        toast.error(e.response?.data?.message || "Gagal menyimpan ekspedisi");
    } finally { 
        isSubmitting.value = false; 
    }
}

onMounted(() => {
    if (canChangeLocation.value) {
        fetchLocations();
    }
    fetchInventoryAccounts();
    fetchData(1);
});
</script>

<template>
    <div class="min-h-screen bg-surface-900 pb-20">
        <!-- Dashboard Header -->
        <div class="bg-surface-900 border-b border-surface-700/50">
            <div class="w-full px-6 md:px-10 py-6">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div class="flex items-center gap-4">
                        <div class="p-3 bg-primary-500/10 rounded-2xl border border-primary-500/20">
                            <LayoutDashboard :size="24" class="text-primary-500" />
                        </div>
                        <div>
                            <h1 class="text-2xl font-black text-white tracking-tight">Monitoring OTW</h1>
                            <p class="text-xs text-text-secondary font-medium tracking-widest uppercase opacity-60">
                                Inventory Transfer Hub</p>
                        </div>
                    </div>
 
                    <!-- Search for History Tabs -->
                    <div v-if="activeTab.includes('history')" class="relative flex-1 max-w-md slide-in">
                        <Search :size="18" class="absolute left-4 top-1/2 -translate-y-1/2 text-text-secondary" />
                        <input v-model="searchQuery" type="text" placeholder="Cari Resi atau Cabang..."
                            class="w-full bg-surface-800 border border-surface-700 rounded-xl pl-12 pr-4 py-2.5 text-sm text-white focus:outline-none focus:border-primary-500 transition-all shadow-inner" />
                    </div>

                    <div class="flex items-center gap-3 ml-auto">
                        <!-- Location Filter (Branch/OS) -->
                        <div v-if="canChangeLocation"
                            class="flex items-center gap-2 bg-surface-800 border border-surface-700 rounded-xl p-1 shadow-sm w-fit">
                            <div class="flex items-center gap-1 group">
                                <div class="p-1.5 bg-surface-750 rounded-lg group-hover:bg-primary-500/10 transition-colors">
                                    <MapPin v-if="locationType === 'branch'" :size="14" class="text-text-secondary group-hover:text-primary-500" />
                                    <Globe v-else :size="14" class="text-text-secondary group-hover:text-primary-500" />
                                </div>
                                <select v-model="locationType" @change="handleLocationTypeChange"
                                    class="bg-transparent border-none text-[10px] uppercase tracking-wider font-black text-text-secondary focus:ring-0 cursor-pointer pr-6">
                                    <option value="branch">Cabang</option>
                                    <option value="online">Online</option>
                                    <option value="warehouse">Gudang</option>
                                    <option value="distributor">Distributor</option>
                                </select>
                            </div>
                            <div class="w-px h-4 bg-surface-700 mr-1"></div>
                            <select v-if="locationType === 'branch'" v-model="filters.branch_id" @change="fetchData(1)"
                                class="bg-transparent border-none text-xs font-bold text-text-primary focus:ring-0 cursor-pointer min-w-[140px] appearance-none pr-8">
                                <option :value="null">Semua Cabang</option>
                                <option v-for="b in filteredBranches" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                            
                            <select v-else-if="locationType === 'warehouse'" v-model="filters.warehouse_id" @change="fetchData(1)"
                                class="bg-transparent border-none text-xs font-bold text-text-primary focus:ring-0 cursor-pointer min-w-[140px] appearance-none pr-8">
                                <option :value="null">Semua Gudang</option>
                                <option v-for="w in filteredWarehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                            </select>
                            <select v-else-if="locationType === 'distributor'" v-model="filters.distributor_id" @change="fetchData(1)"
                                class="bg-transparent border-none text-xs font-bold text-text-primary focus:ring-0 cursor-pointer min-w-[140px] appearance-none pr-8">
                                <option :value="null">Semua Distributor</option>
                                <option v-for="d in filteredDistributors" :key="d.id" :value="d.id">{{ d.name }}</option>
                            </select>
                        </div>

                        <button @click="fetchData(1)" :disabled="isLoading"
                            class="btn btn-secondary gap-2 px-5 h-11 rounded-xl text-sm font-bold border border-surface-700 hover:bg-surface-750 shrink-0">
                            <RefreshCw :size="16" :class="{ 'animate-spin': isLoading }" />
                            <span>Refresh</span>
                        </button>
                    </div>
                </div>
 
                <!-- 5 Big Cards at the Top (Fully Responsive Grid Layout) -->
                <div class="mt-8 grid grid-cols-1 lg:grid-cols-12 gap-6">
                    <!-- Left: Realtime Asset Value Statistics (5 columns on desktop) -->
                    <div class="lg:col-span-5 grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <!-- ASET PERJALANAN (IN) -->
                        <div class="p-5 lg:p-6 rounded-[2rem] border bg-surface-800/60 border-surface-700/50 flex flex-col justify-between gap-4 relative overflow-hidden group">
                            <div class="space-y-2">
                                <h3 class="text-[10px] font-black uppercase tracking-widest text-text-secondary opacity-60">Aset Perjalanan (IN)</h3>
                                <p class="text-xl lg:text-2xl font-black text-emerald-500 tracking-tight transition-all duration-300">
                                    {{ formatCurrency(assetInValue) }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center gap-1 text-[10px] px-2 py-0.5 rounded-lg bg-emerald-500/10 text-emerald-500 font-bold">
                                    Realtime
                                </span>
                                <span class="text-[10px] text-text-secondary">dalam perjalanan</span>
                            </div>
                        </div>

                        <!-- ASET PENGIRIMAN (OUT) -->
                        <div class="p-5 lg:p-6 rounded-[2rem] border bg-surface-800/60 border-surface-700/50 flex flex-col justify-between gap-4 relative overflow-hidden group">
                            <div class="space-y-2">
                                <h3 class="text-[10px] font-black uppercase tracking-widest text-text-secondary opacity-60">Aset Pengiriman (OUT)</h3>
                                <p class="text-xl lg:text-2xl font-black text-blue-500 tracking-tight transition-all duration-300">
                                    {{ formatCurrency(assetOutValue) }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center gap-1 text-[10px] px-2 py-0.5 rounded-lg bg-blue-500/10 text-blue-500 font-bold">
                                    Realtime
                                </span>
                                <span class="text-[10px] text-text-secondary">dalam pengiriman</span>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Interactive Navigation Buttons (7 columns on desktop) -->
                    <div class="lg:col-span-7 grid grid-cols-1 sm:grid-cols-3 gap-6">
                        <button v-for="card in topCards" :key="card.id" @click="selectTopCard(card.id)"
                            class="p-5 lg:p-6 rounded-[2rem] border transition-all duration-300 flex items-start gap-3.5 cursor-pointer text-left w-full relative overflow-hidden group"
                            :class="['incoming_otw', 'outgoing_otw', 'failed_otw'].includes(activeTab) && activeTab === card.id
                                ? `${card.bg} ${card.activeBorder}`
                                : 'bg-surface-800/60 border-surface-700/50 hover:bg-surface-750 hover:border-surface-600'">
                            <div class="p-3.5 rounded-2xl flex items-center justify-center shrink-0"
                                :class="['incoming_otw', 'outgoing_otw', 'failed_otw'].includes(activeTab) && activeTab === card.id
                                    ? 'bg-white/10 text-white'
                                    : 'bg-surface-700 text-text-secondary group-hover:text-white transition-all'">
                                <component :is="card.icon" :size="20" />
                            </div>
                            <div class="space-y-1">
                                <h3 class="text-base lg:text-lg font-black text-white tracking-tight leading-none">{{ card.name }}</h3>
                                <p class="text-[11px] lg:text-xs text-text-secondary font-medium tracking-normal leading-snug">{{ card.description }}</p>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Sub Tabs Line Navigation (Matching User Screenshot Perfectly) -->
                <div class="mt-10 flex gap-8 border-b border-surface-700/30 pb-px">
                    <button @click="activeTab = lastOtwTab"
                        class="pb-4 text-sm font-bold transition-all relative cursor-pointer"
                        :class="['incoming_otw', 'outgoing_otw', 'failed_otw'].includes(activeTab) ? 'text-white font-black' : 'text-text-secondary hover:text-white'">
                        <span>Sedang Diproses</span>
                        <div v-if="['incoming_otw', 'outgoing_otw', 'failed_otw'].includes(activeTab)" class="absolute bottom-0 left-0 right-0 h-0.5 bg-white rounded-full"></div>
                    </button>
                    <button @click="activeTab = 'history_in'"
                        class="pb-4 text-sm font-bold transition-all relative cursor-pointer"
                        :class="activeTab === 'history_in' ? 'text-white font-black' : 'text-text-secondary hover:text-white'">
                        <span>Riwayat Masuk</span>
                        <div v-if="activeTab === 'history_in'" class="absolute bottom-0 left-0 right-0 h-0.5 bg-white rounded-full"></div>
                    </button>
                    <button @click="activeTab = 'history_out'"
                        class="pb-4 text-sm font-bold transition-all relative cursor-pointer"
                        :class="activeTab === 'history_out' ? 'text-white font-black' : 'text-text-secondary hover:text-white'">
                        <span>Riwayat Keluar</span>
                        <div v-if="activeTab === 'history_out'" class="absolute bottom-0 left-0 right-0 h-0.5 bg-white rounded-full"></div>
                    </button>
                    <button @click="activeTab = 'history_failed'"
                        class="pb-4 text-sm font-bold transition-all relative cursor-pointer"
                        :class="activeTab === 'history_failed' ? 'text-white font-black' : 'text-text-secondary hover:text-white'">
                        <span>Riwayat Gagal Kirim</span>
                        <div v-if="activeTab === 'history_failed'" class="absolute bottom-0 left-0 right-0 h-0.5 bg-white rounded-full"></div>
                    </button>
                </div>
            </div>
        </div>
 
        <!-- Main Content -->
        <main class="w-full px-6 md:px-10 mt-8">
            <!-- Loading Grid -->
            <div v-if="isLoading && (transfers.length === 0 && historyData.data.length === 0)"
                class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 animate-pulse">
                <div v-for="i in 6" :key="i" class="h-48 bg-surface-800 rounded-[2rem] border border-surface-700/50">
                </div>
            </div>

            <!-- Empty State -->
            <div v-else-if="(!activeTab.includes('history') && transfers.length === 0) || (activeTab.includes('history') && historyData.data.length === 0)"
                class="text-center py-32 bg-surface-800/50 rounded-[3rem] border border-surface-700/30 backdrop-blur-sm shadow-inner mt-4">
                <div
                    class="w-20 h-20 mx-auto bg-surface-700/50 rounded-3xl flex items-center justify-center mb-6 text-text-secondary/20">
                    <Package :size="48" />
                </div>
                <h2 class="text-2xl font-black text-text-primary mb-2">Data Belum Ada</h2>
                <p class="text-text-secondary max-w-xs mx-auto font-medium">Tidak ada barang atau riwayat yang tersedia
                    untuk kategori ini saat ini.</p>
            </div>


            <!-- Unified Table Section -->
            <div v-else class="space-y-8 animate-in duration-500 mt-6">
                <!-- Desktop Table (With Horizontal Scroll for No Cut-offs!) -->
                <div class="hidden md:block overflow-x-auto rounded-[2rem] border border-surface-700/50 bg-surface-800/40 backdrop-blur-sm shadow-xl custom-scrollbar">
                    <table class="w-full text-left border-collapse min-w-[1250px]">
                        <thead>
                            <tr class="border-b border-surface-700/70 bg-surface-800/85">
                                <th class="px-8 py-5 text-xs font-black uppercase tracking-wider text-text-secondary opacity-60">Id Transaksi</th>
                                <th v-if="['incoming_otw', 'outgoing_otw', 'history_in', 'history_out', 'history_failed'].includes(activeTab)" class="px-8 py-5 text-xs font-black uppercase tracking-wider text-text-secondary opacity-60">
                                    {{ ['incoming_otw', 'history_in'].includes(activeTab) ? 'Cabang Pengirim' : 'Tujuan / Penerima' }}
                                </th>
                                <th class="px-8 py-5 text-xs font-black uppercase tracking-wider text-text-secondary opacity-60">Nama Barang</th>
                                <th v-if="['incoming_otw', 'outgoing_otw'].includes(activeTab)" class="px-8 py-5 text-xs font-black uppercase tracking-wider text-text-secondary opacity-60">Resi Ekspedisi</th>
                                <th class="px-8 py-5 text-xs font-black uppercase tracking-wider text-text-secondary opacity-60">Status</th>
                                <th class="px-8 py-5 text-xs font-black uppercase tracking-wider text-text-secondary opacity-60">Waktu Kirim</th>
                                <th v-if="['incoming_otw', 'outgoing_otw', 'history_in', 'history_out', 'history_failed'].includes(activeTab)" class="px-8 py-5 text-xs font-black uppercase tracking-wider text-text-secondary opacity-60">Detail Info</th>
                                <th class="px-8 py-5 text-xs font-black uppercase tracking-wider text-text-secondary opacity-60 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-700/30">
                            <tr v-for="transfer in (activeTab.includes('history') ? historyData.data : transfers)" :key="transfer.id" @click="openModal(transfer)"
                                class="group cursor-pointer hover:bg-surface-700/40 transition-colors duration-200">
                                <td class="px-8 py-5 whitespace-nowrap">
                                    <span class="px-4 py-1.5 rounded-xl bg-surface-750 border border-surface-700 text-sm font-black text-white tracking-wider">
                                        {{ transfer.receipt_id }}
                                    </span>
                                </td>
                                <!-- Cabang Pengirim / Tujuan Penerima -->
                                <td v-if="['incoming_otw', 'outgoing_otw', 'history_in', 'history_out', 'history_failed'].includes(activeTab)" class="px-8 py-5 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <div class="w-8 h-8 rounded-lg bg-blue-500/10 text-blue-500 flex items-center justify-center shrink-0">
                                            <Building2 :size="14" />
                                        </div>
                                        <div class="flex flex-col">
                                            <span class="font-bold text-white text-sm">
                                                {{ ['incoming_otw', 'history_in'].includes(activeTab) ? getSenderDetails(transfer) : (transfer.destination?.name || 'Umum') }}
                                            </span>
                                            <span v-if="['outgoing_otw', 'history_out', 'history_failed'].includes(activeTab) && transfer.receiver_name" class="text-[10px] font-black text-green-400 uppercase tracking-widest mt-0.5">
                                                Penerima: {{ transfer.receiver_name }}
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-8 py-5">
                                    <div class="flex items-center gap-3">
                                        <span class="font-bold text-white text-base max-w-md truncate">
                                            {{ getTransferItemsSummary(transfer) }}
                                        </span>
                                    </div>
                                </td>
                                <!-- Resi Ekspedisi -->
                                <td v-if="['incoming_otw', 'outgoing_otw'].includes(activeTab)" class="px-8 py-5 whitespace-nowrap">
                                    <div v-if="transfer.expedition_tracking_no" class="flex flex-col gap-1">
                                        <span class="text-xs font-black uppercase tracking-widest text-purple-400">
                                            {{ transfer.expedition_name }}
                                        </span>
                                        <button @click.stop="trackPackage(transfer.expedition_name, transfer.expedition_tracking_no)"
                                            class="px-2.5 py-1 bg-purple-500/10 hover:bg-purple-500 text-purple-400 hover:text-white border border-purple-500/20 rounded-lg text-[10px] font-black uppercase tracking-wider transition-all inline-flex items-center gap-1.5 w-fit">
                                            <Truck :size="10" />
                                            <span>Lacak: {{ transfer.expedition_tracking_no }}</span>
                                        </button>
                                    </div>
                                    <span v-else class="text-xs font-medium text-text-secondary opacity-40">-</span>
                                </td>
                                <td class="px-8 py-5 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1.5 text-[10px] px-3 py-1 rounded-full font-black uppercase tracking-widest border"
                                        :class="getStatusBadgeClass(transfer.status, activeTab)">
                                        <span class="w-1.5 h-1.5 rounded-full" 
                                            :class="getStatusBadgeClass(transfer.status, activeTab).includes('text-blue') ? 'bg-blue-500' : getStatusBadgeClass(transfer.status, activeTab).includes('text-green') ? 'bg-green-500' : getStatusBadgeClass(transfer.status, activeTab).includes('text-amber') ? 'bg-amber-500' : 'bg-red-500'"></span>
                                        {{ getStatusLabel(transfer.status, activeTab) }}
                                    </span>
                                </td>
                                <td class="px-8 py-5 whitespace-nowrap">
                                    <div class="flex items-center gap-2 text-text-secondary text-sm font-bold">
                                        <Calendar :size="14" class="opacity-50" />
                                        <span>{{ formatDate(transfer.confirmed_at || transfer.updated_at || transfer.created_at) }}</span>
                                    </div>
                                </td>
                                <!-- Detail Info -->
                                <td v-if="['incoming_otw', 'outgoing_otw', 'history_in', 'history_out', 'history_failed'].includes(activeTab)" class="px-8 py-5">
                                    <div class="flex flex-col gap-1">
                                        <span class="text-xs font-black text-white flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                                            {{ (transfer.items?.length || 0) }} HP, {{ (transfer.non_hp_items?.reduce((sum, i) => sum + (i.quantity || 0), 0) || transfer.nonHpItems?.reduce((sum, i) => sum + (i.quantity || 0), 0) || 0) }} Aksesoris
                                        </span>
                                        <span class="text-[10px] font-black text-purple-400 uppercase tracking-widest flex items-center gap-1">
                                            <span>Dist: {{ transfer.items?.[0]?.distributor?.name || transfer.items?.[0]?.supplier_name || transfer.nonHpItems?.[0]?.distributor?.name || 'Umum' }}</span>
                                        </span>
                                        <span class="text-[10px] font-black text-amber-500 uppercase tracking-widest flex items-center gap-1">
                                            <span>Kondisi: {{ transfer.items?.[0]?.condition || 'Baru/Bekas' }}</span>
                                        </span>
                                        <span v-if="transfer.transfer_notes || transfer.notes" class="text-[10px] text-text-secondary line-clamp-1 italic max-w-xs">
                                            "{{ transfer.transfer_notes || transfer.notes }}"
                                        </span>
                                    </div>
                                </td>
                                <td class="px-8 py-5 whitespace-nowrap text-right">
                                    <div class="inline-flex items-center gap-3">
                                        <!-- Action Buttons inside Row -->
                                        <button v-if="activeTab === 'outgoing_otw'" 
                                            @click.stop="openExpeditionModal(transfer)"
                                            class="px-3 py-1.5 bg-purple-500/10 hover:bg-purple-500 text-purple-500 hover:text-white border border-purple-500/20 rounded-xl transition-all flex items-center gap-1.5 text-xs font-black uppercase">
                                            <Truck :size="12" />
                                            <span>Ekspedisi</span>
                                        </button>

                                        <button v-if="['incoming_otw', 'outgoing_otw', 'history_out'].includes(activeTab)" 
                                            @click.stop="openPrintModal(transfer)"
                                            class="px-3 py-1.5 bg-green-500/10 hover:bg-green-500 text-green-500 hover:text-white border border-green-500/20 rounded-xl transition-all flex items-center gap-1.5 text-xs font-black uppercase">
                                            <Printer :size="12" />
                                            <span>Cetak</span>
                                        </button>

                                        <button v-if="['incoming_otw', 'outgoing_otw'].includes(activeTab) && transfer.expedition_tracking_no" 
                                            @click.stop="trackPackage(transfer.expedition_name, transfer.expedition_tracking_no)"
                                            class="px-3 py-1.5 bg-blue-500/10 hover:bg-blue-500 text-blue-500 hover:text-white border border-blue-500/20 rounded-xl transition-all flex items-center gap-1.5 text-xs font-black uppercase">
                                            <Search :size="12" />
                                            <span>Lacak</span>
                                        </button>

                                        <button class="px-4 py-2 bg-surface-750 group-hover:bg-primary-500 border border-surface-700 group-hover:border-primary-500 text-xs font-black uppercase tracking-widest rounded-xl transition-all inline-flex items-center gap-1.5 text-text-secondary group-hover:text-white">
                                            <span>Detail</span>
                                            <ChevronRight :size="14" class="group-hover:translate-x-0.5 transition-transform" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Stacked Cards (No Scrolling Needed!) -->
                <div class="block md:hidden space-y-4">
                    <div v-for="transfer in (activeTab.includes('history') ? historyData.data : transfers)" :key="transfer.id" @click="openModal(transfer)"
                        class="p-6 bg-surface-800/50 border border-surface-700/50 rounded-[2rem] active:scale-[0.98] transition-all flex flex-col gap-4 cursor-pointer">
                        <div class="flex items-center justify-between">
                            <span class="px-4 py-1.5 rounded-xl bg-surface-750 border border-surface-700 text-sm font-black text-white tracking-wider">
                                {{ transfer.receipt_id }}
                            </span>
                            <span class="inline-flex items-center gap-1.5 text-[10px] px-3 py-1 rounded-full font-black uppercase tracking-widest border"
                                :class="getStatusBadgeClass(transfer.status, activeTab)">
                                <span class="w-1.5 h-1.5 rounded-full" 
                                    :class="getStatusBadgeClass(transfer.status, activeTab).includes('text-blue') ? 'bg-blue-500' : getStatusBadgeClass(transfer.status, activeTab).includes('text-green') ? 'bg-green-500' : getStatusBadgeClass(transfer.status, activeTab).includes('text-amber') ? 'bg-amber-500' : 'bg-red-500'"></span>
                                {{ getStatusLabel(transfer.status, activeTab) }}
                            </span>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-surface-750 flex items-center justify-center text-text-secondary border border-surface-700 shrink-0">
                                <Building2 :size="16" />
                            </div>
                            <div class="flex flex-col">
                                <span class="text-[10px] uppercase font-black tracking-widest opacity-40">Nama Barang</span>
                                <span class="font-bold text-white text-base truncate max-w-xs">
                                    {{ getTransferItemsSummary(transfer) }}
                                </span>
                            </div>
                        </div>
                        <!-- Cabang Pengirim / Penerima (Mobile) -->
                        <div v-if="['incoming_otw', 'outgoing_otw'].includes(activeTab)" class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-surface-750 flex items-center justify-center text-text-secondary border border-surface-700 shrink-0">
                                <Building2 :size="16" />
                            </div>
                            <div class="flex flex-col">
                                <span class="text-[10px] uppercase font-black tracking-widest opacity-40">{{ activeTab === 'incoming_otw' ? 'Cabang Pengirim' : 'Tujuan / Penerima' }}</span>
                                <span class="font-bold text-white text-base truncate max-w-xs">
                                    {{ activeTab === 'incoming_otw' ? getSenderDetails(transfer) : (transfer.destination?.name || 'Umum') }}
                                </span>
                            </div>
                        </div>
                        <!-- Resi Ekspedisi (Mobile) -->
                        <div v-if="['incoming_otw', 'outgoing_otw'].includes(activeTab) && transfer.expedition_tracking_no" class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-surface-750 flex items-center justify-center text-text-secondary border border-surface-700 shrink-0">
                                <Truck :size="16" />
                            </div>
                            <div class="flex flex-col">
                                <span class="text-[10px] uppercase font-black tracking-widest opacity-40">Resi {{ transfer.expedition_name }}</span>
                                <span class="font-bold text-purple-400 text-sm">
                                    {{ transfer.expedition_tracking_no }}
                                </span>
                            </div>
                        </div>
                        <!-- Detail Info (Mobile) -->
                        <div v-if="['incoming_otw', 'outgoing_otw'].includes(activeTab)" class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-surface-750 flex items-center justify-center text-text-secondary border border-surface-700 shrink-0">
                                <Package :size="16" />
                            </div>
                            <div class="flex flex-col gap-0.5">
                                <span class="text-[10px] uppercase font-black tracking-widest opacity-40">Detail Info</span>
                                <span class="font-bold text-white text-sm">
                                    {{ (transfer.items?.length || 0) }} HP, {{ (transfer.non_hp_items?.reduce((sum, i) => sum + (i.quantity || 0), 0) || transfer.nonHpItems?.reduce((sum, i) => sum + (i.quantity || 0), 0) || 0) }} Aksesoris
                                </span>
                                <div class="flex gap-2 items-center text-[10px] font-black uppercase tracking-wider">
                                    <span class="text-purple-400">Dist: {{ transfer.items?.[0]?.distributor?.name || transfer.items?.[0]?.supplier_name || transfer.nonHpItems?.[0]?.distributor?.name || 'Umum' }}</span>
                                    <span class="text-surface-600">|</span>
                                    <span class="text-amber-500">Kondisi: {{ transfer.items?.[0]?.condition || 'Baru/Bekas' }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="flex justify-between items-center pt-4 border-t border-surface-700/30">
                            <div class="flex items-center gap-2 text-text-secondary text-xs font-bold">
                                <Calendar :size="14" class="opacity-50" />
                                <span>{{ formatDate(transfer.confirmed_at || transfer.updated_at || transfer.created_at) }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <button v-if="activeTab === 'outgoing_otw'" 
                                    @click.stop="openExpeditionModal(transfer)"
                                    class="px-3 py-1.5 bg-purple-500/10 hover:bg-purple-500 text-purple-500 hover:text-white border border-purple-500/20 rounded-xl transition-all text-[10px] font-black uppercase">
                                    Exp
                                </button>
                                <button v-if="['incoming_otw', 'outgoing_otw', 'history_out'].includes(activeTab)" 
                                            @click.stop="openPrintModal(transfer)"
                                            class="px-3 py-1.5 bg-green-500/10 hover:bg-green-500 text-green-500 hover:text-white border border-green-500/20 rounded-xl transition-all text-[10px] font-black uppercase">
                                            Cetak
                                        </button>
                                        
                                <button v-if="['incoming_otw', 'outgoing_otw'].includes(activeTab) && transfer.expedition_tracking_no" 
                                    @click.stop="trackPackage(transfer.expedition_name, transfer.expedition_tracking_no)"
                                    class="px-3 py-1.5 bg-blue-500/10 hover:bg-blue-500 text-blue-500 hover:text-white border border-blue-500/20 rounded-xl transition-all text-[10px] font-black uppercase">
                                    Lacak
                                </button>
                                <span class="text-xs font-black uppercase tracking-widest text-primary-500 flex items-center gap-1">
                                    <span>Detail</span>
                                    <ChevronRight :size="14" />
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <div class="flex flex-col sm:flex-row items-center justify-between gap-6 pt-10 border-t border-surface-700/50"
                    v-if="historyData.last_page > 1">
                    <p class="text-sm text-text-secondary font-bold">
                        Hal <span class="text-white">{{ historyData.current_page }}</span> dari <span
                            class="text-white">{{ historyData.last_page }}</span>
                    </p>
                    <div class="flex gap-4 w-full sm:w-auto">
                        <button @click="fetchData(currentPage - 1)" :disabled="currentPage === 1 || isLoading"
                            class="flex-1 sm:flex-none h-12 px-6 rounded-xl bg-surface-800 border border-surface-700 hover:border-primary-500/50 disabled:opacity-30 transition-all font-bold text-white flex items-center justify-center">
                            <ChevronLeft :size="20" />
                        </button>
                        <button @click="fetchData(currentPage + 1)"
                            :disabled="currentPage === historyData.last_page || isLoading"
                            class="flex-1 sm:flex-none h-12 px-6 rounded-xl bg-surface-800 border border-surface-700 hover:border-primary-500/50 disabled:opacity-30 transition-all font-bold text-white flex items-center justify-center">
                            <ChevronRight :size="20" />
                        </button>
                    </div>
                </div>
            </div>
        </main>

        <!-- --- Modals --- -->

        <!-- Incoming OTW Modal (Receive) -->
        <div v-if="showReceiveModal && selectedTransfer" class="modal-backdrop" @click.self="closeModal">
            <div class="modal-content max-w-4xl max-h-[90vh]">
                <div class="modal-header">
                    <div>
                        <h2 class="text-2xl font-black text-white flex items-center gap-3">
                            <ArrowDownRight class="text-blue-500" /> Konfirmasi Terima Barang
                        </h2>
                        <p class="text-sm text-text-secondary font-medium mt-1">Nota: {{ selectedTransfer.receipt_id }}
                        </p>
                    </div>
                    <button @click="closeModal" class="close-btn">
                        <X :size="20" />
                    </button>
                </div>

                <div class="modal-body space-y-12 pb-12 custom-scrollbar">
                    <!-- Verifikator Selection -->
                    <div class="p-8 bg-blue-500/5 border border-blue-500/20 rounded-[2rem] space-y-4">
                        <div class="flex items-center gap-3">
                            <User :size="20" class="text-blue-500" />
                            <h3 class="text-sm font-black uppercase tracking-widest text-white">Akun Verifikator</h3>
                        </div>
                        <select v-model="selectedInventoryAccount" class="modern-select">
                            <option value="" disabled>Pilih Identitas Penerima...</option>
                            <option v-for="acc in inventoryAccounts" :key="acc.id" :value="acc.id">{{ acc.full_name }}
                                ({{ acc.code_id }})</option>
                        </select>
                    </div>

                    <!-- Receiver & Transfer Notes Details -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 px-2">
                        <div class="p-6 bg-surface-800 border border-surface-700/50 rounded-2xl flex items-center gap-4">
                            <div class="w-10 h-10 rounded-xl bg-green-500/10 flex items-center justify-center text-green-500 shrink-0">
                                <User :size="18" />
                            </div>
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-widest text-text-secondary opacity-50">Nama Penerima</p>
                                <p class="text-base font-black text-white mt-0.5">{{ selectedTransfer.receiver_name || '-' }}</p>
                            </div>
                        </div>
                        <div class="p-6 bg-surface-800 border border-surface-700/50 rounded-2xl flex items-center gap-4">
                            <div class="w-10 h-10 rounded-xl bg-amber-500/10 flex items-center justify-center text-amber-500 shrink-0">
                                <FileText :size="18" />
                            </div>
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-widest text-text-secondary opacity-50">Catatan Pengirim</p>
                                <p class="text-sm font-bold text-text-primary italic mt-0.5">"{{ selectedTransfer.transfer_notes || selectedTransfer.notes || 'Tidak ada catatan' }}"</p>
                            </div>
                        </div>
                    </div>

                    <!-- Items List -->
                    <div class="space-y-10 px-2">
                        <!-- HP Items -->
                        <div v-if="selectedTransfer.items?.length">
                            <h4
                                class="text-xs font-black text-text-secondary uppercase tracking-widest mb-6 flex items-center gap-2">
                                <Smartphone :size="16" class="text-blue-500" /> Unit HP (Tap untuk Tolak)
                            </h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div v-for="item in selectedTransfer.items" :key="item.id"
                                    class="p-5 rounded-2xl border-2 cursor-pointer transition-all flex justify-between items-start"
                                    :class="receiveForm.accepted_items.includes(item.id) ? 'bg-blue-500/10 border-blue-500/30' : 'bg-red-500/10 border-red-500/30 grayscale'"
                                    @click="(() => {
                                        const idx = receiveForm.accepted_items.indexOf(item.id);
                                        if (idx === -1) receiveForm.accepted_items.push(item.id);
                                        else receiveForm.accepted_items.splice(idx, 1);
                                    })()">
                                    <div>
                                        <p class="font-black text-white">{{ getBrandName(item) }} {{ item.product?.name
                                            }}</p>
                                        <p
                                            class="text-[10px] font-mono font-bold text-text-secondary mt-1 tracking-widest">
                                            {{ item.imei }}</p>
                                        <!-- Rejection Note Input -->
                                        <textarea v-if="!receiveForm.accepted_items.includes(item.id)"
                                            v-model="receiveForm.items_rejection[item.id]" @click.stop
                                            placeholder="Alasan penolakan..."
                                            class="mt-3 w-full bg-surface-900/50 border border-red-500/20 rounded-xl p-3 text-xs text-white focus:outline-none"></textarea>
                                    </div>
                                    <CheckCircle2 v-if="receiveForm.accepted_items.includes(item.id)"
                                        class="text-green-500" :size="20" />
                                    <X v-else class="text-red-500" :size="20" />
                                </div>
                            </div>
                        </div>

                        <!-- Non-HP Items -->
                        <div v-if="selectedTransfer.non_hp_items?.length">
                            <h4
                                class="text-xs font-black text-text-secondary uppercase tracking-widest mb-6 flex items-center gap-2">
                                <Package :size="16" class="text-orange-500" /> Aksesoris / Non-HP
                            </h4>
                            <div class="space-y-4">
                                <div v-for="item in selectedTransfer.non_hp_items" :key="item.id"
                                    class="p-6 bg-surface-800 border border-surface-700 rounded-2xl">
                                    <div class="flex items-center justify-between mb-4">
                                        <p class="font-black text-white">{{ getBrandName(item) }} {{ item.product?.name
                                            }}</p>
                                        <div class="flex items-center gap-4">
                                            <span class="text-xs font-bold text-text-secondary">Diterima:</span>
                                            <input type="number" v-model="receiveForm.non_hp_quantities[item.id]"
                                                class="w-20 bg-surface-900 border border-surface-600 rounded-lg py-1 px-3 text-center font-black text-primary-500"
                                                min="0" :max="item.quantity" />
                                        </div>
                                    </div>
                                    <textarea v-if="receiveForm.non_hp_quantities[item.id] < item.quantity"
                                        v-model="receiveForm.non_hp_rejection_notes[item.id]"
                                        placeholder="Alasan pengurangan..."
                                        class="w-full bg-surface-900/50 border border-red-500/20 rounded-xl p-3 text-xs text-white focus:outline-none mt-2"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button @click="submitReceive()" :disabled="isSubmitting"
                        class="action-btn bg-blue-600 hover:bg-blue-500">
                        <Loader2 v-if="isSubmitting" class="animate-spin mr-2" />
                        <span v-else>TERIMA & SIMPAN KE STOK</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Generic Detail Modal (History & Outgoing) -->
        <div v-if="showDetailModal && selectedTransfer" class="modal-backdrop" @click.self="closeModal">
            <div class="modal-content max-w-4xl max-h-[90vh]">
                <div class="modal-header">
                    <div>
                        <h2 class="text-2xl font-black text-white">Detail Transfer</h2>
                        <p class="text-sm font-bold text-text-secondary">{{ selectedTransfer.receipt_id }} — {{
                            selectedTransfer.status }}</p>
                    </div>
                    <button @click="closeModal" class="close-btn">
                        <X :size="20" />
                    </button>
                </div>
                <div class="modal-body pb-12 custom-scrollbar">
                    <!-- Detail info grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-12">
                        <div class="detail-card">
                            <p class="label">Pengirim</p>
                            <p class="value text-white">{{ (selectedTransfer.inventory_user?.name || selectedTransfer.inventoryUser?.name || selectedTransfer.user?.name) || 'Unknown' }}</p>
                        </div>
                        <div class="detail-card">
                            <p class="label">Tujuan</p>
                            <p class="value text-white">{{ selectedTransfer.destination?.name || '-' }}</p>
                        </div>
                        <div class="detail-card">
                            <p class="label">Waktu Kirim</p>
                            <p class="value text-white">{{ formatDate(selectedTransfer.created_at) }}</p>
                        </div>
                        <div class="detail-card">
                            <p class="label">Nama Penerima</p>
                            <p class="value text-white">{{ selectedTransfer.receiver_name || '-' }}</p>
                        </div>
                        <div class="detail-card col-span-1 sm:col-span-2 lg:col-span-2">
                            <p class="label">Catatan Transfer</p>
                            <p class="text-sm font-bold text-text-primary italic mt-1">"{{ selectedTransfer.transfer_notes || selectedTransfer.notes || 'Tidak ada catatan' }}"</p>
                        </div>

                        <!-- Expedition Info -->
                        <div v-if="selectedTransfer.expedition_name" class="detail-card border-purple-500/20 bg-purple-500/5">
                            <p class="label text-purple-400">Ekspedisi</p>
                            <p class="value text-white">{{ selectedTransfer.expedition_name }}</p>
                        </div>
                        <div v-if="selectedTransfer.expedition_tracking_no" class="detail-card border-purple-500/20 bg-purple-500/5">
                            <p class="label text-purple-400">No Resi</p>
                            <p class="value text-white flex items-center justify-between">
                                <span>{{ selectedTransfer.expedition_tracking_no }}</span>
                                <button @click="trackPackage(selectedTransfer.expedition_name, selectedTransfer.expedition_tracking_no)" class="p-1 hover:bg-white/10 rounded">
                                    <Search :size="14" class="text-purple-400" />
                                </button>
                            </p>
                        </div>
                        <div v-if="selectedTransfer.expedition_date" class="detail-card border-purple-500/20 bg-purple-500/5">
                            <p class="label text-purple-400">Tgl Ekspedisi</p>
                            <p class="value text-white">{{ formatDate(selectedTransfer.expedition_date) }}</p>
                        </div>
                    </div>

                    <!-- Items -->
                    <div class="space-y-10">
                        <div v-if="selectedTransfer.items?.length">
                            <h4 class="text-xs font-black text-text-secondary uppercase tracking-[0.2em] mb-4">Unit HP
                            </h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div v-for="item in selectedTransfer.items" :key="item.id"
                                    class="p-6 bg-surface-800 border border-surface-700/50 rounded-2xl flex justify-between items-center group">
                                    <div>
                                        <p class="font-black text-white group-hover:text-primary-400 transition-colors">
                                            {{ getBrandName(item) }} {{ item.product?.name }}
                                        </p>
                                        <p class="text-[10px] font-mono font-bold text-text-secondary mt-1">{{ item.imei
                                            }}</p>
                                        <div class="flex gap-2 mt-2">
                                            <span
                                                class="px-2 py-0.5 rounded bg-surface-700 text-[10px] font-black text-text-secondary uppercase">
                                                {{ item.condition }}
                                            </span>
                                            <span
                                                class="px-2 py-0.5 rounded bg-surface-700 text-[10px] font-black text-text-secondary uppercase">
                                                {{ formatCapacity(item.ram, item.storage) }}
                                            </span>
                                        </div>
                                    </div>
                                    <CheckCircle2 v-if="item.status === 'received' || transfer?.status === 'received'"
                                        class="text-green-500 opacity-20" :size="24" />
                                    <Clock v-else class="text-amber-500 animate-pulse" :size="24" />
                                </div>
                            </div>
                        </div>

                        <div v-if="selectedTransfer.non_hp_items?.length">
                            <h4 class="text-xs font-black text-text-secondary uppercase tracking-[0.2em] mb-4">Aksesoris
                            </h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div v-for="item in selectedTransfer.non_hp_items" :key="item.id"
                                    class="p-6 bg-surface-800 border border-surface-700/50 rounded-2xl flex justify-between items-center">
                                    <p class="font-black text-white">{{ item.product?.name || item.product_name }}</p>
                                    <p class="text-lg font-black text-primary-500">{{ item.quantity }} <span
                                            class="text-[10px] uppercase opacity-40">Qty</span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirm Return (Failed OTW) Modal -->
        <div v-if="showReturnModal && selectedTransfer" class="modal-backdrop" @click.self="closeModal">
            <div class="modal-content max-w-2xl">
                <div class="modal-header">
                    <h2 class="text-2xl font-black text-white">Terima Kembali Barang</h2>
                    <button @click="closeModal" class="close-btn">
                        <X :size="20" />
                    </button>
                </div>
                <div class="modal-body space-y-8 pb-12 custom-scrollbar">
                    <!-- Verifikator Selection -->
                    <div class="p-8 bg-red-500/5 border border-red-500/20 rounded-[2rem] space-y-4">
                        <div class="flex items-center gap-3">
                            <User :size="20" class="text-red-500" />
                            <h3 class="text-sm font-black uppercase tracking-widest text-white">Akun Cabang Penerima
                            </h3>
                        </div>
                        <select v-model="selectedInventoryAccount" class="modern-select">
                            <option value="" disabled>Pilih Identitas Penerima Kembali...</option>
                            <option v-for="acc in inventoryAccounts" :key="acc.id" :value="acc.id">{{ acc.full_name }}
                                ({{ acc.code_id }})</option>
                        </select>
                    </div>

                    <!-- Who Rejected Card -->
                    <div v-if="selectedTransfer.confirmed_by || selectedTransfer.confirmedBy" class="px-4">
                        <div class="p-6 bg-red-500/5 border border-red-500/10 rounded-2xl flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-red-500/10 flex items-center justify-center text-red-500">
                                    <User :size="18" />
                                </div>
                                <div>
                                    <p class="text-xs text-text-secondary font-bold uppercase tracking-wider">Ditolak Oleh</p>
                                    <p class="text-sm font-black text-white">
                                        {{ (selectedTransfer.confirmed_by || selectedTransfer.confirmedBy).full_name }} 
                                        <span class="text-xs font-mono text-text-secondary">({{ (selectedTransfer.confirmed_by || selectedTransfer.confirmedBy).username }})</span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Rejected Items List with Notes -->
                    <div v-if="selectedTransfer.items?.filter(item => item.pivot?.status === 'rejected').length" class="space-y-3">
                        <h4 class="text-xs font-black text-text-secondary uppercase tracking-[0.2em] px-4">Detail Barang Ditolak</h4>
                        <div class="space-y-3 px-4">
                            <div v-for="item in selectedTransfer.items.filter(item => item.pivot?.status === 'rejected')" :key="item.id" 
                                 class="p-5 bg-surface-800 border border-surface-700/50 rounded-2xl space-y-3 animate-in">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <p class="font-black text-white text-sm">{{ item.product?.name || item.product_name }}</p>
                                        <p class="text-[11px] font-mono text-text-secondary mt-0.5">IMEI: {{ item.imei || '-' }}</p>
                                    </div>
                                    <span class="px-2.5 py-1 rounded bg-red-500/10 text-red-500 text-[10px] font-black uppercase">Ditolak</span>
                                </div>
                                <div class="p-3.5 bg-red-500/5 border border-red-500/10 rounded-xl">
                                    <p class="text-[10px] font-black text-red-400 uppercase tracking-wider">Alasan Penolakan:</p>
                                    <p class="text-xs text-text-primary font-medium mt-1">{{ item.pivot?.notes || 'Tidak ada alasan ditulis' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <p class="text-text-secondary font-medium px-4 leading-relaxed">
                        Konfirmasi ini akan memasukkan kembali unit yang ditolak dari nota <span
                            class="text-white font-black">{{ selectedTransfer.receipt_id }}</span> kembali ke stok aktif
                        Anda.
                    </p>
                </div>
                <div class="modal-footer">
                    <button @click="handleVerifyPin((pin) => submitReturn(pin))"
                        :disabled="isSubmitting || !selectedInventoryAccount"
                        class="action-btn bg-red-600 hover:bg-red-500">
                        <CheckCircle2 class="mr-2" :size="20" /> TERIMA BALIK KE STOK
                    </button>
                </div>
            </div>
        </div>

        <PinModal :show="showPinModal" @close="showPinModal = false" @success="onPinVerified" />

        <!-- Expedition Modal -->
        <div v-if="showExpeditionModal && selectedTransfer" class="modal-backdrop" @click.self="closeExpeditionModal">
            <div class="modal-content max-w-lg">
                <div class="modal-header">
                    <div>
                        <h2 class="text-2xl font-black text-white flex items-center gap-3">
                            <Truck class="text-purple-500" /> Informasi Ekspedisi
                        </h2>
                        <p class="text-sm text-text-secondary font-medium">Nota: {{ selectedTransfer.receipt_id }}</p>
                    </div>
                    <button @click="closeExpeditionModal" class="close-btn">
                        <X :size="20" />
                    </button>
                </div>
                <div class="modal-body space-y-6 pb-12 custom-scrollbar">
                    <div class="space-y-4">
                        <div>
                            <label class="label">Pilih Ekspedisi</label>
                            <select v-model="expeditionForm.expedition_name" class="modern-select">
                                <option value="" disabled>Pilih Kurir...</option>
                                <option v-for="c in couriers" :key="c" :value="c">{{ c }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Nomor Resi</label>
                            <input v-model="expeditionForm.expedition_tracking_no" type="text" placeholder="Masukkan No Resi..."
                                class="w-full bg-surface-900 border-2 border-surface-700 rounded-2xl px-6 py-4 text-sm font-bold text-white focus:outline-none focus:border-purple-500 transition-all" />
                        </div>
                        <div>
                            <label class="label">Tanggal Kirim</label>
                            <input v-model="expeditionForm.expedition_date" type="date"
                                class="w-full bg-surface-900 border-2 border-surface-700 rounded-2xl px-6 py-4 text-sm font-bold text-white focus:outline-none focus:border-purple-500 transition-all" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button @click="submitExpedition" :disabled="isSubmitting"
                        class="action-btn bg-purple-600 hover:bg-purple-500">
                        <Loader2 v-if="isSubmitting" class="animate-spin mr-2" />
                        <span v-else>SIMPAN INFORMASI</span>
                    </button>
                </div>
            </div>
        </div>
        <!-- Real-time Integrated Tracking Modal (High Stability, Premium & Dark Mode Ready) -->
        <div v-if="showTrackingModal" class="fixed inset-0 z-[100] flex items-end sm:items-center justify-center bg-surface-950/90 backdrop-blur-md transition-all duration-300">
            <!-- Close Overlay -->
            <div class="absolute inset-0" @click="closeTrackingModal"></div>

            <div class="bg-surface-50 dark:bg-surface-900 w-full sm:max-w-2xl h-[92vh] sm:h-auto sm:max-h-[85vh] rounded-t-[40px] sm:rounded-[40px] shadow-2xl overflow-hidden flex flex-col border-t sm:border border-white/20 dark:border-surface-700 relative z-10 transition-all">
                <!-- Header (Always Dark for Premium Look) -->
                <div class="bg-surface-950 p-6 md:p-8 text-white flex justify-between items-center shrink-0">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-white/10 flex items-center justify-center">
                            <Truck class="w-6 h-6 text-blue-400" />
                        </div>
                        <div>
                            <h2 class="text-xl md:text-2xl font-black tracking-tighter">Status Pengiriman</h2>
                            <p class="text-[10px] md:text-xs font-black text-surface-400 uppercase tracking-widest leading-none">Tracking Real-time Satelit</p>
                        </div>
                    </div>
                    <button @click="closeTrackingModal" class="bg-white/10 hover:bg-white/20 p-3 rounded-2xl transition-all active:scale-90">
                        <X class="w-6 h-6" />
                    </button>
                </div>

                <!-- Body wrapper -->
                <div class="flex-1 overflow-hidden flex flex-col relative">
                    <!-- 1. Loading State -->
                    <div v-if="isTracking" key="tracking-loading" class="flex-1 flex flex-col items-center justify-center py-20 px-10 text-center bg-white dark:bg-surface-900">
                        <div class="relative w-16 h-16 mb-6">
                            <div class="absolute inset-0 border-4 border-surface-200 dark:border-surface-800 rounded-full"></div>
                            <div class="absolute inset-0 border-4 border-blue-600 rounded-full border-t-transparent animate-spin"></div>
                        </div>
                        <h3 class="text-lg font-black text-surface-900 dark:text-white tracking-tighter uppercase">Menghubungkan...</h3>
                        <p class="text-xs font-bold text-surface-400 uppercase tracking-widest mt-1">Mengambil data dari kurir pusat</p>
                    </div>

                    <!-- 2. Results Found State -->
                    <div v-else-if="trackingResult" key="tracking-result" class="flex-1 overflow-y-auto custom-scrollbar flex flex-col bg-surface-50 dark:bg-surface-950">
                        <!-- Summary Card -->
                        <div class="bg-white dark:bg-surface-900 p-6 md:p-8 border-b border-surface-100 dark:border-surface-800 shrink-0">
                            <div class="flex flex-col sm:flex-row justify-between items-start gap-6 mb-8">
                                <div class="space-y-1">
                                    <span class="px-3 py-1 bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 text-[10px] font-black rounded-full border border-blue-100 dark:border-blue-800 uppercase tracking-widest">
                                        {{ trackingResult.summary.courier }}
                                    </span>
                                    <h1 class="text-3xl font-black text-surface-900 dark:text-white tracking-tighter">{{ trackingResult.summary.awb }}</h1>
                                </div>
                                <div :class="[
                                    'px-6 py-3 rounded-2xl font-black text-sm uppercase tracking-tighter border-b-4 w-full sm:w-auto text-center shadow-lg',
                                    trackingResult.summary.status.toLowerCase().includes('diterima') || trackingResult.summary.status.toLowerCase().includes('delivered')
                                    ? 'bg-green-500 text-white border-green-700 shadow-green-500/20' : 'bg-blue-600 text-white border-blue-800 shadow-blue-600/20'
                                ]">
                                    {{ trackingResult.summary.status }}
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="bg-surface-50 dark:bg-surface-800 p-5 rounded-3xl border border-surface-100 dark:border-surface-700 shadow-sm">
                                    <p class="text-[10px] font-black text-surface-400 dark:text-surface-500 uppercase tracking-widest mb-1.5 flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full bg-blue-500"></span> PENGIRIM
                                    </p>
                                    <p class="text-base font-black text-surface-900 dark:text-white leading-tight truncate">{{ trackingResult.detail.shipper }}</p>
                                    <p class="text-xs font-bold text-surface-500 dark:text-surface-400 truncate mt-1">{{ trackingResult.detail.origin }}</p>
                                </div>
                                <div class="bg-surface-50 dark:bg-surface-800 p-5 rounded-3xl border border-surface-100 dark:border-surface-700 shadow-sm">
                                    <p class="text-[10px] font-black text-surface-400 dark:text-surface-500 uppercase tracking-widest mb-1.5 flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full bg-green-500"></span> PENERIMA
                                    </p>
                                    <p class="text-base font-black text-surface-900 dark:text-white leading-tight truncate">{{ trackingResult.detail.receiver }}</p>
                                    <p class="text-xs font-bold text-surface-500 dark:text-surface-400 truncate mt-1">{{ trackingResult.detail.destination }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Timeline Content -->
                        <div class="p-6 md:p-8">
                            <div class="flex items-center gap-3 mb-8">
                                <div class="w-1.5 h-6 bg-surface-900 dark:bg-blue-500 rounded-full"></div>
                                <h3 class="text-xs font-black text-surface-900 dark:text-white uppercase tracking-widest">Riwayat Perjalanan</h3>
                            </div>

                            <div class="relative pl-6 space-y-0">
                                <!-- Modern Vertical Line -->
                                <div class="absolute left-6 top-4 bottom-4 w-1 bg-gradient-to-b from-blue-600 via-surface-200 dark:via-surface-800 to-transparent rounded-full -translate-x-1/2"></div>

                                <div v-for="(step, index) in trackingResult.history" :key="'step-'+index" 
                                    class="relative pl-10 pb-10 last:pb-4">
                                    <!-- Dynamic Dot -->
                                    <div :class="[
                                        'absolute left-0 w-8 h-8 rounded-full flex items-center justify-center z-10 border-4 border-surface-50 dark:border-surface-950 transition-all -translate-x-1/2',
                                        index === 0 ? 'bg-blue-600 shadow-xl shadow-blue-500/50 scale-110' : 'bg-surface-200 dark:bg-surface-800'
                                    ]">
                                        <div v-if="index === 0" class="w-2.5 h-2.5 bg-white rounded-full animate-pulse"></div>
                                        <div v-else class="w-2 h-2 bg-surface-400 dark:bg-surface-600 rounded-full"></div>
                                    </div>

                                    <div :class="[
                                        'p-5 rounded-[2.5rem] border transition-all',
                                        index === 0 ? 'bg-white dark:bg-surface-900 border-blue-100 dark:border-blue-900 shadow-xl shadow-surface-900/5 -translate-y-1' : 'bg-transparent border-transparent opacity-60'
                                    ]">
                                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-2">
                                            <p :class="['text-[11px] font-black uppercase tracking-widest text-surface-400 dark:text-surface-500']">
                                                {{ step.date }}
                                            </p>
                                            <span v-if="step.location" class="inline-block px-3 py-1 bg-surface-900 dark:bg-surface-800 text-white dark:text-blue-400 text-[10px] font-black rounded-xl w-fit whitespace-nowrap border dark:border-surface-700">
                                                {{ step.location }}
                                            </span>
                                        </div>
                                        <p :class="['text-sm md:text-base leading-relaxed break-words', index === 0 ? 'font-black text-surface-900 dark:text-white' : 'font-bold text-surface-600 dark:text-surface-400']">
                                            {{ step.desc }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Not Found State -->
                    <div v-else key="tracking-empty" class="flex-1 flex flex-col items-center justify-center py-20 px-10 text-center bg-surface-50 dark:bg-surface-900">
                        <div class="w-20 h-20 bg-surface-100 dark:bg-surface-800 rounded-[32px] flex items-center justify-center mb-6">
                            <SearchX class="w-10 h-10 text-surface-400 dark:text-surface-500" />
                        </div>
                        <h3 class="text-xl font-black text-surface-900 dark:text-white tracking-tighter">Data Pelacakan Belum Muncul</h3>
                        <p class="text-sm text-surface-500 font-bold mt-2 max-w-xs mx-auto uppercase tracking-tighter leading-tight">Mungkin resi masih baru atau server kurir sedang gangguan sinkronisasi.</p>
                    </div>
                </div>

                <!-- Modern Footer -->
                <div class="bg-white dark:bg-surface-900 p-6 md:p-8 border-t border-surface-100 dark:border-surface-800 shrink-0">
                    <div class="flex flex-col sm:flex-row justify-between items-center gap-6">
                        <div v-if="trackingResult" class="flex items-center gap-3">
                            <div class="flex -space-x-2">
                                <div class="w-6 h-6 rounded-full border-2 border-white dark:border-surface-800 bg-blue-600"></div>
                                <div class="w-6 h-6 rounded-full border-2 border-white dark:border-surface-800 bg-surface-900 dark:bg-surface-700"></div>
                            </div>
                            <span class="text-[10px] font-black text-surface-400 dark:text-surface-500 uppercase tracking-widest">
                                API SOURCE: <span class="text-blue-600 dark:text-blue-400 font-black">{{ trackingProvider || 'PRIMARY' }}</span>
                            </span>
                        </div>
                        <button @click="closeTrackingModal" 
                            class="w-full sm:w-auto px-12 py-4 bg-surface-950 dark:bg-blue-600 hover:bg-surface-900 dark:hover:bg-blue-500 text-white font-black rounded-[2rem] uppercase tracking-tighter text-sm transition-all duration-300 active:scale-95 shadow-xl shadow-surface-900/20">
                            KEMBALI KE MONITORING
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <TransferReceiptModal :isOpen="showPrintModal" :transfer="selectedTransfer" @close="showPrintModal = false" />
    </div>
</template>

<style scoped>
@reference "../../style.css";

.modal-backdrop {
    @apply fixed inset-0 bg-black/90 z-50 flex items-center justify-center p-2 sm:p-4 backdrop-blur-md;
    animation: var(--animate-fade-in);
    animation-duration: 300ms;
}

.modal-content {
    @apply bg-surface-900 rounded-[2.5rem] w-full max-h-[90vh] flex flex-col border border-surface-700 shadow-2xl overflow-hidden;
    animation: var(--animate-fade-in);
}

.modal-header {
    @apply px-8 py-8 border-b border-surface-700 flex justify-between items-start;
}

.close-btn {
    @apply p-3 bg-surface-700 hover:bg-surface-600 rounded-2xl text-text-secondary hover:text-white transition-all active:scale-90;
}

.modal-body {
    @apply p-6 sm:p-12 overflow-y-auto flex-1;
}

.modal-footer {
    @apply px-8 py-8 border-t border-surface-700;
}

.label {
    @apply text-[10px] font-black uppercase tracking-widest text-text-secondary opacity-50 mb-1;
}

.value {
    @apply text-lg font-bold truncate;
}

.detail-card {
    @apply bg-surface-800 p-6 rounded-3xl border border-white/5;
}

.modern-select {
    @apply w-full bg-surface-900 border-2 border-surface-700 rounded-2xl px-6 py-4 text-sm font-bold text-white focus:outline-none focus:border-primary-500 transition-all appearance-none cursor-pointer;
}

.action-btn {
    @apply w-full h-16 text-white font-black text-lg rounded-2xl active:scale-[0.98] transition-all flex items-center justify-center shadow-xl;
}

.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    @apply bg-surface-700 rounded-full;
}

.mask-fade-right {
    mask-image: linear-gradient(to right, black 85%, transparent 100%);
}

.no-scrollbar::-webkit-scrollbar {
    display: none;
}

.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>

