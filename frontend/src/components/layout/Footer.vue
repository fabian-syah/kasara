<script setup>
import { Instagram } from 'lucide-vue-next';
</script>

<template>
    <footer class="w-full py-4 px-6 mt-auto bg-surface-900/50 backdrop-blur-sm border-t border-surface-800">
        <div class="container mx-auto flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="text-xs text-text-secondary">
                &copy; {{ new Date().getFullYear() }} APEX POS. created by <span
                    class="font-bold text-text-primary">Bian</span>
            </div>

            <a href="https://instagram.com/mcisreal_" target="_blank" rel="noopener noreferrer"
                class="group flex items-center gap-2 px-4 py-1.5 rounded-full bg-surface-800 hover:bg-surface-700 transition-all duration-300 border border-surface-700 hover:border-pink-500/50 hover:shadow-[0_0_15px_rgba(236,72,153,0.3)]">
                <div
                    class="relative flex items-center justify-center w-6 h-6 rounded-full bg-gradient-to-tr from-yellow-400 via-red-500 to-purple-500 group-hover:scale-110 transition-transform duration-300">
                    <Instagram :size="14" class="text-white" />
                </div>
                <span class="text-xs font-medium text-text-secondary group-hover:text-pink-400 transition-colors">
                    @mcisreal_
                </span>
            </a>
        </div>
    </footer>
</template>
