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
                            class="nota-paper w-full max-w-[700px] mx-auto bg-white dark:bg-surface-900 p-6 sm:p-8 rounded-2xl text-gray-900 dark:text-gray-100 font-sans text-sm shadow-xl print:shadow-none print:max-w-full print:mx-0 print:p-2 print:bg-white print:text-black relative overflow-hidden select-none border border-gray-200 dark:border-surface-700 print:border-gray-300">
                            
                            <!-- Header Section -->
                            <div class="receipt-header flex justify-between items-start border-b-2 border-black dark:border-white print:border-black pb-4 mb-6">
                                <div class="flex items-center gap-4">
                                    <img src="/images/ps.png" alt="PSTORE" class="receipt-logo w-16 h-16 object-contain dark:invert print:invert-0" />
                                    <div>
                                        <h1 class="receipt-title text-2xl font-black text-danger tracking-tighter leading-none mb-1">SURAT JALAN / TRANSFER</h1>
                                        <p class="receipt-subtitle text-xs font-bold text-gray-600 dark:text-gray-400 print:text-gray-600 uppercase">{{ authStore.userBranch?.name || 'PSTORE' }}</p>
                                    </div>
                                </div>
                                
                                <div class="qr-code-box flex flex-col items-center justify-center border border-gray-300 p-2 sm:p-3 rounded-xl bg-white shadow-sm min-w-[100px] sm:min-w-[120px] min-h-[100px] sm:min-h-[120px]">
                                    <img v-if="qrCodeDataUrl" :src="qrCodeDataUrl" 
                                        alt="QR Resi" class="qr-code-img w-16 h-16 sm:w-20 sm:h-20 object-contain" />
                                    <div v-else class="w-16 h-16 sm:w-20 sm:h-20 bg-gray-100 animate-pulse rounded-lg"></div>
                                    <span class="text-[8px] sm:text-[9px] font-black uppercase mt-1 sm:mt-2 tracking-widest !text-black">Scan by Security</span>
                                </div>
                            </div>

                            <!-- Meta Data Grid -->
                            <div class="meta-grid grid grid-cols-2 gap-6 mb-6 text-xs border border-gray-200 dark:border-surface-700 print:border-gray-200 rounded-xl p-4 bg-gray-50/50 dark:bg-surface-800/50 print:bg-gray-50/50">
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
                            <div class="notes-box mb-6 border border-gray-200 dark:border-surface-700 print:border-gray-200 rounded-xl p-3 bg-gray-50/50 dark:bg-surface-800/50 print:bg-gray-50/50 text-xs">
                                <span class="font-black text-gray-500 dark:text-gray-400 print:text-gray-500 uppercase tracking-widest text-[9px] block mb-1">Catatan:</span>
                                <span class="font-bold italic text-gray-800 dark:text-gray-200 print:text-gray-800">"{{ transfer.transfer_notes || transfer.notes || 'Tidak ada catatan' }}"</span>
                            </div>

                            <!-- Items Table -->
                            <div class="table-container border border-gray-300 dark:border-surface-600 rounded-xl print:rounded-none overflow-hidden mb-8">
                                <table class="w-full text-xs text-left print:!bg-white">
                                    <thead class="bg-white dark:bg-surface-800 border-b border-gray-200 dark:border-surface-700 print:!bg-white print:border-y-2 print:border-white text-gray-950 dark:text-white">
                                        <tr>
                                            <th class="px-4 py-2.5 font-black uppercase tracking-wider text-[10px] w-12 text-center text-gray-700 dark:text-gray-300 print:!text-black print:!bg-white">No</th>
                                            <th class="px-4 py-2.5 font-black uppercase tracking-wider text-[10px] text-gray-700 dark:text-gray-300 print:!text-black print:!bg-white">Deskripsi Barang (Merek, Tipe)</th>
                                            <th v-if="hasImeiItems" class="px-4 py-2.5 font-black uppercase tracking-wider text-[10px] w-48 text-gray-700 dark:text-gray-300 print:!text-black print:!bg-white">IMEI</th>
                                            <th class="px-4 py-2.5 font-black uppercase tracking-wider text-[10px] w-24 text-gray-700 dark:text-gray-300 print:!text-black print:!bg-white">Kondisi</th>
                                            <th class="px-4 py-2.5 font-black uppercase tracking-wider text-[10px] w-16 text-center text-gray-700 dark:text-gray-300 print:!text-black print:!bg-white">Qty</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-surface-700 print:divide-black">
                                        <tr v-for="(item, index) in allItems" :key="index" class="bg-white dark:bg-surface-900 print:!bg-white hover:bg-gray-50 dark:hover:bg-surface-800 print:hover:!bg-white">
                                            <td class="px-4 py-2 text-center font-bold text-gray-500 dark:text-gray-400 print:!text-black print:!bg-white">{{ index + 1 }}</td>
                                            <td class="px-4 py-2 font-bold text-black dark:text-white print:!text-black uppercase print:!bg-white">
                                                {{ item.name }}
                                            </td>
                                            <td v-if="hasImeiItems" class="px-4 py-2 font-mono font-bold text-gray-700 dark:text-gray-300 print:!text-black text-[11px] print:!bg-white">{{ item.imei }}</td>
                                            <td class="px-4 py-2 font-bold text-gray-700 dark:text-gray-300 print:!text-black uppercase text-[10px] print:!bg-white">{{ item.condition }}</td>
                                            <td class="px-4 py-2 text-center font-black text-black dark:text-white print:!text-black print:!bg-white">{{ item.qty }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Signature Area -->
                            <div class="signature-area grid grid-cols-2 gap-16 text-center mt-12 print:mt-2 mb-4 print:mb-0">
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 print:text-gray-500 mb-16 print:mb-6">Pengirim</p>
                                    <div class="border-b border-gray-400 dark:border-gray-500 print:border-gray-400 w-32 mx-auto mb-2 print:mb-1"></div>
                                    <p class="text-[10px] font-bold text-gray-800 dark:text-gray-200 print:text-gray-800 uppercase">{{ transfer.inventory_user?.name || transfer.user?.name || 'PSTORE' }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 print:text-gray-500 mb-16 print:mb-6">Security / Kurir</p>
                                    <div class="border-b border-gray-400 dark:border-gray-500 print:border-gray-400 w-32 mx-auto mb-2 print:mb-1"></div>
                                    <p class="text-[10px] font-bold text-gray-800 dark:text-gray-200 print:text-gray-800 uppercase">( ............................ )</p>
                                </div>
                            </div>
                            
                            <!-- Footer note -->
                            <div class="receipt-footer text-center text-[9px] text-gray-400 dark:text-gray-500 print:text-gray-400 font-bold uppercase tracking-widest mt-8 print:mt-2 border-t border-dashed border-gray-300 dark:border-surface-600 print:border-gray-300 pt-4 print:pt-1">
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

// Print events to temporarily disable dark mode during printing/previewing
let wasDark = false;

function handleBeforePrint() {
    if (props.isOpen) {
        wasDark = document.documentElement.classList.contains('dark');
        if (wasDark) {
            document.documentElement.classList.remove('dark');
        }
    }
}

function handleAfterPrint() {
    if (wasDark) {
        document.documentElement.classList.add('dark');
        wasDark = false;
    }
}

watch(() => props.isOpen, (newVal) => {
    if (newVal) {
        window.addEventListener('beforeprint', handleBeforePrint);
        window.addEventListener('afterprint', handleAfterPrint);
    } else {
        window.removeEventListener('beforeprint', handleBeforePrint);
        window.removeEventListener('afterprint', handleAfterPrint);
        if (wasDark) {
            document.documentElement.classList.add('dark');
            wasDark = false;
        }
    }
});

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
    if (props.isOpen) {
        window.addEventListener('beforeprint', handleBeforePrint);
        window.addEventListener('afterprint', handleAfterPrint);
    }
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
    window.removeEventListener('beforeprint', handleBeforePrint);
    window.removeEventListener('afterprint', handleAfterPrint);
    if (wasDark) {
        document.documentElement.classList.add('dark');
    }
});

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
        
        // Get storage and RAM
        const ram = hp.ram || hp.product?.ram || '';
        const storage = hp.storage || hp.product?.storage || '';
        let capacity = '';
        if (ram && storage) {
            const r = /^\d+$/.test(ram.toString()) ? ram : ram.toString().replace(/GB/gi, '');
            const s = /^\d+$/.test(storage.toString()) ? storage : storage.toString().replace(/GB/gi, '');
            capacity = `${r}/${s}GB`;
        } else if (storage || ram) {
            const val = (storage || ram).toString();
            capacity = /^\d+$/.test(val) ? val + 'GB' : val;
        }
        const capacitySuffix = capacity ? ` ${capacity}` : '';
        
        const name = `${brand} ${hp.product?.name || ''}${capacitySuffix}`.trim();
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

