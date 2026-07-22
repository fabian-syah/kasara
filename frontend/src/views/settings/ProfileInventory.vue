<script setup>
import { ref, computed } from 'vue';
import { useAuthStore } from '../../store/auth';
import { getRoleLabel } from '../../utils/permissions';
import { User, Key, Building2, UserCircle, Eye, EyeOff, Save, Loader2, CheckCircle2, AlertCircle } from 'lucide-vue-next';
import axios from '../../api/axios';

const authStore = useAuthStore();
const user = computed(() => authStore.user);
const userRole = computed(() => getRoleLabel(authStore.userRole));

const mainAccountName = computed(() => {
    return user.value?.created_by?.name || user.value?.created_by?.full_name || 'Admin Utama';
});

// Form state
const formData = ref({
    username: user.value?.username || '',
    password: '',
    password_confirmation: ''
});

const isEditing = ref(false);
const isLoading = ref(false);
const showPassword = ref(false);
const showConfirmPassword = ref(false);
const successMessage = ref('');
const errorMessage = ref('');

const toggleEdit = () => {
    if (!isEditing.value) {
        formData.value.username = user.value?.username || '';
        formData.value.password = '';
        formData.value.password_confirmation = '';
        successMessage.value = '';
        errorMessage.value = '';
    }
    isEditing.value = !isEditing.value;
};

const handleSave = async () => {
    successMessage.value = '';
    errorMessage.value = '';

    if (formData.value.password && formData.value.password !== formData.value.password_confirmation) {
        errorMessage.value = 'Konfirmasi password tidak cocok.';
        return;
    }

    try {
        isLoading.value = true;
        const payload = new FormData();
        payload.append('username', formData.value.username);
        
        if (formData.value.password) {
            if (formData.value.password.length < 6) {
                errorMessage.value = 'Password minimal 6 karakter.';
                isLoading.value = false;
                return;
            }
            payload.append('password', formData.value.password);
        }

        const response = await axios.post(`/inventory/account/${user.value.id}/update`, payload);
        
        if (response.data.success) {
            successMessage.value = 'Profil berhasil diperbarui.';
            isEditing.value = false;
            // Update auth store user data silently
            await authStore.fetchUser();
            
            setTimeout(() => {
                successMessage.value = '';
            }, 3000);
        } else {
            errorMessage.value = response.data.message || 'Gagal memperbarui profil.';
        }
    } catch (error) {
        errorMessage.value = error.response?.data?.message || 'Terjadi kesalahan saat menyimpan data.';
    } finally {
        isLoading.value = false;
    }
};

</script>

