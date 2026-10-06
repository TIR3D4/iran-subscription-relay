#!/usr/bin/env python3
"""Local HTTPS upstreams and real PHP HTTP entrypoints; no live tokens."""
import http.server, ssl, threading, tempfile, subprocess, pathlib, shutil, socket, time, urllib.request, urllib.error, http.cookiejar, re, urllib.parse
ROOT=pathlib.Path(__file__).resolve().parents[1]
def port():
    with socket.socket() as s: s.bind(('127.0.0.1',0)); return s.getsockname()[1]
state={'status':200,'calls':[],'body':b'vless://synthetic-config'}
class Panel(http.server.BaseHTTPRequestHandler):
    def log_message(self,*args): pass
    def do_GET(self):
        state['calls'].append((self.path,self.headers.get('User-Agent'),self.headers.get('Cookie'),self.headers.get('Authorization')))
        status=state['status'] if self.path.startswith('/m/') else 200
        self.send_response(status)
        self.send_header('Content-Type','text/plain')
        self.send_header('Subscription-Userinfo','upload=1; download=2; total=3; expire=4')
        self.send_header('Profile-Update-Interval','12')
        self.send_header('Profile-Web-Page-Url',f'https://localhost:{up_port}/m/sub/TOKEN/info')
        if status==302: self.send_header('Location',f'https://localhost:{up_port}/p/sub/TOKEN')
        self.end_headers(); self.wfile.write(state['body'])
def check(ok,name):
    assert ok,name
    print('PASS',name)
