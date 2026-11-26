<?php
/**
 * Texts of the legal pages, written into WordPress by tools/publish-legal.php only.
 * Returns a function: ( facts, site ) => array kind => HTML. No square bracket may appear in a text:
 * the opening tool reads one as an unfilled template field.
 */
if ( 'cli' !== PHP_SAPI ) { http_response_code( 404 ); exit; }

return static function ( array $facts, array $site ): array {
    $f = array();
    foreach ( $facts as $key => $value ) { $f[ $key ] = is_string( $value ) ? esc_html( $value ) : $value; }
    $url = array_map( 'esc_url', $site['urls'] );
    $days = (int) $site['retention_days'];
    $mail = '<a href="mailto:' . esc_attr( $facts['email'] ) . '">' . $f['email'] . '</a>';
    $eu_vat = $f['eu_vat'] ? ' Numéro de TVA intracommunautaire : ' . $f['eu_vat'] . '.' : '';
    $representative = $f['eu_representative'] ? '<p>Représentant dans l’Union européenne : ' . $f['eu_representative'] . '.</p>' : '';
    $addresses = '';
    foreach ( $facts['return_addresses'] as $carrier => $address ) { $addresses .= '<li>Retour par ' . esc_html( $carrier ) . ' : ' . esc_html( $address ) . '</li>'; }
    $return_costs = 'seller' === $facts['return_costs']
        ? 'Nous prenons en charge les frais de renvoi du produit : nous vous transmettons une étiquette de retour.'
        : 'Les frais directs de renvoi du produit sont à votre charge.';
    $version = '<p><em>Version en vigueur au ' . $f['version_date'] . '.</em></p>';
    $seller = $f['seller_name'] . ', ' . $f['seller_form'] . ', dont le siège social est situé ' . $f['seller_address'] . ', immatriculée auprès de ' . $f['seller_registration'];

    $form = '<p>À l’attention de ' . $f['seller_name'] . ', ' . $f['seller_address'] . ' — ' . $f['email'] . ' :</p>'
        . '<p>Je/nous (*) vous notifie/notifions (*) par la présente ma/notre (*) rétractation du contrat portant sur la vente du bien ci-dessous :</p>'
        . '<p>Commandé le (*) / reçu le (*) :</p><p>Numéro de commande :</p><p>Nom du (des) consommateur(s) :</p><p>Adresse du (des) consommateur(s) :</p>'
        . '<p>Signature du (des) consommateur(s) (uniquement en cas de notification du présent formulaire sur papier) :</p><p>Date :</p><p>(*) Rayez la mention inutile.</p>';

    $withdrawal_steps = '<p>Pour exercer ce droit, vous nous informez de votre décision avant l’expiration du délai, par une déclaration dénuée d’ambiguïté. Vous pouvez :</p>'
        . '<ul><li>utiliser la fonction de rétractation en ligne, accessible pendant tout le délai par le lien « Renoncer au contrat ici » placé en bas de chaque page du site et dans vos e-mails de commande : <a href="' . $url['withdrawal'] . '">' . $url['withdrawal'] . '</a>. Un accusé de réception indiquant la date et l’heure de votre déclaration vous est envoyé aussitôt par e-mail ;</li>'
        . '<li>nous écrire à ' . $mail . ' ;</li>'
        . '<li>nous adresser le formulaire type de rétractation reproduit sur la page <a href="' . $url['returns'] . '">Livraison et retours</a>, sans que ce soit obligatoire.</li></ul>';

    $pages = array();

    $pages['legal'] = '<h2>Éditeur du site</h2>'
        . '<p>Le site ' . esc_html( $site['host'] ) . ' est édité par ' . $seller . '. Belmains est le nom commercial sous lequel cette société vend ses produits.</p>'
        . '<p>Numéro de TVA : ' . $f['seller_vat'] . ' (Royaume-Uni).' . $eu_vat . '</p>'
        . '<p>E-mail : ' . $mail . ' — Téléphone : ' . $f['phone'] . '</p>'
        . '<p>Responsable de la publication : ' . $f['publication_director'] . '.</p>'
        . '<h2>Hébergement</h2>'
        . '<p>Le site est hébergé par ' . $f['host_name'] . ', ' . $f['host_address'] . ' — téléphone : ' . $f['host_phone'] . '.</p>'
        . '<h2>Propriété intellectuelle</h2>'
        . '<p>Le nom Belmains, les textes, photographies, vidéos et éléments graphiques du site appartiennent à l’éditeur ou à ses partenaires. Toute reproduction ou réutilisation sans autorisation écrite préalable est interdite.</p>'
        . '<h2>Vente en ligne</h2>'
        . '<p>Les ventes conclues sur le site sont régies par nos <a href="' . $url['terms'] . '">conditions générales de vente</a>. Les modalités de livraison, de rétractation et de retour sont détaillées sur la page <a href="' . $url['returns'] . '">Livraison et retours</a>.</p>'
        . '<h2>Données personnelles et traceurs</h2>'
        . '<p>Le traitement de vos données et l’usage des traceurs sont décrits dans notre <a href="' . $url['privacy'] . '">politique de confidentialité</a>.</p>'
        . '<h2>Réclamations et médiation</h2>'
        . '<p>Pour toute réclamation, écrivez-nous à ' . $mail . '. À défaut de solution, vous pouvez saisir gratuitement le médiateur de la consommation : ' . $f['mediator_name'] . ', ' . $f['mediator_address'] . ' — <a href="' . esc_url( (string) $facts['mediator_url'] ) . '">' . $f['mediator_url'] . '</a>.</p>'
        . $version;

    $pages['terms'] = '<h2>1. Vendeur</h2>'
        . '<p>Les produits Belmains sont vendus par ' . $seller . ' (TVA : ' . $f['seller_vat'] . ').</p>'
        . '<p>Contact : ' . $mail . ' — téléphone : ' . $f['phone'] . '.</p>'
        . '<h2>2. Champ d’application</h2>'
        . '<p>Les présentes conditions s’appliquent à toutes les commandes passées sur le site ' . esc_html( $site['host'] ) . ' par un consommateur, pour une livraison en ' . $f['territory'] . '. Vous les acceptez en validant votre commande ; la version applicable est celle en vigueur à cette date.</p>'
        . '<h2>3. Produits</h2>'
        . '<p>Les caractéristiques essentielles du gant Belmains et de ses accessoires sont présentées sur la fiche du produit. Les offres sont valables tant qu’elles sont visibles sur le site, dans la limite des stocks disponibles. Si un produit commandé se révèle indisponible, nous vous en informons sans délai et vous remboursons les sommes versées au plus tard dans les quatorze jours.</p>'
        . '<h2>4. Prix</h2>'
        . '<p>Les prix sont indiqués en euros, toutes taxes comprises, TVA incluse au taux applicable dans le pays de livraison. La livraison à domicile est offerte. Le prix applicable est celui affiché sur le site au moment de la validation de la commande.</p>'
        . '<h2>5. Commande</h2>'
        . '<p>Vous choisissez votre offre, vérifiez le contenu de votre panier, renseignez vos coordonnées et votre adresse de livraison, puis validez votre commande, ce qui vous engage à en payer le prix. Vous pouvez corriger vos informations à chaque étape avant cette validation. Un e-mail de confirmation récapitulant votre commande vous est ensuite adressé. Le contrat est conclu en langue française.</p>'
        . '<h2>6. Paiement</h2>'
        . '<p>Le prix est payable en totalité à la commande. Moyens de paiement acceptés : ' . $f['payment_methods'] . '. Les paiements sont traités par notre prestataire Revolut ; vos données de carte bancaire ne sont ni collectées ni conservées par notre site. Les produits restent notre propriété jusqu’au paiement complet du prix.</p>'
        . '<h2>7. Livraison</h2>'
        . '<p>Les commandes sont expédiées depuis ' . $f['shipping_origin'] . ' et livrées à l’adresse indiquée lors de la commande, en ' . $f['territory'] . ', sous ' . $f['delivery_delay'] . ' à compter de la confirmation de votre commande.</p>'
        . '<p>En cas de retard, vous pouvez nous demander de livrer dans un délai supplémentaire raisonnable ; si la livraison n’intervient pas dans ce nouveau délai, vous pouvez mettre fin au contrat par écrit. Nous vous remboursons alors la totalité des sommes versées au plus tard dans les quatorze jours.</p>'
        . '<p>Le risque de perte ou d’endommagement du produit vous est transféré au moment où vous, ou la personne que vous avez désignée, en prenez physiquement possession. Si le colis arrive ouvert ou endommagé, signalez-le au transporteur et écrivez-nous rapidement à ' . $mail . '.</p>'
        . '<h2>8. Droit de rétractation</h2>'
        . '<p>Vous disposez d’un délai de quatorze jours pour vous rétracter, sans avoir à motiver votre décision. Ce délai court à compter du jour où vous, ou un tiers que vous avez désigné autre que le transporteur, prenez physiquement possession du produit ; en cas de livraison en plusieurs colis, à compter de la réception du dernier.</p>'
        . $withdrawal_steps
        . '<p>Vous renvoyez le produit sans retard excessif et au plus tard quatorze jours après nous avoir communiqué votre décision, à l’une des adresses de retour indiquées sur la page <a href="' . $url['returns'] . '">Livraison et retours</a>. ' . $return_costs . '</p>'
        . '<p>Nous vous remboursons tous les paiements reçus, sans retard excessif et au plus tard quatorze jours à compter du jour où nous sommes informés de votre décision. Nous pouvons différer ce remboursement jusqu’à la réception du produit ou jusqu’à ce que vous ayez fourni une preuve de son expédition, la date retenue étant celle du premier de ces faits. Le remboursement est effectué par le moyen de paiement utilisé pour la commande, sauf accord exprès de votre part pour un autre moyen, et sans frais pour vous.</p>'
        . '<p>Votre responsabilité n’est engagée qu’en cas de dépréciation du produit résultant de manipulations autres que celles nécessaires pour établir sa nature, ses caractéristiques et son bon fonctionnement.</p>'
        . '<h2>9. Garanties légales</h2>'
        . '<p>Le produit bénéficie de la garantie légale de conformité et de la garantie des vices cachés. Aucune garantie commerciale ne s’y ajoute. Pour les mettre en œuvre, écrivez-nous à ' . $mail . ' en indiquant votre numéro de commande.</p>'
        . '<div class="belmains-legal-box" style="border:1px solid #63182e;padding:16px 20px;margin:20px 0">'
        . '<p>Le consommateur dispose d’un délai de deux ans à compter de la délivrance du bien pour obtenir la mise en œuvre de la garantie légale de conformité en cas d’apparition d’un défaut de conformité. Durant ce délai, le consommateur n’est tenu d’établir que l’existence du défaut de conformité et non la date d’apparition de celui-ci.</p>'
        . '<p>Lorsque le contrat de vente du bien prévoit la fourniture d’un contenu numérique ou d’un service numérique de manière continue pendant une durée supérieure à deux ans, la garantie légale est applicable à ce contenu numérique ou ce service numérique tout au long de la période de fourniture prévue. Durant ce délai, le consommateur n’est tenu d’établir que l’existence du défaut de conformité affectant le contenu numérique ou le service numérique et non la date d’apparition de celui-ci.</p>'
        . '<p>La garantie légale de conformité emporte obligation pour le professionnel, le cas échéant, de fournir toutes les mises à jour nécessaires au maintien de la conformité du bien.</p>'
        . '<p>La garantie légale de conformité donne au consommateur droit à la réparation ou au remplacement du bien dans un délai de trente jours suivant sa demande, sans frais et sans inconvénient majeur pour lui.</p>'
        . '<p>Si le bien est réparé dans le cadre de la garantie légale de conformité, le consommateur bénéficie d’une extension de ' . $f['repair_extension'] . ' de la garantie initiale.</p>'
        . '<p>Si le consommateur demande la réparation du bien, mais que le vendeur impose le remplacement, la garantie légale de conformité est renouvelée pour une période de deux ans à compter de la date de remplacement du bien.</p>'
        . '<p>Le consommateur peut obtenir une réduction du prix d’achat en conservant le bien ou mettre fin au contrat en se faisant rembourser intégralement contre restitution du bien, si :</p>'
        . '<p>1° Le professionnel refuse de réparer ou de remplacer le bien ;<br>2° La réparation ou le remplacement du bien intervient après un délai de trente jours ;<br>3° La réparation ou le remplacement du bien occasionne un inconvénient majeur pour le consommateur, notamment lorsque le consommateur supporte définitivement les frais de reprise ou d’enlèvement du bien non conforme, ou s’il supporte les frais d’installation du bien réparé ou de remplacement ;<br>4° La non-conformité du bien persiste en dépit de la tentative de mise en conformité du vendeur restée infructueuse.</p>'
        . '<p>Le consommateur a également droit à une réduction du prix du bien ou à la résolution du contrat lorsque le défaut de conformité est si grave qu’il justifie que la réduction du prix ou la résolution du contrat soit immédiate. Le consommateur n’est alors pas tenu de demander la réparation ou le remplacement du bien au préalable.</p>'
        . '<p>Le consommateur n’a pas droit à la résolution de la vente si le défaut de conformité est mineur.</p>'
        . '<p>Toute période d’immobilisation du bien en vue de sa réparation ou de son remplacement suspend la garantie qui restait à courir jusqu’à la délivrance du bien remis en état.</p>'
        . '<p>Les droits mentionnés ci-dessus résultent de l’application des articles L. 217-1 à L. 217-32 du code de la consommation.</p>'
        . '<p>Le vendeur qui fait obstacle de mauvaise foi à la mise en œuvre de la garantie légale de conformité encourt une amende civile d’un montant maximal de 300 000 euros, qui peut être porté jusqu’à 10 % du chiffre d’affaires moyen annuel (article L. 241-5 du code de la consommation).</p>'
        . '<p>Le consommateur bénéficie également de la garantie légale des vices cachés en application des articles 1641 à 1649 du code civil, pendant une durée de deux ans à compter de la découverte du défaut. Cette garantie donne droit à une réduction de prix si le bien est conservé ou à un remboursement intégral contre restitution du bien.</p>'
        . '</div>'
        . '<h2>10. Réclamations et médiation</h2>'
        . '<p>Pour toute réclamation, écrivez-nous à ' . $mail . '. Si aucune solution n’est trouvée après cette réclamation écrite, vous pouvez recourir gratuitement, dans un délai d’un an, au médiateur de la consommation dont nous relevons : ' . $f['mediator_name'] . ', ' . $f['mediator_address'] . ' — <a href="' . esc_url( (string) $facts['mediator_url'] ) . '">' . $f['mediator_url'] . '</a>.</p>'
        . '<h2>11. Données personnelles</h2>'
        . '<p>Les données recueillies lors de la commande sont traitées dans les conditions décrites par notre <a href="' . $url['privacy'] . '">politique de confidentialité</a>.</p>'
        . '<h2>12. Droit applicable et litiges</h2>'
        . '<p>Les présentes conditions sont soumises au droit français. En cas de litige, vous pouvez saisir, à votre choix, la juridiction du lieu où vous demeuriez au moment de la conclusion du contrat ou de la survenance du fait dommageable, ou toute autre juridiction compétente en vertu du code de procédure civile.</p>'
        . $version;

    $pages['privacy'] = '<h2>Responsable du traitement</h2>'
        . '<p>Vos données sont traitées par ' . $seller . ', qui exploite le site ' . esc_html( $site['host'] ) . '. Pour toute question ou pour exercer vos droits : ' . $mail . '.</p>'
        . $representative
        . '<h2>Commandes et compte client</h2>'
        . '<p>Lorsque vous commandez, nous traitons votre identité, vos adresses de facturation et de livraison, votre e-mail, votre numéro de téléphone, le contenu et le montant de votre commande ainsi que l’état de son paiement et de sa livraison. Ces données sont nécessaires à l’exécution du contrat de vente : préparation, livraison, suivi, service après-vente, rétractation et garanties. Vos données de carte bancaire sont saisies auprès de notre prestataire de paiement ; notre site ne les collecte pas et ne les conserve pas.</p>'
        . '<p>Les données de commande et de facturation sont conservées dix ans au titre de nos obligations comptables. Les données de votre compte client sont conservées jusqu’à sa suppression à votre demande et, à défaut, trois ans après votre dernière activité.</p>'
        . '<h2>Demandes au service client</h2>'
        . '<p>Le formulaire de contact enregistre votre nom, votre e-mail, le sujet, votre message et, si vous l’indiquez, votre référence de commande, afin de vous répondre. Il ne vous inscrit à aucune liste publicitaire. Ce traitement repose sur notre intérêt légitime à répondre à vos demandes et, pour un client, sur l’exécution du contrat. Les demandes sont conservées trois ans après le dernier échange.</p>'
        . '<h2>Rétractation en ligne</h2>'
        . '<p>Le formulaire « Renoncer au contrat ici » enregistre votre nom, votre e-mail, le numéro de commande et les précisions que vous indiquez, ainsi que la date et l’heure de votre déclaration, afin de traiter votre rétractation et de vous en accuser réception. Ce traitement répond à une obligation légale. Ces éléments sont conservés cinq ans à titre de preuve.</p>'
        . '<h2>Mesure d’audience</h2>'
        . '<p>Avec votre accord, nous mesurons les visites et le parcours d’achat. Cette mesure est réalisée par notre propre site : les statistiques, pseudonymes, ne contiennent ni votre e-mail, ni votre adresse IP complète, ni l’adresse des pages avec tous leurs paramètres. Elles sont conservées ' . $days . ' jours. Le refus laisse la navigation et l’achat possibles.</p>'
        . '<h2>Publicité Meta (Facebook et Instagram)</h2>'
        . '<p>Avec votre accord, le pixel de Meta Platforms Ireland Ltd. dépose des traceurs dans votre navigateur. Notre site transmet aussi à Meta, depuis votre navigateur et depuis notre serveur, les pages consultées, les ajouts au panier, les passages en commande et vos achats (produits, montant, numéro de commande), accompagnés de votre adresse IP, de l’identité de votre navigateur et des identifiants publicitaires de Meta. Si la correspondance avancée est activée, les coordonnées saisies lors de la commande (e-mail, nom, téléphone, ville, code postal, pays) sont transmises sous forme hachée.</p>'
        . '<p>Ces informations servent à mesurer l’efficacité de nos publicités et à vous présenter nos annonces sur Facebook et Instagram. Elles sont traitées par Meta selon sa propre politique de confidentialité et peuvent être transférées hors de l’Union européenne. Sans accord de votre part, aucun traceur publicitaire n’est déposé et aucune information n’est transmise à Meta.</p>'
        . '<h2>Traceurs et choix</h2>'
        . '<p>Les traceurs strictement nécessaires au fonctionnement du site (panier, commande, connexion à votre compte, mémorisation de vos choix) sont déposés sans consentement. Les traceurs de mesure d’audience et de publicité ne sont déposés qu’après votre accord, donné séparément pour chaque finalité. Votre choix est conservé six mois ; vous pouvez le modifier à tout moment avec le lien « ' . esc_html( $site['consent_label'] ) . ' » présent en bas de chaque page.</p>'
        . '<h2>Destinataires</h2>'
        . '<p>Vos données sont accessibles aux seules personnes chargées de la boutique et du service client, ainsi qu’aux prestataires qui interviennent pour notre compte ou pour l’exécution de votre commande :</p>'
        . '<ul><li>' . $f['host_name'] . ' (hébergement du site), en Suisse ;</li>'
        . '<li>Revolut (traitement des paiements) ;</li>'
        . '<li>notre prestataire logistique, qui prépare et expédie les colis depuis l’Espagne et reçoit les retours en France, et les transporteurs Chronopost et Colissimo ;</li>'
        . '<li>Meta Platforms Ireland Ltd., uniquement si vous avez accepté la publicité.</li></ul>'
        . '<p>Nous ne vendons pas vos données. Notre société est établie au Royaume-Uni et notre hébergeur en Suisse, deux pays reconnus par la Commission européenne comme assurant un niveau de protection adéquat des données personnelles.</p>'
        . '<h2>Vos droits</h2>'
        . '<p>Vous disposez d’un droit d’accès, de rectification, d’effacement, de limitation, d’opposition et de portabilité de vos données, du droit de retirer votre consentement à tout moment et du droit de définir des directives sur le sort de vos données après votre décès. Pour les exercer, écrivez-nous à ' . $mail . ' ; nous pouvons vous demander de justifier de votre identité. Si vous estimez, après nous avoir contactés, que vos droits ne sont pas respectés, vous pouvez adresser une réclamation à la CNIL (www.cnil.fr).</p>'
        . '<p>Si vous ne souhaitez pas faire l’objet de prospection commerciale par téléphone, vous pouvez vous inscrire gratuitement sur la liste d’opposition Bloctel (www.bloctel.gouv.fr). Nous n’utilisons pas votre numéro à cette fin : il sert uniquement à la livraison et au suivi de votre commande.</p>'
        . $version;

    $pages['returns'] = '<h2>Livraison</h2>'
        . '<p>La livraison à domicile est offerte. Nous livrons en ' . $f['territory'] . '. Les commandes sont expédiées depuis ' . $f['shipping_origin'] . ' et livrées sous ' . $f['delivery_delay'] . ' à compter de la confirmation de votre commande. Un e-mail vous informe de l’expédition et vous permet de suivre votre colis.</p>'
        . '<p>Si le colis arrive ouvert ou endommagé, signalez-le au transporteur et écrivez-nous rapidement à ' . $mail . ' en joignant, si possible, des photographies.</p>'
        . '<h2>Changer d’avis : votre droit de rétractation</h2>'
        . '<p>Vous disposez de quatorze jours à compter de la réception de votre commande pour vous rétracter, sans avoir à motiver votre décision.</p>'
        . $withdrawal_steps
        . '<h2>Renvoyer le produit</h2>'
        . '<p>Renvoyez le produit complet, avec ses accessoires, soigneusement emballé, au plus tard quatorze jours après nous avoir communiqué votre décision. ' . $return_costs . ' Conservez la preuve de dépôt de votre colis. L’adresse de retour dépend du transporteur que vous utilisez :</p>'
        . '<ul>' . $addresses . '</ul>'
        . '<p>Indiquez votre numéro de commande dans le colis. Nous vous remboursons tous les paiements reçus au plus tard quatorze jours après avoir été informés de votre décision ; ce remboursement peut être différé jusqu’à la réception du produit ou d’une preuve de son expédition. Il est effectué par le moyen de paiement utilisé pour la commande.</p>'
        . '<h2>Produit défectueux ou non conforme</h2>'
        . '<p>Le produit bénéficie de la garantie légale de conformité pendant deux ans et de la garantie des vices cachés, détaillées dans nos <a href="' . $url['terms'] . '">conditions générales de vente</a>. Écrivez-nous à ' . $mail . ' en indiquant votre numéro de commande et en décrivant le défaut : nous vous indiquons la marche à suivre et prenons en charge les frais de retour. Aucune garantie commerciale ne s’ajoute à ces garanties légales.</p>'
        . '<h2>Formulaire type de rétractation</h2>'
        . '<p>Veuillez compléter et renvoyer le présent formulaire uniquement si vous souhaitez vous rétracter du contrat.</p>'
        . $form
        . $version;

    $pages['withdrawal'] = '[belmains_retractation]';

    return $pages;
};
