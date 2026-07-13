<script setup>
import { ref, computed, onMounted } from "vue";
import { useRoute } from "vue-router";
import { useEscapeKey } from "../../composables/useEscapeKey";
import { formatCurrency, formatDate } from "../../utils/formatters";
import {
  Search,
  Filter,
  ClipboardCheck,
  AlertTriangle,
  CheckCircle,
  XCircle,
  Clock,
  Eye,
  MessageSquare,
  Building2,
  ChevronDown,
  User,
  Camera,
  ThumbsUp,
  ThumbsDown,
  Info,
  RefreshCw,
  Image as ImageIcon,
  Loader2,
  MapPin, Globe
} from "lucide-vue-next";
import api, { users as usersApi, inventory as inventoryApi } from "../../api/axios";
import { useToast } from "../../composables/useToast";
import { useAuthStore } from "../../store/auth";

const authStore = useAuthStore();
const toast = useToast();
const route = useRoute();

// Photo Approval State
const pendingPhotos = ref([]);
const isPhotosLoading = ref(false);
const isDemoMode = ref(false);

const locationType = ref('branch');
const filters = ref({
    branch_id: null,
    online_shop_id: null
});

const branches = ref([]);
const onlineShops = ref([]);

const canChangeLocation = computed(() => {
    const role = (authStore.userRole || '').toLowerCase();
    return ['super_admin', 'analist', 'audit', 'leader', 'owner', 'admin_produk'].some(r => role.includes(r));
});

const filteredBranches = computed(() => {
    const role = (authStore.userRole || '').toLowerCase();
    let result = branches.value;
    if (!['super_admin', 'analist', 'admin_produk'].some(r => role.includes(r))) {
        const allowed = [authStore.user?.branch_id, ...(authStore.user?.placements?.filter(p => p.model_type === 'branch').map(p => p.model_id) || [])].filter(Boolean).map(Number);
        result = result.filter(b => allowed.includes(Number(b.id)));
    }
    return result;
});

const filteredOnlineShops = computed(() => {
    const role = (authStore.userRole || '').toLowerCase();
    let result = onlineShops.value;
    if (!['super_admin', 'analist', 'admin_produk'].some(r => role.includes(r))) {
        const allowed = [authStore.user?.online_shop_id, ...(authStore.user?.placements?.filter(p => p.model_type === 'online_shop').map(p => p.model_id) || [])].filter(Boolean).map(Number);
        result = result.filter(s => allowed.includes(Number(s.id)));
    }
    return result;
});

const fetchLocations = async () => {
    try {
        const response = await api.get('/inventory/meta-locations');
        branches.value = response.data.branches || [];
        onlineShops.value = response.data.online_shops || [];
    } catch (err) {
        console.error(err);
    }
};

const handleLocationTypeChange = () => {
    filters.value.branch_id = null;
    filters.value.online_shop_id = null;
    fetchPendingPhotos();
};

async function fetchPendingPhotos() {
  isPhotosLoading.value = true;
  isDemoMode.value = false;
  
  const params = {};
  if (filters.value.branch_id) params.branch_id = filters.value.branch_id;
  if (filters.value.online_shop_id) params.online_shop_id = filters.value.online_shop_id;

  try {
    // Fetch User pending photos - catch error individually
    const userPhotos = await usersApi.listPendingPhotos(params)
      .then(res => (res.data.data || []).map(u => ({ ...u, type: 'user' })))
      .catch(err => {
        console.warn("User photo endpoint error:", err.message);
        return null; // Return null to indicate failure
      });

    // Fetch Inventory pending photos - catch error individually
    const invPhotos = await inventoryApi.listPendingPhotos(params)
      .then(res => (res.data.data || []).map(i => ({ ...i, type: 'inventory' })))
      .catch(err => {
        console.warn("Inventory photo endpoint error:", err.message);
        return null; // Return null to indicate failure
      });
    
    // If BOTH failed (e.g. 500/404), use Mock Data for demonstration
    if (userPhotos === null && invPhotos === null) {
        console.info("Using Demo Mode Data because Backend is not ready.");
        isDemoMode.value = true;
        pendingPhotos.value = [
            {
                id: 999,
                type: 'user',
                full_name: 'Staff Demo (Backend Belum Siap)',
                roles: [{ name: 'Sales' }],
                photo: null,
                pending_photo: 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&q=80&w=200',
                is_mock: true
            },
            {
                id: 888,
                type: 'inventory',
                name: 'Gudang Demo (Backend Belum Siap)',
                photo_inventory: 'https://images.unsplash.com/photo-1580489944761-15a19d654956?auto=format&fit=crop&q=80&w=200',
                pending_photo_inventory: 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?auto=format&fit=crop&q=80&w=200',
                is_mock: true
            }
        ];
    } else {
        pendingPhotos.value = [...(userPhotos || []), ...(invPhotos || [])];
    }
  } catch (error) {
    console.error("General error in fetchPendingPhotos:", error);
  } finally {
    isPhotosLoading.value = false;
  }
}

