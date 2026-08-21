import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api, { auth as authApi } from '../api/axios'
import { getPermissionsForRole } from '../utils/permissions'

export const useAuthStore = defineStore('auth', () => {
    // State
    const user = ref(null)
    const token = ref(localStorage.getItem('auth_token') || null)
    const isLoading = ref(false)
    const error = ref(null)

    // Getters
    const isAuthenticated = computed(() => !!token.value && !!user.value)

    // Adapt to flexible role structure (Spatie returns roles array)
    const userRole = computed(() => {
        if (user.value?.roles && user.value.roles.length > 0) {
            return user.value.roles[0].name
        }
        return user.value?.role || null
    })

    const userName = computed(() => user.value?.full_name || user.value?.name || 'Guest')
    const userBranch = computed(() => user.value?.branch || null)

    const hasPermission = computed(() => (permission) => {
        if (!user.value?.permissions) return false
        return user.value.permissions.includes('*') || user.value.permissions.includes(permission)
    })

    const hasRole = computed(() => (role) => {
        if (!user.value?.roles) return false
        const userRoles = user.value.roles.map(r => r.name);
        if (Array.isArray(role)) {
            return role.some(r => userRoles.includes(r))
        }
        return userRoles.includes(role);
    })

    // Helper to enrich user object with permissions
    // Backend is the authoritative source of truth for permissions.
    // If backend returns permissions, use them. Otherwise fall back to local role config.
    // This ensures the app works even before Spatie permissions are fully seeded in the database.
    const enrichUserPermissions = (userData) => {
        if (!userData) return null;

        let permissions = userData.permissions || [];

        // If backend returned permissions, use them as source of truth
        if (permissions.length > 0) {
            return { ...userData, permissions };
        }

        // Fallback: derive permissions from role using local config
        // This keeps the app functional until Spatie permissions are fully configured
        const roleName = userData.roles?.[0]?.name || userData.role;
        if (roleName) {
            permissions = getPermissionsForRole(roleName);
        }

        return { ...userData, permissions };
    }

    // Actions
    async function login(credentials) {
        isLoading.value = true
        error.value = null

        try {
            const response = await authApi.login(credentials)
            const { token: authToken, user: rawUser } = response.data

            if (!authToken || !rawUser) {
                throw new Error('Invalid response from server')
            }

            const userData = enrichUserPermissions(rawUser)

            // Set auth data
            token.value = authToken
            user.value = userData

            // Persist to localStorage
            localStorage.setItem('auth_token', authToken)
            localStorage.setItem('user', JSON.stringify(userData))

            return { success: true, user: userData }
        } catch (err) {
            // Log full error only if it's an unexpected error (not 401/422)
            if (err.response?.status !== 401 && err.response?.status !== 422) {
                console.error('Login Exception:', err);
            }
            error.value = err.response?.data?.message || err.message || 'Login failed'
            
            // Pass rate limit info if 429
            if (err.response?.status === 429) {
                const retryAfter = parseInt(err.response.headers?.['retry-after'] || err.response.data?.retry_after || 60);
                return { success: false, error: error.value, status: 429, retryAfter }
            }
            
            return { success: false, error: error.value }
        } finally {
            isLoading.value = false
        }
    }

    async function logout() {
        isLoading.value = true

        try {
            await authApi.logout()
        } catch (err) {
            console.error('Logout error:', err)
        } finally {
            // Clear auth state
            token.value = null
            user.value = null
            localStorage.removeItem('auth_token')
            localStorage.removeItem('user')
            localStorage.removeItem('current_branch_id')
            isLoading.value = false
        }
    }

    async function fetchUser() {
        if (!token.value) return null

        isLoading.value = true

        try {
            const response = await authApi.me()
            // Some APIs return wrapped in 'data', others flat. Adjust as needed.
            // Based on previous code: response.data.user
            const rawUser = response.data.user || response.data

            const userData = enrichUserPermissions(rawUser)

            user.value = userData
            localStorage.setItem('user', JSON.stringify(userData))
            return userData
        } catch (err) {
            // Token might be invalid
            token.value = null
            user.value = null
            localStorage.removeItem('auth_token')
            localStorage.removeItem('user')
            return null
        } finally {
            isLoading.value = false
        }
    }

    function setBranch(branchId) {
        localStorage.setItem('current_branch_id', branchId)
    }



    async function updateFontSize(size) {
        const response = await authApi.updateFontSize(size)
        return response.data
    }

    // Initialize - try to restore user from localStorage
    function initialize() {
        const savedUserHash = localStorage.getItem('user')
        if (savedUserHash && token.value) {
            try {
                let savedUser = JSON.parse(savedUserHash)
                // Re-enrich just in case
                savedUser = enrichUserPermissions(savedUser)
                user.value = savedUser
            } catch (e) {
                console.error("Failed to parse saved user", e)
            }
        }
    }

    const storageBaseUrl = computed(() => {
        const url = import.meta.env.VITE_API_URL || import.meta.env.VITE_API_BASE_URL || '';
        return url.replace(/\/api\/?$/, '');
    });

    const userPhotoUrl = computed(() => {
        if (!user.value?.photo) return null;
        const baseUrl = user.value.photo.startsWith('http')
            ? user.value.photo
            : `${storageBaseUrl.value}/storage/${user.value.photo}`;
        
        // Append timestamp to bust cache based on last update or current execution context
        const ts = user.value.updated_at 
            ? new Date(user.value.updated_at).getTime() 
            : Date.now();
            
        return `${baseUrl}${baseUrl.includes('?') ? '&' : '?'}t=${ts}`;
    });

    // Listen for storage changes from other tabs to handle multi-tab session sync
    if (typeof window !== 'undefined') {
        window.addEventListener('storage', (event) => {
            if (event.key === 'auth_token') {
                // Token changed or removed - must reload to sync session state
                window.location.reload();
            } else if (event.key === 'user' && event.newValue) {
                try {
                    const newUser = JSON.parse(event.newValue);
                    const oldUser = event.oldValue ? JSON.parse(event.oldValue) : null;
                    
                    // If no old user or if roles/permissions changed, we must reload for safety
                    if (!oldUser || 
                        JSON.stringify(newUser.roles) !== JSON.stringify(oldUser.roles) || 
                        JSON.stringify(newUser.permissions) !== JSON.stringify(oldUser.permissions)) {
                        window.location.reload();
                        return;
                    }
                    
                    // If only UI preferences like font_size changed, just update the state 
                    // without reloading. This avoids interrupting the user's flow.
                    const enriched = enrichUserPermissions(newUser);
                    user.value = enriched;
                } catch (e) {
                    console.error('Failed to parse user from storage event', e);
                    window.location.reload();
                }
            }
        });
    }

    // Call initialize on store creation
    initialize()

    return {
        // State
        user,
        token,
        isLoading,
        error,
        // Getters
        isAuthenticated,
        userRole,
        userName,
        userBranch,
        hasPermission,
        hasRole,
        // Actions
        login,
        logout,
        fetchUser,
        setBranch,

        updateFontSize,
        initialize,
        updateUserData(userData) {
            if (!userData) return;
            const enriched = enrichUserPermissions(userData);
            user.value = enriched;
            localStorage.setItem('user', JSON.stringify(enriched));
        },
        storageBaseUrl,
        userPhotoUrl
    }
})
