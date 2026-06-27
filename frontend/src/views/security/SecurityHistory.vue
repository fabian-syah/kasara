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
              <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-text-secondary uppercase tracking-wider">Aksi</th>
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
                <div v-if="item.excess_items && item.excess_items.length > 0" class="flex flex-col gap-1.5 items-start">
                  <button @click="viewExcessDetails(item.excess_items)" class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-bold bg-amber-500/10 text-amber-500 border border-amber-500/20 hover:bg-amber-500/20 transition-colors cursor-pointer shadow-sm">
                    <Eye :size="14" /> Lihat Detail
                  </button>
                  <div class="text-[11px] text-text-secondary line-clamp-2" :title="item.excess_items.map(e => e.type + ' (' + e.excess_qty + ')').join(', ')">
                    {{ item.excess_items.map(e => `${e.brand || ''} ${e.type || ''} (${e.excess_qty})`).join(', ') }}
                  </div>
                </div>
                <div v-else>
                  <span class="text-xs text-surface-500">Aman / Pas</span>
                </div>
              </td>
              <td class="px-6 py-4">
                <div class="flex items-center gap-1">
                  <button @click="downloadPdf(item)" :disabled="isGeneratingPdf" class="p-2 text-primary-500 hover:bg-primary-500/10 rounded-lg transition-colors flex items-center justify-center disabled:opacity-50" title="Download PDF">
                    <Loader2 v-if="isGeneratingPdf && selectedPdfItem?.id === item.id" :size="16" class="animate-spin" />
                    <Download v-else :size="16" />
                  </button>
                  <button @click="deleteHistory(item.id)" class="p-2 text-red-500 hover:bg-red-500/10 rounded-lg transition-colors" title="Hapus Riwayat">
                    <Trash2 :size="16" />
                  </button>
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

    <!-- Modal Detail Barang Lebih -->
    <div v-if="showExcessModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
      <div class="bg-surface-800 border border-surface-700 rounded-2xl w-full max-w-2xl max-h-[90vh] flex flex-col shadow-2xl">
        <div class="flex items-center justify-between p-5 border-b border-surface-700">
          <h2 class="text-lg font-bold text-text-primary flex items-center gap-2">
            <Plus :size="20" class="text-amber-500" /> Detail Barang Lebih
          </h2>
          <button @click="showExcessModal = false" class="text-surface-400 hover:text-text-primary transition-colors">
            <X :size="20" />
          </button>
        </div>
        <div class="p-5 overflow-y-auto custom-scrollbar flex-1 space-y-4">
          <div v-for="(excess, idx) in selectedExcessItems" :key="idx" class="bg-surface-900 border border-surface-700 rounded-xl p-4">
            <div class="grid grid-cols-2 gap-4 mb-4">
              <div>
                <span class="block text-[10px] text-text-secondary uppercase tracking-wider mb-0.5">Brand & Tipe</span>
                <span class="text-sm font-bold text-text-primary">{{ excess.brand }} {{ excess.type }}</span>
              </div>
              <div>
                <span class="block text-[10px] text-text-secondary uppercase tracking-wider mb-0.5">Storage</span>
                <span class="text-sm font-semibold text-text-primary">{{ excess.storage || '-' }}</span>
              </div>
              <div>
                <span class="block text-[10px] text-text-secondary uppercase tracking-wider mb-0.5">Jumlah Unit</span>
                <span class="text-sm font-semibold text-text-primary">{{ excess.excess_qty }}</span>
              </div>
              <div>
                <span class="block text-[10px] text-text-secondary uppercase tracking-wider mb-0.5">Keterangan / IMEI</span>
                <span class="text-sm font-semibold text-text-primary">{{ excess.notes || '-' }}</span>
              </div>
            </div>
            
            <div v-if="excess.photos && parsePhotos(excess.photos).length > 0">
              <span class="block text-[10px] text-text-secondary uppercase tracking-wider mb-2">Foto Barang</span>
              <div class="flex flex-wrap gap-2">
                <a v-for="(photo, pIdx) in parsePhotos(excess.photos)" :key="pIdx" :href="getImageUrl(photo)" target="_blank" class="block w-20 h-20 rounded-lg overflow-hidden border border-surface-600 hover:border-primary-500 transition-colors">
                  <img :src="getImageUrl(photo)" class="w-full h-full object-cover" />
                </a>
              </div>
            </div>
            <div v-else>
               <span class="text-xs text-text-secondary italic">Tidak ada foto dilampirkan.</span>
            </div>
          </div>
        </div>
        <div class="p-4 border-t border-surface-700 flex justify-end">
          <button @click="showExcessModal = false" class="px-5 py-2 bg-surface-700 hover:bg-surface-600 text-text-primary rounded-lg font-medium transition-colors">
            Tutup
          </button>
        </div>
      </div>
    </div>
    <!-- Hidden Print Area for PDF Generation -->
    <div class="fixed" style="top: -9999px; left: -9999px;">
        <div ref="pdfTemplate" class="w-[800px] bg-white p-8 text-black relative"
            style="font-family: sans-serif; min-height: 1122px;">
            <!-- Watermark -->
            <div class="absolute inset-0 flex items-center justify-center pointer-events-none z-0" style="z-index: 0; opacity: 0.08;">
                <img src="/images/ps.png" alt="" style="width: 600px; height: 600px; object-fit: contain;" />
            </div>

            <div class="relative z-10" v-if="selectedPdfItem" style="z-index: 10;">
                <div style="display: flex; align-items: center; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 2px solid black;">
                    <img src="/images/logo-pstore.png" alt="PSTORE" style="height: 50px; width: 160px; object-fit: contain; flex-shrink: 0; margin-right: 16px; display: block;" />
                    <div style="flex: 1;">
                        <h1 style="font-weight: bold; text-transform: uppercase; font-size: 24px; margin: 0; line-height: 1.2;">Bukti Pengecekan Security</h1>
                        <p style="font-size: 14px; margin: 4px 0 0 0;">No. Surat Jalan: {{ selectedPdfItem.receipt_id }}</p>
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; margin-bottom: 24px;">
                    <div style="flex: 1; text-align: left;">
                        <p style="font-size: 12px; color: #6b7280; text-transform: uppercase; margin: 0 0 4px 0;">Dari Cabang</p>
                        <p style="font-weight: bold; margin: 0;">{{ selectedPdfItem.stock_out?.source_name || selectedPdfItem.stock_out?.branch?.name || selectedPdfItem.stock_out?.warehouse?.name || selectedPdfItem.stock_out?.online_shop?.name || '-' }}</p>
                    </div>
                    <div style="flex: 1; text-align: right;">
                        <p style="font-size: 12px; color: #6b7280; text-transform: uppercase; margin: 0 0 4px 0;">Tujuan Cabang</p>
                        <p style="font-weight: bold; margin: 0;">{{ selectedPdfItem.stock_out?.destination?.name || 'Cabang Tujuan' }}</p>
                    </div>
                </div>

                <div class="mb-6">
                    <p class="text-xs text-gray-500 uppercase mb-2">Data Pemeriksa</p>
                    <p><strong>Security:</strong> {{ selectedPdfItem.security_name }}</p>
                    <p><strong>Waktu Selesai:</strong> {{ new Date(selectedPdfItem.created_at).toLocaleString('id-ID') }}</p>
                </div>

                <div class="mb-10">
                    <h2 class="font-bold border-b border-gray-300 pb-2 mb-4 flex justify-between items-end">
                        <span>Daftar Barang (Bawaan)</span>
                        <span class="text-xs font-normal">Total: {{ getMergedPdfItems(selectedPdfItem).length }}</span>
                    </h2>
                    <table class="w-full border-collapse border border-black text-black font-bold" style="width: 100%; font-size: 12px;">
                        <thead>
                            <tr>
                                <th class="border border-black px-2 py-2 text-center uppercase" style="width: 5%; vertical-align: middle;">NO</th>
                                <th class="border border-black px-3 py-2 text-left uppercase" style="width: 35%; vertical-align: middle;">DESKRIPSI
                                    BARANG<br>(MEREK, TIPE)</th>
                                <th class="border border-black px-3 py-2 text-left uppercase" style="width: 25%; vertical-align: middle;">IMEI</th>
                                <th class="border border-black px-3 py-2 text-left uppercase" style="width: 15%; vertical-align: middle;">KONDISI</th>
                                <th class="border border-black px-2 py-2 text-center uppercase" style="width: 10%; vertical-align: middle;">QTY</th>
                                <th class="border border-black px-2 py-2 text-center uppercase" style="width: 10%; vertical-align: middle;">CEK</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(chk, idx) in getMergedPdfItems(selectedPdfItem)" :key="'chk-' + idx">
                                <td class="border border-black px-2 py-2 text-center align-middle" style="vertical-align: middle;">{{ idx + 1 }}</td>
                                <td class="border border-black px-3 py-2 align-middle uppercase" style="vertical-align: middle;">
                                    {{ chk.brand || '' }} {{ chk.product_name || '' }}
                                </td>
                                <td class="border border-black px-3 py-2 align-middle uppercase" style="vertical-align: middle;">
                                    {{ chk.imei || '-' }}
                                </td>
                                <td class="border border-black px-3 py-2 align-middle uppercase" style="vertical-align: middle;">
                                    {{ chk.condition || '-' }}
                                </td>
                                <td class="border border-black px-2 py-2 text-center align-middle" style="vertical-align: middle;">
                                    {{ chk.qty || 1 }}
                                </td>
                                <td class="border border-black px-2 py-2 text-center align-middle" style="vertical-align: middle;">
                                    <span v-if="chk.is_present" class="text-green-600">✔</span>
                                    <span v-else class="text-red-600">✘</span>
                                </td>
                            </tr>
                            <tr v-if="getMergedPdfItems(selectedPdfItem).length === 0">
                                <td colspan="6" class="border border-black px-2 py-2 text-center italic text-gray-500">Tidak ada data pengecekan detail</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mb-10">
                    <h2 class="font-bold border-b border-gray-300 pb-2 mb-4">Barang Lebih / Tambahan</h2>
                    <ul v-if="selectedPdfItem.excess_items && selectedPdfItem.excess_items.length > 0" class="list-disc pl-5 text-sm space-y-1">
                        <li v-for="(excess, idx) in selectedPdfItem.excess_items" :key="idx">
                            <strong>{{ excess.brand }} - {{ excess.type }}</strong>
                            <span v-if="excess.storage"> - {{ excess.storage }}</span>
                            (Qty: {{ excess.excess_qty }})
                            <span v-if="excess.notes">- <em>{{ excess.notes }}</em></span>
                        </li>
                    </ul>
                    <p v-else class="text-sm italic text-gray-500 mt-2">Tidak ada barang tambahan</p>
                </div>

                <div v-if="selectedPdfItem.notes" class="mb-10">
                    <h2 class="font-bold border-b border-gray-300 pb-2 mb-4">Catatan Tambahan</h2>
                    <p class="text-sm bg-gray-50 p-4 rounded-lg border border-gray-200">{{ selectedPdfItem.notes }}</p>
                </div>

            </div>
        </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, nextTick } from 'vue';
