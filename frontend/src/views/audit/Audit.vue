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
} from "lucide-vue-next";

import { useToast } from "../../composables/useToast";
import { users as usersApi, inventory as inventoryApi } from "../../api/axios";

import { useAuthStore } from "../../store/auth";

const authStore = useAuthStore();
const isLeader = computed(() => (authStore.userRole || '').toLowerCase() === 'leader');

// Mock audit items
const auditItems = ref([
  {
    id: 1,
    transactionId: "TRX-20260128-A1B2C3",
    branch: "Pusat Jakarta",
    cashier: "Fabian Syah",
    total: 45997000,
    issue: "Diskon melebihi limit",
    status: "pending",
    priority: "high",
    createdAt: "2026-01-28 10:30:00",
  },
  {
    id: 2,
    transactionId: "TRX-20260127-D4E5F6",
    branch: "Cabang Bandung",
    cashier: "Ahmad Kasir",
    total: 3999000,
    issue: "Stok tidak sesuai",
    status: "approved",
    priority: "medium",
    createdAt: "2026-01-27 15:45:00",
    resolvedAt: "2026-01-28 09:00:00",
  },
  {
    id: 3,
    transactionId: "TRX-20260127-G7H8I9",
    branch: "Cabang Surabaya",
    cashier: "Eko Sales",
    total: 21999000,
    issue: "Void tanpa alasan",
    status: "rejected",
    priority: "high",
    createdAt: "2026-01-27 14:00:00",
    resolvedAt: "2026-01-27 16:30:00",
  },
  {
    id: 4,
    transactionId: "TRX-20260126-J1K2L3",
    branch: "Pusat Jakarta",
    cashier: "Fabian Syah",
    total: 15998000,
    issue: "Harga manual",
    status: "pending",
    priority: "low",
    createdAt: "2026-01-26 11:20:00",
  },
]);

// Filters
const searchQuery = ref("");
const selectedStatus = ref("");
const selectedPriority = ref("");

// Modal
const showDetailModal = ref(false);
const selectedItem = ref(null);
const auditNotes = ref("");

// Filtered items
const filteredItems = computed(() => {
  let result = auditItems.value;

  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    result = result.filter(
      (i) =>
        i.transactionId.toLowerCase().includes(query) ||
        i.branch.toLowerCase().includes(query)
    );
  }

  if (selectedStatus.value) {
    result = result.filter((i) => i.status === selectedStatus.value);
  }

  if (selectedPriority.value) {
    result = result.filter((i) => i.priority === selectedPriority.value);
  }

  return result;
});

// Stats
const stats = computed(() => [
  {
    label: "Menunggu Review",
    value: auditItems.value.filter((i) => i.status === "pending").length,
    icon: Clock,
    color: "amber",
  },
  {
    label: "Disetujui",
    value: auditItems.value.filter((i) => i.status === "approved").length,
    icon: CheckCircle,
    color: "emerald",
  },
  {
    label: "Ditolak",
    value: auditItems.value.filter((i) => i.status === "rejected").length,
    icon: XCircle,
    color: "red",
  },
  {
    label: "High Priority",
    value: auditItems.value.filter((i) => i.priority === "high").length,
    icon: AlertTriangle,
    color: "red",
  },
]);

function getStatusBadge(status) {
  const badges = {
    pending: { label: "Pending", class: "badge-warning", icon: Clock },
    approved: { label: "Approved", class: "badge-success", icon: CheckCircle },
    rejected: { label: "Rejected", class: "badge-danger", icon: XCircle },
  };
  return badges[status] || badges.pending;
}

function getPriorityBadge(priority) {
  const badges = {
    high: {
      label: "High",
      class: "bg-red-500/20 text-red-400 border border-red-500/30",
    },
    medium: {
      label: "Medium",
      class: "bg-amber-500/20 text-amber-400 border border-amber-500/30",
    },
    low: {
      label: "Low",
      class: "bg-slate-500/20 text-slate-400 border border-slate-500/30",
    },
  };
  return badges[priority] || badges.low;
}

