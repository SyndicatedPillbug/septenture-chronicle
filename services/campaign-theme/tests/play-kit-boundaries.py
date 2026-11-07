from pathlib import Path
import json,subprocess,time,urllib.parse,base64
root=Path(__file__).resolve().parent;ns={'__file__':str(root/'play-kit-http.py')};exec((root/'play-kit-http.py').read_text().split('code,state=get(gm);')[0],ns)
g,p,a=ns['gm'],ns['player'],ns['anon'];get,act,check,sql=ns['get'],ns['act'],ns['check'],ns['sql'];tag=str(time.time_ns())[-8:]
r=ns['Client']();r.req('/session/handoff',urllib.parse.urlencode({'token':'recorder-'+'x'*40,'campaign':'septenture'}).encode(),{'Origin':'http://localhost:8802'});check('recorder cannot inspect play-kit',get(r)[0]==403)
old=get(g)[1]['revision'];check('valid shared revision accepted',act(g,'ambience',revision=old,preset='rain')[0]==200);check('stale shared write rejected',act(g,'ambience',revision=old,preset='off')[0]==409)
check('player cannot set ambience',act(p,'ambience',preset='off')[0]==403)
check('counter cannot name an outsider',act(g,'counters',rows=[{'label':'forged','value':1,'owner':'11111111-1111-4111-8111-111111111111'}])[0]==400)
code,s=act(g,'counters',rows=[{'label':'GM clock','value':1,'owner':''},{'label':'Player charge','value':2,'owner':get(p)[1]['account']}]);check('manual counters save',code==200)
x,y=s['state']['counters'];check('player cannot adjust GM counter',act(p,'counter-adjust',id=x['id'],value=2)[0]==403);check('assigned player adjusts own counter',act(p,'counter-adjust',id=y['id'],value=3)[0]==200)
# A unique handout image cannot stay visible through a formerly revealed edit.
fixture=root/'scene-fixtures/moonlit-road.png';boundary='kitboundary';payload=(f'--{boundary}\r\nContent-Disposition: form-data; name="image"; filename="private.png"\r\nContent-Type: image/png\r\n\r\n'.encode()+fixture.read_bytes()+f'\r\n--{boundary}--\r\n'.encode())
code,raw,_=g.req('/session/kit/upload',payload,{'Content-Type':'multipart/form-data; boundary='+boundary,'X-Tabletop-CSRF':g.csrf});assert code==200;image=json.loads(raw)['image']
_,s=act(g,'handout-save',title='Boundary letter '+tag,source='First revealed copy',image=image);h=next(x for x in s['handouts'] if x['title']=='Boundary letter '+tag)
act(g,'handout-reveal',id=h['id'],value=True);h=next(x for x in get(g)[1]['handouts'] if x['id']==h['id']);code,s=act(g,'handout-save',id=h['id'],itemRevision=int(h['revision']),title=h['title'],source='Private edit',image=image)
check('edited handout is withdrawn',code==200 and all(x['id']!=h['id'] for x in get(p)[1]['handouts']));check('withdrawn art is denied',p.req('/session/kit/media?id='+image)[0]==404)
check('stale handout revision rejected',act(g,'handout-save',id=h['id'],itemRevision=int(h['revision']),title=h['title'],source='stale',image=image)[0]==409)
check('player cannot post drawing checkpoint',act(p,'drawing',png='data:image/png;base64,'+base64.b64encode(fixture.read_bytes()).decode())[0]==403)
# Lock checks cover native persistence and broadcast channels, including recovery.
_,s=act(g,'token-create',name='Lock probe '+tag,image=image,width=1,height=1,x=2,y=2,controller=get(p)[1]['account']);piece=next(x for x in s['pieces'] if x['name']=='Lock probe '+tag);pid=int(piece['key'][5:])
def move():return p.req('/object/move',urllib.parse.urlencode({'instance_id':piece['key'],'pos_x':3,'pos_y':3}).encode(),{'Content-Type':'application/x-www-form-urlencoded','X-Tabletop-CSRF':p.csrf,'X-Requested-With':'XMLHttpRequest'})
check('unlocked assigned move accepted',move()[0]==200);history=get(p)[1]['history'];own=next(x for x in history if x['kind']=='move')
act(g,'token-edit',id=pid,name=piece['name'],width=1,height=1,locked=True)
check('locked native move denied',move()[0]==403)
for event in ['move','rotate']:
 code,_,_=p.req('/session/events',json.dumps({'action':event,'instance_id':piece['key'],'pos_x':4,'pos_y':4,'rotation':45}).encode(),{'Content-Type':'application/json','X-Tabletop-CSRF':p.csrf});check('locked '+event+' broadcast denied',code==403)
check('locked piece cannot be moved by player undo',act(p,'undo',id=int(own['id']))[0]==403)
# Native full name can differ from the verified display name. Startup must stay quiet.
name=sql('SELECT fullname FROM users WHERE id=3');before=int(sql("SELECT COUNT(*) FROM septenture_table_events WHERE JSON_UNQUOTE(JSON_EXTRACT(payload,'$.action'))='say'"))
p.req('/session/events',json.dumps({'action':'say','name':None,'mesg':name+' entered the session, playing Visible Hero.'}).encode(),{'Content-Type':'application/json','X-Tabletop-CSRF':p.csrf})
check('native-name startup announcement suppressed',int(sql("SELECT COUNT(*) FROM septenture_table_events WHERE JSON_UNQUOTE(JSON_EXTRACT(payload,'$.action'))='say'"))==before)
# Roll result remains canonical; re-sharing cannot roll again or duplicate chat.
headers={'Content-Type':'application/json','X-Tabletop-CSRF':p.csrf};code,raw,_=p.req('/session/tools/rolls',json.dumps({'expression':'1d20+2','mode':'normal','label':'Lantern watch '+tag}).encode(),headers);rolled=json.loads(raw);assert code in (200,201),(code,raw);roll=rolled['roll'];rid=roll['id'];check('roll description stored with result',roll['label']=='Lantern watch '+tag)
check('GM cannot see player private roll',rid not in g.req('/session/tools/rolls')[1].decode())
for _ in range(2):assert p.req('/session/tools/share-roll',json.dumps({'roll_id':rid}).encode(),headers)[0]==200
check('re-sharing one result does not duplicate chat',sql("SELECT COUNT(*) FROM septenture_table_events WHERE JSON_UNQUOTE(JSON_EXTRACT(payload,'$.roll_id'))='"+rid+"'")=='1')
check('label preserved in shared result','Lantern watch '+tag in json.loads(p.req('/session/chat')[1])['messages'][-1]['text'])
check('player state has no notebook content','notes' not in get(p)[1] and 'Private edit' not in json.dumps(get(p)[1]))
print(str(ns['checks'])+' boundary checks passed')
