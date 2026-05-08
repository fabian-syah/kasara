<script setup>
import { ref, onMounted, computed } from "vue";
import { useAuthStore } from "../../store/auth";
import { users as usersApi, inventory as inventoryApi, auth as authApiApi } from "../../api/axios";
import { useToast } from "../../composables/useToast";
import { 
    User, Camera, Lock, Save, Loader2, Mail, Phone, MapPin, Shield, Key, 
    Edit2, AlertCircle, Clock, RefreshCw, FileText, Check, PlusCircle, 
    Download, Info, CheckCircle2 
} from "lucide-vue-next";
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

// "Buat Akun Inventory Baru" Form State
const newAccountName = ref("");
const newAccountPin = ref("");
const isCreatingAccount = ref(false);

async function createInventoryAccount() {
    if (!newAccountName.value) {
        toast.error("Nama akun harus diisi!");
        return;
    }
    isCreatingAccount.value = true;
    try {
        await inventoryApi.createAccount({
            name: newAccountName.value,
            transaction_pin: newAccountPin.value || null
        });
        toast.success("Akun inventory baru berhasil dibuat!");
        newAccountName.value = "";
        newAccountPin.value = "";
        
        // Refresh accounts list
        const invRes = await inventoryApi.myAccounts();
        inventoryAccounts.value = invRes.data.data || invRes.data;
        if (inventoryAccounts.value.length > 0 && !selectedAccountId.value) {
            selectedAccountId.value = inventoryAccounts.value[0].id;
        }
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
    newAccountPin.value = "";
    toast.info("Pendaftaran akun dibatalkan.");
}

// Custom Cover Photo States
const isUploadingCover = ref(false);
const coverInputRef = ref(null);
const localCoverPreview = ref(null);

const coverPhotoUrl = computed(() => {
    if (localCoverPreview.value) {
        return localCoverPreview.value;
    }
    if (user.value?.cover_photo) {
        return user.value.cover_photo.startsWith('http')
            ? user.value.cover_photo
            : `${authStore.storageBaseUrl}/storage/${user.value.cover_photo}?t=${new Date().getTime()}`;
    }
    return null;
});

function triggerCoverUpload() {
    coverInputRef.value?.click();
}

async function handleCoverChange(event) {
    const file = event.target.files[0];
    if (!file) return;

    if (file.size > 10 * 1024 * 1024) {
        toast.error("Ukuran foto cover maksimal 10MB");
        return;
    }

    // Set local preview instantly
    localCoverPreview.value = URL.createObjectURL(file);

    isUploadingCover.value = true;
    const formData = new FormData();
    formData.append("cover_photo", file);
    formData.append("_method", "PUT");

    try {
        const res = await usersApi.updateProfile(user.value.id, formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });

        toast.success("Foto cover berhasil diperbarui!");
        
        // Refresh user data from server
        const freshRes = await usersApi.get(user.value.id);
        user.value = freshRes.data.data;
        authStore.updateUserData(user.value);
        
        // Clear local preview to fallback to database URL with cache-busting timestamp
        localCoverPreview.value = null;
    } catch (error) {
        console.error("Upload cover error", error);
        toast.error(error.response?.data?.message || "Gagal mengupload foto cover.");
    } finally {
        isUploadingCover.value = false;
        event.target.value = '';
    }
}

// Realtime Login Time adjusted to Branch Timezone in 24-hour format
const lastLoginString = computed(() => {
    const date = new Date();
    let tz = 'Asia/Jakarta';
    let tzName = 'WIB';

    const userTz = user.value?.branch?.timezone || user.value?.warehouse?.timezone;
    if (userTz) {
        const utz = userTz.toUpperCase();
        if (utz === 'WITA' || userTz === 'Asia/Makassar') {
            tz = 'Asia/Makassar';
            tzName = 'WITA';
        } else if (utz === 'WIT' || userTz === 'Asia/Jayapura') {
            tz = 'Asia/Jayapura';
            tzName = 'WIT';
        } else if (utz === 'WIB' || userTz === 'Asia/Jakarta') {
            tz = 'Asia/Jakarta';
            tzName = 'WIB';
        } else {
            tz = userTz;
            if (userTz.includes('Makassar')) tzName = 'WITA';
            else if (userTz.includes('Jayapura')) tzName = 'WIT';
            else tzName = 'WIB';
        }
    }

    try {
        const timeStr = date.toLocaleTimeString('id-ID', {
            timeZone: tz,
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        });
        return `Hari Ini, ${timeStr}`;
    } catch (e) {
        return `Hari Ini, 08:45`;
    }
});

