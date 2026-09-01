import intlTelInput from 'intl-tel-input';

const input = document.querySelector('#phone');

if (input) {
	const iti = intlTelInput(input, {
		loadUtils: () => import('intl-tel-input/utils'),
		initialCountry: 'auto'
	});
}
