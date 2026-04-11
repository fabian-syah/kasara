<template>
    <div class="space-y-6 animate-in">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div class="flex items-center gap-3">
                <button v-if="currentView !== 'menu'" @click="goBack"
                    class="p-2 hover:bg-surface-800 rounded-xl transition-colors">
                    <ArrowLeft :size="20" class="text-text-secondary" />
                </button>
                <div v-else class="w-12 h-12 bg-amber-500/20 rounded-xl flex items-center justify-center">
                    <Trophy :size="24" class="text-amber-500" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-text-primary tracking-tight">Peringkat Penjualan</h1>
                    <p class="text-text-secondary mt-0.5 text-sm">
                        {{ viewLabels[currentView] || 'Lihat peringkat penjualan dan performa sales' }}
                    </p>
                </div>
            </div>
            
            <!-- Filters (Period & Date) -->
            <div class="flex flex-wrap items-center gap-2">
                <!-- Refresh Button -->
                <button @click="fetchData"
                    class="p-2.5 text-text-secondary hover:text-primary-500 hover:bg-primary-500/10 rounded-xl transition-all mr-2">
                    <RefreshCw :size="20" :class="{ 'animate-spin': loading }" />
                </button>

                <div class="flex items-center gap-2 bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl p-1">
                    <button v-for="p in ['daily', 'monthly']" :key="p"
                        @click="selectedPeriod = p; handlePeriodChange()"
                        class="px-4 py-1.5 rounded-lg text-xs font-bold transition-all"
                        :class="selectedPeriod === p ? 'bg-primary-500 text-white shadow-sm' : 'text-text-secondary hover:text-text-primary'">
                        {{ p === 'daily' ? 'Harian' : 'Bulanan' }}
                    </button>
                </div>

                <div v-if="selectedPeriod === 'daily'" class="relative group">
                    <div class="flex items-center gap-2 px-4 py-2 bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl hover:border-primary-500 transition-all cursor-pointer">
                        <Calendar :size="16" class="text-gray-500 group-hover:text-primary-500" />
                        <span class="text-xs font-bold text-text-primary">{{ formattedDateDisplay }}</span>
                    </div>
                    <input type="date" v-model="filters.start_date" @change="handleDateChange"
                        @click="$event.target.showPicker()"
                        :min="minDate" :max="todayDate"
                        class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-20" />
                </div>

                <div v-else class="flex items-center gap-2">
                    <select v-model="selectedMonth" @change="handleMonthChange"
                        class="bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-3 py-2 text-xs font-bold cursor-pointer text-text-primary focus:ring-0 shadow-sm transition-all hover:border-primary-500">
                        <option v-for="m in availableMonths" :key="m.value" :value="m.value">{{ m.name }}</option>
                    </select>
                    <select v-model="selectedYear" @change="handleMonthChange"
                        class="bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-3 py-2 text-xs font-bold cursor-pointer text-text-primary focus:ring-0 shadow-sm transition-all hover:border-primary-500">
                        <option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option>
                    </select>
                </div>

                <!-- Location Filter (Branch/OS) -->
                <div class="flex items-center gap-2 bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl p-1 shadow-sm">
                    <div class="flex items-center gap-1 group">
                        <div class="p-1.5 bg-gray-50 dark:bg-surface-900 rounded-lg group-hover:bg-primary-500/10 transition-colors">
                            <MapPin v-if="locationType === 'branch'" :size="14" class="text-text-secondary group-hover:text-primary-500" />
                            <Globe v-else :size="14" class="text-text-secondary group-hover:text-primary-500" />
                        </div>
                        <select v-model="locationType" @change="handleLocationTypeChange"
                            class="bg-transparent border-none text-[10px] uppercase tracking-wider font-black text-text-secondary focus:ring-0 cursor-pointer pr-6">
                            <option value="branch">Cabang</option>
                            <option value="online">Online</option>
                        </select>
                    </div>
                    <div class="w-px h-4 bg-gray-200 dark:bg-surface-700 mr-1"></div>
                    <select v-if="locationType === 'branch'" v-model="filters.branch_id" @change="fetchData"
                        class="bg-transparent border-none text-xs font-bold text-text-primary focus:ring-0 cursor-pointer min-w-[140px] appearance-none pr-8">
                        <option :value="null">Semua Cabang</option>
                        <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                    <select v-else v-model="filters.online_shop_id" @change="fetchData"
                        class="bg-transparent border-none text-xs font-bold text-text-primary focus:ring-0 cursor-pointer min-w-[140px] appearance-none pr-8">
                        <option :value="null">Semua Toko Online</option>
                        <option v-for="s in onlineShops" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- ==================== MENU LANDING ==================== -->
        <template v-if="currentView === 'menu'">
            <div v-if="loading" class="flex flex-col items-center justify-center py-20">
                <Loader2 class="animate-spin text-primary-500 mb-4" :size="40" />
                <p class="text-text-secondary text-sm font-medium">Memuat data peringkat...</p>
            </div>

            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <!-- Card: Per Hari (Replacing Per Omset) -->
                <button @click="navigateTo('revenue')"
                    class="group bg-white dark:!bg-surface-800 rounded-2xl border border-gray-100 dark:border-surface-700 hover:border-amber-500/50 p-6 text-left transition-all duration-300 hover:shadow-lg hover:shadow-amber-500/5 hover:-translate-y-1">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-amber-500/10 rounded-xl group-hover:bg-amber-500/20 transition-colors">
                            <Calendar :size="24" class="text-amber-500" />
                        </div>
                        <ChevronRight :size="20" class="text-text-secondary group-hover:text-amber-500 transition-colors" />
                    </div>
                    <h3 class="text-lg font-bold text-text-primary mb-1">Peringkat per Hari</h3>
                    <p class="text-sm text-text-secondary">Ringkasan total nilai penjualan per tanggal</p>
                </button>

                <!-- Card: Per Sales -->
                <button @click="navigateTo('sales')"
                    class="group bg-white dark:!bg-surface-800 rounded-2xl border border-gray-100 dark:border-surface-700 hover:border-blue-500/50 p-6 text-left transition-all duration-300 hover:shadow-lg hover:shadow-blue-500/5 hover:-translate-y-1">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-blue-500/10 rounded-xl group-hover:bg-blue-500/20 transition-colors">
                            <Users :size="24" class="text-blue-500" />
                        </div>
                        <ChevronRight :size="20" class="text-text-secondary group-hover:text-blue-500 transition-colors" />
                    </div>
                    <h3 class="text-lg font-bold text-text-primary mb-1">Peringkat per Sales</h3>
                    <p class="text-sm text-text-secondary">Ranking sales berdasarkan jumlah unit terjual</p>
                </button>

                <!-- Card: Per Brand -->
                <button @click="navigateTo('brand')"
                    class="group bg-white dark:!bg-surface-800 rounded-2xl border border-gray-100 dark:border-surface-700 hover:border-purple-500/50 p-6 text-left transition-all duration-300 hover:shadow-lg hover:shadow-purple-500/5 hover:-translate-y-1">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-purple-500/10 rounded-xl group-hover:bg-purple-500/20 transition-colors">
                            <Layers :size="24" class="text-purple-500" />
                        </div>
                        <ChevronRight :size="20" class="text-text-secondary group-hover:text-purple-500 transition-colors" />
                    </div>
                    <h3 class="text-lg font-bold text-text-primary mb-1">Penjualan per Brand</h3>
                    <p class="text-sm text-text-secondary">Ringkasan penjualan berdasarkan merek produk</p>
                </button>

                <!-- Card: Per Tipe -->
                <button @click="navigateTo('type')"
                    class="group bg-white dark:!bg-surface-800 rounded-2xl border border-gray-100 dark:border-surface-700 hover:border-emerald-500/50 p-6 text-left transition-all duration-300 hover:shadow-lg hover:shadow-emerald-500/5 hover:-translate-y-1">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-emerald-500/10 rounded-xl group-hover:bg-emerald-500/20 transition-colors">
                            <Smartphone :size="24" class="text-emerald-500" />
                        </div>
                        <ChevronRight :size="20" class="text-text-secondary group-hover:text-emerald-500 transition-colors" />
                    </div>
                    <h3 class="text-lg font-bold text-text-primary mb-1">Penjualan per Tipe</h3>
                    <p class="text-sm text-text-secondary">Ringkasan penjualan berdasarkan tipe/model produk</p>
                </button>

                <!-- Card: Per Kondisi -->
                <button @click="navigateTo('condition')"
                    class="group bg-white dark:!bg-surface-800 rounded-2xl border border-gray-100 dark:border-surface-700 hover:border-orange-500/50 p-6 text-left transition-all duration-300 hover:shadow-lg hover:shadow-orange-500/5 hover:-translate-y-1">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-orange-500/10 rounded-xl group-hover:bg-orange-500/20 transition-colors">
                            <Tag :size="24" class="text-orange-500" />
                        </div>
                        <ChevronRight :size="20" class="text-text-secondary group-hover:text-orange-500 transition-colors" />
                    </div>
                    <h3 class="text-lg font-bold text-text-primary mb-1">Penjualan per Kondisi</h3>
                    <p class="text-sm text-text-secondary">Ringkasan penjualan berdasarkan kondisi (New/Second)</p>
                </button>

                <!-- Card: Activity Ranking -->
                <button @click="navigateTo('activity')"
                    class="group bg-white dark:!bg-surface-800 rounded-2xl border border-gray-100 dark:border-surface-700 hover:border-red-500/50 p-6 text-left transition-all duration-300 hover:shadow-lg hover:shadow-red-500/5 hover:-translate-y-1">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-red-500/10 rounded-xl group-hover:bg-red-500/20 transition-colors">
                            <RotateCcw :size="24" class="text-red-500" />
                        </div>
                        <ChevronRight :size="20" class="text-text-secondary group-hover:text-red-500 transition-colors" />
                    </div>
                    <h3 class="text-lg font-bold text-text-primary mb-1">Angkat Barang & Refund</h3>
                    <p class="text-sm text-text-secondary">Ranking sales berdasarkan jumlah refund & angkat barang</p>
                </button>

                <!-- Card: Per Distributor -->
                <button @click="navigateTo('distributor')"
                    class="group bg-white dark:!bg-surface-800 rounded-2xl border border-gray-100 dark:border-surface-700 hover:border-indigo-500/50 p-6 text-left transition-all duration-300 hover:shadow-lg hover:shadow-indigo-500/5 hover:-translate-y-1">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-indigo-500/10 rounded-xl group-hover:bg-indigo-500/20 transition-colors">
                            <Truck :size="24" class="text-indigo-500" />
                        </div>
                        <ChevronRight :size="20" class="text-text-secondary group-hover:text-indigo-500 transition-colors" />
                    </div>
                    <h3 class="text-lg font-bold text-text-primary mb-1">Peringkat per Distributor</h3>
                    <p class="text-sm text-text-secondary">Ranking penjualan berdasarkan asal distributor</p>
                </button>
            </div>
        </template>

        <!-- ==================== SUB-VIEWS ==================== -->
        <template v-else>
            <!-- Sub-view Header (Search & Sort) -->
            <div class="bg-white dark:!bg-surface-800 rounded-2xl border border-gray-100 dark:border-surface-700 p-4">
                <div class="flex flex-col sm:flex-row gap-4 justify-between items-start sm:items-center">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center bg-gray-50 dark:bg-surface-900 border border-gray-200 dark:border-surface-700 rounded-xl px-3 py-2">
                            <ListFilter class="text-text-secondary mr-2" :size="16" />
                            <select v-model="sortConfig.order" 
                                class="bg-transparent text-xs font-bold text-text-primary focus:outline-none cursor-pointer appearance-none min-w-[120px]">
                                <option value="num-desc" class="dark:bg-surface-800">Angka Terbanyak</option>
                                <option value="num-asc" class="dark:bg-surface-800">Angka Terendah</option>
                                <option value="alpha-asc" class="dark:bg-surface-800">Abjad (A-Z)</option>
                                <option value="alpha-desc" class="dark:bg-surface-800">Abjad (Z-A)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Search -->
                    <div class="relative w-full sm:w-80 group">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary group-focus-within:text-primary-400 transition-colors" :size="18" />
                        <input v-model="searchQuery" type="text" placeholder="Cari..."
                            class="w-full bg-gray-50 dark:bg-surface-900 border border-gray-200 dark:border-surface-700 rounded-xl py-2 pl-10 pr-4 text-sm font-medium text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all" />
                    </div>
                </div>
            </div>

            <!-- Content Area -->
            <div class="bg-white dark:!bg-surface-800 rounded-2xl border border-gray-100 dark:border-surface-700 overflow-hidden shadow-sm">
                <div v-if="loading" class="p-12 flex justify-center items-center">
                    <Loader2 class="animate-spin text-primary-500" :size="32" />
                </div>
                
                <div v-else-if="sortedData.length === 0" class="p-12 text-center text-text-secondary">
                    <Search :size="48" class="mx-auto mb-4 opacity-20" />
                    <p class="font-medium">Tidak ada data ditemukan</p>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs font-bold text-text-secondary uppercase bg-gray-50/50 dark:bg-surface-900/50 border-b border-gray-100 dark:border-surface-700">
                            <tr>
                                <th class="px-6 py-4 w-16">Rank</th>
                                
                                <!-- Dynamic Columns -->
                                <template v-if="currentView === 'revenue'">
                                    <th class="px-6 py-4">Tanggal</th>
                                    <th class="px-6 py-4 text-center">Unit Terjual</th>
                                    <th class="px-6 py-4 text-right">Total Omset</th>
                                </template>

                                <template v-else-if="['sales', 'activity'].includes(currentView)">
                                    <th class="px-6 py-4">Sales</th>
                                    <th class="px-6 py-4 text-center">Unit Terjual</th>
                                    <th v-if="currentView === 'activity'" class="px-6 py-4 text-center">Angkat Barang</th>
                                    <th v-if="currentView === 'activity'" class="px-6 py-4 text-center">Refund</th>
                                    <th class="px-6 py-4 text-right">Grand Total</th>
                                </template>

                                <template v-else-if="currentView === 'brand'">
                                    <th class="px-6 py-4">Brand</th>
                                    <th class="px-6 py-4 text-center">Unit Terjual</th>
                                </template>

                                <template v-else-if="currentView === 'type'">
                                    <th class="px-6 py-4">Brand</th>
                                    <th class="px-6 py-4">Tipe Produk</th>
                                    <th class="px-6 py-4 text-center">Unit Terjual</th>
                                </template>

                                <template v-else-if="currentView === 'condition'">
                                    <th class="px-6 py-4">Kondisi</th>
                                    <th class="px-6 py-4 text-center">Unit Terjual</th>
                                </template>

                                <template v-else-if="currentView === 'distributor'">
                                    <th class="px-6 py-4">Distributor</th>
                                    <th class="px-6 py-4 text-center">Unit Terjual</th>
                                </template>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-surface-700/50">
                            <tr v-for="(item, idx) in sortedData" :key="idx" 
                                class="hover:bg-gray-50 dark:hover:bg-surface-700/30 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-black shadow-sm"
                                        :class="getRankBadgeClass(idx)">
                                        {{ idx + 1 }}
                                    </div>
                                </td>

                                <!-- Daily History Data -->
                                <template v-if="currentView === 'revenue'">
                                    <td class="px-6 py-4">
                                        <span class="font-bold text-text-primary">{{ formatDateString(item.reporting_date) }}</span>
                                    </td>
                                    <td class="px-6 py-4 text-center font-black text-amber-500">{{ item.total_units }}</td>
                                    <td class="px-6 py-4 text-right font-black text-text-primary font-mono whitespace-nowrap">
                                        {{ formatCurrency(item.total_omset) }}
                                    </td>
                                </template>

                                <!-- CS Related Data -->
                                <template v-else-if="['sales', 'activity'].includes(currentView)">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <img :src="item.photo
                                                ? (item.photo.startsWith('http') ? item.photo : `${storageBaseUrl}/storage/${item.photo}`)
                                                : `https://ui-avatars.com/api/?name=${encodeURIComponent(item.cs_name)}&background=10b981&color=fff&size=48`"
                                                class="w-10 h-10 rounded-xl object-cover border-2 border-surface-200 dark:border-surface-600 shadow-sm"
                                                @error="(e) => e.target.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(item.cs_name)}&background=10b981&color=fff&size=48`" />
                                            <span class="font-bold text-text-primary">{{ item.cs_name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center font-black text-primary-500">{{ item.total_sales }}</td>
                                    <td v-if="currentView === 'activity'" class="px-6 py-4 text-center font-bold text-amber-500">{{ item.total_angkat_barang || 0 }}</td>
                                    <td v-if="currentView === 'activity'" class="px-6 py-4 text-center font-bold text-red-500">{{ item.total_refund || 0 }}</td>
                                    <td class="px-6 py-4 text-right font-black text-text-primary font-mono whitespace-nowrap">
                                        {{ formatCurrency(item.grand_total) }}
                                    </td>
                                </template>

                                <!-- Brand Data -->
                                <template v-else-if="currentView === 'brand'">
                                    <td class="px-6 py-4 font-bold text-text-primary">{{ item.brand }}</td>
                                    <td class="px-6 py-4 text-center font-black text-purple-500">{{ item.qty }}</td>
                                </template>

                                <!-- Type Data -->
                                <template v-else-if="currentView === 'type'">
                                    <td class="px-6 py-4 text-text-secondary">{{ item.brand }}</td>
                                    <td class="px-6 py-4 font-bold text-text-primary">{{ item.name }}</td>
                                    <td class="px-6 py-4 text-center font-black text-emerald-500">{{ item.qty }}</td>
                                </template>

                                <!-- Condition Data -->
                                <template v-else-if="currentView === 'condition'">
                                    <td class="px-6 py-4">
                                        <span class="px-3 py-1 rounded-lg text-xs font-bold border" :class="getConditionClass(item.condition)">
                                            {{ formatCondition(item.condition) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-center font-black text-orange-500">{{ item.qty }}</td>
                                </template>

                                <template v-else-if="currentView === 'distributor'">
                                    <td class="px-6 py-4 font-bold text-text-primary">{{ item.distributor || 'Tanpa Distributor' }}</td>
                                    <td class="px-6 py-4 text-center font-black text-indigo-500">{{ item.qty }}</td>
                                </template>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { 
    Loader2, ChevronDown, Calendar, Trophy, ArrowLeft, RefreshCw, 
    TrendingUp, Users, Layers, Smartphone, Tag, RotateCcw,
    Search, ListFilter, ChevronRight, Truck, MapPin, Globe
} from 'lucide-vue-next'
import axios from '../../api/axios'
import { useAuthStore } from '../../store/auth'

const authStore = useAuthStore()
const storageBaseUrl = computed(() => authStore.storageBaseUrl)

const branches = ref([])
const onlineShops = ref([])
const locationType = ref('branch')

const loading = ref(false)
const currentView = ref('menu')
const searchQuery = ref('')
const selectedPeriod = ref('daily')
const sortConfig = ref({
    order: 'num-desc' // 'num-desc', 'num-asc', 'alpha-asc', 'alpha-desc'
})

const viewLabels = {
    'revenue': 'Ringkasan Penjualan Harian',
    'sales': 'Peringkat Berdasarkan Unit Terjual',
    'brand': 'Penjualan Berdasarkan Brand',
    'type': 'Penjualan Berdasarkan Tipe Produk',
    'condition': 'Penjualan Berdasarkan Kondisi',
    'activity': 'Peringkat Angkat Barang & Refund',
    'distributor': 'Penjualan Berdasarkan Distributor'
}

const months = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

const getLogicalDate = () => {
    const d = new Date();
    if (d.getHours() < 5) {
        d.setDate(d.getDate() - 1);
    }
    return d;
};

const logicalToday = getLogicalDate();
const currentYear = logicalToday.getFullYear();
const years = Array.from({ length: 5 }, (_, i) => currentYear - 2 + i);

const selectedMonth = ref(logicalToday.getMonth() + 1);
const selectedYear = ref(currentYear);

const isRestricted = computed(() => {
    const role = (authStore.userRole || '').toLowerCase();
    return !['audit', 'super_admin', 'admin_produk', 'leader', 'owner', 'analist'].some(r => role.includes(r));
});

const availableMonths = computed(() => {
    if (!isRestricted.value) {
        return months.map((m, i) => ({ name: m, value: i + 1 }));
    }
    
    const d = getLogicalDate();
    const currentMonth = d.getMonth() + 1; // 1-indexed
    
    // For restricted, show current month and last month
    // Handle the case where last month was in the previous year
    // Since year is locked to currentYear, showing last month's name but the same year might show future data if it's Dec in Jan.
    // However, the requirement is "this month and last month".
    const lastDate = new Date(d.getFullYear(), d.getMonth() - 1, 1);
    const lastMonth = lastDate.getMonth() + 1;
    
    return months.map((m, i) => ({ name: m, value: i + 1 }))
        .filter(m => m.value === currentMonth || m.value === lastMonth);
});

const availableYears = computed(() => {
    if (!isRestricted.value) return years;
    return [currentYear];
});

const todayDate = computed(() => getTodayLocal());
const minDate = computed(() => {
    if (!isRestricted.value) return null;
    const d = getLogicalDate();
    d.setDate(d.getDate() - 1); // Allow today and yesterday
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
});


const salesData = ref({
    daily_sales: { data: [] },
    brand_sales: [],
    type_sales: [],
    condition_sales: [],
    distributor_sales: [],
    cs_sales: [],
    daily_history: []
})

const getTodayLocal = () => {
    const d = getLogicalDate();
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

const filters = ref({
    start_date: getTodayLocal(),
    end_date: getTodayLocal(),
    branch_id: null,
    online_shop_id: null
})

const formattedDateDisplay = computed(() => {
    if (!filters.value.start_date) return 'Pilih Tanggal';
    const date = new Date(filters.value.start_date);
    return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
})

// Main Data Processor
const sortedData = computed(() => {
    let base = []
    let numKey = ''
    let alphaKey = ''

    if (currentView.value === 'revenue') {
        base = [...(salesData.value.daily_history || [])]
        numKey = 'total_omset'
        alphaKey = 'reporting_date'
    } else if (currentView.value === 'sales' || currentView.value === 'activity') {
        base = [...(salesData.value.cs_sales || [])]
        numKey = currentView.value === 'sales' ? 'total_sales' : 'total_refund';
        alphaKey = 'cs_name'
    } else if (currentView.value === 'brand') {
        base = [...(salesData.value.brand_sales || [])]
        numKey = 'qty'
        alphaKey = 'brand'
    } else if (currentView.value === 'type') {
        base = [...(salesData.value.type_sales || [])]
        numKey = 'qty'
        alphaKey = 'name'
    } else if (currentView.value === 'condition') {
        base = [...(salesData.value.condition_sales || [])]
        numKey = 'qty'
        alphaKey = 'condition'
    } else if (currentView.value === 'distributor') {
        base = [...(salesData.value.distributor_sales || [])]
        numKey = 'qty'
        alphaKey = 'distributor'
    }

    // Filter by Query
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase()
        base = base.filter(item => {
            if (alphaKey && item[alphaKey]) return item[alphaKey].toLowerCase().includes(q)
            if (item.name) return item.name.toLowerCase().includes(q)
            if (item.cs_name) return item.cs_name.toLowerCase().includes(q)
            return false
        })
    }

    // Sort
    const { order } = sortConfig.value
    base.sort((a, b) => {
        if (order === 'num-desc') return (b[numKey] || 0) - (a[numKey] || 0)
        if (order === 'num-asc') return (a[numKey] || 0) - (b[numKey] || 0)
        if (order === 'alpha-asc') return (a[alphaKey] || '').localeCompare(b[alphaKey] || '')
        if (order === 'alpha-desc') return (b[alphaKey] || '').localeCompare(a[alphaKey] || '')
        return 0
    })

    return base
})

const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(value || 0)
}

const formatDateString = (dateStr) => {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
}

const getRankBadgeClass = (idx) => {
    if (idx === 0) return 'bg-amber-400 text-amber-950 border-2 border-amber-300'
    if (idx === 1) return 'bg-slate-300 text-slate-800 border-2 border-slate-200 shadow-inner'
    if (idx === 2) return 'bg-orange-800 text-orange-50 dark:bg-orange-700 border-2 border-orange-600'
    return 'bg-gray-100 dark:bg-surface-700 text-text-secondary border border-gray-200 dark:border-surface-600'
}

const getConditionClass = (cond) => {
    const maps = {
        'new': 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20',
        'ex_ibox': 'bg-blue-500/10 text-blue-500 border-blue-500/20',
        'second': 'bg-amber-500/10 text-amber-500 border-amber-500/20'
    }
    return maps[cond] || 'bg-gray-500/10 text-gray-500 border-gray-500/20'
}

const formatCondition = (cond) => {
    const maps = { 'new': 'Baru', 'ex_ibox': 'Ex iBox', 'second': 'Second' }
    return maps[cond] || cond
}

const navigateTo = (view) => {
    currentView.value = view
    searchQuery.value = ''
    // Set default sort based on view
    if (view === 'revenue' || view === 'sales') sortConfig.value.order = 'num-desc'
    else sortConfig.value.order = 'num-desc'
}

const goBack = () => {
    currentView.value = 'menu'
    searchQuery.value = ''
}

const handlePeriodChange = () => {
    if (selectedPeriod.value === 'daily') {
        const today = getTodayLocal();
        filters.value.start_date = today;
        filters.value.end_date = today;
    } else {
        handleMonthChange();
    }
    // No need to call fetchData here as watchers will handle it
}

const handleDateChange = () => {
    filters.value.end_date = filters.value.start_date;
    fetchData();
}

const handleMonthChange = () => {
    const year = selectedYear.value;
    const month = selectedMonth.value;
    const endDate = new Date(year, month, 0);
    const pad = (n) => n < 10 ? '0' + n : n;
    filters.value.start_date = `${year}-${pad(month)}-01`;
    filters.value.end_date = `${year}-${pad(month)}-${pad(endDate.getDate())}`;
    fetchData();
}

const fetchData = async () => {
    loading.value = true
    try {
        const params = { 
            start_date: filters.value.start_date,
            end_date: filters.value.end_date,
            branch_id: filters.value.branch_id,
            online_shop_id: filters.value.online_shop_id
        };
        const response = await axios.get('/audit/sales', { params })
        salesData.value = response.data
    } catch (error) {
        console.error('Error fetching ranking data:', error)
    } finally {
        loading.value = false
    }
}

const handleLocationTypeChange = () => {
    filters.value.branch_id = null;
    filters.value.online_shop_id = null;
    fetchData();
}

const fetchLocations = async () => {
    try {
        const [bRes, oRes] = await Promise.all([
            axios.get('/branches'),
            axios.get('/online-shops')
        ]);
        branches.value = bRes.data;
        onlineShops.value = oRes.data;
    } catch (error) {
        console.error('Error fetching locations:', error);
    }
}

onMounted(() => {
    fetchLocations()
    fetchData()
})
</script>

<style scoped>
.animate-in {
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(4px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
