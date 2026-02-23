<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from "vue";
import { useRouter } from "vue-router";
import { useInventoryStore } from "../../store/inventory";
import { useAuthStore } from "../../store/auth";
import { storeToRefs } from "pinia";
import { useToast } from "../../composables/useToast";
import api, {
  inventory as inventoryApi,
  productTypes as productTypesApi,
  brands as brandsApi,
  products as productsApi,
  users as usersApi,
  branches as branchesApi,
  warehouses as warehousesApi,
  onlineShops as onlineShopsApi,
  distributors as distributorsApi
} from "../../api/axios";
import { formatCurrency, formatNumber, parseCurrency } from "../../utils/formatters";

// ... (existing code)




import { Html5Qrcode } from "html5-qrcode";
const router = useRouter();
const props = defineProps({
  isEmbedded: {
    type: Boolean,
    default: false
  },
  branchId: {
    type: [Number, String],
    default: null
  },
  onlineShopId: {
    type: [Number, String],
    default: null
  }
});
const apiUrl = import.meta.env.VITE_API_URL || 'https://api.stokps.com/api';
const storageUrl = apiUrl.replace(/\/api\/?$/, '');
import {
  Search,
  Package,
  AlertTriangle,
  ArrowDownUp,
  Plus,
  Filter,
  Download,
  RefreshCw,
  Eye,

  ChevronDown,
  TrendingUp,
  TrendingDown,
  Box,
  Smartphone,
  X,
  Building2,
  RotateCcw,
  ShoppingBag,
  LogOut,
  Gift,
  Trophy,
  UserCheck,
  Calendar,
  Percent,
  Archive,
  ChevronLeft,
  CheckCircle,
  Loader2,
  ScanBarcode,
  Upload,
  Warehouse
} from "lucide-vue-next";

const inventoryStore = useInventoryStore();
const authStore = useAuthStore();
const toast = useToast();

import { debounce } from "../../utils/debounce";

// Local state
const isLoading = ref(false);
const inventoryItems = ref([]);
const pagination = ref({
  current_page: 1,
  last_page: 1,
  total: 0,
  from: 0,
  to: 0
});
const searchQuery = ref("");
const debouncedSearch = ref("");
const selectedCategory = ref('');
const selectedBrand = ref('');
const selectedCondition = ref('all');
const selectedStockStatus = ref('all');
// Month Filter
const currentDate = new Date();
const currentMonth = currentDate.getMonth() + 1;
const currentYear = currentDate.getFullYear();
const prevDate = new Date();
prevDate.setMonth(prevDate.getMonth() - 1);
const prevMonth = prevDate.getMonth() + 1;
const prevYear = prevDate.getFullYear();

const monthOptions = [
  {
    label: currentDate.toLocaleString('id-ID', { month: 'long', year: 'numeric' }),
    value: { month: currentMonth, year: currentYear }
  },
  {
    label: prevDate.toLocaleString('id-ID', { month: 'long', year: 'numeric' }),
    value: { month: prevMonth, year: prevYear }
  }
];
const selectedMonth = ref(monthOptions[0].value);

const typeList = ref([]);
const brandList = ref([]);
// Column Filters (Faceted)
const filterProduct = ref([])
const filterCapacity = ref([])
const filterBrand = ref([])

// Filter Options
const productOptions = ref([])
const capacityOptions = ref([])
const brandOptions = ref([])

// Dropdown Visibility State
const activeFilterDropdown = ref(null); // 'product', 'capacity', or null

function toggleFilterDropdown(filterName) {
  if (activeFilterDropdown.value === filterName) {
    activeFilterDropdown.value = null;
  } else {
    activeFilterDropdown.value = filterName;
  }
}

// Close dropdown when removing all filters or clicking outside (handled by template click.stop)
function closeFilterDropdown() {
  activeFilterDropdown.value = null;
}

// Toggle filter values
function toggleFilter(filterSet, value) {
  const index = filterSet.indexOf(value);
  if (index === -1) {
    filterSet.push(value);
  } else {
    filterSet.splice(index, 1);
  }
  loadInventory(1); // Trigger reload
}

// Fetch Filter Options
async function fetchFilterOptions() {
  try {
    const response = await api.get('/inventory/filter-options', {
      params: { type: activeTab.value }
    });
    productOptions.value = response.data.products;
    capacityOptions.value = response.data.capacities;
    brandOptions.value = response.data.brands;
  } catch (error) {
    console.error("Failed to fetch filter options", error);
  }
}

const showStockInModal = ref(false);

// Tab Switch
const activeTab = ref("hp"); // 'hp' or 'non-hp'

// Watch tab change to reload data and reset filters
watch(activeTab, () => {
  // Reset search and filters on tab change
  searchQuery.value = "";
  debouncedSearch.value = "";
  filterProduct.value = [];
  filterCapacity.value = [];
  filterBrand.value = [];
  selectedCondition.value = 'all';
  selectedStockStatus.value = 'all';

  loadInventory(1);
  fetchFilterOptions(); // Re-fetch options for new tab
});

