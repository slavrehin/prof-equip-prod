import Swiper from 'swiper';
import { Autoplay, EffectFade, Navigation } from 'swiper/modules';

const hero = document.querySelector('.hero');

if (hero) {
	const heroBullets = hero.querySelectorAll('.hero__bullets .hero__bullet');

	const heroSwiper = new Swiper('.hero__swiper', {
		modules: [EffectFade, Autoplay],
		effect: 'fade',
		autoplay: {
			delay: 2500
		},
		slidesPerView: 1,
		spaceBetween: 10,
		on: {
			slideChange: swiper => {
				const currentBullet = [...heroBullets].find((_, index) => swiper.activeIndex === index);
				const activeBullet = hero.querySelector('.hero__bullets .hero__bullet.active');
				activeBullet.classList.remove('active');
				currentBullet.classList.add('active');
			}
		}
	});

	heroBullets.forEach((bullet, index) => {
		bullet.addEventListener('click', () => {
			heroSwiper.slideTo(index);
			const activeBullet = hero.querySelector('.hero__bullets .hero__bullet.active');
			activeBullet.classList.remove('active');
			bullet.classList.add('active');
		});
	});
}
