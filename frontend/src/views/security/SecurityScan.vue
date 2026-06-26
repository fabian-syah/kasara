<script setup>
import { ref, onMounted, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import api, { questions as questionsApi } from "../../api/axios";
import { useAuthStore } from "../../store/auth";
import { useToast } from "../../composables/useToast";
import html2canvas from 'html2canvas';
import { jsPDF } from 'jspdf';
import {
    Loader2, CheckCircle2, ShieldCheck, Plus, Trash2, Smartphone, Package, Box, MapPin, Calendar, Clock, ArrowRight, User, Camera, X as XIcon
} from "lucide-vue-next";

const route = useRoute();
const router = useRouter();
const toast = useToast();

const receiptId = route.params.id;
const isLoading = ref(true);
const isSubmitting = ref(false);
const isSuccess = ref(false);

const transferData = ref(null);
const alreadyCheckedData = ref(null);
const itemChecks = ref([]);

const securityName = ref(route.query.security_name || "");
const inventoryUserId = ref(route.query.inventory_user_id || "");
const mainNotes = ref("");

const excessItems = ref([]);
const brands = ref([]);
const typesCache = ref({});

const getSelectedType = (brandId, typeName) => {
    if (!brandId || !typeName) return null;
    const types = typesCache.value[brandId] || [];
    return types.find(t => t.name === typeName);
};

const getStorageOptions = (item) => {
    const typeObj = getSelectedType(item.brand_id, item.type);
    if (!typeObj || !typeObj.storage) return [];
    return typeObj.storage.split(',').map(s => s.trim()).filter(Boolean);
};

const onTypeChange = (item) => {
    item.storage = "";
};

const addExcessItem = () => {
    excessItems.value.push({
        brand_id: "",
        brand: "",
        type: "",
        storage: "",
        excess_qty: 1,
        notes: "",
        photos: []
    });
};

const removeExcessItem = (index) => {
    excessItems.value.splice(index, 1);
};

const fetchBrands = async () => {
    try {
        const res = await api.get('/brands', { params: { per_page: 100 } });
        brands.value = res.data.data || [];
    } catch (e) {
        console.error(e);
    }
};

const loadTypesForBrand = async (brandId) => {
    if (!brandId) return [];
    if (typesCache.value[brandId]) return typesCache.value[brandId];
    try {
        const res = await api.get('/product-types', { params: { brand_id: brandId, per_page: 100 } });
        typesCache.value[brandId] = res.data.data || [];
        return typesCache.value[brandId];
    } catch (e) {
        console.error(e);
        return [];
    }
};

const onBrandChange = async (item) => {
    item.type = "";
    item.storage = ""; // Reset storage when brand changes (type resets)
    const b = brands.value.find(x => x.id === item.brand_id);
    item.brand = b ? b.name : "";
    if (item.brand_id) {
        await loadTypesForBrand(item.brand_id);
    }
};

const handleQtyChange = (item) => {
    // No photo logic anymore
};

const fetchData = async () => {
    isLoading.value = true;
    try {
        // Fetch transfer details (using existing track endpoint)
        const trackRes = await api.get('/track', { params: { q: receiptId } });
        const results = trackRes.data.data || [];

        // Find the transfer (stock_out pindah_cabang)
        const transfer = results.find(r => r.type === 'stock_out' && r.category === 'pindah_cabang' && r.id === receiptId);

        if (!transfer) {
            toast.error("Surat Jalan tidak ditemukan!");
            return;
        }

        // Check if already scanned
        const historyRes = await api.get('/security-checks/history', { params: { q: receiptId } });
        const historyItems = historyRes.data.data.data || [];
        const existingCheck = historyItems.find(item => item.receipt_id === receiptId);

        if (existingCheck) {
            transferData.value = transfer;
            alreadyCheckedData.value = existingCheck;
            return; // Stop loading questions and brands, just show the checked state
        }

        transferData.value = transfer;

        await fetchBrands();

        // Initialize item checks
        itemChecks.value = transfer.items.map(() => null);

    } catch (e) {
        toast.error("Gagal memuat data: " + (e.response?.data?.message || e.message));
    } finally {
        isLoading.value = false;
    }
};

const pdfTemplate = ref(null);

const generatePDF = async () => {
    if (!pdfTemplate.value) return;

    try {
        const canvas = await html2canvas(pdfTemplate.value, {
            scale: 2,
            useCORS: true,
            logging: false,
            backgroundColor: '#ffffff'
        });

        const imgData = canvas.toDataURL('image/jpeg', 1.0);
        const pdf = new jsPDF({
            orientation: 'portrait',
            unit: 'mm',
            format: 'a4'
        });

        const pdfWidth = pdf.internal.pageSize.getWidth();
        const pdfHeight = (canvas.height * pdfWidth) / canvas.width;

        pdf.addImage(imgData, 'JPEG', 0, 0, pdfWidth, pdfHeight);
        pdf.save(`Bukti_Security_${receiptId}.pdf`);
    } catch (e) {
        console.error("Failed to generate PDF", e);
        toast.error("Gagal membuat PDF otomatis");
    }
};

const submitSecurityCheck = async () => {
    if (!securityName.value.trim()) {
        toast.error("Nama Security harus diisi!");
        return;
    }

    // Check if all items are checked
    if (itemChecks.value.includes(null)) {
        toast.error("Mohon ceklis semua barang pada Daftar Pengecekan!");
        return;
    }

    // Prepare payload
    const payload = {
        receipt_id: receiptId,
        security_name: securityName.value,
        inventory_user_id: inventoryUserId.value,
        notes: mainNotes.value,
        checked_items: transferData.value.items.map((item, idx) => ({
            is_present: itemChecks.value[idx] === 'yes',
            product_name: item.product_name || item.product?.name,
            brand: item.brand || item.brand_name || item.product?.brand
        })),
        excess_items: excessItems.value.filter(item => item.excess_qty > 0).map(item => {
            return {
                brand_id: item.brand_id,
                brand: item.brand,
                type: item.type,
                storage: item.storage,
                excess_qty: item.excess_qty,
                notes: item.notes
            };
        })
    };

    isSubmitting.value = true;
    try {
        await api.post('/security-checks', payload);
        toast.success("Data berhasil disimpan!");
        isSuccess.value = true;

        toast.info("Sedang membuat PDF...");
        await generatePDF();
    } catch (e) {
        toast.error("Gagal menyimpan data: " + (e.response?.data?.message || e.message));
    } finally {
        isSubmitting.value = false;
    }
};

function formatDate(dateString) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('id-ID', {
        day: '2-digit', month: 'short', year: 'numeric'
    });
}

