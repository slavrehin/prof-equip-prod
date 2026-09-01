import Swiper from 'swiper';
import { Navigation, Pagination } from 'swiper/modules';

import { initAccordion, initTabs } from '@/shared/lib';

let textBlockImagesSwiper = null;
function initTextBlockSwiper() {
	if (window.innerWidth < 768 && !textBlockImagesSwiper) {
		textBlockImagesSwiper = new Swiper('.text-block__images-swiper', {
			modules: [Navigation, Pagination],
			slidesPerView: 1,
			spaceBetween: 30,
			navigation: {
				prevEl: '.text-block__prev',
				nextEl: '.text-block__next'
			},
			pagination: {
				el: '.pagination',
				clickable: true
			},
			loop: true
		});
	}
	if (window.innerWidth >= 768 && textBlockImagesSwiper) {
		textBlockImagesSwiper.destroy(true, true);
		textBlockImagesSwiper = null;
	}
}

window.addEventListener('load', initTextBlockSwiper);
window.addEventListener('resize', initTextBlockSwiper);

const textBlockTabs = document.querySelector('.text-block__tabs ');
const textBlockAccordion = document.querySelector('.text-block__accordion-wrapper');

if (textBlockTabs) {
	initTabs(textBlockTabs);
}

const onReady = callback => {
	if (document.fonts) {
		document.fonts.ready.then(callback);
	} else {
		window.addEventListener('load', callback, { once: true });
	}
};

if (textBlockAccordion) {
	document.addEventListener('DOMContentLoaded', () => {
		onReady(() => {
			initAccordion(textBlockAccordion);
		});
	});
}

const newsSwiper = new Swiper('.news-swiper', {
	spaceBetween: 10,
	slidesPerView: 6,
	freeMode: true,
	watchSlidesProgress: true,
	breakpoints: {
		0: {
			slidesPerView: 1.2
		},
		767: {
			slidesPerView: 3
		},
		992: {
			slidesPerView: 4
		},
		1280: {
			slidesPerView: 6
		}
	}
});
