<?php
/**
 * Screen display functions.
 *
 * @package HumCORE
 * @subpackage Deposits
 */

/**
 * Output the deposits search form.
 *
 * @return string html output
 */
function humcore_deposits_search_form() {

	$default_search_value = bp_get_search_default_text( 'humcore_deposits' );
	$search_value = '';
	if ( ! empty( $_REQUEST['s'] ) ) { $search_value = stripslashes( $_REQUEST['s'] ); }

	$search_form_html = '<form action="" method="get" id="search-deposits-form">
			<label><input type="text" name="s" id="search-deposits-term" value="' . esc_attr( $search_value ) . '" placeholder="'. esc_attr( $default_search_value ) .'" /></label>
			<input type="hidden" name="facets" id="search-deposits-facets"></input>
			<input type="submit" id="search-deposits-submit" name="search_deposits_submit" value="' . __( 'Search', 'humcore_domain' ) . '" />
			</form>';

	echo apply_filters( 'humcore_deposits_search_form', $search_form_html ); // XSS OK.
}

/**
 * Render the content for deposits/item/new.
 */
function humcore_deposit_form() {

	if ( ! empty( $_POST ) ) {
		$deposit_status = humcore_deposit_file();
		if ( $deposit_status ) {
			Humcore_Deposit_Component::humcore_user_deposits_list();
			exit();
		}
	}

	if ( ! humcore_check_externals() ) {
		echo '<h3>New CORE Deposit</h3>';
		echo "<p>We're so sorry, but one of the components of CORE is currently down and it can't accept deposits just now. We're working on it (and we're delighted that you want to share your work) so please come back and try again later.</p>";
		$wp_referer = wp_get_referer();
		printf(
			'<a href="%1$s" class="button white" style="line-height: 1.2em;">Go Back</a>',
			( ! empty( $wp_referer ) && ! strpos( $wp_referer, 'item/new' ) ) ? $wp_referer : '/deposits/'
		);
		return;
	}

	$current_group_id = '';
	preg_match( '~.*?/groups/(.*[^/]?)/deposits/~i', wp_get_referer(), $slug_match );
	if ( ! empty( $slug_match ) ) {
		$current_group_id = BP_Groups_Group::get_id_from_slug( $slug_match[1] );
	}

	$user_id = bp_loggedin_user_id();
	$user_firstname = get_the_author_meta( 'first_name', $user_id );
	$user_lastname = get_the_author_meta( 'last_name', $user_id );

?>

<script type="text/javascript">
	var MyAjax = {
		ajaxurl : '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
		flash_swf_url : '<?php echo esc_url( includes_url( '/js/plupload/Moxie.swf' ) ); ?>',
		silverlight_xap_url : '<?php echo esc_url( includes_url( '/js/plupload/Moxie.xap' ) ); ?>',
		_ajax_nonce : '<?php echo esc_attr( wp_create_nonce( 'file-upload' ) ); ?>',
	};
</script>

<h3>New CORE Deposit</h3>
<div id="filelist">Your browser doesn't have Flash, Silverlight or HTML5 support.</div>
<div id="progressbar">
	<div id="indicator"></div>
</div>
<div id="console">Select the file you wish to upload and deposit. *</div>
<div id="container">
	<button id="pickfile">Select File</button> 
<!--    <button id="uploadfile">Upload</button> -->
	<?php $wp_referer = wp_get_referer();
		printf(
			'<a href="%1$s" class="button white" style="line-height: 1.2em;">Cancel</a>',
			( ! empty( $wp_referer ) && ! strpos( $wp_referer, 'item/new' ) ) ? $wp_referer : '/deposits/'
		);
	?>
</div>

<form id="deposit-form" class="standard-form" method="post" action="" enctype="multipart/form-data">

	<input type="hidden" name="action" id="action" value="deposit_file" />
	<?php wp_nonce_field( 'new_core_deposit', 'new_core_deposit_nonce' ); ?>

	<div id="deposit-metadata-entries">

	<div id="deposit-title-entry">
		<label for="deposit-title">Title</label>
		<input type="text" id="deposit-title" name="deposit-title" size="75" class="long" value="<?php if ( ! empty( $_POST['deposit-title'] ) ) {  echo wp_strip_all_tags( stripslashes( $_POST['deposit-title'] ) ); } ?>" />
		<span class="description">*</span>
	</div>
	<p>
	<div id="deposit-genre-entry">
		<label for="deposit-genre">Item Type</label>
		<select name="deposit-genre" id="deposit-genre" class="js-basic-single-optional" data-placeholder="Select an item type">
			<option class="level-0" value=""></option>
<?php
	$genre_list = humcore_deposits_genre_list();
	$posted_genre = '';
	if ( ! empty( $_POST['deposit-genre'] ) ) {
		$posted_genre = sanitize_text_field( $_POST['deposit-genre'] );
	}
	foreach ( $genre_list as $genre_key => $genre_value ) {
		printf('			<option class="level-0" %1$s value="%2$s">%3$s</option>' . "\n",
			( $genre_key == $posted_genre ) ? 'selected="selected"' : '',
			$genre_key,
			$genre_value
		);
	}
?>
		</select>
	</div>
	</p>
	<div id="deposit-conference-title-entry">
		<label for="deposit-conference-title-entry-list">Conference Title</label>
		<input type="text" name="deposit-conference-title" size="75" class="text" value="<?php if ( ! empty( $_POST['deposit-conference-title'] ) ) { echo sanitize_text_field( $_POST['deposit-conference-title'] ); } ?>" />
	</div>

	<div id="deposit-organization-entry">
		<label for="deposit-organization-entry-list">Conference Host Organization</label>
		<input type="text" name="deposit-organization" size="60" class="text" value="<?php if ( ! empty( $_POST['deposit-organization'] ) ) { echo sanitize_text_field( $_POST['deposit-organization'] ); } ?>" />
	</div>

	<div id="deposit-institution-entry">
		<label for="deposit-institution-entry-list">Name of Institution</label>
		<input type="text" name="deposit-institution" size="60" class="text" value="<?php if ( ! empty( $_POST['deposit-institution'] ) ) { echo sanitize_text_field( $_POST['deposit-institution'] ); } ?>" />
	</div>
	<p>
	<div id="deposit-abstract-entry">
		<label for="deposit-abstract">Description or Abstract</label>
		<textarea class="abstract_area" rows="12" autocomplete="off" cols="80" name="deposit-abstract" id="deposit-abstract"><?php if ( ! empty( $_POST['deposit-abstract'] ) ) { echo wp_strip_all_tags( stripslashes( $_POST['deposit-abstract'] ) ); } ?></textarea>
		<span class="description">*</span>
	</div>
	</p>
	<p>
	<div id="deposit-other-authors-entry">
		<label for="deposit-other-authors-entry-list">Authors</label>
		<span class="description">Add any authors in addition to yourself.</span>
		<ul id="deposit-other-authors-entry-list">
		<li>
		<table id="deposit-other-authors-entry-table"><tbody>
		<tr><td class="noBorderTop" style="width:205px;">
		First Name
		</td><td class="noBorderTop" style="width:205px;">
		Last Name
		</td><td class="noBorderTop">
		</td><td class="noBorderTop">
		</td></tr>
		<tr><td class="borderTop" style="width:205px;">
		<?php echo esc_html( $user_firstname ); ?>
		</td><td class="borderTop" style="width:205px;">
		<?php echo esc_html( $user_lastname ); ?>
		</td><td class="borderTop">
		<input type="button" id="deposit-insert-other-author-button" class="button add_author" value="Add Another">
		</td></tr>

<?php
	if ( ! empty( $_POST['deposit-other-authors-first-name'] ) && ! empty( $_POST['deposit-other-authors-last-name'] ) ) {
		$other_authors = array_map(
			function ( $first_name, $last_name ) { return array( 'first_name' => sanitize_text_field( $first_name ), 'last_name' => sanitize_text_field( $last_name ) ); },
			$_POST['deposit-other-authors-first-name'],
			$_POST['deposit-other-authors-last-name']
		);
		foreach ( $other_authors as $author_array ) {
			if ( ! empty( $author_array['first_name'] ) && ! empty( $author_array['last_name'] ) ) {
?>
		<tr><td class="borderTop" style="width:205px;">
		<input type="text" name="deposit-other-authors-first-name[]" class="text" value="<?php echo $author_array['first_name']; ?>" />
		</td><td class="borderTop" style="width:205px;">
		<input type="text" name="deposit-other-authors-last-name[]" class="text deposit-other-authors-last-name" value="<?php echo $author_array['last_name']; ?>" />
		</td><td class="borderTop">
		</td></tr>
<?php
			}
		}
	}
?>
		</tbody></table>
		</li>
		</ul>
	</div>
	</p>
	<p>
	<div id="deposit-subject-entry">
		<label for="deposit-subject">Subjects</label>
		<span class="description">Assign up to five subject fields to your item.<br />Please let us know if you would like to <a href="mailto:commons@mla.org?subject=CORE" target="_blank">suggest additional subject
 fields</a>.</span><br />
		<select name="deposit-subject[]" id="deposit-subject[]" class="js-basic-multiple" multiple="multiple" data-placeholder="Select subjects">
<?php
	$subject_list = humcore_deposits_subject_list();
	$posted_subject_list = array();
	if ( ! empty( $_POST['deposit-subject'] ) ) {
		$posted_subject_list = array_map( 'sanitize_text_field', $_POST['deposit-subject'] );
	}
	foreach ( $subject_list as $subject_key => $subject_value ) {
		printf('			<option class="level-1" %1$s value="%2$s">%3$s</option>' . "\n",
			( in_array( $subject_key, $posted_subject_list ) ) ? 'selected="selected"' : '',
			$subject_key,
			$subject_value
		);
	}
?>
		</select>
	</div>
	</p>
	<p>
	<div id="deposit-group-entry">
		<label for="deposit-group">Forums</label>
		<span class="description">Share this item with up to five <em>MLA Commons</em> forums.</span><br />
		<select name="deposit-group[]" id="deposit-group[]" class="js-basic-multiple" multiple="multiple" data-placeholder="Select forums">
<?php
	$group_list = humcore_deposits_group_list();
	$posted_group_list = array();
	if ( ! empty( $_POST['deposit-group'] ) ) { $posted_group_list = array_map( 'sanitize_text_field', $_POST['deposit-group'] ); }
	foreach ( $group_list as $group_key => $group_value ) {
		printf( '			<option class="level-1" %1$s value="%2$s">%3$s</option>' . "\n",
			( $current_group_id == $group_key || in_array( $group_key, $posted_group_list ) ) ? 'selected="selected"' : '',
			$group_key,
			$group_value
		);
	}
?>
		</select>
	</div>
	</p>
	<p>
	<div id="deposit-keyword-entry">
		<label for="deposit-keyword">Tags</label>
		<span class="description">Enter up to five tags to further categorize this item.</span><br />
		<select name="deposit-keyword[]" id="deposit-keyword[]" class="js-basic-multiple-tags" multiple="multiple" data-placeholder="Enter tags">
<?php
	$keyword_list = humcore_deposits_keyword_list();
	$posted_keyword_list = array();
	if ( ! empty( $_POST['deposit-keyword'] ) ) {
		$posted_keyword_list = array_map( 'sanitize_text_field', $_POST['deposit-keyword'] );
	}
	foreach ( $keyword_list as $keyword_key => $keyword_value ) {
		printf('			<option class="level-1" %1$s value="%2$s">%3$s</option>' . "\n",
			( in_array( $keyword_key, $posted_keyword_list ) ) ? 'selected="selected"' : '',
			$keyword_key,
			$keyword_value
		);
	}
?>
		</select>
	</div>
	</p>
	<p>
	<div id="deposit-resource-type-entry">
		<label for="deposit-resource-type">File Type</label>
		<select name="deposit-resource-type" id="deposit-resource-type" class="js-basic-single-optional" data-placeholder="Select a file type" data-allowClear="true">
			<option class="level-0" selected value=""></option>
<?php
	$resource_type_list = humcore_deposits_resource_type_list();
	$posted_resource_type = '';
	if ( ! empty( $_POST['deposit-resource-type'] ) ) {
		$posted_resource_type = sanitize_text_field( $_POST['deposit-resource-type'] );
	}
	foreach ( $resource_type_list as $resource_key => $resource_value ) {
		printf('			<option class="level-0" %1$s value="%2$s">%3$s</option>' . "\n",
			( $resource_key == $posted_resource_type ) ? 'selected="selected"' : '',
			$resource_key,
			$resource_value
		);
	}
?>
		</select>
	</div>
	</p>
	<p>
	<div id="deposit-notes-entry">
		<label for="deposit-notes">Notes or Background</label>
		<span class="description">Any additional information about your item?</span><br />
		<textarea name="deposit-notes" class="the-notes" id="deposit-notes"><?php if ( ! empty( $_POST['deposit-notes'] ) ) { echo sanitize_text_field( stripslashes( $_POST['deposit-notes'] ) ); } ?></textarea>
	</div>
	</p>
	<p>
	<div id="deposit-license-type-entry">
		<label for="deposit-license-type">Creative Commons License</label>
		<span class="description">By default, and in accordance with section 2 of the <em>MLA Commons</em> terms of service, no one may reuse this content in any way. Should you wish to allow others to distribute, display, modify, or otherwise reuse your content, please attribute it with the appropriate Creative Commons license from the dropdown menu below. See <a onclick="target='_blank'" href="http://creativecommons.org/licenses/">this page</a> for more information about the different types of Creative Commons licenses.</span><br /><br />
		<select name="deposit-license-type" id="deposit-license-type" class="js-basic-single-required">
<?php
	$license_type_list = humcore_deposits_license_type_list();
	$posted_license_type = '';
	if ( ! empty( $_POST['deposit-license-type'] ) ) {
		$posted_license_type = sanitize_text_field( $_POST['deposit-license-type'] );
	}
	foreach ( $license_type_list as $license_key => $license_value ) {
		printf('			<option class="level-1" %1$s value="%2$s">%3$s</option>' . "\n",
			( $license_key == $posted_license_type ) ? 'selected="selected"' : '',
			$license_key,
			$license_value
		);
	}
?>
		</select>
		<span class="description">*</span>
	</div>
	</p>
	<p>
	<div id="deposit-publication-type-entry">
		<label for="deposit-publication-type">Published?</label>
			<input type="radio" name="deposit-publication-type" value="book" <?php if ( ! empty( $_POST['deposit-publication-type'] ) ) { checked( sanitize_text_field( $_POST['deposit-publication-type'] ), 'book' ); } ?>>Book &nbsp;
			<input type="radio" name="deposit-publication-type" value="journal-article" <?php if ( ! empty( $_POST['deposit-publication-type'] ) ) { checked( sanitize_text_field( $_POST['deposit-publication-type'] ), 'journal-article' ); } ?>>Journal &nbsp;
			<input type="radio" name="deposit-publication-type" value="conference-proceeding" <?php if ( ! empty( $_POST['deposit-publication-type'] ) ) { checked( sanitize_text_field( $_POST['deposit-publication-type'] ), 'conference-proceeding' ); } ?>>Conference proceeding &nbsp;
			<input type="radio" name="deposit-publication-type" value="none" <?php if ( ! empty( $_POST['deposit-publication-type'] ) ) { checked( sanitize_text_field( $_POST['deposit-publication-type'] ), 'none' ); } ?>>Not published &nbsp;
	</div>
	</p>
	<div id="deposit-book-entries">

		<div id="deposit-book-doi-entry">
			<label for="deposit-book-doi">Publisher DOI</label>
			<input type="text" id="deposit-book-doi" name="deposit-book-doi" class="long" value="<?php if ( ! empty( $_POST['deposit-book-doi'] ) ) { echo sanitize_text_field( $_POST['deposit-book-doi'] ); } ?>" />
		</div>

		<div id="deposit-book-publisher-entry">
			<label for="deposit-book-publisher">Publisher</label>
			<input type="text" id="deposit-book-publisher" name="deposit-book-publisher" size="40" class="long" value="<?php if ( ! empty( $_POST['deposit-book-publisher'] ) ) { echo sanitize_text_field( $_POST['deposit-book-publisher'] ); } ?>" />
		</div>

		<div id="deposit-book-publish-date-entry">
			<label for="deposit-book-publish-date">Pub Date</label>
			<input type="text" id="deposit-book-publish-date" name="deposit-book-publish-date" class="text" value="<?php if ( ! empty( $_POST['deposit-book-publish-date'] ) ) { echo sanitize_text_field( $_POST['deposit-book-publish-date'] ); } ?>" />
		</div>

		<div id="deposit-book-title-entry">
			<label for="deposit-book-title">Book Title</label>
			<input type="text" id="deposit-book-title" name="deposit-book-title" size="60" class="long" value="<?php if ( ! empty( $_POST['deposit-book-title'] ) ) { echo sanitize_text_field( $_POST['deposit-book-title'] ); } ?>" />
		</div>

		<div id="deposit-book-author-entry">
			<label for="deposit-book-author">Book Author or Editor</label>
			<input type="text" id="deposit-book-author" name="deposit-book-author" class="long" value="<?php if ( ! empty( $_POST['deposit-book-author'] ) ) { echo sanitize_text_field( $_POST['deposit-book-author'] ); } ?>" />
		</div>

		<div id="deposit-book-chapter-entry">
			<label for="deposit-book-chapter">Chapter</label>
			<input type="text" id="deposit-book-chapter" name="deposit-book-chapter" class="text" value="<?php if ( ! empty( $_POST['deposit-book-chapter'] ) ) { echo sanitize_text_field( $_POST['deposit-book-chapter'] ); } ?>" />
		</div>

		<div id="deposit-book-pages-entry">
			<label for="deposit-book-start-page"><span>Start Page</span>
			<input type="text" id="deposit-book-start-page" name="deposit-book-start-page" size="5" class="text" value="<?php if ( ! empty( $_POST['deposit-book-start-page'] ) ) { echo sanitize_text_field( $_POST['deposit-book-start-page'] ); } ?>" />
			</label>
			<label for="deposit-book-end-page"><span>End Page</span>
			<input type="text" id="deposit-book-end-page" name="deposit-book-end-page" size="5" class="text" value="<?php if ( ! empty( $_POST['deposit-book-end-page'] ) ) { echo sanitize_text_field( $_POST['deposit-book-end-page'] ); } ?>" />
			</label>
			<br style='clear:both'>
		</div>

		<div id="deposit-book-isbn-entry">
			<label for="deposit-book-isbn">ISBN</label>
			<input type="text" id="deposit-book-isbn" name="deposit-book-isbn" class="text" value="<?php if ( ! empty( $_POST['deposit-book-isbn'] ) ) { echo sanitize_text_field( $_POST['deposit-book-isbn'] ); } ?>" />
		</div>

	</div>

	<div id="deposit-journal-entries">

		<div id="deposit-journal-doi-entry">
			<label for="deposit-journal-doi">Publisher DOI</label>
			<input type="text" id="deposit-journal-doi" name="deposit-journal-doi" class="long" value="<?php if ( ! empty( $_POST['deposit-journal-doi'] ) ) { echo sanitize_text_field( $_POST['deposit-journal-doi'] ); } ?>" />
		</div>

		<div id="deposit-journal-publisher-entry">
			<label for="deposit-journal-publisher">Publisher</label>
			<input type="text" id="deposit-journal-publisher" name="deposit-journal-publisher" size="40" class="long" value="<?php if ( ! empty( $_POST['deposit-journal-publisher'] ) ) { echo sanitize_text_field( $_POST['deposit-journal-publisher'] ); } ?>" />
		</div>

		<div id="deposit-journal-publish-date-entry">
			<label for="deposit-journal-publish-date">Pub Date</label>
			<input type="text" id="deposit-journal-publish-date" name="deposit-journal-publish-date" class="text" value="<?php if ( ! empty( $_POST['deposit-journal-publish-date'] ) ) { echo sanitize_text_field( $_POST['deposit-journal-publish-date'] ); } ?>" />
		</div>

		<div id="deposit-journal-title-entry">
			<label for="deposit-journal-title">Journal Title</label>
			<input type="text" id="deposit-journal-title" name="deposit-journal-title" size="75" class="long" value="<?php if ( ! empty( $_POST['deposit-journal-title'] ) ) { echo sanitize_text_field( $_POST['deposit-journal-title'] ); } ?>" />
		</div>

		<div id="deposit-journal-volume-entry">
			<label for="deposit-journal-volume"><span>Volume</span>
			<input type="text" id="deposit-journal-volume" name="deposit-journal-volume" class="text" value="<?php if ( ! empty( $_POST['deposit-journal-volume'] ) ) { echo sanitize_text_field( $_POST['deposit-journal-volume'] ); } ?>" />
			</label>
			<label for="deposit-journal-issue"><span>Issue</span>
			<input type="text" id="deposit-journal-issue" name="deposit-journal-issue" class="text" value="<?php if ( ! empty( $_POST['deposit-journal-volume'] ) ) { echo sanitize_text_field( $_POST['deposit-journal-volume'] ); } ?>" />
			</label>
			<br style='clear:both'>
		</div>

		<div id="deposit-journal-pages-entry">
			<label for="deposit-journal-start-page"><span>Start Page</span>
			<input type="text" id="deposit-journal-start-page" name="deposit-journal-start-page" size="5" class="text" value="<?php if ( ! empty( $_POST['deposit-journal-start-page'] ) ) { echo sanitize_text_field( $_POST['deposit-journal-start-page'] ); } ?>" />
			</label>
			<label for="deposit-journal-end-page"><span>End Page</span>
			<input type="text" id="deposit-journal-end-page" name="deposit-journal-end-page" size="5" class="text" value="<?php if ( ! empty( $_POST['deposit-journal-start-page'] ) ) { echo sanitize_text_field( $_POST['deposit-journal-start-page'] ); } ?>" />
			</label>
			<br style='clear:both'>
		</div>

		<div id="deposit-journal-issn-entry">
			<label for="deposit-journal-issn">ISSN</label>
			<input type="text" id="deposit-journal-issn" name="deposit-journal-issn" class="text" value="<?php if ( ! empty( $_POST['deposit-journal-issn'] ) ) { echo sanitize_text_field( $_POST['deposit-journal-issn'] ); } ?>" />
		</div>

	</div>

	<div id="deposit-conference-proceedings">

		<div id="deposit-proceeding-doi-entry">
			<label for="deposit-proceeding-doi">Publisher DOI</label>
			<input type="text" id="deposit-proceeding-doi" name="deposit-proceeding-doi" class="long" value="<?php if ( ! empty( $_POST['deposit-proceeding-doi'] ) ) { echo sanitize_text_field( $_POST['deposit-proceeding-doi'] ); } ?>" />
		</div>

		<div id="deposit-proceeding-publisher-entry">
			<label for="deposit-proceeding-publisher">Publisher</label>
			<input type="text" id="deposit-proceeding-publisher" name="deposit-proceeding-publisher" size="40" class="long" value="<?php if ( ! empty( $_POST['deposit-proceeding-publisher'] ) ) { echo sanitize_text_field( $_POST['deposit-proceeding-publisher'] ); } ?>" />
		</div>

		<div id="deposit-proceeding-publish-date-entry">
			<label for="deposit-proceeding-publish-date">Pub Date</label>
			<input type="text" id="deposit-proceeding-publish-date" name="deposit-proceeding-publish-date" class="text" value="<?php if ( ! empty( $_POST['deposit-proceeding-publish-date'] ) ) { echo sanitize_text_field( $_POST['deposit-proceeding-publish-date'] ); } ?>" />
		</div>

		<div id="deposit-proceeding-title-entry">
			<label for="deposit-proceeding-title">Proceeding Title</label>
			<input type="text" id="deposit-proceeding-title" name="deposit-proceeding-title" size="75" class="long" value="<?php if ( ! empty( $_POST['deposit-proceeding-title'] ) ) { echo sanitize_text_field( $_POST['deposit-proceeding-title'] ); } ?>" />
		</div>

		<div id="deposit-proceeding-pages-entry">
			<label for="deposit-proceeding-start-page"><span>Start Page</span>
			<input type="text" id="deposit-proceeding-start-page" name="deposit-proceeding-start-page" size="5" class="text" value="<?php if ( ! empty( $_POST['deposit-proceeding-start-page'] ) ) { echo sanitize_text_field( $_POST['deposit-proceeding-start-page'] ); } ?>" />
			</label>
			<label for="deposit-proceeding-end-page"><span>End Page</span>
			<input type="text" id="deposit-proceeding-end-page" name="deposit-proceeding-end-page" size="5" class="text" value="<?php if ( ! empty( $_POST['deposit-proceeding-start-page'] ) ) { echo sanitize_text_field( $_POST['deposit-proceeding-start-page'] ); } ?>" />
			</label>
			<br style='clear:both'>
		</div>

	</div>

	<div id="deposit-non-published-entries">

		<div id="deposit-non-published-date-entry">
			<label for="deposit-non-published-date">Date of Creation</label>
			<input type="text" id="deposit-non-published-date" name="deposit-non-published-date" class="text" value="<?php if ( ! empty( $_POST['deposit-non-published-date'] ) ) { echo sanitize_text_field( $_POST['deposit-non-published-date'] ); } ?>" />
		</div>

	</div>

	<input id="submit" name="submit" type="submit" value="Deposit" />
	<?php $wp_referer = wp_get_referer();
		printf(
			'<a href="%1$s" class="button white">Cancel</a>',
			( ! empty( $wp_referer ) && ! strpos( $wp_referer, 'item/new' ) ) ? $wp_referer : '/deposits/'
		);
	?>

	</div>

</form>
	<br /><span class="description">Required fields are marked *.</span><br />
<br />

<?php

}

