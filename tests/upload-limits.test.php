<?php
declare(strict_types=1);
require __DIR__.'/../com_bslmedia/admin/src/Service/UploadLimits.php';
use BSL\Component\Bslmedia\Administrator\Service\UploadLimits as Limits;
$count=0;
function eq($a,$b){global $count;if($a!==$b)throw new RuntimeException(var_export([$a,$b],true));$count++;}
eq(Limits::quantity('128M'),134217728);eq(Limits::quantity('160M'),167772160);eq(Limits::quantity('2G'),2147483648);
eq(Limits::quantity('0'),0);eq(Limits::quantity('-1'),0);eq(Limits::quantity('1.5M'),null);eq(Limits::quantity('bad'),null);
$policy=Limits::fromIni(1024000000,['upload_max_filesize'=>'128M','post_max_size'=>'160M','max_file_uploads'=>'20']);
eq($policy['effectiveFile'],134217728);eq($policy['limiting'],'upload');eq($policy['valid'],true);
$postBound=Limits::fromIni(1024000000,['upload_max_filesize'=>'256M','post_max_size'=>'160M']);
eq($postBound['effectiveFile'],167772160-65536-4096);eq($postBound['limiting'],'post');
eq(Limits::fromIni(100,['upload_max_filesize'=>'0','post_max_size'=>'0'])['effectiveFile'],100);
eq(Limits::fromIni(100,['upload_max_filesize'=>'128M','post_max_size'=>'160M','file_uploads'=>'0'])['effectiveFile'],0);
eq(Limits::fromIni(100,['upload_max_filesize'=>'128M','post_max_size'=>'160M','max_file_uploads'=>'0'])['effectiveFile'],0);
eq(Limits::fromIni(100,['upload_max_filesize'=>'bad','post_max_size'=>'160M'])['valid'],false);
$server=['REQUEST_METHOD'=>'POST','CONTENT_LENGTH'=>'213578537'];
eq(Limits::discarded($server,[],[],167772160),true);
eq(Limits::discarded($server,['csrf'=>'1'],[],167772160),false);
eq(Limits::discarded($server,[],['media'=>[]],167772160),false);
eq(Limits::discarded($server,[],[],0),false);
eq(Limits::exceedsPost(['REQUEST_METHOD'=>'POST','CONTENT_LENGTH'=>'167772160'],167772160),false);
eq(Limits::exceedsPost(['REQUEST_METHOD'=>'POST','CONTENT_LENGTH'=>'999999999999999999999999'],167772160),true);
eq(Limits::exceedsPost(['REQUEST_METHOD'=>'GET','CONTENT_LENGTH'=>'213578537'],167772160),false);
$path=__DIR__.'/../dist/upload-size-unit-'.getmypid();file_put_contents($path,str_repeat('a',200));
try{
 $small=Limits::fromIni(1000,['upload_max_filesize'=>'128','post_max_size'=>'100000']);
 $issue=Limits::fileIssue($path,'large.mp3',$small);eq($issue['reason'],'upload');eq($issue['actual'],200);eq($issue['limit'],128);
 $small['upload']=1000;eq(Limits::fileIssue($path,'small.mp3',$small),null);
 $small['component']=100;eq(Limits::fileIssue($path,'large.mp3',$small)['reason'],'component');
 echo "PASS $count upload-limit assertions: PHP quantities, decimal component bytes, effective limits, dropped POST and actual disk-size guards\n";
}finally{unlink($path);}
