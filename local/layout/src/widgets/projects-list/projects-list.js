document.addEventListener('DOMContentLoaded', () => {
	const projectsListContent = document.querySelector('.projects-list__content');
	if (!projectsListContent) return;

	const filters = document.querySelectorAll('.projects-list__filters .filter');
	const projectItems = document.querySelectorAll('.project__item');

	filters.forEach(filter => {
		filter.addEventListener('click', () => {
			const filterType = filter.dataset.filter;

			projectItems.forEach(item => {
				const itemFilters = item.dataset.filter;
				const matches = itemFilters && itemFilters.split(' ').includes(filterType);
				item.classList.toggle('hidden', !matches);
			});
		});
	});
});
