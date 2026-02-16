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
        class="relative mt-auto pt-20 pb-10 border-t border-surface-700/80 bg-surface-900/80 backdrop-blur-2xl overflow-hidden shadow-[0_-20px_50px_-20px_rgba(0,0,0,0.5)]">
        <!-- Abstract Background Glows -->
        <div
            class="absolute top-0 right-0 -translate-y-1/2 translate-x-1/2 w-[500px] h-[500px] bg-primary-500/[0.08] blur-[140px] rounded-full pointer-events-none">
        </div>
        <div
            class="absolute bottom-0 left-0 translate-y-1/2 -translate-x-1/2 w-[500px] h-[500px] bg-indigo-500/[0.08] blur-[140px] rounded-full pointer-events-none">
        </div>

        <div class="container mx-auto px-6 relative z-10">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12 mb-16">
                <!-- Brand Section -->
                <div class="space-y-6">
                    <div class="flex items-center gap-3">
                        <div
                            class="w-10 h-10 rounded-xl bg-primary-600 flex items-center justify-center shadow-lg shadow-primary-600/20">
                            <Zap :size="20" class="text-white" fill="white" />
                        </div>
                        <span class="text-xl font-bold text-white tracking-tight">APEX<span
                                class="text-primary-500">POS</span></span>
                    </div>
                    <p class="text-sm text-text-secondary leading-relaxed max-w-xs">
                        Sistem Manajemen Inventaris. Dibangun dengan kecepatan
                        dan kemudahan bagi pengelola toko.
                    </p>
                    <div class="flex items-center gap-2">
                        <div
                            class="flex items-center gap-1 px-2 py-1 rounded bg-emerald-500/10 text-emerald-400 text-[10px] font-bold uppercase tracking-wider border border-emerald-500/20">
                            <ShieldCheck :size="10" /> Secure
                        </div>
                        <div
                            class="flex items-center gap-1 px-2 py-1 rounded bg-blue-500/10 text-blue-400 text-[10px] font-bold uppercase tracking-wider border border-blue-500/20">
                            <Cpu :size="10" /> v2.4.0
                        </div>
                    </div>
                </div>

                <!-- Navigation -->
                <div>
                    <h4
                        class="text-sm font-bold text-white uppercase tracking-widest mb-6 border-l-2 border-primary-500 pl-3">
                        Akses Cepat</h4>
                    <ul class="space-y-3">
                        <li v-for="link in navLinks" :key="link.path">
                            <button @click="goTo(link.path)"
                                class="text-sm text-text-secondary hover:text-primary-400 transition-colors duration-200 flex items-center gap-2 group">
                                <span
                                    class="w-1.5 h-1.5 rounded-full bg-surface-700 group-hover:bg-primary-500 transition-colors"></span>
                                {{ link.label }}
                            </button>
                        </li>
                    </ul>
                </div>

                <!-- Developer Section -->
                <div>
                    <h4
                        class="text-sm font-bold text-white uppercase tracking-widest mb-6 border-l-2 border-primary-500 pl-3">
                        Developer</h4>
                    <div class="space-y-4">
                        <a href="https://instagram.com/mcisreal_" target="_blank"
                            class="flex items-center gap-4 group p-3 rounded-2xl bg-surface-800/50 hover:bg-surface-800 border border-surface-700/50 hover:border-surface-600 transition-all">
                            <div
                                class="w-10 h-10 rounded-xl bg-gradient-to-tr from-yellow-400 via-red-500 to-purple-500 flex items-center justify-center group-hover:scale-110 transition-transform">
                                <Instagram :size="18" class="text-white" />
                            </div>
                            <div>
                                <p class="text-xs text-text-secondary">Created by</p>
                                <p class="text-sm font-bold text-white">@mcisreal_</p>
                            </div>
                        </a>
                        <div class="flex gap-3 px-1">
                            <button
                                class="w-8 h-8 rounded-lg bg-surface-800 flex items-center justify-center text-text-secondary hover:bg-surface-700 hover:text-white transition-all">
                                <Github :size="16" />
                            </button>
                            <button
                                class="w-8 h-8 rounded-lg bg-surface-800 flex items-center justify-center text-text-secondary hover:bg-surface-700 hover:text-white transition-all">
                                <Mail :size="16" />
                            </button>
                            <button
                                class="w-8 h-8 rounded-lg bg-surface-800 flex items-center justify-center text-text-secondary hover:bg-surface-700 hover:text-white transition-all">
                                <Globe :size="16" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Newsletter / Info -->
                <div>
                    <h4
                        class="text-sm font-bold text-white uppercase tracking-widest mb-6 border-l-2 border-primary-500 pl-3">
                        Layanan Kami</h4>
                    <p class="text-sm text-text-secondary italic mb-4">
                        "Memberikan kontrol penuh atas operasional bisnis Anda kapan saja, di mana saja."
                    </p>
                    <div class="p-4 rounded-2xl bg-primary-600/5 border border-primary-500/10">
                        <p class="text-[11px] text-primary-400/80 uppercase font-bold tracking-tighter mb-1">Status
                            Server</p>
                        <div class="flex items-center gap-2">
                            <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
                            <span class="text-xs text-white font-medium">Operasional Normal</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Bar -->
            <div
                class="pt-8 border-t border-surface-700/50 flex flex-col md:flex-row justify-between items-center gap-4">
                <p class="text-xs text-text-secondary">
                    &copy; {{ currentYear }} <span class="text-white font-bold tracking-tight">APEX<span
                            class="text-primary-500">POS</span></span>. Hak Cipta Dilindungi.
                </p>
                <div class="flex items-center gap-6 text-xs text-text-secondary/60">
                    <a href="#" class="hover:text-primary-400 transition-colors">Syarat & Ketentuan</a>
                    <a href="#" class="hover:text-primary-400 transition-colors">Kebijakan Privasi</a>
                </div>
            </div>
        </div>
    </footer>
</template>

<style scoped>
/* Any specific local styles if needed */
</style>
