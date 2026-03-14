<script setup>
import { ref, computed, onMounted, watch } from "vue";
import api from "../../api/axios";
import { useEscapeKey } from "../../composables/useEscapeKey";
import { useCartStore } from "../../store/cart";
import { useInventoryStore } from "../../store/inventory";
import { useAuthStore } from "../../store/auth";
import { formatCurrency } from "../../utils/formatters";
import {
    Search,
    Plus,
    ShoppingCart,
    Minus,
    Trash2,
    X,
    CreditCard,
    Banknote,
    QrCode,
    Receipt,
    CheckCircle,
    AlertCircle,
    User,
    ArrowLeft,
    ArrowRight,
    ShoppingBag,
    Shield,
    Loader2,
    Hash,
    MessageSquare,
    Save,
    Upload
} from "lucide-vue-next";
import PinModal from "../../components/modals/PinModal.vue";
import ReceiptModal from "../../components/modals/ReceiptModal.vue";

const cartStore = useCartStore();
const inventoryStore = useInventoryStore();

// Wizard Steps
const currentStep = ref(1); // 1: Account, 2: Category, 3: Items, 4: Form/Payment
const salesAccount = ref("");
const salesAccounts = ref([]);
const transactionCategory = ref("penjualan");

const categoriesPenjualan = [
    { id: "penjualan", label: "Penjualan" },
    { id: "angkat_barang", label: "Angkat Barang" },
    { id: "refund", label: "Refund" },
    { id: "tukar_unit", label: "Tukar Unit" },
    { id: "tukar_tambah", label: "Tukar Tambah" },
    { id: "downgrade", label: "Downgrade" },
];

const searchQuery = ref("");
const selectedCategory = ref(null);

// Form Fields (Step 4)
const customerForm = ref({
    customer_name: "",
    customer_phone: "",
    notes: "",
});

const tradeInForm = ref({
    customer_name: "",
    customer_phone: "",
    source: "luar_pstore",
    brand_id: null,
    product_type_id: null,
    storage: "",
    condition: "",
    imei: "",
    imeis_raw: "",
    quantity: 1,
    buy_price: 0,
    payment_method_id: null,
    reason: "",
    notes: "",
});

const tradeInPhotos = ref({
    unit: null,
    unitPreview: null,
    customer: null,
    customerPreview: null
});

const refundPhotos = ref({
    unit: null,
    unitPreview: null,
    customer: null,
    customerPreview: null
});

const refundForm = ref({
    customer_name: "",
    customer_phone: "",
    brand_id: null,
    product_type_id: null,
    ram: "",
    condition: "",
    imei: "",
    refund_price: 0,
    payment_method_id: null,
    reason: "",
    notes: "",
});

const brands = ref([]);
const hpProducts = ref([]);
const productTypes = ref([]);
const productPrices = ref([]);
const filteredTradeInTypes = computed(() => {
    if (!tradeInForm.value.brand_id) return [];
    return productTypes.value.filter(t => t.brand_id === tradeInForm.value.brand_id);
});

const selectedTradeInType = computed(() => {
    if (!tradeInForm.value.product_type_id) return null;
    return productTypes.value.find(t => t.id === tradeInForm.value.product_type_id);
});

const isImeiTradeIn = computed(() => {
    if (!selectedTradeInType.value) return true;
    const cat = selectedTradeInType.value.category?.toLowerCase();
    return cat === 'imei' || cat === 'hp / gadget';
});

const filteredTradeInCapacities = computed(() => {
    if (!tradeInForm.value.product_type_id) return [];
    const set = new Set();
    // From ProductPrice
    productPrices.value
        .filter(p => p.product_type_id === tradeInForm.value.product_type_id)
        .forEach(p => { if (p.storage) set.add(p.storage); });
    // From ProductType
    if (selectedTradeInType.value?.storage) {
        selectedTradeInType.value.storage.split(/[,/]/).forEach(s => {
            const clean = s.trim();
            if (clean) set.add(clean);
        });
    }
    return Array.from(set).sort();
});

const filteredTradeInConditions = computed(() => {
    const defaults = ['new', 'second', 'ex_ibox'];
    const set = new Set(defaults);
    if (tradeInForm.value.product_type_id) {
        productPrices.value
            .filter(p => p.product_type_id === tradeInForm.value.product_type_id && p.storage === tradeInForm.value.storage)
            .forEach(p => { if (p.condition) set.add(p.condition); });
    }
    return Array.from(set);
});

const totalTradeInUnits = computed(() => {
    return tradeInForm.value.quantity || 0;
});

const filteredRefundTypes = computed(() => {
    if (!refundForm.value.brand_id) return [];
    return productTypes.value.filter(t => t.brand_id === refundForm.value.brand_id);
});

const selectedRefundType = computed(() => {
    if (!refundForm.value.product_type_id) return null;
    return productTypes.value.find(t => t.id === refundForm.value.product_type_id);
});

const isImeiRefund = computed(() => {
    if (!selectedRefundType.value) return true;
    const cat = selectedRefundType.value.category?.toLowerCase();
    return cat === 'imei' || cat === 'hp / gadget';
});

const filteredRefundRAMs = computed(() => {
    if (!refundForm.value.product_type_id) return [];
    const set = new Set();
    const type = selectedRefundType.value;
    if (type?.ram) {
        type.ram.split(/[,/]/).forEach(r => {
            const clean = r.trim();
            if (clean) set.add(clean);
        });
    }
    return Array.from(set).sort();
});

// Payment state (Step 4)
const paymentAmount = ref(0);
const selectedPaymentMethod = ref(null);
const availablePaymentMethods = ref([]);
const splitPayments = ref([]);
const proofImage = ref(null);
const proofImagePreview = ref(null);
const isSubmitting = ref(false);

// Reset dependent fields when brand or type changes
watch(() => tradeInForm.value.brand_id, () => {
    tradeInForm.value.product_type_id = null;
    tradeInForm.value.storage = "";
    tradeInForm.value.condition = "";
});

watch(() => tradeInForm.value.product_type_id, () => {
    tradeInForm.value.storage = "";
    tradeInForm.value.condition = "";
});

watch(() => tradeInForm.value.storage, () => {
    tradeInForm.value.condition = "";
});

watch(() => refundForm.value.brand_id, () => {
    refundForm.value.product_type_id = null;
    refundForm.value.ram = "";
    refundForm.value.condition = "";
});

watch(() => refundForm.value.product_type_id, () => {
    refundForm.value.ram = "";
    refundForm.value.condition = "";
});
const isCompressing = ref(false);

// Success modal
const showSuccessModal = ref(false);
const showReceiptModal = ref(false);
const shouldAutoSendWA = ref(false);
const lastTransaction = ref(null);

const authStore = useAuthStore();
const currentUser = ref(null);
const showInitialPinSetup = ref(false);
const showPinModal = ref(false);
const pinModalMode = ref("verify");
const pinModalTitle = ref("Verifikasi PIN");

onMounted(async () => {
    try {
        const [hpRes, nonHpRes, accountsRes, userRes, paymentsRes, brandsRes, productsRes, typesRes, pricesRes] = await Promise.all([
            api.get('/inventory', { params: { type: 'hp', status: 'available', per_page: 1000 } }),
            api.get('/inventory', { params: { type: 'non-hp', per_page: 1000 } }),
            api.get('/inventory/my-accounts'),
            api.get('/user'),
            api.get('/payment-methods'),
            api.get('/brands'),
            api.get('/products', { params: { type: 'hp', per_page: 1000 } }),
            api.get('/product-types', { params: { per_page: 1000 } }),
            api.get('/product-prices', { params: { per_page: 1000 } })
        ]);

        // Process HP items
        const hpData = hpRes.data?.data || hpRes.data || [];

        // Process Non-HP items
        const rawNonHpData = nonHpRes.data?.data || nonHpRes.data || [];
        const nonHpData = rawNonHpData.map(item => ({
            ...item,
            is_non_hp: true,
            selling_price: item.product?.selling_price || item.product?.price || 0,
            condition: 'new',
            ram: null,
            storage: null,
            imei: null,
            distributor: { name: item.latest_distributor || item.latest_supplier || null }
        }));

        // Combine and set in store
        inventoryStore.products = [...hpData, ...nonHpData];

        const rawAccounts = accountsRes.data.data || accountsRes.data;
        // Filter ONLY for sales role as requested by user (hide inventory accounts like bian trial)
        salesAccounts.value = rawAccounts.filter(acc =>
            acc.roles && acc.roles.some(r => r.name === 'sales')
        );

        const currentUserData = userRes.data.data || userRes.data;
        currentUser.value = currentUserData;

        // Payment Methods
        const payments = (paymentsRes.data.data || paymentsRes.data).filter(p => p.is_active);
        availablePaymentMethods.value = payments;
        if (payments.length > 0) {
            // Default to cash or first one
            const cashMethod = payments.find(p => p.category?.toLowerCase() === 'cash' || p.name?.toLowerCase() === 'tunai');
            selectedPaymentMethod.value = cashMethod ? cashMethod.id : payments[0].id;
            tradeInForm.value.payment_method_id = selectedPaymentMethod.value;
        }

        // Brands and HP Products
        brands.value = brandsRes.data.data || brandsRes.data || [];
        hpProducts.value = productsRes.data.data || productsRes.data || [];
        productTypes.value = typesRes.data.data || typesRes.data || [];
        productPrices.value = pricesRes.data.data || pricesRes.data || [];

        // Auto-select logged-in user if they are in the list
        if (currentUserData) {
            // Check if PIN setup is needed (only for sales role)
            const isSales = currentUserData.roles && currentUserData.roles.some(r => r.name === 'sales');
            if (isSales && !currentUserData.transaction_pin) {
                showInitialPinSetup.value = true;
            }

            const match = salesAccounts.value.find(acc =>
                acc.name === currentUserData.name ||
                acc.username === currentUserData.username ||
                acc.id === currentUserData.id
            );
            if (match) {
                salesAccount.value = match.name;
            }
        }
    } catch (e) {
        console.error("Gagal memuat data awal", e);
    }
});

const isBundling = ref(false);
const showBundlingModal = ref(false);
const bundleItems = ref([]);
const bundleTotalPrice = ref(0);
const displayBundleTotalPrice = ref("0");

function openBundlingModal() {
    bundleItems.value = [];
    bundleTotalPrice.value = 0;
    displayBundleTotalPrice.value = "0";
    showBundlingModal.value = true;
}

function closeBundlingModal() {
    bundleItems.value = [];
    bundleTotalPrice.value = 0;
    displayBundleTotalPrice.value = "0";
    showBundlingModal.value = false;
}

function addToBundle(product) {
    if (isItemFullyOccupied(product)) {
        const status = getCartStatus(product.id);
        alert(`Produk ini sudah tidak tersedia (Sudah ada ${status?.toLowerCase() || 'di keranjang/memenuhi stok'}).`);
        return;
    }

    const availableStock = product.stock !== undefined ? product.stock : (product.quantity !== undefined ? product.quantity : 0);

    // If it's a non-IMEI item and already in the bundle, just increment quantity
    if (!product.imei) {
        const existingItem = bundleItems.value.find(item => item.id === product.id);
        if (existingItem) {
            if (!isItemFullyOccupied(existingItem)) {
                existingItem.quantity = (existingItem.quantity || 0) + 1;
                updateBundleTotal();
            } else {
                alert("Stok tidak mencukupi.");
            }
            return;
        }
    } else {
        if (bundleItems.value.some(item => item.id === product.id)) {
            alert("Produk sudah ada di dalam bundle.");
            return;
        }
    }

    // Deep copy to avoid reference issues
    const itemToAdd = JSON.parse(JSON.stringify(product));
    itemToAdd.stock = availableStock; // Preserve original stock
    itemToAdd.bundle_price = itemToAdd.selling_price || itemToAdd.price || 0;
    itemToAdd.display_bundle_price = formatNumber(itemToAdd.bundle_price);
    itemToAdd.quantity = 1;

    bundleItems.value.push(itemToAdd);
    updateBundleTotal();
}

function incrementBundleItemQty(index) {
    const item = bundleItems.value[index];
    if (!isItemFullyOccupied(item)) {
        item.quantity++;
        updateBundleTotal();
    } else {
        alert("Stok tidak mencukupi untuk menambah jumlah.");
    }
}

function decrementBundleItemQty(index) {
    const item = bundleItems.value[index];
    if (item.quantity > 1) {
        item.quantity--;
        updateBundleTotal();
    } else {
        removeFromBundle(index);
    }
}

function updateBundleTotal() {
    // Force conversion to Number to avoid string concatenation, multiply by quantity
    const currentTotal = bundleItems.value.reduce((sum, item) => {
        const itemPrice = Number(item.bundle_price || 0);
        const itemQty = Number(item.quantity || 1);
        return sum + (itemPrice * itemQty);
    }, 0);
    bundleTotalPrice.value = currentTotal;
    displayBundleTotalPrice.value = formatNumber(currentTotal);
}

function handleBundleItemPriceInput(idx, e) {
    const val = e.target.value;
    const num = parseNumber(val);
    bundleItems.value[idx].bundle_price = num;
    bundleItems.value[idx].display_bundle_price = formatNumber(num);

    // Immediately update the total
    updateBundleTotal();

    // Sync the input value to the formatted string to avoid cursor jumps/artifacts
    e.target.value = formatNumber(num);
}

function removeFromBundle(index) {
    bundleItems.value.splice(index, 1);
    updateBundleTotal();
}

function handleBundlePriceInput(e) {
    const val = e.target.value;
    const num = parseNumber(val);
    bundleTotalPrice.value = num;
    displayBundleTotalPrice.value = formatNumber(num);
    e.target.value = formatNumber(num);
}

function finishBundling() {
    if (bundleItems.value.length < 2) {
        alert("Pilih minimal 2 produk untuk bundling.");
        return;
    }
    if (bundleTotalPrice.value <= 0) {
        alert("Masukkan harga bundling.");
        return;
    }

    const description = bundleItems.value.map(item => {
        const name = item.product?.name || item.name;
        return item.quantity > 1 ? `${name} (x${item.quantity})` : name;
    }).join(" + ");
    // Use the custom item prices within the bundle
    const itemsWithPrices = bundleItems.value.map(item => ({
        ...item,
        selling_price: item.bundle_price
    }));
    cartStore.addBundle(itemsWithPrices, bundleTotalPrice.value, description);
    closeBundlingModal();
}

