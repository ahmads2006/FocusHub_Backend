FROM php:8.3-apache

# تثبيت الحزم وإضافات PHP الأساسية
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    supervisor \
    sqlite3 \
    libsqlite3-dev \
    fonts-dejavu-core \
    fonts-liberation \
    libmagickwand-dev \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql pdo_sqlite gd bcmath zip pcntl exif

# تفعيل mod_rewrite الخاص بخادم Apache
RUN a2enmod rewrite headers

# تغيير المجلد الافتراضي لـ Apache ليكون مجلد public الخاص بـ Laravel
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# تحميل Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# تحديد مسار العمل
WORKDIR /var/www/html

# نسخ جميع ملفات المشروع إلى الحاوية
COPY . .

# تثبيت حزم Laravel (بدون حزم التطوير)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# إعطاء الصلاحيات المناسبة لمجلدات التخزين
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# نسخ خطوط النظام إلى مجلد التطبيق لضمان عمل SecureShield Watermarking
RUN mkdir -p /var/www/html/storage/app/fonts && \
    cp /usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf /var/www/html/storage/app/fonts/ 2>/dev/null || true && \
    cp /usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf /var/www/html/storage/app/fonts/ 2>/dev/null || true

# تشغيل خادم Apache (منفذ 80) بشكل افتراضي
CMD ["apache2-foreground"]
