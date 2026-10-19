# SEPTENTURE — SITE PROSE, SINGLE SOURCE

Every piece of prose that appears on the Septenture chronicle site, tagged by where it lives.
Edit only the text inside the SEGMENT blocks. Keep the SEGMENT ids exactly as they are so the
edited file can be applied back to the site mechanically. Leave the HTML-ish lines alone unless
their segment note says otherwise.

## House style (apply while editing)

- Mirror antithesis is banned as a reflex in all six disguises: "not X but Y"; the sentence-pair
  version ("That does not excuse the errors. It means they are workable."); the reversed form
  ("Y, not X"); the anaphoric mirror ("The question is never X. The question is Y."); preposition
  skins ("rather than", "instead of", "no X, only Y"); double-negative parallels. State the
  positive claim and let it stand.
- Limit passive "to be" constructions. Prefer a person doing the verb.
- No superficial "-ing" tails, no copula avoidance ("serves as", "stands as"), no significance
  inflation ("of utmost importance"), no vague attribution, no synonym-cycling, no noun pileups.
- Watch the AI tells: rule-of-three runs (one per chapter, third item must turn), anaphora runs
  ("Somebody X. Somebody Y. Somebody Z."), kicker density (two polished aphorisms per piece at
  most), metaphor stacking, stock personifications ("the sea breathed and did not care"), "the way
  X is X" similes, uniform sentence rhythm, missing specifics (give numbers, prices, times),
  em-dash overuse, and tidiness (leave a thread loose).
- Voice reference: long cumulative sentences mixed with blunt four-word ones; concrete nouns;
  cold stone, warm lamplight; local speech from where the speaker stands. Second person for the
  dream/interlude prose, third person past for the Chronicle chapters.

## Segment map

- `L1` — content/index.md — ticker items, one per line; in-world news, present tense
- `L2` — content/index.md — hero title and tagline
- `L3` — content/index.md — landing intro paragraph
- `L4` — content/index.md — the stone oracle: box label, default saying, button text
- `L5` — content/index.md — chronicle part blurbs
- `L6` — content/index.md — reading order line (link text only)
- `L7` — content/index.md — around-the-fire list (dead links, copy only)
- `L8` — content/index.md — footer: counter label, badges, tooltips
- `L9` — content/index.md — meta description (search/social)
- `C1` — content/the-chronicle/index.md — chronicle intro line
- `C2` — content/the-chronicle/index.md — construction bar text
- `C3` — content/the-chronicle/index.md — part blurbs
- `C4` — content/the-chronicle/index.md — chapter blurbs
- `C5` — content/the-chronicle/index.md — meta description
- `CH25-dateline` — content/the-chronicle/25-the-courtyard-and-the-wish.md — chapter 25: dateline
- `CH25-title` — content/the-chronicle/25-the-courtyard-and-the-wish.md — chapter 25: title
- `CH25-body` — content/the-chronicle/25-the-courtyard-and-the-wish.md — chapter 25: body prose
- `CH25-meta` — content/the-chronicle/25-the-courtyard-and-the-wish.md — chapter 25: meta description
- `WH-dateline` — content/the-chronicle/the-wrong-hunter.md — the wrong hunter: dateline
- `WH-title` — content/the-chronicle/the-wrong-hunter.md — the wrong hunter: title
- `WH-body` — content/the-chronicle/the-wrong-hunter.md — the wrong hunter: body prose
- `WH-meta` — content/the-chronicle/the-wrong-hunter.md — the wrong hunter: meta description
- `INT-dateline` — content/the-chronicle/interlude-the-stone.md — interlude the stone: dateline
- `INT-title` — content/the-chronicle/interlude-the-stone.md — interlude the stone: title
- `INT-body` — content/the-chronicle/interlude-the-stone.md — interlude the stone: body prose
- `INT-meta` — content/the-chronicle/interlude-the-stone.md — interlude the stone: meta description
- `WAR-dateline` — content/the-warren.md — the warren (hidden page): dateline
- `WAR-title` — content/the-warren.md — the warren (hidden page): title
- `WAR-body` — content/the-warren.md — the warren (hidden page): body prose
- `WAR-meta` — content/the-warren.md — the warren (hidden page): meta description
- `ORACLE` — quartz/static/extras.js — the stone's sayings (11), used by the oracle box

---

<!-- ==== SEGMENT: L1 | source: content/index.md | ticker items, one per line; in-world news, present tense ==== -->
✦ CITY WATCH: curfew at sundown, carry your papers 
·  ✦ a body recovered at the Flint Seam; the Watch calls it Sickle work 
·  ✦ the Ox-Elevators are checking names 
·  ✦ water share down a quarter at the Marl 
·  ✦ the Hollow Heart has stew and no questions 
·  ✦ one ship riding at the Salt End under a Bohun pennant 
·  ✦ lost: one boot, last seen on the Salt End stairs, reward paid in stew 
·
<!-- ==== END: L1 ==== -->

<!-- ==== SEGMENT: L2 | source: content/index.md | hero title and tagline ==== -->
SEPTENTURE
The Latesa Chronicle
<!-- ==== END: L2 ==== -->

<!-- ==== SEGMENT: L3 | source: content/index.md | landing intro paragraph ==== -->
<div class="hero">
  <img src="static/rockside-lift.png" alt="Rockside, seen from a lift" />
  <div class="hero-inner">
    <div class="lamp-wrap" data-lamp-toggle title="the lamp is a door" aria-hidden="true">
      <div class="lamp-cord"></div>
      <div class="lamp-shade"></div>
      <div class="lamp-bulb"></div>
      <div class="lamp-glow"></div>
    </div>
    <h1 class="hero-title">SEPTENTURE</h1>
    <p class="hero-tagline">The Latesa Chronicle</p>
  </div>
</div>

This is the record of a table's campaign: five people, a city named Latesa, and the mess they made of a year in it. Chapters follow the sessions in order. The fiction sits where it happened.
<!-- ==== END: L3 ==== -->

<!-- ==== SEGMENT: L4 | source: content/index.md | the stone oracle: box label, default saying, button text ==== -->
The stone says
The stone remembers. The stone does not care.
knock again
<!-- ==== END: L4 ==== -->

<!-- ==== SEGMENT: L5 | source: content/index.md | chronicle part blurbs ==== -->
Part I — Campaign One: The Feylight Festival — the festival, the ruins, the ring, and the box.
Part II — The Sea Road — the flashback and the voyage to Latesa.
Part III — Latesa — the closing arc: the courtyard, the wish, and what came after.
<!-- ==== END: L5 ==== -->

