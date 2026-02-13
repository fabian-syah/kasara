<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import api from '../../api/axios';
import {
    Server, Cpu, HardDrive, Database, Activity, RefreshCw, CheckCircle2, XCircle,
    Clock, Layers, WifiOff, ChevronDown, ChevronUp,
    Shield, ShieldAlert, ShieldCheck, Lock, Unlock, Ban, AlertTriangle
} from 'lucide-vue-next';

const REFRESH_INTERVAL = 5000; // 5 detik

const isLoading = ref(true);
const data = ref(null);
const error = ref(null);
const lastChecked = ref(null);
const apiLatency = ref(null);
const countdown = ref(5);
const expandedSections = ref({
    techStack: true,
    php: false,
    database: false,
});
let autoRefreshInterval = null;
let countdownInterval = null;

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
        countdown.value = REFRESH_INTERVAL / 1000;
    }
};

const isBlocking = ref(false);

const toggleDefender = async () => {
    // Optimistic UI update or wait? Let's wait.
    try {
        isLoading.value = true;
        await api.post('/system-status/toggle-defender');
        await fetchStatus();
    } catch (err) {
        // alert('Gagal mengubah status defender: ' + (err.response?.data?.message || err.message));
        error.value = err.response?.data?.message || err.message;
    } finally {
        isLoading.value = false;
    }
};

const blockIp = async (ip) => {
    if (!confirm(`Blokir IP ${ip} secara manual?`)) return;
    try {
        isBlocking.value = true;
        await api.post('/system-status/block-ip', { ip });
        await fetchStatus();
    } catch (err) {
        alert('Gagal memblokir IP: ' + (err.response?.data?.message || err.message));
    } finally {
        isBlocking.value = false;
    }
};

const unblockIp = async (ip) => {
    if (!confirm(`Buka blokir IP ${ip}?`)) return;
    try {
        isBlocking.value = true;
        await api.post('/system-status/unblock-ip', { ip });
        await fetchStatus();
    } catch (err) {
        alert('Gagal membuka blokir IP: ' + (err.response?.data?.message || err.message));
    } finally {
        isBlocking.value = false;
    }
};


const toggleSection = (section) => {
    expandedSections.value[section] = !expandedSections.value[section];
};