/**
 * Output deposits loop entry html.
 */
function humcore_deposits_entry_content() {

	$metadata = (array) humcore_get_current_deposit();

	if ( ! empty( $metadata['group'] ) ) {
		$groups = array_filter( $metadata['group'] );
	}
	if ( ! empty( $groups ) ) {
		$group_list = implode( ', ', array_map( 'humcore_linkify_group', $groups ) );
	}
	if ( ! empty( $metadata['subject'] ) ) {
		$subjects = array_filter( $metadata['subject'] );
	}
	if ( ! empty( $subjects ) ) {
		$subject_list = implode( ', ', array_map( 'humcore_linkify_subject', $subjects ) );
	}
	$authors = array_filter( $metadata['authors'] );
	$author_meta = humcore_deposit_parse_author_info( $metadata['author_info'][0] );
	$authors_list = implode( ', ', array_map( 'humcore_linkify_author', $authors, $author_meta ) );
	$item_url = sprintf( '%1$s/deposits/item/%2$s', bp_get_root_domain(), $metadata['pid'] );
?>
<h4 class="bp-group-documents-title"><a href="<?php echo esc_url( $item_url ); ?>/"><?php echo esc_html( $metadata['title'] ); ?></a></h4>
<div class="bp-group-documents-meta">
<dl class='defList'>
<dt><?php _e( 'Author(s):', 'humcore_domain' ); ?></dt>
<dd><?php echo $authors_list; // XSS OK. ?></dd>
<?php if ( ! empty( $metadata['date'] ) ) : ?>
<dt><?php _e( 'Date:', 'humcore_domain' ); ?></dt>
<dd><a href="/deposits/?facets[pub_date_facet][]=<?php echo urlencode( $metadata['date'] ); ?>"><?php echo esc_html( $metadata['date'] ); ?></a></dd>
<?php endif; ?>
<?php if ( ! empty( $groups ) ) : ?>
<dt><?php _e( 'Forum(s):', 'humcore_domain' ); ?></dt>
<dd><?php echo $group_list; // XSS OK. ?></dd>
<?php endif; ?>
<?php if ( ! empty( $subjects ) ) : ?>
<dt><?php _e( 'Subject(s):', 'humcore_domain' ); ?></dt>
<dd><?php echo $subject_list; // XSS OK. ?></dd>
<?php endif; ?>

<?php if ( ! empty( $metadata['genre'] ) ) : ?>
<dt><?php _e( 'Item Type:', 'humcore_domain' ); ?></dt>
<dd><a href="/deposits/?facets[genre_facet][]=<?php echo urlencode( $metadata['genre'] ); ?>"><?php echo esc_html( $metadata['genre'] ); ?></a></dd>
<?php endif; ?>

<dt><?php _e( 'Permanent URL:', 'humcore_domain' ); ?></dt>
<dd><a href="<?php echo esc_attr( $metadata['handle'] ); ?>"><?php echo esc_html( $metadata['handle'] ); ?></a></dd>
</dl>
</div>
<br style='clear:both'>
<?php

}

