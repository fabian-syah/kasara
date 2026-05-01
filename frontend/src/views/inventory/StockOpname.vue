<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import {
    ArrowLeft, RefreshCw, Search, Smartphone, Package, BarChart3, Box,
    Layers, Tag, Truck, ChevronRight, ToggleLeft, ToggleRight, HardDrive, ListFilter,
    Sparkles, Printer, Copy, FileSpreadsheet, History, Download, Clock, Calendar
} from 'lucide-vue-next';
import { inventory as inventoryApi } from '../../api/axios';
import { useToast } from '../../composables/useToast';

import { useAuthStore } from '../../store/auth';
import axios from '../../api/axios';

const router = useRouter();
const toast = useToast();
const authStore = useAuthStore();

const loading = ref(false);
const rawHpItems = ref([]);
const rawNonHpItems = ref([]);
const currentView = ref('menu');

// Download Center Date Range
const exportMode = ref('daily'); // 'daily' or 'monthly'
const exportMonth = ref(new Date().toISOString().slice(0, 7)); // 'YYYY-MM'
const exportStartDate = ref(new Date().toISOString().split('T')[0]);
const exportEndDate = ref(new Date().toISOString().split('T')[0]);
const activeExportButton = ref('today');

const minExportDate = computed(() => {
    const d = new Date();
    d.setDate(d.getDate() - 6);
    return d.toISOString().split('T')[0];
});

const maxExportDate = computed(() => {
    return new Date().toISOString().split('T')[0];
});

const setExportRange = (type) => {
    const today = new Date();
    exportMode.value = 'daily';
    activeExportButton.value = type;

    if (type === 'today') {
        const d = today.toISOString().split('T')[0];
        exportStartDate.value = d;
        exportEndDate.value = d;
    } else if (type === 'yesterday') {
        const yesterday = new Date(today);
        yesterday.setDate(yesterday.getDate() - 1);
        const d = yesterday.toISOString().split('T')[0];
        exportStartDate.value = d;
        exportEndDate.value = d;
    } else if (type === 'month') {
        exportMode.value = 'monthly';
        // When switching to month mode, we'll use exportMonth ref
        updateMonthRange();
    }
};

const updateMonthRange = () => {
    if (!exportMonth.value) return;
    const [year, month] = exportMonth.value.split('-').map(Number);
    const start = new Date(year, month - 1, 1);
    const end = new Date(year, month, 0);
    exportStartDate.value = start.toISOString().split('T')[0];
    exportEndDate.value = end.toISOString().split('T')[0];
};

watch(exportMonth, () => {
    if (exportMode.value === 'monthly') {
        updateMonthRange();
    }
});

// Location Filter logic
const locations = ref([])
const selectedLocationKey = ref('all')

const showBrandLocation = ref(false)
const showTypeLocation = ref(false)
const showConditionLocation = ref(false)
const showDistributorLocation = ref(false)
const showCategoryLocation = ref(false)

const selectedBranchId = computed(() => {
    if (selectedLocationKey.value === 'all' || !selectedLocationKey.value.startsWith('B:')) return null;
    return selectedLocationKey.value.split(':')[1];
})

const selectedOnlineShopId = computed(() => {
    if (selectedLocationKey.value === 'all' || !selectedLocationKey.value.startsWith('S:')) return null;
    return selectedLocationKey.value.split(':')[1];
})

const selectedWarehouseId = computed(() => {
    if (selectedLocationKey.value === 'all' || !selectedLocationKey.value.startsWith('W:')) return null;
    return selectedLocationKey.value.split(':')[1];
})

const selectedDistributorId = computed(() => {
    if (selectedLocationKey.value === 'all' || !selectedLocationKey.value.startsWith('D:')) return null;
    return selectedLocationKey.value.split(':')[1];
})

const canFilterBranch = computed(() => {
    const role = (authStore.userRole || '').toLowerCase();
    const privilegedRoles = ['super_admin', 'audit', 'owner', 'leader', 'analist', 'admin_produk'];
    return privilegedRoles.some(r => role.includes(r));
})

const fetchBranches = async () => {
    try {
        const [branchRes, shopRes, warehouseRes, distributorRes, userRes] = await Promise.all([
            axios.get('/branches'),
            axios.get('/online-shops'),
            axios.get('/warehouses'),
            axios.get('/distributors'),
            axios.get('/user')
        ])

        const allBranches = (branchRes.data.data || branchRes.data || []).map(b => ({ ...b, type: 'branch' }));
        const allShops = (shopRes.data.data || shopRes.data || []).map(s => ({ ...s, type: 'online_shop' }));
        const allWarehouses = (warehouseRes.data.data || warehouseRes.data || []).map(w => ({ ...w, type: 'warehouse' }));
        const allDistributors = (distributorRes.data.data || distributorRes.data || []).map(d => ({ ...d, type: 'distributor' }));
        const allLocations = [...allBranches, ...allShops, ...allWarehouses, ...allDistributors];

        const user = userRes.data.user || userRes.data.data || userRes.data;
        const role = (authStore.userRole || '').toLowerCase();

        const alwaysGlobalRoles = ['super_admin', 'owner', 'admin_produk', 'analist'];
        const isAlwaysGlobal = alwaysGlobalRoles.some(r => role.includes(r));

        let allowedBranchIds = [];
        if (user?.branch_id) allowedBranchIds.push(user.branch_id);
        let allowedShopIds = [];
        if (user?.online_shop_id) allowedShopIds.push(user.online_shop_id);
        let allowedWarehouseIds = [];
        if (user?.warehouse_id) allowedWarehouseIds.push(user.warehouse_id);
        let allowedDistributorIds = [];
        if (user?.distributor_id) allowedDistributorIds.push(user.distributor_id);

        if (user?.placements && Array.isArray(user.placements)) {
            user.placements.forEach(p => {
                if (p.model_type === 'branch') allowedBranchIds.push(p.model_id);
                if (p.model_type === 'online_shop') allowedShopIds.push(p.model_id);
                if (p.model_type === 'warehouse') allowedWarehouseIds.push(p.model_id);
                if (p.model_type === 'distributor') allowedDistributorIds.push(p.model_id);
            });
        }

        allowedBranchIds = [...new Set(allowedBranchIds.map(id => Number(id)))];
        allowedShopIds = [...new Set(allowedShopIds.map(id => Number(id)))];
        allowedWarehouseIds = [...new Set(allowedWarehouseIds.map(id => Number(id)))];
        allowedDistributorIds = [...new Set(allowedDistributorIds.map(id => Number(id)))];

        const hasAnyRestriction = allowedBranchIds.length > 0 || allowedShopIds.length > 0 || allowedWarehouseIds.length > 0 || allowedDistributorIds.length > 0;

        const exclusionWords = ['testing', 'trial', 'anu', 'huft'];
        const filteredAllLocations = allLocations.filter(loc => {
            // User said: hide test data only for super_admin and analist
            if (!isAlwaysGlobal) return true;
            const name = (loc.name || '').toLowerCase();
            return !exclusionWords.some(word => name.includes(word));
        });

        if (isAlwaysGlobal) {
            locations.value = filteredAllLocations;
        } else if (hasAnyRestriction) {
            locations.value = filteredAllLocations.filter(loc => {
                if (loc.type === 'branch') return allowedBranchIds.includes(Number(loc.id));
                if (loc.type === 'online_shop') return allowedShopIds.includes(Number(loc.id));
                if (loc.type === 'warehouse') return allowedWarehouseIds.includes(Number(loc.id));
                if (loc.type === 'distributor') return allowedDistributorIds.includes(Number(loc.id));
                return false;
            });

            if (locations.value.length === 1) {
                const loc = locations.value[0];
                selectedLocationKey.value = `${loc.type === 'branch' ? 'B' : loc.type === 'online_shop' ? 'S' : loc.type === 'warehouse' ? 'W' : 'D'}:${loc.id}`;
            }
        } else {
            locations.value = [];
        }
    } catch (error) {
        console.error('Error fetching locations:', error)
    }
}

const showPerGb = ref(false);
const showBrandCondition = ref(false);
const showTypeCondition = ref(false);
const showConditionBrand = ref(false);
const showConditionType = ref(false);
const showDistributorBrand = ref(false);
const showDistributorType = ref(false);
const showDistributorGb = ref(false);
const showCategoryBrand = ref(false);
const showCategoryType = ref(false);
const showBrandType = ref(false);
const typeSortOrder = ref('available'); // 'available', 'name', 'brand'
const itemMode = ref('hp'); // 'hp' or 'non-hp'
const searchQuery = ref('');

const fetchAllInventory = async () => {
    if (loading.value) return;
    loading.value = true;
    try {
        const role = (authStore.userRole || '').toLowerCase();
        const alwaysGlobalRoles = ['super_admin', 'owner', 'admin_produk', 'analist'];
        const isAlwaysGlobal = alwaysGlobalRoles.some(r => role.includes(r));

        let bId = selectedBranchId.value;
        let sId = selectedOnlineShopId.value;
        let wId = selectedWarehouseId.value;
        let dId = selectedDistributorId.value;

        if (selectedLocationKey.value === 'all' && !isAlwaysGlobal) {
            if (locations.value.length === 1) {
                const loc = locations.value[0];
                if (loc.type === 'branch') bId = loc.id;
                else if (loc.type === 'online_shop') sId = loc.id;
                else if (loc.type === 'warehouse') wId = loc.id;
                else if (loc.type === 'distributor') dId = loc.id;
            }
        }

        const queryParams = {
            branch_id: bId || undefined,
            online_shop_id: sId || undefined,
            warehouse_id: wId || undefined,
            distributor_id: dId || undefined,
            per_page: 500 // Increased for efficiency
        };

        const fetchType = async (type) => {
            const firstResponse = await inventoryApi.list({ ...queryParams, page: 1, type: type === 'hp' ? undefined : 'non-hp' });
            const firstData = firstResponse.data;
            
            if (!firstData || !firstData.data) return [];
            
            const exclusionWords = ['testing', 'trial', 'anu', 'huft'];
            const filterFn = (item) => {
                // User said: hide test data only when shown to super_admin and analist
                if (!isAlwaysGlobal) return true; 
                const locName = (item.placement_name || '').toLowerCase();
                return !exclusionWords.some(word => locName.includes(word));
            };

            let all = firstData.data.filter(filterFn);

            const lastPage = firstData.last_page;
            if (lastPage > 1) {
                const pagePromises = [];
                for (let p = 2; p <= lastPage; p++) {
                    pagePromises.push(inventoryApi.list({ ...queryParams, page: p, type: type === 'hp' ? undefined : 'non-hp' }));
                }
                
                const otherResponses = await Promise.all(pagePromises);
                otherResponses.forEach(res => {
                    if (res.data && res.data.data) {
                        const filtered = res.data.data.filter(filterFn);
                        all = all.concat(filtered);
                    }
                });
            }
            return all;
        };

        // Fetch both in parallel
        const [hpItems, nonHpItems] = await Promise.all([
            fetchType('hp'),
            fetchType('non-hp')
        ]);

        rawHpItems.value = hpItems;
        rawNonHpItems.value = nonHpItems;
    } catch (error) {
        console.error('Fetch Error:', error);
        toast.error('Gagal memuat data inventory.');
    } finally {
        loading.value = false;
    }
};

onMounted(async () => {
    // ALWAYS resolve user's location - even non-privileged users need branch scoping
    await fetchBranches();
    fetchAllInventory();
});

// Watch location changes
watch(selectedLocationKey, () => {
    fetchAllInventory();
});

// Active items based on mode
const isHpMode = computed(() => itemMode.value === 'hp');
const activeItems = computed(() => isHpMode.value ? rawHpItems.value : rawNonHpItems.value);

// Helper: get available qty for an item
const getAvailable = (item) => {
    if (isHpMode.value) return ['available', 'booking', 'process'].includes(item.status) ? 1 : 0;
    return item.quantity || item.balance || 0;
};
const getTotal = (item) => {
    if (isHpMode.value) return 1;
    return item.quantity || item.balance || 0;
};

// Summary stats for landing
const summaryStats = computed(() => {
    const hpAvail = rawHpItems.value.filter(i => ['available', 'booking', 'process'].includes(i.status)).length;
    const nonHpAvail = rawNonHpItems.value.reduce((s, i) => s + (i.quantity || i.balance || 0), 0);
    return {
        totalHp: hpAvail,
        totalNonHp: nonHpAvail,
        totalBrands: new Set(activeItems.value.map(i => i.product?.brand || '-')).size,
        totalTypes: new Set(activeItems.value.map(i => i.product?.name || '-')).size,
        totalDistributors: new Set(activeItems.value.map(i => i.distributor?.name || i.latest_distributor || i.latest_supplier || '').filter(Boolean)).size
    };
});

// ===== BRAND REPORT =====
const conditionLabels = {
    'new': 'Baru (New)',
    'second': 'Second',
    'ex_ibox': 'Ex-iBox',
    'ex_inter': 'Ex-Inter',
    'refurbished': 'Refurbished',
    'service': 'Service/Retur',
    'other': 'Lainnya'
};
const copyToClipboard = () => {
    const report = newEraReport.value;
    if (!report || !report.stats) {
        alert('Data laporan belum tersedia.');
        return;
    }

    const dateStr = new Date().toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
    const branch = activeBranchName.value;

    let text = `*LAPORAN STOCK*\n`;
    text += `*${branch}*\n`;
    text += `*${dateStr}*\n`;
    text += `====================\n\n`;

    text += `*RINGKASAN TOTAL*\n`;
    text += `iPhone New: *${report.stats.iphone_new || 0}*\n`;
    text += `iPhone Second: *${report.stats.iphone_scd || 0}*\n`;
    text += `iPhone Ex iBox: *${report.stats.iphone_ex_ibox || 0}*\n`;
    text += `Android New: *${report.stats.android_new || 0}*\n`;
    text += `Android Second: *${report.stats.android_scd || 0}*\n`;
    text += `Laptop: *${report.stats.laptop || 0}*\n`;
    text += `TV: *${report.stats.tv || 0}*\n`;
    text += `--------------------\n`;
    text += `TOTAL HP: *${report.stats.total_hp || 0}*\n\n`;

    text += `*RINCIAN UNIT*\n\n`;

    const hasIphone = report.details.iphone_new.length > 0 || report.details.iphone_scd.length > 0 || report.details.iphone_ex_ibox.length > 0;
    if (hasIphone) {
        text += `*iPhone*\n`;
        text += `──────────────────\n`;
        if (report.details.iphone_new.length > 0) {
            text += `\n▸ *New*\n`;
            report.details.iphone_new.forEach(it => { text += `   ${it.name} : *${it.qty}*\n`; });
        }
        if (report.details.iphone_scd.length > 0) {
            text += `\n▸ *Second*\n`;
            report.details.iphone_scd.forEach(it => { text += `   ${it.name} : *${it.qty}*\n`; });
        }
        if (report.details.iphone_ex_ibox.length > 0) {
            text += `\n▸ *Ex iBox*\n`;
            report.details.iphone_ex_ibox.forEach(it => { text += `   ${it.name} : *${it.qty}*\n`; });
        }
        text += `\n`;
    }

    const hasAndroid = report.details.android_new.length > 0 || report.details.android_scd.length > 0;
    if (hasAndroid) {
        text += `*Android*\n`;
        text += `──────────────────\n`;
        if (report.details.android_new.length > 0) {
            text += `\n▸ *New*\n`;
            report.details.android_new.forEach(it => { text += `   ${it.name} : *${it.qty}*\n`; });
        }
        if (report.details.android_scd.length > 0) {
            text += `\n▸ *Second*\n`;
            report.details.android_scd.forEach(it => { text += `   ${it.name} : *${it.qty}*\n`; });
        }
        text += `\n`;
    }

    const hasLaptop = report.details.laptop.length > 0;
    if (hasLaptop) {
        text += `*Laptop*\n`;
        text += `──────────────────\n`;
        report.details.laptop.forEach(it => { text += `   ${it.name} : *${it.qty}*\n`; });
        text += `\n`;
    }

    const hasTv = report.details.tv.length > 0;
    if (hasTv) {
        text += `*TV*\n`;
        text += `──────────────────\n`;
        report.details.tv.forEach(it => { text += `   ${it.name} : *${it.qty}*\n`; });
        text += `\n`;
    }

    text += `━━━━━━━━━━━━━━━━━━━━\n`;

    const fallbackCopy = (t) => {
        const textArea = document.createElement("textarea");
        textArea.value = t;
        textArea.style.position = "fixed";
        textArea.style.left = "-9999px";
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        try {
            document.execCommand('copy');
            toast.success('Laporan berhasil disalin!');
        } catch (err) {
            toast.error('Gagal menyalin laporan.');
        }
        document.body.removeChild(textArea);
    };

    if (!navigator.clipboard) {
        fallbackCopy(text);
    } else {
        navigator.clipboard.writeText(text)
            .then(() => toast.success('Laporan berhasil disalin!'))
            .catch(() => fallbackCopy(text));
    }
};

const brandReport = computed(() => {
    const map = new Map();
    activeItems.value.forEach(item => {
        const brand = item.product?.brand || 'Lainnya';
        if (!map.has(brand)) {
            map.set(brand, {
                brand,
                available: 0,
                tree: new Map() // Type -> Condition
            });
        }
        const entry = map.get(brand);
        const avail = getAvailable(item);
        entry.available += avail;

        const type = item.product?.name || 'Unknown';
        const condKey = item.condition || 'other';

        if (!entry.tree.has(type)) entry.tree.set(type, { label: type, available: 0, conditions: new Map() });
        const tNode = entry.tree.get(type);
        tNode.available += avail;

        if (!tNode.conditions.has(condKey)) {
            tNode.conditions.set(condKey, {
                label: conditionLabels[condKey] || condKey,
                condition: condKey,
                available: 0
            });
        }
        tNode.conditions.get(condKey).available += avail;

        // Location Breakdown
        if (!entry.locations) entry.locations = new Map();
        const locName = item.placement_name || 'Tanpa Lokasi';
        entry.locations.set(locName, (entry.locations.get(locName) || 0) + avail);
    });

    const mapToLocArr = (locMap) => {
        return Array.from(locMap.entries())
            .map(([name, qty]) => ({ name, qty }))
            .sort((a, b) => b.qty - a.qty);
    };

    return Array.from(map.values())
        .map(e => ({
            ...e,
            locations: mapToLocArr(e.locations || new Map()),
            tree: Array.from(e.tree.values()).map(t => ({
                ...t,
                conditions: Array.from(t.conditions.values()).sort((a, b) => b.available - a.available)
            })).sort((a, b) => b.available - a.available)
        }))
        .sort((a, b) => b.available - a.available);
});

