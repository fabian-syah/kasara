<script setup>
import { ref, onMounted, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import api, { questions as questionsApi } from "../../api/axios";
import { useToast } from "../../composables/useToast";
import {
    Loader2, CheckCircle2, ShieldCheck, Plus, Trash2, Smartphone, Package, Box, MapPin, Calendar, Clock, ArrowRight, User
} from "lucide-vue-next";

const route = useRoute();
const router = useRouter();
const toast = useToast();

const receiptId = route.params.id;
const isLoading = ref(true);
const isSubmitting = ref(false);
const isSuccess = ref(false);

const transferData = ref(null);
const questions = ref([]);
const answers = ref({});

const securityName = ref("");
const mainNotes = ref("");

const excessItems = ref([]);

const addExcessItem = () => {
    excessItems.value.push({
        brand: "",
        type: "",
        storage: "",
        excess_qty: 1,
        notes: ""
    });
};

const removeExcessItem = (index) => {
    excessItems.value.splice(index, 1);
};

const fetchData = async () => {
    isLoading.value = true;
    try {
        // Fetch transfer details (using existing track endpoint)
        const trackRes = await api.get('/track', { params: { q: receiptId } });
        const results = trackRes.data.data || [];
        
        // Find the transfer (stock_out pindah_cabang)
        const transfer = results.find(r => r.type === 'stock_out' && r.category === 'pindah_cabang' && r.id === receiptId);
        
        if (!transfer) {
            toast.error("Surat Jalan tidak ditemukan!");
            return;
        }
        
        transferData.value = transfer;
        
        // Fetch questions
        const qRes = await questionsApi.list();
        const allQuestions = Array.isArray(qRes.data) ? qRes.data : [];
        questions.value = allQuestions.filter(q => q.category === 'security_check');
        
        // Initialize answers to null
        questions.value.forEach(q => {
            answers.value[q.id] = null;
        });
        
    } catch (e) {
        toast.error("Gagal memuat data: " + (e.response?.data?.message || e.message));
    } finally {
        isLoading.value = false;
    }
};

const submitSecurityCheck = async () => {
    if (!securityName.value.trim()) {
        toast.error("Nama Security harus diisi!");
        return;
    }

    // Check if all questions answered
    for (const q of questions.value) {
        if (answers.value[q.id] === null) {
            toast.error("Mohon jawab semua pertanyaan security!");
            return;
        }
    }

    // Prepare payload
    const payload = {
        receipt_id: receiptId,
        security_name: securityName.value,
        notes: mainNotes.value,
        answers: questions.value.map(q => ({
            question_id: q.id,
            answer: answers.value[q.id] === 'yes' ? true : false
        })),
        excess_items: excessItems.value.filter(item => item.excess_qty > 0)
    };

    isSubmitting.value = true;
    try {
        await api.post('/security-checks', payload);
        toast.success("Data berhasil disimpan!");
        isSuccess.value = true;
    } catch (e) {
        toast.error("Gagal menyimpan data: " + (e.response?.data?.message || e.message));
    } finally {
        isSubmitting.value = true;
    }
};

function formatDate(dateString) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('id-ID', {
        day: '2-digit', month: 'short', year: 'numeric'
    });
}

function formatTime(dateString) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleTimeString('id-ID', {
        hour: '2-digit', minute: '2-digit'
    });
}

onMounted(() => {
    fetchData();
});
</script>

