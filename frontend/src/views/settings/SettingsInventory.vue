<script setup>
import { ref, onMounted, computed } from "vue";
import { useAuthStore } from "../../store/auth";
import { inventory as inventoryApi, auth as authApiApi, users as usersApi } from "../../api/axios";
import { useToast } from "../../composables/useToast";
import { 
    Key, Shield, Loader2, PlusCircle, FileText, CheckCircle2, User, Eye, EyeOff
} from "lucide-vue-next";

const authStore = useAuthStore();
const toast = useToast();

const inventoryAccounts = ref([]);
const isLoading = ref(true);

// "Buat Akun Inventory Baru" Form State
const newAccountName = ref("");
const newAccountUsername = ref("");
const newAccountPassword = ref("");
const isCreatingAccount = ref(false);

// Edit Account State
const showEditModal = ref(false);
const showPassword = ref(false);
const editAccountData = ref({
    id: null,
    name: "",
    username: "",
    password: "",
    password_confirmation: ""
});
const isUpdatingAccount = ref(false);

function openEditModal(acc) {
    editAccountData.value = {
        id: acc.id,
        name: acc.name,
        username: acc.username,
        password: "",
        password_confirmation: ""
    };
    showPassword.value = false;
    showEditModal.value = true;
}

async function submitEditAccount() {
    if (editAccountData.value.password && editAccountData.value.password !== editAccountData.value.password_confirmation) {
        toast.error("Password baru dan konfirmasi password tidak cocok!");
        return;
    }
    
    isUpdatingAccount.value = true;
    try {
        const payload = new FormData();
        payload.append('name', editAccountData.value.name);
        payload.append('username', editAccountData.value.username);
        if (editAccountData.value.password) {
            payload.append('password', editAccountData.value.password);
        }
        
        await inventoryApi.updateAccount(editAccountData.value.id, payload);
        toast.success("Akun inventory berhasil diupdate!");
        showEditModal.value = false;
        await fetchAccounts();
    } catch (e) {
        toast.error(e.response?.data?.message || "Gagal mengupdate akun.");
    } finally {
        isUpdatingAccount.value = false;
    }
}

async function fetchAccounts() {
    try {
        const invRes = await inventoryApi.myAccounts();
        inventoryAccounts.value = invRes.data.data || invRes.data;
    } catch (error) {
        console.error("Failed to fetch accounts", error);
        toast.error("Gagal memuat daftar akun.");
    }
}

onMounted(async () => {
    isLoading.value = true;
    await fetchAccounts();
    isLoading.value = false;
});

async function createInventoryAccount() {
    if (!newAccountName.value) {
        toast.error("Nama akun harus diisi!");
        return;
    }
    isCreatingAccount.value = true;
    try {
        await inventoryApi.createAccount({
            name: newAccountName.value,
            username: newAccountUsername.value || undefined,
            password: newAccountPassword.value || 'inventory123'
        });
        toast.success("Akun inventory baru berhasil dibuat!");
        newAccountName.value = "";
        newAccountUsername.value = "";
        newAccountPassword.value = "";
        
        await fetchAccounts();
    } catch (e) {
        console.error("Create account error:", e);
        const errMsg = e.response?.data?.message || "Gagal membuat akun.";
        toast.error(errMsg);
    } finally {
        isCreatingAccount.value = false;
    }
}

function cancelCreateAccount() {
    newAccountName.value = "";
    newAccountUsername.value = "";
    newAccountPassword.value = "";
}

const accountsByBranch = computed(() => {
    const grouped = {};
    inventoryAccounts.value.forEach(acc => {
        const branchName = acc.branch?.name || authStore.user?.branch?.name || 'Cabang Utama';
        if (!grouped[branchName]) grouped[branchName] = [];
        grouped[branchName].push(acc);
    });
    return grouped;
});
</script>

