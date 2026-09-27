<?php
/**
 * Page d'accueil : les sections sont rendues dans l'ordre choisi dans le builder.
 */
get_header();
?>
<main id="top">
<?php
foreach ( bm_sections() as $section => $visible ) {
	if ( ! $visible ) {
		continue;
	}
	$part = 'marquee2' === $section ? 'marquee' : $section;
	set_query_var( 'bm_section_id', $section );
	get_template_part( 'template-parts/landing/' . $part );
}
?>
</main>
<?php
get_footer();
