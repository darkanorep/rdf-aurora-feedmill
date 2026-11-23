#!/usr/bin/env bash
# usage: bash approval_test.sh <batch_no>
N=$1
BASE=http://10.10.10.17:8082/api
A1=6; A2=10; A3=11   # approver user ids, in order

tok() { php artisan tinker --execute="echo App\Models\User::find($1)->createToken('flow-test')->plainTextToken;" | tail -n 1; }

declare -A T
for u in $A1 $A2 $A3; do T[$u]=$(tok $u); done

sign() {
  curl -s -o /dev/null -w "%{http_code}" -X POST $BASE/approvals/approve \
    -H "Authorization: Bearer ${T[$1]}" -H "Accept: application/json" -F "batch_no=$N"
}

# prints yes/no: does this user's pending list contain the batch?
sees() {
  curl -s "$BASE/approvals?status=$2" -H "Authorization: Bearer ${T[$1]}" -H "Accept: application/json" \
    | grep -q "\"batch_no\":$N" && echo yes || echo no
}

check() {  # label expected actual
  if [ "$2" = "$3" ]; then echo "PASS  $1 (got $3)"; else echo "FAIL  $1 (expected $2, got $3)"; fi
}

check "Approval 2 signs too early"          403 "$(sign $A2)"
check "Approval 3 signs too early"          403 "$(sign $A3)"
check "user $A1 sees it pending"            yes "$(sees $A1 pending)"
check "user $A2 does not see it yet"        no  "$(sees $A2 pending)"
check "Approval 1 signs"                    200 "$(sign $A1)"
check "Approval 1 double click"             403 "$(sign $A1)"
check "user $A1 pending is now empty"       no  "$(sees $A1 pending)"
check "user $A2 now sees it pending"        yes "$(sees $A2 pending)"
check "Approval 3 still blocked"            403 "$(sign $A3)"
check "Approval 2 signs"                    200 "$(sign $A2)"
check "user $A3 now sees it pending"        yes "$(sees $A3 pending)"
check "Approval 3 signs"                    200 "$(sign $A3)"
check "user $A1 history keeps it"           yes "$(sees $A1 history)"
check "user $A3 history has it"             yes "$(sees $A3 history)"

php artisan tinker --execute="Laravel\Sanctum\PersonalAccessToken::where('name','flow-test')->delete();" > /dev/null
echo "tokens cleaned up"
