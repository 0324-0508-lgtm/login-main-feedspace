# TODO
- [x] Locate OTP expiry (10 minutes) in DB writes and email text.
- [x] Change OTP expiry everywhere from 10 minutes to 3 minutes.
- [x] Fix resend button to hit correct resend endpoint (auth/api/send-otp.php).
- [x] Add resend countdown timer (3 minutes) on verify-account page.
- [ ] Verify PHP files compile (no syntax errors) after edits.
- [ ] Quick manual test: login -> OTP -> resend disabled/enabled after 3 minutes -> verify OTP.

