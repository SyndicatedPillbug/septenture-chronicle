const SeptentureSheetSections={
 'Identity':['Character name','Player','Class and level','Species / ancestry','Background','Alignment','Experience'],
 'Abilities, saves and skills':['Strength','Dexterity','Constitution','Intelligence','Wisdom','Charisma','Proficiency bonus','Strength save','Dexterity save','Constitution save','Intelligence save','Wisdom save','Charisma save','Acrobatics','Animal Handling','Arcana','Athletics','Deception','History','Insight','Intimidation','Investigation','Medicine','Nature','Perception','Performance','Persuasion','Religion','Sleight of Hand','Stealth','Survival','Passive Perception'],
 'Combat and attacks':['Armor class','Initiative','Speed','Maximum hit points','Current hit points','Temporary hit points','Hit dice','Death save successes','Death save failures','Attacks and damage','Resistances / immunities','Conditions'],
 'Traits, features and resources':['Traits and features','Limited-use resources','Proficiencies','Languages','Inspiration'],
 'Spells and spellcasting':['Spellcasting ability','Spell save DC','Spell attack bonus','Spell slots / uses','Cantrips','Known / prepared spells','Concentration'],
 'Equipment and possessions':['Carried equipment','Weapons and armor','Magic items / attunement','Currency','Other possessions'],
 'Appearance, relationships and history':['Appearance','Personality','Ideals','Bonds','Flaws','Allies and relationships','History'],
 'Additional notes':[]
}
window.SeptentureManual={empty:()=>({version:1,sections:[]}),mount({container,sheet,draft,request,changed,save,isDirty}){
 const el=(tag,text)=>{const n=document.createElement(tag);if(text!==undefined)n.textContent=text;return n},editors=[]
 const button=(text,action)=>{const b=el('button',text);b.type='button';b.addEventListener('click',action);return b}
 const input=(label,value,change,max=160)=>{const wrap=el('label',label),n=el('input');n.value=value;n.maxLength=max;n.addEventListener('input',()=>{change(n.value);changed()});wrap.append(n);return wrap}
 const uid=()=>crypto.randomUUID(),editable=sheet.can_edit
 let editing=false,customizing=false
 const makeSection=title=>({id:uid(),title,fields:(SeptentureSheetSections[title]||[]).map(label=>({id:uid(),label,value:label==='Character name'?sheet.name:''})),prose:{source:'',format:'markdown'}})
 const presets=Object.keys(SeptentureSheetSections)
 const root=el('section');root.className='manual-sheet';container.append(root)
 const note=el('p',`Saved revision ${sheet.revision} · ${new Date(sheet.updated_at*1000).toLocaleString()}`);root.append(note)
 const preview=async(source,format)=>format==='plain'?escapePlain(source):(await request('formatting/preview',{content:source,format})).html
 function escapePlain(source){const p=el('p',source);p.style.whiteSpace='pre-wrap';return p.outerHTML}
 function dispose(){for(const editor of editors.splice(0))editor.destroy()}
 function render(){dispose();root.replaceChildren(el('h3',sheet.name),note)
  if(!draft.sections.length){root.append(el('p','No character details have been entered yet.'))
   if(editable){root.append(button('Start a blank character sheet',()=>{draft.sections.push(...presets.map(makeSection));editing=true;changed();render()}))
    const label=el('label','Import saved character sheet'),file=el('input'),feedback=el('p');file.type='file';file.accept='.json,application/json';label.append(file);root.append(label,feedback)
    file.addEventListener('change',async()=>{try{const selected=file.files?.[0];if(!selected)return;if(selected.size>240000)throw Error('Use a sheet export smaller than 240 KB.');const data=JSON.parse(await selected.text());if(data.character_id!==sheet.character_id)throw Error('This file belongs to a different character.');const doc=data.manual_sheet;
     if(doc?.version!==1||!Array.isArray(doc.sections)||!doc.sections.length||doc.sections.length>40)throw Error('Invalid character sheet export.');const ids=new Set();const text=(v,max)=>typeof v==='string'&&new TextEncoder().encode(v).length<=max;const id=v=>typeof v==='string'&&/^[a-zA-Z0-9_-]{1,64}$/.test(v)&&!ids.has(v)&&(ids.add(v),true);
     for(const section of doc.sections){if(!id(section.id)||!text(section.title,160)||!Array.isArray(section.fields)||section.fields.length>100||!text(section.prose?.source,32000)||!['plain','markdown'].includes(section.prose?.format))throw Error('Invalid sheet section.');for(const field of section.fields)if(!id(field.id)||!text(field.label,160)||!text(field.value,2000))throw Error('Invalid sheet field.');}
     if(new TextEncoder().encode(JSON.stringify(doc)).length>200000)throw Error('Sheet exceeds the saved document limit.');
     if(draft.sections.length)throw Error('This sheet already has entries. Switch characters and retry.');draft.sections.push(...structuredClone(doc.sections));editing=false;changed();render();root.prepend(el('p','Imported into your draft. Choose Save character sheet to keep it.'));
    }catch(e){feedback.textContent=e.message;feedback.setAttribute('role','alert');file.value=''}})
   }
  }else if(editable){root.append(button(editing?'Read character sheet':'Edit character sheet',()=>{editing=!editing;customizing=false;render()}));if(editing)root.append(button(customizing?'Done customizing':'Customize sections and fields',()=>{customizing=!customizing;render()}))}
  if(editing)root.append(el('p','Enter values from your character sheet. Bonuses, totals and spell choices are recorded manually.'))
  for(const section of draft.sections){const archived=!editing&&section.title.startsWith('Source record · '),box=el(archived?'details':'section');box.className='manual-section';root.append(box)
   if(editing&&customizing){box.append(input('Section title',section.title,value=>section.title=value));const actions=el('div');const at=draft.sections.indexOf(section)
    actions.append(button('Move section up',()=>{if(at>0){[draft.sections[at-1],draft.sections[at]]=[draft.sections[at],draft.sections[at-1]];changed();render()}}),button('Move section down',()=>{if(at<draft.sections.length-1){[draft.sections[at+1],draft.sections[at]]=[draft.sections[at],draft.sections[at+1]];changed();render()}}),button('Remove section',()=>{if(confirm('Remove this section from your draft? Its saved revision remains in sheet history.')){draft.sections.splice(at,1);changed();render()}}));box.append(actions)
   }else box.append(el(archived?'summary':'h4',section.title))
   const fields=el('div');fields.className='manual-fields';box.append(fields)
   for(const field of section.fields){const row=el('div');fields.append(row);if(editing&&customizing){row.append(input('Field label',field.label,value=>field.label=value),input('Value',field.value,value=>field.value=value,2000),button('Move field up',()=>{const at=section.fields.indexOf(field);if(at>0){[section.fields[at-1],section.fields[at]]=[section.fields[at],section.fields[at-1]];changed();render()}}),button('Move field down',()=>{const at=section.fields.indexOf(field);if(at<section.fields.length-1){[section.fields[at+1],section.fields[at]]=[section.fields[at],section.fields[at+1]];changed();render()}}),button('Remove field',()=>{if(confirm('Remove this field from your draft?')){section.fields.splice(section.fields.indexOf(field),1);changed();render()}}))}else if(editing)row.append(input(field.label,field.value,value=>field.value=value,2000));else {row.className='manual-value';row.append(el('strong',field.label),el('p',field.value||'—'))}}
   if(editing&&customizing)box.append(button('Add field',()=>{section.fields.push({id:uid(),label:'',value:''});changed();render()}))
   const prose=el('div');box.append(prose)
   if(editing)editors.push(window.SeptentureProse.mount(prose,{...section.prose,preview,onChange:(source,format)=>{section.prose={source,format};changed()}}))
   else if(section.prose.source)preview(section.prose.source,section.prose.format).then(html=>{if(prose.isConnected)prose.innerHTML=html}).catch(()=>{prose.textContent=section.prose.source})
  }
  if(editable&&editing){if(sheet.document.notes)root.append(button('Copy original notes into a section',()=>{if(confirm('Copy the original notes into a new plain-text section? The original notes remain unchanged.')){draft.sections.push({id:uid(),title:'Original notes',fields:[],prose:{source:sheet.document.notes,format:'plain'}});changed();render()}}));const select=el('select');select.setAttribute('aria-label','New section');for(const title of [...presets,'Custom section']){const option=el('option',title);select.append(option)}if(customizing)root.append(select,button('Add section',()=>{if(draft.sections.length>=40){alert('Use at most 40 sections.');return}draft.sections.push(makeSection(select.value));changed();render()}));}
  if(editable&&draft.sections.length)root.append(button('Save character sheet',save))
  const exports=el('section'),include=el('input');include.type='checkbox';const label=el('label','Include private notes in this export');label.prepend(include);exports.append(label,button('Print / save as PDF',()=>{
   const go=()=>{const url=new URL(`/session/tools/sheets/${sheet.character_id}/print`,location.origin);if(include.checked)url.searchParams.set('notes','1');window.open(url,'_blank','noopener,noreferrer')}
   if(isDirty()){const warning=el('div');warning.setAttribute('role','alert');warning.append(el('p','This export uses the saved revision. Your current edits are not included.'),button('Export saved version',go),button('Keep editing',()=>warning.remove()));exports.append(warning)}else go()
  }));root.append(exports)
 }
 render();return {destroy:dispose}
}}
