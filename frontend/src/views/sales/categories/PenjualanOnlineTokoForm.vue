<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import api from '../../../api/axios';
import { useAuthStore } from '../../../store/auth';
import { useInventoryStore } from '../../../store/inventory';
import { useToast } from '../../../composables/useToast';
import { formatCurrency } from '../../../utils/formatters';
import { compressImage } from '../../../utils/imageCompressor';
import { getProvinces, getCitiesByProvince } from '../../../utils/indonesiaRegions';
import {
    ArrowLeft,
    Plus,
    Minus,
    Search,
    Trash2,
    Camera,
    UploadCloud,
    X,
    CheckCircle2,
    AlertCircle,
    Loader2,
    Package,
    Smartphone,
    User,
    Phone,
    MapPin,
    CreditCard,
    FileText,
    Image as ImageIcon,
    Check
} from 'lucide-vue-next';

const props = defineProps({
    availablePaymentMethods: {
        type: Array,
        default: () => []
    },
    salesAccount: {
        type: String,
        default: ''
    },
    selectedAccountObject: {
        type: Object,
        default: () => null
    },
    transactionCategory: {
        type: String,
        default: 'penjualan_online_toko'
    }
});

const emit = defineEmits(['back', 'transaction-complete', 'verify-pin']);

const authStore = useAuthStore();
const inventoryStore = useInventoryStore();
const toast = useToast();

// ----------------------------------------------------
// STATE
// ----------------------------------------------------
const isSubmitting = ref(false);
const isRestoring = ref(true);

// Selected Items List
// Item shape: { id, product_id, name, is_hp, imei, condition, ram, storage, price, selling_price, quantity, max_stock }
const selectedItems = ref([]);

// Item Picker Modal State
const showItemPicker = ref(false);
const itemSearchQuery = ref('');
const itemTypeFilter = ref('all'); // 'all', 'hp', 'non-hp'

// Customer & Shipping Form
const customerForm = ref({
    receiver: '',
    phone: '',
    province: '',
    city: '',
    address: ''
});

// Payment & Resi Form
const paymentForm = ref({
    payment_method_id: null,
    paid_amount: 0,
    tracking_no: '',
    notes: ''
});

// Photos State
const photos = ref({
    resi: null,
    resiPreview: null,
    payment: null,
    paymentPreview: null,
    unit: null,
    unitPreview: null
});

const compressingResi = ref(false);
const compressingPayment = ref(false);
const compressingUnit = ref(false);

// Duplicate Resi Check
const checkingResi = ref(false);
const resiDuplicate = ref(false);
const resiChecked = ref(false);

// Indonesia Provinces
const provincesList = getProvinces();
const citiesList = computed(() => {
    return customerForm.value.province ? getCitiesByProvince(customerForm.value.province) : [];
});

watch(() => customerForm.value.province, () => {
    // Reset city if not valid for selected province
    if (customerForm.value.province && !citiesList.value.includes(customerForm.value.city)) {
        customerForm.value.city = '';
    }
});

// ----------------------------------------------------
// CALCULATIONS
// ----------------------------------------------------
const totalBelanja = computed(() => {
    return selectedItems.value.reduce((sum, item) => {
        const qty = item.is_hp ? 1 : Math.max(1, Number(item.quantity) || 1);
        const price = Math.max(0, Number(item.selling_price) || 0);
        return sum + (price * qty);
    }, 0);
});

const totalItemsCount = computed(() => {
    return selectedItems.value.reduce((sum, item) => {
        return sum + (item.is_hp ? 1 : Math.max(1, Number(item.quantity) || 1));
    }, 0);
});

const nominalSelisih = computed(() => {
    const paid = Number(paymentForm.value.paid_amount) || 0;
    return paid - totalBelanja.value;
});

// ----------------------------------------------------
// VALIDATION
// ----------------------------------------------------
const validationErrors = computed(() => {
    const errors = [];
    if (selectedItems.value.length === 0) {
        errors.push('Pilih minimal 1 item produk');
    }
    const hasZeroPrice = selectedItems.value.some(it => (Number(it.selling_price) || 0) <= 0);
    if (hasZeroPrice) {
        errors.push('Tentukan harga jual untuk semua item');
    }
    if (!customerForm.value.receiver.trim()) {
        errors.push('Nama penerima wajib diisi');
    }
    if (!customerForm.value.province) {
        errors.push('Provinsi wajib dipilih');
    }
    if (!customerForm.value.city) {
        errors.push('Kota wajib dipilih');
    }
    if (!customerForm.value.address.trim()) {
        errors.push('Alamat pengiriman wajib diisi');
    }
    if (!paymentForm.value.payment_method_id) {
        errors.push('Metode pembayaran wajib dipilih');
    }
    if ((Number(paymentForm.value.paid_amount) || 0) <= 0) {
        errors.push('Nominal pembayaran wajib diisi');
    }
    if (!paymentForm.value.tracking_no.trim()) {
        errors.push('Nomor resi pesanan wajib diisi');
    }
    if (!photos.value.resi) {
        errors.push('Foto resi orderan wajib diunggah');
    }
    if (!photos.value.payment) {
        errors.push('Foto bukti pembayaran wajib diunggah');
    }
    if (!photos.value.unit) {
        errors.push('Foto unit pesanan wajib diunggah');
    }
    return errors;
});

const isFormValid = computed(() => validationErrors.value.length === 0 && !resiDuplicate.value);

// ----------------------------------------------------
// INVENTORY SEARCH & ITEM SELECTION
// ----------------------------------------------------
const availableInventory = computed(() => {
    let prods = inventoryStore.products || [];
    const q = itemSearchQuery.value.trim().toLowerCase();

    // Tab filtering
    if (itemTypeFilter.value === 'selected') {
        const selectedHpIds = new Set(selectedItems.value.filter(i => i.is_hp).map(i => i.id));
        const selectedNonHpIds = new Set(selectedItems.value.filter(i => !i.is_hp).map(i => i.product_id));
        prods = prods.filter(p => {
            const isHp = !p.is_non_hp;
            if (isHp) return selectedHpIds.has(p.id);
            return selectedNonHpIds.has(p.product_id || p.id);
        });
    } else if (itemTypeFilter.value === 'hp') {
        prods = prods.filter(p => !p.is_non_hp);
    } else if (itemTypeFilter.value === 'non-hp') {
        prods = prods.filter(p => p.is_non_hp);
    }

    // Search query filtering
    if (q) {
        prods = prods.filter(p => {
            const name = (p.product?.name || p.name || '').toLowerCase();
            const imei = (p.imei || '').toLowerCase();
            const brand = (p.product?.brand || p.brand || '').toLowerCase();
            return name.includes(q) || imei.includes(q) || brand.includes(q);
        });
    }

    return prods.slice(0, 100);
});

