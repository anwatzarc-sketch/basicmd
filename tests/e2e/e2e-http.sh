#!/usr/bin/env bash
# Real patient journey over HTTP, plus row-level scoping and header checks.
set -u

B=http://127.0.0.1:8000
MYSQL="/c/xampp/mysql/bin/mysql.exe"
JAR=/tmp/patient.txt
PASS=0; FAIL=0
ok()  { PASS=$((PASS+1)); printf "  OK   %s\n" "$1"; }
bad() { FAIL=$((FAIL+1)); printf "  FAIL %s\n" "$1"; }

tok() { grep -oE 'name="_token" value="[^"]+"' "$1" | head -1 | grep -oE 'value="[^"]+"' | cut -d'"' -f2; }

echo ""
echo "=================================================="
echo " 1. Patient books through the public form"
echo "=================================================="
# Re-run safety: drop the patient this script books.
"$MYSQL" -u root MediCareMini -e "DELETE FROM appointments WHERE patient_phone='+251911777888';" 2>/dev/null
rm -f "$JAR"
curl -s -c "$JAR" "$B/book" -o /tmp/bookform.html
T=$(tok /tmp/bookform.html)
[ -n "$T" ] && ok "booking form carries a CSRF token" || bad "no CSRF token on /book"

# A weekday inside the horizon.
DATE=$(php -r '$d=new DateTimeImmutable("+15 days"); while((int)$d->format("w")===0) $d=$d->modify("+1 day"); echo $d->format("Y-m-d");')

CODE=$(curl -s -b "$JAR" -c "$JAR" -o /dev/null -w "%{http_code}" -D /tmp/h.txt -X POST "$B/book" \
  --data-urlencode "_token=$T" \
  --data-urlencode "patient_name=Alemnesh Haile" \
  --data-urlencode "patient_phone=0911777888" \
  --data-urlencode "patient_email=alemnesh@example.et" \
  --data-urlencode "service_id=1" \
  --data-urlencode "doctor_id=2" \
  --data-urlencode "appointment_date=$DATE" \
  --data-urlencode "time_slot=14:00-16:00" \
  --data-urlencode "queue_tier=express" \
  --data-urlencode "patient_notes=Occasional chest tightness when climbing stairs.")

[ "$CODE" = "302" ] && ok "booking accepted (302 to payment)" || bad "booking returned $CODE"

REF=$("$MYSQL" -u root -N -B MediCareMini -e "SELECT booking_ref FROM appointments WHERE patient_phone='+251911777888' ORDER BY id DESC LIMIT 1;")
[ -n "$REF" ] && ok "appointment persisted: $REF" || bad "no appointment row created"

LOC=$(grep -i '^location:' /tmp/h.txt | tr -d '\r' | sed 's/.*: //')
echo "$LOC" | grep -q "/pay" && ok "redirected to the payment step" || bad "unexpected redirect: $LOC"

# Express surcharge should be on the row.
read -r TOTAL SUR TIER <<< "$("$MYSQL" -u root -N -B MediCareMini -e \
  "SELECT total_amount, surcharge_amount, queue_tier FROM appointments WHERE booking_ref='$REF';")"
[ "$TIER" = "express" ] && ok "express tier stored" || bad "tier=$TIER"
php -r "exit(abs($SUR - 240.00) < 0.01 ? 0 : 1);" && ok "surcharge = ETB $SUR (20% of 1200)" || bad "surcharge=$SUR"
php -r "exit(abs($TOTAL - 1440.00) < 0.01 ? 0 : 1);" && ok "total = ETB $TOTAL" || bad "total=$TOTAL"

echo ""
echo "=================================================="
echo " 2. Booking pages are reachable by reference"
echo "=================================================="
for p in "/booking/$REF" "/booking/$REF/pay"; do
  C=$(curl -s -o /tmp/p.html -w "%{http_code}" "$B$p")
  [ "$C" = "200" ] && ok "$p (200)" || bad "$p -> $C"
done
grep -q "$REF" /tmp/p.html && ok "payment page shows the reference" || bad "reference missing from payment page"
grep -qE '1000XXXXXXXXX|Commercial Bank' /tmp/p.html && ok "transfer instructions rendered" || bad "no bank details on payment page"

echo ""
echo "=================================================="
echo " 3. Booking pages are noindex (they contain PHI)"
echo "=================================================="
curl -s "$B/booking/$REF" | grep -q 'name="robots" content="noindex' \
  && ok "booking page is noindex" || bad "booking page is indexable"
curl -s "$B/robots.txt" | grep -q 'Disallow: /booking/' \
  && ok "robots.txt disallows /booking/" || bad "robots.txt missing /booking/ rule"

