<script setup>
import { ref, onMounted, onUnmounted, computed } from "vue";
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
  Sparkles,
  Target,
  History,
  Calendar,
  RefreshCw,
  Scale,
  Activity,
  AlertTriangle,
  ArrowUpRight,
  BarChart3,
  FileText,
  Zap,
  ArrowRight
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

const leaderBranches = ref([]);
const leaderMonthSummary = ref(null);
const leaderBalancingSummary = ref(null);
const leaderBranchBreakdown = ref([]);
const leaderVelocityTab = ref('models');

const leaderTotalRevenue = computed(() => {
  const rev = stats.value.find(s => s.id === 'revenue');
  return rev ? Number(rev.value || 0) : 0;
});

const leaderNetRevenue = computed(() => {
  const net = stats.value.find(s => s.id === 'net_revenue');
  return net ? Number(net.value || 0) : 0;
});

const leaderTransactionsCount = computed(() => {
  const trx = stats.value.find(s => s.id === 'transactions');
  return trx ? Number(trx.value || 0) : 0;
});

const leaderProductsSoldCount = computed(() => {
  const sold = stats.value.find(s => s.id === 'sold');
  return sold ? Number(sold.value || 0) : 0;
});

const leaderTotalStockCount = computed(() => {
  const st = stats.value.find(s => s.id === 'stock');
  return st ? Number(st.value || 0) : (leaderHpStock.value + leaderNonHpStock.value);
});

const leaderHpStock = computed(() => {
  return leaderBranchBreakdown.value.reduce((acc, b) => acc + (b.hp_stock || 0), 0);
});

const leaderNonHpStock = computed(() => {
  return leaderBranchBreakdown.value.reduce((acc, b) => acc + (b.non_hp_stock || 0), 0);
});

const leaderHpSold = computed(() => {
  return csPerformance.value.reduce((acc, c) => acc + (c.hp_count || 0), 0);
});

const leaderNonHpSold = computed(() => {
  return csPerformance.value.reduce((acc, c) => acc + (c.non_hp_count || 0), 0);
});

const hpStockPercentage = computed(() => {
  const total = leaderTotalStockCount.value;
  if (!total || total <= 0) return 50;
  return Math.min(100, Math.max(0, Math.round((leaderHpStock.value / total) * 100)));
});

const nonHpStockPercentage = computed(() => {
  return 100 - hpStockPercentage.value;
});

const maxTypeSalesCount = computed(() => {
  if (!typeSales.value || typeSales.value.length === 0) return 1;
  return Math.max(...typeSales.value.map(t => t.count || 1), 1);
});

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
if (authStore.hasRole('leader')) {
  dashboardRole.value = 'leader';
} else if (authStore.hasRole('admin_produk')) {
  dashboardRole.value = 'admin_produk';
} else if (authStore.hasRole('security')) {
  dashboardRole.value = 'security';
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
  const icons = { Database, Tags, Box, DollarSign, Package, ShoppingCart, Users, TrendingUp, TrendingDown };
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
  if (dashboardRole.value === 'security') {
    isLoading.value = false;
    return; // Security has static dashboard, no need to fetch online/offline stats
  }
  
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
    } else if (response.data.role === 'leader') {
      dashboardRole.value = 'leader';
      stats.value = response.data.stats.map(s => ({
        ...s,
        icon: resolveIcon(s.icon),
        change: 0,
        trend: 'neutral'
      }));
      recentTransactions.value = response.data.recentTransactions || [];
      csPerformance.value = response.data.csPerformance || [];
      typeSales.value = response.data.typeSales || [];
      brandSales.value = response.data.brandSales || [];
      branchRanking.value = response.data.branch_ranking || null;
      leaderBranches.value = response.data.branches || [];
      leaderMonthSummary.value = response.data.month_summary || null;
      leaderBalancingSummary.value = response.data.balancing_summary || null;
      leaderBranchBreakdown.value = response.data.branch_breakdown || [];
      ranking.value = {
        my_rank: '-',
        leaderboard: response.data.csPerformance || []
      };
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

      // DEBUG LOGS FOR PODIUM
      console.log('--- DASHBOARD DEBUG ---');
      console.log('Role:', dashboardRole.value);
      console.log('Branch Ranking Full Data:', branchRanking.value);
      if (branchRanking.value) {
        console.log('Today Podium:', branchRanking.value.today?.podium);
        console.log('Yesterday Podium:', branchRanking.value.yesterday?.podium);
        console.log('This Month Podium:', branchRanking.value.this_month?.podium);
      }
      console.log('-----------------------');

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

  // Enable Real-Time Dashboard auto-refresh on sales activity
  if (window.Echo) {
    window.Echo.channel('stock-out')
      .listen('.StockOutEvent', (e) => {
        console.log('[Realtime] Sales transaction detected, refreshing dashboard totals...', e);
        // Intelligently re-pull current statistics without aggressive page flashes
        fetchDashboardData();
      });
  }
});

onUnmounted(() => {
  if (window.Echo) {
    window.Echo.leave('stock-out');
  }
});

const getColorClasses = (color) => {
  return {
    bg: "bg-primary-500/20",
    text: "text-primary-600 dark:text-primary-400",
    icon: "bg-primary-500 text-white",
  };
};

const leftPodiumData = computed(() => {
  if (!branchRanking.value) return null;
  if (podiumTab.value === 'today') return branchRanking.value.today;
  if (podiumTab.value === 'this_month') return branchRanking.value.this_month;
  return null;
});

const rightPodiumData = computed(() => {
  if (!branchRanking.value) return null;
  if (podiumTab.value === 'today') return branchRanking.value.yesterday;
  if (podiumTab.value === 'this_month') return branchRanking.value.last_month;
  return null;
});

const leftPodiumTitle = computed(() => {
  if (podiumTab.value === 'today') return "Live Today's Performance";
  if (podiumTab.value === 'this_month') return "This Month's Progress";
  return "Sales Performance Podium";
});

const rightPodiumTitle = computed(() => {
  if (podiumTab.value === 'today') return "Yesterday's Champions";
  if (podiumTab.value === 'this_month') return "Last Month's Champions";
  return "Top 3 Champions";
});

const rightPodiumSubtitle = computed(() => {
  if (podiumTab.value === 'today') return "Final Rankings Yesterday";
  if (podiumTab.value === 'this_month') return "Final Rankings Last Month";
  return "Champions";
});

const currentGlobalRank = computed(() => {
  if (!branchRanking.value?.summary) return '-';
  if (podiumTab.value === 'today') return branchRanking.value.summary.today_global;
  return branchRanking.value.summary.this_month_global;
});

const currentLocalRank = computed(() => {
  if (!branchRanking.value?.summary) return '-';
  if (podiumTab.value === 'today') return branchRanking.value.summary.today_local;
  return branchRanking.value.summary.this_month_local;
});
</script>

