<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { balancing } from '../../api/axios';
import { Building2, Search, MapPin, ArrowLeft, Loader2 } from 'lucide-vue-next';

const props = defineProps({
    balancingType: {
        type: String,
        default: 'payment-method'
    }
});

const router = useRouter();
const branches = ref([]);
const loading = ref(true);
const searchQuery = ref('');

const filteredBranches = computed(() => {
    if (!searchQuery.value) return branches.value;
    const q = searchQuery.value.toLowerCase();
    return branches.value.filter(b =>
        b.name.toLowerCase().includes(q) ||
        (b.address && b.address.toLowerCase().includes(q))
    );
});

async function fetchBranches() {
    loading.value = true;
    try {
        const { data } = await balancing.branches();
        branches.value = data.data || [];
    } catch (e) {
        console.error('Failed to fetch branches:', e);
    } finally {
        loading.value = false;
    }
}

function selectBranch(branch) {
    router.push({
        path: `/balancing/${props.balancingType}/form`,
        query: { branch_id: branch.id, branch_name: branch.name }
    });
}

function goBack() {
    router.push('/balancing');
}

onMounted(fetchBranches);
</script>

<template>
    <div class="max-w-5xl mx-auto">
        <!-- Header -->
        <div class="mb-8">
            <button @click="goBack"
                class="flex items-center gap-2 text-sm text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 transition-colors mb-4 group">
                <ArrowLeft :size="16" class="transition-transform group-hover:-translate-x-1" />
                <span>Kembali ke Menu Balancing</span>
            </button>

            <div class="flex items-center gap-3">
                <div class="p-2.5 rounded-2xl bg-gradient-to-br from-violet-500 to-indigo-600 text-white shadow-lg shadow-violet-500/20">
                    <Building2 :size="24" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-neutral-900 dark:text-white">Pilih Cabang</h1>
                    <p class="text-sm text-neutral-500 dark:text-neutral-400">Pilih cabang yang akan di-balancing</p>
                </div>
            </div>
        </div>

        <!-- Search -->
        <div class="relative mb-6">
            <Search :size="18" class="absolute left-4 top-1/2 -translate-y-1/2 text-neutral-400" />
            <input
                v-model="searchQuery"
                type="text"
                placeholder="Cari nama cabang..."
                class="w-full pl-11 pr-4 py-3.5 rounded-2xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800/50 text-neutral-900 dark:text-white placeholder-neutral-400 focus:outline-none focus:ring-2 focus:ring-violet-500/40 focus:border-violet-500 transition-all text-sm"
            />
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex items-center justify-center py-20">
            <Loader2 :size="32" class="animate-spin text-violet-500" />
        </div>

        <!-- Branch Grid -->
        <div v-else-if="filteredBranches.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <button
                v-for="branch in filteredBranches"
                :key="branch.id"
                @click="selectBranch(branch)"
                class="group text-left p-5 rounded-2xl border border-neutral-200 dark:border-neutral-700/60 bg-white dark:bg-neutral-800/30 hover:border-violet-300 dark:hover:border-violet-600/50 hover:shadow-lg hover:shadow-violet-100/50 dark:hover:shadow-violet-900/20 transition-all duration-300 hover:-translate-y-0.5"
            >
                <div class="flex items-start gap-3.5">
                    <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-violet-100 to-indigo-100 dark:from-violet-900/40 dark:to-indigo-900/40 flex items-center justify-center shrink-0 transition-transform group-hover:scale-105">
                        <Building2 :size="20" class="text-violet-600 dark:text-violet-400" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="font-semibold text-neutral-900 dark:text-white truncate text-sm group-hover:text-violet-600 dark:group-hover:text-violet-400 transition-colors">
                            {{ branch.name }}
                        </h3>
                        <div v-if="branch.address" class="flex items-center gap-1 mt-1.5">
                            <MapPin :size="12" class="text-neutral-400 shrink-0" />
                            <p class="text-xs text-neutral-500 dark:text-neutral-400 truncate">{{ branch.address }}</p>
                        </div>
                        <div v-if="branch.timezone" class="mt-1">
                            <span class="inline-flex px-2 py-0.5 rounded-md text-[10px] font-medium bg-neutral-100 dark:bg-neutral-700 text-neutral-500 dark:text-neutral-400">
                                {{ branch.timezone || 'WIB' }}
                            </span>
                        </div>
                    </div>
                </div>
            </button>
        </div>

        <!-- Empty State -->
        <div v-else class="text-center py-20">
            <Building2 :size="48" class="mx-auto text-neutral-300 dark:text-neutral-600 mb-4" />
            <p class="text-neutral-500 dark:text-neutral-400">
                {{ searchQuery ? 'Tidak ada cabang yang cocok' : 'Belum ada cabang terdaftar' }}
            </p>
        </div>
    </div>
</template>
