<template>
    <div class="space-y-6">
        <h1 v-if="!isEmbedded" class="text-2xl font-bold tracking-tight text-text-primary">Audit Inventory</h1>

        <div v-if="loading" class="flex justify-center py-12">
            <Loader2 class="w-8 h-8 text-primary-600 animate-spin" />
        </div>

        <div v-else class="grid grid-cols-1 gap-5 sm:grid-cols-3">
            <!-- Card Stock -->
            <div class="overflow-hidden shadow rounded-lg hover:shadow-md transition-shadow cursor-pointer"
                :class="activeTab === 'stock' ? 'bg-surface-900 border border-surface-700 text-white' : 'bg-surface-0 dark:bg-surface-800 border border-surface-200 dark:border-surface-700'"
                @click="activeTab = 'stock'">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <Package class="h-6 w-6" :class="activeTab === 'stock' ? 'text-gray-400' : 'text-gray-400'"
                                aria-hidden="true" />
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium truncate"
                                    :class="activeTab === 'stock' ? 'text-gray-400' : 'text-text-secondary'">Total Stock
                                </dt>
                                <dd>
                                    <div class="text-lg font-medium"
                                        :class="activeTab === 'stock' ? 'text-white' : 'text-text-primary'">{{
                                        data.stock }}</div>
                                    <p class="text-xs mt-1"
                                        :class="activeTab === 'stock' ? 'text-gray-400' : 'text-text-secondary'">
                                        Melihat semua stok barang
                                    </p>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card In -->
            <div class="overflow-hidden shadow rounded-lg hover:shadow-md transition-shadow cursor-pointer"
                :class="activeTab === 'in' ? 'bg-surface-900 border border-surface-700 text-white' : 'bg-surface-0 dark:bg-surface-800 border border-surface-200 dark:border-surface-700'"
                @click="activeTab = 'in'">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <ArrowDownLeft class="h-6 w-6"
                                :class="activeTab === 'in' ? 'text-green-400' : 'text-green-500'" aria-hidden="true" />
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium truncate"
                                    :class="activeTab === 'in' ? 'text-gray-400' : 'text-text-secondary'">Barang Masuk
                                </dt>
                                <dd>
                                    <div class="text-lg font-medium"
                                        :class="activeTab === 'in' ? 'text-white' : 'text-text-primary'">{{ data.in }}
                                    </div>
                                    <p class="text-xs mt-1"
                                        :class="activeTab === 'in' ? 'text-gray-400' : 'text-text-secondary'">
                                        Riwayat barang masuk
                                    </p>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card Out -->
            <div class="overflow-hidden shadow rounded-lg hover:shadow-md transition-shadow cursor-pointer"
                :class="activeTab === 'out' ? 'bg-surface-900 border border-surface-700 text-white' : 'bg-surface-0 dark:bg-surface-800 border border-surface-200 dark:border-surface-700'"
                @click="activeTab = 'out'">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <ArrowUpRight class="h-6 w-6" :class="activeTab === 'out' ? 'text-red-400' : 'text-red-500'"
                                aria-hidden="true" />
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium truncate"
                                    :class="activeTab === 'out' ? 'text-gray-400' : 'text-text-secondary'">Barang Keluar
                                </dt>
                                <dd>
                                    <div class="text-lg font-medium"
                                        :class="activeTab === 'out' ? 'text-white' : 'text-text-primary'">{{ data.out }}
                                    </div>
                                    <p class="text-xs mt-1"
                                        :class="activeTab === 'out' ? 'text-gray-400' : 'text-text-secondary'">
                                        Riwayat barang keluar
                                    </p>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Embedded Content -->
        <div
            class="rounded-lg shadow-sm border border-surface-200 dark:border-surface-700 overflow-hidden bg-white dark:bg-surface-800">
            <Inventory v-if="activeTab === 'stock'" :is-embedded="true" />
            <StockInHistory v-if="activeTab === 'in'" :is-embedded="true" />
            <StockOutHistory v-if="activeTab === 'out'" :is-embedded="true" />
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Loader2, Package, ArrowDownLeft, ArrowUpRight } from 'lucide-vue-next'
import axios from '../../api/axios'
import Inventory from '../inventory/Inventory.vue'
import StockInHistory from '../inventory/StockInHistory.vue'
import StockOutHistory from '../inventory/StockOutHistory.vue'

const props = defineProps({
    isEmbedded: {
        type: Boolean,
        default: false
    }
});

const loading = ref(false)
const activeTab = ref('stock')

const data = ref({
    stock: 0,
    in: 0,
    out: 0
})

const fetchData = async () => {
    loading.value = true
    try {
        const response = await axios.get('/audit/inventory')
        data.value = response.data
    } catch (error) {
        console.error('Error fetching inventory summary:', error)
    } finally {
        loading.value = false
    }
}

onMounted(() => {
    fetchData()
})
</script>
