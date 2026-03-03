<script setup>
import { ref, computed, onMounted } from "vue";
import { distributors } from "../../api/axios";
import { useToast } from "../../composables/useToast";
import { Loader2, PackageSearch, MapPin, Box, Smartphone, Activity } from "lucide-vue-next";

const toast = useToast();
const isLoading = ref(false);
const monitoringData = ref([]);

const totalLocations = computed(() => monitoringData.value.length);
const totalUnits = computed(() => {
    return monitoringData.value.stock?.reduce((sum, loc) => {
        return sum + loc.products.reduce((acc, p) => acc + p.qty, 0);
    }, 0) || 0;
});

const totalOmzet = computed(() => monitoringData.value.total_omzet || 0);

// Tab state for switching between Stock and Sales
const activeTab = ref('stock'); // 'stock' or 'sales'

// Accordion state for locations
const expandedLocations = ref({});

const toggleLocation = (idx) => {
    expandedLocations.value[idx] = !expandedLocations.value[idx];
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(value || 0);
};

const formatDate = (dateString) => {
    if (!dateString) return "-";
    const date = new Date(dateString);
    return new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(date);
};

const fetchMonitoringData = async () => {
    isLoading.value = true;
    try {
        const response = await distributors.monitoring();
        if (response.data.success) {
            monitoringData.value = response.data.data;
        } else {
            toast.error(response.data.message || "Gagal memuat data monitoring");
        }
    } catch (error) {
        console.error("Fetch monitoring error", error);
        if (error.response?.data?.message) {
            toast.error(error.response.data.message);
        } else {
            toast.error("Gagal memuat data monitoring.");
        }
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => {
    fetchMonitoringData();
});
</script>

<template>
    <div class="space-y-6 animate-in pb-20">
        <!-- Header Section -->
        <div>
            <h1 class="text-2xl font-bold text-text-primary tracking-tight flex items-center gap-2">
                <PackageSearch :size="28" class="text-primary-500" /> Monitoring Stok Distributor
            </h1>
            <p class="text-text-secondary mt-1 max-w-2xl">
                Pantau ketersediaan barang yang Anda suplai di seluruh jaringan cabang PSTORE POS.
            </p>
        </div>

        <!-- Loading State -->
        <div v-if="isLoading" class="flex flex-col items-center justify-center py-20">
            <Loader2 class="w-10 h-10 animate-spin text-primary-500 mb-4" />
            <p class="text-text-secondary animate-pulse font-medium">Memuat data penyebaran stok...</p>
        </div>

        <!-- Data Display (Stock or Sales) -->
        <template v-else-if="monitoringData.stock">
            <!-- Summary Stats -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div
                    class="bg-gradient-to-br from-primary-500/10 to-primary-600/5 dark:from-primary-500/20 dark:to-primary-900/10 border border-primary-500/20 dark:border-primary-500/30 p-5 rounded-2xl flex items-center gap-4 shadow-sm">
                    <div
                        class="w-12 h-12 rounded-xl bg-primary-500/20 text-primary-600 dark:text-primary-400 flex items-center justify-center">
                        <MapPin :size="24" />
                    </div>
                    <div>
                        <p class="text-sm font-medium text-text-secondary">Total Lokasi</p>
                        <p class="text-2xl font-black text-text-primary">{{ totalLocations }} <span
                                class="text-sm font-semibold text-text-secondary">Cabang</span></p>
                    </div>
                </div>

                <div
                    class="bg-gradient-to-br from-emerald-500/10 to-emerald-600/5 dark:from-emerald-500/20 dark:to-emerald-900/10 border border-emerald-500/20 dark:border-emerald-500/30 p-5 rounded-2xl flex items-center gap-4 shadow-sm">
                    <div
                        class="w-12 h-12 rounded-xl bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                        <Activity :size="24" />
                    </div>
                    <div>
                        <p class="text-sm font-medium text-text-secondary">Produk Aktif</p>
                        <p class="text-2xl font-black text-text-primary">{{ totalUnits }} <span
                                class="text-sm font-semibold text-text-secondary">Unit</span></p>
                    </div>
                </div>
                <div
                    class="lg:col-span-2 bg-gradient-to-br from-purple-500/10 to-purple-600/5 dark:from-purple-500/20 dark:to-purple-900/10 border border-purple-500/20 dark:border-purple-500/30 p-5 rounded-2xl flex items-center gap-4 shadow-sm">
                    <div
                        class="w-12 h-12 rounded-xl bg-purple-500/20 text-purple-600 dark:text-purple-400 flex items-center justify-center">
                        <Activity :size="24" />
                    </div>
                    <div>
                        <p class="text-sm font-medium text-text-secondary">Total Omzet Penjualan</p>
                        <p class="text-2xl font-black text-text-primary">{{ formatCurrency(totalOmzet) }}</p>
                    </div>
                </div>
            </div>

            <!-- Tabs Selection -->
            <div class="flex items-center gap-2 mb-6 border-b border-surface-200 dark:border-surface-700">
                <button @click="activeTab = 'stock'"
                    class="px-6 py-3 text-sm font-semibold transition-colors duration-200 relative"
                    :class="activeTab === 'stock' ? 'text-primary-600 dark:text-primary-400' : 'text-text-secondary hover:text-text-primary'">
                    Stok Tersedia
                    <div v-if="activeTab === 'stock'" class="absolute bottom-0 left-0 w-full h-0.5 bg-primary-500">
                    </div>
                </button>
                <button @click="activeTab = 'sales'"
                    class="px-6 py-3 text-sm font-semibold transition-colors duration-200 relative"
                    :class="activeTab === 'sales' ? 'text-primary-600 dark:text-primary-400' : 'text-text-secondary hover:text-text-primary'">
                    Data Penjualan
                    <div v-if="activeTab === 'sales'" class="absolute bottom-0 left-0 w-full h-0.5 bg-primary-500">
                    </div>
                </button>
            </div>

            <!-- Data Grid for Stock -->
            <div v-if="activeTab === 'stock'" class="space-y-8">
                <!-- Empty State for Stock -->
                <div v-if="!monitoringData.stock.length"
                    class="flex flex-col items-center justify-center p-12 text-center bg-surface-800/40 backdrop-blur-xl border border-surface-700/50 rounded-2xl shadow-lg">
                    <div class="w-20 h-20 bg-surface-700/50 rounded-full flex items-center justify-center mb-4">
                        <Box class="w-10 h-10 text-text-secondary" />
                    </div>
                    <h3 class="text-xl font-bold text-text-primary mb-2">Tidak Ada Stok Aktif</h3>
                    <p class="text-text-secondary max-w-sm">
                        Belum ada produk dari suplai Anda yang berstatus tersedia di Gudang atau Cabang saat ini.
                    </p>
                </div>

                <!-- Iterate over each branch/location -->
                <div v-else v-for="(location, idx) in monitoringData.stock" :key="idx"
                    class="relative overflow-hidden bg-white dark:bg-surface-800/60 backdrop-blur-3xl border border-surface-200 dark:border-surface-700/50 shadow-sm dark:shadow-[0_8px_30px_rgb(0,0,0,0.12)] rounded-2xl transition-all duration-300 hover:shadow-md dark:hover:border-primary-500/30">

                    <!-- Card Header (Location Name) -->
                    <div @click="toggleLocation(idx)"
                        class="px-6 py-4 border-b border-surface-200 dark:border-surface-700/50 bg-surface-50 dark:bg-surface-800/40 sticky top-0 z-10 cursor-pointer hover:bg-surface-100 dark:hover:bg-surface-700/50 transition-colors">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-primary-500/10 flex items-center justify-center">
                                    <MapPin class="w-5 h-5 text-primary-600 dark:text-primary-400" />
                                </div>
                                <h2 class="text-lg font-bold text-text-primary tracking-wide">
                                    {{ location.location }}
                                </h2>
                            </div>
                            <div class="flex items-center gap-4">
                                <span
                                    class="text-xs font-semibold px-3 py-1 rounded-full bg-surface-200 dark:bg-surface-700 text-text-secondary uppercase tracking-wider">
                                    {{ location.products.length }} Varian
                                </span>
                                <span
                                    class="text-xs font-bold px-3 py-1 rounded-full bg-primary-100 text-primary-700 dark:bg-primary-900/40 dark:text-primary-400">
                                    {{location.products.reduce((sum, p) => sum + p.qty, 0)}} Unit
                                </span>
                                <ChevronDown class="w-5 h-5 text-text-secondary transition-transform duration-300"
                                    :class="expandedLocations[idx] ? 'rotate-180' : ''" />
                            </div>
                        </div>
                    </div>

                    <!-- Product Grid inside Location (Collapsible) -->
                    <div v-show="expandedLocations[idx]" class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                            <div v-for="(product, pIdx) in location.products" :key="pIdx"
                                class="group flex flex-col p-4 rounded-xl bg-surface-50 dark:bg-surface-900 border border-transparent dark:border-surface-700/30 transition-all duration-300 hover:bg-surface-100 hover:border-surface-200 dark:hover:bg-surface-800 dark:hover:border-primary-500/20 shadow-sm">

                                <div class="flex items-center justify-between w-full">
                                    <div class="flex items-center flex-1 pr-4 gap-3">
                                        <div
                                            class="w-10 h-10 rounded-lg bg-surface-200 dark:bg-surface-800 flex items-center justify-center text-text-secondary group-hover:text-primary-500 group-hover:bg-primary-500/10 transition-colors">
                                            <Smartphone v-if="product.type === 'hp'" :size="18" />
                                            <Box v-else :size="18" />
                                        </div>
                                        <div class="flex flex-col">
                                            <h4
                                                class="font-semibold text-text-primary text-sm tracking-tight group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors leading-snug">
                                                {{ product.brand }} {{ product.type_name }}
                                            </h4>

                                            <div class="flex flex-wrap items-center gap-1.5 mt-1.5">
                                                <span v-if="product.capacity"
                                                    class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-surface-200 dark:bg-surface-700 text-text-secondary shadow-sm">
                                                    {{ product.capacity }}
                                                </span>
                                                <span
                                                    class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider shadow-sm"
                                                    :class="product.condition_label === 'New' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400' : 'bg-orange-100 text-orange-700 dark:bg-orange-500/20 dark:text-orange-400'">
                                                    {{ product.condition_label }}
                                                </span>
                                                <span v-if="!product.has_imei || product.type === 'non-hp'"
                                                    class="px-1.5 py-0.5 rounded text-[10px] uppercase font-bold bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400 tracking-widest flex items-center gap-1 shadow-sm">
                                                    <div class="w-1.5 h-1.5 rounded-full bg-amber-500"></div> Non
                                                    IMEI
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex flex-col items-end justify-center min-w-[60px]">
                                        <span
                                            class="text-2xl font-black text-primary-600 dark:text-primary-400 leading-none">
                                            {{ product.qty }}
                                        </span>
                                        <span
                                            class="text-[10px] uppercase font-bold text-text-secondary tracking-widest mt-1">
                                            Unit
                                        </span>
                                    </div>
                                </div>

                                <!-- Details list -->
                                <div v-if="product.items && product.items.length > 0"
                                    class="mt-4 pt-3 border-t border-surface-200 dark:border-surface-700/50">
                                    <div class="flex flex-col gap-2 max-h-40 overflow-y-auto pr-1 custom-scrollbar">
                                        <div v-for="item in product.items" :key="item.id"
                                            class="flex items-center justify-between py-2 px-3 rounded-lg bg-white dark:bg-surface-800/80 border border-surface-200 dark:border-surface-700/60 shadow-sm hover:border-primary-300 dark:hover:border-primary-700 transition-colors">
                                            <div class="flex flex-col gap-0.5">
                                                <span v-if="product.has_imei && product.type === 'hp'"
                                                    class="font-mono text-text-primary text-xs font-semibold tracking-wide">
                                                    {{ item.imei || 'Tanpa IMEI' }}
                                                </span>
                                                <span v-else
                                                    class="font-medium text-text-primary text-xs flex items-center gap-1">
                                                    <span class="text-text-secondary">SN:</span> {{ item.imei || '-'
                                                    }}
                                                </span>
                                                <span
                                                    class="text-text-secondary text-[10px] font-medium flex items-center gap-1">
                                                    <template v-if="item.color">
                                                        <div class="w-2 h-2 rounded-full border border-surface-300 dark:border-surface-600 shadow-sm"
                                                            :style="{ backgroundColor: item.color.toLowerCase() }">
                                                        </div>
                                                        {{ item.color }}
                                                    </template>
                                                    <template v-else-if="item.notes">
                                                        <div
                                                            class="w-1.5 h-1.5 rounded-full bg-surface-400 dark:bg-surface-500">
                                                        </div>
                                                        {{ item.notes }}
                                                    </template>
                                                    <template v-else>
                                                        <div
                                                            class="w-1.5 h-1.5 rounded-full bg-surface-300 dark:bg-surface-600">
                                                        </div>
                                                        Unit Standard
                                                    </template>
                                                </span>
                                            </div>
                                            <div class="flex justify-end">
                                                <span
                                                    class="px-2 py-1 rounded text-[10px] font-bold tracking-wider uppercase"
                                                    :class="item.condition === 'new' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400' : 'bg-orange-100 text-orange-700 dark:bg-orange-500/20 dark:text-orange-400'">
                                                    {{ item.condition === 'new' ? 'BARU' : 'BEKAS' }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Data Grid for Sales -->
            <div v-if="activeTab === 'sales'" class="space-y-8">
                <!-- Sales IMEI Items -->
                <div
                    class="bg-white dark:bg-surface-800/60 backdrop-blur-3xl border border-surface-200 dark:border-surface-700/50 rounded-2xl overflow-hidden shadow-sm">
                    <div
                        class="px-6 py-4 border-b border-surface-200 dark:border-surface-700/50 bg-surface-50 dark:bg-surface-800/40 flex items-center gap-3">
                        <Smartphone class="w-5 h-5 text-primary-500" />
                        <h2 class="text-lg font-bold text-text-primary tracking-wide">Penjualan HP (IMEI)</h2>
                    </div>
                    <div class="p-0 overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead
                                class="text-xs text-text-secondary uppercase bg-surface-50 dark:bg-surface-900/50 border-b border-surface-200 dark:border-surface-700">
                                <tr>
                                    <th class="px-6 py-4 font-semibold">Toko & Tanggal</th>
                                    <th class="px-6 py-4 font-semibold">Produk</th>
                                    <th class="px-6 py-4 font-semibold">Spesifikasi</th>
                                    <th class="px-6 py-4 font-semibold">IMEI</th>
                                    <th class="px-6 py-4 font-semibold text-right">Harga Jual</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-surface-200 dark:divide-surface-700">
                                <tr v-if="!monitoringData.sales_hp || !monitoringData.sales_hp.length">
                                    <td colspan="5" class="px-6 py-8 text-center text-text-secondary italic">Tidak
                                        ada data
                                        penjualan HP untuk saat ini.</td>
                                </tr>
                                <tr v-else v-for="item in monitoringData.sales_hp" :key="item.id"
                                    class="hover:bg-surface-50 dark:hover:bg-surface-700/30 transition-colors">
                                    <td class="px-6 py-4 text-xs font-medium">
                                        <div class="text-text-primary font-bold">{{ item.outlet }}</div>
                                        <div class="text-text-secondary mt-0.5">{{ formatDate(item.date) }}</div>
                                        <div class="text-text-secondary mt-0.5 text-[10px] font-mono opacity-75">{{
                                            item.receipt_id
                                            }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-text-primary">{{ item.brand }}</div>
                                        <div class="text-text-secondary mt-0.5">{{ item.type_name }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            <span v-if="item.capacity"
                                                class="px-2 py-0.5 rounded text-[10px] font-bold bg-surface-200 dark:bg-surface-700 text-text-primary">{{
                                                    item.capacity }}</span>
                                            <span
                                                class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider"
                                                :class="item.condition_label === 'New' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400' : 'bg-orange-100 text-orange-700 dark:bg-orange-500/20 dark:text-orange-400'">{{
                                                    item.condition_label }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 font-mono text-xs text-text-primary font-medium tracking-wide">
                                        {{ item.imei
                                            || '-' }}</td>
                                    <td class="px-6 py-4 text-right">
                                        <span class="font-bold font-mono text-emerald-600 dark:text-emerald-400">{{
                                            formatCurrency(item.harga_jual) }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Sales Non-IMEI Items -->
                <div
                    class="bg-white dark:bg-surface-800/60 backdrop-blur-3xl border border-surface-200 dark:border-surface-700/50 rounded-2xl overflow-hidden shadow-sm">
                    <div
                        class="px-6 py-4 border-b border-surface-200 dark:border-surface-700/50 bg-surface-50 dark:bg-surface-800/40 flex items-center gap-3">
                        <Box class="w-5 h-5 text-amber-500" />
                        <h2 class="text-lg font-bold text-text-primary tracking-wide">Penjualan Aksesoris (Non-IMEI)
                        </h2>
                    </div>
                    <div class="p-0 overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead
                                class="text-xs text-text-secondary uppercase bg-surface-50 dark:bg-surface-900/50 border-b border-surface-200 dark:border-surface-700">
                                <tr>
                                    <th class="px-6 py-4 font-semibold">Toko (Outlet)</th>
                                    <th class="px-6 py-4 font-semibold">Brand</th>
                                    <th class="px-6 py-4 font-semibold">Nama Produk</th>
                                    <th class="px-6 py-4 font-semibold text-center">Unit Terjual</th>
                                    <th class="px-6 py-4 font-semibold text-right">Total Omzet Varian</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-surface-200 dark:divide-surface-700">
                                <tr v-if="!monitoringData.sales_non_hp || !monitoringData.sales_non_hp.length">
                                    <td colspan="5" class="px-6 py-8 text-center text-text-secondary italic">Tidak ada
                                        data
                                        penjualan aksesoris untuk saat ini.</td>
                                </tr>
                                <template v-else v-for="(item, idx) in monitoringData.sales_non_hp" :key="idx">
                                    <tr class="hover:bg-surface-50 dark:hover:bg-surface-700/30 transition-colors">
                                        <td class="px-6 py-4 text-xs font-bold text-text-primary">{{ item.outlet }}</td>
                                        <td class="px-6 py-4 font-bold text-text-primary">{{ item.brand }}</td>
                                        <td class="px-6 py-4 text-text-secondary font-medium">{{ item.type_name }}</td>
                                        <td class="px-6 py-4 text-center">
                                            <span
                                                class="px-3 py-1 bg-primary-500/10 text-primary-600 dark:text-primary-400 font-bold rounded-lg">{{
                                                    item.qty }}</span>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <span class="font-bold font-mono text-emerald-600 dark:text-emerald-400">{{
                                                formatCurrency(item.total_sales) }}</span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

<style scoped>
.animate-in {
    animation: slideUpFade 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes slideUpFade {
    0% {
        opacity: 0;
        transform: translateY(15px);
    }

    100% {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
