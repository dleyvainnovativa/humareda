<?php
/**
 * Standalone verification of the T4 decision logic (no DB / no OpenAI / no
 * Laravel). Stubs the few framework touchpoints so the pure branching runs.
 * Run:  php docs/verify_t4.php
 */

// --- Minimal stubs so the pure logic can load without Laravel --------------
namespace Illuminate\Support {
    class Carbon {
        public static function parse($d) { return new self(); }
        public function locale($l) { return $this; }
        public function translatedFormat($f) { return 'FECHA'; }
    }
}
namespace App\Models {
    class Conversation {
        const STATE_IDLE='idle'; const STATE_BOOKING='booking';
        const STATE_CONFIRMING='confirming'; const STATE_HUMAN='human';
    }
}

namespace {

require __DIR__.'/../app/Services/Availability/AvailabilityResult.php'; // provided by T3
require __DIR__.'/../app/Services/Bot/Replies.php';
require __DIR__.'/../app/Services/Bot/SlotFiller.php';
require __DIR__.'/../app/Services/Bot/Affirmation.php';
require __DIR__.'/../app/Services/Bot/ConversationEngine.php';

use App\Services\Availability\AvailabilityResult;
use App\Services\Bot\SlotFiller;
use App\Services\Bot\Affirmation;
use App\Services\Bot\ConversationEngine as CE;

$fail = 0;
function check($label, $got, $expected) {
    global $fail; $ok = $got === $expected; if (!$ok) $fail++;
    printf("[%s] %s\n", $ok?'PASS':'FAIL', $label);
    if (!$ok) printf("       got=%s expected=%s\n", var_export($got,true), var_export($expected,true));
}
function truthy($label, $got) {
    global $fail; $ok = (bool)$got; if(!$ok)$fail++;
    printf("[%s] %s\n", $ok?'PASS':'FAIL', $label);
}

echo "== SlotFiller ==\n";
$e = SlotFiller::empty();
check('merge sets date', SlotFiller::merge($e, ['date'=>'2026-09-25'])['date'], '2026-09-25');
check('merge correction wins', SlotFiller::merge(['party_size'=>4], ['party_size'=>6])['party_size'], 6);
check('merge null keeps old', SlotFiller::merge(['date'=>'2026-09-25'], ['date'=>null])['date'], '2026-09-25');
check('missing empty -> date', SlotFiller::missing($e), 'date');
check('missing after date -> party_size', SlotFiller::missing(['date'=>'x']), 'party_size');
check('missing after date+party -> time', SlotFiller::missing(['date'=>'x','party_size'=>4]), 'time');
check('missing after 3 -> name', SlotFiller::missing(['date'=>'x','party_size'=>4,'time'=>'20:00']), 'name');
check('complete', SlotFiller::complete(['date'=>'x','party_size'=>4,'time'=>'20:00','name'=>'Ana']), true);

echo "\n== Affirmation ==\n";
truthy("yes: 'sí'", Affirmation::isYes('sí'));
truthy("yes: 'si confirmo'", Affirmation::isYes('si confirmo'));
truthy("yes: 'ok, va'", Affirmation::isYes('ok, va'));
truthy("yes: 'yes'", Affirmation::isYes('yes'));
check("not-yes: 'no'", Affirmation::isYes('no'), false);
truthy("no: 'no'", Affirmation::isNo('no'));
truthy("no: 'mejor cambio'", Affirmation::isNo('mejor cambio'));
check("not-no: 'sí'", Affirmation::isNo('sí'), false);
truthy("human: 'quiero hablar con alguien'", Affirmation::wantsHuman('quiero hablar con alguien'));
truthy("human: 'talk to a human'", Affirmation::wantsHuman('can I talk to a human'));
truthy("human: 'necesito un asesor'", Affirmation::wantsHuman('necesito un asesor'));
check("not-human: 'quiero reservar'", Affirmation::wantsHuman('quiero reservar'), false);

echo "\n== mapAvailabilityOutcome ==\n";
$mk = fn($status,$alts=[]) => new AvailabilityResult($status,'2026-09-25','20:00',4,$alts,8);

$o = CE::mapAvailabilityOutcome($mk('available'), 'es', 'Ana');
check('available -> confirming', $o['state'], 'confirming');
check('available -> no clears', $o['clear'], []);
truthy('available reply mentions name Ana', str_contains($o['reply'],'Ana'));

$o = CE::mapAvailabilityOutcome($mk('full', ['19:00','19:30','21:30']), 'es', 'Ana');
check('full -> booking', $o['state'], 'booking');
check('full -> clears time', $o['clear'], ['time']);
truthy('full reply lists alternatives', str_contains($o['reply'],'19:00'));

$o = CE::mapAvailabilityOutcome($mk('full', []), 'es', 'Ana');
truthy('full no-alts -> noAlternatives copy', str_contains(mb_strtolower($o['reply']),'llenos'));

$o = CE::mapAvailabilityOutcome($mk('full', ['19:00']), 'es', 'Ana', true);
truthy('full raceOnFull -> race copy', str_contains(mb_strtolower($o['reply']),'se acaba de llenar'));

$o = CE::mapAvailabilityOutcome($mk('outside_hours', ['19:30']), 'es', 'Ana');
check('outside_hours -> booking', $o['state'], 'booking');
check('outside_hours -> clears time', $o['clear'], ['time']);

$o = CE::mapAvailabilityOutcome($mk('closed'), 'es', 'Ana');
check('closed -> booking', $o['state'], 'booking');
check('closed -> clears date', $o['clear'], ['date']);

$o = CE::mapAvailabilityOutcome($mk('needs_human'), 'es', 'Ana');
check('needs_human -> human', $o['state'], 'human');

$o = CE::mapAvailabilityOutcome($mk('invalid'), 'es', 'Ana');
check('invalid -> booking', $o['state'], 'booking');
check('invalid -> clears party_size', $o['clear'], ['party_size']);

// English still routes identically
$o = CE::mapAvailabilityOutcome($mk('available'), 'en', 'John');
check('EN available -> confirming', $o['state'], 'confirming');
truthy('EN reply is English', str_contains($o['reply'],'confirm'));

echo "\n".($fail===0 ? "ALL T4 CHECKS PASSED\n" : "T4 FAILURES: $fail\n");
exit($fail===0?0:1);

}
