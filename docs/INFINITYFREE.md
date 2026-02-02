# InfinityFree deploy & databáze (bez SSH)

Tento dokument shrnuje minimální kroky pro nasazení na InfinityFree tak, aby byl web znovu nasaditelný bez dohledávání detailů a aby v produkci nezůstaly debug díry.

## A) Požadavky hostingu
- **PHP 8.3**.
- Writable adresáře:
  - `storage/`
  - `storage/logs/`
  - `bootstrap/cache/`
- Produkční URL (HTTPS): **https://4ro.infinityfreeapp.com**

## B) Deploy pravidla
- **`vendor/` musí být nasazený** (InfinityFree nemá Composer/SSH).
- **`.env` se nedeployuje** – zůstává pouze na serveru.
- `bootstrap/cache` musí existovat (hlídá se přes `bootstrap/cache/.gitignore`).
- Po změnách Blade šablon smaž `storage/framework/views/*.php` (InfinityFree bez artisan).

## C) DB setup
### 1) Vytvoření DB v InfinityFree
1. Přihlas se do klientské zóny InfinityFree.
2. Otevři **MySQL Databases**.
3. Vytvoř databázi a uživatele (UI ti ukáže):
   - **DB Name** (např. `if0_XXXXXXX_dbname`)
   - **DB User** (např. `if0_XXXXXXX`)
   - **DB Host** (např. `sqlXXX.infinityfree.com`)
   - **DB Password** (heslo, které nastavíš).

### 2) `.env` proměnné DB_*
V `.env` na serveru nastav minimálně:
```
DB_CONNECTION=mysql
DB_HOST=sqlXXX.infinityfree.com
DB_PORT=3306
DB_DATABASE=if0_XXXXXXX_dbname
DB_USERNAME=if0_XXXXXXX
DB_PASSWORD=YOUR_PASSWORD
```

### 3) Jednorázové migrace přes /_setup/migrate
1. Do `.env` dočasně přidej silný token:
   ```
   SETUP_TOKEN=nahodne_dlouhe_heslo
   ```
2. Zavolej v prohlížeči:
   ```
   https://4ro.infinityfreeapp.com/_setup/migrate?token=nahodne_dlouhe_heslo
   ```
3. Po úspěchu se vytvoří marker `storage/app/setup_done` a endpoint vrátí **410 Gone** při dalším pokusu.
4. **Po dokončení smaž `SETUP_TOKEN` z `.env`.**

## D) Diagnostika
- **/health**
  - Vrací JSON pouze se základními informacemi o konfiguraci a stavu (bez citlivých hodnot).
  - Je bezpečný pro veřejné použití.
- **/diag?token=...**
  - Jen pro admina (DIAG_TOKEN z `.env`).
  - Obsahuje detailnější diagnostiku posledních chyb.
  - Doporučení: **DIAG_TOKEN držet mimo git**.
  - Logy ze `storage/logs` nikdy nevystavovat veřejně (blokovat přes `.htaccess`/webserver).

## E) Admin rozhraní
- Admin rozhraní je dostupné na **/admin** (odkaz se zobrazuje jen adminům).
- Admini jsou definovaní v `.env` pomocí e-mailu uživatele:
  - `ADMIN_EMAILS="mail1@x.cz,mail2@y.cz"` (CSV seznam, mezery se ignorují).
  - fallback `ADMIN_EMAIL="mail@x.cz"` pro jeden účet.
- Admin může upravovat:
  - Soutěže (název + termíny).
  - Deníky (základní údaje, contest/kategorie, možnost smazání).

## F) Časté chyby a řešení
- **500 kvůli chybějícímu `bootstrap/cache/`**
  - Vytvoř adresář a nastav práva (writable).
- **500 kvůli chybějícímu `vendor/`**
  - InfinityFree nemá Composer, nahraj `vendor/` z lokálního buildu.
- **500 kvůli špatné PHP verzi / composer.lock mismatch**
  - Nastav PHP 8.3 a přegeneruj `vendor/` pro odpovídající verzi.

## Co je potřeba po deployi nastavit v `.env`
- `APP_URL` na HTTPS doménu.
- `APP_KEY` (vygeneruj lokálně: `php artisan key:generate --show`).
- `DIAG_TOKEN` (pro admin diagnostiku).
- `SETUP_TOKEN` **jen dočasně** pro jednorázové migrace (pak smazat).
- `DB_*` proměnné (viz sekce C).
- OAuth proměnné (viz `docs/OAUTH.md`).
- Mail & kontakt (viz `docs/CONTACT.md`).
- reCAPTCHA (pokud je zapnutá, viz `docs/CONTACT.md`).
- `ADMIN_EMAILS` / `ADMIN_EMAIL` pro admin rozhraní (viz sekce E).

## CI / Workflows (co se ověřuje)
- CI běží na **PHP 8.3**.
- Smoke test pro Lumen:
  - `php artisan --version`
  - bootstrap + autoload kontrola (bez `route:list`).

## Poznámka k deployi
- `.env` se vytváří ručně na serveru dle `.env.example.infinityfree`.
- Secrets nikdy necommituj do gitu.
