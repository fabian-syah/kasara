<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { ROLE_LABELS, ROLES } from "../../utils/permissions";
import { formatDate } from "../../utils/formatters";
import {
  users as usersApi, branches as branchesApi, warehouses as warehousesApi, onlineShops as onlineShopsApi,
  distributors as distributorsApi
} from "../../api/axios";
import { useToast } from "../../composables/useToast";
import { useEscapeKey } from "../../composables/useEscapeKey";
import { useAuthStore } from "../../store/auth"; // Import Auth Store
import {
  Search,
  Plus,
  Filter,
  Users,
  Shield,
  Trash2,
  Edit,
  UserPlus,
  Check,
  Eye,
  EyeOff,
  Loader2,
  MapPin, // Icon for placement
  Building, // Icon for warehouse
  Camera, // Icon for photo upload
  ChevronLeft,
  ChevronRight
} from "lucide-vue-next";

// Toast
const toast = useToast();
const authStore = useAuthStore(); // Init Store

// State
const users = ref([]);
const branches = ref([]);
const warehouses = ref([]); // New
const onlineShops = ref([]); // New
const distributors = ref([]); // New
const isLoading = ref(false);
const isSaving = ref(false);

// Timezones are inherited from placements

// Roles list
const rolesList = Object.entries(ROLE_LABELS).map(([value, label]) => ({
  value,
  label,
}));

// Local state
const activeTab = ref("active");
const searchQuery = ref("");
const selectedRole = ref("");
const selectedBranch = ref("");
const selectedAccountType = ref("main"); // Default to main accounts
const showModal = ref(false);
const editingUser = ref(null);
const showPassword = ref(false);

const currentPage = ref(1);
const itemsPerPage = ref(10);

const fileInput = ref(null);
const selectedUserForPhoto = ref(null);
const isUploadingPhoto = ref(false);
const processingUserIds = ref(new Set());

function startProcessing(userId) {
  processingUserIds.value.add(userId);
}

function stopProcessing(userId) {
  processingUserIds.value.delete(userId);
}

function isProcessing(userId) {
  return processingUserIds.value.has(userId);
}

const isAudit = computed(() => authStore.userRole === 'audit');
const isSuperAdmin = computed(() => authStore.userRole === 'super_admin');
const isLeader = computed(() => authStore.userRole === 'leader');
const isReadOnlyAccess = computed(() => isLeader.value);
const currentUser = computed(() => authStore.user);

// For audit: filter placement options to only the audit user's accessible placements
const auditAccessibleBranchIds = computed(() => {
  if (!isAudit.value) return null;
  const user = currentUser.value;
  if (!user?.placements) return [];
  return user.placements.filter(p => p.model_type === 'branch' || p.model_type?.includes('Branch')).map(p =>
    Number(p.model_id));
});
const auditAccessibleWarehouseIds = computed(() => {
  if (!isAudit.value) return null;
  const user = currentUser.value;
  if (!user?.placements) return [];
  return user.placements.filter(p => p.model_type === 'warehouse' || p.model_type?.includes('Warehouse')).map(p =>
    Number(p.model_id));
});
const auditAccessibleOnlineShopIds = computed(() => {
  if (!isAudit.value) return null;
  const user = currentUser.value;
  if (!user?.placements) return [];
  return user.placements.filter(p => p.model_type === 'online_shop' || p.model_type?.includes('OnlineShop')).map(p =>
    Number(p.model_id));
});
const auditAccessibleDistributorIds = computed(() => {
  if (!isAudit.value) return null;
  const user = currentUser.value;
  if (!user?.placements) return [];
  return user.placements.filter(p => p.model_type === 'distributor' || p.model_type?.includes('Distributor')).map(p =>
    Number(p.model_id));
});

// Filtered data for modal form â€” audit only sees their assigned locations
const availableBranches = computed(() => {
  if (!isAudit.value || !auditAccessibleBranchIds.value) return branches.value;
  if (auditAccessibleBranchIds.value.length === 0) return [];
  return branches.value.filter(b => auditAccessibleBranchIds.value.includes(Number(b.id)));
});
const availableWarehouses = computed(() => {
  if (!isAudit.value || !auditAccessibleWarehouseIds.value) return warehouses.value;
  if (auditAccessibleWarehouseIds.value.length === 0) return [];
  return warehouses.value.filter(w => auditAccessibleWarehouseIds.value.includes(Number(w.id)));
});
const availableOnlineShops = computed(() => {
  if (!isAudit.value || !auditAccessibleOnlineShopIds.value) return onlineShops.value;
  if (auditAccessibleOnlineShopIds.value.length === 0) return [];
  return onlineShops.value.filter(s => auditAccessibleOnlineShopIds.value.includes(Number(s.id)));
});
const availableDistributors = computed(() => {
  if (!isAudit.value || !auditAccessibleDistributorIds.value) return distributors.value;
  if (auditAccessibleDistributorIds.value.length === 0) return [];
  return distributors.value.filter(d => auditAccessibleDistributorIds.value.includes(Number(d.id)));
});

// Filtered Roles List for Add/Edit Modal
const filteredRolesOptions = computed(() => {
  if (!isAudit.value) return rolesList;

  const user = currentUser.value;
  if (!user) return [];


  // Determine Access based on placements
  const hasBranchAccess = !!user.branch_id || (user.placements?.some(p =>
    p.model_type === 'branch' || p.model_type?.includes('Branch')
  ) ?? false);

  const hasWarehouseAccess = !!user.warehouse_id || (user.placements?.some(p =>
    p.model_type === 'warehouse' || p.model_type?.includes('Warehouse')
  ) ?? false);

  const hasOnlineAccess = !!user.online_shop_id || (user.placements?.some(p =>
    p.model_type === 'online_shop' || p.model_type?.includes('OnlineShop')
  ) ?? false);

  const hasDistributorAccess = !!user.distributor_id || (user.placements?.some(p =>
    p.model_type === 'distributor' || p.model_type?.includes('Distributor')
  ) ?? false);

  // Whitelist: only show roles matching audit's access types
  // By default, Audit can always create 'inventory' accounts
  const allowedRoles = new Set(['inventory']);

  // Roles that an audit user can assign based on their own placements
  if (hasBranchAccess) {
    ['security', 'leader', 'toko_offline'].forEach(r => allowedRoles.add(r));
  }
  if (hasWarehouseAccess) {
    ['gudang'].forEach(r => allowedRoles.add(r));
  }
  if (hasOnlineAccess) {
    ['toko_online'].forEach(r => allowedRoles.add(r));
  }
  if (hasDistributorAccess) {
    ['distributor'].forEach(r => allowedRoles.add(r));
  }

  // Exclude roles that should never be assigned by an audit user, regardless of placement access
  const alwaysExcludedRoles = ['super_admin', 'audit', 'analist', 'admin_produk'];

  let filtered = rolesList.filter(r => allowedRoles.has(r.value) && !alwaysExcludedRoles.includes(r.value));
  
  if (editingUser.value && form.value.role) {
    if (!filtered.find(r => r.value === form.value.role)) {
      const currentRoleObj = rolesList.find(r => r.value === form.value.role);
      if (currentRoleObj) {
        filtered.push(currentRoleObj);
      }
    }
  }

  return filtered;
});

