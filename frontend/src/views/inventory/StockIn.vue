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
    List,
    Camera,
    Edit2,
    X,
    AlertTriangle,
    Shield,
} from "lucide-vue-next";
import PinModal from "../../components/modals/PinModal.vue";
import { debounce } from "../../utils/debounce";
import { parseCurrency, formatNumber } from "../../utils/formatters";

const toast = useToast();
const router = useRouter();
const authStore = useAuthStore();
const isDistributorRole = computed(() => ['distributor', 'distribution'].includes((authStore.userRole || '').toLowerCase()));
const apiUrl = import.meta.env.VITE_API_URL || 'https://api.stokps.com/api';
// Remove '/api' from the end of apiUrl to get the base URL for storage
const storageUrl = apiUrl.replace(/\/api\/?$/, '');

// State
const isLoading = ref(false);
const isSubmitting = ref(false);
const distributors = ref([]);
const currentStep = ref(1);
const isManualDistributor = ref(false);
const newDistributorName = ref("");
const isRestoring = ref(true);

// PIN State
const showPinModal = ref(false);

const targetUsers = ref([]);
const selectedInventoryUserPinEnabled = ref(false);
const placementLabel = ref("");
// Step 1: Placement
const placementType = ref("branch");
const placementId = ref(null);
const placementName = computed(() => placementLabel.value || "Lokasi Belum Terpilih");

// Hierarchical Selection State
const brands = ref([]);
const allowedTypes = ref([]);
const isImeiCategory = (cat) => ['imei', 'HP / Gadget'].includes(cat);

// Format Rupiah Helper
function formatRupiah(value) {
    if (!value) return "Rp 0";
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        minimumFractionDigits: 0
    }).format(value);
}

// Step 2: Item Type
const itemType = ref("hp");

// Step 3: Distributor
const selectedDistributor = ref("");
const selectedDistributorName = computed(() => {
    if (isManualDistributor.value) return newDistributorName.value || 'Distributor Baru';
    const d = distributors.value.find(x => x.id === selectedDistributor.value);
    if (!d && isDistributorRole.value && authStore.user) {
        return authStore.user.name || authStore.user.full_name || 'Distributor (Anda)';
    }
    return d ? d.name : '-';
});

// Step 4: Product & Details
const selectedProduct = ref(null);
const products = ref([]);
const imeiRows = ref([
    { imei: "", condition: "new", cost_price: 0, selling_price: 0, color: "", ram: "", storage: "" }
]);
const nonHpForm = ref({ quantity: 1 });

// NEW: Sub-form for Multiple Non-HP Items
const nonHpItems = ref([
    {
        brand_id: null,
        type_name: "",
        quantity: 1,
        cost_price: 0,
        selling_price: 0,
        selling_price_display: "",
        filteredTypes: [],
        uniqueTypeNames: [],
        isLoadingTypes: false,
        notes: ""
    }
]);

// Persistence Logic
const getStorageKey = () => `temp_stock_in_state_${authStore.user?.id || 'guest'}`;

const persistState = () => {
    if (isRestoring.value) return;
    localStorage.setItem(getStorageKey(), JSON.stringify({
        currentStep: currentStep.value,
        itemType: itemType.value,
        selectedDistributor: selectedDistributor.value,
        selectedInventoryUserId: selectedInventoryUserId.value,
        placementId: placementId.value,
        placementType: placementType.value,
        placementLabel: placementLabel.value,
        hpItems: hpItems.value,
        nonHpItems: nonHpItems.value,
        isManualDistributor: isManualDistributor.value,
        newDistributorName: newDistributorName.value
    }));
};

async function restoreDraft() {
    const saved = localStorage.getItem(getStorageKey());
    if (saved) {
        try {
            isRestoring.value = true;
            const data = JSON.parse(saved);
            currentStep.value = data.currentStep || 1;
            itemType.value = data.itemType || "hp";
            selectedDistributor.value = data.selectedDistributor || "";
            selectedInventoryUserId.value = data.selectedInventoryUserId || null;
            placementId.value = data.placementId || null;
            placementType.value = data.placementType || "branch";
            placementLabel.value = data.placementLabel || "";
            if (data.hpItems) hpItems.value = data.hpItems;
            if (data.nonHpItems) nonHpItems.value = data.nonHpItems;
            isManualDistributor.value = !!data.isManualDistributor;
            newDistributorName.value = data.newDistributorName || "";
            
            await nextTick();
            setTimeout(() => {
                isRestoring.value = false;
            }, 500);
        } catch (e) {
            isRestoring.value = false;
        }
    } else {
        isRestoring.value = false;
    }
}

watch(() => authStore.user?.id, (newId) => {
    if (newId) restoreDraft();
}, { immediate: true });



const addNonHpItem = () => {
    nonHpItems.value.push({
        brand_id: null,
        type_name: "",
        quantity: 1,
        cost_price: 0,
        selling_price: 0,
        selling_price_display: "",
        filteredTypes: [],
        uniqueTypeNames: [],
        isLoadingTypes: false,
        notes: ""
    });
};

// updateNonHpItemPrice removed in favor of v-money sync syntax

// Only show brands that have matching types for the selected itemType AND are allowed by the selected distributor
const filteredBrands = computed(() => {
    const brandIdsFromTypes = new Set(
        allowedTypes.value
            .filter(t => itemType.value === 'hp' ? isImeiCategory(t.category) : !isImeiCategory(t.category))
            .map(t => t.brand_id)
    );

    const dist = distributors.value.find(d => d.id === selectedDistributor.value);
    const restrictedBrandIds = dist?.allowed_brands;

    return brands.value.filter(b => {
        const hasTypes = brandIdsFromTypes.has(b.id);
        const isAllowedByDist = !restrictedBrandIds || restrictedBrandIds.length === 0 || restrictedBrandIds.includes(b.id);
        return hasTypes && isAllowedByDist;
    });
});

const getNonHpCategory = (item) => {
    if (!item.type_name || !item.filteredTypes) return null;
    const type = item.filteredTypes.find(t => t.name === item.type_name);
    return type?.non_imei_category || null;
};

