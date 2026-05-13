<script setup>
import { ref, onMounted, computed, watch } from "vue";
import { useAuthStore } from "../../store/auth";
import axios from "../../api/axios";
import { useToast } from "../../composables/useToast";
import { 
    FileText, Save, Loader2, MapPin, Phone, Instagram, Send, ShieldCheck,
    Info, Building2, ShoppingBag
} from "lucide-vue-next";

const authStore = useAuthStore();
const toast = useToast();

const isLoading = ref(false);
const isSaving = ref(false);
const isLoadingLocations = ref(false);

// Available Locations for Super Admin / Privileged Users
const branches = ref([]);
const onlineShops = ref([]);

// Active target configurations
const selectedType = ref('branch'); // 'branch' or 'online_shop'
const selectedLocationId = ref(null);

const isPrivileged = computed(() => {
    const role = authStore.userRole;
    return ['super_admin', 'owner', 'audit', 'analist'].includes(role);
});

const form = ref({
    store_address: "",
    whatsapp_number: "",
    instagram: "",
    tiktok: "",
    warranty_terms: ""
});

// Helper to get user location label
const currentUserLocationName = computed(() => {
    if (authStore.user?.branch) {
        return `Cabang: ${authStore.user.branch.name}`;
    }
    if (authStore.user?.online_shop) {
        return `Online: ${authStore.user.online_shop.name}`;
    }
    return "Tidak ditugaskan ke cabang manapun";
});

// Default location load
async function initializeLocations() {
    if (!isPrivileged.value) {
        // Force non-privileged to their assigned IDs
        if (authStore.user?.branch_id) {
            selectedType.value = 'branch';
            selectedLocationId.value = authStore.user.branch_id;
        } else if (authStore.user?.online_shop_id) {
            selectedType.value = 'online_shop';
            selectedLocationId.value = authStore.user.online_shop_id;
        }
        return;
    }

    // Privileged users load selection lists
    isLoadingLocations.value = true;
    try {
        const [branchRes, onlineRes] = await Promise.all([
            axios.get('/branches'),
            axios.get('/online-shops')
        ]);

        branches.value = branchRes.data.data || branchRes.data;
        onlineShops.value = onlineRes.data.data || onlineRes.data;

        // Select first available location
        if (branches.value.length > 0) {
            selectedType.value = 'branch';
            selectedLocationId.value = branches.value[0].id;
        } else if (onlineShops.value.length > 0) {
            selectedType.value = 'online_shop';
            selectedLocationId.value = onlineShops.value[0].id;
        }
    } catch (e) {
        console.error("Failed to fetch locations", e);
        toast.error("Gagal memuat daftar cabang/toko online.");
    } finally {
        isLoadingLocations.value = false;
    }
}

async function fetchSettings() {
    if (!selectedLocationId.value) return;

    isLoading.value = true;
    try {
        const params = {};
        if (selectedType.value === 'branch') {
            params.branch_id = selectedLocationId.value;
        } else {
            params.online_shop_id = selectedLocationId.value;
        }

        const res = await axios.get('/receipt-settings', { params });
        const data = res.data.data;

        if (data) {
            form.value.store_address = data.store_address || "";
            form.value.whatsapp_number = data.whatsapp_number || "";
            form.value.instagram = data.instagram || "";
            form.value.tiktok = data.tiktok || "";
            form.value.warranty_terms = data.warranty_terms || "";
        } else {
            // Empty fallback so user starts fresh
            form.value.store_address = "";
            form.value.whatsapp_number = "";
            form.value.instagram = "";
            form.value.tiktok = "";
            form.value.warranty_terms = "";
        }
    } catch (e) {
        console.error("Failed to fetch receipt setting", e);
        toast.error("Gagal mengambil data setting nota.");
    } finally {
        isLoading.value = false;
    }
}

async function handleSave() {
    if (!selectedLocationId.value) {
        toast.error("Pilih cabang atau toko online yang akan disetting.");
        return;
    }

    isSaving.value = true;
    try {
        const payload = {
            ...form.value
        };

        if (selectedType.value === 'branch') {
            payload.branch_id = selectedLocationId.value;
        } else {
            payload.online_shop_id = selectedLocationId.value;
        }

        const res = await axios.post('/receipt-settings', payload);
        toast.success(res.data.message || "Setting nota berhasil disimpan!");
    } catch (e) {
        console.error("Failed to save receipt setting", e);
        toast.error("Gagal menyimpan setting nota.");
    } finally {
        isSaving.value = false;
    }
}

