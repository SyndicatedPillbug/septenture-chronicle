from pathlib import Path
import json,subprocess,time,wave,io,math,struct,re
import tempfile
here=Path(__file__).resolve().parent;ns={};exec((here/'scene-http.py').read_text().split('gm=Client();')[0],ns)
temp=tempfile.TemporaryDirectory(prefix='septenture-stage-test-')
Client=ns['Client'];gm=Client();gm.login('gm');player=Client();player.login('player');anon=Client();n=0

def check(label,ok):
 global n
 assert ok,label;n+=1;print('PASS',label,flush=True)
def get(c,path='state',group='stage'):
 code,raw,_=c.req('/session/'+group+'/'+path)
 try:return code,json.loads(raw)
 except:return code,raw
def act(c,action,group='stage',**body):
 body.setdefault('revision',get(c,group=group)[1].get('revision',0));code,raw,_=c.req('/session/'+group+'/'+action,json.dumps(body).encode(),{'Content-Type':'application/json','X-Tabletop-CSRF':c.csrf})
 try:return code,json.loads(raw)
 except:raise AssertionError((code,raw[:500]))
def sql(q):return subprocess.run(['podman','exec','cauldron-lab-db','mariadb','-ucauldron','-pcauldron','cauldron','-Nse',q],capture_output=True,check=True).stdout.decode().strip()
def upload(c,kind,name,bytes):
 boundary='stagefixture';body=(f'--{boundary}\r\nContent-Disposition: form-data; name="{kind}"; filename="{name}"\r\nContent-Type: application/octet-stream\r\n\r\n'.encode()+bytes+f'\r\n--{boundary}--\r\n'.encode());code,raw,_=c.req('/session/'+('stage/upload-audio' if kind=='audio' else 'kit/upload'),body,{'Content-Type':'multipart/form-data; boundary='+boundary,'X-Tabletop-CSRF':c.csrf});return code,json.loads(raw)
check('GM stage loads',get(gm)[0]==200)
check('anonymous stage rejected',get(anon)[0]==401)
check('player receives no exploration geometry',get(player)[1]['exploration'] is None)
check('shelf GM-only',get(player,'shelf')[0]==403)
check('CSRF required',gm.req('/session/stage/cover-all',b'{}',{'Content-Type':'application/json'})[0]==403)
for action in ['cover-all','reveal-all','sight-mode','area','grid','audio-set','media-display','media-edit','door','light']:
 check('player cannot '+action,act(player,action)[0]==403)
check('stale shared revision rejected',act(gm,'reveal-all',revision=-1)[0]==409)
check('bad template coordinates rejected',act(player,'template-save',shape='ruler',x=[],y=0,ex=2,ey=2)[0]==400)
check('zero-length template rejected',act(player,'template-save',shape='ruler',x=1,y=1,ex=1,ey=1)[0]==400)
# Owner-specific bookmarks: neither GM nor another player can bypass ownership.
code,state=act(player,'bookmark-save',title='Private test moment',source='**Do not share this.**');check('owner creates rich-source bookmark',code==200)
bid=state['bookmarkId'];b=next(b for b in state['bookmarks'] if b['id']==bid)
check('GM cannot list player bookmark',all(x['id']!=bid for x in get(gm)[1]['bookmarks']))
check('GM cannot edit player bookmark',act(gm,'bookmark-save',id=bid,itemRevision=b['revision'],title='forged',source='x')[0]==404)
check('GM cannot archive player bookmark',act(gm,'bookmark-archive',id=bid)[0]==404)
check('bookmark stale revision rejected',act(player,'bookmark-save',id=bid,itemRevision=-1,title='Stale',source='bad')[0]==409)
check('bookmark original preserved',next(x for x in get(player)[1]['bookmarks'] if x['id']==bid)['source']=='**Do not share this.**')
check('owner archive and restore',act(player,'bookmark-archive',id=bid,archived=True)[0]==200 and act(player,'bookmark-archive',id=bid,archived=False)[0]==200)
# Private templates never appear in GM state, shared templates do.
act(gm,'sight-mode',mode=0);act(gm,'reveal-all')
code,state=act(player,'template-save',shape='rectangle',label='Owner rectangle',x=1,y=1,ex=4,ey=3,shared=False);check('player creates private template',code==200)
tid=next(x['id'] for x in state['templates'] if x['label']=='Owner rectangle')
check('GM cannot list private player template',all(x['id']!=tid for x in get(gm)[1]['templates']))
code,state=act(gm,'template-save',shape='cone',label='Shared cone',x=1,y=1,ex=4,ey=3,shared=True);check('GM shares manual cone',code==200)
tid2=next(x['id'] for x in state['templates'] if x['label']=='Shared cone')
check('shared template visible without owner identifiers',any(x['id']==tid2 and 'subject' not in x for x in get(player)[1]['templates']))
check('player cannot remove another template',act(player,'template-remove',id=tid2)[0]==403)
check('owner can remove template',act(player,'template-remove',id=tid)[0]==200)
# Uploaded loop: private preview, scoped scene authorization and byte range.
buf=io.BytesIO()
with wave.open(buf,'wb') as w:
 w.setnchannels(1);w.setsampwidth(2);w.setframerate(22050);w.writeframes(b''.join(struct.pack('<h',int(1000*math.sin(2*math.pi*220*i/22050))) for i in range(22050)))