<!-- ==== SEGMENT: L6 | source: content/index.md | reading order line (link text only) ==== -->
start at the top of the Chronicle, or jump to the newest pages — 25 — The Courtyard and the Wish and the interlude below the city.
<!-- ==== END: L6 ==== -->

<!-- ==== SEGMENT: L7 | source: content/index.md | around-the-fire list (dead links, copy only) ==== -->
*The World* — places, peoples, and powers, as the players have learned them.
*The Cast* — the party, and the people still alive around them.
*Interludes* — the fiction, set in its place in the timeline.
<!-- ==== END: L7 ==== -->

<!-- ==== SEGMENT: L8 | source: content/index.md | footer: counter label, badges, tooltips ==== -->
COUNTER: SOULS ENTERED <span data-ledger>001337</span>
BADGES: best viewed by candlelight · 800×600 · no cookies · only candlewax
LAMP TOOLTIP: the lamp is a door
SIGIL TOOLTIP: ?
<!-- ==== END: L8 ==== -->

<!-- ==== SEGMENT: L9 | source: content/index.md | meta description (search/social) ==== -->
The Latesa Chronicle — a narrative of the Septenture campaign.
<!-- ==== END: L9 ==== -->

<!-- ==== SEGMENT: C1 | source: content/the-chronicle/index.md | chronicle intro line ==== -->
The story so far, in the order the party lived it. Each chapter is one session, dated in-world, written as story.
<!-- ==== END: C1 ==== -->

<!-- ==== SEGMENT: C2 | source: content/the-chronicle/index.md | construction bar text ==== -->
⚠ the chronicle is under construction — more sessions being bound ⚠
<!-- ==== END: C2 ==== -->

<!-- ==== SEGMENT: C3 | source: content/the-chronicle/index.md | part blurbs ==== -->
Part III — Latesa

- **[25 — The Courtyard and the Wish](25-the-courtyard-and-the-wish)** — *Glacies 4th, 1396cr.* The fight outside the Whites ends with a bell, a bullet, and a wish. The party wakes on a cliff above the sea.
- **[The Wrong Hunter](the-wrong-hunter)** — *the nights after.* The dream fight, as played between Page and the DM, posted as it was written.
- **[Interlude — The Stone](interlude-the-stone)** — *Glacies 5th.* First descent into the lines below the Hollow Heart: the queue, the nations of the stone, a body on a door, and a boy with a captain's token.

## Part II — The Sea Road: The flashback and the voyage to Latesa. Chapters in progress.
Part I — Campaign One: The Feylight Festival: Sessions 1–14, 2023–24. The festival, the ruins, the ring, and the box. Chapters in progress.
<!-- ==== END: C3 ==== -->

<!-- ==== SEGMENT: C4 | source: content/the-chronicle/index.md | chapter blurbs ==== -->
25 — The Courtyard and the Wish — *Glacies 4th, 1396cr.* The fight outside the Whites ends with a bell, a bullet, and a wish. The party wakes on a cliff above the sea.
The Wrong Hunter — *the nights after.* The dream fight, as played between Page and the DM, posted as it was written.
Interlude — The Stone — *Glacies 5th.* First descent into the lines below the Hollow Heart: the queue, the nations of the stone, a body on a door, and a boy with a captain's token.
<!-- ==== END: C4 ==== -->

<!-- ==== SEGMENT: C5 | source: content/the-chronicle/index.md | meta description ==== -->
The campaign, session by session, in the order it happened.
<!-- ==== END: C5 ==== -->

<!-- ==== SEGMENT: CH25-dateline | source: content/the-chronicle/25-the-courtyard-and-the-wish.md | chapter 25: dateline ==== -->
Glacies 4th, 1396cr · the courtyard outside the Whites
<!-- ==== END: CH25-dateline ==== -->

<!-- ==== SEGMENT: CH25-title | source: content/the-chronicle/25-the-courtyard-and-the-wish.md | chapter 25: title ==== -->
25 — The Courtyard and the Wish
<!-- ==== END: CH25-title ==== -->

<!-- ==== SEGMENT: CH25-body | source: content/the-chronicle/25-the-courtyard-and-the-wish.md | chapter 25: body prose ==== -->
The courtyard outside the Whites did not end the way anyone expected, and it did not end because of the militia.

It opened the way the last hour had left it: fog in the gutters, star-brick underfoot, a line of black-coated militia pressed into the alley mouth, three war-hounds coming off the leash. Sergeant Rell Hask came with them, and for a moment the black-line had the numbers and the nerve to use them. Ceridwen got up out of the dirt — Coy had seen to that at the end of the last session — marked the sergeant, and let a dolorous bell toll somewhere behind his eyes. Then she put a hex on his strength and stood there smiling, which she does when she is frightened.

The hounds were the real problem. One took Page by the leg and dropped him flat, and the gale he had been holding down the alley — the wind that had pinned the whole patrol — tore loose and died. The courtyard went suddenly, terribly quiet.

After that it was a knife-fight in the fog. Selim carved into the sergeant and the hounds with a soldier's economy, then surged and did it all again when the moment asked. Legion bloomed a grey mist across the alley mouth and stood in the edge of it, where nothing could see them but them. Coy wore the bear and called down a spectral pack of his own, and the pack savaged the swordsman and the halberdier both. The militia broke under it. The halberdier fled down the alley after Ceridwen whispered something discordant in his ear. The Blade and the sergeant watched their line come apart and did the professional thing: they dropped their swords and opened their hands.

That was when Brendon's stranger stepped out of the fog and refused the surrender. She put a round of fire into the Blade, then a second into Hask, and did not stop because they had already stopped fighting. Nobody in the courtyard missed what it meant, and nobody said so. Some things you save for later.

Then Ceridwen did something nobody saw coming.

She reached for the power she has spent months being afraid of — the cold at her wrist, the whisper that never quite stops — and this time she took Morgath's hand. She spoke a wish, in a voice that was half hers and half something much older, and the courtyard stopped: every soldier, every hound, every raised blade and ragged breath, down where they stood. The fog hung in the air. The fight was over.

Only Coy was awake to see what it cost. He felt the wish coming and fought it — bear-muscle and rage — and it took him anyway. In the half-second before the grey closed over him he saw a low, patient shape at Ceridwen's shoulder, an unlit weight with an appetite behind it: Morgath, wearing a shape of his own at last. And across the courtyard, where Page lay in the fog, a light — gold and slow, welling up from under his skin like something underneath finally waking.

Then the dark took him too.

---

They woke on a cliff.

