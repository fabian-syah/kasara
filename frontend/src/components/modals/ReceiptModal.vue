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
                    <h3 class="text-lg font-bold text-text-primary">
                        {{ transaction?.category === 'angkat_barang' ? 'Nota Angkat Barang' : (transaction?.category ===
                            'refund' ? 'Nota Refund' : 'Nota Penjualan') }}
                    </h3>
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
                        <div class="grid grid-cols-[80px_1fr_60px] gap-2 mb-4 pb-4 border-b border-black">
                            <!-- Logo -->
                            <div class="w-16 h-16 bg-white overflow-hidden self-center">
                                <img src="/images/logo-pstore.png" alt="PSTORE" class="w-full h-full object-contain" />
                            </div>

                            <!-- Header Info (Center) -->
                            <div class="text-center self-center px-2">
                                <h1 class="text-lg font-black text-black uppercase leading-tight">
                                    {{ (transaction.branch_name || transaction.branch?.name ||
                                        authStore.userBranch?.name || '').toUpperCase().includes('PSTORE')
                                        ? (transaction.branch_name || transaction.branch?.name ||
                                            authStore.userBranch?.name)
                                        : 'PSTORE ' + (transaction.branch_name || transaction.branch?.name ||
                                            authStore.userBranch?.name || '') }}
                                </h1>
                                <p class="text-[8px] font-bold leading-tight text-black mt-1">
                                    {{ transaction.branch?.address || authStore.userBranch?.address
                                        || 'Pusat Perbelanjaan Online' }}
                                </p>
                                <p class="text-[8px] font-bold text-black">
                                    {{ displayPhone }}
                                </p>
                            </div>

                            <!-- Social Placeholder -->
                            <div class="flex flex-col justify-center items-end opacity-0">
                            </div>
                        </div>

                        <!-- ===== INFO NOTA ===== -->
                        <div class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-xs mb-4">
                            <span class="font-semibold text-black">No. Nota</span>
                            <span class="text-black">: {{ transaction.order_no || '-' }}</span>
                            <span class="font-semibold text-black">Atas Nama</span>
                            <span class="text-black font-bold">: {{ transaction.customer_name || 'Umum' }}</span>
                            <span class="font-semibold text-black">Sales</span>
                            <span class="text-black">: {{ transaction.inventory_user_name ||
                                transaction.inventory_account_name ||
                                transaction.sales_account || transaction.sales_name || '-' }}</span>
                            <span class="font-semibold text-black">Tanggal</span>
                            <span class="text-black">: {{ displayDate }}</span>
                            <span class="font-semibold text-black">No. HP</span>
                            <span class="text-black">: {{ displayCustomerPhone }}</span>
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
                                <template v-if="allReceiptItems.length > 0">
                                    <tr v-for="(item, index) in allReceiptItems" :key="index"
                                        class="border-b border-gray-300">
                                        <td class="py-2 px-1 text-black align-top text-center font-bold">{{ item.qty }}
                                        </td>
                                        <td class="py-2 px-1 text-black align-top font-mono text-[9px] break-all">
                                            {{ item.imei && item.imei !== '-' ? item.imei : (item.is_hp ? '-' : 'ACCESSORY') }}
                                        </td>
                                        <td class="py-2 px-1 text-black align-top">
                                            <div class="font-black uppercase text-black">{{ item.name }}</div>
                                            <div v-if="item.ram || item.storage"
                                                class="text-[10px] text-black font-medium">
                                                {{ [item.ram, item.storage].filter(Boolean).join('/') }}
                                            </div>
                                            <div v-if="item.condition" class="text-[9px] text-black font-bold italic">
                                                Condition: {{ item.condition === 'new' ? 'Baru' : (item.condition ===
                                                    'ex_ibox' ? 'Ex iBox' : 'Second') }}
                                            </div>
                                        </td>
                                        <td class="py-2 px-1 text-black align-top text-right font-bold w-[120px]">
                                            <div v-if="(item.discount || item.item_discount) > 0"
                                                class="text-[8px] text-gray-500 line-through opacity-70">
                                                {{ formatNumber(item.qty * (item.price || item.selling_price)) }}
                                            </div>
                                            <div class="flex flex-col">
                                                <span>{{ formatNumber(item.qty * ((item.price || item.selling_price || 0) -
                                                    (item.discount || item.item_discount || 0))) }}</span>
                                                <span v-if="(item.discount || item.item_discount) > 0"
                                                    class="text-[7px] text-primary-600 bg-primary-50 px-1 rounded inline-block self-end mt-0.5">
                                                    Disc: -{{ formatNumber(item.qty * (item.discount ||
                                                        item.item_discount)) }}
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                                <!-- Empty rows for physical nota feel -->
                                <tr v-for="n in Math.max(0, 3 - (allReceiptItems.length || 0))" :key="'empty-' + n"
                                    class="border-b border-gray-300">
                                    <td class="py-3 px-1">&nbsp;</td>
                                    <td class="py-3 px-1"></td>
                                    <td class="py-3 px-1"></td>
                                    <td class="py-3 px-1"></td>
                                </tr>
                                <!-- No summary rows inside table to keep it clean -->
                            </tbody>
                        </table>

                        <!-- ===== PAYMENT SECTION ===== -->
                        <div class="flex justify-end mb-4 payment-section">
                            <div class="w-[240px] text-xs space-y-1">
                                <!-- Subtotal before all discounts -->
                                <!-- Summary Section -->
                                <div class="flex justify-between border-b border-gray-300 pb-1">
                                    <span class="font-bold text-black text-[10px]">SUB TOTAL :</span>
                                    <span class="text-black">
                                        {{ formatCurrency(transaction.original_price ||
                                            (Number(transaction.selling_price || 0) +
                                                Number(transaction.total_discount || 0))) }}
                                    </span>
                                </div>

                                <!-- Total Diskon (Gabungan) if any -->
                                <div v-if="transaction.total_discount > 0"
                                    class="flex justify-between border-b border-gray-300 pb-1">
                                    <span class="font-bold text-black text-[10px]">TOTAL DISKON :</span>
                                    <span class="text-primary-700 font-bold">
                                        -{{ formatCurrency(transaction.total_discount) }}
                                    </span>
                                </div>

                                <!-- Payment Breakdown -->
                                <template
                                    v-if="transaction.split_payments_data && transaction.split_payments_data.length > 0">
                                    <div v-for="(payment, idx) in transaction.split_payments_data" :key="idx"
                                        class="flex justify-between border-b border-gray-300 pb-1">
                                        <span class="font-bold text-black text-[10px] uppercase">{{ payment.method_name
                                        }} :</span>
                                        <span class="text-black">
                                            {{ formatCurrency(payment.amount) }}
                                        </span>
                                    </div>
                                </template>
                                <template v-else>
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
                                </template>

                                <!-- Final Total Header -->
                                <div
                                    class="flex justify-between border-t-2 border-black pt-2 pb-1 relative transition-all">
                                    <div class="absolute -top-1 left-0 right-0 h-0.5 bg-black/10 print:hidden"></div>
                                    <span class="font-black text-black text-xs uppercase tracking-tight">HARGA
                                        TOTAL</span>
                                    <span class="font-black text-black text-xs">
                                        {{ formatCurrency(calculatedGrandTotal) }}
                                    </span>
                                </div>

                                <!-- Total Paid / Dibayar -->
                                <div
                                    class="flex justify-between border-t border-gray-400/50 pt-1 text-black font-extrabold flex-row-reverse">
                                    <span>{{ formatCurrency(calculatedTotalPaid) }}</span>
                                    <span class="text-[10px] uppercase">DIBAYAR :</span>
                                </div>

                                <!-- Change / Kembalian -->
                                <div v-if="calculatedChange > 0"
                                    class="flex justify-between border-t border-gray-400 pt-1.5 mt-1 text-primary-600 font-black flex-row-reverse bg-primary-50 px-1 rounded animate-pulse-once print:bg-white print:border-black print:text-black">
                                    <span class="text-sm">{{ formatCurrency(calculatedChange) }}</span>
                                    <span class="text-[11px] uppercase self-center">KEMBALIAN :</span>
                                </div>

                                <div class="text-[9px] text-right text-gray-500 italic mt-1">
                                    Metode: {{ transaction.split_payments_data?.length > 1 ? 'SPLIT (CAMPURAN)' : (transaction.payment_method_name || transaction.payment_method || '-') }}
                                </div>
                            </div>
                        </div>

                        <!-- ===== TRANSACTION NOTES ===== -->
                        <div v-if="transaction.notes" class="mb-4 text-[10px] text-black italic">
                            <span class="font-bold">Catatan:</span> {{ transaction.notes }}
                        </div>

                        <!-- ===== GARANSI NOTES ===== -->
                        <div
                            class="bg-gray-100/80 border border-black/20 rounded p-2.5 mb-5 print:bg-white print:border-black">
                            <div v-if="transaction.branch?.warranty_terms || authStore.userBranch?.warranty_terms"
                                class="text-[9px] text-black font-bold whitespace-pre-line leading-relaxed">
                                {{ transaction.branch?.warranty_terms || authStore.userBranch?.warranty_terms }}
                            </div>
                            <ul v-else class="text-[10px] text-black font-bold space-y-0.5 list-disc pl-3">
                                <li class="font-black underline italic">Garansi 1 Bulan (Nota Dan Segel Jangan Hilang)
                                </li>
                                <li class="font-black underline italic">Barang yang Sudah Dibeli Tidak Dapat
                                    Dikembalikan/Ditukarkan</li>
                                <li class="font-black underline italic">Tidak ada garansi IMEI afr, jatuh, gagal upgrade
                                    dan LCD</li>
                            </ul>
                        </div>

                        <!-- ===== SIGNATURE AREA ===== -->
                        <div class="flex justify-between text-xs mt-6 mb-2 signature-area">
                            <div class="text-center">
                                <p class="font-semibold text-black mb-12">Pembeli,</p>
                                <div class="border-b border-gray-400 w-32 text-center text-[10px] font-bold">
                                    {{ transaction.customer_name || 'Umum' }}
                                </div>
                            </div>
                            <div class="text-center">
                                <p class="font-semibold text-black mb-12">Hormat Kami,</p>
                                <div class="border-b border-gray-400 w-32 text-center text-[10px] font-bold">
                                    {{ transaction.inventory_user_name || transaction.inventory_account_name ||
                                        transaction.sales_account || transaction.sales_name || 'PSTORE' }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- LOADING OVERLAY -->
                <div v-if="isGeneratingPDF"
                    class="absolute inset-0 z-[100] flex flex-col items-center justify-center bg-white/90 dark:bg-surface-800/90 backdrop-blur-sm transition-all duration-300">
                    <div class="relative">
                        <Loader2 class="w-12 h-12 text-primary-500 animate-spin" />
                        <div class="absolute inset-0 animate-ping bg-primary-500/20 rounded-full"></div>
                    </div>
                    <h4 class="mt-4 text-lg font-bold text-text-primary">Menyiapkan Nota PDF</h4>
                    <p class="mt-1 text-sm text-text-secondary">Mohon tunggu sebentar, sedang mengupload ke Google
                        Drive...</p>
                </div>

                <!-- Footer / Actions (hide on print) -->
                <div class="p-4 bg-white border-t border-gray-100 flex gap-3 print:hidden shrink-0">
                    <button @click="close"
                        class="flex-1 px-4 py-4 text-base font-black text-white bg-primary-600 rounded-[1.5rem] hover:bg-primary-700 transition-all flex items-center justify-center gap-2 shadow-xl shadow-primary-500/30 active:scale-95 uppercase tracking-widest">
                        Selesai & Keluar
                    </button>
                    <button @click="printReceipt"
                        class="px-4 py-3 text-sm font-bold text-white bg-gray-900 rounded-2xl hover:bg-gray-800 transition-colors flex items-center justify-center gap-2 shadow-lg active:scale-95">
                        <Printer :size="18" />
                        Cetak
                    </button>
                    <!-- <button @click="shareToWhatsApp"
                        class="flex-1 px-4 py-3 text-sm font-bold text-white bg-emerald-600 rounded-2xl hover:bg-emerald-700 transition-colors flex items-center justify-center gap-2 shadow-lg active:scale-95">
                        <MessageSquare :size="18" />
                        Kirim WA
                    </button> -->
                </div>
            </div>
        </div>
    </transition>
</template>

<script setup>
import { defineProps, defineEmits, ref, computed } from 'vue';
import { Printer, Pencil, X, MessageSquare, Loader2 } from 'lucide-vue-next';
import { useEscapeKey } from '../../composables/useEscapeKey';
import api from '../../api/axios';

import { useAuthStore } from '../../store/auth';
const authStore = useAuthStore();

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
/* watch(() => props.isOpen, (newVal) => {
    if (newVal && props.autoSend) {
        // Short delay to ensure DOM is ready
        setTimeout(() => {
            shareToWhatsApp(true); // isAuto = true
        }, 500);
    }
}, { immediate: true }); */

const printReceipt = () => {
    window.print();
};

const shareToWhatsApp = async (isAuto = false) => {
    if (isGeneratingPDF.value) return;

    try {
        isGeneratingPDF.value = true;

        // Call backend to handle EVERYTHING (PDF -> GDrive -> WA Link)
        // Increased timeout to 90 seconds since GDrive PDF generation is heavy
        const response = await api.get(`/receipts/${props.transaction.id}/share-wa`, {
            timeout: 90000
        });
        const result = response.data;

        if (result.success && result.wa_url) {
            window.open(result.wa_url, '_blank');
            emit('sent');
        } else {
            throw new Error(result.error || 'Gagal membuat link sharing');
        }

    } catch (error) {
        console.error('WhatsApp sharing failed:', error);
        // Only alert if NOT automatic, to avoid intrusive popups right after a successful sale
        if (!isAuto) {
            alert('Gagal memproses pengiriman WhatsApp: ' + error.message);
        } else {
            console.warn('Auto-send WA failed, user can still click manually.');
        }
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

// Robust calculations for totals and change
const calculatedTotalPaid = computed(() => {
    // 1. Try direct fields
    const directPaid = Number(props.transaction.paid || props.transaction.paid_amount || 0);
    if (directPaid > 0) return directPaid;

    // 2. Sum from split_payments_data if direct is 0
    if (props.transaction.split_payments_data?.length > 0) {
        return props.transaction.split_payments_data.reduce((sum, p) => sum + Number(p.amount || 0), 0);
    }

    // 3. Fallback to cash/transfer sum
    return Number(props.transaction.cash || 0) + Number(props.transaction.transfer || 0);
});

const calculatedGrandTotal = computed(() => {
    return Number(props.transaction.total || props.transaction.grand_total || props.transaction.selling_price || 0);
});

const calculatedChange = computed(() => {
    return Math.max(0, calculatedTotalPaid.value - calculatedGrandTotal.value);
});

// Perbaikan untuk menampilkan nomor WhatsApp dengan benar
const displayPhone = computed(() => {
    const phone = props.transaction.branch?.phone || authStore.userBranch?.phone;
    if (phone && phone.trim() !== '') {
        return `WhatsApp: ${phone}`;
    }
    return 'HP, Laptop, Barang Elektronik Bergaransi';
});

// Perbaikan untuk Customer Phone
const displayCustomerPhone = computed(() => {
    const p = props.transaction.customer_phone || props.transaction.customer_wa || props.transaction.shopee_phone || props.transaction.event_phone;
    if (p && p.trim() !== '') {
        return p;
    }
    return '-';
});

// Perbaikan untuk Tanggal
const displayDate = computed(() => {
    let rawDate = props.transaction.date || props.transaction.created_at;
    if (!rawDate) return '-';
    
    // Check if it's already a formatted frontend date like "08 Apr 2026"
    if (typeof rawDate === 'string' && /^[0-9]{2} [A-Za-z]{3,4} [0-9]{4}$/.test(rawDate)) {
        return rawDate;
    }
    
    // Parse backend timestamps / date strings
    const dateObj = new Date(rawDate);
    if (!isNaN(dateObj.getTime())) {
        return dateObj.toLocaleString("id-ID", {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }).replace(/\./g, ':'); // Ensure HH:mm format vs dots
    }
    return rawDate;
});

// Consolidate all items for display
const allReceiptItems = computed(() => {
    const list = [];
    
    // 1. Add HP Items
    if (props.transaction.items?.length > 0) {
        props.transaction.items.forEach(it => {
            list.push({
                ...it,
                qty: it.qty || it.quantity || 1,
                is_hp: true
            });
        });
    }
    
    // 2. Add Non-HP Items if they're not already in the main items list
    // (Non-HP items are often stored in non_hp_items or nonHpItems from backend)
    const nonHpSource = props.transaction.non_hp_items || props.transaction.non_hp_details || props.transaction.nonHpItems;
    
    if (nonHpSource?.length > 0) {
        // If it's a bundle, the first item in 'items' might already represent the bundle
        // so we check to avoid double-listing for those specific cases
        const hasBundleRepresented = list.some(it => it.is_bundle);
        
        if (!hasBundleRepresented) {
            nonHpSource.forEach(it => {
                list.push({
                    ...it,
                    qty: it.quantity || it.qty || 1,
                    name: it.product_name || it.product?.name || it.name,
                    price: it.selling_price || it.price,
                    is_hp: false
                });
            });
        }
    }
    
    return list;
});
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
    color: #000000 !important;
    font-weight: 600 !important;
    opacity: 1 !important;
    text-rendering: optimizeLegibility;
    -webkit-font-smoothing: antialiased;
}

.nota-paper h2 {
    font-weight: 900 !important;
}

.nota-paper {
    background-color: #ffffff !important;
    box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1) !important;
}

.nota-paper .text-gray-700,
.nota-paper .text-gray-600,
.nota-paper .text-gray-500 {
    color: #000000 !important;
    opacity: 1 !important;
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

    /* Fix logo gepeng in print/capture */
    .nota-paper img {
        height: auto !important;
        max-height: none !important;
        object-fit: contain !important;
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