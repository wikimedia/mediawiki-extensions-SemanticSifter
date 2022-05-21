( function ( $, mw ) {

	function serializeObject( obj ) {
		var o = {},
			a = obj.serializeArray();
		$.each( a, function () {
			if ( o[ this.name ] !== undefined ) {
				if ( !o[ this.name ].push ) {
					o[ this.name ] = [ o[ this.name ] ];
				}
				o[ this.name ].push( this.value || '' );
			} else {
				o[ this.name ] = this.value || '';
			}
		} );
		return o;
	}

	$( function () {
		var container = $( '.ss-container' ),
			form = $( '.ss-filteringform' );

		form.each( function () {
			$( this ).on( 'submit', function () {
				var filters = serializeObject( form ),
					data = $.get(
						mw.util.wikiScript(),
						{
							action: 'ajax',
							rs: 'SemanticSifter\\API\\API::filter',
							rsargs: [ JSON.stringify( filters ) ]
						}
					).done( function ( response ) {
						var uri = new mw.Uri();
						uri.extend( { filter: response } );
						window.location.href = uri;
					} );

				return false;
			} );
		} );

		container.find( '.ss-propertyfilter > select' ).chosen( { width: '100%' } );

		for ( var key in window.SemanticSifter ) {
			var config = window.SemanticSifter[ key ];
			$( '#' + key ).find( '.ss-propertyfilter' ).width( config[ 'filterbox-width' ] );

		}

		container.fadeIn();
	} );
}( jQuery, mediaWiki ) );
