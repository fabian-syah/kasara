<script setup>
import { ref, computed, onMounted, nextTick } from "vue";
import { useCartStore } from "../../../store/cart";
import { useAuthStore } from "../../../store/auth";
import api from "../../../api/axios";
import { formatCurrency } from "../../../utils/formatters";
import {
    User,
    ShoppingCart,
    AlertCircle,
    Plus,
    Trash2,
    ChevronDown,
    ArrowLeft,
    CheckCircle,
    Loader2,
    Upload,
    X,
    Camera
} from "lucide-vue-next";
import { compressImage } from "../../../utils/imageCompressor";

const props = defineProps({
    availablePaymentMethods: Array,
    transactionCategory: String,
    salesAccount: String,
    selectedAccountObject: Object,
    dpDeduction: { type: Number, default: 0 },
    parentDpId: { type: [Number, String], default: null }
});

const emit = defineEmits(["prev", "transaction-complete", "verify-pin"]);

const cartStore = useCartStore();
const authStore = useAuthStore();

const isSubmitting = ref(false);
const isCompressing = ref(false);
const showErrorModal = ref(false);
const errorModalMessage = ref("");

const isCompressingPayment = ref(false);

const customerForm = ref({
    customer_name: "",
    customer_phone: "",
    notes: "",
});

const splitPayments = ref([]);
const proofImage = ref(null);
const proofImagePreview = ref(null);
const paymentProofImage = ref(null);
const paymentProofImagePreview = ref(null);

const cartItems = computed(() => cartStore.items);
const cartTotal = computed(() => cartStore.total);

// For pelunasan_dp: effective total = cart total - DP already paid
const effectiveTotal = computed(() => {
    if (props.dpDeduction > 0) {
        return Math.max(0, cartTotal.value - props.dpDeduction);
    }
    return cartTotal.value;
});

const switchToPercentage = () => {
    if (cartStore.discountType === 'percentage') return;
    const total = cartStore.totalAfterItemDiscounts;
    if (total <= 0) {
        cartStore.setDiscount(0, 'percentage');
        return;
    }
    // Convert fixed amount to percentage (with proper rounding)
    const percentage = Math.min(100, Math.max(0, parseFloat((cartStore.discount / total * 100).toFixed(2))));
    cartStore.setDiscount(percentage, 'percentage');
    nextTick(() => {
        // Force input re-render
        const input = document.querySelector('input[placeholder="0"][class*="pl-14"]');
        if (input) input.dispatchEvent(new Event('input'));
    });
};

const switchToFixed = () => {
    if (cartStore.discountType === 'fixed') return;
    const total = cartStore.totalAfterItemDiscounts;
    if (total <= 0) {
        cartStore.setDiscount(0, 'fixed');
        return;
    }
    // Convert percentage to fixed amount (with proper rounding)
    const fixedAmount = Math.min(total, Math.max(0, parseFloat((cartStore.discount * total / 100).toFixed(0))));
    cartStore.setDiscount(fixedAmount, 'fixed');
    nextTick(() => {
        // Force input re-render
        const input = document.querySelector('input[placeholder="0"][class*="pl-14"]');
        if (input) input.dispatchEvent(new Event('input'));
    });
};

const missingFields = computed(() => {
    const fields = [];
    if (!customerForm.value.customer_name) fields.push("Nama Pelanggan");
    if (!customerForm.value.customer_phone) fields.push("WhatsApp Customer");
    if (!customerForm.value.notes) fields.push("Keterangan / Notes");
    if (!proofImage.value) fields.push("Foto Bukti (Nota)");
    if (!isCashOnly.value && !paymentProofImage.value) fields.push("Foto Bukti Pembayaran");

    if (props.transactionCategory === 'penjualan_store' && cartTotal.value <= 0) {
        fields.push("Total Belanja 0 (Kembali ke Step 3 untuk isi Harga)");
    }

    const totalPaid = splitPayments.value.reduce((sum, p) => sum + (parseFloat(p.amount) || 0), 0);
    if (totalPaid < effectiveTotal.value && props.transactionCategory !== 'dp') {
        fields.push("Pembayaran Kurang");
    }

    if (props.transactionCategory === 'dp' && totalPaid <= 0) {
        fields.push("DP tidak boleh 0");
    }

    return fields;
});