import api from '../../api/axios';
import { useToast } from '../../composables/useToast';
import html2canvas from 'html2canvas';
import { jsPDF } from 'jspdf';
import { 
  Search, RefreshCw, Loader2, ShieldCheck, User, UserCircle, ChevronLeft, ChevronRight, Eye, X, Plus, Trash2, Download
} from 'lucide-vue-next';

const toast = useToast();
const pdfTemplate = ref(null);
const selectedPdfItem = ref(null);
const isGeneratingPdf = ref(false);

const getMergedPdfItems = (item) => {
  if (!item) return [];
  
  // Mengumpulkan data barang asli dari stock_out (untuk mendapatkan IMEI dan kondisi)
  const originalItems = [];
  if (item.stock_out) {
    if (item.stock_out.items) {
      item.stock_out.items.forEach(hp => {
        originalItems.push({
          brand: hp.product?.brand || hp.product?.brandRelation?.name || 'Brand',
          product_name: hp.product?.name + (hp.storage ? ` ${hp.storage}` : ''),
          imei: hp.pivot?.notes || hp.imei || '-',
          condition: hp.condition || '-',
          qty: 1,
          is_present: true
        });
      });
    }
    if (item.stock_out.non_hp_items) {
      item.stock_out.non_hp_items.forEach(nonHp => {
        originalItems.push({
          brand: nonHp.product?.brand || nonHp.brand || 'Brand',
          product_name: nonHp.product?.name || nonHp.product_name || 'Aksesoris',
          imei: '-',
          condition: '-',
          qty: nonHp.quantity || nonHp.qty || 1,
          is_present: true
        });
      });
    }
  }

  // Jika hasil cek sudah tersimpan di database, gabungkan status is_present-nya dengan data original 
  // berdasarkan index/urutan, karena array checked_items di-save persis sesuai urutan.
  if (item.checked_items && item.checked_items.length > 0) {
    return item.checked_items.map((c, idx) => {
      const orig = originalItems[idx] || {};
      return {
        brand: orig.brand || c.brand,
        product_name: orig.product_name || c.product_name,
        imei: orig.imei || c.imei || '-',
        condition: orig.condition || c.condition || '-',
        qty: orig.qty || c.qty || (c.is_present ? 1 : 0),
        is_present: c.is_present
      };
    });
  }

  // Fallback ke data asli
  return originalItems;
};

