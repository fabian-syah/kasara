const fs = require('fs');
const file = 'd:/bian/apex-frontend/frontend/src/views/sales/CreateSale.vue';
let content = fs.readFileSync(file, 'utf8');
const startTag = '<template>';
const endTag = '</template>';
const startIndex = content.indexOf(startTag);
const endIndex = content.lastIndexOf(endTag) + endTag.length;
if (startIndex !== -1 && endIndex !== -1) {
    const newTemplate = `<template>
    <div class="max-w-[1600px] mx-auto px-2 py-4 h-[calc(100vh-6rem)] flex flex-col pt-0 pb-0">

        <!-- TOP BAR: Settings & Actions -->
        <div class="bg-white dark:bg-surface-800 rounded-2xl border border-surface-200 dark:border-surface-700 p-4 mb-4 shadow-sm flex flex-col md:flex-row gap-4 items-center justify-between shrink-0">
            <div class="flex flex-col md:flex-row items-center gap-4 w-full md:w-auto">
                <div class="w-full md:w-64">
                    <select v-model="salesAccount"
                        class="w-full border border-surface-200 dark:border-surface-700 rounded-xl px-4 py-2 bg-surface-50 dark:bg-surface-900 text-text-primary font-bold focus:outline-none focus:ring-2 focus:ring-primary-500/20 transition-all">
                        <option value="" disabled>-- Pilih Akun Sales --</option>
                        <option v-for="account in salesAccounts" :key="account.id" :value="account.full_name || account.name">
                            {{ account.full_name || account.name }}
                        </option>
                    </select>
                </div>
                <!-- Categories as compact pills -->
                <div class="flex gap-2 overflow-x-auto custom-scrollbar pb-1 md:pb-0 w-full md:w-auto">
                    <button v-for="cat in categoriesPenjualan" :key="cat.id" @click="transactionCategory = cat.id"
                        class="px-4 py-2 rounded-xl text-xs font-bold whitespace-nowrap transition-all border"
                        :class="transactionCategory === cat.id
                            ? 'bg-primary-500 text-white border-primary-500 shadow-md shadow-primary-500/20'
                            : 'bg-surface-50 dark:bg-surface-900 text-text-secondary border-surface-200 dark:border-surface-700 hover:border-primary-500/50 hover:text-primary-500'">
                        {{ cat.label }}
                    </button>
                    <!-- Bundling Toggle Mode (Inside Top Bar) -->
                    <div v-if="transactionCategory === 'penjualan' || transactionCategory === 'bundling'" 
                         class="flex items-center gap-2 px-3 py-1 bg-surface-100 dark:bg-surface-800 rounded-xl ml-auto md:ml-2 shrink-0 md:border md:border-surface-200 dark:border-surface-700">
                        <span class="text-[10px] font-bold text-text-secondary uppercase">Bundling</span>
                        <button @click="toggleBundling"
                            class="w-8 h-4 rounded-full relative transition-all duration-300"
                            :class="isBundling ? 'bg-primary-500' : 'bg-surface-300 dark:bg-surface-600'">
                            <div class="absolute top-[2px] w-3 h-3 bg-white rounded-full transition-all duration-300 shadow-sm"
                                :class="isBundling ? 'left-[18px]' : 'left-0.5'"></div>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- MAIN CONTENT AREA -->
        <div class="flex-1 flex flex-col lg:flex-row gap-4 min-h-0">
            <!-- LEFT PANEL: Product Selection -->
            <div class="flex-[3] flex flex-col min-w-0 bg-white dark:bg-surface-800 rounded-2xl border border-surface-200 dark:border-surface-700 shadow-sm overflow-hidden">
                <!-- Product Search -->
                <div class="p-4 border-b border-surface-100 dark:border-surface-700 bg-surface-50 dark:bg-surface-900 shrink-0">
                    <div class="relative">
                        <Search class="absolute left-4 top-1/2 -translate-y-1/2 text-text-secondary" :size="18" />
                        <input v-model="searchQuery" type="text" placeholder="Cari IMEI, Brand, atau Nama Produk..."
                            class="w-full bg-white dark:bg-surface-800 border border-surface-200 dark:border-surface-700 rounded-xl pl-12 pr-4 py-2.5 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/20 transition-all font-medium shadow-sm" />
                    </div>
                </div>
                
                <!-- Product List -->
                <div class="flex-1 overflow-y-auto custom-scrollbar">
                    <table class="w-full text-left border-collapse">
                        <thead class="sticky top-0 bg-surface-50/90 dark:bg-surface-900/90 backdrop-blur-md z-10">
                            <tr>
                                <th class="px-4 py-3 text-[10px] font-bold text-text-secondary uppercase tracking-wider border-b border-surface-100 dark:border-surface-700">Produk & Brand</th>
                                <th class="px-4 py-3 text-[10px] font-bold text-text-secondary uppercase tracking-wider border-b border-surface-100 dark:border-surface-700">Spek & Kondisi</th>
                                <th class="px-4 py-3 text-[10px] font-bold text-text-secondary uppercase tracking-wider border-b border-surface-100 dark:border-surface-700">IMEI/Stok</th>
                                <th class="hidden xl:table-cell px-4 py-3 text-[10px] font-bold text-text-secondary uppercase tracking-wider border-b border-surface-100 dark:border-surface-700">Distributor</th>
                                <th class="px-4 py-3 text-[10px] font-bold text-text-secondary uppercase tracking-wider border-b border-surface-100 dark:border-surface-700 text-right">Harga</th>
                                <th class="px-4 py-3 text-[10px] font-bold text-text-secondary uppercase tracking-wider border-b border-surface-100 dark:border-surface-700 text-center w-16">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-100 dark:divide-surface-700">
                            <tr v-for="item in filteredProducts" :key="item.id" class="hover:bg-surface-50 dark:hover:bg-surface-900/50 transition-colors group">
                                <td class="px-4 py-3">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-text-primary text-xs">{{ item.product?.name || item.name }}</span>
                                        <span class="text-[9px] text-text-secondary uppercase font-bold mt-0.5">{{ item.product?.brand || '-' }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-col gap-1">
                                        <span class="text-[11px] font-bold text-text-primary">{{ item.ram || '-' }}/{{ item.storage || '-' }}</span>
                                        <span class="text-[9px] uppercase px-1.5 py-0.5 rounded pl-0 w-fit" :class="item.condition === 'new' ? 'text-emerald-500 font-bold' : 'text-amber-500 font-bold'">
                                            {{ item.condition || 'Second' }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <code v-if="item.imei" class="text-[10px] font-mono font-bold bg-surface-100 dark:bg-surface-900 px-1.5 py-0.5 rounded border border-surface-200 dark:border-surface-700 text-text-primary">
                                        {{ item.imei }}
                                    </code>
                                    <span v-else class="text-[11px] font-bold text-primary-600 bg-primary-500/10 px-2 py-0.5 rounded">
                                        Stok: {{ item.quantity || 0 }}
                                    </span>
                                </td>
                                <td class="hidden xl:table-cell px-4 py-3">
                                    <span class="text-[11px] font-semibold text-text-secondary">{{ item.distributor?.name || item.supplier_name || '-' }}</span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <span class="text-xs font-black text-primary-600">{{ formatCurrency(item.selling_price || item.price) }}</span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <button @click="addToCart(item)" class="p-1.5 bg-primary-100 text-primary-600 hover:bg-primary-600 hover:text-white dark:bg-primary-900 dark:text-primary-400 dark:hover:bg-primary-600 dark:hover:text-white rounded-lg transition-all active:scale-95 w-full flex justify-center shadow-sm">
                                        <Plus :size="14" stroke-width="3" />
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="filteredProducts.length === 0">
                                <td colspan="6" class="px-4 py-20 text-center text-text-secondary italic">
                                    Data tidak ditemukan atau stok kosong.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- RIGHT PANEL: Cart & Payment POS -->
            <div class="flex-[2] flex flex-col bg-white dark:bg-surface-800 rounded-2xl border border-surface-200 dark:border-surface-700 shadow-sm overflow-hidden min-w-[350px]">
                
                <!-- Cart Header -->
                <div class="p-3 border-b border-surface-100 dark:border-surface-700 bg-surface-50 dark:bg-surface-900 flex items-center justify-between font-bold shrink-0">
                    <div class="flex items-center gap-2">
                        <ShoppingCart :size="16" class="text-primary-500" /> 
                        <span class="text-sm">Keranjang ({{ cartItemCount }})</span>
                    </div>
                </div>

                <!-- Cart Items List -->
                <div class="flex-1 overflow-y-auto p-3 custom-scrollbar">
                    <div v-if="cartItems.length === 0" class="h-full flex flex-col items-center justify-center text-text-secondary opacity-50">
                        <ShoppingCart :size="32" class="mb-3" />
                        <p class="text-xs font-bold">Keranjang Kosong</p>
                    </div>
                    <div v-else class="space-y-3">
                        <div v-for="item in cartItems" :key="item.id" class="p-3 bg-surface-50 dark:bg-surface-900 rounded-xl relative border border-transparent hover:border-surface-200 dark:hover:border-surface-700 transition-all">
                            <button @click="removeFromCart(item.id)" class="text-surface-400 hover:text-red-500 absolute top-2 right-2 bg-white dark:bg-surface-800 rounded-full p-1 shadow-sm border border-surface-100 dark:border-surface-700">
                                <X :size="12" stroke-width="3" />
                            </button>
                            <div class="pr-6 mb-2">
                                <p class="text-[11px] font-bold text-text-primary leading-tight line-clamp-2">{{ item.product?.name || item.name }}</p>
                                <p class="text-[9px] font-mono text-text-secondary mt-0.5">{{ item.imei || 'Non-IMEI' }}</p>
                            </div>
                            
                            <div class="flex justify-between items-end mt-2 pt-2 border-t border-surface-200 dark:border-surface-700">
                                <div class="flex items-center gap-1.5">
                                    <template v-if="!item.imei">
                                        <button @click="decrementQty(item.id)" class="w-5 h-5 flex items-center justify-center bg-white dark:bg-surface-800 border border-surface-200 dark:border-surface-700 rounded text-text-primary hover:bg-surface-100 transition-colors font-bold text-xs">-</button>
                                        <span class="text-[10px] px-1 font-bold w-4 text-center">{{ item.quantity }}</span>
                                        <button @click="incrementQty(item.id)" class="w-5 h-5 flex items-center justify-center bg-white dark:bg-surface-800 border border-surface-200 dark:border-surface-700 rounded text-text-primary hover:bg-surface-100 transition-colors font-bold text-xs">+</button>
                                    </template>
                                    <template v-else>
                                         <span class="text-[10px] font-bold px-2 py-0.5 bg-surface-200 dark:bg-surface-700 rounded text-text-secondary">1x</span>
                                    </template>
                                </div>
                                
                                <div v-if="!item.imei" class="flex flex-col items-end">
                                    <div class="flex items-center gap-1 bg-white dark:bg-surface-800 border border-surface-200 dark:border-surface-700 rounded px-1.5 py-0.5 focus-within:border-primary-500 transition-all w-[90px]">
                                        <span class="text-[9px] text-text-secondary font-bold">Rp</span>
                                        <input type="text" :value="formatNumber(item.price)" @input="e => handleItemPriceInput(item, e)"
                                            class="w-full text-right text-[11px] font-bold bg-transparent outline-none text-text-primary focus:text-primary-600" />
                                    </div>
                                </div>
                                <p v-else class="text-xs font-black text-primary-600">{{ formatCurrency(item.selling_price || item.price) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Forms & Payment (Sticky at bottom of right column) -->
                <div class="border-t border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800 p-3 pt-4 shrink-0 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)] z-20 sticky bottom-0">
                    
                    <!-- Customer Quick Form (Compact) -->
                    <div class="grid grid-cols-2 gap-2 mb-3">
                        <input v-model="customerForm.customer_name" type="text" placeholder="Nama Plg (Opsional)"
                            class="w-full border border-surface-200 dark:border-surface-700 rounded px-2.5 py-1.5 bg-surface-50 dark:bg-surface-900 text-[10px] font-medium text-text-primary focus:outline-none focus:border-primary-500" />
                        <input v-model="customerForm.customer_phone" type="text" placeholder="No. HP (Opsional)"
                            class="w-full border border-surface-200 dark:border-surface-700 rounded px-2.5 py-1.5 bg-surface-50 dark:bg-surface-900 text-[10px] font-medium text-text-primary focus:outline-none focus:border-primary-500" />
                    </div>

                    <!-- Discount Row (Compact) -->
                    <div class="flex items-center justify-between mb-3 bg-surface-50 dark:bg-surface-900 p-2 rounded-lg border border-surface-100 dark:border-surface-700">
                        <span class="text-[11px] font-bold text-text-primary pl-1">Diskon</span>
                        <div class="flex items-center gap-1">
                            <div class="flex rounded-md overflow-hidden border border-surface-200 dark:border-surface-700">
                                <button @click="cartStore.discountType = 'percentage'" class="px-2 py-1 text-[9px] font-bold transition-all" :class="cartStore.discountType === 'percentage' ? 'bg-primary-500 text-white' : 'bg-white dark:bg-surface-800 text-text-secondary'">%</button>
                                <button @click="cartStore.discountType = 'fixed'" class="px-2 py-1 text-[9px] font-bold transition-all" :class="cartStore.discountType === 'fixed' ? 'bg-primary-500 text-white' : 'bg-white dark:bg-surface-800 text-text-secondary'">Rp</button>
                            </div>
                            <input :value="displayDiscount" @input="handleDiscountInput" type="text"
                                class="w-[80px] border border-surface-200 dark:border-surface-700 rounded px-2 py-1 bg-white dark:bg-surface-800 text-[11px] font-bold text-right focus:outline-none focus:border-primary-500" placeholder="0" />
                        </div>
                    </div>

                    <!-- Payment Methods (Compact Grid) -->
                    <div class="mb-3">
                        <span class="text-[10px] font-bold text-text-secondary uppercase mb-1.5 block">Metode Pembayaran</span>
                        <div class="flex gap-2 overflow-x-auto custom-scrollbar pb-1">
                            <button v-for="method in availablePaymentMethods" :key="method.id" @click="selectedPaymentMethod = method.id"
                                class="px-3 py-1.5 rounded-lg border flex items-center gap-1.5 shrink-0 transition-all font-bold text-[10px] uppercase"
                                :class="selectedPaymentMethod === method.id ? 'border-primary-500 bg-primary-500/10 text-primary-600' : 'border-surface-200 dark:border-surface-700 text-text-secondary bg-surface-50 dark:bg-surface-900 hover:border-surface-300'">
                                <CreditCard v-if="method.category?.toLowerCase() === 'bank' || method.category?.toLowerCase() === 'transfer' || method.category?.toLowerCase() === 'edc'" :size="12" />
                                <QrCode v-else-if="method.category?.toLowerCase() === 'e-wallet' || method.category?.toLowerCase() === 'qris'" :size="12" />
                                <Banknote v-else :size="12" />
                                {{ method.name }}
                            </button>
                        </div>
                    </div>

                    <!-- Cash Input Block (Conditional based on isCashPayment) -->
                     <div v-if="isCashPayment" class="mb-3 bg-surface-50 dark:bg-surface-900 p-2 rounded-lg border border-surface-100 dark:border-surface-700">
                        <div class="flex justify-between items-center mb-1.5">
                            <span class="text-[10px] font-bold text-text-primary">Bayar Tunai</span>
                             <div class="flex gap-1">
                                <button @click="paymentAmount = paymentAmount + 50000" class="px-1.5 py-0.5 bg-white dark:bg-surface-800 border border-surface-200 dark:border-surface-700 rounded text-[9px] font-bold">+50k</button>
                                <button @click="paymentAmount = paymentAmount + 100000" class="px-1.5 py-0.5 bg-white dark:bg-surface-800 border border-surface-200 dark:border-surface-700 rounded text-[9px] font-bold">+100k</button>
                                <button @click="paymentAmount = cartStore.total" class="px-1.5 py-0.5 bg-primary-500/10 text-primary-600 border border-primary-500/20 rounded text-[9px] font-bold">Pas</button>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 bg-white dark:bg-surface-800 border border-surface-200 dark:border-surface-700 rounded focus-within:border-primary-500 px-2 py-1 w-full">
                            <span class="text-[11px] font-bold text-text-secondary">Rp</span>
                            <input :value="displayPaymentAmount" @input="handlePaymentInput" type="text"
                                class="w-full text-right text-sm font-black bg-transparent outline-none text-text-primary focus:text-primary-600" />
                        </div>
                    </div>

                    <!-- Change Calculation Text (Compact) -->
                    <div v-if="isCashPayment && cartTotal > 0 && paymentAmount > 0" class="mb-3 px-1 flex justify-between items-center text-[11px] font-bold">
                        <span v-if="changeAmount >= 0" class="text-emerald-500">Kembalian: {{ formatCurrency(changeAmount) }}</span>
                        <span v-else class="text-red-500">Kurang: {{ formatCurrency(Math.abs(changeAmount)) }}</span>
                    </div>

                    <!-- Total & Submit Button -->
                    <div class="flex gap-3 mt-auto">
                        <div class="flex-1 bg-surface-50 dark:bg-surface-900 rounded-xl p-2.5 border border-surface-200 dark:border-surface-700 flex flex-col justify-center">
                            <span class="text-[9px] font-bold text-text-secondary uppercase leading-none mb-1">Total Bayar</span>
                            <span class="text-lg font-black text-primary-600 leading-none">{{ formatCurrency(cartTotal) }}</span>
                        </div>
                        <button @click="handleSubmitOrder" :disabled="(isCashPayment && changeAmount < 0) || isSubmitting || cartItems.length === 0"
                            class="flex-1 py-2.5 px-4 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 disabled:bg-surface-300 dark:disabled:bg-surface-700 text-white rounded-xl font-bold text-sm shadow-md shadow-emerald-500/20 transition-all flex items-center justify-center gap-2">
                            <Loader2 v-if="isSubmitting" class="animate-spin" :size="16" />
                            <ShoppingCart v-else :size="16" />
                            BAYAR
                        </button>
                    </div>

                </div>
            </div>
        </div>

        <!-- SUCCESS MODAL -->
        <Teleport to="body">
            <div v-if="showSuccessModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/80 backdrop-blur-md"></div>
                <div class="relative bg-white dark:bg-surface-800 rounded-3xl border border-surface-200 dark:border-surface-700 w-full max-w-sm p-8 text-center shadow-2xl">
                    <div class="w-20 h-20 mx-auto mb-5 bg-emerald-500/20 rounded-full flex items-center justify-center animate-bounce">
                        <CheckCircle class="text-emerald-500" :size="40" />
                    </div>
                    <h3 class="text-2xl font-black text-text-primary mb-1">Sukses!</h3>
                    <p class="text-sm font-medium text-text-secondary mb-6">Transaksi berhasil diproses.</p>

                    <div v-if="lastTransaction" class="bg-surface-50 dark:bg-surface-900 rounded-2xl p-4 mb-6 text-left space-y-2">
                        <div class="flex justify-between text-[11px] font-medium border-b border-surface-200 dark:border-surface-700 pb-2">
                            <span class="text-text-secondary">Receipt ID</span>
                            <span class="font-mono">{{ lastTransaction.id }}</span>
                        </div>
                        <div class="flex justify-between text-[11px] font-medium border-b border-surface-200 dark:border-surface-700 pb-2">
                            <span class="text-text-secondary">Sales</span>
                            <span>{{ lastTransaction.sales_account }}</span>
                        </div>
                        <div class="flex justify-between text-sm font-black pt-1">
                            <span class="text-text-primary">Total</span>
                            <span class="text-emerald-500">{{ formatCurrency(lastTransaction.total) }}</span>
                        </div>
                    </div>

                    <button @click="closeSuccessModal" class="w-full py-3.5 bg-primary-600 hover:bg-primary-500 text-white rounded-xl font-bold transition-all shadow-lg shadow-primary-500/30 mb-2 text-sm">
                        Transaksi Baru
                    </button>
                    <button class="w-full py-2.5 text-text-secondary font-bold flex items-center justify-center gap-1.5 text-xs hover:bg-surface-50 dark:hover:bg-surface-900 rounded-lg transition-colors">
                        <Receipt :size="14" /> Cetak Struk
                    </button>
                </div>
            </div>
        </Teleport>
    </div>
</template>`;
    content = content.substring(0, startIndex) + newTemplate + content.substring(endIndex);
    fs.writeFileSync(file, content, 'utf8');
    console.log('Template replaced successfully');
} else {
    console.log('Could not find template tags');
}
