# auth-audit Specification

## Purpose

Logging of all Fortify authentication events: login success and failure, logout, registration, password reset, two-factor authentication enable/disable, and email verification. Each entry captures causer, context, and request metadata.

## Requirements

| # | Requirement | Strength | Notes |
|---|-------------|----------|-------|
| R1 | Successful login MUST produce an audit entry | MUST | Description: `login`, causer=user, log_name=`auth` |
| R2 | Failed login MUST produce an audit entry | MUST | causer=null, properties include attempted username/email |
| R3 | Logout MUST produce an audit entry | MUST | Description: `logout`, causer=user, log_name=`auth` |
| R4 | User registration MUST produce an audit entry | MUST | Injected in `CreateNewUser` action; causer=new user |
| R5 | Password reset completion MUST produce an audit entry | MUST | Injected in `ResetUserPassword` action; causer=user |
| R6 | 2FA enable MUST produce an audit entry | MUST | Listen to `TwoFactorAuthenticationEnabled` event |
| R7 | 2FA disable MUST produce an audit entry | MUST | Listen to `TwoFactorAuthenticationDisabled` event |
| R8 | Email verification MUST produce an audit entry | MUST | Listen to `Verified` event (MustVerifyEmail) |
| R9 | Auth log entries MUST include request IP and user agent | MUST | Stored in Activitylog `properties` column |
| R10 | Auth entries MUST NOT log passwords or 2FA secrets | MUST | Excluded by config; log_name=`auth` enforces this |
| R11 | Failed login entries SHALL include `login` as subject for rate-limit context | SHOULD | Properties: `{email, ip}` |

### Scenario: User logs in successfully

- GIVEN a registered user with verified email
- WHEN the user submits valid credentials at `/login`
- THEN an activity entry is created with `log_name=auth`, `description=login`, `causer`=the user
- AND the entry's properties contain `ip` and `user_agent`

### Scenario: User fails login

- GIVEN a registered user
- WHEN the user submits invalid credentials at `/login`
- THEN an activity entry is created with `log_name=auth`, `description=failed_login`, `causer=null`
- AND the entry's properties contain the attempted `email` and `ip`

### Scenario: User logs out

- GIVEN an authenticated user
- WHEN the user clicks logout or sends POST `/logout`
- THEN an activity entry is created with `description=logout`, `causer`=the user

### Scenario: New user registers

- GIVEN the registration feature is enabled
- WHEN a visitor submits the registration form and the user is created
- THEN an activity entry is created with `description=registered`, `causer`=the new user
- AND the properties include `name` and `email` but NOT `password`

### Scenario: User enables two-factor authentication

- GIVEN an authenticated user
- WHEN the user completes 2FA setup (scan QR, confirm code)
- THEN `TwoFactorAuthenticationEnabled` event fires
- AND the listener records `description=two_factor_enabled`, `causer`=user

### Scenario: User verifies email

- GIVEN a newly registered user with unverified email
- WHEN the user clicks the verification link in the email
- THEN `Verified` event fires
- AND the listener records `description=email_verified`, `causer`=user
