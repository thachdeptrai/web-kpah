"""HTTP regression against a disposable kpah_route_test fixture. No external services."""
import html, http.cookiejar, json, os, re, subprocess, urllib.error, urllib.parse, urllib.request
from pathlib import Path
BASE=os.environ.get('KPAH_TEST_URL','http://127.0.0.1:8089').rstrip('/')
ROOT=Path(__file__).resolve().parents[1]
PHP=os.environ.get('PHP_BIN','php')
passed=0
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*args,**kwargs): return None
class Client:
    def __init__(self):
        self.cookies=http.cookiejar.CookieJar()
        self.opener=urllib.request.build_opener(NoRedirect(),urllib.request.HTTPCookieProcessor(self.cookies))
    def request(self,path,data=None,method=None,headers=None):
        payload=urllib.parse.urlencode(data).encode() if isinstance(data,dict) else data
        req=urllib.request.Request(BASE+path,data=payload,method=method,headers={'User-Agent':'KPAH regression',**(headers or {})})
        try:r=self.opener.open(req,timeout=10)
        except urllib.error.HTTPError as e:r=e
        raw=r.read()
        return r.code,r.headers,raw.decode('utf-8',errors='replace')
def check(ok,label):
    global passed
    if not ok:raise AssertionError(label)
    passed+=1
    print('PASS:',label)
def csrf(body):
    m=re.search(r'name="csrf"\s+value="([^"]+)"',body)
    if not m:raise AssertionError('CSRF input missing')
    return html.unescape(m[1])
def clean_page(c,path):
    status,headers,body=c.request(path)
    check(status==200,path+' loads')
    check(not re.search(r'(?:href|src|action|data-[\w-]*endpoint)=["\'][^"\']*\.php',body),path+' has clean links/forms')
    check(not re.search(r'(?:Fatal error|Warning:|Stack trace|SQLSTATE)',body),path+' hides technical failures')
    for url in set(re.findall(r'(?:href|src)="(/assets/[^"?]+)',body)):
        check(c.request(url)[0]==200,path+' asset '+url)
    return body
for ext in ('png','jpg','jpeg','gif','webp','ico','woff','woff2'):
    candidates=list((ROOT/'assets').rglob('*.'+ext))
    if candidates:
        asset='/' + candidates[0].relative_to(ROOT).as_posix()
        check(Client().request(asset)[0]==200,'static asset delivered '+asset)
catalog=json.loads(subprocess.check_output([PHP,'-r','echo json_encode(require '+repr(str(ROOT/'config/routes.php'))+');'],text=True))
guest=Client()
for old,new in catalog['aliases'].items():
    route=catalog['routes'][new]
    status,headers,body=guest.request(old+'?probe=hello%20world')
    if route['redirect_legacy']:
        check(status==301 and headers.get('Location')==new+'?probe=hello%20world','legacy redirect/query '+old)
    else:
        check(status!=301,'legacy API/callback stays in place '+old)
for path in ['/','/dang-nhap','/dang-ky','/quen-mat-khau','/quan-tri/dang-nhap']:
    clean_page(guest,path)
landing_body=clean_page(guest,'/')
check('id="tai-game"' in landing_body,'landing has download section')
check('href="#tai-game"' in landing_body,'landing has download CTA')
for platform in ('Android','Windows','Java / JAR','iPhone / iPad'):
    check(platform in landing_body,'landing download platform '+platform)
check('lp-download-card' in landing_body,'landing download cards render')
for path in ['/tai-khoan','/ho-so','/nhan-vat','/bang-xep-hang','/nap','/nap-the','/nap-ngan-hang','/lich-su-nap','/top-nap','/vi-vnd','/diem-tich-luy']:
    status,h,b=guest.request(path)
    check(status==302 and h.get('Location','').startswith('/dang-nhap'),'guest login guard '+path)
for path in ['/quan-tri','/quan-tri/diem-nong-dan/lich-su?id=202','/quan-tri/tai-game','/quan-tri/diem-nong-dan','/quan-tri/ma-gioi-thieu','/quan-tri/nap','/quan-tri/ty-le','/quan-tri/cong-tai-nguyen','/quan-tri/cong-vnd','/quan-tri/cong-tong-nap','/quan-tri/doi-soat','/quan-tri/tai-khoan-key']:
    status,h,b=guest.request(path)
    check(status==302 and h.get('Location')=='/quan-tri/dang-nhap','admin guard '+path)
