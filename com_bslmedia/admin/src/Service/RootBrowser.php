<?php
declare(strict_types=1);
namespace BSL\Component\Bslmedia\Administrator\Service;
use BSL\Component\Bslmedia\Site\Service\MediaStream;

/** Read-only root chooser: one canonical boundary, independent of stored media scope. */
final class RootBrowser
{
    private static function same(string $a,string $b):bool
    {
        return PHP_OS_FAMILY==='Windows'?strcasecmp($a,$b)===0:$a===$b;
    }

    public static function readable(string $path):bool
    {
        return @is_dir($path)&&@is_readable($path)&&(PHP_OS_FAMILY==='Windows'||@is_executable($path));
    }

    private static function relativeValue(string $from,string $to):string
    {
        $a=explode('/',str_replace('\\','/',$from));$b=explode('/',str_replace('\\','/',$to));$common=0;
        while(isset($a[$common],$b[$common])&&self::same($a[$common],$b[$common]))$common++;
        $parts=array_merge(array_fill(0,count($a)-$common,'..'),array_slice($b,$common));
        return $parts===[]?'.':implode('/',$parts);
    }

    public static function current(string $siteRoot,string $mediaRoot):?array
    {
        if($mediaRoot==='')return null;
        try{
            $choice=self::read($siteRoot,$mediaRoot,'','library');
            $resolved=MediaStream::resolveRoot($choice['value'],$siteRoot);
            return $resolved!==''&&self::same($resolved,(string)@realpath($mediaRoot))?$choice:null;
        }catch(\RuntimeException){return null;}
    }

    public static function saveValue(string $siteRoot,string $choice,bool $selected,string $storedRoot):string
    {
        if(!$selected){
            if($storedRoot==='')return '';
            throw new \RuntimeException('COM_BSLMEDIA_ROOT_RESELECT');
        }
        // Only a boundary-relative chooser value is accepted, never a submitted server path.
        return self::read($siteRoot,'',$choice)['value'];
    }

    public static function read(string $siteRoot,string $mediaRoot,string $relative='',string $initial=''):array
    {
        $site=@realpath($siteRoot);
        if($site===false||!self::readable($site)||dirname($site)===$site)throw new \RuntimeException('COM_BSLMEDIA_BAD_PATH');
        $parent=@realpath(dirname(rtrim($siteRoot,'/\\')));
        $boundary=$parent!==false&&self::readable($parent)&&(self::same($parent,$site)||MediaLibrary::inside($parent,$site))?$parent:$site;
        // A Joomla folder directly below / or a drive root cannot open the whole disk.
        if(dirname($boundary)===$boundary)$boundary=$site;
        $siteRelative=self::same($site,$boundary)?'':str_replace(DIRECTORY_SEPARATOR,'/',substr($site,strlen($boundary)+1));
        if($initial==='site')$relative=$siteRelative;
        elseif($initial==='library'){
            $media=$mediaRoot===''?false:@realpath($mediaRoot);
            $relative=$media!==false&&self::readable($media)
                &&(self::same($boundary,$media)||MediaLibrary::inside($boundary,$media))
                ?(self::same($boundary,$media)?'':str_replace(DIRECTORY_SEPARATOR,'/',substr($media,strlen($boundary)+1)))
                :$siteRelative;
        }
        $dir=MediaLibrary::directory($boundary,$relative);
        if(!self::readable($dir))throw new \RuntimeException('COM_BSLMEDIA_BAD_PATH');
        $folders=MediaLibrary::folders($boundary,$relative);
        $value=self::relativeValue($site,$dir);
        if(self::same($dir,$site))$location=['area'=>'site','parts'=>[]];
        elseif(self::same($dir,$boundary))$location=['area'=>'container','parts'=>[]];
        elseif(MediaLibrary::inside($site,$dir))$location=['area'=>'site','parts'=>explode('/',self::relativeValue($site,$dir))];
        else $location=['area'=>'neighbour','parts'=>explode('/',$relative)];
        return ['location'=>$location,'boundaryArea'=>self::same($boundary,$site)?'site':'container','relative'=>$relative,'value'=>$value,'canCreate'=>false,
            'folders'=>array_map(static fn($path)=>['relative'=>$path,'name'=>basename($path)],$folders)];
    }
}
