<template>
    <div class="space-y-8">
        <!-- Section: Audit Profit -->
        <div
            class="bg-surface-50 dark:bg-surface-900/50 p-6 rounded-2xl border border-surface-200 dark:border-surface-700">
            <!-- Header & Filters -->
            <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 mb-6">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">Audit Profit</h2>
                    <p class="text-sm text-gray-500 mt-1">Analisis profit per transaksi penjualan</p>
                </div>

                <div class="flex flex-wrap items-center gap-3 w-full lg:w-auto">
                    <!-- Period Filter -->
                    <div class="relative min-w-[140px]">
                        <select v-model="selectedPeriod" @change="handlePeriodChange"
                            class="w-full appearance-none bg-white dark:bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl px-4 py-2.5 pr-10 text-sm font-medium focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all cursor-pointer">
                            <option value="daily">Harian</option>
                            <option value="monthly">Bulanan</option>
                        </select>
                        <ChevronDown :size="16"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
                    </div>

                    <!-- Daily: Date Picker -->
                    <div v-if="selectedPeriod === 'daily'" class="relative group">
                        <div
                            class="flex items-center gap-2 px-4 py-2.5 bg-white dark:bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl hover:border-primary-500 hover:ring-2 hover:ring-primary-500/10 transition-all cursor-pointer">
                            <Calendar :size="18"
                                class="text-gray-500 dark:text-gray-400 group-hover:text-primary-500" />
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-200 min-w-[100px]">
                                {{ formattedDateDisplay }}
                            </span>
                        </div>
                        <input type="date" v-model="filters.start_date" @change="handleDateChange"
                            @click="$event.target.showPicker()"
                            class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-20" />
                    </div>

                    <!-- Monthly: Month & Year Selectors -->
                    <div v-if="selectedPeriod === 'monthly'" class="flex items-center gap-2">
                        <div class="relative min-w-[140px]">
                            <select v-model="selectedMonth" @change="handleMonthChange"
                                class="w-full appearance-none bg-white dark:bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl px-4 py-2.5 pr-10 text-sm font-medium focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all cursor-pointer">
                                <option v-for="(m, i) in months" :key="i" :value="i + 1">{{ m }}</option>
                            </select>
                            <ChevronDown :size="16"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
                        </div>
                        <div class="relative min-w-[100px]">
                            <select v-model="selectedYear" @change="handleMonthChange"
                                class="w-full appearance-none bg-white dark:bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl px-4 py-2.5 pr-10 text-sm font-medium focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all cursor-pointer">
                                <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                            </select>
                            <ChevronDown :size="16"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
                        </div>
                    </div>

                    <!-- Branch Filter -->
                    <div v-if="canFilterBranch" class="relative min-w-[200px]">
                        <select v-model="selectedLocationKey" @change="fetchData"
                            class="w-full appearance-none bg-white dark:bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl px-4 py-2.5 pr-10 text-sm font-medium focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all cursor-pointer">
                            <option value="all">Semua Cabang/Toko</option>
                            <option v-for="loc in locations" :key="`${loc.type}:${loc.id}`"
                                :value="`${loc.type === 'branch' ? 'B' : 'S'}:${loc.id}`">
                                {{ loc.name }}
                            </option>
                        </select>
                        <ChevronDown :size="16"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
                    </div>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6" v-if="profitRecords.daily_sales.length > 0">
                <div class="bg-white dark:bg-surface-800 rounded-xl border border-gray-100 dark:border-surface-700 p-4">
                    <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Total Harga Jual</p>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">{{ formatCurrency(totalHargaJual) }}</p>
                </div>
                <div class="bg-white dark:bg-surface-800 rounded-xl border border-gray-100 dark:border-surface-700 p-4">
                    <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Total Harga Modal</p>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">{{ formatCurrency(totalHargaModal) }}</p>
                </div>
                <div class="bg-white dark:bg-surface-800 rounded-xl border border-gray-100 dark:border-surface-700 p-4">
                    <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Total Profit</p>
                    <p class="text-lg font-bold"
                        :class="totalProfit >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                        {{ formatCurrency(totalProfit) }}
                    </p>
                </div>
            </div>

            <!-- Table -->
            <div
                class="bg-white dark:bg-surface-800 rounded-2xl shadow-sm border border-gray-100 dark:border-surface-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead
                            class="text-xs font-semibold text-gray-500 uppercase bg-gray-50/50 dark:bg-surface-700/50 border-b border-gray-100 dark:border-surface-700">
                            <tr>
                                <th class="px-4 py-4">No</th>
                                <th class="px-4 py-4">Waktu</th>
                                <th class="px-4 py-4">No Pesanan</th>
                                <th class="px-4 py-4">Nama</th>
                                <th class="px-4 py-4">Kategori</th>
                                <th class="px-4 py-4">Tipe</th>
                                <th class="px-4 py-4">Qty</th>
                                <th class="px-4 py-4">Harga Jual</th>
                                <th class="px-4 py-4">Harga Modal</th>
                                <th class="px-4 py-4">Profit</th>
                                <th class="px-4 py-4 text-center">Cek Audit</th>
                                <th class="px-4 py-4 text-center">#</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-surface-700">
                            <tr v-if="loading">
                                <td colspan="12" class="px-6 py-12">
                                    <div class="flex flex-col items-center justify-center text-gray-500">
                                        <Loader2 class="w-8 h-8 animate-spin text-primary-500 mb-2" />
                                        <span class="text-sm font-medium">Memuat data profit...</span>
                                    </div>
                                </td>
                            </tr>
                            <tr v-else-if="profitRecords.daily_sales.length === 0">
                                <td colspan="12" class="px-6 py-12 text-center text-gray-500">
                                    <div class="flex flex-col items-center justify-center">
                                        <div
                                            class="w-12 h-12 bg-gray-100 dark:bg-surface-700 rounded-full flex items-center justify-center mb-3">
                                            <TrendingUp class="w-6 h-6 text-gray-400" />
                                        </div>
                                        <span class="font-medium text-gray-900 dark:text-white">Tidak ada data
                                            profit</span>
                                        <span class="text-xs mt-1">Belum ada transaksi pada periode ini</span>
                                    </div>
                                </td>
                            </tr>
                            <tr v-else v-for="(item, index) in profitRecords.daily_sales" :key="index"
                                class="hover:bg-gray-50 dark:hover:bg-surface-700/30 transition-colors group">
                                <td class="px-4 py-4 text-gray-500">{{ index + 1 }}</td>
                                <td
                                    class="px-4 py-4 font-medium text-gray-900 dark:text-white text-xs whitespace-nowrap">
                                    {{ formatDate(item.date) }}</td>
                                <td class="px-4 py-4 text-gray-900 dark:text-white font-medium text-xs">{{ item.order_no
                                    }}</td>
                                <td class="px-4 py-4 text-gray-600 dark:text-gray-300 text-xs">{{ item.customer_name }}
                                </td>
                                <td class="px-4 py-4">
                                    <span
                                        class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400 border border-blue-100 dark:border-blue-500/20">
                                        {{ item.category }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-gray-600 dark:text-gray-300 font-medium text-xs">{{ item.type
                                    }}</td>
                                <td class="px-4 py-4 text-gray-900 dark:text-white font-semibold">{{ item.qty }}</td>
                                <!-- Harga Jual -->
                                <td
                                    class="px-4 py-4 text-gray-900 dark:text-white font-mono text-xs font-semibold whitespace-nowrap">
                                    {{ formatCurrency(item.harga_jual || 0) }}
                                </td>
                                <!-- Harga Modal (editable with Rupiah formatting) -->
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-1">
                                        <div class="relative">
                                            <span
                                                class="absolute left-2.5 top-1/2 -translate-y-1/2 text-xs text-gray-400 font-mono pointer-events-none">Rp</span>
                                            <input type="text"
                                                :value="formatModalDisplay(item.id, item.default_harga_modal)"
                                                @input="onModalInput($event, item)" @focus="onModalFocus($event, item)"
                                                @blur="onModalBlur($event, item)"
                                                :placeholder="formatNumber(item.default_harga_modal || 0)" class="w-32 pl-8 pr-2.5 py-1.5 text-xs font-mono rounded-lg border transition-all
                                                    bg-white dark:bg-surface-700
                                                    focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500"
                                                :class="item.has_saved_modal
                                                    ? 'border-emerald-300 dark:border-emerald-500/30 text-emerald-700 dark:text-emerald-400'
                                                    : 'border-gray-200 dark:border-surface-600 text-gray-700 dark:text-gray-300'"
                                                @keyup.enter="saveHargaModal(item)" />
                                        </div>
                                        <button @click="saveHargaModal(item)" :disabled="savingModalId === item.id"
                                            class="p-1.5 rounded-lg text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 transition-all"
                                            title="Simpan Harga Modal">
                                            <Save v-if="savingModalId !== item.id" :size="14" />
                                            <Loader2 v-else :size="14" class="animate-spin" />
                                        </button>
                                    </div>
                                </td>
                                <!-- Profit -->
                                <td class="px-4 py-4 font-mono text-xs font-bold whitespace-nowrap"
                                    :class="getEffectiveProfit(item) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                                    {{ formatCurrency(getEffectiveProfit(item)) }}
                                </td>
                                <!-- Audit Score -->
                                <td class="px-4 py-4 text-center">
                                    <span v-if="item.audit_score === null" class="text-xs text-gray-400">-</span>
                                    <span v-else-if="item.audit_score === 100"
                                        class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-500/20">100%
                                        ✅</span>
                                    <span v-else
                                        class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400 border border-amber-100 dark:border-amber-500/20">{{
                                            item.audit_score }}% ⚠️</span>
                                </td>
                                <!-- Actions -->
                                <td class="px-4 py-4">
                                    <div class="flex items-center justify-center gap-2 transition-opacity">
                                        <button @click="openReceipt(item)"
                                            class="p-2 hover:bg-white dark:hover:bg-surface-600 rounded-lg text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 hover:shadow-sm border border-gray-200/50 dark:border-surface-600/50 transition-all shadow-sm"
                                            title="Lihat Nota">
                                            <Eye :size="16" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Receipt Modal -->
    <ReceiptModal :isOpen="showReceiptModal" :transaction="selectedTransaction" :showEditIcon="true"
        @close="showReceiptModal = false" @open-checklist="openChecklistFromReceipt" />

    <!-- Audit Checklist Modal (Profit) -->
    <Teleport to="body">
        <div v-if="showChecklistModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showChecklistModal = false"></div>
            <div
                class="relative bg-white dark:bg-surface-800 rounded-2xl border border-gray-200 dark:border-surface-700 w-full max-w-lg shadow-2xl overflow-hidden">
                <!-- Header -->
                <div class="px-6 py-4 border-b border-gray-100 dark:border-surface-700">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Cek Audit Profit</h3>
                    <p class="text-sm text-gray-500 mt-0.5">
                        Kategori: <span class="font-semibold text-purple-600 dark:text-purple-400">profit</span>
                        — {{ checklistData?.answered }}/{{ checklistData?.total }} dijawab
                        <span v-if="checklistData?.score !== undefined" class="font-semibold"
                            :class="checklistData.score === 100 ? 'text-emerald-600' : 'text-amber-600'">
                            ({{ checklistData.score }}%)
                        </span>
                    </p>
                    <p v-if="checklistData?.audited_at" class="text-xs text-gray-400 mt-1 flex items-center gap-1">
                        <Calendar :size="12" />
                        Terakhir diaudit: {{ formatDate(checklistData.audited_at) }}
                    </p>
                </div>

                <!-- Questions -->
                <div class="p-6 space-y-4 max-h-[60vh] overflow-y-auto">
                    <div v-if="checklistLoading" class="flex items-center justify-center py-8">
                        <Loader2 class="w-6 h-6 animate-spin text-primary-500" />
                    </div>
                    <div v-else-if="!checklistData?.questions?.length" class="text-center py-8 text-gray-500">
                        Belum ada pertanyaan untuk kategori <strong>profit</strong>.
                    </div>
                    <div v-else v-for="(q, i) in checklistData.questions" :key="q.question_id"
                        class="flex flex-col gap-2 p-4 rounded-xl border transition-all"
                        :class="q.answer === true ? 'border-emerald-200 dark:border-emerald-500/30 bg-emerald-50/50 dark:bg-emerald-500/5' : q.answer === false ? 'border-red-200 dark:border-red-500/30 bg-red-50/50 dark:bg-red-500/5' : 'border-gray-200 dark:border-surface-600 bg-gray-50/50 dark:bg-surface-700/30'">
                        <div class="flex items-start gap-4">
                            <span class="text-sm font-bold text-gray-400 mt-0.5">{{ i + 1 }}.</span>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ q.content }}</p>
                                <p v-if="q.is_deleted" class="text-[10px] text-red-400 mt-0.5 italic">Pertanyaan ini
                                    sudah dihapus/diubah</p>
                                <p v-if="q.answered_at" class="text-[10px] text-gray-400 mt-0.5">
                                    Dijawab: {{ formatDate(q.answered_at) }}
                                </p>
                            </div>
                            <div class="flex gap-2 flex-shrink-0">
                                <button @click="setAnswer(i, true)"
                                    class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all"
                                    :class="q.answer === true ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-500/30' : 'bg-gray-100 dark:bg-surface-600 text-gray-500 dark:text-gray-400 hover:bg-emerald-100 dark:hover:bg-emerald-500/20 hover:text-emerald-600'">
                                    Yes
                                </button>
                                <button @click="setAnswer(i, false)"
                                    class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all"
                                    :class="q.answer === false ? 'bg-red-500 text-white shadow-lg shadow-red-500/30' : 'bg-gray-100 dark:bg-surface-600 text-gray-500 dark:text-gray-400 hover:bg-red-100 dark:hover:bg-red-500/20 hover:text-red-600'">
                                    No
                                </button>
                            </div>
                        </div>
                        <!-- Notes textarea -->
                        <div class="ml-8">
                            <textarea v-model="q.notes" rows="2" placeholder="Catatan (opsional)..."
                                class="w-full text-xs px-3 py-2 rounded-lg border border-gray-200 dark:border-surface-600 bg-white dark:bg-surface-700 text-gray-700 dark:text-gray-300 placeholder-gray-400 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all resize-none">
                            </textarea>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-6 py-4 border-t border-gray-100 dark:border-surface-700 flex justify-end gap-3">
                    <button @click="showChecklistModal = false"
                        class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-surface-700 rounded-xl transition-colors">
                        Tutup
                    </button>
                    <button @click="saveChecklist" :disabled="checklistSaving"
                        class="px-5 py-2 text-sm font-bold text-white bg-primary-600 hover:bg-primary-700 rounded-xl shadow-lg shadow-primary-500/20 transition-all disabled:opacity-50">
                        {{ checklistSaving ? 'Menyimpan...' : 'Simpan' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref, onMounted, computed, watch, reactive } from 'vue'
import { Loader2, Eye, FileText, ChevronDown, Calendar, TrendingUp, Save } from 'lucide-vue-next'
import axios from '../../api/axios'
import { useAuthStore } from '../../store/auth'
import ReceiptModal from '../../components/modals/ReceiptModal.vue'

const authStore = useAuthStore()

const loading = ref(false)
const selectedPeriod = ref('daily')

// Receipt Modal State
const showReceiptModal = ref(false)
const selectedTransaction = ref(null)

const openReceipt = (item) => {
    selectedTransaction.value = item;
    showReceiptModal.value = true;
}

const openChecklistFromReceipt = () => {
    showReceiptModal.value = false
    if (selectedTransaction.value) {
        openChecklist(selectedTransaction.value)
    }
}

// Audit Checklist Modal State
const showChecklistModal = ref(false)
const checklistLoading = ref(false)
const checklistSaving = ref(false)
const checklistData = ref(null)
const checklistStockOutId = ref(null)

const openChecklist = async (item) => {
    checklistStockOutId.value = item.id
    showChecklistModal.value = true
    checklistLoading.value = true
    try {
        const res = await axios.get(`/audit/profit-checklist/${item.id}`)
        checklistData.value = res.data
    } catch (e) {
        console.error('Failed to load profit checklist', e)
        alert('Gagal memuat checklist: ' + (e.response?.data?.message || e.message))
    } finally {
        checklistLoading.value = false
    }
}

const setAnswer = (index, value) => {
    if (checklistData.value?.questions?.[index]) {
        checklistData.value.questions[index].answer = value
    }
}

const saveChecklist = async () => {
    if (!checklistData.value?.questions) return

    const answeredQuestions = checklistData.value.questions.filter(q => q.answer !== null)
    if (answeredQuestions.length === 0) {
        alert('Silakan jawab minimal 1 pertanyaan')
        return
    }

    checklistSaving.value = true
    try {
        const payload = {
            answers: answeredQuestions.map(q => ({
                question_id: q.question_id,
                answer: q.answer,
                notes: q.notes || null,
                content: q.content
            }))
        }
        const res = await axios.post(`/audit/profit-checklist/${checklistStockOutId.value}`, payload)

        // Update the score in the table
        const item = profitRecords.value.daily_sales.find(s => s.id === checklistStockOutId.value)
        if (item) {
            item.audit_score = res.data.score
            item.audit_answered = res.data.answered
            item.audit_total = res.data.total
        }

        // Update modal data
        checklistData.value.score = res.data.score
        checklistData.value.answered = res.data.answered
        checklistData.value.total = res.data.total

        alert('Checklist profit berhasil disimpan!')
    } catch (e) {
        console.error('Failed to save checklist', e)
        alert('Gagal menyimpan: ' + (e.response?.data?.message || e.message))
    } finally {
        checklistSaving.value = false
    }
}

// Harga Modal editing
const editableModal = reactive({})
const savingModalId = ref(null)

const initEditableModal = () => {
    profitRecords.value.daily_sales.forEach(item => {
        // Pre-fill with saved harga_modal or leave empty (placeholder shows default)
        editableModal[item.id] = item.harga_modal != null ? Number(item.harga_modal) : null
    })
}

// Format display for modal input (shows rupiah-formatted number)
const formatModalDisplay = (itemId, defaultVal) => {
    const val = editableModal[itemId]
    if (val != null && val !== '') {
        return formatNumber(val)
    }
    return ''
}

// Handle typing in harga modal input - strip non-digits, store raw number
const onModalInput = (event, item) => {
    const raw = event.target.value.replace(/[^0-9]/g, '')
    const num = raw ? parseInt(raw, 10) : null
    editableModal[item.id] = num
    // Reformat the display
    event.target.value = num != null ? formatNumber(num) : ''
}

const onModalFocus = (event, item) => {
    // On focus, show raw number for easy editing
    const val = editableModal[item.id]
    if (val != null) {
        event.target.value = val.toString()
    }
}

const onModalBlur = (event, item) => {
    // On blur, reformat to rupiah
    const val = editableModal[item.id]
    if (val != null) {
        event.target.value = formatNumber(val)
    } else {
        event.target.value = ''
    }
}

const getEffectiveProfit = (item) => {
    const hargaJual = Number(item.harga_jual) || 0
    const hargaModal = editableModal[item.id] || Number(item.harga_modal) || Number(item.default_harga_modal) || 0
    return hargaJual - hargaModal
}

const saveHargaModal = async (item) => {
    const value = editableModal[item.id]
    if (!value && value !== 0) {
        // Use default if empty
        editableModal[item.id] = Number(item.default_harga_modal) || 0
    }
    const hargaModal = editableModal[item.id] || Number(item.default_harga_modal) || 0

    savingModalId.value = item.id
    try {
        const res = await axios.post(`/audit/profit/${item.id}`, {
            harga_modal: hargaModal
        })
        item.harga_modal = res.data.harga_modal
        item.has_saved_modal = true
        item.profit = res.data.profit
    } catch (e) {
        console.error('Failed to save harga modal', e)
        alert('Gagal menyimpan harga modal: ' + (e.response?.data?.message || e.message))
    } finally {
        savingModalId.value = null
    }
}

// Monthly Logic
const months = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];
const currentYear = new Date().getFullYear();
const years = Array.from({ length: 5 }, (_, i) => currentYear - 2 + i);

