#!/usr/bin/env python3
"""Собирает data.json для страниц направлений из wayback-копий (июнь 2026).
Запуск из корня prof-equip-local: python3 -I site/www/local/deploy/directions_2026_11_09/extract_wayback.py
"""
import json, re, html, os
ROOT = 'archive/wayback/pages'
OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'data.json')

def load(p):
    s = open(f'{ROOT}/{p}/index.html', encoding='utf-8').read()
    s = s[s.find('<main'):s.find('<footer')]
    return re.sub(r'<script.*?</script>|<svg.*?</svg>', '', s, flags=re.S)

def sec(s, cls):
    m = re.search(r'<section class="%s[" ](.*?)</section>' % re.escape(cls), s, flags=re.S)
    return m.group(1) if m else ''

def txt(x):
    x = re.sub(r'<br\s*/?>', '\n', x)
    x = re.sub(r'<[^>]+>', ' ', x)
    x = html.unescape(x).replace('\xa0', ' ')
    return re.sub(r'[ \t]+', ' ', re.sub(r'\s*\n\s*', '\n', x)).strip()

def codes(s, prefix):
    out = []
    for c in re.findall(r'href="/%s/([^/"]+)/"' % prefix, s):
        if c not in out:
            out.append(c)
    return out

def steps(s):
    b = sec(s, 'steps')
    return [{'name': txt(t), 'text': txt(d)} for t, d in
            re.findall(r'<p class="title">(.*?)</p>\s*<p class="text">(.*?)</p>', b, flags=re.S)]

def reviews(s):
    b = sec(s, 'reviews')
    out = []
    for t, a in re.findall(r'<p class="text">(.*?)</p>\s*<p class="author">(.*?)</p>', b, flags=re.S):
        t = html.unescape(t)          # в шаблоне text экранируется, <br> лежали в PREVIEW_TEXT как текст
        t = re.sub(r'<br\s*/?>', '\n', t)
        out.append({'text': re.sub(r'\s*\n\s*', ' ', txt(t)), 'author': txt(a)})  # шаблон экранирует текст, переносы не нужны
    return out

def faq(s):
    out = []
    for q, a in re.findall(r'accordion__title__text">(.*?)</span>.*?accordion__content[^>]*>(.*?)</div>', s, flags=re.S):
        out.append({'q': txt(q), 'a': a.strip()})
    return out

data = {}

# ---- Кухня
k = load('kuhnya')
about = re.search(r'<div class="direction-about__text">(.*?)<a class="btn', k, flags=re.S).group(1).strip()
data['kuhnya'] = {
    'DESCRIPTION': about,
    'LINK_CATALOG': re.search(r'direction-about__text.*?<a class="btn[^"]*" href="([^"]+)"', k, flags=re.S).group(1),
    'PROJECTS': codes(sec(k, 'portfolio'), 'portfolio'),
    'BRANDS': codes(sec(k, 'manufacturers'), 'brends'),
    'CATEGORIES_CATALOG': codes(sec(k, 'catalog-links-list'), 'product-category'),
    'STEPS': steps(k),
    'SOLUTION': codes(k, 'gotovye-resheniya'),
    'REVIEWS': reviews(k),
}

# ---- Химия
h = load('himiya')
info = sec(h, 'chemistry-info')
texts = re.findall(r'<div class="chemistry-info__text">(.*?)</div>', info, flags=re.S)
feat = sec(h, 'chemistry-features')
st = sec(h, 'chemistry-steps')
form = sec(h, 'chemistry-form')
data['chemistry'] = {
    'MAIN_TITLE': txt(re.search(r'<h1[^>]*>(.*?)</h1>', info, flags=re.S).group(1)),
    'MAIN_TEXT_LEFT': texts[0].strip(),
    'MAIN_TEXT_RIGHT': texts[1].strip(),
    'STAT': [{'v': txt(n), 'd': txt(d)} for n, d in re.findall(
        r'stat__number">(.*?)</p>\s*<p class="stat__descr">(.*?)</p>', info, flags=re.S)],
    'TITLE_GARANT': txt(re.search(r'<h2[^>]*>(.*?)</h2>', feat, flags=re.S).group(1)),
    'LIST_GARANT': [txt(x) for x in re.findall(r'chemistry-feature__item">.*?<p>(.*?)</p>', feat, flags=re.S)],
    'STEPS': [{'name': re.sub(r'^\d+\.\s*', '', txt(t)), 'text': txt(d)} for t, d in re.findall(
        r'<p class="title">(.*?)</p>\s*<p class="descr">(.*?)</p>', st, flags=re.S)],
    'CATALOG_PRODUCTS': codes(sec(h, 'chemistry-catalog'), 'product'),
    'TITLE_FORM': txt(re.search(r'chemistry-form__title">(.*?)</h2>', form, flags=re.S).group(1)),
    'TEXT_FORM': re.search(r'chemistry-form__descr">(.*?)</p>', form, flags=re.S).group(1).strip(),
    'REVIEWS': reviews(h),
}

