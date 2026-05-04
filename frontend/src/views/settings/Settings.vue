<script setup>
import { ref, onMounted, computed } from "vue";
import { useAuthStore } from "../../store/auth";
import { users as usersApi, inventory as inventoryApi, auth as authApiApi } from "../../api/axios";
import { useToast } from "../../composables/useToast";
import { User, Camera, Lock, Save, Loader2, Mail, Phone, MapPin, Shield, Key, Edit2, AlertCircle, Clock, RefreshCw } from "lucide-vue-next";
import { formatDate } from "../../utils/formatters";
import PinModal from "../../components/modals/PinModal.vue";

const authStore = useAuthStore();
const toast = useToast();

const user = ref({});
const isLoading = ref(false);
const isSaving = ref(false);
const photoPreview = ref(null);
const photoFile = ref(null);

// PIN State
const showPinModal = ref(false);
const pinModalMode = ref('verify');
const pinModalTitle = ref('Verifikasi PIN');
const pinError = ref("");
const isPinLoading = ref(false);

const form = ref({
    full_name: "",
    username: "",
    email: "",
    phone: "",
    address: "",
    current_password: "",
    new_password: "",
    confirm_password: ""
});

// Multi-Account PIN State
const inventoryAccounts = ref([]);
const selectedAccountId = ref(null); // ID of the selected sub-account

const selectedAccount = computed(() => {
    return inventoryAccounts.value.find(acc => acc.id === selectedAccountId.value) || {};
});

onMounted(async () => {
    isLoading.value = true;
    try {
        // Fetch fresh user data
        if (authStore.user?.id) {
            const res = await usersApi.get(authStore.user.id);
            user.value = res.data.data;
            form.value.full_name = user.value.full_name;
            form.value.username = user.value.username;
            form.value.email = user.value.email;
            form.value.phone = user.value.phone;
            form.value.address = user.value.address;

            // Fetch my inventory accounts
            const invRes = await inventoryApi.myAccounts();
            inventoryAccounts.value = invRes.data.data || invRes.data;
            
            // Default to the first inventory account if exists
            if (inventoryAccounts.value.length > 0) {
                selectedAccountId.value = inventoryAccounts.value[0].id;
            }
        }
    } catch (error) {
        console.error("Failed to fetch profile", error);
        toast.error("Gagal memuat profil.");
    } finally {
        isLoading.value = false;
    }
});

const isUploadingPhoto = ref(false);

async function handlePhotoChange(event) {
    const file = event.target.files[0];
    if (!file) return;

    if (file.size > 2 * 1024 * 1024) {
        toast.error("Ukuran foto maksimal 2MB");
        return;
    }

    const hasExistingPhoto = !!user.value.photo;
    if (hasExistingPhoto) {
        if (!confirm("Anda sudah memiliki foto profil. Penggantian foto memerlukan persetujuan dari Audit/Super Admin. Lanjutkan?")) {
            event.target.value = '';
            return;
        }
    }

    // Immediate Upload
    isUploadingPhoto.value = true;
    const formData = new FormData();
    formData.append("photo", file);
    formData.append("_method", "PUT"); 

    try {
        const res = await usersApi.updateProfile(user.value.id, formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });

        if (hasExistingPhoto) {
            toast.success("Permintaan pembaruan foto dikirim! Menunggu persetujuan Audit.");
        } else {
            toast.success("Foto profil berhasil diperbarui!");
        }

        // Refresh user data from server to get pending_photo status
        const freshRes = await usersApi.get(user.value.id);
        user.value = freshRes.data.data;
        
        // Update Auth Store
        authStore.updateUserData(user.value);
        photoPreview.value = null;

    } catch (error) {
        console.error("Upload photo error", error);
        toast.error(error.response?.data?.message || "Gagal mengupload foto.");
    } finally {
        isUploadingPhoto.value = false;
        event.target.value = '';
    }
}

async function saveProfile() {
    isSaving.value = true;
    try {
        const formData = new FormData();
        formData.append("_method", "PUT");

        formData.append("full_name", form.value.full_name);
        formData.append("username", form.value.username);
        formData.append("email", form.value.email || "");
        formData.append("phone", form.value.phone || "");
        formData.append("address", form.value.address || "");

        if (form.value.new_password) {
            if (form.value.new_password !== form.value.confirm_password) {
                toast.error("Konfirmasi password tidak cocok");
                isSaving.value = false;
                return;
            }
            formData.append("password", form.value.new_password);
        }

        // Use updateProfile for text data too as it handles the POST/PUT spoofing correctly
        const res = await usersApi.updateProfile(user.value.id, formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });

        toast.success("Profil berhasil diperbarui!");

        // Update store and persist
        authStore.updateUserData(res.data.data);

        // Clear password fields
        form.value.current_password = "";
        form.value.new_password = "";
        form.value.confirm_password = "";

    } catch (error) {
        console.error("Update profile error", error);
        toast.error("Gagal memperbarui profil.");
    } finally {
        isSaving.value = false;
    }
}

