/* ============================================================
   MOTEUR BELMAINS — injecte la fiche produit (window.PRODUIT)
   dans la structure de index.html.
   Ne modifie ni le style ni la structure : il ne fait que
   remplacer les textes et les photos.
   ============================================================ */
(function(){
  var M = window.Moteur = {};

  /* ---------- utilitaires texte ---------- */
  function esc(s){
    return String(s==null?'':s).replace(/[&<>"']/g,function(c){
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
    });
  }
  /* typographie française : espaces insécables avant ? ! : ; » et entre chiffres */
  function typo(s){
    return s
      .replace(/ ([?!:;»%])/g,'\u00a0$1')
      .replace(/« /g,'«\u00a0')
      .replace(/(\d) (\d{3})(?!\d)/g,'$1\u00a0$2')
      .replace(/(\d) (€|min|h|j|%)\b/g,'$1\u00a0$2');
  }
  /* mise en forme légère : **gras**, *italique*, retours à la ligne */
  function fmt(s){
    var t = esc(typo(String(s==null?'':s)));
    t = t.replace(/\*\*(.+?)\*\*/g,'<b>$1</b>');
    t = t.replace(/\*(.+?)\*/g,'<em>$1</em>');
    return t.replace(/\n/g,'<br>');
  }
  /* paragraphes séparés par une ligne vide */
  function paras(s){
    return String(s==null?'':s).split(/\n\s*\n/).filter(function(p){return p.trim();})
      .map(function(p){return '<p>'+fmt(p.trim())+'</p>';}).join('');
  }
  function get(obj,path,def){
    var cur=obj; path.split('.').forEach(function(k){ cur = (cur==null)?undefined:cur[k]; });
    return cur==null?def:cur;
  }
  function $(sel,root){ return (root||document).querySelector(sel); }
  function $$(sel,root){ return [].slice.call((root||document).querySelectorAll(sel)); }

  /* ---------- photos ---------- */
  /* el = bloc .photo ; ph = {src, alt, note} ; titre = libellé du placeholder */
  function photo(el, ph, titre){
    if(!el) return;
    ph = ph || {};
    if(ph.src){
      el.classList.add('has');
      el.innerHTML = '<img src="'+esc(ph.src)+'" alt="'+esc(ph.alt||'')+'">';
      el.setAttribute('aria-label', ph.alt||'');
    }else{
      el.classList.remove('has');
      el.innerHTML = '<span class="cam" aria-hidden="true">✦</span><b>'+esc(titre||'Photo à fournir')+'</b>'+fmt(ph.note||'');
      el.setAttribute('aria-label', (titre||'Photo à fournir')+(ph.alt?' : '+ph.alt:''));
    }
  }
  function miniature(el, v){
    if(!el) return;
    v = v || {};
    if(v.src){
      el.classList.add('has');
      el.innerHTML = '<img src="'+esc(v.src)+'" alt="'+esc(v.alt||v.titre||'')+'">';
    }else{
      el.classList.remove('has');
      el.innerHTML = '<b>'+esc(v.titre||'')+'</b>'+fmt(v.note||'');
    }
  }

  /* ---------- SEO (title, meta, JSON-LD) ---------- */
  function meta(attr, name, content){
    var el = $('meta['+attr+'="'+name+'"]');
    if(!el){ el=document.createElement('meta'); el.setAttribute(attr,name); document.head.appendChild(el); }
    el.setAttribute('content', content||'');
  }
  function seo(p){
    var titre = get(p,'seo.titre','') || p.nom || document.title;
    document.title = titre;
    var desc = get(p,'seo.description','');
    meta('name','description',desc);
    meta('property','og:title',titre);
    meta('property','og:description',desc);
    var si = get(p,'seo.image','');
    var img = (si && si.src) || (typeof si==='string' ? si : '') || get(p,'fiche.photo.src','');
    if(img) meta('property','og:image',img);

    var ldP = $('#ldProduit');
    if(ldP){
      var offre = {"@type":"Offer","priceCurrency":"EUR","availability":"https://schema.org/InStock",
        "shippingDetails":{"@type":"OfferShippingDetails","shippingRate":{"@type":"MonetaryAmount","value":"0","currency":"EUR"}}};
      if(get(p,'seo.prix','')) offre.price = String(get(p,'seo.prix',''));
      var prod = {"@context":"https://schema.org","@type":"Product","name":p.nom||'',
        "brand":{"@type":"Brand","name":"Belmains"},"description":desc};
      if(img) prod.image=[img];
      if(offre.price) prod.offers=offre;
      ldP.textContent = JSON.stringify(prod);
    }
    var ldF = $('#ldFaq');
    if(ldF){
      ldF.textContent = JSON.stringify({"@context":"https://schema.org","@type":"FAQPage",
        "mainEntity": (get(p,'faq.questions',[])).filter(function(q){return q.question&&q.reponse;}).map(function(q){
          return {"@type":"Question","name":q.question,"acceptedAnswer":{"@type":"Answer","text":q.reponse.replace(/\*\*?/g,'')}};
        })});
    }
  }

  /* ---------- remplissage ---------- */
  function remplir(p){
    if(!p) return;
    M.produit = p;

    /* textes simples : <el data-t="chemin.de.la.cle"> */
    $$('[data-t]').forEach(function(el){
      var v = get(p, el.getAttribute('data-t'), null);
      if(v==null) return;
      el.innerHTML = fmt(v);
    });
    /* liens : <a data-href="chemin"> */
    $$('[data-href]').forEach(function(el){
      var v = get(p, el.getAttribute('data-href'), null);
      if(v) el.setAttribute('href', v);
    });

    /* HERO : titre ligne par ligne */
    var h1 = $('#heroTitre');
    if(h1){
      h1.innerHTML = String(get(p,'hero.titre','')).split('\n').filter(function(l){return l.trim();})
        .map(function(l){ return '<span class="hline"><span>'+fmt(l)+'</span></span>'; }).join('');
    }
    var sceau = $('#sceauTxt'); if(sceau) sceau.textContent = get(p,'hero.sceau','');
    photo($('#heroPhoto'), get(p,'hero.photo',{}));
    var past = get(p,'hero.pastilles',[]);
    $$('.hero .chip').forEach(function(c,i){
      var d = past[i]; if(!d){ c.style.display='none'; return; }
      c.style.display='';
      c.innerHTML = '<small>'+fmt(d.petit)+'</small><b>'+fmt(d.grand)+'</b>';
    });

    /* bandeaux défilants */
    var txt = get(p,'defilant','');
    $$('.mq-track').forEach(function(el){
      var items=''; for(var i=0;i<6;i++) items += '<span class="mq-item">'+fmt(txt)+' <i>✦</i></span>';
      el.innerHTML = items+items; /* piste doublée pour la boucle -50% */
    });

    /* POURQUOI */
    photo($('#pourquoiPhoto'), get(p,'pourquoi.photo',{}));
    var pains = $('#pourquoiPoints');
    if(pains) pains.innerHTML = get(p,'pourquoi.points',[]).map(function(t){return '<li>'+fmt(t)+'</li>';}).join('');

    /* CHIFFRES */
    var ch = get(p,'chiffres',[]);
    $$('.stats .stat').forEach(function(s,i){
      var d = ch[i]; if(!d){ s.style.display='none'; return; }
      s.style.display='';
      var val = Number(d.valeur)||0;
      s.innerHTML = '<b><span data-count="'+val+'">'+val.toLocaleString('fr-FR').replace(/\s/g,'\u00a0')+'</span>'+
        (d.suffixe?'<i>'+esc(d.suffixe)+'</i>':'')+'</b><span>'+fmt(d.legende)+'</span>';
    });

    /* VUE 360 */
    var frames = get(p,'vue360.photos',[]).filter(Boolean);
    M.frames360 = frames;
    var stage = $('#turnPhoto');
    if(stage){
      if(frames.length){
        stage.classList.add('has');
        stage.innerHTML = '<img id="turnImg" src="'+esc(frames[0])+'" alt="'+esc(p.nom||'')+' — vue à 360°">';
        stage.setAttribute('aria-label', (p.nom||'Produit')+' en rotation à 360 degrés');
      }else{
        stage.classList.remove('has');
        stage.innerHTML = '<span class="cam" aria-hidden="true">✦</span><b>Séquence 360° — photo <span id="turnNum">1</span>\u00a0/\u00a012</b>'+fmt(get(p,'vue360.note',''));
        stage.setAttribute('aria-label','Emplacement de la séquence 360 degrés : douze photos du produit à fournir');
      }
    }

    /* FICHE PRODUIT */
    photo($('#fichePhoto'), get(p,'fiche.photo',{}));
    var vig = get(p,'fiche.vignettes',[]);
    $$('#ficheVignettes .photo').forEach(function(el,i){ miniature(el, vig[i]||{}); el.setAttribute('data-src', (vig[i]&&vig[i].src)||''); });
    var atouts = $('#ficheAtouts');
    if(atouts) atouts.innerHTML = get(p,'fiche.atouts',[]).map(function(t){return '<li>'+fmt(t)+'</li>';}).join('');
    var lots = get(p,'fiche.lots',[]);
    $$('.lots .lot').forEach(function(b,i){
      var d = lots[i]; if(!d){ b.style.display='none'; return; }
      b.style.display='';
      b.innerHTML = '<span class="rad" aria-hidden="true"></span><span>'+fmt(d.nom)+(d.etiquette?' <em>'+fmt(d.etiquette)+'</em>':'')+'</span>'+
        '<small>'+fmt(d.remise)+'</small><b>'+fmt(d.prix)+'</b>';
    });
    var gar = get(p,'fiche.garanties',[]);
    $$('.reas > div').forEach(function(el,i){
      var d = gar[i]; if(!d){ el.style.display='none'; return; }
      el.style.display='';
      el.innerHTML = '<b>'+fmt(d.titre)+'</b><span>'+fmt(d.texte)+'</span>';
    });
    var ong = get(p,'fiche.onglets',[]);
    $$('.tabs details').forEach(function(el,i){
      var d = ong[i]; if(!d){ el.style.display='none'; return; }
      el.style.display='';
      el.innerHTML = '<summary>'+fmt(d.titre)+'</summary><div>'+paras(d.texte)+'</div>';
    });

    /* PROMESSES */
    var cartes = get(p,'promesses.cartes',[]);
    $$('.cards .card').forEach(function(el,i){
      var d = cartes[i]; if(!d){ el.style.display='none'; return; }
      el.style.display='';
      el.innerHTML = '<i aria-hidden="true">'+esc(d.icone||'')+'</i><h3>'+fmt(d.titre)+'</h3><p>'+fmt(d.texte)+'</p>';
    });

    /* AVIS */
    var n = Number(get(p,'avis.nombre',0))||0;
    var hAvis = $('#avisTitre');
    if(hAvis) hAvis.innerHTML = '+<span data-count="'+n+'">'+n.toLocaleString('fr-FR').replace(/\s/g,'\u00a0')+'</span> '+fmt(get(p,'avis.titre',''));
    var avis = get(p,'avis.liste',[]);
    $$('.t-cards .t-card').forEach(function(el,i){
      var d = avis[i]; if(!d){ el.style.display='none'; return; }
      el.style.display='';
      if(d.texte && d.texte.trim()){
        el.innerHTML = '<span class="stars" aria-hidden="true">★★★★★</span><blockquote>«\u00a0'+fmt(d.texte)+'\u00a0»</blockquote>'+
          '<footer><b>'+fmt(d.nom)+'</b><span>'+fmt(d.ville)+'</span></footer>';
      }else{
        el.innerHTML = '<span class="stars" aria-hidden="true">★★★★★</span>'+
          '<span class="todo">Avis client réel à insérer — ne pas publier de témoignage inventé</span>'+
          '<blockquote>«\u00a0…\u00a0»</blockquote><footer><b>Prénom N.</b><span>Ville</span></footer>';
      }
    });

    /* FAQ */
    var faq = $('#faqListe');
    if(faq) faq.innerHTML = get(p,'faq.questions',[]).filter(function(q){return q.question;}).map(function(q){
      return '<details><summary>'+fmt(q.question)+'</summary><div>'+paras(q.reponse)+'</div></details>';
    }).join('');

    seo(p);
    document.documentElement.setAttribute('data-produit', p.slug||'');
  }
  M.remplir = remplir;

  /* ---------- démarrage ---------- */
  if(window.PRODUIT){
    remplir(window.PRODUIT);
  }else{
    var b = document.createElement('div');
    b.style.cssText = 'position:fixed;left:0;right:0;bottom:0;z-index:999;background:#a8121f;color:#fff;padding:12px 18px;font:500 14px/1.4 system-ui,sans-serif;text-align:center';
    b.textContent = 'Fiche produit introuvable : le fichier produits/'+(window.PRODUIT_SLUG||'?')+'.js n\'existe pas. Vérifiez produit.js.';
    document.body.appendChild(b);
  }

  /* mode aperçu : l'éditeur envoie la fiche en direct */
  addEventListener('message', function(e){
    if(e.data && e.data.type==='belmains:produit' && e.data.produit){
      window.PRODUIT = e.data.produit;
      remplir(e.data.produit);
    }
  });
})();
