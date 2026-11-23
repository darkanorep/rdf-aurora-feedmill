#!/usr/bin/env bash
# usage: SECTION=<name> bash chain_test.sh <batch_no> <user1> [user2] [user3] ...
N=$1; shift
USERS=("$@")
BASE=http://10.10.10.17:8082/api

tok() { php artisan tinker --execute="echo App\Models\User::find($1)->createToken('flow-test')->plainTextToken;" | tail -n 1; }

declare -A T
for u in "${USERS[@]}"; do [ -z "${T[$u]}" ] && T[$u]=$(tok $u); done

sign()  { curl -s -o /dev/null -w "%{http_code}" -X POST $BASE/approvals/approve -H "Authorization: Bearer ${T[$1]}" -H "Accept: application/json" -F "batch_no=$N"; }
sees()  { curl -s "$BASE/approvals?status=$2${SECTION:+&section=$SECTION}" -H "Authorization: Bearer ${T[$1]}" -H "Accept: application/json" | grep -q "\"batch_no\":$N" && echo yes || echo no; }
check() { if [ "$2" = "$3" ]; then echo "PASS  $1 (got $3)"; else echo "FAIL  $1 (expected $2, got $3)"; fi; }

# 1. Before anyone signs: only the first user may act.
for i in "${!USERS[@]}"; do
  u=${USERS[$i]}
  if [ "$i" -gt 0 ]; then
    check "user $u blocked before their turn"      403 "$(sign $u)"
    check "user $u does not see it yet"            no  "$(sees $u pending)"
  else
    check "user $u sees it pending"                yes "$(sees $u pending)"
  fi
done

# 2. Sign in order; the next user must appear only after the previous one signs.
for i in "${!USERS[@]}"; do
  u=${USERS[$i]}
  [ "$i" -gt 0 ] && check "user $u now sees it pending" yes "$(sees $u pending)"
  check "user $u signs"                            200 "$(sign $u)"
  check "user $u double click"                     403 "$(sign $u)"
  check "user $u history keeps it"                 yes "$(sees $u history)"
done

php artisan tinker --execute="Laravel\Sanctum\PersonalAccessToken::where('name','flow-test')->delete();" > /dev/null
echo "tokens cleaned up"
