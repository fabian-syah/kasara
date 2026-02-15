<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from "vue";
import { useRouter } from "vue-router";
import { useToast } from "../../composables/useToast";
import { inventory as inventoryApi, branches as branchesApi, warehouses as warehousesApi, onlineShops as onlineShopsApi, distributors as distributorsApi, products as productsApi } from "../../api/axios";
import api from "../../api/axios";
import { Html5Qrcode } from "html5-qrcode";
import {
    Package,
    ArrowRightFromLine,
    Building2,
    AlertTriangle,
    RotateCcw,
    ShoppingBag,
    CheckCircle2,
    Loader2,
    ScanBarcode,
    ChevronLeft,
    Smartphone,
    X,
    Search,
    Gift,
    Trophy,
    UserCheck,
    Calendar,
    Percent,
    Archive,
    Upload,
    Warehouse
} from "lucide-vue-next";
import { useAuthStore } from "../../store/auth";

const toast = useToast();
const router = useRouter();
const authStore = useAuthStore();

// State
const isLoading = ref(false);
const isSubmitting = ref(false);
const showForm = ref(false);
const searchQuery = ref("");
const inventoryItems = ref([]);
const selectedItems = ref([]);
const branches = ref([]);
const warehouses = ref([]);
const onlineShops = ref([]);
const distributors = ref([]);
const currentBranch = ref(null);

// Non-HP State
const nonHpInventory = ref([]);
const selectedNonHpItems = ref([]); // [{ product_id, quantity, product, selling_price }]
const showNonHpModal = ref(false);
const newNonHpItem = ref({ product_id: null, quantity: 1, selling_price: 0 });

// Categories
const allCategories = [
    { id: 'pindah_cabang', name: 'Pindah Cabang', icon: Building2, color: 'blue' },
    { id: 'kesalahan_input', name: 'Kesalahan Input', icon: AlertTriangle, color: 'amber' },
    { id: 'retur', name: 'Retur', icon: RotateCcw, color: 'purple' },
    { id: 'shopee', name: 'Shopee', icon: ShoppingBag, color: 'orange' },
    { id: 'giveaway', name: 'Giveaway Customer', icon: Gift, color: 'pink' },
    { id: 'hadiah', name: 'Hadiah', icon: Trophy, color: 'yellow' },
    { id: 'brand_ambassador', name: 'Brand Ambassador', icon: UserCheck, color: 'indigo' },
    { id: 'event', name: 'Event / Sponsorship', icon: Calendar, color: 'cyan' },
    { id: 'promo', name: 'Promo', icon: Percent, color: 'red' },
    { id: 'inventaris', name: 'Inventaris', icon: Archive, color: 'slate' },
];

const categories = computed(() => {
    return allCategories;
});

const selectedCategory = ref(null);
const showReturnBlockedAlert = ref(false);

function selectCategory(category) {
    if (category.id === 'retur') {
        if (warehouses.value.length > 0) {
            if (!warehouses.value[0].can_accept_returns) {
                showReturnBlockedAlert.value = true;
                return;
            }
        }
    }
    selectedCategory.value = category.id;
}

// Form Fields
const form = ref({
    destination_type: 'branch', // Default branch
    destination_branch_id: null, // Legacy/Specific
    destination_id: null, // Polymorphic ID
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
    shopee_province: '',
    shopee_city: '',
    shopee_district: '',
    shopee_village: '',
    shopee_postal_code: '',
    shopee_address: '',
    shopee_notes: '',
    shopee_tracking_no: '',
    selling_price: null,
    notes: '',
});

// Region State
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

// Barcode Scanner State
const isScanning = ref(false);
const scannerContainerId = 'barcode-scanner-container';
let html5QrCode = null;

async function fetchInventory() {
    isLoading.value = true;
    try {
        const response = await inventoryApi.list({ status: 'available' });
        inventoryItems.value = response.data.data || response.data;
    } catch (e) {
        toast.error("Gagal memuat data inventory");
    } finally {
        isLoading.value = false;
    }
}

async function fetchBranches() {
    try {
        const response = await branchesApi.list();
        branches.value = response.data.data || response.data;
    } catch (e) {
        console.error("Gagal memuat cabang", e);
    }
}

async function fetchWarehouses() {
    try {
        const response = await warehousesApi.list();
        warehouses.value = response.data.data || response.data;
    } catch (e) {
        console.error("Gagal memuat gudang", e);
    }
}

