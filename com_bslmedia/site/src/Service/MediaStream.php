<?php
declare(strict_types=1);
namespace BSL\Component\Bslmedia\Site\Service;

/** No Joomla bootstrap: the same request planner is used by the HTTP controller and unit tests. */
final class MediaStream
{
    public const MAX_DEPTH = 32;
    public const MAX_ENTRIES = 20000;
    public static function resolveRoot(string $root, string $siteRoot): string
    {
        if ($root === '' || str_contains($root, "\0") || str_contains($root, '%') || str_contains($root, '://')) return '';
        $absolute = preg_match('~^(?:/|[A-Za-z]:[\\\\/])~', $root);
        if (!$absolute && preg_match('~^[A-Za-z]:~', $root)) return '';
        $resolved=realpath($absolute ? $root : $siteRoot.DIRECTORY_SEPARATOR.$root);
        return $resolved !== false && is_dir($resolved) && dirname($resolved)!==$resolved ? $resolved : '';
    }

    /** Public media entries. Technical limits bound recursion and scan work. */
    public static function files(string $root):array
    {
        return self::scan($root)['files'];
    }

    public static function scan(string $root,int $budget=self::MAX_ENTRIES):array
    {
        $base=$root===''?false:realpath($root);if($base===false||!is_dir($base))return ['files'=>[],'truncated'=>false];
        $budget=max(1,min($budget,self::MAX_ENTRIES));$files=[];$visited=[];$count=0;$truncated=false;
        $walk=function(string $dir,string $prefix,int $depth)use(&$walk,&$files,&$visited,&$count,&$truncated,$budget,$base):void{
            $identity=PHP_OS_FAMILY==='Windows'?strtolower($dir):$dir;
            if(isset($visited[$identity]))return;$visited[$identity]=true;
            try{$iterator=new \FilesystemIterator($dir,\FilesystemIterator::SKIP_DOTS);}catch(\UnexpectedValueException){$truncated=true;return;}
            foreach($iterator as $entry){
                if(++$count>$budget){$truncated=true;return;}
                $name=$entry->getFilename();
                if(str_starts_with($name,'.')||str_contains($name,'..')||preg_match('~[\\\\/\x00-\x1f\x7f:%]~',$name))continue;
                $path=$dir.DIRECTORY_SEPARATOR.$name;$resolved=realpath($path);
                if($resolved===false)continue;
                $inside=rtrim($base,'/\\').DIRECTORY_SEPARATOR;
                if(PHP_OS_FAMILY==='Windows' ? strncasecmp($resolved,$inside,strlen($inside))!==0 : !str_starts_with($resolved,$inside))continue;
                $relative=$prefix.$name;
                if(is_dir($path)){
                    if(!is_link($path)){if($depth<self::MAX_DEPTH)$walk($resolved,$relative.'/',$depth+1);else $truncated=true;}
                    if($count>$budget)return;continue;
                }
                if(is_file($path)&&is_readable($path)&&preg_match('~\.(mp3|mp4|opus)$~iD',$name))$files[]=$relative;
            }
        };
        $walk($base,'',0);sort($files,SORT_STRING);return ['files'=>$files,'truncated'=>$truncated];
    }

    public static function catalog(string $root): array
    {
        return self::catalogFromFiles(self::files($root));
    }

    public static function catalogFromFiles(array $files): array
    {
        $map=[];
        foreach ($files as $name) {
            $key=preg_match('~^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$~D',$name) ? $name : 'media-'.substr(hash('sha256',$name),0,32);
            if (isset($map[$key]) && $map[$key]!==$name) { $map[$key]=null; continue; }
            $map[$key]=$name;
        }
        $aliases=[];
        foreach ($map as $name) {
            if ($name===null) continue;
            // Existing top-level aliases remain stable when files are added in subfolders.
            if(str_contains($name,'/'))continue;
            $stem=pathinfo($name,PATHINFO_FILENAME);
            if (!preg_match('~^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$~D',$stem)) continue;
            if (isset($map[$stem])) continue;
            $aliases[$stem]=array_key_exists($stem,$aliases) ? null : $name;
        }
        return $map+$aliases;
    }

