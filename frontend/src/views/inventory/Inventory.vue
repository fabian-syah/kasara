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

// Watch tab change to reload data
watch(activeTab, () => {
  // Use inventoryStore action if possible, or direct API
  loadInventory();
  fetchFilterOptions(); // Re-fetch options for new tab
});

async function loadInventory(page = 1) {
  // Use inventoryStore action if possible, or direct API
  isLoading.value = true;
  try {
    const params = {
      page: page,
      search: debouncedSearch.value,
      type: activeTab.value, // 'hp' or 'non-hp'
      branch_id: props.branchId || undefined, // Add branch_id filter
      // Add other filters
      product: filterProduct.value.join(','),
      capacity: filterCapacity.value.join(','),
      brand: filterBrand.value.join(','),
      condition: selectedCondition.value !== 'all' ? selectedCondition.value : undefined,
      stock_status: selectedStockStatus.value !== 'all' ? selectedStockStatus.value : undefined,
    };

    const response = await inventoryApi.list(params);
    inventoryItems.value = response.data.data;
    pagination.value = {
      current_page: response.data.current_page,
      last_page: response.data.last_page,
      total: response.data.total,
      from: response.data.from,
      to: response.data.to
    };
  } catch (error) {
    console.error("Error loading inventory:", error);
    toast.error("Gagal memuat data inventory");
  } finally {
    isLoading.value = false;
  }
}

// Watchers
watch([debouncedSearch, selectedCondition, selectedStockStatus, filterProduct, filterCapacity, filterBrand], () => {
  loadInventory(1);
});

watch(() => props.branchId, () => {
  loadInventory(1);
});

// Tab Change
watch(activeTab, () => {
  // Reset filters on tab change? Maybe.
  // filterProduct.value = []
  // filterCapacity.value = []
  // filterBrand.value = []
  loadInventory(1);
  fetchFilterOptions();
})

function changePage(page) {
  if (page >= 1 && page <= pagination.value.last_page) {
    loadInventory(page);
  }
}


const selectedItems = ref([]);
const showStockOutModal = ref(false);
const selectedStockOutCategory = ref(null);
const isSubmitting = ref(false);

// Stock Out Form
const stockOutForm = ref({
  sub_category: '', // Added for Keluar category
  destination_branch_id: null,
  receiver_name: '',
  transfer_notes: '',
  deletion_reason: '',
  retur_officer: '',
  retur_seal: '',
  retur_issue: '',
  customer_name: '',
  customer_phone: '',
  return_destination_id: null,
  proof_image: null,
  shopee_receiver: '',
  shopee_phone: '',
  shopee_address: '',
  shopee_province: '',
  shopee_city: '',
  shopee_district: '',
  shopee_village: '',
  shopee_postal_code: '',
  shopee_notes: '',
  shopee_tracking_no: '',
  selling_price: null,

  // Giveaway Fields
  giveaway_receiver: '',
  giveaway_phone: '',
  giveaway_address: '',
  giveaway_province: '',
  giveaway_city: '',
  giveaway_district: '',
  giveaway_village: '',
  giveaway_postal_code: '',
  giveaway_notes: '',

  notes: '',
});

// Modal Searcher State
const modalSearchQuery = ref("");
const modalSearchResults = ref([]);
const isSearchingInModal = ref(false);

async function searchInModal() {
  if (modalSearchQuery.value.length < 2) {
    modalSearchResults.value = [];
    return;
  }
  isSearchingInModal.value = true;
  try {
    const response = await inventoryStore.fetchProducts({
      search: modalSearchQuery.value,
      per_page: 5,
      type: activeTab.value // Match current tab for searching
    }, true); // Pass true to indicate we want a return, not just state update
    modalSearchResults.value = response.data;
  } catch (e) {
    console.error("Modal search failed", e);
  } finally {
    isSearchingInModal.value = false;
  }
}

function addItemFromModal(item) {
  if (!isSelected(item)) {
    toggleSelect(item);
    toast.success(`${item.product?.name || 'Item'} ditambahkan`);
  } else {
    toast.info("Item sudah ada di daftar");
  }
}

// Barcode Scanner
const isScanning = ref(false);
const scannerContainerId = 'barcode-scanner-container-modal';
const scanningItemIndex = ref(null); // Track which item is being scanned
let html5QrCode = null;

// Per-item forms for Shopee
const shopeeItemForms = ref([]);

// Proof image for retur
const proofImageFile = ref(null);
const proofImagePreview = ref(null);

// Branches & Warehouses
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
  document.removeEventListener('click', (e) => { }); // Cleanup
  if (html5QrCode) {
    html5QrCode.stop().catch(() => { });
  }
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

// Stock Out Modal Functions
function openStockOutModal() {
  if (selectedItems.value.length === 0) {
    toast.error("Pilih setidaknya satu item untuk dikeluarkan");
    return;
  }
  showStockOutModal.value = true;
  selectedStockOutCategory.value = null;
  fetchInventoryUsers(); // Load users when opening modal
}

function closeStockOutModal() {
  showStockOutModal.value = false;
  selectedStockOutCategory.value = null;
  selectedInventoryUser.value = null; // Reset user
  modalSearchQuery.value = "";
  modalSearchResults.value = [];
  resetStockOutForm();
}

function selectStockOutCategory(category) {
  selectedStockOutCategory.value = category.id;
  // Initialize per-item forms for Shopee
}