fixture=Path(temp.name)/'quiet-loop.wav';fixture.write_bytes(buf.getvalue());audio=buf.getvalue()
check('invalid audio rejected',upload(gm,'audio','unsafe.wav',b'<script>bad</script>')[0]==400)
check('player audio upload rejected',upload(player,'audio','quiet.wav',audio)[0]==403)
code,res=upload(gm,'audio','quiet-loop.wav',audio);aid=res['id'];check('GM private audio upload',code==200)
check('unselected audio denied to player',get(player,'audio?id='+aid)[0]==404)
check('private preview denied to player',get(player,'preview?id='+aid)[0]==403)
check('GM private preview exact bytes',gm.req('/session/stage/preview?id='+aid)[1]==audio)
check('scene audio selected explicitly',act(gm,'audio-set',id=aid)[0]==200)
check('selected loop reaches member',player.req('/session/stage/audio?id='+aid)[1]==audio)
code,body,headers=player.req('/session/stage/audio?id='+aid,headers={'Range':'bytes=10-19'});check('range playback supported',code==206 and body==audio[10:20] and headers['Content-Range'].startswith('bytes 10-19/'))
check('anonymous loop rejected',get(anon,'audio?id='+aid)[0]==401)
check('media tags searchable',act(gm,'media-edit',id=aid,title='Forest loop',tags='forest rain')[0]==200 and any(x['id']==aid for x in get(gm,'shelf?kind=audio&q=forest')[1]['items']))
check('media archive and restore',act(gm,'media-edit',id=aid,archived=True)[0]==200 and act(gm,'media-edit',id=aid,archived=False)[0]==200)
check('scene audio stop revokes byte access',act(gm,'audio-set',id='')[0]==200 and get(player,'audio?id='+aid)[0]==404)
# Local map pixels and unseen tokens must never leave the server.
fixtureImage=here/'scene-fixtures/moonlit-road.png';act(gm,'lock-map',group='kit',locked=False);check('fixture map installed in isolated map',gm.post('map',fixtureImage)[0]==200)
mid=get(gm)[1]['map']['id'];mapurl=sql(f'SELECT url FROM maps WHERE id={mid}')
check('grid scale and offsets save',act(gm,'grid',grid=50,offsetX=0,offsetY=0,showGrid=True,scale=5,unit='ft')[0]==200)
code,res=upload(gm,'image','unseen-sentinel.png',fixtureImage.read_bytes());img=res['image'];code,state=act(gm,'token-create',group='kit',name='Covered sentinel',image=img,width=2,height=2,x=5,y=3);check('isolated multi-cell token created',code==200)
piece=next(x for x in state['pieces'] if x['name']=='Covered sentinel');pid=int(piece['key'][5:]);asset=sql(f"SELECT CONCAT('/resources/',o.resources_key,'/tokens/',t.id,'.',t.extension) FROM map_token i JOIN tokens t ON t.id=i.token_id JOIN organisations o ON o.id=t.organisation_id WHERE i.id={pid}")
check('cover entire map',act(gm,'cover-all')[0]==200)
check('player map pixels differ from GM original',player.req(mapurl)[1]!=fixtureImage.read_bytes() and player.req(mapurl)[0]==200)
check('masked map endpoint identical protected pixels',player.req('/session/stage/map-image')[1]==player.req(mapurl)[1])
check('covered token omitted from kit state',all(x['key']!=piece['key'] for x in get(player,group='kit')[1]['pieces']))
check('covered token omitted from native DOM',('id="'+piece['key']+'"').encode() not in player.req('/adventure/1')[1])
check('covered token omitted from event snapshot',all(x['id']!=piece['key'] for x in json.loads(player.req('/session/events?init=1')[1])['pieces']))
check('covered token asset denied',player.req(asset)[0]==404)
check('partial reveal does not disclose multicell token',act(gm,'area',x=5,y=3,ex=6,ey=4,reveal=True)[0]==200 and player.req(asset)[0]==404)
check('full reveal authorizes visible token',act(gm,'area',x=5,y=3,ex=7,ey=5,reveal=True)[0]==200 and player.req(asset)[0]==200)
check('reconceal revokes token',act(gm,'area',x=5,y=3,ex=7,ey=5,reveal=False)[0]==200 and player.req(asset)[0]==404)
# Saved scene captures selected loop and undo restores previous selection.
act(gm,'audio-set',id=aid);code,state=act(gm,'scene-save',group='kit',title='Audio scene capture',intro='',mode='board',ambience='off');sid=next(x['id'] for x in state['scenes'] if x['title']=='Audio scene capture');act(gm,'audio-set',id='');code,state=act(gm,'scene-reveal',group='kit',id=sid);check('saved scene restores uploaded audio',code==200 and get(gm)[1]['audio']['id']==aid)
hid=next(x['id'] for x in state['history'] if x['kind']=='scene');check('undo scene restores prior audio',act(gm,'undo',group='kit',id=int(hid))[0]==200 and get(gm)[1]['audio'] is None)
check('clear cover restores pixels',act(gm,'reveal-all')[0]==200 and player.req(mapurl)[1]==fixtureImage.read_bytes())
check('stop fixtures without public publication',act(gm,'template-remove',id=tid2)[0]==200)
print(f'{n} stage checks passed',flush=True)
