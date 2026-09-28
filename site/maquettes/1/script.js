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
new IntersectionObserver(entries=>{videoInView=entries[0].isIntersecting;syncVideo()},{threshold:.1}).observe($('.hero-cinema'));
document.addEventListener('visibilitychange',syncVideo);
// Responsive choreography: horizontal story on desktop, naturally stacked on mobile.
if(hasGSAP)gsap.registerPlugin(ScrollTrigger);
function buildAnimations(initial=false){
 if(!hasGSAP||reduced)return;
 motionContext=gsap.matchMedia();
 motionContext.add({desktop:'(min-width:901px)',mobile:'(max-width:900px)'},context=>{
  const desktop=context.conditions.desktop;
  if(initial&&scrollY<120){
   gsap.timeline({defaults:{ease:'power3.out'}})
   .from('.hero-copy > *',{y:32,opacity:0,duration:.95,stagger:.1},.05)
   .from('.hero-media',{scale:1.08,opacity:0,duration:1.5},.1)
   .from('.hero-memory, .hero-signoff',{y:20,opacity:0,duration:.8},.7);
  }
  gsap.to('.hero-video',{scale:1.1,y:desktop?30:12,ease:'none',scrollTrigger:{trigger:'.hero',start:'top top',end:'bottom top',scrub:1.1}});
  gsap.to('.hero-copy',{y:desktop?-45:-20,ease:'none',scrollTrigger:{trigger:'.hero',start:'top top',end:'bottom top',scrub:1}});
  gsap.from('.reassurance > div',{y:25,opacity:0,stagger:.13,duration:.8,scrollTrigger:{trigger:'.reassurance',start:'top 92%',once:true}});
  gsap.from('.care-section .section-heading',{y:45,opacity:.1,duration:1,ease:'power3.out',scrollTrigger:{trigger:'.care-section',start:'top 80%',once:true}});
  gsap.fromTo('.care-photo',{scale:1.18,y:25},{scale:1,y:-16,ease:'none',scrollTrigger:{trigger:'.care-photo-wrap',start:'top bottom',end:'bottom top',scrub:1}});
  $$('.care-point').forEach((el,i)=>gsap.from(el,{x:desktop?40:0,y:desktop?0:28,opacity:0,duration:.8,delay:i*.04,scrollTrigger:{trigger:el,start:'top 88%',once:true}}));
  let pin=$('.ritual-pin');
  if(desktop){
   pin.classList.add('is-horizontal');
   const track=$('.ritual-track');
   const story=gsap.to(track,{x:()=>-($('.ritual-window').clientWidth*2),ease:'none',scrollTrigger:{
    trigger:pin,start:'top 88px',end:()=>'+='+Math.max(innerWidth*1.5,1600),pin:true,scrub:1.1,invalidateOnRefresh:true,
    onUpdate:self=>{$$('.ritual-dots i').forEach((d,i)=>d.classList.toggle('active',i===Math.round(self.progress*2)))}
   }});
   $$('.ritual-panel').forEach((panel,i)=>{
    if(i>0)gsap.from($('.ritual-copy',panel),{y:35,opacity:0,duration:.8,scrollTrigger:{trigger:panel,containerAnimation:story,start:'left 75%',toggleActions:'play none none reverse'}});
   });
   gsap.fromTo('.ritual-product',{rotation:-16,scale:.88},{rotation:6,scale:1.08,ease:'none',scrollTrigger:{trigger:pin,start:'top bottom',end:'bottom top',scrub:1}});
  }else{
   $$('.ritual-panel').forEach(panel=>{
    gsap.from($('.ritual-visual',panel),{scale:.94,y:30,opacity:.2,duration:.95,ease:'power3.out',scrollTrigger:{trigger:panel,start:'top 85%',once:true}});
    gsap.from($('.ritual-copy',panel),{y:30,opacity:0,duration:.8,scrollTrigger:{trigger:$('.ritual-copy',panel),start:'top 90%',once:true}});
   });
   gsap.to('.ritual-product',{rotation:8,y:-20,ease:'none',scrollTrigger:{trigger:'.product-scene',start:'top bottom',end:'bottom top',scrub:1}});
  }
  gsap.from('.viewer-main',{y:45,opacity:.3,duration:1,ease:'power3.out',scrollTrigger:{trigger:'.viewer',start:'top 88%',once:true}});
  gsap.from('.view-thumb',{y:18,opacity:0,stagger:.06,duration:.6,scrollTrigger:{trigger:'.view-thumbnails',start:'top 93%',once:true}});
  gsap.from('.purchase-copy > *',{y:25,opacity:0,stagger:.07,duration:.75,scrollTrigger:{trigger:'.purchase-copy',start:'top 86%',once:true}});
  gsap.fromTo('.everyday-photo img',{scale:1.16,y:25},{scale:1,y:-12,ease:'none',scrollTrigger:{trigger:'.everyday-section',start:'top bottom',end:'bottom top',scrub:1.2}});
  gsap.from('.everyday-copy > *',{y:35,opacity:0,stagger:.1,duration:.8,scrollTrigger:{trigger:'.everyday-copy',start:'top 85%',once:true}});
  gsap.from('.faq-list details',{y:25,opacity:0,stagger:.09,duration:.7,scrollTrigger:{trigger:'.faq-list',start:'top 90%',once:true}});
  return()=>{pin.classList.remove('is-horizontal')};
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