function openDetail(item) {
  selectedItem.value = item;
  auditNotes.value = "";
  showDetailModal.value = true;
}

useEscapeKey(() => {
  if (showDetailModal.value) showDetailModal.value = false;
});

const toast = useToast();
const route = useRoute();
const currentTab = ref(route.path.includes('photo-approvals') ? 'photos' : 'transactions');

onMounted(() => {
  if (currentTab.value === 'photos') {
    fetchPendingPhotos();
  }
});

// Photo Approval State
const pendingPhotos = ref([]);
const isPhotosLoading = ref(false);

async function fetchPendingPhotos() {
  isPhotosLoading.value = true;
  try {
    const [userRes, invRes] = await Promise.all([
      usersApi.listPendingPhotos(),
      inventoryApi.listPendingPhotos()
    ]);
    
    // Normalize data
    const users = (userRes.data.data || []).map(u => ({ ...u, type: 'user' }));
    const inventory = (invRes.data.data || []).map(i => ({ ...i, type: 'inventory' }));
    
    pendingPhotos.value = [...users, ...inventory];
  } catch (error) {
    console.error("Failed to fetch pending photos", error);
    toast.error("Gagal memuat daftar foto.");
  } finally {
    isPhotosLoading.value = false;
  }
}

async function handleApprovePhoto(item) {
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

function switchTab(tab) {
  currentTab.value = tab;
  if (tab === 'photos') {
    fetchPendingPhotos();
  }
}

function approveItem() {
  if (selectedItem.value) {
    selectedItem.value.status = "approved";
    selectedItem.value.resolvedAt = new Date().toISOString();
    showDetailModal.value = false;
  }
}

function rejectItem() {
  if (selectedItem.value && auditNotes.value) {
    selectedItem.value.status = "rejected";
    selectedItem.value.resolvedAt = new Date().toISOString();
    selectedItem.value.notes = auditNotes.value;
    showDetailModal.value = false;
  }
}
</script>

<template>
  <div class="space-y-6 animate-in">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-text-primary tracking-tight">Audit & Approval</h1>
        <p class="text-text-secondary mt-1">
          Review dan approval transaksi serta data sensitif
        </p>
      </div>

      <!-- Tab Switcher -->
      <div class="flex bg-surface-900 p-1 rounded-2xl border border-surface-700 self-start">
        <button @click="switchTab('transactions')" class="px-6 py-2 rounded-xl text-sm font-bold transition-all"
          :class="currentTab === 'transactions' ? 'bg-primary-500 text-white shadow-lg' : 'text-text-secondary hover:text-text-primary'">
          Transaksi
        </button>
        <button @click="switchTab('photos')" class="px-6 py-2 rounded-xl text-sm font-bold transition-all flex items-center gap-2"
          :class="currentTab === 'photos' ? 'bg-primary-500 text-white shadow-lg' : 'text-text-secondary hover:text-text-primary'">
          Foto Profil
          <span v-if="pendingPhotos.length > 0" class="w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
        </button>
      </div>
    </div>

    <!-- Transaction Review View -->
    <div v-if="currentTab === 'transactions'" class="space-y-6">
      <!-- Stats -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div v-for="(stat, index) in stats" :key="index" class="card flex items-center gap-4">
          <div class="w-12 h-12 rounded-xl flex items-center justify-center" :class="{
            'bg-amber-600': stat.color === 'amber',
            'bg-emerald-600': stat.color === 'emerald',
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

      <!-- Filters -->
      <div class="card">
        <div class="flex items-center gap-4">
          <div class="relative flex-1">
            <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500" :size="18" />
            <input v-model="searchQuery" type="text" placeholder="Cari ID transaksi atau cabang..." class="input pl-10" />
          </div>
          <select v-model="selectedStatus" class="input w-36">
            <option value="">Status</option>
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
          </select>
          <select v-model="selectedPriority" class="input w-36">
            <option value="">Priority</option>
            <option value="high">High</option>
            <option value="medium">Medium</option>
            <option value="low">Low</option>
          </select>
        </div>
      </div>

      <!-- Audit List -->
      <div class="space-y-4">
        <div v-for="item in filteredItems" :key="item.id" class="card card-hover cursor-pointer"
          @click="openDetail(item)">
          <div class="flex items-start justify-between gap-4">
            <div class="flex items-start gap-4">
              <div class="w-12 h-12 rounded-xl flex items-center justify-center" :class="item.priority === 'high'
                ? 'bg-red-500/20'
                : item.priority === 'medium'
                  ? 'bg-amber-500/20'
                  : 'bg-slate-700'
                ">
                <AlertTriangle :size="20" :class="item.priority === 'high'
                  ? 'text-red-400'
                  : item.priority === 'medium'
                    ? 'text-amber-400'
                    : 'text-slate-400'
                  " />
              </div>
              <div>
                <div class="flex items-center gap-2 mb-1">
                  <span class="font-mono text-sm text-blue-400">{{
                    item.transactionId
                  }}</span>
                  <span class="badge text-[10px]" :class="getPriorityBadge(item.priority).class">
                    {{ getPriorityBadge(item.priority).label }}
                  </span>
                </div>
                <p class="text-text-primary font-medium">{{ item.issue }}</p>
                <div class="flex items-center gap-4 mt-2 text-sm text-text-secondary">
                  <span class="flex items-center gap-1">
                    <Building2 :size="12" />
                    {{ item.branch }}
                  </span>
                  <span>{{ item.cashier }}</span>
                  <span>{{ formatCurrency(item.total) }}</span>
                </div>
              </div>
            </div>
            <div class="text-right">
              <span class="badge" :class="getStatusBadge(item.status).class">
                {{ getStatusBadge(item.status).label }}
              </span>
              <p class="text-xs text-slate-500 mt-2">{{ item.createdAt }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Photo Approval View -->
    <div v-else-if="currentTab === 'photos'" class="animate-in slide-in-from-right space-y-6">
        <div v-if="isPhotosLoading" class="flex flex-col items-center justify-center py-20 card">
            <Loader2 class="animate-spin text-primary-500 mb-4" :size="48" />
            <p class="text-text-secondary font-medium uppercase tracking-widest text-xs">Memuat daftar foto...</p>
        </div>

        <div v-else-if="pendingPhotos.length === 0" class="flex flex-col items-center justify-center py-20 card">
            <ImageIcon class="text-surface-700 mb-4" :size="64" />
            <h3 class="text-lg font-bold text-text-primary mb-2">Tidak Ada Pending Foto</h3>
            <p class="text-text-secondary text-sm">Semua pembaruan foto profil telah diproses.</p>
            <button @click="fetchPendingPhotos" class="btn btn-secondary mt-6 rounded-xl gap-2">
                <RefreshCw :size="16" /> Refresh
            </button>
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div v-for="item in pendingPhotos" :key="item.id + item.type" class="card overflow-hidden border-l-4 border-l-amber-500">
                <div class="flex flex-col h-full">
                    <!-- Request Info -->
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-surface-800 flex items-center justify-center text-primary-500 border border-surface-700">
                                <User v-if="item.type === 'user'" :size="20" />
                                <ImageIcon v-else :size="20" />
                            </div>
                            <div>
                                <h4 class="font-bold text-text-primary leading-none">{{ item.full_name || item.name }}</h4>
                                <p class="text-[10px] text-text-secondary uppercase tracking-wider mt-1">{{ item.type === 'user' ? (item.roles?.[0]?.name || 'Staff') : 'Akun Inventory' }}</p>
                            </div>
                        </div>
                        <span class="badge badge-warning text-[10px] uppercase font-black">Pending Approval</span>
                    </div>

                    <!-- Photo Comparison -->
                    <div class="flex gap-4 items-center mb-6 bg-surface-900/50 p-4 rounded-2xl border border-surface-700/50">
                        <!-- Old Photo -->
                        <div class="flex-1 flex flex-col items-center">
                            <label class="text-[8px] uppercase font-black text-text-secondary mb-2">Foto Lama</label>
                            <div class="w-20 h-20 rounded-2xl border border-surface-700 overflow-hidden bg-surface-800">
                                <img v-if="item.photo || item.photo_inventory" 
                                    :src="`${authStore.storageBaseUrl}/storage/${item.photo || item.photo_inventory}`"
                                    class="w-full h-full object-cover grayscale opacity-50" />
                                <div v-else class="w-full h-full flex items-center justify-center text-surface-600">
                                    <ImageIcon :size="24" />
                                </div>
                            </div>
                        </div>

                        <ChevronDown class="rotate-[-90deg] text-surface-700" :size="20" />

                        <!-- New Photo -->
                        <div class="flex-1 flex flex-col items-center">
                            <label class="text-[8px] uppercase font-black text-primary-500 mb-2">Foto Baru (Pending)</label>
                            <div class="w-24 h-24 rounded-2xl border-2 border-primary-500/50 overflow-hidden bg-surface-800 shadow-xl shadow-primary-500/10">
                                <img :src="`${authStore.storageBaseUrl}/storage/${item.pending_photo || item.pending_photo_inventory}`"
                                    class="w-full h-full object-cover" />
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="mt-auto grid grid-cols-2 gap-3">
                        <button @click="handleRejectPhoto(item)" class="btn bg-rose-500/10 hover:bg-rose-500 text-rose-500 hover:text-white border border-rose-500/20 rounded-xl text-xs gap-2 py-3">
                            <ThumbsDown :size="14" /> Tolak
                        </button>
                        <button @click="handleApprovePhoto(item)" class="btn bg-emerald-500 border border-emerald-400 text-white rounded-xl text-xs gap-2 py-3 shadow-lg shadow-emerald-500/20">
                            <ThumbsUp :size="14" /> Setujui
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detail Modal -->
    <Teleport to="body">
      <div v-if="showDetailModal && selectedItem" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showDetailModal = false"></div>

        <div
          class="relative bg-white dark:!bg-surface-900 rounded-2xl border border-gray-200 dark:border-surface-700 w-full max-w-lg p-6 shadow-2xl">
          <h3 class="text-xl font-bold text-text-primary mb-6">Review Audit</h3>

          <div class="space-y-4 mb-6">
            <div class="flex justify-between">
              <span class="text-text-secondary">ID Transaksi</span>
              <span class="text-blue-500 font-mono">{{
                selectedItem.transactionId
              }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-text-secondary">Cabang</span>
              <span class="text-text-primary">{{ selectedItem.branch }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-text-secondary">Kasir</span>
              <span class="text-text-primary">{{ selectedItem.cashier }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-text-secondary">Total</span>
              <span class="text-text-primary font-bold">{{
                formatCurrency(selectedItem.total)
              }}</span>
            </div>
            <div class="p-4 bg-amber-500/10 border border-amber-500/30 rounded-xl">
              <p class="text-amber-400 font-medium">{{ selectedItem.issue }}</p>
            </div>
          </div>

          <div v-if="selectedItem.status === 'pending'" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-text-secondary mb-2">Catatan (Wajib untuk Reject)</label>
              <textarea v-model="auditNotes" class="input h-24 resize-none"
                placeholder="Masukkan catatan..."></textarea>
            </div>

            <div v-if="!isLeader" class="flex gap-3">
              <button @click="rejectItem" class="btn btn-danger flex-1" :disabled="!auditNotes">
                <XCircle :size="16" />
                Reject
              </button>
              <button @click="approveItem" class="btn btn-success flex-1">
                <CheckCircle :size="16" />
                Approve
              </button>
            </div>
          </div>

          <div v-else class="text-center py-4">
            <span class="badge text-lg py-2 px-4" :class="getStatusBadge(selectedItem.status).class">
              {{ getStatusBadge(selectedItem.status).label }}
            </span>
            <p class="text-slate-500 text-sm mt-2">
              Resolved: {{ selectedItem.resolvedAt }}
            </p>
          </div>
        </div>
      </div>
    </Teleport>
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
</style>
