# Security Policy

## Supported versions

Only the latest minor release receives security fixes. Please upgrade before reporting an issue if you are on an older version.

| Version | Supported |
|---------|-----------|
| 2.x     | Yes       |
| 1.x     | No        |

## Reporting a vulnerability

**Do not open a public GitHub issue for security problems.**

Instead, please report vulnerabilities privately using one of the following channels:

- GitHub's [private vulnerability reporting](https://github.com/rafaelpessoap/rest-email-api-mailer/security/advisories/new) (preferred)
- Or open a minimal public issue asking for a secure contact channel; avoid including proof-of-concept details

Please include:

- A clear description of the issue and its impact
- Reproduction steps or a minimal proof-of-concept
- The plugin version and WordPress/PHP versions where the issue reproduces
- Any mitigation or fix ideas you have

You should receive an initial acknowledgement within 7 days. A coordinated disclosure window of up to 90 days will be agreed case-by-case depending on severity.

## Scope

In scope:

- Authentication / authorization bypass in the admin settings
- Stored or reflected XSS on any admin screen rendered by this plugin
- SQL injection through the plugin's code paths
- Server-Side Request Forgery via the plugin
- Leakage of the API key or log contents to unauthenticated users
- Any flaw that would cause the plugin to send emails without the site owner's authorization

Out of scope:

- Issues that require an already-compromised WordPress administrator
- Vulnerabilities in WordPress core, other plugins or the Cyberpanel service itself
- Clickjacking on pages without sensitive actions
- Missing security headers unrelated to the plugin's functionality

## Safe harbor

Good-faith security research is welcome. If you follow this policy and avoid privacy violations, destruction of data or service disruption, no legal action will be pursued against you.
