<?php

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php');

use ProfEquip\Import\ProductImporter;

require_once($_SERVER['DOCUMENT_ROOT'] . '/local/modules/profequip.import/lib/ProductImporter.php');

global $USER, $APPLICATION;

if (!$USER->IsAdmin()) {
    $APPLICATION->AuthForm('Доступ только для администраторов');
    require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php');
    exit;
}

$APPLICATION->SetTitle('Импорт товаров из CSV');

$templateUrl = '/local/modules/profequip.import/templates/products_template.csv';
$sectionsRefUrl = '/local/modules/profequip.import/templates/reference_sections.csv';
$result = null;
$errorMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
    if (!empty($_FILES['import_file']['tmp_name']) && is_uploaded_file($_FILES['import_file']['tmp_name'])) {
        try {
            $importer = new ProductImporter();
            $result = $importer->importFile($_FILES['import_file']['tmp_name']);
        } catch (\Throwable $e) {
            $errorMessage = 'Ошибка импорта: ' . $e->getMessage();
        }
    } else {
        $errorMessage = 'Файл не выбран или произошла ошибка загрузки';
    }
}

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php');

?>
<style>
    .peimport-wrap { max-width: 1000px; }
    .peimport-help { background: #f5f7fa; border: 1px solid #dbe1e8; padding: 12px 16px; margin-bottom: 16px; border-radius: 4px; }
    .peimport-help code { background: #eef1f5; padding: 1px 5px; border-radius: 3px; }
    .peimport-log-table { border-collapse: collapse; width: 100%; margin-top: 12px; }
    .peimport-log-table th, .peimport-log-table td { border: 1px solid #d7dee5; padding: 4px 8px; font-size: 12px; text-align: left; }
    .peimport-log-table th { background: #eef1f5; }
    .peimport-status-created { color: #1f883d; font-weight: bold; }
    .peimport-status-updated { color: #0969da; font-weight: bold; }
    .peimport-status-error { color: #cf222e; font-weight: bold; }
</style>

<div class="peimport-wrap">

<div class="peimport-help">
    <p><b>Как пользоваться:</b></p>
    <ol>
        <li>Скачайте <a href="<?= htmlspecialcharsbx($templateUrl) ?>" target="_blank">шаблон CSV-файла</a> (разделитель <code>;</code>, кодировка UTF-8).</li>
        <li>Заполните строки товаров. Обязательные колонки: <code>SECTION_CODE</code>, <code>NAME</code>, <code>PRICE</code>.</li>
        <li><code>SECTION_CODE</code> должен совпадать с символьным кодом существующего раздела каталога - см. <a href="<?= htmlspecialcharsbx($sectionsRefUrl) ?>" target="_blank">справочник разделов</a>.</li>
        <li>Если <code>CODE</code> товара не заполнен - он будет сгенерирован из названия. Товар с уже существующим <code>CODE</code> в том же разделе будет обновлён, а не задвоен.</li>
        <li>Любая дополнительная колонка, название которой совпадает с символьным кодом свойства товара (например <code>BRAND</code>, <code>COUNTRY</code>, <code>MOSHHNOST_KVT</code>), будет автоматически сопоставлена со свойством. Для множественных значений внутри ячейки используйте разделитель <code>|</code>.</li>
        <li>Загрузите заполненный файл ниже и нажмите «Импортировать».</li>
    </ol>
</div>

<form method="POST" enctype="multipart/form-data" action="<?= htmlspecialcharsbx($APPLICATION->GetCurPageParam()) ?>">
    <?= bitrix_sessid_post() ?>
    <input type="file" name="import_file" accept=".csv" required>
    <input type="submit" class="adm-btn-save" value="Импортировать">
</form>

<?php if ($errorMessage): ?>
    <?php
    $msg = new CAdminMessage($errorMessage);
    echo $msg->Show();
    ?>
<?php endif; ?>

<?php if ($result): ?>
    <?php
    $s = $result['summary'];
    $summaryText = "Обработано строк: {$s['total']}. Создано: {$s['created']}. Обновлено: {$s['updated']}. Ошибок: {$s['errors']}.";
    $msg = new CAdminMessage([
        'MESSAGE' => $summaryText,
        'TYPE' => $s['errors'] > 0 ? 'WARNING' : 'OK',
    ]);
    echo $msg->Show();
    ?>
    <table class="peimport-log-table">
        <thead>
            <tr>
                <th>Строка</th>
                <th>Название</th>
                <th>Статус</th>
                <th>Сообщение</th>
                <th>ID элемента</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($result['log'] as $entry): ?>
            <tr>
                <td><?= (int)$entry['row'] ?></td>
                <td><?= htmlspecialcharsbx($entry['name']) ?></td>
                <td class="peimport-status-<?= htmlspecialcharsbx($entry['status']) ?>"><?= htmlspecialcharsbx($entry['status']) ?></td>
                <td><?= htmlspecialcharsbx($entry['message']) ?></td>
                <td>
                    <?php if ($entry['id']): ?>
                        <a href="/bitrix/admin/iblock_element_edit.php?IBLOCK_ID=<?= ProductImporter::IBLOCK_ID ?>&type=catalog&lang=<?= LANGUAGE_ID ?>&ID=<?= (int)$entry['id'] ?>" target="_blank"><?= (int)$entry['id'] ?></a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

</div>

<?php
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php');
