<template>
    <div class="space-y-6">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Lacak Barang</h1>

        <!-- Search -->
        <div class="max-w-xl">
            <label for="search" class="sr-only">Cari IMEI/SKU</label>
            <div class="relative rounded-md shadow-sm">
                <input type="text" name="search" id="search" v-model="searchQuery" @keyup.enter="performSearch"
                    class="focus:ring-blue-500 focus:border-blue-500 block w-full pl-4 pr-12 sm:text-sm border-gray-300 dark:border-surface-600 rounded-md py-3 bg-white dark:bg-surface-800 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400"
                    placeholder="Masukkan IMEI atau Nama Barang...">
                <div class="absolute inset-y-0 right-0 flex items-center">
                    <button @click="performSearch"
                        class="h-full px-4 text-gray-500 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 focus:outline-none border-l border-gray-300 dark:border-surface-600">
                        <Search class="h-5 w-5" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Results -->
        <div v-if="loading" class="flex justify-center py-12">
            <Loader2 class="w-8 h-8 text-blue-600 animate-spin" />
        </div>

        <div v-else-if="results.length > 0"
            class="bg-white dark:bg-surface-800 shadow rounded-lg overflow-hidden border border-gray-200 dark:border-surface-700">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-surface-700">
                    <thead class="bg-gray-50 dark:bg-surface-900">
                        <tr>
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Nama Barang</th>
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                IMEI</th>
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Lokasi</th>
                            <th scope="col"
                                class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-surface-800 divide-y divide-gray-200 dark:divide-surface-700">
                        <tr v-for="item in results" :key="item.id"
                            class="hover:bg-gray-50 dark:hover:bg-surface-700/50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">{{
                                item.name }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 font-mono">
                                {{ item.imei }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{
                                item.location }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                    :class="getStatusClass(item.status)">
                                    {{ item.status }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-else-if="searched" class="text-center py-12 text-gray-500">
            Tidak ada barang ditemukan dengan kata kunci tersebut.
        </div>

    </div>
</template>

<script setup>
import { ref } from 'vue'
import { Loader2, Search } from 'lucide-vue-next'
import axios from '../../api/axios'

const searchQuery = ref('')
const loading = ref(false)
const searched = ref(false)
const results = ref([])

const performSearch = async () => {
    if (!searchQuery.value) return

    loading.value = true
    searched.value = true
    results.value = []

    try {
        const response = await axios.get('/audit/track', {
            params: { q: searchQuery.value }
        })
        results.value = response.data
    } catch (error) {
        console.error('Error tracking item:', error)
    } finally {
        loading.value = false
    }
}

const getStatusClass = (status) => {
    switch (status) {
        case 'available': return 'bg-green-100 text-green-800'
        case 'sold': return 'bg-blue-100 text-blue-800'
        case 'defect': return 'bg-red-100 text-red-800'
        case 'transfer': return 'bg-yellow-100 text-yellow-800'
        default: return 'bg-gray-100 text-gray-800'
    }
}
</script>
