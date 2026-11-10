#!/usr/bin/env python3
"""Строит data.json для import.php: из DETAIL_TEXT статей блога вырезает вшитые виджеты
(WP: portfolio/products/blog-карусели The7 + сетки товаров WPBakery; Битрикс: отрисованные
.blog-about-catalog / .blog-about-projects) и превращает их в свойства CATALOG_PRODUCT_1/2,
TITLE_CATALOG_1/2, PROJECTS (товары и проекты — по CODE, ID ищет import.php). На месте товарного блока ставится маркер <!--BLOG_CATALOG_N-->.

  python3 build.py blog_texts.json codes.tsv ../../../../../archive/wayback > data.json 2> build.log
blog_texts.json — [{id,code,pt,dt}] с прода; codes.tsv — IBLOCK_ID CODE ID ACTIVE NAME (инфоблоки 8, 11).
"""
import hashlib, json, re, sys
from bs4 import BeautifulSoup, NavigableString, Tag

texts = json.load(open(sys.argv[1]))
WAYBACK = sys.argv[3]  # archive/wayback: images/ (кэш картинок) и pages_wp_blog/ (WP-снимки статей)
active = {}
for l in open(sys.argv[2]):
    ib, c, i, a, n = l.rstrip('\n').split('\t')
    if a == 'Y':
        active[(ib, c)] = int(i)

RX_PROD = re.compile(r'^(?:https?://[^/]+)?/product/([^/?#]+)/?$')
RX_PROJ = re.compile(r'^(?:https?://[^/]+)?/(?:project|portfolio)/([^/?#]+)/?$')
RX_HEAD = re.compile(r'(ассортимент|наше оборудование|наши выполненные проекты|выполненные проекты|купить .* в профэквип|готовые решения)', re.I)
WIDGET_CLS = ('portfolio-shortcode', 'products-carousel-shortcode', 'products-shortcode', 'blog-carousel-shortcode',
              'blog-shortcode', 'dt-css-grid', 'owl-carousel', 'blog-about-catalog', 'blog-about-projects')


def links(node, rx):
    out = []
    for a in node.select('a[href]'):
        m = rx.match(a['href'].strip())
        if m and m.group(1) not in out:
            out.append(m.group(1))
    return out


def classes(n):
    return ' '.join(n.get('class') or []) if isinstance(n, Tag) else ''


def is_blank(n):
    if isinstance(n, NavigableString):
        return not n.strip()
    if n.name in ('style', 'script', 'br'):
        return True
    c = classes(n)
    if 'vc_separator' in c or 'vc_empty_space' in c:
        return True
    return n.name in ('div', 'p', 'span') and not n.get_text(strip=True) and not n.find('img')


def heading_before(n):
    """Заголовок виджета: ближайший предыдущий h2/h3 через пустые/служебные узлы."""
    p = n.previous_sibling
    junk = []
    while p is not None and is_blank(p):
        junk.append(p); p = p.previous_sibling
    if isinstance(p, Tag) and p.name in ('h2', 'h3') and RX_HEAD.search(p.get_text(' ', strip=True)):
        # и разделитель перед заголовком
        q = p.previous_sibling
        while q is not None and is_blank(q):
            junk.append(q); q = q.previous_sibling
        return p, junk
    return None, junk


def widget_root(n):
    """Поднимаемся до самого внешнего контейнера, где кроме виджета ничего нет (vc_row-обёртки)."""
    while n.parent is not None and n.parent.name in ('div', 'section') and n.parent.get('id') != '__root':
        sib = [c for c in n.parent.children if not is_blank(c)]
        if sib == [n]:
            n = n.parent
        else:
            break
    return n


import os
from collections import defaultdict
RX_SIZE = re.compile(r'^(.*?)(?:-(\d+)x(\d+))?(\.\w+)$')
IMG_ROOTS = [WAYBACK + '/images/wp-content', WAYBACK + '/images/https:/test2.prof-equip.ru/wp-content']
img_idx = defaultdict(list)  # (dir, stem, ext) -> [(width, local_path)]
for r in IMG_ROOTS:
    for dp, dn, fn in os.walk(r):
        rel = '/wp-content' + dp[len(r):]
        for f in fn:
            m = RX_SIZE.match(f)
            if m:
                img_idx[(rel, m.group(1), m.group(4).lower())].append((int(m.group(2)) if m.group(2) else 99999, os.path.join(dp, f)))
to_copy = {}  # web path -> local file (файлы не из основного кэша /wp-content нужно доложить на серверы)


