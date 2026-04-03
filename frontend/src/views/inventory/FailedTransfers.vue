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
    Search
} from 'lucide-vue-next'
import api from '@/utils/api'
import { ROLES } from '@/utils/permissions'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/components/ui/toast'

const authStore = useAuthStore()
const { toast } = useToast()

const transfers = ref([])
const loading = ref(true)
const searchQuery = ref('')
const processingId = ref(null)

const fetchFailedTransfers = async () => {
    loading.ref = true
    try {
        const response = await api.get('/transfers/failed')
        transfers.value = response.data.data
    } catch (error) {
        console.error('Failed to fetch failed transfers:', error)
        toast({
            title: 'Error',
            description: 'Gagal mengambil data transfer.',
            variant: 'destructive'
        })
    } finally {
        loading.value = false
    }
}

const confirmReturn = async (transfer) => {
    if (!confirm(`Konfirmasi terima balik barang untuk transfer ${transfer.receipt_id}?`)) return
    
    processingId.value = transfer.id
    try {
        await api.post(`/transfers/${transfer.id}/confirm-return`)
        toast({
            title: 'Berhasil',
            description: 'Barang telah diterima kembali ke stok.',
            variant: 'success'
        })
        fetchFailedTransfers()
    } catch (error) {
        console.error('Failed to confirm return:', error)
        toast({
            title: 'Error',
            description: 'Gagal memproses penerimaan barang.',
            variant: 'destructive'
        })
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
    
    // Check various relation paths based on backend eager loading
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
    <div class="p-6 space-y-6 max-w-7xl mx-auto">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Gagal Kirim (OTW Kembali)</h1>
                <p class="text-muted-foreground mt-1">Barang yang ditolak oleh cabang penerima dan sedang dalam perjalanan kembali.</p>
            </div>
            
            <button 
                @click="fetchFailedTransfers"
                :disabled="loading"
                class="flex items-center gap-2 px-4 py-2 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-all font-medium disabled:opacity-50 shadow-sm"
            >
                <RefreshCw :class="{'animate-spin': loading}" class="w-4 h-4 text-primary" />
                <span>Refresh</span>
            </button>
        </div>

        <!-- Filter/Search -->
        <div class="flex flex-col md:flex-row gap-4 bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
            <div class="relative flex-1">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                <input 
                    v-model="searchQuery"
                    type="text" 
                    placeholder="Cari ID Nota atau Cabang..." 
                    class="w-full pl-10 pr-4 py-2 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all bg-slate-50/50"
                />
            </div>
        </div>

        <!-- Content -->
        <div v-if="loading" class="flex flex-col items-center justify-center py-20 bg-white rounded-3xl border border-slate-100 shadow-sm">
            <RefreshCw class="w-10 h-10 text-primary animate-spin mb-4" />
            <p class="text-slate-500 font-medium">Memuat data transfer...</p>
        </div>

        <div v-else-if="transfers.length === 0" class="flex flex-col items-center justify-center py-20 bg-white rounded-3xl border border-slate-100 shadow-sm">
            <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mb-6">
                <CheckCircle2 class="w-10 h-10 text-slate-300" />
            </div>
            <h3 class="text-xl font-semibold text-slate-900 mb-2">Tidak Ada Barang Gagal</h3>
            <p class="text-slate-500 max-w-sm text-center">Semua transfer Anda telah diterima dengan baik atau belum ada yang ditolak.</p>
        </div>

        <div v-else class="grid grid-cols-1 gap-6">
            <div 
                v-for="transfer in transfers" 
                :key="transfer.id"
                class="bg-white rounded-3xl border border-slate-100 overflow-hidden shadow-sm hover:shadow-md transition-all group"
            >
                <!-- Card Header -->
                <div class="bg-slate-50/50 px-6 py-4 flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-100">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-red-50 rounded-2xl flex items-center justify-center text-red-600 shadow-sm ring-1 ring-red-100 italic font-bold text-sm shrink-0">
                            TRF
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-slate-900">{{ transfer.receipt_id }}</span>
                                <span class="px-2 py-0.5 bg-red-100 text-red-700 text-[10px] uppercase font-bold rounded-full tracking-wider">Ditolak</span>
                            </div>
                            <div class="flex items-center gap-2 mt-0.5 text-xs text-slate-500">
                                <Clock class="w-3 h-3 text-slate-400" />
                                <span>{{ new Date(transfer.created_at).toLocaleString('id-ID') }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="text-right hidden md:block mr-2">
                            <p class="text-[10px] text-slate-400 uppercase font-bold tracking-tighter">Tujuan</p>
                            <p class="font-semibold text-slate-700">{{ transfer.destination?.name || 'Unknown' }}</p>
                        </div>
                        <ChevronRight class="w-5 h-5 text-slate-300 hidden md:block" />
                    </div>
                </div>

                <!-- Item List -->
                <div class="p-6">
                    <h4 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">Daftar Barang (OTW Kembali)</h4>
                    <div class="space-y-3">
                        <div 
                            v-for="item in transfer.items.filter(i => i.status === 'returning')"
                            :key="item.id"
                            class="flex items-center justify-between p-4 bg-slate-50/50 rounded-2xl border border-dashed border-slate-200 group-hover:bg-red-50/10 group-hover:border-red-100 transition-colors"
                        >
                            <div class="flex items-center gap-4">
                                <div class="p-2 bg-white rounded-xl shadow-sm border border-slate-100">
                                    <Smartphone class="w-5 h-5 text-primary" />
                                </div>
                                <div>
                                    <h5 class="font-bold text-slate-900">
                                        <span class="text-primary mr-1">[{{ getBrandName(item) }}]</span>
                                        {{ item.product?.model_name }}
                                    </h5>
                                    <div class="flex flex-wrap items-center gap-2 mt-1">
                                        <span class="text-xs font-medium text-slate-600 bg-slate-200/50 px-2 py-0.5 rounded-md">
                                            {{ formatCapacity(item.product?.ram, item.product?.storage) }}
                                        </span>
                                        <span class="text-xs font-medium text-slate-600 bg-slate-200/50 px-2 py-0.5 rounded-md">
                                            {{ item.condition }}
                                        </span>
                                        <span class="text-xs font-bold text-slate-400 font-mono tracking-tighter ml-1">
                                            {{ item.imei }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <AlertCircle class="w-5 h-5 text-red-400 animate-pulse hidden sm:block" />
                        </div>
                    </div>
                </div>

                <!-- Action Footer -->
                <div class="px-6 py-4 bg-slate-50/30 border-t border-slate-100 flex justify-end">
                    <button 
                        @click="confirmReturn(transfer)"
                        :disabled="processingId === transfer.id"
                        class="flex items-center gap-2 px-6 py-2.5 bg-primary text-white rounded-xl hover:bg-primary-dark transition-all font-bold text-sm shadow-lg shadow-primary/20 disabled:opacity-50"
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
.bg-primary {
    background-color: #2563eb;
}
.bg-primary-dark {
    background-color: #1e40af;
}
.text-primary {
    color: #2563eb;
}
</style>
