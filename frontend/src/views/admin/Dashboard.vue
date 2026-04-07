<script setup>
import { ref, onMounted, computed } from "vue";
import { useAuthStore } from "../../store/auth";
import { formatCurrency, formatNumber } from "../../utils/formatters";
import api, { users as usersApi } from "../../api/axios";
import {
  LayoutDashboard,
  TrendingUp,
  TrendingDown,
  DollarSign,
  ShoppingCart,
  Package,
  Users,
  Database,
  Tags,
  Box,
  Award,
  Trophy,
  Medal,
  User,
  Store,
  Globe,
  ChevronUp,
  ChevronDown,
  MapPin,
  Sparkles
} from "lucide-vue-next";

const authStore = useAuthStore();
const storageBaseUrl = computed(() => authStore.storageBaseUrl);
const dashboardRole = ref('general');
const recentTypes = ref([]);
const recentPrices = ref([]);
const recentTransactions = ref([]);
const brandSales = ref([]);
const typeSales = ref([]);
const csPerformance = ref([]);
const ranking = ref({ my_rank: '-', leaderboard: [] });
const branchRanking = ref(null);

const usersList = ref([]);
const usersMap = computed(() => {
  const map = {};
  const list = Array.isArray(usersList.value) ? usersList.value : (usersList.value?.data || []);
  list.forEach(u => {
    if (u.name) map[u.name.toLowerCase()] = u;
    if (u.full_name) map[u.full_name.toLowerCase()] = u;
  });
  return map;
});

const fetchUsers = async () => {
    try {
        const response = await usersApi.list();
        // Handle both raw array and nested data object
        usersList.value = response.data?.data || response.data || [];
    } catch (error) {
        console.error('Failed to fetch users for photo mapping', error);
    }
};

// Determine initial role immediately to prevent flash of wrong dashboard
if (authStore.hasRole('admin_produk')) {
  dashboardRole.value = 'admin_produk';
} else if (authStore.hasRole('online_shop') || authStore.hasRole('toko_online') || authStore.user?.online_shop_id) {
  dashboardRole.value = 'online_shop';
} else if (authStore.hasRole('toko_offline') || authStore.hasRole('offline_shop') || authStore.user?.branch_id) {
  dashboardRole.value = 'toko_offline';
}

// Stats data (Initial static data for general, overwritten for other roles)
const initialStats = [
  {
    id: 1,
    label: "Pendapatan Hari Ini",
    value: 85750000,
    change: 12.5,
    trend: "up",
    icon: DollarSign,
    color: "blue",
  },
  {
    id: 2,
    label: "Total Transaksi",
    value: 342,
    change: 8.2,
    trend: "up",
    icon: ShoppingCart,
    color: "emerald",
  },
  {
    id: 3,
    label: "Produk Terjual",
    value: 1247,
    change: -3.1,
    trend: "down",
    icon: Package,
    color: "violet",
  },
  {
    id: 4,
    label: "Pelanggan Baru",
    value: 28,
    change: 15.8,
    trend: "up",
    icon: Users,
    color: "amber",
  },
];

// If role is determined, start with empty/loading stats to avoid showing static general stats
const stats = ref(dashboardRole.value === 'general' ? initialStats : []);

// ... Top Products, Low Stock, etc. (Keep existing for general role)
const topProducts = ref([
  { id: 1, name: "iPhone 15 Pro Max", sold: 45, revenue: 989955000 },
  { id: 2, name: "Samsung S24 Ultra", sold: 38, revenue: 759962000 },
  { id: 3, name: "MacBook Air M3", sold: 22, revenue: 384978000 },
  { id: 4, name: 'iPad Pro 12.9"', sold: 18, revenue: 305982000 },
  { id: 5, name: "AirPods Pro 2", sold: 67, revenue: 267933000 },
]);

const lowStockItems = ref([
  { id: 1, name: "Google Pixel 8 Pro", stock: 3, minStock: 5 },
  { id: 2, name: "Sony WH-1000XM5", stock: 4, minStock: 8 },
  { id: 3, name: "Samsung Galaxy Z Fold 5", stock: 2, minStock: 5 },
]);

// Initial static recent transactions for General Dashboard
const initialRecentTransactions = [
  {
    id: "TRX-001",
    customer: "Ahmad Rizki",
    total: 21999000,
    time: "2 menit lalu",
    status: "success",
  },
  {
    id: "TRX-002",
    customer: "Budi Santoso",
    total: 3999000,
    time: "15 menit lalu",
    status: "success",
  },
  {
    id: "TRX-003",
    customer: "Citra Dewi",
    total: 18499000,
    time: "32 menit lalu",
    status: "success",
  },
  {
    id: "TRX-004",
    customer: "Dimas Pratama",
    total: 5499000,
    time: "1 jam lalu",
    status: "pending",
  },
];

// Initialize properly
if (dashboardRole.value === 'general') {
  recentTransactions.value = initialRecentTransactions;
}

const branchPerformance = ref([
  { name: "Pusat Jakarta", revenue: 125000000, target: 150000000 },
  { name: "Cabang Bandung", revenue: 89000000, target: 100000000 },
  { name: "Cabang Surabaya", revenue: 78000000, target: 80000000 },
  { name: "Cabang Medan", revenue: 45000000, target: 60000000 },
]);

