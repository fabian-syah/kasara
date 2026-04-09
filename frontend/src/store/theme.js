import { defineStore } from 'pinia'
import { ref, watch } from 'vue'

export const useThemeStore = defineStore('theme', () => {
    // State
    const isDark = ref(localStorage.getItem('theme_is_dark') === 'true')
    const fontSize = ref(localStorage.getItem('theme_font_size') || 'standard')

    // Actions
    function toggleDarkMode() {
        isDark.value = !isDark.value
        applyTheme()
    }

    function setFontSize(size) {
        fontSize.value = size
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

        // Handle Font Size
        html.classList.remove('font-small', 'font-standard', 'font-big')
        html.classList.add(`font-${fontSize.value}`)

        // Persist
        localStorage.setItem('theme_is_dark', isDark.value)
        localStorage.setItem('theme_font_size', fontSize.value)
    }

    // Initialize
    applyTheme()

    return {
        isDark,
        fontSize,
        toggleDarkMode,
        setFontSize,
        applyTheme
    }
})
