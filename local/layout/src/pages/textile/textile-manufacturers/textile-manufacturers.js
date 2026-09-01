import Swiper from 'swiper';
import { Navigation } from 'swiper/modules';

import { updateVisibleSlides } from '@/shared/lib';

const textileManufacturers = new Swiper('.textile-manufacturers-swiper', {
	modules: [Navigation],
	slidesPerView: 5,
	spaceBetween: 30,

	navigation: {
		prevEl: '.textile-manufacturers__prev',
		nextEl: '.textile-manufacturers__next'
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
			slidesPerView: 1,
			spaceBetween: 30
		},
		1200: {
			slidesPerView: 3,
			spaceBetween: 30
		},
		1441: {
			slidesPerView: 5
		}
	}
});
