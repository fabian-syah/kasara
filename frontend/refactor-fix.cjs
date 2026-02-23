const fs = require('fs');
const file = 'd:/bian/apex-frontend/frontend/src/views/inventory/Inventory.vue';
let content = fs.readFileSync(file, 'utf8');

const lines = content.split('\n');
// The first </template> is right after the StockOutModal import
const firstIdx = lines.findIndex((l, i) => i > 1000 && l.trim() === '</template>');
// The actual end of the template is right before <style scoped>
const lastIdx = lines.findLastIndex(l => l.trim() === '</template>');

if (firstIdx !== -1 && lastIdx !== -1 && lastIdx > firstIdx) {
    // We remove lines strictly between the first </template> and the last </template>
    // Wait, the first one is actually the one we want to keep, and the last one is the one we want to keep?
    // Actually, the first </template> at 1075 is what the DOM compiler complained about because it closed the main template early while there's still content below it!
    // No, the first `</template>` at 1075 closed the MAIN `<template>`, and then the compiler found more `<template>` tags (e.g. at 1076) OUTSIDE the main template. That's why it said "Invalid end tag."
    // So the CORRECT fix is: delete the `</template>` at 1075, delete all the dangling modal stuff, and KEEP the very last `</template>` at 1621.

    lines.splice(firstIdx, lastIdx - firstIdx);
    fs.writeFileSync(file, lines.join('\n'));
    console.log('Fixed dangling templates');
} else {
    console.log('Could not find indices', firstIdx, lastIdx);
}
