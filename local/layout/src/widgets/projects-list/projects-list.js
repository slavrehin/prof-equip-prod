document.addEventListener('DOMContentLoaded', () => {
	const content = document.querySelector('.projects-list__content');
	const loadMoreBtn = document.querySelector('.projects-list__load-more');
	if (!content || !loadMoreBtn) return;

	const filters = document.querySelectorAll('.projects-list__filters .filter');

	let currentFilter = '0';
	let offset = parseInt(loadMoreBtn.dataset.offset || '0', 10);
	let loading = false;

	function setLoading(state) {
		loading = state;
		loadMoreBtn.disabled = state;
		filters.forEach(filter => { filter.disabled = state; });
	}

	function fetchBatch(filter, offsetValue) {
		const url = `/local/ajax/portfolio/load.php?filter=${encodeURIComponent(filter)}&offset=${offsetValue}`;
		return fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
			.then(response => {
				if (!response.ok) throw new Error('portfolio load failed');
				return response.json();
			});
	}

	loadMoreBtn.addEventListener('click', () => {
		if (loading) return;
		setLoading(true);

		fetchBatch(currentFilter, offset)
			.then(data => {
				content.insertAdjacentHTML('beforeend', data.html);
				offset += data.count;
				loadMoreBtn.style.display = data.hasMore ? '' : 'none';
			})
			.catch(() => {})
			.finally(() => setLoading(false));
	});

	filters.forEach(filter => {
		filter.addEventListener('click', () => {
			if (loading || filter.dataset.filter === currentFilter) return;

			currentFilter = filter.dataset.filter;
			setLoading(true);
			filters.forEach(f => f.classList.toggle('active', f === filter));

			fetchBatch(currentFilter, 0)
				.then(data => {
					content.innerHTML = data.html;
					offset = data.count;
					loadMoreBtn.style.display = data.hasMore ? '' : 'none';
				})
				.catch(() => {})
				.finally(() => setLoading(false));
		});
	});
});