async function fetchOnlineShops() {
    try {
        const response = await onlineShopsApi.list();
        onlineShops.value = response.data.data || response.data;
    } catch (e) {
        console.error("Gagal memuat online shop", e);
    }
}

async function fetchDistributors() {
    try {
        const response = await distributorsApi.list();
        distributors.value = response.data.data || response.data;
    } catch (e) {
        console.error("Gagal memuat distributor", e);
    }
}

async function fetchNonHpInventory() {
    try {
        const response = await inventoryApi.list({ type: 'non-hp', status: 'available' });
        nonHpInventory.value = response.data.data || response.data;
    } catch (e) {
        console.error("Gagal memuat inventory non-hp", e);
    }
}

// Watchers
watch(() => form.value.destination_type, (newType) => {
    form.value.destination_id = null;
    if (newType === 'branch') fetchBranches(); // Already fetched usually
    if (newType === 'warehouse') fetchWarehouses();
    if (newType === 'online_shop') fetchOnlineShops();
    if (newType === 'distributor') fetchDistributors();
});

watch(selectedCategory, (newCat) => {
    if (newCat === 'pindah_cabang') {
        // Ensure data is loaded
        if (form.value.destination_type === 'branch' && branches.value.length === 0) fetchBranches();
    }
    // Load Non-HP inventory when form opens
    if (newCat) {
        fetchNonHpInventory();
    }
});

function addNonHpItem() {
    if (!newNonHpItem.value.product_id) return;

    const product = nonHpInventory.value.find(p => p.product_id === newNonHpItem.value.product_id);
    if (!product) return;

    // Check if enough stock
    if (newNonHpItem.value.quantity > product.quantity) {
        toast.error(`Stok tidak cukup via ${product.placement_type}. Tersedia: ${product.quantity}`);
        return;
    }

    // Check if already added
    const existing = selectedNonHpItems.value.find(i => i.product_id === newNonHpItem.value.product_id);
    if (existing) {
        if (existing.quantity + newNonHpItem.value.quantity > product.quantity) {
            toast.error(`Total quantity melebihi stok tersedia`);
            return;
        }
        existing.quantity += newNonHpItem.value.quantity;
    } else {
        selectedNonHpItems.value.push({
            product_id: newNonHpItem.value.product_id,
            quantity: newNonHpItem.value.quantity,
            product: product.product,
            selling_price: newNonHpItem.value.selling_price
        });
    }

    // Reset
    newNonHpItem.value = { product_id: null, quantity: 1, selling_price: 0 };
    showNonHpModal.value = false;
}

function removeNonHpItem(index) {
    selectedNonHpItems.value.splice(index, 1);
}

const proofImageFile = ref(null);
const proofImagePreview = ref(null);

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

const filteredItems = computed(() => {
    if (!searchQuery.value) return inventoryItems.value;
    const q = searchQuery.value.toLowerCase();
    return inventoryItems.value.filter(item =>
        item.imei?.toLowerCase().includes(q) ||
        item.product?.name?.toLowerCase().includes(q) ||
        item.product?.sku?.toLowerCase().includes(q)
    );
});

// Check if all filtered items are selected
const isAllSelected = computed(() => {
    if (filteredItems.value.length === 0) return false;
    return filteredItems.value.every(item => isSelected(item));
});

// Check if some (but not all) items are selected
const isSomeSelected = computed(() => {
    if (filteredItems.value.length === 0) return false;
    const selectedCount = filteredItems.value.filter(item => isSelected(item)).length;
    return selectedCount > 0 && selectedCount < filteredItems.value.length;
});

function toggleSelectAll() {
    if (isAllSelected.value) {
        // Deselect all filtered items
        filteredItems.value.forEach(item => {
            const idx = selectedItems.value.findIndex(i => i.id === item.id);
            if (idx !== -1) selectedItems.value.splice(idx, 1);
        });
    } else {
        // Select all filtered items
        filteredItems.value.forEach(item => {
            if (!isSelected(item)) selectedItems.value.push(item);
        });
    }
}

function toggleSelect(item) {
    const idx = selectedItems.value.findIndex(i => i.id === item.id);
    if (idx === -1) {
        selectedItems.value.push(item);
    } else {
        selectedItems.value.splice(idx, 1);
    }
}

