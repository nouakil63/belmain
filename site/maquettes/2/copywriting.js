/* Local interactions for the shared reference content. */
(()=>{
 const slides=[...document.querySelectorAll('.copy-daily-slide')],section=document.querySelector('.everyday-section');let index=0;
 function show(n){index=(n+slides.length)%slides.length;slides.forEach((s,i)=>{s.hidden=i!==index;s.querySelector('h2').id='daily-title-'+i});section.setAttribute('aria-labelledby','daily-title-'+index);document.querySelector('[data-daily-status]').textContent=(index+1)+' / '+slides.length;if(window.ScrollTrigger)ScrollTrigger.refresh()}
 document.querySelector('[data-daily-prev]').addEventListener('click',()=>show(index-1));document.querySelector('[data-daily-next]').addEventListener('click',()=>show(index+1));
 const track=document.querySelector('.copy-review-track');function move(direction){const reduced=matchMedia('(prefers-reduced-motion:reduce)').matches||document.documentElement.classList.contains('motion-paused');track.scrollBy({left:direction*(track.querySelector('article').getBoundingClientRect().width+22),behavior:reduced?'instant':'smooth'})}
 document.querySelector('[data-review-prev]').addEventListener('click',()=>move(-1));document.querySelector('[data-review-next]').addEventListener('click',()=>move(1));
 document.querySelectorAll('.copy-product-details details').forEach(d=>d.addEventListener('toggle',()=>{if(window.ScrollTrigger)ScrollTrigger.refresh()}));
})();
