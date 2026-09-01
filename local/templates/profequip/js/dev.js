// Оптимизированный скрипт для отправки форм с капчей
(function() {
    'use strict';
    
    // Состояние отправки форм
    var formsState = new WeakMap();
    
    // Функция для получения и добавления URL текущей страницы
    function addPageUrlToForm(form, formData) {
        var currentUrl = window.location.href;
        var urlField = form.querySelector('.js-url');
        
        if (urlField) {
            var fieldName = urlField.getAttribute('name');
            urlField.value = currentUrl;
            if (fieldName) {
                formData.set(fieldName, currentUrl);
            } else {
                formData.append('page_url', currentUrl);
            }
        } else {
            formData.append('page_url', currentUrl);
        }
        
        return formData;
    }
    
    // Функция отправки формы (вызывается после успешной капчи)
    function sendForm(form) {
        //console.log('Sending form after captcha success');
        
        // Показываем загрузку
        form.classList.add('loading');
        
        // Блокируем кнопку отправки
        var submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.classList.add('loading');
        }
        
        // Собираем данные
        var formData = new FormData(form);
        var actionUrl = form.getAttribute('action') || window.location.pathname;
        
        // Добавляем токен капчи из скрытого поля
        var captchaTokenInput = document.querySelector(".captcha-container-footer input[name='smart-token']");
        if (captchaTokenInput && captchaTokenInput.value) {
            formData.append("smart-token", captchaTokenInput.value);
            //console.log('Added smart-token to form data');
        }
        
        // Добавляем URL текущей страницы
        formData = addPageUrlToForm(form, formData);
        
        // Добавляем маркер AJAX-запроса
        formData.append('IS_AJAX', 'Y');
        
        //console.log('Sending form to:', actionUrl);
        
        // Отправляем запрос
        fetch(actionUrl, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.text();
        })
        .then(function(text) {
            // Сбрасываем состояние
            formsState.delete(form);
            form.classList.remove('loading');
            
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.classList.remove('loading');
            }
            
            try {
                var result = JSON.parse(text);
                //console.log('Server response:', result);
                handleFormResponse(form, result);
            } catch (e) {
                //console.error('Failed to parse JSON:', e);
                showFormError(form, 'Ошибка сервера. Попробуйте позже.');
            }
        })
        .catch(function(error) {
            console.error('Fetch error:', error);
            formsState.delete(form);
            form.classList.remove('loading');
            
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.classList.remove('loading');
            }
            
            showFormError(form, 'Ошибка соединения с сервером');
        });
        
        // Сбрасываем капчу после отправки (как в вашем примере)
        if (window.smartCaptcha) {
            var widgetId = form.getAttribute('widget');
            if (widgetId) {
                window.smartCaptcha.reset(widgetId);
            }
        }
    }
    
    // Обработчик успешной капчи (как в вашем примере)
    function onCaptchaSuccess(event, form) {
        console.log('Captcha success event received');
        sendForm(form);
        document.removeEventListener("changeCaptchaSuccess", form._captchaHandler);
    }
    
    // Основная функция - вызывается при отправке формы
    window.backendFunc = function(form) {
        // Проверка на повторную отправку
        if (formsState.get(form)?.submitting) {
            console.log('Form is already submitting');
            return false;
        }
        
        // Сохраняем состояние
        formsState.set(form, { submitting: true });
        
        // Определяем тип формы
        var isFbForm = form.classList.contains('fb-form');
        
        if (!isFbForm) {
            formsState.delete(form);
            form.submit();
            return false;
        }
        
        // Проверяем наличие капчи на странице
        var captchaContainer = document.querySelector(".captcha-container-footer");
        
        if (captchaContainer && window.smartCaptcha) {

            var widgetId = form.getAttribute('widget');
            var captchaTokenInput = captchaContainer.querySelector("input[name='smart-token']");
            
            if (widgetId && captchaTokenInput && captchaTokenInput.value && window.captchaSuccess) {
                console.log('Captcha already verified, sending form directly');
                sendForm(form);
            } else {
                console.log('Calling captcha execute');
                console.log('widget', form.getAttribute('widget'));
                
                // Если widget еще не создан, создаем его
                if (!widgetId) {
                    console.log('Creating new captcha widget');
                    widgetId = window.smartCaptcha.render(captchaContainer, {
                        sitekey: 'ysc1_UIwDIZX4s5peooFanDbgUdhicJUCkPZ3AsQJ559m1ffac82e',
                        invisible: true,
                        callback: function(token) {
                            console.log("Captcha token:", token);
                            if (typeof token === "string" && token.length > 0) {
                                window.captchaSuccess = true;
                                let event = new Event("changeCaptchaSuccess", {bubbles: true});
                                document.querySelector('body').dispatchEvent(event);
                            }
                        },
                        test: false,
                    });
                    
                    // Сохраняем widget id в атрибут формы
                    form.setAttribute('widget', widgetId);
                    console.log('Widget saved with id:', widgetId);
                }
                
                // Создаем обработчик для события успешной капчи
                var captchaHandler = function(event) {
                    onCaptchaSuccess(event, form);
                };
                form._captchaHandler = captchaHandler;
                document.addEventListener("changeCaptchaSuccess", captchaHandler);
                
                // Вызываем капчу (как в вашем примере)
                window.smartCaptcha.execute(form.getAttribute('widget'));
                
                // Сбрасываем флаг отправки, так как форма еще не отправлена
                formsState.delete(form);
                return false;
            }
        } else {
            // Если капчи нет, отправляем форму как обычно
            console.log("No captcha found, sending form directly");
            sendForm(form);
        }
        
        return false;
    };
    
    // Обработка ответа формы
    function handleFormResponse(form, result) {
        hideFormErrors(form);
        
        if (result.success) {
            form.reset();
            
            form.querySelectorAll('label.active').forEach(function(label) {
                label.classList.remove('active');
            });
            
            var modal = form.closest('.modal');
            if (modal) {
                modal.classList.remove('active');
            }
            
            if (result.show_success_modal) {
                var modalPath = result.subscribed 
                    ? '/local/ajax/success-subscribed-modal/'
                    : '/local/ajax/success-modal/';
                loadSuccessModal(modalPath);
            }
            
            if (typeof ym === 'function') {
                ym(44219954, 'reachGoal', 'send_form');
            }
            
            form.dispatchEvent(new CustomEvent('form:success', { 
                detail: result 
            }));
            
        } else {
            if (result.errors) {
                showValidationErrors(form, result.errors);
            } else {
                showFormError(form, result.message || 'Ошибка при отправке формы');
            }
            
            form.dispatchEvent(new CustomEvent('form:error', { 
                detail: result 
            }));
        }
    }
    
    // Показать ошибку формы
    function showFormError(form, message) {
        var errorContainer = form.querySelector('.form-error, .input__error');
        
        if (errorContainer) {
            errorContainer.textContent = message;
            errorContainer.style.display = 'block';
        } else {
            errorContainer = document.createElement('div');
            errorContainer.className = 'form-error';
            errorContainer.textContent = message;
            errorContainer.style.color = 'red';
            errorContainer.style.marginTop = '10px';
            
            var submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.parentNode.insertBefore(errorContainer, submitBtn.nextSibling);
            } else {
                form.appendChild(errorContainer);
            }
        }
        
        setTimeout(function() {
            if (errorContainer && errorContainer.parentNode) {
                errorContainer.style.display = 'none';
            }
        }, 5000);
    }
    
    // Показать ошибки валидации полей
    function showValidationErrors(form, errors) {
        for (var fieldName in errors) {
            var field = form.querySelector('[name="' + fieldName + '"]');
            if (field) {
                field.classList.add('error');
                
                var errorMsg = document.createElement('div');
                errorMsg.className = 'field-error';
                errorMsg.textContent = errors[fieldName];
                errorMsg.style.color = 'red';
                errorMsg.style.fontSize = '12px';
                
                field.parentNode.appendChild(errorMsg);
                
                field.addEventListener('input', function onInput() {
                    this.classList.remove('error');
                    var next = this.nextSibling;
                    if (next && next.classList.contains('field-error')) {
                        next.remove();
                    }
                    this.removeEventListener('input', onInput);
                }, { once: true });
            }
        }
    }
    
    // Скрыть все ошибки формы
    function hideFormErrors(form) {
        form.querySelectorAll('.form-error, .field-error, .input__error').forEach(function(el) {
            el.style.display = 'none';
            if (el.classList.contains('field-error')) {
                el.remove();
            }
        });
        
        form.querySelectorAll('.error').forEach(function(el) {
            el.classList.remove('error');
        });
    }
    
    // Загрузка модального окна успеха
    function loadSuccessModal(modalPath) {
        fetch(modalPath, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Failed to load success modal');
            }
            return response.text();
        })
        .then(function(html) {
            insertSuccessModal(html);
        })
        .catch(function(error) {
            console.error('Error loading success modal:', error);
            alert('Спасибо! Ваше сообщение отправлено.');
        });
    }
    
    // Вставка модального окна успеха
    function insertSuccessModal(html) {
        var container = document.createElement('div');
        container.innerHTML = html;
        
        var modalElement = container.querySelector('.modal') || container.firstElementChild;
        
        if (!modalElement) return;
        
        var modalsContainer = document.querySelector('.modals') || document.body;
        modalsContainer.appendChild(modalElement);
        
        setTimeout(function() {
            modalElement.classList.add('active');
            document.body.style.overflow = 'hidden';
            initModalEvents(modalElement);
        }, 10);
    }
    
    // Инициализация событий модалки
    function initModalEvents(modal) {
        modal.querySelectorAll('.close-modal, .modal__close').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                closeModal(modal);
            });
        });
        
        modal.addEventListener('click', function(e) {
            if (e.target === modal || e.target.classList.contains('modal__overlay')) {
                closeModal(modal);
            }
        });
        
        var onEsc = function(e) {
            if (e.key === 'Escape') {
                closeModal(modal);
                document.removeEventListener('keydown', onEsc);
            }
        };
        document.addEventListener('keydown', onEsc);
    }
    
    // Закрытие модалки
    function closeModal(modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
        
        setTimeout(function() {
            if (modal.parentNode) {
                modal.remove();
            }
        }, 300);
    }
    
    // Обработчик отправки формы
    function onFormSubmit(e) {
        e.preventDefault();
        var form = e.target;
        
        // Проверяем, что это нужная форма
        if (!form.classList.contains('fb-form')) {
            return;
        }
        
        // Защита от двойной отправки
        if (form.dataset.sending === 'true') {
            console.log('Form already sending');
            return false;
        }
        
        form.dataset.sending = 'true';
        
        // Вызываем основную функцию
        var result = window.backendFunc(form);
        
        // Сбрасываем флаг через 5 секунд (на случай ошибки)
        setTimeout(function() {
            delete form.dataset.sending;
        }, 5000);
        
        return false;
    }
    
    // Инициализация конкретной формы
    function initForm(form) {
        // Удаляем старый обработчик
        form.removeEventListener('submit', onFormSubmit);
        
        // Добавляем новый обработчик
        form.addEventListener('submit', onFormSubmit);
        
        // Добавляем атрибут для защиты
        form.setAttribute('novalidate', 'novalidate');
        
        // Маркируем как инициализированную
        form.dataset.fbInit = 'true';
        
        console.log('Form initialized:', form);
    }
    
    // Инициализация всех существующих форм
    function initAllForms() {
        document.querySelectorAll('.fb-form:not([data-fb-init])').forEach(initForm);
    }
    
    // Наблюдатель за новыми формами
    var observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.addedNodes.length) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1) {
                        if (node.classList && node.classList.contains('fb-form') && !node.dataset.fbInit) {
                            initForm(node);
                        }
                        
                        if (node.querySelectorAll) {
                            node.querySelectorAll('.fb-form:not([data-fb-init])').forEach(initForm);
                        }
                    }
                });
            }
        });
    });
    
    // Запускаем наблюдение
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
    
    // Инициализируем при загрузке
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAllForms);
    } else {
        initAllForms();
    }
    
    // Дополнительная инициализация после полной загрузки
    window.addEventListener('load', initAllForms);
    
})();