var showNotice, adminMenu, columns;
(function($){
// sidebar admin menu
adminMenu = {
	init : function() {
		$('.wp-menu-toggle', '#adminmenu')
		.each( function() {
			var t = $(this);
			if ( t.siblings('.wp-submenu').length )
				t.click(function(){ adminMenu.toggle( $(this).siblings('.wp-submenu') ); });
			else
				t.hide();
		});
		//$.log('wp-menu-toggle');
		
		this.favorites();
		//$.log('favorites');
		
		$('.separator', '#adminmenu')
		.click(function(){
			if ( $('body').hasClass('folded') ) {
				adminMenu.fold(1);
				deleteUserSetting( 'mfold' );
			} else {
				adminMenu.fold();
				setUserSetting( 'mfold', 'f' );
			}
			return false;
		});
		//$.log('admin menu - separator');
		
		if ( $('body').hasClass('folded') ) {
			this.fold();
		}
		//$.log('admin menu - fold');
		
		this.restoreMenuState();
		//$.log('admin menu - restore state');
	},

	restoreMenuState : function() {
		$('.wp-has-submenu', '#adminmenu')
		.each(function(i, e) {
			var v = getUserSetting( 'm'+i );
			if ( $(e).hasClass('wp-has-current-submenu') )
				return true; // leave the current parent open

			if ( 'o' == v )
				$(e).addClass('wp-menu-open');
			else if ( 'c' == v )
				$(e).removeClass('wp-menu-open');
		});
	},

	toggle : function(el) {
		el['slideToggle'](150, function() {
			el.css('display','');
		}).parent().toggleClass( 'wp-menu-open' );

		$('.wp-has-submenu', '#adminmenu')
		.each(function(i, e) {
			var v = $(e).hasClass('wp-menu-open') ? 'o' : 'c';
			setUserSetting( 'm'+i, v );
		});

		return false;
	},

	fold : function(off) {
		if (off) {
			$('body').removeClass('folded');
			$('li.wp-has-submenu', '#adminmenu').unbind();
		} else {
			$('body').addClass('folded');
			$('li.wp-has-submenu', '#adminmenu')
			.hoverIntent({
				over: function(e) {
					var m, b, h, o, f;
					m = $('.wp-submenu', this);
					b = m.parent().offset().top + m.height() + 1; // Bottom offset of the menu
					h = $('#wpwrap').height(); // Height of the entire page
					o = 60 + b - h;
					f = $(window).height() + $('body').scrollTop() - 15; // The fold
					if (f < (b - o)) {
						o = b - f;
					}
					if (o > 1) {
						m.css({'marginTop':'-'+o+'px'});
					} else if ( m.css('marginTop') ) {
						m.css({'marginTop':''});
					}
					m.addClass('sub-open');
				},
				out: function() {
					$('.wp-submenu', this).removeClass('sub-open').css({'marginTop':''});
				},
				timeout: 220,
				sensitivity: 8,
				interval: 100
			});
		}
	},

	favorites : function() {
		$('#favorite-inside').width($('#favorite-actions').width() - 4);
		
		$('#favorite-toggle, #favorite-inside')
		.bind('mouseenter', function() {
			$('#favorite-inside').removeClass('slideUp').addClass('slideDown');
			setTimeout(function() {
				if ( $('#favorite-inside').hasClass('slideDown') ) {
					$('#favorite-inside').slideDown(100);
					$('#favorite-first').addClass('slide-down');
				}
			}, 200);
		}).bind('mouseleave', function() {
			$('#favorite-inside').removeClass('slideDown').addClass('slideUp');
			setTimeout(function() {
				if ( $('#favorite-inside').hasClass('slideUp') ) {
					$('#favorite-inside').slideUp(100, function() {
						$('#favorite-first').removeClass('slide-down');
					});
				}
			}, 300);
		});
	}
};

$(document).ready(function(){
	//$.log('common.js - start');
	adminMenu.init();
});

// show/hide/save table columns
columns = {
	init : function() {
		$('.hide-column-tog', '#adv-settings')
		.click( function() {
			var column = $(this).val(), show = $(this).attr('checked');
			if ( show ) {
				$('.column-' + column).show();
			} else {
				$('.column-' + column).hide();
			}
			columns.save_manage_columns_state();
		});
		//$.log('table columns init');
	},

	save_manage_columns_state : function() {
		var hidden = $('.manage-column').filter(':hidden')
		.map(function() { return this.id; })
		.get().join(',');
		$.post(ajaxurl, {
			action: 'hidden-columns',
			hidden: hidden,
			screenoptionnonce: $('#screenoptionnonce').val(),
			page: pagenow
		});
	}
}

$(document).ready(function(){
	columns.init();
});
})(jQuery);

// stub for doing better warnings
showNotice = {
	warn : function() {
		var msg = commonL10n.warnDelete || '';
		if ( confirm(msg) ) {
			return true;
		}

		return false;
	},

	note : function(text) {
		alert(text);
	}
};

