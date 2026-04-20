<script setup>
import { ref, onMounted, onBeforeUnmount, nextTick } from 'vue'
// Scanner will be imported dynamically
import { useRouter } from 'vue-router'
import { onlineShop } from '../../api/axios'
import { useToast } from '../../composables/useToast'
import { QrCode, ScanBarcode, X, CheckCircle, Package, Truck, Search, RefreshCw, CameraOff } from 'lucide-vue-next'

const router = useRouter()
const toast = useToast()

// State
const step = ref('scan')
const isLoading = ref(false)
const html5QrCode = ref(null)
const scannerId = "reader"
const cameraError = ref(null)

// Data
const scanCode = ref('')
const scanResult = ref(null)

onMounted(() => {
    startScanner()
})

onBeforeUnmount(() => {
    stopScanner()
})

const startScanner = async () => {
    if (isLoading.value) return;

    step.value = 'scan'
    scanResult.value = null
    scanCode.value = ''
    cameraError.value = null

    await nextTick()

    // 1. Force Cleanup
    if (html5QrCode.value) {
        try {
            if (html5QrCode.value.isScanning) {
                await html5QrCode.value.stop();
            }
            html5QrCode.value.clear();
        } catch (e) { }
        html5QrCode.value = null;
    }

    // 2. Config - FOCUSED RECTANGLE
    const config = {
        fps: 30,
        qrbox: (viewfinderWidth, viewfinderHeight) => {
            // Rectangular Box (Long for Barcodes)
            // 85% Width, fixed Height (~250px hard limit or 30% of height)
            const width = Math.floor(viewfinderWidth * 0.85);
            const height = Math.min(Math.floor(viewfinderHeight * 0.4), 250);
            return { width, height };
        },
        aspectRatio: 1.0,
        formatsToSupport: [
            Html5QrcodeSupportedFormats.CODE_128, // FIRST PRIORITY (Resi)
            Html5QrcodeSupportedFormats.QR_CODE,
            Html5QrcodeSupportedFormats.EAN_13,
            Html5QrcodeSupportedFormats.EAN_8,
            Html5QrcodeSupportedFormats.CODE_39,
            Html5QrcodeSupportedFormats.UPC_A,
            Html5QrcodeSupportedFormats.UPC_E,
        ],
        disableFlip: true,
        videoConstraints: {
            facingMode: { exact: "environment" },
            width: { min: 1280, ideal: 1920 },
            height: { min: 720, ideal: 1080 },
            focusMode: "continuous"
        }
    }

    const { Html5Qrcode, Html5QrcodeSupportedFormats } = await import('html5-qrcode')
    html5QrCode.value = new Html5Qrcode(scannerId, {
        experimentalFeatures: { useBarCodeDetectorIfSupported: true },
        verbose: false
    })

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
            cameraError.value = "Gagal. Refresh browser."
        }
    }
}

const stopScanner = async () => {
    if (html5QrCode.value) {
        try {
            // Cek properti internal library untuk melihat status scanning
            if (html5QrCode.value.isScanning) {
                await html5QrCode.value.stop();
            }
            html5QrCode.value.clear();
        } catch (e) {
            console.error("Gagal stop scanner:", e);
        }
    }
}

const onScanSuccess = async (decodedText) => {
    if (isLoading.value) return;

    // Validasi panjang kode (Resi biasanya > 10 karakter)
    if (decodedText.length < 5) return;

    // 1. Langsung matikan scanner agar tidak double scan
    await stopScanner();

    // 2. Isi variable code
    scanCode.value = decodedText;

    // 3. Haptic feedback (Getar)
    if (navigator.vibrate) navigator.vibrate(150);

    // 4. Tembak API
    await handleScan();
}

