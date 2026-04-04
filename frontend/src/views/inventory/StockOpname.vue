<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import {
    ArrowLeft, RefreshCw, Search, Smartphone, Package, BarChart3, Box,
    Layers, Tag, Truck, ChevronRight, ToggleLeft, ToggleRight, HardDrive
} from 'lucide-vue-next';
import { inventory as inventoryApi } from '../../api/axios';
import { useToast } from '../../composables/useToast';

const router = useRouter();
const toast = useToast();

const loading = ref(false);
const rawHpItems = ref([]);
const rawNonHpItems = ref([]);
const showPerGb = ref(false);
const showBrandType = ref(false);
const showBrandCondition = ref(false);
const itemMode = ref('hp'); // 'hp' or 'non-hp'
const searchQuery = ref('');

const fetchAllInventory = async () => {
    loading.value = true;
    try {
        // Fetch HP
        let page = 1;
        let hpAll = [];
        let hasMore = true;
        while (hasMore) {
            const response = await inventoryApi.list({ page, per_page: 100 });
            const data = response.data;
            if (data.data) {
                hpAll = hpAll.concat(data.data);
                hasMore = data.current_page < data.last_page;
                page++;
            } else { hasMore = false; }
        }
        rawHpItems.value = hpAll;

        // Fetch Non-HP
        page = 1;
        let nonHpAll = [];
        hasMore = true;
        while (hasMore) {
            const response = await inventoryApi.list({ page, per_page: 100, type: 'non-hp' });
            const data = response.data;
            if (data.data) {
                nonHpAll = nonHpAll.concat(data.data);
                hasMore = data.current_page < data.last_page;
                page++;
            } else { hasMore = false; }
        }
        rawNonHpItems.value = nonHpAll;
    } catch (error) {
        console.error(error);
        toast.error('Gagal memuat data inventory.');
    } finally {
        loading.value = false;
    }
};

// Active items based on mode
const isHpMode = computed(() => itemMode.value === 'hp');
const activeItems = computed(() => isHpMode.value ? rawHpItems.value : rawNonHpItems.value);

// Helper: get available qty for an item
const getAvailable = (item) => {
    if (isHpMode.value) return ['available', 'booking', 'process'].includes(item.status) ? 1 : 0;
    return item.quantity || item.balance || 0;
};
const getTotal = (item) => {
    if (isHpMode.value) return 1;
    return item.quantity || item.balance || 0;
};

// Summary stats for landing
const summaryStats = computed(() => {
    const hpAvail = rawHpItems.value.filter(i => ['available', 'booking', 'process'].includes(i.status)).length;
    const nonHpAvail = rawNonHpItems.value.reduce((s, i) => s + (i.quantity || i.balance || 0), 0);
    return {
        totalHp: hpAvail,
        totalNonHp: nonHpAvail,
        totalBrands: new Set(activeItems.value.map(i => i.product?.brand || '-')).size,
        totalTypes: new Set(activeItems.value.map(i => i.product?.name || '-')).size,
        totalDistributors: new Set(activeItems.value.map(i => i.distributor?.name || i.latest_distributor || i.latest_supplier || '').filter(Boolean)).size
    };
});

// ===== BRAND REPORT =====
const conditionLabels = {
    'new': 'Baru (New)',
    'second': 'Second',
    'ex_ibox': 'Ex-iBox',
    'other': 'Lainnya'
};

