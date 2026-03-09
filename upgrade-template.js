const fs = require('fs');
const file = 'd:/bian/apex-frontend/frontend/src/views/sales/CreateSale.vue';
let content = fs.readFileSync(file, 'utf8');

const startTag = '<template>';
const endTag = '</template>';
const startIndex = content.indexOf(startTag);
const endIndex = content.lastIndexOf(endTag) + endTag.length;

if (startIndex !== -1 && endIndex !== -1) {
    const newTemplate = `<template>
    <div class="max-w-[1600px] mx-auto px-4 py-8 h-[calc(100vh-8rem)]">
        <!-- Progress Bar -->
        <div class="mb-12 max-w-5xl mx-auto">
            <div class="flex items-center justify-between relative">
                <div
                    class="absolute left-0 right-0 top-1/2 -translate-y-1/2 h-1 bg-surface-200 dark:bg-surface-700 -z-10 mx-8 rounded-full">
                    <div class="h-full bg-primary-500 transition-all duration-500 ease-out rounded-full"
                        :style="{ width: \`\${((currentStep - 1) / 3) * 100}%\` }"></div>
                </div>

                <div v-for="step in 4" :key="step" class="flex flex-col items-center gap-3">
                    <div class="w-14 h-14 rounded-full flex items-center justify-center font-bold text-lg transition-all duration-300 shadow-sm"
                        :class="currentStep >= step ? 'bg-primary-600 text-white shadow-primary-500/30 scale-110' : 'bg-white dark:bg-surface-800 text-text-secondary border-2 border-surface-200 dark:border-surface-700'">
                        <CheckCircle v-if="currentStep > step" :size="24" />
                        <span v-else>{{ step }}</span>
                    </div>
                    <span class="text-xs font-bold uppercase tracking-widest transition-colors"
                        :class="currentStep >= step ? 'text-primary-600' : 'text-text-secondary'">
                        {{ ['Akun Sales', 'Kategori', 'Pilih Barang', 'Pembayaran'][step - 1] }}
                    </span>
                </div>
            </div>
        </div>

        <!-- content area -->
        <div class="h-full flex flex-col transition-all duration-300">
            <!-- STEP 1: ACCOUNT SELECTION -->
            <div v-if="currentStep === 1" class="flex-1 flex flex-col justify-center max-w-3xl mx-auto w-full animate-fade-in">
                <div
                    class="bg-white dark:bg-surface-800 rounded-[2rem] border border-surface-200 dark:border-surface-700 p-10 shadow-xl text-center">
                    <div
                        class="w-24 h-24 bg-primary-500/10 text-primary-500 rounded-[2rem] flex items-center justify-center mx-auto mb-8">
                        <User :size="48" stroke-width="1.5" />
                    </div>
                    <h2 class="text-4xl font-black text-text-primary mb-3">Akun Sales</h2>
                    <p class="text-text-secondary text-lg mb-10">Pilih nama akun utama yang bertanggung jawab pada transaksi ini</p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
                        <button v-for="account in salesAccounts" :key="account.id"
                            @click="salesAccount = (account.full_name || account.name); nextStep()"
                            class="p-6 rounded-2xl border-2 transition-all flex items-center gap-4 text-left group"
                            :class="salesAccount === (account.full_name || account.name)
                                ? 'border-primary-500 bg-primary-500/5 shadow-lg shadow-primary-500/10'
                                : 'border-surface-200 dark:border-surface-700 hover:border-primary-500/50 hover:bg-surface-50 dark:hover:bg-surface-900/50'">
                            <div class="w-12 h-12 rounded-full flex items-center justify-center transition-colors"
                                 :class="salesAccount === (account.full_name || account.name) ? 'bg-primary-500 text-white' : 'bg-surface-100 dark:bg-surface-800 text-text-secondary group-hover:bg-primary-100 group-hover:text-primary-600'">
                                <User :size="20" />
                            </div>
                            <span class="font-bold text-lg text-text-primary group-hover:text-primary-600 transition-colors">{{ account.full_name || account.name }}</span>
                        </button>
                    </div>

                    <div class="flex justify-end mt-8 border-t border-surface-100 dark:border-surface-700 pt-8">
                        <button @click="nextStep" :disabled="!salesAccount"
                            class="py-4 px-10 bg-primary-600 hover:bg-primary-500 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-2xl font-bold text-lg shadow-xl shadow-primary-500/20 transition-all flex items-center gap-3">
                            Lanjut
                            <ArrowRight :size="24" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- STEP 2: CATEGORY SELECTION -->
            <div v-if="currentStep === 2" class="flex-1 flex flex-col justify-center max-w-4xl mx-auto w-full animate-fade-in">
                <div
                    class="bg-white dark:bg-surface-800 rounded-[2rem] border border-surface-200 dark:border-surface-700 p-10 shadow-xl">
                    <div class="text-center mb-10">
                        <h2 class="text-4xl font-black text-text-primary mb-3">Kategori Transaksi</h2>
                        <p class="text-text-secondary text-lg">Pilih jenis transaksi yang akan dilakukan oleh <strong class="text-primary-600">{{ salesAccount }}</strong></p>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-3 gap-6 mb-12">
                        <button v-for="cat in categoriesPenjualan" :key="cat.id" 
                            @click="transactionCategory = cat.id; nextStep()"
                            class="p-8 rounded-[1.5rem] border-2 transition-all duration-300 flex flex-col items-center gap-4 relative overflow-hidden group hover:-translate-y-1 hover:shadow-xl"
                            :class="transactionCategory === cat.id
                                ? 'border-primary-500 bg-primary-500/5 shadow-primary-500/10'
                                : 'border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-900/50 hover:border-primary-300'">
                            <div
                                class="w-16 h-16 bg-white dark:bg-surface-800 rounded-[1.25rem] shadow-sm flex items-center justify-center transition-colors"
                                :class="transactionCategory === cat.id ? 'ring-2 ring-primary-500 ring-offset-2 dark:ring-offset-surface-800' : 'group-hover:text-primary-500'">
                                <ShoppingBag :size="32"
                                    :class="transactionCategory === cat.id ? 'text-primary-500' : 'text-text-secondary group-hover:text-primary-500'" stroke-width="1.5" />
                            </div>
                            <span class="font-bold text-lg text-text-primary group-hover:text-primary-600 transition-colors">{{ cat.label }}</span>
                            <div v-if="transactionCategory === cat.id" class="absolute top-4 right-4 text-primary-500 bg-white dark:bg-surface-800 rounded-full shadow-sm p-0.5">
                                <CheckCircle :size="20" />
                            </div>
                        </button>
                    </div>

                    <div class="flex justify-between border-t border-surface-100 dark:border-surface-700 pt-8">
                        <button @click="prevStep"
                            class="py-4 px-8 bg-surface-100 dark:bg-surface-700 hover:bg-surface-200 dark:hover:bg-surface-600 text-text-primary rounded-2xl font-bold text-lg transition-all flex items-center gap-3">
                            <ArrowLeft :size="24" /> Kembali
                        </button>
                        <button @click="nextStep"
                            class="py-4 px-10 bg-primary-600 hover:bg-primary-500 text-white rounded-2xl font-bold text-lg shadow-xl shadow-primary-500/20 transition-all flex items-center gap-3">
                            Lanjut
                            <ArrowRight :size="24" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- STEP 3: ITEM SELECTION -->
            <div v-if="currentStep === 3" class="flex-1 flex flex-col lg:flex-row gap-8 min-h-0 animate-fade-in">
                <!-- Products -->
                <div class="flex-[2] flex flex-col min-w-0">
                    <div
                        class="bg-white dark:bg-surface-800 rounded-[1.5rem] border border-surface-200 dark:border-surface-700 p-6 mb-6 shadow-sm flex flex-col md:flex-row gap-4 items-center">
                        <div class="relative flex-1 w-full">
                            <Search class="absolute left-5 top-1/2 -translate-y-1/2 text-text-secondary" :size="24" />
                            <input v-model="searchQuery" type="text" placeholder="Cari IMEI, Brand, atau Nama Produk..."
                                class="w-full bg-surface-50 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 rounded-2xl pl-14 pr-6 py-4 text-lg font-medium text-text-primary focus:outline-none focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 transition-all" />
                        </div>

                        <!-- Bundling Toggle (Only for Penjualan flow) -->
                        <div v-if="transactionCategory === 'penjualan' || transactionCategory === 'bundling'"
                            class="flex items-center gap-4 px-6 py-4 bg-surface-50 dark:bg-surface-900 rounded-2xl border border-surface-200 dark:border-surface-700 whitespace-nowrap">
                            <span class="text-sm font-bold text-text-secondary uppercase tracking-wider">Mode Bundling</span>
                            <button @click="toggleBundling"
                                class="w-14 h-8 rounded-full relative transition-all duration-300"
                                :class="isBundling ? 'bg-primary-500' : 'bg-surface-300 dark:bg-surface-600'">
                                <div class="absolute top-1 w-6 h-6 bg-white rounded-full transition-all duration-300 shadow-sm"
                                    :class="isBundling ? 'left-7' : 'left-1'"></div>
                            </button>
                        </div>
                    </div>

                    <div
                        class="flex-1 overflow-y-auto custom-scrollbar bg-white dark:bg-surface-800 rounded-[1.5rem] border border-surface-200 dark:border-surface-700 mb-4 shadow-sm">
                        <table class="w-full text-left border-collapse">
                            <thead class="sticky top-0 bg-surface-50/95 dark:bg-surface-900/95 backdrop-blur-sm z-10">
                                <tr>
                                    <th
                                        class="px-6 py-5 text-xs font-black text-text-secondary uppercase tracking-widest border-b border-surface-200 dark:border-surface-700">
                                        Produk & Brand</th>
                                    <th
                                        class="px-6 py-5 text-xs font-black text-text-secondary uppercase tracking-widest border-b border-surface-200 dark:border-surface-700">
                                        Spek & Kondisi</th>
                                    <th
                                        class="px-6 py-5 text-xs font-black text-text-secondary uppercase tracking-widest border-b border-surface-200 dark:border-surface-700">
                                        IMEI / Stok</th>
                                    <th
                                        class="hidden xl:table-cell px-6 py-5 text-xs font-black text-text-secondary uppercase tracking-widest border-b border-surface-200 dark:border-surface-700">
                                        Distributor</th>
                                    <th
                                        class="px-6 py-5 text-xs font-black text-text-secondary uppercase tracking-widest border-b border-surface-200 dark:border-surface-700 text-right">
                                        Harga</th>
                                    <th
                                        class="px-6 py-5 border-b border-surface-200 dark:border-surface-700 w-24">
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-surface-100 dark:divide-surface-700">
                                <tr v-for="item in filteredProducts" :key="item.id"
                                    class="hover:bg-primary-50/50 dark:hover:bg-primary-900/10 transition-colors group">
                                    <td class="px-6 py-5">
                                        <div class="flex flex-col gap-1">
                                            <span class="font-black text-text-primary text-base">{{ item.product?.name ||
                                                item.name }}</span>
                                            <span class="text-xs text-primary-600 font-bold uppercase tracking-wider">{{
                                                item.product?.brand || '-' }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-5">
                                        <div class="flex flex-col items-start gap-2">
                                            <span class="text-sm font-bold text-text-primary bg-surface-100 dark:bg-surface-800 px-3 py-1 rounded-lg">{{ item.ram || '-'
                                                }} / {{ item.storage || '-' }}</span>
                                            <span
                                                class="text-xs uppercase px-3 py-1 rounded-lg font-bold"
                                                :class="item.condition === 'new' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400'">
                                                {{ item.condition || 'Second' }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-5">
                                        <code v-if="item.imei"
                                            class="text-sm font-mono font-bold text-text-primary bg-surface-50 dark:bg-surface-900 px-3 py-1.5 rounded-lg border border-surface-200 dark:border-surface-700">
                                            {{ item.imei }}
                                        </code>
                                        <span v-else
                                            class="text-sm font-black text-primary-600 bg-primary-500/10 px-4 py-1.5 rounded-lg">
                                            Stok: {{ item.quantity || 0 }}
                                        </span>
                                    </td>
                                    <td class="hidden xl:table-cell px-6 py-5">
                                        <span class="text-sm font-semibold text-text-secondary">{{ item.distributor?.name ||
                                            item.supplier_name || '-' }}</span>
                                    </td>
                                    <td class="px-6 py-5 text-right">
                                        <span class="text-lg font-black text-primary-600">{{
                                            formatCurrency(item.selling_price || item.price) }}</span>
                                    </td>
                                    <td class="px-6 py-5 text-right">
                                        <button @click="addToCart(item)"
                                            class="w-12 h-12 flex items-center justify-center bg-primary-100 text-primary-600 hover:bg-primary-600 hover:text-white dark:bg-primary-900/50 dark:text-primary-400 dark:hover:bg-primary-600 dark:hover:text-white rounded-xl transition-all shadow-sm active:scale-95 ml-auto">
                                            <Plus :size="24" stroke-width="3" />
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="filteredProducts.length === 0">
                                    <td colspan="6" class="px-6 py-32 text-center">
                                        <div class="flex flex-col items-center justify-center text-text-secondary">
                                            <Search :size="48" class="mb-4 opacity-50" />
                                            <span class="text-lg font-medium">Produk tidak ditemukan atau stok kosong.</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Cart Sidebar (Sticky in step 3) -->
                <div
                    class="w-full lg:w-[450px] flex flex-col bg-white dark:bg-surface-800 rounded-[1.5rem] border border-surface-200 dark:border-surface-700 shadow-xl overflow-hidden shrink-0">
                    <div
                        class="p-6 border-b border-surface-100 dark:border-surface-700 bg-surface-50 dark:bg-surface-900 flex items-center justify-between font-bold">
                        <div class="flex items-center gap-3">
                            <ShoppingCart :size="24" class="text-primary-500" stroke-width="2.5" /> 
                            <span class="text-xl">Keranjang <span class="text-primary-500 font-black px-2 py-0.5 bg-primary-500/10 rounded-lg ml-1">{{ cartItemCount }}</span></span>
                        </div>
                    </div>
                    
                    <div class="flex-1 overflow-y-auto p-6 custom-scrollbar">
                        <div v-if="cartItems.length === 0"
                            class="h-full flex flex-col items-center justify-center text-text-secondary opacity-50">
                            <ShoppingCart :size="64" class="mb-6" stroke-width="1.5" />
                            <p class="text-xl font-medium">Keranjang Kosong</p>
                            <p class="text-sm mt-2">Pilih produk dari daftar di sebelah kiri.</p>
                        </div>
                        <div v-else class="space-y-4">
                            <div v-for="item in cartItems" :key="item.id"
                                class="p-5 bg-white dark:bg-surface-800 border-2 border-surface-100 dark:border-surface-700 rounded-2xl relative shadow-sm group hover:border-surface-300 dark:hover:border-surface-600 transition-colors">
                                <div class="flex justify-between items-start mb-4 pr-8">
                                    <div class="min-w-0 flex flex-col gap-1">
                                        <p class="text-sm font-black text-text-primary line-clamp-2 leading-tight">{{ item.product?.name ||
                                            item.name }}</p>
                                        <span v-if="item.imei" class="text-xs font-mono font-bold text-text-secondary bg-surface-50 dark:bg-surface-900 px-2 py-1 rounded w-fit">{{ item.imei }}</span>
                                    </div>
                                    <button @click="removeFromCart(item.id)"
                                        class="text-surface-400 hover:text-red-500 absolute top-4 right-4 bg-surface-50 dark:bg-surface-900 p-2 rounded-full transition-colors">
                                        <Trash2 :size="18" />
                                    </button>
                                </div>
                                <div class="flex justify-between items-end border-t border-surface-100 dark:border-surface-700 pt-4">
                                    <div class="flex items-center gap-3">
                                        <button v-if="!item.imei" @click="decrementQty(item.id)"
                                            class="w-8 h-8 flex items-center justify-center bg-surface-100 dark:bg-surface-700 rounded-lg text-text-primary hover:bg-surface-200 transition-colors font-black">-</button>
                                        <span
                                            class="text-sm font-black px-2">
                                            {{ item.quantity }}<span class="text-text-secondary font-medium ml-1">x</span>
                                        </span>
                                        <button v-if="!item.imei" @click="incrementQty(item.id)"
                                            class="w-8 h-8 flex items-center justify-center bg-surface-100 dark:bg-surface-700 rounded-lg text-text-primary hover:bg-surface-200 transition-colors font-black">+</button>
                                    </div>
                                    <div v-if="!item.imei" class="flex flex-col items-end">
                                        <div
                                            class="flex items-center gap-2 border-2 border-surface-200 dark:border-surface-700 rounded-xl bg-surface-50 dark:bg-surface-900 px-3 py-2.5 focus-within:border-primary-500 transition-all">
                                            <span class="text-xs text-text-secondary font-bold">Rp</span>
                                            <input type="text" :value="formatNumber(item.price)"
                                                @input="e => handleItemPriceInput(item, e)"
                                                class="w-24 text-right text-sm font-black bg-transparent outline-none focus:text-primary-600" />
                                        </div>
                                    </div>
                                    <p v-else class="text-lg font-black text-primary-600">{{
                                        formatCurrency(item.selling_price || item.price) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        class="p-6 bg-surface-50 dark:bg-surface-900 mt-auto border-t border-surface-200 dark:border-surface-700 shrink-0">
                        <div class="flex justify-between items-center mb-6 text-2xl font-black">
                            <span class="text-text-primary text-lg uppercase tracking-widest">Total</span>
                            <span class="text-primary-600">{{ formatCurrency(cartTotal) }}</span>
                        </div>
                        <div class="flex gap-3">
                            <button @click="prevStep"
                                class="w-16 h-16 flex-none bg-white dark:bg-surface-800 text-text-primary border-2 border-surface-200 dark:border-surface-700 rounded-[1.25rem] font-bold transition-all flex items-center justify-center hover:bg-surface-50 hover:border-surface-300">
                                <ArrowLeft :size="24" />
                            </button>
                            <button @click="nextStep" :disabled="cartItems.length === 0"
                                class="flex-1 h-16 bg-primary-600 hover:bg-primary-500 disabled:opacity-50 text-white rounded-[1.25rem] font-bold text-lg shadow-xl shadow-primary-500/30 transition-all flex items-center justify-center gap-3">
                                Pembayaran <ArrowRight :size="24" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- STEP 4: FORM & PAYMENT -->
            <div v-if="currentStep === 4"
                class="flex-1 flex flex-col lg:flex-row gap-8 min-h-0 overflow-y-auto custom-scrollbar animate-fade-in">
                <!-- Transaction Summary & Form -->
                <div class="flex-[2] space-y-8 min-w-0">
                    <div
                        class="bg-white dark:bg-surface-800 rounded-[2rem] border border-surface-200 dark:border-surface-700 p-8 shadow-xl">
                        <h3 class="text-2xl font-black text-text-primary mb-8 flex items-center gap-3">
                            <User :size="28" class="text-primary-500" stroke-width="2.5" /> Detail Pelanggan
                        </h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-bold text-text-secondary uppercase tracking-widest mb-3">Nama Pelanggan (Opsional)</label>
                                <input v-model="customerForm.customer_name" type="text" placeholder="Masukkan nama..."
                                    class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-2xl px-5 py-4 bg-surface-50 dark:bg-surface-900 text-lg font-medium text-text-primary focus:outline-none focus:border-primary-500 focus:bg-white transition-all" />
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-text-secondary uppercase tracking-widest mb-3">No. HP (Opsional)</label>
                                <input v-model="customerForm.customer_phone" type="text" placeholder="08xxx..."
                                    class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-2xl px-5 py-4 bg-surface-50 dark:bg-surface-900 text-lg font-medium text-text-primary focus:outline-none focus:border-primary-500 focus:bg-white transition-all" />
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-bold text-text-secondary uppercase tracking-widest mb-3">Catatan Tambahan</label>
                                <textarea v-model="customerForm.notes" rows="3"
                                    placeholder="Catatan khusus untuk nota ini..."
                                    class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-2xl px-5 py-4 bg-surface-50 dark:bg-surface-900 text-lg font-medium text-text-primary focus:outline-none focus:border-primary-500 focus:bg-white transition-all resize-none"></textarea>
                            </div>
                        </div>
                    </div>

                    <div
                        class="bg-white dark:bg-surface-800 rounded-[2rem] border border-surface-200 dark:border-surface-700 p-8 shadow-xl">
                        <h3 class="text-2xl font-black text-text-primary mb-8 flex items-center gap-3">
                            <ShoppingCart :size="28" class="text-primary-500" stroke-width="2.5" /> Ringkasan Pembelian
                        </h3>
                        <div class="space-y-4">
                            <div v-for="item in cartItems" :key="item.id"
                                class="flex justify-between items-center bg-surface-50 dark:bg-surface-900 p-5 rounded-2xl border border-surface-100 dark:border-surface-700">
                                <div class="flex items-center gap-5">
                                    <div
                                        class="w-14 h-14 bg-white dark:bg-surface-800 border-2 border-surface-200 dark:border-surface-700 rounded-xl flex items-center justify-center font-black text-lg shadow-sm">
                                        {{ item.quantity }}<span class="text-xs text-text-secondary ml-0.5">x</span></div>
                                    <div class="flex flex-col gap-1">
                                        <p class="font-black text-lg text-text-primary">{{ item.name }}</p>
                                        <p class="text-sm font-bold text-text-secondary">{{ formatCurrency(item.price) }} / unit</p>
                                    </div>
                                </div>
                                <p class="font-black text-xl text-primary-600">{{ formatCurrency(item.price * item.quantity) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Block -->
                <div class="flex-[1.5] min-w-[350px]">
                    <div
                        class="bg-white dark:bg-surface-800 rounded-[2rem] border border-surface-200 dark:border-surface-700 p-8 shadow-2xl lg:sticky lg:top-0">
                        <div class="text-center mb-8 pb-8 border-b border-surface-100 dark:border-surface-700">
                            <p class="text-text-secondary text-sm font-black uppercase tracking-widest mb-3">TOTAL TAGIHAN</p>
                            <p class="text-5xl font-black text-primary-600 tracking-tight">{{ formatCurrency(cartTotal) }}</p>
                        </div>

                        <div class="space-y-8">
                            <div>
                                <p class="text-sm font-black text-text-secondary uppercase tracking-widest mb-4">Pilih Metode Pembayaran</p>
                                <div v-if="availablePaymentMethods.length > 0"
                                    class="grid grid-cols-2 gap-4">
                                    <button v-for="method in availablePaymentMethods" :key="method.id"
                                        @click="selectedPaymentMethod = method.id"
                                        class="p-4 rounded-2xl border-2 transition-all flex flex-col items-center gap-3 relative overflow-hidden text-center hover:-translate-y-1 hover:shadow-lg"
                                        :class="selectedPaymentMethod === method.id
                                            ? 'border-primary-500 bg-primary-500/5 shadow-primary-500/10 text-primary-600'
                                            : 'border-surface-200 dark:border-surface-700 hover:border-primary-500/30 text-text-primary'">
                                        <div
                                            class="w-14 h-14 bg-white dark:bg-surface-800 rounded-xl shadow-sm flex items-center justify-center transition-colors"
                                            :class="selectedPaymentMethod === method.id ? 'bg-primary-500 text-white' : ''">
                                            <CreditCard
                                                v-if="method.category?.toLowerCase() === 'bank' || method.category?.toLowerCase() === 'transfer' || method.category?.toLowerCase() === 'edc'"
                                                :size="28" />
                                            <QrCode
                                                v-else-if="method.category?.toLowerCase() === 'e-wallet' || method.category?.toLowerCase() === 'qris'"
                                                :size="28" />
                                            <Banknote v-else :size="28" />
                                        </div>
                                        <div class="flex flex-col gap-1 w-full">
                                            <span class="font-black text-[11px] uppercase tracking-wider leading-tight">{{ method.name }}</span>
                                            <span v-if="method.account_number"
                                                class="text-[10px] text-text-secondary font-mono bg-surface-100 dark:bg-surface-800 px-2 py-0.5 rounded truncate mx-auto max-w-full">{{
                                                    method.account_number }}</span>
                                        </div>
                                        <div v-if="selectedPaymentMethod === method.id"
                                            class="absolute top-3 right-3 text-primary-500 bg-white dark:bg-surface-800 rounded-full shadow-sm p-0.5">
                                            <CheckCircle :size="16" />
                                        </div>
                                    </button>
                                </div>
                                <div v-else class="text-center py-6 text-text-secondary text-sm font-medium bg-surface-50 dark:bg-surface-900 rounded-2xl">
                                    <Loader2 class="animate-spin mx-auto mb-2" :size="24" />
                                    Memuat metode pembayaran...
                                </div>
                            </div>

                            <div v-if="isCashPayment" class="pt-4 border-t border-surface-100 dark:border-surface-700">
                                <p class="text-sm font-black text-text-secondary uppercase tracking-widest mb-4">Jumlah Uang Diterima</p>
                                <div class="relative mb-4">
                                    <span
                                        class="absolute left-5 top-1/2 -translate-y-1/2 text-text-secondary text-xl font-black">Rp</span>
                                    <input :value="displayPaymentAmount" @input="handlePaymentInput" type="text"
                                        class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-2xl px-6 py-5 bg-surface-50 dark:bg-surface-900 text-text-primary text-3xl font-black focus:outline-none focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 focus:bg-white transition-all pl-14"
                                        placeholder="0" />
                                </div>
                                <div class="grid grid-cols-2 gap-3 mb-3">
                                    <button @click="paymentAmount = cartStore.total"
                                        class="py-4 text-sm font-black bg-primary-500 text-white rounded-xl shadow-lg shadow-primary-500/20 hover:-translate-y-0.5 transition-all w-full">Uang Pas</button>
                                    <button @click="paymentAmount = paymentAmount + 50000"
                                        class="py-4 text-sm font-black bg-white dark:bg-surface-800 text-text-primary border-2 border-surface-200 dark:border-surface-700 hover:border-primary-500 hover:text-primary-600 rounded-xl transition-all shadow-sm w-full">+ Rp 50k</button>
                                </div>
                                <div class="grid grid-cols-3 gap-3">
                                    <button @click="paymentAmount = 10000"
                                        class="py-3 text-sm font-bold bg-surface-50 dark:bg-surface-900 hover:bg-surface-100 dark:hover:bg-surface-800 text-text-primary border border-surface-200 dark:border-surface-700 rounded-xl transition-colors">Rp 10k</button>
                                    <button @click="paymentAmount = 50000"
                                        class="py-3 text-sm font-bold bg-surface-50 dark:bg-surface-900 hover:bg-surface-100 dark:hover:bg-surface-800 text-text-primary border border-surface-200 dark:border-surface-700 rounded-xl transition-colors">Rp 50k</button>
                                    <button @click="paymentAmount = 100000"
                                        class="py-3 text-sm font-bold bg-surface-50 dark:bg-surface-900 hover:bg-surface-100 dark:hover:bg-surface-800 text-text-primary border border-surface-200 dark:border-surface-700 rounded-xl transition-colors">Rp 100k</button>
                                </div>
                            </div>

                            <!-- Discount Section -->
                            <div class="pt-6 border-t border-surface-100 dark:border-surface-700">
                                <div class="flex items-center justify-between mb-4">
                                    <p class="text-sm font-black text-text-secondary uppercase tracking-widest">Diskon Tambahan</p>
                                    <div class="flex gap-1.5 bg-surface-100 dark:bg-surface-800 p-1 rounded-xl">
                                        <button @click="cartStore.discountType = 'percentage'"
                                            class="px-4 py-2 text-xs rounded-lg font-black transition-all"
                                            :class="cartStore.discountType === 'percentage' ? 'bg-primary-500 text-white shadow-md' : 'text-text-secondary hover:text-text-primary'">%</button>
                                        <button @click="cartStore.discountType = 'fixed'"
                                            class="px-4 py-2 text-xs rounded-lg font-black transition-all"
                                            :class="cartStore.discountType === 'fixed' ? 'bg-primary-500 text-white shadow-md' : 'text-text-secondary hover:text-text-primary'">Rp</button>
                                    </div>
                                </div>
                                <div class="relative">
                                    <span v-if="cartStore.discountType === 'fixed'"
                                        class="absolute left-5 top-1/2 -translate-y-1/2 text-text-secondary text-lg font-bold">Rp</span>
                                    <input :value="displayDiscount" @input="handleDiscountInput" type="text"
                                        class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-2xl px-5 py-4 bg-surface-50 dark:bg-surface-900 text-text-primary text-xl font-black focus:outline-none focus:border-primary-500 focus:bg-white transition-all"
                                        :class="cartStore.discountType === 'fixed' ? 'pl-14' : ''" placeholder="0" />
                                    <span v-if="cartStore.discountType === 'percentage'"
                                        class="absolute right-5 top-1/2 -translate-y-1/2 text-text-secondary text-lg font-bold">%</span>
                                </div>
                            </div>

                            <div v-if="cartStore.discountAmount > 0"
                                class="p-5 bg-primary-500/10 border border-primary-500/20 rounded-2xl flex justify-between items-center">
                                <span class="text-sm font-black text-primary-700">Potongan Diskon</span>
                                <span class="text-xl font-black text-primary-600">- {{ formatCurrency(cartStore.discountAmount) }}</span>
                            </div>

                            <div v-if="isCashPayment" class="pt-6 border-t border-surface-100 dark:border-surface-700">
                                <div v-if="changeAmount >= 0"
                                    class="p-6 bg-emerald-500/10 border-2 border-emerald-500/20 rounded-2xl flex justify-between items-center">
                                    <span class="text-sm font-black text-emerald-700 uppercase tracking-widest">Kembalian</span>
                                    <span class="text-3xl font-black text-emerald-600">{{ formatCurrency(changeAmount) }}</span>
                                </div>
                                <div v-else
                                    class="p-6 bg-red-500/10 border-2 border-red-500/20 rounded-2xl flex justify-between items-center">
                                    <span class="text-sm font-black text-red-700 uppercase tracking-widest">Kurang</span>
                                    <span class="text-3xl font-black text-red-600">{{ formatCurrency(Math.abs(changeAmount)) }}</span>
                                </div>
                            </div>

                            <div class="flex gap-4 pt-8 border-t border-surface-100 dark:border-surface-700">
                                <button @click="prevStep"
                                    class="w-20 h-20 flex-none bg-surface-100 dark:bg-surface-800 hover:bg-surface-200 dark:hover:bg-surface-700 text-text-primary rounded-[1.25rem] font-bold transition-all flex items-center justify-center">
                                    <ArrowLeft :size="28" />
                                </button>
                                <button @click="handleSubmitOrder"
                                    :disabled="(isCashPayment && changeAmount < 0) || isSubmitting"
                                    class="flex-1 h-20 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white rounded-[1.25rem] font-black text-xl shadow-2xl shadow-emerald-500/30 transition-all flex items-center justify-center gap-3">
                                    <Loader2 v-if="isSubmitting" class="animate-spin mr-2" :size="28" />
                                    <CheckCircle v-else :size="28" />
                                    {{ isSubmitting ? 'MEMPROSES...' : 'SELESAIKAN TRANSAKSI' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SUCCESS MODAL -->
        <Teleport to="body">
            <div v-if="showSuccessModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/80 backdrop-blur-sm"></div>
                <div
                    class="relative bg-white dark:bg-surface-800 rounded-[2.5rem] border border-surface-200 dark:border-surface-700 w-full max-w-md p-10 text-center shadow-2xl">
                    <div
                        class="w-28 h-28 mx-auto mb-8 bg-emerald-500/10 rounded-full flex items-center justify-center animate-bounce">
                        <CheckCircle class="text-emerald-500" :size="64" stroke-width="1.5" />
                    </div>
                    <h3 class="text-4xl font-black text-text-primary mb-3">Suksess!</h3>
                    <p class="text-text-secondary text-lg mb-10">Transaksi telah berhasil diproses & tersimpan</p>

                    <div v-if="lastTransaction"
                        class="bg-surface-50 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 rounded-[1.5rem] p-6 mb-10 text-left space-y-4">
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-text-secondary font-bold uppercase tracking-widest">Receipt ID</span>
                            <span class="font-mono font-black text-text-primary bg-white dark:bg-surface-800 px-3 py-1 rounded-lg border border-surface-200 dark:border-surface-700">{{ lastTransaction.id }}</span>
                        </div>
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-text-secondary font-bold uppercase tracking-widest">Sales</span>
                            <span class="font-black text-text-primary">{{ lastTransaction.sales_account }}</span>
                        </div>
                        <div class="h-px bg-surface-200 dark:bg-surface-700 my-4"></div>
                        <div class="flex justify-between items-end">
                            <span class="text-text-secondary font-bold uppercase tracking-widest mb-1">Total</span>
                            <span class="text-3xl font-black text-emerald-500">{{ formatCurrency(lastTransaction.total) }}</span>
                        </div>
                    </div>

                    <button @click="closeSuccessModal"
                        class="w-full py-5 bg-primary-600 hover:bg-primary-500 text-white rounded-[1.25rem] font-bold text-lg transition-all shadow-xl shadow-primary-500/30 mb-4">
                        Mulai Transaksi Baru
                    </button>
                    <button class="w-full py-4 text-text-secondary hover:text-text-primary hover:bg-surface-50 dark:hover:bg-surface-900 font-bold text-sm uppercase tracking-widest rounded-[1.25rem] flex items-center justify-center gap-2 transition-colors">
                        <Receipt :size="20" /> Cetak Struk Bukti
                    </button>
                </div>
            </div>
        </Teleport>
    </div>
</template>`;
    content = content.substring(0, startIndex) + newTemplate + content.substring(endIndex);
    fs.writeFileSync(file, content, 'utf8');
    console.log('Template upgraded successfully');
} else {
    console.log('Could not find template tags');
}
