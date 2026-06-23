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
        fps: 30,
        qrbox: (viewfinderWidth, viewfinderHeight) => {
            const width = Math.floor(viewfinderWidth * 0.85);
            const height = Math.min(Math.floor(viewfinderHeight * 0.4), 250);
            return { width, height };
        },
        aspectRatio: 1.0,
        formatsToSupport: [ 4, 0, 11, 10, 2, 12, 13 ],
        disableFlip: true,
        videoConstraints: {
            facingMode: { exact: "environment" },
            width: { min: 1280, ideal: 1920 },
            height: { min: 720, ideal: 1080 },
            focusMode: "continuous"
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
        toast.error('Pilih Akun Inventory terlebih dahulu sebelum menscan!');
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

  const receiptId = scanInput.value.trim();
  const acc = inventoryAccounts.value.find(a => a.id === selectedAccount.value);
  router.push(`/security-scan/${receiptId}?inventory_user_id=${selectedAccount.value}&security_name=${encodeURIComponent(acc?.name || '')}`);
}
</script>

<template>
  <div class="scanner-page bg-black min-h-screen text-white overflow-hidden relative font-sans">
      
      <!-- HEADER -->
      <div class="absolute top-0 left-0 w-full p-6 pt-8 z-30 bg-gradient-to-b from-black/80 to-transparent flex flex-col justify-between items-center pointer-events-none">
          <div class="text-center w-full max-w-md pointer-events-auto">
              <h1 class="text-2xl font-bold flex items-center justify-center gap-2 drop-shadow-md mb-4">
                  <ShieldCheck class="w-6 h-6 text-primary-500" /> Security Check
              </h1>
              
              <!-- Select Inventory -->
              <div class="w-full bg-surface-900/80 backdrop-blur-md border border-surface-700/50 rounded-2xl p-4 shadow-xl">
                  <label class="flex items-center gap-2 text-sm font-semibold text-gray-200 mb-2">
                      <User :size="16" class="text-primary-400" />
                      Pilih Staff Inventory
                  </label>
                  <div class="relative">
                      <select v-model="selectedAccount"
                          class="w-full appearance-none bg-surface-800 border border-surface-600 text-white text-sm rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 block p-3.5 pr-10 transition-all font-medium">
                          <option :value="null" disabled>-- Pilih Akun --</option>
                          <option v-for="acc in inventoryAccounts" :key="acc.id" :value="acc.id">
                              {{ acc.name }} ({{ acc.code_id }})
                          </option>
                      </select>
                      <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-gray-400">
                          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                          </svg>
                      </div>
                  </div>
              </div>
          </div>
      </div>

      <!-- SCANNER VIEW -->
      <div class="w-full h-full absolute inset-0 bg-black">
          <div id="reader" class="w-full h-full bg-black relative"></div>

          <!-- SCANNER OVERLAY -->
          <div class="absolute inset-0 pointer-events-none z-10 flex flex-col items-center justify-center">
              <div class="flex-1 w-full bg-black/60"></div>
              <div class="flex w-full h-[250px]">
                  <div class="bg-black/60 flex-1"></div>
                  <!-- The Box -->
                  <div class="relative w-[85%] h-full border-2 border-white/50 rounded-xl shadow-[0_0_0_9999px_rgba(0,0,0,0.6)]">
                      <div class="absolute top-0 left-0 w-8 h-8 border-t-4 border-l-4 border-primary-500 rounded-tl-xl -mt-1 -ml-1"></div>
                      <div class="absolute top-0 right-0 w-8 h-8 border-t-4 border-r-4 border-primary-500 rounded-tr-xl -mt-1 -mr-1"></div>
                      <div class="absolute bottom-0 left-0 w-8 h-8 border-b-4 border-l-4 border-primary-500 rounded-bl-xl -mb-1 -ml-1"></div>
                      <div class="absolute bottom-0 right-0 w-8 h-8 border-b-4 border-r-4 border-primary-500 rounded-br-xl -mb-1 -mr-1"></div>
                      <!-- Scan Line -->
                      <div class="absolute top-0 left-0 w-full h-1 bg-green-500 shadow-[0_0_10px_#22c55e] animate-scan-y"></div>
                  </div>
                  <div class="bg-black/60 flex-1"></div>
              </div>
              <div class="flex-1 w-full bg-black/60"></div>
          </div>

          <!-- MANUAL INPUT -->
          <div class="absolute bottom-6 left-0 w-full px-6 z-30">
              <form @submit.prevent="handleScan" class="relative max-w-md mx-auto">
                  <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                      <Search class="h-5 w-5 text-gray-400" />
                  </div>
                  <input v-model="scanInput" type="text"
                      class="block w-full pl-12 pr-12 py-4 rounded-2xl bg-surface-800/90 backdrop-blur-md border border-surface-600/50 text-white shadow-xl focus:ring-2 focus:ring-primary-500 focus:outline-none placeholder-gray-400"
                      placeholder="Atau ketik resi manual..." />
                  <button type="submit" class="absolute inset-y-2 right-2 px-3 py-1 bg-primary-600 hover:bg-primary-700 rounded-xl text-white font-medium transition-colors disabled:opacity-50"
                          :disabled="!selectedAccount || !scanInput.trim()">
                      Proses
                  </button>
              </form>
          </div>

          <!-- Error Retry -->
          <div v-if="cameraError" class="absolute inset-0 z-40 bg-black/95 flex flex-col items-center justify-center p-8 text-center">
              <CameraOff class="w-16 h-16 text-red-500 mb-4" />
              <p class="mb-6 text-white">{{ cameraError }}</p>
              <button @click="startScanner" class="btn bg-primary-600 text-white rounded-full px-6 py-2">Coba Lagi Kamera</button>
          </div>
      </div>
  </div>
</template>

<style scoped>
video { transform: none !important; }
:deep(#reader video) { object-fit: cover !important; width: 100% !important; height: 100% !important; }
:deep(#reader) { border: none !important; }
@keyframes scan-y { 0% { top: 0; opacity: 0; } 10% { opacity: 1; } 90% { opacity: 1; } 100% { top: 100%; opacity: 0; } }
.animate-scan-y { animation: scan-y 2.5s infinite linear; }
</style>
