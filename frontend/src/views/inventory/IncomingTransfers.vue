<script setup>
import { ref, onMounted, computed } from "vue";
import api from "../../api/axios";
import { useToast } from "../../composables/useToast";
import { useRouter } from "vue-router";
import PinModal from "../../components/modals/PinModal.vue";
import {
    Package,
    Loader2,
    ArrowDownRight,
    Calendar,
    User,
    Smartphone,
    CheckCircle2,
    Clock,
    Building2,
    RefreshCw,
    X,
    AlertTriangle,
    Warehouse,
    Store,
    ShoppingCart,
    Truck
} from "lucide-vue-next";

const toast = useToast();
const router = useRouter();

// State
const isLoading = ref(true);
const isSubmitting = ref(false);
const transfers = ref([]);

// Modal State
const showModal = ref(false);
const selectedTransfer = ref(null);
const form = ref({
    accepted_items: [], // HP IDs
    non_hp_quantities: {} // { id: qty }
});

const showPinModal = ref(false);
const pinCallback = ref(null);

function handleVerifyPin(callback) {
    pinCallback.value = callback;
    showPinModal.value = true;
}

function onPinVerified(pin) {
    showPinModal.value = false;
    if (pinCallback.value) {
        pinCallback.value(pin);
        pinCallback.value = null;
    }
}

// Helper for icons based on destination type (though incoming is usually for US)
const destinationIcon = {
    branch: Store,
    warehouse: Warehouse,
    online_shop: ShoppingCart,
    distributor: Truck
};

// Inventory Accounts
const inventoryAccounts = ref([]);
const selectedInventoryAccount = ref("");

// Fetch Inventory Accounts
async function fetchInventoryAccounts() {
    try {
        const response = await api.get('/inventory/my-accounts');
        inventoryAccounts.value = response.data || [];
        // Auto-select first if available
        if (inventoryAccounts.value.length > 0) {
            selectedInventoryAccount.value = inventoryAccounts.value[0].id;
        }
    } catch (e) {
        console.error("Failed to fetch inventory accounts", e);
    }
}

// Fetch pending transfers
async function fetchPending() {
    isLoading.value = true;
    try {
        const response = await api.get('/transfers/pending');
        transfers.value = response.data.data || response.data || [];
    } catch (e) {
        // toast.error(e.response?.data?.message || "Gagal memuat data transfer");
        console.error(e);
    } finally {
        isLoading.value = false;
    }
}

// Open Confirm Modal
function openConfirmModal(transfer) {
    selectedTransfer.value = transfer;

    // Reset selection if not set (or keep previous selection for convenience, but better reset/default)
    if (inventoryAccounts.value.length > 0 && !selectedInventoryAccount.value) {
        selectedInventoryAccount.value = inventoryAccounts.value[0].id;
    }
    // Reset selection if not set (or keep previous selection for convenience, but better reset/default)
    if (inventoryAccounts.value.length > 0 && !selectedInventoryAccount.value) {
        selectedInventoryAccount.value = inventoryAccounts.value[0].id;
    }

    // Initialize form
    const accepted = [];
    if (transfer.items) {
        transfer.items.forEach(item => accepted.push(item.id));
    }

    const quantities = {};
    if (transfer.non_hp_items) {
        transfer.non_hp_items.forEach(item => {
            quantities[item.id] = item.quantity; // Default receive all
        });
    }

    form.value = {
        accepted_items: accepted,
        non_hp_quantities: quantities
    };

    showModal.value = true;
}

// Close Modal
function closeModal() {
    showModal.value = false;
    selectedTransfer.value = null;
}

// Submit Confirmation
async function submitConfirmation(pin = null) {
    if (!selectedTransfer.value) return;

    // Check PIN if needed
    const selectedAccount = inventoryAccounts.value.find(acc => acc.id === selectedInventoryAccount.value);
    if (!pin && selectedAccount?.pin_enabled) {
        handleVerifyPin((verifiedPin) => submitConfirmation(verifiedPin));
        return;
    }

    isSubmitting.value = true;
    try {
        const payload = {
            items: form.value.accepted_items,
            non_hp_items: form.value.non_hp_quantities,
            inventory_user_id: selectedInventoryAccount.value,
            transaction_pin: pin
        };

        const response = await api.post(`/transfers/${selectedTransfer.value.id}/confirm`, payload);
        toast.success(response.data.message || "Transfer berhasil dikonfirmasi!");

        // Remove from list
        transfers.value = transfers.value.filter(t => t.id !== selectedTransfer.value.id);
        closeModal();
    } catch (e) {
        toast.error(e.response?.data?.message || "Gagal mengkonfirmasi transfer");
    } finally {
        isSubmitting.value = false;
    }
}

// Toggle HP Item
function toggleHpItem(id) {
    const idx = form.value.accepted_items.indexOf(id);
    if (idx === -1) {
        form.value.accepted_items.push(id);
    } else {
        form.value.accepted_items.splice(idx, 1);
    }
}

// Format date
function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

onMounted(() => {
    fetchPending();
    fetchInventoryAccounts();
});
</script>