def wp_file_prefix(u):
    """Запасной вариант для потерянной главной картинки: уменьшенная копия с тем же длинным именем
    (Foo-bar-baz.jpg → Foo-bar-baz-400x400-1-400x266.jpg — квадратная копия для анонса). Только для имён от 15 символов."""
    u = re.sub(r'^(?:https?:)?//[^/]+', '', u.strip())
    d, f = os.path.dirname(u), os.path.basename(u)
    m = RX_SIZE.match(f)
    if not m or len(m.group(1)) < 15:
        return None
    c = [x for k, v in img_idx.items() if k[0] == d and k[1].startswith(m.group(1) + '-400x400') for x in v if x[0] >= 300]
    if not c:
        return None
    pick = max(c)[1]
    web = d + '/' + os.path.basename(pick)
    if not pick.startswith(IMG_ROOTS[0] + '/'):
        to_copy[web] = pick
    return web


def wp_file(u):
    """/wp-content/... → web-путь доступного файла (тот же или ближайший сохранившийся размер) или None."""
    u = re.sub(r'^(?:https?:)?//[^/]+', '', u.strip())
    if not u.startswith('/wp-content/'):
        return None
    d, f = os.path.dirname(u), os.path.basename(u)
    m = RX_SIZE.match(f)
    if not m:
        return None
    want = int(m.group(2)) if m.group(2) else 99999
    c = img_idx.get((d, m.group(1), m.group(4).lower()))
    if not c:
        return None
    exact = [x for x in c if os.path.basename(x[1]) == f]
    big = [x for x in c if x[0] >= min(want, 1200)]
    pick = (exact or [min(big) if big else max(c)])[0][1]
    web = d + '/' + os.path.basename(pick)
    if not pick.startswith(IMG_ROOTS[0] + '/'):
        to_copy[web] = pick
    return web


def wp_content_images(code):
    """Картинки текста статьи в WP-снимке, по порядку (без lazy-заглушек, виджетов и служебных)."""
    f = WAYBACK + '/pages_wp_blog/' + code + '/index.html'
    if not os.path.exists(f):
        return []
    s = BeautifulSoup(open(f, errors='ignore').read(), 'html.parser')
    body = s.select_one('.entry-content') or s.select_one('#content')
    if body is None:
        return []
    for w in body.select('.owl-carousel, .portfolio-shortcode, .products-carousel-shortcode, .blog-carousel-shortcode, form, .wpforms-container'):
        w.decompose()
    out = []
    for im in body.find_all('img'):
        src = im.get('data-src') or im.get('src', '')
        if src.startswith('data:'):
            continue
        if any(k in src for k in ('/plugins/', 'Frame-2-1', 'submit-spin')):
            continue
        a = im.find_parent('a')
        if a and RX_PROD.match(a.get('href', '').strip()) or a and RX_PROJ.match(a.get('href', '').strip()):
            continue
        src = re.sub(r'^(?:https?:)?//[^/]+', '', src)
        if src not in out:
            out.append(src)
    return out


def drop_img(im):
    node = im
    parent = im.parent
    im.extract()
    while parent is not None and parent.get('id') != '__root' and parent.name in ('figure', 'picture', 'a', 'p', 'span', 'div', 'source') \
            and not parent.get_text(strip=True) and not parent.find(['img', 'iframe', 'video']):
        nxt = parent.parent
        parent.extract()
        parent = nxt