// SVG icon map — real brand logos
const brandSvgs = {
    php: `<svg viewBox="0 0 128 128" fill="currentColor"><path d="M64 33.039C30.26 33.039 2.906 46.901 2.906 64S30.26 94.961 64 94.961 125.094 81.099 125.094 64 97.74 33.039 64 33.039zM48.103 70.032c-1.458 1.364-3.077 1.927-4.86 2.507-1.783.581-4.052.461-6.811.461h-6.253l-1.733 10h-7.301l6.515-34H41.7c4.224 0 7.305 1.215 9.242 3.432 1.937 2.217 2.519 5.364 1.747 9.337-.319 1.637-.856 3.159-1.614 4.515a15.118 15.118 0 01-2.972 3.748zM69.414 73l2.881-14.42c.328-1.688.132-2.913-.59-3.676-.723-.764-2.017-1.146-3.882-1.146h-6.253l-3.7 19.241h-7.301l6.515-34h7.302l-1.733 10h6.897c3.379 0 5.789.749 7.227 2.075 1.438 1.327 1.858 3.485 1.263 6.625L75.065 73h-5.651zm33.139-3.968c-1.458 1.364-3.077 1.927-4.86 2.507-1.783.581-4.053.461-6.812.461h-6.253l-1.733 10h-7.301l6.514-34h14.041c4.224 0 7.305 1.215 9.242 3.432 1.937 2.217 2.519 5.364 1.747 9.337-.319 1.637-.856 3.159-1.614 4.515a15.118 15.118 0 01-2.971 3.748zM42.502 55.758h-5.01l-2.538 12.644h4.345c2.789 0 4.882-.465 6.282-1.394 1.399-.929 2.378-2.675 2.938-5.239.554-2.53.201-4.167-1.058-4.913-1.26-.746-3.011-1.098-4.959-1.098zm56.725 0h-5.01l-2.538 12.644h4.345c2.789 0 4.882-.465 6.282-1.394 1.399-.929 2.378-2.675 2.938-5.239.554-2.53.201-4.167-1.058-4.913-1.26-.746-3.012-1.098-4.959-1.098z"/></svg>`,

    laravel: `<svg viewBox="0 0 128 128" fill="currentColor"><path d="M27.271 54.553L1.142 93.399c-1.227 1.834-.95 4.29.658 5.778l20.453 18.987c1.476 1.371 3.72 1.548 5.385.426l49.742-33.604c.781-.527 1.331-1.316 1.545-2.22l.039.007 9.456-40.252c.222-.946.055-1.939-.47-2.782l-.005-.009c-.006-.009-.009-.018-.015-.026L63.078 1.638C61.921-.089 59.411-.56 57.704.614l-20.37 13.924c-1.003.688-1.618 1.806-1.673 3.008L34.735 35.27l-5.243 3.588-.098-.068-2.123 15.763zM50.431 36.835l-9.397 6.25 2.193-15.94c.092-.669.448-1.272.989-1.644l9.854-6.685-3.639 18.019zM16.578 24.684c-.153-.102-.317-.181-.478-.262.107-.09.213-.181.321-.271l10.072-6.85c.1-.069.21-.12.31-.188.137.136.28.266.397.424l4.543 6.104-1.498 10.91-14.367-9.534c.252-.115.49-.221.7-.333zM32.364 75.843l-16.575-10.79c-.373-.243-.692-.557-.94-.923l-1.397-2.08.09-.002-.144-.206c-.139-.2-.257-.413-.348-.641l1.912-12.94c.117-.242.261-.47.437-.677l.093-.119 14.197 8.836-2.3 10.816 3.37-1.63 14.403 10.082-7.103 4.75-5.695-5.476zm68.43-22.063l-7.06 30.03L48.403 113.56l-18.733 12.5a.594.594 0 01-.32.1c-.072 0-.141-.016-.203-.05a.376.376 0 01-.187-.312l-.262-22.197 14.237-9.684 7.365 7.082c.875.839 2.165 1.085 3.295.627l70.693-28.666c-1.182-3.637-12.518-8.87-23.895 2.844zm21.893 2.07L55.303 83.792l-5.68-5.602 35.165-23.694c4.178-2.816 14.356-6.896 19.948-4.656.49.294.891.682 1.153 1.16l2.148 3.199c.477.712.718 1.554.718 2.409 0 .816-.239 1.638-.718 2.362l-3.144 4.695c4.309 2.072 11.365 7.134 14.39 18.19z"/></svg>`,

    octane: `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M13 2L4.093 12.688l4.53 1.86L5.907 22l8.907-10.688-4.53-1.86L13 2z"/></svg>`,

    reverb: `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8z"/><path d="M12 6c-3.31 0-6 2.69-6 6s2.69 6 6 6 6-2.69 6-6-2.69-6-6-6zm0 10c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4-1.79 4-4 4z"/><circle cx="12" cy="12" r="2"/></svg>`,

    sanctum: `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11V11.99z"/></svg>`,

    spatie: `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM9 6c0-1.66 1.34-3 3-3s3 1.34 3 3v2H9V6zm9 14H6V10h12v10zm-6-3c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2z"/></svg>`,

    mysql: `<svg viewBox="0 0 128 128" fill="currentColor"><path d="M116.948 97.807c-6.863-.187-12.104.452-16.585 2.341-1.273.537-3.305.552-3.513 2.147.7.733.808 1.829 1.365 2.731 1.07 1.73 2.876 4.052 4.488 5.268 1.762 1.33 3.577 2.751 5.465 3.902 3.358 2.047 7.107 3.217 10.34 5.268 1.906 1.21 3.799 2.733 5.658 4.097.92.675 1.537 1.724 2.732 2.147v-.194c-.628-.79-.791-1.874-1.365-2.726l-2.537-2.537c-2.48-3.292-5.629-6.184-8.976-8.585-2.669-1.916-8.642-4.504-9.755-7.609l-.195-.195c1.892-.214 4.107-.898 5.854-1.367 2.934-.786 5.556-.583 8.585-1.365l4.097-1.171v-.78c-1.531-1.571-2.623-3.651-4.292-5.073-4.37-3.72-9.138-7.437-14.048-10.537-2.724-1.718-6.089-2.835-8.976-4.292-.971-.491-2.677-.746-3.318-1.562-1.517-1.932-2.342-4.382-3.511-6.631-2.449-4.717-4.854-9.868-7.024-14.831-1.48-3.384-2.447-6.72-4.292-9.756-8.86-14.567-18.396-23.358-33.169-32-3.144-1.838-6.929-2.563-10.929-3.513l-6.438-.389c-1.303-.56-2.669-2.2-3.902-2.927C24.476 6.797 8.571-2.828 2.894 8.625c-3.586 7.238 5.36 14.323 8.585 17.974 2.26 2.56 5.139 5.428 6.824 8.391.929 1.633 1.096 3.298 1.751 5.076 1.619 4.394 3.028 9.164 5.072 13.273 1.039 2.089 2.179 4.279 3.513 6.243.778 1.145 2.117 1.649 2.341 3.513-1.31 1.836-1.387 4.684-2.142 7.024-3.398 10.549-2.118 23.635 2.731 31.42 1.49 2.392 4.998 7.523 9.756 5.564 4.16-1.714 3.23-7.023 4.292-11.726.241-1.067.089-1.851.585-2.537v.194l3.513 7.024c2.6 4.187 7.212 8.562 11.122 11.529 2.029 1.541 3.63 4.213 6.244 5.073v-.196h-.194c-.508-.791-1.303-1.119-1.951-1.755-1.527-1.497-3.225-3.358-4.487-5.073-3.556-4.827-6.698-10.122-9.561-15.613-1.402-2.686-2.622-5.651-3.707-8.391-.417-1.051-.411-2.635-1.171-3.123-1.082 1.674-2.685 3.04-3.513 5.073-1.327 3.259-1.501 7.235-1.951 11.335-.285.028-.159.009-.39.194-2.937-.716-3.957-3.789-5.073-6.437-2.818-6.693-3.345-17.49-0.878-25.233.641-2.003 3.526-8.312 2.341-10.145-.553-1.74-2.366-2.748-3.318-4.097-1.184-1.679-2.367-3.885-3.123-5.854-1.97-5.126-2.896-10.867-5.073-15.998-1.04-2.452-2.8-4.936-4.292-7.218-1.647-2.52-3.491-4.375-4.878-7.218-.484-.991-1.138-2.572-.39-3.707.236-.762.558-.916 1.171-1.171 1.047-.857 3.964.271 5.072.778 2.944 1.346 5.396 2.632 7.804 4.487 1.154.888 2.316 2.609 3.707 3.123h1.562c2.446.564 5.181.194 7.413.778 3.942 1.03 7.472 2.628 10.731 4.488 9.922 5.661 18.009 13.717 23.478 23.349 0.879 1.549 1.263 3.027 2.146 4.682 1.789 3.347 3.819 6.766 5.464 10.146 1.687 3.468 3.332 6.942 5.659 9.756 1.222 1.476 5.928 2.267 8.004 3.123 1.458.6 3.828 1.222 5.073 2.146 2.385 1.77 4.681 3.902 6.827 5.855 1.073.977 4.39 3.126 4.683 4.682zM29.14 14.57c-1.47.008-2.512.252-3.707.585v.194h.194c.721 1.478 1.993 2.439 2.927 3.707l2.146 4.487.194-.195c1.326-.929 1.933-2.417 1.951-4.682-.537-.562-.618-1.263-1.171-1.951-.593-.748-1.748-1.169-2.534-2.145z"/></svg>`,

    vue: `<svg viewBox="0 0 128 128" fill="currentColor"><path d="M0 8.934l49.854.158 14.167 24.47 14.432-24.47L128 8.935l-63.834 110.14zm126.98.637l-24.36.02-38.476 66.053L25.691 9.592.942 9.572l63.211 107.89zm-25.149-.008l-22.745.168-15.053 24.647L49.216 9.73l-22.794-.04 37.731 64.476z" style="fill: #42b883;"/><path d="M25.997 9.393l23.002.023L63.063 34.22 78.68 9.41l23.09.006-38.6 65.067z" style="fill: #35495e;"/></svg>`,

    vite: `<svg viewBox="0 0 128 128" fill="none"><defs><linearGradient id="vg1" x1="6" y1="33" x2="235" y2="344" gradientUnits="userSpaceOnUse" gradientTransform="scale(.4)"><stop stop-color="#41D1FF"/><stop offset="1" stop-color="#BD34FE"/></linearGradient><linearGradient id="vg2" x1="194.651" y1="8.818" x2="236.076" y2="292.989" gradientUnits="userSpaceOnUse" gradientTransform="scale(.4)"><stop stop-color="#FFBD4F"/><stop offset="1" stop-color="#FF9922"/></linearGradient></defs><path d="M124.766 19.52l-55.09 98.178a3.685 3.685 0 01-6.406-.072L6.53 19.47c-1.49-2.756.702-6.032 3.82-5.707l56.142 5.844c.388.04.78.035 1.168-.016l54.744-7.14c3.096-.404 5.455 2.82 3.927 5.54l-1.564 1.53z" fill="url(#vg1)"/><path d="M91.46 1.43l-40.753 7.14c-.76.133-1.333.76-1.385 1.531l-4.44 61.254c-.076 1.047.866 1.903 1.905 1.731l10.857-1.795c1.155-.191 2.126.864 1.83 1.989l-4.123 15.669c-.318 1.209.846 2.283 2.05 1.892l7.674-2.49c1.208-.392 2.372.69 2.046 1.902l-6.561 24.38c-.465 1.725 1.802 2.755 2.703 1.227L64.535 112l32.3-63.83c.574-1.136-.46-2.427-1.702-2.124l-11.3 2.756c-1.217.297-2.228-.952-1.746-2.157l8.85-22.12c.48-1.2-.52-2.443-1.733-2.154l-.001.001z" fill="url(#vg2)"/></svg>`,

    tailwind: `<svg viewBox="0 0 128 128" fill="currentColor"><path d="M64.004 25.602c-17.067 0-27.73 8.53-32 25.597 6.398-8.531 13.867-11.73 22.398-9.597 4.871 1.214 8.352 4.746 12.207 8.66C72.883 56.629 80.145 64 96.004 64c17.066 0 27.73-8.531 32-25.602-6.399 8.536-13.868 11.735-22.399 9.602-4.87-1.215-8.347-4.746-12.207-8.66-6.27-6.367-13.53-13.738-29.394-13.738zM32.004 64c-17.066 0-27.73 8.531-32 25.602C6.402 81.066 13.871 77.867 22.402 80c4.871 1.215 8.352 4.746 12.207 8.66 6.274 6.367 13.536 13.738 29.395 13.738 17.066 0 27.73-8.53 32-25.597-6.399 8.531-13.868 11.73-22.399 9.597-4.87-1.214-8.347-4.746-12.207-8.66C55.128 71.371 47.868 64 32.004 64z" style="fill: #38bdf8;"/></svg>`,

    pinia: `<svg viewBox="0 0 128 128" fill="currentColor"><path d="M56.522 4.463C53.633-.295 46.367-.295 43.478 4.463L25.085 36.327a7 7 0 006.043 10.507H68.87a7 7 0 006.044-10.507L56.522 4.463z" style="fill: #ffd859;"/><path d="M86.808 15.163C83.92 10.406 76.653 10.406 73.764 15.163L55.372 47.027a7 7 0 006.043 10.507h37.743a7 7 0 006.044-10.507L86.808 15.163z" style="fill: #42b883;"/><path d="M47.306 99.537c0 9.39-7.613 17.002-17.003 17.002-9.39 0-17.003-7.613-17.003-17.002V77.535h34.006v22.002z" style="fill: #ffd859;"/><path d="M107.7 99.537c0 9.39-7.613 17.002-17.003 17.002-9.39 0-17.002-7.613-17.002-17.002V77.535H107.7v22.002z" style="fill: #42b883;"/><circle cx="30.303" cy="66.534" r="8" style="fill: #ffd859;"/><circle cx="90.697" cy="66.534" r="8" style="fill: #42b883;"/></svg>`,

    axios: `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M11.068 2.9L5.2 19.1h2.7l1.1-3.2h5.9l1.1 3.2h2.7L12.932 2.9h-1.864zM12 7.8l2.1 6.1H9.9L12 7.8z" style="fill: #5A29E4;"/></svg>`,
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

const countdownWidth = computed(() => {
    return ((REFRESH_INTERVAL / 1000 - countdown.value) / (REFRESH_INTERVAL / 1000)) * 100;
});

onMounted(() => {
    fetchStatus();
    autoRefreshInterval = setInterval(fetchStatus, REFRESH_INTERVAL);
    countdownInterval = setInterval(() => {
        countdown.value = Math.max(0, countdown.value - 1);
    }, 1000);
});

onUnmounted(() => {
    if (autoRefreshInterval) clearInterval(autoRefreshInterval);
    if (countdownInterval) clearInterval(countdownInterval);
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

                <!-- Refresh countdown -->
                <div class="relative">
                    <button @click="fetchStatus" :disabled="isLoading"
                        class="p-2.5 text-text-secondary hover:text-primary-500 hover:bg-primary-500/10 rounded-xl transition-all relative">
                        <RefreshCw :size="20" :class="{ 'animate-spin': isLoading }" />
                    </button>
                    <span
                        class="absolute -bottom-1 left-1/2 -translate-x-1/2 text-[9px] font-mono text-text-secondary tabular-nums">
                        {{ countdown }}s
                    </span>
                </div>
            </div>
        </div>

        <!-- Realtime refresh progress bar -->
        <div class="w-full h-0.5 bg-surface-800 rounded-full overflow-hidden -mt-3">
            <div class="h-full bg-primary-500/60 transition-all duration-1000 ease-linear rounded-full"
                :style="{ width: countdownWidth + '%' }">
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
            <!-- Security Center -->
            <div class="bg-surface-800 rounded-2xl border border-surface-700 overflow-hidden mb-6">
                <div
                    class="p-5 border-b border-surface-700/50 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div class="flex items-center gap-3">
                        <div class="p-2 rounded-lg bg-red-500/10">
                            <ShieldAlert :size="20" class="text-red-400" />
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-text-primary flex items-center gap-2">
                                Security Center
                                <span v-if="data.security.threat_level === 'critical'"
                                    class="animate-pulse px-2 py-0.5 rounded text-[10px] bg-red-500 text-white font-bold uppercase">CRITICAL
                                    THREAT</span>
                            </h2>
                            <p class="text-xs text-text-secondary">Monitoring serangan & proteksi server</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg border transition-colors" :class="data.security.defender_active
                            ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400'
                            : 'bg-surface-700/50 border-surface-600 text-text-secondary'">
                            <ShieldCheck v-if="data.security.defender_active" :size="14" />
                            <Shield v-else :size="14" />
                            <span class="text-xs font-bold">{{ data.security.defender_active ? 'DEFENDER AKTIF' :
                                'DEFENDER MATI' }}</span>
                        </div>
                        <button @click="toggleDefender" :disabled="isLoading"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold border transition-all hover:brightness-110"
                            :class="data.security.defender_active
                                ? 'bg-red-500/10 border-red-500/20 text-red-400 hover:bg-red-500/20'
                                : 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400 hover:bg-emerald-500/20'">
                            {{ data.security.defender_active ? 'Matikan' : 'Aktifkan' }}
                        </button>
                    </div>
                </div>

                <!-- Alerts Banner -->
                <div v-if="data.security.alerts.length > 0" class="px-5 pt-5 space-y-2">
                    <div v-for="(alert, idx) in data.security.alerts" :key="idx"
                        class="p-3 rounded-xl border text-sm flex items-center gap-3" :class="{
                            'bg-red-500/10 border-red-500/20 text-red-400': alert.level === 'critical',
                            'bg-orange-500/10 border-orange-500/20 text-orange-400': alert.level === 'high' || alert.level === 'warning',
                            'bg-blue-500/10 border-blue-500/20 text-blue-400': alert.level === 'info',
                            'bg-emerald-500/10 border-emerald-500/20 text-emerald-400': alert.level === 'success'
                        }">
                        <AlertTriangle v-if="alert.level === 'critical' || alert.level === 'warning'" :size="18"
                            class="shrink-0" />
                        <ShieldCheck v-else-if="alert.level === 'success'" :size="18" class="shrink-0" />
                        <Activity v-else :size="18" class="shrink-0" />
                        <div>
                            <span class="font-bold">{{ alert.message }}</span>
                            <span class="block text-[10px] opacity-70">{{ new
                                Date(alert.time).toLocaleTimeString('id-ID') }}</span>
                        </div>
                    </div>
                </div>

                <div class="p-5 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Threat Level -->
                    <div class="bg-surface-900/50 rounded-xl p-4 border border-surface-700/50">
                        <p class="text-[10px] uppercase text-text-secondary font-semibold mb-1">Threat Level</p>
                        <div class="flex items-center gap-2">
                            <div class="h-2 w-full rounded-full bg-surface-700 overflow-hidden">
                                <div class="h-full transition-all duration-500" :class="{
                                    'w-1/4 bg-emerald-500': data.security.threat_level === 'low',
                                    'w-2/4 bg-amber-500': data.security.threat_level === 'medium',
                                    'w-3/4 bg-orange-500': data.security.threat_level === 'high',
                                    'w-full bg-red-500 animate-pulse': data.security.threat_level === 'critical'
                                }"></div>
                            </div>
                            <span class="text-xs font-bold uppercase" :class="{
                                'text-emerald-400': data.security.threat_level === 'low',
                                'text-amber-400': data.security.threat_level === 'medium',
                                'text-orange-400': data.security.threat_level === 'high',
                                'text-red-400': data.security.threat_level === 'critical'
                            }">{{ data.security.threat_level }}</span>
                        </div>
                    </div>

                    <!-- Failed Logins -->
                    <div class="bg-surface-900/50 rounded-xl p-4 border border-surface-700/50">
                        <p class="text-[10px] uppercase text-text-secondary font-semibold mb-1">Gagal Login (1 Jam)</p>
                        <p class="text-lg font-bold"
                            :class="data.security.failed_logins_1h > 5 ? 'text-red-400' : 'text-text-primary'">
                            {{ data.security.failed_logins_1h }} <span
                                class="text-xs font-normal text-text-secondary">percobaan</span>
                        </p>
                    </div>

                    <!-- Firewall Status -->
                    <div class="bg-surface-900/50 rounded-xl p-4 border border-surface-700/50">
                        <p class="text-[10px] uppercase text-text-secondary font-semibold mb-1">Firewall / Fail2Ban</p>
                        <div class="flex gap-2">
                            <span class="px-2 py-0.5 rounded text-[10px] border"
                                :class="data.security.firewall_active ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400' : 'bg-red-500/10 border-red-500/20 text-red-400'">
                                {{ data.security.firewall_active ? 'FW ON' : 'FW OFF' }}
                            </span>
                            <span class="px-2 py-0.5 rounded text-[10px] border"
                                :class="data.security.fail2ban_active ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400' : 'bg-red-500/10 border-red-500/20 text-red-400'">
                                {{ data.security.fail2ban_active ? 'F2B ON' : 'F2B OFF' }}
                            </span>
                        </div>
                    </div>

                    <!-- Last Attack -->
                    <div class="bg-surface-900/50 rounded-xl p-4 border border-surface-700/50">
                        <p class="text-[10px] uppercase text-text-secondary font-semibold mb-1">Serangan Terakhir</p>
                        <p class="text-xs font-mono text-text-primary truncate">
                            {{ data.security.last_attack_time || 'Belum ada data' }}
                        </p>
                    </div>
                </div>

                <!-- Alerts & Lists -->
                <div class="grid grid-cols-1 lg:grid-cols-2 border-t border-surface-700/50">
                    <!-- Alerts / Recent Attacks -->
                    <div class="p-5 border-b lg:border-b-0 lg:border-r border-surface-700/50">
                        <h3 class="text-sm font-bold text-text-primary mb-3 flex items-center gap-2">
                            <Activity :size="16" class="text-amber-400" />
                            Aktivitas Mencurigakan (Top 10)
                        </h3>
                        <div v-if="data.security.recent_attacks.length === 0"
                            class="text-center py-6 text-text-secondary text-xs italic">
                            Tidak ada aktivitas mencurigakan baru-baru ini.
                        </div>
                        <div v-else class="space-y-2">
                            <div v-for="(attack, idx) in data.security.recent_attacks" :key="idx"
                                class="flex items-center justify-between p-2 rounded bg-surface-900/30 border border-surface-700/30 text-xs">
                                <div>
                                    <div class="font-mono font-bold text-red-300">{{ attack.ip }}</div>
                                    <div class="text-[10px] text-text-secondary">{{ attack.attempts }}x via {{
                                        attack.service }} • {{ attack.last_attempt }}</div>
                                </div>
                                <button v-if="!attack.auto_blocked" @click="blockIp(attack.ip)" :disabled="isBlocking"
                                    class="px-2 py-1 bg-red-500/10 hover:bg-red-500/20 text-red-400 rounded border border-red-500/20 transition-colors">
                                    Block
                                </button>
                                <span v-else
                                    class="px-2 py-1 bg-surface-800 text-text-secondary rounded border border-surface-700 text-[10px]">
                                    Blocked
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Blocked IPs -->
                    <div class="p-5">
                        <h3 class="text-sm font-bold text-text-primary mb-3 flex items-center gap-2">
                            <Ban :size="16" class="text-red-400" />
                            IP Terblokir ({{ data.security.blocked_ips.length }})
                        </h3>
                        <div v-if="data.security.blocked_ips.length === 0"
                            class="text-center py-6 text-text-secondary text-xs italic">
                            Belum ada IP yang diblokir.
                        </div>
                        <div v-else class="space-y-2 max-h-60 overflow-y-auto pr-1 custom-scrollbar">
                            <div v-for="(blocked, idx) in data.security.blocked_ips" :key="idx"
                                class="flex items-center justify-between p-2 rounded bg-surface-900/30 border border-surface-700/30 text-xs">
                                <div>
                                    <div class="font-mono font-bold text-red-300">{{ blocked.ip }}</div>
                                    <div class="text-[10px] text-text-secondary">Source: {{ blocked.source }} {{
                                        blocked.jail ? `(${blocked.jail})` : '' }}</div>
                                </div>
                                <button @click="unblockIp(blocked.ip)" :disabled="isBlocking"
                                    class="px-2 py-1 bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 rounded border border-emerald-500/20 transition-colors">
                                    Unblock
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

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
                            <!-- Brand SVG Icon -->
                            <div class="w-9 h-9 rounded-lg shrink-0 flex items-center justify-center p-1.5"
                                :style="{ backgroundColor: tech.color + '15' }">
                                <div class="w-full h-full" v-html="brandSvgs[tech.icon] || ''"></div>
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
                            <div class="w-[18px] h-[18px]" v-html="brandSvgs.php" style="color: #777BB4;"></div>
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
                            <div class="w-[18px] h-[18px]" v-html="brandSvgs.mysql" style="color: #00758F;"></div>
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
                                <div class="w-[14px] h-[14px]" v-html="brandSvgs.mysql" style="color: #00758F;"></div>
                                Database
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
                                <div class="w-[14px] h-[14px]" v-html="brandSvgs.laravel" style="color: #FF2D20;"></div>
                                Laravel
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