// Form
const form = ref({
  full_name: "",
  username: "",
  email: "",
  role: "",
  branch_id: "", // For Physical Branches
  warehouse_id: "", // For Warehouse
  online_shop_id: "", // For Online Shop
  distributor_id: "", // For Distributor
  address: "",
  password: "",
  is_active: true,
  selected_branches: [], // For Audit/Leader
  selected_online_shops: [], // For Audit/Leader
  selected_warehouses: [], // For Audit/Leader
  selected_distributors: [],
    transaction_pin: "", // For Audit/Leader
});

const selectedMultiPlacementType = ref('physical'); // UI toggle for audit/leader modal
const inventoryPlacementType = ref('branch'); // UI toggle for inventory modal

// Helper to reset form
function resetForm() {
  form.value = {
    full_name: "",
    username: "",
    email: "",
    role: "",
    branch_id: "",
    warehouse_id: "",
    online_shop_id: "",
    distributor_id: "",
    address: "",
    password: "",
    is_active: true,
    selected_branches: [],
    selected_online_shops: [],
    selected_warehouses: [],
    selected_distributors: [],
    transaction_pin: "",
  };
  showPassword.value = false;
}

// Format Date Helper
function formatLastSeen(date, timezoneStr) {
  if (!date) return '-';
  
  // Map WIB/WITA/WIT to valid IANA timezones
  let ianaTz = 'Asia/Jakarta';
  if (timezoneStr === 'WITA') ianaTz = 'Asia/Makassar';
  else if (timezoneStr === 'WIT') ianaTz = 'Asia/Jayapura';
  else if (timezoneStr && timezoneStr.includes('/')) ianaTz = timezoneStr; // Allow if it's already an IANA string
  
  try {
    return new Date(date).toLocaleString('id-ID', {
      timeZone: ianaTz,
      day: 'numeric', month: 'short', year: 'numeric',
      hour: '2-digit', minute: '2-digit'
    });
  } catch (e) {
    return '-';
  }
}

// Avatar Helper & Upload Logic
function getAvatarUrl(user) {
  const photoPath = user?.photo || user?.photo_inventory;
  if (photoPath) {
    if (photoPath.startsWith('http')) {
      return photoPath;
    }
    const storageUrl = import.meta.env.VITE_API_BASE_URL
      ? import.meta.env.VITE_API_BASE_URL.replace(/\/api\/?$/, "")
      : (import.meta.env.VITE_API_URL ? import.meta.env.VITE_API_URL.replace(/\/api\/?$/, "") : '');

    // Ensure no double slashes like .com//storage
    const cleanStorageUrl = storageUrl.replace(/\/+$/, "");
    return `${cleanStorageUrl}/storage/${photoPath}`;
  }
  return `https://ui-avatars.com/api/?name=${encodeURIComponent(user?.full_name || 'U')}&background=3b82f6&color=fff`;
}

function triggerFileInput(user) {
  if (isReadOnlyAccess.value && user.id !== currentUser.value.id) {
    toast.info("Anda hanya dapat mengubah foto profil akun sendiri.");
    return;
  }
  selectedUserForPhoto.value = user;
  if (fileInput.value) {
    fileInput.value.click();
  }
}

async function handlePhotoUpload(event) {
  const file = event.target.files[0];
  if (!file) return;

  if (file.size > 2 * 1024 * 1024) { // 2MB limit
    toast.error("Ukuran foto maksimal 2MB");
    event.target.value = '';
    return;
  }

  isUploadingPhoto.value = true;
  const formData = new FormData();
  formData.append('photo', file);

  try {
    const res = await usersApi.updateProfile(selectedUserForPhoto.value.id, formData);
    // Update local user data
    const updatedUser = res.data.data;
    const index = users.value.findIndex(u => u.id === selectedUserForPhoto.value.id);
    if (index !== -1) {
      users.value[index].photo = updatedUser.photo;
      // Also update photo_inventory if it's the same user or if the API returns both
      users.value[index].photo_inventory = updatedUser.photo_inventory || updatedUser.photo;
    }
    toast.success("Foto profil berhasil diperbarui");
  } catch (error) {
    console.error("Upload error", error);
    toast.error("Gagal mengupload foto");
  } finally {
    isUploadingPhoto.value = false;
    event.target.value = '';
    selectedUserForPhoto.value = null;
  }
}

// API Fetch
async function fetchData() {
  isLoading.value = true;
  try {
    const [usersRes, branchesRes, warehousesRes, onlineShopsRes, distributorsRes] = await Promise.all([
      usersApi.list(),
      branchesApi.list({ include_all: 1 }),
      warehousesApi.list(),
      onlineShopsApi.list(),
      distributorsApi.list()
    ]);

    users.value = usersRes.data.data || [];
    branches.value = (branchesRes.data.data || []).filter(b => b.is_active && (!b.type || b.type === 'physical'));
    warehouses.value = (warehousesRes.data.data || []).filter(w => w.is_active);
    onlineShops.value = (onlineShopsRes.data.data || []).filter(s => s.is_active);
    distributors.value = (distributorsRes.data.data || []).filter(d => d.is_active);
  } catch (error) {
    console.error("Error fetching data:", error);
    toast.error("Gagal memuat data.");
    users.value = []; // Ensure empty array on error
  } finally {
    isLoading.value = false;
  }
}

onMounted(() => {
  fetchData();
});

// Computed Logic for Placements
const placementType = computed(() => {
  const role = form.value.role;
  if (!role) return 'branch';
  if (['super_admin', 'admin_produk', 'analist'].includes(role)) return 'none';
  if (['audit', 'leader'].includes(role)) return 'audit';
  if (['distributor'].includes(role)) return 'distributor';
  if (['distribution'].includes(role)) return 'distributor';
  if (['gudang'].includes(role)) return 'warehouse';
  if (['inventory'].includes(role)) return inventoryPlacementType.value;
  if (['toko_online'].includes(role)) return 'online_shop';
  return 'branch';
});

const placementLabel = computed(() => {
  const type = placementType.value;
  if (type === 'audit') return 'Pengaturan Multi-Lokasi (Bisa lebih dari 1)';
  if (type === 'warehouse') return 'Lokasi Gudang';
  if (type === 'online_shop') return 'Toko Online';
  if (type === 'distributor') return 'Distributor';
  if (type === 'branch') return 'Lokasi Cabang';
  return '';
});

// Build a set of sub-account IDs from the created_users relationship data
// This is reliable because created_users is already shown correctly in the UI
const subAccountIds = computed(() => {
  const ids = new Set();
  (users.value || []).forEach(u => {
    if (u.created_users && Array.isArray(u.created_users)) {
      u.created_users.forEach(child => ids.add(child.id));
    }
  });
  return ids;
});

// Map sub-account ID -> parent user for branch inheritance
const subAccountParentMap = computed(() => {
  const map = new Map();
  (users.value || []).forEach(parent => {
    if (parent.created_users && Array.isArray(parent.created_users)) {
      parent.created_users.forEach(child => {
        map.set(child.id, parent);
      });
    }
  });
  return map;
});