<template>
    <div class="max-w-4xl mx-auto space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-text-primary">Pengaturan Pribadi</h1>
            <p class="text-text-secondary text-sm mt-1">Lihat dan perbarui informasi akun inventory Anda</p>
        </div>

        <!-- Notifikasi -->
        <div v-if="successMessage" class="flex items-center gap-3 bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400 p-4 rounded-xl border border-emerald-100 dark:border-emerald-800/50">
            <CheckCircle2 :size="20" />
            <p class="text-sm font-medium">{{ successMessage }}</p>
        </div>
        <div v-if="errorMessage" class="flex items-center gap-3 bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 p-4 rounded-xl border border-red-100 dark:border-red-800/50">
            <AlertCircle :size="20" />
            <p class="text-sm font-medium">{{ errorMessage }}</p>
        </div>

        <div class="bg-white dark:bg-surface-900 rounded-2xl border border-surface-200 dark:border-surface-700 overflow-hidden shadow-sm">
            <div class="p-6 sm:p-8">
                <!-- Header Profil -->
                <div class="flex flex-col sm:flex-row items-center gap-6 mb-8">
                    <div class="w-24 h-24 rounded-full overflow-hidden border-4 border-primary-50 dark:border-primary-900/20 shadow-md shrink-0">
                        <img :src="authStore.userPhotoUrl || `https://ui-avatars.com/api/?name=${encodeURIComponent(user?.name || 'Inventory')}&background=10b981&color=fff&size=128`"
                            alt="Profile Photo" class="w-full h-full object-cover" />
                    </div>
                    <div class="text-center sm:text-left flex-1">
                        <h2 class="text-2xl font-bold text-text-primary">{{ user?.name || 'Akun Inventory' }}</h2>
                        <p class="text-primary-500 font-medium mt-1">{{ userRole }}</p>
                    </div>
                    <div class="shrink-0 mt-4 sm:mt-0">
                        <button v-if="!isEditing" @click="toggleEdit" class="px-5 py-2.5 bg-primary-50 text-primary-600 hover:bg-primary-100 dark:bg-primary-900/30 dark:text-primary-400 dark:hover:bg-primary-900/50 rounded-xl font-medium transition-colors border border-primary-100 dark:border-primary-800/50 flex items-center gap-2">
                            Ubah Data
                        </button>
                        <div v-else class="flex gap-2">
                            <button @click="toggleEdit" class="px-4 py-2.5 text-text-secondary hover:bg-surface-100 dark:hover:bg-surface-800 rounded-xl font-medium transition-colors border border-transparent">
                                Batal
                            </button>
                            <button @click="handleSave" :disabled="isLoading" class="px-5 py-2.5 bg-primary-600 text-white hover:bg-primary-700 dark:bg-primary-500 dark:hover:bg-primary-600 rounded-xl font-medium transition-colors shadow-sm flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                                <Loader2 v-if="isLoading" class="animate-spin" :size="18" />
                                <Save v-else :size="18" />
                                Simpan
                            </button>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Info Card: Username -->
                    <div class="bg-surface-50 dark:bg-surface-800 p-5 rounded-xl border border-surface-200 dark:border-surface-700 transition-colors" :class="{'ring-2 ring-primary-500/20 border-primary-300 dark:border-primary-700': isEditing}">
                        <div class="flex items-center gap-3 text-text-secondary mb-3">
                            <User :size="18" />
                            <span class="font-medium text-sm">Username</span>
                        </div>
                        <div v-if="!isEditing">
                            <p class="text-text-primary font-semibold text-lg">{{ user?.username || '-' }}</p>
                        </div>
                        <div v-else>
                            <input v-model="formData.username" type="text" class="w-full bg-white dark:bg-surface-900 border border-surface-300 dark:border-surface-600 rounded-lg px-4 py-2 text-text-primary focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition-all" placeholder="Masukkan username baru" />
                        </div>
                    </div>

                    <!-- Info Card: Akun Utama -->
                    <div class="bg-surface-50 dark:bg-surface-800 p-5 rounded-xl border border-primary-100 dark:border-primary-900/30">
                        <div class="flex items-center gap-3 text-primary-600 dark:text-primary-400 mb-3">
                            <UserCircle :size="18" />
                            <span class="font-medium text-sm">Akun Utama (Pembuat)</span>
                        </div>
                        <p class="text-text-primary font-semibold text-lg">{{ mainAccountName }}</p>
                        <p class="text-xs text-text-secondary mt-1">Akun ini dikelola oleh {{ mainAccountName }}</p>
                    </div>

                    <!-- Info Card: Cabang -->
                    <div class="bg-surface-50 dark:bg-surface-800 p-5 rounded-xl border border-surface-200 dark:border-surface-700 md:col-span-2">
                        <div class="flex items-center gap-3 text-text-secondary mb-3">
                            <Building2 :size="18" />
                            <span class="font-medium text-sm">Cabang / Lokasi Penempatan</span>
                        </div>
                        <p class="text-text-primary font-semibold text-lg">{{ user?.branch?.name || user?.warehouse?.name || user?.online_shop?.name || 'Semua Lokasi' }}</p>
                    </div>

                    <!-- Edit Password Section (Only visible when editing) -->
                    <div v-if="isEditing" class="md:col-span-2 bg-surface-50 dark:bg-surface-800 p-5 rounded-xl border border-surface-200 dark:border-surface-700 ring-2 ring-primary-500/20 border-primary-300 dark:border-primary-700">
                        <div class="flex items-center gap-3 text-text-secondary mb-4">
                            <Key :size="18" />
                            <span class="font-medium text-sm">Ubah Password (Opsional)</span>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Password Baru -->
                            <div>
                                <label class="block text-xs text-text-secondary mb-1">Password Baru</label>
                                <div class="relative">
                                    <input :type="showPassword ? 'text' : 'password'" v-model="formData.password" class="w-full bg-white dark:bg-surface-900 border border-surface-300 dark:border-surface-600 rounded-lg px-4 py-2 pr-10 text-text-primary focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition-all" placeholder="Biarkan kosong jika tidak diubah" />
                                    <button @click="showPassword = !showPassword" type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-surface-400 hover:text-surface-600 dark:hover:text-surface-300 transition-colors">
                                        <Eye v-if="!showPassword" :size="18" />
                                        <EyeOff v-else :size="18" />
                                    </button>
                                </div>
                            </div>
                            <!-- Konfirmasi Password -->
                            <div>
                                <label class="block text-xs text-text-secondary mb-1">Konfirmasi Password</label>
                                <div class="relative">
                                    <input :type="showConfirmPassword ? 'text' : 'password'" v-model="formData.password_confirmation" class="w-full bg-white dark:bg-surface-900 border border-surface-300 dark:border-surface-600 rounded-lg px-4 py-2 pr-10 text-text-primary focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition-all" placeholder="Ulangi password baru" />
                                    <button @click="showConfirmPassword = !showConfirmPassword" type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-surface-400 hover:text-surface-600 dark:hover:text-surface-300 transition-colors">
                                        <Eye v-if="!showConfirmPassword" :size="18" />
                                        <EyeOff v-else :size="18" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div v-if="!isEditing" class="mt-8 bg-surface-50 dark:bg-surface-800/50 border border-surface-200 dark:border-surface-700/50 rounded-xl p-4 flex items-start gap-3">
                    <div class="mt-0.5 text-primary-500">
                        <Key :size="18" />
                    </div>
                    <div>
                        <h3 class="text-text-primary font-medium text-sm mb-1">Keamanan Akun</h3>
                        <p class="text-text-secondary text-sm leading-relaxed">
                            Anda sekarang dapat mengubah username dan password Anda sendiri. Jaga kerahasiaan password Anda. Jika lupa password, Anda tetap dapat meminta <strong>{{ mainAccountName }}</strong> untuk meresetnya.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
