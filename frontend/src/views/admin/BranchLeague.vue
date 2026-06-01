<template>
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-amber-400 via-orange-500 to-red-500 flex items-center justify-center shadow-lg shadow-orange-500/25">
                    <Trophy :size="22" class="text-white" />
                </div>
                <div>
                    <h1 class="text-xl sm:text-2xl font-black text-text-primary">Liga Cabang</h1>
                    <p class="text-[11px] text-text-secondary font-medium">Klasifikasi performa cabang per bulan</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <div class="flex items-center bg-white dark:bg-surface-800 border border-surface-200 dark:border-surface-700 rounded-full px-1 py-1 shadow-sm">
                    <button @click="prevMonth" class="w-8 h-8 rounded-full flex items-center justify-center hover:bg-surface-100 dark:hover:bg-surface-700 transition-all">
                        <ChevronLeft :size="15" class="text-text-secondary" />
                    </button>
                    <span class="px-3 text-sm font-bold text-text-primary min-w-[120px] text-center select-none">
                        {{ monthNames[selectedMonth - 1] }} {{ selectedYear }}
                    </span>
                    <button @click="nextMonth" class="w-8 h-8 rounded-full flex items-center justify-center hover:bg-surface-100 dark:hover:bg-surface-700 transition-all">
                        <ChevronRight :size="15" class="text-text-secondary" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex flex-col items-center justify-center py-24 gap-3">
            <div class="w-12 h-12 rounded-full border-[3px] border-surface-200 border-t-primary-500 animate-spin"></div>
            <span class="text-xs text-text-secondary font-medium mt-2">Memuat data liga...</span>
        </div>

        <!-- Liga Grid -->
        <div v-else>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <!-- Liga 1 & Liga 2 (top row, bigger) -->
                <div v-for="liga in leagueConfig.slice(0, 2)" :key="liga.key"
                    class="rounded-3xl overflow-hidden shadow-sm hover:shadow-lg transition-all duration-300 border flex flex-col"
                    :class="liga.cardBorder">
                    <!-- Header -->
                    <div class="px-5 py-4 flex items-center justify-between" :class="liga.headerBg">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl flex items-center justify-center shadow-sm" :class="liga.iconBg">
                                <component :is="liga.icon" :size="18" class="text-white" />
                            </div>
                            <span class="font-black text-sm tracking-wide" :class="liga.titleColor">{{ liga.label }}</span>
                        </div>
                        <span class="text-lg font-black" :class="liga.titleColor">{{ (assignments[liga.key] || []).length }}</span>
                    </div>
                    <!-- List -->
                    <div class="bg-white dark:bg-surface-800 px-3 py-2 flex-1 max-h-[320px] overflow-y-auto custom-scrollbar">
                        <div v-if="!(assignments[liga.key] || []).length" class="flex items-center justify-center py-8 text-text-secondary/40">
                            <span class="text-xs font-medium">Kosong</span>
                        </div>
                        <div v-for="(item, idx) in (assignments[liga.key] || [])" :key="item.id"
                            class="flex items-center justify-between px-3 py-2 rounded-xl hover:bg-surface-50 dark:hover:bg-surface-700/40 group/item transition-all">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="text-[10px] font-black w-5 text-center" :class="liga.numColor">{{ idx + 1 }}</span>
                                <span class="text-xs font-semibold text-text-primary truncate">{{ item.branch?.name }}</span>
                            </div>
                            <button @click="removeAssignment(item)" class="opacity-0 group-hover/item:opacity-100 text-red-400 hover:text-red-600 transition-all">
                                <X :size="13" />
                            </button>
                        </div>
                    </div>
                    <!-- Add -->
                    <div class="bg-white dark:bg-surface-800 px-3 pb-3 border-t border-surface-100 dark:border-surface-700">
                        <select @change="assignBranch($event, liga.key)"
                            class="w-full mt-2 text-xs px-3 py-2 rounded-xl bg-surface-50 dark:bg-surface-700 border-0 font-medium text-text-secondary focus:outline-none focus:ring-2 cursor-pointer"
                            :class="liga.ringColor">
                            <option value="">+ Tambah cabang</option>
                            <option v-for="b in unassigned" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <!-- Zona Merah & Non Liga (bottom row) -->
                <div v-for="liga in leagueConfig.slice(2)" :key="liga.key"
                    class="rounded-3xl overflow-hidden shadow-sm hover:shadow-lg transition-all duration-300 border flex flex-col"
                    :class="liga.cardBorder">
                    <div class="px-5 py-4 flex items-center justify-between" :class="liga.headerBg">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl flex items-center justify-center shadow-sm" :class="liga.iconBg">
                                <component :is="liga.icon" :size="18" class="text-white" />
                            </div>
                            <span class="font-black text-sm tracking-wide" :class="liga.titleColor">{{ liga.label }}</span>
                        </div>
                        <span class="text-lg font-black" :class="liga.titleColor">{{ (assignments[liga.key] || []).length }}</span>
                    </div>
                    <div class="bg-white dark:bg-surface-800 px-3 py-2 flex-1 max-h-[320px] overflow-y-auto custom-scrollbar">
                        <div v-if="!(assignments[liga.key] || []).length" class="flex items-center justify-center py-8 text-text-secondary/40">
                            <span class="text-xs font-medium">Kosong</span>
                        </div>
                        <div v-for="(item, idx) in (assignments[liga.key] || [])" :key="item.id"
                            class="flex items-center justify-between px-3 py-2 rounded-xl hover:bg-surface-50 dark:hover:bg-surface-700/40 group/item transition-all">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="text-[10px] font-black w-5 text-center" :class="liga.numColor">{{ idx + 1 }}</span>
                                <span class="text-xs font-semibold text-text-primary truncate">{{ item.branch?.name }}</span>
                            </div>
                            <button @click="removeAssignment(item)" class="opacity-0 group-hover/item:opacity-100 text-red-400 hover:text-red-600 transition-all">
                                <X :size="13" />
                            </button>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-surface-800 px-3 pb-3 border-t border-surface-100 dark:border-surface-700">
                        <select @change="assignBranch($event, liga.key)"
                            class="w-full mt-2 text-xs px-3 py-2 rounded-xl bg-surface-50 dark:bg-surface-700 border-0 font-medium text-text-secondary focus:outline-none focus:ring-2 cursor-pointer"
                            :class="liga.ringColor">
                            <option value="">+ Tambah cabang</option>
                            <option v-for="b in unassigned" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Unassigned - Collapsible -->
            <div v-if="unassigned.length > 0" class="rounded-2xl bg-white dark:bg-surface-800 border border-surface-200 dark:border-surface-700 shadow-sm overflow-hidden">
                <button @click="showUnassigned = !showUnassigned" class="w-full px-5 py-4 flex items-center justify-between hover:bg-surface-50 dark:hover:bg-surface-750 transition-all">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-lg bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                            <AlertCircle :size="14" class="text-amber-600 dark:text-amber-400" />
                        </div>
                        <span class="text-xs font-black text-text-primary uppercase tracking-wider">Belum Diatur</span>
                        <span class="text-[10px] font-bold text-white bg-amber-500 px-1.5 py-0.5 rounded-md">{{ unassigned.length }}</span>
                    </div>
                    <ChevronRight :size="14" class="text-text-secondary transition-transform" :class="{ 'rotate-90': showUnassigned }" />
                </button>
                <div v-show="showUnassigned" class="px-5 pb-4 border-t border-surface-100 dark:border-surface-700">
                    <div class="flex flex-wrap gap-1.5 pt-3">
                        <span v-for="b in unassigned" :key="b.id"
                            class="px-2.5 py-1.5 bg-surface-100 dark:bg-surface-700 rounded-lg text-[11px] font-semibold text-text-primary hover:bg-primary-50 hover:text-primary-700 dark:hover:bg-primary-900/20 dark:hover:text-primary-400 transition-all cursor-default">
                            {{ b.name }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

    </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { ChevronLeft, ChevronRight, X, AlertCircle, Trophy, Star, AlertTriangle, MinusCircle } from 'lucide-vue-next'
import axios from '../../api/axios'
import { useToast } from '../../composables/useToast'

const toast = useToast()
const loading = ref(false)
const selectedMonth = ref(new Date().getMonth() + 1)
const selectedYear = ref(new Date().getFullYear())
const assignments = ref({})
const unassigned = ref([])
const showUnassigned = ref(false)

const monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']

const leagueConfig = [
    {
        key: 'liga_1', label: 'Liga 1', icon: Trophy,
        cardBorder: 'border-amber-200 dark:border-amber-900/50',
        headerBg: 'bg-gradient-to-r from-amber-400 to-orange-400',
        iconBg: 'bg-white/20',
        titleColor: 'text-white',
        numColor: 'text-amber-500',
        ringColor: 'focus:ring-amber-400',
    },
    {
        key: 'liga_2', label: 'Liga 2', icon: Star,
        cardBorder: 'border-blue-200 dark:border-blue-900/50',
        headerBg: 'bg-gradient-to-r from-blue-500 to-indigo-500',
        iconBg: 'bg-white/20',
        titleColor: 'text-white',
        numColor: 'text-blue-500',
        ringColor: 'focus:ring-blue-400',
    },
    {
        key: 'zona_merah', label: 'Zona Merah', icon: AlertTriangle,
        cardBorder: 'border-red-200 dark:border-red-900/50',
        headerBg: 'bg-gradient-to-r from-red-500 to-rose-500',
        iconBg: 'bg-white/20',
        titleColor: 'text-white',
        numColor: 'text-red-500',
        ringColor: 'focus:ring-red-400',
    },
    {
        key: 'non_liga', label: 'Non Liga', icon: MinusCircle,
        cardBorder: 'border-surface-200 dark:border-surface-700',
        headerBg: 'bg-gradient-to-r from-surface-500 to-surface-600 dark:from-surface-600 dark:to-surface-700',
        iconBg: 'bg-white/20',
        titleColor: 'text-white',
        numColor: 'text-surface-400',
        ringColor: 'focus:ring-surface-400',
    },
]

const prevMonth = () => { if (selectedMonth.value === 1) { selectedMonth.value = 12; selectedYear.value-- } else { selectedMonth.value-- } }
const nextMonth = () => { if (selectedMonth.value === 12) { selectedMonth.value = 1; selectedYear.value++ } else { selectedMonth.value++ } }

const fetchData = async () => {
    loading.value = true
    try {
        const res = await axios.get('/leagues', { params: { month: selectedMonth.value, year: selectedYear.value } })
        assignments.value = res.data.data.assignments || {}
        unassigned.value = res.data.data.unassigned || []

        // Auto-copy from previous month if current month is empty
        const totalAssigned = Object.values(assignments.value).reduce((sum, arr) => sum + arr.length, 0)
        if (totalAssigned === 0) {
            // Calculate previous month
            let prevM = selectedMonth.value - 1
            let prevY = selectedYear.value
            if (prevM === 0) { prevM = 12; prevY-- }

            // Try to copy from previous month
            try {
                const copyRes = await axios.post('/leagues/copy', {
                    from_month: prevM,
                    from_year: prevY,
                    to_month: selectedMonth.value,
                    to_year: selectedYear.value
                })
                if (copyRes.data.success) {
                    // Refetch after auto-copy
                    const res2 = await axios.get('/leagues', { params: { month: selectedMonth.value, year: selectedYear.value } })
                    assignments.value = res2.data.data.assignments || {}
                    unassigned.value = res2.data.data.unassigned || []
                    toast.success('Otomatis disalin dari bulan sebelumnya')
                }
            } catch (e) {
                // Silent fail - previous month might also be empty
            }
        }
    } catch (e) { toast.error('Gagal memuat data liga') }
    finally { loading.value = false }
}

const assignBranch = async (event, league) => {
    const branchId = event.target.value
    if (!branchId) return
    event.target.value = ''
    try {
        await axios.post('/leagues', { branch_id: branchId, league, month: selectedMonth.value, year: selectedYear.value })
        toast.success('Cabang ditambahkan')
        fetchData()
    } catch (e) { toast.error(e.response?.data?.message || 'Gagal') }
}

const removeAssignment = async (item) => {
    try { await axios.delete(`/leagues/${item.id}`); toast.success('Dihapus'); fetchData() }
    catch (e) { toast.error('Gagal menghapus') }
}

watch([selectedMonth, selectedYear], fetchData)
onMounted(fetchData)
</script>
