/* Local demonstration: no Shopify endpoints, remote forms or real payment. */
(()=>{'use strict';
const $=(s,r=document)=>r.querySelector(s),$$=(s,r=document)=>[...r.querySelectorAll(s)];
const media=matchMedia('(prefers-reduced-motion: reduce)');let reduced=media.matches,motionContext,quantity=1,cartQuantity=0;
const header=$('[data-kd-menu]'),drawer=$('[data-kd-drawer]'),overlay=$('[data-kd-overlay]'),burger=$('[data-kd-burger]');
drawer.inert=true;drawer.setAttribute('role','dialog');drawer.setAttribute('aria-label','Navigation');drawer.id='mobile-menu';burger.setAttribute('aria-controls','mobile-menu');let menuFocus;
function menu(open){
 if(open)menuFocus=document.activeElement;
 drawer.classList.toggle('is-open',open);overlay.classList.toggle('is-open',open);drawer.inert=!open;drawer.setAttribute('aria-hidden',String(!open));drawer.setAttribute('aria-modal',String(open));burger.setAttribute('aria-expanded',String(open));document.documentElement.classList.toggle('menu-open',open);
 if(open)$('[data-kd-close]').focus();else menuFocus?.focus();
}
burger.addEventListener('click',()=>menu(!drawer.classList.contains('is-open')));$('[data-kd-close]').addEventListener('click',()=>menu(false));overlay.addEventListener('click',()=>menu(false));$$('a',drawer).forEach(a=>a.addEventListener('click',()=>menu(false)));
document.addEventListener('keydown',e=>{if(!drawer.classList.contains('is-open'))return;if(e.key==='Escape')menu(false);if(e.key==='Tab'){const focus=$$('a[href],button',drawer).filter(x=>x.getClientRects().length);if(e.shiftKey&&document.activeElement===focus[0]){e.preventDefault();focus.at(-1).focus()}else if(!e.shiftKey&&document.activeElement===focus.at(-1)){e.preventDefault();focus[0].focus()}}});
let queued=false;function scrollState(){header.classList.toggle('is-stuck',scrollY>45);queued=false}addEventListener('scroll',()=>{if(!queued){queued=true;requestAnimationFrame(scrollState)}},{passive:true});scrollState();
addEventListener('resize',()=>{if(innerWidth>1100&&drawer.classList.contains('is-open'))menu(false)});
const views=$$('.product-view'),names=['Le gant en rotation','Vue de face','Profil gauche','Vue de dos','Profil droit','Vue du dessus','Vue de l’ouverture'];let active=0;
function gallery(n){active=(n+views.length)%views.length;views.forEach((img,i)=>{img.hidden=i!==active;img.classList.toggle('is-active',i===active)});$$('[data-gallery]').forEach((b,i)=>b.setAttribute('aria-pressed',String(i===active)));$('.gallery-label').textContent=names[active];syncProductFilm();if(!reduced&&window.gsap)gsap.fromTo(views[active],{opacity:.3,x:12},{opacity:1,x:0,duration:.4,overwrite:true})}
// Reuse the film from the first proposal, only while its gallery view is visible.
const productFilm=$('#product-film'),filmToggle=$('#product-film-toggle');
const saveData=!!navigator.connection?.saveData;
let filmInView=false,filmChoice=null;
function productFilmState(){
 const playing=!productFilm.paused;
 filmToggle.setAttribute('aria-label',playing?'Mettre la vidéo du gant en pause':'Lire la vidéo du gant');
 $('.product-film-icon').textContent=playing?'Ⅱ':'▶';
 $('.product-film-state').textContent=playing?'Pause':'Lire';
}
function syncProductFilm(){
 filmToggle.hidden=active!==0;
 const shouldPlay=active===0&&filmInView&&!document.hidden&&(filmChoice===true||(filmChoice!==false&&!reduced&&!saveData));
 if(shouldPlay){productFilm.play().then(productFilmState).catch(productFilmState)}
 else{productFilm.pause();productFilmState()}
}
productFilm.controls=false;filmToggle.hidden=false;
productFilm.addEventListener('play',productFilmState);
productFilm.addEventListener('pause',productFilmState);
filmToggle.addEventListener('click',()=>{filmChoice=productFilm.paused;syncProductFilm()});
new IntersectionObserver(entries=>{filmInView=entries[0].isIntersecting;syncProductFilm()},{threshold:.2}).observe($('.product-stage'));
document.addEventListener('visibilitychange',syncProductFilm);

$$('[data-gallery]').forEach(b=>b.addEventListener('click',()=>gallery(+b.dataset.gallery)));$('[data-gallery-prev]').addEventListener('click',()=>gallery(active-1));$('[data-gallery-next]').addEventListener('click',()=>gallery(active+1));
$('.product-stage').addEventListener('keydown',e=>{const action={ArrowLeft:active-1,ArrowRight:active+1,Home:0,End:views.length-1};if(e.key in action){e.preventDefault();gallery(action[e.key])}});
let start;$('.product-stage').addEventListener('pointerdown',e=>{start={x:e.clientX,y:e.clientY}});$('.product-stage').addEventListener('pointerup',e=>{if(start){const dx=e.clientX-start.x,dy=e.clientY-start.y;if(Math.abs(dx)>40&&Math.abs(dx)>Math.abs(dy))gallery(active+(dx<0?1:-1));start=null}});$('.product-stage').addEventListener('pointercancel',()=>{start=null});
// Prices are calculated in cents; each complete pair uses the two-product offer.
const money = cents => new Intl.NumberFormat('fr-FR',{style:'currency',currency:'EUR'}).format(cents/100);
function pricing(count){return {current:Math.floor(count/2)*14999+(count%2)*8999,previous:Math.floor(count/2)*17999+(count%2)*10999}}
function setQty(n){
 quantity=Math.max(1,Math.min(10,Math.floor(Number(n)||1)));
 $('#product-quantity').value=quantity;
 $('[data-qty=minus]').disabled=quantity===1;
 $('[data-qty=plus]').disabled=quantity===10;
 $$('[name="product-offer"]').forEach(option=>option.checked=+option.value===quantity);
 const price=pricing(quantity);
 $('#product-price-label').textContent=quantity+' produit'+(quantity>1?'s':'');
 $('#product-price').textContent=money(price.current);
 $('#product-compare-price').textContent=money(price.previous);
}
$$('[name="product-offer"]').forEach(option=>option.addEventListener('change',()=>setQty(option.value)));
$$('[data-qty]').forEach(b=>b.addEventListener('click',()=>setQty(quantity+(b.dataset.qty==='plus'?1:-1))));$('#product-quantity').addEventListener('change',e=>setQty(e.target.value));setQty(1);
const cart=$('#cart-dialog'),info=$('#info-dialog');
function showDialog(dialog){dialog.showModal();document.body.style.overflow='hidden'}
$$('dialog').forEach(d=>{d.addEventListener('close',()=>{document.body.style.overflow=''});d.addEventListener('click',e=>{if(e.target===d){const r=d.getBoundingClientRect();if(e.clientX<r.left||e.clientX>r.right||e.clientY<r.top||e.clientY>r.bottom)d.close()}});$$('[data-close]',d).forEach(b=>b.addEventListener('click',()=>d.close()))});
function updateCart(){cartQuantity=Math.max(0,Math.min(99,cartQuantity));$('#cart-empty').hidden=cartQuantity>0;$('#cart-item').hidden=cartQuantity===0;$('#cart-quantity').textContent=cartQuantity;const price=pricing(cartQuantity);$('#cart-total-price').textContent=money(price.current);$('#cart-compare-price').textContent=money(price.previous);$('[data-cart-change="-1"]').disabled=cartQuantity===0;$('[data-cart-change="1"]').disabled=cartQuantity===99;$('[data-cart-count]').textContent=cartQuantity;$$('a[href="#panier"]').forEach(a=>{let badge=$('.cart-counter',a);if(!badge){badge=document.createElement('span');badge.className='cart-counter';a.append(badge)}badge.textContent=cartQuantity;badge.hidden=!cartQuantity;a.setAttribute('aria-label','Panier, '+cartQuantity+' article'+(cartQuantity>1?'s':''))})}
$('#add-to-cart').addEventListener('click',()=>{setQty($('#product-quantity').value);cartQuantity+=quantity;updateCart();$('#checkout-feedback').hidden=true;showDialog(cart)});
$$('a[href="#panier"]').forEach(a=>a.addEventListener('click',e=>{e.preventDefault();updateCart();showDialog(cart)}));$('#cart-remove').addEventListener('click',()=>{cartQuantity=0;updateCart()});$$('[data-cart-change]').forEach(b=>b.addEventListener('click',()=>{cartQuantity+=+b.dataset.cartChange;updateCart()}));$('#checkout-demo').addEventListener('click',()=>{$('#checkout-feedback').hidden=false});updateCart();
const content={
 sizes:['Guide des tailles','<p>Les dimensions et les indications de taille ne sont pas renseignées dans le thème fourni.</p><p>Le guide définitif sera intégré à la fiche produit de la boutique WordPress.</p>'],
 delivery:['Livraison et retours','<p>Livraison Offerte. Livraison à domicile ou en point relais sous 48h/72h.</p><p>Pour toute demande de retour, contactez notre service client afin de connaître les modalités.</p><p>Les informations détaillées du vendeur seront intégrées à la boutique définitive.</p>'],
 legal:['Mentions légales','<p>Les coordonnées légales du vendeur seront ajoutées à la version définitive de la boutique.</p><p>Cette page est une maquette HTML de présentation.</p>'],
 contact:['Contacter Belmains','<p>Une question ? Écrivez-nous, nous répondons sous 24h.</p><form class="preview-form" data-preview="contact"><label>Votre nom<input name="name" autocomplete="name" required></label><label>Votre e-mail<input name="email" type="email" autocomplete="email" required></label><label>Votre message<textarea name="message" required></textarea></label><button class="add-to-cart" type="submit">Envoyer le message</button><small>Formulaire de démonstration : aucun message n’est envoyé.</small><p class="form-feedback" role="status" hidden></p></form>'],
 tracking:['Suivre ma commande','<p>Retrouvez les informations de votre commande.</p><form class="preview-form" data-preview="tracking"><label>Numéro de commande<input name="order" placeholder="Ex. BM1001" required></label><label>E-mail utilisé pour la commande<input name="email" type="email" autocomplete="email" required></label><button class="add-to-cart" type="submit">Consulter le suivi</button><small>Aperçu du suivi : aucune donnée n’est transmise.</small><p class="form-feedback" role="status" hidden></p></form>']};
function openInfo(key){const [title,html]=content[key];$('#info-title').textContent=title;$('#info-content').innerHTML=html;const form=$('form',info);if(form)form.addEventListener('submit',e=>{e.preventDefault();const message=$('.form-feedback',form);message.textContent=key==='contact'?'Aperçu validé. Aucun message n’a été envoyé. Le formulaire sera relié au service client sur WordPress.':'Le suivi sera disponible une fois la boutique WordPress connectée au transporteur.';message.hidden=false});showDialog(info)}
$$('[data-info]').forEach(b=>b.addEventListener('click',()=>openInfo(b.dataset.info)));$$('a[href="#suivi"]').forEach(a=>a.addEventListener('click',e=>{e.preventDefault();openInfo('tracking')}));
$$('.faq-item').forEach((item,i)=>{const b=$('.faq-trigger',item),body=$('.faq-body',item);body.id='faq-answer-'+i;b.setAttribute('aria-controls',body.id);body.inert=!item.classList.contains('open');function size(){body.style.maxHeight=item.classList.contains('open')?body.scrollHeight+'px':'0px'}size();b.addEventListener('click',()=>{const open=!item.classList.contains('open');item.classList.toggle('open',open);b.setAttribute('aria-expanded',String(open));body.inert=!open;size()});new ResizeObserver(size).observe($('p',body))});
const track=$('[data-kd-track]'),cards=$$('article',track),dots=$$('[data-kd-dot]');
function cardWidth(){return cards[0].getBoundingClientRect().width+24}function testimonial(n){track.scrollTo({left:Math.max(0,n)*cardWidth(),behavior:reduced?'instant':'smooth'})}
$('[data-kd-prev]').addEventListener('click',()=>testimonial(Math.round(track.scrollLeft/cardWidth())-1));$('[data-kd-next]').addEventListener('click',()=>testimonial(Math.round(track.scrollLeft/cardWidth())+1));dots.forEach((b,i)=>b.addEventListener('click',()=>testimonial(i)));track.addEventListener('scroll',()=>{const i=Math.round(track.scrollLeft/cardWidth());dots.forEach((b,j)=>{b.classList.toggle('is-active',i===j);b.setAttribute('aria-pressed',String(i===j))})},{passive:true});
const diapo=$('[data-diapo]'),photos=$$('.diapo__slide',diapo),photoDots=$$('[data-goto]',diapo);let photoIndex=0,paused=false,visible=false,hover=false,focus=false,timer;
function photoGo(n){photoIndex=(n+photos.length)%photos.length;photos.forEach((s,i)=>{const active=i===photoIndex;s.classList.toggle('is-active',active);s.setAttribute('aria-hidden',String(!active));s.inert=!active});photoDots.forEach((b,i)=>{b.classList.toggle('is-active',i===photoIndex);b.setAttribute('aria-selected',String(i===photoIndex));b.tabIndex=i===photoIndex?0:-1})}
function autoplay(){clearInterval(timer);diapo.dataset.paused=String(paused||reduced);$('[data-pause]',diapo).disabled=reduced;$('[data-pause]',diapo).setAttribute('aria-label',paused||reduced?'Lire le diaporama':'Mettre le diaporama en pause');if(!paused&&!reduced&&visible&&!hover&&!focus&&!document.hidden)timer=setInterval(()=>photoGo(photoIndex+1),6000)}
$('[data-prev]',diapo).addEventListener('click',()=>{photoGo(photoIndex-1);autoplay()});$('[data-next]',diapo).addEventListener('click',()=>{photoGo(photoIndex+1);autoplay()});photoDots.forEach((b,i)=>{b.addEventListener('click',()=>{photoGo(i);autoplay()});b.addEventListener('keydown',e=>{if(e.key==='ArrowRight'||e.key==='ArrowLeft'){e.preventDefault();photoGo(photoIndex+(e.key==='ArrowRight'?1:-1));photoDots[photoIndex].focus();autoplay()}})});$('[data-pause]',diapo).addEventListener('click',()=>{paused=!paused;if(reduced)paused=true;autoplay()});diapo.addEventListener('mouseenter',()=>{hover=true;autoplay()});diapo.addEventListener('mouseleave',()=>{hover=false;autoplay()});diapo.addEventListener('focusin',()=>{focus=true;autoplay()});diapo.addEventListener('focusout',e=>{focus=diapo.contains(e.relatedTarget);autoplay()});new IntersectionObserver(entries=>{visible=entries[0].isIntersecting;autoplay()},{threshold:.2}).observe(diapo);document.addEventListener('visibilitychange',autoplay);photoGo(0);
function animations(){motionContext?.revert();if(reduced)filmChoice=null;syncProductFilm();document.documentElement.classList.toggle('motion-paused',reduced);$('#motion-toggle').textContent=reduced?'Activer les animations':'Mettre les animations en pause';$('#motion-toggle').setAttribute('aria-pressed',String(reduced));autoplay();if(reduced||!window.gsap)return;gsap.registerPlugin(ScrollTrigger);motionContext=gsap.context(()=>{if(scrollY<50){gsap.from('.hero-ed-left > *',{opacity:0,y:20,duration:.85,stagger:.1,ease:'power2.out'});gsap.from('.hero-ed-right',{opacity:0,y:24,duration:1,delay:.15})}gsap.fromTo('.hero-image img',{scale:1.045},{scale:1,yPercent:1.5,ease:'none',scrollTrigger:{trigger:'.hero-editorial',start:'top top',end:'bottom top',scrub:1.3}});
$$('.iwt-image-tag').forEach(el=>gsap.fromTo(el,{scale:1.06},{scale:1,ease:'none',scrollTrigger:{trigger:el.closest('.iwt-image-wrap')||el,start:'top bottom',end:'bottom top',scrub:1.2}}));
$$('.product-gallery,.promesses-title,.faq-section .section-title').forEach(el=>gsap.from(el,{opacity:0,y:26,duration:.9,ease:'power3.out',scrollTrigger:{trigger:el,start:'top 93%',once:true}}));
$$('.iwt-image,.iwt-content,.promess-card,.promesse-card,.rich-text__heading').forEach(el=>gsap.from(el,{opacity:0,y:24,duration:.8,ease:'power2.out',scrollTrigger:{trigger:el,start:'top 94%',once:true}}))});document.fonts.ready.then(()=>ScrollTrigger.refresh())}
$('#motion-toggle').addEventListener('click',()=>{reduced=!reduced;animations()});media.addEventListener('change',()=>{reduced=media.matches;animations()});animations();
})();
