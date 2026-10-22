#!/usr/bin/env bash
# usage: bash evaluator_test.sh <batch_no>
N=$1
BASE=http://10.10.10.17:8082/api
EV=9; A1=6; A2=10; A3=11

tok() { php artisan tinker --execute="echo App\Models\User::find($1)->createToken('flow-test')->plainTextToken;" | tail -n 1; }

declare -A T
for u in $EV $A1 $A2 $A3; do T[$u]=$(tok $u); done

sign() {
  curl -s -o /dev/null -w "%{http_code}" -X POST $BASE/approvals/approve \
    -H "Authorization: Bearer ${T[$1]}" -H "Accept: application/json" -F "batch_no=$N"
}
sees() {
  curl -s "$BASE/approvals?status=$2" -H "Authorization: Bearer ${T[$1]}" -H "Accept: application/json" \
    | grep -q "\"batch_no\":$N" && echo yes || echo no
}
check() {
  if [ "$2" = "$3" ]; then echo "PASS  $1 (got $3)"; else echo "FAIL  $1 (expected $2, got $3)"; fi
}

check "Approval 1 blocked before evaluator"   403 "$(sign $A1)"
check "Approval 3 blocked before evaluator"   403 "$(sign $A3)"
check "evaluator $EV sees it pending"         yes "$(sees $EV pending)"
check "user $A1 does not see it yet"          no  "$(sees $A1 pending)"
check "evaluator signs"                       200 "$(sign $EV)"
check "evaluator double click"                403 "$(sign $EV)"
check "evaluator pending is now empty"        no  "$(sees $EV pending)"
check "user $A1 now sees it pending"          yes "$(sees $A1 pending)"
check "Approval 2 still blocked"              403 "$(sign $A2)"
check "Approval 1 signs"                      200 "$(sign $A1)"
check "Approval 2 signs"                      200 "$(sign $A2)"
check "Approval 3 signs"                      200 "$(sign $A3)"
check "evaluator history keeps it"            yes "$(sees $EV history)"
check "user $A3 history has it"               yes "$(sees $A3 history)"

php artisan tinker --execute="Laravel\Sanctum\PersonalAccessToken::where('name','flow-test')->delete();" > /dev/null
echo "tokens cleaned up"
