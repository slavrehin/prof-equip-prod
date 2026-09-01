export const dispatch = (
	{ el = document, name, detail = null } = {
		el: document,
		name: '',
		detail: null
	}
) => {
	if (!name) throw new Error('Event name not set');

	if (detail !== undefined && detail !== null) {
		el.dispatchEvent(new CustomEvent(name, { detail }));
	} else {
		el.dispatchEvent(new Event(name));
	}
};
