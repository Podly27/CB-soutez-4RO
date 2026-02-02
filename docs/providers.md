# Import providers

Podporované zdroje deníků:

- **cbdx.cz** (OK)
- **cbpmr.cz** (OK)
- **cbpmr.info** (OK)

## cbpmr.info

- URL `/share/{id}` obvykle přesměrovává na `/share/portable/{id}`.
- Parser používá data z portable HTML:
  - `title`, `place`, `my_locator`, `qso_count_header`, `rows`.
- Importní payload se **neukládá** do tabulky `diary` jako JSON, protože schema nemá `meta/options` sloupce.
