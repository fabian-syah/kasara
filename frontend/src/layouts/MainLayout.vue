<script setup>
import { ref, watch } from "vue";
import { useRoute } from "vue-router";
import { useThemeStore } from "../store/theme";
import AppSidebar from "../components/layout/AppSidebar.vue";
import AppHeader from "../components/layout/AppHeader.vue";
import AppFooter from "../components/layout/Footer.vue";
import {
  Heart,
  Cloud,
  Sparkles,
  PawPrint,
} from "lucide-vue-next";
import ToastContainer from "../components/ToastContainer.vue"; // Ensure this is imported if used (implied by previous code)

const route = useRoute();
const themeStore = useThemeStore();

const isMobileMenuOpen = ref(false);

// Close menu on route change
watch(
  () => route.path,
  () => {
    isMobileMenuOpen.value = false;
  }
);
</script>

<template>
  <div class="flex h-screen bg-surface-900 text-slate-300 font-sans antialiased overflow-hidden">
    <!-- Mobile Backdrop -->
    <div v-if="isMobileMenuOpen" class="fixed inset-0 bg-black/60 z-40 lg:hidden" @click="isMobileMenuOpen = false">
    </div>

    <ToastContainer />

    <!-- Sidebar -->
    <AppSidebar :is-mobile-menu-open="isMobileMenuOpen" @close-mobile-menu="isMobileMenuOpen = false" />

    <!-- Main Content -->
    <main class="flex-1 flex flex-col min-w-0 bg-surface-900 transition-colors duration-300 relative overflow-hidden">
      <!-- Header -->
      <AppHeader @toggle-mobile-menu="isMobileMenuOpen = !isMobileMenuOpen" />

      <!-- Aesthetic Theme Decorations (Mascot/Watermark) -->
      <div v-if="['coquette', 'bear', 'milk', 'white'].includes(themeStore.themeName)"
        class="fixed -bottom-12 -right-12 z-0 pointer-events-none opacity-[0.07] select-none transform -rotate-12 text-primary-500"
        style="pointer-events: none !important;">
        <Heart v-if="themeStore.themeName === 'coquette'" :size="500" stroke-width="1.5" />
        <PawPrint v-if="themeStore.themeName === 'bear'" :size="500" stroke-width="1.5" />
        <Cloud v-if="themeStore.themeName === 'milk'" :size="500" stroke-width="1.5" />
        <Sparkles v-if="themeStore.themeName === 'white'" :size="500" stroke-width="1.5" />
      </div>

      <!-- Page Content -->
      <div class="flex-1 overflow-y-auto custom-scrollbar p-4 md:p-8 pt-6 relative z-10 flex flex-col">
        <router-view v-slot="{ Component }">
          <transition name="fade" mode="out-in">
            <component :is="Component" />
          </transition>
        </router-view>

        <!-- Developer Footer -->
        <AppFooter class="mt-8" />
      </div>
    </main>
  </div>
</template>

<style scoped>
/* Keeps transition styles */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translateY(8px);
}

.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}

.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: #1e293b;
  border-radius: 9999px;
}
</style>