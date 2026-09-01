import Swiper from 'swiper';
import { Navigation } from 'swiper/modules';

import { updateVisibleSlides } from '@/shared/lib';

const textileTrusted = new Swiper('.textile-trusted-swiper', {
	modules: [Navigation],
	slidesPerView: 4,
	spaceBetween: 30,

	navigation: {
		prevEl: '.textile-trusted__prev',
		nextEl: '.textile-trusted__next'
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
			slidesPerView: 1
		},
		1200: {
			slidesPerView: 3
		},
		1441: {
			slidesPerView: 4
		}
	}
});
