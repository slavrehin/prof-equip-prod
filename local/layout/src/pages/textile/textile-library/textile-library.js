import Swiper from 'swiper';
import { Navigation } from 'swiper/modules';

import { updateVisibleSlides } from '@/shared/lib';

const textileLibrary = new Swiper('.textile-library-swiper', {
	modules: [Navigation],
	slidesPerView: 6,
	spaceBetween: 20,

	navigation: {
		prevEl: '.textile-library__prev',
		nextEl: '.textile-library__next'
	},
	loop: true,
	on: {
		init: swiper => {
			if (window.innerWidth >= 768) {
				updateVisibleSlides(swiper);
			}
		},
		slideChange: swiper => {
			if (window.innerWidth >= 768) {
				updateVisibleSlides(swiper);
			}
		},
		resize: swiper => {
			if (window.innerWidth >= 768) {
				updateVisibleSlides(swiper);
			}
		}
	},
	breakpoints: {
		0: {
			slidesPerView: 1.5
		},
		1200: {
			slidesPerView: 4
		},
		1441: {
			slidesPerView: 6
		}
	}
});
