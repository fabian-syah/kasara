
const fs = require('fs');
const path = require('path');

const versionFilePath = path.join(__dirname, 'public', 'version.json');

const versionData = {
    buildTime: new Date().getTime(),
    version: process.env.npm_package_version || '1.0.0'
};

fs.writeFileSync(versionFilePath, JSON.stringify(versionData, null, 2));

console.log('✅ version.json generated:', versionData);