function isSelected(item) {
    return selectedItems.value.some(i => i.id === item.id);
}

function formatCurrency(val) {
    if (!val) return '-';
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val);
}

async function openStockOutForm() {
    if (selectedItems.value.length === 0) {
        toast.error("Pilih minimal 1 barang");
        return;
    }

    const firstItem = selectedItems.value[0];
    if (firstItem && firstItem.placement_id) {
        if (!currentBranch.value || currentBranch.value.id !== firstItem.placement_id) {
            try {
                const response = await branchesApi.get(firstItem.placement_id);
                currentBranch.value = response.data.data || response.data;
            } catch (e) {
                console.error("Failed to load item context branch", e);
            }
        }
    }

    showForm.value = true;
    fetchWarehouses();
}

function resetForm() {
    form.value = {
        destination_branch_id: null,
        receiver_name: '',
        transfer_notes: '',
        deletion_reason: '',
        retur_officer: '',
        retur_seal: '',
        retur_issue: '',
        customer_name: '',
        customer_phone: '',
        shopee_receiver: '',
        shopee_phone: '',
        shopee_address: '',
        shopee_notes: '',
        shopee_tracking_no: '',
        selling_price: null,
    };
    selectedCategory.value = null;
    selectedNonHpItems.value = [];
}

function closeForm() {
    showForm.value = false;
    resetForm();
}

async function startScanner() {
    isScanning.value = true;

    // Wait for DOM to render the container
    await new Promise(resolve => setTimeout(resolve, 100));

    try {
        html5QrCode = new Html5Qrcode(scannerContainerId);

        const config = {
            fps: 10,
            qrbox: { width: 300, height: 150 },
            formatsToSupport: [
                0,  // QR_CODE
                4,  // CODE_128
                2,  // CODE_39
                11, // EAN_13
                10, // EAN_8
            ],
            aspectRatio: 1.7777778, // 16:9
        };

        await html5QrCode.start(
            { facingMode: "environment" },
            config,
            (decodedText) => {
                // On successful scan
                form.value.shopee_tracking_no = decodedText;
                toast.success(`Barcode terdeteksi: ${decodedText}`);
                stopScanner();
            },
            (errorMessage) => {
                // Ignore scan errors (normal when no barcode in view)
            }
        );
    } catch (e) {
        console.error("Scanner error:", e);
        toast.error("Gagal akses kamera. Silakan ketik manual nomor resi.");
        stopScanner();
    }
}

async function stopScanner() {
    isScanning.value = false;
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

// Cleanup on unmount
onUnmounted(() => {
    if (html5QrCode) {
        html5QrCode.stop().catch(() => { });
    }
});

// Region Logic
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
    form.value.shopee_province = name;

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
    form.value.shopee_city = name;

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
    form.value.shopee_district = name;

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
    form.value.shopee_village = name;
}

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

const canSubmit = computed(() => {
    if (!selectedCategory.value || selectedItems.value.length === 0) return false;

    switch (selectedCategory.value) {
        case 'pindah_cabang':
            return form.value.destination_id && form.value.receiver_name;
        case 'kesalahan_input':
            return form.value.deletion_reason.length >= 5;
        case 'retur':
            return form.value.retur_officer && form.value.retur_issue && form.value.customer_name && form.value.customer_phone;
        case 'shopee':
            return form.value.shopee_receiver && form.value.shopee_phone && form.value.shopee_address && form.value.shopee_tracking_no && form.value.selling_price;
        default:
            return true;
    }
});

async function submitStockOut() {
    if (!canSubmit.value) return;

    isSubmitting.value = true;
    try {
        const formData = new FormData();
        formData.append('category', selectedCategory.value);

        // Product IDs
        selectedItems.value.forEach(item => {
            formData.append('product_detail_ids[]', item.id);
        });

        // Form fields
        Object.keys(form.value).forEach(key => {
            if (key !== 'proof_image' && form.value[key] !== null && form.value[key] !== '') {
                formData.append(key, form.value[key]);
            }
        });

        // Non-HP Items
        selectedNonHpItems.value.forEach((item, index) => {
            formData.append(`non_hp_items[${index}][product_id]`, item.product_id);
            formData.append(`non_hp_items[${index}][quantity]`, item.quantity);
            if (item.selling_price) {
                formData.append(`non_hp_items[${index}][selling_price]`, item.selling_price);
            }
        });

        // File upload for retur
        if (selectedCategory.value === 'retur' && proofImageFile.value) {
            formData.append('proof_image', proofImageFile.value);
        }

        const response = await api.post('/stock-outs', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });
        toast.success(`Stok berhasil dikeluarkan! ID: ${response.data.data.receipt_id}`);

        selectedItems.value = [];
        closeForm();
        router.push('/inventory');

    } catch (e) {
        toast.error(e.response?.data?.message || "Gagal keluar stok");
        console.error(e);
    } finally {
        isSubmitting.value = false;
    }
}

