// A script to retrieve a DOI's metadata from CrossRef for CORE
// Copyright (c) Martin Paul Eve 2015
// Released under the MIT license
// Uses a component from DOI Regex by Richard Littauer (https://github.com/regexps/doi-regex) under an MIT license


 jQuery(document).ready(function($)
    {
        // Inject the DOI lookup field
        var element = $('#deposit-title-entry');
        var content = $('<div id="lookup-doi-entry"><label for="lookup-doi">Retrieve journal article metadata from DOI (optional)</label><input type="text" id="lookup-doi" name="lookup-doi" class="long" value="" /> <button onClick="javascript:retrieveDOI(); return false;">Retrieve</button> <span style="color:red" id="lookup-doi-error"></span></div>');
        content.insertBefore(element);
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
      var error = $('#lookup-doi-error');

      if (testDOI(DOI, DOIregex) == null)
      {
        error.text('Please enter a valid DOI.');
        return;
      }
      
      // Use Yahoo! pipes for this request to circumvent
      // same-origin policy. An alternative would be to
      // write our own server-side proxy.

      error.text('Retrieving DOI.');

      $.ajax({
          type: "GET",
          accepts: "application/vnd.citationstyles.csl+json",
          url: url,
          async: false,
          crossDomain: true,
          dataType: 'json',
          success: function (data) {
              console.log(data);

              // parse the received JSON
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
              var deposittype = returnJSON(data.message, "type");
              var issn = returnJSON(data.message, "ISSN");

              if (typeof(issn) == 'Array')
              {
                // Multiple ISSNs can be returned so here we take the first if it's an array
                issn = issn[0];
              }

              var volume = returnJSON(data.message, "volume");
              var issue = returnJSON(data.message, "issue");
              var createddate = data.message["created"]["date-parts"][0];
              
              $('#deposit-title-unchanged').val(title);

              if (deposittype == 'journal-article') {
                // update "Item Type" and also its visible rendering
                $('#deposit-genre').val("Article");
                $('#select2-deposit-genre-container').text('Article');
                $('#select2-deposit-genre-container').attr('title', 'Article');

                // update published item type
                $('input[value="journal-article"]').attr('checked', 'checked');
                $('input[value="journal-article"]').click();
                
                // update journal fields
                $('#deposit-journal-doi').val(DOI);
                $('#deposit-journal-publisher').val(publisher);
                $('#deposit-journal-title').val(containertitle);
                $('#deposit-journal-issn').val(issn);
                $('#deposit-journal-volume').val(volume);
                $('#deposit-journal-issue').val(issue);
                $('#deposit-journal-start-page').val(pages[0]);
                $('#deposit-journal-end-page').val(pages[1]);
                if (createddate != null) {
                    $('#deposit-journal-publish-date').val(createddate[0] + "-" + createddate[1] + "-" + createddate[2]);
                }
              }
              
          }
      });

      error.text('Done.');
  }
