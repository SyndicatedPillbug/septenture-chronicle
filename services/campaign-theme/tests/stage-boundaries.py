from pathlib import Path
exec((Path(__file__).parent/'stage-http.py').read_text().split("check('GM stage loads'")[0])
import urllib.parse
act(gm,'sight-mode',mode=0);act(gm,'reveal-all');act(gm,'cover-all')
# Hidden geometry must not return through event polling or attention signals.
cursor=json.loads(player.req('/session/events?init=1')[1])['cursor']
for payload in [{'action':'marker','pos_x':30,'pos_y':30},{'action':'door_state','door_id':'door1','state':'closed'},{'action':'window_state','window_id':'wall1','transparent':'yes'},{'action':'fill_texture','src':'/files/brushes/grass.png'}]:
 gm.req('/session/events',json.dumps(payload).encode(),{'Content-Type':'application/json','X-Tabletop-CSRF':gm.csrf})
events=json.loads(player.req('/session/events?after='+str(cursor))[1])['events']
check('concealed markers and geometry absent from player events',not any(e['action'] in ['marker','door_state','window_state','fill_texture'] for e in events))
act(gm,'ping',group='kit',x=30,y=30)
check('hidden GM ping absent from player signals',not any(s['kind']=='ping' and s.get('x')==30 for s in get(player,group='kit')[1]['signals']))
page=player.req('/adventure/1')[1];check('empty visible roster renders without native errors',b'Undefined variable' not in page and b'internal_errors' not in page)
for action in ['vision','light']:
 code,_,_=player.req('/object/'+action,urllib.parse.urlencode({'instance_id':'character1','value':255}).encode(),{'Content-Type':'application/x-www-form-urlencoded','X-Tabletop-CSRF':player.csrf});check('player cannot expand '+action+' during exploration',code==403)
check('player cannot bypass concealment with legacy source URL',player.req('/session/stage/map-image')[1]!=gm.req('/session/stage/map-image')[1])
# Invalid grid changes cannot strand player rendering.
mid=get(gm)[1]['map']['id'];before=sql(f'SELECT grid_size FROM maps WHERE id={mid}');size=sql(f'SELECT width*height FROM maps WHERE id={mid}')
if int(size)>320000:
 check('too-fine concealed grid rejected',act(gm,'grid',grid=4)[0]==400 and sql(f'SELECT grid_size FROM maps WHERE id={mid}')==before)
# Quick image replacement must reject an unmaskable source before changing the map.
import zlib,struct,binascii,tempfile
chunk=lambda kind,data:struct.pack('!I',len(data))+kind+data+struct.pack('!I',binascii.crc32(kind+data)&0xffffffff)
wide=b'\x89PNG\r\n\x1a\n'+chunk(b'IHDR',struct.pack('!IIBBBBB',5000,10,8,2,0,0,0))+chunk(b'IDAT',zlib.compress((b'\0'+b'\x88\xaa\x88'*5000)*10))+chunk(b'IEND',b'')
with tempfile.TemporaryDirectory() as folder:
 path=Path(folder)/'too-wide.png';path.write_bytes(wide);oldurl=sql(f'SELECT url FROM maps WHERE id={mid}');check('unmaskable replacement rejected without changing active map',gm.post('map',path)[0]==400 and sql(f'SELECT url FROM maps WHERE id={mid}')==oldurl)
# Native day/night still remove exact map pixels on server.
act(gm,'reveal-all')
for mode in [2,4]:
 check('sight mode '+str(mode)+' saves',act(gm,'sight-mode',mode=mode)[0]==200)
 check('sight mode '+str(mode)+' serves masked raster',player.req('/session/stage/map-image')[0]==200 and player.req('/session/stage/map-image')[1]!=gm.req('/session/stage/map-image')[1])
check('door belongs to current map',act(gm,'door',id=64999,state='open')[0]==404)
check('light belongs to current map',act(gm,'light',id=64999,state='on')[0]==404)
act(gm,'sight-mode',mode=0)
# Search input is parameterized, malformed media and range inputs fail safely.
check('shelf search accepts literal SQL syntax',get(gm,"shelf?q=%27%20OR%201%3D1--")[0]==200)
check('media traversal rejected',get(gm,'preview?id=../../config.php')[0]==400)
check('malformed JSON rejected',gm.req('/session/stage/template-save',b'{bad',{'Content-Type':'application/json','X-Tabletop-CSRF':gm.csrf})[0]==400)
print(f'{n} additional stage boundary checks passed')
