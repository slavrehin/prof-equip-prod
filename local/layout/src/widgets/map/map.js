const mapObj = document.querySelector('#map');

if (mapObj) {
	const center = mapObj.dataset.center.split(',').map(Number);
	const zoom = +mapObj.dataset.zoom || 13;

	const init = () => {
		const map = new ymaps.Map(mapObj, { center, zoom });
		if (!map) return;

		map.controls.remove('geolocationControl');
		map.controls.remove('searchControl');
		map.controls.remove('typeSelector');
		map.controls.remove('rulerControl');
		const placemark = new ymaps.Placemark(center, {}, {});

		map.geoObjects.add(placemark);
	};

	ymaps.ready(init);
}
