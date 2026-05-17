# Migraciones del schema por clínica (tenant)

Laravel **no** ejecuta por defecto los `.php` de esta carpeta al correr `php artisan migrate`.

## Cómo aplicarlas (recomendado)

Comando Artisan (configura `tenant.migration_schema`, ejecuta `migrate --path` y lo limpia):

```bash
php artisan vetsaas:tenant-migrate vet_a1b2c3 --replay
php artisan vetsaas:tenant-migrate vet_a1b2c3 --wipe
```

- **`--replay`**: borra de `public.migrations` las filas de este paquete tenant y vuelve a ejecutar los `up` contra el schema indicado. Úsalo si el schema está **vacío** o alineado con el historial; si dentro del schema ya hay tablas creadas a mano o a medias, puede fallar con «relación ya existe».
- **`--wipe`**: hace `DROP SCHEMA … CASCADE`, recrea el schema vacío, limpia el historial tenant en `public.migrations` y migra. **Borra todos los datos** de ese schema; solo desarrollo / arreglo de schemas corruptos.

Alternativa manual:

```bash
set TENANT_MIGRATION_SCHEMA=vet_a1b2c3
php artisan migrate --path=database/migrations/tenant --no-interaction
```

En Linux/macOS: `export TENANT_MIGRATION_SCHEMA=vet_a1b2c3`

> **Producción / muchos tenants:** clonar un schema plantilla con `pg_dump` / `pg_restore` suele ser más fiable que depender del historial de `migrations` para N schemas; evalúa `stancl/tenancy` si quieres que Laravel gestione el ciclo completo.

Las migraciones extienden `App\Database\Migrations\TenantMigration` y hacen `SET search_path` a ese schema antes de crear tablas.

## Convención de nombres

Prefijo en el nombre del archivo: `t001`, `t010`, … para mantener el orden acordado en `vetsaas_db_estructura_migraciones.md`.
