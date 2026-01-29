# InfinityFree deployment checklist

Tento návod připravuje aplikaci pro provoz na InfinityFree s doménou
`https://4ro.infinityfreeapp.com`.

## 1) Připravte strukturu hostingu

InfinityFree používá Apache a kořen webu je typicky `htdocs`/`public_html`.
Nastavte, aby veřejně přístupný byl obsah složky `public`:

1. Nahrajte obsah `public/` do `htdocs/`.
2. Zbytek repozitáře nahrajte mimo `htdocs/` (např. do `app/`).
3. Upravte v `htdocs/index.php` cesty na `../bootstrap/app.php` tak, aby
   ukazovaly na skutečné umístění mimo `htdocs`.

> Tím zůstane mimo webroot soubor `.env` a interní PHP soubory.

## 2) Konfigurace `.env`

Vytvořte `.env` (mimo `htdocs`) podle vzoru
`deploy-prod-files/.env.infinityfree.example` a nastavte:

- `APP_URL=https://4ro.infinityfreeapp.com`
- `APP_ENV=production`
- `APP_DEBUG=false`
- `SESSION_DOMAIN=4ro.infinityfreeapp.com`

## 3) Práva pro zápis

Ujistěte se, že složky `storage/` a `bootstrap/cache/` jsou zapisovatelné.

## 4) Kontrola po nasazení

- Ověřte `https://4ro.infinityfreeapp.com/health`
- Ověřte statické soubory v `/static`

