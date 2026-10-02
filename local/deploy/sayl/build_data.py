#!/usr/bin/env python3
"""
SAYL: готовит данные для import.php из исходников владельца (папка Sayl с 7 docx и фото):
  - products.json — 29 моделей (название, разделы, «Основные характеристики», описание,
    характеристики SPECS, фото, SEO);
  - папка оптимизированных картинок (JPEG q85, 1600 px по длинной стороне, латиница);
  - dry-run отчёт в stdout (модель → разделы → slug → фото → число характеристик → предупреждения).

Запуск на хосте (нужны python-docx и Pillow):
  PYTHONPATH=<pylib> python3 build_data.py --src=/var/www/prof-equip-test/sayl_src/Sayl \
      --out=/var/www/prof-equip-test/sayl_photos

Тексты не переписываются: описание и характеристики — как в docx, правки только технические
(** / неразрывные пробелы / переносы, регистр ключей «СТРАНА» → «Страна»).
"""
import argparse
import html
import json
import os
import re
import sys
import unicodedata

from PIL import Image

from parse_docx import parse

HERE = os.path.dirname(os.path.abspath(__file__))

DOCX = [
    'Buffet Line (2).docx', 'GN1-1 LINE (2).docx', 'Integra Line (2).docx', 'Neutra Line (2).docx',
    'PAK Line (2).docx', 'Sobremostrador Line (2).docx', 'Sushi Line (2).docx',
]

# Модель (как в docx, NFC) → папка фото, файлы (1-й — главное), разделы (1-й — основной).
# Разделы — по брифу владельца: холодильные / тепловые / нейтральные / настольные / суши / буфетные.
H, T, N, D, S, B = 'holodilnye-vitriny', 'teplovye-vitriny', 'neytralnye-vitriny', 'nastolnye-vitriny', 'vitriny-dlya-sushi', 'bufetnye-stantsii'
MODELS = {
    'BUFFET ISLAND':    ('BUFFET PORTATIL (2)', ['BUFFET ISLAND.png'], [B]),
    'BUFFET SERVICE':   ('BUFFET PORTATIL (2)', ['BUFFET SERVICE.png', 'BUFFET SERVICE (2).png'], [B]),
    'BUFFET VISION':    ('BUFFET PORTATIL (2)', [], [B]),  # фото нет — черновик (аномалия 1)
    'BUFFET INTEGRA':   ('BUFFET PORTATIL (2)', ['BUFFET INTEGRA.png'], [B, T]),
    'BUFFET 360°':      ('BUFFET PORTATIL (2)', ['BUFFET 360º.png'], [B]),
    'BUFFET PORTÁTIL':  ('BUFFET PORTATIL (2)', ['BUFFET PORTATIL.png'], [B]),
    'XL':               ('GN1-1 LINE (2)', ['XL.png'], [H]),
    'LOMA':             ('GN1-1 LINE (2)', ['LOMA.png'], [H]),
    'MAXISELF':         ('GN1-1 LINE (2)', ['MAXISELF.png'], [H, T]),
    'SPLENDID':         ('GN1-1 LINE (2)', ['SPLENDID.png'], [H, T]),
    'INTEGRA DROP-IN':  ('INTEGRA LINE (1) (2)', ['INTEGRA DROP-IN.png'], [H]),
    # INTEGRA GRUPO REMOTO (аномалия 2) не используется: на фото выносной агрегат, а в тексте BASE —
    # «встроенный холодильный агрегат», у DROP-IN — «автономная» система. Ждёт решения владельца.
    'INTEGRA BASE':     ('INTEGRA LINE (1) (2)', ['INTEGRA BASE.png'], [H]),
    'INTEGRA T':        ('INTEGRA LINE (1) (2)', ['INTEGRA T.png'], [H]),
    'INTEGRA COMBI':    ('INTEGRA LINE (1) (2)', ['COMBI.png'], [H]),
    'NEUTRA RECTA':     ('NEUTRA LINE (1) (2)', ['NEUTRA RECTA.png'], [N]),
    'NEUTRA CURVADA':   ('NEUTRA LINE (1) (2)', ['NEUTRA CURVADA.png'], [N]),
    'NEUTRA VISION':    ('NEUTRA LINE (1) (2)', ['NEUTRA VISION.png'], [N]),
    'NEUTRA PITÁGORAS': ('NEUTRA LINE (1) (2)', ['NEUTRA PITAGORAS.png'], [N]),
    'PAK RECTA':        ('PAK LINE (2)', ['PAK RECTA.png'], [H]),
    'PAK CURVADA':      ('PAK LINE (2)', ['PAK CURVADA.png'], [H]),
    'COMPAK':           ('PAK LINE (2)', ['COMPAK.png'], [H]),
    'QBO':              ('Sobremostrador Line (2) (2)', ['QBO.png'], [D, H]),
    'VELA':             ('Sobremostrador Line (2) (2)', ['VELA.png'], [D, H]),
    'TOWER':            ('Sobremostrador Line (2) (2)', ['TOWER.png'], [D, H]),
    'CRYSTAL BOX':      ('Sobremostrador Line (2) (2)', ['CRYSTAL BOX.png'], [D, H]),
    'LOGIC SUSHI':      ('SUSHI LINE (2)', ['LOGIC SUSHI.png'], [S, H]),
    'SHARK SUSHI':      ('SUSHI LINE (2)', ['SHARK SUSHI.png'], [S, H]),
    'CLASSIC SUSHI':    ('SUSHI LINE (2)', ['CLASSIC SUSHI.png'], [S, H]),
    'SLIM SUSHI':       ('SUSHI LINE (2)', ['SLIM SUSHI.png'], [S, H]),
}

