FROM php:7.4-apache

# 安装系统依赖项，包括libzip-dev、zip、GD相关的库
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install gd zip pdo_mysql

# 将源码复制到容器的/var/www/html/目录
COPY . /var/www/html/

# 更改/var/www/html/的所有权给www-data用户
RUN chown -R www-data:www-data /var/www/html

# 创建runtime目录并更改其所有权
RUN mkdir -p /var/www/html/runtime && chown -R www-data:www-data /var/www/html/runtime

# 安装Composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# 运行Composer安装依赖
RUN composer install --no-dev

# 将public目录设置为Apache的根目录
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 启用Apache的mod_rewrite模块，支持伪静态
RUN a2enmod rewrite