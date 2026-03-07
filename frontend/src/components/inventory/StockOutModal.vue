<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from "vue";
import { useAuthStore } from "../../store/auth";
import { useInventoryStore } from "../../store/inventory";
import { useToast } from "../../composables/useToast";
import api, { users as usersApi, branches as branchesApi, warehouses as warehousesApi, onlineShops as onlineShopsApi, distributors as distributorsApi } from "../../api/axios";
import { formatCurrency, formatNumber, parseCurrency } from "../../utils/formatters";
import { Html5Qrcode } from "html5-qrcode";
import {
    ChevronLeft, ChevronRight, X, UserCheck, Box, Smartphone,
    Loader2, ScanBarcode, User, ArrowRightLeft, AlertTriangle, RotateCcw, LogOut, Plus, Shield
} from "lucide-vue-next";
import PinModal from "../modals/PinModal.vue";

const props = defineProps({
    show: { type: Boolean, default: false },
    selectedItems: { type: Array, default: () => [] },
    activeTab: { type: String, default: 'hp' }
});

const emit = defineEmits(['close', 'success']);

const authStore = useAuthStore();
const inventoryStore = useInventoryStore();
const toast = useToast();

const apiUrl = import.meta.env.VITE_API_URL || 'https://api.stokps.com/api';
const storageUrl = apiUrl.replace(/\/api\/?$/, '');

// Copying required refs and functions from Inventory.vue
const isSubmitting = ref(false);
const selectedStockOutCategory = ref(null);
const showPinModal = ref(false);

const stockOutForm = ref({
    sub_category: '',
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

// Modal Searcher
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
            type: props.activeTab // Match current tab for searching
        }, true);
        modalSearchResults.value = response.data;
    } catch (e) {
        console.error("Modal search failed", e);
    } finally {
        isSearchingInModal.value = false;
    }
}

function addItemFromModal(item) {
    const isSelected = props.selectedItems.some(i => i.id === item.id && i.type === (item.type || props.activeTab));
    if (!isSelected) {
        if (!item.type) item.type = props.activeTab;
        if (item.type === 'non-hp') item.out_quantity = 1;
        item.selling_price = item.selling_price || 0;

        // Create new array to trigger reactivity if needed
        props.selectedItems.push(item);
        toast.success(`${item.product?.name || 'Item'} ditambahkan`);
    } else {
        toast.info("Item sudah ada di daftar");
    }
}

// Scanner
const isScanning = ref(false);
const scannerContainerId = 'barcode-scanner-container-modal';
const scanningItemIndex = ref(null);
let html5QrCode = null;

const shopeeItemForms = ref([]);
const proofImageFile = ref(null);
const proofImagePreview = ref(null);

const branches = ref([]);
const warehouses = ref([]);
const onlineShops = ref([]);
const distributors = ref([]);

// Region
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

onMounted(() => {
    fetchBranches();
    fetchWarehouses();
    fetchOnlineShops();
    fetchDistributors();
    fetchProvinces();
});

watch(() => props.show, (newVal) => {
    if (newVal) {
        fetchInventoryUsers();
    } else {
        selectedStockOutCategory.value = null;
        selectedInventoryUser.value = null;
        modalSearchQuery.value = "";
        modalSearchResults.value = [];
        resetStockOutForm();
    }
});

async function fetchBranches() {
    try {
        const response = await branchesApi.list();
        const allBranches = response.data.data || response.data;
        branches.value = allBranches.filter(b => b.is_active);
    } catch (e) {
        console.error("Gagal memuat cabang", e);
    }
}

async function fetchWarehouses() {
    try {
        const response = await warehousesApi.list();
        warehouses.value = response.data.data || response.data;
    } catch (e) {
        console.error("Gagal load warehouses", e);
    }
}

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

function onUnmountedLogic() {
    if (html5QrCode) {
        html5QrCode.stop().catch(() => { });
    }
}
onUnmounted(onUnmountedLogic);

// Stock Out Categories
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

function selectStockOutCategory(category) {
    selectedStockOutCategory.value = category.id;
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
        notes: '',
    };
    selectedRegionIds.value = { province: "", city: "", district: "", village: "" };
    cities.value = [];
    proofImageFile.value = null;
    proofImagePreview.value = null;
}

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

