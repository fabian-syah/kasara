import axios from 'axios'

// Create axios instance with default config
// Create axios instance with default config
const api = axios.create({
    baseURL: import.meta.env.VITE_API_BASE_URL || import.meta.env.VITE_API_URL || 'https://api.stokps.com/api',
    timeout: 60000,
    withCredentials: true,
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
    }
})

// Request interceptor - add auth token
api.interceptors.request.use(
    (config) => {
        const token = localStorage.getItem('auth_token')
        if (token) {
            config.headers.Authorization = `Bearer ${token}`
        }

        // Add branch context if available
        const branchId = localStorage.getItem('current_branch_id')
        if (branchId) {
            config.headers['X-Branch-ID'] = branchId
        }

        return config
    },
    (error) => {
        return Promise.reject(error)
    }
)

// Response interceptor - handle errors
api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response) {
            switch (error.response.status) {
                case 401:
                    // Check if it's a login attempt failure, don't redirect
                    if (error.config.url.includes('/login')) {
                        break;
                    }
                    // Unauthorized - clear auth and redirect to login
                    localStorage.removeItem('auth_token')
                    localStorage.removeItem('user')
                    window.location.href = '/login'
                    break
                case 403:
                    // Forbidden - user does not have permission
                    console.error('Access forbidden:', error.response.data.message)
                    break
                case 422:
                    // Validation error - return as-is for form handling
                    break
                case 500:
                    console.error('Server error:', error.response.data.message)
                    break
            }
        } else if (error.request) {
            console.error('Network error - no response received')
        }

        return Promise.reject(error)
    }
)

export default api

// API endpoints helper functions
export const auth = {
    login: (credentials) => api.post('/login', credentials),
    logout: () => api.post('/logout'),
    verifyPassword: (password) => api.post('/verify-password', { password }),
    user: () => api.get('/user'),
    refresh: () => api.post('/refresh'),
    setPin: (pin) => api.post('/pin/set', { transaction_pin: pin }),
    updatePin: (current_pin, new_pin) => api.post('/pin/update', { current_pin, new_pin }),
    togglePin: (pin) => api.post('/pin/toggle', { transaction_pin: pin }),
    verifyPin: (pin) => api.post('/pin/verify', { transaction_pin: pin }),
    requestResetPin: () => api.post('/pin/request-reset'),
    updateFontSize: (size) => api.post('/settings/font-size', { font_size: size })
}

export const products = {
    list: (params) => api.get('/products', { params }),
    get: (id) => api.get(`/products/${id}`),
    create: (data) => api.post('/products', data),
    update: (id, data) => api.put(`/products/${id}`, data),
    delete: (id) => api.delete(`/products/${id}`),
    search: (query) => api.get('/products/search', { params: { q: query } })
}

export const transactions = {
    list: (params) => api.get('/transactions', { params }),
    get: (id) => api.get(`/transactions/${id}`),
    create: (data) => api.post('/transactions', data),
    void: (id, reason) => api.post(`/transactions/${id}/void`, { reason })
}

export const inventory = {
    list: (params) => api.get('/inventory', { params }),
    update: (id, data) => api.put(`/inventory/${id}`, data),
    transfer: (data) => api.post('/inventory/transfer', data),
    stockIn: (data) => api.post('/inventory/stock-in', data),
    stockOut: (data) => api.post('/inventory/stock-out', data),
    historyIn: (params) => api.get('/inventory/history/in', { params }),
    historyOut: (params) => api.get('/inventory/history/out', { params }),
    failedInputs: (params) => api.get('/inventory/failed-inputs', { params }),
    exportHistoryIn: (params) => api.get('/inventory/history/in/export', { params, responseType: 'blob' }),
    exportHistoryOut: (params) => api.get('/inventory/history/out/export', { params, responseType: 'blob' }),
    getProductsLookup: (params) => api.get('/inventory/products-lookup', { params }),
    createAccount: (data) => api.post('/inventory/account', data),
    lookupPrice: (data) => api.post('/product-prices/lookup', data),
    updateAccount: (id, data) => api.post(`/inventory/account/${id}/update`, data, { headers: { 'Content-Type': 'multipart/form-data' } }),
    myAccounts: () => api.get('/inventory/my-accounts'),
    togglePin: (id, pin) => api.post(`/inventory/account/${id}/toggle-pin`, { transaction_pin: pin }),
    requestResetPin: (id) => api.post(`/inventory/account/${id}/request-reset`),
    listPendingPhotos: () => api.get('/inventory/accounts/pending-photos'),
    approvePhoto: (id) => api.post(`/inventory/account/${id}/approve-photo`),
    rejectPhoto: (id, reason) => api.post(`/inventory/account/${id}/reject-photo`, { reason }),
    deleteAccount: (id) => api.delete(`/inventory/account/${id}`),
    voidStockIn: (id, type) => api.delete(`/inventory/history/in/${id}/void`, { params: { type } })
}

