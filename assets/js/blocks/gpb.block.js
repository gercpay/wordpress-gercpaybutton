/**
 * GercPay Button Block
 *
 * @package 'gercpay-button'
 */
(function (blocks, editor, i18n, element) {
	const el = element.createElement;
	const __ = i18n.__;

	const RichText = editor.RichText;

	blocks.registerBlockType(
		'gercpay-button/gpb-block',
		{
			title: __('GercPay Button', 'gercpay-button'),
			icon: 'shortcode',
			category: 'text',
			keywords: ['gercpay', 'gpb', 'payment', 'button'],

			attributes: {
				name: {
					type: 'string',
					default: __('Example name', 'gercpay-button')
				},
				price: {
					type: 'string',
					default: '0.00'
				},
				size: {
					type: 'string',
					source: 'children',
					selector: 'p'
				},
				align: {
					type: 'string',
					source: 'children',
					selector: 'p'
				}
			},

			edit: props => {
				const {
					attributes: {name, price, size, align}
				} = props;

				function onChangeName(event) {
					props.setAttributes({name: event.target.value});
				}

				function onChangePrice(event) {
					props.setAttributes({price: event.target.value});
				}

				return (
					el(
						'div',
						{class: 'js-gpb-wrapper'},
						el('span', {class: 'js-gpb-label'}, __('Please specify Product name and Price', 'gercpay-button')),
						el(
							'div',
							{class: 'js-gpb-container'},
							el('input', {class: 'js-gpb-input', value: name, onChange: onChangeName}),
							el('input', {class: 'js-gpb-input', value: price, onChange: onChangePrice})
						)
					)
				);
			},

			save: props => {
				const shortcode = "[gpb name='" + props.attributes.name + "' price='" + props.attributes.price + "']";
				return el('p', {}, shortcode);
			}
		}
	);

})(
	window.wp.blocks,
	window.wp.editor,
	window.wp.i18n,
	window.wp.element
);
