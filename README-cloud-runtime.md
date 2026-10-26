# Shared BlokStor node manager

The same PHP source is packaged into Bitcoin, Litecoin, and Dogecoin product
AMI archives. Product-specific credentials and branding come from each launch.
RPC stays on the local/private network; this dashboard is not a public JSON-RPC
proxy or a smart-contract execution service.

## Authentication and errors

SSO trusts Apache's authenticated `REMOTE_USER` only when `SSO_CLIENT_ID` is
configured in `node.env`. Caller-supplied `OIDC_CLAIM_*` request headers never
establish a login. A configured SSO connection without a trusted server identity
fails closed. Password login remains available for standalone/local deployments.
Sessions rotate on login and cookies use HttpOnly, SameSite, the root path, and
Secure when HTTPS is active. Missing credentials fail closed with a setup error.
RPC errors retain their numeric code, are HTML-escaped, and never include a
stack trace or automatically disable TLS verification.

## Chain differences

The dashboard handles nodes with no peers, zero traffic, genesis-only history,
and no loaded wallet. Missing Dogecoin `uptime` is reported as unavailable;
older wallet balances come from `getwalletinfo` when `getbalances` is unsupported.
Block fees use `getblockstats` when supported; unavailable fee data is labelled
rather than inferred from another chain's reward schedule.

## Verification

Read-only UI routes and login were exercised locally against isolated regtest
nodes: Bitcoin Core 31.1, Litecoin Core 0.21.5.5, and Dogecoin Core 1.14.9. Ten
pages per chain rendered without PHP warnings or RPC errors with a loaded wallet.
Unauthenticated and spoofed-SSO-header requests were denied. These checks do not
replace a real post-build AMI launch, HTTPS, or identity-provider callback check.
No new CI checks or AWS permissions are required by this change.
