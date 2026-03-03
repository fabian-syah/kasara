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
  ShieldCheck,
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
  <div :class="[
    'min-h-screen flex items-center justify-center relative overflow-hidden font-sans transition-colors duration-500',
    themeStore.isDark ? 'bg-[#050505] text-white selection:bg-emerald-500/30' : 'bg-neutral-50 text-neutral-900 selection:bg-emerald-500/30'
  ]">
    
    <!-- Animated Abstract Background -->
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
          'p-3 transition-all duration-300 rounded-full border backdrop-blur-md shadow-lg hover:scale-110 active:scale-95 text-neutral-500 hover:text-emerald-500',
          themeStore.isDark ? 'bg-neutral-900/50 border-neutral-800 hover:shadow-emerald-500/20' : 'bg-white/80 border-neutral-200 hover:shadow-emerald-500/20'
        ]"
        :title="themeStore.isDark ? 'Mode Terang' : 'Mode Gelap'">
        <Sun v-if="themeStore.isDark" :size="20" />
        <Moon v-else :size="20" />
      </button>
    </div>

    <!-- Main Card Container -->
    <div :class="[
      'relative z-10 w-full max-w-5xl flex flex-col lg:flex-row shadow-2xl rounded-[2rem] overflow-hidden m-4 lg:m-8 backdrop-blur-2xl border transition-colors duration-500',
      themeStore.isDark ? 'bg-neutral-900/60 border-neutral-800/60 shadow-black/50' : 'bg-white/70 border-white/40 shadow-emerald-900/10'
    ]">
      
      <!-- Left Panel: Branding & Value Props -->
      <div class="hidden lg:flex lg:w-5/12 p-12 flex-col justify-between relative overflow-hidden bg-gradient-to-br from-emerald-950 to-neutral-950">
        <!-- Inside left panel we force a dark elegant aesthetic regardless of theme for contrast -->
        <div class="absolute inset-0 bg-emerald-500/5 backdrop-blur-3xl"></div>
        <div class="absolute bottom-0 left-0 right-0 h-2/3 bg-gradient-to-t from-emerald-900/30 to-transparent"></div>
        
        <div class="relative z-10 flex flex-col gap-8">
          <div class="flex items-center gap-4">
            <div class="w-14 h-14 bg-gradient-to-br from-emerald-400 to-emerald-600 rounded-2xl shadow-xl shadow-emerald-500/30 flex items-center justify-center transform hover:rotate-12 transition-transform duration-500">
               <img src="/images/logo-pstore.png" alt="PSTORE" class="w-8 h-8 object-contain brightness-0 invert" />
            </div>
            <h2 class="text-3xl font-extrabold tracking-tight text-white drop-shadow-md">
              PSTORE<span class="text-emerald-400 font-light">POS</span>
            </h2>
          </div>

          <div>
            <h1 class="text-4xl lg:text-5xl font-bold leading-tight mb-6 text-white drop-shadow-sm">
              Tingkatkan <br />
              <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 to-teal-200">Performa Bisnis</span>
            </h1>
            <p class="text-emerald-100/70 text-lg leading-relaxed font-light">
              Sistem manajemen mutakhir untuk enterprise modern. Kontrol penuh di genggaman Anda.
            </p>
          </div>
        </div>

        <div class="relative z-10 space-y-4">
          <div class="flex items-center gap-4 bg-black/20 p-4 rounded-2xl border border-emerald-500/10 backdrop-blur-md hover:bg-black/30 transition-colors">
            <div class="w-12 h-12 rounded-full bg-emerald-500/20 flex items-center justify-center text-emerald-400 shrink-0">
               <TrendingUp :size="22" stroke-width="1.5" />
            </div>
            <div>
              <h4 class="text-sm font-bold text-white tracking-wide">Analisis Real-time</h4>
              <p class="text-xs text-emerald-200/60 mt-0.5">Pantau omset dan profit secara instan.</p>
            </div>
          </div>
          
          <div class="flex items-center gap-4 bg-black/20 p-4 rounded-2xl border border-emerald-500/10 backdrop-blur-md hover:bg-black/30 transition-colors">
            <div class="w-12 h-12 rounded-full bg-emerald-500/20 flex items-center justify-center text-emerald-400 shrink-0">
               <Boxes :size="22" stroke-width="1.5" />
            </div>
            <div>
              <h4 class="text-sm font-bold text-white tracking-wide">Multi Cabang</h4>
              <p class="text-xs text-emerald-200/60 mt-0.5">Sinkronisasi data 60+ lokasi tanpa delay.</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Right Panel: Login Form -->
      <div class="w-full lg:w-7/12 p-8 sm:p-12 lg:p-16 flex flex-col justify-center">
        
        <!-- Mobile Header -->
        <div class="flex lg:hidden items-center justify-center gap-3 mb-10">
          <div class="w-12 h-12 bg-gradient-to-br from-emerald-400 to-emerald-600 rounded-xl shadow-lg shadow-emerald-500/30 flex items-center justify-center">
            <img src="/images/logo-pstore.png" alt="PSTORE" class="w-7 h-7 object-contain brightness-0 invert" />
          </div>
          <h2 :class="['text-3xl font-extrabold tracking-tight drop-shadow-sm', themeStore.isDark ? 'text-white' : 'text-neutral-900']">
            PSTORE<span class="text-emerald-500 font-light">POS</span>
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
                    'w-full text-sm rounded-xl pl-12 pr-4 py-4 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 transition-all shadow-sm',
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
                    'w-full text-sm rounded-xl pl-12 pr-12 py-4 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 transition-all shadow-sm',
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
              class="w-full relative group overflow-hidden rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 p-[1px] disabled:opacity-50 disabled:cursor-not-allowed mt-6 shadow-lg shadow-emerald-500/25 active:scale-[0.98] transition-all">
              <div class="absolute inset-0 bg-gradient-to-r from-emerald-400 to-teal-400 opacity-0 group-hover:opacity-100 transition-opacity"></div>
              <div :class="['relative flex items-center justify-center gap-2 w-full h-full transition-colors py-4 rounded-xl font-bold text-white', themeStore.isDark ? 'bg-neutral-950 group-hover:bg-transparent' : 'bg-transparent']">
                 <Loader2 v-if="isLoading" :size="20" :class="['animate-spin', themeStore.isDark ? 'text-emerald-400 group-hover:text-white' : 'text-white']" />
                 <template v-else>
                   <span :class="[themeStore.isDark ? 'text-emerald-400 group-hover:text-white transition-colors' : 'text-white']">Masuk Sekarang</span>
                   <ChevronRight :size="18" :class="['transition-all group-hover:translate-x-1', themeStore.isDark ? 'text-emerald-400 group-hover:text-white' : 'text-white']" />
                 </template>
              </div>
            </button>
          </form>

          <!-- Demo Roles -->
          <div class="mt-12">
            <div class="relative flex items-center py-5">
              <div class="flex-grow border-t border-neutral-200 dark:border-neutral-800"></div>
              <span :class="['flex-shrink-0 mx-4 text-xs font-semibold uppercase tracking-widest', themeStore.isDark ? 'text-neutral-600' : 'text-neutral-400']">
                Akses Demo
              </span>
              <div class="flex-grow border-t border-neutral-200 dark:border-neutral-800"></div>
            </div>
            
            <div class="grid grid-cols-3 gap-3">
              <button @click="demoLogin('admin')" 
                :class="['py-3 px-2 rounded-xl border text-xs font-bold transition-all group hover:-translate-y-0.5', themeStore.isDark ? 'border-neutral-800 bg-neutral-900/50 hover:bg-emerald-500/10 hover:border-emerald-500/50 hover:text-emerald-400 text-neutral-400' : 'border-neutral-200 bg-neutral-50 hover:bg-emerald-50 hover:border-emerald-200 hover:text-emerald-600 text-neutral-600']">
                <span :class="['block mb-1 text-lg', themeStore.isDark ? 'text-emerald-500/50 group-hover:text-emerald-400' : 'text-emerald-300 group-hover:text-emerald-500']">●</span> 
                Admin
              </button>
              <button @click="demoLogin('kasir')" 
                :class="['py-3 px-2 rounded-xl border text-xs font-bold transition-all group hover:-translate-y-0.5', themeStore.isDark ? 'border-neutral-800 bg-neutral-900/50 hover:bg-amber-500/10 hover:border-amber-500/50 hover:text-amber-400 text-neutral-400' : 'border-neutral-200 bg-neutral-50 hover:bg-amber-50 hover:border-amber-200 hover:text-amber-600 text-neutral-600']">
                <span :class="['block mb-1 text-lg', themeStore.isDark ? 'text-amber-500/50 group-hover:text-amber-400' : 'text-amber-300 group-hover:text-amber-500']">●</span> 
                Kasir
              </button>
              <button @click="demoLogin('gudang')" 
                :class="['py-3 px-2 rounded-xl border text-xs font-bold transition-all group hover:-translate-y-0.5', themeStore.isDark ? 'border-neutral-800 bg-neutral-900/50 hover:bg-rose-500/10 hover:border-rose-500/50 hover:text-rose-400 text-neutral-400' : 'border-neutral-200 bg-neutral-50 hover:bg-rose-50 hover:border-rose-200 hover:text-rose-600 text-neutral-600']">
                <span :class="['block mb-1 text-lg', themeStore.isDark ? 'text-rose-500/50 group-hover:text-rose-400' : 'text-rose-300 group-hover:text-rose-500']">●</span> 
                Gudang
              </button>
            </div>
          </div>

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
