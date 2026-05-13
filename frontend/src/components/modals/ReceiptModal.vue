<template>
    <transition name="fade">
        <div v-if="isOpen" id="receipt-modal-print-wrapper"
            class="fixed inset-0 z-[99999] flex items-center justify-center p-2 sm:p-4 bg-black/60 backdrop-blur-sm print:p-0 print:bg-white"
            @click.self="close">
            <div
                class="bg-white dark:bg-surface-800 rounded-2xl sm:rounded-3xl w-full max-w-lg overflow-hidden shadow-2xl print:shadow-none print:rounded-none print:max-w-full flex flex-col h-full max-h-[92vh] sm:max-h-[85vh]">

                <!-- Modal Header (hide on print) -->
                <div
                    class="p-6 flex justify-between items-center border-b border-gray-100 dark:border-surface-700 print:hidden shrink-0">
                    <h3 class="text-lg font-bold text-text-primary">
                        {{ receiptTitle }}
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
                    class="flex-1 overflow-y-auto p-2 sm:p-6 print:p-0 bg-gray-100/50 dark:bg-surface-900/50 print:bg-white">
                    <div v-if="transaction"
                        class="nota-paper w-full sm:max-w-[480px] mx-auto bg-white p-3 sm:p-6 text-black font-sans text-sm shadow-xl print:shadow-none print:max-w-full print:mx-0 print:p-4 border border-gray-200 print:border-none">

                        <!-- ===== NOTA HEADER ===== -->
                        <div class="grid grid-cols-[60px_1fr_40px] sm:grid-cols-[80px_1fr_60px] gap-1 sm:gap-2 mb-3 sm:mb-4 pb-3 sm:pb-4 border-b border-black">
                            <!-- Logo -->
                            <div class="w-16 h-16 bg-white overflow-hidden self-center">
                                <img src="/images/logo-pstore.png" alt="PSTORE" class="w-full h-full object-contain" />
                            </div>

                            <!-- Header Info (Center) -->
                            <div class="text-center self-center px-1">
                                <h1 class="text-sm sm:text-lg font-black text-black uppercase leading-tight">
                                    {{ (transaction.branch_name || transaction.branch?.name ||
                                        authStore.userBranch?.name || '').toUpperCase().includes('PSTORE')
                                        ? (transaction.branch_name || transaction.branch?.name ||
                                            authStore.userBranch?.name)
                                        : 'PSTORE ' + (transaction.branch_name || transaction.branch?.name ||
                                            authStore.userBranch?.name || '') }}
                                </h1>
                                <p class="text-[8px] font-bold leading-tight text-black mt-1">
                                    {{ displayAddress }}
                                </p>
                                <p class="text-[8px] font-bold text-black">
                                    {{ displayPhone }}
                                </p>
                            </div>

                            <!-- Social Links -->
                            <div class="flex flex-col justify-center items-end text-[7px] font-bold text-black pr-1">
                                <span v-if="receiptSetting?.instagram" class="flex items-center gap-0.5 leading-tight whitespace-nowrap">
                                    <span>IG:</span>
                                    <span class="uppercase font-black">@{{ receiptSetting.instagram.replace('@', '') }}</span>
                                </span>
                                <span v-if="receiptSetting?.tiktok" class="flex items-center gap-0.5 leading-tight mt-0.5 whitespace-nowrap">
                                    <span>TT:</span>
                                    <span class="uppercase font-black">@{{ receiptSetting.tiktok.replace('@', '') }}</span>
                                </span>
                            </div>
                        </div>

                        <!-- ===== NOTA TYPE TITLE ===== -->
                        <div class="text-center mb-4">
                            <h2 class="text-base font-black text-black uppercase border-b-2 border-black inline-block px-6 pb-0.5 tracking-widest">
                                {{ receiptTitle }}
                            </h2>
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
                        <table class="w-full text-[10px] sm:text-xs border-collapse mb-4">
                            <thead>
                                <tr class="border-t-2 border-b-2 border-black bg-gray-50/50">
                                    <th class="py-2 px-1 text-left font-bold text-black w-[40px] sm:w-[50px]">Qty</th>
                                    <th class="py-2 px-1 text-left font-bold text-black min-w-[70px]">IMEI</th>
                                    <th class="py-2 px-1 text-left font-bold text-black">Ket.</th>
                                    <th class="py-2 px-1 text-right font-bold text-black w-[80px] sm:w-[100px]">{{ columnLabelJumlah }}</th>
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
                                            <!-- Badge for In/Out if applicable -->
                                            <span v-if="item.name.includes('OUT:')" class="inline-block px-1.5 py-0.5 bg-red-100 text-red-700 text-[8px] font-black rounded mb-1">UNIT KELUAR</span>
                                            <span v-else-if="item.name.includes('IN:')" class="inline-block px-1.5 py-0.5 bg-emerald-100 text-emerald-700 text-[8px] font-black rounded mb-1">UNIT MASUK</span>
                                            
                                            <div class="font-black uppercase text-black">{{ item.name.replace('Tukar Tambah OUT: ', '').replace('Tukar Tambah IN: ', '').replace('Tukar Unit OUT: ', '').replace('Tukar Unit IN: ', '').replace('Downgrade OUT: ', '').replace('Downgrade IN: ', '').replace('OUT: ', '').replace('IN: ', '') }}</div>
                                            <div v-if="item.ram || item.storage"
                                                class="text-[10px] text-black font-medium">
                                                {{ [...new Set([item.ram, item.storage].filter(Boolean))].join('/') }}
                                            </div>
                                            <div v-if="item.condition" class="text-[9px] text-black font-bold italic">
                                                Condition: {{ item.condition === 'new' ? 'Baru' : (item.condition ===
                                                    'ex_ibox' ? 'Ex iBox' : 'Second') }}
                                            </div>
                                        </td>
                                        <td class="py-2 px-1 text-black align-top text-right font-bold w-[80px] sm:w-[120px]">
                                            <div v-if="(item.discount || item.item_discount) > 0"
                                                class="text-[7px] sm:text-[8px] text-gray-500 line-through opacity-70">
                                                {{ formatNumber(item.qty * (item.price || item.selling_price)) }}
                                            </div>
                                            <div class="flex flex-col">
                                                <span :class="item.price < 0 ? 'text-emerald-700' : ''" class="text-[9px] sm:text-xs">{{ formatNumber(item.qty * ((item.price || item.selling_price || 0) -
                                                    (item.discount || item.item_discount || 0))) }}</span>
                                                <span v-if="(item.discount || item.item_discount) > 0"
                                                    class="text-[6px] sm:text-[7px] text-primary-600 bg-primary-50 px-0.5 rounded inline-block self-end mt-0.5">
                                                    -{{ formatNumber(item.qty * (item.discount ||
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
                            </tbody>
                        </table>

                        <!-- ===== PAYMENT SECTION ===== -->
                        <div class="flex justify-end mb-4 payment-section">
                            <div class="w-full sm:w-[240px] text-xs space-y-1">
                                <!-- Subtotal -->
                                <div class="flex justify-between border-b border-gray-300 pb-1">
                                    <span class="font-bold text-black text-[10px]">SUB TOTAL :</span>
                                    <span class="text-black">
                                        {{ formatCurrency(transaction.original_price ||
                                            (Number(transaction.selling_price || 0) +
                                                Number(transaction.total_discount || 0))) }}
                                    </span>
                                </div>

                                <!-- Total Diskon if any -->
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
                                    <span class="font-black text-black text-xs uppercase tracking-tight">{{ labelTotal }}</span>
                                    <span class="font-black text-black text-xs">
                                        {{ formatCurrency(calculatedGrandTotal) }}
                                    </span>
                                </div>

                                <!-- Total Paid / Dibayar -->
                                <div
                                    class="flex justify-between border-t border-gray-400/50 pt-1 text-black font-extrabold flex-row-reverse">
                                    <span>{{ formatCurrency(calculatedTotalPaid) }}</span>
                                    <span class="text-[10px] uppercase">{{ labelBayar }} :</span>
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
                        <div v-if="transaction.notes || transaction.reason" class="mb-4 text-[10px] text-black italic">
                            <div v-if="transaction.reason">
                                <span class="font-bold">Alasan:</span> {{ transaction.reason }}
                            </div>
                            <div v-if="transaction.notes">
                                <span class="font-bold">Catatan:</span> {{ transaction.notes }}
                            </div>
                        </div>

                        <!-- ===== GARANSI NOTES ===== -->
                        <div
                            class="bg-gray-100/80 border border-black/20 rounded p-2.5 mb-5 print:bg-white print:border-black">
                            <div v-if="displayWarranty"
                                class="text-[9px] text-black font-bold whitespace-pre-line leading-relaxed">
                                {{ displayWarranty }}
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
                        <div class="flex justify-between text-[10px] sm:text-xs mt-6 mb-2 signature-area gap-2">
                            <div class="flex-1 text-center min-w-0">
                                <p class="font-semibold text-black mb-10 sm:mb-12">Pembeli,</p>
                                <div class="border-b border-gray-400 w-full max-w-[120px] mx-auto text-center text-[8px] sm:text-[10px] font-bold truncate">
                                    {{ transaction.customer_name || 'Umum' }}
                                </div>
                            </div>
                            <div class="flex-1 text-center min-w-0">
                                <p class="font-semibold text-black mb-10 sm:mb-12">Hormat Kami,</p>
                                <div class="border-b border-gray-400 w-full max-w-[120px] mx-auto text-center text-[8px] sm:text-[10px] font-bold truncate">
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

const printReceipt = () => {
    window.print();
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

// Computed property for Receipt Title
const receiptTitle = computed(() => {
    const cat = props.transaction?.category || 'penjualan';
    const mapping = {
        'penjualan_store': 'Nota Penjualan Store',
        'penjualan': 'Nota Penjualan',
        'penjualan_offline': 'Nota Penjualan',
        'refund': 'Nota Refund',
        'tukar_tambah': 'Nota Tukar Tambah',
        'tukar_unit': 'Nota Tukar Unit',
        'angkat_barang': 'Nota Angkat Barang',
        'downgrade': 'Nota Downgrade',
        'shopee': 'Nota Shopee',
        'orderan_online': 'Nota Order Online'
    };
    return mapping[cat] || 'Nota Penjualan';
});

// Dynamic labels for table and summary
const columnLabelJumlah = computed(() => {
    const cat = props.transaction?.category;
    if (cat === 'refund') return 'Refund';
    if (cat === 'angkat_barang') return 'Harga Angkat';
    return 'Jumlah';
});

const labelTotal = computed(() => {
    const cat = props.transaction?.category;
    if (cat === 'tukar_tambah' || cat === 'downgrade' || cat === 'tukar_unit') return 'SELISIH HARGA';
    if (cat === 'refund') return 'TOTAL REFUND';
    if (cat === 'angkat_barang') return 'TOTAL ANGKAT';
    return 'HARGA TOTAL';
});

const labelBayar = computed(() => {
    const cat = props.transaction?.category;
    if (cat === 'refund' || cat === 'downgrade') return 'DIKEMBALIKAN';
    if (cat === 'angkat_barang') return 'DIBAYARKAN';
    return 'DIBAYAR';
});

// Robust calculations for totals and change
const calculatedTotalPaid = computed(() => {
    // 1. Try direct fields
    const directPaid = Number(props.transaction.paid || props.transaction.paid_amount || 0);
    if (directPaid !== 0) return directPaid;

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
    const cat = props.transaction?.category;
    // For refund/downgrade where we pay the customer, "change" is usually not needed in the same way
    if (cat === 'refund' || cat === 'downgrade') return 0;
    return Math.max(0, calculatedTotalPaid.value - Math.abs(calculatedGrandTotal.value));
});

const receiptSetting = computed(() => {
    const b = props.transaction.branch || authStore.userBranch;
    const os = props.transaction.online_shop || authStore.user?.online_shop;
    const target = b || os;
    console.log("DEBUG RECEIPT - Target Location:", target);
    if (!target) return null;
    const setting = target.receipt_setting || target.receiptSetting;
    console.log("DEBUG RECEIPT - Resolved Setting:", setting);
    return setting;
});

const displayAddress = computed(() => {
    return receiptSetting.value?.store_address 
        || 'Pusat Perbelanjaan Online';
});

// WhatsApp Phone
const displayPhone = computed(() => {
    const phone = receiptSetting.value?.whatsapp_number;
    if (phone && phone.trim() !== '') {
        return `WhatsApp: ${phone}`;
    }
    return 'HP, Laptop, Barang Elektronik Bergaransi';
});

const displayWarranty = computed(() => {
    return receiptSetting.value?.warranty_terms
        || `1. Garansi 1 Bulan (Nota Dan Segel Jangan Hilang)\n2. Barang yang Sudah Dibeli Tidak Dapat Dikembalikan/Ditukarkan\n3. Tidak ada garansi IMEI afr, jatuh, gagal upgrade dan LCD`;
});

// Customer Phone
const displayCustomerPhone = computed(() => {
    const p = props.transaction.customer_phone || props.transaction.customer_wa || props.transaction.shopee_phone || props.transaction.event_phone;
    if (p && p.trim() !== '') {
        return p;
    }
    return '-';
});

// Date
const displayDate = computed(() => {
    let rawDate = props.transaction.date || props.transaction.created_at;
    if (!rawDate) return '-';
    
    if (typeof rawDate === 'string' && /^[0-9]{2} [A-Za-z]{3,4} [0-9]{4}$/.test(rawDate)) {
        return rawDate;
    }
    
    const dateObj = new Date(rawDate);
    if (!isNaN(dateObj.getTime())) {
        return dateObj.toLocaleString("id-ID", {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }).replace(/\./g, ':');
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
    
    // 2. Add Non-HP Items
    const nonHpSource = props.transaction.non_hp_items || props.transaction.non_hp_details || props.transaction.nonHpItems;
    
    if (nonHpSource?.length > 0) {
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
}

.nota-paper .text-emerald-700 {
    color: #047857 !important;
}

.pdf-capture-mode,
.pdf-capture-mode * {
    background-color: #ffffff !important;
    border-color: #e5e7eb !important;
    color: #000000 !important;
    box-shadow: none !important;
}
</style>

<style>
@media print {
    @page {
        margin: 0;
        size: auto;
    }

    html,
    body,
    #app,
    #app>* {
        visibility: hidden !important;
        margin: 0 !important;
        padding: 0 !important;
        height: auto !important;
    }

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

    .print\:hidden {
        display: none !important;
    }

    img {
        display: block !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
</style>