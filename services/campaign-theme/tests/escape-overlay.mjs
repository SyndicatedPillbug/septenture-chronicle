import assert from 'node:assert/strict';import vm from 'node:vm';import {readFileSync} from 'node:fs';
let sent=[];const node={off(){return this},remove(){return this}};const ctx={$:()=>node,websocket_send:event=>sent.push(event)};
vm.createContext(ctx);vm.runInContext(readFileSync(new URL('../table-overlay/public/js/rule_systems/spell_effect_area.js',import.meta.url),'utf8'),ctx);
ctx.spell_effect_area_stop();assert.equal(sent.length,0,'Escape without a spell overlay must not issue any event');
ctx.sea_active=true;ctx.sea_ctx={clearRect(){}};ctx.sea_canvas={width:100,height:100};ctx.spell_effect_area_stop();assert.equal(sent.length,1);assert.equal(sent[0].action,'sea_clear');assert.equal(ctx.sea_active,false);
ctx.spell_effect_area_stop();assert.equal(sent.length,1,'a completed clear must not repeat');console.log('PASS inactive Escape is silent; actual overlay cleanup remains intact and is emitted once');