function resetStockOutForm() {
  stockOutForm.value = {
    destination_type: 'branch',
    destination_id: null,
    destination_branch_id: null,
    sub_category: '',
    receiver_name: '',
    transfer_notes: '',
    deletion_reason: '',
    retur_officer: '',
    retur_seal: '',
    retur_issue: '',
    customer_name: '',
    customer_phone: '',
    return_destination_id: null,
    proof_image: null,
    shopee_receiver: '',
    shopee_phone: '',
    shopee_address: '',
    shopee_province: '',
    shopee_city: '',
    shopee_district: '',
    shopee_village: '',
    shopee_postal_code: '',
    shopee_notes: '',
    shopee_tracking_no: '',
    selling_price: null,
    giveaway_receiver: '',
    giveaway_phone: '',
    giveaway_address: '',
    giveaway_province: '',
    giveaway_city: '',
    giveaway_district: '',
    giveaway_village: '',
    giveaway_postal_code: '',
    giveaway_notes: '',
    notes: '',
  };

  // Reset Region IDs
  selectedRegionIds.value = { province: "", city: "", district: "", village: "" };
  cities.value = [];
  districts.value = [];
  villages.value = [];
  proofImageFile.value = null;
  proofImagePreview.value = null;
  shopeeItemForms.value = [];
  scanningItemIndex.value = null;
}

// File upload for retur
function handleFileChange(event) {
  const file = event.target.files[0];
  if (file) {
    if (file.size > 10 * 1024 * 1024) {
      toast.error("Ukuran file maksimal 10MB");
      event.target.value = '';
      return;
    }
    proofImageFile.value = file;
    proofImagePreview.value = URL.createObjectURL(file);
  }
}

// Barcode Scanner
async function startScanner(itemIndex = null) {
  isScanning.value = true;
  scanningItemIndex.value = itemIndex;
  await new Promise(resolve => setTimeout(resolve, 100));

  try {
    html5QrCode = new Html5Qrcode(scannerContainerId);

    const config = {
      fps: 10,
      qrbox: { width: 300, height: 150 },
      formatsToSupport: [0, 4, 2, 11, 10],
      aspectRatio: 1.7777778,
    };

    await html5QrCode.start(
      { facingMode: "environment" },
      config,
      (decodedText) => {
        // Put result in the correct form
        if (scanningItemIndex.value !== null && shopeeItemForms.value[scanningItemIndex.value]) {
          shopeeItemForms.value[scanningItemIndex.value].tracking_no = decodedText;
        } else {
          stockOutForm.value.shopee_tracking_no = decodedText;
        }
        toast.success(`Barcode terdeteksi: ${decodedText}`);
        stopScanner();
      },
      () => { }
    );
  } catch (e) {
    console.error("Scanner error:", e);
    toast.error("Gagal akses kamera. Silakan ketik manual nomor resi.");
    stopScanner();
  }
}

async function stopScanner() {
  isScanning.value = false;
  scanningItemIndex.value = null;
  if (html5QrCode) {
    try {
      await html5QrCode.stop();
      html5QrCode.clear();
    } catch (e) {
      console.error("Error stopping scanner:", e);
    }
    html5QrCode = null;
  }
}

// Profit Calculation
const totalCostPrice = computed(() => {
  return selectedItems.value.reduce((sum, item) => sum + (Number(item.cost_price) || 0), 0);
});

const estimatedProfit = computed(() => {
  // If Shopee/Orderan Online, sum per-item selling prices
  if (selectedStockOutCategory.value === 'shopee' || selectedStockOutCategory.value === 'orderan_online') {
    const totalSelling = selectedItems.value.reduce((sum, item) => {
      const price = Number(item.selling_price) || 0;
      const qty = item.type === 'non-hp' ? (item.out_quantity || 1) : 1;
      return sum + (price * qty);
    }, 0);
    return totalSelling - totalCostPrice.value;
  }

  const selling = Number(stockOutForm.value.selling_price) || 0;
  return selling - totalCostPrice.value;
});

const profitPercentage = computed(() => {
  if (totalCostPrice.value === 0) return 0;
  return (estimatedProfit.value / totalCostPrice.value) * 100;
});



// Can Submit validation
const canSubmitStockOut = computed(() => {
  if (!selectedStockOutCategory.value || selectedItems.value.length === 0) return false;

  switch (selectedStockOutCategory.value) {
    case 'pindah_cabang':
      return stockOutForm.value.destination_type && stockOutForm.value.destination_id && stockOutForm.value.receiver_name;
    case 'kesalahan_input':
      return stockOutForm.value.deletion_reason.length >= 5;
    case 'retur':
      return stockOutForm.value.retur_officer && stockOutForm.value.retur_issue &&
        stockOutForm.value.customer_name && stockOutForm.value.customer_phone;
    case 'orderan_online': // Was shopee
      // Check if all items have selling price
      const allItemsHavePrice = selectedItems.value.every(item => item.selling_price && item.selling_price > 0);

      return stockOutForm.value.shopee_receiver &&
        // stockOutForm.value.shopee_phone && // Optional now
        stockOutForm.value.shopee_address &&
        stockOutForm.value.shopee_tracking_no &&
        allItemsHavePrice; // Per-item price validation
    case 'keluar':
      return stockOutForm.value.sub_category && stockOutForm.value.receiver_name;
    case 'giveaway':
      // Legacy check provided just in case, but category removed from list
      return true;
    case 'inventaris':
      return selectedInventoryUser.value !== null;
    default:
      return true;
  }
});

