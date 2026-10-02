"""Разбор docx SAYL: заголовок -> шапка -> «Описание» -> «Характеристики». Терпим к склеенным строкам."""
import re, unicodedata
import docx

def clean(s):
    s = unicodedata.normalize('NFC', s).replace('\xa0', ' ').replace('**', '')
    s = re.sub(r'[ \t]+', ' ', s)
    return s.strip()

def lines_of(p):
    return [clean(x) for x in p.text.split('\n')]

HEADING_RE = re.compile(r'^(?:\d+\.\s*)?((?:ИННОВАЦИОННАЯ|ПРОФЕССИОНАЛЬНАЯ)\s.*\bSAYL\b.*)$')

def heading_text(p):
    t = clean(p.text)
    m = HEADING_RE.match(t)
    if m and ':' not in t and '\n' not in p.text and t.upper() == t:
        return m.group(1)
    return None

def parse(path):
    d = docx.Document(path)
    models, cur, mode = [], None, None
    for p in d.paragraphs:
        h = heading_text(p)
        if h:
            cur = {'heading': h, 'head': [], 'desc': [], 'specs': []}
            models.append(cur); mode = 'head'; continue
        if cur is None:
            continue
        t = clean(p.text)
        if t.lower() == 'описание':
            mode = 'desc'; continue
        if t.lower() == 'характеристики':
            mode = 'specs'; continue
        if not t:
            continue
        if mode == 'desc':
            # абзац описания: склеенные через \n части считаем отдельными абзацами
            cur['desc'].extend([x for x in lines_of(p) if x])
        else:
            for x in lines_of(p):
                if not x:
                    continue
                if ':' in x:
                    k, v = x.split(':', 1)
                    cur[mode].append([k.strip(), v.strip()])
                else:
                    cur[mode].append(['', x])
    return models