const isFormValid = computed(() => missingFields.value.length === 0);

const changeAmount = computed(() => {
    const totalPaid = splitPayments.value.reduce((sum, p) => sum + (parseFloat(p.amount) || 0), 0);
    return totalPaid - effectiveTotal.value;
});

const isCashPayment = computed(() => {
    return splitPayments.value.some(p => {
        const method = props.availablePaymentMethods.find(m => m.id === p.method_id);
        if (method) {
            const name = method.name.toLowerCase();
            return name.includes('cash') || name.includes('tunai');
        }
        return false;
    });
});

const isCashOnly = computed(() => {
    return splitPayments.value.every(p => {
        const method = props.availablePaymentMethods.find(m => m.id === p.method_id);
        if (method) {
            const name = method.name.toLowerCase();
            return name.includes('cash') || name.includes('tunai');
        }
        return false;
    });
});

const submitButtonText = computed(() => {
    if (isSubmitting.value) return "Memproses...";
    if (isCompressing.value) return "Mengompres...";
    return "Selesaikan Transaksi";
});

// Initialize split payment
onMounted(() => {
    if (splitPayments.value.length === 0) {
        addSplitPayment();
    }
});

// Helpers
function formatNumber(n) {
    if (!n) return "0";
    return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

function parseNumber(s) {
    if (!s) return 0;
    if (typeof s === 'number') return s;
    let clean = s.toString().replace(/Rp/g, "").replace(/\s/g, "");
    if (clean.endsWith('.00') || clean.endsWith(',00')) {
        clean = clean.slice(0, -3);
    }
    const finalClean = clean.replace(/[^0-9]/g, "");
    return parseInt(finalClean) || 0;
}

function addSplitPayment() {
    const totalPaidSoFar = splitPayments.value.reduce((sum, p) => sum + p.amount, 0);
    const remainingAmount = Math.max(0, effectiveTotal.value - totalPaidSoFar);

    splitPayments.value.push({
        method_id: props.availablePaymentMethods[0]?.id || null,
        amount: remainingAmount,
        display_amount: formatNumber(remainingAmount)
    });
}

function removeSplitPayment(index) {
    if (splitPayments.value.length > 1) {
        splitPayments.value.splice(index, 1);
    }
}

// No longer needed: handleSplitAmountInput replaced by v-money

// No longer needed: handleDiscountInput handled via computed or simplified logic

async function handleFileChange(e) {
    const file = e.target.files[0];
    if (!file) return;

    try {
        isCompressing.value = true;
        if (file.type.startsWith('image/')) {
            const compressed = await compressImage(file, {
                maxWidth: 1600,
                maxHeight: 1600,
                quality: 0.8
            });
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
        console.error("Compression failed:", err);
        alert("Gagal mengompres gambar. Silakan coba lagi.");
    } finally {
        isCompressing.value = false;
    }
}

async function handlePaymentFileChange(e) {
    const file = e.target.files[0];
    if (!file) return;

    try {
        isCompressingPayment.value = true;
        if (file.type.startsWith('image/')) {
            const compressed = await compressImage(file, {
                maxWidth: 1600,
                maxHeight: 1600,
                quality: 0.8
            });
            paymentProofImage.value = compressed;
            paymentProofImagePreview.value = URL.createObjectURL(compressed);
        } else {
            if (file.size > 10 * 1024 * 1024) {
                alert("Ukuran file maksimal 10MB");
                e.target.value = "";
                return;
            }
            paymentProofImage.value = file;
            paymentProofImagePreview.value = URL.createObjectURL(file);
        }
    } catch (err) {
        console.error("Compression failed:", err);
        alert("Gagal mengompres gambar pembayaran. Silakan coba lagi.");
    } finally {
        isCompressingPayment.value = false;
    }
}

const isClickLocked = ref(false);
async function handleSubmitOrder() {
    if (isClickLocked.value) return;
    isClickLocked.value = true;
    setTimeout(() => { isClickLocked.value = false; }, 1000);

    if (!isFormValid.value) {
        alert("Mohon lengkapi data: " + missingFields.value.join(", "));
        return;
    }

    if (props.selectedAccountObject) {
        emit('verify-pin', (pin) => processPayment(pin));
    } else {
        await processPayment();
    }
}

async function processPayment(pin = null) {
    if (isSubmitting.value) return;
    try {
        isSubmitting.value = true;
        const formData = new FormData();
        formData.append('category', props.transactionCategory);
        formData.append('sales_account', props.salesAccount);

        const totalPaid = splitPayments.value.reduce((sum, p) => sum + (parseFloat(p.amount) || 0), 0);
        formData.append('paid_amount', totalPaid);
        formData.append('selling_price', Number(cartStore.total || 0));

        // Pelunasan DP: send parent_dp_id and dp_amount
        if (props.parentDpId) {
            formData.append('parent_dp_id', props.parentDpId);
        }
        if (props.dpDeduction > 0) {
            formData.append('dp_deduction', props.dpDeduction);
        }

        formData.append('customer_name', customerForm.value.customer_name);
        formData.append('customer_wa', customerForm.value.customer_phone);
        if (props.selectedAccountObject?.id) {
            formData.append('inventory_user_id', props.selectedAccountObject.id);
        }
        if (pin) {
            formData.append('transaction_pin', pin);
        }

        formData.append('notes', customerForm.value.notes);

        let nonHpIndex = 0;
        cartItems.value.forEach(item => {
            const distributedDiscount = cartStore.getDistributedGlobalDiscount(item);

            if (item.is_bundle && item.bundle_items) {
                item.bundle_items.forEach(bi => {
                    if (bi.imei) {
                        formData.append('product_detail_ids[]', bi.id);
                        formData.append(`hp_items_meta[${bi.id}][selling_price]`, Number(bi.price || 0));
                        formData.append(`hp_items_meta[${bi.id}][item_discount]`, Number(bi.discount || 0));
                        formData.append(`hp_items_meta[${bi.id}][distributed_discount]`, 0);
                        formData.append(`hp_items_meta[${bi.id}][bundle_name]`, item.name);
                    } else {
                        formData.append(`non_hp_items[${nonHpIndex}][product_id]`, bi.product_id || bi.id);
                        formData.append(`non_hp_items[${nonHpIndex}][quantity]`, bi.quantity || 1);
                        formData.append(`non_hp_items[${nonHpIndex}][selling_price]`, Number(bi.price || 0));
                        formData.append(`non_hp_items[${nonHpIndex}][item_discount]`, Number(bi.discount || 0));
                        formData.append(`non_hp_items[${nonHpIndex}][distributed_discount]`, 0);
                        formData.append(`non_hp_items[${nonHpIndex}][bundle_name]`, item.name);
                        nonHpIndex++;
                    }
                });
            } else if (item.imei) {
                formData.append('product_detail_ids[]', item.id);
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

        formData.append('global_discount_value', Number(cartStore.discount || 0));
        formData.append('global_discount_type', cartStore.discountType);
        formData.append('total_discount', Number(cartStore.discountAmount + cartStore.itemDiscountTotal));

        formData.append('split_payments', JSON.stringify(splitPayments.value.map(p => ({
            payment_method_id: p.method_id,
            amount: p.amount
        }))));

        const firstBundle = cartItems.value.find(item => item.is_bundle);
        if (firstBundle) {
            formData.append('is_bundle', '1');
            formData.append('bundle_description', firstBundle.name);
        }

        if (proofImage.value) {
            formData.append('proof_image', proofImage.value);
        }

        if (paymentProofImage.value) {
            formData.append('payment_proof_image', paymentProofImage.value);
        }

        const response = await api.post('/stock-outs', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });

        // Calculate cash/transfer breakdown for Receipt
        let cashAmount = 0;
        let transferAmount = 0;
        splitPayments.value.forEach(p => {
            const method = props.availablePaymentMethods.find(m => m.id === p.method_id);
            if (method) {
                const name = method.name.toLowerCase();
                if (name.includes('cash') || name.includes('tunai')) {
                    cashAmount += Number(p.amount || 0);
                } else {
                    transferAmount += Number(p.amount || 0);
                }
            }
        });

        const detailedSplitPayments = splitPayments.value.map(p => {
            const method = props.availablePaymentMethods.find(m => m.id === p.method_id);
            return {
                method_name: method ? method.name : 'Unknown',
                amount: p.amount
            };
        });

        const firstMethod = props.availablePaymentMethods.find(m => m.id === splitPayments.value[0]?.method_id);
        const now = new Date();

        const lastTransaction = {
            id: response.data?.data?.id || response.data?.id,
            order_no: response.data?.data?.receipt_id || response.data?.receipt_id || "TRX-" + Date.now(),
            items: cartItems.value.map(item => ({
                ...item,
                brand: item.brand || item.product?.brand || item.product?.brandRelation?.name || null,
                name: item.name || item.product?.name,
                storage: item.storage || null,
                condition: item.condition || null,
                qty: item.quantity || 1,
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
            grand_total: cartTotal.value,
            paid: totalPaid,
            change: totalPaid - cartTotal.value,
            change_amount: totalPaid - cartTotal.value,
            split_payments_data: detailedSplitPayments,
            payment_method_name: firstMethod ? firstMethod.name : '-',
            category: props.transactionCategory,
            sales_account: props.salesAccount,
            sales_name: props.salesAccount,
            inventory_account_name: props.salesAccount,
            branch_name: props.selectedAccountObject?.branch?.name || authStore.user?.branch?.name || '',
            branch_timezone: props.selectedAccountObject?.branch?.timezone || authStore.user?.branch?.timezone || 'WIB',
            customer_name: customerForm.value.customer_name,
            customer_phone: customerForm.value.customer_phone,
            notes: customerForm.value.notes,
            created_at: now.toISOString(),
            date: now.toLocaleDateString("id-ID", {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            }),
            time: now.toLocaleTimeString("id-ID", { hour: '2-digit', minute: '2-digit' }),
            proof_images: [
                proofImagePreview.value,
                paymentProofImagePreview.value
            ].filter(Boolean)
        };

        emit('transaction-complete', lastTransaction);

        // Reset local state
        proofImage.value = null;
        proofImagePreview.value = null;
        customerForm.value = {
            customer_name: "",
            customer_phone: "",
            notes: "",
        };
        splitPayments.value = [];

    } catch (error) {
        console.error("Payment failed", error);
        let errorMsg = error.response?.data?.message || "Gagal memproses transaksi";
        if (error.response) {
            const status = error.response.status;
            errorMsg = `[Error ${status}] ${errorMsg}`;
            if (status === 413) errorMsg = "[Error 413] Foto terlalu besar untuk dikirim. Hubungi IT.";
        }
        errorModalMessage.value = errorMsg;
        showErrorModal.value = true;
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <div class="flex-1 flex flex-col lg:flex-row gap-8 min-h-0 overflow-y-auto custom-scrollbar animate-fade-in">
        <!-- Transaction Summary & Form -->
        <div class="flex-[2] space-y-8 min-w-0">
            <div
                class="bg-white dark:bg-surface-800 rounded-[1.5rem] sm:rounded-[2rem] border border-surface-200 dark:border-surface-700 p-6 sm:p-8 shadow-xl">
                <h3 class="text-xl sm:text-2xl font-black text-text-primary mb-6 sm:mb-8 flex items-center gap-3">
                    <User :size="28" class="text-primary-500" stroke-width="2.5" /> Detail Pelanggan
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 sm:gap-6">
                    <div class="space-y-2">
                        <label class="block text-xs font-black text-text-secondary uppercase tracking-widest px-1">
                            Nama Pelanggan <span class="text-red-500">*</span>
                        </label>
                        <input v-model="customerForm.customer_name" type="text" placeholder="Masukkan nama..."
                            class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-2xl px-5 py-3.5 sm:py-4 bg-surface-50 dark:bg-surface-900 text-base sm:text-lg font-medium text-text-primary focus:outline-none focus:border-primary-500 focus:bg-white dark:focus:bg-surface-800 dark:text-white transition-all shadow-sm" />
                    </div>
                    <div class="space-y-2">
                        <label class="block text-xs font-black text-text-secondary uppercase tracking-widest px-1">
                            WhatsApp Customer <span class="text-red-500">*</span>
                        </label>
                        <input v-model="customerForm.customer_phone" type="text" placeholder="08xxx..."
                            class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-2xl px-5 py-3.5 sm:py-4 bg-surface-50 dark:bg-surface-900 text-base sm:text-lg font-medium text-text-primary focus:outline-none focus:border-primary-500 focus:bg-white dark:focus:bg-surface-800 dark:text-white transition-all shadow-sm" />
                    </div>
                    <div class="md:col-span-2 space-y-2">
                        <label class="block text-xs font-black text-text-secondary uppercase tracking-widest px-1">
                            Keterangan / Notes <span class="text-red-500">*</span>
                        </label>
                        <textarea v-model="customerForm.notes" rows="2" placeholder="Catatan khusus untuk nota ini..."
                            class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-2xl px-5 py-3.5 sm:py-4 bg-surface-50 dark:bg-surface-900 text-base sm:text-lg font-medium text-text-primary focus:outline-none focus:border-primary-500 focus:bg-white dark:focus:bg-surface-800 dark:text-white transition-all resize-none shadow-sm"></textarea>
                    </div>
                    <div class="md:col-span-2 space-y-3">
                        <label class="block text-xs font-black text-text-secondary uppercase tracking-widest px-1">
                            Foto Bukti Nota/Unit <span class="text-[10px] lowercase text-text-secondary font-medium">(Max
                                10MB)</span>
                            <span class="text-red-500">*</span>
                        </label>
                        <div class="flex flex-col gap-4">
                            <div class="relative group">
                                <input type="file" @change="handleFileChange" accept="image/*" capture="environment"
                                    class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" />
                                <div
                                    class="w-full border-2 border-dashed border-surface-300 dark:border-surface-600 rounded-2xl p-4 sm:p-6 flex flex-col items-center justify-center gap-2 bg-surface-50 dark:bg-surface-900 group-hover:bg-primary-50 dark:group-hover:bg-primary-900/10 group-hover:border-primary-500 transition-all">
                                    <Upload class="text-text-secondary group-hover:text-primary-500" :size="24"
                                        stroke-width="1.5" />
                                    <div class="text-center">
                                        <p
                                            class="text-sm font-black text-text-primary group-hover:text-primary-600 transition-colors">
                                            Pilih atau Ambil Foto Unit</p>
                                        <p
                                            class="text-[10px] text-text-secondary font-medium uppercase tracking-widest">
                                            Klik untuk mengupload bukti nota</p>
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

                            <div v-if="proofImagePreview" class="relative rounded-2xl overflow-hidden group border-2 border-surface-200 dark:border-surface-700">
                                <img :src="proofImagePreview" class="w-full h-48 object-cover" />
                                <button @click="proofImage = null; proofImagePreview = null"
                                    class="absolute top-2 right-2 p-2 bg-red-500 text-white rounded-xl opacity-0 group-hover:opacity-100 transition-opacity hover:bg-red-600 shadow-lg">
                                    <Trash2 :size="16" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- FOTO BUKTI PEMBAYARAN -->
                    <div v-if="!isCashOnly" class="md:col-span-2 space-y-3 mt-4">
                        <label class="block text-xs font-black text-amber-600 dark:text-amber-500 uppercase tracking-widest px-1">
                            Foto Bukti Pembayaran / Transfer <span class="text-[10px] lowercase text-text-secondary font-medium">(Max 10MB)</span>
                            <span class="text-red-500">*</span>
                        </label>
                        <div class="flex flex-col gap-4">
                            <div class="relative group">
                                <input type="file" @change="handlePaymentFileChange" accept="image/*" capture="environment"
                                    class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" />
                                <div
                                    class="w-full border-2 border-dashed border-amber-300 dark:border-amber-600/50 rounded-2xl p-4 sm:p-6 flex flex-col items-center justify-center gap-2 bg-amber-50/50 dark:bg-amber-900/10 group-hover:bg-amber-100/50 dark:group-hover:bg-amber-900/20 group-hover:border-amber-500 transition-all">
                                    <Upload class="text-amber-500 group-hover:text-amber-600" :size="24"
                                        stroke-width="1.5" />
                                    <div class="text-center">
                                        <p
                                            class="text-sm font-black text-amber-700 dark:text-amber-500 group-hover:text-amber-800 transition-colors">
                                            Upload Bukti Transfer / EDC</p>
                                        <p
                                            class="text-[10px] text-amber-600/70 font-medium uppercase tracking-widest">
                                            Wajib untuk non-tunai</p>
                                    </div>
                                </div>
                            </div>

                            <!-- COMPRESSION LOADER -->
                            <div v-if="isCompressingPayment"
                                class="flex items-center gap-3 p-4 bg-amber-50 dark:bg-amber-900/10 rounded-2xl border border-amber-100 dark:border-amber-500/20 animate-pulse">
                                <Loader2 class="animate-spin text-amber-500" :size="20" />
                                <span
                                    class="text-xs font-black text-amber-600 dark:text-amber-400 uppercase tracking-widest">Mengompres
                                    Foto...</span>
                            </div>

                            <div v-if="paymentProofImagePreview" class="relative rounded-2xl overflow-hidden group border-2 border-amber-200 dark:border-amber-700/50">
                                <img :src="paymentProofImagePreview" class="w-full h-48 object-cover" />
                                <button @click="paymentProofImage = null; paymentProofImagePreview = null"
                                    class="absolute top-2 right-2 p-2 bg-red-500 text-white rounded-xl opacity-0 group-hover:opacity-100 transition-opacity hover:bg-red-600 shadow-lg">
                                    <Trash2 :size="16" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="bg-white dark:bg-surface-800 rounded-[1.5rem] sm:rounded-[2rem] border border-surface-200 dark:border-surface-700 p-4 sm:p-8 shadow-xl">
                <h3 class="text-2xl font-black text-text-primary mb-8 flex items-center gap-3">
                    <ShoppingCart :size="28" class="text-primary-500" stroke-width="2.5" /> Ringkasan Pembelian
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
                                <p class="font-black text-lg text-text-primary">{{ item.product?.name || item.name }}
                                </p>
                                <p class="text-sm font-bold text-text-secondary">{{ formatCurrency(item.price) }} / unit
                                </p>
                                <p v-if="item.imei" class="text-xs font-mono font-bold text-text-secondary bg-surface-100 dark:bg-surface-800 px-2 py-1 rounded w-fit">{{ item.imei }}</p>
                            </div>
                        </div>
                        <p class="font-black text-xl text-primary-600">{{ formatCurrency(item.price * item.quantity) }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Block -->
        <div class="flex-[1.5] min-w-0">
            <div
                class="bg-white dark:bg-surface-800 rounded-[1.5rem] sm:rounded-[2rem] border border-surface-200 dark:border-surface-700 p-5 sm:p-8 shadow-2xl lg:sticky lg:top-0">

                <div v-if="missingFields.length > 0"
                    class="p-4 bg-orange-50 dark:bg-orange-500/10 border border-orange-200 dark:border-orange-500/20 rounded-xl flex items-start gap-3 mb-6">
                    <AlertCircle class="text-orange-500 dark:text-orange-400 shrink-0" :size="20" />
                    <div class="flex-1">
                        <p class="text-xs text-orange-700 dark:text-orange-300 font-bold mb-1 uppercase tracking-tight">
                            Data Belum Lengkap:</p>
                        <ul
                            class="text-[10px] sm:text-xs text-orange-600 dark:text-orange-400 font-medium list-disc list-inside">
                            <li v-for="field in missingFields" :key="field">{{ field }}</li>
                        </ul>
                    </div>
                </div>

                <div class="text-center mb-6 sm:mb-8 pb-6 sm:pb-8 border-b border-surface-100 dark:border-surface-700">
                    <p
                        class="text-text-secondary text-[10px] sm:text-xs font-black uppercase tracking-widest mb-2 sm:mb-3">
                        TOTAL TAGIHAN</p>
                    <p class="text-3xl sm:text-5xl font-black text-primary-600 tracking-tight">{{
                        formatCurrency(effectiveTotal)
                        }}</p>
                    <!-- DP Deduction breakdown -->
                    <div v-if="dpDeduction > 0" class="mt-4 space-y-1">
                        <div class="flex justify-between text-xs font-bold text-text-secondary px-2">
                            <span>Subtotal Item</span>
                            <span>{{ formatCurrency(cartTotal) }}</span>
                        </div>
                        <div class="flex justify-between text-xs font-bold text-emerald-600 px-2">
                            <span>DP Sudah Dibayar</span>
                            <span>- {{ formatCurrency(dpDeduction) }}</span>
                        </div>
                    </div>
                </div>

                <div class="space-y-8">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <p class="text-sm font-black text-text-secondary uppercase tracking-widest">
                                Metode Pembayaran (Split)</p>
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

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label
                                            class="block text-[10px] font-black text-text-secondary uppercase tracking-widest mb-2">Metode</label>
                                        <div class="relative">
                                            <select v-model="payment.method_id"
                                                class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 bg-white dark:bg-surface-800 text-sm font-bold text-text-primary focus:outline-none focus:border-primary-500 transition-all appearance-none">
                                                <option v-for="method in availablePaymentMethods" :key="method.id"
                                                    :value="method.id"
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
                                            <input v-money:amount="payment" type="text"
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
                                Diskon Tambahan</p>
                            <div class="flex gap-1.5 bg-surface-100 dark:bg-surface-800 p-1 rounded-xl">
                            <button @click="switchToPercentage"
                                class="px-4 py-2 text-xs rounded-lg font-black transition-all"
                                :class="cartStore.discountType === 'percentage' ? 'bg-primary-500 text-white shadow-md' : 'text-text-secondary hover:text-text-primary'">%</button>
                            <button @click="switchToFixed"
                                    class="px-4 py-2 text-xs rounded-lg font-black transition-all"
                                    :class="cartStore.discountType === 'fixed' ? 'bg-primary-500 text-white shadow-md' : 'text-text-secondary hover:text-text-primary'">Rp</button>
                            </div>
                        </div>
                        <div class="relative">
                            <span v-if="cartStore.discountType === 'fixed'"
                                class="absolute left-5 top-1/2 -translate-y-1/2 text-text-secondary text-lg font-bold">Rp</span>
                            <input v-if="cartStore.discountType === 'fixed'" v-money:discount="cartStore"
                                type="text"
                                class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-2xl px-5 py-4 bg-surface-50 dark:bg-surface-900 text-text-primary text-xl font-black focus:outline-none focus:border-primary-500 focus:bg-white dark:focus:bg-surface-800 transition-all pl-14"
                                placeholder="0" />
                            <input v-else v-model.number="cartStore.discount" type="number"
                                class="w-full border-2 border-surface-200 dark:border-surface-700 rounded-2xl px-5 py-4 bg-surface-50 dark:bg-surface-900 text-text-primary text-xl font-black focus:outline-none focus:border-primary-500 focus:bg-white dark:focus:bg-surface-800 transition-all"
                                placeholder="0" />
                            <span v-if="cartStore.discountType === 'percentage'"
                                class="absolute right-5 top-1/2 -translate-y-1/2 text-text-secondary text-lg font-bold">%</span>
                        </div>
                    </div>

                    <div v-if="cartStore.discountAmount > 0"
                        class="p-5 bg-primary-500/10 border border-primary-500/20 rounded-2xl flex justify-between items-center">
                        <span class="text-sm font-black text-primary-700">Potongan Diskon</span>
                        <span class="text-xl font-black text-primary-600">- {{ formatCurrency(cartStore.discountAmount)
                            }}</span>
                    </div>

                    <!-- Change/Balance Status -->
                    <div v-if="changeAmount < 0"
                        class="p-4 sm:p-6 border-2 rounded-2xl flex flex-col sm:flex-row justify-between items-start sm:items-center my-6 gap-2 sm:gap-0"
                        :class="transactionCategory === 'dp' ? 'bg-amber-500/10 border-amber-500/20' : 'bg-red-500/10 border-red-500/20 animate-pulse'">
                        <span
                            class="text-[10px] sm:text-sm font-black uppercase tracking-widest"
                            :class="transactionCategory === 'dp' ? 'text-amber-700 dark:text-amber-400' : 'text-red-700 dark:text-red-400'">
                            {{ transactionCategory === 'dp' ? 'Sisa Tagihan (Belum Lunas)' : 'Uang Kurang' }}
                        </span>
                        <span class="text-2xl sm:text-3xl font-black"
                            :class="transactionCategory === 'dp' ? 'text-amber-600 dark:text-amber-500' : 'text-red-600 dark:text-red-500'">{{
                            formatCurrency(Math.abs(changeAmount))
                            }}</span>
                    </div>
                    <div v-else-if="changeAmount >= 0 && isCashPayment"
                        class="p-4 sm:p-6 bg-emerald-500/10 border-2 border-emerald-500/20 rounded-2xl flex flex-col sm:flex-row justify-between items-start sm:items-center my-6 gap-2 sm:gap-0">
                        <span
                            class="text-[10px] sm:text-sm font-black text-emerald-700 dark:text-emerald-400 uppercase tracking-widest">Kembalian</span>
                        <span class="text-2xl sm:text-3xl font-black text-emerald-600 dark:text-emerald-500">{{
                            formatCurrency(changeAmount)
                            }}</span>
                    </div>

                    <div class="flex gap-4 pt-8 border-t border-surface-100 dark:border-surface-700">
                        <button @click="emit('prev')"
                            class="w-20 h-20 flex-none bg-surface-100 dark:bg-surface-800 hover:bg-surface-200 dark:hover:bg-surface-700 text-text-primary rounded-[1.25rem] font-bold transition-all flex items-center justify-center">
                            <ArrowLeft :size="28" />
                        </button>
                        <button @click="handleSubmitOrder" :disabled="isSubmitting || isCompressing || isClickLocked"
                            class="flex-1 h-20 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 disabled:grayscale disabled:cursor-not-allowed text-white rounded-[1.25rem] font-black text-xl shadow-2xl shadow-emerald-500/30 transition-all flex items-center justify-center gap-3"
                            :class="{ 'opacity-60 grayscale cursor-not-allowed': !isFormValid && !isSubmitting }">
                            <Loader2 v-if="isSubmitting || isClickLocked" class="animate-spin" :size="28" />
                            <CheckCircle v-else :size="28" />
                            <span>
                                {{ (isSubmitting || isClickLocked) ? 'Memproses...' : submitButtonText }}
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Error Modal -->
        <div v-if="showErrorModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-fade-in">
            <div class="bg-white dark:bg-surface-800 rounded-[2rem] p-6 sm:p-8 max-w-md w-full shadow-2xl flex flex-col items-center text-center transform transition-all scale-100">
                <div class="w-20 h-20 bg-red-50 dark:bg-red-500/10 rounded-full flex items-center justify-center mb-6 ring-8 ring-red-500/5">
                    <AlertCircle class="text-red-500 w-10 h-10" stroke-width="2.5" />
                </div>
                <h3 class="text-2xl font-black text-text-primary mb-2">Transaksi Gagal</h3>
                <p class="text-text-secondary font-medium mb-8 leading-relaxed">{{ errorModalMessage }}</p>
                <button @click="showErrorModal = false" class="w-full py-4 bg-surface-100 hover:bg-surface-200 dark:bg-surface-700 dark:hover:bg-surface-600 text-text-primary font-black rounded-xl transition-all active:scale-95">
                    Tutup & Periksa Keranjang
                </button>
            </div>
        </div>

    </div>
</template>
