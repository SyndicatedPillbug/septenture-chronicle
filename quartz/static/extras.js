// ─────────────────────────────────────────────────────────────
//  Septenture — small machines in the walls
// ─────────────────────────────────────────────────────────────
;(function () {
  const LEDGER_KEY = "septenture-ledger"
  const SEEN_KEY = "septenture-seen"
  const LEDGER_BASE = 1337

  const STONE = [
    "The stone remembers. The stone does not care.",
    "Nobody looks up. That is not manners.",
    "Every tunnel has two ends. One of them is a lie.",
    "Buy the water first. Ask the questions after.",
    "The Watch writes names. The stone keeps them.",
    "A lamp is a promise that somebody is still awake.",
    "If the route came free, it was not a route.",
    "Down here, direction is social.",
    "The Gullets take everything. They give back a little.",
    "Rockside is a place you leave with permission.",
    "A quiet room is a debt with interest.",
  ]

  function bumpLedger() {
    const el = document.querySelector("[data-ledger]")
    if (!el) return
    let n = 0
    try {
      n = parseInt(localStorage.getItem(LEDGER_KEY) || "0", 10) || 0
      if (!sessionStorage.getItem(SEEN_KEY)) {
        n += 1
        sessionStorage.setItem(SEEN_KEY, "1")
        localStorage.setItem(LEDGER_KEY, String(n))
      }
    } catch (e) {
      n = 0
    }
    el.textContent = String(LEDGER_BASE + n).padStart(6, "0")
  }

  function drawStone() {
    const el = document.querySelector("[data-stone]")
    if (!el) return
    el.textContent = STONE[Math.floor(Math.random() * STONE.length)]
  }

  function toast(text) {
    const node = document.createElement("div")
    node.className = "stone-toast"
    node.textContent = text
    document.body.appendChild(node)
    requestAnimationFrame(() => node.classList.add("show"))
    setTimeout(() => {
      node.classList.remove("show")
      setTimeout(() => node.remove(), 800)
    }, 2600)
  }

  document.addEventListener("click", (event) => {
    const target = event.target
    if (!(target instanceof Element)) return

    if (target.closest("[data-lamp-toggle]")) {
      document.body.classList.toggle("lights-out")
    }

    if (target.closest("[data-stone-draw]")) {
      drawStone()
      const box = target.closest(".stone-box")
      if (box) {
        box.classList.remove("knocked")
        void box.offsetWidth
        box.classList.add("knocked")
      }
    }
  })

  // konami — the old door
  const SEQ = [
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
  document.addEventListener("keydown", (event) => {
    const key = event.key.length === 1 ? event.key.toLowerCase() : event.key.toLowerCase()
    if (key === SEQ[cursor]) {
      cursor += 1
      if (cursor === SEQ.length) {
        cursor = 0
        document.body.classList.add("konami")
        toast("The stone remembers you.")
        setTimeout(() => document.body.classList.remove("konami"), 3200)
      }
    } else {
      cursor = key === SEQ[0] ? 1 : 0
    }
  })

  if (window.console) {
    console.log(
      "%c ✦ S E P T E N T U R E ✦ ",
      "color:#e0a458;background:#12100e;font-size:14px;padding:6px 10px;border:1px solid #e0a45855",
    )
    console.log(
      "%cYou found the warren. Try the lamp. Try the old code.",
      "color:#c2703d;font-style:italic",
    )
  }

  function boot() {
    bumpLedger()
    drawStone()
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot)
  } else {
    boot()
  }
  document.addEventListener("nav", boot)
})()
