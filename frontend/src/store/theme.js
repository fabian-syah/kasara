import { defineStore } from 'pinia'
import { ref, watch } from 'vue'
import { useAuthStore } from './auth'

export const useThemeStore = defineStore('theme', () => {
    const authStore = useAuthStore()

    // State
    const isDark = ref(localStorage.getItem('theme_is_dark') === 'true')
    const fontSize = ref(localStorage.getItem('theme_font_size') || 'standard')

    // Watch for user changes (login/fetch) to sync DB preference
    watch(() => authStore.user?.font_size, (newSize) => {
        if (newSize && newSize !== fontSize.value) {
            fontSize.value = newSize
            applyTheme()
        }
    }, { immediate: true })

    // Actions
    function toggleDarkMode() {
        isDark.value = !isDark.value
        applyTheme()
    }

    async function setFontSize(size) {
        fontSize.value = size
        applyTheme()

        // Sync with database if logged in
        if (authStore.isAuthenticated) {
            try {
                const response = await authStore.updateFontSize(size)
                if (response.success) {
                    authStore.updateUserData(response.user)
                }
            } catch (err) {
                console.error('Failed to sync font size to DB:', err)
            }
        }
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

        // Persist to local for instant load next time
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
