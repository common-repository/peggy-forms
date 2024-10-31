( function( blocks, i18n, editor, element, components ) {
	var el = element.createElement,
		__ = i18n.__,
		registerBlockType = blocks.registerBlockType,
		RichText = blocks.RichText,
		blockStyle = { backgroundColor: '#900', color: '#fff', padding: '20px' };


	registerBlockType( 'peggyforms-gutenberg/form', {
		title: __( 'Peggy Pay' ),

		icon: 'feedback',

		category: 'common',

		attributes: {
            formKey: {
                type: 'string',
                default: ''
            },
            embedType: {
                type: 'string',
                default: 'embed'
            },
            autofocus: {
            	type: 'boolean',
            	default: false
            }
		},

		edit: function( props ) {
	        var children = [];
	        console.log(props);

			var forms = [ { label: "Select page", value: null } ];

			var selectForm = function( newValue ) {
				props.setAttributes( {
					formKey: newValue,
				} );
			};
			var selectEmbedType = function( newValue ) {
				props.setAttributes( {
					embedType: newValue,
				} );
			};
			var selectAutoFocus = function( newVal ) {
				props.setAttributes( {
					autofocus: newVal,
				} );
			};

			_.each( peggyFormsGutenberg.forms.forms, function( form, index ) {
				forms.push(
					{ label: form.Name, value: form.Key }
				);
			});

			var elSelectForm = el(
				wp.components.SelectControl,
				{ label: 'Select form', value: props.attributes.formKey, options: forms, onChange: selectForm }
			);

			var embedTypes = [
				{ value: null, label: "Select embed type" },
				{ value: 'embed', label: "Embeded" },
				{ value: 'iframe', label: "Iframe"}
			];

			var elEmbedType = el(
				wp.components.SelectControl,
				{ label: 'Embed type', value: props.attributes.embedType, options: embedTypes, onChange: selectEmbedType }
			);

			// var elAutoFocus = el(wp.components.CheckboxControl, {
			// 	label: "Auto focus first field",
			// 	checked: props.attributes.autofocus,
			// 	onChange: selectAutoFocus
			// });

			children.push(
				el(
					'div', { class: 'pf-gutenberg-container' },
					el( 'img', { src: peggyFormsGutenberg.block_logo } ),
					el( 'div', {}, [
						elSelectForm,
						elEmbedType//,
						// elAutoFocus
					] )
				)
			);

			return [ children ];
		},

		save: function( props ) {
            var formKey = props.attributes.formKey;
            var embedType = props.attributes.embedType;
            var autofocus = props.attributes.autofocus ? "true" : "false";

            if( !formKey ) return '';
            if( !embedType ) return '';
			/**
			 * we're essentially just adding a short code, here is where
			 * it's save in the editor
			 */
			var returnHTML = '[peggyforms key=' + formKey + ' style=' + embedType + ' autofocus=' + autofocus + ']';
			return el( 'div', null, returnHTML );
		}
	} );
} )(
	window.wp.blocks,
	window.wp.i18n,
	window.wp.editor,
	window.wp.element,
	window.wp.components
);
