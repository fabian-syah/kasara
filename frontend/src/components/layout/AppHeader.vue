<script setup>
import { computed, ref } from "vue";
import { useAuthStore } from "../../store/auth";
import { useThemeStore } from "../../store/theme";
import { getRoleLabel } from "../../utils/permissions";
import {
    Menu,
    Search,
    Palette,
    Sun,
    Moon,
    Bell,
    X,
    Settings,
    ChevronDown,
    LogOut,
    User,
    Type
} from "lucide-vue-next";
import { useRouter } from "vue-router";
import AnimatedThemeToggle from "../common/AnimatedThemeToggle.vue";

const emit = defineEmits(['toggle-mobile-menu', 'toggle-sidebar']);
const router = useRouter();

const authStore = useAuthStore();
const themeStore = useThemeStore();

const isUserMenuOpen = ref(false);

function closeMenus() {
    isUserMenuOpen.value = false;
}

async function handleLogout() {
    await authStore.logout();
    router.push("/login");
}

// User info
const userName = computed(() => authStore.userName);
const userRole = computed(() => getRoleLabel(authStore.userRole));
</script>

<template>
    <header
        class="sticky top-0 z-[50] flex items-center justify-between h-[64px] lg:h-[72px] px-4 lg:px-8 bg-white dark:bg-[#0d0d0d] border-b border-neutral-200/50 dark:border-neutral-800/60 shadow-sm">
        <!-- Left Side: Hamburger & Toggle & Search -->
        <div class="flex items-center gap-3 flex-1">
            <!-- Mobile hamburger -->
            <button @click="emit('toggle-mobile-menu')" aria-label="Buka Menu"
                class="lg:hidden p-2 text-text-secondary hover:text-text-primary rounded-lg hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors">
                <Menu :size="22" />
            </button>

            <!-- Desktop sidebar toggle -->
            <button @click="emit('toggle-sidebar')" aria-label="Toggle Sidebar"
                class="hidden lg:flex p-2 text-text-secondary hover:text-text-primary rounded-lg hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <line x1="3" y1="7" x2="21" y2="7"></line>
                    <line x1="3" y1="12" x2="15" y2="12"></line>
                    <line x1="3" y1="17" x2="21" y2="17"></line>
                </svg>
            </button>

            <!-- Search -->
            <div class="relative w-full max-w-[200px] sm:max-w-sm lg:max-w-md group">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary" :size="18" />
                <input type="text" placeholder="Cari..." autocomplete="off"
                    class="w-full bg-surface-50 dark:bg-surface-800 border border-surface-200 dark:border-surface-700 rounded-lg py-2.5 pl-10 pr-12 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500 transition-all placeholder:text-text-secondary" />
                <span
                    class="absolute right-3 top-1/2 -translate-y-1/2 hidden sm:flex items-center gap-1 text-[11px] text-text-secondary font-mono bg-surface-200 dark:bg-surface-700 px-1.5 py-0.5 rounded">
                    ⌘K
                </span>
            </div>
        </div>

        <!-- Right Side -->
        <div class="flex items-center gap-1 sm:gap-2">

            <!-- Dark mode quick toggle -->
            <div class="hidden md:block mr-2">
                <AnimatedThemeToggle />
            </div>

            <!-- Notifications -->
            <button aria-label="Notifikasi"
                class="relative p-2.5 text-text-secondary hover:text-text-primary transition-colors rounded-full hover:bg-surface-100 dark:hover:bg-surface-800">
                <Bell :size="20" />
                <span class="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full border-2 border-surface-950"></span>
            </button>

            <!-- Separator -->
            <div class="h-8 w-px bg-surface-200 dark:bg-surface-700 mx-1"></div>

            <!-- User Avatar & Dropdown -->
            <div class="relative">
                <button @click="isUserMenuOpen = !isUserMenuOpen"
                    class="flex items-center gap-3 hover:bg-surface-100 dark:hover:bg-surface-800 p-1.5 rounded-lg transition-colors focus:outline-none">
                    <div
                        class="w-10 h-10 rounded-full overflow-hidden border-2 border-surface-200 dark:border-surface-700 shadow-sm">
                        <img :src="authStore.userPhotoUrl
                            ? authStore.userPhotoUrl
                            : `https://ui-avatars.com/api/?name=${encodeURIComponent(userName)}&background=${themeStore.isDark ? '3b82f6' : '0f172a'}&color=fff&size=128`"
                            class="w-full h-full object-cover" :alt="userName"
                            @error="(e) => e.target.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(userName)}&background=${themeStore.isDark ? '3b82f6' : '0f172a'}&color=fff&size=128`" />
                    </div>
                    <div class="text-left hidden sm:block">
                        <p class="text-sm font-semibold text-text-primary leading-none">
                            {{ userName }}
                        </p>
                        <p class="text-[11px] text-text-secondary font-medium mt-0.5">
                            {{ userRole }}
                        </p>
                    </div>
                    <ChevronDown :size="16"
                        class="text-text-secondary transition-transform duration-200 hidden sm:block"
                        :class="{ 'rotate-180': isUserMenuOpen }" />
                </button>

                <!-- Dropdown -->
                <transition enter-active-class="transition duration-100 ease-out"
                    enter-from-class="transform scale-95 opacity-0" enter-to-class="transform scale-100 opacity-100"
                    leave-active-class="transition duration-75 ease-in"
                    leave-from-class="transform scale-100 opacity-100" leave-to-class="transform scale-95 opacity-0">
                    <div v-if="isUserMenuOpen"
                        class="absolute right-0 top-full mt-2 w-56 bg-white dark:bg-surface-900 border border-neutral-200 dark:border-surface-700 rounded-xl shadow-xl z-50 overflow-hidden">

                        <!-- Mobile User Info in Dropdown -->
                        <div
                            class="block sm:hidden px-4 py-3 border-b border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800">
                            <p class="text-sm font-semibold text-text-primary truncate">{{ userName }}</p>
                            <p class="text-xs text-text-secondary truncate">{{ authStore.user?.email }}</p>
                        </div>

                        <div class="p-1">
                            <router-link to="/settings" @click="isUserMenuOpen = false"
                                class="flex items-center gap-3 px-3 py-2 text-sm font-medium text-text-secondary hover:text-text-primary hover:bg-surface-100 dark:hover:bg-surface-800 rounded-lg transition-colors">
                                <Settings :size="16" />
                                <span>Pengaturan Profil</span>
                            </router-link>
                            <button @click="themeStore.toggleDarkMode"
                                class="w-full flex items-center gap-3 px-3 py-2 text-sm font-medium text-text-secondary hover:text-text-primary hover:bg-surface-100 dark:hover:bg-surface-800 rounded-lg transition-colors">
                                <component :is="themeStore.isDark ? Sun : Moon" :size="16" />
                                <span>{{ themeStore.isDark ? 'Mode Terang' : 'Mode Gelap' }}</span>
                            </button>
                        </div>

                        <div class="h-px bg-surface-200 dark:bg-surface-700 my-1"></div>

                        <!-- Font Size Selector -->
                        <div class="px-4 py-2">
                            <div class="flex items-center gap-2 mb-2">
                                <Type :size="14" class="text-text-secondary" />
                                <p class="text-[10px] font-bold text-text-secondary uppercase tracking-widest">Ukuran Font</p>
                            </div>
                            <div class="flex bg-surface-100 dark:bg-surface-800 p-1 rounded-lg gap-1">
                                <button v-for="size in ['small', 'standard', 'big']" :key="size" 
                                    @click.stop.prevent="themeStore.setFontSize(size)"
                                    class="flex-1 py-1 rounded-md text-[10px] font-bold uppercase transition-all duration-200"
                                    :class="themeStore.fontSize === size 
                                        ? 'bg-primary-500 text-white shadow-sm' 
                                        : 'text-text-secondary hover:text-text-primary hover:bg-surface-200 dark:hover:bg-surface-700'">
                                    {{ size }}
                                </button>
                            </div>
                        </div>

                        <div class="h-px bg-surface-200 dark:bg-surface-700 my-1"></div>

                        <div class="p-1">
                            <button @click="handleLogout"
                                class="flex w-full items-center gap-3 px-3 py-2 text-sm font-medium text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 rounded-lg transition-colors">
                                <LogOut :size="16" />
                                <span>Keluar</span>
                            </button>
                        </div>
                    </div>
                </transition>
            </div>

            <!-- Overlay for closing dropdown -->
            <div v-if="isUserMenuOpen" @click="closeMenus" class="fixed inset-0 z-40 bg-transparent">
            </div>
        </div>
    </header>
</template>
