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
    libssl-dev \
    && pecl install redis mongodb imagick \
    && docker-php-ext-enable redis mongodb imagick \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql pdo_sqlite gd bcmath zip pcntl exif opcache

# نسخ إعدادات OpCache الخاصة بنا
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# إخفاء إصدار PHP و Apache لزيادة الأمان
RUN echo "expose_php = Off" > /usr/local/etc/php/conf.d/security.ini && \
    echo "ServerTokens Prod\nServerSignature Off" >> /etc/apache2/conf-available/security.conf && \
    a2enconf security

# تثبيت وكيل New Relic (APM)
RUN curl -L https://download.newrelic.com/php_agent/release/newrelic-php5-12.6.0.34-linux.tar.gz | tar -C /tmp -zx \
    && export NR_INSTALL_USE_CP_NOT_LN=1 \
    && export NR_INSTALL_SILENT=1 \
    && /tmp/newrelic-php5-*/newrelic-install install \
    && rm -rf /tmp/newrelic-php5-* /tmp/nrinstall*

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

# نسخ سكريبت التهيئة الذي يصلح الصلاحيات عند كل بدء تشغيل
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# تشغيل سكريبت التهيئة ثم خادم Apache (منفذ 80)
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
