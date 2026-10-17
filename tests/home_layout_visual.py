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
  const header=rect('.lp-header'), hero=rect('.home-hero'), summary=rect('.home-stats'),
        quests=rect('.home-discover'), shortcuts=rect('.home-shortcuts'),
        logo=rect('.home-hero h1'), subtitle=rect('.home-hero-copy>p'),
        actions=rect('.home-actions');
  add(!document.body.classList.contains('kp-home-page'),'missing homepage scope');
  add(!pick('link[href*="home-layout.css"]'),'missing isolated CSS');
  add(document.documentElement.scrollWidth > innerWidth+2,
      'horizontal document overflow '+document.documentElement.scrollWidth+'/'+innerWidth);
  add(!header || !hero || !summary || !quests || !shortcuts,'required block missing');
  add(header && hero && header.bottom > hero.top+2,'header overlaps hero');
  add(hero && summary && hero.bottom > summary.top+2,'hero overlaps statistics');
  add(summary && quests && summary.bottom > quests.top+2,'stats/events overlaps quests');
  add(quests && shortcuts && quests.bottom > shortcuts.top+2,'quests overlaps shortcuts');
  add(logo && subtitle && logo.bottom > subtitle.top+2,'hero heading overlaps subtitle');
  add(subtitle && actions && subtitle.bottom > actions.top+2,'subtitle overlaps actions');
  const stats = [...document.querySelectorAll('.home-stat')].map(e=>e.getBoundingClientRect());
  const cards = [...document.querySelectorAll('.home-panel')].map(e=>e.getBoundingClientRect());
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
  const statCount = document.querySelectorAll('.home-stat').length;
  const cardCount = document.querySelectorAll('.home-panel').length;
  add(statCount !== 4, 'expected four statistic blocks, got '+statCount);
  add(cardCount !== 4, 'expected four feature panels, got '+cardCount);
  document.querySelectorAll('.home-panel img').forEach(img => {
    add(!img.complete || img.naturalWidth === 0, 'feature artwork failed: '+img.getAttribute('src'));
  });
  const img=pick('.home-hero-scene');
  add(!img || !img.complete || img.naturalWidth===0,'hero scene asset not loaded');
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
    # Chromium can create DevToolsActivePort before writing the port number.
    # Retry until the file has a valid first line; do not treat an empty file
    # as a browser failure (flaky on loaded GitHub Actions runners).
    for _ in range(200):
        if port_file.exists():
            try:
                lines = port_file.read_text().splitlines()
                if lines and lines[0].strip().isdigit():
                    return int(lines[0])
            except OSError:
                pass
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
    with tempfile.TemporaryDirectory(prefix="kpah-home-browser-", ignore_cleanup_errors=True) as profile:
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
                                present=dev.eval("!!document.querySelector('.home-shortcuts')")
                                if state=="complete" and present:
                                    break
                            except (RuntimeError,TypeError):
                                continue
                        else:
                            raise RuntimeError(f"Homepage did not load at {width}px")
                        # Load lazy artwork throughout the page before checking its dimensions.
                        dev.eval("document.querySelectorAll('.home-panel img').forEach(img => img.loading = 'eager')")
                        for _ in range(50):
                            if dev.eval("Array.from(document.querySelectorAll('.home-panel img')).every(img => img.complete)"):
                                break
                            time.sleep(.1)
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
