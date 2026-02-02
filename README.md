# Čtvero ročních období (CB soutěž) / The Four Seasons (CB Contest)

## This Application

[![Test](https://github.com/fivaldi/ctvero-rocnich-obdobi/actions/workflows/test.yml/badge.svg)](https://github.com/fivaldi/ctvero-rocnich-obdobi/actions/workflows/test.yml)
[![Prod](https://github.com/fivaldi/ctvero-rocnich-obdobi/actions/workflows/prod.yml/badge.svg)](https://github.com/fivaldi/ctvero-rocnich-obdobi/actions/workflows/prod.yml)

### Development and Testing

Providing you have `docker` and `docker-compose` installed, clone the repository and run:

```
UID_GID="$(id -u):$(id -g)" docker-compose up db lumen
```

Then check your browser at <http://localhost:8000>.

In a separate console tab/window, you may attach to the running containers and perform various actions:

```
docker exec -it ctvero-lumen sh  # for the app server, then run e.g.: composer, artisan...
docker exec -it ctvero-db sh  # for the db server
```

Local tests can be run as follows:

```
docker exec ctvero-lumen vendor/bin/phpunit -v
```

## Architektura aplikace (stručně)

- **Frontend:** formuláře, login a validační UI v browseru.  
- **Backend:** Lumen aplikace (routing + controllers + služby).  
- **Integrace:**  
  - OAuth (Facebook, Google, X).  
  - CBPMR import (sdílené deníky přes veřejné share linky).  

## Přehled hlavních funkcí

- Přihlášení uživatelů (OAuth).  
- Odesílání deníků.  
- Import deníku z CBPMR přes veřejný share link.  
- Admin rozhraní (správa závodů, deníků).  

## CBPMR Import – jak to funguje

**1) Uživatel vloží URL**  
- `/share/<token>`  
- nebo `/share/portable/<id>`  

**2) Backend**  
- `CbpmrShareService::fetch()`  
  - následuje redirect  
  - ukládá `final_url`, `http_code`, `body`  
- `CbpmrShareService::parsePortable()`  
  - parsuje HTML  
  - získá:  
    - portable_id  
    - locator, place  
    - počet QSO  
    - seznam spojení (time, name, locator, km, note)  

**3) SubmissionController**  
- validuje vstup  
- mapuje parsed data do interní DB struktury  
- uloží deník + QSO  

```
CBPMR share URL
     |
     v
CbpmrShareService::fetch()
     |
     v
CbpmrShareService::parsePortable()
     |
     v
SubmissionController (validate + map + store)
```

## Debug & Diagnostics

Podrobnosti viz [docs/debug.md](docs/debug.md).

### DIAG_TOKEN (povinný pro debug)
- Debug endpointy **vždy vyžadují token** v query parametru (`DIAG_TOKEN` v `.env`).  
- Příklady:
  - `/diag?token=<DIAG_TOKEN>`
  - `/_debug/cbpmr-parse?token=<DIAG_TOKEN>&url=<encoded>`

### Debug endpointy
- `/_debug/ping-json?token=...`  
  → ověření, že routing + JSON odpověď fungují  
  - Úspěch: `{"ok": true, "ts": "...ISO8601..."}`  
- `/_debug/trace?token=...`  
  → ověření, že controller + middleware jsou funkční  
  - Úspěch: `{"ok": true, "hit": "DebugCbpmrController@trace", "app_env": "...", "php": "...", "has_diag_token": true}`  
- `/_debug/cbpmr-parse?token=...&url=...`  
  → kompletní fetch + parse CBPMR  
  - Úspěch: `{"ok": true, "stage": "return_ok", "final_url": "...", "portable_id": "...", "rows_found": 123, "first_rows": [...]}`  
  - Typická chyba: `{"ok": false, "error": "missing_token|missing_url|invalid_host|http_error|no_rows_found|exception", "stage": "..."}`  
- `/_debug/cbpmr-fetch?token=...&url=...`  
  → raw fetch + preview HTML  

### last_exception.txt
- Root výjimky se zapisují do `storage/logs/last_exception.txt` (jen přes `/diag`).  
- **Nikdy** nesmí být veřejně dostupný přes webserver.  
- Pro `QueryException` hledej `sql` + `bindings` pro diagnostiku schema/encoding problémů.  

## Import providers

Detailní popis viz [docs/providers.md](docs/providers.md).

Podporované zdroje deníků:
- `cbdx.cz` (OK)
- `cbpmr.cz` (OK)
- `cbpmr.info` (OK)

Poznámky pro `cbpmr.info`:
- Redirect `/share/{id}` → `/share/portable/{id}`.  
- Parsuje se: `title`, `place`, `my_locator`, `qso_count_header`, `rows`.  
- Importní payload se **neukládá** do tabulky `diary` jako JSON (schema nemá `meta/options`).  

## DB Schema constraints

- Tabulka `diary` **nemá** sloupce `options`/`meta`.  
  - Importní payload patří do session nebo do samostatné tabulky (pokud bude potřeba).  
- `score_points` existuje a používá se při soutěžích, které mají criterion `score_points`.  

## Runbook: když něco nejde uložit

1. Zreprodukuj problém na `/submission` (step=2).  
2. Otevři `/diag?token=...`.  
3. Zkontroluj `last_exception.txt`:  
   - `QueryException` → schema/constraint/encoding  
   - `ViewException` → typy ve view (`count/foreach`) → použij `$safeCount/$asArray`  
   - `Missing column` → DB schema mismatch  
4. Fix **minimálně** v `SubmissionController::submit()` (step=2) – zásada: nehrabat do middleware.  
5. Po změně Blade šablon smaž `storage/framework/views/*.php`.  

## Deployment notes (InfinityFree)

- PHP 8.3, deploy přes GitHub Actions.  
- Writable adresáře: `storage/`, `storage/logs/`, `bootstrap/cache/`.  
- Po změnách view mazat `storage/framework/views/*.php` (na InfinityFree bez artisan).  
- Logy ze `storage/logs` nikdy nevystavovat veřejně.  

## Dokumentace prostředí (ENV)

Podrobný seznam proměnných najdeš v [docs/env.md](docs/env.md).

## Co se může rozbít časem

- změna HTML struktury CBPMR  
- změny OAuth API (Facebook/X/Google)  
- PHP/Lumen upgrade  

Doporučení:  
- používat debug endpointy  
- testovat import při každé větší změně  

## Analýza projektu (aktuální stav)

- **Technologie a běh aplikace:** Lumen aplikace s definovanými routami v `routes/web.php`, včetně `/health` endpointu s kontrolou DB a vracením `APP_URL`.  
- **Konfigurace:** `APP_URL` se čte z prostředí a používá se v konfiguraci aplikace. Další provozní odkazy (repo, issues, Mapbox, reCAPTCHA) jsou řízeny přes `.env` klíče v `config/ctvero.php`.  
- **Autentizace:** OAuth callbacky pro Facebook/Google/Twitter jsou definovány v `config/services.php` a jejich hodnoty se čtou z `.env`.  
- **CI/CD:**  
  - `test.yml` spouští testy v Dockeru na PHP 8.2.  
  - `security-audit.yml` spouští `composer audit` (včetně pravidelného plánování).  
  - `prod.yml` nasazuje po úspěšných testech na `main`.  
- **Deploy a tajemství:** Produkční deploy dešifruje `deploy-prod-files/.env.gpg` pomocí `CTVERO_DEPLOY_PROD_SECRET`, instaluje závislosti, publikuje artefakty přes FTP a volá API endpoint pro migrace.  

## Návrhy úprav / zlepšení

1. **Udržet konzistentní verze závislostí**  
   - Doporučení: commitovat `composer.lock` a držet jej v repu, aby lokální/CI/build prostředí běželo na stejných verzích.  
2. **Standardizovat konfiguraci domén a služeb**  
   - Doplnit do dokumentace seznam `.env` klíčů, které jsou vázané na doménu: `APP_URL`, OAuth callbacky (Facebook/Google/Twitter), reCAPTCHA site key a případně `CTVERO_REPOSITORY_URL` / `CTVERO_ISSUES_REPORT_URL`.  
3. **Přidat kontrolní checklist pro provoz**  
   - Krátký checklist pro post-deploy (health endpoint, OAuth login, kontakt/formuláře, statická aktiva) sníží riziko regresí.  
4. **Průběžná bezpečnostní hygiena**  
   - Už existuje `security-audit.yml`, ale doporučuji navázat proces: řešit výstupy pravidelně a plánovat aktualizace závislostí (např. 1× měsíčně).  
5. **Upgrade frameworku v menších krocích**  
   - Pokud je Lumen starší, plánovat upgrade postupně (composer update → testy → staging → prod).  

## Postup / návod pro migraci na novou doménu

Níže je doporučený, bezpečný postup s jasnými kontrolami:

1. **Příprava DNS a certifikátu**  
   - Připravte nové DNS záznamy (A/AAAA/CNAME) a snižte TTL pro rychlé přepnutí.  
   - Vystavte TLS certifikát pro novou doménu (např. Let’s Encrypt).  
2. **Aktualizace `.env`/secrets**  
   - Změňte `APP_URL` v produkčním `.env` (resp. `deploy-prod-files/.env.gpg`).  
   - Upravte OAuth callback URL (`FACEBOOK_CALLBACK_URL`, `GOOGLE_CALLBACK_URL`, `TWITTER_CALLBACK_URL`).  
   - Zkontrolujte doménové klíče služeb: reCAPTCHA (site key), Mapbox, případně `CTVERO_REPOSITORY_URL` a `CTVERO_ISSUES_REPORT_URL`.  
3. **Aplikace a API migrace**  
   - Po nasazení ověřte `/health` endpoint a běh migrací (deploy je volá přes API).  
4. **Webserver / reverse proxy**  
   - Upravte v hostingu konfiguraci virtuálního hostu (Apache/Nginx) na novou doménu.  
5. **Přesměrování (301)**  
   - Nastavte 301 redirect ze staré domény na novou kvůli SEO i zachování odkazů.  
6. **Testovací checklist po migraci**  
   - Ověřte homepage, formulář pro zprávu, přihlášení přes OAuth, endpointy API a statická aktiva.  
   - Sledujte logy a chybovost v prvních hodinách po přepnutí.  

### Deployment

#### Automated Workflow (preferred)

Long story short: After a successfully tested PR merge to the production branch (`main`), the application gets deployed.

As of 2021/11, we're using GitHub Actions for this.
There's a strong secret (see `CTVERO_DEPLOY_PROD_SECRET` environment variable) which decrypts the file `deploy-prod-files/.env.gpg`. This file contains all other secrets which are necessary for application deployment and runtime. See also `docker/entrypoint.sh`, `docker-compose.yml` and `.github/workflows/prod.yml`.

#### Manual Workflow

Prerequisites:

- Locally cloned up-to-date production branch (`main`) which has successful tests in GitHub Actions CI. (See above.)
- No `ctvero-*` docker containers/images artifacts are present. (Check using `docker ps -a`, `docker images` and/or remove those artifacts using `docker rm`, `docker rmi`.)
- No repository artifacts are present. (Check using `git status --ignored` and/or remove those artifacts using `git clean -fdx`.)

Deployment Steps:

```
docker-compose build --build-arg PHP_IMAGE_TAG=8.2-fpm-alpine3.19 deploy-prod  # for PHP 8.2 LTS webhosting
UID_GID="$(id -u):$(id -g)" CTVERO_DEPLOY_PROD_SECRET="some-very-long-secret" docker-compose up deploy-prod  # deploys the app
```

#### InfinityFree hosting

Pro jednoduchý hosting InfinityFree je připraven checklist a ukázkový `.env`:

- `deploy-prod-files/INFINITYFREE.md`
- `deploy-prod-files/.env.infinityfree.example`

### License

This app is open-sourced software under the *Ham Spirit Transferred into IT* license :-).

