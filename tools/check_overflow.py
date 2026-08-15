from playwright.sync_api import sync_playwright
CUST=["/index.php","/shop/browse.php","/shop/product.php?slug=australian-beef-striploin","/shop/cart.php","/shop/checkout.php",
 "/shop/orders.php","/shop/freshness.php","/help/freshness.php","/wallet.php","/wishlist.php",
 "/notifications.php","/profile.php","/become-retailer.php","/auth/login.php","/auth/register.php"]
RET=["/retailer/dashboard.php","/retailer/products.php","/retailer/inventory.php","/retailer/orders.php",
 "/retailer/refunds.php","/retailer/reviews.php","/retailer/reports.php","/retailer/discounts.php","/retailer/profile.php"]
ADM=["/admin/dashboard.php","/admin/users.php","/admin/retailers.php","/admin/orders.php","/admin/refunds.php",
 "/admin/reviews.php","/admin/promos.php","/admin/settings.php"]
B="http://127.0.0.1:8899"
PROBE=r'''()=>{const W=document.documentElement.clientWidth;const bad=[];
 const OFF='.mobile-nav,.mobile-nav-backdrop,.search-overlay,.sheet,.tabbar,.toast-stack';
 document.body.querySelectorAll('*').forEach(e=>{
   if(e.closest(OFF)) return;
   const cs=getComputedStyle(e);
   if(cs.position==='fixed'||cs.visibility==='hidden'||cs.display==='none') return;
   const b=e.getBoundingClientRect(); if(b.width===0||b.height===0) return;
   let a=e.parentElement,rail=false;
   while(a){const s=getComputedStyle(a); if(/auto|scroll/.test(s.overflowX)){rail=true;break;} a=a.parentElement;}
   if(rail) return;
   if(b.right>W+1) bad.push((e.tagName.toLowerCase()+'.'+(e.className||'').toString().trim().split(/\s+/)[0]).slice(0,42)+' +'+Math.round(b.right-W));
 });
 return [...new Set(bad)].slice(0,4);}'''
def login(pg,em):
    pg.goto(B+"/auth/login.php"); pg.wait_for_load_state("networkidle")
    pg.fill('input[name=email]',em); pg.fill('input[name=password]','Test1234!')
    pg.click('form button[type=submit]:visible'); pg.wait_for_load_state("networkidle")
bad=[];total=0
with sync_playwright() as p:
    br=p.chromium.launch(headless=True)
    for em,paths in [("cherry@example.my",CUST),("retailer@cameron.my",RET),("admin@freshmart.my",ADM)]:
        ctx=br.new_context(viewport={"width":360,"height":800},is_mobile=True,has_touch=True); pg=ctx.new_page()
        login(pg,em)
        for path in paths:
            total+=1; pg.goto(B+path); pg.wait_for_load_state("networkidle")
            r=pg.evaluate(PROBE)
            if r: bad.append((path,r))
        ctx.close()
    br.close()
print(f"scanned {total} at 360px — {len(bad)} overflowing\n")
for path,items in bad:
    print(f"  {path}")
    for i in items: print(f"      {i}px")