/**
 * Output deposits single item html.
 */
function humcore_deposit_item_content() {

	$metadata = (array) humcore_get_current_deposit();

	if ( ! empty( $metadata['group'] ) ) {
		$groups = array_filter( $metadata['group'] );
	}
	if ( ! empty( $groups ) ) {
		$group_list = implode( ', ', array_map( 'humcore_linkify_group', $groups ) );
	}
	if ( ! empty( $metadata['subject'] ) ) {
		$subjects = array_filter( $metadata['subject'] );
	}
	if ( ! empty( $subjects ) ) {
		$subject_list = implode( ', ', array_map( 'humcore_linkify_subject', $subjects ) );
	}
	if ( ! empty( $metadata['keyword'] ) ) {
		$keywords = array_filter( $metadata['keyword'] );
	}
	if ( ! empty( $keywords ) ) {
		$keyword_list = implode( ', ', array_map( 'humcore_linkify_tag', $keywords ) );
	}

	$authors = array_filter( $metadata['authors'] );
	$author_meta = humcore_deposit_parse_author_info( $metadata['author_info'][0] );
	$authors_list = implode( ', ', array_map( 'humcore_linkify_author', $authors, $author_meta ) );
	$deposit_post_id = $metadata['record_identifier'];
	$post_metadata = json_decode( get_post_meta( $deposit_post_id, '_deposit_metadata', true ), true );

	$file_metadata = json_decode( get_post_meta( $deposit_post_id, '_deposit_file_metadata', true ), true );
	$downloads_meta_key = sprintf( '_total_downloads_%s_%s', $file_metadata['files'][0]['datastream_id'], $file_metadata['files'][0]['filename'] );

	$total_downloads = get_post_meta( $deposit_post_id, $downloads_meta_key, true );
	$total_views = get_post_meta( $deposit_post_id, '_total_views', true ) + 1; // Views counted at item page level.
	$post_meta_ID = update_post_meta( $deposit_post_id, '_total_views', $total_views );
	$download_url = sprintf( '/deposits/download/%s/%s/%s/',
		$file_metadata['files'][0]['pid'],
		$file_metadata['files'][0]['datastream_id'],
		$file_metadata['files'][0]['filename']
	);
	$view_url = sprintf( '/deposits/view/%s/%s/%s/',
		$file_metadata['files'][0]['pid'],
		$file_metadata['files'][0]['datastream_id'],
		$file_metadata['files'][0]['filename']
	);
	$metadata_url = sprintf( '/deposits/download/%s/%s/%s/',
		$metadata['pid'],
		'descMetadata',
		'xml'
	);
	$file_type_data = wp_check_filetype( $file_metadata['files'][0]['filename'], wp_get_mime_types() );
	$file_type_icon = sprintf( '<img class="deposit-icon" src="%s" alt="%s" />',
		plugins_url( 'assets/' . esc_attr( $file_type_data['ext'] ) . '-icon-48x48.png', __FILE__ ),
		esc_attr( $file_type_data['ext'] )
	);
	if ( ! empty( $file_metadata['files'][0]['thumb_filename'] ) ) {
		$thumb_url = sprintf( '<img class="deposit-thumb" src="/deposits/view/%s/%s/%s/" alt="%s" />',
			$file_metadata['files'][0]['pid'],
			$file_metadata['files'][0]['thumb_datastream_id'],
			$file_metadata['files'][0]['thumb_filename'],
			'thumbnail'
		);
	} else {
		$thumb_url = '';
	}
?>

<h3 class="bp-group-documents-title"><?php echo esc_html( $metadata['title'] ); ?></h3>
<div class="bp-group-documents-meta">
<dl class='defList'>
<dt><?php _e( 'Author(s):', 'humcore_domain' ); ?></dt>
<dd><?php echo $authors_list; // XSS OK. ?></dd>
<?php if ( ! empty( $metadata['date'] ) ) : ?>
<dt><?php _e( 'Date:', 'humcore_domain' ); ?></dt>
<dd><a href="/deposits/?facets[pub_date_facet][]=<?php echo urlencode( $metadata['date'] ); ?>"><?php echo esc_html( $metadata['date'] ); ?></a></dd>
<?php endif; ?>
<?php if ( ! empty( $groups ) ) : ?>
<dt><?php _e( 'Forum(s):', 'humcore_domain' ); ?></dt>
<dd><?php echo $group_list; // XSS OK. ?></dd>
<?php endif; ?>
<?php if ( ! empty( $subjects ) ) : ?>
<dt><?php _e( 'Subject(s):', 'humcore_domain' ); ?></dt>
<dd><?php echo $subject_list; // XSS OK. ?></dd>
<?php endif; ?>
<?php if ( ! empty( $metadata['genre'] ) ) : ?>
<dt><?php _e( 'Item Type:', 'humcore_domain' ); ?></dt>
<dd><a href="/deposits/?facets[genre_facet][]=<?php echo urlencode( $metadata['genre'] ); ?>"><?php echo esc_html( $metadata['genre'] ); ?></a></dd>
<?php endif; ?>
<?php if ( ! empty( $keywords ) ) : ?>
<dt><?php _e( 'Tag(s):', 'humcore_domain' ); ?></dt>
<dd><?php echo $keyword_list; // XSS OK. ?></dd>
<?php endif; ?>
<dt><?php _e( 'Permanent URL:', 'humcore_domain' ); ?></dt>
<dd><a href="<?php echo esc_attr( $metadata['handle'] ); ?>"><?php echo esc_html( $metadata['handle'] ); ?></a></dd>
<dt><?php ( ! empty( $metadata['genre'] ) && 'Abstract' == $metadata['genre'] ) ? _e( 'Description:', 'humcore_domain' ) : _e( 'Description:', 'humcore_domain' ); // Always Description or Abstract for now. ?></dt>
<?php if ( ! empty( $metadata['abstract'] ) ) : ?>
<dd><?php echo esc_html( $metadata['abstract'] ); ?></dd>
<?php endif; ?>
<?php if ( ! empty( $metadata['notes'] ) ) : ?>
<dt><?php _e( 'Notes:', 'humcore_domain' ); ?></dt>
<dd><?php echo esc_html( $metadata['notes'] ); ?></dd>
<?php endif; ?>
<dt><?php _e( 'Metadata:', 'humcore_domain' ); ?></dt>
<dd><a onclick="target='_blank'" class="bp-deposits-metadata" title="MODS Metadata" href="<?php echo esc_url( $metadata_url ); ?>">xml</a></dd>
<?php if ( ! empty( $post_metadata['type_of_license'] ) ) : ?>
<dt><?php _e( 'License:', 'humcore_domain' ); ?></dt>
<dd><?php echo esc_html( $post_metadata['type_of_license'] ); ?></dd>
<?php endif; ?>
</dl>
</div>
<br style='clear:both'>
<div><h3><?php _e( 'Downloads', 'humcore_domain' ); ?></h3>
<div class="doc-attachments">
	<table class="view_statistics">
	<tr><td class="prompt"><a class="bp-deposits-download button" title="Download" href="<?php echo esc_url( $download_url ); ?>"><?php _e( 'Download', 'humcore_domain' ); ?></a></td>
		<td class="value"><?php echo $file_type_icon . ' ' . esc_attr( $file_metadata['files'][0]['filename'] ); // XSS OK. ?></td></tr>
		<tr><td class="prompt"><?php _e( 'Total downloads:', 'humcore_domain' ); ?></td>
			<td class="value"><?php echo esc_html( $total_downloads ); ?></td></tr>
	<tr><td class="prompt"><a onclick="target='_blank'" class="bp-deposits-view button" title="View" href="<?php echo esc_url( $view_url ); ?>"><?php _e( 'View this item', 'humcore_domain' ); ?></a></td>
		<td class="value"><?php echo $thumb_url;// XSS OK. ?></td></tr>
		<tr><td class="prompt"><?php _e( 'Total views:', 'humcore_domain' ); ?></td>
			<td class="value"><?php echo esc_html( $total_views ); ?></td></tr>
	</table>
</div>
</div>
<?php

}

