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

## Analýza projektu (stručný přehled)

- **Stack:** PHP/Lumen aplikace s Docker/Docker Compose workflowem pro lokální vývoj, testy i deploy (viz `docker-compose.yml` a `docker/`).  
- **CI/CD:** GitHub Actions (test + prod workflow) s tajným klíčem pro dešifrování `.env.gpg` a automatickým nasazením na `main`.  
- **Konfigurace:** Aplikace používá `.env` (v produkci šifrovaný `.env.gpg`) a standardní Laravel/Lumen konfiguraci.  

## Návrhy úprav / zlepšení

1. **Aktualizace PHP verze a image (provedeno)**  
   Přechod z PHP 7.4 na podporovanou LTS verzi PHP (8.2) a odpovídající Alpine image.  
   - Aktualizovat `docker-compose.yml`/`docker/` tak, aby nové image odpovídaly LTS verzi (8.2).  
   - Změnit build-arg `PHP_IMAGE_TAG` v deploy kroku (viz sekce „Deployment“).  
   - Ověřit kompatibilitu s Lumen/Composer závislostmi.  
2. **Bezpečnostní audit závislostí**  
   Pravidelně kontrolovat composer závislosti a aktualizovat security fixy.  
   - Doporučení: pravidelně spouštět `composer audit` a aktualizovat balíčky podle výstupu.  
   - Zvážit automatizaci v CI (např. týdenní scheduled job).  
3. **Explicitní dokumentace domén a URL**  
   Přehled, kde se nastavuje `APP_URL`/doména a jaký je dopad na generované odkazy:  
   - **Lokálně**: `.env` (není commitovaný).  
   - **Produkce**: `deploy-prod-files/.env.gpg` (dešifrování v `prod` workflow).  
   - Změna `APP_URL` ovlivňuje generované odkazy (např. URL helpery a případné odkazy v e-mailech).  
4. **Monitoring & logy**  
   Doplnit doporučení pro logování (rotace) a základní healthcheck endpoint.  
   - Logy: doporučit rotaci na hostu nebo v kontejnerech (např. `logrotate` nebo Docker log driver).  
   - Healthcheck: přidat jednoduchý endpoint (např. `/health`) vracející 200 OK a základní diagnostiku (DB připojení / verze).  
5. **Případný upgrade Lumen**  
   Pokud je framework starší, naplánovat upgrade (testy + ověření kompatibility).  
   - Postup: upgrade závislostí v `composer.json`, běh testů, kontrola deploy pipeline.  
   - Doporučení: upgrade rozdělit do menších kroků kvůli snadnému rollbacku.  

## Postup / návod pro migraci na novou doménu

Níže je bezpečný, krokový postup pro přesun na novou doménu:

1. **Příprava DNS**  
   - Připravte nové DNS záznamy (A/AAAA/CNAME) pro novou doménu.  
   - Snižte TTL u stávajících záznamů před migrací pro rychlejší přepnutí.  
2. **TLS certifikát**  
   - Vystavte certifikát pro novou doménu (např. Let’s Encrypt) a připravte jej na serveru.  
3. **Konfigurace aplikace**  
   - Aktualizujte `APP_URL` v produkčním `.env` (resp. `.env.gpg`) na novou doménu.  
   - Pokud existují hardcoded URL v šablonách/JS, upravte je.  
4. **Webserver / reverse proxy**  
   - Upravte v hostingu konfiguraci virtuálního hostu (v Apache/Nginx) na novou doménu.  
5. **Přesměrování (301)**  
   - Nastavte 301 redirect ze staré domény na novou, aby se zachovalo SEO.  
6. **Testování**  
   - Ověřte funkčnost: homepage, formuláře, API endpointy, statické soubory, HTTPS.  
7. **Monitoring po nasazení**  
   - Sledujte logy a chybovost po přepnutí DNS.  

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