const brandReport = computed(() => {
    const map = new Map();
    activeItems.value.forEach(item => {
        const brand = item.product?.brand || 'Lainnya';
        if (!map.has(brand)) {
            map.set(brand, { 
                brand, 
                available: 0, 
                typeBreakdown: new Map(),
                conditionBreakdown: new Map()
            });
        }
        const entry = map.get(brand);
        const avail = getAvailable(item);
        entry.available += avail;

        // Type Breakdown
        const typeName = item.product?.name || 'Unknown';
        if (!entry.typeBreakdown.has(typeName)) {
            entry.typeBreakdown.set(typeName, { label: typeName, available: 0 });
        }
        entry.typeBreakdown.get(typeName).available += avail;

        // Condition Breakdown
        const condKey = item.condition || 'other';
        const condLabel = conditionLabels[condKey] || condKey;
        if (!entry.conditionBreakdown.has(condLabel)) {
            entry.conditionBreakdown.set(condLabel, { label: condLabel, available: 0, condition: condKey });
        }
        entry.conditionBreakdown.get(condLabel).available += avail;
    });

    return Array.from(map.values())
        .map(e => ({ 
            ...e, 
            typeBreakdown: Array.from(e.typeBreakdown.values()).sort((a, b) => b.available - a.available),
            conditionBreakdown: Array.from(e.conditionBreakdown.values()).sort((a, b) => b.available - a.available)
        }))
        .sort((a, b) => b.available - a.available);
});

// ===== TYPE REPORT =====
const typeReport = computed(() => {
    const map = new Map();
    activeItems.value.forEach(item => {
        const name = item.product?.name || 'Unknown';
        const brand = item.product?.brand || '-';
        const key = `${brand}||${name}`;
        if (!map.has(key)) map.set(key, { name, brand, available: 0, sold: 0, total: 0, gbBreakdown: new Map() });
        const entry = map.get(key);
        const avail = getAvailable(item);
        const tot = getTotal(item);
        entry.total += tot;
        entry.available += avail;
        entry.sold += (tot - avail);

        if (isHpMode.value) {
            const ram = item.ram || '-';
            const storage = item.storage || '-';
            const gbKey = ram !== '-' && storage !== '-' ? `${ram}/${storage}` : (storage !== '-' ? storage : ram);
            if (!entry.gbBreakdown.has(gbKey)) entry.gbBreakdown.set(gbKey, { label: gbKey, available: 0, sold: 0, total: 0 });
            const gb = entry.gbBreakdown.get(gbKey);
            gb.total += tot;
            gb.available += avail;
            gb.sold += (tot - avail);
        }
    });
    return Array.from(map.values())
        .map(e => ({ ...e, gbBreakdown: Array.from(e.gbBreakdown.values()).sort((a, b) => b.available - a.available) }))
        .sort((a, b) => b.available - a.available);
});

// ===== CONDITION REPORT =====
const conditionLabels = {
    'new': 'Baru (New)',
    'second': 'Second',
    'ex_ibox': 'Ex-iBox',
    'ex_inter': 'Ex-Inter',
    'refurbished': 'Refurbished',
    'service': 'Service/Retur',
    'other': 'Lainnya'
};

const conditionReport = computed(() => {
    const map = new Map();
    activeItems.value.forEach(item => {
        const cond = item.condition || 'unknown';
        if (!map.has(cond)) map.set(cond, { condition: cond, label: conditionLabels[cond] || cond, available: 0, sold: 0, total: 0, gbBreakdown: new Map() });
        const entry = map.get(cond);
        const avail = getAvailable(item);
        const tot = getTotal(item);
        entry.total += tot;
        entry.available += avail;
        entry.sold += (tot - avail);

        if (isHpMode.value) {
            const ram = item.ram || '-';
            const storage = item.storage || '-';
            const gbKey = ram !== '-' && storage !== '-' ? `${ram}/${storage}` : (storage !== '-' ? storage : ram);
            if (!entry.gbBreakdown.has(gbKey)) entry.gbBreakdown.set(gbKey, { label: gbKey, available: 0, sold: 0, total: 0 });
            const gb = entry.gbBreakdown.get(gbKey);
            gb.total += tot;
            gb.available += avail;
            gb.sold += (tot - avail);
        }
    });
    return Array.from(map.values())
        .map(e => ({ ...e, gbBreakdown: Array.from(e.gbBreakdown.values()).sort((a, b) => b.available - a.available) }))
        .sort((a, b) => b.available - a.available);
});

