<script setup>
import { ref, onMounted } from "vue";
import { useAuthStore } from "../../store/auth";
import { users as usersApi } from "../../api/axios";
import { useToast } from "../../composables/useToast";
import { User, Camera, Lock, Save, Loader2, Mail, Phone, MapPin } from "lucide-vue-next";

const authStore = useAuthStore();
const toast = useToast();

const user = ref({});
const isLoading = ref(false);
const isSaving = ref(false);
const photoPreview = ref(null);
const photoFile = ref(null);

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

        // Update Auth Store immediately to reflect in Header/Sidebar
        authStore.user.photo = res.data.data.photo;
        // Optionally fetch fresh user to be sure
        // await authStore.fetchUser(); 

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

        // Update store
        authStore.user = res.data.data;

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
                            <img :src="user.photo ? (user.photo.startsWith('http') ? user.photo : `/storage/${user.photo}`) : `https://ui-avatars.com/api/?name=${encodeURIComponent(user.full_name || 'User')}&background=random&color=fff`"
                                class="w-full h-full object-cover" alt="Profile Photo" />
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
                                <input v-model="form.full_name" type="text" class="input" required />
                            </div>
                            <div>
                                <label class="label">Username</label>
                                <input v-model="form.username" type="text" class="input" required />
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

                </form>
            </div>
        </div>
    </div>
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
