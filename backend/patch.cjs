const fs = require('fs');
const path = require('path');

const files = [
  'app/Http/Controllers/AuditController.php',
  'app/Http/Controllers/ReportController.php'
];

files.forEach(file => {
  const fullPath = path.resolve(file);
  if (fs.existsSync(fullPath)) {
    let content = fs.readFileSync(fullPath, 'utf8');
    
    // Replace all occurrences of the problematic orWhereBetween
    content = content.replace(/->orWhereBetween\('created_at',\s*\[\$startTS,\s*\$endTS\]\);/g, "/* ->orWhereBetween('created_at', [$startTS, $endTS]); */");
    content = content.replace(/->orWhereBetween\('stock_outs\.created_at',\s*\[\$startTS,\s*\$endTS\]\);/g, "/* ->orWhereBetween('stock_outs.created_at', [$startTS, $endTS]); */");
    
    fs.writeFileSync(fullPath, content);
    console.log(`Patched ${file}`);
  }
});