function openSetPin() {
    pinModalMode.value = 'setup';
    pinModalTitle.value = 'Pasang PIN Transaksi';
    showPinModal.value = true;
}


async function handlePinToggle() {
    const account = selectedAccount.value;
    const exists = account.transaction_pin_exists;
    const action = account.pin_enabled ? 'Matikan' : (exists ? 'Aktifkan' : 'Pasang');
    
    pinModalMode.value = exists ? 'verify' : 'setup';
    pinModalTitle.value = `${action} PIN ${selectedAccountId.value === 'main' ? 'Anda' : account.name}`;
    pinError.value = "";
    showPinModal.value = true;
}

const isRequestingReset = ref(false);
async function requestPinReset() {
    if (!confirm(`Ajukan reset PIN untuk ${selectedAccountId.value === 'main' ? 'Akun Utama' : selectedAccount.value.name} ke departemen Audit?`)) return;
    
    isRequestingReset.value = true;
    try {
        if (selectedAccountId.value === 'main') {
            await authApiApi.requestResetPin();
        } else {
            await inventoryApi.requestResetPin(selectedAccountId.value);
        }
        
        toast.success("Permintaan reset PIN berhasil diajukan!");
        
        // Refresh data
        if (selectedAccountId.value === 'main') {
            const res = await usersApi.get(authStore.user.id);
            user.value = res.data.data;
        } else {
            const invRes = await inventoryApi.myAccounts();
            inventoryAccounts.value = invRes.data.data || invRes.data;
        }
    } catch (e) {
        toast.error(e.response?.data?.message || "Gagal mengajukan reset PIN.");
    } finally {
        isRequestingReset.value = false;
    }
}

async function handlePinSuccess(pin) {
    isPinLoading.value = true;
    try {
        if (pinModalMode.value === 'setup') {
            if (selectedAccountId.value === 'main') {
                await authStore.setPin(pin);
            } else {
                const fd = new FormData();
                fd.append('transaction_pin', pin);
                fd.append('pin_enabled', 1);
                await inventoryApi.updateAccount(selectedAccountId.value, fd);
            }
            showPinModal.value = false;
            toast.success("PIN berhasil dipasang dan diaktifkan!");
        } else {
            // Toggle Logic
            let newState;
            if (selectedAccountId.value === 'main') {
                const res = await authStore.togglePin(pin);
                newState = res.data.pin_enabled;
            } else {
                const res = await inventoryApi.togglePin(selectedAccountId.value, pin);
                newState = res.data.data.pin_enabled;
            }
            showPinModal.value = false;
            toast.success(`PIN berhasil ${newState ? 'diaktifkan' : 'dinonaktifkan'}!`);
        }
        // Refresh user data
        if (selectedAccountId.value === 'main') {
            const res = await usersApi.get(authStore.user.id);
            user.value = res.data.data;
            authStore.updateUserData(user.value);
        } else {
            const invRes = await inventoryApi.myAccounts();
            inventoryAccounts.value = invRes.data.data || invRes.data;
        }
    } catch (error) {
        console.error("PIN operation failed", error);
        // User requested: no error toast if PIN is wrong (422)
        if (error.response?.status === 422) {
            pinError.value = error.response.data.message || "PIN salah.";
        } else {
            toast.error(error.response?.data?.message || "Operasi PIN gagal.");
        }
    } finally {
        isPinLoading.value = false;
    }
}
</script>

