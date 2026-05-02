import { defineStore } from 'pinia'
import { ref, computed, watch } from 'vue'

export const useCartStore = defineStore('cart', () => {
    // State
    const items = ref([])
    const customer = ref(null)
    const discount = ref(0) // Global Discount value
    const discountType = ref('fixed') // 'percentage' or 'fixed'
    const paymentMethod = ref('cash')
    const notes = ref('')

    // Load from localStorage
    const savedCart = localStorage.getItem('temp_cart_state');
    if (savedCart) {
        try {
            const data = JSON.parse(savedCart);
            items.value = data.items || [];
            customer.value = data.customer || null;
            discount.value = data.discount || 0;
            discountType.value = data.discountType || 'fixed';
            paymentMethod.value = data.paymentMethod || 'cash';
            notes.value = data.notes || '';
        } catch (e) {
            console.error("Failed to restore cart", e);
        }
    }

    // Persist to localStorage
    const persist = () => {
        localStorage.setItem('temp_cart_state', JSON.stringify({
            items: items.value,
            customer: customer.value,
            discount: discount.value,
            discountType: discountType.value,
            paymentMethod: paymentMethod.value,
            notes: notes.value
        }));
    };

    // Watch for changes to persist
    watch([items, customer, discount, discountType, paymentMethod, notes], persist, { deep: true });

    // Getters
    const itemCount = computed(() =>
        items.value.reduce((total, item) => total + item.quantity, 0)
    )

    // Subtotal before any discounts (Sum of Original Price * Qty)
    const subtotal = computed(() =>
        items.value.reduce((total, item) => total + (Number(item.price || 0) * Number(item.quantity || 1)), 0)
    )

    // Sum of per-item discounts
    const itemDiscountTotal = computed(() =>
        items.value.reduce((total, item) => total + (Number(item.discount || 0) * Number(item.quantity || 1)), 0)
    )

    // Total after item-level discounts but before global discount
    const totalAfterItemDiscounts = computed(() =>
        subtotal.value - itemDiscountTotal.value
    )

    // Global discount value in currency
    const discountAmount = computed(() => {
        if (discountType.value === 'percentage') {
            return totalAfterItemDiscounts.value * (discount.value / 100)
        }
        return discount.value
    })

    // Global discount percentage relative to subtotal after item discounts
    const globalDiscountPercentage = computed(() => {
        if (totalAfterItemDiscounts.value === 0) return 0;
        return (discountAmount.value / totalAfterItemDiscounts.value) * 100;
    })

    // Final total to be paid
    const total = computed(() =>
        Math.max(0, totalAfterItemDiscounts.value - discountAmount.value)
    )

    const isEmpty = computed(() => items.value.length === 0)

    // Actions
    function addItem(product) {
        const existingItem = items.value.find(item => item.id === product.id)
        const availableStock = product.stock !== undefined ? product.stock : (product.quantity !== undefined ? product.quantity : 1);

        if (existingItem) {
            if (existingItem.quantity < availableStock) {
                existingItem.quantity++
            }
        } else {
            items.value.push({
                id: product.id,
                name: product.product?.name || product.name,
                price: Number(product.selling_price || product.price || 0),
                discount: 0,
                stock: availableStock,
                quantity: 1,
                image: product.image || null,
                imei: product.imei || null,
                cost_price: Number(product.cost_price || 0),
                product_id: product.product_id || product.product?.id
            })
        }
    }

    function updateItemDiscount(productId, amount) {
        const item = items.value.find(item => item.id === productId)
        if (item) {
            item.discount = Number(amount) || 0;
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

    function setDiscount(amount, type = 'fixed') {
        discount.value = Number(amount) || 0
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
        discountType.value = 'fixed'
        notes.value = ''
        localStorage.removeItem('temp_cart_state');
    }

    // Helper for proportional global discount distribution
    function getDistributedGlobalDiscount(item) {
        const itemPriceAfterItemDiscount = Number(item.price || 0) - Number(item.discount || 0);
        const itemTotalAfterItemDiscount = itemPriceAfterItemDiscount * Number(item.quantity || 1);

        const netTotal = Number(totalAfterItemDiscounts.value || 0);
        if (netTotal <= 0) return 0;

        // Ratio based on its share of total sales value
        const ratio = itemTotalAfterItemDiscount / netTotal;
        const distributed = Number(discountAmount.value || 0) * ratio;

        return isNaN(distributed) ? 0 : distributed;
    }

    function addBundle(bundleItems, totalPrice, description) {
        const bundleId = 'bundle-' + Date.now();
        items.value.push({
            id: bundleId,
            name: description,
            price: totalPrice,
            discount: 0,
            quantity: 1,
            is_bundle: true,
            bundle_items: bundleItems.map(item => ({
                id: item.id,
                product_id: item.product_id || item.id,
                name: item.product?.name || item.name,
                imei: item.imei || null,
                price: item.bundle_price || item.selling_price || item.price,
                cost_price: item.cost_price || 0,
                quantity: item.quantity || 1,
                is_non_hp: !item.imei
            }))
        });
    }

    function updateBundle(bundleId, bundleItems, totalPrice, description) {
        const index = items.value.findIndex(item => item.id === bundleId);
        if (index > -1) {
            items.value[index] = {
                ...items.value[index],
                name: description,
                price: totalPrice,
                bundle_items: bundleItems.map(item => ({
                    id: item.id,
                    product_id: item.product_id || item.id,
                    name: item.product?.name || item.name,
                    imei: item.imei || null,
                    price: item.bundle_price || item.selling_price || item.price,
                    cost_price: item.cost_price || 0,
                    quantity: item.quantity || 1,
                    is_non_hp: !item.imei
                }))
            };
        }
    }

    return {
        items,
        customer,
        discount,
        discountType,
        paymentMethod,
        notes,
        itemCount,
        subtotal,
        itemDiscountTotal,
        totalAfterItemDiscounts,
        discountAmount,
        globalDiscountPercentage,
        total,
        isEmpty,
        addItem,
        addBundle,
        updateBundle,
        updateItemDiscount,
        removeItem,
        updateQuantity,
        incrementQuantity,
        decrementQuantity,
        setCustomer,
        setDiscount,
        setPaymentMethod,
        setNotes,
        clearCart,
        getDistributedGlobalDiscount
    }
})
