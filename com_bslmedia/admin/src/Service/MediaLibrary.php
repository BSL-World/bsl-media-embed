<?php
declare(strict_types=1);
namespace BSL\Component\Bslmedia\Administrator\Service;

/** Filesystem operations only. Controllers enforce Joomla ACL/CSRF and uploaded-file provenance. */
final class MediaLibrary
{
    public static function parseLimit(string $value,string $unit):int
    {
        if(!in_array($unit,['MB','GB'],true)||!preg_match('~^(0|[1-9][0-9]{0,8})(?:\.([0-9]{1,6}))?$~D',$value,$m))throw new \RuntimeException('COM_BSLMEDIA_BAD_LIMIT');
        $scale=$unit==='GB'?1000000000:1000000;
        $fraction=$m[2]??'';
        $bytes=(int)$m[1]*$scale+($fraction===''?0:intdiv((int)$fraction*$scale,10**strlen($fraction)));
        if($bytes<=0)throw new \RuntimeException('COM_BSLMEDIA_BAD_LIMIT');
        return $bytes;
    }
    public static function limit(array $params):array
    {
        $value=(string)($params['max_upload_value']??'');$unit=(string)($params['max_upload_unit']??'MB');
        if($value!=='')return ['value'=>$value,'unit'=>$unit,'bytes'=>self::parseLimit($value,$unit)];
        // Keep the exact byte limit of old MiB-based settings instead of silently reinterpreting it.
        $bytes=(int)($params['max_upload_mb']??64)*1048576;
        if($bytes<=0)throw new \RuntimeException('COM_BSLMEDIA_BAD_LIMIT');
        return ['value'=>rtrim(rtrim(number_format($bytes/1000000,6,'.',''),'0'),'.'),'unit'=>'MB','bytes'=>$bytes];
    }
    public static function name(string $name): bool
    {
        return $name!=='' && preg_match('//u',$name)===1 && strlen($name)<=240 && $name===trim($name) && !str_starts_with($name,'.')
            && !str_ends_with($name,'.') && !str_contains($name,'..')
            && !preg_match('~[\\\\/\x00-\x1f\x7f:%*?"<>|]~u',$name)
            && !preg_match('~^(CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(?:\.|$)~i',$name);
    }

    public static function directory(string $root, string $relative='', int $maxDepth=2): string
    {
        $base=realpath($root);
        if ($root==='' || $base===false || !is_dir($base)) throw new \RuntimeException('COM_BSLMEDIA_UNCONFIGURED');
        if ($relative==='') return $base;
        $parts=explode('/',$relative);
        if (count($parts)>$maxDepth) throw new \RuntimeException('COM_BSLMEDIA_DEPTH');
        $path=$base;
        foreach($parts as $part){
            if (!self::name($part)) throw new \RuntimeException('COM_BSLMEDIA_BAD_PATH');
            $next=$path.DIRECTORY_SEPARATOR.$part;$resolved=realpath($next);
            if ($resolved===false || !is_dir($resolved) || is_link($next) || !self::inside($base,$resolved)) throw new \RuntimeException('COM_BSLMEDIA_BAD_PATH');
            $path=$resolved;
        }
        return $path;
    }

    public static function inside(string $base,string $path):bool
    {
        $prefix=rtrim($base,'/\\').DIRECTORY_SEPARATOR;
        return PHP_OS_FAMILY==='Windows' ? strncasecmp($path,$prefix,strlen($prefix))===0 : str_starts_with($path,$prefix);
    }

    public static function folders(string $root,string $relative='',int $maxDepth=2):array
    {
        $dir=self::directory($root,$relative,$maxDepth);$result=[];
        if ($relative!=='' && count(explode('/',$relative)) >= $maxDepth) return [];
        foreach(@scandir($dir) ?: [] as $name){
            if (!self::name($name)) continue;
            $rel=($relative===''?'':$relative.'/').$name;
            try {self::directory($root,$rel,$maxDepth);$result[]=$rel;}catch(\RuntimeException){}
        }
        return $result;
    }

