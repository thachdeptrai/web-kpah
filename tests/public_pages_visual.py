#!/usr/bin/env python3
"""Chromium screenshot + geometry regression for five public pages, fixture DB only."""
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
ROUTES=["gioi-thieu","tai-game","giftcode","tin-tuc","cong-dong"]
WIDTHS=[320,375,768,1200,1241,1280,1440]
RESULT_DIR=Path("artifacts/home-layout")
RESULT_DIR.mkdir(parents=True,exist_ok=True)

JS=r"""JSON.stringify((()=>{
 const rect=s=>{const e=document.querySelector(s);if(!e)return null;
  const r=e.getBoundingClientRect();return {x:r.x,y:r.y,width:r.width,height:r.height,right:r.right,bottom:r.bottom}};
 const header=rect('.lp-header'),hero=rect('.lp-inner-hero'),content=rect('.lp-inner-content'),
 art=rect('.lp-inner-emblem'),title=rect('.lp-inner-hero h1');
 const issues=[];
 const add=(v,msg)=>{if(v)issues.push(msg)};
 add(!document.body.classList.contains('kp-inner-page'),'missing inner scope');
 add(!document.querySelector('link[href*="public-pages-v2.css"]'),'missing shared public stylesheet');
 add(document.documentElement.scrollWidth>innerWidth+2,'horizontal overflow');
 add(!header||!hero||!content||!title,'required block absent');
 add(header&&hero&&header.bottom>hero.y+2,'sticky header overlays hero');
 add(hero&&content&&hero.bottom>content.y+2,'hero overlaps content');
 add(title&&art&&Math.min(title.right,art.right)-Math.max(title.x,art.x)>2
   &&Math.min(title.bottom,art.bottom)-Math.max(title.y,art.y)>2,'title overlaps hero illustration');
 const menuLinks=[...document.querySelectorAll('#lp-menu>a')];
 add(menuLinks.length!==5,'expected five menu links; download is a CTA');
 menuLinks.forEach((a,i)=>{
   const icon=a.querySelector('.lp-nav-icon');
   const content=icon?.querySelector('svg,img');
   add(!icon||!content||!a.querySelector('.lp-nav-label'),'missing navigation icon/label '+i);
   if(content?.tagName.toLowerCase()==='img')
     add(!content.complete||content.naturalWidth===0,'missing community Zalo logo');
   if(innerWidth>1240&&icon){
     const rect=icon.getBoundingClientRect();
     add(rect.width<32||rect.height<32||rect.width>38,'desktop navigation icon outside balanced size '+i);
   }
 });
 const download=document.querySelector('.lp-nav-actions .lp-download-nav');
 const signin=document.querySelector('.lp-nav-actions .lp-nav-login,.lp-nav-actions .lp-nav-account');
 add(!download||!signin,'header must show download CTA next to login');
 add(menuLinks.some(a=>new URL(a.href).pathname==='/tai-game'),'duplicate download menu item');
 if(download&&signin){
   const d=download.getBoundingClientRect(),l=signin.getBoundingClientRect();
   add(getComputedStyle(download).display==='none'||d.width<90||d.right>innerWidth+2,
      'download CTA invisible or clipped');
   add(d.right>l.left+2||Math.abs((d.top+d.bottom)/2-(l.top+l.bottom)/2)>10,
      'download CTA not beside login');
 }
 if(innerWidth>1240){
   const menu=document.querySelector('#lp-menu')?.getBoundingClientRect();
   const auth=document.querySelector('.lp-nav-actions')?.getBoundingClientRect();
   add(menu&&auth&&menu.right>auth.left+2,'navigation collides with account buttons');
 }else{
   add(getComputedStyle(document.querySelector('.lp-menu-toggle')).display==='none',
       'compact navigation toggle is missing');
 }
 const anchors=[...document.querySelectorAll('.lp-download-card,.lp-gift-card,.lp-news-card,.lp-feature-strip article,.lp-mini-feature')];
 for(const [i,el] of anchors.entries()){
  const r=el.getBoundingClientRect();
  if(r.width>innerWidth+2)issues.push('oversized card '+i);
 }
 const images=[...document.querySelectorAll('.lp-inner-emblem img')];
 for(const img of images) add(!img.complete||img.naturalWidth===0,'broken hero artwork');
 const marks=[...document.querySelectorAll('.lp-platform-brand')];
 if(location.pathname==='/tai-game'){
  add(marks.length!==4,'expected four platform icons');
  const expected=['android','windows','java','apple'];
  marks.forEach((img,i)=>{
   add(!img.getAttribute('src')?.endsWith('/assets/img/platform-'+expected[i]+'.svg'),'wrong platform icon '+i);
   add(!img.complete||img.naturalWidth===0,'broken platform SVG '+i);
  });
 }
 if(location.pathname==='/cong-dong'){
  const button=document.querySelector('.lp-button-zalo');
  add(!button,'Zalo button missing');
  if(button){
   const icon=button.querySelector('img.lp-zalo-icon');
   add(!icon,'Official Zalo app icon missing');
   if(icon){
    add(!icon.getAttribute('src')?.endsWith('/assets/img/zalo-official.svg'),'wrong Zalo icon source');
    add(!icon.complete||icon.naturalWidth===0,'broken Zalo logo asset');
    const box=icon.getBoundingClientRect();
    add(box.left<0||box.right>innerWidth+2,'Zalo logo clipped on mobile');
   }
   add(!button.href.startsWith('https://zalo.me/'),'Zalo group link changed');
   const style=getComputedStyle(button);
   const lum=value=>{const rgb=value.match(/[\d.]+/g).slice(0,3).map(Number).map(v=>{v/=255;return v<=.04045?v/12.92:Math.pow((v+.055)/1.055,2.4)});
    return rgb[0]*.2126+rgb[1]*.7152+rgb[2]*.0722;};
   const fg=lum(style.color),bg=lum(style.backgroundColor);
   add((Math.max(fg,bg)+.05)/(Math.min(fg,bg)+.05)<4.5,'Zalo text contrast below 4.5:1');
  }
 }
 if(location.pathname==='/gioi-thieu'){
  const icon=document.querySelector('.lp-mini-feature-gold .lp-about-zalo-icon');
  add(!icon,'introduction community card Zalo icon missing');
  if(icon){
   add(!icon.src.endsWith('/assets/img/zalo-official.svg'),'introduction Zalo icon asset wrong');
   add(!icon.complete||icon.naturalWidth===0,'introduction Zalo icon failed to load');
   const r=icon.getBoundingClientRect();
   add(r.width>34||r.width<24||r.right>innerWidth+2,'introduction Zalo icon incorrect mobile sizing');
  }
 }
 const toast=window.KpUI?.toast?.({type:'success',title:'Kiểm tra vị trí',message:'Toast trên bên phải',duration:1800});
 const toastRegion=document.querySelector('.kp-ui-toast-region');
 add(!toast||!toastRegion,'UI toast did not initialize');
 if(toastRegion){
   const t=toastRegion.getBoundingClientRect();
   const positioning=getComputedStyle(toastRegion);
   add(positioning.position!=='fixed','toast is not viewport-anchored');
   add(t.top>44||t.top<0,'toast must be in the top-right corner');
   add(t.right>innerWidth+2||innerWidth-t.right>44,'toast is not right-aligned');
   add(positioning.bottom!=='auto','toast is still bottom-anchored');
   add(document.documentElement.scrollWidth>innerWidth+2,'toast causes horizontal overflow');
   toastRegion.remove();
 }
 const bodyStyle=getComputedStyle(document.body);
 return {width:innerWidth,scrollWidth:document.documentElement.scrollWidth,
  issues,heroHeight:hero?.height,contentTop:content?.y,
  heroArtWidth:art?.width,font:bodyStyle.fontFamily};
})())"""
def main():
    exe=next((shutil.which(n) for n in ("google-chrome","google-chrome-stable","chromium","chromium-browser") if shutil.which(n)),None)
    if not exe:raise SystemExit("Chrome/Chromium unavailable")
    failures=[]
    with tempfile.TemporaryDirectory(prefix="kpah-public-", ignore_cleanup_errors=True) as profile:
        with open(os.devnull,"wb") as devnull:
            proc=subprocess.Popen([exe,"--headless=new","--no-sandbox","--disable-dev-shm-usage",
                "--disable-gpu","--no-first-run","--remote-allow-origins=*",
                "--remote-debugging-port=0","--user-data-dir="+profile,"about:blank"],stdout=devnull,stderr=devnull)
            try:
                port=wait_for_remote_debugging(profile,proc)
                req=Request("http://127.0.0.1:%d/json/new"%port,method="PUT")
                tab=json.loads(urlopen(req,timeout=10).read())
                ws=create_connection(tab["webSocketDebuggerUrl"],timeout=15,suppress_origin=True)
                try:
                    dev=CDP(ws);dev.call("Page.enable");dev.call("Runtime.enable")
                    for route in ROUTES:
                        for width in WIDTHS:
                            dev.call("Emulation.setDeviceMetricsOverride",
                                {"width":width,"height":950,"deviceScaleFactor":1,"mobile":False})
                            dev.call("Page.navigate",{"url":BASE+"/"+route})
                            for attempt in range(75):
                                time.sleep(.16)
                                try:
                                    if dev.eval("document.readyState")=="complete" and dev.eval("!!document.querySelector('.lp-inner-content')"):
                                        break
                                except Exception:
                                    continue
                            else:
                                failures.append((route,width,["page not ready"]));continue
                            for _ in range(30):
                                if dev.eval("document.fonts.status === 'loaded'"):
                                    break
                                time.sleep(.1)
                            time.sleep(.23)
                            data=json.loads(dev.eval(JS))
                            png=dev.call("Page.captureScreenshot",{"format":"png","fromSurface":True})
                            (RESULT_DIR/("public-%s-%s.png"%(route,width))).write_bytes(base64.b64decode(png["data"]))
                            print("PUBLIC "+route+" "+json.dumps(data,ensure_ascii=False),flush=True)
                            if data["issues"]:failures.append((route,width,data["issues"]))
                finally:
                    ws.close()
            finally:
                proc.terminate()
                try:proc.wait(timeout=5)
                except subprocess.TimeoutExpired:proc.kill()
    if failures:raise RuntimeError("Public visual regressions: "+json.dumps(failures,ensure_ascii=False))
    print("PASS: all five public pages have no geometry overlap or horizontal overflow at four viewport widths")
if __name__=="__main__":main()
