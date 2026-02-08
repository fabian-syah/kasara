<script setup>
import { ref, onMounted, computed, watch, nextTick } from 'vue';
import { useRouter } from "vue-router";
import { useToast } from "../../composables/useToast";
import { distributors as distributorsApi, inventory as inventoryApi, users as usersApi, brands as brandsApi, productTypes as productTypesApi, products as productsApi } from "../../api/axios";
import { useAuthStore } from "../../store/auth";
import {
    Package,
    Save,
    Plus,
    Trash2,
    Smartphone,
    Box,
    Truck,
    Building,
    Loader2,
    ScanBarcode,
    ChevronRight,
    ChevronLeft,
    CheckCircle2,
    XCircle,
    List
} from "lucide-vue-next";

const toast = useToast();
const router = useRouter();
const authStore = useAuthStore();

// State
const isLoading = ref(false);
const isSubmitting = ref(false);
const distributors = ref([]);
const currentStep = ref(1);
const isManualDistributor = ref(false);
const newDistributorName = ref("");

const targetUsers = ref([]);
const placementLabel = ref("");

// Step 1: Placement
const placementType = ref("branch");
const placementId = ref(null);
const placementName = computed(() => placementLabel.value || "Lokasi Belum Terpilih");

// Step 2: Item Type
const itemType = ref("hp");

// Step 3: Distributor
const selectedDistributor = ref("");
const selectedDistributorName = computed(() => {
    if (isManualDistributor.value) return newDistributorName.value || 'Distributor Baru';
    const d = distributors.value.find(x => x.id === selectedDistributor.value);
    return d ? d.name : '-';
});

// Step 4: Product & Details
const selectedProduct = ref(null);
const products = ref([]);
const imeiRows = ref([
    { imei: "", condition: "new", cost_price: 0, selling_price: 0, color: "", ram: "", storage: "" }
]);
const nonHpForm = ref({ quantity: 1 });

// Hierarchical Selection State
const brands = ref([]);
const allowedTypes = ref([]);
const selectedBrand = ref(null);
const selectedTypeName = ref("");
const selectedRam = ref("");
const selectedStorage = ref("");

// Format Rupiah Helper
function formatRupiah(value) {
    if (!value) return "Rp 0";
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        minimumFractionDigits: 0
    }).format(value);
}

// Selection Logic
const filteredTypes = computed(() => {
    if (!selectedBrand.value) return [];
    return allowedTypes.value.filter(t => t.brand_id === selectedBrand.value);
});

const uniqueTypeNames = computed(() => Array.from(new Set(filteredTypes.value.map(t => t.name))));

const availableSpecs = computed(() => {
    if (!selectedTypeName.value) return { rams: [], storages: [] };
    const matching = allowedTypes.value.filter(t => t.name === selectedTypeName.value);

    const ramSet = new Set();
    const storageSet = new Set();

    matching.forEach(t => {
        if (t.ram) {
            t.ram.split(/[,/]+/).forEach(s => ramSet.add(s.trim()));
        }
        if (t.storage) {
            t.storage.split(/[,/]+/).forEach(s => storageSet.add(s.trim()));
        }
    });

    return {
        rams: Array.from(ramSet).sort((a, b) => parseInt(a) - parseInt(b)),
        storages: Array.from(storageSet).sort((a, b) => parseInt(a) - parseInt(b))
    };
});

import { debounce } from "../../utils/debounce";

// ... (state)

