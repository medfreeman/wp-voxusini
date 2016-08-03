/* global wpvox */
( function( $ ) {

	function isNumber( num ) {
		return !isNaN( parseFloat( num ) ) && isFinite( num );
	}

	var $yearSelect = $( '#' + wpvox.yearField ),
		$monthSelect = $( '#' + wpvox.monthField );

	$yearSelect.change( function() {
		var curYear = $yearSelect.selectedValues()[0],
			postId,
			data;

		postId = wpvox.postId;
		if ( !isNumber( postId ) ) {
			postId = 0;
		}

		$monthSelect.attr( 'disabled', 'disabled' );

		data = {
			action: wpvox.action,
			year: curYear,
			postId: postId
		};

		data[wpvox.nonceField] = wpvox.nonce;

		$.ajax({ // Ask for available months
			url: wpvox.ajaxUrl,
			type: 'POST',
			data: data,
			success: function( result ) {
				$monthSelect.html( result.html );
				$monthSelect.removeAttr( 'disabled', '' );
			},
			dataType: 'json'
		});

		// $month_select.trigger('change');
	});

	$yearSelect.trigger( 'change' );
})( jQuery );
