// Smart Filter - финальная версия с задержкой 1 секунда
(function() {
    'use strict';
    
    var filterInstance = null;
    
    function SmartFilter() {
        this.form = null;
        this.resetBtn = null;
        this.productContainer = null;
        this.countContainer = null;
        this.initialized = false;
        this.baseUrl = null;
        this.filterTimer = null;
        this.activeFiltersCount = 0;
        this.lastFilterUrl = window.location.pathname;
        this.filterDelay = 1000; // Задержка 1 секунда (1000 мс)
        this.urlGetParams = {}; // GET параметры из URL, которые нужно сохранить
        this.preservedGetParams = {}; // GET параметры, которые нужно сохранять при фильтрации
    }
    
    // Инициализация
    SmartFilter.prototype.init = function() {
        // Сохраняем GET параметры из текущего URL, которые нужно сохранить
        this.extractPreservedGetParams();
        
        this.findElements();
        
        if (!this.form) {
            this.setupFormObserver();
            return;
        }
        
        this.setup();
    };
    
    // Извлечение GET параметров, которые нужно сохранить
    SmartFilter.prototype.extractPreservedGetParams = function() {
        this.preservedGetParams = {};
        var search = window.location.search.substring(1);
        
        if (!search) return;
        
        var pairs = search.split('&');
        for (var i = 0; i < pairs.length; i++) {
            var pair = pairs[i].split('=');
            var key = decodeURIComponent(pair[0]);
            var value = pair.length > 1 ? decodeURIComponent(pair[1]) : '';
            
            // Сохраняем ВСЕ параметры, кроме служебных
            if (key !== 'ajax' && key !== 'set_filter') {
                this.preservedGetParams[key] = value;
            }
        }
        
        console.log('Preserved GET params:', this.preservedGetParams);
    };
    
    // Поиск элементов
    SmartFilter.prototype.findElements = function() {
        // Ищем форму по классу js-filter-form
        this.form = document.querySelector('form.js-filter-form');
        
        console.log('Form found:', this.form); // Для отладки
        
        // Кнопка сброса
        this.resetBtn = document.querySelector('.clear__btn');
        
        // Контейнер с товарами
        this.productContainer = document.querySelector('.js-product-container') || 
                               document.querySelector('.catalog-products') ||
                               document.querySelector('[data-products-container]');
        
        // Контейнер с количеством товаров
        this.countContainer = document.querySelector('.js-product-count') ||
                             document.querySelector('[data-products-count]');
        
        if (this.form) {
            // Получаем базовый URL из action
            var formAction = this.form.getAttribute('action') || window.location.pathname;
            this.baseUrl = formAction.split('?')[0];
        }
        
        return !!this.form;
    };
    
    // Настройка наблюдателя за формой
    SmartFilter.prototype.setupFormObserver = function() {
        var self = this;
        
        if (typeof MutationObserver === 'undefined') {
            var checkInterval = setInterval(function() {
                if (self.findElements() && self.form) {
                    clearInterval(checkInterval);
                    self.setup();
                }
            }, 1000);
            
            setTimeout(function() {
                clearInterval(checkInterval);
            }, 10000);
            return;
        }
        
        var observer = new MutationObserver(function(mutations) {
            for (var mutation of mutations) {
                if (mutation.addedNodes.length) {
                    for (var node of mutation.addedNodes) {
                        if (node.nodeType === 1) {
                            var hasForm = node.querySelector ? node.querySelector('form.js-filter-form') : null;
                            if (hasForm || (node.matches && node.matches('form.js-filter-form'))) {
                                observer.disconnect();
                                self.findElements();
                                if (self.form) {
                                    self.setup();
                                }
                                return;
                            }
                        }
                    }
                }
            }
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
        
        setTimeout(function() {
            observer.disconnect();
        }, 10000);
    };
    
    // Настройка после нахождения формы
    SmartFilter.prototype.setup = function() {
        if (!this.form) {
            console.error('Form not found');
            return;
        }
        
        console.log('Setting up filter with form:', this.form);
        
        this.bindEvents();
        this.initialized = true;
        this.checkUrlForFilters();
        
        // Скрываем кнопку "Показать" если она есть
        var acceptBtn = document.querySelector('.accept__btn');
        if (acceptBtn) {
            acceptBtn.classList.add('hidden');
        }
    };
    
    // Привязка событий
    SmartFilter.prototype.bindEvents = function() {
        var self = this;
        
        // Кнопка сброса
        if (this.resetBtn) {
            this.resetBtn.addEventListener('click', function(e) {
                e.preventDefault();
                self.clearFilter();
            });
        }
        
        // Обработка изменений в форме
        if (this.form) {
            this.form.addEventListener('change', function(e) {
                var target = e.target;
                console.log('Change event on:', target); // Для отладки
                
                if (target.type === 'checkbox' || target.type === 'radio' || target.type === 'range') {
                    self.scheduleFilter();
                }
            });
            
            // Для range слайдеров - при отпускании мыши
            var rangeInputs = this.form.querySelectorAll('input[type="range"]');
            rangeInputs.forEach(function(input) {
                input.addEventListener('mouseup', function() {
                    self.scheduleFilter();
                });
            });
        }
    };
    
    // Планирование фильтрации с задержкой
    SmartFilter.prototype.scheduleFilter = function() {
        // Отменяем предыдущий таймер
        if (this.filterTimer) {
            clearTimeout(this.filterTimer);
        }
        
        // Показываем индикатор загрузки
        this.form.classList.add('is-loading');
        
        // Устанавливаем новый таймер на 1 секунду
        this.filterTimer = setTimeout(function() {
            this.applyFilter();
        }.bind(this), this.filterDelay);
    };
    
    // Проверка наличия активных фильтров
    SmartFilter.prototype.hasActiveFilters = function() {
        if (!this.form) return false;
        
        var checkboxes = this.form.querySelectorAll('input[type="checkbox"]:checked');
        var radios = this.form.querySelectorAll('input[type="radio"]:checked');
        
        this.activeFiltersCount = checkboxes.length + radios.length;
        
        return this.activeFiltersCount > 0;
    };
    
    // Получение параметров фильтра из формы
    SmartFilter.prototype.getFilterParams = function() {
        var params = {};
        
        if (!this.form) {
            console.error('Form is null in getFilterParams');
            return params;
        }
        
        try {
            var formData = new FormData(this.form);
            var addedParams = {};
            
            for (var pair of formData.entries()) {
                var name = pair[0];
                var value = pair[1];
                
                if (value === '' || name === 'ajax' || name === 'set_filter' || name === 'set_filter_url') continue;
                
                if (!addedParams[name]) {
                    params[name] = [value];
                    addedParams[name] = true;
                }
            }
            
            params['set_filter'] = ['Y'];
            
        } catch (e) {
            console.error('Error in getFilterParams:', e);
        }
        
        return params;
    };
    
    // Применение фильтра
    SmartFilter.prototype.applyFilter = function() {
        if (!this.form) {
            console.error('Form is null in applyFilter');
            return;
        }
        
        // Очищаем таймер
        if (this.filterTimer) {
            clearTimeout(this.filterTimer);
            this.filterTimer = null;
        }
        
        this.showLoading(true);
        
        var params = this.getFilterParams();
        var url = this.baseUrl || window.location.pathname;
        var ajaxUrl = this.buildAjaxUrl(url, params);
        
        console.log('Applying filter with URL:', ajaxUrl); // Для отладки
        
        this.sendAjaxRequest(ajaxUrl, params);
    };
    
    // Построение AJAX URL с учетом сохраненных GET параметров
    SmartFilter.prototype.buildAjaxUrl = function(baseUrl, filterParams) {
        var url = baseUrl;
        var allParams = [];
        
        // Сначала добавляем сохраненные GET параметры из URL
        for (var key in this.preservedGetParams) {
            if (this.preservedGetParams.hasOwnProperty(key)) {
                allParams.push(encodeURIComponent(key) + '=' + encodeURIComponent(this.preservedGetParams[key]));
            }
        }
        
        // Затем добавляем параметры фильтра
        for (var key in filterParams) {
            if (filterParams.hasOwnProperty(key) && key !== 'ajax') {
                filterParams[key].forEach(function(value) {
                    allParams.push(encodeURIComponent(key) + '=' + encodeURIComponent(value));
                });
            }
        }
        
        // Добавляем ajax параметр
        allParams.push('ajax=Y');
        
        if (allParams.length > 0) {
            url += (url.indexOf('?') === -1 ? '?' : '&') + allParams.join('&');
        }
        
        return url;
    };
    
    // Отправка AJAX запроса
    SmartFilter.prototype.sendAjaxRequest = function(url, params) {
        var self = this;
        
        var xhr = new XMLHttpRequest();
        xhr.open('GET', url, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        
        xhr.onload = function() {
            self.showLoading(false);
            self.form.classList.remove('is-loading');
            
            if (xhr.status >= 200 && xhr.status < 300) {
                self.handleHtmlResponse(xhr.responseText);
            } else {
                console.error('Ошибка при загрузке данных:', xhr.status);
            }
        };
        
        xhr.onerror = function() {
            self.showLoading(false);
            self.form.classList.remove('is-loading');
            console.error('Сетевая ошибка');
        };
        
        xhr.send();
    };
    
    // Обработка HTML ответа
    SmartFilter.prototype.handleHtmlResponse = function(html) {
        if (!html) return;
        
        var tempDiv = document.createElement('div');
        tempDiv.innerHTML = html;
        
        // ИЗВЛЕКАЕМ FILTER_URL ИЗ СКРЫТОГО ПОЛЯ set_filter_url
        var filterUrl = this.extractFilterUrl(tempDiv);
        console.log('Extracted filter URL:', filterUrl); // Для отладки
        
        // Обновляем контейнер с товарами
        if (this.productContainer) {
            var products = tempDiv.querySelector('.js-product-container') ||
                          tempDiv.querySelector('.catalog-products') ||
                          tempDiv.querySelector('[data-products-container]');
            
            if (products) {
                this.productContainer.innerHTML = products.innerHTML;
            }
        }
        
        // Обновляем контейнер с количеством
        if (this.countContainer) {
            var count = tempDiv.querySelector('.js-product-count') ||
                       tempDiv.querySelector('[data-products-count]');
            
            if (count) {
                this.countContainer.innerHTML = count.innerHTML;
            }
        }
        
        // Обновляем счетчики в фильтре
        this.updateFilterCounters(tempDiv);
        
        // ОБНОВЛЯЕМ URL В АДРЕСНОЙ СТРОКЕ с сохранением GET параметров
        if (filterUrl) {
            var finalUrl = this.preserveGetParamsInUrl(filterUrl);
            this.updateBrowserUrl(finalUrl);
            
            // После обновления URL, извлекаем новые параметры для сохранения
            this.extractPreservedGetParams();
        }
        
        // Запускаем события
        document.dispatchEvent(new Event("ajax-load"));
        document.dispatchEvent(new Event("filter-success"));
    };
    
    // Добавление сохраненных GET параметров к URL
    SmartFilter.prototype.preserveGetParamsInUrl = function(url) {
        // Если нет параметров для сохранения, возвращаем исходный URL
        if (Object.keys(this.preservedGetParams).length === 0) {
            return url;
        }
        
        // Разбираем URL на части
        var urlParts = url.split('?');
        var baseUrl = urlParts[0];
        var existingParams = urlParts.length > 1 ? urlParts[1] : '';
        
        // Парсим существующие параметры
        var params = {};
        if (existingParams) {
            var pairs = existingParams.split('&');
            for (var i = 0; i < pairs.length; i++) {
                var pair = pairs[i].split('=');
                var key = decodeURIComponent(pair[0]);
                var value = pair.length > 1 ? decodeURIComponent(pair[1]) : '';
                params[key] = value;
            }
        }
        
        // Добавляем сохраненные параметры (не перезаписываем существующие)
        for (var key in this.preservedGetParams) {
            if (!params.hasOwnProperty(key)) {
                params[key] = this.preservedGetParams[key];
            }
        }
        
        // Собираем новый URL
        var newParams = [];
        for (var key in params) {
            newParams.push(encodeURIComponent(key) + '=' + encodeURIComponent(params[key]));
        }
        
        return baseUrl + (newParams.length > 0 ? '?' + newParams.join('&') : '');
    };
    
    // Извлечение FILTER_URL из ответа (из скрытого поля set_filter_url)
    SmartFilter.prototype.extractFilterUrl = function(tempDiv) {
        // Ищем скрытое поле set_filter_url
        var filterUrlInput = tempDiv.querySelector('input[name="set_filter_url"]');
        if (filterUrlInput && filterUrlInput.value) {
            return filterUrlInput.value;
        }
        
        // Если не нашли, ищем в форме внутри ответа
        var form = tempDiv.querySelector('form.js-filter-form');
        if (form) {
            var formFilterUrl = form.querySelector('input[name="set_filter_url"]');
            if (formFilterUrl && formFilterUrl.value) {
                return formFilterUrl.value;
            }
        }
        
        // Если ничего не нашли, возвращаем текущий URL
        return window.location.pathname + window.location.search;
    };
    
    // Обновление счетчиков в фильтре
    SmartFilter.prototype.updateFilterCounters = function(tempDiv) {
        if (!this.form) return;
        
        var labels = this.form.querySelectorAll('.filter-label');
        
        labels.forEach(function(label) {
            var input = label.querySelector('input');
            if (!input) return;
            
            var controlId = input.id;
            if (!controlId) return;
            
            // Ищем такой же контрол в ответе
            var newInput = tempDiv.querySelector('#' + controlId);
            if (newInput) {
                var newLabel = newInput.closest('.filter-label');
                if (newLabel) {
                    var newSpan = newLabel.querySelector('span');
                    var currentSpan = label.querySelector('span');
                    
                    if (newSpan && currentSpan) {
                        // Обновляем текст счетчика
                        currentSpan.innerHTML = newSpan.innerHTML;
                    }
                    
                    // Обновляем состояние disabled
                    if (newInput.disabled !== input.disabled) {
                        input.disabled = newInput.disabled;
                    }
                }
            }
        });
    };
    
    // Обновление URL в адресной строке
    SmartFilter.prototype.updateBrowserUrl = function(filterUrl) {
        if (!filterUrl) return;
        
        // Убираем ajax параметр из URL если он есть
        var cleanUrl = filterUrl.replace(/[?&]ajax=Y/g, '').replace(/[?&]$/, '');
        
        var currentUrl = window.location.pathname + window.location.search;
        
        if (cleanUrl !== currentUrl && window.history && window.history.pushState) {
            window.history.pushState({}, document.title, cleanUrl);
            console.log('URL обновлен на:', cleanUrl);
        }
    };
    
    // Показать/скрыть лоадер
    SmartFilter.prototype.showLoading = function(show) {
        if (!this.form) return;
        
        if (show) {
            this.form.classList.add('filter-loading');
            
            if (!this.form.querySelector('.filter-loader')) {
                var loader = document.createElement('div');
                loader.className = 'filter-loader';
                loader.innerHTML = '<div class="spinner"></div>';
                this.form.appendChild(loader);
            }
        } else {
            this.form.classList.remove('filter-loading');
            var loader = this.form.querySelector('.filter-loader');
            if (loader) {
                loader.remove();
            }
        }
    };
    
    // Сброс фильтра
    SmartFilter.prototype.clearFilter = function() {
        if (!this.form) return;
        
        // Снимаем все чекбоксы
        var checkboxes = this.form.querySelectorAll('input[type="checkbox"]');
        checkboxes.forEach(function(checkbox) {
            checkbox.checked = false;
        });
        
        // Снимаем все радио
        var radios = this.form.querySelectorAll('input[type="radio"]');
        radios.forEach(function(radio) {
            radio.checked = false;
        });
        
        // Сбрасываем range слайдеры
        var ranges = this.form.querySelectorAll('input[type="range"]');
        ranges.forEach(function(range) {
            var min = range.getAttribute('min') || '0';
            var max = range.getAttribute('max') || '100';
            
            if (range.classList.contains('js-range-min')) {
                range.value = min;
                var valueElement = range.closest('.js-filter-range').querySelector('.js-range-value-min');
                if (valueElement) valueElement.textContent = min;
            } else if (range.classList.contains('js-range-max')) {
                range.value = max;
                var valueElement = range.closest('.js-filter-range').querySelector('.js-range-value-max');
                if (valueElement) valueElement.textContent = max;
            }
        });
        
        // Отменяем таймер
        if (this.filterTimer) {
            clearTimeout(this.filterTimer);
            this.filterTimer = null;
        }
        
        // Применяем фильтр
        this.applyFilter();
    };
    
    // Проверка URL на наличие фильтров
    SmartFilter.prototype.checkUrlForFilters = function() {
        var urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('set_filter')) {
            setTimeout(function() {
                this.applyFilter();
            }.bind(this), 500);
        }
    };
    
    // Глобальная инициализация
    function init() {
        if (!filterInstance) {
            filterInstance = new SmartFilter();
        }
        
        filterInstance.init();
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        setTimeout(init, 100);
    }
    
    document.addEventListener('filter:form:loaded', function() {
        if (filterInstance && !filterInstance.initialized) {
            filterInstance.init();
        }
    });
    
    window.SmartFilter = SmartFilter;
    window.smartFilter = filterInstance;
    
})();