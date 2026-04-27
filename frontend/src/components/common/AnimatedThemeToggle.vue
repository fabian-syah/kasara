<template>
  <button
    @click="themeStore.toggleDarkMode"
    class="theme-toggle"
    :class="{ 'is-dark': themeStore.isDark }"
    :aria-label="themeStore.isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
  >
    <div class="toggle-bg">
      <div class="sky day-sky">
        <div class="cloud cloud-1"></div>
        <div class="cloud cloud-2"></div>
        <div class="cloud cloud-3"></div>
        
        <!-- Airplane SVG -->
        <svg class="airplane" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <!-- Plane Body -->
          <path d="M20 14.5C20 14.5 17 13.5 13 13.5C9 13.5 5.5 14 3.5 15C2.5 15.5 2 16.5 2 17.5C2 18.5 2.5 19.5 4 20L11 21C11 21 16 21.5 19.5 20.5C21.5 19.9 22.5 18 22.5 16.5C22.5 15.5 20 14.5 20 14.5Z" fill="#a4defc"/>
          <!-- Cockpit Window -->
          <path d="M5 16.5C5 16.5 6 15.5 8 15.5C8 15.5 7 17 5 16.5Z" fill="#4fa5d6"/>
          <!-- Tail -->
          <path d="M19.5 14C19.5 14 19 11 17 9L15 8.5C15 8.5 16 12 18 13.5L19.5 14Z" fill="#a4defc"/>
          <!-- Right Wing -->
          <path d="M12 13.5C12 13.5 14 9 16 8L17 8C17 8 14.5 13.5 14 13.5H12Z" fill="#75c2eb"/>
          <!-- Left Wing -->
          <path d="M10 20.5L6 26L7 26.5L13 21C13 21 11.5 20.5 10 20.5Z" fill="#88ceef"/>
          <!-- Engine -->
          <ellipse cx="10.5" cy="22" rx="1.5" ry="1" fill="#666" />
        </svg>
      </div>

      <div class="sky night-sky">
        <!-- 4-Pointed Stars -->
        <svg class="star star-1" viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg"><path d="M12 0L13.5 10.5L24 12L13.5 13.5L12 24L10.5 13.5L0 12L10.5 10.5L12 0Z"/></svg>
        <svg class="star star-2" viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg"><path d="M12 0L13.5 10.5L24 12L13.5 13.5L12 24L10.5 13.5L0 12L10.5 10.5L12 0Z"/></svg>
        <svg class="star star-3" viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg"><path d="M12 0L13.5 10.5L24 12L13.5 13.5L12 24L10.5 13.5L0 12L10.5 10.5L12 0Z"/></svg>
        <svg class="star star-4" viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg"><path d="M12 0L13.5 10.5L24 12L13.5 13.5L12 24L10.5 13.5L0 12L10.5 10.5L12 0Z"/></svg>
        <svg class="star star-5" viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg"><path d="M12 0L13.5 10.5L24 12L13.5 13.5L12 24L10.5 13.5L0 12L10.5 10.5L12 0Z"/></svg>
        <svg class="star star-6" viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg"><path d="M12 0L13.5 10.5L24 12L13.5 13.5L12 24L10.5 13.5L0 12L10.5 10.5L12 0Z"/></svg>
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
.cloud {
  position: absolute;
  background: white;
  border-radius: 50%;
}

.cloud-1 { 
  width: 44px; height: 44px; 
  left: 15px; bottom: -20px; 
  box-shadow: 0 0 8px rgba(255,255,255,0.8);
}
.cloud-2 { 
  width: 54px; height: 54px; 
  left: 35px; bottom: -25px; 
}
.cloud-3 { 
  width: 38px; height: 38px; 
  left: 65px; bottom: -15px; 
}

/* Airplane */
.airplane {
  position: absolute;
  width: 30px;
  height: 30px;
  right: 6px;
  top: 10px;
  transform: rotate(-5deg);
  opacity: 0.95;
  filter: drop-shadow(1px 2px 2px rgba(0,0,0,0.15));
  animation: fly 6s infinite alternate ease-in-out;
}

@keyframes fly {
  0% { transform: translateY(0) rotate(-5deg); }
  100% { transform: translateY(-3px) rotate(0deg); }
}

/* Stars */
.star {
  position: absolute;
  animation: twinkle 3s infinite alternate ease-in-out;
}

.star-1 { width: 10px; height: 10px; top: 8px; left: 24px; animation-delay: 0s; }
.star-2 { width: 5px; height: 5px; top: 22px; left: 16px; animation-delay: 1s; }
.star-3 { width: 8px; height: 8px; top: 12px; left: 38px; animation-delay: 0.5s; }
.star-4 { width: 4px; height: 4px; top: 26px; left: 34px; animation-delay: 1.5s; }
.star-5 { width: 6px; height: 6px; top: 16px; left: 52px; animation-delay: 0.8s; }
.star-6 { width: 9px; height: 9px; top: 6px; left: 62px; animation-delay: 1.2s; }

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
