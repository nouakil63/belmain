<?php
/**
 * Source unique de vérité du builder Belmains.
 *
 * Chaque champ : label, type (text|textarea|rich|title|image|color|select|number|product|sortable),
 * default, et transport ('postMessage' = mise à jour instantanée de l'aperçu, 'refresh' = rechargement).
 * Les types text/rich/title acceptent *italique accentué* et **gras** ; textarea = une entrée par ligne.
 */
defined( 'ABSPATH' ) || exit;

function bm_fields(): array {
	static $fields = null;
	if ( null !== $fields ) {
		return $fields;
	}

	$fields = array(

		'bm_general' => array(
			'title'  => '1 · Général (marque, bandeau, menu)',
			'fields' => array(
				'brand_part1'      => array( 'label' => 'Nom de marque — début (noir)', 'type' => 'text', 'default' => 'Bel' ),
				'brand_part2'      => array( 'label' => 'Nom de marque — fin (rouge)', 'type' => 'text', 'default' => 'mains' ),
				'annbar_text'      => array( 'label' => 'Bandeau d\'annonce', 'type' => 'text', 'default' => 'Livraison offerte en France · Retour gratuit sous 30 jours' ),
				'marquee_text'     => array( 'label' => 'Texte défilant', 'type' => 'text', 'default' => 'Belmains — le rituel bien-être pour vos mains' ),
				'header_locale'    => array( 'label' => 'Indication langue/devise', 'type' => 'text', 'default' => 'FR · EUR €' ),
				'cart_label'       => array( 'label' => 'Libellé panier', 'type' => 'text', 'default' => 'Panier' ),
				'menu_1_label'     => array( 'label' => 'Menu 1 — libellé', 'type' => 'text', 'default' => 'Accueil' ),
				'menu_1_link'      => array( 'label' => 'Menu 1 — lien', 'type' => 'text', 'default' => '#top', 'transport' => 'refresh' ),
				'menu_2_label'     => array( 'label' => 'Menu 2 — libellé', 'type' => 'text', 'default' => 'Le gant' ),
				'menu_2_link'      => array( 'label' => 'Menu 2 — lien', 'type' => 'text', 'default' => '#produit', 'transport' => 'refresh' ),
				'menu_3_label'     => array( 'label' => 'Menu 3 — libellé', 'type' => 'text', 'default' => 'Avis' ),
				'menu_3_link'      => array( 'label' => 'Menu 3 — lien', 'type' => 'text', 'default' => '#avis', 'transport' => 'refresh' ),
				'menu_4_label'     => array( 'label' => 'Menu 4 — libellé', 'type' => 'text', 'default' => 'FAQ' ),
				'menu_4_link'      => array( 'label' => 'Menu 4 — lien', 'type' => 'text', 'default' => '#faq', 'transport' => 'refresh' ),
				'seo_title'        => array( 'label' => 'Titre SEO de la page d\'accueil', 'type' => 'text', 'default' => 'Gant de massage Belmains — Le rituel bien-être pour vos mains', 'transport' => 'refresh' ),
				'seo_description'  => array( 'label' => 'Meta description', 'type' => 'textarea', 'default' => 'Gant de massage des mains Belmains : 5 modes de massage, compresse chaude 3 niveaux, acupression. Compact, rechargeable en 15 min. Livraison offerte, retour gratuit 30 jours.', 'transport' => 'refresh' ),
			),
		),

		'bm_sections' => array(
			'title'  => '2 · Sections (ordre & affichage)',
			'fields' => array(
				'sections_order' => array(
					'label'     => 'Glissez pour réordonner, décochez pour masquer',
					'type'      => 'sortable',
					'default'   => 'hero:1,marquee:1,why:1,turn:1,product:1,marquee2:1,promises:1,reviews:1,faq:1,cta:1',
					'transport' => 'refresh',
					'choices'   => array(
						'hero'     => 'Héro (accroche)',
						'marquee'  => 'Texte défilant',
						'why'      => 'Pourquoi + chiffres clés',
						'turn'     => 'Vue 360°',
						'product'  => 'Fiche produit',
						'marquee2' => 'Texte défilant (2)',
						'promises' => 'Promesses',
						'reviews'  => 'Avis clients',
						'faq'      => 'FAQ',
						'cta'      => 'Appel à l\'action final',
					),
				),
			),
		),

		'bm_hero' => array(
			'title'  => '3 · Héro (accroche)',
			'fields' => array(
				'hero_eyebrow'     => array( 'label' => 'Sur-titre', 'type' => 'text', 'default' => 'Le bien-être entre vos mains' ),
				'hero_title'       => array( 'label' => 'Titre (une ligne par ligne animée, *mot* = accent rouge)', 'type' => 'title', 'default' => "Offrez à vos mains\nle *rituel* qu'elles\nméritent." ),
				'hero_intro'       => array( 'label' => 'Introduction', 'type' => 'rich', 'default' => '5 modes de massage, compresse chaude à 3 niveaux et acupression, réunis dans un gant compact. Quelques minutes suffisent pour retrouver des mains détendues, soulagées, prêtes pour demain.' ),
				'hero_cta1_text'   => array( 'label' => 'Bouton principal — texte', 'type' => 'text', 'default' => 'Découvrir Belmains' ),
				'hero_cta1_link'   => array( 'label' => 'Bouton principal — lien', 'type' => 'text', 'default' => '#produit', 'transport' => 'refresh' ),
				'hero_cta2_text'   => array( 'label' => 'Bouton secondaire — texte', 'type' => 'text', 'default' => 'Pourquoi un rituel ?' ),
				'hero_cta2_link'   => array( 'label' => 'Bouton secondaire — lien', 'type' => 'text', 'default' => '#pourquoi', 'transport' => 'refresh' ),
				'hero_proof'       => array( 'label' => 'Preuve sociale (sous les boutons)', 'type' => 'rich', 'default' => '**+8 000 clients satisfaits**' ),
				'hero_image'       => array( 'label' => 'Photo principale (portrait 4:5)', 'type' => 'image', 'default' => '', 'transport' => 'refresh', 'placeholder' => "femme portant le gant de massage,\nlumière chaude, plan poitrine" ),
				'hero_chip1_small' => array( 'label' => 'Étiquette 1 — sur-titre', 'type' => 'text', 'default' => 'Rituel bien-être' ),
				'hero_chip1_text'  => array( 'label' => 'Étiquette 1 — texte', 'type' => 'text', 'default' => '5 modes de massage' ),
				'hero_chip2_small' => array( 'label' => 'Étiquette 2 — sur-titre', 'type' => 'text', 'default' => 'Compresse chaude' ),
				'hero_chip2_text'  => array( 'label' => 'Étiquette 2 — texte', 'type' => 'text', 'default' => '3 niveaux de chaleur' ),
				'hero_seal_text'   => array( 'label' => 'Texte du sceau tournant', 'type' => 'text', 'default' => 'Belmains · le rituel bien-être des mains ·', 'transport' => 'refresh' ),
			),
		),

		'bm_why' => array(
			'title'  => '4 · Pourquoi + chiffres clés',
			'fields' => array(
				'why_eyebrow'  => array( 'label' => 'Sur-titre', 'type' => 'text', 'default' => 'Le saviez-vous ?' ),
				'why_title'    => array( 'label' => 'Titre', 'type' => 'rich', 'default' => 'Vos mains ne s\'arrêtent *jamais*.' ),
				'why_intro'    => array( 'label' => 'Introduction', 'type' => 'rich', 'default' => 'Au fil des années, vos mains sont de plus en plus sollicitées. Offrez-leur enfin un moment de détente et de confort.' ),
				'why_pains'    => array( 'label' => 'Points de douleur (un par ligne)', 'type' => 'textarea', 'default' => "Travail manuel, téléphone, clavier, tâches quotidiennes : elles ne connaissent pas de pause.\nLe froid et l'humidité vous procurent des douleurs.\nLes premiers signes de l'âge vous engourdissent les mains.", 'transport' => 'refresh' ),
				'why_cta_text' => array( 'label' => 'Bouton — texte', 'type' => 'text', 'default' => 'Découvrir le gant' ),
				'why_cta_link' => array( 'label' => 'Bouton — lien', 'type' => 'text', 'default' => '#produit', 'transport' => 'refresh' ),
				'why_image'    => array( 'label' => 'Photo (portrait 4:5)', 'type' => 'image', 'default' => '', 'transport' => 'refresh', 'placeholder' => "mains fatiguées / sollicitées,\nambiance douce" ),
				'stat1_num'    => array( 'label' => 'Chiffre 1', 'type' => 'text', 'default' => '5' ),
				'stat1_suffix' => array( 'label' => 'Chiffre 1 — unité', 'type' => 'text', 'default' => '' ),
				'stat1_label'  => array( 'label' => 'Chiffre 1 — légende', 'type' => 'text', 'default' => 'modes de massage' ),
				'stat2_num'    => array( 'label' => 'Chiffre 2', 'type' => 'text', 'default' => '3' ),
				'stat2_suffix' => array( 'label' => 'Chiffre 2 — unité', 'type' => 'text', 'default' => '' ),
				'stat2_label'  => array( 'label' => 'Chiffre 2 — légende', 'type' => 'text', 'default' => 'niveaux de chaleur' ),
				'stat3_num'    => array( 'label' => 'Chiffre 3', 'type' => 'text', 'default' => '15' ),
				'stat3_suffix' => array( 'label' => 'Chiffre 3 — unité', 'type' => 'text', 'default' => 'min' ),
				'stat3_label'  => array( 'label' => 'Chiffre 3 — légende', 'type' => 'text', 'default' => 'de recharge' ),
				'stat4_num'    => array( 'label' => 'Chiffre 4', 'type' => 'text', 'default' => '30' ),
				'stat4_suffix' => array( 'label' => 'Chiffre 4 — unité', 'type' => 'text', 'default' => 'j' ),
				'stat4_label'  => array( 'label' => 'Chiffre 4 — légende', 'type' => 'text', 'default' => 'retour gratuit' ),
			),
		),

		'bm_turn' => array(
			'title'  => '5 · Vue 360°',
			'fields' => array_merge(
				array(
					'turn_eyebrow' => array( 'label' => 'Sur-titre', 'type' => 'text', 'default' => 'Sous tous les angles' ),
					'turn_title'   => array( 'label' => 'Titre', 'type' => 'rich', 'default' => 'Le gant, en *360°*.' ),
					'turn_hint'    => array( 'label' => 'Indication', 'type' => 'text', 'default' => 'Faites défiler : le gant tourne avec vous' ),
				),
				bm_repeat_fields( 12, function ( $i ) {
					return array( "turn_img_$i" => array( 'label' => "Vue $i / 12 (" . ( ( $i - 1 ) * 30 ) . '°)', 'type' => 'image', 'default' => '', 'transport' => 'refresh' ) );
				} )
			),
		),

		'bm_product' => array(
			'title'  => '6 · Fiche produit & lots',
			'fields' => array_merge(
				array(
					'product_id'      => array( 'label' => 'Produit WooCommerce vendu', 'type' => 'product', 'default' => '', 'transport' => 'refresh', 'description' => 'Nom, prix, photos et bouton panier suivent ce produit. Les champs ci-dessous permettent de surcharger.' ),
					'product_eyebrow' => array( 'label' => 'Sur-titre', 'type' => 'text', 'default' => 'Le rituel bien-être pour vos mains' ),
					'product_title'   => array( 'label' => 'Titre (vide = nom du produit)', 'type' => 'rich', 'default' => '', 'transport' => 'refresh' ),
					'product_proof'   => array( 'label' => 'Mention avis', 'type' => 'rich', 'default' => 'Avis clients à venir' ),
					'product_price'   => array( 'label' => 'Prix affiché (vide = prix WooCommerce)', 'type' => 'text', 'default' => '', 'transport' => 'refresh' ),
					'product_compare' => array( 'label' => 'Prix barré (vide = prix normal si promo)', 'type' => 'text', 'default' => '', 'transport' => 'refresh' ),
					'product_badge'   => array( 'label' => 'Badge (vide = % de remise auto)', 'type' => 'text', 'default' => '', 'transport' => 'refresh' ),
					'product_intro'   => array( 'label' => 'Introduction', 'type' => 'rich', 'default' => 'Avec Belmains, prenez soin de vos mains aujourd\'hui pour continuer à profiter pleinement de chaque geste demain. Un gant de massage pensé dans les moindres détails pour le confort et le bien-être de vos mains au quotidien.' ),
					'product_checks'  => array( 'label' => 'Arguments (un par ligne, **gras** possible)', 'type' => 'textarea', 'default' => "**5 modes de massage** pour une relaxation musculaire complète.\n**Compresse chaude, 3 niveaux** pour soulager les tensions et favoriser la circulation sanguine.\n**Massage par acupression** pour apaiser les douleurs des mains et des doigts.\n**Compact et rechargeable en 15 minutes** : à la maison comme en déplacement.\nRetrouvez le plaisir de **jardiner, bricoler, cuisiner** sans douleurs.", 'transport' => 'refresh' ),
					'product_img_main'=> array( 'label' => 'Photo principale (vide = image du produit)', 'type' => 'image', 'default' => '', 'transport' => 'refresh', 'placeholder' => "packshot gant de massage Belmains,\nfond ivoire, ombre douce" ),
					'product_img_2'   => array( 'label' => 'Vignette 2 (vide = galerie produit)', 'type' => 'image', 'default' => '', 'transport' => 'refresh', 'placeholder' => 'profil' ),
					'product_img_3'   => array( 'label' => 'Vignette 3', 'type' => 'image', 'default' => '', 'transport' => 'refresh', 'placeholder' => 'intérieur' ),
					'product_img_4'   => array( 'label' => 'Vignette 4', 'type' => 'image', 'default' => '', 'transport' => 'refresh', 'placeholder' => 'en situation' ),
					'lots_title'      => array( 'label' => 'Titre des lots', 'type' => 'text', 'default' => 'Lots & économies' ),
					'lots_show'       => array( 'label' => 'Afficher les lots', 'type' => 'select', 'default' => '1', 'choices' => array( '1' => 'Oui', '0' => 'Non' ), 'transport' => 'refresh' ),
				),
				bm_repeat_fields( 3, function ( $i ) {
					return array(
						"lot{$i}_product" => array( 'label' => "Lot $i — produit WooCommerce", 'type' => 'product', 'default' => '', 'transport' => 'refresh', 'description' => 1 === $i ? 'Vide = produit principal.' : 'Créez un produit « Lot de ' . $i . ' » dans WooCommerce et sélectionnez-le ici.' ),
						"lot{$i}_label"   => array( 'label' => "Lot $i — libellé", 'type' => 'text', 'default' => "Lot de $i" ),
						"lot{$i}_tag"     => array( 'label' => "Lot $i — mention", 'type' => 'text', 'default' => 1 === $i ? 'Le plus populaire' : '' ),
					);
				} ),
				array(
					'product_cta_text' => array( 'label' => 'Bouton panier — texte', 'type' => 'text', 'default' => 'Ajouter au panier' ),
					'pay_text'         => array( 'label' => 'Moyens de paiement', 'type' => 'text', 'default' => 'Visa · Mastercard · PayPal · Apple Pay · Google Pay' ),
				),
				bm_repeat_fields( 3, function ( $i ) {
					$d = array(
						1 => array( 'Expédition suivie', 'Expédition sécurisée avec numéro de suivi.' ),
						2 => array( 'Retour gratuit', 'Sous 30 jours, sans justification.' ),
						3 => array( 'Service client FR', 'Basé en France, réponse sous 24 h.' ),
					);
					return array(
						"reas{$i}_title" => array( 'label' => "Réassurance $i — titre", 'type' => 'text', 'default' => $d[ $i ][0] ),
						"reas{$i}_text"  => array( 'label' => "Réassurance $i — texte", 'type' => 'text', 'default' => $d[ $i ][1] ),
					);
				} ),
				bm_repeat_fields( 3, function ( $i ) {
					$d = array(
						1 => array( 'Description', "Le gant de massage Belmains : le rituel bien-être pour vos mains.\n\nAu fil des années, vos mains sont de plus en plus sollicitées : travail manuel, téléphone, clavier, tâches quotidiennes. Le froid, l'humidité et les premiers signes de l'âge finissent par les faire souffrir.\n\nBelmains combine 5 modes de massage, une fonction compresse chaude à 3 niveaux et un massage par acupression pour apaiser les tensions des mains et des doigts. Compact, rechargeable en 15 minutes et facile à transporter, il s'utilise à la maison, au bureau ou en voyage." ),
						2 => array( 'Service client', 'Service client basé en France, rapide et facilement accessible.' ),
						3 => array( 'Politique de retour', 'Retour gratuit sous 30 jours. Simple, claire & sans stress.' ),
					);
					return array(
						"tab{$i}_title"   => array( 'label' => "Onglet $i — titre", 'type' => 'text', 'default' => $d[ $i ][0] ),
						"tab{$i}_content" => array( 'label' => "Onglet $i — contenu (ligne vide = paragraphe)", 'type' => 'textarea', 'default' => $d[ $i ][1], 'transport' => 'refresh' ),
					);
				} )
			),
		),

		'bm_promises' => array(
			'title'  => '7 · Promesses',
			'fields' => array_merge(
				array(
					'promises_eyebrow' => array( 'label' => 'Sur-titre', 'type' => 'text', 'default' => 'Ce qui change tout' ),
					'promises_title'   => array( 'label' => 'Titre', 'type' => 'rich', 'default' => 'Quatre promesses, *tenues à la lettre*.' ),
				),
				bm_repeat_fields( 4, function ( $i ) {
					$d = array(
						1 => array( '☀', '5 modes de massage', 'Une relaxation musculaire complète, du plus doux au plus profond.' ),
						2 => array( '♥', 'Compresse chaude', '3 niveaux de chauffage pour détendre les tensions et favoriser la circulation.' ),
						3 => array( '✓', 'Retour sous 30 jours', 'Retour gratuit sous 30 jours, sans justification.' ),
						4 => array( '⛟', 'Livraison offerte', 'Livraison à domicile en France avec suivi en temps réel.' ),
					);
					return array(
						"promise{$i}_icon"  => array( 'label' => "Promesse $i — icône (caractère)", 'type' => 'text', 'default' => $d[ $i ][0] ),
						"promise{$i}_title" => array( 'label' => "Promesse $i — titre", 'type' => 'text', 'default' => $d[ $i ][1] ),
						"promise{$i}_text"  => array( 'label' => "Promesse $i — texte", 'type' => 'rich', 'default' => $d[ $i ][2] ),
					);
				} )
			),
		),

		'bm_reviews' => array(
			'title'  => '8 · Avis clients',
			'fields' => array_merge(
				array(
					'reviews_eyebrow' => array( 'label' => 'Sur-titre', 'type' => 'text', 'default' => 'Ils nous font confiance' ),
					'reviews_title'   => array( 'label' => 'Titre', 'type' => 'rich', 'default' => '+8 000 clients *satisfaits*' ),
					'reviews_intro'   => array( 'label' => 'Introduction', 'type' => 'rich', 'default' => 'Découvrez les retours de nos clients qui ont adopté le rituel bien-être Belmains.' ),
				),
				bm_repeat_fields( 3, function ( $i ) {
					return array(
						"review{$i}_quote" => array( 'label' => "Avis $i — citation (vide = emplacement « à insérer »)", 'type' => 'textarea', 'default' => '', 'transport' => 'refresh' ),
						"review{$i}_name"  => array( 'label' => "Avis $i — prénom", 'type' => 'text', 'default' => 'Prénom N.' ),
						"review{$i}_city"  => array( 'label' => "Avis $i — ville", 'type' => 'text', 'default' => 'Ville' ),
					);
				} )
			),
		),

		'bm_faq' => array(
			'title'  => '9 · FAQ',
			'fields' => array_merge(
				array(
					'faq_eyebrow'  => array( 'label' => 'Sur-titre', 'type' => 'text', 'default' => 'Questions fréquentes' ),
					'faq_title'    => array( 'label' => 'Titre', 'type' => 'rich', 'default' => 'Tout savoir sur *Belmains*.' ),
					'faq_intro'    => array( 'label' => 'Introduction', 'type' => 'rich', 'default' => 'Tout ce qu\'il faut savoir avant de commencer votre rituel bien-être. Une autre question ? Écrivez-nous, nous répondons sous 24 h.' ),
					'faq_cta_text' => array( 'label' => 'Bouton — texte', 'type' => 'text', 'default' => 'Nous écrire' ),
					'faq_cta_link' => array( 'label' => 'Bouton — lien', 'type' => 'text', 'default' => 'mailto:contact@belmains.fr', 'transport' => 'refresh' ),
				),
				bm_repeat_fields( 6, function ( $i ) {
					$d = array(
						1 => array( 'Qu\'est-ce que le gant de massage Belmains ?', 'Belmains est un gant de massage conçu pour vous offrir un moment de détente et de confort pour vos mains, directement chez vous ou où que vous soyez. Il combine 5 modes de massage et 3 niveaux de chauffage afin de vous permettre de personnaliser votre expérience selon vos envies.' ),
						2 => array( 'Quels sont les différents modes de massage ?', 'Belmains propose 5 modes de massage pour varier votre expérience et choisir celui qui vous convient le mieux. Vous pouvez ainsi adapter votre séance selon votre niveau de confort et le moment de la journée. 5 modes, une seule mission : vous offrir un véritable moment de détente.' ),
						3 => array( 'Peut-on régler la chaleur ?', 'Oui, Belmains dispose de 3 niveaux de chauffage afin que vous puissiez choisir la sensation de chaleur qui vous convient le mieux. Une chaleur douce peut être particulièrement agréable lorsque vos mains ont été beaucoup sollicitées au cours de la journée.' ),
						4 => array( 'Le gant est-il facile à utiliser ?', 'Oui, Belmains a été conçu pour être simple et intuitif. Il vous suffit d\'enfiler le gant, de sélectionner votre mode de massage et votre niveau de chaleur, puis de profiter de votre moment de détente.' ),
						5 => array( 'Le gant est-il facile à transporter ?', 'Absolument. Grâce à son format pratique, votre gant de massage peut facilement vous accompagner dans vos déplacements. À la maison, au bureau ou en voyage, vous pouvez emporter votre moment de bien-être avec vous.' ),
						6 => array( 'Combien de temps faut-il pour le recharger ?', 'Le gant de massage Belmains est conçu pour être rechargé en seulement 15 minutes. Une recharge rapide et pratique pour être toujours prêt.' ),
					);
					return array(
						"faq{$i}_q" => array( 'label' => "Question $i", 'type' => 'text', 'default' => $d[ $i ][0], 'transport' => 'refresh' ),
						"faq{$i}_a" => array( 'label' => "Réponse $i", 'type' => 'textarea', 'default' => $d[ $i ][1], 'transport' => 'refresh' ),
					);
				} )
			),
		),

		'bm_cta' => array(
			'title'  => '10 · Appel à l\'action final',
			'fields' => array(
				'cta_title'       => array( 'label' => 'Titre', 'type' => 'rich', 'default' => 'Retrouvez le *plaisir du geste*.' ),
				'cta_text'        => array( 'label' => 'Texte', 'type' => 'rich', 'default' => 'Jardiner, bricoler, cuisiner sans douleurs — profitez pleinement de chaque geste, aujourd\'hui comme demain.' ),
				'cta_button_text' => array( 'label' => 'Bouton — texte', 'type' => 'text', 'default' => 'Commencer mon rituel' ),
				'cta_button_link' => array( 'label' => 'Bouton — lien', 'type' => 'text', 'default' => '#produit', 'transport' => 'refresh' ),
			),
		),

		'bm_footer' => array(
			'title'  => '11 · Pied de page',
			'fields' => array(
				'footer_tagline'    => array( 'label' => 'Phrase de marque', 'type' => 'rich', 'default' => "Le bien-être entre vos mains.\nDes mains détendues, soulagées et prêtes pour demain." ),
				'footer_col2_title' => array( 'label' => 'Colonne 2 — titre', 'type' => 'text', 'default' => 'Boutique' ),
				'footer_col2_links' => array( 'label' => 'Colonne 2 — liens (Libellé | URL, un par ligne)', 'type' => 'textarea', 'default' => "Le gant de massage | #produit\nLots & économies | #produit\nAvis clients | #avis\nMon compte | {compte}", 'transport' => 'refresh' ),
				'footer_col3_title' => array( 'label' => 'Colonne 3 — titre', 'type' => 'text', 'default' => 'Aide' ),
				'footer_col3_links' => array( 'label' => 'Colonne 3 — liens', 'type' => 'textarea', 'default' => "FAQ | #faq\nSuivre ma commande | {commandes}\nContact | mailto:contact@belmains.fr\nRetours & remboursements | {retours}", 'transport' => 'refresh', 'description' => 'Jetons disponibles : {boutique} {panier} {commande} {compte} {commandes} {retours} {produit}.' ),
				'footer_nl_title'   => array( 'label' => 'Newsletter — titre', 'type' => 'text', 'default' => 'Newsletter' ),
				'footer_nl_text'    => array( 'label' => 'Newsletter — texte', 'type' => 'text', 'default' => 'Recevez nos conseils bien-être et nos offres.' ),
				'footer_nl_action'  => array( 'label' => 'Newsletter — URL du formulaire (vide = masqué en prod)', 'type' => 'text', 'default' => '', 'transport' => 'refresh', 'description' => 'URL d\'action Mailchimp / Brevo / Klaviyo.' ),
				'footer_copyright'  => array( 'label' => 'Copyright', 'type' => 'text', 'default' => '© Belmains — tous droits réservés' ),
				'footer_legal'      => array( 'label' => 'Liens légaux (Libellé | URL, un par ligne)', 'type' => 'textarea', 'default' => "Mentions légales | /mentions-legales/\nCGV | /cgv/\nConfidentialité | /confidentialite/", 'transport' => 'refresh' ),
			),
		),

		'bm_colors' => array(
			'title'  => '12 · Couleurs',
			'fields' => array(
				'color_rouge'     => array( 'label' => 'Rouge (accent, boutons)', 'type' => 'color', 'default' => '#a8121f', 'var' => '--rouge' ),
				'color_rouge_f'   => array( 'label' => 'Rouge foncé (survol)', 'type' => 'color', 'default' => '#7d0c16', 'var' => '--rouge-f' ),
				'color_noir'      => array( 'label' => 'Noir (texte, fonds sombres)', 'type' => 'color', 'default' => '#181d25', 'var' => '--noir' ),
				'color_or'        => array( 'label' => 'Or', 'type' => 'color', 'default' => '#bf9756', 'var' => '--or' ),
				'color_or_c'      => array( 'label' => 'Or clair', 'type' => 'color', 'default' => '#d4b06a', 'var' => '--or-c' ),
				'color_or_pale'   => array( 'label' => 'Or pâle', 'type' => 'color', 'default' => '#e9dcc3', 'var' => '--or-pale' ),
				'color_ivoire'    => array( 'label' => 'Ivoire (fonds clairs)', 'type' => 'color', 'default' => '#f8f5ee', 'var' => '--ivoire' ),
				'color_ivoire_or' => array( 'label' => 'Ivoire doré', 'type' => 'color', 'default' => '#f5eedf', 'var' => '--ivoire-or' ),
				'color_blanc'     => array( 'label' => 'Blanc (fond de page)', 'type' => 'color', 'default' => '#ffffff', 'var' => '--blanc' ),
				'color_gris'      => array( 'label' => 'Gris (textes secondaires)', 'type' => 'color', 'default' => '#6b6257', 'var' => '--gris' ),
				'color_ligne'     => array( 'label' => 'Lignes / bordures', 'type' => 'color', 'default' => '#e6dfd2', 'var' => '--ligne' ),
			),
		),

		'bm_typo' => array(
			'title'  => '13 · Typographie & formes',
			'fields' => array(
				'font_display'   => array( 'label' => 'Police des titres', 'type' => 'select', 'default' => 'Cormorant Garamond', 'transport' => 'refresh', 'choices' => array(
					'Cormorant Garamond' => 'Cormorant Garamond (v1)',
					'Bodoni Moda'        => 'Bodoni Moda (direction B)',
					'Playfair Display'   => 'Playfair Display',
					'Libre Baskerville'  => 'Libre Baskerville',
					'DM Serif Display'   => 'DM Serif Display',
				) ),
				'font_sans'      => array( 'label' => 'Police des textes', 'type' => 'select', 'default' => 'Jost', 'transport' => 'refresh', 'choices' => array(
					'Jost'       => 'Jost (v1)',
					'Inter'      => 'Inter',
					'Montserrat' => 'Montserrat',
					'DM Sans'    => 'DM Sans',
					'Outfit'     => 'Outfit',
				) ),
				'font_size_base' => array( 'label' => 'Taille de texte de base (px)', 'type' => 'number', 'default' => '16.5', 'var' => '--fs-base', 'unit' => 'px', 'min' => 14, 'max' => 20, 'step' => 0.5 ),
				'radius_btn'     => array( 'label' => 'Arrondi des boutons (px)', 'type' => 'number', 'default' => '44', 'var' => '--r-btn', 'unit' => 'px', 'min' => 0, 'max' => 44, 'step' => 1 ),
				'radius_card'    => array( 'label' => 'Arrondi des cartes (px)', 'type' => 'number', 'default' => '14', 'var' => '--r-card', 'unit' => 'px', 'min' => 0, 'max' => 30, 'step' => 1 ),
			),
		),
	);

	return $fields;
}

/**
 * Génère N groupes de champs via un callback ($i) => array.
 */
function bm_repeat_fields( int $n, callable $cb ): array {
	$out = array();
	for ( $i = 1; $i <= $n; $i++ ) {
		$out = array_merge( $out, $cb( $i ) );
	}
	return $out;
}

/**
 * Tous les champs à plat : key => définition (+ 'section').
 */
function bm_fields_flat(): array {
	static $flat = null;
	if ( null === $flat ) {
		$flat = array();
		foreach ( bm_fields() as $sid => $section ) {
			foreach ( $section['fields'] as $key => $f ) {
				$f['section'] = $sid;
				$flat[ $key ] = $f;
			}
		}
	}
	return $flat;
}
