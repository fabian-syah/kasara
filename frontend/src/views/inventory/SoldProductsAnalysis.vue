<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { Search, BarChart3, MapPin, Package, RefreshCw, Calendar } from 'lucide-vue-next'
import api from '../../api/axios'
import { useAuthStore } from '../../store/auth'

const authStore = useAuthStore()

// Filter state
const selectedTimeFilter = ref('bulan_ini') // 'bulan_ini', 'tahun_ini', 'all_time'
const selectedBrand = ref('')
const selectedType = ref('')
const selectedStorage = ref('')
const selectedCondition = ref('')
const selectedMode = ref('hp') // 'hp' or 'non-hp'

// Data state
const loading = ref(false)
const results = ref([])
const summary = ref({
    total_qty: 0,
    total_locations: 0,
    terjual: 0,
    angkat_barang: 0,
    refund: 0,
    tukar_tambah: 0,
    tukar_unit: 0,
    downgrade: 0,
    retur: 0
})
const hasSearched = ref(false)

// Filter options (dynamic, only show what has been sold)
const brandOptions = ref([])
const typeOptions = ref([])
const storageOptions = ref([])
const conditionOptions = ref([])
const totalAvailable = ref(0)
const filtersLoading = ref(false)

// Load filter options based on current selection (cascading)
async function loadFilters() {
    try {
        filtersLoading.value = true
        const params = { 
            mode: selectedMode.value,
            time_filter: selectedTimeFilter.value
        }
        if (selectedBrand.value) params.brand = selectedBrand.value
        if (selectedType.value) params.product_name = selectedType.value
        if (selectedStorage.value) params.storage = selectedStorage.value
        if (selectedCondition.value) params.condition = selectedCondition.value

        const res = await api.get('/inventory/sold-analysis/filters', { params })
        const data = res.data

        brandOptions.value = data.brands || []
        typeOptions.value = data.types || []
        storageOptions.value = data.storages || []
        conditionOptions.value = data.conditions || []
        totalAvailable.value = data.total_available || 0
    } catch (e) {
        console.error('Failed to load filters:', e)
    } finally {
        filtersLoading.value = false
    }
}

onMounted(() => {
    loadFilters()
})

function onTimeFilterChange() {
    loadFilters()
    onFilterChange()
}

function onBrandChange() {
    selectedType.value = ''
    selectedStorage.value = ''
    selectedCondition.value = ''
    loadFilters()
    onFilterChange()
}

function onModeChange() {
    selectedBrand.value = ''
    selectedType.value = ''
    selectedStorage.value = ''
    selectedCondition.value = ''
    clearResults()
    loadFilters()
}

function onTypeChange() {
    selectedStorage.value = ''
    selectedCondition.value = ''
    loadFilters()
    onFilterChange()
}

function onStorageChange() {
    selectedCondition.value = ''
    loadFilters()
    onFilterChange()
}

function onConditionChange() {
    loadFilters()
    onFilterChange()
}

function onFilterChange() {
    if (selectedBrand.value || selectedType.value || selectedStorage.value || selectedCondition.value || selectedTimeFilter.value) {
        handleSearch(1)
    } else {
        clearResults()
    }
}

function clearResults() {
    results.value = []
    summary.value = {
        total_qty: 0,
        total_locations: 0,
        terjual: 0,
        angkat_barang: 0,
        refund: 0,
        tukar_tambah: 0,
        tukar_unit: 0,
        downgrade: 0,
        retur: 0
    }
    hasSearched.value = false
    pagination.value = { current_page: 1, last_page: 1, total: 0 }
}

// Pagination
const pagination = ref({ current_page: 1, last_page: 1, total: 0 })

// Search
async function handleSearch(page = 1) {
    loading.value = true
    hasSearched.value = true
    try {
        const params = { 
            page,
            mode: selectedMode.value,
            time_filter: selectedTimeFilter.value
        }
        if (selectedBrand.value) params.brand = selectedBrand.value
        if (selectedType.value) params.product_name = selectedType.value
        if (selectedStorage.value) params.storage = selectedStorage.value
        if (selectedCondition.value) params.condition = selectedCondition.value

        const res = await api.get('/inventory/sold-analysis', { params })
        results.value = res.data?.data || []
        summary.value = res.data?.summary || {
            total_qty: 0,
            total_locations: 0,
            terjual: 0,
            angkat_barang: 0,
            refund: 0,
            tukar_tambah: 0,
            tukar_unit: 0,
            downgrade: 0,
            retur: 0
        }
        pagination.value = {
            current_page: res.data?.current_page || 1,
            last_page: res.data?.last_page || 1,
            total: res.data?.total || 0,
        }
    } catch (e) {
        console.error('Sold analysis error:', e)
        clearResults()
    } finally {
        loading.value = false
    }
}

