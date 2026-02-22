<script setup>
import { ref, computed } from "vue";
import { useEscapeKey } from "../../composables/useEscapeKey";
import {
  Building2,
  MapPin,
  Clock,
  Plus,
  Search,
  Edit,
  Trash2,
  Warehouse,
  Store,
  Globe,
  Save,
  X,
} from "lucide-vue-next";

// Mock Data
const locations = ref([
  {
    id: 1,
    name: "Pusat Jakarta",
    type: "branch",
    address: "Jl. Sudirman No. 1",
    timezone: "WIB",
    status: "active",
    code: "JKT01",
  },
  {
    id: 2,
    name: "Gudang Pusat",
    type: "warehouse",
    address: "Kawasan Industri Pulo Gadung",
    timezone: "WIB",
    status: "active",
    code: "GDG01",
  },
  {
    id: 3,
    name: "Cabang Bali",
    type: "branch",
    address: "Jl. Sunset Road",
    timezone: "WITA",
    status: "active",
    code: "DPS01",
  },
  {
    id: 4,
    name: "Cabang Papua",
    type: "branch",
    address: "Jl. Sentani",
    timezone: "WIT",
    status: "active",
    code: "JYP01",
  },
]);

const timezones = [
  { value: "WIB", label: "WIB (GMT+7)" },
  { value: "WITA", label: "WITA (GMT+8)" },
  { value: "WIT", label: "WIT (GMT+9)" },
];

const types = [
  { value: "branch", label: "Cabang Outlet" },
  { value: "warehouse", label: "Gudang (Warehouse)" },
];

// Local State
const searchQuery = ref("");
const showModal = ref(false);
const editingLocation = ref(null);

const form = ref({
  name: "",
  code: "",
  type: "branch",
  address: "",
  timezone: "WIB",
  status: "active",
});

// Filtered Data
const filteredLocations = computed(() => {
  if (!searchQuery.value) return locations.value;
  const query = searchQuery.value.toLowerCase();
  return locations.value.filter(
    (l) =>
      l.name.toLowerCase().includes(query) ||
      l.code.toLowerCase().includes(query) ||
      l.address.toLowerCase().includes(query)
  );
});

// Actions
function openAddModal() {
  editingLocation.value = null;
  form.value = {
    name: "",
    code: "",
    type: "branch",
    address: "",
    timezone: "WIB",
    status: "active",
  };
  showModal.value = true;
}

function openEditModal(loc) {
  editingLocation.value = loc;
  form.value = { ...loc };
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
  editingLocation.value = null;
}

useEscapeKey(() => {
  if (showModal.value) closeModal();
});

function saveLocation() {
  if (editingLocation.value) {
    // Update
    const index = locations.value.findIndex(
      (l) => l.id === editingLocation.value.id
    );
    if (index !== -1) {
      locations.value[index] = { ...form.value, id: editingLocation.value.id };
    }
  } else {
    // Add
    locations.value.push({
      ...form.value,
      id: locations.value.length + 1,
    });
  }
  closeModal();
}

function deleteLocation(id) {
  if (
    confirm(
      "Apakah Anda yakin ingin menghapus lokasi ini? Data yang dihapus tidak dapat dikembalikan."
    )
  ) {
    locations.value = locations.value.filter((l) => l.id !== id);
  }
}
</script>

