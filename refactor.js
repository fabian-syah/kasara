const fs = require('fs');
const path = require('path');

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

    // Replace PinModal with PasswordModal
    content = content.replace(/PinModal\.vue/g, 'PasswordModal.vue');
    content = content.replace(/PinModal/g, 'PasswordModal');
    
    // Replace `@verify-pin` with `@verify-password` ? 
    // Wait, let's keep `@verify-pin` as is on the template side, because PasswordModal still emits "verified". 
    // Actually PasswordModal still emits "verified" and "success". The `@verify-pin` on <PaymentStep> etc isn't related to the modal name, it's just an event.

    // Remove `pin_enabled` checks.
    // e.g. `user.pin_enabled` -> `true` (since we ALWAYS want it if user exists)
    // Wait, if it's `!user.pin_enabled`, it becomes `!true` -> `false`.
    // If it's `user?.pin_enabled`, it becomes `!!user`
    
    // It's safer to manually inspect where pin_enabled was used. Let's list files that had it.
    
    if (content !== original) {
      fs.writeFileSync(filePath, content, 'utf8');
      console.log('Updated: ' + filePath);
    }
  }
});
