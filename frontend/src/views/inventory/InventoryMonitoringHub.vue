<script setup>
import { ref, onMounted, computed, watch } from "vue";
import api from "../../api/axios";
import { useToast } from "../../composables/useToast";
import { useRouter } from "vue-router";
import PinModal from "../../components/modals/PinModal.vue";
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
    LayoutDashboard
} from "lucide-vue-next";

const toast = useToast();
const router = useRouter();

// Tab State
const activeTab = ref("incoming_otw"); // incoming_otw, outgoing_otw, failed_otw, history_in, history_out
const tabs = [
    { id: "incoming_otw", name: "Konfirmasi Masuk", icon: ArrowDownRight, color: "text-blue-500", bg: "bg-blue-500/10" },
    { id: "outgoing_otw", name: "Pantau Kiriman", icon: ArrowUpRight, color: "text-purple-500", bg: "bg-purple-500/10" },
    { id: "failed_otw", name: "Gagal Kirim", icon: AlertTriangle, color: "text-red-500", bg: "bg-red-500/10" },
    { id: "history_in", name: "Riwayat Masuk", icon: FileText, color: "text-green-500", bg: "bg-green-500/10" },
    { id: "history_out", name: "Riwayat Keluar", icon: FileText, color: "text-amber-500", bg: "bg-amber-500/10" },
];

// Global Loading State
const isLoading = ref(false);

// Common Data States
const transfers = ref([]); // For OTW tabs
const historyData = ref({ data: [], current_page: 1, last_page: 1, total: 0 }); // For History tabs
const searchQuery = ref("");
const currentPage = ref(1);

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
const showTrackingModal = ref(false); // NEW: For real-time tracking display

const couriers = ref([
    'JNE', 'J&T', 'Sicepat', 'POS Indonesia', 'Tiki', 'Wahana', 'Anteraja', 'Ninja Xpress', 'Lion Parcel', 'ID Express',
    'SAP Express', 'RPX', 'JET Express', 'Indah Logistic', 'First Logistics', 'NCS', 'REX', 'Shopee Express'
]);

const expeditionForm = ref({
    expedition_name: "",
    expedition_tracking_no: "",
    expedition_date: new Date().toISOString().substr(0, 10),
});