<template>
  <div class="space-y-6 animate-in">
    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between md:items-end gap-4">
      <div>
        <div class="flex items-center gap-2.5 flex-wrap">
          <h1 class="text-2xl font-bold text-text-primary tracking-tight">
            {{ dashboardRole === 'leader' ? 'Leader Dashboard' : 'Executive Overview' }}
          </h1>
          <span v-if="dashboardRole === 'leader'" class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 flex items-center gap-1">
            <Store :size="12" />
            Branch Leader
          </span>
        </div>
        <p class="text-text-secondary mt-1 flex items-center flex-wrap gap-2 text-sm">
          <span v-if="dashboardRole === 'leader' && leaderBranches.length > 0" class="inline-flex items-center gap-1 font-semibold text-text-primary">
            <MapPin :size="14" class="text-primary-500" />
            {{ leaderBranches.map(b => b.name).join(', ') }}
            <span class="text-text-secondary font-normal">•</span>
          </span>
          <span v-else-if="dashboardRole !== 'leader'">Real-time data dari 60+ cabang •</span>
          <span class="text-emerald-500 dark:text-emerald-400">● {{ dashboardRole === 'leader' ? 'Real-time Branch Scoped' : 'Online' }}</span> •
          <span>Reset 05:00</span>
        </p>
      </div>
      <div class="flex items-center gap-3 w-full md:w-auto">
        <!-- League Badge -->
        <div v-if="authStore.user?.league" class="flex items-center gap-2 px-3 py-2 rounded-xl border shadow-sm"
          :class="{
            'bg-gradient-to-r from-amber-50 to-orange-50 dark:from-amber-900/20 dark:to-orange-900/20 border-amber-200 dark:border-amber-800': authStore.user.league.key === 'liga_1',
            'bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 border-blue-200 dark:border-blue-800': authStore.user.league.key === 'liga_2',
            'bg-gradient-to-r from-red-50 to-rose-50 dark:from-red-900/20 dark:to-rose-900/20 border-red-200 dark:border-red-800': authStore.user.league.key === 'zona_merah',
            'bg-surface-50 dark:bg-surface-800 border-surface-200 dark:border-surface-700': authStore.user.league.key === 'non_liga',
          }">
          <Trophy v-if="authStore.user.league.key === 'liga_1'" :size="16" class="text-amber-500" />
          <Award v-else-if="authStore.user.league.key === 'liga_2'" :size="16" class="text-blue-500" />
          <Target v-else-if="authStore.user.league.key === 'zona_merah'" :size="16" class="text-red-500" />
          <Medal v-else :size="16" class="text-surface-400" />
          <span class="text-xs font-bold"
            :class="{
              'text-amber-700 dark:text-amber-400': authStore.user.league.key === 'liga_1',
              'text-blue-700 dark:text-blue-400': authStore.user.league.key === 'liga_2',
              'text-red-700 dark:text-red-400': authStore.user.league.key === 'zona_merah',
              'text-surface-500': authStore.user.league.key === 'non_liga',
            }">{{ authStore.user.league.label }}</span>
        </div>
        <button @click="refreshData" class="btn btn-secondary w-full md:w-auto" :disabled="isLoading">
          <RefreshCw :size="16" :class="{ 'animate-spin': isLoading }" />
          Refresh Data
        </button>
      </div>
    </div>

    <!-- Stats Grid (Hidden for leader role to provide dedicated bespoke Bento Command Grid) -->
    <div v-if="dashboardRole !== 'leader'" class="grid grid-cols-1 sm:grid-cols-2 gap-4" :class="stats.length === 5 ? 'md:grid-cols-3 lg:grid-cols-5' : 'lg:grid-cols-4'">
      <template v-if="isLoading && stats.length === 0">
        <div v-for="i in 5" :key="i" class="card h-32 animate-pulse bg-surface-700/50"></div>
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

    <!-- Dashboard Khusus Security -->
    <div v-else-if="dashboardRole === 'security'" class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div class="card border border-surface-700 shadow-xl overflow-hidden relative min-h-[300px] flex flex-col justify-center">
        <!-- Decoration -->
        <div class="absolute -right-10 -top-10 w-64 h-64 bg-primary-500/10 rounded-full blur-3xl pointer-events-none"></div>
        
        <div class="relative z-10 px-8">
          <div class="w-16 h-16 bg-primary-50 dark:bg-primary-900/20 rounded-2xl flex items-center justify-center mb-6 shadow-sm border border-primary-100 dark:border-primary-900">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary-600 dark:text-primary-400"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
          </div>
          <h2 class="text-3xl font-black mb-2 tracking-tight text-text-primary">Security Check</h2>
          <p class="text-text-secondary mb-8 max-w-sm text-lg">
            Lakukan validasi dan scan QR barang keluar untuk memastikan keamanan dan kesesuaian unit.
          </p>
          <router-link to="/security-scan/start" class="inline-flex items-center gap-3 btn-primary px-8 py-4 rounded-xl font-bold transition-all shadow-xl hover:shadow-2xl hover:-translate-y-1 group">
            Mulai Scan QR
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="transition-transform group-hover:translate-x-1"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
          </router-link>
        </div>
      </div>
    </div>
    <div v-else-if="dashboardRole === 'online_shop' || dashboardRole === 'toko_offline'" class="space-y-6">

      <div class="grid grid-cols-1 gap-8">
        <!-- LEFT: Branch Competition Podium (NEW PREMIUM DESIGN) -->
        <div v-if="branchRanking"
          class="overflow-hidden bg-transparent border-none p-0 flex flex-col relative min-h-[600px]">
          <!-- Header and Tabs -->
          <div class="px-4 lg:px-8 flex flex-col md:flex-row items-center justify-between mb-12 gap-6">
            <div class="text-center md:text-left">
              <p class="text-[10px] font-bold text-surface-500 uppercase tracking-[0.3em] mb-1">{{ leftPodiumTitle }}
              </p>
              <h2 class="font-black text-4xl lg:text-5xl text-text-primary tracking-tighter uppercase leading-none">
                Live Global <span class="text-primary-500">Rankings</span>
              </h2>
            </div>

            <div class="flex bg-surface-800/50 backdrop-blur-md p-1 rounded-2xl border border-surface-700/50 shadow-xl">
              <button v-for="tab in [{ id: 'today', lab: 'Hari Ini' }, { id: 'this_month', lab: 'Bulan Ini' }]"
                :key="tab.id" @click="podiumTab = tab.id"
                class="px-6 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all duration-300"
                :class="podiumTab === tab.id ? 'bg-primary-500 text-black shadow-lg' : 'text-text-secondary hover:text-primary-500'">
                {{ tab.lab }}
              </button>
            </div>
          </div>

          <!-- Main Podium Grid -->
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-start">

            <!-- CURRENT/ACTIVE PODIUM (LEFT SIDE) -->
            <div class="relative flex flex-col items-center justify-end h-[420px] sm:h-[480px] lg:h-[500px] w-full max-w-4xl mx-auto px-2 sm:px-4">
              <!-- Background Glow for Rank 1 -->
              <div
                class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-[400px] h-[300px] sm:h-[400px] bg-primary-500/10 rounded-full blur-[100px] pointer-events-none">
              </div>

              <div v-if="leftPodiumData && leftPodiumData.podium"
                class="flex items-end justify-center w-full gap-0 sm:gap-1 lg:gap-2">

                <!-- RANK 2 (Left Slot) -->
                <div v-if="leftPodiumData.podium[0]"
                  class="flex flex-col items-center flex-1 max-w-[120px] sm:max-w-[160px] lg:max-w-[200px] group animate-slide-up"
                  style="animation-delay: 0.1s">
                  <div class="avatar-container mb-2 sm:mb-4 relative scale-75 sm:scale-100">
                    <div v-if="leftPodiumData.podium[0].is_me"
                      class="absolute -top-8 sm:-top-10 left-1/2 -translate-x-1/2 bg-primary-500 text-black text-[8px] sm:text-[9px] font-black px-2 sm:px-3 py-0.5 sm:py-1 rounded-full shadow-lg animate-pulse z-10 whitespace-nowrap">
                      YOU</div>
                    <div
                      class="w-14 h-14 sm:w-20 sm:h-20 rounded-full bg-surface-800 border sm:border-2 border-slate-400 flex items-center justify-center shadow-xl group-hover:scale-110 transition-transform duration-500 overflow-hidden"
                      :class="leftPodiumData.podium[0].is_me ? 'ring-4 ring-primary-500/20' : ''">
                      <Store
                        v-if="leftPodiumData.podium[0].type === 'branch' || leftPodiumData.podium[0].type === 'Offline'"
                        class="w-8 h-8 sm:w-10 sm:h-10 text-slate-400" />
                      <Globe v-else class="w-8 h-8 sm:w-10 sm:h-10 text-slate-400" />
                    </div>
                  </div>
                  <div class="text-center mb-2 sm:mb-4 px-1 sm:px-2 min-h-[40px] flex flex-col justify-end">
                    <h4 class="font-black text-[9px] sm:text-xs text-text-primary uppercase truncate w-full tracking-tight mb-0.5"
                      :class="leftPodiumData.podium[0].is_me ? 'text-primary-500' : ''">{{ leftPodiumData.podium[0].name }}</h4>
                    <span class="text-[8px] sm:text-[10px] font-bold text-slate-400/60 tabular-nums truncate w-full">Total: {{
                      formatCurrency(leftPodiumData.podium[0].omset) }}</span>
                    <span class="text-[8px] sm:text-[10px] font-bold tabular-nums truncate w-full"
                      :class="leftPodiumData.podium[0].omset_bersih < 0 ? 'text-red-400/80' : 'text-emerald-400/80'">Bersih: {{
                      formatCurrency(leftPodiumData.podium[0].omset_bersih) }}</span>
                  </div>
                  <div
                    class="w-full h-24 sm:h-32 lg:h-40 bg-gradient-to-b from-slate-800/80 to-slate-900/50 rounded-t-xl sm:rounded-t-2xl border-t sm:border-t-2 border-slate-400 border-x border-slate-400/20 flex flex-col items-center justify-center shadow-2xl relative overflow-hidden group-hover:brightness-110 transition-all">
                    <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,rgba(148,163,184,0.1),transparent)]">
                    </div>
                    <span class="text-3xl sm:text-5xl font-black text-slate-400 leading-none">{{ leftPodiumData.podium[0].rank }}</span>
                    <span class="text-[7px] sm:text-[9px] font-black text-slate-400/50 uppercase tracking-[0.2em] sm:tracking-[0.3em] mt-1">RANK</span>
                  </div>
                </div>

                <!-- RANK 1 (Center Slot) -->
                <div v-if="leftPodiumData.podium[1]"
                  class="flex flex-col items-center flex-1 max-w-[140px] sm:max-w-[200px] lg:max-w-[240px] group z-10 animate-slide-up"
                  style="animation-delay: 0s">
                  <div class="avatar-container mb-3 sm:mb-6 relative scale-90 sm:scale-100">
                    <Sparkles
                      class="absolute -top-10 sm:-top-14 left-1/2 -translate-x-1/2 w-6 h-6 sm:w-8 sm:h-8 text-primary-500 animate-pulse" />
                    <div v-if="leftPodiumData.podium[1].is_me"
                      class="absolute -top-8 sm:-top-10 left-1/2 -translate-x-1/2 bg-primary-500 text-black text-[8px] sm:text-[9px] font-black px-3 sm:px-4 py-1 sm:py-1.5 rounded-full shadow-lg animate-bounce z-10 whitespace-nowrap">
                      YOU</div>
                    <div
                      class="w-20 h-20 sm:w-28 sm:h-28 rounded-full bg-surface-800 border sm:border-4 border-primary-500 flex items-center justify-center shadow-[0_0_50px_rgba(245,158,11,0.2)] group-hover:scale-110 transition-transform duration-700 overflow-hidden"
                      :class="leftPodiumData.podium[1].is_me ? 'ring-8 ring-primary-500/10' : ''">
                      <Store
                        v-if="leftPodiumData.podium[1].type === 'branch' || leftPodiumData.podium[1].type === 'Offline'"
                        class="w-10 h-10 sm:w-14 sm:h-14 text-primary-500" />
                      <Globe v-else class="w-10 h-10 sm:w-14 sm:h-14 text-primary-500" />
                    </div>
                  </div>
                  <div class="text-center mb-3 sm:mb-6 px-1 sm:px-2 min-h-[60px] flex flex-col justify-end items-center gap-1">
                    <h4 class="font-black text-xs sm:text-base lg:text-lg text-text-primary uppercase tracking-tighter mb-0.5 sm:mb-1 truncate w-full"
                      :class="leftPodiumData.podium[1].is_me ? 'text-primary-500' : ''">{{ leftPodiumData.podium[1].name }}</h4>
                    <div class="flex flex-col gap-1 items-center">
                      <div class="inline-flex bg-primary-500/10 px-2 sm:px-4 py-0.5 rounded-full border border-primary-500/20 mx-auto">
                        <span class="text-[9px] sm:text-xs font-black text-primary-500 tabular-nums">Total: {{
                          formatCurrency(leftPodiumData.podium[1].omset) }}</span>
                      </div>
                      <div class="inline-flex px-2 sm:px-4 py-0.5 rounded-full border mx-auto transition-colors duration-300"
                        :class="leftPodiumData.podium[1].omset_bersih < 0 ? 'bg-red-500/10 border-red-500/20' : 'bg-emerald-500/10 border-emerald-500/20'">
                        <span class="text-[9px] sm:text-xs font-black tabular-nums"
                          :class="leftPodiumData.podium[1].omset_bersih < 0 ? 'text-red-400' : 'text-emerald-400'">Bersih: {{
                          formatCurrency(leftPodiumData.podium[1].omset_bersih) }}</span>
                      </div>
                    </div>
                  </div>
                  <div
                    class="w-full h-36 sm:h-48 lg:h-64 bg-gradient-to-b from-primary-900/40 to-primary-950/20 rounded-t-2xl sm:rounded-t-3xl border-t-2 sm:border-t-4 border-primary-500 border-x border-primary-500/20 flex flex-col items-center justify-center shadow-[0_-20px_50px_rgba(245,158,11,0.1)] relative overflow-hidden group-hover:brightness-125 transition-all">
                    <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,rgba(245,158,11,0.15),transparent)]">
                    </div>
                    <span class="text-5xl sm:text-7xl font-black text-primary-500 leading-none">{{ leftPodiumData.podium[1].rank }}</span>
                    <span class="text-[8px] sm:text-[10px] font-black text-primary-500/50 uppercase tracking-[0.3em] sm:tracking-[0.4em] mt-1 sm:mt-2">GLOBAL RANK</span>
                  </div>
                </div>

                <!-- RANK 3 (Right Slot) -->
                <div v-if="leftPodiumData.podium[2]"
                  class="flex flex-col items-center flex-1 max-w-[120px] sm:max-w-[160px] lg:max-w-[200px] group animate-slide-up"
                  style="animation-delay: 0.2s">
                  <div class="avatar-container mb-2 sm:mb-4 relative scale-75 sm:scale-100">
                    <div v-if="leftPodiumData.podium[2].is_me"
                      class="absolute -top-8 sm:-top-10 left-1/2 -translate-x-1/2 bg-primary-500 text-black text-[8px] sm:text-[9px] font-black px-2 sm:px-3 py-0.5 sm:py-1 rounded-full shadow-lg animate-pulse z-10 whitespace-nowrap">
                      YOU</div>
                    <div
                      class="w-14 h-14 sm:w-20 sm:h-20 rounded-full bg-surface-800 border sm:border-2 border-amber-700 flex items-center justify-center shadow-xl group-hover:scale-110 transition-transform duration-500 overflow-hidden"
                      :class="leftPodiumData.podium[2].is_me ? 'ring-4 ring-primary-500/20' : ''">
                      <Store
                        v-if="leftPodiumData.podium[2].type === 'branch' || leftPodiumData.podium[2].type === 'Offline'"
                        class="w-8 h-8 sm:w-10 sm:h-10 text-amber-700" />
                      <Globe v-else class="w-8 h-8 sm:w-10 sm:h-10 text-amber-700" />
                    </div>
                  </div>
                  <div class="text-center mb-2 sm:mb-4 px-1 sm:px-2 min-h-[40px] flex flex-col justify-end">
                    <h4 class="font-black text-[9px] sm:text-xs text-text-primary uppercase truncate w-full tracking-tight mb-0.5"
                      :class="leftPodiumData.podium[2].is_me ? 'text-primary-500' : ''">{{ leftPodiumData.podium[2].name }}</h4>
                    <span class="text-[8px] sm:text-[10px] font-bold text-amber-700/60 tabular-nums truncate w-full">Total: {{
                      formatCurrency(leftPodiumData.podium[2].omset) }}</span>
                    <span class="text-[8px] sm:text-[10px] font-bold tabular-nums truncate w-full"
                      :class="leftPodiumData.podium[2].omset_bersih < 0 ? 'text-red-400/80' : 'text-emerald-400/80'">Bersih: {{
                      formatCurrency(leftPodiumData.podium[2].omset_bersih) }}</span>
                  </div>
                  <div
                    class="w-full h-20 sm:h-24 lg:h-32 bg-gradient-to-b from-amber-900/30 to-amber-950/20 rounded-t-xl sm:rounded-t-2xl border-t sm:border-t-2 border-amber-700 border-x border-amber-700/20 flex flex-col items-center justify-center shadow-xl relative overflow-hidden group-hover:brightness-110 transition-all">
                    <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,rgba(184,115,51,0.1),transparent)]">
                    </div>
                    <span class="text-2xl sm:text-4xl font-black text-amber-700 leading-none">{{ leftPodiumData.podium[2].rank }}</span>
                    <span class="text-[7px] sm:text-[9px] font-black text-amber-700/50 uppercase tracking-[0.2em] sm:tracking-[0.3em] mt-1">RANK</span>
                  </div>
                </div>

              </div>
            </div>

            <!-- PREVIOUS PERIOD PODIUM (RIGHT SIDE) -->
            <div
              class="bg-surface-800/10 rounded-[3rem] border border-surface-700/30 p-8 flex flex-col items-center justify-center min-h-[500px] relative overflow-hidden backdrop-blur-sm group/prev transition-all hover:bg-surface-800/20">
              <div class="absolute top-0 right-0 p-6">
                <Trophy class="w-12 h-12 text-surface-700 group-hover/prev:text-primary-500/20 transition-colors" />
              </div>

              <div class="text-center mb-12">
                <h3 class="text-[10px] font-black text-primary-500 uppercase tracking-[0.5em] mb-2">{{ rightPodiumTitle
                  }}
                </h3>
                <p class="text-[9px] font-bold text-text-secondary uppercase tracking-widest">{{ rightPodiumSubtitle }}
                </p>
              </div>

              <div v-if="rightPodiumData && rightPodiumData.podium" class="w-full space-y-4 max-w-sm">
                <div v-for="(item, idx) in rightPodiumData.podium" :key="idx"
                  class="flex items-center gap-4 p-4 rounded-2xl bg-surface-800/40 border transition-all group/item relative overflow-hidden"
                  :class="[
                    item.is_me 
                      ? 'ring-2 ring-primary-500 border-primary-500/50 shadow-[0_0_30px_rgba(16,185,129,0.15)] scale-[1.03] z-10' 
                      : 'border-surface-700/50 hover:border-primary-500/30'
                  ]">
                  
                  <!-- Subtle Background Glow for YOU -->
                  <div v-if="item.is_me" class="absolute inset-0 bg-primary-500/5 animate-pulse pointer-events-none"></div>

                  <div
                    class="w-10 h-10 rounded-xl flex items-center justify-center font-black text-sm transition-transform group-hover/item:scale-110 shadow-lg relative z-10"
                    :class="[
                      item.is_me ? 'bg-primary-500 text-black shadow-primary-500/20' : {
                        'bg-primary-500 text-black': item.rank === 1,
                        'bg-slate-400 text-black': item.rank === 2,
                        'bg-amber-700 text-white': item.rank === 3,
                        'bg-surface-700 text-text-secondary': item.rank > 3
                      }
                    ]">
                    {{ item.rank }}
                  </div>

                  <div class="flex-1 min-w-0 relative z-10">
                    <h4 class="font-black text-xs text-text-primary uppercase truncate mb-1" :class="item.is_me ? 'text-primary-500' : ''">
                      {{ item.name }}
                    </h4>
                    <div class="flex flex-col sm:flex-row sm:gap-3">
                      <span class="text-[10px] font-bold tabular-nums" :class="item.is_me ? 'text-primary-400/80' : 'text-text-secondary opacity-60'">
                        Total: {{ formatCurrency(item.omset) }}
                      </span>
                      <span class="text-[10px] font-bold tabular-nums transition-colors duration-300"
                        :class="item.omset_bersih < 0 ? 'text-red-400/90' : 'text-emerald-400/90'">
                        Bersih: {{ formatCurrency(item.omset_bersih) }}
                      </span>
                    </div>
                  </div>

                  <component :is="item.type === 'branch' || item.type === 'Offline' ? Store : Globe"
                    class="w-5 h-5 transition-all relative z-10" 
                    :class="item.is_me ? 'text-primary-500 scale-110 opacity-100' : 'opacity-20'" />
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>

      <!-- Global & Branch Ranking Summary Bar -->
      <div v-if="branchRanking?.summary" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mt-8">
        <div v-for="item in [
          { label: 'Today Summary', global: branchRanking.summary.today_global, local: branchRanking.summary.today_local, sub: 'Live Standings', icon: Target, color: 'emerald' },
          { label: 'Yesterday Summary', global: branchRanking.summary.yesterday_global, local: branchRanking.summary.yesterday_local, sub: 'Final Rank', icon: History, color: 'blue' },
          { label: 'This Month Summary', global: branchRanking.summary.this_month_global, local: branchRanking.summary.this_month_local, sub: 'Monthly Race', icon: Calendar, color: 'purple' },
          { label: 'Last Month Summary', global: branchRanking.summary.last_month_global, local: branchRanking.summary.last_month_local, sub: 'Prev Month', icon: Award, color: 'amber' }
        ]" :key="item.label"
          class="card group p-5 bg-white dark:bg-[#0d0d0d] border-none shadow-xl overflow-hidden relative">
          <div
            class="absolute -right-4 -top-4 w-24 h-24 rounded-full opacity-[0.03] transition-transform duration-500 group-hover:scale-150"
            :class="`bg-${item.color}-500`"></div>
          <div class="relative z-10 flex items-center justify-between">
            <div class="flex-1">
              <span class="text-[9px] font-black uppercase tracking-widest mb-3 block opacity-40">{{ item.label
                }}</span>
              <div class="flex flex-col gap-3">
                <div class="flex items-center justify-between gap-3">
                  <div class="flex items-baseline gap-1">
                    <span class="text-[9px] font-bold opacity-30 uppercase mr-1">Global</span>
                    <span class="text-2xl font-black tracking-tighter" :class="`text-${item.color}-500`"><span
                        class="text-sm opacity-50">#</span>{{ item.global }}</span>
                  </div>
                  <div class="flex items-baseline gap-1">
                    <span class="text-[9px] font-bold opacity-30 uppercase mr-1">Local</span>
                    <span class="text-xl font-black tracking-tighter" :class="`text-${item.color}-500 opacity-60`"><span
                        class="text-xs">#</span>{{ item.local }}</span>
                  </div>
                </div>
              </div>
            </div>
            <div
              class="w-12 h-12 rounded-2xl flex items-center justify-center transition-all duration-300 group-hover:rotate-12 shrink-0 ml-4"
              :class="`bg-${item.color}-500/10 text-${item.color}-500`">
              <component :is="item.icon" :size="20" />
            </div>
          </div>
          <div class="absolute bottom-0 inset-x-0 h-1 transition-all duration-500 w-0 group-hover:w-full"
            :class="`bg-${item.color}-500`"></div>
        </div>
      </div>

      <!-- Leaderboard Table Card -->
      <div class="card overflow-hidden p-0">
        <div class="p-6 border-b border-surface-700/50 bg-surface-700/10 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <Trophy :size="24" class="text-amber-500" />
            <h2 class="text-lg font-black text-text-primary uppercase tracking-tight">Leaderboard Unit</h2>
          </div>
          <div class="bg-surface-800 px-3 py-1 rounded-full text-[10px] font-black text-text-secondary uppercase">TODAY
          </div>
        </div>
        <div class="p-6 overflow-x-auto">
          <table class="w-full text-sm text-left">
            <thead>
              <tr class="text-[10px] text-text-secondary uppercase tracking-widest font-black">
                <th class="pb-6 w-16">Rank</th>
                <th class="pb-6">Akun CS</th>
                <th class="pb-6 text-center">Unit Terjual</th>
                <th class="pb-6 text-center">Total Omset</th>
                <th class="pb-6 text-center">Omset Bersih</th>
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
                    <div
                      class="w-10 h-10 rounded-full bg-primary-500/10 flex items-center justify-center border border-primary-500/20 overflow-hidden shrink-0 shadow-inner">
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
                    <span class="text-base font-black text-text-primary">{{ user.units ?? user.count ?? user.sold ??
                      ((user.hp_count || 0) + (user.non_hp_count || 0)) ?? 0 }}</span>
                    <span class="text-[9px] font-bold text-text-secondary uppercase tracking-tighter">Unit
                      Terjual</span>
                  </div>
                </td>
                <td class="py-4 text-center">
                  <span class="font-semibold text-slate-400">{{ formatCurrency(user.omset ?? user.revenue ?? 0) }}</span>
                </td>
                <td class="py-4 text-center">
                  <span class="font-bold transition-colors duration-300"
                    :class="(user.omset_bersih ?? 0) < 0 ? 'text-red-400' : 'text-emerald-400'">{{ formatCurrency(user.omset_bersih ?? user.revenue ?? 0) }}</span>
                </td>
                <td class="py-4 text-right">
                  <div
                    class="inline-flex items-center px-2 py-1 bg-surface-700 rounded-lg font-mono text-text-secondary text-xs">
                    #{{ user.rank ?? (idx + 1) }}
                  </div>
                </td>
              </tr>
              <tr v-if="!ranking?.leaderboard || ranking.leaderboard.length === 0">
                <td colspan="4" class="py-8 text-center text-text-secondary italic">
                  Belum ada data penjualan akun CS hari ini
                </td>
              </tr>
            </tbody>
          </table>
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
                  <th class="px-4 py-3 text-right">Total Nominal</th>
                  <th class="px-4 py-3 rounded-r-lg text-right">Nominal Bersih</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-surface-700">
                <tr v-if="csPerformance.length === 0">
                  <td colspan="5" class="px-4 py-8 text-center text-text-secondary italic">
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
                  <td class="px-4 py-3 text-right font-semibold text-slate-400">
                    {{ formatCurrency(cs.total_sales) }}
                  </td>
                  <td class="px-4 py-3 text-right font-bold text-emerald-400">
                    {{ formatCurrency(cs.net_sales ?? cs.total_sales) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Dashboard Khusus Leader (Handcrafted Bento Grid) -->
    <div v-else-if="dashboardRole === 'leader'" class="space-y-6">

      <!-- BENTO ROW 1: Command Center Hero & CS Floor Roster (8 - 4 split) -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
        
        <!-- TILE 1: Branch Command Center (Hero Tile) (lg:col-span-7 xl:col-span-8) -->
        <div class="lg:col-span-7 xl:col-span-8 card p-6 lg:p-7 relative overflow-hidden flex flex-col justify-between border border-surface-700/80 bg-gradient-to-br from-surface-800 via-surface-800 to-surface-800/90 shadow-2xl group">
          <!-- Ambient lighting/glow mesh -->
          <div class="absolute -right-16 -top-16 w-72 h-72 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none transition-all duration-700 group-hover:bg-emerald-500/15"></div>
          <div class="absolute right-32 -bottom-10 w-48 h-48 bg-primary-500/5 rounded-full blur-2xl pointer-events-none"></div>

          <div>
            <!-- Hero Top Bar -->
            <div class="flex flex-wrap items-center justify-between gap-3 relative z-10">
              <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-xs font-bold text-emerald-600 dark:text-emerald-400 shadow-sm">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>EXECUTIVE COMMAND • HARI INI</span>
              </div>
              <div class="flex items-center gap-2 text-xs text-text-secondary">
                <span class="hidden sm:inline-flex items-center gap-1 font-mono text-[11px] px-2 py-0.5 rounded bg-surface-700/50">
                  <History :size="12" class="text-surface-400" /> Reset 05:00 WIB
                </span>
                <span v-if="leaderBranches.length > 0" class="px-2.5 py-1 rounded-lg bg-surface-700/60 border border-surface-600/40 text-text-primary font-bold flex items-center gap-1.5 shadow-sm">
                  <Store :size="13" class="text-primary-500" />
                  <span>{{ leaderBranches.map(b => b.name).join(', ') }}</span>
                </span>
              </div>
            </div>

            <!-- Primary Metric Hero Area -->
            <div class="mt-6 relative z-10">
              <p class="text-xs font-bold uppercase tracking-wider text-text-secondary">Omset Bersih Cabang (Net Sales)</p>
              <div class="flex flex-wrap items-baseline gap-3 mt-1.5">
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-mono font-black tabular-nums tracking-tight"
                  :class="leaderNetRevenue < 0 ? 'text-red-500 dark:text-red-400' : 'text-emerald-500 dark:text-emerald-400'">
                  {{ formatCurrency(leaderNetRevenue) }}
                </h2>
                <span v-if="leaderNetRevenue !== leaderTotalRevenue" class="text-xs font-mono font-semibold px-2 py-0.5 rounded"
                  :class="leaderNetRevenue < leaderTotalRevenue ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400' : 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'">
                  Variance: {{ formatCurrency(leaderNetRevenue - leaderTotalRevenue) }}
                </span>
              </div>

              <!-- Contextual Running Strip -->
              <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 mt-3 text-xs text-text-secondary font-medium">
                <span class="flex items-center gap-1.5">
                  Gross Omset:
                  <strong class="font-mono text-text-primary font-bold">{{ formatCurrency(leaderTotalRevenue) }}</strong>
                </span>
                <span class="text-surface-600">•</span>
                <span class="flex items-center gap-1.5">
                  Volume:
                  <strong class="font-mono text-primary-600 dark:text-primary-400 font-bold">{{ leaderTransactionsCount }} Transaksi</strong>
                </span>
                <template v-if="leaderMonthSummary">
                  <span class="text-surface-600">•</span>
                  <span class="flex items-center gap-1.5">
                    MTD ({{ leaderMonthSummary.month_name }}):
                    <strong class="font-mono text-text-primary font-bold">{{ formatCurrency(leaderMonthSummary.net_revenue) }}</strong>
                  </span>
                </template>
              </div>
            </div>
          </div>

          <!-- Bottom Micro-Telemetry Dock (3 Columns) -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-6 pt-5 border-t border-surface-700/60 relative z-10">
            <!-- Metric 1: Unit Terjual -->
            <div class="p-3.5 rounded-xl bg-surface-700/30 border border-surface-700/50 hover:bg-surface-700/40 transition-colors">
              <div class="flex items-center justify-between text-[11px] text-text-secondary font-bold uppercase tracking-wide">
                <span>Unit Terjual</span>
                <Package :size="14" class="text-violet-500" />
              </div>
              <div class="mt-2 flex items-baseline justify-between">
                <span class="text-xl font-mono font-black text-text-primary">{{ leaderProductsSoldCount }}</span>
                <span class="text-[11px] text-text-secondary">Hari Ini</span>
              </div>
              <div class="mt-2 flex items-center gap-1.5 text-[10px] font-mono">
                <span class="px-1.5 py-0.5 rounded bg-violet-500/10 text-violet-600 dark:text-violet-400 font-bold">HP: {{ leaderHpSold }}</span>
                <span class="px-1.5 py-0.5 rounded bg-blue-500/10 text-blue-600 dark:text-blue-400 font-bold">Acc: {{ leaderNonHpSold }}</span>
              </div>
            </div>

            <!-- Metric 2: Stok Fisik -->
            <div class="p-3.5 rounded-xl bg-surface-700/30 border border-surface-700/50 hover:bg-surface-700/40 transition-colors">
              <div class="flex items-center justify-between text-[11px] text-text-secondary font-bold uppercase tracking-wide">
                <span>Stok Fisik Cabang</span>
                <Box :size="14" class="text-primary-500" />
              </div>
              <div class="mt-2 flex items-baseline justify-between">
                <span class="text-xl font-mono font-black text-text-primary">{{ leaderTotalStockCount }}</span>
                <span class="text-[11px] text-text-secondary">Unit Ready</span>
              </div>
              <div class="mt-2 flex items-center gap-1.5 text-[10px] font-mono">
                <span class="px-1.5 py-0.5 rounded bg-primary-500/10 text-primary-600 dark:text-primary-400 font-bold">HP: {{ leaderHpStock }}</span>
                <span class="px-1.5 py-0.5 rounded bg-cyan-500/10 text-cyan-600 dark:text-cyan-400 font-bold">Acc: {{ leaderNonHpStock }}</span>
              </div>
            </div>

            <!-- Metric 3: Balancing Bulan Ini -->
            <div class="p-3.5 rounded-xl bg-surface-700/30 border border-surface-700/50 hover:bg-surface-700/40 transition-colors">
              <div class="flex items-center justify-between text-[11px] text-text-secondary font-bold uppercase tracking-wide">
                <span>Net Balancing</span>
                <Scale :size="14" class="text-amber-500" />
              </div>
              <div class="mt-2 flex items-baseline justify-between">
                <span class="text-base font-mono font-black truncate"
                  :class="(leaderBalancingSummary?.month_net || 0) < 0 ? 'text-red-500 dark:text-red-400' : 'text-emerald-500 dark:text-emerald-400'">
                  {{ formatCurrency(leaderBalancingSummary?.month_net || 0) }}
                </span>
              </div>
              <div class="mt-2 flex items-center justify-between text-[10px]">
                <span class="px-1.5 py-0.5 rounded bg-amber-500/10 text-amber-600 dark:text-amber-400 font-bold font-mono">
                  {{ leaderBalancingSummary?.month_count || 0 }} Trx
                </span>
                <router-link to="/balancing/history" class="text-primary-500 hover:text-primary-400 font-semibold flex items-center gap-0.5">
                  Audit <ArrowUpRight :size="11" />
                </router-link>
              </div>
            </div>
          </div>
        </div>

        <!-- TILE 2: CS Floor Roster (lg:col-span-5 xl:col-span-4) -->
        <div class="lg:col-span-5 xl:col-span-4 card p-5 flex flex-col justify-between border border-surface-700/80 bg-surface-800 shadow-xl relative">
          <div>
            <!-- Header -->
            <div class="flex items-center justify-between pb-3 border-b border-surface-700/60">
              <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-primary-500/10 text-primary-500 flex items-center justify-center shadow-inner">
                  <Trophy :size="16" />
                </div>
                <div>
                  <h3 class="font-bold text-sm text-text-primary tracking-tight">Roster CS Hari Ini</h3>
                  <p class="text-[11px] text-text-secondary">Leaderboard Staf Cabang</p>
                </div>
              </div>
              <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-primary-500/10 text-primary-600 dark:text-primary-400 border border-primary-500/20 font-mono">
                {{ csPerformance.length }} CS Aktif
              </span>
            </div>

            <!-- List of CS -->
            <div class="mt-3.5 space-y-2.5 max-h-[310px] overflow-y-auto pr-1">
              <div v-for="(cs, idx) in csPerformance" :key="cs.id || idx"
                class="p-2.5 rounded-xl bg-surface-700/25 hover:bg-surface-700/50 border border-surface-700/40 transition-all flex items-center justify-between gap-3 group">
                <!-- Left: Rank & Avatar & Info -->
                <div class="flex items-center gap-2.5 min-w-0">
                  <!-- Rank Pill -->
                  <div class="w-6 h-6 rounded-md flex items-center justify-center font-mono font-black text-xs shrink-0"
                    :class="idx === 0
                      ? 'bg-amber-500 text-black shadow-sm shadow-amber-500/30'
                      : (idx === 1
                        ? 'bg-slate-300 text-black'
                        : (idx === 2
                          ? 'bg-amber-700 text-white'
                          : 'bg-surface-700 text-text-secondary text-[11px]'))">
                    {{ idx + 1 }}
                  </div>

                  <!-- Avatar -->
                  <div class="w-8 h-8 rounded-full bg-primary-500/10 flex items-center justify-center overflow-hidden shrink-0 border border-surface-600/50">
                    <img :src="resolvePhoto(cs, cs.name)"
                      class="w-full h-full object-cover"
                      @error="(e) => e.target.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(cs.name || 'CS')}&background=10b981&color=fff`" />
                  </div>

                  <div class="min-w-0">
                    <p class="font-bold text-xs text-text-primary truncate group-hover:text-primary-500 transition-colors">{{ cs.name }}</p>
                    <p class="text-[10px] text-text-secondary truncate">{{ cs.branch_name || '-' }}</p>
                  </div>
                </div>

                <!-- Right: Units & Sales -->
                <div class="text-right shrink-0">
                  <div class="flex items-baseline justify-end gap-1 font-mono">
                    <span class="font-black text-xs text-text-primary">{{ (cs.hp_count || 0) + (cs.non_hp_count || 0) }}</span>
                    <span class="text-[10px] text-text-secondary font-normal">Unit</span>
                  </div>
                  <p class="text-[11px] font-mono font-bold" :class="(cs.net_sales || 0) < 0 ? 'text-red-500 dark:text-red-400' : 'text-emerald-500 dark:text-emerald-400'">
                    {{ formatCurrency(cs.net_sales || 0) }}
                  </p>
                </div>
              </div>

              <!-- Empty state -->
              <div v-if="!csPerformance || csPerformance.length === 0" class="py-10 text-center text-text-secondary italic text-xs">
                Belum ada aktivitas staf CS hari ini di cabang
              </div>
            </div>
          </div>

          <!-- Bottom Direct Action -->
          <div class="pt-3.5 mt-2 border-t border-surface-700/50">
            <router-link to="/users" class="btn btn-secondary w-full text-xs py-2 justify-center gap-2">
              <Users :size="14" class="text-primary-500" />
              <span>Kelola & Tambah Staf CS</span>
              <ArrowRight :size="13" class="opacity-60" />
            </router-link>
          </div>
        </div>

      </div>

      <!-- BENTO ROW 2: Operational Pillars (Balancing, Stock, Utility Dock) (3 Columns) -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-5">
        
        <!-- TILE 3: Balancing Sentinel (Financial Discrepancies) (lg:col-span-4) -->
        <div class="lg:col-span-4 card p-5 flex flex-col justify-between border border-surface-700/80 bg-surface-800 shadow-xl">
          <div>
            <div class="flex items-center justify-between pb-3 border-b border-surface-700/60">
              <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-amber-500/10 text-amber-500 flex items-center justify-center">
                  <Scale :size="16" />
                </div>
                <div>
                  <h3 class="font-bold text-sm text-text-primary tracking-tight">Balancing Cabang</h3>
                  <p class="text-[11px] text-text-secondary">Penyesuaian Buku Kasir</p>
                </div>
              </div>
              <router-link to="/balancing/history" class="text-xs text-amber-500 hover:text-amber-400 flex items-center gap-1 font-semibold">
                History <ArrowUpRight :size="12" />
              </router-link>
            </div>

            <!-- Dual Variance Pill Strip -->
            <div class="grid grid-cols-2 gap-2 mt-4 p-3 rounded-xl bg-surface-700/30 border border-surface-700/50 text-center">
              <div>
                <p class="text-[10px] text-text-secondary font-bold uppercase tracking-wider">Total Plus (+)</p>
                <p class="text-xs font-mono font-bold text-emerald-500 dark:text-emerald-400 mt-0.5">
                  +{{ formatCurrency(leaderBalancingSummary?.month_plus || 0) }}
                </p>
              </div>
              <div class="border-l border-surface-700/50">
                <p class="text-[10px] text-text-secondary font-bold uppercase tracking-wider">Total Minus (-)</p>
                <p class="text-xs font-mono font-bold text-red-500 dark:text-red-400 mt-0.5">
                  {{ formatCurrency(leaderBalancingSummary?.month_minus || 0) }}
                </p>
              </div>
            </div>

            <!-- Recent Balancing Adjustments Stream -->
            <div class="mt-4">
              <p class="text-[10px] font-bold text-text-secondary uppercase tracking-wider mb-2">Penyesuaian Terbaru (Bulan Ini)</p>
              <div class="space-y-2 max-h-[170px] overflow-y-auto pr-1">
                <div v-for="b in (leaderBalancingSummary?.recent || []).slice(0, 3)" :key="b.id"
                  class="p-2.5 rounded-lg bg-surface-700/20 border border-surface-700/40 flex items-center justify-between text-xs">
                  <div class="min-w-0 pr-2">
                    <p class="font-bold text-text-primary text-[11px] truncate">{{ b.sub_category }}</p>
                    <p class="text-[10px] text-text-secondary truncate">{{ b.cs_name }} • {{ b.time }}</p>
                  </div>
                  <span class="font-mono font-bold text-xs shrink-0" :class="b.amount < 0 ? 'text-red-500 dark:text-red-400' : 'text-emerald-500 dark:text-emerald-400'">
                    {{ formatCurrency(b.amount) }}
                  </span>
                </div>
                <div v-if="!leaderBalancingSummary?.recent || leaderBalancingSummary.recent.length === 0" class="py-6 text-center text-text-secondary italic text-xs">
                  Belum ada penyesuaian balancing bulan ini
                </div>
              </div>
            </div>
          </div>

          <div class="pt-3.5 mt-3 border-t border-surface-700/50">
            <router-link to="/balancing/history" class="btn btn-secondary w-full text-xs py-2 justify-center">
              Buka History Balancing Cabang
            </router-link>
          </div>
        </div>

        <!-- TILE 4: Physical Stock Matrix (lg:col-span-4) -->
        <div class="lg:col-span-4 card p-5 flex flex-col justify-between border border-surface-700/80 bg-surface-800 shadow-xl">
          <div>
            <div class="flex items-center justify-between pb-3 border-b border-surface-700/60">
              <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-blue-500/10 text-blue-500 flex items-center justify-center">
                  <Box :size="16" />
                </div>
                <div>
                  <h3 class="font-bold text-sm text-text-primary tracking-tight">Stok Fisik Cabang</h3>
                  <p class="text-[11px] text-text-secondary">Komposisi Inventori Real</p>
                </div>
              </div>
              <span class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20">
                {{ leaderTotalStockCount }} Total Unit
              </span>
            </div>

            <!-- Segmented Gauge Proportion Bar -->
            <div class="mt-4">
              <div class="flex justify-between items-center text-xs mb-1.5">
                <span class="font-semibold text-primary-500 flex items-center gap-1">
                  <span class="w-2 h-2 rounded-full bg-primary-500"></span> HP ({{ hpStockPercentage }}%)
                </span>
                <span class="font-semibold text-cyan-500 flex items-center gap-1">
                  <span class="w-2 h-2 rounded-full bg-cyan-500"></span> Acc ({{ nonHpStockPercentage }}%)
                </span>
              </div>
              <div class="h-2.5 w-full bg-surface-700 rounded-full overflow-hidden flex">
                <div class="bg-primary-500 h-full transition-all duration-500" :style="{ width: `${hpStockPercentage}%` }"></div>
                <div class="bg-cyan-500 h-full transition-all duration-500" :style="{ width: `${nonHpStockPercentage}%` }"></div>
              </div>
            </div>

            <!-- Detailed Stock Numbers -->
            <div class="grid grid-cols-2 gap-2 mt-4">
              <div class="p-3 rounded-xl bg-surface-700/25 border border-surface-700/40 text-center">
                <p class="text-[10px] text-text-secondary uppercase font-bold tracking-wider">Unit Handphone</p>
                <p class="text-xl font-mono font-black text-text-primary mt-0.5">{{ leaderHpStock }}</p>
                <span class="text-[10px] text-primary-500 font-medium">Status Available</span>
              </div>
              <div class="p-3 rounded-xl bg-surface-700/25 border border-surface-700/40 text-center">
                <p class="text-[10px] text-text-secondary uppercase font-bold tracking-wider">Aksesoris / Non-HP</p>
                <p class="text-xl font-mono font-black text-text-primary mt-0.5">{{ leaderNonHpStock }}</p>
                <span class="text-[10px] text-cyan-500 font-medium">Fisik Display & Box</span>
              </div>
            </div>

            <!-- Branch breakdown snippet if multi branch -->
            <div v-if="leaderBranchBreakdown.length > 1" class="mt-3 space-y-1.5 max-h-[85px] overflow-y-auto">
              <div v-for="br in leaderBranchBreakdown" :key="br.id" class="flex items-center justify-between text-xs p-1.5 rounded bg-surface-700/20">
                <span class="font-semibold text-text-primary truncate max-w-[150px]">{{ br.name }}</span>
                <span class="font-mono text-text-secondary">{{ br.total_stock }} Unit (HP: {{ br.hp_stock }})</span>
              </div>
            </div>
          </div>

          <div class="pt-3.5 mt-3 border-t border-surface-700/50 flex gap-2">
            <router-link to="/inventory" class="btn btn-secondary flex-1 text-xs py-2 justify-center">
              Data Inventory
            </router-link>
            <router-link to="/inventory/stock-analysis" class="btn btn-secondary flex-1 text-xs py-2 justify-center">
              Analisa Stok
            </router-link>
          </div>
        </div>

        <!-- TILE 5: Fast Action Utility Dock (lg:col-span-4 md:col-span-2) -->
        <div class="lg:col-span-4 md:col-span-2 card p-5 flex flex-col justify-between border border-surface-700/80 bg-surface-800 shadow-xl">
          <div>
            <div class="flex items-center justify-between pb-3 border-b border-surface-700/60">
              <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-violet-500/10 text-violet-500 flex items-center justify-center">
                  <Zap :size="16" />
                </div>
                <div>
                  <h3 class="font-bold text-sm text-text-primary tracking-tight">Navigasi Operasional</h3>
                  <p class="text-[11px] text-text-secondary">Menu Eksekutif Leader</p>
                </div>
              </div>
              <span class="text-[10px] font-mono text-text-secondary">5 Modul Utama</span>
            </div>

            <!-- 5 Linear-style Action Buttons -->
            <div class="mt-3 space-y-1.5">
              <!-- 1. History Balancing -->
              <router-link to="/balancing/history"
                class="flex items-center justify-between p-2.5 rounded-xl bg-surface-700/20 hover:bg-amber-500/10 border border-surface-700/40 hover:border-amber-500/30 transition-all group">
                <div class="flex items-center gap-3">
                  <div class="w-7 h-7 rounded-lg bg-amber-500/10 text-amber-500 flex items-center justify-center group-hover:scale-110 transition-transform">
                    <Scale :size="14" />
                  </div>
                  <div>
                    <h4 class="font-bold text-xs text-text-primary group-hover:text-amber-500 transition-colors">History Balancing</h4>
                    <p class="text-[10px] text-text-secondary">Penyesuaian kasir cabang</p>
                  </div>
                </div>
                <div class="flex items-center gap-1.5">
                  <span v-if="leaderBalancingSummary" class="text-[10px] font-mono font-bold px-1.5 py-0.5 rounded bg-amber-500/10 text-amber-500">
                    {{ leaderBalancingSummary.month_count }} Trx
                  </span>
                  <ArrowRight :size="12" class="text-text-secondary group-hover:translate-x-0.5 transition-transform" />
                </div>
              </router-link>

              <!-- 2. Data Inventory -->
              <router-link to="/inventory"
                class="flex items-center justify-between p-2.5 rounded-xl bg-surface-700/20 hover:bg-blue-500/10 border border-surface-700/40 hover:border-blue-500/30 transition-all group">
                <div class="flex items-center gap-3">
                  <div class="w-7 h-7 rounded-lg bg-blue-500/10 text-blue-500 flex items-center justify-center group-hover:scale-110 transition-transform">
                    <Box :size="14" />
                  </div>
                  <div>
                    <h4 class="font-bold text-xs text-text-primary group-hover:text-blue-500 transition-colors">Data Inventory</h4>
                    <p class="text-[10px] text-text-secondary">Stok unit & cek IMEI</p>
                  </div>
                </div>
                <ArrowRight :size="12" class="text-text-secondary group-hover:translate-x-0.5 transition-transform" />
              </router-link>

              <!-- 3. Cek Penjualan -->
              <router-link to="/sales/check"
                class="flex items-center justify-between p-2.5 rounded-xl bg-surface-700/20 hover:bg-emerald-500/10 border border-surface-700/40 hover:border-emerald-500/30 transition-all group">
                <div class="flex items-center gap-3">
                  <div class="w-7 h-7 rounded-lg bg-emerald-500/10 text-emerald-500 flex items-center justify-center group-hover:scale-110 transition-transform">
                    <ShoppingCart :size="14" />
                  </div>
                  <div>
                    <h4 class="font-bold text-xs text-text-primary group-hover:text-emerald-500 transition-colors">Cek Penjualan</h4>
                    <p class="text-[10px] text-text-secondary">Monitoring transaksi kasir</p>
                  </div>
                </div>
                <ArrowRight :size="12" class="text-text-secondary group-hover:translate-x-0.5 transition-transform" />
              </router-link>

              <!-- 4. Analisa Stok -->
              <router-link to="/inventory/stock-analysis"
                class="flex items-center justify-between p-2.5 rounded-xl bg-surface-700/20 hover:bg-purple-500/10 border border-surface-700/40 hover:border-purple-500/30 transition-all group">
                <div class="flex items-center gap-3">
                  <div class="w-7 h-7 rounded-lg bg-purple-500/10 text-purple-500 flex items-center justify-center group-hover:scale-110 transition-transform">
                    <BarChart3 :size="14" />
                  </div>
                  <div>
                    <h4 class="font-bold text-xs text-text-primary group-hover:text-purple-500 transition-colors">Analisa Stok</h4>
                    <p class="text-[10px] text-text-secondary">Perputaran umur unit cabang</p>
                  </div>
                </div>
                <ArrowRight :size="12" class="text-text-secondary group-hover:translate-x-0.5 transition-transform" />
              </router-link>

              <!-- 5. Kelola Staf CS -->
              <router-link to="/users"
                class="flex items-center justify-between p-2.5 rounded-xl bg-surface-700/20 hover:bg-rose-500/10 border border-surface-700/40 hover:border-rose-500/30 transition-all group">
                <div class="flex items-center gap-3">
                  <div class="w-7 h-7 rounded-lg bg-rose-500/10 text-rose-500 flex items-center justify-center group-hover:scale-110 transition-transform">
                    <Users :size="14" />
                  </div>
                  <div>
                    <h4 class="font-bold text-xs text-text-primary group-hover:text-rose-500 transition-colors">Kelola Staf CS</h4>
                    <p class="text-[10px] text-text-secondary">Tambah staf cabang</p>
                  </div>
                </div>
                <div class="flex items-center gap-1.5">
                  <span class="text-[10px] font-mono font-bold px-1.5 py-0.5 rounded bg-rose-500/10 text-rose-500">
                    {{ csPerformance.length }} CS
                  </span>
                  <ArrowRight :size="12" class="text-text-secondary group-hover:translate-x-0.5 transition-transform" />
                </div>
              </router-link>
            </div>
          </div>
        </div>

      </div>

      <!-- BENTO ROW 3: Product Velocity & Live Transactions Stream (5 - 7 split) -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
        
        <!-- TILE 6: Product & Brand Velocity (lg:col-span-5) -->
        <div class="lg:col-span-5 card p-5 flex flex-col justify-between border border-surface-700/80 bg-surface-800 shadow-xl">
          <div>
            <div class="flex items-center justify-between pb-3 border-b border-surface-700/60">
              <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-500 flex items-center justify-center">
                  <TrendingUp :size="16" />
                </div>
                <div>
                  <h3 class="font-bold text-sm text-text-primary tracking-tight">Kecepatan Penjualan Produk</h3>
                  <p class="text-[11px] text-text-secondary">Hari Ini di Cabang Anda</p>
                </div>
              </div>
              <div class="flex p-0.5 rounded-lg bg-surface-700/40 border border-surface-600/30 text-[10px] font-bold">
                <button @click="leaderVelocityTab = 'models'"
                  class="px-2.5 py-1 rounded-md transition-all"
                  :class="leaderVelocityTab === 'models' ? 'bg-primary-500 text-white shadow-sm' : 'text-text-secondary hover:text-text-primary'">
                  Model (Top 5)
                </button>
                <button @click="leaderVelocityTab = 'brands'"
                  class="px-2.5 py-1 rounded-md transition-all"
                  :class="leaderVelocityTab === 'brands' ? 'bg-primary-500 text-white shadow-sm' : 'text-text-secondary hover:text-text-primary'">
                  Brand
                </button>
              </div>
            </div>

            <!-- Content: Models Velocity -->
            <div v-if="leaderVelocityTab === 'models'" class="mt-4 space-y-2.5">
              <div v-for="(item, idx) in typeSales" :key="idx" class="relative overflow-hidden p-2.5 rounded-xl bg-surface-700/20 border border-surface-700/40">
                <!-- Relative Progress Fill -->
                <div class="absolute inset-y-0 left-0 bg-primary-500/10 rounded-l-xl transition-all duration-500 pointer-events-none"
                  :style="{ width: `${Math.round((item.count / maxTypeSalesCount) * 100)}%` }"></div>

                <div class="relative z-10 flex items-center justify-between gap-2 text-xs">
                  <div class="flex items-center gap-2 min-w-0">
                    <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-mono font-bold bg-surface-700 text-text-secondary shrink-0">
                      {{ idx + 1 }}
                    </span>
                    <span class="font-semibold text-text-primary truncate">{{ item.name }}</span>
                  </div>
                  <div class="flex items-center gap-1 font-mono shrink-0">
                    <span class="font-black text-emerald-500 dark:text-emerald-400">{{ item.count }}</span>
                    <span class="text-[10px] text-text-secondary">Unit</span>
                  </div>
                </div>
              </div>

              <div v-if="typeSales.length === 0" class="py-10 text-center text-text-secondary italic text-xs">
                Belum ada produk terjual hari ini di cabang
              </div>
            </div>

            <!-- Content: Brands Velocity -->
            <div v-else class="mt-4 space-y-2.5">
              <div v-for="(item, idx) in brandSales" :key="idx" class="p-2.5 rounded-xl bg-surface-700/20 border border-surface-700/40 flex items-center justify-between text-xs">
                <span class="font-semibold text-text-primary truncate">{{ item.name }}</span>
                <span class="font-mono font-bold text-primary-500">{{ item.count }} Unit</span>
              </div>
              <div v-if="brandSales.length === 0" class="py-10 text-center text-text-secondary italic text-xs">
                Belum ada brand terjual hari ini di cabang
              </div>
            </div>
          </div>

          <div class="pt-3 mt-3 border-t border-surface-700/50">
            <router-link to="/inventory/stock-analysis" class="text-xs text-primary-500 hover:text-primary-400 flex items-center justify-center gap-1 font-semibold">
              Buka Analisa Perputaran Stok Lengkap <ArrowUpRight :size="12" />
            </router-link>
          </div>
        </div>

        <!-- TILE 7: Live Transaction Feed (lg:col-span-7) -->
        <div class="lg:col-span-7 card p-5 flex flex-col justify-between border border-surface-700/80 bg-surface-800 shadow-xl">
          <div>
            <div class="flex items-center justify-between pb-3 border-b border-surface-700/60">
              <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-500 flex items-center justify-center">
                  <Activity :size="16" />
                </div>
                <div>
                  <h3 class="font-bold text-sm text-text-primary tracking-tight">Live Penjualan Cabang (Hari Ini)</h3>
                  <p class="text-[11px] text-text-secondary">Transaksi Terakhir Kasir</p>
                </div>
              </div>
              <router-link to="/sales/check" class="text-xs text-primary-500 hover:text-primary-400 flex items-center gap-1 font-semibold">
                Cek Semua <ArrowUpRight :size="12" />
              </router-link>
            </div>

            <!-- Transaction Feed List -->
            <div class="mt-3.5 space-y-2 max-h-[310px] overflow-y-auto pr-1">
              <div v-for="trx in recentTransactions" :key="trx.id"
                class="p-3 rounded-xl bg-surface-700/20 hover:bg-surface-700/40 border border-surface-700/40 transition-all flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                <div class="min-w-0 flex-1">
                  <div class="flex items-center gap-2 flex-wrap">
                    <span class="font-mono text-xs font-bold text-primary-500 dark:text-primary-400">#{{ trx.id }}</span>
                    <span class="text-xs font-bold text-text-primary">{{ trx.customer }}</span>
                    <span class="text-[10px] px-1.5 py-0.2 rounded bg-surface-700 text-text-secondary font-medium">CS: {{ trx.cs_name || '-' }}</span>
                  </div>
                  <p class="text-[11px] text-text-secondary mt-1 truncate" :title="trx.items">
                    <Package :size="12" class="inline mr-1 text-surface-400" />
                    {{ trx.items }}
                  </p>
                </div>

                <div class="flex sm:flex-col items-center sm:items-end justify-between shrink-0 gap-0.5">
                  <span class="font-mono font-bold text-xs text-emerald-500 dark:text-emerald-400">
                    {{ formatCurrency(trx.total) }}
                  </span>
                  <span class="text-[10px] text-text-secondary font-mono">{{ trx.datetime }} ({{ trx.time }})</span>
                </div>
              </div>

              <div v-if="!recentTransactions || recentTransactions.length === 0" class="py-12 text-center text-text-secondary italic text-xs">
                Belum ada transaksi tercatat hari ini di cabang yang Anda pimpin
              </div>
            </div>
          </div>

          <div class="pt-3 mt-3 border-t border-surface-700/50">
            <router-link to="/sales/check" class="btn btn-secondary w-full text-xs py-2 justify-center gap-1.5">
              <ShoppingCart :size="14" />
              <span>Buka Menu Cek Penjualan Cabang</span>
            </router-link>
          </div>
        </div>

      </div>

      <!-- BENTO ROW 4: National Branch League Standing (Collapsible / Compact) -->
      <div v-if="branchRanking" class="card p-5 border border-surface-700/80 bg-surface-800 shadow-xl relative overflow-hidden">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-surface-700/60">
          <div>
            <div class="flex items-center gap-2">
              <Trophy :size="18" class="text-amber-500" />
              <h3 class="font-bold text-base text-text-primary tracking-tight">Kompetisi Cabang Nasional</h3>
            </div>
            <p class="text-xs text-text-secondary mt-0.5">Posisi ranking cabang Anda bersaing dengan seluruh cabang nasional</p>
          </div>

          <div class="flex bg-surface-700/40 p-1 rounded-xl border border-surface-600/30">
            <button v-for="tab in [{ id: 'today', lab: 'Hari Ini' }, { id: 'this_month', lab: 'Bulan Ini' }]"
              :key="tab.id" @click="podiumTab = tab.id"
              class="px-4 py-1.5 rounded-lg text-xs font-bold transition-all"
              :class="podiumTab === tab.id ? 'bg-primary-500 text-white shadow-sm' : 'text-text-secondary hover:text-text-primary'">
              {{ tab.lab }}
            </button>
          </div>
        </div>

        <!-- Summary Metric Cards -->
        <div v-if="branchRanking?.summary" class="grid grid-cols-2 lg:grid-cols-4 gap-3 mt-4">
          <div class="p-3.5 rounded-xl bg-surface-700/25 border border-surface-700/40">
            <span class="text-[10px] font-bold text-text-secondary uppercase tracking-wider block">Today Global</span>
            <div class="flex items-baseline gap-1 mt-1">
              <span class="text-xs text-text-secondary">#</span>
              <span class="text-2xl font-mono font-black text-emerald-500 dark:text-emerald-400">{{ branchRanking.summary.today_global || '-' }}</span>
            </div>
          </div>
          <div class="p-3.5 rounded-xl bg-surface-700/25 border border-surface-700/40">
            <span class="text-[10px] font-bold text-text-secondary uppercase tracking-wider block">Today Local</span>
            <div class="flex items-baseline gap-1 mt-1">
              <span class="text-xs text-text-secondary">#</span>
              <span class="text-2xl font-mono font-black text-blue-500 dark:text-blue-400">{{ branchRanking.summary.today_local || '-' }}</span>
            </div>
          </div>
          <div class="p-3.5 rounded-xl bg-surface-700/25 border border-surface-700/40">
            <span class="text-[10px] font-bold text-text-secondary uppercase tracking-wider block">Month Global</span>
            <div class="flex items-baseline gap-1 mt-1">
              <span class="text-xs text-text-secondary">#</span>
              <span class="text-2xl font-mono font-black text-purple-500 dark:text-purple-400">{{ branchRanking.summary.this_month_global || '-' }}</span>
            </div>
          </div>
          <div class="p-3.5 rounded-xl bg-surface-700/25 border border-surface-700/40">
            <span class="text-[10px] font-bold text-text-secondary uppercase tracking-wider block">Month Local</span>
            <div class="flex items-baseline gap-1 mt-1">
              <span class="text-xs text-text-secondary">#</span>
              <span class="text-2xl font-mono font-black text-amber-500 dark:text-amber-400">{{ branchRanking.summary.this_month_local || '-' }}</span>
            </div>
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