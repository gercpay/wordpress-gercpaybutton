/**
 * GercPay scripts.
 *
 * @package 'gercpay-button'
 */
(function (i18n) {
	const __ = i18n.__;

	const gpbPopup = document.querySelector('#gpb_popup');
	const gpbCheckoutForm = document.querySelector('#gpb_checkout_form');
	// Shortcode parameters.
	const productNameField = document.querySelector('.js-gpb-product-name');
	const productPriceField = document.querySelector('.js-gpb-product-price');
  // Client info.
	const clientNameField = document.querySelector('.js-gpb-client-name');
	const clientPhoneField = document.querySelector('.js-gpb-client-phone');
	const clientEmailField = document.querySelector('.js-gpb-client-email');
	const clientAmountField = document.querySelector('.js-gpb-product-price');
	const clientAmountWrapper = document.querySelector('.js-gpb-product-price-wrapper');
	// Required fields when making a purchase.
	const requiredFields = [clientNameField, clientPhoneField, clientEmailField];

	// GercPay payment button listener.
	window.addEventListener(
		'click',
		(event) => {
			if (event.target.dataset.type !== 'gpb_submit') {
				return;
			}
			event.preventDefault();
			if (typeof gpbPopup !== 'undefined' && gpbPopup && productNameField && productPriceField) {
				productNameField.value = event.target.dataset.name;
				productPriceField.value = event.target.dataset.price;
				if (requiredFields.every(element => element === null) && productPriceField.value.toLowerCase() !== 'custom') {
					// if 'GPB_MODE_NONE' enabled.
					gpbCheckoutForm.dispatchEvent(new Event('submit'));
				} else {
					// Other modes. Open popup window.
					if (productPriceField.value.toLowerCase() === 'custom') {
						productPriceField.value = 0;
						clientAmountWrapper.classList.remove('gpb-popup-field-hidden');
					}
					gpbPopup.classList.add('open');
				}
			}
		}
	);

	// Popup window close button handler.
	const gpbClose = document.querySelector('.gpb-popup-close');
	if (typeof gpbClose !== 'undefined' && gpbClose) {
		gpbClose.onclick = event => {
			event.preventDefault();
			resetFormFields();
			if (clientAmountWrapper.classList.contains('gpb-popup-field-hidden') !== true) {
				clientAmountWrapper.classList.add('gpb-popup-field-hidden');
			}
			gpbPopup.classList.remove('open');
			resetValidationMessages();
		};
	}

	// Checkout form handler (popup window).
	if (typeof gpbCheckoutForm !== 'undefined' && gpbCheckoutForm) {
		gpbCheckoutForm.onsubmit = event => {
			event.preventDefault();
			if (isFormHasNoErrors()) {
				validateCheckoutForm(event)
			}
		};
		// Event listeners for separate form fields.
		requiredFields.push(clientAmountField);
		requiredFields.map(
			field => {
				if (typeof field !== 'undefined' && field) {
					field.onchange = event => validateCheckoutField(event);
				}
			}
		);
		requiredFields.pop();
	}

	/**
	 * Validate a form field if its value has changed.
	 *
	 * @param event
	 */
	function validateCheckoutField(event) {
		const fieldId = event.target.id;
		const fieldValue = event.target.value;

		switch (fieldId) {
			case 'gpb_client_name':
				validateName(fieldValue);
				break;
			case 'gpb_phone':
				validatePhone(fieldValue);
				break;
			case 'gpb_email':
				validateEmail(fieldValue);
				break;
			case 'gpb_product_price':
				validateAmount(fieldValue);
		}
	}

	/**
	 * Validate name field.
	 *
	 * @param value
	 */
	function validateName(value) {
		const errorMessage = document.querySelector('.js-gpb-error-name');
		if (value.trim().length !== 0) {
			removeValidationMessage(errorMessage);
			return;
		}

		errorMessage.innerHTML = __('Invalid name', 'gercpay-button');
		highlightNearestInput(errorMessage);
	}

	/**
	 * Validate phone field.
	 *
	 * @param value
	 */
	function validatePhone(value) {
		const errorMessage = document.querySelector('.js-gpb-error-phone');
		if (value.replace(/\D/g, '').length >= 10) {
			removeValidationMessage(errorMessage);
			return;
		}

		errorMessage.innerHTML = __('Invalid phone number', 'gercpay-button');
		highlightNearestInput(errorMessage);
	}

	/**
	 * Validate email field.
	 *
	 * @param value
	 */
	function validateEmail(value) {
		const errorMessage = document.querySelector('.js-gpb-error-email');
		const emailPattern = /^(([^<>()[\]\.,;:\s@\"]+(\.[^<>()[\]\.,;:\s@\"]+)*)|(\".+\"))@(([^<>()[\]\.,;:\s@\"]+\.)+[^<>()[\]\.,;:\s@\"]{2,})$/i;
		if (String(value).toLowerCase().match(emailPattern)) {
			removeValidationMessage(errorMessage);
			return;
		}

		errorMessage.innerHTML = __('Invalid email', 'gercpay-button');
		highlightNearestInput(errorMessage);
	}

	/**
	 * Validate product price (amount) field.
	 *
	 * @param value
	 */
	function validateAmount(value) {
		const errorMessage = document.querySelector('.js-gpb-error-product-price');
		if (value.trim().length !== 0 && !isNaN(value) && !isNaN(parseFloat(value)) && parseFloat(value) > 0) {
			removeValidationMessage(errorMessage);
			return;
		}

		errorMessage.innerHTML = __('Invalid amount', 'gercpay-button');
		highlightNearestInput(errorMessage);
	}

	/**
	 * Checkout form validator.
	 *
	 * @param event
	 */
	function validateCheckoutForm(event) {
		event.preventDefault();

		const request = new XMLHttpRequest();
		request.open('POST', gpb_ajax.url, true);
		request.send(new FormData(gpbCheckoutForm));

		request.onload = function () {
			if (this.status >= 200 && this.status < 400) {
				const response = this.response;
				if (isJson(response)) {
					// Response has validation errors. Add validation errors on form.
					resetValidationMessages();
					const errors = JSON.parse(response);
					for (let error in errors) {
						if (errors.hasOwnProperty(error)) {
							let errorMessage = document.querySelector('.js-gpb-error-' + error);
							errorMessage.innerHTML = errors[error];
							highlightNearestInput(errorMessage);
						}
					}
				} else if (event.type === 'change') {
					resetValidationMessages();
				} else {
					// Success.
					gpbPopup.classList.remove('open');
					// Waiting for the end of the popup closing animation.
					const waitEndAnimation = setTimeout(
						() => {
							lockBody();
							const gpbCheckoutFormWrapper = document.querySelector('.gpb-popup-content');
							gpbCheckoutFormWrapper.innerHTML = response;
							document.querySelector('#gpb_payment_form').submit();
						},
						800
					);
				}
			} else {
				// Fail.
				console.log('GercPay plugin validate request error');
			}
		}

		request.onerror = function () {
			console.log('GercPay plugin error');
		}
	}

	/**
	 * Blocking the scroll page body while redirecting to the payment page.
	 */
	function lockBody() {
		document.body.classList.add('gpb-lock');
	}

	/**
	 * Checking if json was received in the response.
	 *
	 * @param str
	 * @returns {boolean}
	 */
	function isJson(str) {
		try {
			JSON.parse(str);
		} catch (e) {
			return false;
		}
		return true;
	}

	/**
	 * Reset all validation messages in form.
	 */
	function resetValidationMessages() {
		const messages = document.querySelectorAll('[class^=js-gpb-error-]');
		messages.forEach(message => message.innerHTML = '');

		const fields = document.querySelectorAll('.gpb-popup-input');
		fields.forEach(field => field.classList.remove('gpb-not-valid'));
	}

	/**
	 * Reset form fields to empty.
	 */
	function resetFormFields() {
		productNameField.value = '';
		productPriceField.value = '';
		gpbCheckoutForm.reset();
	}

	/**
	 * Reset validation message for specify field.
	 *
	 * @param elem
	 */
	function removeValidationMessage(elem) {
		elem.innerHTML = '';
		offHighlightNearestInput(elem);
	}

	/**
	 * Add warning selection on nearest input field.
	 *
	 * @param elem
	 */
	function highlightNearestInput(elem) {
		let input = elem.closest('.gpb-popup-input-group').querySelector('.gpb-popup-input');
		input.classList.add('gpb-not-valid');
	}

	/**
	 * Remove warning selection from the nearest input field.
	 *
	 * @param elem
	 */
	function offHighlightNearestInput(elem) {
		let input = elem.closest('.gpb-popup-input-group').querySelector('.gpb-popup-input');
		input.classList.remove('gpb-not-valid');
	}

	/**
	 * Check that form has no errors.
	 *
	 * @returns {boolean}
	 */
	function isFormHasNoErrors() {
		const hasErrors = gpbCheckoutForm.querySelectorAll('.gpb-not-valid');

		return hasErrors.length === 0;
	}

}(
	window.wp.i18n
));
