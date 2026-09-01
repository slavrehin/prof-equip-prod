<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
?>

<script>
  window.dataLayer = window.dataLayer || [];
  window.dataLayer.push({'event': 'form_success'});
</script>

<div class="modal modal--cost-modal" data-modal="success-modal">
    <div class="modal-content"> <button class="btn close-modal">×</button>
        <p class="modal__title">Ваша заявка принята</p>
		<p class="modal__descr">Благодарим Вас за обращение. <br>Ваша заявка принята. <br>В ближайшее время менеджер ПРОФЭКВИП свяжется с Вами.</p>
    </div>
</div>