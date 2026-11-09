"""Собирает data.json + img/ для local/deploy/solutions_content_2026_11_09 из снимков веб-архива.
Bitrix-снимки (bx/) дают точный прод-контент; WP-снимки (wp/) — картинки (по порядку) и текст для страниц без Bitrix-снимка."""
import re, os, json, time, urllib.request, html as H, sys

SOL = '/private/tmp/claude-501/sol'
OUT = sys.argv[1]
ARCH = os.path.expanduser('~/Documents/prof-equip-local/archive/wayback/images/wp-content/uploads/')
os.makedirs(OUT + '/img', exist_ok=True)
CDX = {l.split()[0].split('/uploads/')[-1] for l in open(SOL + '/cdx_imgs.txt') if l.strip()}


def sect(h, cls):
    i = h.find(f'<div class="{cls}')
    if i < 0:
        return None
    d = 0
    for m in re.finditer(r'<div\b|</div>', h[i:]):
        d += 1 if m.group(0) == '<div' else -1
        if d == 0:
            return h[i:i + m.end()]


def inner(block):
    return block[block.find('>') + 1:block.rfind('</div>')].strip()


def fetch(url):
    for a in range(3):
        try:
            r = urllib.request.urlopen(url, timeout=90)
            d = r.read()
            if r.headers.get('Content-Type', '').startswith('image') and len(d) > 2000:
                return d
            return None
        except urllib.error.HTTPError as e:
            if e.code == 404:
                return None
            time.sleep(4)
        except Exception:
            time.sleep(4)


def wp_images(w):
    """[(rel_path_candidates, ...)] в порядке страницы; rel относительно uploads/."""
    i = w.find('<div class="entry-content">')
    j = w.find('Рассчитать проект', i)
    c = w[i:j]
    res = []
    for m in re.finditer(r'(?:<a [^>]*href="([^"]+)"[^>]*>\s*)?<img([^>]+)>', c):
        a = m.group(2)
        src = re.search(r'\bsrc="([^"]+)"', a).group(1)
        if 'wp-content/uploads' not in src:
            ds = re.search(r'data-src="([^"]+)"', a) or re.search(r'data-lazy-src="([^"]+)"', a)
            if ds and 'wp-content/uploads' in ds.group(1):
                src = ds.group(1)
            else:
                continue
        cands = []
        rel = src.split('/uploads/')[-1]
        orig = re.sub(r'-\d+x\d+(\.\w+)$', r'\1', rel)
        cands.append(orig)
        ss = re.search(r'srcset="([^"]+)"', a)
        if ss:
            best = max(((int(x.split()[1][:-1]), x.split()[0]) for x in ss.group(1).split(',') if len(x.split()) == 2 and x.split()[1][:-1].isdigit()), default=None)
            if best:
                cands.append(best[1].split('/uploads/')[-1])
        if m.group(1) and '/uploads/' in m.group(1):
            cands.append(m.group(1).split('/uploads/')[-1])
        cands.append(rel)
        seen = []
        [seen.append(x) for x in cands if x not in seen]
        res.append(seen)
    return res


def get_image(slug, cands, idx):
    os.makedirs(f'{OUT}/img/{slug}', exist_ok=True)
    for rel in cands:
        name = f'{idx:02d}-' + os.path.basename(rel).lower()
        dst = f'{OUT}/img/{slug}/{name}'
        if os.path.exists(dst):
            return name
        p = ARCH + rel
        if os.path.exists(p) and os.path.getsize(p) > 2000:
            open(dst, 'wb').write(open(p, 'rb').read())
            return name
        if rel not in CDX:
            continue
        d = fetch(f'http://web.archive.org/web/2026id_/https://prof-equip.ru/wp-content/uploads/{rel}')
        if d:
            open(dst, 'wb').write(d)
            return name
    print('  !! нет картинки', cands, file=sys.stderr)
    return None


def clean_img_wrappers(s):
    # отрендеренный шаблоном <div class="image-wrapper"><picture><img></picture></div> -> <img> (processImages обернёт сам)
    s = re.sub(r'<div class="image-wrapper">\s*<picture>\s*(<img[^>]*>)\s*</picture>\s*</div>', r'\1', s)
    s = s.replace('<span id="bx-cursor-node"> </span>', '')
    s = re.sub(r'https?://prof-equip\.ru/', '/', s)
    return s.strip()


