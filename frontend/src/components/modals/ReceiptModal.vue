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

                        <div class="flex justify-end mb-4 payment-section">
                            <div class="w-[240px] text-xs space-y-1">
                                <div class="flex justify-between border-b border-gray-300 pb-1 font-bold">
                                    <span>SUB TOTAL :</span>
                                    <span>{{ formatCurrency(calculatedGrandTotal) }}</span>
                                </div>
                                <div
                                    class="flex justify-between border-t-2 border-black pt-2 text-black font-extrabold flex-row-reverse">
                                    <span>{{ formatCurrency(calculatedTotalPaid) }}</span>
                                    <span class="text-[10px]">DIBAYAR :</span>
                                </div>
                                <div v-if="calculatedChange > 0"
                                    class="flex justify-between pt-1 text-primary-600 font-black flex-row-reverse">
                                    <span>{{ formatCurrency(calculatedChange) }}</span>
                                    <span class="text-[10px]">KEMBALIAN :</span>
                                </div>
                            </div>
                        </div>

                        <div v-if="transaction.notes" class="mb-4 text-[10px] text-black italic">
                            <span class="font-bold">Catatan:</span> {{ transaction.notes }}
                        </div>

                        <div
                            class="bg-gray-100/80 border border-black/20 rounded p-2.5 mb-5 print:bg-white print:border-black">
                            <ul class="text-[10px] text-black font-bold space-y-0.5 list-disc pl-3">
                                <li class="font-black underline italic">Garansi 1 Bulan (Nota Dan Segel Jangan Hilang)
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="p-4 bg-white border-t flex gap-3 print:hidden shrink-0">
                    <button @click="close"
                        class="flex-1 px-4 py-4 text-base font-black text-white bg-primary-600 rounded-[1.5rem] uppercase tracking-widest">
                        Selesai
                    </button>
                    <button @click="printReceipt" class="px-4 py-3 bg-gray-900 text-white rounded-2xl">
                        <Printer :size="18" />
                    </button>
                    <button @click="shareToWhatsApp" :disabled="isGeneratingPDF"
                        class="px-4 py-3 bg-emerald-600 text-white rounded-2xl flex items-center gap-2">
                        <Loader2 v-if="isGeneratingPDF" class="animate-spin" :size="18" />
                        <MessageSquare v-else :size="18" />
                        WA
                    </button>
                </div>
            </div>
        </div>
    </transition>
</template>

<script setup>
import { defineProps, defineEmits, ref, computed } from 'vue';
import { Printer, Pencil, X, MessageSquare, Loader2 } from 'lucide-vue-next';
import { useEscapeKey } from '../../composables/useEscapeKey';
import { useAuthStore } from '../../store/auth';
import api from '../../api/axios';

const authStore = useAuthStore();
const props = defineProps({
    isOpen: Boolean,
    transaction: Object,
    showEditIcon: { type: Boolean, default: false },
    autoSend: { type: Boolean, default: false }
});

const emit = defineEmits(['close', 'open-checklist', 'sent']);

const isGeneratingPDF = ref(false);

const close = () => emit('close');
const printReceipt = () => window.print();

const formatCurrency = (val) => {
    if (val === null || val === undefined) return 'Rp 0';
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val);
};

const formatNumber = (val) => new Intl.NumberFormat('id-ID').format(val || 0);

const calculatedTotalPaid = computed(() => {
    const directPaid = Number(props.transaction?.paid || props.transaction?.paid_amount || 0);
    if (directPaid > 0) return directPaid;
    return Number(props.transaction?.cash || 0) + Number(props.transaction?.transfer || 0);
});

const calculatedGrandTotal = computed(() => {
    return Number(props.transaction?.total || props.transaction?.grand_total || props.transaction?.selling_price || 0);
});

const calculatedChange = computed(() => Math.max(0, calculatedTotalPaid.value - calculatedGrandTotal.value));

const shareToWhatsApp = async () => {
    if (isGeneratingPDF.value) return;
    try {
        isGeneratingPDF.value = true;
        const response = await api.get(`/receipts/${props.transaction.id}/share-wa`, { timeout: 90000 });
        if (response.data.success && response.data.wa_url) {
            window.open(response.data.wa_url, '_blank');
            emit('sent');
        }
    } catch (error) {
        alert('Gagal mengirim WA: ' + error.message);
    } finally {
        isGeneratingPDF.value = false;
    }
};

useEscapeKey(() => { if (props.isOpen) close(); });
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

.nota-paper {
    background-color: #ffffff !important;
    color: #000 !important;
}

@media print {
    @page {
        margin: 0;
        size: auto;
    }

    html,
    body,
    #app {
        visibility: hidden !important;
    }

    #receipt-modal-print-wrapper,
    #receipt-modal-print-wrapper * {
        visibility: visible !important;
    }

    #receipt-modal-print-wrapper {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        display: block !important;
    }
}
</style>