const filteredUsers = computed(() => {
  if (!users.value) return [];
  let result = users.value;

  // Account Type Filter
  if (selectedAccountType.value) {
    if (selectedAccountType.value === 'main') {
      result = result.filter(u => u && !u.roles?.some(r => r.name === 'inventory') && !subAccountIds.value.has(u.id));
    } else if (selectedAccountType.value === 'inventory') {
      result = result.filter(u => u && (u.roles?.some(r => r.name === 'inventory') || subAccountIds.value.has(u.id)));
    }
  }

  // Search Filter
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    result = result.filter(u =>
      (u.full_name && u.full_name.toLowerCase().includes(query)) ||
      (u.username && u.username.toLowerCase().includes(query))
    );
  }

  // Role Filter
  if (selectedRole.value) {
    result = result.filter(u => u && u.roles && u.roles.some(r => r.name === selectedRole.value));
  }

  // Branch Filter â€” for sub-accounts without branch_id, match via their parent's branch
  if (selectedBranch.value) {
    result = result.filter(u => {
      if (!u) return false;
      if (u.branch_id == selectedBranch.value) return true;
      // Check if this is a sub-account whose parent belongs to the selected branch
      const parent = subAccountParentMap.value.get(u.id);
      if (parent && parent.branch_id == selectedBranch.value) return true;
      return false;
    });
  }

  return result;
});

const totalPages = computed(() => {
  return Math.ceil(filteredUsers.value.length / itemsPerPage.value) || 1;
});

const paginatedUsers = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage.value;
  return filteredUsers.value.slice(start, start + itemsPerPage.value);
});

function goToPage(page) {
  if (page < 1 || page > totalPages.value) return;
  currentPage.value = page;
}

// Reset page when filters change
watch([searchQuery, selectedRole, selectedBranch, selectedAccountType], () => {
  currentPage.value = 1;
});

// Computed Stats
const stats = computed(() => {
  const safeUsers = users.value || [];
  return [
    { label: "Total User", value: safeUsers.length, icon: Users, color: "blue" },
    { label: "User Aktif", value: safeUsers.filter(u => u && u.is_active).length, icon: Check, color: "emerald" },
    { label: "User Nonaktif", value: safeUsers.filter(u => u && !u.is_active).length, icon: Shield, color: "amber" },
  ];
});

function openAddModal() {
  resetForm();
  editingUser.value = null;
  showModal.value = true;
}

function openEditModal(user) {
  editingUser.value = user;

  // Parse placements if available
  const branchPlacements = (user.placements || []).filter(p => p.model_type === 'branch').map(p => p.model_id);
  const onlineShopPlacements = (user.placements || []).filter(p => p.model_type === 'online_shop').map(p => p.model_id);
  const warehousePlacements = (user.placements || []).filter(p => p.model_type === 'warehouse').map(p => p.model_id);
  const distributorPlacements = (user.placements || []).filter(p => p.model_type === 'distributor').map(p => p.model_id);

  form.value = {
    full_name: user.full_name,
    username: user.username,
    email: user.email,
    role: user.roles?.length ? user.roles[0].name : '',
    branch_id: user.branch_id,
    warehouse_id: user.warehouse_id,
    online_shop_id: user.online_shop_id,
    distributor_id: user.distributor_id,
    address: user.address,
    is_active: !!user.is_active,
    password: "",
    selected_branches: branchPlacements,
    selected_online_shops: onlineShopPlacements,
    selected_warehouses: warehousePlacements,
    selected_distributors: distributorPlacements,
  };
  selectedMultiPlacementType.value = 'physical'; // Reset UI toggle
  
  if (user.roles?.length && user.roles[0].name === 'inventory') {
      if (user.warehouse_id) inventoryPlacementType.value = 'warehouse';
      else if (user.online_shop_id) inventoryPlacementType.value = 'online_shop';
      else inventoryPlacementType.value = 'branch';
  } else {
      inventoryPlacementType.value = 'branch';
  }
  showModal.value = true;
}


function closeModal() {
  showModal.value = false;
  editingUser.value = null;
  resetForm();
}

useEscapeKey(() => {
  if (showModal.value) closeModal();
});

async function saveUser() {
  isSaving.value = true;
  try {
    // Ensure only relevant placement ID is sent (though backend handles overrides, cleaner here)
    const payload = { ...form.value };
    if (placementType.value !== 'branch') payload.branch_id = null;
    if (placementType.value !== 'warehouse') payload.warehouse_id = null;
    if (placementType.value !== 'online_shop') payload.online_shop_id = null;
    if (placementType.value !== 'distributor') payload.distributor_id = null;

    // Explicitly set null if empty string to avoid DB errors constraint
    if (!payload.branch_id) payload.branch_id = null;
    if (!payload.warehouse_id) payload.warehouse_id = null;
    if (!payload.online_shop_id) payload.online_shop_id = null;
    if (!payload.distributor_id) payload.distributor_id = null;

    // Handle Audit Multi-Placement
    if (placementType.value === 'audit') {
      payload.selected_branches = form.value.selected_branches;
      payload.selected_online_shops = form.value.selected_online_shops;
      payload.selected_warehouses = form.value.selected_warehouses;
      payload.selected_distributors = form.value.selected_distributors;
    }

    if (editingUser.value) {
      if (!payload.password) delete payload.password;
      const res = await usersApi.update(editingUser.value.id, payload);
      const index = users.value.findIndex(u => u.id === editingUser.value.id);
      if (index !== -1) users.value[index] = res.data.data;
      
      if (payload.password) {
        toast.success("Data user dan Password berhasil diperbarui!");
      } else {
        toast.success("User berhasil diperbarui!");
      }
    } else {
      const res = await usersApi.create(payload);
      users.value.unshift(res.data.data);
      toast.success("User baru berhasil ditambahkan!");
    }
    closeModal();
  } catch (error) {
    console.error("Save error", error);
    // Improve error message handling
    let msg = "Gagal menyimpan user.";
    if (error.response?.data?.errors) {
      // Combine all error messages
      msg = Object.values(error.response.data.errors).flat().join('\n');
    } else if (error.response?.data?.message) {
      msg = error.response.data.message;
    } else if (error.response?.data?.error_message) {
      msg = error.response.data.error_message;
    }
    toast.error(msg);
  } finally {
    isSaving.value = false;
  }
}

async function toggleStatus(user) {
  if (isReadOnlyAccess.value || isProcessing(user.id)) return;
  startProcessing(user.id);
  try {
    const newStatus = !user.is_active;
    await usersApi.update(user.id, { is_active: newStatus });
    user.is_active = newStatus;
    toast.info(newStatus ? "User diaktifkan." : "User dinonaktifkan.");
  } catch (error) {
    toast.error("Gagal mengubah status user.");
  } finally {
    stopProcessing(user.id);
  }
}

async function permanentDeleteUser(id) {
  if (isProcessing(id)) return;
  if (!confirm("HAPUS PERMANEN? Data tidak dapat dikembalikan!")) return;
  startProcessing(id);
  try {
    await usersApi.delete(id);
    users.value = users.value.filter(u => u.id !== id);
    toast.success("User berhasil dihapus permanen.");
  } catch (error) {
    toast.error("Gagal menghapus user.");
  } finally {
    stopProcessing(id);
  }
}