// ===== DISTRIBUTOR REPORT =====
const distributorReport = computed(() => {
    const map = new Map();
    activeItems.value.forEach(item => {
        const distName = item.distributor?.name || item.latest_distributor || item.latest_supplier || item.latestLog?.distributor?.name || 'Tidak Diketahui';
        if (!map.has(distName)) map.set(distName, { name: distName, available: 0, sold: 0, total: 0 });
        const entry = map.get(distName);
        const avail = getAvailable(item);
        const tot = getTotal(item);
        entry.total += tot;
        entry.available += avail;
        entry.sold += (tot - avail);
    });
    return Array.from(map.values()).sort((a, b) => b.available - a.available);
});

// Filtered search for sub-views
const searchFilter = (items, fields) => {
    if (!searchQuery.value) return items;
    const q = searchQuery.value.toLowerCase();
    return items.filter(i => fields.some(f => String(i[f] || '').toLowerCase().includes(q)));
};

const filteredBrand = computed(() => searchFilter(brandReport.value, ['brand']));
const filteredType = computed(() => searchFilter(typeReport.value, ['name', 'brand']));
const filteredCondition = computed(() => searchFilter(conditionReport.value, ['label', 'condition']));
const filteredDistributor = computed(() => searchFilter(distributorReport.value, ['name']));

function navigateTo(view) {
    currentView.value = view;
    searchQuery.value = '';
    showPerGb.value = false;
}

function goBack() {
    currentView.value = 'menu';
    searchQuery.value = '';
    showPerGb.value = false;
}

const conditionColor = (cond) => {
    const colors = {
        'new': 'text-emerald-400 bg-emerald-400/10 border-emerald-400/20',
        'second': 'text-amber-400 bg-amber-400/10 border-amber-400/20',
        'ex_ibox': 'text-blue-400 bg-blue-400/10 border-blue-400/20',
        'ex_inter': 'text-purple-400 bg-purple-400/10 border-purple-400/20',
        'refurbished': 'text-orange-400 bg-orange-400/10 border-orange-400/20',
        'service': 'text-red-400 bg-red-400/10 border-red-400/20',
    };
    return colors[cond] || 'text-gray-400 bg-gray-400/10 border-gray-400/20';
};

onMounted(() => { fetchAllInventory(); });
</script>

