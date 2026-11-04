// Shared Markdown authoring control. Persistence and permissions belong to its host.
window.SeptentureProse={mount(container,{source='',format='markdown',editable=true,initialMode='source',label='Section prose',preview,onChange=()=>{}}){
 const el=(tag,text)=>{const n=document.createElement(tag);if(text)n.textContent=text;return n}
 const toolbar=el('div'),text=el('textarea'),richRoot=el('div'),output=el('div'),status=el('p');status.setAttribute('role','status');text.value=source;text.rows=8;text.readOnly=!editable;text.setAttribute('aria-label',label+' source');richRoot.className='story-rich-surface';output.className='story-preview';richRoot.hidden=true;output.hidden=true;let rich=null,serial=0,destroyed=false
 const changed=value=>{if(destroyed)return;text.value=value;serial++;onChange(value,format)}
 text.addEventListener('input',()=>changed(text.value))
 const commandbar=el('div');commandbar.hidden=true
 for(const [name,label]of [['bold','Bold'],['italic','Italic'],['bullet','List'],['quote','Quote'],['link','Link'],['undo','Undo'],['redo','Redo']]){const b=el('button',label);b.type='button';b.addEventListener('click',()=>rich?.command(name));commandbar.append(b)}
 async function mode(next){const original=text.value,ticket=++serial;let candidate=null;status.textContent='';if(rich){await rich.destroy();rich=null}if(destroyed||ticket!==serial)return;text.hidden=false;richRoot.hidden=true;output.hidden=true;commandbar.hidden=true
  try{if(next==='rich'){
   if(format!=='markdown')throw Error('This section uses plain text. Keep editing its source to preserve the original formatting.')
   if(!window.SeptentureRich)throw Error('Rich editing is unavailable. Your source is still editable.')
   if(/<\/?[A-Za-z!]|\[[ xX]\]\s|\]\(\s*(?:javascript|data|vbscript):/i.test(original))throw Error('This source contains formatting that rich editing cannot preserve. Keep editing its source so nothing is dropped.')
   let enabled=false;candidate=await window.SeptentureRich.create(richRoot,original,changed,()=>enabled&&editable&&!destroyed)
   const proposed=candidate.serialized()
   if(proposed!==original){const [before,after]=await Promise.all([preview(original,format),preview(proposed,format)]);const canonical=html=>{const t=el('template');t.innerHTML=html;return t.innerHTML.replace(/>\s+</g,'><').trim()};if(canonical(before)!==canonical(after))throw Error('Rich editing would change unsupported formatting. Your exact source has been kept.')}
   if(destroyed||ticket!==serial||text.value!==original){await candidate.destroy();return}rich=candidate;candidate=null;enabled=true;rich.editable();const surface=richRoot.querySelector('[contenteditable]');if(surface){surface.setAttribute('role','textbox');surface.setAttribute('aria-multiline','true');surface.setAttribute('aria-label',label+' rich text')}text.hidden=true;richRoot.hidden=false;commandbar.hidden=!editable
  }else if(next==='preview'){
   const html=await preview(original,format);if(destroyed||ticket!==serial||text.value!==original)return;output.innerHTML=html;text.hidden=true;output.hidden=false
  }}catch(error){await candidate?.destroy();if(ticket===serial&&!destroyed){status.textContent=error.message;text.hidden=false}}
 }
 for(const [key,label]of [['source','Source'],['rich','Rich text'],['preview','Preview']]){const b=el('button',label);b.type='button';b.addEventListener('click',()=>mode(key));toolbar.append(b)}
 container.append(toolbar,commandbar,text,richRoot,output,status)
 if(initialMode!=='source')mode(initialMode)
 return {source:()=>text.value,async destroy(){destroyed=true;serial++;await rich?.destroy()}}
}}
