import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useInventoryStore = defineStore('inventory', () => {
    // State
    const products = ref([])
    const categories = ref([])
    const isLoading = ref(false)
    const searchQuery = ref('')
    const selectedCategory = ref(null)
    const sortBy = ref('name')
    const sortOrder = ref('asc')

    // Mock data for demo
    const mockProducts = [
        { id: 1, name: 'iPhone 15 Pro Max 256GB', sku: 'IPH15PM256', category: 'Smartphone', brand: 'Apple', price: 21999000, stock: 15, minStock: 5, image: null },
        { id: 2, name: 'iPhone 15 Pro 128GB', sku: 'IPH15P128', category: 'Smartphone', brand: 'Apple', price: 18499000, stock: 22, minStock: 5, image: null },
        { id: 3, name: 'Samsung Galaxy S24 Ultra', sku: 'SGS24U', category: 'Smartphone', brand: 'Samsung', price: 19999000, stock: 18, minStock: 5, image: null },
        { id: 4, name: 'Samsung Galaxy Z Fold 5', sku: 'SGZF5', category: 'Smartphone', brand: 'Samsung', price: 25999000, stock: 8, minStock: 3, image: null },
        { id: 5, name: 'MacBook Air M3 13"', sku: 'MBAM3-13', category: 'Laptop', brand: 'Apple', price: 17499000, stock: 12, minStock: 3, image: null },
        { id: 6, name: 'MacBook Pro M3 14"', sku: 'MBPM3-14', category: 'Laptop', brand: 'Apple', price: 28999000, stock: 6, minStock: 2, image: null },
        { id: 7, name: 'iPad Pro 12.9" M2', sku: 'IPDP12M2', category: 'Tablet', brand: 'Apple', price: 16999000, stock: 10, minStock: 3, image: null },
        { id: 8, name: 'AirPods Pro 2nd Gen', sku: 'APP2', category: 'Audio', brand: 'Apple', price: 3999000, stock: 45, minStock: 10, image: null },
        { id: 9, name: 'Apple Watch Ultra 2', sku: 'AWU2', category: 'Wearable', brand: 'Apple', price: 13999000, stock: 14, minStock: 5, image: null },
        { id: 10, name: 'Samsung Galaxy Watch 6', sku: 'SGW6', category: 'Wearable', brand: 'Samsung', price: 4499000, stock: 25, minStock: 8, image: null },
        { id: 11, name: 'Sony WH-1000XM5', sku: 'SONYXM5', category: 'Audio', brand: 'Sony', price: 5499000, stock: 20, minStock: 5, image: null },
        { id: 12, name: 'Google Pixel 8 Pro', sku: 'GP8P', category: 'Smartphone', brand: 'Google', price: 14999000, stock: 3, minStock: 5, image: null },
    ]

    const mockCategories = [
        { id: 1, name: 'Smartphone', count: 5 },
        { id: 2, name: 'Laptop', count: 2 },
        { id: 3, name: 'Tablet', count: 1 },
        { id: 4, name: 'Audio', count: 2 },
        { id: 5, name: 'Wearable', count: 2 },
    ]

    // Getters
    // Getters
    const filteredProducts = computed(() => {
        let result = [...products.value]

        // Filter by search
        if (searchQuery.value) {
            const query = searchQuery.value.toLowerCase()
            result = result.filter(item =>
                item.imei?.toLowerCase().includes(query) ||
                item.product?.name?.toLowerCase().includes(query) ||
                item.product?.sku?.toLowerCase().includes(query) ||
                item.product?.brand?.toLowerCase().includes(query)
            )
        }

        // Filter by category
        if (selectedCategory.value) {
            result = result.filter(item => item.product?.category === selectedCategory.value)
        }

        // Sort
        result.sort((a, b) => {
            let comparison = 0
            if (sortBy.value === 'name') {
                const nameA = a.product?.name || '';
                const nameB = b.product?.name || '';
                comparison = nameA.localeCompare(nameB)
            } else if (sortBy.value === 'price') {
                comparison = (a.selling_price || 0) - (b.selling_price || 0)
            }
            return sortOrder.value === 'asc' ? comparison : -comparison
        })

        return result
    })

    const lowStockProducts = computed(() => []) // Not applicable for granular items
    const outOfStockProducts = computed(() => []) // Not applicable

    const totalProducts = computed(() => products.value.length)

    const totalValue = computed(() =>
        products.value.reduce((total, item) => total + parseFloat(item.selling_price || 0), 0)
    )

    // Actions
    async function fetchProducts(params = {}) {
        isLoading.value = true
        try {
            const m = await import('../api/axios');
            const response = await m.inventory.list(params);

            // Backend returns pagination object { current_page, data: [...], ... }
            // We need the data array.
            products.value = response.data.data ? response.data.data : response.data

            // Categories logic (optional or separate API)
            // categories.value = ...
        } catch (error) {
            console.error('Failed to fetch products:', error)
        } finally {
            isLoading.value = false
        }
    }

    function setSearchQuery(query) {
        searchQuery.value = query
    }

    function setCategory(category) {
        selectedCategory.value = category
    }

    function setSorting(field, order = 'asc') {
        sortBy.value = field
        sortOrder.value = order
    }

    function updateStock(productId, newStock) {
        const product = products.value.find(p => p.id === productId)
        if (product) {
            product.stock = newStock
        }
    }

    function addProduct(productData) {
        const newId = Math.max(...products.value.map(p => p.id)) + 1
        products.value.push({ ...productData, id: newId })
    }

    function updateProduct(productId, updates) {
        const index = products.value.findIndex(p => p.id === productId)
        if (index > -1) {
            products.value[index] = { ...products.value[index], ...updates }
        }
    }

    function deleteProduct(productId) {
        const index = products.value.findIndex(p => p.id === productId)
        if (index > -1) {
            products.value.splice(index, 1)
        }
    }

    // Real-time Actions
    function pushNewProduct(newProduct) {
        // Determine unique ID key (works for both ProductDetail and Inventory models if they have 'id')
        // Check if item already exists (Upsert)
        const index = products.value.findIndex(p => p.id === newProduct.id);

        if (index > -1) {
            // Update existing
            products.value[index] = { ...products.value[index], ...newProduct };
        } else {
            // Add new to top
            products.value.unshift(newProduct);
            // Optional: You might want to remove the last item if pagination is strict, 
            // but for real-time 'live' feel, accumulating is usually fine until refresh.
        }
    }

    function handleStockOut(data) {
        // data can be a StockOut object which contains items
        if (!data) return;

        // For HP items (items relation)
        if (Array.isArray(data.items)) {
            data.items.forEach(item => {
                // Item here is ProductDetail (from StockOut->items relation)
                // We want to update the status of this item in our list to 'sold' or 'out'
                // The StockOut controller updates status to 'sold', 'transfer', etc.
                // But the event payload $stockOut->items might just contain the pivot or the current state.
                // Actually StockOutController: $stockOut->items()->attach($detail->id);
                // $stockOut->load(['items.product']) returns the ProductDetail models.
                // Their status should be updated in DB by Controller before dispatch.
                // So 'item' should have the new status.

                updateProduct(item.id, { status: item.status });
            });
        }

        // For Non-HP items
        let nonHpItems = data.non_hp_items;
        if (typeof nonHpItems === 'string') {
            try { nonHpItems = JSON.parse(nonHpItems); } catch (e) { nonHpItems = []; }
        }

        if (Array.isArray(nonHpItems)) {
            nonHpItems.forEach(item => {
                // Find inventory record by product_id
                // Note: This matches assuming the user looking is the one who owns the inventory or viewing the same placement
                // Since StockOutEvent is broadcasted to 'stock-out' channel, everyone receives it.
                // We should only update if our local list contains this product_id.
                const productInStore = products.value.find(p => p.product_id === item.product_id);
                if (productInStore) {
                    // Update quantity
                    // Ensure we don't go below 0 visually
                    const newQty = Math.max(0, parseInt(productInStore.quantity) - parseInt(item.quantity));
                    updateProduct(productInStore.id, { quantity: newQty });
                }
            });
        }
    }

    return {
        // State
        products,
        categories,
        isLoading,
        searchQuery,
        selectedCategory,
        sortBy,
        sortOrder,
        // Getters
        filteredProducts,
        lowStockProducts,
        outOfStockProducts,
        totalProducts,
        totalValue,
        // Actions
        fetchProducts,
        setSearchQuery,
        setCategory,
        setSorting,
        updateStock,
        addProduct,
        updateProduct,
        deleteProduct,
        pushNewProduct,
        handleStockOut
    }
})
