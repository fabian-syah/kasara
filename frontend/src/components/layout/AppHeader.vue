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
const userName = computed(() => authStore.userName);
const userRole = computed(() => getRoleLabel(authStore.userRole));
</script>

<template>
    <header
        class="h-16 border-b border-surface-700 flex items-center justify-between px-4 lg:px-8 bg-surface-800/50 backdrop-blur-sm z-20 transition-colors duration-300">
        <!-- Left Side: Hamburger & Search -->
        <div class="flex items-center gap-2 sm:gap-4 flex-1">
            <button @click="emit('toggle-mobile-menu')"
                class="lg:hidden p-2 text-text-secondary hover:text-text-primary transition-colors shrink-0">
                <Menu :size="24" />
            </button>

            <!-- Search -->
            <div class="relative w-full max-w-[160px] xs:max-w-xs md:max-w-md group transition-all duration-300">
                <Search
                    class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary group-focus-within:text-primary-500 transition-colors"
                    :size="18" />
                <input type="text" placeholder="Cari..."
                    class="w-full bg-surface-900 border border-surface-700 rounded-full py-2 pl-10 pr-4 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition-all font-sans" />
            </div>
        </div>

        <!-- Right Side -->
        <div class="flex items-center gap-2 sm:gap-4">
            <!-- Theme Toggle -->
            <div class="relative">
                <button @click="isThemeMenuOpen = !isThemeMenuOpen"
                    class="p-2 text-text-secondary hover:text-text-primary transition-colors rounded-lg hover:bg-surface-700">
                    <Palette :size="20" />
                </button>

                <!-- Theme Dropdown -->
                <transition enter-active-class="transition duration-100 ease-out"
                    enter-from-class="transform scale-95 opacity-0" enter-to-class="transform scale-100 opacity-100"
                    leave-active-class="transition duration-75 ease-in"
                    leave-from-class="transform scale-100 opacity-100" leave-to-class="transform scale-95 opacity-0">
                    <div v-if="isThemeMenuOpen"
                        class="fixed inset-x-4 top-16 md:absolute md:top-auto md:right-0 md:left-auto md:w-72 bg-surface-800 border border-surface-700 rounded-xl shadow-xl z-50 p-4 mt-2">

                        <div class="flex items-center justify-between mb-4">
                            <h3 class="font-semibold text-text-primary text-sm">
                                Tampilan
                            </h3>
                            <button @click="isThemeMenuOpen = false"
                                class="md:hidden p-1 text-text-secondary hover:text-text-primary">
                                <X :size="16" />
                            </button>
                            <button @click="themeStore.toggleDarkMode"
                                class="hidden md:block p-2 rounded-lg bg-surface-900 border border-surface-700 hover:bg-surface-700 transition-colors text-text-primary"
                                :title="themeStore.isDark
                                    ? 'Switch to Light Mode'
                                    : 'Switch to Dark Mode'
                                    ">
                                <Sun v-if="themeStore.isDark" :size="16" />
                                <Moon v-else :size="16" />
                            </button>
                        </div>

                        <!-- Mobile Dark Mode Toggle -->
                        <div
                            class="flex md:hidden items-center justify-between mb-4 bg-surface-900 p-3 rounded-lg border border-surface-700">
                            <span class="text-sm text-text-secondary">Mode Gelap</span>
                            <button @click="themeStore.toggleDarkMode"
                                class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                                :class="themeStore.isDark ? 'bg-primary-600' : 'bg-surface-600'">
                                <span class="sr-only">Use setting</span>
                                <span aria-hidden="true"
                                    class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                    :class="themeStore.isDark ? 'translate-x-5' : 'translate-x-0'"></span>
                            </button>
                        </div>

                        <p class="text-xs text-text-secondary mb-2 font-medium uppercase tracking-wider">
                            Pilih Warna Tema
                        </p>
                        <div class="grid grid-cols-5 gap-2">
                            <button v-for="theme in themeStore.availableThemes" :key="theme.id"
                                @click="themeStore.setTheme(theme.id)"
                                class="w-9 h-9 rounded-full flex items-center justify-center border-2 transition-transform hover:scale-110 shadow-sm"
                                :class="themeStore.themeName === theme.id
                                    ? 'border-text-primary ring-2 ring-offset-2 ring-offset-surface-800 ring-primary-500'
                                    : 'border-transparent'
                                    " :style="{ backgroundColor: theme.color }" :title="theme.name"></button>
                        </div>
                    </div>
                </transition>
            </div>

            <!-- Notifications -->
            <button
                class="relative p-2 text-text-secondary hover:text-text-primary transition-colors rounded-lg hover:bg-surface-700">
                <Bell :size="20" />
                <span
                    class="absolute top-1.5 right-1.5 w-2 h-2 bg-primary-500 rounded-full border-2 border-surface-800"></span>
            </button>

            <div class="h-8 w-px bg-surface-700"></div>

            <!-- User Avatar & Dropdown -->
            <div class="relative">
                <button @click="isUserMenuOpen = !isUserMenuOpen"
                    class="flex items-center gap-3 hover:bg-surface-700/50 p-1.5 rounded-xl transition-colors focus:outline-none">
                    <div class="text-right hidden sm:block">
                        <p class="text-sm font-semibold text-text-primary leading-none">
                            {{ userName }}
                        </p>
                        <p class="text-[10px] text-primary-500 font-medium uppercase mt-1">
                            {{ userRole }}
                        </p>
                    </div>
                    <div class="w-10 h-10 rounded-xl overflow-hidden border-2 border-surface-700 shadow-lg relative">
                        <img :src="authStore.user?.photo
                            ? (authStore.user.photo.startsWith('http') ? authStore.user.photo : `${authStore.storageBaseUrl}/storage/${authStore.user.photo}`)
                            : `https://ui-avatars.com/api/?name=${encodeURIComponent(userName)}&background=${themeStore.isDark ? '3b82f6' : '0f172a'}&color=fff&size=128`"
                            class="w-full h-full object-cover" :alt="userName"
                            @error="(e) => e.target.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(userName)}&background=${themeStore.isDark ? '3b82f6' : '0f172a'}&color=fff&size=128`" />
                    </div>
                    <ChevronDown :size="16" class="text-text-secondary transition-transform duration-200"
                        :class="{ 'rotate-180': isUserMenuOpen }" />
                </button>

                <!-- Dropdown -->
                <transition enter-active-class="transition duration-100 ease-out"
                    enter-from-class="transform scale-95 opacity-0" enter-to-class="transform scale-100 opacity-100"
                    leave-active-class="transition duration-75 ease-in"
                    leave-from-class="transform scale-100 opacity-100" leave-to-class="transform scale-95 opacity-0">
                    <div v-if="isUserMenuOpen"
                        class="absolute right-0 top-full mt-2 w-56 bg-surface-800 border border-surface-700 rounded-xl shadow-xl z-50 overflow-hidden">

                        <!-- Mobile User Info in Dropdown -->
                        <div class="block sm:hidden px-4 py-3 border-b border-surface-700 bg-surface-900/50">
                            <p class="text-sm font-semibold text-text-primary truncate">{{ userName }}</p>
                            <p class="text-xs text-text-secondary truncate">{{ authStore.user?.email }}</p>
                        </div>

                        <div class="p-1">
                            <router-link to="/settings" @click="isUserMenuOpen = false"
                                class="flex items-center gap-3 px-3 py-2 text-sm font-medium text-text-secondary hover:text-text-primary hover:bg-surface-700 rounded-lg transition-colors">
                                <Settings :size="16" />
                                <span>Pengaturan Profil</span>
                            </router-link>
                            <button @click="themeStore.toggleDarkMode"
                                class="w-full flex md:hidden items-center gap-3 px-3 py-2 text-sm font-medium text-text-secondary hover:text-text-primary hover:bg-surface-700 rounded-lg transition-colors">
                                <component :is="themeStore.isDark ? Sun : Moon" :size="16" />
                                <span>{{ themeStore.isDark ? 'Mode Terang' : 'Mode Gelap' }}</span>
                            </button>
                        </div>

                        <div class="h-px bg-surface-700 my-1"></div>

                        <div class="p-1">
                            <button @click="handleLogout"
                                class="flex w-full items-center gap-3 px-3 py-2 text-sm font-medium text-red-500 hover:bg-red-500/10 rounded-lg transition-colors">
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
