<template>
    <div class="space-y-6 animate-in">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div class="flex items-center gap-3">
                <button v-if="currentView !== 'menu'" @click="goBack"
                    class="p-2 hover:bg-surface-800 rounded-xl transition-colors">
                    <ArrowLeft :size="20" class="text-text-secondary" />
                </button>
                <div v-else class="w-12 h-12 bg-amber-500/20 rounded-xl flex items-center justify-center">
                    <Trophy :size="24" class="text-amber-500" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-text-primary tracking-tight">Peringkat Penjualan</h1>
                    <p class="text-text-secondary mt-0.5 text-sm">
                        {{ viewLabels[currentView] || 'Lihat peringkat penjualan dan performa sales' }}
                    </p>
                </div>
            </div>

            <!-- Filters (Period & Date) -->
            <div class="flex flex-wrap items-center gap-2">
                <!-- Refresh Button -->
                <button @click="fetchData"
                    class="p-2.5 text-text-secondary hover:text-primary-500 hover:bg-primary-500/10 rounded-xl transition-all mr-2">
                    <RefreshCw :size="20" :class="{ 'animate-spin': loading }" />
                </button>

                <div
                    class="flex items-center gap-2 bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl p-1">
                    <button v-for="p in ['daily', 'monthly']" :key="p" @click="selectedPeriod = p; handlePeriodChange()"
                        class="px-4 py-1.5 rounded-lg text-xs font-bold transition-all"
                        :class="selectedPeriod === p ? 'bg-primary-500 text-white shadow-sm' : 'text-text-secondary hover:text-text-primary'">
                        {{ p === 'daily' ? 'Harian' : 'Bulanan' }}
                    </button>
                </div>

                <div v-if="selectedPeriod === 'daily'" class="relative group">
                    <div
                        class="flex items-center gap-2 px-4 py-2 bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl hover:border-primary-500 transition-all cursor-pointer">
                        <Calendar :size="16" class="text-gray-500 group-hover:text-primary-500" />
                        <span class="text-xs font-bold text-text-primary">{{ formattedDateDisplay }}</span>
                    </div>
                    <input type="date" v-model="filters.start_date" @change="handleDateChange"
                        @click="$event.target.showPicker()" :min="minDate" :max="todayDate"
                        class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-20" />
                </div>

                <div v-else class="flex items-center gap-2">
                    <select v-model="selectedMonth" @change="handleMonthChange"
                        class="bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-3 py-2 text-xs font-bold cursor-pointer text-text-primary focus:ring-0 shadow-sm transition-all hover:border-primary-500">
                        <option v-for="m in availableMonths" :key="m.value" :value="m.value">{{ m.name }}</option>
                    </select>
                    <select v-model="selectedYear" @change="handleMonthChange"
                        class="bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-3 py-2 text-xs font-bold cursor-pointer text-text-primary focus:ring-0 shadow-sm transition-all hover:border-primary-500">
                        <option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option>
                    </select>
                </div>

                <!-- Location Filter (Branch/OS) - Only for non-restricted users -->
                <div v-if="!isRestricted"
                    class="flex items-center gap-2 bg-white dark:!bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl p-1 shadow-sm">
                    <div class="flex items-center gap-1 group">
                        <div
                            class="p-1.5 bg-gray-50 dark:bg-surface-900 rounded-lg group-hover:bg-primary-500/10 transition-colors">
                            <MapPin v-if="locationType === 'branch'" :size="14"
                                class="text-text-secondary group-hover:text-primary-500" />
                            <Globe v-else :size="14" class="text-text-secondary group-hover:text-primary-500" />
                        </div>
                        <select v-model="locationType" @change="handleLocationTypeChange"
                            class="bg-transparent border-none text-[10px] uppercase tracking-wider font-black text-text-secondary focus:ring-0 cursor-pointer pr-6">
                            <option value="branch">Cabang</option>
                            <option value="online">Online</option>
                        </select>
                    </div>
                    <div class="w-px h-4 bg-gray-200 dark:bg-surface-700 mr-1"></div>
                    <select v-if="locationType === 'branch'" v-model="filters.branch_id" @change="fetchData"
                        class="bg-transparent border-none text-xs font-bold text-text-primary focus:ring-0 cursor-pointer min-w-[140px] appearance-none pr-8">
                        <option :value="null">Semua Cabang</option>
                        <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                    <select v-else v-model="filters.online_shop_id" @change="fetchData"
                        class="bg-transparent border-none text-xs font-bold text-text-primary focus:ring-0 cursor-pointer min-w-[140px] appearance-none pr-8">
                        <option :value="null">Semua Toko Online</option>
                        <option v-for="s in onlineShops" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>
                <!-- Restricted users see their location indicator -->
                <div v-else
                    class="flex items-center gap-2 px-4 py-2 bg-primary-500/5 border border-primary-500/20 rounded-xl">
                    <MapPin v-if="authStore.user?.branch_id" :size="14" class="text-primary-500" />
                    <Globe v-else :size="14" class="text-primary-500" />
                    <span class="text-xs font-bold text-primary-600">
                        {{ authStore.user?.branch?.name || authStore.user?.online_shop?.name || 'Cabang Saya' }}
                    </span>
                </div>
            </div>
        </div>

        <!-- ==================== MENU LANDING ==================== -->
        <template v-if="currentView === 'menu'">
            <div v-if="loading" class="flex flex-col items-center justify-center py-20">
                <Loader2 class="animate-spin text-primary-500 mb-4" :size="40" />
                <p class="text-text-secondary text-sm font-medium">Memuat data peringkat...</p>
            </div>

            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <!-- Card: Per Hari (Replacing Per Omset) -->
                <button @click="navigateTo('revenue')"
                    class="group bg-white dark:!bg-surface-800 rounded-2xl border border-gray-100 dark:border-surface-700 hover:border-amber-500/50 p-6 text-left transition-all duration-300 hover:shadow-lg hover:shadow-amber-500/5 hover:-translate-y-1">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-amber-500/10 rounded-xl group-hover:bg-amber-500/20 transition-colors">
                            <Calendar :size="24" class="text-amber-500" />
                        </div>
                        <ChevronRight :size="20"
                            class="text-text-secondary group-hover:text-amber-500 transition-colors" />
                    </div>
                    <h3 class="text-lg font-bold text-text-primary mb-1">Peringkat per Hari</h3>
                    <p class="text-sm text-text-secondary">Ringkasan total nilai penjualan per tanggal</p>
                </button>

                <!-- Card: Per Sales -->
                <button @click="navigateTo('sales')"
                    class="group bg-white dark:!bg-surface-800 rounded-2xl border border-gray-100 dark:border-surface-700 hover:border-blue-500/50 p-6 text-left transition-all duration-300 hover:shadow-lg hover:shadow-blue-500/5 hover:-translate-y-1">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-blue-500/10 rounded-xl group-hover:bg-blue-500/20 transition-colors">
                            <Users :size="24" class="text-blue-500" />
                        </div>
                        <ChevronRight :size="20"
                            class="text-text-secondary group-hover:text-blue-500 transition-colors" />
                    </div>
                    <h3 class="text-lg font-bold text-text-primary mb-1">Peringkat per Sales</h3>
                    <p class="text-sm text-text-secondary">Ranking sales berdasarkan jumlah unit terjual</p>
                </button>

                <!-- Card: Per Brand -->
                <button @click="navigateTo('brand')"
                    class="group bg-white dark:!bg-surface-800 rounded-2xl border border-gray-100 dark:border-surface-700 hover:border-purple-500/50 p-6 text-left transition-all duration-300 hover:shadow-lg hover:shadow-purple-500/5 hover:-translate-y-1">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-purple-500/10 rounded-xl group-hover:bg-purple-500/20 transition-colors">
                            <Layers :size="24" class="text-purple-500" />
                        </div>
                        <ChevronRight :size="20"
                            class="text-text-secondary group-hover:text-purple-500 transition-colors" />
                    </div>
                    <h3 class="text-lg font-bold text-text-primary mb-1">Penjualan per Brand</h3>
                    <p class="text-sm text-text-secondary">Ringkasan penjualan berdasarkan merek produk</p>
                </button>

                <!-- Card: Per Tipe -->
                <button @click="navigateTo('type')"
                    class="group bg-white dark:!bg-surface-800 rounded-2xl border border-gray-100 dark:border-surface-700 hover:border-emerald-500/50 p-6 text-left transition-all duration-300 hover:shadow-lg hover:shadow-emerald-500/5 hover:-translate-y-1">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-emerald-500/10 rounded-xl group-hover:bg-emerald-500/20 transition-colors">
                            <Smartphone :size="24" class="text-emerald-500" />
                        </div>
                        <ChevronRight :size="20"
                            class="text-text-secondary group-hover:text-emerald-500 transition-colors" />
                    </div>
                    <h3 class="text-lg font-bold text-text-primary mb-1">Penjualan per Tipe</h3>
                    <p class="text-sm text-text-secondary">Ringkasan penjualan berdasarkan tipe/model produk</p>
                </button>

                <!-- Card: Per Kondisi -->
                <button @click="navigateTo('condition')"
                    class="group bg-white dark:!bg-surface-800 rounded-2xl border border-gray-100 dark:border-surface-700 hover:border-orange-500/50 p-6 text-left transition-all duration-300 hover:shadow-lg hover:shadow-orange-500/5 hover:-translate-y-1">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-orange-500/10 rounded-xl group-hover:bg-orange-500/20 transition-colors">
                            <Tag :size="24" class="text-orange-500" />
                        </div>
                        <ChevronRight :size="20"
                            class="text-text-secondary group-hover:text-orange-500 transition-colors" />
                    </div>
                    <h3 class="text-lg font-bold text-text-primary mb-1">Penjualan per Kondisi</h3>
                    <p class="text-sm text-text-secondary">Ringkasan penjualan berdasarkan kondisi (New/Second)</p>
                </button>

                <!-- Card: Activity Ranking -->
                <button @click="navigateTo('activity')"
                    class="group bg-white dark:!bg-surface-800 rounded-2xl border border-gray-100 dark:border-surface-700 hover:border-red-500/50 p-6 text-left transition-all duration-300 hover:shadow-lg hover:shadow-red-500/5 hover:-translate-y-1">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-red-500/10 rounded-xl group-hover:bg-red-500/20 transition-colors">
                            <RotateCcw :size="24" class="text-red-500" />
                        </div>
                        <ChevronRight :size="20"
                            class="text-text-secondary group-hover:text-red-500 transition-colors" />
                    </div>
                    <h3 class="text-lg font-bold text-text-primary mb-1">Angkat Barang & Refund</h3>
                    <p class="text-sm text-text-secondary">Ranking sales berdasarkan jumlah refund & angkat barang</p>
                </button>

                <!-- Card: Per Distributor -->
                <button @click="navigateTo('distributor')"
                    class="group bg-white dark:!bg-surface-800 rounded-2xl border border-gray-100 dark:border-surface-700 hover:border-indigo-500/50 p-6 text-left transition-all duration-300 hover:shadow-lg hover:shadow-indigo-500/5 hover:-translate-y-1">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-indigo-500/10 rounded-xl group-hover:bg-indigo-500/20 transition-colors">
                            <Truck :size="24" class="text-indigo-500" />
                        </div>
                        <ChevronRight :size="20"
                            class="text-text-secondary group-hover:text-indigo-500 transition-colors" />
                    </div>
                    <h3 class="text-lg font-bold text-text-primary mb-1">Peringkat per Distributor</h3>
                    <p class="text-sm text-text-secondary">Ranking penjualan berdasarkan asal distributor</p>
                </button>

                <!-- Card: Laporan Penjualan -->
                <button @click="openSalesReport"
                    class="group bg-white dark:!bg-surface-800 rounded-2xl border border-gray-100 dark:border-surface-700 hover:border-primary-500/50 p-6 text-left transition-all duration-300 hover:shadow-lg hover:shadow-primary-500/5 hover:-translate-y-1">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-primary-500/10 rounded-xl group-hover:bg-primary-500/20 transition-colors">
                            <FileText :size="24" class="text-primary-500" />
                        </div>
                        <ChevronRight :size="20"
                            class="text-text-secondary group-hover:text-primary-500 transition-colors" />
                    </div>
                    <h3 class="text-lg font-bold text-text-primary mb-1">Laporan Penjualan</h3>
                    <p class="text-sm text-text-secondary">Jenerate laporan teks lengkap untuk dikopi</p>
                </button>
            </div>
        </template>

        <!-- ==================== REPORT VIEW (NEW ERA) ==================== -->
        <template v-else-if="currentView === 'report'">
            <div class="flex justify-center pb-12 w-full px-4 sm:px-6">
                <!-- REPORT CARD NEW ERA UI -->
                <div
                    class="bg-[#eafdf3] dark:bg-surface-900 w-full max-w-4xl rounded-[32px] shadow-[0_20px_60px_-15px_rgba(16,185,129,0.15)] overflow-hidden animate-in fade-in slide-in-from-bottom-4 duration-500 border border-emerald-100 dark:border-emerald-900/30">

                    <!-- Action Buttons Overlay -->
                    <div class="p-6 md:p-8 flex justify-between items-start relative z-10 w-full">
                        <button @click="currentView = 'menu'"
                            class="p-3 bg-white/60 dark:bg-surface-800/60 hover:bg-emerald-100 dark:hover:bg-surface-700 text-emerald-800 dark:text-emerald-400 rounded-full transition-all backdrop-blur-md shadow-sm border border-emerald-100/50 dark:border-surface-600/50 hover:scale-105 active:scale-95">
                            <ArrowLeft :size="20" />
                        </button>

                        <button @click="copyReportToClipboard"
                            class="flex items-center gap-2 px-8 py-3 bg-[#0fa968] hover:bg-[#0cd07f] text-white rounded-full font-black tracking-wider text-xs shadow-xl shadow-emerald-500/25 transition-all hover:scale-105 active:scale-95 group">
                            <Copy v-if="!reportCopied" :size="16" class="group-hover:scale-110 transition-transform" />
                            <Check v-else :size="16" />
                            {{ reportCopied ? 'TERSALIN!' : 'SALIN LAPORAN' }}
                        </button>
                    </div>

                    <div class="px-8 md:px-16 pb-16 mt-[-20px]">
                        <!-- Header -->
                        <div class="text-center mb-10">
                            <p
                                class="text-[10px] font-black tracking-[0.35em] text-emerald-800/80 dark:text-emerald-500 uppercase mb-4">
                                STOCK REPORT</p>
                            <h2 class="text-3xl sm:text-4xl font-black text-gray-900 dark:text-white uppercase tracking-tighter mb-4">
                                {{ authStore.user?.branch?.name || authStore.user?.online_shop?.name || 'PSTORE TRANSAKSI' }}
                            </h2>
                            <p
                                class="text-xs font-bold text-emerald-600 dark:text-emerald-400 flex items-center justify-center gap-2 tracking-wider">
                                <span class="w-1 h-1 rounded-full bg-emerald-500"></span>
                                {{ formattedDateDisplay }}
                            </p>
                        </div>

                        <!-- Divider -->
                        <div class="h-px bg-emerald-200/70 dark:bg-surface-700/50 w-full mb-8"></div>

                        <!-- PENJUALAN ALL -->
                        <div class="space-y-4 mb-2">
                            <div v-for="(amount, method) in (salesData?.report_summary?.payments || {})" :key="method"
                                class="flex justify-between items-center text-sm font-bold text-emerald-950 dark:text-gray-200 py-1 border-b border-emerald-100/50 dark:border-surface-700/30">
                                <span class="uppercase tracking-wide">{{ method }}</span>
                                <span>{{ formatCurrency(amount) }}</span>
                            </div>
                        </div>

                        <div class="flex justify-between items-center text-xl font-black text-emerald-950 dark:text-white mt-8 mb-12">
                            <span class="uppercase tracking-wider italic">TOTAL OMSET</span>
                            <span class="text-2xl">{{ formatCurrency(salesData?.report_summary?.payment_total || 0) }}</span>
                        </div>

                        <!-- RINCIAN PENJUALAN DISTRIBUTOR -->
                        <h3
                            class="text-sm font-black tracking-[0.1EM] text-emerald-950/50 dark:text-white/50 text-center mb-8 uppercase">
                            Rincian Penjualan Distributor</h3>

                        <div class="space-y-0 text-sm font-bold text-gray-800 dark:text-gray-300">
                            <div
                                class="flex justify-between items-center py-4 border-b border-emerald-100 dark:border-surface-700/50">
                                <div class="flex items-center gap-3">
                                    <div class="w-2.5 h-2.5 bg-blue-500 rounded-sm"></div> <span
                                        class="capitalize">Penjualan HP</span>
                                </div>
                                <span>{{ formatCurrency(salesData?.report_summary?.dist_map_rp?.hp || 0) }}</span>
                            </div>
                            <div class="flex justify-between items-center py-4 border-b border-emerald-100 dark:border-surface-700/50">
                                <div class="flex items-center gap-3">
                                    <div class="w-2.5 h-2.5 bg-blue-500 rounded-sm"></div> <span class="capitalize">Penjualan Apple Lux</span>
                                </div>
                                <span>{{ formatCurrency(salesData?.report_summary?.dist_map_rp?.apple_lux || 0) }}</span>
                            </div>
                            <div class="flex justify-between items-center py-4 border-b border-emerald-100 dark:border-surface-700/50">
                                <div class="flex items-center gap-3">
                                    <div class="w-2.5 h-2.5 border border-gray-400 bg-white rounded-sm"></div> <span class="capitalize">Penjualan Accesories</span>
                                </div>
                                <span>{{ formatCurrency(salesData?.report_summary?.dist_map_rp?.accessories || 0) }}</span>
                            </div>
                            <div class="flex justify-between items-center py-4 border-b border-emerald-100 dark:border-surface-700/50">
                                <div class="flex items-center gap-3">
                                    <div class="w-2.5 h-2.5 border border-gray-400 bg-white rounded-sm"></div> <span class="capitalize">Penjualan Apply</span>
                                </div>
                                <span>{{ formatCurrency(salesData?.report_summary?.dist_map_rp?.apply || 0) }}</span>
                            </div>
                            <div class="flex justify-between items-center py-4 border-b border-emerald-100 dark:border-surface-700/50">
                                <div class="flex items-center gap-3">
                                    <div class="w-2.5 h-2.5 border border-gray-400 bg-white rounded-sm"></div> <span class="capitalize">Penjualan Debs</span>
                                </div>
                                <span>{{ formatCurrency(salesData?.report_summary?.dist_map_rp?.debs || 0) }}</span>
                            </div>
                            <div class="flex justify-between items-center py-4 border-b border-emerald-100 dark:border-surface-700/50">
                                <div class="flex items-center gap-3">
                                    <div class="w-2.5 h-2.5 border border-gray-400 bg-white rounded-sm"></div> <span class="capitalize">Penjualan Arcis</span>
                                </div>
                                <span>{{ formatCurrency(salesData?.report_summary?.dist_map_rp?.arcis || 0) }}</span>
                            </div>
                            <div class="flex justify-between items-center py-4 border-b border-emerald-100 dark:border-surface-700/50">
                                <div class="flex items-center gap-3">
                                    <div class="w-2.5 h-2.5 border border-gray-400 bg-white rounded-sm"></div> <span class="capitalize">Penjualan Dokter Pstore</span>
                                </div>
                                <span>{{ formatCurrency(salesData?.report_summary?.dist_map_rp?.dokter_pstore || 0) }}</span>
                            </div>
                            <div class="flex justify-between items-center py-4 border-b border-emerald-100 dark:border-surface-700/50">
                                <div class="flex items-center gap-3">
                                    <div class="w-2.5 h-2.5 border border-gray-400 bg-white rounded-sm"></div> <span class="capitalize">Penjualan Perdana</span>
                                </div>
                                <span>{{ formatCurrency(salesData?.report_summary?.dist_map_rp?.perdana || 0) }}</span>
                            </div>
                            <div v-if="salesData?.report_summary?.dist_map_rp?.jaringan > 0" class="flex justify-between items-center py-4 border-b border-emerald-100 dark:border-surface-700/50">
                                <div class="flex items-center gap-3">
                                    <div class="w-2.5 h-2.5 border border-gray-400 bg-white rounded-sm"></div> <span class="capitalize">Penjualan Jaringan</span>
                                </div>
                                <span>{{ formatCurrency(salesData?.report_summary?.dist_map_rp?.jaringan || 0) }}</span>
                            </div>
                        </div>

                        <div class="h-px bg-emerald-200/70 dark:bg-surface-700/50 w-full my-12"></div>

                        <!-- TOTAL UNIT HP -->
                        <div class="space-y-0 text-sm font-bold text-gray-800 dark:text-gray-300">
                            <div class="flex justify-between items-center py-4 border-b border-emerald-100 dark:border-surface-700/50">
                                <span class="capitalize">Iphone</span>
                                <span class="text-emerald-950 font-black">{{ salesData?.report_summary?.dist_map?.iphone || 0 }}</span>
                            </div>
                            <div class="flex justify-between items-center py-4 border-b border-emerald-100 dark:border-surface-700/50">
                                <span class="capitalize">Apple Lux</span>
                                <span class="text-emerald-950 font-black">{{ salesData?.report_summary?.dist_map?.apple_lux || 0 }}</span>
                            </div>
                            <div class="flex justify-between items-center py-4 border-b border-emerald-100 dark:border-surface-700/50">
                                <span class="capitalize">Android</span>
                                <span class="text-emerald-950 font-black">{{ salesData?.report_summary?.dist_map?.android || 0 }}</span>
                            </div>
                            <div class="flex justify-between items-center py-4 border-b border-emerald-100 dark:border-surface-700/50">
                                <span class="capitalize">Laptop</span>
                                <span class="text-emerald-950 font-black">{{ salesData?.report_summary?.dist_map?.laptop || 0 }}</span>
                            </div>
                            <div class="flex justify-between items-center py-4 border-b border-emerald-100 dark:border-surface-700/50">
                                <span class="capitalize">Tv</span>
                                <span class="text-emerald-950 font-black">{{ salesData?.report_summary?.dist_map?.tv || 0 }}</span>
                            </div>
                        </div>

                        <div class="flex justify-between items-center text-xl font-black text-gray-900 dark:text-white mt-8 mb-12">
                            <span class="uppercase tracking-widest italic">TOTAL HANDPHONE</span>
                            <span class="text-3xl">{{ (salesData?.report_summary?.dist_map?.hp || 0) + (salesData?.report_summary?.dist_map?.apple_lux || 0) }}</span>
                        </div>

                        <div class="h-px bg-emerald-200/70 dark:bg-surface-700/50 w-full mb-8"></div>

                        <!-- RINCIAN LAINNYA & STOK -->
                        <h3
                            class="text-sm font-black tracking-[0.1EM] text-emerald-950/50 dark:text-white/50 text-center mb-8 uppercase">
                            Rincian Unit & Stok</h3>

                        <div class="space-y-0 text-sm font-bold text-gray-800 dark:text-gray-300">
                            <div class="flex justify-between items-center py-4 border-b border-emerald-100 dark:border-surface-700/50">
                                <span class="capitalize">Tukar Unit</span>
                                <span class="text-emerald-950 font-black">{{ salesData?.report_summary?.activities?.tukar_unit || 0 }}</span>
                            </div>
                            <div class="flex justify-between items-center py-4 border-b border-emerald-100 dark:border-surface-700/50">
                                <span class="capitalize">Tukar Tambah</span>
                                <span class="text-emerald-950 font-black">{{ salesData?.report_summary?.activities?.tukar_tambah || 0 }}</span>
                            </div>
                            <div class="flex justify-between items-center py-4 border-b border-emerald-100 dark:border-surface-700/50">
                                <span class="capitalize">Downgrade</span>
                                <span class="text-emerald-950 font-black">{{ salesData?.report_summary?.activities?.downgrade || 0 }}</span>
                            </div>
                            <div class="flex justify-between items-center py-4 border-b border-emerald-100 dark:border-surface-700/50">
                                <span class="capitalize">Refund</span>
                                <span class="text-emerald-950 font-black">{{ salesData?.report_summary?.activities?.refund || 0 }}</span>
                            </div>
                            <div class="flex justify-between items-center py-4 border-b border-emerald-100 dark:border-surface-700/50">
                                <span class="capitalize">Angkat Barang</span>
                                <span class="text-emerald-950 font-black">{{ salesData?.report_summary?.activities?.angkat_barang || 0 }}</span>
                            </div>
                        </div>

                        <!-- STOCK SECTIONS (IMAGE STYLE) -->
                        <div class="mt-12 space-y-16 pb-20">
                            <!-- APPLE LUXURY (SINGLE SECTION) -->
                            <div :class="{ 'opacity-50': !appleLuxItems.length }">
                                <!-- Group Header -->
                                <div class="flex items-center gap-4 mb-8">
                                    <div class="h-px bg-emerald-200/60 flex-1"></div>
                                    <h4
                                        class="text-[11px] font-black tracking-[0.4em] text-emerald-800/80 dark:text-emerald-500 uppercase whitespace-nowrap">
                                        STOK APPLE LUX
                                    </h4>
                                    <div class="h-px bg-emerald-200/60 flex-1"></div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-4">
                                    <div v-for="(item, x) in appleLuxItems" :key="x"
                                        class="flex flex-col border-b border-emerald-200/30 pb-3 group">
                                        <div class="flex justify-between items-center mb-1">
                                            <div class="flex flex-col">
                                                <span
                                                    class="text-xs font-black text-gray-900 dark:text-white uppercase tracking-tight">
                                                    {{ item.name }}
                                                </span>
                                            </div>
                                            <div class="flex flex-col items-end">
                                                <span class="text-xs font-black text-emerald-800 dark:text-emerald-400">{{ item.qty }} UNIT</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- OTHER CATEGORIES (STOCK, SOLD, IN) -->
                            <div v-for="cat in categoryStocks" :key="cat.label"
                                v-show="Object.keys(cat.items).length || Object.keys(cat.remainingItems).length || Object.keys(cat.inItems).length">
                                <!-- Category Header -->
                                <div class="flex items-center gap-4 mb-8">
                                    <div class="h-px bg-emerald-200/60 flex-1"></div>
                                    <h4
                                        class="text-[11px] font-black tracking-[0.4em] text-emerald-800/80 dark:text-emerald-500 uppercase whitespace-nowrap">
                                        {{ cat.label }}
                                    </h4>
                                    <div class="h-px bg-emerald-200/60 flex-1"></div>
                                </div>

                                <div v-if="Object.keys(cat.items).length || Object.keys(cat.remainingItems).length || Object.keys(cat.inItems).length"
                                    class="space-y-8">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-12">
                                        <!-- Combined List (Unified) -->
                                        <div v-if="Object.keys(cat.inItems || {}).length" class="space-y-3">
                                            <div v-for="(qty, name) in cat.inItems" :key="'in-' + name"
                                                class="flex justify-between items-center border-b border-amber-200/30 pb-3">
                                                <span
                                                    class="text-xs font-black text-gray-900 dark:text-white uppercase tracking-tight line-clamp-1">
                                                    {{ name }}
                                                </span>
                                                <span class="text-xs font-black text-amber-700 dark:text-amber-400">{{
                                                    qty }}</span>
                                            </div>
                                        </div>

                                        <div v-if="Object.keys(cat.remainingItems || {}).length" class="space-y-3">
                                            <div v-for="(qty, name) in cat.remainingItems" :key="'rem-' + name"
                                                class="flex justify-between items-center border-b border-blue-200/30 pb-3">
                                                <span
                                                    class="text-xs font-black text-gray-900 dark:text-white uppercase tracking-tight line-clamp-1">
                                                    {{ name }}
                                                </span>
                                                <span class="text-xs font-black text-blue-700 dark:text-blue-400">{{ qty
                                                    }}</span>
                                            </div>
                                        </div>

                                        <div v-if="Object.keys(cat.items || {}).length" class="space-y-3">
                                            <div v-for="(qty, name) in cat.items" :key="'sold-' + name"
                                                class="flex justify-between items-center border-b border-emerald-200/30 pb-3">
                                                <span
                                                    class="text-xs font-black text-gray-900 dark:text-white uppercase tracking-tight line-clamp-1">
                                                    {{ name }}
                                                </span>
                                                <span
                                                    class="text-xs font-black text-emerald-700 dark:text-emerald-400">{{
                                                        qty }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="text-center">
                                    <span
                                        class="text-[10px] font-bold text-emerald-800/20 uppercase tracking-widest italic">Belum
                                        ada unit {{ cat.label }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>


        <!-- ==================== SUB-VIEWS (RANKINGS) ==================== -->
        <template v-else>
            <!-- Sub-view Header (Search & Sort) -->
            <div
                class="bg-white dark:!bg-surface-800 rounded-2xl border border-gray-100 dark:border-surface-700 p-4 space-y-4">
                <div class="flex flex-col lg:flex-row gap-4 justify-between items-start lg:items-center">
                    <div class="flex flex-wrap items-center gap-3">
                        <div
                            class="flex items-center bg-gray-50 dark:bg-surface-900 border border-gray-200 dark:border-surface-700 rounded-xl px-3 py-2">
                            <ListFilter class="text-text-secondary mr-2" :size="16" />
                            <select v-model="sortConfig.order"
                                class="bg-transparent text-xs font-bold text-text-primary focus:outline-none cursor-pointer appearance-none min-w-[120px]">
                                <option value="num-desc" class="dark:bg-surface-800">Angka Terbanyak</option>
                                <option value="num-asc" class="dark:bg-surface-800">Angka Terendah</option>
                                <option value="alpha-asc" class="dark:bg-surface-800">Abjad (A-Z)</option>
                                <option value="alpha-desc" class="dark:bg-surface-800">Abjad (Z-A)</option>
                            </select>
                        </div>

                        <!-- Filters (hidden on daily/revenue, brand, and distributor view) -->
                        <template v-if="!['revenue', 'brand', 'distributor'].includes(currentView)">
                            <select v-model="filters.distributor_id" @change="fetchData"
                                class="bg-gray-50 dark:bg-surface-900 border border-gray-200 dark:border-surface-700 rounded-xl px-3 py-2 text-xs font-bold text-text-primary dark:text-white focus:ring-1 focus:ring-primary-500 cursor-pointer min-w-[140px] appearance-none">
                                <option :value="null" class="dark:bg-surface-800">Semua Distributor</option>
                                <option v-for="d in distributors" :key="d.id" :value="d.id" class="dark:bg-surface-800">
                                    {{ d.name }}</option>
                            </select>
                            <select v-model="filters.product_type_id" @change="fetchData"
                                class="bg-gray-50 dark:bg-surface-900 border border-gray-200 dark:border-surface-700 rounded-xl px-3 py-2 text-xs font-bold text-text-primary dark:text-white focus:ring-1 focus:ring-primary-500 cursor-pointer min-w-[120px] appearance-none">
                                <option :value="null" class="dark:bg-surface-800">Semua Tipe</option>
                                <option v-for="p in productTypes" :key="p.id" :value="p.id" class="dark:bg-surface-800">
                                    {{ p.name }}</option>
                            </select>
                            <select v-model="filters.condition" @change="fetchData"
                                class="bg-gray-50 dark:bg-surface-900 border border-gray-200 dark:border-surface-700 rounded-xl px-3 py-2 text-xs font-bold text-text-primary dark:text-white focus:ring-1 focus:ring-primary-500 cursor-pointer appearance-none">
                                <option :value="null" class="dark:bg-surface-800">Semua Kondisi</option>
                                <option value="new" class="dark:bg-surface-800">Baru</option>
                                <option value="ex_ibox" class="dark:bg-surface-800">Ex iBox</option>
                                <option value="second" class="dark:bg-surface-800">Second</option>
                            </select>
                            <select v-model="filters.capacity" @change="fetchData"
                                class="bg-gray-50 dark:bg-surface-900 border border-gray-200 dark:border-surface-700 rounded-xl px-3 py-2 text-xs font-bold text-text-primary dark:text-white focus:ring-1 focus:ring-primary-500 cursor-pointer min-w-[100px] appearance-none">
                                <option :value="null" class="dark:bg-surface-800">Semua GB</option>
                                <option v-for="gb in capacities" :key="gb" :value="gb" class="dark:bg-surface-800">{{ gb
                                }}GB</option>
                            </select>
                        </template>
                    </div>

                    <!-- Search -->
                    <div class="relative w-full sm:w-80 group">
                        <Search
                            class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary group-focus-within:text-primary-400 transition-colors"
                            :size="18" />
                        <input v-model="searchQuery" type="text" placeholder="Cari..."
                            class="w-full bg-gray-50 dark:bg-surface-900 border border-gray-200 dark:border-surface-700 rounded-xl py-2 pl-10 pr-4 text-sm font-medium text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all" />
                    </div>
                </div>

                <!-- Breakdown Toggles (Brand View Only) -->
                <div v-if="currentView === 'brand'"
                    class="flex flex-wrap items-center gap-3 pt-3 border-t border-gray-100 dark:border-surface-700">
                    <button @click="showBrandDistributor = !showBrandDistributor"
                        class="flex items-center gap-2 px-3 py-1.5 rounded-xl text-xs font-bold transition-all border"
                        :class="showBrandDistributor ? 'bg-primary-500/10 border-primary-500/30 text-primary-500' : 'bg-gray-50 dark:bg-surface-900 border-gray-200 dark:border-surface-700 text-text-secondary'">
                        <component :is="showBrandDistributor ? ToggleRight : ToggleLeft" :size="16" />
                        Tampilkan Distributor
                    </button>
                    <button @click="showBrandType = !showBrandType"
                        class="flex items-center gap-2 px-3 py-1.5 rounded-xl text-xs font-bold transition-all border"
                        :class="showBrandType ? 'bg-primary-500/10 border-primary-500/30 text-primary-500' : 'bg-gray-50 dark:bg-surface-900 border-gray-200 dark:border-surface-700 text-text-secondary'">
                        <component :is="showBrandType ? ToggleRight : ToggleLeft" :size="16" />
                        Tampilkan Tipe
                    </button>
                    <button @click="showBrandCondition = !showBrandCondition"
                        class="flex items-center gap-2 px-3 py-1.5 rounded-xl text-xs font-bold transition-all border"
                        :class="showBrandCondition ? 'bg-primary-500/10 border-primary-500/30 text-primary-500' : 'bg-gray-50 dark:bg-surface-900 border-gray-200 dark:border-surface-700 text-text-secondary'">
                        <component :is="showBrandCondition ? ToggleRight : ToggleLeft" :size="16" />
                        Tampilkan Kondisi
                    </button>
                    <button @click="showBrandGb = !showBrandGb"
                        class="flex items-center gap-2 px-3 py-1.5 rounded-xl text-xs font-bold transition-all border"
                        :class="showBrandGb ? 'bg-primary-500/10 border-primary-500/30 text-primary-500' : 'bg-gray-50 dark:bg-surface-900 border-gray-200 dark:border-surface-700 text-text-secondary'">
                        <component :is="showBrandGb ? ToggleRight : ToggleLeft" :size="16" />
                        Tampilkan GB
                    </button>
                </div>

                <!-- Breakdown Toggles (Distributor View Only) -->
                <div v-if="currentView === 'distributor'"
                    class="flex flex-wrap items-center gap-3 pt-3 border-t border-gray-100 dark:border-surface-700">
                    <button @click="showBrandDistributor = !showBrandDistributor"
                        class="flex items-center gap-2 px-3 py-1.5 rounded-xl text-xs font-bold transition-all border"
                        :class="showBrandDistributor ? 'bg-primary-500/10 border-primary-500/30 text-primary-500' : 'bg-gray-50 dark:bg-surface-900 border-gray-200 dark:border-surface-700 text-text-secondary'">
                        <component :is="showBrandDistributor ? ToggleRight : ToggleLeft" :size="16" />
                        Tampilkan Brand
                    </button>
                    <button @click="showBrandType = !showBrandType"
                        class="flex items-center gap-2 px-3 py-1.5 rounded-xl text-xs font-bold transition-all border"
                        :class="showBrandType ? 'bg-primary-500/10 border-primary-500/30 text-primary-500' : 'bg-gray-50 dark:bg-surface-900 border-gray-200 dark:border-surface-700 text-text-secondary'">
                        <component :is="showBrandType ? ToggleRight : ToggleLeft" :size="16" />
                        Tampilkan Tipe
                    </button>
                    <button @click="showBrandCondition = !showBrandCondition"
                        class="flex items-center gap-2 px-3 py-1.5 rounded-xl text-xs font-bold transition-all border"
                        :class="showBrandCondition ? 'bg-primary-500/10 border-primary-500/30 text-primary-500' : 'bg-gray-50 dark:bg-surface-900 border-gray-200 dark:border-surface-700 text-text-secondary'">
                        <component :is="showBrandCondition ? ToggleRight : ToggleLeft" :size="16" />
                        Tampilkan Kondisi
                    </button>
                    <button @click="showBrandGb = !showBrandGb"
                        class="flex items-center gap-2 px-3 py-1.5 rounded-xl text-xs font-bold transition-all border"
                        :class="showBrandGb ? 'bg-primary-500/10 border-primary-500/30 text-primary-500' : 'bg-gray-50 dark:bg-surface-900 border-gray-200 dark:border-surface-700 text-text-secondary'">
                        <component :is="showBrandGb ? ToggleRight : ToggleLeft" :size="16" />
                        Tampilkan GB
                    </button>
                </div>
            </div>

            <!-- Content Area -->
            <div
                class="bg-white dark:!bg-surface-800 rounded-2xl border border-gray-100 dark:border-surface-700 overflow-hidden shadow-sm">
                <div v-if="loading" class="p-12 flex justify-center items-center">
                    <Loader2 class="animate-spin text-primary-500" :size="32" />
                </div>

                <div v-else-if="sortedData.length === 0" class="p-12 text-center text-text-secondary">
                    <Search :size="48" class="mx-auto mb-4 opacity-20" />
                    <p class="font-medium">Tidak ada data ditemukan</p>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead
                            class="text-xs font-bold text-text-secondary uppercase bg-gray-50/50 dark:bg-surface-900/50 border-b border-gray-100 dark:border-surface-700">
                            <tr>
                                <th class="px-6 py-4 w-16">Rank</th>
                                <template v-if="currentView === 'revenue'">
                                    <th class="px-6 py-4">Tanggal</th>
                                    <th class="px-6 py-4 text-center">IPhone</th>
                                    <th class="px-6 py-4 text-center">Android</th>
                                    <th class="px-6 py-4 text-center">Non-HP</th>
                                    <th class="px-6 py-4 text-center">Total Unit</th>
                                    <th class="px-6 py-4 text-right">Total Omset</th>
                                </template>
                                <template v-else-if="currentView === 'brand'">
                                    <th class="px-6 py-4">Brand</th>
                                    <th v-if="showBrandCondition || showBrandGb" class="px-6 py-4">Detail</th>
                                    <th class="px-6 py-4 text-center">Unit Terjual</th>
                                </template>
                                <template v-else-if="['sales', 'activity'].includes(currentView)">
                                    <th class="px-6 py-4">Sales (Inventory)</th>
                                    <template v-if="currentView === 'sales'">
                                        <th class="px-6 py-4 text-center">IPhone</th>
                                        <th class="px-6 py-4 text-center">Android</th>
                                        <th class="px-6 py-4 text-center">Non-HP</th>
                                    </template>
                                    <th v-if="currentView === 'sales'" class="px-6 py-4 text-center">Total Penjualan</th>
                                    <th v-if="currentView === 'activity'" class="px-6 py-4 text-center">
                                        Tukar/Angkat/Downgrade</th>
                                    <th v-if="currentView === 'activity'" class="px-6 py-4 text-center">Refund</th>
                                    <th class="px-6 py-4 text-right">Grand Total</th>
                                </template>
                                <template v-else-if="currentView === 'type'">
                                    <th class="px-6 py-4">Brand</th>
                                    <th class="px-6 py-4">Tipe Produk</th>
                                    <th class="px-6 py-4 text-center">Unit Terjual</th>
                                </template>
                                <template v-else-if="currentView === 'condition'">
                                    <th class="px-6 py-4">Kondisi</th>
                                    <th class="px-6 py-4 text-center">Unit Terjual</th>
                                </template>
                                <template v-else-if="currentView === 'distributor'">
                                    <th class="px-6 py-4">Distributor</th>
                                    <th class="px-6 py-4 text-center">Unit Terjual</th>
                                </template>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-surface-700 text-sm">
                            <template v-if="currentView === 'brand'">
                                <template v-for="(row, idx) in filteredBrandHierarchy" :key="row.brand">
                                    <tr class="hover:bg-gray-50/50 dark:hover:bg-surface-700/30 transition-colors"
                                        :class="{ 'bg-blue-50/20 dark:bg-blue-900/10': showBrandType || showBrandCondition || showBrandGb }">
                                        <td class="px-6 py-4">
                                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-black shadow-sm"
                                                :class="getRankBadgeClass(idx)">
                                                {{ idx + 1 }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 font-bold text-text-primary">{{ row.brand }}</td>
                                        <td v-if="showBrandCondition || showBrandGb"
                                            class="px-6 py-4 text-text-secondary italic text-xs">—</td>
                                        <td class="px-6 py-4 text-center font-black text-purple-500">{{ row.qty }}</td>
                                    </tr>
                                    <!-- Brand Breakdown: Distributor -->
                                    <template v-if="showBrandDistributor" v-for="d in row.tree" :key="d.label">
                                        <tr class="bg-indigo-50/20 dark:bg-indigo-900/10">
                                            <td class="px-6 py-2"></td>
                                            <td
                                                class="px-6 py-2 text-xs font-bold text-indigo-600 dark:text-indigo-400 pl-10">
                                                Distributor: {{ d.label }}</td>
                                            <td v-if="showBrandCondition || showBrandGb" class="px-6 py-2"></td>
                                            <td class="px-6 py-2 text-center text-xs font-black text-indigo-500">{{
                                                d.qty }}</td>
                                            <td class="px-6 py-2"></td>
                                        </tr>
                                        <!-- Nested Type under Distributor -->
                                        <template v-if="showBrandType" v-for="t in d.types" :key="t.label">
                                            <tr class="bg-gray-50/30 dark:bg-surface-900/30">
                                                <td class="px-6 py-2"></td>
                                                <td class="px-6 py-2 text-xs font-bold text-text-primary pl-16">— {{
                                                    t.label }}</td>
                                                <td v-if="showBrandCondition || showBrandGb" class="px-6 py-2"></td>
                                                <td class="px-6 py-2 text-center text-xs font-bold text-emerald-500">{{
                                                    t.qty }}</td>
                                                <td class="px-6 py-2"></td>
                                            </tr>
                                            <!-- Nested Cond under Type -->
                                            <template v-if="showBrandCondition || showBrandGb"
                                                v-for="(c, cIdx) in t.conditions"
                                                :key="idx + '-' + d.label + '-' + t.label + '-' + cIdx">
                                                <tr
                                                    class="bg-white/50 dark:bg-surface-800/30 border-l-2 border-primary-500/20">
                                                    <td class="px-6 py-1.5"></td>
                                                    <td class="px-6 py-1.5"></td>
                                                    <td class="px-6 py-1.5 pl-10">
                                                        <div class="flex items-center gap-2">
                                                            <span v-if="showBrandCondition && c.condition !== '-'"
                                                                class="px-2 py-0.5 rounded text-[10px] font-bold border"
                                                                :class="getConditionClass(c.condition)">
                                                                {{ formatCondition(c.condition) }}
                                                            </span>
                                                            <span v-if="showBrandGb && c.capacity !== '-'"
                                                                class="px-2 py-0.5 rounded text-[10px] font-bold bg-gray-100 dark:bg-surface-900 text-text-secondary border border-gray-200 dark:border-surface-700">
                                                                {{ c.capacity }}GB
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td
                                                        class="px-6 py-1.5 text-center text-[11px] font-medium text-text-secondary">
                                                        {{ c.qty }}</td>
                                                    <td class="px-6 py-1.5"></td>
                                                </tr>
                                            </template>
                                        </template>
                                    </template>

                                    <!-- If Distributor is Hidden but Type is Shown -->
                                    <template v-if="!showBrandDistributor && showBrandType">
                                        <template v-for="d in row.tree">
                                            <template v-for="t in d.types" :key="t.label">
                                                <tr class="bg-gray-50/30 dark:bg-surface-900/30">
                                                    <td class="px-6 py-2"></td>
                                                    <td class="px-6 py-2 text-xs font-bold text-text-primary pl-10">— {{
                                                        t.label }}</td>
                                                    <td v-if="showBrandCondition || showBrandGb" class="px-6 py-2"></td>
                                                    <td
                                                        class="px-6 py-2 text-center text-xs font-bold text-emerald-500">
                                                        {{ t.qty }}</td>
                                                    <td class="px-6 py-2"></td>
                                                </tr>
                                                <template v-if="showBrandCondition || showBrandGb"
                                                    v-for="(c, cIdx) in t.conditions"
                                                    :key="idx + '-' + t.label + '-' + cIdx">
                                                    <tr
                                                        class="bg-white/50 dark:bg-surface-800/30 border-l-2 border-primary-500/20">
                                                        <td class="px-6 py-1.5"></td>
                                                        <td class="px-6 py-1.5"></td>
                                                        <td class="px-6 py-1.5 pl-10">
                                                            <div class="flex items-center gap-2">
                                                                <span v-if="showBrandCondition && c.condition !== '-'"
                                                                    class="px-2 py-0.5 rounded text-[10px] font-bold border"
                                                                    :class="getConditionClass(c.condition)">
                                                                    {{ formatCondition(c.condition) }}
                                                                </span>
                                                                <span v-if="showBrandGb && c.capacity !== '-'"
                                                                    class="px-2 py-0.5 rounded text-[10px] font-bold bg-gray-100 dark:bg-surface-900 text-text-secondary border border-gray-200 dark:border-surface-700">
                                                                    {{ c.capacity }}GB
                                                                </span>
                                                            </div>
                                                        </td>
                                                        <td
                                                            class="px-6 py-1.5 text-center text-[11px] font-medium text-text-secondary">
                                                            {{ c.qty }}</td>
                                                        <td class="px-6 py-1.5"></td>
                                                    </tr>
                                                </template>
                                            </template>
                                        </template>
                                    </template>

                                    <!-- If both Dist & Type are hidden but Detail (Cond/GB) is shown -->
                                    <template
                                        v-if="!showBrandDistributor && !showBrandType && (showBrandCondition || showBrandGb)">
                                        <template v-for="d in row.tree">
                                            <template v-for="t in d.types">
                                                <template v-for="(c, cIdx) in t.conditions"
                                                    :key="row.brand + '-' + cIdx">
                                                    <tr class="bg-white/30 dark:bg-surface-800/20">
                                                        <td class="px-6 py-1.5"></td>
                                                        <td class="px-6 py-1.5"></td>
                                                        <td class="px-6 py-1.5 pl-10">
                                                            <div class="flex items-center gap-2">
                                                                <span v-if="showBrandCondition && c.condition !== '-'"
                                                                    class="px-2 py-0.5 rounded text-[10px] font-bold border"
                                                                    :class="getConditionClass(c.condition)">
                                                                    {{ formatCondition(c.condition) }}
                                                                </span>
                                                                <span v-if="showBrandGb && c.capacity !== '-'"
                                                                    class="px-2 py-0.5 rounded text-[10px] font-bold bg-gray-100 dark:bg-surface-900 text-text-secondary border border-gray-200 dark:border-surface-700">
                                                                    {{ c.capacity }}GB
                                                                </span>
                                                            </div>
                                                        </td>
                                                        <td
                                                            class="px-6 py-1.5 text-center text-[11px] font-medium text-text-secondary">
                                                            {{ c.qty }}</td>
                                                        <td class="px-6 py-1.5"></td>
                                                    </tr>
                                                </template>
                                            </template>
                                        </template>
                                    </template>
                                </template>
                            </template>
                            <template v-else-if="currentView === 'distributor'">
                                <template v-for="(row, idx) in distributorHierarchy" :key="row.distributor">
                                    <tr class="hover:bg-gray-50 dark:hover:bg-surface-700/50 transition-colors group">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center justify-center w-8 h-8 rounded-lg font-black text-sm"
                                                :class="getRankBadgeClass(idx)">
                                                {{ idx + 1 }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col">
                                                <span
                                                    class="font-black text-sm text-text-primary dark:text-white uppercase tracking-tight">{{
                                                        row.distributor }}</span>
                                            </div>
                                        </td>
                                        <td v-if="showBrandCondition || showBrandGb"
                                            class="px-6 py-4 text-text-secondary italic text-xs">—</td>
                                        <td class="px-6 py-4 text-center font-black text-indigo-500 uppercase">{{
                                            row.qty }}</td>
                                        <td class="px-6 py-4"></td>
                                    </tr>
                                    <!-- Nested Brands under Distributor -->
                                    <template v-if="showBrandType" v-for="b in row.tree" :key="b.label">
                                        <tr class="bg-indigo-50/20 dark:bg-indigo-900/10">
                                            <td class="px-6 py-2"></td>
                                            <td
                                                class="px-6 py-2 text-xs font-bold text-indigo-600 dark:text-indigo-400 pl-10">
                                                — {{ b.label }}</td>
                                            <td v-if="showBrandCondition || showBrandGb" class="px-6 py-2"></td>
                                            <td class="px-6 py-2 text-center text-xs font-black text-indigo-500">{{
                                                b.qty }}</td>
                                            <td class="px-6 py-2"></td>
                                        </tr>
                                        <template v-for="t in b.types">
                                            <tr class="bg-gray-50/30 dark:bg-surface-900/30">
                                                <td class="px-6 py-2"></td>
                                                <td class="px-6 py-2 text-xs font-bold text-text-primary pl-16">{{
                                                    t.label }}</td>
                                                <td v-if="showBrandCondition || showBrandGb" class="px-6 py-2"></td>
                                                <td class="px-6 py-2 text-center text-xs font-bold text-emerald-500">{{
                                                    t.qty }}</td>
                                                <td class="px-6 py-2"></td>
                                            </tr>
                                            <template v-if="showBrandCondition || showBrandGb"
                                                v-for="(c, cIdx) in t.conditions"
                                                :key="row.distributor + '-' + b.label + '-' + t.label + '-' + cIdx">
                                                <tr
                                                    class="bg-white/50 dark:bg-surface-800/30 border-l-2 border-primary-500/20">
                                                    <td class="px-6 py-1.5"></td>
                                                    <td class="px-6 py-1.5"></td>
                                                    <td class="px-6 py-1.5 pl-10">
                                                        <div class="flex items-center gap-2">
                                                            <span v-if="showBrandCondition && c.condition !== '-'"
                                                                class="px-2 py-0.5 rounded text-[10px] font-bold border"
                                                                :class="getConditionClass(c.condition)">
                                                                {{ formatCondition(c.condition) }}
                                                            </span>
                                                            <span v-if="showBrandGb && c.capacity !== '-'"
                                                                class="px-2 py-0.5 rounded text-[10px] font-bold bg-gray-100 dark:bg-surface-900 text-text-secondary border border-gray-200 dark:border-surface-700">
                                                                {{ c.capacity }}GB
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td
                                                        class="px-6 py-1.5 text-center text-[11px] font-medium text-text-secondary">
                                                        {{ c.qty }}</td>
                                                    <td class="px-6 py-1.5"></td>
                                                </tr>
                                            </template>
                                        </template>
                                    </template>
                                    <!-- If Brand is hidden but Detail is shown -->
                                    <template v-if="!showBrandType && (showBrandCondition || showBrandGb)">
                                        <template v-for="b in row.tree">
                                            <template v-for="t in b.types">
                                                <template v-for="(c, cIdx) in t.conditions">
                                                    <tr class="bg-white/30 dark:bg-surface-800/20">
                                                        <td class="px-6 py-1.5"></td>
                                                        <td class="px-6 py-1.5"></td>
                                                        <td class="px-6 py-1.5 pl-10">
                                                            <div class="flex items-center gap-2">
                                                                <span v-if="showBrandCondition && c.condition !== '-'"
                                                                    class="px-2 py-0.5 rounded text-[10px] font-bold border"
                                                                    :class="getConditionClass(c.condition)">
                                                                    {{ formatCondition(c.condition) }}
                                                                </span>
                                                                <span v-if="showBrandGb && c.capacity !== '-'"
                                                                    class="px-2 py-0.5 rounded text-[10px] font-bold bg-gray-100 dark:bg-surface-900 text-text-secondary border border-gray-200 dark:border-surface-700">
                                                                    {{ c.capacity }}GB
                                                                </span>
                                                            </div>
                                                        </td>
                                                        <td
                                                            class="px-6 py-1.5 text-center text-[11px] font-medium text-text-secondary">
                                                            {{ c.qty }}</td>
                                                        <td class="px-6 py-1.5"></td>
                                                    </tr>
                                                </template>
                                            </template>
                                        </template>
                                    </template>
                                </template>
                            </template>
                            <template v-else>
                                <tr v-for="(item, idx) in sortedData" :key="idx"
                                    class="hover:bg-gray-50 dark:hover:bg-surface-700/30 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-black shadow-sm"
                                            :class="getRankBadgeClass(idx)">
                                            {{ idx + 1 }}
                                        </div>
                                    </td>

                                    <!-- Daily History Data -->
                                    <template v-if="currentView === 'revenue'">
                                        <td class="px-6 py-4">
                                            <span class="font-bold text-text-primary">{{
                                                formatDateString(item.reporting_date) }}</span>
                                        </td>
                                        <td class="px-6 py-4 text-center text-blue-500 font-bold">{{ item.iphone_units
                                            || 0 }}</td>
                                        <td class="px-6 py-4 text-center text-emerald-500 font-bold">{{
                                            item.android_units || 0 }}</td>
                                        <td class="px-6 py-4 text-center text-gray-500 font-bold">{{ item.non_hp_units
                                            || 0 }}</td>
                                        <td class="px-6 py-4 text-center font-black text-amber-500">{{ item.total_units || 0 }}</td>
                                        <td
                                            class="px-6 py-4 text-right font-black text-text-primary font-mono whitespace-nowrap">
                                            {{ formatCurrency(item.total_omset) }}
                                        </td>
                                    </template>

                                    <!-- CS Related Data -->
                                    <template v-else-if="['sales', 'activity'].includes(currentView)">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <img :src="item.photo
                                                    ? (item.photo.startsWith('http') ? item.photo : `${storageBaseUrl}/storage/${item.photo}`)
                                                    : `https://ui-avatars.com/api/?name=${encodeURIComponent(item.cs_name)}&background=10b981&color=fff&size=48`"
                                                    class="w-10 h-10 rounded-xl object-cover border-2 border-surface-200 dark:border-surface-600 shadow-sm"
                                                    @error="(e) => e.target.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(item.cs_name)}&background=10b981&color=fff&size=48`" />
                                                <span class="font-bold text-text-primary">{{ item.cs_name }}</span>
                                            </div>
                                        </td>
                                        <template v-if="currentView === 'sales'">
                                            <td class="px-6 py-4 text-center text-blue-500 font-bold">{{
                                                item.iphone_units || 0 }}</td>
                                            <td class="px-6 py-4 text-center text-emerald-500 font-bold">{{
                                                item.android_units || 0 }}</td>
                                            <td class="px-6 py-4 text-center text-gray-500 font-bold">{{
                                                item.non_hp_units || 0 }}</td>
                                        </template>
                                        <td v-if="currentView === 'sales'" class="px-6 py-4 text-center font-black text-primary-500">{{
                                            item.total_sales }}</td>
                                        <td v-if="currentView === 'activity'"
                                            class="px-6 py-4 text-center font-bold text-amber-500">{{
                                                item.total_angkat_barang || 0 }}</td>
                                        <td v-if="currentView === 'activity'"
                                            class="px-6 py-4 text-center font-bold text-red-500">{{
                                                item.total_refund || 0 }}</td>
                                        <td
                                            class="px-6 py-4 text-right font-black text-text-primary font-mono whitespace-nowrap">
                                            {{ formatCurrency(item.grand_total) }}
                                        </td>
                                    </template>

                                    <!-- Type Data -->
                                    <template v-else-if="currentView === 'type'">
                                        <td class="px-6 py-4 text-text-secondary">{{ item.brand }}</td>
                                        <td class="px-6 py-4 font-bold text-text-primary">{{ item.name }}</td>
                                        <td class="px-6 py-4 text-center font-black text-emerald-500">{{ item.qty }}
                                        </td>
                                    </template>

                                    <!-- Condition Data -->
                                    <template v-else-if="currentView === 'condition'">
                                        <td class="px-6 py-4">
                                            <span class="px-3 py-1 rounded-lg text-xs font-bold border"
                                                :class="getConditionClass(item.condition)">
                                                {{ formatCondition(item.condition) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-center font-black text-orange-500">{{ item.qty }}</td>
                                    </template>

                                    <template v-else-if="currentView === 'distributor'">
                                        <td class="px-6 py-4 font-bold text-text-primary">{{ item.distributor ||
                                            'Tanpa Distributor' }}</td>
                                        <td class="px-6 py-4 text-center font-black text-indigo-500">{{ item.qty }}</td>
                                    </template>
                                </tr>
                            </template>
                        </tbody>
                        <!-- Table Footer (Totals) -->
                        <tfoot
                            class="bg-gray-50 dark:bg-surface-900/50 border-t-2 border-gray-100 dark:border-surface-700">
                            <tr class="font-black text-xs uppercase text-text-primary dark:text-white">
                                <td class="px-6 py-4"></td>
                                <td class="px-6 py-4 text-left">TOTAL</td>

                                <template v-if="currentView === 'revenue'">
                                    <td class="px-6 py-4 text-center">{{ totals.iphone }}</td>
                                    <td class="px-6 py-4 text-center">{{ totals.android }}</td>
                                    <td class="px-6 py-4 text-center">{{ totals.nonHp }}</td>
                                    <td class="px-6 py-4 text-center text-amber-500">{{ totals.units }}</td>
                                    <td class="px-6 py-4 text-right text-primary-500 font-mono">{{
                                        formatCurrency(totals.revenue) }}</td>
                                </template>

                                <template v-else-if="currentView === 'brand' || currentView === 'distributor'">
                                    <td v-if="showBrandCondition || showBrandGb" class="px-6 py-4"></td>
                                    <td class="px-6 py-4 text-center text-primary-500 text-base">{{ totals.units }}</td>
                                    <td class="px-6 py-4"></td>
                                </template>

                                <template v-else-if="currentView === 'sales'">
                                    <td class="px-6 py-4 text-center">{{ totals.iphone }}</td>
                                    <td class="px-6 py-4 text-center">{{ totals.android }}</td>
                                    <td class="px-6 py-4 text-center">{{ totals.nonHp }}</td>
                                    <td class="px-6 py-4 text-center text-primary-500">{{ totals.units }}</td>
                                    <td class="px-6 py-4 text-right font-mono">{{ formatCurrency(totals.revenue) }}</td>
                                </template>

                                <template v-else-if="currentView === 'activity'">
                                    <td v-if="currentView === 'sales'" class="px-6 py-4 text-center text-primary-500">{{ totals.units }}</td>
                                    <td class="px-6 py-4 text-center text-amber-500">{{ totals.activity }}</td>
                                    <td class="px-6 py-4 text-center text-red-500">{{ totals.refund }}</td>
                                    <td class="px-6 py-4 text-right font-mono">{{ formatCurrency(totals.revenue) }}</td>
                                </template>

                                <template v-else-if="currentView === 'type' || currentView === 'condition'">
                                    <td class="px-6 py-4 text-center text-primary-500 text-base">{{ totals.units }}</td>
                                </template>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </template>

    </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import {
    Trophy, ArrowLeft, Calendar, Loader2, Search, RefreshCw,
    Smartphone, Layers, Tag, RotateCcw, ChevronRight, Users,
    Truck, ListFilter, MapPin, Globe, ToggleLeft, ToggleRight,
    FileText, X, Copy, Check
} from 'lucide-vue-next'
import axios from '../../api/axios'
import { useAuthStore } from '../../store/auth'

const authStore = useAuthStore()
const storageBaseUrl = computed(() => authStore.storageBaseUrl)

const branches = ref([])
const onlineShops = ref([])
const locationType = ref('branch')
const distributors = ref([])
const productTypes = ref([])
const capacities = [16, 32, 64, 128, 256, 512, 1024]

const loading = ref(false)
const currentView = ref('menu')
const searchQuery = ref('')
const selectedPeriod = ref('daily')

// Breakdown Toggles (Stock Opname style)
const showBrandDistributor = ref(false)
const showBrandType = ref(false)
const showBrandCondition = ref(false)
const showBrandGb = ref(false)

const showReportModal = ref(false)
const reportCopied = ref(false)

const sortConfig = ref({
    order: 'num-desc' // 'num-desc', 'num-asc', 'alpha-asc', 'alpha-desc'
})

const viewLabels = {
    'menu': 'Pilih kategori peringkat yang ingin Anda lihat',
    'revenue': 'Peringkat Penjualan per Tanggal',
    'sales': 'Peringkat Penjualan per Sales',
    'brand': 'Penjualan per Merek Produk',
    'type': 'Penjualan per Tipe/Model',
    'condition': 'Penjualan per Kondisi',
    'activity': 'Peringkat Berdasarkan Aktivitas Unit (Tukar/Refund/Angkat)',
    'distributor': 'Peringkat Penjualan per Distributor'
}

const months = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

const getLogicalDate = () => {
    const d = new Date();
    if (d.getHours() < 5) {
        d.setDate(d.getDate() - 1);
    }
    return d;
};

const logicalToday = getLogicalDate();
const currentYear = logicalToday.getFullYear();
const years = Array.from({ length: 5 }, (_, i) => currentYear - 2 + i);

const selectedMonth = ref(logicalToday.getMonth() + 1);
const selectedYear = ref(currentYear);

const isRestricted = computed(() => {
    const role = (authStore.userRole || '').toLowerCase();
    return !['audit', 'super_admin', 'admin_produk', 'leader', 'owner', 'analist'].some(r => role.includes(r));
});

const availableMonths = computed(() => {
    if (!isRestricted.value) {
        return months.map((m, i) => ({ name: m, value: i + 1 }));
    }

    const d = getLogicalDate();
    const currentMonth = d.getMonth() + 1; // 1-indexed

    // For restricted, show current month and last month
    // Handle the case where last month was in the previous year
    // Since year is locked to currentYear, showing last month's name but the same year might show future data if it's Dec in Jan.
    // However, the requirement is "this month and last month".
    const lastDate = new Date(d.getFullYear(), d.getMonth() - 1, 1);
    const lastMonth = lastDate.getMonth() + 1;

    return months.map((m, i) => ({ name: m, value: i + 1 }))
        .filter(m => m.value === currentMonth || m.value === lastMonth);
});

const availableYears = computed(() => {
    if (!isRestricted.value) return years;
    return [currentYear];
});

const todayDate = computed(() => getTodayLocal());
const minDate = computed(() => {
    if (!isRestricted.value) return null;
    const d = getLogicalDate();
    d.setDate(d.getDate() - 7); // Allow past 7 days
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
});


const salesData = ref({
    daily_sales: { data: [] },
    brand_sales: [],
    type_sales: [],
    condition_sales: [],
    distributor_sales: [],
    cs_sales: [],
    daily_history: []
})

const getTodayLocal = () => {
    const d = getLogicalDate();
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

const filters = ref({
    start_date: getTodayLocal(),
    end_date: getTodayLocal(),
    branch_id: null,
    online_shop_id: null,
    distributor_id: null,
    condition: null,
    capacity: null,
    product_type_id: null
})

const formattedDateDisplay = computed(() => {
    if (selectedPeriod.value === 'monthly') {
        return `${months[selectedMonth.value - 1]} ${selectedYear.value}`;
    }
    if (!filters.value.start_date) return 'Pilih Tanggal';
    const date = new Date(filters.value.start_date);
    return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
})

// Main Data Processor
const sortedData = computed(() => {
    let base = []
    let numKey = ''
    let alphaKey = ''

    if (currentView.value === 'revenue') {
        base = [...(salesData.value.daily_history || [])]
        numKey = 'total_omset'
        alphaKey = 'reporting_date'
    } else if (currentView.value === 'sales' || currentView.value === 'activity') {
        base = [...(salesData.value.cs_sales || [])]
        numKey = currentView.value === 'sales' ? 'total_sales' : 'total_angkat_barang';
        // If activity view, we might want to prioritize (angkat + refund)
        if (currentView.value === 'activity') {
            base.forEach(item => {
                item._sortValue = (item.total_angkat_barang || 0) + (item.total_refund || 0);
            });
            numKey = '_sortValue';
        }
        alphaKey = 'cs_name'
    } else if (currentView.value === 'brand') {
        base = [...(salesData.value.brand_sales || [])]
        numKey = 'qty'
        alphaKey = 'brand'
    } else if (currentView.value === 'type') {
        base = [...(salesData.value.type_sales || [])]
        numKey = 'qty'
        alphaKey = 'name'
    } else if (currentView.value === 'condition') {
        base = [...(salesData.value.condition_sales || [])]
        numKey = 'qty'
        alphaKey = 'condition'
    } else if (currentView.value === 'distributor') {
        base = [...(salesData.value.distributor_sales || [])]
        numKey = 'qty'
        alphaKey = 'distributor'
    }

    // Filter by Query
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase()
        base = base.filter(item => {
            if (alphaKey && item[alphaKey]) return item[alphaKey].toLowerCase().includes(q)
            if (item.name) return item.name.toLowerCase().includes(q)
            if (item.cs_name) return item.cs_name.toLowerCase().includes(q)
            return false
        })
    }

    // Sort
    const { order } = sortConfig.value
    base.sort((a, b) => {
        // Special case for daily revenue: default to newest date first
        if (currentView.value === 'revenue' && order === 'num-desc') {
            return (b.reporting_date || '').localeCompare(a.reporting_date || '');
        }

        if (order === 'num-desc') return (b[numKey] || 0) - (a[numKey] || 0)
        if (order === 'num-asc') return (a[numKey] || 0) - (b[numKey] || 0)
        if (order === 'alpha-asc') return (a[alphaKey] || '').localeCompare(b[alphaKey] || '')
        if (order === 'alpha-desc') return (b[alphaKey] || '').localeCompare(a[alphaKey] || '')
        return 0
    })

    return base
})

const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(value || 0)
}

const formatDateString = (dateStr) => {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
}

const getRankBadgeClass = (idx) => {
    if (idx === 0) return 'bg-amber-400 text-amber-950 border-2 border-amber-300'
    if (idx === 1) return 'bg-slate-300 text-slate-800 border-2 border-slate-200 shadow-inner'
    if (idx === 2) return 'bg-orange-800 text-orange-50 dark:bg-orange-700 border-2 border-orange-600'
    return 'bg-gray-100 dark:bg-surface-700 text-text-secondary border border-gray-200 dark:border-surface-600'
}

const getConditionClass = (cond) => {
    const maps = {
        'new': 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20',
        'ex_ibox': 'bg-blue-500/10 text-blue-500 border-blue-500/20',
        'second': 'bg-amber-500/10 text-amber-500 border-amber-500/20'
    }
    return maps[cond] || 'bg-gray-500/10 text-gray-500 border-gray-500/20'
}

const formatCondition = (cond) => {
    const maps = { 'new': 'Baru', 'ex_ibox': 'Ex iBox', 'second': 'Second' }
    return maps[cond] || cond
}

const navigateTo = (view) => {
    currentView.value = view
    searchQuery.value = ''
    resetBreakdowns()
    // Reset product filters when entering revenue view (filters are hidden there)
    if (view === 'revenue') {
        filters.value.distributor_id = null;
        filters.value.product_type_id = null;
        filters.value.condition = null;
        filters.value.capacity = null;
        fetchData();
    }
    // Set default sort based on view
    if (view === 'revenue' || view === 'sales') sortConfig.value.order = 'num-desc'
    else sortConfig.value.order = 'num-desc'
}

const resetBreakdowns = () => {
    showBrandDistributor.value = false
    showBrandType.value = false
    showBrandCondition.value = false
    showBrandGb.value = false
}

const goBack = () => {
    currentView.value = 'menu'
    searchQuery.value = ''
    resetBreakdowns()
}

// Hierarchical Brand Report
const brandHierarchy = computed(() => {
    const raw = salesData.value.brand_sales || []
    const map = new Map()

    raw.forEach(item => {
        const brand = item.brand
        if (!map.has(brand)) {
            map.set(brand, { brand, qty: 0, tree: new Map() })
        }
        const entry = map.get(brand)
        entry.qty += (item.qty || 0)

        const dist = item.distributor || '-'
        if (!entry.tree.has(dist)) {
            entry.tree.set(dist, { label: dist, qty: 0, types: new Map() })
        }
        const dNode = entry.tree.get(dist)
        dNode.qty += (item.qty || 0)

        const type = item.name
        if (!dNode.types.has(type)) {
            dNode.types.set(type, { label: type, qty: 0, conditions: new Map() })
        }
        const tNode = dNode.types.get(type)
        tNode.qty += (item.qty || 0)

        const cond = item.condition || '-'
        const gb = item.storage || '-'
        const cgKey = `${cond}||${gb}`

        if (!tNode.conditions.has(cgKey)) {
            tNode.conditions.set(cgKey, {
                condition: cond,
                capacity: gb,
                qty: 0
            })
        }
        tNode.conditions.get(cgKey).qty += (item.qty || 0)
    })

    return Array.from(map.values())
        .map(e => ({
            ...e,
            tree: Array.from(e.tree.values()).map(d => ({
                ...d,
                types: Array.from(d.types.values()).map(t => ({
                    ...t,
                    conditions: Array.from(t.conditions.values()).sort((a, b) => b.qty - a.qty)
                })).sort((a, b) => b.qty - a.qty)
            })).sort((a, b) => b.qty - a.qty)
        }))
        .sort((a, b) => b.qty - a.qty)
})

// Hierarchical Distributor Report
const distributorHierarchy = computed(() => {
    const raw = salesData.value.distributor_sales || []
    const map = new Map()

    raw.forEach(item => {
        const dist = item.distributor
        if (!map.has(dist)) {
            map.set(dist, { distributor: dist, qty: 0, tree: new Map() })
        }
        const entry = map.get(dist)
        entry.qty += (item.qty || 0)

        const brand = item.brand || '-'
        if (!entry.tree.has(brand)) {
            entry.tree.set(brand, { label: brand, qty: 0, types: new Map() })
        }
        const bNode = entry.tree.get(brand)
        bNode.qty += (item.qty || 0)

        const type = item.product_type || '-'
        if (!bNode.types.has(type)) {
            bNode.types.set(type, { label: type, qty: 0, conditions: new Map() })
        }
        const tNode = bNode.types.get(type)
        tNode.qty += (item.qty || 0)

        const cond = item.condition || '-'
        const gb = item.storage || '-'
        const cgKey = `${cond}||${gb}`

        if (!tNode.conditions.has(cgKey)) {
            tNode.conditions.set(cgKey, {
                condition: cond,
                capacity: gb,
                qty: 0
            })
        }
        tNode.conditions.get(cgKey).qty += (item.qty || 0)
    })

    return Array.from(map.values())
        .map(e => ({
            ...e,
            tree: Array.from(e.tree.values()).map(b => ({
                ...b,
                types: Array.from(b.types.values()).map(t => ({
                    ...t,
                    conditions: Array.from(t.conditions.values()).sort((a, b) => b.qty - a.qty)
                })).sort((a, b) => b.qty - a.qty)
            })).sort((a, b) => b.qty - a.qty)
        }))
        .sort((a, b) => b.qty - a.qty)
})

const formatStorage = (storage) => {
    if (!storage) return '';
    let s = String(storage).toUpperCase().trim();
    if (s.endsWith('GBGB')) s = s.replace('GBGB', 'GB');
    return s.includes('GB') || s.includes('TB') ? s : s + ' GB';
}

const appleLuxItems = computed(() => {
    const raw = salesData.value?.report_summary?.stock_details?.apple_lux || [];
    const grouped = {};
    raw.forEach(item => {
        const key = `${item.name} ${formatStorage(item.storage)}`;
        if (!grouped[key]) {
            grouped[key] = { name: key, qty: 0 };
        }
        grouped[key].qty += 1;
    });
    return Object.values(grouped);
});

const categoryStocks = computed(() => {
    const summary = salesData.value?.report_summary || {};
    const soldDetails = summary.sold_details || {};
    const inDetails = summary.in_details || {};
    const stockReport = summary.stock_report || {};
    const stockDetails = summary.stock_details || {};
    const distMap = summary.dist_map || {};
    const distInMap = summary.dist_in_map || {};

    return [
        {
            label: 'STOK ACCESSORIES',
            count: distMap.accessories || 0,
            items: soldDetails.accessories || {},
            in: distInMap.accessories || 0,
            inItems: inDetails.accessories || {},
            remaining: stockReport.accessories || 0,
            remainingItems: stockDetails.accessories || {},
            suffix: 'Terjual'
        },
        {
            label: 'STOK APPLY',
            count: distMap.apply || 0,
            items: soldDetails.apply || {},
            in: distInMap.apply || 0,
            inItems: inDetails.apply || {},
            remaining: stockReport.apply || 0,
            remainingItems: stockDetails.apply || {},
            suffix: 'Terjual'
        },
        {
            label: 'STOK LAPTOP',
            count: distMap.laptop || 0,
            items: soldDetails.laptop || {},
            in: distInMap.laptop || 0,
            inItems: inDetails.laptop || {},
            remaining: stockReport.laptop || 0,
            remainingItems: stockDetails.laptop || {},
            suffix: 'Terjual'
        },
        {
            label: 'STOK ARCIS',
            count: distMap.arcis || 0,
            items: soldDetails.arcis || {},
            in: distInMap.arcis || 0,
            inItems: inDetails.arcis || {},
            remaining: stockReport.arcis || 0,
            remainingItems: stockDetails.arcis || {},
            suffix: 'Terjual'
        }
    ];
});

const getBaseReportText = (isForCopy = false) => {
    if (!salesData.value || !salesData.value.report_summary) return '';
    const summary = salesData.value.report_summary;
    const map = summary.dist_map || {};
    const mapRp = summary.dist_map_rp || {};
    const stock = summary.stock_report || {};
    const payments = summary.payments || {};
    const activities = summary.activities || {};

    const storeName = authStore.user?.branch?.name || authStore.user?.online_shop?.name || 'PSTORE';
    const dateStr = selectedPeriod.value === 'monthly'
        ? `${months[selectedMonth.value - 1]} ${selectedYear.value}`
        : formatDateString(filters.value.start_date);

    let text = `*LAPORAN PENJUALAN *\n`;
    text += `${storeName.toUpperCase()}\n`;
    text += `${dateStr}\n`;
    text += `============\n\n`;

    text += `PENJUALAN ALL\n\n`;
    if (summary.payment_total === 0) {
        text += `Belum ada transaksi\n`;
    } else {
        Object.entries(payments).forEach(([method, amount]) => {
            text += `${method.toUpperCase()} : ${formatCurrency(amount)}\n`;
        });
    }
    text += `\nTotal : ${formatCurrency(summary.payment_total)}\n`;
    text += `______\n\n`;

    text += `Rincian Penjualan berdasarkan distributor\n\n`;
    text += `🟦 Penjualan HP : ${formatCurrency(mapRp.hp || 0)}\n`;
    text += `🟦 Penjualan apple lux : ${formatCurrency(mapRp.apple_lux || 0)}\n`;
    text += `⬜️ Penjualan accesories : ${formatCurrency(mapRp.accessories || 0)}\n`;
    text += `⬜️ Penjualan apply : ${formatCurrency(mapRp.apply || 0)}\n`;
    text += `⬜️ Penjualan debs : ${formatCurrency(mapRp.debs || 0)}\n`;
    text += `⬜️ Penjualan arcis : ${formatCurrency(mapRp.arcis || 0)}\n`;
    text += `⬜️ Penjualan dokter pstore : ${formatCurrency(mapRp.dokter_pstore || 0)}\n`;
    text += `⬜️ Penjualan perdana : ${formatCurrency(mapRp.perdana || 0)}\n`;
    text += `⬜️ Penjualan jaringan : ${formatCurrency(mapRp.jaringan || 0)}\n`;

    text += `\nLaporan stok\n\n`;

    const stockDetails = summary.stock_details || {};
    const soldDetails = summary.sold_details || {};

    // Apple Lux Sisa
    text += `🔷 stok apple lux\n`;
    // Group Apple Lux by name and storage
    const appleLuxGrouped = {};
    (stockDetails.apple_lux || []).forEach(item => {
        const key = `${item.name} ${item.storage}GB`;
        appleLuxGrouped[key] = (appleLuxGrouped[key] || 0) + 1;
    });
    Object.entries(appleLuxGrouped).forEach(([name, qty]) => {
        text += `- ${name} : ${qty} unit\n`;
    });
    text += `\n`;

    // Other Sales Categories
    const categories = [
        { key: 'accessories', label: 'accesories' },
        { key: 'apply', label: 'apply' },
        { key: 'laptop', label: 'laptop' },
        { key: 'arcis', label: 'arcis' }
    ];

    categories.forEach(cat => {
        const remainingItems = stockDetails[cat.key] || {};
        const soldItems = soldDetails[cat.key] || {};
        const inItems = summary.in_details?.[cat.key] || {};

        if (Object.keys(remainingItems).length > 0 || Object.keys(soldItems).length > 0 || Object.keys(inItems).length > 0) {
            text += `🔷 stok ${cat.label}\n`;

            Object.entries(inItems).forEach(([name, qty]) => {
                text += `- ${name} : ${qty}\n`;
            });

            Object.entries(remainingItems).forEach(([name, qty]) => {
                text += `- ${name} : ${qty}\n`;
            });

            Object.entries(soldItems).forEach(([name, qty]) => {
                text += `- ${name} : ${qty}\n`;
            });
            text += `\n`;
        }
    });

    if (isForCopy) {
        text += `\n______________\n`;
        text += `Laporan keuangan\n\n`;
        text += `🔶 total cash ready\n………………\n………………\n\n`;
        text += `🔶 RICIAN PENGELUARAN\n………………\n………………\nTotal     :\n\n`;
        text += `🔶 RINCIAN DEPOSIT TOKO\n………………\n………………\nTotal     :\n\n`;
        text += `AWAL   :\nIN          :\nSISA     :\n`;
    }

    text += `______________\nunit HP keluar\n\n`;
    text += `Iphone       : ${summary.dist_map?.iphone || 0}\n`;
    text += `Apple lux   : ${summary.dist_map?.apple_lux || 0}\n`;
    text += `Android      : ${summary.dist_map?.android || 0}\n`;
    text += `Total HP     : ${(summary.dist_map?.hp || 0) + (summary.dist_map?.apple_lux || 0)}\n\n`;

    text += `Tukar unit          : ${activities.tukar_unit || 0}\n`;
    text += `Tukar tambah   : ${activities.tukar_tambah || 0}\n`;
    text += `Downgrade       : ${activities.downgrade || 0}\n`;
    text += `Refund               : ${activities.refund || 0}\n`;
    text += `Angkat barang  : ${activities.angkat_barang || 0}\n\n`;

    text += `Laptop        : ${summary.dist_map?.laptop || 0}\n`;
    text += `Tv                : ${summary.dist_map?.tv || 0}\n\n`;

    return text;
}

const displayReportText = computed(() => getBaseReportText(false));
const generatedReportText = computed(() => getBaseReportText(true));

const openSalesReport = () => {
    currentView.value = 'report';
    reportCopied.value = false;
}



const copyReportToClipboard = async () => {
    try {
        await navigator.clipboard.writeText(generatedReportText.value);
        reportCopied.value = true;
        setTimeout(() => { reportCopied.value = false; }, 2000);
    } catch (err) {
        console.error('Failed to copy!', err);
    }
}

const filteredBrandHierarchy = computed(() => {
    if (!searchQuery.value) return brandHierarchy.value
    const q = searchQuery.value.toLowerCase()
    return brandHierarchy.value.filter(b => b.brand.toLowerCase().includes(q))
})

const totals = computed(() => {
    let units = 0, iphone = 0, android = 0, nonHp = 0, revenue = 0, activity = 0, refund = 0;

    if (currentView.value === 'brand') {
        filteredBrandHierarchy.value.forEach(row => units += row.qty);
    } else if (currentView.value === 'distributor') {
        distributorHierarchy.value.forEach(row => units += row.qty);
    } else {
        sortedData.value.forEach(item => {
            if (currentView.value === 'revenue') {
                units += (item.total_units || 0);
                iphone += (item.iphone_units || 0);
                android += (item.android_units || 0);
                nonHp += (item.non_hp_units || 0);
                revenue += (item.total_omset || 0);
            } else if (currentView.value === 'sales' || currentView.value === 'activity') {
                units += (item.total_sales || 0);
                iphone += (item.iphone_units || 0);
                android += (item.android_units || 0);
                nonHp += (item.non_hp_units || 0);
                activity += (item.total_angkat_barang || 0);
                refund += (item.total_refund || 0);
                revenue += (item.grand_total || 0);
            } else {
                units += (item.qty || 0);
            }
        });
    }

    return { units, iphone, android, nonHp, revenue, activity, refund };
});

const handlePeriodChange = () => {
    if (selectedPeriod.value === 'daily') {
        const today = getTodayLocal();
        filters.value.start_date = today;
        filters.value.end_date = today;
        fetchData(); // Pastikan data harian langsung di-fetch
    } else {
        handleMonthChange();
    }
}

const handleDateChange = () => {
    filters.value.end_date = filters.value.start_date;
    fetchData();
}

const handleMonthChange = () => {
    const year = selectedYear.value;
    const month = selectedMonth.value;
    const endDate = new Date(year, month, 0);
    const pad = (n) => n < 10 ? '0' + n : n;
    filters.value.start_date = `${year}-${pad(month)}-01`;
    filters.value.end_date = `${year}-${pad(month)}-${pad(endDate.getDate())}`;
    fetchData();
}

// Filters are triggered via @change on each select, no watchers needed

const fetchData = async () => {
    loading.value = true
    try {
        const params = {
            start_date: filters.value.start_date,
            end_date: filters.value.end_date,
            branch_id: filters.value.branch_id,
            online_shop_id: filters.value.online_shop_id,
            distributor_id: filters.value.distributor_id,
            condition: filters.value.condition,
            capacity: filters.value.capacity,
            product_type_id: filters.value.product_type_id
        };
        const response = await axios.get('/audit/sales', { params })
        salesData.value = response.data
        // Populate filter dropdowns from actual sales data
        if (response.data.filter_options) {
            productTypes.value = response.data.filter_options.products || [];
            distributors.value = response.data.filter_options.distributors || [];
        }
    } catch (error) {
        console.error('Error fetching ranking data:', error)
    } finally {
        loading.value = false
    }
}

const handleLocationTypeChange = () => {
    filters.value.branch_id = null;
    filters.value.online_shop_id = null;
    fetchData();
}

const fetchLocations = async () => {
    try {
        const [bRes, oRes, dRes, pRes] = await Promise.all([
            axios.get('/branches'),
            axios.get('/online-shops'),
            axios.get('/distributors'),
            axios.get('/products?per_page=999')
        ]);
        branches.value = bRes.data;
        onlineShops.value = oRes.data;
        distributors.value = dRes.data?.data || dRes.data || [];
        const allProducts = pRes.data?.data || pRes.data || [];
        productTypes.value = Array.isArray(allProducts) ? allProducts.filter(p => p.type === 'hp') : [];
    } catch (error) {
        console.error('Error fetching locations:', error);
    }
}

onMounted(() => {
    fetchGlobalFilters() // Always fetch distributors and products for filtering
    if (isRestricted.value) {
        filters.value.branch_id = authStore.user?.branch_id || null;
        filters.value.online_shop_id = authStore.user?.online_shop_id || null;
        locationType.value = authStore.user?.branch_id ? 'branch' : 'online';
    } else {
        fetchLocations()
    }
    fetchData()
})

const fetchGlobalFilters = async () => {
    try {
        const [dRes, pRes] = await Promise.all([
            axios.get('/distributors'),
            axios.get('/products?per_page=999')
        ]);
        distributors.value = dRes.data?.data || dRes.data || [];
        const allProducts = pRes.data?.data || pRes.data || [];
        productTypes.value = Array.isArray(allProducts) ? allProducts.filter(p => p.type === 'hp') : [];
    } catch (error) {
        console.error('Error fetching global filters:', error);
    }
}
</script>

<style scoped>
.animate-in {
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(4px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
