<template>
    <div class="space-y-6">
        <h1 v-if="!isEmbedded" class="text-2xl font-bold tracking-tight text-text-primary">Audit Inventory</h1>

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
            <Inventory v-if="activeTab === 'stock'" :is-embedded="true" :branch-id="selectedBranchId" />
            <StockInHistory v-if="activeTab === 'in'" :is-embedded="true" :branch-id="selectedBranchId" />
            <StockOutHistory v-if="activeTab === 'out'" :is-embedded="true" :branch-id="selectedBranchId" />
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import {
    Package, ArrowDownUp, LogOut, Loader2
} from 'lucide-vue-next'
import axios from '../../api/axios'
import Inventory from '../inventory/Inventory.vue'
import StockInHistory from '../inventory/StockInHistory.vue'
import StockOutHistory from '../inventory/StockOutHistory.vue'
import { useAuthStore } from '../../store/auth'
import { watch } from 'vue'

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

const selectedBranchId = ref(null)
const branches = ref([])

const canFilterBranch = computed(() => {
    // Only Audit, Super Admin, Owner can filter branches
    const role = (authStore.userRole || '').toLowerCase();
    return ['super_admin', 'audit', 'owner'].some(r => role.includes(r));
})

const fetchBranches = async () => {
    try {
        const response = await axios.get('/branches')
        const allBranches = response.data.data || response.data || [];

        const user = authStore.user;
        const role = (authStore.userRole || '').toLowerCase();

        // Define unrestricted roles
        const isGlobalRole = ['super_admin', 'owner'].includes(role);

        // Collect allowed IDs from branch_id and placements
        let allowedIds = [];
        if (user?.branch_id) allowedIds.push(user.branch_id);

        if (user?.placements && Array.isArray(user.placements)) {
            const placementIds = user.placements
                .filter(p => p.model_type === 'branch')
                .map(p => p.model_id);
            allowedIds = [...allowedIds, ...placementIds];
        }

        // Deduplicate and ensure comparisons work (ids are usually numbers)
        allowedIds = [...new Set(allowedIds.map(id => Number(id)))];

        // LOGIC: If global role OR (Audit role AND no specific assignments) -> Show all
        if (isGlobalRole || (role === 'audit' && allowedIds.length === 0)) {
            branches.value = allBranches;
        } else if (allowedIds.length > 0) {
            branches.value = allBranches.filter(b => allowedIds.includes(Number(b.id)));
            // Auto-select first if needed
            if (branches.value.length > 0 && !selectedBranchId.value) {
                selectedBranchId.value = branches.value[0].id;
            }
        } else {
            branches.value = [];
        }
    } catch (error) {
        console.error('Error fetching branches:', error)
    }
}

const fetchStats = async () => {
    loading.value = true
    try {
        const response = await axios.get('/audit/inventory', {
            params: {
                branch_id: selectedBranchId.value || undefined
            }
        })
        stats.value = response.data
    } catch (error) {
        console.error('Error fetching inventory stats:', error)
    } finally {
        loading.value = false
    }
}

watch(selectedBranchId, () => {
    fetchStats();
    // Child components will react to prop change automatically if setup correctly
})

onMounted(async () => {
    if (canFilterBranch.value) {
        await fetchBranches()
    }
    fetchStats()
})
</script>
