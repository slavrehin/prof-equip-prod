export const validationRules = {
	surname: {
		type: 'text',
		validator: value => /^[А-Яа-яA-Za-zёЁ \-]+$/.test(value),
		errorMessage: 'Введите фамилию'
	},

	name: {
		type: 'text',
		validator: value => /^[А-Яа-яA-Za-zёЁ \-]+$/.test(value),
		errorMessage: 'Введите имя'
	},

	phone: {
		type: 'phone',
		validator: value => /^[\d+()\-\s]*$/.test(value),
		errorMessage: 'Введите номер телефона'
	},

	address: {
		type: 'text',
		validator: value => value.length > 0,
		errorMessage: 'Введите адрес'
	},

	email: {
		type: 'email',
		validator: value => /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/.test(value),
		errorMessage: 'Введите почту'
	},

	checkbox: {
		type: 'checkbox',
		errorMessage: 'Примите условия'
	}
};
