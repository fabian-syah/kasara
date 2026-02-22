<script setup>
import { ref, computed } from "vue";
import { useEscapeKey } from "../../composables/useEscapeKey";
import {
  Globe,
  ShoppingCart,
  Link,
  Plus,
  Search,
  Edit,
  Trash2,
  ExternalLink,
  Save,
  X,
  CheckCircle,
  XCircle,
} from "lucide-vue-next";

// Mock Data
const channels = ref([
  {
    id: 1,
    name: "Website Official",
    platform: "Custom",
    url: "https://apexpos.com",
    status: "active",
    apiKey: "sk_live_xxxxx",
  },
  {
    id: 2,
    name: "Tokopedia Official Store",
    platform: "Tokopedia",
    url: "https://tokopedia.com/apexpos",
    status: "active",
    apiKey: "os_xxxxx",
  },
  {
    id: 3,
    name: "Shopee Mall",
    platform: "Shopee",
    url: "https://shopee.co.id/apexpos",
    status: "inactive",
    apiKey: "part_xxxxx",
  },
]);

const platforms = [
  "Custom",
  "Tokopedia",
  "Shopee",
  "TikTok Shop",
  "Lazada",
  "Blibli",
];

// Local State
const searchQuery = ref("");
const showModal = ref(false);
const editingItem = ref(null);

const form = ref({
  name: "",
  platform: "Custom",
  url: "",
  status: "active",
  apiKey: "",
  apiSecret: "",
});

// Filtered Data
const filteredChannels = computed(() => {
  if (!searchQuery.value) return channels.value;
  const query = searchQuery.value.toLowerCase();
  return channels.value.filter(
    (c) =>
      c.name.toLowerCase().includes(query) ||
      c.platform.toLowerCase().includes(query)
  );
});

// Actions
function openAddModal() {
  editingItem.value = null;
  form.value = {
    name: "",
    platform: "Custom",
    url: "",
    status: "active",
    apiKey: "",
    apiSecret: "",
  };
  showModal.value = true;
}

function openEditModal(item) {
  editingItem.value = item;
  form.value = { ...item, apiSecret: "********" }; // Masked secret
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
  editingItem.value = null;
}

useEscapeKey(() => {
  if (showModal.value) closeModal();
});

function saveChannel() {
  if (editingItem.value) {
    const index = channels.value.findIndex(
      (c) => c.id === editingItem.value.id
    );
    if (index !== -1) {
      channels.value[index] = { ...form.value, id: editingItem.value.id };
    }
  } else {
    channels.value.push({
      ...form.value,
      id: channels.value.length + 1,
    });
  }
  closeModal();
}

function deleteChannel(id) {
  if (confirm("Apakah Anda yakin ingin menghapus channel ini?")) {
    channels.value = channels.value.filter((c) => c.id !== id);
  }
}
</script>

