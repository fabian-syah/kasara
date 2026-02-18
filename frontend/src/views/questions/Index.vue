<script setup>
import { ref, onMounted, computed } from 'vue';
import {
    Plus, Search, Edit2, Trash2, Save, X,
    AlertCircle, CheckCircle2, HelpCircle
} from 'lucide-vue-next';
import { useAuthStore } from '../../store/auth';
import { questions as questionsApi } from '../../api/axios';
import { useToast } from '../../composables/useToast';

const authStore = useAuthStore();
const toast = useToast();

const questions = ref([]);
const loading = ref(false);
const searchQuery = ref('');
const showModal = ref(false);
const showDeleteModal = ref(false);
const isEditing = ref(false);
const submitLoading = ref(false);

const selectedQuestion = ref(null);
const form = ref({
    category: '',
    content: ''
});

const categories = [
    'Penjualan Store',
    'Penjualan Online',
    'Barang Masuk Pindah Cabang',
    'Barang Keluar Pindah Cabang',
    'Barang Keluar Retur',
    'Barang Keluar Salah Input',
    'Barang Masuk Inventory',
    'Barang Masuk Refund',
    'Barang Masuk Angkat Barang',
    'Barang Masuk Tukar Tambah',
    'Barang Keluar Giveaway Customer',
    'Barang Keluar Hadiah',
    'Barang Keluar Brand Ambassador',
    'Barang Keluar Event / Sponsorship',
    'Barang Keluar Promo',
    'Barang Keluar Inventaris'
];

const fetchData = async () => {
    loading.value = true;
    try {
        const response = await questionsApi.list();
        questions.value = response.data;
    } catch (error) {
        console.error('Error fetching questions:', error);
        toast.error('Gagal memuat data pertanyaan');
    } finally {
        loading.value = false;
    }
};

const handleSubmit = async () => {
    if (!form.value.category || !form.value.content) {
        toast.warning('Mohon lengkapi semua field');
        return;
    }

    submitLoading.value = true;
    try {
        if (isEditing.value) {
            const response = await questionsApi.update(selectedQuestion.value.id, form.value);
            const index = questions.value.findIndex(q => q.id === selectedQuestion.value.id);
            if (index !== -1) questions.value[index] = response.data;
            toast.success('Pertanyaan berhasil diperbarui');
        } else {
            const response = await questionsApi.create(form.value);
            questions.value.unshift(response.data);
            toast.success('Pertanyaan berhasil ditambahkan');
        }
        showModal.value = false;
    } catch (error) {
        console.error('Error saving question:', error);
        toast.error('Gagal menyimpan data');
    } finally {
        submitLoading.value = false;
    }
};

const handleDelete = async () => {
    submitLoading.value = true;
    try {
        await questionsApi.delete(selectedQuestion.value.id);
        questions.value = questions.value.filter(q => q.id !== selectedQuestion.value.id);
        toast.success('Pertanyaan berhasil dihapus');
        showDeleteModal.value = false;
    } catch (error) {
        console.error('Error deleting question:', error);
        toast.error('Gagal menghapus data');
    } finally {
        submitLoading.value = false;
    }
};

onMounted(() => {
    fetchData();
});
</script>

