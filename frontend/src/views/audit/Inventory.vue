<template>
    <div class="space-y-6">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900">Audit Inventory</h1>

        <div v-if="loading" class="flex justify-center py-12">
            <Loader2 class="w-8 h-8 text-blue-600 animate-spin" />
        </div>

        <div v-else class="grid grid-cols-1 gap-5 sm:grid-cols-3">
            <!-- Card Stock -->
            <div class="bg-white overflow-hidden shadow rounded-lg hover:shadow-md transition-shadow cursor-pointer"
                @click="$router.push('/inventory')">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <Package class="h-6 w-6 text-gray-400" aria-hidden="true" />
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Stock</dt>
                                <dd>
                                    <div class="text-lg font-medium text-gray-900">{{ data.stock }}</div>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-5 py-3">
                    <div class="text-sm">
                        <a href="#" class="font-medium text-blue-700 hover:text-blue-900">View details</a>
                    </div>
                </div>
            </div>

            <!-- Card In -->
            <div class="bg-white overflow-hidden shadow rounded-lg hover:shadow-md transition-shadow cursor-pointer"
                @click="$router.push('/inventory/history/in')">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <ArrowDownLeft class="h-6 w-6 text-green-400" aria-hidden="true" />
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Barang Masuk</dt>
                                <dd>
                                    <div class="text-lg font-medium text-gray-900">{{ data.in }}</div>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-5 py-3">
                    <div class="text-sm">
                        <a href="#" class="font-medium text-green-700 hover:text-green-900">View history</a>
                    </div>
                </div>
            </div>

            <!-- Card Out -->
            <div class="bg-white overflow-hidden shadow rounded-lg hover:shadow-md transition-shadow cursor-pointer"
                @click="$router.push('/stock-outs')">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <ArrowUpRight class="h-6 w-6 text-red-400" aria-hidden="true" />
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Barang Keluar</dt>
                                <dd>
                                    <div class="text-lg font-medium text-gray-900">{{ data.out }}</div>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-5 py-3">
                    <div class="text-sm">
                        <a href="#" class="font-medium text-red-700 hover:text-red-900">View history</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Loader2, Package, ArrowDownLeft, ArrowUpRight } from 'lucide-vue-next'
import axios from '@/utils/axios'

const loading = ref(false)
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