<template>
    <div class="space-y-6 animate-in">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div class="flex items-center gap-3">
                <button @click="currentView === 'menu' ? router.push({ name: 'Inventory' }) : goBack()"
                    class="p-2 hover:bg-surface-800 rounded-xl transition-colors">
                    <ArrowLeft :size="20" class="text-text-secondary" />
                </button>
                <div>
                    <h1 class="text-2xl font-bold text-text-primary tracking-tight">Stock Opname</h1>
                    <p class="text-text-secondary mt-0.5 text-sm">
                        {{ currentView === 'menu' ? 'Pilih jenis laporan untuk melihat detail' :
                            currentView === 'brand' ? 'Ringkasan stok per merek' :
                            currentView === 'type' ? 'Ringkasan stok per tipe produk' :
                            currentView === 'condition' ? 'Ringkasan stok per kondisi barang' :
                            'Ringkasan stok per distributor/supplier' }}
                    </p>
                </div>
            </div>
            <button @click="fetchAllInventory"
                class="p-2.5 text-text-secondary hover:text-primary-500 hover:bg-primary-500/10 rounded-xl transition-all">
                <RefreshCw :size="20" :class="{ 'animate-spin': loading }" />
            </button>
        </div>

        <!-- ==================== MENU LANDING ==================== -->
        <template v-if="currentView === 'menu'">
            <!-- HP / Non-HP Toggle -->
            <div class="flex items-center justify-between">
                <div class="flex space-x-1 rounded-xl bg-surface-800 border border-surface-700 p-1 w-fit">
                    <button @click="itemMode = 'hp'"
                        class="px-5 py-2.5 rounded-lg text-sm font-bold leading-5 transition-all duration-200 flex items-center gap-2"
                        :class="itemMode === 'hp' ? 'bg-primary-500 text-white shadow-lg shadow-primary-500/20' : 'text-text-secondary hover:text-white'">
                        <Smartphone :size="16" /> HP (IMEI)
                    </button>
                    <button @click="itemMode = 'non-hp'"
                        class="px-5 py-2.5 rounded-lg text-sm font-bold leading-5 transition-all duration-200 flex items-center gap-2"
                        :class="itemMode === 'non-hp' ? 'bg-purple-500 text-white shadow-lg shadow-purple-500/20' : 'text-text-secondary hover:text-white'">
                        <Package :size="16" /> Non-HP
                    </button>
                </div>
            </div>

            <!-- Loading -->
            <div v-if="loading" class="flex flex-col items-center justify-center py-20">
                <RefreshCw class="animate-spin text-primary-500 mb-4" :size="40" />
                <p class="text-text-secondary text-sm font-medium">Memuat data inventory...</p>
            </div>

            <!-- Quick Stats & Cards (only when not loading) -->
            <div v-else class="space-y-6">
                <!-- Quick Stats -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <div class="bg-surface-800 rounded-2xl border border-surface-700 p-4">
                        <div class="text-xs text-text-secondary uppercase tracking-wider font-bold mb-1">{{ isHpMode ? 'HP Tersedia' : 'Non-HP Tersedia' }}</div>
                        <div class="text-2xl font-black" :class="isHpMode ? 'text-blue-400' : 'text-purple-400'">{{ isHpMode ? summaryStats.totalHp : summaryStats.totalNonHp }}</div>
                    </div>
                    <div class="bg-surface-800 rounded-2xl border border-surface-700 p-4">
                        <div class="text-xs text-text-secondary uppercase tracking-wider font-bold mb-1">Jumlah Brand</div>
                        <div class="text-2xl font-black text-purple-400">{{ summaryStats.totalBrands }}</div>
                    </div>
                    <div class="bg-surface-800 rounded-2xl border border-surface-700 p-4">
                        <div class="text-xs text-text-secondary uppercase tracking-wider font-bold mb-1">Jumlah Tipe</div>
                        <div class="text-2xl font-black text-emerald-400">{{ summaryStats.totalTypes }}</div>
                    </div>
                    <div class="bg-surface-800 rounded-2xl border border-surface-700 p-4">
                        <div class="text-xs text-text-secondary uppercase tracking-wider font-bold mb-1">Distributor</div>
                        <div class="text-2xl font-black text-amber-400">{{ summaryStats.totalDistributors }}</div>
                    </div>
                </div>

                <!-- Report Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Brand -->
                    <button @click="navigateTo('brand')"
                        class="group bg-surface-800 rounded-2xl border border-surface-700 hover:border-blue-500/50 p-6 text-left transition-all duration-300 hover:shadow-lg hover:shadow-blue-500/5 hover:translate-y-[-2px]">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-blue-500/10 rounded-xl group-hover:bg-blue-500/20 transition-colors">
                                <Layers :size="24" class="text-blue-400" />
                            </div>
                            <ChevronRight :size="20" class="text-text-secondary group-hover:text-blue-400 transition-colors" />
                        </div>
                        <h3 class="text-lg font-bold text-text-primary mb-1">Laporan per Brand</h3>
                        <p class="text-sm text-text-secondary">Ringkasan stok tersedia berdasarkan merek</p>
                        <div class="mt-4 flex gap-2 flex-wrap">
                            <span v-for="b in brandReport.slice(0, 4)" :key="b.brand"
                                class="text-[10px] px-2 py-1 rounded-lg bg-surface-900 text-text-secondary font-medium border border-surface-700">
                                {{ b.brand }}: {{ b.available }}
                            </span>
                            <span v-if="brandReport.length > 4" class="text-[10px] px-2 py-1 rounded-lg bg-surface-900 text-text-secondary font-medium border border-surface-700">
                                +{{ brandReport.length - 4 }} lagi
                            </span>
                        </div>
                    </button>

                    <!-- Type -->
                    <button @click="navigateTo('type')"
                        class="group bg-surface-800 rounded-2xl border border-surface-700 hover:border-emerald-500/50 p-6 text-left transition-all duration-300 hover:shadow-lg hover:shadow-emerald-500/5 hover:translate-y-[-2px]">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-emerald-500/10 rounded-xl group-hover:bg-emerald-500/20 transition-colors">
                                <Smartphone :size="24" class="text-emerald-400" />
                            </div>
                            <ChevronRight :size="20" class="text-text-secondary group-hover:text-emerald-400 transition-colors" />
                        </div>
                        <h3 class="text-lg font-bold text-text-primary mb-1">Laporan per Tipe</h3>
                        <p class="text-sm text-text-secondary">Ringkasan stok per model + breakdown GB</p>
                        <div v-if="isHpMode" class="mt-4 flex items-center gap-2 text-[10px] text-emerald-400">
                            <HardDrive :size="12" /> <span class="font-bold uppercase tracking-wider">Fitur: Tampilkan per GB</span>
                        </div>
                    </button>

                    <!-- Condition -->
                    <button @click="navigateTo('condition')"
                        class="group bg-surface-800 rounded-2xl border border-surface-700 hover:border-amber-500/50 p-6 text-left transition-all duration-300 hover:shadow-lg hover:shadow-amber-500/5 hover:translate-y-[-2px]">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-amber-500/10 rounded-xl group-hover:bg-amber-500/20 transition-colors">
                                <Tag :size="24" class="text-amber-400" />
                            </div>
                            <ChevronRight :size="20" class="text-text-secondary group-hover:text-amber-400 transition-colors" />
                        </div>
                        <h3 class="text-lg font-bold text-text-primary mb-1">Laporan per Kondisi</h3>
                        <p class="text-sm text-text-secondary">Ringkasan stok per kondisi barang + breakdown GB</p>
                        <div v-if="isHpMode" class="mt-4 flex items-center gap-2 text-[10px] text-amber-400">
                            <HardDrive :size="12" /> <span class="font-bold uppercase tracking-wider">Fitur: Tampilkan per GB</span>
                        </div>
                    </button>

                    <!-- Distributor -->
                    <button @click="navigateTo('distributor')"
                        class="group bg-surface-800 rounded-2xl border border-surface-700 hover:border-purple-500/50 p-6 text-left transition-all duration-300 hover:shadow-lg hover:shadow-purple-500/5 hover:translate-y-[-2px]">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-purple-500/10 rounded-xl group-hover:bg-purple-500/20 transition-colors">
                                <Truck :size="24" class="text-purple-400" />
                            </div>
                            <ChevronRight :size="20" class="text-text-secondary group-hover:text-purple-400 transition-colors" />
                        </div>
                        <h3 class="text-lg font-bold text-text-primary mb-1">Laporan per Distributor</h3>
                        <p class="text-sm text-text-secondary">Ringkasan stok per supplier/distributor</p>
                        <div class="mt-4 flex gap-2 flex-wrap">
                            <span v-for="d in distributorReport.slice(0, 3)" :key="d.name"
                                class="text-[10px] px-2 py-1 rounded-lg bg-surface-900 text-text-secondary font-medium border border-surface-700">
                                {{ d.name }}: {{ d.available }}
                            </span>
                        </div>
                    </button>
                </div>
            </div>
        </template>

        <!-- ==================== SUB-VIEW: CONTROLS BAR ==================== -->
        <template v-if="currentView !== 'menu'">
            <div class="bg-surface-800 rounded-2xl border border-surface-700 p-4">
                <div class="flex flex-col sm:flex-row gap-4 justify-between items-start sm:items-center">
                    <!-- Toggle per GB (only for HP mode + type/condition views) -->
                    <div v-if="isHpMode && (currentView === 'type' || currentView === 'condition')" class="flex items-center gap-3">
                        <button @click="showPerGb = !showPerGb"
                            class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium transition-all border"
                            :class="showPerGb
                                ? 'bg-primary-500/10 border-primary-500/30 text-primary-400'
                                : 'bg-surface-900 border-surface-700 text-text-secondary hover:text-white'">
                            <component :is="showPerGb ? ToggleRight : ToggleLeft" :size="18" />
                            Tampilkan per GB
                        </button>
                    </div>

                    <!-- Toggle Brand Breakdowns -->
                    <div v-else-if="currentView === 'brand'" class="flex items-center gap-3">
                        <button @click="showBrandType = !showBrandType; if(showBrandType) showBrandCondition = false"
                            class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium transition-all border"
                            :class="showBrandType
                                ? 'bg-primary-500/10 border-primary-500/30 text-primary-400'
                                : 'bg-surface-900 border-surface-700 text-text-secondary hover:text-white'">
                            <component :is="showBrandType ? ToggleRight : ToggleLeft" :size="18" />
                            Tampilkan per Tipe
                        </button>
                        <button @click="showBrandCondition = !showBrandCondition; if(showBrandCondition) showBrandType = false"
                            class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium transition-all border"
                            :class="showBrandCondition
                                ? 'bg-primary-500/10 border-primary-500/30 text-primary-400'
                                : 'bg-surface-900 border-surface-700 text-text-secondary hover:text-white'">
                            <component :is="showBrandCondition ? ToggleRight : ToggleLeft" :size="18" />
                            Tampilkan per Kondisi
                        </button>
                    </div>
                    <div v-else></div>

                    <!-- Search -->
                    <div class="relative w-full sm:w-72">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary" :size="18" />
                        <input v-model="searchQuery" type="text" placeholder="Cari..."
                            class="w-full bg-surface-900 border border-surface-700 rounded-xl py-2 pl-10 pr-4 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition-all placeholder:text-text-secondary" />
                    </div>
                </div>
            </div>
        </template>

        <!-- ==================== BRAND REPORT ==================== -->
        <template v-if="currentView === 'brand'">
            <div class="bg-surface-800 rounded-2xl border border-surface-700 overflow-hidden">
                <div v-if="loading" class="p-12 flex justify-center items-center">
                    <RefreshCw class="animate-spin text-primary-500" :size="32" />
                    <span class="ml-3 text-text-secondary">Memuat data...</span>
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-text-primary">
                        <thead class="bg-surface-900/50 text-text-secondary uppercase text-xs font-semibold">
                            <tr>
                                <th class="px-6 py-4">#</th>
                                <th class="px-6 py-4">Brand</th>
                                <th v-if="showBrandType" class="px-6 py-4">Tipe Produk</th>
                                <th v-if="showBrandCondition" class="px-6 py-4">Kondisi</th>
                                <th class="px-6 py-4 text-center">Tersedia</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-700/50">
                            <template v-for="(row, idx) in filteredBrand" :key="row.brand">
                                <!-- Main Row -->
                                <tr class="hover:bg-surface-700/30 transition-colors" :class="{ 'bg-surface-900/30': showBrandType || showBrandCondition }">
                                    <td class="px-6 py-4 text-text-secondary text-xs">{{ idx + 1 }}</td>
                                    <td class="px-6 py-4 font-bold text-white">{{ row.brand }}</td>
                                    <td v-if="showBrandType || showBrandCondition" class="px-6 py-4 text-text-secondary italic text-xs">—</td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="text-lg font-bold" :class="row.available > 0 ? 'text-emerald-400' : 'text-red-400'">{{ row.available }}</span>
                                    </td>
                                </tr>
                                <!-- Type Breakdown -->
                                <tr v-if="showBrandType" v-for="type in row.typeBreakdown" :key="type.label"
                                    class="bg-surface-900/20 hover:bg-surface-700/20 transition-colors">
                                    <td class="px-6 py-2.5"></td>
                                    <td class="px-6 py-2.5"></td>
                                    <td class="px-6 py-2.5">
                                        <span class="text-xs font-bold text-text-primary">{{ type.label }}</span>
                                    </td>
                                    <td class="px-6 py-2.5 text-center text-sm font-semibold text-emerald-400/80">{{ type.available }}</td>
                                </tr>
                                <!-- Condition Breakdown -->
                                <tr v-if="showBrandCondition" v-for="cond in row.conditionBreakdown" :key="cond.label"
                                    class="bg-surface-900/20 hover:bg-surface-700/20 transition-colors">
                                    <td class="px-6 py-2.5"></td>
                                    <td class="px-6 py-2.5"></td>
                                    <td class="px-6 py-2.5">
                                        <span class="px-2 py-0.5 rounded-lg text-[10px] font-bold border" :class="conditionColor(cond.condition)">
                                            {{ cond.label }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-2.5 text-center text-sm font-semibold text-emerald-400/80">{{ cond.available }}</td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot class="bg-surface-900/70 border-t border-surface-600">
                            <tr class="font-bold">
                                <td class="px-6 py-4 text-right text-text-secondary" :colspan="showBrandType || showBrandCondition ? 3 : 2">TOTAL</td>
                                <td class="px-6 py-4 text-center text-emerald-400 text-lg">{{ filteredBrand.reduce((s, r) => s + r.available, 0) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </template>

        <!-- ==================== TYPE REPORT ==================== -->
        <template v-if="currentView === 'type'">
            <div class="bg-surface-800 rounded-2xl border border-surface-700 overflow-hidden">
                <div v-if="loading" class="p-12 flex justify-center items-center">
                    <RefreshCw class="animate-spin text-primary-500" :size="32" />
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-text-primary">
                        <thead class="bg-surface-900/50 text-text-secondary uppercase text-xs font-semibold">
                            <tr>
                                <th class="px-6 py-4">#</th>
                                <th class="px-6 py-4">Tipe</th>
                                <th class="px-6 py-4">Brand</th>
                                <th v-if="showPerGb" class="px-6 py-4">Kapasitas</th>
                                <th class="px-6 py-4 text-center">Tersedia</th>
                                <th class="px-6 py-4 text-center">Tersedia</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-700/50">
                            <template v-for="(row, idx) in filteredType" :key="row.name + row.brand">
                                <!-- Main Row -->
                                <tr class="hover:bg-surface-700/30 transition-colors" :class="{ 'bg-surface-900/30': showPerGb }">
                                    <td class="px-6 py-4 text-text-secondary text-xs">{{ idx + 1 }}</td>
                                    <td class="px-6 py-4 font-bold text-white">{{ row.name }}</td>
                                    <td class="px-6 py-4 text-text-secondary">{{ row.brand }}</td>
                                    <td v-if="showPerGb" class="px-6 py-4 text-text-secondary italic text-xs">—</td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="text-lg font-bold" :class="row.available > 0 ? 'text-emerald-400' : 'text-red-400'">{{ row.available }}</span>
                                    </td>
                                </tr>
                                <!-- GB Sub-rows -->
                                <tr v-if="showPerGb" v-for="gb in row.gbBreakdown" :key="gb.label"
                                    class="bg-surface-900/20 hover:bg-surface-700/20 transition-colors">
                                    <td class="px-6 py-2.5"></td>
                                    <td class="px-6 py-2.5"></td>
                                    <td class="px-6 py-2.5"></td>
                                    <td class="px-6 py-2.5">
                                        <span class="px-2.5 py-1 rounded-lg text-xs font-bold bg-primary-500/10 text-primary-400 border border-primary-500/20">
                                            {{ gb.label }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-2.5 text-center text-sm font-semibold" :class="gb.available > 0 ? 'text-emerald-400' : 'text-red-400'">{{ gb.available }}</td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot class="bg-surface-900/70 border-t border-surface-600">
                            <tr class="font-bold">
                                <td class="px-6 py-4 text-right text-text-secondary" :colspan="showPerGb ? 4 : 3">TOTAL</td>
                                <td class="px-6 py-4 text-center text-emerald-400 text-lg">{{ filteredType.reduce((s, r) => s + r.available, 0) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </template>

        <!-- ==================== CONDITION REPORT ==================== -->
        <template v-if="currentView === 'condition'">
            <div class="bg-surface-800 rounded-2xl border border-surface-700 overflow-hidden">
                <div v-if="loading" class="p-12 flex justify-center items-center">
                    <RefreshCw class="animate-spin text-primary-500" :size="32" />
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-text-primary">
                        <thead class="bg-surface-900/50 text-text-secondary uppercase text-xs font-semibold">
                            <tr>
                                <th class="px-6 py-4">#</th>
                                <th class="px-6 py-4">Kondisi</th>
                                <th v-if="showPerGb" class="px-6 py-4">Kapasitas</th>
                                <th class="px-6 py-4 text-center">Tersedia</th>
                                <th class="px-6 py-4 text-center">Tersedia</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-700/50">
                            <template v-for="(row, idx) in filteredCondition" :key="row.condition">
                                <tr class="hover:bg-surface-700/30 transition-colors" :class="{ 'bg-surface-900/30': showPerGb }">
                                    <td class="px-6 py-4 text-text-secondary text-xs">{{ idx + 1 }}</td>
                                    <td class="px-6 py-4">
                                        <span class="px-3 py-1.5 rounded-xl text-xs font-bold border" :class="conditionColor(row.condition)">
                                            {{ row.label }}
                                        </span>
                                    </td>
                                    <td v-if="showPerGb" class="px-6 py-4 text-text-secondary italic text-xs">—</td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="text-lg font-bold" :class="row.available > 0 ? 'text-emerald-400' : 'text-red-400'">{{ row.available }}</span>
                                    </td>
                                </tr>
                                <tr v-if="showPerGb" v-for="gb in row.gbBreakdown" :key="gb.label"
                                    class="bg-surface-900/20 hover:bg-surface-700/20 transition-colors">
                                    <td class="px-6 py-2.5"></td>
                                    <td class="px-6 py-2.5"></td>
                                    <td class="px-6 py-2.5">
                                        <span class="px-2.5 py-1 rounded-lg text-xs font-bold bg-primary-500/10 text-primary-400 border border-primary-500/20">
                                            {{ gb.label }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-2.5 text-center text-sm font-semibold" :class="gb.available > 0 ? 'text-emerald-400' : 'text-red-400'">{{ gb.available }}</td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot class="bg-surface-900/70 border-t border-surface-600">
                            <tr class="font-bold">
                                <td class="px-6 py-4 text-right text-text-secondary" :colspan="showPerGb ? 3 : 2">TOTAL</td>
                                <td class="px-6 py-4 text-center text-emerald-400 text-lg">{{ filteredCondition.reduce((s, r) => s + r.available, 0) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </template>

        <!-- ==================== DISTRIBUTOR REPORT ==================== -->
        <template v-if="currentView === 'distributor'">
            <div class="bg-surface-800 rounded-2xl border border-surface-700 overflow-hidden">
                <div v-if="loading" class="p-12 flex justify-center items-center">
                    <RefreshCw class="animate-spin text-primary-500" :size="32" />
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-text-primary">
                        <thead class="bg-surface-900/50 text-text-secondary uppercase text-xs font-semibold">
                            <tr>
                                <th class="px-6 py-4">#</th>
                                <th class="px-6 py-4">Distributor</th>
                                <th class="px-6 py-4 text-center">Tersedia</th>
                                <th class="px-6 py-4 text-center">Tersedia</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-700/50">
                            <tr v-for="(row, idx) in filteredDistributor" :key="row.name" class="hover:bg-surface-700/30 transition-colors">
                                <td class="px-6 py-4 text-text-secondary text-xs">{{ idx + 1 }}</td>
                                <td class="px-6 py-4 font-bold text-white">{{ row.name }}</td>
                                <td class="px-6 py-4 text-center">
                                    <span class="text-lg font-bold" :class="row.available > 0 ? 'text-emerald-400' : 'text-red-400'">{{ row.available }}</span>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-surface-900/70 border-t border-surface-600">
                            <tr class="font-bold">
                                <td class="px-6 py-4 text-right text-text-secondary" colspan="2">TOTAL</td>
                                <td class="px-6 py-4 text-center text-emerald-400 text-lg">{{ filteredDistributor.reduce((s, r) => s + r.available, 0) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </template>


    </div>
</template>

<style scoped>
.animate-in {
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
