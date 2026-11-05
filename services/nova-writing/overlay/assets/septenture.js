(() => {
  if(document.querySelector('.fiction-nav'))return
  const config=document.querySelector('meta[name="septenture-site"]')
  const site=config?.content || 'https://syndicatedpillbug.github.io/septenture-chronicle/'
  const nav=document.createElement('nav');nav.className='fiction-nav';nav.setAttribute('aria-label','Septenture navigation')
  const home=document.createElement('a');home.href=site;const brand=document.createElement('strong');brand.textContent='✶ SEPTENTURE';home.append(brand)
  const links=document.createElement('div')
  for(const [label,path] of [['Chronicle',site+'the-chronicle/'],['Cast',site+'the-cast/'],['Exchange',site+'fiction/'],['Writing desk','/index.php/write/index'],['Table',site+'tabletop/'],['Account',site+'account/'],['Leave writing desk','/index.php/auth/logout']]) {
    const a=document.createElement('a');a.textContent=label;a.href=path;links.append(a)
  }
  nav.append(home,links);document.body.prepend(nav)
  if(/\/write\/index\/?$/.test(location.pathname))document.title='Septenture Fiction :: Writing desk'
  // Terminology is rendered on the server, including menus and empty states.
  for(const link of document.querySelectorAll('a[href]')){const url=new URL(link.href,location.href);if(url.origin!==location.origin)continue;if(url.pathname==='/index.php/user/account')link.href=site+'account/';if(url.pathname==='/index.php/sim/listposts')link.href=site+'fiction/';if(url.pathname==='/index.php/personnel/index')link.href=site+'the-cast/'}
  const identity=document.querySelector('#septenture-character')
  const content=document.querySelector('main .content')
  if(identity&&content) {
    const person=JSON.parse(identity.textContent)
    const header=document.createElement('header');header.className='character-identity';header.dataset.theme=person.theme
    if(person.banner)header.style.backgroundImage=`linear-gradient(#10130fbb,#10130fee),url("${person.banner}")`
    if(person.accent)header.style.setProperty('--character-accent',person.accent)
    const image=document.createElement('img');image.src=person.portrait;image.alt=person.name;image.width=80;image.height=80
    const heading=document.createElement('div')
    const name=document.createElement('h1');name.textContent=person.name
    const detail=document.createElement('p');detail.textContent=person.tagline||person.heading
    heading.append(name,detail);header.append(image,heading)
    const tabs=document.createElement('nav');tabs.className='character-tabs';tabs.setAttribute('aria-label','Character sections')
    for(const [label,path] of [['Public profile',person.publicUrl],['Character details',`/index.php/personnel/character/${person.id}`],['Stories',site+'fiction/?character='+encodeURIComponent(person.slug)+'&kind=Stories'],['Journals',site+'fiction/?character='+encodeURIComponent(person.slug)+'&kind=Journal']]) {
      const link=document.createElement('a');link.href=path;link.textContent=label
      if(new URL(path,location.origin).pathname===location.pathname)link.setAttribute('aria-current','page')
      tabs.append(link)
    }
    for(const link of document.querySelectorAll('a[href]')){const url=new URL(link.href,location.href);if(url.origin!==location.origin)continue;for(const [path,kind] of [[`/index.php/personnel/viewposts/c/${person.id}`,'Stories'],[`/index.php/personnel/viewlogs/c/${person.id}`,'Journal']])if(url.pathname===path)link.href=site+'fiction/?character='+encodeURIComponent(person.slug)+'&kind='+kind}
    content.prepend(header,tabs)
    if(location.pathname.includes('/personnel/character/')&&person.bio) {
      const about=document.createElement('section');about.className='character-about'
      const label=document.createElement('h2');label.textContent='About '+person.name
      const bio=document.createElement('p');bio.textContent=person.bio;window.SeptenturePublicProse?.render(bio,person.bio,new URL('/assets/',location.origin))
      const edit=document.createElement('a');edit.href=person.publicUrl;edit.textContent='Open public profile →'
      about.append(label,bio,edit);tabs.after(about)
    }
  }
})()
