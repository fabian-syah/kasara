<template>
    <div class="space-y-6">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Audit Inventory</h1>

        <div v-if="loading" class="flex justify-center py-12">
            <Loader2 class="w-8 h-8 text-blue-600 animate-spin" />
        </div>

        <div v-else class="grid grid-cols-1 gap-5 sm:grid-cols-3">
            <!-- Card Stock -->
            <div class="bg-white dark:bg-surface-800 overflow-hidden shadow rounded-lg hover:shadow-md transition-shadow cursor-pointer"
                @click="$router.push('/inventory')">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <Package class="h-6 w-6 text-gray-400" aria-hidden="true" />
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Total Stock
                                </dt>
                                <dd>
                                    <div class="text-lg font-medium text-gray-900 dark:text-white">{{ data.stock }}
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        {{ data.stock_hp }} IMEI | {{ data.stock_non_hp }} Non-IMEI
                                    </div>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 dark:bg-surface-900 px-5 py-3">
                    <div class="text-sm">
                        <a href="#"
                            class="font-medium text-blue-700 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300">View
                            details</a>
                    </div>
                </div>
            </div>

            <!-- Card In -->
            <div class="bg-white dark:bg-surface-800 overflow-hidden shadow rounded-lg hover:shadow-md transition-shadow cursor-pointer"
                @click="$router.push('/inventory/history/in')">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <ArrowDownLeft class="h-6 w-6 text-green-400" aria-hidden="true" />
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Barang Masuk
                                </dt>
                                <dd>
                                    <div class="text-lg font-medium text-gray-900 dark:text-white">{{ data.in }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        {{ data.in_hp }} IMEI | {{ data.in_non_hp }} Non-IMEI
                                    </div>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 dark:bg-surface-900 px-5 py-3">
                    <div class="text-sm">
                        <a href="#"
                            class="font-medium text-green-700 dark:text-green-400 hover:text-green-900 dark:hover:text-green-300">View
                            history</a>
                    </div>
                </div>
            </div>

            <!-- Card Out -->
            <div class="bg-white dark:bg-surface-800 overflow-hidden shadow rounded-lg hover:shadow-md transition-shadow cursor-pointer"
                @click="$router.push('/inventory/history/out')">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <ArrowUpRight class="h-6 w-6 text-red-400" aria-hidden="true" />
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Barang Keluar
                                </dt>
                                <dd>
                                    <div class="text-lg font-medium text-gray-900 dark:text-white">{{ data.out }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        {{ data.out_hp }} IMEI | {{ data.out_non_hp }} Non-IMEI
                                    </div>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 dark:bg-surface-900 px-5 py-3">
                    <div class="text-sm">
                        <a href="#"
                            class="font-medium text-red-700 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300">View
                            history</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Loader2, Package, ArrowDownLeft, ArrowUpRight } from 'lucide-vue-next'
import axios from '../../api/axios'

const loading = ref(false)
const data = ref({
    stock: 0,
    stock_hp: 0,
    stock_non_hp: 0,
    in: 0,
    in_hp: 0,
    in_non_hp: 0,
    out: 0,
    out_hp: 0,
    out_non_hp: 0
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
