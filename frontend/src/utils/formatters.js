// Currency and number formatting utilities

/**
 * Get current date adjusted for 5 AM reset logic
 * @returns {Date}
 */
export function getLogicalDate() {
    const now = new Date();
    if (now.getHours() < 5) now.setDate(now.getDate() - 1);
    return now;
}

/**
 * Get logical today in YYYY-MM-DD format
 * @returns {string}
 */
export function getTodayLocal() {
    const d = getLogicalDate();
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

/**
 * Format number as Indonesian Rupiah
 * @param {number} amount - The amount to format
 * @param {object} options - Formatting options
 * @returns {string} Formatted currency string
 */
export function formatCurrency(amount, options = {}) {
    const {
        symbol = 'Rp',
        decimals = 0,
        thousandSep = '.',
        decimalSep = ','
    } = options

    if (amount === null || amount === undefined || isNaN(amount)) return `${symbol} 0`

    const fixed = Math.abs(amount).toFixed(decimals)
    const parts = fixed.split('.')
    const intPart = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousandSep)
    const decPart = parts[1] ? `${decimalSep}${parts[1]}` : ''

    const sign = amount < 0 ? '-' : ''
    return `${sign}${symbol} ${intPart}${decPart}`
}

/**
 * Format number with thousand separators
 * @param {number} num - The number to format
 * @returns {string} Formatted number string
 */
export function formatNumber(num) {
    if (num === null || num === undefined) return '0'
    const n = typeof num === 'string' ? parseFloat(num) : num
    if (isNaN(n)) return '0'
    // For IDR, we usually show 0 decimals
    return Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.')
}

/**
 * Parse a currency string (IDR) into a number
 * Handles thousands separators (.) and decimals (, or .)
 * @param {string|number} val - Value to parse
 * @returns {number} Parsed number
 */
export function parseCurrency(val) {
    if (typeof val === 'number') return val;
    if (!val) return 0;

    let str = val.toString().trim();
    
    // Remove currency symbol and whitespace
    str = str.replace(/Rp\s?/g, '');

    // For Rupiah, we usually don't have decimals. 
    // If there's a comma (standard ID decimal) at the end followed by exactly 2 zeros, strip it.
    str = str.replace(/,00$/, '');

    // NEW LOGIC: Just strip everything that is not a digit.
    // This is the safest for IDR input fields where dots are thousands separators.
    const clean = str.replace(/\D/g, '');
    
    const result = parseInt(clean, 10);
    return isNaN(result) ? 0 : result;
}

/**
 * Format date to Indonesian locale
 * @param {Date|string} date - Date to format
 * @param {string} format - Format type: 'short', 'long', 'time', 'datetime'
 * @returns {string} Formatted date string
 */
export function formatDate(date, format = 'short') {
    if (!date) return '-'

    // Handle space instead of T for better browser compatibility
    const dateStr = typeof date === 'string' ? date.replace(' ', 'T') : date
    const d = new Date(dateStr)

    if (isNaN(d.getTime())) return '-'

    const options = {
        short: { day: '2-digit', month: '2-digit', year: 'numeric' },
        long: { day: 'numeric', month: 'long', year: 'numeric' },
        time: { hour: '2-digit', minute: '2-digit' },
        datetime: { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }
    }

    return d.toLocaleDateString('id-ID', options[format] || options.short)
}

/**
 * Format relative time (e.g., "5 menit yang lalu")
 * @param {Date|string} date - Date to format
 * @returns {string} Relative time string
 */
export function formatRelativeTime(date) {
    const d = new Date(date)
    const now = new Date()
    const diff = now - d

    const seconds = Math.floor(diff / 1000)
    const minutes = Math.floor(seconds / 60)
    const hours = Math.floor(minutes / 60)
    const days = Math.floor(hours / 24)

    if (seconds < 60) return 'Baru saja'
    if (minutes < 60) return `${minutes} menit yang lalu`
    if (hours < 24) return `${hours} jam yang lalu`
    if (days < 7) return `${days} hari yang lalu`

    return formatDate(date, 'short')
}

/**
 * Generate a unique transaction ID
 * @returns {string} Transaction ID
 */
export function generateTransactionId() {
    const date = new Date()
    const dateStr = date.toISOString().slice(0, 10).replace(/-/g, '')
    const random = Math.random().toString(36).substring(2, 8).toUpperCase()
    return `TRX-${dateStr}-${random}`
}

/**
 * Truncate text with ellipsis
 * @param {string} text - Text to truncate
 * @param {number} maxLength - Maximum length
 * @returns {string} Truncated text
 */
export function truncate(text, maxLength = 50) {
    if (!text) return ''
    if (text.length <= maxLength) return text
    return text.substring(0, maxLength) + '...'
}

/**
 * Debounce function
 * @param {function} fn - Function to debounce
 * @param {number} delay - Delay in ms
 * @returns {function} Debounced function
 */
export function debounce(fn, delay = 300) {
    let timeoutId
    return function (...args) {
        clearTimeout(timeoutId)
        timeoutId = setTimeout(() => fn.apply(this, args), delay)
    }
}

/**
 * Calculate percentage change
 * @param {number} current - Current value
 * @param {number} previous - Previous value
 * @returns {number} Percentage change
 */
export function percentageChange(current, previous) {
    if (previous === 0) return current > 0 ? 100 : 0
    return ((current - previous) / previous) * 100
}
