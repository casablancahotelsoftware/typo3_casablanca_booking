# Docusaurus MDX drop-in pages

These files are copied into your main Docusaurus docs site. They are **not** a
plain export of the Sphinx RST manuals: many pages include hand-edited
admonitions, tables, and fenced code blocks with explicit language tags.

## Regenerating from RST

From the extension root:

```bash
python3 Documentation/build_docusaurus_mdx.py
```

The script converts `Documentation/*.rst` to MDX but **preserves** blocks wrapped
in HTML comment markers:

```mdx
<!-- mdx:preserve:sync-logs-tab -->
### Sync logs tab
... hand-maintained content ...
<!-- /mdx:preserve:sync-logs-tab -->
```

On export, marked sections from the existing MDX file replace the matching
generated section (by heading) or admonition paragraph. Unmatched preserved
blocks are appended with a warning.

After running the exporter, review the diff before committing — especially
`03_Configuration.mdx` (Sync logs, environment mode, admonitions).

## Authoring rules

- Put prose changes in **RST first**, then run the exporter or hand-sync MDX.
- Wrap Docusaurus-only formatting (`:::note`, `:::info`, extra tables, multi-block
  code examples) in `mdx:preserve` markers so the next export does not wipe them.
- YAML frontmatter (`sidebar_position`, `title`) is kept from the existing MDX file.
