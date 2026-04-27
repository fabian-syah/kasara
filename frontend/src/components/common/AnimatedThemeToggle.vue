<template>
  <button
    @click="themeStore.toggleDarkMode"
    class="theme-toggle"
    :class="{ 'is-dark': themeStore.isDark }"
    :aria-label="themeStore.isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
  >
    <div class="toggle-bg">
      <div class="sky day-sky">
        <div class="cloud-base"></div>
        <div class="cloud cloud-1"></div>
        <div class="cloud cloud-2"></div>
        <div class="cloud cloud-3"></div>
        
        <!-- Detailed Airplane SVG -->
        <svg class="airplane" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
          <!-- Right Wing -->
          <path d="M 45 45 L 65 20 L 75 25 L 55 50 Z" fill="#7dd3fc"/>
          <!-- Tail Right -->
          <path d="M 25 45 L 15 30 L 25 35 L 30 45 Z" fill="#7dd3fc"/>
          
          <!-- Body -->
          <rect x="15" y="40" width="70" height="20" rx="10" fill="#f0f9ff"/>
          
          <!-- Cockpit -->
          <path d="M 60 40 Q 85 40 85 50 L 60 50 Z" fill="#0ea5e9"/>
          
          <!-- Windows -->
          <circle cx="50" cy="45" r="2" fill="#0ea5e9"/>
          <circle cx="40" cy="45" r="2" fill="#0ea5e9"/>
          <circle cx="30" cy="45" r="2" fill="#0ea5e9"/>
          
          <!-- Tail Left -->
          <path d="M 25 55 L 10 75 L 20 75 L 30 55 Z" fill="#38bdf8"/>
          <!-- Left Wing -->
          <path d="M 40 55 L 20 85 L 35 85 L 50 55 Z" fill="#38bdf8"/>
          
          <!-- Engine -->
          <rect x="30" y="60" width="12" height="8" rx="4" fill="#94a3b8"/>
        </svg>
      </div>

      <div class="sky night-sky">
        <!-- Beautiful Curved 4-Pointed Stars -->
        <svg class="star star-1" viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg"><path d="M12 0 C 12 10, 14 12, 24 12 C 14 12, 12 14, 12 24 C 12 14, 10 12, 0 12 C 10 12, 12 10, 12 0 Z"/></svg>
        <svg class="star star-2" viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg"><path d="M12 0 C 12 10, 14 12, 24 12 C 14 12, 12 14, 12 24 C 12 14, 10 12, 0 12 C 10 12, 12 10, 12 0 Z"/></svg>
        <svg class="star star-3" viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg"><path d="M12 0 C 12 10, 14 12, 24 12 C 14 12, 12 14, 12 24 C 12 14, 10 12, 0 12 C 10 12, 12 10, 12 0 Z"/></svg>
        <svg class="star star-4" viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg"><path d="M12 0 C 12 10, 14 12, 24 12 C 14 12, 12 14, 12 24 C 12 14, 10 12, 0 12 C 10 12, 12 10, 12 0 Z"/></svg>
        <svg class="star star-5" viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg"><path d="M12 0 C 12 10, 14 12, 24 12 C 14 12, 12 14, 12 24 C 12 14, 10 12, 0 12 C 10 12, 12 10, 12 0 Z"/></svg>
        <svg class="star star-6" viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg"><path d="M12 0 C 12 10, 14 12, 24 12 C 14 12, 12 14, 12 24 C 14 12, 10 12, 0 12 C 10 12, 12 10, 12 0 Z"/></svg>
      </div>
    </div>
    
    <div class="toggle-thumb">
      <div class="sun"></div>
      <div class="moon">
        <div class="crater crater-1"></div>
        <div class="crater crater-2"></div>
        <div class="crater crater-3"></div>
      </div>
    </div>
  </button>
</template>

<script setup>
import { useThemeStore } from "../../store/theme";
const themeStore = useThemeStore();
</script>

<style scoped>
.theme-toggle {
  position: relative;
  width: 90px;
  height: 44px;
  border-radius: 44px;
  border: none;
  cursor: pointer;
  overflow: hidden;
  padding: 0;
  outline: none;
  box-shadow: inset 0 2px 6px rgba(0,0,0,0.15);
  background: transparent;
  flex-shrink: 0;
}

.theme-toggle:focus-visible {
  box-shadow: 0 0 0 2px var(--color-primary-500);
}

