# PatriumHub

## Deploy (sin tocar Apache)

1. Copiá esta carpeta a `/var/www/html/patrium` (tiene que haber `index.php` ahí).
2. Importá `../databases/patriumhub.sql` en phpMyAdmin.
3. `.env`:

```env
APP_URL=http://192.168.100.50/patrium
DB_HOST=127.0.0.1
DB_NAME=patriumhub
DB_USER=root
DB_PASS=tu_clave_mysql
```

4. Abrí `http://192.168.100.50/patrium/`  
   Login: `admin@patriumhub.local` / `admin123`

Las pantallas usan URLs tipo `/patrium/index.php?r=/login` (no hace falta tocar Apache).

Prueba rápida: `/patrium/ok.php` → debe decir PatriumHub OK.
