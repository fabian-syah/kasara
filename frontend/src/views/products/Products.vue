<script setup>
import { ref, computed, onMounted } from "vue";
import { useInventoryStore } from "../../store/inventory";
import { formatCurrency } from "../../utils/formatters";
import { useEscapeKey } from "../../composables/useEscapeKey";
import {
  Search,
  Plus,
  Filter,
  Download,
  Edit,
  Trash2,
  Eye,
  Package,
  Tag,
  DollarSign,
  Layers,
  X,
  Save,
  Image,
  Loader2,
} from "lucide-vue-next";

const inventoryStore = useInventoryStore();

// Local state
const searchQuery = ref("");
const selectedCategory = ref("");
const showModal = ref(false);
const editingProduct = ref(null);
const isSaving = ref(false);

// Form
const form = ref({
  name: "",
  sku: "",
  category: "",
  brand: "",
  price: 0,
  stock: 0,
  minStock: 5,
  description: "",
});

onMounted(() => {
  inventoryStore.fetchProducts();
});

// Filtered products
const filteredProducts = computed(() => {
  let products = inventoryStore.products;

  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    products = products.filter(
      (p) =>
        p.name.toLowerCase().includes(query) ||
        p.sku.toLowerCase().includes(query)
    );
  }

  if (selectedCategory.value) {
    products = products.filter((p) => p.category === selectedCategory.value);
  }

  return products;
});

const categories = computed(() => inventoryStore.categories);

// Actions
function openAddModal() {
  editingProduct.value = null;
  form.value = {
    name: "",
    sku: "",
    category: "",
    brand: "",
    price: 0,
    stock: 0,
    minStock: 5,
    description: "",
  };
  showModal.value = true;
}

function openEditModal(product) {
  editingProduct.value = product;
  form.value = { ...product };
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
  editingProduct.value = null;
}

useEscapeKey(() => {
  if (showModal.value) closeModal();
});

async function saveProduct() {
  isSaving.value = true;
  try {
    if (editingProduct.value) {
      await inventoryStore.updateProduct(editingProduct.value.id, form.value);
    } else {
      await inventoryStore.addProduct(form.value);
    }
    closeModal();
  } catch (error) {
    console.error("Failed to save product", error);
  } finally {
    isSaving.value = false;
  }
}

function deleteProduct(id) {
  if (confirm("Apakah Anda yakin ingin menghapus produk ini?")) {
    inventoryStore.deleteProduct(id);
  }
}
</script>