check(guest.request('/tai-game/tep?nen-tang=android')[0]==404,'disabled download returns 404')
check(guest.request('/tai-game/tep?nen-tang=java')[0]==404,'disabled Java JAR download returns 404')
check(guest.request('/tai-game/tep?nen-tang=invalid')[0]==404,'invalid platform rejected')
check(guest.request('/tai-game/tep?nen-tang=android',method='POST')[0]==405,'download accepts GET and HEAD only')
check(guest.request('/storage/game-downloads/catalog.json')[0]==403,'private release catalog blocked')
for path in ['/app/pages/login.php','/config/routes.php','/tools/check.php','/includes/db.php','/storage/topup/rates.json','/database/referral_codes.sql','/tests/seed_fixture.php','/.git/config','/.env','/config.register.local.php','/check.php','/backup.sql','/dump.zip']:
    check(guest.request(path)[0]==403,'private HTTP boundary '+path)
for path in ['/missing-route','/madeup.php','/api/missing','/index.php/extra','/quan-tri/nap-game']:
    check(guest.request(path)[0]==404,'unknown route '+path)
status,h,b=guest.request('/dang-nhap/?x=1')
check(status==301 and h['Location']=='/dang-nhap?x=1','trailing slash canonical')
check(guest.request('/dang-nhap%0d%0aX-Test:bad')[0]==400,'reject encoded CRLF')
status,h,b=guest.request('/login.php',{'username':'nobody','password':'wrong','csrf':'bad'})
check(status==200 and 'Phiên đăng nhập đã hết hạn' in b,'legacy POST body handled without redirect')
for path in ['/api/register','/api/register.php']:
    check(guest.request(path)[0]==405,'GET registration rejected '+path)
    status,h,b=guest.request(path,{})
    check(status==403 and json.loads(b)['code']=='csrf','registration CSRF '+path)
    check(guest.request(path,b'x'*9000,method='POST')[0]==413,'registration request limit '+path)
for path in ['/api/card-callback','/topup/card-callback.php']:
    # Card provider callback legitimately accepts signed GET as well as POST.
    status,h,b=guest.request(path)
    check(status==503 and b=='UNAVAILABLE','disabled provider callback GET '+path)
    status,h,b=guest.request(path,{'request_id':'invalid'})
    check(status==503 and b=='UNAVAILABLE' and 'Location' not in h,'disabled provider callback POST unchanged '+path)
status,h,b=guest.request('/captcha')
check(status==200 and h.get('Content-Type','').startswith(('image/png','image/svg+xml')),'captcha generates image')
# Open admin form first; player login must not invalidate its CSRF/session.
c=Client()
admin_token=csrf(c.request('/quan-tri/dang-nhap')[2])
user_token=csrf(c.request('/dang-nhap')[2])
status,h,b=c.request('/login.php',{'csrf':user_token,'username':'routeplayer','password':'FixturePass123'})
check(status==302 and h.get('Location')=='/tai-khoan','player login via old POST endpoint')
check(c.cookies._cookies is not None,'session cookie jar populated')
status,h,b=c.request('/admin/login.php',{'csrf':admin_token,'username':'routeadmin','password':'FixtureAdmin123'})
check(status==302 and h.get('Location')=='/quan-tri','admin form survives player login')
check({'KPAHREG','KPAHADMIN'}.issubset({cookie.name for cookie in c.cookies}),'distinct admin/player cookies')
for path in ['/tai-khoan','/ho-so','/nhan-vat','/bang-xep-hang','/nap','/nap-the','/nap-ngan-hang','/lich-su-nap','/quan-tri/tai-game','/quan-tri','/quan-tri/ma-gioi-thieu','/quan-tri/diem-nong-dan','/quan-tri/nap','/quan-tri/ty-le','/quan-tri/cong-tai-nguyen','/quan-tri/doi-soat','/quan-tri/tai-khoan-key']:
    body=clean_page(c,path)
    if path=='/nhan-vat':
        check('FixtureHero' in body and 'OtherHero' not in body,'character ownership never crosses accounts')
        check('Điểm nông dân' in body and '777' in body,'character farmer points are visible')
    if path=='/tai-khoan':
        for feature in ('Ví VNĐ hiện có','Tổng đã nạp','Point tích lũy','Nhân vật của tôi','Giao dịch','Bảo mật tài khoản'):
            check(feature.lower() in body.lower(),'dashboard widget '+feature)
        check('FixtureHero' in body and 'OtherHero' not in body,'private dashboard character preview')
        check('Điểm nông dân' in body and '777' in body,'private dashboard farmer points')
        check('/assets/css/dashboard.css' in body and '/assets/css/site-responsive.css' in body,
              'account dashboard and responsive CSS are loaded')
    if path=='/quan-tri/tai-game':
        check('Java / JAR' in body and 'accept=".jar"' in body, 'Admin has dedicated Java JAR upload control')
        check('name="enabled[java]"' in body and 'name="url[java]"' in body, 'Admin has Java URL and enable settings')
    if path=='/quan-tri/diem-nong-dan':
        check('Lượng up TB/giờ' in body,'admin hourly Luong column')
        check('/quan-tri/diem-nong-dan/lich-su?id=' in body,'admin character history links')
        check('FixtureHero' in body and '777' in body,'admin farmer points from stored pInfo')
        check('Online farm/ngày' in body and 'Hộp/ngày' in body,'admin farmer diagnostic fields')
    if path=='/quan-tri':
        check('/quan-tri/nap-game' not in body and '/quan-tri/cong-tong-nap' not in body,
              'retired admin routes not shown')