/**
 * Output the search sidebar facet list content.
 */
function humcore_search_sidebar_content() {

	$extended_query_string = humcore_get_search_request_querystring();
	$facet_display_counts = humcore_get_facet_counts();
	$facet_display_titles = humcore_get_facet_titles();
	$query_args = wp_parse_args( $extended_query_string ); ?>
	<ul class="facet-set"><?php
	foreach ( $facet_display_counts as $facet_key => $facet_values ) {
		$facet_list_count = 0;
		if ( ! empty( $facet_display_titles[ $facet_key ] ) ) : ?>
		<li class="facet-set-item"><?php echo esc_html( trim( $facet_display_titles[ $facet_key ] ) ); ?>
			<ul id="<?php echo sanitize_title_with_dashes( trim( $facet_key ) ); ?>-list" class="facet-list"><?php
			foreach ( $facet_values['counts'] as $facet_value_counts ) {
				if ( ! empty( $facet_value_counts[0] ) ) {
					$facet_list_item_selected = false;
					if ( ! empty( $query_args['facets'][ $facet_key ] ) ) {
						if ( in_array( $facet_value_counts[0], $query_args['facets'][ $facet_key ] ) ) {
							$facet_list_item_selected = true;
						}
					}
					$display_count = sprintf( '<span class="count facet-list-item-count"%1$s>%2$s</span>',
						( $facet_list_item_selected ) ? ' style="display: none;"' : '',
						$facet_value_counts[1]
					);
					$display_selected = sprintf( '<span class="iconify facet-list-item-control%1$s"%2$s>%3$s</span>',
						( $facet_list_item_selected ) ? ' selected' : '',
						( $facet_list_item_selected ) ? '' : ' style="display: none !important;"',
						'X'
					);
					echo sprintf( '<li class="facet-list-item"%1$s><a class="facet-search-link" href="/deposits/?facets[%2$s][]=%3$s">%4$s %5$s%6$s</a></li>',
						( $facet_list_count < 2 || $facet_list_item_selected ) ? '' : ' style="display: none;"',
						trim( $facet_key ),
						urlencode( trim( $facet_value_counts[0] ) ),
						trim( $facet_value_counts[0] ),
						$display_count,
						$display_selected
					); // XSS OK.
					$facet_list_count++;
				}
			}
			if ( 2 < $facet_list_count ) {
				echo '<div class="facet-display-button"><span class="show-more button white right">' . esc_attr__( 'more>>', 'humcore_domain' ) . '</span></div>';
			} ?>
			</ul>
		</li><?php
		endif;
	} ?>
	</ul><?php

}

