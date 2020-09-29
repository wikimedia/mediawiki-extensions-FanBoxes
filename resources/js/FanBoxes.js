( function ( mw, $ ) {

	var FanBoxes = {
		// Display right side of fanbox as user inputs info
		displayRightSide: function () {
			var rightSideOutput = document.form1.inputRightSide.value;
			document.getElementById( 'fanBoxRightSideOutput2' ).innerHTML = rightSideOutput;
		},

		/**
		 * Display left side as user inputs info and sets imagename value to empty
		 * (just in case he previously uploaded an image)
		 */
		displayLeftSide: function () {
			var leftSideOutput = document.form1.inputLeftSide.value;
			document.getElementById( 'fanBoxLeftSideOutput2' ).innerHTML = leftSideOutput;
			document.getElementById( 'fantag_image_name' ).value = '';
		},

		/**
		 * If user uploaded image, and then typed in text, and now wants to insert
		 * image again, he can just click it
		 */
		insertImageToLeft: function () {
			var imageElement = document.getElementById( 'fanbox_image' );
			document.getElementById( 'fantag_image_name' ).value = imageElement.value;
			document.getElementById( 'fanBoxLeftSideOutput2' ).innerHTML = imageElement.innerHTML;
			document.getElementById( 'inputLeftSide' ).value = '';
		},

		// Countdown as user types characters
		limitText: function ( limitField, limitCount, limitNum ) {
			if ( limitField.value.length > limitNum ) {
				limitField.value = limitField.value.substring( 0, limitNum );
			} else {
				limitCount.value = limitNum - limitField.value.length;
			}
		},

		// Limits the left side fanbox so user can't type in tons of characters without a space
		leftSideFanBoxFormat: function () {
			var str_left_side = document.form1.inputLeftSide.value,
				str_left_side_length = document.form1.inputLeftSide.value.length,
				space_position = str_left_side.substring(
					str_left_side_length - 5, str_left_side_length ).search( ' ' );
			if ( str_left_side.length < 6 ) {
				document.form1.inputLeftSide.maxLength = 11;
			}
			if ( space_position === -1 && str_left_side.length > 6 ) {
				document.form1.inputLeftSide.maxLength = str_left_side.length;
			}
			if ( space_position === -1 && str_left_side.length === 6 ) {
				document.form1.inputLeftSide.value =
					document.form1.inputLeftSide.value.substring( 0, 5 ) + ' ' +
					document.form1.inputLeftSide.value.substring( 5, 6 );
				document.getElementById( 'fanBoxLeftSideOutput2' ).innerHTML =
					document.form1.inputLeftSide.value.substring( 0, 5 ) + ' ' +
					document.form1.inputLeftSide.value.substring( 5, 7 );
			}
			if ( str_left_side.length >= 5 ) {
				document.getElementById( 'fanBoxLeftSideOutput2' ).style.fontSize = '14px';
				document.getElementById( 'textSizeLeftSide' ).value = 'mediumfont';
			} else {
				document.getElementById( 'fanBoxLeftSideOutput2' ).style.fontSize = '20px';
				document.getElementById( 'textSizeLeftSide' ).value = 'bigfont';
			}
		},

		/**
		 * Limits right side so user can't type in tons of characters without a
		 * space
		 */
		rightSideFanBoxFormat: function () {
			var str_right_side = document.form1.inputRightSide.value,
				str_right_side_length = document.form1.inputRightSide.value.length,
				space_position = str_right_side.substring(
					str_right_side_length - 17, str_right_side_length ).search( ' ' );
			if ( str_right_side.length < 18 ) {
				document.form1.inputRightSide.maxLength = 70;
			}
			if ( space_position === -1 && str_right_side.length > 18 ) {
				document.form1.inputRightSide.maxLength = str_right_side.length;
			}
			if ( space_position === -1 && str_right_side.length === 18 ) {
				document.form1.inputRightSide.value =
					document.form1.inputRightSide.value.substring( 0, 17 ) + ' ' +
					document.form1.inputRightSide.value.substring( 17, 18 );
				document.getElementById( 'fanBoxRightSideOutput2' ).innerHTML =
					document.form1.inputRightSide.value.substring( 0, 17 ) + ' ' +
					document.form1.inputRightSide.value.substring( 17, 19 );
			}

			if ( str_right_side.length >= 52 ) {
				document.getElementById( 'fanBoxRightSideOutput2' ).style.fontSize = '12px';
				document.getElementById( 'textSizeRightSide' ).value = 'smallfont';
			} else {
				document.getElementById( 'fanBoxRightSideOutput2' ).style.fontSize = '14px';
				document.getElementById( 'textSizeRightSide' ).value = 'mediumfont';
			}
		},

		/**
		 * The below 3 functions are used to open, add/remove, and close the fanbox
		 * popup box when you click on it
		 *
		 * @param popupBox
		 * @param fanBox
		 */
		openFanBoxPopup: function ( popupBox, fanBox ) {
			var $popupBox = $( '#' + popupBox ),
				$fanBox = $( '#' + fanBox );
			if ( $popupBox.is( ':visible' ) ) {
				$popupBox.hide();
			} else {
				$popupBox.show();
			}
			if ( !$fanBox.is( ':visible' ) ) {
				$fanBox.show();
			} else {
				$fanBox.hide();
			}
		},

		closeFanboxAdd: function ( popupBox, fanBox ) {
			var $popupBox = $( '#' + popupBox ),
				$fanBox = $( '#' + fanBox );
			$popupBox.hide();
			$fanBox.show();
		},

		/**
		 * Display image box
		 *
		 * @param el
		 * @param el2
		 * @param el3
		 */
		displayAddImage: function ( el, el2, el3 ) {
			el = document.getElementById( el );
			el.style.display = ( el.style.display === 'block' ) ? 'none' : 'block';
			el2 = document.getElementById( el2 );
			el3 = document.getElementById( el3 );
			el2.style.display = 'none';
			el3.style.display = 'inline';
		},

		/**
		 * Insert a tag (category) from the category cloud into the inputbox below
		 * it on Special:UserBoxes
		 *
		 * @param tagname String: category name
		 * @param tagnumber Integer
		 */
		insertTag: function ( tagname, tagnumber ) {
			$( '#tag-' + tagnumber ).css( 'color', '#CCCCCC' ).html( tagname );
			// Funny...if you move this getElementById call into a variable and use
			// that variable here, this won't work as intended
			document.getElementById( 'pageCtg' ).value += ( ( document.getElementById( 'pageCtg' ).value ) ? ', ' : '' ) + tagname;
		},

		showMessage: function ( addRemove, title, fantagId ) {
			$.post(
				mw.util.wikiScript( 'api' ), {
					action: 'fanboxes',
					what: 'showAddRemoveMessage',
					addRemove: addRemove,
					title: title,
					fantagId: fantagId,
					format: 'json'
				},
				function ( data ) {
					$( '#show-message-container' + fantagId ).html( data.fanboxes.result ).fadeIn( 1000 );
				}
			);
		},

		/**
		 * @param addRemove
		 * @param id
		 * @param style
		 * @todo FIXME: the animations suck
		 */
		showAddRemoveMessageUserPage: function ( addRemove, id, style ) {
			var $container = $( '#show-message-container' + id );
			$container.fadeOut( 1000 );

			$.post(
				mw.util.wikiScript( 'api' ), {
					action: 'fanboxes',
					what: 'messageAddRemoveUserPage',
					addRemove: addRemove,
					fantagId: id,
					style: style,
					format: 'json'
				},
				function ( data ) {
					$container.html( data.fanboxes.result ).fadeIn( 1000 );
				}
			);
		},

		/**
		 * Create a fantag, performing various checks before submitting the
		 * document.
		 *
		 * Moved from SpecialFanBoxes.php
		 */
		createFantag: function () {
			if ( !document.getElementById( 'inputRightSide' ).value ) {
				alert( mw.msg( 'fanbox-mustenter-right-or' ) );
				return '';
			}

			if (
				!document.getElementById( 'inputLeftSide' ).value &&
				!document.getElementById( 'fantag_image_name' ).value
			) {
				alert( mw.msg( 'fanbox-mustenter-left' ) );
				return '';
			}

			var title = document.getElementById( 'wpTitle' ).value;
			if ( !title ) {
				alert( mw.msg( 'fanbox-mustenter-title' ) );
				return '';
			}

			if ( title.indexOf( '#' ) > -1 ) {
				alert( mw.msg( 'fanbox-hash' ) );
				return '';
			}

			// Encode ampersands
			title = title.replace( '&', '%26' );

			( new mw.Api() ).get( {
				action: 'query',
				titles: mw.config.get( 'wgFormattedNamespaces' )[ 600 ] + ':' + title,
				format: 'json',
				formatversion: 2
			} ).done( function ( data ) {
				// Missing page means that we can create it, obviously!
				if ( data.query.pages[ 0 ] && data.query.pages[ 0 ].missing === true ) {
					document.form1.submit();
				} else {
					// could also show data.query.pages[0].invalidreason to the user here instead
					alert( mw.msg( 'fan-addfan-exists' ) );
				}
			} );
		},

		/**
		 * Simpler version of FanBoxes.createFantag(); this one checks that the
		 * right side input has something and that the left side input has
		 * something and then submits the form.
		 */
		createFantagSimple: function () {
			if ( !document.getElementById( 'inputRightSide' ).value ) {
				alert( mw.msg( 'fanbox-mustenter-right' ) );
				return '';
			}

			if (
				!document.getElementById( 'inputLeftSide' ).value &&
				!document.getElementById( 'fantag_image_name' ).value
			) {
				alert( mw.msg( 'fanbox-mustenter-left' ) );
				return '';
			}

			document.form1.submit();
		},

		resetUpload: function () {
			var frame = document.getElementById( 'imageUpload-frame' );
			frame.src = mw.config.get( 'wgScriptPath' ) + '/index.php?title=Special:FanBoxAjaxUpload';
			frame.style.display = 'block';
			frame.style.visibility = 'visible';
		},

		completeImageUpload: function () {
			var html = '<div style="margin:0px 0px 10px 0px;"><img height="30" width="30" src="' +
				mw.config.get( 'wgExtensionAssetsPath' ) + '/FanBoxes/resources/images/ajax-loader-white.gif" alt="" /></div>';
			document.getElementById( 'fanbox_image' ).innerHTML = html;
			document.getElementById( 'fanBoxLeftSideOutput2' ).innerHTML = html;
		},

		uploadComplete: function ( img_tag, img_name ) {
			document.getElementById( 'fanbox_image' ).innerHTML = img_tag;
			document.getElementById( 'fanbox_image2' ).innerHTML =
				'<p><a href="javascript:FanBoxes.resetUpload();">' +
				mw.msg( 'fanbox-upload-new-image' ) + '</a></p>';
			document.getElementById( 'fanbox_image' ).value = img_name;

			document.getElementById( 'fanBoxLeftSideOutput2' ).innerHTML = img_tag;
			document.getElementById( 'fantag_image_name' ).value = img_name;

			document.getElementById( 'inputLeftSide' ).value = '';
			document.getElementById( 'imageUpload-frame' ).style.display = 'none';
			document.getElementById( 'imageUpload-frame' ).style.visibility = 'hidden';
		}
	};

	// Expose as a global object because the MiniAjaxUpload page calls some methods
	// of this class
	// @see https://phabricator.wikimedia.org/T158228#3030457
	window.FanBoxes = FanBoxes;

	$( function () {
		if ( mw.config.get( 'wgCanonicalSpecialPageName' ) === 'UserBoxes' ) {
			$( 'div.create-fanbox-buttons input[type="button"].fanbox-simple-button' ).on( 'click', function () {
				FanBoxes.createFantagSimple();
			} );

			$( 'div#fanbox_image' ).on( 'click', function () {
				FanBoxes.insertImageToLeft();
			} );

			$( 'span#addImage' ).on( 'click', function () {
				FanBoxes.displayAddImage( 'create-fanbox-image', 'addImage', 'closeImage' );
			} );

			$( 'span#closeImage' ).on( 'click', function () {
				FanBoxes.displayAddImage( 'create-fanbox-image', 'closeImage', 'addImage' );
			} );

			$( 'input#inputLeftSide' ).on( {
				input: function () {
					FanBoxes.displayLeftSide();
					FanBoxes.leftSideFanBoxFormat();
				},
				keydown: function () {
					FanBoxes.displayLeftSide();
					FanBoxes.leftSideFanBoxFormat();
				},
				keyup: function () {
					FanBoxes.displayLeftSide();
					FanBoxes.leftSideFanBoxFormat();
				},
				paste: function () {
					FanBoxes.displayLeftSide();
					FanBoxes.leftSideFanBoxFormat();
				},
				keypress: function () {
					FanBoxes.displayLeftSide();
					FanBoxes.leftSideFanBoxFormat();
				}
			} );

			$( 'input#inputRightSide' ).on( {
				input: function () {
					FanBoxes.displayRightSide();
					FanBoxes.rightSideFanBoxFormat();
				},
				keydown: function () {
					FanBoxes.limitText( this.form.inputRightSide, this.form.countdown, 70 );
					FanBoxes.displayRightSide();
					FanBoxes.rightSideFanBoxFormat();
				},
				keyup: function () {
					FanBoxes.limitText( this.form.inputRightSide, this.form.countdown, 70 );
					FanBoxes.displayRightSide();
					FanBoxes.rightSideFanBoxFormat();
				},
				paste: function () {
					FanBoxes.limitText( this.form.inputRightSide, this.form.countdown, 70 );
					FanBoxes.displayRightSide();
					FanBoxes.rightSideFanBoxFormat();
				},
				keypress: function () {
					FanBoxes.limitText( this.form.inputRightSide, this.form.countdown, 70 );
					FanBoxes.displayRightSide();
					FanBoxes.rightSideFanBoxFormat();
				}
			} );

			$( 'div.create-fanbox-buttons input[type="button"]' ).on( 'click', function () {
				FanBoxes.createFantag();
			} );

			// Tag cloud
			$( 'div#create-tagcloud span[id^="tag-"] a' ).on( 'click', function () {
				FanBoxes.insertTag(
					$( this ).data( 'slashed-tag' ),
					$( this ).parent().attr( 'id' ).replace( /tag-/, '' )
				);
			} );
		} // if Special:UserBoxes check

		// Special:TopUserBoxes, Special:ViewUserBoxes, <userboxes /> parser hook,
		// and /extensions/SocialProfile/UserProfile/UserProfilePage.php
		$( 'body' ).on( 'click', 'input.fanbox-cancel-button', function () {
			var $fantagId = $( this ).parents( 'div:eq(0)' ).attr( 'id' ).replace( /fanboxPopUpBox/, '' );
			FanBoxes.closeFanboxAdd(
				'fanboxPopUpBox' + $fantagId,
				'individualFanbox' + $fantagId
			);
		} );

		// FanBoxClass.php (UserBox: pages), Special:TopUserBoxes, Special:ViewUserBoxes
		if ( mw.config.get( 'wgCanonicalSpecialPageName' ) !== 'UserBoxes' ) {
			$( 'body' ).on( 'click', 'table.fanBoxTable', function () {
				var $element;
				if ( $( this ).parent().attr( 'id' ) ) {
					// FanBoxClass.php case
					$element = $( this ).parent();
				} else {
					// Special:TopUserBoxes, Special:ViewUserBoxes
					$element = $( this ).parent().parent().parent();
				}

				var $fantagId = $element.attr( 'id' ).replace( /individualFanbox/, '' );
				FanBoxes.openFanBoxPopup(
					'fanboxPopUpBox' + $fantagId,
					'individualFanbox' + $fantagId
				);
			} );
		}

		// UserBoxesHook.php (<userboxes /> parser hook) & /extensions/SocialProfile/UserProfile/UserProfilePage.php
		$( 'body' ).on( 'click', 'table.fanBoxTableProfile', function () {
			var $fantagId, $element;

			// UserBoxesHook.php
			if ( $( '.relativeposition' ).length > 0 ) {
				$element = $( this ).parent().parent();
			} else {
				// UserProfilePage.php
				$element = $( this ).parent();
			}
			$fantagId = $element.attr( 'id' ).replace( /show-message-container/, '' );

			FanBoxes.openFanBoxPopup(
				'fanboxPopUpBox' + $fantagId,
				'individualFanbox' + $fantagId
			);
		} );

		$( 'input.fanbox-add-button-half' ).on( 'click', function () {
			var $fantagId = $( this ).parents( 'div:eq(0)' ).attr( 'id' ).replace( /fanboxPopUpBox/, '' );
			FanBoxes.closeFanboxAdd(
				'fanboxPopUpBox' + $fantagId,
				'individualFanbox' + $fantagId
			);
			FanBoxes.showAddRemoveMessageUserPage( 1, $fantagId, 'show-addremove-message-half' );
		} );

		$( 'input.fanbox-remove-button-half' ).on( 'click', function () {
			var $fantagId = $( this ).parents( 'div:eq(0)' ).attr( 'id' ).replace( /fanboxPopUpBox/, '' );
			FanBoxes.closeFanboxAdd(
				'fanboxPopUpBox' + $fantagId,
				'individualFanbox' + $fantagId
			);
			FanBoxes.showAddRemoveMessageUserPage( 2, $fantagId, 'show-addremove-message-half' );
		} );

		// "Add this box to your user page?"/"Remove this box from your user page?"
		// (the add/remove buttons) on Special:TopUserBoxes & Special:ViewUserBoxes
		$( 'input.fanbox-add-button' ).on( 'click', function () {
			var $fantagId = $( this ).parents( 'div:eq(0)' ).attr( 'id' ).replace( /fanboxPopUpBox/, '' );
			FanBoxes.closeFanboxAdd(
				'fanboxPopUpBox' + $fantagId,
				'individualFanbox' + $fantagId
			);
			FanBoxes.showAddRemoveMessageUserPage( 1, $fantagId, 'show-addremove-message' );
		} );

		$( 'input.fanbox-remove-button' ).on( 'click', function () {
			var $fantagId = $( this ).parents( 'div:eq(0)' ).attr( 'id' ).replace( /fanboxPopUpBox/, '' );
			FanBoxes.closeFanboxAdd(
				'fanboxPopUpBox' + $fantagId,
				'individualFanbox' + $fantagId
			);
			FanBoxes.showAddRemoveMessageUserPage( 2, $fantagId, 'show-addremove-message' );
		} );

		// FanBoxClass.php
		$( 'input.fanbox-remove-has-button' ).on( 'click', function () {
			var $fantagId = $( this ).parents( 'div:eq(0)' ).attr( 'id' ).replace( /fanboxPopUpBox/, '' );
			FanBoxes.closeFanboxAdd(
				'fanboxPopUpBox' + $fantagId,
				'individualFanbox' + $fantagId
			);
			FanBoxes.showMessage( 2, $( this ).data( 'fanbox-title' ), $fantagId );
		} );

		$( 'input.fanbox-add-doesnt-have-button' ).on( 'click', function () {
			var $fantagId = $( this ).parents( 'div:eq(0)' ).attr( 'id' ).replace( /fanboxPopUpBox/, '' );
			FanBoxes.closeFanboxAdd(
				'fanboxPopUpBox' + $fantagId,
				'individualFanbox' + $fantagId
			);
			FanBoxes.showMessage( 1, $( this ).data( 'fanbox-title' ), $fantagId );
		} );
	} );

}( mediaWiki, jQuery ) );