A ledge of wet rock high above the sea, the night wind coming off the water, and the whole city of Latesa glowing below — the Basin's lamps, the Rungs, the long grey seam of the cliff face all the way down to the light. Coy was human again. Selim was gone. The gunslinger stood a little apart with her hands loose at her sides, watching the water.

Nobody had a good explanation. The wish had taken the field, and then it had taken them, and it set them down on the cold side of the cliff with the city they had spent a month bleeding in spread out beneath them like a map of somebody else's trouble. The militia were still in the courtyard, asleep or dead or waking into either. The dispatch case was still unopened. The halberdier was still loose in the fog. The two people they had promised to protect were still behind a dry fountain in the middle of all of it.

Page alone had stayed awake through it. The goddess's hand held him up while the wish swept the field — long enough to see the shape at Ceridwen's shoulder for himself, long enough to still be standing when the others dropped. The bill came due at once. When the others began to stir on the cold rock he was already moving, because someone had to be: he hauled them up, pointed them down the cliff face toward the lamplit window, and paid for the rooms with coin he was fairly sure was the last of it. Then he lay down in his boots, and the dream came for him, and eight hours of black sleep carried him to morning.

Page sat down on the ledge because his leg would not hold him, and stayed because the wind was cold and the cold helped. Far below, a lamp burned in a window partway down the cliff face. A curtain moved. Somewhere a pot scraped.

That was the light they followed: one lamp, one curtain, one pot.

---

*Next: [the Wrong Hunter](the-wrong-hunter) — what came for Page in his sleep — and the week the city spent closing.*
<!-- ==== END: CH25-body ==== -->

<!-- ==== SEGMENT: CH25-meta | source: content/the-chronicle/25-the-courtyard-and-the-wish.md | chapter 25: meta description ==== -->
The courtyard outside the Whites, the surrender that wasn't, and the wish that ended the fight.
<!-- ==== END: CH25-meta ==== -->

<!-- ==== SEGMENT: WH-dateline | source: content/the-chronicle/the-wrong-hunter.md | the wrong hunter: dateline ==== -->
the nights after Glacies 4th, 1396cr · a dream
<!-- ==== END: WH-dateline ==== -->

<!-- ==== SEGMENT: WH-title | source: content/the-chronicle/the-wrong-hunter.md | the wrong hunter: title ==== -->
The Wrong Hunter
<!-- ==== END: WH-title ==== -->

<!-- ==== SEGMENT: WH-body | source: content/the-chronicle/the-wrong-hunter.md | the wrong hunter: body prose ==== -->
*A dream, as played between Odin and Page's player across nine nights, and posted as it was written — minus the dice.*

You are running. Wet leaf mold under your hands, cold air against your eye-teeth, and the animal instinct that drives you: stop running and you die.

This is the old country. The old hunt. You have been here before — a thousand nights of it. A man bent on revenge follows you, filled with a bloodlust as hot as the brand they scorched you with; a man who will stop at nothing until his hands find your throat. And you are tired, so weary, beaten down so that your deepest wells of strength are plumbed.

But the trees lean wrong tonight. The wind comes from the north instead of from the east. The man pursuing you cracks twigs, crunches leaves, and the icy air does not tire him. When you look back, properly look, the hunter is wrong.

A half mile away, caught in a moment of moonlight. Fingers too long at the second joint. Hair matted, brittle, shaggy, sitting on the head like nest-stuff. Eyes like little pricks of coal set in a swollen, fleshy, wrinkled face. The coat is a Silver Sickle's coat, oh yes, you'd know that garment anywhere. The triangles glint at the throat.

The uniform is right. But the man is not.

<p class="turnmark">✦</p>

He wants to keep running, knows he should, but his body needs but a moment, a moment that perhaps he can carve to his advantage. Muscles are screaming, twitching and sore in every strand, limbs numb at the tips of his fingers and toes from the monumental effort expended in his Sisyphean chase, and Page's eyes were blistered from the cold wind he raced through.

The elf needed to rest. He needed to catch his breath. He needed to know if he could pull another string, pop open a knot, loosen the thread that tethered this relentless hunter to his trail, make it fray, snap, break.

Panting hard through chunks of hardened saliva, Page pauses behind a tree once he's sure that he's dropped line of sight, and perhaps, provided a momentary illusion that he had continued to run. He dislodged a pair of rocks with the nick of his foot, sending it crashing opposite of him, in hopes of creating an auditory distraction that might lead the hunter's attention elsewhere. The elf swallowed his breath and made himself silent, dropping to the earthen lee to spy underneath a lifted root, keeping his body still, his eyes wide. He tried swallowing down the gunk in his throat, the wretched ragged burn that his breathing had raked through his lungs. With his eyes strained and blood shot, he focuses his elvish sight on the distorted shape of his hunter.

<p class="turnmark">✦</p>

At first, as you peer between the gap where root and earth do not quite meet, the distant figure seems the same as you expect: just a man. The worst of men, perhaps, but still a mortal. You hear him shout out, calling in a taunting tone that nevertheless betrays frustration at your continued success in eluding his pursuit.

"Where are you, little tips? Where aaare you? Hide all you fucking like, I am going to find you!"

He stomps down the hillock on which he stood, coming close but clearly unsure which way to go. Your heart hammers. Your palms sweat. In front of you a spider descends on a thin line of string, black eyes glittering in the moonlight. The chill air of the evening has its claws in you and you shiver despite your every effort to remain perfectly still.

In reality, this moment defined a choice that nearly cost you everything. In your dreams, this point always becomes the worst of the nightmare, the part from which you awaken sweating and trembling with anger and fear.

But tonight something new happens. You feel a sudden warmth upon your shoulder, as of a hand there. You imagine it as your father, then your mother; you take comfort in it as a child does when young and a parent holds bulwark against the night.

You always longed for this. Some deep part of you always wished Mein en Glad would come, would lift you from the curse of this repeated nightmare. But that dead god had not risen from the afterlife to save you that night, so long ago, nor had he emerged from shadow to bring the peace of change to your cycle of dark dreams. But this, this is exactly how you imagined it might be if he came.

But of course, it is not the god of change. It is a goddess, the last living goddess of the old world. And she is with you now.

In that moment, you see with clear eyes.

You see seams in the man's skin, like the dotted lines of stitching in the flesh of a doll. And the skin is worn too tight.

"Run," whispers Alleviatira in your ear. "Let this play out."

The chase continues, the forest... bends. It should not bend — you know this ground, every root and hollow of it — but the woods keep rearranging themselves into longer pathways and shorter ravines, and the thing behind you is not tiring.

