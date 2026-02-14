import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

// Pengganti __dirname di ES Module
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const versionFilePath = path.join(__dirname, 'public', 'version.json');

const versionData = {
    buildTime: new Date().getTime(),
    version: process.env.npm_package_version || '1.0.0'
};

fs.writeFileSync(versionFilePath, JSON.stringify(versionData, null, 2));

console.log('✅ version.json generated:', versionData);