// ===== TYPE REPORT =====
const typeReport = computed(() => {
    const map = new Map();
    activeItems.value.forEach(item => {
        const name = item.product?.name || 'Unknown';
        const brand = item.product?.brand || '-';
        const key = `${brand}||${name}`;
        if (!map.has(key)) {
            map.set(key, {
                name,
                brand,
                available: 0,
                tree: new Map() // GB -> Condition
            });
        }
        const entry = map.get(key);
        const avail = getAvailable(item);
        entry.available += avail;

        const ram = item.ram || '-';
        const storage = item.storage || '-';
        const gb = ram !== '-' && storage !== '-' ? `${ram}/${storage}` : (storage !== '-' ? storage : ram);
        const condKey = item.condition || 'other';

        if (!entry.tree.has(gb)) entry.tree.set(gb, { label: gb, available: 0, conditions: new Map() });
        const gNode = entry.tree.get(gb);
        gNode.available += avail;

        if (!gNode.conditions.has(condKey)) {
            gNode.conditions.set(condKey, {
                label: conditionLabels[condKey] || condKey,
                condition: condKey,
                available: 0
            });
        }
        gNode.conditions.get(condKey).available += avail;

        // Location Breakdown
        if (!entry.locations) entry.locations = new Map();
        const locName = item.placement_name || 'Tanpa Lokasi';
        entry.locations.set(locName, (entry.locations.get(locName) || 0) + avail);
    });

    const mapToLocArr = (locMap) => {
        return Array.from(locMap.entries())
            .map(([name, qty]) => ({ name, qty }))
            .sort((a, b) => b.qty - a.qty);
    };

    const result = Array.from(map.values()).map(e => ({
        ...e,
        locations: mapToLocArr(e.locations || new Map()),
        tree: Array.from(e.tree.values()).map(g => ({
            ...g,
            conditions: Array.from(g.conditions.values()).sort((a, b) => b.available - a.available)
        })).sort((a, b) => b.available - a.available)
    }));

    // Sorting
    if (typeSortOrder.value === 'name') {
        result.sort((a, b) => a.name.localeCompare(b.name));
    } else if (typeSortOrder.value === 'brand') {
        result.sort((a, b) => a.brand.localeCompare(b.brand) || a.name.localeCompare(b.name));
    } else {
        result.sort((a, b) => b.available - a.available);
    }

    return result;
});

// ===== CONDITION REPORT =====
const conditionReport = computed(() => {
    const map = new Map();
    activeItems.value.forEach(item => {
        const cond = item.condition || 'unknown';
        if (!map.has(cond)) {
            map.set(cond, {
                condition: cond,
                label: conditionLabels[cond] || cond,
                available: 0,
                tree: new Map() // Brand -> Type -> GB
            });
        }
        const entry = map.get(cond);
        const avail = getAvailable(item);
        entry.available += avail;

        if (isHpMode.value) {
            const brand = item.product?.brand || 'Lainnya';
            const type = item.product?.name || 'Unknown';
            const ram = item.ram || '-';
            const storage = item.storage || '-';
            const gb = ram !== '-' && storage !== '-' ? `${ram}/${storage}` : (storage !== '-' ? storage : ram);

            if (!entry.tree.has(brand)) entry.tree.set(brand, { label: brand, available: 0, types: new Map() });
            const bNode = entry.tree.get(brand);
            bNode.available += avail;

            if (!bNode.types.has(type)) bNode.types.set(type, { label: type, available: 0, gbs: new Map() });
            const tNode = bNode.types.get(type);
            tNode.available += avail;

            if (!tNode.gbs.has(gb)) tNode.gbs.set(gb, { label: gb, available: 0 });
            tNode.gbs.get(gb).available += avail;
        }

        // Location Breakdown
        if (!entry.locations) entry.locations = new Map();
        const locName = item.placement_name || 'Tanpa Lokasi';
        entry.locations.set(locName, (entry.locations.get(locName) || 0) + avail);
    });

    const mapToLocArr = (locMap) => {
        return Array.from(locMap.entries())
            .map(([name, qty]) => ({ name, qty }))
            .sort((a, b) => b.qty - a.qty);
    };

    return Array.from(map.values())
        .map(e => ({
            ...e,
            locations: mapToLocArr(e.locations || new Map()),
            tree: Array.from(e.tree.values()).map(b => ({
                ...b,
                types: Array.from(b.types.values()).map(t => ({
                    ...t,
                    gbs: Array.from(t.gbs.values()).sort((a, b) => b.available - a.available)
                })).sort((a, b) => b.available - a.available)
            })).sort((a, b) => b.available - a.available)
        }))
        .sort((a, b) => b.available - a.available);
});

// ===== DISTRIBUTOR REPORT =====
const distributorReport = computed(() => {
    const map = new Map();
    activeItems.value.forEach(item => {
        const distName = item.distributor?.name || item.latest_distributor || item.latest_supplier || item.latestLog?.distributor?.name || 'Tidak Diketahui';
        if (!map.has(distName)) {
            map.set(distName, {
                name: distName,
                available: 0,
                tree: new Map() // Brand -> Type -> GB
            });
        }
        const entry = map.get(distName);
        const avail = getAvailable(item);
        entry.available += avail;

        if (isHpMode.value) {
            const brand = item.product?.brand || 'Lainnya';
            const type = item.product?.name || 'Unknown';
            const ram = item.ram || '-';
            const storage = item.storage || '-';
            const gb = ram !== '-' && storage !== '-' ? `${ram}/${storage}` : (storage !== '-' ? storage : ram);

            if (!entry.tree.has(brand)) entry.tree.set(brand, { label: brand, available: 0, types: new Map() });
            const bNode = entry.tree.get(brand);
            bNode.available += avail;

            if (!bNode.types.has(type)) bNode.types.set(type, { label: type, available: 0, gbs: new Map() });
            const tNode = bNode.types.get(type);
            tNode.available += avail;

            if (!tNode.gbs.has(gb)) tNode.gbs.set(gb, { label: gb, available: 0 });
            tNode.gbs.get(gb).available += avail;
        }

        // Location Breakdown
        if (!entry.locations) entry.locations = new Map();
        const locName = item.placement_name || 'Tanpa Lokasi';
        entry.locations.set(locName, (entry.locations.get(locName) || 0) + avail);
    });

    const mapToLocArr = (locMap) => {
        return Array.from(locMap.entries())
            .map(([name, qty]) => ({ name, qty }))
            .sort((a, b) => b.qty - a.qty);
    };

    return Array.from(map.values())
        .map(e => ({
            ...e,
            locations: mapToLocArr(e.locations || new Map()),
            tree: Array.from(e.tree.values()).map(b => ({
                ...b,
                types: Array.from(b.types.values()).map(t => ({
                    ...t,
                    gbs: Array.from(t.gbs.values()).sort((a, b) => b.available - a.available)
                })).sort((a, b) => b.available - a.available)
            })).sort((a, b) => b.available - a.available)
        }))
        .sort((a, b) => b.available - a.available);
});

// ===== CATEGORY REPORT (Non-HP Only) =====
const VALID_NONHP_CATEGORIES = [
    'laptop', 'TV', 'accesories', 'elektronik', 'fashion',
    'kendaraan', 'jasa', 'parfum', 'kosmetik', 'makanan'
];

const categoryReport = computed(() => {
    const map = new Map();

    // Initialize with valid categories to ensure they show up even if 0
    VALID_NONHP_CATEGORIES.forEach(c => {
        map.set(c, { category: c, available: 0, tree: new Map() });
    });

    activeItems.value.forEach(item => {
        const prodCat = (item.product?.category || '').toLowerCase();
        const prodBrand = (item.product?.brand || '').toLowerCase();
        const prodName = (item.product?.name || '').toLowerCase();

        // 1. Try to match against the user's specific list (with Indonesian synonyms)
        let category = VALID_NONHP_CATEGORIES.find(c => {
            const lowC = c.toLowerCase();
            const lowerSearch = (prodCat + ' ' + prodBrand + ' ' + prodName).toLowerCase();

            if (prodCat === lowC || prodBrand === lowC || prodName.includes(lowC)) return true;

            // Synonym mapping
            if (lowC === 'accesories') {
                const syns = ['aksesoris', 'charger', 'kabel', 'cable', 'tempered', 'case', 'headset', 'earphone', 'baterai', 'battery', 'adaptor', 'powerbank', 'anti gores', 'pelindung'];
                return syns.some(s => lowerSearch.includes(s));
            }
            if (lowC === 'elektronik') {
                const syns = ['elektronik', 'electronic', 'speaker', 'mouse', 'keyboard', 'monitor', 'pc', 'printer', 'kipas', 'fan', 'lampu', 'light', 'steker', 'colokan'];
                return syns.some(s => lowerSearch.includes(s));
            }
            if (lowC === 'fashion') {
                const syns = ['baju', 'celana', 'kaos', 'shirt', 'jaket', 'sepatu', 'tas', 'dompet', 'jam tangan', 'aksesori fashion'];
                return syns.some(s => lowerSearch.includes(s));
            }
            if (lowC === 'laptop') {
                const syns = ['notebook', 'macbook', 'rog', 'tuf', 'thinkpad', 'laptop'];
                return syns.some(s => lowerSearch.includes(s));
            }
            if (lowC === 'kendaraan') {
                const syns = ['helm', 'oli', 'motor', 'ban', 'variasi motor', 'sparepart'];
                return syns.some(s => lowerSearch.includes(s));
            }

            return false;
        });

        // 2. If no match in the 10, but the category/brand is specific (not generic)
        if (!category) {
            const genericTerms = ['non hp / non imei', 'uncategorized', 'unknown', 'lainnya', 'omset'];
            if (prodCat && !genericTerms.includes(prodCat)) {
                category = item.product.category;
            } else if (prodBrand && !genericTerms.includes(prodBrand)) {
                category = item.product.brand;
            } else {
                category = 'Lainnya';
            }
        }

        if (!map.has(category)) {
            map.set(category, {
                category,
                available: 0,
                tree: new Map() // Type -> Location
            });
        }
        const entry = map.get(category);
        const avail = getAvailable(item);
        entry.available += avail;

        const location = item.placement_name || 'Tanpa Lokasi';
        const type = item.product?.name || 'Unknown';

        if (!entry.tree.has(type)) entry.tree.set(type, { label: type, available: 0, locations: new Map() });
        const tNode = entry.tree.get(type);
        tNode.available += avail;

        if (!tNode.locations.has(location)) tNode.locations.set(location, { label: location, available: 0 });
        tNode.locations.get(location).available += avail;

        // Top-level location tracking for "Tampilkan per Cabang"
        if (!entry.locations) entry.locations = new Map();
        entry.locations.set(location, (entry.locations.get(location) || 0) + avail);
    });

    const mapToLocArr = (locMap) => {
        return Array.from(locMap.entries())
            .map(([name, qty]) => ({ name, qty }))
            .sort((a, b) => b.qty - a.qty);
    };

    return Array.from(map.values())
        .map(e => ({
            ...e,
            locations: mapToLocArr(e.locations || new Map()),
            tree: Array.from(e.tree.values()).map(t => ({
                ...t,
                types: Array.from(t.locations.values()).sort((a, b) => b.available - a.available) // Reusing 'types' property name for UI compatibility or renaming
            })).sort((a, b) => b.available - a.available)
        }))
        .sort((a, b) => b.available - a.available);
});

const activeBranchName = computed(() => {
    const role = (authStore.userRole || '').toLowerCase();
    const alwaysGlobalRoles = ['super_admin', 'owner', 'admin_produk'];
    const isAlwaysGlobal = alwaysGlobalRoles.some(r => role.includes(r));

    if (selectedLocationKey.value === 'all') {
        if (!isAlwaysGlobal && locations.value.length === 1) {
            return locations.value[0].name.toUpperCase();
        }
        if (!isAlwaysGlobal && locations.value.length > 1) {
            return 'GABUNGAN CABANG SAYA';
        }
        return 'SEMUA CABANG';
    }
    const loc = locations.value.find(l => {
        const key = `${l.type === 'branch' ? 'B' : l.type === 'online_shop' ? 'S' : l.type === 'warehouse' ? 'W' : 'D'}:${l.id}`;
        return key === selectedLocationKey.value;
    });
    return loc ? loc.name.toUpperCase() : (isAlwaysGlobal ? 'SEMUA CABANG' : 'CABANG SAYA');
});

// ===== NEW ERA REPORT (Special IMEI) =====
const newEraReport = computed(() => {
    const stats = {
        iphone_new: 0,
        iphone_scd: 0,
        iphone_ex_ibox: 0,
        android_new: 0,
        android_scd: 0,
        laptop: 0,
        tv: 0,
        total_hp: 0
    };

    const details = {
        iphone_new: new Map(),
        iphone_scd: new Map(),
        iphone_ex_ibox: new Map(),
        android_new: new Map(),
        android_scd: new Map(),
        laptop: new Map(),
        tv: new Map()
    };

    rawHpItems.value.forEach(item => {
        // Force HP detection logic: always check status for IMEI items
        const avail = ['available', 'booking', 'process'].includes(item.status) ? 1 : 0;
        if (avail <= 0) return;

        const brand = (item.product?.brand || '').toLowerCase();
        const cond = item.condition || 'second';
        const name = item.product?.name || '';
        const ram = item.ram || '';
        const storage = item.storage || '';
        const gb = ram && storage ? `${ram}/${storage}` : (storage || ram);
        const displayName = gb ? `${name} ${gb}` : name;
        const cat = (item.product?.category || '').toLowerCase();
        const spec = (item.product?.non_imei_category || '').toLowerCase();

        const isLaptop = cat.includes('laptop') || name.toLowerCase().includes('laptop') || spec.includes('laptop') ||
            ['thinkpad', 'macbook', 'notebook', 'modern 14', 'ideapad', 'rog', 'tuf'].some(k => name.toLowerCase().includes(k));

        const isTv = cat.includes('tv') || cat.includes('televisi') || name.toLowerCase().includes('tv') ||
            name.toLowerCase().includes('televisi') || spec.includes('tv');

        // Check if Laptop or TV (even if in HP data/IMEI source)
        if (isLaptop) {
            stats.laptop += avail;
            details.laptop.set(displayName, (details.laptop.get(displayName) || 0) + avail);
            return;
        }
        if (isTv) {
            stats.tv += avail;
            details.tv.set(displayName, (details.tv.get(displayName) || 0) + avail);
            return;
        }

        if (brand.includes('apple') || brand.includes('iphone')) {
            if (cond === 'new') {
                stats.iphone_new += avail;
                details.iphone_new.set(displayName, (details.iphone_new.get(displayName) || 0) + avail);
            } else if (cond === 'ex_ibox') {
                stats.iphone_ex_ibox += avail;
                details.iphone_ex_ibox.set(displayName, (details.iphone_ex_ibox.get(displayName) || 0) + avail);
            } else {
                stats.iphone_scd += avail;
                details.iphone_scd.set(displayName, (details.iphone_scd.get(displayName) || 0) + avail);
            }
            stats.total_hp += avail;
        } else if (brand.length > 0 || name.length > 0) {
            // Android / HP Others
            const brandRaw = item.product?.brand || '';
            const androidName = `${brandRaw} ${displayName}`.trim();

            if (cond === 'new') {
                stats.android_new += avail;
                details.android_new.set(androidName, (details.android_new.get(androidName) || 0) + avail);
            } else {
                stats.android_scd += avail;
                details.android_scd.set(androidName, (details.android_scd.get(androidName) || 0) + avail);
            }
            stats.total_hp += avail;
        }
    });

    rawNonHpItems.value.forEach(item => {
        // Force Non-HP detection logic: always check quantity/balance
        const avail = item.quantity || item.balance || 0;
        if (avail <= 0) return;

        const cat = (item.product?.category || '').toLowerCase();
        const name = (item.product?.name || '').toLowerCase();
        const spec = (item.product?.non_imei_category || '').toLowerCase();
        const brand = (item.product?.brand || '').toUpperCase();
        const displayName = brand ? `${brand} ${item.product?.name}` : item.product?.name;

        const isLaptop = cat.includes('laptop') || name.includes('laptop') || spec.includes('laptop') ||
            ['thinkpad', 'macbook', 'notebook', 'modern 14', 'ideapad', 'rog', 'tuf'].some(k => name.toLowerCase().includes(k));

        const isTv = cat.includes('tv') || cat.includes('televisi') || name.includes('tv') ||
            name.includes('televisi') || spec.includes('tv');

        if (isLaptop) {
            stats.laptop += avail;
            details.laptop.set(displayName, (details.laptop.get(displayName) || 0) + avail);
        } else if (isTv) {
            stats.tv += avail;
            details.tv.set(displayName, (details.tv.get(displayName) || 0) + avail);
        }
    });

    // Default fallback sorting: ALPHABETICAL
    const sortFn = (a, b) => a.name.localeCompare(b.name);
    const mapToArr = (m) => Array.from(m.entries()).map(([name, qty]) => ({ name, qty })).sort(sortFn);

    return {
        stats,
        details: {
            iphone_new: mapToArr(details.iphone_new),
            iphone_scd: mapToArr(details.iphone_scd),
            iphone_ex_ibox: mapToArr(details.iphone_ex_ibox),
            android_new: mapToArr(details.android_new),
            android_scd: mapToArr(details.android_scd),
            laptop: mapToArr(details.laptop),
            tv: mapToArr(details.tv)
        }
    };
});

