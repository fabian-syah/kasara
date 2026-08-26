<script setup>
import { useRouter } from 'vue-router';
import { Scale, CreditCard, ShoppingBag, ArrowRight, Shield } from 'lucide-vue-next';

const router = useRouter();

const menuItems = [
    {
        id: 'payment_method',
        title: 'Balancing Metode Pembayaran',
        description: 'Koreksi dan revisi metode pembayaran pada transaksi yang sudah tercatat. Mendukung nilai minus untuk pengurangan omset.',
        icon: CreditCard,
        color: 'from-violet-500 to-indigo-600',
        bgColor: 'bg-violet-50 dark:bg-violet-950/30',
        borderColor: 'border-violet-200 dark:border-violet-800/40',
        path: '/balancing/payment-method',
        available: true,
    },
    {
        id: 'missed_sale',
        title: 'Balancing Penjualan Terlewat',
        description: 'Input penjualan yang terlewat atau belum tercatat di sistem. Data akan masuk ke omset pada tanggal yang ditentukan.',
        icon: ShoppingBag,
        color: 'from-amber-500 to-orange-600',
        bgColor: 'bg-amber-50 dark:bg-amber-950/30',
        borderColor: 'border-amber-200 dark:border-amber-800/40',
        path: '/balancing/missed-sale',
        available: false,
    },
];

function navigateTo(item) {
    if (!item.available) return;
    router.push(item.path);
}
</script>

<template>
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center gap-3 mb-3">
                <div class="p-2.5 rounded-2xl bg-gradient-to-br from-violet-500 to-indigo-600 text-white shadow-lg shadow-violet-500/20">
                    <Scale :size="24" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-neutral-900 dark:text-white">Balancing</h1>
                    <p class="text-sm text-neutral-500 dark:text-neutral-400">Koreksi & Adjustment Data Penjualan</p>
                </div>
            </div>

            <!-- Admin Badge -->
            <div class="flex items-center gap-2 mt-4 px-3 py-2 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/40 w-fit">
                <Shield :size="14" class="text-amber-600 dark:text-amber-400" />
                <span class="text-xs font-semibold text-amber-700 dark:text-amber-300 uppercase tracking-wide">Super Admin Only</span>
            </div>
        </div>

        <!-- Menu Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <button
                v-for="item in menuItems"
                :key="item.id"
                @click="navigateTo(item)"
                :disabled="!item.available"
                class="group relative text-left rounded-2xl border transition-all duration-300 overflow-hidden"
                :class="[
                    item.borderColor,
                    item.available
                        ? 'hover:shadow-xl hover:shadow-neutral-200/50 dark:hover:shadow-neutral-900/50 hover:-translate-y-1 cursor-pointer'
                        : 'opacity-50 cursor-not-allowed',
                ]"
            >
                <!-- Gradient Glow Effect -->
                <div
                    class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-500 pointer-events-none"
                    :class="item.available ? '' : '!opacity-0'"
                >
                    <div class="absolute -inset-1 bg-gradient-to-r blur-xl opacity-20" :class="item.color"></div>
                </div>

                <div class="relative p-6" :class="item.bgColor">
                    <!-- Icon -->
                    <div class="w-14 h-14 rounded-2xl bg-gradient-to-br flex items-center justify-center mb-5 shadow-lg transition-transform duration-300 group-hover:scale-110"
                        :class="item.color">
                        <component :is="item.icon" :size="26" class="text-white" />
                    </div>

                    <!-- Title -->
                    <h3 class="text-lg font-bold text-neutral-900 dark:text-white mb-2">
                        {{ item.title }}
                    </h3>

                    <!-- Description -->
                    <p class="text-sm text-neutral-600 dark:text-neutral-400 leading-relaxed mb-4">
                        {{ item.description }}
                    </p>

                    <!-- CTA -->
                    <div class="flex items-center gap-2 text-sm font-semibold transition-all duration-300"
                        :class="item.available ? 'text-violet-600 dark:text-violet-400 group-hover:gap-3' : 'text-neutral-400'">
                        <span>{{ item.available ? 'Mulai' : 'Segera Hadir' }}</span>
                        <ArrowRight v-if="item.available" :size="16" class="transition-transform duration-300 group-hover:translate-x-1" />
                    </div>
                </div>
            </button>
        </div>
    </div>
</template>
