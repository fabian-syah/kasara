<script setup>
import { ref, onMounted, computed } from 'vue'
import { 
    Box, 
    ArrowLeft, 
    CheckCircle2, 
    Clock, 
    ChevronRight,
    Smartphone,
    RefreshCw,
    Search,
    Truck,
    AlertTriangle,
    X,
    User,
    Building2,
    Calendar,
    Package
} from 'lucide-vue-next'
import api from '../../api/axios'
import { useToast } from '../../composables/useToast'
import { useAuthStore } from '../../store/auth'
import PasswordModal from '../../components/modals/PasswordModal.vue'

const authStore = useAuthStore()
const { success, error: toastError } = useToast()

const transfers = ref([])
const loading = ref(true)
const searchQuery = ref('')
const processingId = ref(null)

const showPasswordModal = ref(false)
const passwordModalMode = ref('password')
const selectedTransfer = ref(null)
const pendingPasswordCallback = ref(null)

// Inventory Accounts
const inventoryAccounts = ref([])
const selectedInventoryAccount = ref("")

// Fetch Inventory Accounts
async function fetchInventoryAccounts() {
    try {
        const response = await api.get('/inventory/my-accounts')
        inventoryAccounts.value = response.data || []
        if (inventoryAccounts.value.length > 0) {
            selectedInventoryAccount.value = inventoryAccounts.value[0].id
        }
    } catch (e) {
        console.error("Failed to fetch inventory accounts", e)
    }
}

const fetchFailedTransfers = async () => {
    loading.value = true
    try {
        const response = await api.get('/transfers/failed')
        transfers.value = response.data.data || response.data || []
    } catch (err) {
        console.error('Failed to fetch failed transfers:', err)
        toastError('Gagal mengambil data transfer.')
    } finally {
        loading.value = false
    }
}

function handleVerifyPassword(callback) {
    if (authStore.hasRole('inventory')) {
        if (authStore.user?.pin_enabled) {
            passwordModalMode.value = 'pin';
            pendingPasswordCallback.value = callback;
            showPasswordModal.value = true;
        } else {
            callback('skipped');
        }
    } else {
        passwordModalMode.value = 'password';
        pendingPasswordCallback.value = callback
        showPasswordModal.value = true
    }
}

function onPasswordVerified(password) {
    showPasswordModal.value = false
    if (pendingPasswordCallback.value) {
        pendingPasswordCallback.value(password)
        pendingPasswordCallback.value = null
    }
}

const confirmReturn = (transfer) => {
    selectedTransfer.value = transfer
    handleVerifyPassword((password) => handlePinConfirm(password))
}

const handlePinConfirm = async (password) => {
    if (!selectedTransfer.value) return
    
    processingId.value = selectedTransfer.value.id
    try {
        await api.post(`/transfers/${selectedTransfer.value.id}/confirm-return`, {
            transaction_pin: password,
            inventory_user_id: selectedInventoryAccount.value
        })
        success('Barang telah diterima kembali ke stok.')
        fetchFailedTransfers()
        selectedTransfer.value = null
    } catch (err) {
        console.error('Failed to confirm return:', err)
        toastError(err.response?.data?.message || 'Gagal memproses penerimaan barang.')
    } finally {
        processingId.value = null
    }
}

const formatDate = (dateString) => {
    if (!dateString) return '-'
    const date = new Date(dateString)
    return date.toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
}

const getBrandName = (item) => {
    const product = item?.product;
    if (!product) return '';
    const brandObj = product.brand_relation || product.brandRelation || product.brand;
    if (brandObj && typeof brandObj === 'object') {
        return brandObj.name || '';
    }
    return brandObj || '';
}

const formatCapacity = (ram, storage) => {
    if (!ram && !storage) return ''
    if (ram && storage) {
        const r = /^\d+$/.test(ram) ? ram : ram.replace(/GB/gi, '')
        const s = /^\d+$/.test(storage) ? storage : storage.replace(/GB/gi, '')
        return `${r}/${s}GB`
    }
    const val = storage || ram
    if (/^\d+$/.test(val)) return val + 'GB'
    return val
}

onMounted(() => {
    fetchFailedTransfers()
    fetchInventoryAccounts()
})
</script>

