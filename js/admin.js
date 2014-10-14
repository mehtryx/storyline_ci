jQuery( document ).ready( function() {
	jQuery( "#sc_select_storyline" ).change( function() {
			send_to_editor( jQuery( "#sc_select_storyline :selected" ).val() );
			return false;
	} );
} );