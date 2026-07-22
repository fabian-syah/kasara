<script setup>
import { computed } from 'vue';
import { useAuthStore } from '../../store/auth';
import { getRoleLabel } from '../../utils/permissions';
import { User, Key, Building2, UserCircle } from 'lucide-vue-next';

const authStore = useAuthStore();
const user = computed(() => authStore.user);
const userName = computed(() => authStore.userName);
const userRole = computed(() => getRoleLabel(authStore.userRole));

const mainAccountName = computed(() => {
    if (user.value?.created_by_user?.name) {
        return user.value.created_by_user.name;
    }
    return 'Admin Utama';
});

</script>

<template>
    <div class="max-w-4xl mx-auto space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-text-primary">Pengaturan Pribadi</h1>
            <p class="text-text-secondary text-sm mt-1">Lihat informasi akun inventory Anda</p>
        </div>

        <div class="bg-white dark:bg-surface-900 rounded-2xl border border-surface-200 dark:border-surface-700 overflow-hidden shadow-sm">
            <div class="p-6 sm:p-8">
                <div class="flex flex-col sm:flex-row items-center gap-6 mb-8">
                    <div class="w-24 h-24 rounded-full overflow-hidden border-4 border-primary-50 dark:border-primary-900/20 shadow-md shrink-0">
                        <img :src="authStore.userPhotoUrl || `https://ui-avatars.com/api/?name=${encodeURIComponent(userName)}&background=10b981&color=fff&size=128`"
                            alt="Profile Photo" class="w-full h-full object-cover" />
                    </div>
                    <div class="text-center sm:text-left">
                        <h2 class="text-2xl font-bold text-text-primary">{{ userName }}</h2>
                        <p class="text-primary-500 font-medium mt-1">{{ userRole }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Info Card: Username -->
                    <div class="bg-surface-50 dark:bg-surface-800 p-4 rounded-xl border border-surface-200 dark:border-surface-700">
                        <div class="flex items-center gap-3 text-text-secondary mb-2">
                            <User :size="18" />
                            <span class="font-medium text-sm">Username / Email</span>
                        </div>
                        <p class="text-text-primary font-semibold text-lg">{{ user.value?.username || user.value?.email || '-' }}</p>
                    </div>

                    <!-- Info Card: Akun Utama -->
                    <div class="bg-surface-50 dark:bg-surface-800 p-4 rounded-xl border border-primary-100 dark:border-primary-900/30">
                        <div class="flex items-center gap-3 text-primary-600 dark:text-primary-400 mb-2">
                            <UserCircle :size="18" />
                            <span class="font-medium text-sm">Akun Utama (Pembuat)</span>
                        </div>
                        <p class="text-text-primary font-semibold text-lg">{{ mainAccountName }}</p>
                        <p class="text-xs text-text-secondary mt-1">Akun ini dikelola oleh {{ mainAccountName }}</p>
                    </div>

                    <!-- Info Card: Cabang -->
                    <div class="bg-surface-50 dark:bg-surface-800 p-4 rounded-xl border border-surface-200 dark:border-surface-700 md:col-span-2">
                        <div class="flex items-center gap-3 text-text-secondary mb-2">
                            <Building2 :size="18" />
                            <span class="font-medium text-sm">Cabang / Lokasi Penempatan</span>
                        </div>
                        <p class="text-text-primary font-semibold text-lg">{{ user.value?.branch?.name || user.value?.warehouse?.name || user.value?.online_shop?.name || 'Semua Lokasi' }}</p>
                    </div>
                </div>
                
                <div class="mt-8 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800/50 rounded-xl p-4">
                    <h3 class="text-amber-800 dark:text-amber-400 font-semibold mb-2 flex items-center gap-2">
                        <Key :size="18" />
                        Ubah Password / Data
                    </h3>
                    <p class="text-amber-700 dark:text-amber-500/90 text-sm leading-relaxed">
                        Sebagai akun inventory, Anda tidak dapat mengubah data profil, nama, atau password secara mandiri melalui halaman ini.
                        Silakan hubungi <strong>{{ mainAccountName }}</strong> untuk melakukan perubahan password atau informasi profil Anda.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
