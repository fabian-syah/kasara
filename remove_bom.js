const fs = require('fs');
const files = [
    'd:/bian/apex-frontend/backend/app/Http/Controllers/Inventory/InventoryAccountController.php',
    'd:/bian/apex-frontend/backend/app/Http/Controllers/UserController.php',
    'd:/bian/apex-frontend/backend/routes/api.php',
    'd:/bian/apex-frontend/backend/app/Http/Controllers/AuthController.php',
    'd:/bian/apex-frontend/frontend/src/views/settings/SettingsInventory.vue',
    'd:/bian/apex-frontend/frontend/src/views/users/Users.vue',
    'd:/bian/apex-frontend/frontend/src/components/modals/CancelSaleModal.vue'
];

for (let file of files) {
    if (!fs.existsSync(file)) continue;
    let content = fs.readFileSync(file);
    if (content[0] === 0xEF && content[1] === 0xBB && content[2] === 0xBF) {
        content = content.slice(3);
        fs.writeFileSync(file, content);
        console.log('Removed BOM from ' + file);
    }
}
