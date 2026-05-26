<template>
  <Teleport to="body">
    <Transition name="fade">
      <div v-if="isOpen" class="fixed inset-0 z-[99999] flex items-end sm:items-center justify-center bg-black/60 dark:bg-black/80" @click.self="$emit('close')" style="touch-action: manipulation;">
        <div class="relative w-full sm:max-w-[420px] h-full sm:h-auto sm:max-h-[92vh] flex flex-col sm:rounded-xl overflow-hidden shadow-2xl border-t sm:border border-surface-700 bg-surface-950">
          
          <!-- Top Bar - sticky -->
          <div class="sticky top-0 z-10 flex items-center justify-between px-3 sm:px-4 py-3 sm:py-2.5 bg-surface-900 border-b border-surface-700 gap-2 shrink-0">
            <span class="text-[10px] sm:text-xs font-semibold text-text-secondary shrink-0">Bukti Penjualan</span>
            <div class="flex items-center gap-1 sm:gap-1.5 shrink-0">
              <button @click="handleCopyText" :disabled="copying"
                class="flex items-center gap-1 px-2 sm:px-2.5 py-1.5 text-[10px] sm:text-[11px] font-semibold text-white bg-primary-500 hover:bg-primary-600 rounded-md transition-colors disabled:opacity-40">
                <ClipboardCopy :size="11" />
                <span>{{ copying ? 'OK!' : 'Copas' }}</span>
              </button>
              <button @click="handleSave" :disabled="saving || loadingPhotos"
                class="flex items-center gap-1 px-2 sm:px-2.5 py-1.5 text-[10px] sm:text-[11px] font-semibold text-text-primary bg-surface-700 hover:bg-surface-600 rounded-md transition-colors disabled:opacity-40">
                <Download :size="11" />
                <span>{{ saving ? '...' : 'Simpan' }}</span>
              </button>
              <button @click="$emit('close')" class="p-1 text-text-secondary hover:text-text-primary rounded transition-colors">
                <X :size="16" />
              </button>
            </div>
          </div>

          <!-- Scrollable Content -->
          <div class="flex-1 overflow-y-auto bg-surface-950 overscroll-contain">
            <div ref="captureRef" class="p-4 sm:p-5">
              <div class="mx-auto max-w-[360px] bg-surface-800 rounded-2xl p-5 border border-surface-700">

                <!-- Proof Photos Loading -->
                <div v-if="loadingPhotos" class="flex items-center justify-center gap-2 py-6 mb-5 bg-surface-900/50 rounded-xl border border-surface-700">
                  <svg class="animate-spin h-4 w-4 text-primary-500" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                  <span class="text-xs text-text-secondary">Memuat foto bukti...</span>
                </div>

                <!-- Proof Photos -->
                <div v-if="!loadingPhotos && loadedPhotos.length > 0" class="space-y-3 mb-5">
                  <div v-for="(photo, idx) in loadedPhotos" :key="idx" 
                    class="w-full rounded-xl overflow-hidden border border-surface-700">
                    <img :src="photo" :alt="'Bukti ' + (idx + 1)" class="w-full h-auto object-cover" :data-is-base64="photo.startsWith('data:')" />
                  </div>
                </div>

                <!-- Store Name -->
                <div class="text-center mb-4">
                  <h2 class="text-base font-bold text-text-primary tracking-wide">{{ storeName }}</h2>
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
                  <p class="text-[11px] text-text-secondary">{{ formattedDate }}</p>
                  <p class="text-[10px] text-text-secondary/60 font-mono mt-0.5">{{ sale?.order_no || sale?.receipt_id }}</p>
                </div>

                <!-- Separator -->
                <div class="h-px bg-surface-700 my-4"></div>

                <!-- Customer -->
                <div class="mb-4">
                  <p class="text-[10px] font-semibold text-text-secondary uppercase tracking-wider mb-2">Customer</p>
                  <div class="flex items-center gap-2.5 bg-surface-900/50 rounded-lg px-3 py-2.5">
                    <div class="w-7 h-7 rounded-full bg-surface-700 flex items-center justify-center flex-shrink-0">
                      <UserIcon :size="12" class="text-text-secondary" />
                    </div>
                    <div class="min-w-0">
                      <p class="text-sm font-semibold text-text-primary truncate">{{ sale?.customer_name || '-' }}</p>
                      <p class="text-[11px] text-text-secondary">{{ sale?.customer_wa || sale?.customer_phone || '-' }}</p>
                    </div>
                  </div>
                </div>

                <!-- Products -->
                <div class="mb-4">
                  <p class="text-[10px] font-semibold text-text-secondary uppercase tracking-wider mb-2">Produk</p>
                  <div class="space-y-2">
                    <div v-for="(item, idx) in saleItems" :key="idx"
                      class="bg-surface-900/50 rounded-lg px-3 py-2.5 border border-surface-700">
                      <div class="flex justify-between items-start gap-2">
                        <div class="min-w-0 flex-1">
                          <p class="text-xs font-semibold text-text-primary truncate">{{ item.name }}</p>
                          <div class="flex flex-wrap items-center gap-1 mt-1">
                            <span v-if="item.brand" class="text-[10px] text-text-secondary">{{ item.brand }}</span>
                            <span v-if="item.ram || item.storage" class="text-[10px] text-text-primary font-medium">
                              {{ [item.ram, item.storage].filter(Boolean).join('/') }} GB
                            </span>
                            <span v-if="item.condition"
                              class="px-1.5 py-0.5 text-[9px] font-semibold rounded"
                              :class="item.condition === 'new' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-400' : item.condition === 'ex_ibox' ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/50 dark:text-purple-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-400'">
                              {{ item.condition === 'new' ? 'Baru' : item.condition === 'ex_ibox' ? 'Ex iBox' : 'Second' }}
                            </span>
                          </div>
                          <p v-if="item.imei && item.imei !== '-'" class="text-[10px] text-text-secondary/70 font-mono mt-1">IMEI: {{ item.imei }}</p>
                        </div>
                        <div class="text-right flex-shrink-0">
                          <p class="text-xs font-bold text-emerald-600 dark:text-emerald-400">{{ formatCurrency(item.price) }}</p>
                          <p v-if="item.qty > 1" class="text-[10px] text-text-secondary mt-0.5">x{{ item.qty }}</p>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Separator -->
                <div class="h-px bg-surface-700 my-4"></div>

                <!-- Total -->
                <div class="mb-4">
                  <div v-if="sale?.total_discount > 0" class="flex justify-between items-center mb-1.5">
                    <span class="text-[11px] text-red-500 dark:text-red-400">Diskon</span>
                    <span class="text-[11px] text-red-500 dark:text-red-400 font-semibold">-{{ formatCurrency(sale.total_discount) }}</span>
                  </div>
                  <div class="flex justify-between items-center">
                    <span class="text-xs text-text-secondary font-semibold">Total</span>
                    <span class="text-lg font-bold text-text-primary">{{ formatCurrency(sale?.grand_total) }}</span>
                  </div>
                </div>

                <!-- Status -->
                <div class="flex justify-center mb-4">
                  <span class="px-3 py-1 text-[10px] font-bold uppercase tracking-wider rounded-md"
                    :class="sale?.category === 'cancel_penjualan' 
                      ? 'bg-red-100 text-red-600 dark:bg-red-900/40 dark:text-red-400' 
                      : sale?.status === 'Lunas' 
                        ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/40 dark:text-emerald-400' 
                        : 'bg-amber-100 text-amber-600 dark:bg-amber-900/40 dark:text-amber-400'">
                    {{ sale?.category === 'cancel_penjualan' ? 'DIBATALKAN' : sale?.status || 'Lunas' }}
                  </span>
                </div>

                <!-- Notes -->
                <div v-if="sale?.notes" class="mb-4">
                  <div class="bg-surface-900/50 rounded-lg px-3 py-2.5">
                    <p class="text-[10px] font-semibold text-text-secondary uppercase tracking-wider mb-1">Catatan</p>
                    <p class="text-[11px] text-text-primary leading-relaxed">{{ sale.notes }}</p>
                  </div>
                </div>

                <!-- Footer -->
                <div class="text-center pt-2">
                  <p class="text-[9px] text-text-secondary/50 font-medium uppercase tracking-widest">{{ storeName }} • {{ formattedDateShort }}</p>
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
import { X, User as UserIcon, ClipboardCopy, Download } from 'lucide-vue-next'
import { toPng } from 'html-to-image'

