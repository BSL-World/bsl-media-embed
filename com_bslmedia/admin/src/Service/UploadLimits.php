<?php
declare(strict_types=1);
namespace BSL\Component\Bslmedia\Administrator\Service;

/** PHP ini quantities use binary K/M/G; public UI sizes remain decimal MB/GB. */
final class UploadLimits
{
    public const RESERVE_BASE=65536;
    public const RESERVE_FILE=4096;
    private const SAFE_INTEGER=9007199254740991;

    public static function quantity(string $value):?int
    {
        if(!preg_match('/^([+-]?[0-9]+)\s*([KMG]?)$/iD',trim($value),$m))return null;
        if($m[1][0]==='-')return 0;
        $factor=match(strtoupper($m[2])){'K'=>1024,'M'=>1048576,'G'=>1073741824,default=>1};
        $number=(float)$m[1];
        return $number>intdiv(self::SAFE_INTEGER,$factor)?self::SAFE_INTEGER:(int)$m[1]*$factor;
    }

    public static function fromIni(int $component,array $ini):array
    {
        $upload=self::quantity((string)($ini['upload_max_filesize']??''));
        $post=self::quantity((string)($ini['post_max_size']??''));
        $valid=$upload!==null&&$post!==null&&$component>0;
        $enabled=$valid&&!in_array(strtolower((string)($ini['file_uploads']??'1')),['','0','off','false','none'],true)
            &&!in_array(strtolower((string)($ini['enable_post_data_reading']??'1')),['','0','off','false','none'],true);
        $policy=['component'=>min(self::SAFE_INTEGER,max(0,$component)),'upload'=>$upload??0,'post'=>$post??0,
            'maxFiles'=>max(0,(int)($ini['max_file_uploads']??20)),'enabled'=>$enabled,'valid'=>$valid,
            'reserveBase'=>self::RESERVE_BASE,'reserveFile'=>self::RESERVE_FILE];
        $cap=$policy['component'];$policy['limiting']='component';
        if($policy['upload']>0&&$policy['upload']<$cap){$cap=$policy['upload'];$policy['limiting']='upload';}
        if($policy['post']>0&&max(0,$policy['post']-self::RESERVE_BASE-self::RESERVE_FILE)<$cap){$cap=max(0,$policy['post']-self::RESERVE_BASE-self::RESERVE_FILE);$policy['limiting']='post';}
        $policy['effectiveFile']=$enabled&&$policy['maxFiles']>0?$cap:0;
        return $policy;
    }

    public static function runtime(int $component):array
    {
        $ini=[];foreach(['upload_max_filesize','post_max_size','max_file_uploads','file_uploads','enable_post_data_reading'] as $key)$ini[$key]=(string)ini_get($key);
        return self::fromIni($component,$ini);
    }

    public static function fileIssue(string $path,string $name,array $policy):?array
    {
        if(!$policy['valid'])return ['reason'=>'config'];
        if(!$policy['enabled']||$policy['maxFiles']===0)return ['reason'=>'disabled'];
        $size=@filesize($path);
        if($size===false||$size<=0)return ['reason'=>'invalid','name'=>$name];
        foreach(['component','upload'] as $kind)if($policy[$kind]>0&&$size>$policy[$kind])return ['reason'=>$kind,'name'=>$name,'actual'=>$size,'limit'=>$policy[$kind]];
        return null;
    }

    public static function exceedsPost(array $server,int $limit):bool
    {
        if(($server['REQUEST_METHOD']??'')!=='POST'||$limit<=0)return false;
        $raw=(string)($server['CONTENT_LENGTH']??'');
        if(!preg_match('/^[0-9]+$/D',$raw))return false;
        $raw=ltrim($raw,'0');$bound=(string)$limit;
        return strlen($raw)>strlen($bound)||(strlen($raw)===strlen($bound)&&strcmp($raw,$bound)>0);
    }

    /** Paths must first pass uploaded-file provenance in the controller. */
    public static function aggregateIssue(array $paths,array $policy):?array
    {
        if($policy['post']<=0||$paths===[])return null;
        $total=self::RESERVE_BASE+count($paths)*self::RESERVE_FILE;
        foreach($paths as $path){$size=@filesize($path);if($size!==false&&$size>0)$total+=$size;}
        return $total>$policy['post']?['reason'=>'post','actual'=>$total,'limit'=>$policy['post']]:null;
    }

    public static function discarded(array $server,array $post,array $files,?int $limit=null):bool
    {
        return $post===[]&&$files===[]&&self::exceedsPost($server,$limit??(self::quantity((string)ini_get('post_max_size'))??0));
    }

    public static function format(int $bytes):string
    {
        $scale=$bytes>=1000000000?1000000000:1000000;
        return number_format($bytes/$scale,2,'.',' ').($scale===1000000000?' GB':' MB');
    }

    public static function exact(int $bytes):string
    {
        return self::format($bytes).' ('.number_format($bytes,0,'.',' ').' B)';
    }
}
