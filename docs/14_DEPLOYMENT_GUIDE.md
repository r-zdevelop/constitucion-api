# 14 - GUÍA DE DEPLOYMENT (UBUNTU 24.04 + APACHE + PHP 8.4)

**Proyecto:** LexEcuador - API REST para Constitución de Ecuador
**Propósito:** Guía completa de deployment en servidor Ubuntu 24.04 con Apache y PHP 8.4
**Audiencia:** Desarrollador PHP 3+ años con conocimiento de SOLID y Clean Architecture

---

## 📋 ÍNDICE

1. [Requisitos del Servidor](#requisitos-del-servidor)
2. [Instalación de PHP 8.4](#instalación-de-php-84)
3. [Configuración de MySQL](#configuración-de-mysql)
4. [Configuración de Apache](#configuración-de-apache)
5. [SSL/TLS con Let's Encrypt](#ssltls-con-lets-encrypt)
6. [Deployment del Proyecto](#deployment-del-proyecto)
7. [Automatización con GitHub Actions](#automatización-con-github-actions)
8. [Monitoreo y Logs](#monitoreo-y-logs)

---

## 🖥️ REQUISITOS DEL SERVIDOR

### Especificaciones Mínimas

```
- CPU: 2 cores
- RAM: 4GB
- Disco: 20GB SSD
- Sistema Operativo: Ubuntu 24.04 LTS
- Red: IPv4 pública
- Firewall: Permitir puertos 80, 443, 22
```

### Requisitos de Software

```
- PHP 8.4
- Apache 2.4
- MySQL 8.0 o PostgreSQL 16
- Composer 2.7+
- Git 2.x
- Node.js 20+ (para builds de frontend)
- Certbot (Let's Encrypt)
```

---

## 🐘 INSTALACIÓN DE PHP 8.4

### 1. Actualizar Sistema

```bash
# Conectar al servidor por SSH
ssh root@tu-servidor.com

# Actualizar paquetes
sudo apt update
sudo apt upgrade -y
```

---

### 2. Instalar PHP 8.4

```bash
# Añadir repositorio PPA de Ondrej
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Instalar PHP 8.4 y extensiones necesarias
sudo apt install -y \
    php8.4 \
    php8.4-cli \
    php8.4-fpm \
    php8.4-mysql \
    php8.4-pgsql \
    php8.4-xml \
    php8.4-mbstring \
    php8.4-curl \
    php8.4-zip \
    php8.4-intl \
    php8.4-bcmath \
    php8.4-gd \
    php8.4-opcache \
    php8.4-redis \
    libapache2-mod-php8.4

# Verificar instalación
php -v
# Debería mostrar: PHP 8.4.x
```

---

### 3. Configurar PHP para Producción

```bash
# Editar php.ini
sudo nano /etc/php/8.4/apache2/php.ini
```

Configuración recomendada:

```ini
; Configuración de PHP para LexEcuador API

; Límites de memoria y recursos
memory_limit = 256M
max_execution_time = 60
max_input_time = 60
upload_max_filesize = 10M
post_max_size = 10M

; Timezone
date.timezone = America/Guayaquil

; OPcache (mejorar performance)
opcache.enable = 1
opcache.enable_cli = 0
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 8
opcache.max_accelerated_files = 10000
opcache.revalidate_freq = 2
opcache.fast_shutdown = 1

; Seguridad
expose_php = Off
display_errors = Off
display_startup_errors = Off
log_errors = On
error_log = /var/log/php/error.log

; Sesiones (no usamos, pero por si acaso)
session.cookie_httponly = 1
session.cookie_secure = 1
session.cookie_samesite = Strict

; Realpath cache (mejor performance)
realpath_cache_size = 4096K
realpath_cache_ttl = 600
```

Crear directorio de logs:

```bash
sudo mkdir -p /var/log/php
sudo chown www-data:www-data /var/log/php
```

---

### 4. Instalar Composer

```bash
# Descargar Composer
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
php -r "unlink('composer-setup.php');"

# Mover a /usr/local/bin
sudo mv composer.phar /usr/local/bin/composer

# Verificar
composer --version
# Debería mostrar: Composer version 2.7.x
```

---

## 🗄️ CONFIGURACIÓN DE MYSQL

### 1. Instalar MySQL 8.0

```bash
sudo apt install -y mysql-server

# Iniciar servicio
sudo systemctl start mysql
sudo systemctl enable mysql

# Verificar
mysql --version
```

---

### 2. Configurar MySQL

```bash
# Securizar instalación
sudo mysql_secure_installation

# Responder:
# - Set root password? [Y/n] Y
# - Remove anonymous users? [Y/n] Y
# - Disallow root login remotely? [Y/n] Y
# - Remove test database? [Y/n] Y
# - Reload privilege tables? [Y/n] Y
```

---

### 3. Crear Base de Datos y Usuario

```bash
# Conectar a MySQL
sudo mysql -u root -p
```

```sql
-- Crear base de datos
CREATE DATABASE lexecuador CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Crear usuario
CREATE USER 'lexecuador_user'@'localhost' IDENTIFIED BY 'TU_PASSWORD_SEGURO_AQUI';

-- Otorgar permisos
GRANT ALL PRIVILEGES ON lexecuador.* TO 'lexecuador_user'@'localhost';

-- Aplicar cambios
FLUSH PRIVILEGES;

-- Salir
EXIT;
```

---

### 4. Optimizar MySQL para Producción

```bash
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf
```

Añadir al final:

```ini
[mysqld]
# Optimizaciones para LexEcuador

# InnoDB
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT

# Query cache (deshabilitado en MySQL 8.0+)
# query_cache_type = 0
# query_cache_size = 0

# Conexiones
max_connections = 200
max_connect_errors = 10

# Logs
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow-query.log
long_query_time = 2

# Charset
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci
```

Reiniciar MySQL:

```bash
sudo systemctl restart mysql
```

---

## 🌐 CONFIGURACIÓN DE APACHE

### 1. Instalar Apache

```bash
sudo apt install -y apache2

# Habilitar módulos necesarios
sudo a2enmod rewrite
sudo a2enmod ssl
sudo a2enmod headers
sudo a2enmod deflate
sudo a2enmod expires
sudo a2enmod proxy
sudo a2enmod proxy_http

# Reiniciar Apache
sudo systemctl restart apache2
sudo systemctl enable apache2
```

---

### 2. Crear VirtualHost

```bash
sudo nano /etc/apache2/sites-available/lexecuador-api.conf
```

Contenido:

```apache
<VirtualHost *:80>
    ServerName api.lexecuador.com
    ServerAlias www.api.lexecuador.com

    DocumentRoot /var/www/lexecuador-api/public

    <Directory /var/www/lexecuador-api/public>
        AllowOverride All
        Require all granted

        # Rewrite rules para Symfony
        FallbackResource /index.php
    </Directory>

    # Logs
    ErrorLog ${APACHE_LOG_DIR}/lexecuador-error.log
    CustomLog ${APACHE_LOG_DIR}/lexecuador-access.log combined

    # Headers de seguridad
    Header always set X-Frame-Options "DENY"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"

    # Comprimir respuestas
    <IfModule mod_deflate.c>
        AddOutputFilterByType DEFLATE application/json
        AddOutputFilterByType DEFLATE text/html
        AddOutputFilterByType DEFLATE text/plain
        AddOutputFilterByType DEFLATE text/xml
        AddOutputFilterByType DEFLATE text/css
        AddOutputFilterByType DEFLATE application/javascript
    </IfModule>

    # Cache de assets estáticos
    <IfModule mod_expires.c>
        ExpiresActive On
        ExpiresByType application/json "access plus 1 hour"
        ExpiresByType image/png "access plus 1 year"
        ExpiresByType image/jpeg "access plus 1 year"
        ExpiresByType text/css "access plus 1 month"
        ExpiresByType application/javascript "access plus 1 month"
    </IfModule>
</VirtualHost>
```

---

### 3. Habilitar Sitio

```bash
# Deshabilitar sitio por defecto
sudo a2dissite 000-default.conf

# Habilitar nuestro sitio
sudo a2ensite lexecuador-api.conf

# Verificar configuración
sudo apache2ctl configtest

# Debería mostrar: Syntax OK

# Reiniciar Apache
sudo systemctl restart apache2
```

---

## 🔒 SSL/TLS CON LET'S ENCRYPT

### 1. Instalar Certbot

```bash
sudo apt install -y certbot python3-certbot-apache
```

---

### 2. Obtener Certificado SSL

```bash
# Obtener certificado automáticamente
sudo certbot --apache -d api.lexecuador.com -d www.api.lexecuador.com

# Responder:
# - Email: tu@email.com
# - Agree to terms: Yes
# - Redirect HTTP to HTTPS: Yes (opción 2)
```

Certbot automáticamente:
1. Obtiene el certificado
2. Modifica el VirtualHost
3. Configura redirección HTTP → HTTPS
4. Crea un cron job para renovación automática

---

### 3. Verificar Renovación Automática

```bash
# Test de renovación (dry-run)
sudo certbot renew --dry-run

# Ver cuándo expira el certificado
sudo certbot certificates
```

El certificado se renovará automáticamente cada 60 días.

---

### 4. VirtualHost con SSL (después de Certbot)

```bash
cat /etc/apache2/sites-available/lexecuador-api-le-ssl.conf
```

Debería verse algo así:

```apache
<VirtualHost *:443>
    ServerName api.lexecuador.com

    DocumentRoot /var/www/lexecuador-api/public

    <Directory /var/www/lexecuador-api/public>
        AllowOverride All
        Require all granted
        FallbackResource /index.php
    </Directory>

    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/api.lexecuador.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/api.lexecuador.com/privkey.pem
    Include /etc/letsencrypt/options-ssl-apache.conf

    # HSTS (Strict Transport Security)
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"

    # Logs
    ErrorLog ${APACHE_LOG_DIR}/lexecuador-ssl-error.log
    CustomLog ${APACHE_LOG_DIR}/lexecuador-ssl-access.log combined
</VirtualHost>
```

---

## 🚀 DEPLOYMENT DEL PROYECTO

### 1. Crear Directorio del Proyecto

```bash
sudo mkdir -p /var/www/lexecuador-api
sudo chown -R www-data:www-data /var/www/lexecuador-api
sudo chmod -R 755 /var/www/lexecuador-api
```

---

### 2. Clonar Repositorio

```bash
# Cambiar a usuario www-data
sudo -u www-data bash

# Ir al directorio
cd /var/www

# Clonar repositorio (opción 1: HTTPS)
git clone https://github.com/tu-usuario/lexecuador-api.git

# O clonar con SSH (opción 2: requiere configurar deploy key)
git clone git@github.com:tu-usuario/lexecuador-api.git

# Salir de www-data
exit
```

---

### 3. Configurar Variables de Entorno

```bash
# Copiar .env de ejemplo
cd /var/www/lexecuador-api
sudo -u www-data cp .env .env.local

# Editar .env.local
sudo -u www-data nano .env.local
```

Configuración de producción:

```bash
# .env.local (PRODUCCIÓN)

###> symfony/framework-bundle ###
APP_ENV=prod
APP_SECRET=GENERAR_SECRET_AQUI_32_CARACTERES_ALEATORIOS
###< symfony/framework-bundle ###

###> doctrine/doctrine-bundle ###
DATABASE_URL="mysql://lexecuador_user:TU_PASSWORD@127.0.0.1:3306/lexecuador?serverVersion=8.0&charset=utf8mb4"
###< doctrine/doctrine-bundle ###

###> lexik/jwt-authentication-bundle ###
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=TU_PASSPHRASE_SEGURO
###< lexik/jwt-authentication-bundle ###

###> nelmio/cors-bundle ###
CORS_ALLOW_ORIGIN='^https://app\.lexecuador\.com$'
###< nelmio/cors-bundle ###

###> stripe ###
STRIPE_PUBLIC_KEY=pk_live_...
STRIPE_SECRET_KEY=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...
STRIPE_PRICE_PREMIUM=price_...
STRIPE_PRICE_ENTERPRISE=price_...
###< stripe ###

###> mailer ###
MAILER_DSN=smtp://user:pass@smtp.mailtrap.io:2525
###< mailer ###
```

---

### 4. Instalar Dependencias

```bash
cd /var/www/lexecuador-api

# Instalar dependencias de PHP
sudo -u www-data composer install --no-dev --optimize-autoloader

# Generar keypair JWT
sudo -u www-data php bin/console lexik:jwt:generate-keypair
```

---

### 5. Ejecutar Migraciones

```bash
# Crear base de datos (si no existe)
sudo -u www-data php bin/console doctrine:database:create --env=prod

# Ejecutar migraciones
sudo -u www-data php bin/console doctrine:migrations:migrate --no-interaction --env=prod

# Cargar fixtures (solo si es necesario)
# sudo -u www-data php bin/console doctrine:fixtures:load --no-interaction --env=prod
```

---

### 6. Limpiar Caché y Optimizar

```bash
# Limpiar caché
sudo -u www-data php bin/console cache:clear --env=prod

# Warmup cache
sudo -u www-data php bin/console cache:warmup --env=prod

# Setear permisos correctos
sudo chown -R www-data:www-data /var/www/lexecuador-api
sudo chmod -R 755 /var/www/lexecuador-api
sudo chmod -R 775 /var/www/lexecuador-api/var
```

---

### 7. Verificar Deployment

```bash
# Verificar que Apache puede leer el proyecto
sudo -u www-data ls -la /var/www/lexecuador-api/public

# Verificar permisos de var/
ls -la /var/www/lexecuador-api/var

# Test de conectividad
curl -I https://api.lexecuador.com/api/v1/health

# Debería retornar: HTTP/2 200
```

---

## 🤖 AUTOMATIZACIÓN CON GITHUB ACTIONS

### 1. Configurar Deploy Key en Servidor

```bash
# En el servidor, generar SSH key
sudo -u www-data ssh-keygen -t ed25519 -C "deploy@lexecuador.com"

# Mostrar clave pública
sudo -u www-data cat /var/www/.ssh/id_ed25519.pub

# Copiar la clave pública y añadirla a GitHub:
# GitHub → Repo → Settings → Deploy keys → Add deploy key
```

---

### 2. Script de Deployment

```bash
# Crear script de deploy
sudo nano /var/www/lexecuador-api/deploy.sh
```

Contenido:

```bash
#!/bin/bash
set -e

echo "🚀 Starting deployment..."

# Variables
PROJECT_DIR="/var/www/lexecuador-api"
BRANCH="main"

# Ir al directorio del proyecto
cd $PROJECT_DIR

echo "📦 Pulling latest changes..."
sudo -u www-data git fetch origin
sudo -u www-data git reset --hard origin/$BRANCH

echo "📚 Installing dependencies..."
sudo -u www-data composer install --no-dev --optimize-autoloader --no-interaction

echo "🗄️ Running migrations..."
sudo -u www-data php bin/console doctrine:migrations:migrate --no-interaction --env=prod

echo "🧹 Clearing cache..."
sudo -u www-data php bin/console cache:clear --env=prod
sudo -u www-data php bin/console cache:warmup --env=prod

echo "🔧 Setting permissions..."
sudo chown -R www-data:www-data $PROJECT_DIR
sudo chmod -R 755 $PROJECT_DIR
sudo chmod -R 775 $PROJECT_DIR/var

echo "🔄 Restarting services..."
sudo systemctl reload apache2

echo "✅ Deployment completed successfully!"
```

Dar permisos de ejecución:

```bash
sudo chmod +x /var/www/lexecuador-api/deploy.sh
```

---

### 3. GitHub Actions Workflow

```yaml
# .github/workflows/deploy.yml
name: Deploy to Production

on:
  push:
    branches: [ main ]

jobs:
  deploy:
    runs-on: ubuntu-latest

    steps:
      - name: Deploy to Server
        uses: appleboy/ssh-action@master
        with:
          host: ${{ secrets.SERVER_HOST }}
          username: ${{ secrets.SERVER_USER }}
          key: ${{ secrets.SERVER_SSH_KEY }}
          script: |
            cd /var/www/lexecuador-api
            sudo ./deploy.sh
```

Configurar secrets en GitHub:
- `SERVER_HOST`: IP o dominio del servidor
- `SERVER_USER`: Usuario SSH (ej: ubuntu)
- `SERVER_SSH_KEY`: Private key SSH

---

## 📊 MONITOREO Y LOGS

### 1. Configurar Logs de Symfony

```yaml
# config/packages/prod/monolog.yaml
monolog:
    handlers:
        main:
            type: rotating_file
            path: '%kernel.logs_dir%/%kernel.environment%.log'
            level: error
            max_files: 30

        api_requests:
            type: rotating_file
            path: '%kernel.logs_dir%/api_requests.log'
            level: info
            max_files: 30
            channels: ['api']

        security:
            type: rotating_file
            path: '%kernel.logs_dir%/security.log'
            level: info
            max_files: 30
            channels: ['security']

        payment:
            type: rotating_file
            path: '%kernel.logs_dir%/payment.log'
            level: info
            max_files: 30
            channels: ['payment']
```

---

### 2. Rotación de Logs

```bash
# Crear configuración de logrotate
sudo nano /etc/logrotate.d/lexecuador
```

Contenido:

```
/var/www/lexecuador-api/var/log/*.log {
    daily
    rotate 30
    compress
    delaycompress
    notifempty
    missingok
    create 0640 www-data www-data
    sharedscripts
    postrotate
        systemctl reload apache2 > /dev/null 2>&1 || true
    endscript
}
```

---

### 3. Monitoreo con New Relic (Opcional)

```bash
# Instalar New Relic PHP Agent
curl -Ls https://download.newrelic.com/php_agent/release/newrelic-php5-10.11.0.3-linux.tar.gz | tar -C /tmp -zx
cd /tmp/newrelic-php5-*
sudo NR_INSTALL_SILENT=1 ./newrelic-install install

# Configurar
sudo nano /etc/php/8.4/apache2/conf.d/newrelic.ini
```

```ini
newrelic.license = "TU_LICENSE_KEY"
newrelic.appname = "LexEcuador API"
newrelic.daemon.logfile = "/var/log/newrelic/newrelic-daemon.log"
newrelic.logfile = "/var/log/newrelic/php_agent.log"
```

```bash
# Reiniciar Apache
sudo systemctl restart apache2
```

---

### 4. Health Check Endpoint

```php
<?php
// src/Infrastructure/Presentation/Controller/HealthController.php

namespace App\Infrastructure\Presentation\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\DBAL\Connection;

class HealthController extends AbstractController
{
    #[Route('/api/v1/health', methods: ['GET'])]
    public function health(Connection $connection): JsonResponse
    {
        try {
            // Verificar conexión a DB
            $connection->executeQuery('SELECT 1');
            $dbStatus = 'healthy';
        } catch (\Exception $e) {
            $dbStatus = 'unhealthy';
        }

        return $this->json([
            'status' => $dbStatus === 'healthy' ? 'healthy' : 'unhealthy',
            'timestamp' => time(),
            'services' => [
                'database' => $dbStatus,
                'api' => 'healthy',
            ],
        ], $dbStatus === 'healthy' ? 200 : 503);
    }
}
```

Monitorear con cron:

```bash
# Añadir a crontab
crontab -e
```

```
# Health check cada 5 minutos
*/5 * * * * curl -f https://api.lexecuador.com/api/v1/health || echo "API down" | mail -s "Alert: API Down" admin@lexecuador.com
```

---

## 🔧 TROUBLESHOOTING

### Problema: Error 500 después de deployment

```bash
# Verificar permisos
ls -la /var/www/lexecuador-api/var

# Debería ser:
# drwxrwxr-x www-data www-data

# Si no, corregir:
sudo chown -R www-data:www-data /var/www/lexecuador-api/var
sudo chmod -R 775 /var/www/lexecuador-api/var

# Ver logs de Apache
sudo tail -f /var/log/apache2/lexecuador-error.log

# Ver logs de Symfony
sudo tail -f /var/www/lexecuador-api/var/log/prod.log
```

---

### Problema: JWT no funciona

```bash
# Verificar que las claves existen
ls -la /var/www/lexecuador-api/config/jwt/

# Debería mostrar:
# private.pem
# public.pem

# Verificar permisos
sudo chmod 644 /var/www/lexecuador-api/config/jwt/*.pem

# Regenerar keypair si es necesario
sudo -u www-data php bin/console lexik:jwt:generate-keypair --overwrite
```

---

### Problema: Base de datos no conecta

```bash
# Verificar que MySQL está corriendo
sudo systemctl status mysql

# Verificar credenciales en .env.local
cat /var/www/lexecuador-api/.env.local | grep DATABASE_URL

# Probar conexión manualmente
mysql -u lexecuador_user -p -h 127.0.0.1 lexecuador

# Verificar desde PHP
sudo -u www-data php bin/console dbal:run-sql "SELECT 1"
```

---

## ✅ CHECKLIST DE DEPLOYMENT

### Servidor

- [ ] Ubuntu 24.04 actualizado
- [ ] PHP 8.4 instalado con extensiones
- [ ] Apache configurado con módulos
- [ ] MySQL 8.0 instalado y configurado
- [ ] Composer instalado
- [ ] Firewall configurado (UFW)

### SSL/HTTPS

- [ ] Certbot instalado
- [ ] Certificado SSL obtenido
- [ ] Redirección HTTP → HTTPS configurada
- [ ] HSTS header configurado

### Proyecto

- [ ] Repositorio clonado
- [ ] Dependencias instaladas (`composer install`)
- [ ] `.env.local` configurado
- [ ] JWT keypair generado
- [ ] Migraciones ejecutadas
- [ ] Caché cleared y warmed up
- [ ] Permisos correctos

### Automatización

- [ ] Deploy script creado
- [ ] GitHub Actions configurado
- [ ] Deploy key añadida a GitHub

### Monitoreo

- [ ] Logs configurados
- [ ] Logrotate configurado
- [ ] Health check endpoint creado
- [ ] New Relic instalado (opcional)
- [ ] Alertas configuradas

---

**Archivo generado:** `14_DEPLOYMENT_GUIDE.md`
**Siguiente y último:** `15_CHECKLIST_FINAL.md` (Checklist Master del Proyecto)
