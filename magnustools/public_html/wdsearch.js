/*
USAGE:
On en.wikipedia, add the following line:

importScript('MediaWiki:Wdsearch.js');

to your [[Special:Mypage/common.js|common.js]] page. On other Wikipedias, add

importScriptURI("//en.wikipedia.org/w/index.php?title=MediaWiki:Wdsearch.js&action=raw&ctype=text/javascript");

instead. To change the header line to your language, have an admin add the appropriate line to this page.
 
*/

var prevent_wd_auto_desc = true ; // No auto-run
importScriptURI("//tools.wmflabs.org/wikidata-todo/autodesc.js");

$(document).ready ( function () {
	var testing = false ;
	if ( testing ) console.log ( "Initiating WDsearch") ;
	var mode = 'searchresults' ;
	var results = $('div.searchresults') ;
	if ( results.length == 0 ) {
		mode = 'noarticletext' ;
		results = $('#noarticletext') ;
	}

	if ( results.length == 0 ) return ; // No search results, no search page. Bye.

	mw.loader.load( ['jquery.ui.dialog'] );

	var i18n = {
		'en' : {
			'commons_cat' : 'Commons category' ,
			'wikipedias' : 'Wikipedia articles' ,
			'header' : 'Wikidata search results'
		} ,
		'de' : {
			'commons_cat' : 'Kategorie auf Commons' ,
			'wikipedias' : 'Wikipedia-Artikel' ,
			'header' : 'Wikidata-Suchergebnisse'
		},
		'pt' : {
			'commons_cat' : 'Commons category' ,
			'wikipedias' : 'Wikipedia articles' ,
			'header' : 'Resultados da busca no Wikidata'
		},
		'pt-br' : {
			'commons_cat' : 'Commons category' ,
			'wikipedias' : 'Wikipedia articles' ,
			'header' : 'Resultados da busca no Wikidata'
		}
	} ;
	var i18n_lang = wgUserLanguage ;
	if ( undefined === i18n[i18n_lang] ) i18n_lang = 'en' ; // Fallback

	if ( testing ) console.log ( "Preparing WDsearch" ) ;
	
	var api = '//www.wikidata.org/w/api.php?callback=?' ;

	function run () {

		if ( testing ) console.log ( "Trying to run WDsearch") ;

		if ( typeof(wd_auto_desc) == 'undefined' ) {
			setTimeout ( run , 100 ) ;
			return ;
		}

		if ( testing ) console.log ( "Running WDsearch") ;

		wd_auto_desc.lang = wgUserLanguage ;
		
		var query ;
		if ( mode == 'searchresults' ) {
			query = $('#powerSearchText').val() ;
			if ( $('#powerSearchText').length == 0 ) query = $('#searchText').val() ;
		} else if ( mode == 'noarticletext' ) query = wgPageName ;

		if ( testing ) console.log ( "Using mode " + mode + " and query :" + query ) ;

		$.getJSON ( api , {
			action:'query',
			list:'search',
			srsearch:query,
			srnamespace:0,
			format:'json'
		} , function (d) {
			if ( testing ) console.log(d);
			if ( undefined === d.query || undefined === d.query.search || d.query.search.length == 0 ) return ; // No result
			
			var ids = [] ;
			var q = [] ;
			var h = "<div id='wdsearch_container'>" ;
			h += '<h3>' ;
			h += i18n[i18n_lang].header ;
			h += '</h3><table><tbody>' ;
			$.each ( d.query.search , function ( k , v ) {
				q.push ( v.title ) ;
				var title = [] ;
				var snip = $('<span>'+v.snippet+'</span>') ;
				$.each ( snip.find('span.searchmatch') , function ( a , b ) {
					var txt = $(b).text() ;
					if ( -1 != $.inArray ( txt , title ) ) return ;
					title.push ( txt ) ;
				} )
				if ( title.length == 0 ) title = [ v.title ] ; // Fallback to Q
				ids.push ( v.title ) ;
				h += "<tr id='" + v.title + "'>" ;
				h += "<th><a class='wd_title' href='//www.wikidata.org/wiki/" + v.title + "'>" + title.join ( ' ' ) + "</a></th>" ;
				h += "<td><span class='wd_desc'></span><span class='wd_manual_desc'></span></td>" ;
				h += "<td>" ;
				h += "<span class='wikipedias'></span>" ;
				h += "<span class='commonscat'></span>" ;
				h += "<a title='Reasonator' href='//tools.wmflabs.org/reasonator/?lang="+wgUserLanguage+"&q="+v.title+"'><i>R</i></a>" ;
				h += "</td>" ;
				h += "</tr>" ;
			})
			h += "</tbody></table>" ;
			h += "</div>" ;
			
			
			if ( mode == 'searchresults' ) {
				$('#mw-content-text').append ( h ) ;
			} else if ( mode == 'noarticletext' ) {
				$('#noarticletext').append ( h ) ;
			}
			
			if ( ids.length == 0 ) return ;
			console.log('here3');
			$.getJSON ( api , {
				action:'wbgetentities',
				ids:ids.join('|'),
				format:'json',
				languages:wgUserLanguage
			} , function ( d ) {
				if ( d === undefined || d.entities === undefined ) return ; // Some error
				$.each ( d.entities , function ( q , v ) {
				
					if ( undefined !== v.claims['P373'] ) { // Commons cat
						var cat = v.claims['P373'][0].mainsnak.datavalue.value ;
						var h = " <a title='"+i18n[i18n_lang].commons_cat+"' href='//commons.wikimedia.org/wiki/Category:"+escape(cat)+"'><img src='https://upload.wikimedia.org/wikipedia/commons/thumb/4/4a/Commons-logo.svg/12px-Commons-logo.svg.png' border=0 /></a>" ;
						$('#'+q+' span.commonscat').html ( h ) ;
					}

					if ( undefined !== v.descriptions && undefined !== v.labels[wgUserLanguage] ) { // Label
						var h = v.labels[wgUserLanguage].value ;
						$('#'+q+' a.wd_title').html ( h ) ;
					}
					
					if ( undefined !== v.descriptions && undefined !== v.descriptions[wgUserLanguage] ) { // Manual desc
						var h = "; " + v.descriptions[wgUserLanguage].value ;
						$('#'+q+' span.wd_manual_desc').html ( h ) ;
					}
					
					if ( undefined !== v.sitelinks ) { // Wikipedia links
						var wikipedias = [] ;
						$.each ( v.sitelinks , function ( site , v2 ) {
							var m = site.match ( /^(.+)wiki$/ ) ;
							if ( null == m  ) return ; // Wikipedia only
							wikipedias.push ( { site:site , title:v2.title , url:'//'+m[1]+'.wikipedia.org/wiki/'+escape(v2.title) } ) ;
						} ) ;
						if ( wikipedias.length > 0 ) {
							var h = " <a title='"+i18n[i18n_lang].wikipedias+"' href='#'><img src='https://upload.wikimedia.org/wikipedia/commons/thumb/8/80/Wikipedia-logo-v2.svg/14px-Wikipedia-logo-v2.svg.png' border=0 /></a>" ;
							$('#'+q+' span.wikipedias').html ( h ) ;
							$('#'+q+' span.wikipedias a').click ( function () {
								var did = 'wdsearch_dialog' ;
								$('#'+did).remove() ; // Cleanup
								var h = "<div title='"+i18n[i18n_lang].wikipedias+"' id='"+did+"'><div style='overflow:auto'>" ;
								h += "<table class='table table-condensed table-striped'>" ;
								h += "<thead><tr><th>Site</th><th>Page</tr></thead><tbody>" ;
								$.each ( wikipedias , function ( k , v3 ) {
									h += "<tr><td>" + v3.site + "</td><td>" ;
									h += "<a href='"+v3.url+"'>" + v3.title + "</a>" ;
									h += "</td></tr>" ;
								} ) ;
								h += "</tbody></table>" ;
								h += "</div></div>" ;
								$('#wdsearch_container').prepend ( h ) ;
								$('#'+did).dialog ( {
									modal:true
								} )
								return false ;
							} ) ;
						}
					}
					
				} ) ;
			} ) ;
			
			
			$.each ( q , function ( k , v ) {
				wd_auto_desc.loadItem ( v , {
					target:$('#'+v+' span.wd_desc') ,
					links : 'wikipedia' ,
//					callback : function ( q , html , opt ) { console.log ( q + ' : ' + html ) } ,
//					linktarget : '_blank'
				} ) ;
			})
			
		})
	}
	
	run() ;
})