<template>
    <div class="space-y-6 animate-in pb-20">
        <!-- Header -->
        <div>
            <h1 class="text-2xl font-bold text-text-primary tracking-tight flex items-center gap-2">
                <User :size="28" class="text-primary-500" /> Pengaturan Profil
            </h1>
            <p class="text-text-secondary mt-1">Kelola informasi profil dan keamanan akun Anda</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Column: Photo & Basic Info -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Photo Card -->
                <div class="card flex flex-col items-center p-6">
                    <div class="relative group">
                        <div
                            class="w-32 h-32 rounded-full overflow-hidden border-4 border-surface-200 dark:border-surface-700 shadow-xl relative">
                            <!-- Uploading Loader -->
                            <div v-if="isUploadingPhoto"
                                class="absolute inset-0 bg-black/50 flex items-center justify-center z-10">
                                <Loader2 class="animate-spin text-white" :size="32" />
                            </div>

                            <!-- Pending Approval Overlay -->
                            <div v-if="user.pending_photo" class="absolute inset-0 bg-amber-500/20 backdrop-blur-[1px] flex flex-col items-center justify-center z-[5] group-hover:opacity-0 transition-opacity">
                                <Clock class="text-amber-500" :size="24" />
                                <span class="text-[8px] font-black text-amber-600 bg-white/80 px-1 rounded mt-1 uppercase">Pending Audit</span>
                            </div>

                            <!-- Image Display (Show pending if exists, otherwise actual) -->
                            <img :src="(user.pending_photo || user.photo)
                                ? ((user.pending_photo || user.photo).startsWith('http') ? (user.pending_photo || user.photo) : `${authStore.storageBaseUrl}/storage/${user.pending_photo || user.photo}`)
                                : `https://ui-avatars.com/api/?name=${encodeURIComponent(user.full_name || 'User')}&background=random&color=fff&size=512`"
                                class="w-full h-full object-cover" 
                                :class="{ 'opacity-50 grayscale-[0.5]': user.pending_photo }"
                                alt="Profile Photo"
                                @error="(e) => e.target.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(user.full_name || 'User')}&background=random&color=fff&size=512`" />
                            
                            <!-- Status Badge -->
                            <div v-if="user.pending_photo" class="absolute top-0 right-0 p-1.5 bg-amber-500 rounded-full border-2 border-white dark:border-surface-900 shadow-lg z-10">
                                <Clock class="text-white" :size="10" />
                            </div>
                        </div>
                        <label
                            class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer rounded-full z-20"
                            :class="{ 'pointer-events-none': isUploadingPhoto }">
                            <Camera class="text-white" :size="32" />
                            <input type="file" class="hidden" accept="image/*" @change="handlePhotoChange"
                                :disabled="isUploadingPhoto" />
                        </label>
                    </div>
                    <p class="mt-4 text-xs text-text-secondary">Klik foto untuk mengubah</p>

                    <div class="mt-6 text-center w-full">
                        <h3 class="text-lg font-bold text-text-primary">{{ user.full_name }}</h3>
                        <p class="text-primary-500 text-sm font-medium uppercase">{{ user.roles?.[0]?.name || 'User' }}
                        </p>

                        <div class="mt-4 pt-4 border-t border-surface-700/50 w-full text-left space-y-2">
                            <div class="flex items-center gap-2 text-sm text-text-secondary">
                                <Mail :size="14" />
                                <span class="truncate">{{ user.email || '-' }}</span>
                            </div>
                            <div class="flex items-center gap-2 text-sm text-text-secondary">
                                <Phone :size="14" />
                                <span>{{ user.phone || '-' }}</span>
                            </div>
                            <div class="flex items-center gap-2 text-sm text-text-secondary">
                                <MapPin :size="14" />
                                <span class="truncate">{{ user.address || '-' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Edit Form -->
            <div class="lg:col-span-2">
                <form @submit.prevent="saveProfile" class="card overflow-hidden">
                    <div class="px-6 py-4 bg-surface-900/50 border-b border-surface-700">
                        <h3 class="text-lg font-bold text-white flex items-center gap-2">
                            <User :size="20" class="text-primary-500" /> Pengaturan Akun
                        </h3>
                    </div>

                    <div class="p-6 space-y-8">
                        <!-- Personal Info -->
                        <div class="space-y-4">
                            <h4 class="text-xs font-black uppercase tracking-widest text-primary-500/80 mb-4 border-l-2 border-primary-500 pl-3">
                                Informasi Pribadi
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div class="space-y-1.5">
                                    <label class="label">Nama Lengkap</label>
                                    <div class="relative group">
                                        <User class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary group-focus-within:text-primary-500 transition-colors" :size="16" />
                                        <input v-model="form.full_name" type="text" class="input pl-10 opacity-70" disabled />
                                    </div>
                                </div>
                                <div class="space-y-1.5">
                                    <label class="label">Username</label>
                                    <div class="relative group">
                                        <Shield class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary group-focus-within:text-primary-500 transition-colors" :size="16" />
                                        <input v-model="form.username" type="text" class="input pl-10 opacity-70" disabled />
                                    </div>
                                </div>
                                <div class="space-y-1.5">
                                    <label class="label">Email</label>
                                    <div class="relative group">
                                        <Mail class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary group-focus-within:text-primary-500 transition-colors" :size="16" />
                                        <input v-model="form.email" type="email" class="input pl-10" placeholder="your@email.com" />
                                    </div>
                                </div>
                                <div class="space-y-1.5">
                                    <label class="label">No. Telepon</label>
                                    <div class="relative group">
                                        <Phone class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary group-focus-within:text-primary-500 transition-colors" :size="16" />
                                        <input v-model="form.phone" type="tel" class="input pl-10" placeholder="08xx-xxxx-xxxx" />
                                    </div>
                                </div>
                                <div class="md:col-span-2 space-y-1.5">
                                    <label class="label">Alamat</label>
                                    <div class="relative group">
                                        <MapPin class="absolute left-3 top-3 text-text-secondary group-focus-within:text-primary-500 transition-colors" :size="16" />
                                        <textarea v-model="form.address" class="input pl-10 min-h-[100px] resize-none" placeholder="Alamat lengkap Anda..."></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="h-px bg-gradient-to-r from-transparent via-surface-700 to-transparent"></div>

                        <!-- Security -->
                        <div class="space-y-4">
                            <h4 class="text-xs font-black uppercase tracking-widest text-primary-500/80 mb-4 border-l-2 border-primary-500 pl-3">
                                Keamanan Akun
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div class="space-y-1.5">
                                    <label class="label">Password Baru</label>
                                    <div class="relative group">
                                        <Lock class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary group-focus-within:text-primary-500 transition-colors" :size="16" />
                                        <input v-model="form.new_password" type="password" class="input pl-10"
                                            placeholder="••••••••" />
                                    </div>
                                </div>
                                <div class="space-y-1.5">
                                    <label class="label">Konfirmasi Password</label>
                                    <div class="relative group">
                                        <Lock class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary group-focus-within:text-primary-500 transition-colors" :size="16" />
                                        <input v-model="form.confirm_password" type="password" class="input pl-10"
                                            placeholder="••••••••" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="pt-4 flex justify-end">
                            <button type="submit" class="btn btn-primary min-w-[160px] shadow-lg shadow-primary-500/20" :disabled="isSaving">
                                <Loader2 v-if="isSaving" class="animate-spin mr-2" :size="18" />
                                <Save v-else class="mr-2" :size="18" />
                                {{ isSaving ? 'Menyimpan...' : 'Simpan Perubahan' }}
                            </button>
                        </div>

                        <div class="h-px bg-gradient-to-r from-transparent via-surface-700 to-transparent"></div>

                        <!-- PIN Management - ONLY FOR SALES -->
                        <div class="space-y-4">
                            <h4 class="text-xs font-black uppercase tracking-widest text-primary-500/80 mb-4 border-l-2 border-primary-500 pl-3">
                                Keamanan Transaksi (PIN)
                            </h4>
                            
                            <div class="mb-6 p-4 bg-surface-900 border border-surface-700 rounded-2xl" v-if="inventoryAccounts.length > 0">
                               <label class="text-[10px] uppercase font-black tracking-widest text-primary-500 block mb-2">Pilih Akun Staff Untuk Dikelola</label>
                               <div class="relative">
                                   <User class="absolute left-3 top-1/2 -translate-y-1/2 text-primary-500" :size="18" />
                                   <select v-model="selectedAccountId" class="input pl-10 font-bold border-primary-500/30 focus:border-primary-500">
                                       <option v-for="acc in inventoryAccounts" :key="acc.id" :value="acc.id">{{ acc.name }}</option>
                                   </select>
                               </div>
                            </div>

                            <div class="bg-gradient-to-br from-surface-900 to-surface-800 border border-surface-700 rounded-3xl p-6 shadow-xl relative overflow-hidden group">
                                <div class="absolute top-0 right-0 p-8 opacity-[0.03] group-hover:opacity-[0.05] transition-opacity">
                                    <Shield :size="120" />
                                </div>

                                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-6 relative z-10">
                                    <div class="flex items-center gap-4">
                                        <div
                                            class="w-14 h-14 rounded-2xl bg-primary-500/10 flex items-center justify-center text-primary-500 shadow-inner">
                                            <Key :size="28" />
                                        </div>
                                        <div>
                                            <p class="font-black text-white text-lg tracking-tight">Status PIN: {{ selectedAccountId === 'main' ? 'Akun Utama' : selectedAccount.name }}</p>
                                            <div class="flex items-center gap-2 mt-1">
                                                <div class="w-2 h-2 rounded-full" :class="selectedAccount.pin_enabled ? 'bg-emerald-500 animate-pulse' : 'bg-surface-600'"></div>
                                                <p class="text-xs font-medium text-text-secondary uppercase tracking-wider">
                                                    {{ selectedAccount.pin_enabled ? 'Aktif - Keamanan Terjamin' : 'Nonaktif - Tidak Disarankan' }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <button @click="handlePinToggle" type="button"
                                        class="relative inline-flex h-8 w-14 items-center rounded-full transition-all focus:outline-none focus:ring-2 focus:ring-primary-500/50 bg-surface-700 shadow-inner group/toggle"
                                        :class="{ 'bg-emerald-500/20 ring-1 ring-emerald-500/30': selectedAccount.pin_enabled }">
                                        <span
                                            class="inline-block h-6 w-6 transform rounded-full bg-white transition-all shadow-xl"
                                            :class="{ 'translate-x-7 bg-emerald-500': selectedAccount.pin_enabled, 'translate-x-1': !selectedAccount.pin_enabled }" />
                                    </button>
                                </div>

                                <!-- PIN Information -->
                                <div v-if="!selectedAccount.transaction_pin_exists && !selectedAccount.pin_enabled" class="mt-8 p-4 bg-primary-500/10 border border-primary-500/20 rounded-2xl flex items-center gap-3 animate-in slide-in-from-bottom-2 duration-300">
                                    <AlertCircle class="text-primary-500 shrink-0" :size="20" />
                                    <p class="text-xs font-bold text-text-secondary leading-relaxed">
                                        Akun ini <span class="text-primary-500">belum memiliki PIN</span>. Klik toggle di atas untuk memasang PIN baru demi keamanan transaksi.
                                    </p>
                                </div>

                                <!-- Pending Reset Info -->
                                <div v-if="selectedAccount.pin_reset_requested_at"
                                    class="mt-8 bg-amber-500/10 border border-amber-500/20 rounded-2xl p-5 flex items-start gap-4 animate-pulse">
                                    <div class="w-10 h-10 rounded-full bg-amber-500/20 flex items-center justify-center shrink-0">
                                        <Clock class="text-amber-500" :size="20" />
                                    </div>
                                    <div>
                                        <p class="text-sm font-black text-amber-500 uppercase tracking-widest mb-1">Permintaan Reset Pending</p>
                                        <p class="text-xs text-text-secondary leading-relaxed font-bold">
                                            Diajukan pada <span class="text-white">{{ formatDate(selectedAccount.pin_reset_requested_at, 'datetime') }}</span>. 
                                            Silakan hubungi Audit/Super Admin untuk persetujuan.
                                        </p>
                                    </div>
                                </div>

                                <div class="mt-8 flex flex-wrap gap-4 pt-4 border-t border-surface-700/50">
                                    <button @click="requestPinReset" :disabled="isRequestingReset"
                                        type="button" class="btn bg-surface-800 hover:bg-surface-700 text-white px-6 rounded-2xl text-[10px] font-black uppercase tracking-widest gap-2 border-surface-700 shadow-lg transition-all active:scale-95 group/reset">
                                        <RefreshCw v-if="isRequestingReset" :size="14" class="animate-spin" />
                                        <AlertCircle v-else :size="14" class="group-hover/reset:rotate-12 transition-transform" />
                                        {{ selectedAccount.pin_reset_requested_at ? 'Kirim Ulang Permintaan' : 'Ajukan Reset PIN' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- PIN Modal Component -->
    <PinModal :show="showPinModal" :mode="pinModalMode" :title="pinModalTitle" 
        :error="pinError" :loading="isPinLoading"
        @close="showPinModal = false"
        @success="handlePinSuccess" />
</template>

<style scoped>
@reference "../../style.css";

.label {
    @apply block text-xs font-medium text-text-secondary mb-1.5 uppercase tracking-wide;
}

.input {
    @apply w-full bg-surface-900 border border-surface-700 rounded-xl px-4 py-2.5 text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition-all placeholder:text-text-secondary;
}

.animate-in {
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(5px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
