<template>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Audit Penjualan</h1>

            <!-- Date Filter -->
            <div class="flex items-center gap-2 bg-white p-2 rounded-lg border shadow-sm">
                <input type="date" v-model="filters.start_date"
                    class="border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500" />
                <span class="text-gray-500">-</span>
                <input type="date" v-model="filters.end_date"
                    class="border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500" />
                <button @click="fetchData"
                    class="px-3 py-2 bg-blue-600 text-white rounded-md text-sm hover:bg-blue-700 transition-colors"
                    :disabled="loading">
                    <Loader2 v-if="loading" class="w-4 h-4 animate-spin" />
                    <span v-else>Filter</span>
                </button>
            </div>
        </div>

        <!-- Tabs -->
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                <button v-for="tab in tabs" :key="tab.id" @click="currentTab = tab.id" :class="[
                    currentTab === tab.id
                        ? 'border-blue-500 text-blue-600'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                    'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
                ]">
                    {{ tab.name }}
                </button>
            </nav>
        </div>

        <!-- Content -->
        <div v-if="loading" class="flex justify-center py-12">
            <Loader2 class="w-8 h-8 text-blue-600 animate-spin" />
        </div>

        <div v-else>
            <!-- Daily Sales Table -->
            <div v-if="currentTab === 'daily'" class="bg-white shadow rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Waktu</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    No Pesanan</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Nama</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Kategori</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Tipe</th>
                                <th scope="col"
                                    class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Jml</th>
                                <th scope="col"
                                    class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status</th>
                                <th scope="col"
                                    class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Total</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="(item, index) in data.daily_sales" :key="index" class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ formatDate(item.date)
                                    }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">{{
                                    item.order_no }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <div>{{ item.customer_name }}</div>
                                    <div class="text-xs text-gray-400">{{ item.customer_phone }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ item.category }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ item.type }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">{{ item.qty }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                        :class="item.status === 'Lunas' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'">
                                        {{ item.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">{{
                                    formatCurrency(item.grand_total) }}</td>
                            </tr>
                            <tr v-if="data.daily_sales.length === 0">
                                <td colspan="8" class="px-6 py-12 text-center text-gray-500">Tidak ada data penjualan
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Brand Sales Table -->
            <div v-if="currentTab === 'brand'" class="bg-white shadow rounded-lg overflow-hidden max-w-4xl mx-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Brand</th>
                            <th scope="col"
                                class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Total Terjual (Unit)</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="(item, index) in data.brand_sales" :key="index" class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ item.brand }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">{{ item.qty }}</td>
                        </tr>
                        <tr v-if="data.brand_sales.length === 0">
                            <td colspan="2" class="px-6 py-12 text-center text-gray-500">Tidak ada data brand</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- CS Sales Table -->
            <div v-if="currentTab === 'cs'" class="bg-white shadow rounded-lg overflow-hidden max-w-4xl mx-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Nama CS</th>
                            <th scope="col"
                                class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Total Transaksi</th>
                            <th scope="col"
                                class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Grand Total</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="(item, index) in data.cs_sales" :key="index" class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ item.cs_name }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">{{ item.total_sales
                                }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">{{
                                formatCurrency(item.grand_total) }}</td>
                        </tr>
                        <tr v-if="data.cs_sales.length === 0">
                            <td colspan="3" class="px-6 py-12 text-center text-gray-500">Tidak ada data CS</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Loader2 } from 'lucide-vue-next'
import axios from '@/utils/axios'

const tabs = [
    { id: 'daily', name: 'Penjualan Harian' },
    { id: 'brand', name: 'Laporan per Brand' },
    { id: 'cs', name: 'Laporan per CS' }
]

const currentTab = ref('daily')
const loading = ref(false)
const data = ref({
    daily_sales: [],
    brand_sales: [],
    cs_sales: []
})

const filters = ref({
    start_date: new Date().toISOString().slice(0, 10), // Today
    end_date: new Date().toISOString().slice(0, 10)
})

const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(value)
}

const formatDate = (dateString) => {
    if (!dateString) return '-'
    return new Date(dateString).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
}

const fetchData = async () => {
    loading.value = true
    try {
        const response = await axios.get('/audit/sales', {
            params: filters.value
        })
        data.value = response.data
    } catch (error) {
        console.error('Error fetching sales data:', error)
    } finally {
        loading.value = false
    }
}

onMounted(() => {
    // Set start date to first day of month by default for better view? 
    // User usually wants to see daily sales for current day or month. 
    // Let's set start date to first day of current month.
    const now = new Date();
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
    filters.value.start_date = firstDay.toISOString().slice(0, 10);

    fetchData()
})
</script>