const handleScan = async () => {
    const code = scanCode.value.trim();
    if (!code) return;

    isLoading.value = true
    try {
        const response = await onlineShop.scan(code)
        scanResult.value = response.data
        toast.success(response.data.type === 'order' ? 'Pesanan Ditemukan!' : 'Produk Ditemukan')
        step.value = 'result'
    } catch (error) {
        console.error(error)
        toast.error('Gagal scan / Tidak ditemukan')
        scanResult.value = null

        // Auto restart scan if failed
        setTimeout(() => { resetScan() }, 1500)
    } finally {
        isLoading.value = false
    }
}

const resetScan = async () => {
    step.value = 'scan'
    await nextTick()
    startScanner()
}

const updateStatus = async (status) => {
    if (!scanResult.value || scanResult.value.type !== 'order') return
    isLoading.value = true
    try {
        const order = scanResult.value.data
        await onlineShop.updateOrder({
            order_number: order.order_number,
            status: status,
            platform: order.platform
        })
        toast.success(`Status updated to ${status}`)
        scanResult.value.data.status = status
    } catch (error) { toast.error('Gagal update status') }
    finally { isLoading.value = false }
}
const formatCurrency = (val) => new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR", minimumFractionDigits: 0 }).format(val || 0)

</script>

<template>
    <div class="scanner-page bg-black min-h-screen text-white overflow-hidden relative font-sans">

        <!-- HEADER -->
        <div
            class="absolute top-0 left-0 w-full p-6 pt-12 z-20 bg-gradient-to-b from-black/80 to-transparent flex justify-between items-start pointer-events-none">
            <div>
                <h1 class="text-2xl font-bold flex items-center gap-2 drop-shadow-md">
                    <ScanBarcode class="w-6 h-6 text-primary-500" /> Scan Pesanan
                </h1>
                <p class="text-gray-300 text-xs mt-1">Arahkan kamera ke Barcode Resi</p>
            </div>
            <div v-if="step === 'scan'" class="animate-pulse">
                <span class="badge badge-error badge-sm">LIVE</span>
            </div>
        </div>

        <!-- SCANNER VIEW -->
        <div v-show="step === 'scan'" class="w-full h-full absolute inset-0 bg-black">
            <div id="reader" class="w-full h-full bg-black relative"></div>

            <!-- SCANNER OVERLAY -->
            <div class="absolute inset-0 pointer-events-none z-10 flex flex-col items-center justify-center">
                <!-- Dimmed Background Top -->
                <div class="flex-1 w-full bg-black/60"></div>

                <!-- Active Scan Area -->
                <div class="flex w-full h-[250px]">
                    <div class="bg-black/60 flex-1"></div>
                    <!-- The Box -->
                    <div
                        class="relative w-[85%] h-full border-2 border-white/50 rounded-xl shadow-[0_0_0_9999px_rgba(0,0,0,0.6)]">
                        <!-- Corners -->
                        <div
                            class="absolute top-0 left-0 w-8 h-8 border-t-4 border-l-4 border-primary-500 rounded-tl-xl -mt-1 -ml-1">
                        </div>
                        <div
                            class="absolute top-0 right-0 w-8 h-8 border-t-4 border-r-4 border-primary-500 rounded-tr-xl -mt-1 -mr-1">
                        </div>
                        <div
                            class="absolute bottom-0 left-0 w-8 h-8 border-b-4 border-l-4 border-primary-500 rounded-bl-xl -mb-1 -ml-1">
                        </div>
                        <div
                            class="absolute bottom-0 right-0 w-8 h-8 border-b-4 border-r-4 border-primary-500 rounded-br-xl -mb-1 -mr-1">
                        </div>

                        <!-- Scan Line -->
                        <div
                            class="absolute top-0 left-0 w-full h-1 bg-green-500 shadow-[0_0_10px_#22c55e] animate-scan-y">
                        </div>
                    </div>
                    <div class="bg-black/60 flex-1"></div>
                </div>

                <!-- Dimmed Background Bottom -->
                <div class="flex-1 w-full bg-black/60"></div>
            </div>

            <!-- MANUAL INPUT -->
            <div class="absolute bottom-6 left-0 w-full px-6 z-30">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <Search class="h-5 w-5 text-gray-400" />
                    </div>
                    <input v-model="scanCode" @keyup.enter="handleScan" type="text"
                        class="block w-full pl-12 pr-4 py-4 rounded-2xl bg-surface-800/90 border border-surface-600/50 text-white shadow-xl focus:ring-2 focus:ring-primary-500 focus:outline-none"
                        placeholder="Ketik kode manual..." />
                </div>
            </div>

            <!-- Error Retry -->
            <div v-if="cameraError"
                class="absolute inset-0 z-40 bg-black/95 flex flex-col items-center justify-center p-8 text-center">
                <CameraOff class="w-16 h-16 text-red-500 mb-4" />
                <p class="mb-6">{{ cameraError }}</p>
                <button @click="startScanner" class="btn btn-primary rounded-full">Coba Lagi</button>
            </div>
        </div>

        <!-- RESULT VIEW (Overlay) -->
        <div v-if="step === 'result'" class="absolute inset-0 z-50 bg-black/50 backdrop-blur-sm">
            <div
                class="absolute bottom-0 w-full bg-surface-900 rounded-t-3xl min-h-[50vh] max-h-[90vh] overflow-y-auto p-6 animate-in slide-in-from-bottom-5">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold flex items-center gap-2">
                        <CheckCircle class="text-green-500" /> Hasil Scan
                    </h2>
                    <button @click="resetScan" class="btn btn-circle btn-sm btn-ghost bg-surface-800">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- CONTENT -->
                <div v-if="scanResult" class="space-y-6">
                    <div v-if="scanResult.type === 'order'" class="text-center">
                        <div class="badge badge-primary mb-2">ORDER</div>
                        <h3 class="text-2xl font-bold font-mono text-primary-400 mb-1">{{ scanResult.data.order_number
                        }}</h3>
                        <p class="text-gray-400 mb-4">{{ scanResult.data.customer_name }}</p>

                        <div class="grid grid-cols-2 gap-3">
                            <button @click="updateStatus('packed')"
                                class="btn btn-lg bg-surface-800 hover:bg-surface-700 text-white border- surface-700 flex flex-col h-auto py-3 rounded-2xl">
                                <Package class="text-pink-500 mb-1" /> Packed
                            </button>
                            <button @click="updateStatus('shipped')"
                                class="btn btn-lg bg-surface-800 hover:bg-surface-700 text-white border-surface-700 flex flex-col h-auto py-3 rounded-2xl">
                                <Truck class="text-green-500 mb-1" /> Kirim
                            </button>
                        </div>
                    </div>

                    <div v-else class="text-center">
                        <div class="badge badge-secondary mb-2">PRODUK</div>
                        <h3 class="text-xl font-bold mb-2">{{ scanResult.data.name }}</h3>
                        <p class="text-2xl text-primary-500 font-bold mb-4">{{ formatCurrency(scanResult.data.price) }}
                        </p>
                        <div class="stats bg-surface-800 w-full">
                            <div class="stat place-items-center">
                                <div class="stat-title">Stok</div>
                                <div class="stat-value text-white">{{ scanResult.data.stock }}</div>
                            </div>
                        </div>
                    </div>

                    <button @click="resetScan" class="btn btn-primary w-full rounded-xl mt-4">Scan Lagi</button>
                </div>
            </div>
        </div>

        <div v-if="isLoading" class="absolute inset-0 z-[60] flex items-center justify-center bg-black/60">
            <span class="loading loading-spinner loading-lg text-primary-500"></span>
        </div>
    </div>
</template>

<style scoped>
/* Standard fixes */
video {
    transform: none !important;
}

:deep(#reader video) {
    object-fit: cover !important;
    width: 100% !important;
    height: 100% !important;
}

:deep(#reader) {
    border: none !important;
}

@keyframes scan-y {
    0% {
        top: 0;
        opacity: 0;
    }

    10% {
        opacity: 1;
    }

    90% {
        opacity: 1;
    }

    100% {
        top: 100%;
        opacity: 0;
    }
}

.animate-scan-y {
    animation: scan-y 2.5s infinite linear;
}
</style>