with tempfile.TemporaryDirectory() as folder:
    tmp=pathlib.Path(folder); app=tmp/'app'; shutil.copytree(ROOT/'src',app)
    cert=tmp/'cert.pem'; key=tmp/'key.pem'
    subprocess.run(['openssl','req','-x509','-newkey','rsa:2048','-nodes','-keyout',str(key),'-out',str(cert),'-days','1','-subj','/CN=localhost','-addext','subjectAltName=DNS:localhost'],check=True,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
    up_port=port(); web_port=port()
    upstream=http.server.ThreadingHTTPServer(('127.0.0.1',up_port),Panel)
    context=ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER); context.load_cert_chain(cert,key)
    upstream.socket=context.wrap_socket(upstream.socket,server_side=True)
    threading.Thread(target=upstream.serve_forever,daemon=True).start()
    router=tmp/'router.php'
    router.write_text("<?php if (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)==='/installer.php') {require "+repr(str(app/'installer.php'))+";} else {require "+repr(str(app/'index.php'))+";}")
    proc=subprocess.Popen(['php','-d','curl.cainfo='+str(cert),'-d','session.save_path='+str(tmp),'-S',f'127.0.0.1:{web_port}','-t',str(app),str(router)],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
    base=f'http://127.0.0.1:{web_port}'
    opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    def request(path,method='GET',data=None,headers=None):
        req=urllib.request.Request(base+path,data=urllib.parse.urlencode(data).encode() if data is not None else None,method=method,headers=headers or {})
        try: res=opener.open(req,timeout=10)
        except urllib.error.HTTPError as err: res=err
        return res.status,res.headers,res.read()
    try:
        for _ in range(100):
            try: request('/'); break
            except urllib.error.URLError: time.sleep(.03)
        else: raise RuntimeError('PHP server did not start')
        def config(mode='dual',bad_tls=False,max_bytes=8388608):
            host='127.0.0.1' if bad_tls else 'localhost'
            order="['marzban','pasarguard']" if mode=='dual' else f"['{mode}']"
            (app/'config.php').write_text("<?php return ['public_url'=>'https://sub.example.com','mode'=>'"+mode+"','order'=>"+order+",'panels'=>['marzban'=>'https://"+host+f":{up_port}/m/sub','pasarguard'=>'https://localhost:{up_port}/p/sub'],'connect_timeout'=>1,'timeout'=>2,'max_bytes'=>{max_bytes}];")
        config()
        for status in [200,404,401,403,429,500,503,302]:
            state.update(status=status,calls=[])
            code,headers,body=request('/sub/TOKEN/clash?x=1',headers={'User-Agent':'synthetic-client','Cookie':'secret=1','Authorization':'Bearer secret'})
            check(len(state['calls'])==(2 if status==404 else 1),f'HTTP fallback {status}')
            check(code==(200 if status==404 else 502 if status==302 else status),f'status {status}')
            check(state['calls'][0][0]=='/m/sub/TOKEN/clash?x=1','suffix and query forwarded')
            check(state['calls'][0][1]=='synthetic-client' and state['calls'][0][2:] == (None,None),'UA preserved; credentials excluded')
            if status==200:
                check(body==state['body'] and headers['Subscription-Userinfo'].startswith('upload=1'),'body and subscription headers')
                check(headers['Profile-Web-Page-Url']=='https://sub.example.com/sub/TOKEN/info','profile page URL rewritten')
        state.update(status=200,calls=[])
        code,headers,body=request('/sub/TOKEN',method='HEAD')
        check(code==200 and body==b'' and headers['Profile-Update-Interval']=='12','HEAD metadata')
        for path in ['/','/sub/','/sub/../config.php','/sub/TOKEN%2Fsecret','/sub/TOKEN/%2e%2e','/api/users']:
            state['calls']=[]; check(request(path)[0]==404 and not state['calls'],'invalid route '+path)
        check(request('/sub/TOKEN',method='POST',data={})[0]==405,'POST rejected')
        for mode in ['marzban','pasarguard']:
            config(mode); state.update(status=404,calls=[])
            code,_,_=request('/sub/TOKEN'); check(len(state['calls'])==1 and code==(404 if mode=='marzban' else 200),'single mode '+mode)
        config(bad_tls=True); state['calls']=[]
        check(request('/sub/TOKEN')[0]==502 and not state['calls'],'invalid TLS never falls back')
        config(max_bytes=2); state.update(status=200,calls=[])
        check(request('/sub/TOKEN')[0]==502 and len(state['calls'])==1,'response cap')
        (app/'config.php').unlink()
        check(request('/sub/TOKEN')[0]==503,'unconfigured relay')
        (app/'setup-key.php').write_text("<?php return 'test-only-secret-12345678901234567890';")
        def csrf(body): return re.search(rb'name="csrf" value="([a-f0-9]+)"',body).group(1).decode()
        _,_,body=request('/installer.php'); token=csrf(body)
        _,_,body=request('/installer.php','POST',{'csrf':token,'key':'wrong'})
        check('کلید نصب صحیح نیست'.encode() in body,'installer rejects wrong key')
        _,_,body=request('/installer.php','POST',{'csrf':'wrong','key':'test-only-secret-12345678901234567890'})
        check('صفحه را تازه کنید'.encode() in body,'installer CSRF')
        _,_,body=request('/installer.php','POST',{'csrf':token,'key':'test-only-secret-12345678901234567890'})
        check('تنظیمات اتصال'.encode() in body,'installer login')
        state.update(status=404,calls=[])
        _,_,body=request('/installer.php','POST',{'csrf':token,'action':'review','mode':'dual','public_url':'https://sub.example.com','marzban':f'https://localhost:{up_port}/m/sub','pasarguard':f'https://localhost:{up_port}/p/sub','connect_timeout':1,'timeout':2})
        check('تأیید نهایی'.encode() in body and not (app/'config.php').exists(),'review before write')
        _,_,body=request('/installer.php','POST',{'csrf':token,'action':'save'})
        check((app/'config.php').exists() and 'تنظیمات ذخیره شد'.encode() in body,'config created')
        check(request('/installer.php')[0]==403,'installer locked after save')
    finally:
        proc.terminate(); proc.wait(timeout=5); upstream.shutdown(); upstream.server_close()
print('All HTTP integration checks passed.')
