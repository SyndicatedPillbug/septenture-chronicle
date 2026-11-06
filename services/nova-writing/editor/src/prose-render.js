import {parseMarkdown} from './words.js';
// Construct a fixed DOM vocabulary; Markdown and raw HTML never become executable markup.
export function renderMarkdown(source,allowed){
 const tree=parseMarkdown(source),definitions=new Map();
 const scan=n=>{if(n.type==='definition')definitions.set(n.identifier.toLowerCase(),n);for(const c of n.children||[])scan(c)};scan(tree);
 const text=value=>document.createTextNode(value||'');
 function render(n,parent){
  if(n.type==='root'){const f=document.createDocumentFragment();for(const c of n.children||[])f.append(render(c,n.type));return f}
  if(n.type==='html'&&/^<br\s*\/?\s*>$/i.test(n.value.trim())){const br=document.createElement('br');if(['root','blockquote','listItem'].includes(parent)){const p=document.createElement('p');p.append(br);return p}return br}
  if(['text','html'].includes(n.type))return text(n.value);
  const tags={paragraph:'p',heading:'h'+Math.min(6,Math.max(1,n.depth||1)),strong:'strong',emphasis:'em',delete:'del',blockquote:'blockquote',list:n.ordered?'ol':'ul',listItem:'li',break:'br',thematicBreak:'hr',inlineCode:'code',code:'pre',link:'a',linkReference:'a',image:'img',imageReference:'img',table:'table',tableRow:'tr',tableCell:'td'};
  if(n.type==='definition')return document.createDocumentFragment();
  const tag=tags[n.type];if(!tag){const f=document.createDocumentFragment();for(const c of n.children||[])f.append(render(c,n.type));return f}
  const el=document.createElement(tag);
  if(n.type==='code'){const code=document.createElement('code');code.textContent=n.value||'';el.append(code);return el}
  if(n.type==='inlineCode'){el.textContent=n.value||'';return el}
  if(n.type==='list'&&n.ordered&&n.start>1)el.setAttribute('start',String(n.start));
  if(['link','linkReference','image','imageReference'].includes(n.type)){
   const ref=n.type.endsWith('Reference')?definitions.get(n.identifier.toLowerCase()):n;const image=tag==='img';
   if(ref?.url&&allowed(ref.url,image)){el.setAttribute(image?'src':'href',ref.url);if(!image)el.setAttribute('rel','noopener noreferrer');if(ref.title)el.title=ref.title}
   if(image){el.alt=n.alt||'';return el}
  }
  if(n.type==='table'){const head=document.createElement('thead'),body=document.createElement('tbody');for(const [i,row]of(n.children||[]).entries()){const tr=document.createElement('tr');for(const cell of row.children||[]){const td=document.createElement(i?'td':'th');for(const c of cell.children||[])td.append(render(c,n.type));tr.append(td)}(i?body:head).append(tr)}el.append(head,body);return el}
  for(const c of n.children||[])el.append(render(c,n.type));return el;
 }
 const root=document.createElement('div');root.append(render(tree));return root.innerHTML;
}
