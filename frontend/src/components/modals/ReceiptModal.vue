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
                        class="nota-paper w-full sm:max-w-[480px] mx-auto bg-white p-3 sm:p-6 rounded-2xl text-neutral-900 font-sans text-sm shadow-xl print:shadow-none print:max-w-full print:mx-0 print:p-4 border-t-8 border-red-600 relative overflow-hidden print:border-t-0">
                        
                        <!-- ===== WATERMARK BACKGROUND ===== -->
                        <div class="absolute inset-0 flex items-center justify-center opacity-[0.03] pointer-events-none select-none print:opacity-[0.04] z-0">
                            <img src="/images/ps.png" alt="" class="w-72 h-72 object-contain transform scale-125" />
                        </div>

                        <div class="relative z-10">
                            <!-- ===== NOTA HEADER ===== -->
                            <div class="flex flex-col items-center text-center mb-5 pb-5 border-b border-red-100/80">
                                <!-- Logo and Branch Stack -->
                                <div class="flex items-center justify-center gap-4 mb-3">
                                    <div class="w-20 h-20 bg-white rounded-2xl flex items-center justify-center shrink-0 border border-red-100 shadow-sm">
                                        <img src="/images/ps.png" alt="PSTORE" class="w-16 h-16 object-contain" />
                                    </div>
                                    <div class="text-left">
                                        <h1 class="text-lg sm:text-xl font-black text-red-600 uppercase leading-none tracking-tight">
                                            {{ (transaction.branch_name || transaction.branch?.name ||
                                                authStore.userBranch?.name || '').toUpperCase().includes('PSTORE')
                                                ? (transaction.branch_name || transaction.branch?.name ||
                                                    authStore.userBranch?.name)
                                                : 'PSTORE ' + (transaction.branch_name || transaction.branch?.name ||
                                                    authStore.userBranch?.name || '') }}
                                        </h1>
                                        <p class="text-[8px] sm:text-[9px] font-black text-neutral-600 mt-1.5 leading-tight max-w-[220px] sm:max-w-xs">
                                            {{ displayAddress }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Unified Social Bar -->
                                <div class="flex flex-wrap justify-center items-center gap-x-3 gap-y-1 px-3 py-1 bg-white rounded-full border border-red-100/60 shadow-sm w-fit">
                                    <!-- WhatsApp -->
                                    <span class="flex items-center gap-1 text-[8px] sm:text-[9px] font-black text-neutral-800 whitespace-nowrap">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-2.5 h-2.5 text-emerald-500 fill-current" viewBox="0 0 24 24">
                                            <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.443-4.421-9.868-9.889-9.868-5.462 0-9.901 4.44-9.904 9.888-.001 2.15.619 4.193 1.694 5.829l-1.002 3.665 3.82-1.021z"/>
                                        </svg>
                                        <span>{{ displayPhoneClean }}</span>
                                    </span>
                                    
                                    <!-- Divider -->
                                    <span v-if="receiptSetting?.instagram || receiptSetting?.tiktok" class="text-red-200 text-[8px] font-normal">|</span>
                                    
                                    <!-- Instagram -->
                                    <span v-if="receiptSetting?.instagram" class="flex items-center gap-1 text-[8px] sm:text-[9px] font-black text-neutral-800 whitespace-nowrap">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-2.5 h-2.5 text-pink-600 fill-current" viewBox="0 0 24 24">
                                            <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                                        </svg>
                                        <span>@{{ receiptSetting.instagram.replace('@', '').toUpperCase() }}</span>
                                    </span>

                                    <!-- Divider -->
                                    <span v-if="receiptSetting?.instagram && receiptSetting?.tiktok" class="text-red-200 text-[8px] font-normal">|</span>

                                    <!-- TikTok -->
                                    <span v-if="receiptSetting?.tiktok" class="flex items-center gap-1 text-[8px] sm:text-[9px] font-black text-neutral-800 whitespace-nowrap">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-2.5 h-2.5 text-neutral-900 fill-current" viewBox="0 0 24 24">
                                            <path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.59-1 .01 2.24.01 4.48 0 6.72-.09 2.93-1.52 5.82-4.32 7.01-2.86 1.29-6.51.83-8.86-1.38-2.43-2.22-2.99-6.09-1.31-8.93 1.49-2.6 4.72-4 7.69-3.43v4.25c-1.82-.35-3.87.19-4.98 1.69-1.13 1.48-1.09 3.72-.02 5.22 1.15 1.66 3.58 2.27 5.44 1.4 1.71-.73 2.71-2.59 2.76-4.44.06-3.34.03-6.68.03-10.02l.02-.31z"/>
                                        </svg>
                                        <span>@{{ receiptSetting.tiktok.replace('@', '').toUpperCase() }}</span>
                                    </span>
                                </div>
                            </div>

                            <!-- ===== NOTA TYPE TITLE ===== -->
                            <div class="text-center mb-4">
                                <h2 class="text-xs sm:text-sm font-black text-red-600 uppercase bg-red-50 border border-red-100 rounded-lg inline-block px-5 py-1 tracking-[0.2em]">
                                    {{ receiptTitle }}
                                </h2>
                            </div>

                            <!-- ===== INFO NOTA ===== -->
                            <div class="grid grid-cols-2 gap-3 mb-4 text-xs">
                                <div class="p-2.5 bg-neutral-50 rounded-xl border border-red-100/60">
                                    <div class="text-[8px] font-black tracking-wider uppercase text-neutral-400">Pelanggan</div>
                                    <div class="font-black text-red-600 text-sm mt-0.5">{{ transaction.customer_name || 'Umum' }}</div>
                                    <div class="text-[9px] text-neutral-500 mt-1 font-bold">{{ displayCustomerPhone }}</div>
                                </div>
                                <div class="p-2.5 bg-neutral-50 rounded-xl border border-red-100/60 text-right">
                                    <div class="text-[8px] font-black tracking-wider uppercase text-neutral-400">No. Nota</div>
                                    <div class="font-black text-neutral-950 text-xs mt-0.5">#{{ transaction.order_no || '-' }}</div>
                                    <div class="text-[9px] text-neutral-500 mt-1 font-bold">{{ displayDate }}</div>
                                    <div class="text-[8px] text-neutral-400 mt-1 leading-none font-bold">Sales: <span class="font-black text-neutral-700">{{ transaction.inventory_user_name ||
                                        transaction.inventory_account_name ||
                                        transaction.sales_account || transaction.sales_name || '-' }}</span></div>
                                </div>
                            </div>

                            <!-- ===== TABEL ITEMS ===== -->
                            <div class="rounded-xl overflow-hidden border border-red-100/80 mb-4 bg-white">
                                <table class="w-full text-[10px] sm:text-xs border-collapse">
                                    <thead>
                                        <tr class="bg-red-600 text-white">
                                            <th class="py-2.5 px-3 text-left font-black text-white w-[40px]">Qty</th>
                                            <th class="py-2.5 px-2 text-left font-black text-white">Item Detail</th>
                                            <th class="py-2.5 px-3 text-right font-black text-white w-[90px] sm:w-[110px]">{{ columnLabelJumlah }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-transparent">
                                        <template v-if="allReceiptItems.length > 0">
                                            <tr v-for="(item, index) in allReceiptItems" :key="index" class="border-b border-red-50">
                                                <td class="py-3 px-3 text-neutral-950 text-center font-black align-top">{{ item.qty }}</td>
                                                <td class="py-3 px-2 align-top">
                                                    <!-- Badges -->
                                                    <div class="flex flex-wrap items-center gap-1.5 mb-1.5">
                                                        <span v-if="item.name.includes('OUT:')" class="px-1.5 py-0.5 bg-red-600 text-white text-[7px] sm:text-[8px] font-black rounded tracking-wider uppercase">KELUAR</span>
                                                        <span v-else-if="item.name.includes('IN:')" class="px-1.5 py-0.5 bg-neutral-950 text-white text-[7px] sm:text-[8px] font-black rounded tracking-wider uppercase">MASUK</span>
                                                        
                                                        <span v-if="item.condition" class="px-1.5 py-0.5 bg-neutral-100 text-neutral-700 text-[7px] font-black rounded tracking-wider uppercase border border-neutral-200">
                                                            {{ item.condition === 'new' ? 'Baru' : (item.condition === 'ex_ibox' ? 'Ex iBox' : 'Second') }}
                                                        </span>
                                                    </div>

                                                    <div class="font-black uppercase text-neutral-950 text-[10px] sm:text-xs tracking-tight">
                                                        {{ item.name.replace('Tukar Tambah OUT: ', '').replace('Tukar Tambah IN: ', '').replace('Tukar Unit OUT: ', '').replace('Tukar Unit IN: ', '').replace('Downgrade OUT: ', '').replace('Downgrade IN: ', '').replace('OUT: ', '').replace('IN: ', '') }}
                                                    </div>
                                                    
                                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5 mt-1">
                                                        <span v-if="item.imei && item.imei !== '-'" class="font-mono text-[9px] font-black text-red-600 bg-red-50/80 border border-red-100 px-1 py-0.5 rounded leading-none">
                                                            IMEI: {{ item.imei }}
                                                        </span>
                                                        <span v-if="item.ram || item.storage" class="text-[9px] text-neutral-600 font-black flex items-center gap-1">
                                                            <span class="w-1 h-1 rounded-full bg-neutral-300"></span>
                                                            {{ [...new Set([item.ram, item.storage].filter(Boolean))].join('/') }}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td class="py-3 px-3 align-top text-right w-[90px] sm:w-[110px]">
                                                    <div v-if="(item.discount || item.item_discount) > 0"
                                                        class="text-[7px] sm:text-[8px] text-neutral-400 line-through opacity-70 font-bold">
                                                        {{ formatNumber(item.qty * (item.price || item.selling_price)) }}
                                                    </div>
                                                    <div class="flex flex-col items-end">
                                                        <span :class="item.price < 0 ? 'text-red-600 font-black' : 'text-neutral-950'" class="text-[10px] sm:text-xs font-black">
                                                            {{ formatNumber(item.qty * ((item.price || item.selling_price || 0) -
                                                                (item.discount || item.item_discount || 0))) }}
                                                        </span>
                                                        <span v-if="(item.discount || item.item_discount) > 0"
                                                            class="text-[6px] sm:text-[7px] text-red-600 font-black bg-red-50 px-1 py-0.5 rounded mt-0.5">
                                                            -{{ formatNumber(item.qty * (item.discount || item.item_discount)) }}
                                                        </span>
                                                    </div>
                                                </td>
                                            </tr>
                                        </template>
                                        <tr v-for="n in Math.max(0, 3 - (allReceiptItems.length || 0))" :key="'empty-' + n" class="border-b border-red-50/50 opacity-20">
                                            <td class="py-4 px-3">&nbsp;</td>
                                            <td class="py-4 px-2"></td>
                                            <td class="py-4 px-3"></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- ===== PAYMENT SECTION ===== -->
                            <div class="flex justify-end mb-4 payment-section">
                                <div class="w-full sm:w-[240px] text-xs space-y-1.5">
                                    <!-- Subtotal -->
                                    <div class="flex justify-between border-b border-red-50 pb-1.5">
                                        <span class="font-bold text-neutral-500 text-[10px]">SUB TOTAL :</span>
                                        <span class="text-neutral-950 font-black">
                                            {{ formatCurrency(transaction.original_price ||
                                                (Number(transaction.selling_price || 0) +
                                                    Number(transaction.total_discount || 0))) }}
                                        </span>
                                    </div>

                                    <!-- Total Diskon -->
                                    <div v-if="transaction.total_discount > 0"
                                        class="flex justify-between border-b border-red-50 pb-1.5">
                                        <span class="font-bold text-neutral-500 text-[10px]">TOTAL DISKON :</span>
                                        <span class="text-red-600 font-black">
                                            -{{ formatCurrency(transaction.total_discount) }}
                                        </span>
                                    </div>

                                    <!-- Payment Breakdown -->
                                    <template
                                        v-if="transaction.split_payments_data && transaction.split_payments_data.length > 0">
                                        <div v-for="(payment, idx) in transaction.split_payments_data" :key="idx"
                                            class="flex justify-between border-b border-red-50 pb-1.5">
                                            <span class="font-bold text-neutral-500 text-[10px] uppercase">{{ payment.method_name }} :</span>
                                            <span class="text-neutral-700 font-black">
                                                {{ formatCurrency(payment.amount) }}
                                            </span>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <div v-if="transaction.cash > 0"
                                            class="flex justify-between border-b border-red-50 pb-1.5">
                                            <span class="font-bold text-neutral-500 text-[10px]">CASH :</span>
                                            <span class="text-neutral-700 font-black">
                                                {{ formatCurrency(transaction.cash) }}
                                            </span>
                                        </div>
                                        <div v-if="transaction.transfer > 0"
                                            class="flex justify-between border-b border-red-50 pb-1.5">
                                            <span class="font-bold text-neutral-500 text-[10px]">TF :</span>
                                            <span class="text-neutral-700 font-black">
                                                {{ formatCurrency(transaction.transfer) }}
                                            </span>
                                        </div>
                                    </template>

                                    <!-- Final Total Head (Deep Black Box) -->
                                    <div class="bg-neutral-950 text-white rounded-xl p-2.5 my-2 relative transition-all shadow-md">
                                        <div class="flex justify-between items-center">
                                            <span class="font-black text-white text-[10px] sm:text-xs uppercase tracking-wider">{{ labelTotal }}</span>
                                            <span class="font-black text-white text-sm sm:text-base">
                                                {{ formatCurrency(calculatedGrandTotal) }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Total Paid / Dibayar -->
                                    <div class="flex justify-between pt-1 text-neutral-700 font-black flex-row-reverse">
                                        <span class="text-neutral-950">{{ formatCurrency(calculatedTotalPaid) }}</span>
                                        <span class="text-[10px] uppercase text-neutral-400">{{ labelBayar }} :</span>
                                    </div>

                                    <!-- Change / Kembalian -->
                                    <div v-if="calculatedChange > 0"
                                        class="flex justify-between border border-red-100 pt-1.5 pb-1 px-2.5 mt-1.5 text-red-600 font-black flex-row-reverse bg-red-50/50 rounded-lg animate-pulse-once print:bg-white print:border-red-600">
                                        <span class="text-base text-red-600 font-black">{{ formatCurrency(calculatedChange) }}</span>
                                        <span class="text-[11px] uppercase text-red-600/80 self-center">KEMBALIAN :</span>
                                    </div>

                                    <div class="text-[8px] text-right text-neutral-400 italic mt-1 font-black">
                                        Metode: {{ transaction.split_payments_data?.length > 1 ? 'SPLIT (CAMPURAN)' : (transaction.payment_method_name || transaction.payment_method || '-') }}
                                    </div>
                                </div>
                            </div>

                            <!-- ===== TRANSACTION NOTES ===== -->
                            <div v-if="transaction.notes || transaction.reason" class="mb-4 p-2 bg-neutral-50 border border-red-100/60 rounded-lg text-[10px] text-neutral-700 font-black">
                                <div v-if="transaction.reason">
                                    <span class="text-red-600">Alasan:</span> {{ transaction.reason }}
                                </div>
                                <div v-if="transaction.notes" class="mt-0.5">
                                    <span class="text-red-600">Catatan:</span> {{ transaction.notes }}
                                </div>
                            </div>

                            <!-- ===== GARANSI NOTES ===== -->
                            <div class="bg-neutral-50 border border-red-100/60 rounded-xl p-3 mb-5 bg-transparent">
                                <div class="text-[8px] font-black tracking-widest text-red-600 uppercase mb-1.5 border-b border-red-100 pb-1">Ketentuan Garansi</div>
                                <div v-if="displayWarranty" class="text-[9px] text-neutral-600 font-black whitespace-pre-line leading-relaxed">
                                    {{ displayWarranty }}
                                </div>
                                <ul v-else class="text-[9px] text-neutral-600 font-black space-y-0.5 list-disc pl-3 leading-relaxed">
                                    <li>Garansi 1 Bulan (Nota Dan Segel Jangan Hilang)</li>
                                    <li>Barang yang Sudah Dibeli Tidak Dapat Dikembalikan/Ditukarkan</li>
                                    <li>Tidak ada garansi IMEI afr, jatuh, gagal upgrade dan LCD</li>
                                </ul>
                            </div>

                            <!-- ===== SIGNATURE AREA ===== -->
                            <div class="flex justify-between text-[10px] mt-8 mb-2 signature-area gap-4">
                                <div class="flex-1 text-center">
                                    <div class="text-[8px] font-black text-neutral-400 uppercase tracking-wider mb-12">Pembeli</div>
                                    <div class="border-b-2 border-dashed border-neutral-200 w-full max-w-[120px] mx-auto mb-1"></div>
                                    <div class="text-[10px] font-black text-neutral-700 truncate uppercase">
                                        {{ transaction.customer_name || 'Umum' }}
                                    </div>
                                </div>
                                <div class="flex-1 text-center">
                                    <div class="text-[8px] font-black text-neutral-400 uppercase tracking-wider mb-12">Hormat Kami</div>
                                    <div class="border-b-2 border-dashed border-neutral-200 w-full max-w-[120px] mx-auto mb-1"></div>
                                    <div class="text-[10px] font-black text-red-600 truncate uppercase">
                                        {{ transaction.inventory_user_name || transaction.inventory_account_name ||
                                            transaction.sales_account || transaction.sales_name || 'PSTORE' }}
                                    </div>
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

const displayPhoneClean = computed(() => {
    const phone = receiptSetting.value?.whatsapp_number;
    return phone && phone.trim() !== '' ? phone : '0851-3300-5600';
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
.nota-paper {
    background-color: #ffffff !important;
    box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1) !important;
    color: #0f172a !important;
    font-weight: 600 !important;
    text-rendering: optimizeLegibility;
    -webkit-font-smoothing: antialiased;
}

.nota-paper h1, 
.nota-paper h2, 
.nota-paper h3, 
.nota-paper th {
    color: #e11d48 !important;
}

/* Keep explicit text colors override functioning */
.nota-paper .text-white {
    color: #ffffff !important;
}

.nota-paper .text-red-600 {
    color: #e11d48 !important;
}

.nota-paper .text-neutral-950 {
    color: #0a0a0a !important;
}

.nota-paper .text-emerald-600,
.nota-paper .text-emerald-700 {
    color: #059669 !important;
}

.nota-paper .text-pink-600,
.nota-paper .text-pink-700 {
    color: #db2777 !important;
}

.nota-paper .text-slate-500 {
    color: #64748b !important;
}

.nota-paper .text-slate-400 {
    color: #94a3b8 !important;
}

.pdf-capture-mode,
.pdf-capture-mode * {
    background-color: #ffffff !important;
    border-color: #e5e7eb !important;
    color: #0f172a !important;
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