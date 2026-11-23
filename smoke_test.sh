#!/usr/bin/env bash
BASE=http://10.10.10.17:8082/api
AUDITOR=1

tok() { php artisan tinker --execute="echo App\Models\User::find($1)->createToken('flow-test')->plainTextToken;" | tail -n 1; }

echo "database: $(php artisan tinker --execute='echo DB::connection()->getDatabaseName();' | tail -n 1)"
AT=$(php artisan tinker --execute="echo App\Models\User::find($AUDITOR)->createToken('smoke-auditor')->plainTextToken;" | tail -n 1)

# prints "<checklist id> <unit id>" for a section
pick() {
  php artisan tinker --execute="\$c=App\Models\Checklist::where('section_id',$1)->whereNull('deleted_at')->first(); \$u=\$c->unit_ids; \$u=is_string(\$u)?json_decode(\$u,true):\$u; echo \$c->id.' '.(collect(\$u)->first() ?? App\Models\Unit::value('id'));" | tail -n 1
}

# submit <checklist> <unit or -> <evaluator 1/0>  -> prints batch_no or ERR
submit() {
  local args=(-F "checklist_id=$1" -F "is_completed=1" -F "start_at=$(date +%F)" -F "good_points=smoke" -F "remarks=smoke" -F "temporal_audit=Spot/Ongoing")
  [ "$2" != "-" ] && args+=(-F "unit_id=$2")
  [ "$3" = "1" ] && args+=(-F "requires_evaluator=1")
  curl -s -X POST $BASE/responses -H "Authorization: Bearer $AT" -H "Accept: application/json" "${args[@]}" \
    -F 'response[0]={"checklist":"T","item":"T","sub_item":"T","score":100,"remarks":""}' \
    | python3 -c "import sys,json; d=json.load(sys.stdin); print((d.get('data') or {}).get('batch_no') or 'ERR '+str(d.get('message')))"
}

run() {  # label, section name, section id, unit flag, evaluator flag, users...
  local label=$1 name=$2 sid=$3 withunit=$4 ev=$5; shift 5
  read -r CL UN <<< "$(pick $sid)"
  [ "$withunit" = "0" ] && UN="-"
  local B; B=$(submit $CL $UN $ev)
  echo; echo "== $label (checklist $CL, batch $B)"
  case "$B" in ERR*) echo "   submit failed: $B"; return;; esac
  SECTION=$name bash chain_test.sh $B "$@"
}

run "COBS with evaluator" cobs  1 1 1 9 6 10 11
run "PESTS"               pests 3 0 0 6 11
run "BIRDS"               birds 2 0 0 6 11

# signature image on a fresh cobs batch (no evaluator): user 6 signs step 1 with an image
echo; echo "== SIGNATURE IMAGE"
echo 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' | base64 -d > /tmp/sig.png
read -r CL UN <<< "$(pick 1)"
B=$(submit $CL $UN 0)
echo "batch $B"

case "$B" in
  ERR*)
    echo "   submit failed: $B"
    ;;
  *)
    T6=$(tok 6)
    curl -s -o /dev/null -w "user 6 signs with image -> HTTP %{http_code}\n" -X POST $BASE/approvals/approve \
      -H "Authorization: Bearer $T6" -H "Accept: application/json" \
      -F "batch_no=$B" -F 'approve_image[0][0]=@/tmp/sig.png;type=image/png'
    php artisan tinker --execute="App\Models\SubmissionStepAssignee::whereHas('step.submission', fn(\$q)=>\$q->where('legacy_batch_no',$B))->get()->each(fn(\$a)=>print('user '.\$a->user_id.' decision='.\$a->decision.' image='.(\$a->signature_image ?: 'NONE').PHP_EOL));"
    ;;
esac

php artisan tinker --execute="Laravel\Sanctum\PersonalAccessToken::whereIn('name', ['flow-test','smoke-auditor'])->delete();" > /dev/null
echo; echo "tokens cleaned up"