<template>
  <div class="space-y-6 animate-in">
    <!-- Header -->
    <div class="flex justify-between items-end">
      <div>
        <h1 class="text-2xl font-bold text-white tracking-tight">Produk</h1>
        <p class="text-slate-500 mt-1">Kelola master data produk</p>
      </div>
      <button @click="openAddModal" class="btn btn-primary">
        <Plus :size="16" />
        Tambah Produk
      </button>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div class="card flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-blue-600 flex items-center justify-center">
          <Package :size="20" class="text-white" />
        </div>
        <div>
          <p class="text-slate-500 text-sm">Total Produk</p>
          <p class="text-xl font-bold text-white">
            {{ inventoryStore.totalProducts }}
          </p>
        </div>
      </div>
      <div class="card flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-violet-600 flex items-center justify-center">
          <Layers :size="20" class="text-white" />
        </div>
        <div>
          <p class="text-slate-500 text-sm">Kategori</p>
          <p class="text-xl font-bold text-white">{{ categories.length }}</p>
        </div>
      </div>
      <div class="card flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-emerald-600 flex items-center justify-center">
          <Tag :size="20" class="text-white" />
        </div>
        <div>
          <p class="text-slate-500 text-sm">Brand</p>
          <p class="text-xl font-bold text-white">8</p>
        </div>
      </div>
      <div class="card flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-amber-600 flex items-center justify-center">
          <DollarSign :size="20" class="text-white" />
        </div>
        <div>
          <p class="text-slate-500 text-sm">Nilai Total</p>
          <p class="text-xl font-bold text-white">
            {{ formatCurrency(inventoryStore.totalValue) }}
          </p>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="card">
      <div class="flex items-center gap-4">
        <div class="relative flex-1">
          <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500" :size="18" />
          <input v-model="searchQuery" type="text" placeholder="Cari produk atau SKU..." class="input pl-10" />
        </div>
        <select v-model="selectedCategory" class="input w-48">
          <option value="">Semua Kategori</option>
          <option v-for="cat in categories" :key="cat.id" :value="cat.name">
            {{ cat.name }}
          </option>
        </select>
        <button class="btn btn-secondary">
          <Download :size="16" />
          Export
        </button>
      </div>
    </div>

    <!-- Products Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
      <div v-for="product in filteredProducts" :key="product.id" class="card card-hover group">
        <!-- Image placeholder -->
        <div
          class="h-32 bg-gradient-to-br from-slate-700 to-slate-800 rounded-xl mb-4 flex items-center justify-center relative overflow-hidden">
          <Package :size="32" class="text-slate-500" />
          <div
            class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
            <button @click="openEditModal(product)" class="p-2 bg-blue-600 rounded-lg">
              <Edit :size="16" class="text-white" />
            </button>
            <button @click="deleteProduct(product.id)" class="p-2 bg-red-600 rounded-lg">
              <Trash2 :size="16" class="text-white" />
            </button>
          </div>
        </div>

        <div class="space-y-2">
          <p class="text-xs text-slate-500 font-mono">{{ product.sku }}</p>
          <h3 class="font-semibold text-white truncate">{{ product.name }}</h3>
          <p class="text-sm text-slate-500">
            {{ product.brand }} • {{ product.category }}
          </p>
          <div class="flex items-center justify-between pt-2">
            <span class="text-blue-400 font-bold">{{
              formatCurrency(product.price)
            }}</span>
            <span class="badge" :class="product.stock > product.minStock
                ? 'badge-success'
                : product.stock > 0
                  ? 'badge-warning'
                  : 'badge-danger'
              ">
              Stok: {{ product.stock }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Add/Edit Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="closeModal"></div>

        <div
          class="relative bg-slate-900 rounded-2xl border border-slate-700 w-full max-w-lg p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
          <button @click="closeModal"
            class="absolute top-4 right-4 p-2 text-slate-400 hover:text-white transition-colors">
            <X :size="20" />
          </button>

          <h3 class="text-xl font-bold text-white mb-6">
            {{ editingProduct ? "Edit Produk" : "Tambah Produk Baru" }}
          </h3>

          <form @submit.prevent="saveProduct" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div class="col-span-2">
                <label class="block text-sm font-medium text-slate-400 mb-2">Nama Produk</label>
                <input v-model="form.name" type="text" class="input" placeholder="iPhone 15 Pro Max" required />
              </div>

              <div>
                <label class="block text-sm font-medium text-slate-400 mb-2">SKU</label>
                <input v-model="form.sku" type="text" class="input font-mono" placeholder="IPH15PM" required />
              </div>

              <div>
                <label class="block text-sm font-medium text-slate-400 mb-2">Brand</label>
                <input v-model="form.brand" type="text" class="input" placeholder="Apple" required />
              </div>

              <div>
                <label class="block text-sm font-medium text-slate-400 mb-2">Kategori</label>
                <select v-model="form.category" class="input" required>
                  <option value="">Pilih Kategori</option>
                  <option v-for="cat in categories" :key="cat.id" :value="cat.name">
                    {{ cat.name }}
                  </option>
                </select>
              </div>

              <div>
                <label class="block text-sm font-medium text-slate-400 mb-2">Harga (Rp)</label>
                <input v-model.number="form.price" type="number" class="input" placeholder="21999000" required />
              </div>

              <div>
                <label class="block text-sm font-medium text-slate-400 mb-2">Stok Awal</label>
                <input v-model.number="form.stock" type="number" class="input" placeholder="10" />
              </div>

              <div>
                <label class="block text-sm font-medium text-slate-400 mb-2">Min. Stok</label>
                <input v-model.number="form.minStock" type="number" class="input" placeholder="5" />
              </div>
            </div>

            <div class="flex gap-3 pt-4">
              <button type="button" @click="closeModal" class="btn btn-secondary flex-1">
                Batal
              </button>
              <button type="submit" class="btn btn-primary flex-1 items-center justify-center gap-2" :disabled="isSaving">
                <Loader2 v-if="isSaving" :size="16" class="animate-spin" />
                <Save v-else :size="16" />
                {{ isSaving ? 'Menyimpan...' : 'Simpan' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.animate-in {
  animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>
