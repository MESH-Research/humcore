// Deposit form button control
jQuery(document).ready( function($) {

 	function maybe_show_conference_fields(event) {
		var value = $(this).val();
		if ( value == 'Dissertation' || value == 'Thesis' ) {
			$('#deposit-conference-title-entry').hide();
		   	$('#deposit-organization-entry').hide();
		   	$('#deposit-institution-entry').show();
		} else if ( value == 'Conference paper' || value == 'Conference proceeding' ) {
			$('#deposit-conference-title-entry').show();
		   	$('#deposit-organization-entry').show();
			$('#deposit-institution-entry').hide();
		} else {
			$('#deposit-conference-title-entry').hide();
			$('#deposit-organization-entry').hide();
			$('#deposit-institution-entry').hide();
		}
	}

 	function maybe_show_committee_fields(event) {
		var value = $(this).val();
		if ( value == 'yes' ) {
		   	$('#deposit-other-authors-entry').hide();
		   	$('#deposit-committee-entry').show();
		} else {
			$('#deposit-other-authors-entry').show();
			$('#deposit-committee-entry').hide();
		}
	}

 	function maybe_show_publication_fields(event) {
		var value = $(this).val();
		if ( value == 'book' ) {
		   	$('#deposit-book-entries').show();
			$('#deposit-journal-entries').hide();
			$('#deposit-conference-proceedings').hide();
			$('#deposit-non-published-entries').hide();
		} else if ( value == 'journal-article' ) {
			$('#deposit-book-entries').hide();
		   	$('#deposit-journal-entries').show();
			$('#deposit-conference-proceedings').hide();
			$('#deposit-non-published-entries').hide();
		} else if ( value == 'conference-proceeding' ) {
			$('#deposit-book-entries').hide();
		   	$('#deposit-journal-entries').hide();
			$('#deposit-conference-proceedings').show();
			$('#deposit-non-published-entries').hide();
		} else if ( value == 'none' ) {
			$('#deposit-book-entries').hide();
			$('#deposit-journal-entries').hide();
			$('#deposit-conference-proceedings').hide();
			$('#deposit-non-published-entries').show();
		}
	}

    //Hide the published forms by default, expand as needed
    $('#deposit-conference-title-entry').hide();
    $('#deposit-organization-entry').hide();
    $('#deposit-institution-entry').hide();
    $('#deposit-committee-entry').hide();
    $('#deposit-book-entries').hide();
    $('#deposit-journal-entries').hide();
    $('#deposit-conference-proceedings').hide();
//	$('#deposit-non-published-entries').hide();
 	$('select[name=deposit-genre]').on('change', maybe_show_conference_fields);
 	$('input[type=radio][name=deposit-on-behalf-flag]').on('click', maybe_show_committee_fields);
 	$('input[type=radio][name=deposit-publication-type]').on('click', maybe_show_publication_fields);

 	$('#deposit-insert-other-author-button').on('click', function(e) {
		e.preventDefault();
		$('#deposit-other-authors-entry-table>tbody').append('		<tr><td class="borderTop"><input type="text" name="deposit-other-authors-first-name[]" class="text" value="" /></td>' +
				'<td class="borderTop"><input type="text" name="deposit-other-authors-last-name[]" class="text deposit-other-authors-last-name" value="" /></td>' +
				'<td class="borderTop"></td></tr>');
	});

 	$('#deposit-form').on('submit', function(e) {
		var title = $.trim($('#deposit-title').val());
		var description = $.trim($('#deposit-abstract').val());
		var selected_file = $.trim($('input[type=hidden][name=selected_file_name]').val());

		if ( title === '' || description === '' || selected_file === '' ) {
			alert('Please upload a file and enter the mandatory fields Title and Description.');
			return false;
		} else {
			$('#submit').attr('value', 'Please wait...');
			$('#submit').prop('disabled', true);
			return true;
		}
	});

} );

