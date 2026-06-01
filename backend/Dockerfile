FROM dunglas/frankenphp:php8.4-alpine

# Install ekstensi PHP yang dibutuhkan
RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    pcntl \
    intl \
    redis \
    opcache \
    zip \
    gd \
    bcmath \
    exif

# OPcache + JIT production settings (massive performance boost)
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.interned_strings_buffer=16" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.jit=1255" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.jit_buffer_size=128M" >> /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /app

# Copy semua file ke dalam kontainer
COPY . .

# Buat folder dan beri izin akses
RUN mkdir -p storage bootstrap/cache
RUN chmod -R 777 storage bootstrap/cache