# End-to-end JAR lifecycle: privileged multipart upload, public binary download,
# range support and revocation. Uses only disposable fixture storage and accounts.
import io, zipfile
buf=io.BytesIO()
with zipfile.ZipFile(buf,'w',compression=zipfile.ZIP_DEFLATED) as jarzip:
    jarzip.writestr('META-INF/MANIFEST.MF','Manifest-Version: 1.0\nMain-Class: kpah.Main\n\n')
jar_bytes=buf.getvalue()
form=clean_page(c,'/quan-tri/tai-game')
jar_fields={
    'csrf':csrf(form),
    'MAX_FILE_SIZE':'536870912',
    'enabled[java]':'1',
    'source[windows]':'url','source[android]':'url','source[ios]':'url','source[java]':'file',
    'url[windows]':'','url[android]':'','url[ios]':'','url[java]':''
}
boundary='----KPAHJarIntegrationTest'
part=[]
for name,value in jar_fields.items():
    part.append(('--'+boundary+'\r\nContent-Disposition: form-data; name="'+name+'"\r\n\r\n'+value+'\r\n').encode('utf-8'))
part.append(('--'+boundary+'\r\nContent-Disposition: form-data; name="upload[java]"; filename="Nghien_KPAH.jar"\r\nContent-Type: application/java-archive\r\n\r\n').encode('utf-8'))
part.append(jar_bytes+b'\r\n')
part.append(('--'+boundary+'--\r\n').encode('ascii'))
status,head,body=c.request('/quan-tri/tai-game',b''.join(part),headers={
    'Content-Type':'multipart/form-data; boundary='+boundary,
    'X-Requested-With':'XMLHttpRequest'
})
check(status==200 and json.loads(body)['ok'],'admin multipart JAR upload and enable')
status,head,body=guest.request('/tai-game/tep?nen-tang=java',method='HEAD')
check(status==200 and int(head.get('Content-Length','0'))==len(jar_bytes),'public JAR HEAD and file size')
check('Nghien_KPAH.jar' in head.get('Content-Disposition',''),'JAR download retains filename')
status,head,body=guest.request('/tai-game/tep?nen-tang=java',headers={'Range':'bytes=0-3'})
check(status==206 and head.get('Content-Range')=='bytes 0-3/'+str(len(jar_bytes)) and body=='PK\x03\x04','JAR download supports range resume')
check('/tai-game/tep?nen-tang=java' in guest.request('/')[2],'JAR link visible on homepage after publish')
remove_fields={**jar_fields,'remove[java]':'1'}
remove_fields.pop('enabled[java]')
remove_fields['csrf']=csrf(c.request('/quan-tri/tai-game')[2])
status,head,body=c.request('/quan-tri/tai-game',remove_fields,headers={'X-Requested-With':'XMLHttpRequest'})
check(status==200 and json.loads(body)['ok'],'admin disables and removes uploaded JAR')
check(guest.request('/tai-game/tep?nen-tang=java')[0]==404,'revoked JAR is not publicly accessible')