And it speaks. In its call it seems as if there are two voices braided. A hunter's rasp you have heard so often in your dreams: "Quick little fucker, you are…" — yet under it, older, softer, worn like a rot buried in the bone: "Come home, child. You were always such a quick little thing."

Then, plainly, in the hunter's voice: "We have all played at this game for generations. We know you all too well."

You have heard that sentence before. You heard it from men with triangles at their throats. Perhaps you thought it was arrogance, simple human cruelty. Now it strikes you with the cadence of a script.

The hunter is getting closer, somehow moving faster than you through the twisting wood.

<p class="turnmark">✦</p>

Page presses himself into the dirt, into the mulch and damp, reveling in the chilly mud against his hot skin, cooling his overheated body. When the lumbering figure draws close, Page swallows his ragged breathing, fighting off the cough that threatened to struggle up through his raw throat. He was terrified and spent, his body screaming for both rest and movement both, so long pushed that it had forgotten what it was like to be anything but possessed of panic and animal instinct. He dared not close his eyes, dare not blink, not with the bulky hunter so close, so...so wrong. He kept himself in silence, kept still, like a salamander in the leafy detritus, cradled in the roots of the ancient tree named after Alleviatira's fallen tears. The gentle hand against his back, warm and reassuring, gave him the strength to think, even as his mind played a cacophonous cantata of chaos and screams of fear. He needed to study this enemy. He needed to know. If anything, his long life, his centuries of living, had taught him one thing:

Knowledge is power.

Run, Alleviatira had said. Let this play out. Page mouthed a whisper of sound, shapes in his old elvish tongue as magic welled deep within him and spread throughout his body like the numbing grip of warmth. His head sank back into the dirt, and all at once, his consciousness left him, his mind moving into the illusory double that he had conjured, superimposed over his own body. With his physical body left curled and hidden in the roots, mud-soaked and covered with moss and dead leaves, Page lifted his illusory self and began to run again, this time taking care to make deliberate misleading wheezes of noise as he feigned his struggling, his fearful whimpers and ragged draws of breath. His illusory body could move faster than his own, not needing to navigate and negotiate the natural twisting terrain, and so he sprinted through the woods, his ghostly image a corporeal flash through the warping trees, leading his hunter astray.

The creature had said, had called, beckoned to him in those long echoed years with the horrid nicknames that he had earned as the hunter's prey, but what frightened him the most was its plea.

Come home, child.

Whose voice was that? Who wore the skin of the Silver Sickle? Page let his double run far away from his body, towards a gulch, a cenote, he knew to be hidden in the woods, like a shadow-blackened mouth into the forest's grave. His auburn hair, his braids in ragged matted tresses bounced on his shoulders as he wove like a needle through the old growth trees, his ears hot with the pounding of blood in his tipped ears. Grey eyes, wet with fear, cast over his shoulder, searching for the glint of the silver triangles bouncing on the Hunter's neck. Every time he heard the Hunter's howl, heard him growl, he let himself make a terrified sound, hoping to lead the creature on.

A gulch, deep and tangled with brush and bush, obscuring the dangerous rocks beneath loomed up in front of him. Page ran to its edge, as if he didn't realize it was there until it was too late, and caught himself on the crumbling edifice, staggering back until he found solid ground again. Panic stricken, Page's double froze within the sights of the Hunter and made of himself a tempting target.

He just needed to get this hunter close.

<p class="turnmark">✦</p>

The double runs. A real body would have faltered on the mossy and fern-covered slope of the ravine but the double does not, because no body runs there — only a lie... and yet the Sickle believes it and follows.

Through your double's eyes you run, fast as light, and yet the hunter behind you finds the short road every time. A ravine that should cost him five minutes costs him one. A rise that should break his stride does not. The cold does not touch him. You know this nightmare ground the way you know your own hands, but tonight the ground betrays you, opening for him, closing around you as if the woods picked a side long ago and are working with your enemy to hem you in.

The double reaches the gulch. It catches the crumbling lip, staggers, turns. Below it the old cenote yawns, a mouth of brush and broken stone, a black drop with no bottom your gaze can find. The double freezes and becomes what you asked: a spent thing with nowhere left to run.

The hunter climbs to it. And you get your look.

He is worse close.

The skin pulls tight over him. Where it pulls, fine dotted seams run — one down the jaw, one up the wrist, one where the throat meets the collar. The stitching of a doll. The jaw sits wrong, a glove on a smaller hand. The eyes burn small and black and kind in a face swollen and wrinkled and wrong. The coat is a Silver Sickle's coat. The triangles at the throat catch the moon and glint.

And then you see what your fear has hidden all these years. You've hated them, these Silver Sickles and their ilk, but suddenly the hatred clears away like fog before the dawn and you can see the truth unclouded. You called it bigotry. And it was bigotry, true and honest human cruelty against the "other." But now you feel the shape that urged this bigotry on, the hand that gave it power.

The hunter stops. Two steps out.

It tilts its head. It sniffs the air, once, slow, like a dog at a fence. A spider drops from its hair on a thread, swings, and scuttles off onto a shoulder. Then it speaks, two voices sharing the mouth. One the man's, your nightmare's voice. The other a groan, like tree limbs twisting under pressure. And then, even deeper, a third voice, younger than the rest, almost a child's.

"Quick," rasps the hunter. And under it, the second voice soft and worn and almost kind: "You always did leave such little trails, dear. Even when you didn't mean to."

Then it lifts its head. Not to the double. Past it, out into the black, toward a shape under the roots no eyes should be able to find. You feel its gaze follow the very magic you used to conjure your double, follow that thread of living universe right back through the night to your hiding place.

The hunter smiles.

<p class="turnmark">✦</p>

The double's eyes widen, earnest fear translating through the elf's haggard features as he watches the head of the Silver Sickle turn away from him, its burning coal eyes following the umbilical of magic that connected his projected consciousness to the limp body cradled in the roots so very far away. Even this, though, is an advantage. With the creature turning away, exposing the seams on its neck, the double lurches forward like a seamster, investigating an open tear in the fabric of this monster's reality. Page allows his double to approach that which he dare not, and he closes the distance, an intimate proposition, of a dancer approaching his partner in this sick and twisted waltz. Is there a place where the sewing has broken?

Page's keen eyes focus through the double as he very nearly tucks his head against the hunter's neck to whisper.

"What are you? Why do you hunt me? My kind? What could you possibly gain from haunting me and mine all these long centuries? What could you possibly need from this lowly revenant that you would go to such lengths, to wear such a fetid and hateful disguise? Why don't you show yourself, rip off the book's cover?"

