#!/usr/bin/env bash
# Authenticated admin walkthrough: real login, real session cookie, real CSRF.
set -u

B=http://127.0.0.1:8000
JAR=/tmp/medicaremini-cookies.txt
PASS=0
FAIL=0

ok()   { PASS=$((PASS+1)); printf "  OK   %s\n" "$1"; }
bad()  { FAIL=$((FAIL+1)); printf "  FAIL %s\n" "$1"; }

# Pull the CSRF token out of a page (meta tag or hidden input).
token_from() {
  grep -oE 'name="(csrf-token|_token)" (content|value)="[^"]+"' "$1" \
    | head -1 | grep -oE '"[^"]+"$' | tr -d '"'
}

login_as() {
  local email="$1" label="$2"
  rm -f "$JAR"

  curl -s -c "$JAR" "$B/admin/login" -o /tmp/login.html
  local tok; tok=$(token_from /tmp/login.html)

  if [ -z "$tok" ]; then bad "$label: no CSRF token on the login page"; return 1; fi

  local code
  code=$(curl -s -b "$JAR" -c "$JAR" -o /tmp/after-login.html -w "%{http_code}" \
    -X POST "$B/admin/login" \
    --data-urlencode "_token=$tok" \
    --data-urlencode "email=$email" \
    --data-urlencode "password=Test#Passw0rd!2026")

  if [ "$code" = "302" ]; then ok "$label: signed in (302)"; else bad "$label: login returned $code"; return 1; fi
}

# Expect a status code for an authenticated GET.
expect() {
  local path="$1" want="$2" label="$3"
  local code
  code=$(curl -s -b "$JAR" -o /tmp/page.html -w "%{http_code}" "$B$path")
  if [ "$code" = "$want" ]; then ok "$label ($code)"; else bad "$label: got $code, want $want"; fi
}

echo ""
echo "=================================================="
echo " 1. Wrong password is rejected"
echo "=================================================="
rm -f "$JAR"
curl -s -c "$JAR" "$B/admin/login" -o /tmp/login.html
TOK=$(token_from /tmp/login.html)
CODE=$(curl -s -b "$JAR" -c "$JAR" -o /tmp/bad.html -w "%{http_code}" -X POST "$B/admin/login" \
  --data-urlencode "_token=$TOK" --data-urlencode "email=admin@medicaremini.radiants.net.et" \
  --data-urlencode "password=wrong-password")
[ "$CODE" = "302" ] && ok "bad password redirects back (no session granted)" || bad "unexpected $CODE"
curl -s -b "$JAR" -o /tmp/d.html -w "%{http_code}" "$B/admin/dashboard" | grep -q 302 \
  && ok "dashboard still blocked after failed login" || bad "dashboard reachable after failed login"

echo ""
echo "=================================================="
echo " 2. POST without a CSRF token is refused"
echo "=================================================="
rm -f "$JAR"
curl -s -c "$JAR" "$B/admin/login" -o /dev/null
CODE=$(curl -s -b "$JAR" -o /dev/null -w "%{http_code}" -X POST "$B/admin/login" \
  --data-urlencode "email=admin@medicaremini.radiants.net.et" --data-urlencode "password=Test#Passw0rd!2026")
[ "$CODE" = "419" ] && ok "missing CSRF token -> 419" || bad "missing CSRF token -> $CODE (want 419)"

CODE=$(curl -s -b "$JAR" -o /dev/null -w "%{http_code}" -X POST "$B/contact" \
  --data-urlencode "name=Bot" --data-urlencode "phone=0911111111" --data-urlencode "message=spam spam spam")
[ "$CODE" = "419" ] && ok "public form without CSRF -> 419" || bad "public form without CSRF -> $CODE"

echo ""
echo "=================================================="
echo " 3. SUPER ADMIN sees everything"
echo "=================================================="
login_as "admin@medicaremini.radiants.net.et" "super_admin"
expect "/admin/dashboard"        200 "dashboard"
expect "/admin/appointments"     200 "appointments"
expect "/admin/appointments/day" 200 "day sheet"
expect "/admin/appointments/create" 200 "new appointment form"
expect "/admin/payments"         200 "payments queue"
expect "/admin/payments/methods" 200 "payment methods"
expect "/admin/doctors"          200 "doctors"
expect "/admin/doctors/create"   200 "add doctor"
expect "/admin/services"         200 "services"
expect "/admin/packages"         200 "packages"
expect "/admin/facilities"       200 "facilities"
expect "/admin/articles"         200 "articles"
expect "/admin/articles/create"  200 "new article"
expect "/admin/inquiries"        200 "enquiries"
expect "/admin/users"            200 "staff accounts"
expect "/admin/settings"         200 "settings"
expect "/admin/settings/audit"   200 "audit trail"
expect "/admin/settings/mail"    200 "mail queue"
echo "  -- admin search (exercises the :q fix on every repo) --"
expect "/admin/appointments?q=Express"  200 "appointment search"
expect "/admin/payments?q=TXN"          200 "payment search"
expect "/admin/inquiries?q=test"        200 "enquiry search"
expect "/admin/articles?q=heart"        200 "article search"
expect "/admin/doctors?q=Dawit"         200 "doctor search"
expect "/admin/services?q=lab"          200 "service search"

echo ""
echo "=================================================="
echo " 4. RECEPTIONIST is fenced out of finance & users"
echo "=================================================="
login_as "reception@medicaremini.radiants.net.et" "receptionist"
expect "/admin/appointments" 200 "appointments  (allowed)"
expect "/admin/payments"     200 "payments read (allowed)"
expect "/admin/inquiries"    200 "enquiries     (allowed)"
expect "/admin/users"        403 "staff accounts BLOCKED"
expect "/admin/settings"     403 "settings      BLOCKED"
expect "/admin/articles"     403 "articles      BLOCKED"

echo ""
echo "=================================================="
echo " 5. ACCOUNTANT is fenced out of scheduling & CMS"
echo "=================================================="
login_as "finance@medicaremini.radiants.net.et" "accountant"
expect "/admin/payments"           200 "payments      (allowed)"
expect "/admin/payments/methods"   200 "methods       (allowed)"
expect "/admin/appointments"       200 "appointments read (allowed)"
expect "/admin/appointments/create" 403 "create booking BLOCKED"
expect "/admin/doctors"            403 "doctors        BLOCKED"
expect "/admin/users"              403 "staff accounts BLOCKED"
expect "/admin/settings"           403 "settings       BLOCKED"

echo ""
echo "=================================================="
echo " 6. PHYSICIAN sees only their own clinical view"
echo "=================================================="
login_as "dawit@medicaremini.radiants.net.et" "physician"
expect "/admin/dashboard"    200 "clinical dashboard (allowed)"
expect "/admin/appointments" 200 "own queue          (allowed)"
expect "/admin/articles"     200 "articles           (allowed)"
expect "/admin/payments"     403 "payments       BLOCKED"
expect "/admin/users"        403 "staff accounts BLOCKED"
expect "/admin/settings"     403 "settings       BLOCKED"
expect "/admin/doctors"      403 "doctor CMS     BLOCKED"

echo ""
echo "=================================================="
printf "  %d passed, %d failed\n" "$PASS" "$FAIL"
echo "=================================================="
exit $([ "$FAIL" -gt 0 ] && echo 1 || echo 0)
