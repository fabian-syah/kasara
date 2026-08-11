const fs = require('fs');
const path = require('path');

const files = [
    "frontend/src/components/inventory/StockOutModal.vue",
    "frontend/src/components/modals/CancelSaleModal.vue",
    "frontend/src/views/audit/AuditInputTransfer.vue",
    "frontend/src/views/inventory/IncomingTransfers.vue",
    "frontend/src/views/inventory/InventoryMonitoringHub.vue",
    "frontend/src/views/inventory/ReturItems.vue",
    "frontend/src/views/inventory/StockIn.vue",
    "frontend/src/views/inventory/StockOut.vue",
    "frontend/src/views/sales/categories/AngkatBarangForm.vue",
    "frontend/src/views/sales/categories/DowngradeForm.vue",
    "frontend/src/views/sales/categories/DpForm.vue",
    "frontend/src/views/sales/categories/PaymentStep.vue",
    "frontend/src/views/sales/categories\RefundForm.vue", // Note: Will fix the slash in node execution
    "frontend/src/views/sales/categories/TukarTambahForm.vue",
    "frontend/src/views/sales/categories/TukarUnitForm.vue"
];

// Some files might be missing because of path separators, so we'll just walk the directory
function walk(dir, callback) {
  fs.readdirSync(dir).forEach(f => {
    let dirPath = path.join(dir, f);
    let isDirectory = fs.statSync(dirPath).isDirectory();
    isDirectory ? walk(dirPath, callback) : callback(path.join(dir, f));
  });
}

walk('./frontend/src', function(filePath) {
  if (filePath.endsWith('.vue') || filePath.endsWith('.js')) {
    let content = fs.readFileSync(filePath, 'utf8');
    let original = content;

    // Replace specific known patterns
    // 1. `?.pin_enabled` -> `` (meaning `props.selectedAccountObject?.pin_enabled` becomes `props.selectedAccountObject`)
    content = content.replace(/\?\.pin_enabled/g, '');
    
    // 2. `.pin_enabled` -> `` (meaning `user.pin_enabled` becomes `user`)
    // Wait! `user.pin_enabled` -> `user` might lead to `Boolean(user)` which is always true.
    // That's exactly what we want: if there is a user, we require the password.
    
    // For CancelSaleModal: `pin_enabled: !!currentUser.pin_enabled` -> `pin_enabled: true`
    content = content.replace(/!!\w+\.pin_enabled/g, 'true');
    content = content.replace(/!!currentUser\.pin_enabled/g, 'true');
    
    // `Boolean(u.pin_enabled)` -> `true`
    content = content.replace(/Boolean\(\w+\.pin_enabled\)/g, 'true');

    // `user.pin_enabled || false` -> `true`
    content = content.replace(/\w+\.pin_enabled \|\| false/g, 'true');
    
    // Fallback: `.pin_enabled` -> `` (this will just drop the property access, evaluating the object itself)
    // Wait, let's just do `.pin_enabled` -> ``
    // e.g. `selectedAccount?.pin_enabled` -> `selectedAccount`
    // `authStore.user?.pin_enabled` -> `authStore.user`
    // `!user.pin_enabled` -> `!user` (if user exists, it's false. Wait! `!user.pin_enabled` meant "if pin NOT enabled, skip". If we want to ALWAYS require it, we want `false` instead of `!user.pin_enabled`. Wait, if we replace `.pin_enabled` with ``, then `!user` means "if NO user, skip". That's perfect!)

    content = content.replace(/\.pin_enabled/g, '');

    // For SettingsInventory and Inventory, we will do manual cleanup because they have UI elements to remove.

    if (content !== original) {
      fs.writeFileSync(filePath, content, 'utf8');
      console.log('Updated: ' + filePath);
    }
  }
});