const props = defineProps({
  isOpen: { type: Boolean, default: false },
  sale: { type: Object, default: null }
})

defineEmits(['close'])

const captureRef = ref(null)
const saving = ref(false)
const loadingPhotos = ref(false)
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
    case 'cancel_penjualan': return 'bg-red-100 text-red-600 dark:bg-red-900/50 dark:text-red-400'
    case 'refund': return 'bg-orange-100 text-orange-600 dark:bg-orange-900/50 dark:text-orange-400'
    case 'angkat_barang': return 'bg-amber-100 text-amber-600 dark:bg-amber-900/50 dark:text-amber-400'
    case 'tukar_tambah': return 'bg-cyan-100 text-cyan-600 dark:bg-cyan-900/50 dark:text-cyan-400'
    case 'tukar_unit': return 'bg-indigo-100 text-indigo-600 dark:bg-indigo-900/50 dark:text-indigo-400'
    case 'downgrade': return 'bg-pink-100 text-pink-600 dark:bg-pink-900/50 dark:text-pink-400'
    default: return 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/50 dark:text-emerald-400'
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
  loadingPhotos.value = true
  
  // Fetch all images in parallel
  const results = await Promise.allSettled(
    props.sale.proof_images.map(async (url) => {
      // Try proxy URL first (works on desktop, may fail on mobile)
      let fetchUrl = url
      if (url.includes('/storage/')) {
        fetchUrl = url.replace(/\/storage\//, '/api/storage-proxy/')
      }
      
      try {
        const res = await fetch(fetchUrl, { mode: 'cors' })
        if (res.ok) {
          const blob = await res.blob()
          return await new Promise(r => { const fr = new FileReader(); fr.onloadend = () => r(fr.result); fr.readAsDataURL(blob) })
        }
      } catch {}
      
      // Fallback: just use the URL directly - photo will show in modal
      // but may not appear in saved PNG on mobile due to CORS
      return url
    })
  )
  
  loadedPhotos.value = results.map((r, i) => 
    r.status === 'fulfilled' ? r.value : props.sale.proof_images[i]
  )
  loadingPhotos.value = false
}

