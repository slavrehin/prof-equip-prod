import IMask from 'imask';

export const initInputMask = (scope = document) => {
	scope.querySelectorAll('.input__valid-phone').forEach(el => {
		IMask(el, {
			mask: '+7 (000) 000-00-00'
		});
	});
};
