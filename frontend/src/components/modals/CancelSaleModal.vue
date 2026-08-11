<script setup>
import { ref, onMounted, computed, watch, nextTick } from 'vue';
import { X, Trash2, User, Lock, AlertCircle, Loader2 } from 'lucide-vue-next';
import api, { inventory as inventoryApi } from "../../api/axios";
import { useToast } from "../../composables/useToast";
import { useAuthStore } from "../../store/auth";

const props = defineProps({
    show: Boolean,
    sale: Object // The stock out record to cancel
});

const emit = defineEmits(["close", "success"]);

const authStore = useAuthStore();
const toast = useToast();
const loadingUsers = ref(false);
const submitting = ref(false);
const inventoryUsers = ref([]);

const form = ref({
    inventory_user_id: null,
    transaction_pin: '',
    reason: ''
});

const pinDigits = ref(['', '', '', '']);
const pinInputs = ref([]);

async function fetchInventoryUsers() {
    loadingUsers.value = true;
    try {
        const response = await inventoryApi.myAccounts();
        const accounts = Array.isArray(response.data) ? response.data : (response.data?.data || []);
        
        // Include current user in the list if they're not there (backend excludes self)
        const currentUser = authStore.user;
        if (currentUser && !accounts.some(u => u.id === currentUser.id)) {
            accounts.unshift({
                id: currentUser.id,
                name: currentUser.name,
                full_name: currentUser.full_name,
                pin_enabled: true,
                transaction_pin_exists: !!currentUser.transaction_pin_exists
            });
        }
        
        inventoryUsers.value = accounts.map(u => ({
            ...u,
            pin_enabled: true,
            transaction_pin_exists: !!u.transaction_pin_exists
        }));
        
        // Auto select creator if present in the list
        if (props.sale?.inventory_user_id && inventoryUsers.value.length > 0) {
            const creator = inventoryUsers.value.find(u => u.id === props.sale.inventory_user_id);
            if (creator) {
                form.value.inventory_user_id = creator.id;
            } else if (inventoryUsers.value.length > 0) {
                form.value.inventory_user_id = inventoryUsers.value[0].id;
            }
        } else if (inventoryUsers.value.length > 0) {
            form.value.inventory_user_id = inventoryUsers.value[0].id;
        }
    } catch (e) {
        toast.error("Gagal memuat daftar akun inventory");
    } finally {
        loadingUsers.value = false;
    }
}

const selectedUser = computed(() => {
    if (!inventoryUsers.value) return null;
    return inventoryUsers.value.find(u => u.id === form.value.inventory_user_id);
});

const hasSelectedUserPin = computed(() => {
    return !!selectedUser.value;
});

const canSubmitInternal = computed(() => {
    return form.value.inventory_user_id && 
           (!hasSelectedUserPin.value || form.value.transaction_pin.length === 4) && 
           form.value.reason.length >= 5;
});

function handlePinInput(index, event) {
    const val = event.target.value;
    if (val && !/^\d+$/.test(val)) {
        pinDigits.value[index] = "";
        return;
    }
    if (val.length > 1) {
        pinDigits.value[index] = val.slice(-1);
    }
    if (val && index < 3) {
        pinInputs.value[index + 1].focus();
    }
    form.value.transaction_pin = pinDigits.value.join('');
}

function handlePinKeydown(index, event) {
    if (event.key === "Backspace" && !pinDigits.value[index] && index > 0) {
        pinInputs.value[index - 1].focus();
    }
}

async function handleSubmit() {
    if (!form.value.inventory_user_id) {
        toast.error("Pilih akun inventory");
        return;
    }
    if (hasSelectedUserPin.value && form.value.transaction_pin.length < 4) {
        toast.error("Masukkan 4 digit PIN");
        return;
    }
    if (!form.value.reason || form.value.reason.length < 5) {
        toast.error("Alasan pembatalan minimal 5 karakter");
        return;
    }

    submitting.value = true;
    try {
        await api.post(`/stock-outs/${props.sale.id}/cancel`, form.value);
        toast.success("Penjualan berhasil dibatalkan");
        emit("success");
        close();
    } catch (e) {
        toast.error(e.response?.data?.message || "Gagal membatalkan penjualan");
    } finally {
        submitting.value = false;
    }
}

function close() {
    form.value = { inventory_user_id: null, transaction_pin: '', reason: '' };
    pinDigits.value = ['', '', '', ''];
    emit("close");
}