// --- PERBAIKAN: Gunakan Debounce pada API Lookup agar tidak lag saat ganti merk/tipe ---
const fetchProductMatch = debounce(async (brandId, typeName) => {
    if (!brandId || !typeName) {
        selectedProduct.value = null;
        return;
    }

    try {
        const brandObj = brands.value.find(b => b.id === brandId);
        const brandName = brandObj ? brandObj.name : "";

        // Panggil API hanya setelah user berhenti memilih/mengetik selama 300ms
        const response = await inventoryApi.getProductsLookup({
            type: 'hp',
            name: typeName
        });

        const matches = response.data;
        let found = matches.find(p => {
            const dbBrand = (p.brand || "").toLowerCase().trim();
            const selBrand = brandName.toLowerCase().trim();
            const dbName = p.name.toLowerCase().trim();
            const selName = typeName.toLowerCase().trim();
            return dbBrand === selBrand && dbName === selName;
        });

        if (!found && matches.length > 0) found = matches[0];
        selectedProduct.value = found ? found.id : null;

    } catch (e) {
        console.error("Gagal lookup product", e);
        selectedProduct.value = null;
    }
}, 300);

// --- REFACTORED: Single Bulk Input Logic ---
const bulkImeiText = ref("");
const batchDetails = ref({
    condition: "new",
    cost_price: 0,
    selling_price: 0
});

// Parsed IMEIs for count/validation
const parsedImeis = computed(() => {
    return bulkImeiText.value.split(/[^a-zA-Z0-9]+/).filter(s => s.length >= 5);
});

// NEW: Optimized Watcher
watch([selectedBrand, selectedTypeName], ([newBrand, newType]) => {
    fetchProductMatch(newBrand, newType);
});

watch(selectedBrand, () => { selectedTypeName.value = ""; selectedRam.value = ""; selectedStorage.value = ""; });
watch(selectedTypeName, () => { selectedRam.value = ""; selectedStorage.value = ""; });

const canNext = computed(() => {
    if (currentStep.value === 1) return !!placementId.value;
    if (currentStep.value === 2) return !!itemType.value;
    if (currentStep.value === 3) return isManualDistributor.value ? newDistributorName.value.length >= 2 : !!selectedDistributor.value;
    return false;
});

// CARI DAN GANTI LOGIKA INI DI StockIn.vue
const costPriceDisplay = computed({
    get: () => batchDetails.value.cost_price ? formatRupiah(batchDetails.value.cost_price).replace('Rp', '').trim() : '',
    set: (val) => {
        // Remove non-numeric chars
        const num = parseInt(val.replace(/[^\d]/g, ''));
        batchDetails.value.cost_price = isNaN(num) ? 0 : num;
    }
});

const sellingPriceDisplay = computed({
    get: () => batchDetails.value.selling_price ? formatRupiah(batchDetails.value.selling_price).replace('Rp', '').trim() : '',
    set: (val) => {
        const num = parseInt(val.replace(/[^\d]/g, ''));
        batchDetails.value.selling_price = isNaN(num) ? 0 : num;
    }
});

const canSubmit = computed(() => {
    if (!selectedTypeName.value) return false;

    if (itemType.value === 'hp') {
        if (!selectedStorage.value) return false;
        // Check if we have at least one valid IMEI in the bulk text
        if (parsedImeis.value.length === 0) return false;
        // Check prices
        return batchDetails.value.cost_price > 0 && batchDetails.value.selling_price > 0;
    }
    return nonHpForm.value.quantity > 0;
});

// CARI DAN GANTI FUNGSI submitStockIn AGAR SELALU KIRIM ID MESKIPUN MAPPING


function nextStep() {
    if (canNext.value) currentStep.value++;
}

function prevStep() {
    if (currentStep.value > 1) currentStep.value--;
}

const showCreateAccountModal = ref(false);
const newAccountName = ref("");
const isCreatingAccount = ref(false);

async function fetchInitialData() {
    isLoading.value = true;
    try {
        const [dist, user, brd, typ, prd] = await Promise.all([
            distributorsApi.list(),
            usersApi.list({ role: 'inventory' }), // FILTER BY ROLE
            brandsApi.list(),
            productTypesApi.list(),
            inventoryApi.getProductsLookup({ type: 'hp' })
        ]);
        distributors.value = dist.data.data;
        brands.value = brd.data.data || brd.data;
        allowedTypes.value = typ.data.data || typ.data;
        products.value = prd.data;
        targetUsers.value = (user.data.data || user.data);
    } catch (e) { toast.error("Gagal load data"); }
    finally { isLoading.value = false; }
}