<template>
    <div class="space-y-6 animate-in fade-in max-w-6xl mx-auto pb-24">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-text-primary flex items-center gap-3">
                    <div class="w-12 h-12 bg-blue-500/20 rounded-2xl flex items-center justify-center">
                        <ArrowDownRight :size="24" class="text-blue-500" />
                    </div>
                    Barang Masuk Transfer
                </h1>
                <p class="text-text-secondary mt-1">
                    Konfirmasi penerimaan barang dari cabang/gudang lain
                </p>
            </div>
            <button @click="fetchPending" :disabled="isLoading" class="btn btn-secondary gap-2 rounded-xl h-10 px-4">
                <RefreshCw :size="16" :class="{ 'animate-spin': isLoading }" />
                Refresh
            </button>
        </div>

        <!-- Loading State -->
        <div v-if="isLoading" class="text-center py-20 text-text-secondary">
            <Loader2 :size="40" class="animate-spin mx-auto mb-4" />
            <p>Memuat transfer masuk...</p>
        </div>

        <!-- Empty State -->
        <div v-else-if="transfers.length === 0" class="text-center py-20">
            <div class="w-24 h-24 mx-auto bg-surface-700/50 rounded-3xl flex items-center justify-center mb-6">
                <CheckCircle2 :size="48" class="text-green-500" />
            </div>
            <h2 class="text-xl font-bold text-text-primary mb-2">Tidak Ada Transfer Masuk</h2>
            <p class="text-text-secondary">Semua transfer sudah dikonfirmasi 🎉</p>
        </div>

        <!-- Transfer List -->
        <div v-else class="space-y-4">
            <p class="text-text-secondary text-sm">
                <Clock :size="14" class="inline mr-1" />
                {{ transfers.length }} transfer menunggu konfirmasi
            </p>

            <div v-for="transfer in transfers" :key="transfer.id"
                class="card border-l-4 border-l-blue-500 hover:bg-surface-700/30 transition-all cursor-pointer group"
                @click="openConfirmModal(transfer)">
                <!-- Header -->
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-blue-500/20 text-blue-500">
                            <Building2 :size="24" />
                        </div>
                        <div>
                            <p class="font-bold text-text-primary text-lg group-hover:text-blue-400 transition-colors">
                                {{ transfer.receipt_id }}</p>
                            <p class="text-sm text-text-secondary flex items-center gap-1">
                                <User :size="12" />
                                Dari: <span class="text-text-primary font-medium">{{ transfer.user?.name || 'Unknown'
                                    }}</span>
                            </p>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="flex items-center gap-2 text-text-secondary text-sm justify-end">
                            <Calendar :size="14" />
                            {{ formatDate(transfer.created_at) }}
                        </div>
                        <span
                            class="inline-block mt-2 px-3 py-1 rounded-full bg-amber-500/20 text-amber-500 text-xs font-bold">
                            Menunggu Konfirmasi
                        </span>
                    </div>
                </div>

                <!-- Items Preview -->
                <div class="pl-[64px]">
                    <div class="flex gap-4">
                        <div v-if="transfer.items && transfer.items.length > 0">
                            <p class="text-xs uppercase font-bold text-text-secondary mb-1">HP ({{ transfer.items.length
                                }})</p>
                            <div class="flex flex-wrap gap-2">
                                <span v-for="item in transfer.items.slice(0, 3)" :key="item.id"
                                    class="text-xs bg-surface-700 px-2 py-1 rounded text-text-secondary font-mono">
                                    {{ item.imei.slice(-4) }}
                                </span>
                                <span v-if="transfer.items.length > 3" class="text-xs text-text-secondary self-center">
                                    +{{ transfer.items.length - 3 }} more
                                </span>
                            </div>
                        </div>
                        <div v-if="transfer.non_hp_items && transfer.non_hp_items.length > 0">
                            <p class="text-xs uppercase font-bold text-text-secondary mb-1">Non-HP ({{
                                transfer.non_hp_items.length }})</p>
                            <div class="flex flex-wrap gap-2">
                                <span v-for="item in transfer.non_hp_items.slice(0, 3)" :key="item.id"
                                    class="text-xs bg-surface-700 px-2 py-1 rounded text-text-secondary">
                                    {{ item.product?.name }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirmation Modal -->
        <div v-if="showModal && selectedTransfer"
            class="fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4 backdrop-blur-sm">
            <div
                class="bg-surface-800 rounded-2xl w-full max-w-2xl max-h-[90vh] flex flex-col border border-surface-700 shadow-2xl animate-in zoom-in duration-200">
                <!-- Modal Header -->
                <div
                    class="p-6 border-b border-surface-700 flex justify-between items-center bg-surface-800 rounded-t-2xl z-10">
                    <div>
                        <h2 class="text-xl font-bold text-white">Konfirmasi Terima Barang</h2>
                        <p class="text-text-secondary text-sm">{{ selectedTransfer.receipt_id }}</p>
                    </div>
                    <button @click="closeModal" class="text-text-secondary hover:text-white transition-colors">
                        <X :size="24" />
                    </button>
                </div>


                <!-- Modal Body -->
                <div class="p-6 overflow-y-auto flex-1 space-y-8">

                    <!-- Account Selection -->
                    <div v-if="inventoryAccounts.length > 0"
                        class="bg-surface-700/30 p-4 rounded-xl border border-surface-600">
                        <label class="block text-sm font-medium text-text-primary mb-2">Konfirmasi Menggunakan
                            Akun:</label>
                        <select v-model="selectedInventoryAccount"
                            class="w-full bg-surface-800 border border-surface-600 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            <option value="" disabled>Pilih Akun Inventory</option>
                            <option v-for="acc in inventoryAccounts" :key="acc.id" :value="acc.id">
                                {{ acc.full_name }} ({{ acc.code_id }})
                            </option>
                        </select>
                        <p class="text-xs text-text-secondary mt-2">
                            Stok akan tercatat diterima oleh akun ini.
                        </p>
                    </div>

                    <!-- HP Items -->
                    <div v-if="selectedTransfer.items && selectedTransfer.items.length > 0">
                        <h3 class="font-bold text-text-primary mb-3 flex items-center gap-2">
                            <Smartphone :size="18" class="text-blue-500" /> Barang HP
                        </h3>
                        <div class="space-y-2">
                            <div v-for="item in selectedTransfer.items" :key="item.id"
                                class="flex items-center justify-between p-3 rounded-xl border transition-all cursor-pointer"
                                :class="form.accepted_items.includes(item.id)
                                    ? 'bg-blue-500/10 border-blue-500/50'
                                    : 'bg-surface-700/50 border-transparent opacity-60 hover:opacity-100'"
                                @click="toggleHpItem(item.id)">
                                <div class="flex items-center gap-3">
                                    <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center"
                                        :class="form.accepted_items.includes(item.id) ? 'border-blue-500 bg-blue-500' : 'border-text-secondary'">
                                        <CheckCircle2 v-if="form.accepted_items.includes(item.id)" :size="14"
                                            class="text-white" />
                                    </div>
                                    <div>
                                        <p class="font-bold text-sm text-text-primary">{{ item.product?.name }}</p>
                                        <p class="text-xs font-mono text-text-secondary">{{ item.imei }}</p>
                                    </div>
                                </div>
                                <span class="text-xs font-bold"
                                    :class="form.accepted_items.includes(item.id) ? 'text-blue-400' : 'text-red-400'">
                                    {{ form.accepted_items.includes(item.id) ? 'DITERIMA' : 'DITOLAK' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Non-HP Items -->
                    <div v-if="selectedTransfer.non_hp_items && selectedTransfer.non_hp_items.length > 0">
                        <h3 class="font-bold text-text-primary mb-3 flex items-center gap-2">
                            <Package :size="18" class="text-orange-500" /> Barang Non-HP
                        </h3>
                        <div class="space-y-3">
                            <div v-for="item in selectedTransfer.non_hp_items" :key="item.id"
                                class="bg-surface-700/50 p-4 rounded-xl border border-surface-600">
                                <div class="flex justify-between mb-2">
                                    <p class="font-bold text-sm text-text-primary">{{ item.product?.name }}</p>
                                    <span class="text-xs text-text-secondary">Dikirim: {{ item.quantity }}</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <label class="text-xs text-text-secondary">Diterima:</label>
                                    <input type="number" v-model="form.non_hp_quantities[item.id]"
                                        class="input w-24 text-center py-1 px-2" min="0" :max="item.quantity" />
                                    <span class="text-xs text-text-secondary">Unit</span>
                                </div>
                                <p v-if="form.non_hp_quantities[item.id] < item.quantity"
                                    class="text-xs text-red-400 mt-2 flex items-center gap-1">
                                    <AlertTriangle :size="12" />
                                    {{ item.quantity - form.non_hp_quantities[item.id] }} unit akan dikembalikan
                                </p>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Modal Footer -->
                <div class="p-6 border-t border-surface-700 bg-surface-800 rounded-b-2xl">
                    <button @click="submitConfirmation" :disabled="isSubmitting"
                        class="btn btn-primary w-full h-12 text-lg font-bold rounded-xl shadow-lg shadow-blue-500/20">
                        <Loader2 v-if="isSubmitting" :size="20" class="animate-spin mr-2" />
                        {{ isSubmitting ? 'Memproses...' : 'Konfirmasi Penerimaan' }}
                    </button>
                    <button @click="closeModal" :disabled="isSubmitting"
                        class="w-full mt-3 text-text-secondary hover:text-text-primary text-sm">
                        Batal
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- PIN Modal -->
    <PinModal :show="showPinModal" @close="showPinModal = false" @verified="onPinVerified" />
</template>

<style scoped>
@reference "../../style.css";

.input {
    @apply bg-surface-800 border border-surface-600 rounded-lg text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all;
}

.btn {
    @apply transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center;
}

.btn-secondary {
    @apply bg-surface-700 hover:bg-surface-600 text-text-primary;
}

.btn-primary {
    @apply bg-blue-600 hover:bg-blue-500 text-white;
}

.card {
    @apply bg-surface-800 rounded-2xl p-6 border border-surface-700;
}
</style>
