<template>
    <transition name="fade">
        <div v-if="isOpen"
            class="fixed inset-0 z-[99999] flex items-start justify-center pt-24 sm:pt-0 sm:items-center p-4 bg-black/60 backdrop-blur-sm print:p-0 print:bg-white print:static"
            @click.self="close">
            <div
                class="bg-white w-full max-w-sm sm:max-w-md rounded-2xl shadow-2xl overflow-hidden print:shadow-none print:w-full print:max-w-none">
                <!-- Receipt Header -->
                <!-- Added print:pt-10 to push content down from header when printing -->
                <div
                    class="p-6 text-center border-b border-dashed border-gray-300 print:px-0 print:pb-0 print:pt-10 print:mb-4">
                    <!-- Modern Logo Implementation -->
                    <div class="mb-4 flex justify-center">
                        <img src="/images/logo-pstore.png" alt="PSTORE" class="h-16 object-contain"
                            @error="handleImageError" v-show="!imageError">
                        <!-- Fallback if logo missing -->
                        <div v-if="imageError"
                            class="w-12 h-12 bg-gray-900 text-white rounded-xl flex items-center justify-center mx-auto mb-2">
                            <span class="font-bold text-lg">P</span>
                        </div>
                    </div>

                    <h2 class="text-xl font-bold text-gray-900 mb-1" v-if="imageError">{{ transaction?.outlet_name ||
                        'APEX POS' }}</h2>
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
                                <span class="text-xs text-gray-500 print:text-black">
                                    {{ item.qty }} x {{ formatCurrency(item.price) }}
                                </span>
                            </div>
                            <span class="font-semibold text-gray-900 print:text-black whitespace-nowrap">
                                {{ formatCurrency(item.qty * item.price) }}
                            </span>
                        </div>

                        <!-- Fallback if no detailed items (using grand total) -->
                        <div v-if="(!transaction?.items || transaction.items.length === 0)"
                            class="text-center text-gray-400 text-xs italic py-2">
                            Detail item tidak tersedia
                        </div>
                    </div>

                    <div class="my-4 border-t border-dashed border-gray-300"></div>

                    <!-- Totals -->
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between font-bold text-gray-900 text-base print:text-black">
                            <span>Total</span>
                            <span>{{ formatCurrency(transaction?.grand_total || 0) }}</span>
                        </div>
                        <div class="flex justify-between text-xs text-gray-500 print:text-black">
                            <span>Metode Pembayaran</span>
                            <span class="uppercase">{{ transaction?.payment_method || 'Tunai' }}</span>
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
                    <p class="text-[10px] text-gray-400 mt-1">Powered by PSTORE POS</p>
                </div>
            </div>
        </div>
    </transition>
</template>

<script setup>
import { defineProps, defineEmits, ref } from 'vue';
import { Printer } from 'lucide-vue-next';

const props = defineProps({
    isOpen: Boolean,
    transaction: Object
});

const emit = defineEmits(['close']);
const imageError = ref(false);

const close = () => {
    emit('close');
};

const printReceipt = () => {
    window.print();
};

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

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

@media print {

    /* Hide everything else */
    body * {
        visibility: hidden;
    }

    /* Show only the receipt modal content */
    .fixed {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: white;
        padding: 0;
        display: block !important;
        visibility: visible;
        z-index: 99999;
        /* Ensure high z-index for print too */
    }

    /* Ensure internal content is visible */
    .fixed * {
        visibility: visible;
    }

    /* Reset helper classes */
    .print\:hidden {
        display: none !important;
    }

    .print\:block {
        display: block !important;
        /* Ensure display:block wins over other styles if any */
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

    /* Important to override p-0 if it was used */
    .print\:pt-10 {
        padding-top: 2.5rem !important;
    }

    .print\:static {
        position: static !important;
    }

    .print\:w-full {
        width: 100% !important;
    }

    .print\:max-w-none {
        max-width: none !important;
    }

    .print\:mb-4 {
        margin-bottom: 1rem !important;
    }
}
</style>