const selectedMonth = ref(new Date().getMonth() + 1);
const selectedYear = ref(currentYear);

const profitRecords = ref({
    daily_sales: [],
})

const getTodayLocal = () => {
    const d = new Date();
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

const filters = ref({
    start_date: getTodayLocal(),
    end_date: getTodayLocal(),
    branch_id: null
})

const locations = ref([])
const selectedLocationKey = ref('all')

// Summary computeds
const totalHargaJual = computed(() =>
    profitRecords.value.daily_sales.reduce((sum, item) => sum + (Number(item.harga_jual) || 0), 0)
)
const totalHargaModal = computed(() =>
    profitRecords.value.daily_sales.reduce((sum, item) => {
        const modal = editableModal[item.id] || Number(item.harga_modal) || Number(item.default_harga_modal) || 0
        return sum + modal
    }, 0)
)
const totalProfit = computed(() => totalHargaJual.value - totalHargaModal.value)

const formattedDateDisplay = computed(() => {
    if (!filters.value.start_date) return 'Pilih Tanggal';

    if (selectedPeriod.value === 'daily') {
        const date = new Date(filters.value.start_date);
        return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
    } else {
        const monthIndex = selectedMonth.value - 1;
        const year = selectedYear.value;
        return `${months[monthIndex]} ${year}`;
    }
})

const handlePeriodChange = () => {
    if (selectedPeriod.value === 'daily') {
        const today = getTodayLocal();
        filters.value.start_date = today;
        filters.value.end_date = today;
    } else {
        handleMonthChange();
    }
    fetchData();
}

const handleDateChange = () => {
    if (selectedPeriod.value === 'daily') {
        filters.value.end_date = filters.value.start_date;
    }
    fetchData();
}

const handleMonthChange = () => {
    const year = selectedYear.value;
    const month = selectedMonth.value;
    const endDate = new Date(year, month, 0);
    const pad = (n) => n < 10 ? '0' + n : n;
    filters.value.start_date = `${year}-${pad(month)}-01`;
    filters.value.end_date = `${year}-${pad(month)}-${pad(endDate.getDate())}`;
    if (selectedPeriod.value === 'monthly') {
        fetchData();
    }
}

const canFilterBranch = computed(() => {
    const role = (authStore.userRole || '').toLowerCase();
    return ['super_admin', 'audit', 'owner'].some(r => role.includes(r));
})

const formatCurrency = (value) => {
    const num = Number(value) || 0
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(num)
}

const formatNumber = (value) => {
    const num = Number(value) || 0
    return new Intl.NumberFormat('id-ID').format(num)
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

const fetchBranches = async () => {
    if (loading.value) return;
    try {
        const requests = [
            axios.get('/branches'),
            axios.get('/online-shops')
        ];
        if (!authStore.user) {
            requests.push(axios.get('/user'));
        }
        const results = await Promise.all(requests);
        const branchRes = results[0];
        const shopRes = results[1];
        const userRes = results[2];

        const allBranches = (branchRes.data.data || branchRes.data || []).map(b => ({ ...b, type: 'branch' }));
        const allShops = (shopRes.data.data || shopRes.data || []).map(s => ({ ...s, type: 'online_shop' }));
        const allLocations = [...allBranches, ...allShops];

        const user = userRes ? (userRes.data.user || userRes.data.data || userRes.data) : authStore.user;
        const role = (authStore.userRole || '').toLowerCase();
        const isGlobalRole = ['super_admin', 'owner'].includes(role);

        let allowedBranchIds = [];
        if (user?.branch_id) allowedBranchIds.push(user.branch_id);
        let allowedShopIds = [];
        if (user?.online_shop_id) allowedShopIds.push(user.online_shop_id);

        if (user?.placements && Array.isArray(user.placements)) {
            user.placements.forEach(p => {
                if (p.model_type === 'branch') allowedBranchIds.push(p.model_id);
                if (p.model_type === 'online_shop') allowedShopIds.push(p.model_id);
            });
        }

        allowedBranchIds = [...new Set(allowedBranchIds.map(id => Number(id)))];
        allowedShopIds = [...new Set(allowedShopIds.map(id => Number(id)))];

        const hasAnyRestriction = allowedBranchIds.length > 0 || allowedShopIds.length > 0;

        if (isGlobalRole || (role === 'audit' && !hasAnyRestriction)) {
            locations.value = allLocations;
        } else if (hasAnyRestriction) {
            locations.value = allLocations.filter(loc => {
                if (loc.type === 'branch') return allowedBranchIds.includes(Number(loc.id));
                if (loc.type === 'online_shop') return allowedShopIds.includes(Number(loc.id));
                return false;
            });
            if (locations.value.length === 1 && selectedLocationKey.value === 'all') {
                const loc = locations.value[0];
                selectedLocationKey.value = `${loc.type === 'branch' ? 'B' : 'S'}:${loc.id}`;
            }
        } else {
            locations.value = [];
        }
    } catch (error) {
        console.error('Error fetching locations:', error)
    }
}

const fetchData = async () => {
    loading.value = true
    try {
        const params = { ...filters.value };
        if (selectedLocationKey.value === 'all') {
            params.branch_id = undefined;
            params.online_shop_id = undefined;
        } else {
            const [type, id] = selectedLocationKey.value.split(':');
            params.branch_id = type === 'B' ? id : undefined;
            params.online_shop_id = type === 'S' ? id : undefined;
        }

        const response = await axios.get('/audit/profit', { params })
        profitRecords.value = response.data
        initEditableModal()
    } catch (error) {
        console.error('Error fetching profit data:', error)
    } finally {
        loading.value = false
    }
}

onMounted(async () => {
    const today = getTodayLocal();
    filters.value.start_date = today;
    filters.value.end_date = today;

    if (canFilterBranch.value && locations.value.length === 0) {
        await fetchBranches()
    }

    fetchData()
})

watch(() => authStore.user, async (newUser) => {
    if (newUser && canFilterBranch.value) {
        await fetchBranches();
    }
});
</script>