// Track modification state
const isDirty = computed(() => {
    if (!user.value) return false;
    return (form.value.email || "") !== (user.value.email || "") ||
           (form.value.phone || "") !== (user.value.phone || "") ||
           (form.value.address || "") !== (user.value.address || "") ||
           form.value.new_password !== "" ||
           form.value.confirm_password !== "";
});

function resetForm() {
    if (user.value) {
        form.value.email = user.value.email || "";
        form.value.phone = user.value.phone || "";
        form.value.address = user.value.address || "";
        form.value.new_password = "";
        form.value.confirm_password = "";
        toast.info("Perubahan profil dibatalkan!");
    }
}

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
    const action = account.pin_enabled ? 'Matikan' : 'Aktifkan';
    
    // If turning OFF, mandate PIN verification first
    if (account.pin_enabled) {
        pinModalMode.value = 'verify';
        pinModalTitle.value = `Matikan PIN ${selectedAccountId.value === 'main' ? 'Anda' : account.name}`;
        pinError.value = "";
        showPinModal.value = true;
        return;
    }

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
        if (pinModalMode.value === 'setup' && pin !== null) {
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
    <div class="space-y-6 animate-in pb-24">
        <!-- Top Banner / Cover Banner with Customizable Background and PSTORE Logo -->
        <div class="relative rounded-[2rem] overflow-hidden bg-gradient-to-r from-zinc-200 to-zinc-300 dark:from-zinc-800 dark:to-zinc-950 border border-zinc-200/60 dark:border-zinc-800/50 shadow-xl h-56 sm:h-64 md:h-76 group/cover">
            <!-- Customizable Cover Photo Background -->
            <div v-if="coverPhotoUrl" class="absolute inset-0 bg-cover bg-center transition-all duration-500" :style="{ backgroundImage: 'url(' + coverPhotoUrl + ')' }"></div>
            <!-- Blank Empty State default matching theme (glare-free dark slate) -->
            <div v-else class="absolute inset-0 bg-gradient-to-br from-zinc-850 via-zinc-900 to-zinc-950"></div>



            <!-- Change Cover Button with Recommended Size Hint -->
            <div class="absolute bottom-4 right-4 flex flex-col items-end gap-1.5">
                <button @click="triggerCoverUpload" type="button" :disabled="isUploadingCover" class="bg-white/80 dark:bg-black/60 hover:bg-white dark:hover:bg-black/80 backdrop-blur-md border border-zinc-200 dark:border-white/10 px-3 py-1.5 rounded-xl text-[10px] font-black uppercase tracking-wider text-zinc-800 dark:text-white/90 flex items-center gap-1.5 transition-all shadow-lg active:scale-95">
                    <Loader2 v-if="isUploadingCover" class="animate-spin" :size="12" />
                    <Camera v-else :size="12" />
                    {{ isUploadingCover ? 'Uploading...' : 'Custom Cover' }}
                </button>
                <span class="text-[8px] font-black text-zinc-500 dark:text-zinc-400 bg-white/70 dark:bg-black/40 backdrop-blur-[2px] px-2 py-0.5 rounded-md uppercase tracking-widest">
                    Rekomendasi: 1200x400 px (3:1), Maks 10MB
                </span>
                <input ref="coverInputRef" type="file" class="hidden" accept="image/*" @change="handleCoverChange" :disabled="isUploadingCover" />
            </div>
        </div>

        <!-- Profile Info Section Overlay -->
        <div class="relative px-4 sm:px-8 -mt-12 sm:-mt-16 mb-6 flex flex-col md:flex-row items-start md:items-end justify-between gap-6">
            <div class="flex flex-col sm:flex-row items-start sm:items-end gap-4 sm:gap-5">
                <!-- Profile Avatar -->
                <div class="relative group/avatar">
                    <div class="w-24 h-24 sm:w-28 sm:h-28 md:w-36 md:h-36 bg-gradient-to-tr from-[#ef4444] to-[#f59e0b] rounded-[1.8rem] md:rounded-[2.5rem] border-4 border-white dark:border-zinc-900 shadow-2xl flex items-center justify-center text-white text-2xl sm:text-3xl md:text-5xl font-black tracking-wider overflow-hidden">
                        <span v-if="!(user.pending_photo || user.photo)">{{ user.username ? user.username.slice(0, 2).toUpperCase() : 'GU' }}</span>
                        <img v-else :src="(user.pending_photo || user.photo).startsWith('http') ? (user.pending_photo || user.photo) : `${authStore.storageBaseUrl}/storage/${user.pending_photo || user.photo}`" class="w-full h-full object-cover" />
                    </div>
                    <label class="absolute bottom-1 right-1 bg-emerald-500 hover:bg-emerald-400 border-4 border-white dark:border-zinc-900 p-2 rounded-full cursor-pointer shadow-lg transition-all active:scale-90 flex items-center justify-center">
                        <Edit2 class="text-white" :size="13" />
                        <input type="file" class="hidden" accept="image/*" @change="handlePhotoChange" />
                    </label>
                </div>

                <!-- Profile details text -->
                <div class="mb-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-xl sm:text-2xl md:text-3xl font-black text-zinc-900 dark:text-white tracking-tight">{{ user.username || 'gudangtrial' }}</h2>
                        <span class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-[9px] font-black uppercase tracking-widest px-2 py-0.5 rounded-full">
                            {{ user.roles?.[0]?.name || 'GUDANG MASTER' }}
                        </span>
                    </div>
                    <div class="flex flex-col sm:flex-row sm:items-center gap-x-4 gap-y-1 mt-1 text-xs text-zinc-500 dark:text-zinc-400 font-medium">
                        <span class="flex items-center gap-1.5">
                            <Mail :size="12" class="text-primary-500" />
                            {{ user.email || 'adminproduk1@apexpos.com' }}
                        </span>
                        <span class="flex items-center gap-1.5">
                            <MapPin :size="12" class="text-primary-500" />
                            Jakarta, ID
                        </span>
                    </div>
                </div>
            </div>

            <!-- Header Action Buttons -->
            <div class="flex items-center gap-3 w-full md:w-auto">
                <button @click="saveProfile" type="button" :disabled="isSaving" class="btn btn-primary flex-1 md:flex-none py-2.5 px-5 rounded-xl text-xs font-black uppercase tracking-widest gap-2 shadow-lg shadow-primary-500/20 transition-all">
                    <Loader2 v-if="isSaving" class="animate-spin" :size="14" />
                    <Save v-else :size="14" />
                    Save Profile
                </button>
            </div>
        </div>

        <!-- Multi-column Responsive Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            <!-- Left Side: Personal Info & Create Sub-Account Form -->
            <div class="lg:col-span-8 space-y-6">
                <!-- Personal Info Card -->
                <div class="card bg-white dark:bg-zinc-900/90 border border-zinc-200/60 dark:border-zinc-800/70 p-6 rounded-[2rem] shadow-xl space-y-6">
                    <div class="flex items-center justify-between pb-4 border-b border-zinc-100 dark:border-zinc-800/50">
                        <div class="flex items-center gap-2.5">
                            <User :size="18" class="text-primary-500" />
                            <h3 class="text-sm font-black text-zinc-800 dark:text-white uppercase tracking-wider">Informasi Pribadi</h3>
                        </div>
                        <span class="text-[9px] font-black text-primary-500 tracking-widest uppercase">DETAIL AKUN</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div class="space-y-1.5">
                            <label class="label">Nama Lengkap</label>
                            <input v-model="form.full_name" type="text" class="input opacity-60" disabled />
                        </div>
                        <div class="space-y-1.5">
                            <label class="label">Username</label>
                            <input v-model="form.username" type="text" class="input opacity-60" disabled />
                        </div>
                        <div class="space-y-1.5">
                            <label class="label">Email Address</label>
                            <input v-model="form.email" type="email" class="input" placeholder="adminproduk1@apexpos.com" autocomplete="off" />
                        </div>
                        <div class="space-y-1.5">
                            <label class="label">Nomor Telepon</label>
                            <input v-model="form.phone" type="text" class="input" placeholder="08xx-xxxx-xxxx" autocomplete="off" />
                        </div>
                        <div class="sm:col-span-2 space-y-1.5">
                            <label class="label">Alamat Lengkap</label>
                            <textarea v-model="form.address" class="input min-h-[100px] resize-none" placeholder="Masukkan alamat lengkap..."></textarea>
                        </div>
                    </div>
                </div>

                <!-- Create Inventory Account (Special Feature Card) -->
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
                        <div class="space-y-1.5">
                            <label class="label">NAMA AKUN / BAGIAN</label>
                            <input v-model="newAccountName" type="text" class="input" placeholder="Contoh: Admin Gudang 1" autocomplete="off" />
                        </div>
                        <div class="space-y-1.5">
                            <label class="label">PIN TRANSAKSI (OPSIONAL)</label>
                            <input v-model="newAccountPin" type="text" maxlength="4" style="-webkit-text-security: disc; -moz-text-security: disc; text-security: disc;" class="input tracking-[0.5em] font-mono" placeholder="••••" autocomplete="off" />
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

            <!-- Right Side: Security & Account Info -->
            <div class="lg:col-span-4 space-y-6">
                <!-- Security Card -->
                <div class="card bg-white dark:bg-zinc-900/90 border border-zinc-200/60 dark:border-zinc-800/70 p-6 rounded-[2rem] shadow-xl space-y-6">
                    <div class="flex items-center gap-2.5 pb-4 border-b border-zinc-100 dark:border-zinc-800/50">
                        <Shield :size="18" class="text-primary-500" />
                        <h3 class="text-sm font-black text-zinc-800 dark:text-white uppercase tracking-wider">Keamanan</h3>
                    </div>

                    <div class="space-y-4">
                        <div class="space-y-1.5">
                            <label class="label">PASSWORD BARU</label>
                            <input v-model="form.new_password" type="password" class="input" placeholder="•••••••••" autocomplete="new-password" />
                        </div>
                        <div class="space-y-1.5">
                            <label class="label">KONFIRMASI PASSWORD</label>
                            <input v-model="form.confirm_password" type="password" class="input" placeholder="•••••••••" autocomplete="new-password" />
                        </div>

                        <div class="pt-2 border-t border-zinc-100 dark:border-zinc-800/50 space-y-4">
                            <div class="space-y-1.5">
                                <label class="label">PIN TRANSAKSI</label>
                                <div v-if="inventoryAccounts.length > 0" class="relative">
                                    <select v-model="selectedAccountId" class="input font-bold text-xs uppercase tracking-wider">
                                        <option v-for="acc in inventoryAccounts" :key="acc.id" :value="acc.id">Staff: {{ acc.name }}</option>
                                    </select>
                                </div>
                                <div v-else class="text-xs text-zinc-500 dark:text-zinc-400 font-bold p-3 bg-zinc-50 dark:bg-zinc-950 rounded-xl border border-zinc-200 dark:border-zinc-800 text-center">
                                    Belum ada akun staff inventory.
                                </div>
                            </div>

                            <!-- PIN Status Card -->
                            <div v-if="inventoryAccounts.length > 0" class="bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-850 rounded-2xl p-4 flex items-center justify-between shadow-inner">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                                        <Key :size="14" />
                                    </div>
                                    <div>
                                        <p class="text-[9px] font-black text-zinc-400 uppercase">Status PIN</p>
                                        <p class="text-xs font-black uppercase" :class="selectedAccount.pin_enabled ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-400'">
                                            {{ selectedAccount.pin_enabled ? 'PIN Aktif' : 'PIN Nonaktif' }}
                                        </p>
                                    </div>
                                </div>

                                <button @click="handlePinToggle" type="button" class="text-[10px] font-black uppercase tracking-wider text-primary-500 hover:text-primary-400 transition-colors">
                                    {{ selectedAccount.pin_enabled ? 'MATIKAN' : 'AKTIFKAN SEKARANG' }}
                                </button>
                            </div>
                        </div>

                        <!-- Update Security Settings Button -->
                        <button @click="saveProfile" type="button" :disabled="isSaving" class="btn bg-zinc-50 hover:bg-zinc-100 dark:bg-zinc-950 dark:hover:bg-zinc-900 text-zinc-700 dark:text-white border border-zinc-200 dark:border-zinc-800 w-full py-2.5 rounded-xl text-xs font-black uppercase tracking-widest mt-2">
                            <Loader2 v-if="isSaving" class="animate-spin mr-2" :size="14" />
                            Update Security Settings
                        </button>
                    </div>
                </div>

                <!-- Account Info Card -->
                <div class="card bg-white dark:bg-zinc-900/90 border border-zinc-200/60 dark:border-zinc-800/70 p-6 rounded-[2rem] shadow-xl space-y-4">
                    <div class="flex items-center gap-2.5 pb-4 border-b border-zinc-100 dark:border-zinc-800/50">
                        <Info :size="18" class="text-primary-500" />
                        <h3 class="text-sm font-black text-zinc-800 dark:text-white uppercase tracking-wider">Informasi Akun</h3>
                    </div>

                    <div class="space-y-3 text-xs font-semibold">
                        <div class="flex justify-between items-center py-1">
                            <span class="text-zinc-500 dark:text-zinc-400">Status:</span>
                            <span class="text-emerald-600 dark:text-emerald-400 font-bold uppercase tracking-wide">Aktif</span>
                        </div>
                        <div class="flex justify-between items-center py-1">
                            <span class="text-zinc-500 dark:text-zinc-400">Terakhir Login:</span>
                            <span class="text-zinc-800 dark:text-white font-bold">{{ lastLoginString }}</span>
                        </div>
                        <div class="flex justify-between items-center py-1">
                            <span class="text-zinc-500 dark:text-zinc-400">Diperbarui:</span>
                            <span class="text-zinc-800 dark:text-white">{{ formatDate(user.updated_at || new Date(), 'date') }}</span>
                        </div>
                    </div>

                    <button type="button" class="btn bg-zinc-50 hover:bg-zinc-100 dark:bg-[#18181b] dark:hover:bg-zinc-800 text-zinc-700 dark:text-white w-full py-3 rounded-2xl text-xs font-black uppercase tracking-wider border-zinc-200 dark:border-zinc-700/60 shadow-md">
                        <Download :size="14" class="mr-2 text-primary-500" />
                        Unduh Data Akun
                    </button>
                </div>
            </div>
        </div>

        <!-- Sticky Bottom Bar (Shows when form is dirty) -->
        <div v-if="isDirty" class="fixed bottom-0 left-0 right-0 z-50 bg-white/95 dark:bg-zinc-900/95 backdrop-blur-md border-t border-zinc-200 dark:border-zinc-800/80 py-4 px-6 md:px-12 flex items-center justify-between shadow-2xl animate-in slide-in-from-bottom duration-300">
            <div class="flex items-center gap-2">
                <span class="relative flex h-2 w-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                </span>
                <span class="text-xs font-black tracking-widest text-emerald-600 dark:text-emerald-400 uppercase">PERUBAHAN BELUM DISIMPAN</span>
            </div>
            
            <div class="flex items-center gap-3">
                <button @click="resetForm" type="button" class="btn bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 py-2.5 px-5 rounded-xl text-xs font-black uppercase tracking-widest transition-all">
                    Reset
                </button>
                <button @click="saveProfile" type="button" :disabled="isSaving" class="btn btn-primary py-2.5 px-6 rounded-xl text-xs font-black uppercase tracking-widest gap-2 shadow-lg shadow-primary-500/25 transition-all">
                    <Loader2 v-if="isSaving" class="animate-spin" :size="14" />
                    Simpan Perubahan
                </button>
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
