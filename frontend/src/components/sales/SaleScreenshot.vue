<template>
  <Teleport to="body">
    <Transition name="fade">
      <div v-if="isOpen" class="fixed inset-0 z-[99999] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" @click.self="$emit('close')">
        <div class="relative w-full max-w-[440px] max-h-[90vh] overflow-y-auto custom-scrollbar rounded-2xl bg-surface-900 shadow-2xl">
          <!-- Action Buttons (outside capture area) -->
          <div class="sticky top-0 z-10 flex items-center justify-between px-4 py-3 bg-surface-900/95 backdrop-blur-md border-b border-white/5">
            <h3 class="text-sm font-bold text-white/80">Screenshot Penjualan</h3>
            <div class="flex items-center gap-2">
              <button @click="handleDownload" :disabled="capturing"
                class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-all disabled:opacity-50">
                <Loader2 v-if="capturing" :size="14" class="animate-spin" />
                <Download v-else :size="14" />
                <span>Download</span>
              </button>
              <button @click="handleShare" :disabled="capturing"
                class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg transition-all disabled:opacity-50">
                <Share2 :size="14" />
                <span>Share</span>
              </button>
              <button @click="$emit('close')"
                class="p-1.5 text-white/60 hover:text-white hover:bg-white/10 rounded-lg transition-all">
                <X :size="18" />
              </button>
            </div>
          </div>

          <!-- Capturable Card Area -->
          <div ref="captureRef" class="bg-[#0a0a0a] px-5 py-6">
            <!-- Phone Frame -->
            <div class="mx-auto max-w-[380px] rounded-[2rem] bg-gradient-to-b from-[#1a1a2e] to-[#16213e] p-5 shadow-[0_0_60px_rgba(0,0,0,0.5)] border border-white/5">
              
              <!-- Customer Photo -->
              <div v-if="customerPhoto" class="flex justify-center mb-5">
                <div class="w-28 h-36 rounded-2xl overflow-hidden border-2 border-white/10 shadow-lg shadow-black/30">
                  <img :src="customerPhoto" alt="Customer" class="w-full h-full object-cover" crossorigin="anonymous" />
                </div>
              </div>

              <!-- Store Header -->
              <div class="text-center mb-4">
                <h2 class="text-lg font-black text-white tracking-wide uppercase">{{ sale?.branch_name || 'KASARA' }}</h2>
                <div class="w-12 h-0.5 bg-gradient-to-r from-transparent via-primary-500 to-transparent mx-auto mt-2"></div>
              </div>

              <!-- Category Badge -->
              <div class="flex justify-center mb-4">
                <span class="px-3 py-1 text-[10px] font-black uppercase tracking-wider rounded-full border"
                  :class="categoryBadgeClass">
                  {{ categoryLabel }}
                </span>
              </div>

              <!-- Date & Time -->
              <div class="text-center mb-5">
                <p class="text-xs text-white/50 font-medium">{{ formattedDate }}</p>
                <p class="text-[10px] text-white/30 font-mono mt-0.5">{{ sale?.order_no }}</p>
              </div>

              <!-- Divider -->
              <div class="border-t border-dashed border-white/10 my-4"></div>

              <!-- Customer Info -->
              <div class="mb-5">
                <p class="text-[10px] font-bold text-white/40 uppercase tracking-widest mb-2">Customer</p>
                <div class="flex items-center gap-3 bg-white/5 rounded-xl px-3 py-2.5">
                  <div class="w-8 h-8 rounded-full bg-primary-500/20 flex items-center justify-center flex-shrink-0">
                    <UserIcon :size="14" class="text-primary-400" />
                  </div>
                  <div class="min-w-0">
                    <p class="text-sm font-bold text-white truncate">{{ sale?.customer_name || '-' }}</p>
                    <p class="text-[11px] text-white/50 font-mono">{{ sale?.customer_wa || sale?.customer_phone || '-' }}</p>
                  </div>
                </div>
              </div>

              <!-- Product Details -->
              <div class="mb-5">
                <p class="text-[10px] font-bold text-white/40 uppercase tracking-widest mb-2">Produk</p>
                <div class="space-y-2">
                  <div v-for="(item, idx) in saleItems" :key="idx"
                    class="bg-white/5 rounded-xl px-3 py-3 border border-white/5">
                    <div class="flex justify-between items-start gap-2">
                      <div class="min-w-0 flex-1">
                        <p class="text-xs font-bold text-white truncate">{{ item.name }}</p>
                        <div class="flex flex-wrap items-center gap-1.5 mt-1">
                          <span v-if="item.brand" class="text-[10px] text-white/50 font-medium">{{ item.brand }}</span>
                          <span v-if="item.ram || item.storage" class="text-[10px] text-primary-400 font-bold">
                            {{ [item.ram, item.storage].filter(Boolean).join('/') }} GB
                          </span>
                          <span v-if="item.condition"
                            class="px-1.5 py-0.5 text-[9px] font-bold rounded"
                            :class="item.condition === 'new' ? 'bg-emerald-500/20 text-emerald-400' : item.condition === 'ex_ibox' ? 'bg-purple-500/20 text-purple-400' : 'bg-amber-500/20 text-amber-400'">
                            {{ item.condition === 'new' ? 'Baru' : item.condition === 'ex_ibox' ? 'Ex iBox' : 'Second' }}
                          </span>
                        </div>
                        <p v-if="item.imei && item.imei !== '-'" class="text-[10px] text-white/30 font-mono mt-1">IMEI: {{ item.imei }}</p>
                      </div>
                      <div class="text-right flex-shrink-0">
                        <p class="text-xs font-black text-emerald-400">{{ formatCurrency(item.price) }}</p>
                        <p v-if="item.qty > 1" class="text-[10px] text-white/40 mt-0.5">x{{ item.qty }}</p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Divider -->
              <div class="border-t border-dashed border-white/10 my-4"></div>

              <!-- Total Section -->
              <div class="mb-4">
                <div v-if="sale?.total_discount > 0" class="flex justify-between items-center mb-2">
                  <span class="text-[11px] text-red-400 font-medium">Diskon</span>
                  <span class="text-[11px] text-red-400 font-bold">-{{ formatCurrency(sale.total_discount) }}</span>
                </div>
                <div class="flex justify-between items-center">
                  <span class="text-xs text-white/60 font-bold uppercase tracking-wider">Grand Total</span>
                  <span class="text-lg font-black text-white">{{ formatCurrency(sale?.grand_total) }}</span>
                </div>
              </div>

              <!-- Status -->
              <div class="flex justify-center mb-4">
                <span class="px-4 py-1.5 text-[11px] font-black uppercase tracking-wider rounded-full"
                  :class="sale?.category === 'cancel_penjualan' 
                    ? 'bg-red-500/20 text-red-400 border border-red-500/30' 
                    : sale?.status === 'Lunas' 
                      ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' 
                      : 'bg-amber-500/20 text-amber-400 border border-amber-500/30'">
                  {{ sale?.category === 'cancel_penjualan' ? 'DIBATALKAN' : sale?.status || 'Lunas' }}
                </span>
              </div>

              <!-- Notes -->
              <div v-if="sale?.notes" class="mb-4">
                <div class="bg-white/5 rounded-xl px-3 py-2.5 border border-white/5">
                  <p class="text-[10px] font-bold text-white/40 uppercase tracking-widest mb-1">Catatan</p>
                  <p class="text-[11px] text-white/70 leading-relaxed">{{ sale.notes }}</p>
                </div>
              </div>

              <!-- Divider -->
              <div class="border-t border-white/5 my-4"></div>

              <!-- Watermark Footer -->
              <div class="text-center">
                <p class="text-[10px] text-white/20 font-bold uppercase tracking-[0.2em]">Screenshot Penjualan</p>
                <p class="text-[9px] text-white/10 font-mono mt-1">{{ sale?.branch_name || 'KASARA' }} • {{ formattedDateShort }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, computed } from 'vue'
