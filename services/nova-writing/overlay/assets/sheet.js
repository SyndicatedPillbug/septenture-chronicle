;(() => {
 'use strict'
 function initialBuild(){return {ability_method:'standard',abilities:{str:15,dex:14,con:13,int:12,wis:10,cha:8},background:'soldier',species:'human',background_boosts:{str:2,con:1},levels:['fighter'],class_skills:[],multiclass_skills:{},species_skills:[],expertise:[],advancements:[],armor:null,shield:false,weapons:[],two_handed_weapons:[],fighting_styles:{},weapon_masteries:{},custom_features:[],spell_choices:{},origin_choices:{},class_choices:{},subclasses:{}}}
 function hydrateBuild(saved={}){
  const build={...initialBuild(),...structuredClone(saved)}
  // PHP encodes an empty associative array as []. Named properties on a JS
  // array disappear from JSON, so restore only schema-defined map containers.
  const map=value=>Array.isArray(value)&&value.length===0?{}:value??{}
  for(const key of ['abilities','background_boosts','multiclass_skills','fighting_styles','weapon_masteries','spell_choices','origin_choices','class_choices','subclasses'])build[key]=map(build[key])
  for(const [cls,entry]of Object.entries(build.subclasses)){build.subclasses[cls]=map(entry);build.subclasses[cls].choices=map(build.subclasses[cls].choices);if(entry.source)for(const [id,value]of Object.entries(build.subclasses[cls].choices))build.subclasses[cls].choices[id]=map(value)}
  for(const [cls,choices]of Object.entries(build.class_choices))build.class_choices[cls]=map(choices)
  if(build.class_choices.warlock?.invocations)build.class_choices.warlock.invocations=build.class_choices.warlock.invocations.map(map)
  for(const [cls,choices]of Object.entries(build.spell_choices)){const entry=build.spell_choices[cls]=map(choices);entry.arcanum=map(entry.arcanum)}
  build.origin_choices.class_languages=map(build.origin_choices.class_languages)
  build.origin_choices.magic_initiate=map(build.origin_choices.magic_initiate)
  if('human_feat_choices' in build.origin_choices)build.origin_choices.human_feat_choices=map(build.origin_choices.human_feat_choices)
  if('skilled' in build.origin_choices)build.origin_choices.skilled=map(build.origin_choices.skilled)
  for(const [list,choices]of Object.entries(build.origin_choices.magic_initiate))build.origin_choices.magic_initiate[list]=map(choices)
  for(const feature of build.custom_features)feature.choices=map(feature.choices)
  for(const feat of build.advancements){feat.abilities=map(feat.abilities);if('choices' in feat)feat.choices=map(feat.choices)}
  return build
 }
 function cleanSubclassChoices(build,snapshots=[]){
  const counts={};for(const cls of build.levels)counts[cls]=(counts[cls]||0)+1
  for(const [cls,entry]of Object.entries(build.subclasses)){
   const n=counts[cls]||0;if(n<3){delete build.subclasses[cls];continue}
   if(entry.source){const selected=chosenSubclass(entry,subclassEntries({subclass_options:{classes:{}}},snapshots,cls));if(selected){const unlocked=new Set(selected.definition.features.filter(g=>g.at<=n).map(g=>g.feature));for(const id of Object.keys(entry.choices))if(!unlocked.has(id))delete entry.choices[id]}continue}
   for(const [field,at]of Object.entries({discoveries:6,additional_style:7,defense:7,element:6,resistance:10}))if(n<at)delete entry.choices[field]
   if(entry.choices.savant_spells)entry.choices.savant_spells=entry.choices.savant_spells.filter(s=>s.at<=n)
  }
 }
 function backgroundEntries(catalogue,snapshots=[]){
  const entries=Object.entries(catalogue.backgrounds).map(([id,definition])=>({key:'open:'+id,id,definition:{...definition,tool:catalogue.origins.background_tools[id]},source:null}))
  for(const snapshot of snapshots)for(const definition of snapshot.source.backgrounds||[])entries.push({key:`private:${snapshot.source.id}@${snapshot.source.version}:${definition.id}`,id:definition.id,definition,source:{source:snapshot.source.id,version:snapshot.source.version,digest:snapshot.digest},sourceName:snapshot.source.name})
  return entries
 }
 function chosenBackground(build,entries){return entries.find(e=>e.id===build.background&&(build.background_source?e.source?.source===build.background_source.source&&e.source?.version===build.background_source.version&&e.source?.digest===build.background_source.digest:e.source===null))}
 function originFeatEntries(catalogue,snapshots=[]){
  const entries=catalogue.origins.origin_feats.map(id=>({key:'open:'+id,id,source:null}))
  for(const snapshot of snapshots)for(const feat of snapshot.source.origin_feats||[])entries.push({key:`private:${snapshot.source.id}@${snapshot.source.version}:${feat.id}`,id:feat.id,name:feat.name,sourceName:snapshot.source.name,source:{source:snapshot.source.id,version:snapshot.source.version,digest:snapshot.digest},feature:snapshot.source.features.find(f=>f.id===feat.feature)})
  return entries
 }
 function chosenHumanFeat(choices,entries){return entries.find(e=>e.id===choices.human_feat&&(choices.human_feat_source?e.source?.source===choices.human_feat_source.source&&e.source?.version===choices.human_feat_source.version&&e.source?.digest===choices.human_feat_source.digest:e.source===null))}
 function advancementFeatEntries(catalogue,snapshots=[]){
  const entries=Object.entries(catalogue.feat_options).map(([id,definition])=>({key:'open:'+id,id,definition,source:null}))
  for(const snapshot of snapshots)for(const feat of snapshot.source.feats||[]){const feature=snapshot.source.features.find(f=>f.id===feat.feature);entries.push({key:`private:${snapshot.source.id}@${snapshot.source.version}:${feat.id}`,id:feat.id,source:{source:snapshot.source.id,version:snapshot.source.version,digest:snapshot.digest},sourceName:snapshot.source.name,feature,definition:{...feat,summary:feature.description}})}return entries
 }
 function chosenAdvancementFeat(choice,entries){return choice&&entries.find(e=>e.id===choice.feat&&(choice.source?e.source?.source===choice.source.source&&e.source?.version===choice.source.version&&e.source?.digest===choice.source.digest:e.source===null))}
 function subclassEntries(catalogue,snapshots=[],cls){
  const rule=catalogue.subclass_options.classes[cls],entries=rule?[{key:'open:'+rule.id,id:rule.id,definition:rule,source:null}]:[]
  for(const snapshot of snapshots)for(const sub of snapshot.source.subclasses||[])if(sub.class===cls)entries.push({key:`private:${snapshot.source.id}@${snapshot.source.version}:${sub.id}`,id:sub.id,definition:sub,features:snapshot.source.features,sourceName:snapshot.source.name,source:{source:snapshot.source.id,version:snapshot.source.version,digest:snapshot.digest}})
  return entries
 }
 function chosenSubclass(entry,entries){return entry&&entries.find(e=>e.id===entry.id&&(entry.source?e.source?.source===entry.source.source&&e.source?.version===entry.source.version&&e.source?.digest===entry.source.digest:e.source===null))}
 function inventoryDraftAfterCommit(draft,kind){
  const groups={initialize:['startReason'],record_acquisition:['itemFilter','addItem','addQuantity','addLocation','addReason','addNote'],move:['changeItem','moveLocation','spendQuantity','changeReason'],record_expenditure:['changeItem','moveLocation','spendQuantity','changeReason'],record_equipment:['armorChoice','handsChoice','handItem0','handItem1','handGrip0','handGrip1','equipmentReason']},next={...draft}
  for(const key of groups[kind]||[])delete next[key]
  return next
 }
 function inventoryWriter(api,newId=()=>crypto.randomUUID().replaceAll('-','')){
  let pending=null,writing=false
  async function attempt(){
   if(writing)throw new Error('An inventory change is already being saved.')
   if(!pending)throw new Error('There is no unconfirmed inventory change to retry.')
   writing=true
   try{const result=await api(`sheets/${pending.character}/inventory`,structuredClone(pending.payload));pending=null;return result}
   catch(e){if(e.status>=400&&e.status<500)pending=null;throw e}
   finally{writing=false}
  }
  return {get kind(){return pending?.payload.command.kind},get pending(){return pending!==null},get writing(){return writing},commit(sheet,command){if(pending)throw new Error('Retry the unconfirmed inventory change before making another change.');pending={character:sheet.character_id,payload:{revision:sheet.revision,operation_id:newId(),command:structuredClone(command)}};return attempt()},retry:attempt}
 }
 // The pure hydration and pinned-source paths are exercised by the Node suite.
 if(typeof module==='object'&&module.exports){module.exports={inventoryDraftAfterCommit,inventoryWriter,hydrateBuild,cleanSubclassChoices,backgroundEntries,chosenBackground,originFeatEntries,chosenHumanFeat,advancementFeatEntries,chosenAdvancementFeat,subclassEntries,chosenSubclass};return}
 const panel=document.querySelector('[data-private-sheet]');if(!panel)return
 const advanced=panel.dataset.advancedTools==='true'
 const body=panel.querySelector('[data-sheet-body]'),status=panel.querySelector('[data-sheet-status]'),id=Number(panel.dataset.characterId)
 let csrf='',sheet=null,players=[],administrator=false,catalogue=null,draft=null,notes='',notesDirty=false,buildDirty=false,busy=false,inventoryDraft={},inventoryDirty=false,manualDraft={version:1,sections:[]},manualDirty=false,manualView=null
 const inventoryWrites=inventoryWriter(request)
 const title=value=>value.replaceAll('_',' ').replace(/\b\w/g,c=>c.toUpperCase())
 const node=(tag,text,attrs={})=>{const n=document.createElement(tag);if(text!==undefined)n.textContent=text;for(const [key,value]of Object.entries(attrs))n.setAttribute(key,value);return n}
 const tell=message=>{status.textContent=message}
 const label=(text,input)=>{input.setAttribute('aria-label',text);const wrap=node('label');wrap.append(node('span',text),input);return wrap}
 const input=(type,value,attrs={})=>{const n=node('input',undefined,{type,...attrs});n.value=value;return n}
 const select=(items,value)=>{const n=node('select');for(const [key,text]of items)n.append(node('option',text,{value:key}));n.value=value??'';return n}
 const fieldset=text=>{const n=node('fieldset');n.append(node('legend',text));return n}
 const button=(text,action)=>{const n=node('button',text,{type:'button'});n.addEventListener('click',()=>run(action));return n}
 const signed=n=>(n>=0?'+':'')+n
 const playerLabel=subject=>players.find(p=>p.subject===subject)?.label||'Player no longer in the directory'
 async function request(path,data){
  const r=await fetch('/index.php/play/'+path,{credentials:'same-origin',cache:'no-store',method:data?'POST':'GET',headers:data?{'Content-Type':'application/json','X-Play-CSRF':csrf}:{},body:data?JSON.stringify(data):undefined})
  if(!r.headers.get('Content-Type')?.includes('application/json'))throw new Error('Your player session needs refreshing. Sign in again in another tab; your inputs remain here.')
  const result=await r.json();if(!r.ok){const error=new Error(result.error||'The sheet could not finish that action.');error.status=r.status;error.validation=result.validation;throw error}return result
 }
 async function run(action){
  if(busy)return;busy=true
  const controls=[...panel.querySelectorAll('input,select,textarea,button')].map(n=>[n,n.disabled]);for(const [n]of controls)n.disabled=true
  try{await action()}catch(e){tell(e.message);if(e.validation)renderValidation(e.validation,body.querySelector('[data-build-result]'))}
  finally{busy=false;for(const [n,disabled]of controls)n.disabled=disabled;if(inventoryWrites.pending){for(const n of panel.querySelectorAll('[data-inventory-view] input,[data-inventory-view] select,[data-inventory-view] button'))n.disabled=true;for(const n of panel.querySelectorAll('[data-inventory-retry]'))n.disabled=false}}
 }
 function setSheet(next,reset=false){sheet=next;if(reset){draft=hydrateBuild(sheet.document.build?.input||{});notes=sheet.document.notes||'';notesDirty=false;buildDirty=false;inventoryDraft={};inventoryDirty=false;manualDraft=structuredClone(sheet.document.manual_sheet||{version:1,sections:[]});manualDirty=false}}
 function markBuild(){buildDirty=true}
 const availableBackgrounds=()=>backgroundEntries(catalogue,sheet.document.rules_sources||[])
 const backgroundDefinition=()=>chosenBackground(draft,availableBackgrounds())?.definition
 function choiceGroup(text,items,selected,change){
  const group=fieldset(text);group.className='sheet-choices'
  for(const [key,name]of items){const check=input('checkbox','');check.checked=selected.includes(key);check.addEventListener('change',()=>{change([...group.querySelectorAll('input:checked')].map(n=>n.value));markBuild()});check.value=key;group.append(label(name,check))}
  if(items.length>25){const details=node('details');details.append(node('summary',text),group);return details}return group
 }
 function summary(calculated,target){
  target.replaceChildren();if(!calculated){target.append(node('p','No calculated build saved yet. Start with the builder below.'));return}
  const stats=node('dl',undefined,{class:'sheet-stats'})
  for(const [name,value]of [['Level',calculated.level],['Armor class',calculated.armor_class],['Maximum HP',calculated.hp_max],['Proficiency',signed(calculated.proficiency)],['Initiative',signed(calculated.initiative)],['Speed',calculated.speed+' ft.'],['Passive Perception',calculated.passive_perception]])stats.append(node('dt',name),node('dd',String(value)))
  target.append(stats)
  if(calculated.background){const b=calculated.background,details=node('details');details.append(node('summary','Background: '+b.name));if(b.description)details.append(node('p',b.description));details.append(node('p','Ability options: '+b.abilities.map(a=>a.toUpperCase()).join(', ')),node('p','Skill proficiencies: '+b.skills.map(title).join(', ')),node('p','Origin feat: '+title(b.feat)),node('p','Tool: '+(catalogue.origins.tools[b.tool]?.name||title(b.tool))));if(b.source)details.append(node('small',`${b.source.source_name} · ${b.source.version}`));target.append(details)}
  const abilities=node('dl',undefined,{class:'sheet-stats'});for(const [ability,score]of Object.entries(calculated.abilities))abilities.append(node('dt',ability.toUpperCase()),node('dd',`${score} (${signed(calculated.modifiers[ability])})`));target.append(abilities)
  const skills=node('details');skills.append(node('summary','Skills and saving throws'));const list=node('ul');for(const [skill,value]of Object.entries(calculated.skills))list.append(node('li',`${title(skill)} ${signed(value.bonus)}${value.proficiency===2?' · Expertise':value.proficiency===1?' · Proficient':''}`));for(const [ability,value]of Object.entries(calculated.saves))list.append(node('li',`${ability.toUpperCase()} save ${signed(value)}`));skills.append(list);target.append(skills)
  const attacks=node('details');attacks.append(node('summary','Attacks and spellcasting'));const attackList=node('ul');for(const attack of Object.values(calculated.attacks))attackList.append(node('li',`${attack.name}: ${attack.attack}; damage ${attack.damage}${attack.available===false?' · unavailable with current equipment':''}`));for(const [key,casting]of Object.entries(calculated.spellcasting))attackList.append(node('li',`${title(key)}: spell attack ${signed(casting.attack)}, save DC ${casting.save_dc}, up to ${casting.prepared_limit} prepared spells`));attacks.append(attackList);target.append(attacks)
  if(calculated.class_choices){const selected=calculated.class_choices,details=node('details');details.append(node('summary','Class training choices'));for(const [cls,order]of Object.entries(selected.orders))details.append(node('p',`${title(cls)} order: ${title(order)}`));if(selected.scholar)details.append(node('p','Wizard Scholar Expertise: '+title(selected.scholar)));if(selected.armor.length)details.append(node('p','Additional armor training: '+selected.armor.map(title).join(', ')));if(selected.martial)details.append(node('p','Martial weapon proficiency'));for(const [skill,bonus]of Object.entries(selected.skill_bonuses))details.append(node('p',`${title(skill)} additional Intelligence check bonus: ${signed(bonus)}`));if(selected.tools.length)details.append(node('p','Class tool proficiencies: '+selected.tools.map(id=>catalogue.origins.tools[id].name).join(', ')));target.append(details)}
  if(Object.keys(calculated.class_choices?.metamagic?.options||{}).length){const details=node('details');details.append(node('summary','Sorcerer Metamagic'));for(const option of Object.values(calculated.class_choices.metamagic.options))details.append(node('h4',option.name),node('p',option.summary),node('p',option.cost+' Sorcery Point'+(option.cost===1?'':'s')+' · '+title(option.phase)));details.append(node('p','One option per spell unless an option explicitly permits combining them. Sorcery Incarnate and Arcane Apotheosis apply through the casting resolver. Actual casting, point spending and turn state are still being connected.'));target.append(details)}
  renderOriginSummary(calculated,target)
  renderInvocationSummary(calculated,target)
  renderSubclassSummary(calculated,target)
  renderFeatSummary(calculated,target)
  if(calculated.flags?.length){const flags=node('ul');for(const flag of calculated.flags)flags.append(node('li',flag));target.append(node('h4','Equipment effects'),flags)}
  const slotLimits=Object.entries(calculated.spell_slots||{}).filter(([,count])=>count>0);if(slotLimits.length)target.append(node('p','Spell slot limits: '+slotLimits.map(([level,count])=>`level ${level}: ${count}`).join(' · ')))
  for(const [cls,casting]of Object.entries(calculated.spellcasting||{})){const details=node('details');details.append(node('summary',title(cls)+' spell selections'),node('p',`Spell attack ${signed(casting.attack)} · save DC ${casting.save_dc} · class spell level up to ${casting.max_spell_level??'?'} · preparation changes: ${casting.preparation_change||'recalculate this build'}`))
   for(const [key,name]of [['cantrips','Cantrips'],['prepared','Prepared spells'],['always_prepared','Always-prepared spells and arcana'],['rituals','Available rituals'],['spellbook','Spellbook']])if(casting[key]?.length){const group=node('details');group.append(node('summary',`${name} (${casting[key].length})`));for(const spell of casting[key]){const row=node('div',undefined,{class:'sheet-spell-row'});row.append(node('span',`${spell.name} · ${spell.level===0?'cantrip':'level '+spell.level}${spell.method?' · '+(spell.method==='copied'?'copied':spell.method==='savant'?'Evocation Savant':'class learning'):''}`),spellReference(spell.id));group.append(row)}details.append(group)}target.append(details)
  }
  if(Object.keys(calculated.resources||{}).length){const resources=node('details');resources.append(node('summary','Resource limits and recovery'));const list=node('ul');for(const [key,r]of Object.entries(calculated.resources))list.append(node('li',`${r.name||title(key.replaceAll('.',' '))}: maximum ${r.max}; ${r.die?'die '+r.die+'; ':''}short rest ${r.short_rest==='all'?'restore all':'restore '+r.short_rest}; long rest ${r.long_rest==='all'?'restore all':'restore '+r.long_rest}`));resources.append(list);target.append(resources)}
  if(Object.keys(calculated.maneuver_plans||{}).length){const details=node('details');details.append(node('summary','Selected maneuvers'));for(const plan of Object.values(calculated.maneuver_plans))details.append(node('h4',plan.name),node('p',`One ${plan.resource_die||'unavailable resource die'}; ${title(plan.recipe)}. Only one maneuver may enhance an attack.`));details.append(node('p','Automatic targets, resource spending and turn limits are still being implemented.'));target.append(details)}
  if(Object.keys(calculated.feature_save_dcs||{}).length){const details=node('details');details.append(node('summary','Feature saving throw DCs'));for(const dc of Object.values(calculated.feature_save_dcs))details.append(node('p',dc.name+': DC '+dc.dc));target.append(details)}
  if(calculated.custom_features?.length){const features=node('details');features.append(node('summary','Selected campaign features'));for(const feature of calculated.custom_features)features.append(node('h4',feature.name),node('p',feature.description),node('small',`${feature.source} · ${feature.version}`));target.append(features)}
  if(calculated.derivation?.length){const details=node('details');details.append(node('summary','Where campaign modifiers come from'));const list=node('ul');for(const entry of calculated.derivation)list.append(node('li',`${entry.source} ${entry.version} / ${entry.feature}: ${entry.training?title(entry.training)+' proficiency: '+title(entry.proficiency):entry.save_dc!==undefined?'Save DC '+entry.save_dc:entry.maneuver?'Maneuver: '+entry.maneuver:entry.sense?title(entry.sense)+' '+entry.range_feet+' feet':entry.reaction?'Reaction: '+entry.reaction:(entry.target||entry.resource)+' '+(entry.value===undefined?'maximum '+entry.max:signed(entry.value))}${entry.stack==='highest'?' · highest bonus in '+entry.stack_key:''}`));details.append(list);target.append(details)}
  renderValidation(calculated.validation,target)
 }
 function renderValidation(validation,target){
  if(!target)return;target.querySelector('[data-validation]')?.remove();const box=node('div',undefined,{'data-validation':'',class:'sheet-validation'})
  box.append(node('h4',validation.ready?'Build choices complete':'Build needs more work'))
  const choices=node('ul');for(const issue of validation.issues)choices.append(node('li',issue));for(const warning of validation.warnings)choices.append(node('li',warning));if(choices.children.length)box.append(choices)
  const remaining=[...validation.feature_automation_remaining,...validation.origin_automation_remaining]
  if(remaining.length){const details=node('details');details.append(node('summary',`${remaining.length} rules features still need automation`));const list=node('ul');for(const gap of remaining)list.append(node('li',gap));details.append(list);box.append(details,node('p','This remains a draft. Automatic play actions unlock after the required choices and rules support are complete.'))}target.append(box)
 }
 function renderBasics(parent){
  const basics=fieldset('1. Abilities and origin'),abilities=['str','dex','con','int','wis','cha'],method=select([['standard','Standard array'],['point_buy','27-point buy']],draft.ability_method)
  method.addEventListener('change',()=>{draft.ability_method=method.value;markBuild()});basics.append(label('Ability method',method),node('p','Assign the standard array 15, 14, 13, 12, 10, 8, or spend 27 points using scores from 8 to 15. Background increases are separate.'))
  for(const ability of abilities){const score=input('number',draft.abilities[ability],{min:3,max:18,step:1});score.addEventListener('input',()=>{draft.abilities[ability]=Number(score.value);markBuild()});basics.append(label(ability.toUpperCase()+' base score',score))}
  const entries=availableBackgrounds(),selected=chosenBackground(draft,entries),origin=node('div'),background=select([['','Choose a background…'],...entries.map(e=>[e.key,`${e.definition.name||title(e.id)}${e.source?' · '+e.sourceName+' '+e.source.version:''}`])],selected?.key||''),species=select(Object.keys(catalogue.species).map(k=>[k,title(k)]),draft.species)
  if(!selected)basics.append(node('p','The saved background source is unavailable or changed. Choose its pinned version or explicitly select a replacement. Existing choices are kept until then.'))
  const renderBoosts=()=>{origin.replaceChildren(node('p','Background increases: assign +2 and +1 to different listed abilities, or +1 to all three.'))
   for(const ability of backgroundDefinition()?.abilities||[]){const boost=select([['0','+0'],['1','+1'],['2','+2']],String(draft.background_boosts[ability]||0));boost.addEventListener('change',()=>{if(Number(boost.value))draft.background_boosts[ability]=Number(boost.value);else delete draft.background_boosts[ability];markBuild()});origin.append(label(ability.toUpperCase()+' background increase',boost))}}
  background.addEventListener('change',()=>{const entry=entries.find(e=>e.key===background.value);if(!entry)return;draft.background=entry.id;if(entry.source)draft.background_source=structuredClone(entry.source);else delete draft.background_source;draft.background_boosts={};renderBoosts();cleanOriginChoices();updateOriginForms();renderTraining();markBuild()});species.addEventListener('change',()=>{draft.species=species.value;draft.species_skills=[];cleanOriginChoices();updateOriginForms();renderTraining();markBuild()});basics.append(label('Background',background),label('Species',species),origin);renderBoosts();originArea=node('div');basics.append(originArea);updateOriginForms();parent.append(basics)
 }
 let trainingArea=null,progressionArea=null,spellArea=null,originArea=null
 const skillItems=keys=>keys.map(k=>[k,title(k)])
 function renderTraining(){
  if(!trainingArea)return;trainingArea.replaceChildren();const first=draft.levels[0],[count,options]=catalogue.class_skill_choices[first],allowed=options.length?options:Object.keys(catalogue.skills)
  draft.class_skills=(draft.class_skills||[]).filter(k=>allowed.includes(k));trainingArea.append(choiceGroup(`Starting ${catalogue.classes[first].name} skills · choose ${count}`,skillItems(allowed),draft.class_skills,value=>draft.class_skills=value))
  for(const key of [...new Set(draft.levels)].filter(k=>k!==first&&['bard','ranger','rogue'].includes(k))){const options=catalogue.class_skill_choices[key][1],allowed=options.length?options:Object.keys(catalogue.skills);trainingArea.append(choiceGroup(`Multiclass ${catalogue.classes[key].name} skill · choose 1`,skillItems(allowed),draft.multiclass_skills[key]||[],value=>draft.multiclass_skills[key]=value))}
  if(['elf','human'].includes(draft.species)){const options=draft.species==='elf'?['insight','perception','survival']:Object.keys(catalogue.skills);trainingArea.append(choiceGroup('Species skill · choose 1',skillItems(options),draft.species_skills||[],value=>draft.species_skills=value))}
  trainingArea.append(choiceGroup('Expertise · choose only proficient skills when granted',skillItems(Object.keys(catalogue.skills)),draft.expertise||[],value=>draft.expertise=value))
  const counts={};for(const cls of draft.levels)counts[cls]=(counts[cls]||0)+1
  if(counts.warlock)renderInvocations(counts.warlock)
  renderSubclasses(counts)
  for(const [cls,orders]of Object.entries(catalogue.class_options.orders))if(counts[cls]){draft.class_choices[cls]??={};const order=select([['','Choose order'],...orders.map(id=>[id,title(id)])],draft.class_choices[cls].order);order.addEventListener('change',()=>{draft.class_choices[cls].order=order.value;updateSpellForms();markBuild()});trainingArea.append(label(title(cls)+' order',order))}
  if((counts.wizard||0)>=2){draft.class_choices.wizard??={};const scholar=select([['','Choose a proficient skill'],...catalogue.class_options.scholar.map(k=>[k,title(k)])],draft.class_choices.wizard.scholar);scholar.addEventListener('change',()=>{if(scholar.value)draft.class_choices.wizard.scholar=scholar.value;else delete draft.class_choices.wizard.scholar;markBuild()});trainingArea.append(label('Wizard Scholar Expertise',scholar),node('p','Scholar requires proficiency in the selected skill. Choose its Expertise here separately from Bard, Ranger or Rogue Expertise.'))}
  if((counts.sorcerer||0)>=2){draft.class_choices.sorcerer??={};const limit=counts.sorcerer>=17?6:counts.sorcerer>=10?4:2;trainingArea.append(choiceGroup('Sorcerer Metamagic · choose '+limit,Object.entries(catalogue.class_options.metamagic).map(([id,o])=>[id,o.name+' · '+o.cost+' Sorcery Point'+(o.cost===1?'':'s')]),draft.class_choices.sorcerer.metamagic||[],value=>draft.class_choices.sorcerer.metamagic=value));const help=node('details');help.append(node('summary','Read Metamagic options'));for(const option of Object.values(catalogue.class_options.metamagic))help.append(node('h4',option.name),node('p',option.summary));help.append(node('p','Replace one option when gaining a Sorcerer level. Saving a respec draft does not enforce that history yet.'));trainingArea.append(help)}
  for(const cls of ['bard','monk'])if(counts[cls]){const count=cls==='bard'?(first==='bard'?3:1):(first==='monk'?1:0);if(count){draft.class_choices[cls]??={};const tools=Object.entries(catalogue.origins.tools).filter(([,t])=>t.category==='instrument'||(cls==='monk'&&t.category==='artisan'));trainingArea.append(choiceGroup(`${title(cls)} class tools · choose ${count}`,tools.map(([id,t])=>[id,t.name]),draft.class_choices[cls].tools||[],value=>draft.class_choices[cls].tools=value))}}
  for(const [cls,count]of Object.entries(counts)){
   if(['fighter','paladin','ranger'].includes(cls)&&count>=(cls==='fighter'?1:2)){
    const style=select([['','Choose a fighting style'],...['archery','defense','great_weapon_fighting','two_weapon_fighting',...(cls==='paladin'?['blessed_warrior']:cls==='ranger'?['druidic_warrior']:[])].map(k=>[k,title(k)])],draft.fighting_styles[cls]);style.addEventListener('change',()=>{if(style.value)draft.fighting_styles[cls]=style.value;else delete draft.fighting_styles[cls];updateSpellForms();markBuild()});trainingArea.append(label(catalogue.classes[cls].name+' fighting style',style))
   }
   if(['barbarian','fighter','paladin','ranger','rogue'].includes(cls)){const max=catalogue.classes[cls].progression[count-1].values.weapon_mastery||2;trainingArea.append(choiceGroup(`${catalogue.classes[cls].name} weapon masteries · up to ${max}`,Object.entries(catalogue.equipment.weapons).map(([k,w])=>[k,w.name]),draft.weapon_masteries[cls]||[],value=>draft.weapon_masteries[cls]=value))}
  }
 }
 function renderSubclasses(counts){
  for(const [cls,n]of Object.entries(counts)){
   if(n<3)continue;const rule=catalogue.subclass_options.classes[cls],entry=draft.subclasses[cls]??={id:'',choices:{}},area=fieldset(catalogue.classes[cls].name+' subclass')
   const entries=subclassEntries(catalogue,sheet.document.rules_sources||[],cls),selected=chosenSubclass(entry,entries),pick=select([['','Choose subclass'],...entries.map(e=>[e.key,e.definition.name+(e.source?' · '+e.sourceName+' '+e.source.version:'')]),...(!selected&&entry.id?[['unavailable','Saved source unavailable — select explicitly to replace']]:[])],selected?.key||(entry.id?'unavailable':''))
   pick.addEventListener('change',()=>{if(pick.value==='unavailable')return;const chosen=entries.find(e=>e.key===pick.value);draft.subclasses[cls]={id:chosen?.id||'',choices:{},...(chosen?.source?{source:structuredClone(chosen.source)}:{})};markBuild();renderTraining();updateSpellForms()});area.append(label(catalogue.classes[cls].name+' subclass choice',pick))
   if(selected?.source){
    area.append(node('p','Private source progression covers '+catalogue.classes[cls].name+' through level '+selected.definition.coverage_through_level+'.'))
    for(const grant of selected.definition.features.filter(g=>g.at<=n)){const feature=selected.features.find(f=>f.id===grant.feature),group=fieldset(feature.name+' · '+catalogue.classes[cls].name+' '+grant.at);group.append(node('p',feature.description));entry.choices[feature.id]??={};for(const choice of feature.choices)group.append(choiceGroup(choice.name+' · choose '+choice.count,choice.options.map(o=>[o.id,o.name]),entry.choices[feature.id][choice.id]||[],value=>entry.choices[feature.id][choice.id]=value));area.append(group)}
    area.append(node('small','Selections are saved privately. Effects that need combat automation remain listed in the calculation preview.'))
   }
   if(!entry.source&&entry.id===rule.id){const choices=entry.choices,change=()=>{markBuild();updateSpellForms()},choose=(field,text,options)=>{const control=select([['','Choose an option'],...options.map(v=>[v,title(v)])],choices[field]);control.addEventListener('change',()=>{if(control.value)choices[field]=control.value;else delete choices[field];change()});area.append(label(text,control))}
    const features=Object.entries(rule.features).filter(([at])=>n>=Number(at)).flatMap(([,names])=>names);area.append(node('p','Granted features: '+features.join(', ')))
    if(cls==='bard'){
     area.append(choiceGroup('Lore bonus proficiencies · choose 3 untrained skills',skillItems(Object.keys(catalogue.skills)),choices.skills||[],value=>choices.skills=value))
     if(n>=6){const available=Object.keys(catalogue.spells).filter(id=>{const s=catalogue.spells[id];return s.classes.some(c=>['cleric','druid','wizard'].includes(c))&&s.level<=spellCeiling(cls,n)});area.append(spellPicker('Magical Discoveries · choose 2',available,choices.discoveries||[],value=>{choices.discoveries=value;updateSpellForms()}))}
    }
    if(cls==='druid')choose('land','Circle of the Land environment',Object.keys(catalogue.subclass_options.lands))
    if(cls==='fighter'&&n>=7)choose('additional_style','Champion additional Fighting Style',catalogue.subclass_options.styles)
    if(cls==='ranger'){choose('prey','Hunter’s Prey',['colossus_slayer','horde_breaker']);if(n>=7)choose('defense','Hunter Defensive Tactics',['escape_the_horde','multiattack_defense'])}
    if(cls==='sorcerer'&&n>=6)choose('element','Draconic Elemental Affinity',catalogue.subclass_options.elements)
    if(cls==='warlock'&&n>=10)choose('resistance','Fiendish Resilience damage type',catalogue.subclass_options.resistances)
    if(cls==='wizard'){
     area.append(node('p','Evocation Savant adds two Evocation spells at Wizard 3, then one when a later Wizard level unlocks a new slot level. These grants are additional to normal class learning and copying.'))
     const slots={3:2};for(let at=5;at<=Math.min(17,n);at+=2)slots[at]=1
     for(const [at,count]of Object.entries(slots)){const available=Object.keys(catalogue.spells).filter(id=>{const s=catalogue.spells[id];return s.classes.includes('wizard')&&s.school==='Evocation'&&s.level>0&&s.level<=spellCeiling('wizard',Number(at))}),selected=(choices.savant_spells||[]).filter(e=>e.at===Number(at)).map(e=>e.spell)
      area.append(spellPicker('Evocation Savant · Wizard '+at+' · choose '+count,available,selected,value=>{choices.savant_spells=[...(choices.savant_spells||[]).filter(e=>e.at!==Number(at)),...value.map(spell=>({at:Number(at),spell}))];change()}))
     }
    }
    area.append(node('small','Conditional features are listed in previews. Their combat actions and resource transactions are still being implemented.'))
   }
   trainingArea.append(area)
  }
 }
 function renderSubclassSummary(calculated,target){
  if(!Object.keys(calculated.subclasses||{}).length)return
  const details=node('details');details.append(node('summary','Subclasses and their features'))
  const describe=value=>Array.isArray(value)?value.map(describe).join(', '):value&&typeof value==='object'?Object.entries(value).map(([k,v])=>title(k)+': '+describe(v)).join('; '):value===true?'yes':value===false?'no':value===null?'not chosen':String(value)
  for(const [cls,sub]of Object.entries(calculated.subclasses)){details.append(node('h4',title(cls)+' · '+sub.name),node('p',sub.features.map(f=>f.name+' (class '+f.class_level+')').join(', ')));const list=node('ul');for(const [id,trait]of Object.entries(sub.traits||{}))list.append(node('li',title(id)+': '+describe(trait)));details.append(list)}
  if(calculated.defenses)for(const [kind,types]of Object.entries(calculated.defenses))if(types.length)details.append(node('p',title(kind)+': '+types.map(title).join(', ')))
  target.append(details)
 }
 function subclassAlways(cls,n){
  const entry=draft.subclasses[cls],rule=catalogue.subclass_options.classes[cls];if(n<3||entry?.source||entry?.id!==rule.id)return []
  const table=cls==='druid'?catalogue.subclass_options.lands[entry.choices.land]||{}:catalogue.subclass_options.always[cls]||{}
  return [...new Set([...Object.entries(table).filter(([at])=>n>=Number(at)).flatMap(([,ids])=>ids),...(cls==='bard'&&n>=6?entry.choices.discoveries||[]:[])])]
 }
 function renderFeatSummary(calculated,target){
  if(!calculated.advancement_feats?.length)return
  const details=node('details');details.append(node('summary','Feats earned through advancement'))
  for(const feat of calculated.advancement_feats){details.append(node('h4',feat.name+' · character level '+feat.character_level),node('p',title(feat.class)+' '+feat.class_level+' · '+title(feat.category)));if(Object.keys(feat.abilities).length)details.append(node('p','Ability increases: '+Object.entries(feat.abilities).map(([id,n])=>id.toUpperCase()+' +'+n+' ('+feat.ability_scores_before[id]+' → '+feat.ability_scores_after[id]+')').join(', ')));const d=catalogue?.feat_options[feat.id];if(d)details.append(node('p',d.summary));else if(feat.description)details.append(node('p',feat.description));if(feat.source)details.append(node('small',feat.source.source+' · '+feat.source.version))}
  for(const sense of ['blindsight','truesight','tremorsense'])if(calculated[sense])details.append(node('p',title(sense)+': '+calculated[sense]+' feet'))
  for(const reaction of Object.values(calculated.feature_reactions||{}))details.append(node('p',`${reaction.name}: reduction ${reaction.die}${signed(reaction.bonus)}; range ${reaction.range_feet} feet; ${reaction.cost}. Target authorization, equipment, visibility and reaction spending still need resolution.`))
  target.append(details)
 }
 function renderProgression(){
  progressionArea.replaceChildren(node('p','Choose each level in order. The server checks multiclass prerequisites and recalculates the whole character. Changing earlier levels respecs the saved draft when you save.'))
  const classOptions=Object.entries(catalogue.classes).map(([k,c])=>[k,c.name]),counts={},slots=[]
  draft.levels.forEach((cls,index)=>{
   counts[cls]=(counts[cls]||0)+1;const row=catalogue.classes[cls].progression[counts[cls]-1]
   if(row.features.some(f=>['Ability Score Improvement','Epic Boon'].includes(f)))slots.push(index+1)
   const selectClass=select(classOptions,cls);selectClass.addEventListener('change',()=>{draft.levels[index]=selectClass.value;cleanTraining();renderProgression();renderTraining();cleanOriginChoices();updateOriginForms();updateSpellForms();markBuild()});progressionArea.append(label('Character level '+(index+1),selectClass))
  })
  const add=button('Add next level',()=>{if(draft.levels.length<20){draft.levels.push(draft.levels.at(-1));renderProgression();renderTraining();cleanOriginChoices();updateOriginForms();updateSpellForms();markBuild()}});add.disabled=draft.levels.length>=20
  const remove=button('Remove last level',()=>{draft.levels.pop();cleanTraining();renderProgression();renderTraining();cleanOriginChoices();updateOriginForms();updateSpellForms();markBuild()});remove.disabled=draft.levels.length<=1;progressionArea.append(add,remove)
  draft.advancements=(draft.advancements||[]).filter(a=>slots.includes(a.level))
  for(const level of slots){const box=fieldset(`Level ${level} advancement`),existing=draft.advancements.find(a=>a.level===level),counts={};for(const cls of draft.levels.slice(0,level))counts[cls]=(counts[cls]||0)+1
   const entries=advancementFeatEntries(catalogue,sheet.document.rules_sources||[]),selected=chosenAdvancementFeat(existing,entries)
   const eligible=entries.filter(e=>{const d=e.definition;return d.minimum_level<=level&&(d.category!=='fighting_style'||counts.fighter>=1||counts.paladin>=2||counts.ranger>=2)&&(e.source||e.id!=='boon_of_spell_recall'||Object.keys(counts).some(c=>['bard','cleric','druid','paladin','ranger','sorcerer','wizard'].includes(c)))})
   const options=[...eligible];if(selected&&!options.some(e=>e.key===selected.key))options.push(selected)
   const pick=select([['','Choose a feat'],...options.map(e=>[e.key,e.definition.name+' · '+title(e.definition.category)+(e.source?' · '+e.sourceName+' '+e.source.version:'')+(eligible.includes(e)?'':' · unavailable at acquisition')]),...(!selected&&existing?[['unavailable','Saved source unavailable — choose explicitly to replace']]:[])],selected?.key||(existing?'unavailable':''))
   pick.addEventListener('change',()=>{const entry=entries.find(e=>e.key===pick.value);if(pick.value==='unavailable')return;draft.advancements=draft.advancements.filter(a=>a.level!==level);if(entry)draft.advancements.push({level,feat:entry.id,abilities:{},...(entry.source?{source:structuredClone(entry.source),choices:{}}:{})});cleanOriginChoices();updateOriginForms();renderProgression();markBuild()});box.append(label('Character level '+level+' feat',pick))
   const definition=selected?.definition;if(definition){box.append(node('p',definition.summary));if(!selected.source&&existing.feat==='grappler')box.append(node('p','Requires Strength or Dexterity 13 before this feat’s increase.'))
    if(selected.feature){const req=selected.feature.prerequisites;box.append(node('small',`Source feature prerequisites at acquisition: character level ${req.level}${Object.entries(req.classes).map(([cls,n])=>', '+title(cls)+' '+n).join('')}${Object.entries(req.abilities).map(([a,n])=>', '+a.toUpperCase()+' '+n).join('')}.`));existing.choices??={};for(const choice of selected.feature.choices)box.append(choiceGroup(choice.name+' · choose '+choice.count,choice.options.map(o=>[o.id,o.name]),existing.choices[choice.id]||[],value=>existing.choices[choice.id]=value))}
    if(definition.increase){const picks=[];for(const [ability,amount]of Object.entries(existing.abilities||{}))for(let i=0;i<amount;i++)picks.push(ability)
     const controls=[];const update=()=>{const abilities={};for(const control of controls)if(control.value)abilities[control.value]=(abilities[control.value]||0)+1;existing.abilities=abilities;markBuild()}
     for(let i=0;i<definition.increase;i++){const control=select([['','Choose +1'],...definition.abilities.map(k=>[k,k.toUpperCase()])],picks[i]);controls.push(control);control.addEventListener('change',update);box.append(label('Level '+level+' ability increase '+(i+1),control))}box.append(node('small','Maximum ability score from this feat: '+definition.cap+'. The server checks prerequisites at the level this feat is acquired.'))
    }
    if(definition.category==='origin')box.append(node('p','Any extra spell or proficiency selections appear in the origin section above.'))
   }progressionArea.append(box)
  }
 }
 function renderInvocations(level){
  draft.class_choices.warlock??={};const entries=draft.class_choices.warlock.invocations??=[],limit=catalogue.classes.warlock.progression[level-1].values.invocations,area=fieldset('Warlock invocations · choose '+limit),pickers=[]
  const updateCantrips=()=>{const known=[...new Set([...(draft.spell_choices.warlock?.cantrips||[]),...entries.filter(e=>e.id==='pact_of_the_tome').flatMap(e=>e.cantrips||[])])];for(const [entry,pick]of pickers){const available=known.filter(id=>catalogue.class_options.damaging_cantrips.includes(id)&&(entry.id!=='repelling_blast'||catalogue.class_options.attack_cantrips.includes(id))&&(entry.id!=='eldritch_spear'||/^(\d+) feet$/.test(catalogue.spells[id].range)&&Number(catalogue.spells[id].range.split(' ')[0])>=10)),ids=[...new Set([...available,...(entry.cantrip?[entry.cantrip]:[])])];pick.replaceChildren(node('option','Choose a Warlock cantrip',{value:''}));for(const id of ids)pick.append(node('option',(catalogue.spells[id]?.name||title(id))+(available.includes(id)?'':' · unavailable here'),{value:id}));pick.value=entry.cantrip||''}}
  const changed=()=>{markBuild();cleanOriginChoices();updateOriginForms()}
  area.append(node('p','Select base cantrips in Spells below. Book of Shadows cantrips also qualify for invocation choices. Prerequisites use Warlock class level, and repeatable invocations must choose a different spell or feat.'))
  entries.forEach((entry,index)=>{
   const row=fieldset('Invocation '+(index+1)),options=Object.entries(catalogue.class_options.invocations).map(([id,o])=>[id,o.name+' · Warlock '+o.minimum_level+'+']),pick=select([['','Choose invocation'],...options],entry.id),definition=catalogue.class_options.invocations[entry.id]
   pick.addEventListener('change',()=>{entries[index]={id:pick.value};changed();renderTraining()});row.append(label('Invocation '+(index+1),pick));if(definition)row.append(node('p',definition.summary))
   if(definition?.choice==='cantrip'){const cantrip=select([],entry.cantrip);cantrip.addEventListener('change',()=>{entry.cantrip=cantrip.value;markBuild()});pickers.push([entry,cantrip]);row.append(label('Cantrip for invocation '+(index+1),cantrip))}
   if(definition?.choice==='feat'){const feat=select([['','Choose Origin feat'],...catalogue.origins.origin_feats.map(f=>[f,title(f)])],entry.feat);feat.addEventListener('change',()=>{entry.feat=feat.value;changed()});row.append(label('Origin feat for invocation '+(index+1),feat))}
   if(definition?.choice==='movement'){const movement=select([['','Choose familiar movement'],['fly','Fly 40 feet'],['swim','Swim 40 feet']],entry.movement);movement.addEventListener('change',()=>{entry.movement=movement.value;markBuild()});row.append(label('Familiar movement for invocation '+(index+1),movement))}
   if(definition?.choice==='tome'){row.append(node('p','These spells must not already be prepared through another feature. The book must be carried to use its grants.'),choiceGroup('Book of Shadows cantrips · choose 3',Object.entries(catalogue.spells).filter(([,s])=>s.level===0).map(([id,s])=>[id,s.name]),entry.cantrips||[],value=>{entry.cantrips=value;updateCantrips()}),choiceGroup('Book of Shadows Ritual spells · choose 2',Object.entries(catalogue.spells).filter(([,s])=>s.level===1&&s.ritual).map(([id,s])=>[id,s.name]),entry.rituals||[],value=>entry.rituals=value))}
   if(definition?.choice==='weapon'){const weapon=select([['','Choose initial pact weapon'],...Object.entries(catalogue.equipment.weapons).filter(([,w])=>w.category.endsWith('melee')).map(([id,w])=>[id,w.name])],entry.weapon),cha=input('checkbox',''),type=select([['normal','Normal weapon damage'],['necrotic','Necrotic'],['psychic','Psychic'],['radiant','Radiant']],entry.damage_type||'normal');cha.checked=entry.use_charisma!==false;weapon.addEventListener('change',()=>{entry.weapon=weapon.value;markBuild()});cha.addEventListener('change',()=>{entry.use_charisma=cha.checked;markBuild()});type.addEventListener('change',()=>{entry.damage_type=type.value;markBuild()});row.append(label('Pact weapon',weapon),label('Use Charisma with pact weapon',cha),label('Pact weapon damage type',type),node('p','Also add this weapon under Carried weapons. This records the initial bond; active bonds and magic-item inventory are still being connected.'))}
   row.append(button('Remove invocation '+(index+1),()=>{entries.splice(index,1);changed();renderTraining()}));area.append(row)
  })
  const add=button('Add invocation',()=>{entries.push({id:''});markBuild();renderTraining()});add.disabled=entries.length>=Math.min(10,limit);area.append(add);trainingArea.append(area);updateCantrips()
 }
 function renderInvocationSummary(calculated,target){
  const inv=calculated.invocations;if(!inv?.level)return;const details=node('details');details.append(node('summary','Warlock invocations'))
  for(const entry of inv.selected){const def=catalogue.class_options.invocations[entry.id];details.append(node('h4',def.name),node('p',def.summary));if(entry.cantrip)details.append(node('p','Chosen cantrip: '+(catalogue.spells[entry.cantrip]?.name||title(entry.cantrip))));if(entry.feat)details.append(node('p','Origin feat: '+title(entry.feat)))}
  for(const [id,mod]of Object.entries(inv.cantrip_modifiers)){const bits=[];if(mod.damage_bonus!==undefined)bits.push('damage rolls '+signed(mod.damage_bonus));if(mod.range_bonus_feet)bits.push('range +'+mod.range_bonus_feet+' feet');if(mod.push)bits.push('optional push up to 10 feet against Large or smaller targets');details.append(node('p',catalogue.spells[id].name+': '+bits.join(' · ')))}
  for(const spell of inv.spell_grants){const row=node('div',undefined,{class:'sheet-spell-row'});row.append(node('span',spell.name+' · '+title(spell.mode)+(spell.target_scope==='self'?' · self only':'')+(spell.requires_book_carried?' · book carried':'')),spellReference(spell.spell));details.append(row)}
  if(inv.bond?.weapon){const attack=calculated.attacks[inv.bond.weapon];details.append(node('p','Pact weapon: '+catalogue.equipment.weapons[inv.bond.weapon].name+(attack?' · '+attack.attack+' · '+attack.damage+' '+title(attack.damage_type)+' · up to '+attack.attacks_per_action+' attacks with this weapon':'')))}
  if(inv.traits.depths)details.append(node('p','Underwater breathing · Swim Speed '+inv.traits.depths.swim_speed+' feet'))
  if(inv.traits.devils_sight)details.append(node('p','Devil’s Sight: normal vision in magical and nonmagical Dim Light and Darkness, 120 feet'))
  if(inv.traits.witch_sight)details.append(node('p','Truesight 30 feet'))
  details.append(node('p','Casting, active bonds/books/familiars, turn effects and limited-use spending are still being connected. These are calculated draft grants.'));target.append(details)
 }
 function cleanTraining(){cleanSubclassChoices(draft,sheet.document.rules_sources||[]);const classes=new Set(draft.levels);for(const key of Object.keys(draft.class_choices))if(!classes.has(key))delete draft.class_choices[key];if(draft.levels.filter(k=>k==='sorcerer').length<2&&draft.class_choices.sorcerer)delete draft.class_choices.sorcerer.metamagic;if(draft.levels.filter(k=>k==='wizard').length<2&&draft.class_choices.wizard)delete draft.class_choices.wizard.scholar;if(draft.levels[0]!=='monk'&&draft.class_choices.monk)delete draft.class_choices.monk.tools;for(const key of Object.keys(draft.spell_choices))if(!classes.has(key)||!catalogue.classes[key].casting_ability)delete draft.spell_choices[key];for(const key of Object.keys(draft.fighting_styles))if(!classes.has(key)||(key!=='fighter'&&draft.levels.filter(k=>k===key).length<2))delete draft.fighting_styles[key];for(const key of Object.keys(draft.weapon_masteries))if(!classes.has(key))delete draft.weapon_masteries[key];for(const key of Object.keys(draft.multiclass_skills))if(!classes.has(key)||key===draft.levels[0])delete draft.multiclass_skills[key]}
 function renderEquipment(parent){
  const equipment=fieldset('4. Equipment'),armor=select([['','Unarmored'],...Object.entries(catalogue.equipment.armor).map(([k,a])=>[k,a.name])],draft.armor),shield=input('checkbox',''),ac=select([['','Best available formula'],['unarmored','Unarmored'],['armor','Worn armor'],['barbarian','Barbarian Unarmored Defense'],['monk','Monk Unarmored Defense'],['draconic','Draconic Resilience']],draft.ac_method)
  armor.addEventListener('change',()=>{draft.armor=armor.value||null;markBuild()});shield.checked=draft.shield;shield.addEventListener('change',()=>{draft.shield=shield.checked;markBuild()});ac.addEventListener('change',()=>{if(ac.value)draft.ac_method=ac.value;else delete draft.ac_method;markBuild()});equipment.append(label('Armor',armor),label('Shield equipped',shield),label('Armor class formula',ac))
  const hands=node('div'),renderHands=()=>{hands.replaceChildren();const weapons=Object.entries(catalogue.equipment.weapons).filter(([k,w])=>draft.weapons.includes(k)&&w.versatile_damage);if(weapons.length)hands.append(choiceGroup('Use versatile weapons in two hands',weapons.map(([k,w])=>[k,w.name]),draft.two_handed_weapons,value=>draft.two_handed_weapons=value))}
  equipment.append(choiceGroup('Carried weapons',Object.entries(catalogue.equipment.weapons).map(([k,w])=>[k,w.name]),draft.weapons,value=>{draft.weapons=value;draft.two_handed_weapons=draft.two_handed_weapons.filter(k=>value.includes(k));renderHands()}),hands);renderHands();parent.append(equipment)
 }
 function renderBuilder(parent){
  const details=node('details');details.append(node('summary','Build and level up character'));details.append(node('p','The core builder is in development. Save a calculated draft now; finalizing for automatic play remains unavailable while rules features are unfinished.'))
  renderBasics(details);const progression=fieldset('2. Class progression');progressionArea=node('div');progression.append(progressionArea);details.append(progression);const training=fieldset('3. Skills and class training');trainingArea=node('div');training.append(trainingArea);details.append(training);renderProgression();renderTraining();renderEquipment(details);renderSpells(details);renderCustomFeatures(details)
  const result=node('div',undefined,{'data-build-result':''});summary(sheet.document.build?.calculated,result)
  details.append(button('Preview calculations',async()=>{const data=await request(`sheets/${id}/preview`,{input:draft});summary(data.calculated,result);tell('Preview calculated. Your saved sheet has not changed.')}),button('Save build draft',async()=>{const data=await request(`sheets/${id}/build`,{revision:sheet.revision,input:draft,finalize:false});setSheet(data.sheet);buildDirty=false;render();tell('Calculated build draft saved. Unfinished features remain listed below.')}),result);parent.append(details)
 }
 function originFeats(){const c=draft.origin_choices,human=c.human_feat_source?'private:'+c.human_feat_source.source+':'+c.human_feat:c.human_feat;return [backgroundDefinition()?.feat,...(draft.species==='human'&&human?[human]:[]),...(draft.class_choices.warlock?.invocations||[]).filter(i=>i.id==='lessons_of_the_first_ones'&&i.feat).map(i=>i.feat),...(draft.advancements||[]).filter(a=>!a.source&&catalogue.feat_options[a.feat]?.category==='origin').map(a=>a.feat)].filter(Boolean)}
 function cleanOriginChoices(){
  const c=draft.origin_choices,fields={dragonborn:['dragon_ancestry'],elf:['elven_lineage','casting_ability','high_elf_cantrip'],gnome:['gnomish_lineage','casting_ability'],goliath:['giant_ancestry'],human:['human_feat','skilled'],tiefling:['fiendish_legacy','casting_ability']},allowed=fields[draft.species]||[]
  for(const key of new Set(Object.values(fields).flat()))if(!allowed.includes(key)&&!(key==='skilled'&&originFeats().includes('skilled')))delete c[key]
  if(draft.species!=='human'){delete c.human_feat_source;delete c.human_feat_choices}
  if(catalogue.origins.fixed_sizes[draft.species])delete c.size
  if(backgroundDefinition()?.tool!=='choose_gaming_set')delete c.background_tool
  if(!originFeats().includes('skilled'))delete c.skilled
  if(draft.species==='elf'&&c.elven_lineage!=='high')delete c.high_elf_cantrip
  const lists=originFeats().filter(f=>f.startsWith('magic_initiate_')).map(f=>f.slice(15));for(const key of Object.keys(c.magic_initiate||{}))if(!lists.includes(key))delete c.magic_initiate[key]
  const counts={};for(const cls of draft.levels)counts[cls]=(counts[cls]||0)+1;for(const key of Object.keys(c.class_languages||{}))if((key==='ranger'&&(counts.ranger||0)<2)||(key==='rogue'&&!counts.rogue))delete c.class_languages[key]
 }
 function updateOriginForms(){
  if(!originArea)return;const c=draft.origin_choices,data=catalogue.origins;originArea.replaceChildren(node('h4','Origin traits and languages'))
  const selector=(text,items,value,change)=>{const field=select([['','Choose…'],...items],value);field.addEventListener('change',()=>{change(field.value);markBuild()});originArea.append(label(text,field));return field}
  if(!data.fixed_sizes[draft.species])selector('Species size',[['small','Small'],['medium','Medium']],c.size,value=>c.size=value)
  if(backgroundDefinition()?.tool==='choose_gaming_set')selector('Background Gaming Set',Object.entries(data.tools).filter(([,t])=>t.category==='gaming').map(([id,t])=>[id,t.name]),c.background_tool,value=>c.background_tool=value)
  if(draft.species==='human'){
   const entries=originFeatEntries(catalogue,sheet.document.rules_sources||[]).filter(e=>e.source||e.id!==backgroundDefinition()?.feat),selected=chosenHumanFeat(c,entries)
   selector('Human Origin feat',entries.map(e=>[e.key,`${e.name||title(e.id)}${e.source?' · '+e.sourceName+' '+e.source.version:''}`]),selected?.key||'',value=>{const entry=entries.find(e=>e.key===value);if(entry){c.human_feat=entry.id;if(entry.source)c.human_feat_source=structuredClone(entry.source);else delete c.human_feat_source}else{delete c.human_feat;delete c.human_feat_source}delete c.human_feat_choices;cleanOriginChoices();updateOriginForms()})
   if(c.human_feat&&!selected)originArea.append(node('p','The selected Human feat source is unavailable or changed. Its saved choices remain until you select a replacement.'))
   if(selected?.feature){c.human_feat_choices??={};originArea.append(node('p',selected.feature.description));for(const choice of selected.feature.choices)originArea.append(choiceGroup(choice.name+' · choose '+choice.count,choice.options.map(o=>[o.id,o.name]),c.human_feat_choices[choice.id]||[],value=>c.human_feat_choices[choice.id]=value))}
  }
  const lineage={elf:['elven_lineage',Object.keys(data.lineages.elf)],gnome:['gnomish_lineage',Object.keys(data.lineages.gnome)],tiefling:['fiendish_legacy',Object.keys(data.lineages.tiefling)],dragonborn:['dragon_ancestry',Object.keys(data.dragon_ancestries)],goliath:['giant_ancestry',data.giant_ancestries]}[draft.species]
  if(lineage)selector(title(lineage[0]),lineage[1].map(id=>[id,title(id)]),c[lineage[0]],value=>{c[lineage[0]]=value;cleanOriginChoices();updateOriginForms()})
  if(['elf','gnome','tiefling'].includes(draft.species))selector('Species spellcasting ability',[['int','Intelligence'],['wis','Wisdom'],['cha','Charisma']],c.casting_ability,value=>c.casting_ability=value)
  if(draft.species==='elf'&&c.elven_lineage==='high')selector('High Elf cantrip',Object.entries(catalogue.spells).filter(([,s])=>s.level===0&&s.classes.includes('wizard')).map(([id,s])=>[id,s.name]),c.high_elf_cantrip||'prestidigitation',value=>c.high_elf_cantrip=value)
  if(originFeats().includes('skilled')){c.skilled??={skills:[],tools:[]};originArea.append(node('p','Skilled: choose '+(3*originFeats().filter(f=>f==='skilled').length)+' total proficiencies across skills and tools.'),choiceGroup('Skilled skill choices',skillItems(Object.keys(catalogue.skills)),c.skilled.skills||[],value=>c.skilled.skills=value),choiceGroup('Skilled tool choices',Object.entries(data.tools).map(([id,t])=>[id,t.name]),c.skilled.tools||[],value=>c.skilled.tools=value))}
  originArea.append(choiceGroup('Starting languages · choose 2 in addition to Common',data.standard_languages.map(id=>[id,title(id)]),c.languages||[],value=>{c.languages=value;updateOriginForms()}))
  const counts={};for(const cls of draft.levels)counts[cls]=(counts[cls]||0)+1;const known=['common',...(c.languages||[]),...(counts.druid?['druidic']:[]),...(counts.rogue?['thieves_cant']:[])]
  for(const [cls,count]of [['ranger',counts.ranger>=2?2:0],['rogue',counts.rogue?1:0]])if(count){c.class_languages??={};const selected=c.class_languages[cls]||[],all=[...data.standard_languages,...data.rare_languages],ids=[...new Set([...all.filter(id=>!known.includes(id)),...selected])];originArea.append(choiceGroup(`${title(cls)} additional languages · choose ${count}`,ids.map(id=>[id,title(id)+(known.includes(id)?' · already known':'')]),selected,value=>c.class_languages[cls]=value));known.push(...selected)}
  const lists=originFeats().filter(f=>f.startsWith('magic_initiate_')).map(f=>f.slice(15))
  for(const list of lists){c.magic_initiate??={};const mi=c.magic_initiate[list]??={ability:'',cantrips:[],spell:''},box=fieldset('Magic Initiate ('+title(list)+')'),ability=select([['','Choose casting ability'],['int','Intelligence'],['wis','Wisdom'],['cha','Charisma']],mi.ability);ability.addEventListener('change',()=>{mi.ability=ability.value;markBuild()});box.append(label('Magic Initiate '+title(list)+' casting ability',ability))
   const available=Object.entries(catalogue.spells).filter(([,s])=>s.classes.includes(list)),cantrips=available.filter(([,s])=>s.level===0).map(([id])=>id);box.append(spellPicker('Magic Initiate '+title(list)+' cantrips · choose 2',cantrips,mi.cantrips||[],value=>mi.cantrips=value))
   const pick=select([['','Choose a level-one spell'],...available.filter(([,s])=>s.level===1).map(([id,s])=>[id,s.name])],mi.spell),reference=node('div');if(mi.spell)reference.append(spellReference(mi.spell));pick.addEventListener('change',()=>{mi.spell=pick.value;reference.replaceChildren();if(pick.value)reference.append(spellReference(pick.value));markBuild()});box.append(label('Magic Initiate '+title(list)+' level-one spell',pick),reference,node('p','This spell is always prepared and has one free cast per Long Rest. Other spell slots can also cast it.'));originArea.append(box)
  }
 }
 function renderOriginSummary(calculated,target){
  const o=calculated.origin;if(!o)return;const details=node('details');details.append(node('summary','Origin traits and defenses'),node('p',`${title(o.species)}${o.lineage?' · '+title(o.lineage):''}${o.ancestry?' · '+title(o.ancestry)+' ancestry':''} · ${o.size?title(o.size):'Choose size'} ${title(o.creature_type)}`),node('p','Origin feats: '+o.feats.map(f=>f===o.private_human_feat?.key?o.private_human_feat.name:title(f)).join(', ')),node('p','Languages: '+o.languages.map(title).join(', ')))
  if(o.tool_proficiencies.length)details.append(node('p','Tool proficiencies: '+o.tool_proficiencies.map(id=>catalogue.origins.tools[id]?.name||title(id)).join(', ')))
  if(o.resistances.length)details.append(node('p','Damage resistance: '+o.resistances.map(title).join(', ')))
  for(const save of o.saving_throw_advantage)details.append(node('p',`${save.source}: Advantage on ${save.abilities?save.abilities.map(a=>a.toUpperCase()).join(', ')+' saving throws':'saving throws to avoid or end '+save.conditions.map(title).join(', ')}`))
  if(o.immunities.length)details.append(node('p','Immunities: '+o.immunities.map(title).join(', ')))
  const value=v=>Array.isArray(v)?v.map(value).join(', '):v&&typeof v==='object'?Object.entries(v).map(([k,n])=>title(k)+': '+value(n)).join(' · '):typeof v==='boolean'?(v?'yes':'no'):String(v)
  for(const trait of Object.values(o.traits)){const section=node('details');section.append(node('summary',trait.name),node('p',value(trait.effects)));details.append(section)}
  for(const action of Object.values(o.actions)){const section=node('details');section.append(node('summary',action.name));for(const [key,v]of Object.entries(action))if(!['name','resource'].includes(key))section.append(node('p',title(key)+': '+value(v)));if(action.resource){const r=calculated.resources[action.resource];section.append(node('p',`Uses: ${r.max}; short rest ${r.short_rest==='all'?'restore all':r.short_rest}; long rest restore all`))}details.append(section)}target.append(details)
  for(const channel of Object.values(calculated.origin_spellcasting||{})){const section=node('details');section.append(node('summary',channel.name+' spell grants'),node('p',channel.ability?`${channel.ability.toUpperCase()} · spell attack ${signed(channel.attack)} · save DC ${channel.save_dc}`:'Choose a spellcasting ability to calculate these spell statistics.'))
   for(const [key,name]of [['cantrips','Cantrips'],['always_prepared','Always-prepared spells'],['rituals','Available rituals']])if(channel[key].length){section.append(node('h4',name));for(const spell of channel[key]){const row=node('div',undefined,{class:'sheet-spell-row'});row.append(node('span',spell.name+(channel.free_casts[spell.id]?` · ${channel.free_casts[spell.id]} free cast(s) per Long Rest`:'')),spellReference(spell.id));section.append(row)}}target.append(section)
  }
 }
 function spellReference(id){
  const wrap=node('div',undefined,{class:'sheet-spell-reference'}),content=node('div');let loaded=false
  wrap.append(button('Read '+(catalogue?.spells[id]?.name||title(id)),async()=>{if(loaded){content.hidden=!content.hidden;return}const data=await request('spells/'+id),s=data.spell;content.append(node('h4',s.name),node('p',`${s.level===0?'Cantrip':'Level '+s.level} ${s.school} · ${s.classes.map(title).join(', ')}`),node('p',`Casting time: ${s.casting_time} · Range: ${s.range}`),node('p',`Components: ${s.components} · Duration: ${s.duration}`),node('p',s.description,{class:'sheet-spell-description'}),node('small',s.license.attribution));loaded=true}),content);return wrap
 }
 function spellPicker(text,available,selected,change){
  const group=fieldset(text),search=input('search','',{placeholder:'Filter spells by name'}),count=node('p'),list=node('div',undefined,{class:'sheet-spell-list'}),ids=[...new Set([...available,...selected])],checks=[];search.setAttribute('aria-label','Filter '+text)
  const recount=()=>{count.textContent=`${checks.filter(c=>c.input.checked).length} selected`}
  for(const id of ids){const spell=catalogue.spells[id];if(!spell)continue;const row=node('div',undefined,{class:'sheet-spell-row'}),check=input('checkbox','');check.checked=selected.includes(id);checks.push({id,input:check});row.dataset.spellName=spell.name.toLowerCase();row.append(label(`${spell.name} · ${spell.level===0?'cantrip':'level '+spell.level}${available.includes(id)?'':' · unavailable here'}`,check),spellReference(id));check.addEventListener('change',()=>{change(checks.filter(c=>c.input.checked).map(c=>c.id));markBuild();recount()});list.append(row)}
  search.addEventListener('input',()=>{for(const row of list.children)row.hidden=!row.dataset.spellName.includes(search.value.trim().toLowerCase())});group.append(search,count,list);recount();return group
 }
 function spellCeiling(cls,n){const row=catalogue.classes[cls].progression[n-1].values;if(cls==='warlock')return row.pact_slot_level;return Math.max(0,...Object.entries(row).filter(([k,v])=>k.startsWith('slot_')&&v>0).map(([k])=>Number(k.slice(5))))}
 function renderSpells(parent){spellArea=fieldset('5. Spells');parent.append(spellArea);updateSpellForms()}
 function updateSpellForms(){
  if(!spellArea)return;spellArea.replaceChildren(node('legend','5. Spells'));const counts={};for(const cls of draft.levels)counts[cls]=(counts[cls]||0)+1
  const classes=Object.keys(counts).filter(cls=>catalogue.classes[cls].casting_ability)
  if(!classes.length){spellArea.append(node('p','This class progression does not grant base spellcasting. Species, feats and other feature-granted spells will appear with their dedicated choices as those handlers are completed.'));return}
  for(const cls of classes){const n=counts[cls],row=catalogue.classes[cls].progression[n-1].values,max=spellCeiling(cls,n),style=draft.fighting_styles[cls],extraOrder=(cls==='cleric'&&draft.class_choices.cleric?.order==='thaumaturge')||(cls==='druid'&&draft.class_choices.druid?.order==='magician'),cantripLimit=(row.cantrips||0)+(extraOrder?1:0)+(['blessed_warrior','druidic_warrior'].includes(style)?2:0),cantripList=style==='blessed_warrior'?'cleric':style==='druidic_warrior'?'druid':cls,choices=draft.spell_choices[cls]??={cantrips:[],prepared:[],spellbook:[],arcanum:{}},section=node('details');section.open=true;section.append(node('summary',catalogue.classes[cls].name+' spells'),node('p',`Class level ${n}: ${cantripLimit} cantrips and ${row.prepared} normal prepared spells, up to spell level ${max}. Combined multiclass slots do not expand this class’s spell choices.`))
   const always=[...new Set([...Object.entries(catalogue.spell_rules.always_prepared[cls]||{}).filter(([at])=>n>=Number(at)).flatMap(([,spells])=>spells),...subclassAlways(cls,n)])],savant=cls==='wizard'&&!draft.subclasses.wizard?.source&&draft.subclasses.wizard?.id==='evoker'?(draft.subclasses.wizard.choices.savant_spells||[]).map(e=>e.spell):[]
   if(always.length)section.append(node('p','Automatically prepared in addition: '+always.map(id=>catalogue.spells[id].name).join(', ')))
   const available=(levelZero=false)=>Object.keys(catalogue.spells).filter(id=>{const s=catalogue.spells[id],lists=cls==='bard'&&n>=10&&!levelZero?['bard','cleric','druid','wizard']:[levelZero?cantripList:cls];return s.classes.some(c=>lists.includes(c))&&(levelZero?s.level===0:s.level>0&&s.level<=max)})
   if(cantripLimit)section.append(spellPicker(`${catalogue.classes[cls].name} cantrips · choose ${cantripLimit}`,available(true),choices.cantrips||[],value=>{choices.cantrips=value;if(cls==='warlock')renderTraining()}))
   const prepared=node('div'),renderPrepared=()=>{prepared.replaceChildren(spellPicker(`${catalogue.classes[cls].name} prepared spells · choose ${row.prepared}`,available().filter(id=>!always.includes(id)&&(cls!=='wizard'||(savant.includes(id)||(choices.spellbook||[]).some(e=>e.spell===id)))),choices.prepared||[],value=>choices.prepared=value))}
   if(cls==='wizard'){
    const book=fieldset(`Wizard spellbook · ${6+2*(n-1)} class-learned spells, plus copied and Savant grants`),filter=input('search','',{placeholder:'Filter spellbook by name'}),list=node('div',undefined,{class:'sheet-spell-list'});filter.setAttribute('aria-label','Filter Wizard spellbook')
    book.append(node('p','Six starting level-one spells, then two new spells at each Wizard level. Copied spells record their transcription cost and time; the money/time ledger is still pending.'))
    if(savant.length)book.append(node('p','Evocation Savant grants: '+savant.map(id=>catalogue.spells[id]?.name||title(id)).join(', ')))
    const availableBook=available().filter(id=>!savant.includes(id)),ids=[...new Set([...availableBook,...(choices.spellbook||[]).map(e=>e.spell)])]
    for(const id of ids){const s=catalogue.spells[id];if(!s)continue;const row=node('div',undefined,{class:'sheet-spell-row'}),existing=(choices.spellbook||[]).find(e=>e.spell===id),check=input('checkbox',''),method=select([['level','Class learning'],['copied','Copied into book']],existing?.method||'level');check.checked=!!existing;method.disabled=!check.checked;row.dataset.spellName=s.name.toLowerCase();method.setAttribute('aria-label','Acquisition for '+s.name)
     const update=()=>{choices.spellbook=(choices.spellbook||[]).filter(e=>e.spell!==id);if(check.checked)choices.spellbook.push({spell:id,method:method.value});method.disabled=!check.checked;markBuild();renderPrepared()};check.addEventListener('change',update);method.addEventListener('change',update);row.append(label(`${s.name} · level ${s.level}${availableBook.includes(id)?'':' · unavailable here'}`,check),method,spellReference(id));list.append(row)
    }filter.addEventListener('input',()=>{for(const row of list.children)row.hidden=!row.dataset.spellName.includes(filter.value.trim().toLowerCase())});book.append(filter,list);section.append(book)
   }
   renderPrepared();section.append(prepared)
   if(cls==='warlock')for(let level=6;level<=9;level++){const unlocked=n>=11+2*(level-6);if(!unlocked&&!choices.arcanum?.[level])continue;const options=Object.entries(catalogue.spells).filter(([,s])=>s.level===level&&s.classes.includes('warlock')).map(([id,s])=>[id,s.name]),pick=select([['','Choose arcanum'],...options],choices.arcanum?.[level]);pick.addEventListener('change',()=>{choices.arcanum??={};if(pick.value)choices.arcanum[level]=pick.value;else delete choices.arcanum[level];markBuild()});section.append(label(`Level ${level} Mystic Arcanum${unlocked?'':' · not unlocked at this class level'}`,pick))}
   spellArea.append(section)
  }
 }
 function renderCustomFeatures(parent){
  const area=fieldset('6. Campaign and homebrew features');parent.append(area)
  function draw(){
   area.replaceChildren(node('legend','6. Campaign and homebrew features'))
   const sources=sheet.document.rules_sources||[]
   if(!sources.length){area.append(node('p','No private rules sources have been added. Add a campaign source below to make its features available here.'));return}
   for(const snapshot of sources){const source=snapshot.source,group=node('details');group.open=source.features.some(f=>draft.custom_features.some(p=>p.source===source.id&&p.version===source.version&&p.feature===f.id));group.append(node('summary',`${source.name} · ${source.version}`))
    for(const feature of source.features){const selected=draft.custom_features.find(f=>f.source===source.id&&f.version===source.version&&f.feature===feature.id),check=input('checkbox','');check.checked=!!selected
     check.addEventListener('change',()=>{draft.custom_features=draft.custom_features.filter(f=>!(f.source===source.id&&f.feature===feature.id));if(check.checked)draft.custom_features.push({source:source.id,version:source.version,digest:snapshot.digest,feature:feature.id,choices:{}});markBuild();draw()})
     const box=fieldset(feature.name);box.append(label('Include '+feature.name,check),node('p',feature.description));const req=feature.prerequisites;box.append(node('small',`Requires character level ${req.level}${Object.entries(req.classes).map(([cls,n])=>`, ${title(cls)} ${n}`).join('')}${Object.entries(req.abilities).map(([ability,n])=>`, ${ability.toUpperCase()} ${n}`).join('')}`))
     if(selected)for(const choice of feature.choices){box.append(choiceGroup(`${choice.name} · choose ${choice.count}`,choice.options.map(o=>[o.id,o.name]),selected.choices[choice.id]||[],value=>selected.choices[choice.id]=value))}
     group.append(box)
    }if(source.backgrounds?.length)group.append(node('p',`${source.backgrounds.length} background definition(s) are available in Abilities and origin.`));area.append(group)
   }
   area.append(node('p','Selecting the same feature from another version replaces that feature’s pending selection and resets its choices. Save the build to commit the change.'))
  }draw()
 }
 function renderSources(parent){
  const details=node('details');details.append(node('summary','Campaign and homebrew rules sources'),node('p','Full source definitions stay private to the owner and site administrator. Invited readers see selected feature descriptions and calculated effects. Saved versions are immutable. Import a newer version to update rules, then select that version’s features and save the build. Existing selections keep their pinned version.'))
  const list=node('ul');for(const snapshot of sheet.document.rules_sources||[]){const source=snapshot.source,item=node('li',`${source.name} · ${source.version} · ${source.features.length} feature(s)${source.subclasses?.length?' · '+source.subclasses.length+' subclass(es)':''}${source.feats?.length?' · '+source.feats.length+' advancement feat(s)':''}${source.origin_feats?.length?' · '+source.origin_feats.length+' Origin feat(s)':''}${source.backgrounds?.length?' · '+source.backgrounds.length+' background(s)':''} · ${source.license}`);if(source.attribution)item.append(node('p',source.attribution));list.append(item)}details.append(list)
  const file=input('file','',{accept:'.json,application/json'});details.append(label('Import structured rules source (.json)',file),button('Import rules source',async()=>{const chosen=file.files[0];if(!chosen)throw new Error('Choose a rules-source JSON file first.');if(chosen.size>180000)throw new Error('Use a rules-source file smaller than 180 KB.');let source;try{source=JSON.parse(await chosen.text())}catch{throw new Error('That file is not valid JSON.')}await importSource(source)}))
  const create=node('details');create.append(node('summary','Create a simple homebrew feature'),node('p','Add one constant stat bonus. More complex choices, prerequisites and resource formulas can be imported as a structured rules source.'))
  const sourceId=input('text','campaign_rules',{maxlength:48}),version=input('text','1.0.0',{maxlength:16}),sourceName=input('text','Campaign rules',{maxlength:120}),featureId=input('text','custom_training',{maxlength:48}),name=input('text','Custom training',{maxlength:120}),description=node('textarea',undefined,{rows:3,maxlength:4000}),amount=input('number',1,{min:-10000,max:10000,step:1})
  const targets=[['hp_max','Maximum HP'],['armor_class','Armor class'],['initiative','Initiative'],['speed','Speed'],['darkvision','Darkvision'],...Object.keys(catalogue.skills).map(k=>['skill.'+k,title(k)+' skill']),...['str','dex','con','int','wis','cha'].map(k=>['save.'+k,k.toUpperCase()+' save'])],target=select(targets,'hp_max')
  create.append(label('Source identifier',sourceId),label('Source version',version),label('Source name',sourceName),label('Feature identifier',featureId),label('Feature name',name),label('Description',description),label('Bonus applies to',target),label('Bonus amount',amount),button('Create feature source',async()=>{if(amount.value.trim()===''||!Number.isInteger(Number(amount.value)))throw new Error('Use a whole-number bonus.');await importSource({schema:1,id:sourceId.value,version:version.value,name:sourceName.value,license:'private',features:[{id:featureId.value,name:name.value,description:description.value,effects:[{id:'bonus',type:'bonus',target:target.value,value:Number(amount.value)}]}]})}));details.append(create);parent.append(details)
 }
 async function importSource(source){const data=await request(`sheets/${id}/sources`,{revision:sheet.revision,source});setSheet(data.sheet);render();tell('Rules source saved privately. Choose its background, feats, subclass or campaign features in the builder, then preview and save your build. Unsaved notes and build choices were kept.')}
 async function renderSharing(target){
  target.replaceChildren();if(!sheet.can_share)return
  target.append(node('h3','Who can see this sheet?'),node('p','Only you can invite or remove players. View invitations never grant editing or play control. NPC control is a separate invitation and also allows viewing. Your site administrator always has access.'))
  const data=await request(`sheets/${id}/invitations`),list=node('ul')
  for(const grant of data.invitations){const item=node('li',`${playerLabel(grant.viewer_subject)} · ${grant.grant_type==='control'?'NPC control and viewing':'View only'} `);item.append(button('Revoke invitation',async()=>{await request(`sheets/${id}/invitations`,{viewer_subject:grant.viewer_subject,type:grant.grant_type,enabled:false});await renderSharing(target);tell('Invitation revoked. Access is checked on the next request.')}));list.append(item)}target.append(list)
  const picker=select([['','Choose player'],...players.filter(p=>p.subject!==sheet.owner_subject).map(p=>[p.subject,p.label])],''),type=select([['view','View only'],...(sheet.is_npc?[['control','NPC control and viewing']]:[])],'view');target.append(label('Invite player',picker),label('Access',type),button('Grant invitation',async()=>{if(!picker.value)throw new Error('Choose a player first.');await request(`sheets/${id}/invitations`,{viewer_subject:picker.value,type:type.value,enabled:true});await renderSharing(target);tell('Invitation granted by the owner.')}))
 }
 function ownership(parent,create=false){
  const box=fieldset(create?'Assign private sheet owner':'Reassign private sheet owner'),picker=select([['','Unassigned · administrator only'],...players.map(p=>[p.subject,p.label])],sheet?.owner_subject)
  box.append(node('p',create?'Choose the owning player explicitly. Public profile editing rights do not determine private sheet ownership.':'Changing ownership removes every existing invitation. Only your configured administrator account can do this.'),label('Owning player',picker))
  const acknowledge=input('checkbox','');if(!create)box.append(label('I understand all current invitations will be removed',acknowledge))
  box.append(button(create?'Create private sheet':'Reassign owner and revoke invitations',async()=>{if(!create&&!acknowledge.checked)throw new Error('Acknowledge that reassignment removes invitations.');if(!create&&(notesDirty||buildDirty||inventoryDirty||manualDirty||inventoryWrites.pending))throw new Error('Save or discard your sheet edits before reassigning ownership.');const data=await request(create?'sheets':`sheets/${id}/owner`,create?{character_id:id,owner_subject:picker.value||null}:{revision:sheet.revision,owner_subject:picker.value||null});setSheet(data.sheet,true);render();tell(create?'Private sheet created.':'Owner changed and previous invitations removed.')}));parent.append(box)
 }
 async function renderInventory(target,statsView=null){
  const content=node('div'),section=node('details',undefined,{'data-inventory-view':''});section.open=inventoryDirty||inventoryWrites.pending;section.append(node('summary','Equipment and inventory'),content);target.append(section)
  let inventoryCatalogue=null
  const primaryStats=(calculated,message)=>{if(!statsView)return;summary(calculated||sheet.document.build?.calculated,statsView.target);statsView.basis.textContent=message}
  const remember=(key,value)=>{inventoryDraft[key]=value;inventoryDirty=true}
  function textField(key,name,type='text',fallback='',attrs={}){const control=input(type,inventoryDraft[key]??fallback,attrs);control.addEventListener('input',()=>remember(key,control.value));return {control,wrap:label(name,control)}}
  function pickField(key,name,options,fallback=''){const control=select(options,inventoryDraft[key]??fallback);control.addEventListener('change',()=>remember(key,control.value));return {control,wrap:label(name,control)}}
  const whole=(control,name,max=1000000)=>{if(control.value.trim()===''||!Number.isInteger(Number(control.value))||Number(control.value)<1||Number(control.value)>max)throw new Error(`${name} must be a whole number from 1 to ${max}.`);return Number(control.value)}
  async function apply(command){
   try{const result=await inventoryWrites.commit(sheet,command);setSheet(result.sheet);inventoryDraft=inventoryDraftAfterCommit(inventoryDraft,command.kind);inventoryDirty=Object.keys(inventoryDraft).length>0;await load();tell('Inventory record saved. Your unsaved notes and builder choices were kept.')}
   catch(e){if(inventoryWrites.pending){showRetry();throw new Error(e.message+' The result is unconfirmed. Retry the same change before making another inventory change.')}throw e}
  }
  function showRetry(){
   for(const control of content.querySelectorAll('input,select,button'))control.disabled=true
   const warning=node('div',undefined,{role:'alert'});warning.append(node('p','The last inventory change is unconfirmed. Retry sends the same request; it will not add or spend items twice.'),button('Retry inventory change',async()=>{try{const kind=inventoryWrites.kind,result=await inventoryWrites.retry();setSheet(result.sheet);inventoryDraft=inventoryDraftAfterCommit(inventoryDraft,kind);inventoryDirty=Object.keys(inventoryDraft).length>0;await load();tell('Inventory change confirmed. Unsaved notes and builder choices were kept.')}catch(e){if(!inventoryWrites.pending)await load();throw e}}));warning.querySelector('button').setAttribute('data-inventory-retry','');content.append(warning)
  }
  function destination(key,name,v){return pickField(key,name,[['carried','Carried outside a container'],['stored','Stored away'],['ground','On the ground'],...(v?.items||[]).filter(i=>i.quantity===1&&inventoryCatalogue?.items[i.item_id]?.container).map(i=>['container:'+i.id,'In '+i.name])],'carried')}
  const location=control=>control.value.startsWith('container:')?{parent_id:control.value.slice(10)}:{location:control.value}
  const instanceLabel=(item,e)=>{const held=e?.held?.find(h=>h.instance_id===item.id);return `${item.name} × ${item.quantity}${e?.worn_armor===item.id?' · worn':held?' · held in '+held.grip+' '+(held.grip===1?'hand':'hands'):''}`}
  async function controls(v,canEdit,canControl){
   if(!canControl||inventoryWrites.pending)return
   inventoryCatalogue??=(await request('inventory-catalogue')).catalogue
   if(!v){if(canEdit){const box=fieldset('Start your inventory'),reason=textField('startReason','Inventory source or reason','text','',{maxlength:240});box.append(node('p','Start an empty inventory, then add your possessions. Starting equipment and coins are not granted automatically.'),reason.wrap,button('Start empty inventory',()=>apply({kind:'initialize',records:[],reason:reason.control.value})));content.append(box)}return}
   if(v.needs_rules_review||inventoryWrites.pending)return
   if(canEdit){
    const box=fieldset('Add possessions'),filter=textField('itemFilter','Find an item','search'),available=Object.values(inventoryCatalogue.items).filter(i=>!i.requires_expansion).sort((a,b)=>a.name.localeCompare(b.name)),item=pickField('addItem','Item',[['','Choose an item'],...available.map(i=>[i.id,i.name+(i.unit==='bag'?' (bag)':'')])]),quantity=textField('addQuantity','Quantity to add','number','1',{min:1,max:1000000}),where=destination('addLocation','Put new items',v),reason=textField('addReason','Acquisition source or reason','text','',{maxlength:240}),note=textField('addNote','Item note (optional)','text','',{maxlength:500}),hint=node('p')
    const refresh=()=>{const term=filter.control.value.trim().toLowerCase(),chosen=item.control.value||inventoryDraft.addItem||'';item.control.replaceChildren(node('option','Choose an item',{value:''}));for(const d of available)if(d.id===chosen||d.name.toLowerCase().includes(term))item.control.append(node('option',d.name+(d.unit==='bag'?' (bag)':''),{value:d.id}));item.control.value=chosen;const d=inventoryCatalogue.items[chosen];hint.textContent=d?`Quantity is counted in ${d.kind==='ammo'?'individual ammunition pieces':d.unit==='bag'?'whole bags':'individual items'}. ${d.weight_basis==='full'?'Weight assumes a full container. ':''}This records ownership without deducting coins.`:'Choose an item and record how it was acquired.'}
    filter.control.addEventListener('input',refresh);item.control.addEventListener('change',refresh);refresh()
    box.append(filter.wrap,item.wrap,quantity.wrap,where.wrap,reason.wrap,note.wrap,hint,button('Record acquired items',()=>{if(!item.control.value)throw new Error('Choose an item first.');return apply({kind:'record_acquisition',item_id:item.control.value,quantity:whole(quantity.control,'Quantity'),...location(where.control),reason:reason.control.value,note:note.control.value})}));content.append(box)
   }
   const entries=[['','Choose an inventory stack'],...v.items.map(i=>[i.id,instanceLabel(i,v.equipment)+(i.parent_id?' · in a container':' · '+i.effective_location)])],box=fieldset('Move or expend possessions'),item=pickField('changeItem','Inventory stack',entries),where=destination('moveLocation','Move whole stack to',v),quantity=textField('spendQuantity','Quantity expended','number','1',{min:1,max:1000000}),reason=textField('changeReason','Movement or expenditure reason','text','',{maxlength:240})
   box.append(item.wrap,where.wrap,quantity.wrap,reason.wrap,node('p','Moves apply to the entire stack. Release held items or remove worn armor before moving or expending them. Item expenditure records what happened; it does not perform an item action.'),button('Move whole stack',()=>{if(!item.control.value)throw new Error('Choose an inventory stack first.');return apply({kind:'move',instance_id:item.control.value,...location(where.control),reason:reason.control.value})}),button('Record items expended',()=>{if(!item.control.value)throw new Error('Choose an inventory stack first.');return apply({kind:'record_expenditure',instance_id:item.control.value,quantity:whole(quantity.control,'Quantity expended'),reason:reason.control.value})}));content.append(box)
   const equipment=fieldset('Record worn and held equipment'),eligible=v.items.filter(i=>!i.parent_id&&i.effective_location==='carried'),armor=pickField('armorChoice','Worn armor',[['unchanged','Keep armor record unchanged'],['none','No armor worn'],...eligible.filter(i=>i.kind==='armor').map(i=>[i.id,instanceLabel(i,v.equipment)])],'unchanged'),hands=pickField('handsChoice','Held equipment record',[['unchanged','Keep held record unchanged'],['record','Record held items below']],'unchanged'),handInputs=[]
   for(let index=0;index<2;index++){const item=pickField('handItem'+index,'Held item '+(index+1),[['','No item'],...eligible.map(i=>[i.id,instanceLabel(i,v.equipment)])],v.equipment.held[index]?.instance_id||''),grip=pickField('handGrip'+index,'Hands used for item '+(index+1),[['1','One hand'],['2','Two hands']],String(v.equipment.held[index]?.grip||1));handInputs.push({item,grip})}
   const equipmentReason=textField('equipmentReason','Equipment record reason','text','',{maxlength:240}),handArea=node('div');for(const h of handInputs)handArea.append(h.item.wrap,h.grip.wrap)
   const updateHands=()=>{handArea.hidden=hands.control.value!=='record'};hands.control.addEventListener('change',updateHands);updateHands()
   equipment.append(node('p','Record the equipment assignment agreed for the scene. This does not yet spend combat actions or enforce armor donning time. To record empty hands, choose “Record held items below” and leave both items empty.'),armor.wrap,hands.wrap,handArea,equipmentReason.wrap,button('Save equipment record',()=>{const command={kind:'record_equipment',reason:equipmentReason.control.value};if(armor.control.value!=='unchanged')command.armor=armor.control.value==='none'?null:armor.control.value;if(hands.control.value==='record')command.hands=handInputs.filter(h=>h.item.control.value).map(h=>({instance_id:h.item.control.value,grip:Number(h.grip.control.value)}));if(!('armor' in command)&&!('hands' in command))throw new Error('Choose an armor or held-equipment change first.');return apply(command)}));content.append(equipment)
  }
  async function load(){
   content.replaceChildren(node('p','Loading private inventory…'))
   try{
    const data=await request(`sheets/${id}/inventory`),v=data.inventory;content.replaceChildren()
    if(!v){primaryStats(null,'Build preview · physical equipment has not been recorded.');content.append(node('p','No possessions have been recorded yet. Builder equipment choices do not establish ownership.'));await controls(null,data.can_edit,data.can_control);if(inventoryWrites.pending)showRetry();return}
    const pounds=mlb=>new Intl.NumberFormat(undefined,{maximumFractionDigits:3}).format(mlb/1000)+' lb',byId=new Map(v.items.map(i=>[i.id,i]))
    content.append(node('p',`Carried items: ${pounds(v.known_carried_item_weight_mlb)}. Stored: ${pounds(v.stored_item_weight_mlb)}. On the ground: ${pounds(v.ground_item_weight_mlb)}.`))
    if(!v.coin_weight_known)content.append(node('p','Coins have not been recorded; their weight is not included.'))
    for(const issue of v.issues){const message=issue.kind==='container_over_capacity'?`${issue.name}: ${pounds(issue.contents_mlb)} of contents exceeds its ${pounds(issue.capacity_mlb)} capacity. Items remain where they were recorded.`:issue.kind==='container_unit_capacity'?`A container holds ${issue.quantity} units; its recorded limit is ${issue.maximum}.`:'A container needs review because its capacity or contents are not fully supported.';content.append(node('p',message,{class:'sheet-inventory-warning'}))}
    if(v.needs_rules_review)content.append(node('p','Inventory rules have changed. These are the retained records; review is required before changing them.',{class:'sheet-inventory-warning'}))
    const e=v.equipment,armor=e.armor_recorded?(e.worn_armor?byId.get(e.worn_armor)?.name:'None'):'Not recorded',hands=e.hands_recorded?(e.held_items.map(i=>`${i.name} (${i.grip} ${i.grip===1?'hand':'hands'})`).join(', ')||'Empty'):'Not recorded'
    content.append(node('p',`Worn armor: ${armor}. Held items: ${hands}.`));if(!e.armor_recorded)content.append(node('p','Armor class above is a builder preview; worn armor has not been confirmed.'))
    const list=node('ul');for(const item of v.items){const location=item.parent_id?`in ${byId.get(item.parent_id)?.name||'a container'} (${item.effective_location})`:item.effective_location,row=node('li',`${instanceLabel(item,v.equipment)} · ${pounds(item.weight_mlb)} · ${location}`);if(item.note){const note=node('details');note.append(node('summary','Import note'),node('p',item.note));row.append(note)}list.append(row)}
    content.append(list)
    const recorded=node('section');recorded.append(node('h4','Recorded loadout'));content.append(recorded)
    try{const response=await request(`sheets/${id}/loadout`),preview=response.preview;if(response.revision!==data.revision){primaryStats(null,'Build preview · equipment changed while loading.');recorded.append(node('p','Equipment changed while loading. Refresh inventory to review its current statistics.'))}else if(!preview.calculated){primaryStats(null,'Build preview · recorded equipment needs review.');for(const message of preview.issues)recorded.append(node('p',message))}else{const c=preview.calculated;if(data.revision===sheet.revision)primaryStats(c,'Statistics from recorded equipment and the saved character build.');else primaryStats(null,'Saved build preview · equipment changed elsewhere. Reload the saved sheet.');recorded.append(node('p',`Armor class ${c.armor_class} · Speed ${c.speed} ft. · Maximum HP ${c.hp_max}`),node('p','The builder retains its separate equipment preview. These statistics use the recorded loadout.'));const attacks=node('ul');for(const [key,a]of Object.entries(c.attacks))attacks.append(node('li',`${a.name}: ${a.attack} to hit · ${a.damage} ${a.damage_type}${a.available===false?' · unavailable with current equipment':''}${a.ammunition?' · ammunition handler pending':''}`));recorded.append(attacks);if(!c.validation.ready)recorded.append(node('p','Automatic play remains locked until the remaining character and equipment rules are complete.'))}}
    catch(error){primaryStats(null,'Build preview · recorded equipment is temporarily unavailable.');recorded.append(node('p',error.message))}
    content.append(button('Refresh inventory',load));await controls(v,data.can_edit,data.can_control)
    if(inventoryWrites.pending)showRetry()
    if(data.revision!==sheet.revision)content.append(node('p','This inventory was updated elsewhere. Save or discard your unsaved entries, then reload the saved sheet before making changes.',{class:'sheet-inventory-warning'}))
   }catch(e){content.replaceChildren(node('p',e.status===404?'Inventory is no longer available to this account.':e.message));if(inventoryWrites.pending)showRetry();else content.append(button('Retry inventory',load))}
  }
  await load()
 }
 let notesView=null;
 function render(){
  manualView?.destroy();notesView?.destroy();body.replaceChildren();panel.hidden=false
  if(!sheet){ownership(body,true);return}
  body.append(node('p',sheet.can_share?'You own this private sheet.':sheet.can_edit?'You have access as the configured site administrator.':sheet.can_control?'You can view this sheet and use this NPC in play. Editing stays with the owner and site administrator.':'This sheet was shared with you for viewing only.'))
  if(advanced){const display=node('section');display.append(node('h3',sheet.name+' · '+(sheet.document.build?.status==='ready'?'Character sheet':'Build draft')));const totals=node('div'),basis=node('p','Build preview · checking recorded equipment.');summary(sheet.document.build?.calculated,totals);display.append(basis,totals);body.append(display);renderInventory(display,{target:totals,basis}).catch(e=>tell(e.message))}else{body.append(node('h3',sheet.name+' · Character sheet'),node('p','Enter your stats, abilities, equipment, spells and notes in your own format. Rolls and encounter counters are handled manually.'))}
  if(!advanced&&window.SeptentureManual){manualView=window.SeptentureManual.mount({container:body,sheet,draft:manualDraft,request,changed:()=>{manualDirty=true;tell('Character sheet changes waiting to save.')},isDirty:()=>manualDirty||notesDirty,save:()=>run(async()=>{const data=await request(`sheets/${id}/manual`,{revision:sheet.revision,manual_sheet:manualDraft});setSheet(data.sheet);manualDirty=false;render();tell('Character sheet saved. Your separate notes were kept.')})})}
  if(sheet.can_edit){const privateNotes=node('textarea',undefined,{rows:7,maxlength:16000});privateNotes.value=notes;privateNotes.addEventListener('input',()=>{notes=privateNotes.value;notesDirty=true});const notesHost=node('div');body.append(label(advanced?'Private notes':'Original notes',privateNotes),notesHost);privateNotes.hidden=true;let notesFormat=sheet.document.notes_format||'plain';notesView=window.SeptentureProse.mount(notesHost,{source:notes,format:notesFormat,label:'Private sheet notes',preview:async(content,format)=>(await request('formatting/preview',{content,format})).html,onChange:(source,format)=>{notes=source;notesFormat=format;notesDirty=true;privateNotes.value=source}});body.append(button('Save private notes',async()=>{const data=await request(`sheets/${id}/notes`,{revision:sheet.revision,notes,notes_format:notesFormat});setSheet(data.sheet);notesDirty=false;render();tell('Private notes saved.')}));if(advanced){renderBuilder(body);renderSources(body)}}else{body.append(node('h3',advanced?'Private notes':'Original notes'),node('p',sheet.document.notes||'No notes yet.',{class:'sheet-notes'}))}
  const sharing=node('section');body.append(sharing);if(sheet.can_share)renderSharing(sharing).catch(e=>tell(e.message));if(administrator)ownership(body)
  body.append(button('Reload saved sheet',async()=>{if(inventoryWrites.pending)throw new Error('Retry the unconfirmed inventory change before reloading the sheet.');if(notesDirty||buildDirty||inventoryDirty||manualDirty){const warning=node('div',undefined,{role:'alert'});warning.append(node('p','Reloading will discard your unsaved sheet sections, notes, builder and inventory entries.'),button('Discard edits and reload',reload),button('Keep editing',()=>warning.remove()));body.prepend(warning);return}await reload()}))
  if(catalogue){const attribution=node('details');attribution.append(node('summary','Rules sources and attribution'),node('p',catalogue.license.attribution),node('p',catalogue.license.modifications));body.append(attribution)}
 }
 async function reload(){try{const data=await request(`sheets/${id}`);setSheet(data.sheet,true);render();tell('Loaded the latest saved sheet.')}catch(e){if(e.status===404){panel.hidden=true;body.replaceChildren();sheet=null;draft=null;notes=''}throw e}}
 async function start(){
  try{const data=await request('bootstrap');csrf=data.csrf;players=data.players;administrator=data.is_administrator
   const known=data.sheets?.find(item=>item.character_id===id);if(known)setSheet(known,true);else if(!administrator)return
   if(advanced)catalogue=(await request('catalogue')).catalogue;render();tell(sheet?'Private sheet loaded.':'No private sheet has been assigned yet.')
  }catch(e){if(e.status!==401&&e.status!==404){panel.hidden=false;tell(e.message)}}
 }
 window.addEventListener('beforeunload',event=>{if(notesDirty||buildDirty||inventoryDirty||manualDirty||inventoryWrites.pending){event.preventDefault();event.returnValue=''}})
 start()
})()
