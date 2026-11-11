<?php
defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use BSL\Component\Bslmedia\Site\Service\MediaStream;
use BSL\Component\Bslmedia\Administrator\Service\MediaLibrary;
$e=static fn($s)=>htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$t=static fn($k)=>Text::_('COM_BSLMEDIA_'.$k);
$token=HTMLHelper::_('form.token');$warning=$t('NO_DESTRUCTIVE');
$limit=MediaLibrary::limit(['max_upload_mb'=>$params->get('max_upload_mb',64),'max_upload_value'=>$params->get('max_upload_value',''),'max_upload_unit'=>$params->get('max_upload_unit','MB')]);
$formatSize=static fn($n)=>$n>=1000000000?number_format($n/1000000000,2,'.',' ').' '.$t('UNIT_GB'):($n>=1000000?number_format($n/1000000,2,'.',' ').' '.$t('UNIT_MB'):number_format($n,0,'.',' ').' B');
?>
<div id="bsl-library" data-browse-error="<?= $e($t('BROWSE_ERROR')) ?>" data-copied="<?= $e($t('COPIED')) ?>" data-copy-failed="<?= $e($t('COPY_FAILED')) ?>" data-uploading="<?= $e($t('UPLOADING')) ?>" data-root-title="<?= $e($t('BROWSE')) ?>" data-destination-title="<?= $e($t('CHOOSE_DESTINATION')) ?>" data-root-label="<?= $e($t('ROOT_FOLDER')) ?>">
<p class="alert alert-info"><?= $e($t('PUBLIC_LIBRARY')) ?></p>
<?php if($allowed('core.admin')): ?>
<form method="post" action="index.php?option=com_bslmedia">
<input type="hidden" name="task" value="manage.save"><?= $token ?>
<label for="bsl-root"><?= $e($t('ROOT')) ?></label>
<div class="input-group"><input class="form-control" id="bsl-root" name="root" value="<?= $e($params->get('root','')) ?>" aria-describedby="bsl-root-help">
<button type="button" id="bsl-browse" class="btn btn-secondary" title="<?= $e($warning) ?>"><?= $e($t('BROWSE')) ?></button></div>
<p id="bsl-root-help"><?= $e($t('ROOT_HELP')) ?></p><p><?= $e($warning) ?></p>
<label for="bsl-limit"><?= $e($t('UPLOAD_LIMIT')) ?></label>
<div class="input-group" style="max-width:22rem"><input class="form-control" id="bsl-limit" name="max_upload_value" type="number" min="0.000001" max="999999999" step="any" required value="<?= $e($limit['value']) ?>">
<select class="form-select" name="max_upload_unit" aria-label="<?= $e($t('SIZE_UNIT')) ?>"><?php foreach(['MB','GB'] as $unit): ?><option value="<?= $e($unit) ?>" <?= $limit['unit']===$unit?'selected':'' ?>><?= $e($t('UNIT_'.$unit)) ?></option><?php endforeach ?></select></div>
<button class="btn btn-primary mt-2" type="submit"><?= $e($t('SAVE')) ?></button>
</form>
<?php endif ?>
<?php if($root===''): ?><p><?= $e($t('UNCONFIGURED')) ?></p><?php else: ?>
<hr><h2><?= $e($t('DESTINATION')) ?></h2>
<p><strong id="bsl-destination-path"><?= $e($t('ROOT_FOLDER').($folder===''?'':' / '.$folder)) ?></strong>
<button type="button" id="bsl-destination-browse" class="btn btn-secondary" title="<?= $e($warning) ?>"><?= $e($t('CHOOSE_DESTINATION')) ?></button></p>
<p><?= $e($t('DESTINATION_HELP')) ?></p>
<p class="alert alert-info"><?= $e($t('DEPTH_HELP')) ?></p>
<?php if($allowed('media.upload')): ?>
<form id="bsl-upload-form" method="post" action="index.php?option=com_bslmedia" enctype="multipart/form-data" class="mt-3">
<input type="hidden" name="task" value="manage.upload"><input id="bsl-destination" type="hidden" name="folder" value="<?= $e($folder) ?>"><?= $token ?>
<input id="bsl-files" type="file" name="media[]" multiple accept=".mp3,.mp4,.opus" hidden>
<input id="bsl-selected-count" type="hidden" name="selected_count" value="0">
<button id="bsl-upload-trigger" type="button" class="btn btn-primary" disabled title="<?= $e($warning) ?>"><?= $e($t('UPLOAD_FILES')) ?></button>
<p><?= $e($t('AUTO_UPLOAD_HELP')) ?> <?= $e($limit['value'].' '.$t('UNIT_'.$limit['unit'])) ?></p>
<p id="bsl-upload-status" role="status"></p><noscript><p><?= $e($t('JS_REQUIRED')) ?></p></noscript>
<p><?= $e($warning) ?></p></form>
<?php else: ?><input id="bsl-destination" type="hidden" value="<?= $e($folder) ?>"><?php endif ?>
<hr><h2><?= $e($t('MEDIA_FILES')) ?></h2><p role="status" id="bsl-copy-status"></p>
<div id="bsl-table-controls" hidden><label for="bsl-folder-filter"><?= $e($t('FILTER_FOLDER')) ?></label>
<select id="bsl-folder-filter" class="form-select" style="max-width:30rem"><option value="*"><?= $e($t('ALL_FOLDERS')) ?></option><option value="folder:"><?= $e($t('ROOT_FOLDER')) ?></option></select>
<p><?= $e($t('SORT_HELP')) ?></p></div><p><?= $e($t('DATE_HELP')) ?></p>
<div style="overflow-x:auto"><table id="bsl-media-table" class="table"><thead><tr>
<th scope="col" aria-sort="none"><button type="button" class="btn btn-link p-0" data-sort="name"><?= $e($t('FILE')) ?></button></th>
<th scope="col"><?= $e($t('FOLDER')) ?></th><th scope="col"><?= $e($t('TYPE')) ?></th>
<th scope="col" aria-sort="none"><button type="button" class="btn btn-link p-0" data-sort="size"><?= $e($t('SIZE')) ?></button></th>
<th scope="col" aria-sort="none"><button type="button" class="btn btn-link p-0" data-sort="modified"><?= $e($t('MODIFIED')) ?></button></th>
<th scope="col"><?= $e($t('SHORTCODE')) ?></th></tr></thead><tbody>
<?php $catalog=MediaStream::catalog($root);$keys=[];foreach($catalog as $k=>$f)if(is_string($f)&&!isset($keys[$f]))$keys[$f]=$k;$i=0;foreach($keys as $file=>$key):
$ext=strtolower(pathinfo($file,PATHINFO_EXTENSION));$shortcode='{bsl'.($ext==='mp4'?'video':'audio').' file="'.$key.'"}';$id='bsl-code-'.++$i;
$fileFolder=dirname($file)==='.'?'':dirname($file);$fileSize=(int)@filesize($root.'/'.$file);$modified=(int)@filemtime($root.'/'.$file);
?>
<tr data-name="<?= $e(basename($file)) ?>" data-folder="<?= $e($fileFolder) ?>" data-size="<?= $e($fileSize) ?>" data-modified="<?= $e($modified) ?>"><td><?= $e(basename($file)) ?></td>
<td><?= $e($fileFolder===''?$t('ROOT_FOLDER'):$fileFolder) ?></td><td><?= $e(strtoupper($ext)) ?></td><td><?= $e($formatSize($fileSize)) ?></td>
<td><?= $modified>0?$e(HTMLHelper::_('date',gmdate('Y-m-d H:i:s',$modified),'Y-m-d H:i')):'—' ?></td>
<td><input id="<?= $e($id) ?>" type="text" readonly value="<?= $e($shortcode) ?>" aria-label="<?= $e($t('SHORTCODE')) ?>" style="min-width:20rem"><button type="button" class="btn btn-secondary bsl-copy" data-copy="<?= $e($id) ?>"><?= $e($t('COPY')) ?></button></td></tr>
<?php endforeach ?></tbody></table></div>
<?php endif ?>
<?php if($allowed('core.admin')||$root!==''): ?>
<dialog id="bsl-folder-picker" aria-labelledby="bsl-picker-title" style="width:min(42rem,90vw)">
<h2 id="bsl-picker-title"><?= $e($t('BROWSE')) ?></h2>
<div id="bsl-root-scope"><p><?= $e($t('BROWSE_SCOPE')) ?></p><label for="bsl-scope"><?= $e($t('AREA')) ?></label><select id="bsl-scope"><option value="site"><?= $e($t('SITE_AREA')) ?></option><?php if($root!==''): ?><option value="media"><?= $e($t('MEDIA_AREA')) ?></option><?php endif ?></select></div>
<p id="bsl-picker-depth" hidden><?= $e($t('DEPTH_HELP')) ?></p><p id="bsl-picker-path"></p>
<button type="button" id="bsl-up" class="btn btn-secondary"><?= $e($t('UP')) ?></button><ul id="bsl-picker-folders"></ul>
<?php if($allowed('media.create')&&$root!==''): ?>
<form id="bsl-create-form" method="post" action="index.php?option=com_bslmedia" hidden>
<input type="hidden" name="task" value="manage.create"><input type="hidden" name="ajax" value="1"><input id="bsl-create-parent" type="hidden" name="folder" value=""><?= $token ?>
<label for="bsl-new-folder"><?= $e($t('NEW_FOLDER')) ?></label><input id="bsl-new-folder" name="new_folder" required maxlength="100">
<button id="bsl-create-button" type="submit" class="btn btn-secondary" title="<?= $e($warning) ?>"><?= $e($t('CREATE_FOLDER')) ?></button></form>
<?php endif ?>
<p id="bsl-picker-error" role="status"></p>
<button type="button" id="bsl-select-folder" class="btn btn-primary"><?= $e($t('SELECT_FOLDER')) ?></button><button type="button" id="bsl-close-picker" class="btn btn-secondary"><?= $e($t('CLOSE')) ?></button>
<p title="<?= $e($warning) ?>"><?= $e($warning) ?></p></dialog>
<?php endif ?></div>
