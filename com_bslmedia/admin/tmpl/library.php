<?php
defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use BSL\Component\Bslmedia\Site\Service\MediaStream;
use BSL\Component\Bslmedia\Administrator\Service\MediaLibrary;
use BSL\Component\Bslmedia\Administrator\Service\RootBrowser;
$e=static fn($s)=>htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$t=static fn($k)=>Text::_('COM_BSLMEDIA_'.$k);
$link=static fn($path)=>'index.php?option=com_bslmedia&folder='.rawurlencode($path);
$token=HTMLHelper::_('form.token');$warning=$t('NO_DESTRUCTIVE');
$rootChoice=$allowed('core.admin')&&$root!==''?RootBrowser::current(JPATH_ROOT,$root):null;
$locationLabel=static function($location)use($t){return $t(['site'=>'WITHIN_SITE','neighbour'=>'BESIDE_SITE','container'=>'SITE_CONTAINER'][$location['area']]).($location['parts']===[]?'':' → '.implode(' → ',$location['parts']));};
$limit=MediaLibrary::limit(['max_upload_mb'=>$params->get('max_upload_mb',64),'max_upload_value'=>$params->get('max_upload_value',''),'max_upload_unit'=>$params->get('max_upload_unit','MB')]);
$formatSize=static fn($n)=>$n>=1000000000?number_format($n/1000000000,2,'.',' ').' '.$t('UNIT_GB'):($n>=1000000?number_format($n/1000000,2,'.',' ').' '.$t('UNIT_MB'):number_format($n,0,'.',' ').' B');
?>
<div id="bsl-library" data-browse-error="<?= $e($t('BROWSE_ERROR')) ?>" data-copied="<?= $e($t('COPIED')) ?>" data-copy-failed="<?= $e($t('COPY_FAILED')) ?>" data-uploading="<?= $e($t('UPLOADING')) ?>" data-site-label="<?= $e($t('WITHIN_SITE')) ?>" data-neighbour-label="<?= $e($t('BESIDE_SITE')) ?>" data-container-label="<?= $e($t('SITE_CONTAINER')) ?>">
<?php if($allowed('core.admin')): ?>
<details id="bsl-settings" class="card p-3 mb-3" <?= $root===''?'open':'' ?>>
<summary class="h3"><?= $e($t('LIBRARY_SETTINGS')) ?></summary>
<form method="post" action="index.php?option=com_bslmedia" class="mt-3">
<input type="hidden" name="task" value="manage.save"><?= $token ?>
<p id="bsl-root-label"><?= $e($t('ROOT')) ?></p>
<input id="bsl-root-choice" type="hidden" name="root_choice" value="<?= $e($rootChoice['relative']??'') ?>">
<input id="bsl-root-selected" type="hidden" name="root_selected" value="<?= $rootChoice===null?'0':'1' ?>">
<div class="d-flex flex-wrap align-items-center gap-2"><strong id="bsl-root-location" aria-labelledby="bsl-root-label"><?= $e($rootChoice!==null?$locationLabel($rootChoice['location']):$t($root===''?'ROOT_NOT_CHOSEN':'ROOT_RESELECT')) ?></strong>
<button type="button" id="bsl-browse" class="btn btn-secondary" title="<?= $e($warning) ?>"><?= $e($t('BROWSE')) ?></button></div>
<p id="bsl-root-help"><?= $e($t('ROOT_HELP')) ?></p>
<label for="bsl-limit"><?= $e($t('UPLOAD_LIMIT')) ?></label>
<div class="input-group" style="max-width:22rem"><input class="form-control" id="bsl-limit" name="max_upload_value" type="number" min="0.000001" max="999999999" step="any" required value="<?= $e($limit['value']) ?>">
<select class="form-select" name="max_upload_unit" aria-label="<?= $e($t('SIZE_UNIT')) ?>"><?php foreach(['MB','GB'] as $unit): ?><option value="<?= $e($unit) ?>" <?= $limit['unit']===$unit?'selected':'' ?>><?= $e($t('UNIT_'.$unit)) ?></option><?php endforeach ?></select></div>
<button class="btn btn-primary mt-2" type="submit"><?= $e($t('SAVE')) ?></button>
</form></details>
<?php endif ?>
<?php if($root===''): ?><p><?= $e($t('UNCONFIGURED')) ?></p><?php else:
$scan=MediaStream::scan($root);$catalog=MediaStream::catalogFromFiles($scan['files']);$keys=[];
foreach($catalog as $k=>$f)if(is_string($f)&&!isset($keys[$f]))$keys[$f]=$k;
$subfolders=[];$folderError='';try{$subfolders=MediaLibrary::folders($root,$folder);}catch(\RuntimeException $error){$folderError=Text::_($error->getMessage());}
?>
<h2><?= $e($t('MEDIA_FILES')) ?></h2><p><?= $e($t('LIBRARY_HELP')) ?></p>
<nav aria-label="<?= $e($t('CURRENT_FOLDER')) ?>"><ol class="breadcrumb" id="bsl-current-path">
<li class="breadcrumb-item"><?php if($folder===''): ?><strong aria-current="page"><?= $e($t('ROOT_FOLDER')) ?></strong><?php else: ?><a href="<?= $e($link('')) ?>"><?= $e($t('ROOT_FOLDER')) ?></a><?php endif ?></li>
<?php $crumb='';$parts=$folder===''?[]:explode('/',$folder);foreach($parts as $part):$crumb.=($crumb===''?'':'/').$part; ?>
<li class="breadcrumb-item"><?php if($crumb===$folder): ?><strong aria-current="page"><?= $e($part) ?></strong><?php else: ?><a href="<?= $e($link($crumb)) ?>"><?= $e($part) ?></a><?php endif ?></li>
<?php endforeach ?></ol></nav>
<?php if($scan['truncated']): ?><p class="alert alert-warning"><?= $e($t('SCAN_LIMIT')) ?></p><?php endif ?>
<?php if($folderError!==''): ?><p class="alert alert-warning"><?= $e($folderError) ?></p><?php endif ?>
<div class="d-flex flex-wrap gap-3 align-items-start mb-2">
<?php if($allowed('media.upload')): ?>
<form id="bsl-upload-form" method="post" action="index.php?option=com_bslmedia" enctype="multipart/form-data">
<input type="hidden" name="task" value="manage.upload"><input id="bsl-destination" type="hidden" name="folder" value="<?= $e($folder) ?>"><?= $token ?>
<input id="bsl-files" type="file" name="media[]" multiple accept=".mp3,.mp4,.opus" hidden><input id="bsl-selected-count" type="hidden" name="selected_count" value="0">
<button id="bsl-upload-trigger" type="button" class="btn btn-primary" disabled title="<?= $e($warning) ?>"><?= $e($t('UPLOAD_HERE')) ?></button>
<p id="bsl-upload-status" role="status"></p><noscript><p><?= $e($t('JS_REQUIRED')) ?></p></noscript></form>
<?php endif ?>
<?php if($allowed('media.create')): ?>
<?php if($folder===''||count(explode('/',$folder))<MediaStream::MAX_DEPTH): ?>
<details id="bsl-create-panel"><summary class="btn btn-secondary" title="<?= $e($warning) ?>"><?= $e($t('CREATE_FOLDER')) ?></summary>
<form id="bsl-create-form" method="post" action="index.php?option=com_bslmedia" class="mt-2">
<input type="hidden" name="task" value="manage.create"><input type="hidden" name="folder" value="<?= $e($folder) ?>"><?= $token ?>
<label for="bsl-new-folder"><?= $e($t('NEW_FOLDER')) ?></label><input class="form-control" id="bsl-new-folder" name="new_folder" required maxlength="100">
<button type="submit" class="btn btn-primary mt-2"><?= $e($t('CREATE_FOLDER')) ?></button></form></details>
<?php else: ?><p><?= $e($t('DEPTH')) ?></p><?php endif ?>
<?php endif ?></div>
<p><?= $e($t('AUTO_UPLOAD_HELP')) ?> <?= $e($limit['value'].' '.$t('UNIT_'.$limit['unit'])) ?></p>
<p class="small" title="<?= $e($warning) ?>"><?= $e($warning) ?></p>
<div class="d-flex flex-wrap gap-2 mb-3" id="bsl-folders">
<?php if($folder!==''): ?><a class="btn btn-secondary" href="<?= $e($link(dirname($folder)==='.'?'':dirname($folder))) ?>">↑ <?= $e($t('UP')) ?></a><?php endif ?>
<?php foreach($subfolders as $child): ?><a class="btn btn-outline-primary" href="<?= $e($link($child)) ?>"><span aria-hidden="true">📁</span> <?= $e(basename($child)) ?></a><?php endforeach ?></div>
<label for="bsl-file-search"><?= $e($t('SEARCH_CURRENT')) ?></label><input type="search" id="bsl-file-search" class="form-control mb-2" style="max-width:30rem" maxlength="200">
<p><?= $e($t('SORT_HELP')) ?> <?= $e($t('DATE_HELP')) ?></p><p role="status" id="bsl-copy-status"></p>
<div style="overflow-x:auto"><table id="bsl-media-table" class="table"><thead><tr>
<th scope="col" aria-sort="none"><button type="button" class="btn btn-link p-0" data-sort="name"><?= $e($t('FILE')) ?></button></th>
<th scope="col"><?= $e($t('TYPE')) ?></th>
<th scope="col" aria-sort="none"><button type="button" class="btn btn-link p-0" data-sort="size"><?= $e($t('SIZE')) ?></button></th>
<th scope="col" aria-sort="none"><button type="button" class="btn btn-link p-0" data-sort="modified"><?= $e($t('MODIFIED')) ?></button></th>
<th scope="col"><?= $e($t('SHORTCODE')) ?></th></tr></thead><tbody>
<?php $i=0;foreach($keys as $file=>$key):$fileFolder=dirname($file)==='.'?'':dirname($file);if($fileFolder!==$folder)continue;
$ext=strtolower(pathinfo($file,PATHINFO_EXTENSION));$shortcode='{bsl'.($ext==='mp4'?'video':'audio').' file="'.$key.'"}';$id='bsl-code-'.++$i;
$fileSize=(int)@filesize($root.'/'.$file);$modified=(int)@filemtime($root.'/'.$file);
?>
<tr data-name="<?= $e(basename($file)) ?>" data-size="<?= $e($fileSize) ?>" data-modified="<?= $e($modified) ?>"><td><?= $e(basename($file)) ?></td>
<td><?= $e(strtoupper($ext)) ?></td><td><?= $e($formatSize($fileSize)) ?></td><td><?= $modified>0?$e(HTMLHelper::_('date',gmdate('Y-m-d H:i:s',$modified),'Y-m-d H:i')):'—' ?></td>
<td><input id="<?= $e($id) ?>" type="text" readonly value="<?= $e($shortcode) ?>" aria-label="<?= $e($t('SHORTCODE')) ?>" style="min-width:20rem"><button type="button" class="btn btn-secondary bsl-copy" data-copy="<?= $e($id) ?>"><?= $e($t('COPY')) ?></button></td></tr>
<?php endforeach ?></tbody></table></div>
<p id="bsl-empty" <?= $i>0?'hidden':'' ?>><?= $e($t('EMPTY_FOLDER')) ?></p><p class="small"><?= $e($t('PUBLIC_LIBRARY')) ?></p>
<?php endif ?>
<?php if($allowed('core.admin')): ?>
<dialog id="bsl-folder-picker" aria-labelledby="bsl-picker-title" style="width:min(42rem,90vw)">
<h2 id="bsl-picker-title"><?= $e($t('BROWSE')) ?></h2>
<p><?= $e($t('BROWSE_SCOPE')) ?></p>
<p><?= $e($t('BROWSE_BOUNDARY')) ?> <strong id="bsl-picker-boundary"></strong></p>
<button type="button" id="bsl-site-home" class="btn btn-secondary"><?= $e($t('BACK_TO_SITE')) ?></button>
<p id="bsl-picker-path"></p><button type="button" id="bsl-up" class="btn btn-secondary"><?= $e($t('UP')) ?></button><ul id="bsl-picker-folders"></ul>
<p id="bsl-picker-error" role="status"></p>
<button type="button" id="bsl-select-folder" class="btn btn-primary"><?= $e($t('SELECT_FOLDER')) ?></button><button type="button" id="bsl-close-picker" class="btn btn-secondary"><?= $e($t('CLOSE')) ?></button>
<p title="<?= $e($warning) ?>"><?= $e($warning) ?></p></dialog>
<?php endif ?></div>
