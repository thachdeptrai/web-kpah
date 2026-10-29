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
 fail(links.length!==10,"missing account navigation links");
 fail(!links.some(a=>new URL(a.href).pathname==='/dien-dan'),"player Forum link missing");
 fail(!q('.portal-rail .rail-icon[aria-current="page"]'),"active navigation missing");
 if(nav&&w<=900){
  const toggle=q('.portal-nav-toggle');
  fail(!toggle||getComputedStyle(toggle).display==='none','mobile menu toggle missing');
  fail(getComputedStyle(nav).display!=='none','mobile menu should start collapsed');
  if(toggle){
   toggle.click();
   fail(toggle.getAttribute('aria-expanded')!=='true','mobile account menu did not open');
   fail(getComputedStyle(nav).display==='none','mobile menu links not visible when open');
   fail(nav.getBoundingClientRect().right>w+2,'mobile menu offscreen');
   toggle.click();
   fail(toggle.getAttribute('aria-expanded')!=='false','mobile menu did not close');
  }
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

POINT_RANK_JS=r"""JSON.stringify((()=>{
 const w=innerWidth, gallery=document.querySelector('.point-tier-gallery');
 const chips=[...document.querySelectorAll('.point-tier-chip')];
 const images=[...document.querySelectorAll('.point-tier-chip img,.point-tier-featured')];
 const failures=[];
 if(!gallery||chips.length!==6||images.length!==7)failures.push('six member rank badges plus current rank required');
 for(const image of images){
  if(!image.complete||image.naturalWidth<64)failures.push('missing/invalid WebP badge: '+image.src);
  let r=image.getBoundingClientRect();
  if(r.right>w+3||r.left<-3)failures.push('badge outside viewport');
 }
 if(document.documentElement.scrollWidth>w+3)failures.push('Point rank page horizontal overflow');
 const columns=gallery?getComputedStyle(gallery).gridTemplateColumns.split(' ').length:0;
 if(w<=650&&columns!==2)failures.push('phone membership badges should use two columns');
 if(w>650&&w<=1190&&columns!==3)failures.push('tablet membership badges should use three columns');
 if(w>1190&&columns!==6)failures.push('desktop membership badges should use six columns');
 return {w,images:images.length,columns,scrollWidth:document.documentElement.scrollWidth,failures};
})())"""

BANK_HISTORY_JS=r"""JSON.stringify((()=>{
 const wrap=document.querySelector('.tp-bank-history-scroll');
 const table=document.querySelector('.tp-bank-history-table');
 const issues=[],w=innerWidth;
 const bad=(condition,message)=>{if(condition)issues.push(message)};
 bad(!wrap||!table,'missing bank history table or scroll region');
 bad(document.documentElement.scrollWidth>w+2,'bank page overflows viewport');
 if(wrap&&table){
  const r=wrap.getBoundingClientRect();
  bad(r.left<-2||r.right>w+2,'history scroll region offscreen');
  bad(getComputedStyle(wrap).overflowX!=='auto','horizontal scrolling disabled');
  bad(table.getBoundingClientRect().width<895,'history table squashed');
  bad(table.querySelectorAll('thead th').length!==6,'history columns missing');
  for(const cell of table.querySelectorAll('thead th')){
   const cs=getComputedStyle(cell),b=cell.getBoundingClientRect();
   bad(cs.whiteSpace!=='nowrap','header text can wrap vertically: '+cell.textContent);
   bad(b.width<95,'header column crushed: '+cell.textContent);
  }
  if(w<=768)bad(wrap.scrollWidth<=wrap.clientWidth+30,'mobile table cannot scroll horizontally');
 }
 return {width:w,viewportScroll:document.documentElement.scrollWidth,
   historyWidth:table?.getBoundingClientRect().width,scrollRegionWidth:wrap?.clientWidth,
   scrollContentWidth:wrap?.scrollWidth,issues};
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
                        // http_regression.py changes the fixture user's password before visual tests.
                        form.querySelector('[name="password"]').value='ChangedPass123';
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
                    # Check the bank history table after a real fixture-player login.
                    # The report is geometry-only: no QR creation or payment mutation.
                    for width in (320,375,430,768,1280):
                        dev.call("Emulation.setDeviceMetricsOverride",
                                 {"width":width,"height":930,"deviceScaleFactor":1,"mobile":False})
                        dev.call("Page.navigate",{"url":BASE+"/nap-ngan-hang"})
                        if not wait_condition(dev,
                            "document.readyState==='complete' && !!document.querySelector('.tp-bank-history-table')"):
                            failures.append(("bank-history",width,["bank history table did not load"]));continue
                        time.sleep(.2)
                        result=json.loads(dev.eval(BANK_HISTORY_JS))
                        shot=dev.call("Page.captureScreenshot",
                                      {"format":"png","fromSurface":True,"captureBeyondViewport":False})
                        (OUTPUT/f"bank-history-{width}.png").write_bytes(base64.b64decode(shot["data"]))
                        print("BANK HISTORY "+str(width)+": "+json.dumps(result,ensure_ascii=False),flush=True)
                        if result["issues"]:failures.append(("bank-history",width,result["issues"]))
                    # K membership crest layout; never mutates Point balance or catalog.
                    for width in (320,375,430,768,1280):
                        dev.call("Emulation.setDeviceMetricsOverride",
                                 {"width":width,"height":930,"deviceScaleFactor":1,"mobile":False})
                        dev.call("Page.navigate",{"url":BASE+"/diem-tich-luy"})
                        if not wait_condition(dev,
                            "document.readyState==='complete' && !!document.querySelector('.point-tier-gallery')"):
                            failures.append(("point-k-ranks",width,["member rank images did not load"]));continue
                        time.sleep(.22)
                        result=json.loads(dev.eval(POINT_RANK_JS))
                        shot=dev.call("Page.captureScreenshot",
                                      {"format":"png","fromSurface":True,"captureBeyondViewport":False})
                        (OUTPUT/f"point-k-ranks-{width}.png").write_bytes(base64.b64decode(shot["data"]))
                        print("POINT RANK "+str(width)+": "+json.dumps(result,ensure_ascii=False),flush=True)
                        if result["failures"]:failures.append(("point-k-ranks",width,result["failures"]))
                finally:conn.close()
            finally:
                proc.terminate()
                try:proc.wait(timeout=5)
                except subprocess.TimeoutExpired:proc.kill()
    if failures:raise RuntimeError("Player portal geometry failures: "+json.dumps(failures,ensure_ascii=False))
    print("PASS: signed-in player dashboard, profile and characters across five widths")
if __name__=="__main__":
    main()
