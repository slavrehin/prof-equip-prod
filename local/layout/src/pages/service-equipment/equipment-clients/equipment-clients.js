import Swiper from 'swiper';
import { Navigation } from 'swiper/modules';

import { updateVisibleSlides } from '@/shared/lib';

const equipmentClients = new Swiper('.equipment-clients-swiper', {
	modules: [Navigation],
	slidesPerView: 5,
	spaceBetween: 30,

	navigation: {
		prevEl: '.equipment-clients__prev',
		nextEl: '.equipment-clients__next'
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
