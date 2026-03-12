<template>
    <transition name="fade">
        <div v-if="isOpen" id="receipt-modal-print-wrapper"
            class="fixed inset-0 z-[99999] flex items-start justify-center pt-24 sm:pt-0 sm:items-center p-4 bg-black/60 backdrop-blur-sm print:p-0 print:bg-white"
            @click.self="close">
            <div
                class="relative bg-white w-full max-w-sm sm:max-w-md rounded-2xl shadow-2xl overflow-hidden print:shadow-none print:w-[80mm] print:mx-auto">

                <!-- Edit/Audit Button (top-right) -->
                <button v-if="showEditIcon" @click="$emit('open-checklist')"
                    class="absolute top-4 right-4 z-10 p-2 bg-primary-500/10 hover:bg-primary-500/20 text-primary-600 rounded-xl transition-all print:hidden"
                    title="Cek Audit">
                    <Pencil :size="16" />
                </button>

                <!-- Receipt Header -->
                <!-- Added print:pt-10 to push content down from header when printing -->
                <div
                    class="p-6 text-center border-b border-dashed border-gray-300 print:px-0 print:pb-0 print:pt-4 print:mb-4">
                    <!-- Modern Logo Implementation -->
                    <div class="mb-4 flex justify-center">
                        <div
                            class="w-12 h-12 bg-gray-900 text-white rounded-xl flex items-center justify-center mx-auto mb-2">
                            <span class="font-bold text-lg">K</span>
                        </div>
                    </div>

                    <h2 class="text-xl font-bold text-gray-900 mb-1">{{ transaction?.outlet_name || 'KASARA' }}</h2>
                    <p class="text-xs text-gray-500 mb-4 print:text-black">
                        {{ transaction?.outlet_address || 'Jl. Raya Example No. 123, Indonesia' }}</p>

                    <div class="flex flex-col gap-1 text-xs text-gray-600 print:text-black text-left">
                        <div class="flex justify-between">
                            <span>No Pesanan:</span>
                            <span class="font-mono font-bold">{{ transaction?.order_no || '-' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Tanggal:</span>
                            <span>{{ transaction?.date || '-' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Pelanggan:</span>
                            <span class="font-medium">{{ transaction?.customer_name || 'Umum' }}</span>
                        </div>
                        <div v-if="transaction?.customer_phone && transaction.customer_phone !== '-'"
                            class="flex justify-between print:text-black">
                            <span>No HP:</span>
                            <span>{{ transaction.customer_phone }}</span>
                        </div>
                    </div>
                </div>

                <!-- Receipt Items -->
                <div class="p-6 bg-gray-50 print:bg-white print:p-0">
                    <div class="space-y-3 print:space-y-2">
                        <!-- Items Loop -->
                        <div v-for="(item, index) in (transaction?.items || [])" :key="index"
                            class="flex justify-between text-sm print:text-black">
                            <div class="flex-1 pr-4">
                                <span class="font-medium text-gray-900 print:text-black block">{{ item.name }}</span>
                                <span v-if="item.imei && item.imei !== '-'"
                                    class="font-mono text-[10px] text-primary-600 print:text-black block">IMEI: {{
                                        item.imei }}</span>
                                <div class="flex flex-col">
                                    <span class="text-xs text-gray-500 print:text-black">
                                        {{ item.qty }} x {{ formatCurrency(item.price) }}
                                    </span>
                                    <span v-if="item.item_discount > 0"
                                        class="text-[10px] text-amber-600 font-bold italic print:text-black">
                                        Disk. Item: -{{ formatCurrency(item.item_discount) }}
                                    </span>
                                    <span v-if="item.distributed_discount > 0"
                                        class="text-[10px] text-primary-600 font-bold italic print:text-black">
                                        Disk. Global: -{{ formatCurrency(item.distributed_discount) }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex flex-col items-end">
                                <span class="font-semibold text-gray-900 print:text-black whitespace-nowrap">
                                    {{ formatCurrency(item.qty * item.price) }}
                                </span>
                                <div v-if="item.item_discount > 0 || item.distributed_discount > 0"
                                    class="flex flex-col items-end leading-tight">
                                    <span v-if="item.item_discount > 0"
                                        class="text-[10px] text-amber-600 font-bold italic print:text-black">
                                        -{{ formatCurrency(item.qty * item.item_discount) }}
                                    </span>
                                    <span v-if="item.distributed_discount > 0"
                                        class="text-[10px] text-primary-600 font-bold italic print:text-black">
                                        -{{ formatCurrency(item.distributed_discount) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Fallback if no detailed items (using grand total) -->
                        <div v-if="(!transaction?.items || transaction.items.length === 0)"
                            class="text-center text-gray-400 text-xs italic py-2">
                            Detail item tidak tersedia
                        </div>
                    </div>

                    <div class="my-4 border-t border-dashed border-gray-300"></div>

                    <!-- Totals -->
                    <div class="space-y-1.5 text-sm">
                        <div class="flex justify-between text-gray-600 print:text-black">
                            <span>Subtotal</span>
                            <span>{{ formatCurrency(transaction?.original_price || (transaction?.grand_total +
                                (transaction?.total_discount || 0)) || 0) }}</span>
                        </div>
                        <div v-if="transaction?.total_discount > 0"
                            class="flex justify-between text-amber-600 font-bold italic print:text-black">
                            <span>Total Diskon</span>
                            <span>-{{ formatCurrency(transaction?.total_discount) }}</span>
                        </div>
                        <div
                            class="flex justify-between font-bold text-gray-900 text-base border-t border-gray-100 pt-1 mt-1 print:text-black print:border-black">
                            <span>Total Bayar</span>
                            <span>{{ formatCurrency(transaction?.grand_total || 0) }}</span>
                        </div>
                        <div class="flex justify-between text-[10px] text-gray-400 mt-2 print:text-black">
                            <span>Metode Pembayaran</span>
                            <span class="uppercase font-bold">{{ transaction?.payment_method || 'Tunai' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Footer / Actions -->
                <div class="p-4 bg-white border-t border-gray-100 flex gap-3 print:hidden">
                    <button @click="close"
                        class="flex-1 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 transition-colors">
                        Tutup
                    </button>
                    <button @click="printReceipt"
                        class="flex-1 px-4 py-2 text-sm font-bold text-white bg-gray-900 rounded-xl hover:bg-gray-800 transition-colors flex items-center justify-center gap-2">
                        <Printer :size="16" />
                        Cetak Nota
                    </button>
                </div>

                <!-- Print Footer -->
                <div class="hidden print:block text-center mt-6 pt-4 border-t border-dashed border-gray-300">
                    <p class="text-[10px] text-gray-500">Terima kasih atas kunjungan Anda!</p>
                    <p class="text-[10px] text-gray-400 mt-1">Powered by KASARA</p>
                </div>
            </div>
        </div>
    </transition>
</template>

<script setup>
import { defineProps, defineEmits, ref } from 'vue';
import { Printer, Pencil } from 'lucide-vue-next';
import { useEscapeKey } from '../../composables/useEscapeKey';

const props = defineProps({
    isOpen: Boolean,
    transaction: Object,
    showEditIcon: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['close', 'open-checklist']);
const imageError = ref(false);

const close = () => {
    emit('close');
};

const printReceipt = () => {
    window.print();
};

useEscapeKey(() => {
    if (props.isOpen) close();
});

const handleImageError = () => {
    imageError.value = true;
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(value);
};
</script>

<style>
/* Unscoped Global Styles for Printing */
@page {
    size: auto;
    margin: 0mm;
}

@media print {

    /* Hide everything by default */
    body * {
        visibility: hidden;
    }

    /* Target the specific modal wrapper by ID */
    #receipt-modal-print-wrapper,
    #receipt-modal-print-wrapper * {
        visibility: visible;
    }

    #receipt-modal-print-wrapper {
        position: fixed !important;
        /* Force fixed to snap to top */
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
        height: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
        z-index: 999999 !important;
        display: flex !important;
        /* Use Flexbox for centering */
        justify-content: center !important;
        align-items: flex-start !important;
        /* Align top, not center vertically for receipt */
    }

    /* Reset some styles for the inner card if needed */
    #receipt-modal-print-wrapper>div {
        max-width: none !important;
        /* Width is controlled by utility class print:w-[80mm] */
        box-shadow: none !important;
        border-radius: 0 !important;
        margin-top: 20px !important;
        /* Small top margin */
    }
}
</style>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

/* Scoped print utilities */
@media print {
    .print\:hidden {
        display: none !important;
    }

    .print\:block {
        display: block !important;
    }

    .print\:text-black {
        color: black !important;
    }

    .print\:shadow-none {
        box-shadow: none !important;
    }

    .print\:p-0 {
        padding: 0 !important;
    }

    .print\:px-0 {
        padding-left: 0 !important;
        padding-right: 0 !important;
    }

    .print\:pb-0 {
        padding-bottom: 0 !important;
    }

    .print\:pt-4 {
        padding-top: 1rem !important;
    }

    .print\:pt-10 {
        padding-top: 2.5rem !important;
    }

    .print\:w-\[80mm\] {
        width: 80mm !important;
    }

    .print\:mx-auto {
        margin-left: auto !important;
        margin-right: auto !important;
    }

    .print\:mb-4 {
        margin-bottom: 1rem !important;
    }

    .print\:bg-white {
        background-color: white !important;
    }
}
</style>