## Lumen Framework

[![Build Status](https://travis-ci.org/laravel/lumen-framework.svg)](https://travis-ci.org/laravel/lumen-framework)
[![Total Downloads](https://img.shields.io/packagist/dt/laravel/framework)](https://packagist.org/packages/laravel/lumen-framework)
[![Latest Stable Version](https://img.shields.io/packagist/v/laravel/framework)](https://packagist.org/packages/laravel/lumen-framework)
[![License](https://img.shields.io/packagist/l/laravel/framework)](https://packagist.org/packages/laravel/lumen-framework)

Laravel Lumen is a stunningly fast PHP micro-framework for building web applications with expressive, elegant syntax. We believe development must be an enjoyable, creative experience to be truly fulfilling. Lumen attempts to take the pain out of development by easing common tasks used in the majority of web projects, such as routing, database abstraction, queueing, and caching.

### Official Documentation for Lumen

Documentation for the framework can be found on the [Lumen website](https://lumen.laravel.com/docs).

### Contributing

Thank you for considering contributing to Lumen! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

### Security Vulnerabilities

If you discover a security vulnerability within Lumen, please send an e-mail to Taylor Otwell at taylor@laravel.com. All security vulnerabilities will be promptly addressed.

### License

The Lumen framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Provozní poznámky

### APP_URL / doména

- **Lokální vývoj:** nastavuje se v `.env` (soubor není commitovaný).  
- **Produkce:** hodnota je v `deploy-prod-files/.env.gpg` a dešifruje se v `prod` workflow.  
- Změna `APP_URL` ovlivňuje generované odkazy (např. `route()`, URL helpery, odkazy v e-mailech).  

### Healthcheck endpoint

- `/health` vrací JSON se stavem aplikace a připojením na DB (200/500 podle stavu DB).  
- Hodí se jako jednoduchý základ pro monitoring (uptime check, load balancer healthcheck).  

### Logování

- Doporučená je rotace logů buď na hostu (`logrotate`), nebo přes Docker log driver (např. `json-file` s limity).  
