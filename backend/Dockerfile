FROM dunglas/frankenphp:php8.4-alpine

# Install ekstensi PHP yang dibutuhkan (terutama pgsql)
RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    pcntl \
    intl

WORKDIR /app

# Copy semua file dari Windows ke dalam kontainer
COPY . .

# Buat folder dan beri izin akses (Ini dijalankan saat build kontainer)
RUN mkdir -p storage bootstrap/cache
RUN chmod -R 777 storage bootstrap/cache