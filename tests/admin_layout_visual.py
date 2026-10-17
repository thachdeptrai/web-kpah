#!/usr/bin/env python3
"""Admin responsive geometry against disposable fixture only; real browser auth."""
import base64
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import time
from urllib.request import Request, urlopen
from websocket import create_connection
from home_layout_visual import CDP, wait_for_remote_debugging

BASE=os.environ.get("KPAH_TEST_URL","http://127.0.0.1:8089").rstrip("/")
if os.environ.get("KPAH_DB_NAME")!="kpah_route_test" or not BASE.startswith("http://127.0.0.1:"):
    raise SystemExit("Admin geometry tests require local fixture, not production")
PATHS={
    "dashboard":"/quan-tri",
    "downloads":"/quan-tri/tai-game",
    "referrals":"/quan-tri/ma-gioi-thieu",
    "giftcodes":"/quan-tri/giftcode",
    "game-accounts":"/quan-tri/tai-khoan-game",
    "moderation":"/quan-tri/lich-su-thao-tac",
    "farmer":"/quan-tri/diem-nong-dan",
    "payments":"/quan-tri/nap",
    "orders":"/quan-tri/doi-soat",
    "key-users":"/quan-tri/tai-khoan-key",
}
WIDTHS=[320,375,768,1280]
OUTPUT=Path("artifacts/admin-layout")
OUTPUT.mkdir(parents=True,exist_ok=True)
JS=r"""JSON.stringify((()=>{
 const q=s=>document.querySelector(s);
 const box=e=>{if(!e)return null;const r=e.getBoundingClientRect();
   return {left:r.left,right:r.right,top:r.top,bottom:r.bottom,width:r.width,height:r.height}};
 const issues=[],add=(condition,msg)=>{if(condition)issues.push(msg)};
 const menu=q(".kp-admin-nav-v2"),b=box(menu),w=innerWidth,bodyWidth=document.documentElement.scrollWidth;
 add(location.pathname==="/quan-tri/dang-nhap","not authenticated");
 add(!menu,"missing shared admin navigation");
 add(bodyWidth>w+2,"document horizontal overflow "+bodyWidth+"/"+w);
 if(b)add(b.left<-2||b.right>w+2,"admin navigation outside viewport");
 const active=q('.kp-admin-nav-v2 [aria-current="page"]');
 add(!active,"current admin destination not indicated");
 let openMenu=null;
 if(w<=900&&menu){
  const toggle=q(".kp-admin-nav-mobile-toggle"),details=q(".kp-admin-nav-mobile"),panel=q(".kp-admin-nav-mobile-panel");
  add(!toggle||!details||!panel,"mobile menu control missing");
  if(toggle){
   const r=box(toggle);
   add(r.left<-2||r.right>w+2,"admin menu button clipped");
  }
  if(details&&panel){
   details.open=true;
   const m=box(panel);
   add(m.left<-2||m.right>w+2,"admin menu panel clipped");
   add(m.height>innerHeight+2,"admin menu taller than viewport");
   openMenu={width:m.width,height:m.height};
   details.open=false;
  }
 }
 const fieldErrors=[];
 for(const e of document.querySelectorAll("form input:not([type=hidden]),form select,form textarea")){
   const style=getComputedStyle(e),rect=box(e);
   if(style.display==="none"||style.visibility==="hidden"||!rect||rect.width<1)continue;
   const scrollingParent=e.closest(".tp-table-wrap,.gc-table-wrap,.table-scroll,.table-wrap,.gam-audit-scroll,.farmer-table-scroll");
   if(scrollingParent)continue;
   if(rect.left<-3||rect.right>w+3)fieldErrors.push(e.name||e.id||e.tagName);
 }
 add(fieldErrors.length>0,"form control clipped: "+fieldErrors.slice(0,4).join(","));
 return {path:location.pathname,width:w,scrollWidth:bodyWidth,issues,menuOpen:openMenu};
})())"""

def wait(dev,expr,count=90):
    for _ in range(count):
        time.sleep(.16)
        try:
            if dev.eval(expr):return True
        except (RuntimeError,TypeError):
            pass
    return False

def main():
    binary=next((shutil.which(x) for x in ("google-chrome","google-chrome-stable","chromium","chromium-browser") if shutil.which(x)),None)
    if not binary:raise SystemExit("Chromium missing")
    failures=[]
    with tempfile.TemporaryDirectory(prefix="kpah-admin-visual-",ignore_cleanup_errors=True) as profile:
        with open(os.devnull,"wb") as devnull:
            proc=subprocess.Popen([binary,"--headless=new","--no-sandbox","--disable-gpu",
                "--disable-dev-shm-usage","--remote-allow-origins=*","--no-first-run",
                "--remote-debugging-port=0","--user-data-dir="+profile,"about:blank"],
                stdout=devnull,stderr=devnull)
            try:
                port=wait_for_remote_debugging(profile,proc)
                tab=json.loads(urlopen(Request("http://127.0.0.1:%d/json/new"%port,method="PUT"),timeout=10).read())
                ws=create_connection(tab["webSocketDebuggerUrl"],timeout=20,suppress_origin=True)
                try:
                    dev=CDP(ws);dev.call("Page.enable");dev.call("Runtime.enable")
                    dev.call("Page.navigate",{"url":BASE+"/quan-tri/dang-nhap"})
                    if not wait(dev,"!!document.querySelector('form input[name=csrf]')"):
                        raise RuntimeError("Fixture admin login form unavailable")
                    dev.eval("""(function(){
                      const f=document.querySelector('form[action="/quan-tri/dang-nhap"]');
                      if(!f)throw new Error('admin form absent');
                      f.querySelector('[name=username]').value='routeadmin';
                      f.querySelector('[name=password]').value='FixtureAdmin123';
                      f.submit();return true;
                    })()""")
                    if not wait(dev,"location.pathname==='/quan-tri' && !!document.querySelector('.kp-admin-nav-v2')"):
                        raise RuntimeError("Fixture admin login failed")
                    for name,path in PATHS.items():
                        for width in WIDTHS:
                            dev.call("Emulation.setDeviceMetricsOverride",{
                              "width":width,"height":920,"deviceScaleFactor":1,"mobile":False})
                            dev.call("Page.navigate",{"url":BASE+path})
                            if not wait(dev,"document.readyState==='complete' && !!document.querySelector('.kp-admin-nav-v2')"):
                                failures.append((name,width,["page did not load admin shell"]));continue
                            time.sleep(.18)
                            data=json.loads(dev.eval(JS))
                            print("ADMIN "+name+" "+str(width)+": "+json.dumps(data,ensure_ascii=False),flush=True)
                            shot=dev.call("Page.captureScreenshot",{"format":"png","fromSurface":True,
                               "captureBeyondViewport":False})
                            (OUTPUT/("%s-%s.png"%(name,width))).write_bytes(base64.b64decode(shot["data"]))
                            if data["issues"]:failures.append((name,width,data["issues"]))
                finally:
                    ws.close()
            finally:
                proc.terminate()
                try:proc.wait(timeout=5)
                except subprocess.TimeoutExpired:proc.kill()
    if failures:raise RuntimeError("Admin layout problems: "+json.dumps(failures,ensure_ascii=False))
    print("PASS: admin and finance routes at four viewport widths")
if __name__=="__main__":main()