<template>
  <div class="space-y-6 animate-in">
    <!-- Header -->
    <div class="flex justify-between items-end">
      <div>
        <h1 class="text-2xl font-bold text-white tracking-tight">
          Toko Online & Marketplace
        </h1>
        <p class="text-slate-500 mt-1">
          Kelola integrasi channel penjualan online
        </p>
      </div>
      <button @click="openAddModal" class="btn btn-primary">
        <Plus :size="16" />
        Tambah Channel
      </button>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="card flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-blue-600 flex items-center justify-center">
          <Globe :size="20" class="text-white" />
        </div>
        <div>
          <p class="text-slate-500 text-sm">Total Channel</p>
          <p class="text-xl font-bold text-white">{{ channels.length }}</p>
        </div>
      </div>
      <div class="card flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-violet-600 flex items-center justify-center">
          <ShoppingCart :size="20" class="text-white" />
        </div>
        <div>
          <p class="text-slate-500 text-sm">Platform Terhubung</p>
          <p class="text-xl font-bold text-white">
            {{new Set(channels.map((c) => c.platform)).size}}
          </p>
        </div>
      </div>
    </div>

    <!-- Content -->
    <div class="card p-0">
      <div class="p-4 border-b border-slate-700/50">
        <div class="relative max-w-md">
          <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500" :size="18" />
          <input v-model="searchQuery" type="text" placeholder="Cari nama toko, platform..." class="input pl-10" />
        </div>
      </div>

      <div class="table-container">
        <table class="table">
          <thead>
            <tr>
              <th>Nama Toko</th>
              <th>Platform</th>
              <th>URL Toko</th>
              <th>Status Integrasi</th>
              <th>API Key</th>
              <th class="text-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="channel in filteredChannels" :key="channel.id">
              <td class="font-medium text-white">{{ channel.name }}</td>
              <td>
                <span class="badge" :class="{
                  'bg-[#42b549]/20 text-[#42b549]':
                    channel.platform === 'Tokopedia',
                  'bg-[#ee4d2d]/20 text-[#ee4d2d]':
                    channel.platform === 'Shopee',
                  'bg-slate-700 text-white': channel.platform === 'Custom',
                }">
                  {{ channel.platform }}
                </span>
              </td>
              <td>
                <a :href="channel.url" target="_blank"
                  class="flex items-center gap-1 text-blue-400 hover:text-blue-300 text-sm">
                  <Link :size="12" />
                  Link Toko
                </a>
              </td>
              <td>
                <div class="flex items-center gap-2">
                  <CheckCircle v-if="channel.status === 'active'" :size="16" class="text-emerald-500" />
                  <XCircle v-else :size="16" class="text-red-500" />
                  <span class="text-sm" :class="channel.status === 'active'
                      ? 'text-emerald-400'
                      : 'text-red-400'
                    ">
                    {{ channel.status === "active" ? "Terhubung" : "Terputus" }}
                  </span>
                </div>
              </td>
              <td class="font-mono text-xs text-slate-500">
                {{
                  channel.apiKey ? channel.apiKey.substring(0, 8) + "..." : "-"
                }}
              </td>
              <td>
                <div class="flex items-center justify-center gap-2">
                  <button @click="openEditModal(channel)"
                    class="p-2 hover:bg-slate-700 rounded-lg transition-colors text-blue-400">
                    <Edit :size="16" />
                  </button>
                  <button @click="deleteChannel(channel.id)"
                    class="p-2 hover:bg-slate-700 rounded-lg transition-colors text-red-400">
                    <Trash2 :size="16" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="closeModal"></div>
        <div
          class="relative bg-slate-900 rounded-2xl border border-slate-700 w-full max-w-lg p-6 shadow-2xl animate-in">
          <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-bold text-white">
              {{ editingItem ? "Edit Channel" : "Tambah Channel Baru" }}
            </h3>
            <button @click="closeModal" class="text-slate-400 hover:text-white">
              <X :size="20" />
            </button>
          </div>

          <form @submit.prevent="saveChannel" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-slate-400 mb-1">Platform</label>
              <select v-model="form.platform" class="input" required>
                <option v-for="p in platforms" :key="p" :value="p">
                  {{ p }}
                </option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-400 mb-1">Nama Toko</label>
              <input v-model="form.name" type="text" class="input" placeholder="Contoh: Official Store Tokopedia"
                required />
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-400 mb-1">URL Toko</label>
              <input v-model="form.url" type="url" class="input" placeholder="https://..." />
            </div>

            <div class="border-t border-slate-700/50 pt-4 mt-4">
              <h4 class="text-sm font-bold text-white mb-4">Konfigurasi API</h4>

              <div class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-slate-400 mb-1">API Key / Client ID</label>
                  <input v-model="form.apiKey" type="text" class="input font-mono" placeholder="Masukkan API Key" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-slate-400 mb-1">API Secret / Client Secret</label>
                  <input v-model="form.apiSecret" type="password" class="input font-mono"
                    placeholder="Masukkan Secret Key" />
                </div>
              </div>
            </div>

            <div class="flex items-center gap-2 pt-2">
              <input v-model="form.status" type="checkbox" id="status_active" :true-value="'active'"
                :false-value="'inactive'"
                class="w-4 h-4 rounded border-slate-600 bg-slate-700 text-blue-600 focus:ring-blue-500" />
              <label for="status_active" class="text-sm text-slate-300">Aktifkan Integrasi</label>
            </div>

            <div class="pt-4 flex gap-3">
              <button type="button" @click="closeModal" class="btn btn-secondary flex-1">
                Batal
              </button>
              <button type="submit" class="btn btn-primary flex-1">
                <Save :size="16" />
                Simpan Konfigurasi
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