data = []
img_stat = defaultdict(int)
for e in texts:
    src = e['dt'] or ''
    s = BeautifulSoup('<div id="__root">' + src + '</div>', 'html.parser')
    root = s.find(id='__root')
    catalogs, projects, removed = [], [], []

    # 1. явные виджеты по классам (внешние, не вложенные друг в друга)
    found = []
    for n in root.find_all(True):
        c = classes(n)
        if 'gallery' in c:
            continue  # фотогалереи статей не трогаем
        if any(w in c.split() or w in c for w in WIDGET_CLS):
            if not any(f in n.parents for f in found):
                found.append(n)
    # 2. сетки товаров WPBakery: заголовок «Наш ассортимент/Наше оборудование» + следующие vc_row с /product/
    for h in root.find_all(['h2', 'h3']):
        if any(f in h.parents for f in found) or not re.match(r'^\s*Наш(е|и)?\s+(ассортимент|оборудование)', h.get_text(' ', strip=True), re.I):
            continue
        blk = h
        while blk.parent is not None and blk.parent.get('id') != '__root' and not links(blk.parent, RX_PROD):
            blk = blk.parent
        # блок = контейнер заголовка, если внутри него есть товары, иначе соседи после него
        if any(isinstance(f, Tag) and (f is blk.parent or blk.parent in f.parents or f in blk.parent.parents or any(f is x for x in blk.parent.descendants)) for f in found):
            continue  # товары под этим заголовком уже взяты как карусель
        if links(blk.parent, RX_PROD) and blk.parent.get('id') != '__root' and not re.search(r'<p[^>]*>[^<]{200,}', str(blk.parent)):
            found.append(blk.parent)
        else:
            nodes = []; nx = blk.next_sibling
            while nx is not None and (is_blank(nx) or (isinstance(nx, Tag) and links(nx, RX_PROD) and nx.name not in ('h2', 'h3', 'p'))):
                nodes.append(nx); nx = nx.next_sibling
            if any(isinstance(x, Tag) and links(x, RX_PROD) for x in nodes):
                found.append((h, nodes))

    for f in found:
        if isinstance(f, tuple):
            h, nodes = f
            title = h.get_text(' ', strip=True)
            prods = []
            for x in nodes:
                if isinstance(x, Tag):
                    prods += [p for p in links(x, RX_PROD) if p not in prods]
            kind, anchor, extra = 'catalog', h, nodes
            head, junk = None, []
        else:
            w = widget_root(f)
            c = classes(f)
            head, junk = heading_before(w)
            inner_h = f.find(['h2']) if 'blog-about' in c else None
            title = (inner_h or head).get_text(' ', strip=True) if (inner_h or head) else ''
            prods, projs = links(f, RX_PROD), links(f, RX_PROJ)
            if 'portfolio' in c or 'projects' in c or (projs and not prods):
                kind = 'projects'
            elif prods:
                kind = 'catalog'
            else:
                kind = 'other'
            anchor, extra = w, []
        ids_p = [p for p in prods if ('11', p) in active] if kind == 'catalog' else []
        ids_j = [p for p in links(f, RX_PROJ) if ('8', p) in active] if kind == 'projects' else []
        title = re.sub(r'\s*:\s*$', '', title).strip()
        if kind == 'catalog':
            catalogs.append({'title': title or 'Наш ассортимент', 'codes': ids_p, 'found': len(prods)})
        if kind == 'projects':
            projects += [i for i in ids_j if i not in projects]
        removed.append({'kind': kind, 'cls': (classes(f) if not isinstance(f, tuple) else 'grid')[:60], 'title': title, 'prods': len(prods), 'mapped': len(ids_p or ids_j)})
        # вырезаем: заголовок + служебные узлы + сам виджет; для каталога — маркер на месте
        for j in junk:
            j.extract()
        if head is not None:
            head.extract()
        for x in extra:
            x.extract()
        if kind == 'catalog':
            anchor.replace_with(BeautifulSoup('<!--BLOG_CATALOG_%d-->' % len(catalogs), 'html.parser'))
        else:
            anchor.extract()

    # больше двух товарных блоков — сливаем хвост во второй
    while len(catalogs) > 2:
        last = catalogs.pop()
        catalogs[1]['codes'] += [i for i in last['codes'] if i not in catalogs[1]['codes']]
    # картинки текста: WP — тот же файл или другой размер; потерянный Битрикс-/upload — по порядку из WP-версии статьи
    imgs = root.find_all('img')
    bx = [im for im in imgs if re.sub(r'^(?:https?:)?//[^/]+', '', im.get('src', '')).startswith('/upload/')]
    wp_seq = wp_content_images(e['code']) if bx else []
    bx_map = {id(im): wp_seq[k] for k, im in enumerate(bx)} if bx and len(bx) == len(wp_seq) else {}  # только при совпадении числа
    for im in imgs:
        u = im.get('src', '')
        if u.startswith('data:'):
            drop_img(im); img_stat['data_removed'] += 1; continue
        path = re.sub(r'^(?:https?:)?//[^/]+', '', u)
        if path.startswith('/upload/'):
            cand = bx_map.get(id(im))
            web = (wp_file(cand) or wp_file_prefix(cand)) if cand else None
            kind = 'bx'
        elif path.startswith('/wp-content/'):
            web = wp_file(path) or wp_file_prefix(path); kind = 'wp'
        else:
            img_stat['other_kept'] += 1; continue
        if web is None:
            drop_img(im); img_stat[kind + '_removed'] += 1; continue
        if web != path:
            im['src'] = web
            for a in ('srcset', 'sizes', 'data-src', 'data-srcset', 'width', 'height'):
                if a in im.attrs: del im[a]
            img_stat[kind + '_replaced'] += 1
        else:
            img_stat[kind + '_ok'] += 1
    new = root.decode_contents()
    if new == src:
        continue
    for k in range(3, 10):
        new = new.replace('<!--BLOG_CATALOG_%d-->' % k, '')
    data.append({'id': int(e['id']), 'code': e['code'], 'src_md5': hashlib.md5(src.encode()).hexdigest(),
                 'detail_text': new, 'catalogs': catalogs, 'projects': projects, 'removed': removed})

json.dump({'articles': data, 'copy': sorted(to_copy.items())}, sys.stdout, ensure_ascii=False, indent=1)
print('images', dict(img_stat), 'files to copy', len(to_copy), file=sys.stderr)
for d in data:
    if d['removed']: print(d['code'][:50], [(r['kind'], r['title'][:30], r['prods'], r['mapped']) if r['kind']!='other' else ('OTHER', r['cls']) for r in d['removed']], file=sys.stderr)
print('articles', len(data), file=sys.stderr)
