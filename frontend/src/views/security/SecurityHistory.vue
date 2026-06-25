<template>
  <div class="px-6 py-8 mx-auto max-w-7xl">
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-2xl font-bold text-text-primary">History Security</h1>
        <p class="text-text-secondary mt-1">Riwayat pengecekan Surat Jalan oleh tim Security.</p>
      </div>
    </div>

    <!-- Filter / Search -->
    <div class="bg-surface-800 border border-surface-700 rounded-2xl p-4 mb-6 flex flex-col sm:flex-row items-center justify-between gap-4">
      <div class="relative w-full sm:max-w-md">
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
          <Search class="w-5 h-5 text-surface-400" />
        </div>
        <input v-model="searchQuery" @input="debounceSearch" type="text"
          class="block w-full pl-10 pr-3 py-2.5 bg-surface-900 border border-surface-700 rounded-xl text-text-primary focus:ring-primary-500 focus:border-primary-500 sm:text-sm"
          placeholder="Cari No. Resi atau Nama Security..." />
      </div>
      <button @click="fetchHistory"
        class="w-full sm:w-auto px-4 py-2.5 bg-surface-700 hover:bg-surface-600 text-text-primary rounded-xl font-medium transition-colors flex items-center justify-center gap-2">
        <RefreshCw :size="16" :class="{ 'animate-spin': isLoading }" /> Refresh
      </button>
    </div>

    <!-- Data Table -->
    <div class="bg-surface-800 border border-surface-700 rounded-2xl overflow-hidden shadow-sm">
      <div class="overflow-x-auto min-h-[400px]">
        <table class="min-w-full divide-y divide-surface-700">
          <thead class="bg-surface-900/50">
            <tr>
              <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-text-secondary uppercase tracking-wider">Tanggal & Waktu</th>
              <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-text-secondary uppercase tracking-wider">No. Resi</th>
              <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-text-secondary uppercase tracking-wider">Staff & Security</th>
              <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-text-secondary uppercase tracking-wider min-w-[250px]">Barang Yang Di-ACC</th>
              <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-text-secondary uppercase tracking-wider">Info Lebih</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-surface-700 bg-surface-800">
            <tr v-if="isLoading && !history.length">
              <td colspan="5" class="px-6 py-10 text-center text-text-secondary">
                <Loader2 class="w-8 h-8 animate-spin mx-auto mb-2 text-primary-500" />
                Memuat riwayat pengecekan...
              </td>
            </tr>
            <tr v-else-if="!history.length">
              <td colspan="5" class="px-6 py-10 text-center text-text-secondary">
                <ShieldCheck class="w-12 h-12 mx-auto mb-3 text-surface-500 opacity-50" />
                Tidak ada riwayat pengecekan ditemukan.
              </td>
            </tr>
            <tr v-for="item in history" :key="item.id" class="hover:bg-surface-700/50 transition-colors">
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-semibold text-text-primary">{{ formatDate(item.created_at) }}</div>
                <div class="text-xs text-text-secondary mt-0.5">{{ formatTime(item.created_at) }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-primary-500/10 text-primary-400 border border-primary-500/20">
                  {{ item.receipt_id }}
                </span>
              </td>
              <td class="px-6 py-4">
                <div class="flex flex-col gap-1.5">
                  <div class="flex items-center gap-1.5 text-sm">
                    <User :size="14" class="text-primary-500" />
                    <span class="text-text-secondary">Sec:</span>
                    <span class="font-medium text-text-primary">{{ item.security_name }}</span>
                  </div>
                  <div class="flex items-center gap-1.5 text-xs">
                    <UserCircle :size="14" class="text-amber-500" />
                    <span class="text-text-secondary">Inv:</span>
                    <span class="font-medium text-text-primary">{{ item.inventory_user?.name || 'Tidak ada' }}</span>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4">
                <div class="space-y-2 max-h-32 overflow-y-auto pr-2 custom-scrollbar">
                  <template v-if="item.stock_out">
                    <!-- HP / IMEI -->
                    <div v-for="(hp, idx) in item.stock_out.items" :key="'hp-'+idx" class="flex flex-col mb-2 pb-2 border-b border-surface-700/50 last:border-0 last:mb-0 last:pb-0">
                      <span class="text-xs font-semibold text-text-primary">
                        {{ hp.product?.brand || 'Brand' }} {{ hp.product?.name }} {{ hp.product?.storage ? (hp.product.storage + 'GB') : '' }}
                      </span>
                      <div class="flex items-center gap-2 mt-0.5">
                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-surface-900 text-text-secondary font-mono">
                          IMEI: {{ hp.pivot?.notes || hp.imei || '-' }}
                        </span>
                        <span class="text-[10px] text-text-secondary">Qty: 1</span>
                      </div>
                    </div>
                    <!-- Non HP -->
                    <div v-for="(nonHp, idx) in item.stock_out.non_hp_items" :key="'nonhp-'+idx" class="flex flex-col mb-2 pb-2 border-b border-surface-700/50 last:border-0 last:mb-0 last:pb-0">
                      <span class="text-xs font-semibold text-text-primary">
                        {{ nonHp.product?.brand || nonHp.brand }} {{ nonHp.product?.name || nonHp.product_name }}
                      </span>
                      <div class="flex items-center gap-2 mt-0.5">
                        <span class="text-[10px] text-text-secondary bg-surface-900 px-1.5 py-0.5 rounded">Aksesoris</span>
                        <span class="text-[10px] text-text-secondary font-bold text-amber-500">Qty: {{ nonHp.quantity || nonHp.qty }}</span>
                      </div>
                    </div>
                    <div v-if="!item.stock_out.items?.length && !item.stock_out.non_hp_items?.length" class="text-xs text-text-secondary italic">
                      Tidak ada data barang
                    </div>
                  </template>
                  <template v-else>
                    <span class="text-xs text-text-secondary italic">Data Surat Jalan tidak ditemukan</span>
                  </template>
                </div>
              </td>
              <td class="px-6 py-4">
                <div v-if="item.excess_items && item.excess_items.length > 0" class="flex flex-col gap-1.5">
                  <span class="inline-flex items-center w-fit px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/10 text-amber-500 border border-amber-500/20">
                    Ada Barang Lebih
                  </span>
                  <div class="text-[11px] text-text-secondary line-clamp-2" :title="item.excess_items.map(e => e.type + ' (' + e.excess_qty + ')').join(', ')">
                    {{ item.excess_items.map(e => `${e.brand || ''} ${e.type || ''} (${e.excess_qty})`).join(', ') }}
                  </div>
                </div>
                <div v-else>
                  <span class="text-xs text-surface-500">Aman / Pas</span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      
      <!-- Pagination -->
      <div v-if="totalPages > 1" class="px-6 py-4 bg-surface-900/50 border-t border-surface-700 flex items-center justify-between">
        <span class="text-sm text-text-secondary">
          Halaman {{ currentPage }} dari {{ totalPages }}
        </span>
        <div class="flex gap-2">
          <button @click="changePage(currentPage - 1)" :disabled="currentPage === 1"
            class="p-2 rounded-lg bg-surface-800 border border-surface-600 text-text-primary hover:bg-surface-700 disabled:opacity-50 transition-colors">
            <ChevronLeft :size="16" />
          </button>
          <button @click="changePage(currentPage + 1)" :disabled="currentPage === totalPages"
            class="p-2 rounded-lg bg-surface-800 border border-surface-600 text-text-primary hover:bg-surface-700 disabled:opacity-50 transition-colors">
            <ChevronRight :size="16" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../../api/axios';
import { useToast } from '../../composables/useToast';
import { 
  Search, RefreshCw, Loader2, ShieldCheck, User, UserCircle, ChevronLeft, ChevronRight 
} from 'lucide-vue-next';

const toast = useToast();
const history = ref([]);
const isLoading = ref(false);
const searchQuery = ref('');
const currentPage = ref(1);
const totalPages = ref(1);
let debounceTimeout = null;

const fetchHistory = async (page = 1) => {
  isLoading.value = true;
  try {
    const res = await api.get('/security-checks/history', {
      params: {
        page: page,
        q: searchQuery.value
      }
    });
    
    if (res.data.status === 'success') {
      history.value = res.data.data.data || [];
      currentPage.value = res.data.data.current_page;
      totalPages.value = res.data.data.last_page || 1;
    }
  } catch (err) {
    toast.error('Gagal memuat history security: ' + (err.response?.data?.message || err.message));
  } finally {
    isLoading.value = false;
  }
};

const debounceSearch = () => {
  if (debounceTimeout) clearTimeout(debounceTimeout);
  debounceTimeout = setTimeout(() => {
    currentPage.value = 1;
    fetchHistory(1);
  }, 500);
};

const changePage = (page) => {
  if (page >= 1 && page <= totalPages.value) {
    fetchHistory(page);
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
  fetchHistory();
});
</script>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  @apply bg-surface-600 rounded-full;
}
</style>
