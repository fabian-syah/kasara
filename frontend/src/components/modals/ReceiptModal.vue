<template>
    <Teleport to="body">
        <transition name="fade">
            <div v-if="isOpen" id="receipt-modal-print-wrapper"
                class="fixed inset-0 z-[99999] flex items-center justify-center p-2 sm:p-4 bg-black/60 backdrop-blur-sm print:p-0 print:bg-white"
                @click.self="close">
                <div
                    class="bg-white dark:bg-surface-800 rounded-2xl sm:rounded-3xl w-full max-w-2xl overflow-hidden shadow-2xl print:shadow-none print:rounded-none print:max-w-full flex flex-col h-full max-h-[92vh] sm:max-h-[85vh]">

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
                            ref="notaPaperRef"
                            class="nota-paper nota-page w-full sm:max-w-[650px] mx-auto bg-white p-3 sm:p-6 rounded-2xl text-neutral-900 font-sans text-sm shadow-xl print:shadow-none print:max-w-full print:mx-0 relative overflow-hidden select-none">

                            <!-- ===== DYNAMIC CORNER ACCENTS (Mockup Style) ===== -->
                            <div class="corner-accent-tl absolute top-0 left-0 w-16 h-16 pointer-events-none overflow-hidden z-20">
                                <svg class="w-full h-full" viewBox="0 0 100 100" preserveAspectRatio="none">
                                    <polygon points="0,0 100,0 0,100" fill="#0a0a0a" />
                                    <polygon points="0,0 55,0 0,55" fill="#dc2626" />
                                </svg>
                            </div>
                            <div class="corner-accent-tr absolute top-0 right-0 w-16 h-16 pointer-events-none overflow-hidden z-20">
                                <svg class="w-full h-full" viewBox="0 0 100 100" preserveAspectRatio="none">
                                    <polygon points="100,0 0,0 100,100" fill="#0a0a0a" />
                                    <polygon points="100,0 45,0 100,55" fill="#dc2626" />
                                </svg>
                            </div>

                            <!-- Watermark Background Logo (Zero Layout Impact) -->
                            <div class="watermark-container absolute inset-0 flex items-center justify-center pointer-events-none z-0 overflow-hidden select-none">
                                <img src="/images/ps.png" alt=""
                                    class="w-[200px] h-[200px] object-contain opacity-[0.05] pointer-events-none select-none" />
                            </div>

                            <div ref="notaInnerRef" class="nota-inner relative z-10 flex flex-col h-full">
                                <!-- 1. HEADER SECTION (Store Logo, Branch, Socials, Line, Title) -->
                                <div class="nota-section header-section shrink-0">
                                    <div class="flex items-center gap-4 px-3 sm:px-6 mb-2">
                                        <!-- Store Logo -->
                                        <div class="shrink-0">
                                            <img src="/images/ps.png" alt="PSTORE"
                                                class="w-14 h-14 sm:w-16 sm:h-16 object-contain" />
                                        </div>
                                        <!-- Branch & Social Info -->
                                        <div class="flex-1">
                                            <div class="flex items-baseline gap-1.5 flex-wrap">
                                                <span
                                                    class="text-xl sm:text-2xl font-black text-red-600 uppercase tracking-tight leading-none">PSTORE</span>
                                                <span
                                                    class="text-xl sm:text-2xl font-black text-neutral-900 uppercase tracking-tight leading-none">
                                                    {{ (transaction.branch_name || transaction.branch?.name ||
                                                        authStore.userBranch?.name || 'CABANG').toUpperCase().replace('PSTORE ',
                                                            '').replace('PSTORE', '') }}
                                                </span>
                                            </div>
                                            <div class="text-[10px] font-bold text-neutral-700 mt-1 leading-tight">
                                                {{ displayAddress }}
                                            </div>

                                            <!-- Social Bar -->
                                            <div
                                                class="flex items-center gap-x-3 gap-y-1 mt-1 text-[9px] font-extrabold text-neutral-800 flex-wrap">
                                                <span class="flex items-center gap-1">
                                                    <svg class="w-2.5 h-2.5 text-red-600 fill-current" viewBox="0 0 24 24">
                                                        <path
                                                            d="M20 15.5c-1.25 0-2.45-.2-3.57-.57a1.02 1.02 0 00-1.02.24l-2.2 2.2a15.05 15.05 0 01-6.59-6.59l2.2-2.21a.96.96 0 00.25-1.02A11.36 11.36 0 018.5 4c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1 0 9.39 7.61 17 17 17 .55 0 1-.45 1-1v-3.5c0-.55-.45-1-1-1zM12 3v10l3-3h6V3h-9z" />
                                                    </svg>
                                                    WA: {{ displayPhoneClean }}
                                                </span>
                                                <span v-if="receiptSetting?.tiktok" class="text-neutral-300">|</span>
                                                <span v-if="receiptSetting?.tiktok" class="flex items-center gap-1">
                                                    <svg class="w-2.5 h-2.5 text-red-600 fill-current" viewBox="0 0 24 24">
                                                        <path
                                                            d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.59-1 .01 2.24.01 4.48 0 6.72-.09 2.93-1.52 5.82-4.32 7.01-2.86 1.29-6.51.83-8.86-1.38-2.43-2.22-2.99-6.09-1.31-8.93 1.49-2.6 4.72-4 7.69-3.43v4.25c-1.82-.35-3.87.19-4.98 1.69-1.13 1.48-1.09 3.72-.02 5.22 1.15 1.66 3.58 2.27 5.44 1.4 1.71-.73 2.71-2.59 2.76-4.44.06-3.34.03-6.68.03-10.02l.02-.31z" />
                                                    </svg>
                                                    TikTok: {{ receiptSetting.tiktok.replace('@', '') }}
                                                </span>
                                                <span v-if="receiptSetting?.instagram" class="text-neutral-300">|</span>
                                                <span v-if="receiptSetting?.instagram" class="flex items-center gap-1">
                                                    <svg class="w-2.5 h-2.5 text-red-600 fill-current" viewBox="0 0 24 24">
                                                        <path
                                                            d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z" />
                                                    </svg>
                                                    IG: {{ receiptSetting.instagram.replace('@', '') }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Thick Middle Segment Separator Line -->
                                    <div class="relative w-full h-px bg-neutral-200 mb-2 mt-1 flex justify-center">
                                        <div class="absolute -top-0.5 w-16 h-1 bg-red-600 rounded-full"></div>
                                    </div>

                                    <!-- Invoice Type Head -->
                                    <div class="text-center mb-1 mt-1">
                                        <h2
                                            class="text-2xl sm:text-3xl font-black text-neutral-950 uppercase tracking-wide leading-tight">
                                            {{ receiptTitle }}
                                        </h2>
                                        <div
                                            class="flex items-center justify-center gap-2 text-[10px] font-black tracking-wider text-red-600 mt-0.5">
                                            <div class="h-[1.5px] w-5 bg-red-600/30 rounded-full"></div>
                                            <span>BUKTI TRANSAKSI</span>
                                            <div class="h-[1.5px] w-5 bg-red-600/30 rounded-full"></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Spacer 1: Flexible distribution -->
                                <div class="nota-spacer"></div>

                                <!-- 2. METADATA INFO GRID (3 Columns, 2 Rows) -->
                                <div class="nota-section metadata-section shrink-0">
                                    <div
                                        class="metadata-grid grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-x-0 print:grid-cols-3 print:gap-x-0 bg-white/60 backdrop-blur-[2px] rounded-xl p-3 border border-neutral-200">
                                    <!-- Column 1: No Nota & Tanggal -->
                                    <div class="flex flex-col gap-4 pr-4 border-r border-dashed border-neutral-300">
                                        <!-- No Nota -->
                                        <div class="flex items-center gap-2.5">
                                            <div
                                                class="w-7 h-7 rounded-full border border-neutral-200/60 flex items-center justify-center shrink-0 bg-neutral-50 shadow-sm text-red-600">
                                                <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24">
                                                    <path
                                                        d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z" />
                                                </svg>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div
                                                    class="text-[7px] font-black text-neutral-950 uppercase tracking-widest leading-none mb-0.5">
                                                    No. Nota</div>
                                                <div
                                                    class="text-[10px] sm:text-[11px] font-black text-neutral-950 uppercase tracking-tight break-words">
                                                    {{ transaction.order_no || transaction.receipt_id || transaction.invoice_number || '-' }}</div>
                                            </div>
                                        </div>

                                        <!-- Tanggal & Waktu -->
                                        <div class="flex items-center gap-2.5">
                                            <div
                                                class="w-7 h-7 rounded-full border border-neutral-200/60 flex items-center justify-center shrink-0 bg-neutral-50 shadow-sm text-red-600">
                                                <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24">
                                                    <path
                                                        d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10zm0-12H5V6h14v2z" />
                                                </svg>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div
                                                    class="text-[7px] font-black text-neutral-950 uppercase tracking-widest leading-none mb-0.5">
                                                    Tanggal & Waktu</div>
                                                <div
                                                    class="text-[10px] sm:text-[11px] font-black text-neutral-950 uppercase tracking-tight break-words">
                                                    {{ displayDate }}</div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Column 2: Atas Nama & No HP -->
                                    <div class="flex flex-col gap-4 px-4 border-r border-dashed border-neutral-300">
                                        <!-- Atas Nama -->
                                        <div class="flex items-center gap-2.5">
                                            <div
                                                class="w-7 h-7 rounded-full border border-neutral-200/60 flex items-center justify-center shrink-0 bg-neutral-50 shadow-sm text-red-600">
                                                <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24">
                                                    <path
                                                        d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z" />
                                                </svg>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div
                                                    class="text-[7px] font-black text-neutral-950 uppercase tracking-widest leading-none mb-0.5">
                                                    Atas Nama</div>
                                                <div
                                                    class="text-[10px] sm:text-[11px] font-black text-neutral-950 uppercase tracking-tight break-words">
                                                    {{ transaction.customer_name || 'Umum' }}</div>
                                            </div>
                                        </div>

                                        <!-- No HP -->
                                        <div class="flex items-center gap-2.5">
                                            <div
                                                class="w-7 h-7 rounded-full border border-neutral-200/60 flex items-center justify-center shrink-0 bg-neutral-50 shadow-sm text-red-600">
                                                <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24">
                                                    <path
                                                        d="M20.01 15.38c-1.23 0-2.42-.2-3.53-.56a.977.977 0 00-1.01.24l-1.57 1.97c-2.83-1.35-5.48-3.9-6.89-6.83l1.95-1.66c.27-.28.35-.67.24-1.02-.37-1.11-.56-2.3-.56-3.53 0-.54-.45-.99-.99-.99H4.19C3.65 3 3 3.24 3 3.99 3 13.28 10.72 21 20.01 21c.71 0 .99-.63.99-1.18v-3.45c0-.54-.45-.99-.99-.99z" />
                                                </svg>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div
                                                    class="text-[7px] font-black text-neutral-950 uppercase tracking-widest leading-none mb-0.5">
                                                    No. HP</div>
                                                <div
                                                    class="text-[10px] sm:text-[11px] font-black text-neutral-950 uppercase tracking-tight break-words">
                                                    {{ displayCustomerPhone }}</div>
                                                <button v-if="displayCustomerPhone !== '-'"
                                                    @click="sendWaReceiptFromModal" :disabled="isGeneratingPDF"
                                                    class="mt-2 px-2.5 py-1.5 text-[8px] font-black text-white bg-emerald-600 rounded-xl hover:bg-emerald-700 transition-all flex items-center justify-center gap-1 active:scale-95 uppercase tracking-wider print:hidden shadow-md shadow-emerald-500/25">
                                                    <Loader2 v-if="isGeneratingPDF" class="animate-spin w-2.5 h-2.5" />
                                                    <span>{{ isGeneratingPDF ? 'Mengirim...' : 'Kirim Nota' }}</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Column 3: Customer Service & No. Resi -->
                                    <div class="flex flex-col gap-4 pl-4">
                                        <div class="flex items-center gap-2.5">
                                            <div
                                                class="w-7 h-7 rounded-full border border-neutral-200/60 flex items-center justify-center shrink-0 bg-neutral-50 shadow-sm text-red-600">
                                                <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24">
                                                    <path
                                                        d="M12 2c-4.97 0-9 4.03-9 9v7c0 1.66 1.34 3 3 3h3v-8H5v-2c0-3.87 3.13-7 7-7s7 3.13 7 7v2h-4v8h3c1.66 0 3-1.34 3-3v-7c0-4.97-4.03-9-9-9z" />
                                                </svg>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div
                                                    class="text-[7px] font-black text-neutral-950 uppercase tracking-widest leading-none mb-0.5">
                                                    Customer Service</div>
                                                <div
                                                    class="text-[10px] sm:text-[11px] font-black text-neutral-950 uppercase tracking-tight break-words">
                                                    {{ transaction.inventory_user_name ||
                                                        transaction.inventory_account_name
                                                        || transaction.sales_account || transaction.sales_name || '-' }}
                                                </div>
                                            </div>
                                        </div>

                                        <!-- No. Resi (Khusus Pengiriman / Pesanan Online) -->
                                        <div v-if="displayTrackingNo" class="flex items-center gap-2.5">
                                            <div
                                                class="w-7 h-7 rounded-full border border-neutral-200/60 flex items-center justify-center shrink-0 bg-neutral-50 shadow-sm text-red-600">
                                                <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24">
                                                    <path
                                                        d="M20 8h-3V4H3c-1.1 0-2 .9-2 2v11h2c0 1.66 1.34 3 3 3s3-1.34 3-3h6c0 1.66 1.34 3 3 3s3-1.34 3-3h2v-5l-3-4zM6 18.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm13.5-9l1.96 2.5H17V9.5h2.5zm-1 9c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z" />
                                                </svg>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div
                                                    class="text-[7px] font-black text-neutral-950 uppercase tracking-widest leading-none mb-0.5">
                                                    No. Resi</div>
                                                <div
                                                    class="text-[10px] sm:text-[11px] font-black text-neutral-950 uppercase tracking-tight break-words font-mono">
                                                    {{ displayTrackingNo }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                    <!-- Alamat Pengiriman (Khusus Transaksi dengan Tujuan Kirim) -->
                                    <div v-if="displayShippingAddress"
                                        class="mt-2 bg-white/60 backdrop-blur-[2px] rounded-xl p-3 border border-neutral-200 flex items-start gap-2.5">
                                        <div
                                            class="w-7 h-7 rounded-full border border-neutral-200/60 flex items-center justify-center shrink-0 bg-neutral-50 shadow-sm text-red-600 mt-0.5">
                                            <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24">
                                                <path
                                                    d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z" />
                                            </svg>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div
                                                class="text-[7px] font-black text-neutral-950 uppercase tracking-widest leading-none mb-0.5">
                                                Alamat Pengiriman</div>
                                            <div
                                                class="text-[10px] sm:text-[11px] font-bold text-neutral-900 leading-snug break-words">
                                                {{ displayShippingAddress }}</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Spacer 2: Flexible distribution -->
                                <div class="nota-spacer"></div>

                                <!-- 3. TABEL ITEMS (6 Columns) -->
                                <div class="nota-section items-table-section shrink-0">
                                    <div
                                        class="items-table-wrapper rounded-xl overflow-x-auto print:overflow-visible border border-neutral-200 shadow-sm bg-white/60 backdrop-blur-[1px]">
                                        <table class="w-full text-[10px] sm:text-xs border-collapse">
                                            <thead>
                                                <tr style="background-color: #0a0a0a !important;"
                                                    class="text-[9px] sm:text-[10px] uppercase tracking-wider">
                                                    <th style="background-color: #0a0a0a !important; color: #ffffff !important;"
                                                        class="py-2.5 px-3 text-left font-black w-[110px]">IMEI</th>
                                                    <th style="background-color: #0a0a0a !important; color: #ffffff !important;"
                                                        class="py-2.5 px-3 text-left font-black">Deskripsi Barang</th>
                                                    <th style="background-color: #0a0a0a !important; color: #ffffff !important;"
                                                        class="py-2.5 px-3 text-right font-black w-[85px]">Harga Satuan</th>
                                                    <th v-if="hasAnyItemDiscount" style="background-color: #0a0a0a !important; color: #ffffff !important;"
                                                        class="py-2.5 px-3 text-right font-black w-[70px]">Diskon</th>
                                                    <th style="background-color: #0a0a0a !important; color: #ffffff !important;"
                                                        class="py-2.5 px-3 text-center font-black w-[40px]">Qty</th>
                                                    <th style="background-color: #0a0a0a !important; color: #ffffff !important;"
                                                        class="py-2.5 px-3 text-right font-black w-[85px]">Jumlah</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <template v-if="processedReceiptItems.length > 0">
                                                    <tr v-for="(item, index) in processedReceiptItems" :key="index" :class="[
                                                        item.is_bundle_header ? 'bg-blue-50/30 font-bold print:bg-white' : (index % 2 === 1 ? 'bg-neutral-100/80' : 'bg-white/90'),
                                                        item.show_bottom_border ? 'border-b border-neutral-300' : ''
                                                    ]">

                                                        <!-- IMEI COLUMN -->
                                                        <td class="py-2 px-3 align-middle text-left">
                                                            <div v-if="!item.is_bundle_header"
                                                                class="text-[10px] font-black font-mono text-neutral-900"
                                                                :class="{ 'pl-4': item.is_bundle_child }">
                                                                {{ item.is_hp ? (item.imei && item.imei !== '-' ? item.imei
                                                                : '-') : '-' }}
                                                            </div>
                                                            <div v-else
                                                                class="text-[10px] font-bold text-neutral-400 text-center">-
                                                            </div>
                                                        </td>

                                                        <!-- DESKRIPSI BARANG COLUMN -->
                                                        <td class="py-2 px-3 align-middle">
                                                            <div v-if="item.is_bundle_header"
                                                                class="font-black text-neutral-950 text-[11px] sm:text-[12px] uppercase tracking-wide flex items-center">
                                                                <span>📦 {{ item.name }}</span>
                                                            </div>
                                                            <div v-else
                                                                class="font-bold text-neutral-800 text-[10px] sm:text-[11px] uppercase flex items-start"
                                                                :class="{ 'pl-4 font-semibold': item.is_bundle_child }">
                                                                <span v-if="item.is_bundle_child"
                                                                    class="text-neutral-600 mr-1.5 text-[12px] leading-none font-black mt-0.5">*</span>
                                                                <span>
                                                                    <span v-if="item.is_dp_deduction" class="mr-1.5 px-1 py-[1px] rounded bg-emerald-100 text-emerald-700 border border-emerald-200 text-[8px] font-black uppercase tracking-wider inline-block leading-none align-middle">DP</span>
                                                                    <span v-if="transaction.category === 'pelunasan_dp' && !item.is_dp_deduction && !item.is_bundle_header" class="mr-1.5 px-1 py-[1px] rounded bg-rose-100 text-rose-700 border border-rose-200 text-[8px] font-black uppercase tracking-wider inline-block leading-none align-middle">PELUNASAN</span>
                                                                    <span v-if="(item.name || '').includes('IN:')" class="mr-1.5 px-1 py-[1px] rounded bg-emerald-100 text-emerald-700 border border-emerald-200 text-[8px] font-black uppercase tracking-wider inline-block leading-none align-middle">MASUK</span>
                                                                    <span v-if="(item.name || '').includes('OUT:')" class="mr-1.5 px-1 py-[1px] rounded bg-rose-100 text-rose-700 border border-rose-200 text-[8px] font-black uppercase tracking-wider inline-block leading-none align-middle">KELUAR</span>
                                                                    <span class="align-middle">
                                                                        {{ item.is_hp ? (
                                                                            ((item.brand && item.brand !== '-' ? item.brand : (item.product?.brand?.name || item.product?.brandRelation?.name || item.product?.brand))) ? 
                                                                            ((item.brand && item.brand !== '-' ? item.brand : (item.product?.brand?.name || item.product?.brandRelation?.name || item.product?.brand))) + ' - ' : ''
                                                                        ) : ''
                                                                        }}{{ (item.name || '').replace('Tukar Tambah OUT: ', '').replace('Tukar Tambah IN: ', '').replace('Tukar Unit OUT: ', '').replace('Tukar Unit IN: ', '').replace('Downgrade OUT: ', '').replace('Downgrade IN: ', '').replace('OUT: ', '').replace('IN: ', '').replace(/paket bundling/gi, 'Paket Promo').replace('📦 ', '') }}
                                                                        <template v-if="item.is_hp">
                                                                            {{ item.storage && item.storage !== '-' ? ' ' + item.storage : '' }}
                                                                            {{ item.condition && item.condition !== '-' ? ' ' + (item.condition === 'new' ? 'Baru' : (item.condition === 'ex_ibox' ? 'Ex iBox' : (item.condition === 'second' ? 'Second' : item.condition))) : '' }}
                                                                        </template>
                                                                    </span>
                                                                </span>
                                                            </div>
                                                        </td>

                                                        <!-- HARGA SATUAN COLUMN -->
                                                        <td class="py-2 px-3 align-middle text-right">
                                                            <div v-if="!item.is_bundle_child"
                                                                class="text-[10px] sm:text-[11px] font-bold text-neutral-900">
                                                                {{ formatNumber(Math.abs(item.originalPrice || 0)) }}
                                                            </div>
                                                            <div v-else class="text-[10px] font-bold text-neutral-400 text-center">-</div>
                                                        </td>

                                                        <!-- DISKON COLUMN -->
                                                        <td v-if="hasAnyItemDiscount" class="py-2 px-3 align-middle text-right">
                                                            <div v-if="!item.is_bundle_child"
                                                                class="text-[10px] font-black text-red-600">
                                                                <span v-if="(item.itemDiscount || 0) > 0">
                                                                    - {{ formatNumber(Math.abs(item.itemDiscount || 0)) }}
                                                                </span>
                                                                <span v-else class="text-neutral-300">-</span>
                                                            </div>
                                                            <div v-else class="text-[10px] font-bold text-neutral-400 text-center">-</div>
                                                        </td>

                                                        <!-- QTY COLUMN -->
                                                        <td class="py-2 px-3 align-middle text-center">
                                                            <div v-if="!item.is_bundle_child"
                                                                class="text-[10px] sm:text-[11px] font-black text-neutral-900">
                                                                {{ item.qty || 1 }}
                                                            </div>
                                                            <div v-else class="text-[10px] font-bold text-neutral-400 text-center">-</div>
                                                        </td>

                                                        <!-- JUMLAH COLUMN -->
                                                        <td class="py-2 px-3 align-middle text-right">
                                                            <div class="text-[10px] sm:text-[11px] font-black text-neutral-900">
                                                                <span v-if="item.is_bundle_child" class="text-neutral-400">-</span>
                                                                <span v-else-if="item.is_bundle_header">
                                                                    {{ formatNumber(Math.abs(item.price)) }}
                                                                </span>
                                                                <span v-else>
                                                                    {{ formatNumber(Math.abs(item.finalTotal || 0)) }}
                                                                </span>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </template>
                                                <!-- Filler rows (hidden on print) -->
                                                <tr v-for="n in Math.max(0, 2 - (processedReceiptItems.length || 0))"
                                                    :key="'empty-' + n" class="border-b border-neutral-100 print:hidden">
                                                    <td class="py-2 px-3">&nbsp;</td>
                                                    <td colspan="5"></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Spacer 3: Flexible distribution -->
                                <div class="nota-spacer"></div>

                                <!-- 4. SUMMARY & NOTES SECTION (Split Column: Notes/Warranty & Breakdown/Total) -->
                                <div class="nota-section summary-section shrink-0">
                                    <div class="split-section flex flex-col md:flex-row gap-4 print:flex-row print:gap-3 items-start">
                                        <!-- Left Column: Notes & Warranty -->
                                        <div class="flex-1 w-full min-w-0">
                                            <!-- Catatan Section (Separate Block, Clean Margin, No Overlap) -->
                                            <div class="notes-container mb-2.5">
                                                <div class="flex items-center gap-2 mb-1">
                                                    <span
                                                        class="inline-block bg-red-600 text-white text-[8px] font-black uppercase tracking-wider px-2.5 py-0.5 rounded shadow-sm">
                                                        Catatan
                                                    </span>
                                                    <div class="h-0.5 flex-1 bg-red-100 rounded-full"></div>
                                                </div>
                                                <div
                                                    class="notes-box bg-neutral-50/60 border border-neutral-200 rounded-lg p-2 text-[9px] font-bold text-neutral-800 break-words whitespace-pre-wrap leading-tight"
                                                    style="word-break: break-word;">
                                                    {{ transaction.notes || transaction.reason || 'Tidak ada catatan tambahan.' }}
                                                </div>
                                            </div>

                                            <!-- Syarat & Ketentuan (Warranty) -->
                                            <div class="warranty-container">
                                                <div class="flex items-center gap-2 mb-1">
                                                    <span
                                                        class="text-[9px] font-black text-red-600 tracking-wider uppercase">Syarat
                                                        & Ketentuan</span>
                                                    <div class="h-0.5 w-8 bg-red-200 rounded-full"></div>
                                                </div>
                                                <div v-if="displayWarranty"
                                                    class="text-[8px] text-neutral-600 font-bold whitespace-pre-line leading-relaxed">
                                                    {{ displayWarranty }}
                                                </div>
                                                <ul v-else
                                                    class="text-[8px] text-neutral-600 font-bold space-y-0.5 list-disc pl-4 leading-relaxed">
                                                    <li>Garansi unit selama 1 bulan terhitung sejak tanggal nota.</li>
                                                    <li>Garansi yang sudah lewat batas tanggal tidak dapat diklaim garansi.</li>
                                                    <li>Segel wajib utuh. Kerusakan akibat human error membatalkan garansi.</li>
                                                </ul>
                                            </div>
                                        </div>

                                        <!-- Right Column: Financial Details & Slanted Total -->
                                        <div class="w-full md:w-[240px] shrink-0">
                                            <!-- Small Subtotal Breakdown -->
                                            <div
                                                class="bg-neutral-50/80 rounded-xl border border-neutral-200 p-2.5 space-y-1.5 text-xs mb-2">
                                                <div class="flex justify-between">
                                                    <span class="font-bold text-neutral-500 text-[9px]">Sub Total</span>
                                                    <span class="text-neutral-900 font-bold text-[10px]">
                                                        {{ formatCurrency(Math.abs(transaction.original_price ||
                                                            (Number(transaction.selling_price || 0) +
                                                                Number(transaction.global_discount_value || 0)))) }}
                                                    </span>
                                                </div>
                                                <div v-if="Number(transaction.global_discount_value) > 0 && !hasBundleAbsorbedGlobalDiscount" class="flex justify-between">
                                                    <span class="font-bold text-neutral-500 text-[9px]">Diskon (Nota)</span>
                                                    <span class="text-red-600 font-bold text-[10px]">-{{
                                                        formatCurrency(transaction.global_discount_value) }}</span>
                                                </div>
                                                <div v-for="(payment, idx) in transaction.split_payments_data || []"
                                                    :key="idx"
                                                    class="flex justify-between border-t border-neutral-200/50 pt-1">
                                                    <span class="font-bold text-neutral-500 text-[9px] uppercase">{{
                                                        payment.method_name }}</span>
                                                    <span class="text-neutral-900 font-bold text-[10px]">{{
                                                        formatCurrency(payment.amount)
                                                        }}</span>
                                                </div>

                                                <!-- Slanted or Red Divider Line -->
                                                <div
                                                    class="border-t border-neutral-300 border-dashed pt-1 flex justify-between">
                                                    <span
                                                        class="font-black text-red-600 text-[9px] uppercase tracking-wider">Kembalian</span>
                                                    <span class="text-red-600 font-black text-xs">
                                                        {{ formatCurrency(calculatedChange) }}
                                                    </span>
                                                </div>
                                            </div>

                                            <!-- Exact Slanted Grand Total Header Box -->
                                            <div
                                                class="flex rounded-xl overflow-hidden shadow-md h-[48px] bg-red-600 w-full relative">
                                                <!-- Black Trapezoid Part -->
                                                <div class="bg-neutral-950 text-white pl-3 pr-6 flex flex-col justify-center shrink-0 select-none pointer-events-none"
                                                    style="clip-path: polygon(0 0, 100% 0, 82% 100%, 0% 100%); z-index: 2;">
                                                    <div
                                                        class="text-[7.5px] font-black uppercase tracking-wider leading-none text-white">
                                                        {{ (transaction.category === 'refund' || transaction.category === 'refund_dp') ? 'Total' : 'Yang Harus' }}</div>
                                                    <div
                                                        class="text-[7.5px] font-black uppercase tracking-wider leading-tight text-white">
                                                        {{ (transaction.category === 'refund' || transaction.category === 'refund_dp') ? 'Dikembalikan' : 'Dibayarkan' }}</div>
                                                </div>
                                                <!-- Red Total Part -->
                                                <div class="flex-1 flex items-center justify-end pr-3 text-white"
                                                    style="z-index: 1;">
                                                    <span class="text-base sm:text-lg font-black text-white tracking-tight">{{
                                                        formatCurrency(Math.abs(calculatedGrandTotal)) }}</span>
                                                </div>
                                            </div>

                                            <div class="text-[8px] text-right text-neutral-400 italic mt-1 font-bold">
                                                Metode: {{ transaction.split_payments_data?.length > 1 ? 'SPLIT (CAMPURAN)'
                                                    :
                                                    (transaction.payment_method_name || transaction.payment_method || '-') }}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Spacer 4: Flexible distribution -->
                                <div class="nota-spacer"></div>

                                <!-- 5. SIGNATURE AREA (Clean Bottom Placement) -->
                                <div class="nota-section signature-section shrink-0">
                                    <div
                                        class="signature-area grid grid-cols-2 text-[10px] gap-4 border border-neutral-100 rounded-xl py-3 bg-white/60 backdrop-blur-[1px]">
                                        <div class="text-center border-r border-neutral-200">
                                            <div class="text-[8.5px] font-black text-red-600 uppercase tracking-widest mb-10">
                                                Customer / Pembeli</div>
                                            <div class="border-b border-neutral-400 w-full max-w-[130px] mx-auto mb-1">
                                            </div>
                                            <div class="text-[10px] font-black text-neutral-900 uppercase">
                                                {{ transaction.customer_name || 'Umum' }}
                                            </div>
                                        </div>
                                        <div class="text-center">
                                            <div class="text-[8.5px] font-black text-red-600 uppercase tracking-widest mb-10">
                                                Hormat Kami</div>
                                            <div class="border-b border-neutral-400 w-full max-w-[130px] mx-auto mb-1">
                                            </div>
                                            <div class="text-[10px] font-black text-neutral-900 uppercase">
                                                {{ transaction.inventory_user_name || transaction.inventory_account_name ||
                                                    transaction.sales_account ||
                                                    transaction.sales_name || 'PSTORE' }}
                                            </div>
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
                    <div class="p-4 bg-white dark:bg-surface-800 border-t border-gray-100 dark:border-surface-700 print:hidden shrink-0">
                        <!-- Action Buttons Row -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-3">
                            <button @click="emit('open-screenshot')"
                                class="px-3 py-3 text-[11px] font-bold rounded-2xl bg-violet-50 dark:bg-violet-900/20 text-violet-700 dark:text-violet-300 hover:bg-violet-100 dark:hover:bg-violet-900/40 transition-all flex flex-col items-center justify-center gap-1.5 active:scale-95 border border-violet-200 dark:border-violet-800">
                                <MessageSquare :size="16" />
                                <span>Laporkan Nota</span>
                            </button>
                            <button @click="sendWaReceiptFromModal" :disabled="isGeneratingPDF"
                                class="px-3 py-3 text-[11px] font-bold rounded-2xl bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-100 dark:hover:bg-emerald-900/40 transition-all flex flex-col items-center justify-center gap-1.5 active:scale-95 border border-emerald-200 dark:border-emerald-800 disabled:opacity-50">
                                <Loader2 v-if="isGeneratingPDF" class="animate-spin" :size="16" />
                                <svg v-else xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 2C6.477 2 2 6.477 2 12c0 1.89.525 3.66 1.438 5.168L2 22l4.832-1.438A9.955 9.955 0 0012 22c5.523 0 10-4.477 10-10S17.523 2 12 2z"/></svg>
                                <span>Kirim WhatsApp</span>
                            </button>
                            <button type="button" @click="printReceipt"
                                class="px-3 py-3 text-[11px] font-bold rounded-2xl bg-surface-100 dark:bg-surface-700 text-text-primary hover:bg-surface-200 dark:hover:bg-surface-600 transition-all flex flex-col items-center justify-center gap-1.5 active:scale-95 border border-surface-200 dark:border-surface-600 cursor-pointer w-full">
                                <Printer :size="16" class="pointer-events-none" />
                                <span class="pointer-events-none">Cetak Nota</span>
                            </button>
                            <button @click="close"
                                class="px-3 py-3 text-[11px] font-bold rounded-2xl bg-primary-600 text-white hover:bg-primary-700 transition-all flex flex-col items-center justify-center gap-1.5 active:scale-95 shadow-lg shadow-primary-500/20">
                                <X :size="16" />
                                <span>Selesai</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </Teleport>
</template>

<script setup>
import { defineProps, defineEmits, ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue';
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

const emit = defineEmits(['close', 'open-checklist', 'sent', 'open-screenshot']);

const close = () => {
    emit('close');
};

const isGeneratingPDF = ref(false);
const paperSize = ref('A5');
const notaPaperRef = ref(null);
const notaInnerRef = ref(null);

const autoFitA5 = () => {
    if (!notaPaperRef.value || !notaInnerRef.value) return;
    const page = notaPaperRef.value;
    const inner = notaInnerRef.value;

    // Reset zoom and classes to get clean unscaled measurement
    inner.style.zoom = '1';
    inner.classList.remove('is-tight');

    // Available printable height on A5 paper (210mm total - 12mm padding = 198mm)
    // CSS standard: 1in = 25.4mm = 96px => 1mm = 96 / 25.4 ≈ 3.779527559px
    // 198mm ≈ 748.346px
    const a5AvailablePx = (210 - 12) * (96 / 25.4);
    const computedStyle = window.getComputedStyle(page);
    const paddingTop = parseFloat(computedStyle.paddingTop) || 0;
    const paddingBottom = parseFloat(computedStyle.paddingBottom) || 0;
    let avail = page.clientHeight - paddingTop - paddingBottom;
    if (avail <= 150 || avail > 1200) {
        avail = a5AvailablePx;
    }

    // Measure needed content height with spacers in minimum collapsed condition
    inner.classList.add('is-measuring');
    const needed = inner.scrollHeight;
    inner.classList.remove('is-measuring');

    // Mode 1: Content fits comfortably (< 1 A5 page)
    // Keep scale = 1.0, let flex spacers distribute the remaining space evenly across sections.
    if (needed <= avail) {
        inner.style.zoom = '1';
        return;
    }

    // Mode 2: Content overflows (> 1 A5 page)
    // Calculate required scale down to minimum 0.7 to ensure everything fits on 1 sheet.
    let scale = Math.max(0.7, avail / needed);
    inner.style.zoom = String(scale);

    // If scale reaches 0.75 or still tight, apply .is-tight for compact table row padding and gaps
    if (scale <= 0.75 || (needed * scale) > avail) {
        inner.classList.add('is-tight');

        inner.classList.add('is-measuring');
        const tightNeeded = inner.scrollHeight;
        inner.classList.remove('is-measuring');

        const tightScale = Math.max(0.7, avail / tightNeeded);
        inner.style.zoom = String(tightScale);
    }
};

const resetFit = () => {
    if (!notaInnerRef.value) return;
    notaInnerRef.value.style.zoom = '1';
    notaInnerRef.value.classList.remove('is-tight');
};

onMounted(() => {
    window.addEventListener('beforeprint', autoFitA5);
    window.addEventListener('afterprint', resetFit);
});

onUnmounted(() => {
    window.removeEventListener('beforeprint', autoFitA5);
    window.removeEventListener('afterprint', resetFit);
});

watch(() => props.isOpen, async (newVal) => {
    if (newVal) {
        await nextTick();
        autoFitA5();
        if (props.autoSend) {
            setTimeout(() => {
                sendWaReceiptFromModal();
            }, 500);
        }
    }
});

watch(() => props.transaction, async () => {
    if (props.isOpen) {
        await nextTick();
        autoFitA5();
    }
}, { deep: true });

const sendWaReceiptFromModal = async () => {
    // Buka tab baru kosong lebih dulu secara sinkron agar tidak diblokir oleh Safari Popup Blocker
    let newWindow = null;
    try {
        newWindow = window.open('', '_blank');
        if (newWindow) {
            newWindow.document.title = "Menyiapkan WhatsApp...";
            newWindow.document.body.innerHTML = `
                <div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; text-align: center; margin-top: 80px; color: #1f2937; padding: 20px; max-width: 400px; margin-left: auto; margin-right: auto;">
                    <div style="margin-bottom: 24px;">
                        <img src="${window.location.origin}/images/ps.png" alt="PSTORE" style="width: 80px; height: 80px; object-fit: contain;" onerror="this.style.display='none';" />
                    </div>
                    <h2 style="font-size: 20px; font-weight: 800; color: #dc2626; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;">Menyiapkan WhatsApp...</h2>
                    <p style="font-size: 14px; color: #4b5563; line-height: 1.5; margin-bottom: 24px;">Sedang memproses dan mengunggah nota PDF Kakak ke Google Drive. Anda akan diarahkan ke WhatsApp secara otomatis.</p>
                    <div style="margin: 20px auto; width: 40px; height: 40px; border: 4px solid #f3f3f3; border-top: 4px solid #dc2626; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                </div>
                <style>
                    @keyframes spin {
                        0% { transform: rotate(0deg); }
                        100% { transform: rotate(360deg); }
                    }
                </style>
            `;
        }
    } catch (e) {
        console.warn("Failed to pre-open window (likely autoSend trigger):", e);
    }

    isGeneratingPDF.value = true;
    try {
        const element = document.querySelector('.nota-paper');
        if (!element) {
            if (newWindow) newWindow.close();
            alert('Elemen nota tidak ditemukan!');
            return;
        }

        // 1. Kloning elemen nota utama
        const cloned = element.cloneNode(true);

        // 2. Bersihkan tombol-tombol yang tidak perlu ikut di PDF
        cloned.querySelectorAll('.print\\:hidden').forEach(el => el.remove());

        // Convert local images (like logos) to Base64
        const images = cloned.querySelectorAll('img');
        for (const img of images) {
            const src = img.getAttribute('src') || '';
            if (src && (src.startsWith('/') || src.startsWith(window.location.origin))) {
                try {
                    const absoluteUrl = src.startsWith('/') ? (window.location.origin + src) : src;
                    const res = await fetch(absoluteUrl);
                    const blob = await res.blob();
                    const base64Data = await new Promise((resolve, reject) => {
                        const reader = new FileReader();
                        reader.onloadend = () => resolve(reader.result);
                        reader.onerror = reject;
                        reader.readAsDataURL(blob);
                    });
                    img.src = base64Data;
                } catch (err) {
                    console.error('Failed to convert image to base64:', src, err);
                    if (src.startsWith('/')) {
                        img.src = window.location.origin + src;
                    }
                }
            }
        }

        // 3. Ambil semua style aktif (Tailwind / CSS internal) dari document
        let compiledStyles = '';
        const styleElements = document.querySelectorAll('style, link[rel="stylesheet"]');
        for (const el of styleElements) {
            if (el.tagName.toLowerCase() === 'style') {
                compiledStyles += el.outerHTML;
            } else if (el.tagName.toLowerCase() === 'link') {
                const href = el.getAttribute('href') || '';
                const isLocal = href.startsWith('/') || href.startsWith(window.location.origin) || (!href.startsWith('http://') && !href.startsWith('https://'));
                if (isLocal) {
                    try {
                        const res = await fetch(el.href);
                        const cssText = await res.text();
                        compiledStyles += `<style>\n${cssText}\n</style>`;
                    } catch (e) {
                        console.error('Failed to inline stylesheet:', el.href);
                    }
                }
            }
        }

        // 4. Susun struktur HTML khusus untuk PDF Engine
        const htmlContent = `
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Nota Pembelian</title>
    ${compiledStyles}
    <style>
        @page { margin: 0; size: A4 portrait; }
        html, body {
            font-family: 'Inter', sans-serif;
            margin: 0 !important;
            padding: 0 !important;
            background: #ffffff;
            color: #0a0a0a;
            width: 100% !important;
            min-height: 297mm !important;
        }
        #receipt-modal-print-wrapper {
            width: 210mm !important;
            min-height: 297mm !important;
            margin: 0 auto !important;
            padding: 0 !important;
            background: #ffffff !important;
            overflow: visible !important;
        }
        .nota-paper {
            width: 210mm !important;
            max-width: 210mm !important;
            min-height: 297mm !important;
            margin: 0 auto !important;
            padding: 0 0 12mm !important;
            border-radius: 0 !important;
            box-sizing: border-box !important;
            box-shadow: none !important;
            display: flex !important;
            flex-direction: column !important;
            overflow: hidden !important;
        }
        .nota-paper > .relative.z-10 {
            flex: 1 1 auto !important;
            width: calc(100% - 24mm) !important;
            min-height: calc(297mm - 12mm) !important;
            margin-left: auto !important;
            margin-right: auto !important;
            display: flex !important;
            flex-direction: column !important;
        }
        .nota-paper > .absolute.top-0.left-0 {
            left: 0 !important;
            top: 0 !important;
        }
        .nota-paper > .absolute.top-0.right-0 {
            right: 0 !important;
            top: 0 !important;
        }
        .bg-red-600 { background-color: #dc2626 !important; }
        .text-red-600 { color: #dc2626 !important; }
        .bg-neutral-950, .bg-black { background-color: #0a0a0a !important; }
        .text-neutral-950, .text-neutral-900, .text-black { color: #0a0a0a !important; }
        .text-white { color: #ffffff !important; }
        .bg-white { background-color: #ffffff !important; }
        .border-neutral-200 { border-color: #e5e7eb !important; border-width: 1px !important; border-style: solid !important; }
        th { background-color: #0a0a0a !important; color: #ffffff !important; }
        .flex.flex-col.md\\:flex-row { display: table !important; width: 100% !important; table-layout: fixed !important; margin-top: 22px !important; margin-bottom: 0 !important; }
        .flex.flex-col.md\\:flex-row > div:first-child { display: table-cell !important; width: 58% !important; vertical-align: top !important; padding-right: 20px !important; }
        .flex.flex-col.md\\:flex-row > div:last-child { display: table-cell !important; width: 42% !important; vertical-align: top !important; }
        .signature-area.grid.grid-cols-2 { display: table !important; width: 100% !important; table-layout: fixed !important; margin-top: 20mm !important; margin-bottom: 0 !important; }
        .signature-area.grid.grid-cols-2 > div { display: table-cell !important; width: 50% !important; vertical-align: top !important; }
        .nota-paper .flex.items-center.gap-5.mb-4.mt-6.px-6 {
            margin-top: 11mm !important;
            margin-bottom: 4mm !important;
            padding-left: 4mm !important;
            padding-right: 4mm !important;
        }
        .nota-paper .mb-6 { margin-bottom: 1.1rem !important; }
        .nota-paper .mb-5 { margin-bottom: 1rem !important; }
        .nota-paper .mb-4 { margin-bottom: 0.85rem !important; }
        .nota-paper .mt-6 { margin-top: 1.1rem !important; }
        .nota-paper .gap-6 { gap: 1.2rem !important; }
        .nota-paper .gap-4 { gap: 0.85rem !important; }
        .nota-paper .py-4 { padding-top: 0.65rem !important; padding-bottom: 0.65rem !important; }
        .nota-paper .py-3 { padding-top: 0.5rem !important; padding-bottom: 0.5rem !important; }
        .nota-paper .p-4 { padding: 0.9rem !important; }
        .nota-paper .p-6 { padding: 1.1rem !important; }
        .nota-paper .px-6 { padding-left: 1.2rem !important; padding-right: 1.2rem !important; }
        .nota-paper, .nota-paper * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            color-adjust: exact !important;
            text-shadow: none !important;
            filter: none !important;
            -webkit-filter: none !important;
            backdrop-filter: none !important;
            -webkit-backdrop-filter: none !important;
        }
    </style>
</head>
<body>
    <div id="receipt-modal-print-wrapper">
        <div class="nota-paper">
            ${cloned.innerHTML}
        </div>
    </div>
</body>
</html>`;

        // 5. Kirim payload HTML ke backend
        const txId = props.transaction.id || props.transaction.order_no || props.transaction.receipt_id;
        const response = await api.post(`/receipts/${txId}/share-wa`, {
            htmlContent: htmlContent
        });

        if (response.data && response.data.success) {
            // Menggunakan Hardcoded Percent-Encoding (ASCII Murni)
            // KEBAL terhadap kerusakan FTP / File Encoding / VPS.
            const wave = "%F0%9F%91%8B%F0%9F%8F%BB";   // 👋🏻
            const pray = "%F0%9F%A4%B2%F0%9F%8F%BB";   // 🤲🏻
            const pin = "%F0%9F%93%8C";                // 📌
            const heart = "%F0%9F%AB%B6%F0%9F%8F%BB";  // 🫶🏻
            const folded = "%F0%9F%99%8F%F0%9F%8F%BB"; // 🙏🏻

            const customerName = props.transaction.customer_name || 'Pelanggan';
            const branchObj = props.transaction.branch || props.transaction.destinationBranch || authStore.userBranch || {};
            const branchName = props.transaction.branch_name || branchObj.name || 'CABANG';
            const displayBranch = branchName ? `PSTORE ${branchName.toUpperCase().replace('PSTORE ', '').replace('PSTORE', '')}` : 'PSTORE';

            // Susun textParam menggunakan variabel emoji yang sudah aman ter-encode
            let textParam = encodeURIComponent(`Halo Kak *${customerName}* `) + wave + encodeURIComponent(`\n\n`);
            textParam += encodeURIComponent(`Terima kasih banyak ya Kak sudah berbelanja di *${displayBranch}*!\n\n`);
            textParam += encodeURIComponent(`Kami sangat senang bisa melayani Kakak. Semoga produknya awet, berkah, dan bermanfaat yaa `) + pray + encodeURIComponent(`\n\n`);
            textParam += encodeURIComponent(`Berikut adalah link resmi Google Drive untuk mengunduh Nota Pembelian (PDF) Kakak:\n`);
            textParam += pin + encodeURIComponent(` ${response.data.drive_link}\n\n`);
            textParam += encodeURIComponent(`*Penting:* \n`);
            textParam += encodeURIComponent(`Jangan lupa untuk menyimpan (save) nomor WhatsApp toko kami ini ya Kak, untuk mempermudah klaim garansi atau untuk mendapatkan promo menarik kami ke depannya `) + heart + encodeURIComponent(`\n\n`);
            textParam += encodeURIComponent(`Sehat dan sukses selalu untuk Kakak sekeluarga! Terima kasih! `) + folded;

            let cleanPhone = (props.transaction.customer_phone || props.transaction.customer_wa || props.transaction.shopee_phone || '').toString().replace(/\D/g, '');
            if (cleanPhone.startsWith('0')) cleanPhone = '62' + cleanPhone.substring(1);

            const waUrl = `https://api.whatsapp.com/send?phone=${cleanPhone}&text=${textParam}`;

            if (newWindow) {
                newWindow.location.href = waUrl;
            } else {
                window.open(waUrl, '_blank');
            }
            emit('sent');
            if (props.autoSend) {
                close();
            }
        } else {
            if (newWindow) newWindow.close();
            alert('Gagal membagikan nota: ' + (response.data?.error || 'Kesalahan tidak dikenal'));
        }
    } catch (error) {
        if (newWindow) newWindow.close();
        console.error('Error sharing receipt:', error);
        alert('Terjadi kesalahan saat membagikan nota: ' + (error.response?.data?.error || error.message || 'Kesalahan sistem'));
    } finally {
        isGeneratingPDF.value = false;
    }
};

const printReceipt = () => {
    // 1. Dapatkan atau siapkan style element untuk ukuran halaman cetak A5
    const styleId = 'dynamic-print-page-size';
    let styleEl = document.getElementById(styleId);
    if (!styleEl) {
        styleEl = document.createElement('style');
        styleEl.id = styleId;
        document.head.appendChild(styleEl);
    }
    
    // STRICTLY ONLY A5 PORTRAIT (1 lembar pas)
    styleEl.textContent = `
        @media print {
            @page { size: A5 portrait; margin: 0 !important; }
        }
    `;

    // 2. Fit konten secara optimal ke 1 lembar A5 sebelum cetak
    autoFitA5();

    // 3. Bersihkan judul dokumen sementara agar browser header/footer tidak tercetak
    const originalTitle = document.title;
    document.title = ' ';

    // 4. Panggil window.print()
    window.print();

    // 5. Kembalikan judul dokumen
    setTimeout(() => {
        document.title = originalTitle;
    }, 1000);
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
        'penjualan_online_toko': 'Nota Penjualan Online Toko',
        'penjualan': 'Nota Penjualan',
        'penjualan_offline': 'Nota Penjualan',
        'refund': 'Nota Refund',
        'tukar_tambah': 'Nota Tukar Tambah',
        'tukar_unit': 'Nota Tukar Unit',
        'angkat_barang': 'Nota Angkat Barang',
        'downgrade': 'Nota Downgrade',
        'shopee': 'Nota Shopee',
        'orderan_online': 'Nota Order Online',
        'balancing': 'Nota Balancing',
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

const hasBundleAbsorbedGlobalDiscount = computed(() => {
    return processedReceiptItems.value.some(item => item.is_bundle_header && item.absorbedGlobalDiscount);
});

const hasAnyItemDiscount = computed(() => {
    return processedReceiptItems.value.some(item => (item.itemDiscount || 0) > 0);
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

// Tracking / Resi Number
const displayTrackingNo = computed(() => {
    const t = props.transaction?.tracking_no || props.transaction?.shopee_tracking_no || props.transaction?.expedition_tracking_no;
    return (t && String(t).trim()) ? String(t).trim() : null;
});

// Shipping Destination Address
const displayShippingAddress = computed(() => {
    const addr = props.transaction?.address || props.transaction?.shopee_address;
    const prov = props.transaction?.province || props.transaction?.shopee_province;
    const city = props.transaction?.city || props.transaction?.shopee_city;

    const parts = [];
    if (addr && String(addr).trim()) parts.push(String(addr).trim());
    if (city && String(city).trim()) parts.push(String(city).trim());
    if (prov && String(prov).trim()) parts.push(String(prov).trim());

    return parts.length > 0 ? parts.join(', ') : null;
});

// Date
const displayDate = computed(() => {
    let rawDate = props.transaction.created_at || props.transaction.date;
    if (!rawDate) return '-';

    if (typeof rawDate === 'string' && /^[0-9]{2} [A-Za-z]{3,4} [0-9]{4}$/.test(rawDate)) {
        return rawDate;
    }

    // Safely parse backend timestamp which might be in "YYYY-MM-DD HH:mm:ss" format (WIB)
    let safeDateStr = rawDate;
    if (typeof rawDate === 'string' && /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(rawDate)) {
        safeDateStr = rawDate.replace(' ', 'T') + '+07:00';
    }

    const dateObj = new Date(safeDateStr);
    if (!isNaN(dateObj.getTime())) {
        const options = {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        };

        const tz = props.transaction.branch_timezone || props.transaction.branch?.timezone || props.transaction.user?.branch?.timezone || null;
        if (tz) {
            const tzMap = {
                'WIB': 'Asia/Jakarta',
                'WITA': 'Asia/Makassar',
                'WIT': 'Asia/Jayapura',
                'ASIA/JAKARTA': 'Asia/Jakarta',
                'ASIA/MAKASSAR': 'Asia/Makassar',
                'ASIA/JAYAPURA': 'Asia/Jayapura'
            };
            options.timeZone = tzMap[tz.toUpperCase()] || tz;
        }

        let formatted = dateObj.toLocaleString("id-ID", options).replace(/\./g, ':');
        if (tz) {
            formatted += ` ${tz.toUpperCase()}`;
        }
        return formatted;
    }

    if (props.transaction.time) {
        return `${rawDate}, ${String(props.transaction.time).slice(0, 5).replace(/\./g, ':')}`;
    }

    return rawDate;
});

// Consolidate all items for display
const allReceiptItems = computed(() => {
    const list = [];

    // 1. Add HP Items
    if (props.transaction.items?.length > 0) {
        props.transaction.items.forEach(it => {
            const isHp = it.is_hp !== undefined ? Boolean(it.is_hp) : (!!it.imei && it.imei !== '-');
            list.push({
                ...it,
                qty: it.qty || it.quantity || 1,
                is_hp: isHp
            });
        });
    }

    // 2. Add Non-HP Items
    const nonHpSource = props.transaction.non_hp_items || props.transaction.non_hp_details || props.transaction.nonHpItems;

    if (nonHpSource?.length > 0) {
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

    // 3. Add DP Deduction for Pelunasan DP
    if (props.transaction.category === 'pelunasan_dp') {
        const itemsTotal = list.reduce((sum, item) => {
            const p = Number(item.original_price ?? item.pivot?.selling_price ?? item.selling_price ?? item.price ?? 0);
            return sum + (p * Number(item.qty || 1));
        }, 0);
        const gTotal = Number(props.transaction.grand_total || props.transaction.total || 0);
        const tDiscount = Number(props.transaction.total_discount || 0);
        
        // DP Deduction = Harga Barang (itemsTotal) - Diskon - Sisa Pelunasan (gTotal)
        const dpAmount = itemsTotal - tDiscount - gTotal;
        
        if (dpAmount > 0) {
            let dpLabel = 'DP';
            const custName = props.transaction.customer_name || 'Umum';
            let dpDateStr = '';
            
            const noteStr = props.transaction.notes || '';
            const dateMatch = noteStr.match(/Nota:\s*[A-Z]?(\d{2})([A-Z]{3})/i);
            if (dateMatch) {
                dpDateStr = dateMatch[1] + ' ' + dateMatch[2].toUpperCase();
            }

            dpLabel = `DP : ${custName}${dpDateStr ? ' ' + dpDateStr : ''}`;

            list.push({
                name: dpLabel,
                imei: '-',
                price: -Math.abs(dpAmount),
                original_price: -Math.abs(dpAmount),
                selling_price: -Math.abs(dpAmount),
                qty: 1,
                is_hp: false,
                is_dp_deduction: true
            });
        }
    }

    return list;
});

// Transforms list to group bundle items into a clean "Header Row" (with total price) + "Child Bullet Rows"
const processedReceiptItems = computed(() => {
    const baseList = allReceiptItems.value;
    const newList = [];
    const seenBundleKeys = new Set();

    baseList.forEach((it) => {
        const rawNotes = (it.notes || it.pivot?.notes || it.pivot_notes || '').toLowerCase();
        const isBundle = rawNotes.includes('paket') || rawNotes.includes('bundle');
        const groupKey = it.notes || it.pivot?.notes || it.pivot_notes;

        if (!isBundle) {
            const originalPrice = it.original_price ?? it.pivot?.selling_price ?? it.price ?? it.selling_price ?? 0;
            const itemDiscount = it.item_discount ?? it.pivot?.item_discount ?? it.discount ?? 0;
            const finalTotal = Math.abs(originalPrice - itemDiscount) * (it.qty || 1);

            newList.push({ 
                ...it, 
                is_bundle_header: false, 
                is_bundle_child: false, 
                show_bottom_border: true,
                originalPrice: Number(originalPrice),
                itemDiscount: Number(itemDiscount),
                finalTotal: Number(finalTotal)
            });
        } else {
            // Only process this bundle group the FIRST time its group key is encountered to keep it unified
            if (!seenBundleKeys.has(groupKey)) {
                seenBundleKeys.add(groupKey);

                // 1. Collect all child component items belonging to this specific bundle
                const groupItems = baseList.filter(item => {
                    const itemKey = item.notes || item.pivot?.notes || item.pivot_notes || '';
                    return itemKey === groupKey;
                });

                // 2. Sum final selling prices of all child components
                let groupTotal = 0;
                let groupOriginalTotal = 0;
                let groupDiscountTotal = 0;
                
                groupItems.forEach((child) => {
                    const originalPrice = child.pivot?.selling_price || child.price || child.selling_price || 0;
                    const discount = child.pivot?.item_discount || child.discount || child.item_discount || 0;
                    const qty = child.qty || 1;
                    
                    groupOriginalTotal += (originalPrice * qty);
                    groupDiscountTotal += (discount * qty);
                    groupTotal += (Math.abs(originalPrice - discount) * qty);
                });

                // 3. Construct a unified, clean descriptive name for the bundle header
                let bundleDisplayName = groupKey
                    .replace(/paket bundling:/gi, 'Paket Bundling:')
                    .replace(/paket bundling/gi, 'Paket Promo')
                    .replace(/paket promo:/gi, 'Paket Promo:')
                    .replace('📦 ', '')
                    .trim();

                if (!bundleDisplayName.toLowerCase().includes('paket')) {
                    bundleDisplayName = 'Paket Promo: ' + bundleDisplayName;
                }

                // ABSORB GLOBAL DISCOUNT INTO BUNDLE
                const globalDisc = Number(props.transaction?.global_discount_value || 0);
                let absorbed = false;
                if (globalDisc > 0 && !seenBundleKeys.hasAbsorbedGlobal) {
                    groupDiscountTotal += globalDisc;
                    groupTotal -= globalDisc;
                    seenBundleKeys.hasAbsorbedGlobal = true;
                    absorbed = true;
                }

                // 4. Push the HEADER ROW which displays the consolidated price
                newList.push({
                    is_hp: false,
                    is_bundle_header: true,
                    is_bundle_child: false,
                    name: bundleDisplayName,
                    price: groupTotal,
                    originalPrice: groupOriginalTotal,
                    itemDiscount: groupDiscountTotal,
                    absorbedGlobalDiscount: absorbed,
                    qty: 1,
                    imei: '-',
                    notes: groupKey,
                    show_bottom_border: false
                });

                // 5. Push the INDIVIDUAL COMPONENT ROWS underneath it (with prices hidden)
                groupItems.forEach((child, childIdx) => {
                    newList.push({
                        ...child,
                        is_bundle_header: false,
                        is_bundle_child: true,
                        _hidePrice: true,
                        show_bottom_border: childIdx === groupItems.length - 1
                    });
                });
            }
        }
    });

    return newList;
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
.nota-paper h3 {
    color: #e11d48 !important;
}

.nota-paper thead tr {
    background-color: #0a0a0a !important;
}

.nota-paper thead th {
    background-color: #0a0a0a !important;
    color: #ffffff !important;
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

.nota-spacer {
    min-height: 8px;
}
</style>

<style>
/* UNSCOPED PRINT CSS - RESPONSIVE STRICT A5 1-PAGE GUARANTEE */
@media print {
    @page {
        size: A5 portrait;
        margin: 0 !important;
    }

    /* Robustly hide ALL other elements at the root body level except the print wrapper */
    body > :not(#receipt-modal-print-wrapper):not(#transfer-receipt-modal-wrapper) {
        display: none !important;
    }

    html,
    body {
        width: 148mm !important;
        height: 210mm !important;
        max-height: 210mm !important;
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
        overflow: hidden !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color-adjust: exact !important;
    }

    /* COMPLETE PARENT RESET: Strict A5 Page Box */
    #receipt-modal-print-wrapper,
    #receipt-modal-print-wrapper > div,
    #receipt-content {
        display: block !important;
        position: relative !important;
        left: 0 !important;
        top: 0 !important;
        width: 148mm !important;
        max-width: 148mm !important;
        height: 210mm !important;
        max-height: 210mm !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: hidden !important;
        box-sizing: border-box !important;
        background: white !important;
        border: none !important;
        box-shadow: none !important;
        z-index: 9999999 !important;
        transform: none !important;
    }

    /* A5 NOTA PAGE CONTAINER: 148mm x 210mm, padding 6mm, zero scroll, 1 page strict */
    .nota-paper,
    .nota-page {
        display: block !important;
        position: relative !important;
        width: 148mm !important;
        max-width: 148mm !important;
        height: 210mm !important;
        max-height: 210mm !important;
        padding: 6mm !important;
        margin: 0 !important;
        box-sizing: border-box !important;
        overflow: hidden !important;
        break-inside: avoid !important;
        page-break-inside: avoid !important;
        page-break-after: avoid !important;
        break-after: avoid !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color-adjust: exact !important;
        background: white !important;
        border: none !important;
        box-shadow: none !important;
        border-radius: 0 !important;
        color: #0f172a !important;
    }

    /* Watermark: background layer only, zero layout displacement */
    .watermark-container {
        position: absolute !important;
        inset: 0 !important;
        width: 100% !important;
        height: 100% !important;
        pointer-events: none !important;
        z-index: 0 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        overflow: hidden !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    .watermark-container img {
        width: 200px !important;
        height: 200px !important;
        max-width: 200px !important;
        max-height: 200px !important;
        object-fit: contain !important;
        opacity: 0.04 !important;
        pointer-events: none !important;
        transform: none !important;
    }

    /* Corner Accents */
    .corner-accent-tl,
    .corner-accent-tr {
        width: 3.25rem !important;
        height: 3.25rem !important;
        z-index: 20 !important;
    }
    .corner-accent-tl svg,
    .corner-accent-tr svg {
        width: 100% !important;
        height: 100% !important;
    }

    /* Inner flex column: 100% height of printable area (198mm) */
    .nota-inner {
        display: flex !important;
        flex-direction: column !important;
        height: 100% !important;
        width: 100% !important;
        box-sizing: border-box !important;
        position: relative !important;
        z-index: 1 !important;
        transform-origin: top left !important;
    }

    /* Measurement state: collapse height and spacers to accurately calculate minimum needed height */
    .nota-inner.is-measuring {
        height: auto !important;
    }
    .nota-inner.is-measuring .nota-spacer {
        flex-grow: 0 !important;
        flex-shrink: 0 !important;
        height: 2mm !important;
        min-height: 2mm !important;
    }

    /* Flexible spacers: naturally distribute excess space evenly between sections */
    .nota-spacer {
        flex: 1 1 0 !important;
        min-height: 2mm !important;
    }

    /* Logical sections shrink-0 so they don't get squashed unproportionately */
    .header-section,
    .metadata-section,
    .items-table-section,
    .summary-section,
    .signature-section {
        flex-shrink: 0 !important;
    }

    /* Items table styling in print */
    .items-table-wrapper {
        border-radius: 6px !important;
        overflow: visible !important;
        border-color: #e5e7eb !important;
        background: white !important;
    }
    .items-table-wrapper table {
        border-collapse: collapse !important;
        width: 100% !important;
    }
    .items-table-wrapper th {
        background-color: #0a0a0a !important;
        color: #ffffff !important;
        border-color: #0a0a0a !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    /* Notes Box: clear wrapping, no overlap */
    .notes-box {
        word-break: break-word !important;
        background-color: rgba(250, 250, 250, 0.7) !important;
        border-color: #e5e7eb !important;
    }

    /* Total box styling */
    .bg-red-600 {
        background-color: #dc2626 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    /* Tight mode: only applied when scale <= 0.75 for heavy item counts */
    .nota-inner.is-tight .nota-spacer {
        min-height: 1mm !important;
        height: 1mm !important;
    }
    .nota-inner.is-tight th,
    .nota-inner.is-tight td {
        padding-top: 2px !important;
        padding-bottom: 2px !important;
        padding-left: 3px !important;
        padding-right: 3px !important;
        font-size: 8px !important;
        line-height: 1.15 !important;
    }
    .nota-inner.is-tight .notes-box {
        padding: 3px 5px !important;
        font-size: 8px !important;
        min-height: 24px !important;
    }
    .nota-inner.is-tight .signature-section .mb-10 {
        margin-bottom: 1.25rem !important;
    }

    .print\:hidden {
        display: none !important;
    }

    /* Clean shadow/halo stripping */
    #receipt-modal-print-wrapper,
    #receipt-modal-print-wrapper *,
    .nota-paper,
    .nota-paper * {
        box-shadow: none !important;
        text-shadow: none !important;
        filter: none !important;
        -webkit-filter: none !important;
        backdrop-filter: none !important;
        -webkit-backdrop-filter: none !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color-adjust: exact !important;
    }

    img {
        display: block !important;
    }
}
</style>
