import { validationRules } from '@/shared/config';

export const buildValidationRules = form => {
	return Object.entries(validationRules).map(([name, rule]) => ({
		...rule,
		inputsList: form.querySelectorAll(`.input__valid-${name}`)
	}));
};