const hasImeiItems = computed(() => {
    return allItems.value.some(item => item.imei && item.imei !== '-');
});

function printReceipt() {
    // Basic window print
    window.print();
}
</script>

<style scoped>
@reference "../../style.css";

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

<style>
/* UNSCOPED PRINT CSS - GUARANTEED TO WORK GLOBALLY */
@media print {
    @page {
        size: A5 portrait;
        margin: 6mm 6mm 6mm 6mm;
    }

    /* Robustly hide ALL other elements at the root body level except the print wrappers to avoid blank pages and conflicts */
    body > :not(#transfer-receipt-modal-wrapper):not(#receipt-modal-print-wrapper) {
        display: none !important;
    }

    html,
    body {
        height: auto !important;
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
        overflow: visible !important;
    }

    #transfer-receipt-modal-wrapper,
    #transfer-receipt-modal-wrapper > div,
    #transfer-receipt-content {
        display: block !important;
        position: relative !important;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
        height: auto !important;
        min-height: 0 !important;
        max-height: none !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: visible !important;
        box-sizing: border-box !important;
        background: white !important;
        z-index: 9999999 !important;
        flex: none !important;
        align-items: flex-start !important;
        justify-content: flex-start !important;
        transform: none !important;
        border: 0 !important;
        outline: 0 !important;
    }

    .nota-paper {
        border: none !important;
        box-shadow: none !important;
        margin: 0 auto !important;
        padding: 12px !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        background: white !important;
        background-color: white !important;
        color: black !important;
        width: 100% !important;
        max-width: 100% !important;
        height: auto !important;
        overflow: visible !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }
    
    /* Force white background and black text on ALL elements inside the receipt printout using ID to beat dark mode specificity */
    #transfer-receipt-modal-wrapper,
    #transfer-receipt-modal-wrapper *,
    html.dark #transfer-receipt-modal-wrapper,
    html.dark #transfer-receipt-modal-wrapper * {
        background: white !important;
        background-color: white !important;
        color: black !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    /* Print-specific layout adjustments to fill the A5 page nicely on a single page at 120% scale */
    .receipt-header {
        margin-bottom: 12px !important;
        padding-bottom: 8px !important;
        border-bottom-width: 1.5px !important;
    }
    
    .receipt-logo {
        width: 44px !important;
        height: 44px !important;
    }
    
    .receipt-title {
        font-size: 16px !important;
        margin-bottom: 2px !important;
    }
    
    .receipt-subtitle {
        font-size: 9.5px !important;
    }
    
    .qr-code-box {
        min-width: 72px !important;
        max-width: 72px !important;
        min-height: 72px !important;
        max-height: 72px !important;
        padding: 2px !important;
        border-radius: 6px !important;
    }
    
    .qr-code-img {
        width: 52px !important;
        height: 52px !important;
    }
    
    .qr-code-box span {
        display: none !important; /* Hide "Scan by Security" to save vertical space */
    }

    .meta-grid {
        margin-bottom: 12px !important;
        padding: 8px 10px !important;
        gap: 8px !important;
    }
    
    .meta-grid .space-y-3 {
        margin-top: 0 !important;
    }
    
    .meta-grid .space-y-3 > div {
        margin-top: 0 !important;
    }
    
    /* Override spacing utilities inside meta-grid in print */
    .meta-grid .space-y-3 > :not([hidden]) ~ :not([hidden]) {
        margin-top: 3px !important;
    }
    
    .meta-grid p.text-\[9px\] {
        font-size: 8px !important;
        line-height: 1 !important;
        margin-bottom: 2px !important;
    }
    
    .meta-grid p.text-sm {
        font-size: 11px !important;
        line-height: 1.1 !important;
    }
    
    .meta-grid p.font-bold {
        font-size: 10px !important;
        line-height: 1.1 !important;
    }

    .notes-box {
        margin-bottom: 12px !important;
        padding: 6px 10px !important;
    }
    
    .notes-box span.text-\[9px\] {
        font-size: 8px !important;
        line-height: 1 !important;
        margin-bottom: 2px !important;
    }
    
    .notes-box span.font-bold {
        font-size: 10px !important;
        line-height: 1.1 !important;
    }

    .table-container {
        margin-bottom: 14px !important;
        border: 1px solid black !important;
        border-radius: 0 !important;
    }
    
    .table-container table {
        border-collapse: collapse !important;
        width: 100% !important;
    }
    
    .table-container table th {
        padding: 4px 6px !important;
        font-size: 9px !important;
        line-height: 1.1 !important;
        border-right: 1px solid black !important;
    }
    
    .table-container table td {
        padding: 4px 6px !important;
        font-size: 9px !important;
        line-height: 1.1 !important;
        border-bottom: 1px solid black !important;
        border-right: 1px solid black !important;
    }

    .table-container table th:last-child,
    .table-container table td:last-child {
        border-right: none !important;
    }

    .table-container table tbody tr:last-child td {
        border-bottom: none !important;
    }

    .signature-area {
        margin-top: 28px !important;
        margin-bottom: 0 !important;
    }
    
    .signature-area p.text-\[10px\] {
        font-size: 8.5px !important;
    }
    
    /* Target the signature title to give it a nice vertical gap for signing */
    .signature-area p.mb-16 {
        margin-bottom: 30px !important;
    }
    
    .signature-area div.w-32 {
        width: 80px !important;
    }

    .receipt-footer {
        margin-top: 20px !important;
        padding-top: 6px !important;
        font-size: 8px !important;
        line-height: 1.3 !important;
    }

    /* Target all table elements explicitly to bypass table-specific style overrides from global CSS */
    #transfer-receipt-modal-wrapper table,
    #transfer-receipt-modal-wrapper table *,
    #transfer-receipt-modal-wrapper table thead,
    #transfer-receipt-modal-wrapper table tbody,
    #transfer-receipt-modal-wrapper table tr,
    #transfer-receipt-modal-wrapper table th,
    #transfer-receipt-modal-wrapper table td,
    html.dark #transfer-receipt-modal-wrapper table,
    html.dark #transfer-receipt-modal-wrapper table *,
    html.dark #transfer-receipt-modal-wrapper table thead,
    html.dark #transfer-receipt-modal-wrapper table tbody,
    html.dark #transfer-receipt-modal-wrapper table tr,
    html.dark #transfer-receipt-modal-wrapper table th,
    html.dark #transfer-receipt-modal-wrapper table td {
        background: white !important;
        background-color: white !important;
        color: black !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    #transfer-receipt-modal-wrapper table th,
    #transfer-receipt-modal-wrapper table thead,
    html.dark #transfer-receipt-modal-wrapper table th,
    html.dark #transfer-receipt-modal-wrapper table thead {
        border-bottom: 1.5px solid black !important;
        border-top: none !important;
    }

    ::-webkit-scrollbar {
        display: none;
    }
}
</style>
