<template>
    <div class="p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-text-primary">Buat Custom Nota</h1>
            <p class="text-text-secondary">Isi form berikut untuk menghasilkan nota custom secara instan.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-start">
            <!-- Form Input -->
            <div class="bg-surface-900 border border-surface-700 p-6 rounded-2xl shadow-sm">
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
                            <label class="block text-xs font-medium text-text-secondary mb-1">Tanggal</label>
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
                                <input v-model="form.wa" type="text"
                                    class="w-full bg-surface-800 border border-surface-700 rounded-lg px-3 py-2 text-sm text-text-primary focus:border-primary-500 outline-none" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-text-secondary mb-1">TikTok</label>
                                <input v-model="form.tiktok" type="text"
                                    class="w-full bg-surface-800 border border-surface-700 rounded-lg px-3 py-2 text-sm text-text-primary focus:border-primary-500 outline-none" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-text-secondary mb-1">Instagram</label>
                                <input v-model="form.ig" type="text"
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
                                    <label class="block text-xs font-medium text-text-secondary mb-1">Harga
                                        Satuan</label>
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
                            class="px-6 py-2.5 bg-primary-600 hover:bg-primary-700 text-white rounded-lg font-medium shadow-sm transition-colors w-full">
                            Cetak / Simpan PDF
                        </button>
                    </div>
                </div>
            </div>

            <!-- Preview Nota -->
            <div id="receipt-content"
                class="flex-1 w-full flex justify-center bg-gray-100/50 dark:bg-surface-900/50 overflow-hidden rounded-2xl">
                <div ref="notaPaper"
                    class="nota-paper w-full sm:max-w-[650px] mx-auto bg-white p-4 text-neutral-900 font-sans text-sm shadow-xl relative overflow-hidden select-none">

                    <!-- Corner Accents -->
                    <div class="absolute top-0 left-0 w-16 h-16 pointer-events-none overflow-hidden z-20">
                        <svg class="w-full h-full" viewBox="0 0 100 100" preserveAspectRatio="none">
                            <polygon points="0,0 100,0 0,100" fill="#0a0a0a" />
                            <polygon points="0,0 55,0 0,55" fill="#dc2626" />
                        </svg>
                    </div>
                    <div class="absolute top-0 right-0 w-16 h-16 pointer-events-none overflow-hidden z-20">
                        <svg class="w-full h-full" viewBox="0 0 100 100" preserveAspectRatio="none">
                            <polygon points="100,0 0,0 100,100" fill="#0a0a0a" />
                            <polygon points="100,0 45,0 100,55" fill="#dc2626" />
                        </svg>
                    </div>

                    <!-- Watermark -->
                    <div
                        class="absolute inset-0 flex items-center justify-center opacity-[0.05] pointer-events-none z-0">
                        <img src="/images/ps.png" alt="" class="w-[300px] h-[300px] object-contain" />
                    </div>

                    <div class="relative z-10 space-y-3">
                        <!-- Header - Compact -->
                        <div class="flex items-center gap-3 px-4">
                            <div class="shrink-0">
                                <img src="/images/ps.png" alt="PSTORE" class="w-12 h-12 object-contain" />
                            </div>
                            <div class="flex-1">
                                <div class="flex items-baseline gap-1">
                                    <span class="text-xl font-black text-red-600 uppercase">PSTORE</span>
                                    <span class="text-xl font-black text-neutral-900 uppercase">
                                        {{ (form.branch || 'CABANG').toUpperCase().replace('PSTORE ',
                                        '').replace('PSTORE', '') }}
                                    </span>
                                </div>
                                <div class="text-[8px] font-bold text-neutral-700">Pusat Perbelanjaan Online</div>
                                <div class="flex gap-2 text-[7px] font-bold text-neutral-800 flex-wrap mt-1">
                                    <span v-if="form.wa">WA: {{ form.wa }}</span>
                                    <span v-if="form.tiktok">| TT: {{ form.tiktok }}</span>
                                    <span v-if="form.ig">| IG: {{ form.ig }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Title -->
                        <div class="text-center py-2 border-y border-neutral-200">
                            <h2 class="text-2xl font-black text-neutral-950 uppercase">NOTA PENJUALAN</h2>
                            <div class="text-[8px] font-black text-red-600 uppercase tracking-wider mt-0.5">BUKTI
                                TRANSAKSI</div>
                        </div>

                        <!-- Info Grid - 2 Rows, 3 Cols -->
                        <div class="grid grid-cols-3 gap-2 text-[8px]">
                            <div class="space-y-1.5">
                                <div>
                                    <div class="font-black text-neutral-500 uppercase text-[6px]">No. Nota</div>
                                    <div class="font-bold text-neutral-900">{{ form.noNota || '-' }}</div>
                                </div>
                                <div>
                                    <div class="font-black text-neutral-500 uppercase text-[6px]">Tanggal</div>
                                    <div class="font-bold text-neutral-900">{{ form.date || '-' }}</div>
                                </div>
                            </div>
                            <div class="space-y-1.5 border-x border-neutral-200 px-2">
                                <div>
                                    <div class="font-black text-neutral-500 uppercase text-[6px]">Atas Nama</div>
                                    <div class="font-bold text-neutral-900">{{ form.customerName || 'Umum' }}</div>
                                </div>
                                <div>
                                    <div class="font-black text-neutral-500 uppercase text-[6px]">No. HP</div>
                                    <div class="font-bold text-neutral-900">{{ form.customerPhone || '-' }}</div>
                                </div>
                            </div>
                            <div class="space-y-1.5">
                                <div>
                                    <div class="font-black text-neutral-500 uppercase text-[6px]">Customer Service</div>
                                    <div class="font-bold text-neutral-900">{{ form.csName || '-' }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Table - Compact -->
                        <div class="border border-neutral-300 rounded overflow-hidden">
                            <table class="w-full text-[8px]">
                                <thead style="background-color: #0a0a0a !important;">
                                    <tr>
                                        <th class="py-1.5 px-2 text-left font-black text-white w-[100px]">IMEI</th>
                                        <th class="py-1.5 px-2 text-left font-black text-white">Barang</th>
                                        <th class="py-1.5 px-2 text-right font-black text-white w-[70px]">Harga</th>
                                        <th class="py-1.5 px-2 text-center font-black text-white w-[30px]">Qty</th>
                                        <th class="py-1.5 px-2 text-right font-black text-white w-[70px]">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="bg-white">
                                        <td class="py-1.5 px-2 font-mono text-[7px]">{{ form.imei || '-' }}</td>
                                        <td class="py-1.5 px-2 font-bold text-[9px] uppercase">{{ form.itemName }}</td>
                                        <td class="py-1.5 px-2 text-right font-bold">{{ formatNumber(form.price) }}</td>
                                        <td class="py-1.5 px-2 text-center font-black">{{ form.qty }}</td>
                                        <td class="py-1.5 px-2 text-right font-black">{{ formatNumber(form.price *
                                            form.qty) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Bottom Section - 2 Columns -->
                        <div class="flex gap-3">
                            <!-- Left: Notes & Warranty -->
                            <div class="flex-1 space-y-2">
                                <div>
                                    <div
                                        class="bg-red-600 text-white text-[7px] font-black uppercase px-2 py-0.5 rounded-tr inline-block">
                                        Catatan</div>
                                    <div
                                        class="bg-neutral-50 border border-neutral-200 rounded p-1.5 text-[7px] font-bold mt-0.5">
                                        {{ form.notes || '-' }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-[7px] font-black text-red-600 uppercase mb-1">Syarat & Ketentuan
                                    </div>
                                    <ul class="text-[6px] text-neutral-600 space-y-0.5 list-disc pl-3">
                                        <li>Garansi 1 bulan dari tanggal nota</li>
                                        <li>Segel wajib utuh</li>
                                        <li>Human error tidak bergaransi</li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Right: Total -->
                            <div class="w-[140px] space-y-1">
                                <div class="bg-neutral-50 border border-neutral-200 rounded p-1.5 text-[7px] space-y-1">
                                    <div class="flex justify-between">
                                        <span class="font-bold">Sub Total</span>
                                        <span class="font-bold">{{ formatNumber(form.price * form.qty) }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="font-bold uppercase">{{ form.method }}</span>
                                        <span class="font-bold">{{ formatNumber(form.price * form.qty) }}</span>
                                    </div>
                                </div>

                                <!-- Total Box -->
                                <div class="bg-red-600 rounded overflow-hidden flex">
                                    <div class="bg-neutral-950 text-white px-2 py-1.5 text-[6px] font-black uppercase"
                                        style="clip-path: polygon(0 0, 100% 0, 85% 100%, 0 100%)">
                                        Total<br />Bayar
                                    </div>
                                    <div
                                        class="flex-1 flex items-center justify-end pr-2 text-white font-black text-sm">
                                        {{ formatNumber(form.price * form.qty) }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Signature - Compact -->
                        <div class="grid grid-cols-2 gap-4 text-[7px] mt-2 pt-2 border-t border-neutral-200">
                            <div class="text-center">
                                <div class="font-black text-red-600 uppercase mb-6">Customer / Pembeli</div>
                                <div class="border-b border-neutral-400 w-full mb-1"></div>
                                <div class="font-black uppercase">{{ form.customerName || 'Umum' }}</div>
                            </div>
                            <div class="text-center">
                                <div class="font-black text-red-600 uppercase mb-6">Hormat Kami</div>
                                <div class="border-b border-neutral-400 w-full mb-1"></div>
                                <div class="font-black uppercase">{{ form.csName || 'PSTORE' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';

const notaPaper = ref(null);

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

const printNota = () => {
    const content = notaPaper.value;
    if (!content) {
        alert('Element nota tidak ditemukan!');
        return;
    }

    const htmlContent = content.outerHTML;

    const printCSS = `
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap');
            
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
            
            body {
                font-family: 'Inter', sans-serif;
                background: white;
                padding: 8mm;
            }
            
            .nota-paper {
                width: 100%;
                max-width: 210mm;
                margin: 0 auto;
                background: white;
                position: relative;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
            
            .nota-paper * {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
            
            @media print {
                @page {
                    size: A4 portrait;
                    margin: 8mm;
                }
                
                body {
                    padding: 0;
                }
                
                .nota-paper {
                    box-shadow: none !important;
                    border: none !important;
                    width: 100% !important;
                    max-width: none !important;
                }
            }
        </style>
    `;

    const printWindow = window.open('', '_blank');

    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Nota PSTORE</title>
            ${printCSS}
        </head>
        <body>
            ${htmlContent}
            <script>
                window.onload = function() {
                    window.print();
                    setTimeout(function() {
                        window.close();
                    }, 500);
                };
            <\/script>
        </body>
        </html>
    `);

    printWindow.document.close();
};
</script>