watch(() => props.isOpen, (val) => {
  if (val && props.sale?.proof_images?.length) loadProofImages()
  else { loadedPhotos.value = []; loadingPhotos.value = false }
})

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

const handleSave = async () => {
  if (!captureRef.value) return
  saving.value = true
  try {
    // Wait for photos to finish loading
    if (loadingPhotos.value) {
      await new Promise(resolve => {
        const check = setInterval(() => {
          if (!loadingPhotos.value) { clearInterval(check); resolve() }
        }, 100)
        setTimeout(() => { clearInterval(check); resolve() }, 10000)
      })
    }

    // Before capture: temporarily hide non-base64 images to avoid CORS errors
    const imgs = captureRef.value.querySelectorAll('img')
    const hiddenImgs = []
    imgs.forEach(img => {
      const src = img.getAttribute('src') || ''
      if (src && !src.startsWith('data:') && !src.startsWith('blob:')) {
        img.style.display = 'none'
        hiddenImgs.push(img)
      }
    })

    const dataUrl = await toPng(captureRef.value, {
      quality: 0.92,
      pixelRatio: 2,
      backgroundColor: getComputedStyle(document.documentElement).getPropertyValue('--color-surface-950').trim() || '#0a0a0a',
      skipFonts: true,
    })

    // Restore hidden images
    hiddenImgs.forEach(img => { img.style.display = '' })

    // Download directly without fetch (avoids CSP issues with data: URLs)
    const byteString = atob(dataUrl.split(',')[1])
    const mimeString = dataUrl.split(',')[0].split(':')[1].split(';')[0]
    const ab = new ArrayBuffer(byteString.length)
    const ia = new Uint8Array(ab)
    for (let i = 0; i < byteString.length; i++) {
      ia[i] = byteString.charCodeAt(i)
    }
    const blob = new Blob([ab], { type: mimeString })
    const blobUrl = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = blobUrl
    a.download = `${props.sale?.order_no || props.sale?.receipt_id || 'bukti'}-penjualan.png`
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    setTimeout(() => URL.revokeObjectURL(blobUrl), 3000)
  } catch (e) {
    console.error('Save failed:', e)
    // Last resort: retry without any images
    try {
      const imgs = captureRef.value.querySelectorAll('img')
      imgs.forEach(img => { img.style.display = 'none' })
      
      const dataUrl = await toPng(captureRef.value, {
        quality: 0.92,
        pixelRatio: 2,
        backgroundColor: getComputedStyle(document.documentElement).getPropertyValue('--color-surface-950').trim() || '#0a0a0a',
        skipFonts: true,
      })
      
      imgs.forEach(img => { img.style.display = '' })
      
      const byteStr = atob(dataUrl.split(',')[1])
      const mime = dataUrl.split(',')[0].split(':')[1].split(';')[0]
      const arr = new ArrayBuffer(byteStr.length)
      const u8 = new Uint8Array(arr)
      for (let i = 0; i < byteStr.length; i++) u8[i] = byteStr.charCodeAt(i)
      const blob = new Blob([arr], { type: mime })
      const blobUrl = URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = blobUrl
      a.download = `${props.sale?.order_no || props.sale?.receipt_id || 'bukti'}-penjualan.png`
      document.body.appendChild(a)
      a.click()
      document.body.removeChild(a)
      setTimeout(() => URL.revokeObjectURL(blobUrl), 3000)
    } catch {
      alert('Gagal menyimpan. Coba screenshot manual.')
    }
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.15s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
