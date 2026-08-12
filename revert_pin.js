const fs = require('fs');

const files = [
    "frontend/src/views/settings/SettingsInventory.vue",
    "frontend/src/views/sales/categories/TukarTambahForm.vue",
    "frontend/src/views/sales/categories/TukarUnitForm.vue",
    "frontend/src/views/sales/categories/RefundForm.vue",
    "frontend/src/views/sales/categories/PaymentStep.vue",
    "frontend/src/views/sales/categories/DpForm.vue",
    "frontend/src/views/sales/categories/DowngradeForm.vue",
    "frontend/src/views/sales/categories/AngkatBarangForm.vue",
    "frontend/src/views/sales/CreateSale.vue",
    "frontend/src/views/inventory/StockOut.vue",
    "frontend/src/views/inventory/StockIn.vue",
    "frontend/src/views/inventory/ReturItems.vue",
    "frontend/src/views/inventory/InventoryMonitoringHub.vue",
    "frontend/src/views/inventory/Inventory.vue",
    "frontend/src/views/inventory/IncomingTransfers.vue",
    "frontend/src/views/inventory/FailedTransfers.vue",
    "frontend/src/views/audit/AuditInputTransfer.vue",
    "frontend/src/store/auth.js",
    "frontend/src/components/modals/CancelSaleModal.vue",
    "frontend/src/components/inventory/StockOutModal.vue",
    "frontend/src/views/users/Users.vue",
    "frontend/src/utils/permissions.js",
    "frontend/src/router/index.js",
    "frontend/src/components/layout/AppSidebar.vue",
    "frontend/src/api/axios.js"
];

const uniqueFiles = [...new Set(files)];

for (let file of uniqueFiles) {
    if (!fs.existsSync(file)) continue;
    let content = fs.readFileSync(file, 'utf8');
    let modified = content;

    // Component name and imports
    modified = modified.replace(/PasswordModal/g, 'PinModal');
    modified = modified.replace(/password-modal/g, 'pin-modal');
    
    // Data variables and methods
    modified = modified.replace(/showPasswordModal/g, 'showPinModal');
    modified = modified.replace(/isPasswordLoading/g, 'isPinLoading');
    modified = modified.replace(/passwordError/g, 'pinError');
    modified = modified.replace(/passwordModalTitle/g, 'pinModalTitle');
    modified = modified.replace(/passwordModalMode/g, 'pinModalMode');
    modified = modified.replace(/handlePasswordSuccess/g, 'handlePinSuccess');

    // Forms and API requests
    // Instead of replacing the word password blindly, look for the specific payload combinations
    modified = modified.replace(/if \(pin\) formData\.append\('password', pin\);/g, 
        "// if (pin) formData.append('password', pin);\n      if (pin) formData.append('transaction_pin', pin);");
    
    modified = modified.replace(/if \(password\) formData\.append\('password', password\);/g, 
        "// if (password) formData.append('password', password);\n      if (password) formData.append('transaction_pin', password);");

    modified = modified.replace(/if \(pin\) payload\.password = pin;/g, 
        "// if (pin) payload.password = pin;\n    if (pin) payload.transaction_pin = pin;");
        
    modified = modified.replace(/if \(password\) payload\.password = password;/g, 
        "// if (password) payload.password = password;\n    if (password) payload.transaction_pin = password;");

    // Replace verifyPassword endpoint with verifyPin in axios.js
    if (file.includes('axios.js')) {
        modified = modified.replace(/verifyPassword: \(password\) => api\.post\('\/verify-password', \{ password \}\),/g,
        "// verifyPassword: (password) => api.post('/verify-password', { password }),\n    verifyPin: (transaction_pin) => api.post('/verify-pin', { transaction_pin }),");
    }
    
    // Some components might emit 'password' or variable might be 'password' inside HandlePinSuccess
    modified = modified.replace(/function handlePinSuccess\(password\)/g, "function handlePinSuccess(pin)");
    modified = modified.replace(/const password = ref\(''\);/g, "const pin = ref('');");

    // In PinModal itself, it was replaced by PasswordModal. I already restored PinModal from git, so no need to touch PinModal.vue here, it's not in the list.

    if (content !== modified) {
        fs.writeFileSync(file, modified, 'utf8');
        console.log('Updated ' + file);
    }
}