function formatTime(dateString) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleTimeString('id-ID', {
        hour: '2-digit', minute: '2-digit'
    });
}

onMounted(() => {
    fetchData();
});
</script>

<template>
    <div class="max-w-3xl mx-auto px-4 py-8 sm:py-12">
        <!-- Header -->
        <div class="text-center mb-8">
            <div
                class="w-16 h-16 mx-auto bg-primary-500/10 rounded-2xl flex items-center justify-center mb-4 border border-primary-500/20 shadow-inner">
                <ShieldCheck :size="32" class="text-primary-500" />
            </div>
            <h1 class="text-2xl font-bold text-text-primary tracking-tight">Security Check</h1>
            <p class="text-text-secondary mt-1 text-sm">Inspeksi Pengiriman Cabang</p>
            <div
                class="mt-3 inline-flex items-center gap-2 px-3 py-1 bg-surface-800 rounded-lg border border-surface-700">
                <span class="text-xs text-text-secondary uppercase tracking-wider">RESI:</span>
                <span class="text-sm font-mono font-bold text-text-primary">{{ receiptId }}</span>
            </div>
        </div>

        <div v-if="isLoading" class="flex flex-col items-center py-20">
            <Loader2 :size="32" class="animate-spin text-primary-500 mb-4" />
            <p class="text-text-secondary text-sm font-medium animate-pulse">Memuat data surat jalan...</p>
        </div>

        <div v-else-if="isSuccess"
            class="bg-surface-800 border border-green-500/30 rounded-2xl p-8 text-center shadow-lg shadow-green-500/10">
            <div class="w-20 h-20 bg-green-500/10 rounded-full flex items-center justify-center mx-auto mb-5">
                <CheckCircle2 :size="40" class="text-green-500" />
            </div>
            <h2 class="text-2xl font-bold text-text-primary mb-2">Pengecekan Selesai</h2>
            <p class="text-text-secondary mb-6 max-w-md mx-auto">Data inspeksi security untuk surat jalan {{ receiptId
                }} telah berhasil disimpan ke sistem.</p>
            <button @click="router.push('/security_scan/history')"
                class="px-6 py-2.5 bg-surface-700 hover:bg-surface-600 text-text-primary rounded-xl font-medium transition-all shadow-sm">
                Ke History Security
            </button>
        </div>

        <div v-else-if="alreadyCheckedData"
            class="bg-surface-800 border border-amber-500/30 rounded-2xl p-8 text-center shadow-lg shadow-amber-500/10">
            <div
                class="w-20 h-20 bg-amber-500/10 rounded-full flex items-center justify-center mx-auto mb-5 border border-amber-500/20">
                <ShieldCheck :size="40" class="text-amber-500" />
            </div>
            <h2 class="text-2xl font-bold text-text-primary mb-2">Sudah Dicek!</h2>
            <p class="text-text-secondary mb-6 max-w-md mx-auto">Surat jalan <span
                    class="font-bold text-text-primary">{{ receiptId }}</span> sudah pernah di-scan dan di-ACC oleh
                security.</p>

            <div
                class="bg-surface-900 border border-surface-700 rounded-xl p-5 text-left inline-block w-full max-w-sm mx-auto mb-6 shadow-inner">
                <div class="flex flex-col gap-4">
                    <div class="flex items-start gap-3">
                        <div class="p-2 bg-surface-800 rounded-lg">
                            <User :size="16" class="text-surface-400" />
                        </div>
                        <div>
                            <span class="block text-[10px] text-text-secondary uppercase tracking-wider mb-0.5">Security
                                & Staff Inventory</span>
                            <span class="text-sm font-bold text-text-primary block">Sec: {{
                                alreadyCheckedData.security_name }}</span>
                            <span class="text-sm text-text-secondary block">Inv: {{
                                alreadyCheckedData.inventory_user?.name || '-' }}</span>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="p-2 bg-surface-800 rounded-lg">
                            <MapPin :size="16" class="text-surface-400" />
                        </div>
                        <div>
                            <span class="block text-[10px] text-text-secondary uppercase tracking-wider mb-0.5">Lokasi
                                Pengecekan</span>
                            <span class="text-sm font-bold text-text-primary">{{ transferData?.destination?.name ||
                                'Cabang Tujuan' }}</span>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4 pt-2 border-t border-surface-800">
                        <div class="flex items-start gap-3">
                            <div class="p-2 bg-surface-800 rounded-lg">
                                <Calendar :size="16" class="text-surface-400" />
                            </div>
                            <div>
                                <span
                                    class="block text-[10px] text-text-secondary uppercase tracking-wider mb-0.5">Tanggal</span>
                                <span class="text-xs font-bold text-text-primary">{{
                                    formatDate(alreadyCheckedData.created_at) }}</span>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="p-2 bg-surface-800 rounded-lg">
                                <Clock :size="16" class="text-surface-400" />
                            </div>
                            <div>
                                <span
                                    class="block text-[10px] text-text-secondary uppercase tracking-wider mb-0.5">Jam</span>
                                <span class="text-xs font-bold text-text-primary">{{
                                    formatTime(alreadyCheckedData.created_at) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <button @click="router.push('/security_scan/history')"
                    class="px-6 py-2.5 bg-surface-700 hover:bg-surface-600 text-text-primary rounded-xl font-medium transition-all shadow-sm">
                    Kembali ke History
                </button>
            </div>
        </div>

        <div v-else-if="transferData" class="space-y-6">

            <!-- Detail Pengiriman -->
            <div class="bg-surface-800 border border-surface-700 rounded-2xl overflow-hidden shadow-sm">
                <div class="p-4 bg-surface-800/80 border-b border-surface-700 flex items-center gap-2.5">
                    <MapPin :size="18" class="text-primary-500" />
                    <h2 class="font-bold text-text-primary">Detail Pengiriman</h2>
                </div>
                <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="bg-surface-900/50 p-3 rounded-xl border border-surface-700/50">
                        <p class="text-[10px] text-text-secondary uppercase tracking-wider mb-1">Asal Cabang</p>
                        <p class="text-sm font-semibold text-text-primary">{{ transferData.source_name || '-' }}</p>
                        <p class="text-xs text-text-secondary mt-1">Pengirim: {{ transferData.processed_by || '-' }}</p>
                    </div>
                    <div class="bg-surface-900/50 p-3 rounded-xl border border-surface-700/50 relative">
                        <ArrowRight :size="16"
                            class="absolute -left-3 top-1/2 -translate-y-1/2 text-surface-600 hidden sm:block" />
                        <p class="text-[10px] text-text-secondary uppercase tracking-wider mb-1">Tujuan Cabang</p>
                        <p class="text-sm font-semibold text-text-primary">{{ transferData.destination?.name || '-' }}
                        </p>
                        <p class="text-xs text-text-secondary mt-1">Penerima: {{ transferData.receiver_name || '-' }}
                        </p>
                    </div>
                    <div class="flex items-center gap-3 bg-surface-900/50 p-3 rounded-xl border border-surface-700/50">
                        <Calendar :size="16" class="text-surface-500" />
                        <div>
                            <p class="text-[10px] text-text-secondary uppercase tracking-wider mb-0.5">Tanggal</p>
                            <p class="text-xs font-semibold text-text-primary">{{ formatDate(transferData.created_at) }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 bg-surface-900/50 p-3 rounded-xl border border-surface-700/50">
                        <Clock :size="16" class="text-surface-500" />
                        <div>
                            <p class="text-[10px] text-text-secondary uppercase tracking-wider mb-0.5">Jam</p>
                            <p class="text-xs font-semibold text-text-primary">{{ formatTime(transferData.created_at) }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- List Barang -->
            <div class="bg-surface-800 border border-surface-700 rounded-2xl overflow-hidden shadow-sm">
                <div class="p-4 bg-surface-800/80 border-b border-surface-700 flex items-center gap-2.5">
                    <Package :size="18" class="text-primary-500" />
                    <h2 class="font-bold text-text-primary">Daftar Barang ({{ transferData.items?.length || 0 }})</h2>
                </div>
                <div class="divide-y divide-surface-700">
                    <div v-for="(item, idx) in transferData.items" :key="idx"
                        class="p-4 hover:bg-surface-800/50 transition-colors flex items-start gap-3">
                        <div class="p-2 rounded-lg bg-surface-700 mt-0.5 shrink-0">
                            <component :is="item.imei && item.imei !== '-' ? Smartphone : Box" :size="16"
                                class="text-surface-400" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1 mb-1">
                                <span class="text-xs font-bold text-primary-400 uppercase tracking-wider">{{
                                    item.brand_name || 'Brand' }}</span>
                                <h3 class="text-sm font-semibold text-text-primary">{{ item.product_name }}</h3>
                            </div>

                            <div class="flex flex-wrap gap-x-4 gap-y-2 text-xs">
                                <div v-if="item.imei && item.imei !== '-'">
                                    <span class="text-text-secondary">IMEI:</span>
                                    <span
                                        class="font-mono text-text-primary ml-1 bg-surface-900 px-1.5 py-0.5 rounded">{{
                                        item.imei }}</span>
                                </div>
                                <div v-else>
                                    <span class="text-text-secondary">Qty:</span>
                                    <span class="font-bold text-text-primary ml-1">{{ item.quantity }}</span>
                                </div>
                                <div v-if="item.condition">
                                    <span class="text-text-secondary">Kondisi:</span>
                                    <span class="text-text-primary ml-1 capitalize">{{ item.condition }}</span>
                                </div>
                                <div v-if="item.storage">
                                    <span class="text-text-secondary">Storage:</span>
                                    <span class="text-text-primary ml-1">{{ item.storage }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Pemeriksaan -->
            <div
                class="bg-surface-800 border border-primary-500/30 rounded-2xl overflow-hidden shadow-sm shadow-primary-500/5 relative">
                <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-primary-500 to-indigo-500"></div>
                <div class="p-5 sm:p-6 space-y-6">

                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-2 flex items-center gap-2">
                            <User :size="16" /> Staff Pemeriksa
                        </label>
                        <input v-model="securityName" type="text" disabled
                            class="w-full bg-surface-800 border border-surface-700 rounded-xl px-4 py-3 text-surface-400 font-semibold cursor-not-allowed" />
                    </div>

                    <!-- Pengecekan Barang -->
                    <div v-if="transferData?.items?.length > 0" class="space-y-4 pt-4 border-t border-surface-700">
                        <h3 class="font-bold text-text-primary flex items-center gap-2">
                            <ShieldCheck :size="18" class="text-primary-500" /> Checklist Fisik Barang
                        </h3>
                        <div v-for="(item, idx) in transferData.items" :key="'check-'+idx"
                            class="bg-surface-900/50 border border-surface-700 p-4 rounded-xl flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div class="flex-1">
                                <p class="text-sm font-bold text-text-primary">{{ item.brand || item.brand_name || item.product?.brand || '' }} {{ item.product_name || item.product?.name || '' }}</p>
                                <p class="text-xs text-text-secondary mt-1">
                                    <span v-if="item.imei && item.imei !== '-'">IMEI: {{ item.imei }}</span>
                                    <span v-else>Qty: {{ item.quantity || item.qty }}</span>
                                    <span v-if="item.storage"> | {{ item.storage }}</span>
                                </p>
                            </div>
                            <div class="flex items-center gap-3 shrink-0">
                                <label class="flex items-center gap-2 cursor-pointer group">
                                    <input type="radio" :name="'check_' + idx" value="yes" v-model="itemChecks[idx]"
                                        class="sr-only" />
                                    <div class="w-5 h-5 rounded-full border flex items-center justify-center transition-colors"
                                        :class="itemChecks[idx] === 'yes' ? 'border-green-500 bg-green-500 text-white' : 'border-surface-500 bg-surface-800 group-hover:border-green-400'">
                                        <div v-if="itemChecks[idx] === 'yes'" class="w-2 h-2 bg-white rounded-full"></div>
                                    </div>
                                    <span class="text-sm font-medium"
                                        :class="itemChecks[idx] === 'yes' ? 'text-green-500' : 'text-text-secondary'">Ada / Sesuai</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer group">
                                    <input type="radio" :name="'check_' + idx" value="no" v-model="itemChecks[idx]"
                                        class="sr-only" />
                                    <div class="w-5 h-5 rounded-full border flex items-center justify-center transition-colors"
                                        :class="itemChecks[idx] === 'no' ? 'border-red-500 bg-red-500 text-white' : 'border-surface-500 bg-surface-800 group-hover:border-red-400'">
                                        <div v-if="itemChecks[idx] === 'no'" class="w-2 h-2 bg-white rounded-full"></div>
                                    </div>
                                    <span class="text-sm font-medium"
                                        :class="itemChecks[idx] === 'no' ? 'text-red-500' : 'text-text-secondary'">Tidak Ada</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Kelebihan Barang -->
                    <div class="pt-4 border-t border-surface-700 space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="font-bold text-text-primary flex items-center gap-2">
                                    <Plus :size="18" class="text-amber-500" /> Info Kelebihan Barang
                                </h3>
                                <p class="text-[11px] text-text-secondary mt-0.5">Isi jika ada barang lebih dari nota
                                </p>
                            </div>
                        </div>

                        <div v-if="excessItems.length === 0"
                            class="text-center py-6 bg-surface-900/30 border border-surface-700 border-dashed rounded-xl">
                            <p class="text-sm text-text-secondary mb-3">Tidak ada barang lebih?</p>
                            <button @click="addExcessItem" type="button"
                                class="inline-flex items-center gap-2 px-4 py-2 bg-surface-700 hover:bg-surface-600 text-text-primary rounded-lg text-sm font-medium transition-colors">
                                <Plus :size="16" /> Tambah Info Kelebihan
                            </button>
                        </div>

                        <div v-for="(item, idx) in excessItems" :key="idx"
                            class="bg-surface-900 border border-surface-700 rounded-xl p-4 relative group">
                            <button @click="removeExcessItem(idx)"
                                class="absolute -top-3 -right-3 w-8 h-8 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center shadow-lg transition-transform active:scale-95 z-10">
                                <Trash2 :size="14" />
                            </button>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-text-secondary mb-1">Brand</label>
                                    <select v-model="item.brand_id" @change="onBrandChange(item)"
                                        class="w-full bg-surface-800 border border-surface-700 rounded-lg px-3 py-2 text-sm text-text-primary focus:outline-none focus:border-primary-500">
                                        <option value="">Pilih Brand</option>
                                        <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-text-secondary mb-1">Tipe
                                        Barang</label>
                                    <select v-model="item.type" @change="onTypeChange(item)" :disabled="!item.brand_id"
                                        class="w-full bg-surface-800 border border-surface-700 rounded-lg px-3 py-2 text-sm text-text-primary focus:outline-none focus:border-primary-500 disabled:opacity-50">
                                        <option value="">Pilih Tipe</option>
                                        <option v-for="t in (typesCache[item.brand_id] || [])" :key="t.id"
                                            :value="t.name">{{ t.name }}</option>
                                    </select>
                                </div>
                                <div class="grid grid-cols-2 gap-4 sm:col-span-2">
                                    <div
                                        v-if="['imei', 'HP / Gadget'].includes(getSelectedType(item.brand_id, item.type)?.category)">
                                        <label
                                            class="block text-xs font-medium text-text-secondary mb-1">Storage</label>
                                        <select v-model="item.storage"
                                            class="w-full bg-surface-800 border border-surface-700 rounded-lg px-3 py-2 text-sm text-text-primary focus:outline-none focus:border-primary-500">
                                            <option value="">Pilih Storage</option>
                                            <option v-for="s in getStorageOptions(item)" :key="s" :value="s">{{ s }}
                                            </option>
                                        </select>
                                    </div>
                                    <div
                                        :class="['imei', 'HP / Gadget'].includes(getSelectedType(item.brand_id, item.type)?.category) ? '' : 'col-span-2'">
                                        <label class="block text-xs font-medium text-text-secondary mb-1">Total Unit
                                            Lebih</label>
                                        <input v-model.number="item.excess_qty" @change="handleQtyChange(item)"
                                            type="number" min="1"
                                            class="w-full bg-surface-800 border border-surface-700 rounded-lg px-3 py-2 text-sm text-text-primary focus:outline-none focus:border-primary-500" />
                                    </div>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-medium text-text-secondary mb-1">Keterangan /
                                        IMEI</label>
                                    <input v-model="item.notes" type="text"
                                        placeholder="Isi IMEI jika ada, atau info tambahan"
                                        class="w-full bg-surface-800 border border-surface-700 rounded-lg px-3 py-2 text-sm text-text-primary focus:outline-none focus:border-primary-500" />
                                </div>
                            </div>
                        </div>

                        <button v-if="excessItems.length > 0" @click="addExcessItem" type="button"
                            class="w-full py-2.5 border-2 border-dashed border-surface-600 text-surface-400 hover:border-primary-500 hover:text-primary-500 rounded-xl text-sm font-medium transition-colors flex items-center justify-center gap-2">
                            <Plus :size="16" /> Tambah Barang Lain
                        </button>
                    </div>

                    <!-- Catatan Tambahan -->
                    <div class="pt-4 border-t border-surface-700">
                        <label class="block text-sm font-medium text-text-secondary mb-2">Catatan Tambahan
                            (Opsional)</label>
                        <textarea v-model="mainNotes" rows="2" placeholder="Masukkan catatan lain jika diperlukan..."
                            class="w-full bg-surface-900 border border-surface-700 rounded-xl px-4 py-3 text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/50"></textarea>
                    </div>

                </div>
            </div>

            <!-- Submit Button -->
            <div class="pt-4 pb-8 flex justify-end">
                <button @click="submitSecurityCheck" :disabled="isSubmitting"
                    class="w-full sm:w-auto px-8 py-3.5 bg-primary-600 hover:bg-primary-500 text-white rounded-xl font-bold transition-all shadow-lg shadow-primary-500/25 active:scale-95 disabled:opacity-50 flex justify-center items-center gap-2">
                    <Loader2 v-if="isSubmitting" class="animate-spin" :size="20" />
                    <ShieldCheck v-else :size="20" />
                    <span>Selesai & Simpan Pengecekan</span>
                </button>
            </div>
        </div>

    </div>

    <!-- Hidden Print Area for PDF Generation -->
    <div class="fixed" style="top: -9999px; left: -9999px;">
        <div ref="pdfTemplate" class="w-[800px] bg-white p-8 text-black relative"
            style="font-family: sans-serif; min-height: 1122px;">
            <!-- Watermark -->
            <div class="absolute inset-0 flex items-center justify-center opacity-[0.05] pointer-events-none z-0">
                <img src="/images/ps.png" alt="" class="w-[600px] h-[600px] object-contain" />
            </div>

            <div class="relative z-10">
                <div class="flex items-center justify-between mb-6 border-b-2 border-black pb-4">
                    <div class="flex items-center gap-4">
                        <img src="/images/logo-pstore.png" alt="PSTORE" class="h-12 object-contain" />
                        <div>
                            <h1 class="text-2xl font-bold uppercase">Bukti Pengecekan Security</h1>
                            <p class="text-sm mt-1">No. Surat Jalan: {{ receiptId }}</p>
                        </div>
                    </div>
                </div>

                <div class="flex justify-between mb-6">
                    <div>
                        <p class="text-xs text-gray-500 uppercase">Dari Cabang</p>
                        <p class="font-bold">{{ transferData?.source_name || '-' }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-gray-500 uppercase">Tujuan Cabang</p>
                        <p class="font-bold">{{ transferData?.destination?.name || '-' }}</p>
                    </div>
                </div>

                <div class="mb-6">
                    <p class="text-xs text-gray-500 uppercase mb-2">Data Pemeriksa</p>
                    <p><strong>Security:</strong> {{ securityName }}</p>
                    <p><strong>Waktu Selesai:</strong> {{ new Date().toLocaleString('id-ID') }}</p>
                </div>

                <div class="mb-10">
                    <h2 class="font-bold border-b border-gray-300 pb-2 mb-4 flex justify-between items-end">
                        <span>Daftar Barang (Bawaan)</span>
                        <span class="text-xs font-normal">Total: {{ transferData?.items?.length || 0 }}</span>
                    </h2>
                    <table class="w-full text-xs border-collapse border border-black text-black font-bold">
                        <thead>
                            <tr>
                                <th class="border border-black px-2 py-2 text-center uppercase w-10">NO</th>
                                <th class="border border-black px-3 py-2 text-left uppercase">DESKRIPSI
                                    BARANG<br>(MEREK, TIPE)</th>
                                <th class="border border-black px-3 py-2 text-left uppercase w-36">IMEI</th>
                                <th class="border border-black px-3 py-2 text-left uppercase w-28">KONDISI</th>
                                <th class="border border-black px-2 py-2 text-center uppercase w-12">QTY</th>
                                <th class="border border-black px-2 py-2 text-center uppercase w-16">CEK</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, idx) in transferData?.items" :key="'item-' + idx">
                                <td class="border border-black px-2 py-2 text-center align-middle">{{ idx + 1 }}</td>
                                <td class="border border-black px-3 py-2 align-middle uppercase">
                                    {{ item.brand || item.brand_name || item.product?.brand || '' }} {{
                                        item.product_name || item.product?.name || '' }} <span v-if="item.storage">{{
                                        item.storage }}</span>
                                </td>
                                <td class="border border-black px-3 py-2 align-middle uppercase">
                                    <template v-if="item.imei && item.imei !== '-'">{{ item.imei }}</template>
                                    <template v-else>-</template>
                                </td>
                                <td class="border border-black px-3 py-2 align-middle uppercase">
                                    {{ item.condition || '-' }}
                                </td>
                                <td class="border border-black px-2 py-2 text-center align-middle">
                                    <template v-if="item.imei && item.imei !== '-'">1</template>
                                    <template v-else>{{ item.quantity || item.qty }}</template>
                                </td>
                                <td class="border border-black px-2 py-2 text-center align-middle">
                                    <span v-if="itemChecks[idx] === 'yes'" class="text-green-600">✔</span>
                                    <span v-else-if="itemChecks[idx] === 'no'" class="text-red-600">✘</span>
                                    <span v-else>-</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mb-10">
                    <h2 class="font-bold border-b border-gray-300 pb-2 mb-4">Barang Lebih / Tambahan</h2>
                    <ul v-if="excessItems.length > 0" class="list-disc pl-5 text-sm space-y-1">
                        <li v-for="(item, idx) in excessItems" :key="idx">
                            <strong>{{ item.brand }} - {{ item.type }}</strong>
                            <span v-if="item.storage"> - {{ item.storage }}</span>
                            (Qty: {{ item.excess_qty }})
                            <span v-if="item.notes">- <em>{{ item.notes }}</em></span>
                        </li>
                    </ul>
                    <p v-else class="text-sm italic text-gray-500 mt-2">Tidak ada barang tambahan</p>
                </div>

                <div v-if="mainNotes" class="mb-10">
                    <h2 class="font-bold border-b border-gray-300 pb-2 mb-4">Catatan Tambahan</h2>
                    <p class="text-sm bg-gray-50 p-4 rounded-lg border border-gray-200">{{ mainNotes }}</p>
                </div>

            </div>
        </div>
    </div>

</template>
