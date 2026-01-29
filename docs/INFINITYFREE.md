# InfinityFree deploy & databáze (bez SSH)

## Požadavky
- Hosting musí podporovat **PHP 8.3** (projekt je cílený na PHP 8.3).

## 1) Vytvoření databáze v InfinityFree
1. Přihlas se do klientské zóny InfinityFree.
2. Otevři **MySQL Databases**.
3. Vytvoř databázi a uživatele (UI ti ukáže):
   - **DB Name** (např. `if0_XXXXXXX_dbname`)
   - **DB User** (např. `if0_XXXXXXX`)
   - **DB Host** (např. `sqlXXX.infinityfree.com`)
   - **DB Password** (heslo, které nastavíš).

## 2) .env na serveru
1. V kořeni `/htdocs` (kde je `app/`, `bootstrap/`, `public/` atd.) vytvoř soubor `.env`.
2. Použij vzor z `.env.example.infinityfree` a vyplň hodnoty z InfinityFree.
3. **APP_KEY** musíš vygenerovat lokálně:
   ```bash
   php artisan key:generate --show
   ```
   Zkopíruj výstup do `.env` jako `APP_KEY=...`.

## 3) Oprávnění storage/ a bootstrap/cache/
- Laravel/Lumen bez writable `storage/` a `bootstrap/cache/` často končí 500.
- V InfinityFree File Manageru nastav práva (CHMOD) alespoň na `755` nebo `775` dle možností hostingu:
  - `storage/`
  - `bootstrap/cache/`

## 4) Inicializace DB bez SSH (jednorázově)
1. Do `.env` dočasně přidej silný token:
   ```
   SETUP_TOKEN=nahodne_dlouhe_heslo
   ```
2. Zavolej v prohlížeči:
   ```
   https://<domena>/_setup/migrate?token=nahodne_dlouhe_heslo
   ```
3. Po úspěchu se vytvoří marker `storage/app/setup_done` a endpoint vrátí **410 Gone** při dalším pokusu.
4. **Doporučeno:** Po dokončení smaž `SETUP_TOKEN` z `.env`.

## 5) Diagnostika bez logů
- Základní health-check (bez DB):
  - `https://<domena>/health`
- Dočasná diagnostika (token v `.env`):
  - nastav `DIAG_TOKEN=...`
  - otevři `https://<domena>/diag?token=...`
  - endpoint kontroluje existenci `storage/framework`, writable `storage/` a `bootstrap/cache`, a zda je nastaven `APP_KEY`.

## 6) Poznámka k deployi
- InfinityFree obvykle nemá Composer ani SSH, proto **je nutné nahrát i složku `vendor/`**.
- `.env` se nedeployuje, vytvoř ho ručně na serveru dle kroku 2.
- V CI je vhodné spouštět:
  ```bash
  composer install --no-dev --prefer-dist --optimize-autoloader
  ```