SECTIONS = {
    B: 'Буфетные станции', H: 'Холодильные витрины', T: 'Тепловые витрины',
    N: 'Нейтральные витрины', D: 'Настольные витрины', S: 'Витрины для суши',
}

# Поля, которые выводятся свойствами-списками (BRAND/PROIZVODITEL, COUNTRY, LINEYKA) или скрыты
# на сайте (MODEL), — в «Основные характеристики» и SPECS не дублируются.
PROP_KEYS = {'Производитель', 'Страна', 'Линейка', 'Модель'}

# Единые ключи для одинаковых по смыслу полей
KEY_ALIASES = {
    'Температурный режим, охлаждение': 'Температурный режим (охлаждение)',
    'Температурный режим, нагрев': 'Температурный режим (нагрев)',
}

MAX_SIDE = 1600
QUALITY = 85


def norm_key(k):
    k = k.strip()
    k = k[:1].upper() + k[1:].lower()
    return KEY_ALIASES.get(k, k)


def fold(s):
    """Сравнение без регистра и диакритики: PITÁGORAS ↔ PITAGORAS, 360° ↔ 360º."""
    s = s.replace('º', '°')
    s = unicodedata.normalize('NFKD', s)
    return ''.join(c for c in s if not unicodedata.combining(c)).upper()


def slugify(model):
    s = fold(model).lower().replace('°', '')
    s = re.sub(r'[^a-z0-9]+', '-', s).strip('-')
    return 'sayl-' + s


def ucfirst(s):
    return s[:1].upper() + s[1:]


def seo_description(paragraph, limit=160):
    sentences = re.split(r'(?<=[.!?])\s+', paragraph.strip())
    text = sentences[0]
    if len(sentences) > 1 and len(text) + 1 + len(sentences[1]) <= limit:
        text += ' ' + sentences[1]
    if len(text) <= limit:
        return text
    # без обрезки на полуслове: по границе слова, без висящих предлогов/союзов и знаков
    cut = text[:limit - 1].rsplit(' ', 1)[0]
    cut = re.sub(r'(?:[\s,—–-]+(?:и|в|во|с|со|на|по|от|до|для|к|о|об|а|из|за|при))+$', '', cut)
    return cut.rstrip(' ,—–-:;') + '…'


