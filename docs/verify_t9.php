<?php
/**
 * Verifies the pure T9 logic: reminder time computation (native DateTime, with
 * timezone + future guard) and CONFIRMO/CANCELO classification. No DB / Laravel.
 * Run: php docs/verify_t9.php
 */
// Stubs so the files load without the framework.
namespace Illuminate\Support { class Carbon {} }

namespace {
require __DIR__.'/../app/Services/Reminders/ReminderScheduler.php';
require __DIR__.'/../app/Services/Reminders/ReminderReplyHandler.php'; // for classify() (pure static)

use App\Services\Reminders\ReminderScheduler as RS;

$fail = 0;
function check($l,$g,$e){ global $fail; $ok=$g===$e; if(!$ok)$fail++; printf("[%s] %s\n",$ok?'PASS':'FAIL',$l); if(!$ok)printf("       got=%s exp=%s\n",var_export($g,true),var_export($e,true)); }

$tz = 'America/Mexico_City';

echo "== computeAt ==\n";
// fixed_time: reminder at 11:00 on the reservation date, now is earlier same day -> future
check('fixed_time future', RS::computeAt('fixed_time','11:00',3,'2026-10-10','20:00','2026-10-10 09:00:00',$tz), '2026-10-10 11:00:00');
// fixed_time already passed -> null
check('fixed_time past -> null', RS::computeAt('fixed_time','11:00',3,'2026-10-10','20:00','2026-10-10 13:00:00',$tz), null);
// hours_before: 3h before a 20:00 seating = 17:00 same day
check('hours_before 3h -> 17:00', RS::computeAt('hours_before','11:00',3,'2026-10-10','20:00','2026-10-10 09:00:00',$tz), '2026-10-10 17:00:00');
// hours_before but now after that -> null
check('hours_before past -> null', RS::computeAt('hours_before','11:00',3,'2026-10-10','20:00','2026-10-10 18:00:00',$tz), null);
// future date, fixed time always scheduled
check('future date fixed', RS::computeAt('fixed_time','11:00',3,'2026-10-20','21:00','2026-10-10 09:00:00',$tz), '2026-10-20 11:00:00');

echo "\n== ReminderReplyHandler::classify ==\n";
$H = 'App\\Services\\Reminders\\ReminderReplyHandler';
check("'CONFIRMO' -> confirm", $H::classify('CONFIRMO'), 'confirm');
check("'confirmo asistencia' -> confirm", $H::classify('confirmo asistencia'), 'confirm');
check("'CANCELO' -> cancel", $H::classify('CANCELO'), 'cancel');
check("'quiero cancelar' -> cancel", $H::classify('quiero cancelar'), 'cancel');
check("'confirm' (en) -> confirm", $H::classify('confirm'), 'confirm');
check("'hola' -> null", $H::classify('hola'), null);
check("'sí gracias' -> null", $H::classify('sí gracias'), null);

echo "\n".($fail===0?"ALL T9 CHECKS PASSED\n":"T9 FAILURES: $fail\n");
exit($fail===0?0:1);
}