<template>
    <div class="space-y-6 animate-in pb-24">
        <div class="flex items-center gap-3">
            <h1 class="text-2xl font-black text-zinc-900 dark:text-white uppercase tracking-wider">Pengaturan Akun Inventory</h1>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            <!-- Left Side: Create Account Form -->
            <div class="lg:col-span-6 space-y-6">
                <!-- Create Inventory Account -->
                <div class="card bg-white dark:bg-zinc-900/90 border border-zinc-200/60 dark:border-zinc-800/70 p-6 rounded-[2rem] shadow-xl space-y-5">
                    <div class="flex items-center justify-between pb-3 border-b border-zinc-100 dark:border-zinc-800/50">
                        <div class="flex items-center gap-2.5">
                            <div class="p-2 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 rounded-lg">
                                <FileText :size="16" />
                            </div>
                            <h3 class="text-sm font-black text-zinc-800 dark:text-white uppercase tracking-wider">Buat Akun Inventory Baru</h3>
                        </div>
                        <span class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-[8px] font-black uppercase tracking-widest px-2 py-0.5 rounded-md">
                            FITUR SPESIAL
                        </span>
                    </div>

                    <p class="text-xs text-zinc-500 dark:text-zinc-400 leading-relaxed font-semibold">
                        Buat akun khusus untuk operasional gudang. Akun ini memiliki akses terbatas hanya untuk pencatatan logistik dan pergerakan stok barang.
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 pt-2">
                        <div class="space-y-1.5 sm:col-span-2">
                            <label class="label">NAMA AKUN / BAGIAN</label>
                            <input v-model="newAccountName" type="text" class="input" placeholder="Contoh: Admin Gudang 1" autocomplete="off" />
                        </div>
                        <div class="space-y-1.5">
                            <label class="label">USERNAME (Opsional)</label>
                            <input v-model="newAccountUsername" type="text" class="input" placeholder="Otomatis jika kosong" autocomplete="off" />
                        </div>
                        <div class="space-y-1.5">
                            <label class="label">KATA SANDI (Opsional)</label>
                            <input v-model="newAccountPassword" type="text" class="input" placeholder="Default: inventory123" autocomplete="off" />
                        </div>
                        
                        <div class="sm:col-span-2 flex items-center justify-end gap-3 pt-2">
                            <button @click="cancelCreateAccount" type="button" class="text-xs font-black uppercase tracking-wider text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-white px-3 py-2 transition-all">
                                Batal
                            </button>
                            <button @click="createInventoryAccount" type="button" :disabled="!newAccountName || isCreatingAccount" class="btn btn-primary px-5 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest gap-1.5 shadow-md shadow-primary-500/10">
                                <Loader2 v-if="isCreatingAccount" class="animate-spin" :size="14" />
                                <PlusCircle v-else :size="14" />
                                Daftarkan Akun
                            </button>
                        </div>
                    </div>
                </div>
                

            </div>

            <!-- Right Side: Accounts List Grouped by Branch -->
            <div class="lg:col-span-6 space-y-6">
                <div class="card bg-white dark:bg-zinc-900/90 border border-zinc-200/60 dark:border-zinc-800/70 p-6 rounded-[2rem] shadow-xl space-y-6">
                    <div class="flex items-center gap-2.5 pb-4 border-b border-zinc-100 dark:border-zinc-800/50">
                        <User :size="18" class="text-primary-500" />
                        <h3 class="text-sm font-black text-zinc-800 dark:text-white uppercase tracking-wider">Daftar Akun Inventory</h3>
                    </div>

                    <div v-if="isLoading" class="flex justify-center p-8">
                        <Loader2 class="animate-spin text-primary-500" :size="24" />
                    </div>
                    <div v-else-if="Object.keys(accountsByBranch).length === 0" class="text-xs text-zinc-500 font-bold p-4 bg-zinc-50 dark:bg-zinc-950 rounded-xl border border-zinc-200 dark:border-zinc-800 text-center">
                        Belum ada akun staff inventory.
                    </div>
                    <div v-else class="space-y-6">
                        <div v-for="(accounts, branchName) in accountsByBranch" :key="branchName">
                            <h4 class="text-xs font-black text-zinc-500 dark:text-zinc-400 uppercase tracking-widest mb-3 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-primary-500"></span>
                                {{ branchName }}
                            </h4>
                            
                            <div class="space-y-3">
                                <div v-for="acc in accounts" :key="acc.id" class="bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-850 rounded-2xl p-4 flex flex-col gap-3 shadow-inner">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                                                <Key :size="14" />
                                            </div>
                                            <div>
                                                <p class="text-[10px] font-black text-zinc-800 dark:text-white uppercase">{{ acc.name }}</p>
                                                <p class="text-[9px] font-black text-zinc-500 uppercase">{{ acc.username }}</p>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-3">
                                            <button @click="openEditModal(acc)" type="button" class="text-[9px] font-black uppercase tracking-wider text-blue-500 hover:text-blue-400 transition-colors">
                                                EDIT AKUN
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Account Modal -->
    <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-zinc-900/40 backdrop-blur-sm" @click="showEditModal = false">
        <div class="bg-white dark:bg-zinc-900 w-full max-w-sm rounded-[2rem] p-6 shadow-2xl relative border border-zinc-200 dark:border-zinc-800" @click.stop>
            <h3 class="text-sm font-black text-zinc-900 dark:text-white uppercase mb-4 tracking-wider">Edit Akun Inventory</h3>
            <div class="space-y-4">
                <div>
                    <label class="label">NAMA AKUN</label>
                    <input v-model="editAccountData.name" type="text" class="input" placeholder="Nama Akun" />
                </div>
                <div>
                    <label class="label">USERNAME</label>
                    <input v-model="editAccountData.username" type="text" class="input" placeholder="Username" />
                </div>
                <div>
                    <label class="label">PASSWORD BARU (Opsional)</label>
                    <div class="relative">
                        <input v-model="editAccountData.password" :type="showPassword ? 'text' : 'password'" class="input pr-10" placeholder="Kosongkan jika tidak diubah" />
                        <button type="button" @click="showPassword = !showPassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300">
                            <Eye v-if="!showPassword" :size="16" />
                            <EyeOff v-else :size="16" />
                        </button>
                    </div>
                </div>
                <div v-if="editAccountData.password">
                    <label class="label">KONFIRMASI PASSWORD BARU</label>
                    <div class="relative">
                        <input v-model="editAccountData.password_confirmation" :type="showPassword ? 'text' : 'password'" class="input pr-10" placeholder="Ulangi password baru" />
                    </div>
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button @click="showEditModal = false" type="button" class="text-xs font-black uppercase tracking-wider text-zinc-500 hover:text-zinc-800 px-3 py-2">
                    Batal
                </button>
                <button @click="submitEditAccount" :disabled="isUpdatingAccount" class="btn btn-primary px-5 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest gap-1.5 shadow-md shadow-primary-500/10">
                    <Loader2 v-if="isUpdatingAccount" class="animate-spin" :size="14" />
                    Simpan
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
@reference "../../style.css";

.label {
    @apply block text-[10px] font-black text-zinc-500 dark:text-zinc-400 mb-1.5 uppercase tracking-wider;
}

.input {
    @apply w-full bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 rounded-xl px-4 py-2.5 text-xs text-zinc-800 dark:text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition-all placeholder:text-zinc-400 dark:placeholder:text-text-secondary font-bold;
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
