<script setup>
import { ref, onMounted } from 'vue'
import { onlineShop } from '../../api/axios'
import { useToast } from '../../composables/useToast'
import { History, ArrowUpRight, ArrowDownLeft } from 'lucide-vue-next'

const logs = ref([])
const isLoading = ref(true)
const pagination = ref({ current_page: 1, last_page: 1 })
const toast = useToast()

const fetchLogs = async (page = 1) => {
    isLoading.value = true
    try {
        const response = await onlineShop.inventory({ page })
        logs.value = response.data.data
        pagination.value = {
            current_page: response.data.current_page,
            last_page: response.data.last_page
        }
    } catch (error) {
        toast.error('Gagal memuat history inventory')
    } finally {
        isLoading.value = false
    }
}

onMounted(() => {
    fetchLogs()
})

const formatDate = (dateStr) => {
    const d = new Date(dateStr)
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) + ' ' + d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
}
</script>

<template>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-white flex items-center gap-2">
                    <History class="text-primary-500" />
                    Riwayat Inventory
                </h1>
                <p class="text-slate-400">Log mutasi stok (Bulan ini & Bulan lalu)</p>
            </div>
        </div>

        <!-- Table -->
        <div class="card p-0">
            <div v-if="isLoading" class="p-8 flex justify-center">
                <span class="loading loading-spinner text-primary-500"></span>
            </div>
            <div v-else class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Produk</th>
                            <th>Tipe</th>
                            <th class="text-right">Qty</th>
                            <th>User</th>
                            <th>Ket</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="log in logs" :key="log.id">
                            <td class="font-mono text-sm text-slate-400">{{ formatDate(log.created_at) }}</td>
                            <td>
                                <p class="text-white font-medium">{{ log.product ? log.product.name : 'Unknown Product'
                                    }}</p>
                            </td>
                            <td>
                                <span class="badge" :class="{
                                    'badge-success': ['in', 'transfer_in'].includes(log.type),
                                    'badge-error': ['out', 'transfer_out'].includes(log.type),
                                    'badge-warning': log.type === 'adjustment'
                                }">
                                    {{ log.type.toUpperCase() }}
                                </span>
                            </td>
                            <td class="text-right font-mono"
                                :class="log.quantity > 0 ? 'text-emerald-500' : 'text-red-500'">
                                {{ log.quantity > 0 ? '+' : '' }}{{ log.quantity }}
                            </td>
                            <td class="text-sm">
                                {{ log.user ? log.user.full_name : '-' }}
                            </td>
                            <td class="text-xs text-slate-500 max-w-[200px] truncate">
                                {{ log.description || '-' }}
                            </td>
                        </tr>
                        <tr v-if="logs.length === 0">
                            <td colspan="6" class="text-center py-8 text-slate-500">
                                Tidak ada data history untuk periode ini.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Simple Pagination -->
            <div v-if="pagination.last_page > 1" class="p-4 border-t border-slate-700 flex justify-end gap-2">
                <button @click="fetchLogs(pagination.current_page - 1)" :disabled="pagination.current_page <= 1"
                    class="btn btn-sm">
                    Prev
                </button>
                <span class="flex items-center text-sm text-slate-400 px-2">
                    Hal {{ pagination.current_page }} dari {{ pagination.last_page }}
                </span>
                <button @click="fetchLogs(pagination.current_page + 1)"
                    :disabled="pagination.current_page >= pagination.last_page" class="btn btn-sm">
                    Next
                </button>
            </div>
        </div>
    </div>
</template>
