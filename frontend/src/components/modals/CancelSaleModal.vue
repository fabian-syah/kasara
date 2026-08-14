<script setup>
import { ref, onMounted, computed, watch, nextTick } from 'vue';
import { X, Trash2, User, Lock, AlertCircle, Loader2 } from 'lucide-vue-next';
import api, { inventory as inventoryApi } from "../../api/axios";
import { useToast } from "../../composables/useToast";
import { useAuthStore } from "../../store/auth";
import PasswordModal from "./PasswordModal.vue";

const props = defineProps({
    show: Boolean,
    sale: Object // The stock out record to cancel
});

const showPasswordAlert = ref(false);

const emit = defineEmits(["close", "success"]);

const authStore = useAuthStore();
const toast = useToast();
const loadingUsers = ref(false);
const submitting = ref(false);
const inventoryUsers = ref([]);

const form = ref({
    inventory_user_id: null,
    password: '',
    reason: ''
});

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
                pin_enabled: !!currentUser.pin_enabled
            });
        }
        
        inventoryUsers.value = accounts.map(u => ({
            ...u
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

const needsVerification = computed(() => {
    if (!selectedUser.value) return false;
    return true;
});

const verificationLabel = computed(() => {
    return `Password Akun Inventory (${selectedUser.value?.name || ''})`;
});

const verificationPlaceholder = computed(() => {
    return 'Masukkan Password Akun Inventory...';
});

const canSubmitInternal = computed(() => {
    return form.value.inventory_user_id && 
           (!needsVerification.value || form.value.password.length > 0) && 
           form.value.reason.length >= 5;
});



async function handleSubmit() {
    if (!form.value.inventory_user_id) {
        toast.error("Pilih akun inventory");
        return;
    }
    if (!authStore.hasRole('inventory') && selectedUser.value && !selectedUser.value.has_password) {
        showPasswordAlert.value = true;
        return;
    }
    if (needsVerification.value && !form.value.password) {
        toast.error(`Masukkan ${verificationLabel.value}`);
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
    form.value = { inventory_user_id: null, password: '', reason: '' };
    emit("close");
}

watch(() => props.show, (newVal) => {
    if (newVal) {
        fetchInventoryUsers();
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
                                    {{ user.name }} {{ user.id === sale?.inventory_user_id ? '(Pembuat)' : '' }}
                                </option>
                            </select>
                        </div>

                        <!-- Verification Input -->
                        <div v-if="needsVerification">
                            <label
                                class="block text-sm font-bold text-text-secondary mb-2 flex items-center gap-2 font-mono uppercase tracking-wider">
                                <Lock :size="16" /> {{ verificationLabel }}
                            </label>
                            
                            <!-- Missing Password Alert -->
                            <div v-if="selectedUser && !selectedUser.has_password" 
                                 class="p-3 bg-red-500/10 border border-red-500/20 rounded-xl text-red-500 text-sm font-medium mb-3 flex items-start gap-2">
                                 <AlertCircle :size="16" class="mt-0.5 shrink-0" />
                                 <span>Akun ini belum memiliki password. Transaksi tidak dapat dilanjutkan sebelum password diatur.</span>
                            </div>

                            <input v-else v-model="form.password" type="password"
                                class="w-full bg-surface-50 dark:bg-surface-800 border border-gray-200 dark:border-white/10 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition-all text-gray-900 dark:text-white"
                                :placeholder="verificationPlaceholder" />
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
        
        <!-- Password Modal Alert -->
        <PasswordModal v-if="showPasswordAlert" :show="showPasswordAlert" mode="alert"
            title="Akses Ditolak"
            :description="'Akun Inventory (' + (selectedUser?.name || '') + ') belum memasang PASSWORD LOGIN (Bukan PIN). Wajib atur password terlebih dahulu di menu Profil.'"
            :user="selectedUser"
            @close="showPasswordAlert = false" />
    </Teleport>
</template>

