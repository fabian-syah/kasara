<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, watch } from "vue";
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




// Scanner will be imported dynamically when needed
import StockOutModal from "../../components/inventory/StockOutModal.vue";
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
  },
  pageMode: {
    type: String,
    default: 'inventory', // 'inventory', 'warehouse', 'online_shop', 'distributor'
  },
  title: {
    type: String,
    default: 'Inventory'
  },
  subtitle: {
    type: String,
    default: 'Kelola stok produk di semua cabang'
  },
  hideActions: {
    type: Boolean,
    default: false
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
  Globe,
  PackageSearch,
  LogOut,
  Gift,
  Trophy,
  UserCheck,
  Calendar,
  Percent,
  Archive,
  ChevronLeft,
  ChevronRight,
  CheckCircle,
  Loader2,
  ScanBarcode,
  Upload,
  Warehouse,
  Truck
} from "lucide-vue-next";

const inventoryStore = useInventoryStore();
const authStore = useAuthStore();
const toast = useToast();

import { debounce } from "../../utils/debounce";

// Local state
const isLoading = ref(false);
const isInitialLoading = ref(true);
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
const availableLocations = ref([]);
const selectedLocationKey = ref('all');
const selectedLocationValue = computed(() => {
  if (!selectedLocationKey.value || selectedLocationKey.value === 'all') return null;
  return selectedLocationKey.value.split('_')[1];
});
let inventoryController = null;
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
const selectedItems = ref([]);
const showStockOutModal = ref(false);

const openStockOutModal = () => {
  if (selectedItems.value.length === 0) {
    toast.warning("Pilih minimal satu barang untuk dikeluarkan");
    return;
  }
  showStockOutModal.value = true;
};

const handleStockOutSuccess = () => {
  showStockOutModal.value = false;
  selectedItems.value = [];
  loadInventory(pagination.value.current_page);
};

// Detail Modal
const showDetailModal = ref(false);
const selectedItemDetail = ref(null);
const openDetailModal = (item) => {
  selectedItemDetail.value = item;
  showDetailModal.value = true;
};

// Image Lightbox
const showLightbox = ref(false);
const lightboxSource = ref("");
const openLightbox = (src) => {
  lightboxSource.value = src;
  showLightbox.value = true;
};

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
  fetchFilterOptions();
});



const filterSearchQuery = reactive({
  brand: '',
  product: '',
  capacity: '',
  location: ''
});

async function loadInventory(page = 1) {
  if (inventoryController) inventoryController.abort();
  inventoryController = new AbortController();

  isLoading.value = true;
  try {
    const params = {
      page: page,
      search: debouncedSearch.value,
      type: activeTab.value,
      branch_id: selectedLocationKey.value?.startsWith('branch_') ? selectedLocationValue.value : undefined,
      online_shop_id: selectedLocationKey.value?.startsWith('shop_') ? selectedLocationValue.value : undefined,
      warehouse_id: selectedLocationKey.value?.startsWith('warehouse_') ? selectedLocationValue.value : undefined,
      distributor_id: selectedLocationKey.value?.startsWith('distributor_') ? selectedLocationValue.value : undefined,
      product: filterProduct.value.join(','),
      capacity: filterCapacity.value.join(','),
      brand: filterBrand.value.join(','),
      condition: selectedCondition.value !== 'all' ? selectedCondition.value : undefined,
      stock_status: selectedStockStatus.value !== 'all' ? selectedStockStatus.value : undefined,
      status: selectedStockStatus.value === 'all' ? undefined : selectedStockStatus.value,
      signal: inventoryController.signal
    };

    if (props.pageMode === 'online_shop') {
      params.placement_type = 'online_shop';
    } else if (props.pageMode === 'warehouse') {
      params.placement_type = 'warehouse';
    } else if (props.pageMode === 'distributor') {
      params.placement_type = 'distributor';
      if (authStore.user?.distributor_id) {
        params.distributor_id = authStore.user.distributor_id;
      }
    }

    await inventoryStore.fetchProducts(params);

    inventoryItems.value = inventoryStore.products;
    pagination.value = inventoryStore.pagination;

  } catch (error) {
    if (error.name === 'AbortError') return;
    console.error("Error loading inventory:", error);
    toast.error("Gagal memuat data inventory");
  } finally {
    isLoading.value = false;
    isInitialLoading.value = false;
  }
}

const locations = ref([]);

const canFilterBranch = computed(() => {
  if (props.pageMode !== 'inventory') return true;
  const role = (authStore.userRole || '').toLowerCase();
  return ['super_admin', 'audit', 'owner'].some(r => role.includes(r));
});

const computedBrands = computed(() => {
  return brandOptions.value.filter(b => b.toLowerCase().includes(filterSearchQuery.brand.toLowerCase()));
});

const computedProducts = computed(() => {
  return productOptions.value.filter(p => p.toLowerCase().includes(filterSearchQuery.product.toLowerCase()));
});

const computedCapacities = computed(() => {
  return capacityOptions.value.filter(c => c.toLowerCase().includes(filterSearchQuery.capacity.toLowerCase()));
});