function resetFilters() {
    selectedTimeFilter.value = 'bulan_ini'
    selectedBrand.value = ''
    selectedType.value = ''
    selectedStorage.value = ''
    selectedCondition.value = ''
    clearResults()
    loadFilters()
}

function getConditionLabel(condition) {
    const map = { new: 'Baru', second: 'Second', ex_ibox: 'Ex-iBox', ex_inter: 'Ex-Inter', refurbished: 'Refurbished' }
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
                <h1 class="text-2xl font-bold text-text-primary">Analisa Produk Terjual</h1>
                <p class="text-sm text-text-secondary mt-1">Laporan historis kuantitas produk keluar berdasarkan transaksi</p>
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
            <!-- Mode and Time Toggle -->
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-4">
                <div class="flex items-center gap-2">
                    <button @click="selectedMode = 'hp'; onModeChange()"
                        class="px-4 py-2 text-sm font-medium rounded-xl transition-colors"
                        :class="selectedMode === 'hp' ? 'bg-primary-500 text-white shadow-sm' : 'bg-surface-100 dark:bg-surface-800 text-text-secondary hover:text-text-primary'">
                        HP (IMEI)
                    </button>
                    <button @click="selectedMode = 'non-hp'; onModeChange()"
                        class="px-4 py-2 text-sm font-medium rounded-xl transition-colors"
                        :class="selectedMode === 'non-hp' ? 'bg-primary-500 text-white shadow-sm' : 'bg-surface-100 dark:bg-surface-800 text-text-secondary hover:text-text-primary'">
                        Non-HP (Non-IMEI)
                    </button>
                </div>
                
                <div class="flex items-center gap-2 bg-surface-50 dark:bg-surface-800 p-1 rounded-xl border border-surface-200 dark:border-surface-700">
                    <button @click="selectedTimeFilter = 'bulan_ini'; onTimeFilterChange()"
                        class="px-4 py-1.5 text-sm font-medium rounded-lg transition-colors"
                        :class="selectedTimeFilter === 'bulan_ini' ? 'bg-white dark:bg-surface-700 text-primary-600 dark:text-primary-400 shadow-sm' : 'text-text-secondary hover:text-text-primary'">
                        Bulan Ini
                    </button>
                    <button @click="selectedTimeFilter = 'tahun_ini'; onTimeFilterChange()"
                        class="px-4 py-1.5 text-sm font-medium rounded-lg transition-colors"
                        :class="selectedTimeFilter === 'tahun_ini' ? 'bg-white dark:bg-surface-700 text-primary-600 dark:text-primary-400 shadow-sm' : 'text-text-secondary hover:text-text-primary'">
                        Tahun Ini
                    </button>
                    <button @click="selectedTimeFilter = 'all_time'; onTimeFilterChange()"
                        class="px-4 py-1.5 text-sm font-medium rounded-lg transition-colors"
                        :class="selectedTimeFilter === 'all_time' ? 'bg-white dark:bg-surface-700 text-primary-600 dark:text-primary-400 shadow-sm' : 'text-text-secondary hover:text-text-primary'">
                        All Time
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Brand Filter -->
                <div>
                    <label class="block text-xs font-medium text-text-secondary mb-1.5">
                        Brand
                        <span v-if="brandOptions.length > 0" class="text-text-secondary/60">({{ brandOptions.length }})</span>
                    </label>
                    <select v-model="selectedBrand" @change="onBrandChange"
                        class="w-full px-3 py-2.5 text-sm bg-surface-50 dark:bg-surface-800 border border-surface-200 dark:border-surface-700 rounded-xl text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500 transition-colors">
                        <option value="">Semua Brand</option>
                        <option v-for="brand in brandOptions" :key="brand" :value="brand">
                            {{ brand }}
                        </option>
                    </select>
                </div>

                <!-- Tipe Filter (only shows types with stock) -->
                <div>
                    <label class="block text-xs font-medium text-text-secondary mb-1.5">
                        Tipe Produk
                        <span v-if="typeOptions.length > 0" class="text-text-secondary/60">({{ typeOptions.length }})</span>
                    </label>
                    <select v-model="selectedType" @change="onTypeChange"
                        class="w-full px-3 py-2.5 text-sm bg-surface-50 dark:bg-surface-800 border border-surface-200 dark:border-surface-700 rounded-xl text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500 transition-colors">
                        <option value="">Semua Tipe</option>
                        <option v-for="type in typeOptions" :key="type.value" :value="type.value">
                            {{ type.label }} ({{ type.qty }})
                        </option>
                    </select>
                </div>

                <!-- Storage/GB Filter (only for HP mode) -->
                <div v-if="selectedMode === 'hp'">
                    <label class="block text-xs font-medium text-text-secondary mb-1.5">
                        Kapasitas
                        <span v-if="storageOptions.length > 0" class="text-text-secondary/60">({{ storageOptions.length }})</span>
                    </label>
                    <select v-model="selectedStorage" @change="onStorageChange"
                        class="w-full px-3 py-2.5 text-sm bg-surface-50 dark:bg-surface-800 border border-surface-200 dark:border-surface-700 rounded-xl text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500 transition-colors">
                        <option value="">Semua Kapasitas</option>
                        <option v-for="s in storageOptions" :key="s.value" :value="s.value">
                            {{ s.label }} ({{ s.qty }})
                        </option>
                    </select>
                </div>

                <!-- Kondisi Filter (only for HP mode) -->
                <div v-if="selectedMode === 'hp'">
                    <label class="block text-xs font-medium text-text-secondary mb-1.5">
                        Kondisi
                        <span v-if="conditionOptions.length > 0" class="text-text-secondary/60">({{ conditionOptions.length }})</span>
                    </label>
                    <select v-model="selectedCondition" @change="onConditionChange"
                        class="w-full px-3 py-2.5 text-sm bg-surface-50 dark:bg-surface-800 border border-surface-200 dark:border-surface-700 rounded-xl text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500 transition-colors">
                        <option value="">Semua Kondisi</option>
                        <option v-for="c in conditionOptions" :key="c.value" :value="c.value">
                            {{ c.label }}
                        </option>
                    </select>
                </div>
            </div>

            <!-- Search Button -->
            <div class="mt-4 flex justify-end">
                <button @click="handleSearch(1)" :disabled="loading"
                    class="flex items-center gap-2 px-5 py-2.5 text-sm font-medium text-white bg-primary-500 hover:bg-primary-600 disabled:opacity-50 disabled:cursor-not-allowed rounded-xl transition-colors shadow-sm">
                    <Search v-if="!loading" :size="16" />
                    <RefreshCw v-else :size="16" class="animate-spin" />
                    <span>Cari</span>
                </button>
            </div>
        </div>

        <!-- Summary Card -->
        <div v-if="hasSearched && !loading" class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 rounded-2xl p-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-primary-500/10 flex items-center justify-center">
                        <Package :size="20" class="text-primary-500" />
                    </div>
                    <div>
                        <p class="text-xs text-text-secondary">Total Unit Keluar</p>
                        <p class="text-xl font-bold text-text-primary">{{ summary.total_qty }}</p>
                    </div>
                </div>
            </div>
            <div class="bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 rounded-2xl p-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-500/10 flex items-center justify-center">
                        <BarChart3 :size="20" class="text-emerald-500" />
                    </div>
                    <div>
                        <p class="text-xs text-text-secondary">Total Terjual</p>
                        <p class="text-xl font-bold text-text-primary">{{ summary.terjual }}</p>
                    </div>
                </div>
            </div>
            <div class="bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 rounded-2xl p-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-500/10 flex items-center justify-center">
                        <RefreshCw :size="20" class="text-amber-500" />
                    </div>
                    <div>
                        <p class="text-xs text-text-secondary">Total Tukar Tambah</p>
                        <p class="text-xl font-bold text-text-primary">{{ summary.tukar_tambah }}</p>
                    </div>
                </div>
            </div>
            <div class="bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 rounded-2xl p-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-red-500/10 flex items-center justify-center">
                        <RefreshCw :size="20" class="text-red-500" />
                    </div>
                    <div>
                        <p class="text-xs text-text-secondary">Total Refund</p>
                        <p class="text-xl font-bold text-text-primary">{{ summary.refund }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex flex-col items-center justify-center py-16">
            <RefreshCw :size="32" class="text-primary-500 animate-spin mb-3" />
            <p class="text-sm text-text-secondary">Mencari data...</p>
        </div>

        <!-- Results Table -->
        <div v-else-if="hasSearched && results.length > 0"
            class="bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 rounded-2xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-surface-200 dark:border-surface-800 bg-surface-50 dark:bg-surface-800/50">
                            <th class="text-center px-3 py-3 font-medium text-text-secondary w-12">No</th>
                            <th class="text-left px-5 py-3 font-medium text-text-secondary">Lokasi</th>
                            <th v-if="selectedBrand" class="text-left px-5 py-3 font-medium text-text-secondary">Brand</th>
                            <th v-if="selectedType || selectedBrand" class="text-left px-5 py-3 font-medium text-text-secondary">Produk</th>
                            <th v-if="selectedStorage" class="text-left px-5 py-3 font-medium text-text-secondary">Kapasitas</th>
                            <th v-if="selectedCondition" class="text-left px-5 py-3 font-medium text-text-secondary">Kondisi</th>
                            
                            <th class="text-center px-4 py-3 font-medium text-text-secondary">Terjual</th>
                            <th class="text-center px-4 py-3 font-medium text-text-secondary">Angkat Barang</th>
                            <th class="text-center px-4 py-3 font-medium text-text-secondary">Refund</th>
                            <th class="text-center px-4 py-3 font-medium text-text-secondary">Tukar Tambah</th>
                            <th class="text-center px-4 py-3 font-medium text-text-secondary">Tukar Unit</th>
                            <th class="text-center px-4 py-3 font-medium text-text-secondary">Downgrade</th>
                            <th class="text-center px-4 py-3 font-medium text-text-secondary">Retur</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-100 dark:divide-surface-800">
                        <tr v-for="(item, idx) in results" :key="idx"
                            class="hover:bg-surface-50 dark:hover:bg-surface-800/30 transition-colors">
                            <td class="px-3 py-3.5 text-center text-text-secondary text-xs">{{ (pagination.current_page - 1) * 20 + idx + 1 }}</td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-2">
                                    <MapPin :size="14" class="text-text-secondary shrink-0" />
                                    <div>
                                        <span class="font-medium text-text-primary">{{ item.location_name }}</span>
                                        <span class="block text-[10px] text-text-secondary">{{ getLocationTypeLabel(item.location_type) }}</span>
                                    </div>
                                </div>
                            </td>
                            <td v-if="selectedBrand" class="px-5 py-3.5 text-text-primary font-medium">{{ item.brand }}</td>
                            <td v-if="selectedType || selectedBrand" class="px-5 py-3.5 text-text-primary">{{ item.product_name || '-' }}</td>
                            <td v-if="selectedStorage" class="px-5 py-3.5 text-text-secondary font-mono text-xs">{{ item.storage || '-' }}</td>
                            <td v-if="selectedCondition" class="px-5 py-3.5">
                                <span v-if="item.condition" class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium"
                                    :class="{
                                        'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400': item.condition === 'new',
                                        'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400': item.condition === 'second',
                                        'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400': item.condition === 'ex_ibox',
                                    }">
                                    {{ getConditionLabel(item.condition) }}
                                </span>
                                <span v-else class="text-text-secondary text-xs">-</span>
                            </td>
                            
                            <td class="px-4 py-3.5 text-center">
                                <span v-if="item.terjual > 0" class="inline-flex items-center justify-center min-w-[28px] px-2 py-1 rounded-lg text-sm font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                                    {{ item.terjual }}
                                </span>
                                <span v-else class="text-text-secondary text-xs">-</span>
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                <span v-if="item.angkat_barang > 0" class="font-medium text-text-primary">{{ item.angkat_barang }}</span>
                                <span v-else class="text-text-secondary text-xs">-</span>
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                <span v-if="item.refund > 0" class="font-medium text-red-500">{{ item.refund }}</span>
                                <span v-else class="text-text-secondary text-xs">-</span>
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                <span v-if="item.tukar_tambah > 0" class="font-medium text-amber-600 dark:text-amber-400">{{ item.tukar_tambah }}</span>
                                <span v-else class="text-text-secondary text-xs">-</span>
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                <span v-if="item.tukar_unit > 0" class="font-medium text-blue-500">{{ item.tukar_unit }}</span>
                                <span v-else class="text-text-secondary text-xs">-</span>
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                <span v-if="item.downgrade > 0" class="font-medium text-purple-500">{{ item.downgrade }}</span>
                                <span v-else class="text-text-secondary text-xs">-</span>
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                <span v-if="item.retur > 0" class="font-medium text-orange-500">{{ item.retur }}</span>
                                <span v-else class="text-text-secondary text-xs">-</span>
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

        <!-- Initial State -->
        <div v-else-if="!hasSearched"
            class="bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 rounded-2xl p-12 text-center">
            <div class="w-16 h-16 rounded-2xl bg-primary-500/10 flex items-center justify-center mx-auto mb-4">
                <Calendar :size="28" class="text-primary-500" />
            </div>
            <h3 class="text-base font-semibold text-text-primary mb-1">Pilih Filter Waktu</h3>
            <p class="text-sm text-text-secondary">Pilih rentang waktu dan filter lainnya untuk melihat laporan produk terjual</p>
        </div>
    </div>
</template>
