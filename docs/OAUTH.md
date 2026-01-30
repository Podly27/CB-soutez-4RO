# OAuth nastavení

## Google
- Redirect URI: `https://4ro.infinityfreeapp.com/auth/google/callback`
- `.env`:
  ```
  GOOGLE_CLIENT_ID=
  GOOGLE_CLIENT_SECRET=
  GOOGLE_REDIRECT_URI=https://4ro.infinityfreeapp.com/auth/google/callback
  ```

## Facebook
- Redirect URI: `https://4ro.infinityfreeapp.com/auth/facebook/callback`
- Poznámka: Meta/Facebook vyžaduje **HTTPS** redirect URL.
- `.env` (projekt historicky střídal názvy – podporujeme obě varianty):
  ```
  FACEBOOK_CLIENT_ID=      # (== App ID)
  FACEBOOK_CLIENT_SECRET=  # (== App Secret)
  FACEBOOK_APP_ID=
  FACEBOOK_APP_SECRET=
  FACEBOOK_REDIRECT_URI=https://4ro.infinityfreeapp.com/auth/facebook/callback
  ```
- Povolení: permission **"email"** musí být povolená v Meta Login permissions.
- Chyba **"Error validating client secret"** obvykle znamená špatný nebo oklepaný secret.

## Twitter / X
- Aktuálně přeskočeno – neimplementovat bez potřeby.