export const stockOut = {
    list: (params) => api.get('/stock-outs', { params }),
    create: (data) => api.post('/stock-outs', data),
    get: (id) => api.get(`/stock-outs/${id}`),
    track: (query) => api.get('/track', { params: { q: query } })
}

export const users = {
    list: (params) => api.get('/users', { params }),
    get: (id) => api.get(`/users/${id}`),
    create: (data) => api.post('/users', data),
    update: (id, data) => api.put(`/users/${id}`, data),
    updateProfile: (id, data, config = {}) => api.post(`/users/${id}`, data, config),
    delete: (id) => api.delete(`/users/${id}`),
    listPendingPhotos: () => api.get('/users/pending-photos'),
    approvePhoto: (id) => api.post(`/users/${id}/approve-photo`),
    rejectPhoto: (id, reason) => api.post(`/users/${id}/reject-photo`, { reason }),
    approvePinReset: (id) => api.post(`/users/${id}/approve-pin-reset`)
}

// Tambahkan ini di src/api/axios.js
// Branch management (Fisik only)
export const branches = {
    list: (params) => api.get('/branches', { params }),
    get: (id) => api.get(`/branches/${id}`),
    create: (data) => api.post('/branches', data),
    update: (id, data) => api.put(`/branches/${id}`, data),
    delete: (id) => api.delete(`/branches/${id}`),
    toggleStatus: (id) => api.post(`/branches/${id}/toggle-status`)
}

export const warehouses = {
    list: (params) => api.get('/warehouses', { params }),
    get: (id) => api.get(`/warehouses/${id}`),
    create: (data) => api.post('/warehouses', data),
    update: (id, data) => api.put(`/warehouses/${id}`, data),
    delete: (id) => api.delete(`/warehouses/${id}`),
    toggleReturn: (id) => api.post(`/warehouses/${id}/toggle-return`)
}

export const onlineShops = {
    list: (params) => api.get('/online-shops', { params }),
    get: (id) => api.get(`/online-shops/${id}`),
    create: (data) => api.post('/online-shops', data),
    update: (id, data) => api.put(`/online-shops/${id}`, data),
    delete: (id) => api.delete(`/online-shops/${id}`)
}

export const categories = {
    list: (params) => api.get('/categories', { params }),
    get: (id) => api.get(`/categories/${id}`),
    create: (data) => api.post('/categories', data),
    update: (id, data) => api.put(`/categories/${id}`, data),
    delete: (id) => api.delete(`/categories/${id}`)
}

export const distributors = {
    list: (params) => api.get('/distributors', { params }),
    get: (id) => api.get(`/distributors/${id}`),
    create: (data) => api.post('/distributors', data),
    update: (id, data) => api.put(`/distributors/${id}`, data),
    delete: (id) => api.delete(`/distributors/${id}`),
    monitoring: () => api.get('/distributors/monitoring')
}

export const reports = {
    sales: (params) => api.get('/reports/sales', { params }),
    inventory: (params) => api.get('/reports/inventory', { params }),
    profit: (params) => api.get('/reports/profit', { params })
}

export const onlineShop = {
    scan: (code, type) => api.post('/online-shop/scan', { code, type }),
    updateOrder: (data) => api.post('/online-shop/order', data),
    inventory: (params) => api.get('/online-shop/inventory', { params }),
    analysis: () => api.get('/online-shop/analysis')
}

export const brands = {
    list: (params) => api.get('/brands', { params }),
    get: (id) => api.get(`/brands/${id}`),
    create: (data) => api.post('/brands', data),
    update: (id, data) => api.put(`/brands/${id}`, data),
    delete: (id) => api.delete(`/brands/${id}`)
}

export const productTypes = {
    list: (params) => api.get('/product-types', { params }),
    get: (id) => api.get(`/product-types/${id}`),
    create: (data) => api.post('/product-types', data),
    update: (id, data) => api.put(`/product-types/${id}`, data),
    delete: (id) => api.delete(`/product-types/${id}`)
}

export const productPrices = {
    list: (params) => api.get('/product-prices', { params }),
    get: (id) => api.get(`/product-prices/${id}`),
    create: (data) => api.post('/product-prices', data),
    update: (id, data) => api.put(`/product-prices/${id}`, data),
    delete: (id) => api.delete(`/product-prices/${id}`)
}

export const questions = {
    list: (params) => api.get('/questions', { params }),
    create: (data) => api.post('/questions', data),
    update: (id, data) => api.put(`/questions/${id}`, data),
    delete: (id) => api.delete(`/questions/${id}`)
}