<template>
  <div class="space-y-6 animate-in">
    <!-- Header -->
    <div class="flex justify-between items-end">
      <div>
        <h1 class="text-2xl font-bold text-white tracking-tight">
          Lokasi & Zona Waktu
        </h1>
        <p class="text-slate-500 mt-1">
          Kelola cabang, gudang, dan pengaturan zona waktu
        </p>
      </div>
      <button @click="openAddModal" class="btn btn-primary">
        <Plus :size="16" />
        Tambah Lokasi
      </button>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="card flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-blue-600 flex items-center justify-center">
          <Store :size="20" class="text-white" />
        </div>
        <div>
          <p class="text-slate-500 text-sm">Total Cabang</p>
          <p class="text-xl font-bold text-white">
            {{locations.filter((l) => l.type === "branch").length}}
          </p>
        </div>
      </div>
      <div class="card flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-violet-600 flex items-center justify-center">
          <Warehouse :size="20" class="text-white" />
        </div>
        <div>
          <p class="text-slate-500 text-sm">Total Gudang</p>
          <p class="text-xl font-bold text-white">
            {{locations.filter((l) => l.type === "warehouse").length}}
          </p>
        </div>
      </div>
      <div class="card flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-emerald-600 flex items-center justify-center">
          <Globe :size="20" class="text-white" />
        </div>
        <div>
          <p class="text-slate-500 text-sm">Zona Waktu</p>
          <p class="text-xl font-bold text-white">
            {{new Set(locations.map((l) => l.timezone)).size}} Zone
          </p>
        </div>
      </div>
    </div>

    <!-- Content -->
    <div class="card p-0">
      <!-- Search -->
      <div class="p-4 border-b border-slate-700/50">
        <div class="relative max-w-md">
          <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500" :size="18" />
          <input v-model="searchQuery" type="text" placeholder="Cari nama cabang, kode, atau alamat..."
            class="input pl-10" />
        </div>
      </div>

      <!-- Table -->
      <div class="table-container">
        <table class="table">
          <thead>
            <tr>
              <th>Kode</th>
              <th>Nama Lokasi</th>
              <th>Tipe</th>
              <th>Zona Waktu</th>
              <th>Alamat</th>
              <th>Status</th>
              <th class="text-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="loc in filteredLocations" :key="loc.id">
              <td class="font-mono text-blue-400 font-medium">
                {{ loc.code }}
              </td>
              <td>
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 rounded-lg flex items-center justify-center" :class="loc.type === 'branch'
                      ? 'bg-blue-500/20 text-blue-400'
                      : 'bg-violet-500/20 text-violet-400'
                    ">
                    <Store v-if="loc.type === 'branch'" :size="14" />
                    <Warehouse v-else :size="14" />
                  </div>
                  <span class="font-medium text-white">{{ loc.name }}</span>
                </div>
              </td>
              <td class="text-slate-400 capitalize">
                {{ loc.type === "branch" ? "Cabang" : "Gudang" }}
              </td>
              <td>
                <span class="badge badge-info">{{ loc.timezone }}</span>
              </td>
              <td class="text-slate-400 max-w-[200px] truncate">
                {{ loc.address }}
              </td>
              <td>
                <span class="badge" :class="loc.status === 'active' ? 'badge-success' : 'badge-danger'
                  ">
                  {{ loc.status === "active" ? "Aktif" : "Nonaktif" }}
                </span>
              </td>
              <td>
                <div class="flex items-center justify-center gap-2">
                  <button @click="openEditModal(loc)"
                    class="p-2 hover:bg-slate-700 rounded-lg transition-colors text-blue-400">
                    <Edit :size="16" />
                  </button>
                  <button @click="deleteLocation(loc.id)"
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
              {{ editingLocation ? "Edit Lokasi" : "Tambah Lokasi Baru" }}
            </h3>
            <button @click="closeModal" class="text-slate-400 hover:text-white">
              <X :size="20" />
            </button>
          </div>

          <form @submit.prevent="saveLocation" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Kode Lokasi</label>
                <input v-model="form.code" type="text" class="input font-mono uppercase" placeholder="JKT001"
                  required />
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Tipe Lokasi</label>
                <select v-model="form.type" class="input" required>
                  <option v-for="t in types" :key="t.value" :value="t.value">
                    {{ t.label }}
                  </option>
                </select>
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-400 mb-1">Nama Lokasi</label>
              <input v-model="form.name" type="text" class="input" placeholder="Contoh: Cabang Pusat" required />
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-400 mb-1">Alamat Lengkap</label>
              <textarea v-model="form.address" class="input h-24 resize-none"
                placeholder="Alamat lengkap lokasi..."></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Zona Waktu</label>
                <select v-model="form.timezone" class="input" required>
                  <option v-for="tz in timezones" :key="tz.value" :value="tz.value">
                    {{ tz.label }}
                  </option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Status</label>
                <select v-model="form.status" class="input">
                  <option value="active">Aktif</option>
                  <option value="inactive">Nonaktif</option>
                </select>
              </div>
            </div>

            <div class="pt-4 flex gap-3">
              <button type="button" @click="closeModal" class="btn btn-secondary flex-1">
                Batal
              </button>
              <button type="submit" class="btn btn-primary flex-1">
                <Save :size="16" />
                Simpan
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