// Helper to get placement name for table
function getPlacementName(user) {
  if (!user) return '-';

  // Handle Multi-Placements (Audit, Leader, Distributor)
  if ((user.placements || []).length > 0) {
    const list = user.placements;
    if (list.length === 1) {
      const p = list[0];
      if (p.model_type === 'branch') return branches.value.find(b => b.id == p.model_id)?.name || 'Cabang';
      if (p.model_type === 'online_shop') return onlineShops.value.find(s => s.id == p.model_id)?.name || 'Online Shop';
      if (p.model_type === 'warehouse') return warehouses.value.find(w => w.id == p.model_id)?.name || 'Gudang';
      if (p.model_type === 'distributor') return distributors.value.find(d => d.id == p.model_id)?.name || 'Distributor';
    }
    return `${list.length} Akses Lokasi`;
  }

  // Fallback to primary columns
  if (user.branch) return user.branch.name;
  if (user.warehouse) return `Gudang: ${user.warehouse.name}`;
  if (user.online_shop) return `Online: ${user.online_shop.name}`;
  if (user.distributor) return `Dist: ${user.distributor.name}`;

  // Inherit from parent if sub-account
  const parent = subAccountParentMap.value?.get(user.id);
  if (parent) {
    if (parent.branch) return parent.branch.name;
    if (parent.warehouse) return `Gudang: ${parent.warehouse.name}`;
    if (parent.online_shop) return `Online: ${parent.online_shop.name}`;
    if (parent.distributor) return `Dist: ${parent.distributor.name}`;
  }

  return '-';
}

function getPlacementTimezone(user) {
  if (!user) return 'WIB';
  if (user.branch?.timezone) return user.branch.timezone;
  if (user.warehouse?.timezone) return user.warehouse.timezone;
  
  const parent = subAccountParentMap.value?.get(user.id);
  if (parent) {
    if (parent.branch?.timezone) return parent.branch.timezone;
    if (parent.warehouse?.timezone) return parent.warehouse.timezone;
  }
  
  return 'WIB';
}

function getUserRoleName(user) {
  if (!user || !user.roles || !user.roles.length) return 'No Role';
  return ROLE_LABELS[user.roles[0].name] || user.roles[0].name;
}
</script>

