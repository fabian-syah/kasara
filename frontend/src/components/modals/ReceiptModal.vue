<template>
    <transition name="fade">
        <div v-if="isOpen" id="receipt-modal-print-wrapper"
            class="fixed inset-0 z-[99999] flex items-start justify-center pt-24 sm:pt-0 sm:items-center p-4 bg-black/60 backdrop-blur-sm print:p-0 print:bg-white"
            @click.self="close">
            <div
                class="bg-white dark:bg-surface-800 rounded-3xl w-full max-w-lg overflow-hidden shadow-2xl print:shadow-none print:rounded-none print:max-w-full flex flex-col max-h-[90vh] sm:max-h-[85vh]">

                <!-- Modal Header (hide on print) -->
                <div
                    class="p-6 flex justify-between items-center border-b border-gray-100 dark:border-surface-700 print:hidden shrink-0">
                    <h3 class="text-lg font-bold text-text-primary">Nota Penjualan</h3>
                    <div class="flex items-center gap-2">
                        <button v-if="showEditIcon" @click="$emit('open-checklist')"
                            class="p-2 bg-primary-500/10 hover:bg-primary-500/20 text-primary-600 rounded-xl transition-all"
                            title="Cek Audit">
                            <Pencil :size="18" />
                        </button>
                        <button @click="close"
                            class="p-2 hover:bg-gray-100 dark:hover:bg-surface-700 rounded-xl transition-colors">
                            <X :size="20" class="text-gray-500" />
                        </button>
                    </div>
                </div>

                <!-- Nota Content -->
                <div id="receipt-content"
                    class="flex-1 overflow-y-auto p-6 print:p-0 bg-gray-100/50 dark:bg-surface-900/50 print:bg-white">
                    <div v-if="transaction"
                        class="nota-paper max-w-[480px] mx-auto bg-white p-6 text-black font-sans text-sm shadow-xl print:shadow-none print:max-w-full print:mx-0 print:p-4 border border-gray-200 print:border-none">

                        <!-- ===== NOTA HEADER ===== -->
                        <div class="flex items-start gap-4 mb-4 pb-4">
                            <img src="/images/logo-pstore.png" alt="PSTORE" class="w-14 h-14 object-contain shrink-0" />
                            <div class="flex-1 min-w-0">
                                <h2 class="text-2xl font-extrabold tracking-wider text-black leading-none">PSTORE</h2>
                                <p class="text-[9px] leading-tight text-gray-700 mt-1">
                                    Pusat Perbelanjaan Online<br />
                                    HP, Laptop, Barang Elektronik Bergaransi Terjamin Dan Terpercaya
                                </p>
                                <p class="text-[9px] text-gray-600 mt-0.5">
                                    No Customer Service 0851 - 3300 - 5600
                                </p>
                                <div class="mt-2 text-[9px] text-gray-700">
                                    <span class="font-bold">Kami ada juga di :</span>
                                    <div class="flex items-center gap-4 mt-0.5">
                                        <span class="flex items-center gap-1">
                                            <img src="/images/shopee-icon-small.png" class="w-2.5 h-2.5 object-contain"
                                                alt="" />
                                            pstore_
                                        </span>
                                        <span class="flex items-center gap-1">
                                            <img src="/images/tokopedia-icon-small.png"
                                                class="w-2.5 h-2.5 object-contain" alt="" />
                                            pstore_
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ===== INFO NOTA ===== -->
                        <div class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-xs mb-4">
                            <span class="font-semibold text-black">No. Nota</span>
                            <span class="text-black">: {{ transaction.order_no || '-' }}</span>
                            <span class="font-semibold text-black">Atas Nama</span>
                            <span class="text-black font-bold">: {{ transaction.customer_name || 'Umum' }}</span>
                            <span class="font-semibold text-black">Tanggal</span>
                            <span class="text-black">: {{ transaction.date || '-' }}</span>
                            <span v-if="transaction.customer_phone && transaction.customer_phone !== '-'"
                                class="font-semibold text-black">No. HP</span>
                            <span v-if="transaction.customer_phone && transaction.customer_phone !== '-'"
                                class="text-black">: {{ transaction.customer_phone }}</span>
                        </div>

                        <!-- ===== TABEL ITEMS ===== -->
                        <table class="w-full text-xs border-collapse mb-4">
                            <thead>
                                <tr class="border-t-2 border-b-2 border-black">
                                    <th class="py-2 px-1 text-left font-bold text-black w-[50px]">Banyak</th>
                                    <th class="py-2 px-1 text-left font-bold text-black">IMEI</th>
                                    <th class="py-2 px-1 text-left font-bold text-black">Keterangan</th>
                                    <th class="py-2 px-1 text-right font-bold text-black w-[100px]">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-if="transaction.items && transaction.items.length > 0">
                                    <tr v-for="(item, index) in transaction.items" :key="index"
                                        class="border-b border-gray-300">
                                        <td class="py-2 px-1 text-black align-top text-center font-bold">{{ item.qty }}
                                        </td>
                                        <td class="py-2 px-1 text-black align-top font-mono text-[9px] break-all">
                                            {{ item.imei && item.imei !== '-' ? item.imei : '-' }}
                                        </td>
                                        <td class="py-2 px-1 text-black align-top">
                                            <div class="font-bold uppercase">{{ item.name }}</div>
                                            <div v-if="item.storage" class="text-[9px] text-gray-700">{{ item.storage }}
                                            </div>
                                            <div v-if="item.condition" class="text-[9px] text-gray-700 italic">
                                                Condition: {{ item.condition === 'new' ? 'Baru' : (item.condition ===
                                                    'ex_ibox' ? 'Ex iBox' : 'Second') }}
                                            </div>
                                        </td>
                                        <td class="py-2 px-1 text-black align-top text-right font-bold">
                                            {{ formatNumber(item.qty * item.price) }}
                                        </td>
                                    </tr>
                                </template>
                                <!-- Empty rows for physical nota feel -->
                                <tr v-for="n in Math.max(0, 3 - (transaction.items?.length || 0))" :key="'empty-' + n"
                                    class="border-b border-gray-300">
                                    <td class="py-3 px-1">&nbsp;</td>
                                    <td class="py-3 px-1"></td>
                                    <td class="py-3 px-1"></td>
                                    <td class="py-3 px-1"></td>
                                </tr>chr
                                <!-- No summary rows inside table to keep it clean -->
                            </tbody>
                        </table>

                        <!-- ===== PAYMENT SECTION ===== -->
                        <div class="flex justify-end mb-4 payment-section">
                            <div class="w-[240px] text-xs space-y-1">
                                <!-- Subtotal before all discounts -->
                                <div class="flex justify-between border-b border-gray-300 pb-1">
                                    <span class="font-bold text-black">SUB TOTAL :</span>
                                    <span class="text-black">
                                        {{ formatCurrency(transaction.original_price || 0) }}
                                    </span>
                                </div>

                                <!-- Dynamic Discount Row (ONLY Global Discount) -->
                                <div v-if="transaction.global_discount_value > 0"
                                    class="flex justify-between border-b border-gray-300 pb-1 text-black font-bold italic">
                                    <span>DISKON :</span>
                                    <span>
                                        -{{ formatCurrency(transaction.global_discount_type === 'percentage'
                                            ? (transaction.original_price * transaction.global_discount_value / 100)
                                            : transaction.global_discount_value) }}
                                    </span>
                                </div>

                                <!-- Payment Breakdown -->
                                <div v-if="transaction.cash > 0"
                                    class="flex justify-between border-b border-gray-300 pb-1">
                                    <span class="font-bold text-black text-[10px]">CASH :</span>
                                    <span class="text-black">
                                        {{ formatCurrency(transaction.cash) }}
                                    </span>
                                </div>
                                <div v-if="transaction.transfer > 0"
                                    class="flex justify-between border-b border-gray-300 pb-1">
                                    <span class="font-bold text-black text-[10px]">TF :</span>
                                    <span class="text-black">
                                        {{ formatCurrency(transaction.transfer) }}
                                    </span>
                                </div>

                                <!-- Final Total -->
                                <div class="flex justify-between border-t-2 border-black pt-1">
                                    <span class="font-extrabold text-black text-sm">TOTAL :</span>
                                    <span class="font-extrabold text-black text-sm">
                                        {{ formatCurrency(transaction.grand_total || 0) }}
                                    </span>
                                </div>
                                <div class="text-[9px] text-right text-gray-500 italic mt-1">
                                    Metode: {{ transaction.payment_method || '-' }}
                                </div>
                            </div>
                        </div>

                        <!-- ===== GARANSI NOTES ===== -->
                        <div class="bg-gray-50/50 border border-gray-300 rounded p-2.5 mb-5 print:bg-white">
                            <ul class="text-[9px] text-gray-700 space-y-0.5 list-disc pl-3">
                                <li class="font-bold">Garansi 1 Bulan (Nota Dan Segel Jangan Hilang)</li>
                                <li>Barang yang Sudah Dibeli Tidak Dapat Dikembalikan/Ditukarkan</li>
                                <li>Tidak ada garansi IMEI afr, jatuh, gagal upgrade dan LCD</li>
                            </ul>
                        </div>

                        <!-- ===== SIGNATURE AREA ===== -->
                        <div class="flex justify-between text-xs mt-6 mb-2 signature-area">
                            <div class="text-center">
                                <p class="font-semibold text-black mb-12">Penerima,</p>
                                <div class="border-b border-gray-400 w-28"></div>
                            </div>
                            <div class="text-center">
                                <p class="font-semibold text-black mb-12">Hormat Kami,</p>
                                <div class="border-b border-gray-400 w-28 text-center text-[10px] font-bold">PSTORE
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer / Actions (hide on print) -->
                <div class="p-4 bg-white border-t border-gray-100 flex gap-3 print:hidden shrink-0">
                    <button @click="close"
                        class="flex-1 px-4 py-3 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-2xl hover:bg-gray-50 transition-colors">
                        Tutup
                    </button>
                    <button @click="printReceipt"
                        class="px-4 py-3 text-sm font-bold text-white bg-gray-900 rounded-2xl hover:bg-gray-800 transition-colors flex items-center justify-center gap-2 shadow-lg active:scale-95">
                        <Printer :size="18" />
                        Cetak
                    </button>
                    <button @click="shareToWhatsApp"
                        class="flex-1 px-4 py-3 text-sm font-bold text-white bg-emerald-600 rounded-2xl hover:bg-emerald-700 transition-colors flex items-center justify-center gap-2 shadow-lg active:scale-95">
                        <MessageSquare :size="18" />
                        Kirim WA
                    </button>
                </div>
            </div>
        </div>
    </transition>
