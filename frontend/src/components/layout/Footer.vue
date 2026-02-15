<template>
    <footer class="w-full py-6 px-6 mt-auto">
        <div class="container mx-auto flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-primary-500/50 animate-pulse"></span>
                <div class="text-[10px] text-surface-500 font-mono tracking-widest uppercase">
                    {{ new Date().getFullYear() }} &copy; APEX POS SYSTEM v2.0
                </div>
            </div>

            <div class="flex items-center gap-1 text-xs text-surface-400">
                <span>Created with</span>
                <span class="text-red-500 animate-pulse">❤️</span>
                <span>by</span>
                <a href="https://instagram.com/mcisreal_" target="_blank" rel="noopener noreferrer"
                    class="ml-1 font-bold bg-gradient-to-r from-primary-400 to-purple-400 bg-clip-text text-transparent hover:scale-105 transition-transform cursor-pointer">
                    Bian
                </a>
            </div>
        </div>
    </footer>
</template>