async function loadInventory(page = 1) {
  isLoading.value = true;
  try {
    const params = {
      page: page,
      search: debouncedSearch.value,
      type: activeTab.value,
      branch_id: effectiveBranchId.value,
      online_shop_id: effectiveOnlineShopId.value,
      warehouse_id: effectiveWarehouseId.value,
      product: filterProduct.value.join(','),
      capacity: filterCapacity.value.join(','),
      brand: filterBrand.value.join(','),
      condition: selectedCondition.value !== 'all' ? selectedCondition.value : undefined,
      stock_status: selectedStockStatus.value !== 'all' ? selectedStockStatus.value : undefined,
      status: selectedStockStatus.value === 'all' ? undefined : selectedStockStatus.value,
    };

    // Use store action to populate both inventoryStore.products AND pagination
    await inventoryStore.fetchProducts(params);

    // Sync local ref for components that still rely on it (if any)
    inventoryItems.value = inventoryStore.products;
    pagination.value = inventoryStore.pagination;

  } catch (error) {
    console.error("Error loading inventory:", error);
    toast.error("Gagal memuat data inventory");
  } finally {
    isLoading.value = false;
  }
}

const locations = ref([]);
const selectedLocationKey = ref('all');

const effectiveBranchId = computed(() => {
  if (props.isEmbedded) return props.branchId || undefined;
  if (selectedLocationKey.value === 'all' || !selectedLocationKey.value.startsWith('B:')) return undefined;
  return selectedLocationKey.value.split(':')[1];
});

const effectiveOnlineShopId = computed(() => {
  if (props.isEmbedded) return props.onlineShopId || undefined;
  if (selectedLocationKey.value === 'all' || !selectedLocationKey.value.startsWith('S:')) return undefined;
  return selectedLocationKey.value.split(':')[1];
});

const effectiveWarehouseId = computed(() => {
  if (props.isEmbedded) return undefined;
  if (selectedLocationKey.value === 'all' || !selectedLocationKey.value.startsWith('W:')) return undefined;
  return selectedLocationKey.value.split(':')[1];
});

const canFilterBranch = computed(() => {
  const role = (authStore.userRole || '').toLowerCase();
  return ['super_admin', 'audit', 'owner'].some(r => role.includes(r));
});

// Watchers
watch([debouncedSearch, selectedCondition, selectedStockStatus, filterProduct, filterCapacity, filterBrand, selectedLocationKey], () => {
  loadInventory(1);
});

watch(() => props.branchId, () => {
  loadInventory(1);
});

watch(() => props.onlineShopId, () => {
  loadInventory(1);
});


function changePage(page) {
  if (page >= 1 && page <= pagination.value.last_page) {
    loadInventory(page);
  }
}


const selectedItems = ref([]);
const showStockOutModal = ref(false);

function openStockOutModal() {
  if (selectedItems.value.length === 0) {
    import('../../composables/useToast').then(m => m.useToast().error('Pilih setidaknya satu item untuk dikeluarkan'));
    return;
  }
  showStockOutModal.value = true;
}

function handleStockOutSuccess() {
  selectedItems.value = [];
  showStockOutModal.value = false;
  loadInventory(1);
}

const branches = ref([]);
const { user } = storeToRefs(authStore);

onMounted(() => {
  document.addEventListener('click', (e) => {
    // If click is not inside a th with group class, close dropdown
    if (!e.target.closest('.group')) {
      activeFilterDropdown.value = null;
    }
  });

  loadInventory(); // Use new loader
  fetchInventoryUsers();
  fetchFilterOptions(); // Fetch options on mount
  if (canFilterBranch.value && !props.isEmbedded) {
    fetchLocations();
  }

  // Real-time Updates
  if (window.Echo) {
    window.Echo.channel('inventory')
      .listen('.StockInEvent', (e) => {
        console.log('StockIn Event:', e);
        // The event name might be prefixed with dot or namespace depending on Laravel Echo config.
        // Usually it's just the class name if broadcastAs returns it, or full class name.
        // In my Event class I used broadcastAs() return 'StockInEvent'.
        // So .listen('.StockInEvent') or just 'StockInEvent'.
        // If I used broadcastAs, I don't need dot.
        // Wait, if I use broadcastAs, it's just that string.
        // Let's assume 'StockInEvent'.

        inventoryStore.pushNewProduct(e.product);
        toast.success(`Stok baru masuk: ${e.product.product?.name || 'Item'}`);
      });

    window.Echo.channel('stock-out')
      .listen('.StockOutEvent', (e) => {
        console.log('StockOut Event:', e);
        inventoryStore.handleStockOut(e.stockOut);
      });
  }
  fetchBranches();
  fetchWarehouses();
  fetchOnlineShops();
  fetchDistributors();

  // Fetch Product Types for capacity lookup
  productTypesApi.list().then(res => {
    typeList.value = res.data.data;
  }).catch(err => console.error("Failed to load types", err));

  // Fetch Brands
  brandsApi.list().then(res => {
    brandList.value = res.data.data || res.data;
  }).catch(err => console.error("Failed to load brands", err));

  fetchProvinces();
});

// --- Region Logic ---
const provinces = ref([]);
const cities = ref([]);
const districts = ref([]);
const villages = ref([]);

const selectedRegionIds = ref({
  province: "",
  city: "",
  district: "",
  village: ""
});

async function fetchProvinces() {
  try {
    const res = await fetch(`https://www.emsifa.com/api-wilayah-indonesia/api/provinces.json`);
    provinces.value = await res.json();
  } catch (e) { console.error(e); }
}

