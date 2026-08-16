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
    transaction_pin: '',
    pin_confirmation: ''
});

const isEditing = ref(false);
const isLoading = ref(false);
const showPin = ref(false);
const showConfirmPin = ref(false);
const successMessage = ref('');
const errorMessage = ref('');

const toggleEdit = () => {
    if (!isEditing.value) {
        formData.value.username = user.value?.username || '';
        formData.value.transaction_pin = '';
        formData.value.pin_confirmation = '';
        successMessage.value = '';
        errorMessage.value = '';
    }
    isEditing.value = !isEditing.value;
};

const handleSave = async () => {
    successMessage.value = '';
    errorMessage.value = '';

    if (formData.value.transaction_pin && formData.value.transaction_pin !== formData.value.pin_confirmation) {
        errorMessage.value = 'Konfirmasi PIN tidak cocok.';
        return;
    }

    try {
        isLoading.value = true;
        const payload = new FormData();
        payload.append('username', formData.value.username);
        
        if (formData.value.transaction_pin) {
            if (formData.value.transaction_pin.length !== 4) {
                errorMessage.value = 'PIN harus 4 digit angka.';
                isLoading.value = false;
                return;
            }
            payload.append('transaction_pin', formData.value.transaction_pin);
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
    <div class="max-w-4xl mx-auto space-y-6 sm:space-y-8 font-jakarta pb-10">
        <!-- Header Section -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-black text-text-primary tracking-tight">Pengaturan Pribadi</h1>
                <p class="text-text-secondary text-sm font-medium mt-1.5">Kelola informasi dan keamanan akun CS Anda</p>
            </div>
        </div>

        <!-- Notifications -->
        <div v-if="successMessage" class="flex items-center gap-3 bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400 p-4 rounded-2xl border border-emerald-100 dark:border-emerald-800/50 animate-fade-in shadow-sm">
            <CheckCircle2 :size="20" class="shrink-0" />
            <p class="text-sm font-bold">{{ successMessage }}</p>
        </div>
        <div v-if="errorMessage" class="flex items-center gap-3 bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 p-4 rounded-2xl border border-red-100 dark:border-red-800/50 animate-fade-in shadow-sm">
            <AlertCircle :size="20" class="shrink-0" />
            <p class="text-sm font-bold">{{ errorMessage }}</p>
        </div>

        <!-- Main Content -->
        <div class="bg-white dark:bg-surface-800 rounded-[2rem] sm:rounded-[2.5rem] border border-surface-200 dark:border-surface-700 overflow-hidden shadow-xl shadow-surface-200/20 dark:shadow-black/10 relative">
            <!-- Decorative background elements -->
            <div class="absolute top-0 right-0 w-64 h-64 bg-primary-500/5 rounded-full -mr-32 -mt-32 blur-3xl pointer-events-none"></div>
            
            <div class="p-6 sm:p-10 relative z-10">
                <!-- Profile Header -->
                <div class="flex flex-col sm:flex-row items-center sm:items-start gap-6 sm:gap-8 mb-10">
                    <div class="w-28 h-28 sm:w-32 sm:h-32 rounded-[2rem] overflow-hidden border-4 sm:border-[6px] border-primary-50 dark:border-primary-900/20 shadow-xl shadow-primary-500/10 shrink-0 group relative bg-surface-100 dark:bg-surface-800">
                        <img :src="authStore.userPhotoUrl || `https://ui-avatars.com/api/?name=${encodeURIComponent(user?.name || 'Inventory')}&background=10b981&color=fff&size=128`"
                             alt="Profile Photo" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110" />
                    </div>
                    
                    <div class="text-center sm:text-left flex-1 pt-2 w-full">
                        <div class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary-50 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400 rounded-xl text-[10px] font-black uppercase tracking-widest mb-3 border border-primary-100 dark:border-primary-800/50">
                            <UserCircle :size="14" />
                            {{ userRole }}
                        </div>
                        <h2 class="text-3xl sm:text-4xl font-black text-text-primary tracking-tight truncate w-full">{{ user?.name || 'Akun CS' }}</h2>
                        <div class="flex items-center justify-center sm:justify-start gap-2 mt-3 text-text-secondary font-medium">
                            <Building2 :size="16" class="text-primary-500" />
                            <span class="truncate">{{ user?.branch?.name || user?.warehouse?.name || user?.online_shop?.name || 'Semua Lokasi' }}</span>
                        </div>
                    </div>
                    
                    <div class="shrink-0 mt-2 sm:mt-4 w-full sm:w-auto">
                        <button v-if="!isEditing" @click="toggleEdit" class="w-full sm:w-auto px-6 py-3.5 bg-primary-600 hover:bg-primary-500 text-white rounded-2xl font-black transition-all shadow-lg shadow-primary-500/20 hover:shadow-primary-500/40 hover:-translate-y-0.5 flex items-center justify-center gap-2">
                            Ubah Username & PIN Keamanan
                        </button>
                        <div v-else class="flex gap-3 w-full">
                            <button @click="toggleEdit" class="flex-1 sm:flex-none px-5 py-3.5 bg-surface-100 hover:bg-surface-200 dark:bg-surface-700 dark:hover:bg-surface-600 text-text-secondary rounded-2xl font-bold transition-all">
                                Batal
                            </button>
                            <button @click="handleSave" :disabled="isLoading" class="flex-1 sm:flex-none px-6 py-3.5 bg-primary-600 hover:bg-primary-500 text-white rounded-2xl font-black transition-all shadow-lg shadow-primary-500/20 hover:shadow-primary-500/40 hover:-translate-y-0.5 flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                                <Loader2 v-if="isLoading" class="animate-spin" :size="18" />
                                <Save v-else :size="18" />
                                Simpan
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Info Cards Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
                    <!-- Username Card -->
                    <div class="bg-surface-50 dark:bg-surface-800/80 p-6 rounded-[1.5rem] border border-surface-200 dark:border-surface-700 transition-all duration-300 relative overflow-hidden group" :class="{'ring-2 ring-primary-500/30 border-primary-400 dark:border-primary-600 bg-white dark:bg-surface-800 shadow-md': isEditing}">
                        <div class="flex items-center justify-between mb-4 relative z-10">
                            <div class="flex items-center gap-3 text-text-secondary">
                                <div class="w-10 h-10 rounded-xl bg-white dark:bg-surface-700 flex items-center justify-center shadow-sm border border-surface-200 dark:border-surface-600 text-primary-500 group-hover:scale-110 transition-transform">
                                    <User :size="18" stroke-width="2.5" />
                                </div>
                                <span class="font-black text-[11px] uppercase tracking-widest">Username</span>
                            </div>
                        </div>
                        <div v-if="!isEditing" class="relative z-10">
                            <p class="text-text-primary font-black text-xl">{{ user?.username || '-' }}</p>
                        </div>
                        <div v-else class="relative z-10">
                            <input v-model="formData.username" type="text" class="w-full bg-surface-50 dark:bg-surface-900/50 border-2 border-surface-200 dark:border-surface-700 rounded-xl px-4 py-3 text-text-primary font-bold focus:bg-white dark:focus:bg-surface-900 focus:border-primary-500 outline-none transition-all" placeholder="Masukkan username baru" />
                        </div>
                    </div>

                    <!-- Creator Account Card -->
                    <div class="bg-gradient-to-br from-primary-50 to-emerald-50 dark:from-primary-900/20 dark:to-emerald-900/20 p-6 rounded-[1.5rem] border border-primary-100 dark:border-primary-800/30 relative overflow-hidden group hover:shadow-lg hover:shadow-primary-500/5 transition-all">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-white/40 dark:bg-white/5 rounded-full -mr-16 -mt-16 blur-2xl group-hover:bg-primary-500/10 transition-colors"></div>
                        <div class="flex items-center justify-between mb-4 relative z-10">
                            <div class="flex items-center gap-3 text-primary-700 dark:text-primary-400">
                                <div class="w-10 h-10 rounded-xl bg-white/60 dark:bg-surface-800/50 flex items-center justify-center shadow-sm backdrop-blur-sm group-hover:scale-110 transition-transform">
                                    <UserCircle :size="18" stroke-width="2.5" />
                                </div>
                                <span class="font-black text-[11px] uppercase tracking-widest">Akun Utama</span>
                            </div>
                        </div>
                        <div class="relative z-10">
                            <p class="text-primary-900 dark:text-primary-100 font-black text-xl">{{ mainAccountName }}</p>
                            <p class="text-xs font-semibold text-primary-700/70 dark:text-primary-400/70 mt-1.5 flex items-center gap-1.5">
                                Akun pembuat & pengelola utama
                            </p>
                        </div>
                    </div>

                    <!-- Edit PIN Section -->
                    <div v-if="isEditing" class="md:col-span-2 bg-surface-50 dark:bg-surface-800/80 p-6 sm:p-8 rounded-[1.5rem] border-2 border-primary-200 dark:border-primary-800/50 ring-4 ring-primary-500/5 relative overflow-hidden animate-fade-in shadow-sm">
                        <div class="absolute top-0 right-0 w-64 h-64 bg-primary-500/5 rounded-full -mr-32 -mt-32 blur-3xl pointer-events-none"></div>
                        
                        <div class="flex items-center gap-3 text-primary-600 dark:text-primary-400 mb-6 relative z-10">
                            <div class="w-12 h-12 rounded-2xl bg-white dark:bg-surface-700 flex items-center justify-center shadow-sm border border-primary-100 dark:border-primary-800">
                                <Key :size="20" stroke-width="2.5" />
                            </div>
                            <div>
                                <h3 class="font-black text-lg">Keamanan PIN</h3>
                                <p class="text-xs font-medium text-text-secondary mt-0.5">Biarkan kosong jika tidak ingin mengubah PIN</p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 sm:gap-6 relative z-10">
                            <!-- PIN Baru -->
                            <div>
                                <label class="block text-[11px] font-black uppercase tracking-widest text-text-secondary mb-2">PIN Baru</label>
                                <div class="relative group/input">
                                    <input :type="showPin ? 'text' : 'password'" v-model="formData.transaction_pin" class="w-full bg-white dark:bg-surface-900 border-2 border-surface-200 dark:border-surface-700 rounded-xl px-5 py-3.5 pr-12 text-text-primary font-medium focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 outline-none transition-all placeholder:text-surface-400" placeholder="••••" maxlength="4" />
                                    <button @click="showPin = !showPin" type="button" class="absolute right-4 top-1/2 -translate-y-1/2 text-surface-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors p-1 bg-white dark:bg-surface-900 rounded-lg">
                                        <Eye v-if="!showPin" :size="18" stroke-width="2.5" />
                                        <EyeOff v-else :size="18" stroke-width="2.5" />
                                    </button>
                                </div>
                            </div>
                            <!-- Konfirmasi PIN -->
                            <div>
                                <label class="block text-[11px] font-black uppercase tracking-widest text-text-secondary mb-2">Ulangi PIN</label>
                                <div class="relative group/input">
                                    <input :type="showConfirmPin ? 'text' : 'password'" v-model="formData.pin_confirmation" class="w-full bg-white dark:bg-surface-900 border-2 border-surface-200 dark:border-surface-700 rounded-xl px-5 py-3.5 pr-12 text-text-primary font-medium focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 outline-none transition-all placeholder:text-surface-400" placeholder="••••" maxlength="4" />
                                    <button @click="showConfirmPin = !showConfirmPin" type="button" class="absolute right-4 top-1/2 -translate-y-1/2 text-surface-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors p-1 bg-white dark:bg-surface-900 rounded-lg">
                                        <Eye v-if="!showConfirmPin" :size="18" stroke-width="2.5" />
                                        <EyeOff v-else :size="18" stroke-width="2.5" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Info Footer -->
                <div v-if="!isEditing" class="mt-8 bg-surface-50 dark:bg-surface-800/50 border border-surface-200 dark:border-surface-700/50 rounded-2xl p-5 sm:p-6 flex flex-col sm:flex-row items-start sm:items-center gap-4 sm:gap-5 transition-colors">
                    <div class="w-12 h-12 rounded-full bg-white dark:bg-surface-700 flex items-center justify-center text-primary-500 shrink-0 shadow-sm border border-surface-200 dark:border-surface-600">
                        <Key :size="20" stroke-width="2.5" />
                    </div>
                    <div>
                        <h3 class="text-text-primary font-black text-sm mb-1">Keamanan Akun CS</h3>
                        <p class="text-text-secondary text-xs sm:text-sm font-medium leading-relaxed">
                            Anda memiliki kendali penuh atas PIN keamanan Anda sendiri. Jaga kerahasiaannya. Jika Anda lupa PIN, Anda dapat meminta <strong class="text-text-primary">{{ mainAccountName }}</strong> untuk meresetnya dari panel utama.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.font-jakarta {
    font-family: 'Plus Jakarta Sans', sans-serif;
}
.animate-fade-in {
    animation: fadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(5px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
