#!/bin/bash

# Kasih tau user kalau proses mulai
echo "🚀 Memulai proses update APEX POS..."

# 1. Masuk ke folder project
cd ~/apex-pos/apex-frontend || exit

# 2. Tarik kode terbaru dari GitHub
echo "📥 Menarik kode terbaru dari GitHub..."
git pull origin main

# 3. Backend: Jalankan migrasi database & bersihkan cache (Via Docker)
echo "🛠️ Menjalankan migrasi database..."
docker exec apex-api-local php artisan migrate --force
docker exec apex-api-local php artisan optimize:clear
docker exec apex-api-local php artisan cache:clear
docker exec apex-api-local php artisan octane:reload

# 4. Frontend: Rakit (Build) project
echo "🎨 Sedang merakit (Build) project Vue..."
cd frontend
npm run build

# 5. Jika build sukses, baru pindahkan file
if [ $? -eq 0 ]; then
    echo "✅ Build sukses! Memindahkan ke folder server..."
    sudo rm -rf /var/www/stokps/*
    sudo cp -r dist/* /var/www/stokps/
    echo "🎉 Update SELESAI! Web sudah live dengan versi terbaru."
else
    echo "❌ Build GAGAL. Silakan cek error di atas."
    exit 1
fi