async function createInventoryAccount() {
    if (!newAccountName.value) return;
    isCreatingAccount.value = true;
    try {
        await inventoryApi.createAccount({ name: newAccountName.value });
        toast.success("Akun inventory berhasil dibuat!");
        showCreateAccountModal.value = false;
        newAccountName.value = "";
        fetchInitialData(); // Reload list
    } catch (e) {
        console.error("Create account error:", e);
        const errMsg = e.response?.data?.message || "Gagal membuat akun (" + (e.response?.status || 'Unknown') + ")";
        toast.error(errMsg);
    } finally {
        isCreatingAccount.value = false;
    }
}

// EDIT ACCOUNT LOGIC
import { Camera, Edit2 } from "lucide-vue-next";

const showEditAccountModal = ref(false);
const editForm = ref({
    id: null,
    name: "",
    phone: "",
    photo_inventory: null,
    photo_preview: null
});
const isUpdatingAccount = ref(false);
const photoInput = ref(null);

function openEditModal(user, event) {
    event.stopPropagation(); // Prevent card selection
    editForm.value = {
        id: user.id,
        name: user.full_name || user.name,
        phone: user.phone || "",
        photo_inventory: null,
        photo_preview: user.photo_inventory ? `${import.meta.env.VITE_API_URL}/storage/${user.photo_inventory}` : null
    };
    showEditAccountModal.value = true;
}

function handlePhotoChange(event) {
    const file = event.target.files[0];
    if (file) {
        editForm.value.photo_inventory = file;
        editForm.value.photo_preview = URL.createObjectURL(file);
    }
}

async function updateInventoryAccount() {
    isUpdatingAccount.value = true;
    try {
        const formData = new FormData();
        formData.append('phone', editForm.value.phone);
        if (editForm.value.photo_inventory) {
            formData.append('photo_inventory', editForm.value.photo_inventory);
        }

        await inventoryApi.updateAccount(editForm.value.id, formData);
        toast.success("Akun berhasil diupdate!");
        showEditAccountModal.value = false;
        fetchInitialData();
    } catch (e) {
        console.error("Update error:", e);
        toast.error(e.response?.data?.message || "Gagal update akun");
    } finally {
        isUpdatingAccount.value = false;
    }
}




const selectedInventoryUserId = ref(null);

function selectUserPlacement(user) {
    placementId.value = user.online_shop_id || user.warehouse_id || user.branch_id;
    placementType.value = user.online_shop_id ? 'online_shop' : (user.warehouse_id ? 'warehouse' : 'branch');
    placementLabel.value = user.full_name || user.name;
    selectedInventoryUserId.value = user.id; // Capture Inventory Account ID
    nextStep();
}

