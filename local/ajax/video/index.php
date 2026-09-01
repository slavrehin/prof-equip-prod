<?php require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

$request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();
$poster = $request->get("p");
$video = $request->get("v");
?>
<?php if ($poster && $video): ?>
    <div class="modal video-modal" data-modal="video-modal">
        <div class="modal-content-wrapper">
            <div class="close-modal-wrapper">
                <button class="btn close-modal">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M5.05026 14.9497L14.9498 5.05025" stroke="#343176"/>
                        <path d="M14.9498 14.9497L5.05026 5.05025" stroke="#343176"/>
                    </svg>
                </button>
            </div>
            <div class="modal-content">
                <div class="video-block">
                    <video src="<?= CFile::GetPath($video); ?>"
                           width="840"
                           height="475"
                           preload="auto"
                           poster="<?= CFile::GetPath($poster); ?>"
                    ></video>
                    <button class="btn play-btn" aria-label="Кнопка открытия видео">
                        <svg width="96" height="96" viewBox="0 0 96 96" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="48" cy="48" r="48" fill="#49454C"/>
                            <path d="M38.5 31.5455L67 48L38.5 64.4545L38.5 31.5455Z" stroke="white"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    Видео не найдено
<?php endif; ?>