function openItemPicker() {
    itemSearchQuery.value = '';
    itemTypeFilter.value = 'all';
    showItemPicker.value = true;
    if (!inventoryStore.products || inventoryStore.products.length === 0) {
        inventoryStore.fetchInventory?.();
    }
}

function isProductSelected(prod) {
    if (!prod) return false;
    const isHp = !prod.is_non_hp;
    if (isHp) {
        return selectedItems.value.some(it => it.is_hp && it.id === prod.id);
    }
    const prodId = prod.product_id || prod.id;
    return selectedItems.value.some(it => !it.is_hp && it.product_id === prodId);
}

function getSelectedQty(prod) {
    if (!prod) return 0;
    const isHp = !prod.is_non_hp;
    if (isHp) {
        return selectedItems.value.some(it => it.is_hp && it.id === prod.id) ? 1 : 0;
    }
    const prodId = prod.product_id || prod.id;
    const item = selectedItems.value.find(it => !it.is_hp && it.product_id === prodId);
    return item ? (Number(item.quantity) || 1) : 0;
}

function toggleProductSelection(prod) {
    const isHp = !prod.is_non_hp;
    const basePrice = Number(prod.selling_price || prod.product?.selling_price || prod.price || prod.product?.price || 0);

    if (isHp) {
        const idx = selectedItems.value.findIndex(it => it.is_hp && it.id === prod.id);
        if (idx !== -1) {
            // Already selected, unselect
            selectedItems.value.splice(idx, 1);
        } else {
            // Add HP unit
            selectedItems.value.push({
                id: prod.id,
                product_id: prod.product_id || prod.id,
                name: prod.product?.name || prod.name || 'Unit HP',
                is_hp: true,
                imei: prod.imei || '-',
                condition: prod.condition || 'second',
                ram: prod.ram || '-',
                storage: prod.storage || '-',
                color: prod.color || '-',
                selling_price: basePrice,
                quantity: 1
            });
        }
    } else {
        // Non-HP product
        const prodId = prod.product_id || prod.id;
        const existing = selectedItems.value.find(it => !it.is_hp && it.product_id === prodId);
        if (existing) {
            existing.quantity = (Number(existing.quantity) || 1) + 1;
        } else {
            selectedItems.value.push({
                id: prod.id,
                product_id: prodId,
                name: prod.product?.name || prod.name || 'Produk Non-HP',
                is_hp: false,
                imei: null,
                condition: 'new',
                ram: null,
                storage: null,
                color: null,
                selling_price: basePrice,
                quantity: 1,
                max_stock: prod.stock || 999
            });
        }
    }
}

function updateProductQtyFromModal(prod, delta) {
    const isHp = !prod.is_non_hp;
    if (isHp) {
        if (delta < 0) {
            const idx = selectedItems.value.findIndex(it => it.is_hp && it.id === prod.id);
            if (idx !== -1) selectedItems.value.splice(idx, 1);
        }
        return;
    }

    const prodId = prod.product_id || prod.id;
    const idx = selectedItems.value.findIndex(it => !it.is_hp && it.product_id === prodId);
    if (idx === -1) {
        if (delta > 0) toggleProductSelection(prod);
        return;
    }

    const item = selectedItems.value[idx];
    const newQty = (Number(item.quantity) || 1) + delta;
    if (newQty <= 0) {
        selectedItems.value.splice(idx, 1);
    } else {
        item.quantity = newQty;
    }
}

function addItemToSale(product) {
    toggleProductSelection(product);
}

function closeItemPicker() {
    showItemPicker.value = false;
    if (selectedItems.value.length > 0) {
        toast.success(`${totalItemsCount.value} item dipilih`);
    }
}

function removeItem(index) {
    selectedItems.value.splice(index, 1);
}

function updateItemQuantity(index, delta) {
    const item = selectedItems.value[index];
    if (!item || item.is_hp) return;
    const newQty = (Number(item.quantity) || 1) + delta;
    if (newQty < 1) {
        removeItem(index);
    } else {
        item.quantity = newQty;
    }
}

function setPaidToTotal() {
    paymentForm.value.paid_amount = totalBelanja.value;
}

// ----------------------------------------------------
// PHOTO HANDLING WITH COMPRESSION
// ----------------------------------------------------
async function handlePhotoUpload(event, type) {
    const file = event.target.files?.[0];
    if (!file) return;

    if (type === 'resi') compressingResi.value = true;
    if (type === 'payment') compressingPayment.value = true;
    if (type === 'unit') compressingUnit.value = true;

    try {
        const compressed = await compressImage(file, { maxWidth: 1600, quality: 0.8 });
        const previewUrl = URL.createObjectURL(compressed);

        if (type === 'resi') {
            photos.value.resi = compressed;
            photos.value.resiPreview = previewUrl;
        } else if (type === 'payment') {
            photos.value.payment = compressed;
            photos.value.paymentPreview = previewUrl;
        } else if (type === 'unit') {
            photos.value.unit = compressed;
            photos.value.unitPreview = previewUrl;
        }
    } catch (e) {
        console.error('Compression error:', e);
        toast.error('Gagal mengompres gambar. Silakan gunakan foto lain.');
    } finally {
        if (type === 'resi') compressingResi.value = false;
        if (type === 'payment') compressingPayment.value = false;
        if (type === 'unit') compressingUnit.value = false;
        event.target.value = '';
    }
}

function removePhoto(type) {
    if (type === 'resi') {
        photos.value.resi = null;
        photos.value.resiPreview = null;
    } else if (type === 'payment') {
        photos.value.payment = null;
        photos.value.paymentPreview = null;
    } else if (type === 'unit') {
        photos.value.unit = null;
        photos.value.unitPreview = null;
    }
}

// ----------------------------------------------------
// RESI DUPLICATE CHECK
// ----------------------------------------------------
let resiDebounceTimer = null;
watch(() => paymentForm.value.tracking_no, (newVal) => {
    resiChecked.value = false;
    resiDuplicate.value = false;
    if (resiDebounceTimer) clearTimeout(resiDebounceTimer);

    const cleanResi = newVal.trim();
    if (!cleanResi || cleanResi.length < 5) return;

    resiDebounceTimer = setTimeout(async () => {
        checkingResi.value = true;
        try {
            const res = await api.get('/stock-outs/check-resi', {
                params: { resi: cleanResi }
            });
            resiDuplicate.value = !!res.data?.exists;
            resiChecked.value = true;
        } catch (e) {
            console.error('Check resi error:', e);
        } finally {
            checkingResi.value = false;
        }
    }, 600);
});

// ----------------------------------------------------
// PERSISTENCE (LOCAL STORAGE)
// ----------------------------------------------------
const storageKey = computed(() => {
    const userId = authStore.user?.id || 'guest';
    const acc = props.salesAccount ? `_acc_${props.salesAccount.replace(/\s+/g, '_')}` : '';
    return `temp_online_toko_form_${userId}${acc}`;
});

