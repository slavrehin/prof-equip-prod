/* Блог: вкладки блока «Ключевые преимущества» (.blog-advantages[data-blog-advantages] в тексте статьи).
   Наведение/клик/клавиши ←→ переключают вкладку; пока блок на экране и его не трогали — листается сам. */
(function () {
	function init(root) {
		if (root.classList.contains('is-ready')) return;
		var tabs = [].slice.call(root.querySelectorAll('.blog-advantages__tab'));
		var panels = [].slice.call(root.querySelectorAll('.blog-advantages__panel'));
		if (!tabs.length || tabs.length !== panels.length) return;

		var uid = 'blog-adv-' + Math.random().toString(36).slice(2, 8);
		var progress = document.createElement('div');
		progress.className = 'blog-advantages__progress';
		progress.setAttribute('aria-hidden', 'true');
		var bars = tabs.map(function (tab, i) {
			tab.id = uid + '-t' + i;
			panels[i].id = uid + '-p' + i;
			tab.setAttribute('aria-controls', panels[i].id);
			panels[i].setAttribute('aria-labelledby', tab.id);
			var bar = document.createElement('span');
			bar.addEventListener('click', function () { stop(); select(i); });
			progress.appendChild(bar);
			return bar;
		});
		root.querySelector('.blog-advantages__panels').appendChild(progress);

		var current = -1, timer = null, touched = false;
		function select(i, focus) {
			if (i === current) return;
			current = i;
			tabs.forEach(function (t, k) {
				var on = k === i;
				t.classList.toggle('is-active', on);
				t.setAttribute('aria-selected', on ? 'true' : 'false');
				t.setAttribute('tabindex', on ? '0' : '-1');
				panels[k].classList.toggle('is-active', on);
				bars[k].classList.toggle('is-active', on);
			});
			if (focus) tabs[i].focus();
			var strip = tabs[i].parentNode;
			if (strip.scrollWidth > strip.clientWidth) {
				strip.scrollTo({ left: tabs[i].offsetLeft - strip.offsetLeft - 16, behavior: 'smooth' });
			}
		}
		function stop() { touched = true; clearInterval(timer); timer = null; }

		var fine = window.matchMedia && window.matchMedia('(hover: hover)').matches;
		tabs.forEach(function (tab, i) {
			tab.addEventListener('click', function () { stop(); select(i); });
			if (fine) tab.addEventListener('mouseenter', function () { stop(); select(i); });
			tab.addEventListener('keydown', function (e) {
				var n = tabs.length, to = null;
				if (e.key === 'ArrowDown' || e.key === 'ArrowRight') to = (i + 1) % n;
				else if (e.key === 'ArrowUp' || e.key === 'ArrowLeft') to = (i - 1 + n) % n;
				else if (e.key === 'Home') to = 0;
				else if (e.key === 'End') to = n - 1;
				else if (e.key === 'Enter' || e.key === ' ') to = i;
				if (to === null) return;
				e.preventDefault(); stop(); select(to, true);
			});
		});

		root.classList.add('is-ready');
		select(0);

		var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		if (!reduce && 'IntersectionObserver' in window) {
			new IntersectionObserver(function (entries) {
				entries.forEach(function (en) {
					if (touched) return;
					if (en.isIntersecting && !timer) {
						timer = setInterval(function () { select((current + 1) % tabs.length); }, 5000);
					} else if (!en.isIntersecting && timer) {
						clearInterval(timer); timer = null;
					}
				});
			}, { threshold: 0.5 }).observe(root);
		}
	}
	function run() { [].forEach.call(document.querySelectorAll('[data-blog-advantages]'), init); }
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run); else run();
})();
