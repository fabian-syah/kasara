import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useCartStore = defineStore('cart', () => {
    // State
    const items = ref([])
    const customer = ref(null)
    const discount = ref(0)
    const discountType = ref('percentage') // 'percentage' or 'fixed'
    const paymentMethod = ref('cash')
    const notes = ref('')

    // Getters
    const itemCount = computed(() =>
        items.value.reduce((total, item) => total + item.quantity, 0)
    )

    const subtotal = computed(() =>
        items.value.reduce((total, item) => total + (item.price * item.quantity), 0)
    )

    const discountAmount = computed(() => {
        if (discountType.value === 'percentage') {
            return subtotal.value * (discount.value / 100)
        }
        return discount.value
    })

    const total = computed(() =>
        Math.max(0, subtotal.value - discountAmount.value)
    )

    const isEmpty = computed(() => items.value.length === 0)

    // Actions
    function addItem(product) {
        const existingItem = items.value.find(item => item.id === product.id)
        const availableStock = product.stock !== undefined ? product.stock : (product.quantity !== undefined ? product.quantity : 1);

        if (existingItem) {
            // Check stock before adding
            if (existingItem.quantity < availableStock) {
                existingItem.quantity++
            }
        } else {
            items.value.push({
                id: product.id,
                name: product.product?.name || product.name,
                price: product.selling_price || product.price,
                stock: availableStock,
                quantity: 1,
                image: product.image || null,
                imei: product.imei || null,
                product_id: product.product_id || product.product?.id
            })
        }
    }

    function removeItem(productId) {
        const index = items.value.findIndex(item => item.id === productId)
        if (index > -1) {
            items.value.splice(index, 1)
        }
    }

    function updateQuantity(productId, quantity) {
        const item = items.value.find(item => item.id === productId)
        if (item) {
            if (quantity <= 0) {
                removeItem(productId)
            } else if (quantity <= item.stock) {
                item.quantity = quantity
            }
        }
    }

    function incrementQuantity(productId) {
        const item = items.value.find(item => item.id === productId)
        if (item && item.quantity < item.stock) {
            item.quantity++
        }
    }

    function decrementQuantity(productId) {
        const item = items.value.find(item => item.id === productId)
        if (item) {
            if (item.quantity > 1) {
                item.quantity--
            } else {
                removeItem(productId)
            }
        }
    }

    function setCustomer(customerData) {
        customer.value = customerData
    }

    function setDiscount(amount, type = 'percentage') {
        discount.value = amount
        discountType.value = type
    }

    function setPaymentMethod(method) {
        paymentMethod.value = method
    }

    function setNotes(text) {
        notes.value = text
    }

    function clearCart() {
        items.value = []
        customer.value = null
        discount.value = 0
        discountType.value = 'percentage'
        notes.value = ''
    }

    function addBundle(bundleItems, totalPrice, description) {
        const bundleId = 'bundle-' + Date.now();
        items.value.push({
            id: bundleId,
            name: description,
            price: totalPrice,
            quantity: 1,
            is_bundle: true,
            bundle_items: bundleItems.map(item => ({
                id: item.id,
                product_id: item.product_id || item.product?.id,
                name: item.product?.name || item.name,
                imei: item.imei || null,
                price: item.selling_price || item.price,
                quantity: item.quantity || 1,
                is_non_hp: !!item.is_non_hp
            }))
        });
    }

    function getTransactionData() {
        return {
            items: items.value.map(item => ({
                product_id: item.id,
                quantity: item.quantity,
                price: item.price,
                subtotal: item.price * item.quantity,
                is_bundle: item.is_bundle || false,
                bundle_items: item.bundle_items || null
            })),
            customer_id: customer.value?.id || null,
            subtotal: subtotal.value,
            discount: discountAmount.value,
            discount_type: discountType.value,
            discount_value: discount.value,
            total: total.value,
            payment_method: paymentMethod.value,
            notes: notes.value
        }
    }

    return {
        // State
        items,
        customer,
        discount,
        discountType,
        paymentMethod,
        notes,
        // Getters
        itemCount,
        subtotal,
        discountAmount,
        total,
        isEmpty,
        // Actions
        addItem,
        addBundle,
        removeItem,
        updateQuantity,
        incrementQuantity,
        decrementQuantity,
        setCustomer,
        setDiscount,
        setPaymentMethod,
        setNotes,
        clearCart,
        getTransactionData
    }
})