async function onProvinceChange(id) {
  selectedRegionIds.value.province = id;
  selectedRegionIds.value.city = "";
  selectedRegionIds.value.district = "";
  selectedRegionIds.value.village = "";
  cities.value = []; districts.value = []; villages.value = [];

  // Save Name based on Category
  const p = provinces.value.find(x => x.id == id);
  const name = p ? p.name : "";
  console.log("Province Selected:", id, p, name);

  if (selectedStockOutCategory.value === 'shopee' || selectedStockOutCategory.value === 'orderan_online') {
    stockOutForm.value.shopee_province = name;
  } else if (selectedStockOutCategory.value === 'giveaway') {
    stockOutForm.value.giveaway_province = name;
  }

  if (id) {
    try {
      const res = await fetch(`https://www.emsifa.com/api-wilayah-indonesia/api/regencies/${id}.json`);
      cities.value = await res.json();
    } catch (e) { console.error(e); }
  }
}

async function onCityChange(id) {
  selectedRegionIds.value.city = id;
  selectedRegionIds.value.district = "";
  selectedRegionIds.value.village = "";
  districts.value = []; villages.value = [];

  const c = cities.value.find(x => x.id == id);
  const name = c ? c.name : "";
  console.log("City Selected:", id, c, name);

  if (selectedStockOutCategory.value === 'shopee' || selectedStockOutCategory.value === 'orderan_online') {
    stockOutForm.value.shopee_city = name;
  } else if (selectedStockOutCategory.value === 'giveaway') {
    stockOutForm.value.giveaway_city = name;
  }

  if (id) {
    try {
      const res = await fetch(`https://www.emsifa.com/api-wilayah-indonesia/api/districts/${id}.json`);
      districts.value = await res.json();
    } catch (e) { console.error(e); }
  }
}

async function onDistrictChange(id) {
  selectedRegionIds.value.district = id;
  selectedRegionIds.value.village = "";
  villages.value = [];

  const d = districts.value.find(x => x.id == id);
  const name = d ? d.name : "";
  console.log("District Selected:", id, d, name);

  if (selectedStockOutCategory.value === 'shopee' || selectedStockOutCategory.value === 'orderan_online') {
    stockOutForm.value.shopee_district = name;
  } else if (selectedStockOutCategory.value === 'giveaway') {
    stockOutForm.value.giveaway_district = name;
  }

  if (id) {
    try {
      const res = await fetch(`https://www.emsifa.com/api-wilayah-indonesia/api/villages/${id}.json`);
      villages.value = await res.json();
    } catch (e) { console.error(e); }
  }
}

function onVillageChange(id) {
  selectedRegionIds.value.village = id;
  const v = villages.value.find(x => x.id == id);
  const name = v ? v.name : "";
  console.log("Village Selected:", id, v, name);

  if (selectedStockOutCategory.value === 'shopee' || selectedStockOutCategory.value === 'orderan_online') {
    stockOutForm.value.shopee_village = name;
  } else if (selectedStockOutCategory.value === 'giveaway') {
    stockOutForm.value.giveaway_village = name;
  }
}


onUnmounted(() => {
  document.removeEventListener('click', (e) => { });
});

// Watcher untuk debounce search
watch(searchQuery, debounce((newVal) => {
  debouncedSearch.value = newVal;
}, 300));

// Fix: Reset page to 1 when search changes
watch(debouncedSearch, () => {
  loadInventory(1);
});

// Stock Out Categories
const stockOutCategories = ref([
  { id: 'orderan_online', name: 'Orderan Online', icon: 'ShoppingBag', color: 'orange', role: 'toko_online' },
  { id: 'pindah_cabang', name: 'Pindah Cabang', icon: 'ArrowRightLeft', color: 'blue' },
  { id: 'retur', name: 'Retur Barang', icon: 'RotateCcw', color: 'red' },
  { id: 'kesalahan_input', name: 'Kesalahan Input', icon: 'AlertTriangle', color: 'yellow' },
  { id: 'keluar', name: 'Keluar', icon: 'LogOut', color: 'purple' },
]);

const keluarSubCategories = [
  'Giveaway customer',
  'Hadiah',
  'Brand ambasador',
  'Event / Sponsorship',
  'Promo',
  'Inventaris'
];

const availableStockOutCategories = computed(() => {
  const role = authStore.userRole; // Assuming role slip is stored here
  return stockOutCategories.value.filter(cat => {
    if (cat.role && role !== cat.role && role !== 'super_admin') return false;
    return true;
  });
});

const requiresInventoryUser = computed(() => {
  return ['pindah_cabang', 'inventaris'].includes(selectedStockOutCategory.value);
});

// Inventory Users State
const inventoryUsers = ref([]);
const selectedInventoryUser = ref(null);
const isLoadingUsers = ref(false);

async function fetchInventoryUsers() {
  isLoadingUsers.value = true;
  try {
    const response = await usersApi.list({ role: 'inventory' });
    inventoryUsers.value = response.data.data || response.data;
  } catch (e) {
    console.error("Failed to load inventory users", e);
  } finally {
    isLoadingUsers.value = false;
  }
}

// Filtered items (Granular)
const filteredProducts = computed(() => {
  // Rely on Server Side Filtering
  // The API already filtered based on search, category, brand, etc.
  let items = inventoryStore.products;

  // Only keep the Non-HP 0 quantity filter if needed visually,
  // but backend also filters quantity > 0 for index.
  // We can keep it or remove it. Backend index() for non-hp does `where('quantity', '>', 0)`.
  // So we can just return items.

  return items;
});

// Selection helpers
const isAllSelected = computed(() => {
  if (filteredProducts.value.length === 0) return false;
  return filteredProducts.value.every(item => isSelected(item));
});

const isSomeSelected = computed(() => {
  if (filteredProducts.value.length === 0) return false;
  const selectedCount = filteredProducts.value.filter(item => isSelected(item)).length;
  return selectedCount > 0 && selectedCount < filteredProducts.value.length;
});

