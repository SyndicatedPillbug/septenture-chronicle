from pathlib import Path
import json,subprocess,base64,time
root=Path(__file__).resolve().parent;ns={};script=root/'scene-http.py';exec(script.read_text().split('gm=Client();')[0],ns)
tag=str(time.time_ns())[-8:];letter='Sealed letter '+tag;sceneTitle='Moonlit road '+tag;tokenName='Moon sentinel '+tag
Client=ns['Client'];gm=Client();gm.login('gm');player=Client();player.login('player');anon=Client();checks=0

def check(label,condition):
 global checks
 assert condition,label;checks+=1;print('PASS',label)
def get(c,path='state'):
 code,body,_=c.req('/session/kit/'+path)
 try:data=json.loads(body)
 except:raise AssertionError((code,body[:600]))
 return code,data
def act(c,action,**body):
 body.setdefault('revision',get(c)[1].get('revision',0));code,raw,_=c.req('/session/kit/'+action,json.dumps(body).encode(),{'Content-Type':'application/json','X-Tabletop-CSRF':c.csrf})
 try:data=json.loads(raw)
 except:raise AssertionError((code,raw[:700]))
 return code,data
def sql(q):return subprocess.run(['podman','exec','cauldron-lab-db','mariadb','-ucauldron','-pcauldron','cauldron','-Nse',q],capture_output=True,check=True).stdout.decode().strip()
code,state=get(gm);check('GM play-kit state loads',code==200 and state['gm'])
check('player state has no GM draft scenes',get(player)[0]==200 and get(player)[1]['scenes']==[])
check('anonymous state denied',get(anon)[0]==401)
check('player scene creation denied',act(player,'scene-save',title='forbidden')[0]==403)
check('player token creation denied',act(player,'token-create',name='forbidden')[0]==403)
check('CSRF required',gm.req('/session/kit/break',b'{}',{'Content-Type':'application/json'})[0]==403)
# Private personal preferences have separate revision and no GM bypass.
code,state=act(player,'preferences',revision=get(player)[1]['preferences']['revision'],favorites=[{'name':'Sneak','expression':'1d20+3'}],follow=True)
check('player favorite and follow preferences save',code==200 and state['preferences']['value']['favorites'][0]['name']=='Sneak')
check('GM does not see player favorites',get(gm)[1]['preferences']['value'].get('favorites',[])==[])
check('preferences conflict rejected',act(player,'preferences',revision=0,favorites=[],follow=False)[0]==409)
# Private draft media / public handouts.
fixture=root/'scene-fixtures/moonlit-road.png';boundary='kitfixture';body=(f'--{boundary}\r\nContent-Disposition: form-data; name="image"; filename="Road.png"\r\nContent-Type: image/png\r\n\r\n'.encode()+fixture.read_bytes()+f'\r\n--{boundary}--\r\n'.encode())
code,raw,_=gm.req('/session/kit/upload',body,{'Content-Type':'multipart/form-data; boundary='+boundary,'X-Tabletop-CSRF':gm.csrf});check('GM private art upload works',code==200);image=json.loads(raw)['image']
check('unrevealed art denied to player',get(player,'media?id='+image)[0]==404)
check('anonymous art denied',get(anon,'media?id='+image)[0]==401)
code,state=act(gm,'handout-save',title=letter,source='**The road calls.**',image=image);check('GM handout draft saves',code==200)
handout=next(h for h in state['handouts'] if h['title']==letter);check('draft handout hidden from players',all(h['id']!=handout['id'] for h in get(player)[1]['handouts']))
check('reveal handout',act(gm,'handout-reveal',id=handout['id'],value=True)[0]==200)
check('player can reopen revealed handout',any(h['id']==handout['id'] for h in get(player)[1]['handouts']))
check('revealed artwork authorized',player.req('/session/kit/media?id='+image)[1]==fixture.read_bytes())
check('player cannot reveal hidden material',act(player,'handout-reveal',id=handout['id'])[0]==403)
# Saved scenes are server captures, not caller-supplied map data.
code,state=act(gm,'scene-save',title=sceneTitle,intro='## A quiet crossing',image=image,mode='scene',ambience='night');check('scene captures current map and introduction',code==200)
scene=next(s for s in state['scenes'] if s['title']==sceneTitle);code,preview=get(gm,'scene?id='+scene['id']);check('GM can preview saved scene',code==200 and preview['document']['intro']=='## A quiet crossing')
check('player cannot inspect saved scene snapshot',get(player,'scene?id='+scene['id'])[0]==403)
check('scene reveal succeeds',act(gm,'scene-reveal',id=scene['id'])[0]==200)
ps=get(player)[1];check('revealed introduction and ambience reach player',ps['state']['scene']['title']==sceneTitle and ps['state']['ambience']=='night')
check('private snapshot never enters player scene state','snapshot' not in ps['state']['scene'])
check('scene archive and restore',act(gm,'scene-archive',id=scene['id'],archived=True)[0]==200 and act(gm,'scene-archive',id=scene['id'],archived=False)[0]==200)
# Manual turns / counters, break signal, opt-in attention.
code,state=act(gm,'turns',rows=[{'label':'Fixture Hero','counter':3},{'label':'Guard','counter':0}],active=1,round=2)
check('manual turns and counters shared',code==200 and get(player)[1]['state']['turns']['rows'][0]['counter']==3)
check('player cannot overwrite turn order',act(player,'turns',rows=[])[0]==403)
check('player can discreetly request break',act(player,'break')[0]==200)
check('break requester visible to GM only','by' in get(gm)[1]['state']['pause'] and 'by' not in get(player)[1]['state']['pause'])
check('player cannot dismiss break',act(player,'resume')[0]==403);check('GM resumes',act(gm,'resume')[0]==200)
check('reaction accepted',act(player,'reaction',emoji='✨')[0]==200)
check('player signal identity server-derived',any(s.get('by')=='Fixture player' and s['kind']=='reaction' for s in get(gm)[1]['signals']))
check('invalid emoji rejected',act(gm,'reaction',emoji='<script>')[0]==400)
# Quick pieces use native object assets and do not grant private sheet access.
code,state=act(gm,'token-create',name=tokenName,image=image,width=1,height=1,x=4,y=3,controller='00000000-0000-4000-8000-000000000001');check('quick token created with explicit player control',code==200)
piece=next(p for p in state['pieces'] if p['name']==tokenName);pid=int(piece['key'][5:])
check('assigned player can control token',next(p for p in get(player)[1]['pieces'] if p['key']==piece['key'])['control'])
code,state=act(gm,'token-edit',id=pid,name=tokenName,width=2,height=2,hidden=True,locked=True)
check('token resize/hide/lock works',code==200 and next(p for p in state['pieces'] if p['key']==piece['key'])['width']==2)
check('hidden token omitted from player quick bar',all(p['key']!=piece['key'] for p in get(player)[1]['pieces']))
check('hidden token lock metadata omitted from player',piece['key'] not in get(player)[1]['state']['pieceLocks'])
code,state=act(gm,'token-edit',id=pid,name=tokenName,width=2,height=2,hidden=False,locked=False)
check('token can be revealed and unlocked',code==200)
check('token duplicate works',act(gm,'token-duplicate',id=pid,name='Sentinel twin')[0]==200)
check('background lock enforced in quick map endpoint',act(gm,'lock-map',locked=True)[0]==200 and gm.post('map',fixture)[0]==409)
check('background can be unlocked',act(gm,'lock-map',locked=False)[0]==200)
# A player can undo an actual owned native move, not another person's history.
key=piece['key'];old=sql(f'SELECT pos_x FROM map_token WHERE id={pid}');code,raw,_=player.req('/object/move',__import__('urllib.parse').parse.urlencode({'instance_id':key,'pos_x':8,'pos_y':6}).encode(),{'Content-Type':'application/x-www-form-urlencoded','X-Tabletop-CSRF':player.csrf,'X-Requested-With':'XMLHttpRequest'})
check('assigned player native move accepted',code==200)
moveCode,state=get(player);assert moveCode==200,(moveCode,state);moves=[h for h in state['history'] if h['kind']=='move'];check('successful native move recorded for recovery',bool(moves))
check('player undo restores own piece',act(player,'undo',id=int(moves[0]['id']))[0]==200 and sql(f'SELECT pos_x FROM map_token WHERE id={pid}')==old)
print(f'{checks} play-kit checks passed')
