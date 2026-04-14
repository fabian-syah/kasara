<template>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <h1 v-if="!isEmbedded" class="text-2xl font-bold tracking-tight text-text-primary">Audit Inventory</h1>

            <!-- Location Filter -->
            <div v-if="canFilterBranch" class="min-w-[200px]">
                <select v-model="selectedLocationKey"
                    class="block w-full rounded-2xl border-0 py-2.5 text-text-primary shadow-sm ring-1 ring-inset ring-surface-200 focus:ring-2 focus:ring-inset focus:ring-primary-600 sm:text-sm sm:leading-6 dark:bg-surface-800 dark:ring-surface-700">
                    <option value="all">Semua Cabang/Toko</option>
                    <option v-for="loc in locations" :key="`${loc.type}:${loc.id}`"
                        :value="`${loc.type === 'branch' ? 'B' : 'S'}:${loc.id}`">
                        {{ loc.type === 'branch' ? '[Cabang]' : '[Online]' }} {{ loc.name }}
                    </option>
                </select>
            </div>
        </div>

        <div v-if="loading" class="flex justify-center py-12">
            <Loader2 class="w-8 h-8 text-primary-600 animate-spin" />
        </div>

        <div v-else class="grid grid-cols-1 gap-5 sm:grid-cols-3">
            <!-- Card Stock -->
            <div class="overflow-hidden shadow rounded-lg hover:shadow-md transition-shadow cursor-pointer"
                :class="activeTab === 'stock' ? 'bg-surface-900 border border-surface-700 text-white' : 'bg-surface-0 dark:bg-surface-800 border border-surface-200 dark:border-surface-700'"
                @click="activeTab = 'stock'">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <Package class="h-6 w-6" :class="activeTab === 'stock' ? 'text-gray-400' : 'text-gray-400'"
                                aria-hidden="true" />
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium truncate"
                                    :class="activeTab === 'stock' ? 'text-gray-400' : 'text-text-secondary'">Total Stock
                                </dt>
                                <dd>
                                    <div class="text-lg font-medium"
                                        :class="activeTab === 'stock' ? 'text-white' : 'text-text-primary'">{{
                                            stats.total_stock }}</div>
                                    <p class="text-xs mt-1"
                                        :class="activeTab === 'stock' ? 'text-gray-400' : 'text-text-secondary'">
                                        Melihat semua stok barang
                                    </p>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card In -->
            <div @click="activeTab = 'in'"
                class="bg-surface-0 dark:bg-surface-800 overflow-hidden shadow rounded-2xl border border-surface-200 dark:border-surface-700 cursor-pointer transition-all hover:border-emerald-500 relative group">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="p-3 bg-emerald-100 dark:bg-emerald-900/30 rounded-xl">
                                <ArrowDownUp class="h-6 w-6 text-emerald-600 dark:text-emerald-400" />
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-text-secondary truncate">Barang Masuk (Bulan Ini)
                                </dt>
                                <dd>
                                    <div class="text-2xl font-bold text-text-primary">{{ stats.total_in }}</div>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
                <div v-if="activeTab === 'in'" class="absolute bottom-0 left-0 right-0 h-1 bg-emerald-500"></div>
            </div>

            <!-- Card Out -->
            <div @click="activeTab = 'out'"
                class="bg-surface-0 dark:bg-surface-800 overflow-hidden shadow rounded-2xl border border-surface-200 dark:border-surface-700 cursor-pointer transition-all hover:border-rose-500 relative group">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="p-3 bg-rose-100 dark:bg-rose-900/30 rounded-xl">
                                <LogOut class="h-6 w-6 text-rose-600 dark:text-rose-400" />
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-text-secondary truncate">Barang Keluar (Bulan Ini)
                                </dt>
                                <dd>
                                    <div class="text-2xl font-bold text-text-primary">{{ stats.total_out }}</div>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
                <div v-if="activeTab === 'out'" class="absolute bottom-0 left-0 right-0 h-1 bg-rose-500"></div>
            </div>
        </div>

        <!-- Render Component based on Active Tab -->
        <div class="mt-6">
            <Inventory v-if="activeTab === 'stock'" :is-embedded="true" :branch-id="selectedBranchId"
                :online-shop-id="selectedOnlineShopId" />
            <StockInHistory v-if="activeTab === 'in'" :is-embedded="true" :branch-id="selectedBranchId"
                :online-shop-id="selectedOnlineShopId" />
            <StockOutHistory v-if="activeTab === 'out'" :is-embedded="true" :branch-id="selectedBranchId"
                :online-shop-id="selectedOnlineShopId" />
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useAuthStore } from '../../store/auth'
import axios from '../../api/axios'
import {
    Package,
    ArrowDownUp,
    LogOut,
    Loader2
} from 'lucide-vue-next'
import Inventory from '../inventory/Inventory.vue'
import StockInHistory from '../inventory/StockInHistory.vue'
import StockOutHistory from '../inventory/StockOutHistory.vue'