    public static function open(string $root, array $map, string $key, string $method, ?string $range): array
    {
        if (!in_array($method, ['GET', 'HEAD'], true)) return ['status'=>405,'headers'=>['Allow'=>'GET, HEAD']];
        if (!preg_match('~^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$~D', $key) || str_contains($key, '..')) return ['status'=>400,'headers'=>[]];
        if (!preg_match('~^(?:/|[A-Za-z]:[\\\\/])~', $root) || !($base = realpath($root)) || !is_dir($base)) return ['status'=>503,'headers'=>[]];
        // Keep saved legacy aliases, but new installations require only a folder.
        $truncated=false;
        if(array_key_exists($key,$map))$catalog=$map;
        else{$scan=self::scan($base);$catalog=self::catalogFromFiles($scan['files']);$truncated=$scan['truncated'];}
        if (!array_key_exists($key, $catalog)) return ['status'=>$truncated?503:404,'headers'=>[]];
        $relative = $catalog[$key];
        if ($relative === null) return ['status'=>409,'headers'=>[]];
        if (!is_string($relative) || preg_match('~[\\\\\x00-\x1f\x7f:%]~',$relative)
            || str_starts_with($relative,'/') || str_starts_with($relative,'.')
            || !preg_match('~\.(mp3|mp4|opus)$~iD', $relative, $match)
            || str_contains($relative, '..') || str_contains($relative, '//') || substr_count($relative,'/')>self::MAX_DEPTH) return ['status'=>403,'headers'=>[]];
        $file = realpath($base . DIRECTORY_SEPARATOR . $relative);
        if ($file === false) return ['status'=>404,'headers'=>[]];
        $prefix = rtrim($base, '/\\') . DIRECTORY_SEPARATOR;
        $inside = PHP_OS_FAMILY === 'Windows' ? strncasecmp($file, $prefix, strlen($prefix)) === 0 : str_starts_with($file, $prefix);
        if (!$inside || !is_file($file) || !is_readable($file)) return ['status'=>403,'headers'=>[]];
        $handle = @fopen($file, 'rb');
        if ($handle === false) return ['status'=>403,'headers'=>[]];
        $stat = fstat($handle); clearstatcache(true, $file); $named = @stat($file);
        if (PHP_INT_SIZE < 8 || realpath($file) !== $file || realpath($root) !== $base
            || !$stat || !$named || $stat['dev'] !== $named['dev'] || $stat['ino'] !== $named['ino'] || $stat['size'] !== $named['size']) {
            fclose($handle); return ['status'=>403,'headers'=>[]];
        }
        $size=$stat['size']; $start=0; $end=$size-1; $status=200;
        $headers=['Accept-Ranges'=>'bytes','Content-Type'=>['mp3'=>'audio/mpeg','mp4'=>'video/mp4','opus'=>'audio/ogg; codecs=opus'][strtolower($match[1])],
            'X-Content-Type-Options'=>'nosniff','Cache-Control'=>'no-store',
            'Content-Disposition'=>"inline; filename=\"media.".strtolower($match[1])."\"; filename*=UTF-8''".rawurlencode(basename($relative))];
        if ($method === 'GET' && $range !== null) {
            $valid=preg_match('~^bytes=([0-9]*)-([0-9]*)$~D', $range, $m) && ($m[1] !== '' || $m[2] !== '');
            // Reject overflowing input before integer conversion.
            if ($valid) foreach ([$m[1],$m[2]] as $n) if (strlen($n)>18) $valid=false;
            if ($valid && $size>0) {
                if ($m[1]==='') { $suffix=(int)$m[2]; $valid=$suffix>0; $start=max(0,$size-$suffix); }
                else { $start=(int)$m[1]; if ($m[2]!=='') $end=min($end,(int)$m[2]); }
                $valid=$valid && $start<$size && $end >= $start;
            } else $valid=false;
            if (!$valid) { fclose($handle); return ['status'=>416,'headers'=>$headers+['Content-Range'=>'bytes */'.$size,'Content-Length'=>'0']]; }
            $status=206; $headers['Content-Range']="bytes $start-$end/$size";
        }
        $length=max(0,$end-$start+1); $headers['Content-Length']=(string)$length;
        return ['status'=>$status,'headers'=>$headers,'handle'=>$handle,'start'=>$start,'length'=>$method==='HEAD'?0:$length];
    }

    public static function emitBody(array $response): void
    {
        if (!isset($response['handle'])) return;
        $h=$response['handle'];
        try {
            if (fseek($h,$response['start']) !== 0) return;
            $left=$response['length'];
            while ($left>0 && !connection_aborted()) {
                $chunk=fread($h,min(65536,$left)); if ($chunk===false || $chunk==='') break;
                echo $chunk; $left-=strlen($chunk);
            }
        } finally { fclose($h); }
    }
}