onMounted(() => {
    fetchInventory();
    fetchBranches();
    fetchCurrentBranch();
    fetchProvinces();

    if (window.Echo) {
        // Listen for new stock coming in
        window.Echo.channel('inventory')
            .listen('.StockInEvent', (e) => {
                const product = e.product;
                // Add to list if it's available (StockInEvent implies it is available initially)
                // Check if already exists to avoid duplicate
                if (inventoryItems.value.find(i => i.id === product.id)) return;

                // Add to top
                inventoryItems.value.unshift(product);
                toast.success('Stok baru tersedia!');
            });

        // Listen for stock going out
        window.Echo.channel('stock-out')
            .listen('.StockOutEvent', (e) => {
                const out = e.stockOut;
                if (out.items && Array.isArray(out.items)) {
                    out.items.forEach(outItem => {
                        const idx = inventoryItems.value.findIndex(i => i.id === outItem.id);
                        if (idx !== -1) {
                            inventoryItems.value.splice(idx, 1);
                        }
                    });
                }
            });
    }
});
</script>

<template>
    <div class="space-y-6 animate-in fade-in max-w-6xl mx-auto pb-20">
        <div class="flex justify-between items-end">
            <div>
                <h1 class="text-2xl font-bold text-text-primary flex items-center gap-2">
                    <ArrowRightFromLine :size="28" class="text-orange-500" /> Pengeluaran Stok
                </h1>
                <p class="text-text-secondary mt-1">Pilih barang untuk dikeluarkan dari stok</p>
            </div>
            <button v-if="!showForm" @click="openStockOutForm" :disabled="selectedItems.length === 0"
                class="btn btn-primary px-6 h-12 rounded-2xl font-bold disabled:opacity-30">
                <Package :size="18" class="mr-2" />
                Keluar Stok ({{ selectedItems.length }})
            </button>
        </div>

        <div v-if="!showForm">
            <!-- Header Bar -->
            <div class="card mb-4">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <!-- Search -->
                    <div class="relative flex-1 max-w-md">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary" :size="18" />
                        <input v-model="searchQuery" type="text" placeholder="Pencarian Stok dengan IMEI"
                            class="input pl-10" />
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-2">
                        <button @click="fetchInventory" class="btn btn-secondary h-10 px-4 rounded-xl">
                            <Loader2 v-if="isLoading" :size="16" class="animate-spin mr-2" />
                            <span>Refresh</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Stats -->
            <div class="mb-4 flex items-center justify-between">
                <p class="text-text-primary font-medium">
                    Total Stok <span class="text-primary-500 font-bold">{{ filteredItems.length }}</span> Unit
                </p>
                <p v-if="selectedItems.length > 0" class="text-sm text-primary-400">
                    {{ selectedItems.length }} item dipilih
                </p>
            </div>

            <!-- Table -->
            <div class="card p-0 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-surface-700 bg-surface-700/50">
                                <th class="p-4 text-left w-12">
                                    <label class="flex items-center cursor-pointer">
                                        <input type="checkbox" :checked="isAllSelected"
                                            :indeterminate.prop="isSomeSelected" @change="toggleSelectAll"
                                            class="checkbox" />
                                    </label>
                                </th>
                                <th class="p-4 text-left text-xs font-bold text-text-secondary uppercase w-12">No</th>
                                <th class="p-4 text-left text-xs font-bold text-text-secondary uppercase">Merek</th>
                                <th class="p-4 text-left text-xs font-bold text-text-secondary uppercase">Tipe</th>
                                <th class="p-4 text-center text-xs font-bold text-text-secondary uppercase">Ram/Storage
                                </th>
                                <th class="p-4 text-left text-xs font-bold text-text-secondary uppercase">IMEI</th>
                                <th class="p-4 text-right text-xs font-bold text-text-secondary uppercase">Harga Modal
                                </th>
                                <th class="p-4 text-center text-xs font-bold text-text-secondary uppercase">Kondisi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="isLoading">
                                <td colspan="8" class="p-12 text-center text-text-secondary">
                                    <Loader2 :size="32" class="animate-spin mx-auto mb-2" />
                                    Memuat inventory...
                                </td>
                            </tr>
                            <tr v-else-if="filteredItems.length === 0">
                                <td colspan="8" class="p-12 text-center text-text-secondary">
                                    <Package :size="48" class="mx-auto mb-2 opacity-50" />
                                    Tidak ada barang tersedia
                                </td>
                            </tr>
                            <tr v-else v-for="(item, index) in filteredItems" :key="item.id" @click="toggleSelect(item)"
                                class="border-b border-surface-700/50 cursor-pointer transition-all hover:bg-surface-700/30"
                                :class="isSelected(item) ? 'bg-primary-500/10' : ''">
                                <td class="p-4">
                                    <label class="flex items-center" @click.stop>
                                        <input type="checkbox" :checked="isSelected(item)" @change="toggleSelect(item)"
                                            class="checkbox" />
                                    </label>
                                </td>
                                <td class="p-4 text-text-secondary text-sm">{{ index + 1 }}</td>
                                <td class="p-4">
                                    <span class="text-text-primary font-medium">{{ item.product?.brand || '-' }}</span>
                                </td>
                                <td class="p-4">
                                    <span class="text-text-secondary">{{ item.product?.name || '-' }}</span>
                                </td>
                                <td class="p-4 text-center">
                                    <span v-if="item.ram || item.storage"
                                        class="inline-flex px-2 py-1 rounded-lg bg-primary-500/20 text-primary-400 text-xs font-bold">
                                        {{ item.ram || '-' }}/{{ item.storage || '-' }}
                                    </span>
                                    <span v-else class="text-text-secondary">-</span>
                                </td>
                                <td class="p-4">
                                    <span class="font-mono text-sm text-text-primary">{{ item.imei }}</span>
                                </td>
                                <td class="p-4 text-right">
                                    <span class="text-text-secondary text-sm">{{ formatCurrency(item.cost_price)
                                        }}</span>
                                </td>
                                <td class="p-4 text-center">
                                    <span :class="[
                                        'px-2 py-1 rounded text-xs font-bold uppercase',
                                        item.condition === 'new' ? 'bg-green-500/20 text-green-400' : 'bg-amber-500/20 text-amber-400'
                                    ]">
                                        {{ item.condition === 'new' ? 'NEW' : 'SCD' }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div v-else class="space-y-6">
            <button @click="closeForm" class="btn btn-secondary mb-4">
                <ChevronLeft :size="18" class="mr-1" /> Kembali
            </button>

            <div v-if="!selectedCategory" class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <button v-for="cat in categories" :key="cat.id" @click="selectCategory(cat)"
                    class="card p-6 text-center hover:border-primary-500 border-2 border-transparent transition-all">
                    <component :is="cat.icon" :size="40" class="mx-auto mb-3" :class="`text-${cat.color}-500`" />
                    <p class="font-bold text-text-primary">{{ cat.name }}</p>
                </button>
            </div>

            <div v-else class="card p-8 border-t-4" :class="{
                'border-t-blue-500': selectedCategory === 'pindah_cabang',
                'border-t-amber-500': selectedCategory === 'kesalahan_input',
                'border-t-purple-500': selectedCategory === 'retur',
                'border-t-orange-500': selectedCategory === 'shopee',
                'border-t-pink-500': selectedCategory === 'giveaway',
                'border-t-yellow-500': selectedCategory === 'hadiah',
                'border-t-indigo-500': selectedCategory === 'brand_ambassador',
                'border-t-cyan-500': selectedCategory === 'event',
                'border-t-red-500': selectedCategory === 'promo',
                'border-t-slate-500': selectedCategory === 'inventaris',
            }">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold text-text-primary">
                        {{categories.find(c => c.id === selectedCategory)?.name}}
                    </h2>
                    <button @click="selectedCategory = null" class="text-text-secondary hover:text-text-primary">
                        <X :size="24" />
                    </button>
                </div>

                <div v-if="selectedCategory === 'pindah_cabang'" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="label">Tipe Tujuan *</label>
                            <select v-model="form.destination_type" class="input">
                                <option value="branch">Cabang / Store</option>
                                <option value="warehouse">Gudang</option>
                                <option value="online_shop">Online Shop</option>
                                <option value="distributor">Distributor</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Tujuan *</label>
                            <select v-model="form.destination_id" class="input" :disabled="!form.destination_type">
                                <option :value="null">-- Pilih Tujuan --</option>
                                <template v-if="form.destination_type === 'branch'">
                                    <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                                </template>
                                <template v-if="form.destination_type === 'warehouse'">
                                    <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                                </template>
                                <template v-if="form.destination_type === 'online_shop'">
                                    <option v-for="o in onlineShops" :key="o.id" :value="o.id">{{ o.name }} ({{
                                        o.platform }})</option>
                                </template>
                                <template v-if="form.destination_type === 'distributor'">
                                    <option v-for="d in distributors" :key="d.id" :value="d.id">{{ d.name }}</option>
                                </template>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="label">Nama Penerima *</label>
                        <input v-model="form.receiver_name" class="input" placeholder="Nama yang menerima barang" />
                    </div>
                    <div>
                        <label class="label">Catatan</label>
                        <textarea v-model="form.transfer_notes" class="input" rows="3"
                            placeholder="Catatan tambahan..."></textarea>
                    </div>
                </div>

                <div v-if="selectedCategory === 'kesalahan_input'" class="space-y-4">
                    <div>
                        <label class="label">Alasan Hapus *</label>
                        <textarea v-model="form.deletion_reason" class="input" rows="4"
                            placeholder="Jelaskan alasan penghapusan data..."></textarea>
                    </div>
                </div>

                <div v-if="selectedCategory === 'retur'" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="label">Nama Petugas *</label>
                            <input v-model="form.retur_officer" class="input" placeholder="Nama petugas retur" />
                        </div>
                        <div>
                            <label class="label">Pilih Gudang *</label>
                            <select v-model="form.return_destination_id" class="input">
                                <option :value="null">-- Pilih Gudang Retur --</option>
                                <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="label">Foto Bukti / Kondisi (Max 10MB) *</label>
                        <input type="file" accept="image/*" @change="handleFileChange"
                            class="w-full text-sm text-text-secondary file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-bold file:bg-surface-700 file:text-primary-400 hover:file:bg-surface-600 transition-all cursor-pointer border border-surface-700 rounded-xl bg-surface-800" />
                        <div v-if="proofImagePreview" class="mt-3">
                            <img :src="proofImagePreview"
                                class="h-32 rounded-xl object-cover border border-surface-600" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="label">Segel</label>
                            <input v-model="form.retur_seal" class="input" placeholder="Nomor segel (opsional)" />
                        </div>
                        <div>
                            <label class="label">Nama Customer *</label>
                            <input v-model="form.customer_name" class="input" placeholder="Nama customer" />
                        </div>
                    </div>

                    <div>
                        <label class="label">Kendala / Masalah *</label>
                        <textarea v-model="form.retur_issue" class="input" rows="3"
                            placeholder="Jelaskan kendala atau masalah..."></textarea>
                    </div>

                    <div>
                        <label class="label">No. WA Customer *</label>
                        <input v-model="form.customer_phone" class="input" placeholder="08xxxxxxxxxx" />
                    </div>
                </div>

                <div v-if="selectedCategory === 'shopee'" class="space-y-4">
                    <div>
                        <label class="label text-emerald-500">SRP (Rp) *</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary text-sm">Rp</span>
                            <input v-model="form.selling_price" type="number" class="input pl-10 bg-surface-800"
                                placeholder="0" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="label">Nama Penerima *</label>
                            <input v-model="form.shopee_receiver" class="input" placeholder="Nama penerima" />
                        </div>
                        <div>
                            <label class="label">No. WA *</label>
                            <input v-model="form.shopee_phone" class="input" placeholder="08xxxxxxxxxx" />
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="label">Provinsi *</label>
                            <select :value="selectedRegionIds.province" @change="e => onProvinceChange(e.target.value)"
                                class="input bg-surface-800">
                                <option value="">-- Pilih Provinsi --</option>
                                <option v-for="p in provinces" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Kota/Kabupaten *</label>
                            <select :value="selectedRegionIds.city" @change="e => onCityChange(e.target.value)"
                                class="input bg-surface-800" :disabled="!selectedRegionIds.province">
                                <option value="">-- Pilih Kota/Kabupaten --</option>
                                <option v-for="c in cities" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="label">Alamat Lengkap *</label>
                        <textarea v-model="form.shopee_address" class="input bg-surface-800" rows="2"
                            placeholder="Nama jalan, nomor rumah, RT/RW..."></textarea>
                    </div>
                    <div>
                        <label class="label">Catatan</label>
                        <textarea v-model="form.shopee_notes" class="input" rows="2"
                            placeholder="Catatan pengiriman..."></textarea>
                    </div>
                    <div>
                        <label class="label">No. Resi Shopee *</label>
                        <div class="flex gap-2">
                            <input v-model="form.shopee_tracking_no" class="input flex-1 font-mono"
                                placeholder="Scan atau ketik manual..." />
                            <button @click="startScanner" type="button" class="btn btn-secondary px-4"
                                :class="isScanning ? 'bg-orange-500 text-white' : ''">
                                <ScanBarcode :size="20" />
                            </button>
                        </div>
                    </div>

                    <!-- Scanner Modal -->
                    <div v-if="isScanning"
                        class="fixed inset-0 bg-black/95 z-50 flex flex-col items-center justify-center p-4">
                        <div class="relative w-full max-w-lg bg-surface-800 rounded-2xl overflow-hidden">
                            <!-- Scanner Header -->
                            <div class="flex items-center justify-between p-4 border-b border-surface-700">
                                <h3 class="text-white font-bold flex items-center gap-2">
                                    <ScanBarcode :size="20" class="text-orange-500" />
                                    Scan Barcode Resi
                                </h3>
                                <button @click="stopScanner"
                                    class="text-text-secondary hover:text-white transition-colors">
                                    <X :size="24" />
                                </button>
                            </div>

                            <!-- Scanner Container -->
                            <div :id="scannerContainerId" class="w-full aspect-video bg-black"></div>

                            <!-- Instructions -->
                            <div class="p-4 text-center space-y-3">
                                <p class="text-text-secondary text-sm animate-pulse">
                                    Arahkan kamera ke barcode resi...
                                </p>
                                <div class="text-xs text-text-secondary">
                                    <p>Atau ketik manual nomor resi di form lalu tutup scanner</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 pt-6 border-t border-surface-700">
                    <p class="text-xs uppercase font-bold text-text-secondary mb-3">Barang HP ({{
                        selectedItems.length }})</p>
                    <div class="flex flex-wrap gap-2">
                        <div v-for="item in selectedItems" :key="item.id"
                            class="bg-surface-700 px-3 py-2 rounded-xl text-sm flex items-center gap-2">
                            <Smartphone :size="14" />
                            <span class="font-mono text-xs">{{ item.imei }}</span>
                        </div>
                    </div>
                </div>

                <!-- Non-HP Items Section -->
                <div class="mt-6 pt-6 border-t border-surface-700">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-xs uppercase font-bold text-text-secondary">Barang Non-HP ({{
                            selectedNonHpItems.length }})</p>
                        <button @click="showNonHpModal = true"
                            class="text-primary-400 text-xs font-bold hover:underline">
                            + Tambah Barang Non-HP
                        </button>
                    </div>

                    <!-- List Selected Non-HP -->
                    <div v-if="selectedNonHpItems.length > 0" class="space-y-2">
                        <div v-for="(item, idx) in selectedNonHpItems" :key="idx"
                            class="flex items-center justify-between bg-surface-700 p-3 rounded-xl">
                            <div>
                                <p class="font-bold text-sm text-text-primary">{{ item.product?.name }}</p>
                                <p class="text-xs text-text-secondary">{{ item.quantity }} Unit</p>
                            </div>
                            <button @click="removeNonHpItem(idx)" class="text-red-400 hover:text-red-300">
                                <X :size="16" />
                            </button>
                        </div>
                    </div>
                    <div v-else
                        class="text-center py-4 bg-surface-700/30 rounded-xl text-text-secondary text-xs italic">
                        Belum ada barang non-HP dipilih
                    </div>

                    <!-- Non HP Modal -->
                    <div v-if="showNonHpModal"
                        class="fixed inset-0 bg-black/80 z-[70] flex items-center justify-center p-4">
                        <div class="bg-surface-800 rounded-2xl w-full max-w-md p-6 border border-surface-700">
                            <h3 class="font-bold text-lg text-white mb-4">Tambah Barang Non-HP</h3>
                            <div class="space-y-4">
                                <div>
                                    <label class="label">Pilih Produk</label>
                                    <select v-model="newNonHpItem.product_id" class="input">
                                        <option :value="null">-- Pilih Produk --</option>
                                        <option v-for="inv in nonHpInventory" :key="inv.id" :value="inv.product_id">
                                            {{ inv.product?.name }} (Sisa: {{ inv.quantity }})
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label class="label">Jumlah</label>
                                    <input v-model="newNonHpItem.quantity" type="number" min="1" class="input" />
                                </div>
                                <div v-if="selectedCategory === 'shopee'">
                                    <label class="label">Harga Jual (per unit)</label>
                                    <input v-model="newNonHpItem.selling_price" type="number" min="0" class="input" />
                                </div>
                                <div class="flex justify-end gap-2 mt-6">
                                    <button @click="showNonHpModal = false"
                                        class="btn btn-secondary px-4">Batal</button>
                                    <button @click="addNonHpItem" class="btn btn-primary px-4">Tambah</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex justify-end">
                    <button @click="submitStockOut" :disabled="!canSubmit || isSubmitting"
                        class="btn btn-primary px-10 h-14 rounded-2xl font-bold text-sm disabled:opacity-30">
                        <Loader2 v-if="isSubmitting" :size="20" class="animate-spin mr-2" />
                        {{ isSubmitting ? 'Memproses...' : 'Konfirmasi Keluar Stok' }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="showReturnBlockedAlert"
            class="fixed inset-0 bg-black/80 z-[60] flex items-center justify-center p-4">
            <div
                class="bg-surface-800 rounded-2xl max-w-md w-full p-6 border border-red-500/30 shadow-2xl animate-in zoom-in duration-200">
                <div class="flex flex-col items-center text-center">
                    <div class="w-16 h-16 rounded-full bg-red-500/10 flex items-center justify-center mb-4">
                        <AlertTriangle :size="32" class="text-red-500" />
                    </div>
                    <h3 class="text-xl font-bold text-white mb-2">Retur Tidak Diterima</h3>
                    <p class="text-text-secondary mb-6">
                        Mohon maaf, gudang saat ini sedang <strong>TIDAK MENERIMA RETUR</strong>.
                        Silakan coba lagi nanti atau hubungi Admin Gudang.
                    </p>
                    <button @click="showReturnBlockedAlert = false"
                        class="btn bg-surface-700 hover:bg-surface-600 text-white w-full h-12 rounded-xl">
                        Mengerti
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@reference "../../style.css";

.label {
    @apply block text-text-secondary mb-2 font-semibold text-sm;
}

.input {
    @apply w-full border border-surface-700 rounded-xl px-4 py-3 bg-surface-800 text-text-primary focus:outline-none focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 transition-all placeholder:text-surface-500;
}

.btn {
    @apply font-bold transition-all duration-300 disabled:opacity-20 disabled:cursor-not-allowed flex items-center justify-center rounded-xl;
}

.btn-primary {
    @apply bg-primary-600 hover:bg-primary-500 text-white;
}

.btn-secondary {
    @apply bg-surface-700 hover:bg-surface-600 text-text-secondary hover:text-white border border-surface-600;
}

.card {
    @apply bg-surface-800 rounded-2xl p-6 border border-surface-700;
}

.checkbox {
    @apply w-5 h-5 rounded border-2 border-surface-500 bg-surface-700 checked:bg-primary-500 checked:border-primary-500 focus:ring-2 focus:ring-primary-500/30 focus:ring-offset-0 cursor-pointer transition-all appearance-none relative;
}

.checkbox:checked::after {
    content: '';
    position: absolute;
    left: 5px;
    top: 2px;
    width: 5px;
    height: 10px;
    border: solid white;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
}

.checkbox:indeterminate {
    @apply bg-primary-500 border-primary-500;
}

.checkbox:indeterminate::after {
    content: '';
    position: absolute;
    left: 3px;
    top: 7px;
    width: 10px;
    height: 2px;
    background: white;
}
</style>