function toggleSelectAll() {
  if (isAllSelected.value) {
    filteredProducts.value.forEach(item => {
      // Ensure strict matching with ID and Type
      const itemType = item.type || activeTab.value;
      const idx = selectedItems.value.findIndex(i => i.id === item.id && i.type === itemType);
      if (idx !== -1) selectedItems.value.splice(idx, 1);
    });
  } else {
    filteredProducts.value.forEach(item => {
      // Ensure usage of toggleSelect logic for consistency
      // But we can manually do it here to avoid toggle behavior
      if (!item.type) {
        item.type = activeTab.value;
      }

      if (!isSelected(item)) {
        // Init properties
        if (item.type === 'non-hp' && !item.out_quantity) {
          item.out_quantity = 1;
        }
        item.selling_price = item.selling_price || 0;

        selectedItems.value.push(item);
      }
    });
  }
}

function toggleSelect(item) {
  // Ensure item has type
  if (!item.type) {
    item.type = activeTab.value;
  }

  const idx = selectedItems.value.findIndex(i => i.id === item.id && i.type === item.type);
  if (idx === -1) {
    // Init quantity for non-hp
    if (item.type === 'non-hp') {
      item.out_quantity = 1;
    }
    // Init selling price
    item.selling_price = item.selling_price || 0;

    selectedItems.value.push(item);
  } else {
    selectedItems.value.splice(idx, 1);
  }
}

function isSelected(item) {
  return selectedItems.value.some(i => i.id === item.id && i.type === activeTab.value);
}

// Cleanup


// Stats
const stats = computed(() => [
  {
    label: "Total Produk",
    value: inventoryStore.totalProducts,
    icon: Package,
    color: "blue",
  },
  {
    label: "Nilai Inventori",
    value: formatCurrency(inventoryStore.totalValue),
    icon: TrendingUp,
    color: "emerald",
  },
]);



const currentBranch = ref(null);
const isTogglingReturn = ref(false);

async function fetchCurrentBranch() {
  if (authStore.userBranch?.id) {
    try {
      const response = await branchesApi.get(authStore.userBranch.id);
      currentBranch.value = response.data.data || response.data;
    } catch (e) {
      console.error("Gagal load info branch", e);
    }
  }
}

const warehouses = ref([]);

async function fetchWarehouses() {
  try {
    const response = await warehousesApi.list();
    warehouses.value = response.data.data || response.data;
  } catch (e) {
    console.error("Gagal load warehouses", e);
  }
}

async function fetchLocations() {
  try {
    const [branchRes, shopRes, warehouseRes] = await Promise.all([
      branchesApi.list(),
      onlineShopsApi.list(),
      warehousesApi.list()
    ]);
    const allBranches = (branchRes.data?.data || branchRes.data || [])
      .filter(b => b.is_active !== false && b.is_active !== 0)
      .map(b => ({ ...b, type: 'branch' }));
    const allShops = (shopRes.data?.data || shopRes.data || [])
      .filter(s => s.is_active !== false && s.is_active !== 0)
      .map(s => ({ ...s, type: 'online_shop' }));
    const allWarehouses = (warehouseRes.data?.data || warehouseRes.data || [])
      .filter(w => w.is_active !== false && w.is_active !== 0)
      .map(w => ({ ...w, type: 'warehouse' }));
    const allLocations = [...allBranches, ...allShops, ...allWarehouses];

    const user = authStore.user;
    const role = (authStore.userRole || '').toLowerCase();
    const isGlobalRole = ['super_admin', 'owner'].includes(role);

    let allowedBranchIds = [];
    if (user?.branch_id) allowedBranchIds.push(user.branch_id);
    let allowedShopIds = [];
    if (user?.online_shop_id) allowedShopIds.push(user.online_shop_id);
    let allowedWarehouseIds = [];
    if (user?.warehouse_id) allowedWarehouseIds.push(user.warehouse_id);

    if (user?.placements && Array.isArray(user.placements)) {
      user.placements.forEach(p => {
        if (p.model_type === 'branch') allowedBranchIds.push(p.model_id);
        if (p.model_type === 'online_shop') allowedShopIds.push(p.model_id);
        if (p.model_type === 'warehouse') allowedWarehouseIds.push(p.model_id);
      });
    }

    allowedBranchIds = [...new Set(allowedBranchIds.map(id => Number(id)))];
    allowedShopIds = [...new Set(allowedShopIds.map(id => Number(id)))];
    allowedWarehouseIds = [...new Set(allowedWarehouseIds.map(id => Number(id)))];

    const hasAnyRestriction = allowedBranchIds.length > 0 || allowedShopIds.length > 0 || allowedWarehouseIds.length > 0;

    if (isGlobalRole || (role === 'audit' && !hasAnyRestriction)) {
      locations.value = allLocations;
    } else if (hasAnyRestriction) {
      locations.value = allLocations.filter(loc => {
        if (loc.type === 'branch') return allowedBranchIds.includes(Number(loc.id));
        if (loc.type === 'online_shop') return allowedShopIds.includes(Number(loc.id));
        if (loc.type === 'warehouse') return allowedWarehouseIds.includes(Number(loc.id));
        return false;
      });
      if (locations.value.length === 1 && selectedLocationKey.value === 'all') {
        const loc = locations.value[0];
        selectedLocationKey.value = `${loc.type === 'branch' ? 'B' : loc.type === 'online_shop' ? 'S' : 'W'}:${loc.id}`;
      }
    } else {
      locations.value = [];
    }
  } catch (error) {
    console.error('Error fetching locations:', error);
  }
}

