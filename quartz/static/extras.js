// ─────────────────────────────────────────────────────────────
//  Septenture — small machines in the walls
// ─────────────────────────────────────────────────────────────
;(function () {
  const LEDGER_KEY = "septenture-ledger"
  const SEEN_KEY = "septenture-seen"
  const LEDGER_BASE = 1337

  const STONE = [
    "The lower stair is wet again. Take the rail.",
    "Watch the windows above the Drop.",
    "Every tunnel has two ends. Ask who keeps the other.",
    "Buy the water first. Ask the questions after.",
    "The Watch has your old name, too.",
    "Someone left the lamp on for you.",
    "Pay the guide before the tide turns.",
    "The ladle hangs beside the empty pot.",
    "Leave the rope for the next person.",
    "The gatekeeper knows your cousin.",
    "Your room is paid through morning.",
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