// Custom plupload logic
jQuery(document).ready( function($) {

var uploader = new plupload.Uploader( {
	runtimes : 'html5,flash,silverlight,html4',
	multi_selection : false, // only one file allowed for this phase
	unique_names : true, //handle unique names in php
	chunk_size: '2mb',
	max_retries: 3,

	browse_button : 'pickfile', // you can pass in id...
	container: document.getElementById('container'), // ... or DOM Element itself
	url : MyAjax.ajaxurl, //MyAjax set in php
	flash_swf_url : MyAjax.flash_swf_url,
	silverlight_xap_url : MyAjax.silverlight_xap_url,

    // additional post data to send to our ajax hook - nonce and action name
    multipart_params : {
    	_ajax_nonce : MyAjax._ajax_nonce,
		action : 'humcore_upload_handler'
	},
	
	filters : {
		max_file_size : '100mb',
		mime_types: [
			{ title : 'Image files', extensions : 'gif,jpeg,jpg,png,psd,tiff' },
//			{ title : 'Raw Image files', extensions : 'cr2,crw,dng,nef' },
			{ title : 'Web files', extensions : 'htm,html' }, //css,js maybe?
			{ title : 'Archive files', extensions : 'gz,rar,tar,zip' },
			{ title : 'Document files', extensions : 'csv,doc,docx,odp,ods,odt,pdf,ppt,pptx,pps,rdf,rtf,sxc,sxi,sxw,txt,tsv,wpd,xls,xlsx,xml' },
			{ title : 'Audio files', extensions : 'mp3,ogg,wav' },
			{ title : 'Video files', extensions : 'f4v,flv,mov,mp4' }
 		]
	},
	init: {
		PostInit: function() {
			document.getElementById( 'filelist' ).innerHTML = '';
//			document.getElementById( 'uploadfile' ).onclick = function() {
//				uploader.start();
//				return false;
//			};
		},
		FilesAdded: function( up, files ) {
			// only one file allowed for this phase
			if ( up.files.length > 1 ) {
				up.splice( 0, 1 );
            }
			plupload.each( files, function( file ) {
				document.getElementById( 'filelist' ).innerHTML = '<div id="' + file.id + '">' + file.name + ' (' + plupload.formatSize( file.size ) + ') <b></b></div>';
			});
//			document.getElementById( 'uploadfile' ).focus();
			document.getElementById( 'progressbar' ).style.display = 'block';
			up.start();
		},
		UploadFile: function( up, file ) {
			document.getElementById( 'console' ).innerHTML = 'Uploading file ... ';
		},
		UploadProgress: function( up, file ) {
			if ( file.percent <= 100 && file.percent >= 1 ) {
				document.getElementById( 'indicator' ).style.width = file.percent + '%';
			}  
		},
		FileUploaded: function( up, file, info ) {
			var response = JSON.parse( info.response );
			if ( "0" == response.OK ) {
				document.getElementById( 'console' ).appendChild( document.createTextNode( "\nError #" + response.error.code + ": " + response.error.message ) );
				document.getElementById( 'indicator' ).style.width = '0%';
			} else {
//		   		document.getElementById( 'deposit-metadata-entries' ).style.display = 'block';
				document.getElementById( 'deposit-title' ).focus();
				document.getElementById( 'console' ).innerHTML = 'The file has been uploaded. Use the fields below to enter information about the file and press Deposit.';

				var file_size = document.createElement( 'input' );
				file_size.setAttribute( 'type', 'hidden' );
				file_size.setAttribute( 'name', 'selected_file_size' );
				file_size.setAttribute( 'value',  file.size );
				document.getElementById( 'deposit-form' ).appendChild( file_size );

                var target_name = document.createElement( 'input' );
                target_name.setAttribute( 'type', 'hidden' );
                target_name.setAttribute( 'name', 'selected_temp_name' );
                target_name.setAttribute( 'value',  file.target_name );
                document.getElementById( 'deposit-form' ).appendChild( target_name );

                var file_name = document.createElement( 'input' );
                file_name.setAttribute( 'type', 'hidden' );
                file_name.setAttribute( 'name', 'selected_file_name' );
                file_name.setAttribute( 'value',  file.name );
                document.getElementById( 'deposit-form' ).appendChild( file_name );

                var file_type = document.createElement( 'input' );
                file_type.setAttribute( 'type', 'hidden' );
                file_type.setAttribute( 'name', 'selected_file_type' );
                file_type.setAttribute( 'value',  file.type );
                document.getElementById( 'deposit-form' ).appendChild( file_type );

			}
		},
		Error: function( up, err ) {
			if ( err.code == "-600" )	{
				document.getElementById( 'console' ).appendChild( document.createTextNode( '\nSorry, the size of that file exceeds our 100MB limit!' ) );

			} else if ( err.code == "-601" )	{
				document.getElementById( 'console' ).appendChild( document.createTextNode( '\nSorry, that type of file cannot be selected.' ) );

			} else {
				document.getElementById( 'console' ).appendChild( document.createTextNode( '\nError #' + err.code + ': ' + err.message ) );
			}
			return false;
		}
	}

} );

uploader.init();

} );

// Deposit select 2 controls
jQuery(document).ready( function($) {

	$(".js-basic-multiple").select2({
		maximumSelectionLength: 5,
		width: "75%"
	});
	$(".js-basic-multiple-tags").select2({
		maximumSelectionLength: 5,
		width: "75%",
		tags: "true",
		tokenSeparators: [',']
	});

	$(".js-basic-single-required").select2({
		minimumResultsForSearch: "36",
		width: "40%"
	});
	$(".js-basic-single-optional").select2({
		allowClear: "true",
		minimumResultsForSearch: "36",
		width: "40%"
	});
} );
