const fs = require('fs');
const file = 'd:/bian/apex-frontend/frontend/src/views/inventory/Inventory.vue';
let content = fs.readFileSync(file, 'utf8');

// 0. Import component
content = content.replace(/import \{ ref, computed, watch, onMounted, onUnmounted \} from "vue";/,
    `import { ref, computed, watch, onMounted, onUnmounted } from "vue";
import StockOutModal from "../../components/inventory/StockOutModal.vue";`);

// 1. Remove modal state
content = content.replace(/const showStockOutModal = ref\(false\);[\s\S]*?const branches = ref\(\[\]\);/,
    `const showStockOutModal = ref(false);

function openStockOutModal() {
  if (selectedItems.value.length === 0) {
    import('../../composables/useToast').then(m => m.useToast().error('Pilih setidaknya satu item untuk dikeluarkan'));
    return;
  }
  showStockOutModal.value = true;
}

function handleStockOutSuccess() {
  selectedItems.value = [];
  showStockOutModal.value = false;
  loadInventory(1);
}

const branches = ref([]);`);

// 2. Remove Region Logic
content = content.replace(/\/\/ --- Region Logic ---[\s\S]*?function onVillageChange.*?\}[\s\n]*\}/, '');

// 3. Remove onUnmounted html5qrcode
content = content.replace(/onUnmounted\(\(\) => \{[\s\n]*document\.removeEventListener\('click', \(e\) => \{ \}\); \/\/ Cleanup[\s\n]*if \(html5QrCode\) \{[\s\n]*html5QrCode\.stop\(\)\.catch\(\(\) => \{ \}\);[\s\n]*\}[\s\n]*\}\);/,
    `onUnmounted(() => {\n  document.removeEventListener('click', (e) => { });\n});`);

// 4. Remove Categories and Users (lines 521 to 566)
content = content.replace(/\/\/ Stock Out Categories[\s\S]*?isLoadingUsers\.value = false;\n  \}\n\}/, '');

// 5. Remove Modal Functions (all the way down past submitStockOut up to Cleanup)
content = content.replace(/\/\/ Stock Out Modal Functions[\s\S]*?\/\/ Cleanup/, '// Cleanup');

// 6. Remove the modal HTML template
// Replace everything from <!-- Stock Out Modal --> up to just before </template>
content = content.replace(/<!-- Stock Out Modal -->[\s\S]*?(?=<\/template>)/,
    `<!-- Stock Out Modal Component -->
    <StockOutModal 
      :show="showStockOutModal" 
      :selectedItems="selectedItems"
      :activeTab="activeTab"
      @close="showStockOutModal = false"
      @success="handleStockOutSuccess"
    />
  </div>
`);

fs.writeFileSync(file, content);
console.log('Script updated successfully');
