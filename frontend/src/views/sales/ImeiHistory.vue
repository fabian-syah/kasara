<script setup>
import { ref } from "vue";
import api from "../../api/axios";
import { useToast } from "../../composables/useToast";
import {
    Search,
    Package,
    Loader2,
    Calendar,
    Smartphone,
    ArrowUpRight,
    MapPin,
    DollarSign,
    Box,
    User,
    History
} from "lucide-vue-next";

const toast = useToast();

const query = ref("");
const isLoading = ref(false);
const results = ref([]);
const hasSearched = ref(false);

async function search() {
    if (query.value.length < 3) {
        toast.error("Minimal 3 karakter untuk mencari");
        return;
    }

    isLoading.value = true;
    hasSearched.value = true;

    try {
        const response = await api.get('/track', { params: { q: query.value } });
        // Filter out 'barang_masuk' stock_out records - these are internal records from stock-in
        const data = response.data.data || [];
        results.value = data.filter(r => !(r.type === 'stock_out' && r.category === 'barang_masuk'));
    } catch (e) {
        toast.error(e.response?.data?.message || "Gagal mencari");
        results.value = [];
    } finally {
        isLoading.value = false;
    }
}

function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function formatCurrency(value) {
    if (!value) return '-';
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(value);
}
</script>

<template>
    <div class="space-y-6 animate-in fade-in max-w-4xl mx-auto">
        <!-- Header -->
        <div class="text-center py-8">
            <div class="w-20 h-20 mx-auto bg-blue-500/20 rounded-3xl flex items-center justify-center mb-4">
                <History :size="36" class="text-blue-500" />
            </div>
            <h1 class="text-3xl font-bold text-text-primary">History IMEI</h1>
            <p class="text-text-secondary mt-2">Cek riwayat perpindahan barang berdasarkan IMEI</p>
        </div>

        <!-- Search Box -->
        <div
            class="bg-white dark:bg-surface-800 rounded-2xl p-3 sm:p-4 border border-surface-200 dark:border-surface-700 shadow-sm">
            <form @submit.prevent="search" class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1">
                    <Search class="absolute left-4 top-1/2 -translate-y-1/2 text-text-secondary" :size="20" />
                    <input v-model="query" type="text" placeholder="Masukkan nomor IMEI..."
                        class="w-full border border-surface-200 dark:border-surface-700 rounded-xl pl-12 pr-4 py-4 bg-white dark:bg-surface-900 text-text-primary text-base sm:text-lg focus:outline-none focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 transition-all placeholder:text-surface-500" />
                </div>
                <button type="submit" :disabled="isLoading || query.length < 3"
                    class="h-14 px-8 rounded-2xl font-bold bg-primary-600 hover:bg-primary-500 text-white disabled:opacity-30 disabled:cursor-not-allowed flex items-center justify-center transition-all">
                    <Loader2 v-if="isLoading" :size="20" class="animate-spin" />
                    <span v-else>Cari IMEI</span>
                </button>
            </form>
        </div>

        <!-- Results -->
        <div v-if="hasSearched" class="space-y-4">
            <div v-if="isLoading" class="text-center py-12 text-text-secondary">
                <Loader2 :size="32" class="animate-spin mx-auto mb-2" />
                Mencari.....
            </div>

            <div v-else-if="results.length === 0" class="text-center py-12 text-text-secondary">
                <Package :size="48" class="mx-auto mb-2 opacity-50" />
                <p>Tidak ditemukan hasil untuk "{{ query }}"</p>
            </div>

            <div v-else class="space-y-4">
                <p class="text-text-secondary text-sm">Ditemukan {{ results.length }} hasil untuk IMEI "{{ query }}"</p>

                <!-- Timeline -->
                <div class="relative space-y-6 pb-8">
                    <!-- Vertical Timeline Line -->
                    <div class="absolute left-6 top-6 bottom-6 w-0.5 bg-surface-200 dark:bg-surface-700 -z-10"></div>

                    <template v-for="result in results">
                        <!-- STOCK IN -->
                        <div v-if="result.type === 'stock_in'" :key="'in-' + result.id"
                            class="bg-white dark:bg-surface-800 rounded-2xl p-6 border-l-4 border border-surface-200 dark:border-surface-700 transition-all relative"
                            :class="result.is_arrival ? 'border-l-indigo-500 bg-indigo-500/5' : 'border-l-green-500'">
                            <!-- Header -->
                            <div class="flex flex-col sm:flex-row items-start justify-between gap-4 mb-6">
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0"
                                        :class="result.is_arrival ? 'bg-indigo-500/20 text-indigo-500' : 'bg-green-500/20 text-green-500'">
                                        <ArrowUpRight v-if="!result.is_arrival" :size="24" />
                                        <MapPin v-else :size="24" />
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2 mb-1">
                                            <span v-if="!result.is_arrival"
                                                class="text-green-500 text-[10px] font-bold bg-green-500/10 border border-green-500/20 px-2 py-0.5 rounded uppercase tracking-wider">MASUK
                                                (STOK)</span>
                                            <span v-else
                                                class="text-indigo-500 text-[10px] font-bold bg-indigo-500/10 border border-indigo-500/20 px-2 py-0.5 rounded uppercase tracking-wider">MASUK
                                                (TRANSFER)</span>
                                            <p class="font-bold text-text-primary text-base truncate">{{
                                                result.product_name }}</p>
                                        </div>
                                        <p class="text-sm text-text-secondary font-mono tracking-tight">{{ result.imei
                                            }}</p>
                                    </div>
                                </div>
                                <div
                                    class="flex items-center gap-2 text-text-secondary text-xs sm:text-sm bg-surface-50 dark:bg-surface-900/50 px-3 py-1.5 rounded-lg border border-surface-200 dark:border-surface-700/50">
                                    <Calendar :size="14" />
                                    {{ formatDate(result.created_at) }}
                                </div>
                            </div>

                            <!-- Details Grid -->
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                                <div>
                                    <p class="text-text-secondary text-xs flex items-center gap-1">
                                        <Smartphone :size="12" /> Kondisi
                                    </p>
                                    <p class="text-text-primary capitalize">{{ result.condition || '-' }}</p>
                                </div>
                                <div>
                                    <p class="text-text-secondary text-xs flex items-center gap-1">
                                        <Box :size="12" /> Status
                                    </p>
                                    <span class="px-2 py-0.5 rounded text-xs font-bold capitalize" :class="{
                                        'bg-green-500/20 text-green-500': result.status === 'available',
                                        'bg-amber-500/20 text-amber-500': result.status === 'sold',
                                        'bg-blue-500/20 text-blue-500': result.status === 'transfer',
                                        'bg-red-500/20 text-red-500': result.status === 'deleted'
                                    }">
                                        {{ result.status }}
                                    </span>
                                </div>
                                <div>
                                    <p class="text-text-secondary text-xs flex items-center gap-1">
                                        <MapPin :size="12" /> Lokasi
                                    </p>
                                    <p class="text-text-primary">{{ result.placement_name || result.placement_type +
                                        '#' + result.placement_id }}</p>
                                </div>
                                <div>
                                    <p class="text-text-secondary text-xs flex items-center gap-1">
                                        <DollarSign :size="12" /> Harga Jual
                                    </p>
                                    <p class="text-text-primary font-bold">{{ formatCurrency(result.selling_price) }}
                                    </p>
                                </div>
                                <div v-if="result.distributor">
                                    <p class="text-text-secondary text-xs">Distributor</p>
                                    <p class="text-text-primary">{{ result.distributor }}</p>
                                </div>
                                <div>
                                    <p class="text-text-secondary text-xs flex items-center gap-1">
                                        <User :size="12" /> {{ result.is_arrival ? 'Diterima oleh' : 'Diinput oleh' }}
                                    </p>
                                    <p class="text-text-primary">{{ result.input_by || '-' }}</p>
                                </div>
                                <div v-if="(result.ram || result.storage) && !result.is_arrival">
                                    <p class="text-text-secondary text-xs">RAM / Storage</p>
                                    <p class="text-text-primary">{{ result.ram || '-' }}GB / {{ result.storage || '-'
                                        }}GB</p>
                                </div>
                            </div>
                        </div>

                        <!-- STOCK OUT -->
                        <div v-else-if="result.type === 'stock_out'" :key="'out-' + result.id"
                            class="bg-white dark:bg-surface-800 rounded-2xl p-6 border-l-4 border border-surface-200 dark:border-surface-700 transition-all"
                            :class="{
                                'border-l-blue-500': result.category === 'pindah_cabang',
                                'border-l-amber-500': result.category === 'kesalahan_input',
                                'border-l-purple-500': result.category === 'retur',
                                'border-l-[#EE4D2D]': ['shopee', 'orderan_online'].includes(result.category),
                                'border-l-red-500': !['pindah_cabang', 'kesalahan_input', 'retur', 'shopee', 'orderan_online'].includes(result.category)
                            }">
                            <div class="flex flex-col sm:flex-row items-start justify-between gap-4 mb-4">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0 bg-red-500/20 text-red-500">
                                        <Package :size="24" />
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2 mb-1">
                                            <span
                                                class="text-red-400 text-[10px] font-bold bg-red-500/10 border border-red-500/20 px-2 py-0.5 rounded uppercase tracking-wider">KELUAR</span>
                                            <p class="font-bold text-text-primary text-base truncate">{{ result.id }}
                                            </p>
                                        </div>
                                        <p class="text-sm text-text-secondary">{{ result.category }}</p>
                                    </div>
                                </div>
                                <div
                                    class="flex items-center gap-2 text-text-secondary text-xs sm:text-sm bg-surface-50 dark:bg-surface-900/50 px-3 py-1.5 rounded-lg border border-surface-200 dark:border-surface-700/50">
                                    <Calendar :size="14" />
                                    {{ formatDate(result.created_at) }}
                                </div>
                            </div>

                            <!-- Items -->
                            <div v-if="result.items && result.items.length > 0"
                                class="mt-3 border-t border-surface-200 dark:border-surface-700 pt-3">
                                <p class="text-text-secondary text-xs mb-2 font-bold uppercase tracking-wider">
                                    Barang ({{ result.items.length }})
                                </p>
                                <div class="space-y-2">
                                    <div v-for="(item, idx) in result.items" :key="idx"
                                        class="flex items-center gap-3 p-2 bg-surface-50 dark:bg-surface-700/30 rounded-lg">
                                        <Smartphone :size="16" class="text-text-secondary" />
                                        <div class="flex-1">
                                            <p class="text-text-primary text-sm font-medium">{{ item.product_name }}</p>
                                            <p class="text-text-secondary text-xs"
                                                v-if="item.imei && item.imei !== '-'">{{ item.imei }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>
