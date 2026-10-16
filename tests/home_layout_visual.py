#!/usr/bin/env python3
"""Headless Chrome viewport geometry test against the actual PHP homepage.
Runs only on fixture database / CI server. No production write or network payment.
Requires Chrome or Chromium, Python websocket-client. Uses Chrome DevTools Protocol.
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
from urllib.request import Request, urlopen

try:
    from websocket import create_connection
except ImportError as e:
    raise SystemExit("Requires websocket-client package for the browser test") from e

BASE = os.environ.get("KPAH_TEST_URL", "http://127.0.0.1:8089").rstrip("/")
SCREENSHOTS = Path("artifacts/home-layout")
SCREENSHOTS.mkdir(parents=True, exist_ok=True)
WIDTHS = [320, 375, 768, 1024, 1440]
HEIGHT = 1080

JS = r"""JSON.stringify((() => {
  const pick = (selector) => document.querySelector(selector);
  const rect = (selector) => {
    const e = pick(selector);
    if (!e) return null;
    const b = e.getBoundingClientRect();
    return {x:b.x, y:b.y, left:b.left, top:b.top, right:b.right,
            bottom:b.bottom, width:b.width, height:b.height};
  };
  const visible = e => !!(e && getComputedStyle(e).display !== 'none' &&
    getComputedStyle(e).visibility !== 'hidden' && e.getClientRects().length);
  const overlap = (a,b) => a && b &&
    Math.min(a.right,b.right) - Math.max(a.left,b.left) > 2 &&
    Math.min(a.bottom,b.bottom) - Math.max(a.top,b.top) > 2;
  const issues=[];
  const add=(condition,message)=>{if (condition) issues.push(message);};
  const header=rect('.lp-header'), hero=rect('.lp-home-hero'), summary=rect('.lp-home-summary'),
        quests=rect('.lp-quest-grid'), shortcuts=rect('.lp-home-destinations'),
        logo=rect('.lp-home-logo-heading'), subtitle=rect('.lp-home-subtitle'),
        actions=rect('.lp-home-actions');
  add(!document.body.classList.contains('kp-home-page'),'missing homepage scope');
  add(!pick('link[href*="home-layout.css"]'),'missing isolated CSS');
  add(document.documentElement.scrollWidth > innerWidth+2,
      'horizontal document overflow '+document.documentElement.scrollWidth+'/'+innerWidth);
  add(!header || !hero || !summary || !quests || !shortcuts,'required block missing');
  add(header && hero && header.bottom > hero.top+2,'header overlaps hero');
  add(hero && summary && hero.bottom > summary.top+2,'hero overlaps statistics');
  add(summary && quests && summary.bottom > quests.top+2,'stats/events overlaps quests');
  add(quests && shortcuts && quests.bottom > shortcuts.top+2,'quests overlaps shortcuts');
  add(logo && subtitle && logo.bottom > subtitle.top+2,'hero logo overlaps subtitle');
  add(subtitle && actions && subtitle.bottom > actions.top+2,'subtitle overlaps actions');
  const stats = [...document.querySelectorAll('.lp-home-stats article')].map(e=>e.getBoundingClientRect());
  const cards = [...document.querySelectorAll('.lp-quest-card')].map(e=>e.getBoundingClientRect());
  for(let i=0;i<stats.length;i++)for(let j=i+1;j<stats.length;j++)
    add(overlap(stats[i],stats[j]),'stat '+i+' overlaps '+j);
  for(let i=0;i<cards.length;i++)for(let j=i+1;j<cards.length;j++)
    add(overlap(cards[i],cards[j]),'quest card '+i+' overlaps '+j);
  const navParts=['.lp-brand-mark','.lp-menu-toggle','.lp-nav-actions']
    .map(p=>({selector:p,el:pick(p),bbox:rect(p)})).filter(p=>visible(p.el));
  for(let i=0;i<navParts.length;i++)for(let j=i+1;j<navParts.length;j++)
    add(overlap(navParts[i].bbox,navParts[j].bbox),
       navParts[i].selector+' overlaps '+navParts[j].selector);
  const headerStyle=pick('.lp-header')?getComputedStyle(pick('.lp-header')).position:'';
  add(headerStyle!=='sticky','expected sticky header but got '+headerStyle);
  const img=pick('.lp-home-logo');
  add(!img || !img.complete || img.naturalWidth===0,'hero logo asset not loaded');
  return {width:innerWidth,scrollWidth:document.documentElement.scrollWidth,issues,
    heights:{header:header?.height,hero:hero?.height,summary:summary?.height,
      cards:quests?.height,shortcuts:shortcuts?.height},
    positions:{header:header?.top,hero:hero?.top,summary:summary?.top,
      quests:quests?.top,shortcuts:shortcuts?.top}};
})())"""

class CDP:
    def __init__(self, socket):
        self.ws=socket
        self.seq=0
    def call(self, method, params=None):
        self.seq+=1
        wanted=self.seq
        self.ws.send(json.dumps({"id":wanted,"method":method,"params":params or {}}))
        while True:
            m=json.loads(self.ws.recv())
            if m.get("id") != wanted:
                continue
            if "error" in m:
                raise RuntimeError(f"{method}: {m['error']}")
            return m.get("result",{})
    def eval(self, source):
        result=self.call("Runtime.evaluate",{"expression":source,"returnByValue":True})
        if "exceptionDetails" in result:
            raise RuntimeError(str(result["exceptionDetails"]))
        return result.get("result",{}).get("value")


def wait_for_remote_debugging(temp, proc):
    port_file=Path(temp)/"DevToolsActivePort"
    for _ in range(120):
        if port_file.exists():
            return int(port_file.read_text().splitlines()[0])
        if proc.poll() is not None:
            raise RuntimeError("Chrome exited early with code "+str(proc.returncode))
        time.sleep(.1)
    raise RuntimeError("Chrome debug endpoint did not start")


def main():
    executable=next((shutil.which(n) for n in (
        "google-chrome","google-chrome-stable","chromium","chromium-browser"
    ) if shutil.which(n)),None)
    if not executable:
        raise SystemExit("Chrome/Chromium unavailable; cannot verify UI geometry")
    with tempfile.TemporaryDirectory(prefix="kpah-home-browser-") as profile:
        args=[executable,"--headless=new","--no-sandbox","--disable-dev-shm-usage",
              "--disable-gpu","--no-first-run","--no-default-browser-check",
              "--remote-allow-origins=*","--remote-debugging-port=0",
              "--user-data-dir="+profile,"about:blank"]
        with open(os.devnull,"wb") as devnull:
            proc=subprocess.Popen(args,stdout=devnull,stderr=devnull)
            try:
                port=wait_for_remote_debugging(profile,proc)
                req=Request(f"http://127.0.0.1:{port}/json/new",method="PUT")
                target=json.loads(urlopen(req,timeout=10).read())
                conn=create_connection(target["webSocketDebuggerUrl"],timeout=15,
                                       suppress_origin=True)
                try:
                    dev=CDP(conn)
                    dev.call("Page.enable")
                    dev.call("Runtime.enable")
                    failures=[]
                    for width in WIDTHS:
                        dev.call("Emulation.setDeviceMetricsOverride",{
                            "width":width,"height":HEIGHT,"deviceScaleFactor":1,"mobile":False
                        })
                        dev.call("Page.navigate",{"url":BASE+"/"})
                        for attempt in range(70):
                            time.sleep(.2)
                            try:
                                state=dev.eval("document.readyState")
                                present=dev.eval("!!document.querySelector('.lp-home-destinations')")
                                if state=="complete" and present:
                                    break
                            except (RuntimeError,TypeError):
                                continue
                        else:
                            raise RuntimeError(f"Homepage did not load at {width}px")
                        time.sleep(.6)
                        data=json.loads(dev.eval(JS))
                        shot=dev.call("Page.captureScreenshot",{
                            "format":"png","captureBeyondViewport":False,
                            "fromSurface":True
                        })
                        path=SCREENSHOTS/f"home-{width}.png"
                        path.write_bytes(base64.b64decode(shot["data"]))
                        print(f"VIEWPORT {width}: "+json.dumps(data,ensure_ascii=False),flush=True)
                        if data["issues"]:
                            failures.append((width,data["issues"]))
                    if failures:
                        raise RuntimeError("Homepage layout collisions: "+json.dumps(failures,ensure_ascii=False))
                    print("PASS: no block/navbar overlap or horizontal overflow across all viewports")
                finally:
                    conn.close()
            finally:
                proc.terminate()
                try:
                    proc.wait(timeout=5)
                except subprocess.TimeoutExpired:
                    proc.kill()

if __name__=="__main__":
    main()
