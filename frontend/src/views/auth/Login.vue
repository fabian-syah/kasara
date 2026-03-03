<script setup>
import { ref, computed } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "../../store/auth";
import { useThemeStore } from "../../store/theme"; // Import Theme Store
import {
  Eye,
  EyeOff,
  Lock,
  User,
  Loader2,
  Moon,
  Sun,
} from "lucide-vue-next"; // Icons

const router = useRouter();
const authStore = useAuthStore();
const themeStore = useThemeStore(); // Init Store

const form = ref({
  username: "",
  password: "",
});

const showPassword = ref(false);
const rememberMe = ref(false);
const isLoading = ref(false);
const error = ref("");

const isFormValid = computed(() => form.value.username && form.value.password);

async function handleLogin() {
  if (!isFormValid.value) return;

  isLoading.value = true;
  error.value = "";

  try {
    const result = await authStore.login({
      username: form.value.username,
      password: form.value.password,
      remember_me: rememberMe.value,
    });

    if (result.success) {
      router.push("/");
    } else {
      error.value =
        result.error || "Login gagal. Periksa username dan password.";
    }
  } catch (err) {
    error.value = "Terjadi kesalahan. Silakan coba lagi.";
  } finally {
    isLoading.value = false;
  }
}

function demoLogin(role) {
  const demos = {
    admin: { username: "admin", password: "demo123" },
    kasir: { username: "kasir", password: "demo123" },
    gudang: { username: "gudang", password: "demo123" },
  };
  form.value.username = demos[role].username;
  form.value.password = demos[role].password;
}
</script>

