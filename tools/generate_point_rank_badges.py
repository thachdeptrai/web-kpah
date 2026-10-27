#!/usr/bin/env python3
"""Build six fantasy K crests as transparent 192x192 WEBP images for membership ranks.
Only needs Pillow (CI generates binary images and commits to main after checks).
"""
from PIL import Image, ImageDraw, ImageFont, ImageFilter
from pathlib import Path
import math

SIZE=192
CANVAS=640
FONT_PATH="/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf"
BADGES=(
    ("dong",(170,93,43),(241,169,105),(94,44,26)),
    ("bac",(134,157,193),(235,247,255),(53,85,135)),
    ("vang",(231,162,28),(255,246,154),(155,82,6)),
    ("kim-cuong",(38,174,226),(217,253,255),(54,73,205)),
    ("cao-cap",(177,54,241),(255,186,255),(81,25,152)),
    ("huyen-thoai",(235,73,22),(255,202,102),(128,21,18)),
)
def blend(a,b,f):
    return tuple(round(a[j]*(1-f)+b[j]*f) for j in range(3))

def badge(edge,bright,shade,rank,name):
    im=Image.new("RGBA",(CANVAS,CANVAS),(0,0,0,0))
    aura=Image.new("RGBA",im.size,(0,0,0,0))
    g=ImageDraw.Draw(aura)
    for radius in range(265,145,-12):
        alpha=min(110,round(1.5*(265-radius)))
        g.ellipse((320-radius,320-radius,320+radius,320+radius),
                  outline=(*edge,alpha),width=15)
    im.alpha_composite(aura.filter(ImageFilter.GaussianBlur(35)))
    d=ImageDraw.Draw(im)
    # Ornate wing plates inspired by the fantasy membership concept.
    for side in (-1,1):
        for k in range(5+rank):
            y=180+k*31
            tip=320+side*(232+(rank>=3)*23)
            pts=[(320+side*118,y+12),(320+side*(160+k*3),y-25),
                 (tip,y-41+k*4),(320+side*204,y+17),(320+side*130,y+32)]
            d.polygon(pts,fill=(*shade,240))
            d.line(pts+[pts[0]],fill=(*bright,255),width=6,joint="curve")
            d.line([(320+side*154,y),(tip,y-35+k*4)],fill=(*edge,255),width=5)
    shield=[(320,55),(474,105),(522,240),(497,408),(412,512),
            (320,568),(228,512),(143,408),(118,240),(166,105)]
    d.polygon(shield,fill=(*shade,255))
    for shrink,color,width in ((0,bright,13),(16,edge,11),(32,bright,7)):
        factor=1-shrink/300
        pts=[(320+(x-320)*factor,320+(y-320)*factor) for x,y in shield]
        d.line(pts+[pts[0]],fill=(*color,255),width=width,joint="curve")
    inner=Image.new("RGBA",im.size,(0,0,0,0))
    di=ImageDraw.Draw(inner)
    for radius in range(200,30,-6):
        color=blend(shade,edge,(200-radius)/170*.6)
        di.ellipse((320-radius,310-radius,320+radius,310+radius),
                   fill=(*color,210))
    im.alpha_composite(inner.filter(ImageFilter.GaussianBlur(23)))
    d=ImageDraw.Draw(im)
    for n in range(8):
        angle=n*math.pi/4;radius=240
        x=int(320+math.cos(angle)*radius)
        y=int(310+math.sin(angle)*radius)
        d.polygon([(x,y-14),(x+11,y),(x,y+14),(x-11,y)],
                  fill=(*bright,220),outline=(*edge,255))
    font=ImageFont.truetype(FONT_PATH,330)
    bbox=d.textbbox((0,0),"K",font=font)
    x=(CANVAS-(bbox[2]-bbox[0]))/2-bbox[0]
    y=250-(bbox[3]-bbox[1])/2-bbox[1]
    d.text((x+8,y+14),"K",font=font,fill=(*shade,255),
           stroke_width=12,stroke_fill=(7,23,46,235))
    d.text((x,y),"K",font=font,fill=(*bright,255),
           stroke_width=8,stroke_fill=(*edge,255))
    d.line([(235,168),(425,115)],fill=(255,255,255,135),width=5)
    d.ellipse((310,94,330,114),fill=(255,255,255,240))
    # Metallic ribbon carrying the rank name, as in the provided concept.
    labels={"dong":"ĐỒNG","bac":"BẠC","vang":"VÀNG",
            "kim-cuong":"KIM CƯƠNG","cao-cap":"CAO CẤP",
            "huyen-thoai":"HUYỀN THOẠI"}
    label=labels[name]
    d.polygon([(134,463),(193,448),(447,448),(506,463),
               (480,527),(445,510),(195,510),(160,527)],
              fill=(*shade,255))
    d.line([(134,463),(193,448),(447,448),(506,463),
            (480,527),(445,510),(195,510),(160,527),(134,463)],
           fill=(*bright,255),width=8,joint="curve")
    label_font=ImageFont.truetype(FONT_PATH,31 if len(label)>9 else 39)
    bounds=d.textbbox((0,0),label,font=label_font)
    label_x=(CANVAS-(bounds[2]-bounds[0]))/2-bounds[0]
    d.text((label_x,466-bounds[1]),label,font=label_font,
           fill=(255,255,245,255),stroke_width=2,stroke_fill=(*shade,255))
    return im.resize((SIZE,SIZE),Image.Resampling.LANCZOS)

def main():
    target=Path(__file__).resolve().parents[1]/"assets/img"
    target.mkdir(parents=True,exist_ok=True)
    for rank,(name,edge,bright,shade) in enumerate(BADGES):
        path=target/("point-tier-"+name+".webp")
        badge(edge,bright,shade,rank,name).save(path,"WEBP",quality=80,method=6)
        opened=Image.open(path)
        assert opened.format=="WEBP" and opened.size==(SIZE,SIZE)
        assert opened.mode=="RGBA"
        print(path.relative_to(target.parent.parent),path.stat().st_size,"bytes")

if __name__=="__main__":
    main()
