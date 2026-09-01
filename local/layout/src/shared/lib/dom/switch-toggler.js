export const switchToggler = (wrapper, tabSelector) => {
	if (!wrapper) return;

	const toggler = wrapper.querySelector('.toggler');
	const activeTab = wrapper.querySelector(`${tabSelector}.active`);

	if (!toggler || !activeTab) return;

	const { offsetLeft, offsetWidth } = activeTab;

	toggler.style.transform = `translateX(${offsetLeft}px)`;
	toggler.style.width = `${offsetWidth}px`;
};