jQuery(document).ready( function($) {
	var lastClicked = false, checks, first, last, checked;

	// pulse
	$('div.fade').animate( { backgroundColor: '#ffffe0' }, 300)
	.animate( { backgroundColor: '#fffbcc' }, 300)
	.animate( { backgroundColor: '#ffffe0' }, 300)
	.animate( { backgroundColor: '#fffbcc' }, 300);
	//$.log('fade');

	// Move .updated and .error alert boxes
	$('div.wrap').children('h2:first').nextAll('div.updated, div.error')
	.addClass('below-h2');
	//$.log('lock div.updated, div.error')

	$('div.updated, div.error').not('.below-h2')
	.insertAfter($('div.wrap').children('h2:first'));
	//$.log('move div.updated, div.error');

	// show warnings
	$('#doaction, #doaction2').click(function () {
		if ( $('select[name="action"]').val() == 'delete' || $('select[name="action2"]').val() == 'delete' ) {
			return showNotice.warn();
		}
	});
	//$.log('doaction')

	// screen settings tab
	$('#show-settings-link')
	.click(function () {
		if ( ! $('#screen-options-wrap').hasClass('screen-options-open') ) {
			$('#contextual-help-link-wrap').css('visibility', 'hidden');
		}
		
		$('#screen-options-wrap')
		.slideToggle('fast', function(){
			if ( $(this).hasClass('screen-options-open') ) {
				$('#show-settings-link')
				.css({'backgroundImage':'url("images/screen-options-right.gif")'});
				
				$('#contextual-help-link-wrap').css('visibility', '');
				$(this).removeClass('screen-options-open');
			} else {
				$('#show-settings-link')
				.css({'backgroundImage':'url("images/screen-options-right-up.gif")'});
				
				$(this).addClass('screen-options-open');
			}
		});
		return false;
	});
	//$.log('show-settings-link')
	
	// help tab
	$('#contextual-help-link')
	.click(function () {
		if ( ! $('#contextual-help-wrap').hasClass('contextual-help-open') ) {
			$('#screen-options-link-wrap').css('visibility', 'hidden');
		}
		
		$('#contextual-help-wrap')
		.slideToggle('fast', function() {
			if ( $(this).hasClass('contextual-help-open') ) {
				$('#contextual-help-link')
				.css({'backgroundImage':'url("images/screen-options-right.gif")'});
				
				$('#screen-options-link-wrap').css('visibility', '');
				$(this).removeClass('contextual-help-open');
			} else {
				$('#contextual-help-link')
				.css({'backgroundImage':'url("images/screen-options-right-up.gif")'});
				
				$(this).addClass('contextual-help-open');
			}
		});
		return false;
	});
	//$.log('contextual-help-link');
	
	// this one is already taken care of by the hide-if-no-js class
	// show() and :hidden are extremely slow on slow rendering engines
	// e.g. Opera 9 with a 400kb widgets page gets:
	// 6ms -- $('#contextual-help-link-wrap')
	// 6071ms -- $('#contextual-help-link-wrap:hidden')
	// $('#contextual-help-link-wrap, #screen-options-link-wrap').show();

	// check all checkboxes
	$('tbody').children().children('.check-column').find(':checkbox')
	.click( function(e) {
		if ( 'undefined' == e.shiftKey ) { return true; }
		if ( e.shiftKey ) {
			if ( !lastClicked ) { return true; }
			checks = $( lastClicked ).closest( 'form' ).find( ':checkbox' );
			first = checks.index( lastClicked );
			last = checks.index( this );
			checked = $(this).attr('checked');
			if ( 0 < first && 0 < last && first != last ) {
				checks.slice( first, last ).attr( 'checked', function(){
					if ( $(this).closest('tr').is(':visible') )
						return checked ? 'checked' : '';

					return '';
				});
			}
		}
		lastClicked = this;
		return true;
	});
	//$.log('tbody checkboxes');
	
	$('thead, tfoot').find(':checkbox').click( function(e) {
		var c = $(this).attr('checked'),
			kbtoggle = 'undefined' == typeof toggleWithKeyboard ? false : toggleWithKeyboard,
			toggle = e.shiftKey || kbtoggle;
		
		$(this).closest( 'table' ).children( 'tbody' ).filter(':visible')
		.children().children('.check-column').find(':checkbox')
		.attr('checked', function() {
			if ( $(this).closest('tr').is(':hidden') )
				return '';
			if ( toggle )
				return $(this).attr( 'checked' ) ? '' : 'checked';
			else if (c)
				return 'checked';
			return '';
		});
		
		$(this).closest('table').children('thead,  tfoot').filter(':visible')
		.children().children('.check-column').find(':checkbox')
		.attr('checked', function() {
			if ( toggle )
				return '';
			else if (c)
				return 'checked';
			return '';
		});
	});
	//$.log('thead, tfoot checkboxes');
	
	$('#default-password-nag-no').click( function() {
		setUserSetting('default_password_nag', 'hide');
		$('div.default-password-nag').hide();
		return false;
	});
	//$.log('password nag');
});

jQuery(document).ready( function($){
	var turboNag = $('span.turbo-nag');

	if ( !turboNag.length || ('undefined' != typeof(google) && google.gears) )
		return;

	if ( 'undefined' != typeof GearsFactory ) {
		return;
	} else {
		try {
			if ( ( 'undefined' != typeof window.ActiveXObject && ActiveXObject('Gears.Factory') ) ||
				( 'undefined' != typeof navigator.mimeTypes && navigator.mimeTypes['application/x-googlegears'] ) ) {
					return;
			}
		} catch(e){}
	}

	turboNag.show();
	//$.log('turbo');
	//$.log('common.js - stop');
});