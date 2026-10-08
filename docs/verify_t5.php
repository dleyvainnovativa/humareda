<?php
/**
 * Standalone verification of T5 logic (no DB / no Laravel).
 * Covers: own-cover discount math (the modify correctness crux), the modify
 * availability decision using that discount, ReservationSelector, and
 * mapModifyOutcome branching.
 * Run:  php docs/verify_t5.php
 */
namespace Illuminate\Support {
    class Carbon {
        public static function parse($d){ return new self(); }
        public function locale($l){ return $this; }
        public function translatedFormat($f){ return 'FECHA'; }
    }
}
namespace App\Models {
    class Conversation {
        const STATE_IDLE='idle'; const STATE_BOOKING='booking'; const STATE_CONFIRMING='confirming';
        const STATE_CANCELLING='cancelling'; const STATE_MODIFYING='modifying'; const STATE_HUMAN='human';
    }
}
namespace {

// T3 pure deps + T5 files
require_once __DIR__.'/../app/Services/Availability/SlotCalculator.php';       // provided by T3
require_once __DIR__.'/../app/Services/Availability/AvailabilityResult.php';   // provided by T3
require_once __DIR__.'/../app/Services/Availability/AvailabilityService.php';  // provided by T3
require_once __DIR__.'/../app/Services/Availability/BookingService.php';       // T5 (has discountOwn)
require_once __DIR__.'/../app/Services/Bot/Replies.php';
require_once __DIR__.'/../app/Services/Reservations/ReservationSelector.php';

use App\Services\Availability\AvailabilityResult;
use App\Services\Availability\AvailabilityService as AV;
use App\Services\Availability\BookingService as BS;
use App\Services\Reservations\ReservationSelector as RS;

$fail = 0;
function check($l,$g,$e){ global $fail; $ok=$g===$e; if(!$ok)$fail++; printf("[%s] %s\n",$ok?'PASS':'FAIL',$l); if(!$ok)printf("       got=%s expected=%s\n",var_export($g,true),var_export($e,true)); }
function truthy($l,$g){ global $fail; $ok=(bool)$g; if(!$ok)$fail++; printf("[%s] %s\n",$ok?'PASS':'FAIL',$l); }

$cfg = ['is_open'=>true,'open_time'=>'13:00','last_seating'=>'20:00','slot_minutes'=>30,'turn_minutes'=>120,'max_covers_per_slot'=>40,'auto_confirm_max'=>8];
$span = ['20:00','20:30','21:00','21:30'];

echo "== discountOwn ==\n";
check('subtracts own covers', BS::discountOwn(['20:00'=>38,'20:30'=>38],['20:00','20:30'],4), ['20:00'=>34,'20:30'=>34]);
check('floors at 0', BS::discountOwn(['20:00'=>2],['20:00'],4), ['20:00'=>0]);
check('leaves untouched slots', BS::discountOwn(['20:00'=>38,'22:00'=>10],['20:00'],4), ['20:00'=>34,'22:00'=>10]);

echo "\n== modify decision (own covers discounted) ==\n";
// Reservation is party 4 at 20:00; every spanned slot has 38 covers (incl. our 4).
$raw = array_fill_keys($span, 38);
$disc = BS::discountOwn($raw, $span, 4);              // -> 34 each
$r = AV::decide($cfg, $disc, 6, '20:00', '2026-09-26'); // grow 4 -> 6
check('grow 4->6 fits after discount (34+6=40)', $r->status, 'available');

$disc2 = BS::discountOwn(array_fill_keys($span, 40), $span, 4); // 36 each
$r2 = AV::decide($cfg, $disc2, 6, '20:00', '2026-09-26');       // 36+6=42 > 40
check('grow to 6 blocked when 36+6>40', $r2->status, 'full');

$r3 = AV::decide($cfg, $disc, 10, '20:00', '2026-09-26');       // 10 > auto 8
check('grow to 10 -> needs_human', $r3->status, 'needs_human');

echo "\n== ReservationSelector ==\n";
$c = [
    ['id'=>10,'date'=>'2026-09-25','time'=>'20:00','party'=>2,'name'=>'A'],
    ['id'=>11,'date'=>'2026-09-26','time'=>'21:00','party'=>4,'name'=>'B'],
];
check('by number 2 -> id 11', RS::pick($c,'el 2',[]), 11);
check('by date -> id 10', RS::pick($c,'ese dia',['date'=>'2026-09-25']), 10);
check('by time -> id 11', RS::pick($c,'',['time'=>'21:00']), 11);
check('ambiguous -> null', RS::pick($c,'no se',[]), null);
check('number out of range -> null', RS::pick($c,'opcion 5',[]), null);

echo "\n== mapModifyOutcome ==\n";
$mk = fn($s,$alts=[]) => new AvailabilityResult($s,'2026-09-26','20:00',6,$alts,8);
require_once __DIR__.'/../app/Services/Bot/ConversationEngine.php'; // needs T4 SlotFiller/Affirmation present in the project
use App\Services\Bot\ConversationEngine as CE;

$o = CE::mapModifyOutcome($mk('available'),'es','Ana');
check('available -> confirm_mod', $o['stage'], 'confirm_mod');
check('available -> not human', $o['human'], false);
$o = CE::mapModifyOutcome($mk('full',['19:00','19:30']),'es','Ana');
check('full -> collect', $o['stage'], 'collect');
check('full -> clears time', $o['clear'], ['time']);
$o = CE::mapModifyOutcome($mk('needs_human'),'es','Ana');
check('needs_human -> human', $o['human'], true);
$o = CE::mapModifyOutcome($mk('invalid'),'es','Ana');
check('invalid -> clears party_size', $o['clear'], ['party_size']);
$o = CE::mapModifyOutcome($mk('closed'),'es','Ana');
check('closed -> clears date', $o['clear'], ['date']);

echo "\n".($fail===0?"ALL T5 CHECKS PASSED\n":"T5 FAILURES: $fail\n");
exit($fail===0?0:1);
}
