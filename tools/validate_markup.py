#!/usr/bin/env python3
"""Validate rendered HTML for the failure class that HTTP 200 cannot see.

The favicon regression rendered a perfectly valid 200 with clean PHP: an
icon() call was substituted inside an href="" value, its quotes closed the
attribute early, and the remainder of the tag spilled into the document as
the visible text `">`. Status codes and PHP error logs are both blind to
that. These checks are not.

  1. attribute-escape debris — a fragment like `">` or `"/>` appearing as
     text content, which is what a broken-out attribute leaves behind
  2. markup inside an attribute value — `<` or `>` where a value should be
  3. unbalanced quotes within a start tag
  4. raw PHP left in the output — `<?` surviving into the response
  5. stray tag-like text nodes

Usage:  validate_markup.py <file-or-'-'> [label]
Exit 1 on any finding.
"""
import sys, re
from html.parser import HTMLParser

SKIP_TAGS = {"script", "style"}


class Checker(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.findings = []
        self.stack = []

    def handle_starttag(self, tag, attrs):
        self.stack.append(tag)
        for name, val in attrs:
            if val is None:
                continue
            # on* handlers hold JavaScript: `=>`, `<`, quotes are all normal
            # there. URL-bearing attributes are where broken-out markup shows.
            if name.lower().startswith("on"):
                continue
            if "<" in val or ">" in val:
                # an SVG data: URI legitimately contains encoded markup, but
                # only in %3C/%3E form — a literal angle bracket is debris
                self.findings.append(
                    f"markup inside attribute {tag}[{name}]: {val[:70]!r}")

    def handle_endtag(self, tag):
        if self.stack and self.stack[-1] == tag:
            self.stack.pop()

    def handle_data(self, data):
        if self.stack and self.stack[-1] in SKIP_TAGS:
            return
        text = data.strip()
        if not text:
            return
        # debris left by an attribute that closed early
        for pat in ('">', '"/>', "'>", '">'):
            if text.startswith(pat) or text == pat.strip():
                self.findings.append(f"attribute-escape debris as text: {text[:60]!r}")
                return
        if re.match(r'^["\']\s*/?>', text):
            self.findings.append(f"attribute-escape debris as text: {text[:60]!r}")


def check(html, label):
    findings = []

    c = Checker()
    try:
        c.feed(html)
    except Exception as e:
        findings.append(f"parser error: {e}")
    findings += c.findings

    # raw PHP surviving into the response
    if "<?" in html:
        findings.append("raw PHP in output: " + repr(html[html.index("<?"):][:60]))

    # unbalanced double quotes inside a start tag. Scanned rather than
    # regexed: a `>` inside an attribute value must not end the tag, or an
    # onclick containing an arrow function reads as a truncated tag.
    i = 0
    while i < len(html):
        lt = html.find("<", i)
        if lt == -1:
            break
        if not re.match(r"[a-zA-Z]", html[lt + 1:lt + 2] or " "):
            i = lt + 1
            continue
        j, quote, closed = lt + 1, None, None
        while j < len(html):
            ch = html[j]
            if quote:
                if ch == quote:
                    quote = None
            elif ch in "\"'":
                quote = ch
            elif ch == ">":
                closed = j
                break
            j += 1
        if closed is None:
            findings.append(f"unterminated tag: {html[lt:lt+70]!r}")
            break
        tag = html[lt:closed + 1]
        if tag.count('"') % 2:
            findings.append(f"odd number of quotes in tag: {tag[:80]!r}")
        i = closed + 1

    if findings:
        print(f"  ✗ {label}")
        for f in dict.fromkeys(findings):
            print(f"      {f}")
        return 1
    return 0


if __name__ == "__main__":
    src = sys.argv[1]
    label = sys.argv[2] if len(sys.argv) > 2 else src
    html = sys.stdin.read() if src == "-" else open(src, encoding="utf-8", errors="ignore").read()
    sys.exit(check(html, label))
