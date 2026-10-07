/* Живые подсказки поиска в шапке: товары + категории слева, услуги справа.
   Данные — /local/ajax/search.php (ProfEquipSearch::suggest). */
(function () {
	var input = document.querySelector('.search-block input[name="s"]');
	if (!input) return;
	var form = input.closest('form');
	if (!form) return;

	input.setAttribute('autocomplete', 'off');

	var box = document.createElement('div');
	box.className = 'live-search';
	box.setAttribute('role', 'listbox');
	(form.querySelector('.input-wrapper') || form).appendChild(box);

	var timer = null;
	var controller = null;
	var lastQuery = '';
	var activeIndex = -1;

	function esc(s) {
		return String(s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	function highlight(name, query) {
		var html = esc(name);
		query.split(/\s+/).filter(function (w) { return w.length >= 2; }).forEach(function (w) {
			var re = new RegExp('(' + esc(w).replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
			html = html.replace(re, '<mark>$1</mark>');
		});
		return html;
	}

	function render(data) {
		var q = data.query;
		var main = '';

		if (data.sections.length) {
			main += '<div class="live-search__group"><p class="live-search__heading">Категории</p><div class="live-search__chips">' +
				data.sections.map(function (s) {
					return '<a class="live-search__chip js-ls-item" href="' + esc(s.url) + '">' + esc(s.name) + ' <span>' + s.count + '</span></a>';
				}).join('') + '</div></div>';
		}

		main += '<div class="live-search__group"><p class="live-search__heading">Товары</p>';
		if (data.products.length) {
			main += data.products.map(function (p) {
				return '<a class="live-search__product js-ls-item" href="' + esc(p.url) + '">' +
					(p.img ? '<img class="live-search__thumb" src="' + esc(p.img) + '" alt="" loading="lazy">' : '<span class="live-search__thumb"></span>') +
					'<span><span class="live-search__name">' + highlight(p.name, q) + '</span>' +
					(p.section ? '<span class="live-search__meta">' + esc(p.section) + '</span>' : '') + '</span></a>';
			}).join('');
		} else if (data.total) {
			main += '<p class="live-search__empty">Совпадения нашлись в описаниях товаров.</p>';
		} else {
			main += '<p class="live-search__empty">По запросу «' + esc(q) + '» товаров не найдено. Уточните запрос или <a href="/kontakty/">свяжитесь с нами</a> — подберём оборудование.</p>';
		}
		if (data.total) {
			main += '<a class="live-search__all js-ls-item" href="' + esc(data.allUrl) + '">Все результаты (' + data.total + ')</a>';
		}
		main += '</div>';

		var side = '<p class="live-search__heading">Наши услуги</p>' + data.services.map(function (s) {
			return '<a class="live-search__service js-ls-item' + (s.match ? ' is-match' : '') + '" href="' + esc(s.url) + '">' + esc(s.name) + '</a>';
		}).join('');

		box.innerHTML = '<div class="live-search__main">' + main + '</div><div class="live-search__side">' + side + '</div>';
		box.classList.remove('live-search__loading');
		activeIndex = -1;
		open();
	}

	function open() { box.classList.add('is-open'); }
	function close() { box.classList.remove('is-open'); activeIndex = -1; }

	function load(query) {
		if (controller) controller.abort();
		controller = 'AbortController' in window ? new AbortController() : null;
		box.classList.add('live-search__loading');
		fetch('/local/ajax/search.php?q=' + encodeURIComponent(query), controller ? { signal: controller.signal } : {})
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (input.value.trim() === query) render(data);
			})
			.catch(function () {});
	}

	input.addEventListener('input', function () {
		var query = input.value.trim();
		clearTimeout(timer);
		if (query.length < 2) {
			lastQuery = '';
			close();
			return;
		}
		if (query === lastQuery) return;
		lastQuery = query;
		timer = setTimeout(function () { load(query); }, 220);
	});

	input.addEventListener('focus', function () {
		if (box.innerHTML && input.value.trim().length >= 2) open();
	});

	input.addEventListener('keydown', function (e) {
		if (!box.classList.contains('is-open')) return;
		var items = box.querySelectorAll('.js-ls-item');
		if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
			e.preventDefault();
			if (!items.length) return;
			activeIndex = e.key === 'ArrowDown'
				? (activeIndex + 1) % items.length
				: (activeIndex - 1 + items.length) % items.length;
			items.forEach(function (el, i) { el.classList.toggle('is-active', i === activeIndex); });
			items[activeIndex].scrollIntoView({ block: 'nearest' });
		} else if (e.key === 'Enter' && activeIndex >= 0 && items[activeIndex]) {
			e.preventDefault();
			window.location.href = items[activeIndex].href;
		} else if (e.key === 'Escape') {
			close();
		}
	});

	document.addEventListener('click', function (e) {
		if (!form.contains(e.target)) close();
	});

	// закрытие поиска кнопкой-крестиком в шапке
	var closeBtn = document.querySelector('.close-search__btn');
	if (closeBtn) closeBtn.addEventListener('click', close);
})();