// Re-fetch when location selections change
watch([selectedType, selectedLocationId], () => {
    fetchSettings();
});

// Watch category type changes to pick first element
watch(selectedType, (newType) => {
    if (!isPrivileged.value) return;
    if (newType === 'branch' && branches.value.length > 0) {
        selectedLocationId.value = branches.value[0].id;
    } else if (newType === 'online_shop' && onlineShops.value.length > 0) {
        selectedLocationId.value = onlineShops.value[0].id;
    } else {
        selectedLocationId.value = null;
    }
});

onMounted(async () => {
    await initializeLocations();
    await fetchSettings();
});
</script>

<template>
    <div class="space-y-6 pb-24 page-fade-in text-zinc-900 dark:text-zinc-50">
        <!-- Top Glassmorphic Header (Emerald Green Accent) -->
        <div class="relative rounded-[2rem] overflow-hidden bg-gradient-to-br from-emerald-600 to-teal-700 dark:from-emerald-900 dark:to-zinc-950 border border-emerald-500/30 dark:border-zinc-800/50 shadow-2xl p-6 sm:p-8 flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="flex items-center gap-4 relative z-10">
                <div class="p-4 bg-white/10 backdrop-blur-lg border border-white/20 rounded-[1.5rem] shadow-inner">
                    <FileText class="w-8 h-8 text-white" />
                </div>
                <div>
                    <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight uppercase">Setting Nota</h1>
                    <p class="text-emerald-50 text-xs font-bold uppercase tracking-wider mt-1 opacity-95">Kustomisasi tampilan nota fisik dan struk transaksi</p>
                </div>
            </div>
            
            <button @click="handleSave" type="button" :disabled="isSaving || isLoading" class="btn bg-white text-emerald-800 hover:bg-emerald-50 font-black rounded-2xl px-6 py-3 tracking-widest uppercase shadow-lg active:scale-95 flex items-center gap-2 relative z-10">
                <Loader2 v-if="isSaving" class="animate-spin text-emerald-800" :size="16" />
                <Save v-else class="text-emerald-800" :size="16" />
                <span>Simpan Perubahan</span>
            </button>

            <!-- Background Glow Accents in Teal/Emerald -->
            <div class="absolute top-0 right-0 w-96 h-96 bg-emerald-400/20 blur-3xl rounded-full -translate-y-1/2 translate-x-1/3"></div>
            <div class="absolute bottom-0 left-0 w-64 h-64 bg-teal-500/20 blur-3xl rounded-full translate-y-1/3 -translate-x-1/3"></div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- LEFT SIDE: Selection & Configuration Panel -->
            <div class="lg:col-span-4 space-y-6">
                <!-- Target Selection Card -->
                <div class="card bg-white dark:bg-zinc-900/90 border border-zinc-200/60 dark:border-zinc-800/70 p-6 rounded-[2rem] shadow-xl space-y-5">
                    <div class="flex items-center gap-2 pb-3 border-b border-zinc-100 dark:border-zinc-800/50">
                        <Building2 :size="18" class="text-emerald-600 dark:text-emerald-400" />
                        <h3 class="text-sm font-black text-zinc-900 dark:text-zinc-50 uppercase tracking-wider">Lokasi Target</h3>
                    </div>

                    <div v-if="isPrivileged" class="space-y-4">
                        <!-- Privileged Selection Grid -->
                        <div class="space-y-1.5">
                            <label class="label">Jenis Lokasi</label>
                            <div class="grid grid-cols-2 bg-zinc-50 dark:bg-zinc-950 p-1 rounded-xl gap-1 border border-zinc-200 dark:border-zinc-850">
                                <button @click="selectedType = 'branch'" type="button"
                                    class="flex items-center justify-center gap-1.5 py-2 rounded-lg text-[10px] font-black uppercase transition-all"
                                    :class="selectedType === 'branch' ? 'bg-emerald-600 text-white shadow-md' : 'text-zinc-600 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-900'">
                                    <Building2 :size="12" />
                                    Cabang Fisik
                                </button>
                                <button @click="selectedType = 'online_shop'" type="button"
                                    class="flex items-center justify-center gap-1.5 py-2 rounded-lg text-[10px] font-black uppercase transition-all"
                                    :class="selectedType === 'online_shop' ? 'bg-emerald-600 text-white shadow-md' : 'text-zinc-600 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-900'">
                                    <ShoppingBag :size="12" />
                                    Toko Online
                                </button>
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <label class="label">Pilih Target</label>
                            <div v-if="isLoadingLocations" class="flex items-center justify-center py-3 text-zinc-500">
                                <Loader2 class="animate-spin mr-2" :size="16" />
                                <span class="text-xs font-bold">Memuat data...</span>
                            </div>
                            <select v-else v-model="selectedLocationId" class="input uppercase font-black">
                                <template v-if="selectedType === 'branch'">
                                    <option v-for="b in branches" :key="b.id" :value="b.id" class="bg-white dark:bg-zinc-950">{{ b.name }}</option>
                                </template>
                                <template v-else>
                                    <option v-for="os in onlineShops" :key="os.id" :value="os.id" class="bg-white dark:bg-zinc-950">{{ os.name }} ({{ os.platform }})</option>
                                </template>
                            </select>
                        </div>
                    </div>

                    <!-- Non-privileged Display -->
                    <div v-else class="bg-zinc-50 dark:bg-zinc-950 rounded-2xl p-4 border border-zinc-200 dark:border-zinc-850 space-y-2">
                        <div class="flex items-center gap-2 text-emerald-600 dark:text-emerald-400">
                            <ShieldCheck :size="16" />
                            <span class="text-[10px] font-black uppercase tracking-widest">Terkunci ke Akun Anda</span>
                        </div>
                        <p class="text-xs text-zinc-900 dark:text-white font-black uppercase leading-tight">{{ currentUserLocationName }}</p>
                    </div>
                </div>

                <!-- Help Tips Card -->
                <div class="card bg-white dark:bg-zinc-900/90 border border-zinc-200/60 dark:border-zinc-800/70 p-6 rounded-[2rem] shadow-xl space-y-4">
                    <div class="flex items-center gap-2 text-emerald-600 dark:text-emerald-400 pb-3 border-b border-zinc-100 dark:border-zinc-800/50">
                        <Info :size="18" />
                        <h3 class="text-sm font-black text-zinc-900 dark:text-zinc-50 uppercase tracking-wider">Panduan Setting</h3>
                    </div>
                    <ul class="space-y-3 text-[11px] text-zinc-600 dark:text-zinc-200 font-bold leading-relaxed">
                        <li class="flex items-start gap-2">
                            <div class="w-1.5 h-1.5 bg-emerald-600 dark:bg-emerald-400 rounded-full mt-1.5 shrink-0"></div>
                            <span>Jika isian dikosongkan, sistem akan otomatis menggunakan alamat dan nomor WhatsApp default bawaan database pusat.</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <div class="w-1.5 h-1.5 bg-emerald-600 dark:bg-emerald-400 rounded-full mt-1.5 shrink-0"></div>
                            <span>Instagram & TikTok akan muncul di bagian kanan atas kertas nota di sebelah judul struk.</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <div class="w-1.5 h-1.5 bg-emerald-600 dark:bg-emerald-400 rounded-full mt-1.5 shrink-0"></div>
                            <span>Keterangan garansi mendukung pergantian baris (paragraf) untuk memudahkan pengelompokan pasal-pasal garansi toko.</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- RIGHT SIDE: Settings Form -->
            <div class="lg:col-span-8">
                <div class="card bg-white dark:bg-zinc-900/90 border border-zinc-200/60 dark:border-zinc-800/70 p-6 sm:p-8 rounded-[2.5rem] shadow-xl relative overflow-hidden">
                    
                    <!-- Main Form Wrapper with inner absolute loader for fetching -->
                    <div v-if="isLoading" class="absolute inset-0 bg-white/80 dark:bg-zinc-900/80 backdrop-blur-sm z-20 flex flex-col items-center justify-center">
                        <Loader2 class="w-10 h-10 text-emerald-600 dark:text-emerald-400 animate-spin mb-3" />
                        <p class="text-xs font-black text-zinc-600 dark:text-zinc-300 uppercase tracking-widest">Sedang Mengambil Setting...</p>
                    </div>

                    <div class="flex items-center justify-between pb-5 border-b border-zinc-100 dark:border-zinc-800/50 mb-6">
                        <div class="flex items-center gap-2.5">
                            <FileText :size="18" class="text-emerald-600 dark:text-emerald-400" />
                            <h3 class="text-sm font-black text-zinc-900 dark:text-zinc-50 uppercase tracking-wider">Rincian Informasi Nota</h3>
                        </div>
                        <span class="text-[9px] font-black text-emerald-600 dark:text-emerald-400 tracking-widest uppercase">CUSTOM FIELD</span>
                    </div>

                    <div class="space-y-6">
                        <!-- Store Address Override -->
                        <div class="space-y-1.5">
                            <label class="label flex items-center gap-1.5">
                                <MapPin :size="12" class="text-emerald-600 dark:text-emerald-400" />
                                Alamat Toko di Nota
                            </label>
                            <textarea v-model="form.store_address" class="input min-h-[80px] resize-none font-bold text-sm leading-normal" 
                                placeholder="Contoh: Jl. Alternatif Cibubur No. 99, Harjamukti, Cimanggis, Kota Depok, Jawa Barat"></textarea>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <!-- WhatsApp Number -->
                            <div class="space-y-1.5">
                                <label class="label flex items-center gap-1.5">
                                    <Phone :size="12" class="text-emerald-600 dark:text-emerald-400" />
                                    Nomor WhatsApp Toko
                                </label>
                                <input v-model="form.whatsapp_number" type="text" class="input font-black text-sm tracking-wide" placeholder="Contoh: 081299998888" />
                            </div>
                            
                            <!-- Blank to fill grid -->
                            <div class="hidden sm:block"></div>

                            <!-- Instagram Profile -->
                            <div class="space-y-1.5">
                                <label class="label flex items-center gap-1.5">
                                    <Instagram :size="12" class="text-emerald-600 dark:text-emerald-400" />
                                    Username Instagram
                                </label>
                                <div class="relative">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-zinc-500 dark:text-zinc-200 font-black text-xs">@</span>
                                    <input v-model="form.instagram" type="text" class="input pl-8 font-black text-sm" placeholder="pstore.official" />
                                </div>
                            </div>

                            <!-- TikTok Profile -->
                            <div class="space-y-1.5">
                                <label class="label flex items-center gap-1.5">
                                    <Send :size="12" class="text-emerald-600 dark:text-emerald-400" />
                                    Username TikTok
                                </label>
                                <div class="relative">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-zinc-500 dark:text-zinc-200 font-black text-xs">@</span>
                                    <input v-model="form.tiktok" type="text" class="input pl-8 font-black text-sm" placeholder="pstoretiktok" />
                                </div>
                            </div>
                        </div>

                        <!-- Warranty Terms Override -->
                        <div class="space-y-1.5">
                            <label class="label flex items-center gap-1.5">
                                <ShieldCheck :size="12" class="text-emerald-600 dark:text-emerald-400" />
                                Keterangan Garansi (T&C)
                            </label>
                            <textarea v-model="form.warranty_terms" class="input min-h-[160px] resize-y font-bold text-xs leading-relaxed" 
                                placeholder="Tuliskan pasal-pasal garansi yang akan tercetak di bagian bawah nota...&#10;Contoh:&#10;- Garansi mesin 1 bulan sejak tanggal pembelian&#10;- Segel toko wajib utuh & menyertakan nota ini&#10;- Tidak melayani klaim garansi akibat kelalaian pengguna (jatuh/terkena air)."></textarea>
                        </div>

                        <!-- Save Area -->
                        <div class="pt-4 border-t border-zinc-100 dark:border-zinc-800/50 flex items-center justify-end gap-3">
                            <button @click="handleSave" type="button" :disabled="isSaving || isLoading" class="btn bg-emerald-600 text-white hover:bg-emerald-700 shadow-lg shadow-emerald-500/20 font-black rounded-xl px-6 py-2.5 tracking-widest uppercase flex items-center gap-2">
                                <Loader2 v-if="isSaving" class="animate-spin" :size="14" />
                                <Save v-else :size="14" />
                                Simpan
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@reference "../../style.css";

.label {
    @apply block text-[10px] font-black text-zinc-600 dark:text-zinc-200 mb-1.5 uppercase tracking-wider;
}

.input {
    /* High-contrast typography variables mapped explicitly to ensure zero visibility issues on Dark & Light themes */
    @apply w-full bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 rounded-xl px-4 py-2.5 text-xs text-zinc-900 dark:text-zinc-50 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition-all placeholder:text-zinc-500 dark:placeholder:text-zinc-400 font-bold;
}

.page-fade-in {
    animation: fadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
