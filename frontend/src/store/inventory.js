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
    const pagination = ref({
        current_page: 1,
        last_page: 1,
        total: 0,
        per_page: 20
    })
    const stateTotalValue = ref(0)

    // Mock data for demo
    // ... (keep mockProducts and mockCategories as is, they are not used in main flow but good for valid js)

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

    const totalProducts = computed(() => pagination.value.total)

    const totalValue = computed(() => stateTotalValue.value)

    // Actions
    async function fetchProducts(params = {}) {
        isLoading.value = true
        try {
            const m = await import('../api/axios');
            const response = await m.inventory.list(params);

            // Backend returns pagination object { current_page, data: [...], ... }
            if (response.data.data) {
                products.value = response.data.data
                pagination.value = {
                    current_page: response.data.current_page,
                    last_page: response.data.last_page,
                    total: response.data.total,
                    per_page: response.data.per_page,
                    from: response.data.from,
                    to: response.data.to
                }
                // Set Global Total Value from Backend
                stateTotalValue.value = response.data.total_value || 0
            } else {
                products.value = response.data
                // Reset pagination if array returned (should not happen with standard Laravel paginate)
            }

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
        handleStockOut,
        pagination
    }
})
