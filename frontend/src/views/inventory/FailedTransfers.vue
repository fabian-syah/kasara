<script setup>
import { ref, onMounted } from 'vue'
import { 
    Box, 
    ArrowLeft, 
    CheckCircle2, 
    Clock, 
    ChevronRight,
    Smartphone,
    RefreshCw,
    Search,
    Truck
} from 'lucide-vue-next'
import api from '../../api/axios'
import { useToast } from '../../composables/useToast'
import { useAuthStore } from '../../store/auth'
import PinModal from '../../components/modals/PinModal.vue'

const authStore = useAuthStore()
const { success, error: toastError } = useToast()

const transfers = ref([])
const loading = ref(true)
const searchQuery = ref('')
const processingId = ref(null)

const showPinModal = ref(false)
const selectedTransfer = ref(null)

// Inventory Accounts
const inventoryAccounts = ref([])
const selectedInventoryAccount = ref("")

// Fetch Inventory Accounts
async function fetchInventoryAccounts() {
    try {
        const response = await api.get('/inventory/my-accounts')
        inventoryAccounts.value = response.data || []
        // Auto-select first if available
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
        transfers.value = response.data.data
    } catch (err) {
        console.error('Failed to fetch failed transfers:', err)
        toastError('Gagal mengambil data transfer.')
    } finally {
        loading.value = false
    }
}

const confirmReturn = (transfer) => {
    selectedTransfer.value = transfer
    showPinModal.value = true
}

const handlePinConfirm = async (pinStr) => {
    if (!selectedTransfer.value) return
    
    processingId.value = selectedTransfer.value.id
    try {
        await api.post(`/transfers/${selectedTransfer.value.id}/confirm-return`, {
            transaction_pin: pinStr,
            inventory_user_id: selectedInventoryAccount.value
        })
        success('Barang telah diterima kembali ke stok.')
        fetchFailedTransfers()
        showPinModal.value = false
        selectedTransfer.value = null
    } catch (err) {
        console.error('Failed to confirm return:', err)
        toastError(err.response?.data?.message || 'Gagal memproses penerimaan barang.')
    } finally {
        processingId.value = null
    }
}

const formatCapacity = (ram, storage) => {
    if (!ram && !storage) return 'N/A'
    if (ram && storage) {
        if (ram === storage) return ram
        return `${ram}/${storage}`
    }
    return ram || storage
}

const formatCondition = (condition) => {
    if (!condition) return ''
    if (condition.toLowerCase() === 'second') return 'Second'
    if (condition.toLowerCase() === 'new') return 'New'
    return condition
}

const getBrandName = (item) => {
    const product = item?.product;
    if (!product) return 'Unknown';
    const brandObj = product.brand_relation || product.brandRelation || product.brand;
    if (brandObj && typeof brandObj === 'object') {
        return brandObj.name || 'Unknown';
    }
    return brandObj || 'Unknown';
}

onMounted(() => {
    fetchFailedTransfers()
    fetchInventoryAccounts()
})
</script>