<template>
  <div
    class="min-h-screen flex bg-surface-900 text-text-primary relative overflow-hidden transition-colors duration-300">
    <!-- Theme Switcher (Absolute Top Right) -->
    <div class="absolute top-4 right-4 z-50">
      <button @click="themeStore.toggleDarkMode"
        class="p-2.5 text-text-secondary hover:text-text-primary transition-colors rounded-full hover:bg-surface-800 bg-surface-800/50 border border-surface-700 backdrop-blur-sm"
        :title="themeStore.isDark ? 'Mode Terang' : 'Mode Gelap'">
        <Sun v-if="themeStore.isDark" :size="20" />
        <Moon v-else :size="20" />
      </button>
    </div>

    <!-- Background Effects -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
      <div
        class="absolute -top-1/2 -left-1/2 w-full h-full bg-gradient-radial from-primary-600/10 to-transparent rounded-full blur-3xl">
      </div>
      <div
        class="absolute -bottom-1/2 -right-1/2 w-full h-full bg-gradient-radial from-primary-600/10 to-transparent rounded-full blur-3xl">
      </div>
    </div>

    <!-- Left Section - Branding -->
    <div class="hidden lg:flex lg:w-1/2 relative z-10 items-center justify-center p-12">
      <div class="max-w-lg text-center">
        <!-- Header Layout -->
        <div class="mb-10 text-center animate-in slide-up" style="animation-delay: 100ms;">
          <div
            class="w-24 h-24 bg-primary-600 rounded-3xl shadow-xl shadow-primary-500/30 flex items-center justify-center mx-auto mb-6 transform transition-transform hover:scale-105">
            <img src="/images/logo-pstore.png" alt="PSTORE" class="w-16 h-16 object-contain" />
          </div>
          <h2 class="text-4xl font-extrabold text-text-primary mb-2 tracking-tight">
            PSTORE <span class="text-primary-500">POS</span>
          </h2>
          <p class="text-text-secondary font-medium">Masuk untuk melanjutkan ke dashboard.</p>
        </div>
        <h1 class="text-3xl font-bold text-text-primary mb-4">
          Enterprise Point of Sale
        </h1>
        <p class="text-text-secondary text-lg leading-relaxed">
          Sistem POS modern untuk mengelola 60+ cabang dengan real-time sync,
          multi-role access, dan analytics terintegrasi.
        </p>

        <div class="mt-12 grid grid-cols-3 gap-6">
          <div class="text-center">
            <div class="text-3xl font-bold text-text-primary">60+</div>
            <div class="text-sm text-text-secondary">Cabang</div>
          </div>
          <div class="text-center">
            <div class="text-3xl font-bold text-text-primary">12</div>
            <div class="text-sm text-text-secondary">Role Akses</div>
          </div>
          <div class="text-center">
            <div class="text-3xl font-bold text-text-primary">24/7</div>
            <div class="text-sm text-text-secondary">Real-time</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Right Section - Login Form -->
    <div class="w-full lg:w-1/2 flex items-center justify-center p-8 relative z-10">
      <div class="w-full max-w-md">
        <!-- Header (Mobile) -->
        <div class="mb-8 text-center lg:hidden">
          <div class="w-16 h-16 bg-primary-600 rounded-2xl shadow-lg flex items-center justify-center mx-auto mb-4">
            <img src="/images/logo-pstore.png" alt="PSTORE" class="w-10 h-10 object-contain" />
          </div>
          <h2 class="text-3xl font-bold text-text-primary">
            PSTORE <span class="text-primary-500">POS</span>
          </h2>
        </div>

        <!-- Login Card -->
        <div class="glass rounded-3xl p-8 shadow-2xl bg-surface-800 border border-surface-700">
          <div class="text-center mb-8">
            <h2 class="text-2xl font-bold text-text-primary">Selamat Datang</h2>
            <p class="text-text-secondary mt-2">
              Masuk ke akun Anda untuk melanjutkan
            </p>
          </div>

          <!-- Error Alert -->
          <div v-if="error" class="mb-6 p-4 bg-red-500/10 border border-red-500/30 rounded-xl text-red-400 text-sm">
            {{ error }}
          </div>

          <form @submit.prevent="handleLogin" class="space-y-5">
            <!-- Username -->
            <div>
              <label class="block text-sm font-medium text-text-secondary mb-2">ID Login / Username</label>
              <div class="relative">
                <User class="absolute left-4 top-1/2 -translate-y-1/2 text-text-secondary" :size="18" />
                <input v-model="form.username" type="text" placeholder="Masukkan ID atau username"
                  class="input pl-12 bg-surface-900 border-surface-700 text-text-primary placeholder:text-text-secondary focus:border-primary-500 focus:ring-primary-500/50"
                  required />
              </div>
            </div>

            <!-- Password -->
            <div>
              <label class="block text-sm font-medium text-text-secondary mb-2">Password</label>
              <div class="relative">
                <Lock class="absolute left-4 top-1/2 -translate-y-1/2 text-text-secondary" :size="18" />
                <input v-model="form.password" :type="showPassword ? 'text' : 'password'" placeholder="••••••••"
                  class="input pl-12 pr-12 bg-surface-900 border-surface-700 text-text-primary placeholder:text-text-secondary focus:border-primary-500 focus:ring-primary-500/50"
                  required />
                <button type="button" @click="showPassword = !showPassword"
                  class="absolute right-4 top-1/2 -translate-y-1/2 text-text-secondary hover:text-text-primary transition-colors">
                  <Eye v-if="!showPassword" :size="18" />
                  <EyeOff v-else :size="18" />
                </button>
              </div>
            </div>

            <!-- Remember Me -->
            <div class="flex items-center justify-between">
              <label class="flex items-center gap-2 cursor-pointer">
                <input v-model="rememberMe" type="checkbox"
                  class="w-4 h-4 rounded border-surface-600 bg-surface-900 text-primary-600 focus:ring-primary-500" />
                <span class="text-sm text-text-secondary">Ingat saya</span>
              </label>
              <a href="#" class="text-sm text-primary-500 hover:text-primary-400 transition-colors">
                Lupa password?
              </a>
            </div>

            <!-- Submit Button -->
            <button type="submit" :disabled="!isFormValid || isLoading" class="btn btn-primary w-full py-4 text-base">
              <Loader2 v-if="isLoading" :size="20" class="animate-spin" />
              <span v-else>Masuk</span>
            </button>
          </form>

          <!-- Demo Login Section -->
          <div class="mt-8 pt-6 border-t border-surface-700/50">
            <p class="text-center text-sm text-text-secondary mb-4">
              Demo Login (Klik untuk isi otomatis):
            </p>
            <div class="flex gap-2 justify-center flex-wrap">
              <button @click="demoLogin('admin')"
                class="px-3 py-1.5 text-xs font-medium bg-blue-500/10 text-blue-500 rounded-lg hover:bg-blue-500/20 transition-colors">
                Super Admin
              </button>
              <button @click="demoLogin('kasir')"
                class="px-3 py-1.5 text-xs font-medium bg-emerald-500/10 text-emerald-500 rounded-lg hover:bg-emerald-500/20 transition-colors">
                Kasir
              </button>
              <button @click="demoLogin('gudang')"
                class="px-3 py-1.5 text-xs font-medium bg-amber-500/10 text-amber-500 rounded-lg hover:bg-amber-500/20 transition-colors">
                Gudang
              </button>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <p class="mt-8 text-center text-sm text-text-secondary animate-in slide-up" style="animation-delay: 450ms;">
          &copy; 2026 PSTORE POS. All rights reserved.
        </p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.bg-gradient-radial {
  background: radial-gradient(circle,
      var(--tw-gradient-from),
      var(--tw-gradient-to));
}
</style>
