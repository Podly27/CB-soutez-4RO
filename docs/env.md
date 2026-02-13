# ENV konfigurace

Tento dokument shrnuje důležité proměnné prostředí. U každé položky je uvedeno, k čemu slouží a co se stane, když chybí.

## Povinné proměnné

- `APP_ENV` – určuje prostředí (`local`, `production`, `testing`).
  - Pokud chybí, Lumen použije výchozí hodnotu `production`, což může ovlivnit logování a debug.
- `APP_URL` – veřejná URL aplikace.
  - Pokud chybí, aplikace používá `http://localhost`, což rozbije odkazy v e-mailech a callbacky.
- `APP_KEY` – klíč pro šifrování a session.
  - Pokud chybí, některé funkce (např. šifrování) selžou a aplikace není bezpečná.
- `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` – připojení k DB.
  - Pokud chybí, aplikace se nepřipojí k databázi a skončí na chybách při práci s daty.

## OAuth proměnné

### Facebook
- `FACEBOOK_APP_ID` / `FACEBOOK_CLIENT_ID` – OAuth client ID.
- `FACEBOOK_APP_SECRET` / `FACEBOOK_CLIENT_SECRET` – OAuth client secret.
- `FACEBOOK_REDIRECT_URI` – callback URL.
  - Pokud chybí nebo nesouhlasí s konfigurací Facebook app, login nebude fungovat.

### Google
- `GOOGLE_CLIENT_ID` – OAuth client ID.
- `GOOGLE_CLIENT_SECRET` – OAuth client secret.
- `GOOGLE_REDIRECT_URI` – callback URL.
  - Pokud chybí nebo nesouhlasí, login nebude fungovat.

### X (Twitter)
- `TWITTER_CLIENT_ID` – OAuth client ID.
- `TWITTER_CLIENT_SECRET` – OAuth client secret.
- `TWITTER_REDIRECT_URI` – callback URL.
  - Pokud chybí nebo nesouhlasí, login nebude fungovat.

## MAIL konfigurace

- `MAIL_MAILER` – typ maileru (`smtp`, `log`, ...).
- `MAIL_HOST`, `MAIL_PORT`, `MAIL_ENCRYPTION`, `MAIL_USERNAME`, `MAIL_PASSWORD` – SMTP parametry.
- `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` – odesílatel.
  - Pokud chybí, odesílání e-mailů může selhat nebo posílat z neplatné adresy.

## ADMIN / OWNER kontakty

- `CTVERO_OWNER_MAIL` / `CTVERO_OWNER_EMAIL` / `OWNER_MAIL` – hlavní kontakt pro zprávy z webu.
  - Pokud chybí, zprávy nemají kam dorazit a formulář může vracet chybu.
- `ADMIN_EMAILS` (nebo `ADMIN_EMAIL`) – seznam admin e-mailů.
  - Pokud chybí, admin notifikace a správa mohou být omezené.

## DIAG / DEBUG tokeny

- `DIAG_TOKEN` – token pro debug endpointy (`/_debug/*`, `/diag`).
  - Pokud chybí, debug endpointy v produkci nefungují (vrací 404/forbidden).
- `APP_DEBUG` – zapíná/vypíná debug režim frameworku.
  - Pokud chybí, používá se `false` a chyby se nebudou detailně zobrazovat.

## Další provozní klíče

- `CTVERO_API_ADMIN_SECRET` – tajný klíč pro admin API (migrace, provozní akce).
  - Pokud chybí, admin API akce neproběhnou.
- `CTVERO_RECAPTCHA_SITE_KEY`, `CTVERO_RECAPTCHA_ENTERPRISE_PROJECT_ID`, `CTVERO_RECAPTCHA_ENTERPRISE_API_KEY`, `CTVERO_RECAPTCHA_EXPECTED_ACTION`, `CTVERO_RECAPTCHA_SCORE_THRESHOLD` – reCAPTCHA Enterprise pro ochranu formulářů.
  - `CTVERO_RECAPTCHA_SECRET` zůstává jako fallback pro původní ověřování.
  - Pokud chybí enterprise i fallback klíče, ochrana proti spamu nebude fungovat.
- `CTVERO_MAPBOX_ACCESS_TOKEN` – Mapbox token pro mapy.
  - Pokud chybí, mapy se nenačtou.
- `CTVERO_CBPMR_INFO_API_URL`, `CTVERO_CBPMR_INFO_API_AUTH_USERNAME`, `CTVERO_CBPMR_INFO_API_AUTH_PASSWORD` – CBPMR API integrace (pokud je využita).
  - Pokud chybí, API integrace nebude dostupná.
- `CTVERO_REPOSITORY_URL`, `CTVERO_ISSUES_REPORT_URL` – odkazy z UI na repo/issues.
  - Pokud chybí, odkazy v UI nebudou zobrazeny.