<p class="turnmark">✦</p>

The whisper leaves your double's mouth. Then you get to work.

Alleviatira's hand rested on you when Morgath and Ceridwen cast the Wish, and you have not yet spent all of what the goddess lent you. You spend it now. This nightmare creature's unnatural dream presses in on you from every side, but in this moment you understand that her spell is also her dream, and a spell is a door left ajar. You set your shoulder to it and push.

She answers your questions, seemingly unaware of your change in focus, and she is clearly enjoying herself.

"What am I? I am the dark beyond the first fire. You knew me before your kind knew words. Why do I hunt you? Because you deserve to be hunted for being so weak. What do I gain from it? Such delightful satisfaction.

"Tear off the cover, you say?" The coal eyes crease. "You bind books. I bind people. This cover was never there for the book, little binder. This cover is there to spare whoever opens it." The voice drops. "But come. Look, if you dare."

You take her up on it.

The seams give way all at once. Beneath this hunter's face lies another hunter, and beneath him another — centuries of wearings pressed one over the other, flowers kept in a book. The coat has taken a shape of its own around them all. The longpig leather breathes, its surface warm, and under the smell of old sweat lingers the sweet smell of fruit given way to squirming maggots.

But then you press deeper, past the facade, into the why at the core of this being. And you understand. Entities like her work in the dark, across spans most mortal minds could ever hold... most minds, but not all. Deep in your bloodline, in a time that is myth even to elves, there lies an old defeat — hers, from long before the sea-road brought the elves to this new land — and her kind have never once forgiven it. You understand now that her quarry is memory itself, and her tools are the natural bigotry and fearfulness that has always divided the peoples of the world. She works these like a master smith, bending entire generations to her will.

But there are deeper depths to plumb.

You sink into her and the horrible fondness in her voice becomes a pool around you, a pool clear enough for you to see the truth at the bottom... and this one stings. She feels no mockery when she looks at you. She feels recognition.

You understand now that she loves to stalk the quick, the small, the frightened, the ones who break cover and run. And you can see, as through a mirror sheened in verdigris, that every hunt is a replication of the first hunt, the hunt that made the hag: a child slipping through the dark, looking back, afraid. For four hundred years she has eaten the ones who run, because she is still trying to catch herself. It is longing made manifest in the most abhorrent of ways.

That longing is a door. You go through it.

The seam at her throat parts. Cold air comes out of her, air that smells of rain and petrichor. And out of this gap, an eye peers out. One eye, wet and wide, the white showing all the way around. Then a cheek presses against the inside of the sagging fleshy facade, and this skin is fresh and young, pale as the underside of a mushroom. As the skin of the hunter deforms and bulges, its neck splitting wider to make room for this thing emerging from inside, long black hair leaks through the opening and hangs, sodden, down the dead skin of the hunter's neck. It drips, and the night is too dark for you to tell if it drips with water or with blood.

A hand pushes out now as well — small, cold, nails bitten to the quick — and quick as winking lays itself flat against the cheek of your double.

Hundreds of feet away, hidden in the roots, you feel it. Cold fingers on your jaw.

And now the girl — for it is a young girl, bursting like a tumor from the rotting mass of the hunter's form — looks at you out of the hole in everything she became, and she says two words, her voice strained as if each syllable costs a lifetime of will to voice.

"Black Nine."

The dream goes silent. No cricket, no drip of water, no wind. The night holds still. The nightmare has momentarily frozen as the power propelling it shudders.

You have hurt this entity. And you have frightened her, which is worse. The girl reached for a stranger. You understand, through the dregs of your connection, that for four hundred years she has kept the child buried, until now. And you gave the child the opening through which to speak.

"That was mine," she says, voice a quiver of creaking wood and the moan of bending iron. It shakes in fury.

Then she gathers herself. The coats come back one over the other, the seams returning the hunter to its form, and the coal eyes harden as it peers hard at your illusory double.

"You stopped breathing a little while ago," she says, steady now, colder. "I let it go. I wanted to hear you." She pauses. "I should not have."

One long hand rises. The fingers close on the double's throat, and the throat gives like smoke, your magic dispelled.

The dream bends. Far under the roots the bright thread flickers. Space warps and now you are barely sixty feet away from your hunter, laying in the wet leaves as the horrible patchwork face turns toward you.

You have but a single breath. Then she follows the lingering thread of your magic home.

<p class="turnmark">✦</p>

In that single breath, wretched with awakening understanding, Page breathes in tandem with the reconstituted hunter. Air, like the ragged and bloody fingers of a starving bard, drags over the strings of his throat and he croaks, miserable and trembling with the song of hate. The elf pushes himself up from the cradle of root, mud, and leaf, his own eyes a frightful churning storm as he narrows his gaze upon the hunter.

"I am the hunt that bests the hunter," Page hisses, his voice quavering, and from his forehead, a bright and hot white horn breaks into the black of the nightmare. It grows, a spectral spear in the shape of an awl, and within that horrid second does Page unleash a psychic lance, made manifest from the terror he had endured, the anguish and fear. He screams as he hurls the spectral white hot spear. He is the unicorn that guts the hunter, the one that fights back.

"Black Nine!! Tell me what it means!" He shrieks, bracing himself against the cradle of roots, as if the body of his dead god was that which held him, protected him, gave him strength. "What is the Black Nine!?"

<p class="turnmark">✦</p>

Like struck flint in a cave, the lance that leaves your brow burns white and hot—a narrow awl of thought twisting through dream-fabric like hot iron through paper.

You drive it straight into the seam: that wet, ragged tear along the throat where the girl's pale cheek pressed out, where sodden black hair clings to the dead collar.

The lance punches deep into the fissure.

A dull, resonant thrum hums through the dream like an ancient bell struck underwater. It rattles the teeth in your jaw and sends sharp ripples across the stagnant puddles around your knees.

And then she screams.

The sound tears the dreamwoods wide open. The hunter's guttural rasp braids violently with a child's shrill shriek, a raw, frantic terror unaccustomed to pain, suddenly undone by four centuries of reckoning arriving in a single second.

The stitched seams burst across the hunter's frame. The leather coat splits wide, venting an ancient stench of stagnant earth, dried rosemary, and the dank must of forgotten cellar floors. The patchwork flesh sloughs and sags, barely containing the white-hot light fracturing through the tears.

The hunter crashes hard onto both knees, clawed fingers gouging the muck. The coal-black eyes roll back into its skull, dying down to grey ash. Around you, the nightmare grinds to an absolute halt. The falling leaves freeze in midair. The swaying branches stiffen into stone. The creature remains pinned beneath the weight of your mind, its consciousness reeling in suffocating paralysis.