<template>
  <div class="space-y-6 animate-in">
    <!-- Hidden File Input for Avatar Upload -->
    <input type="file" accept="image/*" ref="fileInput" class="hidden" @change="handlePhotoUpload" />

    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-start gap-4">
      <div class="w-full md:flex-1">
        <h1 class="text-2xl font-bold text-text-primary tracking-tight flex items-center gap-2">
          <Users :size="28" class="text-blue-500" /> Staff & Role
        </h1>
        <p class="text-text-secondary mt-1">Kelola pengguna dan hak akses</p>
        <div
          class="mt-3 text-[11px] bg-blue-600/10 text-blue-400 p-3 rounded-xl flex items-start gap-3 border border-blue-500/20 shadow-sm leading-relaxed max-w-xl">
          <span class="shrink-0 mt-0.5">ðŸ’¡</span>
          <p>
            <strong>Tip:</strong> Klik pada foto profil di tabel untuk mengubah atau menambahkan foto pengguna. Hal ini
            berlaku untuk akun login maupun akun inventory.
          </p>
        </div>
      </div>
      <button v-if="!isReadOnlyAccess" @click="openAddModal"
        class="btn btn-primary w-full md:w-auto flex justify-center py-3 md:py-2.5">
        <UserPlus :size="18" />
        <span>Tambah User</span>
      </button>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="(stat, index) in stats" :key="index" class="card flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl flex items-center justify-center"
          :class="{ 'bg-blue-600/20 text-blue-500': stat.color === 'blue', 'bg-emerald-600/20 text-emerald-500': stat.color === 'emerald', 'bg-amber-600/20 text-amber-500': stat.color === 'amber' }">
          <component :is="stat.icon" :size="24" />
        </div>
        <div>
          <p class="text-text-secondary text-sm">{{ stat.label }}</p>
          <p class="text-xl font-bold text-text-primary">{{ stat.value }}</p>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="card space-y-4">
      <div class="flex flex-col md:flex-row gap-4">
        <div class="w-full md:flex-1 group flex flex-col justify-center">
          <div class="relative w-full">
            <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary" :size="18" />
            <input v-model="searchQuery" type="text" placeholder="Cari nama atau email..." class="w-full bg-surface-50 dark:bg-surface-800 border border-surface-200 dark:border-surface-700 rounded-lg py-2.5 pl-10 pr-4 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500 transition-all placeholder:text-text-secondary" />
          </div>
        </div>
        <div class="flex flex-col md:flex-row gap-3 w-full md:w-auto">
          <!-- Account Type Tabs -->
          <div class="flex p-1 bg-surface-900 border border-surface-700 rounded-xl w-full md:w-auto shrink-0 shadow-inner">
            <button @click="selectedAccountType = 'main'" 
                    class="flex-1 md:px-6 py-2.5 md:py-2 rounded-lg text-[10px] font-black uppercase tracking-widest transition-all flex items-center justify-center gap-2"
                    :class="selectedAccountType === 'main' ? 'bg-blue-600 text-white shadow-lg shadow-blue-500/20' : 'text-text-secondary hover:text-text-primary hover:bg-surface-800'">
              <Users :size="14" />
              <span>Akun Login</span>
            </button>
            <button @click="selectedAccountType = 'inventory'" 
                    class="flex-1 lg:px-6 py-2 rounded-lg text-[10px] font-black uppercase tracking-widest transition-all flex items-center justify-center gap-2"
                    :class="selectedAccountType === 'inventory' ? 'bg-orange-600 text-white shadow-lg shadow-orange-500/20' : 'text-text-secondary hover:text-text-primary hover:bg-surface-800'">
              <Shield :size="14" />
              <span>Akun Inventory</span>
            </button>
          </div>

          <select v-model="selectedRole" class="input w-full">
            <option value="">Semua Role</option>
            <option v-for="role in rolesList" :key="role.value" :value="role.value">{{ role.label }}</option>
          </select>
          <select v-model="selectedBranch" class="input w-full">
            <option value="">Semua Cabang</option>
            <option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.name }}</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Table (Desktop) -->
    <!-- Table (Desktop) -->
    <div class="card p-0 hidden lg:block overflow-hidden">
      <div v-if="isLoading" class="p-12 flex justify-center items-center">
        <Loader2 class="animate-spin text-blue-500" :size="32" />
        <span class="ml-3 text-text-secondary">Memuat data user...</span>
      </div>
      <div v-else class="overflow-x-auto">
        <table class="table w-full">
          <thead>
            <tr>
              <th class="text-left py-4 px-6 text-text-secondary font-medium text-sm uppercase tracking-wider">User
              </th>
              <th class="text-left py-4 px-6 text-text-secondary font-medium text-sm uppercase tracking-wider">Role
              </th>
              <th class="text-left py-4 px-6 text-text-secondary font-medium text-sm uppercase tracking-wider">
                Penempatan
              </th>
              <th class="text-left py-4 px-6 text-text-secondary font-medium text-sm uppercase tracking-wider">
                Aktifitas
              </th>
              <th class="text-left py-4 px-6 text-text-secondary font-medium text-sm uppercase tracking-wider">Status
              </th>
              <th class="text-right py-4 px-6 text-text-secondary font-medium text-sm uppercase tracking-wider"
                v-if="!isReadOnlyAccess">Aksi
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-surface-700/50">
            <tr v-if="paginatedUsers.length === 0">
              <td colspan="6" class="text-center py-20 text-text-secondary">
                <div class="flex flex-col items-center gap-4">
                    <Users class="opacity-20" :size="48" />
                    <p class="font-medium tracking-wide">Tidak ada user ditemukan</p>
                </div>
              </td>
            </tr>
            <tr v-for="user in paginatedUsers" :key="user.id" class="hover:bg-surface-800/50 transition-all duration-200">
              <td class="px-6 py-4">
                <div class="flex items-center gap-4">
                  <div class="relative group cursor-pointer h-12 w-12 shrink-0" @click="triggerFileInput(user)"
                    title="Klik untuk ubah foto">
                    <img :src="getAvatarUrl(user)"
                      class="w-full h-full rounded-xl object-cover shadow-sm group-hover:opacity-50 transition-opacity duration-200"
                      :class="{ 'opacity-30': isUploadingPhoto && selectedUserForPhoto?.id === user.id }"
                      :alt="user.full_name" />
                    <div
                      class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity shadow-inner rounded-xl bg-black/40">
                      <Camera v-if="!(isUploadingPhoto && selectedUserForPhoto?.id === user.id)"
                        class="text-white drop-shadow-md" :size="20" />
                      <Loader2 v-else class="text-white animate-spin" :size="20" />
                    </div>
                    <!-- Status Indicator Dot -->
                    <div v-if="!isProcessing(user.id)" class="absolute -bottom-1 -right-1 w-4 h-4 rounded-full border-2 border-surface-800 transition-colors"
                      :class="user.is_active ? 'bg-emerald-500' : 'bg-red-500'"></div>
                    <div v-else class="absolute -bottom-1 -right-1 w-4 h-4 rounded-full border-2 border-surface-800 bg-surface-700 flex items-center justify-center">
                      <Loader2 class="text-primary-400 animate-spin" :size="8" />
                    </div>
                  </div>

                  <div>
                    <div class="flex items-center gap-2 mb-1">
                      <p class="font-bold text-text-primary text-base">{{ user.full_name }}</p>
                      <!-- Account Type Badge -->
                      <span v-if="user.roles?.some(r => r.name === 'inventory') || subAccountIds.has(user.id)"
                        class="px-2 py-0.5 rounded text-[10px] uppercase font-bold tracking-wider bg-orange-500/10 text-orange-600 dark:text-orange-400 border border-orange-500/20">
                        Inventory
                      </span>
                      <span v-else
                        class="px-2 py-0.5 rounded text-[10px] uppercase font-bold tracking-wider bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20">
                        Login / Main
                      </span>
                    </div>

                                        <div v-if="user.pin_reset_requested_at" class="flex items-center gap-1.5 mt-1 animate-pulse">
                      <span class="flex h-2 w-2 rounded-full bg-red-500"></span>
                      <p class="text-[10px] font-bold text-red-500 uppercase">Butuh Reset PIN</p>
                    </div>

                    <p class="text-xs text-text-secondary font-mono mb-1">{{ user.username }}</p>

                    <!-- Relationship Info -->
                    <div v-if="user.created_users?.length" class="flex flex-wrap gap-1 mt-1.5">
                      <span class="text-[10px] text-text-secondary mr-1">Memiliki:</span>
                      <span v-for="child in user.created_users" :key="child.id"
                        class="px-1.5 py-0.5 rounded text-[10px] bg-surface-700 text-text-primary border border-surface-600">
                        {{ child.full_name }}
                      </span>
                    </div>
                    <div v-if="user.created_by_user" class="flex items-center gap-1 mt-1.5">
                      <span class="text-[10px] text-text-secondary">Milik Akun:</span>
                      <span
                        class="px-1.5 py-0.5 rounded text-[10px] bg-blue-500/10 text-blue-600 dark:text-blue-400 font-medium border border-blue-500/20">
                        {{ user.created_by_user.full_name }}
                      </span>
                    </div>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4">
                <span v-if="user && user.roles && user.roles.length"
                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-500/10 text-blue-400 border border-blue-500/20">
                  {{ getUserRoleName(user) }}
                </span>
                <span v-else class="text-xs text-text-secondary italic">No Role</span>
              </td>
              <td class="px-6 py-4">
                <div class="flex items-center gap-2">
                  <MapPin :size="14" class="text-text-secondary" />
                  <span class="text-sm text-text-primary">{{ getPlacementName(user) }}</span>
                </div>
                <div class="text-[10px] text-text-secondary mt-1 pl-5 font-mono">{{ getPlacementTimezone(user) }}</div>
              </td>
              <td class="px-6 py-4 text-sm text-text-secondary">
                <div class="mb-1">
                  <span class="block text-[10px] uppercase font-bold text-text-secondary mb-0.5">Login Terakhir</span>
                  <span class="text-text-primary">{{ formatLastSeen(user.last_seen, getPlacementTimezone(user)) }}</span>
                </div>
                <div>
                  <span class="block text-[10px] uppercase font-bold text-text-secondary mb-0.5">Ubah Password</span>
                  <span class="text-text-primary">{{ user.password_changed_at ? formatLastSeen(user.password_changed_at, getPlacementTimezone(user)) : '-' }}</span>
                </div>
              </td>
              <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                  <button @click="toggleStatus(user)" :disabled="isReadOnlyAccess"
                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none shrink-0"
                    :style="{ backgroundColor: user.is_active ? '#10b981' : '#9ca3af' }"
                    :class="[isReadOnlyAccess ? 'opacity-50 cursor-not-allowed' : '']"
                    title="Klik untuk mengubah status">
                    <span class="sr-only">Toggle status</span>
                    <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                      :class="user.is_active ? 'translate-x-6' : 'translate-x-1'" />
                  </button>
                  <span class="text-sm font-medium"
                    :class="user.is_active ? 'text-emerald-400' : 'text-text-secondary'">
                    {{ user.is_active ? 'Aktif' : 'Nonaktif' }}
                  </span>
                </div>
              </td>
              <td class="px-6 py-4" v-if="!isReadOnlyAccess">
                <div class="flex justify-end gap-2">
                  <button @click="openEditModal(user)" :disabled="isProcessing(user.id)"
                    class="p-2 hover:bg-surface-700 disabled:opacity-50 rounded-lg text-blue-400 transition-colors" title="Edit">
                    <Edit :size="16" />
                  </button>
                  <button @click="permanentDeleteUser(user.id)" :disabled="isProcessing(user.id)"
                    class="p-2 hover:bg-surface-700 disabled:opacity-50 rounded-lg text-red-400 transition-colors" title="Hapus">
                    <Loader2 v-if="isProcessing(user.id)" :size="16" class="animate-spin" />
                    <Trash2 v-else :size="16" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <div class="px-6 py-4 flex items-center justify-between border-t border-surface-700/50 bg-surface-900/10">
        <div class="text-xs text-text-secondary">
          Menampilkan <span class="text-text-primary font-bold">{{ ((currentPage - 1) * itemsPerPage) + 1 }}-{{ Math.min(currentPage * itemsPerPage, filteredUsers.length) }}</span> dari <span class="text-text-primary font-bold">{{ filteredUsers.length }}</span> {{ selectedAccountType === 'main' ? 'Akun Login' : 'Akun Inventory' }}
        </div>
        <div class="flex items-center gap-1.5">
          <button @click="goToPage(currentPage - 1)" :disabled="currentPage === 1"
            class="p-2 rounded-lg hover:bg-surface-700 disabled:opacity-30 disabled:cursor-not-allowed transition-all">
            <ChevronLeft :size="16" />
          </button>
          
          <div class="flex gap-1 overflow-x-auto max-w-[200px] no-scrollbar">
            <button v-for="page in totalPages" :key="page" @click="goToPage(page)"
              class="min-w-[32px] h-8 flex items-center justify-center rounded-lg text-xs font-bold transition-all"
              :class="currentPage === page ? 'bg-primary-500 text-white shadow-lg shadow-primary-500/20' : 'text-text-secondary hover:bg-surface-700'">
              {{ page }}
            </button>
          </div>

          <button @click="goToPage(currentPage + 1)" :disabled="currentPage === totalPages"
            class="p-2 rounded-lg hover:bg-surface-700 disabled:opacity-30 disabled:cursor-not-allowed transition-all">
            <ChevronRight :size="16" />
          </button>
        </div>
      </div>
    </div>

    <!-- Mobile/Tablet Card View -->
    <div class="lg:hidden grid grid-cols-1 sm:grid-cols-2 gap-4 pb-20">
      <div v-if="isLoading" class="p-12 flex justify-center col-span-full">
        <Loader2 class="animate-spin text-blue-500" :size="32" />
      </div>
      <div v-if="paginatedUsers.length === 0 && !isLoading" class="col-span-full py-20 card flex flex-col items-center gap-4 text-center">
          <Users class="opacity-20 text-text-secondary" :size="64" />
          <p class="text-text-secondary font-medium uppercase tracking-widest text-xs">Akun tidak ditemukan</p>
      </div>
      <div v-for="user in paginatedUsers" :key="user.id" class="card relative flex flex-col justify-between h-full group hover:border-blue-500/30 transition-all duration-300">
        <!-- Background Decor -->
        <div class="absolute top-0 right-0 p-4 opacity-5 pointer-events-none group-hover:opacity-10 transition-opacity">
            <component :is="user.roles?.some(r => r.name === 'inventory') ? Shield : Users" :size="80" />
        </div>

        <div class="flex justify-between items-start gap-3 relative z-10">
          <div class="flex items-center gap-3">
            <div class="relative group cursor-pointer h-12 w-12 shrink-0" @click="triggerFileInput(user)">
              <img :src="getAvatarUrl(user)"
                class="w-full h-full rounded-xl object-cover shadow-sm group-hover:opacity-50 transition-opacity duration-200" />
              <div
                class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity shadow-inner rounded-xl bg-black/40">
                <Camera class="text-white drop-shadow-md" :size="20" />
              </div>
              <div class="absolute -bottom-1 -right-1 w-3 h-3 rounded-full border-2 border-surface-800"
                :class="user.is_active ? 'bg-emerald-500' : 'bg-red-500'"></div>
            </div>
            <div>
              <div class="flex items-center gap-2">
                <p class="font-bold text-text-primary text-base">{{ user.full_name }}</p>
                <span v-if="user.roles?.some(r => r.name === 'inventory')"
                  class="px-1.5 py-0.5 rounded text-[9px] uppercase font-bold bg-orange-500/10 text-orange-600 dark:text-orange-400 border border-orange-500/20">
                  INV
                </span>
                <span v-else
                  class="px-1.5 py-0.5 rounded text-[9px] uppercase font-bold bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20">
                  MAIN
                </span>
              </div>
              <p class="text-xs text-text-secondary font-mono">{{ user.username }}</p>

              <!-- Mobile Relationship -->
              <div v-if="user.created_users?.length" class="mt-1">
                <p class="text-[10px] text-text-secondary">Akun Inventory:</p>
                <div class="flex flex-wrap gap-1 mt-0.5">
                  <span v-for="child in user.created_users" :key="child.id"
                    class="text-[10px] bg-surface-700 px-1.5 rounded text-text-primary border border-surface-600">
                    {{ child.full_name }}
                  </span>
                </div>
              </div>
              <div v-if="user.created_by_user" class="mt-1 flex items-center gap-1">
                <span class="text-[10px] text-text-secondary">Milik:</span>
                <span class="text-[10px] font-medium text-blue-600 dark:text-blue-400">{{
                  user.created_by_user.full_name
                }}</span>
              </div>
            </div>
          </div>
          <span v-if="user && user.roles && user.roles.length"
            class="text-xs px-2 py-1 bg-surface-800 text-text-secondary rounded-lg border border-surface-700 leading-none h-fit">
            {{ getUserRoleName(user) }}
          </span>
        </div>

        <div class="grid grid-cols-2 gap-3 text-sm border-t border-surface-700/50 pt-3 mt-auto">
          <div>
            <p class="text-text-secondary text-xs mb-1">Penempatan</p>
            <p class="text-text-primary">{{ getPlacementName(user) }}</p>
          </div>
          <div class="text-right">
            <p class="text-text-secondary text-xs mb-1">Status</p>
            <div class="flex items-center justify-end gap-2">
              <span class="text-xs" :class="user.is_active ? 'text-emerald-400' : 'text-text-secondary'">
                {{ user.is_active ? 'Aktif' : 'Nonaktif' }}
              </span>
              <button @click="toggleStatus(user)" :disabled="isReadOnlyAccess"
                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none shrink-0"
                :style="{ backgroundColor: user.is_active ? '#10b981' : '#9ca3af' }"
                :class="[isReadOnlyAccess ? 'opacity-50 cursor-not-allowed' : '']">
                <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                  :class="user.is_active ? 'translate-x-6' : 'translate-x-1'" />
              </button>
            </div>
          </div>
          <div class="col-span-2 grid grid-cols-2 gap-3 pt-2">
            <div>
              <p class="text-text-secondary text-xs mb-1">Login Terakhir</p>
              <p class="text-text-primary text-xs">{{ formatLastSeen(user.last_seen, getPlacementTimezone(user)) }}</p>
            </div>
            <div class="text-right">
              <p class="text-text-secondary text-xs mb-1">Ubah Password</p>
              <p class="text-text-primary text-xs">{{ user.password_changed_at ? formatLastSeen(user.password_changed_at, getPlacementTimezone(user)) : '-' }}</p>
            </div>
          </div>
        </div>

        <div v-if="!isReadOnlyAccess" class="flex items-center justify-end gap-2 pt-2 border-t border-surface-700/50">
          <button @click="openEditModal(user)"
            class="btn-sm btn-outline text-blue-400 border-surface-700 hover:bg-surface-800">
            <Edit :size="14" class="mr-1" /> Edit
          </button>
          <button @click="permanentDeleteUser(user.id)"
            class="btn-sm btn-outline text-red-400 border-surface-700 hover:bg-surface-800">
            <Trash2 :size="14" class="mr-1" /> Hapus
          </button>
        </div>
      </div>

      <!-- Mobile Pagination -->
      <div v-if="totalPages > 1" class="col-span-full pt-4 flex items-center justify-center gap-4">
        <button @click="goToPage(currentPage - 1)" :disabled="currentPage === 1"
          class="btn btn-secondary px-4 h-10 rounded-xl disabled:opacity-30">
          <ChevronLeft :size="18" />
        </button>
        <span class="text-xs font-black uppercase text-text-secondary tracking-widest">Halaman {{ currentPage }} dari {{ totalPages }}</span>
        <button @click="goToPage(currentPage + 1)" :disabled="currentPage === totalPages"
          class="btn btn-secondary px-4 h-10 rounded-xl disabled:opacity-30">
          <ChevronRight :size="18" />
        </button>
      </div>
    </div>

    <!-- Add/Edit Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="closeModal"></div>

        <div
          class="relative bg-surface-800 rounded-2xl border border-surface-700 w-full max-w-lg p-6 shadow-2xl animate-in zoom-in duration-200 max-h-[90vh] overflow-y-auto">
          <button @click="closeModal"
            class="absolute top-4 right-4 p-2 text-text-secondary hover:text-text-primary transition-colors">
            <X :size="20" />
          </button>

          <h3 class="text-xl font-bold text-text-primary mb-6 flex items-center gap-2">
            <UserPlus v-if="!editingUser" class="text-primary-500" :size="24" />
            <Edit v-else class="text-blue-500" :size="24" />
            {{ editingUser ? "Edit User" : "Tambah User Baru" }}
          </h3>

          <form @submit.prevent="saveUser" class="space-y-4">
            <div>
              <label class="label">Nama Lengkap</label>
              <input v-model="form.full_name" type="text" class="input" placeholder="Nama Lengkap..." required />
            </div>

            <div>
              <label class="label">Username</label>
              <input v-model="form.username" class="input" placeholder="username" required />
            </div>

            <!-- Password -->
            <div>
              <label class="label">Password</label>
              <div class="relative">
                <input v-model="form.password" :type="showPassword ? 'text' : 'password'" class="input pr-10"
                  placeholder="********" required />
                <button type="button" @click="showPassword = !showPassword"
                  class="absolute right-3 top-2.5 text-text-secondary hover:text-text-primary">
                  <Eye v-if="!showPassword" :size="18" />
                  <EyeOff v-else :size="18" />
                </button>
              </div>
            </div>

                        <!-- Transaction PIN Reset -->
            <div v-if="editingUser" class="p-4 rounded-2xl bg-amber-500/5 border border-amber-500/20 space-y-3">
              <div class="flex items-center gap-2 text-amber-500">
                <Shield :size="18" />
                <h4 class="text-sm font-bold uppercase tracking-wider">PIN Transaksi</h4>
              </div>

              <div v-if="editingUser.pin_reset_requested_at"
                class="flex items-start gap-2 p-2 rounded-lg bg-red-500/10 border border-red-500/20">
                <AlertCircle class="text-red-500 shrink-0 mt-0.5" :size="16" />
                <p class="text-[11px] text-red-400 leading-tight">
                  User ini meminta reset PIN pada {{ formatDate(editingUser.pin_reset_requested_at) }}.
                  Masukkan PIN baru di bawah untuk mereset.
                </p>
              </div>

              <div>
                <label class="label">PIN Baru (4 Digit)</label>
                <input v-model="form.transaction_pin" type="text" maxlength="4" class="input font-mono"
                  placeholder="Abaikan jika tidak ingin merubah PIN"
                  @input="form.transaction_pin = form.transaction_pin.replace(/\D/g, '')" />
                <p class="text-[10px] text-text-secondary mt-1">Sifatnya opsional, digunakan jika user lupa PIN.</p>
              </div>
            </div>

            <div v-if="isSuperAdmin || !(editingUser && form.role === 'inventory')" class="grid grid-cols-1 md:grid-cols-1 gap-4">
              <div>
                <label class="label">Role</label>
                <select v-model="form.role" class="input" required :disabled="!isSuperAdmin && !!editingUser">
                  <option value="">Pilih Role</option>
                  <option v-for="role in filteredRolesOptions" :key="role.value" :value="role.value">{{ role.label
                  }}</option>
                </select>
              </div>
            </div>

            <!-- Inventory Placement Type Selector -->
            <div v-if="form.role === 'inventory'" class="animate-in fade-in slide-in-from-top-2">
              <label class="label">Tipe Penempatan Inventory</label>
              <select v-model="inventoryPlacementType" class="input">
                <option value="branch">Cabang Fisik</option>
                <option value="warehouse">Gudang</option>
                <option value="online_shop">Toko Online</option>
              </select>
            </div>

            <!-- Dynamic Placement Selection -->
            <div v-if="form.role && placementType !== 'none'" class="animate-in fade-in slide-in-from-top-2">
              <label class="label">{{ placementLabel }}</label>

              <!-- Audit/Leader Multi-Selection with Cleaner UI -->
              <div v-if="placementType === 'audit'" class="space-y-4">
                <!-- Dropdown to select which list to view -->
                <div>
                  <label class="label mb-2">Pilih Jenis Lokasi</label>
                  <select v-model="selectedMultiPlacementType" class="input">
                    <option value="physical">Cabang Fisik</option>
                    <option value="online">Toko Online</option>
                    <option value="warehouse">Gudang</option>
                    <option value="distributor">Distributor</option>
                  </select>
                </div>

                <!-- Checkboxes container -->
                <div class="border border-surface-700 rounded-xl bg-surface-900/50 p-3 max-h-52 overflow-y-auto">
                  <!-- Physical Branches -->
                  <div v-if="selectedMultiPlacementType === 'physical'" class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <label v-for="b in availableBranches" :key="b.id"
                      class="relative flex items-center justify-between p-3 rounded-xl cursor-pointer transition-all duration-200 border"
                      :class="form.selected_branches?.includes(b.id) ? 'bg-blue-500/10 border-blue-500/50 shadow-[0_0_15px_rgba(59,130,246,0.1)]' : 'bg-surface-800 border-surface-700 hover:border-surface-600'">
                      <input type="checkbox" :value="b.id" v-model="form.selected_branches" class="sr-only">
                      <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 transition-colors"
                          :class="form.selected_branches?.includes(b.id) ? 'bg-blue-500 text-white' : 'bg-surface-700 text-text-secondary'">
                          <Building class="w-4 h-4" />
                        </div>
                        <span class="text-sm font-medium transition-colors"
                          :class="form.selected_branches?.includes(b.id) ? 'text-blue-400' : 'text-text-primary'">
                          {{ b.name }}
                        </span>
                      </div>
                      <Check v-if="form.selected_branches?.includes(b.id)" class="w-4 h-4 text-blue-500 shrink-0" />
                    </label>
                    <p v-if="availableBranches.length === 0"
                      class="text-sm text-text-secondary italic col-span-full text-center py-4">Tidak ada cabang fisik
                      aktif.</p>
                  </div>

                  <!-- Online Shops -->
                  <div v-if="selectedMultiPlacementType === 'online'" class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <label v-for="s in availableOnlineShops" :key="s.id"
                      class="relative flex items-center justify-between p-3 rounded-xl cursor-pointer transition-all duration-200 border"
                      :class="form.selected_online_shops?.includes(s.id) ? 'bg-orange-500/10 border-orange-500/50 shadow-[0_0_15px_rgba(249,115,22,0.1)]' : 'bg-surface-800 border-surface-700 hover:border-surface-600'">
                      <input type="checkbox" :value="s.id" v-model="form.selected_online_shops" class="sr-only">
                      <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 transition-colors"
                          :class="form.selected_online_shops?.includes(s.id) ? 'bg-orange-500 text-white' : 'bg-surface-700 text-text-secondary'">
                          <MapPin class="w-4 h-4" />
                        </div>
                        <span class="text-sm font-medium transition-colors"
                          :class="form.selected_online_shops?.includes(s.id) ? 'text-orange-400' : 'text-text-primary'">
                          {{ s.name }}
                        </span>
                      </div>
                      <Check v-if="form.selected_online_shops?.includes(s.id)"
                        class="w-4 h-4 text-orange-500 shrink-0" />
                    </label>
                    <p v-if="availableOnlineShops.length === 0"
                      class="text-sm text-text-secondary italic col-span-full text-center py-4">Tidak ada toko online
                      aktif.</p>
                  </div>

                  <!-- Warehouses -->
                  <div v-if="selectedMultiPlacementType === 'warehouse'" class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <label v-for="w in availableWarehouses" :key="w.id"
                      class="relative flex items-center justify-between p-3 rounded-xl cursor-pointer transition-all duration-200 border"
                      :class="form.selected_warehouses?.includes(w.id) ? 'bg-purple-500/10 border-purple-500/50 shadow-[0_0_15px_rgba(168,85,247,0.1)]' : 'bg-surface-800 border-surface-700 hover:border-surface-600'">
                      <input type="checkbox" :value="w.id" v-model="form.selected_warehouses" class="sr-only">
                      <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 transition-colors"
                          :class="form.selected_warehouses?.includes(w.id) ? 'bg-purple-500 text-white' : 'bg-surface-700 text-text-secondary'">
                          <Building class="w-4 h-4" />
                        </div>
                        <span class="text-sm font-medium transition-colors"
                          :class="form.selected_warehouses?.includes(w.id) ? 'text-purple-400' : 'text-text-primary'">
                          {{ w.name }}
                        </span>
                      </div>
                      <Check v-if="form.selected_warehouses?.includes(w.id)" class="w-4 h-4 text-purple-500 shrink-0" />
                    </label>
                    <p v-if="availableWarehouses.length === 0"
                      class="text-sm text-text-secondary italic col-span-full text-center py-4">Tidak ada gudang
                      aktif.
                    </p>
                  </div>

                  <!-- Distributors -->
                  <div v-if="selectedMultiPlacementType === 'distributor'"
                    class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <label v-for="d in availableDistributors" :key="d.id"
                      class="relative flex items-center justify-between p-3 rounded-xl cursor-pointer transition-all duration-200 border"
                      :class="form.selected_distributors?.includes(d.id) ? 'bg-emerald-500/10 border-emerald-500/50 shadow-[0_0_15px_rgba(16,185,129,0.1)]' : 'bg-surface-800 border-surface-700 hover:border-surface-600'">
                      <input type="checkbox" :value="d.id" v-model="form.selected_distributors" class="sr-only">
                      <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 transition-colors"
                          :class="form.selected_distributors?.includes(d.id) ? 'bg-emerald-500 text-white' : 'bg-surface-700 text-text-secondary'">
                          <MapPin class="w-4 h-4" />
                        </div>
                        <span class="text-sm font-medium transition-colors"
                          :class="form.selected_distributors?.includes(d.id) ? 'text-emerald-400' : 'text-text-primary'">
                          {{ d.name }}
                        </span>
                      </div>
                      <Check v-if="form.selected_distributors?.includes(d.id)"
                        class="w-4 h-4 text-emerald-500 shrink-0" />
                    </label>
                    <p v-if="availableDistributors.length === 0"
                      class="text-sm text-text-secondary italic col-span-full text-center py-4">Tidak ada distributor
                      aktif.</p>
                  </div>
                </div>

                <!-- Selections Summary -->
                <div class="flex flex-wrap gap-2 text-xs text-text-secondary mt-2">
                  <span v-if="form.selected_branches?.length">Cabang Fisik: <strong class="text-blue-400">{{
                    form.selected_branches.length }}</strong> terpilih</span>
                  <span v-if="form.selected_online_shops?.length">Toko Online: <strong class="text-blue-400">{{
                    form.selected_online_shops.length }}</strong> terpilih</span>
                  <span v-if="form.selected_warehouses?.length">Gudang: <strong class="text-blue-400">{{
                    form.selected_warehouses.length }}</strong> terpilih</span>
                  <span v-if="form.selected_distributors?.length">Distributor: <strong class="text-blue-400">{{
                    form.selected_distributors.length }}</strong> terpilih</span>
                </div>
              </div>

              <!-- Warehouse Select -->
              <select v-else-if="placementType === 'warehouse'" v-model="form.warehouse_id" class="input">
                <option value="">Pilih Gudang...</option>
                <option v-for="w in availableWarehouses" :key="w.id" :value="w.id">{{ w.name }} ({{ w.code }})
                </option>
              </select>

              <!-- Distributor Select -->
              <select v-else-if="placementType === 'distributor'" v-model="form.distributor_id" class="input">
                <option value="">Pilih Distributor...</option>
                <option v-for="d in availableDistributors" :key="d.id" :value="d.id">{{ d.name }}</option>
              </select>

              <!-- Online Shop Select -->
              <select v-else-if="placementType === 'online_shop'" v-model="form.online_shop_id" class="input">
                <option value="">Pilih Toko Online...</option>
                <option v-for="s in availableOnlineShops" :key="s.id" :value="s.id">{{ s.name }} ({{ s.platform }})
                </option>
              </select>

              <!-- Branch Select (Default) -->
              <select v-else v-model="form.branch_id" class="input">
                <option value="">Pilih Cabang Fisik...</option>
                <option v-for="b in availableBranches" :key="b.id" :value="b.id">{{ b.name }}</option>
              </select>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-surface-700/50">
              <button type="button" @click="closeModal"
                class="btn text-text-secondary hover:text-text-primary">Batal</button>
              <button type="submit" class="btn btn-primary" :disabled="isSaving">
                <Loader2 v-if="isSaving" class="animate-spin mr-2" :size="18" />
                {{ isSaving ? 'Menyimpan...' : 'Simpan User' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
@reference "../../style.css";

.label {
  @apply block text-xs font-medium text-text-secondary mb-1.5 uppercase tracking-wide;
}

.input {
  @apply w-full bg-surface-900 border border-surface-700 rounded-xl px-4 py-2.5 text-text-primary focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition-all placeholder:text-text-secondary;
}

.animate-in {
  animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(5px);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>

