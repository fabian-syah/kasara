const fs = require('fs');
let code = fs.readFileSync('frontend/src/views/audit/AuditInputTransfer.vue', 'utf8');

// 1. Add StockOutModal import and selectedItems state
code = code.replace(
  /import PinModal from \".*?PinModal\.vue\";/g,
  `import PinModal from "../../components/modals/PinModal.vue";
import { defineAsyncComponent } from "vue";
const StockOutModal = defineAsyncComponent(() => import("../../components/inventory/StockOutModal.vue"));

const showStockOutModal = ref(false);
const newlyInsertedItems = ref([]);
const auditWarehouseId = ref(null);`
);

// 2. Modify fetchInitialData to find AUDIT warehouse
code = code.replace(
  /const \[dist, user, brd, typ, prd\] = await Promise\.all\(\[/g,
  `const [dist, user, brd, typ, prd, wh] = await Promise.all([
            import("../../api/axios").then(m => m.warehouses.list({ all: 1 })),`
);

code = code.replace(
  /targetUsers\.value = \(user\.data\.data \|\| user\.data\);/g,
  `targetUsers.value = (user.data.data || user.data);
        const allWh = wh.data.data || wh.data;
        const auditWh = allWh.find(w => w.name.toLowerCase().includes('audit') || w.code === 'AUDIT');
        if (auditWh) {
            auditWarehouseId.value = auditWh.id;
            placementId.value = auditWh.id;
            placementType.value = 'warehouse';
        }`
);

// 3. Update currentStep initial value and remove persistence so it doesn't mess up
code = code.replace(/const currentStep = ref\(1\);/g, 'const currentStep = ref(2); // Skip step 1');
code = code.replace(/restoreDraft\(\);/g, '// restoreDraft(); disabled');
code = code.replace(/persistState\(\);/g, '// persistState(); disabled');

// 3.5 FIX THE WATCH SYNTAX ERROR
code = code.replace(/if \(newId\) \/\/ restoreDraft\(\); disabled/g, 'if (newId) { /* restoreDraft(); disabled */ }');

// 4. Update submitStockIn
const submitReplacement = `
        const response = await inventoryApi.stockIn(payload);
        
        toast.success(response.data.message || "Stock In berhasil");
        
        // Populate newlyInsertedItems for StockOutModal
        newlyInsertedItems.value = [];
        
        // Fetch the items we just inserted based on IMEIs or Product IDs
        // For HP
        if (itemType.value === 'hp') {
            const allImeis = [];
            hpItems.value.forEach(item => {
                allImeis.push(...item.parsedImeis);
            });
            if (allImeis.length > 0) {
                try {
                    // search each imei or just fetch recently added
                    const res = await inventoryApi.list({ warehouse_id: auditWarehouseId.value, type: 'hp', per_page: 50 });
                    const items = res.data.data || res.data;
                    newlyInsertedItems.value = items.filter(i => {
                        const imei = i.productDetail?.imei || i.imei;
                        return allImeis.includes(imei);
                    });
                } catch(e) {}
            }
        } else {
            // For NON-HP
            const pIds = [];
            for (const item of nonHpItems.value) {
                // To get exact items, we can just fetch all non-hp in audit warehouse and take newest
                try {
                    const res = await inventoryApi.list({ warehouse_id: auditWarehouseId.value, type: 'non-hp', per_page: 50 });
                    const items = res.data.data || res.data;
                    // Sort by id desc
                    items.sort((a, b) => b.id - a.id);
                    newlyInsertedItems.value = items.slice(0, nonHpItems.value.length);
                } catch(e) {}
            }
        }

        // Show Pindah Cabang modal
        showStockOutModal.value = true;
`;

code = code.replace(/const response = await inventoryApi\.stockIn\(payload\);[^]*?router\.push\('\/inventory'\);/g, submitReplacement);

// 5. Add StockOutModal to template and hide Step 1
code = code.replace(
  /<div v-if=\"currentStep === 1\"/g,
  '<div v-if="false"'
);

code = code.replace(
  /<\/template>/g,
  `    <StockOutModal
        v-if="showStockOutModal"
        :show="showStockOutModal"
        :selected-items="newlyInsertedItems"
        :active-tab="itemType"
        @close="showStockOutModal = false; router.push('/inventory')"
        @success="showStockOutModal = false; router.push('/inventory')"
    />
</template>`
);

fs.writeFileSync('frontend/src/views/audit/AuditInputTransfer.vue', code);
