<template>
    <transition name="fade">
        <div v-if="isOpen" id="receipt-modal-print-wrapper"
            class="fixed inset-0 z-[99999] flex items-start justify-center pt-24 sm:pt-0 sm:items-center p-4 bg-black/60 backdrop-blur-sm print:p-0 print:bg-white"
            @click.self="close">
            <div
                class="bg-white dark:bg-surface-800 rounded-3xl w-full max-w-lg overflow-hidden shadow-2xl print:shadow-none print:rounded-none print:max-w-full flex flex-col max-h-[90vh] sm:max-h-[85vh]">

                <div
                    class="p-6 flex justify-between items-center border-b border-gray-100 dark:border-surface-700 print:hidden shrink-0">
                    <h3 class="text-lg font-bold text-text-primary">
                        {{ transaction?.category === 'angkat_barang' ? 'Nota Angkat Barang' : (transaction?.category ===
                            'refund' ? 'Nota Refund' : 'Nota Penjualan') }}
                    </h3>
                    <div class="flex items-center gap-2">
                        <button v-if="showEditIcon" @click="$emit('open-checklist')"
                            class="p-2 bg-primary-500/10 hover:bg-primary-500/20 text-primary-600 rounded-xl transition-all">
                            <Pencil :size="18" />
                        </button>
                        <button @click="close"
                            class="p-2 hover:bg-gray-100 dark:hover:bg-surface-700 rounded-xl transition-colors">
                            <X :size="20" class="text-gray-500" />
                        </button>
                    </div>
                </div>

                <div id="receipt-content"
                    class="flex-1 overflow-y-auto p-6 print:p-0 bg-gray-100/50 dark:bg-surface-900/50 print:bg-white">
                    <div v-if="transaction"
                        class="nota-paper max-w-[480px] mx-auto bg-white p-6 text-black font-sans text-sm shadow-xl print:shadow-none print:max-w-full print:mx-0 print:p-4 border border-gray-200 print:border-none">

                        <div class="grid grid-cols-[80px_1fr_60px] gap-2 mb-4 pb-4 border-b border-black">
                            <div class="w-16 h-16 bg-white overflow-hidden self-center">
                                <img src="/images/logo-pstore.png" alt="PSTORE" class="w-full h-full object-contain" />
                            </div>
                            <div class="text-center self-center px-2">
                                <h1 class="text-lg font-black text-black uppercase leading-tight">
                                    {{ (transaction.branch_name || transaction.branch?.name ||
                                        authStore.userBranch?.name || '').toUpperCase().includes('PSTORE') ?
                                        (transaction.branch_name || transaction.branch?.name || authStore.userBranch?.name)
                                    : 'PSTORE ' + (transaction.branch_name || transaction.branch?.name ||
                                    authStore.userBranch?.name || '') }}
                                </h1>
                                <p class="text-[8px] font-bold text-black">{{ transaction.branch?.address ||
                                    authStore.userBranch?.address }}</p>
                            </div>
                            <div class="opacity-0"></div>
                        </div>

                        <div class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-xs mb-4">
                            <span class="font-semibold text-black">No. Nota</span>
                            <span class="text-black">: {{ transaction.order_no || '-' }}</span>
                            <span class="font-semibold text-black">Atas Nama</span>
                            <span class="text-black font-bold">: {{ transaction.customer_name || 'Umum' }}</span>
                            <span class="font-semibold text-black">Tanggal</span>
                            <span class="text-black">: {{ transaction.date || '-' }}</span>
                            <span class="font-semibold text-black">No. HP</span>
                            <span class="text-black">: {{ transaction.customer_phone || transaction.customer_wa || '-'
                                }}</span>
                        </div>

                        <table class="w-full text-xs border-collapse mb-4">
                            <thead>
                                <tr class="border-t-2 border-b-2 border-black">
                                    <th class="py-2 text-left font-bold w-[50px]">Qty</th>
                                    <th class="py-2 text-left font-bold">Keterangan</th>
                                    <th class="py-2 text-right font-bold w-[100px]">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in transaction.items" :key="index"
                                    class="border-b border-gray-300">
                                    <td class="py-2 font-bold">{{ item.qty }}</td>
                                    <td class="py-2">
                                        <div class="font-black uppercase">{{ item.name }}</div>
                                        <div class="text-[9px] font-mono">{{ item.imei || '-' }}</div>
                                    </td>
                                    <td class="py-2 text-right font-bold">{{ formatNumber(item.qty * (item.price ||
                                        item.selling_price)) }}</td>
                                </tr>
                            </tbody>
                        </table>

                        <div class="flex justify-end space-y-1">
                            <div class="w-[200px] text-xs">
                                <div class="flex justify-between font-bold">
                                    <span>TOTAL:</span>
                                    <span>{{ formatCurrency(calculatedGrandTotal) }}</span>
                                </div>
                                <div class="flex justify-between border-t border-black mt-1">
                                    <span>DIBAYAR:</span>
                                    <span>{{ formatCurrency(calculatedTotalPaid) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-4 bg-white border-t flex gap-3 print:hidden shrink-0">
                    <button @click="close"
                        class="flex-1 py-4 bg-primary-600 text-white font-black rounded-2xl uppercase">Selesai</button>
                    <button @click="printReceipt" class="px-6 py-4 bg-gray-900 text-white rounded-2xl">
                        <Printer />
                    </button>
                </div>
            </div>
        </div>
    </transition>
</template>

<script setup>
import { defineProps, defineEmits, ref, computed, watch } from 'vue';
import { Printer, Pencil, X, Loader2 } from 'lucide-vue-next';
import { useAuthStore } from '../../store/auth';

const authStore = useAuthStore();
const props = defineProps({
    isOpen: Boolean,
    transaction: Object,
    showEditIcon: Boolean
});

const emit = defineEmits(['close', 'open-checklist']);
const close = () => emit('close');
const printReceipt = () => window.print();

const formatCurrency = (val) => {
    if (!val) return 'Rp 0';
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val);
};

const formatNumber = (val) => new Intl.NumberFormat('id-ID').format(val || 0);

const calculatedTotalPaid = computed(() => {
    return Number(props.transaction?.paid || props.transaction?.paid_amount || 0);
});

const calculatedGrandTotal = computed(() => {
    return Number(props.transaction?.total || props.transaction?.grand_total || 0);
});

const calculatedChange = computed(() => Math.max(0, calculatedTotalPaid.value - calculatedGrandTotal.value));
</script>

<style scoped>
/* CSS Kamu di sini */
.nota-paper {
    background: white;
    color: black;
}
</style>

<script setup>
import { defineProps, defineEmits, ref } from 'vue';
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
import { computed } from 'vue';

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
