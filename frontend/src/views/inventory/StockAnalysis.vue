<script setup>
import { ref, computed, onMounted } from 'vue'
import { Search, BarChart3, MapPin, Package, RefreshCw } from 'lucide-vue-next'
import { inventory, brands as brandsApi, productTypes as productTypesApi } from '../../api/axios'
import api from '../../api/axios'
import { useAuthStore } from '../../store/auth'

const authStore = useAuthStore()

// Filter state
const selectedBrand = ref('')
const selectedType = ref('')
const selectedStorage = ref('')
const selectedCondition = ref('')

// Data state
const loading = ref(false)
const results = ref([])
const summary = ref({ total_qty: 0, total_locations: 0 })
const hasSearched = ref(false)

// Filter options
const brandOptions = ref([])
const typeOptions = ref([])
const storageOptions = ref([])
const conditionOptions = [
    { label: 'Semua Kondisi', value: '' },
    { label: 'Baru (New)', value: 'new' },
    { label: 'Second', value: 'second' },
    { label: 'Ex-iBox', value: 'ex_ibox' },
]

// Load filter options
onMounted(async () => {
    await Promise.all([loadBrands(), loadProductTypes(), loadStorageOptions()])
})

async function loadBrands() {
    try {
        const res = await brandsApi.list({ per_page: -1 })
        const data = res.data?.data || res.data || []
        brandOptions.value = data.map(b => ({ label: b.name, value: b.name }))
    } catch (e) {
        console.error('Failed to load brands:', e)
    }
}

async function loadProductTypes() {
    try {
        const res = await productTypesApi.list({ per_page: -1 })
        const data = res.data?.data || res.data || []
        typeOptions.value = data.map(t => ({ label: t.name, value: t.id, storages: t.storages || [] }))
    } catch (e) {
        console.error('Failed to load product types:', e)
    }
}

// When product type changes, update storage options
function onTypeChange() {
    selectedStorage.value = ''
    const selected = typeOptions.value.find(t => t.value === selectedType.value)
    if (selected && selected.storages && selected.storages.length > 0) {
        storageOptions.value = selected.storages.map(s => ({ label: s, value: s }))
    } else {
        loadStorageOptions()
    }
    onFilterChange()
}

function clearResults() {
    results.value = []
    summary.value = { total_qty: 0, total_locations: 0 }
    hasSearched.value = false
    pagination.value = { current_page: 1, last_page: 1, total: 0 }
}

function onFilterChange() {
    // Auto-search if at least one filter is active
    if (selectedBrand.value || selectedType.value || selectedStorage.value || selectedCondition.value) {
        handleSearch(1)
    } else {
        clearResults()
    }
}

async function loadStorageOptions() {
    try {
        const params = {}
        if (selectedType.value) params.product_type_id = selectedType.value
        if (selectedBrand.value) params.brand = selectedBrand.value
        const res = await api.get('/inventory/filter-options', { params })
        const data = res.data
        if (data?.storages) {
            storageOptions.value = data.storages
                .filter(s => s)
                .map(s => ({ label: s, value: s }))
        }
        // Fallback: if no storages from API, use common values
        if (storageOptions.value.length === 0) {
            storageOptions.value = [
                '32 GB', '64 GB', '128 GB', '256 GB', '512 GB', '1 TB',
                '3/32', '4/64', '4/128', '6/128', '8/128', '8/256', '12/256'
            ].map(s => ({ label: s, value: s }))
        }
    } catch (e) {
        storageOptions.value = []
    }
}

// Pagination
const pagination = ref({ current_page: 1, last_page: 1, total: 0 })

// Search
async function handleSearch(page = 1) {
    if (!selectedBrand.value && !selectedType.value && !selectedStorage.value && !selectedCondition.value) {
        alert('Pilih minimal 1 filter sebelum mencari.')
        return
    }
    loading.value = true
    hasSearched.value = true
    try {
        const params = { page }
        if (selectedBrand.value) params.brand = selectedBrand.value
        if (selectedType.value) params.product_type_id = selectedType.value
        if (selectedStorage.value) params.storage = selectedStorage.value
        if (selectedCondition.value) params.condition = selectedCondition.value

        const res = await inventory.stockAnalysis(params)
        results.value = res.data?.data || []
        summary.value = res.data?.summary || { total_qty: 0, total_locations: 0 }
        pagination.value = {
            current_page: res.data?.current_page || 1,
            last_page: res.data?.last_page || 1,
            total: res.data?.total || 0,
        }
    } catch (e) {
        console.error('Stock analysis error:', e)
        results.value = []
        summary.value = { total_qty: 0, total_locations: 0 }
    } finally {
        loading.value = false
    }
}

