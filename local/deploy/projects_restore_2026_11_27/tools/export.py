"""Export the 'projects' iblock from an old-server mysqldump into ../projects.json and copy
its images (named by SHA-256) into an image folder outside the document root.

usage: python3 -I tools/export.py <old.sql.gz> <old upload/ dir> <image out dir> [label]
"""
import hashlib, json, os, re, shutil, sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import dumpparse  # noqa: E402

SRC, UP, IMG = sys.argv[1], sys.argv[2].rstrip("/") + "/", sys.argv[3].rstrip("/") + "/"
LABEL = sys.argv[4] if len(sys.argv) > 4 else os.path.basename(SRC)
OUT = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "projects.json")
TYPE_MAP = {"Отель": "Гостиница"}  # значение списка TYPE переименовано на новом сайте

d = dumpparse.load(SRC, {"b_iblock", "b_iblock_element", "b_iblock_element_property", "b_iblock_property",
                         "b_file", "b_iblock_property_enum", "b_iblock_iproperty", "b_iblock_element_iprop"})
ib = {b["CODE"]: b["ID"] for b in d["b_iblock"]}
ib_code = {v: k for k, v in ib.items()}
ibid = ib["projects"]
files = {f["ID"]: f for f in d["b_file"]}
el_code = {e["ID"]: (e["IBLOCK_ID"], e["CODE"]) for e in d["b_iblock_element"]}
props = {p["ID"]: p for p in d["b_iblock_property"] if p["IBLOCK_ID"] == ibid}
enums = {e["ID"]: e for e in d["b_iblock_property_enum"]}
ipt = {i["ID"]: i for i in d["b_iblock_iproperty"]}
seo = {}
for r in d["b_iblock_element_iprop"]:
    t = ipt.get(r["IPROP_ID"])
    if t and t["ENTITY_TYPE"] == "E" and t["ENTITY_ID"] == r["ELEMENT_ID"] and (t["TEMPLATE"] or "").strip():
        seo.setdefault(r["ELEMENT_ID"], {})[t["CODE"]] = t["TEMPLATE"].strip()


def php_unserialize(s):
    b, pos = s.encode("utf-8"), 0

    def val():
        nonlocal pos
        t = chr(b[pos])
        if t == "s":
            m = re.compile(rb"s:(\d+):\"").match(b, pos); n = int(m.group(1)); st = m.end()
            pos = st + n + 2
            return b[st:st + n].decode("utf-8")
        if t in "ib":
            m = re.compile(rb"[ib]:(-?\d+);").match(b, pos); pos = m.end(); return int(m.group(1))
        if t == "N":
            pos += 2; return None
        if t == "a":
            m = re.compile(rb"a:(\d+):\{").match(b, pos); pos = m.end(); out = {}
            for _ in range(int(m.group(1))):
                k = val(); out[k] = val()
            pos += 1
            return out
        raise ValueError(f"bad serialized value: {s[:60]!r}")

    return val()


def html_text(v):
    return php_unserialize(v).get("TEXT", "") if isinstance(v, str) and v.startswith("a:") else (v or "")


images = {}


def img(fid):
    f = files.get(fid)
    if not f:
        return None
    src = UP + f"{f['SUBDIR']}/{f['FILE_NAME']}"
    h = hashlib.sha256(open(src, "rb").read()).hexdigest()
    rel = f"{h[:2]}/{h}{os.path.splitext(f['FILE_NAME'])[1].lower()}"
    images[rel] = src
    return {"file": rel, "sha256": h, "name": f["ORIGINAL_NAME"] or f["FILE_NAME"]}


pv = {}
for v in sorted(d["b_iblock_element_property"], key=lambda x: int(x["ID"])):
    p = props.get(v["IBLOCK_PROPERTY_ID"])
    if p:
        pv.setdefault(v["IBLOCK_ELEMENT_ID"], {}).setdefault(p["CODE"], []).append(v)

out = []
for e in d["b_iblock_element"]:
    if e["IBLOCK_ID"] != ibid:
        continue
    pr = pv.get(e["ID"], {})
    row = {
        "code": e["CODE"], "name": e["NAME"], "old_active": e["ACTIVE"], "old_sort": int(e["SORT"]),
        "old_active_from": e["ACTIVE_FROM"],
        "preview_text": e["PREVIEW_TEXT"] or "", "preview_text_type": e["PREVIEW_TEXT_TYPE"] or "text",
        "detail_text": e["DETAIL_TEXT"] or "", "detail_text_type": e["DETAIL_TEXT_TYPE"] or "html",
        "preview_picture": img(e["PREVIEW_PICTURE"]), "detail_picture": img(e["DETAIL_PICTURE"]),
        "seo": seo.get(e["ID"], {}),
        "type": [TYPE_MAP.get(enums[x["VALUE"]]["VALUE"], enums[x["VALUE"]]["VALUE"]) for x in pr.get("TYPE", []) if x["VALUE"] in enums],
        "list": [{"value": html_text(x["VALUE"]).strip(), "description": x["DESCRIPTION"] or ""} for x in pr.get("LIST", []) if html_text(x["VALUE"]).strip()],
        "blocks": [{"html": html_text(x["VALUE"]), "title": x["DESCRIPTION"] or ""} for x in pr.get("BLOCKS", []) if html_text(x["VALUE"]).strip()],
        "gallery": [g for g in (img(x["VALUE"]) for x in pr.get("GALLERY", [])) if g],
        "refs": {k: [el_code[x["VALUE"]][1] for x in pr[k] if x["VALUE"] in el_code] for k in ("CATALOG_PRODUCT_1", "CATALOG_PRODUCT_2", "BRANDS") if pr.get(k)},
        "strings": {k: pr[k][0]["VALUE"] for k in ("CITY", "INFO_TITLE", "TITLE_CATALOG_1", "TITLE_CATALOG_2", "TITLE_BRANDS", "CARD_META", "CARD_TEXT", "CARD_SCOPE") if pr.get(k) and (pr[k][0]["VALUE"] or "").strip()},
    }
    out.append(row)

for rel, src in images.items():
    dst = IMG + rel
    if not os.path.exists(dst):
        os.makedirs(os.path.dirname(dst), exist_ok=True)
        shutil.copy2(src, dst)
json.dump({"source": LABEL, "type_map": TYPE_MAP, "projects": sorted(out, key=lambda r: r["code"])},
          open(OUT, "w", encoding="utf-8"), ensure_ascii=False, indent=1)
print(f"{len(out)} projects, {len(images)} images -> {OUT}")
