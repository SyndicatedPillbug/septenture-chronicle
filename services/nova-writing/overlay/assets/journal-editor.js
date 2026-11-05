(async()=>{
 const mount=document.querySelector('[data-journal-editor]');if(!mount)return
 const form=mount.closest('form'),source=form.querySelector('[name=content]'),status=document.createElement('p');status.setAttribute('role','status');mount.append(status)
 const fields=['title','content','tags'],snapshot=()=>Object.fromEntries(fields.map(key=>[key,form.elements[key].value])),initial=JSON.stringify(snapshot())
 const key=`septenture:journal:${mount.dataset.account}:${mount.dataset.journalId||'new'}`,format=mount.dataset.format
 let csrf='',editor,dirty=false,storage=true,submitting=false
 const persist=()=>{dirty=JSON.stringify(snapshot())!==initial;try{if(dirty)sessionStorage.setItem(key,JSON.stringify({revision:mount.dataset.revision,format,fields:snapshot()}));else sessionStorage.removeItem(key)}catch{storage=false}status.textContent=dirty?(storage?'Unsaved changes kept in this tab. Save your draft before leaving.':'Unsaved changes. Browser recovery is unavailable; save or copy your text.'):'Journal source is ready.'}
 for(const name of fields)form.elements[name].addEventListener('input',persist)
 function button(text,action){const b=document.createElement('button');b.type='button';b.textContent=text;b.addEventListener('click',action);return b}
 try{
  const response=await fetch('/index.php/play/bootstrap',{cache:'no-store'});if(!response.ok)throw Error('Sign in again in another tab to use formatting preview. Your text remains here.');csrf=(await response.json()).csrf
  let saved;try{saved=JSON.parse(sessionStorage.getItem(key)||'null')}catch{storage=false}
  if(saved&&saved.format===format&&saved.fields&&JSON.stringify(saved.fields)!==initial){const box=document.createElement('div');box.setAttribute('role','alert');const p=document.createElement('p');p.textContent='This tab has an unsaved journal copy. Review it before restoring.';const copy=document.createElement('textarea');copy.readOnly=true;copy.value=String(saved.fields.content||'');copy.setAttribute('aria-label','Recovered journal source');box.append(p,copy,button('Restore this copy',()=>{for(const name of fields)if(typeof saved.fields[name]==='string')form.elements[name].value=saved.fields[name];editor?.destroy();prose.replaceChildren();mountEditor();persist();box.remove()}),button('Keep server version',()=>{try{sessionStorage.removeItem(key)}catch{}box.remove()}));mount.append(box)}
  const prose=document.createElement('div');source.after(prose)
  function mountEditor(){if(!window.SeptentureProse)return;editor=window.SeptentureProse.mount(prose,{source:source.value,format,preview:async(content,format)=>{const r=await fetch('/index.php/play/formatting/preview',{method:'POST',cache:'no-store',headers:{'Content-Type':'application/json','X-Play-CSRF':csrf},body:JSON.stringify({content,format})});const result=await r.json();if(!r.ok)throw Error(result.error||'Preview could not load.');return result.html},onChange:value=>{source.value=value;const counter=form.querySelector('#counter');if(counter&&window.SeptentureRich?.countWords)counter.textContent=window.SeptentureRich.countWords(value,format);source.dispatchEvent(new Event('input',{bubbles:true}));persist()}});source.hidden=true}
  mountEditor();status.textContent=format==='legacy'?'This journal retains its original formatting. Source and preview preserve the existing text.':'Rich text, Markdown source and preview share the same saved journal source.'
 }catch(error){status.textContent=error.message}
 form.addEventListener('submit',()=>{submitting=true;persist()})
 window.addEventListener('beforeunload',event=>{if(dirty&&!submitting){event.preventDefault();event.returnValue=''}})
})()
