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
const selectedAccountId = ref('main'); // 'main' or ID

const selectedAccount = computed(() => {
    if (selectedAccountId.value === 'main') return user.value;
    return inventoryAccounts.value.find(acc => acc.id === selectedAccountId.value) || user.value;
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
        const res = await usersApi.updateProfile(user.value.id, formData);

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
        const res = await usersApi.updateProfile(user.value.id, formData);

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
    showPinModal.value = false;
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
        toast.error(error.response?.data?.message || "Operasi PIN gagal. Pastikan PIN benar.");
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
                <form @submit.prevent="saveProfile" class="card space-y-6">

                    <!-- Personal Info -->
                    <div>
                        <h3 class="text-lg font-semibold text-text-primary mb-4 flex items-center gap-2">
                            <User :size="20" class="text-primary-500" /> Informasi Pribadi
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="label">Nama Lengkap</label>
                                <input v-model="form.full_name" type="text" class="input" disabled />
                            </div>
                            <div>
                                <label class="label">Username</label>
                                <input v-model="form.username" type="text" class="input" disabled />
                            </div>
                            <div>
                                <label class="label">Email</label>
                                <input v-model="form.email" type="email" class="input" />
                            </div>
                            <div>
                                <label class="label">No. Telepon</label>
                                <input v-model="form.phone" type="tel" class="input" />
                            </div>
                            <div class="md:col-span-2">
                                <label class="label">Alamat</label>
                                <textarea v-model="form.address" class="input min-h-[80px]"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="h-px bg-surface-700/50"></div>

                    <!-- Security -->
                    <div>
                        <h3 class="text-lg font-semibold text-text-primary mb-4 flex items-center gap-2">
                            <Lock :size="20" class="text-primary-500" /> Keamanan (Ganti Password)
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="label">Password Baru</label>
                                <input v-model="form.new_password" type="password" class="input"
                                    placeholder="Biarkan kosong jika tidak diganti" />
                            </div>
                            <div>
                                <label class="label">Konfirmasi Password</label>
                                <input v-model="form.confirm_password" type="password" class="input"
                                    placeholder="Ulangi password baru" />
                            </div>
                        </div>
                    </div>

                    <div class="pt-4 flex justify-end">
                        <button type="submit" class="btn btn-primary min-w-[120px]" :disabled="isSaving">
                            <Loader2 v-if="isSaving" class="animate-spin mr-2" :size="18" />
                            {{ isSaving ? 'Menyimpan...' : 'Simpan Perubahan' }}
                        </button>
                    </div>

                    <div class="h-px bg-surface-700/50"></div>

                    <!-- PIN Management - ONLY FOR SALES -->
                    <div>
                        <h3 class="text-lg font-semibold text-text-primary mb-4 flex items-center gap-2">
                            <Shield :size="20" class="text-primary-500" /> Pengaturan Keamanan (PIN)
                        </h3>
                        
                        <div class="mb-4">
                           <label class="label text-[10px] uppercase font-black tracking-widest text-primary-500">Pilih Akun Untuk Dikelola</label>
                           <select v-model="selectedAccountId" class="input font-bold">
                               <option value="main">Akun Utama ({{ user.name || user.username }})</option>
                               <optgroup label="Akun Inventory Anda" v-if="inventoryAccounts.length > 0">
                                   <option v-for="acc in inventoryAccounts" :key="acc.id" :value="acc.id">{{ acc.name }}</option>
                               </optgroup>
                           </select>
                        </div>

                        <p class="text-sm text-text-secondary mb-6">
                            Gunakan PIN 4-angka untuk mengamankan transaksi sensitif pada akun <span class="text-text-primary font-bold">{{ selectedAccountId === 'main' ? 'Utama' : selectedAccount.name }}</span>.
                        </p>

                        <div class="bg-surface-900 border border-surface-700 rounded-2xl p-6">
                            <div class="flex items-center justify-between mb-6">
                                <div class="flex items-center gap-4">
                                    <div
                                        class="w-12 h-12 rounded-xl bg-primary-500/10 flex items-center justify-center text-primary-500">
                                        <Key :size="24" />
                                    </div>
                                    <div>
                                        <p class="font-bold text-text-primary">Status PIN: {{ selectedAccountId === 'main' ? 'Utama' : selectedAccount.name }}</p>
                                        <p class="text-xs text-text-secondary">
                                            {{ selectedAccount.pin_enabled ? 'Aktif - Transaksi memerlukan verifikasi PIN' :
                                                'Nonaktif - Transaksi tidak memerlukan PIN' }}
                                        </p>
                                    </div>
                                </div>
                                <button @click="handlePinToggle" type="button"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 bg-surface-700 shadow-inner"
                                    :class="{ 'bg-primary-500 shadow-primary-500/20': selectedAccount.pin_enabled }">
                                    <span
                                        class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform shadow-md"
                                        :class="{ 'translate-x-6': selectedAccount.pin_enabled, 'translate-x-1': !selectedAccount.pin_enabled }" />
                                </button>
                            </div>

                            <!-- PIN Information -->
                            <div v-if="!selectedAccount.transaction_pin_exists && !selectedAccount.pin_enabled" class="mb-6 p-4 bg-primary-500/10 border border-primary-500/20 rounded-2xl flex items-center gap-3">
                                <AlertCircle class="text-primary-500" :size="20" />
                                <p class="text-xs text-text-secondary">Akun ini belum memiliki PIN. Silahkan aktifkan Toggle di atas untuk memasang PIN baru.</p>
                            </div>

                            <!-- Pending Reset Info -->
                            <div v-if="selectedAccount.pin_reset_requested_at"
                                class="mb-6 bg-amber-500/10 border border-amber-500/20 rounded-2xl p-4 flex items-start gap-3 animate-pulse">
                                <AlertCircle class="text-amber-500 shrink-0" :size="20" />
                                <div>
                                    <p class="text-sm font-bold text-amber-500 uppercase tracking-wider mb-1">Permintaan Reset Pending</p>
                                    <p class="text-xs text-text-secondary leading-relaxed font-medium">
                                        Anda telah meminta reset PIN pada <strong class="text-text-primary">{{
                                            formatDate(selectedAccount.pin_reset_requested_at, 'datetime') }}</strong>. Silakan
                                        hubungi departemen Audit Hub atau Super Admin untuk mendapatkan PIN baru.
                                    </p>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-4">
                                <button @click="requestPinReset" :disabled="isRequestingReset"
                                    type="button" class="btn btn-secondary px-6 rounded-xl text-xs gap-2 border-surface-700 shadow-lg">
                                    <RefreshCw v-if="isRequestingReset" :size="16" class="animate-spin" />
                                    <AlertCircle v-else :size="16" />
                                    {{ selectedAccount.pin_reset_requested_at ? 'Ajukan Reset Lagi' : 'Ajukan Reset PIN ke Audit' }}
                                </button>
                            </div>
                        </div>
                    </div>

                </form>
            </div>
        </div>
    </div>

    <!-- PIN Modal Component -->
    <PinModal :show="showPinModal" :mode="pinModalMode" :title="pinModalTitle" @close="showPinModal = false"
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