/**
 * Output the search sidebar facet list content.
 */
function humcore_directory_sidebar_content() {

	$extended_query_string = humcore_get_search_request_querystring();
	humcore_has_deposits( $extended_query_string );
	$facet_display_counts = humcore_get_facet_counts();
	$facet_display_titles = humcore_get_facet_titles();
	$query_args = wp_parse_args( $extended_query_string ); ?>
	<ul class="facet-set"><?php
	foreach ( $facet_display_counts as $facet_key => $facet_values ) {
		if ( ! in_array( $facet_key, array( 'genre_facet', 'subject_facet', 'pub_date_facet' ) ) ) { continue; }
		$facet_list_count = 0; ?>
		<li class="facet-set-item">Browse by <?php echo esc_html( trim( $facet_display_titles[ $facet_key ] ) ); ?>
		<ul id="<?php echo sanitize_title_with_dashes( trim( $facet_key ) ); ?>-list" class="facet-list"><?php
		$sorted_counts = $facet_values['counts'];
		asort( $sorted_counts );
		foreach ( $sorted_counts as $facet_value_counts ) {
			if ( ! empty( $facet_value_counts[0] ) ) {
				$facet_list_item_selected = false;
				if ( ! empty( $query_args['facets'][ $facet_key ] ) ) {
					if ( in_array( $facet_value_counts[0], $query_args['facets'][ $facet_key ] ) ) {
						$facet_list_item_selected = true;
					}
				}
				$display_count = sprintf( '<span class="count facet-list-item-count"%1$s>%2$s</span>',
					( $facet_list_item_selected ) ? ' style="display: none;"' : '',
					$facet_value_counts[1]
				);
				echo sprintf( '<li class="facet-list-item"%1$s><a class="facet-search-link" href="/deposits/?facets[%2$s][]=%3$s">%4$s %5$s</a></li>',
					( $facet_list_count < 4 || $facet_list_item_selected ) ? '' : ' style="display: none;"',
					trim( $facet_key ),
					urlencode( trim( $facet_value_counts[0] ) ),
					trim( $facet_value_counts[0] ),
					$display_count
				); // XSS OK.
				$facet_list_count++;
			}
		}
		if ( 4 < $facet_list_count ) {
			echo '<div class="facet-display-button"><span class="show-more button white right">' . esc_attr__( 'more>>', 'humcore_domain' ) . '</span></div>';
		} ?>
			</ul>
		</li>
	<?php }?>
	</ul>
<?php

}
