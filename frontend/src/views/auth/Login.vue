<script setup>
import { ref, computed } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "../../store/auth";
import { useThemeStore } from "../../store/theme";
import {
  Eye,
  EyeOff,
  Lock,
  User,
  Loader2,
  Moon,
  Sun,
  ChevronRight,
  TrendingUp,
  Boxes
} from "lucide-vue-next";

const router = useRouter();
const authStore = useAuthStore();
const themeStore = useThemeStore();

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
      error.value = result.error || "Login gagal. Periksa username dan password.";
    }
  } catch (err) {
    error.value = "Terjadi kesalahan. Silakan coba lagi.";
  } finally {
    isLoading.value = false;
  }
}
</script>

<template>
  <div :class="[
    'min-h-screen flex items-center justify-center relative overflow-hidden font-sans transition-colors duration-200',
    themeStore.isDark ? 'bg-[#050505] text-white selection:bg-emerald-500/30' : 'bg-neutral-50 text-neutral-900 selection:bg-emerald-500/30'
  ]">
    
    <!-- Animated Abstract Background (Simplified for Performance) -->
    <div class="absolute inset-0 z-0 overflow-hidden pointer-events-none">
      <div :class="[
        'absolute top-[-20%] left-[-10%] w-[50vw] h-[50vw] rounded-full mix-blend-multiply filter blur-[120px] opacity-60 animate-blob',
        themeStore.isDark ? 'bg-emerald-900/40 mix-blend-screen' : 'bg-emerald-200/60'
      ]"></div>
      <div :class="[
        'absolute top-[20%] right-[-20%] w-[60vw] h-[60vw] rounded-full mix-blend-multiply filter blur-[130px] opacity-60 animate-blob animation-delay-2000',
        themeStore.isDark ? 'bg-rose-900/30 mix-blend-screen' : 'bg-rose-200/50'
      ]"></div>
      <div :class="[
        'absolute bottom-[-20%] left-[20%] w-[40vw] h-[40vw] rounded-full mix-blend-multiply filter blur-[120px] opacity-60 animate-blob animation-delay-4000',
        themeStore.isDark ? 'bg-amber-900/30 mix-blend-screen' : 'bg-amber-200/60'
      ]"></div>
      
      <!-- Subtle Grid Overlay -->
      <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxwYXRoIGQ9Ik0wIDBoNDB2NDBIMHoiIGZpbGw9Im5vbmUiLz4KPHBhdGggZD0iTTAgNDBoNDBNNDAgMHY0MCIgc3Ryb2tlPSJyZ2JhKDE1NSwxNTUsMTU1LDAuMDUpIiBzdHJva2Utd2lkdGg9IjEiLz4KPC9zdmc+')] opacity-60"></div>
    </div>

    <!-- Theme Switcher -->
    <div class="absolute top-6 right-6 z-50">
      <button @click="themeStore.toggleDarkMode"
        :class="[
          'p-3 transition-colors duration-200 rounded-full border backdrop-blur-md shadow-lg hover:scale-110 active:scale-95 text-neutral-500 hover:text-emerald-500',
          themeStore.isDark ? 'bg-neutral-900/50 border-neutral-800 hover:shadow-emerald-500/20' : 'bg-white/80 border-neutral-200 hover:shadow-emerald-500/20'
        ]"
        :title="themeStore.isDark ? 'Mode Terang' : 'Mode Gelap'">
        <Sun v-if="themeStore.isDark" :size="20" />
        <Moon v-else :size="20" />
      </button>
    </div>

    <!-- Main Card Container -->
    <div :class="[
      'relative z-10 w-full max-w-5xl flex flex-col lg:flex-row shadow-2xl rounded-[2rem] overflow-hidden m-4 lg:m-8 backdrop-blur-2xl border transition-colors duration-200',
      themeStore.isDark ? 'bg-neutral-900/60 border-neutral-800/60 shadow-black/50' : 'bg-white/70 border-white/40 shadow-emerald-900/10'
    ]">
      
      <!-- Left Panel: Branding & Value Props -->
      <div :class="[
        'hidden lg:flex lg:w-5/12 p-12 flex-col justify-between relative overflow-hidden transition-colors duration-200',
        themeStore.isDark ? 'bg-gradient-to-br from-emerald-950 to-neutral-950' : 'bg-gradient-to-br from-emerald-50 to-teal-50'
      ]">
        <!-- Inside left panel -->
        <div :class="['absolute inset-0 backdrop-blur-3xl transition-colors duration-200', themeStore.isDark ? 'bg-emerald-500/5' : 'bg-white/40']"></div>
        <div :class="['absolute bottom-0 left-0 right-0 h-2/3 bg-gradient-to-t to-transparent transition-colors duration-200', themeStore.isDark ? 'from-emerald-900/30' : 'from-emerald-200/30']"></div>
        
        <div class="relative z-10 flex flex-col gap-8">
          <div class="flex items-center gap-4">
            <!-- Increased Logo Container Size -->
            <h2 :class="['text-4xl font-extrabold tracking-tight drop-shadow-md transition-colors duration-200', themeStore.isDark ? 'text-white' : 'text-neutral-900']">
              KASARA
            </h2>
          </div>

          <div>
            <h1 :class="['text-4xl lg:text-5xl font-bold leading-tight mb-6 drop-shadow-sm transition-colors duration-200', themeStore.isDark ? 'text-white' : 'text-neutral-900']">
              Tingkatkan <br />
              <span :class="['text-transparent bg-clip-text bg-gradient-to-r transition-colors duration-200', themeStore.isDark ? 'from-emerald-400 to-teal-200' : 'from-emerald-600 to-teal-600']">Performa Bisnis</span>
            </h1>
            <p :class="['text-lg leading-relaxed font-light transition-colors duration-200', themeStore.isDark ? 'text-emerald-100/70' : 'text-neutral-600']">
              Sistem manajemen mutakhir untuk enterprise modern. Kontrol penuh di genggaman Anda.
            </p>
          </div>
        </div>

        <div class="relative z-10 space-y-4">
          <div :class="['flex items-center gap-4 p-4 rounded-2xl border backdrop-blur-md transition-colors duration-200', themeStore.isDark ? 'bg-black/20 border-emerald-500/10 hover:bg-black/30' : 'bg-white/60 border-emerald-200/50 hover:bg-white/80 shadow-sm']">
            <div :class="['w-12 h-12 rounded-full flex items-center justify-center shrink-0 transition-colors duration-200', themeStore.isDark ? 'bg-emerald-500/20 text-emerald-400' : 'bg-emerald-100 text-emerald-600']">
               <TrendingUp :size="22" stroke-width="1.5" />
            </div>
            <div>
              <h4 :class="['text-sm font-bold tracking-wide transition-colors duration-200', themeStore.isDark ? 'text-white' : 'text-neutral-900']">Analisis Real-time</h4>
              <p :class="['text-xs mt-0.5 transition-colors duration-200', themeStore.isDark ? 'text-emerald-200/60' : 'text-neutral-500']">Pantau omset dan profit secara instan.</p>
            </div>
          </div>
          
          <div :class="['flex items-center gap-4 p-4 rounded-2xl border backdrop-blur-md transition-colors duration-200', themeStore.isDark ? 'bg-black/20 border-emerald-500/10 hover:bg-black/30' : 'bg-white/60 border-emerald-200/50 hover:bg-white/80 shadow-sm']">
            <div :class="['w-12 h-12 rounded-full flex items-center justify-center shrink-0 transition-colors duration-200', themeStore.isDark ? 'bg-emerald-500/20 text-emerald-400' : 'bg-emerald-100 text-emerald-600']">
               <Boxes :size="22" stroke-width="1.5" />
            </div>
            <div>
              <h4 :class="['text-sm font-bold tracking-wide transition-colors duration-200', themeStore.isDark ? 'text-white' : 'text-neutral-900']">Multi Cabang</h4>
              <p :class="['text-xs mt-0.5 transition-colors duration-200', themeStore.isDark ? 'text-emerald-200/60' : 'text-neutral-500']">Sinkronisasi data 60+ lokasi tanpa delay.</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Right Panel: Login Form -->
      <div class="w-full lg:w-7/12 p-8 sm:p-12 lg:p-16 flex flex-col justify-center">
        
        <!-- Mobile Header -->
        <div class="flex lg:hidden items-center justify-center gap-4 mb-10">
          <h2 :class="['text-4xl font-extrabold tracking-tight drop-shadow-sm', themeStore.isDark ? 'text-white' : 'text-neutral-900']">
            KASARA
          </h2>
        </div>

        <div class="max-w-md w-full mx-auto">
          <div class="mb-10 text-center lg:text-left">
            <h2 :class="['text-3xl font-bold mb-3 tracking-tight', themeStore.isDark ? 'text-white' : 'text-neutral-900']">Selamat Datang</h2>
            <p :class="[themeStore.isDark ? 'text-neutral-400' : 'text-neutral-500', 'text-sm font-medium']">Silakan masuk ke akun Anda untuk melanjutkan.</p>
          </div>

          <!-- Error Alert -->
          <div v-if="error" class="mb-8 p-4 bg-rose-500/10 border border-rose-500/20 rounded-xl text-rose-500 text-sm flex items-center gap-3 shadow-sm animate-in fade-in slide-in-from-top-2">
             <div class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></div>
            {{ error }}
          </div>

          <form @submit.prevent="handleLogin" class="space-y-6">
            <!-- Username Input -->
            <div class="group">
              <label :class="['block text-xs font-bold uppercase tracking-wider mb-2 transition-colors', themeStore.isDark ? 'text-neutral-400 group-focus-within:text-emerald-400' : 'text-neutral-500 group-focus-within:text-emerald-600']">
                ID Login / Username
              </label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                  <User :size="18" :class="['transition-colors', themeStore.isDark ? 'text-neutral-500 group-focus-within:text-emerald-400' : 'text-neutral-400 group-focus-within:text-emerald-600']" />
                </div>
                <input v-model="form.username" type="text" placeholder="Masukkan ID atau username"
                  :class="[
                    'w-full text-sm rounded-xl pl-12 pr-4 py-4 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 transition-colors shadow-sm',
                    themeStore.isDark 
                      ? 'bg-neutral-950/50 border border-neutral-800 text-white placeholder:text-neutral-600 focus:border-emerald-500' 
                      : 'bg-white border border-neutral-200 text-neutral-900 placeholder:text-neutral-400 focus:border-emerald-500'
                  ]"
                  required />
              </div>
            </div>

            <!-- Password Input -->
            <div class="group">
              <label :class="['block text-xs font-bold uppercase tracking-wider mb-2 transition-colors', themeStore.isDark ? 'text-neutral-400 group-focus-within:text-emerald-400' : 'text-neutral-500 group-focus-within:text-emerald-600']">
                Password
              </label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                  <Lock :size="18" :class="['transition-colors', themeStore.isDark ? 'text-neutral-500 group-focus-within:text-emerald-400' : 'text-neutral-400 group-focus-within:text-emerald-600']" />
                </div>
                <input v-model="form.password" :type="showPassword ? 'text' : 'password'" placeholder="••••••••"
                  :class="[
                    'w-full text-sm rounded-xl pl-12 pr-12 py-4 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 transition-colors shadow-sm',
                    themeStore.isDark 
                      ? 'bg-neutral-950/50 border border-neutral-800 text-white placeholder:text-neutral-600 focus:border-emerald-500' 
                      : 'bg-white border border-neutral-200 text-neutral-900 placeholder:text-neutral-400 focus:border-emerald-500'
                  ]"
                  required />
                <button type="button" @click="showPassword = !showPassword"
                  class="absolute inset-y-0 right-0 pr-4 flex items-center text-neutral-400 hover:text-emerald-500 transition-colors">
                  <Eye v-if="!showPassword" :size="18" />
                  <EyeOff v-else :size="18" />
                </button>
              </div>
            </div>

            <!-- Options -->
            <div class="flex items-center justify-between pt-2">
              <label class="flex items-center gap-3 cursor-pointer group">
                <div :class="['relative flex items-center justify-center w-5 h-5 rounded border transition-colors', themeStore.isDark ? 'border-neutral-700 bg-neutral-950 group-hover:border-emerald-500' : 'border-neutral-300 bg-white group-hover:border-emerald-500']">
                  <input v-model="rememberMe" type="checkbox" class="absolute opacity-0 w-full h-full cursor-pointer peer" />
                  <div class="w-full h-full rounded bg-emerald-500 scale-0 peer-checked:scale-100 transition-transform flex items-center justify-center">
                    <svg class="w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                  </div>
                </div>
                <span :class="['text-sm font-medium transition-colors', themeStore.isDark ? 'text-neutral-400 group-hover:text-neutral-300' : 'text-neutral-600 group-hover:text-neutral-900']">Ingat saya</span>
              </label>
              <a href="#" class="text-sm font-medium text-emerald-600 hover:text-emerald-500 transition-colors">
                Lupa sandi?
              </a>
            </div>

            <!-- Submit Button -->
            <button type="submit" :disabled="!isFormValid || isLoading" 
              class="w-full relative group overflow-hidden rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 p-[1px] disabled:opacity-50 disabled:cursor-not-allowed mt-10 shadow-lg shadow-emerald-500/25 active:scale-[0.98] transition-transform">
              <div class="absolute inset-0 bg-gradient-to-r from-emerald-400 to-teal-400 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
              <div :class="['relative flex items-center justify-center gap-2 w-full h-full transition-colors py-4 rounded-xl font-bold text-white', themeStore.isDark ? 'bg-neutral-950 group-hover:bg-transparent' : 'bg-transparent']">
                 <Loader2 v-if="isLoading" :size="20" :class="['animate-spin', themeStore.isDark ? 'text-emerald-400 group-hover:text-white' : 'text-white']" />
                 <template v-else>
                   <span :class="[themeStore.isDark ? 'text-emerald-400 group-hover:text-white transition-colors' : 'text-white']">Masuk Sekarang</span>
                   <ChevronRight :size="18" :class="['transition-transform group-hover:translate-x-1', themeStore.isDark ? 'text-emerald-400 group-hover:text-white' : 'text-white']" />
                 </template>
              </div>
            </button>
          </form>

        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@keyframes blob {
  0% { transform: translate(0px, 0px) scale(1); }
  33% { transform: translate(30px, -50px) scale(1.1); }
  66% { transform: translate(-20px, 20px) scale(0.9); }
  100% { transform: translate(0px, 0px) scale(1); }
}
.animate-blob {
  animation: blob 7s infinite;
}
.animation-delay-2000 {
  animation-delay: 2s;
}
.animation-delay-4000 {
  animation-delay: 4s;
}

::-webkit-scrollbar {
  width: 0px;
  background: transparent;
}
</style>
