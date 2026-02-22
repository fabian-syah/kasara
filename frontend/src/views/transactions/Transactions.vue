<script setup>
import { ref, computed } from "vue";
import { formatCurrency, formatDate } from "../../utils/formatters";
import {
  Search,
  Filter,
  Download,
  Eye,
  Receipt,
  Calendar,
  Building2,
  CheckCircle,
  Clock,
  XCircle,
  ChevronLeft,
  ChevronRight,
  FileText,
  RefreshCw,
} from "lucide-vue-next";

// Mock transactions
const transactions = ref([
  {
    id: "TRX-20260128-A1B2C3",
    customer: "Ahmad Rizki",
    branch: "Pusat Jakarta",
    items: 3,
    total: 45997000,
    status: "success",
    paymentMethod: "cash",
    date: "2026-01-28 10:30:00",
    cashier: "Fabian Syah",
  },
  {
    id: "TRX-20260128-D4E5F6",
    customer: "Budi Santoso",
    branch: "Cabang Bandung",
    items: 1,
    total: 3999000,
    status: "success",
    paymentMethod: "qris",
    date: "2026-01-28 09:45:00",
    cashier: "Ahmad Kasir",
  },
  {
    id: "TRX-20260128-G7H8I9",
    customer: "Citra Dewi",
    branch: "Pusat Jakarta",
    items: 2,
    total: 35498000,
    status: "success",
    paymentMethod: "transfer",
    date: "2026-01-28 09:15:00",
    cashier: "Fabian Syah",
  },
  {
    id: "TRX-20260127-J1K2L3",
    customer: "Dimas Pratama",
    branch: "Cabang Surabaya",
    items: 1,
    total: 21999000,
    status: "pending",
    paymentMethod: "transfer",
    date: "2026-01-27 16:20:00",
    cashier: "Eko Sales",
  },
  {
    id: "TRX-20260127-M4N5O6",
    customer: "Eva Susanti",
    branch: "Pusat Jakarta",
    items: 4,
    total: 12996000,
    status: "success",
    paymentMethod: "cash",
    date: "2026-01-27 14:30:00",
    cashier: "Fabian Syah",
  },
  {
    id: "TRX-20260127-P7Q8R9",
    customer: "-",
    branch: "Cabang Bandung",
    items: 1,
    total: 5499000,
    status: "void",
    paymentMethod: "cash",
    date: "2026-01-27 11:00:00",
    cashier: "Ahmad Kasir",
  },
]);

const branches = [
  "Pusat Jakarta",
  "Cabang Bandung",
  "Cabang Surabaya",
  "Cabang Medan",
];

// Filters
const searchQuery = ref("");
const selectedBranch = ref("");
const selectedStatus = ref("");
const dateFrom = ref("");
const dateTo = ref("");

// Filtered transactions
const filteredTransactions = computed(() => {
  let result = transactions.value;

  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    result = result.filter(
      (t) =>
        t.id.toLowerCase().includes(query) ||
        t.customer.toLowerCase().includes(query)
    );
  }

  if (selectedBranch.value) {
    result = result.filter((t) => t.branch === selectedBranch.value);
  }

  if (selectedStatus.value) {
    result = result.filter((t) => t.status === selectedStatus.value);
  }

  return result;
});

// Stats
const stats = computed(() => {
  const today = transactions.value.filter((t) =>
    t.date.startsWith("2026-01-28")
  );
  const successTotal = transactions.value
    .filter((t) => t.status === "success")
    .reduce((sum, t) => sum + t.total, 0);

  return [
    {
      label: "Transaksi Hari Ini",
      value: today.length,
      icon: Receipt,
      color: "blue",
    },
    {
      label: "Total Pendapatan",
      value: formatCurrency(successTotal),
      icon: CheckCircle,
      color: "emerald",
    },
    {
      label: "Pending",
      value: transactions.value.filter((t) => t.status === "pending").length,
      icon: Clock,
      color: "amber",
    },
    {
      label: "Void",
      value: transactions.value.filter((t) => t.status === "void").length,
      icon: XCircle,
      color: "red",
    },
  ];
});

function getStatusBadge(status) {
  const badges = {
    success: { label: "Sukses", class: "badge-success" },
    pending: { label: "Pending", class: "badge-warning" },
    void: { label: "Void", class: "badge-danger" },
  };
  return badges[status] || badges.pending;
}

