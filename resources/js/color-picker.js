jQuery( function () {
	var colorPickerTest = new YAHOO.widget.ColorPicker( 'colorpickerholder', {
		showhsvcontrols: true,
		showhexcontrols: true,
		images: {
			PICKER_THUMB: 'https://web.archive.org/web/20130310031336/http://developer.yahoo.com/yui/build/colorpicker/assets/picker_thumb.png',
			HUE_THUMB: 'https://web.archive.org/web/20130310031339/http://developer.yahoo.com/yui/build/colorpicker/assets/hue_thumb.png'
		}
	} );

	colorPickerTest.on( 'rgbChange', function () {
		var sColor = '#' + this.get( 'hex' ),
			documentForm;

		documentForm = document.form1;

		if ( documentForm.colorpickerchoice[ 0 ].checked ) {
			document.getElementById( 'fanBoxLeftSideOutput2' ).style.backgroundColor = sColor;
			// The commented-out line below is the original NYC code but I noticed that it doesn't work
			// document.getElementById( 'fanBoxLeftSideContainer' ).style.backgroundColor = sColor;
			document.getElementById( 'bgColorLeftSideColor' ).value = sColor;
		}

		if ( documentForm.colorpickerchoice[ 1 ].checked ) {
			document.getElementById( 'fanBoxLeftSideOutput2' ).style.color = sColor;
			document.getElementById( 'textColorLeftSideColor' ).value = sColor;
		}

		if ( documentForm.colorpickerchoice[ 2 ].checked ) {
			document.getElementById( 'fanBoxRightSideOutput2' ).style.backgroundColor = sColor;
			// The commented-out line below is the original NYC code but I noticed that it doesn't work
			// document.getElementById( 'fanBoxRightSideContainer' ).style.backgroundColor = sColor;
			document.getElementById( 'bgColorRightSideColor' ).value = sColor;
		}

		if ( documentForm.colorpickerchoice[ 3 ].checked ) {
			document.getElementById( 'fanBoxRightSideOutput2' ).style.color = sColor;
			document.getElementById( 'textColorRightSideColor' ).value = sColor;
		}
	} );
} );
