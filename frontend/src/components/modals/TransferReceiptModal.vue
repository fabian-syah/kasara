<template>
    <Teleport to="body">
        <transition name="fade">
            <div v-if="isOpen" id="transfer-receipt-modal-wrapper"
                class="fixed inset-0 z-[99999] flex items-center justify-center p-2 sm:p-4 bg-black/60 backdrop-blur-sm print:p-0 print:bg-white"
                @click.self="close">
                <div
                    class="bg-white dark:bg-surface-800 rounded-2xl sm:rounded-3xl w-full max-w-3xl overflow-hidden shadow-2xl print:shadow-none print:rounded-none print:max-w-full flex flex-col h-full max-h-[92vh] sm:max-h-[95vh]">
                    
                    <!-- Modal Header (hide on print) -->
                    <div
                        class="p-6 flex justify-between items-center border-b border-gray-100 dark:border-surface-700 print:hidden shrink-0">
                        <h3 class="text-lg font-bold text-text-primary">
                            Cetak Bukti Transfer Cabang
                        </h3>
                        <div class="flex items-center gap-2">
                            <button @click="close"
                                class="p-2 hover:bg-gray-100 dark:hover:bg-surface-700 rounded-xl transition-colors">
                                <X :size="20" class="text-gray-500" />
                            </button>
                        </div>
                    </div>

                    <!-- Receipt Content -->
                    <div id="transfer-receipt-content"
                        class="flex-1 overflow-y-auto p-4 sm:p-8 print:p-0 bg-gray-100/50 dark:bg-surface-900/50 print:bg-white custom-scrollbar">
                        
                        <div v-if="transfer"
                            class="nota-paper w-full max-w-[700px] mx-auto bg-white dark:bg-surface-900 p-6 sm:p-8 rounded-2xl text-gray-900 dark:text-gray-100 font-sans text-sm shadow-xl print:shadow-none print:max-w-full print:mx-0 print:p-6 print:bg-white print:text-black relative overflow-hidden select-none border border-gray-200 dark:border-surface-700 print:border-gray-300">
                            
                            <!-- Header Section -->
                            <div class="flex justify-between items-start border-b-2 border-black dark:border-white print:border-black pb-4 mb-6">
                                <div class="flex items-center gap-4">
                                    <img src="/images/ps.png" alt="PSTORE" class="w-16 h-16 object-contain dark:invert print:invert-0" />
                                    <div>
                                        <h1 class="text-2xl font-black text-danger tracking-tighter leading-none mb-1">SURAT JALAN / TRANSFER</h1>
                                        <p class="text-xs font-bold text-gray-600 dark:text-gray-400 print:text-gray-600 uppercase">{{ authStore.userBranch?.name || 'PSTORE' }}</p>
                                    </div>
                                </div>
                                
                                <div class="flex flex-col items-center justify-center border border-gray-300 p-2 sm:p-3 rounded-xl bg-white shadow-sm min-w-[100px] sm:min-w-[120px] min-h-[100px] sm:min-h-[120px]">
                                    <img v-if="qrCodeDataUrl" :src="qrCodeDataUrl" 
                                        alt="QR Resi" class="w-16 h-16 sm:w-20 sm:h-20 object-contain" />
                                    <div v-else class="w-16 h-16 sm:w-20 sm:h-20 bg-gray-100 animate-pulse rounded-lg"></div>
                                    <span class="text-[8px] sm:text-[9px] font-black uppercase mt-1 sm:mt-2 tracking-widest !text-black">Scan by Security</span>
                                </div>
                            </div>

                            <!-- Meta Data Grid -->
                            <div class="grid grid-cols-2 gap-6 mb-6 text-xs border border-gray-200 dark:border-surface-700 print:border-gray-200 rounded-xl p-4 bg-gray-50/50 dark:bg-surface-800/50 print:bg-gray-50/50">
                                <div class="space-y-3">
                                    <div>
                                        <p class="text-[9px] font-black text-gray-500 dark:text-gray-400 print:text-gray-500 uppercase tracking-widest">No. Resi / Referensi</p>
                                        <p class="font-black text-sm text-black dark:text-white print:text-black uppercase">{{ transfer.receipt_id }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[9px] font-black text-gray-500 dark:text-gray-400 print:text-gray-500 uppercase tracking-widest">Tanggal Transfer</p>
                                        <p class="font-bold text-gray-800 dark:text-gray-200 print:text-gray-800">{{ displayDate }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[9px] font-black text-gray-500 dark:text-gray-400 print:text-gray-500 uppercase tracking-widest">Status Pengiriman</p>
                                        <p class="font-bold text-gray-800 dark:text-gray-200 print:text-gray-800 uppercase">{{ transfer.status }}</p>
                                    </div>
                                </div>
                                
                                <div class="space-y-3">
                                    <div>
                                        <p class="text-[9px] font-black text-gray-500 dark:text-gray-400 print:text-gray-500 uppercase tracking-widest">Pengirim (Dari)</p>
                                        <p class="font-bold text-gray-800 dark:text-gray-200 print:text-gray-800 uppercase">{{ transfer.inventory_user?.name || transfer.inventoryUser?.name || transfer.user?.name || 'Cabang Asal' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[9px] font-black text-gray-500 dark:text-gray-400 print:text-gray-500 uppercase tracking-widest">Penerima (Tujuan)</p>
                                        <p class="font-bold text-gray-800 dark:text-gray-200 print:text-gray-800 uppercase">{{ transfer.destination?.name || transfer.receiver_name || '-' }}</p>
                                    </div>
                                    <div v-if="transfer.expedition_name">
                                        <p class="text-[9px] font-black text-gray-500 dark:text-gray-400 print:text-gray-500 uppercase tracking-widest">Ekspedisi</p>
                                        <p class="font-bold text-gray-800 dark:text-gray-200 print:text-gray-800 uppercase">{{ transfer.expedition_name }} - {{ transfer.expedition_tracking_no }}</p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Catatan -->
                            <div class="mb-6 border border-gray-200 dark:border-surface-700 print:border-gray-200 rounded-xl p-3 bg-gray-50/50 dark:bg-surface-800/50 print:bg-gray-50/50 text-xs">
                                <span class="font-black text-gray-500 dark:text-gray-400 print:text-gray-500 uppercase tracking-widest text-[9px] block mb-1">Catatan:</span>
                                <span class="font-bold italic text-gray-800 dark:text-gray-200 print:text-gray-800">"{{ transfer.transfer_notes || transfer.notes || 'Tidak ada catatan' }}"</span>
                            </div>

                            <!-- Items Table -->
                            <div class="border border-gray-300 dark:border-surface-600 print:border-gray-300 rounded-xl overflow-hidden mb-8">
                                <table class="w-full text-xs text-left">
                                    <thead class="bg-gray-900 dark:bg-surface-800 print:bg-white print:border-y-2 print:border-black text-white print:text-black">
                                        <tr>
                                            <th class="px-4 py-2.5 font-black uppercase tracking-wider text-[10px] w-12 text-center dark:text-white print:!text-black">No</th>
                                            <th class="px-4 py-2.5 font-black uppercase tracking-wider text-[10px] dark:text-white print:!text-black">Deskripsi Barang (Merek, Tipe)</th>
                                            <th class="px-4 py-2.5 font-black uppercase tracking-wider text-[10px] w-48 dark:text-white print:!text-black">IMEI / S/N</th>
                                            <th class="px-4 py-2.5 font-black uppercase tracking-wider text-[10px] w-24 dark:text-white print:!text-black">Kondisi</th>
                                            <th class="px-4 py-2.5 font-black uppercase tracking-wider text-[10px] w-16 text-center dark:text-white print:!text-black">Qty</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-surface-700 print:divide-gray-200">
                                        <tr v-for="(item, index) in allItems" :key="index" class="hover:bg-gray-50 dark:hover:bg-surface-800 print:hover:bg-transparent">
                                            <td class="px-4 py-2 text-center font-bold text-gray-500 dark:text-gray-400 print:text-gray-500">{{ index + 1 }}</td>
                                            <td class="px-4 py-2 font-bold text-black dark:text-white print:text-black uppercase">
                                                {{ item.name }}
                                            </td>
                                            <td class="px-4 py-2 font-mono font-bold text-gray-700 dark:text-gray-300 print:text-gray-700 text-[11px]">{{ item.imei }}</td>
                                            <td class="px-4 py-2 font-bold text-gray-700 dark:text-gray-300 print:text-gray-700 uppercase text-[10px]">{{ item.condition }}</td>
                                            <td class="px-4 py-2 text-center font-black text-black dark:text-white print:text-black">{{ item.qty }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Signature Area -->
                            <div class="grid grid-cols-3 gap-4 text-center mt-12 mb-4">
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 print:text-gray-500 mb-16">Pengirim</p>
                                    <div class="border-b border-gray-400 dark:border-gray-500 print:border-gray-400 w-32 mx-auto mb-2"></div>
                                    <p class="text-[10px] font-bold text-gray-800 dark:text-gray-200 print:text-gray-800 uppercase">{{ transfer.inventory_user?.name || transfer.user?.name || 'PSTORE' }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 print:text-gray-500 mb-16">Security / Kurir</p>
                                    <div class="border-b border-gray-400 dark:border-gray-500 print:border-gray-400 w-32 mx-auto mb-2"></div>
                                    <p class="text-[10px] font-bold text-gray-800 dark:text-gray-200 print:text-gray-800 uppercase">( ............................ )</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 print:text-gray-500 mb-16">Penerima</p>
                                    <div class="border-b border-gray-400 dark:border-gray-500 print:border-gray-400 w-32 mx-auto mb-2"></div>
                                    <p class="text-[10px] font-bold text-gray-800 dark:text-gray-200 print:text-gray-800 uppercase">{{ transfer.receiver_name || '( ............................ )' }}</p>
                                </div>
                            </div>
                            
                            <!-- Footer note -->
                            <div class="text-center text-[9px] text-gray-400 dark:text-gray-500 print:text-gray-400 font-bold uppercase tracking-widest mt-8 border-t border-dashed border-gray-300 dark:border-surface-600 print:border-gray-300 pt-4">
                                Dokumen ini dicetak otomatis pada {{ currentDateTime }} <br> Scan QR Code untuk detail pengiriman dan tracking.
                            </div>
                        </div>
                    </div>

                    <!-- Footer Actions -->
                    <div class="p-4 bg-white dark:bg-surface-800 border-t border-gray-100 dark:border-surface-700 print:hidden shrink-0 flex justify-end gap-3">
                        <button @click="close" class="px-5 py-2.5 rounded-xl font-bold text-sm bg-surface-100 dark:bg-surface-700 text-text-primary hover:bg-surface-200 dark:hover:bg-surface-600 transition-all">
                            Tutup
                        </button>
                        <button @click="printReceipt" class="px-6 py-2.5 rounded-xl font-black text-sm bg-primary-600 text-white hover:bg-primary-700 transition-all shadow-lg shadow-primary-500/25 flex items-center gap-2">
                            <Printer :size="18" /> Cetak Surat Jalan
                        </button>
                    </div>
                </div>
            </div>
        </transition>
    </Teleport>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Printer, X } from 'lucide-vue-next';
import { useAuthStore } from '../../store/auth';
import QRCode from 'qrcode';

const authStore = useAuthStore();

const props = defineProps({
    isOpen: Boolean,
    transfer: Object
});

const emit = defineEmits(['close']);

function close() {
    emit('close');
}

// Esc key to close
function handleKeydown(e) {
    if (e.key === 'Escape' && props.isOpen) close();
}
onMounted(() => window.addEventListener('keydown', handleKeydown));
onUnmounted(() => window.removeEventListener('keydown', handleKeydown));

// Computed Properties
const trackingUrl = computed(() => {
    if (!props.transfer) return '';
    const baseUrl = window.location.origin;
    return `${baseUrl}/track?q=${props.transfer.receipt_id}`;
});

const qrCodeDataUrl = ref('');

watch(trackingUrl, async (newUrl) => {
    if (newUrl) {
        try {
            qrCodeDataUrl.value = await QRCode.toDataURL(newUrl, { margin: 1, width: 120 });
        } catch (err) {
            console.error('Failed to generate QR code', err);
        }
    } else {
        qrCodeDataUrl.value = '';
    }
}, { immediate: true });

const displayDate = computed(() => {
    if (!props.transfer?.created_at) return '-';
    return new Date(props.transfer.created_at).toLocaleDateString('id-ID', {
        day: '2-digit', month: 'long', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });
});

const currentDateTime = computed(() => {
    return new Date().toLocaleDateString('id-ID', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });
});

const allItems = computed(() => {
    if (!props.transfer) return [];
    let items = [];
    
    // HP items
    const hps = props.transfer.items || [];
    hps.forEach(hp => {
        const brand = hp.product?.brand?.name || hp.product?.brandRelation?.name || hp.product?.brand || hp.brand || '';
        const name = `${brand} ${hp.product?.name || ''}`.trim();
        items.push({
            name: name || 'PSTORE UNIT',
            imei: hp.imei || '-',
            condition: hp.condition === 'new' ? 'Baru' : (hp.condition === 'ex_ibox' ? 'Ex iBox' : (hp.condition === 'second' ? 'Second' : (hp.condition || '-'))),
            qty: 1
        });
    });
    
    // Non-HP items
    const nonHps = props.transfer.non_hp_items || props.transfer.nonHpItems || [];
    nonHps.forEach(acc => {
        const brand = acc.product?.brand?.name || acc.product?.brandRelation?.name || acc.product?.brand || acc.brand || '';
        const name = `${brand} ${acc.product?.name || acc.product_name || ''}`.trim();
        items.push({
            name: name || 'Aksesoris',
            imei: '-',
            condition: 'Baru',
            qty: acc.quantity || acc.qty || 1
        });
    });
    
    return items;
});

function printReceipt() {
    // Basic window print
    window.print();
}
</script>

<style scoped>
@reference "../../style.css";

@media print {
    @page {
        size: A5 portrait;
        margin: 10mm;
    }
    body > *:not(#transfer-receipt-modal-wrapper) {
        display: none !important;
    }
    #transfer-receipt-modal-wrapper {
        position: static !important;
        display: block !important;
        height: auto !important;
        width: auto !important;
        background: white !important;
        padding: 0 !important;
    }
    #transfer-receipt-modal-wrapper > div {
        max-height: none !important;
        height: auto !important;
        box-shadow: none !important;
        overflow: visible !important;
        padding: 0 !important;
        margin: 0 !important;
    }
    #transfer-receipt-content {
        overflow: visible !important;
        height: auto !important;
        padding: 0 !important;
    }
    .nota-paper {
        border: none !important;
        box-shadow: none !important;
        margin: 0 !important;
        padding: 0 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        zoom: 0.85; /* Mengecilkan sedikit agar selalu pas 1 halaman A5 */
    }
    /* Memaksa semua elemen dalam nota nge-print warna background */
    .nota-paper * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    
    /* OVERRIDE DARK MODE SAAT PRINT */
    .nota-paper, .nota-paper * {
        color: black !important;
    }
    .nota-paper {
        background-color: white !important;
    }
    .nota-paper table th {
        background-color: #e5e7eb !important; /* Light gray */
        color: black !important;
        border-bottom: 2px solid black !important;
    }
    .nota-paper table tr, .nota-paper table td {
        background-color: white !important;
    }
    .nota-paper .text-danger {
        color: #dc2626 !important;
    }
    .nota-paper .text-gray-500 {
        color: #4b5563 !important;
    }
    /* Hide scrollbars during print */
    ::-webkit-scrollbar {
        display: none;
    }
}

.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    @apply bg-gray-300 dark:bg-surface-700 rounded-full;
}
</style>
