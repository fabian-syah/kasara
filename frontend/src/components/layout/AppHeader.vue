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
    User
} from "lucide-vue-next";
import { useRouter } from "vue-router";

const emit = defineEmits(['toggle-mobile-menu']);
const router = useRouter();

const authStore = useAuthStore();
const themeStore = useThemeStore();

const isThemeMenuOpen = ref(false);
const isUserMenuOpen = ref(false);

function closeMenus() {
    isThemeMenuOpen.value = false;
    isUserMenuOpen.value = false;
}

async function handleLogout() {
    await authStore.logout();
    router.push("/login");
}

// User info
const userName = computed(() => authStore.user?.name || "Guest");
const userRole = computed(() => getRoleLabel(authStore.userRole));
</script>

<template>
    <header class="h-20 flex items-center justify-between px-6 lg:px-8 z-40 transition-colors duration-300 relative">
        <!-- Background Blur/Glass -->
        <div
            class="absolute inset-x-6 top-4 bottom-0 bg-surface-900/80 backdrop-blur-xl border border-white/5 rounded-2xl shadow-lg shadow-black/5 -z-10">
        </div>

        <!-- Left Side: Hamburger & Search -->
        <div class="flex items-center gap-6 flex-1">
            <button @click="emit('toggle-mobile-menu')"
                class="lg:hidden p-2 text-surface-400 hover:text-white transition-colors">
                <Menu :size="24" />
            </button>

            <!-- Search -->
            <div class="relative w-full max-w-md group transition-all duration-300">
                <div
                    class="absolute inset-0 bg-primary-500/20 rounded-full blur-md opacity-0 group-focus-within:opacity-100 transition-opacity duration-500">
                </div>
                <Search
                    class="absolute left-4 top-1/2 -translate-y-1/2 text-surface-400 group-focus-within:text-primary-400 transition-colors"
                    :size="18" />
                <input type="text" placeholder="Cari sesuatu..."
                    class="relative w-full bg-surface-800/50 border border-white/5 rounded-full py-2.5 pl-11 pr-4 text-sm text-white placeholder-surface-500 focus:outline-none focus:bg-surface-800 focus:border-primary-500/50 transition-all font-sans" />
            </div>
        </div>

        <!-- Right Side -->
        <div class="flex items-center gap-3 sm:gap-4">
            <!-- Theme Toggle -->
            <div class="relative">
                <button @click="isThemeMenuOpen = !isThemeMenuOpen"
                    class="p-2.5 text-surface-400 hover:text-white transition-all duration-300 rounded-xl hover:bg-white/5 border border-transparent hover:border-white/5"
                    :class="{ 'bg-white/5 text-white border-white/5': isThemeMenuOpen }">
                    <Palette :size="20" />
                </button>

                <!-- Theme Dropdown -->
                <transition enter-active-class="transition duration-200 ease-out"
                    enter-from-class="transform scale-95 opacity-0 translate-y-2"
                    enter-to-class="transform scale-100 opacity-100 translate-y-0"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="transform scale-100 opacity-100 translate-y-0"
                    leave-to-class="transform scale-95 opacity-0 translate-y-2">
                    <div v-if="isThemeMenuOpen"
                        class="absolute right-0 top-full mt-4 w-72 bg-surface-800/90 backdrop-blur-xl border border-white/10 rounded-2xl shadow-2xl z-50 p-5 overflow-hidden">

                        <!-- Glow Effect -->
                        <div
                            class="absolute -top-10 -right-10 w-32 h-32 bg-primary-500/10 blur-[50px] rounded-full pointer-events-none">
                        </div>

                        <div class="flex items-center justify-between mb-4 relative z-10">
                            <h3 class="font-bold text-white text-sm">
                                Tampilan
                            </h3>
                            <button @click="themeStore.toggleDarkMode"
                                class="p-2 rounded-lg bg-surface-900 border border-white/5 hover:border-primary-500/30 transition-colors text-surface-400 hover:text-white"
                                :title="themeStore.isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode'">
                                <Sun v-if="themeStore.isDark" :size="16" />
                                <Moon v-else :size="16" />
                            </button>
                        </div>

                        <p class="text-[10px] text-surface-400 mb-3 font-bold uppercase tracking-wider relative z-10">
                            Aksen Warna
                        </p>
                        <div class="grid grid-cols-5 gap-3 relative z-10">
                            <button v-for="theme in themeStore.availableThemes" :key="theme.id"
                                @click="themeStore.setTheme(theme.id)"
                                class="w-10 h-10 rounded-xl flex items-center justify-center border-2 transition-all duration-300 hover:scale-110 relative group"
                                :class="themeStore.themeName === theme.id
                                    ? 'border-white ring-2 ring-primary-500/50 ring-offset-2 ring-offset-surface-800 shadow-lg shadow-primary-500/20'
                                    : 'border-transparent opacity-80 hover:opacity-100'
                                    " :style="{ backgroundColor: theme.color }">
                                <div
                                    class="absolute inset-0 bg-white/20 opacity-0 group-hover:opacity-100 rounded-xl transition-opacity">
                                </div>
                            </button>
                        </div>
                    </div>
                </transition>
            </div>

            <!-- Notifications -->
            <button
                class="relative p-2.5 text-surface-400 hover:text-white transition-all duration-300 rounded-xl hover:bg-white/5 border border-transparent hover:border-white/5 group">
                <Bell :size="20" class="group-hover:animate-swing" />
                <span
                    class="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full border-2 border-surface-900 shadow-[0_0_8px_rgba(239,68,68,0.5)]"></span>
            </button>

            <div class="h-8 w-px bg-white/10 mx-1"></div>

            <!-- User Avatar & Dropdown -->
            <div class="relative">
                <button @click="isUserMenuOpen = !isUserMenuOpen"
                    class="flex items-center gap-4 hover:bg-white/5 p-1.5 pr-3 rounded-2xl transition-all duration-300 border border-transparent hover:border-white/5 focus:outline-none"
                    :class="{ 'bg-white/5 border-white/5': isUserMenuOpen }">
                    <div class="w-10 h-10 rounded-xl overflow-hidden border border-white/10 shadow-lg relative group">
                        <img :src="authStore.user?.photo
                            ? (authStore.user.photo.startsWith('http') ? authStore.user.photo : `${authStore.storageBaseUrl}/storage/${authStore.user.photo}`)
                            : `https://ui-avatars.com/api/?name=${encodeURIComponent(userName)}&background=${themeStore.isDark ? '3b82f6' : '0f172a'}&color=fff&size=128`"
                            class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110"
                            :alt="userName"
                            @error="(e) => e.target.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(userName)}&background=${themeStore.isDark ? '3b82f6' : '0f172a'}&color=fff&size=128`" />
                    </div>
                    <div class="text-left hidden sm:block">
                        <p class="text-sm font-bold text-white leading-none mb-1">
                            {{ userName }}
                        </p>
                        <p class="text-[10px] text-primary-400 font-bold uppercase tracking-wider">
                            {{ userRole }}
                        </p>
                    </div>
                    <ChevronDown :size="16" class="text-surface-400 transition-transform duration-300"
                        :class="{ 'rotate-180': isUserMenuOpen }" />
                </button>

                <!-- Dropdown -->
                <transition enter-active-class="transition duration-200 ease-out"
                    enter-from-class="transform scale-95 opacity-0 translate-y-2"
                    enter-to-class="transform scale-100 opacity-100 translate-y-0"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="transform scale-100 opacity-100 translate-y-0"
                    leave-to-class="transform scale-95 opacity-0 translate-y-2">
                    <div v-if="isUserMenuOpen"
                        class="absolute right-0 top-full mt-4 w-64 bg-surface-800/90 backdrop-blur-xl border border-white/10 rounded-2xl shadow-2xl z-50 overflow-hidden">

                        <!-- Header Dropdown Mobile -->
                        <div class="block sm:hidden px-5 py-4 border-b border-white/5 bg-white/5">
                            <p class="text-sm font-bold text-white truncate">{{ userName }}</p>
                            <p class="text-xs text-surface-400 truncate">{{ authStore.user?.email }}</p>
                        </div>

                        <div class="p-2 space-y-1">
                            <router-link to="/settings" @click="isUserMenuOpen = false"
                                class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-surface-400 hover:text-white hover:bg-white/5 rounded-xl transition-colors group">
                                <div
                                    class="p-2 rounded-lg bg-surface-700/50 text-surface-400 group-hover:bg-primary-500/20 group-hover:text-primary-400 transition-colors">
                                    <Settings :size="16" />
                                </div>
                                <span>Pengaturan Profil</span>
                            </router-link>

                            <div class="h-px bg-white/5 my-1 mx-2"></div>

                            <button @click="handleLogout"
                                class="flex w-full items-center gap-3 px-4 py-3 text-sm font-medium text-red-500 hover:text-red-400 hover:bg-red-500/10 rounded-xl transition-colors group">
                                <div
                                    class="p-2 rounded-lg bg-red-500/10 text-red-500 group-hover:bg-red-500/20 transition-colors">
                                    <LogOut :size="16" />
                                </div>
                                <span>Keluar</span>
                            </button>
                        </div>
                    </div>
                </transition>
            </div>

            <!-- Overlay for closing dropdown -->
            <div v-if="isUserMenuOpen || isThemeMenuOpen" @click="closeMenus" class="fixed inset-0 z-40 bg-transparent">
            </div>
        </div>
    </header>
</template>

<style scoped>
@keyframes swing {
    0% {
        transform: rotate(0deg);
    }

    20% {
        transform: rotate(15deg);
    }

    40% {
        transform: rotate(-10deg);
    }

    60% {
        transform: rotate(5deg);
    }

    80% {
        transform: rotate(-5deg);
    }

    100% {
        transform: rotate(0deg);
    }
}

.animate-swing {
    animation: swing 0.5s ease-in-out;
}
</style>
