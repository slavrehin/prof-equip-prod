import { dispatch, switchToggler } from '../dom';

export const initTabs = wrapper => {
	initTabsWrapper(wrapper);
};

const initTabsWrapper = wrapper => {
	const tabs = [...wrapper.querySelectorAll('.tab')];
	const contents = [...wrapper.querySelectorAll('.tab-content')];

	if (!tabs.length || !contents.length) return;

	contents.forEach(content => content.classList.add('hidden'));

	const initialIndex = tabs.findIndex(tab => tab.classList.contains('active'));
	const index = initialIndex >= 0 ? initialIndex : 0;

	setActiveTab(wrapper, tabs, contents, index);

	wrapper.addEventListener('click', e => {
		const tab = e.target.closest('.tab');
		if (!tab || !wrapper.contains(tab)) return;

		const index = tabs.indexOf(tab);
		if (index === -1) return;

		setActiveTab(wrapper, tabs, contents, index);

		dispatch({
			el: document,
			name: 'switch-tab',
			detail: { tab }
		});
	});

	window.addEventListener('resize', () => {
		switchToggler(wrapper, '.tab');
	});
};

const setActiveTab = (wrapper, tabs, contents, index) => {
	tabs.forEach((tab, i) => tab.classList.toggle('active', i === index));

	contents.forEach((content, i) => content.classList.toggle('hidden', i !== index));

	requestAnimationFrame(() => {
		switchToggler(wrapper, '.tab');
	});
};
