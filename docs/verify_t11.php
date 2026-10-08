<?php
/**
 * Verifies the pure T11 slot-filling logic: the 5-field order, merge/correction,
 * completeness. No DB / Laravel. Run: php docs/verify_t11.php
 */

// Stub the Replies methods SlotFiller::promptFor calls, so it loads standalone.
namespace App\Services\Bot {
    class Replies {
        static function askName($l){return 'name';} static function askParty($l){return 'party';}
        static function askTime($l){return 'time';} static function askDate($l){return 'date';}
        static function askReference($l){return 'ref';} static function fallback($l){return 'fallback';}
    }
}

namespace {
    require __DIR__.'/../app/Services/Bot/SlotFiller.php';
    use App\Services\Bot\SlotFiller as SF;

    $fail = 0;
    function check($l,$g,$e){ global $fail; $ok=$g===$e; if(!$ok)$fail++; printf("[%s] %s\n",$ok?'PASS':'FAIL',$l); if(!$ok)printf("       got=%s exp=%s\n",var_export($g,true),var_export($e,true)); }

    echo "== SlotFiller (5 fields) ==\n";
    check('fields order', SF::FIELDS, ['name','party_size','time','date','reference_contact']);
    check('missing empty -> name', SF::missing(SF::empty()), 'name');
    check('after name -> party_size', SF::missing(['name'=>'Ana']), 'party_size');
    check('after name+party -> time', SF::missing(['name'=>'Ana','party_size'=>4]), 'time');
    check('after 3 -> date', SF::missing(['name'=>'Ana','party_size'=>4,'time'=>'20:00']), 'date');
    check('after 4 -> reference_contact', SF::missing(['name'=>'Ana','party_size'=>4,'time'=>'20:00','date'=>'2026-10-10']), 'reference_contact');
    check('complete when all 5', SF::complete(['name'=>'Ana','party_size'=>4,'time'=>'20:00','date'=>'2026-10-10','reference_contact'=>'a@b.com']), true);
    check('merge correction wins', SF::merge(['party_size'=>4], ['party_size'=>6])['party_size'], 6);
    check('merge null keeps old', SF::merge(['name'=>'Ana'], ['name'=>null])['name'], 'Ana');
    check('merge adds reference', SF::merge(['name'=>'Ana'], ['reference_contact'=>'55-1234'])['reference_contact'], '55-1234');
    check('promptFor reference', SF::promptFor('reference_contact','es'), 'ref');

    echo "\n".($fail===0?"ALL T11 CHECKS PASSED\n":"T11 FAILURES: $fail\n");
    exit($fail===0?0:1);
}