onMounted(() => {
  if (canChangeLocation.value) {
      fetchLocations();
  }
  fetchPendingPhotos();
});

async function handleApprovePhoto(item) {
  if (item.is_mock) {
      toast.info("Ini adalah data demo. Approval berhasil (Simulasi)");
      pendingPhotos.value = pendingPhotos.value.filter(p => p.id !== item.id);
      return;
  }

  if (!confirm(`Setujui pembaruan foto untuk ${item.full_name || item.name}?`)) return;
  
  try {
    if (item.type === 'user') {
      await usersApi.approvePhoto(item.id);
    } else {
      await inventoryApi.approvePhoto(item.id);
    }
    toast.success("Foto berhasil disetujui!");
    fetchPendingPhotos();
  } catch (error) {
    toast.error("Gagal menyetujui foto.");
  }
}

async function handleRejectPhoto(item) {
  if (item.is_mock) {
      toast.info("Ini adalah data demo. Penolakan berhasil (Simulasi)");
      pendingPhotos.value = pendingPhotos.value.filter(p => p.id !== item.id);
      return;
  }

  const reason = prompt("Masukkan alasan penolakan:");
  if (reason === null) return;
  
  try {
    if (item.type === 'user') {
      await usersApi.rejectPhoto(item.id, reason);
    } else {
      await inventoryApi.rejectPhoto(item.id, reason);
    }
    toast.success("Foto ditolak.");
    fetchPendingPhotos();
  } catch (error) {
    toast.error("Gagal menolak foto.");
  }
}
</script>