<template>
    <div class="p-6 space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h1 class="text-2xl font-bold text-text-primary tracking-tight">Daftar Pertanyaan</h1>
                <p class="text-text-secondary mt-1">Kelola daftar pertanyaan dan kategori untuk staff</p>
            </div>
            <button @click="openCreateModal"
                class="flex items-center gap-2 px-4 py-2.5 bg-primary-600 hover:bg-primary-700 text-white rounded-xl font-medium transition-all shadow-lg shadow-primary-500/20 active:scale-95">
                <Plus :size="18" />
                <span>Tambah Pertanyaan</span>
            </button>
        </div>

        <!-- Search & Content -->
        <div class="bg-surface-800 rounded-2xl border border-surface-700 p-5 shadow-sm">
            <!-- Search Bar -->
            <div class="relative max-w-md mb-6">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary" :size="18" />
                <input v-model="searchQuery" type="text" placeholder="Cari pertanyaan atau kategori..."
                    class="w-full bg-surface-900 border border-surface-700 rounded-xl py-2.5 pl-10 pr-4 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition-all placeholder:text-text-secondary" />
            </div>

            <!-- Table/List -->
            <div v-if="loading" class="flex justify-center py-12">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-500"></div>
            </div>

            <div v-else-if="filteredQuestions.length === 0" class="text-center py-12">
                <div class="w-16 h-16 bg-surface-900 rounded-full flex items-center justify-center mx-auto mb-4">
                    <HelpCircle class="text-text-secondary" :size="32" />
                </div>
                <h3 class="text-lg font-medium text-text-primary mb-1">Belum ada pertanyaan</h3>
                <p class="text-text-secondary">Silakan tambahkan pertanyaan baru</p>
            </div>

            <div v-else class="grid gap-4">
                <div v-for="question in filteredQuestions" :key="question.id"
                    class="bg-surface-900 rounded-xl p-4 border border-surface-700 hover:border-surface-600 transition-colors flex flex-col sm:flex-row justify-between gap-4">
                    <div class="space-y-2 flex-1">
                        <span
                            class="px-2.5 py-1 rounded-lg bg-surface-800 text-text-secondary text-xs font-medium border border-surface-700">
                            {{ question.category }}
                        </span>
                        <p class="text-text-primary font-medium text-base leading-relaxed">
                            {{ question.content }}
                        </p>
                        <p class="text-xs text-text-secondary">
                            Dibuat pada: {{ new Date(question.created_at).toLocaleDateString('id-ID', {
                                day: 'numeric',
                                month: 'long', year: 'numeric'
                            }) }}
                        </p>
                    </div>

                    <div class="flex items-start gap-2">
                        <button @click="openEditModal(question)"
                            class="p-2 text-text-secondary hover:text-primary-500 hover:bg-primary-500/10 rounded-lg transition-colors"
                            title="Edit">
                            <Edit2 :size="18" />
                        </button>
                        <button @click="openDeleteModal(question)"
                            class="p-2 text-text-secondary hover:text-red-500 hover:bg-red-500/10 rounded-lg transition-colors"
                            title="Hapus">
                            <Trash2 :size="18" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showModal = false"></div>
            <div
                class="relative bg-surface-800 w-full max-w-lg rounded-2xl shadow-2xl border border-surface-700 p-6 animate-in fade-in zoom-in duration-200">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold text-text-primary">
                        {{ isEditing ? 'Edit Pertanyaan' : 'Tambah Pertanyaan' }}
                    </h3>
                    <button @click="showModal = false" class="text-text-secondary hover:text-text-primary">
                        <X :size="24" />
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-1.5">Kategori</label>
                        <select v-model="form.category"
                            class="w-full bg-surface-900 border border-surface-700 rounded-xl px-3 py-2.5 text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/50">
                            <option value="" disabled>Pilih Kategori</option>
                            <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-text-secondary mb-1.5">Isi Pertanyaan</label>
                        <textarea v-model="form.content" rows="4"
                            class="w-full bg-surface-900 border border-surface-700 rounded-xl px-3 py-2.5 text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/50 placeholder:text-text-secondary"
                            placeholder="Tuliskan pertanyaan disini..."></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-8">
                    <button @click="showModal = false"
                        class="px-4 py-2 rounded-xl text-text-secondary hover:bg-surface-700 font-medium transition-colors">
                        Batal
                    </button>
                    <button @click="handleSubmit" :disabled="submitLoading"
                        class="flex items-center gap-2 px-6 py-2 bg-primary-600 hover:bg-primary-700 disabled:opacity-50 text-white rounded-xl font-medium transition-all">
                        <div v-if="submitLoading"
                            class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
                        <span>{{ isEditing ? 'Simpan Perubahan' : 'Simpan' }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showDeleteModal = false"></div>
            <div class="relative bg-surface-800 w-full max-w-md rounded-2xl shadow-2xl border border-surface-700 p-6">
                <div class="flex flex-col items-center text-center mb-6">
                    <div class="w-16 h-16 bg-red-500/10 rounded-full flex items-center justify-center mb-4">
                        <AlertCircle class="text-red-500" :size="32" />
                    </div>
                    <h3 class="text-xl font-bold text-text-primary mb-2">Hapus Pertanyaan?</h3>
                    <p class="text-text-secondary">
                        Apakah Anda yakin ingin menghapus pertanyaan ini? Tindakan ini tidak dapat dibatalkan.
                    </p>
                </div>

                <div class="flex gap-3">
                    <button @click="showDeleteModal = false"
                        class="flex-1 py-2.5 rounded-xl bg-surface-900 hover:bg-surface-700 text-text-secondary transition-colors font-medium border border-surface-700">
                        Batal
                    </button>
                    <button @click="handleDelete" :disabled="submitLoading"
                        class="flex-1 py-2.5 rounded-xl bg-red-600 hover:bg-red-700 text-white transition-colors font-medium flex justify-center items-center gap-2">
                        <div v-if="submitLoading"
                            class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
                        <span>Hapus</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