async function submitStockIn() {
    if (!canSubmit.value) return;
    isSubmitting.value = true;
    try {
        let productId = selectedProduct.value;
        if (!productId && selectedTypeName.value) {
            let fallback = products.value.find(p =>
                p.name.toLowerCase().includes(selectedTypeName.value.toLowerCase())
            );

            if (!fallback) {
                try {
                    const resp = await inventoryApi.getProductsLookup({
                        type: 'hp',
                        name: selectedTypeName.value
                    });
                    if (resp.data.length > 0) {
                        fallback = resp.data[0];
                    } else {
                        // AUTO CREATE PRODUCT
                        const brandObj = brands.value.find(b => b.id === selectedBrand.value);
                        const brandName = brandObj ? brandObj.name : "Unknown";

                        const createResp = await productsApi.create({
                            name: selectedTypeName.value,
                            brand: brandName,
                            type: itemType.value,
                            brand_id: !selectedProduct.value ? selectedBrand.value : null,
                            type_name: !selectedProduct.value ? selectedTypeName.value : null,
                            ram: selectedRam.value || null,
                            storage: selectedStorage.value || null,
                            category: itemType.value === 'hp' ? 'HP / Gadget' : 'NON HP / NON IMEI',
                            has_imei: itemType.value === 'hp',
                            sku: null
                        });

                        fallback = createResp.data;
                        toast.success(`Produk pattern "${selectedTypeName.value}" otomatis dibuat.`);
                    }
                } catch (err) {
                    console.error("API Lookup/Create failed", err);
                    toast.error("Gagal membuat produk otomatis: " + (err.response?.data?.message || err.message));
                    isSubmitting.value = false;
                    return;
                }
            }
            productId = fallback ? fallback.id : null;
        }

        if (!productId) {
            toast.error("Produk tidak ditemukan. Pastikan nama Tipe sesuai.");
            isSubmitting.value = false;
            return;
        }

        const payload = {
            product_id: productId,
            distributor_id: isManualDistributor.value ? null : selectedDistributor.value,
            new_distributor_name: isManualDistributor.value ? newDistributorName.value : null,
            type: itemType.value,
            placement_type: placementType.value,
            placement_id: placementId.value,
            inventory_user_id: selectedInventoryUserId.value,
        };

        if (itemType.value === 'hp') {
            payload.storage = selectedStorage.value;
            // Generate Array from Bulk Text
            payload.imeis = parsedImeis.value.map(imei => ({
                imei: imei,
                condition: batchDetails.value.condition,
                cost_price: batchDetails.value.cost_price,
                selling_price: batchDetails.value.selling_price,
                color: "",
                ram: "",
                storage: ""
            }));
        } else {
            payload.quantity = nonHpForm.value.quantity;
        }

        const response = await inventoryApi.stockIn(payload);

        // Handle partial success/duplicates if any
        if (response.data.duplicates && response.data.duplicates.length > 0) {
            toast.warning(`Berhasil: ${response.data.inserted_count}. Gagal (Duplikat): ${response.data.duplicates.length}`);
        } else {
            toast.success("Stok berhasil ditambahkan!");
        }

        router.push('/inventory');

    } catch (error) {
        console.error(error);
        toast.error(error.response?.data?.message || "Gagal input stok");
    } finally {
        isSubmitting.value = false;
    }
}


onMounted(fetchInitialData);
</script>