// Filtered search for sub-views
const searchFilter = (items, fields) => {
    if (!searchQuery.value) return items;
    const q = searchQuery.value.toLowerCase();
    return items.filter(i => fields.some(f => String(i[f] || '').toLowerCase().includes(q)));
};

const filteredBrand = computed(() => searchFilter(brandReport.value, ['brand']));
const filteredType = computed(() => searchFilter(typeReport.value, ['name', 'brand']));
const filteredCondition = computed(() => searchFilter(conditionReport.value, ['label', 'condition']));
const filteredDistributor = computed(() => searchFilter(distributorReport.value, ['name']));
const filteredCategory = computed(() => searchFilter(categoryReport.value, ['category']));

function navigateTo(view) {
    currentView.value = view;
    searchQuery.value = '';
    resetBreakdowns();
}

function goBack() {
    currentView.value = 'menu';
    searchQuery.value = '';
    resetBreakdowns();
}

function resetBreakdowns() {
    showPerGb.value = false;
    showBrandCondition.value = false;
    showTypeCondition.value = false;
    showBrandLocation.value = false;
    showTypeLocation.value = false;
    showConditionLocation.value = false;
    showConditionBrand.value = false;
    showConditionType.value = false;
    showDistributorBrand.value = false;
    showDistributorType.value = false;
    showDistributorGb.value = false;
    showCategoryBrand.value = false;
    showCategoryType.value = false;
}

const conditionColor = (cond) => {
    const colors = {
        'new': 'text-emerald-400 bg-emerald-400/10 border-emerald-400/20',
        'second': 'text-amber-400 bg-amber-400/10 border-amber-400/20',
        'ex_ibox': 'text-blue-400 bg-blue-400/10 border-blue-400/20',
        'ex_inter': 'text-purple-400 bg-purple-400/10 border-purple-400/20',
        'refurbished': 'text-orange-400 bg-orange-400/10 border-orange-400/20',
        'service': 'text-red-400 bg-red-400/10 border-red-400/20',
    };
    return colors[cond] || 'text-gray-400 bg-gray-400/10 border-gray-400/20';
};

// ===== HISTORY STOK & EXPORT =====
const historyLoading = ref(false);
const historyData = ref({ hp: [], non_hp: [] });
const resetTime = ref('');
const downloadLogs = ref([]);
const historyDate = ref(new Date().toISOString().split('T')[0]);
const historyMode = ref('daily');


const fetchStockHistory = async () => {
    historyLoading.value = true;
    try {
        const res = await axios.get('/reports/stock-history', {
            params: {
                branch_id: selectedBranchId.value,
                online_shop_id: selectedOnlineShopId.value,
                warehouse_id: selectedWarehouseId.value,
                date: historyDate.value,
                mode: historyMode.value
            }
        });
        const data = res.data.data || { hp: [], non_hp: [] };
        
        // Secondary sort on frontend for case-insensitive A-Z
        const naturalSort = (a, b) => {
            return (a.name || '').localeCompare((b.name || ''), 'id', { numeric: true, sensitivity: 'base' });
        };
        
        if (data.hp) data.hp.sort(naturalSort);
        if (data.non_hp) data.non_hp.sort(naturalSort);
        
        historyData.value = data;
        resetTime.value = res.data.reset_time_label || res.data.reset_time;
    } catch (err) {
        toast.error(err.response?.data?.error || 'Gagal memuat history stok');
    } finally {
        historyLoading.value = false;
    }
};

    const historyDateDisplay = computed(() => {
        if (!historyDate.value) return '';
        if (historyMode.value === 'monthly') {
            const date = new Date(historyDate.value);
            return date.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
        }
        
        const date = new Date(historyDate.value);
        return date.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'short', year: 'numeric' });
    });

const maxDate = new Date().toISOString().split('T')[0];
const minDate = new Date(Date.now() - 60 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];

const formattedDate = computed(() => {
    if (!historyDate.value) return '';
    const d = new Date(historyDate.value + 'T00:00:00');
    const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    return `${days[d.getDay()]}, ${d.getDate()} ${months[d.getMonth()]} ${d.getFullYear()}`;
});

const isToday = computed(() => {
    return historyDate.value === new Date().toISOString().split('T')[0];
});

const navigateDate = (offset) => {
    const d = new Date(historyDate.value + 'T12:00:00');
    d.setDate(d.getDate() + offset);
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    const newDate = `${year}-${month}-${day}`;
    if (newDate >= minDate && newDate <= maxDate) {
        historyDate.value = newDate;
    }
};

const goToToday = () => {
    const d = new Date();
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    historyDate.value = `${year}-${month}-${day}`;
};

const setHistoryMonth = (isCurrent) => {
    const d = new Date();
    if (!isCurrent) {
        d.setDate(1);
        d.setMonth(d.getMonth() - 1);
    }
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    historyDate.value = `${year}-${month}-${day}`;
};

watch([selectedLocationKey, historyDate, historyMode], () => {
    if (currentView.value === 'history') {
        fetchStockHistory();
    }
});

const fetchDownloadLogs = async () => {
    if (!authStore.userRole?.toLowerCase().includes('admin') && !authStore.userRole?.toLowerCase().includes('audit')) return;
    try {
        const res = await axios.get('/reports/download-history', {
            params: {
                branch_id: selectedBranchId.value || undefined,
            }
        });
        downloadLogs.value = res.data.data || res.data || [];
    } catch (err) {
        console.error('Error fetching logs:', err);
    }
};

const downloadExcel = async (type) => {
    let title = '';
    let endpoint = '';
    if (type === 'sales') {
        title = 'Laporan Penjualan';
        endpoint = '/audit/sales/export';
    } else if (type === 'inventory') {
        title = 'Laporan Data Inventory';
        endpoint = '/inventory/export';
    } else if (type === 'stock-in') {
        title = 'Laporan Riwayat Stok Masuk';
        endpoint = '/inventory/history/in/export';
    } else if (type === 'stock-out') {
        title = 'Laporan Riwayat Stok Keluar';
        endpoint = '/inventory/history/out/export';
    }

    toast.info(`Sedang menyiapkan ${title}...`);
    try {
        const params = {
            branch_id: selectedBranchId.value || undefined,
            online_shop_id: selectedOnlineShopId.value || undefined,
            warehouse_id: selectedWarehouseId.value || undefined,
            start_date: exportStartDate.value,
            end_date: exportEndDate.value,
            date: exportStartDate.value, // for legacy support
            mode: historyMode.value,
        };
        
        const response = await axios.get(endpoint, { 
            params,
            responseType: 'blob'
        });
        
        const timestamp = new Date().toISOString().split('T')[0];
        const filename = `${type.toUpperCase()}_${timestamp}.xlsx`;
        
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', filename);
        document.body.appendChild(link);
        link.click();
        
        toast.success(`${title} berhasil didownload!`);
        fetchDownloadLogs(); 
    } catch (err) {
        toast.error(`Gagal mendownload ${title}.`);
    }
};

onMounted(() => { 
    fetchAllInventory(); 
    fetchDownloadLogs();
});
</script>

