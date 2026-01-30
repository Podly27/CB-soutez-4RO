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
4. **CTVERO_OWNER_MAIL** musí být nastavené v `.env` (adresa, kam se posílají zprávy z formuláře).

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

## 7) SMTP nastavení pro kontaktní formulář
- InfinityFree nemusí podporovat `mail()` ani SMTP bez externí služby.
- Doporučeno použít externí SMTP (např. Gmail, SendGrid, Mailgun).
- V `.env` nastav alespoň:
  ```
  MAIL_MAILER=smtp
  MAIL_HOST=smtp.gmail.com
  MAIL_PORT=587
  MAIL_USERNAME=uzivatel@example.com
  MAIL_PASSWORD=heslo_nebo_app_password
  MAIL_ENCRYPTION=tls
  MAIL_FROM_ADDRESS=uzivatel@example.com
  MAIL_FROM_NAME="Čtyři roční období"

  CTVERO_OWNER_MAIL=cilovy@example.com
  ```
- Diagnostika konfigurace (bez hesla): `https://<domena>/_debug/mail?token=DIAG_TOKEN`

## 8) OAuth callback URL a proměnné prostředí
### Callback URL (nastav v Google/Facebook/Twitter console)
- Google: `http://4ro.infinityfreeapp.com/auth/google/callback`
- Facebook: `http://4ro.infinityfreeapp.com/auth/facebook/callback`
- Twitter: `http://4ro.infinityfreeapp.com/auth/twitter/callback`

### Povinné proměnné v `/htdocs/.env`
- `GOOGLE_CLIENT_ID`
- `GOOGLE_CLIENT_SECRET`
- `GOOGLE_REDIRECT_URI`
- `FACEBOOK_APP_ID`
- `FACEBOOK_APP_SECRET`
- `FACEBOOK_REDIRECT_URI`
- `TWITTER_CLIENT_ID`
- `TWITTER_CLIENT_SECRET`
- `TWITTER_REDIRECT_URI`
