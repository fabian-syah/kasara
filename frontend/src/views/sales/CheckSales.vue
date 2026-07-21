<template>
    <!-- MAINTENANCE VIEW -->
    <div v-if="isMaintenance" class="flex flex-col items-center justify-center min-h-[70vh] text-center py-20 px-4">
        <div
            class="w-20 h-20 bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-500 rounded-3xl border-2 border-amber-100 dark:border-amber-500/20 flex items-center justify-center mb-8 relative shadow-xl shadow-amber-500/5 animate-pulse">
            <Wrench :size="36" stroke-width="2" />
        </div>
        <h2 class="text-3xl font-black text-text-primary tracking-tight mb-4">Sedang Maintenance / Debugging</h2>
        <p class="text-text-secondary max-w-md text-base font-medium leading-relaxed mx-auto">
            Kami sedang melakukan pemeliharaan dan pembaharuan sistem pada halaman Cek Penjualan. Harap tunggu beberapa
            saat, halaman ini akan segera kembali beroperasi normal.
        </p>
    </div>

    <div v-else class="space-y-6">
        <!-- Header & Filters -->
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
            <div>
                <h1 class="text-2xl font-bold text-text-primary">Cek Penjualan</h1>
                <p class="text-text-secondary text-sm mt-1">Lihat riwayat penjualan dari cabang Anda</p>
            </div>

            <div class="flex flex-wrap items-center gap-3 w-full lg:w-auto">
                <!-- Location Filter -->
                <div v-if="canFilterBranch" class="relative min-w-[200px]">
                    <select v-model="selectedLocationKey" @change="handleLocationChange"
                        class="w-full appearance-none bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl px-4 py-2.5 pr-10 text-sm font-medium focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all cursor-pointer text-text-primary">
                        <option v-if="!isRestrictedLocation" value="all" class="dark:bg-surface-800 dark:text-white">Semua Cabang/Toko</option>
                        <option v-for="loc in filteredLocations" :key="`${loc.type}:${loc.id}`"
                            :value="`${loc.type === 'branch' ? 'B' : 'S'}:${loc.id}`"
                            class="dark:bg-surface-800 dark:text-white">
                            {{ loc.type === 'branch' ? '[Cabang]' : '[Online]' }} {{ loc.name }}
                        </option>
                    </select>
                    <ChevronDown :size="16"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
                </div>

                <!-- Period Filter -->
                <div class="relative min-w-[140px]">
                    <select v-model="selectedPeriod" @change="handlePeriodChange"
                        class="w-full appearance-none bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl px-4 py-2.5 pr-10 text-sm font-medium focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all cursor-pointer text-text-primary">
                        <option value="daily" class="dark:bg-surface-800 dark:text-white">Harian</option>
                        <option value="monthly" class="dark:bg-surface-800 dark:text-white">Bulanan</option>
                    </select>
                    <ChevronDown :size="16"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
                </div>

                <!-- Daily: Date Picker -->
                <div v-if="selectedPeriod === 'daily'" class="relative group">
                    <div
                        class="flex items-center gap-2 px-4 py-2.5 bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl hover:border-primary-500 hover:ring-2 hover:ring-primary-500/10 transition-all cursor-pointer">
                        <Calendar :size="18" class="text-gray-500 dark:text-gray-400 group-hover:text-primary-500" />
                        <span class="text-sm font-medium text-text-primary min-w-[100px]">
                            {{ formattedDateDisplay }}
                        </span>
                    </div>
                    <input type="date" v-model="filters.start_date" @change="handleDateChange"
                        @click="$event.target.showPicker()" :min="getMinDate" :max="getTodayLocal()"
                        class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-20" />
                </div>

                <!-- Monthly: Month & Year Selectors -->
                <div v-if="selectedPeriod === 'monthly'" class="flex items-center gap-2">
                    <div class="relative min-w-[140px]">
                        <select v-model="selectedMonth" @change="handleMonthChange"
                            class="w-full appearance-none bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl px-4 py-2.5 pr-10 text-sm font-medium focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all cursor-pointer text-text-primary">
                            <option v-for="m in restrictedMonths" :key="m.value" :value="m.value"
                                class="dark:bg-surface-800 dark:text-white">{{ m.name }}</option>
                        </select>
                        <ChevronDown :size="16"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
                    </div>

                    <div class="relative min-w-[100px]">
                        <select v-model="selectedYear" @change="handleMonthChange"
                            class="w-full appearance-none bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-xl px-4 py-2.5 pr-10 text-sm font-medium focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all cursor-pointer text-text-primary">
                            <option v-for="y in years" :key="y" :value="y" class="dark:bg-surface-800 dark:text-white">
                                {{ y }}</option>
                        </select>
                        <ChevronDown :size="16"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
                    </div>
                </div>
                <!-- Export Excel -->
                <button @click="handleExport" :disabled="exportLoading || loading"
                    class="flex items-center justify-center gap-2 px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl transition-all disabled:opacity-50 font-bold text-sm shadow-lg shadow-emerald-500/20">
                    <Loader2 v-if="exportLoading" :size="18" class="animate-spin" />
                    <Download v-else :size="18" />
                    <span>Export Excel</span>
                </button>
            </div>
        </div>

        <!-- NEW Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Total Omset -->
            <div
                class="bg-white dark:!bg-surface-800 rounded-2xl p-5 border border-gray-100 dark:border-surface-700 shadow-sm relative overflow-hidden group">
                <div
                    class="absolute top-0 right-0 w-24 h-24 bg-primary-500/5 rounded-full -mr-12 -mt-12 group-hover:scale-110 transition-transform duration-500">
                </div>
                <div class="relative">
                    <p
                        class="text-text-secondary text-[10px] font-black uppercase tracking-widest flex items-center gap-2">
                        <TrendingUp :size="14" class="text-primary-500" />
                        Total Omset
                    </p>
                    <p class="text-2xl font-black text-text-primary mt-2">{{ formatCurrency(summaryStats.totalOmset) }}
                    </p>
                    <p class="text-[10px] text-text-secondary mt-1 font-medium italic opacity-70">Penjualan + TT Out + Out DG</p>
                </div>
            </div>

            <!-- Omset Bersih -->
            <div
                class="bg-white dark:!bg-surface-800 rounded-2xl p-5 border border-gray-100 dark:border-surface-700 shadow-sm relative overflow-hidden group">
                <div
                    class="absolute top-0 right-0 w-24 h-24 bg-emerald-500/5 rounded-full -mr-12 -mt-12 group-hover:scale-110 transition-transform duration-500">
                </div>
                <div class="relative">
                    <p
                        class="text-text-secondary text-[10px] font-black uppercase tracking-widest flex items-center gap-2">
                        <Wallet :size="14" class="text-emerald-500" />
                        Omset Bersih
                    </p>
                    <p class="text-2xl font-black mt-2 transition-colors duration-300"
                        :class="summaryStats.omsetBersih < 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600'">
                        {{ formatCurrency(summaryStats.omsetBersih) }}
                    </p>
                    <p class="text-[10px] text-text-secondary mt-1 font-medium italic opacity-70">Sales - (Angkat +
                        Refund + In TT + In DG)</p>
                </div>
            </div>

            <!-- Total Unit HP -->
            <div
                class="bg-white dark:!bg-surface-800 rounded-2xl p-5 border border-gray-100 dark:border-surface-700 shadow-sm relative overflow-hidden group">
                <div
                    class="absolute top-0 right-0 w-24 h-24 bg-blue-500/5 rounded-full -mr-12 -mt-12 group-hover:scale-110 transition-transform duration-500">
                </div>
                <div class="relative">
                    <p
                        class="text-text-secondary text-[10px] font-black uppercase tracking-widest flex items-center gap-2">
                        <Smartphone :size="14" class="text-blue-500" />
                        Unit HP
                    </p>
                    <div class="flex items-center gap-3 mt-2">
                        <div class="flex items-baseline gap-1">
                            <p class="text-2xl font-black text-blue-600">{{ summaryStats.hpUnitsOut }}</p>
                            <p class="text-[10px] font-bold text-text-secondary uppercase">Out</p>
                        </div>
                        <div class="w-px h-6 bg-gray-200 dark:bg-surface-700"></div>
                        <div class="flex items-baseline gap-1">
                            <p class="text-2xl font-black text-amber-600">{{ summaryStats.hpUnitsIn }}</p>
                            <p class="text-[10px] font-bold text-text-secondary uppercase">In</p>
                        </div>
                    </div>
                    <p class="text-[10px] text-text-secondary mt-1 font-medium italic opacity-70">Total Unit HP Masuk &
                        Keluar</p>
                </div>
            </div>

            <!-- Total Unit Non-HP -->
            <div
                class="bg-white dark:!bg-surface-800 rounded-2xl p-5 border border-gray-100 dark:border-surface-700 shadow-sm relative overflow-hidden group">
                <div
                    class="absolute top-0 right-0 w-24 h-24 bg-purple-500/5 rounded-full -mr-12 -mt-12 group-hover:scale-110 transition-transform duration-500">
                </div>
                <div class="relative">
                    <p
                        class="text-text-secondary text-[10px] font-black uppercase tracking-widest flex items-center gap-2">
                        <Box :size="14" class="text-purple-500" />
                        Unit Non-HP
                    </p>
                    <div class="flex items-baseline gap-2 mt-2">
                        <p class="text-2xl font-black text-purple-600">{{ summaryStats.nonHpUnits }}</p>
                        <p class="text-xs font-bold text-text-secondary">Pcs</p>
                    </div>
                    <p class="text-[10px] text-text-secondary mt-1 font-medium italic opacity-70">Aksesoris & Lainnya
                    </p>
                </div>
            </div>
        </div>

        <!-- Sales Table -->
        <div
            class="bg-white dark:!bg-surface-800 rounded-2xl shadow-sm border border-gray-100 dark:border-surface-700 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead
                        class="text-xs font-semibold text-text-secondary uppercase bg-gray-50/50 dark:!bg-surface-700/50 border-b border-gray-100 dark:border-surface-700">
                        <tr>
                            <th class="px-6 py-4">No</th>
                            <th class="px-6 py-4">Tanggal</th>
                            <th class="px-6 py-4">No Pesanan</th>
                            <th class="px-6 py-4">Nama</th>
                            <th class="px-6 py-4">No HP</th>
                            <th class="px-6 py-4">Kategori</th>
                            <th class="px-6 py-4">Produk</th>
                            <th class="px-6 py-4">IMEI</th>
                            <th class="px-6 py-4">Qty</th>
                            <th class="px-6 py-4">Harga</th>
                            <th class="px-6 py-4">Total</th>
                            <th class="px-6 py-4">Diskon & Akhir</th>
                            <th class="px-6 py-4">Akun / Catatan</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4">Distributor</th>
                            <th class="px-6 py-4">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-surface-700">
                        <tr v-if="loading">
                            <td colspan="15" class="px-6 py-12">
                                <div class="flex flex-col items-center justify-center text-text-secondary">
                                    <Loader2 class="w-8 h-8 animate-spin text-primary-500 mb-2" />
                                    <span class="text-sm font-medium">Memuat data penjualan...</span>
                                </div>
                            </td>
                        </tr>
                        <tr v-else-if="(salesRecords.daily_sales?.data || salesRecords.daily_sales).length === 0">
                            <td colspan="15" class="px-6 py-12 text-center text-text-secondary">
                                <div class="flex flex-col items-center justify-center">
                                    <div
                                        class="w-12 h-12 bg-gray-100 dark:!bg-surface-700 rounded-full flex items-center justify-center mb-3">
                                        <FileText class="w-6 h-6 text-gray-400" />
                                    </div>
                                    <span class="font-medium text-text-primary">Tidak ada data penjualan</span>
                                    <span class="text-xs mt-1">Belum ada transaksi pada periode ini</span>
                                </div>
                            </td>
                        </tr>
                        <template v-else>
                            <template
                                v-for="(item, index) in (salesRecords.daily_sales?.data || salesRecords.daily_sales)"
                                :key="index">
                                <!-- If item has sub-items -->
                                <tr v-if="item.items && item.items.length > 0" v-for="(detail, idx) in item.items"
                                    :key="`${index}-${idx}`"
                                    class="hover:bg-gray-50 dark:hover:bg-surface-700/30 transition-colors text-text-primary">
                                    <td class="px-6 py-4 text-text-secondary" v-if="idx === 0"
                                        :rowspan="item.items.length">{{ ((salesRecords.daily_sales?.current_page || 1) - 1) * (salesRecords.daily_sales?.per_page || 50) + index + 1 }}</td>
                                    <td class="px-6 py-4 font-medium" v-if="idx === 0" :rowspan="item.items.length">{{
                                        formatDate(item.date) }}</td>
                                    <td class="px-6 py-4 font-mono text-xs" v-if="idx === 0"
                                        :rowspan="item.items.length">
                                        <div>{{ item.order_no }}</div>
                                        <button @click="openScreenshot(item)" class="mt-1.5 px-2 py-0.5 text-[10px] font-semibold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-500/10 hover:bg-emerald-100 dark:hover:bg-emerald-500/20 border border-emerald-200 dark:border-emerald-500/20 rounded-md transition-colors">Screenshot Nota</button>
                                    </td>
                                    <td class="px-6 py-4 font-medium" v-if="idx === 0" :rowspan="item.items.length">{{
                                        item.customer_name }}</td>
                                    <td class="px-6 py-4" v-if="idx === 0" :rowspan="item.items.length">
                                        <div class="flex flex-col gap-1 items-start">
                                            <span class="font-medium text-text-primary">{{ item.customer_wa || item.customer_phone || '-' }}</span>
                                            <button 
                                                v-if="item.customer_wa || item.customer_phone" 
                                                @click="sendWaReceipt(item)" 
                                                class="mt-1 inline-flex items-center gap-1 text-[10px] font-bold text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300 transition-colors cursor-pointer"
                                            >
                                                <MessageSquare :size="10" stroke-width="2.5" />
                                                <span>Kirim Nota</span>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4" v-if="idx === 0" :rowspan="item.items.length">
                                        <span
                                            class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400 border border-blue-100 dark:border-blue-500/20">
                                            {{ categoryLabels[item.category] || item.category }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <div>{{ detail.name }}</div>
                                        <div v-if="detail.ram || detail.storage" class="text-[10px] text-gray-500">
                                            {{ [...new Set([detail.ram, detail.storage].filter(Boolean))].join('/') }}
                                        </div>
                                        <span v-if="detail.condition"
                                            class="inline-block mt-0.5 px-1.5 py-0.5 text-[10px] font-semibold rounded"
                                            :class="detail.condition === 'new' ? 'bg-emerald-500/10 text-emerald-500' : detail.condition === 'ex_ibox' ? 'bg-purple-500/10 text-purple-500' : 'bg-amber-500/10 text-amber-500'">
                                            {{ detail.condition === 'new' ? 'Baru' : detail.condition === 'ex_ibox' ?
                                                'Ex iBox' : 'Second' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 font-mono text-xs text-blue-500">{{ detail.imei && detail.imei
                                        !== '-' ? detail.imei : '-' }}</td>
                                    <td class="px-6 py-4 font-bold">{{ detail.qty }}</td>
                                    <td class="px-6 py-4 font-bold text-emerald-600 whitespace-nowrap">
                                        <div class="flex flex-col">
                                            <span>{{ formatCurrency(detail.original_price || detail.price) }}</span>
                                            <span v-if="detail.item_discount > 0"
                                                class="text-[10px] text-red-500 font-bold bg-red-50 dark:bg-red-500/10 px-1.5 py-0.5 rounded w-fit mt-0.5 border border-red-100 dark:border-red-500/20">
                                                Disc: -{{ formatCurrency(detail.item_discount) }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 font-black text-text-primary whitespace-nowrap">{{
                                        formatCurrency((detail.original_price ? (detail.original_price - detail.item_discount) : detail.price) * detail.qty) }}</td>
                                    <td class="px-6 py-4" v-if="idx === 0" :rowspan="item.items.length">
                                        <div class="flex flex-col gap-1 items-start">
                                            <span v-if="item.global_discount_value > 0"
                                                class="px-2 py-0.5 bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-400 text-[10px] font-black rounded-md border border-red-100 dark:border-red-500/20 whitespace-nowrap flex items-center gap-1">
                                                Disc: -{{ formatCurrency(item.global_discount_value) }}
                                            </span>
                                            <span
                                                class="font-black text-emerald-600 dark:text-emerald-400 text-[13px] whitespace-nowrap">
                                                {{ formatCurrency(item.grand_total) }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4" v-if="idx === 0" :rowspan="item.items.length">
                                        <div class="flex flex-col gap-1.5 items-start">
                                            <!-- Account Priority: inventory_user_name -> sales_account -> 9090 Mask -> PIN Mask -->
                                            <div v-if="item.inventory_user_name || item.sales_account"
                                                class="px-2.5 py-1 bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-400 rounded-lg text-[10px] font-black uppercase tracking-wider flex items-center gap-1.5 border border-primary-100 dark:border-primary-500/20">
                                                <User :size="12" stroke-width="2.5" />
                                                {{ item.inventory_user_name || item.sales_account }}
                                            </div>
                                            <div v-else-if="String(item.transaction_pin) === '9090'"
                                                class="px-2.5 py-1 bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-400 rounded-lg text-[10px] font-black uppercase tracking-wider flex items-center gap-1.5 border border-primary-100 dark:border-primary-500/20">
                                                <User :size="12" stroke-width="2.5" /> Akun Inventory
                                            </div>
                                            <div v-else-if="item.transaction_pin"
                                                class="px-2.5 py-1 bg-surface-100 dark:bg-surface-800 text-text-secondary rounded-lg text-[10px] font-bold italic uppercase tracking-wider border border-surface-200 dark:border-surface-700">
                                                PIN disembunyikan
                                            </div>

                                            <div v-if="item.notes"
                                                class="text-xs text-text-primary leading-tight px-0.5"
                                                :title="item.notes">
                                                {{ item.notes }}
                                            </div>
                                            <div v-else-if="!item.inventory_user_name && !item.sales_account && String(item.transaction_pin) !== '9090'"
                                                class="text-text-secondary italic text-xs px-0.5">
                                                Tanpa Catatan
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4" v-if="idx === 0" :rowspan="item.items.length">
                                        <span class="px-2.5 py-1 text-xs font-semibold rounded-lg"
                                            :class="item.category === 'cancel_penjualan'
                                                ? 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400 border border-red-100 dark:border-red-500/20'
                                                : item.status === 'Lunas'
                                                    ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-500/20'
                                                    : 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400 border border-amber-100 dark:border-amber-500/20'">
                                            {{ item.category === 'cancel_penjualan' ? 'Dibatalkan' : item.status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="text-xs font-medium text-text-secondary italic">
                                            {{ detail.distributor_name || 'KOSONG' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4" v-if="idx === 0" :rowspan="item.items.length">
                                        <div class="flex items-center gap-2">
                                            <div v-if="item.proof_images && item.proof_images.length > 0"
                                                class="flex flex-wrap gap-1.5 max-w-[200px]">
                                                <button v-for="(img, imgIdx) in item.proof_images" :key="imgIdx"
                                                    @click="viewProof(img)"
                                                    class="flex items-center gap-1.5 px-2.5 py-1.5 text-[9px] font-black uppercase tracking-tighter text-primary-600 bg-primary-50 dark:bg-primary-500/10 hover:bg-primary-100 dark:hover:bg-primary-500/20 rounded-lg transition-all border border-primary-100 dark:border-primary-500/20 whitespace-nowrap"
                                                    :title="'Lihat ' + (item.proof_images.length === 2 ? (imgIdx === 0 ? 'Foto Unit' : 'Foto Customer') : 'Foto #' + (imgIdx + 1))">
                                                    <Image :size="12" stroke-width="3" />
                                                    <span>{{ item.proof_images.length === 2 ? (imgIdx === 0 ? 'Unit' :
                                                        'Cust') : (item.proof_images.length === 1 ? 'Bukti' : '#' +
                                                            (imgIdx + 1)) }}</span>
                                                </button>
                                            </div>
                                            <!-- Fallback if proof_images is empty but single proof_image exists (Foto Unit/Nota) -->
                                            <button v-else-if="item.proof_image" @click="viewProof(item.proof_image)"
                                                class="flex items-center gap-1.5 px-3 py-1.5 text-[10px] font-black uppercase tracking-wider text-primary-600 bg-primary-50 dark:bg-primary-500/10 hover:bg-primary-100 dark:hover:bg-primary-500/20 rounded-lg transition-all border border-primary-100 dark:border-primary-500/20"
                                                title="Lihat Foto Bukti">
                                                <Image :size="14" stroke-width="2.5" />
                                                <span>Lihat Bukti</span>
                                            </button>

                                            <!-- NEW: Payment Proof Button -->
                                            <button v-if="item.payment_proof_image" @click="viewProof(item.payment_proof_image)"
                                                class="flex items-center gap-1.5 px-2.5 py-1.5 text-[9px] font-black uppercase tracking-tighter text-amber-600 bg-amber-50 dark:bg-amber-500/10 hover:bg-amber-100 dark:hover:bg-amber-500/20 rounded-lg transition-all border border-amber-100 dark:border-amber-500/20 whitespace-nowrap"
                                                title="Lihat Foto Bukti Pembayaran/Transfer">
                                                <Wallet :size="12" stroke-width="3" />
                                                <span>Bayar</span>
                                            </button>
                                            <button @click="openReceipt(item)"
                                                class="p-2 text-emerald-500 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 rounded-lg transition-colors"
                                                title="Buat Struk">
                                                <Printer :size="18" />
                                            </button>
                                            <button
                                                v-if="item.category !== 'cancel_penjualan' && canCancel(item.created_at || item.date)"
                                                @click="handleCancelSale(item)"
                                                class="p-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 rounded-lg transition-colors"
                                                title="Batalkan Penjualan">
                                                <Trash2 :size="18" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <!-- Single item row -->
                                <tr v-else
                                    class="hover:bg-gray-50 dark:hover:bg-surface-700/30 transition-colors text-text-primary">
                                    <td class="px-6 py-4 text-text-primary">{{ ((salesRecords.daily_sales?.current_page || 1) - 1) * (salesRecords.daily_sales?.per_page || 50) + index + 1 }}</td>
                                    <td class="px-6 py-4 font-medium">{{ formatDate(item.date) }}</td>
                                    <td class="px-6 py-4 font-mono text-xs text-text-primary">
                                        <div>{{ item.order_no }}</div>
                                        <button @click="openScreenshot(item)" class="mt-1.5 px-2 py-0.5 text-[10px] font-semibold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-500/10 hover:bg-emerald-100 dark:hover:bg-emerald-500/20 border border-emerald-200 dark:border-emerald-500/20 rounded-md transition-colors">Screenshot Nota</button>
                                    </td>
                                    <td class="px-6 py-4 font-medium">{{ item.customer_name }}</td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col gap-1 items-start">
                                            <span class="font-medium text-text-primary">{{ item.customer_wa || item.customer_phone || '-' }}</span>
                                            <button 
                                                v-if="item.customer_wa || item.customer_phone" 
                                                @click="sendWaReceipt(item)" 
                                                class="mt-1 inline-flex items-center gap-1 text-[10px] font-bold text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300 transition-colors cursor-pointer"
                                            >
                                                <MessageSquare :size="10" stroke-width="2.5" />
                                                <span>Kirim Nota</span>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span
                                            class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400 border border-blue-100 dark:border-blue-500/20">
                                            {{ categoryLabels[item.category] || item.category }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-text-primary">{{ item.product_names || '-' }}</td>
                                    <td class="px-6 py-4 font-mono text-xs text-blue-500">{{ item.imeis && item.imeis
                                        !== '-' ? item.imeis : '-' }}</td>
                                    <td class="px-6 py-4 font-bold text-text-primary">{{ item.qty }}</td>
                                    <td class="px-6 py-4 font-bold text-emerald-600 whitespace-nowrap">
                                        <div class="flex flex-col">
                                            <span>{{ formatCurrency((item.original_price || item.grand_total) / (item.qty || 1)) }}</span>
                                            <span v-if="item.total_discount > 0 && (item.total_discount - (item.global_discount_value || 0)) > 0"
                                                class="text-[10px] text-red-500 font-bold bg-red-50 dark:bg-red-500/10 px-1.5 py-0.5 rounded w-fit mt-0.5 border border-red-100 dark:border-red-500/20">
                                                Disc: -{{ formatCurrency((item.total_discount - (item.global_discount_value || 0)) / (item.qty || 1)) }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 font-black text-text-primary whitespace-nowrap">{{
                                        formatCurrency(item.grand_total + (item.global_discount_value || 0)) }}</td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col gap-1 items-start">
                                            <span v-if="item.global_discount_value > 0"
                                                class="px-2 py-0.5 bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-400 text-[10px] font-black rounded-md border border-red-100 dark:border-red-500/20 whitespace-nowrap flex items-center gap-1">
                                                Disc: -{{ formatCurrency(item.global_discount_value) }}
                                            </span>
                                            <span
                                                class="font-black text-emerald-600 dark:text-emerald-400 text-[13px] whitespace-nowrap">
                                                {{ formatCurrency(item.grand_total) }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col gap-1">
                                            <span v-if="String(item.transaction_pin) === '9090'"
                                                class="text-xs font-bold text-primary-500">
                                                Akun Inventory
                                            </span>
                                            <span v-if="item.notes" class="text-xs text-text-primary leading-tight"
                                                :title="item.notes">{{ item.notes }}</span>
                                            <span v-else-if="String(item.transaction_pin) !== '9090'"
                                                class="text-text-secondary italic text-xs">
                                                Tanpa Catatan
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2.5 py-1 text-xs font-semibold rounded-lg"
                                            :class="item.category === 'cancel_penjualan'
                                                ? 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400 border border-red-100 dark:border-red-500/20'
                                                : item.status === 'Lunas'
                                                    ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-500/20'
                                                    : 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400 border border-amber-100 dark:border-amber-500/20'">
                                            {{ item.category === 'cancel_penjualan' ? 'Dibatalkan' : item.status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="text-xs font-medium text-text-secondary italic">
                                            {{ item.distributor_name || item.items?.[0]?.distributor_name || 'KOSONG' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            <div v-if="item.proof_images && item.proof_images.length > 0"
                                                class="flex flex-wrap gap-1.5 max-w-[200px]">
                                                <button v-for="(img, imgIdx) in item.proof_images" :key="imgIdx"
                                                    @click="viewProof(img)"
                                                    class="shrink-0 flex items-center gap-1.5 px-2.5 py-1.5 text-[9px] font-black uppercase tracking-tighter text-primary-600 bg-primary-50 dark:bg-primary-500/10 hover:bg-primary-100 dark:hover:bg-primary-500/20 rounded-lg transition-all border border-primary-100 dark:border-primary-500/20 whitespace-nowrap"
                                                    :title="'Lihat ' + (item.proof_images.length === 2 ? (imgIdx === 0 ? 'Foto Unit' : 'Foto Customer') : 'Foto #' + (imgIdx + 1))">
                                                    <Image :size="12" stroke-width="3" />
                                                    <span>{{ item.proof_images.length === 2 ? (imgIdx === 0 ? 'Unit' :
                                                        'Cust') : (item.proof_images.length === 1 ? 'Bukti' : '#' +
                                                            (imgIdx + 1)) }}</span>
                                                </button>
                                            </div>
                                            <!-- Fallback if proof_images is empty but single proof_image exists (Foto Unit/Nota) -->
                                            <button v-else-if="item.proof_image" @click="viewProof(item.proof_image)"
                                                class="shrink-0 flex items-center gap-1.5 px-3 py-1.5 text-[10px] font-black uppercase tracking-wider text-primary-600 bg-primary-50 dark:bg-primary-500/10 hover:bg-primary-100 dark:hover:bg-primary-500/20 rounded-lg transition-all border border-primary-100 dark:border-primary-500/20"
                                                title="Lihat Foto Bukti">
                                                <Image :size="14" stroke-width="2.5" />
                                                <span>Lihat Bukti</span>
                                            </button>

                                            <!-- NEW: Payment Proof Button -->
                                            <button v-if="item.payment_proof_image" @click="viewProof(item.payment_proof_image)"
                                                class="shrink-0 flex items-center gap-1.5 px-2.5 py-1.5 text-[9px] font-black uppercase tracking-tighter text-amber-600 bg-amber-50 dark:bg-amber-500/10 hover:bg-amber-100 dark:hover:bg-amber-500/20 rounded-lg transition-all border border-amber-100 dark:border-amber-500/20 whitespace-nowrap"
                                                title="Lihat Foto Bukti Pembayaran/Transfer">
                                                <Wallet :size="12" stroke-width="3" />
                                                <span>Bayar</span>
                                            </button>
                                            <button @click.prevent.stop="openReceipt(item)"
                                                class="shrink-0 p-2 text-emerald-500 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 rounded-lg transition-colors"
                                                title="Buat Struk">
                                                <Printer :size="18" />
                                            </button>
                                            <button
                                                v-if="item.category !== 'cancel_penjualan' && canCancel(item.created_at || item.date)"
                                                @click.prevent.stop="handleCancelSale(item)"
                                                class="shrink-0 p-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 rounded-lg transition-colors"
                                                title="Batalkan Penjualan">
                                                <Trash2 :size="18" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination Controls -->
        <div v-if="salesRecords.daily_sales?.last_page > 1" class="flex flex-col sm:flex-row items-center justify-between gap-4 bg-white dark:!bg-surface-800 p-4 rounded-xl border border-gray-100 dark:border-surface-700">
            <div class="text-sm text-text-secondary text-center sm:text-left w-full sm:w-auto">
                Menampilkan <span class="font-bold text-text-primary">{{ salesRecords.daily_sales.current_page }}</span> dari <span class="font-bold text-text-primary">{{ salesRecords.daily_sales.last_page }}</span> <span class="hidden sm:inline">(Total {{ salesRecords.daily_sales.total }} data)</span>
            </div>
            
            <div class="flex items-center gap-2 w-full sm:w-auto justify-between sm:justify-end">
                <button 
                    @click="fetchData(salesRecords.daily_sales.current_page - 1)" 
                    :disabled="salesRecords.daily_sales.current_page === 1"
                    class="flex-1 sm:flex-none px-4 py-2 rounded-lg text-sm font-semibold transition-all bg-gray-50 dark:bg-surface-700 text-text-primary hover:bg-gray-100 dark:hover:bg-surface-600 disabled:opacity-50 disabled:cursor-not-allowed border border-gray-200 dark:border-surface-600 text-center">
                    <span class="hidden sm:inline">Sebelumnya</span>
                    <span class="sm:hidden">&larr; Prev</span>
                </button>

                <!-- Desktop Page Numbers -->
                <div class="hidden md:flex items-center gap-1 mx-2">
                    <button v-for="page in Math.min(salesRecords.daily_sales.last_page, 5)" 
                        :key="page"
                        @click="fetchData(page)"
                        :class="[
                            'min-w-[32px] h-8 px-2 flex items-center justify-center rounded-lg text-sm font-semibold transition-all',
                            salesRecords.daily_sales.current_page === page
                                ? 'bg-primary-500 text-white shadow-sm'
                                : 'text-text-secondary hover:bg-gray-100 dark:hover:bg-surface-600'
                        ]">
                        {{ page }}
                    </button>
                    <span v-if="salesRecords.daily_sales.last_page > 5" class="px-1 text-gray-400">...</span>
                    <button v-if="salesRecords.daily_sales.last_page > 5"
                        @click="fetchData(salesRecords.daily_sales.last_page)"
                        :class="[
                            'min-w-[32px] h-8 px-2 flex items-center justify-center rounded-lg text-sm font-semibold transition-all',
                            salesRecords.daily_sales.current_page === salesRecords.daily_sales.last_page
                                ? 'bg-primary-500 text-white shadow-sm'
                                : 'text-text-secondary hover:bg-gray-100 dark:hover:bg-surface-600'
                        ]">
                        {{ salesRecords.daily_sales.last_page }}
                    </button>
                </div>

                <button 
                    @click="fetchData(salesRecords.daily_sales.current_page + 1)" 
                    :disabled="salesRecords.daily_sales.current_page === salesRecords.daily_sales.last_page"
                    class="flex-1 sm:flex-none px-4 py-2 rounded-lg text-sm font-semibold transition-all bg-primary-50 dark:bg-primary-500/10 text-primary-600 hover:bg-primary-100 dark:hover:bg-primary-500/20 disabled:opacity-50 disabled:cursor-not-allowed border border-primary-100 dark:border-primary-500/20 text-center">
                    <span class="hidden sm:inline">Selanjutnya</span>
                    <span class="sm:hidden">Next &rarr;</span>
                </button>
            </div>
        </div>

        <!-- Proof Photo Modal (HD Gallery) -->
        <div v-if="showProofModal"
            class="fixed inset-0 z-[99999] flex items-center justify-center p-4 px-6 sm:px-20 bg-black/90 backdrop-blur-md">
            <div class="relative w-full max-w-5xl h-full flex flex-col items-center justify-center py-12">
                <!-- Close Button -->
                <button @click="showProofModal = false"
                    class="absolute top-4 right-0 p-3 bg-white/10 hover:bg-white/20 text-white rounded-full transition-all z-[110] backdrop-blur-md border border-white/20 active:scale-95"
                    title="Tutup (ESC)">
                    <X :size="28" stroke-width="3" />
                </button>

                <!-- Images Container (HD Grid/Gallery) -->
                <div class="w-full flex-1 overflow-y-auto custom-scrollbar p-2">
                    <div :class="[
                        'w-full max-w-7xl mx-auto',
                        currentProofImages.length === 2 ? 'grid grid-cols-1 md:grid-cols-2 gap-6 items-start' : 'flex flex-col gap-10 items-center'
                    ]">
                        <div v-for="(imgUrl, index) in currentProofImages" :key="index"
                            class="w-full bg-white dark:bg-surface-800 rounded-[2.5rem] overflow-hidden shadow-[0_30px_60px_-15px_rgba(0,0,0,0.5)] border border-white/10 group">

                            <!-- HD Image Wrapper -->
                            <div class="relative overflow-hidden bg-gray-900 aspect-square sm:aspect-auto">
                                <img :src="imgUrl" :alt="'Foto Bukti ' + (index + 1)"
                                    class="w-full h-auto min-h-[400px] max-h-[70vh] object-contain transition-all duration-700 group-hover:scale-105"
                                    style="image-rendering: -webkit-optimize-contrast; filter: contrast(1.05) brightness(1.02) saturate(1.1);" />

                                <!-- HD Badge -->
                                <div
                                    class="absolute top-4 left-4 px-3 py-1 bg-black/40 backdrop-blur-md rounded-full border border-white/20 text-[10px] font-black text-white uppercase tracking-widest flex items-center gap-1.5">
                                    <div class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></div>
                                    HD Processing Active
                                </div>
                            </div>

                            <!-- Image Info Overlay (Glassmorphism) -->
                            <div
                                class="p-6 bg-white/90 dark:bg-surface-800/90 backdrop-blur-xl border-t border-gray-100 dark:border-surface-700 flex flex-col gap-4">
                                <div class="flex justify-between items-start">
                                    <div class="flex flex-col">
                                        <span
                                            class="text-[10px] font-black uppercase tracking-[0.2em] text-primary-600 mb-1">
                                            FOTO #{{ index + 1 }}
                                            <span v-if="index === 0 && currentProofImages.length > 1"
                                                class="ml-2 text-gray-400">— UNIT</span>
                                            <span v-else-if="index === 1" class="ml-2 text-gray-400">— CUSTOMER</span>
                                        </span>
                                        <h4 class="text-lg font-black text-text-primary leading-tight">Bukti CheckSales
                                        </h4>
                                    </div>
                                    <div class="flex gap-2">
                                        <a :href="imgUrl" target="_blank"
                                            class="p-3 bg-gray-100 dark:bg-surface-700 text-gray-600 dark:text-gray-300 rounded-2xl hover:bg-gray-200 dark:hover:bg-surface-600 transition-all active:scale-95"
                                            title="Buka Ukuran Penuh">
                                            <TrendingUp :size="20" />
                                        </a>
                                        <button @click="downloadImage(imgUrl)"
                                            class="flex-1 flex items-center justify-center gap-2 px-6 py-3 bg-primary-600 text-white rounded-2xl hover:bg-primary-700 transition-all text-xs font-black uppercase tracking-widest shadow-lg shadow-primary-500/30 active:scale-95">
                                            <Download :size="18" />
                                            Download
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Hint -->
                <div v-if="currentProofImages.length > 2"
                    class="mt-8 px-8 py-3 bg-white/5 backdrop-blur-2xl border border-white/10 rounded-full text-white/60 text-[10px] font-black uppercase tracking-[0.3em] animate-bounce">
                    Scroll untuk lihat {{ currentProofImages.length - 2 }} foto lainnya ↓
                </div>
            </div>
        </div>

        <!-- Unified Receipt Modal -->
        <ReceiptModal 
            :is-open="showReceiptModal" 
            :transaction="currentReceiptData" 
            :auto-send="autoSendReceipt"
            @close="showReceiptModal = false; autoSendReceipt = false"
            @open-screenshot="showReceiptModal = false; openScreenshot(currentReceiptData)"
        />

        <!-- Cancel Sale Modal -->
        <CancelSaleModal :show="showCancelModal" :sale="selectedSaleForCancel" @close="showCancelModal = false"
            @success="fetchData" />

        <!-- Sale Screenshot Modal -->
        <SaleScreenshot 
            :is-open="showScreenshotModal" 
            :sale="selectedSaleForScreenshot" 
            @close="showScreenshotModal = false" 
        />
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { Loader2, FileText, ChevronDown, Calendar, Image, User, Printer, X, Download, Trash2, AlertCircle, TrendingUp, Wallet, Smartphone, Box, Wrench, MessageSquare } from 'lucide-vue-next'
import axios from '../../api/axios'
import ReceiptModal from '../../components/modals/ReceiptModal.vue'
import CancelSaleModal from '../../components/modals/CancelSaleModal.vue'
import SaleScreenshot from '../../components/sales/SaleScreenshot.vue'
import { getLogicalDate, getTodayLocal } from '../../utils/formatters'
import { useEscapeKey } from '../../composables/useEscapeKey'

import { useAuthStore } from '../../store/auth'

const authStore = useAuthStore()

// GANTI KE false UNTUK MATIKAN MAINTENANCE DAN MENGEMBALIKAN KE NORMAL
const isMaintenance = ref(false)
const loading = ref(false)
const exportLoading = ref(false)
const selectedPeriod = ref('daily')

const categoryLabels = {
    'shopee': 'Shopee',
    'orderan_online': 'Order Online',
    'penjualan_offline': 'Penjualan Offline',
    'pindah_cabang': 'Pindah Cabang',
    'retur': 'Retur',
    'cancel_penjualan': 'Dibatalkan',
    'refund': 'Refund',
    'angkat_barang': 'Angkat Barang',
    'penjualan_store': 'Penjualan Store',
    'tukar_tambah': 'Tukar Tambah',
    'tukar_unit': 'Tukar Unit',
    'downgrade': 'Downgrade',
    'brand_ambassador': 'Brand Ambassador',
    'event_/_sponsorship': 'Event / Sponsorship',
    'event_sponsorship': 'Event / Sponsorship'
};

const months = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];// helpers getLogicalDate and getTodayLocal are now imported

const selectedMonth = ref(getLogicalDate().getMonth() + 1);
const selectedYear = ref(getLogicalDate().getFullYear());

const salesRecords = ref({
    daily_sales: [],
    brand_sales: [],
    cs_sales: []
})

const locations = ref([])
const filteredLocations = computed(() => {
    const role = (authStore.userRole || '').toLowerCase();
    const isExcludedRole = ['super_admin', 'analist', 'analis'].some(r => role.includes(r));
    if (!isExcludedRole) return locations.value;
    const excluded = ['trial', 'huft', 'anu', 'test', 'testing'];
    return (locations.value || []).filter(loc => !excluded.some(term => (loc.name || '').toLowerCase().includes(term)));
});
const isRestrictedLocation = computed(() => { const role = (authStore.userRole || '').toLowerCase(); return !privilegedRoles.some(r => role.includes(r)); });
const selectedLocationKey = ref('all')
const selectedBranchId = computed(() => {
    if (selectedLocationKey.value === 'all' || !selectedLocationKey.value.startsWith('B:')) return null;
    return selectedLocationKey.value.split(':')[1];
})

const selectedOnlineShopId = computed(() => {
    if (selectedLocationKey.value === 'all' || !selectedLocationKey.value.startsWith('S:')) return null;
    return selectedLocationKey.value.split(':')[1];
})

const privilegedRoles = ['super_admin', 'audit', 'owner', 'leader', 'analist', 'admin_produk'];

const canFilterBranch = computed(() => {
    const role = (authStore.userRole || '').toLowerCase();
    return privilegedRoles.some(r => role.includes(r));
})

const fetchLocations = async () => {
    try {
        const [branchRes, shopRes, userRes] = await Promise.all([
            axios.get('/branches'),
            axios.get('/online-shops'),
            axios.get('/user')
        ])

        const allBranches = (branchRes.data.data || branchRes.data || []).map(b => ({ ...b, type: 'branch' }));
        const allShops = (shopRes.data.data || shopRes.data || []).map(s => ({ ...s, type: 'online_shop' }));
        const allLocations = [...allBranches, ...allShops];

        const user = userRes.data.user || userRes.data.data || userRes.data;
        const role = (authStore.userRole || '').toLowerCase();

        const isGlobalRole = privilegedRoles.some(r => role.includes(r));

        let allowedBranchIds = [];
        if (user?.branch_id) allowedBranchIds.push(user.branch_id);

        let allowedShopIds = [];
        if (user?.online_shop_id) allowedShopIds.push(user.online_shop_id);

        if (user?.placements && Array.isArray(user.placements)) {
            user.placements.forEach(p => {
                if (p.model_type === 'branch') allowedBranchIds.push(p.model_id);
                if (p.model_type === 'online_shop') allowedShopIds.push(p.model_id);
            });
        }

        allowedBranchIds = [...new Set(allowedBranchIds.map(id => Number(id)))];
        allowedShopIds = [...new Set(allowedShopIds.map(id => Number(id)))];

        const hasAnyRestriction = allowedBranchIds.length > 0 || allowedShopIds.length > 0;

        if (isGlobalRole || (['leader'].includes(role) && !hasAnyRestriction)) {
            locations.value = allLocations;
        } else if (hasAnyRestriction) {
            locations.value = allLocations.filter(loc => {
                if (loc.type === 'branch') return allowedBranchIds.includes(Number(loc.id));
                if (loc.type === 'online_shop') return allowedShopIds.includes(Number(loc.id));
                return false;
            });
            if (locations.value.length > 0 && selectedLocationKey.value === 'all') {
                const first = locations.value[0];
                selectedLocationKey.value = `${first.type === 'branch' ? 'B' : 'S'}:${first.id}`;
            }
        } else {
            locations.value = [];
        }
    } catch (error) {
        console.error('Error fetching locations:', error)
    }
}

const handleLocationChange = () => {
    fetchData(1)
}

// Modals State
const showProofModal = ref(false)
const currentProofImages = ref([])
const showReceiptModal = ref(false)
const currentReceiptData = ref(null)
const showCancelModal = ref(false)
const selectedSaleForCancel = ref(null)

const showScreenshotModal = ref(false)
const selectedSaleForScreenshot = ref(null)

const autoSendReceipt = ref(false)

const sendWaReceipt = (item) => {
    let phone = (item.customer_wa || item.customer_phone || '').trim();
    if (!phone || phone === '-') {
        alert('Nomor WhatsApp tidak ditemukan atau kosong!');
        return;
    }
    
    currentReceiptData.value = item
    autoSendReceipt.value = true
    showReceiptModal.value = true
}

const viewProof = (imgUrl) => {
    currentProofImages.value = [imgUrl]
    showProofModal.value = true
}

const downloadImage = async (url) => {
    try {
        // Use backend proxy to bypass CORS and force download
        const response = await axios.get('/audit/sales/download-proof', {
            params: { url },
            responseType: 'blob'
        });

        const blob = new Blob([response.data]);
        const blobUrl = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = blobUrl;

        const filename = url.split('/').pop() || 'bukti-penjualan.jpg';
        link.download = filename;

        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(blobUrl);
    } catch (error) {
        console.error('Download failed:', error);
        // Fallback
        window.open(url, '_blank');
    }
}

useEscapeKey(() => {
    if (showProofModal.value) showProofModal.value = false
})

const openReceipt = (item) => {
    currentReceiptData.value = item
    autoSendReceipt.value = false
    showReceiptModal.value = true
}

const handleCancelSale = (item) => {
    selectedSaleForCancel.value = item;
    showCancelModal.value = true;
}

const openScreenshot = (item) => {
    selectedSaleForScreenshot.value = item;
    showScreenshotModal.value = true;
}

const years = computed(() => {
    const d = getLogicalDate();
    const currentYear = d.getFullYear();
    const role = (authStore.userRole || '').toLowerCase();
    const isRestricted = !privilegedRoles.some(r => role.includes(r));

    if (isRestricted) {
        return [currentYear];
    }
    return Array.from({ length: 5 }, (_, i) => currentYear - 2 + i);
});

const restrictedMonths = computed(() => {
    const d = getLogicalDate();
    const currentMonth = d.getMonth() + 1; // 1-indexed
    const currentYear = d.getFullYear();
    const role = (authStore.userRole || '').toLowerCase();
    const isRestricted = !privilegedRoles.some(r => role.includes(r));

    if (isRestricted && selectedYear.value === currentYear) {
        const lastMonth = new Date(d.getFullYear(), d.getMonth() - 1, 1).getMonth() + 1;
        return months.map((m, i) => ({ name: m, value: i + 1 }))
            .filter(m => m.value === currentMonth || m.value === lastMonth);
    }
    return months.map((m, i) => ({ name: m, value: i + 1 }));
});

const getMinDate = computed(() => {
    const role = (authStore.userRole || '').toLowerCase();
    const isRestricted = !privilegedRoles.some(r => role.includes(r));
    if (!isRestricted) return null;

    const d = getLogicalDate();
    d.setDate(d.getDate() - 7); // Allow past 7 days
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
});

const filters = ref({
    start_date: getTodayLocal(),
    end_date: getTodayLocal(),
})

const formattedDateDisplay = computed(() => {
    if (!filters.value.start_date) return 'Pilih Tanggal';
    if (selectedPeriod.value === 'daily') {
        const date = new Date(filters.value.start_date);
        return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
    } else {
        const monthIndex = selectedMonth.value - 1;
        return `${months[monthIndex]} ${selectedYear.value}`;
    }
})

// Summary stats
const activeRecords = computed(() => {
    const list = salesRecords.value.daily_sales?.data || salesRecords.value.daily_sales || []
    return Array.isArray(list) ? list.filter(item => item.category !== 'cancel_penjualan') : []
})
const totalSales = computed(() => activeRecords.value.reduce((sum, item) => {
    return sum + (parseFloat(item.grand_total) || 0);
}, 0))
const totalUnits = computed(() => activeRecords.value.reduce((sum, item) => {
    return sum + (parseInt(item.qty) || 0);
}, 0))
const totalLunas = computed(() => activeRecords.value.filter(item => item.status === 'Lunas').reduce((sum, item) => {
    return sum + (parseFloat(item.grand_total) || 0);
}, 0))
const totalBelumLunas = computed(() => activeRecords.value.filter(item => item.status !== 'Lunas').reduce((sum, item) => {
    return sum + (parseFloat(item.grand_total) || 0);
}, 0))

const summaryStats = computed(() => {
    // If backend pre-calculated summary is available, use its totals directly for perfect alignment with dashboard & ranking history!
    const summary = salesRecords.value?.report_summary;
    const hasSummary = summary !== undefined && summary !== null;

    let baseSales = 0;
    let tradeSelisih = 0;
    let tradeOutgoingTotal = 0;
    let tradeOutgoingTT = 0;
    let tradeIncomingTotal = 0;
    let outlay = 0;
    let hpUnitsIn = 0;
    let hpUnitsOut = 0;
    let nonHpUnits = 0;

    const resolveActualCategory = (category, notes, salesAccount) => {
        const cat = (category || '').toLowerCase();
        const n = (notes || '').toLowerCase();
        const sa = (salesAccount || '').toLowerCase();

        // If it's a standard sale category, do NOT override with deductions by notes
        if (['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'pos', 'sale', 'bundling', 'tukar_tambah'].includes(cat)) {
            if (n.includes('tukar tambah') || n.includes('tukar_tambah') || sa.includes('tukar tambah') || sa.includes('tukar_tambah')) {
                return 'tukar_tambah';
            }
            return cat;
        }

        if (n.includes('barang angkat') || n.includes('angkat barang') || n.includes('angkat_barang') || sa.includes('barang angkat') || sa.includes('angkat barang') || sa.includes('angkat_barang')) {
            return 'angkat_barang';
        }
        if (n.includes('refund') || sa.includes('refund')) {
            return 'refund';
        }
        if (n.includes('downgrade') || sa.includes('downgrade')) {
            return 'downgrade';
        }
        if (n.includes('tukar tambah') || n.includes('tukar_tambah') || sa.includes('tukar tambah') || sa.includes('tukar_tambah')) {
            return 'tukar_tambah';
        }
        return cat;
    };

    activeRecords.value.forEach(item => {
        const origCat = item.category?.toLowerCase();
        const cat = resolveActualCategory(item.category, item.notes, item.sales_account || item.inventory_user_name);

        let total = 0;
        const discount = parseFloat(item.total_discount) || 0;
        if (item.items && item.items.length > 0) {
            total = item.items.reduce((sum, detail) => sum + (Math.abs(parseFloat(detail.price)) * parseFloat(detail.qty || 1)), 0);
        } else {
            total = Math.abs(parseFloat(item.original_price || item.total_amount || item.grand_total) || 0);
        }
        // Discount is already applied to detail.price (selling_price) or grand_total by the backend.
        // We do not subtract it again to prevent double discounting.
        
        // Standard Sales categories
        const isBaseSale = ['shopee', 'orderan_online', 'penjualan_offline', 'penjualan_store', 'pos', 'sale', 'bundling', 'brand_ambassador', 'event_/_sponsorship', 'event_sponsorship'].includes(cat);
        const isTradeIn = ['tukar_tambah', 'downgrade'].includes(cat);
        const isDeduction = ['refund', 'angkat_barang'].includes(cat);

        if (isBaseSale) {
            baseSales += total;
        } else if (isTradeIn) {
            // Universal Trade-In Extraction Logic
            const outVal = Math.abs(parseFloat(item.price_out) || (cat === 'tukar_tambah' ? total : 0));
            const inVal = Math.abs(parseFloat(item.price_in) || (cat === 'downgrade' ? (parseFloat(item.price_out) || 0) + total : 0));

            // Segregation rules satisfying user's distinct accounting logic for TT vs DG
            if (cat === 'tukar_tambah' || cat === 'downgrade') {
                tradeOutgoingTotal += outVal; 
                tradeIncomingTotal += inVal;
            }
        }

        if (isDeduction) {
            outlay += total;
        }

        // Unit Logic: Separate IN and OUT
        if (item.items && item.items.length > 0) {
            item.items.forEach(detail => {
                const qty = parseInt(detail.qty) || 0;
                const isHp = detail.imei && detail.imei !== '-';
                if (isHp) {
                    const name = (detail.name || '').toUpperCase();
                    if (name.startsWith('IN:')) {
                        hpUnitsIn += qty;
                    } else if (name.startsWith('OUT:')) {
                        hpUnitsOut += qty;
                    } else {
                        // Fallback logic by category
                        if (['refund', 'angkat_barang'].includes(cat)) {
                            hpUnitsIn += qty;
                        } else {
                            hpUnitsOut += qty;
                        }
                    }
                } else {
                    nonHpUnits += qty;
                }
            });
        } else {
            const qty = parseInt(item.qty) || 0;
            const hasImei = (item.imei && item.imei !== '-') || (item.imeis && item.imeis !== '-');
            if (hasImei) {
                if (['refund', 'angkat_barang'].includes(cat)) {
                    hpUnitsIn += qty;
                } else {
                    hpUnitsOut += qty;
                }
            } else {
                nonHpUnits += qty;
            }
        }
    });

    let finalOmset = hasSummary ? (summary.payment_total ?? 0) : (baseSales + tradeOutgoingTotal);
    let finalOmsetBersih = hasSummary ? (summary.omset_bersih ?? 0) : (finalOmset - (outlay + tradeIncomingTotal));

    if (hasSummary && summary.activities && summary.activities.details) {
        const acts = summary.activities.details;
        let summaryOutlay = 0;
        let summaryTradeIncoming = 0;
        
        // Sum deductions from activity details
        ['refund', 'angkat_barang'].forEach(cat => {
            if (acts[cat]) {
                acts[cat].forEach(item => {
                    summaryOutlay += parseFloat(item.price || 0);
                });
            }
        });
        
        // Sum downgrade and in_tt for trade incoming
        ['downgrade', 'in_tt'].forEach(cat => {
            if (acts[cat]) {
                acts[cat].forEach(item => {
                    summaryTradeIncoming += parseFloat(item.price || 0);
                });
            }
        });

        // The user explicitly requested: omset bersih = total omset - (angkat barang + refund + selisih downgrade)
        // We use backend's omset_bersih because backend now correctly calculates Omset using actual payments
        // We can trust the backend's calculations.
    } else if (!hasSummary) {
        finalOmset = baseSales + tradeOutgoingTotal;
        finalOmsetBersih = finalOmset - (outlay + tradeIncomingTotal);
    }

    // Unit HP & Non-HP values fall back to backend report_summary values when available
    let finalHpUnitsOut = hpUnitsOut;
    let finalHpUnitsIn = hpUnitsIn;
    let finalNonHpUnits = nonHpUnits;

    if (hasSummary && summary.dist_map) {
        const distMap = summary.dist_map;
        const acts = summary.activities || {};
        const iphone = parseInt(distMap.iphone) || 0;
        const android = parseInt(distMap.android) || 0;
        const appleLux = parseInt(distMap.apple_lux) || 0;
        
        finalHpUnitsOut = iphone + android + appleLux;
        
        const refundUnits = parseInt(acts.refund) || 0;
        const abUnits = parseInt(acts.angkat_barang) || 0;
        const inTtUnits = parseInt(acts.in_tt) || 0;
        const dgUnits = parseInt(acts.downgrade) || 0;
        finalHpUnitsIn = refundUnits + abUnits + inTtUnits + dgUnits;

        const nonHpKeys = ['accessories', 'apply', 'debs', 'arcis', 'dokter_pstore', 'laptop', 'tv', 'jaringan', 'sim_card', 'pspatu', 'psshion', 'icloud', 'others'];
        finalNonHpUnits = nonHpKeys.reduce((sum, key) => sum + (parseInt(distMap[key]) || 0), 0);
    }

    return {
        totalOmset: finalOmset,
        omsetBersih: finalOmsetBersih,
        hpUnitsOut: finalHpUnitsOut,
        hpUnitsIn: finalHpUnitsIn,
        nonHpUnits: finalNonHpUnits
    };
});

const formatCurrency = (val) => {
    const num = parseFloat(val) || 0;
    const formatted = new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(Math.abs(num));
    return num < 0 ? `-${formatted}` : formatted;
}

const handlePeriodChange = () => {
    if (selectedPeriod.value === 'daily') {
        const today = getTodayLocal();
        filters.value.start_date = today;
        filters.value.end_date = today;
    } else {
        handleMonthChange();
    }
    fetchData(1);
}

const handleDateChange = () => {
    // Enforce min date restriction (iOS/Android ignore min attribute)
    if (getMinDate.value && filters.value.start_date < getMinDate.value) {
        alert('Anda hanya bisa melihat data 7 hari terakhir.');
        filters.value.start_date = getMinDate.value;
    }
    if (selectedPeriod.value === 'daily') {
        filters.value.end_date = filters.value.start_date;
    }
    fetchData(1);
}

const handleMonthChange = () => {
    const year = selectedYear.value;
    const month = selectedMonth.value;
    const endDate = new Date(year, month, 0);
    const pad = (n) => n < 10 ? '0' + n : n;
    filters.value.start_date = `${year}-${pad(month)}-01`;
    filters.value.end_date = `${year}-${pad(month)}-${pad(endDate.getDate())}`;
    if (selectedPeriod.value === 'monthly') {
        fetchData(1);
    }
}

const formatDate = (dateString) => {
    if (!dateString) return '-'
    return new Date(dateString).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
}

const canCancel = (date) => {
    // Roles that can always cancel regardless of date
    const role = (authStore.userRole || '').toLowerCase();
    if (role === 'super_admin' || role === 'owner') return true;

    if (!date) return false;

    // Normalisasi waktu ke WITA/WIB sesuai zona server (UTC+7/8) atau ke local midnight
    // item.date formatnya adalah "YYYY-MM-DD HH:mm:ss" dari backend
    const itemDate = new Date(date);
    if (isNaN(itemDate.getTime())) return false;

    const today = getLogicalDate();

    // Reset ke jam 00:00:00 untuk perbandingan hari yang murni
    today.setHours(0, 0, 0, 0);
    itemDate.setHours(0, 0, 0, 0);

    const msPerDay = 24 * 60 * 60 * 1000;
    const diffDays = Math.round((today.getTime() - itemDate.getTime()) / msPerDay);

    // Jika hari ini tanggal 6, maka:
    // 6 - 6 = 0 (OK)
    // 6 - 1 = 5 (OK)
    // 6 - 31 = 6 (BLOCKED)
    return diffDays <= 5;
}

const fetchData = async (page = 1) => {
    loading.value = true
    try {
        const params = {
            ...filters.value,
            branch_id: selectedBranchId.value,
            online_shop_id: selectedOnlineShopId.value,
            page: page
        };
        // Toko online only sees tukar_unit category
        const role = (authStore.userRole || '').toLowerCase();
        if (role.includes('toko_online') || role.includes('online')) {
            params.category = 'tukar_unit';
        }
        const response = await axios.get('/audit/sales', { params })
        salesRecords.value = response.data
    } catch (error) {
        console.error('Error fetching sales:', error)
    } finally {
        loading.value = false
    }
}

const handleExport = async () => {
    exportLoading.value = true;
    try {
        const params = {
            start_date: filters.value.start_date,
            end_date: filters.value.end_date,
            branch_id: selectedBranchId.value,
            online_shop_id: selectedOnlineShopId.value
        };

        const response = await axios.get('/audit/sales/export', {
            params,
            responseType: 'blob'
        });

        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', `Laporan-Penjualan-${filters.value.start_date}-to-${filters.value.end_date}.xlsx`);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
    } catch (error) {
        console.error('Export failed:', error);
    } finally {
        exportLoading.value = false;
    }
};

const formatNumber = (num) => {
    if (!num) return '0'
    return new Number(num).toLocaleString('id-ID')
}

onMounted(() => {
    const today = getTodayLocal();
    filters.value.start_date = today;
    filters.value.end_date = today;
    fetchLocations()
    fetchData(1)

    // Listen for Real-time sales updates via WebSockets (Laravel Echo/Reverb)
    if (window.Echo) {
        window.Echo.channel('stock-out')
            .listen('.StockOutEvent', (e) => {
                const evt = e?.stockOut;
                if (!evt) return;

                const targetBranch = evt.branch_id;
                const targetOnline = evt.online_shop_id;

                // CRITICAL FIX: Implement intelligent filter enforcement to prevent cross-branch reload fatigue

                // 1. Explicit View Filter Check: If user specifically filtered one location, ignore everything else.
                if (selectedBranchId.value && String(targetBranch) !== String(selectedBranchId.value)) {
                    return; // Ignore: Doesn't match active branch filter
                }
                if (selectedOnlineShopId.value && String(targetOnline) !== String(selectedOnlineShopId.value)) {
                    return; // Ignore: Doesn't match active online store filter
                }

                // 2. Generic View Access Check: If viewing "All" (or local implicit view), restrict by user access set
                if (!selectedBranchId.value && !selectedOnlineShopId.value) {
                    const hasAccess = (locations.value || []).some(loc => {
                        if (loc.type === 'branch' && String(loc.id) === String(targetBranch)) return true;
                        if (loc.type === 'online_shop' && String(loc.id) === String(targetOnline)) return true;
                        return false;
                    });

                    if (!hasAccess) {
                        // Completely unrelated activity (e.g. User is in Palu, event is Gorontalo) -> SILENT IGNORE
                        return;
                    }
                }

                console.log('[Realtime] Verified match for current scope, refetching list...', evt.receipt_id);
                fetchData(salesRecords.value.daily_sales?.current_page || 1);
            });
    }
})

onUnmounted(() => {
    // Clean up listeners to prevent duplicate triggers/memory leaks
    if (window.Echo) {
        window.Echo.leave('stock-out');
    }
})
</script>
