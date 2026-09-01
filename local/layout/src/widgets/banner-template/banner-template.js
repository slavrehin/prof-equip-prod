import Swiper from 'swiper';
import { Autoplay, EffectFade, Navigation, Pagination } from 'swiper/modules';

const bannerEl = document.querySelector('.banner-template');
const slides = bannerEl?.querySelectorAll('.swiper-slide');

if (slides && slides.length > 1) {
	const swiper = new Swiper(bannerEl, {
		modules: [Navigation, Pagination, Autoplay, EffectFade],
		slidesPerView: 1,
		spaceBetween: 30,
		loop: true,
		effect: 'fade',
		pagination: {
			el: '.banner-pagination',
			clickable: true
		},
		fadeEffect: {
			crossFade: true
		},
		autoplay: {
			delay: 5000,
			disableOnInteraction: false
		}
	});

	function handleVideoPlayback(swiper) {
		bannerEl.querySelectorAll('.swiper-slide video').forEach(video => {
			video.pause();
			video.currentTime = 0;
		});
		const activeSlide = swiper.slides[swiper.activeIndex];
		const activeVideo = activeSlide?.querySelector('video');
		if (activeVideo) {
			activeVideo.play();
		}
	}

	swiper.on('slideChangeTransitionEnd', handleVideoPlayback);
	handleVideoPlayback(swiper);
} else {
	const video = bannerEl?.querySelector('video');
	if (video) video.play();
}
