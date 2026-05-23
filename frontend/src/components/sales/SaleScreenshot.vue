<template>
  <Teleport to="body">
    <Transition name="fade">
      <div v-if="isOpen" class="fixed inset-0 z-[99999] flex items-center justify-center p-3 sm:p-6 bg-black/90" @click.self="$emit('close')">
        <div class="relative w-full max-w-[420px] max-h-[92vh] flex flex-col rounded-xl overflow-hidden shadow-2xl">
          
          <!-- Top Bar -->
          <div class="flex items-center justify-between px-4 py-2.5 bg-neutral-900 border-b border-neutral-800">
            <span class="text-xs font-semibold text-neutral-400">Bukti Penjualan</span>
            <div class="flex items-center gap-1.5">
              <button @click="handleCopyText" :disabled="copying"
                class="flex items-center gap-1 px-2.5 py-1.5 text-[11px] font-semibold text-white bg-blue-600 hover:bg-blue-500 rounded-md transition-colors disabled:opacity-40">
                <ClipboardCopy :size="12" />
                <span>{{ copying ? 'Tersalin!' : 'Copas' }}</span>
              </button>
              <button @click="handleDownload" :disabled="capturing"
                class="flex items-center gap-1 px-2.5 py-1.5 text-[11px] font-semibold text-white bg-neutral-700 hover:bg-neutral-600 rounded-md transition-colors disabled:opacity-40">
                <Loader2 v-if="capturing" :size="12" class="animate-spin" />
                <Download v-else :size="12" />
                <span>Simpan</span>
              </button>
              <button @click="$emit('close')" class="p-1 text-neutral-500 hover:text-white rounded transition-colors">
                <X :size="16" />
              </button>
            </div>
          </div>

          <!-- Scrollable Content -->
          <div class="flex-1 overflow-y-auto bg-neutral-950">
            <!-- Capturable Area -->
            <div ref="captureRef" class="bg-neutral-950 p-4 sm:p-5">
              <div class="mx-auto max-w-[360px] bg-neutral-900 rounded-2xl p-5 border border-neutral-800">

                <!-- Proof Photos -->
                <div v-if="loadedPhotos.length > 0" class="space-y-3 mb-5">
                  <div v-for="(photo, idx) in loadedPhotos" :key="idx" 
                    class="w-full rounded-xl overflow-hidden border border-neutral-700">
                    <img :src="photo" :alt="'Bukti ' + (idx + 1)" class="w-full h-auto object-cover" />
                  </div>
                </div>

                <!-- Store Name -->
                <div class="text-center mb-4">
                  <h2 class="text-base font-bold text-white tracking-wide">{{ storeName }}</h2>
                </div>

                <!-- Category -->
                <div class="flex justify-center mb-3">
                  <span class="px-3 py-1 text-[10px] font-bold uppercase tracking-wider rounded-md"
                    :class="categoryClass">
                    {{ categoryLabel }}
                  </span>
                </div>

                <!-- Date -->
                <div class="text-center mb-4">
                  <p class="text-[11px] text-neutral-400">{{ formattedDate }}</p>
                  <p class="text-[10px] text-neutral-600 font-mono mt-0.5">{{ sale?.order_no }}</p>
                </div>

                <!-- Separator -->
                <div class="h-px bg-neutral-800 my-4"></div>

                <!-- Customer -->
                <div class="mb-4">
                  <p class="text-[10px] font-semibold text-neutral-500 uppercase tracking-wider mb-2">Customer</p>
                  <div class="flex items-center gap-2.5 bg-neutral-800/50 rounded-lg px-3 py-2.5">
                    <div class="w-7 h-7 rounded-full bg-neutral-700 flex items-center justify-center flex-shrink-0">
                      <UserIcon :size="12" class="text-neutral-400" />
                    </div>
                    <div class="min-w-0">
                      <p class="text-sm font-semibold text-white truncate">{{ sale?.customer_name || '-' }}</p>
                      <p class="text-[11px] text-neutral-500">{{ sale?.customer_wa || sale?.customer_phone || '-' }}</p>
                    </div>
                  </div>
                </div>

                <!-- Products -->
                <div class="mb-4">
                  <p class="text-[10px] font-semibold text-neutral-500 uppercase tracking-wider mb-2">Produk</p>
                  <div class="space-y-2">
                    <div v-for="(item, idx) in saleItems" :key="idx"
                      class="bg-neutral-800/50 rounded-lg px-3 py-2.5 border border-neutral-800">
                      <div class="flex justify-between items-start gap-2">
                        <div class="min-w-0 flex-1">
                          <p class="text-xs font-semibold text-white truncate">{{ item.name }}</p>
                          <div class="flex flex-wrap items-center gap-1 mt-1">
                            <span v-if="item.brand" class="text-[10px] text-neutral-500">{{ item.brand }}</span>
                            <span v-if="item.ram || item.storage" class="text-[10px] text-neutral-300 font-medium">
                              {{ [item.ram, item.storage].filter(Boolean).join('/') }} GB
                            </span>
                            <span v-if="item.condition"
                              class="px-1.5 py-0.5 text-[9px] font-semibold rounded"
                              :class="item.condition === 'new' ? 'bg-emerald-900/50 text-emerald-400' : item.condition === 'ex_ibox' ? 'bg-purple-900/50 text-purple-400' : 'bg-amber-900/50 text-amber-400'">
                              {{ item.condition === 'new' ? 'Baru' : item.condition === 'ex_ibox' ? 'Ex iBox' : 'Second' }}
                            </span>
                          </div>
                          <p v-if="item.imei && item.imei !== '-'" class="text-[10px] text-neutral-600 font-mono mt-1">IMEI: {{ item.imei }}</p>
                        </div>
                        <div class="text-right flex-shrink-0">
                          <p class="text-xs font-bold text-emerald-400">{{ formatCurrency(item.price) }}</p>
                          <p v-if="item.qty > 1" class="text-[10px] text-neutral-500 mt-0.5">x{{ item.qty }}</p>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Separator -->
                <div class="h-px bg-neutral-800 my-4"></div>

                <!-- Total -->
                <div class="mb-4">
                  <div v-if="sale?.total_discount > 0" class="flex justify-between items-center mb-1.5">
                    <span class="text-[11px] text-red-400">Diskon</span>
                    <span class="text-[11px] text-red-400 font-semibold">-{{ formatCurrency(sale.total_discount) }}</span>
                  </div>
                  <div class="flex justify-between items-center">
                    <span class="text-xs text-neutral-400 font-semibold">Total</span>
                    <span class="text-lg font-bold text-white">{{ formatCurrency(sale?.grand_total) }}</span>
                  </div>
                </div>

                <!-- Status -->
                <div class="flex justify-center mb-4">
                  <span class="px-3 py-1 text-[10px] font-bold uppercase tracking-wider rounded-md"
                    :class="sale?.category === 'cancel_penjualan' 
                      ? 'bg-red-900/40 text-red-400' 
                      : sale?.status === 'Lunas' 
                        ? 'bg-emerald-900/40 text-emerald-400' 
                        : 'bg-amber-900/40 text-amber-400'">
                    {{ sale?.category === 'cancel_penjualan' ? 'DIBATALKAN' : sale?.status || 'Lunas' }}
                  </span>
                </div>

                <!-- Notes -->
                <div v-if="sale?.notes" class="mb-4">
                  <div class="bg-neutral-800/50 rounded-lg px-3 py-2.5">
                    <p class="text-[10px] font-semibold text-neutral-500 uppercase tracking-wider mb-1">Catatan</p>
                    <p class="text-[11px] text-neutral-300 leading-relaxed">{{ sale.notes }}</p>
                  </div>
                </div>

                <!-- Footer -->
                <div class="text-center pt-2">
                  <p class="text-[9px] text-neutral-700 font-medium uppercase tracking-widest">{{ storeName }} • {{ formattedDateShort }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { X, Download, Loader2, User as UserIcon, ClipboardCopy } from 'lucide-vue-next'
import { toPng } from 'html-to-image'

const props = defineProps({
  isOpen: { type: Boolean, default: false },
  sale: { type: Object, default: null }
})

defineEmits(['close'])

const captureRef = ref(null)
const capturing = ref(false)
const loadedPhotos = ref([])

const categoryLabels = {
  penjualan: 'Penjualan',
  penjualan_offline: 'Penjualan Offline',
  penjualan_store: 'Penjualan Store',
  shopee: 'Shopee',
  orderan_online: 'Order Online',
  tukar_tambah: 'Tukar Tambah',
  angkat_barang: 'Angkat Barang',
  refund: 'Refund',
  tukar_unit: 'Tukar Unit',
  downgrade: 'Downgrade',
  cancel_penjualan: 'Dibatalkan',
  brand_ambassador: 'Brand Ambassador',
  event_sponsorship: 'Event / Sponsorship'
}

const categoryLabel = computed(() => categoryLabels[props.sale?.category] || props.sale?.category || 'Penjualan')

const storeName = computed(() => {
  if (!props.sale) return 'KASARA'
  return props.sale.branch_name 
    || props.sale.branch?.name 
    || props.sale.online_shop?.name 
    || props.sale.onlineShop?.name
    || props.sale.outlet_name
    || props.sale.user?.branch?.name
    || props.sale.inventory_user?.branch?.name
    || props.sale.inventoryUser?.branch?.name
    || 'KASARA'
})

const categoryClass = computed(() => {
  const cat = props.sale?.category
  switch (cat) {
    case 'cancel_penjualan': return 'bg-red-900/50 text-red-400'
    case 'refund': return 'bg-orange-900/50 text-orange-400'
    case 'angkat_barang': return 'bg-amber-900/50 text-amber-400'
    case 'tukar_tambah': return 'bg-cyan-900/50 text-cyan-400'
    case 'tukar_unit': return 'bg-indigo-900/50 text-indigo-400'
    case 'downgrade': return 'bg-pink-900/50 text-pink-400'
    default: return 'bg-emerald-900/50 text-emerald-400'
  }
})

const saleItems = computed(() => {
  if (!props.sale) return []
  if (props.sale.items?.length > 0) return props.sale.items
  return [{
    name: props.sale.product_names || 'Produk',
    brand: '', ram: '', storage: '',
    imei: props.sale.imeis || '-',
    price: props.sale.grand_total,
    qty: props.sale.qty || 1,
    condition: ''
  }]
})

const formattedDate = computed(() => {
  if (!props.sale?.date) return '-'
  return new Date(props.sale.date).toLocaleDateString('id-ID', {
    weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit'
  })
})

const formattedDateShort = computed(() => {
  if (!props.sale?.date) return '-'
  return new Date(props.sale.date).toLocaleDateString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric' })
})

const formatCurrency = (val) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(parseFloat(val) || 0)

// Load images as base64 to bypass CORS
const loadProofImages = async () => {
  loadedPhotos.value = []
  if (!props.sale?.proof_images?.length) return
  for (const url of props.sale.proof_images) {
    try {
      const res = await fetch(url, { mode: 'cors' })
      if (res.ok) {
        const blob = await res.blob()
        const base64 = await new Promise(r => { const fr = new FileReader(); fr.onloadend = () => r(fr.result); fr.readAsDataURL(blob) })
        loadedPhotos.value.push(base64)
      }
    } catch { loadedPhotos.value.push(url) }
  }
}

watch(() => props.isOpen, (val) => {
  if (val && props.sale?.proof_images?.length) loadProofImages()
  else loadedPhotos.value = []
})

const generateImage = async () => {
  if (!captureRef.value) return null
  capturing.value = true
  try {
    return await toPng(captureRef.value, { quality: 1, pixelRatio: 2, backgroundColor: '#0a0a0a', cacheBust: true })
  } catch (e) { console.error('Screenshot failed:', e); return null }
  finally { capturing.value = false }
}

const handleDownload = async () => {
  const url = await generateImage()
  if (!url) return
  const a = document.createElement('a')
  a.download = `${props.sale?.order_no || 'bukti'}-penjualan.png`
  a.href = url
  document.body.appendChild(a); a.click(); document.body.removeChild(a)
}

const copying = ref(false)

const handleCopyText = async () => {
  if (!props.sale) return
  
  const lines = []
  
  // Nama cabang
  lines.push(storeName.value)
  lines.push(`No. Nota: ${props.sale.order_no || props.sale.receipt_id || '-'}`)
  lines.push(`Tanggal: ${formattedDate.value}`)
  lines.push(`Kategori: ${categoryLabel.value}`)
  lines.push('')
  
  // Items
  saleItems.value.forEach((item, idx) => {
    const parts = []
    if (item.brand) parts.push(item.brand)
    parts.push(item.name)
    if (item.ram || item.storage) parts.push(`${[item.ram, item.storage].filter(Boolean).join('/')} GB`)
    if (item.imei && item.imei !== '-') parts.push(`IMEI: ${item.imei}`)
    parts.push(formatCurrency(item.price))
    if (item.condition) {
      const condLabel = item.condition === 'new' ? 'Baru' : item.condition === 'ex_ibox' ? 'Ex iBox' : 'Second'
      parts.push(`(${condLabel})`)
    }
    if (item.qty > 1) parts.push(`x${item.qty}`)
    lines.push(`${idx + 1}. ${parts.join(' | ')}`)
  })
  
  lines.push('')
  if (props.sale.total_discount > 0) {
    lines.push(`Diskon: ${formatCurrency(props.sale.total_discount)}`)
  }
  lines.push(`Total: ${formatCurrency(props.sale.grand_total)}`)
  
  // Customer
  if (props.sale.customer_name) {
    lines.push('')
    lines.push(`Customer: ${props.sale.customer_name}`)
    if (props.sale.customer_wa || props.sale.customer_phone) {
      lines.push(`WA: ${props.sale.customer_wa || props.sale.customer_phone}`)
    }
  }
  
  // Notes
  if (props.sale.notes) {
    lines.push('')
    lines.push(`Catatan: ${props.sale.notes}`)
  }
  
  const text = lines.join('\n')
  
  try {
    await navigator.clipboard.writeText(text)
    copying.value = true
    setTimeout(() => { copying.value = false }, 2000)
  } catch (e) {
    console.error('Copy failed:', e)
    // Fallback
    const ta = document.createElement('textarea')
    ta.value = text
    document.body.appendChild(ta)
    ta.select()
    document.execCommand('copy')
    document.body.removeChild(ta)
    copying.value = true
    setTimeout(() => { copying.value = false }, 2000)
  }
}
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.15s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