const isLoading = ref(false);
const podiumTab = ref('today'); // 'today' | 'this_month' | 'last_month'

const resolveIcon = (name) => {
  const icons = { Database, Tags, Box, DollarSign, Package, ShoppingCart, Users };
  return icons[name] || Package;
};

const resolvePhoto = (user, name) => {
  // 1. Try to find user in our detailed usersMap first (more likely to have photo)
  const mappedUser = name ? usersMap.value[name.toLowerCase()] : null;
  
  // 2. Check multiple potential photo fields for robustness (from mapped user or provided user)
  const source = mappedUser || user;
  const photo = source?.photo || source?.photo_inventory || source?.avatar || source?.profile_photo || source?.image;
  
  if (!photo) return `https://ui-avatars.com/api/?name=${encodeURIComponent(name || 'User')}&background=10b981&color=fff&size=128`;
  if (photo.startsWith('http')) return photo;
  return `${storageBaseUrl.value}/storage/${photo}`;
};

async function fetchDashboardData() {
  isLoading.value = true;
  try {
    const response = await api.get('/dashboard');
    if (response.data.role === 'admin_produk') {
      dashboardRole.value = 'admin_produk';
      stats.value = response.data.stats.map(s => ({
        ...s,
        icon: resolveIcon(s.icon),
        change: 0,
        trend: 'neutral'
      }));
      recentTypes.value = response.data.recent_types;
      recentPrices.value = response.data.recent_prices;
    } else if (response.data.role === 'online_shop' || response.data.role === 'toko_online' || response.data.role === 'toko_offline') {
        dashboardRole.value = response.data.role === 'toko_online' ? 'online_shop' : response.data.role;
      stats.value = response.data.stats.map(s => ({
        ...s,
        icon: resolveIcon(s.icon),
        change: 0,
        trend: 'neutral'
      }));
      recentTransactions.value = response.data.recentTransactions || [];
      ranking.value = response.data.ranking || { my_rank: '-', leaderboard: [] };
      branchRanking.value = response.data.branch_ranking;
      // Optional fields for online_shop and toko_offline might differ slightly, handle gracefully
      brandSales.value = response.data.brandSales || [];
      typeSales.value = response.data.typeSales || [];
      csPerformance.value = response.data.csPerformance || [];

      // Unified ranking fallback for online_shop/toko_online
      if (dashboardRole.value === 'online_shop' || dashboardRole.value === 'toko_online') {
        const hasNoUnits = !ranking.value.leaderboard || 
                          ranking.value.leaderboard.length === 0 || 
                          ranking.value.leaderboard.every(u => !(u.units || u.total_sales || u.count || u.sold || (u.hp_count + (u.non_hp_count || 0))));
        
        if (hasNoUnits && csPerformance.value.length > 0) {
          ranking.value.leaderboard = csPerformance.value.map((cs, idx) => ({
            ...cs,
            units: (cs.hp_count || 0) + (cs.non_hp_count || cs.acc_count || 0),
            revenue: cs.total_sales || cs.omset,
            rank: idx + 1
          })).sort((a, b) => (b.units || 0) - (a.units || 0));
        }
      }
    } else {
      dashboardRole.value = 'general';
      // Logic for other roles or keep static
    }
  } catch (error) {
    console.error('Failed to fetch dashboard data', error);
  } finally {
    isLoading.value = false;
  }
}

function refreshData() {
  fetchDashboardData();
}

onMounted(() => {
  fetchDashboardData();
  fetchUsers();
});

const getColorClasses = (color) => {
  return {
    bg: "bg-primary-500/20",
    text: "text-primary-600 dark:text-primary-400",
    icon: "bg-primary-500 text-white",
  };
};

const rightPodiumData = computed(() => {
  if (!branchRanking.value) return null;
  if (podiumTab.value === 'today') return branchRanking.value.yesterday;
  if (podiumTab.value === 'this_month') return branchRanking.value.last_month;
  return null;
});

const rightPodiumTitle = computed(() => {
  if (podiumTab.value === 'today') return "Yesterday's Top 3 Champions";
  return "Last Month's Top 3 Champions";
});

const rightPodiumSubtitle = computed(() => {
  if (podiumTab.value === 'today') return "Yesterday's Global Leaders";
  return "Historical Global Leaders";
});
</script>

