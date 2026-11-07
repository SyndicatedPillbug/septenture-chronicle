import urllib.request as u,urllib.error as err,urllib.parse as url,http.cookiejar,re,json
from pathlib import Path
base='http://localhost:8081'
class Client:
 def __init__(self):self.op=u.build_opener(u.HTTPCookieProcessor(http.cookiejar.CookieJar()))
 def req(self,path,data=None,headers={}):
  try:r=self.op.open(u.Request(base+path,data=data,headers=headers),timeout=20)
  except err.HTTPError as e:r=e
  return r.code,r.read(),r.headers
 def login(self,who):
  code,body,_=self.req('/session/handoff',url.urlencode({'token':who+'-'+'x'*40,'campaign':'septenture'}).encode(),{'Origin':'http://localhost:8802'})
  assert code==200,(code,body[:100]);code,body,_=self.req('/adventure/1');self.csrf=re.search(rb"TABLETOP_CSRF='([a-f0-9]+)'",body)[1].decode()
 def post(self,mode,filename=None,csrf=None):
  boundary='septenturetestboundary';parts=[f'--{boundary}\r\nContent-Disposition: form-data; name="mode"\r\n\r\n{mode}\r\n'.encode()]
  if filename:
   p=Path(filename);parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="image"; filename="{p.name}"\r\nContent-Type: application/octet-stream\r\n\r\n'.encode()+p.read_bytes()+b'\r\n')
  parts.append(f'--{boundary}--\r\n'.encode());return self.req('/session/scene',b''.join(parts),{'Content-Type':'multipart/form-data; boundary='+boundary,'X-Tabletop-CSRF':self.csrf if csrf is None else csrf})
def check(label,ok):assert ok,label;print('PASS',label)
gm=Client();gm.login('gm');player=Client();player.login('player');anon=Client()
fixture=Path(__file__).parent/'scene-fixtures/moonlit-road.png'
check('GM raster upload',gm.post('scene',fixture)[0]==200)
state=json.loads(player.req('/session/scene')[1])['scene'];image=state['url']
check('player receives same scene',state['mode']=='scene' and player.req(image)[1]==fixture.read_bytes())
check('anonymous art denied',anon.req(image)[0] in (401,403))
check('player cannot set scene',player.post('board')[0]==403)
check('CSRF required',gm.post('board',csrf='bad')[0]==403)
check('active SVG rejected',gm.post('scene',Path(__file__).parent/'scene-fixtures/unsafe.svg')[0]==400)
check('bad mode rejected',gm.post('arbitrary')[0]==400)
check('path traversal cannot address art',player.req('/session/scene/image?revision=../../config.php')[0]==404)
check('backdrop switches without reupload',gm.post('backdrop')[0]==200 and json.loads(player.req('/session/scene')[1])['scene']['mode']=='backdrop')
check('return to board preserves reusable art',gm.post('board')[0]==200 and json.loads(player.req('/session/scene')[1])['scene']['mode']=='board')
# Server names, plain text and bounded authorized conversation.
code,_,_=player.req('/session/events',json.dumps({'action':'say','name':'FORGED','mesg':'Scene test <script>hello</script>'}).encode(),{'Content-Type':'application/json','X-Tabletop-CSRF':player.csrf});check('chat send accepted',code==200)
messages=json.loads(gm.req('/session/chat')[1])['messages'];last=messages[-1]
check('chat identity server-derived; text round-trips literally',last['name']!='FORGED' and last['text']=='Scene test <script>hello</script>')
check('chat bounded to 60 rows',len(messages)<=60)
check('anonymous conversation denied',anon.req('/session/chat')[0] in (401,403))
rec=Client();code,_,_=rec.req('/session/handoff',url.urlencode({'token':'recorder-'+'x'*40,'campaign':'septenture'}).encode(),{'Origin':'http://localhost:8802'})
check('recorder cannot retrieve chat',rec.req('/session/chat')[0] in (401,403));check('recorder cannot retrieve art',rec.req(image)[0] in (401,403))


# The GM can use the same picker path to replace the actual map, not just an overlay.
check('player map replacement denied',player.post('map',fixture)[0]==403)
check('map replacement requires CSRF',gm.post('map',fixture,csrf='bad')[0]==403)
check('GM quick map upload',gm.post('map',fixture)[0]==200)
page=gm.req('/adventure/1')[1]
asset=re.search(rb'/resources/[a-zA-Z0-9_-]+/maps/scene-[a-f0-9]{32}[.]png',page)
check('new map is used by the active board',bool(asset))
check('player can read active map art',player.req(asset[0].decode())[1]==fixture.read_bytes())
check('anonymous map art denied',anon.req(asset[0].decode())[0] in (401,403,404))
# Reload notices should never become conversation; normal text remains visible.
announcement={'action':'say','name':None,'mesg':'Fixture GM entered the session, being the Dungeon Master.'}
for _ in range(3):
 code,_,_=gm.req('/session/events',json.dumps(announcement).encode(),{'Content-Type':'application/json','X-Tabletop-CSRF':gm.csrf})
 assert code==200
messages=json.loads(gm.req('/session/chat')[1])['messages']
check('reload announcements are absent from conversation',all('entered the session, being the' not in m['text'] for m in messages))
print('23 scene/chat/map checks passed')
