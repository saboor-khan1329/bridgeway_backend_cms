# Bridgeway Digital Google Console Notes

The Service Finder module is optional and disabled by default in local Bridgeway
configuration (`SERVICE_FINDER_ENABLED=false`). Do not add Google Maps keys to
local `.env` unless this module is intentionally being tested.

If the module is re-enabled, use a new Google Cloud project for Bridgeway
Digital and restrict browser keys to:

```text
https://bridgewaydigital.com/*
https://www.bridgewaydigital.com/*
http://localhost:3000/*
http://127.0.0.1:3000/*
```

Keep API keys out of tracked files. Store local keys only in `.env`.
