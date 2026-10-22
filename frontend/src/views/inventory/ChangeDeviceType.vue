<script setup>
import { ref, reactive, computed, onMounted, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import api from "../../api/axios";
import { useAuthStore } from "../../store/auth";
import { useToast } from "../../composables/useToast";
import {
    Search, Loader2, Smartphone, ShieldAlert, CheckCircle2,
    RotateCcw, History, AlertTriangle, ArrowRight, Save,
    User, HardDrive, Building2, Tag, Calendar
} from "lucide-vue-next";

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const toast = useToast();

const isSuperAdmin = computed(() => {
    const role = (authStore.userRole || '').toLowerCase().replace(/\s+/g, '_');
    return role === 'super_admin';
});

// Search & State
const searchImei = ref("");
const isSearching = ref(false);
const isSubmitting = ref(false);
const isLoadingTypes = ref(false);
const isManualType = ref(false);
const isManualStorage = ref(false);
const deviceData = ref(null);
const pastHistories = ref([]);
const brands = ref([]);
const typesList = ref([]);
const productsList = ref([]);
const selectedTypeObj = ref(null);

// Global Audit Log
const globalHistories = ref([]);
const isGlobalLoading = ref(false);
const globalSearch = ref("");

// Form
const form = reactive({
    brand_id: null,
    brand_name: "",
    product_id: null,
    type_name: "",
    storage: "",
    cs_name_mistake: "",
    notes: ""
});

// Combined unique types strictly belonging to the currently selected brand
const combinedTypes = computed(() => {
    const list = [];
    const seenNames = new Set();

    // 1. Types from ProductType
    (typesList.value || []).forEach(t => {
        const norm = (t.name || '').trim().toLowerCase();
        if (norm && !seenNames.has(norm)) {
            seenNames.add(norm);
            list.push({
                id: t.id,
                name: t.name.trim(),
                is_product: false,
                capacities: Array.isArray(t.capacities) ? t.capacities : []
            });
        }
    });

    // 2. Types from Product
    (productsList.value || []).forEach(p => {
        const norm = (p.name || '').trim().toLowerCase();
        if (norm && !seenNames.has(norm)) {
            seenNames.add(norm);
            list.push({
                id: p.id,
                name: p.name.trim(),
                is_product: true,
                capacities: Array.isArray(p.capacities) ? p.capacities : []
            });
        }
    });

    return list.sort((a, b) => a.name.localeCompare(b.name));
});

// Capacities strictly matching the selected Tipe HP
const currentAvailableCapacities = computed(() => {
    if (!form.type_name) return [];

    if (selectedTypeObj.value && Array.isArray(selectedTypeObj.value.capacities) && selectedTypeObj.value.capacities.length > 0) {
        return selectedTypeObj.value.capacities;
    }

    const norm = form.type_name.trim().toLowerCase();
    const found = combinedTypes.value.find(t => t.name.toLowerCase() === norm);
    if (found && Array.isArray(found.capacities) && found.capacities.length > 0) {
        return found.capacities;
    }

    return [];
});

function normalizeStorage(val) {
    return (val || '').toUpperCase().replace(/\s+/g, '');
}

function findMatchingBrand(brandId, brandName, productName) {
    if (brandId && brands.value.some(b => b.id === brandId)) {
        return brands.value.find(b => b.id === brandId);
    }

    const cleanStr = (s) => (s || '').toLowerCase().replace(/[^a-z0-9]/g, '');

    const targetClean = cleanStr(brandName);
    if (targetClean && targetClean !== '-') {
        // Direct exact clean match
        let found = brands.value.find(b => cleanStr(b.name) === targetClean);
        if (found) return found;

        // Substring match
        found = brands.value.find(b => {
            const bClean = cleanStr(b.name);
            return bClean && (bClean.includes(targetClean) || targetClean.includes(bClean));
        });
        if (found) return found;

        // iPhone / Apple alias matching
        if (targetClean.includes('iphone') || targetClean.includes('apple') || targetClean.includes('ibox')) {
            found = brands.value.find(b => {
                const bClean = cleanStr(b.name);
                return bClean.includes('iphone') || bClean.includes('apple');
            });
            if (found) return found;
        }
    }

    // Fallback by productName (e.g. "iPhone 13", "IP 14", "13")
    const prodClean = cleanStr(productName);
    if (prodClean) {
        if (prodClean.startsWith('ip') || prodClean.includes('iphone') || prodClean.includes('apple')) {
            const appleBrand = brands.value.find(b => {
                const bClean = cleanStr(b.name);
                return bClean.includes('iphone') || bClean.includes('apple');
            });
            if (appleBrand) return appleBrand;
        }

        const matchByName = brands.value.find(b => {
            const bClean = cleanStr(b.name);
            return bClean && prodClean.includes(bClean);
        });
        if (matchByName) return matchByName;
    }

    return null;
}

async function loadBrands() {
    try {
        const res = await api.get('/brands');
        brands.value = res.data.data || res.data || [];

        // If device data already loaded but brand not yet resolved, re-match
        if (deviceData.value && (!form.brand_id || form.brand_id === null)) {
            const matched = findMatchingBrand(
                deviceData.value.brand_id,
                deviceData.value.brand_name,
                deviceData.value.product_name
            );
            if (matched) {
                form.brand_id = matched.id;
                form.brand_name = matched.name;
                await loadTypes(form.brand_id);
            }
        }
    } catch (e) {
        console.error("Gagal memuat brands:", e);
    }
}

async function loadTypes(brandId = null) {
    if (!brandId && !form.brand_name) {
        typesList.value = [];
        productsList.value = [];
        return;
    }
    isLoadingTypes.value = true;
    try {
        const res = await api.get('/admin/imei-correction/types', {
            params: {
                brand_id: brandId,
                brand_name: form.brand_name
            }
        });
        typesList.value = res.data.types || [];
        productsList.value = res.data.products || [];
    } catch (e) {
        console.error("Gagal memuat types:", e);
    } finally {
        isLoadingTypes.value = false;
    }
}

async function loadGlobalHistory() {
    isGlobalLoading.value = true;
    try {
        const res = await api.get('/admin/imei-correction/history', {
            params: { q: globalSearch.value }
        });
        globalHistories.value = res.data.data || [];
    } catch (e) {
        console.error("Gagal memuat history global:", e);
    } finally {
        isGlobalLoading.value = false;
    }
}

async function doSearch() {
    const q = searchImei.value.trim();
    if (!q || q.length < 3) {
        toast.error("Masukkan minimal 3 digit IMEI");
        return;
    }

    isSearching.value = true;
    deviceData.value = null;
    pastHistories.value = [];
    selectedTypeObj.value = null;
    isManualType.value = false;
    isManualStorage.value = false;

    try {
        const res = await api.get('/admin/imei-correction/search', {
            params: { imei: q }
        });

        if (res.data.found && res.data.data) {
            deviceData.value = res.data.data;
            pastHistories.value = res.data.histories || [];
            if (res.data.brands && res.data.brands.length > 0) {
                brands.value = res.data.brands;
            }

            // Robust Brand Matching
            const matchedBrand = findMatchingBrand(
                deviceData.value.brand_id,
                deviceData.value.brand_name,
                deviceData.value.product_name
            );

            if (matchedBrand) {
                form.brand_id = matchedBrand.id;
                form.brand_name = matchedBrand.name;
            } else {
                form.brand_id = deviceData.value.brand_id || null;
                form.brand_name = deviceData.value.brand_name !== '-' ? deviceData.value.brand_name : "";
            }

            form.product_id = deviceData.value.product_id || null;
            form.cs_name_mistake = "";
            form.notes = "";

            // Load types filtered by brand
            if (form.brand_id) {
                await loadTypes(form.brand_id);
            }

            // Auto-match and select Type in dropdown
            const initialTypeName = (deviceData.value.product_name || '').trim();
            const cleanTarget = initialTypeName.toLowerCase().replace(/[^a-z0-9]/g, '');

            let matchedType = combinedTypes.value.find(t => {
                const cleanT = t.name.toLowerCase().replace(/[^a-z0-9]/g, '');
                return cleanT === cleanTarget;
            });

            if (!matchedType && cleanTarget) {
                matchedType = combinedTypes.value.find(t => {
                    const cleanT = t.name.toLowerCase().replace(/[^a-z0-9]/g, '');
                    return cleanT.endsWith(cleanTarget) || cleanT.includes(cleanTarget);
                });
            }

            if (matchedType) {
                selectedTypeObj.value = matchedType;
                form.type_name = matchedType.name;
                form.product_id = matchedType.is_product ? matchedType.id : null;
            } else {
                selectedTypeObj.value = {
                    name: initialTypeName,
                    is_product: false,
                    capacities: deviceData.value.storage && deviceData.value.storage !== '-' ? [deviceData.value.storage] : []
                };
                form.type_name = initialTypeName !== '-' ? initialTypeName : "";
            }

            // Auto-select storage matching the type's capacities
            const initStorage = (deviceData.value.storage || '').trim();
            const caps = currentAvailableCapacities.value;
            if (caps.length > 0) {
                const matchedCap = caps.find(c => normalizeStorage(c) === normalizeStorage(initStorage));
                form.storage = matchedCap || (initStorage !== '-' ? initStorage : caps[0]);
            } else {
                form.storage = initStorage !== '-' ? initStorage : "";
            }
        } else {
            toast.error(res.data.message || "Data unit tidak ditemukan.");
        }
    } catch (err) {
        const msg = err.response?.data?.message || "Data IMEI tidak ditemukan di database.";
        toast.error(msg);
    } finally {
        isSearching.value = false;
    }
}

async function onBrandChange() {
    const selectedBrand = brands.value.find(b => b.id === form.brand_id);
    form.brand_name = selectedBrand ? selectedBrand.name : "";
    
    // Reset Type and Storage when brand changes
    selectedTypeObj.value = null;
    form.type_name = "";
    form.product_id = null;
    form.storage = "";
    isManualType.value = false;
    isManualStorage.value = false;

    if (form.brand_id) {
        await loadTypes(form.brand_id);
    } else {
        typesList.value = [];
        productsList.value = [];
    }
}

function onTypeSelect() {
    if (!selectedTypeObj.value) return;
    form.type_name = selectedTypeObj.value.name;
    form.product_id = selectedTypeObj.value.is_product ? selectedTypeObj.value.id : null;

    // Auto-select capacity if available for this type
    const caps = selectedTypeObj.value.capacities || [];
    if (caps.length > 0) {
        const matched = caps.find(c => normalizeStorage(c) === normalizeStorage(form.storage));
        if (matched) {
            form.storage = matched;
        } else {
            form.storage = caps[0];
        }
        isManualStorage.value = false;
    } else {
        form.storage = "";
    }
}

function selectStorage(gb) {
    form.storage = gb;
}

async function submitCorrection() {
    if (!form.cs_name_mistake || form.cs_name_mistake.trim().length < 2) {
        toast.error("Nama CS yang salah input wajib diisi!");
        return;
    }
    if (!form.type_name || form.type_name.trim().length < 2) {
        toast.error("Tipe HP tujuan wajib diisi!");
        return;
    }
    if (!form.storage || form.storage.trim().length === 0) {
        toast.error("Storage (GB) wajib dipilih atau diisi!");
        return;
    }

    const confirmMsg = `Konfirmasi koreksi IMEI ${deviceData.value.imei}:\n\n`
        + `Dari: ${deviceData.value.product_name} (${deviceData.value.storage})\n`
        + `Menjadi: ${form.type_name} (${form.storage})\n`
        + `CS Salah Input: ${form.cs_name_mistake}\n\n`
        + `Lanjutkan simpan perubahan?`;

    if (!window.confirm(confirmMsg)) {
        return;
    }

    isSubmitting.value = true;
    try {
        const payload = {
            imei: deviceData.value.imei,
            cs_name_mistake: form.cs_name_mistake.trim(),
            brand_id: form.brand_id,
            brand_name: form.brand_name,
            product_id: form.product_id,
            type_name: form.type_name.trim(),
            storage: form.storage.trim(),
            notes: form.notes ? form.notes.trim() : null
        };

        const res = await api.post('/admin/imei-correction/correct', payload);
        toast.success(res.data.message || "Spesifikasi IMEI berhasil dikoreksi.");

        // Reload data for this IMEI and global history
        await doSearch();
        loadGlobalHistory();
    } catch (err) {
        const msg = err.response?.data?.message || "Gagal mengoreksi spesifikasi.";
        toast.error(msg);
    } finally {
        isSubmitting.value = false;
    }
}

function formatDate(dateStr) {
    if (!dateStr) return "-";
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleDateString("id-ID", {
        day: "numeric",
        month: "short",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit"
    });
}

function formatCurrency(amount) {
    if (!amount) return "Rp 0";
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        minimumFractionDigits: 0
    }).format(amount);
}