echo ""
echo "=================================================="
echo " 4. Lookup needs BOTH reference and phone"
echo "=================================================="
rm -f "$JAR"; curl -s -c "$JAR" "$B/my-booking" -o /tmp/lk.html; T=$(tok /tmp/lk.html)
# Right reference, wrong phone -> refused.
curl -s -b "$JAR" -c "$JAR" -o /dev/null -D /tmp/h2.txt -X POST "$B/my-booking" \
  --data-urlencode "_token=$T" --data-urlencode "reference=$REF" --data-urlencode "phone=0911000000"
grep -i '^location:' /tmp/h2.txt | grep -q 'my-booking' \
  && ok "wrong phone refused (bounced back to lookup)" || bad "wrong phone was accepted"

rm -f "$JAR"; curl -s -c "$JAR" "$B/my-booking" -o /tmp/lk.html; T=$(tok /tmp/lk.html)
curl -s -b "$JAR" -c "$JAR" -o /dev/null -D /tmp/h3.txt -X POST "$B/my-booking" \
  --data-urlencode "_token=$T" --data-urlencode "reference=$REF" --data-urlencode "phone=0911777888"
grep -i '^location:' /tmp/h3.txt | grep -q "booking/$REF" \
  && ok "correct reference + phone resolves" || bad "valid lookup failed"

echo ""
echo "=================================================="
echo " 5. Doctor cannot reach another doctor's patient"
echo "=================================================="
# The booking above is assigned to doctor_id 2 (Dawit). Dawit's login IS linked
# to doctor 2 only if a users<->doctors link exists; link it now.
"$MYSQL" -u root MediCareMini -e "UPDATE doctors SET user_id=NULL WHERE user_id=(SELECT id FROM users WHERE email='dawit@medicaremini.radiants.net.et'); UPDATE doctors SET user_id=(SELECT id FROM users WHERE email='dawit@medicaremini.radiants.net.et') WHERE id=2;" 2>/dev/null

OWN_ID=$("$MYSQL" -u root -N -B MediCareMini -e "SELECT id FROM appointments WHERE doctor_id=2 AND deleted_at IS NULL ORDER BY id DESC LIMIT 1;")
OTHER_ID=$("$MYSQL" -u root -N -B MediCareMini -e "SELECT id FROM appointments WHERE doctor_id<>2 AND doctor_id IS NOT NULL AND deleted_at IS NULL ORDER BY id DESC LIMIT 1;")

rm -f "$JAR"; curl -s -c "$JAR" "$B/admin/login" -o /tmp/l.html
T=$(grep -oE 'name="_token" value="[^"]+"' /tmp/l.html | head -1 | cut -d'"' -f4)
curl -s -b "$JAR" -c "$JAR" -o /dev/null -X POST "$B/admin/login" \
  --data-urlencode "_token=$T" --data-urlencode "email=dawit@medicaremini.radiants.net.et" \
  --data-urlencode "password=Test#Passw0rd!2026"

C=$(curl -s -b "$JAR" -o /dev/null -w "%{http_code}" "$B/admin/appointments/$OWN_ID")
[ "$C" = "200" ] && ok "doctor CAN open their own appointment #$OWN_ID (200)" || bad "own appointment -> $C"

C=$(curl -s -b "$JAR" -o /dev/null -w "%{http_code}" "$B/admin/appointments/$OTHER_ID")
[ "$C" = "403" ] && ok "doctor CANNOT open another doctor's #$OTHER_ID (403)" || bad "another doctor's appointment -> $C (want 403)"

echo ""
echo "=================================================="
echo " 6. Security headers"
echo "=================================================="
curl -s -D /tmp/hdr.txt -o /dev/null "$B/"
for h in "content-security-policy" "x-content-type-options" "x-frame-options" "referrer-policy" "permissions-policy"; do
  grep -qi "^$h:" /tmp/hdr.txt && ok "$h present" || bad "$h MISSING"
done
grep -i '^content-security-policy:' /tmp/hdr.txt | grep -q "nonce-" \
  && ok "CSP carries a per-request nonce" || bad "CSP has no nonce"
grep -i '^content-security-policy:' /tmp/hdr.txt | grep -q "script-src 'self' 'nonce-" \
  && ok "script-src has no unsafe-inline" || bad "script-src may allow unsafe-inline"

curl -s -D /tmp/hdr2.txt -o /dev/null -b "$JAR" "$B/admin/dashboard"
grep -i '^cache-control:' /tmp/hdr2.txt | grep -qi 'no-store' \
  && ok "admin pages are no-store" || bad "admin page cacheable"

echo ""
echo "=================================================="
printf "  %d passed, %d failed\n" "$PASS" "$FAIL"
echo "=================================================="
exit $([ "$FAIL" -gt 0 ] && echo 1 || echo 0)
