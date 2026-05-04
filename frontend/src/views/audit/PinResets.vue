<script setup>
import { ref, computed, onMounted } from "vue";
import { ROLE_LABELS } from "../../utils/permissions";
import { formatDate } from "../../utils/formatters";
import { users as usersApi } from "../../api/axios";
import { useToast } from "../../composables/useToast";
import { useAuthStore } from "../../store/auth";
import {
    Users,
    Shield,
    Edit,
    Loader2,
    MapPin,
    X,
    Eye,
    EyeOff,
    AlertCircle,
    Check
} from "lucide-vue-next";

const toast = useToast();
const authStore = useAuthStore();

// State
const users = ref([]);
const isLoading = ref(false);
const isSaving = ref(false);
const showModal = ref(false);
const editingUser = ref(null);
const showPassword = ref(false);

const form = ref({
    transaction_pin: "",
});

const isReadOnlyAccess = computed(() => authStore.userRole === 'leader');

async function fetchData() {
    isLoading.value = true;
    try {
        const res = await usersApi.list({ needs_reset: 1 });
        users.value = res.data.data || [];
    } catch (error) {
        console.error("Error fetching data:", error);
        toast.error("Gagal memuat permintaan reset PIN.");
    } finally {
        isLoading.value = false;
    }
}

function openEditModal(user) {
    editingUser.value = user;
    form.value = {
        transaction_pin: "",
        password: "",
    };
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
    editingUser.value = null;
    resetForm();
}

function resetForm() {
    form.value = {
        transaction_pin: "",
        password: "",
    };
    showPassword.value = false;
}

async function approveReset(user) {
    if (!confirm(`Setujui reset PIN untuk ${user.full_name}? PIN lama akan dihapus dan user dapat membuat PIN baru sendiri.`)) {
        return;
    }

    isSaving.value = true;
    try {
        await usersApi.approvePinReset(user.id);
        toast.success(`Permintaan reset PIN untuk ${user.full_name} disetujui.`);
        fetchData(); // Refresh list
    } catch (error) {
        console.error("Approval error:", error);
        toast.error(error.response?.data?.message || "Gagal menyetujui reset PIN.");
    } finally {
        isSaving.value = false;
    }
}

function getAvatarUrl(user) {
    const photoPath = user?.photo || user?.photo_inventory;
    if (photoPath) {
        if (photoPath.startsWith('http')) return photoPath;
        const storageUrl = import.meta.env.VITE_API_BASE_URL
            ? import.meta.env.VITE_API_BASE_URL.replace(/\/api\/?$/, "")
            : 'https://api.stokps.com';
        return `${storageUrl.replace(/\/+$/, "")}/storage/${photoPath}`;
    }
    return `https://ui-avatars.com/api/?name=${encodeURIComponent(user?.full_name || 'U')}&background=3b82f6&color=fff`;
}

function getPlacementName(user) {
    if (user.branch) return user.branch.name;
    if (user.warehouse) return `Gudang: ${user.warehouse.name}`;
    if (user.online_shop) return `Online: ${user.online_shop.name}`;
    if (user.distributor) return `Dist: ${user.distributor.name}`;
    return '-';
}

function getUserRoleName(user) {
    if (!user || !user.roles || !user.roles.length) return 'No Role';
    return ROLE_LABELS[user.roles[0].name] || user.roles[0].name;
}

onMounted(() => {
    fetchData();
});
</script>

<template>
    <div class="space-y-6 animate-in">
        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-4">
            <div class="flex items-center gap-5">
                <div class="p-3 bg-amber-500/10 rounded-2xl text-amber-600 dark:text-amber-400">
                    <Shield :size="24" stroke-width="2.5" />
                </div>
                <div>
                    <h1 class="text-2xl font-black text-neutral-900 dark:text-white tracking-tight">
                        Permintaan Reset PIN
                    </h1>
                    <p class="text-sm font-medium text-neutral-500 dark:text-neutral-400">
                        Daftar staff yang memerlukan bantuan reset PIN transaksi
                    </p>
                </div>
            </div>
        </div>

        <!-- Table Section -->
        <div class="card overflow-hidden border-neutral-200/50 dark:border-neutral-800/50 shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr
                            class="bg-neutral-50/50 dark:bg-neutral-900/50 border-b border-neutral-200 dark:border-neutral-800">
                            <th
                                class="px-6 py-4 text-xs font-bold text-neutral-500 dark:text-neutral-400 uppercase tracking-widest">
                                User</th>
                            <th
                                class="px-6 py-4 text-xs font-bold text-neutral-500 dark:text-neutral-400 uppercase tracking-widest">
                                Role</th>
                            <th
                                class="px-6 py-4 text-xs font-bold text-neutral-500 dark:text-neutral-400 uppercase tracking-widest">
                                Penempatan</th>
                            <th
                                class="px-6 py-4 text-xs font-bold text-neutral-500 dark:text-neutral-400 uppercase tracking-widest">
                                Waktu Request</th>
                            <th
                                class="px-6 py-4 text-xs font-bold text-neutral-500 dark:text-neutral-400 uppercase tracking-widest text-right">
                                Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 dark:divide-neutral-800">
                        <tr v-if="isLoading">
                            <td colspan="5" class="px-6 py-12 text-center">
                                <Loader2 class="animate-spin mx-auto text-primary-500" :size="32" />
                                <p class="mt-2 text-sm text-neutral-500">Memuat data...</p>
                            </td>
                        </tr>
                        <tr v-else-if="users.length === 0">
                            <td colspan="5" class="px-6 py-12 text-center text-neutral-500 italic">
                                Tidak ada permintaan reset PIN saat ini.
                            </td>
                        </tr>
                        <tr v-for="user in users" :key="user.id"
                            class="hover:bg-neutral-50/50 dark:hover:bg-neutral-800/30 transition-colors group">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-4">
                                    <img :src="getAvatarUrl(user)"
                                        class="w-10 h-10 rounded-xl object-cover shadow-sm" />
                                    <div>
                                        <p class="font-bold text-neutral-900 dark:text-white">{{ user.full_name }}</p>
                                        <p class="text-xs text-neutral-500 font-mono">{{ user.username }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20">
                                    {{ getUserRoleName(user) }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <MapPin :size="14" class="text-neutral-400" />
                                    <span class="text-sm text-neutral-600 dark:text-neutral-300">{{
                                        getPlacementName(user) }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-col">
                                    <span class="text-sm font-medium text-red-500">{{
                                        formatDate(user.pin_reset_requested_at, 'datetime') }}</span>
                                    <span class="text-[10px] text-neutral-400 italic">Meminta bantuan reset</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button @click="approveReset(user)" :disabled="isSaving"
                                    class="btn btn-primary btn-sm px-4 rounded-xl shadow-lg shadow-primary-500/20">
                                    <Loader2 v-if="isSaving" class="animate-spin mr-2 inline" :size="14" />
                                    Setujui Reset
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

     </div>
 </template>

<style scoped>
@reference "../../style.css";

.card {
    @apply bg-white dark:bg-neutral-900 rounded-[32px] p-6 border border-neutral-200 dark:border-neutral-800;
}

.animate-in {
    animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