IMG_TOKEN = re.compile(r'<img[^>]*src="/upload/medialibrary/[^"]+"[^>]*>')


def from_bitrix(slug, h, imgs):
    pv = clean_img_wrappers(inner(sect(h, 'complete-solution__preview')))
    g = sect(h, 'text-block__images-swiper')
    ngal = len(re.findall(r'swiper-slide', g or ''))
    tbh = sect(h, 'text-block__info') or ''
    blocks = []
    for m in re.finditer(r'<h2 class="info__title">(.*?)</h2>\s*<div class="info__content">(.*?)</div>\s*</div>', tbh, re.S):
        blocks.append([H.unescape(m.group(1)).strip(), clean_img_wrappers(m.group(2))])
    dt = sect(h, 'complete-solution__detail')
    dt = clean_img_wrappers(inner(dt)) if dt else ''
    # порядок картинок на странице: preview, gallery, text_blocks, detail
    n_pv = len(IMG_TOKEN.findall(pv)); n_tb = sum(len(IMG_TOKEN.findall(b[1])) for b in blocks); n_dt = len(IMG_TOKEN.findall(dt))
    total = n_pv + ngal + n_tb + n_dt
    if total != len(imgs):
        print(f'  {slug}: bitrix картинок {total} (gal {ngal}), WP {len(imgs)} -> лишние WP в галерею', file=sys.stderr)
        ngal += len(imgs) - total
    it = iter(imgs)
    sub = lambda s: IMG_TOKEN.sub(lambda m: f'<img src="{{IMG:{next(it)}}}">', s)
    pv = sub(pv)
    gal = [x for x in (next(it) for _ in range(ngal)) if x]
    for b in blocks:
        b[1] = sub(b[1])
    dt = sub(dt)
    return dict(preview=pv, gallery=gal, blocks=blocks, detail=dt, source='bitrix')


BLOCK_RE = re.compile(r'<table\b.*?</table>|<ul\b.*?</ul>|<ol\b.*?</ol>|<h[1-6]\b.*?</h[1-6]>|<p\b.*?</p>|<img\b[^>]*>', re.S)


def tidy(x):
    x = re.sub(r'\s(class|style|id|data-[\w-]+|target|rel|title|decoding|loading|fetchpriority|sizes|srcset)="[^"]*"', '', x)
    x = re.sub(r'https?://prof-equip\.ru/', '/', x)
    x = re.sub(r'</?span>', '', x)
    x = re.sub(r'\s+', ' ', x).replace('> <', '><')
    return x.strip()


def from_wp(slug, w, imgs):
    i = w.find('<div class="entry-content">')
    j = w.find('Рассчитать проект', i)
    c = re.sub(r'<script.*?</script>|<style.*?</style>|<noscript>.*?</noscript>', '', w[i:j], flags=re.S)
    seq = []
    k = 0
    for m in BLOCK_RE.finditer(c):
        x = m.group(0)
        if x.startswith('<img'):
            if 'wp-content/uploads' not in x:
                continue
            seq.append(('img', imgs[k])); k += 1
        else:
            t = re.sub(r'<[^>]+>', '', x).strip()
            if not t.replace('&nbsp;', '').strip():
                continue
            seq.append(('txt', tidy(x)))
    plain = lambda x: H.unescape(re.sub(r'<[^>]+>', '', x)).strip()
    # химчистка: дубль заголовка и картинка перед текстом
    seq = [s for s in seq if not (s[0] == 'txt' and re.match(r'<h\d', s[1]) and plain(s[1]) in ('Открыть химчистку',))]
    kitchen = any(s[0] == 'txt' and plain(s[1]) in ('ОПИСАНИЕ ПРОЕКТА', 'ВАЖНО ЗНАТЬ', 'ОСНОВНАЯ КУХНЯ') for s in seq)
    img = lambda n: f'<img src="{{IMG:{n}}}">'
    if kitchen:
        pv, gal, blocks, dt = [], [], [], []
        stage = 'pv'
        cur = None
        for typ, v in seq:
            if typ == 'img':
                if stage in ('pv', 'gal'):
                    stage = 'gal'; gal.append(v)
                else:  # план — отдельный блок без заголовка (как на Bitrix-проде)
                    blocks.append(['', img(v)]); cur = None
                continue
            p = plain(v)
            if stage == 'pv':
                pv.append(v); continue
            if p.isupper() and len(p) < 60:
                cur = [p, '']; blocks.append(cur); stage = 'tb'; continue
            if p.startswith('В запросе') or p.startswith('Получите индивидуальные') or stage == 'dt':
                dt.append(v); stage = 'dt'; continue
            if cur is None:
                cur = ['', '']; blocks.append(cur)
            cur[1] += v
        return dict(preview=''.join(pv), gallery=gal, blocks=blocks, detail=''.join(dt), source='wp')
    # прачечные: вступление (+ первая картинка) в preview, остальное — в detail, картинки инлайн
    first_txt = next(n for n, s in enumerate(seq) if s[0] == 'txt')
    lead = [s for s in seq[:first_txt] if s[0] == 'img']
    seq = seq[first_txt:]
    pv, dt = [], []
    n = 0
    while n < len(seq) and seq[n][0] == 'txt':
        pv.append(seq[n][1]); n += 1
        if plain(pv[-1]).startswith('Предлагаем Вам ознакомиться'):
            break
    if lead:
        pv.append(img(lead[0][1]))
    elif n < len(seq) and seq[n][0] == 'img':
        pv.append(img(seq[n][1])); n += 1
    for typ, v in seq[n:]:
        dt.append(img(v) if typ == 'img' else v)
    return dict(preview=''.join(pv), gallery=[], blocks=[], detail=''.join(dt), source='wp')


