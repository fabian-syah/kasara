<template>
  <div class="p-6">
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-text-primary">Buat Custom Nota</h1>
      <p class="text-text-secondary">Isi form berikut untuk menghasilkan nota custom secara instan.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
      <!-- Form Input (No Print) -->
      <div class="bg-surface-900 border border-surface-700 p-6 rounded-2xl no-print shadow-sm">
        <h2 class="text-lg font-semibold mb-4 text-text-primary">Data Nota</h2>
        <div class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-medium text-text-secondary mb-1">Nama Cabang</label>
              <input v-model="form.branch" type="text" class="w-full bg-surface-800 border border-surface-700 rounded-lg px-3 py-2 text-sm text-text-primary focus:border-primary-500 outline-none" />
            </div>
            <div>
              <label class="block text-xs font-medium text-text-secondary mb-1">No. Nota</label>
              <input v-model="form.noNota" type="text" class="w-full bg-surface-800 border border-surface-700 rounded-lg px-3 py-2 text-sm text-text-primary focus:border-primary-500 outline-none" />
            </div>
          </div>
          
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-medium text-text-secondary mb-1">Nama Customer</label>
              <input v-model="form.customerName" type="text" class="w-full bg-surface-800 border border-surface-700 rounded-lg px-3 py-2 text-sm text-text-primary focus:border-primary-500 outline-none" />
            </div>
            <div>
              <label class="block text-xs font-medium text-text-secondary mb-1">No. HP Customer</label>
              <input v-model="form.customerPhone" type="text" class="w-full bg-surface-800 border border-surface-700 rounded-lg px-3 py-2 text-sm text-text-primary focus:border-primary-500 outline-none" />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-medium text-text-secondary mb-1">Tanggal (cth: 25 JUN 2026, 19:49)</label>
              <input v-model="form.date" type="text" class="w-full bg-surface-800 border border-surface-700 rounded-lg px-3 py-2 text-sm text-text-primary focus:border-primary-500 outline-none" />
            </div>
            <div>
              <label class="block text-xs font-medium text-text-secondary mb-1">Nama CS</label>
              <input v-model="form.csName" type="text" class="w-full bg-surface-800 border border-surface-700 rounded-lg px-3 py-2 text-sm text-text-primary focus:border-primary-500 outline-none" />
            </div>
          </div>

          <div class="border-t border-surface-700 pt-4 mt-4">
            <h3 class="text-sm font-semibold mb-3 text-text-primary">Detail Barang</h3>
            <div class="space-y-3">
              <div>
                <label class="block text-xs font-medium text-text-secondary mb-1">IMEI</label>
                <input v-model="form.imei" type="text" class="w-full bg-surface-800 border border-surface-700 rounded-lg px-3 py-2 text-sm text-text-primary focus:border-primary-500 outline-none" />
              </div>
              <div>
                <label class="block text-xs font-medium text-text-secondary mb-1">Deskripsi Barang</label>
                <input v-model="form.itemName" type="text" class="w-full bg-surface-800 border border-surface-700 rounded-lg px-3 py-2 text-sm text-text-primary focus:border-primary-500 outline-none" />
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-medium text-text-secondary mb-1">Harga Satuan (Angka)</label>
                  <input v-model.number="form.price" type="number" class="w-full bg-surface-800 border border-surface-700 rounded-lg px-3 py-2 text-sm text-text-primary focus:border-primary-500 outline-none" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-text-secondary mb-1">Qty</label>
                  <input v-model.number="form.qty" type="number" class="w-full bg-surface-800 border border-surface-700 rounded-lg px-3 py-2 text-sm text-text-primary focus:border-primary-500 outline-none" />
                </div>
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-medium text-text-secondary mb-1">Metode Pembayaran</label>
                  <input v-model="form.method" type="text" class="w-full bg-surface-800 border border-surface-700 rounded-lg px-3 py-2 text-sm text-text-primary focus:border-primary-500 outline-none" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-text-secondary mb-1">Catatan</label>
                  <input v-model="form.notes" type="text" class="w-full bg-surface-800 border border-surface-700 rounded-lg px-3 py-2 text-sm text-text-primary focus:border-primary-500 outline-none" />
                </div>
              </div>
            </div>
          </div>

          <div class="mt-6 flex gap-3">
            <button @click="printNota" class="px-6 py-2.5 bg-primary-600 hover:bg-primary-700 text-white rounded-lg font-medium shadow-sm transition-colors w-full flex items-center justify-center gap-2">
               Cetak / Simpan PDF
            </button>
          </div>
        </div>
      </div>

      <!-- Preview Nota -->
      <div>
        <div class="print-area">
          <div class="nota-box">
            <!-- Top decoration lines -->
            <div class="absolute top-0 left-0 w-32 h-32 overflow-hidden pointer-events-none">
                <div class="absolute -top-20 -left-16 w-32 h-32 bg-red-600 rotate-45"></div>
                <div class="absolute -top-24 -left-10 w-32 h-32 bg-black rotate-45"></div>
            </div>
            <div class="absolute top-0 right-0 w-32 h-32 overflow-hidden pointer-events-none">
                <div class="absolute -top-20 -right-16 w-32 h-32 bg-red-600 -rotate-45"></div>
                <div class="absolute -top-24 -right-10 w-32 h-32 bg-black -rotate-45"></div>
            </div>

            <div class="p-8 pt-12 relative z-10 text-black">
                <!-- Header -->
                <div class="flex items-center gap-4 mb-8 relative z-10">
                    <!-- Logo P -->
                    <div class="w-16 h-16 shrink-0 text-black">
                        <svg viewBox="0 0 100 100" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                            <path d="M25 20 H65 A20 20 0 0 1 85 40 V50 A20 20 0 0 1 65 70 H45" stroke="black" stroke-width="12" stroke-linecap="round" fill="none"/>
                            <path d="M25 45 H55" stroke="black" stroke-width="12" stroke-linecap="round"/>
                            <path d="M25 20 V80" stroke="black" stroke-width="12" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-2xl font-black uppercase"><span class="text-red-600">{{ form.branch.split(' ')[0] }}</span> {{ form.branch.split(' ').slice(1).join(' ') }}</h1>
                        <p class="text-[11px] font-bold mt-1 text-gray-800">Pusat Perbelanjaan Online</p>
                        <p class="text-[11px] font-bold mt-1 text-gray-800 flex items-center gap-1">
                            <span class="text-red-600">📞 WA:</span> 0851-3300-5600
                        </p>
                    </div>
                </div>

                <div class="flex justify-center mb-8 relative z-10">
                    <div class="border-t-[3px] border-red-600 w-12 absolute -top-4"></div>
                    <h2 class="text-2xl font-black tracking-wider text-center">NOTA PENJUALAN STORE<br><span class="text-[10px] text-red-600 tracking-widest font-bold">BUKTI TRANSAKSI</span></h2>
                </div>

                <!-- Meta Data Grid -->
                <div class="border border-gray-300 rounded-xl p-4 mb-6 grid grid-cols-3 gap-4 text-xs relative z-10 bg-white">
                    <div>
                        <p class="text-red-600 font-bold mb-1 flex items-center gap-1 text-[10px]">NO. NOTA</p>
                        <p class="font-bold">{{ form.noNota }}</p>
                        <p class="text-red-600 font-bold mt-3 mb-1 flex items-center gap-1 text-[10px]">TANGGAL & WAKTU</p>
                        <p class="font-bold">{{ form.date }}</p>
                    </div>
                    <div>
                        <p class="text-red-600 font-bold mb-1 flex items-center gap-1 text-[10px]">ATAS NAMA</p>
                        <p class="font-bold uppercase">{{ form.customerName }}</p>
                        <p class="text-red-600 font-bold mt-3 mb-1 flex items-center gap-1 text-[10px]">NO. HP</p>
                        <p class="font-bold">{{ form.customerPhone }}</p>
                    </div>
                    <div>
                        <p class="text-red-600 font-bold mb-1 flex items-center gap-1 text-[10px]">CUSTOMER SERVICE</p>
                        <p class="font-bold uppercase">{{ form.csName }}</p>
                    </div>
                </div>

                <!-- Table -->
                <div class="mb-6 rounded-xl overflow-hidden border border-gray-300 relative z-10">
                    <table class="w-full text-xs">
                        <thead class="bg-black text-white">
                            <tr>
                                <th class="py-2.5 px-3 text-left w-1/4 uppercase tracking-wider font-bold">IMEI</th>
                                <th class="py-2.5 px-3 text-left uppercase tracking-wider font-bold">Deskripsi Barang</th>
                                <th class="py-2.5 px-3 text-right uppercase tracking-wider font-bold">Harga Satuan</th>
                                <th class="py-2.5 px-3 text-center uppercase tracking-wider font-bold">Qty</th>
                                <th class="py-2.5 px-3 text-right uppercase tracking-wider font-bold">Jumlah</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="border-b border-gray-200 font-bold bg-white text-black">
                                <td class="py-3 px-3 tracking-widest">{{ form.imei || '-' }}</td>
                                <td class="py-3 px-3 uppercase">{{ form.itemName }}</td>
                                <td class="py-3 px-3 text-right">{{ formatNumber(form.price) }}</td>
                                <td class="py-3 px-3 text-center">{{ form.qty }}</td>
                                <td class="py-3 px-3 text-right">{{ formatNumber(form.price * form.qty) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Catatan -->
                <div class="mb-6 border border-gray-300 rounded-xl relative pt-6 pb-4 px-4 bg-white z-10">
                    <div class="absolute -top-3 left-4 bg-red-600 text-white px-3 py-1 text-[10px] tracking-wider font-bold rounded-md shadow-sm">CATATAN</div>
                    <p class="text-xs font-bold text-black">{{ form.notes || '-' }}</p>
                </div>

                <!-- S&K & Totals -->
                <div class="relative bg-white text-black z-10 p-4 border-t border-b border-gray-200" style="background: linear-gradient(180deg, rgba(255,255,255,1) 0%, rgba(245,245,245,1) 100%); border-radius: 12px; margin-bottom: 24px;">
                    <!-- Watermark -->
                    <div class="absolute top-0 inset-x-0 bottom-0 flex items-center justify-center pointer-events-none opacity-[0.03]">
                        <svg viewBox="0 0 100 100" class="w-64 h-64" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                            <path d="M25 20 H65 A20 20 0 0 1 85 40 V50 A20 20 0 0 1 65 70 H45" stroke="currentColor" stroke-width="12" stroke-linecap="round" fill="none"/>
                            <path d="M25 45 H55" stroke="currentColor" stroke-width="12" stroke-linecap="round"/>
                            <path d="M25 20 V80" stroke="currentColor" stroke-width="12" stroke-linecap="round"/>
                        </svg>
                    </div>

                    <div class="mb-6 relative z-10">
                        <h3 class="text-red-600 font-bold text-xs mb-2 uppercase">SYARAT & KETENTUAN</h3>
                        <ul class="text-[10px] font-bold space-y-1 text-black">
                            <li>1. Garansi 1 Bulan (Nota Dan Segel Jangan Hilang)</li>
                            <li>2. Barang yang Sudah Dibeli Tidak Dapat Dikembalikan/Ditukarkan</li>
                            <li>3. Tidak ada garansi IMEI afr, jatuh, gagal upgrade dan LCD</li>
                        </ul>
                    </div>

                    <div class="space-y-3 text-xs font-bold mb-2 relative z-10 bg-white p-4 rounded-xl border border-gray-200">
                        <div class="flex justify-between border-b border-gray-200 pb-2">
                            <span>Sub Total</span>
                            <span>Rp {{ formatNumber(form.price * form.qty) }}</span>
                        </div>
                        <div class="flex justify-between border-b border-gray-200 pb-2">
                            <span class="uppercase">{{ form.method }}</span>
                            <span>Rp {{ formatNumber(form.price * form.qty) }}</span>
                        </div>
                        <div class="flex justify-between text-red-600">
                            <span class="uppercase">SELISIH HARGA</span>
                            <span>Rp {{ formatNumber(form.price * form.qty) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Footer Total -->
                <div class="flex rounded-xl overflow-hidden mt-6 shadow-md relative z-10 border border-gray-300">
                    <div class="bg-black text-white w-1/3 p-4 flex items-center">
                        <span class="font-black uppercase text-sm leading-tight tracking-wider">YANG HARUS<br>DIBAYARKAN</span>
                    </div>
                    <div class="bg-red-600 text-white w-2/3 p-4 flex items-center justify-end">
                        <span class="font-black text-xl">Rp {{ formatNumber(form.price * form.qty) }}</span>
                    </div>
                </div>
                <div class="text-right mt-2 relative z-10 mb-8">
                    <span class="text-[10px] font-bold italic text-black">Metode: {{ form.method }}</span>
                </div>

                <!-- Signatures -->
                <div class="grid grid-cols-2 gap-4 mt-8 relative z-10 border border-gray-300 rounded-xl p-4 bg-white text-center text-xs">
                    <div>
                        <p class="text-red-600 font-bold mb-12 uppercase tracking-wider">CUSTOMER / PEMBELI</p>
                        <div class="border-b border-gray-400 w-32 mx-auto mb-1"></div>
                        <p class="font-black uppercase text-black">{{ form.customerName }}</p>
                    </div>
                    <div>
                        <p class="text-red-600 font-bold mb-12 uppercase tracking-wider">HORMAT KAMI</p>
                        <div class="border-b border-gray-400 w-32 mx-auto mb-1"></div>
                        <p class="font-black uppercase text-black">{{ form.csName }}</p>
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
import { ref } from 'vue';

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
    method: 'transfer BCA',
    notes: 'spider, 100% 26.5'
});

const formatNumber = (num) => {
    if (!num) return '0';
    return Number(num).toLocaleString('id-ID');
};

const printNota = () => {
    window.print();
};
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap');

.nota-box {
    font-family: 'Inter', sans-serif;
    max-width: 500px;
    margin: 0 auto;
    background: white;
    position: relative;
    border-radius: 12px;
    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
    overflow: hidden;
}

@media print {
    body * {
        visibility: hidden;
    }
    
    .print-area, .print-area * {
        visibility: visible !important;
    }
    
    .print-area {
        position: fixed;
        left: 0;
        top: 0;
        width: 100vw;
        height: 100vh;
        background: white;
        z-index: 999999;
        display: flex;
        justify-content: center;
        align-items: flex-start;
        padding-top: 20px;
    }

    .nota-box {
        box-shadow: none !important;
        border: none !important;
        width: 100% !important;
        max-width: 800px !important;
        margin: 0 auto !important;
    }

    .no-print {
        display: none !important;
    }

    /* Print backgrounds */
    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
</style>