<template>
    <div class="space-y-8 animate-in fade-in max-w-7xl mx-auto pb-24 px-4 sm:px-6 lg:px-8">
        <!-- Header Section -->
        <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-6 pb-2 border-b border-surface-700/50">
            <div class="flex items-start gap-5">
                <div class="w-16 h-16 lg:w-20 lg:h-20 bg-gradient-to-br from-red-500/20 to-red-600/10 rounded-3xl flex items-center justify-center border border-red-500/20 shadow-xl shadow-red-500/5 shrink-0">
                    <Truck :size="32" class="text-red-500" />
                </div>
                <div class="pt-1">
                    <h1 class="text-3xl lg:text-5xl font-black text-white tracking-tight leading-tight">
                        Gagal <span class="text-red-500">Kirim</span> (OTW Balik)
                    </h1>
                    <p class="text-text-secondary text-sm lg:text-base mt-2 max-w-xl">
                        Barang yang ditolak oleh cabang penerima dan sedang dalam perjalanan kembali ke lokasi asal.
                    </p>
                </div>
            </div>

            <button @click="fetchFailedTransfers" :disabled="loading"
                class="btn btn-secondary gap-3 rounded-2xl h-[54px] px-6 text-base font-bold border border-surface-600 hover:border-red-500/50 hover:bg-surface-750 transition-all shadow-lg active:scale-95 shrink-0 self-start lg:self-end">
                <RefreshCw :size="20" :class="{ 'animate-spin': loading }" />
                <span>{{ loading ? 'Memuat...' : 'Refresh Data' }}</span>
            </button>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="text-center py-20 text-text-secondary">
            <Loader2 :size="40" class="animate-spin mx-auto mb-4" />
            <p>Memuat data transfer gagal...</p>
        </div>

        <!-- Empty State -->
        <div v-else-if="transfers.length === 0" class="text-center py-20 bg-surface-800 rounded-3xl border border-surface-700">
            <div class="w-24 h-24 mx-auto bg-green-500/10 rounded-full flex items-center justify-center mb-6">
                <CheckCircle2 :size="48" class="text-green-500" />
            </div>
            <h2 class="text-2xl font-black text-text-primary mb-2">Semua Aman!</h2>
            <p class="text-text-secondary max-w-xs mx-auto">Tidak ada pengiriman yang ditolak atau sedang retur saat ini.</p>
        </div>

        <!-- Transfer Grid -->
        <div v-else class="space-y-6">
            <div class="flex items-center gap-3 px-2">
                <AlertTriangle :size="18" class="text-red-500" />
                <p class="text-text-secondary font-bold text-sm uppercase tracking-widest">
                    {{ transfers.length }} Pengiriman Ditolak / Retur
                </p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div v-for="transfer in transfers" :key="transfer.id"
                    class="card bg-surface-800 rounded-[2rem] border border-surface-700/50 shadow-xl overflow-hidden hover:border-red-500/30 transition-all">
                    
                    <!-- Card Header -->
                    <div class="p-6 sm:p-8 bg-surface-900/40 border-b border-surface-700/50">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-center gap-5">
                                <div class="w-14 h-14 rounded-2xl flex items-center justify-center bg-red-500/10 text-red-500 border border-red-500/20 shadow-inner">
                                    <Truck :size="24" />
                                </div>
                                <div>
                                    <div class="flex items-center gap-3 mb-1">
                                        <p class="font-black text-xl text-white tracking-tight">{{ transfer.receipt_id }}</p>
                                        <span class="px-2 py-0.5 rounded bg-red-500/10 text-red-500 text-[10px] font-black uppercase tracking-widest border border-red-500/20">RETUR</span>
                                    </div>
                                    <p class="text-sm text-text-secondary flex items-center gap-2">
                                        <Store :size="14" class="opacity-50" />
                                        Tujuan: <span class="text-white font-bold">{{ transfer.destination?.name || 'Gudang Utama' }}</span>
                                    </p>
                                </div>
                            </div>
                            <div class="text-right hidden sm:block">
                                <p class="text-[10px] text-text-secondary uppercase font-black tracking-widest opacity-40">Waktu Kirim</p>
                                <p class="text-sm font-bold text-text-primary">{{ formatDate(transfer.created_at) }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Items Content -->
                    <div class="p-6 sm:p-8 space-y-8 bg-surface-800">
                        <!-- HP Items Returning -->
                        <div v-if="transfer.items && transfer.items.filter(i => i.pivot?.status === 'rejected' || i.status === 'returning').length > 0">
                            <h4 class="text-xs font-black text-text-secondary uppercase tracking-[0.2em] mb-4 flex items-center gap-2">
                                <Smartphone :size="14" class="text-red-500" /> Barang HP Dikembalikan
                            </h4>
                            <div class="space-y-3">
                                <div v-for="item in transfer.items.filter(i => i.pivot?.status === 'rejected' || i.status === 'returning')"
                                    :key="item.id"
                                    class="p-4 rounded-2xl border border-surface-700 bg-surface-900/30">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="font-bold text-sm text-white">
                                                <span v-if="getBrandName(item)" class="text-red-400 mr-1">[{{ getBrandName(item) }}]</span>
                                                {{ item.product?.name }}
                                                <span class="text-xs text-text-secondary font-medium ml-1">
                                                    {{ formatCapacity(item.ram, item.storage) }}
                                                </span>
                                            </p>
                                            <p class="text-[10px] font-mono text-text-secondary tracking-widest mt-1 uppercase">{{ item.imei }}</p>
                                        </div>
                                        <span class="px-2 py-0.5 rounded bg-surface-700 text-text-secondary text-[10px] font-black uppercase border border-surface-600">
                                            {{ item.condition }}
                                        </span>
                                    </div>
                                    <div v-if="item.pivot?.notes" class="mt-3 p-3 bg-red-500/5 border border-red-500/10 rounded-xl">
                                        <p class="text-[9px] font-black text-red-400 uppercase tracking-widest mb-1 opacity-60">Alasan Tolak</p>
                                        <p class="text-xs text-white/80 italic font-medium">"{{ item.pivot.notes }}"</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Non-HP Items Returning -->
                        <div v-if="transfer.non_hp_items && transfer.non_hp_items.filter(i => i.returned_quantity > 0).length > 0">
                            <h4 class="text-xs font-black text-text-secondary uppercase tracking-[0.2em] mb-4 flex items-center gap-2">
                                <Package :size="14" class="text-orange-500" /> Aksesoris Dikembalikan
                            </h4>
                            <div class="space-y-3">
                                <div v-for="item in transfer.non_hp_items.filter(i => i.returned_quantity > 0)"
                                    :key="item.id"
                                    class="p-4 rounded-2xl border border-surface-700 bg-surface-900/30">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="font-bold text-sm text-white">
                                                <span v-if="getBrandName(item)" class="text-orange-400 mr-1">[{{ getBrandName(item) }}]</span>
                                                {{ item.product?.name || item.product_name }}
                                            </p>
                                            <p class="text-xs text-text-secondary mt-1 font-bold">
                                                Ditolak: <span class="text-red-500">{{ item.returned_quantity }}</span> / {{ item.quantity }} Unit
                                            </p>
                                        </div>
                                        <div class="w-10 h-10 bg-orange-500/10 rounded-xl flex items-center justify-center text-orange-500">
                                            <Package :size="18" />
                                        </div>
                                    </div>
                                    <div v-if="item.notes" class="mt-3 p-3 bg-red-500/5 border border-red-500/10 rounded-xl">
                                        <p class="text-[9px] font-black text-red-500 uppercase tracking-widest mb-1 opacity-60">Alasan Tolak</p>
                                        <p class="text-xs text-white/80 italic font-medium">"{{ item.notes }}"</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Receipt Footer & Actions -->
                    <div class="p-6 sm:p-8 bg-surface-900/40 border-t border-surface-700/50">
                        <div class="bg-blue-500/5 p-6 rounded-2xl border border-blue-500/10 mb-6 group hover:bg-blue-500/10 transition-colors">
                            <div class="flex items-center gap-3 mb-4">
                                <User :size="16" class="text-blue-500" />
                                <p class="text-[10px] uppercase font-black text-blue-500 tracking-widest">Verifikator Penerima</p>
                            </div>
                            <select v-model="selectedInventoryAccount"
                                class="w-full bg-surface-900 border-2 border-surface-700 rounded-xl px-4 py-3 text-sm font-bold text-white focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 appearance-none shadow-lg">
                                <option value="" disabled>Pilih Akun CS...</option>
                                <option v-for="acc in inventoryAccounts" :key="acc.id" :value="acc.id">
                                    {{ acc.full_name }} ({{ acc.code_id }})
                                </option>
                            </select>
                        </div>
                        
                        <button @click="confirmReturn(transfer)"
                            :disabled="processingId === transfer.id || !selectedInventoryAccount"
                            class="w-full h-14 bg-red-600 hover:bg-red-500 text-white font-black text-sm uppercase tracking-widest rounded-2xl shadow-xl shadow-red-500/10 transition-all flex items-center justify-center gap-3 active:scale-[0.98] disabled:opacity-50">
                            <RefreshCw v-if="processingId === transfer.id" :size="20" class="animate-spin" />
                            <CheckCircle2 v-else :size="20" />
                            <span>TERIMA KEMBALI KE STOK</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Password Verification Modal -->
    <PasswordModal 
        v-if="showPasswordModal"
        :show="showPasswordModal"
        :mode="passwordModalMode"
        @close="showPasswordModal = false"
        @success="onPasswordVerified"
    />
</template>

<style scoped>
@reference "../../style.css";

.btn {
    @apply transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center;
}

.btn-secondary {
    @apply bg-surface-700 hover:bg-surface-600 text-text-primary border border-surface-600;
}

.card {
    @apply bg-surface-800 rounded-xl border border-surface-700 transition-all;
}
</style>