const removeNonHpItem = (index) => {
    if (nonHpItems.value.length > 1) {
        nonHpItems.value.splice(index, 1);
    }
};

// Automatically clear invalid brand selections when distributor changes
watch(selectedDistributor, () => {
    const validBrandIds = new Set(filteredBrands.value.map(b => b.id));

    nonHpItems.value.forEach(item => {
        if (item.brand_id && !validBrandIds.has(item.brand_id)) {
            item.brand_id = null;
            item.type_name = "";
        }
    });

    hpItems.value.forEach(item => {
        if (item.brand_id && !validBrandIds.has(item.brand_id)) {
            item.brand_id = null;
            item.type_name = "";
        }
    });
});

const handleBrandChangeNonHp = (index) => {
    const item = nonHpItems.value[index];
    item.type_name = "";
    if (!item.brand_id) {
        item.filteredTypes = [];
        item.uniqueTypeNames = [];
        return;
    }

    // Filter types locally based on brand_id
    item.filteredTypes = allowedTypes.value.filter(t =>
        t.brand_id === item.brand_id && !isImeiCategory(t.category)
    );
    item.uniqueTypeNames = Array.from(new Set(item.filteredTypes.map(t => t.name)));
};

// NEW: Sub-form for Multiple HP Items
const hpItems = ref([
    {
        brand_id: null,
        type_name: "",
        capacity: "",
        ram: "",
        storage: "",
        condition: "new",
        cost_price: 0,
        selling_price: 0,
        bulkImeiText: "",
        parsedImeis: [],
        uniqueTypeNames: [],
        combinations: [],
        suggestedSellingPrice: 0,
        product_id: null,
        notes: ""
    }
]);

const addHpItem = () => {
    hpItems.value.push({
        brand_id: null,
        type_name: "",
        capacity: "",
        ram: "",
        storage: "",
        condition: "new",
        cost_price: 0,
        selling_price: 0,
        bulkImeiText: "",
        parsedImeis: [],
        uniqueTypeNames: [],
        combinations: [],
        suggestedSellingPrice: 0,
        product_id: null,
        notes: ""
    });
};

const removeHpItem = (index) => {
    if (hpItems.value.length > 1) {
        hpItems.value.splice(index, 1);
    }
};

const handleImeiInput = (index) => {
    const item = hpItems.value[index];
    // Sanitize: allow alphanumeric and whitespace
    item.bulkImeiText = item.bulkImeiText.replace(/[^a-zA-Z0-9\s,;-]/g, '');
    item.parsedImeis = item.bulkImeiText.split(/[^a-zA-Z0-9]+/).filter(s => s.length >= 5);
};

const handleBrandChangeHp = (index) => {
    const item = hpItems.value[index];
    item.type_name = "";
    item.capacity = "";
    item.ram = "";
    item.storage = "";
    item.product_id = null;

    if (!item.brand_id) {
        item.uniqueTypeNames = [];
        return;
    }

    const types = allowedTypes.value.filter(t => t.brand_id === item.brand_id && isImeiCategory(t.category));
    item.uniqueTypeNames = Array.from(new Set(types.map(t => t.name)));
};

const handleTypeChangeHp = (index) => {
    const item = hpItems.value[index];
    item.capacity = "";
    item.ram = "";
    item.storage = "";
    item.product_id = null;

    if (!item.type_name) {
        item.combinations = [];
        return;
    }

    // Resolve combinations for this specific item
    const specs = resolveSpecsForType(item.brand_id, item.type_name);
    item.combinations = specs.combinations;
};

const handleCapacityChangeHp = (index) => {
    const item = hpItems.value[index];
    if (item.capacity && item.capacity.includes('/')) {
        const parts = item.capacity.split('/');
        item.ram = parts[0].trim();
        item.storage = parts[1].trim();
    } else {
        item.ram = "";
        item.storage = item.capacity ? item.capacity.trim() : "";
    }

    // Look up product ID and price
    lookupProductIdHp(index);
    lookupPriceHp(index);
};

const lookupProductIdHp = async (index) => {
    const item = hpItems.value[index];
    if (!item.brand_id || !item.type_name) return;

    try {
        const brandObj = brands.value.find(b => b.id === item.brand_id);
        const brandName = brandObj ? brandObj.name : "";

        const response = await inventoryApi.getProductsLookup({
            type: 'hp',
            name: item.type_name
        });

        const found = response.data.find(p => {
            const dbBrand = (p.brand || "").toLowerCase().trim();
            const selBrand = brandName.toLowerCase().trim();
            const dbName = p.name.toLowerCase().trim();
            const selName = item.type_name.toLowerCase().trim();
            return dbBrand === selBrand && dbName === selName;
        });

        if (found) {
            item.product_id = found.id;
        } else {
            item.product_id = null;
        }
    } catch (e) {
        console.error("Lookup product failed", e);
    }
};

const lookupPriceHp = debounce(async (index) => {
    const item = hpItems.value[index];
    if (!item.type_name || !item.brand_id) return;

    // Find the product type ID from allowedTypes
    const typeObj = allowedTypes.value.find(t =>
        t.name === item.type_name &&
        t.brand_id === item.brand_id &&
        isImeiCategory(t.category)
    );

    if (!typeObj) return;

    try {
        const res = await inventoryApi.lookupPrice({
            product_type_id: typeObj.id,
            condition: item.condition,
            ram: item.ram || null,
            storage: item.storage || null
        });

        if (res.data && res.data.found) {
            const rawPrice = Number(res.data.price || 0);
            const rawCost = Number(res.data.cost_price || 0);

            item.suggestedSellingPrice = Math.round(rawPrice);
            // Auto-update prices when lookup succeeds (e.g. condition changed)
            item.selling_price = Math.round(rawPrice);
            item.cost_price = Math.round(rawCost);
        } else {
            item.suggestedSellingPrice = 0;
        }
    } catch (e) {
        console.error("Price lookup failed", e);
    }
}, 300);