    public static function createFolder(string $root,string $parent,string $name):void
    {
        if (!self::name($name)) throw new \RuntimeException('COM_BSLMEDIA_BAD_NAME');
        $depth=$parent===''?0:count(explode('/',$parent));
        if ($depth>=2) throw new \RuntimeException('COM_BSLMEDIA_DEPTH');
        $dir=self::directory($root,$parent);$target=$dir.DIRECTORY_SEPARATOR.$name;
        if (file_exists($target)||is_link($target)) throw new \RuntimeException('COM_BSLMEDIA_EXISTS');
        if (!@mkdir($target,0750)) throw new \RuntimeException('COM_BSLMEDIA_WRITE_FAILED');
    }

    public static function validate(string $source,string $name,int $limit):void
    {
        if (!self::name($name)||!preg_match('~\.(mp3|mp4|opus)$~iD',$name,$m)) throw new \RuntimeException('COM_BSLMEDIA_BAD_TYPE');
        if(preg_match('~\.(php[0-9]*|phtml|pht|phar|cgi|pl|py|shtml|asp|aspx|jsp|exe|bat|cmd|ps1|sh)(?:\.|$)~i',$name))throw new \RuntimeException('COM_BSLMEDIA_BAD_TYPE');
        $size=@filesize($source);
        if ($size===false||$size<=0||$limit<=0||$size>$limit) throw new \RuntimeException('COM_BSLMEDIA_TOO_LARGE');
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($source);
        $h=@fopen($source,'rb');if(!$h)throw new \RuntimeException('COM_BSLMEDIA_BAD_TYPE');
        try{$head=fread($h,65536);}finally{fclose($h);}
        $valid=match(strtolower($m[1])){
            'mp3'=>$mime==='audio/mpeg',
            'mp4'=>in_array($mime,['video/mp4','audio/mp4','application/mp4'],true)&&substr($head,4,4)==='ftyp',
            'opus'=>in_array($mime,['audio/ogg','application/ogg','audio/opus'],true)&&str_starts_with($head,'OggS')&&str_contains($head,'OpusHead'),
        };
        if(!$valid)throw new \RuntimeException('COM_BSLMEDIA_BAD_TYPE');
    }

    public static function upload(string $root,string $relative,string $source,string $name,int $limit):void
    {
        self::validate($source,$name,$limit);$dir=self::directory($root,$relative);
        $target=$dir.DIRECTORY_SEPARATOR.$name;
        if (file_exists($target)||is_link($target))throw new \RuntimeException('COM_BSLMEDIA_EXISTS');
        $temp=$dir.DIRECTORY_SEPARATOR.'.bsl-upload-'.bin2hex(random_bytes(16)).'.tmp';
        $out=@fopen($temp,'x+b');if(!$out)throw new \RuntimeException('COM_BSLMEDIA_WRITE_FAILED');
        $in=null;
        try{
            $in=@fopen($source,'rb');if(!$in)throw new \RuntimeException('COM_BSLMEDIA_WRITE_FAILED');
            $bytes=0;
            while(!feof($in)){
                $chunk=fread($in,65536);if($chunk===false)throw new \RuntimeException('COM_BSLMEDIA_WRITE_FAILED');
                $bytes+=strlen($chunk);if($bytes>$limit)throw new \RuntimeException('COM_BSLMEDIA_TOO_LARGE');
                $offset=0;while($offset<strlen($chunk)){$n=fwrite($out,substr($chunk,$offset));if($n===false||$n===0)throw new \RuntimeException('COM_BSLMEDIA_WRITE_FAILED');$offset+=$n;}
            }
            if(!fflush($out)||$bytes!==filesize($source))throw new \RuntimeException('COM_BSLMEDIA_WRITE_FAILED');
            fclose($out);$out=null;fclose($in);$in=null;
            self::validate($temp,$name,$limit);
            if(self::directory($root,$relative)!==$dir)throw new \RuntimeException('COM_BSLMEDIA_BAD_PATH');
            // link() publishes a complete file atomically and fails if destination exists.
            // Never use move_uploaded_file()/rename(): they can replace an existing file.
            @chmod($temp,0640);
            if(!@link($temp,$target))throw new \RuntimeException('COM_BSLMEDIA_PUBLISH_FAILED');
        }finally{
            if(is_resource($in))fclose($in);if(is_resource($out))fclose($out);
            if(is_file($temp))@unlink($temp); // only our own incomplete staging file
        }
    }
}