</template>

<script setup>
import { defineProps, defineEmits, ref } from 'vue';
import { Printer, Pencil, X, MessageSquare, Loader2 } from 'lucide-vue-next';
import { useEscapeKey } from '../../composables/useEscapeKey';

const props = defineProps({
    isOpen: Boolean,
    transaction: Object,
    showEditIcon: {
        type: Boolean,
        default: false
    },
    autoSend: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['close', 'open-checklist', 'sent']);

const close = () => {
    emit('close');
};

const isGeneratingPDF = ref(false);

// Auto-send if prop is true and modal opens
import { watch } from 'vue';
watch(() => props.isOpen, (newVal) => {
    if (newVal && props.autoSend) {
        // Short delay to ensure DOM is ready
        setTimeout(() => {
            shareToWhatsApp();
        }, 500);
    }
});

const printReceipt = () => {
    window.print();
};

const shareToWhatsApp = async () => {
    try {
        const phone = props.transaction.customer_phone || props.transaction.customer_wa || props.transaction.shopee_phone;
        const receiptId = props.transaction.receipt_id || props.transaction.order_no || props.transaction.id;
        const publicUrl = `https://api.stokps.com/n/${receiptId}`;

        const message = `Halo Kak ${props.transaction.customer_name || ''},\n\nTerima kasih telah berbelanja di PSTORE. Berikut adalah nota digital untuk pesanan Anda:\n\n*Lihat Nota Online:* ${publicUrl}\n\n*Nota ini dapat Anda simpan atau cetak langsung dari browser Anda.*`;

        if (phone && phone !== '-') {
            // Clean phone number
            let cleanPhone = phone.replace(/\D/g, '');
            if (cleanPhone.startsWith('0')) {
                cleanPhone = '62' + cleanPhone.substring(1);
            } else if (!cleanPhone.startsWith('62')) {
                cleanPhone = '62' + cleanPhone;
            }

            // Try Web Share API (Mobile)
            if (navigator.share) {
                try {
                    await navigator.share({
                        title: 'Nota PSTORE',
                        text: message,
                        url: publicUrl
                    });
                    return;
                } catch (e) {
                    console.log('navigator.share failed, falling back to wa.me');
                }
            }

            const waUrl = `https://wa.me/${cleanPhone}?text=${encodeURIComponent(message)}`;
            window.open(waUrl, '_blank');
        } else {
            alert('Nomor WhatsApp pelanggan tidak ditemukan. PDF berhasil diunduh.');
        }

    } catch (error) {
        console.error('WhatsApp sharing failed:', error);
        alert('Gagal memproses pengiriman WhatsApp.');
    } finally {
        isGeneratingPDF.value = false;
    }
};

useEscapeKey(() => {
    if (props.isOpen) close();
});

const formatCurrency = (value) => {
    if (value === null || value === undefined || isNaN(value)) return 'Rp 0';
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(value);
};

const formatNumber = (value) => {
    if (value === null || value === undefined || isNaN(value)) return '0';
    return new Intl.NumberFormat('id-ID').format(value);
};
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

/* PSTORE Receipt Paper Styles */
.nota-paper,
.nota-paper * {
    color: #000 !important;
}

.nota-paper {
    background-color: #fff !important;
}

.nota-paper .text-gray-700,
.nota-paper .text-gray-600,
.nota-paper .text-gray-500 {
    color: #374151 !important;
}

.nota-paper .text-primary-700 {
    color: #1d4ed8 !important;
    /* Blue for discount all */
}

.nota-paper .text-amber-700 {
    color: #b45309 !important;
    /* Amber for item discount */
}

/* Force standard colors for PDF capture to avoid html2canvas oklab error */
.pdf-capture-mode,
.pdf-capture-mode * {
    background-color: #ffffff !important;
    border-color: #e5e7eb !important;
    /* Standard gray-200 hex */
    color: #000000 !important;
    box-shadow: none !important;
}

.pdf-capture-mode .bg-gray-50\/50 {
    background-color: #f9fafb !important;
}

.pdf-capture-mode .border-gray-300 {
    border-color: #d1d5db !important;
}

.pdf-capture-mode .border-gray-400 {
    border-color: #9ca3af !important;
}

.pdf-capture-mode .text-gray-700 {
    color: #374151 !important;
}

.pdf-capture-mode .text-gray-600 {
    color: #4b5563 !important;
}

.pdf-capture-mode .text-gray-500 {
    color: #6b7280 !important;
}
</style>

<style>
@media print {
    @page {
        margin: 0;
        size: auto;
    }

    /* Hide everything by default */
    html,
    body,
    #app,
    #app>* {
        visibility: hidden !important;
        margin: 0 !important;
        padding: 0 !important;
        height: auto !important;
    }

    /* Only show the receipt wrapper and its children */
    #receipt-modal-print-wrapper,
    #receipt-modal-print-wrapper * {
        visibility: visible !important;
    }

    #receipt-modal-print-wrapper {
        display: block !important;
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
        background: white !important;
        z-index: 9999999 !important;
        padding: 10mm !important;
    }

    #receipt-modal-print-wrapper>div {
        display: block !important;
    }

    .nota-paper {
        border: none !important;
        box-shadow: none !important;
        width: 100% !important;
        max-width: none !important;
        padding: 0 !important;
        margin: 0 !important;
        zoom: 1.1;
        color: black !important;
        background: white !important;
    }

    .nota-paper tr,
    .nota-paper .payment-section,
    .nota-paper .signature-area {
        break-inside: avoid;
    }

    /* Target UI elements inside the wrapper to hide */
    .print\:hidden {
        display: none !important;
    }

    /* Ensure images show */
    img {
        display: block !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
</style>
