#!/usr/bin/env python3
"""Authenticated-form-neutral Chromium geometry checks for the three public auth routes.
Uses fixture DB and snapshots only. Does not submit or alter credentials/OTP.
"""
from __future__ import annotations
import base64
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import time
from urllib.request import Request,urlopen
from websocket import create_connection
from home_layout_visual import CDP,wait_for_remote_debugging

BASE=os.environ.get("KPAH_TEST_URL","http://127.0.0.1:8089").rstrip("/")
PATHS={"login":"/dang-nhap","register":"/dang-ky","recovery":"/quen-mat-khau"}
WIDTHS=[320,375,768,1024,1440]
OUTPUT=Path("artifacts/auth-layout")
OUTPUT.mkdir(parents=True,exist_ok=True)
JS=r"""JSON.stringify((()=>{
 const $=s=>document.querySelector(s);
 const r=s=>{const e=$(s);if(!e)return null;const q=e.getBoundingClientRect();
  return {left:q.left,right:q.right,top:q.top,bottom:q.bottom,width:q.width,height:q.height}};
 const seen=s=>{const e=$(s);return !!e&&getComputedStyle(e).visibility!=='hidden'
                 &&getComputedStyle(e).display!=='none'&&!!e.getClientRects().length};
 const intersect=(a,b)=>a&&b&&Math.min(a.right,b.right)-Math.max(a.left,b.left)>2
                     &&Math.min(a.bottom,b.bottom)-Math.max(a.top,b.top)>2;
 const failures=[];const fail=(test,s)=>{if(test)failures.push(s)};
 const head=r('.kp-auth-topbar'),story=r('.kp-auth-story'),form=r('.panel-inner'),
       footer=r('.footer'),nav=r('.kp-auth-links'),logo=r('.kp-auth-logo');
 fail(!document.body.classList.contains('kp-auth-v2'),'missing auth v2 body class');
 fail(!document.body.classList.contains('kp-auth-v3'),'missing v3 design');
 const intro=r('.kp-auth-story__intro'),picture=r('.kp-auth-story__visual');
 fail(!intro||!picture,'separate picture and text zones absent');
 fail(intersect(intro,picture),'artwork intersects text zone');
 const art=$('.kp-auth-story__visual img');
 fail(!art||!art.complete||art.naturalWidth===0,'new standalone artwork failed to load');
 if(art)fail(getComputedStyle(art).objectFit!=='contain','artwork must not crop with cover');

 fail(!document.querySelector('link[href*="auth-portal-v2.css"]'),'missing stylesheet');
 fail(!head||!story||!form||!footer,'missing layout region');
 fail(document.documentElement.scrollWidth>innerWidth+2,
      'horizontal document overflow '+document.documentElement.scrollWidth+'/'+innerWidth);
 fail(intersect(story,form),'story overlaps form');
 fail(head&&story&&head.bottom>story.top+2,'header covers story');
 fail(head&&form&&head.bottom>form.top+2,'header covers form');
 fail(intersect(nav,logo),'header navigation collides with logo');
 fail(!seen('.kp-auth-switch'),'account tabs absent');
 const tips=$('.kp-auth-story .rules');
 fail(innerWidth<=820 && tips && getComputedStyle(tips).display!=='none',
      'mobile story tip list still visible');

 fail(head&&nav&&nav.right>innerWidth+2,'nav clipped outside viewport');

 const storyLogo=$('.kp-auth-story__logo');
 fail(!storyLogo||!storyLogo.complete||storyLogo.naturalWidth===0,'story logo unavailable');
 const isCompact=location.pathname==='/dang-ky'||location.pathname==='/quen-mat-khau';
 if(isCompact&&document.body.classList.contains('kp-auth-v4')){
   const imageHeight=story?.height??0;
   if(innerWidth<=820)fail(imageHeight>250,'compact auth artwork too tall on mobile: '+imageHeight);
   if(innerWidth>=960)fail(imageHeight>715,'compact auth artwork stretches beyond intended cap: '+imageHeight);
   if(location.pathname==='/dang-ky'&&innerWidth>=960){
     const username=$('#reg-form [data-field="username"]');
     const phone=$('#reg-form [data-field="phone"]');
     fail(!username||!phone||
          Math.abs(username.getBoundingClientRect().top-phone.getBoundingClientRect().top)>12,
          'register username and phone must share the first desktop row');
   }
   if(innerWidth>=1200)fail(form&&form.height>1020,'compact auth form too tall on desktop: '+form.height);
 }
 const btns=[...document.querySelectorAll('form input:not([type=hidden]):not([type=checkbox])')].filter(input=>!input.closest('.hp') && getComputedStyle(input).visibility !== 'hidden');
 btns.forEach((input,i)=>{const t=input.getBoundingClientRect();
   fail(t.right>innerWidth+2||t.left<0,'input outside viewport '+i)});
 return {width:innerWidth,page:location.pathname,scrollWidth:document.documentElement.scrollWidth,
     errors:failures,layout:{header:head?.height,brand:story?.height,panel:form?.height,
       brandX:story?.left,panelX:form?.left},fields:btns.length};
})())"""
def main():
 exe=next((shutil.which(x) for x in ("google-chrome","google-chrome-stable","chromium","chromium-browser") if shutil.which(x)),None)
 if not exe:raise SystemExit("Chrome/Chromium unavailable")
 failures=[]
 with tempfile.TemporaryDirectory(prefix="kpah-auth-", ignore_cleanup_errors=True) as profile:
  with open(os.devnull,"wb") as devnull:
   cmd=[exe,"--headless=new","--no-sandbox","--disable-gpu",
       "--disable-dev-shm-usage","--remote-allow-origins=*","--no-first-run",
       "--remote-debugging-port=0","--user-data-dir="+profile,"about:blank"]
   proc=subprocess.Popen(cmd,stdout=devnull,stderr=devnull)
   try:
    port=wait_for_remote_debugging(profile,proc)
    new=Request(f"http://127.0.0.1:{port}/json/new",method="PUT")
    tab=json.loads(urlopen(new,timeout=10).read())
    ws=create_connection(tab["webSocketDebuggerUrl"],timeout=15,suppress_origin=True)
    try:
     dev=CDP(ws);dev.call("Page.enable");dev.call("Runtime.enable")
     for route,path in PATHS.items():
      for width in WIDTHS:
       dev.call("Emulation.setDeviceMetricsOverride",
           {"width":width,"height":950,"deviceScaleFactor":1,"mobile":False})
       dev.call("Page.navigate",{"url":BASE+path})
       for _ in range(65):
        time.sleep(.15)
        try:
         if dev.eval("document.readyState")=="complete" and dev.eval("!!document.querySelector('.kp-auth-switch')"):
          break
        except Exception:
         continue
       else: failures.append((route,width,["page did not load"]));continue
       time.sleep(.25)
       result=json.loads(dev.eval(JS))
       capture=dev.call("Page.captureScreenshot",{"format":"png","fromSurface":True})
       (OUTPUT/f"{route}-{width}.png").write_bytes(base64.b64decode(capture["data"]))
       print(f"AUTH {route} {width}: "+json.dumps(result,ensure_ascii=False),flush=True)
       if result["errors"]:failures.append((route,width,result["errors"]))
    finally:ws.close()
   finally:
    proc.terminate()
    try:proc.wait(timeout=5)
    except subprocess.TimeoutExpired:proc.kill()
 if failures:raise RuntimeError("Auth layout regressions: "+json.dumps(failures,ensure_ascii=False))
 print("PASS: three auth routes, five widths, no overlap or horizontal overflow")
if __name__=="__main__":main()
