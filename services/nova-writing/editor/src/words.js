import {unified} from 'unified';
import remarkParse from 'remark-parse';
import {gfmTable} from 'micromark-extension-gfm-table';
import {gfmTableFromMarkdown} from 'mdast-util-gfm-table';
import {gfmStrikethrough} from 'micromark-extension-gfm-strikethrough';
import {gfmStrikethroughFromMarkdown} from 'mdast-util-gfm-strikethrough';
const parser=unified().use(remarkParse).use(function(){const d=this.data();(d.micromarkExtensions??=[]).push(gfmTable(),gfmStrikethrough({singleTilde:false}));(d.fromMarkdownExtensions??=[]).push(gfmTableFromMarkdown(),gfmStrikethroughFromMarkdown())});
export const countText=text=>(text.match(/[\p{L}\p{N}]+(?:[’'\-][\p{L}\p{N}]+)*/gu)||[]).length;
function prose(node){if(['text','inlineCode','code','html'].includes(node.type))return node.value||'';if(['image','definition','imageReference'].includes(node.type))return '';const text=(node.children||[]).map(prose).join('');return text+(['paragraph','heading','listItem','blockquote','tableCell','tableRow','break','thematicBreak'].includes(node.type)?' ':'')}
export const countMarkdown=source=>countText(prose(parser.parse(source)));
export function countStory(source,format){if(format==='markdown')return countMarkdown(source);const template=document.createElement('template');template.innerHTML=source;template.content.querySelectorAll('script,style').forEach(el=>el.remove());template.content.querySelectorAll('p,br,div,li,h1,h2,h3,h4,h5,h6,td,th,blockquote,pre').forEach(el=>el.append(' '));return countText([...template.content.childNodes].map(n=>n.textContent||'').join(''))}
// Check parsed URLs before a visual document can create anchors or image elements.
export function hasUnsafeURL(source,allowed){const walk=node=>typeof node.url==='string'&&!allowed(node.url,node.type==='image')||(node.children||[]).some(walk);return walk(parser.parse(source))}

export const parseMarkdown=source=>parser.parse(source);