<template>
  <div class="space-y-6 animate-in">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-text-primary tracking-tight flex items-center gap-2">
            <Camera class="text-primary-500" :size="28" /> Persetujuan Foto Profil
        </h1>
        <p class="text-text-secondary mt-1">
          Review dan tinjau permintaan penggantian foto profil staff
        </p>
      </div>

        <div class="flex items-center gap-3">
          <!-- Location Filter -->
          <div v-if="canChangeLocation"
              class="flex items-center gap-2 bg-surface-900 border border-surface-700 rounded-xl p-1 shadow-sm w-fit hidden md:flex">
              <div class="flex items-center gap-1 group">
                  <div
                      class="p-1.5 bg-surface-800 rounded-lg group-hover:bg-primary-500/10 transition-colors">
                      <MapPin v-if="locationType === 'branch'" :size="14"
                          class="text-text-secondary group-hover:text-primary-500" />
                      <Globe v-else :size="14" class="text-text-secondary group-hover:text-primary-500" />
                  </div>
                  <select v-model="locationType" @change="handleLocationTypeChange"
                      class="bg-transparent border-none text-[10px] uppercase tracking-wider font-black text-text-secondary focus:ring-0 cursor-pointer pr-6">
                      <option value="branch">Cabang</option>
                      <option value="online">Online</option>
                  </select>
              </div>
              <div class="w-px h-4 bg-surface-700 mr-1"></div>
              <select v-if="locationType === 'branch'" v-model="filters.branch_id" @change="fetchPendingPhotos"
                  class="bg-transparent border-none text-xs font-bold text-text-primary focus:ring-0 cursor-pointer min-w-[140px] appearance-none pr-8">
                  <option :value="null">Semua Cabang</option>
                  <option v-for="b in filteredBranches" :key="b.id" :value="b.id">{{ b.name }}</option>
              </select>
              <select v-else v-model="filters.online_shop_id" @change="fetchPendingPhotos"
                  class="bg-transparent border-none text-xs font-bold text-text-primary focus:ring-0 cursor-pointer min-w-[140px] appearance-none pr-8">
                  <option :value="null">Semua Toko Online</option>
                  <option v-for="s in filteredOnlineShops" :key="s.id" :value="s.id">{{ s.name }}</option>
              </select>
          </div>

        <div v-if="isDemoMode" class="px-3 py-1 bg-amber-500/10 border border-amber-500/20 rounded-full flex items-center gap-2">
            <div class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></div>
            <span class="text-[10px] font-black text-amber-500 uppercase tracking-widest">Demo Mode (API Failing)</span>
        </div>
        <button @click="fetchPendingPhotos" class="btn btn-secondary rounded-xl gap-2 text-xs py-2">
            <RefreshCw :size="14" :class="{ 'animate-spin': isPhotosLoading }" /> Refresh
        </button>
      </div>
    </div>

    <!-- Photo Approval View -->
    <div class="space-y-6">
        <div v-if="isPhotosLoading" class="flex flex-col items-center justify-center py-32 card">
            <Loader2 class="animate-spin text-primary-500 mb-4" :size="48" />
            <p class="text-text-secondary font-medium uppercase tracking-widest text-xs">Memuat daftar permintaan...</p>
        </div>

        <div v-else-if="pendingPhotos.length === 0" class="flex flex-col items-center justify-center py-32 card">
            <div class="w-20 h-20 rounded-3xl bg-surface-800 flex items-center justify-center mb-6 text-surface-600">
                <ImageIcon :size="40" />
            </div>
            <h3 class="text-xl font-bold text-text-primary mb-2">Semua Beres!</h3>
            <p class="text-text-secondary text-sm max-w-xs text-center leading-relaxed">
                Tidak ada permintaan foto profil yang menunggu persetujuan saat ini.
            </p>
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div v-for="item in pendingPhotos" :key="item.id + item.type" 
                class="card overflow-hidden border-l-4 transition-all duration-300 group hover:translate-y-[-4px]"
                :class="item.is_mock ? 'border-l-amber-500' : 'border-l-primary-500'">
                
                <div class="flex flex-col h-full">
                    <!-- Request Info -->
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-surface-800 flex items-center justify-center text-primary-500 border border-surface-700">
                                <User v-if="item.type === 'user'" :size="20" />
                                <ImageIcon v-else :size="20" />
                            </div>
                            <div>
                                <h4 class="font-bold text-text-primary leading-none">{{ item.full_name || item.name }}</h4>
                                <p class="text-[10px] text-text-secondary uppercase tracking-wider mt-1.5 font-bold">
                                    {{ item.type === 'user' ? (item.roles?.[0]?.name || 'Staff') : 'Akun Inventory' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Photo Comparison Content -->
                    <div class="relative grid grid-cols-2 gap-4 mb-8">
                        <!-- Old Photo -->
                        <div class="space-y-2">
                            <p class="text-[9px] font-black text-text-secondary uppercase text-center tracking-widest">Foto Lama</p>
                            <div class="aspect-square rounded-2xl border border-surface-700 overflow-hidden bg-surface-800 relative group/old">
                                <img v-if="item.photo || item.photo_inventory" 
                                    :src="item.is_mock ? (item.photo || item.photo_inventory) : `${authStore.storageBaseUrl}/storage/${item.photo || item.photo_inventory}`"
                                    class="w-full h-full object-cover grayscale opacity-40 transition-all group-hover/old:grayscale-0 group-hover/old:opacity-100" 
                                    @error="(e) => e.target.src = 'https://ui-avatars.com/api/?name=Old&background=333&color=666'" />
                                <div v-else class="w-full h-full flex items-center justify-center text-surface-600 bg-surface-900/50">
                                    <ImageIcon :size="24" />
                                </div>
                                <div class="absolute inset-0 bg-black/20 flex items-center justify-center opacity-100 group-hover/old:opacity-0 transition-opacity">
                                    <span class="text-[8px] bg-black/60 text-white px-2 py-0.5 rounded-full font-bold uppercase">Archive</span>
                                </div>
                            </div>
                        </div>

                        <!-- Arrow Overlay -->
                        <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 z-10 w-8 h-8 rounded-full bg-surface-900 border border-surface-700 flex items-center justify-center shadow-xl">
                            <ChevronDown class="rotate-[-90deg] text-primary-500" :size="16" />
                        </div>

                        <!-- New Photo -->
                        <div class="space-y-2">
                            <p class="text-[9px] font-black text-primary-500 uppercase text-center tracking-widest">Foto Baru</p>
                            <div class="aspect-square rounded-2xl border-2 border-primary-500/50 overflow-hidden bg-surface-800 shadow-2xl shadow-primary-500/10 transition-transform group-hover:scale-[1.02]">
                                <img :src="item.is_mock ? (item.pending_photo || item.pending_photo_inventory) : `${authStore.storageBaseUrl}/storage/${item.pending_photo || item.pending_photo_inventory}`"
                                    class="w-full h-full object-cover" 
                                    @error="(e) => e.target.src = 'https://ui-avatars.com/api/?name=New&background=10b981&color=fff'" />
                                <div class="absolute top-2 right-2">
                                    <div class="w-2 h-2 rounded-full bg-primary-500 animate-ping"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="mt-auto grid grid-cols-2 gap-3 pt-4 border-t border-surface-800/50">
                        <button @click="handleRejectPhoto(item)" class="btn bg-rose-500/10 hover:bg-rose-500 text-rose-500 hover:text-white border border-rose-500/20 rounded-xl text-xs gap-2 py-3.5 transition-all active:scale-95 font-bold uppercase tracking-wider">
                            Tolak
                        </button>
                        <button @click="handleApprovePhoto(item)" class="btn bg-primary-500 border border-primary-400 text-white rounded-xl text-xs gap-2 py-3.5 shadow-lg shadow-primary-500/20 transition-all active:scale-95 font-bold uppercase tracking-wider">
                            Setujui
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

.animate-in {
  animation: fadeIn 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(12px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.card {
    background-color: var(--surface-800);
    @apply bg-surface-900/40 backdrop-blur-xl border border-surface-800 rounded-[28px] p-6 shadow-xl;
}
</style>