def drop_missing(x):
    x = x.replace('<img src="{IMG:None}">', '')
    # подпись схемы без самой схемы
    x = re.sub(r'<p>\s*(?:<strong>)?\s*Схема расположения оборудования:?\s*(?:</strong>)?\s*</p>(?!\s*(?:<p>)?\s*<img)', '', x)
    x = re.sub(r'<p>\s*(?:<br>\s*)*</p>', '', x)
    x = re.sub(r'(?:<br>\s*){3,}', '<br>', x)
    return x.strip()


LINK_FIX = {
    '/finishnoe-oborudovanie/': '/product-category/finishnoe-oborudovanie/',
    '/product/professionalnyj-gladilnyj-katok-jensen-alpha-i5-s-gibkoj-muldoj/': '/product/professionalnyj-gladilnyj-katok-alpha-i5-s-gibkoj-muldoj/',
    '/product/professionalnaya-stiralnaya-mashina-tolon-twe-20-zagruzka-20-kg/': None,  # товара нет — снять ссылку
}


def fix_links(x):
    for old, new in LINK_FIX.items():
        if new:
            x = x.replace(f'href="{old}"', f'href="{new}"')
        else:
            x = re.sub(r'<a href="' + re.escape(old) + r'"[^>]*>(.*?)</a>', r'\1', x, flags=re.S)
    return x


def finalize(d):
    for k in ('preview', 'detail'):
        d[k] = fix_links(d[k])
    d['blocks'] = [[t, fix_links(c)] for t, c in d['blocks']]
    d['gallery'] = [g for g in d['gallery'] if g]
    d['preview'] = drop_missing(d['preview'])
    d['detail'] = drop_missing(d['detail'])
    d['blocks'] = [[t, drop_missing(c)] for t, c in d['blocks']]
    d['blocks'] = [b for b in d['blocks'] if re.sub(r'<[^>]+>|\s|&nbsp;', '', b[1]) or '<img' in b[1]]
    return d


data = {}
for slug in sorted(f[:-5] for f in os.listdir(SOL + '/wp')):
    w = open(f'{SOL}/wp/{slug}.html', encoding='utf8', errors='ignore').read()
    cands = wp_images(w)
    imgs = [get_image(slug, c, i + 1) for i, c in enumerate(cands)]
    bx = f'{SOL}/bx/{slug}.html'
    if os.path.exists(bx):
        d = from_bitrix(slug, open(bx, encoding='utf8', errors='ignore').read(), imgs)
    else:
        d = from_wp(slug, w, imgs)
    d = finalize(d)
    data[slug] = d
    print(slug, d['source'], 'imgs', len(imgs), 'gal', len(d['gallery']), 'blocks', len(d['blocks']),
          'pv', len(d['preview']), 'dt', len(d['detail']), file=sys.stderr)
json.dump(data, open(OUT + '/data.json', 'w', encoding='utf8'), ensure_ascii=False, indent=1)