import { X, Download, Share2, Loader2, User as UserIcon } from 'lucide-vue-next'
import { toPng } from 'html-to-image'

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false
  },
  sale: {
    type: Object,
    default: null
  }
})

defineEmits(['close'])

const captureRef = ref(null)
const capturing = ref(false)

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
  'event_/_sponsorship': 'Event / Sponsorship',
  event_sponsorship: 'Event / Sponsorship'
}

const categoryLabel = computed(() => {
  if (!props.sale?.category) return 'Penjualan'
  return categoryLabels[props.sale.category] || props.sale.category
})

const categoryBadgeClass = computed(() => {
  const cat = props.sale?.category
  switch (cat) {
    case 'cancel_penjualan':
      return 'bg-red-500/20 text-red-400 border-red-500/30'
    case 'refund':
      return 'bg-orange-500/20 text-orange-400 border-orange-500/30'
    case 'angkat_barang':
      return 'bg-amber-500/20 text-amber-400 border-amber-500/30'
    case 'tukar_tambah':
      return 'bg-cyan-500/20 text-cyan-400 border-cyan-500/30'
    case 'tukar_unit':
      return 'bg-indigo-500/20 text-indigo-400 border-indigo-500/30'
    case 'downgrade':
      return 'bg-pink-500/20 text-pink-400 border-pink-500/30'
    default:
      return 'bg-primary-500/20 text-primary-400 border-primary-500/30'
  }
})

