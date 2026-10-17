#!/usr/bin/env python3
"""Signed-in fixture visual smoke: browser logs in normally; never touches live accounts."""
from __future__ import annotations
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
    raise SystemExit("Requires disposable local fixture, never a production account")
PATHS={"dashboard":"/tai-khoan","profile":"/ho-so","characters":"/nhan-vat"}
WIDTHS=[320,375,768,1024,1440]
OUTPUT=Path("artifacts/player-portal")
OUTPUT.mkdir(parents=True,exist_ok=True)
JS=r"""JSON.stringify((()=>{
 const q=s=>document.querySelector(s);
 const rect=s=>{const e=q(s);if(!e)return null;let r=e.getBoundingClientRect();return {left:r.left,right:r.right,top:r.top,bottom:r.bottom,width:r.width,height:r.height}};
 const overlap=(a,b)=>a&&b&&Math.min(a.right,b.right)>Math.max(a.left,b.left)+2&&Math.min(a.bottom,b.bottom)>Math.max(a.top,b.top)+2;
 const errors=[],fail=(c,msg)=>{if(c)errors.push(msg)};
 const w=innerWidth,rail=rect(".portal-rail"),header=rect(".portal-top"),hero=rect(".portal-hero"),main=rect(".portal-main");
 fail(!document.body.classList.contains("portal-page"),"not logged in to player portal");
 fail(!q('link[href*="player-portal-v3.css"]'),"v3 stylesheet missing");
 fail(document.documentElement.scrollWidth>w+2,"horizontal document overflow "+document.documentElement.scrollWidth);
 fail(!rail||!header||!hero||!main,"missing rail/header/hero/main");
 if(w>900){
  fail(rail&&main&&rail.right>main.left+2,"desktop rail intersects content");
  fail(rail&&getComputedStyle(q(".portal-rail")).position!=="fixed","desktop rail must be fixed");
 }else{
  fail(rail&&main&&rail.bottom>header.top+2,"mobile navigation overlaps header");
  fail(rail&&getComputedStyle(q(".portal-rail")).position==="fixed","legacy fixed bottom rail still active");
 }
 fail(header&&hero&&header.bottom>hero.top+2,"header overlaps hero");
 const nav=q(".portal-nav-list"),links=nav?[...nav.querySelectorAll("a")]:[];
 fail(links.length!==9,"missing account navigation links");
 fail(!q('.portal-rail .rail-icon[aria-current="page"]'),"active navigation missing");
 if(nav&&w<=900){
  fail(getComputedStyle(nav).overflowX!=="auto","mobile navigation not horizontally scrollable");
  fail(nav.getBoundingClientRect().right>w+2,"mobile navigation offscreen");
 }
 const stats=[...document.querySelectorAll(".pd-stat-card")];
 if(location.pathname==="/tai-khoan"){
  fail(stats.length!==4,"four resource metrics not rendered");
  for(let i=0;i<stats.length;i++){let b=stats[i].getBoundingClientRect();fail(b.right>w+2||b.left<-2,"stat "+i+" outside viewport")}
  fail(!q(".pd-character-list")&&!q(".pd-empty"),"character preview absent");
 }
 if(location.pathname==="/ho-so"){
  fail(!q('#profile-form input[name="csrf"]'),"profile CSRF missing");
  fail(!q('#change-password-form input[name="csrf"]'),"password CSRF missing");
  for(const input of document.querySelectorAll('.profile-card input,.security-card input')){
   let b=input.getBoundingClientRect();if(b.width>0)fail(b.left<-2||b.right>w+2,"profile input outside viewport: "+input.name)
  }
 }
 if(location.pathname==="/nhan-vat")fail(!q(".characters-section"),"characters missing");
 fail(!getComputedStyle(document.body).backgroundImage.includes("linear-gradient"),"bright portal background missing");
 const img=q(".portal-rail-logo img");
 fail(!img||!img.complete||img.naturalWidth===0,"branded portal logo missing");
 return {viewport:w,path:location.pathname,scrollWidth:document.documentElement.scrollWidth,
  errors,layout:{navHeight:rail?.height,heroHeight:hero?.height,mainX:main?.left,metricCount:stats.length}};
})())"""

def wait_condition(dev,expr,attempts=100):
    for _ in range(attempts):
        time.sleep(.16)
        try:
            if dev.eval(expr):return True
        except (RuntimeError,TypeError):
            continue
    return False

def main():
    binary=next((shutil.which(x) for x in ("google-chrome","google-chrome-stable","chromium","chromium-browser") if shutil.which(x)),None)
    if not binary:raise SystemExit("Chromium missing")
    failures=[]
    with tempfile.TemporaryDirectory(prefix="kpah-player-visual-",ignore_cleanup_errors=True) as profile:
        with open(os.devnull,"wb") as devnull:
            args=[binary,"--headless=new","--no-sandbox","--disable-gpu","--disable-dev-shm-usage",
                  "--remote-allow-origins=*","--no-first-run","--remote-debugging-port=0",
                  "--user-data-dir="+profile,"about:blank"]
            proc=subprocess.Popen(args,stdout=devnull,stderr=devnull)
            try:
                port=wait_for_remote_debugging(profile,proc)
                tab=json.loads(urlopen(Request(f"http://127.0.0.1:{port}/json/new",method="PUT"),timeout=10).read())
                conn=create_connection(tab["webSocketDebuggerUrl"],timeout=18,suppress_origin=True)
                try:
                    dev=CDP(conn);dev.call("Page.enable");dev.call("Runtime.enable")
                    dev.call("Page.navigate",{"url":BASE+"/dang-nhap"})
                    if not wait_condition(dev,"!!document.querySelector('#login-form #username')"):
                        raise RuntimeError("Fixture login form unavailable")
                    dev.eval("""(function(){
                        const form=document.getElementById('login-form');
                        form.querySelector('[name="username"]').value='routeplayer';
                        form.querySelector('[name="password"]').value='FixturePass123';
                        form.submit();
                        return true;
                    })()""")
                    if not wait_condition(dev,"location.pathname==='/tai-khoan' && !!document.querySelector('.portal-nav-list')"):
                        raise RuntimeError("Fixture account login failed")
                    for name,path in PATHS.items():
                        for width in WIDTHS:
                            dev.call("Emulation.setDeviceMetricsOverride",
                                     {"width":width,"height":930,"deviceScaleFactor":1,"mobile":False})
                            dev.call("Page.navigate",{"url":BASE+path})
                            if not wait_condition(dev,"document.readyState==='complete' && !!document.querySelector('.portal-rail')"):
                                failures.append((name,width,["page load failed"]));continue
                            time.sleep(.35)
                            result=json.loads(dev.eval(JS))
                            shot=dev.call("Page.captureScreenshot",{"format":"png","fromSurface":True,"captureBeyondViewport":False})
                            (OUTPUT/f"{name}-{width}.png").write_bytes(base64.b64decode(shot["data"]))
                            print("PORTAL "+name+" "+str(width)+": "+json.dumps(result,ensure_ascii=False),flush=True)
                            if result["errors"]:failures.append((name,width,result["errors"]))
                finally:conn.close()
            finally:
                proc.terminate()
                try:proc.wait(timeout=5)
                except subprocess.TimeoutExpired:proc.kill()
    if failures:raise RuntimeError("Player portal geometry failures: "+json.dumps(failures,ensure_ascii=False))
    print("PASS: signed-in player dashboard, profile and characters across five widths")
if __name__=="__main__":
    main()
