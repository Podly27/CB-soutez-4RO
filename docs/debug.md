# Debug & Diagnostics

## DIAG_TOKEN

- Debug endpointy vyžadují query parametr `token` se shodou na `DIAG_TOKEN` z `.env`.
- Příklady:
  - `/diag?token=<DIAG_TOKEN>`
  - `/_debug/cbpmr-parse?token=<DIAG_TOKEN>&url=<encoded>`

## Debug endpointy

- `/_debug/ping-json?token=...` – ověření routingu + JSON response.
- `/_debug/trace?token=...` – ověření controlleru + middleware.
- `/_debug/cbpmr-fetch?token=...&url=...` – raw fetch + preview.
- `/_debug/cbpmr-parse?token=...&url=...` – fetch + parse + preview záznamů.
- `/diag?token=...` – diagnostika storage + `last_exception`.

## last_exception.txt

- Root výjimky se zapisují do `storage/logs/last_exception.txt`.
- Log **nikdy** nesmí být veřejně přístupný přes webserver.
- `QueryException` obvykle obsahuje `sql` + `bindings` pro identifikaci schema/constraint problémů.

## Bezpečnost

- `DIAG_TOKEN` držet mimo git, používat jen pro admin diagnostiku.
- `storage/logs/*` musí být blokované (např. `.htaccess`).