const authStore = useAuthStore()

const props = defineProps({
    isEmbedded: {
        type: Boolean,
        default: false
    }
})

const activeTab = ref('stock')
const loading = ref(false)
const stats = ref({
    total_stock: 0,
    total_in: 0,
    total_out: 0
})

const locations = ref([])
const selectedLocationKey = ref('all')

const selectedBranchId = computed(() => {
    if (selectedLocationKey.value === 'all' || !selectedLocationKey.value.startsWith('B:')) return null;
    return selectedLocationKey.value.split(':')[1];
})

const selectedOnlineShopId = computed(() => {
    if (selectedLocationKey.value === 'all' || !selectedLocationKey.value.startsWith('S:')) return null;
    return selectedLocationKey.value.split(':')[1];
})

const canFilterBranch = computed(() => {
    const role = (authStore.userRole || '').toLowerCase();
    const privilegedRoles = ['super_admin', 'audit', 'owner', 'leader', 'analist', 'admin_produk'];
    return privilegedRoles.some(r => role.includes(r));
})

const fetchBranches = async () => {
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

        console.log('[DEBUG-INVENTORY] Fresh User Data:', user);
        console.log('[DEBUG-INVENTORY] All Available Shops:', allShops);

        const privilegedRoles = ['super_admin', 'owner', 'admin_produk'];
        const isAlwaysGlobal = privilegedRoles.some(r => role.includes(r));

        // Collect allowed IDs
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

        // Deduplicate
        allowedBranchIds = [...new Set(allowedBranchIds.map(id => Number(id)))];
        allowedShopIds = [...new Set(allowedShopIds.map(id => Number(id)))];

        const hasAnyRestriction = allowedBranchIds.length > 0 || allowedShopIds.length > 0;

        if (isAlwaysGlobal) {
            locations.value = allLocations;
        } else if (hasAnyRestriction) {
            locations.value = allLocations.filter(loc => {
                if (loc.type === 'branch') return allowedBranchIds.includes(Number(loc.id));
                if (loc.type === 'online_shop') return allowedShopIds.includes(Number(loc.id));
                return false;
            });

            // Auto-select first if needed
            if (locations.value.length === 1 && selectedLocationKey.value === 'all') {
                const loc = locations.value[0];
                selectedLocationKey.value = `${loc.type === 'branch' ? 'B' : 'S'}:${loc.id}`;
            }
        } else if (role.includes('audit') || role.includes('leader') || role.includes('analist')) {
            locations.value = allLocations;
        } else {
            locations.value = [];
        }
    } catch (error) {
        console.error('Error fetching locations:', error)
    }
}

const fetchStats = async () => {
    loading.value = true
    try {
        const response = await axios.get('/audit/inventory', {
            params: {
                branch_id: selectedBranchId.value || undefined,
                online_shop_id: selectedOnlineShopId.value || undefined
            }
        })
        stats.value = response.data
    } catch (error) {
        console.error('Error fetching inventory stats:', error)
    } finally {
        loading.value = false
    }
}

watch(selectedLocationKey, () => {
    fetchStats();
    // Child components will react to prop change automatically
})

onMounted(async () => {
    if (canFilterBranch.value) {
        await fetchBranches()
    }
    fetchStats()
})
</script>
