<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { RefreshCw, AlertCircle } from 'lucide-vue-next';

const hasUpdate = ref(false);
const currentVersion = ref(null);
const checkInterval = ref(null);
const isChecking = ref(false);

const CHECK_INTERVAL_MS = 60 * 1000; // Check every 1 minute

const checkVersion = async () => {
    if (isChecking.value) return;
    isChecking.value = true;

    try {
        // Add timestamp to prevent caching
        const response = await fetch(`/version.json?t=${new Date().getTime()}`);
        if (!response.ok) throw new Error('Failed to fetch version');

        const data = await response.json();

        // Initial load
        if (!currentVersion.value) {
            currentVersion.value = data.buildTime;
            return;
        }

        // Check for mismatch
        if (currentVersion.value !== data.buildTime) {
            hasUpdate.value = true;
            clearInterval(checkInterval.value); // Stop checking once update found
        }
    } catch (error) {
        // Silent error for periodic version checks to avoid cluttering console 
        // especially during network changes/drops
    } finally {
        isChecking.value = false;
    }
};

const refreshPage = () => {
    window.location.reload(true);
};

onMounted(() => {
    checkVersion();
    checkInterval.value = setInterval(checkVersion, CHECK_INTERVAL_MS);
});

onUnmounted(() => {
    if (checkInterval.value) clearInterval(checkInterval.value);
});
</script>

<template>
    <div v-if="hasUpdate" class="fixed bottom-4 right-4 z-[9999] animate-bounce-in">
        <div
            class="bg-surface-800 border border-primary-500/50 shadow-lg shadow-primary-500/20 rounded-xl p-4 flex items-center gap-4 max-w-sm">
            <div class="w-10 h-10 rounded-full bg-primary-500/20 flex items-center justify-center shrink-0">
                <AlertCircle class="text-primary-500" :size="24" />
            </div>

            <div class="flex-1">
                <h3 class="font-bold text-text-primary text-sm">Update Tersedia!</h3>
                <p class="text-xs text-text-secondary mt-0.5">Versi terbaru aplikasi telah dirilis.</p>
            </div>

            <button @click="refreshPage" class="btn btn-primary text-xs px-3 py-2 shrink-0 flex items-center gap-1.5">
                <RefreshCw :size="14" />
                Refresh
            </button>
        </div>
    </div>
</template>

<style scoped>
.animate-bounce-in {
    animation: bounceIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
}

@keyframes bounceIn {
    0% {
        opacity: 0;
        transform: translateY(20px) scale(0.9);
    }

    100% {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}
</style>
