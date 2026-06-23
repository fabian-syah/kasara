<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../store/auth';
import { inventory } from '../../api/axios';
import { useToast } from '../../composables/useToast';
import { ScanBarcode, User, ArrowRight, ShieldCheck } from 'lucide-vue-next';

const router = useRouter();
const authStore = useAuthStore();
const toast = useToast();

const inventoryAccounts = ref([]);
const selectedAccount = ref(null);
const scanInput = ref('');
const isLoading = ref(false);

const inputRef = ref(null);

onMounted(async () => {
  await fetchInventoryAccounts();
  if (inputRef.value) {
    inputRef.value.focus();
  }
});

async function fetchInventoryAccounts() {
  try {
    const res = await inventory.myAccounts();
    inventoryAccounts.value = res.data || [];
  } catch (error) {
    console.error('Failed to fetch inventory accounts:', error);
    toast.error('Gagal memuat daftar akun inventory.');
  }
}

function handleScan() {
  if (!selectedAccount.value) {
    toast.error('Pilih Akun Inventory (Staff) terlebih dahulu!');
    return;
  }
  
  if (!scanInput.value.trim()) {
    toast.error('Masukkan atau Scan QR Resi!');
    return;
  }

  // Navigate to actual scan page with the selected inventory account
  const receiptId = scanInput.value.trim();
  const acc = inventoryAccounts.value.find(a => a.id === selectedAccount.value);
  router.push(`/security-scan/${receiptId}?inventory_user_id=${selectedAccount.value}&security_name=${encodeURIComponent(acc?.name || '')}`);
}

// Ensure the scanner input always has focus if the user clicks around
function focusInput() {
  if (inputRef.value) {
    inputRef.value.focus();
  }
}
</script>

<template>
  <div class="p-6 max-w-3xl mx-auto min-h-[80vh] flex flex-col justify-center" @click="focusInput">
    <div class="bg-white dark:bg-[#050505] rounded-[24px] p-8 border border-neutral-200/60 dark:border-neutral-800/60 shadow-xl relative overflow-hidden">
      <!-- Background Decorations -->
      <div class="absolute -top-24 -right-24 w-48 h-48 bg-primary-500/10 rounded-full blur-3xl pointer-events-none"></div>
      <div class="absolute -bottom-24 -left-24 w-48 h-48 bg-blue-500/10 rounded-full blur-3xl pointer-events-none"></div>

      <div class="relative z-10 flex flex-col items-center">
        <div class="w-20 h-20 bg-primary-50 dark:bg-primary-900/20 text-primary-600 rounded-full flex items-center justify-center mb-6">
          <ShieldCheck :size="40" />
        </div>
        
        <h1 class="text-3xl font-bold text-text-primary mb-2 text-center">Security Scan Area</h1>
        <p class="text-text-secondary text-center mb-10 max-w-md">
          Pilih akun inventory Anda dan scan QR Code resi untuk memvalidasi barang keluar.
        </p>

        <!-- Step 1: Select Inventory Account -->
        <div class="w-full max-w-md mb-8">
          <label class="flex items-center gap-2 text-sm font-semibold text-text-primary mb-2">
            <User :size="16" class="text-primary-500" />
            Pilih Staff Inventory
          </label>
          <div class="relative">
            <select v-model="selectedAccount"
              class="w-full appearance-none bg-surface-100 dark:bg-surface-800 border border-neutral-200 dark:border-neutral-700 text-text-primary text-sm rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 block p-3.5 pr-10 transition-all font-medium">
              <option :value="null" disabled>-- Pilih Akun --</option>
              <option v-for="acc in inventoryAccounts" :key="acc.id" :value="acc.id">
                {{ acc.name }} ({{ acc.code_id }})
              </option>
            </select>
            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-text-secondary">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
              </svg>
            </div>
          </div>
        </div>

        <!-- Step 2: Scan Input -->
        <div class="w-full max-w-md mb-8">
          <label class="flex items-center gap-2 text-sm font-semibold text-text-primary mb-2">
            <ScanBarcode :size="16" class="text-blue-500" />
            Scan QR Code / Masukkan Resi
          </label>
          <form @submit.prevent="handleScan" class="relative">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
              <ScanBarcode class="w-5 h-5 text-neutral-400" />
            </div>
            <input ref="inputRef" v-model="scanInput" type="text"
              class="w-full bg-surface-100 dark:bg-surface-800 border border-neutral-200 dark:border-neutral-700 text-text-primary text-lg rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 block p-4 pl-11 transition-all placeholder:text-neutral-400 font-mono tracking-wider text-center"
              placeholder="SXXX-..." required autocomplete="off" />
            
            <button type="submit" 
              class="absolute inset-y-1.5 right-1.5 px-4 bg-primary-600 hover:bg-primary-700 text-white rounded-lg transition-all flex items-center justify-center font-semibold disabled:opacity-50"
              :disabled="!selectedAccount || !scanInput.trim()">
              Proses <ArrowRight :size="16" class="ml-1" />
            </button>
          </form>
          
          <div v-if="!selectedAccount" class="mt-3 text-xs text-amber-600 dark:text-amber-400 flex items-center justify-center gap-1.5">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            Harap pilih staff inventory sebelum melakukan scan.
          </div>
        </div>

      </div>
    </div>
  </div>
</template>
