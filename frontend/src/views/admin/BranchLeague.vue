<template>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl sm:text-3xl font-black text-text-primary tracking-tight">Liga Cabang</h1>
                <p class="text-sm text-text-secondary mt-1">Atur zona liga cabang per bulan</p>
            </div>

            <!-- Month/Year Selector -->
            <div class="flex items-center gap-3">
                <button @click="prevMonth" class="p-2 rounded-xl bg-surface-100 dark:bg-surface-700 hover:bg-surface-200 dark:hover:bg-surface-600 transition-all">
                    <ChevronLeft :size="18" />
                </button>
                <div class="px-4 py-2 bg-surface-100 dark:bg-surface-700 rounded-xl font-bold text-sm min-w-[140px] text-center">
                    {{ monthNames[selectedMonth - 1] }} {{ selectedYear }}
                </div>
                <button @click="nextMonth" class="p-2 rounded-xl bg-surface-100 dark:bg-surface-700 hover:bg-surface-200 dark:hover:bg-surface-600 transition-all">
                    <ChevronRight :size="18" />
                </button>
                <button @click="showCopyModal = true" class="ml-2 px-3 py-2 bg-primary-600 text-white rounded-xl text-xs font-bold hover:bg-primary-500 transition-all flex items-center gap-1.5">
                    <Copy :size="14" /> Salin Bulan
                </button>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex justify-center py-20">
            <Loader2 class="animate-spin text-primary-500" :size="32" />
        </div>

        <!-- Liga Cards -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5 mb-8">
            <div v-for="liga in leagueConfig" :key="liga.key"
                class="rounded-2xl border-2 overflow-hidden transition-all hover:shadow-lg"
                :class="liga.borderClass">
                <!-- Liga Header -->
                <div class="px-5 py-4 flex items-center justify-between" :class="liga.headerClass">
                    <div class="flex items-center gap-2.5">
                        <component :is="liga.icon" :size="20" class="shrink-0" />
                        <span class="font-black text-sm uppercase tracking-wider">{{ liga.label }}</span>
                    </div>
                    <span class="text-xs font-bold opacity-80 bg-white/20 px-2 py-0.5 rounded-full">
                        {{ (assignments[liga.key] || []).length }}
                    </span>
                </div>

                <!-- Branch List -->
                <div class="p-3 space-y-1.5 max-h-[400px] overflow-y-auto bg-surface-50 dark:bg-surface-800/50">
                    <div v-if="!(assignments[liga.key] || []).length"
                        class="text-center py-8 text-text-secondary text-xs">
                        Belum ada cabang
                    </div>
                    <div v-for="item in (assignments[liga.key] || [])" :key="item.id"
                        class="flex items-center justify-between px-3 py-2.5 bg-white dark:bg-surface-700 rounded-xl shadow-sm group hover:shadow-md transition-all">
                        <span class="text-xs font-bold text-text-primary truncate">{{ item.branch?.name || '-' }}</span>
                        <button @click="removeAssignment(item)"
                            class="opacity-0 group-hover:opacity-100 p-1 rounded-lg hover:bg-red-100 dark:hover:bg-red-900/30 text-red-500 transition-all">
                            <X :size="14" />
                        </button>
                    </div>
                </div>

                <!-- Drop Zone -->
                <div class="px-3 pb-3 pt-1 bg-surface-50 dark:bg-surface-800/50">
                    <select @change="assignBranch($event, liga.key)"
                        class="w-full text-xs px-3 py-2.5 rounded-xl border-2 border-dashed border-surface-300 dark:border-surface-600 bg-transparent text-text-secondary focus:border-primary-500 focus:outline-none transition-all cursor-pointer">
                        <option value="">+ Tambah cabang...</option>
                        <option v-for="b in unassigned" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Unassigned Section -->
        <div v-if="unassigned.length > 0" class="bg-surface-100 dark:bg-surface-800 rounded-2xl p-5">
            <h3 class="text-sm font-black text-text-secondary uppercase tracking-wider mb-3 flex items-center gap-2">
                <AlertCircle :size="16" /> Belum Diatur ({{ unassigned.length }} cabang)
            </h3>
            <div class="flex flex-wrap gap-2">
                <span v-for="b in unassigned" :key="b.id"
                    class="px-3 py-1.5 bg-white dark:bg-surface-700 rounded-lg text-xs font-semibold text-text-primary shadow-sm">
                    {{ b.name }}
                </span>
            </div>
        </div>

        <!-- Copy Modal -->
        <Teleport to="body">
            <div v-if="showCopyModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" @click.self="showCopyModal = false">
                <div class="bg-white dark:bg-surface-800 rounded-2xl p-6 w-full max-w-sm shadow-2xl">
                    <h3 class="text-lg font-black text-text-primary mb-4">Salin dari Bulan Lain</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="text-xs font-bold text-text-secondary uppercase">Bulan Sumber</label>
                            <div class="flex gap-2 mt-1">
                                <select v-model="copyFrom.month" class="flex-1 px-3 py-2 rounded-xl border border-surface-300 dark:border-surface-600 bg-surface-50 dark:bg-surface-700 text-sm">
                                    <option v-for="(name, idx) in monthNames" :key="idx" :value="idx + 1">{{ name }}</option>
                                </select>
                                <select v-model="copyFrom.year" class="w-24 px-3 py-2 rounded-xl border border-surface-300 dark:border-surface-600 bg-surface-50 dark:bg-surface-700 text-sm">
                                    <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-3 mt-6">
                        <button @click="showCopyModal = false" class="flex-1 py-2.5 bg-surface-200 dark:bg-surface-700 rounded-xl text-sm font-bold">Batal</button>
                        <button @click="copyAssignments" :disabled="copying" class="flex-1 py-2.5 bg-primary-600 text-white rounded-xl text-sm font-bold hover:bg-primary-500 transition-all flex items-center justify-center gap-2">
                            <Loader2 v-if="copying" class="animate-spin" :size="14" />
                            <span>Salin</span>
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { ChevronLeft, ChevronRight, Copy, Loader2, X, AlertCircle, Trophy, Star, AlertTriangle, MinusCircle } from 'lucide-vue-next'
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
    { key: 'liga_1', label: 'Liga 1', icon: Trophy, headerClass: 'bg-gradient-to-r from-amber-500 to-yellow-400 text-white', borderClass: 'border-amber-400 dark:border-amber-600' },
    { key: 'liga_2', label: 'Liga 2', icon: Star, headerClass: 'bg-gradient-to-r from-blue-500 to-cyan-400 text-white', borderClass: 'border-blue-400 dark:border-blue-600' },
    { key: 'zona_merah', label: 'Zona Merah', icon: AlertTriangle, headerClass: 'bg-gradient-to-r from-red-600 to-rose-500 text-white', borderClass: 'border-red-400 dark:border-red-600' },
    { key: 'non_liga', label: 'Non Liga', icon: MinusCircle, headerClass: 'bg-gradient-to-r from-gray-500 to-gray-400 text-white', borderClass: 'border-gray-300 dark:border-gray-600' },
]

const prevMonth = () => {
    if (selectedMonth.value === 1) {
        selectedMonth.value = 12
        selectedYear.value--
    } else {
        selectedMonth.value--
    }
}

const nextMonth = () => {
    if (selectedMonth.value === 12) {
        selectedMonth.value = 1
        selectedYear.value++
    } else {
        selectedMonth.value++
    }
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
        await axios.post('/leagues', {
            branch_id: branchId,
            league,
            month: selectedMonth.value,
            year: selectedYear.value,
        })
        toast.success('Cabang berhasil ditambahkan')
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
        await axios.post('/leagues/copy', {
            from_month: copyFrom.value.month,
            from_year: copyFrom.value.year,
            to_month: selectedMonth.value,
            to_year: selectedYear.value,
        })
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