async function fetchBranches() {
  try {
    const response = await branchesApi.list();
    const allBranches = response.data.data || response.data;
    // Filter to only active branches
    branches.value = allBranches.filter(b => b.is_active);
  } catch (e) {
    console.error("Gagal memuat cabang", e);
  }
}


const onlineShops = ref([]);
const distributors = ref([]);

async function fetchOnlineShops() {
  try {
    const response = await onlineShopsApi.list();
    onlineShops.value = response.data.data || response.data;
  } catch (e) {
    console.error("Gagal load online shops", e);
  }
}

async function fetchDistributors() {
  try {
    const response = await distributorsApi.list();
    distributors.value = response.data.data || response.data;
  } catch (e) {
    console.error("Gagal load distributors", e);
  }
}



const categories = computed(() => inventoryStore.categories);

const canToggleReturn = computed(() => {
  const allowedRoles = ['super_admin'];
  return authStore.hasRole(allowedRoles);
});

function getStockStatus(product) {
  if (product.stock === 0)
    return { label: "Habis", class: "bg-red-500/20 text-red-400" };
  if (product.stock <= product.minStock)
    return { label: "Menipis", class: "bg-amber-500/20 text-amber-400" };
  return { label: "Tersedia", class: "bg-emerald-500/20 text-emerald-400" };
}




</script>

