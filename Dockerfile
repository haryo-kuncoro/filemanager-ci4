
FROM php:8.5-apache

# Install library dan ekstensi PHP yang dibutuhkan CI4
RUN apt-get update && apt-get install -y --no-install-recommends \
    libicu-dev \
    libonig-dev \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
       intl mbstring mysqli pdo_mysql zip gd opcache \
    && rm -rf /var/lib/apt/lists/*

# Arahkan Apache ke folder public CodeIgniter
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf \
    /etc/apache2/conf-available/*.conf \
    && a2enmod rewrite headers \
    && printf '<Directory %s>\n AllowOverride All\n Require all granted\n</Directory>\n' \
       "$APACHE_DOCUMENT_ROOT" \
       > /etc/apache2/conf-available/public-root.conf \
    && a2enconf public-root

# Salin source aplikasi
COPY --chown=www-data:www-data . /var/www/html

# Siapkan writable dan .env agar konfigurasi dapat persisten
RUN mkdir -p /var/www/html/writable \
    && chown -R www-data:www-data /var/www/html/writable \
    && chmod -R 775 /var/www/html/writable \
    && ln -s writable/.env /var/www/html/.env

EXPOSE 80