<template>
    <div class="p-6 space-y-6 max-w-7xl mx-auto min-h-screen animate-in">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-text-primary tracking-tight">Gagal Kirim (OTW Kembali)</h1>
                <p class="text-text-secondary mt-1">Barang yang ditolak oleh cabang penerima dan sedang dalam perjalanan kembali.</p>
            </div>
            
            <button 
                @click="fetchFailedTransfers"
                :disabled="loading"
                class="btn btn-secondary w-full sm:w-auto flex items-center justify-center gap-2"
            >
                <RefreshCw :class="{'animate-spin': loading}" class="w-4 h-4 text-primary-500" />
                <span>Refresh</span>
            </button>
        </div>

        <!-- Filter/Search -->
        <div class="card p-4 flex flex-col sm:flex-row gap-4">
            <div class="relative flex-1">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-text-secondary" />
                <input 
                    v-model="searchQuery"
                    type="text" 
                    placeholder="Cari ID Nota atau Cabang..." 
                    class="input pl-10 w-full"
                />
            </div>
        </div>

        <!-- Content -->
        <div v-if="loading" class="card flex flex-col items-center justify-center py-20">
            <RefreshCw class="w-10 h-10 text-primary-500 animate-spin mb-4" />
            <p class="text-text-secondary font-medium">Memuat data transfer...</p>
        </div>

        <div v-else-if="transfers.length === 0" class="card flex flex-col items-center justify-center py-20">
            <div class="w-20 h-20 bg-surface-700 rounded-full flex items-center justify-center mb-6">
                <CheckCircle2 class="w-10 h-10 text-text-secondary opacity-20" />
            </div>
            <h3 class="text-xl font-semibold text-text-primary mb-2">Tidak Ada Barang Gagal</h3>
            <p class="text-text-secondary max-w-sm text-center">Semua transfer Anda telah diterima dengan baik atau belum ada yang ditolak.</p>
        </div>

        <div v-else class="grid grid-cols-1 gap-6">
            <div 
                v-for="transfer in transfers" 
                :key="transfer.id"
                class="card overflow-hidden hover:border-primary-500/30 transition-all group p-0"
            >
                <!-- Card Header -->
                <div class="bg-surface-800/50 px-4 sm:px-6 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-surface-700">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-danger/10 rounded-2xl flex items-center justify-center text-danger border border-danger/20 italic font-bold text-sm shrink-0">
                            TRF
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-text-primary">{{ transfer.receipt_id }}</span>
                                <span class="badge badge-danger">Ditolak</span>
                            </div>
                            <div class="flex items-center gap-2 mt-0.5 text-xs text-text-secondary">
                                <Clock class="w-3 h-3" />
                                <span>{{ new Date(transfer.created_at).toLocaleString('id-ID') }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between sm:justify-end gap-3">
                        <div class="text-left sm:text-right">
                            <p class="text-[10px] text-text-secondary uppercase font-bold tracking-tighter">Tujuan</p>
                            <p class="font-semibold text-text-primary">{{ transfer.destination?.name || 'Unknown' }}</p>
                        </div>
                        <ChevronRight class="w-5 h-5 text-text-secondary opacity-30" />
                    </div>
                </div>

                <!-- Item List -->
                <div class="p-6 space-y-6">
                    <!-- HP Items -->
                    <div v-if="transfer.items && transfer.items.filter(i => i.pivot?.status === 'rejected' || i.status === 'returning').length > 0">
                        <h4 class="text-xs font-bold text-text-secondary uppercase tracking-widest mb-4 flex items-center gap-2">
                            <Smartphone :size="14" /> Unit HP (OTW Kembali)
                        </h4>
                        <div class="space-y-3">
                            <div 
                                v-for="item in transfer.items.filter(i => i.pivot?.status === 'rejected' || i.status === 'returning')"
                                :key="item.id"
                                class="p-4 bg-surface-900/50 rounded-2xl border border-dashed border-surface-700 group-hover:border-danger/30 transition-colors"
                            >
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-4">
                                        <div class="p-2 bg-surface-800 rounded-xl border border-surface-700 shadow-sm">
                                            <Smartphone class="w-5 h-5 text-primary-500" />
                                        </div>
                                        <div>
                                            <h5 class="font-bold text-text-primary flex items-center flex-wrap gap-1">
                                                <span v-if="getBrandName(item)" class="text-blue-400 mr-1">[{{ getBrandName(item) }}]</span>
                                                <span>{{ item.product?.name || item.product?.model_name }}</span>
                                            </h5>
                                            <div class="flex flex-wrap items-center gap-2 mt-1">
                                                <span class="text-xs font-medium text-text-secondary bg-surface-700 px-2 py-0.5 rounded-md">
                                                    {{ formatCapacity(item.ram, item.storage) }}
                                                </span>
                                                <span class="text-xs font-medium text-text-secondary bg-surface-700 px-2 py-0.5 rounded-md">
                                                    {{ formatCondition(item.condition) }}
                                                </span>
                                                <span class="text-xs font-bold text-text-secondary font-mono tracking-tighter opacity-70 ml-1">
                                                    {{ item.imei }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <Truck class="w-5 h-5 text-danger opacity-20 hidden sm:block" />
                                </div>
                                <!-- Rejection Note -->
                                <div v-if="item.pivot?.notes" class="mt-3 bg-danger/5 border border-danger/10 p-3 rounded-xl">
                                    <p class="text-[10px] font-bold text-danger uppercase tracking-wider mb-1">Alasan Penolakan:</p>
                                    <p class="text-xs text-text-primary italic">"{{ item.pivot.notes }}"</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Non-HP Items -->
                    <div v-if="transfer.non_hp_items && transfer.non_hp_items.filter(i => i.returned_quantity > 0).length > 0">
                        <h4 class="text-xs font-bold text-text-secondary uppercase tracking-widest mb-4 flex items-center gap-2">
                            <Box :size="14" /> Barang Non-HP (OTW Kembali)
                        </h4>
                        <div class="space-y-3">
                            <div 
                                v-for="item in transfer.non_hp_items.filter(i => i.returned_quantity > 0)"
                                :key="item.id"
                                class="p-4 bg-surface-900/50 rounded-2xl border border-dashed border-surface-700 group-hover:border-danger/30 transition-colors"
                            >
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-4">
                                        <div class="p-2 bg-surface-800 rounded-xl border border-surface-700 shadow-sm">
                                            <Box class="w-5 h-5 text-orange-500" />
                                        </div>
                                        <div>
                                            <h5 class="font-bold text-text-primary">
                                                <span v-if="getBrandName(item)" class="text-orange-400 mr-1">[{{ getBrandName(item) }}]</span>
                                                {{ item.product?.name }}
                                            </h5>
                                            <p class="text-xs text-text-secondary mt-1">
                                                Ditolak: <span class="text-danger font-bold">{{ item.returned_quantity }}</span> Unit
                                            </p>
                                        </div>
                                    </div>
                                    <Truck class="w-5 h-5 text-danger opacity-20 hidden sm:block" />
                                </div>
                                <!-- Rejection Note -->
                                <div v-if="item.notes" class="mt-3 bg-danger/5 border border-danger/10 p-3 rounded-xl">
                                    <p class="text-[10px] font-bold text-danger uppercase tracking-wider mb-1">Alasan Penolakan:</p>
                                    <p class="text-xs text-text-primary italic">"{{ item.notes }}"</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Account Selection -->
                <div v-if="inventoryAccounts.length > 0" class="px-6 py-4 border-t border-surface-700 bg-surface-800/50">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                        <label class="shrink-0 text-xs font-bold text-text-secondary uppercase tracking-wider">Penerima Stok:</label>
                        <select 
                            v-model="selectedInventoryAccount"
                            class="flex-1 bg-surface-800 border border-surface-700 rounded-xl px-4 py-2 text-sm text-text-primary focus:outline-none focus:border-blue-500 transition-colors"
                        >
                            <option value="" disabled>Pilih Akun Inventory</option>
                            <option v-for="acc in inventoryAccounts" :key="acc.id" :value="acc.id">
                                {{ acc.full_name }} ({{ acc.code_id }})
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Action Footer -->
                <div class="px-6 py-4 bg-surface-800/10 border-t border-surface-700 flex justify-end">
                    <button 
                        @click="confirmReturn(transfer)"
                        :disabled="processingId === transfer.id || !selectedInventoryAccount"
                        class="btn btn-primary"
                    >
                        <RefreshCw v-if="processingId === transfer.id" class="w-4 h-4 animate-spin mr-2" />
                        <CheckCircle2 v-else class="w-4 h-4" />
                        <span>Terima Kembali ke Stok</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- PIN Modal -->
    <PinModal
        v-if="showPinModal"
        :show="showPinModal"
        title="Konfirmasi Terima Balik"
        description="Masukkan PIN untuk mengonfirmasi penerimaan barang kembali ke stok."
        :processing="processingId !== null"
        @close="showPinModal = false"
        @success="handlePinConfirm"
    />
</template>

<style scoped>
@reference "../../style.css";
/* No need for custom text-surface-400 classes here as we updated the template to use project standard classes and transparent utilities */
</style>
