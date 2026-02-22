<script setup>
import { ref, computed } from "vue";
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
} from "lucide-vue-next";

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
    <div class="flex justify-between items-end">
      <div>
        <h1 class="text-2xl font-bold text-text-primary tracking-tight">Audit</h1>
        <p class="text-text-secondary mt-1">
          Review dan approval transaksi yang memerlukan perhatian
        </p>
      </div>
    </div>

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

    <!-- Detail Modal -->
    <Teleport to="body">
      <div v-if="showDetailModal && selectedItem" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showDetailModal = false"></div>

        <div
          class="relative bg-white dark:bg-surface-900 rounded-2xl border border-gray-200 dark:border-surface-700 w-full max-w-lg p-6 shadow-2xl">
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

            <div class="flex gap-3">
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