function resetFilters() {
    selectedBrand.value = ''
    selectedType.value = ''
    selectedStorage.value = ''
    selectedCondition.value = ''
    storageOptions.value = []
    results.value = []
    summary.value = { total_qty: 0, total_locations: 0 }
    hasSearched.value = false
}

function getConditionLabel(condition) {
    const map = { new: 'Baru', second: 'Second', ex_ibox: 'Ex-iBox' }
    return map[condition] || condition || '-'
}

function getLocationTypeLabel(type) {
    const map = { branch: 'Cabang', warehouse: 'Gudang', online_shop: 'Toko Online', distributor: 'Distributor' }
    return map[type] || type
}
</script>

<template>
    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-text-primary">Analisa Stok</h1>
                <p class="text-sm text-text-secondary mt-1">Cari ketersediaan stok berdasarkan filter produk</p>
            </div>
            <div class="flex items-center gap-2">
                <button @click="resetFilters"
                    class="flex items-center gap-2 px-3 py-2 text-sm text-text-secondary hover:text-text-primary bg-surface-100 dark:bg-surface-800 hover:bg-surface-200 dark:hover:bg-surface-700 rounded-xl transition-colors">
                    <RefreshCw :size="16" />
                    <span class="hidden sm:inline">Reset</span>
                </button>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 rounded-2xl p-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Brand Filter -->
                <div>
                    <label class="block text-xs font-medium text-text-secondary mb-1.5">Brand</label>
                    <select v-model="selectedBrand" @change="onFilterChange"
                        class="w-full px-3 py-2.5 text-sm bg-surface-50 dark:bg-surface-800 border border-surface-200 dark:border-surface-700 rounded-xl text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500 transition-colors">
                        <option value="">Semua Brand</option>
                        <option v-for="brand in brandOptions" :key="brand.value" :value="brand.value">
                            {{ brand.label }}
                        </option>
                    </select>
                </div>

                <!-- Tipe Filter -->
                <div>
                    <label class="block text-xs font-medium text-text-secondary mb-1.5">Tipe Produk</label>
                    <select v-model="selectedType" @change="onTypeChange"
                        class="w-full px-3 py-2.5 text-sm bg-surface-50 dark:bg-surface-800 border border-surface-200 dark:border-surface-700 rounded-xl text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500 transition-colors">
                        <option value="">Semua Tipe</option>
                        <option v-for="type in typeOptions" :key="type.value" :value="type.value">
                            {{ type.label }}
                        </option>
                    </select>
                </div>

                <!-- Storage/GB Filter -->
                <div>
                    <label class="block text-xs font-medium text-text-secondary mb-1.5">Kapasitas (GB)</label>
                    <select v-model="selectedStorage" @change="onFilterChange"
                        class="w-full px-3 py-2.5 text-sm bg-surface-50 dark:bg-surface-800 border border-surface-200 dark:border-surface-700 rounded-xl text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500 transition-colors">
                        <option value="">Semua Kapasitas</option>
                        <option v-for="s in storageOptions" :key="s.value" :value="s.value">
                            {{ s.label }}
                        </option>
                    </select>
                </div>

                <!-- Kondisi Filter -->
                <div>
                    <label class="block text-xs font-medium text-text-secondary mb-1.5">Kondisi</label>
                    <select v-model="selectedCondition" @change="onFilterChange"
                        class="w-full px-3 py-2.5 text-sm bg-surface-50 dark:bg-surface-800 border border-surface-200 dark:border-surface-700 rounded-xl text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500 transition-colors">
                        <option v-for="c in conditionOptions" :key="c.value" :value="c.value">
                            {{ c.label }}
                        </option>
                    </select>
                </div>
            </div>

            <!-- Search Button -->
            <div class="mt-4 flex justify-end">
                <button @click="handleSearch" :disabled="loading"
                    class="flex items-center gap-2 px-5 py-2.5 text-sm font-medium text-white bg-primary-500 hover:bg-primary-600 disabled:opacity-50 disabled:cursor-not-allowed rounded-xl transition-colors shadow-sm">
                    <Search v-if="!loading" :size="16" />
                    <RefreshCw v-else :size="16" class="animate-spin" />
                    <span>Cari</span>
                </button>
            </div>
        </div>

        <!-- Summary Card -->
        <div v-if="hasSearched && !loading"
            class="grid grid-cols-2 gap-4">
            <div class="bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 rounded-2xl p-5">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-primary-500/10 flex items-center justify-center">
                        <Package :size="20" class="text-primary-500" />
                    </div>
                    <div>
                        <p class="text-xs text-text-secondary">Total Unit</p>
                        <p class="text-xl font-bold text-text-primary">{{ summary.total_qty }}</p>
                    </div>
                </div>
            </div>
            <div class="bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 rounded-2xl p-5">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-500/10 flex items-center justify-center">
                        <MapPin :size="20" class="text-emerald-500" />
                    </div>
                    <div>
                        <p class="text-xs text-text-secondary">Total Lokasi</p>
                        <p class="text-xl font-bold text-text-primary">{{ summary.total_locations }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex flex-col items-center justify-center py-16">
            <RefreshCw :size="32" class="text-primary-500 animate-spin mb-3" />
            <p class="text-sm text-text-secondary">Mencari data stok...</p>
        </div>

        <!-- Results Table -->
        <div v-else-if="hasSearched && results.length > 0"
            class="bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 rounded-2xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-surface-200 dark:border-surface-800 bg-surface-50 dark:bg-surface-800/50">
                            <th class="text-left px-5 py-3 font-medium text-text-secondary">Nama Cabang</th>
                            <th class="text-left px-5 py-3 font-medium text-text-secondary">Tipe Lokasi</th>
                            <th v-if="selectedType" class="text-left px-5 py-3 font-medium text-text-secondary">Produk</th>
                            <th v-if="selectedStorage" class="text-left px-5 py-3 font-medium text-text-secondary">Kapasitas</th>
                            <th v-if="selectedCondition" class="text-left px-5 py-3 font-medium text-text-secondary">Kondisi</th>
                            <th class="text-center px-5 py-3 font-medium text-text-secondary">Qty</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-100 dark:divide-surface-800">
                        <tr v-for="(item, idx) in results" :key="idx"
                            class="hover:bg-surface-50 dark:hover:bg-surface-800/30 transition-colors">
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-2">
                                    <MapPin :size="14" class="text-text-secondary shrink-0" />
                                    <span class="font-medium text-text-primary">{{ item.location_name }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-surface-100 dark:bg-surface-800 text-text-secondary">
                                    {{ getLocationTypeLabel(item.location_type) }}
                                </span>
                            </td>
                            <td v-if="selectedType" class="px-5 py-3.5">
                                <div>
                                    <span class="text-text-primary">{{ item.product_name }}</span>
                                </div>
                            </td>
                            <td v-if="selectedStorage" class="px-5 py-3.5 text-text-secondary">{{ item.storage || '-' }}</td>
                            <td v-if="selectedCondition" class="px-5 py-3.5">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium"
                                    :class="{
                                        'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400': item.condition === 'new',
                                        'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400': item.condition === 'second',
                                        'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400': item.condition === 'ex_ibox',
                                    }">
                                    {{ getConditionLabel(item.condition) }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                <span class="inline-flex items-center justify-center min-w-[32px] px-2 py-1 rounded-lg text-sm font-bold bg-primary-500/10 text-primary-600 dark:text-primary-400">
                                    {{ item.qty }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="pagination.last_page > 1" class="border-t border-surface-200 dark:border-surface-800 p-4 flex items-center justify-between">
                <span class="text-xs text-text-secondary">
                    Halaman {{ pagination.current_page }} dari {{ pagination.last_page }} ({{ pagination.total }} data)
                </span>
                <div class="flex gap-2">
                    <button @click="handleSearch(pagination.current_page - 1)" :disabled="pagination.current_page === 1"
                        class="px-3 py-1.5 text-xs font-medium rounded-lg bg-surface-100 dark:bg-surface-800 text-text-primary hover:bg-surface-200 dark:hover:bg-surface-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                        Sebelumnya
                    </button>
                    <button @click="handleSearch(pagination.current_page + 1)" :disabled="pagination.current_page === pagination.last_page"
                        class="px-3 py-1.5 text-xs font-medium rounded-lg bg-surface-100 dark:bg-surface-800 text-text-primary hover:bg-surface-200 dark:hover:bg-surface-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                        Selanjutnya
                    </button>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else-if="hasSearched && results.length === 0"
            class="bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 rounded-2xl p-12 text-center">
            <div class="w-16 h-16 rounded-2xl bg-surface-100 dark:bg-surface-800 flex items-center justify-center mx-auto mb-4">
                <BarChart3 :size="28" class="text-text-secondary" />
            </div>
            <h3 class="text-base font-semibold text-text-primary mb-1">Tidak ada data ditemukan</h3>
            <p class="text-sm text-text-secondary">Coba ubah filter pencarian Anda</p>
        </div>

        <!-- Initial State (before search) -->
        <div v-else-if="!hasSearched"
            class="bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 rounded-2xl p-12 text-center">
            <div class="w-16 h-16 rounded-2xl bg-primary-500/10 flex items-center justify-center mx-auto mb-4">
                <Search :size="28" class="text-primary-500" />
            </div>
            <h3 class="text-base font-semibold text-text-primary mb-1">Mulai Analisa Stok</h3>
            <p class="text-sm text-text-secondary">Pilih filter yang diinginkan lalu klik "Cari" untuk melihat ketersediaan stok</p>
        </div>
    </div>
</template>