history=clean_page(c,'/quan-tri/diem-nong-dan/lich-su?id=202')
check('OtherHero' in history and 'FixtureHero' not in history,'admin single-character farmer history')
check('Lượng up TB/1 giờ' in history and 'Biến động Lượng theo từng giờ' in history,'historical Luong metrics and graph')
check('Điểm trước' in history and 'Lượng trước' in history,'history before/after values')
check('Mất mẫu / lưu chậm' in history or 'Biến động quan sát' in history,'history event annotations')
check(c.request('/quan-tri/diem-nong-dan/lich-su?id=nope')[0]==404,'history rejects invalid character IDs')
farmer_filtered=clean_page(c,'/quan-tri/diem-nong-dan?q=FixtureHero&state=high&alert=500')
check('FixtureHero' in farmer_filtered and 'OtherHero' not in farmer_filtered,'admin farmer search and threshold')
farmer_empty=clean_page(c,'/quan-tri/diem-nong-dan?state=high&alert=100000')
check('Không có nhân vật phù hợp' in farmer_empty,'farmer high-point filter excludes low values')
for category in ['level','honor','pk','clan','nap','event','duel','siege','wc']:
    status,h,b=c.request('/bang-xep-hang?type='+category)
    check(status==200 and 'Chưa tải được bảng xếp hạng' not in b,'schema-backed leaderboard '+category)
    check(not re.search('tob_|team_user|point1|nPK',b),'no schema names in leaderboard copy '+category)
check(c.request('/bang-xep-hang?type='+urllib.parse.quote('level; DROP TABLE team_user'))[0]==404,'leaderboard injection rejected')
profile=clean_page(c,'/ho-so');token=csrf(profile)
for path in ['/api/change-password','/api/change-password.php']:
    check(c.request(path,{'csrf':'bad'})[0]==403,'password CSRF '+path)
status,h,b=c.request('/api/user-profile',{'csrf':token,'email':'routeplayer@example.test','phone':'0912345678'})
check(status==200 and json.loads(b)['ok'],'profile update with canonical endpoint')
check('0912345678' in c.request('/ho-so')[2],'profile update persisted')
status,h,b=c.request('/api/user-profile.php',{'csrf':token,'email':'other@example.test','phone':'0912345678'})
check(status==403,'email changes still require verification')
status,h,b=c.request('/api/change-password',{'csrf':token,'current_password':'FixturePass123','new_password':'ChangedPass123','confirm_password':'ChangedPass123'})
check(status==200 and json.loads(b)['ok'],'password update persists')
status,h,b=c.request('/api/logout')
check(status==302 and h.get('Location')=='/dang-nhap','player logout canonical')
check(c.request('/quan-tri')[0]==200,'player logout preserves admin session')
check(c.request('/tai-khoan')[0]==302,'player session revoked')
token=csrf(c.request('/dang-nhap')[2])
status,h,b=c.request('/dang-nhap',{'csrf':token,'username':'routeplayer','password':'ChangedPass123'})
check(status==302 and h.get('Location')=='/tai-khoan','login with changed password')
c.request('/quan-tri/dang-xuat')
check(c.request('/tai-khoan')[0]==200,'admin logout preserves player session')
check(c.request('/quan-tri')[0]==302,'admin session revoked')
# Simulate delivered OTP only; verification and account creation use real APIs.
# Session fixture injection is optional for Apache (different OS session owner/path).
if os.environ.get('KPAH_TEST_SESSION_FIXTURE')=='1':
    new=Client()
    token=csrf(new.request('/dang-ky')[2])
    sid=next(cookie.value for cookie in new.cookies if cookie.name=='KPAHREG')
    subprocess.run([PHP,str(ROOT/'tests/fixture_session.php'),sid],check=True)
    status,h,b=new.request('/api/verify-email-code',{'csrf':token,'email':'wrong@example.test','email_code':'123456'})
    check(status==422,'OTP is bound to email')
    token=json.loads(b)['csrf']
    status,h,b=new.request('/api/verify-email-code.php',{'csrf':token,'email':'newroute@example.test','email_code':'123456'})
    check(status==200 and json.loads(b)['ok'],'OTP verification through legacy API')
    token=json.loads(b)['csrf']
    data={'csrf':token,'username':'newroute','password':'NewRoutePass123','password2':'NewRoutePass123','phone':'0912345678','email':'newroute@example.test','agree':'1','referral_code':'INVALID'}
    status,h,b=new.request('/api/register',data)
    check(status==422 and json.loads(b)['code']=='invalid_referral','invalid referral rejected')
    data.update(csrf=json.loads(b)['csrf'],referral_code='ROUTETEST')
    status,h,b=new.request('/api/register.php',data)
    check(status==200 and json.loads(b)['ok'],'registration succeeds with schema fixture and verified email')
    token=csrf(new.request('/dang-nhap')[2])
    status,h,b=new.request('/dang-nhap',{'csrf':token,'username':'newroute','password':'NewRoutePass123'})
    check(status==302 and h.get('Location')=='/tai-khoan','new account can log in')
    status,h,b=new.request('/nhan-vat')
    check(status==200 and 'FixtureHero' not in b and 'OtherHero' not in b,'new account sees no other player characters')
print('TOTAL HTTP CHECKS:',passed)