watch(() => props.show, (newVal) => {
    if (newVal) {
        fetchInventoryUsers();
        nextTick(() => {
            if (pinInputs.value[0]) pinInputs.value[0].focus();
        });
    }
});
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-[110] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="close"></div>

            <div
                class="relative w-full max-w-md bg-white dark:bg-surface-900 rounded-[2.5rem] shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-300 border border-gray-200 dark:border-white/10">
                <div class="absolute top-0 left-0 w-full h-1.5 bg-gradient-to-r from-red-500 to-rose-700"></div>

                <div class="p-8">
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center gap-3">
                            <div
                                class="w-12 h-12 rounded-2xl bg-red-500/10 flex items-center justify-center text-red-500">
                                <Trash2 :size="24" />
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-text-primary">Batalkan Penjualan</h3>
                                <p class="text-xs text-text-secondary">Ref: {{ sale?.receipt_id || sale?.order_no }}</p>
                            </div>
                        </div>
                        <button @click="close"
                            class="p-2 hover:bg-gray-100 dark:hover:bg-white/10 rounded-full transition-colors text-text-secondary">
                            <X :size="20" />
                        </button>
                    </div>

                    <div class="space-y-5">
                        <!-- Inventory User Selection -->
                        <div>
                            <label class="block text-sm font-bold text-text-secondary mb-2 flex items-center gap-2">
                                <User :size="16" /> Akun Inventory Penanggung Jawab
                            </label>
                            <div v-if="loadingUsers" class="flex items-center gap-2 py-3 text-text-secondary text-sm">
                                <Loader2 :size="16" class="animate-spin" /> Memuat daftar akun...
                            </div>
                            <select v-else v-model="form.inventory_user_id"
                                class="w-full bg-surface-50 dark:bg-surface-800 border border-gray-200 dark:border-white/10 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition-all text-gray-900 dark:text-white">
                                <option :value="null" class="dark:bg-surface-800">-- Pilih Akun --</option>
                                <option v-for="user in inventoryUsers" :key="user.id" :value="user.id"
                                    class="dark:bg-surface-800">
                                    {{ user.name }} {{ user.id === sale?.inventory_user_id ? '(Pembuat)' : '' }} - {{
                                        user ? 'Sudah Ada PIN' : 'Belum Ada PIN' }}
                                </option>
                            </select>

                            <!-- Show warning ONLY if the user has NO active pin -->
                            <div v-if="selectedUser && !selectedUser"
                                class="mt-2 p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 flex gap-2">
                                <AlertCircle :size="16" class="text-amber-500 shrink-0 mt-0.5" />
                                <p class="text-[10px] text-amber-500 leading-tight">
                                    Akun <strong>{{ selectedUser.name }}</strong> belum memiliki PIN. <br>
                                    Silakan gunakan akun lain atau pasang PIN di menu akun.
                                </p>
                            </div>
                        </div>

                        <!-- PIN Input -->
                        <div v-if="hasSelectedUserPin">
                            <label
                                class="block text-sm font-bold text-text-secondary mb-2 flex items-center gap-2 font-mono uppercase tracking-wider">
                                <Lock :size="16" /> PIN Transaksi (4 Digit)
                            </label>
                            <div class="flex justify-between gap-3 px-4">
                                <input v-for="(digit, idx) in 4" :key="idx" :ref="el => pinInputs[idx] = el"
                                    v-model="pinDigits[idx]" type="password" inputmode="numeric" maxlength="1"
                                    class="w-full h-14 bg-surface-50 dark:bg-surface-800 border-2 border-surface-100 dark:border-white/5 rounded-2xl text-center text-3xl font-black text-gray-900 dark:text-white focus:outline-none focus:ring-4 focus:ring-red-500/20 focus:border-red-500 transition-all placeholder:text-text-secondary/20"
                                    @input="handlePinInput(idx, $event)" @keydown="handlePinKeydown(idx, $event)" />
                            </div>
                        </div>

                        <!-- Reason Input -->
                        <div>
                            <label class="block text-sm font-bold text-text-secondary mb-2">Alasan Pembatalan</label>
                            <textarea v-model="form.reason" rows="2"
                                class="w-full bg-surface-50 dark:bg-surface-800 border border-gray-200 dark:border-white/10 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition-all text-gray-900 dark:text-white placeholder:text-text-secondary/40"
                                placeholder="Contoh: Salah input barang, customer cancel order..."></textarea>
                        </div>
                    </div>

                    <div class="mt-8">
                        <button @click="handleSubmit" :disabled="submitting || !canSubmitInternal"
                            class="w-full h-14 rounded-2xl bg-red-600 hover:bg-red-500 disabled:opacity-30 disabled:cursor-not-allowed text-white font-bold transition-all shadow-lg shadow-red-600/20 flex items-center justify-center gap-2">
                            <Loader2 v-if="submitting" :size="20" class="animate-spin" />
                            <Trash2 v-else :size="20" />
                            Konfirmasi Pembatalan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
