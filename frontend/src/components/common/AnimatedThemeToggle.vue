<template>
  <button
    @click="themeStore.toggleDarkMode"
    class="theme-toggle"
    :class="{ 'is-dark': themeStore.isDark }"
    :aria-label="themeStore.isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
  >
    <div class="toggle-bg">
      <div class="bg-layer bg-day"></div>
      <div class="bg-layer bg-night"></div>
      
      <!-- Day Decor -->
      <div class="decor day-decor">
        <!-- Plane -->
        <div class="plane">
          <svg viewBox="0 0 24 24" fill="white" width="12" height="12">
            <path d="M21 16v-2l-8-5V3.5c0-.83-.67-1.5-1.5-1.5S10 2.67 10 3.5V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5l8 2.5z"/>
          </svg>
        </div>

        <!-- Left Cloud -->
        <div class="cloud-group cg-1">
          <div class="cloud-bump b-1"></div>
          <div class="cloud-bump b-2"></div>
          <div class="cloud-base"></div>
        </div>
        <!-- Right Cloud -->
        <div class="cloud-group cg-2">
          <div class="cloud-bump b-1"></div>
          <div class="cloud-bump b-2"></div>
          <div class="cloud-base"></div>
        </div>
      </div>

      <!-- Night Decor -->
      <div class="decor night-decor">
        <svg class="star s-1" viewBox="0 0 24 24" fill="#a4b9e6"><path d="M12 2l2.4 7.4h7.6l-6.2 4.5 2.4 7.4-6.2-4.6-6.2 4.6 2.4-7.4-6.2-4.5h7.6z"/></svg>
        <svg class="star s-2" viewBox="0 0 24 24" fill="#c4d2ef"><path d="M12 2l2.4 7.4h7.6l-6.2 4.5 2.4 7.4-6.2-4.6-6.2 4.6 2.4-7.4-6.2-4.5h7.6z"/></svg>
        <svg class="star s-3" viewBox="0 0 24 24" fill="#a4b9e6"><path d="M12 2l2.4 7.4h7.6l-6.2 4.5 2.4 7.4-6.2-4.6-6.2 4.6 2.4-7.4-6.2-4.5h7.6z"/></svg>
        <div class="dot d-1"></div>
        <div class="dot d-2"></div>
      </div>
    </div>
    
    <div class="toggle-thumb-container">
      <div class="thumb sun"></div>
      <div class="thumb moon">
        <div class="crater c-1"></div>
        <div class="crater c-2"></div>
        <div class="crater c-3"></div>
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
  width: 100px;
  height: 50px;
  border-radius: 50px;
  border: none;
  cursor: pointer;
  overflow: hidden;
  padding: 0;
  outline: none;
  background: transparent;
  flex-shrink: 0;
  box-shadow: inset 0 4px 8px rgba(0, 0, 0, 0.2);
}

.toggle-bg {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  border-radius: 50px;
  overflow: hidden;
  z-index: 1;
}

