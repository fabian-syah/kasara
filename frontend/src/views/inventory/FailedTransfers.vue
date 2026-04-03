<script setup>
import { ref, onMounted } from 'vue'
import { 
    Box, 
    ArrowLeft, 
    CheckCircle2, 
    AlertCircle, 
    Clock, 
    ChevronRight,
    Smartphone,
    Package,
    RefreshCw,
    Search,
    AlertTriangle,
    Truck,
    Building2
} from 'lucide-vue-next'
import api from '../../api/axios'
import { useToast } from '../../composables/useToast'
import { useAuthStore } from '../../store/auth'

const authStore = useAuthStore()
const { success, error: toastError } = useToast()

const transfers = ref([])
const loading = ref(true)
const searchQuery = ref('')
const processingId = ref(null)

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

const confirmReturn = async (transfer) => {
    if (!confirm(`Konfirmasi terima balik barang untuk transfer ${transfer.receipt_id}?`)) return
    
    processingId.value = transfer.id
    try {
        await api.post(`/transfers/${transfer.id}/confirm-return`)
        success('Barang telah diterima kembali ke stok.')
        fetchFailedTransfers()
    } catch (err) {
        console.error('Failed to confirm return:', err)
        toastError('Gagal memproses penerimaan barang.')
    } finally {
        processingId.value = null
    }
}

const formatCapacity = (ram, storage) => {
    if (!ram && !storage) return ''
    if (ram && storage) return `${ram}/${storage}`
    return ram || storage
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
})
</script>

<template>
    <div class="p-6 space-y-6 max-w-7xl mx-auto min-h-screen">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-white tracking-tight">Gagal Kirim (OTW Kembali)</h1>
                <p class="text-text-secondary mt-1">Barang yang ditolak oleh cabang penerima dan sedang dalam perjalanan kembali.</p>
            </div>
            
            <button 
                @click="fetchFailedTransfers"
                :disabled="loading"
                class="btn btn-secondary px-4 py-2 rounded-xl flex items-center gap-2"
            >
                <RefreshCw :class="{'animate-spin': loading}" class="w-4 h-4 text-blue-400" />
                <span>Refresh</span>
            </button>
        </div>

        <!-- Filter/Search -->
        <div class="card p-4 flex flex-col md:flex-row gap-4">
            <div class="relative flex-1">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-surface-400" />
                <input 
                    v-model="searchQuery"
                    type="text" 
                    placeholder="Cari ID Nota atau Cabang..." 
                    class="w-full pl-10 pr-4 py-2 bg-surface-900 border border-surface-700 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all text-white"
                />
            </div>
        </div>

        <!-- Content -->
        <div v-if="loading" class="card flex flex-col items-center justify-center py-20">
            <RefreshCw class="w-10 h-10 text-blue-500 animate-spin mb-4" />
            <p class="text-text-secondary font-medium">Memuat data transfer...</p>
        </div>

        <div v-else-if="transfers.length === 0" class="card flex flex-col items-center justify-center py-20">
            <div class="w-20 h-20 bg-surface-700 rounded-full flex items-center justify-center mb-6">
                <CheckCircle2 class="w-10 h-10 text-surface-500" />
            </div>
            <h3 class="text-xl font-semibold text-white mb-2">Tidak Ada Barang Gagal</h3>
            <p class="text-text-secondary max-w-sm text-center">Semua transfer Anda telah diterima dengan baik atau belum ada yang ditolak.</p>
        </div>

        <div v-else class="grid grid-cols-1 gap-6">
            <div 
                v-for="transfer in transfers" 
                :key="transfer.id"
                class="card overflow-hidden hover:border-surface-600 transition-all group p-0"
            >
                <!-- Card Header -->
                <div class="bg-surface-700/50 px-6 py-4 flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-surface-700">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-red-500/10 rounded-2xl flex items-center justify-center text-red-400 border border-red-500/20 italic font-bold text-sm shrink-0">
                            TRF
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-white">{{ transfer.receipt_id }}</span>
                                <span class="px-2 py-0.5 bg-red-500/20 text-red-400 text-[10px] uppercase font-bold rounded-full tracking-wider border border-red-500/30">Ditolak</span>
                            </div>
                            <div class="flex items-center gap-2 mt-0.5 text-xs text-text-secondary">
                                <Clock class="w-3 h-3" />
                                <span>{{ new Date(transfer.created_at).toLocaleString('id-ID') }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="text-right hidden md:block mr-2">
                            <p class="text-[10px] text-text-secondary uppercase font-bold tracking-tighter">Tujuan</p>
                            <p class="font-semibold text-white">{{ transfer.destination?.name || 'Unknown' }}</p>
                        </div>
                        <ChevronRight class="w-5 h-5 text-surface-500 hidden md:block" />
                    </div>
                </div>

                <!-- Item List -->
                <div class="p-6">
                    <h4 class="text-xs font-bold text-text-secondary uppercase tracking-widest mb-4">Daftar Barang (OTW Kembali)</h4>
                    <div class="space-y-3">
                        <div 
                            v-for="item in transfer.items.filter(i => i.status === 'returning')"
                            :key="item.id"
                            class="flex items-center justify-between p-4 bg-surface-900 rounded-2xl border border-dashed border-surface-700 group-hover:border-red-500/30 transition-colors"
                        >
                            <div class="flex items-center gap-4">
                                <div class="p-2 bg-surface-800 rounded-xl border border-surface-700">
                                    <Smartphone class="w-5 h-5 text-blue-400" />
                                </div>
                                <div>
                                    <h5 class="font-bold text-white">
                                        <span class="text-blue-400 mr-1">[{{ getBrandName(item) }}]</span>
                                        {{ item.product?.model_name }}
                                    </h5>
                                    <div class="flex flex-wrap items-center gap-2 mt-1">
                                        <span class="text-xs font-medium text-text-secondary bg-surface-700 px-2 py-0.5 rounded-md">
                                            {{ formatCapacity(item.product?.ram, item.product?.storage) }}
                                        </span>
                                        <span class="text-xs font-medium text-text-secondary bg-surface-700 px-2 py-0.5 rounded-md">
                                            {{ item.condition }}
                                        </span>
                                        <span class="text-xs font-bold text-text-secondary font-mono tracking-tighter ml-1">
                                            {{ item.imei }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <Truck class="w-5 h-5 text-red-400/50 hidden sm:block" />
                        </div>
                    </div>
                </div>

                <!-- Action Footer -->
                <div class="px-6 py-4 bg-surface-700/20 border-t border-surface-700 flex justify-end">
                    <button 
                        @click="confirmReturn(transfer)"
                        :disabled="processingId === transfer.id"
                        class="btn btn-primary px-6 py-2.5 rounded-xl font-bold text-sm shadow-lg shadow-blue-600/10 flex items-center gap-2"
                    >
                        <RefreshCw v-if="processingId === transfer.id" class="w-4 h-4 animate-spin" />
                        <CheckCircle2 v-else class="w-4 h-4" />
                        <span>Terima Kembali ke Stok</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@reference "../../style.css";

.card {
    @apply bg-surface-800 border border-surface-700 rounded-3xl p-6;
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

.text-text-secondary {
    @apply text-surface-400;
}
</style>
