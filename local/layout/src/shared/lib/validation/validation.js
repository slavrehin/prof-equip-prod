import { dispatch } from '../dom/dispatch.js';
import { toggleClass } from '../dom/toggle-class.js';

import { buildValidationRules } from './build-validation-rules.js';
import { initInputMask } from './inputs-mask.js';

export class FormValidator {
	constructor(form) {
		this.form = form;
		this.rules = buildValidationRules(form);

		this.init();
	}

	init() {
		initInputMask(this.form);

		const inputs = this.form.querySelectorAll('.input');

		inputs.forEach(input => {
			input.addEventListener('blur', () => {
				this.rules.forEach(({ inputsList, type, validator, errorMessage }) => {
					const currentInput = [...inputsList].find(i => {
						return i.className === input.className;
					});
					if (currentInput) {
						const valid = this.validateInput(currentInput, type, validator, errorMessage);
					}
				});
			});
		});

		const submitBtn = this.form.querySelector(".btn[type='submit']");

		if (!submitBtn) return;

		submitBtn.addEventListener('click', e => {
			const isValid = this.validate();

			if (!isValid) {
				e.preventDefault();
				console.log('Валидация не пройдена');
			} else {
				console.log('Валидация пройдена');
			}

			dispatch({
				el: document,
				name: 'submit-form'
			});
		});
	}

	validate() {
		let isFormValid = true;

		this.rules.forEach(({ inputsList, type, validator, errorMessage }) => {
			inputsList.forEach(input => {
				const valid = this.validateInput(input, type, validator, errorMessage);

				if (!valid) {
					isFormValid = false;
				}
			});
		});

		return isFormValid;
	}

	validateInput(input, type, validator, errorMessage = '') {
		if (type === 'checkbox') {
			if (input instanceof HTMLInputElement && input.type === 'checkbox') {
				const isValid = input.checked;
				return this.setFeedback(input, isValid, isValid ? '' : errorMessage);
			}

			return this.setFeedback(input, false, errorMessage);
		}

		const value = this.getValue(input);
		const isValid = Boolean(value && validator && validator(value));

		return this.setFeedback(input, isValid, isValid ? '' : errorMessage);
	}

	getValue(input) {
		if (input instanceof HTMLInputElement || input instanceof HTMLTextAreaElement) {
			return input.value.trim();
		}

		return String(input.value || '').trim();
	}

	setFeedback(input, isValid, errorMessage = '') {
		const inputRow = input.closest('.input__row');

		if (!inputRow) {
			console.warn('Input row not found for input:', input);
			return isValid;
		}

		let errorElement = inputRow.querySelector('.input__error');

		if (!isValid && errorMessage) {
			if (!errorElement) {
				errorElement = document.createElement('p');
				errorElement.classList.add('input__error');
				inputRow.appendChild(errorElement);
			}
			errorElement.textContent = errorMessage;
		} else {
			if (errorElement) errorElement.remove();
		}

		toggleClass(inputRow, isValid ? 'success' : 'error', isValid ? 'error' : 'success');

		return isValid;
	}
}
