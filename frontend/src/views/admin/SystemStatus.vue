<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import api from '../../api/axios';
import { useToast } from '../../composables/useToast';
import {
    Server, Cpu, HardDrive, Database, Zap, Radio, Shield, Lock,
    Globe, Palette, Box, Activity, RefreshCw, CheckCircle2, XCircle,
    Clock, MemoryStick, Layers, Wifi, WifiOff, ChevronDown, ChevronUp, Bolt, Smartphone
} from 'lucide-vue-next';

const toast = useToast();

const isLoading = ref(true);
const data = ref(null);
const error = ref(null);
const lastChecked = ref(null);
const apiLatency = ref(null);
const expandedSections = ref({
    server: true,
    techStack: true,
    resources: true,
    php: false,
    database: false,
});
let autoRefreshInterval = null;

const isOnline = computed(() => data.value?.server?.status === 'online');

const fetchStatus = async () => {
    const start = performance.now();
    try {
        const response = await api.get('/system-status');
        const end = performance.now();
        apiLatency.value = Math.round(end - start);
        data.value = response.data;
        error.value = null;
        lastChecked.value = new Date();
    } catch (err) {
        const end = performance.now();
        apiLatency.value = Math.round(end - start);
        error.value = err.message || 'Gagal menghubungi server';
        data.value = null;
    } finally {
        isLoading.value = false;
    }
};

const toggleSection = (section) => {
    expandedSections.value[section] = !expandedSections.value[section];
};

const getStackIcon = (iconName) => {
    const iconMap = {
        'zap': Zap,
        'radio': Radio,
        'shield': Shield,
        'lock': Lock,
        'database': Database,
        'bolt': Bolt,
        'palette': Palette,
        'box': Box,
        'globe': Globe,
    };
    return iconMap[iconName] || Layers;
};

const diskUsageColor = computed(() => {
    const pct = data.value?.disk?.usage_percent || 0;
    if (pct > 90) return 'from-red-500 to-red-600';
    if (pct > 70) return 'from-amber-500 to-amber-600';
    return 'from-emerald-500 to-emerald-600';
});

const diskTextColor = computed(() => {
    const pct = data.value?.disk?.usage_percent || 0;
    if (pct > 90) return 'text-red-400';
    if (pct > 70) return 'text-amber-400';
    return 'text-emerald-400';
});

onMounted(() => {
    fetchStatus();
    autoRefreshInterval = setInterval(fetchStatus, 30000); // auto-refresh 30s
});

onUnmounted(() => {
    if (autoRefreshInterval) clearInterval(autoRefreshInterval);
});
</script>

