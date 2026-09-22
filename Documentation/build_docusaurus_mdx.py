#!/usr/bin/env python3
"""Convert extension RST manuals to Docusaurus MDX drop-in files.

Writes to docusaurus/typo_ext/ and preserves existing YAML frontmatter.
After running, review the diff. Hand-maintained blocks wrapped in
``<!-- mdx:preserve:NAME -->`` markers in docusaurus/typo_ext/*.mdx are
spliced back into the export (admonitions, Sync logs table, code fences).
See docusaurus/typo_ext/README.md.
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

DOC_ROOT = Path(__file__).resolve().parent
EXT_ROOT = DOC_ROOT.parent
OUT_ROOT = EXT_ROOT / "docusaurus" / "typo_ext"

DOC_MAP: dict[str, str] = {
    "Introduction/Index.rst": "typo_ext.mdx",
    "Index.rst": "_readme.mdx",
    "Installation/Index.rst": "02_Installation.mdx",
    "Configuration/Index.rst": "03_Configuration.mdx",
    "ContentElements/Index.rst": "04_ContentElements.mdx",
    "Localization/Index.rst": "05_Localization.mdx",
    "Sync/Index.rst": "06_Sync.mdx",
    "Theming/Index.rst": "07_Theming.mdx",
    "Reference/Index.rst": "08_Reference.mdx",
}

DOC_LINKS: dict[str, tuple[str, str]] = {
    "../Installation/Index": ("Installation", "./02_Installation.mdx"),
    "../Configuration/Index": ("Configuration", "./03_Configuration.mdx"),
    "../ContentElements/Index": ("Content elements", "./04_ContentElements.mdx"),
    "../Localization/Index": ("Localization", "./05_Localization.mdx"),
    "../Sync/Index": ("Background sync", "./06_Sync.mdx"),
    "../Theming/Index": ("Theming", "./07_Theming.mdx"),
    "../Reference/Index": ("Reference", "./08_Reference.mdx"),
    "../Introduction/Index": ("Introduction", "./typo_ext.mdx"),
    "Installation/Index": ("Installation", "./02_Installation.mdx"),
    "Configuration/Index": ("Configuration", "./03_Configuration.mdx"),
    "ContentElements/Index": ("Content elements", "./04_ContentElements.mdx"),
    "Localization/Index": ("Localization", "./05_Localization.mdx"),
    "Sync/Index": ("Background sync", "./06_Sync.mdx"),
    "Theming/Index": ("Theming", "./07_Theming.mdx"),
    "Reference/Index": ("Reference", "./08_Reference.mdx"),
    "Introduction/Index": ("Introduction", "./typo_ext.mdx"),
}

SKIP_SECTION_TITLES = {"Backend module"}

CHAPTER_TITLES = {
    "Introduction",
    "Installation",
    "Configuration",
    "Content elements",
    "Localization",
    "Reference",
    "Sync",
    "Theming",
    "CASABLANCA Booking Engine",
}

FRONTMATTER_RE = re.compile(r"^---\s*\n.*?\n---\s*\n?", re.DOTALL)


def strip_rst_directives(lines: list[str]) -> list[str]:
    out: list[str] = []
    skip_block = False
    for line in lines:
        stripped = line.strip()
        if stripped.startswith(".. toctree::"):
            skip_block = True
            continue
        if stripped.startswith(".. ") and not stripped.startswith(".. |"):
            if stripped.endswith("::"):
                skip_block = True
            continue
        if skip_block:
            if stripped == "" or line.startswith("   ") or line.startswith("\t"):
                continue
            skip_block = False
        if stripped.startswith(".. |"):
            continue
        if re.match(r":\w+:", stripped):
            continue
        if stripped.startswith("|Rendered:") or stripped.startswith("|Author:"):
            continue
        if stripped == "----":
            continue
        out.append(line)
    return out


def convert_doc_link(target: str) -> str:
    target = target.strip()
    if target in DOC_LINKS:
        label, href = DOC_LINKS[target]
        return f"[{label}]({href})"
    return target


def convert_inline(text: str) -> str:
    def doc_repl(match: re.Match[str]) -> str:
        return convert_doc_link(match.group(1))

    text = re.sub(r":doc:`([^`]+)`", doc_repl, text)
    text = re.sub(r":ref:`([^`]+)`", r"**\1**", text)
    text = re.sub(r"`([^`]+) <([^>]+)>`__", r"[\1](\2)", text)
    text = re.sub(r"``([^`]+)``", r"`\1`", text)
    return text


def is_table_line(line: str) -> bool:
    s = line.strip()
    return s.startswith("+") or (s.startswith("|") and s.endswith("|"))


def convert_table(lines: list[str], start: int) -> tuple[list[str], int]:
    block: list[str] = []
    i = start
    while i < len(lines) and is_table_line(lines[i]):
        block.append(lines[i].rstrip())
        i += 1

    rows: list[list[str]] = []
    for line in block:
        if not line.strip().startswith("|"):
            continue
        cells = [c.strip() for c in line.strip().strip("|").split("|")]
        rows.append(cells)

    if not rows:
        return [lines[start]], start + 1

    merged: list[list[str]] = []
    for row in rows:
        if merged and row and row[0] == "":
            prev = merged[-1]
            for idx, cell in enumerate(row):
                if cell and idx < len(prev):
                    prev[idx] = (prev[idx] + " " + cell).strip()
            continue
        merged.append(row)

    md: list[str] = []
    header = merged[0]
    md.append("| " + " | ".join(convert_inline(c) for c in header) + " |")
    md.append("| " + " | ".join("---" for _ in header) + " |")
    for row in merged[1:]:
        if len(row) < len(header):
            row += [""] * (len(header) - len(row))
        md.append("| " + " | ".join(convert_inline(c) for c in row[: len(header)]) + " |")
    md.append("")
    return md, i


def heading_level(next_line: str) -> int:
    if not next_line:
        return 0
    chars = set(next_line.strip())
    if chars <= {"="}:
        return 1
    if chars <= {"-"}:
        return 2
    if chars <= {"~"}:
        return 3
    if chars <= {"^"}:
        return 4
    return 0


def mdx_heading(level: int, title: str, chapter_title: str) -> str | None:
    if level == 1 and title == chapter_title:
        return None
    if level == 1 and title in CHAPTER_TITLES:
        return None
    if level == 1 and title in SKIP_SECTION_TITLES:
        return None
    hashes = {1: "##", 2: "###", 3: "####", 4: "#####"}.get(level, "#####")
    return f"{hashes} {convert_inline(title)}"


def convert_index_rst(text: str) -> str:
    lines = text.splitlines()
    out: list[str] = [
        "# CASABLANCA Booking Engine",
        "",
        "**Extension key:** `casablanca_booking`  ",
        "**Package name:** `casablanca/bookingengine-connector`  ",
        "**Version:** 1.0.9  ",
        "**Language:** en",
        "",
        "**Author:** Martin Hairer  ",
        "**Copyright:** CASABLANCA hotelsoftware GmbH",
        "",
        "Licensed under the **GNU Affero General Public License v3.0 only** (AGPL-3.0-only). "
        "See the `LICENSE` file in the extension root.",
        "",
        "Integrates the CASABLANCA Booking Engine (BE v2) into TYPO3 with "
        "SSR widgets, scheduled ARI synchronisation, and PCI-safe redirect handover.",
        "",
    ]
    return "\n".join(out).strip() + "\n"


def detect_chapter_title(lines: list[str]) -> str:
    if len(lines) >= 2 and heading_level(lines[1].strip()) == 1:
        return lines[0].strip()
    return ""


CODE_BLOCK_STARTS = (
    "composer ",
    "vendor/bin/",
    "docker run",
    "plugin.tx_",
    "casablanca_booking:",
    "casablancaBooking:",
    ".cb-",
    ".site-header",
    "<section",
    "<",
    "/*",
    "?arrival",
)


def read_indented_code_block(lines: list[str], start: int) -> tuple[list[str], int] | None:
    if start >= len(lines) or not lines[start].startswith("   "):
        return None
    block: list[str] = []
    i = start
    while i < len(lines) and lines[i].startswith("   "):
        block.append(lines[i][3:] if lines[i].startswith("   ") else lines[i].lstrip())
        i += 1
    if not block:
        return None
    first = block[0].lstrip()
    if not first.startswith(CODE_BLOCK_STARTS):
        return None
    return block, i


def convert_rst(text: str) -> str:
    lines = strip_rst_directives(text.splitlines())
    chapter_title = detect_chapter_title(lines)
    out: list[str] = []
    i = 0
    in_code = False
    code_lang = ""

    while i < len(lines):
        line = lines[i]
        stripped = line.strip()

        if stripped.startswith(".. code-block::"):
            lang = stripped.split("::")[-1].strip() or ""
            out.append(f"```{lang}")
            in_code = True
            code_lang = lang
            i += 1
            continue

        if in_code:
            if stripped == "" and i + 1 < len(lines) and not lines[i + 1].startswith("   "):
                out.append("```")
                out.append("")
                in_code = False
                code_lang = ""
                i += 1
                continue
            out.append(line[4:] if line.startswith("   ") else line)
            i += 1
            continue

        code_block = read_indented_code_block(lines, i)
        if code_block is not None and not is_table_line(line):
            block, i = code_block
            content = "\n".join(block).strip()
            lang = "bash" if content.startswith(("composer ", "vendor/bin/")) else ""
            if content.startswith("?"):
                lang = "text"
            elif content.startswith(("casablanca_booking:", "casablancaBooking:")):
                lang = "yaml"
            elif content.startswith("plugin.tx_"):
                lang = "typoscript"
            elif content.startswith((".cb-", ".site-header", "/*")):
                lang = "css"
            elif content.startswith("<"):
                lang = "html"
            out.append(f"```{lang}")
            out.append(content)
            out.append("```")
            out.append("")
            continue

        if is_table_line(line):
            md_table, i = convert_table(lines, i)
            out.extend(md_table)
            continue

        if i + 1 < len(lines):
            level = heading_level(lines[i + 1].strip())
            if level:
                heading = mdx_heading(level, stripped, chapter_title)
                if heading:
                    out.append(heading)
                    out.append("")
                i += 2
                continue

        if stripped.startswith("* ") or stripped.startswith("- "):
            out.append(convert_inline(stripped))
            i += 1
            continue

        if stripped == "":
            out.append("")
            i += 1
            continue

        out.append(convert_inline(line))
        i += 1

    if in_code:
        out.append("```")

    body = "\n".join(out)
    body = re.sub(r"\n{3,}", "\n\n", body)
    return post_process(body.strip() + "\n")


def post_process(body: str) -> str:
    # Fix double-hash hex colours produced by MDX escaping mistakes.
    body = re.sub(r"`##([0-9a-fA-F]{3,8})`", r"`#\1`", body)
    body = re.sub(r"(#[0-9a-fA-F]{3,8})", lambda m: m.group(1), body)

    # Remove horizontal rules immediately after headings.
    body = re.sub(r"(^#{2,4} .+)\n---\n", r"\1\n\n", body, flags=re.MULTILINE)
    body = re.sub(r"\n---\n(?=\n)", "\n\n", body)

    # Promote blockquoted CLI lines to fenced bash blocks.
    def blockquote_to_bash(match: re.Match[str]) -> str:
        lines = [ln[2:] if ln.startswith("> ") else ln.lstrip(">") for ln in match.group(0).splitlines()]
        content = "\n".join(lines).strip()
        if content.startswith("vendor/bin/") or content.startswith("composer "):
            return f"```bash\n{content}\n```"
        if content.startswith("plugin.tx_") or content.startswith("casablancaBooking"):
            return f"```typoscript\n{content}\n```"
        if content.startswith("?arrivalDate"):
            return f"```text\n{content}\n```"
        return match.group(0)

    body = re.sub(r"(?:^> .+\n?)+", blockquote_to_bash, body, flags=re.MULTILINE)

    body = re.sub(r"\n{3,}", "\n\n", body)
    return body


def read_frontmatter(path: Path) -> str:
    if not path.is_file():
        return ""
    text = path.read_text(encoding="utf-8")
    match = FRONTMATTER_RE.match(text)
    if not match:
        return ""
    return match.group(0).rstrip() + "\n"


def read_existing_body(path: Path) -> str:
    if not path.is_file():
        return ""
    text = path.read_text(encoding="utf-8")
    return FRONTMATTER_RE.sub("", text, count=1)


PRESERVE_START_RE = re.compile(r"^<!-- mdx:preserve:([\w-]+) -->$")
PRESERVE_END_RE = re.compile(r"^<!-- /mdx:preserve:([\w-]+) -->$")


def extract_preserved_blocks(text: str) -> dict[str, str]:
    blocks: dict[str, str] = {}
    lines = text.splitlines()
    i = 0
    while i < len(lines):
        start_match = PRESERVE_START_RE.match(lines[i].strip())
        if not start_match:
            i += 1
            continue

        name = start_match.group(1)
        block_lines = [lines[i]]
        i += 1
        while i < len(lines):
            block_lines.append(lines[i])
            end_match = PRESERVE_END_RE.match(lines[i].strip())
            if end_match and end_match.group(1) == name:
                break
            i += 1

        blocks[name] = "\n".join(block_lines).rstrip() + "\n"
        i += 1

    return blocks


def strip_preserve_wrapper(block: str) -> str:
    lines = block.splitlines()
    if lines and PRESERVE_START_RE.match(lines[0].strip()):
        lines = lines[1:]
    if lines and PRESERVE_END_RE.match(lines[-1].strip()):
        lines = lines[:-1]
    return "\n".join(lines).strip()


def replace_section_by_heading(body: str, preserved_block: str) -> tuple[str, bool]:
    heading_match = re.search(r"^(#{2,4}) (.+)$", preserved_block, re.MULTILINE)
    if not heading_match:
        return body, False

    level = len(heading_match.group(1))
    heading_line = heading_match.group(0)
    pattern = re.compile(
        rf"^{re.escape(heading_line)}\n.*?(?=^#{{1,{level}}} \S|\Z)",
        re.MULTILINE | re.DOTALL,
    )
    if not pattern.search(body):
        return body, False

    return pattern.sub(preserved_block.rstrip() + "\n\n", body, count=1), True


def merge_by_exact_content(body: str, preserved_block: str) -> tuple[str, bool]:
    inner = strip_preserve_wrapper(preserved_block).strip()
    if len(inner) < 20 or inner not in body:
        return body, False

    return body.replace(inner, preserved_block.rstrip(), 1), True


def merge_admonition_block(body: str, preserved_block: str) -> tuple[str, bool]:
    inner = strip_preserve_wrapper(preserved_block)
    if not inner.startswith(":::"):
        return body, False

    content_lines = [line for line in inner.splitlines() if line and not line.startswith(":::")]
    if not content_lines:
        return body, False

    needle = content_lines[0]
    if needle not in body:
        return body, False

    pattern = re.compile(re.escape(needle) + r".*?(?=\n\n|\Z)", re.DOTALL)
    return pattern.sub(preserved_block.rstrip() + "\n", body, count=1), True


def merge_preserved_blocks(body: str, preserved: dict[str, str], dst_name: str) -> str:
    if not preserved:
        return body

    for name, block in preserved.items():
        merged, ok = replace_section_by_heading(body, block)
        if ok:
            body = merged
            continue

        merged, ok = merge_admonition_block(body, block)
        if ok:
            body = merged
            continue

        merged, ok = merge_by_exact_content(body, block)
        if ok:
            body = merged
            continue

        if f"<!-- mdx:preserve:{name} -->" in body:
            continue

        print(
            f"warn: preserved block '{name}' in {dst_name} had no match; appending",
            file=sys.stderr,
        )
        body = body.rstrip() + "\n\n" + block

    body = re.sub(r"\n{3,}", "\n\n", body)
    return body.strip() + "\n"


def main() -> int:
    OUT_ROOT.mkdir(parents=True, exist_ok=True)

    for src_rel, dst_name in DOC_MAP.items():
        src = DOC_ROOT / src_rel
        dst = OUT_ROOT / dst_name
        if not src.is_file():
            print(f"skip missing: {src_rel}", file=sys.stderr)
            continue

        frontmatter = read_frontmatter(dst)
        preserved = extract_preserved_blocks(read_existing_body(dst))
        if src_rel == "Index.rst":
            body = convert_index_rst(src.read_text(encoding="utf-8"))
        else:
            body = convert_rst(src.read_text(encoding="utf-8"))
        body = merge_preserved_blocks(body, preserved, dst_name)

        content = frontmatter + body if frontmatter else body
        dst.write_text(content, encoding="utf-8")
        print(f"wrote docusaurus/typo_ext/{dst_name}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
