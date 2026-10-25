<script setup>
import { ref, onMounted, computed, reactive } from 'vue';
import {
    Plus,
    Search,
    Edit,
    Trash2,
    Globe,
    ShoppingBag,
    ExternalLink,
    CheckCircle,
    XCircle
} from 'lucide-vue-next';
import api, { onlineShop } from '../../api/axios';
import ShopModal from './ShopModal.vue';
import { useToast } from '../../composables/useToast';
const toast = useToast();
const shops = ref([]);
const loading = ref(false);
const searchQuery = ref('');

const showModal = ref(false);
const editingShop = ref(null);

const fetchShops = async () => {
    loading.value = true;
    try {
        const response = await api.get('/online-shops', { params: { include_all: 1 } });
        // Filter is handled by backend or if needed locally, but API should return online shops
        shops.value = response.data.data;
    } catch (error) {
        console.error("Failed to fetch shops", error);
        toast.error("Gagal memuat data toko online");
    } finally {
        loading.value = false;
    }
};

const handleDelete = async (id) => {
    if (!confirm('Apakah Anda yakin ingin menghapus toko ini?')) return;
    try {
        await api.delete(`/online-shops/${id}`);
        toast.success('Toko berhasil dihapus');
        fetchShops();
    } catch (error) {
        toast.error('Gagal menghapus toko');
    }
};

const filteredShops = computed(() => {
    if (!searchQuery.value) return shops.value;
    const lower = searchQuery.value.toLowerCase();
    return shops.value.filter(s =>
        s.name.toLowerCase().includes(lower) ||
        s.code.toLowerCase().includes(lower) ||
        s.platform?.toLowerCase().includes(lower)
    );
});

const openCreateModal = () => {
    editingShop.value = null;
    showModal.value = true;
};

const openEditModal = (shop) => {
    editingShop.value = { ...shop }; // Clone object
    showModal.value = true;
};

// const handleDelete = async (id) => {
//     if (!confirm('Apakah Anda yakin ingin menghapus toko ini?')) return;

//     try {
//         await api.delete(`/branches/${id}`);
//         toast.success('Toko berhasil dihapus');
//         fetchShops();
//     } catch (error) {
//         toast.error('Gagal menghapus toko');
//     }
// };

const handleSaved = () => {
    showModal.value = false;
    fetchShops();
};

onMounted(fetchShops);
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h1 class="text-2xl font-bold text-text-primary tracking-tight">Toko Online</h1>
                <p class="text-text-secondary mt-1">Kelola integrasi marketplace dan toko online</p>
            </div>
            <button @click="openCreateModal"
                class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2.5 rounded-xl font-medium flex items-center gap-2 transition-all shadow-lg shadow-primary-500/20 active:scale-95">
                <Plus :size="20" />
                <span>Tambah Toko</span>
            </button>
        </div>

        <!-- Filter & Search -->
        <div class="bg-surface-800 rounded-2xl border border-surface-700 p-4">
            <div class="relative max-w-md">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary" :size="18" />
                <input v-model="searchQuery" type="text" placeholder="Cari toko, platform, atau kode..."
                    class="w-full bg-surface-900 border border-surface-700 rounded-xl py-2.5 pl-10 pr-4 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition-all" />
            </div>
        </div>

        <!-- Content -->
        <div v-if="loading" class="flex justify-center py-12">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-500"></div>
        </div>

        <div v-else-if="filteredShops.length === 0" class="flex flex-col items-center justify-center py-16 text-center">
            <div
                class="w-16 h-16 bg-surface-800 rounded-full flex items-center justify-center mb-4 text-text-secondary">
                <ShoppingBag :size="32" />
            </div>
            <h3 class="text-lg font-semibold text-text-primary mb-1">Belum ada Toko Online</h3>
            <p class="text-text-secondary max-w-xs mx-auto mb-6">Tambahkan toko online pertama Anda untuk mulai
                mengelola pesanan.</p>
            <button @click="openCreateModal" class="text-primary-500 font-medium hover:text-primary-400">
                + Tambah Toko Baru
            </button>
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div v-for="shop in filteredShops" :key="shop.id"
                class="group bg-surface-800 rounded-2xl border border-surface-700 p-5 hover:border-primary-500/30 transition-all hover:shadow-xl hover:shadow-primary-500/5">
                <div class="flex justify-between items-start mb-4">
                    <div class="flex items-center gap-3">
                        <div
                            class="w-12 h-12 rounded-xl bg-surface-900 flex items-center justify-center border border-surface-700 text-2xl">
                            <!-- Simple Icon Logic based on platform -->
                            <span v-if="shop.platform?.toLowerCase().includes('shopee')"
                                class="text-orange-500">S</span>
                            <span v-else-if="shop.platform?.toLowerCase().includes('tiktok')"
                                class="text-black dark:text-white">T</span>
                            <Globe v-else :size="24" class="text-primary-500" />
                        </div>
                        <div>
                            <h3 class="font-bold text-text-primary group-hover:text-primary-500 transition-colors">{{
                                shop.name }}</h3>
                            <p class="text-xs text-text-secondary uppercase tracking-wider font-semibold">{{
                                shop.platform || 'Unknown Platform' }}</p>
                        </div>
                    </div>
                    <div class="flex gap-1">
                        <button @click="openEditModal(shop)"
                            class="p-2 text-text-secondary hover:text-primary-500 hover:bg-surface-700 rounded-lg transition-colors">
                            <Edit :size="16" />
                        </button>
                        <button @click="handleDelete(shop.id)"
                            class="p-2 text-text-secondary hover:text-red-500 hover:bg-red-500/10 rounded-lg transition-colors">
                            <Trash2 :size="16" />
                        </button>
                    </div>
                </div>

                <div class="space-y-3 mb-5">
                    <div class="flex justify-between text-sm">
                        <span class="text-text-secondary">Kode ID</span>
                        <span class="text-text-primary font-mono bg-surface-900 px-2 py-0.5 rounded text-xs">{{
                            shop.code }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-text-secondary">Status API</span>
                        <span class="flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium"
                            :class="shop.api_key ? 'bg-green-500/10 text-green-500' : 'bg-red-500/10 text-red-500'">
                            <component :is="shop.api_key ? CheckCircle : XCircle" :size="12" />
                            {{ shop.api_key ? 'Connected' : 'Not Configured' }}
                        </span>
                    </div>
                    <div v-if="shop.url" class="flex justify-between text-sm">
                        <span class="text-text-secondary">Link Toko</span>
                        <a :href="shop.url" target="_blank"
                            class="text-primary-500 hover:underline flex items-center gap-1">
                            Kunjungi
                            <ExternalLink :size="12" />
                        </a>
                    </div>
                </div>

                <div class="pt-4 border-t border-surface-700 flex justify-between items-center">
                    <span class="text-xs text-text-secondary">Terakhir update: 2m ago</span>
                    <span class="px-2 py-1 rounded-lg text-xs font-medium"
                        :class="shop.is_active ? 'bg-primary-500/10 text-primary-500' : 'bg-surface-700 text-text-secondary'">
                        {{ shop.is_active ? 'Aktif' : 'Non-Aktif' }}
                    </span>
                </div>
            </div>
        </div>

        <ShopModal :show="showModal" :shop="editingShop" @close="showModal = false" @saved="handleSaved" />
    </div>
</template>