async function startScanner(itemIndex = null) {
    isScanning.value = true;
    scanningItemIndex.value = itemIndex;
    await new Promise(resolve => setTimeout(resolve, 100));

    try {
        html5QrCode = new Html5Qrcode(scannerContainerId);
        const config = { fps: 10, qrbox: { width: 300, height: 150 }, aspectRatio: 1.7777778 };

        await html5QrCode.start(
            { facingMode: "environment" },
            config,
            (decodedText) => {
                stockOutForm.value.shopee_tracking_no = decodedText;
                toast.success(`Barcode terdeteksi: ${decodedText}`);
                stopScanner();
            },
            () => { }
        );
    } catch (e) {
        toast.error("Gagal akses kamera.");
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
        } catch (e) { }
        html5QrCode = null;
    }
}

const canSubmitStockOut = computed(() => {
    if (!selectedStockOutCategory.value || props.selectedItems.length === 0) return false;

    switch (selectedStockOutCategory.value) {
        case 'pindah_cabang':
            return stockOutForm.value.destination_type && stockOutForm.value.destination_id && stockOutForm.value.receiver_name;
        case 'kesalahan_input':
            return stockOutForm.value.deletion_reason.length >= 5;
        case 'retur':
            return stockOutForm.value.retur_officer && stockOutForm.value.retur_issue &&
                stockOutForm.value.customer_name && stockOutForm.value.customer_phone;
        case 'orderan_online':
        case 'shopee':
            const allItemsHavePrice = props.selectedItems.every(item => item.selling_price && item.selling_price > 0);
            return stockOutForm.value.shopee_receiver &&
                stockOutForm.value.shopee_address &&
                stockOutForm.value.shopee_tracking_no &&
                allItemsHavePrice;
        case 'keluar':
            return stockOutForm.value.sub_category && stockOutForm.value.receiver_name;
        case 'giveaway':
            return true;
        case 'inventaris':
            return selectedInventoryUser.value !== null;
        default:
            return true;
    }
});

async function handlePinSuccess(pin) {
    showPinModal.value = false;
    await submitStockOut(pin);
}