// Helper for specs (extracted from original computed logic)
function resolveSpecsForType(brandId, typeName) {
    const matching = allowedTypes.value.filter(t => t.name === typeName && t.brand_id === brandId);
    const validCombinations = new Set();

    matching.forEach(t => {
        const rawRam = t.ram || "";
        const rawStorage = t.storage || "";
        const combinedRaw = rawRam + " " + rawStorage;
        const pairRegex = /(\d+)\s*[\/-]\s*(\d+)/g;
        const matches = combinedRaw.match(pairRegex);

        if (matches && matches.length > 0) {
            matches.forEach(m => {
                const parts = m.split(/[\/-]/);
                const r = parseInt(parts[0]);
                const s = parseInt(parts[1]);
                if (brandId === 1) validCombinations.add(String(s));
                else validCombinations.add(`${r}/${s}`);
            });
        } else {
            const parseToSet = (str) => {
                const s = new Set();
                if (!str) return s;
                const parts = str.split(',');
                parts.forEach(p => { const clean = p.trim().toUpperCase(); if (clean) s.add(clean); });
                return s;
            };
            const rams = parseToSet(rawRam);
            const storages = parseToSet(rawStorage);
            if (rams.size === 0 && storages.size === 0) {
                const fallbackMatch = combinedRaw.match(/\d+\s*(?:TB|GB|MB)?/gi);
                if (fallbackMatch) {
                    fallbackMatch.forEach(m => {
                        const clean = m.trim().toUpperCase();
                        const num = parseInt(clean);
                        if (num > 0 && num <= 32 && !clean.includes('TB')) rams.add(clean);
                        else if (num > 0) storages.add(clean);
                    });
                }
            }
            if (rams.size > 0 && storages.size > 0) {
                rams.forEach(r => storages.forEach(s => {
                    if (brandId === 1) validCombinations.add(String(s));
                    else if (parseInt(r) === 1) validCombinations.add(String(s));
                    else validCombinations.add(`${r}/${s}`);
                }));
            } else if (storages.size > 0) {
                storages.forEach(s => validCombinations.add(String(s)));
            } else if (rams.size > 0) {
                rams.forEach(r => validCombinations.add(String(r)));
            }
        }
    });

    const combinations = Array.from(validCombinations).sort((a, b) => {
        const parse = (str) => {
            const parts = String(str).split('/');
            const getBytes = (val) => {
                if (!val) return 0;
                let num = parseInt(val) || 0;
                let upper = String(val).toUpperCase();
                if (upper.includes('TB')) return num * 1024;
                return num;
            };
            return { ram: getBytes(parts[0]), storage: parts[1] ? getBytes(parts[1]) : getBytes(parts[0]) };
        };
        const pa = parse(a); const pb = parse(b);
        if (pa.ram !== pb.ram) return pa.ram - pb.ram;
        return pa.storage - pb.storage;
    });

    return { combinations };
}

// --- END REFACTORED Logic ---

// Redundant single-item state and watchers removed. 
// Row-specific logic is now handled in hpItems array and its associated handlers (handleBrandChangeHp, handleTypeChangeHp, etc.)


const canNext = computed(() => {
    if (currentStep.value === 1) return !!placementId.value;
    if (currentStep.value === 2) return !!itemType.value;
    if (currentStep.value === 3) return isManualDistributor.value ? newDistributorName.value.length >= 2 : !!selectedDistributor.value;
    return false;
});

// CARI DAN GANTI LOGIKA INI DI StockIn.vue
// costPriceDisplay and sellingPriceDisplay computed properties removed in favor of v-money sync syntax



const canSubmit = computed(() => {
    if (itemType.value === 'hp') {
        if (hpItems.value.length === 0) return false;
        return hpItems.value.every(item => item.type_name && item.capacity && item.parsedImeis.length > 0 && item.selling_price > 0);
    }

    // For Non-HP multiple items
    if (nonHpItems.value.length === 0) return false;
    return nonHpItems.value.every(item => item.brand_id && item.type_name && item.quantity > 0);
});

// CARI DAN GANTI FUNGSI submitStockIn AGAR SELALU KIRIM ID MESKIPUN MAPPING


function nextStep() {
    if (canNext.value) {
        if (currentStep.value === 2 && isDistributorRole.value) {
            selectedDistributor.value = authStore.user?.distributor_id || "";
            currentStep.value = 4;
            return;
        }
        currentStep.value++;
    }
}

function prevStep() {
    if (currentStep.value > 1) {
        if (currentStep.value === 4 && isDistributorRole.value) {
            currentStep.value = 2;
            return;
        }
        currentStep.value--;
    }
}

const showCreateAccountModal = ref(false);
const newAccountName = ref("");
const newAccountPin = ref("");
const isCreatingAccount = ref(false);

// Duplicate Modal State
const showDuplicateModal = ref(false);
const duplicateDetails = ref({ success: 0, fail: 0, items: [] });

function closeDuplicateModal() {
    showDuplicateModal.value = false;
    // Force direct navigation to ensure it happens
    router.push('/inventory').then(() => {
        window.scrollTo(0, 0);
    });
}

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
        await inventoryApi.createAccount({
            name: newAccountName.value,
            transaction_pin: newAccountPin.value
        });
        toast.success("Akun inventory berhasil dibuat!");
        showCreateAccountModal.value = false;
        newAccountName.value = "";
        newAccountPin.value = "";
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
        photo_preview: user.photo_inventory ? `${storageUrl}/storage/${user.photo_inventory}` : null
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
    const hasExistingPhoto = !!editForm.value.photo_preview && !editForm.value.photo_inventory;
    if (hasExistingPhoto && editForm.value.photo_inventory) {
        if (!confirm("Akun ini sudah memiliki foto. Penggantian foto memerlukan persetujuan dari Audit/Super Admin. Lanjutkan?")) {
            return;
        }
    }

    isUpdatingAccount.value = true;
    try {
        const formData = new FormData();
        formData.append('name', editForm.value.name);
        formData.append('phone', editForm.value.phone);
        if (editForm.value.photo_inventory) {
            formData.append('photo_inventory', editForm.value.photo_inventory);
        }

        const response = await inventoryApi.updateAccount(editForm.value.id, formData);

        if (hasExistingPhoto && editForm.value.photo_inventory) {
            toast.success("Permintaan pembaruan foto dikirim! Menunggu persetujuan Audit.");
        } else {
            toast.success("Akun berhasil diupdate!");
        }

        // Refresh specifically this user to get pending status
        const usersRes = await usersApi.list({ role: 'inventory' });
        targetUsers.value = (usersRes.data.data || usersRes.data);

        showEditAccountModal.value = false;
    } catch (e) {
        console.error("Update error:", e);
        if (e.response?.data?.errors) {
            console.error("Validation errors:", e.response.data.errors);
            const firstError = Object.values(e.response.data.errors)[0][0];
            toast.error(firstError || "Validasi gagal.");
        } else {
            toast.error(e.response?.data?.message || "Gagal update akun");
        }
    } finally {
        isUpdatingAccount.value = false;
    }
}