function toggleBundling() {
    isBundling.value = !isBundling.value;
    if (transactionCategory.value === 'penjualan' || transactionCategory.value === 'bundling') {
        transactionCategory.value = isBundling.value ? 'bundling' : 'penjualan';
    }
}

// Step Navigation
function nextStep() {
    if (currentStep.value === 1 && !salesAccount.value) {
        alert("Silakan pilih Akun Sales terlebih dahulu.");
        return;
    }
    if (currentStep.value === 3 && cartItems.value.length === 0) {
        alert("Pilih minimal 1 produk terlebih dahulu.");
        return;
    }

    if (currentStep.value < 4) {
        currentStep.value++;
        if (currentStep.value === 4) {
            paymentAmount.value = cartTotal.value;
            displayPaymentAmount.value = formatNumber(cartTotal.value);
            // Initialize split payments with a single entry covering the total
            splitPayments.value = [{
                method_id: selectedPaymentMethod.value,
                amount: cartTotal.value,
                display_amount: formatNumber(cartTotal.value)
            }];
        }
    }
}

function prevStep() {
    if (currentStep.value > 1) {
        currentStep.value--;
    }
}

const filteredProducts = computed(() => {
    let result = inventoryStore.products;
    if (searchQuery.ref || searchQuery.value) {
        const query = (searchQuery.value || "").toLowerCase();
        result = result.filter(
            (p) =>
                (p.product?.name || p.name || "").toLowerCase().includes(query) ||
                (p.product?.brand || "").toLowerCase().includes(query) ||
                (p.imei || "").toLowerCase().includes(query)
        );
    }
    if (selectedCategory.value) {
        result = result.filter((p) => p.category === selectedCategory.value);
    }
    return result;
});

const categories = computed(() => inventoryStore.categories);
const cartItems = computed(() => cartStore.items);
const cartTotal = computed(() => cartStore.total);
const cartSubtotal = computed(() => cartStore.subtotal);
const cartItemCount = computed(() => cartStore.itemCount);

const paymentMethods = []; // Deprecated, using availablePaymentMethods from DB

const selectedPaymentMethodObj = computed(() =>
    availablePaymentMethods.value.find(m => m.id === selectedPaymentMethod.value)
);

const isCashPayment = computed(() => {
    const cat = selectedPaymentMethodObj.value?.category?.toLowerCase();
    const name = selectedPaymentMethodObj.value?.name?.toLowerCase();
    return cat === 'cash' || cat === 'tunai' || name?.includes('cash') || name?.includes('tunai');
});

const changeAmount = computed(() => {
    const totalPaid = splitPayments.value.reduce((sum, p) => sum + p.amount, 0);
    return totalPaid - cartTotal.value;
});

const missingFields = computed(() => {
    const fields = [];
    const salesCategoriesList = ['penjualan', 'bundling', 'tukar_unit', 'tukar_tambah', 'downgrade', 'penjualan_offline', 'shopee', 'orderan_online'];
    const isSale = salesCategoriesList.includes(transactionCategory.value);

    if (isSale) {
        if (!customerForm.value.customer_name) fields.push("Nama Pelanggan");
        if (!customerForm.value.customer_phone) fields.push("WhatsApp");
        if (!customerForm.value.notes) fields.push("Catatan/Keterangan");
        if (!proofImage.value) fields.push("Foto Bukti");
    }

    if (changeAmount.value < 0) fields.push("Pembayaran (Masih Kurang)");

    return fields;
});

const isFormValid = computed(() => missingFields.value.length === 0);

const submitButtonText = computed(() => {
    if (isSubmitting.value) return 'MEMPROSES...';
    return isFormValid.value ? 'SELESAIKAN TRANSAKSI' : 'ISI SEMUA DATA';
});

function isItemInCart(itemId) {
    return cartItems.value.some(item => {
        if (item.id === itemId) return true;
        if (item.is_bundle && item.bundle_items && item.bundle_items.some(bi => bi.id === itemId)) return true;
        return false;
    });
}

function getCartStatus(itemId) {
    const item = cartItems.value.find(i => i.id === itemId && !i.is_bundle);
    if (item) return "Di Keranjang";
    const bundle = cartItems.value.find(i => i.is_bundle && i.bundle_items && i.bundle_items.some(bi => bi.id === itemId));
    if (bundle) return "Dalam Bundle";
    return null;
}

function getTotalSpentQuantity(itemId) {
    let total = 0;
    cartItems.value.forEach(item => {
        if (item.id === itemId && !item.is_bundle) {
            total += (item.quantity || 1);
        }
        if (item.is_bundle && item.bundle_items) {
            const bi = item.bundle_items.find(b => b.id === itemId);
            if (bi) {
                total += (bi.quantity || 1);
            }
        }
    });

    // Also count items in the bundle currently being built
    const inCurrentBundle = bundleItems.value.find(bi => bi.id === itemId);
    if (inCurrentBundle) {
        total += (inCurrentBundle.quantity || 1);
    }

    return total;
}

function getRemainingStock(product) {
    if (product.imei) return 1;
    const spent = getTotalSpentQuantity(product.id);
    const stockLimit = product.stock !== undefined ? product.stock : (product.quantity !== undefined ? product.quantity : 0);
    return Math.max(0, stockLimit - spent);
}

function isItemFullyOccupied(product) {
    if (product.imei) {
        return getCartStatus(product.id) !== null;
    }
    return getRemainingStock(product) <= 0;
}

function addToCart(product) {
    if (isItemFullyOccupied(product)) {
        const status = getCartStatus(product.id);
        alert(`Produk ini sudah tidak tersedia (Sudah ada ${status?.toLowerCase() || 'di keranjang'}).`);
        return;
    }

    if (!product.imei) {
        // If already in cart (individual), increment quantity
        const existingInCart = cartItems.value.find(i => i.id === product.id && !i.is_bundle);
        if (existingInCart) {
            cartStore.incrementQuantity(product.id);
            return;
        }
    }

    cartStore.addItem(product);
}

function removeFromCart(productId) {
    cartStore.removeItem(productId);
}

function incrementQty(productId) {
    const item = cartItems.value.find(i => i.id === productId);
    if (item && isItemFullyOccupied(item)) {
        alert("Stok tidak mencukupi.");
        return;
    }
    cartStore.incrementQuantity(productId);
}

function decrementQty(productId) {
    cartStore.decrementQuantity(productId);
}

function addSplitPayment() {
    const remainingAmount = Math.max(0, cartTotal.value - splitPayments.value.reduce((sum, p) => sum + p.amount, 0));
    splitPayments.value.push({
        method_id: availablePaymentMethods.value[0]?.id || null,
        amount: remainingAmount,
        display_amount: formatNumber(remainingAmount)
    });
}

function removeSplitPayment(index) {
    if (splitPayments.value.length > 1) {
        splitPayments.value.splice(index, 1);
    }
}

function handleSplitAmountInput(index, e) {
    const val = e.target.value;
    const num = parseNumber(val);
    splitPayments.value[index].amount = num;
    splitPayments.value[index].display_amount = formatNumber(num);
}
async function compressImage(file, maxWidth = 1200, maxHeight = 1200, quality = 0.7) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.readAsDataURL(file);
        reader.onload = (event) => {
            const img = new Image();
            img.src = event.target.result;
            img.onload = () => {
                const canvas = document.createElement('canvas');
                let width = img.width;
                let height = img.height;
                if (width > height) {
                    if (width > maxWidth) {
                        height = Math.round((height * maxWidth) / width);
                        width = maxWidth;
                    }
                } else {
                    if (height > maxHeight) {
                        width = Math.round((width * maxHeight) / height);
                        height = maxHeight;
                    }
                }
                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);
                canvas.toBlob((blob) => {
                    if (blob) {
                        resolve(new File([blob], file.name, { type: 'image/jpeg' }));
                    } else reject(new Error('Blob null'));
                }, 'image/jpeg', quality);
            };
            img.onerror = reject;
        };
        reader.onerror = reject;
    });
}

async function handleFileChange(e) {
    const file = e.target.files[0];
    if (!file) return;

    try {
        isCompressing.value = true;
        // Compress if it's an image to avoid 413 Payload Too Large
        if (file.type.startsWith('image/')) {
            const compressed = await compressImage(file);
            proofImage.value = compressed;
            proofImagePreview.value = URL.createObjectURL(compressed);
        } else {
            if (file.size > 10 * 1024 * 1024) {
                alert("Ukuran file maksimal 10MB");
                e.target.value = "";
                return;
            }
            proofImage.value = file;
            proofImagePreview.value = URL.createObjectURL(file);
        }
    } catch (err) {
        console.error("Compression failed, using original", err);
        proofImage.value = file;
        proofImagePreview.value = URL.createObjectURL(file);
    } finally {
        isCompressing.value = false;
    }
}

async function handleSubmitOrder() {
    if (!isFormValid.value) {
        alert("Mohon lengkapi data: " + missingFields.value.join(", "));
        return;
    }
    // Only Sales role with PIN enabled requires PIN
    if (authStore.userRole === 'sales' && authStore.user?.pin_enabled) {
        showPinModal.value = true;
        pinModalMode.value = "verify";
        pinModalTitle.value = "Verifikasi PIN Transaksi";
    } else {
        await processPayment();
    }
}

async function handlePinSuccess(pin) {
    showPinModal.value = false;
    await processPayment(pin);
}

async function processPayment(pin = null) {
    if (isSubmitting.value) return;
    try {
        isSubmitting.value = true;
        const formData = new FormData();
        formData.append('category', transactionCategory.value);
        formData.append('sales_account', salesAccount.value);
        if (selectedPaymentMethod.value) {
            formData.append('payment_method_id', selectedPaymentMethod.value);
        }
        const totalPaid = splitPayments.value.reduce((sum, p) => sum + p.amount, 0);
        formData.append('paid_amount', totalPaid);
        formData.append('selling_price', Number(cartStore.total || 0));

        // Form details
        formData.append('customer_name', customerForm.value.customer_name);
        formData.append('customer_wa', customerForm.value.customer_phone); // Map phone field to customer_wa for WA
        if (pin) {
            formData.append('transaction_pin', pin);
        }

        let finalNotes = customerForm.value.notes;
        if (cartStore.discount > 0) {
            const discText = cartStore.discountType === 'percentage' ? `${cartStore.discount}%` : formatCurrency(cartStore.discount);
            finalNotes = (finalNotes ? finalNotes + "\n" : "") + `[Diskon ${discText}: -${formatCurrency(cartStore.discountAmount)}]`;
        }
        formData.append('notes', finalNotes);

        let nonHpIndex = 0;
        cartItems.value.forEach(item => {
            const distributedDiscount = cartStore.getDistributedGlobalDiscount(item);

            if (item.is_bundle && item.bundle_items) {
                // UNPACK BUNDLE ITEMS
                item.bundle_items.forEach(bi => {
                    if (bi.imei) {
                        formData.append('product_detail_ids[]', bi.id);
                        // For bundle, we might need a way to distribute item discount? 
                        // But usually item discount is on the bundle itself if specified.
                        // For now we treat bundle as 1 item for discounts.
                    } else {
                        formData.append(`non_hp_items[${nonHpIndex}][product_id]`, bi.product_id || bi.id);
                        formData.append(`non_hp_items[${nonHpIndex}][quantity]`, bi.quantity || 1);
                        formData.append(`non_hp_items[${nonHpIndex}][selling_price]`, Number(bi.price || 0));
                        nonHpIndex++;
                    }
                });
            } else if (item.imei) {
                formData.append('product_detail_ids[]', item.id);
                // We need to send selling_price, item_discount, distributed_discount for each HP item
                formData.append(`hp_items_meta[${item.id}][selling_price]`, Number(item.price || 0));
                formData.append(`hp_items_meta[${item.id}][item_discount]`, Number(item.discount || 0));
                formData.append(`hp_items_meta[${item.id}][distributed_discount]`, Number(distributedDiscount || 0));
            } else {
                formData.append(`non_hp_items[${nonHpIndex}][product_id]`, item.product_id || item.id);
                formData.append(`non_hp_items[${nonHpIndex}][quantity]`, item.quantity);
                formData.append(`non_hp_items[${nonHpIndex}][selling_price]`, Number(item.price || 0));
                formData.append(`non_hp_items[${nonHpIndex}][item_discount]`, Number(item.discount || 0));
                formData.append(`non_hp_items[${nonHpIndex}][distributed_discount]`, Number(distributedDiscount || 0));
                nonHpIndex++;
            }
        });

        // Global discount info
        formData.append('global_discount_value', Number(cartStore.discount || 0));
        formData.append('global_discount_type', cartStore.discountType);
        formData.append('total_discount', Number(cartStore.discountAmount + cartStore.itemDiscountTotal));

        // Split Payments
        formData.append('split_payments', JSON.stringify(splitPayments.value.map(p => ({
            payment_method_id: p.method_id,
            amount: p.amount
        }))));

        // Bundling meta
        const firstBundle = cartItems.value.find(item => item.is_bundle);
        if (firstBundle) {
            formData.append('is_bundle', '1');
            formData.append('bundle_description', firstBundle.name);
        }

        // Proof Image
        if (proofImage.value) {
            formData.append('proof_image', proofImage.value);
        }

        const response = await api.post('/stock-outs', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });

        // Calculate cash/transfer breakdown for Receipt
        let cashAmount = 0;
        let transferAmount = 0;
        splitPayments.value.forEach(p => {
            const method = availablePaymentMethods.value.find(m => m.id === p.method_id);
            if (method) {
                const name = method.name.toLowerCase();
                // Common names for cash in this system: "Cash", "Tunai", "Cash On Delivery"
                if (name.includes('cash') || name.includes('tunai')) {
                    cashAmount += Number(p.amount || 0);
                } else {
                    transferAmount += Number(p.amount || 0);
                }
            }
        });

        // Create split payments data for the modal
        const detailedSplitPayments = splitPayments.value.map(p => {
            const method = availablePaymentMethods.value.find(m => m.id === p.method_id);
            return {
                method_name: method ? method.name : 'Unknown',
                amount: p.amount
            };
        });

        // Get primary payment method name
        const primaryMethod = availablePaymentMethods.value.find(m => m.id === selectedPaymentMethod.value);

        lastTransaction.value = {
            id: response.data?.data?.id,
            order_no: response.data?.data?.receipt_id || "TRX-" + Date.now(),
            items: cartItems.value.map(item => ({
                ...item,
                price: item.price,
                item_discount: item.discount || 0,
                distributed_discount: cartStore.getDistributedGlobalDiscount(item)
            })),
            total_discount: cartStore.discountAmount,
            original_price: cartStore.totalAfterItemDiscounts,
            global_discount_value: cartStore.discountAmount,
            global_discount_type: 'fixed',
            cash: cashAmount,
            transfer: transferAmount,
            total: cartTotal.value,
            paid: paymentAmount.value,
            change: changeAmount.value,
            method: selectedPaymentMethod.value,
            payment_method_name: primaryMethod ? primaryMethod.name : 'Unknown',
            split_payments_data: detailedSplitPayments,
            category: transactionCategory.value,
            sales_account: salesAccount.value,
            customer_name: customerForm.value.customer_name,
            customer_phone: customerForm.value.customer_phone,
            time: new Date().toLocaleString("id-ID"),
        };

        showSuccessModal.value = true;
        shouldAutoSendWA.value = false; // Reset for new txn
        cartStore.clearCart();
        paymentAmount.value = 0;
        splitPayments.value = [];
        proofImage.value = null;
        proofImagePreview.value = null;
        customerForm.value = {
            customer_name: "",
            customer_phone: "",
            notes: "",
        };
        currentStep.value = 1;
        salesAccount.value = "";
    } catch (error) {
        console.error("Payment failed", error);
        let errorMsg = error.response?.data?.message || "Gagal memproses transaksi";
        if (error.response) {
            const status = error.response.status;
            errorMsg = `[Error ${status}] ${errorMsg}`;
            if (status === 413) errorMsg = "[Error 413] Foto terlalu besar untuk dikirim. Hubungi IT.";
        }
        alert(errorMsg);
    } finally {
        isSubmitting.value = false;
    }
}