onMounted(async () => {
    await loadBrands();
    await loadTypes();
    loadGlobalHistory();

    // Check if imei query param was passed (e.g. from TrackStock page)
    const initialImei = route.query.imei;
    if (initialImei) {
        searchImei.value = String(initialImei).trim();
        doSearch();
    }
});
</script>

<template>
    <div class="change-type-page max-w-5xl mx-auto px-4 sm:px-6 py-6 pb-16">
        <!-- Role Check Barrier -->
        <div v-if="!isSuperAdmin" class="bg-red-500/10 border border-red-500/20 rounded-2xl p-6 text-center my-8">
            <ShieldAlert :size="48" class="text-red-500 mx-auto mb-3" />
            <h2 class="text-lg font-bold text-text-primary">Akses Ditolak</h2>
            <p class="text-sm text-text-secondary mt-1">Halaman ini hanya dapat diakses oleh role Super Admin.</p>
        </div>

        <template v-else>
            <!-- Header -->
            <div class="mb-6">
                <div class="flex items-center gap-2 mb-1.5 flex-wrap">
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-purple-500/10 text-purple-600 dark:text-purple-400 border border-purple-500/20">
                        <ShieldAlert :size="12" />
                        KHUSUS SUPER ADMIN
                    </span>
                    <span class="text-xs text-text-secondary">Audit Trail & Koreksi Data</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-text-primary tracking-tight">
                    Rubah Tipe & Spesifikasi IMEI
                </h1>
                <p class="text-sm text-text-secondary mt-1 max-w-2xl">
                    Perbaiki kesalahan input tipe, brand, atau kapasitas storage IMEI oleh CS. Riwayat sebelum koreksi akan tetap tersimpan secara permanen pada history pelacakan.
                </p>
            </div>

            <!-- Search IMEI Card -->
            <div class="card-box p-4 sm:p-5 mb-6">
                <form @submit.prevent="doSearch" class="flex flex-col sm:flex-row gap-3">
                    <div class="relative flex-1">
                        <Search class="absolute left-3.5 top-1/2 -translate-y-1/2 text-surface-500" :size="18" />
                        <input
                            v-model="searchImei"
                            type="text"
                            placeholder="Masukkan nomor IMEI yang ingin dirubah..."
                            class="input-control pl-10 h-12 text-sm font-mono w-full"
                            autofocus
                        />
                    </div>
                    <button
                        type="submit"
                        :disabled="isSearching || searchImei.length < 3"
                        class="btn-primary h-12 px-6 flex items-center justify-center gap-2 shrink-0 font-semibold text-sm"
                    >
                        <Loader2 v-if="isSearching" :size="18" class="animate-spin" />
                        <template v-else>
                            <Search :size="18" />
                            <span>Cari Unit</span>
                        </template>
                    </button>
                    <router-link
                        v-if="deviceData"
                        :to="`/track?q=${deviceData.imei}`"
                        class="btn-secondary h-12 px-4 flex items-center justify-center gap-1.5 text-xs font-semibold shrink-0"
                        title="Buka Lacak & History IMEI"
                    >
                        <History :size="16" />
                        <span class="hidden sm:inline">Lacak Timeline</span>
                    </router-link>
                </form>
            </div>

            <!-- Main Content: Found Device -->
            <div v-if="deviceData" class="space-y-6">
                <!-- Current Device Info Banner -->
                <div class="card-box p-4 sm:p-6 border-l-4 border-l-blue-500 bg-surface-800">
                    <div class="flex items-center justify-between flex-wrap gap-2 pb-3 mb-4 border-b border-surface-700/60">
                        <div class="flex items-center gap-2.5">
                            <div class="w-10 h-10 rounded-xl bg-blue-500/10 text-blue-500 flex items-center justify-center shrink-0">
                                <Smartphone :size="20" />
                            </div>
                            <div>
                                <p class="text-xs text-text-secondary uppercase tracking-wider font-semibold">Spesifikasi Saat Ini (Database)</p>
                                <p class="text-base sm:text-lg font-extrabold text-text-primary">{{ deviceData.product_name }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="inline-block px-2.5 py-1 rounded-lg text-xs font-mono font-bold bg-surface-700 text-text-primary border border-surface-600">
                                IMEI: {{ deviceData.imei }}
                            </span>
                            <span class="inline-block px-2 py-0.5 rounded text-xs font-bold uppercase" :class="{
                                'bg-green-500/10 text-green-500 border border-green-500/20': deviceData.status === 'available',
                                'bg-amber-500/10 text-amber-500 border border-amber-500/20': deviceData.status === 'sold',
                                'bg-blue-500/10 text-blue-500 border border-blue-500/20': deviceData.status === 'transfer',
                                'bg-red-500/10 text-red-500 border border-red-500/20': deviceData.is_deleted
                            }">
                                {{ deviceData.is_deleted ? 'DELETED' : deviceData.status }}
                            </span>
                        </div>
                    </div>

                    <!-- Meta specs grid -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
                        <div>
                            <span class="text-text-secondary block">Merek (Brand):</span>
                            <strong class="text-text-primary text-sm">{{ deviceData.brand_name }}</strong>
                        </div>
                        <div>
                            <span class="text-text-secondary block">Kapasitas Storage:</span>
                            <strong class="text-text-primary text-sm">{{ deviceData.storage }}</strong>
                        </div>
                        <div>
                            <span class="text-text-secondary block">Posisi Lokasi:</span>
                            <strong class="text-text-primary text-sm">{{ deviceData.placement_name }}</strong>
                        </div>
                        <div>
                            <span class="text-text-secondary block">Harga Jual:</span>
                            <strong class="text-text-primary text-sm">{{ formatCurrency(deviceData.selling_price) }}</strong>
                        </div>
                        <div>
                            <span class="text-text-secondary block">Distributor / Supplier:</span>
                            <strong class="text-text-primary text-sm">{{ deviceData.distributor_name }}</strong>
                        </div>
                        <div>
                            <span class="text-text-secondary block">Diinput oleh (CS):</span>
                            <strong class="text-text-primary text-sm">{{ deviceData.input_by }}</strong>
                        </div>
                        <div class="col-span-2">
                            <span class="text-text-secondary block">Tgl Registrasi Masuk:</span>
                            <strong class="text-text-primary text-sm">{{ formatDate(deviceData.registered_at) }}</strong>
                        </div>
                    </div>
                </div>

                <!-- Form Koreksi Spesifikasi -->
                <div class="card-box p-4 sm:p-6 border-purple-500/20">
                    <div class="flex items-center gap-2.5 mb-5 pb-3 border-b border-surface-700/60">
                        <div class="w-9 h-9 rounded-xl bg-purple-500/10 text-purple-500 flex items-center justify-center shrink-0">
                            <RotateCcw :size="18" />
                        </div>
                        <div>
                            <h2 class="text-base sm:text-lg font-bold text-text-primary">Form Koreksi Spesifikasi Baru</h2>
                            <p class="text-xs text-text-secondary">Ubah tipe produk, kapasitas, dan catat nama CS yang salah input</p>
                        </div>
                    </div>

                    <form @submit.prevent="submitCorrection" class="space-y-5">
                        <!-- Row 1: Brand & Type -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Brand -->
                            <div>
                                <label class="block text-xs font-bold text-text-primary mb-1.5 flex items-center gap-1.5">
                                    <Tag :size="14" class="text-purple-400" />
                                    <span>Merek (Brand)</span>
                                    <span class="text-red-500">*</span>
                                </label>
                                <select
                                    v-model="form.brand_id"
                                    @change="onBrandChange"
                                    class="input-control h-11 w-full text-sm"
                                    required
                                >
                                    <option :value="null">Pilih Brand...</option>
                                    <option v-for="b in brands" :key="b.id" :value="b.id">
                                        {{ b.name }}
                                    </option>
                                    <option
                                        v-if="form.brand_name && !brands.some(b => b.id === form.brand_id || b.name === form.brand_name)"
                                        :value="form.brand_id || form.brand_name"
                                    >
                                        {{ form.brand_name }}
                                    </option>
                                </select>
                            </div>

                            <!-- Tipe HP -->
                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <label class="text-xs font-bold text-text-primary flex items-center gap-1.5">
                                        <Smartphone :size="14" class="text-purple-400" />
                                        <span>Tipe HP (Model)</span>
                                        <span class="text-red-500">*</span>
                                    </label>
                                    <button
                                        v-if="form.brand_id"
                                        type="button"
                                        @click="isManualType = !isManualType"
                                        class="text-[11px] text-purple-600 dark:text-purple-400 hover:underline font-semibold"
                                    >
                                        {{ isManualType ? '← Pilih dari Dropdown' : '+ Ketik Manual' }}
                                    </button>
                                </div>

                                <!-- Dropdown Mode (Sesuai Brand) -->
                                <div v-if="!isManualType">
                                    <select
                                        v-model="selectedTypeObj"
                                        @change="onTypeSelect"
                                        class="input-control h-11 w-full text-sm"
                                        :disabled="!form.brand_id || isLoadingTypes"
                                        required
                                    >
                                        <option :value="null" disabled>
                                            {{ !form.brand_id ? 'Pilih Brand terlebih dahulu...' : (isLoadingTypes ? 'Memuat tipe HP...' : (combinedTypes.length === 0 ? 'Belum ada tipe untuk brand ini' : 'Pilih Tipe HP...')) }}
                                        </option>
                                        <option
                                            v-for="t in combinedTypes"
                                            :key="'t-' + t.name"
                                            :value="t"
                                        >
                                            {{ t.name }}
                                        </option>
                                        <!-- Fallback option for current unit type if not in list -->
                                        <option
                                            v-if="form.type_name && !combinedTypes.some(t => t.name.toLowerCase() === form.type_name.toLowerCase())"
                                            :value="{ name: form.type_name, capacities: [form.storage].filter(Boolean) }"
                                        >
                                            {{ form.type_name }} (Bawaan Unit)
                                        </option>
                                    </select>
                                    <p class="text-[11px] text-text-secondary mt-1">
                                        {{ !form.brand_id ? 'Pilih brand terlebih dahulu untuk memuat model.' : `Hanya menampilkan tipe produk dari merek ${form.brand_name || ''}.` }}
                                    </p>
                                </div>

                                <!-- Manual Input Mode -->
                                <div v-else>
                                    <input
                                        v-model="form.type_name"
                                        type="text"
                                        placeholder="Ketik tipe HP baru secara manual..."
                                        class="input-control h-11 w-full text-sm"
                                        required
                                    />
                                    <p class="text-[11px] text-amber-500 mt-1">Mode ketik manual aktif jika tipe unit belum tercatat di sistem.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Row 2: Storage (Sesuai Tipe yang Dipilih) -->
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="text-xs font-bold text-text-primary flex items-center gap-1.5">
                                    <HardDrive :size="14" class="text-purple-400" />
                                    <span>Kapasitas Storage (GB)</span>
                                    <span class="text-red-500">*</span>
                                </label>
                                <button
                                    v-if="currentAvailableCapacities.length > 0"
                                    type="button"
                                    @click="isManualStorage = !isManualStorage"
                                    class="text-[11px] text-purple-600 dark:text-purple-400 hover:underline font-semibold"
                                >
                                    {{ isManualStorage ? '← Pilih Kapasitas Tipe' : '+ Input Manual' }}
                                </button>
                            </div>

                            <!-- Case A: Tipe memiliki varian kapasitas terdaftar -->
                            <template v-if="currentAvailableCapacities.length > 0 && !isManualStorage">
                                <!-- Interactive chips strictly for this type -->
                                <div class="flex items-center gap-2 flex-wrap mb-2">
                                    <button
                                        v-for="cap in currentAvailableCapacities"
                                        :key="cap"
                                        type="button"
                                        @click="form.storage = cap"
                                        class="px-3.5 py-1.5 rounded-lg text-xs font-bold border transition-all flex items-center gap-1"
                                        :class="normalizeStorage(form.storage) === normalizeStorage(cap)
                                            ? 'bg-purple-600 text-white border-purple-600 shadow-sm ring-1 ring-purple-500/50'
                                            : 'bg-surface-800 text-text-primary border-surface-700 hover:border-purple-500/50'"
                                    >
                                        <CheckCircle2 v-if="normalizeStorage(form.storage) === normalizeStorage(cap)" :size="13" />
                                        <span>{{ cap }}</span>
                                    </button>
                                </div>

                                <select
                                    v-model="form.storage"
                                    class="input-control h-11 w-full text-sm"
                                    required
                                >
                                    <option value="" disabled>Pilih Kapasitas...</option>
                                    <option
                                        v-for="cap in currentAvailableCapacities"
                                        :key="'opt-' + cap"
                                        :value="cap"
                                    >
                                        {{ cap }}
                                    </option>
                                    <option
                                        v-if="form.storage && !currentAvailableCapacities.some(c => normalizeStorage(c) === normalizeStorage(form.storage))"
                                        :value="form.storage"
                                    >
                                        {{ form.storage }} (Bawaan Unit)
                                    </option>
                                </select>
                                <p class="text-[11px] text-text-secondary mt-1">
                                    Pilihan kapasitas disesuaikan dengan varian tipe <strong class="text-text-primary">{{ form.type_name }}</strong>.
                                </p>
                            </template>

                            <!-- Case B: Belum pilih tipe atau tipe tidak memiliki varian atau mode manual -->
                            <template v-else>
                                <div v-if="!form.type_name" class="p-3.5 rounded-xl bg-surface-900 border border-surface-700 text-xs text-text-secondary flex items-center gap-2">
                                    <AlertTriangle :size="16" class="text-surface-500 shrink-0" />
                                    <span>Pilih <strong>Tipe HP</strong> terlebih dahulu untuk memunculkan daftar kapasitas yang sesuai.</span>
                                </div>
                                <div v-else class="space-y-1.5">
                                    <input
                                        v-model="form.storage"
                                        type="text"
                                        placeholder="Contoh: 128GB, 256GB..."
                                        class="input-control h-11 w-full text-sm"
                                        required
                                    />
                                    <p class="text-[11px] text-text-secondary">
                                        {{ isManualStorage ? 'Mode input manual aktif: masukkan kapasitas storage unit.' : 'Tipe ini belum memiliki data varian kapasitas di master data. Silakan ketik kapasitas secara manual.' }}
                                    </p>
                                </div>
                            </template>
                        </div>

                        <!-- Row 3: CRITICAL FIELD - Nama CS Yang Salah Input -->
                        <div class="p-4 rounded-xl bg-amber-500/10 border-2 border-amber-500/30">
                            <label class="block text-xs sm:text-sm font-black text-amber-600 dark:text-amber-400 mb-1 flex items-center gap-1.5">
                                <AlertTriangle :size="16" class="text-amber-500" />
                                <span>NAMA CS YANG SALAH INPUT (WAJIB DIISI)</span>
                                <span class="text-red-500">*</span>
                            </label>
                            <p class="text-xs text-text-secondary mb-2.5">
                                Masukkan nama CS atau staf yang awalnya salah memasukkan tipe/spesifikasi unit ini. Nama ini akan dicatat ke audit trail dan muncul di history pelacakan IMEI.
                            </p>
                            <input
                                v-model="form.cs_name_mistake"
                                type="text"
                                placeholder="Ketik nama CS yang salah input (contoh: Budi, Siti, Aldi CS)..."
                                class="input-control h-11 w-full text-sm font-bold bg-surface-900 border-amber-500/40 focus:border-amber-500 focus:ring-amber-500/20"
                                required
                            />
                        </div>

                        <!-- Row 4: Alasan / Keterangan -->
                        <div>
                            <label class="block text-xs font-bold text-text-primary mb-1.5">
                                Alasan / Keterangan Koreksi (Opsional)
                            </label>
                            <textarea
                                v-model="form.notes"
                                rows="2"
                                placeholder="Jelaskan detail koreksi jika ada catatan khusus..."
                                class="input-control w-full text-sm p-3"
                            ></textarea>
                        </div>

                        <!-- Submit Button -->
                        <div class="flex items-center justify-end gap-3 pt-3 border-t border-surface-700/60">
                            <button
                                type="button"
                                @click="doSearch"
                                :disabled="isSubmitting"
                                class="btn-secondary h-11 px-4 text-xs font-bold"
                            >
                                Reset Form
                            </button>
                            <button
                                type="submit"
                                :disabled="isSubmitting"
                                class="btn-primary h-11 px-6 flex items-center gap-2 text-sm font-bold bg-purple-600 hover:bg-purple-500 text-white"
                            >
                                <Loader2 v-if="isSubmitting" :size="16" class="animate-spin" />
                                <template v-else>
                                    <Save :size="16" />
                                    <span>Simpan Koreksi Spesifikasi</span>
                                </template>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Past Histories for this IMEI -->
                <div v-if="pastHistories.length > 0" class="card-box p-4 sm:p-6">
                    <div class="flex items-center gap-2 mb-4 pb-2 border-b border-surface-700/60">
                        <History :size="18" class="text-purple-400" />
                        <h3 class="text-sm sm:text-base font-bold text-text-primary">
                            Riwayat Koreksi untuk IMEI Ini ({{ pastHistories.length }})
                        </h3>
                    </div>

                    <div class="space-y-3">
                        <div
                            v-for="(hist, hIdx) in pastHistories"
                            :key="hist.id || hIdx"
                            class="p-3.5 rounded-xl bg-surface-900/60 border border-surface-700 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs"
                        >
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap mb-1">
                                    <span class="badge-xs bg-purple-500/10 text-purple-400 border-purple-500/20 font-bold">KOREKSI #{{ pastHistories.length - hIdx }}</span>
                                    <span class="badge-xs bg-amber-500/10 text-amber-400 border-amber-500/20">CS SALAH: {{ hist.cs_name_mistake }}</span>
                                </div>
                                <div class="flex items-center gap-2 text-text-primary font-bold text-sm flex-wrap mt-1">
                                    <span class="text-red-400 line-through">{{ hist.old_product_name }} ({{ hist.old_storage || '-' }})</span>
                                    <ArrowRight :size="14" class="text-surface-500" />
                                    <span class="text-emerald-400">{{ hist.new_product_name }} ({{ hist.new_storage || '-' }})</span>
                                </div>
                                <p v-if="hist.notes" class="text-text-secondary italic mt-1 text-[11px]">{{ hist.notes }}</p>
                            </div>
                            <div class="text-[11px] text-text-secondary shrink-0 text-left sm:text-right">
                                <p>Oleh: <strong class="text-text-primary">{{ hist.creator?.name || 'Super Admin' }}</strong></p>
                                <p>{{ formatDate(hist.created_at) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Global Audit Log Table -->
            <div class="mt-10 card-box p-4 sm:p-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4 pb-3 border-b border-surface-700/60">
                    <div>
                        <h3 class="text-base sm:text-lg font-bold text-text-primary flex items-center gap-2">
                            <History :size="18" class="text-purple-400" />
                            <span>Audit Log: Semua Koreksi Tipe IMEI</span>
                        </h3>
                        <p class="text-xs text-text-secondary mt-0.5">Daftar rekapan seluruh unit yang pernah dirubah tipe/spesifikasinya oleh Super Admin</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <input
                            v-model="globalSearch"
                            @keyup.enter="loadGlobalHistory"
                            type="text"
                            placeholder="Cari IMEI atau nama CS..."
                            class="input-control h-9 px-3 text-xs w-48 sm:w-60"
                        />
                        <button
                            @click="loadGlobalHistory"
                            :disabled="isGlobalLoading"
                            class="btn-secondary h-9 px-3 text-xs font-semibold"
                        >
                            <Loader2 v-if="isGlobalLoading" :size="13" class="animate-spin" />
                            <span v-else>Cari</span>
                        </button>
                    </div>
                </div>

                <div v-if="isGlobalLoading" class="py-10 text-center">
                    <Loader2 :size="24" class="animate-spin text-purple-400 mx-auto mb-2" />
                    <p class="text-xs text-text-secondary">Memuat audit log...</p>
                </div>

                <div v-else-if="globalHistories.length === 0" class="py-10 text-center text-text-secondary text-xs">
                    Belum ada riwayat koreksi tipe IMEI.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="border-b border-surface-700 text-text-secondary uppercase text-[10px] tracking-wider">
                            <tr>
                                <th class="py-2.5 px-3">Tanggal</th>
                                <th class="py-2.5 px-3">IMEI</th>
                                <th class="py-2.5 px-3">Sebelumnya (Salah)</th>
                                <th class="py-2.5 px-3">Hasil Koreksi (Benar)</th>
                                <th class="py-2.5 px-3">CS Salah Input</th>
                                <th class="py-2.5 px-3">Admin</th>
                                <th class="py-2.5 px-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-700/50">
                            <tr v-for="item in globalHistories" :key="item.id" class="hover:bg-surface-700/20 transition-all">
                                <td class="py-2.5 px-3 whitespace-nowrap text-text-secondary font-mono">{{ formatDate(item.created_at) }}</td>
                                <td class="py-2.5 px-3 font-mono font-bold text-text-primary">{{ item.imei }}</td>
                                <td class="py-2.5 px-3">
                                    <span class="text-red-400 font-medium">{{ item.old_product_name }}</span>
                                    <span class="text-text-secondary text-[11px] block">{{ item.old_storage || '-' }}</span>
                                </td>
                                <td class="py-2.5 px-3">
                                    <span class="text-emerald-400 font-bold">{{ item.new_product_name }}</span>
                                    <span class="text-text-secondary text-[11px] block">{{ item.new_storage || '-' }}</span>
                                </td>
                                <td class="py-2.5 px-3">
                                    <span class="inline-block px-2 py-0.5 rounded text-[11px] font-bold bg-amber-500/10 text-amber-500 border border-amber-500/20">
                                        {{ item.cs_name_mistake }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3 text-text-secondary">{{ item.creator?.name || 'Super Admin' }}</td>
                                <td class="py-2.5 px-3 text-right whitespace-nowrap">
                                    <router-link
                                        :to="`/track?q=${item.imei}`"
                                        class="text-primary-500 hover:text-primary-400 font-bold hover:underline inline-flex items-center gap-1"
                                    >
                                        Lacak
                                    </router-link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>
    </div>
</template>

<style scoped>
@reference "../../style.css";

.card-box {
    @apply bg-surface-800 border border-surface-700 rounded-2xl shadow-sm transition-all;
}

.input-control {
    @apply bg-surface-900 border border-surface-700 rounded-xl px-3.5 text-text-primary placeholder:text-surface-500 focus:outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 transition-all;
}

.btn-primary {
    @apply rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-semibold transition-all disabled:opacity-40 disabled:cursor-not-allowed shadow-sm;
}

.btn-secondary {
    @apply rounded-xl bg-surface-700/60 hover:bg-surface-700 text-text-primary border border-surface-600 font-semibold transition-all disabled:opacity-40 disabled:cursor-not-allowed;
}

.badge-xs {
    @apply inline-flex px-1.5 py-0.5 rounded text-[9px] sm:text-[10px] font-bold uppercase tracking-wider border;
}
</style>