<template>
    <div class="space-y-6 animate-in">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div class="flex items-center gap-3">
                <button @click="currentView === 'menu' ? router.push({ name: 'Inventory' }) : goBack()"
                    class="p-2 hover:bg-surface-800 rounded-xl transition-colors">
                    <ArrowLeft :size="20" class="text-text-secondary" />
                </button>
                <div>
                    <h1 class="text-2xl font-bold text-text-primary tracking-tight">Stock Opname</h1>
                    <p class="text-text-secondary mt-0.5 text-sm">
                        {{ currentView === 'menu' ? 'Pilih jenis laporan untuk melihat detail' :
                            currentView === 'brand' ? 'Ringkasan stok per merek' :
                                currentView === 'type' ? 'Ringkasan stok per tipe produk' :
                                    currentView === 'condition' ? 'Ringkasan stok per kondisi barang' :
                                        currentView === 'distributor' ? 'Ringkasan stok per distributor/supplier' :
                                            currentView === 'category' ? 'Ringkasan stok per kategori produk' :
                                                'Format ringkasan stok khusus New Era' }}
                    </p>
                </div>
            </div>
            <div class="flex flex-col sm:flex-row items-center gap-3 w-full sm:w-auto">
                <!-- Location Filter -->
                <div v-if="canFilterBranch && locations.length > 1" class="min-w-[180px] w-full sm:w-auto">
                    <select v-model="selectedLocationKey"
                        class="block w-full rounded-xl border-0 py-2 text-text-primary shadow-sm ring-1 ring-inset ring-surface-700/50 focus:ring-2 focus:ring-inset focus:ring-primary-600 sm:text-sm sm:leading-6 dark:bg-surface-800 dark:ring-surface-700">
                        <option value="all">Semua Lokasi</option>
                        <option v-for="loc in locations" :key="`${loc.type}:${loc.id}`"
                            :value="`${loc.type === 'branch' ? 'B' : loc.type === 'online_shop' ? 'S' : loc.type === 'warehouse' ? 'W' : 'D'}:${loc.id}`">
                            {{ loc.type === 'branch' ? '[Cabang]' : loc.type === 'online_shop' ? '[Toko]' : loc.type ===
                                'distributor' ? '[Distributor]' : '[Gudang]' }} {{ loc.name }}
                        </option>
                    </select>
                </div>
                <!-- Single Branch Display -->
                <div v-else-if="canFilterBranch && locations.length === 1"
                    class="px-4 py-2 bg-gray-50 dark:bg-surface-800 border border-gray-100 dark:border-surface-700 rounded-xl flex items-center gap-2">
                    <div class="w-2 h-2 rounded-full bg-primary-500"></div>
                    <span class="text-xs font-bold text-text-secondary">
                        {{ locations[0].type === 'branch' ? '[Cabang]' : locations[0].type === 'online_shop' ? '[Toko]'
                            : locations[0].type === 'distributor' ? '[Distributor]' : '[Gudang]' }}
                    </span>
                    <span class="text-sm font-bold text-text-primary">{{ locations[0].name }}</span>
                </div>

                <button @click="fetchAllInventory"
                    class="p-2.5 text-text-secondary hover:text-primary-500 hover:bg-primary-500/10 rounded-xl transition-all"
                    :disabled="loading">
                    <RefreshCw :size="20" :class="{ 'animate-spin': loading }" />
                </button>
            </div>
        </div>

        <!-- ==================== MENU LANDING ==================== -->
        <template v-if="currentView === 'menu'">
            <!-- HP / Non-HP Toggle -->
            <div class="flex items-center justify-between">
                <div class="flex space-x-1 rounded-xl bg-surface-800 border border-surface-700 p-1 w-fit">
                    <button @click="itemMode = 'hp'"
                        class="px-5 py-2.5 rounded-lg text-sm font-bold leading-5 transition-all duration-200 flex items-center gap-2"
                        :class="itemMode === 'hp' ? 'bg-primary-500 text-white shadow-lg shadow-primary-500/20' : 'text-text-secondary hover:text-white'">
                        <Smartphone :size="16" /> HP (IMEI)
                    </button>
                    <button @click="itemMode = 'non-hp'"
                        class="px-5 py-2.5 rounded-lg text-sm font-bold leading-5 transition-all duration-200 flex items-center gap-2"
                        :class="itemMode === 'non-hp' ? 'bg-purple-500 text-white shadow-lg shadow-purple-500/20' : 'text-text-secondary hover:text-white'">
                        <Package :size="16" /> Non-HP
                    </button>
                </div>
            </div>

            <!-- Loading -->
            <div v-if="loading" class="flex flex-col items-center justify-center py-20">
                <RefreshCw class="animate-spin text-primary-500 mb-4" :size="40" />
                <p class="text-text-secondary text-sm font-medium">Memuat data inventory...</p>
            </div>

            <!-- Quick Stats & Cards (only when not loading) -->
            <div v-else class="space-y-6">
                <!-- Quick Stats -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <div class="bg-surface-800 rounded-2xl border border-surface-700 p-4">
                        <div class="text-xs text-text-secondary uppercase tracking-wider font-bold mb-1">{{ isHpMode ?
                            'HP Tersedia' : 'Non-HP Tersedia' }}</div>
                        <div class="text-2xl font-black" :class="isHpMode ? 'text-blue-400' : 'text-purple-400'">{{
                            isHpMode ? summaryStats.totalHp : summaryStats.totalNonHp }}</div>
                    </div>
                    <div class="bg-surface-800 rounded-2xl border border-surface-700 p-4">
                        <div class="text-xs text-text-secondary uppercase tracking-wider font-bold mb-1">Jumlah Brand
                        </div>
                        <div class="text-2xl font-black text-purple-400">{{ summaryStats.totalBrands }}</div>
                    </div>
                    <div class="bg-surface-800 rounded-2xl border border-surface-700 p-4">
                        <div class="text-xs text-text-secondary uppercase tracking-wider font-bold mb-1">Jumlah Tipe
                        </div>
                        <div class="text-2xl font-black text-emerald-400">{{ summaryStats.totalTypes }}</div>
                    </div>
                    <div class="bg-surface-800 rounded-2xl border border-surface-700 p-4">
                        <div class="text-xs text-text-secondary uppercase tracking-wider font-bold mb-1">Distributor
                        </div>
                        <div class="text-2xl font-black text-amber-400">{{ summaryStats.totalDistributors }}</div>
                    </div>
                </div>

                <!-- Report Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">

                    <!-- Brand -->
                    <button @click="navigateTo('brand')"
                        class="group bg-surface-800 rounded-2xl border border-surface-700 hover:border-blue-500/50 p-6 text-left transition-all duration-300 hover:shadow-lg hover:shadow-blue-500/5 hover:translate-y-[-2px]">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-blue-500/10 rounded-xl group-hover:bg-blue-500/20 transition-colors">
                                <Layers :size="24" class="text-blue-400" />
                            </div>
                            <ChevronRight :size="20"
                                class="text-text-secondary group-hover:text-blue-400 transition-colors" />
                        </div>
                        <h3 class="text-lg font-bold text-text-primary mb-1">Laporan per Brand</h3>
                        <p class="text-sm text-text-secondary">Ringkasan stok tersedia berdasarkan merek</p>
                        <div class="mt-4 flex gap-2 flex-wrap">
                            <span v-for="b in brandReport.slice(0, 4)" :key="b.brand"
                                class="text-[10px] px-2 py-1 rounded-lg bg-surface-900 text-text-secondary font-medium border border-surface-700">
                                {{ b.brand }}: {{ b.available }}
                            </span>
                            <span v-if="brandReport.length > 4"
                                class="text-[10px] px-2 py-1 rounded-lg bg-surface-900 text-text-secondary font-medium border border-surface-700">
                                +{{ brandReport.length - 4 }} lagi
                            </span>
                        </div>
                    </button>

                    <!-- Type -->
                    <button @click="navigateTo('type')"
                        class="group bg-surface-800 rounded-2xl border border-surface-700 hover:border-emerald-500/50 p-6 text-left transition-all duration-300 hover:shadow-lg hover:shadow-emerald-500/5 hover:translate-y-[-2px]">
                        <div class="flex items-center justify-between mb-4">
                            <div
                                class="p-3 bg-emerald-500/10 rounded-xl group-hover:bg-emerald-500/20 transition-colors">
                                <Smartphone :size="24" class="text-emerald-400" />
                            </div>
                            <ChevronRight :size="20"
                                class="text-text-secondary group-hover:text-emerald-400 transition-colors" />
                        </div>
                        <h3 class="text-lg font-bold text-text-primary mb-1">Laporan per Tipe</h3>
                        <p class="text-sm text-text-secondary">Ringkasan stok per model + breakdown GB</p>
                        <div v-if="isHpMode" class="mt-4 flex items-center gap-2 text-[10px] text-emerald-400">
                            <HardDrive :size="12" /> <span class="font-bold uppercase tracking-wider">Fitur: Tampilkan
                                per GB</span>
                        </div>
                    </button>

                    <!-- Condition -->
                    <button v-if="isHpMode" @click="navigateTo('condition')"
                        class="group bg-surface-800 rounded-2xl border border-surface-700 hover:border-amber-500/50 p-6 text-left transition-all duration-300 hover:shadow-lg hover:shadow-amber-500/5 hover:translate-y-[-2px]">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-amber-500/10 rounded-xl group-hover:bg-amber-500/20 transition-colors">
                                <Tag :size="24" class="text-amber-400" />
                            </div>
                            <ChevronRight :size="20"
                                class="text-text-secondary group-hover:text-amber-400 transition-colors" />
                        </div>
                        <h3 class="text-lg font-bold text-text-primary mb-1">Laporan per Kondisi</h3>
                        <p class="text-sm text-text-secondary">Ringkasan stok per kondisi barang + breakdown GB</p>
                        <div v-if="isHpMode" class="mt-4 flex items-center gap-2 text-[10px] text-amber-400">
                            <HardDrive :size="12" /> <span class="font-bold uppercase tracking-wider">Fitur: Tampilkan
                                per GB</span>
                        </div>
                    </button>

                    <!-- Distributor -->
                    <button @click="navigateTo('distributor')"
                        class="group bg-surface-800 rounded-2xl border border-surface-700 hover:border-purple-500/50 p-6 text-left transition-all duration-300 hover:shadow-lg hover:shadow-purple-500/5 hover:translate-y-[-2px]">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-purple-500/10 rounded-xl group-hover:bg-purple-500/20 transition-colors">
                                <Truck :size="24" class="text-purple-400" />
                            </div>
                            <ChevronRight :size="20"
                                class="text-text-secondary group-hover:text-purple-400 transition-colors" />
                        </div>
                        <h3 class="text-lg font-bold text-text-primary mb-1">Laporan per Distributor</h3>
                        <p class="text-sm text-text-secondary">Ringkasan stok per supplier/distributor</p>
                        <div class="mt-4 flex gap-2 flex-wrap">
                            <span v-for="d in distributorReport.slice(0, 3)" :key="d.name"
                                class="text-[10px] px-2 py-1 rounded-lg bg-surface-900 text-text-secondary font-medium border border-surface-700">
                                {{ d.name }}: {{ d.available }}
                            </span>
                        </div>
                    </button>

                    <!-- Category (Non-HP Only) -->
                    <button v-if="!isHpMode" @click="navigateTo('category')"
                        class="group bg-surface-800 rounded-2xl border border-surface-700 hover:border-indigo-500/50 p-6 text-left transition-all duration-300 hover:shadow-lg hover:shadow-indigo-500/5 hover:translate-y-[-2px]">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-indigo-500/10 rounded-xl group-hover:bg-indigo-500/20 transition-colors">
                                <Box :size="24" class="text-indigo-400" />
                            </div>
                            <ChevronRight :size="20"
                                class="text-text-secondary group-hover:text-indigo-400 transition-colors" />
                        </div>
                        <h3 class="text-lg font-bold text-text-primary mb-1">Laporan per Kategori</h3>
                        <p class="text-sm text-text-secondary">Ringkasan stok per kategori barang (Non-HP)</p>
                        <div class="mt-4 flex gap-2 flex-wrap">
                            <span v-for="c in categoryReport.slice(0, 5)" :key="c.category"
                                class="text-[10px] px-2 py-1 rounded-lg bg-surface-900 text-text-secondary font-medium border border-surface-700">
                                {{ c.category }}: {{ c.available }}
                            </span>
                        </div>
                    </button>

                    <!-- History Stok -->
                    <button @click="navigateTo('history'); fetchStockHistory();"
                        class="group bg-surface-800 rounded-2xl border border-surface-700 hover:border-emerald-500/50 p-6 text-left transition-all duration-300 hover:shadow-lg hover:shadow-emerald-500/5 hover:translate-y-[-2px]">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-emerald-500/10 rounded-xl group-hover:bg-emerald-500/20 transition-colors">
                                <History :size="24" class="text-emerald-400" />
                             </div>
                            <ChevronRight :size="20"
                                class="text-text-secondary group-hover:text-emerald-400 transition-colors" />
                        </div>
                        <h3 class="text-lg font-bold text-text-primary mb-1">History Stok</h3>
                        <p class="text-sm text-text-secondary">Track barang masuk & keluar (Reset 05:00 AM)</p>
                        <div class="mt-4 flex items-center gap-2 text-[10px] text-emerald-400">
                             <Clock :size="12" /> <span class="font-bold uppercase tracking-wider">Reset Otomatis Jam 5 Pagi</span>
                        </div>
                    </button>

                    <!-- New Era (IMEI Priority) - MOVED TO END -->
                    <button v-if="isHpMode" @click="navigateTo('new_era')"
                        class="group bg-gradient-to-br from-surface-800 to-primary-500/10 rounded-2xl border border-primary-500/30 hover:border-primary-500 p-6 text-left transition-all duration-300 hover:shadow-xl hover:shadow-primary-500/10 hover:translate-y-[-4px]">
                        <div class="flex items-center justify-between mb-4">
                            <div
                                class="p-3 bg-primary-500/10 rounded-xl group-hover:bg-primary-500/20 transition-colors">
                                <Sparkles :size="24" class="text-primary-400" />
                            </div>
                            <div
                                class="px-2 py-1 bg-primary-500 text-white text-[8px] font-black rounded uppercase tracking-widest">
                                HOT</div>
                        </div>
                        <h3 class="text-lg font-black text-text-primary mb-1 uppercase italic tracking-tight">Laporan
                            New Era</h3>
                        <p class="text-xs text-text-secondary font-medium">Ringkasan stok khusus IMEI (New & Second)</p>
                        <div class="mt-4 flex items-center justify-between">
                            <span class="text-xs font-black text-primary-400">{{ summaryStats.totalHp }} UNIT</span>
                            <ChevronRight :size="16" class="text-primary-500" />
                        </div>
                    </button>
                </div>

                <!-- ==================== DOWNLOAD CENTER (NEW) ==================== -->
                <div class="mt-8 space-y-6">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-primary-500/10 rounded-lg">
                            <Download :size="20" class="text-primary-400" />
                        </div>
                        <h2 class="text-xl font-bold text-text-primary tracking-tight">Download Center</h2>
                    </div>

                    <!-- Date Filter for Download Center -->
                    <div class="bg-surface-800 rounded-2xl border border-surface-700 p-5">
                        <div class="flex flex-col lg:flex-row lg:items-end gap-6">
                            <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <template v-if="exportMode === 'daily'">
                                    <div class="space-y-2">
                                        <label class="text-[10px] font-black text-text-secondary uppercase tracking-widest flex items-center gap-2">
                                            <Calendar :size="12" /> Dari Tanggal
                                        </label>
                                        <input type="date" v-model="exportStartDate" :min="minExportDate" :max="maxExportDate"
                                            class="w-full bg-surface-900 border-surface-700 rounded-xl px-4 h-11 text-sm text-text-primary focus:border-primary-500 focus:ring-0 transition-all" />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-[10px] font-black text-text-secondary uppercase tracking-widest flex items-center gap-2">
                                            <Calendar :size="12" /> Sampai Tanggal
                                        </label>
                                        <input type="date" v-model="exportEndDate" :min="minExportDate" :max="maxExportDate"
                                            class="w-full bg-surface-900 border-surface-700 rounded-xl px-4 h-11 text-sm text-text-primary focus:border-primary-500 focus:ring-0 transition-all" />
                                    </div>
                                </template>
                                <template v-else>
                                    <div class="col-span-full space-y-2">
                                        <label class="text-[10px] font-black text-text-secondary uppercase tracking-widest flex items-center gap-2">
                                            <Calendar :size="12" /> Pilih Bulan Laporan
                                        </label>
                                        <input type="month" v-model="exportMonth"
                                            class="w-full bg-surface-900 border-surface-700 rounded-xl px-4 h-11 text-sm text-text-primary focus:border-primary-500 focus:ring-0 transition-all" />
                                    </div>
                                </template>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <button @click="setExportRange('today')" 
                                    class="px-5 h-11 rounded-xl text-xs font-black transition-all border tracking-tight"
                                    :class="activeExportButton === 'today' ? 'bg-primary-500 border-primary-500 text-white shadow-lg shadow-primary-500/25' : 'bg-surface-900 border-surface-700 text-text-secondary hover:border-surface-600'">
                                    HARI INI
                                </button>
                                <button @click="setExportRange('yesterday')" 
                                    class="px-5 h-11 rounded-xl text-xs font-black transition-all border tracking-tight"
                                    :class="activeExportButton === 'yesterday' ? 'bg-primary-500 border-primary-500 text-white shadow-lg shadow-primary-500/25' : 'bg-surface-900 border-surface-700 text-text-secondary hover:border-surface-600'">
                                    KEMARIN
                                </button>
                                <button @click="setExportRange('month')" 
                                    class="px-5 h-11 rounded-xl text-xs font-black transition-all border tracking-tight"
                                    :class="activeExportButton === 'month' ? 'bg-emerald-500 border-emerald-500 text-white shadow-lg shadow-emerald-500/25' : 'bg-surface-900 border-surface-700 text-text-secondary hover:border-surface-600'">
                                    BULANAN
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <!-- Excel Penjualan -->
                        <button @click="downloadExcel('sales')"
                            class="group bg-surface-800 rounded-2xl border border-surface-700 hover:border-emerald-500/50 p-5 text-left transition-all duration-300 hover:shadow-lg hover:shadow-emerald-500/5">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="p-2 bg-emerald-500/10 rounded-lg group-hover:bg-emerald-500/20 transition-colors">
                                    <FileSpreadsheet :size="20" class="text-emerald-400" />
                                </div>
                                <span class="text-sm font-bold text-text-primary">Excel Penjualan</span>
                            </div>
                            <p class="text-xs text-text-secondary">Laporan riwayat penjualan (Sales History)</p>
                        </button>

                        <!-- Excel Data Inventory -->
                        <button @click="downloadExcel('inventory')"
                            class="group bg-surface-800 rounded-2xl border border-surface-700 hover:border-blue-500/50 p-5 text-left transition-all duration-300 hover:shadow-lg hover:shadow-blue-500/5">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="p-2 bg-blue-500/10 rounded-lg group-hover:bg-blue-500/20 transition-colors">
                                    <Box :size="20" class="text-blue-400" />
                                </div>
                                <span class="text-sm font-bold text-text-primary">Excel Data Inventory</span>
                            </div>
                            <p class="text-xs text-text-secondary">Data stok saat ini (HP & Non-HP)</p>
                        </button>

                        <!-- Excel Riwayat Stok Masuk -->
                        <button @click="downloadExcel('stock-in')"
                            class="group bg-surface-800 rounded-2xl border border-surface-700 hover:border-purple-500/50 p-5 text-left transition-all duration-300 hover:shadow-lg hover:shadow-purple-500/5">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="p-2 bg-purple-500/10 rounded-lg group-hover:bg-purple-500/20 transition-colors">
                                    <Download :size="20" class="text-purple-400" />
                                </div>
                                <span class="text-sm font-bold text-text-primary">Stok Masuk</span>
                            </div>
                            <p class="text-xs text-text-secondary">Riwayat barang masuk (HP & Non-HP)</p>
                        </button>

                        <!-- Excel Riwayat Stok Keluar -->
                        <button @click="downloadExcel('stock-out')"
                            class="group bg-surface-800 rounded-2xl border border-surface-700 hover:border-amber-500/50 p-5 text-left transition-all duration-300 hover:shadow-lg hover:shadow-amber-500/5">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="p-2 bg-amber-500/10 rounded-lg group-hover:bg-amber-500/20 transition-colors">
                                    <Truck :size="20" class="text-amber-400" />
                                </div>
                                <span class="text-sm font-bold text-text-primary">Stok Keluar</span>
                            </div>
                            <p class="text-xs text-text-secondary">Riwayat barang keluar (HP & Non-HP)</p>
                        </button>
                    </div>

                    <!-- Download History (For Admin/Audit) -->
                    <div v-if="downloadLogs.length > 0" class="bg-surface-800 rounded-2xl border border-surface-700 overflow-hidden">
                        <div class="px-5 py-4 border-b border-surface-700 bg-surface-800/50 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <History :size="18" class="text-primary-400" />
                                <h3 class="font-bold text-text-primary">Riwayat Download Excel</h3>
                            </div>
                            <button @click="fetchDownloadLogs" class="p-1.5 hover:bg-surface-700 rounded-lg transition-colors">
                                <RefreshCw :size="14" class="text-text-secondary" />
                            </button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-surface-900/50 text-text-secondary text-[10px] uppercase tracking-wider">
                                    <tr>
                                        <th class="px-5 py-3 font-bold">Waktu</th>
                                        <th class="px-5 py-3 font-bold">User</th>
                                        <th class="px-5 py-3 font-bold">Nama Laporan</th>
                                        <th class="px-5 py-3 font-bold">File</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-surface-700">
                                    <tr v-for="log in downloadLogs.slice(0, 5)" :key="log.id" class="hover:bg-surface-700/30 transition-colors">
                                        <td class="px-5 py-3 text-text-secondary whitespace-nowrap">
                                            {{ new Date(log.created_at).toLocaleString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }) }}
                                        </td>
                                        <td class="px-5 py-3">
                                            <div class="font-medium text-text-primary">{{ log.user?.name || 'User' }}</div>
                                            <div class="text-[10px] text-text-secondary">{{ log.user?.roles?.[0]?.name }}</div>
                                        </td>
                                        <td class="px-5 py-3 text-text-primary font-medium">{{ log.report_name }}</td>
                                        <td class="px-5 py-3 text-text-secondary italic text-xs">{{ log.filename }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-if="downloadLogs.length > 5" class="px-5 py-3 bg-surface-900/30 text-center">
                            <p class="text-xs text-text-secondary font-medium">Hanya menampilkan 5 download terakhir</p>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <!-- ==================== SUB-VIEW: CONTROLS BAR ==================== -->
        <template v-if="currentView !== 'menu'">
            <div class="bg-surface-800 rounded-2xl border border-surface-700 p-4">
                <div class="flex flex-col sm:flex-row gap-4 justify-between items-start sm:items-center">
                    <!-- Toggle for Brand View Breakdown -->
                    <div v-if="currentView === 'brand'" class="flex items-center gap-3">
                        <button v-if="isHpMode" @click="showBrandType = !showBrandType"
                            class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium transition-all border"
                            :class="showBrandType
                                ? 'bg-primary-500/10 border-primary-500/30 text-primary-400'
                                : 'bg-surface-900 border-surface-700 text-text-secondary hover:text-white'">
                            <component :is="showBrandType ? ToggleRight : ToggleLeft" :size="18" />
                            Tampilkan per Tipe
                        </button>
                        <button v-if="isHpMode" @click="showBrandCondition = !showBrandCondition"
                            class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium transition-all border"
                            :class="showBrandCondition
                                ? 'bg-primary-500/10 border-primary-500/30 text-primary-400'
                                : 'bg-surface-900 border-surface-700 text-text-secondary hover:text-white'">
                            <component :is="showBrandCondition ? ToggleRight : ToggleLeft" :size="18" />
                            Tampilkan per Kondisi
                        </button>
                    </div>

                    <!-- Toggle for Brand View Breakdown -->
                    <div v-else-if="currentView === 'brand'" class="flex items-center gap-3">
                        <button v-if="isHpMode" @click="showBrandType = !showBrandType"
                            class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-bold transition-all border"
                            :class="showBrandType
                                ? 'bg-primary-500/10 border-primary-500/30 text-primary-400'
                                : 'bg-surface-900 border-surface-700 text-text-secondary hover:text-white'">
                            <component :is="showBrandType ? ToggleRight : ToggleLeft" :size="18" />
                            Tampilkan per Tipe
                        </button>
                        <button v-if="isHpMode" @click="showBrandCondition = !showBrandCondition"
                            class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-bold transition-all border"
                            :class="showBrandCondition
                                ? 'bg-primary-500/10 border-primary-500/30 text-primary-400'
                                : 'bg-surface-900 border-surface-700 text-text-secondary hover:text-white'">
                            <component :is="showBrandCondition ? ToggleRight : ToggleLeft" :size="18" />
                            Tampilkan per Kondisi
                        </button>
                        <button @click="showBrandLocation = !showBrandLocation"
                            class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-bold transition-all border"
                            :class="showBrandLocation
                                ? 'bg-indigo-500/10 border-indigo-500/30 text-indigo-400'
                                : 'bg-surface-900 border-surface-700 text-text-secondary hover:text-white'">
                            <component :is="showBrandLocation ? ToggleRight : ToggleLeft" :size="18" />
                            Tampilkan per Cabang
                        </button>
                    </div>

                    <!-- Toggle for Type View Breakdown -->
                    <div v-else-if="currentView === 'type'" class="flex flex-wrap items-center gap-3">
                        <button v-if="isHpMode" @click="showPerGb = !showPerGb"
                            class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-bold transition-all border"
                            :class="showPerGb
                                ? 'bg-primary-500/10 border-primary-500/30 text-primary-400'
                                : 'bg-surface-900 border-surface-700 text-text-secondary hover:text-white'">
                            <component :is="showPerGb ? ToggleRight : ToggleLeft" :size="18" />
                            Tampilkan per GB
                        </button>
                        <button v-if="isHpMode" @click="showTypeCondition = !showTypeCondition"
                            class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-bold transition-all border"
                            :class="showTypeCondition
                                ? 'bg-primary-500/10 border-primary-500/30 text-primary-400'
                                : 'bg-surface-900 border-surface-700 text-text-secondary hover:text-white'">
                            <component :is="showTypeCondition ? ToggleRight : ToggleLeft" :size="18" />
                            Tampilkan per Kondisi
                        </button>
                        <button @click="showTypeLocation = !showTypeLocation"
                            class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-bold transition-all border"
                            :class="showTypeLocation
                                ? 'bg-indigo-500/10 border-indigo-500/30 text-indigo-400'
                                : 'bg-surface-900 border-surface-700 text-text-secondary hover:text-white'">
                            <component :is="showTypeLocation ? ToggleRight : ToggleLeft" :size="18" />
                            Tampilkan per Cabang
                        </button>

                        <!-- Sort Filter -->
                        <div
                            class="flex items-center bg-surface-900/50 border border-surface-700/50 rounded-xl px-4 py-2 hover:border-primary-500/50 focus-within:border-primary-500 transition-all duration-200">
                            <ListFilter class="text-text-secondary mr-2" :size="16" />
                            <select v-model="typeSortOrder"
                                class="bg-transparent text-sm font-bold text-white focus:outline-none cursor-pointer appearance-none min-w-[120px]">
                                <option value="available" class="bg-[#1f2937] text-white">Stok Terbanyak</option>
                                <option value="brand" class="bg-[#1f2937] text-white">Brand (A-Z)</option>
                                <option value="name" class="bg-[#1f2937] text-white">Tipe (A-Z)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Toggle for Condition View Breakdown -->
                    <div v-else-if="currentView === 'condition'" class="flex flex-wrap items-center gap-3">
                        <button @click="showConditionBrand = !showConditionBrand"
                            class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-bold transition-all border"
                            :class="showConditionBrand
                                ? 'bg-primary-500/10 border-primary-500/30 text-primary-400'
                                : 'bg-surface-900 border-surface-700 text-text-secondary hover:text-white'">
                            <component :is="showConditionBrand ? ToggleRight : ToggleLeft" :size="18" />
                            Tampilkan per Brand
                        </button>
                        <button @click="showConditionType = !showConditionType"
                            class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-bold transition-all border"
                            :class="showConditionType
                                ? 'bg-primary-500/10 border-primary-500/30 text-primary-400'
                                : 'bg-surface-900 border-surface-700 text-text-secondary hover:text-white'">
                            <component :is="showConditionType ? ToggleRight : ToggleLeft" :size="18" />
                            Tampilkan per Tipe
                        </button>
                        <button v-if="isHpMode" @click="showPerGb = !showPerGb"
                            class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-bold transition-all border"
                            :class="showPerGb
                                ? 'bg-primary-500/10 border-primary-500/30 text-primary-400'
                                : 'bg-surface-900 border-surface-700 text-text-secondary hover:text-white'">
                            <component :is="showPerGb ? ToggleRight : ToggleLeft" :size="18" />
                            Tampilkan per GB
                        </button>
                        <button @click="showConditionLocation = !showConditionLocation"
                            class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-bold transition-all border"
                            :class="showConditionLocation
                                ? 'bg-indigo-500/10 border-indigo-500/30 text-indigo-400'
                                : 'bg-surface-900 border-surface-700 text-text-secondary hover:text-white'">
                            <component :is="showConditionLocation ? ToggleRight : ToggleLeft" :size="18" />
                            Tampilkan per Cabang
                        </button>
                    </div>

                    <!-- Toggle for Distributor View Breakdown -->
                    <div v-else-if="currentView === 'distributor'" class="flex flex-wrap items-center gap-3">
                        <button @click="showDistributorBrand = !showDistributorBrand"
                            class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-bold transition-all border"
                            :class="showDistributorBrand
                                ? 'bg-primary-500/10 border-primary-500/30 text-primary-400'
                                : 'bg-surface-900 border-surface-700 text-text-secondary hover:text-white'">
                            <component :is="showDistributorBrand ? ToggleRight : ToggleLeft" :size="18" />
                            Tampilkan per Brand
                        </button>
                        <button @click="showDistributorType = !showDistributorType"
                            class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-bold transition-all border"
                            :class="showDistributorType
                                ? 'bg-primary-500/10 border-primary-500/30 text-primary-400'
                                : 'bg-surface-900 border-surface-700 text-text-secondary hover:text-white'">
                            <component :is="showDistributorType ? ToggleRight : ToggleLeft" :size="18" />
                            Tampilkan per Tipe
                        </button>
                        <button v-if="isHpMode" @click="showDistributorGb = !showDistributorGb"
                            class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-bold transition-all border"
                            :class="showDistributorGb
                                ? 'bg-primary-500/10 border-primary-500/30 text-primary-400'
                                : 'bg-surface-900 border-surface-700 text-text-secondary hover:text-white'">
                            <component :is="showDistributorGb ? ToggleRight : ToggleLeft" :size="18" />
                            Tampilkan per GB
                        </button>
                        <button @click="showDistributorLocation = !showDistributorLocation"
                            class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-bold transition-all border"
                            :class="showDistributorLocation
                                ? 'bg-indigo-500/10 border-indigo-500/30 text-indigo-400'
                                : 'bg-surface-900 border-surface-700 text-text-secondary hover:text-white'">
                            <component :is="showDistributorLocation ? ToggleRight : ToggleLeft" :size="18" />
                            Tampilkan per Cabang
                        </button>
                    </div>

                    <!-- Toggle for Category View Breakdown -->
                    <div v-else-if="currentView === 'category'" class="flex flex-wrap items-center gap-3">
                        <button @click="showCategoryType = !showCategoryType"
                            class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-bold transition-all border"
                            :class="showCategoryType
                                ? 'bg-primary-500/10 border-primary-500/30 text-primary-400'
                                : 'bg-surface-900 border-surface-700 text-text-secondary hover:text-white'">
                            <component :is="showCategoryType ? ToggleRight : ToggleLeft" :size="18" />
                            Tampilkan per Tipe
                        </button>
                        <button @click="showCategoryLocation = !showCategoryLocation"
                            class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-bold transition-all border"
                            :class="showCategoryLocation
                                ? 'bg-indigo-500/10 border-indigo-500/30 text-indigo-400'
                                : 'bg-surface-900 border-surface-700 text-text-secondary hover:text-white'">
                            <component :is="showCategoryLocation ? ToggleRight : ToggleLeft" :size="18" />
                            Tampilkan per Cabang
                        </button>
                    </div>
                    <div v-else></div>

                    <!-- Search -->
                    <div class="relative w-full sm:w-80 group">
                        <Search
                            class="absolute left-3 top-1/2 -translate-y-1/2 text-text-secondary group-focus-within:text-primary-400 transition-colors"
                            :size="20" />
                        <input v-model="searchQuery" type="text" placeholder="Cari..."
                            class="w-full bg-surface-900 border border-surface-700 rounded-xl py-2.5 pl-11 pr-4 text-sm font-medium text-text-primary focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all placeholder:text-text-secondary" />
                    </div>
                </div>
            </div>
        </template>

        <!-- ==================== BRAND REPORT ==================== -->
        <template v-if="currentView === 'brand'">
            <div class="bg-surface-800 rounded-2xl border border-surface-700 overflow-hidden">
                <div v-if="loading" class="p-12 flex justify-center items-center">
                    <RefreshCw class="animate-spin text-primary-500" :size="32" />
                    <span class="ml-3 text-text-secondary">Memuat data...</span>
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-text-primary">
                        <thead class="bg-surface-900/50 text-text-secondary uppercase text-xs font-semibold">
                            <tr>
                                <th class="px-6 py-4">#</th>
                                <th class="px-6 py-4">Brand</th>
                                <th v-if="showBrandType" class="px-6 py-4">Tipe Produk</th>
                                <th v-if="showBrandCondition" class="px-6 py-4">Kondisi</th>
                                <th class="px-6 py-4 text-center">Tersedia</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-700/50">
                            <template v-for="(row, idx) in filteredBrand" :key="row.brand">
                                <!-- Main Row -->
                                <tr class="hover:bg-surface-700/30 transition-colors"
                                    :class="{ 'bg-surface-900/30': showBrandType || showBrandCondition }">
                                    <td class="px-6 py-4 text-text-secondary text-xs">{{ idx + 1 }}</td>
                                    <td class="px-6 py-4 font-bold text-white">{{ row.brand }}</td>
                                    <td v-if="showBrandType || showBrandCondition"
                                        class="px-6 py-4 text-text-secondary italic text-xs">—</td>
                                    <td v-if="showBrandType && showBrandCondition"
                                        class="px-6 py-4 text-text-secondary italic text-xs">—</td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="text-lg font-bold"
                                            :class="row.available > 0 ? 'text-emerald-400' : 'text-red-400'">{{
                                                row.available }}</span>
                                    </td>
                                </tr>

                                <!-- Brand Location Breakdown (Renamed to Tampilkan per Cabang label in UI) -->
                                <tr v-if="showBrandLocation" class="bg-indigo-500/5">
                                    <td colspan="5" class="px-6 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <span v-for="loc in row.locations" :key="loc.name"
                                                class="px-3 py-1.5 rounded-xl bg-surface-900 border border-indigo-500/20 text-[11px] flex items-center gap-2 shadow-sm shadow-indigo-500/5">
                                                <span class="text-text-secondary font-medium">{{ loc.name }}</span>
                                                <span class="font-black text-indigo-400">{{ loc.qty }}</span>
                                            </span>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Brand Sub-rows (Type Breakdown) -->
                                <template v-if="showBrandType" v-for="t in row.tree" :key="t.label">
                                    <tr class="bg-surface-900/20 hover:bg-surface-700/20 transition-colors">
                                        <td class="px-6 py-2.5"></td>
                                        <td class="px-6 py-2.5"></td>
                                        <td class="px-6 py-2.5 text-xs font-bold text-text-primary">{{ t.label }}</td>
                                        <td v-if="showBrandCondition" class="px-6 py-2.5"></td>
                                        <td class="px-6 py-2.5 text-center text-sm font-semibold text-emerald-400/80">{{
                                            t.available }}</td>
                                    </tr>

                                    <!-- Conditions under Type -->
                                    <template v-if="showBrandCondition" v-for="c in t.conditions" :key="c.label">
                                        <tr class="bg-surface-900/40 hover:bg-surface-700/40 transition-colors">
                                            <td class="px-6 py-2.5"></td>
                                            <td class="px-6 py-2.5"></td>
                                            <td class="px-6 py-2.5"></td>
                                            <td class="px-6 py-2.5">
                                                <span class="px-2 py-0.5 rounded-lg text-[10px] font-bold border"
                                                    :class="conditionColor(c.condition)">
                                                    {{ c.label }}
                                                </span>
                                            </td>
                                            <td
                                                class="px-6 py-2.5 text-center text-sm font-semibold text-emerald-400/70">
                                                {{ c.available }}</td>
                                        </tr>
                                    </template>
                                </template>

                                <!-- Conditions if Type is OFF -->
                                <template v-if="!showBrandType && showBrandCondition" v-for="t in row.tree">
                                    <template v-for="c in t.conditions" :key="c.label">
                                        <tr class="bg-surface-900/20 hover:bg-surface-700/20 transition-colors">
                                            <td class="px-6 py-2.5"></td>
                                            <td class="px-6 py-2.5"></td>
                                            <td class="px-6 py-2.5">
                                                <span class="px-2 py-0.5 rounded-lg text-[10px] font-bold border"
                                                    :class="conditionColor(c.condition)">
                                                    {{ c.label }}
                                                </span>
                                            </td>
                                            <td
                                                class="px-6 py-2.5 text-center text-sm font-semibold text-emerald-400/80">
                                                {{ c.available }}</td>
                                        </tr>
                                    </template>
                                </template>
                            </template>
                        </tbody>
                        <tfoot class="bg-surface-900/70 border-t border-surface-600">
                            <tr class="font-bold">
                                <td class="px-6 py-4 text-right text-text-secondary"
                                    :colspan="(showBrandType ? 1 : 0) + (showBrandCondition ? 1 : 0) + 2">TOTAL</td>
                                <td class="px-6 py-4 text-center text-emerald-400 text-lg">
                                    {{filteredBrand.reduce((s, r) => s + r.available, 0)}}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </template>

        <!-- ==================== TYPE REPORT ==================== -->
        <template v-else-if="currentView === 'type'">
            <div class="bg-surface-800 rounded-2xl border border-surface-700 overflow-hidden">
                <div v-if="loading" class="p-12 flex justify-center items-center">
                    <RefreshCw class="animate-spin text-primary-500" :size="32" />
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-text-primary">
                        <thead class="bg-surface-900/50 text-text-secondary uppercase text-xs font-semibold">
                            <tr>
                                <th class="px-6 py-4">#</th>
                                <th class="px-6 py-4">Brand</th>
                                <th class="px-6 py-4">Tipe</th>
                                <th v-if="showPerGb" class="px-6 py-4">Kapasitas</th>
                                <th v-if="showTypeCondition" class="px-6 py-4">Kondisi</th>
                                <th class="px-6 py-4 text-center">Tersedia</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-700/50">
                            <template v-for="(row, idx) in filteredType" :key="row.name + row.brand">
                                <!-- Main Row -->
                                <tr class="hover:bg-surface-700/30 transition-colors"
                                    :class="{ 'bg-surface-900/30': showPerGb || showTypeCondition }">
                                    <td class="px-6 py-4 text-text-secondary text-xs">{{ idx + 1 }}</td>
                                    <td class="px-6 py-4 text-text-secondary">{{ row.brand }}</td>
                                    <td class="px-6 py-4 font-bold text-white">{{ row.name }}</td>
                                    <td v-if="showPerGb" class="px-6 py-4 text-text-secondary italic text-xs">—</td>
                                    <td v-if="showTypeCondition" class="px-6 py-4 text-text-secondary italic text-xs">—
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="text-lg font-bold"
                                            :class="row.available > 0 ? 'text-emerald-400' : 'text-red-400'">{{
                                                row.available }}</span>
                                    </td>
                                </tr>

                                <!-- Type Location Breakdown (Renamed in UI) -->
                                <tr v-if="showTypeLocation" class="bg-indigo-500/5">
                                    <td colspan="6" class="px-6 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <span v-for="loc in row.locations" :key="loc.name"
                                                class="px-3 py-1.5 rounded-xl bg-surface-900 border border-indigo-500/20 text-[11px] flex items-center gap-2 shadow-sm shadow-indigo-500/5">
                                                <span class="text-text-secondary font-medium">{{ loc.name }}</span>
                                                <span class="font-black text-indigo-400">{{ loc.qty }}</span>
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                                <!-- GB Sub-rows -->
                                <template v-if="showPerGb" v-for="g in row.tree" :key="g.label">
                                    <tr class="bg-surface-900/20 hover:bg-surface-700/20 transition-colors">
                                        <td class="px-6 py-2.5"></td>
                                        <td class="px-6 py-2.5"></td>
                                        <td class="px-6 py-2.5"></td>
                                        <td class="px-6 py-2.5">
                                            <span
                                                class="px-2.5 py-1 rounded-lg text-xs font-bold bg-primary-500/10 text-primary-400 border border-primary-500/20">
                                                {{ g.label }}
                                            </span>
                                        </td>
                                        <td v-if="showTypeCondition" class="px-6 py-2.5"></td>
                                        <td class="px-6 py-2.5 text-center text-sm font-semibold text-emerald-400/80">{{
                                            g.available }}</td>
                                    </tr>

                                    <!-- Conditions under GB -->
                                    <template v-if="showTypeCondition" v-for="c in g.conditions" :key="c.label">
                                        <tr class="bg-surface-900/40 hover:bg-surface-700/40 transition-colors">
                                            <td class="px-6 py-2.5"></td>
                                            <td class="px-6 py-2.5"></td>
                                            <td class="px-6 py-2.5"></td>
                                            <td class="px-6 py-2.5"></td>
                                            <td class="px-6 py-2.5">
                                                <span class="px-2 py-0.5 rounded-lg text-[10px] font-bold border"
                                                    :class="conditionColor(c.condition)">
                                                    {{ c.label }}
                                                </span>
                                            </td>
                                            <td
                                                class="px-6 py-2.5 text-center text-sm font-semibold text-emerald-400/70">
                                                {{ c.available }}</td>
                                        </tr>
                                    </template>
                                </template>

                                <!-- Conditions if GB is OFF -->
                                <template v-if="!showPerGb && showTypeCondition" v-for="g in row.tree">
                                    <template v-for="c in g.conditions" :key="c.label">
                                        <tr class="bg-surface-900/20 hover:bg-surface-700/20 transition-colors">
                                            <td class="px-6 py-2.5"></td>
                                            <td class="px-6 py-2.5"></td>
                                            <td class="px-6 py-2.5"></td>
                                            <td class="px-6 py-2.5 text-text-secondary italic text-xs">—</td>
                                            <td class="px-6 py-2.5">
                                                <span class="px-2 py-0.5 rounded-lg text-[10px] font-bold border"
                                                    :class="conditionColor(c.condition)">
                                                    {{ c.label }}
                                                </span>
                                            </td>
                                            <td
                                                class="px-6 py-2.5 text-center text-sm font-semibold text-emerald-400/80">
                                                {{ c.available }}</td>
                                        </tr>
                                    </template>
                                </template>
                            </template>
                        </tbody>
                        <tfoot class="bg-surface-900/70 border-t border-surface-600">
                            <tr class="font-bold">
                                <td class="px-6 py-4 text-right text-text-secondary"
                                    :colspan="(showPerGb ? 1 : 0) + (showTypeCondition ? 1 : 0) + 3">TOTAL</td>
                                <td class="px-6 py-4 text-center text-emerald-400 text-lg">
                                    {{filteredType.reduce((s, r) => s + r.available, 0)}}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </template>

        <!-- ==================== CONDITION REPORT ==================== -->
        <template v-else-if="currentView === 'condition'">
            <div class="bg-surface-800 rounded-2xl border border-surface-700 overflow-hidden">
                <div v-if="loading" class="p-12 flex justify-center items-center">
                    <RefreshCw class="animate-spin text-primary-500" :size="32" />
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-text-primary">
                        <thead class="bg-surface-900/50 text-text-secondary uppercase text-xs font-semibold">
                            <tr>
                                <th class="px-6 py-4">#</th>
                                <th class="px-6 py-4">Kondisi</th>
                                <th v-if="showConditionBrand" class="px-6 py-4">Brand</th>
                                <th v-if="showConditionType" class="px-6 py-4">Tipe Produk</th>
                                <th v-if="showPerGb" class="px-6 py-4">Kapasitas</th>
                                <th class="px-6 py-4 text-center">Tersedia</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-700/50">
                            <template v-for="(row, idx) in filteredCondition" :key="row.condition">
                                <tr class="hover:bg-surface-700/30 transition-colors"
                                    :class="{ 'bg-surface-900/30': showPerGb || showConditionBrand || showConditionType }">
                                    <td class="px-6 py-4 text-text-secondary text-xs">{{ idx + 1 }}</td>
                                    <td class="px-6 py-4">
                                        <span class="px-3 py-1.5 rounded-xl text-xs font-bold border"
                                            :class="conditionColor(row.condition)">
                                            {{ row.label }}
                                        </span>
                                    </td>
                                    <td v-if="showConditionBrand" class="px-6 py-4 text-text-secondary italic text-xs">—
                                    </td>
                                    <td v-if="showConditionType" class="px-6 py-4 text-text-secondary italic text-xs">—
                                    </td>
                                    <td v-if="showPerGb" class="px-6 py-4 text-text-secondary italic text-xs">—</td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="text-lg font-bold"
                                            :class="row.available > 0 ? 'text-emerald-400' : 'text-red-400'">{{
                                                row.available }}</span>
                                    </td>
                                </tr>

                                <!-- Condition Location Breakdown -->
                                <tr v-if="showConditionLocation" class="bg-indigo-500/5">
                                    <td colspan="6" class="px-6 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <span v-for="loc in row.locations" :key="loc.name"
                                                class="px-3 py-1.5 rounded-xl bg-surface-900 border border-indigo-500/20 text-[11px] flex items-center gap-2 shadow-sm shadow-indigo-500/5">
                                                <span class="text-text-secondary font-medium">{{ loc.name }}</span>
                                                <span class="font-black text-indigo-400">{{ loc.qty }}</span>
                                            </span>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Brand Breakdown -->
                                <template v-if="showConditionBrand" v-for="b in row.tree" :key="b.label">
                                    <tr class="bg-surface-900/20 hover:bg-surface-700/20 transition-colors">
                                        <td class="px-6 py-2.5"></td>
                                        <td class="px-6 py-2.5"></td>
                                        <td class="px-6 py-2.5 text-xs font-bold text-text-primary">{{ b.label }}</td>
                                        <td v-if="showConditionType" class="px-6 py-2.5"></td>
                                        <td v-if="showPerGb" class="px-6 py-2.5"></td>
                                        <td class="px-6 py-2.5 text-center text-sm font-semibold text-emerald-400/80">{{
                                            b.available }}</td>
                                    </tr>

                                    <!-- Type Breakdown (Hierarchical under Brand) -->
                                    <template v-if="showConditionType" v-for="t in b.types" :key="t.label">
                                        <tr class="bg-surface-900/30 hover:bg-surface-700/30 transition-colors">
                                            <td class="px-6 py-2.5"></td>
                                            <td class="px-6 py-2.5"></td>
                                            <td class="px-6 py-2.5"></td>
                                            <td
                                                class="px-6 py-2.5 text-xs font-bold text-text-primary underline decoration-primary-500/30">
                                                {{ t.label }}</td>
                                            <td v-if="showPerGb" class="px-6 py-2.5"></td>
                                            <td
                                                class="px-6 py-2.5 text-center text-sm font-semibold text-emerald-400/80">
                                                {{ t.available }}</td>
                                        </tr>

                                        <!-- GB Breakdown (Hierarchical under Type) -->
                                        <template v-if="showPerGb" v-for="gb in t.gbs" :key="gb.label">
                                            <tr class="bg-surface-900/40 hover:bg-surface-700/40 transition-colors">
                                                <td class="px-6 py-2.5"></td>
                                                <td class="px-6 py-2.5"></td>
                                                <td class="px-6 py-2.5"></td>
                                                <td class="px-6 py-2.5"></td>
                                                <td class="px-6 py-2.5">
                                                    <span
                                                        class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-primary-500/5 text-primary-400/80 border border-primary-500/10">
                                                        {{ gb.label }}
                                                    </span>
                                                </td>
                                                <td
                                                    class="px-6 py-2.5 text-center text-sm font-semibold text-emerald-400/70">
                                                    {{ gb.available }}</td>
                                            </tr>
                                        </template>
                                    </template>
                                </template>

                                <!-- Special case: if Tipe is ON but Brand is OFF, we still want hierarchical grouping but maybe flattened? 
                                     The user said "Brand -> Tipe -> GB", so we assume they follow the hierarchy. 
                                     If only Tipe is ON, we show nothing currenty. Let's fix that. -->
                                <template v-if="!showConditionBrand && showConditionType" v-for="b in row.tree">
                                    <template v-for="t in b.types">
                                        <tr class="bg-surface-900/20 hover:bg-surface-700/20 transition-colors">
                                            <td class="px-6 py-2.5"></td>
                                            <td class="px-6 py-2.5"></td>
                                            <td class="px-6 py-2.5 text-xs font-bold text-text-primary">{{ t.label }}
                                            </td>
                                            <td v-if="showPerGb" class="px-6 py-2.5"></td>
                                            <td
                                                class="px-6 py-2.5 text-center text-sm font-semibold text-emerald-400/80">
                                                {{ t.available }}</td>
                                        </tr>
                                        <template v-if="showPerGb" v-for="gb in t.gbs">
                                            <tr class="bg-surface-900/30 hover:bg-surface-700/30 transition-colors">
                                                <td class="px-6 py-2.5"></td>
                                                <td class="px-6 py-2.5"></td>
                                                <td class="px-6 py-2.5"></td>
                                                <td class="px-6 py-2.5">
                                                    <span
                                                        class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-primary-500/5 text-primary-400/80 border border-primary-500/10">
                                                        {{ gb.label }}
                                                    </span>
                                                </td>
                                                <td
                                                    class="px-6 py-2.5 text-center text-sm font-semibold text-emerald-400/70">
                                                    {{ gb.available }}</td>
                                            </tr>
                                        </template>
                                    </template>
                                </template>

                                <!-- Special case: if ONLY GB is ON -->
                                <template v-if="!showConditionBrand && !showConditionType && showPerGb"
                                    v-for="b in row.tree">
                                    <template v-for="t in b.types">
                                        <template v-for="gb in t.gbs">
                                            <tr class="bg-surface-900/20 hover:bg-surface-700/20 transition-colors">
                                                <td class="px-6 py-2.5"></td>
                                                <td class="px-6 py-2.5"></td>
                                                <td class="px-6 py-2.5">
                                                    <span
                                                        class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-primary-500/5 text-primary-400/80 border border-primary-500/10">
                                                        {{ gb.label }}
                                                    </span>
                                                </td>
                                                <td
                                                    class="px-6 py-2.5 text-center text-sm font-semibold text-emerald-400/70">
                                                    {{ gb.available }}</td>
                                            </tr>
                                        </template>
                                    </template>
                                </template>
                            </template>
                        </tbody>
                        <tfoot class="bg-surface-900/70 border-t border-surface-600">
                            <tr class="font-bold">
                                <td class="px-6 py-4 text-right text-text-secondary"
                                    :colspan="(showConditionBrand ? 1 : 0) + (showConditionType ? 1 : 0) + (showPerGb ? 1 : 0) + 2">
                                    TOTAL</td>
                                <td class="px-6 py-4 text-center text-emerald-400 text-lg">
                                    {{filteredCondition.reduce((s, r) => s + r.available, 0)}}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </template>

        <!-- ==================== DISTRIBUTOR REPORT ==================== -->
        <template v-else-if="currentView === 'distributor'">
            <div class="bg-surface-800 rounded-2xl border border-surface-700 overflow-hidden">
                <div v-if="loading" class="p-12 flex justify-center items-center">
                    <RefreshCw class="animate-spin text-primary-500" :size="32" />
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-text-primary">
                        <thead class="bg-surface-900/50 text-text-secondary uppercase text-xs font-semibold">
                            <tr>
                                <th class="px-6 py-4">#</th>
                                <th class="px-6 py-4">Distributor</th>
                                <th v-if="showDistributorBrand" class="px-6 py-4">Brand</th>
                                <th v-if="showDistributorType" class="px-6 py-4">Tipe Produk</th>
                                <th v-if="showDistributorGb" class="px-6 py-4">Kapasitas</th>
                                <th class="px-6 py-4 text-center">Tersedia</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-700/50">
                            <template v-for="(row, idx) in filteredDistributor" :key="row.name">
                                <tr class="hover:bg-surface-700/30 transition-colors"
                                    :class="{ 'bg-surface-900/30': showDistributorGb || showDistributorBrand || showDistributorType }">
                                    <td class="px-6 py-4 text-text-secondary text-xs">{{ idx + 1 }}</td>
                                    <td class="px-6 py-4 font-bold text-white">{{ row.name }}</td>
                                    <td v-if="showDistributorBrand"
                                        class="px-6 py-4 text-text-secondary italic text-xs">—</td>
                                    <td v-if="showDistributorType" class="px-6 py-4 text-text-secondary italic text-xs">
                                        —</td>
                                    <td v-if="showDistributorGb" class="px-6 py-4 text-text-secondary italic text-xs">—
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="text-lg font-bold"
                                            :class="row.available > 0 ? 'text-emerald-400' : 'text-red-400'">{{
                                                row.available }}</span>
                                    </td>
                                </tr>

                                <!-- Distributor Location Breakdown -->
                                <tr v-if="showDistributorLocation" class="bg-indigo-500/5">
                                    <td colspan="6" class="px-6 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <span v-for="loc in row.locations" :key="loc.name"
                                                class="px-3 py-1.5 rounded-xl bg-surface-900 border border-indigo-500/20 text-[11px] flex items-center gap-2 shadow-sm shadow-indigo-500/5">
                                                <span class="text-text-secondary font-medium">{{ loc.name }}</span>
                                                <span class="font-black text-indigo-400">{{ loc.qty }}</span>
                                            </span>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Brand Breakdown -->
                                <template v-if="showDistributorBrand" v-for="b in row.tree" :key="b.label">
                                    <tr class="bg-surface-900/20 hover:bg-surface-700/20 transition-colors">
                                        <td class="px-6 py-2.5"></td>
                                        <td class="px-6 py-2.5"></td>
                                        <td class="px-6 py-2.5 text-xs font-bold text-text-primary">{{ b.label }}</td>
                                        <td v-if="showDistributorType" class="px-6 py-2.5"></td>
                                        <td v-if="showDistributorGb" class="px-6 py-2.5"></td>
                                        <td class="px-6 py-2.5 text-center text-sm font-semibold text-emerald-400/80">{{
                                            b.available }}</td>
                                    </tr>

                                    <!-- Type Breakdown (Hierarchical under Brand) -->
                                    <template v-if="showDistributorType" v-for="t in b.types" :key="t.label">
                                        <tr class="bg-surface-900/30 hover:bg-surface-700/30 transition-colors">
                                            <td class="px-6 py-2.5"></td>
                                            <td class="px-6 py-2.5"></td>
                                            <td class="px-6 py-2.5"></td>
                                            <td
                                                class="px-6 py-2.5 text-xs font-bold text-text-primary underline decoration-primary-500/30">
                                                {{ t.label }}</td>
                                            <td v-if="showDistributorGb" class="px-6 py-2.5"></td>
                                            <td
                                                class="px-6 py-2.5 text-center text-sm font-semibold text-emerald-400/80">
                                                {{ t.available }}</td>
                                        </tr>

                                        <!-- GB Breakdown (Hierarchical under Type) -->
                                        <template v-if="showDistributorGb" v-for="gb in t.gbs" :key="gb.label">
                                            <tr class="bg-surface-900/40 hover:bg-surface-700/40 transition-colors">
                                                <td class="px-6 py-2.5"></td>
                                                <td class="px-6 py-2.5"></td>
                                                <td class="px-6 py-2.5"></td>
                                                <td class="px-6 py-2.5"></td>
                                                <td class="px-6 py-2.5">
                                                    <span
                                                        class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-primary-500/5 text-primary-400/80 border border-primary-500/10">
                                                        {{ gb.label }}
                                                    </span>
                                                </td>
                                                <td
                                                    class="px-6 py-2.5 text-center text-sm font-semibold text-emerald-400/70">
                                                    {{ gb.available }}</td>
                                            </tr>
                                        </template>
                                    </template>
                                </template>

                                <!-- Special cases for partial toggles -->
                                <template v-if="!showDistributorBrand && showDistributorType" v-for="b in row.tree">
                                    <template v-for="t in b.types">
                                        <tr class="bg-surface-900/20 hover:bg-surface-700/20 transition-colors">
                                            <td class="px-6 py-2.5"></td>
                                            <td class="px-6 py-2.5"></td>
                                            <td class="px-6 py-2.5 text-xs font-bold text-text-primary">{{ t.label }}
                                            </td>
                                            <td v-if="showDistributorGb" class="px-6 py-2.5"></td>
                                            <td
                                                class="px-6 py-2.5 text-center text-sm font-semibold text-emerald-400/80">
                                                {{ t.available }}</td>
                                        </tr>
                                        <template v-if="showDistributorGb" v-for="gb in t.gbs">
                                            <tr class="bg-surface-900/30 hover:bg-surface-700/30 transition-colors">
                                                <td class="px-6 py-2.5"></td>
                                                <td class="px-6 py-2.5"></td>
                                                <td class="px-6 py-2.5"></td>
                                                <td class="px-6 py-2.5">
                                                    <span
                                                        class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-primary-500/5 text-primary-400/80 border border-primary-500/10">
                                                        {{ gb.label }}
                                                    </span>
                                                </td>
                                                <td
                                                    class="px-6 py-2.5 text-center text-sm font-semibold text-emerald-400/70">
                                                    {{ gb.available }}</td>
                                            </tr>
                                        </template>
                                    </template>
                                </template>

                                <template v-if="!showDistributorBrand && !showDistributorType && showDistributorGb"
                                    v-for="b in row.tree">
                                    <template v-for="t in b.types">
                                        <template v-for="gb in t.gbs">
                                            <tr class="bg-surface-900/20 hover:bg-surface-700/20 transition-colors">
                                                <td class="px-6 py-2.5"></td>
                                                <td class="px-6 py-2.5"></td>
                                                <td class="px-6 py-2.5">
                                                    <span
                                                        class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-primary-500/5 text-primary-400/80 border border-primary-500/10">
                                                        {{ gb.label }}
                                                    </span>
                                                </td>
                                                <td
                                                    class="px-6 py-2.5 text-center text-sm font-semibold text-emerald-400/70">
                                                    {{ gb.available }}</td>
                                            </tr>
                                        </template>
                                    </template>
                                </template>
                            </template>
                        </tbody>
                        <tfoot class="bg-surface-900/70 border-t border-surface-600">
                            <tr class="font-bold">
                                <td class="px-6 py-4 text-right text-text-secondary"
                                    :colspan="(showDistributorBrand ? 1 : 0) + (showDistributorType ? 1 : 0) + (showDistributorGb ? 1 : 0) + 2">
                                    TOTAL</td>
                                <td class="px-6 py-4 text-center text-emerald-400 text-lg">
                                    {{filteredDistributor.reduce((s, r) => s + r.available, 0)}}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </template>
        <!-- ==================== CATEGORY REPORT ==================== -->
        <template v-else-if="currentView === 'category'">
            <div class="bg-surface-800 rounded-2xl border border-surface-700 overflow-hidden">
                <div v-if="loading" class="p-12 flex justify-center items-center">
                    <RefreshCw class="animate-spin text-primary-500" :size="32" />
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-text-primary">
                        <thead class="bg-surface-900/50 text-text-secondary uppercase text-xs font-semibold">
                            <tr>
                                <th class="px-6 py-4">#</th>
                                <th class="px-6 py-4">Kategori</th>
                                <th v-if="showCategoryBrand" class="px-6 py-4">Tipe Produk</th>
                                <th v-if="showCategoryType" class="px-6 py-4">Lokasi (Cabang/Store)</th>
                                <th class="px-6 py-4 text-center">Tersedia</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-700/50">
                            <template v-for="(row, idx) in filteredCategory" :key="row.category">
                                <tr class="hover:bg-surface-700/30 transition-colors"
                                    :class="{ 'bg-surface-900/30': showCategoryBrand || showCategoryType }">
                                    <td class="px-6 py-4 text-text-secondary text-xs">{{ idx + 1 }}</td>
                                    <td class="px-6 py-4 font-bold text-white">{{ row.category }}</td>
                                    <td v-if="showCategoryBrand" class="px-6 py-4 text-text-secondary italic text-xs">—
                                    </td>
                                    <td v-if="showCategoryType" class="px-6 py-4 text-text-secondary italic text-xs">—
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="text-lg font-bold"
                                            :class="row.available > 0 ? 'text-emerald-400' : 'text-red-400'">{{
                                                row.available }}</span>
                                    </td>
                                </tr>

                                <!-- Category Location Breakdown -->
                                <tr v-if="showCategoryLocation" class="bg-indigo-500/5">
                                    <td colspan="5" class="px-6 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <span v-for="loc in row.locations" :key="loc.name"
                                                class="px-3 py-1.5 rounded-xl bg-surface-900 border border-indigo-500/20 text-[11px] flex items-center gap-2 shadow-sm shadow-indigo-500/5">
                                                <span class="text-text-secondary font-medium">{{ loc.name }}</span>
                                                <span class="font-black text-indigo-400">{{ loc.qty }}</span>
                                            </span>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Brand Breakdown -->
                                <template v-if="showCategoryBrand" v-for="b in row.tree" :key="b.label">
                                    <tr class="bg-surface-900/20 hover:bg-surface-700/20 transition-colors">
                                        <td class="px-6 py-2.5"></td>
                                        <td class="px-6 py-2.5"></td>
                                        <td class="px-6 py-2.5 text-xs font-bold text-text-primary">{{ b.label }}</td>
                                        <td v-if="showCategoryType" class="px-6 py-2.5"></td>
                                        <td class="px-6 py-2.5 text-center text-sm font-semibold text-emerald-400/80">{{
                                            b.available }}</td>
                                    </tr>

                                    <!-- Type Breakdown (Hierarchical under Brand) -->
                                    <template v-if="showCategoryType" v-for="t in b.types" :key="t.label">
                                        <tr class="bg-surface-900/30 hover:bg-surface-700/30 transition-colors">
                                            <td class="px-6 py-2.5"></td>
                                            <td class="px-6 py-2.5"></td>
                                            <td class="px-6 py-2.5"></td>
                                            <td
                                                class="px-6 py-2.5 text-xs font-bold text-text-primary underline decoration-primary-500/30">
                                                {{ t.label }}</td>
                                            <td
                                                class="px-6 py-2.5 text-center text-sm font-semibold text-emerald-400/80">
                                                {{ t.available }}</td>
                                        </tr>
                                    </template>
                                </template>

                                <!-- Case: if Type is ON but Brand is OFF -->
                                <template v-if="!showCategoryBrand && showCategoryType" v-for="b in row.tree">
                                    <template v-for="t in b.types">
                                        <tr class="bg-surface-900/20 hover:bg-surface-700/20 transition-colors">
                                            <td class="px-6 py-2.5"></td>
                                            <td class="px-6 py-2.5"></td>
                                            <td class="px-6 py-2.5 text-xs font-bold text-text-primary">{{ t.label }}
                                            </td>
                                            <td
                                                class="px-6 py-2.5 text-center text-sm font-semibold text-emerald-400/80">
                                                {{ t.available }}</td>
                                        </tr>
                                    </template>
                                </template>
                            </template>
                        </tbody>
                        <tfoot class="bg-surface-900/70 border-t border-surface-600">
                            <tr class="font-bold">
                                <td class="px-6 py-4 text-right text-text-secondary"
                                    :colspan="(showCategoryBrand ? 1 : 0) + (showCategoryType ? 1 : 0) + 2">TOTAL</td>
                                <td class="px-6 py-4 text-center text-emerald-400 text-lg">
                                    {{filteredCategory.reduce((s, r) => s + r.available, 0)}}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </template>


        <!-- ==================== NEW ERA REPORT ==================== -->
        <template v-else-if="currentView === 'new_era'">
            <div class="space-y-6 max-w-4xl mx-auto pb-32">
                <div
                    class="bg-surface-800 rounded-3xl border border-surface-700 p-6 md:p-10 shadow-2xl relative overflow-hidden print:bg-white print:text-black print:p-0 print:border-0 print:shadow-none">
                    <!-- Background Decor (Non-Print) -->
                    <div
                        class="absolute -top-24 -right-24 w-64 h-64 bg-primary-500/10 blur-[100px] rounded-full print:hidden">
                    </div>

                    <!-- Copy Tool (Non-Print) -->
                    <div class="flex justify-end gap-3 mb-6 relative z-30 print:hidden">
                        <button @click="fetchAllInventory()"
                            class="flex items-center gap-2 px-4 py-2 bg-surface-700 hover:bg-surface-600 active:scale-95 text-white rounded-xl text-xs font-bold transition-all cursor-pointer">
                            <RefreshCw :size="14" :class="{ 'animate-spin': loading }" /> Sync Data
                        </button>
                        <button @click="copyToClipboard()"
                            class="flex items-center gap-3 px-6 py-3 bg-primary-500 hover:bg-primary-400 active:scale-95 text-white rounded-2xl text-sm font-black transition-all shadow-xl shadow-primary-500/20 cursor-pointer">
                            <Copy :size="18" /> SALIN LAPORAN
                        </button>
                    </div>

                    <div class="relative">
                        <div
                            class="text-center mb-10 pb-8 border-b-4 border-double border-surface-700/50 print:border-black">
                            <h2
                                class="text-xs font-black text-primary-500 print:text-black uppercase tracking-[0.5em] mb-4">
                                Stock Report</h2>
                            <p
                                class="text-4xl font-black text-white print:text-black tabular-nums tracking-tighter mb-2">
                                {{ activeBranchName }}</p>
                            <div class="flex items-center justify-center gap-2">
                                <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
                                <p
                                    class="text-text-secondary print:text-black font-bold text-sm tracking-widest uppercase">
                                    {{ new Date().toLocaleDateString('id-ID', {
                                        day: '2-digit', month: 'long', year:
                                            'numeric'
                                    }) }}
                                </p>
                            </div>
                        </div>

                        <!-- Summary Table -->
                        <div class="mb-10">
                            <table class="w-full border-collapse">
                                <tbody class="text-lg font-bold">
                                    <tr class="border-b border-surface-700/50 print:border-black">
                                        <td class="py-2.5 text-text-secondary print:text-black">Iphone New</td>
                                        <td class="py-2.5 text-right text-white print:text-black tabular-nums">{{
                                            newEraReport.stats.iphone_new }}</td>
                                    </tr>
                                    <tr class="border-b border-surface-700/50 print:border-black">
                                        <td class="py-2.5 text-text-secondary print:text-black">Iphone Scd</td>
                                        <td class="py-2.5 text-right text-white print:text-black tabular-nums">{{
                                            newEraReport.stats.iphone_scd }}</td>
                                    </tr>
                                    <tr class="border-b border-surface-700/50 print:border-black">
                                        <td class="py-2.5 text-text-secondary print:text-black">Iphone Ex Ibox</td>
                                        <td class="py-2.5 text-right text-white print:text-black tabular-nums">{{
                                            newEraReport.stats.iphone_ex_ibox }}</td>
                                    </tr>
                                    <tr class="border-b border-surface-700/50 print:border-black">
                                        <td class="py-2.5 text-text-secondary print:text-black">Android New</td>
                                        <td class="py-2.5 text-right text-white print:text-black tabular-nums">{{
                                            newEraReport.stats.android_new }}</td>
                                    </tr>
                                    <tr class="border-b border-surface-700/50 print:border-black">
                                        <td class="py-2.5 text-text-secondary print:text-black">Android Scd</td>
                                        <td class="py-2.5 text-right text-white print:text-black tabular-nums">{{
                                            newEraReport.stats.android_scd }}</td>
                                    </tr>
                                    <tr class="border-b border-surface-700/50 print:border-black">
                                        <td class="py-2.5 text-text-secondary print:text-black">Laptop</td>
                                        <td class="py-2.5 text-right text-white print:text-black tabular-nums">{{
                                            newEraReport.stats.laptop }}</td>
                                    </tr>
                                    <tr class="border-b border-surface-700/50 print:border-black">
                                        <td class="py-2.5 text-text-secondary print:text-black">Tv</td>
                                        <td class="py-2.5 text-right text-white print:text-black tabular-nums">{{
                                            newEraReport.stats.tv }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="pt-6 border-t-2 border-surface-600 print:border-black mt-6">
                            <div class="flex justify-between items-center">
                                <span class="text-xl font-black text-white print:text-black uppercase italic">Total
                                    Handphone</span>
                                <span
                                    class="text-3xl font-black text-primary-500 print:text-black tabular-nums underline decoration-primary-500/30 underline-offset-8">{{
                                        newEraReport.stats.total_hp }}</span>
                            </div>
                        </div>

                        <!-- Details Section -->
                        <div class="space-y-10">
                            <div
                                class="h-px w-full bg-gradient-to-r from-transparent via-surface-600 to-transparent print:bg-black">
                            </div>

                            <h3
                                class="text-2xl font-black text-white print:text-black uppercase tracking-tighter italic text-center">
                                Rincian Unit</h3>

                            <!-- iPhone Sections -->
                            <div class="space-y-12">
                                <div v-if="newEraReport.details.iphone_new.length > 0">
                                    <div class="flex items-center gap-4 mb-4 print:mb-2">
                                        <div class="h-px flex-1 bg-emerald-500/30 print:bg-black"></div>
                                        <h4
                                            class="text-sm font-black text-emerald-400 print:text-black uppercase tracking-[0.3em]">
                                            Iphone New</h4>
                                        <div class="h-px flex-1 bg-emerald-500/30 print:bg-black"></div>
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-12 gap-y-2">
                                        <div v-for="item in newEraReport.details.iphone_new" :key="item.name"
                                            class="flex justify-between text-base font-bold py-1 border-b border-surface-700/20 print:border-black last:border-0">
                                            <span class="text-text-secondary print:text-black uppercase text-[10px]">{{
                                                item.name }}</span>
                                            <span class="text-white print:text-black tabular-nums">{{ item.qty }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="newEraReport.details.iphone_scd.length > 0">
                                    <div class="flex items-center gap-4 mb-4 print:mb-2">
                                        <div class="h-px flex-1 bg-amber-500/30 print:bg-black"></div>
                                        <h4
                                            class="text-sm font-black text-amber-400 print:text-black uppercase tracking-[0.3em]">
                                            Iphone Scd</h4>
                                        <div class="h-px flex-1 bg-amber-500/30 print:bg-black"></div>
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-12 gap-y-2">
                                        <div v-for="item in newEraReport.details.iphone_scd" :key="item.name"
                                            class="flex justify-between text-base font-bold py-1 border-b border-surface-700/20 print:border-black last:border-0">
                                            <span class="text-text-secondary print:text-black uppercase text-[10px]">{{
                                                item.name }}</span>
                                            <span class="text-white print:text-black tabular-nums">{{ item.qty }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="newEraReport.details.iphone_ex_ibox.length > 0">
                                    <div class="flex items-center gap-4 mb-4 print:mb-2">
                                        <div class="h-px flex-1 bg-blue-500/30 print:bg-black"></div>
                                        <h4
                                            class="text-sm font-black text-blue-400 print:text-black uppercase tracking-[0.3em]">
                                            Iphone Ex Ibox</h4>
                                        <div class="h-px flex-1 bg-blue-500/30 print:bg-black"></div>
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-12 gap-y-2">
                                        <div v-for="item in newEraReport.details.iphone_ex_ibox" :key="item.name"
                                            class="flex justify-between text-base font-bold py-1 border-b border-surface-700/20 print:border-black last:border-0">
                                            <span class="text-text-secondary print:text-black uppercase text-[10px]">{{
                                                item.name }}</span>
                                            <span class="text-white print:text-black tabular-nums">{{ item.qty }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Android Sections -->
                                <div v-if="newEraReport.details.android_new.length > 0">
                                    <div class="flex items-center gap-4 mb-4 print:mb-2">
                                        <div class="h-px flex-1 bg-purple-500/30 print:bg-black"></div>
                                        <h4
                                            class="text-sm font-black text-purple-400 print:text-black uppercase tracking-[0.3em]">
                                            Android New</h4>
                                        <div class="h-px flex-1 bg-purple-500/30 print:bg-black"></div>
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-12 gap-y-2">
                                        <div v-for="item in newEraReport.details.android_new" :key="item.name"
                                            class="flex justify-between text-base font-bold py-1 border-b border-surface-700/20 print:border-black last:border-0">
                                            <span class="text-text-secondary print:text-black uppercase text-[10px]">{{
                                                item.name }}</span>
                                            <span class="text-white print:text-black tabular-nums">{{ item.qty }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="newEraReport.details.android_scd.length > 0">
                                    <div class="flex items-center gap-4 mb-4 print:mb-2">
                                        <div class="h-px flex-1 bg-indigo-500/30 print:bg-black"></div>
                                        <h4
                                            class="text-sm font-black text-indigo-400 print:text-black uppercase tracking-[0.3em]">
                                            Android Scd</h4>
                                        <div class="h-px flex-1 bg-indigo-500/30 print:bg-black"></div>
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-12 gap-y-2">
                                        <div v-for="item in newEraReport.details.android_scd" :key="item.name"
                                            class="flex justify-between text-base font-bold py-1 border-b border-surface-700/20 print:border-black last:border-0">
                                            <span class="text-text-secondary print:text-black uppercase text-[10px]">{{
                                                item.name }}</span>
                                            <span class="text-white print:text-black tabular-nums">{{ item.qty }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Laptop Section -->
                                <div v-if="newEraReport.details.laptop.length > 0">
                                    <div class="flex items-center gap-4 mb-4 print:mb-2">
                                        <div class="h-px flex-1 bg-blue-400/30 print:bg-black"></div>
                                        <h4
                                            class="text-sm font-black text-blue-400 print:text-black uppercase tracking-[0.3em]">
                                            Laptop</h4>
                                        <div class="h-px flex-1 bg-blue-400/30 print:bg-black"></div>
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-12 gap-y-2">
                                        <div v-for="item in newEraReport.details.laptop" :key="item.name"
                                            class="flex justify-between text-base font-bold py-1 border-b border-surface-700/20 print:border-black last:border-0">
                                            <span class="text-text-secondary print:text-black uppercase text-[10px]">{{
                                                item.name }}</span>
                                            <span class="text-white print:text-black tabular-nums">{{ item.qty }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- TV Section -->
                                <div v-if="newEraReport.details.tv.length > 0">
                                    <div class="flex items-center gap-4 mb-4 print:mb-2">
                                        <div class="h-px flex-1 bg-rose-500/30 print:bg-black"></div>
                                        <h4
                                            class="text-sm font-black text-rose-400 print:text-black uppercase tracking-[0.3em]">
                                            TV</h4>
                                        <div class="h-px flex-1 bg-rose-500/30 print:bg-black"></div>
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-12 gap-y-2">
                                        <div v-for="item in newEraReport.details.tv" :key="item.name"
                                            class="flex justify-between text-base font-bold py-1 border-b border-surface-700/20 print:border-black last:border-0">
                                            <span class="text-text-secondary print:text-black uppercase text-[10px]">{{
                                                item.name }}</span>
                                            <span class="text-white print:text-black tabular-nums">{{ item.qty }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons (Non-Print) -->
                <div class="flex justify-center gap-4 pb-20 print:hidden">
                    <button @click="goBack()"
                        class="flex items-center gap-3 px-8 py-4 bg-surface-700 hover:bg-surface-600 text-white rounded-2xl font-black uppercase tracking-widest transition-all shadow-xl hover:scale-[1.02] active:scale-[0.98]">
                        <ArrowLeft :size="20" /> Kembali
                    </button>
                </div>
            </div>
        </template>
        <!-- ==================== HISTORY STOK VIEW ==================== -->
        <template v-else-if="currentView === 'history'">
            <div class="space-y-6 pb-20">
                <!-- Modern Filters Bar -->
                <div class="history-filter-bar">
                    <div class="flex flex-col gap-5 w-full">
                        <!-- Row 1: Period Toggle + Date Navigator -->
                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-4">
                            <!-- Mode Selector (Harian/Bulanan) -->
                            <div class="flex flex-col gap-1.5">
                                 <label class="text-[10px] font-black uppercase tracking-[0.15em] text-text-secondary/70 ml-1">Periode</label>
                                 <div class="flex p-1 bg-surface-900 rounded-xl border border-surface-700">
                                     <button @click="historyMode = 'daily'" 
                                        class="px-5 py-2.5 rounded-lg text-xs font-bold transition-all duration-300"
                                        :class="historyMode === 'daily' ? 'bg-gradient-to-r from-primary-500 to-primary-400 text-white shadow-lg shadow-primary-500/25' : 'text-text-secondary hover:text-text-primary hover:bg-surface-700/50'">
                                        📅 Harian
                                     </button>
                                     <button @click="historyMode = 'monthly'" 
                                        class="px-5 py-2.5 rounded-lg text-xs font-bold transition-all duration-300"
                                        :class="historyMode === 'monthly' ? 'bg-gradient-to-r from-primary-500 to-primary-400 text-white shadow-lg shadow-primary-500/25' : 'text-text-secondary hover:text-text-primary hover:bg-surface-700/50'">
                                        📊 Bulanan
                                     </button>
                                 </div>
                            </div>

                            <!-- Date Navigator -->
                            <!-- Date Navigator (Daily Mode) -->
                            <div v-if="historyMode === 'daily'" class="flex flex-col gap-1.5 flex-1 min-w-0">
                                <label class="text-[10px] font-black uppercase tracking-[0.15em] text-text-secondary/70 ml-1">Navigasi Tanggal</label>
                                <div class="flex items-center gap-2">
                                    <button @click="navigateDate(-1)" 
                                        :disabled="historyDate <= minDate"
                                        class="p-2.5 rounded-xl bg-surface-900 border border-surface-700 text-text-secondary hover:text-text-primary hover:bg-surface-700 hover:border-primary-500/30 transition-all duration-200 active:scale-90 disabled:opacity-30 disabled:cursor-not-allowed">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                                    </button>

                                    <div class="relative flex-1 min-w-0 group cursor-pointer" @click="$refs.dateInput.showPicker?.() || $refs.dateInput.focus()">
                                        <div class="flex items-center gap-3 px-4 py-2.5 bg-surface-900 border border-surface-700 rounded-xl group-hover:border-primary-500/40 transition-all duration-200">
                                            <Calendar class="text-primary-500 shrink-0 group-hover:text-primary-400 transition-colors" :size="16" />
                                            <span class="text-sm font-bold text-text-primary truncate">{{ formattedDate }}</span>
                                            <span v-if="isToday" class="ml-auto px-2 py-0.5 rounded-md bg-primary-500/15 border border-primary-500/20 text-[9px] font-black text-primary-600 uppercase tracking-wider shrink-0">Hari Ini</span>
                                        </div>
                                        <input ref="dateInput" type="date" v-model="historyDate" :min="minDate" :max="maxDate"
                                            class="absolute inset-0 opacity-0 cursor-pointer" />
                                    </div>

                                    <button @click="navigateDate(1)" 
                                        :disabled="historyDate >= maxDate"
                                        class="p-2.5 rounded-xl bg-surface-900 border border-surface-700 text-text-secondary hover:text-text-primary hover:bg-surface-700 hover:border-primary-500/30 transition-all duration-200 active:scale-90 disabled:opacity-30 disabled:cursor-not-allowed">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                                    </button>

                                    <button v-if="!isToday" @click="goToToday()"
                                        class="px-3 py-2.5 rounded-xl bg-primary-500/10 border border-primary-500/20 text-primary-600 text-xs font-bold hover:bg-primary-500/20 hover:border-primary-500/40 transition-all duration-200 active:scale-95 whitespace-nowrap">
                                        Hari Ini
                                    </button>
                                </div>
                            </div>

                            <!-- Month Navigator (Monthly Mode) -->
                            <div v-else class="flex flex-col gap-1.5 flex-1 min-w-0">
                                <label class="text-[10px] font-black uppercase tracking-[0.15em] text-text-secondary/70 ml-1">Pilih Bulan (Audit 2 Bulan Terakhir)</label>
                                <div class="flex items-center gap-3">
                                    <button @click="setHistoryMonth(true)" 
                                        class="flex-1 flex items-center justify-center gap-2 px-6 py-3 rounded-2xl border transition-all duration-300 font-black text-sm uppercase tracking-wider"
                                        :class="new Date(historyDate).getMonth() === new Date().getMonth() 
                                            ? 'bg-primary-500 border-primary-400 text-white shadow-xl shadow-primary-500/20' 
                                            : 'bg-surface-900 border-surface-700 text-text-secondary hover:text-white hover:bg-surface-800'">
                                        <Calendar :size="18" /> Bulan Ini
                                    </button>
                                    <button @click="setHistoryMonth(false)" 
                                        class="flex-1 flex items-center justify-center gap-2 px-6 py-3 rounded-2xl border transition-all duration-300 font-black text-sm uppercase tracking-wider"
                                        :class="new Date(historyDate).getMonth() === (new Date().getMonth() === 0 ? 11 : new Date().getMonth() - 1)
                                            ? 'bg-indigo-600 border-indigo-500 text-white shadow-xl shadow-indigo-500/20' 
                                            : 'bg-surface-900 border-surface-700 text-text-secondary hover:text-white hover:bg-surface-800'">
                                        <History :size="18" /> Bulan Kemarin
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Row 2: Reset Info + Refresh -->
                        <div class="flex items-center justify-between gap-4 pt-3 border-t border-surface-700/50">
                            <div class="flex items-center gap-3">
                                <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-surface-900 border border-surface-700/50">
                                    <Clock class="text-primary-500" :size="13" />
                                    <span class="text-[10px] font-bold text-text-secondary">Reset:</span>
                                    <span class="text-[10px] font-black text-primary-600 tabular-nums">{{ resetTime || '05:00' }}</span>
                                </div>
                                <span class="text-[10px] text-text-secondary/50 hidden sm:inline">Mutasi dihitung sejak jam 05:00 pagi</span>
                            </div>
                            <button @click="fetchStockHistory()" 
                                class="flex items-center gap-2 px-4 py-2 bg-surface-700 hover:bg-surface-600 rounded-xl text-text-primary text-xs font-bold transition-all duration-200 shadow-sm active:scale-95 border border-surface-600/50 hover:border-primary-500/30">
                                <RefreshCw :size="14" :class="{'animate-spin': historyLoading}" />
                                <span class="hidden sm:inline">Refresh</span>
                            </button>
                        </div>
                    </div>
                </div>



                <!-- History Table (Placeholder) -->
                <div class="bg-surface-800 rounded-2xl border border-surface-700 overflow-hidden">
                    <div class="p-6 border-b border-surface-700 flex justify-between items-center">
                        <div>
                            <h3 class="font-bold text-text-primary text-lg">📊 Mutasi Stok</h3>
                            <p class="text-[10px] text-text-secondary uppercase tracking-widest mt-1">Reset Tiap Jam 05:00 AM • <span class="text-primary-600 font-bold">Jam Reset: {{ resetTime }}</span></p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button @click="downloadExcel('mutation')" class="flex items-center gap-3 px-5 py-2 bg-blue-600/10 hover:bg-blue-600/20 border border-blue-600/30 rounded-xl text-blue-400 text-xs font-black transition-all active:scale-95">
                                <Download :size="14" /> DOWNLOAD EXCEL (MUTASI)
                            </button>
                            <div class="w-px h-6 bg-surface-700 mx-1"></div>
                            <button @click="fetchStockHistory()" class="flex items-center gap-2 px-3 py-2 bg-surface-700 hover:bg-surface-600 rounded-xl text-text-primary text-[10px] font-bold transition-all border border-surface-600/50">
                                <RefreshCw :size="12" :class="{'animate-spin': historyLoading}" />
                                <span>REFRESH</span>
                            </button>
                        </div>
                    </div>
                    <div class="p-12 text-center" v-if="historyLoading">
                        <RefreshCw class="animate-spin text-primary-500 mx-auto mb-3" :size="32" />
                        <p class="text-text-secondary text-sm">Menghitung mutasi stok...</p>
                    </div>
                    <div v-else-if="itemMode === 'hp' && (!historyData.hp || historyData.hp.length === 0)" class="p-12 text-center text-text-secondary italic">
                         Belum ada mutasi stok Handphone untuk tanggal ini.
                    </div>
                    <div v-else-if="itemMode === 'non-hp' && (!historyData.non_hp || historyData.non_hp.length === 0)" class="p-12 text-center text-text-secondary italic">
                         Belum ada mutasi stok Aksesoris untuk tanggal ini.
                    </div>
                    <div v-else class="overflow-x-auto">
                        <!-- SECTION 1: UNIT HANDPHONE (Only show in HP Mode) -->
                        <div v-if="itemMode === 'hp' && historyData.hp && historyData.hp.length > 0">
                            <div class="px-6 py-3 bg-surface-900/50 border-y border-surface-700 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <Smartphone class="text-primary-500" :size="16" />
                                    <span class="text-xs font-black text-text-primary uppercase tracking-widest">LAPORAN MUTASI UNIT HP</span>
                                </div>
                                <span class="text-[9px] px-2 py-0.5 rounded bg-primary-500/10 text-primary-500 font-bold">IMEI BASED</span>
                            </div>
                            <table class="w-full text-[10px] text-left border-collapse">
                                <thead class="text-text-secondary uppercase font-bold sticky top-0 z-10 text-center">
                                    <tr>
                                        <th rowspan="2" class="px-4 py-3 border border-surface-700 min-w-[180px] bg-surface-900 text-left">Produk</th>
                                        <th rowspan="2" class="px-2 py-3 border border-surface-700 bg-surface-900 w-12">Awal</th>
                                        <th colspan="7" class="px-2 py-2 border border-surface-700 bg-emerald-500/10 text-emerald-600">Barang Masuk</th>
                                        <th colspan="7" class="px-2 py-2 border border-surface-700 bg-rose-500/10 text-rose-600">Barang Keluar</th>
                                        <th rowspan="2" class="px-2 py-3 border border-surface-700 bg-primary-500/10 text-primary-600 font-black w-12">Akhir</th>
                                    </tr>
                                    <tr class="bg-surface-900/60 text-[9px]">
                                        <th class="px-1 py-2 border border-surface-700 text-emerald-600/70">Total</th>
                                        <th class="px-1 py-2 border border-surface-700">Manual</th>
                                        <th class="px-1 py-2 border border-surface-700">TT</th>
                                        <th class="px-1 py-2 border border-surface-700">TU</th>
                                        <th class="px-1 py-2 border border-surface-700">DW</th>
                                        <th class="px-1 py-2 border border-surface-700">RF</th>
                                        <th class="px-1 py-2 border border-surface-700">AB</th>
                                        <th class="px-1 py-2 border border-surface-700 text-rose-600/70">Total</th>
                                        <th class="px-1 py-2 border border-surface-700">Sold</th>
                                        <th class="px-1 py-2 border border-surface-700">TT</th>
                                        <th class="px-1 py-2 border border-surface-700">TU</th>
                                        <th class="px-1 py-2 border border-surface-700">DW</th>
                                        <th class="px-1 py-2 border border-surface-700">Lainnya</th>
                                        <th class="px-1 py-2 border border-surface-700">Retur</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-surface-700/50">
                                    <tr v-for="item in historyData.hp" :key="item.name" class="hover:bg-surface-700/20 transition-colors text-center tabular-nums">
                                        <td class="px-4 py-2.5 border border-surface-700/30 text-left font-bold text-text-primary text-[10px]">
                                            {{ item.name }}
                                        </td>
                                        <td class="px-1 py-2.5 border border-surface-700/30 font-bold text-text-secondary">{{ item.initial }}</td>
                                        <td class="px-1 py-2.5 border border-surface-700/30 bg-emerald-500/5 font-black text-emerald-600">{{ item.in_total }}</td>
                                        <td class="px-1 py-2.5 border border-surface-700/30" :class="item.in_manual > 0 ? 'text-emerald-600 font-bold' : 'opacity-20 text-text-secondary'">{{ item.in_manual }}</td>
                                        <td class="px-1 py-2.5 border border-surface-700/30" :class="item.in_tt > 0 ? 'text-emerald-600 font-bold' : 'opacity-20 text-text-secondary'">{{ item.in_tt }}</td>
                                        <td class="px-1 py-2.5 border border-surface-700/30" :class="item.in_tu > 0 ? 'text-emerald-600 font-bold' : 'opacity-20 text-text-secondary'">{{ item.in_tu }}</td>
                                        <td class="px-1 py-2.5 border border-surface-700/30" :class="item.in_dw > 0 ? 'text-emerald-600 font-bold' : 'opacity-20 text-text-secondary'">{{ item.in_dw }}</td>
                                        <td class="px-1 py-2.5 border border-surface-700/30" :class="item.in_rf > 0 ? 'text-emerald-600 font-bold' : 'opacity-20 text-text-secondary'">{{ item.in_rf }}</td>
                                        <td class="px-1 py-2.5 border border-surface-700/30" :class="item.in_ab > 0 ? 'text-emerald-600 font-bold' : 'opacity-20 text-text-secondary'">{{ item.in_ab }}</td>
                                        <td class="px-1 py-2.5 border border-surface-700/30 bg-rose-500/5 font-black text-rose-600">{{ item.out_total }}</td>
                                        <td class="px-1 py-2.5 border border-surface-700/30" :class="item.out_sold > 0 ? 'text-sky-600 font-bold' : 'opacity-20 text-text-secondary'">{{ item.out_sold }}</td>
                                        <td class="px-1 py-2.5 border border-surface-700/30" :class="item.out_tt > 0 ? 'text-rose-600 font-bold' : 'opacity-20 text-text-secondary'">{{ item.out_tt }}</td>
                                        <td class="px-1 py-2.5 border border-surface-700/30" :class="item.out_tu > 0 ? 'text-rose-600 font-bold' : 'opacity-20 text-text-secondary'">{{ item.out_tu }}</td>
                                        <td class="px-1 py-2.5 border border-surface-700/30" :class="item.out_dw > 0 ? 'text-rose-600 font-bold' : 'opacity-20 text-text-secondary'">{{ item.out_dw }}</td>
                                        <td class="px-1 py-2.5 border border-surface-700/30" :class="(item.out_pindah + item.out_kesalahan + item.out_keluar + item.out_hilang) > 0 ? 'text-rose-600 font-bold' : 'opacity-20 text-text-secondary'">
                                            {{ item.out_pindah + (item.out_kesalahan || 0) + (item.out_keluar || 0) + (item.out_hilang || 0) }}
                                        </td>
                                        <td class="px-1 py-2.5 border border-surface-700/30" :class="item.out_retur > 0 ? 'text-purple-600 font-bold' : 'opacity-20 text-text-secondary'">{{ item.out_retur }}</td>
                                        <td class="px-1 py-2.5 border border-surface-700/30 font-black text-primary-600 bg-primary-500/5">{{ item.final }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- SECTION 2: AKSESORIS & LAINNYA (Only show in Non-HP Mode) -->
                        <div v-if="itemMode === 'non-hp' && historyData.non_hp && historyData.non_hp.length > 0">
                            <div class="px-6 py-3 bg-surface-900/50 border-y border-surface-700 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <Box class="text-orange-500" :size="16" />
                                    <span class="text-xs font-black text-text-primary uppercase tracking-widest">LAPORAN MUTASI AKSESORIS</span>
                                </div>
                                <span class="text-[9px] px-2 py-0.5 rounded bg-orange-500/10 text-orange-500 font-bold">QTY BASED</span>
                            </div>
                            <table class="w-full text-[10px] text-left border-collapse">
                                <thead class="text-text-secondary uppercase font-bold sticky top-0 z-10 text-center">
                                    <tr>
                                        <th rowspan="2" class="px-4 py-3 border border-surface-700 min-w-[180px] bg-surface-900 text-left">Nama Barang</th>
                                        <th rowspan="2" class="px-2 py-3 border border-surface-700 bg-surface-900 w-12">Awal</th>
                                        <th colspan="7" class="px-2 py-2 border border-surface-700 bg-emerald-500/10 text-emerald-600">Barang Masuk</th>
                                        <th colspan="7" class="px-2 py-2 border border-surface-700 bg-rose-500/10 text-rose-600">Barang Keluar</th>
                                        <th rowspan="2" class="px-2 py-3 border border-surface-700 bg-primary-500/10 text-primary-600 font-black w-12">Akhir</th>
                                    </tr>
                                    <tr class="bg-surface-900/60 text-[9px]">
                                        <th class="px-1 py-2 border border-surface-700 text-emerald-600/70">Total</th>
                                        <th class="px-1 py-2 border border-surface-700">Manual</th>
                                        <th class="px-2 py-2 border border-surface-700 opacity-20">TT</th>
                                        <th class="px-2 py-2 border border-surface-700 opacity-20">TU</th>
                                        <th class="px-2 py-2 border border-surface-700 opacity-20">DW</th>
                                        <th class="px-2 py-2 border border-surface-700 opacity-20">RF</th>
                                        <th class="px-1 py-2 border border-surface-700">AB</th>
                                        <th class="px-1 py-2 border border-surface-700 text-rose-600/70">Total</th>
                                        <th class="px-1 py-2 border border-surface-700">Sold</th>
                                        <th class="px-2 py-2 border border-surface-700 opacity-20">TT</th>
                                        <th class="px-2 py-2 border border-surface-700 opacity-20">TU</th>
                                        <th class="px-2 py-2 border border-surface-700 opacity-20">DW</th>
                                        <th class="px-1 py-2 border border-surface-700">Lainnya</th>
                                        <th class="px-1 py-2 border border-surface-700">Retur</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-surface-700/50">
                                    <tr v-for="item in historyData.non_hp" :key="item.name" class="hover:bg-surface-700/20 transition-colors text-center tabular-nums">
                                        <td class="px-4 py-2.5 border border-surface-700/30 text-left font-bold text-text-primary text-[10px]">
                                            {{ item.name }}
                                        </td>
                                        <td class="px-1 py-2.5 border border-surface-700/30 font-bold text-text-secondary">{{ item.initial }}</td>
                                        <td class="px-1 py-2.5 border border-surface-700/30 bg-emerald-500/5 font-black text-emerald-600">{{ item.in_total }}</td>
                                        <td class="px-1 py-2.5 border border-surface-700/30" :class="item.in_manual > 0 ? 'text-emerald-600 font-bold' : 'opacity-20 text-text-secondary'">{{ item.in_manual }}</td>
                                        <td class="px-1 py-2.5 border border-surface-700/30 opacity-10">-</td>
                                        <td class="px-1 py-2.5 border border-surface-700/30 opacity-10">-</td>
                                        <td class="px-1 py-2.5 border border-surface-700/30 opacity-10">-</td>
                                        <td class="px-1 py-2.5 border border-surface-700/30 opacity-10">-</td>
                                        <td class="px-1 py-2.5 border border-surface-700/30" :class="item.in_ab > 0 ? 'text-emerald-600 font-bold' : 'opacity-20 text-text-secondary'">{{ item.in_ab }}</td>
                                        <td class="px-1 py-2.5 border border-surface-700/30 bg-rose-500/5 font-black text-rose-600">{{ item.out_total }}</td>
                                        <td class="px-1 py-2.5 border border-surface-700/30" :class="item.out_sold > 0 ? 'text-sky-600 font-bold' : 'opacity-20 text-text-secondary'">{{ item.out_sold }}</td>
                                        <td class="px-1 py-2.5 border border-surface-700/30 opacity-10">-</td>
                                        <td class="px-1 py-2.5 border border-surface-700/30 opacity-10">-</td>
                                        <td class="px-1 py-2.5 border border-surface-700/30 opacity-10">-</td>
                                        <td class="px-1 py-2.5 border border-surface-700/30" :class="(item.out_pindah + item.out_kesalahan + item.out_keluar + item.out_hilang) > 0 ? 'text-rose-600 font-bold' : 'opacity-20 text-text-secondary'">
                                            {{ item.out_pindah + (item.out_kesalahan || 0) + (item.out_keluar || 0) + (item.out_hilang || 0) }}
                                        </td>
                                        <td class="px-1 py-2.5 border border-surface-700/30" :class="item.out_retur > 0 ? 'text-purple-600 font-bold' : 'opacity-20 text-text-secondary'">{{ item.out_retur }}</td>
                                        <td class="px-1 py-2.5 border border-surface-700/30 font-black text-primary-600 bg-primary-500/5">{{ item.final }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Download Logs -->
                <div class="bg-surface-800 rounded-2xl border border-surface-700 overflow-hidden">
                    <div class="p-6 border-b border-surface-700 flex items-center gap-3">
                         <History class="text-primary-400" :size="20" />
                         <h3 class="font-bold text-white">History Download</h3>
                    </div>
                    <div class="overflow-x-auto">
                         <table class="w-full text-sm text-left">
                            <thead class="bg-surface-900/50 text-text-secondary uppercase text-[10px] font-bold">
                                <tr>
                                    <th class="px-6 py-4">Waktu</th>
                                    <th class="px-6 py-4">User</th>
                                    <th class="px-6 py-4">Laporan</th>
                                    <th class="px-6 py-4">Filename</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-surface-700/50">
                                <tr v-for="log in downloadLogs" :key="log.id" class="hover:bg-surface-700/20 transition-colors">
                                    <td class="px-6 py-4 text-xs tabular-nums text-text-secondary">
                                        {{ new Date(log.downloaded_at).toLocaleString('id-ID') }}
                                    </td>
                                    <td class="px-6 py-4 font-medium text-white">{{ log.user?.name || 'System' }}</td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-0.5 rounded-lg bg-surface-900 border border-surface-700 text-[10px] font-bold text-primary-400">
                                            {{ log.report_name }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 font-mono text-[10px] text-text-secondary">{{ log.filename }}</td>
                                </tr>
                                <tr v-if="downloadLogs.length === 0">
                                    <td colspan="4" class="px-6 py-8 text-center text-text-secondary italic">Belum ada riwayat download.</td>
                                </tr>
                            </tbody>
                         </table>
                    </div>
                </div>

                <div class="flex justify-center pt-6">
                    <button @click="goBack()"
                        class="flex items-center gap-3 px-8 py-4 bg-surface-700 hover:bg-surface-600 text-white rounded-2xl font-black uppercase tracking-widest transition-all shadow-xl">
                        <ArrowLeft :size="20" /> Kembali
                    </button>
                </div>
            </div>
        </template>

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

/* Fix for select options visibility in dark mode */
select option {
    background-color: #111827 !important;
    /* darker background */
    color: #ffffff !important;
    padding: 10px;
}

/* History filter bar */
.history-filter-bar {
    background: var(--color-surface-800);
    border: 1px solid var(--color-surface-700);
    border-radius: 1.25rem;
    padding: 1.5rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    position: relative;
    overflow: hidden;
}

.history-filter-bar::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 2px;
    background: linear-gradient(90deg, transparent, var(--color-primary-400), transparent);
}
</style>