# ---- Оснащение отелей
e = load('kompleksnoe-osnashhenie-otelej')
eq = {}
blocks = re.findall(r'<div class="equipment-about__item[^"]*">(.*?)(?=<div class="equipment-about__item|<section)', e, flags=re.S)
for i, b in enumerate(blocks, 1):
    t = re.search(r'equipment__title">(.*?)</h3>', b, flags=re.S).group(1)
    d = re.search(r'<p class="equipment__descr">(.*)</p>\s*(?:<a class="btn cost__btn"|</div>)', b, flags=re.S).group(1).strip()
    d = re.sub(r'\s*<a href="https://prof-equip.ru/tekstil/" target="_blank"></a>\s*$', '', d)
    l = re.search(r'<a class="btn cost__btn" href="([^"]*)"', b)
    eq[f'ITEM_{i}_TITLE'] = txt(t)
    eq[f'ITEM_{i}_TEXT'] = d
    eq[f'ITEM_{i}_BUTTON_LINK'] = l.group(1) if l else ''
eq.update({
    'PROJECTS': codes(sec(e, 'portfolio'), 'portfolio'),
    'BRANDS': codes(sec(e, 'manufacturers'), 'brends'),
    'CATEGORIES_CATALOG': codes(sec(e, 'catalog-links-list'), 'product-category'),
    'STEPS': steps(e),
    'SOLUTION': codes(e, 'gotovye-resheniya'),
    'REVIEWS': reviews(e),
    'FAQ': faq(sec(e, 'faq')),
})
data['equipment'] = eq

# ---- Мебель
m = load('mebel')
data['furniture'] = {
    'HERO_TITLE': txt(re.search(r'furniture-hero__title">(.*?)</h1>', m, flags=re.S).group(1)),
    'HERO_DESCR': html.unescape(re.search(r'furniture-hero__descr">(.*?)</p>', m, flags=re.S).group(1)).strip(),
    'ABOUT_DESC': [txt(x) for x in re.findall(r'feature icon">\s*<p>(.*?)</p>', m, flags=re.S)],
    'ABOUT_TEXT': txt(re.search(r'furniture-about__content-text">\s*<p>(.*?)</p>', m, flags=re.S).group(1)),
    'PLACES': [{'t': txt(t), 'd': txt(d)} for t, d in re.findall(
        r'furniture-places__item-content">\s*<p class="title">(.*?)</p>\s*<p class="descr">(.*?)</p>', m, flags=re.S)],
    'CABINET_TITLE': txt(re.search(r'furniture-cabinet__features">\s*<p class="title">(.*?)</p>', m, flags=re.S).group(1)),
    'CABINET_DESCR': txt(re.search(r'furniture-cabinet__features">.*?<p class="descr">(.*?)</p>', m, flags=re.S).group(1)),
    'CABINET_FEATURES': [txt(x) for x in re.findall(r'cabinet icon">\s*<p>(.*?)</p>', m, flags=re.S)],
    'CASES_TEXT': txt(re.search(r'furniture-cases__content-text">\s*<p>(.*?)</p>', m, flags=re.S).group(1)),
    'CASES_ADDITIONAL_TEXT': txt(re.search(r'furniture-cases__content-image">.*?</div>\s*<p>(.*?)</p>', m, flags=re.S).group(1)),
    'CASES_COUNT': len(re.findall(r'alt="case"', m)) - 1,
    'SUPPLIERS_COUNT': len(re.findall(r'alt="supplier"', m)),
}

json.dump(data, open(OUT, 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
for k2, v in data.items():
    print(k2, {kk: (len(vv) if isinstance(vv, (list, str)) else vv) for kk, vv in v.items()})
