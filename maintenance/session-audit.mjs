import { readFile, writeFile } from 'node:fs/promises'
import { resolve, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'

export async function audit(root, catalog, session) {
  const errors = []
  const read = async path => { try { return await readFile(resolve(root, 'content', path), 'utf8') } catch { errors.push(`Missing content: ${path}`); return '' } }
  const home = await read('index.md'), contents = await read('the-chronicle/index.md')
  const record = await read(`${session.record}.md`), previous = await read(`${session.previous}.md`)
  const slug = session.record.split('/').at(-1), prev = session.previous.split('/').at(-1)
  if (!home.includes(`class="hero-latest" href="${session.record}"`)) errors.push('Homepage latest-session link is stale')
  if (!home.includes(`class="chapter-card" href="${session.record}"`)) errors.push('Homepage is missing the current session card')
  const part = contents.split(`## ${session.partHeading}\n`)[1]?.split('\n## ')[0] || ''
  const chapters = [...part.matchAll(/^- \*\*\[.*?\]\(([^)]+)\)/gm)].map(m => m[1])
  if (chapters.at(-1) !== slug) errors.push('Current session is not last in its Chronicle part')
  if (chapters.at(-2) !== prev) errors.push('Previous chapter disagrees with contents reading order')
  if (!home.includes(`${chapters.length} chapters`)) errors.push('Homepage chapter count disagrees with Chronicle contents')
  if (!previous.includes(`class="nav-next" href="${slug}"`)) errors.push('Previous chapter does not link forward to current session')
  if (!record.includes(`class="nav-prev" href="${prev}"`)) errors.push('Current record does not link back to previous chapter')
  if (!record.includes('class="nav-index"')) errors.push('Current record has no contents navigation')
  if (!record.includes(session.title)) errors.push('Current record title disagrees with audit record')
  for (const source of session.sources || []) if (source.kind !== 'played-record' || source.reviewed !== true) errors.push('Only reviewed played records may establish session facts')
  if (!session.sources?.length) errors.push('No reviewed session source recorded')
  const refs = await read('the-cast/source-notes.md')
  for (const character of session.affectedCharacters || []) {
    const profile = await read(`the-cast/${character}/index.md`)
    const referenceSection = refs.split(/(?=^## |^<h2 )/m).find(section => {
      const heading = section.split('\n')[0]
      return heading.includes(`id="${character}"`) || heading.replace(/^## /, '').toLowerCase().replaceAll(' ', '-') === character
    }) || ''
    if (!profile.includes(slug) && !referenceSection.includes(slug)) errors.push(`No session reference for ${character}`)
  }
  for (const surface of catalog.surfaces) {
    const review = session.reviews?.[surface.id]
    if (!review || !['updated','reviewed','no-change','not-applicable'].includes(review.status) || !review.evidence?.trim()) errors.push(`Missing review decision/evidence: ${surface.name}`)
  }
  const pending = Object.entries(session.browserChecks || {}).filter(([, status]) => status !== 'verified').map(([name]) => name)
  if (catalog.currentSession !== session.session) errors.push('Catalog current session disagrees with audit record')
  return {session:session.session, errors, pendingBrowserChecks:pending}
}
const escape = s => String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]))
if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  const root = resolve(dirname(fileURLToPath(import.meta.url)), '..')
  const catalog = JSON.parse(await readFile(resolve(root,'maintenance/surfaces.json')))
  const session = JSON.parse(await readFile(resolve(root,`maintenance/sessions/${catalog.currentSession}.json`)))
  const result = await audit(root,catalog,session)
  console.log(JSON.stringify(result,null,2))
  const reportIndex = process.argv.indexOf('--report')
  if (reportIndex >= 0) {
    if (!process.argv[reportIndex+1]) throw Error('--report requires an output path')
    const rows = catalog.surfaces.map(s => `<tr><th>${escape(s.name)}<small>${escape(s.location)}</small></th><td>${escape(s.review)}</td><td><b>${escape(session.reviews[s.id]?.status || 'pending')}</b><p>${escape(session.reviews[s.id]?.evidence || '')}</p></td></tr>`).join('')
    await writeFile(process.argv[reportIndex+1],`<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Septenture session audit</title><style>body{font:17px/1.55 system-ui;max-width:1200px;margin:3rem auto;padding:0 1rem;background:#f7f3e9;color:#25222c}table{border-collapse:collapse}th,td{text-align:left;vertical-align:top;padding:1rem;border-bottom:1px solid #bdb4a3}small{display:block;font-weight:400;overflow-wrap:anywhere}th{width:24%}td{width:38%}@media(max-width:700px){tr,th,td{display:block;width:auto!important}}</style><h1>Post-session audit · ${session.session}</h1><p>${escape(session.title)} · played ${escape(session.playedDate)}</p><p>Automated checks: ${result.errors.length ? escape(result.errors.join('; ')) : 'passed'}. Human browser checks still pending: ${escape(result.pendingBrowserChecks.join(', ') || 'none')}.</p><p>This maintainer report separates recorded human observations from automated consistency checks. It does not verify private backend state or mail delivery.</p><table><thead><tr><th>Surface and source</th><th>Review each session</th><th>This session</th></tr></thead><tbody>${rows}</tbody></table></html>`)
  }
  process.exitCode = result.errors.length ? 1 : 0
}