function closeSuccessModal() {
    showSuccessModal.value = false;
    lastTransaction.value = null;
}

useEscapeKey(() => {
    if (showSuccessModal.value) closeSuccessModal();
});

// Auto Rupiah logic
const displayPaymentAmount = ref("0");
const displayDiscount = ref("0");

// Helper to format raw number to Rupiah string
function formatNumber(n) {
    if (!n) return "0";
    return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

// Helper to get number from Rupiah string
function parseNumber(s) {
    if (!s) return 0;
    if (typeof s === 'number') return s;

    let clean = s.toString().replace(/Rp/g, "").replace(/\s/g, "");

    // Remove .00 or ,00 suffix to avoid multiplying by 100 when stripping non-digits
    if (clean.endsWith('.00') || clean.endsWith(',00')) {
        clean = clean.slice(0, -3);
    }

    const finalClean = clean.replace(/[^0-9]/g, "");
    return parseInt(finalClean) || 0;
}

// Sync displays when underlying values change (e.g. from cartTotal)
watch(() => paymentAmount.value, (newVal) => {
    displayPaymentAmount.value = formatNumber(newVal);
});

watch(() => cartStore.discount, (newVal) => {
    if (cartStore.discountType === 'fixed') {
        displayDiscount.value = formatNumber(newVal);
    } else {
        displayDiscount.value = newVal?.toString() || "0";
    }
});

function handlePaymentInput(e) {
    const val = e.target.value;
    const num = parseNumber(val);
    paymentAmount.value = num;
    displayPaymentAmount.value = formatNumber(num);
}

function handleDiscountInput(e) {
    const val = e.target.value;
    const num = parseNumber(val);
    cartStore.setDiscount(num, 'fixed');
    displayDiscount.value = formatNumber(num);
}

function handleItemDiscountInput(item, e) {
    const val = e.target.value;
    const num = parseNumber(val);
    cartStore.updateItemDiscount(item.id, num);
    e.target.value = formatNumber(num);
}

function handleItemPriceInput(item, e) {
    const val = e.target.value;
    const num = parseNumber(val);
    item.price = num;

    // Force reactivity update on the input element's value to maintain format
    // even if the raw number didn't change (e.g., typing letters)
    e.target.value = formatNumber(num);
}

// Sync paymentAmount to cartTotal when Step 4 is entered
watch(() => currentStep.value, (newStep) => {
    if (newStep === 4) {
        paymentAmount.value = cartStore.total;
        displayPaymentAmount.value = formatNumber(cartStore.total);
    }
});

const displayBuyPrice = ref("0");
function handleBuyPriceInput(e) {
    const val = e.target.value;
    const num = parseNumber(val);
    tradeInForm.value.buy_price = num;
    displayBuyPrice.value = formatNumber(num);
    e.target.value = formatNumber(num);
}

function handleImeiInput(e) {
    const val = e.target.value;
    // Allow digits, commas, and newlines
    const filtered = val.replace(/[^0-9,\n]/g, "");
    tradeInForm.value.imeis_raw = filtered;
    e.target.value = filtered;
}

const handlePhotoUpload = (type, e) => {
    const file = e.target.files[0];
    if (!file) return;

    tradeInPhotos.value[type] = file;
    tradeInPhotos.value[type + 'Preview'] = URL.createObjectURL(file);
}

const handleRefundPhotoUpload = (type, e) => {
    const file = e.target.files[0];
    if (!file) return;

    refundPhotos.value[type] = file;
    refundPhotos.value[type + 'Preview'] = URL.createObjectURL(file);
}

const displayRefundPrice = ref("0");
function handleRefundPriceInput(e) {
    const val = e.target.value;
    const num = parseNumber(val);
    refundForm.value.refund_price = num;
    displayRefundPrice.value = formatNumber(num);
    e.target.value = formatNumber(num);
}

async function submitTradeIn() {
    if (!tradeInForm.value.customer_name || !tradeInForm.value.customer_phone || !tradeInForm.value.brand_id || !tradeInForm.value.product_type_id || !tradeInForm.value.storage || !tradeInForm.value.condition || !tradeInForm.value.buy_price) {
        alert("Mohon lengkapi semua data wajib (Nama, WA, Brand, Tipe, Kapasitas, Kondisi, Harga).");
        return;
    }

    if (!tradeInPhotos.value.unit) {
        alert("Foto unit wajib diupload.");
        return;
    }

    isSubmitting.value = true;
    const formData = new FormData();
    if (tradeInPhotos.value.unit) formData.append('photo_unit', tradeInPhotos.value.unit);
    if (tradeInPhotos.value.customer) formData.append('photo_customer', tradeInPhotos.value.customer);

    formData.append('customer_name', tradeInForm.value.customer_name);
    formData.append('customer_phone', tradeInForm.value.customer_phone);
    formData.append('brand_id', tradeInForm.value.brand_id);
    formData.append('product_type_id', tradeInForm.value.product_type_id);
    formData.append('source', tradeInForm.value.source);
    formData.append('storage', tradeInForm.value.storage);
    formData.append('condition', tradeInForm.value.condition);
    formData.append('buy_price', tradeInForm.value.buy_price);
    formData.append('payment_method_id', tradeInForm.value.payment_method_id);
    formData.append('reason', tradeInForm.value.reason);
    formData.append('notes', tradeInForm.value.notes);

    if (isImeiTradeIn.value) {
        const list = tradeInForm.value.imeis_raw.split(/[\n,]/).map(i => i.trim()).filter(i => i !== "");
        if (list.length === 0) { alert("Masukkan Minimal 1 IMEI"); isSubmitting.value = false; return; }
        if (list.some(i => !/^\d+$/.test(i))) { alert("IMEI Harus Berupa Angka (Numeric 0-9)"); isSubmitting.value = false; return; }
        list.forEach(i => formData.append('imeis[]', i));
    } else {
        formData.append('quantity', tradeInForm.value.quantity);
    }

    try {
        const response = await api.post('/trade-ins', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });

        // Set last transaction for receipt modal
        const data = response.data.data;
        const batchCount = response.data.count || 1;
        lastTransaction.value = {
            id: data.id,
            order_no: data.receipt_id,
            items: [{
                product: data.product_type,
                name: data.product_type?.name,
                imei: isImeiTradeIn.value ? (batchCount > 1 ? `${batchCount} Unit (Batch)` : data.imei) : '-',
                selling_price: data.buy_price,
                condition: data.condition,
                storage: data.storage,
                ram: data.ram,
                price: data.buy_price,
                qty: batchCount
            }],
            original_price: data.buy_price * batchCount,
            grand_total: data.buy_price * batchCount,
            total: data.buy_price * batchCount,
            paid: data.buy_price * batchCount,
            cash: data.payment_method?.category?.toLowerCase() === 'cash' ? (data.buy_price * batchCount) : 0,
            transfer: data.payment_method?.category?.toLowerCase() === 'transfer' ? (data.buy_price * batchCount) : 0,
            payment_method_name: data.payment_method?.name,
            category: 'angkat_barang',
            customer_name: data.customer_name,
            customer_phone: data.customer_phone,
            time: new Date().toLocaleString("id-ID"),
        };

        showSuccessModal.value = true;
        // Reset form
        tradeInForm.value = {
            customer_name: "",
            customer_phone: "",
            source: "luar_pstore",
            brand_id: null,
            product_type_id: null,
            storage: "",
            condition: "",
            imei: "",
            imeis_raw: "",
            quantity: 1,
            buy_price: 0,
            payment_method_id: availablePaymentMethods.value[0]?.id || null,
            reason: "",
            notes: "",
        };
        tradeInPhotos.value = { unit: null, unitPreview: null, customer: null, customerPreview: null };
        displayBuyPrice.value = "0";
        currentStep.value = 1;
        salesAccount.value = "";

    } catch (error) {
        console.error("Trade-in failed", error);
        alert(error.response?.data?.message || "Gagal memproses barang angkat");
    } finally {
        isSubmitting.value = false;
    }
}
async function submitRefund() {
    if (!refundForm.value.customer_name || !refundForm.value.customer_phone || !refundForm.value.brand_id || !refundForm.value.product_type_id || !refundForm.value.condition || !refundForm.value.refund_price || !refundForm.value.reason) {
        alert("Mohon lengkapi semua data wajib (Nama, WA, Brand, Tipe, Kondisi, Harga Refund, Alasan).");
        return;
    }

    if (!refundPhotos.value.unit) {
        alert("Foto unit wajib diupload.");
        return;
    }

    isSubmitting.value = true;
    const formData = new FormData();
    if (refundPhotos.value.unit) formData.append('photo_unit', refundPhotos.value.unit);
    if (refundPhotos.value.customer) formData.append('photo_customer', refundPhotos.value.customer);

    formData.append('customer_name', refundForm.value.customer_name);
    formData.append('customer_phone', refundForm.value.customer_phone);
    formData.append('brand_id', refundForm.value.brand_id);
    formData.append('product_type_id', refundForm.value.product_type_id);
    formData.append('ram', refundForm.value.ram);
    formData.append('condition', refundForm.value.condition);
    formData.append('imei', refundForm.value.imei);
    formData.append('refund_price', refundForm.value.refund_price);
    formData.append('payment_method_id', refundForm.value.payment_method_id);
    formData.append('reason', refundForm.value.reason);
    formData.append('notes', refundForm.value.notes);

    try {
        const response = await api.post('/refunds', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });

        const data = response.data.data;
        lastTransaction.value = {
            id: data.id,
            order_no: data.receipt_id,
            items: [{
                product: data.product_type,
                name: data.product_type?.name,
                imei: data.imei || '-',
                selling_price: data.refund_price,
                condition: data.condition,
                ram: data.ram,
                price: data.refund_price,
                qty: 1
            }],
            original_price: data.refund_price,
            grand_total: data.refund_price,
            total: data.refund_price,
            paid: data.refund_price,
            cash: data.payment_method?.category?.toLowerCase() === 'cash' ? data.refund_price : 0,
            transfer: data.payment_method?.category?.toLowerCase() === 'transfer' ? data.refund_price : 0,
            payment_method_name: data.payment_method?.name,
            category: 'refund',
            customer_name: data.customer_name,
            customer_phone: data.customer_phone,
            time: new Date().toLocaleString("id-ID"),
        };

        showSuccessModal.value = true;
        // Reset form
        refundForm.value = {
            customer_name: "",
            customer_phone: "",
            brand_id: null,
            product_type_id: null,
            ram: "",
            condition: "",
            imei: "",
            refund_price: 0,
            payment_method_id: availablePaymentMethods.value[0]?.id || null,
            reason: "",
            notes: "",
        };
        refundPhotos.value = { unit: null, unitPreview: null, customer: null, customerPreview: null };
        displayRefundPrice.value = "0";
        currentStep.value = 1;
        salesAccount.value = "";

    } catch (error) {
        console.error("Refund failed", error);
        alert(error.response?.data?.message || "Gagal memproses refund");
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <div class="max-w-[1600px] mx-auto px-2 sm:px-4 py-4 sm:py-8 min-h-[calc(100vh-8rem)]">
        <!-- Progress Bar -->
        <div class="mb-12 max-w-5xl mx-auto">
            <div class="flex items-center justify-between relative">
                <div
                    class="absolute left-0 right-0 top-1/2 -translate-y-1/2 h-1 bg-surface-200 dark:bg-surface-700 -z-10 mx-8 rounded-full">
                    <div class="h-full bg-primary-500 transition-all duration-500 ease-out rounded-full"
                        :style="{ width: `${((currentStep - 1) / 3) * 100}%` }"></div>
                </div>

                <div v-for="step in 4" :key="step" class="flex flex-col items-center gap-2 sm:gap-3">
                    <div class="w-10 h-10 sm:w-14 sm:h-14 rounded-full flex items-center justify-center font-bold text-sm sm:text-lg transition-all duration-300 shadow-sm"
                        :class="currentStep >= step ? 'bg-primary-600 text-white shadow-primary-500/30 scale-110' : 'bg-white dark:bg-surface-800 text-text-secondary border-2 border-surface-200 dark:border-surface-700'">
                        <CheckCircle v-if="currentStep > step" :size="20" class="sm:hidden" />
                        <CheckCircle v-if="currentStep > step" :size="24" class="hidden sm:block" />
                        <span v-else>{{ step }}</span>
                    </div>
                    <span
                        class="hidden sm:block text-[10px] sm:text-xs font-bold uppercase tracking-widest transition-colors"
                        :class="currentStep >= step ? 'text-primary-600' : 'text-text-secondary'">
                        {{ ['Akun Sales', 'Kategori', 'Pilih Barang', 'Pembayaran'][step - 1] }}
                    </span>
                </div>
            </div>
        </div>

        <!-- content area -->
        <div class="h-full flex flex-col transition-all duration-300">
            <!-- STEP 1: ACCOUNT SELECTION -->
            <div v-if="currentStep === 1"
                class="flex-1 flex flex-col justify-center max-w-3xl mx-auto w-full animate-fade-in">
                <div
                    class="bg-white dark:bg-surface-800 rounded-[2rem] border border-surface-200 dark:border-surface-700 p-10 shadow-xl text-center">
                    <div
                        class="w-24 h-24 bg-primary-500/10 text-primary-500 rounded-[2rem] flex items-center justify-center mx-auto mb-8">
                        <User :size="48" stroke-width="1.5" />
                    </div>
                    <h2 class="text-4xl font-black text-text-primary mb-3">Akun Sales</h2>
                    <p class="text-text-secondary text-lg mb-10">Pilih nama akun utama yang bertanggung jawab pada
                        transaksi ini</p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 mb-8">
                        <button v-for="account in salesAccounts" :key="account.id"
                            @click="salesAccount = (account.full_name || account.name); nextStep()"
                            class="p-4 sm:p-6 rounded-2xl border-2 transition-all flex items-center gap-3 sm:gap-4 text-left group"
                            :class="salesAccount === (account.full_name || account.name)
                                ? 'border-primary-500 bg-primary-500/5 shadow-lg shadow-primary-500/10'
                                : 'border-surface-200 dark:border-surface-700 hover:border-primary-500/50 hover:bg-surface-50 dark:hover:bg-surface-900/50'">
                            <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-full flex items-center justify-center transition-colors"
                                :class="salesAccount === (account.full_name || account.name) ? 'bg-primary-500 text-white' : 'bg-surface-100 dark:bg-surface-800 text-text-secondary group-hover:bg-primary-100 group-hover:text-primary-600'">
                                <User :size="18" class="sm:hidden" />
                                <User :size="20" class="hidden sm:block" />
                            </div>
                            <span
                                class="font-bold text-base sm:text-lg text-text-primary group-hover:text-primary-600 transition-colors line-clamp-1">{{
                                    account.full_name || account.name }}</span>
                        </button>
                    </div>

                    <div
                        class="flex justify-center sm:justify-end mt-8 border-t border-surface-100 dark:border-surface-700 pt-8">
                        <button @click="nextStep" :disabled="!salesAccount"
                            class="w-full sm:w-auto py-4 px-10 bg-primary-600 hover:bg-primary-500 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-2xl font-bold text-lg shadow-xl shadow-primary-500/20 transition-all flex items-center justify-center sm:justify-start gap-3">
                            Lanjut
                            <ArrowRight :size="24" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- STEP 2: CATEGORY SELECTION -->
            <div v-if="currentStep === 2"
                class="flex-1 flex flex-col justify-center max-w-4xl mx-auto w-full animate-fade-in">
                <div
                    class="bg-white dark:bg-surface-800 rounded-[2rem] border border-surface-200 dark:border-surface-700 p-10 shadow-xl">
                    <div class="text-center mb-10">
                        <h2 class="text-4xl font-black text-text-primary mb-3">Kategori Transaksi</h2>
                        <p class="text-text-secondary text-lg">Pilih jenis transaksi yang akan dilakukan oleh <strong
                                class="text-primary-600">{{ salesAccount }}</strong></p>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-3 gap-6 mb-12">
                        <button v-for="cat in categoriesPenjualan" :key="cat.id"
                            @click="transactionCategory = cat.id; nextStep()"
                            class="p-8 rounded-[1.5rem] border-2 transition-all duration-300 flex flex-col items-center gap-4 relative overflow-hidden group hover:-translate-y-1 hover:shadow-xl"
                            :class="transactionCategory === cat.id
                                ? 'border-primary-500 bg-primary-500/5 shadow-primary-500/10'
                                : 'border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-900/50 hover:border-primary-300'">
                            <div class="w-16 h-16 bg-white dark:bg-surface-800 rounded-[1.25rem] shadow-sm flex items-center justify-center transition-colors"
                                :class="transactionCategory === cat.id ? 'ring-2 ring-primary-500 ring-offset-2 dark:ring-offset-surface-800' : 'group-hover:text-primary-500'">
                                <ShoppingBag :size="32"
                                    :class="transactionCategory === cat.id ? 'text-primary-500' : 'text-text-secondary group-hover:text-primary-500'"
                                    stroke-width="1.5" />
                            </div>
                            <span
                                class="font-bold text-lg text-text-primary group-hover:text-primary-600 transition-colors">{{
                                    cat.label }}</span>
                            <div v-if="transactionCategory === cat.id"
                                class="absolute top-4 right-4 text-primary-500 bg-white dark:bg-surface-800 rounded-full shadow-sm p-0.5">
                                <CheckCircle :size="20" />
                            </div>
                        </button>
                    </div>

                    <div
                        class="flex flex-col sm:flex-row justify-between border-t border-surface-100 dark:border-surface-700 pt-8 gap-3 sm:gap-0">
                        <button @click="prevStep"
                            class="w-full sm:w-auto py-4 px-8 bg-surface-100 dark:bg-surface-700 hover:bg-surface-200 dark:hover:bg-surface-600 text-text-primary rounded-2xl font-bold text-lg transition-all flex items-center justify-center sm:justify-start gap-3">
                            <ArrowLeft :size="24" /> Kembali
                        </button>
                        <button @click="nextStep"
                            class="w-full sm:w-auto py-4 px-10 bg-primary-600 hover:bg-primary-500 text-white rounded-2xl font-bold text-lg shadow-xl shadow-primary-500/20 transition-all flex items-center justify-center sm:justify-start gap-3">
                            Lanjut
                            <ArrowRight :size="24" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- STEP 3: ITEM SELECTION -->
            <div v-if="currentStep === 3" class="flex-1 flex flex-col lg:flex-row gap-8 min-h-0 animate-fade-in">
                <!-- Products -->
                <div v-if="transactionCategory !== 'angkat_barang' && transactionCategory !== 'refund'"
                    class="flex-[2] flex flex-col min-w-0">
                    <div
                        class="bg-white dark:bg-surface-800 rounded-[1.5rem] border border-surface-200 dark:border-surface-700 p-6 mb-6 shadow-sm flex flex-col md:flex-row gap-4 items-center">
                        <div class="relative flex-1 w-full">
                            <Search class="absolute left-5 top-1/2 -translate-y-1/2 text-text-secondary" :size="20" />
                            <input v-model="searchQuery" type="text" placeholder="Cari..."
                                class="w-full bg-surface-50 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 rounded-2xl pl-12 pr-4 py-3 sm:py-4 text-base sm:text-lg font-medium text-text-primary focus:outline-none focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 transition-all" />
                        </div>

                        <!-- Bundling Button (Only for Penjualan flow) -->
                        <div v-if="transactionCategory === 'penjualan' || transactionCategory === 'bundling'"
                            class="flex items-center gap-4">
                            <button @click="openBundlingModal"
                                class="px-6 py-4 bg-primary-600 hover:bg-primary-500 text-white rounded-2xl font-bold flex items-center gap-2 shadow-lg shadow-primary-500/20 transition-all active:scale-95">
                                <Plus :size="20" stroke-width="3" />
                                Buat Bundling
                            </button>
                        </div>
                    </div>

                    <div
                        class="flex-1 overflow-y-auto overflow-x-auto custom-scrollbar bg-white dark:bg-surface-800 rounded-[1.5rem] border border-surface-200 dark:border-surface-700 mb-4 shadow-sm">

                        <!-- Table for Tablet/Desktop -->
                        <table class="w-full text-left border-collapse hidden md:table">
                            <thead class="sticky top-0 bg-surface-50 dark:bg-surface-900 z-10">
                                <tr>
                                    <th
                                        class="px-6 py-5 text-xs font-black text-text-secondary uppercase tracking-widest border-b border-surface-200 dark:border-surface-700">
                                        Produk & Brand</th>
                                    <th
                                        class="px-6 py-5 text-xs font-black text-text-secondary uppercase tracking-widest border-b border-surface-200 dark:border-surface-700">
                                        Spek & Kondisi</th>
                                    <th
                                        class="px-6 py-5 text-xs font-black text-text-secondary uppercase tracking-widest border-b border-surface-200 dark:border-surface-700">
                                        IMEI / Stok</th>
                                    <th
                                        class="hidden xl:table-cell px-6 py-5 text-xs font-black text-text-secondary uppercase tracking-widest border-b border-surface-200 dark:border-surface-700">
                                        Distributor</th>
                                    <th
                                        class="px-6 py-5 text-xs font-black text-text-secondary uppercase tracking-widest border-b border-surface-200 dark:border-surface-700 text-right">
                                        Harga</th>
                                    <th class="px-6 py-5 border-b border-surface-200 dark:border-surface-700 w-24">
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-surface-100 dark:divide-surface-700">
                                <tr v-for="item in filteredProducts" :key="item.id"
                                    class="hover:bg-primary-50/50 dark:hover:bg-primary-900/10 transition-colors group">
                                    <td class="px-6 py-5">
                                        <div class="flex flex-col gap-1">
                                            <span class="font-black text-text-primary text-base">{{ item.product?.name
                                                ||
                                                item.name }}</span>
                                            <span class="text-xs text-primary-600 font-bold uppercase tracking-wider">{{
                                                item.product?.brand || '-' }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-5">
                                        <div class="flex flex-col items-start gap-2">
                                            <span
                                                class="text-sm font-bold text-text-primary bg-surface-100 dark:bg-surface-800 px-3 py-1 rounded-lg">{{
                                                    item.ram || '-'
                                                }} / {{ item.storage || '-' }}</span>
                                            <span class="text-xs uppercase px-3 py-1 rounded-lg font-bold"
                                                :class="item.condition === 'new' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400'">
                                                {{ item.condition || 'Second' }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-5">
                                        <code v-if="item.imei"
                                            class="text-sm font-mono font-bold text-text-primary bg-surface-50 dark:bg-surface-900 px-3 py-1.5 rounded-lg border border-surface-200 dark:border-surface-700">
                                            {{ item.imei }}
                                        </code>
                                        <span v-else
                                            class="text-sm font-black text-primary-600 bg-primary-500/10 px-4 py-1.5 rounded-lg">
                                            Sisa: {{ getRemainingStock(item) }}
                                        </span>
                                    </td>
                                    <td class="hidden xl:table-cell px-6 py-5">
                                        <span class="text-sm font-semibold text-text-secondary">{{
                                            item.distributor?.name ||
                                            item.supplier_name || '-' }}</span>
                                    </td>
                                    <td class="px-6 py-5 text-right">
                                        <span class="text-lg font-black text-primary-600">{{
                                            formatCurrency(item.selling_price || item.price) }}</span>
                                    </td>
                                    <td class="px-6 py-5 text-right">
                                        <button v-if="!isItemFullyOccupied(item)" @click="addToCart(item)"
                                            class="w-12 h-12 flex items-center justify-center bg-primary-100 text-primary-600 hover:bg-primary-600 hover:text-white dark:bg-primary-900/50 dark:text-primary-400 dark:hover:bg-primary-600 dark:hover:text-white rounded-xl transition-all shadow-sm active:scale-95 ml-auto">
                                            <Plus :size="24" stroke-width="3" />
                                        </button>
                                        <div v-else class="flex flex-col items-end">
                                            <div
                                                class="flex items-center gap-1 text-emerald-600 font-black text-[10px] uppercase tracking-widest bg-emerald-500/10 px-3 py-2 rounded-lg">
                                                <CheckCircle :size="14" />
                                                {{ getCartStatus(item.id) }}
                                            </div>
                                        </div>
                                    </td>
                                </tr>

                            </tbody>
                        </table>

                        <!-- Card list for Mobile -->
                        <div class="md:hidden divide-y divide-surface-100 dark:divide-surface-700">
                            <div v-for="item in filteredProducts" :key="item.id" class="p-4 flex flex-col gap-3">
                                <div class="flex justify-between items-start">
                                    <div class="flex flex-col gap-1">
                                        <span class="font-black text-text-primary text-base leading-tight">{{
                                            item.product?.name || item.name }}</span>
                                        <span class="text-[10px] text-primary-600 font-bold uppercase tracking-wider">{{
                                            item.product?.brand || '-' }}</span>
                                    </div>
                                    <button v-if="!isItemFullyOccupied(item)" @click="addToCart(item)"
                                        class="w-10 h-10 flex items-center justify-center bg-primary-600 text-white rounded-xl shadow-lg active:scale-95">
                                        <Plus :size="20" stroke-width="3" />
                                    </button>
                                    <div v-else
                                        class="flex items-center gap-1 text-emerald-600 font-black text-[10px] uppercase tracking-widest bg-emerald-500/10 px-3 py-1.5 rounded-lg">
                                        <CheckCircle :size="12" />
                                        {{ getCartStatus(item.id) }}
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <span
                                        class="text-[10px] font-bold text-text-primary bg-surface-100 dark:bg-surface-800 px-2 py-0.5 rounded-md">{{
                                            item.ram || '-' }} / {{ item.storage || '-' }}</span>
                                    <span class="text-[10px] uppercase px-2 py-0.5 rounded-md font-bold"
                                        :class="item.condition === 'new' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400'">
                                        {{ item.condition || 'Second' }}
                                    </span>
                                    <code v-if="item.imei"
                                        class="text-[10px] font-mono font-bold text-text-secondary truncate max-w-[120px]">{{ item.imei }}</code>
                                    <span v-else class="text-[10px] font-black text-primary-600">Sisa: {{
                                        getRemainingStock(item) }}</span>
                                </div>
                                <div class="flex justify-between items-center mt-1">
                                    <span class="text-xs text-text-secondary">{{ item.distributor?.name ||
                                        item.supplier_name || '-' }}</span>
                                    <span class="text-base font-black text-primary-600">{{
                                        formatCurrency(item.selling_price || item.price) }}</span>
                                </div>
                            </div>
                        </div>

                        <div v-if="filteredProducts.length === 0" class="px-6 py-20 text-center">
                            <div class="flex flex-col items-center justify-center text-text-secondary">
                                <Search :size="48" class="mb-4 opacity-50" />
                                <span class="text-lg font-medium">Produk tidak ditemukan</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ANGAKAT BARANG FORM -->
                <div v-else-if="transactionCategory === 'angkat_barang'"
                    class="flex-1 overflow-y-auto custom-scrollbar bg-white dark:bg-surface-800 rounded-[2rem] border border-surface-200 dark:border-surface-700 p-8 shadow-xl">
                    <div class="max-w-4xl mx-auto">
                        <h3 class="text-2xl font-black text-text-primary mb-8 flex items-center gap-3">
                            <Receipt :size="28" class="text-primary-500" stroke-width="2.5" /> Formulir Angkat Barang
                        </h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <!-- Customer Info -->
                            <div class="space-y-6">
                                <h4
                                    class="text-sm font-black text-primary-600 uppercase tracking-widest border-b border-primary-100 dark:border-primary-900/30 pb-2">
                                    Data Customer</h4>
                                <div>
                                    <label
                                        class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Nama
                                        Customer <span class="text-red-500">*</span></label>
                                    <input v-model="tradeInForm.customer_name" type="text" placeholder="Nama lengkap..."
                                        class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none" />
                                </div>
                                <div>
                                    <label
                                        class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">No
                                        WhatsApp <span class="text-red-500">*</span></label>
                                    <input v-model="tradeInForm.customer_phone" type="text" placeholder="08xxx..."
                                        class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none" />
                                </div>
                                <div>
                                    <label
                                        class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Sumber
                                        Handphone <span class="text-red-500">*</span></label>
                                    <select v-model="tradeInForm.source"
                                        class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none">
                                        <option value="pstore">ex pstore</option>
                                        <option value="luar_pstore">Luar pstore</option>
                                    </select>
                                </div>
                            </div>

                            <!-- HP Specs -->
                            <div class="space-y-6">
                                <h4
                                    class="text-sm font-black text-primary-600 uppercase tracking-widest border-b border-primary-100 dark:border-primary-900/30 pb-2">
                                    Spesifikasi Unit</h4>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label
                                            class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Brand
                                            <span class="text-red-500">*</span></label>
                                        <select v-model="tradeInForm.brand_id"
                                            class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none">
                                            <option :value="null" disabled>Pilih Brand</option>
                                            <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}
                                            </option>
                                        </select>
                                    </div>
                                    <div>
                                        <label
                                            class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Tipe
                                            <span class="text-red-500">*</span></label>
                                        <select v-model="tradeInForm.product_type_id"
                                            class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none"
                                            :disabled="!tradeInForm.brand_id">
                                            <option :value="null" disabled>Pilih Tipe</option>
                                            <option v-for="p in filteredTradeInTypes" :key="p.id" :value="p.id">{{
                                                p.name }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label
                                            class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Kapasitas
                                            (Internal) <span class="text-red-500">*</span></label>
                                        <select v-model="tradeInForm.storage"
                                            class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none"
                                            :disabled="!tradeInForm.product_type_id">
                                            <option value="" disabled>Pilih Kapasitas</option>
                                            <option v-for="storage in filteredTradeInCapacities" :key="storage"
                                                :value="storage">{{ storage }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label
                                            class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Kondisi
                                            <span class="text-red-500">*</span></label>
                                        <select v-model="tradeInForm.condition"
                                            class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none"
                                            :disabled="!tradeInForm.storage">
                                            <option value="" disabled>Pilih Kondisi</option>
                                            <option v-for="cond in filteredTradeInConditions" :key="cond" :value="cond">
                                                {{
                                                    cond === 'new' ? 'New' : (cond === 'ex_ibox' ? 'Ex iBox' :
                                                        'Second / SCD') }}</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div v-if="isImeiTradeIn">
                                <label
                                    class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Daftar
                                    IMEI (Pisahkan tiap baris/koma) <span class="text-red-500">*</span></label>
                                <textarea :value="tradeInForm.imeis_raw" @input="handleImeiInput" rows="3"
                                    placeholder="Masukkan IMEI...&#10;Contoh:&#10;351234...&#10;355678..."
                                    class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none font-mono text-sm"></textarea>
                                <div class="mt-2 flex items-center justify-between text-[10px] font-bold">
                                    <span :class="totalTradeInUnits > 0 ? 'text-primary-500' : 'text-text-secondary'">
                                        {{ totalTradeInUnits }} Unit terdeteksi
                                    </span>
                                    <span class="text-text-secondary italic">Hanya angka (0-9)</span>
                                </div>
                            </div>
                            <div v-else>
                                <label
                                    class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Jumlah
                                    Stok <span class="text-red-500">*</span></label>
                                <div class="flex items-center gap-4">
                                    <input v-model.number="tradeInForm.quantity" type="number" min="1"
                                        class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none" />
                                    <span class="text-xs font-bold text-text-secondary uppercase">Unit</span>
                                </div>
                            </div>
                        </div>

                        <!-- Financial & Media -->
                        <div class="space-y-6">
                            <h4
                                class="text-sm font-black text-primary-600 uppercase tracking-widest border-b border-primary-100 dark:border-primary-900/30 pb-2">
                                Pembayaran & Bukti</h4>
                            <div>
                                <label
                                    class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Harga
                                    Angkat <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <span
                                        class="absolute left-4 top-1/2 -translate-y-1/2 text-sm font-bold text-text-secondary">Rp</span>
                                    <input type="text" :value="displayBuyPrice" @input="handleBuyPriceInput"
                                        class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl pl-10 pr-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none font-black text-lg text-primary-600" />
                                </div>
                            </div>
                            <div>
                                <label
                                    class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Metode
                                    Pembayaran <span class="text-red-500">*</span></label>
                                <select v-model="tradeInForm.payment_method_id"
                                    class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none">
                                    <option v-for="m in availablePaymentMethods" :key="m.id" :value="m.id">{{ m.name
                                    }}</option>
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label
                                        class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2 text-center">Foto
                                        Unit <span class="text-red-500">*</span></label>
                                    <div @click="$refs.unitInput.click()"
                                        class="relative border-2 border-dashed border-surface-300 dark:border-surface-600 rounded-xl aspect-square flex flex-col items-center justify-center cursor-pointer hover:bg-surface-50 dark:hover:bg-surface-800 transition-all overflow-hidden group">
                                        <template v-if="tradeInPhotos.unitPreview">
                                            <img :src="tradeInPhotos.unitPreview" class="w-full h-full object-cover" />
                                            <div
                                                class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity">
                                                <span class="text-white text-[10px] font-black uppercase">Ganti</span>
                                            </div>
                                        </template>
                                        <template v-else>
                                            <Plus :size="24" class="text-text-secondary mb-1" />
                                            <span class="text-[9px] font-black text-text-secondary uppercase">Upload
                                                Unit</span>
                                        </template>
                                        <input type="file" ref="unitInput" @change="e => handlePhotoUpload('unit', e)"
                                            accept="image/*" class="hidden" capture="environment" />
                                    </div>
                                </div>
                                <div>
                                    <label
                                        class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2 text-center">Foto
                                        Customer</label>
                                    <div @click="$refs.customerInput.click()"
                                        class="relative border-2 border-dashed border-surface-300 dark:border-surface-600 rounded-xl aspect-square flex flex-col items-center justify-center cursor-pointer hover:bg-surface-50 dark:hover:bg-surface-800 transition-all overflow-hidden group">
                                        <template v-if="tradeInPhotos.customerPreview">
                                            <img :src="tradeInPhotos.customerPreview"
                                                class="w-full h-full object-cover" />
                                            <div
                                                class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity">
                                                <span class="text-white text-[10px] font-black uppercase">Ganti</span>
                                            </div>
                                        </template>
                                        <template v-else>
                                            <Plus :size="24" class="text-text-secondary mb-1" />
                                            <span class="text-[9px] font-black text-text-secondary uppercase">Upload
                                                Customer</span>
                                        </template>
                                        <input type="file" ref="customerInput"
                                            @change="e => handlePhotoUpload('customer', e)" accept="image/*"
                                            class="hidden" capture="environment" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Additional info -->
                        <div class="space-y-6">
                            <h4
                                class="text-sm font-black text-primary-600 uppercase tracking-widest border-b border-primary-100 dark:border-primary-900/30 pb-2">
                                Informasi Tambahan</h4>
                            <div>
                                <label
                                    class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Alasan
                                    Angkat
                                    (Opsional)</label>
                                <textarea v-model="tradeInForm.reason" rows="2"
                                    class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none text-sm"
                                    placeholder="Kenapa barang ini diangkat?"></textarea>
                            </div>
                            <div>
                                <label
                                    class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Keterangan
                                    Tambahan
                                    (Opsional)</label>
                                <textarea v-model="tradeInForm.notes" rows="2"
                                    class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none text-sm"
                                    placeholder="Catatan tambahan..."></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Section -->
                    <div
                        class="mt-12 pt-8 border-t border-surface-100 dark:border-surface-700 flex flex-col sm:flex-row gap-4">
                        <button @click="prevStep"
                            class="flex-1 py-4 bg-surface-100 dark:bg-surface-700 text-text-primary rounded-2xl font-black uppercase tracking-widest hover:bg-surface-200 transition-all">
                            Kembali ke Kategori
                        </button>
                        <button @click="submitTradeIn" :disabled="isSubmitting"
                            class="flex-[2] py-4 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white rounded-2xl font-black uppercase tracking-widest shadow-xl shadow-emerald-500/20 transition-all flex items-center justify-center gap-3">
                            <Loader2 v-if="isSubmitting" class="animate-spin" :size="24" />
                            <template v-else>
                                <Save :size="24" /> Selesaikan & Simpan ke Inventory
                            </template>
                        </button>
                    </div>
                </div>

                <!-- REFUND FORM -->
                <div v-else-if="transactionCategory === 'refund'"
                    class="flex-1 overflow-y-auto custom-scrollbar bg-white dark:bg-surface-800 rounded-[2rem] border border-surface-200 dark:border-surface-700 p-8 shadow-xl">
                    <div class="max-w-4xl mx-auto">
                        <div class="flex items-center justify-between mb-8">
                            <h3 class="text-2xl font-black text-text-primary flex items-center gap-3">
                                <ArrowLeft :size="28" class="text-primary-500 cursor-pointer" @click="prevStep" />
                                Formulir Refund
                                Barang
                            </h3>
                            <div
                                class="px-4 py-1.5 bg-primary-100 dark:bg-primary-900/30 text-primary-600 rounded-full text-xs font-black uppercase tracking-widest">
                                Masuk ke Inventory
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <!-- Customer Info -->
                            <div class="space-y-6">
                                <h4
                                    class="text-sm font-black text-primary-600 uppercase tracking-widest border-b border-primary-100 dark:border-primary-900/30 pb-2">
                                    Data Customer</h4>
                                <div>
                                    <label
                                        class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Nama
                                        Customer <span class="text-red-500">*</span></label>
                                    <input v-model="refundForm.customer_name" type="text" placeholder="Nama lengkap..."
                                        class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none" />
                                </div>
                                <div>
                                    <label
                                        class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">No
                                        WhatsApp <span class="text-red-500">*</span></label>
                                    <input v-model="refundForm.customer_phone" type="text" placeholder="08xxx..."
                                        class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none" />
                                </div>
                            </div>

                            <!-- HP Specs -->
                            <div class="space-y-6">
                                <h4
                                    class="text-sm font-black text-primary-600 uppercase tracking-widest border-b border-primary-100 dark:border-primary-900/30 pb-2">
                                    Spesifikasi Unit</h4>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label
                                            class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Brand
                                            <span class="text-red-500">*</span></label>
                                        <select v-model="refundForm.brand_id"
                                            class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none">
                                            <option :value="null" disabled>Pilih Brand</option>
                                            <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}
                                            </option>
                                        </select>
                                    </div>
                                    <div>
                                        <label
                                            class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Tipe
                                            <span class="text-red-500">*</span></label>
                                        <select v-model="refundForm.product_type_id"
                                            class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none"
                                            :disabled="!refundForm.brand_id">
                                            <option :value="null" disabled>Pilih Tipe</option>
                                            <option v-for="p in filteredRefundTypes" :key="p.id" :value="p.id">{{
                                                p.name }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label
                                            class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">RAM
                                            <span class="text-red-500">*</span></label>
                                        <select v-model="refundForm.ram"
                                            class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none"
                                            :disabled="!refundForm.product_type_id">
                                            <option value="" disabled>Pilih RAM</option>
                                            <option v-for="r in filteredRefundRAMs" :key="r" :value="r">{{ r }}
                                            </option>
                                            <option value="Non-HP">Non-HP</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label
                                            class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Kategori
                                            <span class="text-red-500">*</span></label>
                                        <select v-model="refundForm.condition"
                                            class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none">
                                            <option value="" disabled>Pilih Kategori</option>
                                            <option value="new">New</option>
                                            <option value="second">Second / SCD</option>
                                            <option value="ex_ibox">Ex iBox</option>
                                        </select>
                                    </div>
                                </div>
                                <div v-if="isImeiRefund">
                                    <label
                                        class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Masukkan
                                        IMEI <span class="text-red-500">*</span></label>
                                    <input v-model="refundForm.imei" type="text" placeholder="15 digit IMEI..."
                                        class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none" />
                                </div>
                            </div>
                        </div>

                        <!-- Financial & Media -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-8">
                            <div class="space-y-6">
                                <h4
                                    class="text-sm font-black text-primary-600 uppercase tracking-widest border-b border-primary-100 dark:border-primary-900/30 pb-2">
                                    Pembayaran & Bukti</h4>
                                <div>
                                    <label
                                        class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Harga
                                        Refund <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <span
                                            class="absolute left-4 top-1/2 -translate-y-1/2 text-sm font-bold text-text-secondary">Rp</span>
                                        <input type="text" :value="displayRefundPrice" @input="handleRefundPriceInput"
                                            class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl pl-10 pr-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none font-black text-lg text-primary-600" />
                                    </div>
                                    <p class="mt-1 text-[10px] text-text-secondary font-medium italic">*Harga ini
                                        akan otomatis menjadi
                                        harga modal unit</p>
                                </div>
                                <div>
                                    <label
                                        class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Metode
                                        Pembayaran <span class="text-red-500">*</span></label>
                                    <select v-model="refundForm.payment_method_id"
                                        class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none">
                                        <option v-for="m in availablePaymentMethods" :key="m.id" :value="m.id">{{
                                            m.name
                                            }}</option>
                                    </select>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label
                                            class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2 text-center">Foto
                                            Unit <span class="text-red-500">*</span></label>
                                        <div @click="$refs.unitRefundInput.click()"
                                            class="relative border-2 border-dashed border-surface-300 dark:border-surface-600 rounded-xl aspect-square flex flex-col items-center justify-center cursor-pointer hover:bg-surface-50 dark:hover:bg-surface-800 transition-all overflow-hidden group">
                                            <template v-if="refundPhotos.unitPreview">
                                                <img :src="refundPhotos.unitPreview"
                                                    class="w-full h-full object-cover" />
                                                <div
                                                    class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity">
                                                    <span
                                                        class="text-white text-[10px] font-black uppercase">Ganti</span>
                                                </div>
                                            </template>
                                            <template v-else>
                                                <Plus :size="24" class="text-text-secondary mb-1" />
                                                <span class="text-[9px] font-black text-text-secondary uppercase">Upload
                                                    Unit</span>
                                            </template>
                                            <input type="file" ref="unitRefundInput"
                                                @change="e => handleRefundPhotoUpload('unit', e)" accept="image/*"
                                                class="hidden" capture="environment" />
                                        </div>
                                    </div>
                                    <div>
                                        <label
                                            class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2 text-center">Foto
                                            Customer</label>
                                        <div @click="$refs.customerRefundInput.click()"
                                            class="relative border-2 border-dashed border-surface-300 dark:border-surface-600 rounded-xl aspect-square flex flex-col items-center justify-center cursor-pointer hover:bg-surface-50 dark:hover:bg-surface-800 transition-all overflow-hidden group">
                                            <template v-if="refundPhotos.customerPreview">
                                                <img :src="refundPhotos.customerPreview"
                                                    class="w-full h-full object-cover" />
                                                <div
                                                    class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity">
                                                    <span
                                                        class="text-white text-[10px] font-black uppercase">Ganti</span>
                                                </div>
                                            </template>
                                            <template v-else>
                                                <Plus :size="24" class="text-text-secondary mb-1" />
                                                <span class="text-[9px] font-black text-text-secondary uppercase">Upload
                                                    Customer</span>
                                            </template>
                                            <input type="file" ref="customerRefundInput"
                                                @change="e => handleRefundPhotoUpload('customer', e)" accept="image/*"
                                                class="hidden" capture="environment" />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Additional info -->
                            <div class="space-y-6">
                                <h4
                                    class="text-sm font-black text-primary-600 uppercase tracking-widest border-b border-primary-100 dark:border-primary-900/30 pb-2">
                                    Informasi Tambahan</h4>
                                <div>
                                    <label
                                        class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Alasan
                                        Refund <span class="text-red-500">*</span></label>
                                    <textarea v-model="refundForm.reason" rows="3"
                                        class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none text-sm"
                                        placeholder="Kenapa barang ini direfund? (Wajib diisi)"></textarea>
                                </div>
                                <div>
                                    <label
                                        class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-2">Keterangan
                                        Tambahan (Opsional)</label>
                                    <textarea v-model="refundForm.notes" rows="3"
                                        class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-surface-50 dark:bg-surface-900 focus:border-primary-500 transition-all outline-none text-sm"
                                        placeholder="Catatan tambahan..."></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Section -->
                        <div
                            class="mt-12 pt-8 border-t border-surface-100 dark:border-surface-700 flex flex-col sm:flex-row gap-4">
                            <button @click="prevStep"
                                class="flex-1 py-4 bg-surface-100 dark:bg-surface-700 text-text-primary rounded-2xl font-black uppercase tracking-widest hover:bg-surface-200 transition-all">
                                Kembali ke Kategori
                            </button>
                            <button @click="submitRefund" :disabled="isSubmitting"
                                class="flex-[2] py-4 bg-primary-600 hover:bg-primary-500 disabled:opacity-50 text-white rounded-2xl font-black uppercase tracking-widest shadow-xl shadow-primary-500/20 transition-all flex items-center justify-center gap-3">
                                <Loader2 v-if="isSubmitting" class="animate-spin" :size="24" />
                                <template v-else>
                                    <Save :size="24" /> Proses Refund & Simpan ke Inventory
                                </template>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Cart Sidebar (Sticky in step 3) - Only show for other categories -->
                <div v-if="transactionCategory !== 'angkat_barang' && transactionCategory !== 'refund'"
                    class="w-full lg:w-[450px] flex flex-col bg-white dark:bg-surface-800 rounded-[1.5rem] border border-surface-200 dark:border-surface-700 shadow-xl overflow-hidden shrink-0">
                    <div
                        class="p-6 border-b border-surface-100 dark:border-surface-700 bg-surface-50 dark:bg-surface-900 flex items-center justify-between font-bold">
                        <div class="flex items-center gap-3">
                            <ShoppingCart :size="24" class="text-primary-500" stroke-width="2.5" />
                            <span class="text-xl">Keranjang <span
                                    class="text-primary-500 font-black px-2 py-0.5 bg-primary-500/10 rounded-lg ml-1">{{
                                        cartItemCount }}</span></span>
                        </div>
                    </div>

                    <div class="flex-1 overflow-y-auto p-6 custom-scrollbar">
                        <div v-if="cartItems.length === 0"
                            class="h-full flex flex-col items-center justify-center text-text-secondary opacity-50">
                            <ShoppingCart :size="64" class="mb-6" stroke-width="1.5" />
                            <p class="text-xl font-medium">Keranjang Kosong</p>
                            <p class="text-sm mt-2">Pilih produk dari daftar di sebelah kiri.</p>
                        </div>
                        <div v-else class="space-y-4">
                            <div v-for="item in cartItems" :key="item.id"
                                class="p-5 bg-white dark:bg-surface-800 border-2 border-surface-100 dark:border-surface-700 rounded-2xl relative shadow-sm group hover:border-surface-300 dark:hover:border-surface-600 transition-colors">
                                <div class="flex justify-between items-start mb-4 pr-8">
                                    <div class="min-w-0 flex flex-col gap-1">
                                        <p class="text-sm font-black text-text-primary line-clamp-2 leading-tight">
                                            {{
                                                item.product?.name ||
                                                item.name }}</p>
                                        <span v-if="item.imei"
                                            class="text-xs font-mono font-bold text-text-secondary bg-surface-50 dark:bg-surface-900 px-2 py-1 rounded w-fit">{{
                                                item.imei }}</span>
                                    </div>
                                    <button @click="removeFromCart(item.id)"
                                        class="text-surface-400 hover:text-red-500 absolute top-4 right-4 bg-surface-50 dark:bg-surface-900 p-2 rounded-full transition-colors">
                                        <Trash2 :size="18" />
                                    </button>
                                </div>
                                <div
                                    class="flex flex-col sm:flex-row justify-between items-start sm:items-end border-t border-surface-100 dark:border-surface-700 pt-4 gap-4 sm:gap-0">
                                    <div class="flex items-center gap-3 w-full sm:w-auto">
                                        <button v-if="!item.imei" @click="decrementQty(item.id)"
                                            class="w-10 h-10 sm:w-8 sm:h-8 flex items-center justify-center bg-surface-100 dark:bg-surface-700 rounded-lg text-text-primary hover:bg-surface-200 transition-colors font-black text-lg sm:text-base">-</button>
                                        <span class="text-base sm:text-sm font-black px-2">
                                            {{ item.quantity }}<span
                                                class="text-text-secondary font-medium ml-1">x</span>
                                        </span>
                                        <button v-if="!item.imei" @click="incrementQty(item.id)"
                                            :disabled="isItemFullyOccupied(item)"
                                            class="w-10 h-10 sm:w-8 sm:h-8 flex items-center justify-center bg-surface-100 dark:bg-surface-700 disabled:opacity-30 disabled:cursor-not-allowed rounded-lg text-text-primary hover:bg-surface-200 transition-colors font-black text-lg sm:text-base">+</button>
                                    </div>
                                    <div class="flex flex-col items-start sm:items-end gap-3 sm:gap-2 w-full sm:w-auto">
                                        <!-- Price/Subtotal -->
                                        <div v-if="!item.imei && !item.is_bundle"
                                            class="flex items-center justify-between sm:justify-end gap-2 border-2 border-surface-200 dark:border-surface-700 rounded-xl bg-surface-50 dark:bg-surface-900 px-3 py-2 focus-within:border-primary-500 transition-all w-full sm:w-auto">
                                            <span
                                                class="text-[10px] sm:text-[9px] font-black text-text-secondary uppercase tracking-widest whitespace-nowrap">Harga
                                                Unit</span>
                                            <div class="flex items-center gap-1">
                                                <span class="text-[10px] font-bold text-text-secondary">Rp</span>
                                                <input type="text" :value="formatNumber(item.price)"
                                                    @input="e => handleItemPriceInput(item, e)"
                                                    class="w-24 sm:w-20 text-right text-sm sm:text-xs font-black bg-transparent outline-none focus:text-primary-600" />
                                            </div>
                                        </div>
                                        <p v-else class="text-sm font-black text-primary-600">{{
                                            formatCurrency(item.price) }}</p>

                                        <!-- Discount per Item -->
                                        <div class="flex flex-col gap-1 w-full sm:w-auto">
                                            <div
                                                class="flex items-center justify-between sm:justify-end gap-2 border-2 border-amber-200 dark:border-amber-900/30 rounded-xl bg-amber-50/50 dark:bg-amber-900/10 px-3 py-2 focus-within:border-amber-500 transition-all w-full sm:w-auto">
                                                <span
                                                    class="text-[10px] sm:text-[9px] font-black text-amber-600 uppercase tracking-widest whitespace-nowrap">Diskon
                                                    Unit</span>
                                                <div class="flex items-center gap-1">
                                                    <span class="text-[10px] font-bold text-amber-600">Rp</span>
                                                    <input type="text" :value="formatNumber(item.discount || 0)"
                                                        @input="e => handleItemDiscountInput(item, e)"
                                                        class="w-24 sm:w-20 text-right text-sm sm:text-xs font-black bg-transparent outline-none text-amber-600 placeholder:text-amber-300"
                                                        placeholder="0" />
                                                </div>
                                            </div>
                                            <div v-if="cartStore.getDistributedGlobalDiscount(item) > 0"
                                                class="px-2 py-1.5 sm:py-1 bg-primary-50 dark:bg-primary-900/10 rounded-lg border border-primary-100 dark:border-primary-900/20">
                                                <p
                                                    class="text-[10px] sm:text-[9px] font-black text-primary-600 uppercase tracking-tighter">
                                                    Pot. Global ({{ cartStore.globalDiscountPercentage.toFixed(1)
                                                    }}%):
                                                    -{{ formatCurrency(cartStore.getDistributedGlobalDiscount(item))
                                                    }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        class="p-6 bg-surface-50 dark:bg-surface-900 mt-auto border-t border-surface-200 dark:border-surface-700 shrink-0 space-y-4">

                        <!-- Mini Summary -->
                        <div class="space-y-2 border-b border-surface-200 dark:border-surface-700 pb-4">
                            <div
                                class="flex justify-between text-sm font-bold text-text-secondary uppercase tracking-widest">
                                <span>Subtotal</span>
                                <span>{{ formatCurrency(cartStore.subtotal) }}</span>
                            </div>
                            <div v-if="cartStore.itemDiscountTotal > 0"
                                class="flex justify-between text-xs font-bold text-amber-600 uppercase tracking-widest">
                                <span>Diskon Item</span>
                                <span>-{{ formatCurrency(cartStore.itemDiscountTotal) }}</span>
                            </div>
                        </div>

                        <!-- Global Discount Input -->
                        <div class="flex items-center justify-between gap-4">
                            <div class="flex flex-col">
                                <span
                                    class="text-[10px] font-black text-text-secondary uppercase tracking-widest leading-none mb-1">Diskon
                                    All</span>
                                <div class="flex items-center gap-1">
                                    <span class="text-[9px] text-text-secondary/50 font-bold uppercase">(Nota)</span>
                                    <span v-if="cartStore.globalDiscountPercentage > 0"
                                        class="text-[9px] bg-primary-500 text-white px-1.5 py-0.5 rounded-full font-black">
                                        {{ cartStore.globalDiscountPercentage.toFixed(1) }}%
                                    </span>
                                </div>
                            </div>
                            <div class="relative flex-1 max-w-[200px]">
                                <span
                                    class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-text-secondary">Rp</span>
                                <input type="text" :value="displayDiscount" @input="handleDiscountInput"
                                    class="w-full bg-white dark:bg-surface-800 border-2 border-surface-200 dark:border-surface-700 rounded-xl pl-9 pr-4 py-3 text-sm font-black text-primary-600 focus:outline-none focus:border-primary-500 transition-all text-right"
                                    placeholder="0" />
                            </div>
                        </div>

                        <div class="flex justify-between items-center text-2xl font-black pt-2">
                            <span class="text-text-primary text-sm sm:text-lg uppercase tracking-widest">Total
                                Bayar</span>
                            <span class="text-primary-600">{{ formatCurrency(cartStore.total) }}</span>
                        </div>
                        <div class="flex gap-3 pt-2">
                            <button @click="prevStep"
                                class="w-16 h-16 flex-none bg-white dark:bg-surface-800 text-text-primary border-2 border-surface-200 dark:border-surface-700 rounded-[1.25rem] font-bold transition-all flex items-center justify-center hover:bg-surface-50 hover:border-surface-300">
                                <ArrowLeft :size="24" />
                            </button>
                            <button @click="nextStep" :disabled="cartItems.length === 0"
                                class="flex-1 h-16 bg-primary-600 hover:bg-primary-500 disabled:opacity-50 text-white rounded-[1.25rem] font-bold text-lg shadow-xl shadow-primary-500/30 transition-all flex items-center justify-center gap-3">
                                Pembayaran
                                <ArrowRight :size="24" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- STEP 4: FORM & PAYMENT -->
            <div v-if="currentStep === 4"
                class="flex-1 flex flex-col lg:flex-row gap-8 min-h-0 overflow-y-auto custom-scrollbar animate-fade-in">
                <!-- Transaction Summary & Form -->
                <div class="flex-[2] space-y-8 min-w-0">
                    <div
                        class="bg-white dark:bg-surface-800 rounded-[1.5rem] sm:rounded-[2rem] border border-surface-200 dark:border-surface-700 p-6 sm:p-8 shadow-xl">
                        <h3
                            class="text-xl sm:text-2xl font-black text-text-primary mb-6 sm:mb-8 flex items-center gap-3">
                            <User :size="28" class="text-primary-500" stroke-width="2.5" /> Detail Pelanggan
                        </h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 sm:gap-6">
                            <div class="space-y-2">
                                <label
                                    class="block text-xs font-black text-text-secondary uppercase tracking-widest px-1">
                                    Nama Pelanggan <span class="text-red-500">*</span>
                                </label>
                                <input v-model="customerForm.customer_name" type="text" placeholder="Masukkan nama..."
                                    class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-2xl px-5 py-3.5 sm:py-4 bg-surface-50 dark:bg-surface-900 text-base sm:text-lg font-medium text-text-primary focus:outline-none focus:border-primary-500 focus:bg-white dark:focus:bg-surface-800 dark:text-white transition-all shadow-sm" />
                            </div>
                            <div class="space-y-2">
                                <label
                                    class="block text-xs font-black text-text-secondary uppercase tracking-widest px-1">
                                    WhatsApp Customer <span class="text-red-500">*</span>
                                </label>
                                <input v-model="customerForm.customer_phone" type="text" placeholder="08xxx..."
                                    class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-2xl px-5 py-3.5 sm:py-4 bg-surface-50 dark:bg-surface-900 text-base sm:text-lg font-medium text-text-primary focus:outline-none focus:border-primary-500 focus:bg-white dark:focus:bg-surface-800 dark:text-white transition-all shadow-sm" />
                            </div>
                            <div class="md:col-span-2 space-y-2">
                                <label
                                    class="block text-xs font-black text-text-secondary uppercase tracking-widest px-1">
                                    Keterangan / Notes <span class="text-red-500">*</span>
                                </label>
                                <textarea v-model="customerForm.notes" rows="2"
                                    placeholder="Catatan khusus untuk nota ini..."
                                    class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-2xl px-5 py-3.5 sm:py-4 bg-surface-50 dark:bg-surface-900 text-base sm:text-lg font-medium text-text-primary focus:outline-none focus:border-primary-500 focus:bg-white dark:focus:bg-surface-800 dark:text-white transition-all resize-none shadow-sm"></textarea>
                            </div>
                            <div class="md:col-span-2 space-y-3">
                                <label
                                    class="block text-xs font-black text-text-secondary uppercase tracking-widest px-1">
                                    Foto Bukti <span class="text-[10px] lowercase text-text-secondary font-medium">(Max
                                        10MB)</span>
                                    <span class="text-red-500">*</span>
                                </label>
                                <div class="flex flex-col gap-4">
                                    <div class="relative group">
                                        <input type="file" @change="handleFileChange" accept="image/*"
                                            capture="environment"
                                            class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" />
                                        <div
                                            class="w-full border-2 border-dashed border-surface-300 dark:border-surface-600 rounded-2xl p-4 sm:p-6 flex flex-col items-center justify-center gap-2 bg-surface-50 dark:bg-surface-900 group-hover:bg-primary-50 dark:group-hover:bg-primary-900/10 group-hover:border-primary-500 transition-all">
                                            <Upload class="text-text-secondary group-hover:text-primary-500" :size="24"
                                                stroke-width="1.5" />
                                            <div class="text-center">
                                                <p
                                                    class="text-sm font-black text-text-primary group-hover:text-primary-600 transition-colors">
                                                    Pilih atau Ambil Foto</p>
                                                <p
                                                    class="text-[10px] text-text-secondary font-medium uppercase tracking-widest">
                                                    Klik untuk mengupload bukti</p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- COMPRESSION LOADER -->
                                    <div v-if="isCompressing"
                                        class="flex items-center gap-3 p-4 bg-primary-50 dark:bg-primary-900/10 rounded-2xl border border-primary-100 dark:border-primary-500/20 animate-pulse">
                                        <Loader2 class="animate-spin text-primary-500" :size="20" />
                                        <span
                                            class="text-xs font-black text-primary-600 dark:text-primary-400 uppercase tracking-widest">Mengompres
                                            Foto...</span>
                                    </div>

                                    <div v-if="proofImagePreview && !isCompressing"
                                        class="relative w-full sm:w-48 aspect-square sm:aspect-auto sm:h-48 rounded-2xl overflow-hidden border-2 border-surface-200 dark:border-surface-700 shadow-sm bg-surface-100">
                                        <img :src="proofImagePreview" class="w-full h-full object-cover" />
                                        <button @click="proofImage = null; proofImagePreview = null"
                                            class="absolute top-2 right-2 bg-red-500 text-white p-2 rounded-full shadow-lg transition-transform active:scale-90 hover:bg-red-600">
                                            <X :size="16" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        class="bg-white dark:bg-surface-800 rounded-[1.5rem] sm:rounded-[2rem] border border-surface-200 dark:border-surface-700 p-5 sm:p-8 shadow-xl">
                        <h3 class="text-2xl font-black text-text-primary mb-8 flex items-center gap-3">
                            <ShoppingCart :size="28" class="text-primary-500" stroke-width="2.5" /> Ringkasan
                            Pembelian
                        </h3>
                        <div class="space-y-4">
                            <div v-for="item in cartItems" :key="item.id"
                                class="flex justify-between items-center bg-surface-50 dark:bg-surface-900 p-5 rounded-2xl border border-surface-100 dark:border-surface-700">
                                <div class="flex items-center gap-5">
                                    <div
                                        class="w-14 h-14 bg-white dark:bg-surface-800 border-2 border-surface-200 dark:border-surface-700 rounded-xl flex items-center justify-center font-black text-lg shadow-sm">
                                        {{ item.quantity }}<span class="text-xs text-text-secondary ml-0.5">x</span>
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <p class="font-black text-lg text-text-primary">{{ item.name }}</p>
                                        <p class="text-sm font-bold text-text-secondary">{{
                                            formatCurrency(item.price)
                                            }} / unit</p>
                                    </div>
                                </div>
                                <p class="font-black text-xl text-primary-600">{{ formatCurrency(item.price *
                                    item.quantity) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Block -->
                <div class="flex-[1.5] min-w-0">
                    <div
                        class="bg-white dark:bg-surface-800 rounded-[1.5rem] sm:rounded-[2rem] border border-surface-200 dark:border-surface-700 p-5 sm:p-8 shadow-2xl lg:sticky lg:top-0">

                        <!-- Validation Notice at Top for Mobile -->
                        <div v-if="missingFields.length > 0"
                            class="p-4 bg-orange-50 dark:bg-orange-500/10 border border-orange-200 dark:border-orange-500/20 rounded-xl flex items-start gap-3 mb-6">
                            <AlertCircle class="text-orange-500 dark:text-orange-400 shrink-0" :size="20" />
                            <div class="flex-1">
                                <p
                                    class="text-xs text-orange-700 dark:text-orange-300 font-bold mb-1 uppercase tracking-tight">
                                    Data Belum Lengkap:</p>
                                <ul
                                    class="text-[10px] sm:text-xs text-orange-600 dark:text-orange-400 font-medium list-disc list-inside">
                                    <li v-for="field in missingFields" :key="field">{{ field }}</li>
                                </ul>
                            </div>
                        </div>

                        <div
                            class="text-center mb-6 sm:mb-8 pb-6 sm:pb-8 border-b border-surface-100 dark:border-surface-700">
                            <p
                                class="text-text-secondary text-[10px] sm:text-xs font-black uppercase tracking-widest mb-2 sm:mb-3">
                                TOTAL
                                TAGIHAN</p>
                            <p class="text-3xl sm:text-5xl font-black text-primary-600 tracking-tight">{{
                                formatCurrency(cartTotal)
                                }}</p>
                        </div>

                        <div class="space-y-8">
                            <div>
                                <div class="flex items-center justify-between mb-4">
                                    <p class="text-sm font-black text-text-secondary uppercase tracking-widest">
                                        Metode
                                        Pembayaran (Split)</p>
                                    <button @click="addSplitPayment"
                                        class="text-xs font-bold text-primary-500 hover:text-primary-600 flex items-center gap-1 bg-primary-50 dark:bg-primary-900/20 px-3 py-2 rounded-lg transition-all active:scale-95">
                                        <Plus :size="14" stroke-width="3" /> Tambah Metode
                                    </button>
                                </div>

                                <div class="space-y-4">
                                    <div v-for="(payment, index) in splitPayments" :key="index"
                                        class="p-5 bg-surface-50 dark:bg-surface-900 rounded-2xl border-2 border-surface-100 dark:border-surface-700 relative group animate-fade-in">

                                        <button v-if="splitPayments.length > 1" @click="removeSplitPayment(index)"
                                            class="absolute top-4 right-4 text-surface-400 hover:text-red-500 transition-colors">
                                            <Trash2 :size="16" />
                                        </button>

                                        <div class="grid grid-cols-1 gap-4">
                                            <div>
                                                <label
                                                    class="block text-[10px] font-black text-text-secondary uppercase tracking-widest mb-2">Metode</label>
                                                <div class="relative">
                                                    <select v-model="payment.method_id"
                                                        class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-white dark:bg-surface-800 text-sm font-bold text-text-primary focus:outline-none focus:border-primary-500 transition-all appearance-none">
                                                        <option v-for="method in availablePaymentMethods"
                                                            :key="method.id" :value="method.id"
                                                            class="bg-white dark:bg-zinc-900 text-black dark:text-zinc-100">
                                                            {{ method.name }} {{ method.account_number ?
                                                                `(${method.account_number})` : '' }}
                                                        </option>
                                                    </select>
                                                    <div
                                                        class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-text-secondary">
                                                        <ChevronDown :size="18" />
                                                    </div>
                                                </div>
                                            </div>
                                            <div>
                                                <label
                                                    class="block text-[10px] font-black text-text-secondary uppercase tracking-widest mb-2">Nominal
                                                    Bayar</label>
                                                <div class="relative">
                                                    <span
                                                        class="absolute left-4 top-1/2 -translate-y-1/2 text-text-secondary text-sm font-black">Rp</span>
                                                    <input :value="payment.display_amount"
                                                        @input="e => handleSplitAmountInput(index, e)" type="text"
                                                        class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-white dark:bg-surface-800 text-text-primary text-xl font-black focus:outline-none focus:border-primary-500 transition-all pl-10"
                                                        placeholder="0" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Discount Section -->
                            <div class="pt-6 border-t border-surface-100 dark:border-surface-700">
                                <div class="flex items-center justify-between mb-4">
                                    <p class="text-sm font-black text-text-secondary uppercase tracking-widest">
                                        Diskon
                                        Tambahan</p>
                                    <div class="flex gap-1.5 bg-surface-100 dark:bg-surface-800 p-1 rounded-xl">
                                        <button @click="cartStore.discountType = 'percentage'"
                                            class="px-4 py-2 text-xs rounded-lg font-black transition-all"
                                            :class="cartStore.discountType === 'percentage' ? 'bg-primary-500 text-white shadow-md' : 'text-text-secondary hover:text-text-primary'">%</button>
                                        <button @click="cartStore.discountType = 'fixed'"
                                            class="px-4 py-2 text-xs rounded-lg font-black transition-all"
                                            :class="cartStore.discountType === 'fixed' ? 'bg-primary-500 text-white shadow-md' : 'text-text-secondary hover:text-text-primary'">Rp</button>
                                    </div>
                                </div>
                                <div class="relative">
                                    <span v-if="cartStore.discountType === 'fixed'"
                                        class="absolute left-5 top-1/2 -translate-y-1/2 text-text-secondary text-lg font-bold">Rp</span>
                                    <input :value="displayDiscount" @input="handleDiscountInput" type="text"
                                        class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-2xl px-5 py-4 bg-surface-50 dark:bg-surface-900 text-text-primary text-xl font-black focus:outline-none focus:border-primary-500 focus:bg-white dark:focus:bg-surface-800 transition-all"
                                        :class="cartStore.discountType === 'fixed' ? 'pl-14' : ''" placeholder="0" />
                                    <span v-if="cartStore.discountType === 'percentage'"
                                        class="absolute right-5 top-1/2 -translate-y-1/2 text-text-secondary text-lg font-bold">%</span>
                                </div>
                            </div>

                            <div v-if="cartStore.discountAmount > 0"
                                class="p-5 bg-primary-500/10 border border-primary-500/20 rounded-2xl flex justify-between items-center">
                                <span class="text-sm font-black text-primary-700">Potongan Diskon</span>
                                <span class="text-xl font-black text-primary-600">- {{
                                    formatCurrency(cartStore.discountAmount) }}</span>
                            </div>

                            <div v-if="isCashPayment" class="pt-6 border-t border-surface-100 dark:border-surface-700">
                                <div v-if="changeAmount >= 0"
                                    class="p-6 bg-emerald-500/10 border-2 border-emerald-500/20 rounded-2xl flex justify-between items-center">
                                    <span
                                        class="text-sm font-black text-emerald-700 uppercase tracking-widest">Kembalian</span>
                                    <span class="text-3xl font-black text-emerald-600">{{
                                        formatCurrency(changeAmount)
                                        }}</span>
                                </div>
                                <div v-else
                                    class="p-6 bg-red-500/10 border-2 border-red-500/20 rounded-2xl flex justify-between items-center">
                                    <span
                                        class="text-sm font-black text-red-700 uppercase tracking-widest">Kurang</span>
                                    <span class="text-3xl font-black text-red-600">{{
                                        formatCurrency(Math.abs(changeAmount)) }}</span>
                                </div>
                            </div>

                            <!-- Change/Balance Status -->
                            <div v-if="changeAmount < 0"
                                class="p-4 sm:p-6 bg-red-500/10 border-2 border-red-500/20 rounded-2xl flex flex-col sm:flex-row justify-between items-start sm:items-center my-6 animate-pulse gap-2 sm:gap-0">
                                <span
                                    class="text-[10px] sm:text-sm font-black text-red-700 dark:text-red-400 uppercase tracking-widest">Uang
                                    Kurang</span>
                                <span class="text-2xl sm:text-3xl font-black text-red-600 dark:text-red-500">{{
                                    formatCurrency(Math.abs(changeAmount))
                                    }}</span>
                            </div>
                            <div v-else-if="changeAmount >= 0"
                                class="p-4 sm:p-6 bg-emerald-500/10 border-2 border-emerald-500/20 rounded-2xl flex flex-col sm:flex-row justify-between items-start sm:items-center my-6 gap-2 sm:gap-0">
                                <span
                                    class="text-[10px] sm:text-sm font-black text-emerald-700 dark:text-emerald-400 uppercase tracking-widest">Kembalian</span>
                                <span class="text-2xl sm:text-3xl font-black text-emerald-600 dark:text-emerald-500">{{
                                    formatCurrency(changeAmount)
                                    }}</span>
                            </div>


                            <div class="flex gap-4 pt-8 border-t border-surface-100 dark:border-surface-700">
                                <button @click="prevStep"
                                    class="w-20 h-20 flex-none bg-surface-100 dark:bg-surface-800 hover:bg-surface-200 dark:hover:bg-surface-700 text-text-primary rounded-[1.25rem] font-bold transition-all flex items-center justify-center">
                                    <ArrowLeft :size="28" />
                                </button>
                                <button @click="handleSubmitOrder" :disabled="isSubmitting || isCompressing"
                                    class="flex-1 h-20 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 disabled:grayscale disabled:cursor-not-allowed text-white rounded-[1.25rem] font-black text-xl shadow-2xl shadow-emerald-500/30 transition-all flex items-center justify-center gap-3"
                                    :class="{ 'opacity-60 grayscale cursor-not-allowed': !isFormValid && !isSubmitting }">
                                    <Loader2 v-if="isSubmitting" class="animate-spin" :size="28" />
                                    <CheckCircle v-else :size="28" />
                                    <span>
                                        {{ submitButtonText }}
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SUCCESS MODAL -->
        <Teleport to="body">
            <div v-if="showSuccessModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/80 backdrop-blur-sm"></div>
                <div
                    class="relative bg-white dark:bg-surface-800 rounded-[2rem] sm:rounded-[2.5rem] border border-surface-200 dark:border-surface-700 w-full max-w-md p-6 sm:p-10 text-center shadow-2xl">
                    <div
                        class="w-28 h-28 mx-auto mb-8 bg-emerald-500/10 rounded-full flex items-center justify-center animate-bounce">
                        <CheckCircle class="text-emerald-500" :size="64" stroke-width="1.5" />
                    </div>
                    <h3 class="text-4xl font-black text-text-primary mb-3">Suksess!</h3>
                    <p class="text-text-secondary text-lg mb-10">Transaksi telah berhasil diproses & tersimpan</p>

                    <div v-if="lastTransaction"
                        class="bg-surface-50 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 rounded-[1.5rem] p-6 mb-10 text-left space-y-4">
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-text-secondary font-bold uppercase tracking-widest">Receipt ID</span>
                            <span
                                class="font-mono font-black text-text-primary bg-white dark:bg-surface-800 px-3 py-1 rounded-lg border border-surface-200 dark:border-surface-700">{{
                                    lastTransaction.order_no }}</span>
                        </div>
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-text-secondary font-bold uppercase tracking-widest">Sales</span>
                            <span class="font-black text-text-primary">{{ lastTransaction.sales_account }}</span>
                        </div>
                        <div class="h-px bg-surface-200 dark:bg-surface-700 my-4"></div>
                        <div class="flex justify-between items-end">
                            <span class="text-text-secondary font-bold uppercase tracking-widest mb-1">Total</span>
                            <span class="text-3xl font-black text-emerald-500">{{
                                formatCurrency(lastTransaction.total)
                                }}</span>
                        </div>
                    </div>

                    <button @click="closeSuccessModal"
                        class="w-full py-5 bg-primary-600 hover:bg-primary-500 text-white rounded-[1.25rem] font-bold text-lg transition-all shadow-xl shadow-primary-500/30 mb-4">
                        Mulai Transaksi Baru
                    </button>
                    <button @click="showReceiptModal = true; shouldAutoSendWA = false"
                        class="w-full py-4 text-text-secondary hover:text-text-primary hover:bg-surface-50 dark:hover:bg-surface-900 font-bold text-sm uppercase tracking-widest rounded-[1.25rem] flex items-center justify-center gap-2 transition-colors">
                        <Receipt :size="20" /> Cetak Struk Bukti
                    </button>

                    <button v-if="lastTransaction?.customer_phone"
                        @click="showReceiptModal = true; shouldAutoSendWA = true"
                        class="w-full py-4 mt-2 bg-emerald-500/10 text-emerald-600 hover:bg-emerald-500/20 font-bold text-sm uppercase tracking-widest rounded-[1.25rem] flex items-center justify-center gap-2 transition-colors">
                        <MessageSquare :size="20" /> WhatsApp Nota (PDF)
                    </button>
                </div>
            </div>
        </Teleport>

        <!-- RECEIPT MODAL -->
        <ReceiptModal :isOpen="showReceiptModal" :transaction="{
            id: lastTransaction?.id,
            order_no: lastTransaction?.order_no,
            date: lastTransaction?.time,
            customer_name: lastTransaction?.customer_name,
            customer_phone: lastTransaction?.customer_phone,
            items: lastTransaction?.items?.map(item => {
                let imei = '-';
                if (item.is_bundle && item.bundle_items) {
                    imei = item.bundle_items.map(bi => bi.imei).filter(i => i && i !== '-').join(', ') || '-';
                } else {
                    imei = item.imei || '-';
                }
                return {
                    name: item.name || item.product?.name,
                    qty: item.quantity,
                    price: item.price - (item.item_discount || 0), // Use Net Price
                    imei: imei,
                    item_discount: 0, // Hiding granular item discounts
                    distributed_discount: 0
                };
            }),
            grand_total: lastTransaction?.total,
            original_price: lastTransaction?.original_price,
            total_discount: lastTransaction?.global_discount_value,
            global_discount_value: lastTransaction?.global_discount_value,
            global_discount_type: 'fixed',
            cash: lastTransaction?.cash,
            transfer: lastTransaction?.transfer,
            payment_method: selectedPaymentMethodObj?.name || 'Tunai',
            outlet_name: 'PSTORE',
            outlet_address: 'Pusat Perbelanjaan Gadget Terlengkap'
        }" :autoSend="shouldAutoSendWA" @close="showReceiptModal = false; shouldAutoSendWA = false" />

        <!-- PIN VERIFICATION MODAL -->
        <PinModal :show="showPinModal" :mode="pinModalMode" :title="pinModalTitle" @close="showPinModal = false"
            @success="handlePinSuccess" />

        <!-- INITIAL PIN SETUP MODAL -->
        <PinModal :show="showInitialPinSetup" mode="setup" title="Setup PIN Transaksi"
            @close="showInitialPinSetup = false" @success="showInitialPinSetup = false" />

        <!-- BUNDLING MODAL -->
        <Teleport to="body">
            <div v-if="showBundlingModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="closeBundlingModal"></div>
                <div
                    class="relative bg-white dark:bg-surface-800 rounded-[2rem] border border-surface-200 dark:border-surface-700 w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden shadow-2xl">
                    <div
                        class="p-6 border-b border-surface-100 dark:border-surface-700 flex justify-between items-center">
                        <h3 class="text-2xl font-black text-text-primary">Buat Sistem Bundling</h3>
                        <button @click="closeBundlingModal"
                            class="p-2 hover:bg-surface-100 dark:hover:bg-surface-700 rounded-full transition-colors">
                            <X :size="24" />
                        </button>
                    </div>

                    <div class="flex-1 overflow-hidden flex flex-col md:flex-row">
                        <!-- Left: Item Picker -->
                        <div
                            class="flex-1 p-6 overflow-y-auto custom-scrollbar border-r border-surface-100 dark:border-surface-700">
                            <div class="mb-6 sticky top-0 bg-white dark:bg-surface-800 z-10 pb-4">
                                <div class="relative">
                                    <Search class="absolute left-4 top-1/2 -translate-y-1/2 text-text-secondary"
                                        :size="18" />
                                    <input v-model="searchQuery" type="text" placeholder="Cari item untuk bundle..."
                                        class="w-full bg-surface-50 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 rounded-xl pl-11 pr-4 py-3 text-sm font-medium focus:outline-none focus:border-primary-500 transition-all" />
                                </div>
                            </div>

                            <div class="space-y-3">
                                <div v-for="item in filteredProducts" :key="item.id" @click="addToBundle(item)"
                                    class="p-4 bg-surface-50 dark:bg-surface-900 rounded-xl border border-surface-200 dark:border-surface-700 hover:border-primary-500 cursor-pointer transition-all flex justify-between items-center group"
                                    :class="{ 'opacity-50 pointer-events-none border-emerald-500 bg-emerald-500/5': isItemFullyOccupied(item) }">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <p class="font-bold text-text-primary text-sm">{{ item.product?.name ||
                                                item.name }}</p>
                                            <CheckCircle v-if="getCartStatus(item.id)" :size="14"
                                                class="text-emerald-500" />
                                        </div>
                                        <p v-if="item.imei" class="text-xs font-mono text-text-secondary">{{
                                            item.imei
                                            }}</p>
                                        <p v-else
                                            class="text-[10px] font-black text-primary-600 bg-primary-500/10 px-2 py-0.5 rounded w-fit">
                                            Sisa: {{ getRemainingStock(item) }}
                                        </p>
                                        <p class="text-xs text-primary-600 font-bold mt-1">{{
                                            formatCurrency(item.selling_price || item.price) }}</p>
                                    </div>
                                    <Plus v-if="!isItemFullyOccupied(item)" :size="18"
                                        class="text-surface-400 group-hover:text-primary-500 transition-colors" />
                                    <span v-else
                                        class="text-[10px] font-black text-emerald-600 uppercase tracking-widest">{{
                                            getCartStatus(item.id) || 'Stok Habis' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Right: Selected Items & Final Price -->
                        <div class="w-full md:w-[350px] bg-surface-50 dark:bg-surface-900 p-6 flex flex-col">
                            <h4 class="text-sm font-black text-text-secondary uppercase tracking-widest mb-4">Item
                                Terpilih</h4>

                            <div class="flex-1 overflow-y-auto custom-scrollbar space-y-3 mb-6">
                                <div v-if="bundleItems.length === 0"
                                    class="h-full flex flex-col items-center justify-center text-text-secondary opacity-50 py-10">
                                    <ShoppingBag :size="48" class="mb-3" />
                                    <p class="text-xs font-medium text-center">Belum ada item dipilih</p>
                                </div>
                                <div v-for="(item, idx) in bundleItems" :key="item.id"
                                    class="p-4 bg-white dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 animate-fade-in relative group/item">
                                    <button @click="removeFromBundle(idx)"
                                        class="absolute top-2 right-2 text-surface-400 hover:text-red-500 p-1 hover:bg-red-50 dark:hover:bg-red-500/10 rounded-lg transition-colors z-10">
                                        <Trash2 :size="16" />
                                    </button>

                                    <div class="mb-3 pr-6">
                                        <p class="text-xs font-black text-text-primary truncate">{{
                                            item.product?.name
                                            || item.name }}</p>
                                        <p v-if="item.imei" class="text-[10px] font-mono text-text-secondary">{{
                                            item.imei }}</p>
                                    </div>

                                    <div class="flex items-center justify-between gap-3">
                                        <!-- Quantity Controls for non-IMEI -->
                                        <div v-if="!item.imei"
                                            class="flex items-center bg-surface-100 dark:bg-surface-900 rounded-lg border border-surface-200 dark:border-surface-700 h-9">
                                            <button @click="decrementBundleItemQty(idx)"
                                                class="px-2 h-full flex items-center justify-center text-text-primary hover:bg-surface-200 dark:hover:bg-surface-700 transition-colors rounded-l-lg font-black">-</button>
                                            <span
                                                class="px-3 text-xs font-black text-center border-x border-surface-200 dark:border-surface-700 h-full flex items-center bg-white dark:bg-surface-800">
                                                {{ item.quantity }}<span
                                                    class="text-[10px] text-text-secondary ml-0.5">x</span>
                                            </span>
                                            <button @click="incrementBundleItemQty(idx)"
                                                :disabled="isItemFullyOccupied(item)"
                                                class="px-2 h-full flex items-center justify-center text-text-primary hover:bg-surface-200 dark:hover:bg-surface-700 disabled:opacity-30 disabled:cursor-not-allowed transition-colors rounded-r-lg font-black">+</button>
                                        </div>
                                        <div v-else
                                            class="h-9 px-3 flex items-center justify-center bg-surface-100 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 rounded-lg text-[10px] font-black uppercase tracking-widest text-text-secondary">
                                            1 Unit
                                        </div>

                                        <!-- Price Input -->
                                        <div class="relative flex-1">
                                            <span
                                                class="absolute left-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-text-secondary">Rp</span>
                                            <input type="text" :value="item.display_bundle_price"
                                                @input="e => handleBundleItemPriceInput(idx, e)"
                                                class="w-full bg-surface-50 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 rounded-lg pl-8 pr-3 py-2 text-xs font-black text-primary-600 outline-none focus:border-primary-500 transition-all h-9"
                                                :placeholder="formatNumber(item.selling_price || item.price || 0)" />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-6 border-t border-surface-200 dark:border-surface-700 mt-auto">
                                <label
                                    class="block text-xs font-black text-text-secondary uppercase tracking-widest mb-3">Harga
                                    Total Bundle</label>
                                <div class="relative mb-6">
                                    <span
                                        class="absolute left-4 top-1/2 -translate-y-1/2 text-text-secondary font-bold">Rp</span>
                                    <input :value="displayBundleTotalPrice" @input="handleBundlePriceInput" type="text"
                                        class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-5 py-4 bg-white dark:bg-surface-800 text-text-primary text-xl font-black focus:outline-none focus:border-primary-500 transition-all pl-12"
                                        placeholder="Tentukan harga..." />
                                </div>

                                <button @click="finishBundling"
                                    :disabled="bundleItems.length < 2 || bundleTotalPrice <= 0"
                                    class="w-full py-4 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white rounded-xl font-bold transition-all shadow-lg shadow-emerald-500/20 flex items-center justify-center gap-2">
                                    <CheckCircle :size="20" />
                                    Selesai
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: rgba(156, 163, 175, 0.2);
    border-radius: 9999px;
}

input[type="number"]::-webkit-inner-spin-button,
input[type="number"]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
</style>