Through the smoking fissure at its throat, the memory spills out as a raw and unbidden violence into which you headlong plunge.

A battlefield under a sky that bleeds violet. Spires of silver fire and jagged purple lightning tear the earth apart.

At the center of the storm stands a man, perhaps in his late-thirties (it's always been hard for you to tell with mortals). You are struck at once by the sheer presence of the man; he is regal and terrifying in his fury. Long black hair whips around his shoulders in intricate braids, woven past a high, starched collar. Tendrils of arcane power pulse around him in an octarine fire. He commands through raw will and the universe itself obeys. Every gesture of his hands splinters stone, disintegrating waves of the Shrouded into mist and shearing through towering, antlered briar-beasts summoned from the deepest fey barrows.

He is winning! You feel a desperate fear. Yours? No... you can feel it now; this is not your body, this is hers, the creature from your nightmare. This is her memory; you are watching this battle through her eyes as this sorcerer supreme systematically dismantles the Briar Wife's defenses, pressing the arch-hag back into the dark with an unholy, brilliant majesty. He has come to end her forever.

But at the edge of the fire-line, you wait in the smoke and shadow, preparing to strike when the moment is right. Then your mind splits and you can at once see with her eyes even as you look at her, this horrible, melancholy thing that has haunted you for so long.

It wears the face of a little girl. And dragged behind her in chains of briar and blackened bone is an older woman with fierce, weeping eyes. You know, from the connection you've made with this hag's mind, that the woman was a mother who recognized her daughter's face in the dark woods and walked straight into the snare. Morgath's mother.

"Look here, sorcerer! Look upon your family and despair!" These words burst from your mouth, hateful and gleeful all at once. You—the girl, dragging the bound woman—leap forward into the midst of the battle. And you see Morgath turn, see his eyes narrow in fury, then go wide in horror as he understands.

And now you understand as well. The Briar Wife planned for this. She broke the assault with a simpler lever than magic. She simply had the daughter he had loved more than anything hold their mother by the hair and place a claw against her throat. The sorcerer's hands freeze mid-invocation. The violet sky goes a dead blue, the color of peeling plaster. The trap snaps shut.

You are back in your body. The hunter's split form before you.

"The boy who called himself an angel of vengeance," the voice wheezes from the ruined throat at your feet, ragged and whistling like wind through a cracked flue. "He burned our harvest twice. He broke the court we built. But my Mother knew... mortals can be made to trade an empire for a daughter's smile..."

The seam shudders, and the voice slips into a venomous, ancient chuckle. "They will lay down even their gods for their blood."

"And while he bled himself dry trying to tear my mother's roots from the world, where were you? Ah, yes, you were out in the hills, weren't you, little bookbinder? Running your clever little trails, so certain of yourself, of your mission in life. So certain in the omnipotence of your decaying corpse of a god!" Again, she laughs. The coal in her eyes flickers brighter, hotter. "Leading your gentle elf-folk away in the dark. And you never suspected that the Sickles were our hounds, herding your people into corners—you thought the boy sorcerer was the monster your people needed to fear. You built oh so many roads, oh so many hidey-holes... and what's the problem with a hole? There's no way out."

The realization settles like poisoned lead into your marrow. Morgath... the 'reign of terror'... it was an apex sorcerer waging an all-out, desperate war to burn the hags and their Sickle hounds from the world. He was crushing them until they weaponized his own lost family against him. And as Curaun, you fled his fire, misreading the smoke, believing the propaganda of the victor. And how many of those that you thought saved were really just shepherded directly into the waiting arms of this creature? It makes you physically sick to think.

Your breath comes ragged in your throat. You lean over the paralyzed, shuddering horror, your voice trembling with the force of the dead god's root at your back.

"Black Nine! Tell me what it means! What is the Black Nine?!"

The paralyzed thing strains against the psychic spear pinning its thoughts. Slowly, inch by agonizing inch, the head tilts up. The coal eyes ignite with a venomous, lucid fury.

"You dare demand an account from me?" The voice grinds like millstones, but your will still presses upon her, compelling her to speak. "Nine lines run beneath your stone city. Eight are dead. But the ninth... the ninth is a mouth that hungers for fresh meat, and someone we don't yet know has been feeding it fat."

A wet, shuddering breath pulls through the split throat, dragging the gaping edges of the coat back together with agonizing slowness. The hag is regrouping her power, pulling her form together bit by bit.

"Ask the pale little doctors who tend the deep. They hold the keys. They know the dark down there better than I... they are very old friends of mine."

The creature heaves itself back onto its feet, the shattered seams knitting together crookedly, swollen and angry.

Splitting the ruined face, a crooked, bloody grin pulls wide across the stitches. "You have earned a proper accounting, little binder. Let us speak of bargains."

The dream darkens around you, narrowing until only the two of you remain.

<p class="turnmark">✦</p>

Page's mind wallows in the billowing curls of the memory, his senses tangled in the smokey vines, struck and sick with the dawning weight of realization. For a moment, he wants to retch, but he swallows back the ochre bile, crawling onto his feet until he's standing stolid and strong above the unraveled mess of his enemy. The arch-hag... A remnant of fables once whispered by his elders, his very own father, a warning to the young elfin that wandered into the wood, lured by the ancient magic of the forest. Be wary, they warned, For the world is full of weeping, that you've yet to understand, weeping reaped by the hags, our ancient foe, who chased us from our ancient home. Ware, the bargain, the blight, and the briar, ware the hag that hunts us still.

Hunts us still... As Silver Sickles, in mortal flesh, through mortal means, these wretched creatures chased his kind to the ends of the earth, systematically lancing each life from the surface of the world. Donning the easy guise of mortal hate, fanning a movement of bigotry and genocide, did they work their will on the ancient descendants from the realms of the fey. Centuries of mortals making mince meat of his kin, all beneath the guiding strings of their puppeteer masters. Page feels his cheeks heat, his paper-grey eyes burn. He feels that molten sickened anger well up his gut into his throat, climb through the taut chords of his neck and into the very ears whose tips have been a point of pride for his eternal hunter. The creatures who wrought the world's weeping, who, centuries ago, controlled a narrative of a powerful sorcerer and wrought his doom with bargains and butchery, set him into desperate war while his would-be allies fled the sight of his shadow.

Was it right for him to flee, to ferry his kindred to safe haven? Did any of his roads, his hideaways, did any of his secret warrens provide the peace that he hoped to carve out for his elven kin? Doubt, an insidious poison, grew like a gnarled vine in his guts. Page swallowed back down again the fetid bile that threatened to burn his teeth. No, he couldn't let that doubt take root. Logic was the glove of the gardener's mind and Page ripped up those wretched rotten seeds. He saved many, he told himself. Saved many who would have otherwise been hunted and torn apart, the tips of their elven ears mounted and silvered to be hung on necklaces. Without his intervention, the war would have been more quickly lost, his people, more scattered and lost. He created community, drew thread through the holes that threatened to rip their families apart, bound them together in strength and companionship, created networks that provided a living for his hidden kindred. He never built his warrens without exits. He was not stupid. Page... No. Thurilas... Was kind and clever, sharp and wise. He had survived all these long centuries not only to save his kin, not only to preserve their culture, their knowledge and art. He had survived to do more than simply preserve.

Thurilas had survived to write the end to the tale of his people's torment.

Smiling, limpid and pitying, the elven bookbinder gave the stitched guise of his hunter a quiet once over, judgement passing over his face as if he were a teacher, appraising a student's sewing. He shook his head slightly, a sordid smile reflecting the bloody grin turned to him. "Oh," he chided, almost sulking, almost consolatory, "Pretty thing, if only I cared for bargains." His features turned confident then, lofty, even. If it wasn't for the mud and leaves, the twigs in his hair, he would have looked a professional at politics and pandering. Page pulled his smile into a toothy predatory grin.

<p class="turnmark">✦</p>

"There is not a book bound big enough to house all the wretched things I will do to you and your miserable sisters," he crooned, his words sweet as saccharine and strychnine. His grey eyes flashed with delight as he leaned closer, conspiratorial with his intent, his smile pinned on his face. "I'll just have to make one myself. I'll start with your skin. I'll turn your bones into folders, into needles with which I'll sew the string made from your sinew. I'll make ink from your black blood, and I'll write for all to know exactly how to dismantle you and your kind. How to keep your kind small and brittle, how to dispel your bargains and magics, how to make you rot and rage at your own feeble undoing. I'll write your end upon the vellum made from your hide, and trap the history of your miserable demise for all to see and learn from so that your kind will simply thin and fade from memory. I'll make of you such an end as to inspire the very muses themselves with new understandings of oblivion. An anthropodermic bibliographic study in how to kill a hag. After all, I've learnt all from the best in such morbidities. I've learned from you. Thank you."

Another glowing star of light blossoms upon his brow and again, this time at point blank, with as much strength as his magic can muster, Page punches the awl of light again into the horrible hag wearing the hunter's guise. He knows that if she's offering a bargain, that she's on her back foot, and so in his other hand, he readies a counterspell, should she try to warp away. Hags were survivors, too, and made for themselves many exits. Page intended on collapsing each option, until the hag knew what it was like to have her back up against a wall, to know what it was like to be him, hunted and scared and terrified.

"I'm going to be biographer. I'm going to be your end."

<p class="turnmark">✦</p>

The second lance leaves your brow at point-blank range, a javelin of thought driving for the ruin of her face.

She watches it come... and the light passes through her.

The hunter's shape comes apart like a reflection disturbed in water. The coat, the coal eyes, and finally the stitched grin, the whole of her shivers and thins into mist before your eyes. Behind you a tree falls and makes no sound. Motes of dust caught by the beam of your spell freeze in mid air. And you remember: this is a dream.

"Bargains," the hag says, though the words come from someplace farther away than her fading image's mouth. "Why Bargains? Curiosity, little binder. I wanted to see what you would do with one."

The mist takes her feet. Her hands. The long fingers.

"You could have had him, little binder. I would have been true to my end of the deal. The boy on the cold table? I would have shown you the truth... now, perhaps you'll never know."

Her grin is the last thing to go, a crack in the mirror of this dreamworld, a jagged break in the fabric of your mind.

"You will never know now. Unless... Unless you ask the little singer what she's hiding from you... well, some of what she's hiding." A wicked laugh echoes around you as the dream world dissolves.

"Sleep well, Thurilas. Enjoy waking with everything except the one answer you want."

The dream goes out.

You wake in the Hollow Heart, the tavern you've all managed to collapse in after the harrowing ordeal of the battle in the courtyard and Ceri's impossible casting.

Cold stone above you. An amber lamp turned down to a bead. The curtain across the chamber's mouth stirs with someone's breathing. Down the passage a pot scrapes, and the air carries early morning food smells, the must of wet rock, and the smoky residue of that odd tallow they burn in cliffside dwellings.

You are on your back in your boots. Sleep took you without warning; you remember the ledge overlooking the grey winter ocean, the cool of the salty wind, and sitting down for a moment with your back against the stone. The dream arrived on its own.

Even though you know the dream is done, you shiver. Then your knuckles crack as anger floods you, hot and electric.

The stitched shape mocking all those years of running, hiding, protecting while being unknowingly steered, all in service to a defeat her kind has never forgiven. And with the anger, too, is shame. Morgath's war... how could you have been so wrong? One man, one enemy, his own mother and sister used to break him. And the hag's words: Black Nine. The image of a mouth under the city, gaping, sucking, slavering for flesh appears unbidden in your mind. Something old is down there, something half-dead and buried beneath the petrified stone... and through it, somehow, the night doctors are entering the world.

And the girl. You shudder at the memory of her eye, her black hair leaking like oil from the fetid wound in the hag-thing's stitching. The buried girl, who looked at you out of the seam with one wet eye. Somehow, she existed deep within the hag. Somehow, she was the hag. And you had reached her... and she had reached back. Black Nine.

You went to sleep with a grudge, you have awoken with a map of the war.
<!-- ==== END: WH-body ==== -->

<!-- ==== SEGMENT: WH-meta | source: content/the-chronicle/the-wrong-hunter.md | the wrong hunter: meta description ==== -->
The dream fight, as played between Page and the DM — the fiction, with the dice left out.
<!-- ==== END: WH-meta ==== -->

<!-- ==== SEGMENT: INT-dateline | source: content/the-chronicle/interlude-the-stone.md | interlude the stone: dateline ==== -->
Glacies 5th, 1396cr · the lines below the Hollow Heart
<!-- ==== END: INT-dateline ==== -->

<!-- ==== SEGMENT: INT-title | source: content/the-chronicle/interlude-the-stone.md | interlude the stone: title ==== -->
Interlude — The Stone
<!-- ==== END: INT-title ==== -->

<!-- ==== SEGMENT: INT-body | source: content/the-chronicle/interlude-the-stone.md | interlude the stone: body prose ==== -->
Morning down here is a rumor the world above keeps to itself. The pot went out an hour ago. The stew is thinner than yesterday, and nobody has pretended otherwise since the soldiers came. So you take the water queue, because that is what there is to do.

The Hollow Heart hangs a curtain where another place would put a door. It opens on a landing, the landing opens on the stone, and the stone goes on longer than any city has a right to. Tunnels run away in both directions, hung with oil lamps and washing lines. Far above, a straw's width of grey: the sky, minding its own business. The Rungs creak under traffic and never quite stop — ladders and switchback stairs stitching the cliff for miles, climbed the way other cities cross streets, without looking, a hand on the rail. Nobody looks up here. The Drop sits directly overhead, and the barracks empties its slops over the edge.

You came down the Great Ox-Elevators yourself, the day you reached this city — the whole cliff face roaring past, the Basin opening below like a cave that learned to be rich. That was weeks ago. You have been back in the city since, but you have never gone *in*: never past the first landing, never where the lamps run out. Down here people live. The proof is everywhere. Laundry, cooking smoke, a chess game abandoned mid-move on a windowsill of rock, a child's chalk marks counting something on the wall.

The queue moves along a channel cut into the floor, cold water running in a groove worn smooth by a hundred years of hands. The wardens have been dwarves for as long as anyone remembers; nobody living hired the first one. The warden on duty keeps her ledger chained to her belt and does her arithmetic like a politeness: you get what the share buys, and the share is thinner this week, because the soldiers above drink like the rain owes them money.

The market shelf is two turns on, where the Yellow Line widens enough to call itself a square. This is where the stone keeps its nations. A halfling runner-band works the landing with painted message-tubes, beached since the blockade closed the river, their boat-coats going grey on their backs. A gnome sells rescued door-locks off a hand cart and swears every lock opens twice. On the Sea-Lift side a dragonfolk stevedore with brass scales gone green at the joints swings kelp baskets up from the water and never once looks up the cliff. And in the Grey Lines, so the queue says, there is a shop that sells faces — paper ones, cut to fit a checkpoint. Everyone denies using it. The price is common knowledge.

At the lip of the Gullet, where the rock drops away into the dark that swallows the city's waste, scavengers work the ropes. Illithids. Their people keep cities in the deep cold under the ocean; these few came up generations back and stayed, sorting what the stone throws away — bone, brass, rope-ends — with hands more careful than any butcher's. Children are told to stay away from the Gullets. The children play at the edge anyway, and the scavengers move the rope so the children can.

Two turns past the market, a fry-stall sits at the shelf's edge, oil and onion and the good smell of somebody else's breakfast. The girl working it has the mark: half-pointed ears, the blood showing soft through them. While she works she hums. The song stops you before you know why — the words are old, older than the tune and older than the singer, and they come out small, at a volume for one, the way people sing things they have been told mean nothing. Nobody else in the queue turns a head. You let it go. The wanting to ask stays with you longer than the song does.

Then the Watch comes through.

Four of them, bright tan and blue, young; behind them, unhurried, in black, with the stamped paper and the book of names, the soldiers. A body comes up from the Flint Seam side on a door off its hinges. Another one. The officer never looks at the body; he looks at the queue, and the queue looks at the stone. By evening the Watch will call it Sickle work, the way they call everything Sickle work. The queue murmurs otherwise, quietly: the wounds were made by somebody with time — somebody who washed up after.

By the time the queue shuffles forward twice, the body is gone off the door. Nobody sees it carried. Collected, then.

You are still holding the day's water when a boy comes down the Rungs three at a time, wrong direction, and lands at the end of the queue without spilling. Too thin, too sure of himself, paid to be somewhere. He does not ask for a name. He counts the strangers without appearing to: the tall one with the ears, the one with the guns, the quiet one wearing bone at her wrist. He holds up a clay token with a captain's seal pressed into it.

"Up," he says. "You're wanted."

You go up. The water is heavy, the stairs are dark, and behind you the queue starts talking again, quietly, about the body on the door.
<!-- ==== END: INT-body ==== -->

<!-- ==== SEGMENT: INT-meta | source: content/the-chronicle/interlude-the-stone.md | interlude the stone: meta description ==== -->
First descent into the lines below the Hollow Heart.
<!-- ==== END: INT-meta ==== -->

<!-- ==== SEGMENT: WAR-dateline | source: content/the-warren.md | the warren (hidden page): dateline ==== -->
(no dateline)
<!-- ==== END: WAR-dateline ==== -->

<!-- ==== SEGMENT: WAR-title | source: content/the-warren.md | the warren (hidden page): title ==== -->
The Warren
<!-- ==== END: WAR-title ==== -->

<!-- ==== SEGMENT: WAR-body | source: content/the-warren.md | the warren (hidden page): body prose ==== -->
You found a page that is not in the index. Nothing here is required reading. The stone keeps a few things for people who look sideways.

The Chronicle is being bound chapter by chapter. Part I walks the Feylight Festival again, Part II comes up the sea road, Part III is already bleeding in a courtyard outside the Whites. When a chapter is ready, it appears in [the Chronicle](../the-chronicle/) without ceremony.

<div class="construction">⚠ this page is always under construction ⚠</div>

## The guestbook

<div class="guestbook">

<div class="guestbook-entry">“I meant to look at the maps. The stairs kept going, and so did I.” — a runner, no name given</div>

<div class="guestbook-entry">“Prices are on the door. Don't knock twice.” — R.</div>

<div class="guestbook-entry">“The soup is thinner but the company is honest.” — someone at the long table</div>

<div class="guestbook-entry">“THE STONE IS LISTENING.” — the stone</div>

</div>

[← back to the surface](../)

</div>
<!-- ==== END: WAR-body ==== -->

<!-- ==== SEGMENT: WAR-meta | source: content/the-warren.md | the warren (hidden page): meta description ==== -->
A page that is not in the index.
<!-- ==== END: WAR-meta ==== -->

<!-- ==== SEGMENT: ORACLE | source: quartz/static/extras.js | the stone's sayings (11), used by the oracle box ==== -->
The stone remembers. The stone does not care.
Nobody looks up. That is not manners.
Every tunnel has two ends. One of them is a lie.
Buy the water first. Ask the questions after.
The Watch writes names. The stone keeps them.
A lamp is a promise that somebody is still awake.
If the route came free, it was not a route.
Down here, direction is social.
The Gullets take everything. They give back a little.
Rockside is a place you leave with permission.
A quiet room is a debt with interest.
<!-- ==== END: ORACLE ==== -->