function saveDraft() {
    if (isRestoring.value) return;
    try {
        const payload = {
            selectedItems: selectedItems.value,
            customerForm: customerForm.value,
            paymentForm: paymentForm.value
        };
        localStorage.setItem(storageKey.value, JSON.stringify(payload));
    } catch (e) {
        console.error('Failed to save draft:', e);
    }
}

function restoreDraft() {
    try {
        const raw = localStorage.getItem(storageKey.value);
        if (raw) {
            const data = JSON.parse(raw);
            if (Array.isArray(data.selectedItems)) selectedItems.value = data.selectedItems;
            if (data.customerForm) customerForm.value = { ...customerForm.value, ...data.customerForm };
            if (data.paymentForm) paymentForm.value = { ...paymentForm.value, ...data.paymentForm };
        }
    } catch (e) {
        console.error('Failed to restore draft:', e);
    } finally {
        isRestoring.value = false;
    }
}

watch([selectedItems, customerForm, paymentForm], () => {
    saveDraft();
}, { deep: true });

// Filter out "Cash Toko" for online store sales (orders are shipped with tracking resi)
const filteredPaymentMethods = computed(() => {
    return (props.availablePaymentMethods || []).filter(pm => {
        const name = (pm.name || '').trim().toLowerCase();
        const code = (pm.code || '').trim().toLowerCase();
        return !name.includes('cash toko') && name !== 'cash' && code !== 'cash' && code !== 'cash_toko';
    });
});

// Watch payment methods to ensure payment_method_id is valid and not Cash Toko
watch(filteredPaymentMethods, (methods) => {
    if (methods && methods.length > 0) {
        const currentIsValid = methods.some(m => m.id === paymentForm.value.payment_method_id);
        if (!paymentForm.value.payment_method_id || !currentIsValid) {
            paymentForm.value.payment_method_id = methods[0].id;
        }
    } else {
        paymentForm.value.payment_method_id = null;
    }
}, { immediate: true });

onMounted(() => {
    restoreDraft();
    if (filteredPaymentMethods.value.length > 0) {
        const currentIsValid = filteredPaymentMethods.value.some(m => m.id === paymentForm.value.payment_method_id);
        if (!paymentForm.value.payment_method_id || !currentIsValid) {
            paymentForm.value.payment_method_id = filteredPaymentMethods.value[0].id;
        }
    }
});

// ----------------------------------------------------
// SUBMISSION
// ----------------------------------------------------
function handleInitiateSubmit() {
    if (!isFormValid.value) {
        toast.error(validationErrors.value[0] || 'Lengkapi semua data sebelum menyimpan transaksi');
        return;
    }

    emit('verify-pin', (pin) => executeSubmit(pin));
}