<template>
    <div class="max-w-3xl mx-auto px-4 py-8 sm:py-12">
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="w-16 h-16 mx-auto bg-primary-500/10 rounded-2xl flex items-center justify-center mb-4 border border-primary-500/20 shadow-inner">
                <ShieldCheck :size="32" class="text-primary-500" />
            </div>
            <h1 class="text-2xl font-bold text-text-primary tracking-tight">Security Check</h1>
            <p class="text-text-secondary mt-1 text-sm">Inspeksi Pengiriman Cabang</p>
            <div class="mt-3 inline-flex items-center gap-2 px-3 py-1 bg-surface-800 rounded-lg border border-surface-700">
                <span class="text-xs text-text-secondary uppercase tracking-wider">RESI:</span>
                <span class="text-sm font-mono font-bold text-text-primary">{{ receiptId }}</span>
            </div>
        </div>

        <div v-if="isLoading" class="flex flex-col items-center py-20">
            <Loader2 :size="32" class="animate-spin text-primary-500 mb-4" />
            <p class="text-text-secondary text-sm font-medium animate-pulse">Memuat data surat jalan...</p>
        </div>

        <div v-else-if="isSuccess" class="bg-surface-800 border border-green-500/30 rounded-2xl p-8 text-center shadow-lg shadow-green-500/10">
            <div class="w-20 h-20 bg-green-500/10 rounded-full flex items-center justify-center mx-auto mb-5">
                <CheckCircle2 :size="40" class="text-green-500" />
            </div>
            <h2 class="text-2xl font-bold text-text-primary mb-2">Pengecekan Selesai</h2>
            <p class="text-text-secondary mb-6 max-w-md mx-auto">Data inspeksi security untuk surat jalan {{ receiptId }} telah berhasil disimpan ke sistem.</p>
            <button @click="router.push('/')" class="px-6 py-2.5 bg-surface-700 hover:bg-surface-600 text-text-primary rounded-xl font-medium transition-all shadow-sm">
                Kembali ke Beranda
            </button>
        </div>

        <div v-else-if="transferData" class="space-y-6">
            
            <!-- Detail Pengiriman -->
            <div class="bg-surface-800 border border-surface-700 rounded-2xl overflow-hidden shadow-sm">
                <div class="p-4 bg-surface-800/80 border-b border-surface-700 flex items-center gap-2.5">
                    <MapPin :size="18" class="text-primary-500" />
                    <h2 class="font-bold text-text-primary">Detail Pengiriman</h2>
                </div>
                <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="bg-surface-900/50 p-3 rounded-xl border border-surface-700/50">
                        <p class="text-[10px] text-text-secondary uppercase tracking-wider mb-1">Asal Cabang</p>
                        <p class="text-sm font-semibold text-text-primary">{{ transferData.source_name || '-' }}</p>
                        <p class="text-xs text-text-secondary mt-1">Pengirim: {{ transferData.processed_by || '-' }}</p>
                    </div>
                    <div class="bg-surface-900/50 p-3 rounded-xl border border-surface-700/50 relative">
                        <ArrowRight :size="16" class="absolute -left-3 top-1/2 -translate-y-1/2 text-surface-600 hidden sm:block" />
                        <p class="text-[10px] text-text-secondary uppercase tracking-wider mb-1">Tujuan Cabang</p>
                        <p class="text-sm font-semibold text-text-primary">{{ transferData.destination?.name || '-' }}</p>
                        <p class="text-xs text-text-secondary mt-1">Penerima: {{ transferData.receiver_name || '-' }}</p>
                    </div>
                    <div class="flex items-center gap-3 bg-surface-900/50 p-3 rounded-xl border border-surface-700/50">
                        <Calendar :size="16" class="text-surface-500" />
                        <div>
                            <p class="text-[10px] text-text-secondary uppercase tracking-wider mb-0.5">Tanggal</p>
                            <p class="text-xs font-semibold text-text-primary">{{ formatDate(transferData.created_at) }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 bg-surface-900/50 p-3 rounded-xl border border-surface-700/50">
                        <Clock :size="16" class="text-surface-500" />
                        <div>
                            <p class="text-[10px] text-text-secondary uppercase tracking-wider mb-0.5">Jam</p>
                            <p class="text-xs font-semibold text-text-primary">{{ formatTime(transferData.created_at) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- List Barang -->
            <div class="bg-surface-800 border border-surface-700 rounded-2xl overflow-hidden shadow-sm">
                <div class="p-4 bg-surface-800/80 border-b border-surface-700 flex items-center gap-2.5">
                    <Package :size="18" class="text-primary-500" />
                    <h2 class="font-bold text-text-primary">Daftar Barang ({{ transferData.items?.length || 0 }})</h2>
                </div>
                <div class="divide-y divide-surface-700">
                    <div v-for="(item, idx) in transferData.items" :key="idx" class="p-4 hover:bg-surface-800/50 transition-colors flex items-start gap-3">
                        <div class="p-2 rounded-lg bg-surface-700 mt-0.5 shrink-0">
                            <component :is="item.imei && item.imei !== '-' ? Smartphone : Box" :size="16" class="text-surface-400" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1 mb-1">
                                <span class="text-xs font-bold text-primary-400 uppercase tracking-wider">{{ item.brand_name || 'Brand' }}</span>
                                <h3 class="text-sm font-semibold text-text-primary">{{ item.product_name }}</h3>
                            </div>
                            
                            <div class="flex flex-wrap gap-x-4 gap-y-2 text-xs">
                                <div v-if="item.imei && item.imei !== '-'">
                                    <span class="text-text-secondary">IMEI:</span>
                                    <span class="font-mono text-text-primary ml-1 bg-surface-900 px-1.5 py-0.5 rounded">{{ item.imei }}</span>
                                </div>
                                <div v-else>
                                    <span class="text-text-secondary">Qty:</span>
                                    <span class="font-bold text-text-primary ml-1">{{ item.quantity }}</span>
                                </div>
                                <div v-if="item.condition">
                                    <span class="text-text-secondary">Kondisi:</span>
                                    <span class="text-text-primary ml-1 capitalize">{{ item.condition }}</span>
                                </div>
                                <div v-if="item.storage">
                                    <span class="text-text-secondary">Storage:</span>
                                    <span class="text-text-primary ml-1">{{ item.storage }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Pemeriksaan -->
            <div class="bg-surface-800 border border-primary-500/30 rounded-2xl overflow-hidden shadow-sm shadow-primary-500/5 relative">
                <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-primary-500 to-indigo-500"></div>
                <div class="p-5 sm:p-6 space-y-6">
                    
                    <!-- Identitas -->
                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-2 flex items-center gap-2">
                            <User :size="16"/> Nama Security <span class="text-red-500">*</span>
                        </label>
                        <input v-model="securityName" type="text" placeholder="Masukkan nama pemeriksa..."
                            class="w-full bg-surface-900 border border-surface-700 rounded-xl px-4 py-3 text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/50" />
                    </div>

                    <!-- Pertanyaan -->
                    <div v-if="questions.length > 0" class="space-y-4 pt-4 border-t border-surface-700">
                        <h3 class="font-bold text-text-primary flex items-center gap-2">
                            <ShieldCheck :size="18" class="text-primary-500"/> Daftar Pengecekan
                        </h3>
                        <div v-for="q in questions" :key="q.id" class="bg-surface-900/50 border border-surface-700 p-4 rounded-xl flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <p class="text-sm text-text-primary flex-1">{{ q.content }}</p>
                            <div class="flex items-center gap-3 shrink-0">
                                <label class="flex items-center gap-2 cursor-pointer group">
                                    <input type="radio" :name="'q_'+q.id" value="yes" v-model="answers[q.id]" class="sr-only" />
                                    <div class="w-5 h-5 rounded-full border flex items-center justify-center transition-colors"
                                        :class="answers[q.id] === 'yes' ? 'border-green-500 bg-green-500 text-white' : 'border-surface-500 bg-surface-800 group-hover:border-green-400'">
                                        <div v-if="answers[q.id] === 'yes'" class="w-2 h-2 bg-white rounded-full"></div>
                                    </div>
                                    <span class="text-sm font-medium" :class="answers[q.id] === 'yes' ? 'text-green-500' : 'text-text-secondary'">Yes</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer group">
                                    <input type="radio" :name="'q_'+q.id" value="no" v-model="answers[q.id]" class="sr-only" />
                                    <div class="w-5 h-5 rounded-full border flex items-center justify-center transition-colors"
                                        :class="answers[q.id] === 'no' ? 'border-red-500 bg-red-500 text-white' : 'border-surface-500 bg-surface-800 group-hover:border-red-400'">
                                        <div v-if="answers[q.id] === 'no'" class="w-2 h-2 bg-white rounded-full"></div>
                                    </div>
                                    <span class="text-sm font-medium" :class="answers[q.id] === 'no' ? 'text-red-500' : 'text-text-secondary'">No</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Kelebihan Barang -->
                    <div class="pt-4 border-t border-surface-700 space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="font-bold text-text-primary flex items-center gap-2">
                                    <Plus :size="18" class="text-amber-500"/> Info Kelebihan Barang
                                </h3>
                                <p class="text-[11px] text-text-secondary mt-0.5">Isi jika ada barang lebih dari nota</p>
                            </div>
                        </div>

                        <div v-if="excessItems.length === 0" class="text-center py-6 bg-surface-900/30 border border-surface-700 border-dashed rounded-xl">
                            <p class="text-sm text-text-secondary mb-3">Tidak ada barang lebih?</p>
                            <button @click="addExcessItem" type="button" class="inline-flex items-center gap-2 px-4 py-2 bg-surface-700 hover:bg-surface-600 text-text-primary rounded-lg text-sm font-medium transition-colors">
                                <Plus :size="16" /> Tambah Info Kelebihan
                            </button>
                        </div>

                        <div v-for="(item, idx) in excessItems" :key="idx" class="bg-surface-900 border border-surface-700 rounded-xl p-4 relative relative group">
                            <button @click="removeExcessItem(idx)" class="absolute -top-3 -right-3 w-8 h-8 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center shadow-lg transition-transform active:scale-95 z-10 opacity-0 group-hover:opacity-100 sm:opacity-100">
                                <Trash2 :size="14" />
                            </button>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-text-secondary mb-1">Brand</label>
                                    <input v-model="item.brand" type="text" placeholder="Contoh: Samsung" class="w-full bg-surface-800 border border-surface-700 rounded-lg px-3 py-2 text-sm text-text-primary focus:outline-none focus:border-primary-500" />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-text-secondary mb-1">Tipe Barang</label>
                                    <input v-model="item.type" type="text" placeholder="Contoh: Galaxy S24" class="w-full bg-surface-800 border border-surface-700 rounded-lg px-3 py-2 text-sm text-text-primary focus:outline-none focus:border-primary-500" />
                                </div>
                                <div class="grid grid-cols-2 gap-4 sm:col-span-2">
                                    <div>
                                        <label class="block text-xs font-medium text-text-secondary mb-1">Storage (GB)</label>
                                        <input v-model="item.storage" type="text" placeholder="Contoh: 8/256" class="w-full bg-surface-800 border border-surface-700 rounded-lg px-3 py-2 text-sm text-text-primary focus:outline-none focus:border-primary-500" />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-text-secondary mb-1">Total Unit Lebih</label>
                                        <input v-model.number="item.excess_qty" type="number" min="1" class="w-full bg-surface-800 border border-surface-700 rounded-lg px-3 py-2 text-sm text-text-primary focus:outline-none focus:border-primary-500" />
                                    </div>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-medium text-text-secondary mb-1">Keterangan / IMEI</label>
                                    <input v-model="item.notes" type="text" placeholder="Isi IMEI jika ada, atau info tambahan" class="w-full bg-surface-800 border border-surface-700 rounded-lg px-3 py-2 text-sm text-text-primary focus:outline-none focus:border-primary-500" />
                                </div>
                            </div>
                        </div>

                        <button v-if="excessItems.length > 0" @click="addExcessItem" type="button" class="w-full py-2.5 border-2 border-dashed border-surface-600 text-surface-400 hover:border-primary-500 hover:text-primary-500 rounded-xl text-sm font-medium transition-colors flex items-center justify-center gap-2">
                            <Plus :size="16" /> Tambah Barang Lain
                        </button>
                    </div>

                    <!-- Catatan Tambahan -->
                    <div class="pt-4 border-t border-surface-700">
                        <label class="block text-sm font-medium text-text-secondary mb-2">Catatan Tambahan (Opsional)</label>
                        <textarea v-model="mainNotes" rows="2" placeholder="Masukkan catatan lain jika diperlukan..."
                            class="w-full bg-surface-900 border border-surface-700 rounded-xl px-4 py-3 text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/50"></textarea>
                    </div>

                </div>
            </div>

            <!-- Submit Button -->
            <div class="pt-4 pb-8 flex justify-end">
                <button @click="submitSecurityCheck" :disabled="isSubmitting"
                    class="w-full sm:w-auto px-8 py-3.5 bg-primary-600 hover:bg-primary-500 text-white rounded-xl font-bold transition-all shadow-lg shadow-primary-500/25 active:scale-95 disabled:opacity-50 flex justify-center items-center gap-2">
                    <Loader2 v-if="isSubmitting" class="animate-spin" :size="20" />
                    <ShieldCheck v-else :size="20" />
                    <span>Selesai & Simpan Pengecekan</span>
                </button>
            </div>
        </div>

    </div>
</template>
