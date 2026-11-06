import {readFileSync} from 'node:fs';import assert from 'node:assert/strict';import {parseHTML} from 'linkedom';import {countStory,hasUnsafeURL} from './src/words.js';globalThis.document=parseHTML('<html></html>').document;
const cases=JSON.parse(readFileSync('../tests/story_words.json'));for(const [text,format,expected] of cases)assert.equal(countStory(text,format),expected,`${format}: ${text}`);console.log(`${cases.length} client visible-prose word count checks passed`);

const allowed=(url,image)=>{try{return ['http:','https:',...image?[]:['mailto:']].includes(new URL(url,'https://example.org').protocol)}catch{return false}};for(const source of ['[link](file:///etc/passwd)','![image](ftp://example.org/a)','[link][id]\n\n[id]: javascript:alert(1)','[link](data:text/html,hello)'])assert.ok(hasUnsafeURL(source,allowed));for(const source of ['[link](https://example.org)','![image](/assets/map.png)'])assert.ok(!hasUnsafeURL(source,allowed));console.log('6 initial rich-document URL checks passed');

assert.equal(countStory('One\n\n<br />\n\nTwo','markdown'),2);
console.log('PASS empty rich paragraphs do not inflate the word count');
