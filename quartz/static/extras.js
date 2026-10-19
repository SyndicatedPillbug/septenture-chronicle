;(() => {
  if (window.__septentureExtras) return
  window.__septentureExtras = true

  const sayings = [
    "Buy the water first. Ask the questions after.",
    "The lower stair is wet again. Take the rail.",
    "Every tunnel has two ends. Ask who keeps the other.",
    "The Watch has your old name, too.",
    "Someone left the lamp on for you.",
    "Pay the guide before the tide turns.",
    "The ladle hangs beside the empty pot.",
    "Leave the rope for the next person.",
    "The gatekeeper knows your cousin.",
    "Your room is paid through morning.",
    "Watch the windows above the Drop.",
  ]
  let lastSaying = -1
  let flareTimer
  const oldCode = [
    "arrowup",
    "arrowup",
    "arrowdown",
    "arrowdown",
    "arrowleft",
    "arrowright",
    "arrowleft",
    "arrowright",
    "b",
    "a",
  ]
  let cursor = 0

  function drawStone() {
    const stone = document.querySelector("[data-stone]")
    if (!stone) return
    let index = Math.floor(Math.random() * (sayings.length - 1))
    if (index >= lastSaying && lastSaying >= 0) index++
    lastSaying = index
    stone.textContent = sayings[index]
  }

  function navigation() {
    document.querySelector(".site-nav")?.remove()
    const base = document.body.dataset.basepath || ""
    const root = `${base}/`
    const nav = document.createElement("nav")
    nav.className = "site-nav"
    nav.setAttribute("aria-label", "Main navigation")
    const brand = document.createElement("a")
    brand.className = "site-brand"
    brand.href = root
    brand.textContent = "✶ SEPTENTURE"
    const links = document.createElement("div")
    links.className = "site-links"
    for (const [label, path] of [
      ["THE CHRONICLE", "the-chronicle/"],
      ["LATEST CHAPTER", "the-chronicle/25-the-courtyard-and-the-wish"],
    ]) {
      const link = document.createElement("a")
      link.href = root + path
      link.textContent = label
      if (location.pathname.replace(/\/$/, "") === link.pathname.replace(/\/$/, ""))
        link.setAttribute("aria-current", "page")
      links.append(link)
    }
    nav.append(brand, links)
    document.body.prepend(nav)
  }

  function ledger() {
    const el = document.querySelector("[data-ledger]")
    if (!el) return
    let count = 1
    try {
      const stored = Number.parseInt(localStorage.getItem("septenture-reading-visits") || "0", 10)
      count = Number.isFinite(stored) && stored >= 0 ? stored : 0
      if (!sessionStorage.getItem("septenture-reading-seen")) {
        count++
        localStorage.setItem("septenture-reading-visits", String(count))
        sessionStorage.setItem("septenture-reading-seen", "yes")
      }
    } catch {
      /* The room works with storage disabled. */
    }
    el.textContent = String(Math.max(1, count)).padStart(6, "0")
  }

  // A real tally, shared by every visitor. Abacus is a free counter service:
  // /hit creates-or-increments, /get only reads. One hit per browser session.
  let globalRequested = false
  function globalLedger() {
    const el = document.querySelector("[data-ledger]")
    if (!el || typeof fetch !== "function" || globalRequested) return
    globalRequested = true
    let seen = false
    try {
      seen = Boolean(sessionStorage.getItem("septenture-global-seen"))
      if (!seen) sessionStorage.setItem("septenture-global-seen", "1")
    } catch {
      seen = false
    }
    const url =
      "https://abacus.jasoncameron.dev" +
      (seen ? "/get/septenture/chronicle" : "/hit/septenture/chronicle")
    fetch(url, { cache: "no-store" })
      .then((res) => (res.ok ? res.json() : null))
      .then((data) => {
        if (!data || !Number.isFinite(data.value)) throw new Error("no value")
        el.textContent = String(data.value).padStart(6, "0")
        const plate = el.closest(".lcd")
        if (plate) plate.title = "Souls entered — all visitors"
      })
      .catch(() => {
        // Offline or the service is down: the local count stands, and the next
        // load is allowed to try the shared tally again.
        try {
          sessionStorage.removeItem("septenture-global-seen")
        } catch {
          /* nothing to clear */
        }
        globalRequested = false
      })
  }

  function setLamp(dim) {
    document.body.classList.toggle("lights-out", dim)
    const lamp = document.querySelector("[data-lamp-toggle]")
    if (lamp) {
      lamp.setAttribute("aria-pressed", String(dim))
      lamp.setAttribute(
        "aria-label",
        dim ? "Restore the reading room lights" : "Dim the reading room",
      )
    }
  }

  document.addEventListener("click", (event) => {
    if (!(event.target instanceof Element)) return
    if (event.target.closest("[data-lamp-toggle]"))
      setLamp(!document.body.classList.contains("lights-out"))
    if (event.target.closest("[data-stone-draw]")) drawStone()
    const pause = event.target.closest("[data-ticker-toggle]")
    if (pause) {
      const paused = pause.closest(".ticker").classList.toggle("paused")
      pause.setAttribute("aria-pressed", String(paused))
      pause.setAttribute("aria-label", paused ? "Resume city wire" : "Pause city wire")
      pause.textContent = paused ? "▶" : "Ⅱ"
    }
  })

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") setLamp(false)
    if (
      event.target instanceof Element &&
      event.target.closest("input, textarea, select, [contenteditable]")
    )
      return
    const key = event.key.toLowerCase()
    cursor = key === oldCode[cursor] ? cursor + 1 : key === oldCode[0] ? 1 : 0
    if (cursor !== oldCode.length) return
    cursor = 0
    document.body.classList.add("konami")
    document.querySelector(".stone-toast")?.remove()
    const toast = document.createElement("div")
    toast.className = "stone-toast show"
    toast.setAttribute("role", "status")
    toast.textContent = "The stone remembers you. Follow the small star."
    document.body.append(toast)
    clearTimeout(flareTimer)
    flareTimer = setTimeout(() => {
      document.body.classList.remove("konami")
      toast.remove()
    }, 4000)
  })

  function boot() {
    navigation()
    ledger()
    globalLedger()
    setLamp(false)
    drawStone()
  }
  if (document.readyState === "loading")
    document.addEventListener("DOMContentLoaded", boot, { once: true })
  else boot()
  document.addEventListener("nav", boot)
})()
