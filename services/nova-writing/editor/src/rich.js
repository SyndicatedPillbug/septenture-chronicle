import {countStory,hasUnsafeURL} from './words.js';
import {Editor,rootCtx,defaultValueCtx,editorViewCtx,editorViewOptionsCtx} from '@milkdown/kit/core';
import * as cm from '@milkdown/kit/preset/commonmark';
import * as g from '@milkdown/kit/preset/gfm';
import {history,undoCommand,redoCommand} from '@milkdown/kit/plugin/history';
import {listener,listenerCtx} from '@milkdown/kit/plugin/listener';
import {clipboard} from '@milkdown/kit/plugin/clipboard';
import {callCommand,getMarkdown,insert,$prose,$remark} from '@milkdown/kit/utils';
import {Plugin} from '@milkdown/kit/prose/state';
import {gfmTable} from 'micromark-extension-gfm-table';
import {gfmTableFromMarkdown,gfmTableToMarkdown} from 'mdast-util-gfm-table';
import {gfmStrikethrough} from 'micromark-extension-gfm-strikethrough';
import {gfmStrikethroughFromMarkdown,gfmStrikethroughToMarkdown} from 'mdast-util-gfm-strikethrough';
import {deleteRow,deleteColumn,deleteTable} from '@milkdown/kit/prose/tables';
const storyGfm=$remark('storyGfm',()=>function(){const d=this.data();(d.micromarkExtensions??=[]).push(gfmTable(),gfmStrikethrough({singleTilde:false}));(d.fromMarkdownExtensions??=[]).push(gfmTableFromMarkdown(),gfmStrikethroughFromMarkdown());(d.toMarkdownExtensions??=[]).push(gfmTableToMarkdown(),gfmStrikethroughToMarkdown())});
const supportedGfm=[g.tableSchema,g.tableHeaderRowSchema,g.tableRowSchema,g.tableHeaderSchema,g.tableCellSchema,g.strikethroughAttr,g.strikethroughSchema,g.strikethroughInputRule,g.insertTableInputRule,g.tablePasteRule,g.strikethroughKeymap,g.tableKeymap,...g.commands,g.keepTableAlignPlugin,g.autoInsertSpanPlugin,g.tableEditingPlugin,storyGfm].flat();
const safeURL=(s,image=false)=>{try{const u=new URL(s,location.origin);return ['http:','https:',...image?[]:['mailto:']].includes(u.protocol)}catch{return false}};
function sanitize(html){const t=document.createElement('template');t.innerHTML=html;const allowed=new Set('P BR HR H1 H2 H3 H4 H5 H6 STRONG B EM I DEL S UL OL LI BLOCKQUOTE PRE CODE A IMG TABLE THEAD TBODY TR TH TD'.split(' '));for(const el of [...t.content.querySelectorAll('*')].reverse()){if(['SCRIPT','STYLE','IFRAME','OBJECT','SVG','MATH'].includes(el.tagName)){el.remove();continue}if(!allowed.has(el.tagName)){el.replaceWith(...el.childNodes);continue}for(const a of [...el.attributes])if(!['href','src','alt','title','start','align'].includes(a.name))el.removeAttribute(a.name);for(const a of ['href','src'])if(el.hasAttribute(a)&&!safeURL(el.getAttribute(a),a==='src'))el.removeAttribute(a)}return t.innerHTML}
window.SeptentureRich={safeURL,countWords:countStory,async create(root,source,onChange,canEdit){
 if(hasUnsafeURL(source,safeURL))throw new Error('This Markdown contains an unsupported URL. Keep it in source mode or change the URL before rich editing.');
 let initializing=true;
 const safety=$prose(ctx=>new Plugin({view(){return {update(view,prev){if(!initializing){if(!prev.doc.eq(view.state.doc))onChange(editor.action(getMarkdown()));document.dispatchEvent(new Event('story-selection-change'))}}}},filterTransaction(tr){let ok=true;tr.doc.descendants(node=>{if(node.type.name==='image'&&!safeURL(node.attrs.src,true))ok=false;if(node.attrs.checked!=null)ok=false;for(const m of node.marks)if(m.type.name==='link'&&!safeURL(m.attrs.href))ok=false});return ok},props:{handleDrop:()=>true}}));
 const editor=await Editor.make().config(ctx=>{ctx.set(rootCtx,root);ctx.set(defaultValueCtx,source);ctx.update(editorViewOptionsCtx,old=>({...old,editable:canEdit,transformPastedHTML:sanitize,handleDOMEvents:{drop:()=>true,click:(_v,e)=>{if(e.target.closest('a')){e.preventDefault();return true}return false}}}));ctx.get(listenerCtx)}).use(cm.commonmark).use(supportedGfm).use(history).use(listener).use(clipboard).use(safety).create();
 initializing=false;
 const command=(c,p)=>{if(!canEdit())return;editor.action(callCommand(c.key,p));editor.action(ctx=>ctx.get(editorViewCtx).focus())};
 return {state:()=>editor.action(ctx=>{const s=ctx.get(editorViewCtx).state,marks=s.storedMarks||s.selection.$from.marks(),active=name=>marks.some(m=>m.type.name===name);return {bold:active('strong'),italic:active('emphasis'),strike:active('strike_through'),inline:active('inlineCode')||active('inline_code'),heading:s.selection.$from.parent.type.name==='heading'?s.selection.$from.parent.attrs.level:0}}),serialized:()=>editor.action(getMarkdown()),insert:text=>{if(canEdit())editor.action(insert(text,true))},insertPlain:text=>{if(canEdit())editor.action(ctx=>{const v=ctx.get(editorViewCtx),from=v.state.selection.from,before=v.state.doc.textBetween(Math.max(0,from-1),from);v.dispatch(v.state.tr.insertText((before&&!/\s/.test(before)?' ':'')+text));v.focus()})},editable:()=>editor.action(ctx=>ctx.get(editorViewCtx).setProps({editable:canEdit})),destroy:()=>editor.destroy(),focus:()=>editor.action(ctx=>ctx.get(editorViewCtx).focus()),command(name,value){
 const map={undo:undoCommand,redo:redoCommand,bold:cm.toggleStrongCommand,italic:cm.toggleEmphasisCommand,strike:g.toggleStrikethroughCommand,paragraph:cm.turnIntoTextCommand,heading:cm.wrapInHeadingCommand,bullet:cm.wrapInBulletListCommand,ordered:cm.wrapInOrderedListCommand,quote:cm.wrapInBlockquoteCommand,inline:cm.toggleInlineCodeCommand,code:cm.createCodeBlockCommand,rule:cm.insertHrCommand,table:g.insertTableCommand,row:g.addRowAfterCommand,column:g.addColAfterCommand,deleteCells:g.deleteSelectedCellsCommand};
 if(name==='link'||name==='image'){const url=prompt(name==='link'?'Link URL (http, https or mailto)':'Image URL (http or https)');if(url===null)return;if(!safeURL(url,name==='image')){alert('Use a safe web URL.');return}command(name==='link'?cm.toggleLinkCommand:cm.insertImageCommand,name==='link'?{href:url}:{src:url,alt:prompt('Image description')||''});return}
 if(['deleteRow','deleteColumn','deleteTable'].includes(name)){if(canEdit())editor.action(ctx=>{const v=ctx.get(editorViewCtx);({deleteRow,deleteColumn,deleteTable})[name](v.state,v.dispatch);v.focus()});return}if(map[name])command(map[name],value)
 }}
}};
