export const initAccordion = (scope = document) => {
	const accordions = scope.querySelectorAll('.accordion');
	if (!accordions.length) return;

	const nextFrame = () => new Promise(resolve => requestAnimationFrame(() => resolve()));

	const updateHeight = async item => {
		const title = item.querySelector('.accordion__title');
		const content = item.querySelector('.accordion__content');

		if (!title) return;

		await nextFrame();
		await nextFrame();

		const titleHeight = Math.ceil(title.getBoundingClientRect().height);
		const contentHeight =
			item.classList.contains('active') && content
				? Math.ceil(content.getBoundingClientRect().height)
				: 0;

		const height = titleHeight + contentHeight;

		item.style.height = `${height}px`;
		item.style.minHeight = `${height}px`;
	};

	const initItem = item => {
		const title = item.querySelector('.accordion__title');
		if (!title) return;

		updateHeight(item);

		title.addEventListener('click', () => {
			item.classList.toggle('active');
			updateHeight(item);
		});

		window.addEventListener('resize', () => updateHeight(item));
	};

	accordions.forEach(acc => {
		acc.querySelectorAll('.accordion__item').forEach(initItem);
	});
};
