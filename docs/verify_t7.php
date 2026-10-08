<?php
/**
 * Verifies the pure inbound routing (hot path). No DB / network.
 * Run: php docs/verify_t7.php
 */
require __DIR__.'/../app/Services/Bot/InboundRouter.php';
use App\Services\Bot\InboundRouter as R;

$fail = 0;
function check($l,$g,$e){ global $fail; $ok=$g===$e; if(!$ok)$fail++; printf("[%s] %s\n",$ok?'PASS':'FAIL',$l); if(!$ok)printf("       got=%s exp=%s\n",var_export($g,true),var_export($e,true)); }

check('text with body -> engine', R::decide('text','Hola',null), ['mode'=>'engine','text'=>'Hola']);
check('text empty -> please_type', R::decide('text','   ',null), ['mode'=>'please_type','text'=>null]);
check('audio w/ transcript -> engine', R::decide('audio',null,'mesa para 4'), ['mode'=>'engine','text'=>'mesa para 4']);
check('audio no transcript -> please_type_audio', R::decide('audio',null,null), ['mode'=>'please_type_audio','text'=>null]);
check('audio empty transcript -> please_type_audio', R::decide('audio',null,'   '), ['mode'=>'please_type_audio','text'=>null]);
check('image w/ caption -> engine', R::decide('image','reservar viernes',null), ['mode'=>'engine','text'=>'reservar viernes']);
check('image no caption -> please_type', R::decide('image',null,null), ['mode'=>'please_type','text'=>null]);
check('sticker -> please_type', R::decide('sticker',null,null), ['mode'=>'please_type','text'=>null]);
check('location -> please_type', R::decide('location','location',null), ['mode'=>'please_type','text'=>null]);
check('trims engine text', R::decide('text','  hey  ',null), ['mode'=>'engine','text'=>'hey']);

echo "\n".($fail===0?"ALL T7 CHECKS PASSED\n":"T7 FAILURES: $fail\n");
exit($fail===0?0:1);
