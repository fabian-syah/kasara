const fs = require('fs');
const path = require('path');

const directoryPath = path.join(__dirname, 'src');

function findAndReplace(dir) {
    fs.readdirSync(dir).forEach(file => {
        const fullPath = path.join(dir, file);
        if (fs.statSync(fullPath).isDirectory()) {
            findAndReplace(fullPath);
        } else if (fullPath.endsWith('.vue')) {
            let content = fs.readFileSync(fullPath, 'utf8');
            let originalContent = content;

            // Replace commonly failing combinations in Safari
            content = content.replace(/bg-white dark:bg-surface-800/g, 'bg-white dark:!bg-surface-800');
            content = content.replace(/bg-white dark:bg-surface-900/g, 'bg-white dark:!bg-surface-900');
            content = content.replace(/bg-gray-50 dark:bg-surface-700/g, 'bg-gray-50 dark:!bg-surface-700');
            content = content.replace(/bg-gray-50 dark:bg-surface-800/g, 'bg-gray-50 dark:!bg-surface-800');
            content = content.replace(/bg-gray-50\/50 dark:bg-surface-700\/50/g, 'bg-gray-50/50 dark:!bg-surface-700/50');
            content = content.replace(/bg-gray-50\/50 dark:bg-surface-800\/50/g, 'bg-gray-50/50 dark:!bg-surface-800/50');
            content = content.replace(/bg-gray-100 dark:bg-surface-700/g, 'bg-gray-100 dark:!bg-surface-700');
            content = content.replace(/bg-white dark:bg-surface-700/g, 'bg-white dark:!bg-surface-700');

            if (content !== originalContent) {
                fs.writeFileSync(fullPath, content, 'utf8');
                console.log(`Updated: ${fullPath}`);
            }
        }
    });
}

findAndReplace(directoryPath);
console.log('Done fixing iOS dark mode backgrounds.');