const trackingData = ref(null);
const isTracking = ref(false);

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
        if (activeTab.value === "incoming_otw") {
            const response = await api.get('/transfers/pending');
            transfers.value = response.data.data || response.data || [];
        } else if (activeTab.value === "outgoing_otw") {
            const response = await api.get('/transfers/outgoing');
            transfers.value = response.data.data || response.data || [];
        } else if (activeTab.value === "failed_otw") {
            const response = await api.get('/transfers/failed');
            transfers.value = response.data.data || response.data || [];
        } else if (activeTab.value === "history_in") {
            const response = await api.get('/transfers/history', {
                params: { page, q: searchQuery.value, type: 'incoming' }
            });
            historyData.value = response.data;
            currentPage.value = page;
        } else if (activeTab.value === "history_out") {
            const response = await api.get('/transfers/history', {
                params: { page, q: searchQuery.value, type: 'outgoing' }
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

async function trackPackage(courier, trackingNo) {
    if (!trackingNo || !courier) return;
    
    isTracking.value = true;
    trackingData.value = null;
    showTrackingModal.value = true;
    
    try {
        const response = await api.get('/transfers/track-expedition', {
            params: { courier, awb: trackingNo }
        });
        
        if (response.data?.status === 200) {
            trackingData.value = response.data.data;
        } else {
            toast.error(response.data?.message || "Data tidak ditemukan");
        }
    } catch (e) {
        console.error(e);
        toast.error("Gagal melacak unit. Pastikan API Key sudah diset di server.");
        showTrackingModal.value = false;
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
    if (!selectedTransfer.value) return;
    
    isSubmitting.value = true;
    try {
        await api.post(`/transfers/${selectedTransfer.value.id}/expedition`, expeditionForm.value);
        toast.success("Informasi ekspedisi berhasil disimpan!");
        fetchData(currentPage.value);
        closeExpeditionModal();
    } catch (e) {
        toast.error(e.response?.data?.message || "Gagal menyimpan ekspedisi");
    } finally { isSubmitting.value = false; }
}

onMounted(() => {
    fetchInventoryAccounts();
    fetchData(1);
});
</script>

<template>
    <div class="min-h-screen bg-surface-900 pb-20">
        <!-- Dashboard Header -->
        <div class="bg-surface-900 border-b border-surface-700/50 sticky top-0 z-30 backdrop-blur-xl">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
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

                    <button @click="fetchData(1)" :disabled="isLoading"
                        class="btn btn-secondary gap-2 px-5 h-11 rounded-xl text-sm font-bold border border-surface-700 hover:bg-surface-750 shrink-0">
                        <RefreshCw :size="16" :class="{ 'animate-spin': isLoading }" />
                        <span>Refresh</span>
                    </button>
                </div>

                <!-- Responsive Tab Bar -->
                <div
                    class="mt-8 flex gap-2 overflow-x-auto pb-2 scrollbar-none no-scrollbar touch-pan-x snap-x mask-fade-right">
                    <button v-for="tab in tabs" :key="tab.id" @click="activeTab = tab.id"
                        class="flex items-center gap-3 px-6 py-3 rounded-2xl whitespace-nowrap transition-all duration-300 snap-start border shrink-0"
                        :class="activeTab === tab.id
                            ? `${tab.bg} ${tab.color} border-${tab.color.split('-')[1]}-500/30 font-black shadow-lg shadow-${tab.color.split('-')[1]}-500/10`
                            : 'bg-surface-800 text-text-secondary border-surface-700 hover:bg-surface-700 font-bold'">
                        <component :is="tab.icon" :size="18" />
                        <span>{{ tab.name }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-8">
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

            <!-- Content Grid (OTW Tabs) -->
            <div v-else-if="!activeTab.includes('history')"
                class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 animate-in duration-500">
                <div v-for="transfer in transfers" :key="transfer.id" @click="openModal(transfer)"
                    class="card group cursor-pointer hover:bg-surface-700 transition-all border-l-4 p-0 rounded-[2.5rem] shadow-xl hover:-translate-y-1"
                    :class="{
                        'border-l-blue-500 hover:shadow-blue-500/5': activeTab === 'incoming_otw',
                        'border-l-purple-500 hover:shadow-purple-500/5': activeTab === 'outgoing_otw',
                        'border-l-red-500 hover:shadow-red-500/5': activeTab === 'failed_otw'
                    }">
                    <div class="p-6 sm:p-8">
                        <div class="flex items-center justify-between mb-6">
                            <div
                                class="px-4 py-1.5 rounded-xl bg-surface-700/50 border border-surface-600/30 text-xs font-black text-white tracking-widest uppercase">
                                {{ transfer.receipt_id }}
                            </div>
                            <div class="text-right">
                                <span
                                    class="text-[10px] uppercase font-black tracking-widest opacity-40 block mb-0.5">Muatan</span>
                                <span class="text-lg font-black text-white">
                                    {{(transfer.items?.length || 0) + (transfer.non_hp_items?.reduce((acc, i) => acc +
                                        i.quantity, 0) || 0)}} Unit
                                </span>
                            </div>
                        </div>

                        <div class="flex items-center gap-4 py-4 border-t border-surface-700/30">
                            <div
                                class="w-12 h-12 rounded-2xl bg-surface-700 flex items-center justify-center text-text-secondary group-hover:bg-primary-500 group-hover:text-white transition-all">
                                <Store v-if="activeTab !== 'incoming_otw'" :size="20" />
                                <Building2 v-else :size="20" />
                            </div>
                            <div>
                                <p class="text-[10px] uppercase font-black tracking-widest opacity-40 mb-0.5">
                                    {{ activeTab === 'incoming_otw' ? 'Dari Cabang' : 'Tujuan Cabang' }}
                                </p>
                                <p class="font-bold text-white text-lg">
                                    {{ activeTab === 'incoming_otw' ? ((transfer.inventory_user?.name || transfer.inventoryUser?.name || transfer.user?.name) || 'Unknown') :
                                        (transfer.destination?.name || transfer.receiver_name || 'Unknown') }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-6 flex items-center justify-between pt-6 border-t border-surface-700/30">
                            <div class="flex items-center gap-2 text-text-secondary">
                                <Calendar :size="14" />
                                <span class="text-xs font-bold">{{ formatDate(transfer.created_at) }}</span>
                            </div>
                            
                            <div class="flex items-center gap-3">
                                <!-- Action Buttons -->
                                <button v-if="activeTab === 'outgoing_otw'" 
                                    @click.stop="openExpeditionModal(transfer)"
                                    class="p-2 bg-purple-500/10 hover:bg-purple-500 text-purple-500 hover:text-white border border-purple-500/20 rounded-xl transition-all group/btn flex items-center gap-2">
                                    <Truck :size="14" />
                                    <span class="text-[10px] font-black uppercase">Ekspedisi</span>
                                </button>

                                <button v-if="activeTab === 'incoming_otw' && transfer.expedition_tracking_no" 
                                    @click.stop="trackPackage(transfer.expedition_name, transfer.expedition_tracking_no)"
                                    class="p-2 bg-blue-500/10 hover:bg-blue-500 text-blue-500 hover:text-white border border-blue-500/20 rounded-xl transition-all group/btn flex items-center gap-2">
                                    <Search :size="14" />
                                    <span class="text-[10px] font-black uppercase tracking-wider">Lacak</span>
                                </button>

                                <ChevronRight :size="18"
                                    class="text-text-secondary opacity-20 group-hover:translate-x-1 group-hover:opacity-100 transition-all" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Grid (History Tabs) -->
            <div v-else class="space-y-8 animate-in duration-500">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div v-for="transfer in historyData.data" :key="transfer.id" @click="openModal(transfer)"
                        class="card group cursor-pointer hover:bg-surface-700 transition-all border-l-4 p-0 rounded-[2.5rem] shadow-xl overflow-hidden"
                        :class="activeTab === 'history_in' ? 'border-l-green-500' : 'border-l-amber-500'">
                        <div class="p-6 sm:p-8 flex flex-col sm:flex-row sm:items-center justify-between gap-6">
                            <div class="flex items-center gap-5">
                                <div class="w-14 h-14 rounded-2xl bg-surface-700/50 flex items-center justify-center transition-all border border-surface-600/30"
                                    :class="activeTab === 'history_in' ? 'text-green-500' : 'text-amber-500'">
                                    <FileText :size="24" />
                                </div>
                                <div>
                                    <div class="flex items-center gap-3 mb-1">
                                        <p class="font-black text-xl text-white">{{ transfer.receipt_id }}</p>
                                        <span
                                            class="text-[9px] px-2 py-0.5 rounded-lg font-black uppercase tracking-widest border"
                                            :class="transfer.status === 'confirmed' || transfer.status === 'received' ? 'bg-green-500/10 text-green-500 border-green-500/20' : 'bg-amber-500/10 text-amber-500 border-amber-500/20'">
                                            {{ transfer.status }}
                                        </span>
                                    </div>
                                    <p class="text-sm font-bold text-text-secondary">
                                        {{ activeTab === 'history_in' ? 'Dari: ' + ((transfer.inventory_user?.name || transfer.inventoryUser?.name || transfer.user?.name) || 'Unknown') :
                                            'Tujuan: ' + (transfer.destination?.name || 'Unknown') }}
                                    </p>
                                </div>
                            </div>
                            <div
                                class="flex flex-col items-end gap-1.5 sm:text-right border-t sm:border-t-0 border-surface-700/50 pt-4 sm:pt-0">
                                <p class="text-[10px] uppercase font-black tracking-widest opacity-40">Tgl Konfirmasi
                                </p>
                                <p class="text-sm font-black text-white flex items-center gap-2">
                                    <Calendar :size="14" class="opacity-50" />
                                    {{ formatDate(transfer.confirmed_at || transfer.updated_at) }}
                                </p>
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
                            <p class="value text-white">{{ selectedTransfer.destination?.name ||
                                selectedTransfer.receiver_name || '-' }}</p>
                        </div>
                        <div class="detail-card">
                            <p class="label">Waktu Kirim</p>
                            <p class="value text-white">{{ formatDate(selectedTransfer.created_at) }}</p>
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

                    <p class="text-text-secondary font-medium px-4">
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
        <!-- Real-time Tracking Modal -->
        <div v-if="showTrackingModal" class="modal-backdrop" @click.self="closeTrackingModal">
            <div class="modal-content max-w-2xl max-h-[85vh]">
                <div class="modal-header bg-surface-800">
                    <div>
                        <h2 class="text-2xl font-black text-white flex items-center gap-3">
                            <Truck class="text-blue-500" /> Status Pengiriman
                        </h2>
                        <p class="text-sm font-bold text-text-secondary mt-1" v-if="trackingData">
                            {{ trackingData.summary?.courier }} — {{ trackingData.summary?.waybill }}
                        </p>
                    </div>
                    <button @click="closeTrackingModal" class="close-btn">
                        <X :size="20" />
                    </button>
                </div>

                <div class="modal-body custom-scrollbar bg-surface-900/50">
                    <!-- Loading State -->
                    <div v-if="isTracking" class="py-20 text-center space-y-4">
                        <Loader2 :size="48" class="animate-spin text-blue-500 mx-auto" />
                        <p class="text-white font-black tracking-widest animate-pulse">MENGAMBIL DATA PELACAKAN...</p>
                    </div>

                    <!-- Tracking Content -->
                    <div v-else-if="trackingData" class="space-y-10">
                        <!-- Summary Header -->
                        <div class="grid grid-cols-2 gap-4">
                             <div class="p-4 bg-surface-800 rounded-2xl border border-white/5">
                                <p class="label">Status</p>
                                <p class="text-sm font-black" :class="trackingData.summary?.status === 'DELIVERED' ? 'text-green-500' : 'text-blue-500'">
                                    {{ trackingData.summary?.status }}
                                </p>
                             </div>
                             <div class="p-4 bg-surface-800 rounded-2xl border border-white/5">
                                <p class="label">Penerima</p>
                                <p class="text-sm font-black text-white truncate">{{ trackingData.detail?.receiver || '-' }}</p>
                             </div>
                        </div>

                        <!-- Timeline -->
                        <div class="relative pl-8 space-y-10">
                            <!-- Vertical Line -->
                            <div class="absolute left-[11px] top-2 bottom-2 w-0.5 bg-gradient-to-b from-blue-500 via-surface-700 to-transparent"></div>

                            <div v-for="(history, index) in trackingData.history" :key="index" class="relative group">
                                <!-- Dot -->
                                <div class="absolute -left-[27px] top-1.5 w-4 h-4 rounded-full border-4 border-surface-900 shadow-xl transition-all duration-500"
                                    :class="index === 0 ? 'bg-blue-500 scale-125 ring-4 ring-blue-500/20' : 'bg-surface-700'">
                                </div>

                                <div class="space-y-1">
                                    <p class="text-[10px] font-black text-blue-500/60 uppercase tracking-widest">{{ history.date }}</p>
                                    <p class="text-sm font-bold text-white leading-relaxed">{{ history.desc }}</p>
                                    <p v-if="history.location" class="text-[11px] font-medium text-text-secondary italic">@ {{ history.location }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Error State -->
                    <div v-else class="py-20 text-center space-y-4">
                        <AlertTriangle :size="48" class="text-red-500 mx-auto" />
                        <p class="text-white font-black tracking-widest">DATA TIDAK TERSEDIA</p>
                        <p class="text-text-secondary text-sm">Gagal menghubungkan ke provider tracking.</p>
                    </div>
                </div>

                <div class="modal-footer bg-surface-800 p-6">
                    <button @click="closeTrackingModal" class="btn btn-secondary w-full rounded-xl font-black py-4">
                        TUTUP PELACAKAN
                    </button>
                </div>
            </div>
        </div>
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
    @apply bg-surface-900 rounded-[2.5rem] w-full flex flex-col border border-surface-700 shadow-2xl overflow-hidden;
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
