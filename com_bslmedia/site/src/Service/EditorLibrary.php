<?php
declare(strict_types=1);
namespace BSL\Component\Bslmedia\Site\Service;

/** Read-only catalogue for authenticated editors. Never returns server paths. */
final class EditorLibrary
{
    public static function canRead(object $user): bool
    {
        if (!empty($user->guest)) return false;
        foreach (['core.admin','core.manage','media.create','media.upload'] as $action) {
            if ($user->authorise($action,'com_bslmedia')) return true;
        }
        foreach (['core.create','core.edit','core.edit.own'] as $action) {
            if ($user->authorise($action,'com_content')) return true;
        }
        return false;
    }

    public static function page(string $root, string $query='', string $folder='*', string $type='', int $page=1): array
    {
        $items=[]; $folders=[]; $seen=[];
        $query=mb_substr(trim($query),0,200);
        $scan=MediaStream::scan($root);
        foreach (MediaStream::catalogFromFiles($scan['files']) as $key=>$relative) {
            if ($relative===null || isset($seen[$relative])) continue;
            $seen[$relative]=true;
            $dir=dirname($relative); if ($dir==='.') $dir='';
            $folders[$dir]=true;
            $extension=strtolower(pathinfo($relative,PATHINFO_EXTENSION));
            if (($folder!=='*' && $folder!==$dir) || ($type!=='' && $type!==$extension)
                || ($query!=='' && mb_stripos($relative,$query)===false)) continue;
            $items[]=['name'=>basename($relative),'folder'=>$dir,'type'=>$extension,
                'shortcode'=>'{'.($extension==='mp4'?'bslvideo':'bslaudio').' file="'.$key.'"}'];
        }
        usort($items,fn($a,$b)=>strnatcasecmp($a['folder'].'/'.$a['name'],$b['folder'].'/'.$b['name']));
        $total=count($items); $pages=max(1,(int)ceil($total/30)); $page=max(1,min($page,$pages));
        $folders=array_keys($folders); natcasesort($folders);
        return ['items'=>array_slice($items,($page-1)*30,30),'total'=>$total,'page'=>$page,'pages'=>$pages,'folders'=>array_values($folders),'truncated'=>$scan['truncated']];
    }
}