def make_image(src, dst):
    im = Image.open(src)
    if im.mode in ('RGBA', 'LA', 'P'):
        im = im.convert('RGBA')
        bg = Image.new('RGB', im.size, (255, 255, 255))
        bg.paste(im, mask=im.split()[-1])
        im = bg
    else:
        im = im.convert('RGB')
    im.thumbnail((MAX_SIDE, MAX_SIDE), Image.LANCZOS)
    im.save(dst, 'JPEG', quality=QUALITY, optimize=True, progressive=True)
    return os.path.getsize(dst), im.size


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--src', required=True)
    ap.add_argument('--out', required=True)
    args = ap.parse_args()
    os.makedirs(args.out, exist_ok=True)

    products, warnings, seen = [], [], {}
    for fname in DOCX:
        for m in parse(os.path.join(args.src, fname)):
            head = [(norm_key(k), v) for k, v in m['head'] if k]
            specs = [(norm_key(k), v) for k, v in m['specs']]
            hd, sd = dict(head), dict(specs)
            model = sd.get('Модель') or hd.get('Модель')
            line = hd.get('Линейка') or sd.get('Линейка')
            if not model or not line:
                sys.exit(f'{fname}: нет модели/линейки в «{m["heading"]}»')
            if model in seen:
                if seen[model] != (m['head'], m['desc'], m['specs']):
                    sys.exit(f'{model}: повторный блок отличается от первого — разобраться вручную')
                warnings.append(f'{model}: блок продублирован в {fname} — создан один товар')
                continue
            seen[model] = (m['head'], m['desc'], m['specs'])
            if model not in MODELS:
                sys.exit(f'{model}: нет в таблице MODELS')
            if 'Линейка' not in hd:
                warnings.append(f'{model}: в шапке нет «Линейка» — взята из «Характеристик» ({line})')
            folder, photos, sections = MODELS[model]
            type_ = ucfirst(sd.get('Тип оборудования') or hd['Тип оборудования'])
            name = f'{type_} SAYL {model}'
            slug = slugify(model)

            images = []
            for n, ph in enumerate(photos, 1):
                src = os.path.join(args.src, folder, ph)
                if not os.path.isfile(src):
                    sys.exit(f'{model}: нет фото {src}')
                stem = re.sub(r'\s*\(\d+\)$', '', fold(os.path.splitext(ph)[0]))
                if not fold(model).endswith(stem):  # COMBI.png ↔ INTEGRA COMBI
                    warnings.append(f'{model}: имя фото «{ph}» не совпадает с моделью — проверить')
                file = slug + ('' if n == 1 else f'-{n}') + '.jpg'
                size, dim = make_image(src, os.path.join(args.out, file))
                if size > 300 * 1024:
                    warnings.append(f'{model}: {file} {size // 1024} КБ > 300 КБ')
                images.append({'file': file, 'source': f'{folder}/{ph}', 'bytes': size, 'size': list(dim),
                               'alt': f'{type_} SAYL {model}' + ('' if n == 1 else f' — фото {n}')})
            if not images:
                warnings.append(f'{model}: фото нет — товар создаётся черновиком (ACTIVE=N)')

            main_rows = [(k, v) for k, v in head if k not in PROP_KEYS]
            preview = '<h2>Основные характеристики</h2>\n<table class="ch">\n<tbody>\n' + ''.join(
                f'<tr>\n<td class="name">{html.escape(k, False)}</td>\n<td class="value">{html.escape(v, False)}</td>\n</tr>\n'
                for k, v in main_rows) + '</tbody>\n</table>\n'
            desc_html = '\n'.join(f'<p>{html.escape(p, False)}</p>' for p in m['desc'])
            products.append({
                'model': model,
                'line': line,
                'slug': slug,
                'name': name,
                'type': type_,
                'sections': sections,
                'active': bool(images),
                'sort': 500 + len(products) + 1,
                'heading_docx': m['heading'],
                'source_docx': fname,
                'main_specs': [{'name': k, 'value': v} for k, v in main_rows],
                'preview_html': preview,
                'description_paragraphs': m['desc'],
                'description_html': desc_html,
                'specs': [{'name': k, 'value': v} for k, v in specs if k not in PROP_KEYS],
                'images': images,
                'seo': {
                    'title': f'{type_} SAYL {model} серии {line} — купить в ПРОФЭКВИП',
                    'description': seo_description(m['desc'][0]),
                },
            })

    if len(products) != 29:
        sys.exit(f'ожидалось 29 моделей, получено {len(products)}')
    slugs = [p['slug'] for p in products]
    if len(set(slugs)) != len(slugs):
        sys.exit('дубли slug')
    used = {i['source'] for p in products for i in p['images']}
    all_png = {f'{f}/{x}' for f, *_ in MODELS.values() for x in os.listdir(os.path.join(args.src, f))
               if x.lower().endswith('.png') and not x.startswith('._')}
    if all_png - used:
        warnings.append('не использованы фото: ' + ', '.join(sorted(all_png - used)))

    data = {'sections': SECTIONS, 'products': products, 'warnings': warnings}
    with open(os.path.join(HERE, 'products.json'), 'w', encoding='utf-8') as f:
        json.dump(data, f, ensure_ascii=False, indent=1)

    print(f'{"#":>2} | {"Модель":17} | {"Разделы":42} | {"slug":24} | фото | хар. | осн.')
    for i, p in enumerate(products, 1):
        print(f'{i:>2} | {p["model"]:17} | {", ".join(p["sections"]):42} | {p["slug"]:24} | {len(p["images"]):>4} | {len(p["specs"]):>4} | {len(p["main_specs"]):>4}'
              + ('' if p['active'] else ' | ЧЕРНОВИК'))
    print(f'\nФото: {sum(len(p["images"]) for p in products)}, '
          f'макс. {max(i["bytes"] for p in products for i in p["images"]) // 1024} КБ')
    print('Предупреждения:\n  ' + '\n  '.join(warnings))


if __name__ == '__main__':
    main()