<template>
  <div class="space-y-6 animate-in">
    <!-- Header -->
    <div v-if="!isEmbedded" class="flex flex-col md:flex-row justify-between items-start md:items-end gap-4">
      <div>
        <h1 class="text-2xl font-bold text-text-primary tracking-tight">Inventory</h1>
        <p class="text-text-secondary mt-1">Kelola stok produk di semua cabang</p>
      </div>
      <div class="flex flex-wrap gap-2 items-center w-full md:w-auto">
        <!-- History Buttons -->
        <button class="btn btn-secondary" @click="router.push({ name: 'StockInHistory' })" title="Riwayat Masuk">
          <Calendar :size="16" />
          <span class="hidden sm:inline">Riwayat Masuk</span>
        </button>
        <button class="btn btn-secondary" @click="router.push({ name: 'StockOutHistory' })" title="Riwayat Keluar">
          <ArrowDownUp :size="16" />
          <span class="hidden sm:inline">Riwayat Keluar</span>
        </button>
        <button class="btn btn-secondary" @click="router.push({ name: 'StockOpname' })" title="Stock Opname">
          <Archive :size="16" />
          <span class="hidden sm:inline">Stock Opname</span>
        </button>

        <!-- Keluar Stok Button -->
        <button class="btn" :class="selectedItems.length > 0 ? 'btn-primary' : 'btn-secondary'"
          @click="openStockOutModal" :disabled="selectedItems.length === 0">
          <ArrowDownUp :size="16" />
          Keluar Stok
          <span v-if="selectedItems.length > 0" class="ml-1 bg-white/20 px-2 py-0.5 rounded-full text-xs">
            {{ selectedItems.length }}
          </span>
        </button>
        <button class="btn btn-primary" @click="router.push({ name: 'StockIn' })">
          <Plus :size="16" />
          Tambah Stok Masuk
        </button>
      </div>
    </div>

    <!-- Stats -->
    <div v-if="!isEmbedded" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
      <div v-for="(stat, index) in stats" :key="index" class="card flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl flex items-center justify-center" :class="{
          'bg-blue-600': stat.color === 'blue',
          'bg-emerald-600': stat.color === 'emerald',
          'bg-amber-600': stat.color === 'amber',
          'bg-red-600': stat.color === 'red',
        }">
          <component :is="stat.icon" :size="20" class="text-white" />
        </div>
        <div>
          <p class="text-text-secondary text-sm">{{ stat.label }}</p>
          <p class="text-xl font-bold text-text-primary">{{ stat.value }}</p>
        </div>
      </div>
    </div>

    <!-- Tab Switcher -->
    <div class="flex space-x-1 rounded-xl bg-surface-800 p-1 w-full md:w-fit overflow-x-auto">
      <button v-for="tab in ['hp', 'non-hp']" :key="tab" @click="activeTab = tab"
        class="w-32 rounded-lg py-2.5 text-sm font-medium leading-5 transition-all duration-200" :class="activeTab === tab
          ? 'bg-blue-600 text-white shadow'
          : 'text-text-secondary hover:bg-surface-700/50 hover:text-white'
          ">
        {{ tab === 'hp' ? 'Unit / HP' : 'NON HP / NON IMEI' }}
      </button>
    </div>

    <div class="card">
      <div class="flex flex-col xl:flex-row gap-4 items-start xl:items-center justify-between">
        <!-- Search -->
        <div class="relative w-full xl:w-auto xl:flex-1 min-w-[200px]">
          <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary" :size="18" />
          <input v-model="searchQuery" type="text" placeholder="Cari produk, SKU, atau IMEI..."
            class="input w-full pl-10" />
        </div>

        <!-- Filters Wrapper -->
        <div class="flex flex-col md:flex-row flex-wrap gap-3 w-full xl:w-auto items-start md:items-center">

          <!-- Location Filter (Not Embedded Only) -->
          <select v-if="!isEmbedded && canFilterBranch" v-model="selectedLocationKey"
            class="input w-full md:w-48 bg-surface-800">
            <option value="all">Semua Cabang/Toko</option>
            <option v-for="loc in locations" :key="`${loc.type}:${loc.id}`"
              :value="`${loc.type === 'branch' ? 'B' : loc.type === 'online_shop' ? 'S' : 'W'}:${loc.id}`">
              <span v-if="loc.type === 'branch'">[Cabang]</span>
              <span v-else-if="loc.type === 'online_shop'">[Toko]</span>
              <span v-else>[Gudang]</span>
              {{ loc.name }}
            </option>
          </select>

          <!-- Month Filter -->
          <select v-model="selectedMonth" class="input w-full md:w-48 bg-surface-800">
            <option v-for="(option, index) in monthOptions" :key="index" :value="option.value">
              {{ option.label }}
            </option>
          </select>


          <!-- Condition Filter (New - Only for HP) -->
          <select v-if="activeTab === 'hp'" v-model="selectedCondition" @change="loadInventory(1)"
            class="input w-full md:w-48 bg-surface-800">
            <option value="all">Semua Kondisi</option>
            <option value="new">Baru</option>
            <option value="second">Bekas</option>
          </select>

          <button @click="loadInventory(1)"
            class="btn btn-primary w-full md:w-auto flex items-center justify-center gap-2">
            <Filter :size="16" />
            Filter
          </button>

          <!-- Export -->
          <button class="btn btn-secondary w-full md:w-auto">
            <Download :size="16" />
            Export
          </button>
        </div>
      </div>
    </div>

    <!-- Selection Info -->
    <div v-if="selectedItems.length > 0"
      class="bg-primary-500/10 border border-primary-500/30 rounded-xl p-4 flex items-center justify-between">
      <p class="text-primary-400 font-medium">
        {{ selectedItems.length }} item dipilih
      </p>
      <button @click="selectedItems = []" class="text-primary-400 hover:text-primary-300 text-sm">
        Batalkan Semua
      </button>
    </div>

    <!-- Table -->
    <div class="card p-0 overflow-hidden">
      <div class="table-container overflow-x-auto">
        <table class="table">
          <thead>
            <tr>
              <th class="w-12">
                <label class="flex items-center cursor-pointer">
                  <input type="checkbox" :checked="isAllSelected" :indeterminate.prop="isSomeSelected"
                    @change="toggleSelectAll" class="checkbox border-surface-400" />
                </label>
              </th>

              <template v-if="activeTab === 'hp'">
                <!-- Filterable Brand Column (Faceted) -->
                <th class="min-w-[120px] relative group" @click.stop>
                  <div class="flex items-center justify-between cursor-pointer" @click="toggleFilterDropdown('brand')">
                    <span>Merek</span>
                    <Filter :size="14" :class="filterBrand.length > 0 ? 'text-blue-400' : 'text-surface-400'" />
                  </div>
                  <!-- Dropdown -->
                  <div v-if="activeFilterDropdown === 'brand'"
                    class="absolute left-0 top-full mt-2 w-48 bg-surface-800 border border-surface-700 rounded-lg shadow-xl z-50 p-2 max-h-60 overflow-y-auto">
                    <div v-for="option in brandOptions" :key="option"
                      class="flex items-center gap-2 p-1.5 hover:bg-surface-700 rounded cursor-pointer"
                      @click.stop="toggleFilter(filterBrand, option)">
                      <div class="w-4 h-4 border rounded flex items-center justify-center transition-colors"
                        :class="filterBrand.includes(option) ? 'bg-blue-600 border-blue-600' : 'border-surface-500'">
                        <Check v-if="filterBrand.includes(option)" :size="10" class="text-white" />
                      </div>
                      <span class="text-sm text-text-primary truncate">{{ option }}</span>
                    </div>
                    <div v-if="brandOptions.length === 0" class="text-xs text-text-secondary text-center py-2">No
                      options</div>
                  </div>
                </th>

                <!-- Filterable Product Column (Faceted) -->
                <th class="min-w-[150px] relative group" @click.stop>
                  <div class="flex items-center justify-between cursor-pointer"
                    @click="toggleFilterDropdown('product')">
                    <span>Produk</span>
                    <Filter :size="14" :class="filterProduct.length > 0 ? 'text-blue-400' : 'text-surface-400'" />
                  </div>
                  <!-- Dropdown -->
                  <div v-if="activeFilterDropdown === 'product'"
                    class="absolute left-0 top-full mt-2 w-48 bg-surface-800 border border-surface-700 rounded-lg shadow-xl z-50 p-2 max-h-60 overflow-y-auto">
                    <div v-for="option in productOptions" :key="option"
                      class="flex items-center gap-2 p-1.5 hover:bg-surface-700 rounded cursor-pointer"
                      @click.stop="toggleFilter(filterProduct, option)">
                      <div class="w-4 h-4 border rounded flex items-center justify-center transition-colors"
                        :class="filterProduct.includes(option) ? 'bg-blue-600 border-blue-600' : 'border-surface-500'">
                        <Check v-if="filterProduct.includes(option)" :size="10" class="text-white" />
                      </div>
                      <span class="text-sm text-text-primary truncate">{{ option }}</span>
                    </div>
                    <div v-if="productOptions.length === 0" class="text-xs text-text-secondary text-center py-2">No
                      options</div>
                  </div>
                </th>

                <!-- Filterable Capacity Column (Faceted) -->
                <th class="hidden lg:table-cell min-w-[120px] relative group" @click.stop>
                  <div class="flex items-center justify-between cursor-pointer"
                    @click="toggleFilterDropdown('capacity')">
                    <span>Kapasitas</span>
                    <Filter :size="14" :class="filterCapacity.length > 0 ? 'text-blue-400' : 'text-surface-400'" />
                  </div>
                  <!-- Dropdown -->
                  <div v-if="activeFilterDropdown === 'capacity'"
                    class="absolute left-0 top-full mt-2 w-40 bg-surface-800 border border-surface-700 rounded-lg shadow-xl z-50 p-2 max-h-60 overflow-y-auto">
                    <div v-for="option in capacityOptions" :key="option"
                      class="flex items-center gap-2 p-1.5 hover:bg-surface-700 rounded cursor-pointer"
                      @click.stop="toggleFilter(filterCapacity, option)">
                      <div class="w-4 h-4 border rounded flex items-center justify-center transition-colors"
                        :class="filterCapacity.includes(option) ? 'bg-blue-600 border-blue-600' : 'border-surface-500'">
                        <Check v-if="filterCapacity.includes(option)" :size="10" class="text-white" />
                      </div>
                      <span class="text-sm text-text-primary">{{ option }}</span>
                    </div>
                    <div v-if="capacityOptions.length === 0" class="text-xs text-text-secondary text-center py-2">No
                      options</div>
                  </div>
                </th>

                <th class="hidden lg:table-cell">Kondisi</th>
                <th>IMEI</th>
                <th class="hidden md:table-cell">Lokasi</th>
                <th class="hidden xl:table-cell">Distributor</th>
                <th>Harga Jual</th>
                <th>Status</th>
              </template>

              <!-- Non-HP Columns -->
              <template v-else>
                <th>Merek</th>
                <th>Produk</th>
                <th class="hidden md:table-cell">Lokasi</th>
                <th>Stok</th>
                <th class="hidden xl:table-cell">Distributor / Supplier</th>
              </template>

              <th class="hidden 2xl:table-cell">Catatan</th>
              <th class="hidden xl:table-cell">Akun Inventory</th>
              <th class="text-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="inventoryStore.isLoading">
              <td colspan="10" class="text-center py-12">
                <RefreshCw :size="24" class="animate-spin mx-auto text-blue-400 mb-2" />
                <p class="text-text-secondary">Memuat data...</p>
              </td>
            </tr>
            <tr v-else-if="filteredProducts.length === 0">
              <td colspan="10" class="text-center py-12">
                <Box :size="48" class="mx-auto text-text-secondary mb-2" />
                <p class="text-text-secondary">Tidak ada data ditemukan</p>
              </td>
            </tr>
            <tr v-else v-for="item in filteredProducts" :key="item.id" @click="toggleSelect(item)"
              class="cursor-pointer transition-all hover:bg-surface-700/30"
              :class="isSelected(item) ? 'bg-primary-500/10' : ''">
              <td @click.stop>
                <label class="flex items-center">
                  <input type="checkbox" :checked="isSelected(item)" @change="toggleSelect(item)"
                    class="checkbox border-surface-400" />
                </label>
              </td>
              <td class="text-sm text-text-secondary whitespace-nowrap">
                {{ item.product?.brand || '-' }}
              </td>
              <td class="min-w-[200px]">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 bg-surface-700 rounded-lg flex items-center justify-center">
                    <Smartphone v-if="activeTab === 'hp'" :size="16" class="text-text-secondary" />
                    <Box v-else :size="16" class="text-text-secondary" />
                  </div>
                  <div>
                    <p class="font-medium text-text-primary">{{ item.product?.name }}</p>
                  </div>
                </div>
              </td>

              <!-- HP Specific Columns -->
              <template v-if="activeTab === 'hp'">
                <td class="text-sm hidden lg:table-cell">
                  <span class="bg-surface-800 px-3 py-1 rounded-lg text-text-secondary" v-if="item.storage">{{
                    item.storage
                  }}</span>
                  <span v-else class="text-text-secondary">-</span>
                </td>
                <td class="text-sm hidden lg:table-cell">
                  <span class="badge"
                    :class="item.condition === 'new' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-amber-500/20 text-amber-400'">
                    {{ item.condition === 'new' ? 'Baru' : 'Bekas' }}
                  </span>
                </td>
                <td class="font-mono text-sm">
                  <div class="bg-surface-700/50 px-2 py-1 rounded w-fit text-text-primary">{{ item.imei }}</div>
                </td>
                <td class="text-sm text-text-secondary hidden md:table-cell">
                  <div v-if="item.placement_name" class="font-medium text-text-primary">
                    {{ item.placement_name }}
                    <span class="text-[10px] text-text-secondary block capitalize">
                      {{ item.placement_type?.replace('_', ' ') }}
                    </span>
                  </div>
                  <div v-else>
                    <span class="capitalize">{{ item.placement_type?.replace('_', ' ') }}</span>
                    <span v-if="item.placement_id" class="text-xs ml-1 text-surface-400">#{{ item.placement_id
                    }}</span>
                  </div>
                </td>
                <td class="text-sm text-text-secondary hidden xl:table-cell">
                  {{ item.distributor?.name || item.supplier_name || '-' }}
                </td>
                <!-- Cost Price Removed -->
                <td class="text-text-primary font-medium">
                  {{ formatCurrency(item.selling_price) }}
                </td>
                <td>
                  <span class="badge"
                    :class="item.status === 'available' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-surface-600 text-surface-300'">
                    {{ item.status }}
                  </span>
                </td>
              </template>

              <!-- Non-HP Specific Columns -->
              <template v-else>
                <td class="text-sm text-text-secondary hidden md:table-cell">
                  <div v-if="item.placement_name" class="font-medium text-text-primary">
                    {{ item.placement_name }}
                    <span class="text-[10px] text-text-secondary block capitalize">
                      {{ item.placement_type?.replace('_', ' ') }}
                    </span>
                  </div>
                  <div v-else>
                    <span class="capitalize">{{ item.placement_type?.replace('_', ' ') }}</span>
                    <span v-if="item.placement_id" class="text-xs ml-1 text-surface-400">#{{ item.placement_id }}</span>
                  </div>
                </td>
                <td>
                  <span class="text-lg font-bold text-text-primary">{{ item.quantity }}</span>
                  <span class="text-xs text-text-secondary ml-1">Pcs</span>
                </td>
                <td class="text-sm text-text-secondary hidden xl:table-cell">
                  {{ item.latest_supplier || item.latest_distributor || '-' }}
                </td>
              </template>

              <td class="max-w-[200px] hidden 2xl:table-cell">
                <span v-if="item.notes" class="text-xs text-text-secondary italic block truncate" :title="item.notes">
                  {{ item.notes }}
                </span>
                <span v-else class="text-text-secondary/30">-</span>
              </td>

              <td class="hidden xl:table-cell">
                <div class="flex flex-col">
                  <!-- For Non-HP, user info might not be directly on item, but typically 'updated_by' or similar. 
                             Inventory model doesn't strictly track owner like ProductDetail does. 
                             We'll show '-' if not available or maybe the updated_at -->
                  <span class="text-sm font-medium text-text-primary">{{ item.user?.full_name || item.user?.name ||
                    '-'
                  }}</span>
                  <span class="text-[10px] text-text-secondary">{{ item.user?.username }}</span>
                </div>
              </td>
              <td @click.stop>
                <div class="flex items-center justify-center gap-2">

                  <button class="p-2 hover:bg-surface-700 rounded-lg transition-colors">
                    <Eye :size="16" class="text-text-secondary" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Pagination -->
    <div class="flex items-center justify-between mt-4 mb-8" v-if="inventoryStore.pagination.total > 0">
      <div class="text-sm text-text-secondary">
        Showing {{ inventoryStore.pagination.from }} to {{ inventoryStore.pagination.to }} of {{
          inventoryStore.pagination.total }} results
      </div>
      <div class="flex items-center gap-2">
        <button @click="changePage(inventoryStore.pagination.current_page - 1)"
          :disabled="inventoryStore.pagination.current_page === 1"
          class="p-2 rounded-lg hover:bg-surface-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors text-text-secondary">
          <ChevronLeft :size="20" />
        </button>

        <div class="flex items-center gap-1">
          <button v-for="page in inventoryStore.pagination.last_page" :key="page" @click="changePage(page)"
            v-show="Math.abs(page - inventoryStore.pagination.current_page) <= 2 || page === 1 || page === inventoryStore.pagination.last_page"
            class="w-8 h-8 rounded-lg flex items-center justify-center text-sm font-medium transition-colors"
            :class="page === inventoryStore.pagination.current_page ? 'bg-primary-500 text-white' : 'hover:bg-surface-700 text-text-secondary'">
            <span
              v-if="Math.abs(page - inventoryStore.pagination.current_page) === 2 && page !== 1 && page !== inventoryStore.pagination.last_page">...</span>
            <span v-else>{{ page }}</span>
          </button>
        </div>

        <button @click="changePage(inventoryStore.pagination.current_page + 1)"
          :disabled="inventoryStore.pagination.current_page === inventoryStore.pagination.last_page"
          class="p-2 rounded-lg hover:bg-surface-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors text-text-secondary">
          <ChevronRight :size="20" />
        </button>
      </div>
    </div>

    <!-- Stock Out Modal Component -->
    <StockOutModal :show="showStockOutModal" :selectedItems="selectedItems" :activeTab="activeTab"
      @close="showStockOutModal = false" @success="handleStockOutSuccess" />
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

