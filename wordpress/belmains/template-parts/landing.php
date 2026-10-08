<?php
/** Approved landing layout with native WooCommerce cart integration. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$belmains_product = function_exists( 'belmains_commerce_product' ) ? belmains_commerce_product() : false;
$belmains_can_buy = $belmains_product && $belmains_product->is_purchasable() && $belmains_product->is_in_stock();
$belmains_cart_url = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/#fiche-produit' );
?>
<a class="skip-link" href="#main">Aller au contenu</a><div id="shopify-section-texte_defilant" class="theme-section" data-source-section="texte-defilant">

<div
  id="Marquee-texte_defilant"
  class="marquee color-background-1"
  data-marquee
  style="--marquee-duree: 15s;"
>
  <div
    class="marquee__bar"
    role="marquee"
    aria-label="Bandeau d'annonces"
  ><div class="marquee__groupe"><span
              class="marquee__item marquee__item--annonce marquee__item--annonce_1"


            ><svg class="icone icone--heart" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 20s-7-4.6-7-10a4 4 0 0 1 7-2.5A4 4 0 0 1 19 10c0 5.4-7 10-7 10z"/></svg><span class="marquee__texte"><p><strong>Le bien-être entre vos mains</strong></p></span>
            </span><span
              class="marquee__item marquee__item--annonce marquee__item--annonce_2"


            ><svg class="icone icone--truck" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M2 7h11v9H2zM13 10h4l3 3v3h-7z"/><circle cx="6" cy="18" r="1.8"/><circle cx="17" cy="18" r="1.8"/></svg><span class="marquee__texte"><p><strong>Livraison Offerte</strong></p></span>
            </span></div><div class="marquee__groupe" aria-hidden="true"><span
              class="marquee__item marquee__item--annonce marquee__item--annonce_1"


            ><svg class="icone icone--heart" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 20s-7-4.6-7-10a4 4 0 0 1 7-2.5A4 4 0 0 1 19 10c0 5.4-7 10-7 10z"/></svg><span class="marquee__texte"><p><strong>Le bien-être entre vos mains</strong></p></span>
            </span><span
              class="marquee__item marquee__item--annonce marquee__item--annonce_2"


            ><svg class="icone icone--truck" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M2 7h11v9H2zM13 10h4l3 3v3h-7z"/><circle cx="6" cy="18" r="1.8"/><circle cx="17" cy="18" r="1.8"/></svg><span class="marquee__texte"><p><strong>Livraison Offerte</strong></p></span>
            </span></div><div class="marquee__groupe" aria-hidden="true"><span
              class="marquee__item marquee__item--annonce marquee__item--annonce_1"


            ><svg class="icone icone--heart" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 20s-7-4.6-7-10a4 4 0 0 1 7-2.5A4 4 0 0 1 19 10c0 5.4-7 10-7 10z"/></svg><span class="marquee__texte"><p><strong>Le bien-être entre vos mains</strong></p></span>
            </span><span
              class="marquee__item marquee__item--annonce marquee__item--annonce_2"


            ><svg class="icone icone--truck" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M2 7h11v9H2zM13 10h4l3 3v3h-7z"/><circle cx="6" cy="18" r="1.8"/><circle cx="17" cy="18" r="1.8"/></svg><span class="marquee__texte"><p><strong>Livraison Offerte</strong></p></span>
            </span></div><div class="marquee__groupe" aria-hidden="true"><span
              class="marquee__item marquee__item--annonce marquee__item--annonce_1"


            ><svg class="icone icone--heart" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 20s-7-4.6-7-10a4 4 0 0 1 7-2.5A4 4 0 0 1 19 10c0 5.4-7 10-7 10z"/></svg><span class="marquee__texte"><p><strong>Le bien-être entre vos mains</strong></p></span>
            </span><span
              class="marquee__item marquee__item--annonce marquee__item--annonce_2"


            ><svg class="icone icone--truck" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M2 7h11v9H2zM13 10h4l3 3v3h-7z"/><circle cx="6" cy="18" r="1.8"/><circle cx="17" cy="18" r="1.8"/></svg><span class="marquee__texte"><p><strong>Livraison Offerte</strong></p></span>
            </span></div><div class="marquee__groupe" aria-hidden="true"><span
              class="marquee__item marquee__item--annonce marquee__item--annonce_1"


            ><svg class="icone icone--heart" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 20s-7-4.6-7-10a4 4 0 0 1 7-2.5A4 4 0 0 1 19 10c0 5.4-7 10-7 10z"/></svg><span class="marquee__texte"><p><strong>Le bien-être entre vos mains</strong></p></span>
            </span><span
              class="marquee__item marquee__item--annonce marquee__item--annonce_2"


            ><svg class="icone icone--truck" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M2 7h11v9H2zM13 10h4l3 3v3h-7z"/><circle cx="6" cy="18" r="1.8"/><circle cx="17" cy="18" r="1.8"/></svg><span class="marquee__texte"><p><strong>Livraison Offerte</strong></p></span>
            </span></div><div class="marquee__groupe" aria-hidden="true"><span
              class="marquee__item marquee__item--annonce marquee__item--annonce_1"


            ><svg class="icone icone--heart" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 20s-7-4.6-7-10a4 4 0 0 1 7-2.5A4 4 0 0 1 19 10c0 5.4-7 10-7 10z"/></svg><span class="marquee__texte"><p><strong>Le bien-être entre vos mains</strong></p></span>
            </span><span
              class="marquee__item marquee__item--annonce marquee__item--annonce_2"


            ><svg class="icone icone--truck" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M2 7h11v9H2zM13 10h4l3 3v3h-7z"/><circle cx="6" cy="18" r="1.8"/><circle cx="17" cy="18" r="1.8"/></svg><span class="marquee__texte"><p><strong>Livraison Offerte</strong></p></span>
            </span></div></div>
</div>




</div><div id="shopify-section-menu_kooldogy_jpEgq8" class="theme-section" data-source-section="menu-kooldogy"><header
  class="kd-menu-menu_kooldogy_jpEgq8"
  data-kd-menu
  style="
    --bg: #ffffff;
    --bg-rgb: 255, 255, 255;
    --ink: #181d25;
    --accent: #bf9756;
    --accent-rgb: 191, 151, 86;
    --opacity-scrolled: 0.7;
  "
>
  <div class="kd-menu-inner-menu_kooldogy_jpEgq8">

    <button class="kd-menu-burger-menu_kooldogy_jpEgq8" data-kd-burger aria-label="Menu" aria-expanded="false">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
        <line x1="3" y1="6" x2="21" y2="6"/>
        <line x1="3" y1="12" x2="21" y2="12"/>
        <line x1="3" y1="18" x2="21" y2="18"/>
      </svg>
    </button><ul class="kd-menu-nav-menu_kooldogy_jpEgq8"><li class="kd-menu-item-menu_kooldogy_jpEgq8">
            <a href="#accueil" class="kd-menu-link-menu_kooldogy_jpEgq8 is-active">
              Accueil</a></li><li class="kd-menu-item-menu_kooldogy_jpEgq8">
            <a href="<?php echo esc_url( belmains_contact_url() ); ?>" class="kd-menu-link-menu_kooldogy_jpEgq8">
              Contact</a></li><li class="kd-menu-item-menu_kooldogy_jpEgq8">
            <a href="<?php echo esc_url( belmains_tracking_url() ); ?>" class="kd-menu-link-menu_kooldogy_jpEgq8">
              Suivre ma commande</a></li></ul><a href="#accueil" class="kd-menu-logo-menu_kooldogy_jpEgq8" aria-label="Belmains"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/belmains-logo.png' ) ); ?>" alt="Belmains" width="719" height="183" loading="eager" fetchpriority="high"></a>

    <div class="kd-menu-actions-menu_kooldogy_jpEgq8"><a href="<?php echo esc_url( $belmains_cart_url ); ?>" class="kd-menu-icon-menu_kooldogy_jpEgq8" aria-label="Panier">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
          <line x1="3" y1="6" x2="21" y2="6"/>
          <path d="M16 10a4 4 0 0 1-8 0"/>
        </svg></a>
    </div>
  </div>
</header>

<div class="kd-drawer-overlay-menu_kooldogy_jpEgq8" data-kd-overlay></div>

<div class="kd-drawer-menu_kooldogy_jpEgq8" data-kd-drawer aria-hidden="true">
  <button class="kd-drawer-close-menu_kooldogy_jpEgq8" data-kd-close aria-label="Fermer">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
      <line x1="18" y1="6" x2="6" y2="18"/>
      <line x1="6" y1="6" x2="18" y2="18"/>
    </svg>
  </button>

  <div class="kd-drawer-logo-wrap-menu_kooldogy_jpEgq8">
    <a href="#accueil" class="kd-drawer-logo-menu_kooldogy_jpEgq8" aria-label="Belmains"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/belmains-logo.png' ) ); ?>" alt="Belmains" width="719" height="183" loading="lazy"></a>
  </div><ul class="kd-drawer-nav-menu_kooldogy_jpEgq8"><li>
          <a href="#accueil" class="kd-drawer-link-menu_kooldogy_jpEgq8">
            Accueil
          </a></li><li>
          <a href="<?php echo esc_url( belmains_contact_url() ); ?>" class="kd-drawer-link-menu_kooldogy_jpEgq8">
            Contact
          </a></li><li>
          <a href="<?php echo esc_url( belmains_tracking_url() ); ?>" class="kd-drawer-link-menu_kooldogy_jpEgq8">
            Suivre ma commande
          </a></li></ul></div>





</div><div class="announcement announcement-red" data-announcement><button aria-label="Annonce précédente" data-announcement-prev hidden>←</button><p aria-live="polite">Livraison Offerte</p><button aria-label="Annonce suivante" data-announcement-next hidden>→</button><script type="application/json" class="announcement-data">["Livraison Offerte"]</script></div><main id="main"><div id="shopify-section-hero_custom_TjqNkF" class="theme-section" data-source-section="hero-custom"><section
  class="hero-editorial"
  style="
    --bg: #ffffff;
    --ink: #181d25;
    --muted: #6b5d4e;
    --accent: #63182e;
    --btn-bg: #f8f5ee;
    --btn-text: #000000;
  "
>
  <div class="hero-ed-inner"><div class="hero-ed-left"><div class="top-badge">
          <span class="eyebrow-dash"></span>Le bien-être entre vos mains
        </div><h1 class="hero-ed-title">
          Offrez enfin à vos mains le moment de détente <em>qu'elles méritent</em>.</h1><div class="hero-ed-cta"><a href="#fiche-produit" class="btn btn-primary">
              Découvrir Belmains
            </a></div><div class="hero-ed-proof">
          <div class="proof-avatars"></div>
          <div class="proof-text">
            <div class="proof-label">5 modes de massage · 3 niveaux de chaleur</div>
          </div>
        </div></div><div class="hero-ed-right">
      <div class="single-image-wrapper">

        <div class="hero-image"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/belmains-photo-hero.jpg' ) ); ?>" alt="Offrez enfin à vos mains le moment de détente" loading="eager" fetchpriority="high" width="1448" height="1086"></div><div class="floating-badge">
            <div class="floating-badge-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="2" x2="12" y2="22"/>
                <line x1="2" y1="12" x2="22" y2="12"/>
                <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
                <line x1="19.07" y1="4.93" x2="4.93" y2="19.07"/>
                <polyline points="8,5 12,2 16,5"/>
                <polyline points="8,19 12,22 16,19"/>
                <polyline points="5,8 2,12 5,16"/>
                <polyline points="19,8 22,12 19,16"/>
              </svg>
            </div>
            <div>
              <div class="floating-badge-label">RITUEL BIEN-ÊTRE</div>
              <div class="floating-badge-title">5 modes · 3 niveaux de chaleur</div>
            </div>
          </div></div>
    </div>
  </div>
</section>




</div>
<div id="shopify-section-rich_text_kEE36c" class="theme-section" data-source-section="rich-text">



<div class="isolate">
  <div class="rich-text content-container color-scheme-ad51283d-6044-441e-b8ec-b4441c2da21b gradient rich-text--full-width content-container--full-width section-rich_text_kEE36c-padding">
    <div class="rich-text__wrapper rich-text__wrapper--center page-width">
      <div class="rich-text__blocks center"><h2
                class="rich-text__heading rte inline-richtext h2"


              >
                <em><span class="shortcut-text__fancy1">Prenez soin</span> de vos mains au <span class="shortcut-text__fancy1">quotidien</span>.</em>
              </h2></div>
    </div>
  </div>
</div>


</div>
<div id="shopify-section-image_texte_ndtUdi" class="theme-section" data-source-section="image-texte">

<section class="iwt iwt-image_texte_ndtUdi image-pos-left">
  <div class="iwt-inner"><div class="iwt-image">
      <div class="iwt-image-wrap iwt-image-wrap--adapt"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/belmains-photo-douleur.jpg' ) ); ?>" alt="Le saviez-vous ?" loading="lazy" width="1448" height="1086" class="iwt-image-tag"></div>
    </div><div class="iwt-content"><div class="eyebrow">
          <span class="eyebrow-dash"></span>Le saviez-vous ?
        </div><p class="iwt-intro">Au fil des années, vos mains sont de plus en plus sollicitées. Offrez-leur un moment de détente et de confort.</p><ul class="iwt-list"><li>
              <span class="iwt-list-check" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                  <polyline points="20 6 9 17 4 12"/>
                </svg>
              </span>
              <span>Travail manuel, téléphone, clavier, tâches quotidiennes : vos mains ne s'arrêtent jamais.</span>
            </li><li>
              <span class="iwt-list-check" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                  <polyline points="20 6 9 17 4 12"/>
                </svg>
              </span>
              <span>Le froid et l'humidité vous procurent des douleurs.</span>
            </li><li>
              <span class="iwt-list-check" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                  <polyline points="20 6 9 17 4 12"/>
                </svg>
              </span>
              <span>Les premiers signes de l'âge vous font souffrir et vous engourdissent les mains.</span>
            </li></ul><a href="#fiche-produit" class="btn btn-primary">
          Découvrir
          <svg class="btn-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <line x1="5" y1="12" x2="19" y2="12"/>
            <polyline points="12 5 19 12 12 19"/>
          </svg>
        </a></div>
  </div>
</section>




</div>
<div id="shopify-section-brands_mWCJBf" class="theme-section" data-source-section="brands">

<div class="section-brands_mWCJBf-padding color-background-1 gradient">
  <div
    class="text-marquee-brands_mWCJBf"
    role="region"
    aria-label="Belmains — Le rituel bien-être pour vos mains"
  >
    <div class="text-marquee-brands_mWCJBf__track" aria-hidden="true"><div><p class="text-marquee-brands_mWCJBf__item">
              Belmains — Le rituel bien-être pour vos mains
              <span class="text-marquee-brands_mWCJBf__separator">●</span>
            </p><p class="text-marquee-brands_mWCJBf__item">
              Belmains — Le rituel bien-être pour vos mains
              <span class="text-marquee-brands_mWCJBf__separator">●</span>
            </p><p class="text-marquee-brands_mWCJBf__item">
              Belmains — Le rituel bien-être pour vos mains
              <span class="text-marquee-brands_mWCJBf__separator">●</span>
            </p><p class="text-marquee-brands_mWCJBf__item">
              Belmains — Le rituel bien-être pour vos mains
              <span class="text-marquee-brands_mWCJBf__separator">●</span>
            </p><p class="text-marquee-brands_mWCJBf__item">
              Belmains — Le rituel bien-être pour vos mains
              <span class="text-marquee-brands_mWCJBf__separator">●</span>
            </p><p class="text-marquee-brands_mWCJBf__item">
              Belmains — Le rituel bien-être pour vos mains
              <span class="text-marquee-brands_mWCJBf__separator">●</span>
            </p></div><div><p class="text-marquee-brands_mWCJBf__item">
              Belmains — Le rituel bien-être pour vos mains
              <span class="text-marquee-brands_mWCJBf__separator">●</span>
            </p><p class="text-marquee-brands_mWCJBf__item">
              Belmains — Le rituel bien-être pour vos mains
              <span class="text-marquee-brands_mWCJBf__separator">●</span>
            </p><p class="text-marquee-brands_mWCJBf__item">
              Belmains — Le rituel bien-être pour vos mains
              <span class="text-marquee-brands_mWCJBf__separator">●</span>
            </p><p class="text-marquee-brands_mWCJBf__item">
              Belmains — Le rituel bien-être pour vos mains
              <span class="text-marquee-brands_mWCJBf__separator">●</span>
            </p><p class="text-marquee-brands_mWCJBf__item">
              Belmains — Le rituel bien-être pour vos mains
              <span class="text-marquee-brands_mWCJBf__separator">●</span>
            </p><p class="text-marquee-brands_mWCJBf__item">
              Belmains — Le rituel bien-être pour vos mains
              <span class="text-marquee-brands_mWCJBf__separator">●</span>
            </p></div></div>
  </div>
</div>


</div>
<section id="fiche-produit" class="mock-product" aria-labelledby="product-title"><div class="mock-product-grid">
 <div class="product-gallery"><div class="product-stage" tabindex="0" aria-label="Galerie du gant. Flèches gauche et droite pour changer de vue."><video id="product-film" class="product-view product-film is-active" src="<?php echo esc_url( get_theme_file_uri( 'assets/belmain-rituel.mp4' ) ); ?>" poster="<?php echo esc_url( get_theme_file_uri( 'assets/hero-film-poster.webp' ) ); ?>" aria-label="Le gant Belmains présenté en rotation" width="1280" height="720" loop muted playsinline controls preload="none"></video><img class="product-view product-marketing" src="<?php echo esc_url( get_theme_file_uri( 'assets/belmains-01-pause-bien-etre-v1.webp' ) ); ?>" alt="Offrez une pause à vos mains. Une femme profite du gant Belmains dans son fauteuil. Chaleur douce, massage enveloppant." width="1254" height="1254" loading="lazy" decoding="async" hidden><img class="product-view product-marketing" src="<?php echo esc_url( get_theme_file_uri( 'assets/belmains-02-chaleur-douce-v1.webp' ) ); ?>" alt="La chaleur, à votre mesure. Trois niveaux de chaleur pour choisir votre moment de confort." width="1254" height="1254" loading="lazy" decoding="async" hidden><img class="product-view product-marketing" src="<?php echo esc_url( get_theme_file_uri( 'assets/belmains-03-confort-fonctions-v1.webp' ) ); ?>" alt="Le confort, pensé dans les détails : 5 modes de massage, 3 niveaux de chaleur, massage par pression, recharge USB-C, utilisation sans fil et format compact." width="1254" height="1254" loading="lazy" decoding="async" hidden><img class="product-view product-marketing" src="<?php echo esc_url( get_theme_file_uri( 'assets/belmains-04-commandes-simples-v1.webp' ) ); ?>" alt="Simple à prendre en main. Les quatre commandes du gant : marche et arrêt, chaleur, modes et intensité." width="1254" height="1254" loading="lazy" decoding="async" hidden><img class="product-view product-marketing" src="<?php echo esc_url( get_theme_file_uri( 'assets/belmains-05-rituel-quotidien-v1.webp' ) ); ?>" alt="Votre moment, à votre rythme. Le gant Belmains utilisé à la maison pendant une pause lecture. Massage, chaleur et confort." width="1254" height="1254" loading="lazy" decoding="async" hidden><button data-gallery-prev aria-label="Photo précédente">←</button><button data-gallery-next aria-label="Photo suivante">→</button></div><button id="product-film-toggle" class="product-film-toggle" type="button" aria-label="Lire la vidéo du gant" hidden><span aria-hidden="true" class="product-film-icon">▶</span><span class="product-film-state">Lire</span></button><button id="gallery-expand" class="gallery-expand" type="button" hidden>Agrandir l’image ↗</button><div class="product-thumbs"><button data-gallery="0" aria-label="Vidéo du gant en rotation" aria-pressed="true"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/gant-trois-quarts-1100.webp' ) ); ?>" width="1100" height="1100" alt="" loading="lazy"></button><button data-gallery="1" aria-label="Votre pause bien-être" aria-pressed="false"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/belmains-01-pause-bien-etre-thumb-v1.webp' ) ); ?>" width="180" height="180" alt="" loading="lazy"></button><button data-gallery="2" aria-label="La chaleur à votre mesure" aria-pressed="false"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/belmains-02-chaleur-douce-thumb-v1.webp' ) ); ?>" width="180" height="180" alt="" loading="lazy"></button><button data-gallery="3" aria-label="Les atouts du gant" aria-pressed="false"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/belmains-03-confort-fonctions-thumb-v1.webp' ) ); ?>" width="180" height="180" alt="" loading="lazy"></button><button data-gallery="4" aria-label="Les commandes en un regard" aria-pressed="false"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/belmains-04-commandes-simples-thumb-v1.webp' ) ); ?>" width="180" height="180" alt="" loading="lazy"></button><button data-gallery="5" aria-label="Votre rituel quotidien" aria-pressed="false"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/belmains-05-rituel-quotidien-thumb-v1.webp' ) ); ?>" width="180" height="180" alt="" loading="lazy"></button></div><p class="gallery-label" aria-live="polite">1 / 6 · Le gant en rotation</p></div>
 <div class="product-information"><h2 id="product-title">Le gant de massage Belmains</h2><p class="product-kicker">Le rituel bien-être pour vos mains</p><p class="product-price" aria-live="polite"><span id="product-price-label">1 produit</span> <strong id="product-price">89,99 €</strong> <span class="visually-hidden">au lieu de</span> <del id="product-compare-price">109,99 €</del></p><p class="product-description">Avec Belmains, prenez soin de vos mains aujourd'hui pour continuer à profiter pleinement de chaque geste demain. Un gant de massage pensé dans les moindres détails pour le confort et le bien-être de vos mains au quotidien.</p><ul class="comfort-benefits"><li><span><svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg></span>5 modes de massage différents pour une relaxation musculaire complète.</li><li><span><svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg></span>Fonction compresse chaude avec 3 niveaux de chauffage pour soulager les tensions et favoriser la circulation sanguine.</li><li><span><svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg></span>Massage par acupression pour apaiser les douleurs des mains et des doigts.</li><li><span><svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg></span>Conception compacte : facile à transporter, à la maison comme en déplacement.</li><li><span><svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg></span>Quelques minutes suffisent pour retrouver une agréable sensation de confort et de détente.</li><li><span><svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg></span>Retrouvez le plaisir de jardiner, bricoler, cuisiner sans douleurs.</li></ul>
 <noscript><style>.product-offers,[data-qty]{display:none!important}</style><p>Saisissez le nombre de gants souhaité. Les offres seront appliquées automatiquement dans le panier.</p></noscript>
 <fieldset class="product-offers"><legend>Choisissez votre offre</legend>
 <label class="product-offer"><input type="radio" name="product-offer" value="1" checked><span><b>1 produit</b><span class="offer-prices"><strong>89,99 €</strong><span class="visually-hidden">au lieu de</span><del>109,99 €</del></span></span></label>
 <label class="product-offer"><input type="radio" name="product-offer" value="2"><span><b>2 produits</b><span class="offer-prices"><strong>149,99 €</strong><span class="visually-hidden">au lieu de</span><del>179,99 €</del></span></span></label>
 </fieldset>
 <div class="quantity-row"><label for="product-quantity">Quantité</label><div class="quantity"><button type="button" data-qty="minus" aria-label="Diminuer la quantité">−</button><input name="quantity" form="belmains-product-form" id="product-quantity" type="number" value="1" min="1" max="10" inputmode="numeric"><button type="button" data-qty="plus" aria-label="Augmenter la quantité">+</button></div></div><p class="product-finish"><span></span>Gris</p>
 <div class="reassurance-list"><div><svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 4h13v13H1zM14 9h5l4 5v3h-9M5 17a2 2 0 1 0 0 4 2 2 0 0 0 0-4M19 17a2 2 0 1 0 0 4 2 2 0 0 0 0-4"/></svg><p><strong>Livraison Offerte</strong><span>Livraison à domicile ou en point relais sous 48h/72h.</span></p></div><div><svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12 2 9 4v6c0 5-9 10-9 10S3 17 3 12V6l9-4Z"/><path d="m8 12 3 3 5-6"/></svg><p><strong>Politique de retour</strong><span>Simple, claire & sans stress</span></p></div></div>
 <div class="product-accordions"><details><summary><svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.8 4.8a5.5 5.5 0 0 0-7.8 0L12 6l-1-1.2a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.4a5.5 5.5 0 0 0 0-7.8Z"/></svg>Description<span>+</span></summary><div><p>Le gant de massage Belmains : le rituel bien-être pour vos mains.</p><p>Au fil des années, vos mains sont de plus en plus sollicitées : travail manuel, téléphone, clavier, tâches quotidiennes. Le froid, l'humidité et les premiers signes de l'âge finissent par les faire souffrir.</p><p>Belmains combine 5 modes de massage, une fonction compresse chaude à 3 niveaux et un massage par acupression pour apaiser les tensions des mains et des doigts. Compact, rechargeable et facile à transporter, il s'utilise à la maison, au bureau ou en voyage.</p></div></details><details><summary><svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.8 4.8a5.5 5.5 0 0 0-7.8 0L12 6l-1-1.2a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.4a5.5 5.5 0 0 0 0-7.8Z"/></svg>Service client<span>+</span></summary><div><p>Contactez notre service client pour toute question sur le produit ou votre commande.</p></div></details><details><summary><svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.8 4.8a5.5 5.5 0 0 0-7.8 0L12 6l-1-1.2a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.4a5.5 5.5 0 0 0 0-7.8Z"/></svg>Politique de retour<span>+</span></summary><div><p>Pour toute demande de retour, contactez notre service client afin de connaître les modalités.</p></div></details></div><button class="size-guide" data-info="sizes">Guide des tailles</button><form id="belmains-product-form" action="<?php echo esc_url( $belmains_cart_url ); ?>" method="post">
<?php if ( $belmains_product ) : ?><input type="hidden" name="add-to-cart" value="<?php echo esc_attr( $belmains_product->get_id() ); ?>"><?php endif; ?>
<button class="add-to-cart" id="add-to-cart" type="submit" <?php disabled( ! $belmains_can_buy ); ?>><?php echo $belmains_can_buy ? 'Ajouter au panier' : 'Actuellement indisponible'; ?> <span aria-hidden="true">→</span></button>
<p id="purchase-feedback" class="purchase-feedback" tabindex="-1" role="status" hidden></p>
</form><p class="purchase-note">Les offres sont appliquées automatiquement dans votre panier.</p></div></div></section>
<div id="shopify-section-rich_text_PkAFHE" class="theme-section" data-source-section="rich-text">



<div class="isolate">
  <div class="rich-text content-container color-scheme-ad51283d-6044-441e-b8ec-b4441c2da21b gradient rich-text--full-width content-container--full-width section-rich_text_PkAFHE-padding">
    <div class="rich-text__wrapper rich-text__wrapper--center page-width">
      <div class="rich-text__blocks center"><h2
                class="rich-text__heading rte inline-richtext h2"


              >
                Avec <span class="shortcut-text__fancy1">Belmains</span>, prenez soin de vos mains aujourd'hui pour continuer à profiter pleinement de chaque geste <span class="shortcut-text__fancy1">demain</span>.
              </h2></div>
    </div>
  </div>
</div>


</div>
<div id="shopify-section-image_texte_XqDidL" class="theme-section" data-source-section="image-texte">

<section class="iwt iwt-image_texte_XqDidL image-pos-left">
  <div class="iwt-inner"><div class="iwt-image">
      <div class="iwt-image-wrap iwt-image-wrap--large"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/belmains-photo-poterie.jpg' ) ); ?>" alt="Retrouvez le plaisir du geste" loading="lazy" width="1448" height="1086" class="iwt-image-tag"></div>
    </div><div class="iwt-content"><div class="eyebrow">
          <span class="eyebrow-dash"></span>Retrouvez le plaisir du geste
        </div><ul class="iwt-list"><li>
              <span class="iwt-list-check" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                  <polyline points="20 6 9 17 4 12"/>
                </svg>
              </span>
              <span>Retrouvez enfin le bonheur de pouvoir jardiner, bricoler, cuisiner sans douleurs.</span>
            </li><li>
              <span class="iwt-list-check" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                  <polyline points="20 6 9 17 4 12"/>
                </svg>
              </span>
              <span>Pouvoir à nouveau profiter de mes activités quotidiennes redevient un plaisir.</span>
            </li><li>
              <span class="iwt-list-check" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                  <polyline points="20 6 9 17 4 12"/>
                </svg>
              </span>
              <span>Quelques minutes suffisent pour retrouver une agréable sensation de confort et de détente après une longue journée.</span>
            </li></ul><a href="#fiche-produit" class="btn btn-primary">
          Découvrir
          <svg class="btn-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <line x1="5" y1="12" x2="19" y2="12"/>
            <polyline points="12 5 19 12 12 19"/>
          </svg>
        </a></div>
  </div>
</section>




</div>
<div id="shopify-section-rich_text_PqrYaB" class="theme-section" data-source-section="rich-text">



<div class="isolate">
  <div class="rich-text content-container color-scheme-ad51283d-6044-441e-b8ec-b4441c2da21b gradient rich-text--full-width content-container--full-width section-rich_text_PqrYaB-padding">
    <div class="rich-text__wrapper rich-text__wrapper--center page-width">
      <div class="rich-text__blocks center"><h2
                class="rich-text__heading rte inline-richtext h2"


              >
                Résultat : <span class="shortcut-text__fancy1">des mains détendues</span>, soulagées et prêtes pour demain.
              </h2></div>
    </div>
  </div>
</div>


</div>
<div id="shopify-section-promess_8CcEiE" class="theme-section" data-source-section="promess"><section
  class="promesses"
  style="
    --bg: #f8f5ee;
    --card-bg: #ffffff;
    --ink: #181d25;
    --muted: #181d25;
    --accent: #63182e;
  "
>
  <div class="promesses-header"><div class="eyebrow">
        <span class="eyebrow-dash"></span>Ce qui change tout
      </div><h2 class="promesses-title">
        Trois <em>promesses</em><br>tenues à la lettre.</h2></div>

  <div class="promesses-grid"><div class="promesse-card" >
        <div class="icon-wrap"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="4"/>
                <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>
              </svg></div>
        <h3>5 modes de massage</h3>
        <p>Une relaxation musculaire complète, du plus doux au plus profond.</p>
      </div><div class="promesse-card" >
        <div class="icon-wrap"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
              </svg></div>
        <h3>Compresse chaude</h3>
        <p>3 niveaux de chauffage pour détendre les tensions et favoriser la circulation.</p>
      </div><div class="promesse-card" >
        <div class="icon-wrap"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 3h15v13H1z"/>
                <path d="M16 8h4l3 3v5h-7V8z"/>
                <circle cx="5.5" cy="18.5" r="2.5"/>
                <circle cx="18.5" cy="18.5" r="2.5"/>
              </svg></div>
        <h3>Livraison Offerte</h3>
        <p>Livraison à domicile ou en point relais sous 48h/72h.</p>
      </div></div>
</section>



</div>

<div id="shopify-section-testimonial_custom_JztQEh" class="theme-section" data-source-section="testimonial-custom"><section id="avis" aria-label="Vos avis"
  class="kd-testimonials-testimonial_custom_JztQEh"
  style="
    --bg: #f8f5ee;
    --ink: #181d25;
    --muted: #6b5d4e;
    --accent: #bf9756;
    --card-bg: #ffffff;
  "
>
  <div class="kd-testimonials-inner-testimonial_custom_JztQEh"><div class="kd-testimonials-header-testimonial_custom_JztQEh"><div class="kd-testimonials-eyebrow-testimonial_custom_JztQEh">
          <span class="kd-testimonials-eyebrow-dash-testimonial_custom_JztQEh"></span>Vos avis
        </div><h2 class="kd-testimonials-title-testimonial_custom_JztQEh">
          À chacun sa pause <em>bien-être</em>.</h2></div><div class="kd-testimonials-carousel-testimonial_custom_JztQEh" data-kd-carousel>

        <div class="kd-testimonials-track-testimonial_custom_JztQEh" data-kd-track><article class="kd-testimonial-card-testimonial_custom_JztQEh" data-review-example>
 <div class="kd-testimonial-stars-testimonial_custom_JztQEh" role="img" aria-label="5 étoiles sur 5"><svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg><svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg><svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg><svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg><svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg></div>
 <blockquote class="kd-testimonial-quote-testimonial_custom_JztQEh">Le soir, j’aime prendre quelques minutes pour moi. Je choisis un mode de massage, je m’installe dans le canapé et je profite de cette petite pause.</blockquote>
 <div class="kd-testimonial-author-testimonial_custom_JztQEh">
  <div class="kd-testimonial-photo-placeholder-testimonial_custom_JztQEh" aria-hidden="true">S</div>
  <div class="kd-testimonial-meta-testimonial_custom_JztQEh"><div class="kd-testimonial-name-testimonial_custom_JztQEh">Sophie</div></div>
 </div>
</article><article class="kd-testimonial-card-testimonial_custom_JztQEh" data-review-example>
 <div class="kd-testimonial-stars-testimonial_custom_JztQEh" role="img" aria-label="5 étoiles sur 5"><svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg><svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg><svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg><svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg><svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg></div>
 <blockquote class="kd-testimonial-quote-testimonial_custom_JztQEh">J’apprécie de pouvoir choisir entre trois niveaux de chaleur. Je commence par le plus doux et j’ajuste selon mon envie, sans changer mes habitudes.</blockquote>
 <div class="kd-testimonial-author-testimonial_custom_JztQEh">
  <div class="kd-testimonial-photo-placeholder-testimonial_custom_JztQEh" aria-hidden="true">M</div>
  <div class="kd-testimonial-meta-testimonial_custom_JztQEh"><div class="kd-testimonial-name-testimonial_custom_JztQEh">Marc</div></div>
 </div>
</article><article class="kd-testimonial-card-testimonial_custom_JztQEh" data-review-example>
 <div class="kd-testimonial-stars-testimonial_custom_JztQEh" role="img" aria-label="4 étoiles sur 5"><svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg><svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg><svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg><svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg><svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg></div>
 <blockquote class="kd-testimonial-quote-testimonial_custom_JztQEh">Les quatre boutons sont faciles à repérer. Après un premier essai, je retrouve rapidement le réglage de chaleur et le mode que je souhaite utiliser.</blockquote>
 <div class="kd-testimonial-author-testimonial_custom_JztQEh">
  <div class="kd-testimonial-photo-placeholder-testimonial_custom_JztQEh" aria-hidden="true">C</div>
  <div class="kd-testimonial-meta-testimonial_custom_JztQEh"><div class="kd-testimonial-name-testimonial_custom_JztQEh">Claire</div></div>
 </div>
</article><article class="kd-testimonial-card-testimonial_custom_JztQEh" data-review-example>
 <div class="kd-testimonial-stars-testimonial_custom_JztQEh" role="img" aria-label="5 étoiles sur 5"><svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg><svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg><svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg><svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg><svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg></div>
 <blockquote class="kd-testimonial-quote-testimonial_custom_JztQEh">Je m’installe avec mon livre et le gant pendant ma pause lecture. C’est un moment agréable dans la journée, surtout quand j’ai envie de ralentir un peu.</blockquote>
 <div class="kd-testimonial-author-testimonial_custom_JztQEh">
  <div class="kd-testimonial-photo-placeholder-testimonial_custom_JztQEh" aria-hidden="true">A</div>
  <div class="kd-testimonial-meta-testimonial_custom_JztQEh"><div class="kd-testimonial-name-testimonial_custom_JztQEh">Anne</div></div>
 </div>
</article><article class="kd-testimonial-card-testimonial_custom_JztQEh" data-review-example>
 <div class="kd-testimonial-stars-testimonial_custom_JztQEh" role="img" aria-label="4 étoiles sur 5"><svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg><svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg><svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg><svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg><svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg></div>
 <blockquote class="kd-testimonial-quote-testimonial_custom_JztQEh">Le fonctionnement sans fil me permet de m’installer où j’en ai envie. Je passe facilement du fauteuil au canapé, et le gant trouve sa place près de moi.</blockquote>
 <div class="kd-testimonial-author-testimonial_custom_JztQEh">
  <div class="kd-testimonial-photo-placeholder-testimonial_custom_JztQEh" aria-hidden="true">P</div>
  <div class="kd-testimonial-meta-testimonial_custom_JztQEh"><div class="kd-testimonial-name-testimonial_custom_JztQEh">Philippe</div></div>
 </div>
</article></div><button class="kd-testimonials-nav-testimonial_custom_JztQEh kd-testimonials-prev-testimonial_custom_JztQEh" data-kd-prev aria-label="Précédent">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="15 18 9 12 15 6"/>
            </svg>
          </button>
          <button class="kd-testimonials-nav-testimonial_custom_JztQEh kd-testimonials-next-testimonial_custom_JztQEh" data-kd-next aria-label="Suivant">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="9 18 15 12 9 6"/>
            </svg>
          </button><div class="kd-testimonials-dots-testimonial_custom_JztQEh" data-kd-dots><button class="kd-testimonials-dot-testimonial_custom_JztQEh is-active" data-kd-dot="0" aria-label="Aller au témoignage 1"></button><button class="kd-testimonials-dot-testimonial_custom_JztQEh" data-kd-dot="1" aria-label="Aller au témoignage 2"></button><button class="kd-testimonials-dot-testimonial_custom_JztQEh" data-kd-dot="2" aria-label="Aller au témoignage 3"></button><button class="kd-testimonials-dot-testimonial_custom_JztQEh" data-kd-dot="3" aria-label="Aller au témoignage 4"></button><button class="kd-testimonials-dot-testimonial_custom_JztQEh" data-kd-dot="4" aria-label="Aller au témoignage 5"></button></div></div></div>
</section>





</div>
<div id="shopify-section-diaporama_belmains" class="theme-section" data-source-section="diaporama">

<div id="Diapo-diaporama_belmains" class="diapo color-background-1" data-diapo data-auto="true" data-delai="6000">
  <div class="diapo__cadre">
    <div class="diapo__piste" role="region" aria-roledescription="carrousel" aria-label="Diaporama Belmains"><article
          class="diapo__slide diapo__slide--left is-active"
          role="group"
          aria-roledescription="diapositive"
          aria-label="1 / 3"


        >
          <div class="diapo__media"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/belmains-photo-couple-douleur.jpg' ) ); ?>" alt="Tout devient compliqué." width="1448" height="1086" loading="eager"><div class="diapo__voile" aria-hidden="true"></div>
          </div><div class="diapo__contenu"><p class="diapo__surtitre">Quand les mains font mal</p><h2 class="diapo__titre">Tout devient compliqué.</h2><div class="diapo__texte"><p>Belmains, le rituel bien-être pour vos mains.</p></div><a class="diapo__bouton" href="#fiche-produit">Découvrir</a></div></article><article
          class="diapo__slide diapo__slide--left"
          role="group"
          aria-roledescription="diapositive"
          aria-label="2 / 3"
          aria-hidden="true"

        >
          <div class="diapo__media"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/belmains-photo-hero.jpg' ) ); ?>" alt="Quelques minutes de détente." width="1448" height="1086" loading="lazy"><div class="diapo__voile" aria-hidden="true"></div>
          </div><div class="diapo__contenu"><p class="diapo__surtitre">Chaque jour</p><h2 class="diapo__titre">Quelques minutes de détente.</h2><div class="diapo__texte"><p>5 modes de massage · 3 niveaux de chaleur</p></div></div></article><article
          class="diapo__slide diapo__slide--left"
          role="group"
          aria-roledescription="diapositive"
          aria-label="3 / 3"
          aria-hidden="true"

        >
          <div class="diapo__media"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/belmains-photo-poterie.jpg' ) ); ?>" alt="Jardiner, bricoler, cuisiner sans douleurs." width="1448" height="1086" loading="lazy"><div class="diapo__voile" aria-hidden="true"></div>
          </div><div class="diapo__contenu"><p class="diapo__surtitre">Retrouvez le plaisir du geste</p><h2 class="diapo__titre">Jardiner, bricoler, cuisiner sans douleurs.</h2><a class="diapo__bouton" href="#fiche-produit">Découvrir</a></div></article><button class="diapo__fleche diapo__fleche--prev" type="button" data-prev aria-label="Diapositive précédente">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
        <button class="diapo__fleche diapo__fleche--next" type="button" data-next aria-label="Diapositive suivante">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button></div><div class="diapo__nav">
        <div class="diapo__points" role="tablist"><button type="button" role="tab" data-goto="0" aria-label="Charger la diapositive 1" aria-selected="true" class="is-active"></button><button type="button" role="tab" data-goto="1" aria-label="Charger la diapositive 2"></button><button type="button" role="tab" data-goto="2" aria-label="Charger la diapositive 3"></button></div><button type="button" class="diapo__pause" data-pause aria-label="Mettre le diaporama en pause">
            <svg class="ic-pause" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5h3v14H8zM13 5h3v14h-3z" fill="currentColor"/></svg>
            <svg class="ic-play" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5l11 7-11 7z" fill="currentColor"/></svg>
          </button></div></div>
</div>






</div>
<div id="shopify-section-faq_3knxkP" class="theme-section" data-source-section="faq">

<section
  class="faq-section"
  id="faq"
  style="
    --bg: #ffffff;
    --card-bg: #f8f5ee;
    --border: #63182e;
    --ink: #181d25;
    --muted: #4a4640;
    --accent: #63182e;
  "
>
  <div class="faq-inner">

    <div class="section-header">
      <div class="eyebrow">
        <span class="eyebrow-dash"></span>Questions fréquentes
      </div>

      <h2 class="section-title">
        Questions fréquentes sur <em>Belmains</em>.
      </h2>

      <p class="section-subtitle">
        Tout ce qu'il faut savoir avant de commencer votre rituel bien-être. Une autre question ? Écrivez-nous, nous répondons sous 24h.
      </p>
    </div>

    <div class="faq-list">

        <div class="faq-item open" >
          <button class="faq-trigger" type="button" aria-expanded="true">
            Qu'est-ce que le gant de massage Belmains ?
            <span class="plus">+</span>
          </button>

          <div class="faq-body">
            <p>
              Belmains est un gant de massage conçu pour vous offrir un moment de détente et de confort pour vos mains, directement chez vous ou où que vous soyez. Il combine 5 modes de massage et 3 niveaux de chauffage afin de vous permettre de personnaliser votre expérience selon vos envies.
            </p>
          </div>
        </div>

        <div class="faq-item " >
          <button class="faq-trigger" type="button" aria-expanded="false">
            Quels sont les différents modes de massage ?
            <span class="plus">+</span>
          </button>

          <div class="faq-body">
            <p>
              Belmains propose 5 modes de massage pour varier votre expérience et choisir celui qui vous convient le mieux. Vous pouvez ainsi adapter votre séance selon votre niveau de confort et le moment de la journée. 5 modes, une seule mission : vous offrir un véritable moment de détente.
            </p>
          </div>
        </div>

        <div class="faq-item " >
          <button class="faq-trigger" type="button" aria-expanded="false">
            Peut-on régler la chaleur ?
            <span class="plus">+</span>
          </button>

          <div class="faq-body">
            <p>
              Oui, Belmains dispose de 3 niveaux de chauffage afin que vous puissiez choisir la sensation de chaleur qui vous convient le mieux. Une chaleur douce peut être particulièrement agréable lorsque vos mains ont été beaucoup sollicitées au cours de la journée.
            </p>
          </div>
        </div>

        <div class="faq-item " >
          <button class="faq-trigger" type="button" aria-expanded="false">
            Le gant est-il facile à utiliser ?
            <span class="plus">+</span>
          </button>

          <div class="faq-body">
            <p>
              Oui, Belmains a été conçu pour être simple et intuitif. Il vous suffit d'enfiler le gant, de sélectionner votre mode de massage et votre niveau de chaleur, puis de profiter de votre moment de détente.
            </p>
          </div>
        </div>

        <div class="faq-item " >
          <button class="faq-trigger" type="button" aria-expanded="false">
            Le gant de massage Belmains est-il facile à transporter ?
            <span class="plus">+</span>
          </button>

          <div class="faq-body">
            <p>
              Absolument. Grâce à son format pratique, votre gant de massage peut facilement vous accompagner dans vos déplacements. À la maison, au bureau ou en voyage, vous pouvez emporter votre moment de bien-être avec vous. Votre rituel de détente ne reste plus à la maison.
            </p>
          </div>
        </div>

        <div class="faq-item " >
          <button class="faq-trigger" type="button" aria-expanded="false">
            Comment recharger mon gant de massage ?
            <span class="plus">+</span>
          </button>

          <div class="faq-body">
            <p>
              Rechargez votre gant en suivant les instructions de la notice fournie avec l’appareil. Vous y trouverez les indications de branchement et les conditions d’utilisation.
            </p>
          </div>
        </div>

        <div class="faq-item " >
          <button class="faq-trigger" type="button" aria-expanded="false">
            Pourquoi utiliser le gant de massage Belmains après une journée de travail ?
            <span class="plus">+</span>
          </button>

          <div class="faq-body">
            <p>
              Nos mains sont constamment sollicitées : clavier, téléphone, conduite, gestes répétitifs, travail manuel. Après une journée bien remplie, offrir à vos mains quelques minutes de détente peut devenir un véritable rituel de bien-être.
            </p>
          </div>
        </div>

        <div class="faq-item " >
          <button class="faq-trigger" type="button" aria-expanded="false">
            Peut-on utiliser le gant de massage Belmains tous les jours ?
            <span class="plus">+</span>
          </button>

          <div class="faq-body">
            <p>
              Le gant de massage Belmains peut s'intégrer à votre routine quotidienne de détente. Belmains s'adresse à toutes les personnes qui souhaitent prendre soin de leurs mains et s'accorder régulièrement un moment de relaxation. Il peut notamment être apprécié par les personnes dont les mains sont fortement sollicitées au quotidien par le travail, l'utilisation d'un ordinateur, du téléphone ou les activités manuelles.
            </p>
          </div>
        </div>

        <div class="faq-item " >
          <button class="faq-trigger" type="button" aria-expanded="false">
            Est-ce une bonne idée comme cadeau ?
            <span class="plus">+</span>
          </button>

          <div class="faq-body">
            <p>
              Oui, le gant de massage Belmains peut être une idée cadeau originale pour une personne qui travaille beaucoup avec ses mains ou qui apprécie les moments de détente à la maison. Un cadeau utile, pratique et pensé pour le bien-être.
            </p>
          </div>
        </div>

    </div>

  </div>
</section>





</div></main><div id="shopify-section-waves_ptbQNq" class="theme-section" data-source-section="waves">

<div class="section-waves_ptbQNq-padding" style="position:relative;"><div class="ocean-waves_ptbQNq">
  <div class="wave"></div>
  <div class="wave"></div>
  <div class="wave"></div>
</div>
</div>

</div><footer class="mock-footer" id="contact"><div class="footer-grid"><a href="#accueil" class="footer-logo"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/belmains-logo.png' ) ); ?>" alt="Belmains — Le bien-être entre vos mains" width="719" height="183" loading="lazy"></a><div><h2>Liens rapides</h2><a href="#faq">Questions fréquentes</a><button data-info="delivery">Livraison et retours</button><button data-info="legal">Mentions légales</button></div><div><h2>Boutique</h2><a href="#accueil">Accueil</a><a href="#fiche-produit">Le gant Belmains</a><a href="<?php echo esc_url( belmains_tracking_url() ); ?>">Suivre ma commande</a></div><div><h2>Contact</h2><p>Belmains — Le bien-être entre vos mains.<br>Une question ? Notre service client est à votre écoute.</p><a class="contact-button" href="<?php echo esc_url( belmains_contact_url() ); ?>">Nous contacter ↗</a></div></div><div class="footer-bottom"><span>France · EUR € &nbsp; / &nbsp; Français</span><p>© <?php echo esc_html( wp_date( 'Y' ) ); ?> Belmains · NOK’S Consulting</p></div><p class="footer-credit">NOK’S Consulting pour le compte de Belmains</p><div class="prototype-tools"><span>Maquette de présentation · Avis de démonstration</span><button id="motion-toggle" aria-pressed="false">Mettre les animations en pause</button></div></footer><dialog class="info-dialog" id="info-dialog" aria-labelledby="info-title"><div class="dialog-top"><h2 id="info-title"></h2><button data-close aria-label="Fermer">×</button></div><div id="info-content"></div></dialog><dialog id="gallery-dialog" class="gallery-dialog" aria-labelledby="gallery-dialog-title">
 <div class="dialog-top"><h2 id="gallery-dialog-title">Le gant Belmains en détail</h2><button data-close aria-label="Fermer l’image agrandie">×</button></div>
 <p id="gallery-dialog-caption"></p>
 <div class="gallery-zoom-scroll"><button id="gallery-zoom-toggle" type="button" aria-label="Zoomer sur l’image"><img id="gallery-dialog-image" alt="" width="1254" height="1254"></button></div>
 <p class="gallery-zoom-hint">Touchez l’image pour zoomer.</p>
</dialog>