const customerPhoto = computed(() => {
  if (!props.sale?.proof_images || props.sale.proof_images.length === 0) return null
  // Prefer proof_images[1] (customer photo), fallback to [0]
  return props.sale.proof_images[1] || props.sale.proof_images[0]
})

const saleItems = computed(() => {
  if (!props.sale) return []
  if (props.sale.items && props.sale.items.length > 0) return props.sale.items
  // Fallback for flat structure
  return [{
    name: props.sale.product_names || 'Produk',
    brand: '',
    ram: '',
    storage: '',
    imei: props.sale.imeis || '-',
    price: props.sale.grand_total,
    qty: props.sale.qty || 1,
    condition: ''
  }]
})

const formattedDate = computed(() => {
  if (!props.sale?.date) return '-'
  const d = new Date(props.sale.date)
  return d.toLocaleDateString('id-ID', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
})

const formattedDateShort = computed(() => {
  if (!props.sale?.date) return '-'
  const d = new Date(props.sale.date)
  return d.toLocaleDateString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric' })
})

const formatCurrency = (val) => {
  const num = parseFloat(val) || 0
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0
  }).format(num)
}

const generateImage = async () => {
  if (!captureRef.value) return null
  capturing.value = true
  try {
    const dataUrl = await toPng(captureRef.value, {
      quality: 1,
      pixelRatio: 2,
      backgroundColor: '#0a0a0a',
      cacheBust: true
    })
    return dataUrl
  } catch (err) {
    console.error('Failed to generate screenshot:', err)
    return null
  } finally {
    capturing.value = false
  }
}

const handleDownload = async () => {
  const dataUrl = await generateImage()
  if (!dataUrl) return

  const link = document.createElement('a')
  link.download = `${props.sale?.order_no || 'screenshot'}-penjualan.png`
  link.href = dataUrl
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}

const handleShare = async () => {
  const dataUrl = await generateImage()
  if (!dataUrl) return

  try {
    // Convert data URL to blob
    const res = await fetch(dataUrl)
    const blob = await res.blob()

    // Try Web Share API first
    if (navigator.share && navigator.canShare) {
      const file = new File([blob], `${props.sale?.order_no || 'screenshot'}-penjualan.png`, { type: 'image/png' })
      const shareData = { files: [file] }
      
      if (navigator.canShare(shareData)) {
        await navigator.share(shareData)
        return
      }
    }

    // Fallback: copy to clipboard
    await navigator.clipboard.write([
      new ClipboardItem({ 'image/png': blob })
    ])
    alert('Screenshot berhasil disalin ke clipboard!')
  } catch (err) {
    console.error('Share failed:', err)
    // Final fallback: just download
    handleDownload()
  }
}
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.custom-scrollbar::-webkit-scrollbar {
  width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.1);
  border-radius: 4px;
}
</style>