async function executeSubmit(pin = null) {
    if (isSubmitting.value) return;
    isSubmitting.value = true;

    try {
        const formData = new FormData();
        formData.append('category', 'penjualan_online_toko');
        formData.append('sales_account', props.salesAccount);

        if (props.selectedAccountObject?.id) {
            formData.append('inventory_user_id', props.selectedAccountObject.id);
        }
        if (props.selectedAccountObject?.branch_id) {
            formData.append('origin_branch_id', props.selectedAccountObject.branch_id);
        }

        if (pin && pin !== 'skipped') {
            formData.append('transaction_pin', pin);
        }

        // Receiver & Address
        const receiverName = customerForm.value.receiver.trim();
        const waNumber = customerForm.value.phone.trim();
        formData.append('customer_name', receiverName);
        formData.append('shopee_receiver', receiverName);
        formData.append('customer_wa', waNumber);
        formData.append('shopee_phone', waNumber);
        formData.append('shopee_province', customerForm.value.province);
        formData.append('shopee_city', customerForm.value.city);
        formData.append('shopee_address', customerForm.value.address.trim());

        // Tracking / Resi
        const cleanResi = paymentForm.value.tracking_no.trim();
        formData.append('expedition_tracking_no', cleanResi);
        formData.append('shopee_tracking_no', cleanResi);

        // Payment
        formData.append('payment_method_id', paymentForm.value.payment_method_id);
        formData.append('paid_amount', Number(paymentForm.value.paid_amount) || 0);
        formData.append('selling_price', totalBelanja.value);
        formData.append('notes', paymentForm.value.notes.trim());

        // Photos
        if (photos.value.resi) {
            formData.append('photo_resi', photos.value.resi);
            formData.append('proof_image', photos.value.resi);
        }
        if (photos.value.payment) {
            formData.append('payment_proof_image', photos.value.payment);
        }
        if (photos.value.unit) {
            formData.append('photo_unit', photos.value.unit);
        }

        // Items (HP & Non-HP)
        let nonHpIndex = 0;
        selectedItems.value.forEach(item => {
            const price = Math.max(0, Number(item.selling_price) || 0);
            if (item.is_hp) {
                formData.append('product_detail_ids[]', item.id);
                formData.append(`hp_items_meta[${item.id}][selling_price]`, price);
                formData.append(`hp_items_meta[${item.id}][item_discount]`, 0);
                formData.append(`hp_items_meta[${item.id}][distributed_discount]`, 0);
            } else {
                const qty = Math.max(1, Number(item.quantity) || 1);
                formData.append(`non_hp_items[${nonHpIndex}][product_id]`, item.product_id || item.id);
                formData.append(`non_hp_items[${nonHpIndex}][quantity]`, qty);
                formData.append(`non_hp_items[${nonHpIndex}][selling_price]`, price);
                formData.append(`non_hp_items[${nonHpIndex}][item_discount]`, 0);
                formData.append(`non_hp_items[${nonHpIndex}][distributed_discount]`, 0);
                nonHpIndex++;
            }
        });

        const res = await api.post('/stock-outs', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });

        // Clear local draft upon success
        localStorage.removeItem(storageKey.value);
        toast.success('Penjualan Online Toko berhasil disimpan! 🎉');
        emit('transaction-complete', res.data?.data || res.data);
    } catch (err) {
        console.error('Submit error:', err);
        const msg = err.response?.data?.message || 'Gagal menyimpan transaksi penjualan online toko.';
        toast.error(msg);
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <div class="flex-1 flex flex-col min-h-0 text-text-primary">
        <!-- Top Navigation Bar -->
        <div class="flex items-center justify-between gap-4 mb-6">
            <button type="button" @click="emit('back')"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-surface-100 hover:bg-surface-200 dark:bg-surface-800 dark:hover:bg-surface-700 text-text-secondary hover:text-text-primary text-xs sm:text-sm font-black uppercase tracking-wider transition-all">
                <ArrowLeft :size="18" stroke-width="2.5" />
                <span>Ganti Kategori</span>
            </button>

            <div class="flex items-center gap-2 text-xs font-bold text-text-secondary">
                <span class="inline-block w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Cabang: {{ selectedAccountObject?.branch?.name || authStore.user?.branch?.name || 'Aktif' }}</span>
                <span class="text-surface-400">•</span>
                <span>CS: {{ salesAccount || selectedAccountObject?.name || '-' }}</span>
            </div>
        </div>

        <!-- Main Responsive Layout (2 Columns on Desktop, 1 Column on Mobile) -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start pb-24">
            
            <!-- LEFT COLUMN: Items & Shipping (7 cols) -->
            <div class="lg:col-span-7 space-y-6">
                
                <!-- SECTION 1: Item & Penentuan Harga -->
                <div class="bg-white dark:bg-surface-800 rounded-2xl border border-surface-200 dark:border-surface-700 p-5 sm:p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3 mb-4 pb-3 border-b border-surface-100 dark:border-surface-700">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-primary-50 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400 flex items-center justify-center font-black">
                                <Package :size="20" stroke-width="2.5" />
                            </div>
                            <div>
                                <h3 class="text-base font-black text-text-primary uppercase tracking-tight">Pilih Item & Tentukan Harga</h3>
                                <p class="text-xs text-text-secondary">Pilih unit HP atau aksesoris dan tentukan harga jual per item</p>
                            </div>
                        </div>

                        <button type="button" @click="openItemPicker"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-primary-600 hover:bg-primary-500 text-white text-xs font-black uppercase tracking-wider transition-all shadow-md shadow-primary-500/20 active:scale-95">
                            <Plus :size="16" stroke-width="3" />
                            <span>Pilih Item</span>
                        </button>
                    </div>

                    <!-- Selected Items List -->
                    <div v-if="selectedItems.length === 0" class="py-12 px-4 text-center border-2 border-dashed border-surface-200 dark:border-surface-700 rounded-xl bg-surface-50 dark:bg-surface-900/50">
                        <Package :size="40" class="mx-auto text-surface-400 dark:text-surface-500 mb-3" />
                        <p class="text-sm font-bold text-text-primary">Belum ada item yang dipilih</p>
                        <p class="text-xs text-text-secondary mt-1 max-w-sm mx-auto">
                            Klik tombol <strong>"Pilih Item"</strong> di atas untuk menambahkan barang dari stok inventori cabang ini.
                        </p>
                    </div>

                    <div v-else class="space-y-3">
                        <div v-for="(item, idx) in selectedItems" :key="item.is_hp ? item.id : `${item.product_id}_${idx}`"
                            class="p-4 rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-900/60 flex flex-col sm:flex-row sm:items-center justify-between gap-4 transition-all">
                            
                            <!-- Item Info -->
                            <div class="flex items-start gap-3 flex-1 min-w-0">
                                <div class="w-9 h-9 rounded-lg bg-surface-200 dark:bg-surface-800 flex items-center justify-center shrink-0 mt-0.5 text-text-secondary">
                                    <Smartphone v-if="item.is_hp" :size="18" />
                                    <Package v-else :size="18" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h4 class="text-sm font-black text-text-primary truncate">{{ item.name }}</h4>
                                        <span v-if="item.is_hp" class="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-primary-500/10 text-primary-600 dark:text-primary-400 border border-primary-500/20">
                                            HP • {{ item.condition }}
                                        </span>
                                        <span v-else class="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                            Non-HP
                                        </span>
                                    </div>
                                    <p v-if="item.is_hp" class="text-xs text-text-secondary mt-0.5 font-mono">
                                        IMEI: {{ item.imei }} <span v-if="item.ram && item.ram !== '-'">• {{ item.ram }}/{{ item.storage }}</span>
                                    </p>
                                </div>
                            </div>

                            <!-- Quantity & Editable Price -->
                            <div class="flex items-center gap-3 sm:gap-4 shrink-0 justify-between sm:justify-end">
                                <!-- Quantity for Non-HP -->
                                <div v-if="!item.is_hp" class="flex items-center gap-1.5 bg-white dark:bg-surface-800 border border-surface-200 dark:border-surface-700 rounded-lg p-1">
                                    <button type="button" @click="updateItemQuantity(idx, -1)" class="w-7 h-7 flex items-center justify-center rounded hover:bg-surface-100 dark:hover:bg-surface-700 text-text-secondary font-black text-sm">
                                        -
                                    </button>
                                    <span class="w-8 text-center text-xs font-black text-text-primary">{{ item.quantity }}</span>
                                    <button type="button" @click="updateItemQuantity(idx, 1)" class="w-7 h-7 flex items-center justify-center rounded hover:bg-surface-100 dark:hover:bg-surface-700 text-text-secondary font-black text-sm">
                                        +
                                    </button>
                                </div>

                                <!-- Editable Price Input -->
                                <div class="flex flex-col items-end">
                                    <label class="text-[10px] font-bold text-text-secondary uppercase mb-0.5">Harga Jual (Rp)</label>
                                    <input v-model.number="item.selling_price" type="number" min="0" step="1000"
                                        class="w-32 sm:w-36 px-3 py-1.5 text-xs font-black text-right rounded-lg bg-white dark:bg-surface-800 border border-surface-200 dark:border-surface-700 text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500" />
                                </div>

                                <!-- Remove Button -->
                                <button type="button" @click="removeItem(idx)"
                                    class="p-2 text-rose-500 hover:bg-rose-500/10 rounded-lg transition-colors"
                                    title="Hapus Item">
                                    <Trash2 :size="16" />
                                </button>
                            </div>
                        </div>

                        <!-- Subtotal Bar -->
                        <div class="flex items-center justify-between p-4 bg-primary-500/10 dark:bg-primary-900/20 border border-primary-500/20 rounded-xl mt-4">
                            <span class="text-xs font-black text-text-secondary uppercase tracking-wider">Subtotal Item ({{ totalItemsCount }})</span>
                            <span class="text-base font-black text-primary-600 dark:text-primary-400">{{ formatCurrency(totalBelanja) }}</span>
                        </div>
                    </div>
                </div>

                <!-- SECTION 2: Data Penerima & Pengiriman -->
                <div class="bg-white dark:bg-surface-800 rounded-2xl border border-surface-200 dark:border-surface-700 p-5 sm:p-6 shadow-sm">
                    <div class="flex items-center gap-3 mb-4 pb-3 border-b border-surface-100 dark:border-surface-700">
                        <div class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center font-black">
                            <MapPin :size="20" stroke-width="2.5" />
                        </div>
                        <div>
                            <h3 class="text-base font-black text-text-primary uppercase tracking-tight">Data Penerima & Pengiriman</h3>
                            <p class="text-xs text-text-secondary">Identitas customer dan tujuan pengiriman paket</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <!-- Penerima & No WA -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-1.5">
                                    Nama Penerima <span class="text-rose-500">*</span>
                                </label>
                                <div class="relative">
                                    <input v-model="customerForm.receiver" type="text" placeholder="Contoh: Budi Santoso"
                                        class="w-full pl-10 pr-4 py-2.5 text-xs sm:text-sm rounded-xl bg-surface-50 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 text-text-primary placeholder:text-surface-400 dark:placeholder:text-surface-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500 transition-all font-medium" />
                                    <User :size="16" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-text-secondary" />
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-1.5">
                                    No WhatsApp Customer
                                </label>
                                <div class="relative">
                                    <input v-model="customerForm.phone" type="tel" placeholder="Contoh: 08123456789"
                                        class="w-full pl-10 pr-4 py-2.5 text-xs sm:text-sm rounded-xl bg-surface-50 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 text-text-primary placeholder:text-surface-400 dark:placeholder:text-surface-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500 transition-all font-mono font-medium" />
                                    <Phone :size="16" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-text-secondary" />
                                </div>
                            </div>
                        </div>

                        <!-- Provinsi & Kota Dropdowns -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-1.5">
                                    Provinsi <span class="text-rose-500">*</span>
                                </label>
                                <select v-model="customerForm.province"
                                    class="w-full px-4 py-2.5 text-xs sm:text-sm rounded-xl bg-surface-50 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500 transition-all cursor-pointer font-medium">
                                    <option value="" disabled class="bg-white dark:bg-surface-900 text-text-primary">-- Pilih Provinsi --</option>
                                    <option v-for="prov in provincesList" :key="prov" :value="prov" class="bg-white dark:bg-surface-900 text-text-primary">{{ prov }}</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-1.5">
                                    Kota / Kabupaten <span class="text-rose-500">*</span>
                                </label>
                                <select v-model="customerForm.city" :disabled="!customerForm.province"
                                    class="w-full px-4 py-2.5 text-xs sm:text-sm rounded-xl bg-surface-50 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500 transition-all cursor-pointer font-medium disabled:opacity-50 disabled:cursor-not-allowed">
                                    <option value="" disabled class="bg-white dark:bg-surface-900 text-text-primary">{{ customerForm.province ? '-- Pilih Kota/Kabupaten --' : 'Pilih Provinsi terlebih dahulu' }}</option>
                                    <option v-for="cty in citiesList" :key="cty" :value="cty" class="bg-white dark:bg-surface-900 text-text-primary">{{ cty }}</option>
                                </select>
                            </div>
                        </div>

                        <!-- Alamat Lengkap -->
                        <div>
                            <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-1.5">
                                Alamat Lengkap <span class="text-rose-500">*</span>
                            </label>
                            <textarea v-model="customerForm.address" rows="3" placeholder="Nama Jalan, No. Rumah, RT/RW, Kelurahan, Kecamatan, Kode Pos..."
                                class="w-full px-4 py-2.5 text-xs sm:text-sm rounded-xl bg-surface-50 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 text-text-primary placeholder:text-surface-400 dark:placeholder:text-surface-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500 transition-all font-medium"></textarea>
                        </div>
                    </div>
                </div>

            </div>

            <!-- RIGHT COLUMN: Payment, Resi & Photos (5 cols) -->
            <div class="lg:col-span-5 space-y-6">
                
                <!-- SECTION 3: Pembayaran & Resi -->
                <div class="bg-white dark:bg-surface-800 rounded-2xl border border-surface-200 dark:border-surface-700 p-5 sm:p-6 shadow-sm">
                    <div class="flex items-center gap-3 mb-4 pb-3 border-b border-surface-100 dark:border-surface-700">
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-black">
                            <CreditCard :size="20" stroke-width="2.5" />
                        </div>
                        <div>
                            <h3 class="text-base font-black text-text-primary uppercase tracking-tight">Pembayaran & Resi</h3>
                            <p class="text-xs text-text-secondary">Metode bayar, nominal dan nomor resi kurir</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <!-- Metode Pembayaran (Cash Toko Excluded) -->
                        <div>
                            <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-1.5">
                                Metode Pembayaran <span class="text-rose-500">*</span>
                            </label>
                            <select v-model="paymentForm.payment_method_id"
                                class="w-full px-4 py-2.5 text-xs sm:text-sm rounded-xl bg-surface-50 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500 transition-all cursor-pointer font-bold uppercase">
                                <option :value="null" disabled class="bg-white dark:bg-surface-900 text-text-primary">-- Pilih Metode Pembayaran --</option>
                                <option v-for="pm in filteredPaymentMethods" :key="pm.id" :value="pm.id" class="bg-white dark:bg-surface-900 text-text-primary">
                                    {{ pm.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Nominal Pembayaran -->
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="text-xs font-bold text-text-secondary uppercase tracking-widest">
                                    Nominal Bayar (Rp) <span class="text-rose-500">*</span>
                                </label>
                                <button type="button" @click="setPaidToTotal"
                                    class="text-[11px] font-bold text-primary-600 dark:text-primary-400 hover:underline">
                                    Sesuai Total Belanja
                                </button>
                            </div>
                            <input v-model.number="paymentForm.paid_amount" type="number" min="0" step="1000"
                                class="w-full px-4 py-2.5 text-sm sm:text-base font-black rounded-xl bg-surface-50 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 text-text-primary placeholder:text-surface-400 dark:placeholder:text-surface-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500 transition-all font-mono" />
                            
                            <!-- Selisih Nominal Feedback -->
                            <div v-if="nominalSelisih !== 0 && totalBelanja > 0" class="mt-2 text-xs font-bold">
                                <span v-if="nominalSelisih > 0" class="text-emerald-500">
                                    Kembalian: {{ formatCurrency(nominalSelisih) }}
                                </span>
                                <span v-else class="text-amber-500">
                                    Kurang Bayar: {{ formatCurrency(Math.abs(nominalSelisih)) }}
                                </span>
                            </div>
                        </div>

                        <!-- No Resi Pesanan -->
                        <div>
                            <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-1.5">
                                No Resi Pesanan <span class="text-rose-500">*</span>
                            </label>
                            <div class="relative">
                                <input v-model="paymentForm.tracking_no" type="text" placeholder="Contoh: JNE123456789 / SPXID0123"
                                    class="w-full px-4 py-2.5 pr-10 text-xs sm:text-sm font-mono font-bold rounded-xl bg-surface-50 dark:bg-surface-900 border text-text-primary placeholder:text-surface-400 dark:placeholder:text-surface-500 focus:outline-none focus:ring-2 transition-all uppercase"
                                    :class="resiDuplicate ? 'border-rose-500 focus:ring-rose-500/30' : 'border-surface-200 dark:border-surface-700 focus:ring-primary-500/30 focus:border-primary-500'" />
                                
                                <div class="absolute right-3 top-1/2 -translate-y-1/2">
                                    <Loader2 v-if="checkingResi" :size="16" class="animate-spin text-primary-500" />
                                    <AlertCircle v-else-if="resiDuplicate" :size="16" class="text-rose-500" />
                                    <CheckCircle2 v-else-if="resiChecked && !resiDuplicate" :size="16" class="text-emerald-500" />
                                </div>
                            </div>
                            <p v-if="resiDuplicate" class="text-xs text-rose-500 font-bold mt-1">
                                Nomor resi ini sudah pernah digunakan pada transaksi lain!
                            </p>
                        </div>

                        <!-- Catatan -->
                        <div>
                            <label class="block text-xs font-bold text-text-secondary uppercase tracking-widest mb-1.5">
                                Catatan Transaksi
                            </label>
                            <textarea v-model="paymentForm.notes" rows="2" placeholder="Catatan tambahan (opsional)..."
                                class="w-full px-4 py-2 text-xs rounded-xl bg-surface-50 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 text-text-primary placeholder:text-surface-400 dark:placeholder:text-surface-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500 transition-all font-medium"></textarea>
                        </div>
                    </div>
                </div>

                <!-- SECTION 4: Foto Bukti (Resi, Pembayaran, Unit) -->
                <div class="bg-white dark:bg-surface-800 rounded-2xl border border-surface-200 dark:border-surface-700 p-5 sm:p-6 shadow-sm">
                    <div class="flex items-center gap-3 mb-4 pb-3 border-b border-surface-100 dark:border-surface-700">
                        <div class="w-10 h-10 rounded-xl bg-violet-50 dark:bg-violet-900/30 text-violet-600 dark:text-violet-400 flex items-center justify-center font-black">
                            <ImageIcon :size="20" stroke-width="2.5" />
                        </div>
                        <div>
                            <h3 class="text-base font-black text-text-primary uppercase tracking-tight">Foto Bukti Transaksi</h3>
                            <p class="text-xs text-text-secondary">Wajib lampirkan ketiga foto berikut</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <!-- 1. Foto Resi -->
                        <div class="flex flex-col items-center">
                            <label class="text-[11px] font-bold text-text-secondary uppercase tracking-wider mb-2 text-center">
                                Foto Resi <span class="text-rose-500">*</span>
                            </label>

                            <div v-if="photos.resiPreview" class="relative w-full aspect-square rounded-xl overflow-hidden border border-surface-200 dark:border-surface-700 group bg-surface-100 dark:bg-surface-900">
                                <img :src="photos.resiPreview" class="w-full h-full object-cover" alt="Foto Resi" />
                                <button type="button" @click="removePhoto('resi')"
                                    class="absolute top-2 right-2 p-1.5 rounded-lg bg-black/60 text-white hover:bg-rose-600 transition-colors">
                                    <X :size="14" />
                                </button>
                            </div>

                            <label v-else class="w-full aspect-square rounded-xl border-2 border-dashed border-surface-200 dark:border-surface-700 hover:border-primary-500 bg-surface-50 dark:bg-surface-900/50 flex flex-col items-center justify-center cursor-pointer transition-all p-3 text-center">
                                <input type="file" accept="image/*" class="hidden" @change="handlePhotoUpload($event, 'resi')" />
                                <Loader2 v-if="compressingResi" :size="20" class="animate-spin text-primary-500 mb-1" />
                                <Camera v-else :size="24" class="text-surface-400 dark:text-surface-500 mb-1.5" />
                                <span class="text-[11px] font-bold text-text-secondary">{{ compressingResi ? 'Mengompres...' : 'Unggah Resi' }}</span>
                            </label>
                        </div>

                        <!-- 2. Foto Pembayaran -->
                        <div class="flex flex-col items-center">
                            <label class="text-[11px] font-bold text-text-secondary uppercase tracking-wider mb-2 text-center">
                                Foto Pembayaran <span class="text-rose-500">*</span>
                            </label>

                            <div v-if="photos.paymentPreview" class="relative w-full aspect-square rounded-xl overflow-hidden border border-surface-200 dark:border-surface-700 group bg-surface-100 dark:bg-surface-900">
                                <img :src="photos.paymentPreview" class="w-full h-full object-cover" alt="Foto Pembayaran" />
                                <button type="button" @click="removePhoto('payment')"
                                    class="absolute top-2 right-2 p-1.5 rounded-lg bg-black/60 text-white hover:bg-rose-600 transition-colors">
                                    <X :size="14" />
                                </button>
                            </div>

                            <label v-else class="w-full aspect-square rounded-xl border-2 border-dashed border-surface-200 dark:border-surface-700 hover:border-primary-500 bg-surface-50 dark:bg-surface-900/50 flex flex-col items-center justify-center cursor-pointer transition-all p-3 text-center">
                                <input type="file" accept="image/*" class="hidden" @change="handlePhotoUpload($event, 'payment')" />
                                <Loader2 v-if="compressingPayment" :size="20" class="animate-spin text-primary-500 mb-1" />
                                <Camera v-else :size="24" class="text-surface-400 dark:text-surface-500 mb-1.5" />
                                <span class="text-[11px] font-bold text-text-secondary">{{ compressingPayment ? 'Mengompres...' : 'Bukti Bayar' }}</span>
                            </label>
                        </div>

                        <!-- 3. Foto Unit -->
                        <div class="flex flex-col items-center">
                            <label class="text-[11px] font-bold text-text-secondary uppercase tracking-wider mb-2 text-center">
                                Foto Unit <span class="text-rose-500">*</span>
                            </label>

                            <div v-if="photos.unitPreview" class="relative w-full aspect-square rounded-xl overflow-hidden border border-surface-200 dark:border-surface-700 group bg-surface-100 dark:bg-surface-900">
                                <img :src="photos.unitPreview" class="w-full h-full object-cover" alt="Foto Unit" />
                                <button type="button" @click="removePhoto('unit')"
                                    class="absolute top-2 right-2 p-1.5 rounded-lg bg-black/60 text-white hover:bg-rose-600 transition-colors">
                                    <X :size="14" />
                                </button>
                            </div>

                            <label v-else class="w-full aspect-square rounded-xl border-2 border-dashed border-surface-200 dark:border-surface-700 hover:border-primary-500 bg-surface-50 dark:bg-surface-900/50 flex flex-col items-center justify-center cursor-pointer transition-all p-3 text-center">
                                <input type="file" accept="image/*" class="hidden" @change="handlePhotoUpload($event, 'unit')" />
                                <Loader2 v-if="compressingUnit" :size="20" class="animate-spin text-primary-500 mb-1" />
                                <Camera v-else :size="24" class="text-surface-400 dark:text-surface-500 mb-1.5" />
                                <span class="text-[11px] font-bold text-text-secondary">{{ compressingUnit ? 'Mengompres...' : 'Foto Unit' }}</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- SUBMIT BUTTON & SUMMARY CARD -->
                <div class="bg-white dark:bg-surface-800 rounded-2xl border border-surface-200 dark:border-surface-700 p-5 shadow-sm space-y-4">
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-bold text-text-secondary">Total Tagihan</span>
                        <span class="font-black text-lg text-text-primary">{{ formatCurrency(totalBelanja) }}</span>
                    </div>

                    <!-- Incomplete fields prompt if not valid -->
                    <div v-if="!isFormValid && validationErrors.length > 0" class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-[11px] text-amber-600 dark:text-amber-400 font-bold space-y-1">
                        <p class="font-black">Lengkapi data berikut:</p>
                        <ul class="list-disc list-inside space-y-0.5">
                            <li v-for="err in validationErrors.slice(0, 3)" :key="err">{{ err }}</li>
                            <li v-if="validationErrors.length > 3">+ {{ validationErrors.length - 3 }} lainnya</li>
                        </ul>
                    </div>

                    <button type="button" @click="handleInitiateSubmit" :disabled="!isFormValid || isSubmitting"
                        class="w-full h-14 bg-primary-600 hover:bg-primary-500 disabled:opacity-50 text-white rounded-xl font-black text-sm uppercase tracking-wider transition-all shadow-lg shadow-primary-500/25 flex items-center justify-center gap-2 active:scale-95 disabled:cursor-not-allowed">
                        <Loader2 v-if="isSubmitting" :size="18" class="animate-spin" />
                        <Check v-else :size="18" stroke-width="3" />
                        <span>{{ isSubmitting ? 'Menyimpan Transaksi...' : 'Simpan Penjualan Online' }}</span>
                    </button>
                </div>

            </div>

        </div>

        <!-- ITEM PICKER MODAL -->
        <div v-if="showItemPicker" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4">
            <div class="absolute inset-0 bg-black/75 backdrop-blur-sm" @click="showItemPicker = false"></div>

            <div class="relative bg-white dark:bg-surface-800 rounded-2xl border border-surface-200 dark:border-surface-700 w-full max-w-2xl max-h-[92vh] sm:max-h-[85vh] flex flex-col shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-200">
                <!-- Modal Header -->
                <div class="px-5 py-4 border-b border-surface-200 dark:border-surface-700 flex items-center justify-between bg-surface-50 dark:bg-surface-900/60 shrink-0">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="text-sm sm:text-base font-black text-text-primary dark:text-white">Pilih Produk dari Inventori</h3>
                                <span v-if="selectedItems.length > 0" class="px-2 py-0.5 rounded-full text-[10px] font-black bg-primary-500/10 dark:bg-primary-900/40 text-primary-600 dark:text-primary-400 border border-primary-500/20 dark:border-primary-500/30">
                                    {{ totalItemsCount }} Terpilih
                                </span>
                            </div>
                            <p class="text-xs text-text-secondary dark:text-neutral-400 mt-0.5">Pilih satu atau beberapa unit HP dan aksesoris sekaligus</p>
                        </div>
                    </div>
                    <button type="button" @click="showItemPicker = false" class="p-2 text-text-secondary hover:text-text-primary dark:hover:text-white rounded-lg shrink-0 transition-colors">
                        <X :size="18" />
                    </button>
                </div>

                <!-- Modal Filter & Search -->
                <div class="p-3 sm:p-4 border-b border-surface-200 dark:border-surface-700 space-y-3 bg-surface-50/50 dark:bg-surface-900/40 shrink-0">
                    <div class="relative">
                        <input v-model="itemSearchQuery" type="text" placeholder="Cari nama produk, IMEI, atau merek..."
                            class="w-full pl-10 pr-4 py-2.5 text-xs sm:text-sm rounded-xl bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-700 text-text-primary dark:text-white placeholder:text-surface-500 dark:placeholder:text-neutral-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500 transition-all font-medium" />
                        <Search :size="16" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-text-secondary dark:text-neutral-400" />
                    </div>

                    <div class="flex items-center gap-1.5 sm:gap-2 overflow-x-auto pb-1 sm:pb-0 scrollbar-none">
                        <button type="button" @click="itemTypeFilter = 'all'"
                            class="px-3 py-1.5 rounded-lg text-xs font-black uppercase tracking-wider transition-all whitespace-nowrap"
                            :class="itemTypeFilter === 'all' ? 'bg-primary-600 text-white shadow-sm' : 'bg-surface-200 dark:bg-surface-700 text-text-secondary dark:text-neutral-300 hover:text-text-primary dark:hover:text-white'">
                            Semua
                        </button>
                        <button type="button" @click="itemTypeFilter = 'hp'"
                            class="px-3 py-1.5 rounded-lg text-xs font-black uppercase tracking-wider transition-all whitespace-nowrap"
                            :class="itemTypeFilter === 'hp' ? 'bg-primary-600 text-white shadow-sm' : 'bg-surface-200 dark:bg-surface-700 text-text-secondary dark:text-neutral-300 hover:text-text-primary dark:hover:text-white'">
                            HP
                        </button>
                        <button type="button" @click="itemTypeFilter = 'non-hp'"
                            class="px-3 py-1.5 rounded-lg text-xs font-black uppercase tracking-wider transition-all whitespace-nowrap"
                            :class="itemTypeFilter === 'non-hp' ? 'bg-primary-600 text-white shadow-sm' : 'bg-surface-200 dark:bg-surface-700 text-text-secondary dark:text-neutral-300 hover:text-text-primary dark:hover:text-white'">
                            Non-HP
                        </button>
                        <button v-if="selectedItems.length > 0" type="button" @click="itemTypeFilter = 'selected'"
                            class="px-3 py-1.5 rounded-lg text-xs font-black uppercase tracking-wider transition-all whitespace-nowrap flex items-center gap-1.5"
                            :class="itemTypeFilter === 'selected' ? 'bg-primary-600 text-white shadow-sm' : 'bg-primary-500/10 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400 border border-primary-500/30'">
                            <Check :size="13" stroke-width="3" />
                            <span>Terpilih ({{ totalItemsCount }})</span>
                        </button>
                    </div>
                </div>

                <!-- Products List -->
                <div class="p-3 sm:p-4 overflow-y-auto max-h-[50vh] sm:max-h-[52vh] space-y-2 custom-scrollbar flex-1 min-h-[220px]">
                    <div v-if="availableInventory.length === 0" class="py-12 text-center text-text-secondary dark:text-neutral-400 text-xs">
                        <Package :size="32" class="mx-auto text-surface-400 dark:text-surface-500 mb-2 opacity-50" />
                        <p class="font-bold text-text-primary dark:text-white">Tidak ada barang yang ditemukan.</p>
                        <p v-if="itemTypeFilter === 'selected'" class="text-[11px] mt-1 text-text-secondary dark:text-neutral-400">Belum ada item yang dipilih</p>
                        <p v-else-if="itemSearchQuery" class="text-[11px] mt-1 text-text-secondary dark:text-neutral-400">Coba ubah kata kunci pencarian</p>
                    </div>

                    <div v-for="prod in availableInventory" :key="prod.id"
                        @click="toggleProductSelection(prod)"
                        class="w-full p-3 sm:p-3.5 rounded-xl border transition-all cursor-pointer flex items-center justify-between gap-3 select-none group"
                        :class="isProductSelected(prod)
                            ? 'border-primary-500 dark:border-primary-500 ring-2 ring-primary-500/20 bg-primary-50/70 dark:bg-primary-900/30 shadow-sm'
                            : 'border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-900 hover:border-primary-500/40 dark:hover:border-surface-600'">
                        
                        <div class="flex items-center gap-3 min-w-0 flex-1">
                            <!-- Left Icon -->
                            <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-lg flex items-center justify-center shrink-0 transition-colors"
                                :class="isProductSelected(prod)
                                    ? 'bg-primary-600 text-white shadow-xs'
                                    : 'bg-surface-100 dark:bg-surface-800 text-text-secondary dark:text-neutral-400 group-hover:text-primary-500'">
                                <Smartphone v-if="!prod.is_non_hp" :size="18" />
                                <Package v-else :size="18" />
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5 sm:gap-2 flex-wrap">
                                    <h4 class="text-xs sm:text-sm font-black text-text-primary dark:text-white truncate">
                                        {{ prod.product?.name || prod.name }}
                                    </h4>
                                    <span v-if="!prod.is_non_hp" class="px-1.5 py-0.5 rounded text-[9px] font-black uppercase bg-surface-100 dark:bg-surface-800 text-text-secondary dark:text-neutral-300 border border-surface-200 dark:border-surface-700">
                                        {{ prod.condition || 'second' }}
                                    </span>
                                    <span v-if="isProductSelected(prod) && !prod.is_non_hp" class="px-1.5 py-0.5 rounded text-[9px] font-black uppercase bg-primary-600 text-white shadow-xs">
                                        Terpilih
                                    </span>
                                </div>
                                <p v-if="!prod.is_non_hp" class="text-[11px] font-mono text-text-secondary dark:text-neutral-400 mt-0.5 truncate">
                                    IMEI: {{ prod.imei }} <span v-if="prod.ram && prod.ram !== '-'">• {{ prod.ram }}/{{ prod.storage }}</span>
                                </p>
                                <p v-else class="text-[11px] text-text-secondary dark:text-neutral-400 mt-0.5">
                                    Stok: {{ prod.stock || 1 }} unit
                                </p>
                            </div>
                        </div>

                        <!-- Right Actions: Price & Stepper/Check -->
                        <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                            <div class="text-right">
                                <span class="text-xs sm:text-sm font-black text-text-primary dark:text-white block font-mono">
                                    {{ formatCurrency(prod.selling_price || prod.product?.selling_price || prod.price || prod.product?.price || 0) }}
                                </span>
                            </div>

                            <!-- Non-HP Stepper when selected -->
                            <div v-if="prod.is_non_hp && isProductSelected(prod)" 
                                class="flex items-center gap-1 bg-white dark:bg-surface-800 border border-primary-500/50 rounded-lg p-0.5 shadow-sm"
                                @click.stop>
                                <button type="button" @click.stop="updateProductQtyFromModal(prod, -1)"
                                    class="w-6 h-6 flex items-center justify-center rounded hover:bg-surface-100 dark:hover:bg-surface-700 text-text-secondary dark:text-white hover:text-text-primary transition-colors">
                                    <Minus :size="12" stroke-width="2.5" />
                                </button>
                                <span class="w-6 text-center text-xs font-black text-primary-600 dark:text-primary-400 font-mono">
                                    {{ getSelectedQty(prod) }}
                                </span>
                                <button type="button" @click.stop="updateProductQtyFromModal(prod, 1)"
                                    class="w-6 h-6 flex items-center justify-center rounded hover:bg-surface-100 dark:hover:bg-surface-700 text-text-secondary dark:text-white hover:text-text-primary transition-colors">
                                    <Plus :size="12" stroke-width="2.5" />
                                </button>
                            </div>

                            <!-- Indicator box (HP or unselected non-hp) -->
                            <div v-else class="w-7 h-7 rounded-lg flex items-center justify-center transition-all"
                                :class="isProductSelected(prod)
                                    ? 'bg-primary-600 text-white shadow-sm'
                                    : 'border border-surface-200 dark:border-surface-700 text-surface-400 dark:text-neutral-500 group-hover:border-primary-500 group-hover:text-primary-500'">
                                <Check v-if="isProductSelected(prod)" :size="14" stroke-width="3" />
                                <Plus v-else :size="14" stroke-width="2.5" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="px-4 sm:px-6 py-3.5 border-t border-surface-200 dark:border-surface-700 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 bg-surface-50 dark:bg-surface-900 backdrop-blur-sm shrink-0">
                    <div class="flex items-center justify-between sm:justify-start gap-3">
                        <div class="flex flex-col">
                            <span class="text-[11px] font-bold text-text-secondary dark:text-neutral-400 uppercase tracking-wider">
                                {{ totalItemsCount }} Item Terpilih
                            </span>
                            <span class="text-sm font-black text-text-primary dark:text-white font-mono">
                                {{ formatCurrency(totalBelanja) }}
                            </span>
                        </div>
                        <button v-if="selectedItems.length > 0" type="button" @click="selectedItems = []"
                            class="text-[11px] font-bold text-rose-500 hover:text-rose-600 dark:hover:text-rose-400 hover:underline">
                            Reset Pilihan
                        </button>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="button" @click="showItemPicker = false"
                            class="flex-1 sm:flex-none px-4 py-2.5 rounded-xl border border-surface-200 dark:border-surface-700 text-xs font-black uppercase tracking-wider text-text-secondary dark:text-white hover:text-text-primary hover:bg-surface-100 dark:hover:bg-surface-800 transition-all">
                            Tutup
                        </button>
                        <button type="button" @click="closeItemPicker"
                            class="flex-1 sm:flex-none px-5 py-2.5 rounded-xl bg-primary-600 hover:bg-primary-500 text-white text-xs font-black uppercase tracking-wider transition-all shadow-md shadow-primary-500/20 active:scale-95 flex items-center justify-center gap-2">
                            <Check :size="16" stroke-width="3" />
                            <span>Selesai ({{ totalItemsCount }})</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
