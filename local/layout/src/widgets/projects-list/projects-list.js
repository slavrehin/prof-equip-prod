window.addEventListener('load', () => {
	const projectsListContent = document.querySelector('.projects-list__content');
	if (!projectsListContent) return;

	const filters = document.querySelectorAll('.projects-list__filters .filter');
	const projectItems = document.querySelectorAll('.project__item');
	let msnry;

	function initMasonry() {
		if (msnry) msnry.destroy();

		msnry = new Masonry(projectsListContent, {
			itemSelector: '.grid-item:not(.hidden)',
			percentPosition: true,
			columnWidth: '.grid-sizer'
		});

		// Пересчитываем layout после загрузки КАЖДОЙ картинки
		imagesLoaded(projectsListContent).on('progress', function () {
			msnry.layout();
		});
	}

	initMasonry();

	filters.forEach(filter => {
		filter.addEventListener('click', () => {
			const filterType = filter.dataset.filter;

			projectItems.forEach(item => {
				const itemFilters = item.dataset.filter;
				const matches = itemFilters && itemFilters.split(' ').includes(filterType);
				item.classList.toggle('hidden', !matches);
			});

			initMasonry();
		});
	});
});
