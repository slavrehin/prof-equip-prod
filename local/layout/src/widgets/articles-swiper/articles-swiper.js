import Swiper from 'swiper';
import { Navigation, Pagination } from 'swiper/modules';

import { updateVisibleSlides } from '@/shared/lib';

new Swiper('.articles-swiper', {
	modules: [Navigation, Pagination],
	slidesPerView: 4,
	spaceBetween: 30,
	navigation: {
		prevEl: '.articles__prev',
		nextEl: '.articles__next'
	},
	loop: true,
	breakpoints: {
		0: {
			slidesPerView: 1
		},
		576: {
			slidesPerView: 2
		},
		1024: {
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