const downloadPdf = async (item) => {
  if (isGeneratingPdf.value) return;
  
  selectedPdfItem.value = item;
  isGeneratingPdf.value = true;
  toast.info('Sedang menyiapkan file PDF...');

  try {
    await nextTick();
    await new Promise(r => setTimeout(r, 600)); // wait for images to load if any

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
    pdf.save(`Bukti_Security_${item.receipt_id}.pdf`);
  } catch (e) {
    console.error("Failed to generate PDF", e);
    toast.error("Gagal membuat PDF");
  } finally {
    isGeneratingPdf.value = false;
    selectedPdfItem.value = null;
  }
};

const history = ref([]);
const isLoading = ref(false);
const searchQuery = ref('');
const currentPage = ref(1);
const totalPages = ref(1);
let debounceTimeout = null;

const showExcessModal = ref(false);
const selectedExcessItems = ref([]);

const viewExcessDetails = (items) => {
  selectedExcessItems.value = items;
  showExcessModal.value = true;
};

const parsePhotos = (photosStr) => {
  try {
    if (Array.isArray(photosStr)) return photosStr;
    return JSON.parse(photosStr) || [];
  } catch(e) {
    return [];
  }
};

const getImageUrl = (path) => {
  if(path.startsWith('http')) return path;
  return import.meta.env.VITE_API_URL.replace('/api', '') + '/' + path;
};

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

const deleteHistory = async (id) => {
  if (!confirm('Apakah Anda yakin ingin menghapus riwayat scan ini? Anda dapat melakukan scan ulang setelahnya.')) return;
  
  try {
    await api.delete(`/security-checks/${id}`);
    toast.success('Riwayat berhasil dihapus.');
    fetchHistory(currentPage.value);
  } catch (err) {
    toast.error('Gagal menghapus riwayat: ' + (err.response?.data?.message || err.message));
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
  background-color: rgba(156, 163, 175, 0.3);
  border-radius: 9999px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background-color: rgba(156, 163, 175, 0.5);
}
</style>
