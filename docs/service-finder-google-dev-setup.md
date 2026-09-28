# Bridgeway Digital Service Finder Local Setup

Service Finder is retained as optional backend infrastructure, but the current
Bridgeway frontend does not depend on it. Local environments should leave:

```env
SERVICE_FINDER_ENABLED=false
```

If development work explicitly targets this module:

1. Create a Bridgeway-specific Google Cloud project.
2. Add a restricted browser key to backend `.env`.
3. Keep `FRONTEND_URL=http://localhost:3000` for local traffic.
4. Keep `WEBSITE_URL=https://bridgewaydigital.com` for public SEO URLs.
5. Verify `/api/frontend/service-finder` only after the feature is enabled.

Do not reuse retired project names, domains, API keys, or exported site data.