function getPaymentLabel(method) {
  const labels = { cash: "Tunai", transfer: "Transfer", qris: "QRIS" };
  return labels[method] || method;
}
</script>

<template>
  <div class="space-y-6 animate-in">
    <!-- Header -->
    <div class="flex justify-between items-end">
      <div>
        <h1 class="text-2xl font-bold text-text-primary tracking-tight">Transaksi</h1>
        <p class="text-text-secondary mt-1">Riwayat semua transaksi penjualan</p>
      </div>
      <button class="btn btn-secondary">
        <Download :size="16" />
        Export Laporan
      </button>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
      <div v-for="(stat, index) in stats" :key="index" class="card flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl flex items-center justify-center" :class="{
          'bg-blue-600': stat.color === 'blue',
          'bg-emerald-600': stat.color === 'emerald',
          'bg-amber-600': stat.color === 'amber',
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
      <div class="flex flex-wrap items-center gap-4">
        <div class="relative flex-1 min-w-[200px]">
          <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500" :size="18" />
          <input v-model="searchQuery" type="text" placeholder="Cari ID transaksi atau customer..."
            class="input pl-10" />
        </div>

        <select v-model="selectedBranch" class="input w-40">
          <option value="">Semua Cabang</option>
          <option v-for="branch in branches" :key="branch" :value="branch">
            {{ branch }}
          </option>
        </select>

        <select v-model="selectedStatus" class="input w-32">
          <option value="">Status</option>
          <option value="success">Sukses</option>
          <option value="pending">Pending</option>
          <option value="void">Void</option>
        </select>

        <div class="flex items-center gap-2">
          <input v-model="dateFrom" type="date" class="input w-36" />
          <span class="text-text-secondary">-</span>
          <input v-model="dateTo" type="date" class="input w-36" />
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="card p-0">
      <div class="table-container">
        <table class="table">
          <thead>
            <tr>
              <th>ID Transaksi</th>
              <th>Customer</th>
              <th>Cabang</th>
              <th>Items</th>
              <th>Total</th>
              <th>Pembayaran</th>
              <th>Status</th>
              <th>Waktu</th>
              <th class="text-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="trx in filteredTransactions" :key="trx.id">
              <td class="font-mono text-sm text-blue-400">{{ trx.id }}</td>
              <td class="text-text-primary">{{ trx.customer }}</td>
              <td class="text-text-secondary">{{ trx.branch }}</td>
              <td class="text-center text-text-secondary">{{ trx.items }}</td>
              <td class="text-text-primary font-semibold">
                {{ formatCurrency(trx.total) }}
              </td>
              <td>
                <span class="badge badge-info">{{
                  getPaymentLabel(trx.paymentMethod)
                }}</span>
              </td>
              <td>
                <span class="badge" :class="getStatusBadge(trx.status).class">
                  {{ getStatusBadge(trx.status).label }}
                </span>
              </td>
              <td class="text-text-secondary text-sm">{{ trx.date }}</td>
              <td>
                <div class="flex items-center justify-center gap-2">
                  <button class="p-2 hover:bg-slate-700 rounded-lg transition-colors">
                    <Eye :size="16" class="text-slate-400" />
                  </button>
                  <button class="p-2 hover:bg-slate-700 rounded-lg transition-colors">
                    <FileText :size="16" class="text-slate-400" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="flex items-center justify-between px-6 py-4 border-t border-slate-700/50">
        <p class="text-sm text-slate-500">
          Menampilkan 1-{{ filteredTransactions.length }} dari
          {{ filteredTransactions.length }} transaksi
        </p>
        <div class="flex items-center gap-2">
          <button class="p-2 hover:bg-slate-700 rounded-lg transition-colors disabled:opacity-50" disabled>
            <ChevronLeft :size="16" class="text-slate-400" />
          </button>
          <button class="px-3 py-1 bg-blue-600 text-white rounded-lg text-sm">
            1
          </button>
          <button class="p-2 hover:bg-slate-700 rounded-lg transition-colors disabled:opacity-50" disabled>
            <ChevronRight :size="16" class="text-slate-400" />
          </button>
        </div>
      </div>
    </div>
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
