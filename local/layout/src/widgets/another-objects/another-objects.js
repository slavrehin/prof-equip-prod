import Swiper from 'swiper';
import { Navigation, Pagination } from 'swiper/modules';

import { updateVisibleSlides } from '@/shared/lib';

new Swiper('.another-objects__swiper', {
	modules: [Navigation, Pagination],
	slidesPerView: 4,
	spaceBetween: 50,
	navigation: {
		prevEl: '.another-objects__prev',
		nextEl: '.another-objects__next'
	},
	loop: true,
	breakpoints: {
		0: {
			slidesPerView: 1
		},
		576: {
			slidesPerView: 2
		},
		767: {
			slidesPerView: 3
		},
		992: {
			slidesPerView: 4
		}
	},
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
	}
});
