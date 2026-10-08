<?php
/**
 * Standalone verification of the pure availability math (no DB / no Laravel).
 * Run:  php docs/verify_t3.php
 * Exits non-zero if any assertion fails.
 */
require __DIR__.'/../app/Services/Availability/SlotCalculator.php';
require __DIR__.'/../app/Services/Availability/AvailabilityResult.php';
require __DIR__.'/../app/Services/Availability/AvailabilityService.php';

use App\Services\Availability\SlotCalculator as SC;
use App\Services\Availability\AvailabilityService as AV;

$fail = 0;
function check($label, $got, $expected) {
    global $fail;
    $ok = $got === $expected;
    if (!$ok) $fail++;
    $g = is_array($got) ? json_encode($got) : var_export($got, true);
    $e = is_array($expected) ? json_encode($expected) : var_export($expected, true);
    printf("[%s] %s\n", $ok ? 'PASS' : 'FAIL', $label);
    if (!$ok) printf("       got=%s expected=%s\n", $g, $e);
}

echo "== SlotCalculator ==\n";
check('snap 20:15 /30 -> 20:30', SC::snapToGrid('20:15', 30), '20:30');
check('snap 20:20 /30 -> 20:30', SC::snapToGrid('20:20', 30), '20:30');
check('snap 20:10 /30 -> 20:00', SC::snapToGrid('20:10', 30), '20:00');
check('spanned 20:00 turn120 slot30', SC::spannedSlots('20:00',120,30), ['20:00','20:30','21:00','21:30']);
check('spanned 20:00 turn90 slot30 (ceil)', SC::spannedSlots('20:00',90,30), ['20:00','20:30','21:00']);
check('daySlots 13:00-20:00 /30 count', count(SC::daySlots('13:00','20:00',30)), 15);
$ds = SC::daySlots('13:00','20:00',30);
check('daySlots first/last', [$ds[0], $ds[count($ds)-1]], ['13:00','20:00']);

// Weeknight placeholder config
$cfg = [
    'is_open'=>true,'open_time'=>'13:00','last_seating'=>'20:00',
    'slot_minutes'=>30,'turn_minutes'=>120,'max_covers_per_slot'=>40,'auto_confirm_max'=>8,
];
$D = '2026-09-25';

echo "\n== decide() ==\n";
check('empty slot, party 4 -> available',
    AV::decide($cfg, [], 4, '20:00', $D)->status, 'available');
check('available snaps time to 20:00',
    AV::decide($cfg, [], 4, '20:07', $D)->time, '20:00');

// THE oversell case: 20:00 has room but a later spanned slot (20:30) is nearly full.
$occ = ['20:00'=>0,'20:30'=>38,'21:00'=>0,'21:30'=>0];
check('multi-slot block: 20:30 at 38 + party4 > 40 -> full',
    AV::decide($cfg, $occ, 4, '20:00', $D)->status, 'full');
check('same slots, party 2 fits (38+2=40) -> available',
    AV::decide($cfg, $occ, 2, '20:00', $D)->status, 'available');

check('after last seating (21:00) -> outside_hours',
    AV::decide($cfg, [], 4, '21:00', $D)->status, 'outside_hours');
check('before open (12:00) -> outside_hours',
    AV::decide($cfg, [], 4, '12:00', $D)->status, 'outside_hours');
check('party 12 > auto_confirm_max 8 -> needs_human',
    AV::decide($cfg, [], 12, '19:00', $D)->status, 'needs_human');
check('party 0 -> invalid',
    AV::decide($cfg, [], 0, '19:00', $D)->status, 'invalid');
check('closed day -> closed',
    AV::decide(array_merge($cfg,['is_open'=>false]), [], 4, '19:00', $D)->status, 'closed');

echo "\n== fits() boundary ==\n";
check('36 + 4 = 40 <= 40 fits', AV::fits(['20:00'=>36], ['20:00'], 4, 40), true);
check('37 + 4 = 41 > 40 no',    AV::fits(['20:00'=>37], ['20:00'], 4, 40), false);

echo "\n== suggest() ==\n";
// Make 20:00 start unbookable by saturating 21:00 (spanned by a 20:00 start),
// leave the rest open; expect nearest-3 to 20:00 that still fit party 4.
$day = ['21:00'=>40];  // blocks any start whose span includes 21:00: 19:30,20:00 (and 21:00 itself is past last seating)
$alts = AV::suggest($cfg, $day, 4, '20:00');
check('suggest returns 3', count($alts), 3);
check('suggest excludes blocked 20:00', in_array('20:00',$alts,true), false);
check('suggest chronological', $alts === array_values($alts) && $alts[0] < $alts[1] && $alts[1] < $alts[2], true);
check('suggest nearest to 20:00 (has 19:00)', in_array('19:00',$alts,true), true);

// minStart filter (today, past slots excluded)
$alts2 = AV::suggest($cfg, [], 4, '13:00', '18:00');
check('minStart 18:00 excludes 13:00', in_array('13:00',$alts2,true), false);
check('minStart 18:00 keeps >= 18:00', min(array_map([SC::class,'timeToMinutes'],$alts2)) >= SC::timeToMinutes('18:00'), true);

echo "\n".($fail === 0 ? "ALL T3 CHECKS PASSED\n" : "T3 FAILURES: $fail\n");
exit($fail === 0 ? 0 : 1);