async function deleteInventoryAccount(user, event) {
    event.stopPropagation();
    if (!confirm(`Hapus akun inventory "${user.full_name || user.name}"?`)) return;

    try {
        await inventoryApi.deleteAccount(user.id);
        toast.success("Akun berhasil dihapus!");
        // Refresh list
        const usersRes = await usersApi.list({ role: 'inventory' });
        targetUsers.value = (usersRes.data.data || usersRes.data);
    } catch (e) {
        console.error("Delete error:", e);
        toast.error(e.response?.data?.message || "Gagal menghapus akun");
    }
}




const selectedInventoryUserId = ref(null);

function selectUserPlacement(user) {
    placementId.value = user.online_shop_id || user.warehouse_id || user.branch_id || user.distributor_id;
    placementType.value = user.online_shop_id ? 'online_shop' : (user.warehouse_id ? 'warehouse' : (user.distributor_id ? 'distributor' : 'branch'));

    // FALLBACK: If inventory account has no placement (orphaned), try using active user's context
    if (!placementId.value && authStore.user) {
        placementId.value = authStore.user.online_shop_id || authStore.user.warehouse_id || authStore.user.branch_id || authStore.user.distributor_id;
        placementType.value = authStore.user.online_shop_id ? 'online_shop' : (authStore.user.warehouse_id ? 'warehouse' : (authStore.user.distributor_id ? 'distributor' : 'branch'));

        // If still null, try localStorage for Super Admins
        if (!placementId.value) {
            const savedBranchId = localStorage.getItem('current_branch_id');
            if (savedBranchId) {
                placementId.value = parseInt(savedBranchId);
                placementType.value = 'branch'; // Default to branch if derived from global switcher
            }
        }
    }

    placementLabel.value = user.full_name || user.name;
    selectedInventoryUserId.value = user.id; // Capture Inventory Account ID
    selectedInventoryUserPinEnabled.value = user.pin_enabled || false;
    nextStep();
}

async function handlePinSuccess(pin) {
    showPinModal.value = false;
    await submitStockIn(pin);
}

async function submitStockIn(verifiedPin = null) {
    if (!canSubmit.value) return;

    // Use either the provided verifiedPin or the local pin state
    const pin = typeof verifiedPin === 'string' ? verifiedPin : null;

    // If the targeted inventory account has PIN enabled and we don't have a verified PIN yet
    if (selectedInventoryUserPinEnabled.value && !pin) {
        showPinModal.value = true;
        return;
    }

    isSubmitting.value = true;
    try {
        let productId = null;
        const payload = {
            distributor_id: isManualDistributor.value ? null : selectedDistributor.value,
            new_distributor_name: isManualDistributor.value ? newDistributorName.value : null,
            type: itemType.value,
            placement_type: placementType.value,
            placement_id: placementId.value,
            inventory_user_id: selectedInventoryUserId.value,
            transaction_pin: pin,
        };

        if (itemType.value === 'hp') {
            let totalInserted = 0;
            let totalDuplicates = [];

            for (const item of hpItems.value) {
                let productId = item.product_id;
                if (!productId && item.type_name) {
                    const brandObj = brands.value.find(b => b.id === item.brand_id);
                    const brandName = brandObj ? brandObj.name.toLowerCase().trim() : "";
                    const targetName = item.type_name.toLowerCase().trim();

                    let fallback = products.value.find(p => {
                        const pBrand = (p.brand || "").toLowerCase().trim();
                        const pName = p.name.toLowerCase().trim();
                        return pBrand === brandName && pName === targetName;
                    });

                    if (!fallback) {
                        try {
                            const resp = await inventoryApi.getProductsLookup({
                                type: 'hp',
                                name: item.type_name
                            });

                            if (resp.data.length > 0) {
                                fallback = resp.data.find(p => {
                                    const pBrand = (p.brand || "").toLowerCase().trim();
                                    const pName = p.name.toLowerCase().trim();
                                    return pBrand === brandName && pName === targetName;
                                });
                            }

                            if (!fallback) {
                                const brandObjRaw = brands.value.find(b => b.id === item.brand_id);
                                const brandNameRaw = brandObjRaw ? brandObjRaw.name : "Unknown";

                                const createResp = await productsApi.create({
                                    name: item.type_name,
                                    brand: brandNameRaw,
                                    type: 'hp',
                                    brand_id: item.brand_id,
                                    ram: item.ram || null,
                                    storage: item.storage || null,
                                    category: 'HP / Gadget',
                                    has_imei: true,
                                    sku: null
                                });
                                fallback = createResp.data;
                                toast.success(`Produk "${item.type_name}" otomatis dibuat.`);
                            }
                        } catch (err) {
                            console.error("API Lookup/Create failed", err);
                            toast.error("Gagal membuat produk otomatis: " + (err.response?.data?.message || err.message));
                            isSubmitting.value = false;
                            return;
                        }
                    }
                    productId = fallback ? fallback.id : null;
                    item.product_id = productId;
                }

                if (!productId) {
                    toast.error(`Produk tidak ditemukan untuk tipe: ${item.type_name}`);
                    isSubmitting.value = false;
                    return;
                }

                const payloadItem = {
                    ...payload,
                    product_id: productId,
                    brand_id: item.brand_id,
                    ram: item.ram,
                    storage: item.storage,
                    quantity: item.parsedImeis.length,
                    imeis: item.parsedImeis.map(imei => ({
                        imei: imei,
                        condition: item.condition,
                        cost_price: item.cost_price,
                        selling_price: item.selling_price,
                        color: "",
                        ram: item.ram,
                        storage: item.storage,
                        notes: item.notes
                    }))
                };

                const response = await inventoryApi.stockIn(payloadItem);
                if (response.data.duplicates && response.data.duplicates.length > 0) {
                    totalDuplicates.push(...response.data.duplicates);
                }
                totalInserted += response.data.inserted_count || 0;
            }

            if (totalDuplicates.length > 0) {
                duplicateDetails.value = {
                    success: totalInserted,
                    fail: totalDuplicates.length,
                    items: totalDuplicates
                };
                showDuplicateModal.value = true;
            } else if (totalInserted > 0) {
                toast.success(`Berhasil input ${totalInserted} stok HP!`);
                router.push('/inventory').catch(() => { window.location.href = '/inventory'; });
            }

            isSubmitting.value = false;
            return;
        } else {
            // NEW: Multi-item Non-HP
            payload.items = nonHpItems.value.map(item => {
                const brandObj = brands.value.find(b => b.id === item.brand_id);
                return {
                    brand_name: brandObj ? brandObj.name : "",
                    brand_id: item.brand_id,
                    type_name: item.type_name,
                    quantity: item.quantity,
                    cost_price: item.cost_price || 0,
                    selling_price: item.selling_price || 0,
                    notes: item.notes
                };
            });
        }

        const response = await inventoryApi.stockIn(payload);

        // Handle partial success/duplicates if any
        if (response.data.duplicates && response.data.duplicates.length > 0) {
            // Show Duplicate Modal
            duplicateDetails.value = {
                success: response.data.inserted_count,
                fail: response.data.duplicates.length,
                items: response.data.duplicates
            };
            showDuplicateModal.value = true;
            // DO NOT redirect yet
        } else {
            toast.success("Stok berhasil ditambahkan!");
            // Instant redirect to inventory page
            router.push('/inventory').catch(() => {
                window.location.href = '/inventory';
            });
        }

    } catch (error) {
        console.error("Stock in submit failed:", error);
        if (error.response?.data?.errors) {
            const firstError = Object.values(error.response.data.errors)[0][0];
            toast.error(firstError);
        } else {
            toast.error(error.response?.data?.message || "Gagal input stok");
        }
    } finally {
        isSubmitting.value = false;
    }
}


