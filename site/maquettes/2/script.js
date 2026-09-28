(() => {
'use strict';
const $=(s,p=document)=>p.querySelector(s), $$=(s,p=document)=>[...p.querySelectorAll(s)];
const config=window.BELMAIN||{}, hasGSAP=!!(window.gsap&&window.ScrollTrigger);
const motionQuery=matchMedia('(prefers-reduced-motion: reduce)');
let preference;try{preference=localStorage.getItem('belmain-motion')}catch{}
let reduced=preference?preference==='reduced':motionQuery.matches, motionContext, quantity=1;
const header=$('.site-header'), menu=$('.menu-toggle'), nav=$('.mobile-nav');
function closeMenu(){menu.setAttribute('aria-expanded','false');menu.setAttribute('aria-label','Ouvrir le menu');nav.classList.remove('is-open');nav.inert=true}
menu.addEventListener('click',()=>{const open=menu.getAttribute('aria-expanded')!=='true';menu.setAttribute('aria-expanded',String(open));menu.setAttribute('aria-label',open?'Fermer le menu':'Ouvrir le menu');nav.classList.toggle('is-open',open);nav.inert=!open});
$$('a',nav).forEach(a=>a.addEventListener('click',closeMenu));
document.addEventListener('keydown',e=>{if(e.key==='Escape'&&menu.getAttribute('aria-expanded')==='true'){closeMenu();menu.focus()}});
document.addEventListener('pointerdown',e=>{if(!header.contains(e.target))closeMenu()});
matchMedia('(min-width: 901px)').addEventListener('change',closeMenu);
const floating=$('.floating-purchase'), purchase=$('#choisir'), footer=$('.site-footer');
let scrollQueued=false;
function updateScroll(){
scrollQueued=false;header.classList.toggle('is-scrolled',scrollY>35);

const box=purchase.getBoundingClientRect(), foot=footer.getBoundingClientRect();
const show=scrollY>innerHeight*1.2&&!(box.top<innerHeight&&box.bottom>0)&&foot.top>innerHeight;
floating.classList.toggle('is-visible',show);floating.setAttribute('aria-hidden',String(!show));floating.inert=!show;
}
addEventListener('scroll',()=>{if(!scrollQueued){scrollQueued=true;requestAnimationFrame(updateScroll)}},{passive:true});
addEventListener('resize',updateScroll,{passive:true});updateScroll();
$$('[data-year]').forEach(n=>n.textContent=new Date().getFullYear());

// Seven real product photographs. Mouse, keyboard and touch access.
const views=[['trois-quarts','Vue de trois quarts'],['face','Vue de face'],['profil-gauche','Profil gauche'],['dos','Vue de dos'],['profil-droit','Profil droit · Port de charge'],['dessus','Vue du dessus'],['ouverture','Vue de l’ouverture']];
const viewer=$('#viewer-image'), canvas=$('.viewer-canvas');let activeView=0,viewRequest=0;
async function showView(index){
activeView=(index+views.length)%views.length;const chosen=activeView,request=++viewRequest,[file,label]=views[chosen];
const src=window.BELMAIN_ASSETS?.[file]||'assets/gant-'+file+'-1100.webp', preload=new Image();preload.src=src;
try{await preload.decode()}catch{return}if(request!==viewRequest)return;
viewer.src=src;viewer.alt='Gant Belmains, '+label.toLowerCase();$('#view-label').textContent=label;
$$('.view-thumb').forEach((b,i)=>{b.classList.toggle('is-active',i===chosen);b.setAttribute('aria-pressed',String(i===chosen))});
if(hasGSAP&&!reduced)gsap.fromTo(viewer,{opacity:.35,x:14,rotate:-4},{opacity:1,x:0,rotate:-7,duration:.5,ease:'power2.out',overwrite:true});
}
$('#view-prev').addEventListener('click',()=>showView(activeView-1));$('#view-next').addEventListener('click',()=>showView(activeView+1));
$$('.view-thumb').forEach(b=>b.addEventListener('click',()=>showView(Number(b.dataset.view))));
$$('[data-detail-view]').forEach(b=>b.addEventListener('click',()=>{showView(Number(b.dataset.detailView));if(innerWidth<=700)$('.viewer').scrollIntoView({behavior:reduced?'instant':'smooth',block:'center'})}));
canvas.addEventListener('keydown',e=>{if(['ArrowLeft','ArrowRight'].includes(e.key)){e.preventDefault();showView(activeView+(e.key==='ArrowRight'?1:-1))}if(e.key==='Home'){e.preventDefault();showView(0)}if(e.key==='End'){e.preventDefault();showView(6)}});
let startPointer;
canvas.addEventListener('pointerdown',e=>{startPointer={x:e.clientX,y:e.clientY}});
canvas.addEventListener('pointerup',e=>{if(!startPointer)return;const dx=e.clientX-startPointer.x,dy=e.clientY-startPointer.y;if(Math.abs(dx)>35&&Math.abs(dx)>Math.abs(dy))showView(activeView+(dx<0?1:-1));startPointer=null});
canvas.addEventListener('pointercancel',()=>{startPointer=null});

// The order remains an explicit demonstration until a real offer is configured.
const price=typeof config.price==='number'&&Number.isFinite(config.price)&&config.price>=0?config.price:null;
const money=n=>new Intl.NumberFormat('fr-FR',{style:'currency',currency:config.currency||'EUR'}).format(n);
if(price!==null){$('[data-price]').hidden=false;$('[data-price]').textContent=money(price)}
const minus=$('#quantity-minus'),plus=$('#quantity-plus');
function updateQuantity(n){quantity=Math.min(config.maxQuantity||5,Math.max(1,n));$('#quantity').value=quantity;minus.disabled=quantity===1;plus.disabled=quantity===(config.maxQuantity||5)}
minus.addEventListener('click',()=>updateQuantity(quantity-1));plus.addEventListener('click',()=>updateQuantity(quantity+1));updateQuantity(1);
const dialog=$('.order-dialog'),checkout=$('#checkout-link');let checkoutURL;
try{const u=new URL(config.checkoutUrl);if(u.protocol==='https:')checkoutURL=u}catch{}
$('#order-status').textContent=checkoutURL?'Vous allez rejoindre la boutique pour vérifier votre commande.':'Le parcours de commande sera relié à la future boutique WordPress. Aucun paiement n’est effectué dans cette maquette.';
if(checkoutURL){checkout.href=checkoutURL.href;checkout.hidden=false}
$('#order-button').addEventListener('click',()=>{$('#order-quantity').textContent='Quantité : '+quantity;$('#order-total').textContent=price!==null?money(price*quantity):'';dialog.showModal();document.body.style.overflow='hidden'});
$('.dialog-close').addEventListener('click',()=>dialog.close());$('.dialog-continue').addEventListener('click',()=>dialog.close());
dialog.addEventListener('close',()=>{document.body.style.overflow=''});
dialog.addEventListener('click',e=>{if(e.target===dialog){const r=dialog.getBoundingClientRect();if(e.clientX<r.left||e.clientX>r.right||e.clientY<r.top||e.clientY>r.bottom)dialog.close()}});
$$('.faq-list details').forEach(item=>item.addEventListener('toggle',()=>{if(item.open)$$('.faq-list details').forEach(other=>{if(other!==item)other.open=false});if(hasGSAP)ScrollTrigger.refresh()}));


// The supplied product film is muted, controllable and paused outside the viewport.
const heroVideo=$('#hero-video'),videoButton=$('#video-toggle');
let videoInView=false,videoChoice=null;
const saveData=!!navigator.connection?.saveData;
function videoState(){
 const playing=!heroVideo.paused;
 videoButton.setAttribute('aria-pressed',String(playing));
 videoButton.setAttribute('aria-label',playing?'Mettre la vidéo en pause':'Lire la vidéo de présentation du gant');
 $('.video-state-icon',videoButton).textContent=playing?'Ⅱ':'▶';
 $('.video-state-text',videoButton).textContent=playing?'Pause':'Lire';
}
function syncVideo(){
 const shouldPlay=videoInView&&!document.hidden&&(videoChoice===true||(videoChoice!==false&&!reduced&&!saveData));
 if(shouldPlay){heroVideo.play().then(videoState).catch(videoState)}else{heroVideo.pause();videoState()}
}
heroVideo.controls=false;videoButton.hidden=false;
heroVideo.addEventListener('play',videoState);heroVideo.addEventListener('pause',videoState);
videoButton.addEventListener('click',()=>{videoChoice=heroVideo.paused;syncVideo()});
new IntersectionObserver(entries=>{videoInView=entries[0].isIntersecting;syncVideo()},{threshold:.1}).observe($('.scene-product'));
document.addEventListener('visibilitychange',syncVideo);
// The new direction uses a photographic opening and stacking scenes.
const walker=document.createTreeWalker($('.manifesto'),NodeFilter.SHOW_TEXT),wordNodes=[];
while(walker.nextNode())wordNodes.push(walker.currentNode);
wordNodes.forEach(node=>{const f=document.createDocumentFragment();node.textContent.split(/(\s+)/).forEach(t=>{if(!t.trim())f.append(document.createTextNode(t));else{const span=document.createElement('span');span.className='reveal-word';span.textContent=t;f.append(span)}});node.replaceWith(f)});
if(hasGSAP)gsap.registerPlugin(ScrollTrigger);
function buildAnimations(initial=false){
 if(!hasGSAP||reduced)return;
 motionContext=gsap.matchMedia();
 motionContext.add({desktop:'(min-width:901px)',mobile:'(max-width:900px)'},context=>{
  const desktop=context.conditions.desktop,track=$('.ritual-track');
  if(initial&&scrollY<120){
   gsap.timeline({defaults:{ease:'power3.out'}})
   .from('.hero-intro>.eyebrow',{y:18,opacity:0,duration:.75},.05)
   .from('.hero-intro h1',{y:48,opacity:0,duration:1.1},.15)
   .from('.hero-lead',{y:26,opacity:0,duration:.85},.5)
   .from('.scene',{y:'+=65',opacity:0,stagger:.13,duration:1.2},.25);
  }
  gsap.to('.scene-life',{y:desktop?-100:-48,rotation:desktop?-3:0,ease:'none',scrollTrigger:{trigger:'.hero-scenes',start:'top 75%',end:'bottom top',scrub:1.2}});
  gsap.to('.scene-touch',{y:desktop?-60:-20,rotation:desktop?3:0,ease:'none',scrollTrigger:{trigger:'.hero-scenes',start:'top 75%',end:'bottom top',scrub:1.2}});
  gsap.to('.hero-video',{scale:1.1,ease:'none',scrollTrigger:{trigger:'.hero-scenes',start:'top 80%',end:'bottom top',scrub:1}});
  gsap.fromTo('.slow-ribbon p',{xPercent:desktop?8:5},{xPercent:desktop?-8:-48,ease:'none',scrollTrigger:{trigger:'.slow-ribbon',start:'top bottom',end:'bottom top',scrub:1.3}});
  gsap.fromTo('.reveal-word',{opacity:.2},{opacity:1,stagger:.14,ease:'none',scrollTrigger:{trigger:'.manifesto',start:'top 80%',end:'bottom 45%',scrub:.7}});
  gsap.from('.care-summary>p',{y:35,opacity:0,stagger:.12,duration:.9,scrollTrigger:{trigger:'.care-summary',start:'top 90%',once:true}});
  gsap.from('.ritual-heading',{y:35,opacity:0,duration:1,scrollTrigger:{trigger:'.ritual-heading',start:'top 88%',once:true}});
  const panels=$$('.ritual-panel');
  if(desktop){
   track.classList.add('stacked');
   panels.slice(0,-1).forEach((panel,i)=>gsap.to(panel,{scale:.94,filter:'brightness(.88)',ease:'none',scrollTrigger:{trigger:panels[i+1],start:'top 85%',end:'top 112px',scrub:1}}));
   gsap.fromTo('.ritual-product',{rotation:-12,y:35},{rotation:12,y:-30,ease:'none',scrollTrigger:{trigger:'.light-panel',start:'top bottom',end:'bottom top',scrub:1.2}});
  }else{
   panels.forEach(panel=>gsap.from($('.ritual-copy',panel),{y:35,opacity:0,duration:.9,scrollTrigger:{trigger:$('.ritual-copy',panel),start:'top 90%',once:true}}));
   gsap.fromTo('.ritual-product',{rotation:-8,y:20},{rotation:8,y:-20,ease:'none',scrollTrigger:{trigger:'.light-panel',start:'top bottom',end:'bottom top',scrub:1}});
  }
  gsap.from('.viewer-main',{y:45,opacity:.3,duration:1,ease:'power3.out',scrollTrigger:{trigger:'.viewer',start:'top 88%',once:true}});
  gsap.from('.view-thumb',{y:18,opacity:0,stagger:.06,duration:.6,scrollTrigger:{trigger:'.view-thumbnails',start:'top 93%',once:true}});
  gsap.from('.purchase-copy > *',{y:25,opacity:0,stagger:.07,duration:.75,scrollTrigger:{trigger:'.purchase-copy',start:'top 86%',once:true}});
  gsap.fromTo('.everyday-photo img',{scale:1.17,y:35},{scale:1,y:-20,ease:'none',scrollTrigger:{trigger:'.everyday-section',start:'top bottom',end:'bottom top',scrub:1.3}});
  gsap.from('.everyday-copy',{y:80,rotation:desktop?3:0,opacity:0,duration:1.1,scrollTrigger:{trigger:'.everyday-copy',start:'top 90%',once:true}});
  gsap.from('.faq-list details',{y:25,opacity:0,stagger:.08,duration:.7,scrollTrigger:{trigger:'.faq-list',start:'top 90%',once:true}});
  // Additional editorial sections follow the same motion preference and cleanup.
  $$('.copy-editorial-row').forEach(row=>{
   gsap.from($('.copy-editorial-photo',row),{y:38,opacity:.35,duration:1.1,ease:'power3.out',scrollTrigger:{trigger:row,start:'top 88%',once:true}});
   gsap.fromTo($('.copy-editorial-photo img',row),{scale:1.09},{scale:1,ease:'none',scrollTrigger:{trigger:row,start:'top bottom',end:'bottom top',scrub:1.3}});
  });
  $$('.copy-section-heading,.copy-statement').forEach(el=>gsap.from(el,{y:28,opacity:0,duration:.85,ease:'power2.out',scrollTrigger:{trigger:el,start:'top 91%',once:true}}));
  $$('.copy-promise-grid article').forEach((el,i)=>gsap.from(el,{y:30,opacity:0,duration:.8,delay:desktop?i*.08:0,ease:'power3.out',scrollTrigger:{trigger:el,start:'top 94%',once:true}}));
  gsap.from('.footer-wordmark',{yPercent:22,opacity:.4,ease:'none',scrollTrigger:{trigger:'.site-footer',start:'top 95%',end:'bottom bottom',scrub:1}});
  return()=>track.classList.remove('stacked');
 });
 ScrollTrigger.refresh();
}
const motionButton=$('#motion-toggle');
function applyMotion(initial=false){
 motionContext?.revert();motionContext=null;
 document.documentElement.classList.toggle('motion-paused',reduced);
 motionButton.textContent=reduced?'Activer les animations':'Réduire les animations';motionButton.setAttribute('aria-pressed',String(reduced));
 buildAnimations(initial);syncVideo();
}
motionButton.addEventListener('click',()=>{reduced=!reduced;preference=reduced?'reduced':'full';try{localStorage.setItem('belmain-motion',preference)}catch{}applyMotion()});
motionQuery.addEventListener('change',()=>{if(!preference){reduced=motionQuery.matches;applyMotion()}});
applyMotion(true);
if(hasGSAP)document.fonts.ready.then(()=>ScrollTrigger.refresh());
addEventListener('load',()=>{if(hasGSAP)ScrollTrigger.refresh();updateScroll()},{once:true});
})();