.checkbox {
  width: 1.375rem;
  height: 1.375rem;
  border-radius: 0.375rem;
  border: 2.5px solid rgb(100, 116, 139);
  /* slate-500 - more visible */
  background-color: transparent;
  cursor: pointer;
  transition: all 0.2s;
  appearance: none;
  position: relative;
  flex-shrink: 0;
}

.checkbox:hover {
  border-color: rgb(var(--color-primary-400));
  background-color: rgba(var(--color-primary-500), 0.1);
}

.checkbox:checked {
  background-color: rgb(var(--color-primary-500));
  border-color: rgb(var(--color-primary-500));
}

.checkbox:checked::after {
  content: '';
  position: absolute;
  left: 6px;
  top: 2px;
  width: 5px;
  height: 10px;
  border: solid white;
  border-width: 0 2.5px 2.5px 0;
  transform: rotate(45deg);
}

.checkbox:indeterminate {
  background-color: rgb(var(--color-primary-500));
  border-color: rgb(var(--color-primary-500));
}

.checkbox:indeterminate::after {
  content: '';
  position: absolute;
  left: 3px;
  top: 8px;
  width: 12px;
  height: 2.5px;
  background: white;
  border-radius: 1px;
}

.label {
  display: block;
  color: rgb(var(--color-text-secondary));
  margin-bottom: 0.5rem;
  font-weight: 600;
  font-size: 0.875rem;
}
</style>