<template>
    <div class="space-y-6 animate-in">
        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-2xl font-bold text-text-primary tracking-tight flex items-center gap-3">
                    <div
                        class="p-2 rounded-xl bg-linear-to-br from-primary-500/20 to-primary-600/10 border border-primary-500/20">
                        <Server :size="24" class="text-primary-400" />
                    </div>
                    VPS Status Monitor
                </h1>
                <p class="text-text-secondary mt-1 ml-14">
                    <span class="font-mono text-xs bg-surface-700 px-2 py-0.5 rounded">api.stokps.com</span>
                    <span v-if="lastChecked" class="ml-2 text-xs">
                        Terakhir cek: {{ lastChecked.toLocaleTimeString('id-ID') }}
                    </span>
                </p>
            </div>

            <div class="flex items-center gap-3">
                <!-- Latency Badge -->
                <div v-if="apiLatency !== null" class="px-3 py-1.5 rounded-lg text-xs font-medium border" :class="apiLatency < 500
                    ? 'text-emerald-400 bg-emerald-400/10 border-emerald-400/20'
                    : apiLatency < 2000
                        ? 'text-amber-400 bg-amber-400/10 border-amber-400/20'
                        : 'text-red-400 bg-red-400/10 border-red-400/20'">
                    <Activity :size="12" class="inline mr-1" />
                    {{ apiLatency }}ms
                </div>

                <!-- Status Badge -->
                <div class="px-3 py-1.5 rounded-lg text-xs font-bold border flex items-center gap-1.5" :class="isOnline
                    ? 'text-emerald-400 bg-emerald-400/10 border-emerald-400/20'
                    : 'text-red-400 bg-red-400/10 border-red-400/20'">
                    <span class="relative flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full rounded-full opacity-75 animate-ping"
                            :class="isOnline ? 'bg-emerald-400' : 'bg-red-400'"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2"
                            :class="isOnline ? 'bg-emerald-400' : 'bg-red-400'"></span>
                    </span>
                    {{ isOnline ? 'ONLINE' : (error ? 'OFFLINE' : 'CHECKING...') }}
                </div>

                <button @click="fetchStatus" :disabled="isLoading"
                    class="p-2.5 text-text-secondary hover:text-primary-500 hover:bg-primary-500/10 rounded-xl transition-all">
                    <RefreshCw :size="20" :class="{ 'animate-spin': isLoading }" />
                </button>
            </div>
        </div>

        <!-- Error State -->
        <div v-if="error && !isLoading"
            class="p-6 bg-red-500/10 border border-red-500/20 rounded-2xl text-center space-y-3">
            <WifiOff :size="48" class="mx-auto text-red-400" />
            <p class="text-red-400 font-bold text-lg">VPS Tidak Dapat Dijangkau</p>
            <p class="text-text-secondary text-sm">{{ error }}</p>
            <button @click="fetchStatus"
                class="mt-2 px-6 py-2 bg-red-500/20 hover:bg-red-500/30 text-red-400 rounded-xl text-sm font-medium transition-all border border-red-500/30">
                Coba Lagi
            </button>
        </div>

        <!-- Loading Skeleton -->
        <div v-if="isLoading && !data" class="space-y-4">
            <div v-for="n in 3" :key="n" class="bg-surface-800 rounded-2xl border border-surface-700 p-6 animate-pulse">
                <div class="h-5 bg-surface-700 rounded w-1/3 mb-4"></div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div v-for="i in 4" :key="i" class="h-16 bg-surface-700 rounded-xl"></div>
                </div>
            </div>
        </div>

        <!-- Data Sections -->
        <template v-if="data">
            <!-- Server Overview Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Hostname -->
                <div
                    class="bg-surface-800 rounded-2xl border border-surface-700 p-4 hover:border-primary-500/30 transition-all">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="p-2 rounded-lg bg-blue-500/10">
                            <Server :size="18" class="text-blue-400" />
                        </div>
                        <span class="text-xs text-text-secondary uppercase font-semibold">Hostname</span>
                    </div>
                    <p class="text-lg font-bold text-text-primary truncate">{{ data.server.hostname }}</p>
                    <p class="text-xs text-text-secondary mt-1">{{ data.server.os }}</p>
                </div>

                <!-- Uptime -->
                <div
                    class="bg-surface-800 rounded-2xl border border-surface-700 p-4 hover:border-emerald-500/30 transition-all">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="p-2 rounded-lg bg-emerald-500/10">
                            <Clock :size="18" class="text-emerald-400" />
                        </div>
                        <span class="text-xs text-text-secondary uppercase font-semibold">Uptime</span>
                    </div>
                    <p class="text-lg font-bold text-emerald-400">{{ data.server.uptime }}</p>
                    <p class="text-xs text-text-secondary mt-1">{{ data.server.current_time }}</p>
                </div>

                <!-- Disk Usage -->
                <div
                    class="bg-surface-800 rounded-2xl border border-surface-700 p-4 hover:border-surface-600 transition-all">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="p-2 rounded-lg bg-violet-500/10">
                            <HardDrive :size="18" class="text-violet-400" />
                        </div>
                        <span class="text-xs text-text-secondary uppercase font-semibold">Disk</span>
                    </div>
                    <p class="text-lg font-bold" :class="diskTextColor">{{ data.disk.usage_percent }}%</p>
                    <div class="w-full bg-surface-900 rounded-full h-2 mt-2 overflow-hidden">
                        <div class="h-full rounded-full bg-linear-to-r transition-all duration-500"
                            :class="diskUsageColor" :style="{ width: data.disk.usage_percent + '%' }">
                        </div>
                    </div>
                    <p class="text-[10px] text-text-secondary mt-1">{{ data.disk.used }} / {{ data.disk.total }}</p>
                </div>

                <!-- Memory -->
                <div
                    class="bg-surface-800 rounded-2xl border border-surface-700 p-4 hover:border-surface-600 transition-all">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="p-2 rounded-lg bg-amber-500/10">
                            <Cpu :size="18" class="text-amber-400" />
                        </div>
                        <span class="text-xs text-text-secondary uppercase font-semibold">Memory (PHP)</span>
                    </div>
                    <p class="text-lg font-bold text-amber-400">{{ data.memory.current }}</p>
                    <p class="text-xs text-text-secondary mt-1">Peak: {{ data.memory.peak }} / Limit: {{
                        data.memory.limit }}</p>
                </div>
            </div>

            <!-- Tech Stack -->
            <div class="bg-surface-800 rounded-2xl border border-surface-700 overflow-hidden">
                <button @click="toggleSection('techStack')"
                    class="w-full flex items-center justify-between p-5 hover:bg-surface-700/30 transition-colors">
                    <div class="flex items-center gap-3">
                        <div class="p-2 rounded-lg bg-linear-to-br from-primary-500/20 to-violet-500/20">
                            <Layers :size="18" class="text-primary-400" />
                        </div>
                        <h2 class="text-lg font-bold text-text-primary">Tech Stack</h2>
                        <span class="text-xs bg-surface-700 px-2 py-0.5 rounded-full text-text-secondary">
                            {{ data.tech_stack.length }} items
                        </span>
                    </div>
                    <component :is="expandedSections.techStack ? ChevronUp : ChevronDown" :size="20"
                        class="text-text-secondary" />
                </button>

                <div v-show="expandedSections.techStack" class="p-5 pt-0">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                        <div v-for="(tech, idx) in data.tech_stack" :key="idx"
                            class="group flex items-center gap-3 p-3 rounded-xl bg-surface-900/50 border border-surface-700/50 hover:border-surface-600 transition-all hover:bg-surface-700/30">
                            <div class="p-2 rounded-lg shrink-0" :style="{ backgroundColor: tech.color + '15' }">
                                <component :is="getStackIcon(tech.icon)" :size="18" :style="{ color: tech.color }" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-text-primary truncate">{{ tech.name }}</p>
                                <p class="text-xs font-mono" :style="{ color: tech.color }">v{{ tech.version }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PHP Extensions -->
            <div class="bg-surface-800 rounded-2xl border border-surface-700 overflow-hidden">
                <button @click="toggleSection('php')"
                    class="w-full flex items-center justify-between p-5 hover:bg-surface-700/30 transition-colors">
                    <div class="flex items-center gap-3">
                        <div class="p-2 rounded-lg bg-indigo-500/10">
                            <Cpu :size="18" class="text-indigo-400" />
                        </div>
                        <h2 class="text-lg font-bold text-text-primary">PHP Configuration</h2>
                    </div>
                    <component :is="expandedSections.php ? ChevronUp : ChevronDown" :size="20"
                        class="text-text-secondary" />
                </button>

                <div v-show="expandedSections.php" class="p-5 pt-0 space-y-4">
                    <!-- PHP Stats -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <div class="p-3 bg-surface-900/50 rounded-xl border border-surface-700/50">
                            <p class="text-[10px] uppercase text-text-secondary font-semibold">Memory Limit</p>
                            <p class="text-sm font-bold text-text-primary mt-1">{{ data.php.memory_limit }}</p>
                        </div>
                        <div class="p-3 bg-surface-900/50 rounded-xl border border-surface-700/50">
                            <p class="text-[10px] uppercase text-text-secondary font-semibold">Max Execution</p>
                            <p class="text-sm font-bold text-text-primary mt-1">{{ data.php.max_execution_time }}</p>
                        </div>
                        <div class="p-3 bg-surface-900/50 rounded-xl border border-surface-700/50">
                            <p class="text-[10px] uppercase text-text-secondary font-semibold">Upload Max</p>
                            <p class="text-sm font-bold text-text-primary mt-1">{{ data.php.upload_max_filesize }}</p>
                        </div>
                        <div class="p-3 bg-surface-900/50 rounded-xl border border-surface-700/50">
                            <p class="text-[10px] uppercase text-text-secondary font-semibold">Post Max Size</p>
                            <p class="text-sm font-bold text-text-primary mt-1">{{ data.php.post_max_size }}</p>
                        </div>
                    </div>

                    <!-- Extensions -->
                    <div>
                        <p class="text-xs text-text-secondary uppercase font-semibold mb-2">Extensions</p>
                        <div class="flex flex-wrap gap-2">
                            <div v-for="(enabled, ext) in data.php.extensions" :key="ext"
                                class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium border"
                                :class="enabled
                                    ? 'text-emerald-400 bg-emerald-400/10 border-emerald-400/20'
                                    : 'text-red-400 bg-red-400/10 border-red-400/20'">
                                <component :is="enabled ? CheckCircle2 : XCircle" :size="12" />
                                {{ ext }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Database & Laravel -->
            <div class="bg-surface-800 rounded-2xl border border-surface-700 overflow-hidden">
                <button @click="toggleSection('database')"
                    class="w-full flex items-center justify-between p-5 hover:bg-surface-700/30 transition-colors">
                    <div class="flex items-center gap-3">
                        <div class="p-2 rounded-lg bg-cyan-500/10">
                            <Database :size="18" class="text-cyan-400" />
                        </div>
                        <h2 class="text-lg font-bold text-text-primary">Database & Laravel</h2>
                    </div>
                    <component :is="expandedSections.database ? ChevronUp : ChevronDown" :size="20"
                        class="text-text-secondary" />
                </button>

                <div v-show="expandedSections.database" class="p-5 pt-0">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Database -->
                        <div class="p-4 bg-surface-900/50 rounded-xl border border-surface-700/50">
                            <h3 class="text-sm font-bold text-text-primary mb-3 flex items-center gap-2">
                                <Database :size="14" class="text-cyan-400" /> Database
                            </h3>
                            <div class="space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span class="text-text-secondary">Status</span>
                                    <span class="font-medium"
                                        :class="data.database.status === 'connected' ? 'text-emerald-400' : 'text-red-400'">
                                        {{ data.database.status === 'connected' ? '● Connected' : '● Error' }}
                                    </span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-text-secondary">Driver</span>
                                    <span class="text-text-primary font-medium uppercase">{{ data.database.driver
                                    }}</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-text-secondary">Version</span>
                                    <span class="text-text-primary font-mono text-xs">{{ data.database.version }}</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-text-secondary">Database</span>
                                    <span class="text-text-primary font-mono text-xs">{{ data.database.database
                                    }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Laravel -->
                        <div class="p-4 bg-surface-900/50 rounded-xl border border-surface-700/50">
                            <h3 class="text-sm font-bold text-text-primary mb-3 flex items-center gap-2">
                                <Zap :size="14" class="text-rose-400" /> Laravel
                            </h3>
                            <div class="space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span class="text-text-secondary">Environment</span>
                                    <span class="font-medium px-2 py-0.5 rounded text-xs" :class="data.laravel.environment === 'production'
                                        ? 'text-emerald-400 bg-emerald-400/10'
                                        : 'text-amber-400 bg-amber-400/10'">
                                        {{ data.laravel.environment }}
                                    </span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-text-secondary">Debug Mode</span>
                                    <span :class="data.laravel.debug_mode ? 'text-amber-400' : 'text-emerald-400'"
                                        class="font-medium">
                                        {{ data.laravel.debug_mode ? '⚠ ON' : '✓ OFF' }}
                                    </span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-text-secondary">Cache</span>
                                    <span class="text-text-primary font-medium">{{ data.laravel.cache_driver }}</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-text-secondary">Session</span>
                                    <span class="text-text-primary font-medium">{{ data.laravel.session_driver }}</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-text-secondary">Queue</span>
                                    <span class="text-text-primary font-medium">{{ data.laravel.queue_driver }}</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-text-secondary">Broadcast</span>
                                    <span class="text-text-primary font-medium">{{ data.laravel.broadcast_driver
                                    }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

<style scoped>
.animate-in {
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
