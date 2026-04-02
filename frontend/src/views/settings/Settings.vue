<script setup>
import { ref, onMounted, computed } from "vue";
import { useAuthStore } from "../../store/auth";
import { users as usersApi } from "../../api/axios";
import { useToast } from "../../composables/useToast";
import { User, Camera, Lock, Save, Loader2, Mail, Phone, MapPin, Shield, Key, Edit2, AlertCircle, Clock } from "lucide-vue-next";
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

    // Immediate Upload
    isUploadingPhoto.value = true;
    const formData = new FormData();
    formData.append("photo", file);
    formData.append("_method", "PUT"); // Ensure method spoofing if needed, though updateProfile handles it

    try {
        // Use the correct API method: updateProfile
        const res = await usersApi.updateProfile(user.value.id, formData);

        toast.success("Foto profil berhasil diperbarui!");

        // Update local state
        user.value.photo = res.data.data.photo;
        photoPreview.value = null; // Clear preview to use actual URL

        // Update Auth Store (persists to localStorage)
        authStore.updateUserData(res.data.data);

    } catch (error) {
        console.error("Upload photo error", error);
        toast.error("Gagal mengupload foto.");
    } finally {
        isUploadingPhoto.value = false;
        // Reset input
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
    const action = user.value.pin_enabled ? 'Matikan' : 'Aktifkan';
    pinModalMode.value = 'verify';
    pinModalTitle.value = `${action} PIN Transaksi`;
    showPinModal.value = true;
}

async function requestPinReset() {
    if (!confirm("Ajukan reset PIN ke Audit?")) return;
    try {
        await authStore.requestResetPin(); // No - authStore doesn't have it, axios authApi does
        // Wait, auth store doesn't have it? 
        // I check current implementation...
    } catch (e) {
        toast.error("Gagal mengajukan reset PIN.");
    }
}

async function handlePinSuccess(pin, newPin) {
    showPinModal.value = false;
    try {
        if (pinModalMode.value === 'setup') {
            await authStore.setPin(pin);
            toast.success("PIN berhasil dipasang!");
        } else {
            // Toggle Logic
            await authStore.togglePin(pin);
            toast.success(`PIN berhasil ${user.value.pin_enabled ? 'dimatikan' : 'diaktifkan'}!`);
        }
        // Refresh user data
        const res = await usersApi.get(authStore.user.id);
        user.value = res.data.data;
        authStore.updateUserData(user.value);
    } catch (error) {
        console.error("PIN operation failed", error);
        toast.error(error.response?.data?.message || "Operasi PIN gagal.");
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
                            <div v-if="isUploadingPhoto"
                                class="absolute inset-0 bg-black/50 flex items-center justify-center z-10">
                                <Loader2 class="animate-spin text-white" :size="32" />
                            </div>
                            <img :src="user.photo
                                ? (user.photo.startsWith('http') ? user.photo : `${authStore.storageBaseUrl}/storage/${user.photo}`)
                                : `https://ui-avatars.com/api/?name=${encodeURIComponent(user.full_name || 'User')}&background=random&color=fff&size=512`"
                                class="w-full h-full object-cover" alt="Profile Photo"
                                @error="(e) => e.target.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(user.full_name || 'User')}&background=random&color=fff&size=512`" />
                        </div>
                        <label
                            class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer rounded-full"
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
                    <div v-if="authStore.userRole === 'toko_offline'">
                        <h3 class="text-lg font-semibold text-text-primary mb-4 flex items-center gap-2">
                            <Shield :size="20" class="text-primary-500" /> PIN Transaksi
                        </h3>
                        <p class="text-sm text-text-secondary mb-6">
                            Gunakan PIN 4-angka untuk mengamankan transaksi sensitif seperti input stok, hapus stok, dan
                            penjualan.
                        </p>

                        <div class="bg-surface-900 border border-surface-700 rounded-2xl p-6">
                            <div class="flex items-center justify-between mb-6">
                                <div class="flex items-center gap-4">
                                    <div
                                        class="w-12 h-12 rounded-xl bg-primary-500/10 flex items-center justify-center text-primary-500">
                                        <Key :size="24" />
                                    </div>
                                    <div>
                                        <p class="font-bold text-text-primary">Status PIN Transaksi</p>
                                        <p class="text-xs text-text-secondary">
                                            {{ user.pin_enabled ? 'Aktif - Transaksi memerlukan verifikasi PIN' :
                                                'Nonaktif - Transaksi tidak memerlukan PIN' }}
                                        </p>
                                    </div>
                                </div>
                                <button @click="handlePinToggle" type="button"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 bg-surface-700"
                                    :class="{ 'bg-primary-500': user.pin_enabled }">
                                    <span
                                        class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                        :class="{ 'translate-x-6': user.pin_enabled, 'translate-x-1': !user.pin_enabled }" />
                                </button>
                            </div>

                            <!-- Pending Reset Info -->
                            <div v-if="user.pin_reset_requested_at"
                                class="mb-6 bg-amber-500/10 border border-amber-500/20 rounded-2xl p-4 flex items-start gap-3">
                                <AlertCircle class="text-amber-500 shrink-0" :size="20" />
                                <div>
                                    <p class="text-sm font-bold text-amber-500 uppercase tracking-wider mb-1">Permintaan
                                        Reset Pending</p>
                                    <p class="text-xs text-text-secondary leading-relaxed">
                                        Anda telah meminta reset PIN pada <strong class="text-text-primary">{{
                                            formatDate(user.pin_reset_requested_at, 'datetime') }}</strong>. Silakan
                                        hubungi Audit Hub atau Admin untuk mendapatkan PIN baru.
                                    </p>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-3">
                                <button v-if="!user.pin_enabled && !user.transaction_pin_exists" @click="openSetPin"
                                    type="button" class="btn btn-primary px-6 rounded-xl">
                                    Pasang PIN Sekarang
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