<template>
    <div class="space-y-6 animate-in fade-in max-w-4xl mx-auto pb-20">
        <h1 class="text-2xl font-bold text-text-primary flex items-center gap-2">
            <Box :size="28" class="text-blue-500" /> Input Barang Masuk
        </h1>

        <div class="flex items-center justify-between mb-8 px-4">
            <div v-for="step in [1, 2, 3, 4]" :key="step" class="flex items-center">
                <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm"
                    :class="currentStep >= step ? 'bg-primary-500 text-white' : 'bg-surface-800 text-text-secondary'">{{
                        step }}</div>
                <div v-if="step < 4" class="w-16 h-1 mx-2 rounded-full"
                    :class="currentStep > step ? 'bg-primary-500' : 'bg-surface-800'"></div>
            </div>
        </div>

        <div class="card p-8 border-t-4 border-t-primary-500 bg-surface-800 rounded-2xl shadow-2xl">
            <div v-if="currentStep === 1" class="animate-in slide-in-from-right">
                <div v-if="targetUsers.length === 0" class="text-center py-10">
                    <div
                        class="bg-red-500/10 border border-red-500/20 text-red-500 p-6 rounded-2xl max-w-lg mx-auto mb-6">
                        <h3 class="font-bold text-lg mb-2">Belum Ada Akun Inventory</h3>
                        <p class="text-sm opacity-80">Anda belum memiliki akun khusus inventory untuk cabang/lokasi ini.
                            Silahkan buat terlebih dahulu untuk melanjutkan stok in.</p>
                    </div>
                    <button @click="showCreateAccountModal = true" class="btn btn-primary px-8 py-4 rounded-xl">
                        <Plus :size="20" class="mr-2" /> Buat Akun Inventory Baru
                    </button>
                </div>

                <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div v-for="user in targetUsers" :key="user.id" @click="selectUserPlacement(user)"
                        class="p-5 rounded-2xl border border-surface-700 bg-surface-900 cursor-pointer hover:border-primary-500 transition-all relative">
                        <div v-if="placementLabel === (user.full_name || user.name)"
                            class="absolute top-3 right-3 text-primary-500">
                            <CheckCircle2 :size="24" />
                        </div>

                        <!-- EDIT BUTTON -->
                        <div v-if="authStore.user?.id === user.created_by?.id || authStore.user?.id === user.created_by"
                            @click="openEditModal(user, $event)"
                            class="absolute top-3 right-10 p-1 hover:bg-surface-800 rounded-full text-text-secondary hover:text-primary-500 transition-colors z-10">
                            <Edit2 :size="16" />
                        </div>

                        <!-- EDIT BUTTON -->
                        <div v-if="authStore.user?.id === user.created_by?.id || authStore.user?.id === user.created_by"
                            @click="openEditModal(user, $event)"
                            class="absolute top-3 right-10 p-1 hover:bg-surface-800 rounded-full text-text-secondary hover:text-primary-500 transition-colors z-10">
                            <Edit2 :size="16" />
                        </div>
                        <div class="flex items-center gap-4">
                            <div
                                class="w-12 h-12 rounded-xl bg-primary-500 flex items-center justify-center text-white font-bold">
                                {{ user.name[0] }}</div>
                            <div>
                                <h3 class="font-bold text-text-primary">{{ user.full_name || user.name }}</h3>
                                <div class="flex flex-col">
                                    <span class="text-xs text-text-secondary uppercase">{{ user.roles?.[0]?.name
                                    }}</span>
                                    <span v-if="user.created_by" class="text-[10px] text-text-secondary/70">
                                        by: {{ user.created_by.username }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Always Allow Creating New Account -->
                    <button @click="showCreateAccountModal = true"
                        class="p-5 rounded-2xl border-2 border-dashed border-surface-700 hover:border-primary-500 bg-surface-900/50 hover:bg-surface-800 transition-all flex flex-col items-center justify-center gap-2 group min-h-[88px]">
                        <div
                            class="h-10 w-10 rounded-full bg-surface-800 group-hover:bg-primary-500/20 flex items-center justify-center transition-colors">
                            <Plus :size="24" class="text-text-secondary group-hover:text-primary-500" />
                        </div>
                        <span class="font-bold text-text-secondary group-hover:text-primary-500 text-sm">Buat Akun
                            Baru</span>
                    </button>
                </div>

                <!-- Modal Create Account -->
                <div v-if="showCreateAccountModal"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
                    <div
                        class="bg-surface-900 border border-surface-700 p-8 rounded-3xl w-full max-w-md shadow-2xl animate-in zoom-in-95">
                        <h3 class="text-xl font-bold text-white mb-4">Buat Akun Inventory</h3>
                        <p class="text-text-secondary text-sm mb-6">Akun ini akan digunakan khusus untuk pencatatan
                            keluar masuk barang di lokasi ini.</p>

                        <div class="space-y-4">
                            <div>
                                <label class="label text-xs uppercase">Nama Akun / Bagian</label>
                                <input v-model="newAccountName" class="input bg-surface-800"
                                    placeholder="Contoh: Gudang Fisik A" autoFocus />
                            </div>
                            <div class="flex justify-end gap-3 mt-6">
                                <button @click="showCreateAccountModal = false"
                                    class="btn btn-secondary px-6 rounded-xl">Batal</button>
                                <button @click="createInventoryAccount" :disabled="!newAccountName || isCreatingAccount"
                                    class="btn btn-primary px-6 rounded-xl">
                                    <Loader2 v-if="isCreatingAccount" class="animate-spin mr-2" :size="16" />
                                    {{ isCreatingAccount ? 'Membuat...' : 'Buat Akun' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Edit Account -->
                <div v-if="showEditAccountModal"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
                    <div
                        class="bg-surface-900 border border-surface-700 p-8 rounded-3xl w-full max-w-md shadow-2xl animate-in zoom-in-95">
                        <h3 class="text-xl font-bold text-white mb-4">Edit Akun Inventory</h3>

                        <div class="space-y-4">
                            <!-- Photo Upload -->
                            <div class="flex justify-center mb-6">
                                <div class="relative group cursor-pointer" @click="photoInput.click()">
                                    <div
                                        class="w-24 h-24 rounded-full bg-surface-800 border-2 border-surface-700 overflow-hidden flex items-center justify-center">
                                        <img v-if="editForm.photo_preview" :src="editForm.photo_preview"
                                            class="w-full h-full object-cover" />
                                        <div v-else class="text-text-secondary">
                                            <Camera :size="32" />
                                        </div>
                                    </div>
                                    <div
                                        class="absolute inset-0 bg-black/50 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                        <Edit2 :size="20" class="text-white" />
                                    </div>
                                    <input type="file" ref="photoInput" class="hidden" accept="image/*"
                                        @change="handlePhotoChange" />
                                </div>
                            </div>

                            <div>
                                <label class="label text-xs uppercase">Nama Akun (Terkunci)</label>
                                <input v-model="editForm.name"
                                    class="input bg-surface-800 opacity-50 cursor-not-allowed" disabled />
                                <p class="text-[10px] text-text-secondary mt-1">*Nama akun hanya bisa diubah oleh Super
                                    Admin</p>
                            </div>

                            <div>
                                <label class="label text-xs uppercase">No HP / WhatsApp</label>
                                <input v-model="editForm.phone" class="input bg-surface-800" placeholder="08..." />
                            </div>

                            <div class="flex justify-end gap-3 mt-6">
                                <button @click="showEditAccountModal = false"
                                    class="btn btn-secondary px-6 rounded-xl">Batal</button>
                                <button @click="updateInventoryAccount" :disabled="isUpdatingAccount"
                                    class="btn btn-primary px-6 rounded-xl">
                                    <Loader2 v-if="isUpdatingAccount" class="animate-spin mr-2" :size="16" />
                                    {{ isUpdatingAccount ? 'Menyimpan...' : 'Simpan Perubahan' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="currentStep === 2" class="grid grid-cols-1 md:grid-cols-2 gap-6 animate-in slide-in-from-right">
                <button @click="itemType = 'hp'"
                    class="p-8 rounded-3xl border-2 transition-all flex flex-col items-center gap-4"
                    :class="itemType === 'hp' ? 'border-primary-500 bg-primary-500/10' : 'border-surface-700 bg-surface-900'">
                    <Smartphone :size="48" class="text-primary-500" /><span class="font-bold">HP / IMEI</span>
                </button>
                <button @click="itemType = 'non-hp'"
                    class="p-8 rounded-3xl border-2 transition-all flex flex-col items-center gap-4"
                    :class="itemType === 'non-hp' ? 'border-primary-500 bg-primary-500/10' : 'border-surface-700 bg-surface-900'">
                    <Box :size="48" class="text-primary-500" /><span class="font-bold">NON HP / NON IMEI</span>
                </button>
            </div>

            <div v-if="currentStep === 3"
                class="bg-surface-900 p-8 rounded-3xl border border-surface-700 animate-in slide-in-from-right">
                <label class="label text-xs uppercase font-black text-text-secondary mb-4">Pemasok</label>
                <div class="flex gap-3">
                    <select v-if="!isManualDistributor" v-model="selectedDistributor"
                        class="input flex-1 bg-surface-800">
                        <option value="" disabled>-- Pilih Daftar --</option>
                        <option v-for="d in distributors" :key="d.id" :value="d.id">{{ d.name }}</option>
                    </select>
                    <input v-else v-model="newDistributorName" placeholder="Nama baru..."
                        class="input flex-1 bg-surface-800" />
                    <button @click="isManualDistributor = !isManualDistributor" class="btn btn-outline w-14 h-14"
                        :class="isManualDistributor ? 'text-primary-500 border-primary-500' : ''">
                        <component :is="isManualDistributor ? List : Plus" />
                    </button>
                </div>
            </div>

            <div v-if="currentStep === 4" class="space-y-6 animate-in slide-in-from-right">
                <div
                    class="grid grid-cols-3 gap-3 bg-surface-900 rounded-2xl p-4 border border-surface-700 text-[10px] font-bold uppercase tracking-widest text-text-secondary">
                    <div class="px-2">Akun: <span class="text-text-primary">{{ placementName }}</span></div>
                    <div class="px-2 border-l border-surface-700">Tipe: <span class="text-text-primary">{{ itemType
                    }}</span></div>
                    <div class="px-2 border-l border-surface-700">Dist: <span class="text-text-primary">{{
                        selectedDistributorName }}</span></div>
                </div>

                <div class="grid grid-cols-2 gap-5 bg-surface-900/50 p-8 rounded-3xl border border-surface-700">
                    <div><label class="label text-[10px] uppercase">Merk</label><select v-model="selectedBrand"
                            class="input bg-surface-900">
                            <option :value="null">-- Pilih Merk --</option>
                            <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select></div>
                    <div><label class="label text-[10px] uppercase">Tipe</label><select v-model="selectedTypeName"
                            :disabled="!selectedBrand" class="input bg-surface-900 disabled:opacity-30">
                            <option value="">-- Pilih Tipe --</option>
                            <option v-for="n in uniqueTypeNames" :key="n" :value="n">{{ n }}</option>
                        </select></div>

                    <!-- Kapasitas (HP Only) -->
                    <div v-if="itemType === 'hp'"><label class="label text-[10px] uppercase">Kapasitas</label><select
                            v-model="selectedStorage" :disabled="!selectedTypeName"
                            class="input bg-surface-900 disabled:opacity-30">
                            <option value="">-- Semua --</option>
                            <option v-for="s in availableSpecs.storages" :key="s" :value="s">{{ s }}</option>
                        </select>
                    </div>

                    <!-- Quantity (Non-HP Only) -->
                    <div v-if="itemType === 'non-hp'">
                        <label class="label text-[10px] uppercase">Jumlah Stok (Pcs/Unit)</label>
                        <input v-model.number="nonHpForm.quantity" type="number" min="1"
                            class="input bg-surface-900 h-[42px]" placeholder="Jumlah..." />
                    </div>

                    <div v-if="!selectedProduct && selectedTypeName"
                        class="col-span-full text-red-400 text-[10px] animate-pulse">
                        <XCircle :size="12" class="inline mr-1" /> Spek belum terdaftar, tapi sistem akan mencoba
                        mapping otomatis ke Tipe Model terdekat.
                    </div>
                </div>

                <div v-if="itemType === 'hp'" class="space-y-6">
                    <!-- Global Settings for Batch -->
                    <div
                        class="grid grid-cols-1 md:grid-cols-3 gap-5 bg-surface-900/50 p-6 rounded-3xl border border-surface-700">
                        <div>
                            <label class="label text-[10px] uppercase">Kondisi (Batch)</label>
                            <select v-model="batchDetails.condition" class="input bg-surface-900 h-10 text-sm">
                                <option value="new">Baru</option>
                                <option value="second">Bekas</option>
                            </select>
                        </div>
                        <div>
                            <label class="label text-[10px] uppercase text-emerald-500">Modal Satuan</label>
                            <div class="relative">
                                <span
                                    class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary text-sm">Rp</span>
                                <input v-model="costPriceDisplay" type="text"
                                    class="input bg-surface-900 h-10 text-sm pl-10" placeholder="0" />
                            </div>
                        </div>
                        <div>
                            <label class="label text-[10px] uppercase text-blue-500">Jual Satuan</label>
                            <div class="relative">
                                <span
                                    class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary text-sm">Rp</span>
                                <input v-model="sellingPriceDisplay" type="text"
                                    class="input bg-surface-900 h-10 text-sm pl-10" placeholder="0" />
                            </div>
                        </div>
                    </div>

                    <!-- Single Bulk Textarea -->
                    <div class="space-y-2">
                        <label class="label text-sm uppercase font-bold flex justify-between">
                            <span>Input IMEI (Scan / Copy-Paste)</span>
                            <span
                                class="text-xs font-normal text-text-secondary bg-surface-800 px-2 py-1 rounded-lg">Total:
                                {{ parsedImeis.length }} items</span>
                        </label>
                        <textarea v-model="bulkImeiText" rows="8"
                            class="input bg-surface-900 font-mono text-sm leading-relaxed p-4 w-full rounded-2xl border-2 border-surface-700 focus:border-primary-500 transition-all placeholder:text-text-secondary/30"
                            placeholder="Contoh: &#10;123456789012345&#10;987654321098765&#10;Paste banyak IMEI sekaligus disini..."></textarea>
                        <p class="text-[10px] text-text-secondary flex items-center gap-1">
                            <CheckCircle2 :size="12" class="text-emerald-500" />
                            Otomatis memisahkan spasi, koma, enter, atau strip.
                        </p>
                    </div>
                </div>
            </div>

            <div class="mt-auto pt-8 border-t border-surface-700 flex justify-between gap-4">
                <button v-if="currentStep > 1" @click="prevStep"
                    class="btn btn-secondary px-8 h-14 rounded-2xl uppercase text-[10px] tracking-widest">
                    <ChevronLeft :size="18" /> Kembali
                </button>
                <div v-else></div>
                <button v-if="currentStep < 4" @click="nextStep" :disabled="!canNext"
                    class="btn btn-primary px-10 h-14 rounded-2xl uppercase text-[10px] tracking-widest font-black">Lanjut
                    <ChevronRight :size="18" />
                </button>
                <button v-if="currentStep === 4" @click="submitStockIn" :disabled="!canSubmit || isSubmitting"
                    class="btn btn-primary px-10 h-14 rounded-2xl uppercase text-[10px] tracking-widest font-black shadow-xl shadow-emerald-600/20">
                    <Loader2 v-if="isSubmitting" class="animate-spin mr-2" />
                    {{ isSubmitting ? 'Proses...' : 'Selesai & Simpan' }}
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
@reference "../../style.css";

.label {
    @apply block text-text-secondary mb-2 font-semibold;
}

.input {
    @apply w-full border border-surface-700 rounded-2xl px-5 text-text-primary focus:outline-none focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 transition-all placeholder:text-surface-600 shadow-inner;
}

.btn {
    @apply font-bold transition-all duration-300 disabled:opacity-20 disabled:cursor-not-allowed flex items-center justify-center;
}

.btn-primary {
    @apply bg-primary-600 hover:bg-primary-500 text-white;
}

.btn-secondary {
    @apply bg-surface-700 hover:bg-surface-600 text-text-secondary hover:text-white border border-surface-600;
}

.btn-outline {
    @apply border border-surface-700 hover:border-primary-500/50 text-text-secondary hover:text-primary-500;
}

input::-webkit-outer-spin-button,
input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
</style>