const computedLocations = computed(() => {
  return availableLocations.value.filter(loc => loc.label.toLowerCase().includes(filterSearchQuery.location.toLowerCase()));
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



const branches = ref([]);
const { user } = storeToRefs(authStore);

onMounted(() => {
  document.addEventListener('click', (e) => {
    if (!e.target.closest('.group')) {
      activeFilterDropdown.value = null;
    }
  });

  loadInventory();
  fetchInventoryUsers();
  fetchFilterOptions();
  if (canFilterBranch.value && !props.isEmbedded) {
    fetchLocations();
  }

  if (window.Echo) {
    window.Echo.channel('inventory')
      .listen('.StockInEvent', (e) => {
        inventoryStore.pushNewProduct(e.product);
        toast.success(`Stok baru masuk: ${e.product.product?.name || 'Item'}`);
      });

    window.Echo.channel('stock-out')
      .listen('.StockOutEvent', (e) => {
        inventoryStore.handleStockOut(e.stockOut);
      });
  }


  productTypesApi.list().then(res => {
    typeList.value = res.data.data;
  }).catch(err => console.error("Failed to load types", err));

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

  const p = provinces.value.find(x => x.id == id);
  const name = p ? p.name : "";

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

  if (selectedStockOutCategory.value === 'shopee' || selectedStockOutCategory.value === 'orderan_online') {
    stockOutForm.value.shopee_village = name;
  } else if (selectedStockOutCategory.value === 'giveaway') {
    stockOutForm.value.giveaway_village = name;
  }
}


onUnmounted(() => {
  document.removeEventListener('click', (e) => { });
});

watch(searchQuery, debounce((newVal) => {
  debouncedSearch.value = newVal;
}, 300));

watch(debouncedSearch, () => {
  loadInventory(1);
});

const stockOutCategories = ref([
  { id: 'orderan_online', name: 'Orderan Online', icon: 'ShoppingBag', color: 'orange', role: 'toko_online' },
  { id: 'pindah_cabang', name: 'Pindah Cabang', icon: 'ArrowRightLeft', color: 'blue' },
  { id: 'retur', name: 'Retur Barang', icon: 'RotateCcw', color: 'red' },
  { id: 'kesalahan_input', name: 'Kesalahan Input', icon: 'AlertTriangle', color: 'yellow' },
  { id: 'keluar', name: 'Keluar', icon: 'LogOut', color: 'purple' },
]);

const availableStockOutCategories = computed(() => {
  const role = authStore.userRole;
  return stockOutCategories.value.filter(cat => {
    if (cat.role && role !== cat.role && role !== 'super_admin') return false;
    return true;
  });
});

const requiresInventoryUser = computed(() => {
  return ['pindah_cabang', 'inventaris'].includes(selectedStockOutCategory.value);
});

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

const filteredProducts = computed(() => {
  return inventoryStore.products;
});

const isAllSelected = computed(() => {
  if (filteredProducts.value.length === 0) return false;
  return filteredProducts.value.every(item => isSelected(item));
});

const isSomeSelected = computed(() => {
  if (filteredProducts.value.length === 0) return false;
  const selectedCount = filteredProducts.value.filter(item => isSelected(item)).length;
  return selectedCount > 0 && selectedCount < filteredProducts.value.length;
});

const toggleSelectAll = () => {
  if (selectedItems.value.length === filteredProducts.value.length) {
    selectedItems.value = [];
  } else {
    selectedItems.value = [...filteredProducts.value];
  }
};

function toggleSelect(item) {
  if (!item.type) {
    item.type = activeTab.value;
  }

  const idx = selectedItems.value.findIndex(i => i.id === item.id && i.type === item.type);
  if (idx === -1) {
    if (item.type === 'non-hp') {
      item.out_quantity = 1;
    }
    item.selling_price = item.selling_price || 0;

    selectedItems.value.push(item);
  } else {
    selectedItems.value.splice(idx, 1);
  }
}

function isSelected(item) {
  return selectedItems.value.some(i => i.id === item.id && i.type === activeTab.value);
}

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

const fetchLocations = async () => {
  try {
    const response = await api.get('/inventory/meta-locations');
    const { branches: bList, online_shops: sList, warehouses: wList, distributors: dList } = response.data;

    const locs = [];

    bList.forEach(b => {
      locs.push({ key: `branch_${b.id}`, value: b.id, label: b.name, type: 'branch', icon: MapPin });
    });

    sList.forEach(s => {
      locs.push({ key: `shop_${s.id}`, value: s.id, label: s.name, type: 'online_shop', icon: ShoppingBag });
    });

    wList.forEach(w => {
      locs.push({ key: `warehouse_${w.id}`, value: w.id, label: w.name, type: 'warehouse', icon: Database });
    });

    dList.forEach(d => {
      locs.push({ key: `distributor_${d.id}`, value: d.id, label: d.name, type: 'distributor', icon: Truck });
    });

    availableLocations.value = locs;
  } catch (err) {
    console.error('Error loading locations:', err);
  }
};

async function fetchBranches() {
  try {
    const response = await branchesApi.list();
    const allBranches = response.data.data || response.data;
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



async function exportInventory() {
  try {
    const params = new URLSearchParams({
      type: activeTab.value,
      search: debouncedSearch.value,
      branch_id: effectiveBranchId.value || '',
      online_shop_id: effectiveOnlineShopId.value || '',
      warehouse_id: effectiveWarehouseId.value || '',
      brand: filterBrand.value.join(','),
      product: filterProduct.value.join(','),
      condition: selectedCondition.value !== 'all' ? selectedCondition.value : '',
      stock_status: selectedStockStatus.value !== 'all' ? selectedStockStatus.value : '',
    });

    if (authStore.user?.distributor_id && props.pageMode === 'distributor') {
      params.append('distributor_id', authStore.user.distributor_id);
    }

    const response = await api.get(`/inventory/export?${params.toString()}`, {
      responseType: 'blob'
    });

    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `inventory-${activeTab.value}-${new Date().toISOString().split('T')[0]}.csv`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
    toast.success("Export berhasil dimulai");
  } catch (error) {
    console.error("Export failed:", error);
    toast.error("Gagal melakukan export");
  }
}

</script>

<template>
  <div class="space-y-6 animate-in">
    <!-- Header -->
    <div v-if="!isEmbedded" class="flex flex-col md:flex-row justify-between items-start md:items-end gap-4">
      <div class="flex items-center gap-3">
        <!-- Dynamic Icon based on pageMode -->
        <Warehouse v-if="pageMode === 'warehouse'" :size="32" class="text-amber-500" />
        <Globe v-else-if="pageMode === 'online_shop'" :size="32" class="text-cyan-500" />
        <PackageSearch v-else-if="pageMode === 'distributor'" :size="32" class="text-primary-500" />

        <div>
          <h1 class="text-2xl font-bold text-text-primary tracking-tight">{{ title }}</h1>
          <p class="text-text-secondary mt-1">{{ subtitle }}</p>
        </div>
      </div>
      <div v-if="!hideActions" class="flex flex-wrap gap-2 items-center justify-start md:justify-end w-full md:w-auto">
        <!-- History Buttons -->
        <button class="btn btn-secondary" @click="router.push({ name: 'StockInHistory' })" title="Riwayat Masuk" aria-label="Lihat Riwayat Stok Masuk">
          <Calendar :size="16" />
          <span class="hidden sm:inline">Riwayat Masuk</span>
        </button>
        <button class="btn btn-secondary" @click="router.push({ name: 'StockOutHistory' })" title="Riwayat Keluar" aria-label="Lihat Riwayat Stok Keluar">
          <ArrowDownUp :size="16" />
          <span class="hidden sm:inline">Riwayat Keluar</span>
        </button>

        <button class="btn btn-secondary" @click="router.push({ name: 'outgoing_transfer_history' })"
          title="Riwayat Transfer Keluar (Pindah Cabang)" aria-label="Lihat Riwayat Transfer Keluar">
          <Truck :size="16" />
          <span class="hidden sm:inline">Trx Keluar</span>
        </button>

        <!-- Keluar Stok Button -->
        <button class="btn" :class="selectedItems.length > 0 ? 'btn-primary' : 'btn-secondary'"
          @click="openStockOutModal" :disabled="selectedItems.length === 0" aria-label="Keluarkan stok item terpilih">
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
        class="w-36 rounded-lg py-2.5 text-sm font-medium leading-5 transition-all duration-200"
        :aria-label="tab === 'hp' ? 'Pilih kategori Unit/HP' : 'Pilih kategori NON HP/NON IMEI'"
        :class="activeTab === tab
          ? 'bg-primary-500 text-white shadow-lg shadow-primary-500/25'
          : 'text-text-secondary hover:bg-surface-700/50 hover:text-white'
          ">
        {{ tab === 'hp' ? 'Unit / HP' : 'NON HP / NON IMEI' }}
      </button>
    </div>

    <div class="card">
      <div class="flex flex-col xl:flex-row gap-4 items-start xl:items-center justify-between">
        <!-- Search -->
        <div class="relative w-full xl:w-auto xl:flex-1 min-w-[200px]">
          <label for="inventory-search" class="sr-only">Cari Inventaris</label>
          <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary" :size="18" />
          <input id="inventory-search" v-model="searchQuery" type="text" placeholder="Cari produk, SKU, atau IMEI..."
            class="input w-full pl-10" />
        </div>

        <!-- Filters Wrapper -->
        <div class="flex flex-col md:flex-row flex-wrap gap-3 w-full xl:w-auto items-start md:items-center">

          <!-- Location Filter (Not Embedded Only) -->
          <div class="w-full md:w-56">
            <label for="location-filter" class="sr-only">Filter Lokasi</label>
            <select id="location-filter" v-if="!isEmbedded && canFilterBranch && pageMode !== 'distributor'" v-model="selectedLocationKey"
              class="input w-full bg-surface-800">
              <option value="all">Semua Lokasi</option>
              <option v-for="loc in availableLocations" :key="loc.key" :value="loc.key">
                {{ loc.type === 'branch' ? '[Cabang]' : (loc.type === 'online_shop' ? '[Toko]' : (loc.type === 'distributor' ? '[Distributor]' : '[Gudang]')) }} {{ loc.label }}
              </option>
            </select>
          </div>

          <!-- Month Filter -->
          <div class="w-full md:w-48">
            <label for="month-filter" class="sr-only">Filter Bulan</label>
            <select id="month-filter" v-model="selectedMonth" class="input w-full bg-surface-800">
              <option v-for="opt in monthOptions" :key="opt.label" :value="opt.value">
                {{ opt.label }}
              </option>
            </select>
          </div>

          <!-- Condition Filter -->
          <div class="w-full md:w-32" v-if="activeTab === 'hp'">
            <label for="condition-filter" class="sr-only">Filter Kondisi</label>
            <select id="condition-filter" v-model="selectedCondition" class="input w-full bg-surface-800">
              <option value="all">Kondisi</option>
              <option value="new">Baru</option>
              <option value="used">Bekas</option>
              <option value="ex_ibox">Ex iBox</option>
            </select>
          </div>

          <!-- Stock Status Filter -->
          <div class="w-full md:w-32">
            <label for="stock-status-filter" class="sr-only">Filter Status Stok</label>
            <select id="stock-status-filter" v-model="selectedStockStatus" class="input w-full bg-surface-800">
              <option value="all">S. Stok</option>
              <option value="available">Tersedia</option>
              <option value="sold">Terjual</option>
              <option value="booking">Booking</option>
              <option value="loss">Hilang</option>
            </select>
          </div>

          <button @click="loadInventory(1)" aria-label="Terapkan Filter"
            class="btn btn-primary w-full md:w-auto flex items-center justify-center gap-2">
            <Filter :size="16" />
            Filter
          </button>

          <!-- Export -->
          <button @click="exportInventory" class="btn btn-secondary w-full md:w-auto" aria-label="Export Data">
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
      <div class="table-container overflow-x-auto inventory-table-container">
        <table class="table">
          <thead>
            <tr>
              <th class="w-12">
                <label for="select-all-checkbox" class="flex items-center cursor-pointer">
                  <span class="sr-only">Pilih Semua</span>
                  <input id="select-all-checkbox" type="checkbox" :checked="isAllSelected" :indeterminate.prop="isSomeSelected"
                    @change="toggleSelectAll" class="checkbox border-surface-400" />
                </label>
              </th>

              <template v-if="activeTab === 'hp'">
                <!-- Filterable Brand Column (Faceted) -->
                <th class="cursor-pointer group relative">
                  <button @click="toggleFilterDropdown('brand')" class="flex items-center justify-between w-full font-semibold uppercase tracking-wider text-xs outline-none" aria-label="Filter Merek">
                    <span>Merek</span>
                    <Filter :size="14" :class="filterBrand.length > 0 ? 'text-blue-400' : 'text-surface-400'" />
                  </button>
                  <!-- Dropdown -->
                  <div v-if="activeFilterDropdown === 'brand'"
                    class="absolute left-0 top-full mt-2 w-48 bg-surface-800 border border-surface-700 rounded-lg shadow-xl z-50 p-2">
                    <div class="px-1 pb-2 border-b border-surface-700 mb-1 sticky top-0 bg-surface-800">
                      <label for="brand-search" class="sr-only">Cari Merek</label>
                      <input id="brand-search" v-model="filterSearchQuery.brand" placeholder="Cari..." class="w-full bg-surface-900 text-xs p-1.5 rounded outline-none border border-surface-700 focus:border-primary-500" @click.stop />
                    </div>
                    <div class="max-h-52 overflow-y-auto custom-scrollbar">
                      <div v-for="option in computedBrands" :key="option"
                        class="flex items-center gap-2 p-1.5 hover:bg-surface-700 rounded cursor-pointer"
                        @click.stop="toggleFilter(filterBrand, option)">
                        <div class="w-4 h-4 border rounded flex items-center justify-center transition-colors"
                          :class="filterBrand.includes(option) ? 'bg-blue-600 border-blue-600' : 'border-surface-500'">
                          <Check v-if="filterBrand.includes(option)" :size="10" class="text-white" />
                        </div>
                        <span class="text-sm text-text-primary truncate">{{ option }}</span>
                      </div>
                      <div v-if="computedBrands.length === 0" class="text-xs text-text-secondary text-center py-2">Tidak ada hasil</div>
                    </div>
                  </div>
                </th>

                <!-- Filterable Product Column (Faceted) -->
                <th class="cursor-pointer group relative">
                  <button @click="toggleFilterDropdown('product')" class="flex items-center justify-between w-full font-semibold uppercase tracking-wider text-xs outline-none" aria-label="Filter Produk">
                    <span>Produk</span>
                    <Filter :size="14" :class="filterProduct.length > 0 ? 'text-blue-400' : 'text-surface-400'" />
                  </button>
                  <!-- Dropdown -->
                  <div v-if="activeFilterDropdown === 'product'"
                    class="absolute left-0 top-full mt-2 w-56 bg-surface-800 border border-surface-700 rounded-lg shadow-xl z-50 p-2">
                    <div class="px-1 pb-2 border-b border-surface-700 mb-1 sticky top-0 bg-surface-800">
                      <label for="product-search" class="sr-only">Cari Produk</label>
                      <input id="product-search" v-model="filterSearchQuery.product" placeholder="Cari..." class="w-full bg-surface-900 text-xs p-1.5 rounded outline-none border border-surface-700 focus:border-primary-500" @click.stop />
                    </div>
                    <div class="max-h-52 overflow-y-auto custom-scrollbar">
                      <div v-for="option in computedProducts" :key="option"
                        class="flex items-center gap-2 p-1.5 hover:bg-surface-700 rounded cursor-pointer"
                        @click.stop="toggleFilter(filterProduct, option)">
                        <div class="w-4 h-4 border rounded flex items-center justify-center transition-colors"
                          :class="filterProduct.includes(option) ? 'bg-blue-600 border-blue-600' : 'border-surface-500'">
                          <Check v-if="filterProduct.includes(option)" :size="10" class="text-white" />
                        </div>
                        <span class="text-sm text-text-primary truncate">{{ option }}</span>
                      </div>
                      <div v-if="computedProducts.length === 0" class="text-xs text-text-secondary text-center py-2">Tidak ada hasil</div>
                    </div>
                  </div>
                </th>

                <!-- Filterable Capacity Column (Faceted) -->
                <th class="hidden lg:table-cell cursor-pointer group relative">
                  <button @click="toggleFilterDropdown('capacity')" class="flex items-center justify-between w-full font-semibold uppercase tracking-wider text-xs outline-none" aria-label="Filter Kapasitas">
                    <span>Kapasitas</span>
                    <Filter :size="14" :class="filterCapacity.length > 0 ? 'text-blue-400' : 'text-surface-400'" />
                  </button>
                  <!-- Dropdown -->
                  <div v-if="activeFilterDropdown === 'capacity'"
                    class="absolute left-0 top-full mt-2 w-48 bg-surface-800 border border-surface-700 rounded-lg shadow-xl z-50 p-2">
                    <div class="px-1 pb-2 border-b border-surface-700 mb-1 sticky top-0 bg-surface-800">
                      <label for="capacity-search" class="sr-only">Cari Kapasitas</label>
                      <input id="capacity-search" v-model="filterSearchQuery.capacity" placeholder="Cari..." class="w-full bg-surface-900 text-xs p-1.5 rounded outline-none border border-surface-700 focus:border-primary-500" @click.stop />
                    </div>
                    <div class="max-h-52 overflow-y-auto custom-scrollbar">
                      <div v-for="option in computedCapacities" :key="option"
                        class="flex items-center gap-2 p-1.5 hover:bg-surface-700 rounded cursor-pointer"
                        @click.stop="toggleFilter(filterCapacity, option)">
                        <div class="w-4 h-4 border rounded flex items-center justify-center transition-colors"
                          :class="filterCapacity.includes(option) ? 'bg-blue-600 border-blue-600' : 'border-surface-500'">
                          <Check v-if="filterCapacity.includes(option)" :size="10" class="text-white" />
                        </div>
                        <span class="text-sm text-text-primary">{{ option }}</span>
                      </div>
                      <div v-if="computedCapacities.length === 0" class="text-xs text-text-secondary text-center py-2">Tidak ada hasil</div>
                    </div>
                  </div>
                </th>

                <th class="hidden lg:table-cell">Kondisi</th>
                <th>IMEI</th>
                <th class="hidden md:table-cell">Lokasi</th>
                <th class="hidden xl:table-cell">Distributor</th>
                <th class="text-right">Harga Jual</th>
                <th>Status</th>
              </template>

              <!-- Non-HP Columns -->
              <template v-else>
                <th>Merek</th>
                <th>Produk</th>
                <th>Kategori</th>
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
            <tr v-if="isLoading">
              <td colspan="15" class="p-0">
                <div v-for="i in 5" :key="i" class="flex items-center border-b border-surface-700/50 p-4 gap-4">
                  <div class="w-10 h-10 bg-surface-700 rounded-lg animate-pulse"></div>
                  <div class="flex-1 space-y-2">
                    <div class="h-4 bg-surface-700 rounded animate-pulse w-1/4"></div>
                    <div class="h-3 bg-surface-800 rounded animate-pulse w-1/2"></div>
                  </div>
                  <div class="w-24 h-4 bg-surface-700 rounded animate-pulse"></div>
                  <div class="w-24 h-4 bg-surface-700 rounded animate-pulse"></div>
                </div>
              </td>
            </tr>
            <tr v-else-if="filteredProducts.length === 0">
              <td colspan="15" class="text-center py-12">
                <div class="flex flex-col items-center justify-center w-full h-full">
                  <Box :size="48" class="text-text-secondary mb-2" />
                  <p class="text-text-secondary text-center">Tidak ada data ditemukan</p>
                </div>
              </td>
            </tr>
            <tr v-else v-for="item in filteredProducts" :key="item.id" @click="toggleSelect(item)"
              class="cursor-pointer transition-all hover:bg-surface-700/30"
              :class="isSelected(item) ? 'bg-primary-500/10' : ''">
              <td @click.stop>
                <label :for="'item-select-' + item.id" class="flex items-center cursor-pointer">
                  <span class="sr-only">Pilih item {{ item.product?.name }}</span>
                  <input :id="'item-select-' + item.id" type="checkbox" :checked="isSelected(item)" @change="toggleSelect(item)"
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
                  <span class="bg-surface-800 px-3 py-1 rounded-lg text-text-secondary" v-if="item.ram || item.storage">
                    {{ [item.ram, item.storage].filter(Boolean).join('/') }}
                  </span>
                  <span v-else class="text-text-secondary">-</span>
                </td>
                <td class="text-sm hidden lg:table-cell">
                  <span class="badge"
                    :class="item.condition === 'new' ? 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400' : (item.condition === 'ex_ibox' ? 'bg-purple-500/20 text-purple-600 dark:text-purple-400' : 'bg-amber-500/20 text-amber-600 dark:text-amber-400')">
                    {{ item.condition === 'new' ? 'Baru' : (item.condition === 'ex_ibox' ? 'Ex iBox' : 'Bekas') }}
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
                   {{ item.latest_distributor || item.latest_supplier || '-' }}
                </td>
                <td class="text-sm font-bold text-blue-500 text-right">
                  Rp {{ formatNumber(item.selling_price || item.price) }}
                </td>
                <td>
                  <span class="badge"
                    :class="item.status === 'available' ? 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400' : 'bg-surface-600 text-text-primary'">
                    {{ item.status }}
                  </span>
                </td>
              </template>

              <!-- Non-HP Specific Columns -->
              <template v-else>
                <td>
                  <span v-if="item.product?.non_imei_category" class="px-2.5 py-1 rounded-lg bg-surface-800 text-xs font-bold text-primary-400 border border-primary-500/20 uppercase tracking-tight">
                    {{ item.product.non_imei_category }}
                  </span>
                  <span v-else class="text-text-secondary/30 text-xs italic">Umum</span>
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
                    <span v-if="item.placement_id" class="text-xs ml-1 text-surface-400">#{{ item.placement_id }}</span>
                  </div>
                </td>
                <td>
                  <span class="text-lg font-bold text-text-primary">{{ item.quantity }}</span>
                  <span class="text-xs text-text-secondary ml-1">Pcs</span>
                </td>
                <td class="text-sm text-text-secondary hidden xl:table-cell">
                  {{ item.latest_distributor || item.latest_supplier || '-' }}
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

                  <button @click.stop="openDetailModal(item)"
                    class="p-2 hover:bg-surface-700 rounded-lg transition-colors"
                    :aria-label="'Lihat detail ' + (item.product?.name || 'barang')">
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
          class="p-2 rounded-lg hover:bg-surface-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors text-text-secondary"
          aria-label="Halaman sebelumnya">
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
          class="p-2 rounded-lg hover:bg-surface-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors text-text-secondary"
          aria-label="Halaman berikutnya">
          <ChevronRight :size="20" />
        </button>
      </div>
    </div>

    <!-- Detail Modal -->
    <div v-if="showDetailModal" class="fixed inset-0 z-[60] flex items-center justify-center p-4 sm:p-6">
      <div class="absolute inset-0 bg-surface-950/80 backdrop-blur-sm" @click="showDetailModal = false"></div>
      <div
        class="relative w-full max-w-2xl bg-surface-900 border border-surface-800 rounded-3xl shadow-2xl overflow-hidden animate-in">
        <div class="p-6 border-b border-surface-800 flex justify-between items-center bg-surface-800/50">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-primary-500/20 flex items-center justify-center">
              <Eye :size="20" class="text-primary-400" />
            </div>
            <div>
              <h3 class="text-xl font-bold text-text-primary">Detail Barang</h3>
              <p class="text-xs text-text-secondary mt-0.5">Informasi lengkap unit inventory</p>
            </div>
          </div>
          <button @click="showDetailModal = false"
            class="p-2 hover:bg-surface-700 rounded-xl transition-colors text-text-secondary"
            aria-label="Tutup modal detail">
            <X :size="20" />
          </button>
        </div>

        <div class="p-6 max-h-[70vh] overflow-y-auto custom-scrollbar">
          <div v-if="selectedItemDetail" class="space-y-6">
            <!-- Basic Info -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div class="space-y-4">
                <div>
                  <label class="text-[10px] font-bold text-text-secondary uppercase tracking-wider block mb-1">Merek &
                    Tipe</label>
                  <p class="text-lg font-bold text-text-primary">{{ selectedItemDetail.product?.brand }} {{
                    selectedItemDetail.product?.name }}</p>
                </div>
                <div v-if="selectedItemDetail.imei">
                  <label
                    class="text-[10px] font-bold text-text-secondary uppercase tracking-wider block mb-1">IMEI</label>
                  <p class="font-mono text-primary-400 font-bold tracking-wider">{{ selectedItemDetail.imei }}</p>
                </div>
                <div v-if="activeTab === 'non-hp'">
                  <label class="text-[10px] font-bold text-text-secondary uppercase tracking-wider block mb-1">Stok
                    Tersedia</label>
                  <p class="text-text-primary font-bold">{{ selectedItemDetail.quantity }} Pcs</p>
                </div>
                <div v-if="activeTab !== 'hp'">
                  <label class="text-[10px] font-bold text-text-secondary uppercase tracking-wider block mb-1">Harga
                    Modal
                    (HPP)</label>
                  <p class="text-lg font-bold text-amber-500">{{ formatCurrency(selectedItemDetail.cost_price) }}</p>
                </div>
                <div>
                  <label class="text-[10px] font-bold text-text-secondary uppercase tracking-wider block mb-1">Harga
                    Jual</label>
                  <p class="text-lg font-bold text-blue-500">{{ formatCurrency(selectedItemDetail.selling_price) }}</p>
                </div>
              </div>
              <div class="space-y-4">
                <div>
                  <label class="text-[10px] font-bold text-text-secondary uppercase tracking-wider block mb-1">Kapasitas
                    &
                    Kondisi</label>
                  <div class="flex flex-wrap gap-2 mt-1">
                    <span v-if="selectedItemDetail.ram || selectedItemDetail.storage"
                      class="bg-surface-800 px-3 py-1 rounded-lg text-sm text-text-primary border border-surface-700">
                      {{ [selectedItemDetail.ram, selectedItemDetail.storage].filter(Boolean).join('/') }}
                    </span>
                    <span class="badge"
                      :class="selectedItemDetail.condition === 'new' ? 'bg-emerald-500/20 text-emerald-400' : (selectedItemDetail.condition === 'ex_ibox' ? 'bg-purple-500/20 text-purple-400' : 'bg-amber-500/20 text-amber-400')">
                      {{
                        selectedItemDetail.condition === 'new' ? 'Baru' :
                          (selectedItemDetail.condition === 'ex_ibox' ?
                            'ExiBox' : 'Bekas')
                      }}
                    </span>
                  </div>
                </div>
                <div>
                  <label class="text-[10px] font-bold text-text-secondary uppercase tracking-wider block mb-1">Lokasi
                    Saat
                    Ini</label>
                  <p class="text-text-primary font-medium">{{ selectedItemDetail.placement_name }}</p>
                  <span class="text-[10px] text-text-secondary uppercase">{{
                    selectedItemDetail.placement_type?.replace('_',
                      ' ') }}</span>
                </div>
              </div>
            </div>

            <!-- Refund Info Section -->
            <div v-if="selectedItemDetail.refund"
              class="bg-primary-500/5 rounded-2xl border border-primary-500/20 overflow-hidden">
              <div class="p-4 bg-primary-500/10 flex items-center gap-2 border-b border-primary-500/20">
                <RotateCcw :size="16" class="text-primary-400" />
                <h4 class="text-sm font-bold text-primary-400 uppercase tracking-widest">Informasi Refund (Barang Masuk)
                </h4>
              </div>
              <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-4">
                  <div>
                    <label
                      class="text-[10px] font-bold text-text-secondary uppercase tracking-wider block mb-1">Customer</label>
                    <p class="text-text-primary font-bold">{{ selectedItemDetail.refund.customer_name }}</p>
                    <p class="text-xs text-text-secondary">{{ selectedItemDetail.refund.customer_phone }}</p>
                  </div>
                  <div>
                    <label class="text-[10px] font-bold text-text-secondary uppercase tracking-wider block mb-1">Harga
                      Refund (Modal)</label>
                    <p class="text-lg font-bold text-emerald-400">{{
                      formatCurrency(selectedItemDetail.refund.refund_price)
                    }}</p>
                  </div>
                  <div>
                    <label class="text-[10px] font-bold text-text-secondary uppercase tracking-wider block mb-1">Alasan
                      Refund</label>
                    <p class="text-sm text-text-primary italic">"{{ selectedItemDetail.refund.reason }}"</p>
                  </div>
                </div>
                <div class="space-y-4">
                  <div>
                    <label class="text-[10px] font-bold text-text-secondary uppercase tracking-wider block mb-1">Metode
                      Bayar Refund</label>
                    <p class="text-text-primary font-medium">{{ selectedItemDetail.refund.payment_method?.name || '-' }}
                    </p>
                  </div>
                  <div>
                    <label class="text-[10px] font-bold text-text-secondary uppercase tracking-wider block mb-1">Foto
                      Bukti</label>
                    <div class="flex flex-wrap gap-3 mt-2">
                      <div v-if="selectedItemDetail.refund.photo_unit"
                        @click="openLightbox(storageUrl + '/storage/' + selectedItemDetail.refund.photo_unit)"
                        class="group relative w-20 h-20 sm:w-24 sm:h-24 rounded-xl bg-surface-800 border border-surface-700 overflow-hidden hover:border-primary-500/50 transition-all cursor-pointer">
                        <img :src="storageUrl + '/storage/' + selectedItemDetail.refund.photo_unit"
                          class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
                          :alt="'Foto unit refund ' + selectedItemDetail.product?.name" />
                        <div
                          class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity">
                          <Eye :size="16" class="text-white" />
                        </div>
                        <span
                          class="absolute bottom-0 left-0 right-0 bg-black/60 text-[8px] text-white text-center py-0.5">Unit</span>
                      </div>
                      <div v-else
                        class="w-20 h-20 sm:w-24 sm:h-24 rounded-xl bg-surface-800 border border-surface-700 border-dashed flex flex-col items-center justify-center text-surface-600">
                        <Smartphone :size="20" />
                        <span class="text-[8px]">No Photo</span>
                      </div>
                      <div v-if="selectedItemDetail.refund.photo_customer"
                        @click="openLightbox(storageUrl + '/storage/' + selectedItemDetail.refund.photo_customer)"
                        class="group relative w-20 h-20 sm:w-24 sm:h-24 rounded-xl bg-surface-800 border border-surface-700 overflow-hidden hover:border-primary-500/50 transition-all cursor-pointer">
                        <img :src="storageUrl + '/storage/' + selectedItemDetail.refund.photo_customer"
                          class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
                          alt="Customer" loading="lazy" />
                        <div
                          class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity">
                          <Eye :size="16" class="text-white" />
                        </div>
                        <span
                          class="absolute bottom-0 left-0 right-0 bg-black/60 text-[8px] text-white text-center py-0.5">Customer</span>
                      </div>
                      <div v-else
                        class="w-20 h-20 sm:w-24 sm:h-24 rounded-xl bg-surface-800 border border-surface-700 border-dashed flex flex-col items-center justify-center text-surface-600">
                        <UserCheck :size="20" />
                        <span class="text-[8px]">No Photo</span>
                      </div>
                    </div>
                  </div>
                  <div>
                    <label class="text-[10px] font-bold text-text-secondary uppercase tracking-wider block mb-1">Diinput
                      Oleh</label>
                    <p class="text-xs text-text-primary font-medium">{{ selectedItemDetail.user?.name || '-' }}</p>
                    <p class="text-[10px] text-text-secondary">{{ selectedItemDetail.created_at ? new
                      Date(selectedItemDetail.created_at).toLocaleString('id-ID') : '-' }}</p>
                  </div>
                </div>
              </div>
              <div v-if="selectedItemDetail.refund.notes" class="px-5 pb-5">
                <label class="text-[10px] font-bold text-text-secondary uppercase tracking-wider block mb-1">Catatan
                  Tambahan</label>
                <p class="text-xs text-text-primary bg-surface-800/50 p-3 rounded-xl border border-surface-700/50">{{
                  selectedItemDetail.refund.notes }}</p>
              </div>
            </div>

            <!-- Trade In Info Section -->
            <div v-if="selectedItemDetail.trade_in"
              class="bg-amber-500/5 rounded-2xl border border-amber-500/20 overflow-hidden">
              <div class="p-4 bg-amber-500/10 flex items-center gap-2 border-b border-amber-500/20">
                <RefreshCw :size="16" class="text-amber-400" />
                <h4 class="text-sm font-bold text-amber-400 uppercase tracking-widest">Informasi Angkat Barang
                  (Trade-In)
                </h4>
              </div>
              <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-4">
                  <div>
                    <label
                      class="text-[10px] font-bold text-text-secondary uppercase tracking-wider block mb-1">Customer</label>
                    <p class="text-text-primary font-bold">{{ selectedItemDetail.trade_in.customer_name }}</p>
                    <p class="text-xs text-text-secondary">{{ selectedItemDetail.trade_in.customer_phone }}</p>
                  </div>
                  <div>
                    <label class="text-[10px] font-bold text-text-secondary uppercase tracking-wider block mb-1">Harga
                      Beli</label>
                    <p class="text-lg font-bold text-amber-400">{{ formatCurrency(selectedItemDetail.trade_in.buy_price)
                    }}
                    </p>
                  </div>
                  <div v-if="selectedItemDetail.trade_in.reason">
                    <label
                      class="text-[10px] font-bold text-text-secondary uppercase tracking-wider block mb-1">Alasan</label>
                    <p class="text-sm text-text-primary italic">"{{ selectedItemDetail.trade_in.reason }}"</p>
                  </div>
                </div>
                <div class="space-y-4">
                  <div>
                    <label class="text-[10px] font-bold text-text-secondary uppercase tracking-wider block mb-1">Metode
                      Bayar</label>
                    <p class="text-text-primary font-medium">{{ selectedItemDetail.trade_in.payment_method?.name || '-'
                    }}
                    </p>
                  </div>
                  <div>
                    <label class="text-[10px] font-bold text-text-secondary uppercase tracking-wider block mb-1">Foto
                      Bukti</label>
                    <div class="flex flex-wrap gap-3 mt-2">
                      <div v-if="selectedItemDetail.trade_in.photo_unit"
                        @click="openLightbox(storageUrl + '/storage/' + selectedItemDetail.trade_in.photo_unit)"
                        class="group relative w-20 h-20 sm:w-24 sm:h-24 rounded-xl bg-surface-800 border border-surface-700 overflow-hidden hover:border-amber-500/50 transition-all cursor-pointer">
                        <img :src="storageUrl + '/storage/' + selectedItemDetail.trade_in.photo_unit"
                          class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
                          alt="Unit" loading="lazy" />
                        <div
                          class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity">
                          <Eye :size="16" class="text-white" />
                        </div>
                        <span
                          class="absolute bottom-0 left-0 right-0 bg-black/60 text-[8px] text-white text-center py-0.5">Unit</span>
                      </div>
                      <div v-else
                        class="w-20 h-20 sm:w-24 sm:h-24 rounded-xl bg-surface-800 border border-surface-700 border-dashed flex flex-col items-center justify-center text-surface-600">
                        <Smartphone :size="20" />
                        <span class="text-[8px]">No Photo</span>
                      </div>

                      <div v-if="selectedItemDetail.trade_in.photo_customer"
                        @click="openLightbox(storageUrl + '/storage/' + selectedItemDetail.trade_in.photo_customer)"
                        class="group relative w-20 h-20 sm:w-24 sm:h-24 rounded-xl bg-surface-800 border border-surface-700 overflow-hidden hover:border-amber-500/50 transition-all cursor-pointer">
                        <img :src="storageUrl + '/storage/' + selectedItemDetail.trade_in.photo_customer"
                          class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
                          alt="Customer" />
                        <div
                          class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity">
                          <Eye :size="16" class="text-white" />
                        </div>
                        <span
                          class="absolute bottom-0 left-0 right-0 bg-black/60 text-[8px] text-white text-center py-0.5">Customer</span>
                      </div>
                      <div v-else
                        class="w-20 h-20 sm:w-24 sm:h-24 rounded-xl bg-surface-800 border border-surface-700 border-dashed flex flex-col items-center justify-center text-surface-600">
                        <UserCheck :size="20" />
                        <span class="text-[8px]">No Photo</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div v-if="selectedItemDetail.trade_in.notes" class="px-5 pb-5">
                <label class="text-[10px] font-bold text-text-secondary uppercase tracking-wider block mb-1">Catatan
                  Tambahan</label>
                <p class="text-xs text-text-primary bg-surface-800/50 p-3 rounded-xl border border-surface-700/50">{{
                  selectedItemDetail.trade_in.notes }}</p>
              </div>
            </div>

          </div>
        </div>

        <div class="p-6 border-t border-surface-800 bg-surface-800/30 flex justify-end">
          <button @click="showDetailModal = false" class="btn btn-secondary px-8">Tutup</button>
        </div>
      </div>
    </div>

    <!-- Image Lightbox Modal -->
    <div v-if="showLightbox"
      class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/95 transition-all animate-in"
      @click="showLightbox = false">
      <button @click="showLightbox = false"
        class="absolute top-6 right-6 p-3 bg-white/10 hover:bg-white/20 rounded-full text-white transition-all">
        <X :size="24" />
      </button>
      <img :src="lightboxSource" class="max-w-full max-h-[90vh] object-contain shadow-2xl rounded-lg" @click.stop />
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

.inventory-table-container {
  content-visibility: auto;
  contain-intrinsic-size: 1px 500px;
}
</style>