<template>
  <div class="space-y-6 animate-in">
    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between md:items-end gap-4">
      <div>
        <h1 class="text-2xl font-bold text-text-primary tracking-tight">
          Executive Overview
        </h1>
        <p class="text-text-secondary mt-1">
          Real-time data dari 60+ cabang •
          <span class="text-emerald-500 dark:text-emerald-400">● Online</span>
        </p>
      </div>
      <button @click="refreshData" class="btn btn-secondary w-full md:w-auto" :disabled="isLoading">
        <RefreshCw :size="16" :class="{ 'animate-spin': isLoading }" />
        Refresh Data
      </button>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
      <template v-if="isLoading && stats.length === 0">
        <div v-for="i in 4" :key="i" class="card h-32 animate-pulse bg-surface-700/50"></div>
      </template>
      <div v-else v-for="stat in stats" :key="stat.id" class="stat-card">
        <div class="flex items-start justify-between mb-4">
          <div class="stat-icon" :class="getColorClasses(stat.color).icon">
            <component :is="stat.icon" :size="20" class="text-white" />
          </div>
          <div v-if="stat.trend" class="flex items-center gap-1 text-xs font-semibold px-2 py-1 rounded-full" :class="stat.trend === 'up'
            ? 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400'
            : (stat.trend === 'down' ? 'bg-red-500/20 text-red-600 dark:text-red-400' : 'bg-surface-500/20 text-text-secondary')
            ">
            <TrendingUp v-if="stat.trend === 'up'" :size="12" />
            <TrendingDown v-else-if="stat.trend === 'down'" :size="12" />
            <span v-else>-</span>
            {{ Math.abs(stat.change) }}%
          </div>
        </div>
        <p class="text-text-secondary text-sm font-medium">{{ stat.label }}</p>
        <p class="text-2xl font-bold text-text-primary mt-1">
          {{
            stat.isCurrency || stat.label.includes("Pendapatan")
              ? formatCurrency(stat.value)
              : formatNumber(stat.value)
          }}
          <span v-if="stat.sub" class="text-xs text-text-secondary font-normal ml-2">{{ stat.sub }}</span>
        </p>
      </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6" v-if="dashboardRole === 'admin_produk'">
      <!-- Recent Types (Admin Produk) -->
      <div class="card">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-lg font-semibold text-text-primary">
            Tipe Produk Terbaru
          </h2>
          <router-link to="/types"
            class="text-sm text-primary-500 hover:text-primary-600 dark:text-blue-400 dark:hover:text-blue-300 transition-colors">
            Lihat Semua
          </router-link>
        </div>
        <div class="space-y-4">
          <div v-for="(type, index) in recentTypes" :key="type.id"
            class="flex items-center gap-4 p-3 rounded-xl hover:bg-surface-700/50 transition-colors">
            <div
              class="w-8 h-8 rounded-lg bg-surface-700 flex items-center justify-center text-sm font-bold text-text-secondary shrink-0">
              {{ index + 1 }}
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-text-primary font-medium truncate">
                {{ type.name }}
              </p>
              <p class="text-sm text-text-secondary">
                {{ type.brand_name }} • {{ type.category_name }}
              </p>
            </div>
            <div class="text-right">
              <p class="text-xs text-text-secondary">
                {{ type.created_at }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent Prices (Admin Produk) -->
      <div class="card">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-lg font-semibold text-text-primary">
            Update Harga Terakhir
          </h2>
          <router-link to="/prices"
            class="text-sm text-primary-500 hover:text-primary-600 dark:text-blue-400 dark:hover:text-blue-300 transition-colors">
            Lihat Semua
          </router-link>
        </div>
        <div class="space-y-4">
          <div v-for="price in recentPrices" :key="price.id"
            class="flex items-center gap-4 p-3 rounded-xl hover:bg-surface-700/50 transition-colors">
            <div
              class="w-10 h-10 rounded-lg bg-surface-700 flex items-center justify-center text-sm font-bold text-text-secondary shrink-0">
              <DollarSign :size="18" />
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-text-primary font-medium truncate">
                {{ price.name }}
              </p>
              <span class="text-[10px] px-2 py-0.5 rounded bg-surface-600 text-text-secondary">
                {{ price.condition }}
              </span>
            </div>
            <div class="text-right">
              <p class="text-text-primary font-semibold">
                {{ formatCurrency(price.price) }}
              </p>
              <p class="text-xs text-text-secondary">
                {{ price.updated_at }}
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Ranking & Leaderboard (Online & Offline) -->
    <div v-if="dashboardRole === 'online_shop' || dashboardRole === 'toko_offline'"
      class="space-y-6">
      
      <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <!-- LEFT: Branch Competition Podium -->
        <div v-if="branchRanking" class="xl:col-span-2 card overflow-hidden bg-white dark:bg-[#0d0d0d] border-none p-0 flex flex-col shadow-2xl relative min-h-[400px]">
          <!-- Deco -->
          <div class="absolute -top-24 -left-24 w-48 h-48 bg-emerald-500/10 rounded-full blur-[100px]"></div>
          <div class="absolute -bottom-24 -right-24 w-48 h-48 bg-emerald-500/5 rounded-full blur-[100px]"></div>

          <div class="relative z-10 h-full flex flex-col">
            <!-- Header -->
            <div class="px-8 py-6 flex flex-col md:flex-row items-center justify-between border-b border-black/5 dark:border-white/5 gap-4">
              <div class="flex items-center gap-3">
                <div class="w-3 h-3 rounded-full bg-emerald-500 animate-pulse"></div>
                <h2 class="text-xs font-black text-emerald-500 uppercase tracking-[0.4em]">Sales Performance Podium</h2>
              </div>
              
              <!-- Tabs -->
              <div class="flex bg-black/5 dark:bg-white/5 p-1 rounded-xl shadow-inner">
                 <button v-for="tab in [{id: 'today', lab: 'Harian'}, {id: 'this_month', lab: 'Bulan Ini'}]" :key="tab.id" @click="podiumTab = tab.id" class="px-4 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest transition-all duration-300" :class="podiumTab === tab.id ? 'bg-emerald-500 text-black shadow-lg' : 'text-text-secondary hover:text-emerald-500'">{{ tab.lab }}</button>
              </div>
            </div>

            <div class="p-8 pb-16 flex-1 flex flex-col justify-center">
              <div class="flex flex-col md:flex-row items-center md:items-end justify-center gap-12 md:gap-6 lg:gap-12 pt-32 md:pt-20 pb-4">
                <!-- Left Slot -->
                <div class="flex flex-col items-center w-full md:w-1/3 max-w-[180px] order-2 md:order-1">
                  <template v-if="branchRanking[podiumTab]?.podium[0]">
                    <div class="relative group">
                      <div class="absolute -top-10 left-1/2 -translate-x-1/2 text-[10px] font-black text-black/30 dark:text-white/30 bg-black/5 dark:bg-white/5 px-3 py-1 rounded-full">#{{ branchRanking[podiumTab].podium[0].rank }}</div>
                      <div class="w-24 h-24 rounded-[2.2rem] bg-white dark:bg-[#1a1a1a] border border-black/10 dark:border-white/10 flex items-center justify-center mb-4 shadow-xl transition-all duration-500 group-hover:-translate-y-2 group-hover:border-emerald-500/50">
                        <component :is="branchRanking[podiumTab].podium[0].type === 'branch' ? Store : Globe" class="w-12 h-12 text-black/60 dark:text-white/60" />
                      </div>
                    </div>
                    <div class="text-center">
                      <p class="text-[11px] font-black text-black/60 dark:text-white/60 uppercase truncate w-full px-2 leading-none">{{ branchRanking[podiumTab].podium[0].name }}</p>
                      <div class="mt-3 text-[10px] font-mono text-black/50 dark:text-white/50 bg-black/5 dark:bg-white/5 px-4 py-1 rounded-full">Rp {{ formatNumber(branchRanking[podiumTab].podium[0].omset) }}</div>
                    </div>
                  </template>
                </div>

                <!-- Center Slot -->
                <div class="flex flex-col items-center w-full md:w-2/5 max-w-[320px] relative md:-top-12 order-1 md:order-2 mb-8 md:mb-0">
                  <div class="absolute -top-20 left-1/2 -translate-x-1/2 scale-[1.5]">
                    <div class="relative"><div class="absolute inset-0 bg-emerald-500/60 blur-3xl rounded-full"></div><Trophy :size="28" class="text-emerald-500 relative z-10 animate-bounce" /></div>
                  </div>
                  <div class="relative group w-full">
                    <div class="absolute -top-10 left-1/2 -translate-x-1/2 text-xs font-black text-emerald-500 bg-emerald-500/10 border border-emerald-500/20 px-5 py-1.5 rounded-full z-20 uppercase tracking-[0.2em]">RANK #{{ branchRanking[podiumTab].podium[1].rank }}</div>
                    <div class="w-full aspect-square max-w-[220px] md:max-w-[280px] mx-auto rounded-[3.5rem] bg-white dark:bg-[#1a1a1a] border-[5px] border-emerald-500/50 flex flex-col items-center justify-center mb-6 shadow-2xl relative overflow-hidden group-hover:border-emerald-500 transition-all duration-700">
                      <component :is="branchRanking[podiumTab].podium[1].type === 'branch' ? Store : Globe" class="w-24 h-24 text-black dark:text-white relative z-10 transition-transform group-hover:scale-110" />
                      <div class="absolute bottom-0 inset-x-0 bg-emerald-500 py-2.5 flex items-center justify-center"><span class="text-[9px] font-black text-black uppercase tracking-[0.4em]">RANKING UNIT</span></div>
                    </div>
                  </div>
                  <div class="text-center w-full px-2">
                    <h3 class="text-xl md:text-2xl font-black text-text-primary uppercase tracking-tight mb-4 truncate">{{ branchRanking[podiumTab].podium[1].name }}</h3>
                    <div class="bg-emerald-500 rounded-[2.5rem] p-4 shadow-xl border border-emerald-400/30">
                      <p class="text-[9px] font-black text-black/60 uppercase mb-1">TOTAL OMSET</p>
                      <p class="text-2xl font-black text-black">Rp {{ formatNumber(branchRanking[podiumTab].podium[1].omset) }}</p>
                    </div>
                  </div>
                </div>

                <!-- Right Slot -->
                <div class="flex flex-col items-center w-full md:w-1/3 max-w-[180px] order-3 md:order-3">
                  <template v-if="branchRanking[podiumTab]?.podium[2]">
                    <div class="relative group">
                      <div class="absolute -top-10 left-1/2 -translate-x-1/2 text-[10px] font-black text-black/30 dark:text-white/30 bg-black/5 dark:bg-white/5 px-3 py-1 rounded-full">#{{ branchRanking[podiumTab].podium[2].rank }}</div>
                      <div class="w-20 h-20 rounded-[2rem] bg-white dark:bg-[#1a1a1a] border border-black/10 dark:border-white/10 flex items-center justify-center mb-4 shadow-xl transition-all duration-500 group-hover:-translate-y-2 group-hover:border-emerald-500/50">
                        <component :is="branchRanking[podiumTab].podium[2].type === 'branch' ? Store : Globe" class="w-10 h-10 text-black/60 dark:text-white/60" />
                      </div>
                    </div>
                    <div class="text-center">
                      <p class="text-[11px] font-black text-black/60 dark:text-white/60 uppercase truncate w-full px-2 leading-none">{{ branchRanking[podiumTab].podium[2].name }}</p>
                      <div class="mt-3 text-[10px] font-mono text-black/40 dark:text-white/40 bg-black/5 dark:bg-white/5 px-4 py-1 rounded-full">Rp {{ formatNumber(branchRanking[podiumTab].podium[2].omset) }}</div>
                    </div>
                  </template>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- RIGHT: Historical TOP 3 Champions (Yesterday or Last Month) -->
        <div v-if="rightPodiumData" class="xl:col-span-1 flex flex-col">
          <div class="card overflow-hidden bg-white dark:bg-[#0d0d0d] border-none p-0 flex flex-col shadow-xl relative flex-1 min-h-[400px]">
            <div class="absolute top-0 right-0 w-32 h-32 bg-amber-500/5 rounded-full blur-3xl"></div>
            <div class="relative z-10 h-full flex flex-col">
              <div class="px-6 py-6 border-b border-black/5 dark:border-white/5">
                  <div class="flex items-center gap-3 mb-1"><Trophy :size="18" class="text-amber-500" /><h2 class="text-[10px] font-black text-amber-500 uppercase tracking-[0.3em]">{{ rightPodiumTitle }}</h2></div>
                  <p class="text-[9px] font-bold text-black/30 dark:text-white/30 uppercase tracking-widest">{{ rightPodiumSubtitle }}</p>
              </div>
              <div class="p-6 flex-1 flex flex-col justify-center">
                <!-- Refined Stack Podium -->
                <div class="flex flex-col items-center gap-14 py-8">
                  <!-- CHAMPION (Rank #1) -->
                  <div v-if="rightPodiumData.podium[0]" class="w-full max-w-[240px] relative group">
                    <!-- Repositioned Trophy -->
                    <div class="absolute -top-14 left-1/2 -translate-x-1/2 z-20">
                      <div class="relative">
                        <div class="absolute inset-0 bg-amber-500/20 blur-xl rounded-full scale-150"></div>
                        <Trophy :size="32" class="text-amber-500 relative z-10 animate-bounce" />
                      </div>
                    </div>
                    
                    <div class="relative flex flex-col items-center">
                      <div class="absolute -top-6 left-1/2 -translate-x-1/2 text-[9px] font-black text-amber-500 bg-amber-500/10 border border-amber-500/20 px-4 py-1 rounded-full uppercase tracking-[0.2em] z-10 whitespace-nowrap shadow-xl">CHAMPION</div>
                      
                      <div class="w-28 h-28 rounded-[2.8rem] bg-white dark:bg-[#151515] border-[4px] border-amber-500/40 flex items-center justify-center mb-5 shadow-2xl relative overflow-hidden group-hover:border-amber-500 transition-all duration-700">
                        <div class="absolute inset-0 bg-gradient-to-br from-amber-500/5 to-transparent"></div>
                        <component :is="rightPodiumData.podium[0].type === 'branch' ? Store : Globe" class="w-14 h-14 text-text-primary dark:text-white relative z-10 transition-transform duration-500 group-hover:scale-110" />
                        <div class="absolute bottom-0 inset-x-0 bg-amber-500 py-1.5 flex items-center justify-center shadow-[0_-2px_10px_rgba(0,0,0,0.2)]">
                          <span class="text-[8px] font-black text-black uppercase tracking-[0.3em]">RANK #1</span>
                        </div>
                      </div>
                      
                      <div class="text-center w-full px-2">
                        <h4 class="text-sm font-black text-text-primary uppercase tracking-tight mb-2 truncate group-hover:text-amber-500 transition-colors">{{ rightPodiumData.podium[0].name }}</h4>
                        <div class="inline-flex items-center gap-2 bg-black/5 dark:bg-white/5 border border-amber-500/20 rounded-full px-5 py-1.5 shadow-inner">
                          <p class="text-xs font-black text-amber-500">Rp {{ formatNumber(rightPodiumData.podium[0].omset) }}</p>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- CONTENDERS (Rank #2 & #3) -->
                  <div class="w-full flex flex-col gap-8 max-w-[280px]">
                    <!-- Rank #2 -->
                    <div v-if="rightPodiumData.podium[1]" class="relative group">
                      <div class="flex items-center gap-4 p-4 rounded-2xl bg-black/[0.02] dark:bg-white/[0.02] border border-black/5 dark:border-white/5 hover:border-slate-400/30 transition-all duration-300">
                        <div class="w-12 h-12 rounded-xl bg-slate-400/10 flex items-center justify-center font-black text-slate-400 group-hover:scale-110 transition-transform">#2</div>
                        <div class="flex-1 min-w-0">
                          <p class="text-[10px] font-black text-text-primary uppercase truncate mb-0.5">{{ rightPodiumData.podium[1].name }}</p>
                          <p class="text-[9px] font-mono text-slate-500 font-bold">Rp {{ formatNumber(rightPodiumData.podium[1].omset) }}</p>
                        </div>
                        <component :is="rightPodiumData.podium[1].type === 'branch' ? Store : Globe" class="w-5 h-5 text-slate-400/40" />
                      </div>
                    </div>

                    <!-- Rank #3 -->
                    <div v-if="rightPodiumData.podium[2]" class="relative group">
                      <div class="flex items-center gap-4 p-4 rounded-2xl bg-black/[0.02] dark:bg-white/[0.02] border border-black/5 dark:border-white/5 hover:border-amber-700/30 transition-all duration-300">
                        <div class="w-12 h-12 rounded-xl bg-amber-700/10 flex items-center justify-center font-black text-amber-700 group-hover:scale-110 transition-transform">#3</div>
                        <div class="flex-1 min-w-0">
                          <p class="text-[10px] font-black text-text-primary uppercase truncate mb-0.5">{{ rightPodiumData.podium[2].name }}</p>
                          <p class="text-[9px] font-mono text-amber-700 font-bold">Rp {{ formatNumber(rightPodiumData.podium[2].omset) }}</p>
                        </div>
                        <component :is="rightPodiumData.podium[2].type === 'branch' ? Store : Globe" class="w-5 h-5 text-amber-700/40" />
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="px-6 py-4 bg-amber-500/5 border-t border-amber-500/10 mt-auto"><span class="text-[8px] font-black text-amber-500/50 uppercase tracking-[0.3em]">Historical Data Room</span></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Leaderboard Table Card -->
      <div class="card overflow-hidden p-0">
        <div class="p-6 border-b border-surface-700/50 bg-surface-700/10 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <Trophy :size="24" class="text-amber-500" />
            <h2 class="text-lg font-black text-text-primary uppercase tracking-tight">Leaderboard Unit</h2>
          </div>
          <div class="bg-surface-800 px-3 py-1 rounded-full text-[10px] font-black text-text-secondary uppercase">TODAY</div>
        </div>
        <div class="p-6 overflow-x-auto">
          <table class="w-full text-sm text-left">
            <thead>
              <tr class="text-[10px] text-text-secondary uppercase tracking-widest font-black">
                <th class="pb-6 w-16">Rank</th>
                <th class="pb-6">Akun Inventory</th>
                <th class="pb-6 text-center">Unit Terjual</th>
                <th class="pb-6 text-right">Global Rank</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-surface-700">
              <tr v-for="(user, idx) in ranking.leaderboard" :key="user.id"
                class="group hover:bg-surface-700/20 transition-all duration-300">
                <td class="py-4">
                  <div class="w-8 h-8 rounded-xl flex items-center justify-center font-black text-sm"
                    :class="idx === 0 ? 'bg-amber-500 text-black shadow-lg shadow-amber-500/20' : (idx === 1 ? 'bg-slate-400 text-black' : (idx === 2 ? 'bg-amber-700 text-white' : 'text-text-secondary bg-surface-700'))">
                    {{ idx + 1 }}
                  </div>
                </td>
                <td class="py-4">
                  <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-full bg-primary-500/10 flex items-center justify-center border border-primary-500/20 overflow-hidden shrink-0 shadow-inner">
                      <img :src="resolvePhoto(user, user.name)"
                        class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110"
                        @error="(e) => e.target.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name || 'User')}&background=10b981&color=fff`" />
                    </div>
                    <span class="font-bold text-text-primary group-hover:text-primary-400 transition-colors">{{
                      user.name }}</span>
                  </div>
                </td>
                <td class="py-4 text-center">
                  <div class="flex flex-col">
                    <span class="text-base font-black text-text-primary">{{ user.units ?? user.count ?? user.sold ?? ((user.hp_count || 0) + (user.non_hp_count || 0)) ?? 0 }}</span>
                    <span class="text-[9px] font-bold text-text-secondary uppercase tracking-tighter">Unit Terjual</span>
                  </div>
                </td>
                <td class="py-4 text-right">
                   <div class="inline-flex items-center px-2 py-1 bg-surface-700 rounded-lg font-mono text-text-secondary text-xs">
                      #{{ user.rank ?? (idx + 1) }}
                   </div>
                </td>
              </tr>
              <tr v-if="!ranking?.leaderboard || ranking.leaderboard.length === 0">
                <td colspan="4" class="py-8 text-center text-text-secondary italic">
                  Belum ada data penjualan akun inventory hari ini
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        </div>
      </div>
    </div>

    <!-- Main Content Grid (Online Shop or Offline) -->
    <div class="grid grid-cols-1 gap-6" v-if="dashboardRole === 'online_shop' || dashboardRole === 'toko_offline'">
      <div class="card">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-lg font-semibold text-text-primary">
            Transaksi Terakhir (Today)
          </h2>
          <router-link to="/online-shop/history"
            class="text-sm text-primary-500 hover:text-primary-600 dark:text-blue-400 dark:hover:text-blue-300 transition-colors flex items-center gap-1">
            Lihat Semua
            <ArrowUpRight :size="14" />
          </router-link>
        </div>
        <!-- Recent Transactions (Desktop Table) -->
        <div class="hidden md:block overflow-x-auto">
          <table class="w-full text-sm text-left">
            <thead class="text-xs text-text-secondary uppercase bg-surface-700/50">
              <tr>
                <th class="px-4 py-3 rounded-l-lg">ID Resi</th>
                <th class="px-4 py-3">Customer</th>
                <th class="px-4 py-3">Item</th>
                <th class="px-4 py-3">Total</th>
                <th class="px-4 py-3">Waktu</th>
                <th class="px-4 py-3 rounded-r-lg">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-surface-700">
              <tr v-for="trx in recentTransactions" :key="trx.id" class="hover:bg-surface-700/30 transition-colors">
                <td class="px-4 py-3 font-mono font-medium text-primary-400">{{ trx.id }}</td>
                <td class="px-4 py-3 text-text-primary">{{ trx.customer }}</td>
                <td class="px-4 py-3 text-text-secondary max-w-xs truncate" :title="trx.items">{{ trx.items }}</td>
                <td class="px-4 py-3 font-bold text-emerald-400">{{ formatCurrency(trx.total) }}</td>
                <td class="px-4 py-3 text-text-secondary">
                  <div class="flex flex-col">
                    <span class="text-xs">{{ trx.datetime }}</span>
                    <span class="text-[10px] opacity-70">{{ trx.time }}</span>
                  </div>
                </td>
                <td class="px-4 py-3">
                  <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-500">
                    SUKSES
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Recent Transactions (Mobile Card List) -->
        <div class="md:hidden space-y-3">
          <div v-for="trx in recentTransactions" :key="trx.id"
            class="p-4 rounded-xl bg-surface-700/30 border border-surface-700 space-y-3">
            <div class="flex justify-between items-start">
              <div>
                <p class="text-[10px] text-primary-400 font-mono mb-0.5">#{{ trx.id }}</p>
                <p class="font-bold text-text-primary">{{ trx.customer }}</p>
              </div>
              <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-500 uppercase">
                Sukses
              </span>
            </div>
            <p class="text-xs text-text-secondary truncate">
              <Package :size="12" class="inline mr-1" /> {{ trx.items }}
            </p>
            <div class="flex justify-between items-end pt-2 border-t border-surface-700/50">
              <div class="text-[10px] text-text-secondary">
                <p>{{ trx.datetime }}</p>
                <p>{{ trx.time }}</p>
              </div>
              <p class="font-bold text-emerald-400">{{ formatCurrency(trx.total) }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- New Widgets Row -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Type Sales (Today) -->
        <div class="card">
          <div class="flex items-center justify-between mb-6">
            <h2 class="text-lg font-semibold text-text-primary">
              Top 5 Produk Laris (Hari Ini)
            </h2>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
              <thead class="text-xs text-text-secondary uppercase bg-surface-700/50">
                <tr>
                  <th class="px-4 py-3 rounded-l-lg">Produk / Model</th>
                  <th class="px-4 py-3 rounded-r-lg text-right">Unit Terjual</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-surface-700">
                <tr v-if="typeSales.length === 0">
                  <td colspan="2" class="px-4 py-8 text-center text-text-secondary italic">
                    Belum ada penjualan type hari ini
                  </td>
                </tr>
                <tr v-for="(item, idx) in typeSales" :key="idx" class="hover:bg-surface-700/30 transition-colors">
                  <td class="px-4 py-3 font-medium text-text-primary">{{ item.name }}</td>
                  <td class="px-4 py-3 text-right">
                    <span class="font-bold text-emerald-400">{{ item.count }}</span>
                    <span class="text-xs text-text-secondary ml-1">Unit</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Brand Sales (Today) - Modified to show Brand + Condition -->
        <div class="card">
          <div class="flex items-center justify-between mb-6">
            <h2 class="text-lg font-semibold text-text-primary">
              Total Brand Terjual (Hari Ini)
            </h2>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
              <thead class="text-xs text-text-secondary uppercase bg-surface-700/50">
                <tr>
                  <th class="px-4 py-3 rounded-l-lg">Brand / Kondisi</th>
                  <th class="px-4 py-3 rounded-r-lg text-right">Unit Terjual</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-surface-700">
                <tr v-if="brandSales.length === 0">
                  <td colspan="2" class="px-4 py-8 text-center text-text-secondary italic">
                    Belum ada penjualan brand hari ini
                  </td>
                </tr>
                <tr v-for="(item, idx) in brandSales" :key="idx" class="hover:bg-surface-700/30 transition-colors">
                  <td class="px-4 py-3 font-medium text-text-primary">{{ item.name }}</td>
                  <td class="px-4 py-3 text-right">
                    <span class="font-bold text-emerald-400">{{ item.count }}</span>
                    <span class="text-xs text-text-secondary ml-1">Unit</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- CS Performance (Today) -->
        <div class="card">
          <div class="flex items-center justify-between mb-6">
            <h2 class="text-lg font-semibold text-text-primary">
              Total Penjualan CS (Hari Ini)
            </h2>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
              <thead class="text-xs text-text-secondary uppercase bg-surface-700/50">
                <tr>
                  <th class="px-4 py-3 rounded-l-lg">Nama CS</th>
                  <th class="px-4 py-3 text-center">Unit HP</th>
                  <th class="px-4 py-3 text-center">Non-HP</th>
                  <th class="px-4 py-3 rounded-r-lg text-right">Total Nominal</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-surface-700">
                <tr v-if="csPerformance.length === 0">
                  <td colspan="4" class="px-4 py-8 text-center text-text-secondary italic">
                    Belum ada data performa hari ini
                  </td>
                </tr>
                <tr v-for="(cs, idx) in csPerformance" :key="idx" class="hover:bg-surface-700/30 transition-colors">
                  <td class="px-4 py-3 font-medium text-text-primary">{{ cs.name }}</td>
                  <td class="px-4 py-3 text-center">
                    <span :class="cs.hp_count > 0 ? 'text-primary-400 font-bold' : 'text-text-secondary'">{{ cs.hp_count
                    }}</span>
                  </td>
                  <td class="px-4 py-3 text-center">
                    <span :class="cs.non_hp_count > 0 ? 'text-blue-400 font-bold' : 'text-text-secondary'">{{
                      cs.non_hp_count }}</span>
                  </td>
                  <td class="px-4 py-3 text-right font-bold text-emerald-400">
                    {{ formatCurrency(cs.total_sales) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Content Grid (General) -->
    <div v-else-if="dashboardRole === 'general'" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Top Products -->
      <div class="lg:col-span-2 card">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-lg font-semibold text-text-primary">
            Produk Terlaris
          </h2>
          <button
            class="text-sm text-primary-500 hover:text-primary-600 dark:text-blue-400 dark:hover:text-blue-300 transition-colors">
            Lihat Semua
          </button>
        </div>
        <div class="space-y-4">
          <div v-for="(product, index) in topProducts" :key="product.id"
            class="flex items-center gap-4 p-3 rounded-xl hover:bg-surface-700/50 transition-colors">
            <div
              class="w-8 h-8 rounded-lg bg-surface-700 flex items-center justify-center text-sm font-bold text-text-secondary shrink-0">
              {{ index + 1 }}
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-text-primary font-medium truncate">
                {{ product.name }}
              </p>
              <p class="text-sm text-text-secondary">
                {{ product.sold }} terjual
              </p>
            </div>
            <div class="text-right">
              <p class="text-text-primary font-semibold">
                {{ formatCurrency(product.revenue) }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Low Stock Alert -->
      <div class="card border-amber-500/30">
        <div class="flex items-center gap-2 mb-6">
          <AlertTriangle :size="18" class="text-amber-500" />
          <h2 class="text-lg font-semibold text-text-primary">Stok Menipis</h2>
        </div>
        <div class="space-y-3">
          <div v-for="item in lowStockItems" :key="item.id"
            class="p-3 bg-amber-500/10 border border-amber-500/20 rounded-xl">
            <p class="text-text-primary font-medium text-sm">{{ item.name }}</p>
            <div class="flex items-center justify-between mt-2">
              <span class="text-amber-600 dark:text-amber-500 text-xs font-semibold">
                Stok: {{ item.stock }} / {{ item.minStock }}
              </span>
              <button
                class="text-xs text-primary-500 hover:text-primary-600 dark:text-blue-400 dark:hover:text-blue-300">
                Restock
              </button>
            </div>
          </div>
        </div>
        <button
          class="w-full mt-4 py-2 text-sm text-amber-600 dark:text-amber-500 hover:text-amber-700 dark:hover:text-amber-400 border border-amber-500/30 rounded-xl hover:bg-amber-500/10 transition-colors">
          Lihat {{ lowStockItems.length }} Item Lainnya
        </button>
      </div>
    </div>

    <!-- Bottom Grid (General) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6" v-if="dashboardRole === 'general'">
      <!-- Recent Transactions -->
      <div class="card">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-lg font-semibold text-text-primary">
            Transaksi Terakhir
          </h2>
          <button
            class="text-sm text-primary-500 hover:text-primary-600 dark:text-blue-400 dark:hover:text-blue-300 transition-colors flex items-center gap-1">
            Lihat Semua
            <ArrowUpRight :size="14" />
          </button>
        </div>
        <div class="space-y-3">
          <div v-for="trx in recentTransactions" :key="trx.id"
            class="flex items-center justify-between p-3 rounded-xl hover:bg-surface-700/50 transition-colors">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl bg-surface-700 flex items-center justify-center">
                <Activity :size="18" class="text-text-secondary" />
              </div>
              <div>
                <p class="text-text-primary font-medium text-sm">
                  {{ trx.customer }}
                </p>
                <p class="text-xs text-text-secondary">
                  {{ trx.id }} • {{ trx.time }}
                </p>
              </div>
            </div>
            <div class="text-right">
              <p class="text-text-primary font-semibold text-sm">
                {{ formatCurrency(trx.total) }}
              </p>
              <span class="text-[10px] font-medium px-2 py-0.5 rounded-full" :class="trx.status === 'success'
                ? 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400'
                : 'bg-amber-500/20 text-amber-600 dark:text-amber-400'
                ">
                {{ trx.status === "success" ? "Sukses" : "Pending" }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Branch Performance -->
      <div class="card">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-lg font-semibold text-text-primary">
            Performa Cabang
          </h2>
          <span class="text-xs text-text-secondary">Target Bulanan</span>
        </div>
        <div class="space-y-4">
          <div v-for="branch in branchPerformance" :key="branch.name">
            <div class="flex items-center justify-between mb-2">
              <span class="text-sm text-text-primary">{{ branch.name }}</span>
              <span class="text-xs text-text-secondary">
                {{ formatCurrency(branch.revenue) }} /
                {{ formatCurrency(branch.target) }}
              </span>
            </div>
            <div class="h-2 bg-surface-700 rounded-full overflow-hidden">
              <div class="h-full rounded-full transition-all duration-500" :class="branch.revenue / branch.target >= 0.8
                ? 'bg-emerald-500'
                : branch.revenue / branch.target >= 0.5
                  ? 'bg-amber-500'
                  : 'bg-red-500'
                " :style="{
                  width: `${Math.min(
                    (branch.revenue / branch.target) * 100,
                    100
                  )}%`,
                }"></div>
            </div>
          </div>
      </div>
    </div>
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