onMounted(() => {
    fetchInitialData();
    setTimeout(() => {
        if (isRestoring.value) isRestoring.value = false;
    }, 3000);
});
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
                <!-- Loading State -->
                <div v-if="isLoading" class="flex flex-col items-center justify-center py-20">
                    <div class="relative">
                        <div
                            class="w-16 h-16 border-4 border-primary-500/20 border-t-primary-500 rounded-full animate-spin">
                        </div>
                        <Loader2 class="absolute inset-0 m-auto w-6 h-6 text-primary-500 animate-pulse" />
                    </div>
                    <p class="mt-6 text-text-secondary font-medium animate-pulse tracking-wide uppercase text-[10px]">
                        Sedang Menyiapkan Sesi Stok In...</p>
                </div>

                <div v-else-if="targetUsers.length === 0" class="text-center py-10">
                    <div
                        class="bg-red-500/10 border border-red-500/20 text-red-500 p-6 rounded-2xl max-w-lg mx-auto mb-6">
                        <h3 class="font-bold text-lg mb-2">Belum Ada Akun Inventory</h3>
                        <p class="text-sm opacity-80">Anda belum memiliki akun khusus inventory untuk cabang/lokasi ini.
                            Silahkan buat terlebih dahulu untuk melanjutkan stok in.</p>
                    </div>
                    <button @click="showCreateAccountModal = true"
                        class="btn btn-primary px-8 py-4 rounded-xl shadow-lg shadow-primary-500/20 active:scale-95 transition-all">
                        <Plus :size="20" class="mr-2" /> Buat Akun Inventory Baru
                    </button>
                </div>

                <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div v-for="user in targetUsers" :key="user.id" @click="selectUserPlacement(user)"
                        class="p-5 rounded-2xl border border-surface-700 bg-surface-900 cursor-pointer hover:border-primary-500 transition-all relative">
                        <div v-if="placementLabel === (user.full_name || user.name)"
                            class="absolute top-3 right-20 text-primary-500">
                            <CheckCircle2 :size="24" />
                        </div>

                        <!-- EDIT BUTTON: Visible to creator OR high roles (super_admin, owner, audit, admin_produk) -->
                        <div v-if="authStore.user?.id === user.created_by?.id ||
                            authStore.user?.id === user.created_by ||
                            ['super_admin', 'owner', 'audit', 'admin_produk'].includes((authStore.userRole || '').toLowerCase())"
                            @click="openEditModal(user, $event)"
                            class="absolute top-3 right-10 p-1.5 hover:bg-surface-800 rounded-lg text-text-secondary hover:text-primary-500 transition-all z-10 group/edit">
                            <Edit2 :size="16" class="group-hover/edit:scale-110 transition-transform" />
                        </div>

                        <div class="flex items-center gap-4">
                            <div
                                class="w-12 h-12 rounded-xl bg-surface-800 flex items-center justify-center text-white font-bold overflow-hidden border border-surface-700 relative group/pic">

                                <!-- Pending Approval Badge -->
                                <div v-if="user.pending_photo_inventory"
                                    class="absolute inset-0 bg-amber-500/20 backdrop-blur-[1px] flex items-center justify-center z-[5]">
                                    <Clock class="text-amber-500" :size="16" />
                                </div>

                                <img v-if="user.pending_photo_inventory || user.photo_inventory"
                                    :src="`${storageUrl}/storage/${user.pending_photo_inventory || user.photo_inventory}`"
                                    class="w-full h-full object-cover"
                                    :class="{ 'opacity-50 grayscale-[0.5]': user.pending_photo_inventory }" />
                                <span v-else class="text-primary-500">{{ user.name[0] }}</span>

                                <!-- Corner Icon for Pending -->
                                <div v-if="user.pending_photo_inventory"
                                    class="absolute top-0 right-0 p-0.5 bg-amber-500 rounded-bl-lg z-10">
                                    <Clock class="text-white" :size="8" />
                                </div>
                            </div>
                            <div>
                                <h3 class="font-bold text-text-primary">{{ user.full_name || user.name }}</h3>
                                <div class="flex flex-col">
                                    <span class="text-xs text-text-secondary uppercase">{{ user.roles?.[0]?.name
                                    }}</span>
                                    <span v-if="user.created_by" class="text-[10px] text-text-secondary/70">
                                        by: {{ user.created_by.username }}
                                    </span>
                                    <span v-if="user.phone" class="text-[10px] text-emerald-500 font-mono">
                                        {{ user.phone }}
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
                        <h3 class="text-lg md:text-xl font-bold text-white mb-4">Buat Akun Inventory</h3>
                        <p class="text-text-secondary text-sm mb-6">Akun ini akan digunakan khusus untuk pencatatan
                            keluar masuk barang di lokasi ini.</p>

                        <div class="space-y-4">
                            <div>
                                <label class="label text-xs uppercase">Nama Akun / Bagian</label>
                                <input v-model="newAccountName" class="input bg-surface-800"
                                    placeholder="Contoh: Gudang Fisik A" autoFocus />
                            </div>
                            <div>
                                <label class="label text-[10px] uppercase">PIN Transaksi (Opsional)</label>
                                <input v-model="newAccountPin" type="password" maxlength="4"
                                    class="input bg-surface-800" placeholder="4 angka (Default: 0000)" />
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
            </div>

            <!-- Modal Edit Account -->
            <div v-if="showEditAccountModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
                <div
                    class="bg-surface-900 border border-surface-700 p-8 rounded-3xl w-full max-w-md shadow-2xl animate-in zoom-in-95">
                    <h3 class="text-lg md:text-xl font-bold text-white mb-4">Edit Akun Inventory</h3>

                    <div class="space-y-6">
                        <!-- Photo Upload -->
                        <div class="flex justify-center">
                            <div class="relative group cursor-pointer" @click="photoInput.click()">
                                <div
                                    class="w-24 h-24 rounded-full bg-surface-800 border-2 border-surface-700 overflow-hidden flex items-center justify-center relative">
                                    <img v-if="editForm.photo_preview" :src="editForm.photo_preview"
                                        class="w-full h-full object-cover" />
                                    <div v-else class="text-text-secondary">
                                        <Camera :size="32" />
                                    </div>

                                    <!-- Pending Approval Ribbon for Edit Modal -->
                                    <div v-if="targetUsers.find(u => u.id === editForm.id)?.pending_photo_inventory && !editForm.photo_inventory"
                                        class="absolute bottom-0 inset-x-0 bg-amber-500 py-0.5 text-center z-10">
                                        <p class="text-[8px] font-black text-white uppercase tracking-tighter">Pending
                                            Audit</p>
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

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-text-secondary mb-2">Nama Akun / Bagian
                                    (Terkunci)</label>
                                <input v-model="editForm.name" type="text"
                                    class="input w-full bg-surface-800/50 text-text-secondary cursor-not-allowed border-surface-800"
                                    readonly />
                                <div class="flex items-center gap-1.5 mt-2 px-1 text-text-secondary/40">
                                    <Shield :size="10" />
                                    <span class="text-[9px] uppercase font-black tracking-tighter">Identitas
                                        Permanen</span>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-text-secondary mb-2">No.
                                    WhatsApp</label>
                                <input v-model="editForm.phone" type="text" class="input w-full"
                                    placeholder="08xxxxx" />
                            </div>
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
                <label class="label text-xs uppercase font-black text-text-secondary mb-4">Pemasok <span
                        class="text-red-500">*</span></label>
                <div class="flex gap-3">
                    <select v-if="!isManualDistributor" v-model="selectedDistributor"
                        class="input flex-1 bg-surface-800 h-14">
                        <option value="" disabled>-- Pilih Daftar --</option>
                        <option v-for="d in distributors" :key="d.id" :value="d.id">{{ d.name }}</option>
                    </select>
                    <input v-else v-model="newDistributorName" placeholder="Nama baru..."
                        class="input flex-1 bg-surface-800 h-14" />
                    <button @click="isManualDistributor = !isManualDistributor"
                        class="btn btn-outline w-14 h-14 rounded-2xl"
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

                <div class="space-y-6">
                    <!-- HP MODE: Multiple Items Selection -->
                    <div v-if="itemType === 'hp'" class="space-y-6">
                        <div class="flex items-center justify-between px-2">
                            <label
                                class="text-xs font-black uppercase tracking-wider text-text-secondary opacity-60">Daftar
                                Stok HP / Gadget</label>
                            <button @click="addHpItem"
                                class="text-[10px] font-black text-primary-500 hover:text-primary-400 flex items-center gap-1 uppercase transition-all">
                                <Plus :size="14" /> Tambah Batch
                            </button>
                        </div>

                        <div v-for="(item, idx) in hpItems" :key="idx"
                            class="bg-surface-800/30 p-5 rounded-3xl border border-surface-700 relative group animate-in slide-in-from-right duration-300 shadow-lg space-y-6">
                            <button v-if="hpItems.length > 1" @click="removeHpItem(idx)"
                                class="absolute -top-3 -right-3 w-8 h-8 bg-surface-900 border border-surface-700 hover:border-red-500 hover:text-red-500 text-text-secondary rounded-xl flex items-center justify-center transition-all shadow-xl z-10">
                                <Trash2 :size="14" />
                            </button>

                            <div class="grid grid-cols-2 gap-4">
                                <div><label class="label text-[10px] uppercase">Merk <span
                                            class="text-red-500">*</span></label>
                                    <select v-model="item.brand_id" @change="handleBrandChangeHp(idx)"
                                        class="input bg-surface-900 h-11 !py-0 !px-3 text-sm">
                                        <option :value="null">-- Pilih Merk --</option>
                                        <option v-for="b in filteredBrands" :key="b.id" :value="b.id">{{ b.name }}
                                        </option>
                                    </select>
                                </div>

                                <div><label class="label text-[10px] uppercase">Tipe <span
                                            class="text-red-500">*</span></label>
                                    <select v-model="item.type_name" @change="handleTypeChangeHp(idx)"
                                        :disabled="!item.brand_id" class="input bg-surface-900 disabled:opacity-30 h-11 !py-0 !px-3 text-sm">
                                        <option value="">-- Pilih Tipe --</option>
                                        <option v-for="n in item.uniqueTypeNames" :key="n" :value="n">{{ n }}</option>
                                    </select>
                                </div>

                                <div class="col-span-full"><label class="label text-[10px] uppercase">Kapasitas <span
                                            class="text-red-500">*</span></label>
                                    <select v-model="item.capacity" @change="handleCapacityChangeHp(idx)"
                                        :disabled="!item.type_name" class="input bg-surface-900 disabled:opacity-30 h-11 !py-0 !px-3 text-sm">
                                        <option value="">-- Semua --</option>
                                        <option v-for="c in item.combinations" :key="c" :value="c">{{ c }}</option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                <div><label class="label text-[10px] uppercase">Kondisi <span
                                            class="text-red-500">*</span></label>
                                    <select v-model="item.condition" @change="lookupPriceHp(idx)"
                                        class="input bg-surface-900 h-11 !py-0 !px-3 text-sm">
                                        <option value="new">Baru</option>
                                        <option value="second">Bekas</option>
                                        <option value="ex_ibox">Ex iBox</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="label text-[10px] uppercase text-emerald-500">Harga Modal
                                        (Satuan)</label>
                                    <div
                                        class="w-full bg-surface-900 border border-surface-700 rounded-xl flex items-center px-4 focus-within:border-primary-500 h-10">
                                        <span class="text-text-secondary text-xs mr-2 font-black">Rp</span>
                                        <input v-money:cost_price="item" type="text"
                                            class="bg-transparent border-none outline-none w-full text-xs font-bold text-text-primary"
                                            placeholder="0" />
                                    </div>
                                </div>
                                <div class="col-span-full md:col-span-1">
                                    <label class="label text-[10px] uppercase text-blue-500">Harga Jual (Satuan) <span
                                            class="text-red-500">*</span></label>
                                    <div
                                        class="w-full bg-surface-900 border border-surface-700 rounded-xl flex items-center px-4 focus-within:border-primary-500 h-10">
                                        <span class="text-text-secondary text-xs mr-2 font-black">Rp</span>
                                        <input v-money:selling_price="item" type="text"
                                            class="bg-transparent border-none outline-none w-full text-xs font-bold text-text-primary"
                                            :placeholder="item.suggestedSellingPrice ? formatRupiah(item.suggestedSellingPrice).replace('Rp', '').trim() : '0'" />
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-2 mt-4">
                <label class="label text-sm uppercase font-bold flex justify-between">
                    <div class="flex items-center gap-2"><span>Input IMEI</span><span
                            class="text-red-500">*</span></div>
                    <span
                        class="text-xs font-normal text-text-secondary bg-surface-800 px-2 py-1 rounded-lg">Total:
                        {{ item.parsedImeis.length }} items</span>
                </label>
                <textarea v-model="item.bulkImeiText" @input="handleImeiInput(idx)" rows="4"
                    class="input bg-surface-900 font-mono text-xs leading-relaxed p-4 w-full rounded-2xl border-2 border-surface-700 focus:border-primary-500"
                    placeholder="Paste banyak IMEI disini..."></textarea>
            </div>

            <div class="space-y-2 mt-2">
                <label class="label text-[10px] uppercase font-bold text-text-secondary">Catatan Batch Ini</label>
                <input v-model="item.notes" class="input bg-surface-900 h-10 text-xs" placeholder="Contoh: Bazel lecet, Bonus Case, dll" />
            </div>
                        </div>

                        <button @click="addHpItem"
                            class="w-full py-4 border-2 border-dashed border-surface-700/50 rounded-2xl text-text-secondary hover:text-primary-500 hover:border-primary-500 transition-all flex items-center justify-center gap-2 font-black uppercase text-[10px] tracking-widest bg-surface-900/10 active:scale-95">
                            <Plus :size="18" /> Klik Disini Untuk Tambah Batch Lainnya
                        </button>
                    </div>

                    <!-- NON-HP MODE: Multiple Product Table Selection -->
                    <div v-if="itemType === 'non-hp'" class="space-y-4">
                        <div class="flex items-center justify-between px-2">
                            <label
                                class="text-xs font-black uppercase tracking-wider text-text-secondary opacity-60">Daftar
                                Barang Non-HP / Aksesoris</label>
                            <button @click="addNonHpItem"
                                class="text-[10px] font-black text-primary-500 hover:text-primary-400 flex items-center gap-1 uppercase transition-all">
                                <Plus :size="14" /> Tambah Baris
                            </button>
                        </div>

                        <div v-for="(item, idx) in nonHpItems" :key="idx"
                            class="bg-surface-800/30 p-5 rounded-2xl border border-surface-700 relative group animate-in slide-in-from-right duration-300">
                            <button v-if="nonHpItems.length > 1" @click="removeNonHpItem(idx)"
                                class="absolute -top-2 -right-2 w-7 h-7 bg-red-500/80 hover:bg-red-500 text-white rounded-lg flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity shadow-lg z-10">
                                <Trash2 :size="14" />
                            </button>
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                                <div class="md:col-span-2">
                                    <label class="label text-[8px] uppercase mb-1 opacity-50 font-black">Merk</label>
                                    <select v-model="item.brand_id" @change="handleBrandChangeNonHp(idx)"
                                        class="input bg-surface-900 text-[10px] h-10 px-2">
                                        <option :value="null">-- Merk --</option>
                                        <option v-for="b in filteredBrands" :key="b.id" :value="b.id">{{ b.name }}
                                        </option>
                                    </select>
                                </div>
                                <div class="md:col-span-3">
                                    <label class="label text-[8px] uppercase mb-1 opacity-50 font-black">Nama
                                        Barang</label>
                                    <input v-model="item.type_name" :disabled="!item.brand_id" placeholder="Tipe..."
                                        class="input bg-surface-900 text-[10px] h-10 px-2 disabled:opacity-30 font-bold"
                                        :list="'type-options-' + idx" />
                                    <datalist :id="'type-options-' + idx">
                                        <option v-for="n in item.uniqueTypeNames" :key="n" :value="n">{{ n }}</option>
                                    </datalist>
                                    <div v-if="getNonHpCategory(item)" class="mt-1 flex items-center">
                                        <span
                                            class="px-1 py-0.5 rounded bg-blue-500/10 text-blue-400 text-[8px] font-black uppercase tracking-tighter border border-blue-500/20">
                                            {{ getNonHpCategory(item) }}
                                        </span>
                                    </div>
                                </div>
                                <div class="md:col-span-1">
                                    <label class="label text-[8px] uppercase mb-1 opacity-50 font-black text-center">QTY</label>
                                    <input v-model.number="item.quantity" type="number" min="1"
                                        class="input bg-surface-900 text-[10px] h-10 text-center px-1 font-bold" />
                                </div>
                                <div class="md:col-span-3">
                                    <label
                                        class="label text-[8px] uppercase mb-1 opacity-50 font-black text-amber-500">Harga
                                        Modal</label>
                                    <div
                                        class="bg-surface-900 border border-surface-700 rounded-xl flex items-center px-2 h-10 focus-within:border-primary-500">
                                        <span class="text-[9px] text-text-secondary mr-1 font-black">Rp</span>
                                        <input v-money:cost_price="item" type="text" placeholder="0"
                                            class="bg-transparent border-none outline-none w-full text-[10px] font-bold text-text-primary" />
                                    </div>
                                </div>
                                <div class="md:col-span-3">
                                    <label
                                        class="label text-[8px] uppercase mb-1 opacity-50 font-black text-blue-500">Harga
                                        Jual</label>
                                    <div
                                        class="bg-surface-900 border border-surface-700 rounded-xl flex items-center px-2 h-10 focus-within:border-primary-500">
                                        <span class="text-[9px] text-text-secondary mr-1 font-black">Rp</span>
                                        <input v-money:selling_price="item" type="text" placeholder="0"
                                            class="bg-transparent border-none outline-none w-full text-[10px] font-bold text-text-primary" />
                                    </div>
                                </div>
                                <div class="md:col-span-full mt-2">
                                    <input v-model="item.notes" placeholder="Catatan item..." class="input bg-surface-900 text-[10px] h-8 px-2 border-surface-700" />
                                </div>
                            </div>
                        </div>

                        <button @click="addNonHpItem"
                            class="w-full py-4 border-2 border-dashed border-surface-700/50 rounded-2xl text-text-secondary hover:text-primary-500 hover:border-primary-500 transition-all flex items-center justify-center gap-2 font-black uppercase text-[10px] tracking-widest bg-surface-900/10 active:scale-95">
                            <Plus :size="18" /> Klik Disini Untuk Tambah Item Lain
                        </button>
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
                <button v-if="currentStep === 4" @click="submitStockIn()" :disabled="!canSubmit || isSubmitting"
                    class="btn btn-primary px-10 h-14 rounded-2xl uppercase text-[10px] tracking-widest font-black shadow-xl shadow-emerald-600/20">
                    <Loader2 v-if="isSubmitting" class="animate-spin mr-2" />
                    {{ isSubmitting ? 'Proses...' : 'Selesai & Simpan' }}
                </button>
            </div>
        </div>


        <!-- Duplicate Report Modal -->
        <div v-if="showDuplicateModal"
            class="fixed inset-0 z-[100] w-screen h-screen flex items-center justify-center bg-black/80 backdrop-blur-sm p-4 animate-in fade-in">
            <div
                class="bg-surface-900 border border-surface-700 p-8 rounded-3xl w-full max-w-lg shadow-2xl relative animate-in zoom-in-95">
                <div class="flex items-center gap-3 text-red-500 mb-6 font-bold text-xl uppercase tracking-wider">
                    <AlertTriangle :size="32" /> IMEI Duplikat Terdeteksi
                </div>

                <div class="space-y-4 mb-8">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-emerald-500/10 border border-emerald-500/20 p-4 rounded-2xl">
                            <span class="block text-xs uppercase text-emerald-500 mb-1">Berhasil</span>
                            <span class="text-3xl font-black text-white">{{ duplicateDetails.success }}</span>
                        </div>
                        <div class="bg-rose-500/10 border border-rose-500/20 p-4 rounded-2xl">
                            <span class="block text-xs uppercase text-rose-500 mb-1">Duplikat</span>
                            <span class="text-3xl font-black text-white">{{ duplicateDetails.fail }}</span>
                        </div>
                    </div>

                    <div class="bg-surface-800 rounded-2xl p-4 max-h-[200px] overflow-y-auto border border-surface-700">
                        <p class="text-sm text-text-secondary mb-3">IMEI berikut sudah ada di sistem:</p>
                        <div class="grid grid-cols-2 gap-2">
                            <div v-for="item in duplicateDetails.items" :key="item.imei"
                                class="text-sm font-mono text-white/80 bg-surface-900 border border-surface-700 p-2 rounded-xl text-center">
                                {{ item.imei }}
                            </div>
                        </div>
                    </div>
                    <p class="text-xs text-text-secondary italic">Sistem hanya mengabaikan IMEI yang duplikat.
                        Barang
                        lainnya sudah berhasil disimpan.</p>
                </div>

                <div class="flex justify-end pt-4 border-t border-surface-700">
                    <button @click="closeDuplicateModal" class="btn btn-primary px-8 rounded-xl font-bold">Saya
                        Mengerti</button>
                </div>
            </div>
        </div>

        <!-- PIN Modal Component -->
        <PinModal :show="showPinModal" mode="verify" title="Verifikasi PIN Transaksi" @close="showPinModal = false"
            @success="handlePinSuccess" />
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