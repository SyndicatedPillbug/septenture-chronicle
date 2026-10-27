;(() => {
  if (window.__septentureCompanions) return
  window.__septentureCompanions = true
  const scriptURL = document.currentScript?.src || new URL("static/companions.js", location.origin + (document.body.dataset.basepath || "") + "/").href
  const roster = ['page', 'ceridwen', 'coy', 'legion', 'simon', 'selim', 'the-gunslinger']
  let cleanup = () => {}

  function boot() {
    cleanup()
    const root = document.querySelector('[data-pc]')
    if (!root) return
    if(root.hasAttribute('data-profile-template')){
      const slug=new URLSearchParams(location.search).get('character')||''
      if(!/^[a-z][a-z0-9-]{0,40}$/.test(slug)){root.querySelector('.pc-identity h1').textContent='Choose a character from The Cast';return}
      root.dataset.pc=slug
    }
    const controller = new AbortController()
    const on = (el, type, handler) => el?.addEventListener(type, handler, { signal: controller.signal })
    const audio = root.querySelector('[data-pc-audio]')
    const play = root.querySelector('[data-play]')
    const status = root.querySelector('[data-audio-status]')
    const title = root.querySelector('[data-track-title]')
    const credit = root.querySelector('[data-track-credit]')
    const original = { src: audio.src, title: title.textContent, credit: credit.textContent }
    let objectUrl
    let alive = true
    audio.volume = 0.45
    function playing(isPlaying) {
      root.querySelector('.pc-music').classList.toggle('is-playing', isPlaying)
      play.textContent = isPlaying ? 'Ⅱ PAUSE' : '▶ PLAY'
      play.setAttribute('aria-pressed', String(isPlaying))
      play.setAttribute('aria-label', `${isPlaying ? 'Pause' : 'Play'} ${title.textContent}`)
    }
    on(audio, 'play', () => { playing(true); status.textContent = 'Playing · loop on' })
    on(audio, 'pause', () => { playing(false); status.textContent = 'Paused. The room is yours.' })
    on(audio, 'error', () => { playing(false); status.textContent = 'This track could not load. Try another audio file.' })
    on(play, 'click', async () => {
      if (!audio.paused) { audio.pause(); return }
      try { await audio.play() } catch { if (alive) status.textContent = 'Playback could not start. Try another audio file.' }
    })
    on(root.querySelector('[data-stop]'), 'click', () => { audio.pause(); audio.currentTime = 0; status.textContent = 'Stopped. Press play to begin again.' })
    on(root.querySelector('[data-volume]'), 'input', event => { audio.volume = Number(event.target.value) })
    on(root.querySelector('[data-audio-file]'), 'change', event => {
      const file = event.target.files?.[0]
      if (!file) return
      audio.pause()
      if (objectUrl) URL.revokeObjectURL(objectUrl)
      objectUrl = URL.createObjectURL(file)
      audio.src = objectUrl
      title.textContent = file.name
      credit.textContent = 'Your soundtrack · this visit only'
      playing(false)
      status.textContent = 'Your track is ready. Press play.'
    })
    on(root.querySelector('[data-reset-track]'), 'click', () => {
      audio.pause()
      if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = undefined }
      audio.src = original.src
      title.textContent = original.title
      credit.textContent = original.credit
      root.querySelector('[data-audio-file]').value = ''
      playing(false)
      status.textContent = 'Original loop restored. Press play.'
    })
    on(root.querySelector('[data-random-companion]'), 'click', async () => {
      const url=new URL('character-identity.js',scriptURL);url.search=new URL(scriptURL).search
      const shared=await import(url.href)
      let slugs=roster
      try{slugs=(await shared.loadCharacterProfiles()).map(p=>p.slug)}catch{/* Static companions remain available offline. */}
      const options=slugs.filter(id=>id!==root.dataset.pc)
      if(options.length&&!controller.signal.aborted)location.href=shared.profileHref(options[Math.floor(Math.random()*options.length)])
    })
    const key = `septenture-guestbook-v1:${root.dataset.pc}`
    const form = root.querySelector('[data-guest-form]')
    const messages = root.querySelector('[data-guest-entries]')
    const guestStatus = root.querySelector('[data-guest-status]')
    let notes = []
    try {
      const stored = JSON.parse(localStorage.getItem(key) || '[]')
      if (Array.isArray(stored)) notes = stored.filter(n => n && typeof n.name === 'string' && typeof n.message === 'string').slice(0, 30).map(n => ({name:n.name.slice(0,60),message:n.message.slice(0,600)}))
    } catch { /* In-memory notes still work when storage is unavailable. */ }
    function persist() {
      try { localStorage.setItem(key, JSON.stringify(notes)); return true } catch { return false }
    }
    function render() {
      messages.replaceChildren()
      for (const [index, note] of notes.entries()) {
        const entry = document.createElement('div')
        entry.className = 'pc-guest-note'
        const name = document.createElement('strong')
        name.textContent = note.name
        const text = document.createElement('p')
        text.textContent = note.message
        const remove = document.createElement('button')
        remove.type = 'button'
        remove.textContent = 'Remove from this copy'
        remove.setAttribute('aria-label', `Remove note from ${note.name}`)
        on(remove, 'click', () => { notes.splice(index, 1); const saved = persist(); render(); guestStatus.textContent = saved ? 'Note removed from this copy.' : 'Removed for this visit; browser storage is unavailable.' })
        entry.append(name, text, remove)
        messages.append(entry)
      }
    }
    render()
    on(form, 'submit', event => {
      event.preventDefault()
      const data = new FormData(form)
      const name = String(data.get('guest') || '').trim().slice(0, 60)
      const message = String(data.get('message') || '').trim().slice(0, 600)
      if (!name || !message) { guestStatus.textContent = 'Add your name and a message first.'; return }
      notes.unshift({name, message})
      notes = notes.slice(0, 30)
      const saved = persist()
      render()
      form.reset()
      guestStatus.textContent = saved ? 'Signed! Your note is saved in this browser’s copy.' : 'Signed for this visit. This browser could not save the note.'
    })
    const communityURL = new URL("companions-community.js", scriptURL)
    communityURL.search = new URL(scriptURL).search
    import(communityURL.href).then(module => { if (!controller.signal.aborted) return module.mount(root, controller.signal) }).catch(() => {})
    cleanup = () => { alive = false; controller.abort(); audio.pause(); if (objectUrl) URL.revokeObjectURL(objectUrl); cleanup = () => {} }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, {once:true})
  else boot()
  document.addEventListener('nav', boot)
  window.addEventListener('pagehide', () => cleanup())
  window.addEventListener('pageshow', event => { if (event.persisted) boot() })
})()
