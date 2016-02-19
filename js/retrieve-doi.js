// A script to retrieve a DOI's metadata from CrossRef for CORE
// Copyright (c) Martin Paul Eve 2015
// Released under the MIT license
// Uses a component from DOI Regex by Richard Littauer (https://github.com/regexps/doi-regex) under an MIT license


 jQuery(document).ready(function($)
    {
        // Inject the DOI lookup field
        var element = $('#deposit-title-entry');
        var content = $('<div id="lookup-doi-entry"><label for="lookup-doi">Retrieve journal article metadata from DOI (optional)</label><input type="text" id="lookup-doi" name="lookup-doi" class="long" value="" /> <button onClick="javascript:retrieveDOI(); return false;">Retrieve</button> <span style="color:red" id="lookup-doi-message"></span></div>');
	// not used
        // content.insertBefore(element);
    });

 function returnJSON(response, element)
    {
      // Return an element from the JSON or a blank
      // if there is no such element
      try
      {
        return response[element];
      }
      catch (err)
      {
        return "";
      }
    }

 function testDOI(DOI, DOIregex)
    {
      // Check if a string is a valid DOI
      DOI = DOI || {};
      matcher = DOI.exact ? new RegExp('^' + DOIregex + '$') : new RegExp(DOIregex, 'g');
      return matcher.exec(DOI);
    }

 function retrieveDOI()
    {
      // Lookup a DOI and fill in the fields for the user
      // Journals only at the moment
      $ = jQuery;
      var response = '';
      var DOI = $('#lookup-doi').val();
      var url = 'https://api.crossref.org/works/' + DOI;
      var DOIregex = '(10[.][0-9]{4,}(?:[.][0-9]+)*/(?:(?![%"#? ])\\S)+)';
      var message = $('#lookup-doi-message');

      if (testDOI(DOI, DOIregex) == null)
      {
        message.text('Please enter a valid DOI.');
        resetFields();
        return false;
      }

      // Use Yahoo! pipes for this request to circumvent
      // same-origin policy. An alternative would be to
      // write our own server-side proxy.
      // Now using CORS.

      message.text('Retrieving information.');

      $.ajax({
          type: "GET",
          accepts: "application/vnd.citationstyles.csl+json",
          url: url,
//          async: false,
          crossDomain: true,
          dataType: 'json',
          error: function (data)
          {
            if ( data.status == 404 )
            { 
              message.text('That DOI was not found in CrossRef.');
            }
            else
            {
              message.text(data.responseText);
            }
            console.dir(data);
            resetFields();
            return false;
          },
          success: function (data)
          {
            // Make sure we haven't changed type
            resetFields();

            // parse the received JSON
            var deposittype = returnJSON(data.message, "type");
            if (deposittype != 'journal-article' && deposittype != 'book-chapter' && deposittype != 'proceedings-article')
            {
              message.text('Sorry, we only support information retrieval for a journal article, a book chapter or a conference proceeding at this time.');
              return false;
            }

            var title = returnJSON(data.message, "title");
            var containertitle = returnJSON(data.message, "container-title");
            var subject = returnJSON(data.message, "subject");
            var pages = returnJSON(data.message, "page");

            if (pages == '' || pages == null)
            { 
              pages = ['',''];
            }
            else if (pages.indexOf('-') == -1) 
            {
              pages = ['','']; 
            }
            else
            {
              pages = pages.split('-'); 
            }

            var DOIUrl = returnJSON(data.message, "URL");
            var publisher = returnJSON(data.message, "publisher");
            var issn = returnJSON(data.message, "ISSN");

            if (typeof(issn) == 'Array')
            {
              // Multiple ISSNs can be returned so here we take the first if it's an array
              issn = issn[0];
            }

            var volume = returnJSON(data.message, "volume");
            var issue = returnJSON(data.message, "issue");
            var createddate = data.message["created"]["date-parts"][0];

            var chapter = returnJSON(data.message, "chapter");
            var crossref_isbn = returnJSON(data.message, "ISBN");

            if (typeof crossref_isbn !== "undefined" && crossref_isbn != '')
            {
              // Multiple ISBNs can be returned so here we take the first if it's an array
              var first_isbn = crossref_isbn[0];
              // Parse isbn, we don't want the full url.
              var isbn_regex = /^http.+?isbn\/(.+?)$/;
              var isbn_matches = first_isbn.match(isbn_regex);
              var isbn = isbn_matches[1];
            }
            else
            {
              var isbn = '';
            }

            var author = returnJSON(data.message, "author");
            var authors = [];
            if (typeof author !== "undefined" && author != '')
            {
              $.each( author, function( i, val )
              {
                authors.push( val["given"] + ' ' + val["family"] );
              });
            }

            var editor = returnJSON(data.message, "editor");
            var editors = [];
            if (typeof editor !== "undefined" && editor != '')
            {
              $.each( editor, function( i, val )
              {
                editors.push( val["given"] + ' ' + val["family"] );
              });
            }

            var author_editor = authors.join(", ");
            if (author_editor == '' || author_editor == null)
            {
              author_editor = editors.join(", ");
            }

            $('#deposit-title-unchanged').val(title);

            if (deposittype == 'journal-article')
            {
              // update "Item Type"
              $('#deposit-genre').val("Article").trigger("change");

              // update published item type
              $('input[type="radio"][name="deposit-publication-type"][value="journal-article"]').prop('checked', true);
              $('input[type="radio"][name="deposit-publication-type"][value="journal-article"]').click();
                
              // update journal fields
              $('#deposit-journal-doi').val(DOI);
              $('#deposit-journal-publisher').val(publisher);
              $('#deposit-journal-title').val(containertitle);
              $('#deposit-journal-issn').val(issn);
              $('#deposit-journal-volume').val(volume);
              $('#deposit-journal-issue').val(issue);
              $('#deposit-journal-start-page').val(pages[0]);
              $('#deposit-journal-end-page').val(pages[1]);
              if (createddate != null)
              {
                $('#deposit-journal-publish-date').val(createddate[0] + "-" + createddate[1] + "-" + createddate[2]);
              }
              message.text('We found information for that journal article! You can review it before submitting your deposit.');
            }
            else if (deposittype == 'book-chapter')
            {
              // update "Item Type"
              $('#deposit-genre').val("Book chapter").trigger("change");

              // update published item type
              $('input[type="radio"][name="deposit-publication-type"][value="book-chapter"]').prop('checked', true);
              $('input[type="radio"][name="deposit-publication-type"][value="book-chapter"]').click();
                
              // update book chapter fields
              $('#deposit-book-doi').val(DOI);
              $('#deposit-book-publisher').val(publisher);
              $('#deposit-book-title').val(containertitle);
              $('#deposit-book-author').val(author_editor);
              $('#deposit-book-isbn').val(isbn);
              $('#deposit-book-chapter').val(chapter);
              $('#deposit-book-start-page').val(pages[0]);
              $('#deposit-book-end-page').val(pages[1]);
              if (createddate != null)
              {
                $('#deposit-book-publish-date').val(createddate[0] + "-" + createddate[1] + "-" + createddate[2]);
              }
              message.text('We found information for that book chapter! You can review it before submitting your deposit.');
            }
            else if (deposittype == 'proceedings-article')
            {
              // update "Item Type" and also its visible rendering
              $('#deposit-genre').val("Conference proceeding").trigger("change");

              // update published item type
              $('input[type="radio"][name="deposit-publication-type"][value="proceedings-article"]').prop('checked', true);
              $('input[type="radio"][name="deposit-publication-type"][value="proceedings-article"]').click();
                
              // update conference proceeding fields
              $('#deposit-proceeding-doi').val(DOI);
              $('#deposit-proceeding-publisher').val(publisher);
              $('#deposit-proceeding-title').val(containertitle);
              $('#deposit-proceeding-start-page').val(pages[0]);
              $('#deposit-proceeding-end-page').val(pages[1]);
              if (createddate != null)
              {
                $('#deposit-proceeding-publish-date').val(createddate[0] + "-" + createddate[1] + "-" + createddate[2]);
              }
              message.text('We found information for that conference proceeding! You can review it before submitting your deposit.');
            }
        }
      });

    }

 function resetFields()
    {
      $('#deposit-title-unchanged').val("");

      // update "Item Type"
      $('#deposit-genre').val("").trigger("change");

      // update published item type
      $('input[type="radio"][name="deposit-publication-type"]:checked').prop('checked', false);
      $('input[type="radio"][name="deposit-publication-type"][value="none"]').prop('checked', true);
//      $('input[type="radio"][name="deposit-publication-type"][value="none"]').click();

      // update journal fields
      $('#deposit-journal-doi').val("");
      $('#deposit-journal-publisher').val("");
      $('#deposit-journal-title').val("");
      $('#deposit-journal-issn').val("");
      $('#deposit-journal-volume').val("");
      $('#deposit-journal-issue').val("");
      $('#deposit-journal-start-page').val("");
      $('#deposit-journal-end-page').val("");
      $('#deposit-journal-publish-date').val("");

      // update book chapter fields
      $('#deposit-book-doi').val("");
      $('#deposit-book-publisher').val("");
      $('#deposit-book-title').val("");
      $('#deposit-book-author').val("");
      $('#deposit-book-isbn').val("");
      $('#deposit-book-chapter').val("");
      $('#deposit-book-start-page').val("");
      $('#deposit-book-end-page').val("");
      $('#deposit-book-publish-date').val("");

      // update conference proceeding fields
      $('#deposit-proceeding-doi').val("");
      $('#deposit-proceeding-publisher').val("");
      $('#deposit-proceeding-title').val("");
      $('#deposit-proceeding-start-page').val("");
      $('#deposit-proceeding-end-page').val("");
      $('#deposit-proceeding-publish-date').val("");

    }
