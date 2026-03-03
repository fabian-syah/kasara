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

const emit = defineEmits(['toggle-mobile-menu', 'toggle-sidebar']);
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
const userName = computed(() => authStore.userName);
const userRole = computed(() => getRoleLabel(authStore.userRole));
</script>

<template>
    <header
        class="sticky top-0 z-[999] flex items-center justify-between h-[72px] px-4 lg:px-6 bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 transition-colors duration-300">
        <!-- Left Side: Hamburger & Toggle & Search -->
        <div class="flex items-center gap-3 flex-1">
            <!-- Mobile hamburger -->
            <button @click="emit('toggle-mobile-menu')"
                class="lg:hidden p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-white rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                <Menu :size="22" />
            </button>

            <!-- Desktop sidebar toggle -->
            <button @click="emit('toggle-sidebar')"
                class="hidden lg:flex p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-white rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <line x1="3" y1="7" x2="21" y2="7"></line>
                    <line x1="3" y1="12" x2="15" y2="12"></line>
                    <line x1="3" y1="17" x2="21" y2="17"></line>
                </svg>
            </button>

            <!-- Search -->
            <div class="relative w-full max-w-[200px] sm:max-w-sm lg:max-w-md group">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" :size="18" />
                <input type="text" placeholder="Cari..."
                    class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg py-2.5 pl-10 pr-12 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500 transition-all placeholder:text-gray-400 dark:placeholder:text-gray-500" />
                <span
                    class="absolute right-3 top-1/2 -translate-y-1/2 hidden sm:flex items-center gap-1 text-[11px] text-gray-400 font-mono bg-gray-200 dark:bg-gray-700 px-1.5 py-0.5 rounded">
                    ⌘K
                </span>
            </div>
        </div>

        <!-- Right Side -->
        <div class="flex items-center gap-1 sm:gap-2">
            <!-- Theme Toggle -->
            <div class="relative">
                <button @click="isThemeMenuOpen = !isThemeMenuOpen"
                    class="p-2.5 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-white transition-colors rounded-full hover:bg-gray-100 dark:hover:bg-gray-800">
                    <Palette :size="20" />
                </button>

                <!-- Theme Dropdown -->
                <transition enter-active-class="transition duration-100 ease-out"
                    enter-from-class="transform scale-95 opacity-0" enter-to-class="transform scale-100 opacity-100"
                    leave-active-class="transition duration-75 ease-in"
                    leave-from-class="transform scale-100 opacity-100" leave-to-class="transform scale-95 opacity-0">
                    <div v-if="isThemeMenuOpen"
                        class="fixed inset-x-4 top-16 md:absolute md:top-auto md:right-0 md:left-auto md:w-72 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-xl z-50 p-4 mt-2">

                        <div class="flex items-center justify-between mb-4">
                            <h3 class="font-semibold text-gray-900 dark:text-white text-sm">
                                Tampilan
                            </h3>
                            <button @click="isThemeMenuOpen = false"
                                class="md:hidden p-1 text-gray-400 hover:text-gray-700 dark:hover:text-white">
                                <X :size="16" />
                            </button>
                            <button @click="themeStore.toggleDarkMode"
                                class="hidden md:flex items-center gap-2 p-2 rounded-lg bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors text-gray-700 dark:text-gray-300 text-xs font-medium"
                                :title="themeStore.isDark
                                    ? 'Switch to Light Mode'
                                    : 'Switch to Dark Mode'
                                    ">
                                <Sun v-if="themeStore.isDark" :size="14" />
                                <Moon v-else :size="14" />
                                <span>{{ themeStore.isDark ? 'Light' : 'Dark' }}</span>
                            </button>
                        </div>

                        <!-- Mobile Dark Mode Toggle -->
                        <div
                            class="flex md:hidden items-center justify-between mb-4 bg-gray-50 dark:bg-gray-800 p-3 rounded-lg border border-gray-200 dark:border-gray-700">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Mode Gelap</span>
                            <button @click="themeStore.toggleDarkMode"
                                class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                                :class="themeStore.isDark ? 'bg-primary-600' : 'bg-gray-300'">
                                <span class="sr-only">Use setting</span>
                                <span aria-hidden="true"
                                    class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                    :class="themeStore.isDark ? 'translate-x-5' : 'translate-x-0'"></span>
                            </button>
                        </div>

                        <p class="text-xs text-gray-400 mb-2 font-medium uppercase tracking-wider">
                            Warna Tema
                        </p>
                        <div class="grid grid-cols-5 gap-2">
                            <button v-for="theme in themeStore.availableThemes" :key="theme.id"
                                @click="themeStore.setTheme(theme.id)"
                                class="w-9 h-9 rounded-full flex items-center justify-center border-2 transition-transform hover:scale-110 shadow-sm"
                                :class="themeStore.themeName === theme.id
                                    ? 'border-gray-900 dark:border-white ring-2 ring-offset-2 ring-offset-white dark:ring-offset-gray-900 ring-primary-500'
                                    : 'border-transparent'
                                    " :style="{ backgroundColor: theme.color }" :title="theme.name"></button>
                        </div>
                    </div>
                </transition>
            </div>

            <!-- Dark mode quick toggle -->
            <button @click="themeStore.toggleDarkMode"
                class="hidden md:block p-2.5 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-white transition-colors rounded-full hover:bg-gray-100 dark:hover:bg-gray-800">
                <Sun v-if="themeStore.isDark" :size="20" />
                <Moon v-else :size="20" />
            </button>

            <!-- Notifications -->
            <button
                class="relative p-2.5 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-white transition-colors rounded-full hover:bg-gray-100 dark:hover:bg-gray-800">
                <Bell :size="20" />
                <span
                    class="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full border-2 border-white dark:border-gray-900"></span>
            </button>

            <!-- Separator -->
            <div class="h-8 w-px bg-gray-200 dark:bg-gray-700 mx-1"></div>

            <!-- User Avatar & Dropdown -->
            <div class="relative">
                <button @click="isUserMenuOpen = !isUserMenuOpen"
                    class="flex items-center gap-3 hover:bg-gray-100 dark:hover:bg-gray-800 p-1.5 rounded-lg transition-colors focus:outline-none">
                    <div
                        class="w-10 h-10 rounded-full overflow-hidden border-2 border-gray-200 dark:border-gray-700 shadow-sm">
                        <img :src="authStore.user?.photo
                            ? (authStore.user.photo.startsWith('http') ? authStore.user.photo : `${authStore.storageBaseUrl}/storage/${authStore.user.photo}`)
                            : `https://ui-avatars.com/api/?name=${encodeURIComponent(userName)}&background=${themeStore.isDark ? '3b82f6' : '0f172a'}&color=fff&size=128`"
                            class="w-full h-full object-cover" :alt="userName"
                            @error="(e) => e.target.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(userName)}&background=${themeStore.isDark ? '3b82f6' : '0f172a'}&color=fff&size=128`" />
                    </div>
                    <div class="text-left hidden sm:block">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white leading-none">
                            {{ userName }}
                        </p>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 font-medium mt-0.5">
                            {{ userRole }}
                        </p>
                    </div>
                    <ChevronDown :size="16" class="text-gray-400 transition-transform duration-200 hidden sm:block"
                        :class="{ 'rotate-180': isUserMenuOpen }" />
                </button>

                <!-- Dropdown -->
                <transition enter-active-class="transition duration-100 ease-out"
                    enter-from-class="transform scale-95 opacity-0" enter-to-class="transform scale-100 opacity-100"
                    leave-active-class="transition duration-75 ease-in"
                    leave-from-class="transform scale-100 opacity-100" leave-to-class="transform scale-95 opacity-0">
                    <div v-if="isUserMenuOpen"
                        class="absolute right-0 top-full mt-2 w-56 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-xl z-50 overflow-hidden">

                        <!-- Mobile User Info in Dropdown -->
                        <div
                            class="block sm:hidden px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ userName }}</p>
                            <p class="text-xs text-gray-500 truncate">{{ authStore.user?.email }}</p>
                        </div>

                        <div class="p-1">
                            <router-link to="/settings" @click="isUserMenuOpen = false"
                                class="flex items-center gap-3 px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">
                                <Settings :size="16" />
                                <span>Pengaturan Profil</span>
                            </router-link>
                            <button @click="themeStore.toggleDarkMode"
                                class="w-full flex md:hidden items-center gap-3 px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">
                                <component :is="themeStore.isDark ? Sun : Moon" :size="16" />
                                <span>{{ themeStore.isDark ? 'Mode Terang' : 'Mode Gelap' }}</span>
                            </button>
                        </div>

                        <div class="h-px bg-gray-200 dark:bg-gray-700 my-1"></div>

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
            <div v-if="isUserMenuOpen || isThemeMenuOpen" @click="closeMenus" class="fixed inset-0 z-40 bg-transparent">
            </div>
        </div>
    </header>
</template>
