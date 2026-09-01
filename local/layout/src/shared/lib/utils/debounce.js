export const debounce = (fn, wait = 100) => {
	let t;
	return (...args) => {
		clearTimeout(t);
		t = setTimeout(() => fn(...args), wait);
	};
};
