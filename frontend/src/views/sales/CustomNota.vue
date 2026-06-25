<template>
    <div class="p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-text-primary">Buat Custom Nota</h1>
            <p class="text-text-secondary">Isi form berikut untuk menghasilkan nota custom secara instan.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-start">
            <!-- Form Input (No Print) -->
            <div class="bg-surface-900 border border-surface-700 p-6 rounded-2xl no-print shadow-sm">
                <h2 class="text-lg font-semibold mb-4 text-text-primary">Data Nota</h2>
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-text-secondary mb-1">Nama Cabang</label>
                            <input v-model="form.branch" type="text"
                                class="w-full bg-surface-800 border border-surface-700 rounded-lg px-3 py-2 text-sm text-text-primary focus:border-primary-500 outline-none" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-text-secondary mb-1">No. Nota</label>
                            <input v-model="form.noNota" type="text"
                                class="w-full bg-surface-800 border border-surface-700 rounded-lg px-3 py-2 text-sm text-text-primary focus:border-primary-500 outline-none" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-text-secondary mb-1">Nama Customer</label>
                            <input v-model="form.customerName" type="text"
                                class="w-full bg-surface-800 border border-surface-700 rounded-lg px-3 py-2 text-sm text-text-primary focus:border-primary-500 outline-none" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-text-secondary mb-1">No. HP Customer</label>
                            <input v-model="form.customerPhone" type="text"
                                class="w-full bg-surface-800 border border-surface-700 rounded-lg px-3 py-2 text-sm text-text-primary focus:border-primary-500 outline-none" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-text-secondary mb-1">Tanggal (cth: 25 JUN 2026,
                                19:49)</label>
                            <input v-model="form.date" type="text"
                                class="w-full bg-surface-800 border border-surface-700 rounded-lg px-3 py-2 text-sm text-text-primary focus:border-primary-500 outline-none" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-text-secondary mb-1">Nama CS</label>
                            <input v-model="form.csName" type="text"
                                class="w-full bg-surface-800 border border-surface-700 rounded-lg px-3 py-2 text-sm text-text-primary focus:border-primary-500 outline-none" />
                        </div>
                    </div>

                    <div class="border-t border-surface-700 pt-4 mt-4">
                        <h3 class="text-sm font-semibold mb-3 text-text-primary">Social Media</h3>
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-text-secondary mb-1">WhatsApp</label>
                                <input v-model="form.wa" type="text" placeholder="0851-..."
                                    class="w-full bg-surface-800 border border-surface-700 rounded-lg px-3 py-2 text-sm text-text-primary focus:border-primary-500 outline-none" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-text-secondary mb-1">TikTok</label>
                                <input v-model="form.tiktok" type="text" placeholder="pstore_jkt"
                                    class="w-full bg-surface-800 border border-surface-700 rounded-lg px-3 py-2 text-sm text-text-primary focus:border-primary-500 outline-none" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-text-secondary mb-1">Instagram</label>
                                <input v-model="form.ig" type="text" placeholder="pstore_jakarta"
                                    class="w-full bg-surface-800 border border-surface-700 rounded-lg px-3 py-2 text-sm text-text-primary focus:border-primary-500 outline-none" />
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-surface-700 pt-4 mt-4">
                        <h3 class="text-sm font-semibold mb-3 text-text-primary">Detail Barang</h3>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-text-secondary mb-1">IMEI</label>
                                <input v-model="form.imei" type="text"
                                    class="w-full bg-surface-800 border border-surface-700 rounded-lg px-3 py-2 text-sm text-text-primary focus:border-primary-500 outline-none" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-text-secondary mb-1">Deskripsi
                                    Barang</label>
                                <input v-model="form.itemName" type="text"
                                    class="w-full bg-surface-800 border border-surface-700 rounded-lg px-3 py-2 text-sm text-text-primary focus:border-primary-500 outline-none" />
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-text-secondary mb-1">Harga Satuan
                                        (Angka)</label>
                                    <input v-model.number="form.price" type="number"
                                        class="w-full bg-surface-800 border border-surface-700 rounded-lg px-3 py-2 text-sm text-text-primary focus:border-primary-500 outline-none" />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-text-secondary mb-1">Qty</label>
                                    <input v-model.number="form.qty" type="number"
                                        class="w-full bg-surface-800 border border-surface-700 rounded-lg px-3 py-2 text-sm text-text-primary focus:border-primary-500 outline-none" />
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-text-secondary mb-1">Metode
                                        Pembayaran</label>
                                    <input v-model="form.method" type="text"
                                        class="w-full bg-surface-800 border border-surface-700 rounded-lg px-3 py-2 text-sm text-text-primary focus:border-primary-500 outline-none" />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-text-secondary mb-1">Catatan</label>
                                    <input v-model="form.notes" type="text"
                                        class="w-full bg-surface-800 border border-surface-700 rounded-lg px-3 py-2 text-sm text-text-primary focus:border-primary-500 outline-none" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex gap-3">
                        <button @click="printNota"
                            class="px-6 py-2.5 bg-primary-600 hover:bg-primary-700 text-white rounded-lg font-medium shadow-sm transition-colors w-full flex items-center justify-center gap-2">
                            Cetak / Simpan PDF
                        </button>
                    </div>
                </div>
            </div>

            <!-- Preview Nota -->
            <div id="receipt-content"
                class="flex-1 w-full flex justify-center bg-gray-100/50 dark:bg-surface-900/50 print:bg-white overflow-hidden rounded-2xl no-print-bg">
                <div
                    class="nota-paper w-full sm:max-w-[650px] mx-auto bg-white p-3 sm:p-6 text-neutral-900 font-sans text-sm shadow-xl print:shadow-none print:max-w-full print:mx-0 print:p-6 relative overflow-hidden select-none">

                    <!-- ===== DYNAMIC CORNER ACCENTS (Mockup Style) ===== -->
                    <div class="absolute top-0 left-0 w-20 h-20 pointer-events-none overflow-hidden z-20">
                        <svg class="w-full h-full" viewBox="0 0 100 100" preserveAspectRatio="none">
                            <polygon points="0,0 100,0 0,100" fill="#0a0a0a" />
                            <polygon points="0,0 55,0 0,55" fill="#dc2626" />
                        </svg>
                    </div>
                    <div class="absolute top-0 right-0 w-20 h-20 pointer-events-none overflow-hidden z-20">
                        <svg class="w-full h-full" viewBox="0 0 100 100" preserveAspectRatio="none">
                            <polygon points="100,0 0,0 100,100" fill="#0a0a0a" />
                            <polygon points="100,0 45,0 100,55" fill="#dc2626" />
                        </svg>
                    </div>

                    <div
                        class="absolute inset-0 flex items-center justify-center opacity-[0.08] pointer-events-none z-0">
                        <img src="/images/ps.png" alt=""
                            class="w-[400px] h-[400px] object-contain transform scale-125" />
                    </div>

                    <div class="relative z-10">
                        <!-- ===== NOTA HEADER ===== -->
                        <div class="flex items-center gap-5 mb-4 mt-6 px-6">
                            <!-- Giant Floating Logo Left -->
                            <div class="shrink-0">
                                <img src="/images/ps.png" alt="PSTORE"
                                    class="w-16 h-16 print:w-20 print:h-20 object-contain" />
                            </div>
                            <!-- Branch & Social Info -->
                            <div class="flex-1">
                                <div class="flex items-baseline gap-1.5 flex-wrap">
                                    <span
                                        class="text-2xl font-black !text-red-600 uppercase tracking-tight leading-none"
                                        style="color: #dc2626 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;">PSTORE</span>
                                    <span
                                        class="text-2xl font-black text-neutral-900 uppercase tracking-tight leading-none">
                                        {{ (form.branch || 'CABANG').toUpperCase().replace('PSTORE ',
                                            '').replace('PSTORE', '') }}
                                    </span>
                                </div>
                                <div class="text-[10px] font-bold text-neutral-700 mt-1.5 leading-tight">
                                    Pusat Perbelanjaan Online
                                </div>

                                <!-- Social Bar -->
                                <div
                                    class="flex items-center gap-x-3 gap-y-1 mt-2 text-[9px] font-extrabold text-neutral-800 flex-wrap">
                                    <span v-if="form.wa" class="flex items-center gap-1">
                                        <svg class="w-2.5 h-2.5 text-red-600 fill-current !text-red-600"
                                            viewBox="0 0 24 24"
                                            style="color: #dc2626 !important; fill: #dc2626 !important;">
                                            <path
                                                d="M20 15.5c-1.25 0-2.45-.2-3.57-.57a1.02 1.02 0 00-1.02.24l-2.2 2.2a15.05 15.05 0 01-6.59-6.59l2.2-2.21a.96.96 0 00.25-1.02A11.36 11.36 0 018.5 4c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1 0 9.39 7.61 17 17 17 .55 0 1-.45 1-1v-3.5c0-.55-.45-1-1-1zM12 3v10l3-3h6V3h-9z" />
                                        </svg>
                                        WA: {{ form.wa }}
                                    </span>
                                    <span v-if="form.wa && (form.tiktok || form.ig)" class="text-neutral-300">|</span>

                                    <span v-if="form.tiktok" class="flex items-center gap-1">
                                        <svg class="w-2.5 h-2.5 text-red-600 fill-current !text-red-600"
                                            viewBox="0 0 24 24"
                                            style="color: #dc2626 !important; fill: #dc2626 !important;">
                                            <path
                                                d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.59-1 .01 2.24.01 4.48 0 6.72-.09 2.93-1.52 5.82-4.32 7.01-2.86 1.29-6.51.83-8.86-1.38-2.43-2.22-2.99-6.09-1.31-8.93 1.49-2.6 4.72-4 7.69-3.43v4.25c-1.82-.35-3.87.19-4.98 1.69-1.13 1.48-1.09 3.72-.02 5.22 1.15 1.66 3.58 2.27 5.44 1.4 1.71-.73 2.71-2.59 2.76-4.44.06-3.34.03-6.68.03-10.02l.02-.31z" />
                                        </svg>
                                        TikTok: {{ form.tiktok }}
                                    </span>
                                    <span v-if="form.tiktok && form.ig" class="text-neutral-300">|</span>

                                    <span v-if="form.ig" class="flex items-center gap-1">
                                        <svg class="w-2.5 h-2.5 text-red-600 fill-current !text-red-600"
                                            viewBox="0 0 24 24"
                                            style="color: #dc2626 !important; fill: #dc2626 !important;">
                                            <path
                                                d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z" />
                                        </svg>
                                        IG: {{ form.ig }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Thick Middle Segment Separator Line -->
                        <div class="relative w-full h-px bg-neutral-200 mb-4 mt-2 flex justify-center">
                            <div class="absolute -top-0.5 w-16 h-1 bg-red-600 rounded-full"></div>
                        </div>

                        <!-- ===== INVOICE TYPE HEAD ===== -->
                        <div class="text-center mb-5 mt-2">
                            <h2 class="text-3xl font-black text-neutral-950 uppercase tracking-wide leading-tight">
                                NOTA PENJUALAN STORE
                            </h2>
                            <div class="flex items-center justify-center gap-2 text-[10px] font-black tracking-wider !text-red-600 mt-0.5"
                                style="color: #dc2626 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;">
                                <div class="h-[1.5px] w-5 bg-red-600/30 rounded-full"></div>
                                <span class="!text-red-600 font-bold"
                                    style="color: #dc2626 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;">BUKTI
                                    TRANSAKSI</span>
                                <div class="h-[1.5px] w-5 bg-red-600/30 rounded-full"></div>
                            </div>
                        </div>

                        <!-- ===== METADATA INFO GRID (3 Columns, 2 Rows) ===== -->
                        <div
                            class="grid grid-cols-3 gap-x-0 mb-4 bg-white/60 backdrop-blur-[2px] rounded-xl p-4 border border-neutral-200">
                            <!-- Column 1: No Nota & Tanggal -->
                            <div class="flex flex-col gap-4 pr-4 border-r border-dashed border-neutral-300">
                                <!-- No Nota -->
                                <div class="flex items-center gap-2.5">
                                    <div class="w-7 h-7 rounded-full border border-neutral-200/60 flex items-center justify-center shrink-0 bg-neutral-50 shadow-sm !text-red-600"
                                        style="color: #dc2626 !important; background-color: #fafafa !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;">
                                        <svg class="w-3.5 h-3.5 fill-current !text-red-600" viewBox="0 0 24 24"
                                            style="color: #dc2626 !important; fill: #dc2626 !important;">
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
                                            {{ form.noNota || '-' }}</div>
                                    </div>
                                </div>

                                <!-- Tanggal & Waktu -->
                                <div class="flex items-center gap-2.5">
                                    <div class="w-7 h-7 rounded-full border border-neutral-200/60 flex items-center justify-center shrink-0 bg-neutral-50 shadow-sm !text-red-600"
                                        style="color: #dc2626 !important; background-color: #fafafa !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;">
                                        <svg class="w-3.5 h-3.5 fill-current !text-red-600" viewBox="0 0 24 24"
                                            style="color: #dc2626 !important; fill: #dc2626 !important;">
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
                                            {{ form.date || '-' }}</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Column 2: Atas Nama & No HP -->
                            <div class="flex flex-col gap-4 px-4 border-r border-dashed border-neutral-300">
                                <!-- Atas Nama -->
                                <div class="flex items-center gap-2.5">
                                    <div class="w-7 h-7 rounded-full border border-neutral-200/60 flex items-center justify-center shrink-0 bg-neutral-50 shadow-sm !text-red-600"
                                        style="color: #dc2626 !important; background-color: #fafafa !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;">
                                        <svg class="w-3.5 h-3.5 fill-current !text-red-600" viewBox="0 0 24 24"
                                            style="color: #dc2626 !important; fill: #dc2626 !important;">
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
                                            {{ form.customerName || 'Umum' }}</div>
                                    </div>
                                </div>

                                <!-- No HP -->
                                <div class="flex items-center gap-2.5">
                                    <div class="w-7 h-7 rounded-full border border-neutral-200/60 flex items-center justify-center shrink-0 bg-neutral-50 shadow-sm !text-red-600"
                                        style="color: #dc2626 !important; background-color: #fafafa !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;">
                                        <svg class="w-3.5 h-3.5 fill-current !text-red-600" viewBox="0 0 24 24"
                                            style="color: #dc2626 !important; fill: #dc2626 !important;">
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
                                            {{ form.customerPhone || '-' }}</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Column 3: Customer Service -->
                            <div class="flex flex-col gap-4 pl-4">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-7 h-7 rounded-full border border-neutral-200/60 flex items-center justify-center shrink-0 bg-neutral-50 shadow-sm !text-red-600"
                                        style="color: #dc2626 !important; background-color: #fafafa !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;">
                                        <svg class="w-3.5 h-3.5 fill-current !text-red-600" viewBox="0 0 24 24"
                                            style="color: #dc2626 !important; fill: #dc2626 !important;">
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
                                            {{ form.csName || '-' }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ===== TABEL ITEMS (6 Columns) ===== -->
                        <div
                            class="rounded-xl overflow-hidden border border-neutral-200 mb-6 shadow-sm bg-white/60 backdrop-blur-[1px]">
                            <table class="w-full text-[10px] sm:text-xs border-collapse">
                                <thead>
                                    <tr style="background-color: #0a0a0a !important;"
                                        class="text-[9px] sm:text-[10px] uppercase tracking-wider">
                                        <th style="background-color: #0a0a0a !important; color: #ffffff !important;"
                                            class="py-3 px-3 text-left font-black w-[110px]">IMEI</th>
                                        <th style="background-color: #0a0a0a !important; color: #ffffff !important;"
                                            class="py-3 px-3 text-left font-black">Deskripsi Barang</th>
                                        <th style="background-color: #0a0a0a !important; color: #ffffff !important;"
                                            class="py-3 px-3 text-right font-black w-[85px]">Harga Satuan</th>
                                        <th style="background-color: #0a0a0a !important; color: #ffffff !important;"
                                            class="py-3 px-3 text-center font-black w-[40px]">Qty</th>
                                        <th style="background-color: #0a0a0a !important; color: #ffffff !important;"
                                            class="py-3 px-3 text-right font-black w-[85px]">Jumlah</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="bg-white/90 border-b border-neutral-300">
                                        <td class="py-3 px-3 align-middle text-left">
                                            <div class="text-[10px] font-black font-mono text-neutral-900">{{ form.imei
                                                || '-' }}</div>
                                        </td>
                                        <td class="py-3 px-3 align-middle">
                                            <div
                                                class="font-bold text-neutral-800 text-[11px] uppercase flex items-start">
                                                <span>{{ form.itemName }}</span>
                                            </div>
                                        </td>
                                        <td
                                            class="py-3 px-3 align-middle text-right font-bold text-neutral-900 whitespace-nowrap">
                                            {{ formatNumber(form.price) }}
                                        </td>
                                        <td
                                            class="py-3 px-3 text-neutral-950 text-center font-black align-middle text-xs">
                                            {{ form.qty }}
                                        </td>
                                        <td
                                            class="py-3 px-3 align-middle text-right font-black text-neutral-950 whitespace-nowrap text-xs">
                                            {{ formatNumber(form.price * form.qty) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- ===== SPLIT COLUMN: Left (Note, Warranty) | Right (Breakdown, Total) ===== -->
                        <div class="flex flex-col md:flex-row gap-6 mb-6 mt-2">
                            <!-- Left Column: Notes & Warranty -->
                            <div class="flex-1">
                                <!-- Catatan Ribbon Box -->
                                <div class="mb-5 relative">
                                    <div class="relative inline-block bg-red-600 !text-white text-[9px] font-black uppercase tracking-wider px-4 py-1.5 rounded-tr-2xl rounded-bl-sm shadow-sm z-10"
                                        style="color: #ffffff !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;">
                                        Catatan
                                    </div>
                                    <div
                                        class="notes-box bg-neutral-50/40 border border-neutral-200 rounded-xl p-3.5 pt-5 -mt-2.5 text-[10px] font-bold text-neutral-800 min-h-[64px]">
                                        {{ form.notes || 'Tidak ada catatan tambahan.' }}
                                    </div>
                                </div>

                                <!-- Syarat & Ketentuan (Warranty) -->
                                <div class="mb-4">
                                    <div class="flex items-center gap-2 mb-2">
                                        <span class="text-[10px] font-black !text-red-600 tracking-wider uppercase"
                                            style="color: #dc2626 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;">Syarat
                                            & Ketentuan</span>
                                        <div class="h-0.5 w-10 bg-red-200 rounded-full"></div>
                                    </div>
                                    <ul
                                        class="text-[8px] sm:text-[9px] text-neutral-600 font-bold space-y-1 list-disc pl-4 leading-relaxed">
                                        <li>Garansi unit selama 1 bulan terhitung sejak tanggal nota.</li>
                                        <li>Garansi yang sudah tidak batas tanggal akan tidak mendapatkan klaim garansi.
                                        </li>
                                        <li>Segel wajib utuh. Kerusakan akibat human error membatalkan garansi.</li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Right Column: Financial Details & Slanted Total -->
                            <div class="w-full md:w-[260px] shrink-0">
                                <!-- Small Subtotal Breakdown -->
                                <div
                                    class="bg-neutral-50/80 rounded-xl border border-neutral-200 p-3 space-y-2 text-xs mb-3">
                                    <div class="flex justify-between">
                                        <span class="font-bold text-neutral-500 text-[10px]">Sub Total</span>
                                        <span class="text-neutral-900 font-bold">
                                            {{ formatNumber(form.price * form.qty) }}
                                        </span>
                                    </div>
                                    <div class="flex justify-between border-t border-neutral-200/50 pt-1">
                                        <span class="font-bold text-neutral-500 text-[10px] uppercase">{{ form.method
                                        }}</span>
                                        <span class="text-neutral-900 font-bold">{{ formatNumber(form.price * form.qty)
                                        }}</span>
                                    </div>

                                    <!-- Slanted or Red Divider Line -->
                                    <div class="border-t border-neutral-300 border-dashed pt-1.5 flex justify-between">
                                        <span class="font-black !text-red-600 text-[10px] uppercase tracking-wider"
                                            style="color: #dc2626 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;">Selisih
                                            Harga</span>
                                        <span class="!text-red-600 font-black text-sm"
                                            style="color: #dc2626 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;">
                                            {{ formatNumber(form.price * form.qty) }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Exact Slanted Grand Total Header Box -->
                                <div
                                    class="flex rounded-xl overflow-hidden shadow-md h-[60px] bg-red-600 w-full relative">
                                    <!-- Black Trapezoid Part -->
                                    <div class="bg-neutral-950 text-white pl-4 pr-8 flex flex-col justify-center shrink-0 select-none pointer-events-none"
                                        style="clip-path: polygon(0 0, 100% 0, 82% 100%, 0% 100%); z-index: 2;">
                                        <div class="text-[8px] font-black uppercase tracking-wider leading-none !text-white"
                                            style="color: #ffffff !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;">
                                            Yang Harus</div>
                                        <div class="text-[8px] font-black uppercase tracking-wider leading-tight !text-white"
                                            style="color: #ffffff !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;">
                                            Dibayarkan</div>
                                    </div>
                                    <!-- Red Total Part -->
                                    <div class="flex-1 flex items-center justify-end pr-4 !text-white"
                                        style="z-index: 1; color: #ffffff !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;">
                                        <span class="text-lg sm:text-xl font-black !text-white tracking-tight"
                                            style="color: #ffffff !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;">{{
                                                formatNumber(form.price * form.qty) }}</span>
                                    </div>
                                </div>

                                <div class="text-[8px] text-right text-neutral-400 italic mt-2 font-bold">
                                    Metode: {{ form.method || '-' }}
                                </div>
                            </div>
                        </div>

                        <!-- ===== SIGNATURE AREA ===== -->
                        <div
                            class="signature-area grid grid-cols-2 text-[10px] mt-8 mb-6 gap-6 border border-neutral-100 rounded-xl py-4 bg-white/60 backdrop-blur-[1px]">
                            <div class="text-center border-r border-neutral-200">
                                <div class="text-[9px] font-black !text-red-600 uppercase tracking-widest mb-12"
                                    style="color: #dc2626 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;">
                                    Customer
                                    / Pembeli</div>
                                <div class="border-b border-neutral-400 w-full max-w-[160px] mx-auto mb-1.5"></div>
                                <div class="text-[11px] font-black text-neutral-900 uppercase">
                                    {{ form.customerName || 'Umum' }}
                                </div>
                            </div>
                            <div class="text-center">
                                <div class="text-[9px] font-black !text-red-600 uppercase tracking-widest mb-12"
                                    style="color: #dc2626 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;">
                                    Hormat
                                    Kami</div>
                                <div class="border-b border-neutral-400 w-full max-w-[160px] mx-auto mb-1.5"></div>
                                <div class="text-[11px] font-black text-neutral-900 uppercase">
                                    {{ form.csName || 'PSTORE' }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, nextTick } from 'vue';

const isPrinting = ref(false);

const form = ref({
    branch: 'PSTORE SERANG BANTEN',
    noNota: '025JUN-2LL',
    customerName: 'NURHIDAYAT',
    customerPhone: '087773204445',
    date: '25 JUN 2026, 19:49',
    csName: 'AMOY',
    imei: '357415870334934',
    itemName: 'IPHONE ™ - 13 128 GB SECOND',
    price: 5250000,
    qty: 1,
    method: 'TRANSFER BCA',
    notes: 'spider, 100% 26.5',
    wa: '0851-3300-5600',
    tiktok: 'pstore_jkt',
    ig: 'pstore_jakarta'
});

const formatNumber = (num) => {
    if (!num) return '0';
    return 'Rp ' + Number(num).toLocaleString('id-ID');
};

const printNota = async () => {
    isPrinting.value = true;
    await nextTick();

    const originalTitle = document.title;
    document.title = 'Nota PSTORE';

    const receiptContent = document.querySelector('.nota-paper');
    if (!receiptContent) {
        alert('Element nota tidak ditemukan!');
        isPrinting.value = false;
        return;
    }

    // Create wrapper with proper styling
    const printWrapper = document.createElement('div');
    printWrapper.id = 'custom-nota-print-wrapper';
    printWrapper.style.cssText = `
        position: relative;
        width: 100%;
        background: white;
        z-index: 9999999;
        padding: 0;
        margin: 0;
    `;

    const clone = receiptContent.cloneNode(true);
    clone.className = 'nota-paper w-full bg-white mx-auto relative select-none';
    clone.style.cssText = `
        width: 100%;
        max-width: 210mm;
        margin: 0 auto;
        background: white !important;
        padding: 10mm;
        box-sizing: border-box;
        box-shadow: none !important;
        border: none !important;
    `;

    printWrapper.appendChild(clone);
    document.body.appendChild(printWrapper);

    // Inject print styles
    const styleId = 'custom-nota-print-styles';
    let styleEl = document.getElementById(styleId);
    if (!styleEl) {
        styleEl = document.createElement('style');
        styleEl.id = styleId;
        document.head.appendChild(styleEl);
    }
    styleEl.textContent = `
        @media print {
            @page { 
                size: A4 portrait; 
                margin: 10mm !important; 
            }
            
            /* Hide everything except our wrapper */
            body > *:not(#custom-nota-print-wrapper) {
                display: none !important;
                visibility: hidden !important;
            }
            
            /* Ensure wrapper is visible and flows properly */
            #custom-nota-print-wrapper {
                display: block !important;
                position: static !important;
                width: 100% !important;
                background: white !important;
                visibility: visible !important;
                z-index: auto !important;
            }
            
            /* Reset nota-paper for print */
            #custom-nota-print-wrapper .nota-paper {
                width: 100% !important;
                max-width: none !important;
                box-shadow: none !important;
                border: none !important;
                border-radius: 0 !important;
                background: white !important;
                color: black !important;
                padding: 0 !important;
                margin: 0 !important;
                overflow: visible !important;
                page-break-inside: avoid !important;
            }
            
            /* Force colors to print */
            #custom-nota-print-wrapper * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
            
            /* Remove shadows and effects */
            #custom-nota-print-wrapper * {
                box-shadow: none !important;
                text-shadow: none !important;
                filter: none !important;
                backdrop-filter: none !important;
            }
        }
    `;

    // Wait for DOM to stabilize
    setTimeout(() => {
        window.print();

        // Cleanup after print dialog
        setTimeout(() => {
            document.title = originalTitle;
            if (printWrapper && printWrapper.parentNode) {
                printWrapper.parentNode.removeChild(printWrapper);
            }
            if (styleEl && styleEl.parentNode) {
                styleEl.parentNode.removeChild(styleEl);
            }
            isPrinting.value = false;
        }, 500);
    }, 300);
};
</script>

<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap');

.nota-paper {
    font-family: 'Inter', sans-serif;
}

/* Screen-only styles for preview */
@media screen {
    .nota-paper {
        max-width: 650px;
        margin: 0 auto;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }
}
</style>