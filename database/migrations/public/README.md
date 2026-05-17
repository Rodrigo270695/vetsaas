# Nota sobre esta carpeta

Las migraciones del **schema `public` (SaaS)** viven en **`database/migrations/`** con prefijo de fecha `2026_05_12_070xxx_*` para que `php artisan migrate` las ejecute (Laravel no incluye subcarpetas en el descubrimiento por defecto).

Aquí solo se deja documentación; el orden lógico sigue descrito en `vetsaas_db_estructura_migraciones.md`.
