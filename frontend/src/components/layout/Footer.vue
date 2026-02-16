<script setup>
import { Instagram, Github, Mail, Globe, Cpu, ShieldCheck, Zap } from 'lucide-vue-next';
import { useRouter } from 'vue-router';

const router = useRouter();

const currentYear = new Date().getFullYear();

const navLinks = [
    { label: 'Dashboard', path: '/' },
    { label: 'Kasir (POS)', path: '/pos' },
    { label: 'Inventory', path: '/inventory' },
    { label: 'Lacak Barang', path: '/track' },
];

const goTo = (path) => {
    router.push(path);
};
</script>

<template>
    <footer
        class="relative mt-auto py-8 border-t border-surface-700/50 bg-surface-900/50 backdrop-blur-xl overflow-hidden">
        <!-- Abstract Background Glows -->
        <div
            class="absolute top-0 right-0 -translate-y-1/2 translate-x-1/2 w-80 h-80 bg-primary-500/[0.05] blur-[100px] rounded-full pointer-events-none">
        </div>
        <div
            class="absolute bottom-0 left-0 translate-y-1/2 -translate-x-1/2 w-80 h-80 bg-indigo-500/[0.05] blur-[100px] rounded-full pointer-events-none">
        </div>

        <div class="container mx-auto px-6 relative z-10">
            <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                <!-- App Identity -->
                <div class="flex items-center gap-3">
                    <div
                        class="w-8 h-8 rounded-lg bg-primary-600 flex items-center justify-center shadow-lg shadow-primary-600/20">
                        <Zap :size="16" class="text-white" fill="white" />
                    </div>
                    <span class="text-lg font-bold text-white tracking-tight">APEX<span
                            class="text-primary-500">POS</span></span>
                    <span class="hidden sm:inline text-xs text-text-secondary/40 ml-2 font-mono">v2.4.0</span>
                </div>

                <!-- Creator & Social -->
                <div class="flex flex-col sm:flex-row items-center gap-4 sm:gap-8">
                    <div class="flex items-center gap-2 text-sm text-text-secondary">
                        <span>Dibuat oleh</span>
                        <span class="font-bold text-white">Bian</span>
                    </div>

                    <a href="https://instagram.com/mcisreal_" target="_blank"
                        class="flex items-center gap-2 px-4 py-2 rounded-full bg-surface-800/80 border border-surface-700 hover:border-primary-500/50 hover:bg-surface-700 transition-all group">
                        <Instagram :size="16"
                            class="text-text-secondary group-hover:text-primary-400 transition-colors" />
                        <span
                            class="text-xs font-bold text-text-secondary group-hover:text-white transition-colors">@mcisreal_</span>
                    </a>
                </div>

                <!-- Copyright -->
                <div class="text-[11px] text-text-secondary/50">
                    &copy; {{ currentYear }} APEXPOS. Hak Cipta Dilindungi.
                </div>
            </div>
        </div>
    </footer>
</template>

<style scoped>
/* Any specific local styles if needed */
</style>
