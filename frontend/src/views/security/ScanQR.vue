<script setup>
import { ref, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../store/auth';
import { inventory } from '../../api/axios';
import { useToast } from '../../composables/useToast';
import { ScanBarcode, User, ArrowRight, ShieldCheck, CameraOff, Search } from 'lucide-vue-next';

const router = useRouter();
const authStore = useAuthStore();
const toast = useToast();

const inventoryAccounts = ref([]);
const selectedAccount = ref(null);
const scanInput = ref('');
const isLoading = ref(false);

const html5QrCode = ref(null);
const scannerId = "reader";
const cameraError = ref(null);

onMounted(async () => {
  await fetchInventoryAccounts();
  startScanner();
});

onBeforeUnmount(() => {
  stopScanner();
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

const startScanner = async () => {
  if (isLoading.value) return;
  cameraError.value = null;

  await nextTick();

  if (html5QrCode.value) {
    try {
      if (html5QrCode.value.isScanning) {
        await html5QrCode.value.stop();
      }
      html5QrCode.value.clear();
    } catch (e) { }
    html5QrCode.value = null;
  }

  const config = {
    fps: 10,
    qrbox: (viewfinderWidth, viewfinderHeight) => {
      const size = Math.floor(Math.min(viewfinderWidth, viewfinderHeight) * 0.75);
      return { width: size, height: size };
    },
    formatsToSupport: [4, 0, 11, 10, 2, 12, 13],
    disableFlip: false, // allow flipped camera scanning
    videoConstraints: {
      facingMode: "environment"
    }
  };

  const { Html5Qrcode } = await import('html5-qrcode');
  html5QrCode.value = new Html5Qrcode(scannerId, {
    experimentalFeatures: { useBarCodeDetectorIfSupported: true },
    verbose: false
  });

  try {
    await html5QrCode.value.start(
      { facingMode: { exact: "environment" } },
      config,
      onScanSuccess,
      (err) => { }
    );
  } catch (err) {
    console.warn("Strict Start Failed, retrying...", err);
    try {
      await html5QrCode.value.start(
        { facingMode: "environment" },
        { ...config, videoConstraints: { facingMode: "environment" } },
        onScanSuccess,
        (err) => { }
      );
    } catch (fatal) {
      cameraError.value = "Kamera tidak dapat diakses. Mohon izinkan kamera atau gunakan input manual.";
    }
  }
};

const stopScanner = async () => {
  if (html5QrCode.value) {
    try {
      if (html5QrCode.value.isScanning) {
        await html5QrCode.value.stop();
      }
      html5QrCode.value.clear();
    } catch (e) {
      console.error("Gagal stop scanner:", e);
    }
  }
};

const onScanSuccess = async (decodedText) => {
  if (isLoading.value) return;

  if (decodedText.length < 5) return;

  if (!selectedAccount.value) {
    // Just return silently. The visual overlay already warns them to select an account.
    // Showing a toast here would spam the user if they point the camera at a QR code before selecting.
    return;
  }

  await stopScanner();
  scanInput.value = decodedText;

  if (navigator.vibrate) navigator.vibrate(150);

  handleScan();
};

function handleScan() {
  if (!selectedAccount.value) {
    toast.error('Pilih Akun Inventory (Staff) terlebih dahulu!');
    return;
  }

  if (!scanInput.value.trim()) {
    toast.error('Masukkan atau Scan QR Resi!');
    return;
  }

  // Ekstrak receipt ID jika hasil scan berupa URL (misal: https://stokps.com/security-scan/018APR-N61)
  let receiptId = scanInput.value.trim();
  if (receiptId.includes('/security-scan/')) {
    receiptId = receiptId.split('/security-scan/').pop();
  } else if (receiptId.includes('/track/')) {
    receiptId = receiptId.split('/track/').pop();
  } else if (receiptId.includes('/')) {
    receiptId = receiptId.split('/').pop();
  }

  // Bersihkan karakter query string jika ada
  if (receiptId.includes('?')) {
    receiptId = receiptId.split('?')[0];
  }

  const acc = inventoryAccounts.value.find(a => a.id === selectedAccount.value);
  router.push(`/security-scan/${receiptId}?inventory_user_id=${selectedAccount.value}&security_name=${encodeURIComponent(acc?.name || '')}`);
}
</script>

<template>
  <div class="p-6 max-w-3xl mx-auto min-h-[80vh] flex flex-col justify-center">
    <div
      class="bg-white dark:bg-[#050505] rounded-[24px] p-8 border border-neutral-200/60 dark:border-neutral-800/60 shadow-xl relative overflow-hidden">
      <!-- Background Decorations -->
      <div class="absolute -top-24 -right-24 w-48 h-48 bg-primary-500/10 rounded-full blur-3xl pointer-events-none">
      </div>
      <div class="absolute -bottom-24 -left-24 w-48 h-48 bg-blue-500/10 rounded-full blur-3xl pointer-events-none">
      </div>

      <div class="relative z-10 flex flex-col items-center">
        <div
          class="w-20 h-20 bg-primary-50 dark:bg-primary-900/20 text-primary-600 rounded-full flex items-center justify-center mb-6">
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

        <!-- CAMERA SCANNER BOX -->
        <div
          class="w-full max-w-md mb-8 relative rounded-2xl overflow-hidden bg-black/5 dark:bg-black/50 border border-neutral-200 dark:border-neutral-800"
          style="min-height: 300px;">
          <div id="reader" class="w-full h-full bg-black"></div>

          <!-- BLOCKED OVERLAY -->
          <div v-if="!selectedAccount"
            class="absolute inset-0 z-40 bg-black/70 backdrop-blur-sm flex flex-col items-center justify-center p-6 text-center">
            <User class="w-12 h-12 mb-3" color="white" />
            <p class="font-medium text-sm" style="color: white !important;">Pilih Staff Inventory terlebih dahulu untuk mulai scan.</p>
          </div>

          <!-- Error State inside Box -->
          <div v-if="cameraError"
            class="absolute inset-0 z-40 bg-black/90 flex flex-col items-center justify-center p-6 text-center">
            <CameraOff class="w-12 h-12 text-red-500 mb-3" />
            <p class="mb-4 text-white text-sm">{{ cameraError }}</p>
            <button @click="startScanner"
              class="btn btn-sm bg-primary-600 hover:bg-primary-700 text-white rounded-lg px-4 py-2">Coba Lagi
              Kamera</button>
          </div>
        </div>

        <!-- Step 2: Scan Input (Manual) -->
        <div class="w-full max-w-md">
          <label class="flex items-center gap-2 text-sm font-semibold text-text-primary mb-2">
            Atau Ketik Resi Manual
          </label>
          <form @submit.prevent="handleScan" class="relative">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
              <Search class="w-5 h-5 text-neutral-400" />
            </div>
            <input v-model="scanInput" type="text"
              class="w-full bg-surface-100 dark:bg-surface-800 border border-neutral-200 dark:border-neutral-700 text-text-primary text-lg rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 block p-4 pl-11 pr-24 transition-all placeholder:text-neutral-400 font-mono tracking-wider"
              placeholder="SXXX-..." autocomplete="off" />

            <button type="submit"
              class="absolute inset-y-1.5 right-1.5 px-4 bg-primary-600 hover:bg-primary-700 text-white rounded-lg transition-all flex items-center justify-center font-semibold disabled:opacity-50"
              :disabled="!selectedAccount || !scanInput.trim()">
              Proses
              <ArrowRight :size="16" class="ml-1" />
            </button>
          </form>

          <div v-if="!selectedAccount"
            class="mt-3 text-xs text-amber-600 dark:text-amber-400 flex items-center justify-center gap-1.5">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
              </path>
            </svg>
            Harap pilih staff inventory sebelum melakukan scan.
          </div>
        </div>

      </div>
    </div>
  </div>
</template>

<style scoped>
video {
  transform: none !important;
}

:deep(#reader video) {
  object-fit: cover !important;
  width: 100% !important;
  height: 300px !important;
}

:deep(#reader) {
  border: none !important;
}
</style>
