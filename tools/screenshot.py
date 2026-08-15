#!/usr/bin/env python3
"""Capture rendered pages so a phase can be checked by looking, not inferring.

A 200 with an empty error log says the page did not crash. It says nothing
about whether it renders correctly — a stray `">` sat at the top-left of every
page for a whole phase behind a green suite. This exists so "verified" can mean
"looked at".

Usage:
    screenshot.py <base-url> <outdir> [--auth EMAIL:PASSWORD] path [path ...]
"""
import sys, os, re, pathlib

from playwright.sync_api import sync_playwright


def main():
    args = sys.argv[1:]
    auth = None
    if "--auth" in args:
        i = args.index("--auth")
        auth = args[i + 1]
        del args[i:i + 2]
    base, outdir, paths = args[0], args[1], args[2:]
    pathlib.Path(outdir).mkdir(parents=True, exist_ok=True)

    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        ctx = browser.new_context(viewport={"width": 1280, "height": 900})
        page = ctx.new_page()

        errors = []
        page.on("console", lambda m: errors.append(f"console.{m.type}: {m.text}")
                if m.type in ("error",) else None)
        page.on("pageerror", lambda e: errors.append(f"pageerror: {e}"))

        if auth:
            email, pw = auth.split(":", 1)
            page.goto(f"{base}/auth/login.php")
            page.wait_for_load_state("networkidle")
            page.fill('input[name="email"]', email)
            page.fill('input[name="password"]', pw)
            page.click('form button[type="submit"]:visible, form input[type="submit"]:visible')
            page.wait_for_load_state("networkidle")

        for path in paths:
            name = re.sub(r"[^a-zA-Z0-9]+", "_", path).strip("_") or "root"
            page.goto(f"{base}{path}")
            page.wait_for_load_state("networkidle")
            out = os.path.join(outdir, f"{name}.png")
            page.screenshot(path=out, full_page=False)

            # visible text in the first 120px — where attribute debris lands
            top = page.evaluate("""() => {
                const out = [];
                const w = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
                let n;
                while ((n = w.nextNode())) {
                    const t = n.textContent.trim();
                    if (!t) continue;
                    const r = n.parentElement?.getBoundingClientRect();
                    if (r && r.top < 120 && r.width > 0) out.push(t.slice(0, 40));
                    if (out.length > 8) break;
                }
                return out;
            }""")
            print(f"  {path}  ->  {out}")
            print(f"      top-of-page text: {top[:5]}")

        if errors:
            print("\n  JS/console errors:")
            for e in dict.fromkeys(errors):
                print(f"      {e[:140]}")
        browser.close()


if __name__ == "__main__":
    main()
