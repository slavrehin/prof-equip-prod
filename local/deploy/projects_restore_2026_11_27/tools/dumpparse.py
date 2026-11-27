"""Minimal mysqldump (.sql.gz) reader for selected tables. Used by export.py."""
import gzip, re


def parse_values(s, i):
    n = len(s)
    rows = []
    while i < n:
        while i < n and s[i] in ", \n":
            i += 1
        if i >= n or s[i] != "(":
            break
        i += 1
        row = []
        while True:
            c = s[i]
            if c == "'":
                i += 1
                buf = []
                while True:
                    c = s[i]
                    if c == "\\":
                        nx = s[i + 1]
                        buf.append({"n": "\n", "r": "\r", "t": "\t", "0": "\0", "Z": "\x1a"}.get(nx, nx))
                        i += 2
                    elif c == "'":
                        if s[i + 1] == "'":
                            buf.append("'"); i += 2
                        else:
                            i += 1; break
                    else:
                        buf.append(c); i += 1
                row.append("".join(buf))
            else:
                j = i
                while s[j] not in ",)":
                    j += 1
                tok = s[i:j].strip()
                row.append(None if tok == "NULL" else tok)
                i = j
            if s[i] == ",":
                i += 1
                continue
            if s[i] == ")":
                i += 1
                break
        rows.append(row)
    return rows


def load(path, want):
    cols, data, cur = {}, {t: [] for t in want}, None

    def statements(f):
        buf = None
        for line in f:
            if buf is not None:
                buf.append(line)
                if line.rstrip().endswith(";"):
                    yield "".join(buf); buf = None
                continue
            m = re.match(r"INSERT INTO `(\w+)`", line)
            if m and m.group(1) in want and not line.rstrip().endswith(";"):
                buf = [line]
                continue
            yield line

    with gzip.open(path, "rt", encoding="utf-8", errors="replace") as f:
        for line in statements(f):
            m = re.match(r"CREATE TABLE `(\w+)`", line)
            if m:
                cur = m.group(1) if m.group(1) in want else None
                if cur:
                    cols[cur] = []
                continue
            if cur and line.startswith("  `"):
                cols[cur].append(line.split("`")[1])
                continue
            if line.startswith(")"):
                cur = None
            m = re.match(r"INSERT INTO `(\w+)` (?:\(([^)]*)\) )?VALUES\s*", line)
            if m and m.group(1) in want:
                if m.group(2):
                    cols[m.group(1)] = [c.strip(" `") for c in m.group(2).split(",")]
                data[m.group(1)].extend(parse_values(line, m.end()))
    return {t: [dict(zip(cols[t], r)) for r in data[t]] for t in want if t in cols}
