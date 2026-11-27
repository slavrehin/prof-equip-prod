# Winterhalter: посудомоечные машины (2026-11-27)

Раздел «Посудомоечное оборудование» (`/product-category/posudomoechnoe-oborudovanie/`).

- `products.json` — 13 моделей: UC-S/M/L/XL, PT-M/L/XL, PT-L/XL Utensil, UF-M/L/XL, GS 630. Источник — официальные русские листовки Winterhalter (assetId в `meta.source_assets`). UF-XL создаётся черновиком (`prod_draft`): нет фото модели двойной ширины.
- Карточки серий MTR, CTR, MTF, STF (созданы 2026-11-07) дополнительно привязываются к разделу и получают «Тип»; основной раздел, тексты и SEO не меняются.
- Фото — партия `local/deploy/product_photos/batches/winterhalter_20261127` (12 товаров, без UF-XL).

## Прод

```bash
ssh root@89.111.178.85 "mysqldump --defaults-extra-file=/root/.my-prof.cnf --single-transaction --no-tablespaces profdb \
  | gzip > /backup/prod_db_before_winterhalter_\$(date +%Y%m%d_%H%M%S).sql.gz"
ssh root@89.111.178.85 "cd /var/www/prof-equip.ru/docs && sudo -u www-data php local/deploy/winterhalter_dishwashers/import.php --dry"
ssh root@89.111.178.85 "cd /var/www/prof-equip.ru/docs && sudo -u www-data php local/deploy/winterhalter_dishwashers/import.php \
  --log=/var/www/prof-equip.ru/rollback_winterhalter_dishwashers.json"
ssh root@89.111.178.85 "cd /var/www/prof-equip.ru/docs && sudo -u www-data php local/deploy/product_photos/import.php winterhalter_20261127 \
  --log=/var/www/prof-equip.ru/rollback_winterhalter_20261127.json"
```

Откат: сначала фото (`product_photos/import.php --rollback=…`), затем `import.php --rollback=/var/www/prof-equip.ru/rollback_winterhalter_dishwashers.json`.

## UF-XL

Когда будет фото: положить `img/winterhalter-uf-xl.jpg` (normalize.py, 1200×1200) в новую партию фото, импортировать её и активировать товар `winterhalter-uf-xl` (ACTIVE=Y) в админке или SQL.
