# Kontaktní formulář / email

## Povinné env (kam se posílají zprávy)
- Primárně:
  ```
  CTVERO_OWNER_MAIL=
  ```
- Fallbacky (projekt je historicky používal):
  ```
  CTVERO_OWNER_EMAIL=
  OWNER_MAIL=
  ```

## Gmail SMTP nastavení (doporučený postup)
1. Vytvoř **App Password** v Google účtu.
   - Vlož bez mezer a bez uvozovek.
2. `.env`:
   ```
   MAIL_MAILER=smtp
   MAIL_HOST=smtp.gmail.com
   MAIL_PORT=587
   MAIL_ENCRYPTION=tls
   MAIL_USERNAME=
   MAIL_PASSWORD=
   MAIL_FROM_ADDRESS=
   MAIL_FROM_NAME="Čtyři roční období"

   CTVERO_OWNER_MAIL=
   ```

## reCAPTCHA
- Pokud je zapnutá, musí být nastaveny klíče v `.env`:
  ```
  CTVERO_RECAPTCHA_SITE_KEY=
  CTVERO_RECAPTCHA_ENTERPRISE_PROJECT_ID=
  CTVERO_RECAPTCHA_ENTERPRISE_API_KEY=
  CTVERO_RECAPTCHA_EXPECTED_ACTION=submit
  CTVERO_RECAPTCHA_SCORE_THRESHOLD=0.5
  # fallback pro legacy kontrolu:
  CTVERO_RECAPTCHA_SECRET=
  ```
- Pokud nejsou klíče nastavené, aplikace **nesmí padat 500** – uživatel musí dostat srozumitelnou hlášku.

