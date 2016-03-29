# FT Authentication client

## An important note on authentication from cookie

Please note that if you enable authentication from cookie with this helper, there is currently no verification that the cookie is valid and created by FT.com.  The cookie supports a feature for validation (the session key or "skey") but this isn't enabled in DAM due to multiple concurrent sessions not being enabled.

If cookie authentication is enabled that means that a user can easily fake a cookie and be automatically logged in as another user by just changing the ID.

So please use cookie authentication very carefully: don't use it on any site storing non-trivial user data, and only where the convenience outweighs the lack of security.  If any user data will be accessed it may be better to log the user in yourself originally, then storing the EID hashed or validated against a database session before automatically logging that user in again.