.toggle-bg {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  border-radius: 44px;
  background: linear-gradient(to bottom, #7ab4f8, #a8d1ff);
  transition: background 0.5s ease;
  overflow: hidden;
}

.theme-toggle.is-dark .toggle-bg {
  background: linear-gradient(to bottom, #191c40, #2b326e);
}

/* Sky containers */
.sky {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  transition: opacity 0.5s ease, transform 0.5s ease;
}

.day-sky {
  opacity: 1;
  transform: translateY(0);
}

.theme-toggle.is-dark .day-sky {
  opacity: 0;
  transform: translateY(15px);
}

.night-sky {
  opacity: 0;
  transform: translateY(-15px);
}

.theme-toggle.is-dark .night-sky {
  opacity: 1;
  transform: translateY(0);
}

/* Clouds */
.cloud-base {
  position: absolute;
  bottom: 0;
  left: 0;
  width: 100%;
  height: 12px;
  background: white;
  z-index: 2;
}
.cloud {
  position: absolute;
  background: white;
  border-radius: 50%;
  z-index: 2;
}

.cloud-1 { 
  width: 36px; height: 36px; 
  left: 10px; bottom: 4px; 
}
.cloud-2 { 
  width: 54px; height: 54px; 
  left: 32px; bottom: -4px; 
}
.cloud-3 { 
  width: 40px; height: 40px; 
  left: 65px; bottom: 2px; 
}

/* Airplane */
.airplane {
  position: absolute;
  width: 32px;
  height: 32px;
  right: 12px;
  top: 6px;
  transform: rotate(-10deg);
  opacity: 0.95;
  filter: drop-shadow(1px 2px 2px rgba(0,0,0,0.15));
  animation: fly 6s infinite alternate ease-in-out;
  z-index: 3;
}

@keyframes fly {
  0% { transform: translateY(0) rotate(-10deg); }
  100% { transform: translateY(-4px) rotate(0deg); }
}

/* Stars */
.star {
  position: absolute;
  animation: twinkle 3s infinite alternate ease-in-out;
}

.star-1 { width: 12px; height: 12px; top: 6px; left: 24px; animation-delay: 0s; }
.star-2 { width: 6px; height: 6px; top: 22px; left: 16px; animation-delay: 1s; }
.star-3 { width: 9px; height: 9px; top: 10px; left: 38px; animation-delay: 0.5s; }
.star-4 { width: 5px; height: 5px; top: 26px; left: 34px; animation-delay: 1.5s; }
.star-5 { width: 8px; height: 8px; top: 16px; left: 52px; animation-delay: 0.8s; }
.star-6 { width: 11px; height: 11px; top: 5px; left: 62px; animation-delay: 1.2s; }

@keyframes twinkle {
  0%, 100% { opacity: 0.2; transform: scale(0.6); }
  50% { opacity: 1; transform: scale(1.1); }
}

/* Thumb */
.toggle-thumb {
  position: absolute;
  top: 4px;
  left: 4px;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: transparent;
  transition: transform 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
  z-index: 10;
  overflow: hidden;
}

.theme-toggle.is-dark .toggle-thumb {
  transform: translateX(46px);
}

.sun, .moon {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  border-radius: 50%;
  transition: opacity 0.5s ease, transform 0.6s ease;
}

/* Sun */
.sun {
  background: linear-gradient(135deg, #ffde6b, #ff9b26);
  box-shadow: inset -2px -2px 6px rgba(255, 155, 38, 0.5), 0 0 10px rgba(255, 222, 107, 0.6);
  opacity: 1;
  transform: rotate(0deg) scale(1);
}

.theme-toggle.is-dark .sun {
  opacity: 0;
  transform: rotate(90deg) scale(0.5);
}

/* Moon */
.moon {
  background: linear-gradient(135deg, #f5f8ff, #c4d3ec);
  box-shadow: inset -3px -3px 6px rgba(0,0,0,0.1), 0 0 12px rgba(255,255,255,0.3);
  opacity: 0;
  transform: rotate(-90deg) scale(0.5);
}

.theme-toggle.is-dark .moon {
  opacity: 1;
  transform: rotate(0deg) scale(1);
}

/* Craters */
.crater {
  position: absolute;
  background: #d4dfee;
  border-radius: 50%;
  box-shadow: inset 2px 2px 4px rgba(0,0,0,0.15), inset -1px -1px 2px rgba(255,255,255,0.5);
}

.crater-1 { width: 10px; height: 10px; top: 10px; left: 8px; }
.crater-2 { width: 14px; height: 14px; bottom: 6px; left: 16px; }
.crater-3 { width: 6px; height: 6px; top: 6px; right: 8px; }
</style>
