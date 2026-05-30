<template>
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="flex flex-col gap-6 mb-8">
            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-3 mb-1">
                        <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center shadow-lg shadow-amber-500/20">
                            <Trophy :size="20" class="text-white" />
                        </div>
                        <h1 class="text-2xl sm:text-3xl font-black text-text-primary tracking-tight">Liga Cabang</h1>
                    </div>
                    <p class="text-sm text-text-secondary ml-[52px]">Atur zona liga cabang per bulan</p>
                </div>

                <!-- Period Controls -->
                <div class="flex items-center gap-2 flex-wrap">
                    <div class="flex items-center bg-surface-100 dark:bg-surface-800 rounded-2xl p-1">
                        <button @click="prevMonth" class="w-9 h-9 rounded-xl flex items-center justify-center hover:bg-white dark:hover:bg-surface-700 transition-all text-text-secondary hover:text-text-primary">
                            <ChevronLeft :size="16" />
                        </button>
                        <span class="px-4 py-1.5 font-bold text-sm text-text-primary min-w-[130px] text-center">
                            {{ monthNames[selectedMonth - 1] }} {{ selectedYear }}
                        </span>
                        <button @click="nextMonth" class="w-9 h-9 rounded-xl flex items-center justify-center hover:bg-white dark:hover:bg-surface-700 transition-all text-text-secondary hover:text-text-primary">
                            <ChevronRight :size="16" />
                        </button>
                    </div>
                    <button @click="showCopyModal = true" class="h-9 px-4 bg-surface-100 dark:bg-surface-800 hover:bg-surface-200 dark:hover:bg-surface-700 rounded-xl text-xs font-bold text-text-secondary hover:text-text-primary transition-all flex items-center gap-1.5">
                        <Copy :size="13" /> Salin
                    </button>
                </div>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex flex-col items-center justify-center py-24 gap-3">
            <Loader2 class="animate-spin text-primary-500" :size="28" />
            <span class="text-xs text-text-secondary font-medium">Memuat data liga...</span>
        </div>

        <!-- Liga Grid -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
            <div v-for="liga in leagueConfig" :key="liga.key"
                class="group rounded-[20px] overflow-hidden transition-all duration-300 hover:shadow-xl border"
                :class="liga.cardClass">
                
                <!-- Liga Header -->
                <div class="relative px-5 py-4 overflow-hidden" :class="liga.headerBg">
                    <!-- Background Pattern -->
                    <div class="absolute inset-0 opacity-10">
                        <div class="absolute -right-4 -top-4 w-24 h-24 rounded-full border-[6px]" :class="liga.patternClass"></div>
                        <div class="absolute -left-2 -bottom-2 w-16 h-16 rounded-full border-[4px]" :class="liga.patternClass"></div>
                    </div>
                    
                    <div class="relative flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl flex items-center justify-center" :class="liga.iconBg">
                                <component :is="liga.icon" :size="16" class="text-white" />
                            </div>
                            <div>
                                <span class="font-black text-xs uppercase tracking-[0.15em] block" :class="liga.textClass">{{ liga.label }}</span>
                            </div>
                        </div>
                        <div class="w-7 h-7 rounded-full flex items-center justify-center text-[11px] font-black" :class="liga.countClass">
                            {{ (assignments[liga.key] || []).length }}
                        </div>
                    </div>
                </div>

                <!-- Branch List -->
                <div class="px-3 py-3 space-y-1 min-h-[120px] max-h-[320px] overflow-y-auto custom-scrollbar">
                    <div v-if="!(assignments[liga.key] || []).length"
                        class="flex flex-col items-center justify-center py-8 text-text-secondary/50">
                        <Package :size="24" class="mb-2 opacity-50" />
                        <span class="text-[11px] font-medium">Belum ada cabang</span>
                    </div>
                    <div v-for="(item, idx) in (assignments[liga.key] || [])" :key="item.id"
                        class="flex items-center justify-between px-3 py-2 rounded-xl transition-all group/item hover:bg-surface-100 dark:hover:bg-surface-700/50">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="w-5 h-5 rounded-md flex items-center justify-center text-[10px] font-bold shrink-0" :class="liga.numClass">
                                {{ idx + 1 }}
                            </span>
                            <span class="text-xs font-semibold text-text-primary truncate">{{ item.branch?.name || '-' }}</span>
                        </div>
                        <button @click="removeAssignment(item)"
                            class="opacity-0 group-hover/item:opacity-100 w-6 h-6 rounded-lg flex items-center justify-center hover:bg-red-100 dark:hover:bg-red-900/30 text-red-400 hover:text-red-500 transition-all shrink-0">
                            <X :size="12" />
                        </button>
                    </div>
                </div>

                <!-- Add Branch -->
                <div class="px-3 pb-3">
                    <select @change="assignBranch($event, liga.key)"
                        class="w-full text-[11px] font-medium px-3 py-2.5 rounded-xl border border-dashed transition-all cursor-pointer appearance-none bg-transparent hover:border-solid focus:outline-none focus:ring-2 focus:ring-offset-1"
                        :class="liga.selectClass">
                        <option value="">+ Tambah cabang</option>
                        <option v-for="b in unassigned" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Unassigned -->
        <div v-if="!loading && unassigned.length > 0" class="rounded-2xl border border-surface-200 dark:border-surface-700 bg-surface-50/50 dark:bg-surface-800/30 p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xs font-black text-text-secondary uppercase tracking-[0.15em] flex items-center gap-2">
                    <AlertCircle :size="14" class="text-amber-500" /> Belum Diatur
                </h3>
                <span class="text-[10px] font-bold text-text-secondary bg-surface-200 dark:bg-surface-700 px-2 py-0.5 rounded-full">{{ unassigned.length }} cabang</span>
            </div>
            <div class="flex flex-wrap gap-1.5">
                <span v-for="b in unassigned" :key="b.id"
                    class="px-2.5 py-1.5 bg-white dark:bg-surface-700 border border-surface-200 dark:border-surface-600 rounded-lg text-[11px] font-medium text-text-primary hover:border-primary-300 hover:bg-primary-50 dark:hover:bg-primary-900/20 transition-all cursor-default">
                    {{ b.name }}
                </span>
            </div>
        </div>

        <!-- Copy Modal -->
        <Teleport v-if="showCopyModal" to="body">
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="showCopyModal = false">
                <div class="bg-white dark:bg-surface-800 rounded-3xl p-7 w-full max-w-sm shadow-2xl border border-surface-200 dark:border-surface-700">
                    <div class="flex items-center gap-3 mb-5">
                        <div class="w-10 h-10 rounded-2xl bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center">
                            <Copy :size="18" class="text-primary-600" />
                        </div>
                        <div>
                            <h3 class="text-base font-black text-text-primary">Salin Liga</h3>
                            <p class="text-[11px] text-text-secondary">Salin pengaturan dari bulan lain</p>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <div>
                            <label class="text-[10px] font-black text-text-secondary uppercase tracking-widest mb-1.5 block">Dari Bulan</label>
                            <div class="flex gap-2">
                                <select v-model="copyFrom.month" class="flex-1 px-3 py-2.5 rounded-xl border border-surface-200 dark:border-surface-600 bg-surface-50 dark:bg-surface-700 text-xs font-medium focus:outline-none focus:ring-2 focus:ring-primary-500">
                                    <option v-for="(name, idx) in monthNames" :key="idx" :value="idx + 1">{{ name }}</option>
                                </select>
                                <select v-model="copyFrom.year" class="w-24 px-3 py-2.5 rounded-xl border border-surface-200 dark:border-surface-600 bg-surface-50 dark:bg-surface-700 text-xs font-medium focus:outline-none focus:ring-2 focus:ring-primary-500">
                                    <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="pt-1">
                            <div class="flex items-center gap-2 text-[11px] text-text-secondary bg-surface-100 dark:bg-surface-700/50 rounded-xl px-3 py-2">
                                <ArrowRight :size="12" />
                                <span>Ke: <strong class="text-text-primary">{{ monthNames[selectedMonth - 1] }} {{ selectedYear }}</strong></span>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-3 mt-6">
                        <button @click="showCopyModal = false" class="flex-1 py-2.5 bg-surface-100 dark:bg-surface-700 hover:bg-surface-200 dark:hover:bg-surface-600 rounded-xl text-xs font-bold transition-all">Batal</button>
                        <button @click="copyAssignments" :disabled="copying" class="flex-1 py-2.5 bg-primary-600 hover:bg-primary-500 text-white rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-2 disabled:opacity-50">
                            <Loader2 v-if="copying" class="animate-spin" :size="13" />
                            <span>{{ copying ? 'Menyalin...' : 'Salin Sekarang' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { ChevronLeft, ChevronRight, Copy, Loader2, X, AlertCircle, Trophy, Star, AlertTriangle, MinusCircle, Package, ArrowRight } from 'lucide-vue-next'
import axios from '../../api/axios'
import { useToast } from '../../composables/useToast'

const toast = useToast()

const loading = ref(false)
const selectedMonth = ref(new Date().getMonth() + 1)
const selectedYear = ref(new Date().getFullYear())
const assignments = ref({})
const unassigned = ref([])
const showCopyModal = ref(false)
const copying = ref(false)
const copyFrom = ref({ month: new Date().getMonth() + 1, year: new Date().getFullYear() })

const monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']
const yearOptions = [2024, 2025, 2026, 2027, 2028, 2029, 2030]

const leagueConfig = [
    {
        key: 'liga_1', label: 'Liga 1', icon: Trophy,
        cardClass: 'border-amber-200/80 dark:border-amber-800/40 bg-white dark:bg-surface-800',
        headerBg: 'bg-gradient-to-br from-amber-50 to-orange-50 dark:from-amber-950/30 dark:to-orange-950/20',
        patternClass: 'border-amber-300/50',
        iconBg: 'bg-gradient-to-br from-amber-400 to-orange-500 shadow-md shadow-amber-400/30',
        textClass: 'text-amber-700 dark:text-amber-400',
        countClass: 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400',
        numClass: 'bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400',
        selectClass: 'border-amber-300 dark:border-amber-800 text-amber-600 dark:text-amber-400 hover:border-amber-400 focus:ring-amber-400',
    },
    {
        key: 'liga_2', label: 'Liga 2', icon: Star,
        cardClass: 'border-blue-200/80 dark:border-blue-800/40 bg-white dark:bg-surface-800',
        headerBg: 'bg-gradient-to-br from-blue-50 to-cyan-50 dark:from-blue-950/30 dark:to-cyan-950/20',
        patternClass: 'border-blue-300/50',
        iconBg: 'bg-gradient-to-br from-blue-500 to-cyan-500 shadow-md shadow-blue-400/30',
        textClass: 'text-blue-700 dark:text-blue-400',
        countClass: 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-400',
        numClass: 'bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400',
        selectClass: 'border-blue-300 dark:border-blue-800 text-blue-600 dark:text-blue-400 hover:border-blue-400 focus:ring-blue-400',
    },
    {
        key: 'zona_merah', label: 'Zona Merah', icon: AlertTriangle,
        cardClass: 'border-red-200/80 dark:border-red-800/40 bg-white dark:bg-surface-800',
        headerBg: 'bg-gradient-to-br from-red-50 to-rose-50 dark:from-red-950/30 dark:to-rose-950/20',
        patternClass: 'border-red-300/50',
        iconBg: 'bg-gradient-to-br from-red-500 to-rose-600 shadow-md shadow-red-400/30',
        textClass: 'text-red-700 dark:text-red-400',
        countClass: 'bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-400',
        numClass: 'bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400',
        selectClass: 'border-red-300 dark:border-red-800 text-red-600 dark:text-red-400 hover:border-red-400 focus:ring-red-400',
    },
    {
        key: 'non_liga', label: 'Non Liga', icon: MinusCircle,
        cardClass: 'border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800',
        headerBg: 'bg-gradient-to-br from-surface-50 to-surface-100 dark:from-surface-800 dark:to-surface-750',
        patternClass: 'border-surface-300/50',
        iconBg: 'bg-gradient-to-br from-surface-400 to-surface-500 shadow-md shadow-surface-400/20',
        textClass: 'text-text-secondary',
        countClass: 'bg-surface-200 dark:bg-surface-700 text-text-secondary',
        numClass: 'bg-surface-100 dark:bg-surface-700 text-text-secondary',
        selectClass: 'border-surface-300 dark:border-surface-600 text-text-secondary hover:border-surface-400 focus:ring-surface-400',
    },
]

const prevMonth = () => {
    if (selectedMonth.value === 1) { selectedMonth.value = 12; selectedYear.value-- }
    else { selectedMonth.value-- }
}

const nextMonth = () => {
    if (selectedMonth.value === 12) { selectedMonth.value = 1; selectedYear.value++ }
    else { selectedMonth.value++ }
}

const fetchData = async () => {
    loading.value = true
    try {
        const res = await axios.get('/leagues', { params: { month: selectedMonth.value, year: selectedYear.value } })
        assignments.value = res.data.data.assignments || {}
        unassigned.value = res.data.data.unassigned || []
    } catch (e) {
        toast.error('Gagal memuat data liga')
    } finally {
        loading.value = false
    }
}

const assignBranch = async (event, league) => {
    const branchId = event.target.value
    if (!branchId) return
    event.target.value = ''
    try {
        await axios.post('/leagues', { branch_id: branchId, league, month: selectedMonth.value, year: selectedYear.value })
        toast.success('Cabang ditambahkan')
        fetchData()
    } catch (e) {
        toast.error(e.response?.data?.message || 'Gagal menambahkan')
    }
}

const removeAssignment = async (item) => {
    try {
        await axios.delete(`/leagues/${item.id}`)
        toast.success('Cabang dihapus dari liga')
        fetchData()
    } catch (e) {
        toast.error('Gagal menghapus')
    }
}

const copyAssignments = async () => {
    copying.value = true
    try {
        await axios.post('/leagues/copy', { from_month: copyFrom.value.month, from_year: copyFrom.value.year, to_month: selectedMonth.value, to_year: selectedYear.value })
        toast.success('Berhasil disalin')
        showCopyModal.value = false
        fetchData()
    } catch (e) {
        toast.error(e.response?.data?.message || 'Gagal menyalin')
    } finally {
        copying.value = false
    }
}

watch([selectedMonth, selectedYear], fetchData)
onMounted(fetchData)
</script>
