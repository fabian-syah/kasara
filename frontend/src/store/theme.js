import { defineStore } from 'pinia'
import { ref, watch } from 'vue'

export const useThemeStore = defineStore('theme', () => {
    // State
    const isDark = ref(localStorage.getItem('theme_is_dark') === 'true')

    // Actions
    function toggleDarkMode() {
        isDark.value = !isDark.value
        applyTheme()
    }

    function applyTheme() {
        const html = document.documentElement

        // Handle Dark Mode
        if (isDark.value) {
            html.classList.add('dark')
        } else {
            html.classList.remove('dark')
        }

        // Persist
        localStorage.setItem('theme_is_dark', isDark.value)
    }

    // Initialize
    applyTheme()

    return {
        isDark,
        toggleDarkMode,
        applyTheme
    }
})
