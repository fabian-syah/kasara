<script setup>
import { ref, watch, onErrorCaptured } from "vue";
import { useRoute } from "vue-router";
import { useThemeStore } from "../store/theme";
import AppSidebar from "../components/layout/AppSidebar.vue";
import AppHeader from "../components/layout/AppHeader.vue";
import ToastContainer from "../components/ToastContainer.vue";

const route = useRoute();
const themeStore = useThemeStore();

const isMobileMenuOpen = ref(false);
const isSidebarExpanded = ref(true);

// Close menu on route change
watch(
  () => route.path,
  () => {
    isMobileMenuOpen.value = false;
  }
);

function toggleSidebar() {
  isSidebarExpanded.value = !isSidebarExpanded.value;
}

// Error boundary - prevent child component errors from crashing the entire layout
onErrorCaptured((err, instance, info) => {
  console.error(`[Layout Error] ${info}:`, err);
  return false;
});
</script>

<template>
  <div class="flex h-screen overflow-hidden bg-neutral-50 dark:bg-[#050505] font-sans antialiased transition-colors duration-500">
    <!-- Mobile Backdrop -->
    <div v-if="isMobileMenuOpen" class="fixed inset-0 bg-black/50 z-[99998] lg:hidden backdrop-blur-sm"
      @click="isMobileMenuOpen = false">
    </div>

    <ToastContainer />

    <!-- Sidebar & Main Content Wrapper for floating effect -->
    <div class="flex flex-1 w-full h-full relative p-2 md:p-4 gap-2 md:gap-4 overflow-hidden">
      <!-- Sidebar -->
      <AppSidebar :is-mobile-menu-open="isMobileMenuOpen" :is-expanded="isSidebarExpanded"
        @close-mobile-menu="isMobileMenuOpen = false" @expand-sidebar="isSidebarExpanded = true" />

      <!-- Main Content -->
      <main class="flex-1 flex flex-col min-w-0 bg-white dark:bg-surface-900 rounded-2xl md:rounded-3xl shadow-sm border border-neutral-200/50 dark:border-neutral-800/60 relative overflow-hidden transition-colors duration-500">
        <!-- Header -->
        <AppHeader @toggle-mobile-menu="isMobileMenuOpen = !isMobileMenuOpen" @toggle-sidebar="toggleSidebar" />

        <!-- Page Content Wrapper -->
        <div class="flex-1 overflow-y-auto custom-scrollbar">
          <div class="p-4 md:p-6 lg:p-8 max-w-[1920px] mx-auto">
            <router-view />
          </div>
        </div>
      </main>
    </div>
  </div>
</template>

<style scoped>
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
  background-color: rgba(156, 163, 175, 0.3);
  border-radius: 9999px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background-color: rgba(156, 163, 175, 0.5);
}
</style>