async function submitStockOut(pin = null) {
    if (!canSubmitStockOut.value) return;

    // If user has PIN enabled and we don't have a verified PIN yet
    if (authStore.user?.pin_enabled && !pin) {
        showPinModal.value = true;
        return;
    }

    isSubmitting.value = true;
    try {
        const formData = new FormData();
        formData.append('category', selectedStockOutCategory.value);
        if (pin) {
            formData.append('transaction_pin', pin);
        }

        const hpItems = props.selectedItems.filter(item => !item.type || item.type === 'hp');
        const nonHpItems = props.selectedItems.filter(item => item.type === 'non-hp');

        nonHpItems.forEach((item, index) => {
            formData.append(`non_hp_items[${index}][product_id]`, item.product_id || item.id);
            formData.append(`non_hp_items[${index}][quantity]`, item.out_quantity || 1);
            if (['shopee', 'orderan_online'].includes(selectedStockOutCategory.value)) {
                formData.append(`non_hp_items[${index}][selling_price]`, item.selling_price || 0);
            }
        });

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

        if (['shopee', 'orderan_online'].includes(selectedStockOutCategory.value)) {
            hpItems.forEach((item, index) => {
                formData.append(`shopee_items[${index}][product_detail_id]`, item.id);
                formData.append(`shopee_items[${index}][receiver]`, stockOutForm.value.shopee_receiver);
                formData.append(`shopee_items[${index}][phone]`, stockOutForm.value.shopee_phone);
                formData.append(`shopee_items[${index}][address]`, stockOutForm.value.shopee_address);
                formData.append(`shopee_items[${index}][notes]`, stockOutForm.value.shopee_notes);
                formData.append(`shopee_items[${index}][tracking_no]`, stockOutForm.value.shopee_tracking_no);
                formData.append(`shopee_items[${index}][selling_price]`, item.selling_price || 0);
            });

            formData.append('shopee_receiver', stockOutForm.value.shopee_receiver);
            formData.append('shopee_phone', stockOutForm.value.shopee_phone);
            formData.append('shopee_address', stockOutForm.value.shopee_address);
            formData.append('shopee_province', stockOutForm.value.shopee_province);
            formData.append('shopee_city', stockOutForm.value.shopee_city);
            formData.append('shopee_district', stockOutForm.value.shopee_district);
            formData.append('shopee_village', stockOutForm.value.shopee_village);
            formData.append('shopee_postal_code', stockOutForm.value.shopee_postal_code);
            formData.append('shopee_tracking_no', stockOutForm.value.shopee_tracking_no);
            formData.append('notes', stockOutForm.value.shopee_notes);
        } else if (selectedStockOutCategory.value === 'keluar') {
            formData.append('notes', stockOutForm.value.notes);
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
        emit('success');
    } catch (e) {
        toast.error(e.response?.data?.message || "Gagal keluar stok");
        console.error(e);
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        <div
            class="bg-white dark:!bg-surface-800 rounded-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col animate-in zoom-in duration-200 border border-surface-200 dark:border-surface-700 shadow-xl">
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
                        {{selectedStockOutCategory ? stockOutCategories.find(c => c.id ===
                            selectedStockOutCategory)?.name : 'Pilih Kategori'}}
                    </h2>
                </div>
                <button @click="emit('close')" class="text-text-secondary hover:text-text-primary transition-colors">
                    <X :size="24" />
                </button>
            </div>

            <!-- Modal Body -->
            <div class="flex-1 overflow-y-auto p-6 bg-surface-900/50">
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
                        <div class="bg-surface-800 p-6 rounded-2xl inline-block mb-3 border border-surface-700">
                            <UserCheck :size="32" class="text-surface-500" />
                        </div>
                        <p>Tidak ada akun inventory ditemukan.</p>
                    </div>

                    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div v-for="user in inventoryUsers" :key="user.id" @click="selectedInventoryUser = user"
                            class="p-4 rounded-2xl border border-surface-700 bg-surface-800 cursor-pointer hover:border-primary-500 hover:bg-surface-700 transition-all relative group shadow-sm hover:shadow-md">
                            <div class="flex items-center gap-4">
                                <div
                                    class="w-14 h-14 rounded-xl bg-surface-900 shrink-0 flex items-center justify-center overflow-hidden border border-surface-700 group-hover:border-primary-500/50 transition-colors">
                                    <img v-if="user.photo_inventory"
                                        :src="`${storageUrl}/storage/${user.photo_inventory}`"
                                        class="w-full h-full object-cover" alt="Foto" />
                                    <span v-else class="text-xl font-bold text-primary-500">{{ (user.full_name ||
                                        user.name || '?')[0].toUpperCase() }}</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h4 class="font-bold text-text-primary truncate text-base mb-0.5">{{ user.full_name
                                        || user.name }}</h4>
                                    <div class="flex flex-col gap-0.5">
                                        <span
                                            class="text-xs text-text-secondary uppercase tracking-wider font-medium">{{
                                                user.roles?.[0]?.name || 'INVENTORY' }}</span>
                                        <span v-if="user.phone"
                                            class="text-[10px] text-emerald-500 font-mono flex items-center gap-1">
                                            <Smartphone :size="10" /> {{ user.phone }}
                                        </span>
                                    </div>
                                </div>
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
                        class="flex items-center justify-between mb-6 bg-surface-800 p-3 rounded-xl border border-surface-700">
                        <div class="flex items-center gap-3">
                            <div
                                class="w-10 h-10 rounded-full bg-surface-900 flex items-center justify-center overflow-hidden border border-surface-700">
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
                        <button @click="selectedInventoryUser = null" class="text-xs text-primary-500 font-medium">Ganti
                            Akun</button>
                    </div>

                    <h3 class="text-lg font-bold text-text-primary mb-4 flex items-center gap-2">
                        <Box :size="20" class="text-primary-500" /> Pilih Kategori Pengeluaran
                    </h3>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <button v-for="category in availableStockOutCategories" :key="category.id"
                            @click="selectStockOutCategory(category)"
                            class="flex flex-col items-center justify-center p-6 rounded-2xl border border-surface-700 bg-surface-800 hover:bg-surface-700 transition-all group gap-3 text-center shadow-sm">
                            <div
                                :class="`p-3 rounded-full bg-${category.color}-500/10 text-${category.color}-500 group-hover:scale-110`">
                                <component :is="category.icon" :size="28" />
                            </div>
                            <span class="font-medium text-sm md:text-base text-text-primary">{{ category.name }}</span>
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
                                        <option v-for="o in onlineShops" :key="o.id" :value="o.id">{{ o.name }}</option>
                                    </template>
                                    <template v-if="stockOutForm.destination_type === 'distributor'">
                                        <option v-for="d in distributors" :key="d.id" :value="d.id">{{ d.name }}
                                        </option>
                                    </template>
                                </select>
                            </div>
                        </div>
                        <div class="bg-surface-700/30 p-4 rounded-xl border border-surface-600 mb-4">
                            <p class="text-xs uppercase font-bold text-text-secondary mb-3">Item yang dikirim ({{
                                props.selectedItems.length }})
                            </p>
                            <div class="space-y-3 max-h-80 overflow-y-auto pr-1">
                                <div v-for="(item, idx) in props.selectedItems" :key="item.id + (item.type || '')"
                                    class="bg-surface-800 p-3 rounded-xl border border-surface-600">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <span class="text-primary-400 font-bold text-xs">{{ idx + 1 }}.</span>
                                            <div>
                                                <p class="font-medium text-sm text-white">{{ item.product?.name }}</p>
                                                <p class="text-[10px] text-text-secondary">{{ item.product?.brand }} {{
                                                    item.product?.type }}
                                                </p>
                                            </div>
                                        </div>
                                        <span v-if="item.type !== 'non-hp'"
                                            class="text-xs font-mono bg-surface-700 px-2 py-0.5 rounded text-text-secondary">{{
                                                item.imei
                                            }}</span>
                                    </div>
                                    <div v-if="item.type === 'non-hp'" class="w-full md:w-1/2 mt-2">
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
                            <input v-model="stockOutForm.receiver_name" class="input"
                                placeholder="Nama yang menerima barang" />
                        </div>
                        <div>
                            <label class="label">Catatan</label>
                            <textarea v-model="stockOutForm.transfer_notes" class="input" rows="3"></textarea>
                        </div>
                    </template>

                    <!-- Kesalahan Input Form -->
                    <template v-if="selectedStockOutCategory === 'kesalahan_input'">
                        <div class="bg-surface-700/30 p-4 rounded-xl border border-surface-600 mb-4">
                            <p class="text-xs uppercase font-bold text-text-secondary mb-3">Item yang dihapus ({{
                                props.selectedItems.length }})</p>
                            <div class="space-y-3 max-h-80 overflow-y-auto pr-1">
                                <div v-for="(item, idx) in props.selectedItems" :key="item.id + (item.type || '')"
                                    class="bg-surface-800 p-3 rounded-xl border border-surface-600 space-y-3">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <span class="text-primary-400 font-bold text-xs">{{ idx + 1 }}.</span>
                                            <div>
                                                <p class="font-medium text-sm text-white">{{ item.product?.name }}</p>
                                                <p class="text-[10px] text-text-secondary">{{ item.product?.brand }}</p>
                                            </div>
                                        </div>
                                        <span v-if="item.type !== 'non-hp'"
                                            class="text-xs font-mono bg-surface-700 px-2 rounded">{{ item.imei }}</span>
                                    </div>
                                    <div v-if="item.type === 'non-hp'" class="w-full md:w-1/2">
                                        <label class="text-[10px] text-text-secondary block mb-1">Qty Hapus</label>
                                        <div class="flex items-center gap-2">
                                            <input type="number" v-model="item.out_quantity"
                                                class="w-full text-sm p-2 rounded-lg bg-surface-900 text-center" min="1"
                                                :max="item.quantity">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="label">Alasan Hapus *</label>
                            <textarea v-model="stockOutForm.deletion_reason" class="input" rows="4"></textarea>
                        </div>
                    </template>

                    <!-- Retur Form -->
                    <template v-if="selectedStockOutCategory === 'retur'">
                        <div class="bg-surface-700/30 p-4 rounded-xl border border-surface-600 mb-4">
                            <p class="text-xs uppercase font-bold text-text-secondary mb-3">Item yang diretur ({{
                                props.selectedItems.length }})</p>
                            <div class="space-y-3 max-h-80 overflow-y-auto pr-1">
                                <div v-for="(item, idx) in props.selectedItems" :key="item.id + (item.type || '')"
                                    class="bg-surface-800 p-3 rounded-xl border border-surface-600 space-y-3">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <span class="text-primary-400 font-bold text-xs">{{ idx + 1 }}.</span>
                                            <div>
                                                <p class="font-medium text-sm text-white">{{ item.product?.name }}</p>
                                            </div>
                                        </div>
                                        <span v-if="item.type !== 'non-hp'"
                                            class="text-xs font-mono bg-surface-700 px-2 rounded">{{ item.imei }}</span>
                                    </div>
                                    <div v-if="item.type === 'non-hp'" class="w-full md:w-1/2">
                                        <label class="text-[10px] text-text-secondary block mb-1">Qty Retur</label>
                                        <input type="number" v-model="item.out_quantity"
                                            class="w-full text-sm p-2 rounded-lg bg-surface-900 text-center">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div><label class="label">Nama Petugas *</label><input v-model="stockOutForm.retur_officer"
                                    class="input" /></div>
                            <div>
                                <label class="label">Pilih Gudang *</label>
                                <select v-model="stockOutForm.return_destination_id" class="input">
                                    <option :value="null">-- Pilih Gudang --</option>
                                    <option v-for="w in warehouses.filter(w => w.can_accept_returns)" :key="w.id"
                                        :value="w.id">{{ w.name }}</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="label">Foto Bukti / Kondisi</label>
                            <input type="file" accept="image/*" @change="handleFileChange"
                                class="w-full text-sm border border-surface-700 bg-surface-800 rounded-xl" />
                            <img v-if="proofImagePreview" :src="proofImagePreview"
                                class="h-24 rounded-xl object-cover mt-3 border border-surface-600" />
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3">
                            <div><label class="label">Segel</label><input v-model="stockOutForm.retur_seal"
                                    class="input" /></div>
                            <div><label class="label">Nama Customer *</label><input v-model="stockOutForm.customer_name"
                                    class="input" /></div>
                        </div>
                        <div class="mt-3">
                            <label class="label">Kendala / Masalah *</label>
                            <textarea v-model="stockOutForm.retur_issue" class="input" rows="3"></textarea>
                        </div>
                        <div class="mt-3">
                            <label class="label">No. WA Customer *</label>
                            <input v-model="stockOutForm.customer_phone" class="input" />
                        </div>
                    </template>

                    <!-- Shopee / Orderan Online -->
                    <template
                        v-if="selectedStockOutCategory === 'shopee' || selectedStockOutCategory === 'orderan_online'">
                        <div class="space-y-4">
                            <div class="bg-surface-700/30 p-4 rounded-xl border border-surface-600">
                                <p class="text-xs uppercase font-bold text-text-secondary mb-3">Item yang dikirim ({{
                                    props.selectedItems.length }})</p>

                                <div class="mb-4 relative">
                                    <div class="relative">
                                        <input v-model="modalSearchQuery" @input="searchInModal" type="text"
                                            placeholder="Tambah produk lain..."
                                            class="w-full text-sm p-2 rounded-xl bg-surface-800 border" />
                                        <Loader2 v-if="isSearchingInModal" :size="14"
                                            class="absolute right-3 top-3 animate-spin text-primary-500" />
                                    </div>
                                    <div v-if="modalSearchResults.length > 0"
                                        class="absolute z-50 mt-1 w-full bg-surface-800 rounded-xl max-h-40 overflow-auto">
                                        <div v-for="res in modalSearchResults" :key="res.id"
                                            @click="addItemFromModal(res)"
                                            class="p-2 hover:bg-surface-700 cursor-pointer text-sm">
                                            {{ res.product?.name }} - {{ res.imei || res.product?.sku }}
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-3">
                                    <div v-for="(item, idx) in props.selectedItems" :key="item.id"
                                        class="bg-surface-800 p-3 rounded-xl">
                                        <div class="flex justify-between items-center mb-2">
                                            <span class="text-sm font-medium">{{ item.product?.name }}</span>
                                            <span v-if="item.type !== 'non-hp'" class="text-xs font-mono">{{ item.imei
                                            }}</span>
                                        </div>
                                        <div class="flex gap-3">
                                            <div v-if="item.type === 'non-hp'" class="w-1/3">
                                                <label class="text-[10px] block mb-1">Qty</label>
                                                <input type="number" v-model="item.out_quantity" min="1"
                                                    :max="item.quantity"
                                                    class="w-full bg-surface-900 border text-sm p-2 rounded" />
                                            </div>
                                            <div class="flex-1">
                                                <label class="text-[10px] text-emerald-500 block mb-1">SRP (Per
                                                    Item)</label>
                                                <input type="text"
                                                    :value="item.selling_price ? formatNumber(item.selling_price) : ''"
                                                    @input="e => { item.selling_price = parseCurrency(e.target.value); e.target.value = formatNumber(item.selling_price); }"
                                                    class="w-full bg-surface-900 border text-sm p-2 rounded" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div><label class="label">Penerima *</label><input
                                        v-model="stockOutForm.shopee_receiver" class="input" /></div>
                                <div><label class="label">No WA</label><input v-model="stockOutForm.shopee_phone"
                                        class="input" /></div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="label">Provinsi *</label>
                                    <select :value="selectedRegionIds.province"
                                        @change="e => onProvinceChange(e.target.value)" class="input">
                                        <option value="">Pilih Provinsi</option>
                                        <option v-for="p in provinces" :key="p.id" :value="p.id">{{ p.name }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="label">Kota *</label>
                                    <select :value="selectedRegionIds.city" @change="e => onCityChange(e.target.value)"
                                        class="input">
                                        <option value="">Pilih Kota</option>
                                        <option v-for="c in cities" :key="c.id" :value="c.id">{{ c.name }}</option>
                                    </select>
                                </div>
                            </div>
                            <div><label class="label">Alamat *</label><textarea v-model="stockOutForm.shopee_address"
                                    class="input" rows="2"></textarea></div>
                            <div><label class="label">No. Resi *</label>
                                <div class="flex gap-2">
                                    <input v-model="stockOutForm.shopee_tracking_no" class="input font-mono" />
                                    <button @click="startScanner()" type="button" class="btn btn-secondary px-4">
                                        <ScanBarcode :size="18" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Keluar -->
                    <template v-if="selectedStockOutCategory === 'keluar'">
                        <div class="bg-surface-700/30 p-4 rounded-xl border mb-4">
                            <div class="space-y-3 max-h-80 overflow-y-auto">
                                <div v-for="(item, idx) in props.selectedItems" :key="item.id"
                                    class="bg-surface-800 p-3 rounded-xl border flex justify-between">
                                    <span class="text-sm">{{ item.product?.name }}</span>
                                    <div v-if="item.type === 'non-hp'" class="flex gap-2 items-center">
                                        <input type="number" v-model="item.out_quantity"
                                            class="w-16 bg-surface-900 border p-1 text-center" />
                                    </div>
                                    <span v-else class="text-xs">{{ item.imei }}</span>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="label">Sub Kategori</label>
                            <select v-model="stockOutForm.sub_category" class="input">
                                <option value="Giveaway customer">Giveaway customer</option>
                                <option value="Hadiah">Hadiah</option>
                                <option value="Inventaris">Inventaris</option>
                                <option value="Promo">Promo</option>
                            </select>
                        </div>
                        <div class="mt-3"><label class="label">Penerima *</label><input
                                v-model="stockOutForm.receiver_name" class="input" /></div>
                        <div class="mt-3"><label class="label">Catatan</label><textarea v-model="stockOutForm.notes"
                                class="input"></textarea></div>
                    </template>
                </div>
            </div>

            <div v-if="selectedStockOutCategory" class="p-6 border-t border-surface-700">
                <button @click="submitStockOut" :disabled="!canSubmitStockOut || isSubmitting"
                    class="btn btn-primary w-full h-12 font-bold disabled:opacity-30">
                    <Loader2 v-if="isSubmitting" :size="20" class="animate-spin mr-2" />
                    {{ isSubmitting ? 'Memproses...' : 'Konfirmasi Keluar Stok' }}
                </button>
            </div>
        </div>

        <!-- Scanner Modal component within -->
        <div v-if="isScanning" class="fixed inset-0 bg-black/95 z-60 flex flex-col items-center justify-center p-4">
            <div class="relative w-full max-w-lg bg-surface-800 rounded-2xl overflow-hidden p-4">
                <button @click="stopScanner" class="absolute top-4 right-4 text-white">
                    <X :size="24" />
                </button>
                <div :id="scannerContainerId" class="w-full aspect-video bg-black mt-4"></div>
            </div>
        </div>

        <!-- PIN Modal Component -->
        <PinModal :show="showPinModal" mode="verify" title="Verifikasi PIN Transaksi" @close="showPinModal = false"
            @success="handlePinSuccess" />
    </div>
</template>