// Submit Stock Out
async function submitStockOut() {
  if (!canSubmitStockOut.value) return;

  isSubmitting.value = true;
  try {
    const formData = new FormData();
    formData.append('category', selectedStockOutCategory.value);

    // Split items by type
    const hpItems = selectedItems.value.filter(item => !item.type || item.type === 'hp');
    const nonHpItems = selectedItems.value.filter(item => item.type === 'non-hp');

    // Add Non-HP Items
    nonHpItems.forEach((item, index) => {
      formData.append(`non_hp_items[${index}][product_id]`, item.product_id || item.id); // Inventory item.id is usually inventory ID, but product_id is needed.
      // Wait, item from inventoryStore.products has product_id if it is inventory record AND id if it is inventory record.
      // For Non-HP, item structure: { id: 1, product_id: 2, quantity: 10, ... }
      formData.append(`non_hp_items[${index}][quantity]`, item.out_quantity || 1);

      if (selectedStockOutCategory.value === 'shopee' || selectedStockOutCategory.value === 'orderan_online') {
        formData.append(`non_hp_items[${index}][selling_price]`, item.selling_price || 0);
      }
    });

    // Add HP Items Product Detail IDs
    hpItems.forEach(item => {
      formData.append('product_detail_ids[]', item.id);
    });

    if (selectedStockOutCategory.value === 'pindah_cabang') {
      formData.append('destination_type', stockOutForm.value.destination_type);
      formData.append('destination_id', stockOutForm.value.destination_id);
    }

    if (selectedInventoryUser.value) {
      formData.append('inventory_user_id', selectedInventoryUser.value.id);
    }

    if (stockOutForm.value.sub_category) {
      formData.append('sub_category', stockOutForm.value.sub_category);
    }

    // For Shopee, send per-item data (constructed from global form)
    if (selectedStockOutCategory.value === 'orderan_online' || selectedStockOutCategory.value === 'shopee') {
      hpItems.forEach((item, index) => {
        formData.append(`shopee_items[${index}][product_detail_id]`, item.id);
        formData.append(`shopee_items[${index}][receiver]`, stockOutForm.value.shopee_receiver);
        formData.append(`shopee_items[${index}][phone]`, stockOutForm.value.shopee_phone);
        formData.append(`shopee_items[${index}][address]`, stockOutForm.value.shopee_address);
        formData.append(`shopee_items[${index}][notes]`, stockOutForm.value.shopee_notes);
        formData.append(`shopee_items[${index}][tracking_no]`, stockOutForm.value.shopee_tracking_no);
        formData.append(`shopee_items[${index}][selling_price]`, item.selling_price || 0);
      });

      // Append Global Region Data
      formData.append('shopee_receiver', stockOutForm.value.shopee_receiver);
      formData.append('shopee_phone', stockOutForm.value.shopee_phone);
      formData.append('shopee_address', stockOutForm.value.shopee_address);
      formData.append('shopee_province', stockOutForm.value.shopee_province);
      formData.append('shopee_city', stockOutForm.value.shopee_city);
      formData.append('shopee_district', stockOutForm.value.shopee_district);
      formData.append('shopee_village', stockOutForm.value.shopee_village);
      formData.append('shopee_postal_code', stockOutForm.value.shopee_postal_code);
      formData.append('shopee_tracking_no', stockOutForm.value.shopee_tracking_no);
      // Ensure notes are sent to the transaction level
      formData.append('notes', stockOutForm.value.shopee_notes);
      // formData.append('selling_price', stockOutForm.value.selling_price); // Not used anymore for Shopee
    } else if (selectedStockOutCategory.value === 'keluar') {
      formData.append('notes', stockOutForm.value.notes);
      // Sub category already appended above
    } else if (selectedStockOutCategory.value === 'giveaway') {
      formData.append('giveaway_receiver', stockOutForm.value.giveaway_receiver);
      formData.append('giveaway_phone', stockOutForm.value.giveaway_phone);
      formData.append('giveaway_address', stockOutForm.value.giveaway_address);
      formData.append('giveaway_province', stockOutForm.value.giveaway_province);
      formData.append('giveaway_city', stockOutForm.value.giveaway_city);
      formData.append('giveaway_district', stockOutForm.value.giveaway_district);
      formData.append('giveaway_village', stockOutForm.value.giveaway_village);
      formData.append('giveaway_postal_code', stockOutForm.value.giveaway_postal_code);
      formData.append('giveaway_notes', stockOutForm.value.giveaway_notes);
    } else {
      Object.keys(stockOutForm.value).forEach(key => {
        if (key !== 'proof_image' && stockOutForm.value[key] !== null && stockOutForm.value[key] !== '') {
          formData.append(key, stockOutForm.value[key]);
        }
      });
    }

    if (selectedStockOutCategory.value === 'retur' && proofImageFile.value) {
      formData.append('proof_image', proofImageFile.value);
    }

    const response = await api.post('/stock-outs', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    });

    toast.success(`Stok berhasil dikeluarkan! ID: ${response.data.data.receipt_id}`);
    selectedItems.value = [];
    closeStockOutModal();
    inventoryStore.fetchProducts();

  } catch (e) {
    toast.error(e.response?.data?.message || "Gagal keluar stok");
    console.error(e);
  } finally {
    isSubmitting.value = false;
  }
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

    <!-- Stock Out Modal -->
    <div v-if="showStockOutModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
      <div
        class="bg-surface-0 dark:bg-surface-800 rounded-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col animate-in zoom-in duration-200 border border-surface-200 dark:border-surface-700 shadow-xl">
        <!-- Modal Header -->
        <div class="flex items-center justify-between p-6 border-b border-surface-200 dark:border-surface-700">
          <div class="flex items-center gap-3">
            <button v-if="selectedStockOutCategory" @click="selectedStockOutCategory = null"
              class="text-text-secondary hover:text-text-primary transition-colors">
              <ChevronLeft :size="20" />
            </button>
            <button v-else-if="selectedInventoryUser" @click="selectedInventoryUser = null"
              class="text-text-secondary hover:text-text-primary transition-colors">
              <ChevronLeft :size="20" />
            </button>
            <h2 class="text-xl font-bold text-text-primary">
              {{selectedStockOutCategory ? stockOutCategories.find(c => c.id === selectedStockOutCategory)?.name :
                'Pilih Kategori'}}
            </h2>
          </div>
          <button @click="closeStockOutModal" class="text-text-secondary hover:text-text-primary transition-colors">
            <X :size="24" />
          </button>
        </div>

        <!-- Modal Body -->
        <div class="flex-1 overflow-y-auto p-6 bg-surface-50 dark:bg-surface-900/50">
          <!-- STEP 1: SELECT INVENTORY ACCOUNT -->
          <div v-if="!selectedInventoryUser" class="animate-in slide-in-from-right">
            <h3 class="text-lg font-bold text-text-primary mb-4 flex items-center gap-2">
              <UserCheck :size="20" class="text-emerald-500" />
              Pilih User Inventory
            </h3>

            <div v-if="isLoadingUsers" class="flex justify-center py-12">
              <Loader2 :size="32" class="animate-spin text-primary-500" />
            </div>

            <div v-else-if="inventoryUsers.length === 0" class="text-center py-8 text-text-secondary">
              <div class="bg-surface-100 dark:bg-surface-800 p-6 rounded-2xl inline-block mb-3">
                <UserCheck :size="32" class="text-surface-500" />
              </div>
              <p>Tidak ada akun inventory ditemukan.</p>
            </div>

            <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div v-for="user in inventoryUsers" :key="user.id" @click="selectedInventoryUser = user"
                class="p-4 rounded-2xl border border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800 cursor-pointer hover:border-primary-500 hover:bg-surface-50 dark:hover:bg-surface-700/80 transition-all relative group shadow-sm hover:shadow-md">

                <div class="flex items-center gap-4">
                  <!-- Photo -->
                  <div
                    class="w-14 h-14 rounded-xl bg-surface-100 dark:bg-surface-900 shrink-0 flex items-center justify-center overflow-hidden border border-surface-200 dark:border-surface-600 group-hover:border-primary-500/50 transition-colors">
                    <img v-if="user.photo_inventory" :src="`${storageUrl}/storage/${user.photo_inventory}`"
                      class="w-full h-full object-cover" alt="Foto" />
                    <span v-else class="text-xl font-bold text-primary-500">{{ (user.full_name || user.name ||
                      '?')[0].toUpperCase() }}</span>
                  </div>

                  <!-- Details -->
                  <div class="flex-1 min-w-0">
                    <h4 class="font-bold text-text-primary truncate text-base mb-0.5">{{ user.full_name || user.name }}
                    </h4>
                    <div class="flex flex-col gap-0.5">
                      <span class="text-xs text-text-secondary uppercase tracking-wider font-medium">{{
                        user.roles?.[0]?.name || 'INVENTORY' }}</span>
                      <span v-if="user.phone"
                        class="text-[10px] text-emerald-500 dark:text-emerald-400 font-mono flex items-center gap-1">
                        <Smartphone :size="10" /> {{ user.phone }}
                      </span>
                    </div>
                  </div>

                  <!-- Chevron -->
                  <div class="text-surface-400 group-hover:text-primary-500 transition-colors">
                    <ChevronRight :size="20" />
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- STEP 2: CATEGORY SELECTION -->
          <div v-else-if="!selectedStockOutCategory" class="animate-in slide-in-from-right">
            <!-- Selected User Header -->
            <div
              class="flex items-center justify-between mb-6 bg-surface-100 dark:bg-surface-700/30 p-3 rounded-xl border border-surface-200 dark:border-surface-600">
              <div class="flex items-center gap-3">
                <div
                  class="w-10 h-10 rounded-full bg-surface-200 dark:bg-surface-600 flex items-center justify-center overflow-hidden border border-surface-300 dark:border-surface-500">
                  <img v-if="selectedInventoryUser.photo_inventory"
                    :src="`${storageUrl}/storage/${selectedInventoryUser.photo_inventory}`"
                    class="w-full h-full object-cover" />
                  <User v-else :size="16" class="text-text-secondary" />
                </div>
                <div>
                  <p class="text-xs text-text-secondary uppercase">Akun Inventory</p>
                  <p class="font-bold text-text-primary text-sm">{{ selectedInventoryUser.full_name ||
                    selectedInventoryUser.name }}</p>
                </div>
              </div>
              <button @click="selectedInventoryUser = null"
                class="text-xs text-primary-500 hover:text-primary-600 dark:text-primary-400 font-medium">
                Ganti Akun
              </button>
            </div>

            <h3 class="text-lg font-bold text-text-primary mb-4 flex items-center gap-2">
              <Box :size="20" class="text-primary-500" />
              Pilih Kategori Pengeluaran
            </h3>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
              <button v-for="category in availableStockOutCategories" :key="category.id"
                @click="selectStockOutCategory(category)"
                class="flex flex-col items-center justify-center p-6 rounded-2xl border border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800 hover:bg-surface-50 dark:hover:bg-surface-700 hover:border-primary-500/50 transition-all group gap-3 text-center h-32 md:h-40 shadow-sm hover:shadow-md">
                <div
                  :class="`p-3 rounded-full bg-${category.color}-500/10 text-${category.color}-500 group-hover:scale-110 transition-transform`">
                  <component :is="category.icon" :size="28" />
                </div>
                <span class="font-medium text-sm md:text-base text-text-primary transition-colors">
                  {{ category.name }}
                </span>
              </button>
            </div>
          </div>

          <!-- STEP 3: FORMS -->
          <div v-else class="space-y-4">
            <!-- Pindah Cabang Form -->
            <template v-if="selectedStockOutCategory === 'pindah_cabang'">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="label">Tipe Tujuan *</label>
                  <select v-model="stockOutForm.destination_type" class="input">
                    <option value="branch">Cabang / Store</option>
                    <option value="warehouse">Gudang</option>
                    <option value="online_shop">Online Shop</option>
                    <option value="distributor">Distributor</option>
                  </select>
                </div>
                <div>
                  <label class="label">Tujuan *</label>
                  <select v-model="stockOutForm.destination_id" class="input"
                    :disabled="!stockOutForm.destination_type">
                    <option :value="null">-- Pilih Tujuan --</option>
                    <template v-if="stockOutForm.destination_type === 'branch'">
                      <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </template>
                    <template v-if="stockOutForm.destination_type === 'warehouse'">
                      <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                    </template>
                    <template v-if="stockOutForm.destination_type === 'online_shop'">
                      <option v-for="o in onlineShops" :key="o.id" :value="o.id">{{ o.name }} ({{ o.platform }})
                      </option>
                    </template>
                    <template v-if="stockOutForm.destination_type === 'distributor'">
                      <option v-for="d in distributors" :key="d.id" :value="d.id">{{ d.name }}</option>
                    </template>
                  </select>
                </div>
              </div>

              <!-- Items Summary for Pindah Cabang -->
              <div class="bg-surface-700/30 p-4 rounded-xl border border-surface-600 mb-4">
                <p class="text-xs uppercase font-bold text-text-secondary mb-3">
                  Item yang dikirim ({{ selectedItems.length }})
                </p>

                <div class="space-y-3 max-h-80 overflow-y-auto pr-1">
                  <div v-for="(item, idx) in selectedItems" :key="item.id + (item.type || '')"
                    class="bg-surface-800 p-3 rounded-xl border border-surface-600 space-y-3">

                    <!-- Header -->
                    <div class="flex items-center justify-between">
                      <div class="flex items-center gap-2">
                        <span class="text-primary-400 font-bold text-xs">{{ idx + 1 }}.</span>
                        <div>
                          <p class="font-medium text-sm text-white">{{ item.product?.name }}</p>
                          <p class="text-[10px] text-text-secondary">{{ item.product?.brand }} {{ item.product?.type
                          }}</p>
                        </div>
                      </div>
                      <span v-if="item.type !== 'non-hp'"
                        class="text-xs font-mono bg-surface-700 px-2 py-0.5 rounded text-text-secondary">
                        {{ item.imei }}
                      </span>
                    </div>

                    <!-- Quantity for Non-HP -->
                    <div v-if="item.type === 'non-hp'" class="w-full md:w-1/2">
                      <label class="text-[10px] text-text-secondary block mb-1">Qty Kirim</label>
                      <div class="flex items-center gap-2">
                        <input type="number" v-model="item.out_quantity"
                          class="w-full text-sm p-2 rounded-lg bg-surface-900 border border-surface-600 text-center"
                          min="1" :max="item.quantity" placeholder="1">
                        <span class="text-xs text-text-secondary">/{{ item.quantity }} Pcs</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div>
                <label class="label">Nama Penerima *</label>
                <input v-model="stockOutForm.receiver_name" class="input" placeholder="Nama yang menerima barang" />
              </div>
              <div>
                <label class="label">Catatan</label>
                <textarea v-model="stockOutForm.transfer_notes" class="input" rows="3"
                  placeholder="Catatan tambahan..."></textarea>
              </div>
            </template>

            <!-- Kesalahan Input Form -->
            <template v-if="selectedStockOutCategory === 'kesalahan_input'">
              <!-- Item List (Added for Quantity Input) -->
              <div class="bg-surface-700/30 p-4 rounded-xl border border-surface-600 mb-4">
                <p class="text-xs uppercase font-bold text-text-secondary mb-3">
                  Item yang dihapus ({{ selectedItems.length }})
                </p>

                <div class="space-y-3 max-h-80 overflow-y-auto pr-1">
                  <div v-for="(item, idx) in selectedItems" :key="item.id + (item.type || '')"
                    class="bg-surface-800 p-3 rounded-xl border border-surface-600 space-y-3">

                    <!-- Header -->
                    <div class="flex items-center justify-between">
                      <div class="flex items-center gap-2">
                        <span class="text-primary-400 font-bold text-xs">{{ idx + 1 }}.</span>
                        <div>
                          <p class="font-medium text-sm text-white">{{ item.product?.name }}</p>
                          <p class="text-[10px] text-text-secondary">{{ item.product?.brand }} {{ item.product?.type
                          }}</p>
                        </div>
                      </div>
                      <span v-if="item.type !== 'non-hp'"
                        class="text-xs font-mono bg-surface-700 px-2 py-0.5 rounded text-text-secondary">
                        {{ item.imei }}
                      </span>
                    </div>

                    <!-- Quantity for Non-HP -->
                    <div v-if="item.type === 'non-hp'" class="w-full md:w-1/2">
                      <label class="text-[10px] text-text-secondary block mb-1">Qty Hapus</label>
                      <div class="flex items-center gap-2">
                        <input type="number" v-model="item.out_quantity"
                          class="w-full text-sm p-2 rounded-lg bg-surface-900 border border-surface-600 text-center"
                          min="1" :max="item.quantity" placeholder="1">
                        <span class="text-xs text-text-secondary">/{{ item.quantity }} Pcs</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div>
                <label class="label">Alasan Hapus *</label>
                <textarea v-model="stockOutForm.deletion_reason" class="input" rows="4"
                  placeholder="Jelaskan alasan penghapusan data..."></textarea>
              </div>
            </template>

            <!-- Retur Form -->
            <template v-if="selectedStockOutCategory === 'retur'">
              <!-- Item List (Added for Quantity Input) -->
              <div class="bg-surface-700/30 p-4 rounded-xl border border-surface-600 mb-4">
                <p class="text-xs uppercase font-bold text-text-secondary mb-3">
                  Item yang diretur ({{ selectedItems.length }})
                </p>

                <div class="space-y-3 max-h-80 overflow-y-auto pr-1">
                  <div v-for="(item, idx) in selectedItems" :key="item.id + (item.type || '')"
                    class="bg-surface-800 p-3 rounded-xl border border-surface-600 space-y-3">

                    <!-- Header -->
                    <div class="flex items-center justify-between">
                      <div class="flex items-center gap-2">
                        <span class="text-primary-400 font-bold text-xs">{{ idx + 1 }}.</span>
                        <div>
                          <p class="font-medium text-sm text-white">{{ item.product?.name }}</p>
                          <p class="text-[10px] text-text-secondary">{{ item.product?.brand }} {{ item.product?.type
                          }}</p>
                        </div>
                      </div>
                      <span v-if="item.type !== 'non-hp'"
                        class="text-xs font-mono bg-surface-700 px-2 py-0.5 rounded text-text-secondary">
                        {{ item.imei }}
                      </span>
                    </div>

                    <!-- Quantity for Non-HP -->
                    <div v-if="item.type === 'non-hp'" class="w-full md:w-1/2">
                      <label class="text-[10px] text-text-secondary block mb-1">Qty Retur</label>
                      <div class="flex items-center gap-2">
                        <input type="number" v-model="item.out_quantity"
                          class="w-full text-sm p-2 rounded-lg bg-surface-900 border border-surface-600 text-center"
                          min="1" :max="item.quantity" placeholder="1">
                        <span class="text-xs text-text-secondary">/{{ item.quantity }} Pcs</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="label">Nama Petugas *</label>
                  <input v-model="stockOutForm.retur_officer" class="input" placeholder="Nama petugas retur" />
                </div>
                <div>
                  <label class="label">Pilih Gudang *</label>
                  <select v-model="stockOutForm.return_destination_id" class="input">
                    <option :value="null">-- Pilih Gudang Retur --</option>
                    <option v-for="w in warehouses.filter(w => w.can_accept_returns)" :key="w.id" :value="w.id">{{
                      w.name }}</option>
                  </select>
                </div>
              </div>
              <div>
                <label class="label">Foto Bukti / Kondisi (Max 10MB)</label>
                <input type="file" accept="image/*" @change="handleFileChange"
                  class="w-full text-sm text-text-secondary file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-bold file:bg-surface-700 file:text-primary-400 hover:file:bg-surface-600 transition-all cursor-pointer border border-surface-700 rounded-xl bg-surface-800" />
                <div v-if="proofImagePreview" class="mt-3">
                  <img :src="proofImagePreview" class="h-24 rounded-xl object-cover border border-surface-600" />
                </div>
              </div>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="label">Segel</label>
                  <input v-model="stockOutForm.retur_seal" class="input" placeholder="Nomor segel (opsional)" />
                </div>
                <div>
                  <label class="label">Nama Customer *</label>
                  <input v-model="stockOutForm.customer_name" class="input" placeholder="Nama customer" />
                </div>
              </div>
              <div>
                <label class="label">Kendala / Masalah *</label>
                <textarea v-model="stockOutForm.retur_issue" class="input" rows="3"
                  placeholder="Jelaskan kendala atau masalah..."></textarea>
              </div>
              <div>
                <label class="label">No. WA Customer *</label>
                <input v-model="stockOutForm.customer_phone" class="input" placeholder="08xxxxxxxxxx" />
              </div>
            </template>

            <!-- Shopee / Orderan Online Form -->
            <template v-if="selectedStockOutCategory === 'shopee' || selectedStockOutCategory === 'orderan_online'">
              <div class="space-y-4">
                <div class="bg-surface-700/30 p-4 rounded-xl border border-surface-600">
                  <p class="text-xs uppercase font-bold text-text-secondary mb-3">
                    Item yang dikirim ({{ selectedItems.length }})
                  </p>

                  <!-- Modal Product Searcher -->
                  <div class="mb-4 relative">
                    <div class="relative">
                      <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary" :size="16" />
                      <input v-model="modalSearchQuery" @input="searchInModal" type="text"
                        placeholder="Tambah produk lain (Ketik SKU/Nama)..."
                        class="w-full text-sm pl-10 pr-4 py-2 rounded-xl bg-surface-800 border border-surface-600 focus:border-primary-500 transition-colors" />
                      <div v-if="isSearchingInModal" class="absolute right-3 top-1/2 -translate-y-1/2">
                        <Loader2 :size="14" class="animate-spin text-primary-500" />
                      </div>
                    </div>

                    <!-- Search Results -->
                    <div v-if="modalSearchResults.length > 0"
                      class="absolute z-50 left-0 right-0 mt-1 bg-surface-800 border border-surface-600 rounded-xl shadow-2xl overflow-hidden max-h-60 overflow-y-auto">
                      <div v-for="res in modalSearchResults" :key="res.id" @click="addItemFromModal(res)"
                        class="p-3 hover:bg-surface-700 cursor-pointer border-b border-surface-700 last:border-0 flex items-center justify-between group">
                        <div class="flex items-center gap-3">
                          <div class="w-8 h-8 rounded bg-surface-900 flex items-center justify-center">
                            <Smartphone v-if="res.product?.type === 'hp'" :size="14" class="text-text-secondary" />
                            <Box v-else :size="14" class="text-text-secondary" />
                          </div>
                          <div>
                            <p class="text-xs font-bold text-white">{{ res.product?.name }}</p>
                            <p class="text-[10px] text-text-secondary">{{ res.imei || res.product?.sku }}</p>
                          </div>
                        </div>
                        <Plus :size="14"
                          class="text-primary-500 opacity-0 group-hover:opacity-100 transition-opacity" />
                      </div>
                    </div>
                  </div>

                  <div class="space-y-3 max-h-80 overflow-y-auto pr-1">
                    <div v-for="(item, idx) in selectedItems" :key="item.id + (item.type || '')"
                      class="bg-surface-800 p-3 rounded-xl border border-surface-600 space-y-3">

                      <!-- Item Header -->
                      <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                          <span class="text-primary-400 font-bold text-xs">{{ idx + 1 }}.</span>
                          <div>
                            <p class="font-medium text-sm text-white">{{ item.product?.name }}</p>
                            <p class="text-[10px] text-text-secondary">{{ item.product?.brand }} {{
                              item.product?.type
                            }}</p>
                          </div>
                        </div>
                        <span v-if="item.type !== 'non-hp'"
                          class="text-xs font-mono bg-surface-700 px-2 py-0.5 rounded text-text-secondary">{{
                            item.imei }}</span>
                      </div>

                      <div class="flex gap-3">
                        <!-- Quantity for Non-HP -->
                        <div v-if="item.type === 'non-hp'" class="w-1/3">
                          <label class="text-[10px] text-text-secondary block mb-1">Qty</label>
                          <div class="flex items-center gap-2">
                            <input type="number" v-model="item.out_quantity"
                              class="w-full text-sm p-2 rounded-lg bg-surface-900 border border-surface-600 text-center"
                              min="1" :max="item.quantity">
                            <span class="text-xs text-text-secondary">/{{ item.quantity }}</span>
                          </div>
                        </div>

                        <!-- SRP for Everyone -->
                        <div class="flex-1">
                          <label class="text-[10px] text-emerald-500 block mb-1">SRP (Per Item)</label>
                          <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary text-sm">Rp</span>
                            <input type="text" :value="item.selling_price ? formatNumber(item.selling_price) : ''"
                              @input="e => {
                                const val = parseCurrency(e.target.value);
                                item.selling_price = val;
                                e.target.value = formatNumber(val);
                              }"
                              class="w-full text-sm p-2 pl-9 rounded-lg bg-surface-900 border border-surface-600 focus:outline-none focus:border-emerald-500 transition-colors"
                              placeholder="0">
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="label">Nama Penerima *</label>
                    <input v-model="stockOutForm.shopee_receiver" class="input" placeholder="Nama penerima" />
                  </div>
                  <div>
                    <label class="label">No. WA (Opsional)</label>
                    <input v-model="stockOutForm.shopee_phone" class="input" placeholder="08xxxxxxxxxx" />
                  </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="label">Provinsi *</label>
                    <select :value="selectedRegionIds.province" @change="e => onProvinceChange(e.target.value)"
                      class="input">
                      <option value="">-- Pilih Provinsi --</option>
                      <option v-for="p in provinces" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                  </div>
                  <div>
                    <label class="label">Kota/Kabupaten *</label>
                    <select :value="selectedRegionIds.city" @change="e => onCityChange(e.target.value)" class="input"
                      :disabled="!selectedRegionIds.province">
                      <option value="">-- Pilih Kota --</option>
                      <option v-for="c in cities" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                  </div>
                </div>

                <div>
                  <label class="label">Detail Alamat (Jalan, No. Rumah, RT/RW) *</label>
                  <textarea v-model="stockOutForm.shopee_address" class="input" rows="3"
                    placeholder="Nama Jalan, No. Rumah, RT/RW..."></textarea>
                </div>

                <div>
                  <label class="label">Catatan</label>
                  <input v-model="stockOutForm.shopee_notes" class="input" placeholder="Catatan pengiriman..." />
                </div>

                <div>
                  <label class="label">No. Resi *</label>
                  <div class="flex gap-2">
                    <input v-model="stockOutForm.shopee_tracking_no" class="input font-mono"
                      placeholder="Scan atau ketik manual..." />
                    <button @click="startScanner()" type="button" class="btn btn-secondary px-4">
                      <ScanBarcode :size="18" />
                    </button>
                  </div>
                  <p class="text-xs text-text-secondary mt-1">* Satu resi untuk semua item di atas</p>
                </div>
              </div>
            </template>

            <!-- Keluar Form -->
            <template v-if="selectedStockOutCategory === 'keluar'">
              <div class="space-y-4">
                <!-- Item List -->
                <div class="bg-surface-700/30 p-4 rounded-xl border border-surface-600">
                  <p class="text-xs uppercase font-bold text-text-secondary mb-3">
                    Item yang dikeluarkan ({{ selectedItems.length }})
                  </p>

                  <div class="space-y-3 max-h-80 overflow-y-auto pr-1">
                    <div v-for="(item, idx) in selectedItems" :key="item.id + (item.type || '')"
                      class="bg-surface-800 p-3 rounded-xl border border-surface-600 space-y-3">

                      <!-- Header -->
                      <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                          <span class="text-primary-400 font-bold text-xs">{{ idx + 1 }}.</span>
                          <div>
                            <p class="font-medium text-sm text-white">{{ item.product?.name }}</p>
                            <p class="text-[10px] text-text-secondary">{{ item.product?.brand }} {{ item.product?.type
                            }}</p>
                          </div>
                        </div>
                        <span v-if="item.type !== 'non-hp'"
                          class="text-xs font-mono bg-surface-700 px-2 py-0.5 rounded text-text-secondary">
                          {{ item.imei }}
                        </span>
                      </div>

                      <!-- Quantity for Non-HP -->
                      <div v-if="item.type === 'non-hp'" class="w-full md:w-1/2">
                        <label class="text-[10px] text-text-secondary block mb-1">Qty Keluar</label>
                        <div class="flex items-center gap-2">
                          <input type="number" v-model="item.out_quantity"
                            class="w-full text-sm p-2 rounded-lg bg-surface-900 border border-surface-600 text-center"
                            min="1" :max="item.quantity" placeholder="1">
                          <span class="text-xs text-text-secondary">/{{ item.quantity }} Pcs</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <div>
                  <label class="label">Sub Kategori *</label>
                  <select v-model="stockOutForm.sub_category" class="input">
                    <option value="">-- Pilih Sub Kategori --</option>
                    <option value="Giveaway customer">Giveaway customer</option>
                    <option value="Hadiah">Hadiah</option>
                    <option value="Brand ambassador">Brand ambassador</option>
                    <option value="Event / Sponsorship">Event / Sponsorship</option>
                    <option value="Promo">Promo</option>
                    <option value="Inventaris">Inventaris</option>
                  </select>
                </div>

                <div>
                  <label class="label">Nama Penerima *</label>
                  <input v-model="stockOutForm.receiver_name" class="input" placeholder="Nama penerima" />
                </div>

                <div>
                  <label class="label">No. WA (Opsional)</label>
                  <input v-model="stockOutForm.customer_phone" class="input" placeholder="08xxxxxxxxxx" />
                </div>

                <div>
                  <label class="label">Catatan</label>
                  <textarea v-model="stockOutForm.notes" class="input" rows="2"
                    placeholder="Catatan tambahan..."></textarea>
                </div>
              </div>
            </template>

            <!-- Giveaway Form -->
            <template v-if="selectedStockOutCategory === 'giveaway'">
              <div class="space-y-4">
                <div class="bg-surface-700/30 p-4 rounded-xl border border-surface-600">
                  <p class="text-xs uppercase font-bold text-text-secondary mb-2">
                    Item Giveaway ({{ selectedItems.length }})
                  </p>
                  <div class="flex flex-wrap gap-2 max-h-32 overflow-y-auto">
                    <div v-for="(item, idx) in selectedItems" :key="item.id"
                      class="bg-surface-800 px-3 py-1.5 rounded-lg text-xs font-mono flex items-center gap-2 border border-surface-600">
                      <span class="text-primary-400 font-bold">{{ idx + 1 }}.</span>
                      <span>{{ item.product?.name }}</span>
                      <span class="text-text-secondary">|</span>
                      <span v-if="activeTab === 'hp'">{{ item.imei }}</span>
                      <div v-else class="flex items-center gap-1">
                        <input type="number" v-model="item.out_quantity"
                          class="w-12 h-6 text-xs p-1 rounded bg-surface-900 border border-surface-600 text-center"
                          min="1" :max="item.quantity">
                        <span class="text-text-secondary">/ {{ item.quantity }}</span>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="label">Nama Customer *</label>
                    <input v-model="stockOutForm.giveaway_receiver" class="input" placeholder="Nama customer" />
                  </div>
                  <div>
                    <label class="label">No. WA *</label>
                    <input v-model="stockOutForm.giveaway_phone" class="input" placeholder="08xxxxxxxxxx" />
                  </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="label">Provinsi *</label>
                    <select :value="selectedRegionIds.province" @change="e => onProvinceChange(e.target.value)"
                      class="input">
                      <option value="">-- Pilih Provinsi --</option>
                      <option v-for="p in provinces" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                  </div>
                  <div>
                    <label class="label">Kota/Kabupaten *</label>
                    <select :value="selectedRegionIds.city" @change="e => onCityChange(e.target.value)" class="input"
                      :disabled="!selectedRegionIds.province">
                      <option value="">-- Pilih Kota --</option>
                      <option v-for="c in cities" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                  </div>
                </div>

                <!-- District & Village Removed -->

                <div>
                  <label class="label">Kode Pos</label>
                  <input v-model="stockOutForm.giveaway_postal_code" class="input" placeholder="Kode Pos" />
                </div>

                <div>
                  <label class="label">Detail Alamat (Jalan, No. Rumah, RT/RW) *</label>
                  <textarea v-model="stockOutForm.giveaway_address" class="input" rows="3"
                    placeholder="Nama Jalan, No. Rumah, RT/RW, Kecamatan, Kelurahan..."></textarea>
                </div>

                <div>
                  <label class="label">Catatan</label>
                  <input v-model="stockOutForm.giveaway_notes" class="input" placeholder="Catatan giveaway..." />
                </div>
              </div>
            </template>

            <!-- Selected Items Summary -->
            <div class="mt-6 pt-4 border-t border-surface-700">
              <p class="text-xs uppercase font-bold text-text-secondary mb-3">
                Barang yang akan dikeluarkan ({{ selectedItems.length }})
              </p>
              <div class="flex flex-wrap gap-2 max-h-32 overflow-y-auto">
                <div v-for="item in selectedItems" :key="item.id"
                  class="bg-surface-700 px-3 py-2 rounded-xl text-sm flex items-center gap-2">
                  <Smartphone :size="14" />
                  <span class="font-mono text-xs">{{ item.imei }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>


        <div v-if="selectedStockOutCategory" class="p-6 border-t border-surface-700">
          <button @click="submitStockOut" :disabled="!canSubmitStockOut || isSubmitting"
            class="btn btn-primary w-full h-12 rounded-xl font-bold disabled:opacity-30">
            <Loader2 v-if="isSubmitting" :size="20" class="animate-spin mr-2" />
            {{ isSubmitting ? 'Memproses...' : 'Konfirmasi Keluar Stok' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Scanner Modal -->
    <div v-if="isScanning" class="fixed inset-0 bg-black/95 z-60 flex flex-col items-center justify-center p-4">
      <div class="relative w-full max-w-lg bg-surface-800 rounded-2xl overflow-hidden">
        <div class="flex items-center justify-between p-4 border-b border-surface-700">
          <h3 class="text-white font-bold flex items-center gap-2">
            <ScanBarcode :size="20" class="text-orange-500" />
            Scan Barcode Resi
          </h3>
          <button @click="stopScanner" class="text-text-secondary hover:text-white transition-colors">
            <X :size="24" />
          </button>
        </div>
        <div :id="scannerContainerId" class="w-full aspect-video bg-black"></div>
        <div class="p-4 text-center space-y-3">
          <p class="text-text-secondary text-sm animate-pulse">Arahkan kamera ke barcode resi...</p>
          <div class="text-xs text-text-secondary">
            <p>Atau ketik manual nomor resi di form lalu tutup scanner</p>
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