.bg-layer {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  transition: opacity 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

.bg-day {
  background: linear-gradient(180deg, #59d4c7 0%, #fbe19e 100%);
  opacity: 1;
}
.theme-toggle.is-dark .bg-day {
  opacity: 0;
}

.bg-night {
  background: linear-gradient(180deg, #232f58 0%, #7d94c1 100%);
  opacity: 0;
}
.theme-toggle.is-dark .bg-night {
  opacity: 1;
}

.decor {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  transition: opacity 0.5s ease, transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

.day-decor {
  opacity: 1;
  transform: translateY(0);
}
.theme-toggle.is-dark .day-decor {
  opacity: 0;
  transform: translateY(15px);
}

/* Plane Animation */
.plane {
  position: absolute;
  left: -20px;
  top: 15px;
  opacity: 0.8;
  animation: fly 12s linear infinite;
  z-index: 2;
}

@keyframes fly {
  0% { transform: translateX(0) translateY(0) rotate(10deg); }
  25% { transform: translateX(40px) translateY(-5px) rotate(5deg); }
  50% { transform: translateX(80px) translateY(0) rotate(0deg); }
  75% { transform: translateX(120px) translateY(5px) rotate(-5deg); }
  100% { transform: translateX(160px) translateY(0) rotate(-10deg); }
}

.cloud-group {
  position: absolute;
  transition: transform 0.8s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.cg-1 { left: 15px; top: 25px; transform: scale(1); }
.cg-2 { left: 45px; top: 12px; transform: scale(0.7); }

.theme-toggle:hover .cg-1 { transform: scale(1.1) translateX(2px); }
.theme-toggle:hover .cg-2 { transform: scale(0.8) translateX(-2px); }

.cloud-bump {
  position: absolute;
  background: #ffffff;
  border-radius: 50%;
  box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}
.cg-1 .b-1 { width: 14px; height: 14px; left: 0; bottom: 0; }
.cg-1 .b-2 { width: 20px; height: 20px; left: 10px; bottom: 0; }
.cg-1 .cloud-base { width: 26px; height: 10px; left: 2px; bottom: 0; position: absolute; background: #ffffff; border-radius: 10px; }

.cg-2 .b-1 { width: 12px; height: 12px; left: 0; bottom: 0; }
.cg-2 .b-2 { width: 16px; height: 16px; left: 8px; bottom: 0; }
.cg-2 .cloud-base { width: 20px; height: 8px; left: 2px; bottom: 0; position: absolute; background: #ffffff; border-radius: 8px; }

.night-decor {
  opacity: 0;
  transform: translateY(-15px);
}
.theme-toggle.is-dark .night-decor {
  opacity: 1;
  transform: translateY(0);
}

.star {
  position: absolute;
  filter: drop-shadow(0 0 2px rgba(255,255,255,0.4));
  animation: starPulse 4s infinite alternate;
}
.s-1 { width: 12px; height: 12px; right: 35px; top: 12px; animation-delay: 0s; }
.s-2 { width: 8px; height: 8px; right: 20px; top: 8px; animation-delay: 1s; }
.s-3 { width: 6px; height: 6px; right: 26px; top: 22px; animation-delay: 0.5s; }

.dot {
  position: absolute;
  background: #ffffff;
  border-radius: 50%;
  opacity: 0.6;
}
.d-1 { width: 2px; height: 2px; right: 45px; top: 10px; }
.d-2 { width: 1.5px; height: 1.5px; right: 15px; top: 20px; }

@keyframes starPulse {
  0% { opacity: 0.4; transform: scale(0.8); }
  100% { opacity: 1; transform: scale(1.1); }
}

.toggle-thumb-container {
  position: absolute;
  top: 5px;
  left: 5px;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  z-index: 10;
  transform: translateX(50px);
  transition: transform 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.theme-toggle.is-dark .toggle-thumb-container {
  transform: translateX(0px);
}

.thumb {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  border-radius: 50%;
  transition: opacity 0.4s ease, transform 0.6s ease;
}

.sun {
  background-color: #fb9a2e;
  background-image: linear-gradient(155deg, rgba(255,255,255,0.25) 0%, transparent 70%);
  border: 3px solid #f67a3a;
  box-shadow: 0 4px 10px rgba(246, 122, 58, 0.4);
  box-sizing: border-box;
  opacity: 1;
  transform: rotate(0deg) scale(1);
}

.theme-toggle.is-dark .sun {
  opacity: 0;
  transform: rotate(90deg) scale(0.5);
}

.moon {
  background: #dbe3f3;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
  opacity: 0;
  transform: rotate(-90deg) scale(0.5);
  overflow: hidden;
}

.theme-toggle.is-dark .moon {
  opacity: 1;
  transform: rotate(0deg) scale(1);
}

.crater {
  position: absolute;
  background: #c4d0ec;
  border-radius: 50%;
  box-shadow: inset 1px 1px 2px rgba(0,0,0,0.1);
}
.c-1 { width: 10px; height: 10px; top: 12px; left: 8px; }
.c-2 { width: 12px; height: 12px; bottom: 6px; right: 8px; }
.c-3 { width: 6px; height: 6px; top: 6px; right: 12px; }

</style>
